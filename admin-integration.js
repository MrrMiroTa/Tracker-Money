/**
 * admin-integration-v5.js - Frontend API Integration with Audit Archive & Edit Capability
 * Part of the Khmer Payment Tracker and Financial Management System
 * 
 * Includes paginated transactions table, date filters, secure editing, soft deletion,
 * real-time dashboard widgets, and dedicated audit history loading for admins.
 */

const API_BASE_URL = 'api-v2.php';
const API_TRANSACTIONS_URL = 'api-transactions.php';
// --- CUSTOM UI COMPONENTS (TOAST, CONFIRM MODAL, ACTION CHOICE MODAL) ---

/**
 * Show a beautifully designed custom toast notification (replacing browser alert)
 */
function showToast(message, type = 'success') {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.style.position = 'fixed';
        container.style.top = '20px';
        container.style.right = '20px';
        container.style.zIndex = '9999';
        container.style.display = 'flex';
        container.style.flexDirection = 'column';
        container.style.gap = '10px';
        document.body.appendChild(container);
    }
    
    const toast = document.createElement('div');
    toast.style.minWidth = '300px';
    toast.style.padding = '12px 20px';
    toast.style.borderRadius = '8px';
    toast.style.color = '#ffffff';
    toast.style.fontFamily = "'Kantumruy Pro', sans-serif";
    toast.style.fontSize = '0.9rem';
    toast.style.fontWeight = '600';
    toast.style.boxShadow = '0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05)';
    toast.style.display = 'flex';
    toast.style.alignItems = 'center';
    toast.style.justifyContent = 'space-between';
    toast.style.transition = 'all 0.3s ease-in-out';
    toast.style.transform = 'translateX(120%)';
    toast.style.opacity = '0';
    
    if (type === 'success') {
        toast.style.backgroundColor = '#10b981'; // Green
        toast.innerHTML = `<span>🎉 ${message}</span>`;
    } else if (type === 'error') {
        toast.style.backgroundColor = '#ef4444'; // Red
        toast.innerHTML = `<span>❌ ${message}</span>`;
    } else {
        toast.style.backgroundColor = '#3b82f6'; // Blue / Info
        toast.innerHTML = `<span>ℹ️ ${message}</span>`;
    }
    
    const closeBtn = document.createElement('span');
    closeBtn.innerHTML = '&times;';
    closeBtn.style.cursor = 'pointer';
    closeBtn.style.fontSize = '1.25rem';
    closeBtn.style.marginLeft = '15px';
    closeBtn.addEventListener('click', () => {
        toast.style.transform = 'translateX(120%)';
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 300);
    });
    toast.appendChild(closeBtn);
    container.appendChild(toast);
    
    // Trigger animation
    setTimeout(() => {
        toast.style.transform = 'translateX(0)';
        toast.style.opacity = '1';
    }, 50);
    
    // Auto remove
    setTimeout(() => {
        if (toast.parentNode) {
            toast.style.transform = 'translateX(120%)';
            toast.style.opacity = '0';
            setTimeout(() => toast.remove(), 300);
        }
    }, 4000);
}

/**
 * Show a beautifully designed custom confirmation modal (replacing browser confirm)
 */
