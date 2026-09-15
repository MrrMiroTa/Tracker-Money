<?php
/**
 * config.php & config-wasmer.php - Ultra-Resilient Database Configuration for Wasmer Edge
 * Part of the Khmer Payment Tracker and Financial Management System
 */

// 1. Fetch MySQL Database Credentials from Environment Variables or Defaults
define('DB_HOST', getenv('WASMER_MYSQL_HOST') ?: (getenv('DB_HOST') ?: 'YOUR_WASMER_DB_HOST'));
define('DB_PORT', getenv('WASMER_MYSQL_PORT') ?: (getenv('DB_PORT') ?: '3306'));
define('DB_NAME', getenv('WASMER_MYSQL_NAME') ?: (getenv('DB_NAME') ?: 'YOUR_WASMER_DB_NAME'));
define('DB_USER', getenv('WASMER_MYSQL_USER') ?: (getenv('DB_USER') ?: 'YOUR_WASMER_DB_USER'));
define('DB_PASS', getenv('WASMER_MYSQL_PASSWORD') ?: (getenv('DB_PASS') ?: 'YOUR_WASMER_DB_PASSWORD'));

// 2. Simulation Mode Flag
if (!defined('SYS_SIMULATION_MODE')) {
    define('SYS_SIMULATION_MODE', false);
}

define('SECURE_SESSION_COOKIES', true);

/**
 * Universal Database Connection Engine with Automatic Fallback Handling
 */
function getSecureDBConnection() {
    // If Simulation Mode is explicitly enabled
    if (defined('SYS_SIMULATION_MODE') && SYS_SIMULATION_MODE === true) {
        return null;
    }

    // If environment variables are unconfigured or using defaults
    if (DB_HOST === 'YOUR_WASMER_DB_HOST' || empty(DB_HOST)) {
        return null; // Safely trigger simulated mode in API without throwing 500 error
    }

    try {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_TIMEOUT            => 3,
        ];
        return new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        error_log("MySQL Connection Failed: " . $e->getMessage() . " - Switching to Simulated Mode");
        // Return null so api-v2.php can handle requests in simulated mode seamlessly!
        return null;
    }
}

function getDBConnection() {
    return getSecureDBConnection();
}
?>
