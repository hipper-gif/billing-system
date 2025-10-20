<?php
/**
 * 請求書番号生成デバッグツール
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/database.php';

echo "<h1>🔍 請求書番号生成デバッグ</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    h1 { color: #4CAF50; }
    h2 { color: #2196F3; }
    .success { color: #4CAF50; font-weight: bold; }
    .error { color: #F44336; font-weight: bold; }
    pre { background: #f5f5f5; padding: 10px; border-radius: 5px; }
</style>";

try {
    $db = Database::getInstance();
    
    // 現在の年月
    $year = date('Y');
    $month = date('m');
    $prefix = "SMY-{$year}{$month}-";
    
    echo "<h2>Step 1: 現在の設定</h2>";
    echo "<p>年: {$year}</p>";
    echo "<p>月: {$month}</p>";
    echo "<p>接頭辞: {$prefix}</p>";
    
    // 既存の請求書番号を確認
    echo "<h2>Step 2: 既存の請求書番号</h2>";
    
    $sql = "SELECT invoice_number, created_at FROM invoices 
            WHERE invoice_number LIKE ? 
            ORDER BY created_at DESC";
    
    $invoices = $db->fetchAll($sql, [$prefix . '%']);
    
    if (empty($invoices)) {
        echo "<p class='success'>✅ {$prefix}で始まる請求書は存在しません</p>";
        echo "<p>次の請求書番号: <strong>{$prefix}001</strong></p>";
    } else {
        echo "<p class='error'>⚠️ {$prefix}で始まる請求書が" . count($invoices) . "件存在します</p>";
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>請求書番号</th><th>作成日時</th></tr>";
        foreach ($invoices as $inv) {
            echo "<tr><td>{$inv['invoice_number']}</td><td>{$inv['created_at']}</td></tr>";
        }
        echo "</table>";
        
        // 最後の番号を取得
        $lastInvoice = $invoices[0];
        $lastNumber = intval(substr($lastInvoice['invoice_number'], -3));
        $newNumber = $lastNumber + 1;
        
        echo "<p>最後の請求書番号: <strong>{$lastInvoice['invoice_number']}</strong></p>";
        echo "<p>最後の番号: <strong>{$lastNumber}</strong></p>";
        echo "<p>次の番号: <strong>{$newNumber}</strong></p>";
        echo "<p>次の請求書番号: <strong>" . $prefix . str_pad($newNumber, 3, '0', STR_PAD_LEFT) . "</strong></p>";
    }
    
    // すべての請求書を確認
    echo "<h2>Step 3: すべての請求書</h2>";
    
    $allSql = "SELECT id, invoice_number, user_name, company_name, status, created_at 
               FROM invoices 
               ORDER BY id DESC";
    $allInvoices = $db->fetchAll($allSql);
    
    echo "<p>総件数: " . count($allInvoices) . "件</p>";
    
    if (!empty($allInvoices)) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>請求書番号</th><th>利用者名</th><th>企業名</th><th>ステータス</th><th>作成日時</th></tr>";
        foreach ($allInvoices as $inv) {
            echo "<tr>";
            echo "<td>{$inv['id']}</td>";
            echo "<td>{$inv['invoice_number']}</td>";
            echo "<td>{$inv['user_name']}</td>";
            echo "<td>{$inv['company_name']}</td>";
            echo "<td>{$inv['status']}</td>";
            echo "<td>{$inv['created_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // 削除SQLの提案
    if (!empty($invoices)) {
        echo "<h2>Step 4: 削除SQL</h2>";
        echo "<p class='error'>以下のSQLをphpMyAdminで実行してください：</p>";
        echo "<pre>";
        echo "-- invoice_detailsから削除\n";
        echo "DELETE FROM invoice_details WHERE invoice_id IN (\n";
        echo "    SELECT id FROM invoices WHERE invoice_number LIKE '{$prefix}%'\n";
        echo ");\n\n";
        echo "-- invoicesから削除\n";
        echo "DELETE FROM invoices WHERE invoice_number LIKE '{$prefix}%';\n\n";
        echo "-- 確認\n";
        echo "SELECT * FROM invoices WHERE invoice_number LIKE '{$prefix}%';";
        echo "</pre>";
    }
    
} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<h2>❌ エラー発生</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}

echo "<hr>";
echo "<p><strong>確認完了時刻:</strong> " . date('Y-m-d H:i:s') . "</p>";
?>
