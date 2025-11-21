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

// 入金編集処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_payment'])) {
    $result = $collectionManager->updatePayment([
        'payment_id' => $_POST['payment_id'],
        'payment_date' => $_POST['payment_date'],
        'amount' => $_POST['amount'],
        'payment_method' => $_POST['payment_method'],
        'reference_number' => $_POST['reference_number'] ?? '',
        'notes' => $_POST['notes'] ?? ''
    ]);

    if ($result['success']) {
        $message = $result['message'];
        $messageType = 'success';
    } else {
        $message = 'エラー: ' . $result['error'];
        $messageType = 'danger';
    }
}

// 入金削除処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_payment'])) {
    $result = $collectionManager->deletePayment($_POST['payment_id']);

    if ($result['success']) {
        $message = $result['message'];
        $messageType = 'success';
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
        'issuer_name' => $_POST['issuer_name'] ?? '株式会社Smiley',
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

// 一括領収書発行処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_issue_receipts'])) {
    $paymentIds = json_decode($_POST['payment_ids'] ?? '[]', true);

    if (empty($paymentIds)) {
        $message = 'エラー: 入金記録が選択されていません';
        $messageType = 'danger';
    } else {
        $result = $receiptManager->bulkIssueReceipts($paymentIds, [
            'issue_date' => $_POST['bulk_issue_date'] ?? date('Y-m-d'),
            'description' => $_POST['bulk_description'] ?? 'お弁当代として',
            'issuer_name' => $_POST['bulk_issuer_name'] ?? '株式会社Smiley',
            'created_by' => 'admin' // TODO: ログインユーザー
        ]);

        if ($result['success'] || $result['issued'] > 0) {
            $message = "領収書を{$result['issued']}件発行しました。";
            if ($result['skipped'] > 0) {
                $message .= "（{$result['skipped']}件はスキップ）";
            }
            if ($result['failed'] > 0) {
                $message .= "（{$result['failed']}件は失敗）";
            }
            $messageType = 'success';
        } else {
            $message = 'エラー: 領収書の発行に失敗しました';
            $messageType = 'danger';
        }
    }
}

