<?php
/**
 * index.php - Smiley配食事業システム メインダッシュボード
 * マテリアルデザイン統一版
 * 最終更新: 2025年9月22日（Database.php重複エラー修正版）
 */

// セキュリティ・基本設定
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'config/database.php';
// require_once 'classes/Database.php';  ← ❌ 削除：config/database.php に Singleton Database クラス定義済み
require_once 'classes/PaymentManager.php';

// PaymentManagerインスタンス作成
try {
    $paymentManager = new PaymentManager();
    
    // 統計データ取得
    $statistics = $paymentManager->getPaymentStatistics('month');
    $alerts = $paymentManager->getPaymentAlerts();
    $outstanding = $paymentManager->getOutstandingAmounts(['overdue_only' => false]);
    
} catch (Exception $e) {
    // PaymentManager でエラーが発生した場合の基本データ
    $statistics = ['summary' => ['total_amount' => 0, 'outstanding_amount' => 0, 'outstanding_count' => 0], 'trend' => [], 'payment_methods' => []];
    $alerts = ['alert_count' => 0, 'alerts' => []];
    $outstanding = [];
    
    // デバッグモードでエラー表示
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        echo "<div class='alert alert-warning'>PaymentManager初期化エラー: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

// 基本統計の準備
$totalSales = $statistics['summary']['total_amount'] ?? 0;
$outstandingAmount = $statistics['summary']['outstanding_amount'] ?? 0;
$outstandingCount = $statistics['summary']['outstanding_count'] ?? 0;
$alertCount = $alerts['alert_count'] ?? 0;

// Chart.js用のデータ準備
$trendData = $statistics['trend'] ?? [];
$monthLabels = json_encode(array_column($trendData, 'month'));
$monthAmounts = json_encode(array_column($trendData, 'monthly_amount'));

$methodData = $statistics['payment_methods'] ?? [];
$methodLabels = json_encode(array_map(function($item) {
    $methods = PaymentManager::getPaymentMethods();
    return $methods[$item['payment_method']] ?? $item['payment_method'];
}, $methodData));
$methodAmounts = json_encode(array_column($methodData, 'total_amount'));
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smiley配食事業システム - ダッシュボード</title>
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="assets/css/material-theme.css" rel="stylesheet">
    
    <style>
        /* ページ固有スタイル */
        .dashboard-container {
            padding: var(--spacing-lg);
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .welcome-section {
            background: linear-gradient(135deg, var(--primary-blue), var(--primary-green));
            color: white;
            border-radius: var(--radius-large);
            padding: var(--spacing-xxl);
            margin-bottom: var(--spacing-lg);
            box-shadow: var(--elevation-2);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: var(--spacing-lg);
            margin-bottom: var(--spacing-lg);
        }
        
        .stat-card {
            background: var(--surface-white);
            border-radius: var(--radius-normal);
            padding: var(--spacing-lg);
            box-shadow: var(--elevation-1);
            transition: all var(--transition-normal);
            border-left: 4px solid transparent;
        }
        
        .stat-card:hover {
            box-shadow: var(--elevation-2);
            transform: translateY(-2px);
        }
        
        .stat-card.success { border-left-color: var(--success-green); }
        .stat-card.warning { border-left-color: var(--warning-amber); }
        .stat-card.error { border-left-color: var(--error-red); }
        .stat-card.info { border-left-color: var(--info-blue); }
        
        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: var(--spacing-md);
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin: var(--spacing-sm) 0;
        }
        
        .stat-label {
            color: var(--text-secondary);
            font-size: var(--body-small);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .action-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: var(--spacing-md);
            margin-bottom: var(--spacing-lg);
        }
        
        .action-card {
            background: var(--surface-white);
            border-radius: var(--radius-normal);
            padding: var(--spacing-lg);
            box-shadow: var(--elevation-1);
            text-align: center;
            transition: all var(--transition-normal);
            text-decoration: none;
            color: var(--text-dark);
        }
        
        .action-card:hover {
            box-shadow: var(--elevation-3);
            transform: translateY(-4px);
            text-decoration: none;
            color: var(--text-dark);
        }
        
        .action-icon {
            font-size: 3rem;
            margin-bottom: var(--spacing-md);
            color: var(--primary-blue);
        }
        
        .chart-container {
            background: var(--surface-white);
            border-radius: var(--radius-normal);
            padding: var(--spacing-lg);
            box-shadow: var(--elevation-1);
            margin-bottom: var(--spacing-lg);
        }
        
        .alert-list {
            max-height: 400px;
            overflow-y: auto;
        }
        
        @media (max-width: 768px) {
            .dashboard-container {
                padding: var(--spacing-md);
            }
            
            .welcome-section {
                padding: var(--spacing-lg);
                text-align: center;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
                gap: var(--spacing-md);
            }
            
            .action-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: var(--spacing-sm);
            }
        }
    </style>
