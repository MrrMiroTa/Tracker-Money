<?php
/**
 * api-transactions-v5.php - Secure Transaction Management API with Pagination, Date Filter, Soft Delete & Metrics
 * Part of the Khmer Payment Tracker and Financial Management System
 * 
 * Handles secure creation (POST), paginated & date-filtered retrieval (GET), and secure soft-deletion (DELETE) of transactions.
 * Includes SQL Injection prevention, Role-Based Access Control (RBAC), and Audit Logging.
 */

header("Content-Type: application/json; charset=UTF-8");

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';

// --- ១. ផ្ទៀងផ្ទាត់ការចូលប្រើប្រាស់ (Authentication Guard) ---\
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
        // --- ២. ការបន្ថែមប្រតិបត្តិការថ្មី (Create Transaction - POST) ---
        $input = json_decode(file_get_contents("php://input"), true);
        if (!$input) {
            $input = $_POST;
        }

        // ប្រមូល និងសម្អាតទិន្នន័យ (Sanitization and Validation)
        $description = isset($input['title']) ? trim(htmlspecialchars($input['title'])) : '';
        $amount = isset($input['amount']) ? filter_var($input['amount'], FILTER_VALIDATE_FLOAT) : false;
        $currency = isset($input['currency']) ? trim($input['currency']) : '';
        $type = isset($input['type']) ? trim($input['type']) : '';
        $category = isset($input['category']) ? trim(htmlspecialchars($input['category'])) : '';
        $date = isset($input['date']) ? trim($input['date']) : '';

        // ផ្ទៀងផ្ទាត់ភាពត្រឹមត្រូវនៃទិន្នន័យ (Validation Checks)
        if (empty($description) || $amount === false || $amount <= 0 || empty($currency) || empty($type) || empty($category) || empty($date)) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "សូមបំពេញព័ត៌មានឱ្យបានត្រឹមត្រូវ និងគ្រប់គ្រាន់ (ទឹកប្រាក់ត្រូវតែធំជាង ០)।"]);
            exit;
        }

        // ការពារទម្រង់ជម្រើសផ្សេងៗ (Strict Enum Whitelisting)
        if (!in_array($currency, ['KHR', 'USD']) || !in_array($type, ['income', 'expense'])) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "ប្រភេទរូបិយប័ណ្ណ ឬប្រភេទប្រតិបត្តិការមិនត្រឹមត្រូវឡើយ។"]);
            exit;
        }

        try {
            // បញ្ចូលទិន្នន័យដោយប្រើ Prepared Statements ការពារ SQL Injection
            $stmt = $db->prepare("
                INSERT INTO `transactions` (`user_id`, `description`, `amount`, `currency`, `type`, `category`, `date`, `is_deleted`) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 0)
            ");
            $stmt->execute([$current_user_id, $description, $amount, $currency, $type, $category, $date]);
            $new_transaction_id = $db->lastInsertId();

            // កត់ត្រាសកម្មភាពសន្តិសុខក្នុង Audit Logs
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $details = "Added transaction ID: {$new_transaction_id} ({$description}) of amount {$amount} {$currency} [Type: {$type}].";
            $logStmt = $db->prepare("
                INSERT INTO audit_logs (user_id, action, target_user_id, details, ip_address) 
                VALUES (?, 'ADD_TRANSACTION', NULL, ?, ?)
            ");
            $logStmt->execute([$current_user_id, $details, $ip]);

            echo json_encode([
                "status" => "success",
                "message" => "ប្រតិបត្តិការត្រូវបានរក្សាទុកដោយជោគជ័យ!",
                "transaction_id" => $new_transaction_id
            ]);

        } catch (PDOException $e) {
            error_log("Database insertion failed in api-transactions.php: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "បរាជ័យក្នុងការរក្សាទុកទិន្នន័យទៅកាន់ Database។"]);
        }
        break;

    case 'GET':
        $action = isset($_GET['action']) ? $_GET['action'] : '';

        // --- ៣. ការគណនា Dashboard Metrics (GET ?action=metrics) ---
        if ($action === 'metrics') {
            $whereClause = " WHERE is_deleted = 0";
            $queryParams = [];

            // ដែនកំណត់សិទ្ធិ (Scoped Access)៖ User ធម្មតាមើលឃើញតែទិន្នន័យផ្ទាល់ខ្លួន
            if ($current_role !== 'super_admin' && $current_role !== 'admin') {
                $whereClause .= " AND user_id = :user_id";
                $queryParams[':user_id'] = $current_user_id;
            }

            try {
                // Initialize counters
                $totals = [
                    'income_khr' => 0, 'income_usd' => 0,
                    'expense_khr' => 0, 'expense_usd' => 0
                ];

                $metricQuery = "
                    SELECT type, currency, SUM(amount) as total_amount 
                    FROM transactions 
                    {$whereClause}
                    GROUP BY type, currency
                ";

                $stmt = $db->prepare($metricQuery);
                $stmt->execute($queryParams);
                $rows = $stmt->fetchAll();

                foreach ($rows as $row) {
                    $key = strtolower($row['type']) . '_' . strtolower($row['currency']);
                    if (isset($totals[$key])) {
                        $totals[$key] = (float)$row['total_amount'];
                    }
                }

                // គណនាសមតុល្យសរុប (Balance = Income - Expense)
                $balance_khr = $totals['income_khr'] - $totals['expense_khr'];
                $balance_usd = $totals['income_usd'] - $totals['expense_usd'];

                echo json_encode([
                    "status" => "success",
                    "data" => [
                        "income" => [
                            "KHR" => number_format($totals['income_khr']) . ' ៛',
                            "USD" => '$' . number_format($totals['income_usd'], 2)
                        ],
                        "expense" => [
                            "KHR" => number_format($totals['expense_khr']) . ' ៛',
                            "USD" => '$' . number_format($totals['expense_usd'], 2)
                        ],
                        "balance" => [
                            "KHR" => number_format($balance_khr) . ' ៛',
                            "USD" => '$' . number_format($balance_usd, 2),
                            "raw_khr" => $balance_khr,
                            "raw_usd" => $balance_usd
                        ]
                    ]
                ]);

            } catch (PDOException $e) {
                error_log("Database calculation failed in api-transactions.php: " . $e->getMessage());
                http_response_code(500);
                echo json_encode(["status" => "error", "message" => "មានបញ្ហាបច្ចេកទេសក្នុងការគណនាសមតុល្យ។"]);
            }
            exit;
        }

        // --- ៤. ការទាញយកបញ្ជីប្រតិបត្តិការជាមួយ Pagination និង Date Filter (GET) ---
        $limit = isset($_GET['limit']) ? max(1, intval($_GET['limit'])) : 10;
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $offset = ($page - 1) * $limit;
        
        // ប្រមូលតម្លៃតម្រងកាលបរិច្ឆេទ (Date Filter - e.g. 'YYYY-MM-DD')
        $filter_date = isset($_GET['date']) ? trim($_GET['date']) : '';

        // ក. គណនាចំនួនប្រតិបត្តិការសរុបដើម្បីកំណត់ទំព័រ (Total Count)
        $countQueryStr = "
            SELECT COUNT(*) 
            FROM transactions t 
            WHERE t.is_deleted = 0
        ";
        
        if (!empty($filter_date)) {
            $countQueryStr .= " AND DATE(t.date) = :filter_date";
        }

        if ($current_role !== 'super_admin' && $current_role !== 'admin') {
            $countQueryStr .= " AND t.user_id = :user_id";
        }

        try {
            $countStmt = $db->prepare($countQueryStr);
            if (!empty($filter_date)) {
                $countStmt->bindValue(':filter_date', $filter_date, PDO::PARAM_STR);
            }
            if ($current_role !== 'super_admin' && $current_role !== 'admin') {
                $countStmt->bindValue(':user_id', $current_user_id, PDO::PARAM_INT);
            }
            $countStmt->execute();
            $total_records = (int)$countStmt->fetchColumn();
            $total_pages = ceil($total_records / $limit);
        } catch (PDOException $e) {
            error_log("Database count query failed: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "មានបញ្ហាបច្ចេកទេសក្នុងការគណនាទំព័រ។"]);
            exit;
        }

        // ខ. ទាញយកទិន្នន័យប្រតិបត្តិការតាមទំព័រនីមួយៗ (Paginated Results)
        $queryStr = "
            SELECT t.*, u.username as creator_name 
            FROM transactions t 
            LEFT JOIN users u ON t.user_id = u.id
            WHERE t.is_deleted = 0
        ";

        if (!empty($filter_date)) {
            $queryStr .= " AND DATE(t.date) = :filter_date";
        }

        if ($current_role !== 'super_admin' && $current_role !== 'admin') {
            $queryStr .= " AND t.user_id = :user_id";
        }

        $queryStr .= " ORDER BY t.date DESC LIMIT :limit OFFSET :offset";

        try {
            $stmt = $db->prepare($queryStr);
            
            if (!empty($filter_date)) {
                $stmt->bindValue(':filter_date', $filter_date, PDO::PARAM_STR);
            }
            if ($current_role !== 'super_admin' && $current_role !== 'admin') {
                $stmt->bindValue(':user_id', $current_user_id, PDO::PARAM_INT);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $transactions = $stmt->fetchAll();

            $formatted_transactions = [];
            foreach ($transactions as $row) {
                $amount_display = ($row['currency'] === 'KHR') 
                    ? number_format($row['amount']) . ' ៛' 
                    : '$' . number_format($row['amount'], 2);

                $you_add_display = ($row['user_id'] == $current_user_id) ? '-' : $row['creator_name'];

                $formatted_transactions[] = [
                    "id" => $row['id'],
                    "date" => date('y-m-d H:i', strtotime($row['date'])),
                    "description" => $row['description'],
                    "creator" => $you_add_display,
                    "type" => ($row['type'] === 'income') ? 'Income' : 'Expense',
                    "raw_type" => $row['type'],
                    "amount" => $amount_display,
                    "category" => $row['category']
                ];
            }

            echo json_encode([
                "status" => "success",
                "data" => $formatted_transactions,
                "pagination" => [
                    "current_page" => $page,
                    "limit" => $limit,
                    "total_records" => $total_records,
                    "total_pages" => $total_pages
                ]
            ]);

        } catch (PDOException $e) {
            error_log("Database query failed in api-transactions.php: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "មានបញ្ហាបច្ចេកទេសក្នុងការទាញយកទិន្នន័យ។"]);
        }
        break;

    case 'DELETE':
        // --- ៥. មុខងារលុបប្រតិបត្តិការដោយសុវត្ថិភាព (Soft Delete - DELETE) ---
        if ($current_role !== 'super_admin') {
            http_response_code(403);
            echo json_encode(["status" => "error", "message" => "Forbidden: មានតែ Super Admin ប៉ុណ្ណោះដែលអាចលុបបាន។"]);
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
            // ទាញយកព័ត៌មានប្រតិបត្តិការមុនពេលលុបដើម្បីកត់ត្រាក្នុងសវនកម្ម (Audit Trail Detail)
            $getStmt = $db->prepare("SELECT description, amount, currency, type FROM `transactions` WHERE id = ?");
            $getStmt->execute([$transaction_id]);
            $txn = $getStmt->fetch();

            if (!$txn) {
                http_response_code(404);
                echo json_encode(["status" => "error", "message" => "រកមិនឃើញប្រតិបត្តិការដែលត្រូវលុបឡើយ។"]);
                exit;
            }

            // អនុវត្តវិធីសាស្ត្រ Soft Delete (is_deleted = 1) ដើម្បីរក្សាទុកប្រវត្តិគណនេយ្យ
            $stmt = $db->prepare("UPDATE `transactions` SET `is_deleted` = 1 WHERE `id` = ?");
            $stmt->execute([$transaction_id]);

            // កត់ត្រាសកម្មភាពសន្តិសុខក្នុង Audit Logs
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $details = "Soft-deleted transaction ID: {$transaction_id} ({$txn['description']}) of amount {$txn['amount']} {$txn['currency']} [Type: {$txn['type']}].";
            
            $logStmt = $db->prepare("
                INSERT INTO audit_logs (user_id, action, target_user_id, details, ip_address) 
                VALUES (?, 'DELETE_TRANSACTION', NULL, ?, ?)
            ");
            $logStmt->execute([$current_user_id, $details, $ip]);

            echo json_encode([
                "status" => "success",
                "message" => "ប្រតិបត្តិការត្រូវបានលុបដោយជោគជ័យ!"
            ]);

        } catch (PDOException $e) {
            error_log("Failed to delete transaction: " . $e->getMessage());
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