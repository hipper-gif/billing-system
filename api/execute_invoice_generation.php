<?php
/**
 * 請求書生成 詳細ログ記録ツール
 * 
 * このツールを使って実際に請求書を生成し、各ステップの結果を記録
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/SmileyInvoiceGenerator.php';

header('Content-Type: text/html; charset=utf-8');

echo "<h1>🔧 請求書生成 実行ログ</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    h1 { color: #4CAF50; }
    h2 { color: #2196F3; border-bottom: 2px solid #2196F3; padding-bottom: 5px; margin-top: 30px; }
    .success { color: #4CAF50; font-weight: bold; }
    .error { color: #F44336; font-weight: bold; }
    .warning { color: #FF9800; font-weight: bold; }
    .step { background: #e3f2fd; padding: 15px; margin: 10px 0; border-left: 4px solid #2196F3; }
    pre { background: #f5f5f5; padding: 10px; border-radius: 5px; overflow-x: auto; font-size: 12px; }
</style>";

try {
    // パラメータ設定
    $periodStart = $_GET['period_start'] ?? '2025-08-01';
    $periodEnd = $_GET['period_end'] ?? '2025-08-31';
    $invoiceType = $_GET['invoice_type'] ?? 'individual';
    $executeGeneration = isset($_GET['execute']) && $_GET['execute'] === '1';
    
    echo "<div class='step'>";
    echo "<strong>設定パラメータ:</strong><br>";
    echo "請求書種別: {$invoiceType}<br>";
    echo "請求期間: {$periodStart} 〜 {$periodEnd}<br>";
    echo "実行モード: " . ($executeGeneration ? '<span class="error">本番実行</span>' : '<span class="success">シミュレーション</span>') . "<br>";
    if (!$executeGeneration) {
        echo "<br><a href='?period_start={$periodStart}&period_end={$periodEnd}&invoice_type={$invoiceType}&execute=1' style='background: #4CAF50; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>本番実行する</a>";
    }
    echo "</div>";
    
    // Step 1: 生成パラメータ構築
    echo "<h2>Step 1: パラメータ構築</h2>";
    
    $dueDate = date('Y-m-d', strtotime($periodEnd . ' +30 days'));
    
    $params = [
        'invoice_type' => $invoiceType,
        'period_start' => $periodStart,
        'period_end' => $periodEnd,
        'due_date' => $dueDate,
        'target_ids' => [], // 空の場合、全利用者
        'auto_generate_pdf' => false
    ];
    
    echo "<pre>" . print_r($params, true) . "</pre>";
    
    // Step 2: 対象データ確認
    echo "<h2>Step 2: 対象データ確認</h2>";
    
    $db = Database::getInstance();
    
    $usersSql = "SELECT DISTINCT user_id, user_code, user_name, company_name 
                 FROM orders 
                 WHERE delivery_date >= ? AND delivery_date <= ?";
    $users = $db->fetchAll($usersSql, [$periodStart, $periodEnd]);
    
    echo "<p class='success'>✅ 対象利用者: " . count($users) . "名</p>";
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>user_code</th><th>user_name</th><th>company_name</th></tr>";
    foreach (array_slice($users, 0, 10) as $user) {
        echo "<tr>";
        echo "<td>{$user['user_code']}</td>";
        echo "<td>{$user['user_name']}</td>";
        echo "<td>{$user['company_name']}</td>";
        echo "</tr>";
    }
    if (count($users) > 10) {
        echo "<tr><td colspan='3' style='text-align: center;'>...他" . (count($users) - 10) . "名...</td></tr>";
    }
    echo "</table>";
    
    if (!$executeGeneration) {
        echo "<p class='warning'>⚠️ シミュレーションモード: 実際には生成されません</p>";
        echo "<hr>";
        echo "<p><strong>確認完了時刻:</strong> " . date('Y-m-d H:i:s') . "</p>";
        exit;
    }
    
    // Step 3: SmileyInvoiceGenerator初期化
    echo "<h2>Step 3: SmileyInvoiceGenerator初期化</h2>";
    
    try {
        $generator = new SmileyInvoiceGenerator();
        echo "<p class='success'>✅ SmileyInvoiceGeneratorインスタンス作成成功</p>";
    } catch (Exception $e) {
        echo "<p class='error'>❌ インスタンス作成失敗: " . htmlspecialchars($e->getMessage()) . "</p>";
        throw $e;
    }
    
    // Step 4: 請求書生成実行
    echo "<h2>Step 4: 請求書生成実行</h2>";
    echo "<p class='warning'>⚠️ 本番実行中...</p>";
    
    $startTime = microtime(true);
    
    try {
        $result = $generator->generateInvoices($params);
        
        $executionTime = microtime(true) - $startTime;
        
        echo "<p class='success'>✅ 請求書生成完了！（処理時間: " . number_format($executionTime, 2) . "秒）</p>";
        
        // 結果表示
        echo "<h2>Step 5: 生成結果</h2>";
        
        echo "<div class='step'>";
        echo "<strong>生成件数:</strong> " . ($result['total_invoices'] ?? 0) . "件<br>";
        echo "<strong>成功:</strong> " . (isset($result['success']) && $result['success'] ? 'はい' : 'いいえ') . "<br>";
        if (!empty($result['errors'])) {
            echo "<strong class='error'>エラー:</strong><br>";
            echo "<pre>" . print_r($result['errors'], true) . "</pre>";
        }
        echo "</div>";
        
        // 生成された請求書の詳細
        if (!empty($result['invoices'])) {
            echo "<h3>生成された請求書一覧:</h3>";
            echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
            echo "<tr><th>ID</th><th>請求書番号</th><th>利用者名</th><th>企業名</th><th>金額</th><th>ステータス</th></tr>";
            
            foreach ($result['invoices'] as $invoice) {
                echo "<tr>";
                echo "<td>{$invoice['id']}</td>";
                echo "<td>{$invoice['invoice_number']}</td>";
                echo "<td>{$invoice['user_name']}</td>";
                echo "<td>{$invoice['company_name']}</td>";
                echo "<td>" . number_format($invoice['total_amount']) . "円</td>";
                echo "<td>{$invoice['status']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p class='warning'>⚠️ 生成された請求書がありません</p>";
        }
        
        // データベース確認
        echo "<h2>Step 6: データベース確認</h2>";
        
        $checkSql = "SELECT 
                        id, 
                        invoice_number, 
                        company_id, 
                        user_id, 
                        company_name, 
                        user_name,
                        total_amount,
                        status,
                        created_at
                     FROM invoices 
                     WHERE created_at >= NOW() - INTERVAL 5 MINUTE
                     ORDER BY id DESC";
        
        $recentInvoices = $db->fetchAll($checkSql);
        
        if (!empty($recentInvoices)) {
            echo "<p class='success'>✅ 最近5分以内に作成された請求書: " . count($recentInvoices) . "件</p>";
            
            echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
            echo "<tr><th>ID</th><th>請求書番号</th><th>company_id</th><th>user_id</th><th>企業名</th><th>利用者名</th><th>金額</th><th>ステータス</th><th>作成日時</th></tr>";
            
            foreach ($recentInvoices as $inv) {
                echo "<tr>";
                echo "<td>{$inv['id']}</td>";
                echo "<td>{$inv['invoice_number']}</td>";
                echo "<td>" . ($inv['company_id'] ? "<span class='success'>{$inv['company_id']}</span>" : "<span class='error'>NULL</span>") . "</td>";
                echo "<td>" . ($inv['user_id'] ? "<span class='success'>{$inv['user_id']}</span>" : "<span class='error'>NULL</span>") . "</td>";
                echo "<td>{$inv['company_name']}</td>";
                echo "<td>{$inv['user_name']}</td>";
                echo "<td>" . number_format($inv['total_amount']) . "円</td>";
                echo "<td>{$inv['status']}</td>";
                echo "<td>{$inv['created_at']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p class='error'>❌ 最近5分以内に作成された請求書がありません</p>";
        }
        
    } catch (Exception $e) {
        echo "<p class='error'>❌ 請求書生成エラー:</p>";
        echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
        echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    }
    
} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<h2>❌ エラー発生</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    echo "</div>";
}

echo "<hr>";
echo "<p><strong>実行完了時刻:</strong> " . date('Y-m-d H:i:s') . "</p>";
?>
