/**
 * admin-integration.js - Frontend API Integration for Maker-Checker Authorization
 * 
 * This script provides modular JavaScript functions to connect your frontend UI (HTML/PHP pages)
 * with the secure backend API (api-v2.php). It handles session logins, promoting users (Maker),
 * approving requests (Checker), and loading security audit logs.
 */

const API_BASE_URL = 'api-v2.php';

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
    
    if (bodyData && (method === 'POST' || method === 'PUT')) {
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
        // Save user role and name to localStorage for UI rendering
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
 * Only Admins or Super Admins can submit requests
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
 * Only Super Admin can approve, and they CANNOT approve their own request (Separation of Duties)
 */
async function handleAdminDecision(requestId, decision) {
    // decision must be 'approve' or 'reject'
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
 * 5. Retrieve Pending Admin Promotion Requests (Checker View)
 */
async function fetchPendingApprovals() {
    try {
        const result = await sendRequest(`${API_BASE_URL}?action=approvals`, 'GET');
        return result.data; // Array of pending requests
    } catch (error) {
        console.error('Failed to load pending approvals:', error);
        return [];
    }
}

/**
 * 6. Retrieve All Registered Users (For selection list)
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


// --- UI RENDERING HELPERS (Examples to bind to your UI) ---

/**
 * Render the pending approvals table dynamically
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
        // Enforce visual indicator for separation of duties
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
// បន្ថែមកូដនេះទៅក្នុងព្រឹត្តិការណ៍ DOMContentLoaded របស់ admin-integration.js របស់អ្នក
document.addEventListener('DOMContentLoaded', () => {
    const exportCsvBtn = document.getElementById('btn-export-csv');
    
    if (exportCsvBtn) {
        exportCsvBtn.addEventListener('click', () => {
            // បញ្ជូនអ្នកប្រើប្រាស់ទៅកាន់កូដទាញយក CSV ដោយផ្ទាល់
            // យន្តការ Session/RBAC ក្នុង export-csv.php នឹងកំណត់ដែនសិទ្ធិទិន្នន័យដោយស្វ័យប្រវត្តិ
            window.location.href = 'export-csv.php';
        });
    }
});

/**
 * Render the system security audit logs
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
                <td><strong>${log.operator}</strong></td>
                <td><span class="badge-action">${log.action}</span></td>
                <td>${log.details}</td>
                <td><code>${log.ip_address}</code></td>
            </tr>
        `;
    });

    html += '</tbody></table>';
    container.innerHTML = html;
}
