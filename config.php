<?php
/**
 * config.php - Database connection & security configuration
 * Part of the Khmer Payment Tracker and Financial Management System
 *
 * SECURITY CHANGES vs. the old version
 *  - NO credentials in the source code. They come from environment variables
 *    (Wasmer: WASMER_MYSQL_*) or from a git-ignored file `config.local.php`.
 *  - NO silent SQLite fallback and NO seeded default accounts (the old fallback
 *    created superadmin_cambodia / admin123 whenever MySQL was unreachable).
 *    If the database is down the app now fails CLOSED with a 503.
 *  - Sessions / headers are handled by security-bootstrap.php.
 */

require_once __DIR__ . '/security-bootstrap.php';

// Optional local override (shared hosting without env vars). Must be git-ignored.
$__local = is_file(__DIR__ . '/config.local.php') ? (array)require __DIR__ . '/config.local.php' : [];

$__pick = function (string $key, string $wasmerEnv) use ($__local): string {
    foreach ([$wasmerEnv, $key] as $env) {
        $v = getenv($env);
        if ($v !== false && $v !== '') {
            return $v;
        }
    }
    return (string)($__local[$key] ?? '');
};

define('DB_HOST', $__pick('DB_HOST', 'WASMER_MYSQL_HOST'));
define('DB_PORT', $__pick('DB_PORT', 'WASMER_MYSQL_PORT') ?: '3306');
define('DB_NAME', $__pick('DB_NAME', 'WASMER_MYSQL_NAME'));
define('DB_USER', $__pick('DB_USER', 'WASMER_MYSQL_USER'));
define('DB_PASS', $__pick('DB_PASS', 'WASMER_MYSQL_PASSWORD'));
unset($__local, $__pick);

function configFailClosed(string $logMessage): void
{
    error_log($logMessage);
    if (!headers_sent()) {
        http_response_code(503);
        header('Content-Type: application/json; charset=UTF-8');
        header('Retry-After: 60');
    }
    echo json_encode(['status' => 'error', 'message' => 'ប្រព័ន្ធមិនអាចប្រើបានបណ្តោះអាសន្ន។ សូមសាកល្បងម្តងទៀតនៅពេលក្រោយ។']);
    exit;
}

/** PDO connection (MySQL). Never returns null: on failure the request ends with 503. */
function getSecureDBConnection(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    if (DB_HOST === '' || DB_NAME === '' || DB_USER === '') {
        configFailClosed('Database is not configured: set WASMER_MYSQL_* / DB_* env vars or config.local.php');
    }

    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
    } catch (PDOException $e) {
        configFailClosed('MySQL connection failed: ' . $e->getMessage());
    }
    return $pdo;
}

/** Compatibility wrapper used by older files. */
if (!function_exists('getDBConnection')) {
    function getDBConnection(): PDO
    {
        return getSecureDBConnection();
    }
}