function showCustomConfirm(message, onConfirm) {
    let modal = document.getElementById('custom-confirm-modal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'custom-confirm-modal';
        modal.style.position = 'fixed';
        modal.style.zIndex = '10001';
        modal.style.left = '0';
        modal.style.top = '0';
        modal.style.width = '100%';
        modal.style.height = '100%';
        modal.style.backgroundColor = 'rgba(0,0,0,0.5)';
        modal.style.display = 'none';
        modal.style.justifyContent = 'center';
        modal.style.alignItems = 'center';
        modal.style.fontFamily = "'Kantumruy Pro', sans-serif";
        
        modal.innerHTML = `
            <div style="background-color: #ffffff; padding: 2rem; border-radius: 12px; width: 400px; max-width: 90%; box-shadow: 0 4px 15px rgba(0,0,0,0.2); text-align: center;">
                <div style="font-size: 3rem; color: #ef4444; margin-bottom: 1rem;">⚠️</div>
                <h3 style="margin-top: 0; color: #1f2937;" id="custom-confirm-title">បញ្ជាក់សកម្មភាព</h3>
                <p id="custom-confirm-msg" style="color: #4b5563; font-size: 0.95rem; margin-bottom: 1.5rem;"></p>
                <div style="display: flex; gap: 12px; justify-content: center;">
                    <button id="custom-confirm-yes" style="padding: 8px 20px; border: none; background: #ef4444; color: white; border-radius: 6px; font-weight: bold; cursor: pointer; transition: background 0.2s;" onmouseover="this.style.backgroundColor='#dc2626'" onmouseout="this.style.backgroundColor='#ef4444'">យល់ព្រមលុប</button>
                    <button id="custom-confirm-no" style="padding: 8px 20px; border: 1px solid #d1d5db; background: #ffffff; color: #4b5563; border-radius: 6px; font-weight: bold; cursor: pointer; transition: background 0.2s;" onmouseover="this.style.backgroundColor='#f3f4f6'" onmouseout="this.style.backgroundColor='#ffffff'">បោះបង់</button>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
    }
    
    document.getElementById('custom-confirm-msg').innerText = message;
    modal.style.display = 'flex';
    
    const yesBtn = document.getElementById('custom-confirm-yes');
    const noBtn = document.getElementById('custom-confirm-no');
    
    const newYesBtn = yesBtn.cloneNode(true);
    const newNoBtn = noBtn.cloneNode(true);
    yesBtn.parentNode.replaceChild(newYesBtn, yesBtn);
    noBtn.parentNode.replaceChild(newNoBtn, noBtn);
    
    newYesBtn.addEventListener('click', () => {
        modal.style.display = 'none';
        if (typeof onConfirm === 'function') onConfirm();
    });
    
    newNoBtn.addEventListener('click', () => {
        modal.style.display = 'none';
    });
}

/**
 * Show action choices (Update/Delete) for a transaction when user clicks the '...' button
 */
function showActionChoiceModal(id, title, amount, currency, type, category, date) {
    let modal = document.getElementById('action-choice-modal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'action-choice-modal';
        modal.style.position = 'fixed';
        modal.style.zIndex = '10000';
        modal.style.left = '0';
        modal.style.top = '0';
        modal.style.width = '100%';
        modal.style.height = '100%';
        modal.style.backgroundColor = 'rgba(0,0,0,0.5)';
        modal.style.display = 'none';
        modal.style.justifyContent = 'center';
        modal.style.alignItems = 'center';
        modal.style.fontFamily = "'Kantumruy Pro', sans-serif";
        
        modal.innerHTML = `
            <div style="background-color: #ffffff; padding: 2rem; border-radius: 12px; width: 420px; max-width: 90%; box-shadow: 0 4px 15px rgba(0,0,0,0.2);">
                <h3 style="margin-top: 0; color: #1e3a8a; border-bottom: 2px solid #e5e7eb; padding-bottom: 0.5rem;" id="action-choice-title">សកម្មភាពប្រតិបត្តិការ</h3>
                <div style="margin: 1rem 0; padding: 10px; background-color: #f3f4f6; border-radius: 8px;">
                    <span style="font-size: 0.85rem; color: #6b7280;">ប្រតិបត្តិការ៖</span><br>
                    <strong id="action-choice-desc" style="font-size: 1.05rem; color: #1f2937;"></strong><br>
                    <span style="font-size: 0.85rem; color: #6b7280;">ទឹកប្រាក់៖</span> <strong id="action-choice-amount" style="color: #2563eb;"></strong>
                </div>
                <p style="color: #4b5563; font-size: 0.9rem; margin-bottom: 1.5rem;">សូមជ្រើសរើសសកម្មភាពណាមួយដែលលោកអ្នកចង់អនុវត្តខាងក្រោម៖</p>
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    <button id="action-btn-update" style="padding: 10px; border: none; background: #3b82f6; color: white; border-radius: 6px; font-weight: bold; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; transition: background 0.2s;" onmouseover="this.style.backgroundColor='#2563eb'" onmouseout="this.style.backgroundColor='#3b82f6'">✏️ កែប្រែទិន្នន័យ (Update)</button>
                    <button id="action-btn-delete" style="padding: 10px; border: none; background: #ef4444; color: white; border-radius: 6px; font-weight: bold; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; transition: background 0.2s;" onmouseover="this.style.backgroundColor='#dc2626'" onmouseout="this.style.backgroundColor='#ef4444'">🗑️ លុបប្រតិបត្តិការ (Delete)</button>
                    <button id="action-btn-cancel" style="padding: 10px; border: 1px solid #d1d5db; background: #ffffff; color: #4b5563; border-radius: 6px; font-weight: bold; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; transition: background 0.2s;" onmouseover="this.style.backgroundColor='#f3f4f6'" onmouseout="this.style.backgroundColor='#ffffff'">បោះបង់</button>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
    }
    
    const decodedTitle = decodeURIComponent(title);
    const displayAmount = currency === 'KHR' ? parseFloat(amount).toLocaleString() + ' ៛' : '$' + parseFloat(amount).toFixed(2);
    
    document.getElementById('action-choice-desc').innerText = decodedTitle;
    document.getElementById('action-choice-amount').innerText = displayAmount;
    
    modal.style.display = 'flex';
    
    const updateBtn = document.getElementById('action-btn-update');
    const deleteBtn = document.getElementById('action-btn-delete');
    const cancelBtn = document.getElementById('action-btn-cancel');
    
    const newUpdateBtn = updateBtn.cloneNode(true);
    const newDeleteBtn = deleteBtn.cloneNode(true);
    const newCancelBtn = cancelBtn.cloneNode(true);
    
    updateBtn.parentNode.replaceChild(newUpdateBtn, updateBtn);
    deleteBtn.parentNode.replaceChild(newDeleteBtn, deleteBtn);
    cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);
    
    newUpdateBtn.addEventListener('click', () => {
        modal.style.display = 'none';
        editTransactionClick(id, title, amount, currency, type, category, date);
    });
    
    newDeleteBtn.addEventListener('click', () => {
        modal.style.display = 'none';
        deleteTransaction(id);
    });
    
    newCancelBtn.addEventListener('click', () => {
        modal.style.display = 'none';
    });
}


