<?php
/**
 * 入金登録画面
 * 請求書に対する入金を登録する
 */

session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/PaymentManager.php';
require_once __DIR__ . '/../classes/InvoiceManager.php';

$pageTitle = '入金登録';
$message = '';
$messageType = '';

// PaymentManager と InvoiceManager のインスタンス作成
$paymentManager = new PaymentManager();
$invoiceManager = new InvoiceManager();

// 入金処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_payment'])) {
    $result = $paymentManager->registerPayment([
        'invoice_id' => $_POST['invoice_id'],
        'payment_date' => $_POST['payment_date'],
        'amount' => $_POST['amount'],
        'payment_method' => $_POST['payment_method'],
        'reference_number' => $_POST['reference_number'] ?? '',
        'notes' => $_POST['notes'] ?? '',
        'created_by' => 'admin' // TODO: ログインユーザー名を取得
    ]);

    if ($result['success']) {
        $message = $result['message'];
        $messageType = 'success';
    } else {
        $message = 'エラー: ' . $result['error'];
        $messageType = 'error';
    }
}

// フィルタ条件から未払い請求書を取得
$userId = $_GET['user_id'] ?? null;
$companyName = $_GET['company_name'] ?? null;

$invoiceFilters = ['status' => 'issued'];
if ($userId) {
    // 特定ユーザーの請求書のみ
    $invoiceFilters['user_id'] = $userId;
}
if ($companyName) {
    $invoiceFilters['company_name'] = $companyName;
}

// 未払い・一部入金の請求書を取得
$unpaidInvoices = $invoiceManager->getInvoices($invoiceFilters);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - 集金管理システム</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
        .payment-form {
            max-width: 600px;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        .form-group select,
        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            min-height: 80px;
        }
        .btn-primary {
            background: #3498db;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
        }
        .btn-primary:hover {
            background: #2980b9;
        }
        .message {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .invoice-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            margin-top: 10px;
            display: none;
        }
        .invoice-info.show {
            display: block;
        }
        .invoice-history {
            margin-top: 30px;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .history-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .history-table th,
        .history-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }
        .history-table th {
            background: #f8f9fa;
            font-weight: 600;
        }
    </style>
    <script>
        function updateInvoiceInfo() {
            const select = document.getElementById('invoice_id');
            const selectedOption = select.options[select.selectedIndex];

            if (selectedOption.value) {
                const invoiceInfo = document.getElementById('invoice-info');
                const totalAmount = selectedOption.dataset.total;
                const paidAmount = selectedOption.dataset.paid || '0';
                const outstanding = selectedOption.dataset.outstanding;

                document.getElementById('info-total').textContent = '¥' + parseInt(totalAmount).toLocaleString();
                document.getElementById('info-paid').textContent = '¥' + parseInt(paidAmount).toLocaleString();
                document.getElementById('info-outstanding').textContent = '¥' + parseInt(outstanding).toLocaleString();

                // 入金額のmax値を設定
                document.getElementById('amount').max = outstanding;

                invoiceInfo.classList.add('show');
            } else {
                document.getElementById('invoice-info').classList.remove('show');
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            // 日付のデフォルトを今日に設定
            const dateInput = document.getElementById('payment_date');
            const today = new Date().toISOString().split('T')[0];
            dateInput.value = today;

            // 請求書選択時に情報を更新
            document.getElementById('invoice_id').addEventListener('change', updateInvoiceInfo);
        });
    </script>
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>

    <div class="container">
        <h1><?php echo htmlspecialchars($pageTitle); ?></h1>

        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="payment-form">
            <form method="POST" action="">
                <div class="form-group">
                    <label for="invoice_id">請求書を選択 *</label>
                    <select id="invoice_id" name="invoice_id" required>
                        <option value="">-- 請求書を選択してください --</option>
                        <?php foreach ($unpaidInvoices as $invoice): ?>
                            <option
                                value="<?php echo $invoice['id']; ?>"
                                data-total="<?php echo $invoice['total_amount']; ?>"
                                data-paid="<?php echo $invoice['paid_amount']; ?>"
                                data-outstanding="<?php echo $invoice['outstanding_amount']; ?>"
                            >
                                <?php echo htmlspecialchars($invoice['invoice_number']); ?> -
                                <?php echo htmlspecialchars($invoice['user_name']); ?>
                                <?php if ($invoice['company_name']): ?>
                                    (<?php echo htmlspecialchars($invoice['company_name']); ?>)
                                <?php endif; ?>
                                - ¥<?php echo number_format($invoice['outstanding_amount']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="invoice-info" class="invoice-info">
                    <p><strong>請求書情報</strong></p>
                    <p>請求額: <span id="info-total">¥0</span></p>
                    <p>入金済み: <span id="info-paid">¥0</span></p>
                    <p>未回収: <span id="info-outstanding">¥0</span></p>
                </div>

                <div class="form-group">
                    <label for="payment_date">入金日 *</label>
                    <input type="date" id="payment_date" name="payment_date" required>
                </div>

                <div class="form-group">
                    <label for="amount">入金額 *</label>
                    <input type="number" id="amount" name="amount" required min="1" step="0.01">
                </div>

                <div class="form-group">
                    <label for="payment_method">支払方法 *</label>
                    <select id="payment_method" name="payment_method" required>
                        <option value="cash">現金</option>
                        <option value="bank_transfer">銀行振込</option>
                        <option value="account_debit">口座引き落とし</option>
                        <option value="other">その他</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="reference_number">参照番号（振込番号等）</label>
                    <input type="text" id="reference_number" name="reference_number" placeholder="任意">
                </div>

                <div class="form-group">
                    <label for="notes">備考</label>
                    <textarea id="notes" name="notes" placeholder="備考があれば入力してください"></textarea>
                </div>

                <button type="submit" name="register_payment" class="btn-primary">
                    入金を登録
                </button>
            </form>
        </div>

        <!-- 入金履歴（最近の10件） -->
        <?php
        $recentPayments = $paymentManager->getPayments(['limit' => 10]);
        if (!empty($recentPayments)):
        ?>
        <div class="invoice-history">
            <h2>最近の入金履歴</h2>
            <table class="history-table">
                <thead>
                    <tr>
                        <th>入金日</th>
                        <th>請求書番号</th>
                        <th>利用者・企業</th>
                        <th>入金額</th>
                        <th>支払方法</th>
                        <th>参照番号</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentPayments as $payment): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($payment['payment_date']); ?></td>
                            <td><?php echo htmlspecialchars($payment['invoice_number']); ?></td>
                            <td>
                                <?php echo htmlspecialchars($payment['user_name']); ?>
                                <?php if ($payment['company_name']): ?>
                                    <br><small>(<?php echo htmlspecialchars($payment['company_name']); ?>)</small>
                                <?php endif; ?>
                            </td>
                            <td>¥<?php echo number_format($payment['amount']); ?></td>
                            <td>
                                <?php
                                $methods = [
                                    'cash' => '現金',
                                    'bank_transfer' => '銀行振込',
                                    'account_debit' => '口座引き落とし',
                                    'other' => 'その他'
                                ];
                                echo $methods[$payment['payment_method']] ?? $payment['payment_method'];
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars($payment['reference_number'] ?? '-'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
