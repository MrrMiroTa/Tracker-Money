<?php
/**
 * index-v8.php - Main Financial Dashboard with Fully Responsive & Clear Styling
 * Part of the Khmer Payment Tracker and Financial Management System
 * 
 * This file serves as the core user dashboard. It displays real-time financial widgets (metrics),
 * a transaction creation form, a paginated transaction history table, and admin control panels.
 * Features mobile-first responsive layout grid and high legibility Khmer font styling.
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
            <h1>សួស្តី, <?php echo htmlspecialchars($username); ?>!</h1>
            <p>នេះជាផ្ទាំងស្ថិតិហិរញ្ញវត្ថុប្រចាំថ្ងៃរបស់អ្នក។ តួនាទីបច្ចុប្បន្ន៖ <strong style="text-transform: uppercase; color: #2563eb;"><?php echo htmlspecialchars($role); ?></strong></p>
        </div>

        <!-- Dashboard Widgets (Metrics) -->
        <div class="metrics-grid">
            
            <!-- ១. កាតសមតុល្យសរុប -->
            <div class="metric-card">
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
            <div class="metric-card">
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
            <div class="metric-card">
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

        <?php if ($role === 'super_admin' || $role === 'admin'): ?>
        <!-- របារបញ្ជាអភិបាលប្រព័ន្ធ (Admin Panel Toggle Control Bar) -->
        <div class="admin-control-bar" style="display: flex; gap: 0.75rem; margin-bottom: 1.5rem; flex-wrap: wrap; background-color: var(--white); padding: 1rem; border-radius: var(--radius-lg); border: 1px solid var(--gray-border); box-shadow: var(--shadow);">
            <span style="font-weight: 700; color: #1e3a8a; display: flex; align-items: center; gap: 8px; width: 100%; margin-bottom: 0.5rem; font-size: 1rem;">
                🛠️ ផ្ទាំងគ្រប់គ្រងសិទ្ធិអភិបាលប្រព័ន្ធ (Administrative Controls)
            </span>
            <button id="toggle-create-user-btn" class="btn" style="background-color: #10b981; color: white; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; padding: 10px 18px; font-size: 0.9rem; border-radius: var(--radius-sm); border: none; cursor: pointer; transition: all 0.2s;">
                👤 បង្កើតគណនីថ្មី (Create Account)
            </button>
            <button id="toggle-manage-users-btn" class="btn" style="background-color: #2563eb; color: white; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; padding: 10px 18px; font-size: 0.9rem; border-radius: var(--radius-sm); border: none; cursor: pointer; transition: all 0.2s;">
                👥 គ្រប់គ្រងគណនី (Manage Users)
            </button>
        </div>

        <!-- ធុងផ្ទុកផ្ទាំងអភិបាលប្រព័ន្ធ (Admin Panels Collapsible Container - Spans Full Width for high clarity) -->
        <div id="admin-panels-container" style="margin-bottom: 1.5rem; display: flex; flex-direction: column; gap: 1.5rem;">
            
            <!-- ផ្ទាំងបង្កើតអ្នកប្រើប្រាស់ថ្មី (Collapsible Card 1) -->
            <div id="create-user-panel" class="card" style="display: none; border-left: 5px solid #10b981; animation: slideDownPanel 0.25s ease-out; margin: 0;">
                <h2 class="form-title" style="color: #10b981; border-bottom: 2px solid #ecfdf5; display: flex; align-items: center; gap: 8px;">
                    👤 បង្កើតគណនីអ្នកប្រើប្រាស់ថ្មី
                </h2>
                <form id="admin-create-user-form" style="max-width: 500px; margin: 0 auto; padding: 1rem 0;">
                    <div class="form-group">
                        <label for="new-username">ឈ្មោះអ្នកប្រើប្រាស់ (Username)</label>
                        <input type="text" id="new-username" required placeholder="ឧ. vichea_dev" autocomplete="username">
                    </div>
                    <div class="form-group">
                        <label for="new-password">ពាក្យសម្ងាត់ (Password)</label>
                        <input type="password" id="new-password" required placeholder="••••••••" autocomplete="new-password">
                    </div>
                    <div class="form-group">
                        <label for="new-role">តួនាទី (User Role)</label>
                        <select id="new-role" required>
                            <option value="user">អ្នកប្រើប្រាស់ទូទៅ (USER)</option>
                            <?php if ($role === 'super_admin'): ?>
                                <option value="admin">អភិបាលប្រព័ន្ធ (ADMIN)</option>
                                <option value="super_admin">អភិបាលជាន់ខ្ពស់ (SUPER ADMIN)</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn-submit" style="background-color: #10b981;">បង្កើតគណនី (Create)</button>
                </form>
            </div>

            <!-- ផ្ទាំងបញ្ជីឈ្មោះអ្នកប្រើប្រាស់ និងសកម្មភាព (Collapsible Card 2) -->
            <div id="manage-users-panel" class="card" style="display: none; border-left: 5px solid #2563eb; animation: slideDownPanel 0.25s ease-out; margin: 0;">
                <h2 class="form-title" style="color: #2563eb; border-bottom: 2px solid #eff6ff; display: flex; align-items: center; gap: 8px;">
                    👥 គ្រប់គ្រងគណនីអ្នកប្រើប្រាស់
                </h2>
                <div class="table-responsive">
                    <table class="transaction-table" id="users-table">
                        <thead>
                            <tr>
                                <th>ឈ្មោះអ្នកប្រើប្រាស់ (Username)</th>
                                <th>តួនាទី (Role)</th>
                                <th>ស្ថានភាព (Status)</th>
                                <th style="text-align: center;">សកម្មភាព (Actions)</th>
                            </tr>
                        </thead>
                        <tbody id="admin-users-table-body">
                            <tr>
                                <td colspan="4" style="text-align: center; padding: 20px; color: #6b7280;">កំពុងទាញយកទិន្នន័យ...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

        <style>
            @keyframes slideDownPanel {
                from { opacity: 0; transform: translateY(-10px); }
                to { opacity: 1; transform: translateY(0); }
            }
        </style>
        <?php endif; ?>

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
            <div class="card">
                <div class="table-header-row">
                    <h2>បញ្ជីប្រតិបត្តិការហិរញ្ញវត្ថុ</h2>
                    
                    <!-- ឧបករណ៍ច្រោះកាលបរិច្ឆេទ (Date Filter UI) -->
                    <div class="filter-group">
                        <label for="search-date">ស្វែងរកតាមកាលបរិច្ឆេទ៖</label>
                        <input type="date" id="search-date">
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="transaction-table">
                        <thead>
                            <tr>
                                <th>កាលបរិច្ឆេទ (Date)</th>
                                <th>បរិយាយ (Description)</th>
                                <th>អ្នកបន្ថែម (You add)</th>
                                <th>ប្រភេទ (Type)</th>
                                <th>ចំនួនទឹកប្រាក់ (Amount)</th>
                                <th style="text-align: center;">សកម្មភាព (Activity)</th>
                            </tr>
                        </thead>
                        <tbody id="transaction-table-body">
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 20px; color: #6b7280;">កំពុងទាញយកទិន្នន័យប្រតិបត្តិការ...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- របារគ្រប់គ្រងទំព័រ (Pagination Controls Grid) -->
                <div id="pagination-controls"></div>
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

        // Toggle Admin Collapsible Panels
        document.addEventListener('DOMContentLoaded', () => {
            const toggleCreateBtn = document.getElementById('toggle-create-user-btn');
            const toggleManageBtn = document.getElementById('toggle-manage-users-btn');
            
            const createUserPanel = document.getElementById('create-user-panel');
            const manageUsersPanel = document.getElementById('manage-users-panel');
            
            if (toggleCreateBtn && createUserPanel) {
                toggleCreateBtn.addEventListener('click', () => {
                    if (createUserPanel.style.display === 'none') {
                        // Show Create and Hide Manage
                        createUserPanel.style.display = 'block';
                        manageUsersPanel.style.display = 'none';
                        
                        toggleCreateBtn.style.backgroundColor = '#059669'; // Darker green
                        toggleCreateBtn.innerHTML = '✕ បិទផ្ទាំងបង្កើត (Close Create)';
                        
                        // Reset Manage Button
                        toggleManageBtn.style.backgroundColor = '#2563eb';
                        toggleManageBtn.innerHTML = '👥 គ្រប់គ្រងគណនី (Manage Users)';
                    } else {
                        createUserPanel.style.display = 'none';
                        toggleCreateBtn.style.backgroundColor = '#10b981';
                        toggleCreateBtn.innerHTML = '👤 បង្កើតគណនីថ្មី (Create Account)';
                    }
                });
            }
            
            if (toggleManageBtn && manageUsersPanel) {
                toggleManageBtn.addEventListener('click', () => {
                    if (manageUsersPanel.style.display === 'none') {
                        // Show Manage and Hide Create
                        manageUsersPanel.style.display = 'block';
                        createUserPanel.style.display = 'none';
                        
                        toggleManageBtn.style.backgroundColor = '#1d4ed8'; // Darker blue
                        toggleManageBtn.innerHTML = '✕ បិទផ្ទាំងគ្រប់គ្រង (Close Manage)';
                        
                        // Reset Create Button
                        toggleCreateBtn.style.backgroundColor = '#10b981';
                        toggleCreateBtn.innerHTML = '👤 បង្កើតគណនីថ្មី (Create Account)';
                        
                        // Reload table when opened
                        if (typeof loadUsersTable === 'function') {
                            loadUsersTable();
                        }
                    } else {
                        manageUsersPanel.style.display = 'none';
                        toggleManageBtn.style.backgroundColor = '#2563eb';
                        toggleManageBtn.innerHTML = '👥 គ្រប់គ្រងគណនី (Manage Users)';
                    }
                });
            }
        });

        // Form បង្កើតអ្នកប្រើប្រាស់ថ្មីដោយ Admin
        document.addEventListener('DOMContentLoaded', () => {
            const createUserForm = document.getElementById('admin-create-user-form');
            if (createUserForm) {
                createUserForm.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    
                    const usernameInput = document.getElementById('new-username');
                    const passwordInput = document.getElementById('new-password');
                    const roleInput = document.getElementById('new-role');
                    
                    const username = usernameInput.value.trim();
                    const password = passwordInput.value;
                    const role = roleInput.value;
                    
                    try {
                        await createUserByAdmin(username, password, role);
                        createUserForm.reset();
                    } catch (error) {
                        console.error('Error creating user:', error);
                    }
                });
            }
        });

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
