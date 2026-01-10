<?php
/**
 * 請求書明細挿入テスト - リアルタイムデバッグ
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/classes/SmileyInvoiceGenerator.php';

header('Content-Type: text/html; charset=utf-8');

echo "<h1>請求書明細挿入テスト</h1>";
echo "<p>このスクリプトは実際に請求書を生成し、明細挿入プロセスをデバッグします。</p>";

// カスタムエラーハンドラー
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    echo "<div style='background:#ffcccc; padding:10px; margin:10px 0; border-left:4px solid red;'>";
    echo "<strong>PHP Error [{$errno}]:</strong> {$errstr}<br>";
    echo "<strong>File:</strong> {$errfile}<br>";
    echo "<strong>Line:</strong> {$errline}";
    echo "</div>";
});

// カスタムエラーログキャプチャ
$logMessages = [];
$originalErrorLog = ini_get('error_log');
ini_set('error_log', '/tmp/invoice_test_debug.log');

// テストパラメータ
$testParams = [
    'invoice_type' => 'company_bulk',
    'period_start' => '2025-12-01',
    'period_end' => '2025-12-31',
    'due_date' => '2026-01-30',
    'target_ids' => [2], // ロイヤルケアのID
    'auto_generate_pdf' => false
];

echo "<h2>テストパラメータ</h2>";
echo "<pre>" . print_r($testParams, true) . "</pre>";

try {
    $generator = new SmileyInvoiceGenerator();

    echo "<h2>請求書生成開始...</h2>";
    echo "<p style='color:green;'>SmileyInvoiceGenerator インスタンス作成成功</p>";

    // generateInvoices を実行
    ob_start(); // 出力バッファリング開始
    $result = $generator->generateInvoices($testParams);
    $output = ob_get_clean();

    if ($output) {
        echo "<h3>生成中の出力:</h3>";
        echo "<pre style='background:#f0f0f0; padding:10px;'>" . htmlspecialchars($output) . "</pre>";
    }

    echo "<h2>生成結果</h2>";
    echo "<pre style='background:#e8f5e9; padding:15px; border-left:4px solid green;'>";
    print_r($result);
    echo "</pre>";

    if (!empty($result['invoices'])) {
        $invoice = $result['invoices'][0];
        $invoiceId = $invoice['id'];

        echo "<h2>生成された請求書</h2>";
        echo "<p>請求書ID: <strong>{$invoiceId}</strong></p>";
        echo "<p>請求書番号: <strong>{$invoice['invoice_number']}</strong></p>";
        echo "<p>企業名: <strong>{$invoice['company_name']}</strong></p>";
        echo "<p>合計金額: <strong>¥" . number_format($invoice['total_amount']) . "</strong></p>";

        // 明細データを確認
        $db = Database::getInstance();
        $detailCountSql = "SELECT COUNT(*) as count FROM invoice_details WHERE invoice_id = ?";
        $detailCount = $db->fetch($detailCountSql, [$invoiceId]);

        echo "<h2>明細データ確認</h2>";
        echo "<p>明細件数: <strong style='font-size:24px; " . ($detailCount['count'] > 0 ? "color:green" : "color:red") . "'>{$detailCount['count']}</strong> 件</p>";

        if ($detailCount['count'] > 0) {
            echo "<p style='background:#e8f5e9; padding:10px; border-left:4px solid green;'>";
            echo "✓ 明細データが正常に挿入されました！";
            echo "</p>";

            $detailsSql = "SELECT * FROM invoice_details WHERE invoice_id = ? ORDER BY order_date LIMIT 10";
            $details = $db->fetchAll($detailsSql, [$invoiceId]);

            echo "<h3>挿入された明細（最初の10件）</h3>";
            echo "<table border='1' cellpadding='5' style='border-collapse:collapse;'>";
            echo "<tr style='background:#f0f0f0;'><th>order_id</th><th>order_date</th><th>product_name</th><th>quantity</th><th>unit_price</th><th>amount</th></tr>";

            foreach ($details as $detail) {
                echo "<tr>";
                echo "<td>{$detail['order_id']}</td>";
                echo "<td>{$detail['order_date']}</td>";
                echo "<td>{$detail['product_name']}</td>";
                echo "<td>{$detail['quantity']}</td>";
                echo "<td>¥" . number_format($detail['unit_price']) . "</td>";
                echo "<td>¥" . number_format($detail['amount']) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p style='background:#ffebee; padding:10px; border-left:4px solid red;'>";
            echo "✗ 明細データが挿入されませんでした！";
            echo "</p>";
        }
    } else {
        echo "<p style='color:red;'>請求書が生成されませんでした。</p>";
    }

} catch (Exception $e) {
    echo "<h2 style='color:red;'>エラー発生</h2>";
    echo "<div style='background:#ffebee; padding:15px; border-left:4px solid red;'>";
    echo "<strong>エラーメッセージ:</strong> " . htmlspecialchars($e->getMessage()) . "<br>";
    echo "<strong>ファイル:</strong> " . htmlspecialchars($e->getFile()) . "<br>";
    echo "<strong>行:</strong> " . $e->getLine() . "<br>";
    echo "<strong>スタックトレース:</strong><br>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    echo "</div>";
}

// デバッグログファイルを確認
if (file_exists('/tmp/invoice_test_debug.log')) {
    $logContent = file_get_contents('/tmp/invoice_test_debug.log');
    if ($logContent) {
        echo "<h2>デバッグログ</h2>";
        echo "<pre style='background:#fff3e0; padding:10px; max-height:400px; overflow-y:auto;'>";
        echo htmlspecialchars($logContent);
        echo "</pre>";
    }
}

echo "<hr>";
echo "<p>現在時刻: " . date('Y-m-d H:i:s') . "</p>";
echo "<p><a href='debug_invoice.php'>デバッグ情報に戻る</a></p>";
?>
