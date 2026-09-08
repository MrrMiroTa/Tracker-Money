<?php
/**
 * profile-v2.php - ទំព័រប្រវត្តិរូបផ្ទាល់ខ្លួន និងការដំឡើងប្រព័ន្ធសន្តិសុខ ជាមួយការរចនាបែប Responsive & Clear
 * Part of the Khmer Payment Tracker and Financial Management System
 */

// ១. ចាប់ផ្តើម Session និងត្រួតពិនិត្យការចូលប្រើប្រាស់ (Session & Authorization Guard)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ប្រសិនបើមិនទាន់ចូលប្រើប្រាស់ទេ ត្រូវបញ្ជូនទៅទំព័រ Login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// ២. រួមបញ្ចូលឯកសារកំណត់រចនាសម្ព័ន្ធ និងប្រព័ន្ធជំនួយសន្តិសុខ
require_once 'config.php';
require_once 'mfa-helper.php';

$db = getSecureDBConnection();
$userId = $_SESSION['user_id'];

// ទាញយកព័ត៌មានអ្នកប្រើប្រាស់បច្ចុប្បន្នពី Database
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

// ៣. លំហូរការងារផ្លាស់ប្តូរពាក្យសម្ងាត់ (Password Change Handling)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $current_pass = $_POST['current_password'] ?? '';
    $new_pass = $_POST['new_password'] ?? '';
    $confirm_pass = $_POST['confirm_password'] ?? '';

    // ផ្ទៀងផ្ទាត់ពាក្យសម្ងាត់បច្ចុប្បន្ន
    $pwStmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
    $pwStmt->execute([$userId]);
    $pwRow = $pwStmt->fetch();

    if (!$pwRow || !password_verify($current_pass, $pwRow['password_hash'])) {
        $feedback = ['status' => 'error', 'message' => 'ពាក្យសម្ងាត់បច្ចុប្បន្នមិនត្រឹមត្រូវឡើយ។'];
    } elseif ($new_pass !== $confirm_pass) {
        $feedback = ['status' => 'error', 'message' => 'ការបញ្ជាក់ពាក្យសម្ងាត់ថ្មីមិនត្រូវគ្នានោះទេ។'];
    } else {
        // អនុវត្តគោលការណ៍ពាក្យសម្ងាត់រឹងមាំ (Enforce Proportional Password Policy)
        $role = $user['role'];
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
            // កែប្រែពាក្យសម្ងាត់ និងកត់ត្រាទុកក្នុងសវនកម្ម (Hash and Log Activity)
            $new_hash = password_hash($new_pass, PASSWORD_BCRYPT);
            $updateStmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $updateStmt->execute([$new_hash, $userId]);

            // កត់ត្រាទុកក្នុងតារាង Audit Log សម្រាប់សន្តិសុខ
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $logStmt = $db->prepare("INSERT INTO audit_logs (user_id, action, target_user_id, details, ip_address) VALUES (?, 'CHANGE_PASSWORD', ?, 'User updated their password successfully', ?)");
            $logStmt->execute([$userId, $userId, $ip]);

            $feedback = ['status' => 'success', 'message' => 'ការផ្លាស់ប្តូរពាក្យសម្ងាត់ទទួលបានជោគជ័យ!'];
        }
    }
}

// ៤. លំហូរការងាររៀបចំ និងផ្ទៀងផ្ទាត់ MFA (MFA Activation Workflow)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'setup_mfa') {
    // បង្កើត Secret Key ជាបណ្តោះអាសន្ន និងរក្សាទុកក្នុង Session
    $_SESSION['temp_mfa_secret'] = MFAHelper::generateSecret();
    header('Location: profile.php?setup_mfa=1');
    exit;
}

