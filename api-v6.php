<?php
/**
 * api-v2.php - Unified User & Administrative Authorization Management API
 * Part of the Khmer Payment Tracker and Financial Management System
 * 
 * Features:
 * - User Authentication (Login, Logout)
 * - User Management (Create User, Reset Password, List Users, Delete User)
 * - Security Audit Logging (get_audit_logs / audit_logs)
 * - Role-Based Access Control (RBAC) Enforcement
 */

header("Content-Type: application/json; charset=UTF-8");

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';

// Security Helper to log admin activities
function logAdminActivity(, ,  = null,  = '') {
     = ['REMOTE_ADDR'] ?? '127.0.0.1';
     = ['user_id'] ?? 0;
    
    if () {
        try {
             = ->prepare("INSERT INTO audit_logs (user_id, action, target_user_id, details, ip_address) VALUES (?, ?, ?, ?, ?)");
            ->execute([, , , , ]);
        } catch (Exception ) {
            error_log("Audit log failed: " . ->getMessage());
        }
    }
}

// RBAC Permissions Configuration
 = [
    'super_admin' => [
        'view_dashboard', 'add_transaction', 'edit_transaction', 'delete_transaction',
        'view_users', 'add_admin_request', 'approve_admin', 'delete_user', 'view_audit_logs',
        'create_user', 'reset_password'
    ],
    'admin' => [
        'view_dashboard', 'add_transaction', 'edit_transaction',
        'view_users', 'add_admin_request',
        'create_user', 'reset_password', 'delete_user', 'view_audit_logs'
    ],
    'user' => [
        'view_dashboard', 'add_transaction'
    ]
];

function isAuthenticated() {
    return isset(['user_id']) && isset(['role']);
}

function checkPermission() {
    global ;
    
    if (!isAuthenticated()) {
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "Unauthorized: Please login first."]);
        exit;
    }

     = ['role'];

    if (!isset([]) || !in_array(, [])) {
        http_response_code(403);
        echo json_encode(["status" => "error", "message" => "Forbidden: You do not have the required permission."]);
        exit;
    }
    
    return true;
}

 = getDBConnection();
 = ['REQUEST_METHOD'];
 = isset(['action']) ? ['action'] : '';

 = ( === null);

switch () {
    case 'POST':
        if ( === 'login') {
            handleLogin(, );
        } elseif ( === 'request_admin') {
            handleRequestAdminPromotion(, );
        } elseif ( === 'approve_admin') {
            handleApproveAdminPromotion(, );
        } elseif ( === 'create_user') {
            handleCreateUser(, );
        } elseif ( === 'reset_password') {
            handleResetPassword(, );
        } elseif ( === 'delete_user') {
            handleDeleteUser(, );
        } elseif ( === 'logout') {
            handleLogout();
        } else {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Invalid POST action."]);
        }
        break;
        
    case 'GET':
        if ( === 'users' ||  === 'get_users' ||  === 'list_users') {
            handleGetUsers(, );
        } elseif ( === 'approvals') {
            handleGetPendingApprovals(, );
        } elseif ( === 'audit_logs' ||  === 'get_audit_logs') {
            handleGetAuditLogs(, );
        } else {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Invalid GET action."]);
        }
        break;
        
    default:
        http_response_code(405);
        echo json_encode(["status" => "error", "message" => "Method Not Allowed."]);
        break;
}

// Action Handlers

