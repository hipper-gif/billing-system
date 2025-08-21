<?php
/**
 * 利用者管理API（実テーブル構造対応版）
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
    
    // デバッグモード確認
    $debug = $_GET['debug'] ?? null;
    
    if ($debug === 'true') {
        handleDebug($db);
        return;
    }
    
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
    echo json_encode([
        'error' => 'Internal server error',
        'debug_message' => $e->getMessage(),
        'debug_file' => $e->getFile(),
        'debug_line' => $e->getLine()
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * デバッグ情報取得
 */
function handleDebug($db) {
    $debug_info = [];
    
    try {
        // 1. データベース接続確認
        $debug_info['database_connection'] = 'SUCCESS';
        
        // 2. usersテーブルデータ数確認
        $countSql = "SELECT COUNT(*) as total FROM users WHERE is_active = 1";
        $countStmt = $db->prepare($countSql);
        $countStmt->execute();
        $debug_info['users_count'] = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // 3. 簡単なデータサンプル確認（最初の3件）
        $sampleSql = "
            SELECT 
                u.id, u.user_code, u.user_name, u.company_id, u.department_id,
                u.company_name, u.department, u.email, u.phone, u.payment_method,
                u.is_active
            FROM users u 
            WHERE u.is_active = 1 
            LIMIT 3
        ";
        $sampleStmt = $db->prepare($sampleSql);
        $sampleStmt->execute();
        $debug_info['users_sample'] = $sampleStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 4. companiesテーブル確認
        $companiesSql = "SELECT COUNT(*) as total FROM companies WHERE is_active = 1";
        $companiesStmt = $db->prepare($companiesSql);
        $companiesStmt->execute();
        $debug_info['companies_count'] = $companiesStmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // 5. departmentsテーブル確認
        $departmentsSql = "SELECT COUNT(*) as total FROM departments WHERE is_active = 1";
        $departmentsStmt = $db->prepare($departmentsSql);
        $departmentsStmt->execute();
        $debug_info['departments_count'] = $departmentsStmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // 6. ordersテーブル確認
        $ordersSql = "SELECT COUNT(*) as total FROM orders";
        $ordersStmt = $db->prepare($ordersSql);
        $ordersStmt->execute();
        $debug_info['orders_count'] = $ordersStmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        echo json_encode($debug_info, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        
    } catch (Exception $e) {
        echo json_encode([
            'debug_error' => $e->getMessage(),
            'debug_file' => $e->getFile(),
            'debug_line' => $e->getLine()
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
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
 * 利用者一覧取得（実テーブル構造対応）
 */
function getUserList($db) {
    try {
        $page = intval($_GET['page'] ?? 1);
        $limit = intval($_GET['limit'] ?? 20);
        $offset = ($page - 1) * $limit;
        
        $companyId = $_GET['company_id'] ?? null;
        $departmentId = $_GET['department_id'] ?? null;
        $search = $_GET['search'] ?? '';
        $status = $_GET['status'] ?? 'all';
        
        // 検索条件構築
        $whereConditions = ['u.is_active = 1'];
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
            $whereConditions[] = '(u.user_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)';
            $searchParam = "%{$search}%";
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
        }
        
        $whereClause = implode(' AND ', $whereConditions);
        
        // 総件数取得
        $countSql = "
            SELECT COUNT(*) as total
            FROM users u
            WHERE {$whereClause}
        ";
        $countStmt = $db->prepare($countSql);
        $countStmt->execute($params);
        $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // 利用者一覧取得（注文統計含む）
        $sql = "
            SELECT 
                u.*,
                c.company_name as company_name_from_table,
                d.department_name,
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
                COUNT(CASE WHEN u.is_active = 1 THEN 1 END) as active_users,
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
                GROUP BY user_id
            ) order_stats ON u.id = order_stats.user_id
            WHERE u.is_active = 1
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
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'error' => 'データ取得エラー',
            'debug_message' => $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
}

/**
 * 利用者詳細取得
 */
function getUserDetail($db, $userId) {
    try {
        // 基本情報取得
        $sql = "
            SELECT 
                u.*,
                c.company_name as company_name_from_table,
                c.company_address,
                c.contact_phone as company_phone,
                d.department_name,
                d.manager_name as department_manager,
                d.manager_phone as department_phone
            FROM users u
            LEFT JOIN companies c ON u.company_id = c.id
            LEFT JOIN departments d ON u.department_id = d.id
            WHERE u.id = ? AND u.is_active = 1
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
                o.*
            FROM orders o
            WHERE o.user_id = ?
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
            WHERE user_id = ?
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
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'error' => 'データ取得エラー',
            'debug_message' => $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
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
    
    try {
        // user_codeの自動生成（簡易版）
        $userCode = 'USR' . date('Ymd') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        // 利用者作成
        $sql = "
            INSERT INTO users (
                user_code, user_name, company_id, department_id, 
                company_name, department, email, phone, address, 
                payment_method, is_active, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
        ";
        
        $stmt = $db->prepare($sql);
        $result = $stmt->execute([
            $userCode,
            $input['user_name'],
            $input['company_id'],
            $input['department_id'] ?? null,
            $input['company_name'] ?? '',
            $input['
