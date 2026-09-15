/**
 * admin-integration.js - Production JavaScript Engine (v25.0)
 * Part of the Khmer Payment Tracker and Financial Management System
 * 
 * Features:
 * - Real-time Chart.js Analytics (Bar Chart, Doughnut Chart with Center Text Overlay)
 * - Administrative Controls: Create User Modal, Manage Users Modal, Reset Password, Delete User
 * - Audit Log Viewer for Admins (fetches get_audit_logs)
 * - Daily Spending Limit Enforcement ($5 / 20,000 KHR Alert Warning)
 * - Exchange Rate Converter ($1 USD = X KHR) & Unified Total Balance Calculation
 * - Category Budget Tracking & Threshold Warnings
 * - Date Range Filtering (From Date - To Date)
 * - Optional Receipt File Upload Handling
 * - 3-Dots (...) Action Dropdown Menu with Update Modal and Alert Confirm Delete
 * - Dark Mode Toggle with LocalStorage Persistence
 * - Robust Logout Function (logoutUser)
 */

const API_BASE_URL = 'api-v2.php';
const API_TRANSACTIONS_URL = 'api-transactions.php';

// Global Chart Instances
let incomeExpenseChart = null;
let categoryDoughnutChart = null;

// Global Exchange Rate State (Default 1 USD = 4,100 KHR)
let currentUsdKhrRate = parseFloat(localStorage.getItem('usd_khr_rate')) || 4100;

// Default Category Budgets (in USD)
const categoryBudgets = {
    'ម្ហូបអាហារ': 150,
    'Food': 100,
    'Room': 100,
    'ការធ្វើដំណើរ': 80,
    'Motor': 50,
    'Breakfast': 30,
    'Dinner': 60
};

document.addEventListener('DOMContentLoaded', () => {
    initApp();
});

/**
 * Initialize Application Engine
 */
function initApp() {
    initThemeState();
    setupNavigation();
    setupExchangeRateWidget();
    setupDateFilterListeners();
    setupTransactionForm();
    setupEditTransactionForm();
    setupAdminControlButtons();
    loadDashboardMetricsAndCharts();
    loadTransactionsTable(1);
}

/**
 * Initialize Theme State (Dark / Light Mode)
 */
function initThemeState() {
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'dark') {
        document.body.classList.add('dark-mode');
        updateToggleButton(true);
    }
}

function toggleTheme() {
    const isDark = document.body.classList.toggle('dark-mode');
    localStorage.setItem('theme', isDark ? 'dark' : 'light');
    updateToggleButton(isDark);
    
    // Re-render charts to update axis/label colors
    loadDashboardMetricsAndCharts();
    showToast(isDark ? '🌙 បានផ្លាស់ប្តូរទៅជា Dark Mode' : '☀️ បានផ្លាស់ប្តូរទៅជា Light Mode', 'info');
}

function updateToggleButton(isDark) {
    const btn = document.getElementById('dark-mode-toggle');
    if (btn) {
        btn.innerHTML = isDark ? '☀️ Light Mode' : '🌙 Dark Mode';
    }
}

/**
 * Logout User Function
 */
async function logoutUser() {
    try {
        await fetch(`${API_BASE_URL}?action=logout`, { method: 'POST' });
    } catch (error) {
        console.error('Logout failed, forcing client-side logout:', error);
    } finally {
        localStorage.removeItem('current_user');
        window.location.href = 'login.php';
    }
}

/**
 * Setup Navigation Toggle
 */
function setupNavigation() {
    const toggleBtn = document.getElementById('navbar-toggle-btn');
    const menu = document.getElementById('navbar-menu');
    
    if (toggleBtn && menu) {
        toggleBtn.addEventListener('click', () => {
            toggleBtn.classList.toggle('active');
            menu.classList.toggle('active');
        });
    }

    // Close action dropdown menus when clicking outside
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.action-dropdown')) {
            document.querySelectorAll('.action-menu.show').forEach(m => m.classList.remove('show'));
        }
    });
}

/**
 * Setup Exchange Rate Converter Widget
 */
function setupExchangeRateWidget() {
    const rateInput = document.getElementById('exchange-rate-input');
    if (rateInput) {
        rateInput.value = currentUsdKhrRate;
        rateInput.addEventListener('change', (e) => {
            const val = parseFloat(e.target.value);
            if (val && val > 0) {
                currentUsdKhrRate = val;
                localStorage.setItem('usd_khr_rate', val);
                showToast(`អត្រាប្តូរប្រាក់ត្រូវបានប្តូរទៅ៖ $1 = ${val.toLocaleString()} ៛`, 'info');
                loadDashboardMetricsAndCharts();
            }
        });
    }
}

/**
 * Setup Date Filter Change Listeners
 */
function setupDateFilterListeners() {
    const fromDateInput = document.getElementById('filter-from-date');
    const toDateInput = document.getElementById('filter-to-date');

    if (fromDateInput && toDateInput) {
        fromDateInput.addEventListener('change', () => loadTransactionsTable(1));
        toDateInput.addEventListener('change', () => loadTransactionsTable(1));
    }
}

/**
 * Get Daily Spending Limit Settings from LocalStorage
 */
function getDailySpendingLimit() {
    const saved = localStorage.getItem('daily_spending_limit');
    if (saved) {
        try { return JSON.parse(saved); } catch(e) {}
    }
    return { enabled: true, usd: 5.00, khr: 20500 };
}

