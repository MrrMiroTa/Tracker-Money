/**
 * admin-integration.js - Production B2B Fintech SaaS JavaScript Engine (v26.0)
 * Part of the Khmer Payment Tracker and Financial Management System
 */

const API_BASE_URL = 'api-v2.php';
const API_TRANSACTIONS_URL = 'api-transactions.php';

// Global Chart Instances
let incomeExpenseChart = null;
const KHMER_MONTHS = ['មករា', 'កុម្ភៈ', 'មីនា', 'មេសា', 'ឧសភា', 'មិថុនា', 'កក្កដា', 'សីហា', 'កញ្ញា', 'តុលា', 'វិច្ឆិកា', 'ធ្នូ'];
function currentMonthStr() { const n = new Date(); return n.getFullYear() + '-' + String(n.getMonth() + 1).padStart(2, '0'); }
// Default = THIS month. A link like index.php?month=2026-09 opens that month instead.
let dashboardMonth = (function () { const m = new URLSearchParams(location.search).get('month'); return /^\d{4}-(0[1-9]|1[0-2])$/.test(m || '') ? m : currentMonthStr(); })();
function decodeEntities(s) { const t = document.createElement('textarea'); t.innerHTML = String(s == null ? '' : s); return t.value; } // old rows were HTML-encoded on save

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
    initThemeState();
    initApp();
});

/**
 * Initialize Theme Preference from LocalStorage
 */
function initThemeState() {
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'dark') {
        document.body.classList.add('dark-mode');
        updateToggleButton(true);
    }
}

/**
 * Toggle Dark / Light Theme Mode
 */
function toggleTheme() {
    const isDark = document.body.classList.toggle('dark-mode');
    localStorage.setItem('theme', isDark ? 'dark' : 'light');
    updateToggleButton(isDark);
    
    // Re-render charts for theme contrast
    if (typeof loadDashboardMetricsAndCharts === 'function') {
        loadDashboardMetricsAndCharts();
    }
    
    showToast(isDark ? '🌙 បានផ្លាស់ប្តូរទៅជា Dark Mode' : '☀️ បានផ្លាស់ប្តូរទៅជា Light Mode', 'info');
}

function updateToggleButton(isDark) {
    const btn = document.getElementById('dark-mode-toggle');
    if (btn) {
        btn.innerHTML = isDark ? '☀️ Light Mode' : '🌙 Dark Mode';
    }
}

/**
 * Logout User Helper
 */
async function logoutUser() {
    try {
        await fetch(`${API_BASE_URL}?action=logout`, { method: 'POST' });
    } catch (e) {
        console.error('Logout error:', e);
    } finally {
        localStorage.removeItem('current_user');
        window.location.href = 'login.php';
    }
}

/**
 * Initialize Application Engine
 */
function initApp() {
    setupNavigation();
    setupExchangeRateWidget();
    setupDateFilterListeners();
    setupTransactionForm();
    setupEditTransactionForm();
    setupAdminControlButtons();

    if (document.getElementById('chart-income-expense')) {
        loadDashboardMetricsAndCharts();
    }
    if (document.getElementById('transaction-table-body')) {
        loadTransactionsTable(1);
    }
    if (document.getElementById('archive-history-tbody') || document.getElementById('archive-table-body') || document.getElementById('audit-history-tbody')) {
        loadArchiveHistoryTable(1);
    }
}

/**
 * Responsive Navigation Toggle
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
                if (document.getElementById('chart-income-expense')) {
                    loadDashboardMetricsAndCharts();
                }
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
    const monthInput = document.getElementById('filter-month');
    const categoryInput = document.getElementById('filter-category');
    const operationInput = document.getElementById('filter-operation');

    [fromDateInput, toDateInput, monthInput, categoryInput, operationInput]
        .filter(Boolean)
        .forEach(input => input.addEventListener('change', () => loadTransactionsTable(1)));
}

function resetTransactionFilters() {
    ['filter-month', 'filter-category', 'filter-operation', 'filter-from-date', 'filter-to-date']
        .forEach(id => {
            const input = document.getElementById(id);
            if (input) input.value = '';
        });
    loadTransactionsTable(1);
}

/**
 * Get Daily Spending Limit Settings from LocalStorage
 */
