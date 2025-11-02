<?php
/**
 * 売掛金管理画面
 * 個人別・企業別の売掛金残高を表示し、入金処理を行う
 */

session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/PaymentManager.php';
require_once __DIR__ . '/../classes/InvoiceManager.php';

$pageTitle = '売掛金管理';

// 表示タイプ（個人別/企業別）
$groupBy = $_GET['group_by'] ?? 'individual';

// PaymentManagerのインスタンス作成
$paymentManager = new PaymentManager();

// 売掛金サマリーを取得
$summary = $paymentManager->getReceivablesSummary();

// 売掛金残高一覧を取得
$receivables = $paymentManager->getReceivables($groupBy, [
    'limit' => 50
]);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - 集金管理システム</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        .summary-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .summary-card h3 {
            font-size: 14px;
            color: #666;
            margin: 0 0 10px 0;
        }
        .summary-card .amount {
            font-size: 24px;
            font-weight: bold;
            color: #333;
        }
        .summary-card.outstanding .amount {
            color: #e74c3c;
        }
        .summary-card.paid .amount {
            color: #27ae60;
        }
        .summary-card.overdue .amount {
            color: #c0392b;
        }
        .view-toggle {
            margin-bottom: 20px;
        }
        .view-toggle a {
            display: inline-block;
            padding: 10px 20px;
            margin-right: 10px;
            background: #f0f0f0;
            text-decoration: none;
            color: #333;
            border-radius: 4px;
        }
        .view-toggle a.active {
            background: #3498db;
            color: white;
        }
        .receivables-table {
            width: 100%;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .receivables-table table {
            width: 100%;
            border-collapse: collapse;
        }
        .receivables-table th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #555;
            border-bottom: 2px solid #dee2e6;
        }
        .receivables-table td {
            padding: 12px;
            border-bottom: 1px solid #dee2e6;
        }
        .receivables-table tr:hover {
            background: #f8f9fa;
        }
        .amount-cell {
            text-align: right;
            font-weight: 600;
        }
        .overdue-badge {
            display: inline-block;
            padding: 3px 8px;
            background: #e74c3c;
            color: white;
            border-radius: 3px;
            font-size: 12px;
        }
        .action-btn {
            padding: 6px 12px;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 14px;
        }
        .action-btn:hover {
            background: #2980b9;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>

    <div class="container">
        <h1><?php echo htmlspecialchars($pageTitle); ?></h1>

        <!-- サマリーカード -->
        <div class="summary-cards">
            <div class="summary-card outstanding">
                <h3>未回収合計</h3>
                <div class="amount">¥<?php echo number_format($summary['total_outstanding']); ?></div>
                <small><?php echo $summary['total_invoices']; ?> 件の請求書</small>
            </div>
            <div class="summary-card paid">
                <h3>入金済み</h3>
                <div class="amount">¥<?php echo number_format($summary['total_paid']); ?></div>
            </div>
            <div class="summary-card overdue">
                <h3>期限超過</h3>
                <div class="amount">¥<?php echo number_format($summary['overdue_amount']); ?></div>
                <small><?php echo $summary['overdue_count']; ?> 件</small>
            </div>
            <div class="summary-card">
                <h3>請求済み合計</h3>
                <div class="amount">¥<?php echo number_format($summary['total_billed']); ?></div>
            </div>
        </div>

        <!-- 表示切替 -->
        <div class="view-toggle">
            <a href="?group_by=individual" class="<?php echo $groupBy === 'individual' ? 'active' : ''; ?>">
                個人別
            </a>
            <a href="?group_by=company" class="<?php echo $groupBy === 'company' ? 'active' : ''; ?>">
                企業別
            </a>
        </div>

        <!-- 売掛金一覧 -->
        <div class="receivables-table">
            <table>
                <thead>
                    <tr>
                        <?php if ($groupBy === 'individual'): ?>
                            <th>利用者名</th>
                            <th>企業名</th>
                        <?php else: ?>
                            <th>企業名</th>
                        <?php endif; ?>
                        <th>請求書数</th>
                        <th class="amount-cell">請求額</th>
                        <th class="amount-cell">入金済み</th>
                        <th class="amount-cell">未回収</th>
                        <th>期限</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($receivables)): ?>
                        <tr>
                            <td colspan="<?php echo $groupBy === 'individual' ? '8' : '7'; ?>" style="text-align: center; padding: 30px;">
                                未回収の売掛金はありません
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($receivables as $item): ?>
                            <tr>
                                <?php if ($groupBy === 'individual'): ?>
                                    <td><?php echo htmlspecialchars($item['user_name']); ?></td>
                                    <td><?php echo htmlspecialchars($item['company_name'] ?? '-'); ?></td>
                                <?php else: ?>
                                    <td><?php echo htmlspecialchars($item['company_name']); ?></td>
                                <?php endif; ?>
                                <td><?php echo $item['invoice_count']; ?> 件</td>
                                <td class="amount-cell">¥<?php echo number_format($item['total_billed']); ?></td>
                                <td class="amount-cell">¥<?php echo number_format($item['total_paid']); ?></td>
                                <td class="amount-cell">¥<?php echo number_format($item['total_outstanding']); ?></td>
                                <td>
                                    <?php if ($item['nearest_due_date']): ?>
                                        <?php
                                        $dueDate = new DateTime($item['nearest_due_date']);
                                        $now = new DateTime();
                                        $isOverdue = $dueDate < $now;
                                        ?>
                                        <?php if ($isOverdue): ?>
                                            <span class="overdue-badge">
                                                <?php echo $dueDate->format('Y/m/d'); ?>
                                            </span>
                                        <?php else: ?>
                                            <?php echo $dueDate->format('Y/m/d'); ?>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="payment_register.php?<?php echo $groupBy === 'individual' ? 'user_id=' . $item['user_id'] : 'company_name=' . urlencode($item['company_name']); ?>" class="action-btn">
                                        入金登録
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