/**
 * Check Daily Spending Limit Warning
 */
async function checkDailySpendingLimitWarning(amountUsd) {
    const limit = getDailySpendingLimit();
    if (!limit.enabled || limit.usd <= 0) return true;

    try {
        const response = await fetch(`${API_TRANSACTIONS_URL}?action=list_all`);
        const result = await response.json();
        if (result.status === 'success' && Array.isArray(result.data)) {
            const todayStr = new Date().toISOString().split('T')[0];
            
            let todayExpenseUSD = 0;
            result.data.forEach(t => {
                if (!t.is_deleted && (t.raw_type || t.type) === 'expense') {
                    const txDate = (t.raw_date || t.date || '').split(' ')[0];
                    if (txDate === todayStr) {
                        const amt = parseFloat(t.raw_amount || t.amount) || 0;
                        const curr = (t.raw_currency || t.currency || '').toUpperCase();
                        todayExpenseUSD += (curr === 'USD') ? amt : (amt / currentUsdKhrRate);
                    }
                }
            });

            const newTotalUSD = todayExpenseUSD + amountUsd;
            if (newTotalUSD > limit.usd) {
                const limitKhr = Math.round(limit.usd * currentUsdKhrRate);
                const newTotalKHR = Math.round(newTotalUSD * currentUsdKhrRate);
                
                const confirmMsg = `🚨 ព្រមាន៖ ការចំណាយប្រចាំថ្ងៃរបស់អ្នកនឹងលើសពីកម្រិតកំណត់!\n\n` +
                    `• កម្រិតកំណត់ប្រចាំថ្ងៃ៖ $${limit.usd.toFixed(2)} (${limitKhr.toLocaleString()} ៛)\n` +
                    `• ការចំណាយសរុបប្រចាំថ្ងៃនឹងកើនដល់៖ $${newTotalUSD.toFixed(2)} (${newTotalKHR.toLocaleString()} ៛)\n\n` +
                    `តើអ្នកពិតជាចង់រក្សាទុកប្រតិបត្តិការចំណាយនេះដែរឬទេ?`;

                return confirm(confirmMsg);
            }
        }
    } catch(err) {
        console.error('Limit check error:', err);
    }
    return true;
}

/**
 * Setup Transaction Form Submission Handler
 */
function setupTransactionForm() {
    const form = document.getElementById('transaction-form');
    if (!form) return;

    const dateInput = document.getElementById('date');
    if (dateInput && !dateInput.value) {
        const now = new Date();
        const pad = (n) => String(n).padStart(2, '0');
        dateInput.value = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}T${pad(now.getHours())}:${pad(now.getMinutes())}`;
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const title = document.getElementById('title').value.trim();
        const amount = parseFloat(document.getElementById('amount').value);
        const currency = document.getElementById('currency').value;
        const type = document.getElementById('type').value;
        const category = document.getElementById('category').value.trim();
        const date = dateInput ? dateInput.value : '';
        const receiptInput = document.getElementById('receipt');

        if (!title || isNaN(amount) || amount <= 0 || !currency || !type || !category) {
            showToast('សូមបំពេញព័ត៌មានឱ្យបានត្រឹមត្រូវ និងគ្រប់គ្រាន់ (ទឹកប្រាក់ត្រូវតែធំជាង ០)!', 'error');
            return;
        }

        // Check daily spending limit if type is expense
        if (type === 'expense') {
            const amountUsd = (currency === 'USD') ? amount : (amount / currentUsdKhrRate);
            const proceed = await checkDailySpendingLimitWarning(amountUsd);
            if (!proceed) return;
        }

        const submitBtn = form.querySelector('button[type="submit"]');
        const originalBtnText = submitBtn ? submitBtn.innerHTML : '';
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '⏳ កំពុងរក្សាទុក...';
        }

        try {
            const formData = new FormData();
            formData.append('title', title);
            formData.append('amount', amount);
            formData.append('currency', currency);
            formData.append('type', type);
            formData.append('category', category);
            if (date) formData.append('date', date);

            if (receiptInput && receiptInput.files && receiptInput.files[0]) {
                formData.append('receipt', receiptInput.files[0]);
            }

            const response = await fetch(API_TRANSACTIONS_URL, {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.status === 'success') {
                showToast(result.message || '🎉 រក្សាទុកប្រតិបត្តិការជោគជ័យ!', 'success');
                form.reset();
                if (receiptInput) receiptInput.value = '';

                if (dateInput) {
                    const now = new Date();
                    const pad = (n) => String(n).padStart(2, '0');
                    dateInput.value = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}T${pad(now.getHours())}:${pad(now.getMinutes())}`;
                }

                loadTransactionsTable(1);
                loadDashboardMetricsAndCharts();
            } else {
                showToast(result.message || 'បរាជ័យក្នុងការរក្សាទុក!', 'error');
            }
        } catch (err) {
            console.error('Error adding transaction:', err);
            showToast('មានបញ្ហាបច្ចេកទេសក្នុងការរក្សាទុកទិន្នន័យ!', 'error');
        } finally {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
            }
        }
    });
}

/**
 * Setup Edit Transaction Form Handler
 */
