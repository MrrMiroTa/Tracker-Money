<?php
/**
 * api-v2.php - Secure User Authorization and Admin Management API with Built-in MFA Support
 * Part of the Khmer Payment Tracker and Financial Management System
 * 
 * This file implements Role-Based Access Control (RBAC), Multi-Admin Approval (Dual Control),
 * and security-hardened authentication flows supporting 2-Step Verification (MFA).
 */

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// --- 1. SESSION MANAGEMENT & CONFIGURATION ---
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? 1 : 0);
    session_start();
}

// --- 2. DATABASE CONNECTION (Using PDO for SQL Injection Prevention) ---
require_once 'config.php';

function getDBConnection() {
    try {
        return getSecureDBConnection();
    } catch (Exception $e) {
        error_log("api-v2.php failed to get secure db connection: " . $e->getMessage());
        return null;
    }
}

// --- 3. AUDIT LOGGING FUNCTION ---
function logAdminActivity($db, $action, $target_user_id = null, $details = '') {
    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';
    $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
    
    if ($db) {
        try {
            $stmt = $db->prepare("INSERT INTO audit_logs (user_id, action, target_user_id, details, ip_address) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$userId, $action, $target_user_id, $details, $ip]);
        } catch (Exception $e) {
            error_log("Audit log failed: " . $e->getMessage());
        }
    }
}

// --- 4. ROLE-BASED ACCESS CONTROL (RBAC) CONFIGURATION ---
$role_permissions = [
    'super_admin' => [
        'view_dashboard', 'add_transaction', 'edit_transaction', 'delete_transaction',
        'view_users', 'add_admin_request', 'approve_admin', 'delete_user', 'view_audit_logs',
        'create_user', 'reset_password'
    ],
    'admin' => [
        'view_dashboard', 'add_transaction', 'edit_transaction',
        'view_users', 'add_admin_request',
        'create_user', 'reset_password'
    ],
    'user' => [
        'view_dashboard', 'add_transaction'
    ]
];

// --- 5. AUTHORIZATION GUARDS ---
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

// --- 6. API REQUEST ROUTING & LOGIC ---
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
        } elseif ($action === 'logout') {
            handleLogout();
        } else {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Invalid POST action."]);
        }
        break;
        
    case 'GET':
        if ($action === 'users') {
            handleGetUsers($db, $is_simulated);
        } elseif ($action === 'approvals') {
            handleGetPendingApprovals($db, $is_simulated);
        } elseif ($action === 'audit_logs') {
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

// --- 7. ACTION HANDLERS ---

function handleLogin($db, $simulated) {
    $data = json_decode(file_get_contents("php://input"), true);
    
    if (empty($data['username']) || empty($data['password'])) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Username and password are required."]);
        return;
    }

    $username = trim($data['username']);
    $password = $data['password'];
    $mfa_code = isset($data['mfa_code']) ? trim($data['mfa_code']) : '';

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
            echo json_encode(["status" => "success", "message" => "Simulated SuperAdmin Login Successful", "user" => ["username" => $username, "role" => "super_admin"]]);
        } else {
            http_response_code(401);
            echo json_encode(["status" => "error", "message" => "Invalid credentials."]);
        }
        return;
    }

    try {
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            if ($user['status'] !== 'active') {
                http_response_code(403);
                echo json_encode(["status" => "error", "message" => "Your account is currently suspended or pending."]);
                return;
            }

            // --- 2-STEP VERIFICATION (MFA) CHECK ---
            if (!empty($user['mfa_secret'])) {
                if (empty($mfa_code)) {
                    http_response_code(401);
                    echo json_encode(["status" => "error", "message" => "MFA code is required."]);
                    return;
                }
                
                require_once 'mfa-helper.php';
                if (!MFAHelper::verifyCode($user['mfa_secret'], $mfa_code)) {
                    http_response_code(401);
                    echo json_encode(["status" => "error", "message" => "កូដ OTP មិនត្រឹមត្រូវ ឬហួសសុពលភាពឡើយ! (Invalid MFA Code)"]);
                    return;
                }
            }

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            
            logAdminActivity($db, 'LOGIN_SUCCESS', null, "Successfully signed in.");

            echo json_encode([
                "status" => "success", 
                "message" => "Login Successful", 
                "user" => [
                    "id" => $user['id'],
                    "username" => $user['username'],
                    "role" => $user['role']
                ]
            ]);
        } else {
            http_response_code(401);
            echo json_encode(["status" => "error", "message" => "Invalid username or password."]);
        }
    } catch (PDOException $e) {
        error_log("Login database query failed: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "ការស៊ើបអង្កេតទិន្នន័យបានបរាជ័យ។ (Database query failed)"]);
    }
}

