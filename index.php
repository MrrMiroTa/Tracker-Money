<?php
/**
 * index-v2.php - Main Financial Dashboard with Automated LocalStorage Synchronization
 * Part of the Khmer Payment Tracker and Financial Management System
 * 
 * This file serves as the core user dashboard. It displays real-time financial widgets (metrics),
 * a transaction creation form, a paginated transaction history table, and admin control panels.
 * It automatically synchronizes the PHP Session to Browser LocalStorage to ensure seamless Javascript integration.
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
    <title>ប្រព័ន្ធគ្រប់គ្រងហិរញ្ញវត្ថុ - Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Kantumruy+Pro:wght@300;400;600;700&family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="admin-style.css">
    <link rel="icon" type="image/x-icon" href="icon.png">
    <style>
        body {
            font-family: 'Kantumruy Pro', 'Inter', sans-serif;
            background-color: #f3f4f6;
            margin: 0;
            padding: 0;
            color: #1f2937;
        }
        .navbar {
            background: #1e3a8a;
            color: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        .navbar-brand {
            font-size: 1.25rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .navbar-nav {
            display: flex;
            gap: 1.5rem;
            align-items: center;
        }
        .navbar-nav a {
            color: #e0e7ff;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.2s;
        }
        .navbar-nav a:hover {
            color: white;
        }
        .logout-btn {
            background-color: #ef4444;
            color: white !important;
            padding: 0.4rem 1rem;
            border-radius: 6px;
            transition: background 0.2s !important;
        }
        .logout-btn:hover {
            background-color: #dc2626;
        }
        .dashboard-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1.5rem;
        }
        .welcome-banner {
            margin-bottom: 2rem;
        }
        .welcome-banner h1 {
            margin: 0;
            font-size: 1.75rem;
            color: #1f2937;
        }
        .welcome-banner p {
            margin: 0.25rem 0 0 0;
            color: #6b7280;
        }
        .main-content-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 2rem;
            align-items: start;
        }
        @media (min-width: 992px) {
            .main-content-grid {
                grid-template-columns: 4fr 8fr;
            }
        }
        .form-title {
            margin-top: 0;
            margin-bottom: 1.5rem;
            font-size: 1.25rem;
            font-weight: 700;
            color: #1e3a8a;
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 0.5rem;
        }
        .form-group {
            margin-bottom: 1.25rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            font-size: 0.9rem;
            color: #475569;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 0.6rem;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-family: inherit;
            font-size: 0.95rem;
            box-sizing: border-box;
        }
        .amount-input-group {
            display: flex;
            gap: 0.5rem;
        }
        .amount-input-group input {
            flex: 2;
        }
        .amount-input-group select {
            flex: 1;
        }
        .btn-submit {
            width: 100%;
            background-color: #2563eb;
            color: white;
            border: none;
            padding: 0.75rem;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            font-family: inherit;
            font-size: 1rem;
            transition: background-color 0.2s;
        }
        .btn-submit:hover {
            background-color: #1d4ed8;
        }
        .table-header-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        .table-header-row h2 {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 700;
            color: #1e3a8a;
        }
        .filter-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .filter-group label {
            font-weight: 600;
            font-size: 0.85rem;
            color: #475569;
            white-space: nowrap;
        }
        .filter-group input {
            padding: 0.4rem;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-family: inherit;
            font-size: 0.85rem;
        }
        .card {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            border: 1px solid #e5e7eb;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.02);
        }
    </style>
</head>
<body>

    <!-- Dynamic Session-to-LocalStorage Syncer (CRITICAL SECURITY FIX) -->
    <script>
        localStorage.setItem('current_user', JSON.stringify({
            user_id: <?php echo json_encode($_SESSION['user_id']); ?>,
            username: <?php echo json_encode($_SESSION['username']); ?>,
            role: <?php echo json_encode($_SESSION['role']); ?>
        }));
    </script>

    <!-- Navigation Bar -->
    <nav class="navbar">
        <div class="navbar-brand">
            <span>📊 ប្រព័ន្ធគ្រប់គ្រងហិរញ្ញវត្ថុ</span>
        </div>
        <div class="navbar-nav">
            <a href="index.php">Dashboard</a>
            <a href="profile.php">ប្រវត្តិរូបផ្ទាល់ខ្លួន</a>
            <?php if ($role === 'super_admin' || $role === 'admin'): ?>
                <a href="archive-history.php">បណ្ណសារសវនកម្ម (History)</a>
                <a href="pdf.php" target="_blank">ទាញយក PDF</a>
                <a href="export-csv.php" target="_blank">នាំចេញ CSV</a>
            <?php endif; ?>
            <a href="#" onclick="logoutUser(); return false;" class="logout-btn">ចាកចេញ</a>
        </div>
    </nav>

    <div class="dashboard-container">
        <!-- Welcome Banner -->
        <div class="welcome-banner">
            <h1>សួស្តី, <?php echo htmlspecialchars($username); ?>!</h1>
            <p>នេះជាផ្ទាំងស្ថិតិហិរញ្ញវត្ថុប្រចាំថ្ងៃរបស់អ្នក។ តួនាទីបច្ចុប្បន្ន៖ <strong style="text-transform: uppercase; color: #2563eb;"><?php echo htmlspecialchars($role); ?></strong></p>
        </div>

        <!-- Dashboard Widgets (Metrics) -->
        <div class="metrics-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
            
            <!-- ១. កាតសមតុល្យសរុប -->
            <div class="metric-card" style="background: #ffffff; padding: 1.5rem; border-radius: 10px; border: 1px solid #e5e7eb; box-shadow: 0 4px 6px rgba(0,0,0,0.02);">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem;">
                    <h3 style="margin: 0; font-size: 1.1rem; color: #374151;">💰 សមតុល្យសរុប (Total Balance)</h3>
                    <span style="font-size: 1.5rem;">💵</span>
                </div>
                <div style="display: flex; flex-direction: column; gap: 0.25rem;">
                    <span style="font-size: 0.9rem; color: #6b7280;">KHR (រៀល)៖ <strong id="total-balance-khr" style="font-size: 1.25rem; color: #10b981;">0 ៛</strong></span>
                    <span style="font-size: 0.9rem; color: #6b7280;">USD (ដុល្លារ)៖ <strong id="total-balance-usd" style="font-size: 1.25rem; color: #10b981;">$0.00</strong></span>
                </div>
            </div>

            <!-- ២. កាតចំណូលសរុប -->
            <div class="metric-card" style="background: #ffffff; padding: 1.5rem; border-radius: 10px; border: 1px solid #e5e7eb; box-shadow: 0 4px 6px rgba(0,0,0,0.02);">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem;">
                    <h3 style="margin: 0; font-size: 1.1rem; color: #374151;">📈 ចំណូលសរុប (Total Income)</h3>
                    <span style="font-size: 1.5rem;">📈</span>
                </div>
                <div style="display: flex; flex-direction: column; gap: 0.25rem;">
                    <span style="font-size: 0.9rem; color: #6b7280;">KHR (រៀល)៖ <strong id="total-income-khr" style="font-size: 1.25rem; color: #10b981;">0 ៛</strong></span>
                    <span style="font-size: 0.9rem; color: #6b7280;">USD (ដុល្លារ)៖ <strong id="total-income-usd" style="font-size: 1.25rem; color: #10b981;">$0.00</strong></span>
                </div>
            </div>

            <!-- ៣. កាតចំណាយសរុប -->
            <div class="metric-card" style="background: #ffffff; padding: 1.5rem; border-radius: 10px; border: 1px solid #e5e7eb; box-shadow: 0 4px 6px rgba(0,0,0,0.02);">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem;">
                    <h3 style="margin: 0; font-size: 1.1rem; color: #374151;">📉 ចំណាយសរុប (Total Expense)</h3>
                    <span style="font-size: 1.5rem;">📉</span>
                </div>
                <div style="display: flex; flex-direction: column; gap: 0.25rem;">
                    <span style="font-size: 0.9rem; color: #6b7280;">KHR (រៀល)៖ <strong id="total-expense-khr" style="font-size: 1.25rem; color: #ef4444;">0 ៛</strong></span>
                    <span style="font-size: 0.9rem; color: #6b7280;">USD (ដុល្លារ)៖ <strong id="total-expense-usd" style="font-size: 1.25rem; color: #ef4444;">$0.00</strong></span>
                </div>
            </div>

        </div>

        <!-- Main Layout Grid -->
        <div class="main-content-grid">

            <!-- ផ្នែកបន្ថែមប្រតិបត្តិការថ្មី (Transaction Form) -->
            <div class="card">
                <h2 class="form-title">បន្ថែមប្រតិបត្តិការថ្មី</h2>
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
                    <button type="submit" class="btn-submit">រក្សាទុកទិន្នន័យ (Save)</button>
                </form>
            </div>

            <!-- ផ្នែកបញ្ជីប្រតិបត្តិការហិរញ្ញវត្ថុ (Transaction Table) -->
            <div class="card" style="overflow-x: auto;">
                <div class="table-header-row">
                    <h2>បញ្ជីប្រតិបត្តិការហិរញ្ញវត្ថុ</h2>
                    
                    <!-- ឧបករណ៍ច្រោះកាលបរិច្ឆេទ (Date Filter UI) -->
                    <div class="filter-group">
                        <label for="search-date">ស្វែងរកតាមកាលបរិច្ឆេទ៖</label>
                        <input type="date" id="search-date">
                    </div>
                </div>
                
                <table class="transaction-table" style="width: 100%; border-collapse: collapse; margin-top: 1rem;">
                    <thead>
                        <tr style="background-color: #f3f4f6; text-align: left;">
                            <th style="padding: 12px; border-bottom: 2px solid #e5e7eb; font-size: 0.9rem;">កាលបរិច្ឆេទ (Date)</th>
                            <th style="padding: 12px; border-bottom: 2px solid #e5e7eb; font-size: 0.9rem;">បរិយាយ (Description)</th>
                            <th style="padding: 12px; border-bottom: 2px solid #e5e7eb; font-size: 0.9rem;">អ្នកបន្ថែម (You add)</th>
                            <th style="padding: 12px; border-bottom: 2px solid #e5e7eb; font-size: 0.9rem;">ប្រភេទ (Type)</th>
                            <th style="padding: 12px; border-bottom: 2px solid #e5e7eb; font-size: 0.9rem;">ចំនួនទឹកប្រាក់ (Amount)</th>
                            <th style="padding: 12px; border-bottom: 2px solid #e5e7eb; font-size: 0.9rem; text-align: center;">សកម្មភាព (Activity)</th>
                        </tr>
                    </thead>
                    <tbody id="transaction-table-body">
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 20px; color: #6b7280;">កំពុងទាញយកទិន្នន័យប្រតិបត្តិការ...</td>
                        </tr>
                    </tbody>
                </table>

                <!-- របារគ្រប់គ្រងទំព័រ (Pagination Controls Grid) -->
                <div id=\"pagination-controls\"></div>
            </div>

        </div>
    </div>

    <script src="admin-integration.js"></script>
    <script>
        // Set default date input value to current local datetime
        document.addEventListener('DOMContentLoaded', () => {
            const dateInput = document.getElementById('date');
            if (dateInput) {
                const now = new Date();
                const year = now.getFullYear();
                const month = String(now.getMonth() + 1).padStart(2, '0');
                const day = String(now.getDate()).padStart(2, '0');
                const hours = String(now.getHours()).padStart(2, '0');
                const minutes = String(now.getMinutes()).padStart(2, '0');
                dateInput.value = `${year}-${month}-${day}T${hours}:${minutes}`;
            }
        });
    </script>
</body>
</html>