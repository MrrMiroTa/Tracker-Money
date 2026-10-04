<?php
// pdf.php - printable financial report (Khmer-safe).
// WHY HTML: PDF libraries such as tFPDF cannot shape Khmer (subscripts, pre-vowels) and showed garbage when the font file
// was missing. The browser's print engine shapes Khmer correctly, so we render a print-ready page that opens the
// "Save as PDF" dialog automatically.
// Designed for Khmer Payment Tracker and Financial Management System

// 1. Initialize Session and Central Configurations
require_once __DIR__ . '/security-bootstrap.php';

require_once 'config.php';

// (errors are logged, never shown - see security-bootstrap.php)

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

require_once __DIR__ . '/report-filter.php';
$filter_date = applyReportFilter($queryStr, $params, $_GET); // label of the chosen period ('' = all)

$queryStr .= " ORDER BY t.date DESC LIMIT 5000"; // cap keeps huge exports from exhausting memory

try {
    $stmt = $db->prepare($queryStr);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Database error in pdf.php: " . $e->getMessage());
    http_response_code(500);
    die("មានបញ្ហាបច្ចេកទេសក្នុងការទាញយកទិន្នន័យ។ សូមសាកល្បងម្តងទៀត។");
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

// 5. Generate Dynamic Serial Number and Record Action in Security Audit Logs
$export_count = 1;
try {
    // Write log entry first to keep audit trail consistent
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

    // Query total previous EXPORT_PDF_REPORT records to generate sequential serial number (No.)
    $countStmt = $db->prepare("SELECT COUNT(*) FROM audit_logs WHERE action = 'EXPORT_PDF_REPORT'");
    $countStmt->execute();
    $export_count = intval($countStmt->fetchColumn());
    if ($export_count < 1) {
        $export_count = 1;
    }
} catch (PDOException $e) {
    error_log("Failed to write audit log or count exports in pdf.php: " . $e->getMessage());
    $export_count = rand(100, 999); // Fallback secure randomized ID if DB audit log fails
}

// Format sequential number (e.g., 001, 002, 015) and current date time when clicked
$serial_str = str_pad($export_count, 3, '0', STR_PAD_LEFT);
$current_time_str = date('Y-m-d_H-i-s');

// 6. Render the printable report
function h($v): string { // DB text was HTML-encoded on input in older versions -> decode, then escape once
    return htmlspecialchars(html_entity_decode((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_QUOTES, 'UTF-8');
}
$report_name = 'payment_report_No_' . $serial_str . '_' . $current_time_str;
$autoprint = ($_GET['autoprint'] ?? '1') !== '0';
$truncated = count($transactions) >= 5000;
$riel = ' ៛';
$scope = ($user_role === 'super_admin' || $user_role === 'admin') ? 'ទិន្នន័យទាំងអស់' : 'ទិន្នន័យផ្ទាល់ខ្លួន';

header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store');
?><!DOCTYPE html>
<html lang="km">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/x-icon" href="icon.png">
<title><?= h($report_name) ?></title>
<style>
@font-face { font-family: 'KantumruyLocal'; src: url('tfpdf/font/unifont/KantumruyPro-VariableFont_wght.ttf') format('truetype'); font-weight: 100 900; font-display: swap; }
@page { size: A4; margin: 12mm; @bottom-center { content: counter(page) " / " counter(pages); font-size: 9px; color: #6b7280; } }
* { box-sizing: border-box; }
body { margin: 0; font-family: 'KantumruyLocal', 'Kantumruy Pro', 'Khmer UI', 'Leelawadee UI', 'Noto Sans Khmer', 'Khmer OS', system-ui, sans-serif; color: #1f2937; background: #eef0f6; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
.toolbar { position: sticky; top: 0; z-index: 5; display: flex; gap: 12px; align-items: center; justify-content: center; flex-wrap: wrap; padding: 12px; background: #171736; color: #fff; font-size: 14px; }
.toolbar button { background: #4b4bf2; color: #fff; border: 0; border-radius: 12px; padding: 10px 20px; font: inherit; font-weight: 700; cursor: pointer; }
.toolbar a { color: #c7c9ff; }
.sheet { max-width: 210mm; margin: 18px auto; padding: 14mm 12mm; background: #fff; box-shadow: 0 10px 40px -18px rgba(0,0,0,.4); }
.accent { height: 4px; background: #1e3a8a; border-radius: 4px; margin-bottom: 14px; }
h1 { margin: 0 0 4px; text-align: center; color: #1e3a8a; font-size: 22px; }
.sub { text-align: center; color: #6b7280; font-size: 11px; line-height: 1.7; margin-bottom: 14px; }
.cards { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 16px; }
.card { border: 1px solid #e2e8f0; border-radius: 10px; padding: 10px 12px; font-size: 12px; }
.card b { display: block; font-size: 15px; margin-top: 4px; }
.card.bal { background: #f0fdfa; color: #0f766e; } .card.inc { background: #f0fdf4; color: #15803d; } .card.exp { background: #fef2f2; color: #b91c1c; }
table { width: 100%; border-collapse: collapse; font-size: 11px; }
thead { display: table-header-group; }
th { background: #1e3a8a; color: #fff; padding: 8px 6px; font-weight: 600; border: 1px solid #1e3a8a; }
td { padding: 6px; border: 1px solid #e2e8f0; vertical-align: top; overflow-wrap: anywhere; }
tr { break-inside: avoid; page-break-inside: avoid; } tbody tr:nth-child(even) td { background: #f8fafc; }
.r { text-align: right; white-space: nowrap; } .c { text-align: center; } .nw { white-space: nowrap; }
.inc-t { color: #10b981; font-weight: 700; } .exp-t { color: #ef4444; font-weight: 700; }
.note { margin-top: 10px; font-size: 11px; color: #b45309; } .empty { text-align: center; padding: 30px; color: #6b7280; }
@media print { body { background: #fff; } .no-print { display: none !important; } .sheet { margin: 0; padding: 0; box-shadow: none; max-width: none; } }
</style>
</head>
<body>
<div class="toolbar no-print">
    <button type="button" onclick="window.print()">⬇ រក្សាទុកជា PDF</button>
    <span>ក្នុងផ្ទាំងបោះពុម្ព ជ្រើស <b>Destination → Save as PDF</b></span>
    <a href="index.php">← ត្រឡប់ទៅ Dashboard</a>
</div>
<div class="sheet">
    <div class="accent"></div>
    <h1>របាយការណ៍ហិរញ្ញវត្ថុប្រចាំប្រព័ន្ធ</h1>
    <div class="sub">
        លេខរៀងទាញយក៖ No. <?= h($serial_str) ?> &nbsp;|&nbsp; កាលបរិច្ឆេទបញ្ចេញ៖ <?= h(date('Y-m-d H:i:s')) ?> &nbsp;|&nbsp; រៀបចំដោយ៖ <?= h($username) ?><br>
        វិសាលភាព៖ <?= h($scope) ?><?php if ($filter_date !== ''): ?> &nbsp;|&nbsp; រយៈពេលដែលបានចម្រោះ៖ <?= h($filter_date) ?><?php endif; ?> &nbsp;|&nbsp; ចំនួន៖ <?= count($transactions) ?> ប្រតិបត្តិការ
    </div>
    <div class="cards">
        <div class="card bal">សមតុល្យសរុប (Total Balance)<b><?= number_format($bal_khr) . $riel ?></b><b>$<?= number_format($bal_usd, 2) ?></b></div>
        <div class="card inc">ចំណូលសរុប (Total Income)<b><?= number_format($total_income_khr) . $riel ?></b><b>$<?= number_format($total_income_usd, 2) ?></b></div>
        <div class="card exp">ចំណាយសរុប (Total Expense)<b><?= number_format($total_expense_khr) . $riel ?></b><b>$<?= number_format($total_expense_usd, 2) ?></b></div>
    </div>
    <table>
        <thead><tr>
            <th>កាលបរិច្ឆេទ</th><th>បរិយាយប្រតិបត្តិការ</th><th>ប្រភេទក្រុម</th><th>អ្នកបញ្ចូល</th><th>ប្រភេទ</th><th>ចំនួនទឹកប្រាក់ (៛)</th><th>ចំនួនទឹកប្រាក់ ($)</th>
        </tr></thead>
        <tbody>
<?php if (!$transactions): ?>
            <tr><td colspan="7" class="empty">មិនមានប្រតិបត្តិការក្នុងរយៈពេលនេះទេ។</td></tr>
<?php endif; foreach ($transactions as $row): $inc = ($row['type'] === 'income'); ?>
            <tr>
                <td class="nw c"><?= h(substr($row['date'], 0, 16)) ?></td>
                <td><?= h($row['description']) ?></td>
                <td><?= h($row['category']) ?></td>
                <td class="c"><?= h($row['creator_name'] ?? ('ID: ' . $row['user_id'])) ?></td>
                <td class="c <?= $inc ? 'inc-t' : 'exp-t' ?>"><?= $inc ? 'ចំណូល' : 'ចំណាយ' ?></td>
                <td class="r"><?= $row['currency'] === 'KHR' ? number_format((float)$row['amount']) . $riel : '-' ?></td>
                <td class="r"><?= $row['currency'] === 'USD' ? '$' . number_format((float)$row['amount'], 2) : '-' ?></td>
            </tr>
<?php endforeach; ?>
        </tbody>
    </table>
<?php if ($truncated): ?>
    <div class="note">⚠ បង្ហាញត្រឹម ៥០០០ ប្រតិបត្តិការចុងក្រោយប៉ុណ្ណោះ។ សូមជ្រើសរយៈពេលតូចជាងនេះ ដើម្បីទទួលបានទិន្នន័យពេញលេញ។</div>
<?php endif; ?>
</div>
<?php if ($autoprint): ?>
<script>
window.addEventListener('load', function () {
    (document.fonts ? document.fonts.ready : Promise.resolve()).then(function () { setTimeout(function () { window.print(); }, 300); });
});
</script>
<?php endif; ?>
</body>
</html>