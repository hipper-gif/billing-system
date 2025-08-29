<?php
/**
 * 請求書生成対象取得API
 * invoice_generate.php用の対象一覧を提供
 */

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
    
    $action = $_GET['action'] ?? '';
    $invoiceType = $_GET['invoice_type'] ?? 'company_bulk';
    
    switch ($action) {
        case 'companies':
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
                    WHERE c.is_active = 1 
                    ORDER BY c.company_name";
            
            $companies = $db->fetchAll($sql);
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'companies' => $companies,
                    'total_count' => count($companies)
                ]
            ], JSON_UNESCAPED_UNICODE);
            break;
            
        case 'departments':
            // 部署別請求用：部署一覧
            $companyId = $_GET['company_id'] ?? null;
            $whereClause = '';
            $params = [];
            
            if ($companyId) {
                $whereClause = 'AND d.company_id = ?';
                $params[] = $companyId;
            }
            
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
                    WHERE d.is_active = 1 {$whereClause}
                    ORDER BY c.company_name, d.department_name";
            
            $departments = $db->fetchAll($sql, $params);
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'departments' => $departments,
                    'total_count' => count($departments)
                ]
            ], JSON_UNESCAPED_UNICODE);
            break;
            
        case 'users':
            // 個人請求用：利用者一覧
            $companyId = $_GET['company_id'] ?? null;
            $departmentId = $_GET['department_id'] ?? null;
            $whereClause = '';
            $params = [];
            
            if ($companyId) {
                $whereClause .= ' AND u.company_id = ?';
                $params[] = $companyId;
            }
            
            if ($departmentId) {
                $whereClause .= ' AND u.department_id = ?';
                $params[] = $departmentId;
            }
            
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
                    WHERE u.is_active = 1 {$whereClause}
                    ORDER BY c.company_name, d.department_name, u.user_name";
            
            $users = $db->fetchAll($sql, $params);
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'users' => $users,
                    'total_count' => count($users)
                ]
            ], JSON_UNESCAPED_UNICODE);
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
                    WHERE c.is_active = 1 
                    ORDER BY c.company_name";
            
            $companies = $db->fetchAll($sql);
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'companies' => $companies,
                    'total_count' => count($companies),
                    'mixed_mode' => true
                ]
            ], JSON_UNESCAPED_UNICODE);
            break;
            
        case 'preview':
            // プレビュー用：選択された対象の統計情報
            $targetType = $_GET['target_type'] ?? 'companies';
            $targetIds = json_decode($_GET['target_ids'] ?? '[]', true);
            $periodStart = $_GET['period_start'] ?? date('Y-m-01');
            $periodEnd = $_GET['period_end'] ?? date('Y-m-t');
            
            if (empty($targetIds)) {
                echo json_encode([
                    'success' => true,
                    'data' => [
                        'preview' => [],
                        'summary' => [
                            'total_targets' => 0,
                            'total_amount' => 0,
                            'total_orders' => 0
                        ]
                    ]
                ]);
                break;
            }
            
            $placeholders = implode(',', array_fill(0, count($targetIds), '?'));
            $params = array_merge($targetIds, [$periodStart, $periodEnd]);
            
            switch ($targetType) {
                case 'companies':
                    $sql = "SELECT 
                                c.id,
                                c.company_name as target_name,
                                '企業' as target_type,
                                COUNT(o.id) as order_count,
                                COALESCE(SUM(o.total_amount), 0) as total_amount,
                                COUNT(DISTINCT u.id) as user_count
                            FROM companies c
                            LEFT JOIN users u ON u.company_id = c.id
                            LEFT JOIN orders o ON o.user_id = u.id AND o.delivery_date BETWEEN ? AND ?
                            WHERE c.id IN ({$placeholders})
                            GROUP BY c.id, c.company_name
                            ORDER BY c.company_name";
                    break;
                    
                case 'departments':
                    $sql = "SELECT 
                                d.id,
                                CONCAT(c.company_name, ' - ', d.department_name) as target_name,
                                '部署' as target_type,
                                COUNT(o.id) as order_count,
                                COALESCE(SUM(o.total_amount), 0) as total_amount,
                                COUNT(DISTINCT u.id) as user_count
                            FROM departments d
                            INNER JOIN companies c ON d.company_id = c.id
                            LEFT JOIN users u ON u.department_id = d.id
                            LEFT JOIN orders o ON o.user_id = u.id AND o.delivery_date BETWEEN ? AND ?
                            WHERE d.id IN ({$placeholders})
                            GROUP BY d.id, c.company_name, d.department_name
                            ORDER BY c.company_name, d.department_name";
                    break;
                    
                case 'users':
                    $sql = "SELECT 
                                u.id,
                                CONCAT(u.user_name, ' (', COALESCE(c.company_name, ''), ')') as target_name,
                                '個人' as target_type,
                                COUNT(o.id) as order_count,
                                COALESCE(SUM(o.total_amount), 0) as total_amount,
                                1 as user_count
                            FROM users u
                            LEFT JOIN companies c ON u.company_id = c.id
                            LEFT JOIN orders o ON o.user_id = u.id AND o.delivery_date BETWEEN ? AND ?
                            WHERE u.id IN ({$placeholders})
                            GROUP BY u.id, u.user_name, c.company_name
                            ORDER BY c.company_name, u.user_name";
                    break;
                    
                default:
                    throw new Exception('不正な対象タイプです');
            }
            
            // パラメータの順序を調整（期間が先、IDが後）
            $adjustedParams = [$periodStart, $periodEnd];
            $adjustedParams = array_merge($adjustedParams, $targetIds);
            
            $preview = $db->fetchAll($sql, $adjustedParams);
            
            // サマリー計算
            $totalAmount = array_sum(array_column($preview, 'total_amount'));
            $totalOrders = array_sum(array_column($preview, 'order_count'));
            $totalUsers = array_sum(array_column($preview, 'user_count'));
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'preview' => $preview,
                    'summary' => [
                        'total_targets' => count($preview),
                        'total_amount' => $totalAmount,
                        'total_orders' => $totalOrders,
                        'total_users' => $totalUsers
                    ]
                ]
            ], JSON_UNESCAPED_UNICODE);
            break;
            
        default:
            throw new Exception('不正なアクションです');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_type' => get_class($e)
    ], JSON_UNESCAPED_UNICODE);
}