function handleRequestAdminPromotion($db, $simulated) {
    checkPermission('add_admin_request');
    
    $data = json_decode(file_get_contents("php://input"), true);
    if (empty($data['target_user_id'])) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Target user ID is required."]);
        return;
    }

    $target_user_id = intval($data['target_user_id']);

    if ($simulated) {
        echo json_encode([
            "status" => "success", 
            "message" => "Admin promotion request submitted (Simulated). Awaiting second Admin approval.",
            "data" => ["requested_by" => $_SESSION['user_id'], "target_user" => $target_user_id, "status" => "pending"]
        ]);
        return;
    }

    $stmt = $db->prepare("SELECT role, status FROM users WHERE id = ?");
    $stmt->execute([$target_user_id]);
    $target = $stmt->fetch();

    if (!$target) {
        http_response_code(404);
        echo json_encode(["status" => "error", "message" => "Target user not found."]);
        return;
    }

    if ($target['role'] !== 'user') {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Target user is already an Admin or Super Admin."]);
        return;
    }

    $stmt = $db->prepare("SELECT id FROM admin_approvals WHERE target_user_id = ? AND status = 'pending'");
    $stmt->execute([$target_user_id]);
    if ($stmt->fetch()) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "A pending request already exists for this user."]);
        return;
    }

    $stmt = $db->prepare("INSERT INTO admin_approvals (requested_by, target_user_id, status) VALUES (?, ?, 'pending')");
    $stmt->execute([$_SESSION['user_id'], $target_user_id]);
    
    logAdminActivity($db, 'REQUEST_ADD_ADMIN', $target_user_id, "Requested to promote user ID: $target_user_id to Admin.");

    echo json_encode(["status" => "success", "message" => "Admin promotion request submitted. Awaiting second Admin approval."]);
}

function handleApproveAdminPromotion($db, $simulated) {
    checkPermission('approve_admin');
    
    $data = json_decode(file_get_contents("php://input"), true);
    if (empty($data['request_id']) || !isset($data['decision'])) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Request ID and decision (approve/reject) are required."]);
        return;
    }

    $request_id = intval($data['request_id']);
    $decision = $data['decision'];

    if ($simulated) {
        echo json_encode([
            "status" => "success", 
            "message" => "Admin promotion decision handled successfully (Simulated). Decision: " . strtoupper($decision)
        ]);
        return;
    }

    $stmt = $db->prepare("SELECT * FROM admin_approvals WHERE id = ? AND status = 'pending'");
    $stmt->execute([$request_id]);
    $request = $stmt->fetch();

    if (!$request) {
        http_response_code(404);
        echo json_encode(["status" => "error", "message" => "Pending promotion request not found."]);
        return;
    }

    if ($request['requested_by'] === $_SESSION['user_id']) {
        http_response_code(403);
        echo json_encode(["status" => "error", "message" => "Security Violation: You cannot approve or reject your own request."]);
        return;
    }

    $db->beginTransaction();
    try {
        if ($decision === 'approve') {
            $updateUser = $db->prepare("UPDATE users SET role = 'admin' WHERE id = ?");
            $updateUser->execute([$request['target_user_id']]);

            $updateApproval = $db->prepare("UPDATE admin_approvals SET approved_by = ?, status = 'approved', actioned_at = CURRENT_TIMESTAMP WHERE id = ?");
            $updateApproval->execute([$_SESSION['user_id'], $request_id]);

            logAdminActivity($db, 'APPROVE_ADD_ADMIN', $request['target_user_id'], "Approved Admin promotion request ID: $request_id");
            $message = "User successfully promoted to Admin.";
        } else {
            $updateApproval = $db->prepare("UPDATE admin_approvals SET approved_by = ?, status = 'rejected', actioned_at = CURRENT_TIMESTAMP WHERE id = ?");
            $updateApproval->execute([$_SESSION['user_id'], $request_id]);

            logAdminActivity($db, 'REJECT_ADD_ADMIN', $request['target_user_id'], "Rejected Admin promotion request ID: $request_id");
            $message = "Admin promotion request rejected.";
        }

        $db->commit();
        echo json_encode(["status" => "success", "message" => $message]);
    } catch (Exception $e) {
        $db->rollBack();
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Internal error during promotion processing: " . $e->getMessage()]);
    }
}

function handleGetUsers($db, $simulated) {
    checkPermission('view_users');
    
    if ($simulated) {
        $users = [
            ["id" => 1, "username" => "superadmin_cambodia", "role" => "super_admin", "status" => "active"],
            ["id" => 2, "username" => "admin_sophors", "role" => "admin", "status" => "active"],
            ["id" => 3, "username" => "khmer_user1", "role" => "user", "status" => "active"]
        ];
        echo json_encode(["status" => "success", "data" => $users]);
        return;
    }

    $stmt = $db->query("SELECT id, username, role, status, created_at FROM users");
    $users = $stmt->fetchAll();
    echo json_encode(["status" => "success", "data" => $users]);
}

