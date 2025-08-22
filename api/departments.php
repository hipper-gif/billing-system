<?php
/**
 * データ診断・JOIN修正版
 * 注文データが表示されない問題の根本解決
 * 
 * 診断ポイント:
 * 1. orders テーブルにデータが存在するか
 * 2. users と orders の関連付けが正しいか
 * 3. department_id の整合性チェック
 * 4. user_code の一致確認
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
    // Database::getInstance() を使用
    $db = Database::getInstance();
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        
        $action = $_GET['action'] ?? 'list';
        
        switch ($action) {
            case 'diagnosis':
                // データ診断機能
                $diagnosis = [];
                
                // 1. orders テーブルのデータ確認
                $ordersCheck = $db->query("SELECT COUNT(*) as count FROM orders");
                $ordersCount = $ordersCheck->fetch()['count'];
                $diagnosis['orders_total'] = $ordersCount;
                
                // 2. orders テーブルのサンプルデータ
                $ordersSample = $db->query("SELECT user_code, user_name, delivery_date, total_amount FROM orders LIMIT 5");
                $diagnosis['orders_sample'] = $ordersSample->fetchAll();
                
                // 3. users テーブルのデータ確認
                $usersCheck = $db->query("SELECT COUNT(*) as count FROM users");
                $usersCount = $usersCheck->fetch()['count'];
                $diagnosis['users_total'] = $usersCount;
                
                // 4. users テーブルのサンプルデータ
                $usersSample = $db->query("SELECT user_code, user_name, department_id FROM users LIMIT 5");
                $diagnosis['users_sample'] = $usersSample->fetchAll();
                
                // 5. user_code の一致確認（MariaDB対応版）
                $matchCheck = $db->query("
                    SELECT 
                        (SELECT COUNT(DISTINCT user_code) FROM users) as users_with_code,
                        (SELECT COUNT(DISTINCT user_code) FROM orders) as orders_with_code,
                        COUNT(DISTINCT u.user_code) as matching_codes
                    FROM users u
                    INNER JOIN orders o ON u.user_code = o.user_code
                ");
                $diagnosis['user_code_match'] = $matchCheck->fetch();
                
                // 6. department_id の関連付け確認
                $deptCheck = $db->query("
                    SELECT 
                        d.id as dept_id,
                        d.department_name,
                        COUNT(DISTINCT u.id) as users_count,
                        COUNT(DISTINCT o.id) as orders_count_via_users
                    FROM departments d
                    LEFT JOIN users u ON d.id = u.department_id
                    LEFT JOIN orders o ON u.user_code = o.user_code
                    GROUP BY d.id, d.department_name
                ");
                $diagnosis['department_relation'] = $deptCheck->fetchAll();
                
                echo json_encode([
                    'success' => true,
                    'data' => $diagnosis
                ], JSON_UNESCAPED_UNICODE);
                break;
                
            case 'list':
                // 修正版：複数のJOINパターンを試行
                $companyId = $_GET['company_id'] ?? null;
                $isActive = $_GET['is_active'] ?? null;
                $page = max(1, intval($_GET['page'] ?? 1));
                $limit = min(100, max(10, intval($_GET['limit'] ?? 50)));
                $offset = ($page - 1) * $limit;
                
                // WHERE句とパラメータを段階的に構築
                $whereParts = [];
                $params = [];
                
                // 企業IDフィルター
                if ($companyId) {
                    $whereParts[] = "d.company_id = ?";
                    $params[] = $companyId;
                }
                
                // アクティブ状態フィルター
                if ($isActive !== null) {
                    $whereParts[] = "d.is_active = ?";
                    $params[] = $isActive ? 1 : 0;
                } else {
                    // デフォルトはアクティブのみ
                    $whereParts[] = "d.is_active = ?";
                    $params[] = 1;
                }
                
                // WHERE句構築
                $whereClause = !empty($whereParts) ? 'WHERE ' . implode(' AND ', $whereParts) : '';
                
                // 修正版SQL：複数の関連付けパターンに対応
                $sql = "
                    SELECT 
                        d.id,
                        d.company_id,
                        d.department_code,
                        d.department_name,
                        d.manager_name,
                        d.manager_phone,
                        d.manager_email,
                        d.is_active,
                        d.created_at,
                        d.updated_at,
                        c.company_name,
                        c.company_code,
                        COUNT(DISTINCT u.id) as user_count,
                        
                        -- 注文データ取得：複数パターンを統合
                        COUNT(DISTINCT COALESCE(o1.id, o2.id, o3.id)) as order_count,
                        COALESCE(SUM(COALESCE(o1.total_amount, o2.total_amount, o3.total_amount)), 0) as total_revenue,
                        MAX(COALESCE(o1.delivery_date, o2.delivery_date, o3.delivery_date)) as last_order_date,
                        
                        COUNT(DISTINCT CASE 
                            WHEN COALESCE(o1.delivery_date, o2.delivery_date, o3.delivery_date) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) 
                            THEN COALESCE(o1.id, o2.id, o3.id) 
                        END) as recent_orders,
                        
                        COALESCE(SUM(CASE 
                            WHEN COALESCE(o1.delivery_date, o2.delivery_date, o3.delivery_date) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) 
                            THEN COALESCE(o1.total_amount, o2.total_amount, o3.total_amount) 
                            ELSE 0 
                        END), 0) as recent_revenue
                        
                    FROM departments d
                    LEFT JOIN companies c ON d.company_id = c.id
                    LEFT JOIN users u ON d.id = u.department_id AND u.is_active = 1
                    
                    -- パターン1: user_code での関連付け（推奨）
                    LEFT JOIN orders o1 ON u.user_code = o1.user_code AND o1.delivery_date >= DATE_SUB(CURDATE(), INTERVAL 180 DAY)
                    
                    -- パターン2: user_id での関連付け（backup）
                    LEFT JOIN orders o2 ON u.id = o2.user_id AND o2.delivery_date >= DATE_SUB(CURDATE(), INTERVAL 180 DAY)
                    
                    -- パターン3: department_id 直接関連付け（orders テーブルに department_id がある場合）
                    LEFT JOIN orders o3 ON d.id = o3.department_id AND o3.delivery_date >= DATE_SUB(CURDATE(), INTERVAL 180 DAY)
                    
                    {$whereClause}
                    GROUP BY d.id, d.company_id, d.department_code, d.department_name, d.manager_name, d.manager_phone, d.manager_email, d.is_active, d.created_at, d.updated_at, c.company_name, c.company_code
                    ORDER BY c.company_name ASC, d.department_name ASC
                    LIMIT ? OFFSET ?
                ";
                
                // LIMITとOFFSETパラメータを追加
                $queryParams = array_merge($params, [$limit, $offset]);
                
                $stmt = $db->query($sql, $queryParams);
                $departments = $stmt->fetchAll();
                
                // 総件数取得（同じWHERE条件を使用）
                $countSql = "SELECT COUNT(*) as total FROM departments d {$whereClause}";
                $countStmt = $db->query($countSql, $params);
                $totalCount = $countStmt->fetch()['total'];
                
                echo json_encode([
                    'success' => true,
                    'data' => $departments,
                    'pagination' => [
                        'current_page' => $page,
                        'per_page' => $limit,
                        'total' => $totalCount,
                        'total_pages' => ceil($totalCount / $limit)
                    ],
                    'debug' => [
                        'where_clause' => $whereClause,
                        'params_count' => count($params),
                        'query_params_count' => count($queryParams),
                        'sql_preview' => substr($sql, 0, 200) . '...'
                    ]
                ], JSON_UNESCAPED_UNICODE);
                break;
                
            case 'detail':
                // 部署詳細取得（修正版）
                $departmentId = $_GET['id'] ?? null;
                if (!$departmentId) {
                    throw new Exception('部署IDが指定されていません');
                }
                
                // 基本情報取得
                $sql = "
                    SELECT 
                        d.*,
                        c.company_name,
                        c.company_code,
                        COUNT(DISTINCT u.id) as user_count
                    FROM departments d
                    LEFT JOIN companies c ON d.company_id = c.id
                    LEFT JOIN users u ON d.id = u.department_id AND u.is_active = 1
                    WHERE d.id = ?
                    GROUP BY d.id
                ";
                
                $stmt = $db->query($sql, [$departmentId]);
                $department = $stmt->fetch();
                
                if (!$department) {
                    throw new Exception('部署が見つかりません');
                }
                
                // 注文統計取得（修正版：複数パターン対応）
                $orderStatsSql = "
                    SELECT 
                        COUNT(DISTINCT COALESCE(o1.id, o2.id)) as total_orders,
                        COALESCE(SUM(COALESCE(o1.total_amount, o2.total_amount)), 0) as total_revenue,
                        MAX(COALESCE(o1.delivery_date, o2.delivery_date)) as last_order_date,
                        MIN(COALESCE(o1.delivery_date, o2.delivery_date)) as first_order_date,
                        COALESCE(AVG(COALESCE(o1.total_amount, o2.total_amount)), 0) as avg_order_amount
                    FROM users u
                    LEFT JOIN orders o1 ON u.user_code = o1.user_code
                    LEFT JOIN orders o2 ON u.id = o2.user_id
                    WHERE u.department_id = ?
                ";
                
                $orderStatsStmt = $db->query($orderStatsSql, [$departmentId]);
                $orderStats = $orderStatsStmt->fetch();
                
                // 利用者情報取得（修正版）
                $usersSql = "
                    SELECT 
                        u.id,
                        u.user_code,
                        u.user_name,
                        u.employee_type_name,
                        u.is_active,
                        COUNT(DISTINCT COALESCE(o1.id, o2.id)) as order_count,
                        COALESCE(SUM(COALESCE(o1.total_amount, o2.total_amount)), 0) as total_spent,
                        MAX(COALESCE(o1.delivery_date, o2.delivery_date)) as last_order_date
                    FROM users u
                    LEFT JOIN orders o1 ON u.user_code = o1.user_code AND o1.delivery_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
                    LEFT JOIN orders o2 ON u.id = o2.user_id AND o2.delivery_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
                    WHERE u.department_id = ? AND u.is_active = 1
                    GROUP BY u.id, u.user_code, u.user_name, u.employee_type_name, u.is_active
                    ORDER BY u.user_name ASC
                ";
                
                $usersStmt = $db->query($usersSql, [$departmentId]);
                $users = $usersStmt->fetchAll();
                
                echo json_encode([
                    'success' => true,
                    'data' => [
                        'department' => array_merge($department, $orderStats ?: []),
                        'users' => $users
                    ]
                ], JSON_UNESCAPED_UNICODE);
                break;
                
            default:
                throw new Exception('不正なアクションです');
        }
        
    } else {
        throw new Exception('サポートされていないHTTPメソッドです');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    error_log("Data Diagnosis API Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'データ診断エラー: ' . $e->getMessage(),
        'debug' => [
            'file' => basename(__FILE__),
            'line' => $e->getLine(),
            'request_method' => $_SERVER['REQUEST_METHOD'],
            'get_params' => $_GET,
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ], JSON_UNESCAPED_UNICODE);
}
?>
