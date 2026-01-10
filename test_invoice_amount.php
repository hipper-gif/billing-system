<?php
/**
 * 請求書生成テストスクリプト - 金額計算デバッグ
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config/database.php';

header('Content-Type: text/html; charset=utf-8');

echo "<h1>請求書生成テスト - 金額計算デバッグ</h1>";

$db = Database::getInstance();

// テスト対象
$companyName = 'ロイヤルケア';
$periodStart = '2025-12-01';
$periodEnd = '2025-12-31';

echo "<h2>テスト条件</h2>";
echo "<p>企業名: <strong>{$companyName}</strong></p>";
echo "<p>期間: <strong>{$periodStart}</strong> ～ <strong>{$periodEnd}</strong></p>";

// 1. 実際の注文データを取得（SmileyInvoiceGeneratorと同じクエリ）
echo "<h2>1. 注文データ取得（SmileyInvoiceGenerator と同じクエリ）</h2>";

$sql = "SELECT * FROM orders
        WHERE company_name = ?
        AND order_date >= ?
        AND order_date <= ?
        ORDER BY order_date, user_name";

$orders = $db->fetchAll($sql, [$companyName, $periodStart, $periodEnd]);

echo "<p>取得件数: <strong>" . count($orders) . "</strong> 件</p>";

$totalFromQuery = 0;
foreach ($orders as $order) {
    $totalFromQuery += $order['total_amount'];
}

echo "<p>合計金額（クエリ結果）: <strong>¥" . number_format($totalFromQuery) . "</strong></p>";

// 2. 注文データの詳細表示
echo "<h3>注文データ詳細</h3>";
echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
echo "<tr style='background:#f0f0f0;'><th>ID</th><th>注文日</th><th>利用者名</th><th>商品名</th><th>数量</th><th>単価</th><th>金額</th></tr>";

$runningTotal = 0;
foreach ($orders as $order) {
    $runningTotal += $order['total_amount'];
    $dateStyle = ($order['order_date'] === '0000-00-00') ? "style='color:red;'" : "";

    echo "<tr>";
    echo "<td>{$order['id']}</td>";
    echo "<td {$dateStyle}>{$order['order_date']}</td>";
    echo "<td>{$order['user_name']}</td>";
    echo "<td>{$order['product_name']}</td>";
    echo "<td>{$order['quantity']}</td>";
    echo "<td>¥" . number_format($order['unit_price']) . "</td>";
    echo "<td>¥" . number_format($order['total_amount']) . "</td>";
    echo "</tr>";
}

echo "<tr style='background:#ffffcc; font-weight:bold;'>";
echo "<td colspan='6' style='text-align:right;'>合計:</td>";
echo "<td>¥" . number_format($runningTotal) . "</td>";
echo "</tr>";
echo "</table>";

// 3. 重複チェック
echo "<h2>2. 重複チェック</h2>";

$orderIds = array_column($orders, 'id');
$uniqueIds = array_unique($orderIds);

if (count($orderIds) !== count($uniqueIds)) {
    echo "<p style='color:red; font-weight:bold;'>⚠️ 警告: 注文IDに重複があります！</p>";

    $duplicates = array_diff_assoc($orderIds, $uniqueIds);
    echo "<p>重複しているID: " . implode(', ', $duplicates) . "</p>";
} else {
    echo "<p style='color:green;'>✓ 重複なし</p>";
}

// 4. 既存の請求書データを確認
echo "<h2>3. 既存の請求書データ</h2>";

$invoiceSql = "SELECT id, invoice_number, period_start, period_end, subtotal, tax_amount, total_amount, created_at
               FROM invoices
               WHERE company_name = ?
               AND period_start = ?
               AND period_end = ?
               ORDER BY created_at DESC
               LIMIT 5";

$invoices = $db->fetchAll($invoiceSql, [$companyName, $periodStart, $periodEnd]);

if (!empty($invoices)) {
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr style='background:#f0f0f0;'><th>ID</th><th>請求書番号</th><th>小計</th><th>税額</th><th>合計</th><th>作成日時</th><th>差異</th></tr>";

    foreach ($invoices as $invoice) {
        $diff = $invoice['total_amount'] - $totalFromQuery;
        $diffStyle = ($diff != 0) ? "style='color:red; font-weight:bold;'" : "style='color:green;'";

        echo "<tr>";
        echo "<td>{$invoice['id']}</td>";
        echo "<td>{$invoice['invoice_number']}</td>";
        echo "<td>¥" . number_format($invoice['subtotal']) . "</td>";
        echo "<td>¥" . number_format($invoice['tax_amount']) . "</td>";
        echo "<td>¥" . number_format($invoice['total_amount']) . "</td>";
        echo "<td>{$invoice['created_at']}</td>";
        echo "<td {$diffStyle}>";
        if ($diff > 0) {
            echo "+¥" . number_format($diff);
        } elseif ($diff < 0) {
            echo "-¥" . number_format(abs($diff));
        } else {
            echo "一致";
        }
        echo "</td>";
        echo "</tr>";
    }
    echo "</table>";

    echo "<p style='background:#ffffcc; padding:10px;'>";
    echo "<strong>正しい金額:</strong> ¥" . number_format($totalFromQuery) . "<br>";
    echo "<strong>最新の請求書:</strong> ¥" . number_format($invoices[0]['total_amount']) . "<br>";
    echo "<strong>差異:</strong> ";
    $latestDiff = $invoices[0]['total_amount'] - $totalFromQuery;
    if ($latestDiff != 0) {
        echo "<span style='color:red; font-weight:bold;'>¥" . number_format(abs($latestDiff)) . " " . ($latestDiff > 0 ? "多い" : "少ない") . "</span>";
    } else {
        echo "<span style='color:green; font-weight:bold;'>一致</span>";
    }
    echo "</p>";
} else {
    echo "<p>この期間の請求書はまだありません。</p>";
}

// 5. 明細データの確認
echo "<h2>4. 請求書明細の確認</h2>";

if (!empty($invoices)) {
    $latestInvoiceId = $invoices[0]['id'];

    $detailSql = "SELECT COUNT(*) as count, SUM(amount) as total FROM invoice_details WHERE invoice_id = ?";
    $detailStats = $db->fetch($detailSql, [$latestInvoiceId]);

    echo "<p>最新請求書ID: <strong>{$latestInvoiceId}</strong></p>";
    echo "<p>明細件数: <strong>{$detailStats['count']}</strong> 件</p>";
    echo "<p>明細合計: <strong>¥" . number_format($detailStats['total']) . "</strong></p>";

    if ($detailStats['count'] == 0) {
        echo "<p style='color:red; font-weight:bold;'>⚠️ 明細データが0件です！これが「明細データがない」エラーの原因です。</p>";
    }

    // 明細データの詳細
    if ($detailStats['count'] > 0) {
        $detailsSql = "SELECT * FROM invoice_details WHERE invoice_id = ? ORDER BY order_date";
        $details = $db->fetchAll($detailsSql, [$latestInvoiceId]);

        echo "<h3>明細データ詳細</h3>";
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
        echo "<tr style='background:#f0f0f0;'><th>注文日</th><th>利用者名</th><th>商品名</th><th>数量</th><th>単価</th><th>金額</th></tr>";

        $detailTotal = 0;
        foreach ($details as $detail) {
            $detailTotal += $detail['amount'];
            echo "<tr>";
            echo "<td>{$detail['order_date']}</td>";
            echo "<td>{$detail['user_name']}</td>";
            echo "<td>{$detail['product_name']}</td>";
            echo "<td>{$detail['quantity']}</td>";
            echo "<td>¥" . number_format($detail['unit_price']) . "</td>";
            echo "<td>¥" . number_format($detail['amount']) . "</td>";
            echo "</tr>";
        }

        echo "<tr style='background:#ffffcc; font-weight:bold;'>";
        echo "<td colspan='5' style='text-align:right;'>合計:</td>";
        echo "<td>¥" . number_format($detailTotal) . "</td>";
        echo "</tr>";
        echo "</table>";
    }
}

echo "<hr>";
echo "<p>現在時刻: " . date('Y-m-d H:i:s') . "</p>";
?>
