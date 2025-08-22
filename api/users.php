<?php
/**
 * 利用者管理API（修正版）
 * Database統一対応版
 * 
 * 修正内容:
 * 1. Database::getInstance() を使用（統一修正）
 * 2. 注文データ取得ロジック修正
 * 3. エラーハンドリング強化
 */

require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/SecurityHelper.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// セキュリティヘッダー設定
SecurityHelper::setSecurityHeaders();

// OPTIONS リクエスト対応
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    // Database::getInstance() を使用（修正箇所）
    $db = Database::getInstance();
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        
        $action = $_GET['action'] ?? 'list';
        
        switch ($action) {
            case 'list':
                // 利用者一覧取得（修正版：注文データ正しく取得）
                $companyId = $_GET['company_id'] ?? null;
                $departmentId = $_GET['department_id'] ?? null;
                $isActive = $_GET['is_active'] ?? null;
                $page = max(1, intval($_GET['page'] ?? 1));
                $limit = min(100, max(10, intval($_GET['limit'] ?? 50)));
                $offset = ($page - 1) * $limit;
                
                $where = [];
                $params = [];
                
                if ($companyId) {
                    $where[] = "u.company_id = ?";
                    $params[] = $companyId;
                }
                
                if ($departmentId) {
                    $where[] = "u.department_id = ?";
                    $params[] = $departmentId;
                }
                
                if ($isActive !== null) {
                    $where[] = "u.is_active = ?";
                    $params[] = $isActive ? 1 : 0;
                }
                
                $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
                
                // 修正版SQL：注文データとのJOIN条件を正確に
                $sql = "
                    SELECT 
                        u.*,
                        c.company_name,
                        d.department_name,
                        COUNT(DISTINCT o.id) as order_count,
                        COALESCE(SUM(o.total_amount), 0) as total_spent,
                        MAX(o.delivery_date) as last_order_date,
                        COALESCE(AVG(o.total_amount), 0) as avg_order_amount,
                        MIN(o.delivery_date) as first_order_date,
                        COUNT(DISTINCT DATE(o.delivery_date)) as order_days
                    FROM users u
                    LEFT JOIN companies c ON u.company_id = c.id
                    LEFT JOIN departments d ON u.department_id = d.id
                    LEFT JOIN orders o ON u.user_code = o.user_code AND o.delivery_date >= DATE_SUB(CURDATE(), INTERVAL 180 DAY)
                    {$whereClause}
                    GROUP BY u.id
                    ORDER BY u.user_name ASC
                    LIMIT ? OFFSET ?
                ";
                
                $params[] = $limit;
                $params[] = $offset;
                
                $stmt = $db->query($sql, $params);
                $users = $stmt->fetchAll();
                
                // 総件数取得
                $countSql = "SELECT COUNT(*) as total FROM users u {$whereClause}";
                $countParams = array_slice($params, 0, -2); // limit, offsetを除く
                $countStmt = $db->query($countSql, $countParams);
                $totalCount = $countStmt->fetch()['total'];
                
                echo json_encode([
                    'success' => true,
                    'data' => $users,
                    'pagination' => [
                        'current_page' => $page,
                        'per_page' => $limit,
                        'total' => $totalCount,
                        'total_pages' => ceil($totalCount / $limit)
                    ]
                ], JSON_UNESCAPED_UNICODE);
                break;
                
            case 'detail':
                // 利用者詳細取得（修正版）
                $userId = $_GET['id'] ?? null;
                if (!$userId) {
                    throw new Exception('利用者IDが指定されていません');
                }
                
                // 基本情報取得
                $sql = "
                    SELECT 
                        u.*,
                        c.company_name,
                        c.company_code as company_display_code,
                        d.department_name,
                        d.department_code as department_display_code
                    FROM users u
                    LEFT JOIN companies c ON u.company_id = c.id
                    LEFT JOIN departments d ON u.department_id = d.id
                    WHERE u.id = ?
                ";
                
                $stmt = $db->query($sql, [$userId]);
                $user = $stmt->fetch();
                
                if (!$user) {
                    throw new Exception('利用者が見つかりません');
                }
                
                // 注文履歴取得（修正版：user_codeで正確にマッチング）
                $orderSql = "
                    SELECT 
                        o.*,
                        p.product_name,
                        s.supplier_name
                    FROM orders o
                    LEFT JOIN products p ON o.product_code = p.product_code
                    LEFT JOIN suppliers s ON o.supplier_code = s.supplier_code
                    WHERE o.user_code = ?
                    ORDER BY o.delivery_date DESC, o.created_at DESC
                    LIMIT 50
                ";
                
                $orderStmt = $db->query($orderSql, [$user['user_code']]);
                $orders = $orderStmt->fetchAll();
                
                // 月別統計取得（修正版）
                $statsSql = "
                    SELECT 
                        DATE_FORMAT(delivery_date, '%Y-%m') as month,
                        COUNT(*) as order_count,
                        SUM(total_amount) as total_amount,
                        AVG(total_amount) as avg_amount,
                        COUNT(DISTINCT delivery_date) as order_days
                    FROM orders 
                    WHERE user_code = ? 
                        AND delivery_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                    GROUP BY DATE_FORMAT(delivery_date, '%Y-%m')
                    ORDER BY month DESC
                ";
                
                $statsStmt = $db->query($statsSql, [$user['user_code']]);
                $monthlyStats = $statsStmt->fetchAll();
                
                echo json_encode([
                    'success' => true,
                    'data' => [
                        'user' => $user,
                        'orders' => $orders,
                        'monthly_stats' => $monthlyStats
                    ]
                ], JSON_UNESCAPED_UNICODE);
                break;
                
            case 'stats':
                // 利用者統計取得（修正版）
                $sql = "
                    SELECT 
                        COUNT(*) as total_users,
                        COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_users,
                        COUNT(CASE WHEN is_active = 0 THEN 1 END) as inactive_users,
                        COUNT(DISTINCT company_id) as total_companies,
                        COUNT(DISTINCT department_id) as total_departments
                    FROM users
                ";
                
                $stmt = $db->query($sql);
                $stats = $stmt->fetch();
                
                // 注文関連統計（修正版）
                $orderStatsSql = "
                    SELECT 
                        COUNT(DISTINCT o.user_code) as users_with_orders,
                        COUNT(*) as total_orders,
                        SUM(total_amount) as total_revenue,
                        AVG(total_amount) as avg_order_amount
                    FROM orders o
                    WHERE o.delivery_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                ";
                
                $orderStatsStmt = $db->query($orderStatsSql);
                $orderStats = $orderStatsStmt->fetch();
                
                $combinedStats = array_merge($stats, $orderStats);
                
                echo json_encode([
                    'success' => true,
                    'data' => $combinedStats
                ], JSON_UNESCAPED_UNICODE);
                break;
                
            default:
                throw new Exception('不正なアクションです');
        }
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? 'create';
        
        switch ($action) {
            case 'create':
                // 利用者新規作成
                $requiredFields = ['user_code', 'user_name'];
                foreach ($requiredFields as $field) {
                    if (empty($input[$field])) {
                        throw new Exception("必須項目が入力されていません: {$field}");
                    }
                }
                
                // 重複チェック
                $duplicateCheck = $db->query(
                    "SELECT id FROM users WHERE user_code = ?", 
                    [$input['user_code']]
                );
                
                if ($duplicateCheck->fetch()) {
                    throw new Exception('この利用者コードは既に登録されています');
                }
                
                $sql = "
                    INSERT INTO users (
                        user_code, user_name, company_id, department_id,
                        employee_type_code, employee_type_name, is_active,
                        created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ";
                
                $params = [
                    $input['user_code'],
                    $input['user_name'],
                    $input['company_id'] ?? null,
                    $input['department_id'] ?? null,
                    $input['employee_type_code'] ?? null,
                    $input['employee_type_name'] ?? null,
                    isset($input['is_active']) ? ($input['is_active'] ? 1 : 0) : 1
                ];
                
                $db->query($sql, $params);
                $newUserId = $db->lastInsertId();
                
                echo json_encode([
                    'success' => true,
                    'message' => '利用者を登録しました',
                    'data' => ['id' => $newUserId]
                ], JSON_UNESCAPED_UNICODE);
                break;
                
            default:
                throw new Exception('不正なアクションです');
        }
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        
        $input = json_decode(file_get_contents('php://input'), true);
        $userId = $input['id'] ?? null;
        
        if (!$userId) {
            throw new Exception('利用者IDが指定されていません');
        }
        
        // 存在チェック
        $userCheck = $db->query("SELECT id FROM users WHERE id = ?", [$userId]);
        if (!$userCheck->fetch()) {
            throw new Exception('利用者が見つかりません');
        }
        
        $updateFields = [];
        $params = [];
        
        $allowedFields = [
            'user_name', 'company_id', 'department_id', 
            'employee_type_code', 'employee_type_name', 'is_active'
        ];
        
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $input)) {
                $updateFields[] = "{$field} = ?";
                $params[] = $input[$field];
            }
        }
        
        if (empty($updateFields)) {
            throw new Exception('更新する項目がありません');
        }
        
        $updateFields[] = "updated_at = NOW()";
        $params[] = $userId;
        
        $sql = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = ?";
        $db->query($sql, $params);
        
        echo json_encode([
            'success' => true,
            'message' => '利用者情報を更新しました'
        ], JSON_UNESCAPED_UNICODE);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        
        $userId = $_GET['id'] ?? null;
        if (!$userId) {
            throw new Exception('利用者IDが指定されていません');
        }
        
        // 関連データ確認
        $orderCheck = $db->query(
            "SELECT COUNT(*) as count FROM orders WHERE user_id = ?", 
            [$userId]
        );
        $orderCount = $orderCheck->fetch()['count'];
        
        if ($orderCount > 0) {
            // 論理削除
            $db->query(
                "UPDATE users SET is_active = 0, updated_at = NOW() WHERE id = ?", 
                [$userId]
            );
            $message = '利用者を無効化しました（注文履歴があるため論理削除）';
        } else {
            // 物理削除
            $db->query("DELETE FROM users WHERE id = ?", [$userId]);
            $message = '利用者を削除しました';
        }
        
        echo json_encode([
            'success' => true,
            'message' => $message
        ], JSON_UNESCAPED_UNICODE);
        
    } else {
        throw new Exception('サポートされていないHTTPメソッドです');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    error_log("Users API Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
