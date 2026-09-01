<?php
/**
 * config.php - Database Configuration & Security Hardening Settings
 * Part of the Khmer Payment Tracker and Financial Management System
 * 
 * This file centralizes database credentials and security settings, isolating
 * them from the core API logic (api-v2.php).
 */

// --- ១. ការកំណត់ព័ត៌មានសម្ងាត់ Database (Database Credentials) ---
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'payment_tracker');

// គោលការណ៍សិទ្ធិអប្បបរមា (Least Privilege)៖ ក្នុងសង្វាក់ផលិតកម្ម (Production) 
// ត្រូវជៀសវាងការប្រើប្រាស់គណនី 'root'។ គួរបង្កើតគណនីដែលមានសិទ្ធិត្រឹមកម្រិតចាំបាច់។
// define('DB_USER', 'payment_admin'); 
// define('DB_PASS', 'KhmerSecurePass2026!');
define('DB_USER', 'root'); 
define('DB_PASS', ''); // ទុកជាប្រអប់ទទេគ្មានលេខកូដ


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
        // ការកំណត់ charset=utf8mb4 គឺចាំបាច់បំផុតដើម្បីឱ្យប្រព័ន្ធគាំទ្រអក្សរខ្មែរ និងសញ្ញាប្រាក់រៀល (៛) ឥតខ្ចោះ [56]
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
        // ការការពារការលេចធ្លាយព័ត៌មាន (Information Disclosure Prevention) [28, 30]
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