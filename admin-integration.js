/**
 * admin-integration.js - Production JavaScript Engine (v20)
 * Part of the Khmer Payment Tracker and Financial Management System
 * 
 * Features:
 * - Ultra-Responsive Mobile Layout Handling
 * - Dark Mode Toggle & LocalStorage Memory
 * - 3-Dots Action Dropdown Menu for Transaction Table
 * - Real-time Chart.js Analytics (Income vs Expense Bar Chart, Category Doughnut Chart)
 * - Exchange Rate Converter ($1 USD = X KHR) & Unified Total Balance Calculation
 * - Category Budget Tracking & Threshold Warning Banners
 * - Advanced Date Range Filtering (From Date - To Date)
 * - Audit Log Viewer for Admins
 * - Optional Receipt File Upload Handling
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

// Close open action menus when clicking outside
document.addEventListener('click', (e) => {
    if (!e.target.closest('.action-dropdown')) {
        document.querySelectorAll('.action-menu.show').forEach(menu => menu.classList.remove('show'));
    }
});

/**
 * Initialize Application Engine
 */
function initApp() {
    setupNavigation();
    setupExchangeRateWidget();
    setupDateFilterListeners();
    setupTransactionForm();
    setupEditTransactionForm();
    loadDashboardMetricsAndCharts();
    loadTransactionsTable(1);
    initDarkModeState();
}

/**
 * Dark Mode Theme Initializer & Toggle
 */
function initDarkModeState() {
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'dark') {
        document.body.classList.add('dark-mode');
        updateDarkModeBtnText(true);
    }
}

function toggleTheme() {
    const isDark = document.body.classList.toggle('dark-mode');
    localStorage.setItem('theme', isDark ? 'dark' : 'light');
    updateDarkModeBtnText(isDark);
    showToast(isDark ? '🌙 បានផ្លាស់ប្តូរទៅជា Dark Mode' : '☀️ បានផ្លាស់ប្តូរទៅជា Light Mode', 'info');
    
    // Refresh chart text colors if loaded
    loadDashboardMetricsAndCharts();
}

function updateDarkModeBtnText(isDark) {
    const btn = document.getElementById('dark-mode-toggle');
    if (btn) {
        btn.innerHTML = isDark ? '☀️ Light Mode' : '🌙 Dark Mode';
    }
}

/**
 * Setup Responsive Navbar Burger Menu Toggle
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
    const searchDateInput = document.getElementById('search-date');

    if (fromDateInput && toDateInput) {
        fromDateInput.addEventListener('change', () => loadTransactionsTable(1));
        toDateInput.addEventListener('change', () => loadTransactionsTable(1));
    } else if (searchDateInput) {
        searchDateInput.addEventListener('change', () => loadTransactionsTable(1));
    }
}

/**
 * Setup Form Submission Handler for Adding New Transactions
 */
