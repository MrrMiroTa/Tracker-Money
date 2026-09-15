<?php
/**
 * profile.php - Ultra-Modern Profile & Security Management Dashboard
 * Part of the Khmer Payment Tracker and Financial Management System
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'config.php';
require_once 'mfa-helper.php';

$db = getSecureDBConnection();
$userId = $_SESSION['user_id'];

$stmt = $db->prepare("SELECT id, username, role, status, mfa_secret, created_at FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$role = $user['role'] ?? 'user';
$feedback = ['status' => '', 'message' => ''];

// 1. Password Change Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $current_pass = $_POST['current_password'] ?? '';
    $new_pass = $_POST['new_password'] ?? '';
    $confirm_pass = $_POST['confirm_password'] ?? '';

    $pwStmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
    $pwStmt->execute([$userId]);
    $pwRow = $pwStmt->fetch();

    if (!$pwRow || !password_verify($current_pass, $pwRow['password_hash'])) {
        $feedback = ['status' => 'error', 'message' => 'ពាក្យសម្ងាត់បច្ចុប្បន្នមិនត្រឹមត្រូវឡើយ។'];
    } elseif ($new_pass !== $confirm_pass) {
        $feedback = ['status' => 'error', 'message' => 'ការបញ្ជាក់ពាក្យសម្ងាត់ថ្មីមិនត្រូវគ្នានោះទេ។'];
    } else {
        $minLength = ($role === 'super_admin' || $role === 'admin') ? 12 : 8;
        
        if (strlen($new_pass) < $minLength) {
            $feedback = ['status' => 'error', 'message' => "ពាក្យសម្ងាត់សម្រាប់តួនាទី " . strtoupper($role) . " ត្រូវតែមានប្រវែងយ៉ាងតិច $minLength ខ្ទង់។"];
        } elseif (!preg_match('/[A-Z]/', $new_pass)) {
            $feedback = ['status' => 'error', 'message' => 'ពាក្យសម្ងាត់ត្រូវតែមានអក្សរធំ (A-Z) យ៉ាងហោចណាស់មួយខ្ទង់។'];
        } elseif (!preg_match('/[a-z]/', $new_pass)) {
            $feedback = ['status' => 'error', 'message' => 'ពាក្យសម្ងាត់ត្រូវតែមានអក្សរតូច (a-z) យ៉ាងហោចណាស់មួយខ្ទង់។'];
        } elseif (!preg_match('/[0-9]/', $new_pass)) {
            $feedback = ['status' => 'error', 'message' => 'ពាក្យសម្ងាត់ត្រូវតែមានលេខ (0-9) យ៉ាងហោចណាស់មួយខ្ទង់។'];
        } elseif (!preg_match('/[^a-zA-Z0-9]/', $new_pass)) {
            $feedback = ['status' => 'error', 'message' => 'ពាក្យសម្ងាត់ត្រូវតែមាននិមិត្តសញ្ញាពិសេស (ដូចជា @, #, $, %, ^, &, *) យ៉ាងហោចណាស់មួយខ្ទង់។'];
        } else {
            $new_hash = password_hash($new_pass, PASSWORD_BCRYPT);
            $updateStmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $updateStmt->execute([$new_hash, $userId]);

            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $logStmt = $db->prepare("INSERT INTO audit_logs (user_id, action, target_user_id, details, ip_address) VALUES (?, 'CHANGE_PASSWORD', ?, 'User updated password', ?)");
            $logStmt->execute([$userId, $userId, $ip]);

            $feedback = ['status' => 'success', 'message' => '🎉 ការផ្លាស់ប្តូរពាក្យសម្ងាត់ទទួលបានជោគជ័យ!'];
        }
    }
}

// 2. Setup MFA Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'setup_mfa') {
    $_SESSION['temp_mfa_secret'] = MFAHelper::generateSecret();
    header('Location: profile.php?setup_mfa=1');
    exit;
}

// 3. Activate MFA Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'activate_mfa') {
    $temp_secret = $_SESSION['temp_mfa_secret'] ?? '';
    $mfa_code = $_POST['mfa_code'] ?? '';

    if (empty($temp_secret)) {
        $feedback = ['status' => 'error', 'message' => 'មានបញ្ហាបច្ចេកទេសបណ្តោះអាសន្ន។ សូមសាកល្បងម្តងទៀត។'];
    } elseif (empty($mfa_code) || !MFAHelper::verifyCode($temp_secret, $mfa_code)) {
        $feedback = ['status' => 'error', 'message' => 'លេខកូដ MFA ៦ ខ្ទង់មិនត្រឹមត្រូវឡើយ។'];
    } else {
        $mfaStmt = $db->prepare("UPDATE users SET mfa_secret = ? WHERE id = ?");
        $mfaStmt->execute([$temp_secret, $userId]);
        
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $logStmt = $db->prepare("INSERT INTO audit_logs (user_id, action, target_user_id, details, ip_address) VALUES (?, 'ENABLE_MFA', ?, 'User activated MFA', ?)");
        $logStmt->execute([$userId, $userId, $ip]);

        unset($_SESSION['temp_mfa_secret']);
        $user['mfa_secret'] = $temp_secret;
        $feedback = ['status' => 'success', 'message' => '🎉 ការបើកដំណើរការ MFA ជោគជ័យ! គណនីរបស់អ្នកត្រូវបានការពារយ៉ាងរឹងមាំ។'];
    }
}

// 4. Disable MFA Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'disable_mfa') {
    $mfaStmt = $db->prepare("UPDATE users SET mfa_secret = NULL WHERE id = ?");
    $mfaStmt->execute([$userId]);

    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $logStmt = $db->prepare("INSERT INTO audit_logs (user_id, action, target_user_id, details, ip_address) VALUES (?, 'DISABLE_MFA', ?, 'User disabled MFA', ?)");
    $logStmt->execute([$userId, $userId, $ip]);

    $user['mfa_secret'] = null;
    $feedback = ['status' => 'success', 'message' => 'ការបិទ MFA ទទួលបានជោគជ័យ។'];
}

$isSetupMfa = isset($_GET['setup_mfa']) && $_GET['setup_mfa'] == '1' && !empty($_SESSION['temp_mfa_secret']);
$mfaSecretToShow = $isSetupMfa ? $_SESSION['temp_mfa_secret'] : ($user['mfa_secret'] ?? '');
$qrCodeUrl = (!empty($mfaSecretToShow)) ? MFAHelper::getQRCodeGoogleUrl($user['username'], $mfaSecretToShow, 'KhmerPaymentTracker') : '';
?>
<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ប្រវត្តិរូប និងកំណត់រចនាសម្ព័ន្ធសន្តិសុខ - Payment Tracker</title>
    <link href="https://fonts.googleapis.com/css2?family=Kantumruy+Pro:wght@300;400;600;700;800&family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="admin-style.css?v=26.0">
    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme === 'dark') {
                document.documentElement.classList.add('dark-mode');
                document.addEventListener('DOMContentLoaded', () => document.body.classList.add('dark-mode'));
            }
        })();
    </script>
</head>
<body>

    <!-- Sticky Navigation Bar -->
    <nav class="navbar">
        <div class="navbar-brand">
            <span>📊 ប្រព័ន្ធគ្រប់គ្រងហិរញ្ញវត្ថុ</span>
        </div>
        <button class="navbar-toggle" id="navbar-toggle-btn" aria-label="Toggle Navigation">
            <span class="bar"></span>
            <span class="bar"></span>
            <span class="bar"></span>
        </button>
        <div class="navbar-nav" id="navbar-menu">
            <a href="index.php">Dashboard</a>
            <a href="profile.php" class="active">ប្រវត្តិរូបផ្ទាល់ខ្លួន</a>
            <?php if ($role === 'super_admin' || $role === 'admin'): ?>
                <a href="archive-history.php">បណ្ណសារសវនកម្ម (History)</a>
            <?php endif; ?>
            <a href="pdf.php" target="_blank">ទាញយក PDF</a>
            <a href="export-csv.php" target="_blank">នាំចេញ CSV</a>
            <button id="dark-mode-toggle" onclick="toggleTheme()" class="btn" style="background: rgba(255,255,255,0.15); color: white; border: none; padding: 6px 14px; border-radius: 20px; cursor: pointer; font-size: 0.88rem;">
                🌙 Dark Mode
            </button>
            <a href="#" onclick="logoutUser(); return false;" class="logout-btn">ចាកចេញ</a>
        </div>
    </nav>

    <div class="profile-container">

        <!-- Feedback Alert Toast -->
        <?php if (!empty($feedback['message'])): ?>
            <div style="margin-bottom: 1.5rem; padding: 1rem 1.25rem; border-radius: var(--radius-lg); font-weight: 700; background: <?php echo $feedback['status'] === 'success' ? '#ecfdf5' : '#fef2f2'; ?>; color: <?php echo $feedback['status'] === 'success' ? '#047857' : '#991b1b'; ?>; border: 1px solid <?php echo $feedback['status'] === 'success' ? '#a7f3d0' : '#fecaca'; ?>;">
                <?php echo htmlspecialchars($feedback['message']); ?>
            </div>
        <?php endif; ?>

        <!-- Hero Profile Banner Card -->
        <div class="profile-hero-card">
            <div class="profile-avatar-box">
                👤
            </div>
            <div class="profile-hero-info">
                <h2><?php echo htmlspecialchars($user['username']); ?></h2>
                <p>គណនីគ្រប់គ្រងហិរញ្ញវត្ថុផ្លូវការ • បង្កើតនៅ៖ <?php echo date('d-M-Y', strtotime($user['created_at'])); ?></p>
                <div class="profile-badges-row">
                    <span class="profile-stat-pill">តួនាទី៖ <strong style="color: #a5b4fc; text-transform: uppercase;"><?php echo htmlspecialchars($role); ?></strong></span>
                    <span class="profile-stat-pill">ស្ថានភាព៖ <strong style="color: #34d399;">ACTIVE</strong></span>
                    <span class="profile-stat-pill">MFA៖ <strong style="color: <?php echo !empty($user['mfa_secret']) ? '#34d399' : '#f87171'; ?>;"><?php echo !empty($user['mfa_secret']) ? 'ENABLED' : 'DISABLED'; ?></strong></span>
                </div>
            </div>
        </div>

        <div class="profile-grid">

            <!-- 1. Daily Spending Limit Settings Card -->
            <div class="profile-card">
                <h3>🎯 កំណត់កម្រិតចំណាយប្រចាំថ្ងៃ (Daily Spending Limit)</h3>
                <p style="font-size: 0.9rem; color: var(--gray-text); margin-bottom: 1.25rem;">
                    កំណត់កម្រិតអតិបរមានៃការចំណាយក្នុងមួយថ្ងៃ (ឧទាហរណ៍៖ $5.00 ឬ 20,000 ៛)។ ប្រសិនបើលោកអ្នកបញ្ចូលប្រតិបត្តិការចំណាយក្នុងមួយថ្ងៃដែលលើសកម្រិតនេះ ប្រព័ន្ធនឹងបោះសារព្រមាន (Alert Warning) ភ្លាមៗ!
                </p>

                <form id="daily-limit-form">
                    <div class="form-group">
                        <label for="limit-usd">កម្រិតចំណាយជាដុល្លារ ($ USD / ថ្ងៃ)</label>
                        <input type="number" id="limit-usd" step="0.50" min="0" placeholder="5.00">
                    </div>
                    <div class="form-group">
                        <label for="limit-khr">កម្រិតចំណាយជារៀល (៛ KHR / ថ្ងៃ - គណនាស្វ័យប្រវត្តិ)</label>
                        <input type="number" id="limit-khr" step="1000" min="0" placeholder="20000">
                    </div>
                    <div class="toggle-switch-container" style="background: var(--gray-bg); padding: 10px 14px; border-radius: var(--radius-md); border: 1px solid var(--gray-border); margin-bottom: 1.25rem;">
                        <span style="font-size: 0.9rem; font-weight: 700;">បើកការ Alert ព្រមានពេលចំណាយលើសកម្រិតប្រចាំថ្ងៃ</span>
                        <label class="switch">
                            <input type="checkbox" id="limit-enabled" checked>
                            <span class="slider"></span>
                        </label>
                    </div>
                    <button type="button" onclick="saveDailySpendingLimitSettings()" class="btn-submit" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                        💾 រក្សាទុកកម្រិតចំណាយប្រចាំថ្ងៃ (Save Daily Limit)
                    </button>
                </form>
            </div>

            <!-- 2. Security & Password Change Card -->
            <div class="profile-card">
                <h3>🔐 ផ្លាស់ប្តូរពាក្យសម្ងាត់ (Change Password)</h3>
                <form method="POST" action="profile.php">
                    <input type="hidden" name="action" value="change_password">
                    
                    <div class="form-group">
                        <label for="current_password">ពាក្យសម្ងាត់បច្ចុប្បន្ន</label>
                        <input type="password" id="current_password" name="current_password" required placeholder="••••••••">
                    </div>
                    
                    <div class="form-group">
                        <label for="new_password">ពាក្យសម្ងាត់ថ្មី</label>
                        <input type="password" id="new_password" name="new_password" required placeholder="••••••••">
                        <small style="font-size: 0.8rem; color: var(--gray-text); display: block; margin-top: 4px;">
                            ត្រូវមានយ៉ាងតិច <?php echo ($role === 'super_admin' || $role === 'admin') ? '12' : '8'; ?> ខ្ទង់ មានអក្សរធំ, អក្សរតូច, លេខ, និងនិមិត្តសញ្ញាពិសេស (@#$%)
                        </small>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">បញ្ជាក់ពាក្យសម្ងាត់ថ្មី</label>
                        <input type="password" id="confirm_password" name="confirm_password" required placeholder="••••••••">
                    </div>

                    <button type="submit" class="btn-submit">
                        🔑 ធ្វើបច្ចុប្បន្នភាពពាក្យសម្ងាត់ (Update Password)
                    </button>
                </form>
            </div>

            <!-- 3. Multi-Factor Authentication (MFA) Setup Card -->
            <div class="profile-card" style="grid-column: 1 / -1;">
                <h3>🛡️ ការផ្ទៀងផ្ទាត់ពីរជំហាន (Google Authenticator MFA)</h3>
                
                <?php if (!empty($user['mfa_secret']) && !$isSetupMfa): ?>
                    <!-- State A: MFA Already Active -->
                    <div style="background: #ecfdf5; border-left: 5px solid #10b981; padding: 1.25rem; border-radius: var(--radius-md); margin-bottom: 1.25rem; color: #047857;">
                        <h4 style="margin: 0; font-size: 1.15rem; font-weight: 800;">✅ គណនីរបស់អ្នកត្រូវបានការពារដោយ MFA រួចជាស្រេច</h4>
                        <p style="margin: 0.5rem 0 0 0; font-size: 0.92rem; color: #065f46;">
                            រាល់ពេលចូលប្រើប្រាស់ (Login) ប្រព័ន្ធនឹងទាមទារលេខកូដ ៦ ខ្ទង់ពីកម្មវិធី Google Authenticator ដើម្បីធានាសន្តិសុខអតិបរមា។
                        </p>
                    </div>
                    <form method="POST" action="profile.php" onsubmit="return confirm('តើអ្នកពិតជាចង់បិទ MFA មែនទេ?');">
                        <input type="hidden" name="action" value="disable_mfa">
                        <button type="submit" class="btn" style="background: #ef4444; color: white; border: none; padding: 0.75rem 1.5rem; border-radius: var(--radius-md); font-weight: 700; cursor: pointer;">
                            🚫 បិទដំណើរការ MFA (Disable MFA)
                        </button>
                    </form>

                <?php elseif ($isSetupMfa): ?>
                    <!-- State B: MFA Setup in Progress -->
                    <div style="background: var(--gray-bg); padding: 1.5rem; border-radius: var(--radius-lg); border: 1px solid var(--gray-border); margin-bottom: 1.25rem;">
                        <p style="margin-top: 0; font-weight: 700; color: var(--dark);">
                            ជំហានទី ១៖ សូមស្កែន QR Code ខាងក្រោមដោយប្រើកម្មវិធី <strong>Google Authenticator</strong> ឬ <strong>Authy</strong>៖
                        </p>
                        <div style="text-align: center; margin: 1.25rem 0;">
                            <img src="<?php echo htmlspecialchars($qrCodeUrl); ?>" alt="MFA QR Code" style="width: 180px; height: 180px; border: 4px solid var(--white); border-radius: 12px; box-shadow: var(--shadow-card);">
                            <p style="font-size: 0.88rem; color: var(--gray-text); margin-top: 8px;">
                                កូដសម្ងាត់ (Secret Key)៖ <code style="background: #e2e8f0; padding: 2px 8px; border-radius: 4px; font-weight: bold; color: #1e293b;"><?php echo htmlspecialchars($mfaSecretToShow); ?></code>
                            </p>
                        </div>
                        
                        <form method="POST" action="profile.php">
                            <input type="hidden" name="action" value="activate_mfa">
                            <div class="form-group">
                                <label for="mfa_code">ជំហានទី ២៖ បញ្ចូលលេខកូដ ៦ ខ្ទង់ពីកម្មវិធី Google Authenticator</label>
                                <input type="text" id="mfa_code" name="mfa_code" required maxlength="6" placeholder="ឧ. 123456" style="text-align: center; font-size: 1.2rem; letter-spacing: 0.2em; font-weight: bold;">
                            </div>
                            <button type="submit" class="btn-submit">
                                ✅ ផ្ទៀងផ្ទាត់ និងបើកដំណើរការ MFA
                            </button>
                        </form>
                    </div>

                <?php else: ?>
                    <!-- State C: MFA Not Setup Yet -->
                    <p style="font-size: 0.92rem; color: var(--gray-text); margin-bottom: 1.25rem;">
                        ការផ្ទៀងផ្ទាត់ពីរជំហាន (MFA) បន្ថែមស្រទាប់សន្តិសុខដ៏រឹងមាំសម្រាប់គណនីរបស់អ្នក ដោយទាមទារលេខកូដពីទូរស័ព្ទដៃបន្ថែមពីលើពាក្យសម្ងាត់។
                    </p>
                    <form method="POST" action="profile.php">
                        <input type="hidden" name="action" value="setup_mfa">
                        <button type="submit" class="btn-submit" style="background: linear-gradient(135deg, #4f46e5 0%, #3b82f6 100%); width: auto; padding: 0.75rem 1.75rem;">
                            🛡️ ចាប់ផ្តើមរៀបចំ MFA (Setup Google Authenticator)
                        </button>
                    </form>
                <?php endif; ?>
            </div>

        </div>

    </div>

    <script src="admin-integration.js?v=26.0"></script>
    <script>
        // Load & Sync Daily Spending Limit in Profile Page
        document.addEventListener('DOMContentLoaded', () => {
            const limitSettings = getDailySpendingLimit();
            const usdInput = document.getElementById('limit-usd');
            const khrInput = document.getElementById('limit-khr');
            const enabledCheck = document.getElementById('limit-enabled');

            if (usdInput && khrInput) {
                usdInput.value = limitSettings.usd;
                khrInput.value = limitSettings.khr;
                enabledCheck.checked = limitSettings.enabled;

                usdInput.addEventListener('input', (e) => {
                    const usdVal = parseFloat(e.target.value) || 0;
                    khrInput.value = Math.round(usdVal * currentUsdKhrRate);
                });

                khrInput.addEventListener('input', (e) => {
                    const khrVal = parseFloat(e.target.value) || 0;
                    usdInput.value = (khrVal / currentUsdKhrRate).toFixed(2);
                });
            }
        });

        function saveDailySpendingLimitSettings() {
            const usdVal = parseFloat(document.getElementById('limit-usd').value) || 5.00;
            const khrVal = parseFloat(document.getElementById('limit-khr').value) || (usdVal * currentUsdKhrRate);
            const enabled = document.getElementById('limit-enabled').checked;

            const settings = { enabled: enabled, usd: usdVal, khr: khrVal };
            localStorage.setItem('daily_spending_limit', JSON.stringify(settings));

            showToast(`🎉 រក្សាទុកកម្រិតចំណាយប្រចាំថ្ងៃ ($${usdVal.toFixed(2)} / ${Math.round(khrVal).toLocaleString()} ៛) រួចរាល់!`, 'success');
        }
    </script>
</body>
</html>
