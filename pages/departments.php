<?php
/**
 * 部署管理API（実テーブル構造対応版）
 * departments テーブルの実際のカラム名に対応
 * 
 * 実際のテーブル構造:
 * - manager_name (contact_personではない)
 * - manager_phone (phone_numberではない)  
 * - manager_email (email_addressではない)
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
            case 'list':
                // 部署一覧取得（実テーブル構造対応）
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
                
                // メインクエリ実行（実際のカラム名を使用）
                $sql = "
                    SELECT 
                        d.id,
                        d.company_id,
                        d.department_code,
                        d.department_name,
                        d.parent_department_id,
                        d.department_level,
                        d.department_path,
                        d.manager_name,
                        d.manager_title,
                        d.manager_phone,
                        d.manager_email,
                        d.floor_building,
                        d.room_number,
                        d.delivery_location,
                        d.delivery_time_default,
                        d.delivery_notes,
                        d.separate_billing,
                        d.billing_contact_person,
                        d.cost_center_code,
                        d.budget_code,
                        d.employee_count,
                        d.daily_order_average,
                        d.is_active,
                        d.created_at,
                        d.updated_at,
                        c.company_name,
                        c.company_code,
                        COUNT(DISTINCT u.id) as user_count,
                        COUNT(DISTINCT o.id) as order_count,
                        COALESCE(SUM(o.total_amount), 0) as total_revenue,
                        MAX(o.delivery_date) as last_order_date,
                        COUNT(DISTINCT CASE WHEN o.delivery_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN o.id END) as recent_orders,
                        COALESCE(SUM(CASE WHEN o.delivery_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN o.total_amount ELSE 0 END), 0) as recent_revenue
                    FROM departments d
                    LEFT JOIN companies c ON d.company_id = c.id
                    LEFT JOIN users u ON d.id = u.department_id AND u.is_active = 1
                    LEFT JOIN orders o ON u.user_code = o.user_code AND o.delivery_date >= DATE_SUB(CURDATE(), INTERVAL 180 DAY)
                    {$whereClause}
                    GROUP BY d.id, d.company_id, d.department_code, d.department_name, d.parent_department_id, d.department_level, d.department_path, d.manager_name, d.manager_title, d.manager_phone, d.manager_email, d.floor_building, d.room_number, d.delivery_location, d.delivery_time_default, d.delivery_notes, d.separate_billing, d.billing_contact_person, d.cost_center_code, d.budget_code, d.employee_count, d.daily_order_average, d.is_active, d.created_at, d.updated_at, c.company_name, c.company_code
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
                        'query_params_count' => count($queryParams)
                    ]
                ], JSON_UNESCAPED_UNICODE);
                break;
                
            case 'detail':
                // 部署詳細取得（実テーブル構造対応）
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
                    GROUP BY d.id, d.company_id, d.department_code, d.department_name, d.parent_department_id, d.department_level, d.department_path, d.manager_name, d.manager_title, d.manager_phone, d.manager_email, d.floor_building, d.room_number, d.delivery_location, d.delivery_time_default, d.delivery_notes, d.separate_billing, d.billing_contact_person, d.cost_center_code, d.budget_code, d.employee_count, d.daily_order_average, d.is_active, d.created_at, d.updated_at, c.company_name, c.company_code
                ";
                
                $stmt = $db->query($sql, [$departmentId]);
                $department = $stmt->fetch();
                
                if (!$department) {
                    throw new Exception('部署が見つかりません');
                }
                
                // 注文統計取得
                $orderStatsSql = "
                    SELECT 
                        COUNT(DISTINCT o.id) as total_orders,
                        COALESCE(SUM(o.total_amount), 0) as total_revenue,
                        MAX(o.delivery_date) as last_order_date,
                        MIN(o.delivery_date) as first_order_date,
                        COALESCE(AVG(o.total_amount), 0) as avg_order_amount
                    FROM orders o
                    INNER JOIN users u ON o.user_code = u.user_code
                    WHERE u.department_id = ?
                ";
                
                $orderStatsStmt = $db->query($orderStatsSql, [$departmentId]);
                $orderStats = $orderStatsStmt->fetch();
                
                // 利用者情報取得
                $usersSql = "
                    SELECT 
                        u.id,
                        u.user_code,
                        u.user_name,
                        u.employee_type_name,
                        u.is_active,
                        COUNT(DISTINCT o.id) as order_count,
                        COALESCE(SUM(o.total_amount), 0) as total_spent,
                        MAX(o.delivery_date) as last_order_date
                    FROM users u
                    LEFT JOIN orders o ON u.user_code = o.user_code AND o.delivery_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
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
                
            case 'stats':
                // 部署統計取得
                $companyId = $_GET['company_id'] ?? null;
                
                $whereParts = [];
                $params = [];
                
                if ($companyId) {
                    $whereParts[] = "d.company_id = ?";
                    $params[] = $companyId;
                }
                
                $whereParts[] = "d.is_active = ?";
                $params[] = 1;
                
                $whereClause = 'WHERE ' . implode(' AND ', $whereParts);
                
                $sql = "
                    SELECT 
                        COUNT(DISTINCT d.id) as total_departments,
                        COUNT(DISTINCT u.id) as total_users,
                        COUNT(DISTINCT CASE WHEN u.is_active = 1 THEN u.id END) as active_users,
                        COUNT(DISTINCT c.id) as companies_with_departments
                    FROM departments d
                    LEFT JOIN companies c ON d.company_id = c.id
                    LEFT JOIN users u ON d.id = u.department_id
                    {$whereClause}
                ";
                
                $stmt = $db->query($sql, $params);
                $stats = $stmt->fetch();
                
                // 注文関連統計
                $orderStatsWhere = '';
                $orderStatsParams = [];
                
                if ($companyId) {
                    $orderStatsWhere = "WHERE u.company_id = ? AND o.delivery_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
                    $orderStatsParams = [$companyId];
                } else {
                    $orderStatsWhere = "WHERE o.delivery_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
                }
                
                $orderStatsSql = "
                    SELECT 
                        COUNT(*) as total_orders,
                        SUM(total_amount) as total_revenue,
                        COUNT(DISTINCT user_code) as ordering_users,
                        AVG(total_amount) as avg_order_amount
                    FROM orders o
                    INNER JOIN users u ON o.user_code = u.user_code
                    {$orderStatsWhere}
                ";
                
                $orderStatsStmt = $db->query($orderStatsSql, $orderStatsParams);
                $orderStats = $orderStatsStmt->fetch();
                
                $combinedStats = array_merge($stats ?: [], $orderStats ?: []);
                
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
                // 部署新規作成（実テーブル構造対応）
                $requiredFields = ['company_id', 'department_code', 'department_name'];
                foreach ($requiredFields as $field) {
                    if (empty($input[$field])) {
                        throw new Exception("必須項目が入力されていません: {$field}");
                    }
                }
                
                // 企業存在チェック
                $companyCheck = $db->query(
                    "SELECT id FROM companies WHERE id = ? AND is_active = 1", 
                    [$input['company_id']]
                );
                
                if (!$companyCheck->fetch()) {
                    throw new Exception('指定された企業が見つかりません');
                }
                
                // 重複チェック（同一企業内での部署コード重複）
                $duplicateCheck = $db->query(
                    "SELECT id FROM departments WHERE company_id = ? AND department_code = ?", 
                    [$input['company_id'], $input['department_code']]
                );
                
                if ($duplicateCheck->fetch()) {
                    throw new Exception('この企業内で同じ部署コードが既に登録されています');
                }
                
                $sql = "
                    INSERT INTO departments (
                        company_id, department_code, department_name,
                        manager_name, manager_phone, manager_email,
                        is_active, created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ";
                
                $params = [
                    $input['company_id'],
                    $input['department_code'],
                    $input['department_name'],
                    $input['manager_name'] ?? null,
                    $input['manager_phone'] ?? null,
                    $input['manager_email'] ?? null,
                    isset($input['is_active']) ? ($input['is_active'] ? 1 : 0) : 1
                ];
                
                $db->query($sql, $params);
                $newDepartmentId = $db->lastInsertId();
                
                echo json_encode([
                    'success' => true,
                    'message' => '部署を登録しました',
                    'data' => ['id' => $newDepartmentId]
                ], JSON_UNESCAPED_UNICODE);
                break;
                
            default:
                throw new Exception('不正なアクションです');
        }
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        
        $input = json_decode(file_get_contents('php://input'), true);
        $departmentId = $input['id'] ?? null;
        
        if (!$departmentId) {
            throw new Exception('部署IDが指定されていません');
        }
        
        // 存在チェック
        $departmentCheck = $db->query("SELECT id, company_id FROM departments WHERE id = ?", [$departmentId]);
        $department = $departmentCheck->fetch();
        
        if (!$department) {
            throw new Exception('部署が見つかりません');
        }
        
        $updateParts = [];
        $params = [];
        
        $allowedFields = [
            'department_name', 'manager_name', 'manager_phone', 
            'manager_email', 'is_active'
        ];
        
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $input)) {
                $updateParts[] = "{$field} = ?";
                $params[] = $input[$field];
            }
        }
        
        // 部署コード変更の場合は重複チェック
        if (array_key_exists('department_code', $input)) {
            $duplicateCheck = $db->query(
                "SELECT id FROM departments WHERE company_id = ? AND department_code = ? AND id != ?", 
                [$department['company_id'], $input['department_code'], $departmentId]
            );
            
            if ($duplicateCheck->fetch()) {
                throw new Exception('この企業内で同じ部署コードが既に登録されています');
            }
            
            $updateParts[] = "department_code = ?";
            $params[] = $input['department_code'];
        }
        
        if (empty($updateParts)) {
            throw new Exception('更新する項目がありません');
        }
        
        $updateParts[] = "updated_at = NOW()";
        $params[] = $departmentId;
        
        $sql = "UPDATE departments SET " . implode(', ', $updateParts) . " WHERE id = ?";
        $db->query($sql, $params);
        
        echo json_encode([
            'success' => true,
            'message' => '部署情報を更新しました'
        ], JSON_UNESCAPED_UNICODE);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        
        $departmentId = $_GET['id'] ?? null;
        if (!$departmentId) {
            throw new Exception('部署IDが指定されていません');
        }
        
        // 関連データ確認
        $relatedDataCheck = $db->query(
            "SELECT 
                COUNT(DISTINCT u.id) as user_count,
                COUNT(DISTINCT o.id) as order_count
             FROM departments d
             LEFT JOIN users u ON d.id = u.department_id
             LEFT JOIN orders o ON u.user_code = o.user_code
             WHERE d.id = ?", 
            [$departmentId]
        );
        
        $relatedData = $relatedDataCheck->fetch();
        
        if ($relatedData['order_count'] > 0 || $relatedData['user_count'] > 0) {
            // 論理削除
            $db->query(
                "UPDATE departments SET is_active = 0, updated_at = NOW() WHERE id = ?", 
                [$departmentId]
            );
            $message = '部署を無効化しました（関連データがあるため論理削除）';
        } else {
            // 物理削除
            $db->query("DELETE FROM departments WHERE id = ?", [$departmentId]);
            $message = '部署を削除しました';
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
    error_log("Departments API Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'クエリエラー: ' . $e->getMessage(),
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
