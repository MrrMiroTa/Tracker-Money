<?php
/**
 * export-csv.php - Secure CSV Export Service with Scoped Access & Audit Trail
 * Part of the Khmer Payment Tracker and Financial Management System
 * 
 * This file allows users and administrators to export transaction records to a CSV file.
 * It strictly adheres to the Principle of Least Privilege and records actions in audit logs.
 */

// ១. ចាប់ផ្តើម Session និងរួមបញ្ចូលការកំណត់ប្រព័ន្ធ (Session & Central Config)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';

// ២. ផ្ទៀងផ្ទាត់ការចូលប្រើប្រាស់ (Authentication Guard)
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "សូមចូលប្រើប្រាស់ប្រព័ន្ធជាមុនសិន។ (Unauthorized)"]);
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];
$username = $_SESSION['username'] ?? 'Unknown';

// ភ្ជាប់ទៅកាន់ Database ដោយសុវត្ថិភាព
$db = getSecureDBConnection();

// ៣. បែងចែកសិទ្ធិទាញយកទិន្នន័យ (Scoped Access Logic)
// Admin & Super Admin អាចទាញយកបានទាំងអស់ | User ធម្មតាអាចទាញយកបានតែទិន្នន័យផ្ទាល់ខ្លួនប៉ុណ្ណោះ (PoLP)
$queryStr = "SELECT t.date, t.description, t.type, t.currency, t.amount, u.username as creator_name 
             FROM transactions t 
             LEFT JOIN users u ON t.user_id = u.id
             WHERE t.is_deleted = 0";
$params = [];

if ($user_role !== 'super_admin' && $user_role !== 'admin') {
    $queryStr .= " AND t.user_id = :user_id";
    $params[':user_id'] = $user_id;
}

$filter_date = isset($_GET['date']) ? trim($_GET['date']) : '';
if (!empty($filter_date)) {
    $queryStr .= " AND DATE(t.date) = :filter_date";
    $params[':filter_date'] = $filter_date;
}

$queryStr .= " ORDER BY t.date DESC";

try {
    $stmt = $db->prepare($queryStr);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Database error in export-csv.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "មានបញ្ហាបច្ចេកទេសក្នុងការទាញយកទិន្នន័យ។"]);
    exit;
}

// ៤. កត់ត្រាសកម្មភាពសន្តិសុខក្នុង Audit Logs (Security Audit Trail)
try {
    $logStmt = $db->prepare("
        INSERT INTO audit_logs (user_id, action, details, ip_address) 
        VALUES (:user_id, 'EXPORT_CSV_REPORT', :details, :ip_address)
    ");
    $details = "Exported financial transactions to CSV. Scope: " . ($user_role === 'super_admin' || $user_role === 'admin' ? "ALL_RECORDS" : "OWN_RECORDS_ONLY");
    if (!empty($filter_date)) {
        $details .= " | Filtered Date: " . $filter_date;
    }
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    
    $logStmt->execute([
        ':user_id' => $user_id,
        ':details' => $details,
        ':ip_address' => $ip_address
    ]);
} catch (PDOException $e) {
    error_log("Failed to write audit log in export-csv.php: " . $e->getMessage());
}

// ៥. កំណត់ Headers សម្រាប់ទាញយកឯកសារ CSV (CSV Force Download Headers)
header('Content-Type: text/csv; charset=utf-8');
$filename_suffix = date('Y-m-d_H-i-s');
if (!empty($filter_date)) {
    $filename_suffix = 'filtered_' . $filter_date . '_' . date('H-i-s');
}
header('Content-Disposition: attachment; filename="payment_report_' . $filename_suffix . '.csv"');

// បញ្ចូល UTF-8 BOM ដើម្បីឱ្យ Microsoft Excel បង្ហាញអក្សរខ្មែរ និងនិមិត្តសញ្ញាប្រាក់រៀល (៛) បានត្រឹមត្រូវ
echo "\xEF\xBB\xBF";

// បើកស្ទ្រីមបញ្ចេញទិន្នន័យ (Output Stream)
$output = fopen('php://output', 'w');

// សរសេរក្បាលតារាង (Header Row)
fputcsv($output, [
    'កាលបរិច្ឆេទ (Date)', 
    'បរិយាយ (Description)', 
    'ប្រភេទ (Type)', 
    'រូបិយប័ណ្ណ (Currency)', 
    'ចំនួនទឹកប្រាក់ (Amount)', 
    'អ្នកបញ្ចូល (Created By)'
]);

// ៦. បំពេញទិន្នន័យប្រតិបត្តិការទៅក្នុងឯកសារ CSV (Populate CSV Rows)
foreach ($transactions as $row) {
    // កែសម្រួលការបង្ហាញប្រភេទឱ្យងាយស្រួលយល់ជាភាសាខ្មែរ
    $type_kh = $row['type'] === 'income' ? 'ចំណូល (Income)' : 'ចំណាយ (Expense)';
    $amount_formatted = $row['currency'] === 'KHR' 
        ? number_format($row['amount']) . ' ៛' 
        : '$' . number_format($row['amount'], 2);

    fputcsv($output, [
        $row['date'],
        $row['description'],
        $type_kh,
        $row['currency'],
        $amount_formatted,
        $row['creator_name'] ?? ('User ID: ' . $user_id)
    ]);
}

// បិទស្ទ្រីមទិន្នន័យ
fclose($output);
exit;
?>