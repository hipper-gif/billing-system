<?php
/**
 * Smiley配食事業システム メインダッシュボード
 * 
 * 🔧 修正内容:
 * - Database読み込み順序問題解決
 * - config/database.php を最初に読み込み
 * - PaymentManager エラー "Class Database not found" 完全解決
 */

// 🔧 統合版Database読み込み（設定値+クラス統合済み）
require_once __DIR__ . '/config/database.php';

// ✅ PaymentManager読み込み
require_once __DIR__ . '/classes/PaymentManager.php';

// 🛡️ セキュリティ対策
require_once __DIR__ . '/classes/SecurityHelper.php';

// セッション開始
session_start();

try {
    // 📊 PaymentManager初期化（エラー解消）
    $paymentManager = new PaymentManager();
    
    // 📈 統計データ取得
    $statistics = $paymentManager->getPaymentStatistics('month');
    $alerts = $paymentManager->getPaymentAlerts();
    $outstanding = $paymentManager->getOutstandingAmounts(['overdue_only' => false]);
    
    // 🎯 表示データ準備
    $totalSales = $statistics['summary']['total_amount'];
    $outstandingAmount = $statistics['summary']['outstanding_amount'];
    $alertCount = $alerts['alert_count'];
    $orderCount = $statistics['summary']['order_count'];
    $invoiceCount = $statistics['summary']['invoice_count'];
    
    // 📊 Chart.js用データ準備
    $trendData = $statistics['trend'];
    $methodData = $statistics['payment_methods'];
    
    // 📅 現在日時
    $currentDateTime = date('Y年m月d日 H:i');
    
} catch (Exception $e) {
    error_log("Index Dashboard Error: " . $e->getMessage());
    
    // 🚨 エラー時のデフォルト値
    $totalSales = 0;
    $outstandingAmount = 0;
    $alertCount = 0;
    $orderCount = 0;
    $invoiceCount = 0;
    $trendData = [];
    $methodData = [];
    $currentDateTime = date('Y年m月d日 H:i');
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smiley配食事業システム - ダッシュボード</title>
    
    <!-- 🎨 Bootstrap & Material Design -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    
    <!-- 📊 Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        :root {
            --primary-color: #2196F3;
            --success-color: #4CAF50;
            --warning-color: #FFC107;
            --error-color: #F44336;
            --info-color: #03A9F4;
        }
        
        body {
            font-family: 'Roboto', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        
        .dashboard-container {
            padding: 2rem 0;
        }
        
        .main-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        
        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border-left: 5px solid var(--primary-color);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
        }
        
        .stat-card.success {
            border-left-color: var(--success-color);
        }
        
        .stat-card.warning {
            border-left-color: var(--warning-color);
        }
        
        .stat-card.error {
            border-left-color: var(--error-color);
        }
        
        .stat-card.info {
            border-left-color: var(--info-color);
        }
        
        .stat-value {
            font-size: 3rem;
            font-weight: 700;
            margin: 0;
            line-height: 1;
        }
        
        .stat-label {
            font-size: 1.1rem;
            color: #666;
            margin-top: 0.5rem;
        }
        
        .stat-icon {
            font-size: 4rem;
            opacity: 0.7;
        }
        
        .action-button {
            background: linear-gradient(45deg, var(--primary-color), #1976D2);
            border: none;
            border-radius: 15px;
            color: white;
            font-size: 1.5rem;
            font-weight: 500;
            min-height: 80px;
            margin: 1rem 0;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(33, 150, 243, 0.3);
        }
        
        .action-button:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(33, 150, 243, 0.4);
            color: white;
        }
        
        .chart-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            height: 400px;
        }
        
        .alert-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            border-left: 4px solid var(--warning-color);
        }
        
        .alert-card.error {
            border-left-color: var(--error-color);
        }
        
        .alert-card.info {
            border-left-color: var(--info-color);
        }
        
        .counter {
            transition: all 0.5s ease;
        }
        
        @media (max-width: 768px) {
            .stat-value {
                font-size: 2rem;
            }
            
            .action-button {
                font-size: 1.2rem;
                min-height: 60px;
            }
        }
    </style>
</head>

