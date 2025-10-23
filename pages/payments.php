<?php
// ✅ 修正版: classes/Database.php の重複読み込み問題を解決
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/PaymentManager.php';
require_once __DIR__ . '/../classes/InvoiceGenerator.php';

// セキュリティヘッダー
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

$pageTitle = '支払い管理センター - 入金記録・未回収管理';

try {
    $paymentManager = new PaymentManager();
    $invoiceGenerator = new InvoiceGenerator();
    
    // フィルター設定
    $filters = [
        'date_from' => $_GET['date_from'] ?? date('Y-m-01'), // 今月初日
        'date_to' => $_GET['date_to'] ?? date('Y-m-t'),      // 今月末日
        'payment_method' => $_GET['payment_method'] ?? '',
        'company_id' => $_GET['company_id'] ?? '',
        'invoice_status' => $_GET['invoice_status'] ?? '',
        'search' => $_GET['search'] ?? '',
        'view_type' => $_GET['view_type'] ?? 'payments', // payments, outstanding, alerts
        'page' => intval($_GET['page'] ?? 1),
        'limit' => 20
    ];

    // データ取得
    switch ($filters['view_type']) {
        case 'outstanding':
            $data = $paymentManager->getOutstandingAmounts($filters);
            break;
        case 'alerts':
            $data = $paymentManager->getPaymentAlerts();
            break;
        default:
            $data = $paymentManager->getPaymentsList($filters);
            break;
    }
    
    // 統計データを取得
    $statistics = $paymentManager->getPaymentStatistics('current_month');
    $alerts = $paymentManager->getPaymentAlerts();
    
    // 企業リストを取得（フィルター用）
    // ✅ 修正版: 正しい Database インスタンス取得方法
    $db = Database::getInstance();
    $companies = $db->fetchAll("SELECT id, company_name FROM companies WHERE is_active = 1 ORDER BY company_name");
    
    // 処理メッセージ
    $message = '';
    $messageType = '';
    
    // POST処理（入金記録など）
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'record_payment':
                $paymentData = [
                    'payment_date' => $_POST['payment_date'],
                    'amount' => floatval($_POST['amount']),
                    'payment_method' => $_POST['payment_method'],
                    'reference_number' => $_POST['reference_number'] ?? '',
                    'notes' => $_POST['notes'] ?? '',
                    'auto_generate_receipt' => isset($_POST['auto_generate_receipt'])
                ];
                
                $result = $paymentManager->recordPayment($_POST['invoice_id'], $paymentData);
                $message = $result['message'];
                $messageType = $result['success'] ? 'success' : 'danger';
                break;
                
            case 'cancel_payment':
                $result = $paymentManager->cancelPayment($_POST['payment_id'], $_POST['reason'] ?? '');
                $message = $result['message'];
                $messageType = $result['success'] ? 'success' : 'danger';
                break;
        }
        
        // POST後リダイレクト
        if ($message) {
            header("Location: payments.php?" . http_build_query(array_merge($filters, ['msg' => base64_encode($message), 'type' => $messageType])));
            exit;
        }
    }
    
    // GETメッセージ表示
    if (isset($_GET['msg'])) {
        $message = base64_decode($_GET['msg']);
        $messageType = $_GET['type'] ?? 'info';
    }

} catch (Exception $e) {
    error_log("支払い管理画面エラー: " . $e->getMessage());
    $error = "データの取得に失敗しました: " . $e->getMessage();
    $data = [];
    $statistics = [];
    $alerts = [];
    $companies = [];
}

// 表示用関数
function formatCurrency($amount) {
    return number_format($amount ?? 0) . '円';
}

function formatDate($date) {
    return date('Y/m/d', strtotime($date));
}

function getPaymentMethodText($method) {
    $methods = [
        'cash' => '現金',
        'bank_transfer' => '銀行振込',
        'account_debit' => '口座引落',
        'other' => 'その他'
    ];
    return $methods[$method] ?? $method;
}

function getPriorityBadge($priority) {
    $badges = [
        'overdue' => 'badge bg-danger',
        'urgent' => 'badge bg-warning',
        'warning' => 'badge bg-info',
        'normal' => 'badge bg-secondary'
    ];
    return $badges[$priority] ?? 'badge bg-secondary';
}