try {
    // 統計データ取得
    $statistics = $collectionManager->getMonthlyCollectionStats();
    $alerts = $collectionManager->getAlerts();

    // 表示タイプ（個人別/企業別）
    $viewType = $_GET['view'] ?? 'individual';

    // 検索パラメータ
    $searchQuery = $_GET['search'] ?? '';
    $sortBy = $_GET['sort'] ?? 'outstanding_desc';

    // 売掛残高を取得
    if ($viewType === 'company') {
        $receivables = $collectionManager->getCompanyReceivables(['limit' => 100]);
    } else {
        $receivables = $collectionManager->getUserReceivables(['limit' => 100]);
    }

    // 検索フィルタ
    if (!empty($searchQuery)) {
        $receivables = array_filter($receivables, function($item) use ($searchQuery, $viewType) {
            if ($viewType === 'company') {
                return stripos($item['company_name'], $searchQuery) !== false;
            } else {
                return stripos($item['user_name'], $searchQuery) !== false ||
                       stripos($item['user_code'], $searchQuery) !== false ||
                       stripos($item['company_name'], $searchQuery) !== false;
            }
        });
    }

    // ソート
    if (!empty($receivables)) {
        usort($receivables, function($a, $b) use ($sortBy) {
            switch ($sortBy) {
                case 'outstanding_asc':
                    return $a['outstanding_amount'] <=> $b['outstanding_amount'];
                case 'outstanding_desc':
                    return $b['outstanding_amount'] <=> $a['outstanding_amount'];
                case 'name_asc':
                    $nameA = $a['user_name'] ?? $a['company_name'];
                    $nameB = $b['user_name'] ?? $b['company_name'];
                    return strcmp($nameA, $nameB);
                case 'name_desc':
                    $nameA = $a['user_name'] ?? $a['company_name'];
                    $nameB = $b['user_name'] ?? $b['company_name'];
                    return strcmp($nameB, $nameA);
                case 'orders_desc':
                    return $b['total_orders'] <=> $a['total_orders'];
                default:
                    return $b['outstanding_amount'] <=> $a['outstanding_amount'];
            }
        });
    }

    // 入金履歴を取得
    $paymentHistory = $collectionManager->getPaymentHistory(['limit' => 20]);

    // 各入金の領収書発行状態をチェック
    foreach ($paymentHistory as &$payment) {
        $receipt = $receiptManager->getReceiptByPaymentId($payment['id']);
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

<!-- 検索・ソート -->
<div class="search-sort-controls" style="background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
    <form method="GET" style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
        <input type="hidden" name="view" value="<?php echo htmlspecialchars($viewType); ?>">

        <div style="flex: 1; min-width: 200px;">
            <input type="text"
                   name="search"
                   placeholder="<?php echo $viewType === 'company' ? '企業名で検索' : '利用者名・利用者コード・企業名で検索'; ?>"
                   value="<?php echo htmlspecialchars($searchQuery); ?>"
                   style="width: 100%; padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px;">
        </div>

        <div style="min-width: 180px;">
            <select name="sort"
                    style="width: 100%; padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px;"
                    onchange="this.form.submit()">
                <option value="outstanding_desc" <?php echo $sortBy === 'outstanding_desc' ? 'selected' : ''; ?>>未回収額（高い順）</option>
                <option value="outstanding_asc" <?php echo $sortBy === 'outstanding_asc' ? 'selected' : ''; ?>>未回収額（低い順）</option>
                <option value="name_asc" <?php echo $sortBy === 'name_asc' ? 'selected' : ''; ?>>名前（昇順）</option>
                <option value="name_desc" <?php echo $sortBy === 'name_desc' ? 'selected' : ''; ?>>名前（降順）</option>
                <option value="orders_desc" <?php echo $sortBy === 'orders_desc' ? 'selected' : ''; ?>>注文件数（多い順）</option>
            </select>
        </div>

        <button type="submit" class="btn btn-material btn-primary">
            <span class="material-icons" style="font-size: 1rem; vertical-align: middle;">search</span>
            検索
        </button>

        <?php if (!empty($searchQuery) || $sortBy !== 'outstanding_desc'): ?>
        <a href="?view=<?php echo $viewType; ?>" class="btn btn-material btn-secondary">
            <span class="material-icons" style="font-size: 1rem; vertical-align: middle;">clear</span>
            クリア
        </a>
        <?php endif; ?>
    </form>
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
                    <button class="btn btn-material btn-sm btn-info"
                            onclick='issuePreReceipt("<?php echo $viewType; ?>", <?php echo htmlspecialchars(json_encode($item), ENT_QUOTES, 'UTF-8'); ?>)'>
                        <span class="material-icons" style="font-size: 1rem; vertical-align: middle;">receipt</span>
                        領収書発行
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
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="mb-0">
            <span class="material-icons" style="vertical-align: middle; color: #4CAF50;">history</span>
            最近の入金履歴
        </h3>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-material btn-success" onclick="openBulkIssueModal()" id="bulkIssueBtn" style="display: none;">
                <span class="material-icons" style="font-size: 1rem; vertical-align: middle;">receipt_long</span>
                選択した入金の領収書を一括発行
            </button>
            <button type="button" class="btn btn-material btn-info" onclick="bulkPrintReceipts()" id="bulkPrintBtn" style="display: none;">
                <span class="material-icons" style="font-size: 1rem; vertical-align: middle;">print</span>
                選択した領収書を一括印刷
            </button>
        </div>
    </div>

    <?php if (!empty($paymentHistory)): ?>
    <table>
        <thead>
            <tr>
                <th style="width: 50px;">
                    <input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)">
                </th>
                <th>入金日</th>
                <th>タイプ</th>
                <th>利用者/企業</th>
                <th class="amount-cell">入金額</th>
                <th>支払方法</th>
                <th>注文数</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($paymentHistory as $payment): ?>
            <tr>
                <td>
                    <input type="checkbox"
                           class="payment-checkbox"
                           value="<?php echo $payment['id']; ?>"
                           data-has-receipt="<?php echo !empty($payment['receipt']) ? '1' : '0'; ?>"
                           onchange="updateBulkButtons()">
                </td>
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
                    <button class="btn btn-material btn-sm btn-primary"
                            onclick='openEditPaymentModal(<?php echo htmlspecialchars(json_encode($payment), ENT_QUOTES, 'UTF-8'); ?>)'
                            title="編集">
                        <span class="material-icons" style="font-size: 1rem; vertical-align: middle;">edit</span>
                    </button>
                    <button class="btn btn-material btn-sm btn-danger"
                            onclick='confirmDeletePayment(<?php echo $payment["id"]; ?>, "<?php echo htmlspecialchars($payment["user_name"] ?? $payment["company_name"]); ?>")'
                            title="削除">
                        <span class="material-icons" style="font-size: 1rem; vertical-align: middle;">delete</span>
                    </button>
                    <?php if (!empty($payment['receipt'])): ?>
                    <button class="btn btn-material btn-sm btn-success"
                            onclick='window.open("receipt.php?id=<?php echo $payment["receipt"]["id"]; ?>", "_blank")'
                            title="領収書を表示">
                        <span class="material-icons" style="font-size: 1rem; vertical-align: middle;">receipt</span>
                    </button>
                    <?php else: ?>
                    <button class="btn btn-material btn-sm btn-info"
                            onclick='openReceiptModal(<?php echo $payment["id"]; ?>, "<?php echo htmlspecialchars($payment["user_name"] ?? $payment["company_name"]); ?>", <?php echo $payment["amount"]; ?>)'
                            title="領収書を発行">
                        <span class="material-icons" style="font-size: 1rem; vertical-align: middle;">receipt_long</span>
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
                <div style="display: flex; gap: 10px; align-items: center;">
                    <input type="number" name="amount" id="amount" required min="1" step="0.01" style="flex: 1;">
                    <button type="button" class="btn btn-material btn-info" onclick="setFullAmount()" id="fullAmountBtn">
                        <span class="material-icons" style="font-size: 1rem; vertical-align: middle;">account_balance_wallet</span>
                        満額入金
                    </button>
                </div>
                <input type="hidden" id="full_amount_value" value="0">
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

<!-- 入金編集モーダル -->
<div id="editPaymentModal" class="payment-modal">
    <div class="payment-modal-content">
        <h3 class="mb-4">入金情報を編集</h3>
        <form method="POST" id="editPaymentForm">
            <input type="hidden" name="payment_id" id="edit_payment_id" value="">

            <div id="editPaymentInfo" class="alert alert-info mb-4"></div>

            <div class="form-group">
                <label for="edit_payment_date">入金日 *</label>
                <input type="date" name="payment_date" id="edit_payment_date" required>
            </div>

            <div class="form-group">
                <label for="edit_amount">入金額 *</label>
                <input type="number" name="amount" id="edit_amount" required min="1" step="0.01">
            </div>

            <div class="form-group">
                <label for="edit_payment_method">支払方法 *</label>
                <select name="payment_method" id="edit_payment_method" required>
                    <option value="cash">現金</option>
                    <option value="bank_transfer">銀行振込</option>
                    <option value="account_debit">口座引き落とし</option>
                    <option value="other">その他</option>
                </select>
            </div>

            <div class="form-group">
                <label for="edit_reference_number">参照番号（振込番号等）</label>
                <input type="text" name="reference_number" id="edit_reference_number">
            </div>

            <div class="form-group">
                <label for="edit_notes">備考</label>
                <textarea name="notes" id="edit_notes" rows="3"></textarea>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" name="update_payment" class="btn btn-material btn-primary flex-grow-1">
                    <span class="material-icons" style="font-size: 1rem; vertical-align: middle;">save</span>
                    更新
                </button>
                <button type="button" class="btn btn-material btn-secondary" onclick="closeEditPaymentModal()">
                    キャンセル
                </button>
            </div>
        </form>
    </div>
</div>

<!-- 入金削除フォーム（非表示） -->
<form method="POST" id="deletePaymentForm" style="display: none;">
    <input type="hidden" name="payment_id" id="delete_payment_id" value="">
    <input type="hidden" name="delete_payment" value="1">
</form>

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
                <input type="text" name="issuer_name" id="issuer_name" required value="株式会社Smiley">
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

<!-- 一括領収書発行モーダル -->
<div id="bulkReceiptModal" class="payment-modal">
    <div class="payment-modal-content">
        <h3 class="mb-4">領収書を一括発行</h3>
        <form method="POST" id="bulkReceiptForm">
            <input type="hidden" name="payment_ids" id="bulk_payment_ids" value="">

            <div id="bulkReceiptInfo" class="alert alert-info mb-4"></div>

            <div class="form-group">
                <label for="bulk_issue_date">発行日 *</label>
                <input type="date" name="bulk_issue_date" id="bulk_issue_date" required value="<?php echo date('Y-m-d'); ?>">
            </div>

            <div class="form-group">
                <label for="bulk_description">但し書き *</label>
                <input type="text" name="bulk_description" id="bulk_description" required value="お弁当代として">
            </div>

            <div class="form-group">
                <label for="bulk_issuer_name">発行者名 *</label>
                <input type="text" name="bulk_issuer_name" id="bulk_issuer_name" required value="株式会社Smiley">
            </div>

            <div class="d-flex gap-2">
                <button type="submit" name="bulk_issue_receipts" class="btn btn-material btn-success flex-grow-1">
                    <span class="material-icons" style="font-size: 1rem; vertical-align: middle;">receipt_long</span>
                    一括発行
                </button>
                <button type="button" class="btn btn-material btn-secondary" onclick="closeBulkReceiptModal()">
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
        document.getElementById('full_amount_value').value = data.outstanding_amount;
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
        document.getElementById('full_amount_value').value = data.outstanding_amount;
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

// 満額入金ボタン
function setFullAmount() {
    const fullAmount = document.getElementById('full_amount_value').value;
    document.getElementById('amount').value = fullAmount;
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

// 編集モーダルを開く
function openEditPaymentModal(payment) {
    const modal = document.getElementById('editPaymentModal');
    const editInfo = document.getElementById('editPaymentInfo');

    document.getElementById('edit_payment_id').value = payment.id;
    document.getElementById('edit_payment_date').value = payment.payment_date;
    document.getElementById('edit_amount').value = payment.amount;
    document.getElementById('edit_payment_method').value = payment.payment_method;
    document.getElementById('edit_reference_number').value = payment.reference_number || '';
    document.getElementById('edit_notes').value = payment.notes || '';

    let name = payment.payment_type === 'individual' ? payment.user_name : payment.company_name;
    editInfo.innerHTML = `
        <strong>入金情報の編集</strong><br>
        ${payment.payment_type === 'individual' ? '利用者' : '企業'}: ${name}<br>
        注文数: ${payment.order_count}件
    `;

    modal.classList.add('active');
}

function closeEditPaymentModal() {
    document.getElementById('editPaymentModal').classList.remove('active');
    document.getElementById('editPaymentForm').reset();
}

// 編集モーダル外クリックで閉じる
document.getElementById('editPaymentModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeEditPaymentModal();
    }
});

