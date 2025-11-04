<?php
/**
 * 集金管理センター - 個人別・企業別入金管理
 * Smiley配食事業システム
 */

session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/SimpleCollectionManager.php';
require_once __DIR__ . '/../classes/ReceiptManager.php';

// ページ設定
$pageTitle = '集金管理 - Smiley配食事業システム';
$activePage = 'payments';
$basePath = '..';

$message = '';
$messageType = '';

// 入金処理
$collectionManager = new SimpleCollectionManager();
$receiptManager = new ReceiptManager();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_payment'])) {
    $paymentType = $_POST['payment_type']; // 'individual' or 'company'

    if ($paymentType === 'individual') {
        $result = $collectionManager->recordPayment([
            'user_id' => $_POST['user_id'],
            'payment_date' => $_POST['payment_date'],
            'amount' => $_POST['amount'],
            'payment_method' => $_POST['payment_method'],
            'reference_number' => $_POST['reference_number'] ?? '',
            'notes' => $_POST['notes'] ?? '',
            'created_by' => 'admin' // TODO: ログインユーザー
        ]);
    } else {
        $result = $collectionManager->recordCompanyPayment([
            'company_name' => $_POST['company_name'],
            'payment_date' => $_POST['payment_date'],
            'amount' => $_POST['amount'],
            'payment_method' => $_POST['payment_method'],
            'reference_number' => $_POST['reference_number'] ?? '',
            'notes' => $_POST['notes'] ?? '',
            'created_by' => 'admin' // TODO: ログインユーザー
        ]);
    }

    if ($result['success']) {
        $message = $result['message'];
        $messageType = 'success';
    } elseif (isset($result['check_failed']) && $result['check_failed']) {
        // 企業単位の合計チェック失敗
        $message = $result['message'];
        $messageType = 'warning';
    } else {
        $message = 'エラー: ' . $result['error'];
        $messageType = 'danger';
    }
}

// 領収書発行処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['issue_receipt'])) {
    $result = $receiptManager->issueReceipt([
        'payment_id' => $_POST['payment_id'],
        'issue_date' => $_POST['issue_date'] ?? date('Y-m-d'),
        'description' => $_POST['description'] ?? 'お弁当代として',
        'issuer_name' => $_POST['issuer_name'] ?? 'システム管理者',
        'created_by' => 'admin' // TODO: ログインユーザー
    ]);

    if ($result['success']) {
        $message = $result['message'] . '（領収書番号: ' . $result['receipt_number'] . '）';
        $messageType = 'success';
    } else {
        $message = 'エラー: ' . $result['message'];
        $messageType = 'danger';
    }
}

try {
    // 統計データ取得
    $statistics = $collectionManager->getMonthlyCollectionStats();
    $alerts = $collectionManager->getAlerts();

    // 表示タイプ（個人別/企業別）
    $viewType = $_GET['view'] ?? 'individual';

    // 売掛残高を取得
    if ($viewType === 'company') {
        $receivables = $collectionManager->getCompanyReceivables(['limit' => 50]);
    } else {
        $receivables = $collectionManager->getUserReceivables(['limit' => 50]);
    }

    // 入金履歴を取得
    $paymentHistory = $collectionManager->getPaymentHistory(['limit' => 10]);

    // 各入金の領収書発行状態をチェック
    foreach ($paymentHistory as &$payment) {
        $receipt = $receiptManager->getReceiptByPaymentId($payment['payment_id']);
        $payment['receipt'] = $receipt;
    }
    unset($payment);

} catch (Exception $e) {
    error_log("集金管理画面エラー: " . $e->getMessage());
    $error = "データの取得に失敗しました: " . $e->getMessage();
    $statistics = ['collected_amount' => 0, 'outstanding_amount' => 0, 'total_orders' => 0];
    $alerts = ['alert_count' => 0, 'overdue' => ['count' => 0], 'due_soon' => ['count' => 0]];
    $receivables = [];
    $paymentHistory = [];
}

// ヘッダー読み込み
require_once __DIR__ . '/../includes/header.php';
?>

