<?php
// pdf-v6.php
// Upgraded PDF & CSV Export Service with Professional Styling, Scoped RBAC, Zebra Rows, colored headers, and Summary Cards
// Designed for Khmer Payment Tracker and Financial Management System

// 1. Initialize Session and Central Configurations
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';

// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 2. Strict Security Guards (RBAC & Principle of Least Privilege)
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

// 3. Define Scoped Access & Filters
$queryStr = "SELECT t.*, u.username as creator_name 
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
    error_log("Database error in pdf.php: " . $e->getMessage());
    die("មានបញ្ហាបច្ចេកទេសក្នុងការទាញយកទិន្នន័យ៖ " . $e->getMessage());
}

// 4. Calculate Financial Totals for Summary Block
$total_income_khr = 0;
$total_expense_khr = 0;
$total_income_usd = 0;
$total_expense_usd = 0;

foreach ($transactions as $row) {
    $amt = floatval($row['amount']);
    if ($row['type'] === 'income') {
        if ($row['currency'] === 'KHR') {
            $total_income_khr += $amt;
        } else {
            $total_income_usd += $amt;
        }
    } else {
        if ($row['currency'] === 'KHR') {
            $total_expense_khr += $amt;
        } else {
            $total_expense_usd += $amt;
        }
    }
}
$bal_khr = $total_income_khr - $total_expense_khr;
$bal_usd = $total_income_usd - $total_expense_usd;