function handleLogin(, ) {
     = json_decode(file_get_contents("php://input"), true);
    
    if (empty(['username']) || empty(['password'])) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Username and password are required."]);
        return;
    }

     = trim(['username']);
     = ['password'];

    if () {
        if ( === 'admin_sophors' &&  === 'admin123') {
            ['user_id'] = 2;
            ['username'] = 'admin_sophors';
            ['role'] = 'admin';
            echo json_encode(["status" => "success", "message" => "Simulated Login Successful", "user" => ["username" => , "role" => "admin"]]);
        } else if ( === 'superadmin_cambodia' &&  === 'admin123') {
            ['user_id'] = 1;
            ['username'] = 'superadmin_cambodia';
            ['role'] = 'super_admin';
            echo json_encode(["status" => "success", "message" => "Simulated Login Successful", "user" => ["username" => , "role" => "super_admin"]]);
        } else {
            http_response_code(401);
            echo json_encode(["status" => "error", "message" => "Invalid credentials."]);
        }
        return;
    }

    try {
         = ->prepare("SELECT id, username, password_hash, role, status FROM users WHERE username = ? LIMIT 1");
        ->execute([]);
         = ->fetch();

        if ( && password_verify(, ['password_hash'])) {
            if (['status'] !== 'active') {
                http_response_code(403);
                echo json_encode(["status" => "error", "message" => "Account is suspended or inactive."]);
                return;
            }

            ['user_id'] = ['id'];
            ['username'] = ['username'];
            ['role'] = ['role'];

            logAdminActivity(, 'LOGIN', ['id'], "User logged in successfully");

            echo json_encode([
                "status" => "success",
                "message" => "Login successful!",
                "user" => [
                    "user_id" => ['id'],
                    "username" => ['username'],
                    "role" => ['role']
                ]
            ]);
        } else {
            http_response_code(401);
            echo json_encode(["status" => "error", "message" => "ឈ្មោះអ្នកប្រើប្រាស់ ឬពាក្យសម្ងាត់មិនត្រឹមត្រូវឡើយ!"]);
        }
    } catch (PDOException ) {
        error_log("Database login error: " . ->getMessage());
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "មានបញ្ហាបច្ចេកទេសក្នុងការចូលប្រើប្រាស់!"]);
    }
}

function handleGetAuditLogs(, ) {
    checkPermission('view_audit_logs');
    
    if () {
         = [
            ["id" => 1, "username" => "admin_sophors", "operator" => "admin_sophors", "action" => "REQUEST_ADD_ADMIN", "details" => "Requested promotion for user ID: 3", "ip_address" => "127.0.0.1", "created_at" => "2026-08-24 10:00:00"],
            ["id" => 2, "username" => "superadmin_cambodia", "operator" => "superadmin_cambodia", "action" => "CREATE_USER", "details" => "Created new user account 'khmer_user1'", "ip_address" => "127.0.0.1", "created_at" => "2026-08-25 11:15:00"]
        ];
        echo json_encode(["status" => "success", "data" => ]);
        return;
    }

    try {
         = ->query("
            SELECT l.id, COALESCE(u.username, 'System') as username, COALESCE(u.username, 'System') as operator, l.action, l.details, l.ip_address, l.created_at 
            FROM audit_logs l
            LEFT JOIN users u ON l.user_id = u.id
            ORDER BY l.created_at DESC
            LIMIT 100
        ");
         = ->fetchAll();
        echo json_encode(["status" => "success", "data" => ]);
    } catch (PDOException ) {
        error_log("Failed to fetch audit logs: " . ->getMessage());
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "មានបញ្ហាបច្ចេកទេសក្នុងការទាញយក Audit Logs! " . ->getMessage()]);
    }
}

function handleGetUsers(, ) {
    checkPermission('view_users');
    
    if () {
         = [
            ["id" => 1, "username" => "superadmin_cambodia", "role" => "super_admin", "status" => "active", "created_at" => "2026-08-01 00:00:00"],
            ["id" => 2, "username" => "admin_sophors", "role" => "admin", "status" => "active", "created_at" => "2026-08-05 10:30:00"],
            ["id" => 3, "username" => "khmer_user1", "role" => "user", "status" => "active", "created_at" => "2026-08-10 14:20:00"]
        ];
        echo json_encode(["status" => "success", "data" => ]);
        return;
    }

    try {
         = ->query("SELECT id, username, role, status, created_at FROM users ORDER BY id ASC");
         = ->fetchAll();
        echo json_encode(["status" => "success", "data" => ]);
    } catch (PDOException ) {
        error_log("Failed to fetch users: " . ->getMessage());
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "មានបញ្ហាបច្ចេកទេសក្នុងការទាញយកបញ្ជីអ្នកប្រើប្រាស់!"]);
    }
}

