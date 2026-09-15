<?php
/**
 * archive-history.php - Production Audit History & Original Transaction Archives Page
 * Part of the Khmer Payment Tracker and Financial Management System
 * 
 * B2B Fintech SaaS Design with Dark Mode Support & Interactive Restore Workflow
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Authentication Guard
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$username = $_SESSION['username'] ?? 'User';
$role = $_SESSION['role'] ?? 'user';
?>
<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>បណ្ណសារសវនកម្ម - Financial Tracker B2B SaaS</title>
    <link href="https://fonts.googleapis.com/css2?family=Kantumruy+Pro:wght@300;400;500;600;700;800&family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="admin-style.css?v=26.0">
    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme === 'dark') {
                document.documentElement.classList.add('dark-mode');
                document.addEventListener('DOMContentLoaded', () => document.body.classList.add('dark-mode'));
            }
        })();
    </script>
</head>
<body>

    <!-- Sticky Navigation Bar with Burger Toggle -->
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
            <a href="archive-history.php" class="active">បណ្ណសារសវនកម្ម (History)</a>
            <a href="pdf.php" target="_blank">ទាញយក PDF</a>
            <a href="export-csv.php" target="_blank">នាំចេញ CSV</a>
            
            <button id="dark-mode-toggle" onclick="toggleTheme()" class="btn" style="background: rgba(255,255,255,0.15); color: white; border: none; padding: 6px 14px; border-radius: 20px; cursor: pointer; font-size: 0.88rem; display: inline-flex; align-items: center; gap: 6px;">
                🌙 Dark Mode
            </button>
            <a href="#" onclick="logoutUser(); return false;" class="logout-btn">ចាកចេញ</a>
        </div>
    </nav>

    <div class="dashboard-container">

        <!-- Welcome Banner / Header -->
        <div class="welcome-banner">
            <div>
                <h1>📜 បណ្ណសារសវនកម្មប្រតិបត្តិការ (Audit History Logs)</h1>
                <p>តាមដានរាល់ប្រវត្តិការកែប្រែ ការលុបទិន្នន័យ និងសកម្មភាពសន្តិសុខនៃគណនីទាំងអស់ក្នុងប្រព័ន្ធ។</p>
            </div>
            <span class="role-badge-pill">អ្នកប្រើប្រាស់៖ <?php echo htmlspecialchars($username); ?> (<?php echo htmlspecialchars(strtoupper($role)); ?>)</span>
        </div>

        <!-- Metric Summary Cards for Archives -->
        <div class="metrics-grid">
            <div class="metric-card metric-card-expense">
                <div class="metric-header">
                    <h3>🗑️ ប្រតិបត្តិការដែលបានលុប (Deleted)</h3>
                    <div class="metric-icon-box icon-expense">🗑️</div>
                </div>
                <div class="currency-row">
                    <span class="currency-label">ចំនួនសរុប៖</span>
                    <span class="currency-value" id="metric-deleted-count" style="color: var(--danger);">0</span>
                </div>
            </div>

            <div class="metric-card metric-card-balance">
                <div class="metric-header">
                    <h3>📝 ប្រវត្តិធ្វើបច្ចុប្បន្នភាព (Modified Logs)</h3>
                    <div class="metric-icon-box icon-balance">📝</div>
                </div>
                <div class="currency-row">
                    <span class="currency-label">ចំនួនកែប្រែ៖</span>
                    <span class="currency-value" id="metric-modified-count" style="color: var(--primary);">0</span>
                </div>
            </div>

            <div class="metric-card metric-card-income">
                <div class="metric-header">
                    <h3>🛡️ សកម្មភាពសវនកម្មសរុប (Total Logs)</h3>
                    <div class="metric-icon-box icon-income">🛡️</div>
                </div>
                <div class="currency-row">
                    <span class="currency-label">កំណត់ត្រាសរុប៖</span>
                    <span class="currency-value" id="metric-total-audit" style="color: var(--success);">0</span>
                </div>
            </div>
        </div>

        <!-- Main Card Section -->
        <div class="card">
            <div class="table-header-row" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 10px;">
                <h2 style="margin: 0; font-size: 1.25rem; font-weight: 800; color: var(--dark);">📜 កំណត់ត្រាបម្រុងទុកទិន្នន័យដើម (Original Transaction Archives)</h2>
                <button class="btn btn-secondary" style="padding: 6px 14px; font-size: 0.88rem;" onclick="loadArchiveHistoryTable(1)">🔄 Refresh Data</button>
            </div>

            <!-- Date & Action Filter Bar -->
            <div class="date-range-bar">
                <label>ស្វែងរកតាមចម្រោះ៖</label>
                <span>ចាប់ពី៖</span>
                <input type="date" id="archive-filter-from">
                <span>ដល់៖</span>
                <input type="date" id="archive-filter-to">
                <select id="archive-filter-action" style="padding: 6px 10px; border-radius: 6px; border: 1px solid var(--gray-border); font-family: inherit; font-size: 0.88rem;">
                    <option value="">ប្រភេទសកម្មភាពទាំងអស់</option>
                    <option value="DELETE">DELETE (លុប)</option>
                    <option value="UPDATE">UPDATE (កែប្រែ)</option>
                    <option value="RESTORE">RESTORE (ស្តារឡើងវិញ)</option>
                    <option value="CREATE">CREATE (បង្កើត)</option>
                </select>
                <button class="btn btn-secondary" style="padding: 6px 14px; font-size: 0.85rem;" onclick="loadArchiveHistoryTable(1)">🔍 ចម្រោះ</button>
            </div>

            <!-- Table Wrapper -->
            <div class="table-responsive">
                <table class="transaction-table">
                    <thead>
                        <tr>
                            <th>កាលបរិច្ឆេទសកម្មភាព</th>
                            <th>ម្ចាស់ទិន្នន័យ</th>
                            <th>ប្រភេទសកម្មភាព</th>
                            <th>ទិន្នន័យដើម (Original Value)</th>
                            <th>ទិន្នន័យថ្មី (New Value)</th>
                            <th>អ្នកអនុវត្ត</th>
                            <th style="text-align: center;">សកម្មភាព</th>
                        </tr>
                    </thead>
                    <tbody id="archive-history-tbody">
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 25px; color: #6b7280; font-weight: 600;">កំពុងទាញយកទិន្នន័យប្រវត្តិសវនកម្ម...</td>
                        </tr>
                    </tbody>
                    <tbody id="archive-table-body" style="display: none;"></tbody>
                    <tbody id="audit-history-tbody" style="display: none;"></tbody>
                </table>
            </div>

            <!-- Pagination Controls -->
            <div id="archive-pagination-controls"></div>
        </div>

    </div>

    <script src="admin-integration.js?v=26.0"></script>
</body>
</html>
