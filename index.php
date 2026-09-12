<?php
/**
 * index-v12.php - Ultra-Modern Production Financial Dashboard
 * Part of the Khmer Payment Tracker and Financial Management System
 * 
 * Features:
 * - Ultra-Modern SaaS Dashboard UI with sleek Glassmorphic Header & Cards
 * - Real-time Chart.js Analytics (Income vs Expense Bar Chart, Category Doughnut Chart)
 * - Exchange Rate Converter ($1 USD = X KHR) & Unified Total Balance Calculation
 * - Category Budget Tracking & Threshold Warning Banners
 * - Advanced Date Range Filtering (From Date - To Date)
 * - Audit Log Viewer Modal for Admins
 * - Mobile-first responsive layout with Hamburger Navigation Toggle & Mobile Card Views
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Enforce Authentication Guard
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: login.php");
    exit;
}

$username = $_SESSION['username'] ?? 'User';
$role = $_SESSION['role'] ?? 'user';

// Categories list for form datalist
$categories = ['ម្ហូបអាហារ', 'សម្លៀកបំពាក់', 'ការធ្វើដំណើរ', 'វិក្កយបត្រ', 'ការអប់រំ', 'សុខភាព', 'កម្សាន្ត', 'ផ្សេងៗ'];
?>
<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ប្រព័ន្ធគ្រប់គ្រងហិរញ្ញវត្ថុ - Production Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Kantumruy+Pro:wght@300;400;500;600;700;800&family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="admin-style.css">
    <link rel="icon" type="image/x-icon" href="icon.png">
    <!-- Chart.js Engine for Visual Analytics -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

    <!-- Dynamic Session Syncer -->
    <script>
        localStorage.setItem('current_user', JSON.stringify({
            user_id: <?php echo json_encode($_SESSION['user_id']); ?>,
            username: <?php echo json_encode($_SESSION['username']); ?>,
            role: <?php echo json_encode($_SESSION['role']); ?>
        }));
    </script>

    <!-- Sticky Glassmorphic Navigation Bar with Burger Toggle -->
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
            <?php if ($role === 'super_admin' || $role === 'admin'): ?>
                <a href="archive-history.php">បណ្ណសារសវនកម្ម (History)</a>
            <?php endif; ?>
            <a href="pdf.php" target="_blank">ទាញយក PDF</a>
            <a href="export-csv.php" target="_blank">នាំចេញ CSV</a>
            <a href="#" onclick="logoutUser(); return false;" class="logout-btn">ចាកចេញ</a>
        </div>
    </nav>

    <div class="dashboard-container">
        
        <!-- Welcome Banner -->
        <div class="welcome-banner">
            <div>
                <h1>សួស្តី, <?php echo htmlspecialchars($username); ?>! 👋</h1>
                <p>នេះជាផ្ទាំងស្ថិតិហិរញ្ញវត្ថុប្រចាំថ្ងៃរបស់អ្នក។ តាមដានចំណូល និងចំណាយបានយ៉ាងងាយស្រួល។</p>
            </div>
            <div>
                <span class="role-badge-pill">តួនាទី៖ <?php echo htmlspecialchars($role); ?></span>
            </div>
        </div>

        <!-- 1. Unified Exchange Rate Banner Widget -->
        <div class="exchange-rate-widget">
            <div class="exchange-rate-inputs">
                <span>💱 អត្រាប្តូរប្រាក់៖ <strong>$1 USD =</strong></span>
                <input type="number" id="exchange-rate-input" value="4100" step="50" min="1000">
                <span><strong>៛ KHR</strong></span>
            </div>
            <div class="unified-balance-display">
                <span style="font-weight: 600;">សមតុល្យសរុបរួម (Unified Balance)៖</span>
                <span class="unified-badge" id="unified-total-usd">$0.00</span>
                <span class="unified-badge" id="unified-total-khr">0 ៛</span>
            </div>
        </div>

        <!-- 2. Dashboard Metrics Grid (Individual Currency Cards) -->
        <div class="metrics-grid">
            
            <!-- កាតសមតុល្យសរុប -->
            <div class="metric-card metric-card-balance">
                <div class="metric-header">
                    <h3>💰 សមតុល្យសរុប (Total Balance)</h3>
                    <div class="metric-icon-box icon-balance">💵</div>
                </div>
                <div class="currency-row">
                    <span class="currency-label">KHR (រៀល)</span>
                    <span class="currency-value" id="total-balance-khr" style="color: var(--success);">0 ៛</span>
                </div>
                <div class="currency-row">
                    <span class="currency-label">USD (ដុល្លារ)</span>
                    <span class="currency-value" id="total-balance-usd" style="color: var(--success);">$0.00</span>
                </div>
            </div>

            <!-- កាតចំណូលសរុប -->
            <div class="metric-card metric-card-income">
                <div class="metric-header">
                    <h3>📈 ចំណូលសរុប (Total Income)</h3>
                    <div class="metric-icon-box icon-income">📈</div>
                </div>
                <div class="currency-row">
                    <span class="currency-label">KHR (រៀល)</span>
                    <span class="currency-value" id="total-income-khr" style="color: var(--success);">0 ៛</span>
                </div>
                <div class="currency-row">
                    <span class="currency-label">USD (ដុល្លារ)</span>
                    <span class="currency-value" id="total-income-usd" style="color: var(--success);">$0.00</span>
                </div>
            </div>

            <!-- កាតចំណាយសរុប -->
            <div class="metric-card metric-card-expense">
                <div class="metric-header">
                    <h3>📉 ចំណាយសរុប (Total Expense)</h3>
                    <div class="metric-icon-box icon-expense">📉</div>
                </div>
                <div class="currency-row">
                    <span class="currency-label">KHR (រៀល)</span>
                    <span class="currency-value" id="total-expense-khr" style="color: var(--danger);">0 ៛</span>
                </div>
                <div class="currency-row">
                    <span class="currency-label">USD (ដុល្លារ)</span>
                    <span class="currency-value" id="total-expense-usd" style="color: var(--danger);">$0.00</span>
                </div>
            </div>

        </div>

        <!-- 3. Category Budget Threshold Alert Banners -->
        <div id="budget-alerts-container" class="budget-alerts-container"></div>

        <!-- 4. Visual Analytics Section (Chart.js Section) -->
        <div class="charts-grid">
            <!-- ក្រាហ្វិកប្រៀបធៀបចំណូល-ចំណាយ -->
            <div class="chart-card">
                <div class="chart-header">
                    <span>📊 ក្រាហ្វិកប្រៀបធៀបចំណូល និងចំណាយ ($ USD)</span>
                </div>
                <div class="chart-container">
                    <canvas id="chart-income-expense"></canvas>
                </div>
            </div>

            <!-- ក្រាហ្វិកចំណាយតាមប្រភេទក្រុម -->
            <div class="chart-card">
                <div class="chart-header">
                    <span>🍩 ចំណាយតាមប្រភេទក្រុម (Expense Categories)</span>
                </div>
                <div class="chart-container">
                    <canvas id="chart-category-doughnut"></canvas>
                </div>
            </div>
        </div>

        <?php if ($role === 'super_admin' || $role === 'admin'): ?>
        <!-- 5. Admin Control Bar -->
        <div class="admin-control-bar">
            <span style="font-weight: 800; color: var(--dark); display: flex; align-items: center; gap: 8px; width: 100%; margin-bottom: 0.25rem; font-size: 1.05rem;">
                🛠️ ផ្ទាំងគ្រប់គ្រងសិទ្ធិអភិបាលប្រព័ន្ធ (Administrative Controls)
            </span>
            <button id="toggle-create-user-btn" class="btn" style="background-color: var(--success); color: white;">
                👤 បង្កើតគណនីថ្មី (Create Account)
            </button>
            <button id="toggle-manage-users-btn" class="btn" style="background-color: var(--primary); color: white;">
                👥 គ្រប់គ្រងគណនី (Manage Users)
            </button>
            <button onclick="openAuditLogModal()" class="btn" style="background-color: #8b5cf6; color: white;">
                📜 មើល Audit Logs (Audit Log Viewer)
            </button>
        </div>
        <?php endif; ?>

        <!-- 6. Main Layout Grid (Form & Table) -->
        <div class="main-content-grid">

            <!-- ផ្នែកបន្ថែមប្រតិបត្តិការថ្មី (Transaction Form) -->
            <div class="card">
                <h2 class="form-title">➕ បន្ថែមប្រតិបត្តិការថ្មី</h2>
                <form id="transaction-form">
                    <div class="form-group">
                        <label for="title">បរិយាយ / ឈ្មោះប្រតិបត្តិការ</label>
                        <input type="text" id="title" required placeholder="ឧ. បើកប្រាក់ខែ, ទិញម្ហូប...">
                    </div>
                    <div class="form-group">
                        <label for="amount">ចំនួនទឹកប្រាក់</label>
                        <div class="amount-input-group">
                            <input type="number" id="amount" step="any" required placeholder="0.00">
                            <select id="currency" required>
                                <option value="KHR">រៀល (៛)</option>
                                <option value="USD">ដុល្លារ ($)</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="type">ប្រភេទប្រតិបត្តិការ</label>
                        <select id="type" required>
                            <option value="income">ចំណូល (Income)</option>
                            <option value="expense">ចំណាយ (Expense)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="category">ប្រភេទក្រុម (Category)</label>
                        <input type="text" id="category" list="category-list" required placeholder="ឧ. ម្ហូបអាហារ, ផ្ទះបាយ, ធ្វើដំណើរ...">
                        <datalist id="category-list">
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= htmlspecialchars($cat) ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    <div class="form-group">
                        <label for="date">កាលបរិច្ឆេទ</label>
                        <input type="datetime-local" id="date" required>
                    </div>
                    <button type="submit" class="btn-submit">💾 រក្សាទុកទិន្នន័យ (Save Transaction)</button>
                </form>
            </div>

            <!-- ផ្នែកបញ្ជីប្រតិបត្តិការហិរញ្ញវត្ថុ (Transaction Table) -->
            <div class="card">
                <div class="table-header-row">
                    <h2 style="margin: 0; font-size: 1.25rem; font-weight: 800; color: var(--dark);">📋 បញ្ជីប្រតិបត្តិការហិរញ្ញវត្ថុ</h2>
                </div>

                <!-- 7. Advanced Date Range Filter Bar -->
                <div class="date-range-bar">
                    <label>ស្វែងរកតាមចន្លោះកាលបរិច្ឆេទ៖</label>
                    <span>ចាប់ពី៖</span>
                    <input type="date" id="filter-from-date">
                    <span>ដល់៖</span>
                    <input type="date" id="filter-to-date">
                    <button class="btn btn-secondary" style="padding: 6px 14px; font-size: 0.85rem;" onclick="loadTransactionsTable(1)">🔍 ចម្រោះ</button>
                </div>

                <!-- Responsive Table Wrapper -->
                <div class="table-responsive">
                    <table class="transaction-table">
                        <thead>
                            <tr>
                                <th>កាលបរិច្ឆេទ (Date)</th>
                                <th>បរិយាយ (Description)</th>
                                <th>អ្នកបន្ថែម (You add)</th>
                                <th>ប្រភេទ (Type)</th>
                                <th>ចំនួនទឹកប្រាក់ (Amount)</th>
                                <th>វិក្កយបត្រ (Receipt)</th>
                                <th style="text-align: center;">សកម្មភាព (Activity)</th>
                            </tr>
                        </thead>
                        <tbody id="transaction-table-body">
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 20px; color: var(--gray-text);">កំពុងទាញយកទិន្នន័យប្រតិបត្តិការ...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- របារគ្រប់គ្រងទំព័រ (Pagination Controls Grid) -->
                <div id="pagination-controls"></div>
            </div>

        </div>

    </div>

    <!-- 8. Audit Log Viewer Popup Modal -->
    <div id="audit-log-modal" class="modal-backdrop">
        <div class="modal-content-card">
            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid var(--gray-bg); padding-bottom: 0.85rem; margin-bottom: 1.25rem;">
                <h3 style="margin: 0; color: var(--dark); font-weight: 800;">📜 កំណត់ហេតុសវនកម្មសន្តិសុខ (Audit Log Viewer)</h3>
                <button class="btn btn-secondary" style="padding: 4px 12px;" onclick="closeAuditLogModal()">&times; បិទ</button>
            </div>
            <div class="table-responsive" style="max-height: 420px; overflow-y: auto;">
                <table class="transaction-table" style="width: 100%;">
                    <thead>
                        <tr>
                            <th>កាលបរិច្ឆេទ</th>
                            <th>អ្នកប្រព្រឹត្ត</th>
                            <th>សកម្មភាព</th>
                            <th>ព័ត៌មានលម្អិត</th>
                            <th>IP Address</th>
                        </tr>
                    </thead>
                    <tbody id="audit-log-table-body">
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 20px; color: var(--gray-text);">កំពុងទាញយក...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="admin-integration.js"></script>
</body>
</html>
