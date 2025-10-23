<?php
require_once __DIR__ . '/config/database.php';

// セキュリティヘッダー
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

$pageTitle = '集金フローガイド - ステップ式業務ナビゲーション';

try {
    $db = Database::getInstance();
    
    // 現在の業務状況を取得
    $currentMonth = date('Y-m');
    
    // ステップ1: 今月のCSVインポート状況
    $sql = "SELECT 
                COUNT(DISTINCT DATE(created_at)) as import_days,
                COUNT(*) as total_imports,
                MAX(created_at) as last_import
            FROM orders 
            WHERE DATE_FORMAT(order_date, '%Y-%m') = ?";
    $importStatus = $db->fetchOne($sql, [$currentMonth]);
    
    // ステップ2: 請求書生成状況
    $sql = "SELECT 
                COUNT(*) as total_invoices,
                COUNT(CASE WHEN status = 'draft' THEN 1 END) as draft_invoices,
                COUNT(CASE WHEN status = 'issued' THEN 1 END) as issued_invoices,
                MAX(created_at) as last_generated
            FROM invoices 
            WHERE DATE_FORMAT(period_start, '%Y-%m') = ?";
    $invoiceStatus = $db->fetchOne($sql, [$currentMonth]);
    
    // ステップ3: 支払い管理状況
    $sql = "SELECT 
                COUNT(CASE WHEN i.status = 'issued' THEN 1 END) as pending_payments,
                COUNT(CASE WHEN i.status = 'paid' THEN 1 END) as completed_payments,
                COUNT(CASE WHEN i.status = 'overdue' THEN 1 END) as overdue_payments,
                SUM(CASE WHEN i.status != 'paid' THEN i.total_amount ELSE 0 END) as outstanding_amount
            FROM invoices i
            WHERE DATE_FORMAT(i.period_start, '%Y-%m') = ?";
    $paymentStatus = $db->fetchOne($sql, [$currentMonth]);
    
    // ステップ4: 領収書発行状況
    $sql = "SELECT 
                COUNT(*) as total_receipts,
                COUNT(CASE WHEN receipt_type = 'advance' THEN 1 END) as advance_receipts,
                COUNT(CASE WHEN receipt_type = 'payment' THEN 1 END) as payment_receipts
            FROM receipts 
            WHERE DATE_FORMAT(issue_date, '%Y-%m') = ?";
    $receiptStatus = $db->fetchOne($sql, [$currentMonth]);
    
    // 業務進捗判定
    $progress = [
        'csv_import' => ($importStatus['total_imports'] > 0),
        'invoice_generation' => ($invoiceStatus['total_invoices'] > 0),
        'payment_management' => ($paymentStatus['completed_payments'] > 0),
        'receipt_issue' => ($receiptStatus['total_receipts'] > 0)
    ];
    
    // 次に実行すべきアクション
    $nextAction = '';
    if (!$progress['csv_import']) {
        $nextAction = 'csv_import';
    } elseif (!$progress['invoice_generation']) {
        $nextAction = 'invoice_generation';
    } elseif ($paymentStatus['pending_payments'] > 0) {
        $nextAction = 'payment_management';
    } elseif ($paymentStatus['completed_payments'] > 0 && $receiptStatus['total_receipts'] == 0) {
        $nextAction = 'receipt_issue';
    } else {
        $nextAction = 'completed';
    }

} catch (Exception $e) {
    error_log("集金フローガイド読み込みエラー: " . $e->getMessage());
    $error = "データの取得に失敗しました。";
    
    // 初期値を設定
    $importStatus = ['total_imports' => 0, 'import_days' => 0, 'last_import' => null];
    $invoiceStatus = ['total_invoices' => 0, 'draft_invoices' => 0, 'issued_invoices' => 0, 'last_generated' => null];
    $paymentStatus = ['pending_payments' => 0, 'completed_payments' => 0, 'overdue_payments' => 0, 'outstanding_amount' => 0];
    $receiptStatus = ['total_receipts' => 0, 'advance_receipts' => 0, 'payment_receipts' => 0];
    $progress = ['csv_import' => false, 'invoice_generation' => false, 'payment_management' => false, 'receipt_issue' => false];
    $nextAction = 'csv_import';
}

