<?php
/**
 * 請求書デバッグスクリプト
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config/database.php';

header('Content-Type: text/html; charset=utf-8');

echo "<h1>請求書デバッグ情報</h1>";

$db = Database::getInstance();

// 1. ordersテーブルの状態確認
echo "<h2>1. 注文データ (orders)</h2>";
try {
    $orderCount = $db->fetch("SELECT COUNT(*) as count FROM orders");
    echo "<p>総注文数: <strong>{$orderCount['count']}</strong></p>";

    if ($orderCount['count'] > 0) {
        $dateRange = $db->fetch("SELECT MIN(order_date) as min_date, MAX(order_date) as max_date FROM orders");
        echo "<p>注文日の範囲: <strong>{$dateRange['min_date']}</strong> ～ <strong>{$dateRange['max_date']}</strong></p>";

        // 企業別の集計
        $companies = $db->fetchAll("
            SELECT
                company_name,
                COUNT(*) as order_count,
                SUM(total_amount) as total_amount,
                MIN(order_date) as first_order,
                MAX(order_date) as last_order
            FROM orders
            WHERE company_name IS NOT NULL AND company_name != ''
            GROUP BY company_name
            ORDER BY order_count DESC
            LIMIT 10
        ");

        echo "<h3>企業別注文集計 (上位10社)</h3>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>企業名</th><th>注文数</th><th>合計金額</th><th>最初の注文</th><th>最後の注文</th></tr>";
        foreach ($companies as $company) {
            echo "<tr>";
            echo "<td>{$company['company_name']}</td>";
            echo "<td>{$company['order_count']}</td>";
            echo "<td>¥" . number_format($company['total_amount']) . "</td>";
            echo "<td>{$company['first_order']}</td>";
            echo "<td>{$company['last_order']}</td>";
            echo "</tr>";
        }
        echo "</table>";

        // 最近の注文サンプル
        $recentOrders = $db->fetchAll("
            SELECT id, order_date, company_name, user_name, product_name, quantity, unit_price, total_amount
            FROM orders
            ORDER BY order_date DESC
            LIMIT 5
        ");

        echo "<h3>最近の注文 (最新5件)</h3>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>注文日</th><th>企業名</th><th>利用者名</th><th>商品名</th><th>数量</th><th>単価</th><th>金額</th></tr>";
        foreach ($recentOrders as $order) {
            echo "<tr>";
            echo "<td>{$order['id']}</td>";
            echo "<td>{$order['order_date']}</td>";
            echo "<td>{$order['company_name']}</td>";
            echo "<td>{$order['user_name']}</td>";
            echo "<td>{$order['product_name']}</td>";
            echo "<td>{$order['quantity']}</td>";
            echo "<td>¥" . number_format($order['unit_price']) . "</td>";
            echo "<td>¥" . number_format($order['total_amount']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "<p style='color:red;'>エラー: {$e->getMessage()}</p>";
}

// 2. 請求書の状態確認
echo "<h2>2. 請求書データ (invoices)</h2>";
try {
    $invoiceCount = $db->fetch("SELECT COUNT(*) as count FROM invoices");
    echo "<p>総請求書数: <strong>{$invoiceCount['count']}</strong></p>";

    if ($invoiceCount['count'] > 0) {
        // 最新の請求書
        $latestInvoices = $db->fetchAll("
            SELECT id, invoice_number, company_name, invoice_type, period_start, period_end,
                   subtotal, tax_amount, total_amount, created_at
            FROM invoices
            ORDER BY created_at DESC
            LIMIT 5
        ");

        echo "<h3>最新の請求書 (最新5件)</h3>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>請求書番号</th><th>企業名</th><th>タイプ</th><th>期間</th><th>小計</th><th>税額</th><th>合計</th><th>作成日時</th></tr>";
        foreach ($latestInvoices as $invoice) {
            echo "<tr>";
            echo "<td><strong>{$invoice['id']}</strong></td>";
            echo "<td>{$invoice['invoice_number']}</td>";
            echo "<td>{$invoice['company_name']}</td>";
            echo "<td>{$invoice['invoice_type']}</td>";
            echo "<td>{$invoice['period_start']} ～ {$invoice['period_end']}</td>";
            echo "<td>¥" . number_format($invoice['subtotal']) . "</td>";
            echo "<td>¥" . number_format($invoice['tax_amount']) . "</td>";
            echo "<td>¥" . number_format($invoice['total_amount']) . "</td>";
            echo "<td>{$invoice['created_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "<p style='color:red;'>エラー: {$e->getMessage()}</p>";
}

// 3. 請求書明細の状態確認
echo "<h2>3. 請求書明細 (invoice_details)</h2>";
try {
    $detailCount = $db->fetch("SELECT COUNT(*) as count FROM invoice_details");
    echo "<p>総明細数: <strong>{$detailCount['count']}</strong></p>";

    if ($detailCount['count'] > 0) {
        // 請求書ごとの明細数
        $detailsByInvoice = $db->fetchAll("
            SELECT
                i.id,
                i.invoice_number,
                i.company_name,
                COUNT(d.id) as detail_count,
                SUM(d.amount) as detail_total
            FROM invoices i
            LEFT JOIN invoice_details d ON i.id = d.invoice_id
            GROUP BY i.id
            ORDER BY i.created_at DESC
            LIMIT 10
        ");

        echo "<h3>請求書別明細数 (最新10件)</h3>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>請求書ID</th><th>請求書番号</th><th>企業名</th><th>明細数</th><th>明細合計</th></tr>";
        foreach ($detailsByInvoice as $invoice) {
            $detailCountCell = $invoice['detail_count'] > 0
                ? "<td style='color:green;'><strong>{$invoice['detail_count']}</strong></td>"
                : "<td style='color:red;'><strong>0</strong></td>";
            echo "<tr>";
            echo "<td>{$invoice['id']}</td>";
            echo "<td>{$invoice['invoice_number']}</td>";
            echo "<td>{$invoice['company_name']}</td>";
            echo $detailCountCell;
            echo "<td>¥" . number_format($invoice['detail_total']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";

        // 実際の明細サンプル
        $sampleDetails = $db->fetchAll("
            SELECT d.*, i.invoice_number
            FROM invoice_details d
            JOIN invoices i ON d.invoice_id = i.id
            ORDER BY d.created_at DESC
            LIMIT 10
        ");

        if (!empty($sampleDetails)) {
            echo "<h3>明細サンプル (最新10件)</h3>";
            echo "<table border='1' cellpadding='5'>";
            echo "<tr><th>請求書番号</th><th>注文日</th><th>利用者名</th><th>商品名</th><th>数量</th><th>単価</th><th>金額</th></tr>";
            foreach ($sampleDetails as $detail) {
                echo "<tr>";
                echo "<td>{$detail['invoice_number']}</td>";
                echo "<td>{$detail['order_date']}</td>";
                echo "<td>{$detail['user_name']}</td>";
                echo "<td>{$detail['product_name']}</td>";
                echo "<td>{$detail['quantity']}</td>";
                echo "<td>¥" . number_format($detail['unit_price']) . "</td>";
                echo "<td>¥" . number_format($detail['amount']) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    } else {
        echo "<p style='color:red; font-weight:bold;'>⚠️ 明細データが1件も存在しません！これが「明細データがない」エラーの原因です。</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red;'>エラー: {$e->getMessage()}</p>";
}

// 4. 特定期間のクエリテスト
echo "<h2>4. クエリテスト（先月のデータ）</h2>";
try {
    $lastMonthStart = date('Y-m-01', strtotime('-1 month'));
    $lastMonthEnd = date('Y-m-t', strtotime('-1 month'));

    echo "<p>テスト期間: <strong>{$lastMonthStart}</strong> ～ <strong>{$lastMonthEnd}</strong></p>";

    $testQuery = "SELECT company_name, COUNT(*) as count, SUM(total_amount) as total
                  FROM orders
                  WHERE order_date >= ? AND order_date <= ?
                  AND company_name IS NOT NULL AND company_name != ''
                  GROUP BY company_name";

    $testResults = $db->fetchAll($testQuery, [$lastMonthStart, $lastMonthEnd]);

    if (!empty($testResults)) {
        echo "<h3>期間内の注文データ（企業別）</h3>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>企業名</th><th>注文数</th><th>合計金額</th></tr>";
        foreach ($testResults as $result) {
            echo "<tr>";
            echo "<td>{$result['company_name']}</td>";
            echo "<td>{$result['count']}</td>";
            echo "<td>¥" . number_format($result['total']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color:orange;'>⚠️ この期間には注文データがありません。別の期間で試してください。</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red;'>エラー: {$e->getMessage()}</p>";
}

echo "<hr>";
echo "<p>現在時刻: " . date('Y-m-d H:i:s') . "</p>";
?>