// Pagination State
let currentTxPage = 1;
let txLimitPerPage = 5;
let currentFilterDate = '';

/**
 * Request Handler Helper
 */
async function sendRequest(url, method = 'GET', bodyData = null) {
    const options = {
        method: method,
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    };
    
    if (bodyData && (method === 'POST' || method === 'PUT' || method === 'DELETE')) {
        options.body = JSON.stringify(bodyData);
    }

    try {
        const response = await fetch(url, options);
        const result = await response.json();
        
        if (!response.ok) {
            throw new Error(result.message || `Request failed with status ${response.status}`);
        }
        return result;
    } catch (error) {
        console.error('API Error:', error);
        throw error;
    }
}

/**
 * 1. User Authentication (Login)
 */
async function loginUser(username, password) {
    try {
        const result = await sendRequest(`${API_BASE_URL}?action=login`, 'POST', { username, password });
        console.log('Login success:', result);
        localStorage.setItem('current_user', JSON.stringify(result.user));
        return result;
    } catch (error) {
        showToast(`Login Failed: ${error.message}`, 'error');
        throw error;
    }
}

/**
 * 2. User Logout
 */
async function logoutUser() {
    try {
        await sendRequest(`${API_BASE_URL}?action=logout`, 'POST');
    } catch (error) {
        console.error('Logout failed, forcing client-side logout:', error);
    } finally {
        localStorage.removeItem('current_user');
        window.location.href = 'login.php';
    }
}

/**
 * 3. Retrieve Transaction Metrics
 */
async function fetchTransactionMetrics() {
    try {
        const result = await sendRequest(`${API_TRANSACTIONS_URL}?action=metrics`, 'GET');
        return result.data;
    } catch (error) {
        console.error('Failed to load transaction metrics:', error);
        return null;
    }
}

/**
 * Update Dashboard Metrics Widgets
 */
async function updateDashboardMetricsUI() {
    const metrics = await fetchTransactionMetrics();
    if (!metrics) return;

    const elements = {
        'total-balance-khr': metrics.balance.KHR,
        'total-balance-usd': metrics.balance.USD,
        'total-income-khr': metrics.income.KHR,
        'total-income-usd': metrics.income.USD,
        'total-expense-khr': metrics.expense.KHR,
        'total-expense-usd': metrics.expense.USD
    };

    for (const [id, value] of Object.entries(elements)) {
        const el = document.getElementById(id);
        if (el) {
            el.innerText = value;
            if (id.includes('balance')) {
                const isNegative = (id.includes('khr') && metrics.balance.raw_khr < 0) || 
                                   (id.includes('usd') && metrics.balance.raw_usd < 0);
                el.style.color = isNegative ? '#ef4444' : '#10b981';
            }
        }
    }
}

/**
 * ទាញយក និងបង្ហាញតារាងប្រតិបត្តិការ (Paginated Transactions Table)
 */