// 入金削除確認
function confirmDeletePayment(paymentId, name) {
    if (confirm(`${name} の入金記録を削除してもよろしいですか？\n\nこの操作は取り消せません。削除すると、関連する按分情報もすべて削除されます。`)) {
        document.getElementById('delete_payment_id').value = paymentId;
        document.getElementById('deletePaymentForm').submit();
    }
}

// チェックボックスの全選択/解除
function toggleSelectAll(checkbox) {
    const checkboxes = document.querySelectorAll('.payment-checkbox');
    checkboxes.forEach(cb => {
        cb.checked = checkbox.checked;
    });
    updateBulkButtons();
}

// 一括発行/印刷ボタンの表示/非表示
function updateBulkButtons() {
    const checkboxes = document.querySelectorAll('.payment-checkbox:checked');
    const bulkIssueBtn = document.getElementById('bulkIssueBtn');
    const bulkPrintBtn = document.getElementById('bulkPrintBtn');

    // 選択されたチェックボックスの状態を確認
    let hasUnissued = false;  // 未発行の入金
    let hasIssued = false;    // 発行済みの入金

    checkboxes.forEach(cb => {
        const hasReceipt = cb.dataset.hasReceipt === '1';
        if (hasReceipt) {
            hasIssued = true;
        } else {
            hasUnissued = true;
        }
    });

    // 未発行の入金が選択されている場合は一括発行ボタンを表示
    if (hasUnissued) {
        bulkIssueBtn.style.display = 'inline-flex';
    } else {
        bulkIssueBtn.style.display = 'none';
    }

    // 発行済みの入金が選択されている場合は一括印刷ボタンを表示
    if (hasIssued) {
        bulkPrintBtn.style.display = 'inline-flex';
    } else {
        bulkPrintBtn.style.display = 'none';
    }
}

