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

define('DB_HOST', getenv('WASMER_MYSQL_HOST') ?: (getenv('DB_HOST') ?: 'YOUR_WASMER_DB_HOST'));
define('DB_PORT', getenv('WASMER_MYSQL_PORT') ?: (getenv('DB_PORT') ?: '3306'));
define('DB_NAME', getenv('WASMER_MYSQL_NAME') ?: (getenv('DB_NAME') ?: 'YOUR_WASMER_DB_NAME'));
define('DB_USER', getenv('WASMER_MYSQL_USER') ?: (getenv('DB_USER') ?: 'YOUR_WASMER_DB_USER'));
define('DB_PASS', getenv('WASMER_MYSQL_PASSWORD') ?: (getenv('DB_PASS') ?: 'YOUR_WASMER_DB_PASSWORD'));

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