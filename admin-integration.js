/**
 * admin-integration-v3.js - Frontend API Integration with Transaction Metrics and Pagination
 * Part of the Khmer Payment Tracker and Financial Management System
 * 
 * This script connects your frontend UI with the secure backend APIs (api-v2.php & api-transactions.php).
 * Handles user authentication, admin promotion approvals, real-time metrics, paginated transactions,
 * and secure soft deletion.
 */

const API_BASE_URL = 'api-v2.php';
const API_TRANSACTIONS_URL = 'api-transactions-v4.php'; // ប្រើប្រាស់ API v4 ថ្មីដែលប្តូរឈ្មោះរួច

// រក្សាទុកស្ថានភាពបច្ចុប្បន្ននៃតារាងប្រតិបត្តិការ (Pagination State)
let currentTxPage = 1;
let txLimitPerPage = 5; // លំនាំដើម ៥ ប្រតិបត្តិការក្នុងមួយទំព័រដើម្បីឱ្យតារាងស្អាតបាត

/**
 * Helper function to handle standard fetch requests with JSON
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
        alert(`Login Failed: ${error.message}`);
        throw error;
    }
}

/**
 * 2. User Logout
 */
async function logoutUser() {
    try {
        const result = await sendRequest(`${API_BASE_URL}?action=logout`, 'POST');
        localStorage.removeItem('current_user');
        return result;
    } catch (error) {
        console.error('Logout failed:', error);
    }
}

/**
 * 3. Submit Admin Promotion Request (Maker Action)
 */
async function requestAdminPromotion(targetUserId) {
    try {
        const result = await sendRequest(`${API_BASE_URL}?action=request_admin`, 'POST', {
            target_user_id: parseInt(targetUserId)
        });
        alert(result.message);
        return result;
    } catch (error) {
        alert(`Request Failed: ${error.message}`);
        throw error;
    }
}

/**
 * 4. Approve or Reject Admin Promotion (Checker Action)
 */
async function handleAdminDecision(requestId, decision) {
    try {
        const result = await sendRequest(`${API_BASE_URL}?action=approve_admin`, 'POST', {
            request_id: parseInt(requestId),
            decision: decision
        });
        alert(result.message);
        return result;
    } catch (error) {
        alert(`Decision Failed: ${error.message}`);
        throw error;
    }
}

/**
 * 5. Retrieve Pending Admin Promotion Requests
 */
async function fetchPendingApprovals() {
    try {
        const result = await sendRequest(`${API_BASE_URL}?action=approvals`, 'GET');
        return result.data;
    } catch (error) {
        console.error('Failed to load pending approvals:', error);
        return [];
    }
}

/**
 * 6. Retrieve All Registered Users
 */
async function fetchUsers() {
    try {
        const result = await sendRequest(`${API_BASE_URL}?action=users`, 'GET');
        return result.data;
    } catch (error) {
        console.error('Failed to load users:', error);
        return [];
    }
}

/**
 * 7. Retrieve System Security Audit Logs
 */
async function fetchAuditLogs() {
    try {
        const result = await sendRequest(`${API_BASE_URL}?action=audit_logs`, 'GET');
        return result.data;
    } catch (error) {
        console.error('Failed to load audit logs:', error);
        return [];
    }
}

/**
 * 8. Retrieve Transaction Metrics (Balances, Incomes, Expenses)
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


// --- UI RENDERING HELPERS ---

/**
 * Render dynamic real-time widgets/metrics on the Dashboard
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
 * ទាញយក និងបង្ហាញតារាងប្រតិបត្តិការជាមួយប្រព័ន្ធបែងចែកទំព័រ (Paginated Transactions Table)
 */
