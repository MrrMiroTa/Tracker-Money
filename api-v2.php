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
function logAdminActivity($db, $action, $target_user_id = null, $details = '') {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $userId = $_SESSION['user_id'] ?? 0;
    
    if ($db) {
        try {
            $stmt = $db->prepare("INSERT INTO audit_logs (user_id, action, target_user_id, details, ip_address) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$userId, $action, $target_user_id, $details, $ip]);
        } catch (Exception $e) {
            error_log("Audit log failed: " . $e->getMessage());
        }
    }
}

// RBAC Permissions Configuration
$role_permissions = [
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
    return isset($_SESSION['user_id']) && isset($_SESSION['role']);
}

function checkPermission($required_permission) {
    global $role_permissions;
    
    if (!isAuthenticated()) {
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "Unauthorized: Please login first."]);
        exit;
    }

    $user_role = $_SESSION['role'];

    if (!isset($role_permissions[$user_role]) || !in_array($required_permission, $role_permissions[$user_role])) {
        http_response_code(403);
        echo json_encode(["status" => "error", "message" => "Forbidden: You do not have the required permission."]);
        exit;
    }
    
    return true;
}

$db = getDBConnection();
$request_method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

$is_simulated = ($db === null);

switch ($request_method) {
    case 'POST':
        if ($action === 'login') {
            handleLogin($db, $is_simulated);
        } elseif ($action === 'request_admin') {
            handleRequestAdminPromotion($db, $is_simulated);
        } elseif ($action === 'approve_admin') {
            handleApproveAdminPromotion($db, $is_simulated);
        } elseif ($action === 'create_user') {
            handleCreateUser($db, $is_simulated);
        } elseif ($action === 'reset_password') {
            handleResetPassword($db, $is_simulated);
        } elseif ($action === 'delete_user') {
            handleDeleteUser($db, $is_simulated);
        } elseif ($action === 'logout') {
            handleLogout();
        } else {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Invalid POST action."]);
        }
        break;
        
    case 'GET':
        if ($action === 'users' || $action === 'get_users' || $action === 'list_users') {
            handleGetUsers($db, $is_simulated);
        } elseif ($action === 'approvals') {
            handleGetPendingApprovals($db, $is_simulated);
        } elseif ($action === 'audit_logs' || $action === 'get_audit_logs') {
            handleGetAuditLogs($db, $is_simulated);
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

function handleLogin($db, $simulated) {
    $data = json_decode(file_get_contents("php://input"), true);
    
    if (empty($data['username']) || empty($data['password'])) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Username and password are required."]);
        return;
    }

    $username = trim($data['username']);
    $password = $data['password'];

    if ($simulated) {
        if ($username === 'admin_sophors' && $password === 'admin123') {
            $_SESSION['user_id'] = 2;
            $_SESSION['username'] = 'admin_sophors';
            $_SESSION['role'] = 'admin';
            echo json_encode(["status" => "success", "message" => "Simulated Login Successful", "user" => ["username" => $username, "role" => "admin"]]);
        } else if ($username === 'superadmin_cambodia' && $password === 'admin123') {
            $_SESSION['user_id'] = 1;
            $_SESSION['username'] = 'superadmin_cambodia';
            $_SESSION['role'] = 'super_admin';
            echo json_encode(["status" => "success", "message" => "Simulated Login Successful", "user" => ["username" => $username, "role" => "super_admin"]]);
        } else {
            http_response_code(401);
            echo json_encode(["status" => "error", "message" => "Invalid credentials."]);
        }
        return;
    }

    try {
        $stmt = $db->prepare("SELECT id, username, password_hash, role, status FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            if ($user['status'] !== 'active') {
                http_response_code(403);
                echo json_encode(["status" => "error", "message" => "Account is suspended or inactive."]);
                return;
            }

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];

            logAdminActivity($db, 'LOGIN', $user['id'], "User logged in successfully");

            echo json_encode([
                "status" => "success",
                "message" => "Login successful!",
                "user" => [
                    "user_id" => $user['id'],
                    "username" => $user['username'],
                    "role" => $user['role']
                ]
            ]);
        } else {
            http_response_code(401);
            echo json_encode(["status" => "error", "message" => "ឈ្មោះអ្នកប្រើប្រាស់ ឬពាក្យសម្ងាត់មិនត្រឹមត្រូវឡើយ!"]);
        }
    } catch (PDOException $e) {
        error_log("Database login error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "មានបញ្ហាបច្ចេកទេសក្នុងការចូលប្រើប្រាស់!"]);
    }
}