// 一括印刷機能
function bulkPrintReceipts() {
    const checkboxes = document.querySelectorAll('.payment-checkbox:checked');
    const paymentIds = [];

    // 発行済みの入金IDのみを抽出
    checkboxes.forEach(cb => {
        if (cb.dataset.hasReceipt === '1') {
            paymentIds.push(cb.value);
        }
    });

    if (paymentIds.length === 0) {
        alert('領収書が発行されている入金を選択してください');
        return;
    }

    // 一括印刷ページを新しいタブで開く
    const url = `bulk_receipt_print.php?payment_ids=${paymentIds.join(',')}`;
    window.open(url, '_blank');
}

// 一括領収書発行モーダルを開く
function openBulkIssueModal() {
    const checkboxes = document.querySelectorAll('.payment-checkbox:checked');
    const paymentIds = [];

    // 未発行の入金IDのみを抽出
    checkboxes.forEach(cb => {
        if (cb.dataset.hasReceipt === '0') {
            paymentIds.push(cb.value);
        }
    });

    if (paymentIds.length === 0) {
        alert('領収書が未発行の入金を選択してください');
        return;
    }

    const modal = document.getElementById('bulkReceiptModal');
    const receiptInfo = document.getElementById('bulkReceiptInfo');

    document.getElementById('bulk_payment_ids').value = JSON.stringify(paymentIds);

    receiptInfo.innerHTML = `
        <strong>一括領収書発行</strong><br>
        選択件数: ${paymentIds.length}件
    `;

    modal.classList.add('active');
}

