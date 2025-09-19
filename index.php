<?php
/**
 * Smiley配食事業 集金管理システム
 * メインダッシュボード - 集金業務特化版
 * 
 * @version 5.0
 * @date 2025-09-19
 * @purpose 集金管理業務の中央司令室
 */

session_start();

// 必要なクラスを読み込み
require_once __DIR__ . '/classes/PaymentManager.php';
require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/classes/SecurityHelper.php';

// 初期化処理
$paymentManager = new PaymentManager();
$error_message = '';
$success_message = '';

// エラーハンドリング
set_error_handler(function($severity, $message, $file, $line) {
    error_log("Dashboard Error: {$message} in {$file}:{$line}");
});

try {
    // サマリーデータ取得
    $summary_result = $paymentManager->getCollectionSummary();
    $summary = $summary_result['success'] ? $summary_result['data'] : [
        'current_month_sales' => 0,
        'total_outstanding' => 0,
        'overdue_amount' => 0,
        'collection_rate' => 0,
        'outstanding_count' => 0,
        'overdue_count' => 0
    ];
    
    // 緊急アラート取得
    $alerts_result = $paymentManager->getUrgentCollectionAlerts();
    $alerts = $alerts_result['success'] ? $alerts_result['data'] : [
        'urgent_count' => 0,
        'total_urgent_amount' => 0,
        'alerts' => []
    ];
    
    // 今日の予定取得
    $schedule_result = $paymentManager->getTodayCollectionSchedule();
    $schedule = $schedule_result['success'] ? $schedule_result['data'] : [
        'today' => [],
        'tomorrow' => [],
        'today_count' => 0,
        'tomorrow_count' => 0,
        'today_amount' => 0,
        'tomorrow_amount' => 0
    ];
    
} catch (Exception $e) {
    $error_message = 'システムエラーが発生しました。管理者にお問い合わせください。';
    error_log("Dashboard Exception: " . $e->getMessage());
    
    // エラー時のデフォルト値
    $summary = ['current_month_sales' => 0, 'total_outstanding' => 0, 'overdue_amount' => 0, 'collection_rate' => 0, 'outstanding_count' => 0, 'overdue_count' => 0];
    $alerts = ['urgent_count' => 0, 'total_urgent_amount' => 0, 'alerts' => []];
    $schedule = ['today' => [], 'tomorrow' => [], 'today_count' => 0, 'tomorrow_count' => 0, 'today_amount' => 0, 'tomorrow_amount' => 0];
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smiley配食 集金管理システム</title>
    
    <!-- CSS Libraries -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="assets/css/collection.css" rel="stylesheet">
    
    <style>
        /* 緊急CSS（collection.css作成前の暫定対応） */
        :root {
            --primary-blue: #2196F3;
            --success-green: #4CAF50;
            --warning-amber: #FFC107;
            --error-red: #F44336;
            --info-blue: #03A9F4;
        }
        
        /* PC操作不慣れ対応 */
        .btn {
            min-height: 45px;
            font-size: 18px;
            font-weight: 600;
        }
        
        .btn-lg {
            min-height: 60px;
            font-size: 24px;
            padding: 15px 30px;
        }
        
        /* 統計カード */
        .stat-card {
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.15);
        }
        
        .stat-card.success { background: linear-gradient(135deg, #4CAF50, #66BB6A); color: white; }
        .stat-card.warning { background: linear-gradient(135deg, #FFC107, #FFCA28); color: #333; }
        .stat-card.danger { background: linear-gradient(135deg, #F44336, #EF5350); color: white; }
        .stat-card.info { background: linear-gradient(135deg, #2196F3, #42A5F5); color: white; }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            margin: 0;
        }
        
        .stat-label {
            font-size: 1.1rem;
            opacity: 0.9;
            margin-bottom: 0;
        }
        
        /* 集金リスト行の色分け */
        .collection-row.overdue {
            background-color: #ffebee !important;
            border-left: 5px solid #f44336;
        }
        
        .collection-row.urgent {
            background-color: #fff8e1 !important;
            border-left: 5px solid #ffc107;
        }
        
        .collection-row.normal {
            background-color: #f1f8e9 !important;
            border-left: 5px solid #4caf50;
        }
        
        /* 満額入金ボタン */
        .btn-full-payment {
            background: linear-gradient(45deg, #4caf50, #66bb6a);
            border: none;
            color: white;
            font-weight: bold;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: all 0.3s;
            min-width: 150px;
        }
        
        .btn-full-payment:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            color: white;
        }
        
        /* 選択時のハイライト */
        .collection-row.selected {
            background-color: #e3f2fd !important;
            border: 2px solid #2196f3;
        }
        
        /* アラートバッジ */
        .alert-badge.overdue {
            background-color: #f44336;
            color: white;
        }
        
        .alert-badge.urgent {
            background-color: #ffc107;
            color: black;
        }
        
        .alert-badge.normal {
            background-color: #4caf50;
            color: white;
        }
        
        /* 印刷専用スタイル */
        @media print {
            .no-print {
                display: none !important;
            }
            
            .collection-row {
                page-break-inside: avoid;
            }
            
            .card {
                border: none;
                box-shadow: none;
            }
        }
        
        /* レスポンシブ対応 */
        @media (max-width: 768px) {
            .btn {
                font-size: 16px;
                min-height: 40px;
            }
            
            .stat-number {
                font-size: 2rem;
            }
            
            .card-body {
                padding: 0.75rem;
            }
        }
        
        /* ローディング表示 */
        .loading {
            text-align: center;
            padding: 40px;
        }
        
        .loading .spinner-border {
            width: 3rem;
            height: 3rem;
        }
    </style>
</head>
<body class="bg-light">
    <!-- ヘッダー -->
    <nav class="navbar navbar-dark bg-primary mb-4 no-print">
        <div class="container-fluid">
            <span class="navbar-brand mb-0 h1">
                <i class="material-icons me-2">account_balance_wallet</i>
                Smiley配食 集金管理システム
            </span>
            <div>
                <button class="btn btn-outline-light me-2" onclick="importCSV()" title="CSVインポート">
                    <i class="material-icons me-1">upload_file</i> CSVインポート
                </button>
                <button class="btn btn-outline-light me-2" onclick="location.reload()" title="画面更新">
                    <i class="material-icons me-1">refresh</i> 更新
                </button>
                <span class="navbar-text">
                    <i class="material-icons me-1">today</i>
                    <?= date('Y年m月d日') ?>
                </span>
            </div>
        </div>
    </nav>
    
    <div class="container-fluid">
        <!-- エラー・成功メッセージ -->
        <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show no-print" role="alert">
                <i class="material-icons me-2">error</i>
                <?= htmlspecialchars($error_message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show no-print" role="alert">
                <i class="material-icons me-2">check_circle</i>
                <?= htmlspecialchars($success_message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <!-- サマリーカード -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card info">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="stat-label">今月売上</p>
                            <h2 class="stat-number">¥<?= number_format($summary['current_month_sales']) ?></h2>
                        </div>
                        <i class="material-icons" style="font-size: 3rem; opacity: 0.7;">trending_up</i>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="stat-card warning">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="stat-label">未回収</p>
                            <h2 class="stat-number">¥<?= number_format($summary['total_outstanding']) ?></h2>
                            <small><?= $summary['outstanding_count'] ?>件</small>
                        </div>
                        <i class="material-icons" style="font-size: 3rem; opacity: 0.7;">pending</i>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="stat-card danger">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="stat-label">期限切れ</p>
                            <h2 class="stat-number">¥<?= number_format($summary['overdue_amount']) ?></h2>
                            <small><?= $summary['overdue_count'] ?>件</small>
                        </div>
                        <i class="material-icons" style="font-size: 3rem; opacity: 0.7;">error</i>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="stat-card success">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="stat-label">回収率</p>
                            <h2 class="stat-number"><?= number_format($summary['collection_rate'], 1) ?>%</h2>
                        </div>
                        <i class="material-icons" style="font-size: 3rem; opacity: 0.7;">check_circle</i>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- 緊急アラート -->
        <?php if ($alerts['urgent_count'] > 0): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="alert alert-danger no-print">
                    <h5 class="alert-heading">
                        <i class="material-icons me-2">warning</i>
                        緊急対応が必要な案件があります！
                    </h5>
                    <p class="mb-2">
                        期限切れ・高額未回収: <strong><?= $alerts['urgent_count'] ?>件</strong>
                        合計金額: <strong>¥<?= number_format($alerts['total_urgent_amount']) ?></strong>
                    </p>
                    <button class="btn btn-danger" onclick="showUrgentAlerts()">
                        <i class="material-icons me-1">priority_high</i>
                        緊急案件を確認
                    </button>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- 検索・フィルター -->
        <div class="row mb-3 no-print">
            <div class="col-md-6">
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="material-icons">search</i>
                    </span>
                    <input type="text" class="form-control" id="search-company" 
                           placeholder="企業名で検索..." style="font-size: 18px;">
                    <button class="btn btn-outline-secondary" onclick="searchCollections()">
                        検索
                    </button>
                </div>
            </div>
            <div class="col-md-6">
                <div class="btn-group w-100" role="group">
                    <input type="radio" class="btn-check" name="filter" id="filter-all" value="" checked>
                    <label class="btn btn-outline-primary" for="filter-all">
                        <i class="material-icons me-1">list</i> 全て
                    </label>
                    
                    <input type="radio" class="btn-check" name="filter" id="filter-overdue" value="overdue">
                    <label class="btn btn-outline-danger" for="filter-overdue">
                        <i class="material-icons me-1">error</i> 期限切れ
                    </label>
                    
                    <input type="radio" class="btn-check" name="filter" id="filter-urgent" value="urgent">
                    <label class="btn btn-outline-warning" for="filter-urgent">
                        <i class="material-icons me-1">warning</i> 期限間近
                    </label>
                </div>
            </div>
        </div>
        
        <!-- 集金リスト -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="material-icons me-2">list</i> 集金が必要な企業一覧
                </h5>
                <div class="no-print">
                    <span id="selected-summary" class="me-3 badge bg-info fs-6">
                        選択: 0件 ¥0
                    </span>
                    <button class="btn btn-success me-2" id="bulk-payment-btn" disabled>
                        <i class="material-icons me-1">payments</i> 一括入金記録
                    </button>
                    <button class="btn btn-outline-primary me-2" onclick="printSelected()">
                        <i class="material-icons me-1">print</i> 印刷
                    </button>
                    <div class="btn-group">
                        <button class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="material-icons me-1">sort</i> 並び替え
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#" data-sort="priority">優先度順</a></li>
                            <li><a class="dropdown-item" href="#" data-sort="amount-desc">金額順（高→低）</a></li>
                            <li><a class="dropdown-item" href="#" data-sort="due-date">期限順</a></li>
                            <li><a class="dropdown-item" href="#" data-sort="company-name">企業名順</a></li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <!-- ローディング表示 -->
                <div id="loading" class="loading">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">読み込み中...</span>
                    </div>
                    <p class="mt-2">集金リストを読み込み中...</p>
                </div>
                
                <!-- 集金リストテーブル -->
                <div class="table-responsive" id="collection-table" style="display: none;">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th width="50" class="no-print">
                                    <input type="checkbox" id="select-all" class="form-check-input">
                                </th>
                                <th>企業名</th>
                                <th>請求金額</th>
                                <th>支払期限</th>
                                <th>状況</th>
                                <th class="no-print">操作</th>
                            </tr>
                        </thead>
                        <tbody id="collection-list">
                            <!-- 動的生成 -->
                        </tbody>
                    </table>
                </div>
                
                <!-- データなし表示 -->
                <div id="no-data" style="display: none;" class="text-center p-5">
                    <i class="material-icons text-muted" style="font-size: 4rem;">inbox</i>
                    <h5 class="text-muted mt-3">集金が必要な企業はありません</h5>
                    <p class="text-muted">全ての請求が完了しているか、検索条件を変更してください。</p>
                </div>
            </div>
        </div>
        
        <!-- ページネーション -->
        <nav aria-label="集金リストページネーション" class="mt-3 no-print">
            <ul class="pagination justify-content-center" id="pagination">
                <!-- 動的生成 -->
            </ul>
        </nav>
    </div>
    
    <!-- 満額入金モーダル -->
    <div class="modal fade" id="paymentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="material-icons me-2">payments</i> 満額入金記録
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="payment-form">
                        <div class="mb-3">
                            <label class="form-label fw-bold">企業名</label>
                            <p class="form-control-plaintext border bg-light p-2" id="modal-company-name">-</p>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">入金金額</label>
                            <p class="form-control-plaintext border bg-light p-2 fs-4 text-success fw-bold" id="modal-amount">¥0</p>
                        </div>
                        
                        <div class="mb-3">
                            <label for="payment-method" class="form-label fw-bold">支払方法 <span class="text-danger">*</span></label>
                            <select class="form-select" id="payment-method" name="payment_method" required style="font-size: 18px;">
                                <option value="">選択してください</option>
                                <option value="cash">💵 現金</option>
                                <option value="bank_transfer">🏦 銀行振込</option>
                                <option value="paypay">📱 PayPay</option>
                                <option value="account_debit">🏦 口座引き落とし</option>
                                <option value="other">💼 その他</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="payment-date" class="form-label fw-bold">入金日 <span class="text-danger">*</span></label>
                            <input type="date" class="form-select" id="payment-date" name="payment_date" 
                                   value="<?= date('Y-m-d') ?>" required style="font-size: 18px;">
                        </div>
                        
                        <div class="mb-3">
                            <label for="payment-notes" class="form-label fw-bold">備考</label>
                            <textarea class="form-control" id="payment-notes" name="notes" rows="2" 
                                      placeholder="特記事項があれば入力" style="font-size: 16px;"></textarea>
                        </div>
                        
                        <input type="hidden" id="modal-invoice-id">
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="material-icons me-1">cancel</i> キャンセル
                    </button>
                    <button type="button" class="btn btn-success btn-lg" id="confirm-payment-btn">
                        <i class="material-icons me-1">check</i> 入金記録する
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 一括入金モーダル -->
    <div class="modal fade" id="bulkPaymentModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="material-icons me-2">receipt</i> 一括入金記録
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="material-icons me-2">info</i>
                        複数の企業の入金を同時に記録します。
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>選択企業数:</strong> <span id="bulk-company-count">0</span>社
                        </div>
                        <div class="col-md-6">
                            <strong>合計金額:</strong> <span class="text-success fw-bold" id="bulk-total-amount">¥0</span>
                        </div>
                    </div>
                    
                    <form id="bulk-payment-form">
                        <div class="mb-3">
                            <label for="bulk-payment-method" class="form-label fw-bold">一括支払方法 <span class="text-danger">*</span></label>
                            <select class="form-select" id="bulk-payment-method" name="payment_method" required style="font-size: 18px;">
                                <option value="">選択してください</option>
                                <option value="cash">💵 現金一括</option>
                                <option value="bank_transfer">🏦 銀行振込一括</option>
                                <option value="mixed">💳 混合（個別設定）</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="bulk-payment-date" class="form-label fw-bold">処理日 <span class="text-danger">*</span></label>
                            <input type="date" class="form-select" id="bulk-payment-date" name="payment_date" 
                                   value="<?= date('Y-m-d') ?>" required style="font-size: 18px;">
                        </div>
                        
                        <div class="mb-3">
                            <label for="bulk-payment-notes" class="form-label fw-bold">備考</label>
                            <textarea class="form-control" id="bulk-payment-notes" name="notes" rows="2" 
                                      placeholder="一括処理の備考" style="font-size: 16px;"></textarea>
                        </div>
                    </form>
                    
                    <div class="alert alert-warning mt-3">
                        <i class="material-icons me-2">warning</i>
                        <strong>注意:</strong> この操作は取り消せません。内容を十分確認してから実行してください。
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="material-icons me-1">cancel</i> キャンセル
                    </button>
                    <button type="button" class="btn btn-success btn-lg" id="confirm-bulk-payment-btn">
                        <i class="material-icons me-1">check</i> 一括処理実行
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- JavaScript Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    
    <!-- Custom JavaScript -->
    <script>
        // グローバル変数
        let collectionManager = null;
        
        // ページ読み込み完了時に初期化
        document.addEventListener('DOMContentLoaded', function() {
            console.log('集金管理ダッシュボード初期化開始');
            
            // CollectionManagerクラスは別ファイルで定義予定
            // 暫定的に基本機能のみ実装
            initBasicFunctions();
        });
        
        /**
         * 基本機能初期化
         */
        function initBasicFunctions() {
            console.log('基本機能初期化中...');
            
            // 集金リスト読み込み
            loadCollectionList();
            
            // イベントリスナー設定
            setupEventListeners();
            
            // 自動更新設定（5分ごと）
            setInterval(refreshData, 300000);
        }
        
        /**
         * イベントリスナー設定
         */
        function setupEventListeners() {
            // 全選択チェックボックス
            const selectAll = document.getElementById('select-all');
            if (selectAll) {
                selectAll.addEventListener('change', function(e) {
                    toggleSelectAll(e.target.checked);
                });
            }
            
            // 検索フィールド
            const searchInput = document.getElementById('search-company');
            if (searchInput) {
                searchInput.addEventListener('input', function(e) {
                    debounceSearch(e.target.value);
                });
            }
            
            // フィルターラジオボタン
            document.querySelectorAll('input[name="filter"]').forEach(radio => {
                radio.addEventListener('change', function(e) {
                    applyFilter(e.target.value);
                });
            });
            
            // 一括入金ボタン
            const bulkBtn = document.getElementById('bulk-payment-btn');
            if (bulkBtn) {
                bulkBtn.addEventListener('click', showBulkPaymentModal);
            }
            
            // モーダル確認ボタン
            const confirmBtn = document.getElementById('confirm-payment-btn');
            if (confirmBtn) {
                confirmBtn.addEventListener('click', confirmPayment);
            }
            
            const confirmBulkBtn = document.getElementById('confirm-bulk-payment-btn');
            if (confirmBulkBtn) {
                confirmBulkBtn.addEventListener('click', confirmBulkPayment);
            }
        }
        
        /**
         * 集金リスト読み込み
         */
        async function loadCollectionList(filters = {}) {
            try {
                console.log('集金リスト読み込み開始', filters);
                showLoading(true);
                
                const params = new URLSearchParams({
                    action: 'collection_list',
                    ...filters
                });
                
                const response = await fetch(`api/payments.php?${params}`);
                const data = await response.json();
                
                if (data && data.success !== false) {
                    // データが配列の場合は成功とみなす（APIが未完成のため暫定対応）
                    const listData = Array.isArray(data) ? data : (data.data || []);
                    renderCollectionList(listData);
                } else {
                    console.error('集金リスト取得エラー:', data);
                    showError('集金リストの取得に失敗しました');
                }
                
            } catch (error) {
                console.error('集金リスト読み込みエラー:', error);
                showError('集金リスト読み込み中にエラーが発生しました');
            } finally {
                showLoading(false);
            }
        }
        
        /**
         * 集金リスト表示
         */
        function renderCollectionList(data) {
            console.log('集金リスト表示', data);
            
            const tbody = document.getElementById('collection-list');
            const tableContainer = document.getElementById('collection-table');
            const noDataContainer = document.getElementById('no-data');
            
            if (!tbody) return;
            
            tbody.innerHTML = '';
            
            if (!data || data.length === 0) {
                tableContainer.style.display = 'none';
                noDataContainer.style.display = 'block';
                return;
            }
            
            tableContainer.style.display = 'block';
            noDataContainer.style.display = 'none';
            
            data.forEach(item => {
                const row = createCollectionRow(item);
                tbody.appendChild(row);
            });
            
            updateSelectedSummary();
        }
        
        /**
         * 集金リスト行作成
         */
        function createCollectionRow(item) {
            const tr = document.createElement('tr');
            tr.className = `collection-row ${item.alert_level || 'normal'}`;
            tr.dataset.invoiceId = item.invoice_id || item.id;
            tr.dataset.amount = item.outstanding_amount || item.total_amount || 0;
            
            const alertIcon = getAlertIcon(item.alert_level);
            const alertBadge = getAlertBadge(item.alert_level, item.overdue_days);
            
            tr.innerHTML = `
                <td class="no-print">
                    <input type="checkbox" class="form-check-input row-checkbox" 
                           data-invoice-id="${item.invoice_id || item.id}" 
                           data-amount="${item.outstanding_amount || item.total_amount || 0}">
                </td>
                <td>
                    <div class="d-flex align-items-center">
                        ${alertIcon}
                        <div class="ms-2">
                            <div class="fw-bold">${item.company_name || '企業名不明'}</div>
                            <small class="text-muted">${item.contact_person || ''}</small>
                        </div>
                    </div>
                </td>
                <td>
                    <span class="fw-bold fs-5">¥${(item.outstanding_amount || item.total_amount || 0).toLocaleString()}</span>
                </td>
                <td>
                    <div>${item.due_date || '期限未設定'}</div>
                    ${alertBadge}
                </td>
                <td>
                    <span class="badge bg-${getStatusColor(item.alert_level)}">
                        ${getStatusText(item.alert_level)}
                    </span>
                </td>
                <td class="no-print">
                    <button class="btn btn-full-payment btn-sm" 
                            onclick="showPaymentModal(${item.invoice_id || item.id})">
                        <i class="material-icons me-1">payments</i>
                        満額入金 ¥${(item.outstanding_amount || item.total_amount || 0).toLocaleString()}
                    </button>
                </td>
            `;
            
            // チェックボックスイベント
            const checkbox = tr.querySelector('.row-checkbox');
            if (checkbox) {
                checkbox.addEventListener('change', function(e) {
                    toggleRowSelection(e.target);
                });
            }
            
            return tr;
        }
        
        /**
         * アラートアイコン取得
         */
        function getAlertIcon(level) {
            const icons = {
                'overdue': '<i class="material-icons text-danger">error</i>',
                'urgent': '<i class="material-icons text-warning">warning</i>',
                'normal': '<i class="material-icons text-success">check_circle</i>'
            };
            return icons[level] || icons['normal'];
        }
        
        /**
         * アラートバッジ取得
         */
        function getAlertBadge(level, overdueDays) {
            if (level === 'overdue' && overdueDays > 0) {
                return `<small class="badge bg-danger">${overdueDays}日経過</small>`;
            } else if (level === 'urgent') {
                return `<small class="badge bg-warning">期限間近</small>`;
            }
            return '';
        }
        
        /**
         * ステータス色取得
         */
        function getStatusColor(level) {
            const colors = {
                'overdue': 'danger',
                'urgent': 'warning',
                'normal': 'success'
            };
            return colors[level] || 'success';
        }
        
        /**
         * ステータステキスト取得
         */
        function getStatusText(level) {
            const texts = {
                'overdue': '期限切れ',
                'urgent': '期限間近',
                'normal': '正常'
            };
            return texts[level] || '正常';
        }
        
        /**
         * 行選択切り替え
         */
        function toggleRowSelection(checkbox) {
            const row = checkbox.closest('tr');
            
            if (checkbox.checked) {
                row.classList.add('selected');
            } else {
                row.classList.remove('selected');
            }
            
            updateSelectedSummary();
        }
        
        /**
         * 全選択切り替え
         */
        function toggleSelectAll(checked) {
            const checkboxes = document.querySelectorAll('.row-checkbox');
            checkboxes.forEach(cb => {
                cb.checked = checked;
                toggleRowSelection(cb);
            });
        }
        
        /**
         * 選択サマリー更新
         */
        function updateSelectedSummary() {
            const selectedCheckboxes = document.querySelectorAll('.row-checkbox:checked');
            const selectedCount = selectedCheckboxes.length;
            const selectedAmount = Array.from(selectedCheckboxes).reduce((sum, cb) => {
                return sum + parseFloat(cb.dataset.amount || 0);
            }, 0);
            
            const summaryEl = document.getElementById('selected-summary');
            if (summaryEl) {
                summaryEl.textContent = `選択: ${selectedCount}件 ¥${selectedAmount.toLocaleString()}`;
            }
            
            const bulkBtn = document.getElementById('bulk-payment-btn');
            if (bulkBtn) {
                bulkBtn.disabled = selectedCount === 0;
            }
        }
        
        /**
         * 満額入金モーダル表示
         */
        function showPaymentModal(invoiceId) {
            const row = document.querySelector(`tr[data-invoice-id="${invoiceId}"]`);
            if (!row) return;
            
            const companyName = row.querySelector('.fw-bold').textContent;
            const amount = parseFloat(row.dataset.amount);
            
            document.getElementById('modal-company-name').textContent = companyName;
            document.getElementById('modal-amount').textContent = `¥${amount.toLocaleString()}`;
            document.getElementById('modal-invoice-id').value = invoiceId;
            
            const modal = new bootstrap.Modal(document.getElementById('paymentModal'));
            modal.show();
        }
        
        /**
         * 一括入金モーダル表示
         */
        function showBulkPaymentModal() {
            const selectedCheckboxes = document.querySelectorAll('.row-checkbox:checked');
            const selectedCount = selectedCheckboxes.length;
            const totalAmount = Array.from(selectedCheckboxes).reduce((sum, cb) => {
                return sum + parseFloat(cb.dataset.amount || 0);
            }, 0);
            
            if (selectedCount === 0) {
                alert('処理する企業を選択してください');
                return;
            }
            
            document.getElementById('bulk-company-count').textContent = selectedCount;
            document.getElementById('bulk-total-amount').textContent = `¥${totalAmount.toLocaleString()}`;
            
            const modal = new bootstrap.Modal(document.getElementById('bulkPaymentModal'));
            modal.show();
        }
        
        /**
         * 入金記録確認
         */
        async function confirmPayment() {
            const form = document.getElementById('payment-form');
            const formData = new FormData(form);
            const invoiceId = document.getElementById('modal-invoice-id').value;
            
            if (!formData.get('payment_method')) {
                alert('支払方法を選択してください');
                return;
            }
            
            if (!confirm('入金記録を実行しますか？\nこの操作は取り消せません。')) {
                return;
            }
            
            try {
                const response = await fetch('api/payments.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'record_full_payment',
                        invoice_id: invoiceId,
                        payment_method: formData.get('payment_method'),
                        payment_date: formData.get('payment_date'),
                        notes: formData.get('notes')
                    })
                });
                
                const result = await response.json();
                
                if (result && result.success) {
                    alert(`入金記録が完了しました（¥${result.amount?.toLocaleString() || '0'}）`);
                    
                    // モーダルを閉じる
                    const modal = bootstrap.Modal.getInstance(document.getElementById('paymentModal'));
                    modal.hide();
                    
                    // データを更新
                    refreshData();
                } else {
                    alert(`エラー: ${result.error || '入金記録に失敗しました'}`);
                }
                
            } catch (error) {
                console.error('入金記録エラー:', error);
                alert('入金記録中にエラーが発生しました');
            }
        }
        
        /**
         * 一括入金記録確認
         */
        async function confirmBulkPayment() {
            const form = document.getElementById('bulk-payment-form');
            const formData = new FormData(form);
            const selectedCheckboxes = document.querySelectorAll('.row-checkbox:checked');
            const invoiceIds = Array.from(selectedCheckboxes).map(cb => parseInt(cb.dataset.invoiceId));
            
            if (!formData.get('payment_method')) {
                alert('支払方法を選択してください');
                return;
            }
            
            if (!confirm(`${invoiceIds.length}件の一括入金記録を実行しますか？\nこの操作は取り消せません。`)) {
                return;
            }
            
            try {
                const response = await fetch('api/payments.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'record_bulk_full_payments',
                        invoice_ids: invoiceIds,
                        payment_method: formData.get('payment_method'),
                        payment_date: formData.get('payment_date'),
                        notes: formData.get('notes')
                    })
                });
                
                const result = await response.json();
                
                if (result && result.success) {
                    alert(`一括入金記録が完了しました\n成功: ${result.success_count}件\n合計: ¥${result.total_amount?.toLocaleString() || '0'}`);
                    
                    // モーダルを閉じる
                    const modal = bootstrap.Modal.getInstance(document.getElementById('bulkPaymentModal'));
                    modal.hide();
                    
                    // データを更新
                    refreshData();
                } else {
                    alert(`エラー: ${result.error || '一括入金記録に失敗しました'}`);
                }
                
            } catch (error) {
                console.error('一括入金記録エラー:', error);
                alert('一括入金記録中にエラーが発生しました');
            }
        }
        
        /**
         * ローディング表示切り替え
         */
        function showLoading(show) {
            const loading = document.getElementById('loading');
            const table = document.getElementById('collection-table');
            
            if (loading) {
                loading.style.display = show ? 'block' : 'none';
            }
            if (table) {
                table.style.display = show ? 'none' : 'block';
            }
        }
        
        /**
         * エラーメッセージ表示
         */
        function showError(message) {
            alert(message); // 暫定実装、後でtoast等に変更
        }
        
        /**
         * データ更新
         */
        function refreshData() {
            console.log('データ更新中...');
            location.reload(); // 暫定実装、後でAJAXに変更
        }
        
        /**
         * CSVインポート
         */
        function importCSV() {
            window.open('pages/csv_import.php', '_blank');
        }
        
        /**
         * 印刷
         */
        function printSelected() {
            window.print();
        }
        
        /**
         * 検索（デバウンス付き）
         */
        let searchTimer;
        function debounceSearch(query) {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => {
                loadCollectionList({ company_name: query });
            }, 500);
        }
        
        /**
         * 検索実行
         */
        function searchCollections() {
            const query = document.getElementById('search-company').value;
            loadCollectionList({ company_name: query });
        }
        
        /**
         * フィルター適用
         */
        function applyFilter(filterValue) {
            loadCollectionList({ alert_level: filterValue });
        }
        
        /**
         * 緊急アラート表示
         */
        function showUrgentAlerts() {
            // フィルターを期限切れに設定
            document.getElementById('filter-overdue').checked = true;
            applyFilter('overdue');
        }
        
        console.log('集金管理ダッシュボード JavaScript 読み込み完了');
    </script>
</body>
</html>