function getDailySpendingLimit() {
    const saved = localStorage.getItem('daily_spending_limit');
    if (saved) {
        try {
            return JSON.parse(saved);
        } catch(e) {}
    }
    return { enabled: true, usd: 5.00, khr: 20000 };
}

/**
 * Check Daily Spending Limit Warning
 */
async function checkDailySpendingLimitWarning(newExpenseUSD) {
    const limitSettings = getDailySpendingLimit();
    if (!limitSettings || !limitSettings.enabled) return true;

    const limitUSD = limitSettings.usd || 5.00;
    const limitKHR = limitSettings.khr || (limitUSD * currentUsdKhrRate);

    try {
        const response = await fetch(`${API_TRANSACTIONS_URL}?action=list_all`);
        const result = await response.json();

        if (result.status === 'success' && Array.isArray(result.data)) {
            const todayStr = new Date().toISOString().slice(0, 10);
            let todayExpenseUSD = 0;

            result.data.forEach(t => {
                if (!t.is_deleted) {
                    const type = (t.raw_type || t.type || '').toLowerCase();
                    const dateStr = (t.date || '').slice(0, 10);

                    if (type === 'expense' && dateStr === todayStr) {
                        const amt = parseFloat(t.raw_amount || t.amount) || 0;
                        const curr = (t.raw_currency || t.currency || '').toUpperCase();
                        const amtUsd = (curr === 'USD') ? amt : (amt / currentUsdKhrRate);
                        todayExpenseUSD += amtUsd;
                    }
                }
            });

            const totalProjectedUSD = todayExpenseUSD + newExpenseUSD;

            if (totalProjectedUSD > limitUSD) {
                const totalProjectedKHR = Math.round(totalProjectedUSD * currentUsdKhrRate);
                const msg = `🚨 ព្រមាន៖ ការចំណាយប្រចាំថ្ងៃរបស់អ្នកនឹងលើសពីកម្រិតកំណត់!\n\n` +
                            `• កម្រិតកំណត់ប្រចាំថ្ងៃ៖ $${limitUSD.toFixed(2)} (${Math.round(limitKHR).toLocaleString()} ៛) / មួយថ្ងៃ\n` +
                            `• ការចំណាយសរុបប្រចាំថ្ងៃនឹងកើនដល់៖ $${totalProjectedUSD.toFixed(2)} (${totalProjectedKHR.toLocaleString()} ៛)\n\n` +
                            `តើអ្នកពិតជាចង់រក្សាទុកប្រតិបត្តិការចំណាយនេះដែរឬទេ?`;
                
                return confirm(msg);
            }
        }
    } catch(err) {
        console.error('Error checking daily limit:', err);
    }

    return true;
}

/**
 * Setup Transaction Form Handler
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

        const titleInput = document.getElementById('title');
        const amountInput = document.getElementById('amount');
        const currencyInput = document.getElementById('currency');
        const typeInput = document.getElementById('type');
        const categoryInput = document.getElementById('category');
        const receiptInput = document.getElementById('receipt');

        const title = titleInput.value.trim();
        const amount = parseFloat(amountInput.value);
        const currency = currencyInput.value;
        const type = typeInput.value;
        const category = categoryInput.value.trim();
        const date = dateInput ? dateInput.value : '';

        if (!title || isNaN(amount) || amount <= 0 || !currency || !type || !category) {
            showToast('សូមបំពេញព័ត៌មានឱ្យបានត្រឹមត្រូវ និងគ្រប់គ្រាន់ (ទឹកប្រាក់ត្រូវតែធំជាង ០)!', 'error');
            return;
        }

        if (type === 'expense') {
            const newAmtUSD = (currency === 'USD') ? amount : (amount / currentUsdKhrRate);
            const proceed = await checkDailySpendingLimitWarning(newAmtUSD);
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
 * Setup Edit Transaction Form
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

        if (type === 'expense') {
            const newAmtUSD = (currency === 'USD') ? amount : (amount / currentUsdKhrRate);
            const proceed = await checkDailySpendingLimitWarning(newAmtUSD);
            if (!proceed) return;
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
 * Setup Admin Control Buttons
 */
