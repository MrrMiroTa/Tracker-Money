/**
 * admin-integration-v15.js - Frontend JavaScript Engine v15
 * Part of the Khmer Payment Tracker and Financial Management System
 * 
 * Features:
 * - Form submit handler for creating new transactions (with optional receipt file upload)
 * - Two Activity Action buttons (✏️ កែប្រែ / Update & 🗑️ លុប / Delete)
 * - Modal form handler for updating existing transactions
 * - Real-time Chart.js Analytics (Income vs Expense Bar Chart, Category Doughnut Chart)
 * - Exchange Rate Converter ($1 USD = X KHR) & Unified Total Balance Calculation
 * - Category Budget Tracking & Threshold Warnings (>80% and Exceeded)
 * - Advanced Date Range Filtering (From Date - To Date)
 * - Audit Log Viewer for Admins
 * - Responsive Navbar Burger Toggle
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
    setupNavigation();
    setupExchangeRateWidget();
    setupDateFilterListeners();
    setupTransactionForm();
    setupEditTransactionForm();
    loadDashboardMetricsAndCharts();
    loadTransactionsTable(1);
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

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const titleInput = document.getElementById('title');
        const amountInput = document.getElementById('amount');
        const currencyInput = document.getElementById('currency');
        const typeInput = document.getElementById('type');
        const categoryInput = document.getElementById('category');
        const dateInput = document.getElementById('date');
        const receiptInput = document.getElementById('receipt');

        const title = titleInput.value.trim();
        const amount = parseFloat(amountInput.value);
        const currency = currencyInput.value;
        const type = typeInput.value;
        const category = categoryInput.value.trim();
        const date = dateInput.value;

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

                // Set default date to current local datetime
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
 * Open Edit Transaction Modal
 */