function setupEditTransactionForm() {
    const form = document.getElementById('edit-transaction-form');
    if (!form) return;

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const id = document.getElementById('edit-tx-id').value;
        const title = document.getElementById('edit-tx-title').value.trim();
        const amount = parseFloat(document.getElementById('edit-tx-amount').value);
        const currency = document.getElementById('edit-tx-currency').value;
        const type = document.getElementById('edit-tx-type').value;
        const category = document.getElementById('edit-tx-category').value.trim();
        const date = document.getElementById('edit-tx-date').value;
        const receiptInput = document.getElementById('edit-tx-receipt');

        if (!id || !title || isNaN(amount) || amount <= 0 || !currency || !type || !category) {
            showToast('សូមបំពេញព័ត៌មានឱ្យបានត្រឹមត្រូវ!', 'error');
            return;
        }

        try {
            const formData = new FormData();
            formData.append('transaction_id', id);
            formData.append('title', title);
            formData.append('amount', amount);
            formData.append('currency', currency);
            formData.append('type', type);
            formData.append('category', category);
            if (date) formData.append('date', date);

            if (receiptInput && receiptInput.files && receiptInput.files[0]) {
                formData.append('receipt', receiptInput.files[0]);
            }

            const response = await fetch(`${API_TRANSACTIONS_URL}?action=update`, {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.status === 'success') {
                showToast(result.message || '🎉 ធ្វើបច្ចុប្បន្នភាពជោគជ័យ!', 'success');
                closeEditTransactionModal();
                loadTransactionsTable(1);
                loadDashboardMetricsAndCharts();
            } else {
                showToast(result.message || 'បរាជ័យក្នុងការធ្វើបច្ចុប្បន្នភាព!', 'error');
            }
        } catch (err) {
            console.error('Error updating transaction:', err);
            showToast('មានបញ្ហាបច្ចេកទេសក្នុងការកែប្រែទិន្នន័យ!', 'error');
        }
    });
}

function openEditTransactionModal(id, title, amount, currency, type, category, date) {
    let modal = document.getElementById('edit-transaction-modal');
    if (modal) {
        document.getElementById('edit-tx-id').value = id;
        document.getElementById('edit-tx-title').value = title;
        document.getElementById('edit-tx-amount').value = amount;
        document.getElementById('edit-tx-currency').value = currency;
        document.getElementById('edit-tx-type').value = type;
        document.getElementById('edit-tx-category').value = category;
        document.getElementById('edit-tx-date').value = date;

        modal.classList.add('active');
    }
}

function closeEditTransactionModal() {
    const modal = document.getElementById('edit-transaction-modal');
    if (modal) modal.classList.remove('active');
}

/**
 * Setup Administrative Controls Event Listeners (Create Account & Manage Users)
 */
function setupAdminControlButtons() {
    const createBtn = document.getElementById('toggle-create-user-btn');
    if (createBtn) {
        createBtn.addEventListener('click', () => openCreateUserModal());
    }

    const manageBtn = document.getElementById('toggle-manage-users-btn');
    if (manageBtn) {
        manageBtn.addEventListener('click', () => openManageUsersModal());
    }

    // Bind Create User Form Submit
    const createUserForm = document.getElementById('create-user-form');
    if (createUserForm) {
        createUserForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const username = document.getElementById('create-username').value.trim();
            const password = document.getElementById('create-password').value;
            const role = document.getElementById('create-role').value;

            if (!username || !password || !role) {
                showToast('សូមបំពេញព័ត៌មានឱ្យបានគ្រប់ជ្រុងជ្រោយ!', 'error');
                return;
            }

            try {
                const response = await fetch(`${API_BASE_URL}?action=create_user`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ username, password, role })
                });

                const result = await response.json();
                if (result.status === 'success') {
                    showToast(result.message || '🎉 បង្កើតគណនីថ្មីជោគជ័យ!', 'success');
                    createUserForm.reset();
                    closeCreateUserModal();
                    if (document.getElementById('manage-users-modal')?.classList.contains('active')) {
                        loadUsersTable();
                    }
                } else {
                    showToast(result.message || 'បរាជ័យក្នុងការបង្កើតគណនី!', 'error');
                }
            } catch(err) {
                console.error('Error creating user:', err);
                showToast('មានបញ្ហាបច្ចេកទេសក្នុងការបង្កើតគណនី!', 'error');
            }
        });
    }

    // Bind Reset Password Form Submit
    const resetPwForm = document.getElementById('reset-password-form');
    if (resetPwForm) {
        resetPwForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const target_user_id = document.getElementById('reset-user-id').value;
            const new_password = document.getElementById('reset-new-password').value;

            if (!target_user_id || !new_password) {
                showToast('សូមបញ្ចូលពាក្យសម្ងាត់ថ្មី!', 'error');
                return;
            }

            try {
                const response = await fetch(`${API_BASE_URL}?action=reset_password`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ target_user_id, new_password })
                });

                const result = await response.json();
                if (result.status === 'success') {
                    showToast(result.message || '🎉 ធ្វើបច្ចុប្បន្នភាពពាក្យសម្ងាត់ជោគជ័យ!', 'success');
                    closeResetPasswordModal();
                } else {
                    showToast(result.message || 'បរាជ័យក្នុងការផ្លាស់ប្តូរពាក្យសម្ងាត់!', 'error');
                }
            } catch(err) {
                console.error('Error resetting password:', err);
                showToast('មានបញ្ហាបច្ចេកទេសក្នុងការ Reset ពាក្យសម្ងាត់!', 'error');
            }
        });
    }
}

function openCreateUserModal() {
    const modal = document.getElementById('create-user-modal');
    if (modal) modal.classList.add('active');
}

function closeCreateUserModal() {
    const modal = document.getElementById('create-user-modal');
    if (modal) modal.classList.remove('active');
}