function handleGetPendingApprovals($db, $simulated) {
    checkPermission('view_users');
    
    if ($simulated) {
        $approvals = [
            ["id" => 1, "requested_by" => "admin_sophors", "target_username" => "khmer_user1", "status" => "pending", "created_at" => "2026-08-24"]
        ];
        echo json_encode(["status" => "success", "data" => $approvals]);
        return;
    }

    $stmt = $db->query("
        SELECT a.id, u1.username as requested_by_username, u2.username as target_username, a.status, a.created_at 
        FROM admin_approvals a
        JOIN users u1 ON a.requested_by = u1.id
        JOIN users u2 ON a.target_user_id = u2.id
        WHERE a.status = 'pending'
    ");
    $approvals = $stmt->fetchAll();
    echo json_encode(["status" => "success", "data" => $approvals]);
}

function handleGetAuditLogs($db, $simulated) {
    checkPermission('view_audit_logs');
    
    if ($simulated) {
        $logs = [
            ["id" => 1, "operator" => "admin_sophors", "action" => "REQUEST_ADD_ADMIN", "details" => "Requested promotion for user ID: 3", "ip" => "127.0.0.1", "timestamp" => "2026-08-24 10:00:00"]
        ];
        echo json_encode(["status" => "success", "data" => $logs]);
        return;
    }

    $stmt = $db->query("
        SELECT l.id, u.username as operator, l.action, l.details, l.ip_address, l.created_at 
        FROM audit_logs l
        JOIN users u ON l.user_id = u.id
        ORDER BY l.created_at DESC
    ");
    $logs = $stmt->fetchAll();
    echo json_encode(["status" => "success", "data" => $logs]);
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

    // Validate role
    if (!in_array($role, ['user', 'admin', 'super_admin'])) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "ប្រភេទតួនាទីមិនត្រឹមត្រូវឡើយ!"]);
        return;
    }

    // Admins can only create regular users (Prevent Privilege Escalation)
    $creator_role = $_SESSION['role'];
    if ($creator_role === 'admin' && ($role === 'admin' || $role === 'super_admin')) {
        http_response_code(403);
        echo json_encode(["status" => "error", "message" => "គណនីប្រភេទ Admin អាចបង្កើតបានតែសិទ្ធិជា User ធម្មតាប៉ុណ្ណោះ!"]);
        return;
    }

    // Proportional Password Policy length constraint
    $minLength = ($role === 'super_admin' || $role === 'admin') ? 12 : 8;
    if (strlen($password) < $minLength) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "ពាក្យសម្ងាត់សម្រាប់តួនាទី " . strtoupper($role) . " ត្រូវតែមានប្រវែងយ៉ាងតិច $minLength ខ្ទង់។"]);
        return;
    }

    if ($simulated) {
        echo json_encode([
            "status" => "success",
            "message" => "បង្កើតគណនីថ្មីជោគជ័យ! (Simulated User Created)",
            "data" => [
                "username" => $username,
                "role" => $role,
                "status" => "active"
            ]
        ]);
        return;
    }

    try {
        // Check uniqueness
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

        // Security Log
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
            "message" => "Reset Password ជោគជ័យ! (Simulated Password Reset)",
            "data" => [
                "target_user_id" => $target_user_id,
                "status" => "success"
            ]
        ]);
        return;
    }

    try {
        // Fetch target user details
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

        // Prevent Privilege Escalation / Unauthorized Reset
        $creator_role = $_SESSION['role'];
        if ($creator_role === 'admin' && ($target_role === 'admin' || $target_role === 'super_admin')) {
            http_response_code(403);
            echo json_encode(["status" => "error", "message" => "គណនីប្រភេទ Admin អាចធ្វើការ Reset បានតែគណនីប្រភេទ User ធម្មតាប៉ុណ្ណោះ!"]);
            return;
        }

        // Proportional Password Policy length constraint
        $minLength = ($target_role === 'super_admin' || $target_role === 'admin') ? 12 : 8;
        if (strlen($new_password) < $minLength) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "ពាក្យសម្ងាត់សម្រាប់តួនាទី " . strtoupper($target_role) . " ត្រូវតែមានប្រវែងយ៉ាងតិច $minLength ខ្ទង់។"]);
            return;
        }

        $password_hash = password_hash($new_password, PASSWORD_BCRYPT);
        
        $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->execute([$password_hash, $target_user_id]);

        // Security Audit Log with Details
        logAdminActivity($db, 'RESET_USER_PASSWORD', $target_user_id, "Admin reset password for user: '$target_username' (ID: $target_user_id, Role: " . strtoupper($target_role) . ")");

        echo json_encode([
            "status" => "success",
            "message" => "ការផ្លាស់ប្តូរពាក្យសម្ងាត់សម្រាប់អ្នកប្រើប្រាស់ '$target_username' ទទួលបានជោគជ័យ!"
        ]);
    } catch (PDOException $e) {
        error_log("Database error resetting password: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "បរាជ័យក្នុងការ Reset Password ក្នុង Database! " . $e->getMessage()]);
    }
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