// 5. Record Action in Security Audit Logs
try {
    $logStmt = $db->prepare("
        INSERT INTO audit_logs (user_id, action, details, ip_address) 
        VALUES (:user_id, 'EXPORT_PDF_REPORT', :details, :ip_address)
    ");
    $details = "Exported financial transaction report. Scope: " . ($user_role === 'super_admin' || $user_role === 'admin' ? "ALL_RECORDS" : "OWN_RECORDS_ONLY");
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
    error_log("Failed to write audit log in pdf.php: " . $e->getMessage());
}

// 6. PDF Generation Engine with Fallbacks
$tfpdf_loaded = false;
if (file_exists('tfpdf/tfpdf.php')) {
    require_once 'tfpdf/tfpdf.php';
    if (class_exists('tFPDF')) {
        $tfpdf_loaded = true;
        class PDF_Engine extends tFPDF {
            function Footer() {
                $this->SetY(-15);
                if (file_exists('tfpdf/font/unifont/KantumruyPro-VariableFont_wght.ttf')) {
                    $this->SetFont('Kantumruy', '', 8);
                } else {
                    $this->SetFont('Arial', 'I', 8);
                }
                $this->SetTextColor(150, 150, 150);
                $this->Cell(0, 10, 'Page ' . $this->PageNo() . ' / {nb}', 0, 0, 'C');
            }
        }
    }
}

if (!$tfpdf_loaded) {
    // Fallback Mock class to CSV download
    class PDF_Engine {
        public function __construct() {
            header('Content-Type: text/csv; charset=utf-8');
            $filename = 'payment_report_' . date('Y-m-d');
            global $filter_date;
            if (!empty($filter_date)) {
                $filename .= '_filtered_' . $filter_date;
            }
            header('Content-Disposition: attachment; filename=' . $filename . '.csv');
            echo "\xEF\xBB\xBF"; // UTF-8 BOM for Khmer encoding in Excel
            $output = fopen('php://output', 'w');
            fputcsv($output, ['កាលបរិច្ឆេទ', 'បរិយាយ', 'អ្នកបន្ថែម', 'ប្រភេទ', 'ចំនួនទឹកប្រាក់ (រៀល)', 'ចំនួនទឹកប្រាក់ (ដុល្លារ)']);
            global $transactions;
            foreach ($transactions as $row) {
                fputcsv($output, [
                    $row['date'],
                    $row['description'],
                    $row['creator_name'] ?? $row['user_id'],
                    $row['type'] === 'income' ? 'ចំណូល' : 'ចំណាយ',
                    $row['currency'] === 'KHR' ? number_format($row['amount']) . ' KHR' : '0 KHR',
                    $row['currency'] === 'USD' ? '$' . number_format($row['amount'], 2) : '$0.00'
                ]);
            }
            fclose($output);
            exit;
        }
    }
}

if (class_exists('PDF_Engine') && $tfpdf_loaded) {
    $pdf = new PDF_Engine();
    $pdf->AliasNbPages();
    $pdf->AddPage();
    
    $has_kantumruy = file_exists('tfpdf/font/unifont/KantumruyPro-VariableFont_wght.ttf');
    if ($has_kantumruy) {
        $pdf->AddFont('Kantumruy', '', 'KantumruyPro-VariableFont_wght.ttf', true);
        $pdf->SetFont('Kantumruy', '', 11);
    } else {
        $pdf->SetFont('Arial', '', 10);
    }

    // --- STYLING CONSTANTS & COLORS ---
    $color_primary = [30, 58, 138];    // Deep Blue (#1e3a8a)
    $color_secondary = [37, 99, 235];  // Slate Blue (#2563eb)
    $color_success = [16, 185, 129];   // Green (#10b981)
    $color_danger = [239, 68, 68];     // Red (#ef4444)
    $color_text_dark = [31, 41, 55];   // Dark Gray (#1f2937)
    $color_bg_light = [248, 250, 252];  // Alternating light gray (#f8fafc)

    // Unify Riel symbol vs KHR characters based on font availability to prevent raw utf-8 encoding errors
    $khr_symbol = $has_kantumruy ? ' ៛' : ' KHR';

    // 1. Decorative Header Accent Line
    $pdf->SetDrawColor($color_primary[0], $color_primary[1], $color_primary[2]);
    $pdf->SetLineWidth(1);
    $pdf->Line(10, 12, 200, 12);
    $pdf->Ln(5);

    // 2. Report Title Block
    $pdf->SetTextColor($color_primary[0], $color_primary[1], $color_primary[2]);
    if ($has_kantumruy) {
        $pdf->SetFont('Kantumruy', '', 18);
        $pdf->Cell(0, 12, "របាយការណ៍ហិរញ្ញវត្ថុប្រចាំប្រព័ន្ធ", 0, 1, 'C');
    } else {
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell(0, 12, "Payment Tracker Financial Report", 0, 1, 'C');
    }

    // Subtitle Info
    $pdf->SetTextColor(107, 114, 128); // Muted gray
    if ($has_kantumruy) {
        $pdf->SetFont('Kantumruy', '', 8.5);
        $subtitle = "កាលបរិច្ឆេទបញ្ចេញរបាយការណ៍៖ " . date('Y-m-d H:i:s') . " | រៀបចំដោយ៖ " . $username . " (" . strtoupper($user_role) . ")";
        if (!empty($filter_date)) {
            $subtitle .= " | ថ្ងៃដែលបានចម្រោះ៖ " . $filter_date;
        }
    } else {
        $pdf->SetFont('Arial', 'I', 8.5);
        $subtitle = "Date: " . date('Y-m-d H:i:s') . " | Exported by: " . $username . " (" . strtoupper($user_role) . ")";
        if (!empty($filter_date)) {
            $subtitle .= " | Filtered Date: " . $filter_date;
        }
    }
    $pdf->Cell(0, 6, $subtitle, 0, 1, 'C');
    $pdf->Ln(6);

    // 3. FINANCIAL SUMMARY CARDS BLOCK (Beautiful Dashboard inside PDF - WITHOUT EMOJIS TO PREVENT ENCODING BUGS)
    $pdf->SetDrawColor(226, 232, 240); // Card border
    $pdf->SetLineWidth(0.3);

    // Card 1: Balance Card
    $pdf->SetFillColor(240, 253, 250); // Light emerald green bg
    $pdf->Rect(10, 42, 60, 25, 'DF');
    $pdf->SetXY(12, 44);
    $pdf->SetTextColor(15, 118, 110); // Emerald text
    $pdf->SetFont($has_kantumruy ? 'Kantumruy' : 'Arial', 'B', 9);
    $pdf->Cell(56, 5, $has_kantumruy ? "សមតុល្យសរុប (Total Balance)" : "Total Balance", 0, 1);
    $pdf->SetX(12);
    $pdf->SetTextColor($bal_khr >= 0 ? 15 : $color_danger[0], $bal_khr >= 0 ? 118 : $color_danger[1], $bal_khr >= 0 ? 110 : $color_danger[2]);
    $pdf->SetFont($has_kantumruy ? 'Kantumruy' : 'Arial', '', 9.5);
    $pdf->Cell(56, 5, number_format($bal_khr) . $khr_symbol, 0, 1);
    $pdf->SetX(12);
    $pdf->Cell(56, 5, "$" . number_format($bal_usd, 2), 0, 1);

    // Card 2: Income Card
    $pdf->SetFillColor(240, 253, 244); // Light green bg
    $pdf->Rect(75, 42, 60, 25, 'DF');
    $pdf->SetXY(77, 44);
    $pdf->SetTextColor(21, 128, 61); // Green text
    $pdf->SetFont($has_kantumruy ? 'Kantumruy' : 'Arial', 'B', 9);
    $pdf->Cell(56, 5, $has_kantumruy ? "ចំណូលសរុប (Total Income)" : "Total Income", 0, 1);
    $pdf->SetX(77);
    $pdf->SetFont($has_kantumruy ? 'Kantumruy' : 'Arial', '', 9.5);
    $pdf->Cell(56, 5, number_format($total_income_khr) . $khr_symbol, 0, 1);
    $pdf->SetX(77);
    $pdf->Cell(56, 5, "$" . number_format($total_income_usd, 2), 0, 1);

    // Card 3: Expense Card
    $pdf->SetFillColor(254, 242, 242); // Light red bg
    $pdf->Rect(140, 42, 60, 25, 'DF');
    $pdf->SetXY(142, 44);
    $pdf->SetTextColor(185, 28, 28); // Red text
    $pdf->SetFont($has_kantumruy ? 'Kantumruy' : 'Arial', 'B', 9);
    $pdf->Cell(56, 5, $has_kantumruy ? "ចំណាយសរុប (Total Expense)" : "Total Expense", 0, 1);
    $pdf->SetX(142);
    $pdf->SetFont($has_kantumruy ? 'Kantumruy' : 'Arial', '', 9.5);
    $pdf->Cell(56, 5, number_format($total_expense_khr) . $khr_symbol, 0, 1);
    $pdf->SetX(142);
    $pdf->Cell(56, 5, "$" . number_format($total_expense_usd, 2), 0, 1);

    $pdf->Ln(15);
    $pdf->SetY(75);

    // 4. TRANSACTION TABLE HEADERS WITH DEEP BLUE STYLE
    $pdf->SetFillColor($color_primary[0], $color_primary[1], $color_primary[2]);
    $pdf->SetTextColor(255, 255, 255); // White text
    $pdf->SetDrawColor(226, 232, 240); // Table grid line color
    $pdf->SetLineWidth(0.15);
    $pdf->SetFont($has_kantumruy ? 'Kantumruy' : 'Arial', '', 10);

    // Setup widths (Total = 190)
    $w_date = 35;
    $w_desc = 50;
    $w_user = 25;
    $w_type = 20;
    $w_khr  = 30;
    $w_usd  = 30;

    if ($has_kantumruy) {
        $pdf->Cell($w_date, 10, 'កាលបរិច្ឆេទ', 1, 0, 'C', true);
        $pdf->Cell($w_desc, 10, 'បរិយាយប្រតិបត្តិការ', 1, 0, 'L', true);
        $pdf->Cell($w_user, 10, 'អ្នកបញ្ចូល', 1, 0, 'C', true);
        $pdf->Cell($w_type, 10, 'ប្រភេទ', 1, 0, 'C', true);
        $pdf->Cell($w_khr, 10, 'ចំនួនទឹកប្រាក់ (៛)', 1, 0, 'R', true);
        $pdf->Cell($w_usd, 10, 'ចំនួនទឹកប្រាក់ ($)', 1, 1, 'R', true);
    } else {
        $pdf->Cell($w_date, 10, 'Date / Time', 1, 0, 'C', true);
        $pdf->Cell($w_desc, 10, 'Description', 1, 0, 'L', true);
        $pdf->Cell($w_user, 10, 'Created By', 1, 0, 'C', true);
        $pdf->Cell($w_type, 10, 'Type', 1, 0, 'C', true);
        $pdf->Cell($w_khr, 10, 'Amount (KHR)', 1, 0, 'R', true);
        $pdf->Cell($w_usd, 10, 'Amount (USD)', 1, 1, 'R', true);
    }

    // 5. TABLE ROWS WITH ALTERNATING ZEBRA STRIPING
    $fill = false;
    $pdf->SetTextColor($color_text_dark[0], $color_text_dark[1], $color_text_dark[2]);
    $pdf->SetFont($has_kantumruy ? 'Kantumruy' : 'Arial', '', 9);

    foreach ($transactions as $row) {
        // Set light zebra background color
        if ($fill) {
            $pdf->SetFillColor($color_bg_light[0], $color_bg_light[1], $color_bg_light[2]);
        } else {
            $pdf->SetFillColor(255, 255, 255);
        }

        // Cell 1: Date and Time
        $pdf->Cell($w_date, 8, $row['date'], 1, 0, 'C', true);

        // Cell 2: Description (Safe trim if too long)
        $desc_text = $row['description'];
        if (mb_strlen($desc_text, 'utf-8') > 22) {
            $desc_text = mb_substr($desc_text, 0, 20, 'utf-8') . '...';
        }
        $pdf->Cell($w_desc, 8, $desc_text, 1, 0, 'L', true);

        // Cell 3: Creator Username
        $creator = $row['creator_name'] ?? ('ID: ' . $row['user_id']);
        if (strlen($creator) > 12) {
            $creator = substr($creator, 0, 10) . '..';
        }
        $pdf->Cell($w_user, 8, $creator, 1, 0, 'C', true);

        // Cell 4: Type (Colored: Green for Income, Red for Expense)
        if ($row['type'] === 'income') {
            $pdf->SetTextColor($color_success[0], $color_success[1], $color_success[2]);
            $pdf->Cell($w_type, 8, $has_kantumruy ? 'ចំណូល' : 'Income', 1, 0, 'C', true);
        } else {
            $pdf->SetTextColor($color_danger[0], $color_danger[1], $color_danger[2]);
            $pdf->Cell($w_type, 8, $has_kantumruy ? 'ចំណាយ' : 'Expense', 1, 0, 'C', true);
        }
        // Reset Dark Text Color
        $pdf->SetTextColor($color_text_dark[0], $color_text_dark[1], $color_text_dark[2]);

        // Cell 5: Amount KHR (Use Unified khr_symbol to prevent encoding error áŸ›)
        $khr_text = ($row['currency'] === 'KHR') ? number_format($row['amount']) . $khr_symbol : '-';
        $pdf->Cell($w_khr, 8, $khr_text, 1, 0, 'R', true);

        // Cell 6: Amount USD
        $usd_text = ($row['currency'] === 'USD') ? '$' . number_format($row['amount'], 2) : '-';
        $pdf->Cell($w_usd, 8, $usd_text, 1, 1, 'R', true);

        $fill = !$fill; // Toggle zebra row fill
    }

    // 6. Output PDF File
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="payment_report_' . date('Ymd') . '.pdf"');
    $pdf->Output('D');
}
?>