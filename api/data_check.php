<?php
/**
 * データ確認ツール
 * 各テーブルのデータ件数と未回収金額を確認
 */

require_once '../config/database.php';

header('Content-Type: text/html; charset=utf-8');

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    
    // 各テーブルのデータ件数を取得
    $tables = [
        'orders' => '注文データ',
        'companies' => '企業',
        'departments' => '部署',
        'users' => '利用者',
        'products' => '商品',
        'suppliers' => '業者',
        'invoices' => '請求書',
        'invoice_details' => '請求書明細',
        'payments' => '支払い記録',
        'receipts' => '領収書'
    ];
    
    $dataStatus = [];
    
    foreach ($tables as $table => $label) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM `{$table}`");
            $result = $stmt->fetch();
            $dataStatus[$table] = [
                'label' => $label,
                'count' => $result['count']
            ];
        } catch (Exception $e) {
            $dataStatus[$table] = [
                'label' => $label,
                'count' => 'エラー',
                'error' => $e->getMessage()
            ];
        }
    }
    
    // 未回収金額の計算
    $outstandingAmount = 0;
    $totalInvoiced = 0;
    $totalPaid = 0;
    
    try {
        // 請求書の合計金額
        $stmt = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) as total FROM invoices WHERE status != 'cancelled'");
        $result = $stmt->fetch();
        $totalInvoiced = $result['total'];
        
        // 支払い済み金額の合計
        $stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE status = 'completed'");
        $result = $stmt->fetch();
        $totalPaid = $result['total'];
        
        // 未回収金額
        $outstandingAmount = $totalInvoiced - $totalPaid;
        
    } catch (Exception $e) {
        $calcError = $e->getMessage();
    }
    
    // 最近のorders
    try {
        $stmt = $pdo->query("SELECT * FROM orders ORDER BY id DESC LIMIT 5");
        $recentOrders = $stmt->fetchAll();
    } catch (Exception $e) {
        $recentOrders = [];
        $ordersError = $e->getMessage();
    }
    
    // 請求書一覧
    try {
        $stmt = $pdo->query("SELECT * FROM invoices ORDER BY id DESC LIMIT 5");
        $recentInvoices = $stmt->fetchAll();
    } catch (Exception $e) {
        $recentInvoices = [];
        $invoicesError = $e->getMessage();
    }
    
    // 支払い記録
    try {
        $stmt = $pdo->query("SELECT * FROM payments ORDER BY id DESC LIMIT 5");
        $recentPayments = $stmt->fetchAll();
    } catch (Exception $e) {
        $recentPayments = [];
        $paymentsError = $e->getMessage();
    }
    
} catch (Exception $e) {
    die("データベース接続エラー: " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>データ確認ツール - Smiley配食システム</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 20px;
            background: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #2c3e50;
            border-bottom: 3px solid #4CAF50;
            padding-bottom: 10px;
        }
        h2 {
            color: #34495e;
            margin-top: 30px;
            border-left: 4px solid #4CAF50;
            padding-left: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #4CAF50;
            color: white;
            font-weight: bold;
        }
        tr:hover {
            background-color: #f5f5f5;
        }
        .summary-box {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .summary-item {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
        .summary-item.green {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
        }
        .summary-item.blue {
            background: linear-gradient(135deg, #2196F3 0%, #1976D2 100%);
        }
        .summary-item.red {
            background: linear-gradient(135deg, #f44336 0%, #d32f2f 100%);
        }
        .summary-item h3 {
            margin: 0 0 10px 0;
            font-size: 14px;
            opacity: 0.9;
        }
        .summary-item .value {
            font-size: 32px;
            font-weight: bold;
            margin: 10px 0;
        }
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
        .badge-success {
            background: #d4edda;
            color: #155724;
        }
        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }
        .badge-danger {
            background: #f8d7da;
            color: #721c24;
        }
        .error {
            color: #f44336;
            background: #ffebee;
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
        }
        .code {
            background: #f5f5f5;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🍱 Smiley配食システム - データ確認ツール</h1>
        
        <h2>📊 テーブルデータ件数</h2>
        <div class="summary-box">
            <?php foreach ($dataStatus as $table => $info): ?>
                <div class="summary-item <?php 
                    if ($info['count'] === 'エラー') echo '';
                    elseif ($info['count'] > 0) echo 'green';
                    else echo 'red';
                ?>">
                    <h3><?= $info['label'] ?></h3>
                    <div class="value"><?= $info['count'] === 'エラー' ? '⚠️' : number_format($info['count']) ?></div>
                    <small class="code"><?= $table ?></small>
                    <?php if (isset($info['error'])): ?>
                        <div style="font-size: 11px; margin-top: 10px; opacity: 0.8;">
                            <?= htmlspecialchars($info['error']) ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        
        <h2>💰 未回収金額サマリー</h2>
        <?php if (isset($calcError)): ?>
            <div class="error">
                <strong>計算エラー:</strong> <?= htmlspecialchars($calcError) ?>
            </div>
        <?php else: ?>
            <div class="summary-box">
                <div class="summary-item blue">
                    <h3>請求書合計</h3>
                    <div class="value">¥<?= number_format($totalInvoiced) ?></div>
                </div>
                <div class="summary-item green">
                    <h3>支払い済み</h3>
                    <div class="value">¥<?= number_format($totalPaid) ?></div>
                </div>
                <div class="summary-item <?= $outstandingAmount > 0 ? 'red' : 'green' ?>">
                    <h3>未回収金額</h3>
                    <div class="value">¥<?= number_format($outstandingAmount) ?></div>
                </div>
            </div>
        <?php endif; ?>
        
        <h2>📦 最近の注文データ（orders）</h2>
        <?php if (isset($ordersError)): ?>
            <div class="error">
                <strong>エラー:</strong> <?= htmlspecialchars($ordersError) ?>
            </div>
        <?php elseif (empty($recentOrders)): ?>
            <p>注文データがありません。CSVインポートを実行してください。</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>配達日</th>
                        <th>利用者</th>
                        <th>企業</th>
                        <th>商品</th>
                        <th>金額</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentOrders as $order): ?>
                        <tr>
                            <td><?= $order['id'] ?></td>
                            <td><?= $order['delivery_date'] ?? '' ?></td>
                            <td><?= htmlspecialchars($order['user_name'] ?? '') ?></td>
                            <td><?= htmlspecialchars($order['company_name'] ?? '') ?></td>
                            <td><?= htmlspecialchars($order['product_name'] ?? '') ?></td>
                            <td>¥<?= number_format($order['total_amount'] ?? 0) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        
        <h2>📄 最近の請求書（invoices）</h2>
        <?php if (isset($invoicesError)): ?>
            <div class="error">
                <strong>エラー:</strong> <?= htmlspecialchars($invoicesError) ?>
            </div>
        <?php elseif (empty($recentInvoices)): ?>
            <p>請求書がありません。請求書生成機能を使用してください。</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>請求書番号</th>
                        <th>企業</th>
                        <th>期間</th>
                        <th>金額</th>
                        <th>ステータス</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentInvoices as $invoice): ?>
                        <tr>
                            <td><?= $invoice['id'] ?></td>
                            <td class="code"><?= htmlspecialchars($invoice['invoice_number'] ?? '') ?></td>
                            <td><?= htmlspecialchars($invoice['company_name'] ?? '') ?></td>
                            <td><?= $invoice['period_start'] ?? '' ?> ～ <?= $invoice['period_end'] ?? '' ?></td>
                            <td>¥<?= number_format($invoice['total_amount'] ?? 0) ?></td>
                            <td>
                                <span class="badge badge-<?php 
                                    $status = $invoice['status'] ?? '';
                                    echo $status === 'paid' ? 'success' : ($status === 'issued' ? 'warning' : 'danger');
                                ?>">
                                    <?= htmlspecialchars($status) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        
        <h2>💳 最近の支払い記録（payments）</h2>
        <?php if (isset($paymentsError)): ?>
            <div class="error">
                <strong>エラー:</strong> <?= htmlspecialchars($paymentsError) ?>
            </div>
        <?php elseif (empty($recentPayments)): ?>
            <p>支払い記録がありません。PaymentManager機能を使用してください。</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>請求書ID</th>
                        <th>金額</th>
                        <th>支払日</th>
                        <th>支払方法</th>
                        <th>ステータス</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentPayments as $payment): ?>
                        <tr>
                            <td><?= $payment['id'] ?></td>
                            <td><?= $payment['invoice_id'] ?></td>
                            <td>¥<?= number_format($payment['amount'] ?? 0) ?></td>
                            <td><?= $payment['payment_date'] ?? '' ?></td>
                            <td><?= htmlspecialchars($payment['payment_method'] ?? '') ?></td>
                            <td>
                                <span class="badge badge-<?php 
                                    $status = $payment['status'] ?? '';
                                    echo $status === 'completed' ? 'success' : ($status === 'pending' ? 'warning' : 'danger');
                                ?>">
                                    <?= htmlspecialchars($status) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        
        <h2>🔧 診断結果</h2>
        <div style="background: #e3f2fd; padding: 20px; border-radius: 8px; margin: 20px 0;">
            <h3 style="margin-top: 0;">システム状態</h3>
            <ul>
                <li><strong>注文データ (orders):</strong> 
                    <?php if ($dataStatus['orders']['count'] > 0): ?>
                        <span class="badge badge-success">✅ <?= $dataStatus['orders']['count'] ?>件</span>
                    <?php else: ?>
                        <span class="badge badge-danger">❌ データなし</span>
                        → CSVインポートを実行してください
                    <?php endif; ?>
                </li>
                <li><strong>請求書 (invoices):</strong> 
                    <?php if ($dataStatus['invoices']['count'] > 0): ?>
                        <span class="badge badge-success">✅ <?= $dataStatus['invoices']['count'] ?>件</span>
                    <?php else: ?>
                        <span class="badge badge-warning">⚠️ データなし</span>
                        → 請求書生成機能を使用してください
                    <?php endif; ?>
                </li>
                <li><strong>支払い記録 (payments):</strong> 
                    <?php if ($dataStatus['payments']['count'] > 0): ?>
                        <span class="badge badge-success">✅ <?= $dataStatus['payments']['count'] ?>件</span>
                    <?php else: ?>
                        <span class="badge badge-warning">⚠️ データなし</span>
                        → PaymentManager機能を使用してください
                    <?php endif; ?>
                </li>
            </ul>
            
            <?php if ($dataStatus['invoices']['count'] > 0 && $dataStatus['payments']['count'] == 0): ?>
                <div style="background: #fff3cd; padding: 15px; border-radius: 4px; margin-top: 15px;">
                    <strong>⚠️ 注意:</strong> 請求書は存在しますが、支払い記録がありません。<br>
                    これが「未回収金額が反映されていない」原因の可能性があります。<br>
                    <strong>対策:</strong> PaymentManager機能を使用して支払い記録を登録してください。
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
