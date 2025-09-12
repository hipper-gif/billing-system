<?php
/**
 * 請求書対象選択API
 * 請求書生成時の対象（企業・部署・利用者）一覧を取得
 * 
 * @author Claude
 * @version 1.0.0
 * @created 2025-09-12
 */

// 出力バッファリング制御
ob_start();

// エラー出力制御
error_reporting(E_ALL);
ini_set('display_errors', 0);

try {
    // 必要なファイルを読み込み
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../classes/Database.php';
    
    // ヘッダー設定
    header('Content-Type: application/json; charset=utf-8');
    
    // 出力バッファをクリア
    ob_clean();
    
    // Databaseインスタンス取得（Singletonパターン）
    $db = Database::getInstance();
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        
        $invoiceType = $_GET['invoice_type'] ?? 'company_bulk';
        
        switch ($invoiceType) {
            case 'company_bulk':
                // 企業一括請求の対象取得
                $sql = "SELECT 
                            c.id,
                            'company' as type,
                            c.company_code as code,
                            c.company_name as name,
                            CONCAT(
                                '利用者: ', COALESCE(user_stats.user_count, 0), '名 | ',
                                '注文: ', COALESCE(order_stats.order_count, 0), '件'
                            ) as description,
                            COALESCE(user_stats.user_count, 0) as user_count,
                            COALESCE(order_stats.order_count, 0) as order_count,
                            COALESCE(order_stats.total_amount, 0) as total_amount
                        FROM companies c
                        LEFT JOIN (
                            SELECT 
                                company_id, 
                                COUNT(*) as user_count 
                            FROM users 
                            WHERE is_active = 1 
                            GROUP BY company_id
                        ) user_stats ON c.id = user_stats.company_id
                        LEFT JOIN (
                            SELECT 
                                u.company_id,
                                COUNT(o.id) as order_count,
                                SUM(o.quantity * o.unit_price) as total_amount
                            FROM orders o
                            JOIN users u ON o.user_id = u.id
                            WHERE o.delivery_date >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
                            AND o.is_active = 1
                            GROUP BY u.company_id
                        ) order_stats ON c.id = order_stats.company_id
                        WHERE c.is_active = 1
                        ORDER BY c.company_name";
                break;
                
            case 'department_bulk':
                // 部署別一括請求の対象取得
                $sql = "SELECT 
                            d.id,
                            'department' as type,
                            d.department_code as code,
                            CONCAT(c.company_name, ' - ', d.department_name) as name,
                            CONCAT(
                                '利用者: ', COALESCE(user_stats.user_count, 0), '名 | ',
                                '注文: ', COALESCE(order_stats.order_count, 0), '件'
                            ) as description,
                            COALESCE(user_stats.user_count, 0) as user_count,
                            COALESCE(order_stats.order_count, 0) as order_count,
                            COALESCE(order_stats.total_amount, 0) as total_amount
                        FROM departments d
                        JOIN companies c ON d.company_id = c.id
                        LEFT JOIN (
                            SELECT 
                                department_id, 
                                COUNT(*) as user_count 
                            FROM users 
                            WHERE is_active = 1 
                            GROUP BY department_id
                        ) user_stats ON d.id = user_stats.department_id
                        LEFT JOIN (
                            SELECT 
                                u.department_id,
                                COUNT(o.id) as order_count,
                                SUM(o.quantity * o.unit_price) as total_amount
                            FROM orders o
                            JOIN users u ON o.user_id = u.id
                            WHERE o.delivery_date >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
                            AND o.is_active = 1
                            AND u.department_id IS NOT NULL
                            GROUP BY u.department_id
                        ) order_stats ON d.id = order_stats.department_id
                        WHERE d.is_active = 1 AND c.is_active = 1
                        ORDER BY c.company_name, d.department_name";
                break;
                
            case 'individual':
                // 個人請求の対象取得
                $sql = "SELECT 
                            u.id,
                            'user' as type,
                            u.user_code as code,
                            CONCAT(u.user_name, ' (', c.company_name, ')') as name,
                            CONCAT(
                                '注文: ', COALESCE(order_stats.order_count, 0), '件 | ',
                                '合計: ¥', FORMAT(COALESCE(order_stats.total_amount, 0), 0)
                            ) as description,
                            1 as user_count,
                            COALESCE(order_stats.order_count, 0) as order_count,
                            COALESCE(order_stats.total_amount, 0) as total_amount
                        FROM users u
                        JOIN companies c ON u.company_id = c.id
                        LEFT JOIN (
                            SELECT 
                                user_id,
                                COUNT(id) as order_count,
                                SUM(quantity * unit_price) as total_amount
                            FROM orders 
                            WHERE delivery_date >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
                            AND is_active = 1
                            GROUP BY user_id
                        ) order_stats ON u.id = order_stats.user_id
                        WHERE u.is_active = 1 AND c.is_active = 1
                        ORDER BY c.company_name, u.user_name";
                break;
                
            case 'mixed':
                // 混合請求（企業一括と同じデータを返す）
                $sql = "SELECT 
                            c.id,
                            'company' as type,
                            c.company_code as code,
                            c.company_name as name,
                            '自動判定対象' as description,
                            COALESCE(user_stats.user_count, 0) as user_count,
                            COALESCE(order_stats.order_count, 0) as order_count,
                            COALESCE(order_stats.total_amount, 0) as total_amount
                        FROM companies c
                        LEFT JOIN (
                            SELECT 
                                company_id, 
                                COUNT(*) as user_count 
                            FROM users 
                            WHERE is_active = 1 
                            GROUP BY company_id
                        ) user_stats ON c.id = user_stats.company_id
                        LEFT JOIN (
                            SELECT 
                                u.company_id,
                                COUNT(o.id) as order_count,
                                SUM(o.quantity * o.unit_price) as total_amount
                            FROM orders o
                            JOIN users u ON o.user_id = u.id
                            WHERE o.delivery_date >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
                            AND o.is_active = 1
                            GROUP BY u.company_id
                        ) order_stats ON c.id = order_stats.company_id
                        WHERE c.is_active = 1
                        ORDER BY c.company_name";
                break;
                
            default:
                throw new Exception('不正な請求書タイプです: ' . $invoiceType);
        }
        
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $targets = $stmt->fetchAll();
        
        // 統計情報の計算
        $totalTargets = count($targets);
        $totalUsers = array_sum(array_column($targets, 'user_count'));
        $totalOrders = array_sum(array_column($targets, 'order_count'));
        $totalAmount = array_sum(array_column($targets, 'total_amount'));
        
        echo json_encode([
            'success' => true,
            'data' => [
                'targets' => $targets,
                'statistics' => [
                    'total_targets' => $totalTargets,
                    'total_users' => $totalUsers,
                    'total_orders' => $totalOrders,
                    'total_amount' => $totalAmount,
                    'invoice_type' => $invoiceType
                ]
            ]
        ], JSON_UNESCAPED_UNICODE);
        
    } else {
        throw new Exception('サポートされていないHTTPメソッドです: ' . $_SERVER['REQUEST_METHOD']);
    }
    
} catch (Exception $e) {
    // エラー時の出力制御
    ob_clean();
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_type' => get_class($e),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ], JSON_UNESCAPED_UNICODE);
} finally {
    // 出力バッファ終了
    ob_end_flush();
}
?>