function openManageUsersModal() {
    const modal = document.getElementById('manage-users-modal');
    if (modal) {
        modal.classList.add('active');
        loadUsersTable();
    }
}

function closeManageUsersModal() {
    const modal = document.getElementById('manage-users-modal');
    if (modal) modal.classList.remove('active');
}

async function loadUsersTable() {
    const tbody = document.getElementById('users-table-body');
    if (!tbody) return;

    tbody.innerHTML = `<tr><td colspan="5" style="text-align: center; padding: 20px; color: #6b7280;">កំពុងទាញយកបញ្ជីអ្នកប្រើប្រាស់...</td></tr>`;

    try {
        const response = await fetch(`${API_BASE_URL}?action=users`);
        const result = await response.json();

        if (result.status === 'success' && Array.isArray(result.data)) {
            tbody.innerHTML = result.data.map(u => `
                <tr>
                    <td style="padding: 12px; font-weight: bold;">#${u.id}</td>
                    <td style="padding: 12px; font-weight: 700;">👤 ${u.username}</td>
                    <td style="padding: 12px;"><span style="background: #e0e7ff; color: #3730a3; padding: 3px 10px; border-radius: 12px; font-size: 0.8rem; font-weight: 700; text-transform: uppercase;">${u.role}</span></td>
                    <td style="padding: 12px;"><span style="color: #10b981; font-weight: bold;">● Active</span></td>
                    <td style="padding: 12px; text-align: center;">
                        <div style="display: flex; gap: 6px; justify-content: center;">
                            <button class="btn" style="padding: 4px 10px; font-size: 0.8rem; background: #2563eb; color: white; border: none; border-radius: 4px; cursor: pointer;" onclick="openResetPasswordModal(${u.id}, '${encodeURIComponent(u.username)}')">🔑 Reset PW</button>
                            <button class="btn" style="padding: 4px 10px; font-size: 0.8rem; background: #ef4444; color: white; border: none; border-radius: 4px; cursor: pointer;" onclick="deleteUserAccount(${u.id}, '${encodeURIComponent(u.username)}')">🗑️ លុប</button>
                        </div>
                    </td>
                </tr>
            `).join('');
        } else {
            tbody.innerHTML = `<tr><td colspan="5" style="text-align: center; padding: 20px; color: #ef4444;">មិនអាចទាញយកបញ្ជីអ្នកប្រើប្រាស់បានឡើយ!</td></tr>`;
        }
    } catch(err) {
        console.error('Error loading users:', err);
        tbody.innerHTML = `<tr><td colspan="5" style="text-align: center; padding: 20px; color: #ef4444;">មានបញ្ហាបច្ចេកទេសក្នុងការទាញយកទិន្នន័យ!</td></tr>`;
    }
}

function openResetPasswordModal(id, encodedUsername) {
    const username = decodeURIComponent(encodedUsername);
    document.getElementById('reset-user-id').value = id;
    document.getElementById('reset-user-display').innerText = username;
    document.getElementById('reset-new-password').value = '';

    const modal = document.getElementById('reset-password-modal');
    if (modal) modal.classList.add('active');
}

function closeResetPasswordModal() {
    const modal = document.getElementById('reset-password-modal');
    if (modal) modal.classList.remove('active');
}

async function deleteUserAccount(id, encodedUsername) {
    const username = decodeURIComponent(encodedUsername);
    if (!confirm(`តើអ្នកពិតជាចង់លុបគណនី '${username}' នេះមែនទេ? (Are you sure you want to delete this user?)`)) {
        return;
    }

    try {
        const response = await fetch(`${API_BASE_URL}?action=delete_user`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ target_user_id: id })
        });

        const result = await response.json();
        if (result.status === 'success') {
            showToast(result.message || '🗑️ លុបគណនីជោគជ័យ!', 'success');
            loadUsersTable();
        } else {
            showToast(result.message || 'បរាជ័យក្នុងការលុប!', 'error');
        }
    } catch(err) {
        console.error('Delete user error:', err);
        showToast('មានបញ្ហាបច្ចេកទេសក្នុងការលុបគណនី!', 'error');
    }
}

/**
 * Toggle Action Menu for 3-Dots Button
 */
function toggleActionMenu(event, id) {
    event.stopPropagation();
    document.querySelectorAll('.action-menu.show').forEach(menu => {
        if (menu.id !== `action-menu-${id}`) {
            menu.classList.remove('show');
        }
    });

    const menu = document.getElementById(`action-menu-${id}`);
    if (menu) {
        menu.classList.toggle('show');
    }
}

function triggerUpdate(id, encodedTitle, amount, currency, type, encodedCategory, date) {
    document.querySelectorAll('.action-menu.show').forEach(menu => menu.classList.remove('show'));
    const title = decodeURIComponent(encodedTitle);
    const category = decodeURIComponent(encodedCategory);
    openEditTransactionModal(id, title, amount, currency, type, category, date);
}

function triggerDelete(id) {
    document.querySelectorAll('.action-menu.show').forEach(menu => menu.classList.remove('show'));
    softDeleteTransaction(id);
}

/**
 * Load Dashboard Metrics, Unified Totals, Budget Alerts & Charts
 */
