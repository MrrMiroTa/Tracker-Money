<?php
/**
 * api-transactions.php - Complete Production Transaction Management API
 * Part of the Khmer Payment Tracker and Financial Management System
 * 
 * Handles secure CRUD operations, list_all for frontend metrics/charts,
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

switch ($method) {
    case 'POST':
        $action = isset($_GET['action']) ? $_GET['action'] : '';

        // --- Restore Soft-Deleted Transaction ---
        if ($action === 'restore') {
            if ($current_role !== 'super_admin' && $current_role !== 'admin') {
                http_response_code(403);
                echo json_encode(["status" => "error", "message" => "Forbidden: មានតែ Admin ប៉ុណ្ណោះដែលអាចស្តារទិន្នន័យបាន។"]);
                exit;
            }

            $input = json_decode(file_get_contents("php://input"), true);
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

        // --- Create New Transaction ---
        $input = json_decode(file_get_contents("php://input"), true) ?: $_POST;

        $description = isset($input['title']) ? trim(htmlspecialchars($input['title'])) : '';
        $amount = isset($input['amount']) ? filter_var($input['amount'], FILTER_VALIDATE_FLOAT) : false;
        $currency = isset($input['currency']) ? trim($input['currency']) : '';
        $type = isset($input['type']) ? trim($input['type']) : '';
        $category = isset($input['category']) ? trim(htmlspecialchars($input['category'])) : '';
        $date = isset($input['date']) ? trim($input['date']) : '';
        $receipt_image = isset($input['receipt_image']) ? trim($input['receipt_image']) : null;

        if (empty($description) || $amount === false || $amount <= 0 || empty($currency) || empty($type) || empty($category) || empty($date)) {
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
            echo json_encode(["status" => "error", "message" => "បរាជ័យក្នុងការរក្សាទុកទិន្នន័យ។"]);
        }
        break;

    case 'GET':
        $action = isset($_GET['action']) ? $_GET['action'] : '';

        // --- Action: list_all (For Dashboard Metrics, Unified Totals, Budget Alerts & Charts) ---
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

        // --- Action: metrics ---
        if ($action === 'metrics') {
            $whereClause = " WHERE is_deleted = 0";
            $queryParams = [];

            if ($current_role !== 'super_admin' && $current_role !== 'admin') {
                $whereClause .= " AND user_id = :user_id";
                $queryParams[':user_id'] = $current_user_id;
            }

            try {
                $totals = ['income_khr' => 0, 'income_usd' => 0, 'expense_khr' => 0, 'expense_usd' => 0];

                $stmt = $db->prepare("SELECT type, currency, SUM(amount) as total_amount FROM transactions {$whereClause} GROUP BY type, currency");
                $stmt->execute($queryParams);
                $rows = $stmt->fetchAll();

                foreach ($rows as $row) {
                    $key = strtolower($row['type']) . '_' . strtolower($row['currency']);
                    if (isset($totals[$key])) {
                        $totals[$key] = (float)$row['total_amount'];
                    }
                }

                $balance_khr = $totals['income_khr'] - $totals['expense_khr'];
                $balance_usd = $totals['income_usd'] - $totals['expense_usd'];

                echo json_encode([
                    "status" => "success",
                    "data" => [
                        "income" => ["KHR" => number_format($totals['income_khr']) . ' ៛', "USD" => '$' . number_format($totals['income_usd'], 2)],
                        "expense" => ["KHR" => number_format($totals['expense_khr']) . ' ៛', "USD" => '$' . number_format($totals['expense_usd'], 2)],
                        "balance" => ["KHR" => number_format($balance_khr) . ' ៛', "USD" => '$' . number_format($balance_usd, 2), "raw_khr" => $balance_khr, "raw_usd" => $balance_usd]
                    ]
                ]);

            } catch (PDOException $e) {
                error_log("Metrics calculation failed: " . $e->getMessage());
                http_response_code(500);
                echo json_encode(["status" => "error", "message" => "មានបញ្ហាបច្ចេកទេសក្នុងការគណនាសមតុល្យ។"]);
            }
            exit;
        }

        // --- Action: get_transactions (Standard Paginated List for Table) ---
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
            foreach ($queryParams as $key => $val) {
                $countStmt->bindValue($key, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
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
            foreach ($queryParams as $key => $val) {
                $stmt->bindValue($key, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $transactions = $stmt->fetchAll();

            $formatted = [];
            foreach ($transactions as $row) {
                $amount_display = ($row['currency'] === 'KHR') ? number_format($row['amount']) . ' ៛' : '$' . number_format($row['amount'], 2);

                $formatted[] = [
                    "id" => $row['id'],
                    "user_id" => $row['user_id'],
                    "date" => date('y-m-d H:i', strtotime($row['date'])),
                    "description" => $row['description'],
                    "creator" => ($row['user_id'] == $current_user_id) ? '-' : $row['creator_name'],
                    "type" => ($row['type'] === 'income') ? 'Income' : 'Expense',
                    "raw_type" => strtolower($row['type']),
                    "amount" => $amount_display,
                    "raw_amount" => (float)$row['amount'],
                    "currency" => strtoupper($row['currency']),
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
            echo json_encode(["status" => "error", "message" => "មានបញ្ហាបច្ចេកទេសក្នុងការលុបទិន្នន័យ។"]);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(["status" => "error", "message" => "Method Not Allowed."]);
        break;
}
?>
