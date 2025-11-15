<?php
/**
 * 領収書一括印刷ページ
 * Smiley配食事業システム
 */

session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/ReceiptManager.php';

// 入金IDを取得（カンマ区切り）
$paymentIds = $_GET['payment_ids'] ?? '';

if (empty($paymentIds)) {
    die('入金IDが指定されていません');
}

// カンマ区切りの文字列を配列に変換
$paymentIdArray = explode(',', $paymentIds);
$paymentIdArray = array_filter(array_map('intval', $paymentIdArray));

if (empty($paymentIdArray)) {
    die('有効な入金IDが指定されていません');
}

// 領収書データを取得
$receiptManager = new ReceiptManager();
$receipts = [];

foreach ($paymentIdArray as $paymentId) {
    $receipt = $receiptManager->getReceiptByPaymentId($paymentId);
    if ($receipt) {
        $receipts[] = $receipt;
    }
}

if (empty($receipts)) {
    die('領収書が見つかりません');
}

// ロゴファイルの存在確認
$logoPath = __DIR__ . '/../assets/images/logo.png';
$hasLogo = file_exists($logoPath);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>領収書一括印刷 (<?php echo count($receipts); ?>件)</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'MS Mincho', 'Yu Mincho', serif;
            background-color: #f5f5f5;
        }

        .print-header {
            background: white;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .print-buttons {
            margin: 20px 0;
        }

        .btn {
            padding: 12px 30px;
            margin: 0 10px;
            font-size: 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .btn-print {
            background-color: #4CAF50;
            color: white;
        }

        .btn-print:hover {
            background-color: #45a049;
        }

        .btn-close {
            background-color: #666;
            color: white;
        }

        .btn-close:hover {
            background-color: #555;
        }

        .receipt-container {
            width: 210mm;
            min-height: 297mm;
            margin: 20px auto;
            padding: 30mm 20mm;
            background: white;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            page-break-after: always;
        }

        .receipt-container:last-child {
            page-break-after: auto;
        }

        .receipt-header {
            text-align: center;
            margin-bottom: 40px;
            position: relative;
        }

        .company-logo {
            max-width: 150px;
            max-height: 80px;
            margin-bottom: 20px;
        }

        .receipt-title {
            font-size: 32px;
            font-weight: bold;
            margin-bottom: 10px;
            letter-spacing: 10px;
        }

        .receipt-number {
            font-size: 14px;
            color: #666;
            text-align: right;
            margin-top: 10px;
        }

        .recipient {
            font-size: 20px;
            margin-bottom: 40px;
            border-bottom: 1px solid #000;
            padding-bottom: 10px;
        }

        .recipient-name {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .amount-section {
            margin: 40px 0;
            padding: 30px;
            border: 2px solid #000;
            text-align: center;
        }

        .amount-label {
            font-size: 16px;
            margin-bottom: 20px;
        }

        .amount-value {
            font-size: 32px;
            font-weight: bold;
        }

        .details-section {
            margin: 30px 0;
        }

        .detail-row {
            display: flex;
            padding: 10px 0;
            border-bottom: 1px dotted #ccc;
        }

        .detail-label {
            width: 150px;
            font-weight: bold;
        }

        .detail-value {
            flex: 1;
        }

        .description-section {
            margin: 30px 0;
            padding: 20px;
            background-color: #f9f9f9;
            border-left: 4px solid #4CAF50;
        }

        .issuer-section {
            margin-top: 60px;
            text-align: right;
        }

        .issuer-info {
            display: inline-block;
            text-align: left;
            border-top: 2px solid #000;
            padding-top: 15px;
        }

        .issue-date {
            font-size: 13px;
            color: #666;
            margin-bottom: 15px;
        }

        .issuer-name {
            font-size: 18px;
            font-weight: bold;
        }

        @media print {
            body {
                background-color: white;
            }

            .print-header {
                display: none;
            }

            .print-buttons {
                display: none;
            }

            .receipt-container {
                width: 100%;
                box-shadow: none;
                margin: 0;
                padding: 15mm 10mm;
                page-break-after: always;
            }

            .receipt-container:last-child {
                page-break-after: auto;
            }
        }

        @page {
            size: A4;
            margin: 0;
        }
    </style>
</head>
<body>
    <div class="print-header">
        <h2>領収書一括印刷 (<?php echo count($receipts); ?>件)</h2>
        <div class="print-buttons">
            <button onclick="window.print()" class="btn btn-print">一括印刷</button>
            <button onclick="window.close()" class="btn btn-close">閉じる</button>
        </div>
    </div>

    <?php foreach ($receipts as $index => $receipt): ?>
    <div class="receipt-container">
        <div class="receipt-header">
            <?php if ($hasLogo): ?>
                <img src="../assets/images/logo.png" alt="会社ロゴ" class="company-logo">
            <?php endif; ?>
            <div class="receipt-title">領　収　書</div>
            <div class="receipt-number">No. <?php echo htmlspecialchars($receipt['receipt_number']); ?></div>
        </div>

        <div class="recipient">
            <div class="recipient-name">
                <?php echo htmlspecialchars($receipt['recipient_name']); ?>
            </div>
        </div>

        <div class="amount-section">
            <div class="amount-label">金　額</div>
            <div class="amount-value">
                ¥ <?php echo number_format($receipt['amount']); ?> －
            </div>
        </div>

        <div class="description-section">
            <strong>但し書き：</strong>
            <?php echo htmlspecialchars($receipt['description']); ?>
        </div>

        <div class="details-section">
            <div class="detail-row">
                <div class="detail-label">入金日</div>
                <div class="detail-value"><?php echo htmlspecialchars($receipt['payment_date']); ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">支払方法</div>
                <div class="detail-value"><?php echo htmlspecialchars($receipt['payment_method_display']); ?></div>
            </div>
            <?php if ($receipt['user_code']): ?>
            <div class="detail-row">
                <div class="detail-label">利用者コード</div>
                <div class="detail-value"><?php echo htmlspecialchars($receipt['user_code']); ?></div>
            </div>
            <?php endif; ?>
        </div>

        <div class="issuer-section">
            <div class="issuer-info">
                <div class="issue-date">
                    発行日: <?php echo htmlspecialchars($receipt['issue_date']); ?>
                </div>
                <div class="issuer-name">
                    <?php echo htmlspecialchars($receipt['issuer_name']); ?>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <script>
        // ページ読み込み完了後に自動的に印刷ダイアログを表示（オプション）
        // window.addEventListener('load', function() {
        //     setTimeout(function() {
        //         window.print();
        //     }, 500);
        // });
    </script>
</body>
</html>