async function loadTransactionsTable(page = 1, limit = 5, dateFilter = '') {
    const tableBody = document.getElementById('transaction-table-body');
    if (!tableBody) return;

    currentTxPage = page;
    txLimitPerPage = limit;
    currentFilterDate = dateFilter;

    tableBody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 20px;">កំពុងទាញយកទិន្នន័យប្រតិបត្តិការ...</td></tr>';

    try {
        let fetchUrl = `${API_TRANSACTIONS_URL}?page=${page}&limit=${limit}`;
        if (dateFilter) {
            fetchUrl += `&date=${dateFilter}`;
        }

        const response = await fetch(fetchUrl);
        const result = await response.json();

        if (result.status === 'success') {
            tableBody.innerHTML = ''; 

            if (result.data.length === 0) {
                const message = dateFilter 
                    ? `មិនមានប្រតិបត្តិការណាមួយក្នុងថ្ងៃទី ${dateFilter} នេះទេ។` 
                    : `មិនទាន់មានប្រតិបត្តិការនៅឡើយទេ។`;
                tableBody.innerHTML = `<tr><td colspan="6" style="text-align: center; padding: 20px; color: #6b7280; font-weight: 600;">${message}</td></tr>`;
                renderPaginationControls({ total_pages: 0, current_page: 0 });
                return;
            }

            result.data.forEach(row => {
                const typeColor = (row.raw_type === 'income') ? '#10b981' : '#ef4444';
                const tr = document.createElement('tr');
                tr.style.borderBottom = '1px solid #e5e7eb';
                
                const currentUser = JSON.parse(localStorage.getItem('current_user') || '{}');
                
                // User can edit/delete their own transaction (row.creator === '-'), Admin/Super Admin can edit/delete any
                const isOwner = (row.creator === '-');
                const canModify = isOwner || (currentUser.role === 'super_admin' || currentUser.role === 'admin');

                let actionButtonsHtml = `<span style="color: #9ca3af;">⋯</span>`;

                if (canModify) {
                    const escapedDesc = encodeURIComponent(row.description);
                    const escapedCategory = encodeURIComponent(row.category);
                    
                    actionButtonsHtml = `
                        <button onclick="showActionChoiceModal(${row.id}, '${escapedDesc}', ${row.raw_amount}, '${row.raw_currency}', '${row.raw_type}', '${escapedCategory}', '${row.raw_date}')" 
                                style="background: #f3f4f6; border: 1px solid #cbd5e1; color: #4b5563; cursor: pointer; font-size: 1.1rem; padding: 4px 12px; border-radius: 6px; font-weight: bold; transition: all 0.15s ease-in-out;" 
                                onmouseover="this.style.backgroundColor='#e5e7eb'; this.style.borderColor='#cbd5e1';"
                                onmouseout="this.style.backgroundColor='#f3f4f6'; this.style.borderColor='#cbd5e1';"
                                title="ជម្រើសសកម្មភាព">⋯</button>
                    `;
                }

                tr.innerHTML = `
                    <td style="padding: 12px; font-size: 0.9rem; color: #4b5563;">${row.date}</td>
                    <td style="padding: 12px; font-weight: 600; color: #1f2937;">${row.description}</td>
                    <td style="padding: 12px; color: #6b7280;">${row.creator}</td>
                    <td style="padding: 12px;"><span style="color: ${typeColor}; font-weight: bold;">${row.type}</span></td>
                    <td style="padding: 12px; font-weight: bold; color: #1f2937;">${row.amount}</td>
                    <td style="padding: 12px; text-align: center;">${actionButtonsHtml}</td>
                `;
                tableBody.appendChild(tr);
            });

            renderPaginationControls(result.pagination);
        } else {
            tableBody.innerHTML = `<tr><td colspan="6" style="text-align: center; padding: 20px; color: #ef4444;">❌ មិនអាចទាញយកទិន្នន័យបានទេ៖ ${result.message}</td></tr>`;
        }
    } catch (error) {
        console.error('Error fetching transactions:', error);
        tableBody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 20px; color: #ef4444;">❌ កំហុសក្នុងការតភ្ជាប់ទៅកាន់ Server!</td></tr>';
    }
}

/**
 * បង្កើតរបារគ្រប់គ្រងទំព័រ (Pagination UI Builder)
 */