function getPriorityText($priority) {
    $texts = [
        'overdue' => '期限切れ',
        'urgent' => '緊急',
        'warning' => '注意',
        'normal' => '通常'
    ];
    return $texts[$priority] ?? $priority;
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
    <style>
        .payment-card {
            transition: all 0.3s ease;
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .payment-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }
        
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
        }
        
        .alert-card {
            border-left: 4px solid;
            border-radius: 0;
        }
        
        .alert-card.alert-danger-custom {
            border-left-color: #dc3545;
            background-color: #f8d7da;
        }
        
        .alert-card.alert-warning-custom {
            border-left-color: #ffc107;
            background-color: #fff3cd;
        }
        
        .alert-card.alert-info-custom {
            border-left-color: #0dcaf0;
            background-color: #d1ecf1;
        }
        
        .action-button {
            min-height: 45px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .action-button:hover {
            transform: translateY(-2px);
        }
        
        .table-responsive {
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        
        .modal-content {
            border-radius: 15px;
        }
        
        .tab-content {
            margin-top: 20px;
        }
        
        .nav-tabs .nav-link {
            border-radius: 10px 10px 0 0;
            margin-right: 5px;
            border: none;
            background: #f8f9fa;
        }
        
        .nav-tabs .nav-link.active {
            background: #007bff;
            color: white;
        }
        
        .filter-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .outstanding-item {
            border-left: 5px solid;
            margin-bottom: 10px;
            padding: 15px;
            border-radius: 5px;
        }
        
        .outstanding-item.overdue {
            border-left-color: #dc3545;
            background-color: #f8d7da;
        }
        
        .outstanding-item.urgent {
            border-left-color: #ffc107;
            background-color: #fff3cd;
        }
        
        .outstanding-item.warning {
            border-left-color: #0dcaf0;
            background-color: #d1ecf1;
        }
        
        .outstanding-item.normal {
            border-left-color: #28a745;
            background-color: #d4edda;
        }
    </style>
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container-fluid">
        <a class="navbar-brand" href="#">
            <i class="fas fa-money-check-alt me-2"></i>
            支払い管理センター
        </a>
        <div class="navbar-nav ms-auto">
            <a class="nav-link" href="../dashboard.php">
                <i class="fas fa-tachometer-alt me-1"></i>ダッシュボード
            </a>
            <a class="nav-link" href="../collection_flow.php">
                <i class="fas fa-route me-1"></i>フローガイド
            </a>
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

    <?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
        <i class="fas fa-info-circle me-2"></i>
        <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- ヘッダー -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-0 text-primary">
                        <i class="fas fa-money-check-alt me-2"></i>
                        支払い管理センター
                    </h1>
                    <p class="text-muted mb-0">入金記録・未回収管理・督促アラート</p>
                </div>
                <div>
                    <button class="btn btn-success btn-lg" data-bs-toggle="modal" data-bs-target="#paymentModal">
                        <i class="fas fa-plus me-2"></i>
                        入金を記録
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- 統計カード -->
    <div class="row g-3 mb-4">
        <div class="col-lg-3 col-md-6">
            <div class="card payment-card stats-card">
                <div class="card-body text-center">
                    <i class="fas fa-yen-sign fa-3x mb-3 opacity-75"></i>
                    <h5 class="card-title">今月の入金額</h5>
                    <h2 class="mb-0"><?php echo formatCurrency($statistics['total_amount'] ?? 0); ?></h2>
                    <small class="opacity-75"><?php echo ($statistics['total_payments'] ?? 0); ?>件の入金</small>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6">
            <div class="card payment-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white;">
                <div class="card-body text-center">
                    <i class="fas fa-exclamation-triangle fa-3x mb-3 opacity-75"></i>
                    <h5 class="card-title">未回収金額</h5>
                    <h2 class="mb-0"><?php echo formatCurrency($statistics['outstanding_amount'] ?? 0); ?></h2>
                    <small class="opacity-75"><?php echo ($statistics['outstanding_invoices'] ?? 0); ?>件の未払い</small>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6">
            <div class="card payment-card" style="background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%); color: #333;">
                <div class="card-body text-center">
                    <i class="fas fa-clock fa-3x mb-3 opacity-75"></i>
                    <h5 class="card-title">期限切れ</h5>
                    <h2 class="mb-0"><?php echo ($alerts['overdue']['count'] ?? 0); ?>件</h2>
                    <small class="opacity-75"><?php echo formatCurrency($alerts['overdue']['total_amount'] ?? 0); ?></small>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6">
            <div class="card payment-card" style="background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%); color: #333;">
                <div class="card-body text-center">
                    <i class="fas fa-percentage fa-3x mb-3 opacity-75"></i>
                    <h5 class="card-title">回収率</h5>
                    <h2 class="mb-0">
                        <?php 
                        $totalInvoiced = ($statistics['total_amount'] ?? 0) + ($statistics['outstanding_amount'] ?? 0);
                        $collectionRate = $totalInvoiced > 0 ? (($statistics['total_amount'] ?? 0) / $totalInvoiced * 100) : 0;
                        echo number_format($collectionRate, 1) . '%';
                        ?>
                    </h2>
                    <small class="opacity-75">今月の実績</small>
                </div>
            </div>
        </div>
    </div>

    <!-- アラート表示 -->
    <?php if (!empty($alerts)): ?>
    <div class="row mb-4">
        <?php if (($alerts['overdue']['count'] ?? 0) > 0): ?>
        <div class="col-md-4">
            <div class="alert-card alert-danger-custom p-3">
                <h6 class="fw-bold text-danger">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    期限切れ請求書
                </h6>
                <div class="h4 mb-2"><?php echo $alerts['overdue']['count']; ?>件</div>
                <div class="small"><?php echo formatCurrency($alerts['overdue']['total_amount']); ?></div>
                <a href="?view_type=outstanding&priority=overdue" class="btn btn-outline-danger btn-sm mt-2">
                    詳細を確認
                </a>
            </div>
        </div>
        <?php endif; ?>

        <?php if (($alerts['due_soon']['count'] ?? 0) > 0): ?>
        <div class="col-md-4">
            <div class="alert-card alert-warning-custom p-3">
                <h6 class="fw-bold text-warning">
                    <i class="fas fa-clock me-2"></i>
                    期限間近（3日以内）
                </h6>
                <div class="h4 mb-2"><?php echo $alerts['due_soon']['count']; ?>件</div>
                <div class="small"><?php echo formatCurrency($alerts['due_soon']['total_amount']); ?></div>
                <a href="?view_type=outstanding&priority=urgent" class="btn btn-outline-warning btn-sm mt-2">
                    詳細を確認
                </a>
            </div>
        </div>
        <?php endif; ?>

        <?php if (($alerts['large_amount']['count'] ?? 0) > 0): ?>
        <div class="col-md-4">
            <div class="alert-card alert-info-custom p-3">
                <h6 class="fw-bold text-info">
                    <i class="fas fa-money-bill-wave me-2"></i>
                    高額未回収（5万円以上）
                </h6>
                <div class="h4 mb-2"><?php echo $alerts['large_amount']['count']; ?>件</div>
                <div class="small"><?php echo formatCurrency($alerts['large_amount']['total_amount']); ?></div>
                <a href="?view_type=outstanding&large_amount=1" class="btn btn-outline-info btn-sm mt-2">
                    詳細を確認
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- フィルター・タブ -->
    <div class="row mb-4">
        <div class="col-12">
            <ul class="nav nav-tabs" id="paymentTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <a class="nav-link <?php echo $filters['view_type'] === 'payments' ? 'active' : ''; ?>" 
                       href="?<?php echo http_build_query(array_merge($filters, ['view_type' => 'payments', 'page' => 1])); ?>">
                        <i class="fas fa-list me-2"></i>入金履歴
                    </a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link <?php echo $filters['view_type'] === 'outstanding' ? 'active' : ''; ?>" 
                       href="?<?php echo http_build_query(array_merge($filters, ['view_type' => 'outstanding', 'page' => 1])); ?>">
                        <i class="fas fa-exclamation-triangle me-2"></i>未回収管理
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <!-- フィルターセクション -->
    <div class="filter-section">
        <form method="GET" class="row g-3">
            <input type="hidden" name="view_type" value="<?php echo htmlspecialchars($filters['view_type']); ?>">
            
            <div class="col-md-2">
                <label for="date_from" class="form-label">開始日</label>
                <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo htmlspecialchars($filters['date_from']); ?>">
            </div>
            
            <div class="col-md-2">
                <label for="date_to" class="form-label">終了日</label>
                <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo htmlspecialchars($filters['date_to']); ?>">
            </div>
            
            <div class="col-md-2">
                <label for="company_id" class="form-label">企業</label>
                <select class="form-select" id="company_id" name="company_id">
                    <option value="">全企業</option>
                    <?php foreach ($companies as $company): ?>
                    <option value="<?php echo $company['id']; ?>" <?php echo $filters['company_id'] == $company['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($company['company_name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <?php if ($filters['view_type'] === 'payments'): ?>
            <div class="col-md-2">
                <label for="payment_method" class="form-label">支払方法</label>
                <select class="form-select" id="payment_method" name="payment_method">
                    <option value="">全方法</option>
                    <option value="cash" <?php echo $filters['payment_method'] === 'cash' ? 'selected' : ''; ?>>現金</option>
                    <option value="bank_transfer" <?php echo $filters['payment_method'] === 'bank_transfer' ? 'selected' : ''; ?>>銀行振込</option>
                    <option value="account_debit" <?php echo $filters['payment_method'] === 'account_debit' ? 'selected' : ''; ?>>口座引落</option>
                    <option value="other" <?php echo $filters['payment_method'] === 'other' ? 'selected' : ''; ?>>その他</option>
                </select>
            </div>
            <?php endif; ?>
            
            <div class="col-md-2">
                <label for="search" class="form-label">検索</label>
                <input type="text" class="form-control" id="search" name="search" 
                       placeholder="企業名・利用者名・請求書番号" value="<?php echo htmlspecialchars($filters['search']); ?>">
            </div>
            
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-2"></i>検索
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- データ表示エリア -->
    <div class="card">
        <div class="card-header bg-white">
            <h5 class="card-title mb-0">
                <i class="fas fa-list me-2"></i>
                <?php echo $filters['view_type'] === 'payments' ? '入金履歴一覧' : '未回収金額管理'; ?>
            </h5>
        </div>
        <div class="card-body p-0">
            <?php if (!empty($data)): ?>
                <div class="text-center py-3">
                    <p class="text-muted">データ表示機能は実装中です</p>
                    <small>取得データ件数: <?php echo count($data); ?>件</small>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">データが見つかりません</h5>
                    <p class="text-muted">検索条件を変更してお試しください。</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// JavaScriptは省略（必要に応じて追加）
console.log('✅ 修正版 payments.php 動作開始');
console.log('Database.php 重複問題解決済み');
</script>

</body>
</html>
