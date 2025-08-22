<?php
/**
 * CSVインポート診断・修復ツール
 * 注文データが登録されない問題の特定と修復
 */

require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/SecurityHelper.php';

header('Content-Type: application/json; charset=utf-8');

// セキュリティヘッダー設定
SecurityHelper::setSecurityHeaders();

try {
    $db = Database::getInstance();
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        
        $action = $_GET['action'] ?? 'full_diagnosis';
        
        switch ($action) {
            case 'full_diagnosis':
                $diagnosis = [];
                
                // 1. 全テーブルのデータ件数確認
                $tables = ['companies', 'departments', 'users', 'orders', 'products', 'suppliers', 'import_logs'];
                foreach ($tables as $table) {
                    try {
                        $stmt = $db->query("SELECT COUNT(*) as count FROM {$table}");
                        $diagnosis['table_counts'][$table] = $stmt->fetch()['count'];
                    } catch (Exception $e) {
                        $diagnosis['table_counts'][$table] = 'ERROR: ' . $e->getMessage();
                    }
                }
                
                // 2. import_logs確認（CSVインポート履歴）
                $importLogs = $db->query("
                    SELECT batch_id, file_name, total_records, success_records, error_records, 
                           status, import_start, error_details 
                    FROM import_logs 
                    ORDER BY import_start DESC 
                    LIMIT 5
                ");
                $diagnosis['import_history'] = $importLogs->fetchAll();
                
                // 3. users と companies/departments の関連確認
                $userRelations = $db->query("
                    SELECT 
                        COUNT(*) as total_users,
                        COUNT(CASE WHEN company_id IS NOT NULL THEN 1 END) as users_with_company,
                        COUNT(CASE WHEN department_id IS NOT NULL THEN 1 END) as users_with_department,
                        COUNT(CASE WHEN company_id IS NULL THEN 1 END) as users_without_company
                    FROM users
                ");
                $diagnosis['user_relations'] = $userRelations->fetch();
                
                // 4. orders テーブル構造確認
                $ordersStructure = $db->query("DESCRIBE orders");
                $diagnosis['orders_structure'] = $ordersStructure->fetchAll();
                
                // 5. CSVインポートでエラーがあったか確認
                $errorLogs = $db->query("
                    SELECT batch_id, error_details, import_start 
                    FROM import_logs 
                    WHERE status LIKE '%error%' OR error_records > 0
                    ORDER BY import_start DESC 
                    LIMIT 3
                ");
                $diagnosis['import_errors'] = $errorLogs->fetchAll();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'システム診断完了',
                    'data' => $diagnosis
                ], JSON_UNESCAPED_UNICODE);
                break;
                
            case 'create_sample_orders':
                // サンプル注文データ作成（テスト用）
                $sampleOrders = [
                    [
                        'delivery_date' => '2025-08-22',
                        'user_code' => 'Smiley0001',
                        'user_name' => '保田　翔',
                        'company_code' => '0001',
                        'company_name' => '株式会社Smiley',
                        'department_code' => '0001',
                        'department_name' => 'Smiley',
                        'product_code' => 'BENTO001',
                        'product_name' => '唐揚げ弁当',
                        'quantity' => 1,
                        'unit_price' => 500.00,
                        'total_amount' => 500.00
                    ],
                    [
                        'delivery_date' => '2025-08-22',
                        'user_code' => 'Smiley0003',
                        'user_name' => '松本　邦康',
                        'company_code' => '0001',
                        'company_name' => '株式会社Smiley',
                        'department_code' => '0001',
                        'department_name' => 'Smiley',
                        'product_code' => 'BENTO002',
                        'product_name' => 'ハンバーグ弁当',
                        'quantity' => 1,
                        'unit_price' => 550.00,
                        'total_amount' => 550.00
                    ]
                ];
                
                $insertedCount = 0;
                $db->beginTransaction();
                
                try {
                    foreach ($sampleOrders as $order) {
                        // user_id と company_id を取得
                        $userStmt = $db->query("SELECT id, company_id, department_id FROM users WHERE user_code = ?", [$order['user_code']]);
                        $user = $userStmt->fetch();
                        
                        if ($user) {
                            $sql = "
                                INSERT INTO orders (
                                    delivery_date, user_id, user_code, user_name,
                                    company_id, company_code, company_name,
                                    department_id, department_code, department_name,
                                    product_code, product_name,
                                    quantity, unit_price, total_amount,
                                    created_at, updated_at
                                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                            ";
                            
                            $db->query($sql, [
                                $order['delivery_date'],
                                $user['id'],
                                $order['user_code'],
                                $order['user_name'],
                                $user['company_id'],
                                $order['company_code'],
                                $order['company_name'],
                                $user['department_id'],
                                $order['department_code'],
                                $order['department_name'],
                                $order['product_code'],
                                $order['product_name'],
                                $order['quantity'],
                                $order['unit_price'],
                                $order['total_amount']
                            ]);
                            
                            $insertedCount++;
                        }
                    }
                    
                    $db->commit();
                    
                    echo json_encode([
                        'success' => true,
                        'message' => "サンプル注文データを{$insertedCount}件作成しました",
                        'data' => ['inserted_count' => $insertedCount]
                    ], JSON_UNESCAPED_UNICODE);
                    
                } catch (Exception $e) {
                    $db->rollback();
                    throw $e;
                }
                break;
                
            case 'fix_user_relations':
                // users テーブルの company_id, department_id を修復
                $fixedCount = 0;
                
                // company_id が null の users を修復
                $usersWithoutCompany = $db->query("
                    SELECT u.id, u.user_code 
                    FROM users u 
                    WHERE u.company_id IS NULL 
                    LIMIT 10
                ");
                
                foreach ($usersWithoutCompany->fetchAll() as $user) {
                    // user_code の先頭から company を推定
                    if (strpos($user['user_code'], 'Smiley') === 0) {
                        // Smiley から始まる場合は company_id = 5
                        $companyStmt = $db->query("SELECT id FROM companies WHERE company_name LIKE '%Smiley%' LIMIT 1");
                        $company = $companyStmt->fetch();
                        
                        if ($company) {
                            $db->query("UPDATE users SET company_id = ? WHERE id = ?", [$company['id'], $user['id']]);
                            $fixedCount++;
                        }
                    }
                }
                
                echo json_encode([
                    'success' => true,
                    'message' => "利用者の関連付けを{$fixedCount}件修復しました",
                    'data' => ['fixed_count' => $fixedCount]
                ], JSON_UNESCAPED_UNICODE);
                break;
                
            default:
                throw new Exception('不正なアクションです');
        }
        
    } else {
        throw new Exception('GETメソッドのみサポートしています');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    error_log("CSV Import Diagnostic Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'CSVインポート診断エラー: ' . $e->getMessage(),
        'debug' => [
            'file' => basename(__FILE__),
            'line' => $e->getLine(),
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ], JSON_UNESCAPED_UNICODE);
}
?>
