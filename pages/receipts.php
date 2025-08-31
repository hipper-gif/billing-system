<?php
/**
 * 領収書管理画面
 * Smiley配食事業 集金管理システム
 */
require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/ReceiptGenerator.php';

// セキュリティ: 直接アクセスを防ぐ
if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    if (!isset($_SERVER['HTTP_REFERER']) || 
        strpos($_SERVER['HTTP_REFERER'], $_SERVER['SERVER_NAME']) === false) {
        header('Location: ../index.php');
        exit;
    }
}

try {
    $db = new Database();
    $receiptGenerator = new ReceiptGenerator($db);
} catch (Exception $e) {
    error_log("Database connection error: " . $e->getMessage());
    die("システムエラーが発生しました。管理者にお問い合わせください。");
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>領収書管理 - Smiley配食事業システム</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- カスタムCSS -->
    <style>
        /* PC操作不慣れ対応 */
        .main-btn {
            min-height: 80px;
            font-size: 1.2rem;
            font-weight: bold;
            margin: 10px 0;
            border-radius: 12px;
        }
        
        .btn-receipt-generate { background: linear-gradient(45deg, #28a745, #20c997); }
        .btn-receipt-list { background: linear-gradient(45deg, #007bff, #17a2b8); }
        .btn-receipt-search { background: linear-gradient(45deg, #6f42c1, #6610f2); }
        .btn-receipt-export { background: linear-gradient(45deg, #fd7e14, #ffc107); }
        
        /* 緊急度別カラー */
        .status-draft { background-color: #fff3cd; border-left: 5px solid #ffc107; }
        .status-issued { background-color: #d1ecf1; border-left: 5px solid #17a2b8; }
        .status-delivered { background-color: #d4edda; border-left: 5px solid #28a745; }
        
        .stamp-required { color: #dc3545; font-weight: bold; }
        .stamp-not-required { color: #6c757d; }
        
        /* レスポンシブ対応 */
        @media (max-width: 768px) {
            .main-btn { min-height: 70px; font-size: 1rem; }
            .display-6 { font-size: 1.5rem; }
        }
        
        /* 統計カード */
        .stats-card { 
            border-radius: 15px; 
            box-shadow: 0 4px 6px rgba(0,0,0,0.1); 
            transition: transform 0.2s;
        }
        .stats-card:hover { transform: translateY(-5px); }
        
        /* データテーブル調整 */
        .table-responsive { border-radius: 10px; overflow: hidden; }
        .table th { background: linear-gradient(45deg, #495057, #6c757d); color: white; }
    </style>
</head>
<body class="bg-light">

<div class="container-fluid py-4">
    <!-- ヘッダー -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="display-6 mb-0">
                        <i class="bi bi-receipt"></i> 領収書管理
                    </h1>
                    <p class="text-muted mb-0">事前・事後領収書の発行・管理</p>
                </div>
                <div>
                    <button onclick="location.href='../index.php'" 
                            class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-house"></i> ダッシュボードに戻る
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 統計サマリー -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card stats-card text-center">
                <div class="card-body">
                    <h3 class="display-6 text-success mb-2" id="issued-receipts">0</h3>
                    <p class="card-text">発行済み領収書</p>
                    <small class="text-muted">今月</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stats-card text-center">
                <div class="card-body">
                    <h3 class="display-6 text-primary mb-2" id="total-amount">¥0</h3>
                    <p class="card-text">総発行金額</p>
                    <small class="text-muted">今月</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stats-card text-center">
                <div class="card-body">
                    <h3 class="display-6 text-warning mb-2" id="stamp-required-count">0</h3>
                    <p class="card-text">収入印紙要</p>
                    <small class="text-muted">5万円以上</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stats-card text-center">
                <div class="card-body">
                    <h3 class="display-6 text-info mb-2" id="advance-receipts">0</h3>
                    <p class="card-text">事前領収書</p>
                    <small class="text-muted">配達前発行</small>
                </div>
            </div>
        </div>
    </div>
    
    <!-- メイン操作ボタン -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-lightning-fill"></i> ワンクリック操作
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <button class="btn btn-receipt-generate main-btn w-100 text-white" 
                                    onclick="showReceiptGenerateModal()">
                                <i class="bi bi-plus-circle-fill"></i><br>
                                領収書生成
                            </button>
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn-receipt-list main-btn w-100 text-white" 
                                    onclick="refreshReceiptList()">
                                <i class="bi bi-list-task"></i><br>
                                領収書一覧
                            </button>
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn-receipt-search main-btn w-100 text-white" 
                                    onclick="showAdvancedSearchModal()">
                                <i class="bi bi-search"></i><br>
                                詳細検索
                            </button>
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn-receipt-export main-btn w-100 text-white" 
                                    onclick="exportReceiptList()">
                                <i class="bi bi-download"></i><br>
                                一括PDF出力
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- フィルター -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="bi bi-funnel"></i> フィルター
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-2">
                            <label class="form-label">発行日期間</label>
                            <div class="input-group">
                                <input type="date" class="form-control" id="date-from" 
                                       value="<?= date('Y-m-01') ?>">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <div class="input-group">
                                <input type="date" class="form-control" id="date-to" 
                                       value="<?= date('Y-m-t') ?>">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">ステータス</label>
                            <select class="form-select" id="status-filter">
                                <option value="">全て</option>
                                <option value="draft">下書き</option>
                                <option value="issued">発行済み</option>
                                <option value="delivered">配達済み</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">領収書タイプ</label>
                            <select class="form-select" id="type-filter">
                                <option value="">全て</option>
                                <option value="advance">事前領収書</option>
                                <option value="payment">正式領収書</option>
                                <option value="split">分割領収書</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">収入印紙</label>
                            <select class="form-select" id="stamp-filter">
                                <option value="">全て</option>
                                <option value="1">印紙要</option>
                                <option value="0">印紙不要</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <button class="btn btn-primary w-100" onclick="applyFilters()">
                                <i class="bi bi-search"></i> 検索
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 領収書一覧テーブル -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">
                        <i class="bi bi-table"></i> 領収書一覧
                    </h6>
                    <div>
                        <button class="btn btn-sm btn-outline-primary" onclick="refreshReceiptList()">
                            <i class="bi bi-arrow-clockwise"></i> 更新
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="receipts-table">
                            <thead>
                                <tr>
                                    <th>領収書番号</th>
                                    <th>発行日</th>
                                    <th>受領者名</th>
                                    <th>金額</th>
                                    <th>タイプ</th>
                                    <th>収入印紙</th>
                                    <th>ステータス</th>
                                    <th>操作</th>
                                </tr>
                            </thead>
                            <tbody id="receipts-tbody">
                                <!-- JavaScript で動的に読み込み -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 領収書生成モーダル -->
<div class="modal fade" id="receiptGenerateModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-plus-circle"></i> 領収書生成
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="receipt-generate-form">
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label">領収書タイプ *</label>
                            <select class="form-select" name="receipt_type" required>
                                <option value="payment">正式領収書（支払後）</option>
                                <option value="advance">事前領収書（配達前）</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">請求書ID</label>
                            <input type="number" class="form-control" name="invoice_id" 
                                   placeholder="請求書IDを入力">
                        </div>
                    </div>
                    
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <label class="form-label">受領者名 *</label>
                            <input type="text" class="form-control" name="recipient_name" 
                                   required placeholder="株式会社○○">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">金額 *</label>
                            <div class="input-group">
                                <span class="input-group-text">¥</span>
                                <input type="number" class="form-control" name="amount" 
                                       required min="1" step="1">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <label class="form-label">但し書き</label>
                            <input type="text" class="form-control" name="purpose" 
                                   value="お弁当代として" placeholder="お弁当代として">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">発行日</label>
                            <input type="date" class="form-control" name="issue_date" 
                                   value="<?= date('Y-m-d') ?>">
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <label class="form-label">備考</label>
                        <textarea class="form-control" name="notes" rows="2" 
                                  placeholder="特記事項があれば入力"></textarea>
                    </div>
                    
                    <div class="mt-3">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i>
                            <strong>収入印紙について:</strong> 5万円以上の領収書には収入印紙（200円）が必要です。
                            システムが自動判定します。
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    キャンセル
                </button>
                <button type="button" class="btn btn-success" onclick="generateReceipt()">
                    <i class="bi bi-check-circle"></i> 領収書生成
                </button>
            </div>
        </div>
    </div>
</div>

<!-- 詳細検索モーダル -->
<div class="modal fade" id="advancedSearchModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-search"></i> 詳細検索
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="advanced-search-form">
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label">領収書番号</label>
                            <input type="text" class="form-control" name="receipt_number">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">受領者名</label>
                            <input type="text" class="form-control" name="recipient_name">
                        </div>
                    </div>
                    
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <label class="form-label">金額範囲（最小）</label>
                            <div class="input-group">
                                <span class="input-group-text">¥</span>
                                <input type="number" class="form-control" name="amount_min">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">金額範囲（最大）</label>
                            <div class="input-group">
                                <span class="input-group-text">¥</span>
                                <input type="number" class="form-control" name="amount_max">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mt-3">
                        <div class="col-md-12">
                            <label class="form-label">但し書き</label>
                            <input type="text" class="form-control" name="purpose">
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    キャンセル
                </button>
                <button type="button" class="btn btn-primary" onclick="performAdvancedSearch()">
                    <i class="bi bi-search"></i> 検索実行
                </button>
            </div>
        </div>
    </div>
</div>

<!-- 領収書詳細モーダル -->
<div class="modal fade" id="receiptDetailModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-receipt"></i> 領収書詳細
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="receipt-detail-content">
                <!-- 詳細内容は JavaScript で動的に読み込み -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    閉じる
                </button>
                <button type="button" class="btn btn-info" id="download-pdf-btn">
                    <i class="bi bi-download"></i> PDF出力
                </button>
                <button type="button" class="btn btn-warning" id="reissue-receipt-btn">
                    <i class="bi bi-arrow-clockwise"></i> 再発行
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
$(document).ready(function() {
    // ページ読み込み時の初期化
    initializeReceiptManagement();
    loadReceiptStatistics();
    loadReceiptList();
});

/**
 * 領収書管理の初期化
 */
function initializeReceiptManagement() {
    console.log('領収書管理画面を初期化しています...');
    
    // DataTables初期化
    $('#receipts-table').DataTable({
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.11.5/i18n/ja.json"
        },
        "order": [[1, "desc"]],  // 発行日で降順ソート
        "pageLength": 25,
        "responsive": true,
        "columnDefs": [
            { "orderable": false, "targets": [7] }  // 操作列はソート不可
        ]
    });
}

/**
 * 統計情報の読み込み
 */
function loadReceiptStatistics() {
    $.ajax({
        url: '../api/receipts.php',
        method: 'GET',
        data: {
            action: 'statistics',
            month: new Date().getFullYear() + '-' + String(new Date().getMonth() + 1).padStart(2, '0')
        },
        success: function(response) {
            if (response.success) {
                const stats = response.data;
                $('#issued-receipts').text(stats.issued_count || 0);
                $('#total-amount').text('¥' + (stats.total_amount || 0).toLocaleString());
                $('#stamp-required-count').text(stats.stamp_required_count || 0);
                $('#advance-receipts').text(stats.advance_count || 0);
            }
        },
        error: function() {
            console.error('統計データの読み込みに失敗しました');
        }
    });
}

/**
 * 領収書一覧の読み込み
 */
function loadReceiptList() {
    const dateFrom = $('#date-from').val();
    const dateTo = $('#date-to').val();
    const status = $('#status-filter').val();
    const type = $('#type-filter').val();
    const stamp = $('#stamp-filter').val();
    
    $.ajax({
        url: '../api/receipts.php',
        method: 'GET',
        data: {
            action: 'list',
            date_from: dateFrom,
            date_to: dateTo,
            status: status,
            receipt_type: type,
            stamp_required: stamp,
            limit: 100
        },
        success: function(response) {
            if (response.success) {
                renderReceiptList(response.data.receipts);
            } else {
                showAlert('エラー', response.message, 'danger');
            }
        },
        error: function() {
            showAlert('エラー', 'データの読み込みに失敗しました', 'danger');
        }
    });
}

/**
 * 領収書一覧の描画
 */
function renderReceiptList(receipts) {
    const tbody = $('#receipts-tbody');
    tbody.empty();
    
    receipts.forEach(function(receipt) {
        const row = `
            <tr class="status-${receipt.status}">
                <td>
                    <a href="#" onclick="showReceiptDetail(${receipt.id})" class="text-decoration-none">
                        ${receipt.receipt_number}
                    </a>
                </td>
                <td>${formatDate(receipt.issue_date)}</td>
                <td>${receipt.recipient_name}</td>
                <td class="text-end">¥${parseInt(receipt.amount).toLocaleString()}</td>
                <td>
                    <span class="badge ${getReceiptTypeBadgeClass(receipt.receipt_type)}">
                        ${getReceiptTypeText(receipt.receipt_type)}
                    </span>
                </td>
                <td class="text-center">
                    <span class="${receipt.stamp_required ? 'stamp-required' : 'stamp-not-required'}">
                        ${receipt.stamp_required ? '要' : '不要'}
                    </span>
                </td>
                <td>
                    <span class="badge ${getStatusBadgeClass(receipt.status)}">
                        ${getStatusText(receipt.status)}
                    </span>
                </td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary" onclick="showReceiptDetail(${receipt.id})" 
                                title="詳細表示">
                            <i class="bi bi-eye"></i>
                        </button>
                        <button class="btn btn-outline-success" onclick="downloadReceiptPDF(${receipt.id})" 
                                title="PDF出力">
                            <i class="bi bi-download"></i>
                        </button>
                        <button class="btn btn-outline-warning" onclick="reissueReceipt(${receipt.id})" 
                                title="再発行">
                            <i class="bi bi-arrow-clockwise"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
        tbody.append(row);
    });
}

/**
 * 領収書生成モーダルの表示
 */
function showReceiptGenerateModal() {
    $('#receiptGenerateModal').modal('show');
}

/**
 * 領収書生成
 */
function generateReceipt() {
    const formData = new FormData($('#receipt-generate-form')[0]);
    const data = Object.fromEntries(formData.entries());
    
    // バリデーション
    if (!data.recipient_name || !data.amount) {
        showAlert('エラー', '受領者名と金額は必須です', 'danger');
        return;
    }
    
    $.ajax({
        url: '../api/receipts.php',
        method: 'POST',
        data: JSON.stringify({
            action: 'generate',
            ...data
        }),
        contentType: 'application/json',
        success: function(response) {
            if (response.success) {
                showAlert('成功', '領収書を生成しました', 'success');
                $('#receiptGenerateModal').modal('hide');
                $('#receipt-generate-form')[0].reset();
                loadReceiptList();
                loadReceiptStatistics();
            } else {
                showAlert('エラー', response.message, 'danger');
            }
        },
        error: function() {
            showAlert('エラー', '領収書の生成に失敗しました', 'danger');
        }
    });
}

/**
 * フィルター適用
 */
function applyFilters() {
    loadReceiptList();
}

/**
 * 領収書一覧の更新
 */
function refreshReceiptList() {
    loadReceiptList();
    loadReceiptStatistics();
    showAlert('完了', '一覧を更新しました', 'info', 2000);
}

/**
 * 詳細検索モーダルの表示
 */
function showAdvancedSearchModal() {
    $('#advancedSearchModal').modal('show');
}

/**
 * 詳細検索の実行
 */
function performAdvancedSearch() {
    const formData = new FormData($('#advanced-search-form')[0]);
    const data = Object.fromEntries(formData.entries());
    
    $.ajax({
        url: '../api/receipts.php',
        method: 'GET',
        data: {
            action: 'search',
            ...data
        },
        success: function(response) {
            if (response.success) {
                renderReceiptList(response.data.receipts);
                $('#advancedSearchModal').modal('hide');
                showAlert('完了', `${response.data.receipts.length}件の領収書が見つかりました`, 'info');
            } else {
                showAlert('エラー', response.message, 'danger');
            }
        },
        error: function() {
            showAlert('エラー', '検索に失敗しました', 'danger');
        }
    });
}

/**
 * 領収書詳細の表示
 */
function showReceiptDetail(receiptId) {
    $.ajax({
        url: '../api/receipts.php',
        method: 'GET',
        data: {
            action: 'detail',
            receipt_id: receiptId
        },
        success: function(response) {
            if (response.success) {
                const receipt = response.data;
                renderReceiptDetail(receipt);
                $('#receiptDetailModal').modal('show');
            } else {
                showAlert('エラー', response.message, 'danger');
            }
        },
        error: function() {
            showAlert('エラー', '詳細情報の取得に失敗しました', 'danger');
        }
    });
}

/**
 * 領収書詳細の描画
 */
function renderReceiptDetail(receipt) {
    const content = `
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">基本情報</h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <tr>
                                <th>領収書番号:</th>
                                <td>${receipt.receipt_number}</td>
                            </tr>
                            <tr>
                                <th>発行日:</th>
                                <td>${formatDate(receipt.issue_date)}</td>
                            </tr>
                            <tr>
                                <th>受領者名:</th>
                                <td>${receipt.recipient_name}</td>
                            </tr>
                            <tr>
                                <th>但し書き:</th>
                                <td>${receipt.purpose}</td>
                            </tr>
                            <tr>
                                <th>タイプ:</th>
                                <td>
                                    <span class="badge ${getReceiptTypeBadgeClass(receipt.receipt_type)}">
                                        ${getReceiptTypeText(receipt.receipt_type)}
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th>ステータス:</th>
                                <td>
                                    <span class="badge ${getStatusBadgeClass(receipt.status)}">
                                        ${getStatusText(receipt.status)}
                                    </span>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">金額詳細</h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <tr>
                                <th>金額:</th>
                                <td class="text-end">¥${parseInt(receipt.amount).toLocaleString()}</td>
                            </tr>
                            <tr>
                                <th>消費税額:</th>
                                <td class="text-end">¥${parseInt(receipt.tax_amount || 0).toLocaleString()}</td>
                            </tr>
                            <tr>
                                <th>収入印紙:</th>
                                <td class="${receipt.stamp_required ? 'stamp-required' : 'stamp-not-required'}">
                                    ${receipt.stamp_required ? '要（¥200）' : '不要'}
                                </td>
                            </tr>
                            <tr>
                                <th>印紙代:</th>
                                <td class="text-end">¥${parseInt(receipt.stamp_amount || 0).toLocaleString()}</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mt-3">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">関連情報</h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <tr>
                                <th>関連請求書ID:</th>
                                <td>${receipt.invoice_id || '-'}</td>
                            </tr>
                            <tr>
                                <th>関連支払いID:</th>
                                <td>${receipt.payment_id || '-'}</td>
                            </tr>
                            <tr>
                                <th>PDFファイル:</th>
                                <td>${receipt.file_path ? 'あり' : 'なし'}</td>
                            </tr>
                            <tr>
                                <th>備考:</th>
                                <td>${receipt.notes || '-'}</td>
                            </tr>
                            <tr>
                                <th>作成日時:</th>
                                <td>${formatDateTime(receipt.created_at)}</td>
                            </tr>
                            <tr>
                                <th>更新日時:</th>
                                <td>${formatDateTime(receipt.updated_at)}</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    $('#receipt-detail-content').html(content);
    
    // ボタンにイベントを設定
    $('#download-pdf-btn').off('click').on('click', function() {
        downloadReceiptPDF(receipt.id);
    });
    
    $('#reissue-receipt-btn').off('click').on('click', function() {
        reissueReceipt(receipt.id);
    });
}

/**
 * PDF出力
 */
function downloadReceiptPDF(receiptId) {
    window.open(`../api/receipts.php?action=pdf&receipt_id=${receiptId}`, '_blank');
}

/**
 * 領収書再発行
 */
function reissueReceipt(receiptId) {
    if (!confirm('領収書を再発行しますか？')) {
        return;
    }
    
    $.ajax({
        url: '../api/receipts.php',
        method: 'POST',
        data: JSON.stringify({
            action: 'reissue',
            receipt_id: receiptId
        }),
        contentType: 'application/json',
        success: function(response) {
            if (response.success) {
                showAlert('成功', '領収書を再発行しました', 'success');
                loadReceiptList();
                loadReceiptStatistics();
            } else {
                showAlert('エラー', response.message, 'danger');
            }
        },
        error: function() {
            showAlert('エラー', '再発行に失敗しました', 'danger');
        }
    });
}

/**
 * 一括PDF出力
 */
function exportReceiptList() {
    const dateFrom = $('#date-from').val();
    const dateTo = $('#date-to').val();
    
    if (!confirm(`${dateFrom}から${dateTo}の期間の領収書をまとめてPDF出力しますか？`)) {
        return;
    }
    
    window.open(`../api/receipts.php?action=export&date_from=${dateFrom}&date_to=${dateTo}`, '_blank');
}

/**
 * ユーティリティ関数群
 */

// 日付フォーマット
function formatDate(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('ja-JP');
}

// 日時フォーマット
function formatDateTime(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('ja-JP') + ' ' + date.toLocaleTimeString('ja-JP');
}

// 領収書タイプの表示テキスト
function getReceiptTypeText(type) {
    const types = {
        'advance': '事前領収書',
        'payment': '正式領収書',
        'split': '分割領収書'
    };
    return types[type] || type;
}

// 領収書タイプのバッジクラス
function getReceiptTypeBadgeClass(type) {
    const classes = {
        'advance': 'bg-warning',
        'payment': 'bg-success',
        'split': 'bg-info'
    };
    return classes[type] || 'bg-secondary';
}

// ステータスの表示テキスト
function getStatusText(status) {
    const statuses = {
        'draft': '下書き',
        'issued': '発行済み',
        'delivered': '配達済み'
    };
    return statuses[status] || status;
}

// ステータスのバッジクラス
function getStatusBadgeClass(status) {
    const classes = {
        'draft': 'bg-secondary',
        'issued': 'bg-primary',
        'delivered': 'bg-success'
    };
    return classes[status] || 'bg-secondary';
}

/**
 * アラート表示
 */
function showAlert(title, message, type = 'info', duration = 5000) {
    const alertId = 'alert-' + Date.now();
    const alertHtml = `
        <div id="${alertId}" class="alert alert-${type} alert-dismissible fade show position-fixed" 
             style="top: 20px; right: 20px; z-index: 9999; min-width: 300px;">
            <strong>${title}:</strong> ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    $('body').append(alertHtml);
    
    // 自動削除
    setTimeout(() => {
        $(`#${alertId}`).alert('close');
    }, duration);
}

// Enter キーでの検索
$(document).on('keypress', '#receipt-generate-form input, #advanced-search-form input', function(e) {
    if (e.which === 13) {
        e.preventDefault();
        const form = $(this).closest('form');
        if (form.attr('id') === 'receipt-generate-form') {
            generateReceipt();
        } else if (form.attr('id') === 'advanced-search-form') {
            performAdvancedSearch();
        }
    }
});

// フィルター変更時の自動検索
$('#status-filter, #type-filter, #stamp-filter').on('change', function() {
    applyFilters();
});

</script>

</body>
</html>