<style>
    .view-toggle {
        margin-bottom: 2rem;
    }
    .view-toggle .btn {
        margin-right: 10px;
    }
    .receivables-table {
        background: white;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 30px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .receivables-table table {
        width: 100%;
        border-collapse: collapse;
    }
    .receivables-table th,
    .receivables-table td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid #dee2e6;
    }
    .receivables-table th {
        background: #f8f9fa;
        font-weight: 600;
    }
    .receivables-table tr:hover {
        background: #f8f9fa;
    }
    .amount-cell {
        text-align: right;
        font-weight: 600;
    }
    .payment-modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        z-index: 1000;
        overflow-y: auto;
    }
    .payment-modal.active {
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .payment-modal-content {
        background: white;
        padding: 30px;
        border-radius: 8px;
        max-width: 600px;
        width: 90%;
        max-height: 90vh;
        overflow-y: auto;
    }
    .form-group {
        margin-bottom: 20px;
    }
    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
    }
    .form-group input,
    .form-group select,
    .form-group textarea {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 4px;
    }
    .payment-history {
        background: white;
        border-radius: 8px;
        padding: 20px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
</style>

<!-- メインコンテンツ -->
<div class="row mb-4">
    <div class="col-12">
        <h2 class="h4 mb-3">
            <span class="material-icons" style="vertical-align: middle; font-size: 2rem;">payment</span>
            集金管理センター
        </h2>
        <p class="text-white-50">個人別・企業別の入金管理と残売掛確認</p>
    </div>
</div>

<!-- メッセージ表示 -->
<?php if ($message): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- デバッグ情報 -->
<?php if (defined('DEBUG_MODE') && DEBUG_MODE): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="alert alert-info">
            <h6><strong>デバッグ情報</strong></h6>
            <small>
                - 総注文数: <?php echo $statistics['total_orders'] ?? 0; ?><br>
                - 総金額: <?php echo number_format($statistics['total_amount'] ?? 0); ?>円<br>
                - 入金済み: <?php echo number_format($statistics['collected_amount'] ?? 0); ?>円<br>
                - 未回収: <?php echo number_format($statistics['outstanding_amount'] ?? 0); ?>円<br>
                - 売掛残高件数: <?php echo count($receivables); ?>件<br>
            </small>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- データがない場合の案内 -->
<?php if (($statistics['total_orders'] ?? 0) === 0): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="alert alert-warning">
            <h5><span class="material-icons" style="vertical-align: middle;">info</span> データがまだ登録されていません</h5>
            <p class="mb-2">集金管理を開始するには、まず注文データを登録してください。</p>
            <a href="<?php echo $basePath; ?>/pages/csv_import.php" class="btn btn-warning">
                <span class="material-icons" style="vertical-align: middle; font-size: 1.2rem;">upload_file</span>
                データ取込ページへ
            </a>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- 統計カード -->
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="stat-card success">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-value"><?php echo number_format($statistics['collected_amount'] ?? 0); ?></div>
                    <div class="stat-label">今月入金額 (円)</div>
                </div>
                <span class="material-icons stat-icon" style="color: var(--success-green);">payments</span>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="stat-card warning">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-value"><?php echo number_format($statistics['outstanding_amount'] ?? 0); ?></div>
                    <div class="stat-label">未回収金額 (円)</div>
                </div>
                <span class="material-icons stat-icon" style="color: var(--warning-amber);">account_balance_wallet</span>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="stat-card error">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-value"><?php echo $alerts['overdue']['count'] ?? 0; ?></div>
                    <div class="stat-label">期限切れ件数</div>
                </div>
                <span class="material-icons stat-icon" style="color: var(--error-red);">error</span>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="stat-card info">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-value"><?php echo $alerts['due_soon']['count'] ?? 0; ?></div>
                    <div class="stat-label">要対応件数（14-30日）</div>
                </div>
                <span class="material-icons stat-icon" style="color: var(--info-blue);">schedule</span>
            </div>
        </div>
    </div>
</div>

<!-- 表示切替 -->
<div class="view-toggle">
    <a href="?view=individual" class="btn btn-material <?php echo $viewType === 'individual' ? 'btn-primary' : 'btn-secondary'; ?>">
        <span class="material-icons" style="font-size: 1rem; vertical-align: middle;">person</span>
        個人別
    </a>
    <a href="?view=company" class="btn btn-material <?php echo $viewType === 'company' ? 'btn-primary' : 'btn-secondary'; ?>">
        <span class="material-icons" style="font-size: 1rem; vertical-align: middle;">business</span>
        企業別
    </a>
</div>

<!-- 売掛残高一覧 -->
<div class="receivables-table">
    <h3 class="mb-4">
        <span class="material-icons" style="vertical-align: middle; color: #FF9800;">notifications_active</span>
        <?php echo $viewType === 'company' ? '企業別' : '個人別'; ?>売掛残高
    </h3>

    <?php if (!empty($receivables)): ?>
    <table>
        <thead>
            <tr>
                <?php if ($viewType === 'individual'): ?>
                    <th>利用者名</th>
                    <th>企業名</th>
                <?php else: ?>
                    <th>企業名</th>
                    <th>利用者数</th>
                <?php endif; ?>
                <th>注文件数</th>
                <th class="amount-cell">注文合計</th>
                <th class="amount-cell">入金済み</th>
                <th class="amount-cell">未回収</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($receivables as $item): ?>
            <tr>
                <?php if ($viewType === 'individual'): ?>
                    <td><?php echo htmlspecialchars($item['user_name']); ?></td>
                    <td><?php echo htmlspecialchars($item['company_name'] ?? '-'); ?></td>
                <?php else: ?>
                    <td><?php echo htmlspecialchars($item['company_name']); ?></td>
                    <td><?php echo $item['user_count']; ?>名</td>
                <?php endif; ?>
                <td><?php echo $item['total_orders']; ?>件</td>
                <td class="amount-cell">¥<?php echo number_format($item['total_ordered']); ?></td>
                <td class="amount-cell">¥<?php echo number_format($item['total_paid']); ?></td>
                <td class="amount-cell"><strong>¥<?php echo number_format($item['outstanding_amount']); ?></strong></td>
                <td>
                    <button class="btn btn-material btn-sm btn-success"
                            onclick='openPaymentModal("<?php echo $viewType; ?>", <?php echo htmlspecialchars(json_encode($item), ENT_QUOTES, 'UTF-8'); ?>)'>
                        <span class="material-icons" style="font-size: 1rem; vertical-align: middle;">add_card</span>
                        入金
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <p class="text-center py-4">未回収の売掛金はありません</p>
    <?php endif; ?>
</div>

<!-- 入金履歴 -->
<div class="payment-history" id="history">
    <h3 class="mb-4">
        <span class="material-icons" style="vertical-align: middle; color: #4CAF50;">history</span>
        最近の入金履歴
    </h3>

    <?php if (!empty($paymentHistory)): ?>
    <table>
        <thead>
            <tr>
                <th>入金日</th>
                <th>タイプ</th>
                <th>利用者/企業</th>
                <th class="amount-cell">入金額</th>
                <th>支払方法</th>
                <th>注文数</th>
                <th>領収書</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($paymentHistory as $payment): ?>
            <tr>
                <td><?php echo htmlspecialchars($payment['payment_date']); ?></td>
                <td>
                    <span class="badge bg-<?php echo $payment['payment_type'] === 'individual' ? 'primary' : 'info'; ?>">
                        <?php echo $payment['payment_type'] === 'individual' ? '個人' : '企業'; ?>
                    </span>
                </td>
                <td>
                    <?php if ($payment['payment_type'] === 'individual'): ?>
                        <?php echo htmlspecialchars($payment['user_name']); ?>
                        <?php if ($payment['company_name']): ?>
                            <small>(<?php echo htmlspecialchars($payment['company_name']); ?>)</small>
                        <?php endif; ?>
                    <?php else: ?>
                        <?php echo htmlspecialchars($payment['company_name']); ?>
                    <?php endif; ?>
                </td>
                <td class="amount-cell">¥<?php echo number_format($payment['amount']); ?></td>
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
                <td><?php echo $payment['order_count']; ?>件</td>
                <td>
                    <?php if ($payment['receipt']): ?>
                        <a href="receipt.php?id=<?php echo $payment['receipt']['id']; ?>" class="btn btn-material btn-sm btn-info" target="_blank">
                            <span class="material-icons" style="font-size: 1rem; vertical-align: middle;">receipt</span>
                            表示
                        </a>
                    <?php else: ?>
                        <button class="btn btn-material btn-sm btn-success"
                                onclick='openReceiptModal(<?php echo htmlspecialchars(json_encode($payment), ENT_QUOTES, 'UTF-8'); ?>)'>
                            <span class="material-icons" style="font-size: 1rem; vertical-align: middle;">receipt_long</span>
                            発行
                        </button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <p class="text-center py-4">入金履歴がありません</p>
    <?php endif; ?>
</div>

<!-- 入金登録モーダル -->
<div id="paymentModal" class="payment-modal">
    <div class="payment-modal-content">
        <h3 class="mb-4">入金を記録</h3>
        <form method="POST" id="paymentForm">
            <input type="hidden" name="payment_type" id="payment_type" value="">
            <input type="hidden" name="user_id" id="user_id" value="">
            <input type="hidden" name="company_name" id="company_name" value="">

            <div id="paymentInfo" class="alert alert-info mb-4"></div>

            <div class="form-group">
                <label for="payment_date">入金日 *</label>
                <input type="date" name="payment_date" id="payment_date" required value="<?php echo date('Y-m-d'); ?>">
            </div>

            <div class="form-group">
                <label for="amount">入金額 *</label>
                <input type="number" name="amount" id="amount" required min="1" step="0.01">
            </div>

            <div class="form-group">
                <label for="payment_method">支払方法 *</label>
                <select name="payment_method" id="payment_method" required>
                    <option value="cash">現金</option>
                    <option value="bank_transfer">銀行振込</option>
                    <option value="account_debit">口座引き落とし</option>
                    <option value="other">その他</option>
                </select>
            </div>

            <div class="form-group">
                <label for="reference_number">参照番号（振込番号等）</label>
                <input type="text" name="reference_number" id="reference_number">
            </div>

            <div class="form-group">
                <label for="notes">備考</label>
                <textarea name="notes" id="notes" rows="3"></textarea>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" name="record_payment" class="btn btn-material btn-success flex-grow-1">
                    <span class="material-icons" style="font-size: 1rem; vertical-align: middle;">check_circle</span>
                    入金を記録
                </button>
                <button type="button" class="btn btn-material btn-secondary" onclick="closePaymentModal()">
                    キャンセル
                </button>
            </div>
        </form>
    </div>
</div>

<!-- 領収書発行モーダル -->
<div id="receiptModal" class="payment-modal">
    <div class="payment-modal-content">
        <h3 class="mb-4">領収書を発行</h3>
        <form method="POST" id="receiptForm">
            <input type="hidden" name="payment_id" id="receipt_payment_id" value="">

            <div id="receiptInfo" class="alert alert-info mb-4"></div>

            <div class="form-group">
                <label for="issue_date">発行日 *</label>
                <input type="date" name="issue_date" id="issue_date" required value="<?php echo date('Y-m-d'); ?>">
            </div>

            <div class="form-group">
                <label for="description">但し書き *</label>
                <input type="text" name="description" id="description" required value="お弁当代として">
            </div>

            <div class="form-group">
                <label for="issuer_name">発行者名 *</label>
                <input type="text" name="issuer_name" id="issuer_name" required value="システム管理者">
            </div>

            <div class="d-flex gap-2">
                <button type="submit" name="issue_receipt" class="btn btn-material btn-success flex-grow-1">
                    <span class="material-icons" style="font-size: 1rem; vertical-align: middle;">receipt_long</span>
                    領収書を発行
                </button>
                <button type="button" class="btn btn-material btn-secondary" onclick="closeReceiptModal()">
                    キャンセル
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openPaymentModal(type, data) {
    const modal = document.getElementById('paymentModal');
    const form = document.getElementById('paymentForm');
    const paymentInfo = document.getElementById('paymentInfo');

    document.getElementById('payment_type').value = type;

    if (type === 'individual') {
        document.getElementById('user_id').value = data.user_id;
        document.getElementById('company_name').value = '';
        paymentInfo.innerHTML = `
            <strong>個人別入金</strong><br>
            利用者: ${data.user_name}<br>
            未回収: ¥${parseInt(data.outstanding_amount).toLocaleString()}
        `;
        document.getElementById('amount').max = data.outstanding_amount;
        document.getElementById('payment_method').value = 'cash';
    } else {
        document.getElementById('user_id').value = '';
        document.getElementById('company_name').value = data.company_name;
        paymentInfo.innerHTML = `
            <strong>企業別入金</strong><br>
            企業: ${data.company_name}<br>
            利用者数: ${data.user_count}名<br>
            未回収合計: ¥${parseInt(data.outstanding_amount).toLocaleString()}<br>
            <span class="text-warning">※ 入金額が未回収合計と一致する必要があります</span>
        `;
        document.getElementById('amount').value = data.outstanding_amount;
        document.getElementById('payment_method').value = 'bank_transfer';
    }

    modal.classList.add('active');
}

function closePaymentModal() {
    document.getElementById('paymentModal').classList.remove('active');
    document.getElementById('paymentForm').reset();
}

// モーダル外クリックで閉じる
document.getElementById('paymentModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closePaymentModal();
    }
});

// 領収書発行モーダルを開く
function openReceiptModal(payment) {
    const modal = document.getElementById('receiptModal');
    const receiptInfo = document.getElementById('receiptInfo');

    document.getElementById('receipt_payment_id').value = payment.payment_id;

    let name = payment.payment_type === 'company' ? payment.company_name : payment.user_name;
    receiptInfo.innerHTML = `
        <strong>領収書発行</strong><br>
        入金日: ${payment.payment_date}<br>
        ${payment.payment_type === 'individual' ? '利用者' : '企業'}: ${name}<br>
        入金額: ¥${parseInt(payment.amount).toLocaleString()}
    `;

    modal.classList.add('active');
}

function closeReceiptModal() {
    document.getElementById('receiptModal').classList.remove('active');
    document.getElementById('receiptForm').reset();
}

// 領収書モーダル外クリックで閉じる
document.getElementById('receiptModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeReceiptModal();
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
