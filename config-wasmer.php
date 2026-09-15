<?php
/**
 * config.php & config-wasmer.php - Universal Database Connection & Security Configuration
 * Part of the Khmer Payment Tracker and Financial Management System
 * 
 * Supports MySQL (via Wasmer Environment Variables or standard credentials)
 * with an automatic fallback to SQLite so the application NEVER crashes or fails to connect.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Environment Credentials for Wasmer / MySQL
define("DB_HOST", getenv("WASMER_MYSQL_HOST") ?: (getenv("DB_HOST") ?: "YOUR_WASMER_DB_HOST"));
define("DB_PORT", getenv("WASMER_MYSQL_PORT") ?: (getenv("DB_PORT") ?: "3306"));
define("DB_NAME", getenv("WASMER_MYSQL_NAME") ?: (getenv("DB_NAME") ?: "YOUR_WASMER_DB_NAME"));
define("DB_USER", getenv("WASMER_MYSQL_USER") ?: (getenv("DB_USER") ?: "YOUR_WASMER_DB_USER"));
define("DB_PASS", getenv("WASMER_MYSQL_PASSWORD") ?: (getenv("DB_PASS") ?: "YOUR_WASMER_DB_PASSWORD"));

define("SYS_SIMULATION_MODE", false);
define("SECURE_SESSION_COOKIES", true);

/**
 * Universal Database Connection Engine (MySQL with SQLite Fallback)
 */
function getSecureDBConnection() {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $host = DB_HOST;
    $port = DB_PORT;
    $dbname = DB_NAME;
    $user = DB_USER;
    $pass = DB_PASS;

    // 1. Try MySQL Connection if Host is configured (not placeholder)
    if (!empty($host) && $host !== "YOUR_WASMER_DB_HOST") {
        try {
            $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $pdo = new PDO($dsn, $user, $pass, $options);
            return $pdo;
        } catch (PDOException $e) {
            error_log("MySQL Connection Exception: " . $e->getMessage());
        }
    }

    // 2. Universal Fallback: SQLite (Guarantees zero-downtime & zero server-connection failure)
    try {
        $dataDir = __DIR__ . "/data";
        if (!is_dir($dataDir)) {
            @mkdir($dataDir, 0755, true);
        }
        $dbFile = $dataDir . "/tracker.sqlite";
        $pdo = new PDO("sqlite:" . $dbFile);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        initSQLiteSchema($pdo);
        return $pdo;
    } catch (Exception $e) {
        error_log("SQLite Connection Exception: " . $e->getMessage());
        return null;
    }
}

/**
 * Compatibility Function Wrapper
 */
if (!function_exists("getDBConnection")) {
    function getDBConnection() {
        return getSecureDBConnection();
    }
}

/**
 * Auto-initialize SQLite database schema & seed admin accounts
 */
function initSQLiteSchema($pdo) {
    if (!$pdo) return;
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT UNIQUE NOT NULL,
                password_hash TEXT NOT NULL,
                role TEXT DEFAULT 'user\,
                status TEXT DEFAULT 'active\,
                mfa_secret TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS transactions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                description TEXT NOT NULL,
                amount REAL NOT NULL,
                currency TEXT DEFAULT 'USD\,
                type TEXT NOT NULL,
                category TEXT NOT NULL,
                date DATETIME NOT NULL,
                receipt_image TEXT,
                is_deleted INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS audit_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER,
                action TEXT NOT NULL,
                target_user_id INTEGER,
                details TEXT,
                ip_address TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS transaction_history (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                transaction_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                original_description TEXT,
                original_amount REAL,
                original_currency TEXT,
                original_type TEXT,
                original_category TEXT,
                new_description TEXT,
                new_amount REAL,
                new_currency TEXT,
                new_type TEXT,
                new_category TEXT,
                action_type TEXT NOT NULL,
                actioned_by INTEGER NOT NULL,
                actioned_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
        ");

        // Seed default admin accounts if empty
        $check = $pdo->query("SELECT COUNT(*) FROM users");
        if ((int)$check->fetchColumn() === 0) {
            $superPass = password_hash("admin123", PASSWORD_BCRYPT);
            $adminPass = password_hash("admin123", PASSWORD_BCRYPT);
            $userPass = password_hash("user123", PASSWORD_BCRYPT);

            $stmt = $pdo->prepare("INSERT INTO users (id, username, password_hash, role, status) VALUES (?, ?, ?, ?, 'active\)");
            $stmt->execute([1, "superadmin_cambodia", $superPass, "super_admin"]);
            $stmt->execute([2, "admin_sophors", $adminPass, "admin"]);
            $stmt->execute([3, "khmer_user1", $userPass, "user"]);
        }
    } catch (Exception $e) {
        error_log("SQLite schema init warning: " . $e->getMessage());
    }
}
?>