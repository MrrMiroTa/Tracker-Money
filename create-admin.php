<?php
/**
 * create-admin.php - create the first Super Admin from the command line.
 *
 *   php create-admin.php
 *
 * Replaces the old hard-coded accounts (admin123 / user123). Refuses to run from a browser.
 * After creating the account, log in and enable MFA from profile.php.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/config.php';

function ask(string $prompt, bool $hidden = false): string
{
    echo $prompt;
    if ($hidden && stripos(PHP_OS, 'WIN') !== 0) {
        system('stty -echo');
        $v = trim((string)fgets(STDIN));
        system('stty echo');
        echo "\n";
        return $v;
    }
    return trim((string)fgets(STDIN));
}

$username = ask('Username (3-50 chars: letters, numbers, _ . -): ');
if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) {
    fwrite(STDERR, "Invalid username.\n");
    exit(1);
}

$password = ask('Password (min 12, upper+lower+digit+symbol): ', true);
$confirm  = ask('Repeat password: ', true);
if ($password !== $confirm) {
    fwrite(STDERR, "Passwords do not match.\n");
    exit(1);
}
if (strlen($password) < 12 || !preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password)
    || !preg_match('/[0-9]/', $password) || !preg_match('/[^a-zA-Z0-9]/', $password)) {
    fwrite(STDERR, "Password too weak.\n");
    exit(1);
}

$db = getSecureDBConnection();
$exists = $db->prepare('SELECT COUNT(*) FROM users WHERE username = ?');
$exists->execute([$username]);
if ((int)$exists->fetchColumn() > 0) {
    fwrite(STDERR, "That username already exists.\n");
    exit(1);
}

$db->prepare("INSERT INTO users (username, password_hash, role, status) VALUES (?, ?, 'super_admin', 'active')")
   ->execute([$username, password_hash($password, PASSWORD_BCRYPT, ['cost' => 12])]);

echo "Super Admin '{$username}' created. Log in, then enable MFA at profile.php.\n";