async function loadDashboardMetricsAndCharts() {
    try {
        const response = await fetch(`${API_TRANSACTIONS_URL}?action=list_all`, {
            method: 'GET',
            headers: { 'Content-Type': 'application/json' }
        });

        if (!response.ok) return;

        const result = await response.json();
        if (result.status === 'success' && Array.isArray(result.data)) {
            const transactions = result.data.filter(t => !t.is_deleted);
            
            calculateMetricsAndUnifiedBalance(transactions);
            checkCategoryBudgetAlerts(transactions);
            renderAnalyticsCharts(transactions);
        }
    } catch (err) {
        console.error("Failed to load dashboard metrics and charts:", err);
    }
}

function calculateMetricsAndUnifiedBalance(transactions) {
    let incUsd = 0, incKhr = 0;
    let expUsd = 0, expKhr = 0;

    transactions.forEach(t => {
        const amt = parseFloat(t.raw_amount || t.amount) || 0;
        const type = (t.raw_type || t.type || '').toLowerCase();
        const curr = (t.raw_currency || t.currency || '').toUpperCase();

        if (type === 'income') {
            if (curr === 'USD') incUsd += amt;
            else incKhr += amt;
        } else if (type === 'expense') {
            if (curr === 'USD') expUsd += amt;
            else expKhr += amt;
        }
    });

    const balUsd = incUsd - expUsd;
    const balKhr = incKhr - expKhr;

    updateMetricElement('total-balance-khr', `${Math.round(balKhr).toLocaleString()} ៛`);
    updateMetricElement('total-balance-usd', `$${balUsd.toFixed(2)}`);
    updateMetricElement('total-income-khr', `${Math.round(incKhr).toLocaleString()} ៛`);
    updateMetricElement('total-income-usd', `$${incUsd.toFixed(2)}`);
    updateMetricElement('total-expense-khr', `${Math.round(expKhr).toLocaleString()} ៛`);
    updateMetricElement('total-expense-usd', `$${expUsd.toFixed(2)}`);

    const unifiedNetUsd = balUsd + (balKhr / currentUsdKhrRate);
    const unifiedNetKhr = balKhr + (balUsd * currentUsdKhrRate);

    updateMetricElement('unified-total-usd', `$${unifiedNetUsd.toFixed(2)}`);
    updateMetricElement('unified-total-khr', `${Math.round(unifiedNetKhr).toLocaleString()} ៛`);
}

function updateMetricElement(id, text) {
    const el = document.getElementById(id);
    if (el) el.innerText = text;
}

function checkCategoryBudgetAlerts(transactions) {
    const container = document.getElementById('budget-alerts-container');
    if (!container) return;

    container.innerHTML = '';

    // Check Daily Spending Limit Warning Banner for Dashboard Header
    const limitSettings = getDailySpendingLimit();
    if (limitSettings.enabled && limitSettings.usd > 0) {
        const todayStr = new Date().toISOString().split('T')[0];
        let todaySpentUSD = 0;
        transactions.forEach(t => {
            if ((t.raw_type || t.type) === 'expense') {
                const txDate = (t.raw_date || t.date || '').split(' ')[0];
                if (txDate === todayStr) {
                    const amt = parseFloat(t.raw_amount || t.amount) || 0;
                    const curr = (t.raw_currency || t.currency || '').toUpperCase();
                    todaySpentUSD += (curr === 'USD') ? amt : (amt / currentUsdKhrRate);
                }
            }
        });

        if (todaySpentUSD >= limitSettings.usd) {
            const limitCard = document.createElement('div');
            limitCard.className = 'budget-alert-card exceeded';
            limitCard.innerHTML = `
                <div style="flex: 1;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span>🚨 លើសកម្រិតចំណាយប្រចាំថ្ងៃកំណត់ ($${todaySpentUSD.toFixed(2)} / $${limitSettings.usd.toFixed(2)})</span>
                        <span><strong>100%+</strong></span>
                    </div>
                    <div class="progress-bar-bg">
                        <div class="progress-bar-fill exceeded" style="width: 100%;"></div>
                    </div>
                </div>
            `;
            container.appendChild(limitCard);
        }
    }

    const categoryTotalsUSD = {};

    transactions.forEach(t => {
        const type = (t.raw_type || t.type || '').toLowerCase();
        if (type === 'expense') {
            const cat = (t.category || '').trim();
            const amt = parseFloat(t.raw_amount || t.amount) || 0;
            const curr = (t.raw_currency || t.currency || '').toUpperCase();
            const amtUsd = (curr === 'USD') ? amt : (amt / currentUsdKhrRate);

            categoryTotalsUSD[cat] = (categoryTotalsUSD[cat] || 0) + amtUsd;
        }
    });

    for (const [cat, limit] of Object.entries(categoryBudgets)) {
        const spent = categoryTotalsUSD[cat] || 0;
        const percentage = (spent / limit) * 100;

        if (percentage >= 80) {
            const isExceeded = percentage >= 100;
            const alertCard = document.createElement('div');
            alertCard.className = `budget-alert-card ${isExceeded ? 'exceeded' : ''}`;

            alertCard.innerHTML = `
                <div style="flex: 1;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span>${isExceeded ? '🚨 លើសកម្រិតថវិកា' : '⚠️ ជិតដល់កម្រិតថវិកា'} (${cat}): <strong>$${spent.toFixed(2)}</strong> / $${limit.toFixed(2)}</span>
                        <span><strong>${percentage.toFixed(0)}%</strong></span>
                    </div>
                    <div class="progress-bar-bg">
                        <div class="progress-bar-fill ${isExceeded ? 'exceeded' : ''}" style="width: ${Math.min(percentage, 100)}%;"></div>
                    </div>
                </div>
            `;
            container.appendChild(alertCard);
        }
    }
}

