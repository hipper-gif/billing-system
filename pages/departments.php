<?php
/**
 * 部署管理API（修正版）
 * Database統一対応版
 * 
 * 修正内容:
 * 1. Database::getInstance() を使用（統一修正）
 * 2. 注文データ集計ロジック修正
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
                // 部署一覧取得（修正版：注文データを正確に集計）
                $companyId = $_GET['company_id'] ?? null;
                $isActive = $_GET['is_active'] ?? null;
                $page = max(1, intval($_GET['page'] ?? 1));
                $limit = min(100, max(10, intval($_GET['limit'] ?? 50)));
                $offset = ($page - 1) * $limit;
                
                $where = [];
                $params = [];
                
                if ($companyId) {
                    $where[] = "d.company_id = ?";
                    $params[] = $companyId;
                }
                
                if ($isActive !== null) {
                    $where[] = "d.is_active = ?";
                    $params[] = $isActive ? 1 : 0;
                } else {
                    $where[] = "d.is_active = 1";
                    $params[] = 1;
                }
                
                $whereClause = 'WHERE ' . implode(' AND ', $where);
                
                // 修正版SQL：user_codeを使って正確な注文データを取得
                $sql = "
                    SELECT 
                        d.*,
                        c.company_name,
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
                    GROUP BY d.id
                    ORDER BY c.company_name ASC, d.department_name ASC
                    LIMIT ? OFFSET ?
                ";
                
                $params[] = $limit;
                $params[] = $offset;
                
                $stmt = $db->query($sql, $params);
                $departments = $stmt->fetchAll();
                
                // 総件数取得
                $countSql = "SELECT COUNT(*) as total FROM departments d {$whereClause}";
                $countParams = array_slice($params, 0, -2); // limit, offsetを除く
                $countStmt = $db->query($countSql, $countParams);
                $totalCount = $countStmt->fetch()['total'];
                
                echo json_encode([
                    'success' => true,
                    'data' => $departments,
                    'pagination' => [
                        'current_page' => $page,
                        'per_page' => $limit,
                        'total' => $totalCount,
                        'total_pages' => ceil($totalCount / $limit)
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
                
                // 注文統計取得（修正版）
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
                        u.*,
                        COUNT(DISTINCT o.id) as order_count,
                        COALESCE(SUM(o.total_amount), 0) as total_spent,
                        MAX(o.delivery_date) as last_order_date
                    FROM users u
                    LEFT JOIN orders o ON u.user_code = o.user_code AND o.delivery_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
                    WHERE u.department_id = ? AND u.is_active = 1
                    GROUP BY u.id
                    ORDER BY u.user_name ASC
                ";
                
                $usersStmt = $db->query($usersSql, [$departmentId]);
                $users = $usersStmt->fetchAll();
                
                echo json_encode([
                    'success' => true,
                    'data' => [
                        'department' => array_merge($department, $orderStats),
                        'users' => $users
                    ]
                ], JSON_UNESCAPED_UNICODE);
                break;
                
            case 'stats':
                // 部署統計取得（修正版）
                $companyId = $_GET['company_id'] ?? null;
                
                $where = [];
                $params = [];
                
                if ($companyId) {
                    $where[] = "d.company_id = ?";
                    $params[] = $companyId;
                }
                
                $where[] = "d.is_active = 1";
                $params[] = 1;
                
                $whereClause = 'WHERE ' . implode(' AND ', $where);
                
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
                
                // 注文関連統計（修正版）
                $orderStatsWhere = $companyId ? "WHERE u.company_id = ?" : "";
                $orderStatsParams = $companyId ? [$companyId] : [];
                
                $orderStatsSql = "
                    SELECT 
                        COUNT(*) as total_orders,
                        SUM(total_amount) as total_revenue,
                        COUNT(DISTINCT user_code) as ordering_users,
                        AVG(total_amount) as avg_order_amount
                    FROM orders o
                    INNER JOIN users u ON o.user_code = u.user_code
                    {$orderStatsWhere}
                      AND o.delivery_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                ";
                
                $orderStatsStmt = $db->query($orderStatsSql, $orderStatsParams);
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
                // 部署新規作成
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
                        contact_person, phone_number, email_address,
                        is_active, created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ";
                
                $params = [
                    $input['company_id'],
                    $input['department_code'],
                    $input['department_name'],
                    $input['contact_person'] ?? null,
                    $input['phone_number'] ?? null,
                    $input['email_address'] ?? null,
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
        
        $updateFields = [];
        $params = [];
        
        $allowedFields = [
            'department_name', 'contact_person', 'phone_number', 
            'email_address', 'is_active'
        ];
        
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $input)) {
                $updateFields[] = "{$field} = ?";
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
            
            $updateFields[] = "department_code = ?";
            $params[] = $input['department_code'];
        }
        
        if (empty($updateFields)) {
            throw new Exception('更新する項目がありません');
        }
        
        $updateFields[] = "updated_at = NOW()";
        $params[] = $departmentId;
        
        $sql = "UPDATE departments SET " . implode(', ', $updateFields) . " WHERE id = ?";
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
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
