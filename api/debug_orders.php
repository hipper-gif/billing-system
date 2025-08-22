<?php
/**
 * 注文データ反映問題デバッグツール
 * CSVインポート時の注文データ登録状況を詳細に確認
 */

header('Content-Type: application/json; charset=utf-8');
require_once '../classes/Database.php';

try {
    $db = Database::getInstance();
    
    $response = [
        'success' => true,
        'debug_info' => []
    ];
    
    // 1. データベース接続確認
    $response['debug_info']['database_connection'] = 'OK';
    
    // 2. テーブル存在確認
    $tables = ['orders', 'users', 'companies', 'departments', 'products', 'suppliers'];
    foreach ($tables as $table) {
        $stmt = $db->query("SHOW TABLES LIKE '{$table}'");
        $exists = $stmt->fetch() ? 'EXISTS' : 'NOT_EXISTS';
        $response['debug_info']['tables'][$table] = $exists;
    }
    
    // 3. ordersテーブル構造確認
    $stmt = $db->query("DESCRIBE orders");
    $orderColumns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $response['debug_info']['orders_structure'] = $orderColumns;
    
    // 4. 現在のordersテーブルデータ件数
    $stmt = $db->query("SELECT COUNT(*) as count FROM orders");
    $orderCount = $stmt->fetch()['count'];
    $response['debug_info']['orders_count'] = $orderCount;
    
    // 5. 最新のインポートログ確認
    $stmt = $db->query("SELECT * FROM import_logs ORDER BY created_at DESC LIMIT 3");
    $importLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $response['debug_info']['recent_import_logs'] = $importLogs;
    
    // 6. 最新のorders データサンプル
    $stmt = $db->query("SELECT * FROM orders ORDER BY created_at DESC LIMIT 5");
    $orderSamples = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $response['debug_info']['recent_orders'] = $orderSamples;
    
    // 7. CSVインポート関連データ確認
    $stmt = $db->query("
        SELECT 
            u.user_code, u.user_name, u.company_id,
            c.company_code, c.company_name,
            COUNT(o.id) as order_count
        FROM users u
        LEFT JOIN companies c ON u.company_id = c.id
        LEFT JOIN orders o ON u.user_code = o.user_code
        GROUP BY u.user_code
        ORDER BY u.created_at DESC
        LIMIT 10
    ");
    $userOrderStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $response['debug_info']['user_order_stats'] = $userOrderStats;
    
    // 8. SmileyCSVImporter クラス確認
    if (class_exists('SmileyCSVImporter')) {
        $response['debug_info']['importer_class'] = 'EXISTS';
        
        // クラスメソッド確認
        $reflection = new ReflectionClass('SmileyCSVImporter');
        $methods = array_map(function($method) {
            return $method->getName();
        }, $reflection->getMethods());
        $response['debug_info']['importer_methods'] = $methods;
    } else {
        $response['debug_info']['importer_class'] = 'NOT_EXISTS';
    }
    
    // 9. 外部キー制約確認
    $stmt = $db->query("
        SELECT 
            CONSTRAINT_NAME,
            TABLE_NAME,
            COLUMN_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE REFERENCED_TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'orders'
    ");
    $foreignKeys = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $response['debug_info']['orders_foreign_keys'] = $foreignKeys;
    
    // 10. 手動テスト：注文データ挿入テスト
    try {
        $testUserId = null;
        $testCompanyId = null;
        $testProductId = null;
        
        // テストデータ取得
        $stmt = $db->query("SELECT id FROM users LIMIT 1");
        $user = $stmt->fetch();
        if ($user) $testUserId = $user['id'];
        
        $stmt = $db->query("SELECT id FROM companies LIMIT 1");
        $company = $stmt->fetch();
        if ($company) $testCompanyId = $company['id'];
        
        $stmt = $db->query("SELECT id FROM products LIMIT 1");
        $product = $stmt->fetch();
        if ($product) $testProductId = $product['id'];
        
        if ($testUserId && $testCompanyId) {
            // テスト用注文データ挿入
            $testSql = "INSERT INTO orders (
                delivery_date, user_id, user_code, user_name,
                company_id, company_code, company_name,
                product_id, product_code, product_name,
                quantity, unit_price, total_amount,
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $testParams = [
                date('Y-m-d'),
                $testUserId,
                'TEST_USER_001',
                'テストユーザー',
                $testCompanyId,
                'TEST_COMPANY_001',
                'テスト企業',
                $testProductId,
                'TEST_PRODUCT_001',
                'テスト商品',
                1,
                500.00,
                500.00
            ];
            
            $stmt = $db->prepare($testSql);
            $testInsert = $stmt->execute($testParams);
            
            if ($testInsert) {
                $testOrderId = $db->lastInsertId();
                $response['debug_info']['test_insert'] = [
                    'status' => 'SUCCESS',
                    'order_id' => $testOrderId
                ];
                
                // テストデータ削除
                $db->query("DELETE FROM orders WHERE id = {$testOrderId}");
            } else {
                $response['debug_info']['test_insert'] = [
                    'status' => 'FAILED',
                    'error' => $stmt->errorInfo()
                ];
            }
        } else {
            $response['debug_info']['test_insert'] = [
                'status' => 'SKIPPED',
                'reason' => 'No test data available'
            ];
        }
    } catch (Exception $e) {
        $response['debug_info']['test_insert'] = [
            'status' => 'ERROR',
            'message' => $e->getMessage()
        ];
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ], JSON_UNESCAPED_UNICODE);
}
?>
