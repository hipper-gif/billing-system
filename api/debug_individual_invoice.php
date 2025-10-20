<?php
/**
 * 個人請求書生成 詳細デバッグツール
 * 
 * 問題: 個人請求書生成を実行してもinvoicesテーブルに追加されない
 * 
 * このツールで確認する内容:
 * 1. ordersテーブルに対象期間のデータが存在するか
 * 2. 利用者情報が正しく取得できるか
 * 3. company_id, user_idが取得できるか
 * 4. 請求書生成処理のステップごとの結果
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/database.php';

echo "<h1>🔍 個人請求書生成 詳細デバッグ</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    h1 { color: #4CAF50; }
    h2 { color: #2196F3; border-bottom: 2px solid #2196F3; padding-bottom: 5px; margin-top: 30px; }
    h3 { color: #FF9800; }
    .success { color: #4CAF50; font-weight: bold; }
    .error { color: #F44336; font-weight: bold; }
    .warning { color: #FF9800; font-weight: bold; }
    table { border-collapse: collapse; width: 100%; margin: 10px 0; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #4CAF50; color: white; }
    pre { background: #f5f5f5; padding: 10px; border-radius: 5px; overflow-x: auto; font-size: 12px; }
    .step { background: #e3f2fd; padding: 15px; margin: 10px 0; border-left: 4px solid #2196F3; }
</style>";

try {
    $db = Database::getInstance();
    
    // テスト期間設定（ユーザーが指定した期間を使用）
    $periodStart = $_GET['period_start'] ?? '2025-10-01';
    $periodEnd = $_GET['period_end'] ?? '2025-10-31';
    
    echo "<div class='step'>";
    echo "<strong>テスト対象期間:</strong> {$periodStart} 〜 {$periodEnd}<br>";
    echo "<small>期間を変更する場合: ?period_start=YYYY-MM-DD&period_end=YYYY-MM-DD</small>";
    echo "</div>";
    
    // Step 1: ordersテーブルのデータ確認
    echo "<h2>Step 1: ordersテーブルのデータ確認</h2>";
    
    $ordersSql = "SELECT 
                    user_id,
                    user_code,
                    user_name,
                    company_name,
                    department_name,
                    COUNT(*) as order_count,
                    SUM(total_amount) as total_amount
                  FROM orders
                  WHERE delivery_date >= ? AND delivery_date <= ?
                  GROUP BY user_id, user_code, user_name, company_name, department_name
                  ORDER BY order_count DESC";
    
    $ordersData = $db->fetchAll($ordersSql, [$periodStart, $periodEnd]);
    
    if (empty($ordersData)) {
        echo "<p class='error'>❌ 指定期間にordersデータが存在しません</p>";
        echo "<p>期間を変更してください: <a href='?period_start=2025-08-01&period_end=2025-08-31'>2025年8月のデータを確認</a></p>";
    } else {
        echo "<p class='success'>✅ {$periodStart}〜{$periodEnd}期間に" . count($ordersData) . "名の利用者データが存在します</p>";
        
        echo "<table>";
        echo "<tr><th>user_id</th><th>user_code</th><th>user_name</th><th>company_name</th><th>department</th><th>注文数</th><th>合計金額</th></tr>";
        foreach ($ordersData as $order) {
            echo "<tr>";
            echo "<td>" . ($order['user_id'] ?? '<span class="warning">NULL</span>') . "</td>";
            echo "<td>{$order['user_code']}</td>";
            echo "<td>{$order['user_name']}</td>";
            echo "<td>{$order['company_name']}</td>";
            echo "<td>" . ($order['department_name'] ?? '<span class="warning">NULL</span>') . "</td>";
            echo "<td>{$order['order_count']}</td>";
            echo "<td>" . number_format($order['total_amount']) . "円</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    if (!empty($ordersData)) {
        // Step 2: 各利用者のcompany_id, user_id取得確認
        echo "<h2>Step 2: 利用者ごとのID取得確認</h2>";
        
        foreach ($ordersData as $index => $orderData) {
            if ($index >= 5) {
                echo "<p class='warning'>⚠️ 5名まで表示（全{$ordersData}名）</p>";
                break;
            }
            
            echo "<h3>利用者 " . ($index + 1) . ": {$orderData['user_name']} ({$orderData['user_code']})</h3>";
            
            // company_id取得
            echo "<div class='step'>";
            echo "<strong>company_id取得:</strong><br>";
            $companyQuery = "SELECT id, company_name FROM companies WHERE company_name = ? LIMIT 1";
            echo "<pre>SQL: " . htmlspecialchars($companyQuery) . "\nパラメータ: " . htmlspecialchars($orderData['company_name']) . "</pre>";
            
            $companyResult = $db->fetch($companyQuery, [$orderData['company_name']]);
            if ($companyResult) {
                echo "<p class='success'>✅ company_id取得成功: {$companyResult['id']} ({$companyResult['company_name']})</p>";
            } else {
                echo "<p class='error'>❌ company_id取得失敗: companiesテーブルに「{$orderData['company_name']}」が見つかりません</p>";
                
                // 類似企業名を検索
                $similarSql = "SELECT id, company_name FROM companies WHERE company_name LIKE ? LIMIT 5";
                $similarCompanies = $db->fetchAll($similarSql, ['%' . $orderData['company_name'] . '%']);
                if (!empty($similarCompanies)) {
                    echo "<p class='warning'>類似する企業名:</p><ul>";
                    foreach ($similarCompanies as $similar) {
                        echo "<li>{$similar['company_name']} (ID: {$similar['id']})</li>";
                    }
                    echo "</ul>";
                }
            }
            echo "</div>";
            
            // user_id取得
            echo "<div class='step'>";
            echo "<strong>user_id取得:</strong><br>";
            $userQuery = "SELECT id, user_code, user_name FROM users WHERE user_code = ? LIMIT 1";
            echo "<pre>SQL: " . htmlspecialchars($userQuery) . "\nパラメータ: " . htmlspecialchars($orderData['user_code']) . "</pre>";
            
            $userResult = $db->fetch($userQuery, [$orderData['user_code']]);
            if ($userResult) {
                echo "<p class='success'>✅ user_id取得成功: {$userResult['id']} ({$userResult['user_name']} / {$userResult['user_code']})</p>";
            } else {
                echo "<p class='error'>❌ user_id取得失敗: usersテーブルに「{$orderData['user_code']}」が見つかりません</p>";
                
                // ordersテーブルのuser_idを確認
                if (!empty($orderData['user_id'])) {
                    echo "<p class='warning'>⚠️ ordersテーブルにはuser_id={$orderData['user_id']}が記録されています</p>";
                    
                    // このuser_idがusersテーブルに存在するか確認
                    $userCheckSql = "SELECT id, user_code, user_name FROM users WHERE id = ?";
                    $userCheck = $db->fetch($userCheckSql, [$orderData['user_id']]);
                    if ($userCheck) {
                        echo "<p class='success'>✅ usersテーブルにID={$orderData['user_id']}は存在します: {$userCheck['user_name']} ({$userCheck['user_code']})</p>";
                    } else {
                        echo "<p class='error'>❌ usersテーブルにID={$orderData['user_id']}が存在しません（データ不整合）</p>";
                    }
                }
            }
            echo "</div>";
        }
        
        // Step 3: 実際の請求書生成シミュレーション
        echo "<h2>Step 3: 請求書生成シミュレーション</h2>";
        
        $testUser = $ordersData[0];
        echo "<p>テスト対象: {$testUser['user_name']} ({$testUser['user_code']})</p>";
        
        // 注文データ取得
        $orderDetailsSql = "SELECT * FROM orders 
                           WHERE user_code = ? 
                           AND delivery_date >= ? 
                           AND delivery_date <= ?
                           ORDER BY delivery_date";
        $orderDetails = $db->fetchAll($orderDetailsSql, [$testUser['user_code'], $periodStart, $periodEnd]);
        
        echo "<p class='success'>✅ 注文データ取得: " . count($orderDetails) . "件</p>";
        
        // 金額計算
        $subtotal = array_sum(array_column($orderDetails, 'total_amount'));
        $taxAmount = round($subtotal * 0.10);
        $totalAmount = $subtotal + $taxAmount;
        
        echo "<div class='step'>";
        echo "<strong>金額計算:</strong><br>";
        echo "小計: " . number_format($subtotal) . "円<br>";
        echo "消費税: " . number_format($taxAmount) . "円<br>";
        echo "合計: " . number_format($totalAmount) . "円";
        echo "</div>";
        
        // company_id, user_id取得
        $companyId = null;
        if (!empty($testUser['company_name'])) {
            $companyResult = $db->fetch("SELECT id FROM companies WHERE company_name = ? LIMIT 1", [$testUser['company_name']]);
            $companyId = $companyResult ? $companyResult['id'] : null;
        }
        
        $userId = null;
        if (!empty($testUser['user_code'])) {
            $userResult = $db->fetch("SELECT id FROM users WHERE user_code = ? LIMIT 1", [$testUser['user_code']]);
            $userId = $userResult ? $userResult['id'] : null;
        }
        
        echo "<div class='step'>";
        echo "<strong>ID取得結果:</strong><br>";
        echo "company_id: " . ($companyId ? "<span class='success'>{$companyId}</span>" : "<span class='error'>NULL</span>") . "<br>";
        echo "user_id: " . ($userId ? "<span class='success'>{$userId}</span>" : "<span class='error'>NULL</span>");
        echo "</div>";
        
        // INSERT文生成
        echo "<h3>生成されるINSERT文:</h3>";
        $invoiceNumber = "TEST-202510-001";
        $dueDate = date('Y-m-d', strtotime($periodEnd . ' +30 days'));
        
        $insertSql = "INSERT INTO invoices (
                        invoice_number, company_id, user_id, user_code, user_name,
                        company_name, department,
                        invoice_date, due_date, period_start, period_end,
                        subtotal, tax_rate, tax_amount, total_amount,
                        invoice_type, status,
                        created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, 'draft', NOW(), NOW())";
        
        echo "<pre>" . htmlspecialchars($insertSql) . "</pre>";
        
        echo "<h3>パラメータ値:</h3>";
        $params = [
            $invoiceNumber,
            $companyId,
            $userId,
            $testUser['user_code'],
            $testUser['user_name'],
            $testUser['company_name'],
            $testUser['department_name'] ?? null,
            $dueDate,
            $periodStart,
            $periodEnd,
            $subtotal,
            10.00,
            $taxAmount,
            $totalAmount,
            'individual'
        ];
        
        echo "<pre>";
        foreach ($params as $i => $param) {
            $paramNum = $i + 1;
            $value = $param === null ? 'NULL' : $param;
            echo "パラメータ{$paramNum}: " . htmlspecialchars($value) . "\n";
        }
        echo "</pre>";
        
        // 実際にINSERTを試みる（テストモード）
        if (isset($_GET['test_insert']) && $_GET['test_insert'] === '1') {
            echo "<h3>⚠️ テストINSERT実行</h3>";
            try {
                $db->beginTransaction();
                $db->execute($insertSql, $params);
                $insertedId = $db->lastInsertId();
                $db->rollback(); // ロールバック（テストなので実際にはコミットしない）
                
                echo "<p class='success'>✅ INSERT文は正常に実行できます（ロールバックしました）</p>";
                echo "<p>挿入予定ID: {$insertedId}</p>";
            } catch (Exception $e) {
                $db->rollback();
                echo "<p class='error'>❌ INSERT実行エラー: " . htmlspecialchars($e->getMessage()) . "</p>";
            }
        } else {
            echo "<p><a href='?period_start={$periodStart}&period_end={$periodEnd}&test_insert=1'>テストINSERTを実行する（実際にはロールバック）</a></p>";
        }
    }
    
    // Step 4: SmileyInvoiceGenerator.phpの読み込み確認
    echo "<h2>Step 4: SmileyInvoiceGenerator.php確認</h2>";
    
    $generatorPath = __DIR__ . '/../classes/SmileyInvoiceGenerator.php';
    if (file_exists($generatorPath)) {
        echo "<p class='success'>✅ ファイル存在: {$generatorPath}</p>";
        echo "<p>ファイルサイズ: " . number_format(filesize($generatorPath)) . " bytes</p>";
        echo "<p>最終更新: " . date('Y-m-d H:i:s', filemtime($generatorPath)) . "</p>";
        
        // createInvoiceメソッドの確認
        $content = file_get_contents($generatorPath);
        
        if (strpos($content, 'company_id') !== false) {
            echo "<p class='success'>✅ company_idの処理が含まれています</p>";
        } else {
            echo "<p class='error'>❌ company_idの処理が見つかりません（修正版がアップロードされていない可能性）</p>";
        }
        
        if (strpos($content, 'SELECT id FROM companies WHERE company_name = ?') !== false) {
            echo "<p class='success'>✅ company_id取得SQLが含まれています</p>";
        } else {
            echo "<p class='error'>❌ company_id取得SQLが見つかりません</p>";
        }
        
        if (strpos($content, 'SELECT id FROM users WHERE user_code = ?') !== false) {
            echo "<p class='success'>✅ user_id取得SQLが含まれています</p>";
        } else {
            echo "<p class='error'>❌ user_id取得SQLが見つかりません</p>";
        }
    } else {
        echo "<p class='error'>❌ ファイルが見つかりません: {$generatorPath}</p>";
    }
    
    // Step 5: PHPエラーログ確認
    echo "<h2>Step 5: 最近のPHPエラーログ</h2>";
    
    $errorLog = ini_get('error_log');
    if ($errorLog && file_exists($errorLog)) {
        $logContent = file_get_contents($errorLog);
        $lines = explode("\n", $logContent);
        $recentErrors = array_slice(array_reverse($lines), 0, 20);
        
        echo "<pre style='max-height: 300px; overflow-y: auto;'>";
        foreach ($recentErrors as $line) {
            if (!empty(trim($line))) {
                echo htmlspecialchars($line) . "\n";
            }
        }
        echo "</pre>";
    } else {
        echo "<p class='warning'>⚠️ エラーログファイルが見つかりません</p>";
    }
    
    echo "<hr>";
    echo "<p><strong>診断完了時刻:</strong> " . date('Y-m-d H:i:s') . "</p>";
    
} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<h2>❌ エラー発生</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    echo "</div>";
}
?>
