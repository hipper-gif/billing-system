<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/classes/Database.php';

// セキュリティヘッダー
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

$pageTitle = 'Smiley配食事業 - 集金管理システム';

try {
    $db = Database::getInstance();
    
    // 現在日付を取得
    $currentDate = date('Y-m-d');
    $currentMonth = date('Y-m');
    $currentMonthDisplay = date('Y年n月');
    
    // 今月の売上統計
    $sql = "SELECT 
                COUNT(*) as total_orders,
                SUM(total_amount) as total_sales,
                COUNT(DISTINCT user_code) as unique_users,
                COUNT(DISTINCT company_name) as unique_companies
            FROM orders 
            WHERE DATE_FORMAT(order_date, '%Y-%m') = ?";
    $monthlyStats = $db->fetchOne($sql, [$currentMonth]);
    
    // 今月の日別売上推移
    $sql = "SELECT 
                DATE_FORMAT(order_date, '%d') as day,
                SUM(total_amount) as daily_sales,
                COUNT(*) as daily_orders
            FROM orders 
            WHERE DATE_FORMAT(order_date, '%Y-%m') = ?
            GROUP BY DATE_FORMAT(order_date, '%d')
            ORDER BY day";
    $dailySales = $db->fetchAll($sql, [$currentMonth]);
    
    // 企業別売上ランキング
    $sql = "SELECT 
                company_name,
                SUM(total_amount) as company_sales,
                COUNT(*) as company_orders
            FROM orders 
            WHERE DATE_FORMAT(order_date, '%Y-%m') = ?
            GROUP BY company_name
            ORDER BY company_sales DESC
            LIMIT 5";
    $companyRanking = $db->fetchAll($sql, [$currentMonth]);
    
    // 商品別売上ランキング
    $sql = "SELECT 
                product_name,
                SUM(total_amount) as product_sales,
                SUM(quantity) as total_quantity
            FROM orders 
            WHERE DATE_FORMAT(order_date, '%Y-%m') = ?
            GROUP BY product_name
            ORDER BY product_sales DESC
            LIMIT 5";
    $productRanking = $db->fetchAll($sql, [$currentMonth]);
    
    // 請求書関連統計
    $sql = "SELECT 
                COUNT(*) as total_invoices,
                COUNT(CASE WHEN status = 'issued' THEN 1 END) as issued_invoices,
                COUNT(CASE WHEN status = 'paid' THEN 1 END) as paid_invoices,
                COUNT(CASE WHEN status = 'overdue' THEN 1 END) as overdue_invoices,
                SUM(CASE WHEN status != 'paid' THEN total_amount ELSE 0 END) as outstanding_amount
            FROM invoices";
    $invoiceStats = $db->fetchOne($sql);
    
    // 支払い期限が近い請求書
    $sql = "SELECT 
                i.invoice_number,
                i.due_date,
                i.total_amount,
                u.user_name,
                c.company_name,
                DATEDIFF(i.due_date, CURDATE()) as days_left
            FROM invoices i
            LEFT JOIN users u ON i.user_id = u.id
            LEFT JOIN companies c ON u.company_id = c.id
            WHERE i.status = 'issued' 
            AND i.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
            ORDER BY i.due_date ASC
            LIMIT 10";
    $upcomingDues = $db->fetchAll($sql);
    
    // システム健全性チェック
    $systemHealth = [
        'database' => true,
        'tables_count' => 0,
        'data_integrity' => true
    ];
    
    // テーブル数をチェック
    $tablesResult = $db->fetchAll("SHOW TABLES");
    $systemHealth['tables_count'] = count($tablesResult);
    
    // データ整合性チェック（簡易）
    $integrityCheck = $db->fetchOne("SELECT COUNT(*) as count FROM orders WHERE user_code IS NULL OR user_code = ''");
    $systemHealth['data_integrity'] = $integrityCheck['count'] == 0;

} catch (Exception $e) {
    error_log("メインページ読み込みエラー: " . $e->getMessage());
    $error = "システムの初期化に失敗しました。管理者にお問い合わせください。";
    
    // 初期値を設定
    $monthlyStats = [
        'total_orders' => 0,
        'total_sales' => 0,
        'unique_users' => 0,
        'unique_companies' => 0
    ];
    $dailySales = [];
    $companyRanking = [];
    $productRanking = [];
    $invoiceStats = [
        'total_invoices' => 0,
        'issued_invoices' => 0,
        'paid_invoices' => 0,
        'overdue_invoices' => 0,
        'outstanding_amount' => 0
    ];
    $upcomingDues = [];
    $systemHealth = [
        'database' => false,
        'tables_count' => 0,
        'data_integrity' => false
    ];
}