function setupAdminControlButtons() {
    const btnCreate = document.getElementById('toggle-create-user-btn');
    if (btnCreate) {
        btnCreate.addEventListener('click', openCreateUserModal);
    }

    const btnManage = document.getElementById('toggle-manage-users-btn');
    if (btnManage) {
        btnManage.addEventListener('click', openManageUsersModal);
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

/**
 * Load Users List Table for Admin Management
 */
async function loadUsersTable() {
    const tbody = document.getElementById('manage-users-table-body');
    if (!tbody) return;

    tbody.innerHTML = `<tr><td colspan="5" style="text-align: center; padding: 20px; color: #6b7280;">កំពុងទាញយកបញ្ជីអ្នកប្រើប្រាស់...</td></tr>`;

    try {
        const response = await fetch(`${API_BASE_URL}?action=users`);
        const result = await response.json();

        if (result.status === 'success' && Array.isArray(result.data)) {
            tbody.innerHTML = result.data.map(u => `
                <tr>
                    <td style="padding: 10px; font-weight: 700;">#${u.id}</td>
                    <td style="padding: 10px; font-weight: 600;">👤 ${u.username}</td>
                    <td style="padding: 10px;"><span class="role-badge-pill">${(u.role || 'user').toUpperCase()}</span></td>
                    <td style="padding: 10px;"><span style="color: #10b981; font-weight: bold;">● Active</span></td>
                    <td style="padding: 10px; text-align: center;">
                        <button class="btn btn-secondary" style="padding: 4px 8px; font-size: 0.8rem;" onclick="openResetPasswordModal(${u.id}, '${u.username}')">🔑 Reset PW</button>
                        <button class="btn btn-danger" style="padding: 4px 8px; font-size: 0.8rem;" onclick="deleteUserAccount(${u.id}, '${u.username}')">🗑️ លុប</button>
                    </td>
                </tr>
            `).join('');
        } else {
            tbody.innerHTML = `<tr><td colspan="5" style="text-align: center; padding: 20px; color: #ef4444;">មិនអាចទាញយកបញ្ជីអ្នកប្រើប្រាស់បានឡើយ។</td></tr>`;
        }
    } catch(err) {
        console.error('Error loading users:', err);
    }
}

function openResetPasswordModal(userId, username) {
    const modal = document.getElementById('reset-password-modal');
    if (modal) {
        document.getElementById('reset-user-id').value = userId;
        document.getElementById('reset-user-display').innerText = username;
        modal.classList.add('active');
    }
}

function closeResetPasswordModal() {
    const modal = document.getElementById('reset-password-modal');
    if (modal) modal.classList.remove('active');
}

async function deleteUserAccount(userId, username) {
    if (!confirm(`តើអ្នកពិតជាចង់លុបគណនីអ្នកប្រើប្រាស់ '${username}' នេះចេញពីប្រព័ន្ធមែនទេ?`)) return;

    try {
        const response = await fetch(`${API_BASE_URL}?action=delete_user`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ target_user_id: userId })
        });
        const result = await response.json();
        if (result.status === 'success') {
            showToast(result.message || '🎉 លុបគណនីជោគជ័យ!', 'success');
            loadUsersTable();
        } else {
            showToast(result.message || 'បរាជ័យក្នុងការលុបគណនី!', 'error');
        }
    } catch(err) {
        console.error('Delete user error:', err);
    }
}

/**
 * Toggle 3-Dots Action Dropdown Menu
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
    document.querySelectorAll('.action-menu.show').forEach(m => m.classList.remove('show'));
    const title = decodeURIComponent(encodedTitle);
    const category = decodeURIComponent(encodedCategory);
    openEditTransactionModal(id, title, amount, currency, type, category, date);
}

function triggerDelete(id) {
    document.querySelectorAll('.action-menu.show').forEach(m => m.classList.remove('show'));
    softDeleteTransaction(id);
}

/**
 * Load Dashboard Metrics, Balance & Charts
 */