async function loadTransactionsTable(page = 1, limit = 5) {
    const tableBody = document.getElementById('transaction-table-body');
    if (!tableBody) return;

    currentTxPage = page;
    txLimitPerPage = limit;

    tableBody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 20px;">កំពុងទាញយកទិន្នន័យប្រតិបត្តិការ...</td></tr>';

    try {
        const response = await fetch(`${API_TRANSACTIONS_URL}?page=${page}&limit=${limit}`);
        const result = await response.json();

        if (result.status === 'success') {
            tableBody.innerHTML = ''; // សម្អាតតារាងចាស់

            if (result.data.length === 0) {
                tableBody.innerHTML = `<tr><td colspan="6" style="text-align: center; padding: 20px; color: #6b7280;">មិនទាន់មានប្រតិបត្តិការនៅឡើយទេ។</td></tr>`;
                renderPaginationControls({ total_pages: 0, current_page: 0 });
                return;
            }

            // បង្ហាញជួរទិន្នន័យនីមួយៗ
            result.data.forEach(row => {
                const typeColor = (row.raw_type === 'income') ? '#10b981' : '#ef4444';
                const tr = document.createElement('tr');
                tr.style.borderBottom = '1px solid #e5e7eb';
                
                // បង្ហាញប៊ូតុងលុប (Delete) សម្រាប់តែ Super Admin ប៉ុណ្ណោះ
                const currentUser = JSON.parse(localStorage.getItem('current_user') || '{}');
                const deleteActionHtml = (currentUser.role === 'super_admin')
                    ? `<button onclick="deleteTransaction(${row.id})" style="background: none; border: none; color: #ef4444; cursor: pointer; font-size: 1.1rem; padding: 4px 8px;" title="លុបប្រតិបត្តិការ">🗑️</button>`
                    : `<span style="color: #9ca3af;">⋯</span>`;

                tr.innerHTML = `
                    <td style="padding: 12px; font-size: 0.9rem; color: #4b5563;">${row.date}</td>
                    <td style="padding: 12px; font-weight: 600; color: #1f2937;">${row.description}</td>
                    <td style="padding: 12px; color: #6b7280;">${row.creator}</td>
                    <td style="padding: 12px;"><span style="color: ${typeColor}; font-weight: bold;">${row.type}</span></td>
                    <td style="padding: 12px; font-weight: bold; color: #1f2937;">${row.amount}</td>
                    <td style="padding: 12px; text-align: center;">${deleteActionHtml}</td>
                `;
                tableBody.appendChild(tr);
            });

            // បង្កើត និងបង្ហាញប៊ូតុងគ្រប់គ្រងទំព័រ (Pagination Buttons)
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
    
    // ប្រសិនបើមិនទាន់មាន Container ក្នុង HTML ទេ វានឹងបង្កើតវាដោយស្វ័យប្រវត្តិក្បែរតារាង
    if (!container) {
        const table = document.querySelector('.transaction-table');
        if (!table) return;
        
        container = document.createElement('div');
        container.id = 'pagination-controls';
        table.parentNode.insertBefore(container, table.nextSibling);
    }

    const { current_page, total_pages, total_records } = pagination;

    // សម្អាត HTML ចាស់
    container.innerHTML = '';
    
    // កំណត់ស្ទីលសម្រាប់ Container ឱ្យស្អាតបាត
    container.style.display = 'flex';
    container.style.justify = 'space-between';
    container.style.alignItems = 'center';
    container.style.marginTop = '1rem';
    container.style.padding = '0.5rem 1rem';
    container.style.fontFamily = "'Kantumruy Pro', sans-serif";

    if (total_pages <= 1) {
        container.style.display = 'none'; // លាក់បើសិនជាមានតែ ១ ទំព័រ
        return;
    }

    // ១. បង្ហាញព័ត៌មានចំនួនកំណត់ត្រា (Info Panel)
    const infoSpan = document.createElement('span');
    infoSpan.style.fontSize = '0.9rem';
    infoSpan.style.color = '#4b5563';
    infoSpan.innerText = `ទំព័រទី ${current_page} នៃ ${total_pages} (សរុប ${total_records} ប្រតិបត្តិការ)`;
    container.appendChild(infoSpan);

    // ២. បង្កើតប៊ូតុងផ្លាស់ប្តូរទំព័រ (Buttons Wrapper)
    const btnWrapper = document.createElement('div');
    btnWrapper.style.display = 'flex';
    btnWrapper.style.gap = '6px';

    // ប៊ូតុងថយក្រោយ (Previous)
    const prevBtn = document.createElement('button');
    prevBtn.innerText = '« មុន';
    stylePaginationButton(prevBtn, current_page === 1);
    if (current_page > 1) {
        prevBtn.addEventListener('click', () => loadTransactionsTable(current_page - 1, txLimitPerPage));
    }
    btnWrapper.appendChild(prevBtn);

    // ប៊ូតុងលេខទំព័រនីមួយៗ
    for (let i = 1; i <= total_pages; i++) {
        const pageBtn = document.createElement('button');
        pageBtn.innerText = i;
        stylePaginationButton(pageBtn, false, i === current_page);
        pageBtn.addEventListener('click', () => loadTransactionsTable(i, txLimitPerPage));
        btnWrapper.appendChild(pageBtn);
    }

    // ប៊ូតុងទៅមុខ (Next)
    const nextBtn = document.createElement('button');
    nextBtn.innerText = 'បន្ទាប់ »';
    stylePaginationButton(nextBtn, current_page === total_pages);
    if (current_page < total_pages) {
        nextBtn.addEventListener('click', () => loadTransactionsTable(current_page + 1, txLimitPerPage));
    }
    btnWrapper.appendChild(nextBtn);

    container.appendChild(btnWrapper);
}

/**
 * ជំនួយការតុបតែងស្ទីលប៊ូតុង Pagination (Button Styling Helper)
 */
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
 * លុបប្រតិបត្តិការដោយសុវត្ថិភាព (Soft Delete UI Action)
 */
async function deleteTransaction(transactionId) {
    if (!confirm("តើលោកអ្នកពិតជាចង់លុបប្រតិបត្តិការនេះមែនទេ? សកម្មភាពនេះនឹងត្រូវបានកត់ត្រាក្នុងប្រព័ន្ធសវនកម្ម។")) {
        return;
    }

    try {
        const response = await fetch(API_TRANSACTIONS_URL, {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ transaction_id: transactionId })
        });

        const result = await response.json();

        if (result.status === 'success') {
            alert('🎉 ' + result.message);
            //  reload ទំព័របច្ចុប្បន្នឡើងវិញ
            loadTransactionsTable(currentTxPage, txLimitPerPage);
            updateDashboardMetricsUI();
        } else {
            alert('❌ ' + result.message);
        }
    } catch (error) {
        console.error('Error deleting transaction:', error);
        alert('❌ បរាជ័យក្នុងការតភ្ជាប់ទៅកាន់ម៉ាស៊ីនបម្រើ!');
    }
}

