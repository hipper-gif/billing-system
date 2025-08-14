<?php
/**
 * データベース初期化 API
 * POST /api/install.php
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../classes/Database.php';

// エラーハンドリング
function sendError($message, $code = 500) {
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'error' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function sendSuccess($data, $message = 'Success') {
    echo json_encode([
        'success' => true,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method === 'GET') {
        // インストール状況確認
        $db = new Database();
        
        if (!$db->isConnected()) {
            sendError('データベース接続に失敗しました: ' . $db->getLastError());
        }
        
        // 必要なテーブルの存在確認
        $requiredTables = [
            'users',
            'products', 
            'orders',
            'invoices',
            'invoice_details',
            'payments',
            'receipts',
            'import_logs',
            'system_settings'
        ];
        
        $existingTables = $db->getTables();
        $missingTables = [];
        $installedTables = [];
        
        foreach ($requiredTables as $table) {
            if (in_array($table, $existingTables)) {
                $installedTables[] = $table;
            } else {
                $missingTables[] = $table;
            }
        }
        
        // 各テーブルのレコード数確認
        $tableCounts = [];
        foreach ($installedTables as $table) {
            try {
                $result = $db->fetchOne("SELECT COUNT(*) as count FROM `$table`");
                $tableCounts[$table] = $result['count'];
            } catch (Exception $e) {
                $tableCounts[$table] = 'エラー';
            }
        }
        
        $isInstalled = empty($missingTables);
        
        sendSuccess([
            'installed' => $isInstalled,
            'existing_tables' => $installedTables,
            'missing_tables' => $missingTables,
            'table_counts' => $tableCounts,
            'environment' => ENVIRONMENT
        ], $isInstalled ? 'システムは正常にインストールされています' : 'システムのインストールが必要です');
        
    } elseif ($method === 'POST') {
        // データベース初期化実行
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? '';
        $confirmPassword = $input['password'] ?? '';
        
        // 簡易的なパスワード確認（本番環境では適切な認証を実装）
        $expectedPassword = ENVIRONMENT === 'production' ? 'smiley2025' : 'install123';
        
        if ($confirmPassword !== $expectedPassword) {
            sendError('パスワードが正しくありません', 403);
        }
        
        $db = new Database();
        
        if (!$db->isConnected()) {
            sendError('データベース接続に失敗しました: ' . $db->getLastError());
        }
        
        switch ($action) {
            case 'install':
                // SQLファイル読み込み・実行
                $sqlFile = __DIR__ . '/../sql/init.sql';
                
                if (!file_exists($sqlFile)) {
                    sendError('SQLファイルが見つかりません: ' . $sqlFile);
                }
                
                $sql = file_get_contents($sqlFile);
                $statements = explode(';', $sql);
                
                $executed = 0;
                $errors = [];
                
                $db->beginTransaction();
                
                try {
                    foreach ($statements as $statement) {
                        $statement = trim($statement);
                        if (empty($statement) || 
                            strpos($statement, '--') === 0 || 
                            strpos($statement, '/*') === 0) {
                            continue;
                        }
                        
                        $db->query($statement);
                        $executed++;
                    }
                    
                    $db->commit();
                    
                    sendSuccess([
                        'executed_statements' => $executed,
                        'tables_created' => $db->getTables(),
                        'environment' => ENVIRONMENT
                    ], 'データベースの初期化が完了しました');
                    
                } catch (Exception $e) {
                    $db->rollback();
                    sendError('データベース初期化中にエラーが発生しました: ' . $e->getMessage());
                }
                break;
                
            case 'reset':
                // データベースリセット（テーブル削除・再作成）
                $tables = [
                    'invoice_details',
                    'receipts', 
                    'payments',
                    'invoices',
                    'orders',
                    'import_logs',
                    'products',
                    'users',
                    'system_settings'
                ];
                
                $db->beginTransaction();
                
                try {
                    // 外部キー制約無効化
                    $db->query('SET FOREIGN_KEY_CHECKS = 0');
                    
                    // テーブル削除
                    $dropped = 0;
                    foreach ($tables as $table) {
                        try {
                            $db->query("DROP TABLE IF EXISTS `$table`");
                            $dropped++;
                        } catch (Exception $e) {
                            // エラーは無視して続行
                        }
                    }
                    
                    // 外部キー制約有効化
                    $db->query('SET FOREIGN_KEY_CHECKS = 1');
                    
                    $db->commit();
                    
                    sendSuccess([
                        'dropped_tables' => $dropped,
                        'remaining_tables' => $db->getTables()
                    ], 'データベースのリセットが完了しました');
                    
                } catch (Exception $e) {
                    $db->rollback();
                    sendError('データベースリセット中にエラーが発生しました: ' . $e->getMessage());
                }
                break;
                
            case 'sample_data':
                // サンプルデータの追加投入
                $sampleSql = "
                -- 追加のサンプルデータ
                INSERT IGNORE INTO users (user_code, user_name, company_name, department, email, payment_method) VALUES
                ('U006', '木村大輔', 'ABC商事', '営業1課', 'kimura@abc.co.jp', 'bank_transfer'),
                ('U007', '松本美咲', 'ABC商事', '経理課', 'matsumoto@abc.co.jp', 'cash'),
                ('U008', '井上健太', 'XYZ工業', '開発チーム', 'inoue@xyz.co.jp', 'account_debit'),
                ('U009', '小林優子', 'XYZ工業', '管理部', 'kobayashi@xyz.co.jp', 'cash'),
                ('U010', '中村淳', '個人事業主', '', 'nakamura@freelance.jp', 'bank_transfer');
                
                -- 過去3ヶ月分の注文データ
                ";
                
                // 過去3ヶ月のランダムデータ生成
                $userCodes = ['U001', 'U002', 'U003', 'U004', 'U005', 'U006', 'U007', 'U008', 'U009', 'U010'];
                $products = [
                    ['BENTO001', 'お弁当A（日替わり）', 600],
                    ['BENTO002', 'お弁当B（唐揚げ）', 650],
                    ['BENTO003', 'お弁当C（焼き魚）', 680],
                    ['DRINK001', 'お茶', 120],
                    ['DRINK002', 'コーヒー', 150],
                    ['SIDE001', 'サラダ', 200]
                ];
                
                $userNames = [
                    'U001' => '田中太郎', 'U002' => '佐藤花子', 'U003' => '鈴木一郎',
                    'U004' => '高橋美和', 'U005' => '山田次郎', 'U006' => '木村大輔',
                    'U007' => '松本美咲', 'U008' => '井上健太', 'U009' => '小林優子',
                    'U010' => '中村淳'
                ];
                
                for ($i = 1; $i <= 90; $i++) {
                    $date = date('Y-m-d', strtotime("-{$i} days"));
                    $userCode = $userCodes[array_rand($userCodes)];
                    $product = $products[array_rand($products)];
                    $quantity = rand(1, 2);
                    $amount = $product[2] * $quantity;
                    
                    $sampleSql .= "INSERT IGNORE INTO orders (order_date, user_code, user_name, product_code, product_name, quantity, unit_price, total_amount, import_batch_id) VALUES ";
                    $sampleSql .= "('$date', '$userCode', '{$userNames[$userCode]}', '{$product[0]}', '{$product[1]}', $quantity, {$product[2]}, $amount, 'SAMPLE_BATCH');";
                }
                
                try {
                    $statements = explode(';', $sampleSql);
                    $inserted = 0;
                    
                    foreach ($statements as $statement) {
                        $statement = trim($statement);
                        if (!empty($statement)) {
                            $db->query($statement);
                            $inserted++;
                        }
                    }
                    
                    sendSuccess([
                        'inserted_statements' => $inserted,
                        'sample_data_ready' => true
                    ], 'サンプルデータの追加が完了しました');
                    
                } catch (Exception $e) {
                    sendError('サンプルデータ追加中にエラーが発生しました: ' . $e->getMessage());
                }
                break;
                
            default:
                sendError('無効なアクション: ' . $action, 400);
        }
        
    } else {
        sendError('許可されていないメソッドです', 405);
    }
    
} catch (Exception $e) {
    if (DEBUG_MODE) {
        sendError('エラー: ' . $e->getMessage() . ' (' . $e->getFile() . ':' . $e->getLine() . ')');
    } else {
        sendError('システムエラーが発生しました');
    }
}
?>