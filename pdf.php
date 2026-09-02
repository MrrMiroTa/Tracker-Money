<?php
// pdf-v2.php
// Upgraded PDF Export Service with RBAC, Soft-Delete Filtering, Date Filters, Dual-Currency Support, and Audit Logging
// Designed for Khmer Payment Tracker and Financial Management System

// 1. Initialize Session and Central Configurations
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';

// 2. Strict Security Guards (RBAC & Principle of Least Privilege)
// Ensure the user is authenticated
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    if (isset($_GET['format']) && $_GET['format'] === 'json') {
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "សូមចូលប្រើប្រាស់ប្រព័ន្ធជាមុនសិន។ (Unauthorized)"]);
        exit;
    }
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];
$username = $_SESSION['username'] ?? 'Unknown';

// Establish Secure Database Connection
$db = getSecureDBConnection();

// 3. Define Scoped Access & Filters (Least Privilege)
// Filter out soft-deleted transactions (is_deleted = 0) and filter by date if provided
$queryStr = "SELECT t.*, u.username as creator_name 
             FROM transactions t 
             LEFT JOIN users u ON t.user_id = u.id
             WHERE t.is_deleted = 0";
$params = [];

// Scoped Access: Admins & Super Admins see all active records; regular users see only their own
if ($user_role !== 'super_admin' && $user_role !== 'admin') {
    $queryStr .= " AND t.user_id = :user_id";
    $params[':user_id'] = $user_id;
}

// Support Date Filter (e.g., ?date=2026-08-20) to match the filtered frontend view
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
    error_log("Database error in pdf-v2.php: " . $e->getMessage());
    die("មានបញ្ហាបច្ចេកទេសក្នុងការទាញយកទិន្នន័យ។");
}

// 4. Record Action in Security Audit Logs (Audit Trail Best Practice)
try {
    $logStmt = $db->prepare("
        INSERT INTO audit_logs (user_id, action, details, ip_address) 
        VALUES (:user_id, 'EXPORT_PDF_REPORT', :details, :ip_address)
    ");
    $details = "Exported financial transaction PDF report. Scope: " . ($user_role === 'super_admin' || $user_role === 'admin' ? "ALL_RECORDS" : "OWN_RECORDS_ONLY");
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
    error_log("Failed to write audit log in pdf-v2.php: " . $e->getMessage());
}

// 5. PDF Generation Engine
// Use tFPDF / FPDF for PDF generation with a robust CSV fallback
if (!class_exists('FPDF') && file_exists('tfpdf/tfpdf.php')) {
    require_once 'tfpdf/tfpdf.php';
    class PDF_Engine extends tFPDF {}
} elseif (class_exists('FPDF')) {
    class PDF_Engine extends FPDF {}
} else {
    // Fallback Mock class if FPDF library is not yet configured on the local server
    class PDF_Engine {
        public function __construct() {
            header('Content-Type: text/csv; charset=utf-8');
            $filename = 'payment_report_' . date('Y-m-d');
            global $filter_date;
            if (!empty($filter_date)) {
                $filename .= '_filtered_' . $filter_date;
            }
            header('Content-Disposition: attachment; filename=' . $filename . '.csv');
            echo "\xEF\xBB\xBF"; // UTF-8 BOM for Excel Khmer text rendering
            $output = fopen('php://output', 'w');
            fputcsv($output, ['កាលបរិច្ឆេទ', 'បរិយាយ', 'អ្នកបន្ថែម', 'ប្រភេទ', 'จำนวนទឹកប្រាក់ (រៀល)', 'จำนวนទឹកប្រាក់ (ដុល្លារ)']);
            global $transactions;
            foreach ($transactions as $row) {
                fputcsv($output, [\n                    $row['date'],\n                    $row['description'],\n                    $row['creator_name'] ?? $row['user_id'],\n                    $row['type'] === 'income' ? 'ចំណូល' : 'ចំណាយ',\n                    $row['currency'] === 'KHR' ? number_format($row['amount']) . ' ៛' : '0 ៛',\n                    $row['currency'] === 'USD' ? '$' . number_format($row['amount'], 2) : '$0.00'\n                ]);\n            }\n            fclose($output);\n            exit;\n        }
    }
}

// Create Instance of PDF engine (If class is available)
if (class_exists('PDF_Engine') && method_exists('PDF_Engine', 'AddPage')) {
    $pdf = new PDF_Engine();
    $pdf->AliasNbPages();
    $pdf->AddPage();
    
    // Add Unicode Khmer Font support if using tFPDF
    if (method_exists($pdf, 'AddFont') && file_exists('tfpdf/font/unifont/KantumruyPro-Regular.ttf')) {
        $pdf->AddFont('Kantumruy', '', 'KantumruyPro-Regular.ttf', true);
        $pdf->SetFont('Kantumruy', '', 12);
    } else {
        $pdf->SetFont('Arial', '', 10);
    }
    
    // Title Banner
    $pdf->Cell(0, 10, "Payment Tracker Financial Report", 0, 1, 'C');
    $subtitle = "Date: " . date('Y-m-d H:i:s') . " | Exported by: " . $username . " (" . strtoupper($user_role) . ")";
    if (!empty($filter_date)) {
        $subtitle .= " | Filtered Date: " . $filter_date;
    }
    $pdf->Cell(0, 5, $subtitle, 0, 1, 'C');
    $pdf->Ln(10);
    
    // Header Table Columns
    $pdf->Cell(30, 8, 'Date', 1);\n    $pdf->Cell(60, 8, 'Description', 1);\n    $pdf->Cell(30, 8, 'Created By', 1);\n    $pdf->Cell(20, 8, 'Type', 1);\n    $pdf->Cell(25, 8, 'Amount (KHR)', 1);\n    $pdf->Cell(25, 8, 'Amount (USD)', 1);\n    $pdf->Ln();
    
    // Rows
    foreach ($transactions as $row) {
        $pdf->Cell(30, 6, $row['date'], 1);\n        $pdf->Cell(60, 6, substr($row['description'], 0, 30), 1);\n        $pdf->Cell(30, 6, $row['creator_name'] ?? ('User ID: ' . $row['user_id']), 1);\n        $pdf->Cell(20, 6, ucfirst($row['type']), 1);\n        $pdf->Cell(25, 6, ($row['currency'] === 'KHR' ? number_format($row['amount']) . ' KHR' : '-'), 1);\n        $pdf->Cell(25, 6, ($row['currency'] === 'USD' ? '$' . number_format($row['amount'], 2) : '-'), 1);\n        $pdf->Ln();
    }
    
    // Output PDF File
    header('Content-Type: application/pdf');
    $filename = 'payment_report_' . date('Ymd');
    if (!empty($filter_date)) {
        $filename .= '_' . str_replace('-', '', $filter_date);
    }
    header('Content-Disposition: attachment; filename="' . $filename . '.pdf"');
    $pdf->Output('I');
}
?>