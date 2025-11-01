<?php
/**
 * 集金管理センター - シンプル版
 * Smiley配食事業システム
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/PaymentManager.php';

// ページ設定
$pageTitle = '集金管理 - Smiley配食事業システム';
$activePage = 'payments';
$basePath = '..';

try {
    $paymentManager = new PaymentManager();

    // 統計データ取得
    $statistics = $paymentManager->getPaymentStatistics('month');
    $alerts = $paymentManager->getPaymentAlerts();
    $outstanding = $paymentManager->getOutstandingAmounts(['overdue_only' => false]);

    // 企業リスト取得
    $db = Database::getInstance();
    $companies = $db->fetchAll("SELECT id, company_name FROM companies WHERE is_active = 1 ORDER BY company_name");

} catch (Exception $e) {
    error_log("集金管理画面エラー: " . $e->getMessage());
    $error = "データの取得に失敗しました: " . $e->getMessage();
    $statistics = ['summary' => ['total_amount' => 0, 'outstanding_amount' => 0]];
    $alerts = ['alert_count' => 0, 'overdue' => ['count' => 0], 'due_soon' => ['count' => 0]];
    $outstanding = [];
    $companies = [];
}

// ヘッダー読み込み
require_once __DIR__ . '/../includes/header.php';
?>

<!-- メインコンテンツ -->
<div class="row mb-4">
    <div class="col-12">
        <h2 class="h4 mb-3">
            <span class="material-icons" style="vertical-align: middle; font-size: 2rem;">payment</span>
            集金管理センター
        </h2>
        <p class="text-white-50">入金状況の確認と未回収金額の管理</p>
    </div>
</div>

<!-- 統計カード -->
<div class="row g-4 mb-4">
    <!-- 今月入金額 -->
    <div class="col-md-3">
        <div class="stat-card success">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-value"><?php echo number_format($statistics['summary']['total_amount'] ?? 0); ?></div>
                    <div class="stat-label">今月入金額 (円)</div>
                </div>
                <span class="material-icons stat-icon" style="color: var(--success-green);">payments</span>
            </div>
        </div>
    </div>

    <!-- 未回収金額 -->
    <div class="col-md-3">
        <div class="stat-card warning">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-value"><?php echo number_format($statistics['summary']['outstanding_amount'] ?? 0); ?></div>
                    <div class="stat-label">未回収金額 (円)</div>
                </div>
                <span class="material-icons stat-icon" style="color: var(--warning-amber);">account_balance_wallet</span>
            </div>
        </div>
    </div>

    <!-- 期限切れ件数 -->
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

    <!-- 要対応件数 -->
    <div class="col-md-3">
        <div class="stat-card info">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-value"><?php echo $alerts['due_soon']['count'] ?? 0; ?></div>
                    <div class="stat-label">要対応件数（3日以内）</div>
                </div>
                <span class="material-icons stat-icon" style="color: var(--info-blue);">schedule</span>
            </div>
        </div>
    </div>
</div>

<!-- アクションカード -->
<div class="row g-4 mb-4">
    <!-- 入金記録 -->
    <div class="col-md-4">
        <a href="#" class="action-card" onclick="alert('入金記録機能は実装中です'); return false;">
            <span class="material-icons">add_circle</span>
            <h3>入金を記録</h3>
            <p>新しい入金情報を登録</p>
        </a>
    </div>

    <!-- 未回収確認 -->
    <div class="col-md-4">
        <a href="#outstanding" class="action-card" style="background: linear-gradient(135deg, #FF9800, #F57C00);">
            <span class="material-icons">warning</span>
            <h3>未回収を確認</h3>
            <p>期限切れ・要対応リスト</p>
        </a>
    </div>

    <!-- 入金履歴 -->
    <div class="col-md-4">
        <a href="#history" class="action-card" style="background: linear-gradient(135deg, #4CAF50, #388E3C);">
            <span class="material-icons">history</span>
            <h3>入金履歴</h3>
            <p>過去の入金記録を確認</p>
        </a>
    </div>
</div>

<!-- 未回収金額一覧 -->
<div class="row" id="outstanding">
    <div class="col-12">
        <div class="payment-summary">
            <h3 class="mb-4">
                <span class="material-icons" style="vertical-align: middle; color: #FF9800;">notifications_active</span>
                未回収金額一覧
            </h3>

            <!-- フィルター -->
            <div class="filter-panel mb-4">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label for="company_filter" class="form-label">企業でフィルター</label>
                        <select class="form-select" id="company_filter" name="company_id">
                            <option value="">全企業</option>
                            <?php foreach ($companies as $company): ?>
                            <option value="<?php echo $company['id']; ?>">
                                <?php echo htmlspecialchars($company['company_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label for="status_filter" class="form-label">ステータス</label>
                        <select class="form-select" id="status_filter" name="status">
                            <option value="">全て</option>
                            <option value="overdue">期限切れ</option>
                            <option value="due_soon">期限間近（3日以内）</option>
                            <option value="normal">通常</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label for="search" class="form-label">検索</label>
                        <input type="text" class="form-control" id="search" name="search" placeholder="企業名・請求書番号">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-material btn-primary w-100">
                            <span class="material-icons" style="font-size: 1rem; vertical-align: middle;">search</span>
                            検索
                        </button>
                    </div>
                </form>
            </div>

            <!-- データテーブル -->
            <?php if (!empty($outstanding)): ?>
            <div class="data-table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>請求書番号</th>
                            <th>企業名</th>
                            <th>請求日</th>
                            <th>支払期限</th>
                            <th>金額</th>
                            <th>状態</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($outstanding, 0, 10) as $item): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($item['invoice_number'] ?? '---'); ?></strong></td>
                            <td><?php echo htmlspecialchars($item['company_name'] ?? '---'); ?></td>
                            <td><?php echo htmlspecialchars($item['invoice_date'] ?? '---'); ?></td>
                            <td><?php echo htmlspecialchars($item['due_date'] ?? '---'); ?></td>
                            <td><strong><?php echo number_format($item['amount'] ?? 0); ?>円</strong></td>
                            <td>
                                <?php
                                $priority = $item['priority'] ?? 'normal';
                                $badge_class = match($priority) {
                                    'overdue' => 'payment-badge overdue',
                                    'urgent' => 'payment-badge pending',
                                    default => 'payment-badge paid'
                                };
                                $badge_text = match($priority) {
                                    'overdue' => '期限切れ',
                                    'urgent' => '期限間近',
                                    default => '通常'
                                };
                                ?>
                                <span class="<?php echo $badge_class; ?>"><?php echo $badge_text; ?></span>
                            </td>
                            <td>
                                <button class="btn btn-material btn-primary btn-material-small" onclick="alert('入金記録機能は実装中です')">
                                    入金記録
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <span class="material-icons">check_circle</span>
                <h5>未回収金額はありません</h5>
                <p>全ての請求が回収済みです</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- 入金履歴セクション -->
<div class="row mt-4" id="history">
    <div class="col-12">
        <div class="payment-summary">
            <h3 class="mb-4">
                <span class="material-icons" style="vertical-align: middle; color: #4CAF50;">history</span>
                最近の入金履歴
            </h3>

            <div class="empty-state">
                <span class="material-icons">inbox</span>
                <h5>入金履歴機能は実装中です</h5>
                <p>入金記録機能と連携して、履歴が表示されます</p>
            </div>
        </div>
    </div>
</div>

<?php
// フッター読み込み
require_once __DIR__ . '/../includes/footer.php';
?>