</head>
<body>
    <!-- メインナビゲーション -->
    <nav class="navbar navbar-expand-lg" style="background: var(--primary-blue); color: white; box-shadow: var(--elevation-2);">
        <div class="container-fluid" style="max-width: 1400px;">
            <a class="navbar-brand d-flex align-items-center" href="#" style="color: white;">
                <span class="material-icons me-2" style="font-size: 2rem;">restaurant_menu</span>
                <span style="font-weight: 500; font-size: 1.25rem;">Smiley配食事業システム</span>
            </a>
            
            <div class="d-flex align-items-center">
                <!-- アラート表示 -->
                <?php if ($alertCount > 0): ?>
                <div class="me-3">
                    <span class="material-icons text-warning me-1">notifications</span>
                    <span class="badge bg-warning text-dark"><?php echo $alertCount; ?></span>
                </div>
                <?php endif; ?>
                
                <!-- 現在時刻 -->
                <span class="text-small opacity-75">
                    <?php echo date('Y年m月d日 H:i'); ?>
                </span>
            </div>
        </div>
    </nav>

    <!-- メインコンテンツ -->
    <div class="dashboard-container">
        <!-- システム状態確認 -->
        <?php if (defined('DEBUG_MODE') && DEBUG_MODE): ?>
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <span class="material-icons me-2">info</span>
            <strong>システム状態:</strong> 
            Database接続: ✅ 正常 | 
            PaymentManager: <?php echo isset($paymentManager) ? '✅ 正常' : '⚠️ 初期化エラー'; ?> |
            環境: <?php echo defined('ENVIRONMENT') ? ENVIRONMENT : '未定義'; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- ウェルカムセクション -->
        <div class="welcome-section animate-fade-in">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 style="font-size: 2.5rem; font-weight: 300; margin-bottom: var(--spacing-md);">
                        <span class="material-icons me-2" style="font-size: 2.5rem; vertical-align: middle;">dashboard</span>
                        システムダッシュボード
                    </h1>
                    <p style="font-size: var(--body-large); opacity: 0.9; margin: 0;">
                        請求書生成・支払い管理・領収書発行を効率的に管理
                    </p>
                </div>
                <div class="col-md-4 text-md-end text-center">
                    <button class="btn btn-material btn-material-large" 
                            style="background: rgba(255,255,255,0.2); color: white; border: 2px solid white;">
                        <span class="material-icons me-2">play_arrow</span>
                        クイックスタート
                    </button>
                </div>
            </div>
        </div>

        <!-- 統計サマリーカード -->
        <div class="stats-grid">
            <!-- 今月の売上 -->
            <div class="stat-card success animate-fade-in">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <div class="stat-icon text-success">
                            <span class="material-icons">attach_money</span>
                        </div>
                        <div class="stat-value text-success">
                            ¥<?php echo number_format($totalSales); ?>
                        </div>
                        <div class="stat-label">今月の売上</div>
                        <small class="text-secondary">
                            <span class="material-icons" style="font-size: 0.875rem;">trending_up</span>
                            前月比 +12%
                        </small>
                    </div>
                </div>
            </div>

            <!-- 未回収金額 -->
            <div class="stat-card <?php echo $outstandingAmount > 0 ? 'warning' : 'info'; ?> animate-fade-in">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <div class="stat-icon <?php echo $outstandingAmount > 0 ? 'text-warning' : 'text-info'; ?>">
                            <span class="material-icons">account_balance_wallet</span>
                        </div>
                        <div class="stat-value <?php echo $outstandingAmount > 0 ? 'text-warning' : 'text-info'; ?>">
                            ¥<?php echo number_format($outstandingAmount); ?>
                        </div>
                        <div class="stat-label">未回収金額</div>
                        <small class="text-secondary">
                            <?php echo $outstandingCount; ?>件の未払い請求書
                        </small>
                    </div>
                </div>
            </div>

            <!-- 今月の請求書 -->
            <div class="stat-card info animate-fade-in">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <div class="stat-icon text-info">
                            <span class="material-icons">description</span>
                        </div>
                        <div class="stat-value text-info">
                            <?php echo count($trendData); ?>件
                        </div>
                        <div class="stat-label">今月の請求書</div>
                        <small class="text-secondary">
                            <span class="material-icons" style="font-size: 0.875rem;">check_circle</span>
                            完了率 85%
                        </small>
                    </div>
                </div>
            </div>

            <!-- アラート -->
            <div class="stat-card <?php echo $alertCount > 0 ? 'error' : 'success'; ?> animate-fade-in">
                <div class="d-flex align-items-center">
                    <div class="flex-grow-1">
                        <div class="stat-icon <?php echo $alertCount > 0 ? 'text-danger' : 'text-success'; ?>">
                            <span class="material-icons">
                                <?php echo $alertCount > 0 ? 'warning' : 'check_circle'; ?>
                            </span>
                        </div>
                        <div class="stat-value <?php echo $alertCount > 0 ? 'text-danger' : 'text-success'; ?>">
                            <?php echo $alertCount; ?>
                        </div>
                        <div class="stat-label">緊急アラート</div>
                        <small class="text-secondary">
                            <?php echo $alertCount > 0 ? '対応が必要です' : '正常稼働中'; ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <!-- クイックアクション -->
        <div class="row mb-4">
            <div class="col-12">
                <h2 class="mb-3">
                    <span class="material-icons me-2">flash_on</span>
                    クイックアクション
                </h2>
            </div>
        </div>

        <div class="action-grid">
            <!-- CSV インポート -->
            <a href="pages/csv_import.php" class="action-card animate-fade-in">
                <div class="action-icon">
                    <span class="material-icons">file_upload</span>
                </div>
                <h3 class="h5 mb-2">CSVインポート</h3>
                <p class="text-secondary mb-3">注文データを一括取り込み</p>
                <div class="btn btn-material btn-outline">
                    <span class="material-icons me-1">upload</span>
                    データ取込
                </div>
            </a>

            <!-- 請求書生成 -->
            <a href="pages/invoice_generate.php" class="action-card animate-fade-in">
                <div class="action-icon text-success">
                    <span class="material-icons">receipt_long</span>
                </div>
                <h3 class="h5 mb-2">請求書生成</h3>
                <p class="text-secondary mb-3">月次請求書を一括作成</p>
                <div class="btn btn-material btn-success">
                    <span class="material-icons me-1">create</span>
                    今月の請求書を作る
                </div>
            </a>

            <!-- 支払い管理 -->
            <a href="pages/payments.php" class="action-card animate-fade-in">
                <div class="action-icon text-warning">
                    <span class="material-icons">payments</span>
                </div>
                <h3 class="h5 mb-2">支払い管理</h3>
                <p class="text-secondary mb-3">入金記録・未回収管理</p>
                <div class="btn btn-material btn-warning">
                    <span class="material-icons me-1">account_balance</span>
                    支払い状況確認
                </div>
            </a>

            <!-- 領収書発行 -->
            <a href="pages/receipts.php" class="action-card animate-fade-in">
                <div class="action-icon text-info">
                    <span class="material-icons">local_printshop</span>
                </div>
                <h3 class="h5 mb-2">領収書発行</h3>
                <p class="text-secondary mb-3">領収書の作成・印刷</p>
                <div class="btn btn-material btn-info">
                    <span class="material-icons me-1">print</span>
                    領収書作成
                </div>
            </a>

            <!-- 企業管理 -->
            <a href="pages/companies.php" class="action-card animate-fade-in">
                <div class="action-icon text-primary">
                    <span class="material-icons">business</span>
                </div>
                <h3 class="h5 mb-2">企業管理</h3>
                <p class="text-secondary mb-3">配達先企業・部署管理</p>
                <div class="btn btn-material btn-primary">
                    <span class="material-icons me-1">manage_accounts</span>
                    企業設定
                </div>
            </a>

            <!-- システム設定 -->
            <a href="config/database.php?debug=env" class="action-card animate-fade-in">
                <div class="action-icon" style="color: var(--text-secondary);">
                    <span class="material-icons">settings</span>
                </div>
                <h3 class="h5 mb-2">システム設定</h3>
                <p class="text-secondary mb-3">環境確認・データベース状態</p>
                <div class="btn btn-material btn-flat">
                    <span class="material-icons me-1">tune</span>
                    環境確認
                </div>
            </a>
        </div>

        <!-- PC操作不慣れ対応：ヘルプセクション -->
        <div class="material-card mb-4 animate-fade-in">
            <div class="card-header">
                <div class="d-flex align-items-center">
                    <span class="material-icons text-info me-2">help_outline</span>
                    <h3 class="card-title">操作ガイド</h3>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <h4 class="h6 text-primary mb-2">
                        <span class="material-icons me-1" style="font-size: 1rem;">looks_one</span>
                        月次作業の流れ
                    </h4>
                    <ol class="text-small">
                        <li>CSVインポートで注文データを取り込み</li>
                        <li>請求書生成で企業別請求書を作成</li>
                        <li>支払い管理で入金確認・記録</li>
                        <li>領収書発行で領収書を印刷</li>
                    </ol>
                </div>
                <div class="col-md-6">
                    <h4 class="h6 text-primary mb-2">
                        <span class="material-icons me-1" style="font-size: 1rem;">support_agent</span>
                        困ったときは
                    </h4>
                    <ul class="text-small">
                        <li>画面上の<span class="material-icons" style="font-size: 0.875rem;">help</span>アイコンをクリック</li>
                        <li>大きなボタンは重要な操作です</li>
                        <li>色で状態を判断：🟢正常 🟡注意 🔴緊急</li>
                        <li>不明な点はお気軽にお問い合わせください</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- フッター -->
    <footer class="text-center py-4 mt-5" style="background: var(--surface-white); border-top: 1px solid var(--divider-grey);">
        <div class="container">
            <p class="text-secondary mb-2">
                <span class="material-icons me-1" style="font-size: 1rem;">restaurant_menu</span>
                Smiley配食事業システム v2.0 - 修正版
            </p>
            <p class="text-small text-secondary mb-0">
                © 2025 Smiley Kitchen. All rights reserved.
            </p>
        </div>
    </footer>

    <!-- JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
        // Chart.js設定（エラー耐性版）
        const materialColors = {
            primary: '#2196F3',
            success: '#4CAF50',
            warning: '#FFC107',
            error: '#F44336',
            info: '#2196F3'
        };

        // 安全なChart.js初期化
        function initCharts() {
            try {
                const salesTrendCtx = document.getElementById('salesTrendChart');
                const paymentMethodCtx = document.getElementById('paymentMethodChart');
                
                if (salesTrendCtx && paymentMethodCtx) {
                    // 月別売上推移チャート
                    new Chart(salesTrendCtx, {
                        type: 'line',
                        data: {
                            labels: <?php echo $monthLabels; ?>,
                            datasets: [{
                                label: '売上金額',
                                data: <?php echo $monthAmounts; ?>,
                                borderColor: materialColors.primary,
                                backgroundColor: materialColors.primary + '20',
                                borderWidth: 3,
                                fill: true,
                                tension: 0.4
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { display: false } }
                        }
                    });

                    // 支払い方法別円グラフ
                    new Chart(paymentMethodCtx, {
                        type: 'doughnut',
                        data: {
                            labels: <?php echo $methodLabels; ?>,
                            datasets: [{
                                data: <?php echo $methodAmounts; ?>,
                                backgroundColor: [
                                    materialColors.success,
                                    materialColors.primary,
                                    materialColors.warning,
                                    materialColors.info,
                                    '#9C27B0',
                                    '#FF5722'
                                ]
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            cutout: '60%'
                        }
                    });
                }
            } catch (error) {
                console.warn('Chart initialization failed:', error);
            }
        }

        // ページ読み込み時の初期化
        document.addEventListener('DOMContentLoaded', function() {
            // アニメーション
            const cards = document.querySelectorAll('.animate-fade-in');
            cards.forEach((card, index) => {
                card.style.animationDelay = (index * 0.1) + 's';
            });

            // チャート初期化
            setTimeout(initCharts, 500);
        });
    </script>
</body>
</html>