function renderPaginationControls(pagination) {
    let container = document.getElementById('pagination-controls');
    
    if (!container) {
        const table = document.querySelector('.transaction-table');
        if (!table) return;
        
        container = document.createElement('div');
        container.id = 'pagination-controls';
        table.parentNode.insertBefore(container, table.nextSibling);
    }

    const { current_page, total_pages, total_records } = pagination;

    container.innerHTML = '';
    
    container.style.display = 'flex';
    container.style.justifyContent = 'space-between';
    container.style.alignItems = 'center';
    container.style.marginTop = '1rem';
    container.style.padding = '0.5rem 1rem';
    container.style.fontFamily = "'Kantumruy Pro', sans-serif";

    if (total_pages <= 1) {
        container.style.display = 'none'; 
        return;
    }

    const infoSpan = document.createElement('span');
    infoSpan.style.fontSize = '0.9rem';
    infoSpan.style.color = '#4b5563';
    infoSpan.innerText = `ទំព័រទី ${current_page} នៃ ${total_pages} (សរុប ${total_records} ប្រតិបត្តិការ)`;
    container.appendChild(infoSpan);

    const btnWrapper = document.createElement('div');
    btnWrapper.style.display = 'flex';
    btnWrapper.style.gap = '6px';

    const prevBtn = document.createElement('button');
    prevBtn.innerText = '« មុន';
    stylePaginationButton(prevBtn, current_page === 1);
    if (current_page > 1) {
        prevBtn.addEventListener('click', () => loadTransactionsTable(current_page - 1, txLimitPerPage, currentFilterDate));
    }
    btnWrapper.appendChild(prevBtn);

    for (let i = 1; i <= total_pages; i++) {
        const pageBtn = document.createElement('button');
        pageBtn.innerText = i;
        stylePaginationButton(pageBtn, false, i === current_page);
        pageBtn.addEventListener('click', () => loadTransactionsTable(i, txLimitPerPage, currentFilterDate));
        btnWrapper.appendChild(pageBtn);
    }

    const nextBtn = document.createElement('button');
    nextBtn.innerText = 'បន្ទាប់ »';
    stylePaginationButton(nextBtn, current_page === total_pages);
    if (current_page < total_pages) {
        nextBtn.addEventListener('click', () => loadTransactionsTable(current_page + 1, txLimitPerPage, currentFilterDate));
    }
    btnWrapper.appendChild(nextBtn);

    container.appendChild(btnWrapper);
}

function stylePaginationButton(btn, isDisabled, isActive = false) {
    btn.style.padding = '6px 12px';
    btn.style.fontSize = '0.85rem';
    btn.style.border = '1px solid #e5e7eb';
    btn.style.borderRadius = '6px';
    btn.style.cursor = isDisabled ? 'not-allowed' : 'pointer';
    btn.style.fontFamily = "'Kantumruy Pro', sans-serif";
    btn.style.fontWeight = '600';
    btn.style.transition = 'all 0.15s ease-in-out';

    if (isDisabled) {
        btn.style.backgroundColor = '#f3f4f6';
        btn.style.color = '#9ca3af';
    } else if (isActive) {
        btn.style.backgroundColor = '#2563eb';
        btn.style.color = '#ffffff';
        btn.style.borderColor = '#2563eb';
    } else {
        btn.style.backgroundColor = '#ffffff';
        btn.style.color = '#4b5563';
        
        btn.addEventListener('mouseover', () => {
            btn.style.backgroundColor = '#f9fafb';
            btn.style.borderColor = '#d1d5db';
        });
        btn.addEventListener('mouseout', () => {
            btn.style.backgroundColor = '#ffffff';
            btn.style.borderColor = '#e5e7eb';
        });
    }
}

/**
 * ៤. លុបប្រតិបត្តិការដោយសុវត្ថិភាព (Soft Delete UI Action with Version archiving)
 */
async function deleteTransaction(transactionId) {
    showCustomConfirm("តើលោកអ្នកពិតជាចង់លុបប្រតិបត្តិការនេះមែនទេ? សកម្មភាពនេះនឹងត្រូវបានចម្លងទុកបណ្ណសារសវនកម្ម។", async () => {

    try {
        const response = await fetch(API_TRANSACTIONS_URL, {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ transaction_id: transactionId })
        });

        const result = await response.json();

        if (result.status === 'success') {
            showToast(result.message, 'success');
            loadTransactionsTable(currentTxPage, txLimitPerPage, currentFilterDate);
            updateDashboardMetricsUI();
        } else {
            showToast(result.message, 'error');
        }
    } catch (error) {
        console.error('Error deleting transaction:', error);
        showToast('បរាជ័យក្នុងការតភ្ជាប់ទៅកាន់ម៉ាស៊ីនបម្រើ!', 'error');
    }
    });
}

/**
 * ៥. ទាញយក និងបង្ហាញប្រវត្តិសវនកម្ម (Audit Archive Table for Admin Panel)
 */
