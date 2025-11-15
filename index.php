<?php
/**
 * Smiley配食事業システム - ダッシュボード（集金管理中心）
 * メインエントリーポイント
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/classes/SimpleCollectionManager.php';

// ページ設定
$pageTitle = 'ダッシュボード - Smiley配食事業システム';
$activePage = 'dashboard';
$basePath = '.';
$includeChartJS = true;

try {
    $collectionManager = new SimpleCollectionManager();

    // 統計データ取得（ordersテーブルから直接）
    $statistics = $collectionManager->getMonthlyCollectionStats();
    $alerts = $collectionManager->getAlerts();
    $trendData = $collectionManager->getMonthlyTrend(6);

    // 表示データ準備
    $totalSales = $statistics['collected_amount'] ?? 0;
    $outstandingAmount = $statistics['outstanding_amount'] ?? 0;
    $alertCount = $alerts['alert_count'] ?? 0;
    $orderCount = $statistics['total_orders'] ?? 0;
    $overdueCount = $alerts['overdue']['count'] ?? 0;
    $dueSoonCount = $alerts['due_soon']['count'] ?? 0;

    // 現在日時
    $currentDateTime = date('Y年m月d日 H:i');

} catch (Exception $e) {
    error_log("Dashboard Error: " . $e->getMessage());

    // エラー時のデフォルト値
    $totalSales = 0;
    $outstandingAmount = 0;
    $alertCount = 0;
    $orderCount = 0;
    $overdueCount = 0;
    $dueSoonCount = 0;
    $trendData = [];
    $currentDateTime = date('Y年m月d日 H:i');
}

// ヘッダー読み込み
require_once __DIR__ . '/includes/header.php';
?>

<!-- ダッシュボードヘッダー -->
<div class="row mb-4">
    <div class="col-12">
        <div style="background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); border-radius: 20px; padding: 2rem; box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);">
            <h1 class="h3 mb-2">
                <span class="material-icons" style="font-size: 2.5rem; vertical-align: middle; color: #2196F3;">dashboard</span>
                <strong>集金管理ダッシュボード</strong>
            </h1>
            <p class="text-muted mb-1">配食事業の入金状況と未回収金額を一目で確認</p>
            <small class="text-muted">最終更新: <?php echo $currentDateTime; ?></small>
        </div>
    </div>
</div>

<!-- データがない場合の案内 -->
<?php if ($orderCount === 0): ?>
<div class="row mb-4">
    <div class="col-12">
        <div style="background: rgba(255, 255, 255, 0.95); border-radius: 20px; padding: 2rem; box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1); border-left: 5px solid #FFC107;">
            <h4><span class="material-icons" style="vertical-align: middle; color: #FFC107;">info</span> ようこそ！</h4>
            <p>データ取込を行うことで、集金管理を開始できます。</p>
            <div class="d-flex gap-3 mt-3">
                <a href="pages/csv_import.php" class="btn btn-material btn-warning btn-material-large">
                    <span class="material-icons" style="vertical-align: middle;">upload_file</span>
                    CSVデータを取込む
                </a>
                <a href="collection_flow.php" class="btn btn-material btn-flat btn-material-large">
                    <span class="material-icons" style="vertical-align: middle;">help_outline</span>
                    使い方ガイド
                </a>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- 統計カード -->
<div class="row g-4 mb-4">
    <!-- 未回収金額（最優先） -->
    <div class="col-lg-3 col-md-6">
        <div class="stat-card warning">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-value"><?php echo number_format($outstandingAmount); ?></div>
                    <div class="stat-label">未回収金額 (円)</div>
                </div>
                <span class="material-icons stat-icon" style="color: var(--warning-amber);">account_balance_wallet</span>
            </div>
        </div>
    </div>

    <!-- 期限切れ件数 -->
    <div class="col-lg-3 col-md-6">
        <div class="stat-card error">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-value"><?php echo $overdueCount; ?></div>
                    <div class="stat-label">期限切れ件数</div>
                </div>
                <span class="material-icons stat-icon" style="color: var(--error-red);">error</span>
            </div>
        </div>
    </div>

    <!-- 今月入金額 -->
    <div class="col-lg-3 col-md-6">
        <div class="stat-card success">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-value"><?php echo number_format($totalSales); ?></div>
                    <div class="stat-label">今月入金額 (円)</div>
                </div>
                <span class="material-icons stat-icon" style="color: var(--success-green);">payments</span>
            </div>
        </div>
    </div>

    <!-- 要対応件数 -->
    <div class="col-lg-3 col-md-6">
        <div class="stat-card info">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="stat-value"><?php echo $dueSoonCount; ?></div>
                    <div class="stat-label">要対応（3日以内）</div>
                </div>
                <span class="material-icons stat-icon" style="color: var(--info-blue);">schedule</span>
            </div>
        </div>
    </div>
</div>

<!-- メインアクション -->
<div class="row g-4 mb-4">
    <!-- 集金管理（最優先） -->
    <div class="col-md-6">
        <a href="pages/payments.php" class="action-card" style="min-height: 220px;">
            <span class="material-icons" style="font-size: 5rem;">payment</span>
            <h3 style="font-size: 1.75rem;">集金管理</h3>
            <p style="font-size: 1rem;">入金記録・未回収確認・入金履歴</p>
            <div class="mt-3">
                <?php if ($overdueCount > 0): ?>
                <span class="payment-badge overdue">期限切れ <?php echo $overdueCount; ?>件</span>
                <?php endif; ?>
                <?php if ($dueSoonCount > 0): ?>
                <span class="payment-badge pending ms-2">要対応 <?php echo $dueSoonCount; ?>件</span>
                <?php endif; ?>
            </div>
        </a>
    </div>

    <!-- データ取込 -->
    <div class="col-md-6">
        <a href="pages/csv_import.php" class="action-card" style="background: linear-gradient(135deg, #4CAF50, #388E3C); min-height: 220px;">
            <span class="material-icons" style="font-size: 5rem;">upload_file</span>
            <h3 style="font-size: 1.75rem;">データ取込</h3>
            <p style="font-size: 1rem;">CSVファイルから注文データを一括登録</p>
        </a>
    </div>
</div>

<!-- サブアクション -->
<div class="row g-4 mb-4">
    <!-- 企業管理 -->
    <div class="col-md-4">
        <a href="pages/companies.php" class="action-card" style="background: linear-gradient(135deg, #9C27B0, #7B1FA2);">
            <span class="material-icons">business</span>
            <h3>企業管理</h3>
            <p>配達先企業の管理</p>
        </a>
    </div>

    <!-- 利用者管理 -->
    <div class="col-md-4">
        <a href="pages/users.php" class="action-card" style="background: linear-gradient(135deg, #FF9800, #F57C00);">
            <span class="material-icons">people</span>
            <h3>利用者管理</h3>
            <p>個人利用者の管理</p>
        </a>
    </div>

    <!-- その他機能 -->
    <div class="col-md-4">
        <a href="#" onclick="toggleAdvancedMenu(); return false;" class="action-card" style="background: linear-gradient(135deg, #607D8B, #455A64);">
            <span class="material-icons">more_horiz</span>
            <h3>その他機能</h3>
            <p>請求書・領収書など</p>
        </a>
    </div>
</div>

<!-- その他機能メニュー（折りたたみ） -->
<div id="advancedMenu" style="display: none;">
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="stat-card">
                <h5>
                    <span class="material-icons" style="vertical-align: middle;">description</span>
                    請求書作成
                </h5>
                <p class="text-muted">請求書の生成・管理</p>
                <a href="pages/invoice_generate.php" class="btn btn-material btn-primary">請求書作成</a>
                <a href="pages/invoices.php" class="btn btn-material btn-flat ms-2">請求書一覧</a>
            </div>
        </div>

        <div class="col-md-4">
            <div class="stat-card">
                <h5>
                    <span class="material-icons" style="vertical-align: middle;">receipt_long</span>
                    領収書管理
                </h5>
                <p class="text-muted">領収書の発行・管理</p>
                <a href="pages/receipts.php" class="btn btn-material btn-primary">領収書管理</a>
            </div>
        </div>

        <div class="col-md-4">
            <div class="stat-card">
                <h5>
                    <span class="material-icons" style="vertical-align: middle;">settings</span>
                    システム設定
                </h5>
                <p class="text-muted">各種設定・管理</p>
                <a href="pages/settings.php" class="btn btn-material btn-primary">設定画面</a>
            </div>
        </div>
    </div>
</div>

<!-- グラフエリア -->
<div class="row">
    <!-- 月別売上推移 -->
    <div class="col-md-12">
        <div style="background: rgba(255, 255, 255, 0.98); backdrop-filter: blur(10px); border-radius: 20px; padding: 2rem; box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);">
            <h4 class="mb-3">
                <span class="material-icons" style="vertical-align: middle;">trending_up</span>
                月別入金推移
            </h4>
            <div style="height: 300px;">
                <canvas id="trendChart"></canvas>
            </div>
        </div>
    </div>
</div>

<?php
$customJS = <<<'JAVASCRIPT'
<script>
// Chart.js 設定
const chartData = {
    trend: <?php echo json_encode($trendData); ?>
};

// 月別売上推移チャート
if (document.getElementById('trendChart')) {
    const trendCtx = document.getElementById('trendChart').getContext('2d');
    new Chart(trendCtx, {
        type: 'line',
        data: {
            labels: chartData.trend.map(item => item.month),
            datasets: [{
                label: '月別入金額',
                data: chartData.trend.map(item => item.monthly_amount),
                borderColor: '#2196F3',
                backgroundColor: 'rgba(33, 150, 243, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '¥' + value.toLocaleString();
                        }
                    }
                }
            }
        }
    });
}

// その他機能メニューの表示切替
function toggleAdvancedMenu() {
    const menu = document.getElementById('advancedMenu');
    if (menu.style.display === 'none') {
        menu.style.display = 'block';
        menu.style.animation = 'fadeIn 0.5s ease-out';
    } else {
        menu.style.display = 'none';
    }
}

// 統計値のカウントアップアニメーション
function animateValue(element, start, end, duration) {
    const range = end - start;
    const increment = range / (duration / 16);
    let current = start;

    const timer = setInterval(() => {
        current += increment;
        if ((increment > 0 && current >= end) || (increment < 0 && current <= end)) {
            current = end;
            clearInterval(timer);
        }
        element.textContent = Math.floor(current).toLocaleString();
    }, 16);
}

// ページ読み込み時のアニメーション
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.stat-value').forEach(el => {
        const target = parseInt(el.textContent.replace(/,/g, ''));
        el.textContent = '0';
        setTimeout(() => animateValue(el, 0, target, 1000), 300);
    });
});
</script>
JAVASCRIPT;

// フッター読み込み
require_once __DIR__ . '/includes/footer.php';
?>
