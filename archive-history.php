<?php
/**
 * archive-history.php - Admin-Only Audit Archive with Fully Responsive Top Navigation Bar
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
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>បណ្ណសារសវនកម្មប្រតិបត្តិការ - Admin Control Panel</title>
    <link href="https://fonts.googleapis.com/css2?family=Kantumruy+Pro:wght@400;600;700&family=Inter:wght@400;600;700&display=swap" rel="stylesheet"/>
    <link rel="stylesheet" href="admin-style.css"/>
</head>
<body>

    <!-- Navigation Bar with Burger Toggle -->
    <nav class="navbar">
        <div class="navbar-brand">
            <span>📊 ប្រព័ន្ធគ្រប់គ្រងហិរញ្ញវត្ថុ</span>
        </div>
        <button class="navbar-toggle" id="navbar-toggle-btn" aria-label="Toggle Navigation">
            <span class="bar"></span>
            <span class="bar"></span>
            <span class="bar"></span>
        </button>
        <div class="navbar-nav" id="navbar-menu">
            <a href="index.php">Dashboard</a>
            <a href="profile.php">ប្រវត្តិរូបផ្ទាល់ខ្លួន</a>
            <?php if ($current_role === 'super_admin' || $current_role === 'admin'): ?>
                <a href="archive-history.php" class="active">បណ្ណសារសវនកម្ម (History)</a>
            <?php endif; ?>
            <a href="pdf.php" target="_blank">ទាញយក PDF</a>
            <a href="export-csv.php" target="_blank">នាំចេញ CSV</a>
            <a href="#" onclick="logoutUser(); return false;" class="logout-btn">ចាកចេញ</a>
        </div>
    </nav>

    <div class="dashboard-container">
        <!-- Page Header -->
        <div class="welcome-banner">
            <h1>📂 បណ្ណសារសវនកម្មប្រតិបត្តិការ (Audit History Log)</h1>
            <p style="margin-top: 0.25rem;">តាមដានរាល់ការកែប្រែ និងការលុបទិន្នន័យប្រតិបត្តិការហិរញ្ញវត្ថុរបស់គណនីទាំងអស់ក្នុងប្រព័ន្ធ</p>
        </div>

        <!-- History Card -->
        <div class="card">
            <h2 class="card-title">📜 កំណត់ត្រាបម្រុងទុកទិន្នន័យដើម (Original Transaction Archives)</h2>
            <div class="table-responsive">
                <table class="transaction-table">
                    <thead>
                        <tr>
                            <th>កាលបរិច្ឆេទសកម្មភាព</th>
                            <th>ម្ចាស់ទិន្នន័យ</th>
                            <th>ប្រភេទសកម្មភាព</th>
                            <th>ទិន្នន័យដើម (Original Value)</th>
                            <th>ទិន្នន័យថ្មី (New Value)</th>
                            <th style="text-align: center;">សកម្មភាព</th>
                            <th>អ្នកអនុវត្ត</th>
                        </tr>
                    </thead>
                    <tbody id="history-table-body">
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 30px; color: #4b5563;">កំពុងទាញយកទិន្នន័យប្រវត្តិសវនកម្ម...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="admin-integration.js"></script>
    <script>
        // Burger menu toggle logic
        document.addEventListener('DOMContentLoaded', () => {
            const toggleBtn = document.getElementById('navbar-toggle-btn');
            const menu = document.getElementById('navbar-menu');
            if (toggleBtn && menu) {
                toggleBtn.addEventListener('click', () => {
                    toggleBtn.classList.toggle('active');
                    menu.classList.toggle('active');
                });
            }
        });

        // Autoload audit history log
        document.addEventListener('DOMContentLoaded', () => {
            if (typeof loadAuditHistoryTable === 'function') {
                loadAuditHistoryTable();
            }
        });
    </script>
</body>
</html>
