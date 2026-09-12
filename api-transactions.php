<?php
/**
 * api-transactions-v9.php - Complete Production Transaction Management API v9
 * Part of the Khmer Payment Tracker and Financial Management System
 * 
 * Handles secure CRUD operations, optional receipt file uploads, list_all for frontend metrics/charts,
 * RBAC user scoping, soft-deletion, and audit history.
 */

header("Content-Type: application/json; charset=UTF-8");

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';

// --- 1. Authentication Guard ---
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "សូមចូលប្រើប្រាស់ប្រព័ន្ធជាមុនសិន។ (Unauthorized)"]);
    exit;
}

$current_user_id = $_SESSION['user_id'];
$current_role = $_SESSION['role'];
$db = getSecureDBConnection();

$method = $_SERVER['REQUEST_METHOD'];

/**
 * Helper to handle optional receipt file upload
 */
function handleReceiptUpload() {
    $file = $_FILES['receipt'] ?? $_FILES['receipt_image'] ?? null;
    if (!$file || !isset($file['tmp_name']) || empty($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $uploadDir = __DIR__ . '/uploads/';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0755, true);
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'pdf'];
    if (!in_array($ext, $allowed)) {
        return null;
    }

    $filename = 'receipt_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $targetPath = $uploadDir . $filename;

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return 'uploads/' . $filename;
    }

    return null;
}