async function loadDashboardMetricsAndCharts() {
    try {
        const response = await fetch(`${API_TRANSACTIONS_URL}?action=dashboard_summary&month=${encodeURIComponent(dashboardMonth)}`);
        if (!response.ok) return;
        const r = await response.json();
        if (r.status !== 'success') return;

        // Expense per category for the chosen month, everything converted to USD
        const spentByCategory = {};
        (r.categories || []).forEach(c => {
            const name = decodeEntities(c.category).trim() || 'ផ្សេងៗ';
            const usd = (c.currency === 'USD') ? c.total : c.total / currentUsdKhrRate;
            spentByCategory[name] = (spentByCategory[name] || 0) + usd;
        });

        calculateMetricsAndUnifiedBalance(r.totals);   // KPI cards stay ALL-TIME totals
        checkCategoryBudgetAlerts(spentByCategory);    // monthly budgets vs the chosen month
        renderIncomeVsExpenseChart(r.month_totals);
        renderCategoryList(spentByCategory);
        syncMonthControls(r.first_year);
    } catch (err) {
        console.error("Failed to load dashboard metrics and charts:", err);
    }
}

function calculateMetricsAndUnifiedBalance(totals) {
    const incUsd = totals.income_usd, incKhr = totals.income_khr;
    const expUsd = totals.expense_usd, expKhr = totals.expense_khr;

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

function checkCategoryBudgetAlerts(categoryTotalsUSD) {
    const container = document.getElementById('budget-alerts-container');
    if (!container) return;

    container.innerHTML = '';

    let hasAlerts = false;

    for (const [cat, limit] of Object.entries(categoryBudgets)) {
        const spent = categoryTotalsUSD[cat] || 0;
        const percentage = (spent / limit) * 100;

        if (percentage >= 80) {
            hasAlerts = true;
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

    if (!hasAlerts) {
        container.innerHTML = `<div style="font-size: 0.88rem; color: #10b981; font-weight: 600; padding: 4px 0;">✅ ការចំណាយតាមក្រុមទាំងអស់ស្ថិតក្នុងកម្រិតថវិកាស្របតាមផែនការ។</div>`;
    }
}

/**
 * Render Analytics Charts (Chart.js)
 */
function renderIncomeVsExpenseChart(monthTotals) {
    const canvas = document.getElementById('chart-income-expense');
    if (!canvas || typeof Chart === 'undefined') return;

    const incUsd = monthTotals.income_usd + monthTotals.income_khr / currentUsdKhrRate;
    const expUsd = monthTotals.expense_usd + monthTotals.expense_khr / currentUsdKhrRate;

    if (incomeExpenseChart) incomeExpenseChart.destroy();

    const isDarkMode = document.body.classList.contains('dark-mode');
    const textColor = isDarkMode ? '#f8fafc' : '#1e293b';

    const ctx = canvas.getContext('2d');
    incomeExpenseChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['ចំណូល (Income)', 'ចំណាយ (Expense)'],
            datasets: [{
                label: 'ប្រាក់ដុល្លារ ($ USD)',
                data: [incUsd.toFixed(2), expUsd.toFixed(2)],
                backgroundColor: ['#4b4bf2', '#ff7a3d'],
                borderRadius: { topLeft: 16, topRight: 16, bottomLeft: 0, bottomRight: 0 },
                maxBarThickness: 72
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { display: false }, ticks: { color: textColor } },
                y: {
                    beginAtZero: true,
                    ticks: { color: textColor, callback: function(v) { return '$' + v; } }
                }
            }
        }
    });
}

const CATEGORY_COLORS = ['#4b4bf2', '#ff7a3d', '#16a34a', '#ef4444', '#8b5cf6', '#06b6d4', '#f59e0b', '#ec4899', '#64748b'];

function mk(tag, cls, text) { const e = document.createElement(tag); if (cls) e.className = cls; if (text !== undefined) e.textContent = text; return e; }

