<?php
/**
 * 利用者管理API
 * Smiley配食事業 請求書・集金管理システム
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

require_once '../classes/Database.php';
require_once '../classes/SecurityHelper.php';

SecurityHelper::setSecurityHeaders();

try {
    $db = Database::getInstance();
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'GET':
            handleGet($db);
            break;
        case 'POST':
            handlePost($db);
            break;
        case 'PUT':
            handlePut($db);
            break;
        case 'DELETE':
            handleDelete($db);
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    }
} catch (Exception $e) {
    http_response_code(500);
    error_log("Users API Error: " . $e->getMessage());
    echo json_encode(['error' => 'Internal server error'], JSON_UNESCAPED_UNICODE);
}

/**
 * GET: 利用者一覧取得・詳細取得
 */
function handleGet($db) {
    $userId = $_GET['id'] ?? null;
    
    if ($userId) {
        getUserDetail($db, $userId);
    } else {
        getUserList($db);
    }
}

/**
 * 利用者一覧取得（企業・部署別フィルター対応）
 */
function getUserList($db) {
    $page = intval($_GET['page'] ?? 1);
    $limit = intval($_GET['limit'] ?? 20);
    $offset = ($page - 1) * $limit;
    
    $companyId = $_GET['company_id'] ?? null;
    $departmentId = $_GET['department_id'] ?? null;
    $search = $_GET['search'] ?? '';
    $status = $_GET['status'] ?? 'all';
    
    // 検索条件構築
    $whereConditions = ['u.deleted_at IS NULL'];
    $params = [];
    
    if ($companyId) {
        $whereConditions[] = 'u.company_id = ?';
        $params[] = $companyId;
    }
    
    if ($departmentId) {
        $whereConditions[] = 'u.department_id = ?';
        $params[] = $departmentId;
    }
    
    if ($search) {
        $whereConditions[] = '(u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)';
        $searchParam = "%{$search}%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    if ($status === 'active') {
        $whereConditions[] = 'u.status = "active"';
    } elseif ($status === 'inactive') {
        $whereConditions[] = 'u.status = "inactive"';
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    // 総件数取得
    $countSql = "
        SELECT COUNT(*) as total
        FROM users u
        LEFT JOIN companies c ON u.company_id = c.id
        LEFT JOIN departments d ON u.department_id = d.id
        WHERE {$whereClause}
    ";
    $countStmt = $db->prepare($countSql);
    $countStmt->execute($params);
    $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // 利用者一覧取得（注文統計含む）
    $sql = "
        SELECT 
            u.*,
            c.name as company_name,
            d.name as department_name,
            COALESCE(order_stats.total_orders, 0) as total_orders,
            COALESCE(order_stats.total_amount, 0) as total_amount,
            COALESCE(order_stats.last_order_date, NULL) as last_order_date,
            COALESCE(order_stats.recent_orders, 0) as recent_orders,
            CASE
                WHEN order_stats.last_order_date >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 'active'
                WHEN order_stats.last_order_date >= DATE_SUB(NOW(), INTERVAL 90 DAY) THEN 'warning'
                ELSE 'inactive'
            END as activity_status
        FROM users u
        LEFT JOIN companies c ON u.company_id = c.id
        LEFT JOIN departments d ON u.department_id = d.id
        LEFT JOIN (
            SELECT 
                user_id,
                COUNT(*) as total_orders,
                SUM(total_amount) as total_amount,
                MAX(order_date) as last_order_date,
                COUNT(CASE WHEN order_date >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as recent_orders
            FROM orders
            WHERE deleted_at IS NULL
            GROUP BY user_id
        ) order_stats ON u.id = order_stats.user_id
        WHERE {$whereClause}
        ORDER BY u.created_at DESC
        LIMIT ? OFFSET ?
    ";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 統計情報取得
    $statsSql = "
        SELECT 
            COUNT(*) as total_users,
            COUNT(CASE WHEN u.status = 'active' THEN 1 END) as active_users,
            COUNT(CASE WHEN order_stats.last_order_date >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as recent_active_users,
            COALESCE(SUM(order_stats.total_amount), 0) as total_sales,
            COALESCE(SUM(order_stats.recent_amount), 0) as recent_sales
        FROM users u
        LEFT JOIN (
            SELECT 
                user_id,
                SUM(total_amount) as total_amount,
                MAX(order_date) as last_order_date,
                SUM(CASE WHEN order_date >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN total_amount ELSE 0 END) as recent_amount
            FROM orders
            WHERE deleted_at IS NULL
            GROUP BY user_id
        ) order_stats ON u.id = order_stats.user_id
        WHERE u.deleted_at IS NULL
    ";
    
    $statsStmt = $db->prepare($statsSql);
    $statsStmt->execute();
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'users' => $users,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => ceil($totalCount / $limit),
            'total_count' => $totalCount,
            'per_page' => $limit
        ],
        'stats' => $stats
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * 利用者詳細取得
 */
function getUserDetail($db, $userId) {
    // 基本情報取得
    $sql = "
        SELECT 
            u.*,
            c.name as company_name,
            c.address as company_address,
            c.phone as company_phone,
            d.name as department_name,
            d.manager_name as department_manager,
            d.phone as department_phone
        FROM users u
        LEFT JOIN companies c ON u.company_id = c.id
        LEFT JOIN departments d ON u.department_id = d.id
        WHERE u.id = ? AND u.deleted_at IS NULL
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        http_response_code(404);
        echo json_encode(['error' => '利用者が見つかりません'], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    // 注文履歴取得（直近20件）
    $ordersSql = "
        SELECT 
            o.*,
            p.name as product_name,
            p.price as product_price
        FROM orders o
        LEFT JOIN products p ON o.product_id = p.id
        WHERE o.user_id = ? AND o.deleted_at IS NULL
        ORDER BY o.order_date DESC, o.created_at DESC
        LIMIT 20
    ";
    
    $ordersStmt = $db->prepare($ordersSql);
    $ordersStmt->execute([$userId]);
    $orders = $ordersStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 月別注文統計（過去12ヶ月）
    $monthlyStatsSql = "
        SELECT 
            DATE_FORMAT(order_date, '%Y-%m') as month,
            COUNT(*) as order_count,
            SUM(total_amount) as total_amount
        FROM orders
        WHERE user_id = ? 
            AND order_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
            AND deleted_at IS NULL
        GROUP BY DATE_FORMAT(order_date, '%Y-%m')
        ORDER BY month DESC
    ";
    
    $monthlyStatsStmt = $db->prepare($monthlyStatsSql);
    $monthlyStatsStmt->execute([$userId]);
    $monthlyStats = $monthlyStatsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 総計統計
    $totalStatsSql = "
        SELECT 
            COUNT(*) as total_orders,
            SUM(total_amount) as total_amount,
            AVG(total_amount) as average_amount,
            MAX(order_date) as last_order_date,
            MIN(order_date) as first_order_date,
            COUNT(CASE WHEN order_date >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as recent_orders
        FROM orders
        WHERE user_id = ? AND deleted_at IS NULL
    ";
    
    $totalStatsStmt = $db->prepare($totalStatsSql);
    $totalStatsStmt->execute([$userId]);
    $totalStats = $totalStatsStmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'user' => $user,
        'orders' => $orders,
        'monthly_stats' => $monthlyStats,
        'total_stats' => $totalStats
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * POST: 新規利用者作成
 */
function handlePost($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON input'], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    // バリデーション
    $errors = validateUserInput($input);
    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode(['errors' => $errors], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    // 重複チェック（同一企業・部署内での名前重複）
    $duplicateCheckSql = "
        SELECT id FROM users 
        WHERE company_id = ? AND department_id = ? AND name = ? AND deleted_at IS NULL
    ";
    $duplicateStmt = $db->prepare($duplicateCheckSql);
    $duplicateStmt->execute([$input['company_id'], $input['department_id'], $input['name']]);
    
    if ($duplicateStmt->fetch()) {
        http_response_code(409);
        echo json_encode(['error' => '同じ企業・部署に同名の利用者が既に存在します'], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    // 利用者作成
    $sql = "
        INSERT INTO users (
            company_id, department_id, name, email, phone, 
            address, allergies, dietary_restrictions, payment_method,
            status, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())
    ";
    
    $stmt = $db->prepare($sql);
    $result = $stmt->execute([
        $input['company_id'],
        $input['department_id'],
        $input['name'],
        $input['email'] ?? null,
        $input['phone'] ?? null,
        $input['address'] ?? null,
        $input['allergies'] ?? null,
        $input['dietary_restrictions'] ?? null,
        $input['payment_method'] ?? 'company'
    ]);
    
    if ($result) {
        $userId = $db->lastInsertId();
        echo json_encode(['success' => true, 'user_id' => $userId], JSON_UNESCAPED_UNICODE);
    } else {
        http_response_code(500);
        echo json_encode(['error' => '利用者の作成に失敗しました'], JSON_UNESCAPED_UNICODE);
    }
}

/**
 * PUT: 利用者情報更新
 */
function handlePut($db) {
    $userId = $_GET['id'] ?? null;
    if (!$userId) {
        http_response_code(400);
        echo json_encode(['error' => 'User ID is required'], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON input'], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    // バリデーション
    $errors = validateUserInput($input, $userId);
    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode(['errors' => $errors], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    // 利用者存在確認
    $checkSql = "SELECT id FROM users WHERE id = ? AND deleted_at IS NULL";
    $checkStmt = $db->prepare($checkSql);
    $checkStmt->execute([$userId]);
    
    if (!$checkStmt->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => '利用者が見つかりません'], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    // 更新
    $sql = "
        UPDATE users SET
            company_id = ?, department_id = ?, name = ?, email = ?,
            phone = ?, address = ?, allergies = ?, dietary_restrictions = ?,
            payment_method = ?, status = ?, updated_at = NOW()
        WHERE id = ?
    ";
    
    $stmt = $db->prepare($sql);
    $result = $stmt->execute([
        $input['company_id'],
        $input['department_id'],
        $input['name'],
        $input['email'] ?? null,
        $input['phone'] ?? null,
        $input['address'] ?? null,
        $input['allergies'] ?? null,
        $input['dietary_restrictions'] ?? null,
        $input['payment_method'] ?? 'company',
        $input['status'] ?? 'active',
        $userId
    ]);
    
    if ($result) {
        echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
    } else {
        http_response_code(500);
        echo json_encode(['error' => '利用者情報の更新に失敗しました'], JSON_UNESCAPED_UNICODE);
    }
}

/**
 * DELETE: 利用者削除
 */
function handleDelete($db) {
    $userId = $_GET['id'] ?? null;
    if (!$userId) {
        http_response_code(400);
        echo json_encode(['error' => 'User ID is required'], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    // 注文履歴確認
    $orderCheckSql = "SELECT COUNT(*) as order_count FROM orders WHERE user_id = ? AND deleted_at IS NULL";
    $orderCheckStmt = $db->prepare($orderCheckSql);
    $orderCheckStmt->execute([$userId]);
    $orderCount = $orderCheckStmt->fetch(PDO::FETCH_ASSOC)['order_count'];
    
    if ($orderCount > 0) {
        // 論理削除
        $sql = "UPDATE users SET deleted_at = NOW() WHERE id = ?";
        $deleteType = 'soft';
    } else {
        // 物理削除
        $sql = "DELETE FROM users WHERE id = ?";
        $deleteType = 'hard';
    }
    
    $stmt = $db->prepare($sql);
    $result = $stmt->execute([$userId]);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'delete_type' => $deleteType
        ], JSON_UNESCAPED_UNICODE);
    } else {
        http_response_code(500);
        echo json_encode(['error' => '利用者の削除に失敗しました'], JSON_UNESCAPED_UNICODE);
    }
}

/**
 * 利用者入力バリデーション
 */
function validateUserInput($input, $userId = null) {
    $errors = [];
    
    // 必須項目チェック
    if (empty($input['company_id'])) {
        $errors['company_id'] = '企業を選択してください';
    }
    
    if (empty($input['department_id'])) {
        $errors['department_id'] = '部署を選択してください';
    }
    
    if (empty($input['name']) || strlen(trim($input['name'])) < 1) {
        $errors['name'] = '利用者名を入力してください';
    }
    
    // 文字数制限
    if (!empty($input['name']) && mb_strlen($input['name']) > 100) {
        $errors['name'] = '利用者名は100文字以内で入力してください';
    }
    
    if (!empty($input['email'])) {
        if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = '正しいメールアドレスを入力してください';
        }
        if (mb_strlen($input['email']) > 255) {
            $errors['email'] = 'メールアドレスは255文字以内で入力してください';
        }
    }
    
    if (!empty($input['phone']) && mb_strlen($input['phone']) > 20) {
        $errors['phone'] = '電話番号は20文字以内で入力してください';
    }
    
    if (!empty($input['address']) && mb_strlen($input['address']) > 255) {
        $errors['address'] = '住所は255文字以内で入力してください';
    }
    
    if (!empty($input['allergies']) && mb_strlen($input['allergies']) > 500) {
        $errors['allergies'] = 'アレルギー情報は500文字以内で入力してください';
    }
    
    if (!empty($input['dietary_restrictions']) && mb_strlen($input['dietary_restrictions']) > 500) {
        $errors['dietary_restrictions'] = '食事制限は500文字以内で入力してください';
    }
    
    // 支払い方法チェック
    $validPaymentMethods = ['company', 'individual', 'cash'];
    if (!empty($input['payment_method']) && !in_array($input['payment_method'], $validPaymentMethods)) {
        $errors['payment_method'] = '正しい支払い方法を選択してください';
    }
    
    // ステータスチェック
    $validStatuses = ['active', 'inactive'];
    if (!empty($input['status']) && !in_array($input['status'], $validStatuses)) {
        $errors['status'] = '正しいステータスを選択してください';
    }
    
    return $errors;
}
?>
