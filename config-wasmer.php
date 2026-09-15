<?php
/**
 * config.php - Universal Database Connection & System Settings
 * Part of the Khmer Payment Tracker and Financial Management System
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('DB_HOST', getenv('WASMER_MYSQL_HOST') ?: (getenv('DB_HOST') ?: '127.0.0.1'));
define('DB_PORT', getenv('WASMER_MYSQL_PORT') ?: (getenv('DB_PORT') ?: '3306'));
define('DB_NAME', getenv('WASMER_MYSQL_NAME') ?: (getenv('DB_NAME') ?: 'payment_tracker'));
define('DB_USER', getenv('WASMER_MYSQL_USER') ?: (getenv('DB_USER') ?: 'root'));
define('DB_PASS', getenv('WASMER_MYSQL_PASSWORD') ?: (getenv('DB_PASS') ?: ''));

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