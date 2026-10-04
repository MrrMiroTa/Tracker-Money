<?php
/**
 * auth-guard.php - Secure login flow shared by api-v2.php and api-v6.php
 *
 *  - Brute-force protection (lockout after repeated failures)
 *  - MFA (Google Authenticator) is verified ON THE SERVER
 *  - Session id regenerated after login (anti session-fixation)
 *  - Same error for "wrong user" and "wrong password" (no user enumeration)
 */

require_once __DIR__ . '/security-bootstrap.php';
require_once __DIR__ . '/mfa-helper.php';

const LOGIN_MAX_FAILS_PER_USER_IP = 5;   // failures allowed per username+IP ...
const LOGIN_MAX_FAILS_PER_IP      = 20;  // ... and per IP overall
const LOGIN_WINDOW_MINUTES        = 15;  // ... inside this window

function guardEnsureTable(PDO $db): void
{
    $db->exec("CREATE TABLE IF NOT EXISTS `login_attempts` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `username` VARCHAR(100) NOT NULL,
        `ip_address` VARCHAR(45) NOT NULL,
        `success` TINYINT(1) NOT NULL DEFAULT 0,
        `attempted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY `idx_user_ip_time` (`username`, `ip_address`, `attempted_at`),
        KEY `idx_ip_time` (`ip_address`, `attempted_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function guardIsLockedOut(PDO $db, string $username, string $ip): bool
{
    $w = (int)LOGIN_WINDOW_MINUTES;

    $s = $db->prepare("SELECT COUNT(*) FROM login_attempts
        WHERE success = 0 AND username = ? AND ip_address = ?
          AND attempted_at > (NOW() - INTERVAL {$w} MINUTE)");
    $s->execute([$username, $ip]);
    if ((int)$s->fetchColumn() >= LOGIN_MAX_FAILS_PER_USER_IP) {
        return true;
    }

    $s = $db->prepare("SELECT COUNT(*) FROM login_attempts
        WHERE success = 0 AND ip_address = ?
          AND attempted_at > (NOW() - INTERVAL {$w} MINUTE)");
    $s->execute([$ip]);
    return (int)$s->fetchColumn() >= LOGIN_MAX_FAILS_PER_IP;
}

function guardRecord(PDO $db, string $username, string $ip, bool $success): void
{
    $db->prepare("INSERT INTO login_attempts (username, ip_address, success) VALUES (?, ?, ?)")
       ->execute([$username, $ip, $success ? 1 : 0]);

    if ($success) { // forgive earlier typos
        $db->prepare("DELETE FROM login_attempts WHERE success = 0 AND username = ? AND ip_address = ?")
           ->execute([$username, $ip]);
    }
    if (random_int(1, 100) === 1) { // housekeeping
        $db->exec("DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 1 DAY)");
    }
}

function guardJson(int $code, array $payload): void
{
    http_response_code($code);
    echo json_encode($payload);
}

function handleSecureLogin($db): void
{
    $raw      = json_decode(file_get_contents('php://input'), true);
    $data     = is_array($raw) ? $raw : [];
    $username = isset($data['username']) ? trim((string)$data['username']) : '';
    $password = isset($data['password']) ? (string)$data['password'] : '';
    $mfaCode  = isset($data['mfa_code']) ? preg_replace('/\D/', '', (string)$data['mfa_code']) : '';

    if ($username === '' || $password === '' || strlen($username) > 100 || strlen($password) > 1024) {
        guardJson(400, ['status' => 'error', 'message' => 'Username and password are required.']);
        return;
    }

    $ip     = sec_client_ip();
    $keyUser = strtolower($username);

    try {
        guardEnsureTable($db);

        if (guardIsLockedOut($db, $keyUser, $ip)) {
            header('Retry-After: ' . (LOGIN_WINDOW_MINUTES * 60));
            guardJson(429, ['status' => 'error',
                'message' => 'ព្យាយាមចូលច្រើនដងពេក។ សូមរង់ចាំ ' . LOGIN_WINDOW_MINUTES . ' នាទី រួចសាកល្បងម្តងទៀត។']);
            return;
        }

        $stmt = $db->prepare("SELECT id, username, password_hash, role, status, mfa_secret
                              FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        // Always run one bcrypt verify so response time doesn't reveal whether the user exists.
        static $dummyHash = null;
        $dummyHash = $dummyHash ?? password_hash('timing-equaliser', PASSWORD_BCRYPT);
        $passwordOk = password_verify($password, $user['password_hash'] ?? $dummyHash) && $user;

        if (!$passwordOk) {
            guardRecord($db, $keyUser, $ip, false);
            guardJson(401, ['status' => 'error', 'message' => 'ឈ្មោះអ្នកប្រើប្រាស់ ឬពាក្យសម្ងាត់មិនត្រឹមត្រូវឡើយ!']);
            return;
        }

        if ($user['status'] !== 'active') {
            guardJson(403, ['status' => 'error', 'message' => 'គណនីនេះត្រូវបានផ្អាក ឬមិនទាន់ដំណើរការ។']);
            return;
        }

        $hasMfa  = !empty($user['mfa_secret']);
        $isAdmin = in_array($user['role'], ['admin', 'super_admin'], true);
        $mustMfa = filter_var(getenv('REQUIRE_MFA_FOR_ADMINS') ?: 'false', FILTER_VALIDATE_BOOLEAN);

        if ($isAdmin && $mustMfa && !$hasMfa) {
            guardJson(403, ['status' => 'error',
                'message' => 'គណនី Admin ត្រូវតែបើក MFA។ សូមទាក់ទង Super Admin ដើម្បីដំឡើង MFA។']);
            return;
        }

        if ($hasMfa) {
            if ($mfaCode === '') {
                guardJson(401, ['status' => 'error', 'mfa_required' => true,
                    'message' => 'សូមបញ្ចូលលេខកូដ MFA ៦ ខ្ទង់ពី Google Authenticator។']);
                return;
            }
            if (!MFAHelper::verifyCode($user['mfa_secret'], $mfaCode)) {
                guardRecord($db, $keyUser, $ip, false);
                guardJson(401, ['status' => 'error', 'mfa_required' => true,
                    'message' => 'លេខកូដ MFA មិនត្រឹមត្រូវ ឬផុតកំណត់។']);
                return;
            }
        }

        guardRecord($db, $keyUser, $ip, true);
        sec_login_session($user);

        if (function_exists('logAdminActivity')) {
            logAdminActivity($db, 'LOGIN', (int)$user['id'], $hasMfa ? 'Login OK (password + MFA)' : 'Login OK (password only)');
        }

        guardJson(200, [
            'status'  => 'success',
            'message' => 'Login successful!',
            'user'    => ['user_id' => (int)$user['id'], 'username' => $user['username'], 'role' => $user['role']],
        ]);
    } catch (PDOException $e) {
        error_log('Login error: ' . $e->getMessage());
        guardJson(500, ['status' => 'error', 'message' => 'មានបញ្ហាបច្ចេកទេសក្នុងការចូលប្រើប្រាស់!']);
    }
}
