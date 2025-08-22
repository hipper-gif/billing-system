<?php
/**
 * 企業管理API（修正版）
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
                // 企業一覧取得（修正版：注文データを正確に集計）
                $isActive = $_GET['is_active'] ?? null;
                $page = max(1, intval($_GET['page'] ?? 1));
                $limit = min(100, max(10, intval($_GET['limit'] ?? 50)));
                $offset = ($page - 1) * $limit;
                
                $where = [];
                $params = [];
                
                if ($isActive !== null) {
                    $where[] = "c.is_active = ?";
                    $params[] = $isActive ? 1 : 0;
                }
                
                $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : 'WHERE c.is_active = 1';
                if (empty($where)) {
                    $params[] = 1;
                }
                
                // 修正版SQL：user_codeを使って正確な注文データを取得
                $sql = "
                    SELECT 
                        c.*,
                        COUNT(DISTINCT d.id) as department_count,
                        COUNT(DISTINCT u.id) as user_count,
                        COUNT(DISTINCT o.id) as order_count,
                        COALESCE(SUM(o.total_amount), 0) as total_revenue,
                        MAX(o.delivery_date) as last_order_date,
                        COUNT(DISTINCT CASE WHEN o.delivery_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN o.id END) as recent_orders,
                        COALESCE(SUM(CASE WHEN o.delivery_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN o.total_amount ELSE 0 END), 0) as recent_revenue
                    FROM companies c
                    LEFT JOIN departments d ON c.id = d.company_id AND d.is_active = 1
                    LEFT JOIN users u ON c.id = u.company_id AND u.is_active = 1
                    LEFT JOIN orders o ON u.user_code = o.user_code AND o.delivery_date >= DATE_SUB(CURDATE(), INTERVAL 180 DAY)
                    {$whereClause}
                    GROUP BY c.id
                    ORDER BY c.company_name ASC
                    LIMIT ? OFFSET ?
                ";
                
                $params[] = $limit;
                $params[] = $offset;
                
                $stmt = $db->query($sql, $params);
                $companies = $stmt->fetchAll();
                
                // 総件数取得
                $countSql = "SELECT COUNT(*) as total FROM companies c {$whereClause}";
                $countParams = array_slice($params, 0, -2); // limit, offsetを除く
                $countStmt = $db->query($countSql, $countParams);
                $totalCount = $countStmt->fetch()['total'];
                
                echo json_encode([
                    'success' => true,
                    'data' => $companies,
                    'pagination' => [
                        'current_page' => $page,
                        'per_page' => $limit,
                        'total' => $totalCount,
                        'total_pages' => ceil($totalCount / $limit)
                    ]
                ], JSON_UNESCAPED_UNICODE);
                break;
                
            case 'detail':
                // 企業詳細取得（修正版）
                $companyId = $_GET['id'] ?? null;
                if (!$companyId) {
                    throw new Exception('企業IDが指定されていません');
                }
                
                // 基本情報取得
                $sql = "
                    SELECT 
                        c.*,
                        COUNT(DISTINCT d.id) as department_count,
                        COUNT(DISTINCT u.id) as user_count
                    FROM companies c
                    LEFT JOIN departments d ON c.id = d.company_id AND d.is_active = 1
                    LEFT JOIN users u ON c.id = u.company_id AND u.is_active = 1
                    WHERE c.id = ?
                    GROUP BY c.id
                ";
                
                $stmt = $db->query($sql, [$companyId]);
                $company = $stmt->fetch();
                
                if (!$company) {
                    throw new Exception('企業が見つかりません');
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
                    WHERE u.company_id = ?
                ";
                
                $orderStatsStmt = $db->query($orderStatsSql, [$companyId]);
                $orderStats = $orderStatsStmt->fetch();
                
                // 部署情報取得
                $departmentsSql = "
                    SELECT 
                        d.*,
                        COUNT(DISTINCT u.id) as user_count,
                        COUNT(DISTINCT o.id) as order_count,
                        COALESCE(SUM(o.total_amount), 0) as total_revenue
                    FROM departments d
                    LEFT JOIN users u ON d.id = u.department_id AND u.is_active = 1
                    LEFT JOIN orders o ON u.user_code = o.user_code AND o.delivery_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
                    WHERE d.company_id = ? AND d.is_active = 1
                    GROUP BY d.id
                    ORDER BY d.department_name ASC
                ";
                
                $deptStmt = $db->query($departmentsSql, [$companyId]);
                $departments = $deptStmt->fetchAll();
                
                echo json_encode([
                    'success' => true,
                    'data' => [
                        'company' => array_merge($company, $orderStats),
                        'departments' => $departments
                    ]
                ], JSON_UNESCAPED_UNICODE);
                break;
                
            case 'stats':
                // 企業統計取得（修正版）
                $sql = "
                    SELECT 
                        COUNT(*) as total_companies,
                        COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_companies,
                        COUNT(CASE WHEN is_active = 0 THEN 1 END) as inactive_companies
                    FROM companies
                ";
                
                $stmt = $db->query($sql);
                $stats = $stmt->fetch();
                
                // 部署・利用者統計
                $detailStatsSql = "
                    SELECT 
                        COUNT(DISTINCT d.id) as total_departments,
                        COUNT(DISTINCT u.id) as total_users,
                        COUNT(DISTINCT CASE WHEN u.is_active = 1 THEN u.id END) as active_users
                    FROM companies c
                    LEFT JOIN departments d ON c.id = d.company_id AND d.is_active = 1
                    LEFT JOIN users u ON c.id = u.company_id
                    WHERE c.is_active = 1
                ";
                
                $detailStatsStmt = $db->query($detailStatsSql);
                $detailStats = $detailStatsStmt->fetch();
                
                // 注文関連統計（修正版）
                $orderStatsSql = "
                    SELECT 
                        COUNT(*) as total_orders,
                        SUM(total_amount) as total_revenue,
                        COUNT(DISTINCT user_code) as ordering_users,
                        AVG(total_amount) as avg_order_amount
                    FROM orders
                    WHERE delivery_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                ";
                
                $orderStatsStmt = $db->query($orderStatsSql);
                $orderStats = $orderStatsStmt->fetch();
                
                $combinedStats = array_merge($stats, $detailStats, $orderStats);
                
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
                // 企業新規作成
                $requiredFields = ['company_code', 'company_name'];
                foreach ($requiredFields as $field) {
                    if (empty($input[$field])) {
                        throw new Exception("必須項目が入力されていません: {$field}");
                    }
                }
                
                // 重複チェック
                $duplicateCheck = $db->query(
                    "SELECT id FROM companies WHERE company_code = ?", 
                    [$input['company_code']]
                );
                
                if ($duplicateCheck->fetch()) {
                    throw new Exception('この企業コードは既に登録されています');
                }
                
                $sql = "
                    INSERT INTO companies (
                        company_code, company_name, address_detail,
                        contact_person, phone_number, email_address,
                        billing_method, is_active, created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ";
                
                $params = [
                    $input['company_code'],
                    $input['company_name'],
                    $input['address_detail'] ?? null,
                    $input['contact_person'] ?? null,
                    $input['phone_number'] ?? null,
                    $input['email_address'] ?? null,
                    $input['billing_method'] ?? 'company',
                    isset($input['is_active']) ? ($input['is_active'] ? 1 : 0) : 1
                ];
                
                $db->query($sql, $params);
                $newCompanyId = $db->lastInsertId();
                
                echo json_encode([
                    'success' => true,
                    'message' => '企業を登録しました',
                    'data' => ['id' => $newCompanyId]
                ], JSON_UNESCAPED_UNICODE);
                break;
                
            default:
                throw new Exception('不正なアクションです');
        }
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        
        $input = json_decode(file_get_contents('php://input'), true);
        $companyId = $input['id'] ?? null;
        
        if (!$companyId) {
            throw new Exception('企業IDが指定されていません');
        }
        
        // 存在チェック
        $companyCheck = $db->query("SELECT id FROM companies WHERE id = ?", [$companyId]);
        if (!$companyCheck->fetch()) {
            throw new Exception('企業が見つかりません');
        }
        
        $updateFields = [];
        $params = [];
        
        $allowedFields = [
            'company_name', 'address_detail', 'contact_person', 
            'phone_number', 'email_address', 'billing_method', 'is_active'
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
        $params[] = $companyId;
        
        $sql = "UPDATE companies SET " . implode(', ', $updateFields) . " WHERE id = ?";
        $db->query($sql, $params);
        
        echo json_encode([
            'success' => true,
            'message' => '企業情報を更新しました'
        ], JSON_UNESCAPED_UNICODE);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        
        $companyId = $_GET['id'] ?? null;
        if (!$companyId) {
            throw new Exception('企業IDが指定されていません');
        }
        
        // 関連データ確認
        $relatedDataCheck = $db->query(
            "SELECT 
                COUNT(DISTINCT d.id) as department_count,
                COUNT(DISTINCT u.id) as user_count,
                COUNT(DISTINCT o.id) as order_count
             FROM companies c
             LEFT JOIN departments d ON c.id = d.company_id
             LEFT JOIN users u ON c.id = u.company_id
             LEFT JOIN orders o ON u.user_code = o.user_code
             WHERE c.id = ?", 
            [$companyId]
        );
        
        $relatedData = $relatedDataCheck->fetch();
        
        if ($relatedData['order_count'] > 0 || $relatedData['user_count'] > 0) {
            // 論理削除
            $db->query(
                "UPDATE companies SET is_active = 0, updated_at = NOW() WHERE id = ?", 
                [$companyId]
            );
            $message = '企業を無効化しました（関連データがあるため論理削除）';
        } else {
            // 物理削除（部署も削除）
            $db->beginTransaction();
            try {
                $db->query("DELETE FROM departments WHERE company_id = ?", [$companyId]);
                $db->query("DELETE FROM companies WHERE id = ?", [$companyId]);
                $db->commit();
                $message = '企業を削除しました';
            } catch (Exception $e) {
                $db->rollback();
                throw $e;
            }
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
    error_log("Companies API Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