async function loadAuditHistoryTable() {
    const tableBody = document.getElementById('history-table-body');
    if (!tableBody) return;

    tableBody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 30px;">កំពុងទាញយកទិន្នន័យប្រវត្តិសវនកម្ម...</td></tr>';

    try {
        const response = await fetch(`${API_TRANSACTIONS_URL}?action=admin_history`);
        const result = await response.json();

        if (result.status === 'success') {
            tableBody.innerHTML = '';

            if (result.data.length === 0) {
                tableBody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 40px; color: #6b7280;">គ្មានកំណត់ត្រាបម្រុងទុកទិន្នន័យកែប្រែ ឬលុបនៅឡើយទេ។</td></tr>';
                return;
            }

            result.data.forEach(row => {
                let actionBadgeClass = 'badge-update';
                let actionBadgeText = 'កែប្រែ';
                
                if (row.action_type === 'DELETE') {
                    actionBadgeClass = 'badge-delete';
                    actionBadgeText = 'លុបចោល';
                } else if (row.action_type === 'RESTORE') {
                    actionBadgeClass = 'badge-info';
                    actionBadgeText = 'ស្តារឡើងវិញ';
                }
                
                // Original Values Block
                const origBlock = `
                    <div class="comparison-box">
                        <span class="comparison-label">ទិន្នន័យដើម</span>
                        <strong>${row.original_description}</strong><br>
                        ប្រភេទ៖ ${row.original_type} | ក្រុម៖ ${row.original_category}<br>
                        ទឹកប្រាក់៖ <span style="color: #ef4444; font-weight: bold;">${row.original_amount}</span>
                    </div>
                `;

                // New Values Block
                let newBlock = '';
                if (row.action_type === 'UPDATE') {
                    newBlock = `
                        <div class="comparison-box" style="border-color: #a7f3d0;">
                            <span class="comparison-label" style="color: #065f46;">ទិន្នន័យថ្មី</span>
                            <strong>${row.new_description}</strong><br>
                            ប្រភេទ៖ ${row.new_type} | ក្រុម៖ ${row.new_category}<br>
                            ទឹកប្រាក់៖ <span style="color: #10b981; font-weight: bold;">${row.new_amount}</span>
                        </div>
                    `;
                } else if (row.action_type === 'RESTORE') {
                    newBlock = `
                        <div class="comparison-box" style="background-color: #e0f2fe; border-color: #7dd3fc; text-align: center; display: flex; justify-content: center; align-items: center; min-height: 50px;">
                            <strong style="color: #0369a1; font-size: 0.8rem;">🔄 បានស្តារឡើងវិញទៅ Dashboard</strong>
                        </div>
                    `;
                } else {
                    newBlock = `
                        <div class="comparison-box" style="background-color: #fee2e2; border-color: #fca5a5; text-align: center; display: flex; justify-content: center; align-items: center; min-height: 50px;">
                            <strong style="color: #991b1b; font-size: 0.8rem;">❌ ត្រូវបានលុបចេញពីប្រព័ន្ធ</strong>
                        </div>
                    `;
                }

                // Restore Button Block for deleted transactions
                let actionColumnHtml = '';
                if (row.action_type === 'DELETE') {
                    if (row.current_deleted_status === 1) {
                        actionColumnHtml = `
                            <button onclick="restoreTransaction(${row.transaction_id})" 
                                    style="background-color: #10b981; color: white; border: none; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-family: 'Kantumruy Pro', sans-serif; font-size: 0.8rem; font-weight: 600; display: flex; align-items: center; gap: 4px; transition: background 0.2s; box-shadow: 0 1px 3px rgba(0,0,0,0.1);"
                                    onmouseover="this.style.backgroundColor='#059669'"
                                    onmouseout="this.style.backgroundColor='#10b981'">
                                🔄 ស្តារឡើងវិញ
                            </button>
                        `;
                    } else {
                        actionColumnHtml = `<span class="badge" style="background-color: #d1fae5; color: #065f46; font-size: 0.8rem; padding: 4px 8px; border-radius: 50px; font-weight: 600;">✅ បានស្តាររួច</span>`;
                    }
                } else {
                    actionColumnHtml = `<span style="color: #9ca3af; font-size: 0.85rem;">-</span>`;
                }

                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td style="padding: 12px; color: #4b5563;"><small>${row.actioned_at}</small></td>
                    <td style="padding: 12px;"><strong>${row.owner}</strong></td>
                    <td style="padding: 12px;"><span class="badge ${actionBadgeClass}">${actionBadgeText}</span></td>
                    <td style="padding: 12px;">${origBlock}</td>
                    <td style="padding: 12px;">${newBlock}</td>
                    <td style="padding: 12px; text-align: center; vertical-align: middle;">${actionColumnHtml}</td>
                    <td style="padding: 12px;">👤 <strong>${row.actioned_by}</strong></td>
                `;
                tableBody.appendChild(tr);
            });
        } else {
            tableBody.innerHTML = `<tr><td colspan="6" style="text-align: center; padding: 30px; color: #ef4444;">❌ បរាជ័យ៖ ${result.message}</td></tr>`;
        }
    } catch (error) {
        console.error('Error fetching audit history:', error);
        tableBody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 30px; color: #ef4444;">❌ មិនអាចតភ្ជាប់ទៅកាន់ API បានឡើយ។</td></tr>';
    }
}

/**
 * ៨. មុខងារស្តារប្រតិបត្តិការដែលលុបចោលឡើងវិញ (Restore Deleted Transaction)
 */
async function restoreTransaction(transactionId) {
    showCustomConfirm("តើលោកអ្នកពិតជាចង់ស្តារប្រតិបត្តិការនេះឡើងវិញទៅកាន់ Dashboard ដែរឬទេ?", async () => {

    try {
        const response = await fetch(`${API_TRANSACTIONS_URL}?action=restore`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ transaction_id: transactionId })
        });

        const result = await response.json();

        if (result.status === 'success') {
            showToast(result.message, 'success');
            // ហៅឱ្យរៀបចំតារាងប្រវត្តិសវនកម្មឡើងវិញ
            loadAuditHistoryTable();
            // ធ្វើបច្ចុប្បន្នភាព Dashboard Metrics ផងដែរ ប្រសិនបើមាន Widgets
            if (document.getElementById('total-balance-khr')) {
                updateDashboardMetricsUI();
            }
        } else {
            showToast(result.message, 'error');
        }
    } catch (error) {
        console.error('Error restoring transaction:', error);
        showToast('មានបញ្ហាក្នុងការតភ្ជាប់ទៅកាន់ Server!', 'error');
    }
    });
}

/**
 * ៦. បង្កើត និងគ្រប់គ្រង Edit Modal នៅលើ Frontend
 */
function createEditModalMarkup() {
    if (document.getElementById('edit-transaction-modal')) return;

    const modalDiv = document.createElement('div');
    modalDiv.id = 'edit-transaction-modal';
    modalDiv.style.display = 'none';
    modalDiv.style.position = 'fixed';
    modalDiv.style.zIndex = '1000';
    modalDiv.style.left = '0';
    modalDiv.style.top = '0';
    modalDiv.style.width = '100%';
    modalDiv.style.height = '100%';
    modalDiv.style.backgroundColor = 'rgba(0,0,0,0.4)';
    modalDiv.style.justifyContent = 'center';
    modalDiv.style.alignItems = 'center';

    modalDiv.innerHTML = `
        <div style="background-color: #ffffff; padding: 2rem; border-radius: 12px; width: 450px; max-width: 90%; box-shadow: 0 4px 12px rgba(0,0,0,0.15); font-family: 'Kantumruy Pro', sans-serif;">
            <h3 style="margin-top: 0; margin-bottom: 1.5rem; color: #1f2937;">✏️ កែប្រែព័ត៌មានប្រតិបត្តិការ</h3>
            <form id="edit-transaction-form">
                <input type="hidden" id="edit-tx-id">
                
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; font-weight: bold; margin-bottom: 0.5rem; font-size: 0.9rem;">បរិយាយ / ឈ្មោះប្រតិបត្តិការ</label>
                    <input type="text" id="edit-tx-title" style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 6px; box-sizing: border-box;" required>
                </div>
                
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; font-weight: bold; margin-bottom: 0.5rem; font-size: 0.9rem;">ចំនួនទឹកប្រាក់</label>
                    <div style="display: flex; gap: 0.5rem;">
                        <input type="number" id="edit-tx-amount" step="any" style="flex: 1; padding: 8px; border: 1px solid #d1d5db; border-radius: 6px; box-sizing: border-box;" required>
                        <select id="edit-tx-currency" style="padding: 8px; border: 1px solid #d1d5db; border-radius: 6px;" required>
                            <option value="KHR">រៀល (៛)</option>
                            <option value="USD">ដុល្លារ ($)</option>
                        </select>
                    </div>
                </div>
                
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; font-weight: bold; margin-bottom: 0.5rem; font-size: 0.9rem;">ប្រភេទប្រតិបត្តិការ</label>
                    <select id="edit-tx-type" style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 6px;" required>
                        <option value="income">ចំណូល (Income)</option>
                        <option value="expense">ចំណាយ (Expense)</option>
                    </select>
                </div>
                
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; font-weight: bold; margin-bottom: 0.5rem; font-size: 0.9rem;">ប្រភេទក្រុម (Category)</label>
                    <input type="text" id="edit-tx-category" style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 6px; box-sizing: border-box;" required>
                </div>
                
                <div style="margin-bottom: 1.5rem;">
                    <label style="display: block; font-weight: bold; margin-bottom: 0.5rem; font-size: 0.9rem;">កាលបរិច្ឆេទ</label>
                    <input type="date" id="edit-tx-date" style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 6px; box-sizing: border-box;" required>
                </div>
                
                <div style="display: flex; gap: 0.5rem; justify-content: flex-end;">
                    <button type="button" onclick="closeEditTransactionModal()" style="padding: 8px 16px; border: 1px solid #d1d5db; background: #ffffff; border-radius: 6px; cursor: pointer;">បោះបង់</button>
                    <button type="submit" style="padding: 8px 16px; background: #2563eb; color: #ffffff; border: none; border-radius: 6px; cursor: pointer; font-weight: bold;">រក្សាទុកទិន្នន័យ</button>
                </div>
            </form>
        </div>
    `;

    document.body.appendChild(modalDiv);

    // Modal Submit listener
    document.getElementById('edit-transaction-form').addEventListener('submit', async (e) => {
        e.preventDefault();

        const payload = {
            transaction_id: parseInt(document.getElementById('edit-tx-id').value),
            title: document.getElementById('edit-tx-title').value,
            amount: parseFloat(document.getElementById('edit-tx-amount').value),
            currency: document.getElementById('edit-tx-currency').value,
            type: document.getElementById('edit-tx-type').value,
            category: document.getElementById('edit-tx-category').value,
            date: document.getElementById('edit-tx-date').value
        };

        try {
            const response = await fetch(API_TRANSACTIONS_URL, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            const result = await response.json();

            if (result.status === 'success') {
                showToast(result.message, 'success');
                closeEditTransactionModal();
                loadTransactionsTable(currentTxPage, txLimitPerPage, currentFilterDate);
                updateDashboardMetricsUI();
            } else {
                showToast(result.message, 'error');
            }
        } catch (error) {
            console.error('Error updating transaction:', error);
            showToast('មានបញ្ហាក្នុងការតភ្ជាប់ទៅកាន់ Server!', 'error');
        }
    });
}

function editTransactionClick(id, title, amount, currency, type, category, date) {
    createEditModalMarkup();
    
    document.getElementById('edit-tx-id').value = id;
    document.getElementById('edit-tx-title').value = decodeURIComponent(title);
    document.getElementById('edit-tx-amount').value = amount;
    document.getElementById('edit-tx-currency').value = currency;
    document.getElementById('edit-tx-type').value = type;
    document.getElementById('edit-tx-category').value = decodeURIComponent(category);
    document.getElementById('edit-tx-date').value = date;

    const modal = document.getElementById('edit-transaction-modal');
    if (modal) {
        modal.style.display = 'flex';
    }
}

function closeEditTransactionModal() {
    const modal = document.getElementById('edit-transaction-modal');
    if (modal) {
        modal.style.display = 'none';
    }
}


// --- ៧. Setup Listeners and Autoloaders ---
document.addEventListener('DOMContentLoaded', () => {
    // បង្កើត Modal Container ត្រៀមជាមុនសិន
    createEditModalMarkup();

    // ធ្វើបច្ចុប្បន្នភាព Metrics លើ Dashboard
    if (document.getElementById('total-balance-khr')) {
        updateDashboardMetricsUI();
    }
    
    // <b>ទាញយកបញ្ជីប្រតិបត្តិការទំព័រដំបូង</b>
    if (document.getElementById('transaction-table-body')) {
        loadTransactionsTable(1, 5);
    }

    // ភ្ជាប់ Event Listener សម្រាប់ឧបករណ៍ស្វែងរកថ្ងៃខែ (Date Filter Search)
    const dateInput = document.getElementById('search-date') || document.getElementById('filter-date');
    if (dateInput) {
        dateInput.addEventListener('change', (e) => {
            const selectedDate = e.target.value;
            loadTransactionsTable(1, txLimitPerPage, selectedDate);
        });
    }

    // Form បញ្ចូលប្រតិបត្តិការថ្មី (POST)
    const transactionForm = document.getElementById('transaction-form');
    if (transactionForm) {
        transactionForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            const payload = {
                title: document.getElementById('title').value,
                amount: parseFloat(document.getElementById('amount').value),
                currency: document.getElementById('currency').value,
                type: document.getElementById('type').value,
                category: document.getElementById('category').value,
                date: document.getElementById('date').value
            };

            try {
                const response = await fetch(API_TRANSACTIONS_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });

                const result = await response.json();

                if (result.status === 'success') {
                    showToast(result.message, 'success');
                    transactionForm.reset();
                    loadTransactionsTable(1, txLimitPerPage, currentFilterDate);
                    updateDashboardMetricsUI();
                } else {
                    showToast(result.message, 'error');
                }
            } catch (error) {
                console.error('Error adding transaction:', error);
                showToast('មានបញ្ហាក្នុងការតភ្ជាប់ទៅកាន់ Server!', 'error');
            }
        });
    }
});