function openEditTransactionModal(id, title, amount, currency, type, category, date) {
    let modal = document.getElementById('edit-transaction-modal');
    if (!modal) {
        modal = createEditModalElement();
    }

    document.getElementById('edit-tx-id').value = id;
    document.getElementById('edit-tx-title').value = decodeURIComponent(title);
    document.getElementById('edit-tx-amount').value = amount;
    document.getElementById('edit-tx-currency').value = currency;
    document.getElementById('edit-tx-type').value = type;
    document.getElementById('edit-tx-category').value = decodeURIComponent(category);
    
    if (date) {
        const d = new Date(date);
        if (!isNaN(d.getTime())) {
            const pad = (n) => String(n).padStart(2, '0');
            const localIso = `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
            document.getElementById('edit-tx-date').value = localIso;
        } else {
            document.getElementById('edit-tx-date').value = date;
        }
    }

    modal.classList.add('active');
}

function closeEditTransactionModal() {
    const modal = document.getElementById('edit-transaction-modal');
    if (modal) modal.classList.remove('active');
}

function createEditModalElement() {
    const modal = document.createElement('div');
    modal.id = 'edit-transaction-modal';
    modal.className = 'modal-backdrop';
    modal.innerHTML = `
        <div class="modal-content-card" style="max-width: 500px;">
            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.75rem; margin-bottom: 1rem;">
                <h3 style="margin: 0; color: #1e3a8a;">✏️ កែប្រែប្រតិបត្តិការហិរញ្ញវត្ថុ (Update)</h3>
                <button class="btn btn-secondary" style="padding: 4px 10px;" onclick="closeEditTransactionModal()">&times; បោះបង់</button>
            </div>
            <form id="edit-transaction-form">
                <input type="hidden" id="edit-tx-id">
                <div class="form-group">
                    <label>បរិយាយ / ឈ្មោះប្រតិបត្តិការ</label>
                    <input type="text" id="edit-tx-title" required>
                </div>
                <div class="form-group">
                    <label>ចំនួនទឹកប្រាក់</label>
                    <div class="amount-input-group">
                        <input type="number" id="edit-tx-amount" step="any" required>
                        <select id="edit-tx-currency" required>
                            <option value="KHR">រៀល (៛)</option>
                            <option value="USD">ដុល្លារ ($)</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>ប្រភេទប្រតិបត្តិការ</label>
                    <select id="edit-tx-type" required>
                        <option value="income">ចំណូល (Income)</option>
                        <option value="expense">ចំណាយ (Expense)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>ប្រភេទក្រុម (Category)</label>
                    <input type="text" id="edit-tx-category" required>
                </div>
                <div class="form-group">
                    <label>កាលបរិច្ឆេទ</label>
                    <input type="datetime-local" id="edit-tx-date" required>
                </div>
                <div class="form-group">
                    <label>រូបភាពវិក្កយបត្រ / ស្លីប (Receipt - មិនបង្ខំ/Optional)</label>
                    <input type="file" id="edit-tx-receipt" accept="image/*,.pdf">
                </div>
                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 1rem;">
                    <button type="button" class="btn btn-secondary" onclick="closeEditTransactionModal()">បោះបង់</button>
                    <button type="submit" class="btn btn-primary" style="background: #2563eb;">💾 ធ្វើបច្ចុប្បន្នភាព (Save Changes)</button>
                </div>
            </form>
        </div>
    `;
    document.body.appendChild(modal);
    setupEditTransactionForm();
    return modal;
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
        const amt = parseFloat(t.raw_amount ?? t.amount) || 0;
        const curr = (t.raw_currency || t.currency || '').toUpperCase();
        const type = (t.raw_type || t.type || '').toLowerCase();

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

    updateMetricElement('total-balance-khr', `${balKhr.toLocaleString()} ៛`);
    updateMetricElement('total-balance-usd', `$${balUsd.toFixed(2)}`);
    updateMetricElement('total-income-khr', `${incKhr.toLocaleString()} ៛`);
    updateMetricElement('total-income-usd', `$${incUsd.toFixed(2)}`);
    updateMetricElement('total-expense-khr', `${expKhr.toLocaleString()} ៛`);
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
            const amt = parseFloat(t.raw_amount ?? t.amount) || 0;
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
        const amt = parseFloat(t.raw_amount ?? t.amount) || 0;
        const curr = (t.raw_currency || t.currency || '').toUpperCase();
        const type = (t.raw_type || t.type || '').toLowerCase();
        const amtUsd = (curr === 'USD') ? amt : (amt / currentUsdKhrRate);

        if (type === 'income') incUsd += amtUsd;
        else if (type === 'expense') expUsd += amtUsd;
    });

    if (incomeExpenseChart) incomeExpenseChart.destroy();

    const ctx = canvas.getContext('2d');
    incomeExpenseChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['ចំណូល (Income)', 'ចំណាយ (Expense)'],
            datasets: [{
                label: 'ប្រាក់ដុល្លារ ($ USD)',
                data: [incUsd.toFixed(2), expUsd.toFixed(2)],
                backgroundColor: ['#10b981', '#ef4444'],
                borderRadius: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) { return '$' + value; }
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

    transactions.forEach(t => {
        const type = (t.raw_type || t.type || '').toLowerCase();
        if (type === 'expense') {
            const cat = (t.category || '').trim();
            const amt = parseFloat(t.raw_amount ?? t.amount) || 0;
            const curr = (t.raw_currency || t.currency || '').toUpperCase();
            const amtUsd = (curr === 'USD') ? amt : (amt / currentUsdKhrRate);

            catTotals[cat] = (catTotals[cat] || 0) + amtUsd;
        }
    });

    const labels = Object.keys(catTotals);
    const dataValues = Object.values(catTotals).map(v => v.toFixed(2));

    const colors = [
        '#ef4444', '#f59e0b', '#3b82f6', '#10b981', '#8b5cf6', 
        '#ec4899', '#06b6d4', '#84cc16', '#64748b'
    ];

    if (categoryDoughnutChart) categoryDoughnutChart.destroy();

    const ctx = canvas.getContext('2d');
    categoryDoughnutChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: dataValues,
                backgroundColor: colors.slice(0, labels.length)
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { font: { family: "'Kantumruy Pro', sans-serif" } }
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

/**
 * Render Table Rows with 2 Activity Options (Update & Delete)
 */
function renderTableRows(data) {
    const tbody = document.getElementById('transaction-table-body');
    if (!tbody) return;

    if (data.length === 0) {
        tbody.innerHTML = `<tr><td colspan="7" style="text-align: center; padding: 20px; color: #6b7280;">មិនមានប្រតិបត្តិការនៅក្នុងចន្លោះកាលបរិច្ឆេទនេះឡើយ។</td></tr>`;
        return;
    }

    tbody.innerHTML = data.map(row => {
        const rawType = (row.raw_type || (row.type === 'Income' ? 'income' : 'expense')).toLowerCase();
        const typeColor = rawType === 'income' ? '#10b981' : '#ef4444';
        const typeText = rawType === 'income' ? 'ចំណូល' : 'ចំណាយ';
        const receiptBadge = row.receipt_image ? `<a href="${row.receipt_image}" target="_blank" style="color: #2563eb; font-size: 0.85rem; font-weight: bold; text-decoration: underline;">🧾 មើលវិក្កយបត្រ</a>` : `<span style="color: #9ca3af; font-size: 0.85rem;">គ្មាន</span>`;

        const escapedTitle = encodeURIComponent(row.description);
        const escapedCategory = encodeURIComponent(row.category);
        const rawAmt = row.raw_amount || parseFloat(row.amount) || 0;
        const rawCurr = row.raw_currency || (row.amount.includes('$') ? 'USD' : 'KHR');
        const rawDate = row.raw_date || row.date;

        return `
            <tr>
                <td data-label="កាលបរិច្ឆេទ">${row.date}</td>
                <td data-label="បរិយាយ"><strong>${row.description}</strong></td>
                <td data-label="អ្នកបន្ថែម">${row.creator || 'User'}</td>
                <td data-label="ប្រភេទ"><span style="color: ${typeColor}; font-weight: bold;">${typeText} (${row.category})</span></td>
                <td data-label="ចំនួនទឹកប្រាក់" style="font-weight: bold; color: ${typeColor};">${row.amount}</td>
                <td data-label="វិក្កយបត្រ">${receiptBadge}</td>
                <td data-label="សកម្មភាព" style="text-align: center;">
                    <div style="display: flex; gap: 6px; justify-content: center; flex-wrap: wrap;">
                        <button class="btn btn-secondary" style="padding: 4px 10px; font-size: 0.8rem; background: #2563eb; color: white; border: none; border-radius: 4px; cursor: pointer;" onclick="openEditTransactionModal(${row.id}, '${escapedTitle}', ${rawAmt}, '${rawCurr}', '${rawType}', '${escapedCategory}', '${rawDate}')">✏️ កែប្រែ</button>
                        <button class="btn btn-danger" style="padding: 4px 10px; font-size: 0.8rem; background: #ef4444; color: white; border: none; border-radius: 4px; cursor: pointer;" onclick="softDeleteTransaction(${row.id})">🗑️ លុប</button>
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
 * Soft Delete Transaction Function
 */
async function softDeleteTransaction(id) {
    if (!confirm('តើអ្នកពិតជាចង់លុបប្រតិបត្តិការនេះមែនទេ?')) return;

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
        console.error('Error deleting transaction:', err);
        showToast('មានបញ្ហាបច្ចេកទេសក្នុងការលុបទិន្នន័យ!', 'error');
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
                    <td style="padding: 10px;"><span style="background: #e0f2fe; color: #0369a1; padding: 2px 8px; border-radius: 4px; font-size: 0.8rem; font-weight: bold;">${log.action}</span></td>
                    <td style="padding: 10px; font-size: 0.85rem; color: #4b5563;">${log.details || '-'}</td>
                    <td style="padding: 10px; font-size: 0.85rem; color: #6b7280;">${log.ip_address}</td>
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
