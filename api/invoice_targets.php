<?php
/**
 * 請求書生成対象取得API（修正版）
 * invoice_generate.phpが期待するパラメータ形式に対応
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/SecurityHelper.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

// セキュリティヘッダー設定
SecurityHelper::setSecurityHeaders();

try {
    $db = Database::getInstance();
    
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('GETメソッドのみ対応しています');
    }
    
    // invoice_generate.phpからのパラメータに対応
    $invoiceType = $_GET['invoice_type'] ?? 'company_bulk';
    $searchTerm = $_GET['search'] ?? '';
    
    $result = [];
    
    switch ($invoiceType) {
        case 'company_bulk':
            // 企業一括請求用：企業一覧
            $sql = "SELECT 
                        c.id,
                        c.company_code,
                        c.company_name,
                        c.payment_method,
                        c.billing_method,
                        (SELECT COUNT(*) FROM users u WHERE u.company_id = c.id AND u.is_active = 1) as user_count,
                        (SELECT COUNT(*) FROM orders o INNER JOIN users u ON o.user_id = u.id WHERE u.company_id = c.id AND o.delivery_date >= CURDATE() - INTERVAL 90 DAY) as recent_orders,
                        (SELECT COALESCE(SUM(o.total_amount), 0) FROM orders o INNER JOIN users u ON o.user_id = u.id WHERE u.company_id = c.id AND o.delivery_date >= CURDATE() - INTERVAL 90 DAY) as recent_amount
                    FROM companies c 
                    WHERE c.is_active = 1";
            
            $params = [];
            if (!empty($searchTerm)) {
                $sql .= " AND (c.company_name LIKE ? OR c.company_code LIKE ?)";
                $params[] = "%{$searchTerm}%";
                $params[] = "%{$searchTerm}%";
            }
            
            $sql .= " ORDER BY c.company_name";
            
            $companies = $db->fetchAll($sql, $params);
            
            // invoice_generate.phpが期待する形式に変換
            $targets = [];
            foreach ($companies as $company) {
                $targets[] = [
                    'id' => (int)$company['id'],
                    'type' => 'company',
                    'code' => $company['company_code'],
                    'name' => $company['company_name'],
                    'description' => "利用者: {$company['user_count']}名 | 注文: {$company['recent_orders']}件",
                    'user_count' => (int)$company['user_count'],
                    'order_count' => (int)$company['recent_orders'],
                    'total_amount' => (float)$company['recent_amount']
                ];
            }
            
            $result = [
                'success' => true,
                'data' => [
                    'targets' => $targets,
                    'total_count' => count($targets),
                    'invoice_type' => 'company_bulk'
                ]
            ];
            break;
            
        case 'department_bulk':
            // 部署別請求用：部署一覧
            $sql = "SELECT 
                        d.id,
                        d.department_code,
                        d.department_name,
                        d.company_id,
                        c.company_name,
                        d.separate_billing,
                        (SELECT COUNT(*) FROM users u WHERE u.department_id = d.id AND u.is_active = 1) as user_count,
                        (SELECT COUNT(*) FROM orders o INNER JOIN users u ON o.user_id = u.id WHERE u.department_id = d.id AND o.delivery_date >= CURDATE() - INTERVAL 90 DAY) as recent_orders,
                        (SELECT COALESCE(SUM(o.total_amount), 0) FROM orders o INNER JOIN users u ON o.user_id = u.id WHERE u.department_id = d.id AND o.delivery_date >= CURDATE() - INTERVAL 90 DAY) as recent_amount
                    FROM departments d 
                    INNER JOIN companies c ON d.company_id = c.id
                    WHERE d.is_active = 1";
            
            $params = [];
            if (!empty($searchTerm)) {
                $sql .= " AND (d.department_name LIKE ? OR d.department_code LIKE ? OR c.company_name LIKE ?)";
                $params[] = "%{$searchTerm}%";
                $params[] = "%{$searchTerm}%";
                $params[] = "%{$searchTerm}%";
            }
            
            $sql .= " ORDER BY c.company_name, d.department_name";
            
            $departments = $db->fetchAll($sql, $params);
            
            $targets = [];
            foreach ($departments as $dept) {
                $targets[] = [
                    'id' => (int)$dept['id'],
                    'type' => 'department',
                    'code' => $dept['department_code'],
                    'name' => $dept['department_name'],
                    'description' => "{$dept['company_name']} | 利用者: {$dept['user_count']}名 | 注文: {$dept['recent_orders']}件",
                    'company_name' => $dept['company_name'],
                    'user_count' => (int)$dept['user_count'],
                    'order_count' => (int)$dept['recent_orders'],
                    'total_amount' => (float)$dept['recent_amount']
                ];
            }
            
            $result = [
                'success' => true,
                'data' => [
                    'targets' => $targets,
                    'total_count' => count($targets),
                    'invoice_type' => 'department_bulk'
                ]
            ];
            break;
            
        case 'individual':
            // 個人請求用：利用者一覧
            $sql = "SELECT 
                        u.id,
                        u.user_code,
                        u.user_name,
                        u.company_id,
                        u.department_id,
                        c.company_name,
                        d.department_name,
                        u.payment_method,
                        (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id AND o.delivery_date >= CURDATE() - INTERVAL 90 DAY) as recent_orders,
                        (SELECT COALESCE(SUM(o.total_amount), 0) FROM orders o WHERE o.user_id = u.id AND o.delivery_date >= CURDATE() - INTERVAL 90 DAY) as recent_amount,
                        (SELECT MAX(o.delivery_date) FROM orders o WHERE o.user_id = u.id) as last_order_date
                    FROM users u 
                    LEFT JOIN companies c ON u.company_id = c.id
                    LEFT JOIN departments d ON u.department_id = d.id
                    WHERE u.is_active = 1";
            
            $params = [];
            if (!empty($searchTerm)) {
                $sql .= " AND (u.user_name LIKE ? OR u.user_code LIKE ? OR c.company_name LIKE ?)";
                $params[] = "%{$searchTerm}%";
                $params[] = "%{$searchTerm}%";
                $params[] = "%{$searchTerm}%";
            }
            
            $sql .= " ORDER BY c.company_name, d.department_name, u.user_name";
            
            $users = $db->fetchAll($sql, $params);
            
            $targets = [];
            foreach ($users as $user) {
                $description = $user['company_name'];
                if (!empty($user['department_name'])) {
                    $description .= " - " . $user['department_name'];
                }
                $description .= " | 注文: {$user['recent_orders']}件";
                if ($user['recent_amount'] > 0) {
                    $description .= " | 金額: ¥" . number_format($user['recent_amount']);
                }
                
                $targets[] = [
                    'id' => (int)$user['id'],
                    'type' => 'user',
                    'code' => $user['user_code'],
                    'name' => $user['user_name'],
                    'description' => $description,
                    'company_name' => $user['company_name'],
                    'department_name' => $user['department_name'],
                    'order_count' => (int)$user['recent_orders'],
                    'total_amount' => (float)$user['recent_amount'],
                    'last_order_date' => $user['last_order_date']
                ];
            }
            
            $result = [
                'success' => true,
                'data' => [
                    'targets' => $targets,
                    'total_count' => count($targets),
                    'invoice_type' => 'individual'
                ]
            ];
            break;
            
        case 'mixed':
            // 混合請求用：企業設定に基づく自動判定
            $sql = "SELECT 
                        c.id,
                        c.company_code,
                        c.company_name,
                        c.billing_method,
                        c.payment_method,
                        CASE 
                            WHEN c.billing_method = 'company' THEN '企業一括'
                            WHEN c.billing_method = 'department' THEN '部署別'
                            WHEN c.billing_method = 'individual' THEN '個人別'
                            ELSE '混合'
                        END as billing_type_label,
                        (SELECT COUNT(*) FROM users u WHERE u.company_id = c.id AND u.is_active = 1) as user_count,
                        (SELECT COUNT(*) FROM orders o INNER JOIN users u ON o.user_id = u.id WHERE u.company_id = c.id AND o.delivery_date >= CURDATE() - INTERVAL 90 DAY) as recent_orders,
                        (SELECT COALESCE(SUM(o.total_amount), 0) FROM orders o INNER JOIN users u ON o.user_id = u.id WHERE u.company_id = c.id AND o.delivery_date >= CURDATE() - INTERVAL 90 DAY) as recent_amount
                    FROM companies c 
                    WHERE c.is_active = 1";
            
            $params = [];
            if (!empty($searchTerm)) {
                $sql .= " AND (c.company_name LIKE ? OR c.company_code LIKE ?)";
                $params[] = "%{$searchTerm}%";
                $params[] = "%{$searchTerm}%";
            }
            
            $sql .= " ORDER BY c.company_name";
            
            $companies = $db->fetchAll($sql, $params);
            
            $targets = [];
            foreach ($companies as $company) {
                $targets[] = [
                    'id' => (int)$company['id'],
                    'type' => 'mixed',
                    'code' => $company['company_code'],
                    'name' => $company['company_name'],
                    'description' => "請求方式: {$company['billing_type_label']} | 利用者: {$company['user_count']}名 | 注文: {$company['recent_orders']}件",
                    'preferred_type' => $company['billing_method'],
                    'user_count' => (int)$company['user_count'],
                    'order_count' => (int)$company['recent_orders'],
                    'total_amount' => (float)$company['recent_amount']
                ];
            }
            
            $result = [
                'success' => true,
                'data' => [
                    'targets' => $targets,
                    'total_count' => count($targets),
                    'invoice_type' => 'mixed'
                ]
            ];
            break;
            
        default:
            throw new Exception('サポートされていない請求書タイプです: ' . $invoiceType);
    }
    
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => [
            'message' => $e->getMessage(),
            'code' => $e->getCode()
        ],
        'data' => [
            'targets' => [],
            'total_count' => 0
        ]
    ], JSON_UNESCAPED_UNICODE);
}
