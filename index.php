<?php
/**
 * index.php - Smiley配食事業システム メインダッシュボード
 * ボタンレイアウト修正版
 * 最終更新: 2025年9月17日
 */

// セキュリティ・基本設定
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'config/database.php';
require_once 'classes/Database.php';
require_once 'classes/PaymentManager.php';

// PaymentManagerインスタンス作成
$paymentManager = new PaymentManager();

// 統計データ取得
$statistics = $paymentManager->getPaymentStatistics('month');
$alerts = $paymentManager->getPaymentAlerts();
$outstanding = $paymentManager->getOutstandingAmounts(['overdue_only' => false]);

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
        /* ページ固有スタイル - ボタン修正版 */
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
            font-size: var(--font-sm);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* 修正: アクションボタングリッド */
        .action-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: var(--spacing-lg);
            margin-bottom: var(--spacing-lg);
        }
        
        /* 修正: アクションカードの基本スタイル */
        .action-card {
            background: var(--surface-white);
            border-radius: var(--radius-normal);
            padding: var(--spacing-xl);
            box-shadow: var(--elevation-1);
            text-align: center;
            transition: all var(--transition-normal);
            text-decoration: none;
            color: var(--text-dark);
            border: 2px solid transparent;
            min-height: 220px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }
        
        .action-card:hover {
            box-shadow: var(--elevation-3);
            transform: translateY(-6px);
            text-decoration: none;
            color: var(--text-dark);
            border-color: var(--primary-blue);
        }
        
        .action-card:active {
            transform: translateY(-2px);
            box-shadow: var(--elevation-2);
        }
        
        /* 修正: アクションアイコン */
        .action-icon {
            font-size: 4rem;
            margin-bottom: var(--spacing-lg);
            color: var(--primary-blue);
            transition: all var(--transition-normal);
        }
        
        .action-card:hover .action-icon {
            transform: scale(1.1);
            color: var(--primary-green);
        }
        
        /* 修正: アクションタイトル */
        .action-title {
            font-size: var(--font-xl);
            font-weight: var(--font-weight-medium);
            margin-bottom: var(--spacing-md);
            color: var(--text-dark);
        }
        
        /* 修正: アクション説明 */
        .action-description {
            font-size: var(--font-sm);
            color: var(--text-secondary);
            margin-bottom: var(--spacing-lg);
            line-height: 1.5;
        }
        
        /* 修正: アクションボタン */
        .action-button {
            background: var(--primary-blue);
            color: var(--text-light);
            border: none;
            border-radius: var(--radius-normal);
            padding: var(--spacing-md) var(--spacing-lg);
            font-size: var(--font-sm);
            font-weight: var(--font-weight-medium);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all var(--transition-normal);
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
            margin-top: auto;
        }
        
        .action-card:hover .action-button {
            background: var(--primary-green);
            transform: translateY(-2px);
            box-shadow: var(--elevation-2);
        }
        
        /* カラーバリエーション */
        .action-card.success .action-icon { color: var(--success-green); }
        .action-card.success:hover .action-icon { color: var(--primary-blue); }
        .action-card.success .action-button { background: var(--success-green); }
        .action-card.success:hover .action-button { background: var(--primary-green); }
        
        .action-card.warning .action-icon { color: var(--warning-amber); }
        .action-card.warning:hover .action-icon { color: var(--primary-blue); }
        .action-card.warning .action-button { background: var(--warning-amber); color: var(--text-dark); }
        .action-card.warning:hover .action-button { background: var(--primary-green); color: var(--text-light); }
        
        .action-card.info .action-icon { color: var(--info-blue); }
        .action-card.info:hover .action-icon { color: var(--primary-green); }
        .action-card.info .action-button { background: var(--info-blue); }
        .action-card.info:hover .action-button { background: var(--primary-green); }
        
        .action-card.secondary .action-icon { color: var(--text-secondary); }
        .action-card.secondary:hover .action-icon { color: var(--primary-blue); }
        .action-card.secondary .action-button { background: var(--text-secondary); }
        .action-card.secondary:hover .action-button { background: var(--primary-blue); }
        
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
        
        /* レスポンシブ対応 */
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
                grid-template-columns: 1fr;
                gap: var(--spacing-md);
            }
            
            .action-card {
                min-height: 180px;
                padding: var(--spacing-lg);
            }
            
            .action-icon {
                font-size: 3rem;
                margin-bottom: var(--spacing-md);
            }
            
            .action-title {
                font-size: var(--font-lg);
            }
        }
        
        @media (max-width: 480px) {
            .action-grid {
                grid-template-columns: 1fr;
            }
            
            .action-card {
                min-height: 160px;
                padding: var(--spacing-md);
            }
            
            .action-icon {
                font-size: 2.5rem;
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
        <!-- ウェルカムセクション -->
        <div class="welcome-section animate-fade-in">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 style="font-size: 2.5rem; font-weight: 300; margin-bottom: var(--spacing-md);">
                        <span class="material-icons me-2" style="font-size: 2.5rem; vertical-align: middle;">dashboard</span>
                        システムダッシュボード
                    </h1>
                    <p style="font-size: var(--font-lg); opacity: 0.9; margin: 0;">
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

        <!-- 修正: アクションカードグリッド -->
        <div class="action-grid">
            <!-- CSV インポート -->
            <a href="pages/csv_import.php" class="action-card info animate-fade-in">
                <div class="action-icon">
                    <span class="material-icons">file_upload</span>
                </div>
                <h3 class="action-title">CSVインポート</h3>
                <p class="action-description">注文データを一括取り込み<br>月次データの効率的な処理</p>
                <div class="action-button">
                    <span class="material-icons">upload</span>
                    データ取込
                </div>
            </a>

            <!-- 請求書生成 -->
            <a href="pages/invoice_generate.php" class="action-card success animate-fade-in">
                <div class="action-icon">
                    <span class="material-icons">receipt_long</span>
                </div>
                <h3 class="action-title">請求書生成</h3>
                <p class="action-description">月次請求書を一括作成<br>企業別・部署別対応</p>
                <div class="action-button">
                    <span class="material-icons">create</span>
                    今月の請求書を作る
                </div>
            </a>

            <!-- 支払い管理 -->
            <a href="pages/payments.php" class="action-card warning animate-fade-in">
                <div class="action-icon">
                    <span class="material-icons">payments</span>
                </div>
                <h3 class="action-title">支払い管理</h3>
                <p class="action-description">入金記録・未回収管理<br>支払い状況の一元管理</p>
                <div class="action-button">
                    <span class="material-icons">account_balance</span>
                    支払い状況確認
                </div>
            </a>

            <!-- 領収書発行 -->
            <a href="pages/receipts.php" class="action-card info animate-fade-in">
                <div class="action-icon">
                    <span class="material-icons">local_printshop</span>
                </div>
                <h3 class="action-title">領収書発行</h3>
                <p class="action-description">領収書の作成・印刷<br>収入印紙対応・PDF出力</p>
                <div class="action-button">
                    <span class="material-icons">print</span>
                    領収書作成
                </div>
            </a>

            <!-- 企業管理 -->
            <a href="pages/companies.php" class="action-card animate-fade-in">
                <div class="action-icon">
                    <span class="material-icons">business</span>
                </div>
                <h3 class="action-title">企業管理</h3>
                <p class="action-description">配達先企業・部署管理<br>利用者情報の管理</p>
                <div class="action-button">
                    <span class="material-icons">manage_accounts</span>
                    企業設定
                </div>
            </a>

            <!-- システム設定 -->
            <a href="#" class="action-card secondary animate-fade-in">
                <div class="action-icon">
                    <span class="material-icons">settings</span>
                </div>
                <h3 class="action-title">システム設定</h3>
                <p class="action-description">各種設定・環境管理<br>バックアップ・メンテナンス</p>
                <div class="action-button">
                    <span class="material-icons">tune</span>
                    設定画面
                </div>
            </a>
        </div>

        <!-- アラート通知 -->
        <?php if (!empty($alerts['alerts'])): ?>
        <div class="row mb-4">
            <div class="col-12">
                <h2 class="mb-3">
                    <span class="material-icons me-2 text-warning">priority_high</span>
                    重要な通知
                </h2>
            </div>
        </div>

        <div class="material-card animate-fade-in">
            <div class="alert-list">
                <?php foreach ($alerts['alerts'] as $alert): ?>
                <div class="material-alert alert-<?php echo $alert['type']; ?> mb-2">
                    <span class="alert-icon material-icons">
                        <?php 
                        switch($alert['type']) {
                            case 'error': echo 'error'; break;
                            case 'warning': echo 'warning'; break;
                            case 'success': echo 'check_circle'; break;
                            default: echo 'info'; break;
                        }
                        ?>
                    </span>
                    <div class="flex-grow-1">
                        <strong><?php echo htmlspecialchars($alert['title']); ?></strong><br>
                        <?php echo htmlspecialchars($alert['message']); ?>
                        <?php if (isset($alert['amount']) && $alert['amount'] > 0): ?>
                        <br><small>金額: ¥<?php echo number_format($alert['amount']); ?></small>
                        <?php endif; ?>
                    </div>
                    <?php if (isset($alert['action_url'])): ?>
                    <a href="<?php echo $alert['action_url']; ?>" class="btn btn-material btn-flat btn-sm ms-2">
                        <span class="material-icons">arrow_forward</span>
                    </a>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- チャートセクション -->
        <div class="row">
            <div class="col-lg-8 mb-4">
                <div class="chart-container animate-fade-in">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h3 class="mb-0">
                            <span class="material-icons me-2">trending_up</span>
                            月別売上推移
                        </h3>
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-material btn-flat btn-sm active">月別</button>
                            <button type="button" class="btn btn-material btn-flat btn-sm">週別</button>
                            <button type="button" class="btn btn-material btn-flat btn-sm">日別</button>
                        </div>
                    </div>
                    <canvas id="salesTrendChart" height="300"></canvas>
                </div>
            </div>

            <div class="col-lg-4 mb-4">
                <div class="chart-container animate-fade-in">
                    <h3 class="mb-3">
                        <span class="material-icons me-2">pie_chart</span>
                        支払い方法別割合
                    </h3>
                    <canvas id="paymentMethodChart" height="300"></canvas>
                    
                    <!-- 支払い方法の詳細 -->
                    <div class="mt-3">
                        <?php foreach ($methodData as $method): ?>
                        <?php
                        $methods = PaymentManager::getPaymentMethods();
                        $methodName = $methods[$method['payment_method']] ?? $method['payment_method'];
                        $percentage = $totalSales > 0 ? round(($method['total_amount'] / $totalSales) * 100, 1) : 0;
                        ?>
                        <div class="d-flex justify-content-between align-items-center py-1">
                            <span class="text-small"><?php echo $methodName; ?></span>
                            <span class="text-small">
                                <strong><?php echo $percentage; ?>%</strong>
                                <small class="text-secondary">(¥<?php echo number_format($method['total_amount']); ?>)</small>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
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

    <!-- フローティングアクションボタン -->
    <button class="fab" onclick="showQuickMenu()">
        <span class="material-icons">add</span>
    </button>

    <!-- フッター -->
    <footer class="text-center py-4 mt-5" style="background: var(--surface-white); border-top: 1px solid var(--divider-grey);">
        <div class="container">
            <p class="text-secondary mb-2">
                <span class="material-icons me-1" style="font-size: 1rem;">restaurant_menu</span>
                Smiley配食事業システム v2.0
            </p>
            <p class="text-small text-secondary mb-0">
                © 2025 Smiley Kitchen. All rights reserved. | 
                <a href="#" class="text-decoration-none">利用規約</a> | 
                <a href="#" class="text-decoration-none">プライバシーポリシー</a>
            </p>
        </div>
    </footer>

    <!-- JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
        // Chart.js設定
        const materialColors = {
            primary: '#2196F3',
            success: '#4CAF50',
            warning: '#FFC107',
            error: '#F44336',
            info: '#2196F3'
        };

        // 月別売上推移チャート
        const salesTrendCtx = document.getElementById('salesTrendChart').getContext('2d');
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
                    tension: 0.4,
                    pointBackgroundColor: materialColors.primary,
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 6
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
                        },
                        grid: {
                            color: '#E0E0E0'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                },
                elements: {
                    point: {
                        hoverRadius: 8
                    }
                },
                interaction: {
                    intersect: false,
                    mode: 'index'
                }
            }
        });

        // 支払い方法別円グラフ
        const paymentMethodCtx = document.getElementById('paymentMethodChart').getContext('2d');
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
                    ],
                    borderWidth: 0,
                    hoverBorderWidth: 2,
                    hoverBorderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true,
                            font: {
                                size: 12
                            }
                        }
                    }
                },
                cutout: '60%'
            }
        });

        // フローティングアクションボタン機能
        function showQuickMenu() {
            const actions = [
                { icon: 'receipt_long', text: '請求書生成', url: 'pages/invoice_generate.php' },
                { icon: 'payments', text: '支払い確認', url: 'pages/payments.php' },
                { icon: 'file_upload', text: 'CSV取込', url: 'pages/csv_import.php' }
            ];
            
            // 簡易メニュー表示（実装は省略）
            alert('クイックメニュー機能（実装予定）');
        }

        // ページ読み込み時のアニメーション
        document.addEventListener('DOMContentLoaded', function() {
            // カードのスタガーアニメーション
            const cards = document.querySelectorAll('.animate-fade-in');
            cards.forEach((card, index) => {
                card.style.animationDelay = (index * 0.1) + 's';
            });

            // 統計数値のカウントアップアニメーション
            const statValues = document.querySelectorAll('.stat-value');
            statValues.forEach(stat => {
                const finalValue = parseInt(stat.textContent.replace(/[^\d]/g, ''));
                animateNumber(stat, finalValue);
            });
        });

        // 数値アニメーション関数
        function animateNumber(element, finalValue, duration = 1000) {
            let startValue = 0;
            const increment = finalValue / (duration / 16);
            
            function updateNumber() {
                startValue += increment;
                if (startValue < finalValue) {
                    element.innerHTML = element.innerHTML.replace(/[\d,]+/, Math.floor(startValue).toLocaleString());
                    requestAnimationFrame(updateNumber);
                } else {
                    element.innerHTML = element.innerHTML.replace(/[\d,]+/, finalValue.toLocaleString());
                }
            }
            
            updateNumber();
        }

        // レスポンシブ対応
        window.addEventListener('resize', function() {
            // チャートのリサイズは Chart.js が自動対応
        });

        // ダークモード切り替え（将来機能）
        function toggleDarkMode() {
            document.body.classList.toggle('dark-mode');
            localStorage.setItem('darkMode', document.body.classList.contains('dark-mode'));
        }

        // ローカルストレージからダークモード設定を復元
        if (localStorage.getItem('darkMode') === 'true') {
            document.body.classList.add('dark-mode');
        }
    </script>
</body>
</html>