/** Expense categories as a ranked list: name, amount, share %, bar. Built with textContent (no HTML injection). */
function renderCategoryList(totals) {
    const box = document.getElementById('category-list');
    if (!box) return;
    const rows = Object.entries(totals).sort((x, y) => y[1] - x[1]);
    const sum = rows.reduce((s, r) => s + r[1], 0);
    const totalEl = document.getElementById('category-total');
    if (totalEl) totalEl.textContent = '$' + sum.toFixed(2);
    box.replaceChildren();

    if (!rows.length) { box.appendChild(mk('div', 'cat-empty', 'មិនមានការចំណាយក្នុងខែនេះទេ។')); return; }

    rows.forEach(([name, value], i) => {
        const pct = sum > 0 ? (value / sum) * 100 : 0;
        const color = CATEGORY_COLORS[i % CATEGORY_COLORS.length];
        const row = mk('div', 'cat-row');

        const top = mk('div', 'cat-top');
        const left = mk('span', 'cat-name');
        const dot = mk('i', 'cat-dot'); dot.style.background = color;
        left.append(dot, mk('span', '', name));
        const right = mk('span', 'cat-amount');
        right.append(mk('strong', '', '$' + value.toFixed(2)), mk('small', '', pct.toFixed(0) + '%'));
        top.append(left, right);

        const bar = mk('div', 'cat-bar');
        const fill = mk('div', 'cat-fill'); fill.style.width = Math.max(pct, 2) + '%'; fill.style.background = color;
        bar.appendChild(fill);

        row.append(top, bar);
        box.appendChild(row);
    });
}

/** Month picker: ‹ [month] [year] › [this month]. Works in every browser (no <input type=month>). */
function syncMonthControls(firstYear) {
    const mSel = document.getElementById('mf-month'), ySel = document.getElementById('mf-year');
    if (!mSel || !ySel) return;
    const [y, m] = dashboardMonth.split('-').map(Number);
    const thisYear = new Date().getFullYear();
    const from = Math.min(firstYear || thisYear, y), to = Math.max(thisYear, y);
    if (ySel.options.length !== to - from + 1 || +ySel.options[0].value !== to) {
        ySel.replaceChildren();
        for (let yy = to; yy >= from; yy--) { const o = mk('option', '', String(yy)); o.value = yy; ySel.appendChild(o); }
    }
    if (!mSel.options.length) KHMER_MONTHS.forEach((n, i) => { const o = mk('option', '', n); o.value = i + 1; mSel.appendChild(o); });
    mSel.value = m; ySel.value = y;
    const label = 'ខែ' + KHMER_MONTHS[m - 1] + ' ' + y;
    document.querySelectorAll('.js-month-label').forEach(e => { e.textContent = label; });
    const isNow = dashboardMonth === currentMonthStr();
    const todayBtn = document.getElementById('mf-today'); if (todayBtn) todayBtn.disabled = isNow;
    const next = document.getElementById('mf-next'); if (next) next.disabled = isNow; // no future months
}

