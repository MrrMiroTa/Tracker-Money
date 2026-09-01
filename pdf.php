<?php
// pdf.php
// Upgraded PDF Export Service with RBAC, Dual-Currency Support, and Audit Logging
// Designed for Khmer Payment Tracker and Financial Management System

// 1. Initialize Session and Central Configurations
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';

// 2. Strict Security Guards (RBAC & Principle of Least Privilege)
// Ensure the user is authenticated
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    // If it's an API/ajax request, return JSON; otherwise redirect to login
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

// 3. Define Scoped Access (Least Privilege)
// Admins & Super Admins can export all records. Normal users can ONLY export their own records.
$queryStr = "SELECT t.*, u.username as creator_name 
             FROM transactions t 
             LEFT JOIN users u ON t.user_id = u.id";
$params = [];

if ($user_role !== 'super_admin' && $user_role !== 'admin') {
    // Restrict standard users to their own data (Scoped Access)
    $queryStr .= " WHERE t.user_id = :user_id";
    $params[':user_id'] = $user_id;
}

$queryStr .= " ORDER BY t.date DESC";

try {
    $stmt = $db->prepare($queryStr);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Database error in pdf.php: " . $e->getMessage());
    die("មានបញ្ហាបច្ចេកទេសក្នុងការទាញយកទិន្នន័យ។");
}

// 4. Record Action in Security Audit Logs (Audit Trail Best Practice)
try {
    $logStmt = $db->prepare("
        INSERT INTO audit_logs (user_id, action, details, ip_address) 
        VALUES (:user_id, 'EXPORT_PDF_REPORT', :details, :ip_address)
    ");
    $details = "Exported financial transaction PDF report. Scope: " . ($user_role === 'super_admin' || $user_role === 'admin' ? "ALL_RECORDS" : "OWN_RECORDS_ONLY");
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    
    $logStmt->execute([
        ':user_id' => $user_id,
        ':details' => $details,
        ':ip_address' => $ip_address
    ]);
} catch (PDOException $e) {
    // Log privately but don't halt report generation
    error_log("Failed to write audit log in pdf.php: " . $e->getMessage());
}

// 5. PDF Generation Engine
// We use tFPDF / FPDF for PDF generation. We include a standard fallback to ensure usability.
if (!class_exists('FPDF') && file_exists('tfpdf/tfpdf.php')) {
    require_once 'tfpdf/tfpdf.php';
    class PDF_Engine extends tFPDF {}
} elseif (class_exists('FPDF')) {
    class PDF_Engine extends FPDF {}
} else {
    // Fallback Mock class if FPDF is not yet configured on the local server
    class PDF_Engine {
        public function __construct() {
            // If FPDF library is missing on local server, provide a highly formatted CSV download option
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=payment_report_' . date('Y-m-d') . '.csv');
            echo "\xEF\xBB\xBF"; // UTF-8 BOM for Excel Khmer text rendering
            $output = fopen('php://output', 'w');
            fputcsv($output, ['កាលបរិច្ឆេទ', 'បរិយាយ', 'អ្នកបន្ថែម', 'ប្រភេទ', 'ចំនួនទឹកប្រាក់ (រៀល)', 'ចំនួនទឹកប្រាក់ (ដុល្លារ)']);
            global $transactions;
            foreach ($transactions as $row) {
                fputcsv($output, [
                    $row['date'],
                    $row['description'],
                    $row['creator_name'] ?? $row['user_id'],
                    $row['type'] === 'income' ? 'ចំណូល' : 'ចំណាយ',
                    $row['currency'] === 'KHR' ? number_format($row['amount']) . ' ៛' : '0 ៛',
                    $row['currency'] === 'USD' ? '$' . number_format($row['amount'], 2) : '$0.00'
                ]);
            }
            fclose($output);
            exit;
        }
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
    $pdf->Cell(0, 5, "Date: " . date('Y-m-d H:i:s') . " | Exported by: " . $username . " (" . strtoupper($user_role) . ")", 0, 1, 'C');
    $pdf->Ln(10);
    
    // Header Table Columns
    $pdf->Cell(30, 8, 'Date', 1);
    $pdf->Cell(60, 8, 'Description', 1);
    $pdf->Cell(30, 8, 'Created By', 1);
    $pdf->Cell(20, 8, 'Type', 1);
    $pdf->Cell(25, 8, 'Amount (KHR)', 1);
    $pdf->Cell(25, 8, 'Amount (USD)', 1);
    $pdf->Ln();
    
    // Rows
    foreach ($transactions as $row) {
        $pdf->Cell(30, 6, $row['date'], 1);
        $pdf->Cell(60, 6, substr($row['description'], 0, 30), 1);
        $pdf->Cell(30, 6, $row['creator_name'] ?? ('User ID: ' . $row['user_id']), 1);
        $pdf->Cell(20, 6, ucfirst($row['type']), 1);
        $pdf->Cell(25, 6, ($row['currency'] === 'KHR' ? number_format($row['amount']) . ' KHR' : '-'), 1);
        $pdf->Cell(25, 6, ($row['currency'] === 'USD' ? '$' . number_format($row['amount'], 2) : '-'), 1);
        $pdf->Ln();
    }
    
    // Output PDF File
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="payment_report_' . date('Ymd') . '.pdf"');
    $pdf->Output('I');
}
?>