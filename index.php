<?php
/**
 * Smiley Kitchen 配食事業システム - メインダッシュボード
 * 
 * @author Claude
 * @version 1.0.0
 * @created 2025-08-26
 */

require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/classes/SecurityHelper.php';

// セキュリティヘッダー設定
SecurityHelper::setSecurityHeaders();

$pageTitle = 'Smiley Kitchen - 配食事業管理システム';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.css" rel="stylesheet">
    <style>
        :root {
            --smiley-primary: #4CAF50;    /* Smileyブランドグリーン */
            --smiley-orange: #FF9800;     /* Smileyオレンジ */
            --smiley-pink: #E91E63;       /* Smileyピンク */
            --smiley-light-green: #81C784; /* ライトグリーン */
            --smiley-yellow: #FFC107;     /* イエロー */
        }

        body {
            background: linear-gradient(135deg, #e8f5e8, #f0f8e8);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .smiley-header {
            background: linear-gradient(135deg, var(--smiley-primary), var(--smiley-light-green));
            color: white;
            padding: 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            box-shadow: 0 8px 32px rgba(76, 175, 80, 0.3);
        }

        .logo-container {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
        }

        .logo-container img {
            height: 60px;
            margin-right: 1rem;
        }

        .brand-title {
            font-size: 2.2rem;
            font-weight: bold;
            margin: 0;
        }

        .brand-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
            margin: 0;
        }

        .dashboard-card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 32px rgba(0,0,0,0.15);
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            text-align: center;
            height: 150px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .stat-card.primary { 
            background: linear-gradient(135deg, var(--smiley-primary), var(--smiley-light-green)); 
            color: white; 
        }
        .stat-card.warning { 
            background: linear-gradient(135deg, var(--smiley-orange), var(--smiley-yellow)); 
            color: white; 
        }
        .stat-card.danger { 
            background: linear-gradient(135deg, var(--smiley-pink), #F06292); 
            color: white; 
        }
        .stat-card.info { 
            background: linear-gradient(135deg, #2196F3, #64B5F6); 
            color: white; 
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .action-button {
            background: linear-gradient(135deg, var(--smiley-primary), var(--smiley-light-green));
            border: none;
            color: white;
            padding: 1rem 2rem;
            border-radius: 25px;
            font-size: 1.1rem;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            margin: 0.5rem;
            box-shadow: 0 4px 12px rgba(76, 175, 80, 0.3);
        }

        .action-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(76, 175, 80, 0.4);
            color: white;
        }

        .action-button i {
            margin-right: 0.5rem;
        }

        .action-button.secondary {
            background: linear-gradient(135deg, var(--smiley-orange), var(--smiley-yellow));
            box-shadow: 0 4px 12px rgba(255, 152, 0, 0.3);
        }

        .action-button.secondary:hover {
            box-shadow: 0 8px 20px rgba(255, 152, 0, 0.4);
        }

        .quick-stats {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .recent-activities {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
        }

        .activity-item {
            padding: 0.8rem;
            border-left: 4px solid var(--smiley-primary);
            background: #f8f9fa;
            margin-bottom: 0.5rem;
            border-radius: 0 8px 8px 0;
        }

        .activity-time {
            color: #666;
            font-size: 0.9rem;
        }

        .nav-tabs .nav-link {
            color: var(--smiley-primary);
            border: none;
            border-radius: 20px 20px 0 0;
        }

        .nav-tabs .nav-link.active {
            background: var(--smiley-primary);
            color: white;
        }

        @media (max-width: 768px) {
            .brand-title {
                font-size: 1.5rem;
            }
            
            .action-button {
                width: 100%;
                margin: 0.5rem 0;
                justify-content: center;
            }
            
            .stat-number {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid mt-4">
        <!-- ヘッダー -->
        <div class="smiley-header text-center">
            <div class="logo-container justify-content-center">
                <img src="assets/images/smiley-kitchen-logo.png" alt="Smiley Kitchen Logo" class="logo">
                <div>
                    <h1 class="brand-title">Smiley Kitchen</h1>
                    <p class="brand-subtitle">配食事業管理システム</p>
                </div>
            </div>
        </div>

        <!-- 統計情報 -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="dashboard-card">
                    <div class="stat-card primary">
                        <div class="stat-number" id="totalSales">¥0</div>
                        <div class="stat-label">今月の売上</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="dashboard-card">
                    <div class="stat-card warning">
                        <div class="stat-number" id="pendingAmount">¥0</div>
                        <div class="stat-label">未回収金額</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="dashboard-card">
                    <div class="stat-card info">
                        <div class="stat-number" id="totalInvoices">0</div>
                        <div class="stat-label">今月の請求書</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="dashboard-card">
                    <div class="stat-card danger">
                        <div class="stat-number" id="overdueCount">0</div>
                        <div class="stat-label">期限超過</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- アクションボタン -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="dashboard-card">
                    <div class="card-body text-center py-4">
                        <h5 class="mb-4">
                            <i class="fas fa-rocket me-2" style="color: var(--smiley-primary);"></i>
                            クイックアクション
                        </h5>
                        
                        <!-- 請求書生成（メインアクション） -->
                        <a href="pages/invoice_generate.php" class="action-button">
                            <i class="fas fa-magic"></i>
                            請求書生成
                        </a>
                        
                        <!-- CSVインポート -->
                        <a href="pages/csv_import.php" class="action-button secondary">
                            <i class="fas fa-upload"></i>
                            データ取込
                        </a>
                        
                        <!-- その他のアクション -->
                        <a href="pages/invoices.php" class="action-button">
                            <i class="fas fa-file-invoice"></i>
                            請求書一覧
                        </a>
                        
                        <a href="pages/companies.php" class="action-button secondary">
                            <i class="fas fa-building"></i>
                            企業管理
                        </a>
                        
                        <a href="pages/users.php" class="action-button">
                            <i class="fas fa-users"></i>
                            利用者管理
                        </a>
                        
                        <a href="pages/departments.php" class="action-button secondary">
                            <i class="fas fa-sitemap"></i>
                            部署管理
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- タブ切り替えコンテンツ -->
        <div class="row">
            <div class="col-12">
                <ul class="nav nav-tabs" id="dashboardTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="overview-tab" data-bs-toggle="tab" data-bs-target="#overview" type="button">
                            <i class="fas fa-chart-line me-2"></i>概要
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="activities-tab" data-bs-toggle="tab" data-bs-target="#activities" type="button">
                            <i class="fas fa-clock me-2"></i>最近の活動
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="alerts-tab" data-bs-toggle="tab" data-bs-target="#alerts" type="button">
                            <i class="fas fa-exclamation-triangle me-2"></i>アラート
                        </button>
                    </li>
                </ul>
            </div>
        </div>

        <div class="tab-content" id="dashboardTabsContent">
            <!-- 概要タブ -->
            <div class="tab-pane fade show active" id="overview" role="tabpanel">
                <div class="row mt-3">
                    <div class="col-md-8">
                        <div class="dashboard-card">
                            <div class="card-body">
                                <h6><i class="fas fa-chart-bar me-2"></i>月別売上推移</h6>
                                <canvas id="salesChart" height="120"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="quick-stats">
                            <h6><i class="fas fa-info-circle me-2"></i>システム概要</h6>
                            <div class="d-flex justify-content-between mb-2">
                                <span>総企業数:</span>
                                <span id="totalCompanies" class="fw-bold">-</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>総利用者数:</span>
                                <span id="totalUsers" class="fw-bold">-</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>今月注文数:</span>
                                <span id="monthlyOrders" class="fw-bold">-</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>平均単価:</span>
                                <span id="avgUnitPrice" class="fw-bold">¥-</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 最近の活動タブ -->
            <div class="tab-pane fade" id="activities" role="tabpanel">
                <div class="recent-activities mt-3">
                    <h6><i class="fas fa-clock me-2"></i>最近の活動</h6>
                    <div id="recentActivities">
                        <div class="text-center py-3">
                            <i class="fas fa-spinner fa-spin me-2"></i>読み込み中...
                        </div>
                    </div>
                </div>
            </div>

            <!-- アラートタブ -->
            <div class="tab-pane fade" id="alerts" role="tabpanel">
                <div class="recent-activities mt-3">
                    <h6><i class="fas fa-exclamation-triangle me-2"></i>重要なお知らせ</h6>
                    <div id="systemAlerts">
                        <div class="text-center py-3">
                            <i class="fas fa-spinner fa-spin me-2"></i>読み込み中...
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    
    <script>
        // 初期化
        document.addEventListener('DOMContentLoaded', function() {
            loadDashboardData();
            initializeSalesChart();
            loadRecentActivities();
            loadSystemAlerts();
            
            // 10分ごとにデータ更新
            setInterval(loadDashboardData, 600000);
        });
        
        // ダッシュボードデータ読み込み
        function loadDashboardData() {
            Promise.all([
                fetch('api/dashboard.php?action=statistics'),
                fetch('api/companies.php?action=summary'),
                fetch('api/users.php?action=summary')
            ])
            .then(responses => Promise.all(responses.map(r => r.json())))
            .then(([statsData, companiesData, usersData]) => {
                updateStatistics(statsData.data);
                updateSystemOverview(companiesData.data, usersData.data);
            })
            .catch(error => {
                console.error('Dashboard data load error:', error);
            });
        }
        
        // 統計情報更新
        function updateStatistics(data) {
            if (data) {
                document.getElementById('totalSales').textContent = 
                    '¥' + (data.total_sales || 0).toLocaleString();
                document.getElementById('pendingAmount').textContent = 
                    '¥' + (data.pending_amount || 0).toLocaleString();
                document.getElementById('totalInvoices').textContent = 
                    data.total_invoices || 0;
                document.getElementById('overdueCount').textContent = 
                    data.overdue_count || 0;
            }
        }
        
        // システム概要更新
        function updateSystemOverview(companiesData, usersData) {
            document.getElementById('totalCompanies').textContent = 
                companiesData?.total_companies || 0;
            document.getElementById('totalUsers').textContent = 
                usersData?.total_users || 0;
            document.getElementById('monthlyOrders').textContent = 
                usersData?.monthly_orders || 0;
            document.getElementById('avgUnitPrice').textContent = 
                '¥' + (usersData?.avg_unit_price || 0).toLocaleString();
        }
        
        // 売上グラフ初期化
        function initializeSalesChart() {
            const ctx = document.getElementById('salesChart');
            if (!ctx) return;
            
            const salesChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: ['1月', '2月', '3月', '4月', '5月', '6月'],
                    datasets: [{
                        label: '月別売上',
                        data: [0, 0, 0, 0, 0, 0],
                        borderColor: getComputedStyle(document.documentElement)
                            .getPropertyValue('--smiley-primary'),
                        backgroundColor: getComputedStyle(document.documentElement)
                            .getPropertyValue('--smiley-primary') + '20',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '¥' + value.toLocaleString();
                                }
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
            
            // 実際のデータで更新
            fetch('api/dashboard.php?action=monthly_sales')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.data) {
                        salesChart.data.labels = data.data.labels;
                        salesChart.data.datasets[0].data = data.data.values;
                        salesChart.update();
                    }
                })
                .catch(error => console.error('Sales chart data error:', error));
        }
        
        // 最近の活動読み込み
        function loadRecentActivities() {
            fetch('api/dashboard.php?action=recent_activities')
                .then(response => response.json())
                .then(data => {
                    const container = document.getElementById('recentActivities');
                    
                    if (data.success && data.data && data.data.length > 0) {
                        let html = '';
                        data.data.forEach(activity => {
                            html += `
                                <div class="activity-item">
                                    <div class="fw-bold">${activity.title}</div>
                                    <div class="activity-time">${formatDateTime(activity.created_at)}</div>
                                </div>
                            `;
                        });
                        container.innerHTML = html;
                    } else {
                        container.innerHTML = '<p class="text-muted text-center py-3">最近の活動はありません</p>';
                    }
                })
                .catch(error => {
                    console.error('Activities load error:', error);
                    document.getElementById('recentActivities').innerHTML = 
                        '<p class="text-danger text-center py-3">読み込みエラー</p>';
                });
        }
        
        // システムアラート読み込み
        function loadSystemAlerts() {
            fetch('api/dashboard.php?action=system_alerts')
                .then(response => response.json())
                .then(data => {
                    const container = document.getElementById('systemAlerts');
                    
                    if (data.success && data.data && data.data.length > 0) {
                        let html = '';
                        data.data.forEach(alert => {
                            const alertClass = alert.type === 'error' ? 'border-danger' : 
                                             alert.type === 'warning' ? 'border-warning' : 'border-info';
                            html += `
                                <div class="activity-item ${alertClass}">
                                    <div class="fw-bold">${alert.title}</div>
                                    <div class="small">${alert.message}</div>
                                    <div class="activity-time">${formatDateTime(alert.created_at)}</div>
                                </div>
                            `;
                        });
                        container.innerHTML = html;
                    } else {
                        container.innerHTML = '<p class="text-muted text-center py-3">現在アラートはありません</p>';
                    }
                })
                .catch(error => {
                    console.error('Alerts load error:', error);
                    document.getElementById('systemAlerts').innerHTML = 
                        '<p class="text-danger text-center py-3">読み込みエラー</p>';
                });
        }
        
        // 日時フォーマット
        function formatDateTime(dateString) {
            if (!dateString) return '';
            const date = new Date(dateString);
            return date.toLocaleString('ja-JP');
        }
    </script>
</body>
</html>