/**
 * Render Analytics Charts (Chart.js)
 */
function renderAnalyticsCharts(transactions) {
    if (typeof Chart === 'undefined') return;

    renderIncomeVsExpenseChart(transactions);
    renderCategoryDoughnutChart(transactions);
}

function renderIncomeVsExpenseChart(transactions) {
    const canvas = document.getElementById('chart-income-expense');
    if (!canvas) return;

    let incUsd = 0, expUsd = 0;

    transactions.forEach(t => {
        const amt = parseFloat(t.raw_amount || t.amount) || 0;
        const type = (t.raw_type || t.type || '').toLowerCase();
        const curr = (t.raw_currency || t.currency || '').toUpperCase();
        const amtUsd = (curr === 'USD') ? amt : (amt / currentUsdKhrRate);

        if (type === 'income') incUsd += amtUsd;
        else if (type === 'expense') expUsd += amtUsd;
    });

    if (incomeExpenseChart) incomeExpenseChart.destroy();

    const isDarkMode = document.body.classList.contains('dark-mode');
    const textColor = isDarkMode ? '#f8fafc' : '#1e293b';
    const isMobile = window.innerWidth < 600;

    const ctx = canvas.getContext('2d');
    incomeExpenseChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['ចំណូល (Income)', 'ចំណាយ (Expense)'],
            datasets: [{
                label: 'ប្រាក់ដុល្លារ ($ USD)',
                data: [incUsd.toFixed(2), expUsd.toFixed(2)],
                backgroundColor: ['#10b981', '#ef4444'],
                borderRadius: { topLeft: 10, topRight: 10, bottomLeft: 0, bottomRight: 0 },
                maxBarThickness: isMobile ? 36 : 52
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    enabled: true,
                    backgroundColor: isDarkMode ? '#1e293b' : '#0f172a',
                    titleColor: '#ffffff',
                    bodyColor: '#ffffff',
                    padding: 10,
                    cornerRadius: 8,
                    titleFont: { family: "'Kantumruy Pro', sans-serif", size: 13, weight: 'bold' },
                    bodyFont: { family: "'Kantumruy Pro', sans-serif", size: 12 },
                    callbacks: {
                        label: function(context) {
                            const valUsd = parseFloat(context.raw) || 0;
                            const valKhr = Math.round(valUsd * currentUsdKhrRate);
                            return ` ចំនួន៖ $${valUsd.toFixed(2)} (${valKhr.toLocaleString()} ៛)`;
                        }
                    }
                }
            },
            scales: {
                x: { ticks: { color: textColor, font: { family: "'Kantumruy Pro', sans-serif" } } },
                y: {
                    beginAtZero: true,
                    ticks: { color: textColor, callback: function(v) { return '$' + v; } }
                }
            }
        }
    });
}

function renderCategoryDoughnutChart(transactions) {
    const canvas = document.getElementById('chart-category-doughnut');
    if (!canvas) return;

    const catTotals = {};
    let totalExpenseUSD = 0;

    transactions.forEach(t => {
        const type = (t.raw_type || t.type || '').toLowerCase();
        if (type === 'expense') {
            const cat = (t.category || '').trim() || 'ផ្សេងៗ';
            const amt = parseFloat(t.raw_amount || t.amount) || 0;
            const curr = (t.raw_currency || t.currency || '').toUpperCase();
            const amtUsd = (curr === 'USD') ? amt : (amt / currentUsdKhrRate);

            catTotals[cat] = (catTotals[cat] || 0) + amtUsd;
            totalExpenseUSD += amtUsd;
        }
    });

    const labels = Object.keys(catTotals);
    let dataValues = Object.values(catTotals).map(v => v.toFixed(2));
    let colors = ['#ef4444', '#f59e0b', '#3b82f6', '#10b981', '#8b5cf6', '#ec4899', '#06b6d4', '#84cc16', '#64748b'];

    if (categoryDoughnutChart) categoryDoughnutChart.destroy();

    const isDarkMode = document.body.classList.contains('dark-mode');
    const textColor = isDarkMode ? '#f8fafc' : '#1e293b';
    const isMobile = window.innerWidth < 600;

    // Custom Center Text Overlay Plugin
    const centerTextPlugin = {
        id: 'centerText',
        beforeDraw: function(chart) {
            if (!chart.chartArea) return;
            const { ctx } = chart;
            ctx.save();
            const fontSize = isMobile ? 13 : 15;
            ctx.font = `bold ${fontSize}px 'Kantumruy Pro', sans-serif`;
            ctx.textBaseline = 'middle';
            ctx.textAlign = 'center';
            ctx.fillStyle = textColor;

            const totalText = totalExpenseUSD > 0 ? `$${totalExpenseUSD.toFixed(2)}` : '$0.00';
            const labelText = 'ចំណាយសរុប';

            const centerX = (chart.chartArea.left + chart.chartArea.right) / 2;
            const centerY = (chart.chartArea.top + chart.chartArea.bottom) / 2;

            ctx.font = `bold ${fontSize + 2}px 'Kantumruy Pro', sans-serif`;
            ctx.fillText(totalText, centerX, centerY - 6);

            ctx.font = `500 ${fontSize - 2}px 'Kantumruy Pro', sans-serif`;
            ctx.fillStyle = isDarkMode ? '#94a3b8' : '#64748b';
            ctx.fillText(labelText, centerX, centerY + 14);

            ctx.restore();
        }
    };

    if (labels.length === 0 || totalExpenseUSD === 0) {
        const ctx = canvas.getContext('2d');
        categoryDoughnutChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['គ្មានទិន្នន័យចំណាយ'],
                datasets: [{
                    data: [1],
                    backgroundColor: [isDarkMode ? '#1e293b' : '#e2e8f0'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: textColor,
                            usePointStyle: true,
                            pointStyle: 'circle',
                            font: { family: "'Kantumruy Pro', sans-serif", size: 11 }
                        }
                    },
                    tooltip: { enabled: false }
                }
            },
            plugins: [centerTextPlugin]
        });
        return;
    }

    const ctx = canvas.getContext('2d');
    categoryDoughnutChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: dataValues,
                backgroundColor: colors.slice(0, labels.length),
                borderWidth: 2,
                borderColor: isDarkMode ? '#111827' : '#ffffff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        color: textColor,
                        usePointStyle: true,
                        pointStyle: 'circle',
                        padding: isMobile ? 10 : 15,
                        font: { family: "'Kantumruy Pro', sans-serif", size: isMobile ? 11 : 12 }
                    }
                },
                tooltip: {
                    enabled: true,
                    backgroundColor: isDarkMode ? '#1e293b' : '#0f172a',
                    titleColor: '#ffffff',
                    bodyColor: '#ffffff',
                    padding: 10,
                    cornerRadius: 8,
                    titleFont: { family: "'Kantumruy Pro', sans-serif", size: 13, weight: 'bold' },
                    bodyFont: { family: "'Kantumruy Pro', sans-serif", size: 12 },
                    callbacks: {
                        label: function(context) {
                            const valUsd = parseFloat(context.raw) || 0;
                            const pct = totalExpenseUSD > 0 ? ((valUsd / totalExpenseUSD) * 100).toFixed(1) : 0;
                            return ` ${context.label}: $${valUsd.toFixed(2)} (${pct}%)`;
                        }
                    }
                }
            }
        },
        plugins: [centerTextPlugin]
    });
}

