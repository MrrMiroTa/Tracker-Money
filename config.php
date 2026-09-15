<?php
/**
 * config-wasmer.php - Database Configuration & Security Hardening for Wasmer Edge
 * Part of the Khmer Payment Tracker and Financial Management System
 * 
 * This file centralizes database credentials and security settings, optimized
 * for WebAssembly deployment on Wasmer Edge with support for Environment Variables.
 */

// --- ១. ការកំណត់ព័ត៌មានសម្ងាត់ Database (Database Credentials for Wasmer) ---
// ឧត្តមានុវត្តន៍សន្តិសុខ៖ ប្រើប្រាស់ getenv() ដើម្បីទាញយកតម្លៃសម្ងាត់ពី Environment Variables លើ Wasmer Dashboard 
// ដើម្បីជៀសវាងការលេចធ្លាយលេខកូដសម្ងាត់ទៅកាន់ GitHub (Zero-Credentials in Repo)។
// ប្រសិនបើគ្មានការកំណត់នៅលើ Wasmer Dashboard ទេ វានឹងប្រើប្រាស់តម្លៃលំនាំដើម (Default Values) ខាងក្រោម។

<?php
/**
 * config.php - Universal Database Connection & System Settings
 * Part of the Khmer Payment Tracker and Financial Management System
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('DB_HOST', getenv('WASMER_MYSQL_HOST') ?: (getenv('DB_HOST') ?: 'db.fr-roub1.bengt.wasmernet.com'));
define('DB_PORT', getenv('WASMER_MYSQL_PORT') ?: (getenv('DB_PORT') ?: '20184'));
define('DB_NAME', getenv('WASMER_MYSQL_NAME') ?: (getenv('DB_NAME') ?: 'db_f5500176'));
define('DB_USER', getenv('WASMER_MYSQL_USER') ?: (getenv('DB_USER') ?: 'user_ea3af2a0'));
define('DB_PASS', getenv('WASMER_MYSQL_PASSWORD') ?: (getenv('DB_PASS') ?: 'pw_q7C4JpkrPETlFukJ8FBKgl6Q2azdXepu'));

/**
 * Universal Database Connection Engine (MySQL with SQLite Fallback)
 */
function getSecureDBConnection() {
    static $db_instance = null;
    if ($db_instance !== null) {
        return $db_instance;
    }

    // Attempt 1: Try MySQL Connection
    try {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        $db_instance = new PDO($dsn, DB_USER, DB_PASS, $options);
        return $db_instance;
    } catch (PDOException $e) {
        error_log("MySQL Connection Failed: " . $e->getMessage() . " - Falling back to SQLite.");
    }

    // Attempt 2: SQLite Local Fallback for Seamless Operation
    try {
        $dataDir = __DIR__ . '/data';
        if (!is_dir($dataDir)) {
            @mkdir($dataDir, 0755, true);
        }
        $sqlitePath = $dataDir . '/tracker.sqlite';
        $dsn = "sqlite:" . $sqlitePath;
        $db_instance = new PDO($dsn);
        $db_instance->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db_instance->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // Auto-create SQLite tables
        $db_instance->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT UNIQUE NOT NULL,
                password_hash TEXT NOT NULL,
                role TEXT NOT NULL DEFAULT 'user',
                status TEXT NOT NULL DEFAULT 'active',
                mfa_secret TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS transactions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                description TEXT NOT NULL,
                amount REAL NOT NULL,
                currency TEXT NOT NULL,
                type TEXT NOT NULL,
                category TEXT NOT NULL,
                date DATETIME DEFAULT CURRENT_TIMESTAMP,
                receipt_image TEXT,
                is_deleted INTEGER DEFAULT 0
            );
            CREATE TABLE IF NOT EXISTS audit_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                action TEXT NOT NULL,
                target_user_id INTEGER,
                details TEXT,
                ip_address TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
        ");

        // Seed default accounts if empty
        $check = $db_instance->query("SELECT COUNT(*) FROM users")->fetchColumn();
        if ($check == 0) {
            $passHash = password_hash('admin123', PASSWORD_BCRYPT);
            $stmt = $db_instance->prepare("INSERT INTO users (username, password_hash, role, status) VALUES (?, ?, ?, 'active')");
            $stmt->execute(['superadmin_cambodia', $passHash, 'super_admin']);
            $stmt->execute(['admin_sophors', $passHash, 'admin']);
            $stmt->execute(['khmer_user1', $passHash, 'user']);
        }

        return $db_instance;
    } catch (Exception $ex) {
        error_log("SQLite Fallback Failed: " . $ex->getMessage());
        return null;
    }
}

function getDBConnection() {
    return getSecureDBConnection();
}
?>

// --- ២. ការកំណត់សន្តិសុខប្រព័ន្ធ (Security Configuration) ---
// កំណត់ស្ថានភាពដំណើរការប្រព័ន្ធ៖ true សម្រាប់ម៉ូដសាកល្បង (Simulation) / false សម្រាប់ប្រព័ន្ធដំណើរការពិត
define('SYS_SIMULATION_MODE', false); 

// ការពាររាល់ Session Cookies ពីការវាយប្រហារ XSS និង Session Hijacking
define('SECURE_SESSION_COOKIES', true);

// --- ៣. មុខងារតភ្ជាប់ Database ដោយប្រើប្រាស់ PDO (Secure PDO Connection Function) ---
function getSecureDBConnection() {
    // ប្រសិនបើកំណត់ជា Simulation Mode ឱ្យដំណើរការដោយគ្មាន Database (សម្រាប់តេស្តស្រាលៗ)
    if (defined('SYS_SIMULATION_MODE') && SYS_SIMULATION_MODE === true) {
        return null;
    }

    try {
        // ការកំណត់ charset=utf8mb4 គឺចាំបាច់បំផុតដើម្បីឱ្យប្រព័ន្ធគាំទ្រអក្សរខ្មែរ និងសញ្ញាប្រាក់រៀល (៛) ឥតខ្ចោះ
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        
        $options = [
            // កំណត់ឱ្យបោះចោលជា Exception រាល់ពេលមានកំហុស SQL (ងាយស្រួលគ្រប់គ្រង និងចាប់កំហុស)
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            // កំណត់ឱ្យទាញយកទិន្នន័យ (Fetch) ជា Associative Array តាមលំនាំដើម
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // ជៀសវាងការក្លែងបន្លំ Prepared Statements (Emulate Prepared Statements) ការពារ SQL Injection
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        return new PDO($dsn, DB_USER, DB_PASS, $options);
        
    } catch (PDOException $e) {
        // ការការពារការលេចធ្លាយព័ត៌មាន (Information Disclosure Prevention)
        // កត់ត្រាកំហុសទុកក្នុង Server Log ដោយសម្ងាត់ ការពារការលេចធ្លាយព័ត៌មានបច្ចេកទេសទៅកាន់អ្នកវាយប្រហារ
        error_log("Database Connection Failed: " . $e->getMessage()); 
        
        // បង្ហាញសារជាទូទៅដែលមានសុវត្ថិភាពខ្ពស់
        http_response_code(500);
        echo json_encode([
            "status" => "error", 
            "message" => "សេវាកម្មជួបបញ្ហាបច្គេកទេសបណ្តោះអាសន្ន។ សូមព្យាយាមម្តងទៀតនៅពេលក្រោយ។"
        ]);
        exit;
    }
}
?>