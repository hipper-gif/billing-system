<?php
/**
 * 企業詳細画面
 * Smiley配食事業専用 - 企業の詳細情報表示・編集
 */

require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/SecurityHelper.php';

// セキュリティヘッダー設定
SecurityHelper::setSecurityHeaders();

$company_id = intval($_GET['id'] ?? 0);

if (!$company_id) {
    header('Location: companies.php?error=企業IDが指定されていません');
    exit;
}

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    
    // 企業詳細情報を取得
    $company_sql = "
        SELECT 
            c.*,
            COUNT(DISTINCT d.id) as department_count,
            COUNT(DISTINCT u.id) as user_count,
            COUNT(DISTINCT o.id) as total_order_count,
            COALESCE(SUM(o.total_amount), 0) as total_revenue,
            MAX(o.delivery_date) as last_order_date,
            MIN(o.delivery_date) as first_order_date
        FROM companies c
        LEFT JOIN departments d ON c.id = d.company_id
        LEFT JOIN users u ON c.id = u.company_id
        LEFT JOIN orders o ON c.id = o.company_id
        WHERE c.id = :company_id
        GROUP BY c.id
    ";
    
    $stmt = $pdo->prepare($company_sql);
    $stmt->bindValue(':company_id', $company_id, PDO::PARAM_INT);
    $stmt->execute();
    $company = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$company) {
        header('Location: companies.php?error=指定された企業が見つかりません');
        exit;
    }
    
    // 部署一覧取得
    $departments_sql = "
        SELECT 
            d.*,
            COUNT(DISTINCT u.id) as user_count,
            COUNT(DISTINCT o.id) as order_count,
            COALESCE(SUM(o.total_amount), 0) as total_amount
        FROM departments d
        LEFT JOIN users u ON d.id = u.department_id
        LEFT JOIN orders o ON d.id = o.department_id
        WHERE d.company_id = :company_id
        GROUP BY d.id
        ORDER BY d.department_name
    ";
    
    $dept_stmt = $pdo->prepare($departments_sql);
    $dept_stmt->bindValue(':company_id', $company_id, PDO::PARAM_INT);
    $dept_stmt->execute();
    $departments = $dept_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 最近の注文履歴取得（最新20件）
    $orders_sql = "
        SELECT 
            o.delivery_date,
            o.user_name,
            o.product_name,
            o.quantity,
            o.total_amount,
            o.department_name
        FROM orders o
        WHERE o.company_id = :company_id
        ORDER BY o.delivery_date DESC, o.id DESC
        LIMIT 20
    ";
    
    $orders_stmt = $pdo->prepare($orders_sql);
    $orders_stmt->bindValue(':company_id', $company_id, PDO::PARAM_INT);
    $orders_stmt->execute();
    $recent_orders = $orders_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 月別売上統計（過去12ヶ月）
    $monthly_stats_sql = "
        SELECT 
            DATE_FORMAT(delivery_date, '%Y-%m') as month,
            COUNT(*) as order_count,
            SUM(total_amount) as total_amount
        FROM orders
        WHERE company_id = :company_id
        AND delivery_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(delivery_date, '%Y-%m')
        ORDER BY month DESC
    ";
    
    $monthly_stmt = $pdo->prepare($monthly_stats_sql);
    $monthly_stmt->bindValue(':company_id', $company_id, PDO::PARAM_INT);
    $monthly_stmt->execute();
    $monthly_stats = $monthly_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("企業詳細画面エラー: " . $e->getMessage());
    header('Location: companies.php?error=データの取得中にエラーが発生しました');
    exit;
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($company['company_name']) ?> - 企業詳細 - Smiley配食事業</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --smiley-green: #2E8B57;
            --smiley-light-green: #90EE90;
            --smiley-dark-green: #006400;
        }
        
        .navbar-brand {
            color: var(--smiley-green) !important;
            font-weight: bold;
        }
        
        .btn-smiley {
            background-color: var(--smiley-green);
            border-color: var(--smiley-green);
            color: white;
        }
        
        .btn-smiley:hover {
            background-color: var(--smiley-dark-green);
            border-color: var(--smiley-dark-green);
            color: white;
        }
        
        .card-header {
            background-color: var(--smiley-green);
            color: white;
        }
        
        .stats-card {
            border-left: 4px solid var(--smiley-green);
        }
        
        .status-active {
            color: var(--smiley-green);
        }
        
        .status-inactive {
            color: #dc3545;
        }
        
        .amount-highlight {
            font-weight: bold;
            color: var(--smiley-dark-green);
        }
        
        .company-header {
            background: linear-gradient(135deg, var(--smiley-green), var(--smiley-dark-green));
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <!-- ナビゲーション -->
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <div class="container">
            <a class="navbar-brand" href="../index.php">
                <i class="bi bi-house-heart"></i> Smiley配食事業
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="../pages/csv_import.php">CSVインポート</a>
                <a class="nav-link active" href="../pages/companies.php">配達先企業</a>
                <a class="nav-link" href="../pages/departments.php">部署管理</a>
                <a class="nav-link" href="../pages/users.php">利用者管理</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- パンくずリスト -->
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="../index.php">ホーム</a></li>
                <li class="breadcrumb-item"><a href="companies.php">配達先企業</a></li>
                <li class="breadcrumb-item active"><?= htmlspecialchars($company['company_name']) ?></li>
            </ol>
        </nav>

        <!-- 企業ヘッダー -->
        <div class="company-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h2 class="mb-2">
                        <i class="bi bi-building"></i> <?= htmlspecialchars($company['company_name']) ?>
                        <?php if ($company['is_active']): ?>
                            <span class="badge bg-success ms-2">アクティブ</span>
                        <?php else: ?>
                            <span class="badge bg-danger ms-2">非アクティブ</span>
                        <?php endif; ?>
                    </h2>
                    <p class="mb-1">
                        <strong>企業コード:</strong> <code><?= htmlspecialchars($company['company_code']) ?></code>
                    </p>
                    <?php if ($company['company_address']): ?>
                        <p class="mb-1">
                            <i class="bi bi-geo-alt"></i> <?= htmlspecialchars($company['company_address']) ?>
                        </p>
                    <?php endif; ?>
                    <?php if ($company['contact_person']): ?>
                        <p class="mb-0">
                            <i class="bi bi-person"></i> 担当者: <?= htmlspecialchars($company['contact_person']) ?>
                            <?php if ($company['contact_phone']): ?>
                                | <i class="bi bi-telephone"></i> <?= htmlspecialchars($company['contact_phone']) ?>
                            <?php endif; ?>
                            <?php if ($company['contact_email']): ?>
                                | <i class="bi bi-envelope"></i> <?= htmlspecialchars($company['contact_email']) ?>
                            <?php endif; ?>
                        </p>
                    <?php endif; ?>
                </div>
                <div class="col-md-4 text-end">
                    <button class="btn btn-light me-2" onclick="editCompany()">
                        <i class="bi bi-pencil"></i> 編集
                    </button>
                    <button class="btn btn-warning" onclick="generateInvoice()">
                        <i class="bi bi-receipt"></i> 請求書生成
                    </button>
                </div>
            </div>
        </div>

        <!-- 統計サマリー -->
        <div class="row mb-4">
            <div class="col-md-2">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <h3 class="text-info"><?= number_format($company['department_count']) ?></h3>
                        <small class="text-muted">部署数</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <h3 class="text-success"><?= number_format($company['user_count']) ?></h3>
                        <small class="text-muted">利用者数</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <h3 class="text-warning"><?= number_format($company['total_order_count']) ?></h3>
                        <small class="text-muted">総注文数</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <h3 class="amount-highlight">¥<?= number_format($company['total_revenue']) ?></h3>
                        <small class="text-muted">総売上</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <h5 class="text-primary">
                            <?= $company['last_order_date'] ? date('Y/m/d', strtotime($company['last_order_date'])) : '-' ?>
                        </h5>
                        <small class="text-muted">最終注文日</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- 左側コラム -->
            <div class="col-md-8">
                <!-- 月別売上チャート -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="bi bi-graph-up"></i> 月別売上推移（過去12ヶ月）
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="monthlyChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- 最近の注文履歴 -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-clock-history"></i> 最近の注文履歴</span>
                        <a href="orders.php?company_id=<?= $company['id'] ?>" class="btn btn-sm btn-outline-light">
                            全履歴を見る
                        </a>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($recent_orders)): ?>
                            <div class="p-4 text-center text-muted">
                                <i class="bi bi-inbox" style="font-size: 2rem;"></i>
                                <p class="mt-2">注文履歴がありません。</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>配達日</th>
                                            <th>利用者</th>
                                            <th>メニュー</th>
                                            <th>数量</th>
                                            <th>金額</th>
                                            <th>部署</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_orders as $order): ?>
                                            <tr>
                                                <td><?= date('m/d', strtotime($order['delivery_date'])) ?></td>
                                                <td><?= htmlspecialchars($order['user_name']) ?></td>
                                                <td><?= htmlspecialchars($order['product_name']) ?></td>
                                                <td><?= number_format($order['quantity']) ?></td>
                                                <td class="amount-highlight">¥<?= number_format($order['total_amount']) ?></td>
                                                <td>
                                                    <small class="text-muted"><?= htmlspecialchars($order['department_name'] ?? '-') ?></small>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