// ផ្ទៀងផ្ទាត់ និងបើកដំណើរការ MFA ជាផ្លូវការ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'activate_mfa') {
    $temp_secret = $_SESSION['temp_mfa_secret'] ?? '';
    $mfa_code = $_POST['mfa_code'] ?? '';

    if (empty($temp_secret)) {
        $feedback = ['status' => 'error', 'message' => 'មានបញ្ហាបច្ចេកទេសបណ្តោះអាសន្ន។ សូមសាកល្បងម្តងទៀត។'];
    } elseif (empty($mfa_code) || !MFAHelper::verifyCode($temp_secret, $mfa_code)) {
        $feedback = ['status' => 'error', 'message' => 'លេខកូដ MFA ៦ ខ្ទង់មិនត្រឹមត្រូវឡើយ។ សូមពិនិត្យកម្មវិធី Google Authenticator សារជាថ្មី។'];
    } else {
        // រក្សាទុកក្នុង Database ផ្លូវការ
        $mfaStmt = $db->prepare("UPDATE users SET mfa_secret = ? WHERE id = ?");
        $mfaStmt->execute([$temp_secret, $userId]);
        
        // កត់ត្រាទុកក្នុងសវនកម្ម (Audit Log)
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $logStmt = $db->prepare("INSERT INTO audit_logs (user_id, action, target_user_id, details, ip_address) VALUES (?, 'ENABLE_MFA', ?, 'User activated Google Authenticator MFA', ?)");
        $logStmt->execute([$userId, $userId, $ip]);

        unset($_SESSION['temp_mfa_secret']);
        $user['mfa_secret'] = $temp_secret; // បញ្ចូលទៅក្នុងអថេរបច្ចុប្បន្នដើម្បីបង្ហាញលើ UI
        $feedback = ['status' => 'success', 'message' => 'ការបើកដំណើរការផ្ទៀងផ្ទាត់ពីរជំហាន (MFA) ជោគជ័យ! គណនីរបស់អ្នកត្រូវបានការពារយ៉ាងរឹងមាំបំផុត។'];
    }
}

// បិទដំណើរការ MFA (Disable MFA)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'disable_mfa') {
    $mfaStmt = $db->prepare("UPDATE users SET mfa_secret = NULL WHERE id = ?");
    $mfaStmt->execute([$userId]);

    // កត់ត្រាទុកក្នុងសវនកម្ម (Audit Log)
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $logStmt = $db->prepare("INSERT INTO audit_logs (user_id, action, target_user_id, details, ip_address) VALUES (?, 'DISABLE_MFA', ?, 'User deactivated Google Authenticator MFA', ?)");
    $logStmt->execute([$userId, $userId, $ip]);

    $user['mfa_secret'] = null;
    $feedback = ['status' => 'success', 'message' => 'ការផ្ទៀងផ្ទាត់ពីរជំហាន (MFA) ត្រូវបានបិទដំណើរការវិញ។'];
}
?>
<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ប្រវត្តិរូបផ្ទាល់ខ្លួន - Payment Tracker</title>
    <!-- ភ្ជាប់ឯកសាររចនាប័ទ្ម CSS និង Font ភាសាខ្មែរ -->
    <link rel="stylesheet" href="admin-style.css">
    <link href="https://fonts.googleapis.com/css2?family=Kantumruy+Pro:wght@300;400;600;700&family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="icon" href="icon.png">