/**
 * Load Transactions Table
 */
async function loadTransactionsTable(page = 1) {
    const tbody = document.getElementById('transaction-table-body');
    if (!tbody) return;

    let url = `${API_TRANSACTIONS_URL}?page=${page}`;

    const fromDate = document.getElementById('filter-from-date')?.value;
    const toDate = document.getElementById('filter-to-date')?.value;

    if (fromDate && toDate) {
        url += `&from_date=${fromDate}&to_date=${toDate}`;
    }

    try {
        const response = await fetch(url);
        const result = await response.json();

        if (result.status === 'success' && Array.isArray(result.data)) {
            renderTableRows(result.data);
            renderPaginationControls(result.pagination);
        } else {
            tbody.innerHTML = `<tr><td colspan="7" style="text-align: center; padding: 20px; color: #ef4444;">មិនមានទិន្នន័យប្រតិបត្តិការឡើយ។</td></tr>`;
        }
    } catch (err) {
        console.error("Error loading transactions:", err);
    }
}

function renderTableRows(data) {
    const tbody = document.getElementById('transaction-table-body');
    if (!tbody) return;

    if (data.length === 0) {
        tbody.innerHTML = `<tr><td colspan="7" style="text-align: center; padding: 20px; color: #6b7280;">មិនមានប្រតិបត្តិការនៅក្នុងចន្លោះកាលបរិច្ឆេទនេះឡើយ។</td></tr>`;
        return;
    }

    tbody.innerHTML = data.map(row => {
        const typeStr = (row.raw_type || row.type || '').toLowerCase();
        const typeColor = typeStr === 'income' ? '#10b981' : '#ef4444';
        const typeText = typeStr === 'income' ? 'ចំណូល' : 'ចំណាយ';
        const receiptBadge = row.receipt_image ? `<a href="${row.receipt_image}" target="_blank" style="color: #2563eb; font-size: 0.85rem; text-decoration: underline;">🧾 មើលវិក្កយបត្រ</a>` : `<span style="color: #9ca3af; font-size: 0.85rem;">គ្មាន</span>`;

        const escapedTitle = encodeURIComponent(row.description || '');
        const escapedCategory = encodeURIComponent(row.category || '');
        const rawAmt = row.raw_amount || parseFloat(row.amount) || 0;
        const rawCurr = row.raw_currency || (row.amount.includes('$') ? 'USD' : 'KHR');
        const rawDate = row.raw_date || row.date;

        return `
            <tr data-type="${typeStr}">
                <td data-label="កាលបរិច្ឆេទ">${row.date}</td>
                <td data-label="បរិយាយ"><strong>${row.description}</strong></td>
                <td data-label="អ្នកបន្ថែម">${row.creator || 'User'}</td>
                <td data-label="ប្រភេទ"><span style="color: ${typeColor}; font-weight: bold;">${typeText} (${row.category})</span></td>
                <td data-label="ចំនួនទឹកប្រាក់" style="font-weight: bold; color: ${typeColor};">${row.amount}</td>
                <td data-label="វិក្កយបត្រ">${receiptBadge}</td>
                <td data-label="សកម្មភាព" style="text-align: center;">
                    <div class="action-dropdown">
                        <button class="action-dots-btn" aria-label="More actions" onclick="toggleActionMenu(event, ${row.id})">⋮</button>
                        <div class="action-menu" id="action-menu-${row.id}">
                            <button class="action-menu-item update-btn" onclick="triggerUpdate(${row.id}, '${escapedTitle}', ${rawAmt}, '${rawCurr}', '${typeStr}', '${escapedCategory}', '${rawDate}')">
                                ✏️ កែប្រែ (Update)
                            </button>
                            <button class="action-menu-item delete-btn" onclick="triggerDelete(${row.id})">
                                🗑️ លុប (Delete)
                            </button>
                        </div>
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

function renderPaginationControls(pagination) {
    const container = document.getElementById('pagination-controls');
    if (!container || !pagination) return;

    container.innerHTML = `
        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #e2e8f0; font-size: 0.88rem;">
            <span>ទំព័រទី <strong>${pagination.current_page}</strong> នៃ <strong>${pagination.total_pages}</strong> (សរុប ${pagination.total_records} ប្រតិបត្តិការ)</span>
            <div style="display: flex; gap: 6px;">
                <button class="btn btn-secondary" style="padding: 4px 12px; font-size: 0.85rem;" ${pagination.current_page <= 1 ? 'disabled' : ''} onclick="loadTransactionsTable(${pagination.current_page - 1})">← មុន</button>
                <button class="btn btn-secondary" style="padding: 4px 12px; font-size: 0.85rem;" ${pagination.current_page >= pagination.total_pages ? 'disabled' : ''} onclick="loadTransactionsTable(${pagination.current_page + 1})">បន្ទាប់ →</button>
            </div>
        </div>
    `;
}

/**
 * Soft Delete Transaction
 */
async function softDeleteTransaction(id) {
    if (!confirm('តើអ្នកពិតជាចង់លុបប្រតិបត្តិការនេះមែនទេ? (Are you sure you want to delete this transaction?)')) {
        return;
    }

    try {
        const response = await fetch(API_TRANSACTIONS_URL, {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ transaction_id: id })
        });

        const result = await response.json();
        if (result.status === 'success') {
            showToast(result.message || '🗑️ ប្រតិបត្តិការត្រូវបានលុបជោគជ័យ!', 'success');
            loadTransactionsTable(1);
            loadDashboardMetricsAndCharts();
        } else {
            showToast(result.message || 'បរាជ័យក្នុងការលុប!', 'error');
        }
    } catch (err) {
        console.error('Delete error:', err);
        showToast('មានបញ្ហាបច្ចេកទេសក្នុងការលុប!', 'error');
    }
}

/**
 * Open Audit Log Viewer Modal
 */
async function openAuditLogModal() {
    const modal = document.getElementById('audit-log-modal');
    const tbody = document.getElementById('audit-log-table-body');
    if (!modal || !tbody) return;

    modal.classList.add('active');
    tbody.innerHTML = `<tr><td colspan="5" style="text-align: center; padding: 20px; color: #6b7280;">កំពុងទាញយក Audit Logs...</td></tr>`;

    try {
        const response = await fetch(`${API_BASE_URL}?action=get_audit_logs`);
        const result = await response.json();

        if (result.status === 'success' && Array.isArray(result.data)) {
            tbody.innerHTML = result.data.map(log => `
                <tr>
                    <td style="padding: 10px; font-size: 0.85rem;">${log.created_at || log.timestamp || '-'}</td>
                    <td style="padding: 10px; font-weight: 600;">👤 ${log.username || log.operator || 'System'}</td>
                    <td style="padding: 10px;"><span style="background: #e0f2fe; color: #0369a1; padding: 2px 8px; border-radius: 4px; font-size: 0.8rem; font-weight: bold;">${log.action}</span></td>
                    <td style="padding: 10px; font-size: 0.85rem; color: #4b5563;">${log.details || '-'}</td>
                    <td style="padding: 10px; font-size: 0.85rem; color: #6b7280;">${log.ip_address || log.ip || '-'}</td>
                </tr>
            `).join('');
        } else {
            tbody.innerHTML = `<tr><td colspan="5" style="text-align: center; padding: 20px; color: #ef4444;">មិនអាចទាញយក Audit Logs បានឡើយ។</td></tr>`;
        }
    } catch (err) {
        console.error("Failed to load audit logs:", err);
        tbody.innerHTML = `<tr><td colspan="5" style="text-align: center; padding: 20px; color: #ef4444;">មានបញ្ហាបច្ចេកទេសក្នុងការទាញយក Audit Logs!</td></tr>`;
    }
}

function closeAuditLogModal() {
    const modal = document.getElementById('audit-log-modal');
    if (modal) modal.classList.remove('active');
}

/**
 * Show Custom Toast Notification
 */
function showToast(message, type = 'success') {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999; display: flex; flex-direction: column; gap: 10px;';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.style.cssText = `min-width: 280px; padding: 12px 18px; border-radius: 8px; color: #fff; font-family: 'Kantumruy Pro', sans-serif; font-size: 0.9rem; font-weight: 600; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); background-color: ${type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : '#2563eb'}; transition: all 0.3s; transform: translateX(120%);`;

    toast.innerHTML = `<span>${type === 'success' ? '🎉' : type === 'error' ? '❌' : 'ℹ️'} ${message}</span>`;
    container.appendChild(toast);

    setTimeout(() => toast.style.transform = 'translateX(0)', 50);
    setTimeout(() => {
        toast.style.transform = 'translateX(120%)';
        setTimeout(() => toast.remove(), 300);
    }, 4000);
}
