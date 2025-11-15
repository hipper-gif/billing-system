<?php
/**
 * 領収書表示・印刷ページ
 * Smiley配食事業システム
 */

session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/ReceiptManager.php';

// 領収書IDを取得
$receiptId = $_GET['id'] ?? null;

if (!$receiptId) {
    die('領収書IDが指定されていません');
}

// 領収書データを取得
$receiptManager = new ReceiptManager();
$receipt = $receiptManager->getReceipt($receiptId);

if (!$receipt) {
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
    <title>領収書 - <?php echo htmlspecialchars($receipt['receipt_number']); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'MS Mincho', 'Yu Mincho', serif;
            padding: 20px;
            background-color: #f5f5f5;
        }

        .receipt-container {
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            padding: 30mm 20mm;
            background: white;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
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

        .print-buttons {
            text-align: center;
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

        @media print {
            body {
                background-color: white;
                padding: 0;
            }

            .receipt-container {
                width: 100%;
                box-shadow: none;
                margin: 0;
                padding: 15mm 10mm;
            }

            .print-buttons {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="print-buttons">
        <button onclick="window.print()" class="btn btn-print">印刷</button>
        <button onclick="window.close()" class="btn btn-close">閉じる</button>
    </div>

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

    <div class="print-buttons">
        <button onclick="window.print()" class="btn btn-print">印刷</button>
        <button onclick="window.close()" class="btn btn-close">閉じる</button>
    </div>
</body>
</html>