function setupTransactionForm() {
    const form = document.getElementById('transaction-form');
    if (!form) return;

    // Set default date input value if empty
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

                // Reset date to current time
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

/**
 * Open Edit Transaction Modal Window
 */
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
 * Toggle Action Dropdown Menu for 3-Dots (...) Button
 */
function toggleActionMenu(event, id) {
    event.stopPropagation();

    // Close all other active action menus
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

/**
 * Trigger Update Action from Dropdown Menu
 */
function triggerUpdate(id, encodedTitle, amount, currency, type, encodedCategory, date) {
    document.querySelectorAll('.action-menu.show').forEach(menu => menu.classList.remove('show'));

    const title = decodeURIComponent(encodedTitle);
    const category = decodeURIComponent(encodedCategory);

    openEditTransactionModal(id, title, amount, currency, type, category, date);
}

/**
 * Trigger Delete Action from Dropdown Menu (With Alert Confirmation)
 */
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

/**
 * Calculate Metrics & Unified Balance using USD/KHR Exchange Rate
 */
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

    // Unified Total Calculations
    const unifiedNetUsd = balUsd + (balKhr / currentUsdKhrRate);
    const unifiedNetKhr = balKhr + (balUsd * currentUsdKhrRate);

    updateMetricElement('unified-total-usd', `$${unifiedNetUsd.toFixed(2)}`);
    updateMetricElement('unified-total-khr', `${Math.round(unifiedNetKhr).toLocaleString()} ៛`);
}

function updateMetricElement(id, text) {
    const el = document.getElementById(id);
    if (el) el.innerText = text;
}

/**
 * Check Category Budgets and render warning banners if >80% or exceeded
 */
function checkCategoryBudgetAlerts(transactions) {
    const container = document.getElementById('budget-alerts-container');
    if (!container) return;

    container.innerHTML = '';

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
    const gridColor = isDarkMode ? 'rgba(255, 255, 255, 0.08)' : 'rgba(0, 0, 0, 0.05)';
    const isMobile = window.innerWidth < 480;

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
                            return ` $${valUsd.toLocaleString(undefined, {minimumFractionDigits: 2})} USD (${valKhr.toLocaleString()} ៛)`;
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: {
                        color: textColor,
                        font: { family: "'Kantumruy Pro', sans-serif", size: isMobile ? 11 : 12, weight: '600' }
                    }
                },
                y: {
                    beginAtZero: true,
                    grid: { color: gridColor },
                    ticks: {
                        color: textColor,
                        font: { family: "sans-serif", size: isMobile ? 10 : 11 },
                        callback: function(v) { return '$' + v; }
                    }
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
    const isMobile = window.innerWidth < 480;

    // Handle Empty Case gracefully
    if (labels.length === 0 || totalExpenseUSD === 0) {
        const ctx = canvas.getContext('2d');
        categoryDoughnutChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['គ្មានទិន្នន័យចំណាយ'],
                datasets: [{
                    data: [1],
                    backgroundColor: [isDarkMode ? '#334155' : '#e2e8f0'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '72%',
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
            }
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
                borderColor: isDarkMode ? '#1e293b' : '#ffffff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '68%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        color: textColor,
                        usePointStyle: true,
                        pointStyle: 'circle',
                        boxWidth: 8,
                        boxHeight: 8,
                        padding: isMobile ? 8 : 12,
                        font: {
                            family: "'Kantumruy Pro', sans-serif",
                            size: isMobile ? 10 : 12
                        }
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
                            const catName = context.label || '';
                            const valUsd = parseFloat(context.raw) || 0;
                            const pct = totalExpenseUSD > 0 ? ((valUsd / totalExpenseUSD) * 100).toFixed(1) : 0;
                            return ` ${catName}: $${valUsd.toLocaleString(undefined, {minimumFractionDigits: 2})} (${pct}%)`;
                        }
                    }
                }
            }
        }
    });
}

/**
 * Load Transactions Table with Date Range Filter & Pagination
 */
async function loadTransactionsTable(page = 1) {
    const tbody = document.getElementById('transaction-table-body');
    if (!tbody) return;

    let url = `${API_TRANSACTIONS_URL}?page=${page}`;

    const fromDate = document.getElementById('filter-from-date')?.value;
    const toDate = document.getElementById('filter-to-date')?.value;
    const searchDate = document.getElementById('search-date')?.value;

    if (fromDate && toDate) {
        url += `&from_date=${fromDate}&to_date=${toDate}`;
    } else if (searchDate) {
        url += `&date=${searchDate}`;
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
                <td data-label="បរិយាយ"><strong style="color: var(--dark);">${row.description}</strong></td>
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
        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--gray-border); font-size: 0.88rem; flex-wrap: wrap; gap: 10px;">
            <span>ទំព័រទី <strong>${pagination.current_page}</strong> នៃ <strong>${pagination.total_pages}</strong> (សរុប ${pagination.total_records} ប្រតិបត្តិការ)</span>
            <div style="display: flex; gap: 6px;">
                <button class="btn btn-secondary" style="padding: 4px 12px; font-size: 0.85rem;" ${pagination.current_page <= 1 ? 'disabled' : ''} onclick="loadTransactionsTable(${pagination.current_page - 1})">← មុន</button>
                <button class="btn btn-secondary" style="padding: 4px 12px; font-size: 0.85rem;" ${pagination.current_page >= pagination.total_pages ? 'disabled' : ''} onclick="loadTransactionsTable(${pagination.current_page + 1})">បន្ទាប់ →</button>
            </div>
        </div>
    `;
}

/**
 * Soft Delete Transaction with Alert Confirmation Dialog
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
 * Open Audit Log Viewer Modal for Admins
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
                    <td style="padding: 10px; font-size: 0.85rem;">${log.created_at}</td>
                    <td style="padding: 10px; font-weight: 600;">👤 ${log.username || 'System'}</td>
                    <td style="padding: 10px;"><span style="background: var(--primary-light); color: var(--primary); padding: 2px 8px; border-radius: 4px; font-size: 0.8rem; font-weight: bold;">${log.action}</span></td>
                    <td style="padding: 10px; font-size: 0.85rem; color: var(--gray-text);">${log.details || '-'}</td>
                    <td style="padding: 10px; font-size: 0.85rem; color: var(--gray-text);">${log.ip_address}</td>
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