/**
 * Render dynamic approvals table
 */
async function renderPendingApprovalsTable(containerId) {
    const container = document.getElementById(containerId);
    if (!container) return;

    container.innerHTML = '<p class="loading">កំពុងទាញយកទិន្នន័យសំណើ...</p>';
    
    const approvals = await fetchPendingApprovals();
    const currentUser = JSON.parse(localStorage.getItem('current_user') || '{}');

    if (approvals.length === 0) {
        container.innerHTML = '<p class="no-data">គ្មានសំណើដែលត្រូវអនុម័តឡើយ។</p>';
        return;
    }

    let html = `
        <table class="auth-table">
            <thead>
                <tr>
                    <th>អ្នកស្នើសុំ (Maker)</th>
                    <th>អ្នកប្រើប្រាស់ដែលត្រូវតម្លើង (Target)</th>
                    <th>កាលបរិច្ឆេទស្នើ</th>
                    <th>សកម្មភាព (Actions)</th>
                </tr>
            </thead>
            <tbody>
    `;

    approvals.forEach(req => {
        const isOwnRequest = (req.requested_by === currentUser.username);
        const actionButtons = isOwnRequest 
            ? `<span class="badge warning">រង់ចាំ Admin ផ្សេងអនុម័ត</span>`
            : `
                <button onclick="approveRequest(${req.id})" class="btn-approve">យល់ព្រម</button>
                <button onclick="rejectRequest(${req.id})" class="btn-reject">បដិសេធ</button>
            `;

        html += `
            <tr>
                <td><strong>${req.requested_by_username || req.requested_by}</strong></td>
                <td><span class="user-target">${req.target_username}</span></td>
                <td>${req.created_at}</td>
                <td>${actionButtons}</td>
            </tr>
        `;
    });

    html += '</tbody></table>';
    container.innerHTML = html;
}

/**
 * Render system security audit logs
 */
async function renderAuditLogsTable(containerId) {
    const container = document.getElementById(containerId);
    if (!container) return;

    container.innerHTML = '<p class="loading">កំពុងទាញយកកំណត់ត្រាសវនកម្ម...</p>';
    const logs = await fetchAuditLogs();

    if (logs.length === 0) {
        container.innerHTML = '<p class="no-data">គ្មានកំណត់ត្រាសកម្មភាពឡើយ។</p>';
        return;
    }

    let html = `
        <table class="audit-table">
            <thead>
                <tr>
                    <th>ពេលវេលា</th>
                    <th>អ្នកធ្វើសកម្មភាព</th>
                    <th>សកម្មភាព (Action)</th>
                    <th>ព័ត៌មានលម្អិត</th>
                    <th>IP Address</th>
                </tr>
            </thead>
            <tbody>
    `;

    logs.forEach(log => {
        let actionClass = '';
        if (log.action.includes('APPROVE')) actionClass = 'log-success';
        else if (log.action.includes('REJECT') || log.action.includes('VIOLATION')) actionClass = 'log-danger';
        else if (log.action.includes('REQUEST')) actionClass = 'log-info';

        html += `
            <tr class="${actionClass}">
                <td><small>${log.created_at}</small></td>
                <td><strong>${log.operator}</strong></td>\n                <td><span class="badge-action">${log.action}</span></td>
                <td>${log.details}</td>
                <td><code>${log.ip_address}</code></td>
            </tr>
        `;
    });

    html += '</tbody></table>';
    container.innerHTML = html;
}

// Hook dynamic load into DOM state
document.addEventListener('DOMContentLoaded', () => {
    // ធ្វើបច្ចុប្បន្នភាព Dashboard Widgets
    if (document.getElementById('total-balance-khr')) {
        updateDashboardMetricsUI();
    }
    // ដំណើរការទាញយកតារាងប្រតិបត្តិការដំបូង (ទំព័រទី ១)
    if (document.getElementById('transaction-table-body')) {
        loadTransactionsTable(1, 5);
    }
});
