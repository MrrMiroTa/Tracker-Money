<?php
/**
 * api-transactions-v6.php - Secure Transaction Management API with Versioning & Audit History
 * Part of the Khmer Payment Tracker and Financial Management System
 * 
 * This file handles secure creation (POST), paginated/filtered retrieval (GET),
 * secure updates with version control (PUT), and secure deletions (DELETE).
 * When updates or deletions occur, the original data is archived in `transaction_history`
 * for administrative auditing (Admin-only access).
 */

header("Content-Type: application/json; charset=UTF-8");

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';

// --- ១. ផ្ទៀងផ្ទាត់ការចូលប្រើប្រាស់ (Authentication Guard) --
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
            echo json_encode(["status" => "error", "message" => "សូមបំពេញព័ត៌មានឱ្យបានត្រឹមត្រូវ និងគ្រប់គ្រាន់ (ទឹកប្រាក់ត្រូវតែធំជាង ០)។"]);
            exit;
        }

        if (!in_array($currency, ['KHR', 'USD']) || !in_array($type, ['income', 'expense'])) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "ប្រភេទរូបិយប័ណ្ណ ឬប្រភេទប្រតិបត្តិការមិនត្រឹមត្រូវឡើយ។"]);
            exit;
        }

        try {
            $stmt = $db->prepare("
                INSERT INTO `transactions` (`user_id`, `description`, `amount`, `currency`, `type`, `category`, `date`, `is_deleted`) \n                VALUES (?, ?, ?, ?, ?, ?, ?, 0)
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
            error_log("Database insertion failed: " . $e->getMessage());
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

            if ($current_role !== 'super_admin' && $current_role !== 'admin') {
                $whereClause .= " AND user_id = :user_id";
                $queryParams[':user_id'] = $current_user_id;
            }

            try {
                $totals = [
                    'income_khr' => 0, 'income_usd' => 0,
                    'expense_khr' => 0, 'expense_usd' => 0
                ];

                $metricQuery = "
                    SELECT type, currency, SUM(amount) as total_amount 
                    FROM transactions \n                    {$whereClause}
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
                error_log("Database calculation failed: " . $e->getMessage());
                http_response_code(500);
                echo json_encode(["status" => "error", "message" => "មានបញ្ហាបច្ចេកទេសក្នុងការគណនាសមតុល្យ។"]);
            }
            exit;
        }

        // --- ៤. ទាញយកប្រវត្តិការកែប្រែ/លុបប្រតិបត្តិការ (GET ?action=admin_history - Admin/Super Admin Only) ---
        if ($action === 'admin_history') {
            if ($current_role !== 'super_admin' && $current_role !== 'admin') {
                http_response_code(403);
                echo json_encode(["status" => "error", "message" => "Forbidden: ផ្នែកនេះសម្រាប់តែ Admin ប៉ុណ្ណោះដែលអាចចូលមើលបាន។"]);
                exit;
            }

            try {
                $histQuery = "
                    SELECT h.*, 
                           u.username as owner_name, 
                           ab.username as actioned_by_name
                    FROM transaction_history h
                    LEFT JOIN users u ON h.user_id = u.id
                    LEFT JOIN users ab ON h.actioned_by = ab.id
                    ORDER BY h.actioned_at DESC
                ";
                $stmt = $db->query($histQuery);
                $history = $stmt->fetchAll();

                $formatted_history = [];
                foreach ($history as $row) {
                    $orig_amount = ($row['original_currency'] === 'KHR')
                        ? number_format($row['original_amount']) . ' ៛'
                        : '$' . number_format($row['original_amount'], 2);

                    $new_amount = '-';
                    if ($row['new_amount'] !== null) {
                        $new_amount = ($row['new_currency'] === 'KHR')
                            ? number_format($row['new_amount']) . ' ៛'
                            : '$' . number_format($row['new_amount'], 2);
                    }

                    $formatted_history[] = [
                        "id" => $row['id'],
                        "transaction_id" => $row['transaction_id'],
                        "owner" => $row['owner_name'] ?? 'Unknown',
                        "original_description" => $row['original_description'],
                        "original_amount" => $orig_amount,
                        "original_type" => ucfirst($row['original_type']),
                        "original_category" => $row['original_category'],
                        "new_description" => $row['new_description'] ?? '-',
                        "new_amount" => $new_amount,
                        "new_type" => $row['new_type'] ? ucfirst($row['new_type']) : '-',
                        "new_category" => $row['new_category'] ?? '-',
                        "action_type" => $row['action_type'],
                        "actioned_by" => $row['actioned_by_name'] ?? 'Unknown',
                        "actioned_at" => date('y-m-d H:i', strtotime($row['actioned_at']))
                    ];
                }

                echo json_encode([
                    "status" => "success",
                    "data" => $formatted_history
                ]);

            } catch (PDOException $e) {
                error_log("Failed to load history: " . $e->getMessage());
                http_response_code(500);
                echo json_encode(["status" => "error", "message" => "មានបញ្ហាបច្ចេកទេសក្នុងការទាញយកប្រវត្តិកែប្រែ។"]);
            }
            exit;
        }

        // --- ៥. ការទាញយកបញ្ជីប្រតិបត្តិការជាមួយ Pagination & Date Filter (GET) ---
        $limit = isset($_GET['limit']) ? max(1, intval($_GET['limit'])) : 10;
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $offset = ($page - 1) * $limit;
        $filter_date = isset($_GET['date']) ? trim($_GET['date']) : '';

        $whereClause = " WHERE t.is_deleted = 0";
        $queryParams = [];

        if ($current_role !== 'super_admin' && $current_role !== 'admin') {
            $whereClause .= " AND t.user_id = :user_id";
            $queryParams[':user_id'] = $current_user_id;
        }

        if (!empty($filter_date)) {
            $whereClause .= " AND DATE(t.date) = :filter_date";
            $queryParams[':filter_date'] = $filter_date;
        }

        try {
            // Count query for pagination
            $countQueryStr = "SELECT COUNT(*) FROM transactions t {$whereClause}";
            $countStmt = $db->prepare($countQueryStr);
            foreach ($queryParams as $key => $val) {
                $countStmt->bindValue($key, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $countStmt->execute();
            $total_records = (int)$countStmt->fetchColumn();
            $total_pages = ceil($total_records / $limit);

            // Fetch list query
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

            $formatted_transactions = [];
            foreach ($transactions as $row) {
                $amount_display = ($row['currency'] === 'KHR') 
                    ? number_format($row['amount']) . ' ៛' 
                    : '$' . number_format($row['amount'], 2);

                $you_add_display = ($row['user_id'] == $current_user_id) ? '-' : $row['creator_name'];

                $formatted_transactions[] = [
                    "id" => $row['id'],
                    "date" => date('y-m-d H:i', strtotime($row['date'])),
                    "raw_date" => date('Y-m-d', strtotime($row['date'])),
                    "description" => $row['description'],
                    "creator" => $you_add_display,
                    "type" => ($row['type'] === 'income') ? 'Income' : 'Expense',
                    "raw_type" => $row['type'],
                    "amount" => $amount_display,
                    "raw_amount" => $row['amount'],
                    "raw_currency" => $row['currency'],
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
            error_log("Database query failed: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "មានបញ្ហាបច្ចេកទេសក្នុងការទាញយកទិន្នន័យ។"]);
        }
        break;

    case 'PUT':
        // --- ៦. មុខងារកែប្រែទិន្នន័យប្រតិបត្តិការដោយសុវត្ថិភាព និងរក្សាប្រវត្តិដើម (Update - PUT) ---
        $input = json_decode(file_get_contents("php://input"), true);
        
        $transaction_id = isset($input['transaction_id']) ? intval($input['transaction_id']) : 0;
        $description = isset($input['title']) ? trim(htmlspecialchars($input['title'])) : '';
        $amount = isset($input['amount']) ? filter_var($input['amount'], FILTER_VALIDATE_FLOAT) : false;
        $currency = isset($input['currency']) ? trim($input['currency']) : '';
        $type = isset($input['type']) ? trim($input['type']) : '';
        $category = isset($input['category']) ? trim(htmlspecialchars($input['category'])) : '';
        $date = isset($input['date']) ? trim($input['date']) : '';

        if ($transaction_id <= 0 || empty($description) || $amount === false || $amount <= 0 || empty($currency) || empty($type) || empty($category)) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "សូមបំពេញព័ត៌មានឱ្យបានត្រឹមត្រូវ និងគ្រប់គ្រាន់។"]);
            exit;
        }

        try {
            // ទាញយកព័ត៌មានប្រតិបត្តិការបច្ចុប្បន្ន ( original record )
            $getStmt = $db->prepare("SELECT * FROM `transactions` WHERE id = ?");
            $getStmt->execute([$transaction_id]);
            $txn = $getStmt->fetch();

            if (!$txn) {
                http_response_code(404);
                echo json_encode(["status" => "error", "message" => "រកមិនឃើញប្រតិបត្តិការដែលត្រូវកែប្រែឡើយ។"]);
                exit;
            }

            // Enforce Scoped Access Guard: ធម្មតា User កែបានតែរបស់ខ្លួនឯង | Admin កែបានទាំងអស់
            if ($current_role !== 'super_admin' && $current_role !== 'admin' && $txn['user_id'] != $current_user_id) {
                http_response_code(403);
                echo json_encode(["status" => "error", "message" => "Forbidden: លោកអ្នកគ្មានសិទ្ធិកែប្រែប្រតិបត្តិការរបស់អ្នកដទៃឡើយ។"]);
                exit;
            }

            // ចាប់ផ្តើម Transaction (SQL) ដើម្បីធានាថាទិន្នន័យចម្លង និងទិន្នន័យកែប្រែរត់រួមគ្នាប្រកបដោយជោគជ័យ
            $db->beginTransaction();

            // ក. ចម្លងទិន្នន័យដើមចូលទៅកាន់តារាង transaction_history
            $histStmt = $db->prepare("
                INSERT INTO `transaction_history` (
                    `transaction_id`, `user_id`, 
                    `original_description`, `original_amount`, `original_currency`, `original_type`, `original_category`,
                    `new_description`, `new_amount`, `new_currency`, `new_type`, `new_category`,
                    `action_type`, `actioned_by`
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'UPDATE', ?)
            ");
            $histStmt->execute([
                $transaction_id,
                $txn['user_id'],
                $txn['description'],
                $txn['amount'],
                $txn['currency'],
                $txn['type'],
                $txn['category'],
                $description,
                $amount,
                $currency,
                $type,
                $category,
                $current_user_id
            ]);

            // ខ. ធ្វើបច្ចុប្បន្នភាពទិន្នន័យក្នុងតារាង transactions ធំ
            $updateSql = "UPDATE `transactions` SET `description` = ?, `amount` = ?, `currency` = ?, `type` = ?, `category` = ?";
            $updateParams = [$description, $amount, $currency, $type, $category];

            if (!empty($date)) {
                $updateSql .= ", `date` = ?";
                $updateParams[] = $date;
            }
            $updateSql .= " WHERE `id` = ?";
            $updateParams[] = $transaction_id;

            $updateStmt = $db->prepare($updateSql);
            $updateStmt->execute($updateParams);

            // គ. កត់ត្រាក្នុង Audit Logs
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $details = "Updated transaction ID: {$transaction_id}. Original: [{$txn['description']}, {$txn['amount']} {$txn['currency']}], New: [{$description}, {$amount} {$currency}].";
            $logStmt = $db->prepare("
                INSERT INTO audit_logs (user_id, action, target_user_id, details, ip_address) 
                VALUES (?, 'EDIT_TRANSACTION', NULL, ?, ?)
            ");
            $logStmt->execute([$current_user_id, $details, $ip]);

            $db->commit();

            echo json_encode(["status" => "success", "message" => "ប្រតិបត្តិការត្រូវបានកែប្រែ និងរក្សាទុកប្រវត្តិដើមរួចរាល់!"]);

        } catch (PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("Failed to update transaction: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "មានបញ្ហាបច្ចេកទេសក្នុងការកែប្រែទិន្នន័យ។"]);
        }
        break;

    case 'DELETE':
        // --- ៧. មុខងារលុបប្រតិបត្តិការដោយសុវត្ថិភាព និងរក្សាទុកប្រវត្តិដើម (Soft Delete - DELETE) ---
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
                echo json_encode(["status" => "error", "message" => "រកមិនឃើញប្រតិបត្តិការដែលត្រូវលុបឡើយ។"]);
                exit;
            }

            // Enforce Scoped Access Guard: ធម្មតា User លុបបានតែរបស់ខ្លួនឯង | Admin/Super Admin លុបបានទាំងអស់
            if ($current_role !== 'super_admin' && $current_role !== 'admin' && $txn['user_id'] != $current_user_id) {
                http_response_code(403);
                echo json_encode(["status" => "error", "message" => "Forbidden: លោកអ្នកគ្មានសិទ្ធិលុបប្រតិបត្តិការរបស់អ្នកដទៃឡើយ。"]);
                exit;
            }

            $db->beginTransaction();

            // ក. ចម្លងទិន្នន័យចុងក្រោយទៅកាន់តារាង transaction_history ជាមួយសកម្មភាព DELETE
            $histStmt = $db->prepare("
                INSERT INTO `transaction_history` (
                    `transaction_id`, `user_id`, 
                    `original_description`, `original_amount`, `original_currency`, `original_type`, `original_category`,
                    `new_description`, `new_amount`, `new_currency`, `new_type`, `new_category`,
                    `action_type`, `actioned_by`
                ) VALUES (?, ?, ?, ?, ?, ?, ?, NULL, NULL, NULL, NULL, NULL, 'DELETE', ?)
            ");
            $histStmt->execute([
                $transaction_id,
                $txn['user_id'],
                $txn['description'],
                $txn['amount'],
                $txn['currency'],
                $txn['type'],
                $txn['category'],
                $current_user_id
            ]);

            // ខ. អនុវត្តការ Soft Delete (is_deleted = 1)
            $stmt = $db->prepare("UPDATE `transactions` SET `is_deleted` = 1 WHERE `id` = ?");
            $stmt->execute([$transaction_id]);

            // គ. កត់ត្រាក្នុង Audit Logs
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $details = "Soft-deleted transaction ID: {$transaction_id} ({$txn['description']}) of amount {$txn['amount']} {$txn['currency']}.";
            $logStmt = $db->prepare("
                INSERT INTO audit_logs (user_id, action, target_user_id, details, ip_address) 
                VALUES (?, 'DELETE_TRANSACTION', NULL, ?, ?)
            ");
            $logStmt->execute([$current_user_id, $details, $ip]);

            $db->commit();

            echo json_encode(["status" => "success", "message" => "Operating successfully deleted, historical data archived!"]);

        } catch (PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
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
