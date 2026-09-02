<?php
/**
 * archive-history.php - Admin-Only Audit Archive for Modified/Deleted Transactions
 * Part of the Khmer Payment Tracker and Financial Management System
 * 
 * Enforces strict role guard (Admins/Super Admins only) and displays the original 
 * vs. modified values for all transactions updated or deleted by users.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';

// --- ១. ត្រួតពិនិត្យសន្តិសុខ និងសិទ្ធិចូលមើល (Admin Guard) ---
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: login.php");
    exit;
}

$current_role = $_SESSION['role'];
if ($current_role !== 'super_admin' && $current_role !== 'admin') {
    // ប្រសិនបើជា User ធម្មតា គ្មានសិទ្ធិបើកទំព័រនេះទេ បណ្តេញចេញទៅ Dashboard
    header("Location: index.php");
    exit;
}

$username = $_SESSION['username'] ?? 'Unknown';
?>
<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>បណ្ណសារសវនកម្មប្រតិបត្តិការ - Admin Control Panel</title>
    <link href="https://fonts.googleapis.com/css2?family=Kantumruy+Pro:wght@400;600;700&family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="admin-style.css">
    <style>
        body {
            font-family: 'Kantumruy Pro', 'Inter', sans-serif;
            background-color: #f3f4f6;
            margin: 0;
            color: #1f2937;
        }

        .layout-container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar Styles */
        .sidebar {
            width: 260px;
            background-color: #1f2937;
            color: #ffffff;
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .sidebar h2 {
            font-size: 1.25rem;
            margin: 0;
            padding-bottom: 1rem;
            border-bottom: 1px solid #374151;
            color: #3b82f6;
        }

        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .sidebar-menu a {
            color: #d1d5db;
            text-decoration: none;
            padding: 0.75rem 1rem;
            border-radius: 6px;
            display: block;
            font-weight: 600;
            transition: all 0.2s ease;
        }

        .sidebar-menu a:hover, .sidebar-menu a.active {
            background-color: #374151;
            color: #ffffff;
        }

        .main-content {
            flex: 1;
            padding: 2rem;
            max-width: 1200px;
            margin: 0 auto;
        }

        /* Page Headers */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .page-title h1 {
            margin: 0;
            font-size: 1.75rem;
            font-weight: 700;
            color: #111827;
        }

        .page-title p {
            margin: 0.25rem 0 0 0;
            color: #6b7280;
            font-size: 0.9rem;
        }

        /* Card Styles */
        .card {
            background-color: #ffffff;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.02), 0 1px 3px rgba(0, 0, 0, 0.05);
            border: 1px solid #e5e7eb;
            margin-bottom: 1.5rem;
        }

        .card-title {
            margin-top: 0;
            font-size: 1.2rem;
            font-weight: 700;
            color: #1f2937;
            border-bottom: 1px solid #f3f4f6;
            padding-bottom: 0.75rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Audit Table Styles */
        .history-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }

        .history-table th {
            background-color: #f9fafb;
            color: #4b5563;
            font-weight: 700;
            padding: 12px;
            text-align: left;
            border-bottom: 2px solid #e5e7eb;
        }

        .history-table td {
            padding: 12px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: top;
        }

        /* Action Badges */
        .badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .badge-update {
            background-color: #fef3c7;
            color: #92400e;
            border: 1px solid #fde68a;
        }

        .badge-delete {
            background-color: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }

        /* Comparison block */
        .comparison-box {
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 0.5rem 0.75rem;
            font-size: 0.8rem;
            line-height: 1.4;
            max-width: 320px;
        }

        .comparison-label {
            font-weight: bold;
            color: #4b5563;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.25rem;
            display: block;
        }

        .diff-text {
            color: #1f2937;
        }

        .diff-highlight {
            background-color: #fef3c7;
            padding: 1px 3px;
            border-radius: 2px;
            font-weight: 600;
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #6b7280;
        }

        .empty-state span {
            font-size: 3rem;
            display: block;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>

<div class="layout-container">
    <!-- Sidebar Left -->
    <div class="sidebar">
        <h2>🛡️ Admin Panel</h2>
        <ul class="sidebar-menu">
            <li><a href="index.php">📊 ផ្ទាំងគ្រប់គ្រង (Dashboard)</a></li>
            <li><a href="profile.php">👤 ប្រវត្តិរូប (Profile)</a></li>
            <li><a href="archive-history.php" class="active">📂 ផ្ទាំងបណ្ណសារសវនកម្ម (Audit)</a></li>
        </ul>
        <div style="margin-top: auto; padding-top: 1rem; border-top: 1px solid #374151; font-size: 0.85rem; color: #9ca3af;">
            គណនី៖ <strong><?php echo htmlspecialchars($username); ?></strong><br>
            តួនាទី៖ <span style="text-transform: uppercase; color: #3b82f6; font-weight: bold;"><?php echo str_replace('_', ' ', $current_role); ?></span>
        </div>
    </div>

    <!-- Main Content Right -->
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div class="page-title">
                <h1>📂 បណ្ណសារសវនកម្មប្រតិបត្តិការ (Audit History Log)</h1>
                <p>តាមដានរាល់ការកែប្រែ និងការលុបទិន្នន័យប្រតិបត្តិការហិរញ្ញវត្ថុរបស់គណនីទាំងអស់ក្នុងប្រព័ន្ធ</p>
            </div>
        </div>

        <!-- History Card -->
        <div class="card">
            <h2 class="card-title">📜 កំណត់ត្រាបម្រុងទុកទិន្នន័យដើម (Original Transaction Archives)</h2>
            <div style="overflow-x: auto;">
                <table class="history-table">
                    <thead>
                        <tr>
                            <th>កាលបរិច្ឆេទសកម្មភាព</th>
                            <th>ម្ចាស់ទិន្នន័យ</th>
                            <th>ប្រភេទសកម្មភាព</th>
                            <th>ទិន្នន័យដើម (Original Value)</th>
                            <th>ទិន្នន័យថ្មី (New Value)</th>
                            <th>អ្នកធ្វើការកែប្រែ / លុប</th>
                        </tr>
                    </thead>
                    <tbody id="history-table-body">
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 30px; color: #4b5563;">កំពុងទាញយកទិន្នន័យប្រវត្តិសវនកម្ម...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="admin-integration.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        // បើមានអនុគមន៍ loadHistory ក្នុង admin-integration.js ឱ្យរត់ភ្លាមៗ
        if (typeof loadAuditHistoryTable === 'function') {
            loadAuditHistoryTable();
        }
    });
</script>
</body>
</html>