// 表示用関数
function formatCurrency($amount) {
    return number_format($amount ?? 0) . '円';
}

function formatDate($date) {
    return date('m/d', strtotime($date));
}

function getBadgeClass($daysLeft) {
    if ($daysLeft <= 1) return 'badge bg-danger';
    if ($daysLeft <= 3) return 'badge bg-warning';
    return 'badge bg-info';
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .dashboard-card {
            transition: transform 0.2s;
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }
        
        .big-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: #2c3e50;
        }
        
        .action-btn {
            min-height: 80px;
            font-size: 1.2rem;
            font-weight: bold;
            transition: all 0.3s;
            border: none;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .action-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 25px rgba(0,0,0,0.2);
        }
        
        .action-btn i {
            font-size: 1.5rem;
            margin-right: 0.5rem;
        }
        
        .bg-gradient-blue {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .bg-gradient-green {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        
        .bg-gradient-orange {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
        }
        
        .bg-gradient-purple {
            background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
        }
        
        .system-health {
            border-left: 4px solid;
            border-radius: 0;
            font-weight: 500;
        }
        
        .system-health.healthy {
            border-left-color: #28a745;
            background-color: #d4edda;
            color: #155724;
        }
        
        .system-health.warning {
            border-left-color: #ffc107;
            background-color: #fff3cd;
            color: #856404;
        }
        
        .system-health.error {
            border-left-color: #dc3545;
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .workflow-section {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .workflow-step {
            text-align: center;
            padding: 1rem;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 1rem;
        }
        
        .workflow-arrow {
            text-align: center;
            font-size: 2rem;
            color: #6c757d;
            margin: 0.5rem 0;
        }
    </style>
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container-fluid">
        <a class="navbar-brand" href="#">
            <i class="fas fa-utensils me-2"></i>
            Smiley配食事業 集金管理システム
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link active" href="#">
                        <i class="fas fa-home me-1"></i>ホーム
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="pages/csv_import.php">
                        <i class="fas fa-upload me-1"></i>CSVインポート
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="pages/invoices.php">
                        <i class="fas fa-file-invoice me-1"></i>請求書管理
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="pages/payments.php">
                        <i class="fas fa-money-bill me-1"></i>支払い管理
                    </a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-cog me-1"></i>管理
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="pages/companies.php"><i class="fas fa-building me-2"></i>企業管理</a></li>
                        <li><a class="dropdown-item" href="pages/users.php"><i class="fas fa-users me-2"></i>利用者管理</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="pages/system_health.php"><i class="fas fa-heartbeat me-2"></i>システム診断</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="container-fluid py-4">
    <?php if (isset($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- システム健全性表示 -->
    <div class="row mb-4">
        <div class="col-12">
            <?php 
            $healthClass = 'healthy';
            $healthIcon = 'fas fa-check-circle';
            $healthMessage = 'システム正常動作中';
            
            if (!$systemHealth['database']) {
                $healthClass = 'error';
                $healthIcon = 'fas fa-exclamation-triangle';
                $healthMessage = 'データベース接続エラー';
            } elseif ($systemHealth['tables_count'] < 10) {
                $healthClass = 'warning';
                $healthIcon = 'fas fa-exclamation-circle';
                $healthMessage = 'テーブル不足（' . $systemHealth['tables_count'] . '個）';
            } elseif (!$systemHealth['data_integrity']) {
                $healthClass = 'warning';
                $healthIcon = 'fas fa-exclamation-circle';
                $healthMessage = 'データ整合性に問題あり';
            }
            ?>
            <div class="system-health <?php echo $healthClass; ?> p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <i class="<?php echo $healthIcon; ?> me-2"></i>
                        <strong><?php echo $healthMessage; ?></strong>
                        <span class="ms-3 small">
                            DB: <?php echo $systemHealth['database'] ? '接続中' : '切断'; ?> | 
                            テーブル: <?php echo $systemHealth['tables_count']; ?>個 | 
                            更新: <?php echo date('H:i:s'); ?>
                        </span>
                    </div>
                    <div>
                        <a href="pages/system_health.php" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-cogs me-1"></i>詳細診断
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 統合ヘッダー -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-0 text-primary">
                        <i class="fas fa-tachometer-alt me-2"></i>
                        集金管理ダッシュボード
                    </h1>
                    <p class="text-muted mb-0"><?php echo $currentMonthDisplay; ?>の業務状況 - PC操作がかんたんな集金管理</p>
                </div>
                <div class="text-end">
                    <div class="text-muted small">最終更新</div>
                    <div class="fw-bold"><?php echo date('Y/m/d H:i'); ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- 主要指標カード -->
    <div class="row g-3 mb-4">
        <div class="col-lg-3 col-md-6">
            <div class="card dashboard-card bg-gradient-blue text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h5 class="card-title mb-0">今月売上</h5>
                            <div class="big-number"><?php echo formatCurrency($monthlyStats['total_sales']); ?></div>
                            <small class="opacity-75">注文件数: <?php echo number_format($monthlyStats['total_orders']); ?>件</small>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-chart-line fa-3x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="card dashboard-card bg-gradient-green text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h5 class="card-title mb-0">未回収金額</h5>
                            <div class="big-number"><?php echo formatCurrency($invoiceStats['outstanding_amount']); ?></div>
                            <small class="opacity-75">未払い請求書: <?php echo $invoiceStats['overdue_invoices']; ?>件</small>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-exclamation-triangle fa-3x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="card dashboard-card bg-gradient-orange text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h5 class="card-title mb-0">アクティブ企業</h5>
                            <div class="big-number"><?php echo $monthlyStats['unique_companies']; ?></div>
                            <small class="opacity-75">利用者: <?php echo $monthlyStats['unique_users']; ?>名</small>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-building fa-3x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="card dashboard-card bg-gradient-purple text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h5 class="card-title mb-0">回収率</h5>
                            <div class="big-number">
                                <?php 
                                $totalInvoiced = $invoiceStats['total_invoices'] > 0 ? 
                                    ($invoiceStats['paid_invoices'] / $invoiceStats['total_invoices'] * 100) : 0;
                                echo number_format($totalInvoiced, 1); 
                                ?>%
                            </div>
                            <small class="opacity-75">回収済: <?php echo $invoiceStats['paid_invoices']; ?>件</small>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-percentage fa-3x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 集金フローワークフロー -->
    <div class="workflow-section">
        <h4 class="text-center mb-4">
            <i class="fas fa-route me-2"></i>
            かんたん集金フロー - 4つのステップで完了！
        </h4>
        <div class="row">
            <div class="col-md-3">
                <div class="workflow-step">
                    <i class="fas fa-upload fa-3x text-primary mb-2"></i>
                    <h6>STEP 1</h6>
                    <p class="small mb-3">CSVデータを取り込む</p>
                    <a href="pages/csv_import.php" class="btn btn-primary btn-sm">
                        <i class="fas fa-upload me-1"></i>インポート開始
                    </a>
                </div>
                <div class="workflow-arrow d-md-none">
                    <i class="fas fa-arrow-down"></i>
                </div>
            </div>
            <div class="col-md-3 d-none d-md-block" style="display: flex; align-items: center; justify-content: center;">
                <i class="fas fa-arrow-right fa-2x text-muted"></i>
            </div>
            <div class="col-md-3">
                <div class="workflow-step">
                    <i class="fas fa-file-invoice fa-3x text-success mb-2"></i>
                    <h6>STEP 2</h6>
                    <p class="small mb-3">請求書を作成する</p>
                    <a href="pages/invoice_generate.php" class="btn btn-success btn-sm">
                        <i class="fas fa-plus me-1"></i>請求書作成
                    </a>
                </div>
                <div class="workflow-arrow d-md-none">
                    <i class="fas fa-arrow-down"></i>
                </div>
            </div>
            <div class="col-md-3 d-none d-md-block" style="display: flex; align-items: center; justify-content: center;">
                <i class="fas fa-arrow-right fa-2x text-muted"></i>
            </div>
        </div>
        <div class="row mt-md-4">
            <div class="col-md-3">
                <div class="workflow-step">
                    <i class="fas fa-money-check-alt fa-3x text-warning mb-2"></i>
                    <h6>STEP 3</h6>
                    <p class="small mb-3">入金を記録する</p>
                    <a href="pages/payments.php" class="btn btn-warning btn-sm">
                        <i class="fas fa-edit me-1"></i>入金記録
                    </a>
                </div>
                <div class="workflow-arrow d-md-none">
                    <i class="fas fa-arrow-down"></i>
                </div>
            </div>
            <div class="col-md-3 d-none d-md-block" style="display: flex; align-items: center; justify-content: center;">
                <i class="fas fa-arrow-right fa-2x text-muted"></i>
            </div>
            <div class="col-md-3">
                <div class="workflow-step">
                    <i class="fas fa-receipt fa-3x text-info mb-2"></i>
                    <h6>STEP 4</h6>
                    <p class="small mb-3">領収書を発行する</p>
                    <a href="pages/receipts.php" class="btn btn-info btn-sm">
                        <i class="fas fa-print me-1"></i>領収書発行
                    </a>
                </div>
            </div>
            <div class="col-md-3 d-none d-md-block" style="display: flex; align-items: center; justify-content: center;">
                <i class="fas fa-check-circle fa-3x text-success"></i>
            </div>
        </div>
    </div>

    <!-- メインアクションボタン -->
    <div class="row g-3 mb-4">
        <div class="col-12">
            <h4 class="mb-3">
                <i class="fas fa-bolt me-2"></i>
                よく使う機能 - 大きなボタンで迷わない操作
            </h4>
        </div>
        <div class="col-lg-4 col-md-6">
            <button class="btn btn-primary action-btn w-100" onclick="window.location.href='pages/invoice_generate.php'">
                <i class="fas fa-file-invoice-dollar"></i>
                今月の請求書を作る
            </button>
        </div>
        <div class="col-lg-4 col-md-6">
            <button class="btn btn-success action-btn w-100" onclick="window.location.href='pages/payments.php'">
                <i class="fas fa-money-check-alt"></i>
                入金を記録する
            </button>
        </div>
        <div class="col-lg-4 col-md-6">
            <button class="btn btn-warning action-btn w-100" onclick="window.location.href='pages/receipts.php'">
                <i class="fas fa-receipt"></i>
                領収書を発行する
            </button>
        </div>
    </div>

    <!-- アラート・重要な情報 -->
    <?php if (!empty($upcomingDues)): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="alert alert-danger border-0">
                <h5 class="alert-heading">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    支払期限が近い請求書があります！
                </h5>
                <div class="row">
                    <?php foreach (array_slice($upcomingDues, 0, 3) as $due): ?>
                    <div class="col-md-4">
                        <strong><?php echo htmlspecialchars($due['company_name']); ?></strong><br>
                        <?php echo htmlspecialchars($due['user_name']); ?><br>
                        <span class="<?php echo getBadgeClass($due['days_left']); ?>">
                            残り<?php echo $due['days_left']; ?>日
                        </span>
                        <?php echo formatCurrency($due['total_amount']); ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <hr>
                <a href="pages/payments.php?filter=upcoming" class="btn btn-outline-danger">
                    <i class="fas fa-list me-2"></i>
                    全ての期限近い請求書を確認する
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- 統計グラフ・詳細データ -->
    <div class="row g-4">
        <!-- 日別売上推移 -->
        <div class="col-lg-8">
            <div class="card dashboard-card">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-chart-area me-2 text-primary"></i>
                        今月の日別売上推移
                    </h5>
                </div>
                <div class="card-body">
                    <div style="position: relative; height: 300px;">
                        <canvas id="dailySalesChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- 企業別売上ランキング -->
        <div class="col-lg-4">
            <div class="card dashboard-card">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-trophy me-2 text-warning"></i>
                        企業別売上TOP5
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($companyRanking)): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($companyRanking as $index => $company): ?>
                        <div class="list-group-item border-0 px-0">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="badge bg-primary me-2"><?php echo $index + 1; ?></span>
                                    <strong><?php echo htmlspecialchars($company['company_name']); ?></strong>
                                    <br>
                                    <small class="text-muted"><?php echo $company['company_orders']; ?>件</small>
                                </div>
                                <div class="text-end">
                                    <strong class="text-success"><?php echo formatCurrency($company['company_sales']); ?></strong>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <p class="text-muted text-center py-3">データがありません</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- 商品別売上ランキング -->
        <div class="col-lg-6">
            <div class="card dashboard-card">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-utensils me-2 text-info"></i>
                        人気商品ランキング
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($productRanking)): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($productRanking as $index => $product): ?>
                        <div class="list-group-item border-0 px-0">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="badge bg-info me-2"><?php echo $index + 1; ?></span>
                                    <strong><?php echo htmlspecialchars($product['product_name']); ?></strong>
                                    <br>
                                    <small class="text-muted">販売数: <?php echo $product['total_quantity']; ?>個</small>
                                </div>
                                <div class="text-end">
                                    <strong class="text-primary"><?php echo formatCurrency($product['product_sales']); ?></strong>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <p class="text-muted text-center py-3">データがありません</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- クイックアクセス -->
        <div class="col-lg-6">
            <div class="card dashboard-card">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-rocket me-2 text-success"></i>
                        クイックアクセス
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-6">
                            <a href="pages/csv_import.php" class="btn btn-outline-primary w-100 py-3">
                                <i class="fas fa-upload d-block mb-1"></i>
                                <small>CSVインポート</small>
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="pages/companies.php" class="btn btn-outline-secondary w-100 py-3">
                                <i class="fas fa-building d-block mb-1"></i>
                                <small>企業管理</small>
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="pages/users.php" class="btn btn-outline-success w-100 py-3">
                                <i class="fas fa-users d-block mb-1"></i>
                                <small>利用者管理</small>
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="pages/system_health.php" class="btn btn-outline-warning w-100 py-3">
                                <i class="fas fa-cogs d-block mb-1"></i>
                                <small>システム診断</small>
                            </a>
                        </div>
                    </div>
                    
                    <hr class="my-3">
                    
                    <!-- 今日のタスク -->
                    <h6 class="text-muted mb-2">
                        <i class="fas fa-tasks me-1"></i>今日のおすすめアクション
                    </h6>
                    <div class="small">
                        <?php if ($monthlyStats['total_orders'] == 0): ?>
                        <div class="alert alert-info alert-sm py-2">
                            <i class="fas fa-upload me-2"></i>
                            まずはCSVデータをインポートしましょう
                        </div>
                        <?php elseif ($invoiceStats['total_invoices'] == 0): ?>
                        <div class="alert alert-warning alert-sm py-2">
                            <i class="fas fa-file-invoice me-2"></i>
                            請求書を生成する時期です
                        </div>
                        <?php elseif ($invoiceStats['outstanding_amount'] > 0): ?>
                        <div class="alert alert-danger alert-sm py-2">
                            <i class="fas fa-money-check me-2"></i>
                            未回収金額の確認をお願いします
                        </div>
                        <?php else: ?>
                        <div class="alert alert-success alert-sm py-2">
                            <i class="fas fa-check-circle me-2"></i>
                            すべて順調です！
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- お知らせ・ヘルプセクション -->
    <div class="row mt-5">
        <div class="col-md-8">
            <div class="card dashboard-card">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-info-circle me-2 text-info"></i>
                        システム利用ガイド
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <h6 class="text-primary">
                                <i class="fas fa-play-circle me-2"></i>
                                はじめての方へ
                            </h6>
                            <ul class="small text-muted list-unstyled">
                                <li class="mb-1">
                                    <i class="fas fa-chevron-right me-2 text-primary"></i>
                                    CSVファイルをインポートして開始
                                </li>
                                <li class="mb-1">
                                    <i class="fas fa-chevron-right me-2 text-primary"></i>
                                    企業・利用者情報の確認
                                </li>
                                <li class="mb-1">
                                    <i class="fas fa-chevron-right me-2 text-primary"></i>
                                    請求書の一括生成
                                </li>
                                <li class="mb-1">
                                    <i class="fas fa-chevron-right me-2 text-primary"></i>
                                    支払い状況の管理
                                </li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-success">
                                <i class="fas fa-lightbulb me-2"></i>
                                便利な使い方
                            </h6>
                            <ul class="small text-muted list-unstyled">
                                <li class="mb-1">
                                    <i class="fas fa-chevron-right me-2 text-success"></i>
                                    大きなボタンで直感的操作
                                </li>
                                <li class="mb-1">
                                    <i class="fas fa-chevron-right me-2 text-success"></i>
                                    色分けでステータス一目確認
                                </li>
                                <li class="mb-1">
                                    <i class="fas fa-chevron-right me-2 text-success"></i>
                                    自動計算で入力ミスを防止
                                </li>
                                <li class="mb-1">
                                    <i class="fas fa-chevron-right me-2 text-success"></i>
                                    PDF出力で印刷・保存可能
                                </li>
                            </ul>
                        </div>
                    </div>
                    <div class="mt-3 pt-3 border-top">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="small text-muted">
                                <i class="fas fa-question-circle me-2"></i>
                                操作でお困りの時は、各画面の「？」ボタンでヘルプを確認できます
                            </span>
                            <a href="pages/system_health.php" class="btn btn-outline-info btn-sm">
                                <i class="fas fa-question me-1"></i>詳細ヘルプ
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card dashboard-card">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-chart-pie me-2 text-warning"></i>
                        システム概要
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-2 text-center">
                        <div class="col-6">
                            <div class="p-2 bg-light rounded">
                                <i class="fas fa-database fa-2x text-primary mb-1"></i>
                                <div class="small">
                                    <strong><?php echo $systemHealth['tables_count']; ?></strong><br>
                                    <span class="text-muted">テーブル</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-2 bg-light rounded">
                                <i class="fas fa-users fa-2x text-success mb-1"></i>
                                <div class="small">
                                    <strong><?php echo $monthlyStats['unique_users']; ?></strong><br>
                                    <span class="text-muted">アクティブユーザー</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-2 bg-light rounded">
                                <i class="fas fa-file-invoice fa-2x text-warning mb-1"></i>
                                <div class="small">
                                    <strong><?php echo $invoiceStats['total_invoices']; ?></strong><br>
                                    <span class="text-muted">請求書</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-2 bg-light rounded">
                                <i class="fas fa-shopping-cart fa-2x text-info mb-1"></i>
                                <div class="small">
                                    <strong><?php echo $monthlyStats['total_orders']; ?></strong><br>
                                    <span class="text-muted">今月注文</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <hr>
                    <div class="text-center">
                        <small class="text-muted">
                            <i class="fas fa-clock me-1"></i>
                            システム稼働開始: <?php echo date('Y年n月'); ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// 日別売上チャート
const dailySalesData = <?php echo json_encode($dailySales); ?>;
const ctx = document.getElementById('dailySalesChart').getContext('2d');

const labels = [];
const salesData = [];
const orderData = [];

// 1日から31日までのラベルを作成
for (let i = 1; i <= 31; i++) {
    labels.push(i + '日');
    const dayData = dailySalesData.find(d => parseInt(d.day) === i);
    salesData.push(dayData ? dayData.daily_sales : 0);
    orderData.push(dayData ? dayData.daily_orders : 0);
}

new Chart(ctx, {
    type: 'line',
    data: {
        labels: labels,
        datasets: [{
            label: '売上金額',
            data: salesData,
            borderColor: 'rgb(54, 162, 235)',
            backgroundColor: 'rgba(54, 162, 235, 0.1)',
            tension: 0.1,
            fill: true,
            yAxisID: 'y'
        }, {
            label: '注文件数',
            data: orderData,
            borderColor: 'rgb(255, 99, 132)',
            backgroundColor: 'rgba(255, 99, 132, 0.1)',
            tension: 0.1,
            fill: false,
            yAxisID: 'y1'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: {
            intersect: false,
        },
        scales: {
            x: {
                display: true,
                title: {
                    display: true,
                    text: '日付'
                }
            },
            y: {
                type: 'linear',
                display: true,
                position: 'left',
                title: {
                    display: true,
                    text: '売上金額 (円)'
                },
                ticks: {
                    callback: function(value) {
                        return value.toLocaleString() + '円';
                    }
                }
            },
            y1: {
                type: 'linear',
                display: true,
                position: 'right',
                title: {
                    display: true,
                    text: '注文件数'
                },
                grid: {
                    drawOnChartArea: false,
                },
            }
        },
        plugins: {
            tooltip: {
                callbacks: {
                    afterLabel: function(context) {
                        if (context.datasetIndex === 0) {
                            return '注文件数: ' + orderData[context.dataIndex] + '件';
                        }
                    },
                    label: function(context) {
                        if (context.datasetIndex === 0) {
                            return context.dataset.label + ': ' + context.raw.toLocaleString() + '円';
                        } else {
                            return context.dataset.label + ': ' + context.raw + '件';
                        }
                    }
                }
            }
        }
    }
});

// ページ読み込み完了時のアニメーション
document.addEventListener('DOMContentLoaded', function() {
    // カードのアニメーション
    const cards = document.querySelectorAll('.dashboard-card');
    cards.forEach((card, index) => {
        setTimeout(() => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            card.style.transition = 'all 0.5s ease';
            setTimeout(() => {
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, 50);
        }, index * 100);
    });

    // ワークフロー ステップのアニメーション
    const steps = document.querySelectorAll('.workflow-step');
    steps.forEach((step, index) => {
        setTimeout(() => {
            step.style.opacity = '0';
            step.style.transform = 'scale(0.9)';
            step.style.transition = 'all 0.4s ease';
            setTimeout(() => {
                step.style.opacity = '1';
                step.style.transform = 'scale(1)';
            }, 50);
        }, index * 150);
    });
});

// アクションボタンのクリック確認
document.querySelectorAll('.action-btn').forEach(button => {
    button.addEventListener('click', function(e) {
        const buttonText = this.textContent.trim();
        if (!confirm(buttonText + 'を開始しますか？')) {
            e.preventDefault();
        }
    });
});

// キーボードショートカット
document.addEventListener('keydown', function(e) {
    // Ctrl + 1-4 でワークフロー機能に直接アクセス
    if (e.ctrlKey && e.key >= '1' && e.key <= '4') {
        e.preventDefault();
        const urls = [
            'pages/csv_import.php',      // Ctrl + 1
            'pages/invoice_generate.php', // Ctrl + 2
            'pages/payments.php',         // Ctrl + 3
            'pages/receipts.php'          // Ctrl + 4
        ];
        window.location.href = urls[parseInt(e.key) - 1];
    }
    
    // Ctrl + H でヘルプ
    if (e.ctrlKey && e.key === 'h') {
        e.preventDefault();
        window.open('pages/system_health.php', '_blank');
    }
});

// 自動リロード機能（30分ごと）
setTimeout(function() {
    location.reload();
}, 30 * 60 * 1000);

// システム健全性の定期チェック（5分ごと）
setInterval(function() {
    fetch('api/health_check.php')
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                console.warn('システム健全性チェックで問題を検出:', data.message);
            }
        })
        .catch(error => {
            console.error('健全性チェックエラー:', error);
        });
}, 5 * 60 * 1000);

// ユーザビリティ向上: フォーカス管理
document.addEventListener('keydown', function(e) {
    // Tab キーでの移動時に視覚的なフィードバックを強化
    if (e.key === 'Tab') {
        document.body.classList.add('keyboard-navigation');
    }
});

document.addEventListener('mousedown', function() {
    document.body.classList.remove('keyboard-navigation');
});

// エラーハンドリング：予期しないエラーをキャッチ
window.addEventListener('error', function(e) {
    console.error('予期しないエラー:', e.error);
    // 重要なエラーの場合は、ユーザーに通知
    if (e.error && e.error.message && e.error.message.includes('database')) {
        alert('データベースに接続できません。システム管理者にお問い合わせください。');
    }
});

// パフォーマンス監視
window.addEventListener('load', function() {
    // ページ読み込み時間を記録
    const loadTime = performance.now();
    if (loadTime > 3000) { // 3秒以上の場合
        console.warn('ページ読み込み時間が長いです:', Math.round(loadTime), 'ms');
    }
});
</script>

</body>
</html>