</head>
<body>

    <!-- Navigation Bar with Burger Toggle -->
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
                <a href="pdf.php" target="_blank">ទាញយក PDF</a>
                <a href="export-csv.php" target="_blank">នាំចេញ CSV</a>
            <?php endif; ?>
            <a href="#" onclick="logoutUser(); return false;" class="logout-btn">ចាកចេញ</a>
        </div>
    </nav>

    <div class="profile-container">
        
        <!-- បង្ហាញការជូនដំណឹងពីប្រព័ន្ធ (Feedback Messages) -->
        <?php if (!empty($feedback['message'])): ?>
            <div class="feedback-msg <?php echo $feedback['status'] === 'success' ? 'success-alert' : 'error-alert'; ?>" style="margin-bottom: 1.5rem;">
                <?php echo htmlspecialchars($feedback['message']); ?>
            </div>
        <?php endif; ?>

        <div class="profile-grid">
            
            <!-- ផ្ទាំងព័ត៌មានទូទៅនៃគណនី (Account Overview) -->
            <div class="card">
                <div class="card-header">
                    <h3>👤 ប្រវត្តិរូប និងព័ត៌មានគណនី</h3>
                </div>
                <div class="card-body">
                    <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1.5rem;">
                        <span style="font-size: 3.5rem;">👤</span>
                        <div>
                            <h2 style="margin: 0; font-size: 1.4rem; color: #111827;"><?php echo htmlspecialchars($user['username']); ?></h2>
                            <span class="user-role-badge <?php echo $user['role'] === 'super_admin' ? 'super-admin-badge' : ($user['role'] === 'admin' ? 'admin-badge' : 'user-badge'); ?>" style="display: inline-block; margin-top: 0.25rem;">
                                <?php echo strtoupper($user['role']); ?>
                            </span>
                        </div>
                    </div>

                    <table class="table" style="margin-top: 1rem;">
                        <tr>
                            <td style="font-weight: 600; padding: 0.75rem 0;">អត្តសញ្ញាណ (User ID)៖</td>
                            <td style="padding: 0.75rem 0;"><?php echo $user['id']; ?></td>
                        </tr>
                        <tr>
                            <td style="font-weight: 600; padding: 0.75rem 0;">ស្ថានភាពគណនី៖</td>
                            <td style="padding: 0.75rem 0;">
                                <span class="user-role-badge <?php echo $user['status'] === 'active' ? 'super-admin-badge' : 'admin-badge'; ?>">
                                    <?php echo htmlspecialchars($user['status']); ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td style="font-weight: 600; padding: 0.75rem 0;">ថ្ងៃបង្កើតគណនី៖</td>
                            <td style="padding: 0.75rem 0;"><?php echo date('d-M-Y H:i', strtotime($user['created_at'])); ?></td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- ផ្ទាំងការផ្លាស់ប្តូរពាក្យសម្ងាត់ (Password Update Guard) -->
            <div class="card">
                <div class="card-header">
                    <h3>🔐 ផ្លាស់ប្តូរពាក្យសម្ងាត់ (Password Change)</h3>
                </div>
                <div class="card-body">
                    <p class="helper-text" style="margin-bottom: 1rem;">
                        ដើម្បីធានាសុវត្ថិភាព គណនីប្រភេទ Admin ត្រូវប្រើប្រាស់ពាក្យសម្ងាត់យ៉ាងតិច <strong>១២ ខ្ទង់</strong> និងគណនីទូទៅ <strong>៨ ខ្ទង់</strong> ដែលរួមមានអក្សរធំ អក្សរតូច លេខ និងនិមិត្តសញ្ញាពិសេស។
                    </p>

                    <form method="POST" action="profile.php">
                        <input type="hidden" name="action" value="change_password">
                        
                        <div class="form-group">
                            <label for="current_password">ពាក្យសម្ងាត់បច្ចុប្បន្ន</label>
                            <input type="password" name="current_password" id="current_password" class="form-control" placeholder="••••••••" required>
                        </div>

                        <div class="form-group">
                            <label for="new_password">ពាក្យសម្ងាត់ថ្មី</label>
                            <input type="password" name="new_password" id="new_password" class="form-control" placeholder="••••••••" required>
                        </div>

                        <div class="form-group">
                            <label for="confirm_password">បញ្ជាក់ពាក្យសម្ងាត់ថ្មី</label>
                            <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="••••••••" required>
                        </div>

                        <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 0.5rem;">ធ្វើបច្ចុប្បន្នភាពពាក្យសម្ងាត់</button>
                    </form>
                </div>
            </div>

        </div> <!-- បញ្ចប់ Grid ទី ១ -->

        <div class="profile-grid" style="margin-top: 1.5rem;">
            
            <!-- ផ្ទាំងគ្រប់គ្រង Multi-Factor Authentication (MFA Panel) -->
            <div class="card">
                <div class="card-header">
                    <h3>🛡️ ការផ្ទៀងផ្ទាត់ពីរជំហាន (Google Authenticator MFA)</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($user['mfa_secret']) && !isset($_GET['setup_mfa'])): ?>
                        <p class="helper-text" style="margin-bottom: 1.5rem;">
                            ការផ្ទៀងផ្ទាត់ពីរជំហាន (2-Step Verification) ជួយការពារគណនីរបស់អ្នកពីការចូលប្រើប្រាស់ដោយគ្មានការអនុញ្ញាត ទោះបីជាមាននរណាម្នាក់ដឹងពាក្យសម្ងាត់របស់អ្នកក៏ដោយ។
                        </p>
                        <form method="POST" action="profile.php">
                            <input type="hidden" name="action" value="setup_mfa">
                            <button type="submit" class="btn btn-primary" style="width: 100%;">ដំឡើង Google Authenticator</button>
                        </form>

                    <?php elseif (isset($_GET['setup_mfa']) && isset($_SESSION['temp_mfa_secret'])): ?>
                        <!-- បង្ហាញកូដ QR សម្រាប់ផ្ទៀងផ្ទាត់ និងបើកដំណើរការ MFA -->
                        <div class="mfa-qr-box">
                            <p style="font-weight: 600; margin-top: 0; font-size: 0.95rem;">សូមស្កេនកូដ QR នេះជាមួយកម្មវិធី Google Authenticator</p>
                            <?php 
                                $qrUrl = MFAHelper::getQRUrl($user['username'], $_SESSION['temp_mfa_secret'], "KhmerPaymentTracker");
                                echo '<img src="' . htmlspecialchars($qrUrl) . '" alt="MFA QR Code">';
                            ?>
                            <p style="font-family: monospace; font-size: 0.9rem; background: #eaeaea; padding: 0.4rem; border-radius: 4px; display: inline-block; margin: 0.5rem 0;">
                                <?php echo htmlspecialchars($_SESSION['temp_mfa_secret']); ?>
                            </p>
                        </div>

                        <form method="POST" action="profile.php">
                            <input type="hidden" name="action" value="activate_mfa">
                            <div class="form-group">
                                <label for="mfa_code" style="font-weight: 600; text-align: center; display: block;">បញ្ចូលកូដ ៦ ខ្ទង់ពីទូរស័ព្ទដៃរបស់អ្នក</label>
                                <input type="text" name="mfa_code" id="mfa_code" class="form-control" placeholder="000000" maxlength="6" style="text-align: center; font-size: 1.3rem; letter-spacing: 0.2rem; font-weight: bold;" required>
                            </div>
                            <div style="display: flex; gap: 0.5rem;">
                                <a href="profile.php" class="btn btn-secondary" style="flex: 1; text-align: center;">បោះបង់</a>
                                <button type="submit" class="btn btn-primary" style="flex: 1;">ផ្ទៀងផ្ទាត់ និងបើកដំណើរការ</button>
                            </div>
                        </form>

                    <?php else: ?>
                        <!-- គណនីមានបើកដំណើរការ MFA រួចរាល់ -->
                        <div style="text-align: center; padding: 1.5rem 1rem;">
                            <span style="font-size: 3.5rem; display: block; margin-bottom: 0.5rem;">🛡️</span>
                            <h4 style="margin: 0; color: #10b981; font-size: 1.2rem;">គណនីរបស់អ្នកត្រូវបានការពារដោយ MFA រួចជាស្រេច</h4>
                            <p class="helper-text" style="margin-top: 0.5rem; margin-bottom: 1.5rem;">
                                រាល់ការចូលប្រើប្រាស់គណនីនាពេលខាងមុខ នឹងតម្រូវឱ្យវាយបញ្ចូលលេខកូដសន្តិសុខផ្លាស់ប្តូររៀងរាល់ ៣០ វិនាទីពីទូរស័ព្ទដៃរបស់អ្នកជានិច្ច។
                            </p>
                            <form method="POST" action="profile.php" onsubmit="return confirm('តើអ្នកពិតជាចង់បិទដំណើរការ MFA មែនទេ? ការធ្វើបែបនេះអាចប៉ះពាល់ដល់សន្តិសុខគណនីយ៉ាងធ្ងន់ធ្ងរ។');">
                                <input type="hidden" name="action" value="disable_mfa">
                                <button type="submit" class="btn btn-secondary" style="width: 100%; border-color: #ef4444; color: #ef4444;">បិទដំណើរការ MFA ឡើងវិញ</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ផ្ទាំងការណែនាំអំពីការស្តារគណនីបន្ទាន់ (Emergency Recovery Guide) -->
            <div class="card">
                <div class="card-header">
                    <h3>🚨 ការស្តារគណនី និងវិធានការបន្ទាន់ (Account Recovery)</h3>
                </div>
                <div class="card-body">
                    <p class="helper-text" style="margin-bottom: 1rem;">
                        ស្របតាមឧត្តមានុវត្តន៍របស់ <strong>Google Workspace Help</strong> ក្នុងករណីដែលលោកអ្នកបាត់បង់ទូរស័ព្ទដៃ ឬមិនអាចទទួលលេខកូដ MFA បាន៖
                    </p>
                    
                    <ul style="padding-left: 1.25rem; font-size: 0.85rem; color: #4b5563; line-height: 1.5rem;">
                        <li style="margin-bottom: 0.5rem;">
                            <strong>រក្សាទុកកូដបម្រុងទុក (Backup Codes)</strong>៖ បង្កើត និងបោះពុម្ពលេខកូដបម្រុងទុកជាមុន ដាក់ក្នុងកន្លែងមានសុវត្ថិភាពបំផុត។
                        </li>
                        <li style="margin-bottom: 0.5rem;">
                            <strong>ចុះឈ្មោះសោសន្តិសុខរូបវន្តបម្រុង (Enroll Spare Security Keys)</strong>៖ ដំឡើងសោសន្តិសុខរូបវន្តលើសពីមួយ ដើម្បីអាចចូលប្រើប្រាស់បានជានិច្ច។
                        </li>
                        <li style="margin-bottom: 0.5rem;">
                            <strong>បញ្ចូលព័ត៌មានទំនាក់ទំនងស្តារគណនី</strong>៖ បន្ថែមលេខទូរស័ព្ទ ឬអ៊ីមែលបន្ទាប់បន្សំ ដើម្បីងាយស្រួល Reset ពាក្យសម្ងាត់។
                        </li>
                        <li style="margin-bottom: 0.5rem;">
                            <strong>ទំនាក់ទំនង Super Admin ម្នាក់ទៀត</strong>៖ ប្រសិនបើលោកអ្នកជា Admin ហើយបានបាត់បង់ MFA លោកអ្នកត្រូវទាក់ទង Super Admin ម្នាក់ទៀតឱ្យជួយកែសម្រួលស្ថានភាព ឬ Reset MFA ពីប្រព័ន្ធ Dashboard។
                        </li>
                    </ul>

                    <div style="background: #eff6ff; border: 1px solid #bfdbfe; padding: 0.85rem; border-radius: 6px; margin-top: 1rem; font-size: 0.8rem; color: #1e40af; line-height: 1.3rem;">
                        ℹ️ <strong>ចំណាំសម្រាប់ Super Admin៖</strong> ប្រព័ន្ធត្រូវបានបិទដំណើរការស្តារគណនីដោយខ្លួនឯងជាលំនាំដើម (Self-recovery OFF) ដើម្បីការពារការលួចគណនី។ រាល់ការបាត់បង់ ត្រូវតែឆ្លងកាត់ការផ្ទៀងផ្ទាត់យ៉ាងម៉ត់ចត់។
                    </div>
                </div>
            </div>

        </div> <!-- បញ្ចប់ Grid ទី ២ -->

    </div>

    <script src="admin-integration.js"></script>
    <script>
        // Burger menu toggle logic
        document.addEventListener('DOMContentLoaded', () => {
            const toggleBtn = document.getElementById('navbar-toggle-btn');
            const menu = document.getElementById('navbar-menu');
            if (toggleBtn && menu) {
                toggleBtn.addEventListener('click', () => {
                    toggleBtn.classList.toggle('active');
                    menu.classList.toggle('active');
                });
            }
        });
    </script>
</body>
</html>