function setDashboardMonth(ym) {
    if (!/^\d{4}-(0[1-9]|1[0-2])$/.test(ym) || ym > currentMonthStr()) return;
    dashboardMonth = ym;
    loadDashboardMetricsAndCharts();
}
function shiftDashboardMonth(delta) {
    const [y, m] = dashboardMonth.split('-').map(Number);
    const d = new Date(y, m - 1 + delta, 1);
    setDashboardMonth(d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0'));
}
document.addEventListener('DOMContentLoaded', () => {
    syncMonthControls();
    const on = (id, fn) => { const e = document.getElementById(id); if (e) e.addEventListener('click', fn); };
    on('mf-prev', () => shiftDashboardMonth(-1));
    on('mf-next', () => shiftDashboardMonth(1));
    on('mf-today', () => setDashboardMonth(currentMonthStr()));
    const pick = () => setDashboardMonth(document.getElementById('mf-year').value + '-' + String(document.getElementById('mf-month').value).padStart(2, '0'));
    ['mf-month', 'mf-year'].forEach(id => { const e = document.getElementById(id); if (e) e.addEventListener('change', pick); });
});

/**
 * Load Transactions Table
 */
async function loadTransactionsTable(page = 1) {
    const tbody = document.getElementById('transaction-table-body');
    if (!tbody) return;

    const params = new URLSearchParams({ page: String(page) });
    const fromDate = document.getElementById('filter-from-date')?.value;
    const toDate = document.getElementById('filter-to-date')?.value;
    const month = document.getElementById('filter-month')?.value;
    const category = document.getElementById('filter-category')?.value;
    const operation = document.getElementById('filter-operation')?.value;

    if (fromDate && toDate) {
        params.set('from_date', fromDate);
        params.set('to_date', toDate);
    }
    if (month) params.set('month', month);
    if (category) params.set('category', category);
    if (operation) params.set('operation', operation);

    try {
        const response = await fetch(`${API_TRANSACTIONS_URL}?${params.toString()}`);
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
        const rawCurr = row.raw_currency || 'USD';
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
    if (!confirm('តើអ្នកពិតជាចង់លុបប្រតិបត្តិការនេះមែនទេ? (Are you sure you want to delete this transaction?)')) return;

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
    }
}

/**
 * Load Archive History Table for archive-history.php
 */
async function loadArchiveHistoryTable(page = 1) {
    const tbodys = [
        document.getElementById('archive-history-tbody'),
        document.getElementById('archive-table-body'),
        document.getElementById('audit-history-tbody')
    ].filter(Boolean);

    if (tbodys.length === 0) return;

    tbodys.forEach(tb => {
        tb.style.display = 'table-row-group';
        tb.innerHTML = `<tr><td colspan="7" style="text-align: center; padding: 25px; color: #6b7280; font-weight: 600;">កំពុងទាញយកទិន្នន័យប្រវត្តិសវនកម្ម...</td></tr>`;
    });

    try {
        const response = await fetch(`${API_TRANSACTIONS_URL}?action=get_archives`);
        const result = await response.json();

        if (result.status === 'success' && Array.isArray(result.data)) {
            renderArchiveRows(result.data);
            calculateArchiveMetrics(result.data);
        } else {
            tbodys.forEach(tb => {
                tb.innerHTML = `<tr><td colspan="7" style="text-align: center; padding: 25px; color: #ef4444;">មិនមានប្រវត្តិសវនកម្មឡើយ។</td></tr>`;
            });
        }
    } catch(err) {
        console.error('Error loading archive history:', err);
        tbodys.forEach(tb => {
            tb.innerHTML = `<tr><td colspan="7" style="text-align: center; padding: 25px; color: #ef4444;">មានបញ្ហាក្នុងការទាញយកទិន្នន័យពី Server!</td></tr>`;
        });
    }
}

function renderArchiveRows(data) {
    const tbodys = [
        document.getElementById('archive-history-tbody'),
        document.getElementById('archive-table-body'),
        document.getElementById('audit-history-tbody')
    ].filter(Boolean);

    if (tbodys.length === 0) return;

    if (data.length === 0) {
        tbodys.forEach(tb => {
            tb.innerHTML = `<tr><td colspan="7" style="text-align: center; padding: 25px; color: #6b7280;">មិនមានប្រវត្តិសវនកម្មឡើយ។</td></tr>`;
        });
        return;
    }

    const rowsHtml = data.map(row => {
        const actType = (row.action_type || 'INFO').toUpperCase();
        let badgeStyle = 'background: #e0f2fe; color: #0369a1;';
        if (actType === 'DELETE') badgeStyle = 'background: #fef2f2; color: #ef4444; border: 1px solid #fca5a5;';
        else if (actType === 'UPDATE') badgeStyle = 'background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe;';
        else if (actType === 'RESTORE') badgeStyle = 'background: #ecfdf5; color: #10b981; border: 1px solid #6ee7b7;';
        else if (actType === 'CREATE' || actType === 'ADD') badgeStyle = 'background: #f3e8ff; color: #8b5cf6; border: 1px solid #ddd6fe;';

        const isDeleted = (row.is_deleted === 1) || (actType === 'DELETE' && row.transaction_id > 0);
        const actionBtn = isDeleted ? `<button class="btn btn-secondary" style="padding: 4px 10px; font-size: 0.8rem; background: #10b981; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;" onclick="restoreTransaction(${row.transaction_id || row.id})">🔄 ស្តារឡើងវិញ</button>` : `<span style="color: #9ca3af; font-size: 0.85rem;">-</span>`;

        return `
            <tr>
                <td data-label="កាលបរិច្ឆេទសកម្មភាព" style="font-size: 0.88rem; font-weight: 600;">${row.action_date || row.created_at || '-'}</td>
                <td data-label="ម្ចាស់ទិន្នន័យ"><strong>👤 ${row.owner_name || 'User'}</strong></td>
                <td data-label="ប្រភេទសកម្មភាព"><span style="padding: 4px 10px; border-radius: 20px; font-size: 0.82rem; font-weight: 800; ${badgeStyle}">${actType}</span></td>
                <td data-label="ទិន្នន័យដើម" style="font-size: 0.88rem; color: #475569;">${row.original_value || '-'}</td>
                <td data-label="ទិន្នន័យថ្មី" style="font-size: 0.88rem; color: #1e293b; font-weight: 600;">${row.new_value || '-'}</td>
                <td data-label="អ្នកអនុវត្ត"><span class="role-badge-pill" style="font-size: 0.78rem;">${row.operator_name || 'Admin'}</span></td>
                <td data-label="សកម្មភាព" style="text-align: center;">${actionBtn}</td>
            </tr>
        `;
    }).join('');

    tbodys.forEach(tb => {
        tb.innerHTML = rowsHtml;
    });
}

function calculateArchiveMetrics(data) {
    let delCount = 0;
    let modCount = 0;
    let totalCount = data.length;

    data.forEach(d => {
        const act = (d.action_type || '').toUpperCase();
        if (act === 'DELETE' || d.is_deleted === 1) delCount++;
        else if (act === 'UPDATE') modCount++;
    });

    updateMetricElement('metric-deleted-count', delCount);
    updateMetricElement('metric-modified-count', modCount);
    updateMetricElement('metric-total-audit', totalCount);
}

/**
 * Restore Transaction Action
 */
async function restoreTransaction(transactionId) {
    if (!confirm('តើអ្នកពិតជាចង់ស្តារប្រតិបត្តិការនេះឡើងវិញមែនទេ? (Restore Transaction)')) return;

    try {
        const response = await fetch(`${API_TRANSACTIONS_URL}?action=restore`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ transaction_id: transactionId })
        });

        const result = await response.json();
        if (result.status === 'success') {
            showToast(result.message || '🎉 បានស្តារប្រតិបត្តិការឡើងវិញដោយជោគជ័យ!', 'success');
            loadArchiveHistoryTable(1);
        } else {
            showToast(result.message || 'បរាជ័យក្នុងការស្តារទិន្នន័យ!', 'error');
        }
    } catch(err) {
        console.error('Error restoring transaction:', err);
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
        const response = await fetch(`${API_BASE_URL}?action=audit_logs`);
        const result = await response.json();

        if (result.status === 'success' && Array.isArray(result.data)) {
            tbody.innerHTML = result.data.map(log => `
                <tr>
                    <td style="padding: 10px; font-size: 0.85rem;">${log.created_at || log.action_date || '-'}</td>
                    <td style="padding: 10px; font-weight: 600;">👤 ${log.username || log.operator_name || 'System'}</td>
                    <td style="padding: 10px;"><span style="background: #e0f2fe; color: #0369a1; padding: 2px 8px; border-radius: 4px; font-size: 0.8rem; font-weight: bold;">${log.action || log.action_type}</span></td>
                    <td style="padding: 10px; font-size: 0.85rem; color: #4b5563;">${log.details || log.new_value || '-'}</td>
                    <td style="padding: 10px; font-size: 0.85rem; color: #6b7280;">${log.ip_address || '127.0.0.1'}</td>
                </tr>
            `).join('');
        } else {
            tbody.innerHTML = `<tr><td colspan="5" style="text-align: center; padding: 20px; color: #ef4444;">មិនអាចទាញយក Audit Logs បានឡើយ។</td></tr>`;
        }
    } catch (err) {
        console.error("Failed to load audit logs:", err);
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