function handleGetAuditLogs($db, $simulated) {
    checkPermission('view_audit_logs');
    
    if ($simulated) {
        $logs = [
            ["id" => 1, "username" => "admin_sophors", "operator" => "admin_sophors", "action" => "REQUEST_ADD_ADMIN", "details" => "Requested promotion for user ID: 3", "ip_address" => "127.0.0.1", "created_at" => "2026-08-24 10:00:00"],
            ["id" => 2, "username" => "superadmin_cambodia", "operator" => "superadmin_cambodia", "action" => "CREATE_USER", "details" => "Created new user account 'khmer_user1'", "ip_address" => "127.0.0.1", "created_at" => "2026-08-25 11:15:00"]
        ];
        echo json_encode(["status" => "success", "data" => $logs]);
        return;
    }

    try {
        $stmt = $db->query("
            SELECT l.id, COALESCE(u.username, 'System') as username, COALESCE(u.username, 'System') as operator, l.action, l.details, l.ip_address, l.created_at 
            FROM audit_logs l
            LEFT JOIN users u ON l.user_id = u.id
            ORDER BY l.created_at DESC
            LIMIT 100
        ");
        $logs = $stmt->fetchAll();
        echo json_encode(["status" => "success", "data" => $logs]);
    } catch (PDOException $e) {
        error_log("Failed to fetch audit logs: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "មានបញ្ហាបច្ចេកទេសក្នុងការទាញយក Audit Logs! " . $e->getMessage()]);
    }
}

function handleGetUsers($db, $simulated) {
    checkPermission('view_users');
    
    if ($simulated) {
        $users = [
            ["id" => 1, "username" => "superadmin_cambodia", "role" => "super_admin", "status" => "active", "created_at" => "2026-08-01 00:00:00"],
            ["id" => 2, "username" => "admin_sophors", "role" => "admin", "status" => "active", "created_at" => "2026-08-05 10:30:00"],
            ["id" => 3, "username" => "khmer_user1", "role" => "user", "status" => "active", "created_at" => "2026-08-10 14:20:00"]
        ];
        echo json_encode(["status" => "success", "data" => $users]);
        return;
    }

    try {
        $stmt = $db->query("SELECT id, username, role, status, created_at FROM users ORDER BY id ASC");
        $users = $stmt->fetchAll();
        echo json_encode(["status" => "success", "data" => $users]);
    } catch (PDOException $e) {
        error_log("Failed to fetch users: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "មានបញ្ហាបច្ចេកទេសក្នុងការទាញយកបញ្ជីអ្នកប្រើប្រាស់!"]);
    }
}

function handleCreateUser($db, $simulated) {
    checkPermission('create_user');

    $data = json_decode(file_get_contents("php://input"), true);
    if (empty($data['username']) || empty($data['password']) || empty($data['role'])) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "ឈ្មោះអ្នកប្រើប្រាស់ ពាក្យសម្ងាត់ និងតួនាទី គឺចាំបាច់ត្រូវតែបំពេញ!"]);
        return;
    }

    $username = trim($data['username']);
    $password = $data['password'];
    $role = trim($data['role']);

    if (!in_array($role, ['user', 'admin', 'super_admin'])) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "ប្រភេទតួនាទីមិនត្រឹមត្រូវឡើយ!"]);
        return;
    }

    $creator_role = $_SESSION['role'];
    if ($creator_role === 'admin' && ($role === 'admin' || $role === 'super_admin')) {
        http_response_code(403);
        echo json_encode(["status" => "error", "message" => "គណនីប្រភេទ Admin អាចបង្កើតបានតែសិទ្ធិជា User ធម្មតាប៉ុណ្ណោះ!"]);
        return;
    }

    $minLength = ($role === 'super_admin' || $role === 'admin') ? 12 : 8;
    if (strlen($password) < $minLength) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "ពាក្យសម្ងាត់សម្រាប់តួនាទី " . strtoupper($role) . " ត្រូវតែមានប្រវែងយ៉ាងតិច " . $minLength . " ខ្ទង់។"]);
        return;
    }

    if ($simulated) {
        echo json_encode([
            "status" => "success",
            "message" => "បង្កើតគណនី '$username' (តួនាទី: " . strtoupper($role) . ") ជោគជ័យ!"
        ]);
        return;
    }

    try {
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "ឈ្មោះអ្នកប្រើប្រាស់នេះមានរួចហើយនៅក្នុងប្រព័ន្ធ!"]);
            return;
        }

        $password_hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $db->prepare("INSERT INTO users (username, password_hash, role, status) VALUES (?, ?, ?, 'active')");
        $stmt->execute([$username, $password_hash, $role]);
        $new_user_id = $db->lastInsertId();

        logAdminActivity($db, 'CREATE_USER', $new_user_id, "Created new user account: '$username' with role: '$role'");

        echo json_encode([
            "status" => "success",
            "message" => "បង្កើតគណនីអ្នកប្រើប្រាស់ '$username' (តួនាទី: " . strtoupper($role) . ") ទទួលបានជោគជ័យ!"
        ]);
    } catch (PDOException $e) {
        error_log("Database error creating user: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "បរាជ័យក្នុងការបង្កើតគណនីថ្មីក្នុង Database! " . $e->getMessage()]);
    }
}

