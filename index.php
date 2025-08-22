<?php
/**
 * Smiley配食事業 請求書・集金管理システム
 * メインダッシュボード（修正版）
 * 
 * 修正内容:
 * 1. Database::getInstance()を使用
 * 2. エラーハンドリング強化
 * 3. Smiley配食事業専用UI
 */

require_once 'config/database.php';
require_once 'classes/Database.php';
require_once 'classes/SecurityHelper.php';

// セキュリティヘッダー設定
SecurityHelper::setSecurityHeaders();

// 統計データ取得
$stats = getDashboardStats();

function getDashboardStats() {
    try {
        // Database::getInstance()を使用
        $db = Database::getInstance();
        
        $stats = [
            'total_orders' => 0,
            'total_revenue' => 0,
            'active_companies' => 0,
            'active_users' => 0,
            'pending_invoices' => 0,
            'unpaid_amount' => 0,
            'recent_orders' => [],
            'monthly_revenue' => []
        ];
        
        // 総注文数
        $stmt = $db->query("SELECT COUNT(*) as total FROM orders WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $result = $stmt->fetch();
        $stats['total_orders'] = $result['total'] ?? 0;
        
        // 総売上（過去30日）
        $stmt = $db->query("SELECT SUM(total_amount) as revenue FROM orders WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $result = $stmt->fetch();
        $stats['total_revenue'] = $result['revenue'] ?? 0;
        
        // アクティブ企業数
        $stmt = $db->query("SELECT COUNT(DISTINCT company_id) as companies FROM orders WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $result = $stmt->fetch();
        $stats['active_companies'] = $result['companies'] ?? 0;
        
        // アクティブ利用者数
        $stmt = $db->query("SELECT COUNT(DISTINCT user_id) as users FROM orders WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $result = $stmt->fetch();
        $stats['active_users'] = $result['users'] ?? 0;
        
        // 未請求額（概算）
        $stmt = $db->query("
            SELECT SUM(total_amount) as unpaid 
            FROM orders o 
            WHERE NOT EXISTS (
                SELECT 1 FROM invoices i 
                WHERE i.user_id = o.user_id 
                AND DATE(o.delivery_date) BETWEEN i.period_start AND i.period_end
            )
        ");
        $result = $stmt->fetch();
        $stats['unpaid_amount'] = $result['unpaid'] ?? 0;
        
        // 最近の注文（上位5件）
        $stmt = $db->query("
            SELECT 
                o.delivery_date,
                o.user_name,
                o.company_name,
                o.product_name,
                o.total_amount,
                o.created_at
            FROM orders o 
            ORDER BY o.created_at DESC 
            LIMIT 5
        ");
        $stats['recent_orders'] = $stmt->fetchAll();
        
        // 月別売上（過去6ヶ月）
        $stmt = $db->query("
            SELECT 
                DATE_FORMAT(delivery_date, '%Y-%m') as month,
                SUM(total_amount) as revenue,
                COUNT(*) as order_count
            FROM orders 
            WHERE delivery_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
            GROUP BY DATE_FORMAT(delivery_date, '%Y-%m')
            ORDER BY month ASC
        ");
        $stats['monthly_revenue'] = $stmt->fetchAll();
        
        return $stats;
        
    } catch (Exception $e) {
        error_log("Dashboard stats error: " . $e->getMessage());
        return [
            'total_orders' => 'エラー',
            'total_revenue' => 'エラー',
            'active_companies' => 'エラー',
            'active_users' => 'エラー',
            'pending_invoices' => 'エラー',
            'unpaid_amount' => 'エラー',
            'recent_orders' => [],
            'monthly_revenue' => [],
            'error' => $e->getMessage()
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🍱 Smiley配食 請求書管理システム</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .main-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            margin: 20px auto;
            padding: 30px;
            max-width: 1400px;
        }
        .smiley-green { color: #2E8B57; }
        .bg-smiley-green { background-color: #2E8B57; }
        .main-btn {
            min-height: 120px;
            font-size: 1.2rem;
            font-weight: bold;
            border-radius: 15px;
            transition: all 0.3s ease;
            margin-bottom: 20px;
        }
        .main-btn:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }
        .stat-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            border-left: 5px solid #2E8B57;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: #2E8B57;
        }
        .btn-outline-smiley {
            border-color: #2E8B57;
            color: #2E8B57;
        }
        .btn-outline-smiley:hover {
            background-color: #2E8B57;
            border-color: #2E8B57;
            color: white;
        }
        .recent-orders-table {
            font-size: 0.9rem;
        }
        .chart-container {
            position: relative;
            height: 300px;
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body>
    <div class="main-container">
        <!-- ヘッダー -->
        <div class="text-center mb-5">
            <h1 class="display-4 smiley-green mb-3">🍱 Smiley配食 請求書管理システム</h1>
            <p class="lead text-muted">配達先企業・利用者管理、請求書生成、集金管理を一元化</p>
        </div>

        <!-- 統計サマリー -->
        <div class="row mb-5">
            <div class="col-lg-3 col-md-6">
                <div class="stat-card text-center">
                    <div class="stat-number"><?php echo is_numeric($stats['total_orders']) ? number_format($stats['total_orders']) : $stats['total_orders']; ?></div>
                    <div class="text-muted">今月の注文数</div>
                    <small class="text-success"><i class="bi bi-graph-up"></i> 過去30日間</small>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stat-card text-center">
                    <div class="stat-number">¥<?php echo is_numeric($stats['total_revenue']) ? number_format($stats['total_revenue']) : $stats['total_revenue']; ?></div>
                    <div class="text-muted">今月の売上</div>
                    <small class="text-success"><i class="bi bi-currency-yen"></i> 過去30日間</small>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stat-card text-center">
                    <div class="stat-number"><?php echo is_numeric($stats['active_companies']) ? number_format($stats['active_companies']) : $stats['active_companies']; ?></div>
                    <div class="text-muted">アクティブ企業</div>
                    <small class="text-info"><i class="bi bi-building"></i> 利用中企業数</small>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stat-card text-center">
                    <div class="stat-number">¥<?php echo is_numeric($stats['unpaid_amount']) ? number_format($stats['unpaid_amount']) : $stats['unpaid_amount']; ?></div>
                    <div class="text-muted">未回収金額</div>
                    <small class="text-warning"><i class="bi bi-exclamation-triangle"></i> 要確認</small>
                </div>
            </div>
        </div>

        <!-- メイン機能ボタン -->
        <div class="row mb-5">
            <div class="col-lg-3 col-md-6">
                <a href="pages/csv_import.php" class="btn btn-primary main-btn w-100 d-flex flex-column align-items-center justify-content-center">
                    <i class="bi bi-cloud-upload-fill fs-1 mb-2"></i>
                    <span>CSVインポート</span>
                    <small>注文データ取込</small>
                </a>
            </div>
            <div class="col-lg-3 col-md-6">
                <a href="pages/companies.php" class="btn btn-success main-btn w-100 d-flex flex-column align-items-center justify-content-center">
                    <i class="bi bi-building fs-1 mb-2"></i>
                    <span>配達先企業管理</span>
                    <small>企業・部署・利用者</small>
                </a>
            </div>
            <div class="col-lg-3 col-md-6">
                <a href="pages/invoices.php" class="btn btn-warning main-btn w-100 d-flex flex-column align-items-center justify-content-center">
                    <i class="bi bi-receipt fs-1 mb-2"></i>
                    <span>請求書生成</span>
                    <small>企業別・個人別</small>
                </a>
            </div>
            <div class="col-lg-3 col-md-6">
                <a href="pages/payments.php" class="btn btn-info main-btn w-100 d-flex flex-column align-items-center justify-content-center">
                    <i class="bi bi-credit-card fs-1 mb-2"></i>
                    <span>集金管理</span>
                    <small>支払確認・督促</small>
                </a>
            </div>
        </div>

        <!-- 詳細情報 -->
        <div class="row">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header bg-smiley-green text-white">
                        <h5 class="mb-0"><i class="bi bi-clock-history"></i> 最近の注文</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (!empty($stats['recent_orders'])): ?>
                            <div class="table-responsive">
                                <table class="table table-hover recent-orders-table mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>配達日</th>
                                            <th>利用者</th>
                                            <th>企業</th>
                                            <th>金額</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($stats['recent_orders'] as $order): ?>
                                            <tr>
                                                <td><?php echo date('m/d', strtotime($order['delivery_date'])); ?></td>
                                                <td><?php echo htmlspecialchars($order['user_name']); ?></td>
                                                <td><?php echo htmlspecialchars($order['company_name']); ?></td>
                                                <td class="text-end">¥<?php echo number_format($order['total_amount']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center p-4 text-muted">
                                <i class="bi bi-inbox fs-1"></i>
                                <p>最近の注文データがありません</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header bg-smiley-green text-white">
                        <h5 class="mb-0"><i class="bi bi-graph-up"></i> 月別売上推移</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="revenueChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- システム状況 -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="text-center">
                    <a href="pages/system_health.php" class="btn btn-outline-smiley">
                        <i class="bi bi-gear"></i> システム健全性チェック
                    </a>
                    <a href="pages/users.php" class="btn btn-outline-smiley ms-2">
                        <i class="bi bi-people"></i> 利用者管理
                    </a>
                    <a href="pages/departments.php" class="btn btn-outline-smiley ms-2">
                        <i class="bi bi-diagram-3"></i> 部署管理
                    </a>
                </div>
            </div>
        </div>

        <!-- フッター -->
        <div class="text-center mt-5 pt-4 border-top">
            <p class="text-muted mb-0">
                <strong>Smiley配食事業 請求書管理システム v1.0.0</strong><br>
                © 2025 Smiley配食事業. All rights reserved.
            </p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // 月別売上チャート
        <?php if (!empty($stats['monthly_revenue'])): ?>
        const revenueData = <?php echo json_encode($stats['monthly_revenue']); ?>;
        const ctx = document.getElementById('revenueChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: revenueData.map(item => item.month),
                datasets: [{
                    label: '売上金額',
                    data: revenueData.map(item => item.revenue),
                    borderColor: '#2E8B57',
                    backgroundColor: 'rgba(46, 139, 87, 0.1)',
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
        <?php else: ?>
        document.getElementById('revenueChart').getContext('2d').fillText('データがありません', 50, 50);
        <?php endif; ?>

        // エラー表示（デバッグモード時）
        <?php if (isset($stats['error']) && DEBUG_MODE): ?>
        console.error('Dashboard Error:', <?php echo json_encode($stats['error']); ?>);
        <?php endif; ?>
    </script>
</body>
</html>