// ステップ状態判定関数
function getStepStatus($completed, $hasData = false) {
    if ($completed) return 'completed';
    if ($hasData) return 'in_progress';
    return 'pending';
}

function getStepIcon($status) {
    switch ($status) {
        case 'completed': return 'fas fa-check-circle text-success';
        case 'in_progress': return 'fas fa-clock text-warning';
        case 'pending': return 'far fa-circle text-muted';
        default: return 'far fa-circle text-muted';
    }
}

function getStepBadge($status) {
    switch ($status) {
        case 'completed': return 'badge bg-success';
        case 'in_progress': return 'badge bg-warning';
        case 'pending': return 'badge bg-secondary';
        default: return 'badge bg-secondary';
    }
}

// ステップ詳細定義
$steps = [
    'csv_import' => [
        'title' => 'CSVデータインポート',
        'description' => '注文データをシステムに取り込みます',
        'status' => getStepStatus($progress['csv_import'], $importStatus['total_imports'] > 0),
        'url' => 'pages/csv_import.php',
        'button_text' => 'CSVインポートを実行',
        'details' => [
            'インポート日数' => $importStatus['import_days'] . '日',
            '総インポート件数' => $importStatus['total_imports'] . '件',
            '最終インポート' => $importStatus['last_import'] ? date('Y/m/d H:i', strtotime($importStatus['last_import'])) : '未実施'
        ]
    ],
    'invoice_generation' => [
        'title' => '請求書生成',
        'description' => 'インポートしたデータから請求書を作成します',
        'status' => getStepStatus($progress['invoice_generation'], $invoiceStatus['total_invoices'] > 0),
        'url' => 'pages/invoice_generate.php',
        'button_text' => '請求書を生成',
        'details' => [
            '生成済み請求書' => $invoiceStatus['total_invoices'] . '件',
            '下書き' => $invoiceStatus['draft_invoices'] . '件',
            '発行済み' => $invoiceStatus['issued_invoices'] . '件',
            '最終生成' => $invoiceStatus['last_generated'] ? date('Y/m/d H:i', strtotime($invoiceStatus['last_generated'])) : '未実施'
        ]
    ],
    'payment_management' => [
        'title' => '支払い管理・入金記録',
        'description' => '支払い状況を管理し、入金を記録します',
        'status' => getStepStatus($progress['payment_management'], $paymentStatus['pending_payments'] > 0),
        'url' => 'pages/payments.php',
        'button_text' => '支払い管理画面へ',
        'details' => [
            '支払い待ち' => $paymentStatus['pending_payments'] . '件',
            '支払い完了' => $paymentStatus['completed_payments'] . '件',
            '期限超過' => $paymentStatus['overdue_payments'] . '件',
            '未回収金額' => number_format($paymentStatus['outstanding_amount']) . '円'
        ]
    ],
    'receipt_issue' => [
        'title' => '領収書発行',
        'description' => '支払い完了後の領収書を発行します',
        'status' => getStepStatus($progress['receipt_issue'], $receiptStatus['total_receipts'] > 0),
        'url' => 'pages/receipts.php',
        'button_text' => '領収書発行画面へ',
        'details' => [
            '発行済み領収書' => $receiptStatus['total_receipts'] . '件',
            '事前領収書' => $receiptStatus['advance_receipts'] . '件',
            '正式領収書' => $receiptStatus['payment_receipts'] . '件'
        ]
    ]
];

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
        .flow-card {
            transition: all 0.3s ease;
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .flow-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }
        
        .flow-card.completed {
            border-left: 5px solid #28a745;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        }
        
        .flow-card.in_progress {
            border-left: 5px solid #ffc107;
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
        }
        
        .flow-card.pending {
            border-left: 5px solid #6c757d;
            background: #ffffff;
        }
        
        .flow-card.current {
            border-left: 5px solid #007bff;
            background: linear-gradient(135deg, #cce7ff 0%, #e3f2fd 100%);
            transform: scale(1.02);
            box-shadow: 0 6px 25px rgba(0,123,255,0.3);
        }
        
        .step-number {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: bold;
            margin-right: 1rem;
        }
        
        .step-number.completed {
            background: #28a745;
            color: white;
        }
        
        .step-number.in_progress {
            background: #ffc107;
            color: #212529;
        }
        
        .step-number.pending {
            background: #6c757d;
            color: white;
        }
        
        .step-number.current {
            background: #007bff;
            color: white;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(0, 123, 255, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(0, 123, 255, 0); }
            100% { box-shadow: 0 0 0 0 rgba(0, 123, 255, 0); }
        }
        
        .action-button {
            min-height: 60px;
            font-size: 1.1rem;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .action-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        
        .progress-bar-custom {
            height: 8px;
            border-radius: 10px;
            background: linear-gradient(90deg, #28a745 0%, #ffc107 50%, #dc3545 100%);
        }
        
        .detail-item {
            padding: 0.5rem 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .detail-item:last-child {
            border-bottom: none;
        }
        
        .next-action-banner {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }
        
        .next-action-banner::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: shimmer 3s linear infinite;
        }
        
        @keyframes shimmer {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .workflow-connector {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 60px;
            font-size: 2rem;
            color: #dee2e6;
        }
    </style>
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container-fluid">
        <a class="navbar-brand" href="#">
            <i class="fas fa-route me-2"></i>
            集金フローガイド
        </a>
        <div class="navbar-nav ms-auto">
            <a class="nav-link" href="dashboard.php">
                <i class="fas fa-tachometer-alt me-1"></i>ダッシュボードに戻る
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

    <!-- ヘッダー -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-0 text-primary">
                        <i class="fas fa-route me-2"></i>
                        集金フローガイド
                    </h1>
                    <p class="text-muted mb-0"><?php echo date('Y年n月'); ?>の集金業務を段階的に進めましょう</p>
                </div>
                <div class="text-end">
                    <div class="text-muted small">現在の進捗</div>
                    <div class="fw-bold">
                        <?php
                        $completedSteps = count(array_filter($progress));
                        $totalSteps = count($progress);
                        echo $completedSteps . '/' . $totalSteps . ' ステップ完了';
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 次のアクションバナー -->
    <?php if ($nextAction !== 'completed'): ?>
    <div class="next-action-banner text-center">
        <h2 class="h4 mb-3">
            <i class="fas fa-arrow-right me-2"></i>
            次に実行するアクション
        </h2>
        <h3 class="h2 mb-3"><?php echo $steps[$nextAction]['title']; ?></h3>
        <p class="lead mb-4"><?php echo $steps[$nextAction]['description']; ?></p>
        <a href="<?php echo $steps[$nextAction]['url']; ?>" class="btn btn-light btn-lg px-4 py-3">
            <i class="fas fa-play me-2"></i>
            <?php echo $steps[$nextAction]['button_text']; ?>
        </a>
    </div>
    <?php else: ?>
    <div class="next-action-banner text-center">
        <h2 class="h4 mb-3">
            <i class="fas fa-check-circle me-2"></i>
            今月の集金業務完了
        </h2>
        <p class="lead mb-4">すべてのステップが完了しました。お疲れさまでした！</p>
        <a href="dashboard.php" class="btn btn-light btn-lg px-4 py-3">
            <i class="fas fa-tachometer-alt me-2"></i>
            ダッシュボードで詳細を確認
        </a>
    </div>
    <?php endif; ?>

    <!-- 全体進捗バー -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fw-bold">業務進捗</span>
                        <span class="text-muted"><?php echo round(($completedSteps / $totalSteps) * 100); ?>% 完了</span>
                    </div>
                    <div class="progress" style="height: 20px;">
                        <div class="progress-bar bg-success" style="width: <?php echo ($completedSteps / $totalSteps) * 100; ?>%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- フローステップ -->
    <div class="row g-4">
        <?php $stepIndex = 1; ?>
        <?php foreach ($steps as $stepKey => $step): ?>
            <div class="col-12">
                <div class="flow-card card <?php echo $step['status']; ?> <?php echo ($nextAction === $stepKey) ? 'current' : ''; ?>">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-auto">
                                <div class="step-number <?php echo $step['status']; ?> <?php echo ($nextAction === $stepKey) ? 'current' : ''; ?>">
                                    <?php if ($step['status'] === 'completed'): ?>
                                        <i class="fas fa-check"></i>
                                    <?php else: ?>
                                        <?php echo $stepIndex; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col">
                                <div class="row">
                                    <div class="col-lg-4">
                                        <h5 class="card-title mb-1">
                                            <?php echo $step['title']; ?>
                                            <span class="<?php echo getStepBadge($step['status']); ?> ms-2">
                                                <?php
                                                switch ($step['status']) {
                                                    case 'completed': echo '完了';break;
                                                    case 'in_progress': echo '進行中';break;
                                                    case 'pending': echo '待機中';break;
                                                }
                                                ?>
                                            </span>
                                        </h5>
                                        <p class="text-muted mb-0"><?php echo $step['description']; ?></p>
                                    </div>
                                    <div class="col-lg-4">
                                        <div class="small">
                                            <?php foreach ($step['details'] as $label => $value): ?>
                                            <div class="detail-item d-flex justify-content-between">
                                                <span class="text-muted"><?php echo $label; ?>:</span>
                                                <strong><?php echo $value; ?></strong>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <div class="col-lg-4">
                                        <div class="d-flex justify-content-end align-items-center h-100">
                                            <?php if ($nextAction === $stepKey || $step['status'] === 'in_progress'): ?>
                                                <a href="<?php echo $step['url']; ?>" class="btn btn-primary action-button px-4">
                                                    <i class="fas fa-play me-2"></i>
                                                    <?php echo $step['button_text']; ?>
                                                </a>
                                            <?php elseif ($step['status'] === 'completed'): ?>
                                                <a href="<?php echo $step['url']; ?>" class="btn btn-success action-button px-4">
                                                    <i class="fas fa-check me-2"></i>
                                                    詳細を確認
                                                </a>
                                            <?php else: ?>
                                                <button class="btn btn-outline-secondary action-button px-4" disabled>
                                                    <i class="fas fa-lock me-2"></i>
                                                    前のステップを完了してください
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if ($stepIndex < count($steps)): ?>
                <div class="workflow-connector">
                    <i class="fas fa-arrow-down"></i>
                </div>
                <?php endif; ?>
            </div>
        <?php $stepIndex++; ?>
        <?php endforeach; ?>
    </div>

    <!-- ヘルプセクション -->
    <div class="row mt-5">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-question-circle me-2 text-info"></i>
                        各ステップの詳細説明
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        <div class="col-lg-3 col-md-6">
                            <div class="h-100">
                                <h6 class="text-primary">
                                    <i class="fas fa-upload me-2"></i>
                                    CSVデータインポート
                                </h6>
                                <ul class="small text-muted">
                                    <li>注文CSVファイルをアップロード</li>
                                    <li>データの自動変換・検証</li>
                                    <li>エラーチェック・重複除去</li>
                                    <li>データベースへの登録</li>
                                </ul>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <div class="h-100">
                                <h6 class="text-warning">
                                    <i class="fas fa-file-invoice me-2"></i>
                                    請求書生成
                                </h6>
                                <ul class="small text-muted">
                                    <li>期間・企業の選択</li>
                                    <li>請求書の自動作成</li>
                                    <li>PDF形式での出力</li>
                                    <li>一括生成・個別生成対応</li>
                                </ul>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <div class="h-100">
                                <h6 class="text-success">
                                    <i class="fas fa-money-check me-2"></i>
                                    支払い管理
                                </h6>
                                <ul class="small text-muted">
                                    <li>入金記録・支払確認</li>
                                    <li>未払い・期限管理</li>
                                    <li>支払い方法別管理</li>
                                    <li>督促・アラート機能</li>
                                </ul>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <div class="h-100">
                                <h6 class="text-info">
                                    <i class="fas fa-receipt me-2"></i>
                                    領収書発行
                                </h6>
                                <ul class="small text-muted">
                                    <li>事前・事後領収書対応</li>
                                    <li>収入印紙判定機能</li>
                                    <li>PDF自動生成</li>
                                    <li>宛名事前設定対応</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- クイックアクション -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body text-center">
                    <h6 class="card-title">クイックアクション</h6>
                    <div class="row g-2">
                        <div class="col-lg-2 col-md-4 col-6">
                            <a href="dashboard.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-tachometer-alt d-block mb-1"></i>
                                <small>ダッシュボード</small>
                            </a>
                        </div>
                        <div class="col-lg-2 col-md-4 col-6">
                            <a href="pages/csv_import.php" class="btn btn-outline-info w-100">
                                <i class="fas fa-upload d-block mb-1"></i>
                                <small>CSVインポート</small>
                            </a>
                        </div>
                        <div class="col-lg-2 col-md-4 col-6">
                            <a href="pages/companies.php" class="btn btn-outline-secondary w-100">
                                <i class="fas fa-building d-block mb-1"></i>
                                <small>企業管理</small>
                            </a>
                        </div>
                        <div class="col-lg-2 col-md-4 col-6">
                            <a href="pages/users.php" class="btn btn-outline-success w-100">
                                <i class="fas fa-users d-block mb-1"></i>
                                <small>利用者管理</small>
                            </a>
                        </div>
                        <div class="col-lg-2 col-md-4 col-6">
                            <a href="pages/invoices.php" class="btn btn-outline-warning w-100">
                                <i class="fas fa-file-invoice d-block mb-1"></i>
                                <small>請求書管理</small>
                            </a>
                        </div>
                        <div class="col-lg-2 col-md-4 col-6">
                            <a href="pages/system_health.php" class="btn btn-outline-dark w-100">
                                <i class="fas fa-cogs d-block mb-1"></i>
                                <small>システム診断</small>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ページ読み込み時のアニメーション
document.addEventListener('DOMContentLoaded', function() {
    const cards = document.querySelectorAll('.flow-card');
    cards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateX(-30px)';
        card.style.transition = 'all 0.5s ease';
        
        setTimeout(() => {
            card.style.opacity = '1';
            card.style.transform = 'translateX(0)';
        }, index * 200);
    });
});

// 確認ダイアログ
document.querySelectorAll('.action-button[href]').forEach(button => {
    button.addEventListener('click', function(e) {
        const isCompleted = this.classList.contains('btn-success');
        if (!isCompleted) {
            const stepName = this.closest('.flow-card').querySelector('.card-title').textContent.trim();
            if (!confirm(`${stepName}を開始しますか？\n\n実行前に必要な準備が整っていることを確認してください。`)) {
                e.preventDefault();
            }
        }
    });
});

// 自動更新機能（5分ごと）
setInterval(function() {
    // ページをリロード（進捗状況を更新）
    location.reload();
}, 5 * 60 * 1000);

// キーボードショートカット
document.addEventListener('keydown', function(e) {
    // Ctrl + 1-4 でステップに直接移動
    if (e.ctrlKey && e.key >= '1' && e.key <= '4') {
        e.preventDefault();
        const stepIndex = parseInt(e.key) - 1;
        const buttons = document.querySelectorAll('.action-button[href]:not([disabled])');
        if (buttons[stepIndex] && !buttons[stepIndex].disabled) {
            buttons[stepIndex].click();
        }
    }
    
    // Ctrl + D でダッシュボードに戻る
    if (e.ctrlKey && e.key === 'd') {
        e.preventDefault();
        window.location.href = 'dashboard.php';
    }
});

// ステップ完了時の効果音（オプション）
function playCompletionSound() {
    // Web Audio API を使用した簡単な効果音
    if (typeof AudioContext !== 'undefined' || typeof webkitAudioContext !== 'undefined') {
        const audioContext = new (AudioContext || webkitAudioContext)();
        const oscillator = audioContext.createOscillator();
        const gainNode = audioContext.createGain();
        
        oscillator.connect(gainNode);
        gainNode.connect(audioContext.destination);
        
        oscillator.frequency.setValueAtTime(523.25, audioContext.currentTime); // C5
        oscillator.frequency.setValueAtTime(659.25, audioContext.currentTime + 0.1); // E5
        oscillator.frequency.setValueAtTime(783.99, audioContext.currentTime + 0.2); // G5
        
        gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
        gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.5);
        
        oscillator.start(audioContext.currentTime);
        oscillator.stop(audioContext.currentTime + 0.5);
    }
}

// 完了したステップがある場合は効果音を再生
<?php if (count(array_filter($progress)) > 0): ?>
// ページ読み込み時に1回だけ再生
window.addEventListener('load', function() {
    setTimeout(playCompletionSound, 1000);
});
<?php endif; ?>
</script>

</body>
</html>