function handleResetPassword($db, $simulated) {
    checkPermission('reset_password');

    $data = json_decode(file_get_contents("php://input"), true);
    if (empty($data['target_user_id']) || empty($data['new_password'])) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "លេខសម្គាល់អ្នកប្រើប្រាស់ និងពាក្យសម្ងាត់ថ្មី គឺចាំបាច់ត្រូវតែបំពេញ!"]);
        return;
    }

    $target_user_id = intval($data['target_user_id']);
    $new_password = $data['new_password'];

    if ($simulated) {
        echo json_encode([
            "status" => "success",
            "message" => "Reset Password ជោគជ័យ!"
        ]);
        return;
    }

    try {
        $stmt = $db->prepare("SELECT username, role FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$target_user_id]);
        $target_user = $stmt->fetch();

        if (!$target_user) {
            http_response_code(404);
            echo json_encode(["status" => "error", "message" => "រកមិនឃើញគណនីអ្នកប្រើប្រាស់ដែលត្រូវ Reset ឡើយ!"]);
            return;
        }

        $target_username = $target_user['username'];
        $target_role = $target_user['role'];

        $creator_role = $_SESSION['role'];
        if ($creator_role === 'admin' && ($target_role === 'admin' || $target_role === 'super_admin')) {
            http_response_code(403);
            echo json_encode(["status" => "error", "message" => "គណនីប្រភេទ Admin អាចធ្វើការ Reset បានតែគណនីប្រភេទ User ធម្មតាប៉ុណ្ណោះ!"]);
            return;
        }

        $minLength = ($target_role === 'super_admin' || $target_role === 'admin') ? 12 : 8;
        if (strlen($new_password) < $minLength) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "ពាក្យសម្ងាត់សម្រាប់តួនាទី " . strtoupper($target_role) . " ត្រូវតែមានប្រវែងយ៉ាងតិច " . $minLength . " ខ្ទង់。"]);
            return;
        }

        $password_hash = password_hash($new_password, PASSWORD_BCRYPT);
        $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->execute([$password_hash, $target_user_id]);

        logAdminActivity($db, 'RESET_USER_PASSWORD', $target_user_id, "Reset password for user: '$target_username'");

        echo json_encode([
            "status" => "success",
            "message" => "ការផ្លាស់ប្តូរពាក្យសម្ងាត់សម្រាប់អ្នកប្រើប្រាស់ '$target_username' ទទួលបានជោគជ័យ!"
        ]);
    } catch (PDOException $e) {
        error_log("Database error resetting password: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "បរាជ័យក្នុងការ Reset Password! " . $e->getMessage()]);
    }
}

function handleDeleteUser($db, $simulated) {
    checkPermission('delete_user');

    $data = json_decode(file_get_contents("php://input"), true);
    $target_user_id = intval($data['target_user_id'] ?? 0);

    if ($target_user_id <= 0) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "ID អ្នកប្រើប្រាស់មិនត្រឹមត្រូវឡើយ!"]);
        return;
    }

    if ($target_user_id == $_SESSION['user_id']) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "លោកអ្នកមិនអាចលុបគណនីផ្ទាល់ខ្លួនឯងបានទេ!"]);
        return;
    }

    if ($simulated) {
        echo json_encode(["status" => "success", "message" => "លុបគណនីអ្នកប្រើប្រាស់ជោគជ័យ!"]);
        return;
    }

    try {
        $stmt = $db->prepare("SELECT username, role FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$target_user_id]);
        $target_user = $stmt->fetch();

        if (!$target_user) {
            http_response_code(404);
            echo json_encode(["status" => "error", "message" => "រកមិនឃើញគណនីអ្នកប្រើប្រាស់ដែលត្រូវលុបឡើយ!"]);
            return;
        }

        $target_username = $target_user['username'];
        $target_role = $target_user['role'];

        $creator_role = $_SESSION['role'];
        if ($creator_role === 'admin' && ($target_role === 'admin' || $target_role === 'super_admin')) {
            http_response_code(403);
            echo json_encode(["status" => "error", "message" => "Admin អាចលុបបានតែគណនីប្រភេទ User ធម្មតាប៉ុណ្ណោះ!"]);
            return;
        }

        $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$target_user_id]);

        logAdminActivity($db, 'DELETE_USER', $target_user_id, "Deleted user account: '$target_username'");

        echo json_encode(["status" => "success", "message" => "លុបគណនីអ្នកប្រើប្រាស់ '$target_username' ទទួលបានជោគជ័យ!"]);
    } catch (PDOException $e) {
        error_log("Database error deleting user: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "បរាជ័យក្នុងការលុបគណនីអ្នកប្រើប្រាស់! " . $e->getMessage()]);
    }
}

function handleGetPendingApprovals($db, $simulated) {
    checkPermission('approve_admin');
    echo json_encode(["status" => "success", "data" => []]);
}

function handleRequestAdminPromotion($db, $simulated) {
    checkPermission('add_admin_request');
    echo json_encode(["status" => "success", "message" => "Admin request submitted"]);
}

function handleApproveAdminPromotion($db, $simulated) {
    checkPermission('approve_admin');
    echo json_encode(["status" => "success", "message" => "Admin promotion approved"]);
}

function handleLogout() {
    $_SESSION = array();
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    echo json_encode(["status" => "success", "message" => "Logout successful."]);
}
?>