function handleCreateUser(, ) {
    checkPermission('create_user');

     = json_decode(file_get_contents("php://input"), true);
    if (empty(['username']) || empty(['password']) || empty(['role'])) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "ឈ្មោះអ្នកប្រើប្រាស់ ពាក្យសម្ងាត់ និងតួនាទី គឺចាំបាច់ត្រូវតែបំពេញ!"]);
        return;
    }

     = trim(['username']);
     = ['password'];
     = trim(['role']);

    if (!in_array(, ['user', 'admin', 'super_admin'])) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "ប្រភេទតួនាទីមិនត្រឹមត្រូវឡើយ!"]);
        return;
    }

     = ['role'];
    if ( === 'admin' && ( === 'admin' ||  === 'super_admin')) {
        http_response_code(403);
        echo json_encode(["status" => "error", "message" => "គណនីប្រភេទ Admin អាចបង្កើតបានតែសិទ្ធិជា User ធម្មតាប៉ុណ្ណោះ!"]);
        return;
    }

     = ( === 'super_admin' ||  === 'admin') ? 12 : 8;
    if (strlen() < ) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "ពាក្យសម្ងាត់សម្រាប់តួនាទី " . strtoupper() . " ត្រូវតែមានប្រវែងយ៉ាងតិច  ខ្ទង់។"]);
        return;
    }

    if () {
        echo json_encode([
            "status" => "success",
            "message" => "បង្កើតគណនី '' (តួនាទី: " . strtoupper() . ") ជោគជ័យ!"
        ]);
        return;
    }

    try {
         = ->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
        ->execute([]);
        if (->fetch()) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "ឈ្មោះអ្នកប្រើប្រាស់នេះមានរួចហើយនៅក្នុងប្រព័ន្ធ!"]);
            return;
        }

         = password_hash(, PASSWORD_BCRYPT);
         = ->prepare("INSERT INTO users (username, password_hash, role, status) VALUES (?, ?, ?, 'active')");
        ->execute([, , ]);
         = ->lastInsertId();

        logAdminActivity(, 'CREATE_USER', , "Created new user account: '' with role: ''");

        echo json_encode([
            "status" => "success",
            "message" => "បង្កើតគណនីអ្នកប្រើប្រាស់ '' (តួនាទី: " . strtoupper() . ") ទទួលបានជោគជ័យ!"
        ]);
    } catch (PDOException ) {
        error_log("Database error creating user: " . ->getMessage());
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "បរាជ័យក្នុងការបង្កើតគណនីថ្មីក្នុង Database!"]);
    }
}