switch ($method) {
    case 'POST':
        $action = isset($_GET['action']) ? $_GET['action'] : '';

        // --- Action: restore ---
        if ($action === 'restore') {
            if ($current_role !== 'super_admin' && $current_role !== 'admin') {
                http_response_code(403);
                echo json_encode(["status" => "error", "message" => "Forbidden: មានតែ Admin ប៉ុណ្ណោះដែលអាចស្តារទិន្នន័យបាន។"]);
                exit;
            }

            $input = json_decode(file_get_contents("php://input"), true) ?: $_POST;
            $transaction_id = isset($input['transaction_id']) ? intval($input['transaction_id']) : 0;

            if ($transaction_id <= 0) {
                http_response_code(400);
                echo json_encode(["status" => "error", "message" => "ID ប្រតិបត្តិការមិនត្រឹមត្រូវឡើយ។"]);
                exit;
            }

            try {
                $getStmt = $db->prepare("SELECT * FROM `transactions` WHERE id = ?");
                $getStmt->execute([$transaction_id]);
                $txn = $getStmt->fetch();

                if (!$txn) {
                    http_response_code(404);
                    echo json_encode(["status" => "error", "message" => "រកមិនឃើញប្រតិបត្តិការនេះឡើយ។"]);
                    exit;
                }

                $db->beginTransaction();

                $stmt = $db->prepare("UPDATE `transactions` SET `is_deleted` = 0 WHERE `id` = ?");
                $stmt->execute([$transaction_id]);

                $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
                $details = "Restored transaction ID: {$transaction_id} ({$txn['description']}).";
                $logStmt = $db->prepare("INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (?, 'RESTORE_TRANSACTION', ?, ?)");
                $logStmt->execute([$current_user_id, $details, $ip]);

                $db->commit();
                echo json_encode(["status" => "success", "message" => "ប្រតិបត្តិការត្រូវបានស្តារឡើងវិញដោយជោគជ័យ!"]);

            } catch (PDOException $e) {
                if ($db->inTransaction()) $db->rollBack();
                error_log("Failed to restore: " . $e->getMessage());
                http_response_code(500);
                echo json_encode(["status" => "error", "message" => "មានបញ្ហាបច្ចេកទេសក្នុងការស្តារទិន្នន័យ។"]);
            }
            exit;
        }

        // --- Action: update / edit ---
        if ($action === 'update' || $action === 'edit') {
            $input = json_decode(file_get_contents("php://input"), true) ?: $_POST;
            $transaction_id = isset($input['transaction_id']) ? intval($input['transaction_id']) : (isset($input['id']) ? intval($input['id']) : 0);
            
            $description = isset($input['title']) ? trim(htmlspecialchars($input['title'])) : (isset($input['description']) ? trim(htmlspecialchars($input['description'])) : '');
            $amount = isset($input['amount']) ? filter_var($input['amount'], FILTER_VALIDATE_FLOAT) : false;
            $currency = isset($input['currency']) ? trim($input['currency']) : '';
            $type = isset($input['type']) ? trim($input['type']) : '';
            $category = isset($input['category']) ? trim(htmlspecialchars($input['category'])) : '';
            $date = isset($input['date']) ? trim($input['date']) : '';

            $new_receipt = handleReceiptUpload();

            if ($transaction_id <= 0 || empty($description) || $amount === false || $amount <= 0 || empty($currency) || empty($type) || empty($category)) {
                http_response_code(400);
                echo json_encode(["status" => "error", "message" => "សូមបំពេញព័ត៌មានឱ្យបានត្រឹមត្រូវ និងគ្រប់គ្រាន់។"]);
                exit;
            }

            try {
                $getStmt = $db->prepare("SELECT * FROM `transactions` WHERE id = ?");
                $getStmt->execute([$transaction_id]);
                $txn = $getStmt->fetch();

                if (!$txn) {
                    http_response_code(404);
                    echo json_encode(["status" => "error", "message" => "រកមិនឃើញប្រតិបត្តិការដែលត្រូវកែប្រែឡើយ។"]);
                    exit;
                }

                if ($current_role !== 'super_admin' && $current_role !== 'admin' && $txn['user_id'] != $current_user_id) {
                    http_response_code(403);
                    echo json_encode(["status" => "error", "message" => "Forbidden: លោកអ្នកគ្មានសិទ្ធិកែប្រែប្រតិបត្តិការរបស់អ្នកដទៃឡើយ。"]);
                    exit;
                }

                $db->beginTransaction();

                // Record history
                $histStmt = $db->prepare("
                    INSERT INTO `transaction_history` (
                        `transaction_id`, `user_id`, 
                        `original_description`, `original_amount`, `original_currency`, `original_type`, `original_category`,
                        `new_description`, `new_amount`, `new_currency`, `new_type`, `new_category`,
                        `action_type`, `actioned_by`
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'UPDATE', ?)
                ");
                $histStmt->execute([
                    $transaction_id, $txn['user_id'],
                    $txn['description'], $txn['amount'], $txn['currency'], $txn['type'], $txn['category'],
                    $description, $amount, $currency, $type, $category,
                    $current_user_id
                ]);

                // Receipt path
                $receiptPath = $new_receipt ?: $txn['receipt_image'];

                $updateSql = "UPDATE `transactions` SET `description` = ?, `amount` = ?, `currency` = ?, `type` = ?, `category` = ?, `receipt_image` = ?";
                $params = [$description, $amount, $currency, $type, $category, $receiptPath];

                if (!empty($date)) {
                    $updateSql .= ", `date` = ?";
                    $params[] = $date;
                }
                $updateSql .= " WHERE `id` = ?";
                $params[] = $transaction_id;

                $updateStmt = $db->prepare($updateSql);
                $updateStmt->execute($params);

                $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
                $details = "Updated transaction ID: {$transaction_id} ({$description}).";
                $logStmt = $db->prepare("INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (?, 'EDIT_TRANSACTION', ?, ?)");
                $logStmt->execute([$current_user_id, $details, $ip]);

                $db->commit();

                echo json_encode([
                    "status" => "success",
                    "message" => "ប្រតិបត្តិការត្រូវបានកែប្រែ និងរក្សាទុកជោគជ័យ!"
                ]);

            } catch (PDOException $e) {
                if ($db->inTransaction()) $db->rollBack();
                error_log("Failed to update: " . $e->getMessage());
                http_response_code(500);
                echo json_encode(["status" => "error", "message" => "មានបញ្ហាបច្ចេកទេសក្នុងការកែប្រែទិន្នន័យ។"]);
            }
            exit;
        }

        // --- Default POST: Create New Transaction ---
        $input = json_decode(file_get_contents("php://input"), true) ?: $_POST;

        $description = isset($input['title']) ? trim(htmlspecialchars($input['title'])) : (isset($input['description']) ? trim(htmlspecialchars($input['description'])) : '');
        $amount = isset($input['amount']) ? filter_var($input['amount'], FILTER_VALIDATE_FLOAT) : false;
        $currency = isset($input['currency']) ? trim($input['currency']) : '';
        $type = isset($input['type']) ? trim($input['type']) : '';
        $category = isset($input['category']) ? trim(htmlspecialchars($input['category'])) : '';
        $date = isset($input['date']) && !empty($input['date']) ? trim($input['date']) : date('Y-m-d H:i:s');
        
        $receipt_image = handleReceiptUpload();
        if (!$receipt_image && isset($input['receipt_image'])) {
            $receipt_image = trim($input['receipt_image']);
        }

        if (empty($description) || $amount === false || $amount <= 0 || empty($currency) || empty($type) || empty($category)) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "សូមបំពេញព័ត៌មានឱ្យបានត្រឹមត្រូវ និងគ្រប់គ្រាន់ (ទឹកប្រាក់ត្រូវតែធំជាង ០)។"]);
            exit;
        }

        try {
            $stmt = $db->prepare("
                INSERT INTO `transactions` (`user_id`, `description`, `amount`, `currency`, `type`, `category`, `date`, `receipt_image`, `is_deleted`) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)
            ");
            $stmt->execute([$current_user_id, $description, $amount, $currency, $type, $category, $date, $receipt_image]);
            $new_id = $db->lastInsertId();

            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $details = "Added transaction ID: {$new_id} ({$description}) amount {$amount} {$currency}.";
            $logStmt = $db->prepare("INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (?, 'ADD_TRANSACTION', ?, ?)");
            $logStmt->execute([$current_user_id, $details, $ip]);

            echo json_encode([
                "status" => "success",
                "message" => "ប្រតិបត្តិការត្រូវបានរក្សាទុកដោយជោគជ័យ!",
                "transaction_id" => $new_id
            ]);

        } catch (PDOException $e) {
            error_log("Database insertion failed: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "បរាជ័យក្នុងការរក្សាទុកទិន្នន័យទៅកាន់ Database។"]);
        }
        break;

    case 'GET':
        $action = isset($_GET['action']) ? $_GET['action'] : '';

        // --- Action: list_all ---
        if ($action === 'list_all') {
            $whereClause = " WHERE t.is_deleted = 0";
            $queryParams = [];

            if ($current_role !== 'super_admin' && $current_role !== 'admin') {
                $whereClause .= " AND t.user_id = :user_id";
                $queryParams[':user_id'] = $current_user_id;
            }

            try {
                $queryStr = "
                    SELECT t.*, u.username as creator_name 
                    FROM transactions t 
                    LEFT JOIN users u ON t.user_id = u.id
                    {$whereClause}
                    ORDER BY t.date DESC
                ";
                $stmt = $db->prepare($queryStr);
                $stmt->execute($queryParams);
                $transactions = $stmt->fetchAll();

                $formatted = [];
                foreach ($transactions as $row) {
                    $formatted[] = [
                        "id" => $row['id'],
                        "user_id" => $row['user_id'],
                        "date" => date('Y-m-d H:i', strtotime($row['date'])),
                        "description" => $row['description'],
                        "creator" => ($row['user_id'] == $current_user_id) ? '-' : $row['creator_name'],
                        "type" => strtolower($row['type']),
                        "raw_type" => strtolower($row['type']),
                        "currency" => strtoupper($row['currency']),
                        "raw_currency" => strtoupper($row['currency']),
                        "amount" => (float)$row['amount'],
                        "raw_amount" => (float)$row['amount'],
                        "category" => $row['category'],
                        "is_deleted" => (int)$row['is_deleted'],
                        "receipt_image" => $row['receipt_image'] ?? null
                    ];
                }

                echo json_encode([
                    "status" => "success",
                    "data" => $formatted
                ]);

            } catch (PDOException $e) {
                error_log("list_all query failed: " . $e->getMessage());
                http_response_code(500);
                echo json_encode(["status" => "error", "message" => "មានបញ្ហាបច្ចេកទេសក្នុងការទាញយកទិន្នន័យ។"]);
            }
            exit;
        }

        // --- Default GET: Paginated list ---
        $limit = isset($_GET['limit']) ? max(1, intval($_GET['limit'])) : 10;
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $offset = ($page - 1) * $limit;

        $fromDate = isset($_GET['from_date']) ? trim($_GET['from_date']) : '';
        $toDate = isset($_GET['to_date']) ? trim($_GET['to_date']) : '';
        $filter_date = isset($_GET['date']) ? trim($_GET['date']) : '';

        $whereClause = " WHERE t.is_deleted = 0";
        $queryParams = [];

        if ($current_role !== 'super_admin' && $current_role !== 'admin') {
            $whereClause .= " AND t.user_id = :user_id";
            $queryParams[':user_id'] = $current_user_id;
        }

        if (!empty($fromDate) && !empty($toDate)) {
            $whereClause .= " AND DATE(t.date) BETWEEN :from_date AND :to_date";
            $queryParams[':from_date'] = $fromDate;
            $queryParams[':to_date'] = $toDate;
        } elseif (!empty($filter_date)) {
            $whereClause .= " AND DATE(t.date) = :filter_date";
            $queryParams[':filter_date'] = $filter_date;
        }

        try {
            $countStmt = $db->prepare("SELECT COUNT(*) FROM transactions t {$whereClause}");
            foreach ($queryParams as $k => $v) {
                $countStmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $countStmt->execute();
            $total_records = (int)$countStmt->fetchColumn();
            $total_pages = ceil($total_records / $limit);

            $queryStr = "
                SELECT t.*, u.username as creator_name 
                FROM transactions t 
                LEFT JOIN users u ON t.user_id = u.id
                {$whereClause}
                ORDER BY t.date DESC 
                LIMIT :limit OFFSET :offset
            ";
            $stmt = $db->prepare($queryStr);
            foreach ($queryParams as $k => $v) {
                $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $transactions = $stmt->fetchAll();

            $formatted = [];
            foreach ($transactions as $row) {
                $formatted[] = [
                    "id" => $row['id'],
                    "date" => date('y-m-d H:i', strtotime($row['date'])),
                    "raw_date" => date('Y-m-d\TH:i', strtotime($row['date'])),
                    "description" => $row['description'],
                    "creator" => ($row['user_id'] == $current_user_id) ? '-' : $row['creator_name'],
                    "type" => ($row['type'] === 'income') ? 'Income' : 'Expense',
                    "raw_type" => strtolower($row['type']),
                    "amount" => ($row['currency'] === 'KHR') ? number_format($row['amount']) . ' ៛' : '$' . number_format($row['amount'], 2),
                    "raw_amount" => (float)$row['amount'],
                    "raw_currency" => strtoupper($row['currency']),
                    "category" => $row['category'],
                    "receipt_image" => $row['receipt_image'] ?? null
                ];
            }

            echo json_encode([
                "status" => "success",
                "data" => $formatted,
                "pagination" => [
                    "current_page" => $page,
                    "limit" => $limit,
                    "total_records" => $total_records,
                    "total_pages" => $total_pages
                ]
            ]);

        } catch (PDOException $e) {
            error_log("Query failed: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "មានបញ្ហាបច្ចេកទេសក្នុងការទាញយកទិន្នន័យ។"]);
        }
        break;

    case 'PUT':
        $input = json_decode(file_get_contents("php://input"), true);
        $transaction_id = isset($input['transaction_id']) ? intval($input['transaction_id']) : (isset($input['id']) ? intval($input['id']) : 0);
        
        $description = isset($input['title']) ? trim(htmlspecialchars($input['title'])) : (isset($input['description']) ? trim(htmlspecialchars($input['description'])) : '');
        $amount = isset($input['amount']) ? filter_var($input['amount'], FILTER_VALIDATE_FLOAT) : false;
        $currency = isset($input['currency']) ? trim($input['currency']) : '';
        $type = isset($input['type']) ? trim($input['type']) : '';
        $category = isset($input['category']) ? trim(htmlspecialchars($input['category'])) : '';
        $date = isset($input['date']) ? trim($input['date']) : '';

        if ($transaction_id <= 0 || empty($description) || $amount === false || $amount <= 0 || empty($currency) || empty($type) || empty($category)) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "សូមបំពេញព័ត៌មានឱ្យបានត្រឹមត្រូវ និងគ្រប់គ្រាន់。"]);
            exit;
        }

        try {
            $getStmt = $db->prepare("SELECT * FROM `transactions` WHERE id = ?");
            $getStmt->execute([$transaction_id]);
            $txn = $getStmt->fetch();

            if (!$txn) {
                http_response_code(404);
                echo json_encode(["status" => "error", "message" => "រកមិនឃើញប្រតិបត្តិការដែលត្រូវកែប្រែឡើយ។"]);
                exit;
            }

            if ($current_role !== 'super_admin' && $current_role !== 'admin' && $txn['user_id'] != $current_user_id) {
                http_response_code(403);
                echo json_encode(["status" => "error", "message" => "Forbidden: លោកអ្នកគ្មានសិទ្ធិកែប្រែប្រតិបត្តិការរបស់អ្នកដទៃឡើយ。"]);
                exit;
            }

            $db->beginTransaction();

            $histStmt = $db->prepare("
                INSERT INTO `transaction_history` (
                    `transaction_id`, `user_id`, 
                    `original_description`, `original_amount`, `original_currency`, `original_type`, `original_category`,
                    `new_description`, `new_amount`, `new_currency`, `new_type`, `new_category`,
                    `action_type`, `actioned_by`
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'UPDATE', ?)
            ");
            $histStmt->execute([
                $transaction_id, $txn['user_id'],
                $txn['description'], $txn['amount'], $txn['currency'], $txn['type'], $txn['category'],
                $description, $amount, $currency, $type, $category,
                $current_user_id
            ]);

            $updateSql = "UPDATE `transactions` SET `description` = ?, `amount` = ?, `currency` = ?, `type` = ?, `category` = ?";
            $params = [$description, $amount, $currency, $type, $category];

            if (!empty($date)) {
                $updateSql .= ", `date` = ?";
                $params[] = $date;
            }
            $updateSql .= " WHERE `id` = ?";
            $params[] = $transaction_id;

            $updateStmt = $db->prepare($updateSql);
            $updateStmt->execute($params);

            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $details = "Updated transaction ID: {$transaction_id}.";
            $logStmt = $db->prepare("INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (?, 'EDIT_TRANSACTION', ?, ?)");
            $logStmt->execute([$current_user_id, $details, $ip]);

            $db->commit();

            echo json_encode(["status" => "success", "message" => "ប្រតិបត្តិការត្រូវបានកែប្រែ និងរក្សាទុកជោគជ័យ!"]);

        } catch (PDOException $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log("Failed to update: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "មានបញ្ហាបច្ចេកទេសក្នុងការកែប្រែទិន្នន័យ。"]);
        }
        break;

    case 'DELETE':
        $input = json_decode(file_get_contents("php://input"), true);
        $transaction_id = isset($input['transaction_id']) ? intval($input['transaction_id']) : 0;

        if ($transaction_id <= 0) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "ID ប្រតិបត្តិការមិនត្រឹមត្រូវឡើយ。"]);
            exit;
        }

        try {
            $getStmt = $db->prepare("SELECT * FROM `transactions` WHERE id = ?");
            $getStmt->execute([$transaction_id]);
            $txn = $getStmt->fetch();

            if (!$txn) {
                http_response_code(404);
                echo json_encode(["status" => "error", "message" => "រកមិនឃើញប្រតិបត្តិការនេះឡើយ។"]);
                exit;
            }

            if ($current_role !== 'super_admin' && $current_role !== 'admin' && $txn['user_id'] != $current_user_id) {
                http_response_code(403);
                echo json_encode(["status" => "error", "message" => "Forbidden: លោកអ្នកគ្មានសិទ្ធិលុបប្រតិបត្តិការរបស់អ្នកដទៃឡើយ。"]);
                exit;
            }

            $stmt = $db->prepare("UPDATE `transactions` SET `is_deleted` = 1 WHERE `id` = ?");
            $stmt->execute([$transaction_id]);

            echo json_encode(["status" => "success", "message" => "ប្រតិបត្តិការត្រូវបានលុបដោយជោគជ័យ!"]);

        } catch (PDOException $e) {
            error_log("Delete failed: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "មានបញ្ហាបច្ចេកទេសក្នុងការលុបទិន្នន័យ。"]);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(["status" => "error", "message" => "Method Not Allowed."]);
        break;
}
?>