function closeBulkReceiptModal() {
    document.getElementById('bulkReceiptModal').classList.remove('active');
    document.getElementById('bulkReceiptForm').reset();
}

// 一括発行モーダル外クリックで閉じる
document.getElementById('bulkReceiptModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeBulkReceiptModal();
    }
});

// 領収書発行モーダルを開く
function openReceiptModal(paymentId, name, amount) {
    const modal = document.getElementById('receiptModal');
    const receiptInfo = document.getElementById('receiptInfo');

    document.getElementById('receipt_payment_id').value = paymentId;

    receiptInfo.innerHTML = `
        <strong>領収書発行</strong><br>
        対象: ${name}<br>
        入金額: ¥${parseInt(amount).toLocaleString()}
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

// 入金前領収書発行
function issuePreReceipt(viewType, item) {
    if (!confirm(`${item.user_name || item.company_name} の未払い分 ¥${parseInt(item.outstanding_amount).toLocaleString()} の領収書を発行しますか？\n\n※入金日は空欄で発行されます。配達現場で集金時に記入してください。`)) {
        return;
    }

    const action = viewType === 'individual' ? 'issue_by_user' : 'issue_by_company';
    const data = {
        action: action,
        user_id: item.user_id,
        company_name: item.company_name,
        description: 'お弁当代として'
    };

    fetch('../api/pre_receipts.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            alert(`領収書を発行しました。\n\n領収書番号: ${result.receipt_number}\n\n印刷画面を開きます。`);
            // 領収書印刷ページを新しいウィンドウで開く
            window.open(`receipt.php?id=${result.receipt_id}`, '_blank');
            // ページをリロード
            location.reload();
        } else {
            alert('領収書の発行に失敗しました: ' + result.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('領収書の発行中にエラーが発生しました');
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