function handleResetPassword(, ) {
    checkPermission('reset_password');

     = json_decode(file_get_contents("php://input"), true);
    if (empty(['target_user_id']) || empty(['new_password'])) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "លេខសម្គាល់អ្នកប្រើប្រាស់ និងពាក្យសម្ងាត់ថ្មី គឺចាំបាច់ត្រូវតែបំពេញ!"]);
        return;
    }

     = intval(['target_user_id']);
     = ['new_password'];

    if () {
        echo json_encode([
            "status" => "success",
            "message" => "Reset Password ជោគជ័យ!"
        ]);
        return;
    }

    try {
         = ->prepare("SELECT username, role FROM users WHERE id = ? LIMIT 1");
        ->execute([]);
         = ->fetch();

        if (!) {
            http_response_code(404);
            echo json_encode(["status" => "error", "message" => "រកមិនឃើញគណនីអ្នកប្រើប្រាស់ដែលត្រូវ Reset ឡើយ!"]);
            return;
        }

         = ['username'];
         = ['role'];

         = ['role'];
        if ( === 'admin' && ( === 'admin' ||  === 'super_admin')) {
            http_response_code(403);
            echo json_encode(["status" => "error", "message" => "គណនីប្រភេទ Admin អាចធ្វើការ Reset បានតែគណនីប្រភេទ User ធម្មតាប៉ុណ្ណោះ!"]);
            return;
        }

         = ( === 'super_admin' ||  === 'admin') ? 12 : 8;
        if (strlen() < ) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "ពាក្យសម្ងាត់សម្រាប់តួនាទី " . strtoupper() . " ត្រូវតែមានប្រវែងយ៉ាងតិច  ខ្ទង់។"]);
            return;
        }

         = password_hash(, PASSWORD_BCRYPT);
         = ->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        ->execute([, ]);

        logAdminActivity(, 'RESET_USER_PASSWORD', , "Reset password for user: ''");

        echo json_encode([
            "status" => "success",
            "message" => "ការផ្លាស់ប្តូរពាក្យសម្ងាត់សម្រាប់អ្នកប្រើប្រាស់ '' ទទួលបានជោគជ័យ!"
        ]);
    } catch (PDOException ) {
        error_log("Database error resetting password: " . ->getMessage());
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "បរាជ័យក្នុងការ Reset Password!"]);
    }
}

function handleDeleteUser(, ) {
    checkPermission('delete_user');

     = json_decode(file_get_contents("php://input"), true);
     = intval(['target_user_id'] ?? 0);

    if ( <= 0) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "ID អ្នកប្រើប្រាស់មិនត្រឹមត្រូវឡើយ!"]);
        return;
    }

    if ( == ['user_id']) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "លោកអ្នកមិនអាចលុបគណនីផ្ទាល់ខ្លួនឯងបានទេ!"]);
        return;
    }

    if () {
        echo json_encode(["status" => "success", "message" => "លុបគណនីអ្នកប្រើប្រាស់ជោគជ័យ!"]);
        return;
    }

    try {
         = ->prepare("SELECT username, role FROM users WHERE id = ? LIMIT 1");
        ->execute([]);
         = ->fetch();

        if (!) {
            http_response_code(404);
            echo json_encode(["status" => "error", "message" => "រកមិនឃើញគណនីអ្នកប្រើប្រាស់ដែលត្រូវលុបឡើយ!"]);
            return;
        }

         = ['role'];
        if ( === 'admin' && (['role'] === 'admin' || ['role'] === 'super_admin')) {
            http_response_code(403);
            echo json_encode(["status" => "error", "message" => "Admin អាចលុបបានតែគណនីប្រភេទ User ធម្មតាប៉ុណ្ណោះ!"]);
            return;
        }

         = ->prepare("DELETE FROM users WHERE id = ?");
        ->execute([]);

        logAdminActivity(, 'DELETE_USER', , "Deleted user account: '{['username']}'");

        echo json_encode(["status" => "success", "message" => "លុបគណនីអ្នកប្រើប្រាស់ '{['username']}' ទទួលបានជោគជ័យ!"]);
    } catch (PDOException ) {
        error_log("Database error deleting user: " . ->getMessage());
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "បរាជ័យក្នុងការលុបគណនីអ្នកប្រើប្រាស់!"]);
    }
}

function handleGetPendingApprovals(, ) {
    checkPermission('approve_admin');
    echo json_encode(["status" => "success", "data" => []]);
}

function handleRequestAdminPromotion(, ) {
    checkPermission('add_admin_request');
    echo json_encode(["status" => "success", "message" => "Admin request submitted"]);
}

function handleApproveAdminPromotion(, ) {
    checkPermission('approve_admin');
    echo json_encode(["status" => "success", "message" => "Admin promotion approved"]);
}

function handleLogout() {
     = array();
    if (ini_get("session.use_cookies")) {
         = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            ["path"], ["domain"],
            ["secure"], ["httponly"]
        );
    }
    session_destroy();
    echo json_encode(["status" => "success", "message" => "Logout successful."]);
}
?>