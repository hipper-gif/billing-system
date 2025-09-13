<?php
/**
 * index.php - Smiley配食事業システム 集金管理特化版ダッシュボード
 * マテリアルデザイン統一版・PC操作不慣れ対応・集金業務最適化
 * 最終更新: 2025年9月11日
 */

// セキュリティ・基本設定
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'config/database.php';
require_once 'classes/Database.php';

// PaymentManagerクラスが存在する場合のみ読み込み
$paymentManagerAvailable = false;
if (file_exists('classes/PaymentManager.php')) {
    require_once 'classes/PaymentManager.php';
    $paymentManagerAvailable = true;
}

// データベース接続
try {
    $db = Database::getInstance();
    $dbAvailable = true;
} catch (Exception $e) {
    $dbAvailable = false;
    $dbError = $e->getMessage();
}

// 基本統計データの初期化
$totalSales = 0;
$outstandingAmount = 0;
$outstandingCount = 0;
$alertCount = 0;
$totalCompanies = 0;
$totalUsers = 0;
$urgentCollections = [];
$trendData = [];
$methodData = [];

// データ取得処理（エラーハンドリング付き）
if ($dbAvailable) {
    try {
        // 基本統計データ取得
        $stmt = $db->query("SELECT COUNT(*) as count FROM companies WHERE is_active = 1");
        $totalCompanies = $stmt->fetch()['count'] ?? 0;
        
        $stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE is_active = 1");
        $totalUsers = $stmt->fetch()['count'] ?? 0;
        
        // 今月の売上計算
        $stmt = $db->query("SELECT SUM(total_amount) as total FROM orders WHERE MONTH(delivery_date) = MONTH(CURDATE()) AND YEAR(delivery_date) = YEAR(CURDATE())");
        $totalSales = $stmt->fetch()['total'] ?? 0;
        
        // 未回収金額計算（請求済み・未払い）
        $stmt = $db->query("SELECT COUNT(*) as count, SUM(total_amount) as total FROM invoices WHERE status = 'issued'");
        $result = $stmt->fetch();
        $outstandingCount = $result['count'] ?? 0;
        $outstandingAmount = $result['total'] ?? 0;
        
        // 期限切れアラート数
        $stmt = $db->query("SELECT COUNT(*) as count FROM invoices WHERE status = 'issued' AND due_date < CURDATE()");
        $alertCount = $stmt->fetch()['count'] ?? 0;
        
        // 緊急回収リスト（期限切れ・高額未回収）
        $stmt = $db->query("
            SELECT i.*, c.company_name, 
                   DATEDIFF(CURDATE(), i.due_date) as overdue_days,
                   (i.total_amount * 0.7 + DATEDIFF(CURDATE(), i.due_date) * 0.3) as priority_score
            FROM invoices i 
            LEFT JOIN companies c ON i.company_id = c.id 
            WHERE i.status = 'issued' AND i.due_date < CURDATE()
            ORDER BY priority_score DESC 
            LIMIT 5
        ");
        $urgentCollections = $stmt->fetchAll();
        
        // 月別売上推移（過去6ヶ月）
        $stmt = $db->query("
            SELECT DATE_FORMAT(delivery_date, '%Y-%m') as month, 
                   SUM(total_amount) as monthly_amount 
            FROM orders 
            WHERE delivery_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
            GROUP BY DATE_FORMAT(delivery_date, '%Y-%m')
            ORDER BY month
        ");
        $trendData = $stmt->fetchAll();
        
        // 支払方法別データ（実際のデータがある場合）
        $stmt = $db->query("
            SELECT payment_method, SUM(amount) as total_amount 
            FROM payments 
            WHERE payment_date >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)
            GROUP BY payment_method
        ");
        $methodData = $stmt->fetchAll();
        
    } catch (Exception $e) {
        $dataError = $e->getMessage();
    }
}

// PaymentManagerが利用可能な場合の処理
if ($paymentManagerAvailable) {
    try {
        $paymentManager = new PaymentManager();
        
        // PaymentManagerのメソッドが存在する場合のみ実行
        if (method_exists($paymentManager, 'getPaymentStatistics')) {
            $statistics = $paymentManager->getPaymentStatistics('month');
            // 既存データを上書き
            if (!empty($statistics['summary'])) {
                $totalSales = $statistics['summary']['total_amount'] ?? $totalSales;
                $outstandingAmount = $statistics['summary']['outstanding_amount'] ?? $outstandingAmount;
                $outstandingCount = $statistics['summary']['outstanding_count'] ?? $outstandingCount;
            }
        }
        
        if (method_exists($paymentManager, 'getPaymentAlerts')) {
            $alerts = $paymentManager->getPaymentAlerts();
            $alertCount = $alerts['alert_count'] ?? $alertCount;
        }
        
    } catch (Exception $e) {
        // PaymentManagerエラーは無視して基本データを使用
    }
}

// Chart.js用のデータ準備
$monthLabels = json_encode(array_column($trendData, 'month'));
$monthAmounts = json_encode(array_column($trendData, 'monthly_amount'));

// 支払方法のラベル変換
$paymentMethods = [
    'cash' => '現金',
    'bank_transfer' => '銀行振込',
    'account_debit' => '口座引落',
    'paypay' => 'PayPay',
    'mixed' => '混合',
    'other' => 'その他'
];

$methodLabels = json_encode(array_map(function($item) use ($paymentMethods) {
    return $paymentMethods[$item['payment_method']] ?? $item['payment_method'];
}, $methodData));
$methodAmounts = json_encode(array_column($methodData, 'total_amount'));

// 回収優先度の計算
$collectionPriority = [];
foreach ($urgentCollections as $collection) {
    $priority = 'high';
    if ($collection['total_amount'] >= 100000 && $collection['overdue_days'] >= 30) {
        $priority = 'critical';
    } elseif ($collection['total_amount'] >= 50000 || $collection['overdue_days'] >= 14) {
        $priority = 'high';
    } else {
        $priority = 'medium';
    }
    $collection['priority'] = $priority;
    $collectionPriority[] = $collection;
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smiley配食事業システム - 集金管理ダッシュボード</title>
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    
    <style>
        /* マテリアルデザイン基本設定 */
        :root {
            --primary-blue: #2196F3;
            --primary-green: #4CAF50;
            --success-green: #4CAF50;
            --warning-amber: #FFC107;
            --error-red: #F44336;
            --info-blue: #2196F3;
            --surface-white: #FFFFFF;
            --text-dark: #212121;
            --text-secondary: #757575;
            --divider-grey: #E0E0E0;
            --spacing-xs: 4px;
            --spacing-sm: 8px;
            --spacing-md: 16px;
            --spacing-lg: 24px;
            --spacing-xl: 32px;
            --spacing-xxl: 48px;
            --radius-normal: 8px;
            --radius-large: 16px;
            --elevation-1: 0 2px 4px rgba(0,0,0,0.1);
            --elevation-2: 0 4px 8px rgba(0,0,0,0.12);
            --elevation-3: 0 8px 16px rgba(0,0,0,0.14);
            --transition-normal: 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --body-large: 16px;
            --body-small: 14px;
        }
        
        body {
            font-family: 'Roboto', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: #FAFAFA;
            color: var(--text-dark);
            line-height: 1.6;
        }
        
        /* 集金管理特化レイアウト */
        .collection-container {
            padding: var(--spacing-lg);
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .urgent-collection-section {
            background: linear-gradient(135deg, var(--error-red), #E91E63);
            color: white;
            border-radius: var(--radius-large);
            padding: var(--spacing-xl);
            margin-bottom: var(--spacing-lg);
            box-shadow: var(--elevation-2);
        }
        
        .quick-payment-section {
            background: linear-gradient(135deg, var(--primary-green), #8BC34A);
            color: white;
            border-radius: var(--radius-large);
            padding: var(--spacing-xl);
            margin-bottom: var(--spacing-lg);
            box-shadow: var(--elevation-2);
        }
        
        .collection-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: var(--spacing-lg);
            margin-bottom: var(--spacing-lg);
        }
        
        .collection-stat-card {
            background: var(--surface-white);
            border-radius: var(--radius-normal);
            padding: var(--spacing-lg);
            box-shadow: var(--elevation-1);
            transition: all var(--transition-normal);
            border-left: 6px solid transparent;
        }
        
        .collection-stat-card:hover {
            box-shadow: var(--elevation-2);
            transform: translateY(-2px);
        }
        
        .collection-stat-card.critical { border-left-color: var(--error-red); }
        .collection-stat-card.warning { border-left-color: var(--warning-amber); }
        .collection-stat-card.success { border-left-color: var(--success-green); }
        .collection-stat-card.info { border-left-color: var(--info-blue); }
        
        .stat-icon {
            font-size: 3rem;
            margin-bottom: var(--spacing-md);
        }
        
        .stat-value {
            font-size: 2.5rem;
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
        
        /* 集金業務特化ボタン */
        .collection-action-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: var(--spacing-md);
            margin-bottom: var(--spacing-lg);
        }
        
        .collection-action-card {
            background: var(--surface-white);
            border-radius: var(--radius-normal);
            padding: var(--spacing-xl);
            box-shadow: var(--elevation-1);
            text-align: center;
            transition: all var(--transition-normal);
            text-decoration: none;
            color: var(--text-dark);
            position: relative;
            overflow: hidden;
        }
        
        .collection-action-card:hover {
            box-shadow: var(--elevation-3);
            transform: translateY(-4px);
            text-decoration: none;
            color: var(--text-dark);
        }
        
        .collection-action-card.urgent {
            background: linear-gradient(135deg, #FF5722, var(--error-red));
            color: white;
        }
        
        .collection-action-card.urgent:hover {
            color: white;
        }
        
        .action-icon {
            font-size: 4rem;
            margin-bottom: var(--spacing-md);
        }
        
        .action-title {
            font-size: 1.5rem;
            font-weight: 500;
            margin-bottom: var(--spacing-sm);
        }
        
        .action-description {
            color: var(--text-secondary);
            margin-bottom: var(--spacing-lg);
            font-size: var(--body-small);
        }
        
        .collection-action-card.urgent .action-description {
            color: rgba(255, 255, 255, 0.9);
        }
        
        /* 緊急回収リスト */
        .urgent-collection-list {
            background: var(--surface-white);
            border-radius: var(--radius-normal);
            padding: var(--spacing-lg);
            box-shadow: var(--elevation-1);
            margin-bottom: var(--spacing-lg);
        }
        
        .collection-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: var(--spacing-md);
            border-radius: var(--radius-normal);
            margin-bottom: var(--spacing-sm);
            transition: all var(--transition-normal);
        }
        
        .collection-item:hover {
            background: #F5F5F5;
        }
        
        .collection-item.critical {
            background: #FFEBEE;
            border-left: 4px solid var(--error-red);
        }
        
        .collection-item.high {
            background: #FFF3E0;
            border-left: 4px solid var(--warning-amber);
        }
        
        .collection-item.medium {
            background: #E8F5E8;
            border-left: 4px solid var(--success-green);
        }
        
        .collection-company {
            font-weight: 500;
            font-size: 1.1rem;
        }
        
        .collection-amount {
            font-weight: 700;
            font-size: 1.2rem;
        }
        
        .collection-overdue {
            color: var(--error-red);
            font-weight: 500;
            font-size: var(--body-small);
        }
        
        /* 巨大入金ボタン */
        .mega-payment-button {
            background: linear-gradient(135deg, var(--success-green), #8BC34A);
            color: white;
            border: none;
            border-radius: var(--radius-large);
            padding: var(--spacing-xl) var(--spacing-xxl);
            font-size: 1.5rem;
            font-weight: 700;
            box-shadow: var(--elevation-3);
            transition: all var(--transition-normal);
            min-height: 100px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: var(--spacing-md);
        }
        
        .mega-payment-button:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px rgba(0,0,0,0.2);
            color: white;
        }
        
        /* チャート部分 */
        .chart-container {
            background: var(--surface-white);
            border-radius: var(--radius-normal);
            padding: var(--spacing-lg);
            box-shadow: var(--elevation-1);
            margin-bottom: var(--spacing-lg);
        }
        
        /* エラー状態表示 */
        .system-status {
            background: var(--surface-white);
            border-radius: var(--radius-normal);
            padding: var(--spacing-lg);
            box-shadow: var(--elevation-1);
            margin-bottom: var(--spacing-lg);
        }
        
        .status-error {
            background: #FFEBEE;
            border-left: 4px solid var(--error-red);
        }
        
        .status-warning {
            background: #FFF3E0;
            border-left: 4px solid var(--warning-amber);
        }
        
        /* レスポンシブ対応 */
        @media (max-width: 768px) {
            .collection-container {
                padding: var(--spacing-md);
            }
            
            .urgent-collection-section,
            .quick-payment-section {
                padding: var(--spacing-lg);
                text-align: center;
            }
            
            .collection-stats-grid {
                grid-template-columns: 1fr;
                gap: var(--spacing-md);
            }
            
            .collection-action-grid {
                grid-template-columns: 1fr;
                gap: var(--spacing-sm);
            }
            
            .collection-item {
                flex-direction: column;
                text-align: center;
                gap: var(--spacing-sm);
            }
            
            .mega-payment-button {
                font-size: 1.2rem;
                padding: var(--spacing-lg);
                min-height: 80px;
            }
        }
        
        /* アニメーション */
        .animate-fade-in {
            animation: fadeIn 0.6s ease-out;
        }
        
        .animate-slide-up {
            animation: slideUp 0.8s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(40px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* 数値アニメーション用 */
        .counter {
            display: inline-block;
        }
        
        /* フローティングアクションボタン */
        .fab {
            position: fixed;
            bottom: 24px;
            right: 24px;
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: var(--primary-blue);
            color: white;
            border: none;
            box-shadow: var(--elevation-3);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            transition: all var(--transition-normal);
            z-index: 1000;
        }
        
        .fab:hover {
            transform: scale(1.1);
            box-shadow: 0 8px 16px rgba(0,0,0,0.2);
        }
    </style>
</head>
<body>
    <!-- メインナビゲーション -->
    <nav class="navbar navbar-expand-lg" style="background: var(--primary-blue); color: white; box-shadow: var(--elevation-2);">
        <div class="container-fluid" style="max-width: 1400px;">
            <a class="navbar-brand d-flex align-items-center" href="index.php" style="color: white;">
                <span class="material-icons me-2" style="font-size: 2rem;">account_balance_wallet</span>
                <span style="font-weight: 500; font-size: 1.25rem;">Smiley集金管理システム</span>
            </a>
            
            <!-- ナビゲーションメニュー -->
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link text-white" href="pages/csv_import.php">
                            <span class="material-icons me-1">file_upload</span>データ取込
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="pages/invoice_generate.php">
                            <span class="material-icons me-1">receipt_long</span>請求書生成
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="pages/payments.php">
                            <span class="material-icons me-1">payments</span>入金管理
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="pages/receipts.php">
                            <span class="material-icons me-1">local_printshop</span>領収書
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="pages/companies.php">
                            <span class="material-icons me-1">business</span>企業管理
                        </a>
                    </li>
                </ul>
            </div>
            
            <div class="d-flex align-items-center">
                <?php if ($alertCount > 0): ?>
                <div class="me-3">
                    <span class="material-icons text-warning me-1">notifications</span>
                    <span class="badge bg-warning text-dark"><?php echo $alertCount; ?></span>
                </div>
                <?php endif; ?>
                
                <span class="text-small opacity-75">
                    <?php echo date('Y年m月d日 H:i'); ?>
                </span>
            </div>
        </div>
    </nav>

    <!-- メインコンテンツ -->
    <div class="collection-container">
        
        <!-- システム状態表示 -->
        <?php if (!$dbAvailable): ?>
        <div class="system-status status-error animate-fade-in">
            <h4 class="text-danger mb-2">
                <span class="material-icons me-2">error</span>
                データベース接続エラー
            </h4>
            <p class="mb-0">データベースに接続できません。システム管理者にお問い合わせください。</p>
            <small class="text-secondary">エラー詳細: <?php echo htmlspecialchars($dbError ?? ''); ?></small>
        </div>
        <?php elseif (!$paymentManagerAvailable): ?>
        <div class="system-status status-warning animate-fade-in">
            <h4 class="text-warning mb-2">
                <span class="material-icons me-2">warning</span>
                支払い管理機能準備中
            </h4>
            <p class="mb-0">PaymentManagerクラスが見つかりません。基本データで表示しています。</p>
        </div>
        <?php endif; ?>

        <!-- 緊急回収セクション -->
        <?php if (!empty($collectionPriority)): ?>
        <div class="urgent-collection-section animate-fade-in">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 style="font-size: 2.5rem; font-weight: 300; margin-bottom: var(--spacing-md);">
                        <span class="material-icons me-2" style="font-size: 2.5rem; vertical-align: middle;">priority_high</span>
                        緊急回収アラート
                    </h1>
                    <p style="font-size: var(--body-large); opacity: 0.9; margin: 0;">
                        期限切れ・高額未回収案件があります。早急な対応が必要です。
                    </p>
                </div>
                <div class="col-md-4 text-md-end text-center">
                    <div style="font-size: 3rem; font-weight: 700;">
                        <?php echo count($collectionPriority); ?>件
                    </div>
                    <div style="opacity: 0.9;">対応必要</div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- クイック入金処理セクション -->
        <div class="quick-payment-section animate-fade-in">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h2 style="font-size: 2rem; font-weight: 300; margin-bottom: var(--spacing-md);">
                        <span class="material-icons me-2" style="font-size: 2rem; vertical-align: middle;">flash_on</span>
                        クイック入金処理
                    </h2>
                    <p style="font-size: var(--body-large); opacity: 0.9; margin: 0;">
                        入金があった場合は、こちらから即座に記録できます
                    </p>
                </div>
                <div class="col-md-4 text-md-end text-center">
                    <button class="mega-payment-button" onclick="quickPaymentEntry()">
                        <span class="material-icons" style="font-size: 2rem;">add_circle</span>
                        今すぐ入金記録
                    </button>
                </div>
            </div>
        </div>

        <!-- 集金統計サマリー -->
        <div class="collection-stats-grid">
            <!-- 今月売上 -->
            <div class="collection-stat-card success animate-fade-in">
                <div class="stat-icon text-success">
                    <span class="material-icons">trending_up</span>
                </div>
                <div class="stat-value text-success counter" data-target="<?php echo $totalSales; ?>">
                    ¥<?php echo number_format($totalSales); ?>
                </div>
                <div class="stat-label">今月の売上</div>
                <small class="text-secondary">
                    <span class="material-icons" style="font-size: 0.875rem;">business</span>
                    <?php echo $totalCompanies; ?>社・<?php echo $totalUsers; ?>名の利用者
                </small>
            </div>

            <!-- 未回収金額 -->
            <div class="collection-stat-card <?php echo $outstandingAmount > 0 ? 'critical' : 'info'; ?> animate-fade-in">
                <div class="stat-icon <?php echo $outstandingAmount > 0 ? 'text-danger' : 'text-info'; ?>">
                    <span class="material-icons">account_balance_wallet</span>
                </div>
                <div class="stat-value <?php echo $outstandingAmount > 0 ? 'text-danger' : 'text-info'; ?> counter" data-target="<?php echo $outstandingAmount; ?>">
                    ¥<?php echo number_format($outstandingAmount); ?>
                </div>
                <div class="stat-label">未回収金額</div>
                <small class="text-secondary">
                    <?php echo $outstandingCount; ?>件の未払い請求書
                </small>
            </div>

            <!-- 期限切れアラート -->
            <div class="collection-stat-card <?php echo $alertCount > 0 ? 'warning' : 'success'; ?> animate-fade-in">
                <div class="stat-icon <?php echo $alertCount > 0 ? 'text-warning' : 'text-success'; ?>">
                    <span class="material-icons">
                        <?php echo $alertCount > 0 ? 'schedule' : 'check_circle'; ?>
                    </span>
                </div>
                <div class="stat-value <?php echo $alertCount > 0 ? 'text-warning' : 'text-success'; ?> counter" data-target="<?php echo $alertCount; ?>">
                    <?php echo $alertCount; ?>
                </div>
                <div class="stat-label">期限切れ件数</div>
                <small class="text-secondary">
                    <?php echo $alertCount > 0 ? '回収対応が必要です' : '期限内で管理されています'; ?>
                </small>
            </div>

            <!-- 回収効率 -->
            <div class="collection-stat-card info animate-fade-in">
                <div class="stat-icon text-info">
                    <span class="material-icons">donut_large</span>
                </div>
                <div class="stat-value text-info">
                    <?php 
                    $totalInvoiced = $totalSales + $outstandingAmount;
                    $collectionRate = $totalInvoiced > 0 ? round(($totalSales / $totalInvoiced) * 100, 1) : 100;
                    echo $collectionRate;
                    ?>%
                </div>
                <div class="stat-label">今月の回収率</div>
                <small class="text-secondary">
                    回収済み¥<?php echo number_format($totalSales); ?> / 総請求額¥<?php echo number_format($totalInvoiced); ?>
                </small>
            </div>
        </div>

        <!-- 緊急回収リスト -->
        <?php if (!empty($collectionPriority)): ?>
        <div class="urgent-collection-list animate-slide-up">
            <h3 class="mb-3">
                <span class="material-icons me-2 text-danger">warning</span>
                緊急回収リスト（優先度順）
            </h3>
            
            <?php foreach ($collectionPriority as $collection): ?>
            <div class="collection-item <?php echo $collection['priority']; ?>">
                <div class="d-flex flex-column flex-md-row align-items-md-center w-100">
                    <div class="flex-grow-1">
                        <div class="collection-company">
                            <?php echo htmlspecialchars($collection['company_name'] ?? '企業名不明'); ?>
                        </div>
                        <div class="collection-overdue">
                            期限切れ <?php echo $collection['overdue_days']; ?>日経過
                            (期限: <?php echo date('Y/m/d', strtotime($collection['due_date'])); ?>)
                        </div>
                    </div>
                    <div class="text-md-end">
                        <div class="collection-amount text-danger">
                            ¥<?php echo number_format($collection['total_amount']); ?>
                        </div>
                        <div class="mt-2">
                            <button class="btn btn-danger btn-sm me-2" onclick="recordPayment(<?php echo $collection['id']; ?>, <?php echo $collection['total_amount']; ?>)">
                                <span class="material-icons" style="font-size: 1rem;">payment</span>
                                入金記録
                            </button>
                            <button class="btn btn-outline-secondary btn-sm" onclick="contactCompany('<?php echo htmlspecialchars($collection['company_name']); ?>')">
                                <span class="material-icons" style="font-size: 1rem;">call</span>
                                連絡
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            
            <div class="text-center mt-3">
                <a href="pages/payments.php" class="btn btn-primary btn-lg">
                    <span class="material-icons me-2">list</span>
                    全ての未回収を確認
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- 集金業務アクション -->
        <div class="mb-4">
            <h2 class="mb-3">
                <span class="material-icons me-2">task_alt</span>
                集金業務メニュー
            </h2>
        </div>

<div class="mega-button">
    <a href="pages/bulk_payment_list.php" class="btn btn-warning btn-lg">
        <i class="material-icons">account_balance_wallet</i>
        月末締め - 満額入金リスト
    </a>
</div>

            <!-- 一部入金・分割対応 -->
            <a href="pages/payments.php?mode=partial" class="collection-action-card animate-fade-in">
                <div class="action-icon text-warning">
                    <span class="material-icons">pie_chart</span>
                </div>
                <div class="action-title">一部入金・分割</div>
                <div class="action-description">
                    部分入金・分割支払いの柔軟対応
                </div>
                <div class="btn btn-warning btn-lg">
                    <span class="material-icons me-1">account_balance</span>
                    分割管理
                </div>
            </a>

            <!-- 今月の請求書作成 -->
            <a href="pages/invoice_generate.php" class="collection-action-card animate-fade-in">
                <div class="action-icon text-success">
                    <span class="material-icons">receipt_long</span>
                </div>
                <div class="action-title">今月の請求書作成</div>
                <div class="action-description">
                    月次請求書の一括生成・PDF出力
                </div>
                <div class="btn btn-success btn-lg">
                    <span class="material-icons me-1">create</span>
                    請求書作成
                </div>
            </a>

            <!-- 領収書発行 -->
            <a href="pages/receipts.php" class="collection-action-card animate-fade-in">
                <div class="action-icon text-info">
                    <span class="material-icons">local_printshop</span>
                </div>
                <div class="action-title">領収書発行</div>
                <div class="action-description">
                    事前・事後領収書の作成・印刷
                </div>
                <div class="btn btn-info btn-lg">
                    <span class="material-icons me-1">print</span>
                    領収書作成
                </div>
            </a>

            <!-- 督促・連絡管理 -->
            <a href="pages/payments.php?mode=reminder" class="collection-action-card animate-fade-in">
                <div class="action-icon text-primary">
                    <span class="material-icons">campaign</span>
                </div>
                <div class="action-title">督促・連絡管理</div>
                <div class="action-description">
                    支払督促・企業連絡の履歴管理
                </div>
                <div class="btn btn-primary btn-lg">
                    <span class="material-icons me-1">contact_phone</span>
                    督促管理
                </div>
            </a>

            <!-- CSVデータ取込 -->
            <a href="pages/csv_import.php" class="collection-action-card animate-fade-in">
                <div class="action-icon" style="color: var(--text-secondary);">
                    <span class="material-icons">file_upload</span>
                </div>
                <div class="action-title">CSVデータ取込</div>
                <div class="action-description">
                    注文データの一括インポート
                </div>
                <div class="btn btn-outline-secondary btn-lg">
                    <span class="material-icons me-1">upload</span>
                    データ取込
                </div>
            </a>
        </div>

        <!-- 統計・分析セクション -->
        <div class="row">
            <div class="col-lg-8 mb-4">
                <div class="chart-container animate-fade-in">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h3 class="mb-0">
                            <span class="material-icons me-2">trending_up</span>
                            売上・回収推移
                        </h3>
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-outline-primary btn-sm active">月別</button>
                            <button type="button" class="btn btn-outline-primary btn-sm">週別</button>
                        </div>
                    </div>
                    <canvas id="salesTrendChart" height="300"></canvas>
                </div>
            </div>

            <div class="col-lg-4 mb-4">
                <div class="chart-container animate-fade-in">
                    <h3 class="mb-3">
                        <span class="material-icons me-2">pie_chart</span>
                        支払方法別割合
                    </h3>
                    <canvas id="paymentMethodChart" height="300"></canvas>
                    
                    <!-- 支払方法の詳細 -->
                    <div class="mt-3">
                        <?php if (!empty($methodData)): ?>
                            <?php foreach ($methodData as $method): ?>
                            <?php
                            $methodName = $paymentMethods[$method['payment_method']] ?? $method['payment_method'];
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
                        <?php else: ?>
                            <div class="text-center text-secondary py-3">
                                <span class="material-icons mb-2" style="font-size: 2rem;">analytics</span>
                                <div>支払データが記録されると<br>ここに詳細が表示されます</div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- 操作ガイド（PC操作不慣れ対応） -->
        <div class="chart-container animate-fade-in">
            <div class="row">
                <div class="col-12 mb-3">
                    <h3>
                        <span class="material-icons me-2 text-info">help_outline</span>
                        集金業務の流れ
                    </h3>
                </div>
                <div class="col-md-6">
                    <h4 class="h6 text-primary mb-2">
                        <span class="material-icons me-1" style="font-size: 1rem;">looks_one</span>
                        入金があった場合
                    </h4>
                    <ol class="text-small">
                        <li>「今すぐ入金記録」ボタンをクリック</li>
                        <li>企業名・金額・支払方法を選択</li>
                        <li>「記録する」ボタンで完了</li>
                        <li>必要に応じて領収書を発行</li>
                    </ol>
                </div>
                <div class="col-md-6">
                    <h4 class="h6 text-primary mb-2">
                        <span class="material-icons me-1" style="font-size: 1rem;">looks_two</span>
                        期限切れ対応
                    </h4>
                    <ol class="text-small">
                        <li>緊急回収リストで優先企業を確認</li>
                        <li>「連絡」ボタンで督促電話</li>
                        <li>入金確認後「入金記録」ボタン</li>
                        <li>一部入金の場合は分割機能を使用</li>
                    </ol>
                </div>
            </div>
            
            <div class="row mt-3">
                <div class="col-md-6">
                    <h4 class="h6 text-primary mb-2">
                        <span class="material-icons me-1" style="font-size: 1rem;">looks_3</span>
                        月次作業
                    </h4>
                    <ol class="text-small">
                        <li>CSVデータを取り込み</li>
                        <li>「今月の請求書作成」で一括生成</li>
                        <li>請求書をPDFで出力・送付</li>
                        <li>入金確認・記録を継続</li>
                    </ol>
                </div>
                <div class="col-md-6">
                    <h4 class="h6 text-success mb-2">
                        <span class="material-icons me-1" style="font-size: 1rem;">support_agent</span>
                        困ったときは
                    </h4>
                    <ul class="text-small">
                        <li>🔴赤いボタン：緊急・重要な操作</li>
                        <li>🟢緑のボタン：安全・通常の操作</li>
                        <li>🟡黄のボタン：注意が必要な操作</li>
                        <li>大きなボタンから優先的に操作</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- クイック入金モーダル -->
    <div class="modal fade" id="quickPaymentModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <span class="material-icons me-2">payment</span>
                        クイック入金記録
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="quickPaymentForm">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">企業名</label>
                                <select class="form-select form-select-lg" id="companySelect" required>
                                    <option value="">企業を選択してください</option>
                                    <?php if ($dbAvailable): ?>
                                        <?php
                                        try {
                                            $stmt = $db->query("SELECT id, company_name FROM companies WHERE is_active = 1 ORDER BY company_name");
                                            while ($company = $stmt->fetch()) {
                                                echo '<option value="' . $company['id'] . '">' . htmlspecialchars($company['company_name']) . '</option>';
                                            }
                                        } catch (Exception $e) {
                                            echo '<option value="">データ取得エラー</option>';
                                        }
                                        ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">入金金額</label>
                                <div class="input-group">
                                    <span class="input-group-text">¥</span>
                                    <input type="number" class="form-control form-control-lg" id="paymentAmount" required min="1">
                                </div>
                                <div class="mt-2">
                                    <button type="button" class="btn btn-outline-secondary btn-sm me-1" onclick="setAmount(10000)">1万円</button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm me-1" onclick="setAmount(50000)">5万円</button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setAmount(100000)">10万円</button>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">支払方法</label>
                                <div class="d-grid gap-2">
                                    <div class="btn-group" role="group">
                                        <input type="radio" class="btn-check" name="paymentMethod" id="cash" value="cash">
                                        <label class="btn btn-outline-success" for="cash">
                                            💵 現金
                                        </label>
                                        
                                        <input type="radio" class="btn-check" name="paymentMethod" id="bank" value="bank_transfer">
                                        <label class="btn btn-outline-primary" for="bank">
                                            🏦 振込
                                        </label>
                                        
                                        <input type="radio" class="btn-check" name="paymentMethod" id="paypay" value="paypay">
                                        <label class="btn btn-outline-warning" for="paypay">
                                            📱 PayPay
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">入金日</label>
                                <input type="date" class="form-control form-control-lg" id="paymentDate" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">備考</label>
                            <textarea class="form-control" id="paymentNotes" rows="2" placeholder="特記事項があれば入力してください"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
                    <button type="button" class="btn btn-success btn-lg" onclick="submitQuickPayment()">
                        <span class="material-icons me-2">save</span>
                        入金を記録する
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- フローティングアクションボタン -->
    <button class="fab" onclick="quickPaymentEntry()">
        <span class="material-icons">add</span>
    </button>

    <!-- フッター -->
    <footer class="text-center py-4 mt-5" style="background: var(--surface-white); border-top: 1px solid var(--divider-grey);">
        <div class="container">
            <p class="text-secondary mb-2">
                <span class="material-icons me-1" style="font-size: 1rem;">account_balance_wallet</span>
                Smiley配食事業 集金管理システム v2.0
            </p>
            <p class="text-small text-secondary mb-0">
                © 2025 Smiley Kitchen. All rights reserved. | 
                最終更新: <?php echo date('Y年m月d日 H:i'); ?>
            </p>
        </div>
    </footer>

    <!-- JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
        // マテリアルデザイン色設定
        const materialColors = {
            primary: '#2196F3',
            success: '#4CAF50',
            warning: '#FFC107',
            error: '#F44336',
            info: '#2196F3'
        };

        // 売上推移チャート
        <?php if (!empty($trendData)): ?>
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
        <?php else: ?>
        // データがない場合のプレースホルダー
        const salesTrendCtx = document.getElementById('salesTrendChart').getContext('2d');
        salesTrendCtx.fillStyle = '#E0E0E0';
        salesTrendCtx.fillRect(0, 0, salesTrendCtx.canvas.width, salesTrendCtx.canvas.height);
        salesTrendCtx.fillStyle = '#757575';
        salesTrendCtx.font = '16px Roboto';
        salesTrendCtx.textAlign = 'center';
        salesTrendCtx.fillText('データを蓄積中...', salesTrendCtx.canvas.width / 2, salesTrendCtx.canvas.height / 2);
        <?php endif; ?>

        // 支払方法別円グラフ
        <?php if (!empty($methodData)): ?>
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
        <?php else: ?>
        // データがない場合のプレースホルダー
        const paymentMethodCtx = document.getElementById('paymentMethodChart').getContext('2d');
        paymentMethodCtx.fillStyle = '#E0E0E0';
        paymentMethodCtx.fillRect(0, 0, paymentMethodCtx.canvas.width, paymentMethodCtx.canvas.height);
        paymentMethodCtx.fillStyle = '#757575';
        paymentMethodCtx.font = '14px Roboto';
        paymentMethodCtx.textAlign = 'center';
        paymentMethodCtx.fillText('支払データ待機中', paymentMethodCtx.canvas.width / 2, paymentMethodCtx.canvas.height / 2);
        <?php endif; ?>

        // クイック入金機能
        function quickPaymentEntry() {
            const modal = new bootstrap.Modal(document.getElementById('quickPaymentModal'));
            modal.show();
        }

        function setAmount(amount) {
            document.getElementById('paymentAmount').value = amount;
        }

        function recordPayment(invoiceId, amount) {
            // 特定請求書の入金記録
            document.getElementById('paymentAmount').value = amount;
            quickPaymentEntry();
            // 隠しフィールドで請求書IDを設定（実装時）
        }

        function contactCompany(companyName) {
            alert('企業連絡機能\n\n対象: ' + companyName + '\n\n※実際の運用では電話番号やメール機能と連携します');
        }

        function submitQuickPayment() {
            const form = document.getElementById('quickPaymentForm');
            const company = document.getElementById('companySelect').value;
            const amount = document.getElementById('paymentAmount').value;
            const method = document.querySelector('input[name="paymentMethod"]:checked');
            const date = document.getElementById('paymentDate').value;
            
            if (!company || !amount || !method || !date) {
                alert('必須項目を全て入力してください');
                return;
            }
            
            // 実際の実装では AJAX でサーバーに送信
            const paymentData = {
                company_id: company,
                amount: amount,
                payment_method: method.value,
                payment_date: date,
                notes: document.getElementById('paymentNotes').value
            };
            
            // デモ用アラート
            alert('入金記録完了\n\n' + 
                  '企業: ' + document.getElementById('companySelect').selectedOptions[0].text + '\n' +
                  '金額: ¥' + parseInt(amount).toLocaleString() + '\n' +
                  '方法: ' + method.nextElementSibling.textContent.trim() + '\n' +
                  '日付: ' + date);
            
            // モーダルを閉じる
            bootstrap.Modal.getInstance(document.getElementById('quickPaymentModal')).hide();
            
            // 実際の実装では画面リロードまたは部分更新
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        }

        // ページ読み込み時のアニメーション
        document.addEventListener('DOMContentLoaded', function() {
            // カードのスタガーアニメーション
            const cards = document.querySelectorAll('.animate-fade-in');
            cards.forEach((card, index) => {
                card.style.animationDelay = (index * 0.1) + 's';
            });

            // 統計数値のカウントアップアニメーション
            const counters = document.querySelectorAll('.counter');
            counters.forEach(counter => {
                const target = parseInt(counter.getAttribute('data-target')) || 0;
                animateNumber(counter, target);
            });
        });

        // 数値アニメーション関数
        function animateNumber(element, finalValue, duration = 1500) {
            let startValue = 0;
            const increment = finalValue / (duration / 16);
            
            function updateNumber() {
                startValue += increment;
                if (startValue < finalValue) {
                    const currentValue = Math.floor(startValue);
                    const formattedValue = element.textContent.includes('¥') ? 
                        '¥' + currentValue.toLocaleString() : 
                        element.textContent.includes('%') ?
                        currentValue + '%' :
                        currentValue.toString();
                    element.textContent = formattedValue;
                    requestAnimationFrame(updateNumber);
                } else {
                    const finalFormattedValue = element.textContent.includes('¥') ? 
                        '¥' + finalValue.toLocaleString() : 
                        element.textContent.includes('%') ?
                        finalValue + '%' :
                        finalValue.toString();
                    element.textContent = finalFormattedValue;
                }
            }
            
            if (finalValue > 0) {
                updateNumber();
            }
        }

        // レスポンシブ対応
        window.addEventListener('resize', function() {
            // チャートのリサイズは Chart.js が自動対応
        });

        // エラーハンドリング
        window.addEventListener('error', function(e) {
            console.error('JavaScript エラー:', e.error);
        });

        // オフライン対応
        window.addEventListener('offline', function() {
            document.body.insertAdjacentHTML('afterbegin', 
                '<div class="alert alert-warning text-center mb-0">' +
                '<span class="material-icons me-2">wifi_off</span>' +
                'オフライン状態です。一部機能が制限されます。' +
                '</div>');
        });

        window.addEventListener('online', function() {
            const offlineAlert = document.querySelector('.alert-warning');
            if (offlineAlert) {
                offlineAlert.remove();
            }
        });
    </script>
</body>
</html>