<body>
    <div class="container-fluid dashboard-container">
        <!-- 🏢 メインヘッダー -->
        <div class="main-header text-center">
            <h1 class="mb-0">
                <i class="material-icons" style="font-size: 3rem; vertical-align: middle;">restaurant</i>
                <strong>Smiley配食事業システム</strong>
            </h1>
            <p class="lead mb-0">請求書・集金管理ダッシュボード</p>
            <small class="text-muted">最終更新: <?php echo $currentDateTime; ?></small>
        </div>
        
        <!-- 📊 統計カード -->
        <div class="row">
            <!-- 💰 今月売上 -->
            <div class="col-md-3">
                <div class="stat-card success">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-value counter" data-target="<?php echo (int)$totalSales; ?>">0</div>
                            <div class="stat-label">今月売上 (円)</div>
                        </div>
                        <i class="material-icons stat-icon" style="color: var(--success-color);">attach_money</i>
                    </div>
                </div>
            </div>
            
            <!-- 📄 未回収金額 -->
            <div class="col-md-3">
                <div class="stat-card warning">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-value counter" data-target="<?php echo (int)$outstandingAmount; ?>">0</div>
                            <div class="stat-label">未回収金額 (円)</div>
                        </div>
                        <i class="material-icons stat-icon" style="color: var(--warning-color);">account_balance_wallet</i>
                    </div>
                </div>
            </div>
            
            <!-- 🚨 アラート件数 -->
            <div class="col-md-3">
                <div class="stat-card <?php echo $alertCount > 0 ? 'error' : 'info'; ?>">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-value counter" data-target="<?php echo $alertCount; ?>">0</div>
                            <div class="stat-label">アラート件数</div>
                        </div>
                        <i class="material-icons stat-icon" style="color: var(<?php echo $alertCount > 0 ? '--error-color' : '--info-color'; ?>);">
                            <?php echo $alertCount > 0 ? 'warning' : 'check_circle'; ?>
                        </i>
                    </div>
                </div>
            </div>
            
            <!-- 📋 注文件数 -->
            <div class="col-md-3">
                <div class="stat-card info">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-value counter" data-target="<?php echo $orderCount; ?>">0</div>
                            <div class="stat-label">今月注文件数</div>
                        </div>
                        <i class="material-icons stat-icon" style="color: var(--info-color);">receipt</i>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- 🎯 アクションボタン -->
        <div class="row mb-4">
            <div class="col-md-3">
                <button class="btn action-button w-100" onclick="location.href='pages/csv_import.php'">
                    <i class="material-icons me-2">upload_file</i>
                    CSVインポート
                </button>
            </div>
            <div class="col-md-3">
                <button class="btn action-button w-100" onclick="location.href='pages/invoice_generate.php'">
                    <i class="material-icons me-2">description</i>
                    請求書生成
                </button>
            </div>
            <div class="col-md-3">
                <button class="btn action-button w-100" onclick="location.href='pages/payments.php'">
                    <i class="material-icons me-2">payment</i>
                    支払い管理
                </button>
            </div>
            <div class="col-md-3">
                <button class="btn action-button w-100" onclick="location.href='pages/receipts.php'">
                    <i class="material-icons me-2">receipt_long</i>
                    領収書管理
                </button>
            </div>
        </div>
        
        <!-- 📊 グラフエリア -->
        <div class="row">
            <!-- 📈 月別売上推移 -->
            <div class="col-md-8">
                <div class="chart-container">
                    <h4 class="mb-3">
                        <i class="material-icons me-2">trending_up</i>
                        月別売上推移
                    </h4>
                    <canvas id="trendChart"></canvas>
                </div>
            </div>
            
            <!-- 🔔 アラート一覧 -->
            <div class="col-md-4">
                <div class="chart-container">
                    <h4 class="mb-3">
                        <i class="material-icons me-2">notifications</i>
                        アラート一覧
                    </h4>
                    <div id="alertsList" style="max-height: 300px; overflow-y: auto;">
                        <?php if (empty($alerts['alerts'])): ?>
                            <div class="alert-card info">
                                <div class="d-flex align-items-center">
                                    <i class="material-icons me-2" style="color: var(--success-color);">check_circle</i>
                                    <span>現在アラートはありません</span>
                                </div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($alerts['alerts'] as $alert): ?>
                                <div class="alert-card <?php echo $alert['type']; ?>">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <strong><?php echo htmlspecialchars($alert['title']); ?></strong>
                                            <p class="mb-1"><?php echo htmlspecialchars($alert['message']); ?></p>
                                            <?php if ($alert['action_url']): ?>
                                                <a href="<?php echo $alert['action_url']; ?>" class="btn btn-sm btn-outline-primary">
                                                    詳細確認
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                        <i class="material-icons">
                                            <?php 
                                                echo $alert['type'] === 'error' ? 'error' : 
                                                    ($alert['type'] === 'warning' ? 'warning' : 'info');
                                            ?>
                                        </i>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- 🏢 企業管理リンク -->
        <div class="row">
            <div class="col-md-4">
                <div class="stat-card">
                    <h5><i class="material-icons me-2">business</i>企業管理</h5>
                    <p class="text-muted">配達先企業の管理・統計表示</p>
                    <a href="pages/companies.php" class="btn btn-outline-primary">企業一覧</a>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="stat-card">
                    <h5><i class="material-icons me-2">people</i>利用者管理</h5>
                    <p class="text-muted">個人利用者の管理・注文履歴</p>
                    <a href="pages/users.php" class="btn btn-outline-primary">利用者一覧</a>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="stat-card">
                    <h5><i class="material-icons me-2">inventory</i>請求書一覧</h5>
                    <p class="text-muted">生成済み請求書の管理・確認</p>
                    <a href="pages/invoices.php" class="btn btn-outline-primary">請求書一覧</a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 🔧 JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // 📊 Chart.js 設定
        const chartData = {
            trend: <?php echo json_encode($trendData); ?>,
            methods: <?php echo json_encode($methodData); ?>
        };
        
        // 📈 月別売上推移チャート
        const trendCtx = document.getElementById('trendChart').getContext('2d');
        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: chartData.trend.map(item => item.month),
                datasets: [{
                    label: '月別売上',
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
        
        // 🔢 カウントアップアニメーション
        function animateCounters() {
            const counters = document.querySelectorAll('.counter');
            const speed = 200;
            
            counters.forEach(counter => {
                const target = +counter.getAttribute('data-target');
                const count = +counter.innerText;
                const inc = target / speed;
                
                if (count < target) {
                    counter.innerText = Math.ceil(count + inc).toLocaleString();
                    setTimeout(() => animateCounters(), 1);
                } else {
                    counter.innerText = target.toLocaleString();
                }
            });
        }
        
        // 🎬 ページ読み込み時のアニメーション
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(animateCounters, 500);
        });
        
        // 🔄 リアルタイム更新（5分間隔）
        setInterval(function() {
            location.reload();
        }, 300000);
    </script>
</body>
</html>
