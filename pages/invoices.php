<?php
/**
 * 請求書一覧・管理画面
 * Smiley配食事業の請求書一覧表示、検索、ステータス管理
 * 
 * @author Claude
 * @version 1.1.0 - 構文エラー修正版
 * @created 2025-08-28
 */

require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/SecurityHelper.php';

// セキュリティヘッダー設定
SecurityHelper::setSecurityHeaders();

$pageTitle = '請求書一覧 - Smiley配食事業システム';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" rel="stylesheet">
    <style>
        :root {
            --smiley-primary: #ff6b35;
            --smiley-secondary: #ffa500;
            --smiley-accent: #ffeb3b;
            --smiley-success: #4caf50;
            --smiley-warning: #ff9800;
            --smiley-danger: #f44336;
        }

        .smiley-header {
            background: linear-gradient(135deg, var(--smiley-primary), var(--smiley-secondary));
            color: white;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 2rem;
        }

        .filter-card {
            border: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border-radius: 12px;
            margin-bottom: 2rem;
        }

        .filter-card .card-header {
            background: linear-gradient(90deg, #f8f9fa, #e9ecef);
            border-bottom: 2px solid var(--smiley-primary);
            border-radius: 12px 12px 0 0 !important;
            font-weight: 600;
        }

        .invoices-card {
            border: none;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            border-radius: 12px;
        }

        .invoice-table {
            margin-bottom: 0;
        }

        .invoice-table th {
            background-color: #f8f9fa;
            border-top: none;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .invoice-table td {
            vertical-align: middle;
            font-size: 0.9rem;
        }

        .status-badge {
            font-size: 0.8rem;
            padding: 0.4em 0.8em;
            border-radius: 15px;
        }

        .status-issued { background-color: #e3f2fd; color: #1976d2; }
        .status-sent { background-color: #f3e5f5; color: #7b1fa2; }
        .status-paid { background-color: #e8f5e8; color: #2e7d32; }
        .status-overdue { background-color: #ffebee; color: #d32f2f; }
        .status-cancelled { background-color: #f5f5f5; color: #616161; }
        .status-draft { background-color: #fff3e0; color: #f57c00; }

        .type-badge {
            font-size: 0.8rem;
            padding: 0.3em 0.6em;
            border-radius: 10px;
        }

        .type-company_bulk { background-color: #e1f5fe; color: #0277bd; }
        .type-department_bulk { background-color: #f1f8e9; color: #558b2f; }
        .type-individual { background-color: #fce4ec; color: #c2185b; }
        .type-mixed { background-color: #f3e5f5; color: #7b1fa2; }

        .btn-action {
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
            border-radius: 4px;
        }

        .statistics-row {
            background: linear-gradient(135deg, #e3f2fd, #bbdefb);
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            text-align: center;
            padding: 1rem;
        }

        .stat-value {
            font-size: 1.8rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            font-size: 0.9rem;
            color: #666;
        }

        .search-box {
            position: relative;
        }

        .search-box .form-control {
            padding-left: 2.5rem;
        }

        .search-box .fa-search {
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }

        .pagination .page-link {
            color: var(--smiley-primary);
            border-color: #dee2e6;
        }

        .pagination .page-link:hover {
            color: var(--smiley-secondary);
            background-color: rgba(255, 107, 53, 0.1);
        }

        .pagination .page-item.active .page-link {
            background-color: var(--smiley-primary);
            border-color: var(--smiley-primary);
        }

        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #6c757d;
        }

        .action-buttons {
            white-space: nowrap;
        }

        .loading-row td {
            text-align: center;
            padding: 2rem;
            color: #6c757d;
        }

        @media (max-width: 768px) {
            .invoice-table {
                font-size: 0.8rem;
            }
            
            .stat-card {
                margin-bottom: 1rem;
            }
        }
    </style>
</head>
<body class="bg-light">
    <!-- ナビゲーション -->
    <nav class="navbar navbar-expand-lg navbar-dark" style="background: linear-gradient(135deg, var(--smiley-primary), var(--smiley-secondary));">
        <div class="container">
            <a class="navbar-brand" href="../index.php">
                <i class="fas fa-utensils me-2"></i>Smiley配食事業システム
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="../pages/companies.php">企業管理</a>
                <a class="nav-link" href="../pages/departments.php">部署管理</a>
                <a class="nav-link" href="../pages/users.php">利用者管理</a>
                <a class="nav-link" href="../pages/invoice_generate.php">請求書生成</a>
                <a class="nav-link active" href="../pages/invoices.php">請求書一覧</a>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <!-- ヘッダー -->
        <div class="smiley-header text-center">
            <h1><i class="fas fa-file-invoice me-3"></i>請求書一覧・管理</h1>
            <p class="mb-0">発行済み請求書の確認、検索、ステータス管理を行います</p>
        </div>

        <!-- 統計情報 -->
        <div class="statistics-row" id="statisticsSection">
            <div class="row" id="statisticsContent">
                <div class="col-md-3 col-6">
                    <div class="stat-card">
                        <div class="stat-value text-primary" id="totalInvoices">-</div>
                        <div class="stat-label">総請求書数</div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-card">
                        <div class="stat-value text-success" id="totalAmount">¥-</div>
                        <div class="stat-label">総請求金額</div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-card">
                        <div class="stat-value text-info" id="paidAmount">¥-</div>
                        <div class="stat-label">入金済み</div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-card">
                        <div class="stat-value text-warning" id="pendingAmount">¥-</div>
                        <div class="stat-label">未回収</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- フィルター -->
        <div class="card filter-card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-filter me-2"></i>検索・フィルター</h5>
            </div>
            <div class="card-body">
                <form id="filterForm">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label for="searchKeyword" class="form-label">キーワード検索</label>
                            <div class="search-box">
                                <i class="fas fa-search"></i>
                                <input type="text" class="form-control" id="searchKeyword" placeholder="請求書番号・企業名">
                            </div>
                        </div>
                        <div class="col-md-2 mb-3">
                            <label for="statusFilter" class="form-label">ステータス</label>
                            <select class="form-select" id="statusFilter">
                                <option value="">全て</option>
                                <option value="draft">下書き</option>
                                <option value="issued">発行済み</option>
                                <option value="sent">送付済み</option>
                                <option value="paid">支払済み</option>
                                <option value="overdue">期限超過</option>
                                <option value="cancelled">キャンセル</option>
                            </select>
                        </div>
                        <div class="col-md-2 mb-3">
                            <label for="typeFilter" class="form-label">タイプ</label>
                            <select class="form-select" id="typeFilter">
                                <option value="">全て</option>
                                <option value="company_bulk">企業一括</option>
                                <option value="department_bulk">部署別</option>
                                <option value="individual">個人請求</option>
                                <option value="mixed">混合請求</option>
                            </select>
                        </div>
                        <div class="col-md-2 mb-3">
                            <label for="periodStart" class="form-label">期間開始</label>
                            <input type="text" class="form-control" id="periodStart" placeholder="YYYY-MM-DD">
                        </div>
                        <div class="col-md-2 mb-3">
                            <label for="periodEnd" class="form-label">期間終了</label>
                            <input type="text" class="form-control" id="periodEnd" placeholder="YYYY-MM-DD">
                        </div>
                        <div class="col-md-1 mb-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <button type="button" class="btn btn-outline-secondary btn-sm me-2" onclick="clearFilters()">
                                        <i class="fas fa-times me-1"></i>クリア
                                    </button>
                                    <button type="button" class="btn btn-outline-info btn-sm" onclick="exportToCSV()">
                                        <i class="fas fa-download me-1"></i>CSV出力
                                    </button>
                                </div>
                                <div>
                                    <span class="text-muted">表示件数: </span>
                                    <select class="form-select form-select-sm d-inline-block w-auto" id="limitSelect">
                                        <option value="20">20件</option>
                                        <option value="50" selected>50件</option>
                                        <option value="100">100件</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- 請求書一覧 -->
        <div class="card invoices-card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover invoice-table">
                        <thead>
                            <tr>
                                <th>請求書番号</th>
                                <th>タイプ</th>
                                <th>企業名</th>
                                <th>発行日</th>
                                <th>支払期限</th>
                                <th>金額</th>
                                <th>ステータス</th>
                                <th class="text-center">操作</th>
                            </tr>
                        </thead>
                        <tbody id="invoicesTableBody">
                            <tr class="loading-row">
                                <td colspan="8">
                                    <i class="fas fa-spinner fa-spin me-2"></i>読み込み中...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <!-- ページネーション -->
                <div class="d-flex justify-content-between align-items-center p-3">
                    <div class="text-muted" id="paginationInfo">
                        <!-- ページ情報 -->
                    </div>
                    <nav>
                        <ul class="pagination pagination-sm mb-0" id="pagination">
                            <!-- ページネーション -->
                        </ul>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <!-- 請求書詳細モーダル -->
    <div class="modal fade" id="invoiceDetailModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-file-invoice me-2"></i>請求書詳細</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="invoiceDetailContent">
                        <!-- 詳細がここに表示される -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">閉じる</button>
                    <button type="button" class="btn btn-primary" id="printInvoice">
                        <i class="fas fa-print me-2"></i>印刷
                    </button>
                    <button type="button" class="btn btn-success" id="downloadPDF">
                        <i class="fas fa-download me-2"></i>PDF
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ステータス更新モーダル -->
    <div class="modal fade" id="statusUpdateModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>ステータス更新</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="statusUpdateForm">
                        <input type="hidden" id="updateInvoiceId">
                        <div class="mb-3">
                            <label for="newStatus" class="form-label">新しいステータス</label>
                            <select class="form-select" id="newStatus" required>
                                <option value="">選択してください</option>
                                <option value="draft">下書き</option>
                                <option value="issued">発行済み</option>
                                <option value="sent">送付済み</option>
                                <option value="paid">支払済み</option>
                                <option value="overdue">期限超過</option>
                                <option value="cancelled">キャンセル</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="updateNotes" class="form-label">備考</label>
                            <textarea class="form-control" id="updateNotes" rows="3" placeholder="ステータス変更の理由など"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
                    <button type="button" class="btn btn-primary" onclick="updateStatus()">
                        <i class="fas fa-save me-2"></i>更新
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- 削除確認モーダル -->
    <div class="modal fade" id="deleteConfirmModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-danger"><i class="fas fa-exclamation-triangle me-2"></i>削除確認</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>この請求書を削除してもよろしいですか？</p>
                    <p class="text-muted">※削除された請求書はキャンセル状態になります。</p>
                    <div id="deleteInvoiceInfo" class="bg-light p-3 rounded">
                        <!-- 削除対象の請求書情報 -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
                    <button type="button" class="btn btn-danger" onclick="confirmDelete()">
                        <i class="fas fa-trash me-2"></i>削除
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/ja.js"></script>
    
    <script>
        // グローバル変数
        let currentPage = 1;
        let currentFilters = {};
        let currentInvoiceId = null;
        
        // 初期化
        document.addEventListener('DOMContentLoaded', function() {
            initializeDatePickers();
            initializeEventListeners();
            loadStatistics();
            loadInvoices();
        });
        
        // 日付ピッカー初期化
        function initializeDatePickers() {
            const commonConfig = {
                locale: 'ja',
                dateFormat: 'Y-m-d',
                allowInput: true
            };
            
            flatpickr('#periodStart', commonConfig);
            flatpickr('#periodEnd', commonConfig);
        }
        
        // イベントリスナー初期化
        function initializeEventListeners() {
            // フィルターフォーム
            document.getElementById('filterForm').addEventListener('submit', function(e) {
                e.preventDefault();
                currentPage = 1;
                loadInvoices();
            });
            
            // 表示件数変更
            document.getElementById('limitSelect').addEventListener('change', function() {
                currentPage = 1;
                loadInvoices();
            });
            
            // リアルタイム検索（デバウンス）
            let searchTimeout;
            document.getElementById('searchKeyword').addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    currentPage = 1;
                    loadInvoices();
                }, 500);
            });
        }
        
        // 統計情報読み込み
        function loadStatistics() {
            fetch('../api/invoices.php?action=statistics')
                .then(response => {
                    if (!response.ok) {
                        throw new Error('HTTP ' + response.status + ': ' + response.statusText);
                    }
                    return response.text().then(text => {
                        try {
                            return JSON.parse(text);
                        } catch (e) {
                            console.warn('API Response is not JSON:', text.substring(0, 100));
                            throw new Error('APIが存在しないか、JSON形式でないレスポンスです');
                        }
                    });
                })
                .then(data => {
                    if (data.success) {
                        updateStatistics(data.data);
                    } else {
                        console.warn('Statistics load warning:', data.error);
                        updateStatistics({
                            total_invoices: 0,
                            total_amount: 0,
                            paid_amount: 0,
                            pending_amount: 0
                        });
                    }
                })
                .catch(error => {
                    console.error('Statistics load error:', error);
                    // API未実装の場合のデモデータ
                    updateStatistics({
                        total_invoices: 0,
                        total_amount: 0,
                        paid_amount: 0,
                        pending_amount: 0
                    });
                });
        }
        
        // 統計情報更新
        function updateStatistics(stats) {
            document.getElementById('totalInvoices').textContent = stats.total_invoices || 0;
            document.getElementById('totalAmount').textContent = '¥' + (parseFloat(stats.total_amount) || 0).toLocaleString();
            document.getElementById('paidAmount').textContent = '¥' + (parseFloat(stats.paid_amount) || 0).toLocaleString();
            document.getElementById('pendingAmount').textContent = '¥' + (parseFloat(stats.pending_amount) || 0).toLocaleString();
        }
        
        // 請求書一覧読み込み
        function loadInvoices() {
            const tableBody = document.getElementById('invoicesTableBody');
            tableBody.innerHTML = '<tr class="loading-row"><td colspan="8"><i class="fas fa-spinner fa-spin me-2"></i>読み込み中...</td></tr>';
            
            // フィルター取得
            const filters = getFilters();
            const limit = document.getElementById('limitSelect').value;
            
            // URLパラメータ構築
            const params = new URLSearchParams({
                action: 'list',
                page: currentPage,
                limit: limit
            });
            
            // フィルター追加
            for (const key in filters) {
                if (filters[key]) {
                    params.append(key, filters[key]);
                }
            }
            
            fetch('../api/invoices.php?' + params.toString())
                .then(response => {
                    if (!response.ok) {
                        throw new Error('HTTP ' + response.status + ': ' + response.statusText);
                    }
                    return response.text().then(text => {
                        try {
                            return JSON.parse(text);
                        } catch (e) {
                            console.warn('API Response is not JSON:', text.substring(0, 200));
                            throw new Error('APIレスポンスの解析に失敗しました');
                        }
                    });
                })
                .then(data => {
                    if (data.success) {
                        renderInvoices(data.data);
                        updatePagination(data.data);
                    } else {
                        throw new Error(data.error || '請求書データの読み込みに失敗しました');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    // SmileyInvoiceGeneratorクラスが未実装の場合のデモ表示
                    if (error.message.includes('SmileyInvoiceGenerator') || error.message.includes('Fatal error')) {
                        tableBody.innerHTML = '<tr><td colspan="8" class="text-center text-warning py-4"><i class="fas fa-info-circle me-2"></i><div class="h5">請求書機能準備中</div><p class="text-muted mb-3">SmileyInvoiceGeneratorクラスをデプロイ中です。しばらくお待ちください。</p><a href="../pages/invoice_test.php" class="btn btn-primary"><i class="fas fa-vial me-2"></i>テスト機能を確認</a></td></tr>';
                    } else {
                        tableBody.innerHTML = '<tr><td colspan="8" class="text-center text-danger py-4"><i class="fas fa-exclamation-triangle me-2"></i>読み込みエラー: ' + error.message + '</td></tr>';
                    }
                });
        }
        
        // フィルター取得
        function getFilters() {
            const filters = {};
            
            const keyword = document.getElementById('searchKeyword').value.trim();
            if (keyword) filters.keyword = keyword;
            
            const status = document.getElementById('statusFilter').value;
            if (status) filters.status = status;
            
            const type = document.getElementById('typeFilter').value;
            if (type) filters.invoice_type = type;
            
            const periodStart = document.getElementById('periodStart').value;
            if (periodStart) filters.period_start = periodStart;
            
            const periodEnd = document.getElementById('periodEnd').value;
            if (periodEnd) filters.period_end = periodEnd;
            
            currentFilters = filters;
            return filters;
        }
        
        // 請求書一覧表示
        function renderInvoices(data) {
            const tableBody = document.getElementById('invoicesTableBody');
            
            if (!data.invoices || data.invoices.length === 0) {
                tableBody.innerHTML = '<tr><td colspan="8" class="empty-state"><i class="fas fa-inbox fa-3x mb-3 text-muted"></i><div class="h5">請求書が見つかりません</div><p class="text-muted">検索条件を変更するか、新しい請求書を生成してください。</p><a href="../pages/invoice_generate.php" class="btn btn-primary"><i class="fas fa-plus me-2"></i>請求書生成</a></td></tr>';
                return;
            }
            
            let html = '';
            data.invoices.forEach(invoice => {
                const invoiceType = invoice.invoice_type || 'company';
                const status = invoice.status || 'draft';
                
                html += '<tr>';
                html += '<td><a href="#" class="text-decoration-none" onclick="showInvoiceDetail(' + invoice.id + ')"><strong>' + (invoice.invoice_number || '') + '</strong></a></td>';
                html += '<td><span class="badge type-badge type-' + invoiceType + '">' + getInvoiceTypeLabel(invoiceType) + '</span></td>';
                html += '<td><div class="fw-bold">' + (invoice.company_name || '未設定') + '</div>' + (invoice.user_name ? '<small class="text-muted">' + invoice.user_name + '</small>' : '') + '</td>';
                html += '<td>' + formatDate(invoice.invoice_date) + '</td>';
                html += '<td>' + formatDate(invoice.due_date) + (isOverdue(invoice.due_date, status) ? '<i class="fas fa-exclamation-triangle text-danger ms-1" title="期限超過"></i>' : '') + '</td>';
                html += '<td class="text-end"><strong>¥' + parseFloat(invoice.total_amount || 0).toLocaleString() + '</strong></td>';
                html += '<td><span class="badge status-badge status-' + status + '">' + getStatusLabel(status) + '</span></td>';
                html += '<td class="text-center action-buttons">';
                html += '<button type="button" class="btn btn-outline-primary btn-action me-1" onclick="showInvoiceDetail(' + invoice.id + ')" title="詳細"><i class="fas fa-eye"></i></button>';
                html += '<button type="button" class="btn btn-outline-warning btn-action me-1" onclick="showStatusUpdate(' + invoice.id + ', \'' + status + '\')" title="ステータス変更"><i class="fas fa-edit"></i></button>';
                html += '<button type="button" class="btn btn-outline-danger btn-action" onclick="showDeleteConfirm(' + invoice.id + ', \'' + (invoice.invoice_number || '') + '\')" title="削除"><i class="fas fa-trash"></i></button>';
                html += '</td>';
                html += '</tr>';
            });
            
            tableBody.innerHTML = html;
        }
        
        // ページネーション更新
        function updatePagination(data) {
            const paginationInfo = document.getElementById('paginationInfo');
            const pagination = document.getElementById('pagination');
            
            const start = ((data.page - 1) * data.limit) + 1;
            const end = Math.min(data.page * data.limit, data.total_count);
            
            paginationInfo.textContent = start + '-' + end + ' / ' + data.total_count + '件';
            
            // ページネーション構築
            let paginationHtml = '';
            
            // 前へ
            paginationHtml += '<li class="page-item ' + (data.page === 1 ? 'disabled' : '') + '">';
            paginationHtml += '<a class="page-link" href="#" onclick="changePage(' + (data.page - 1) + ')" tabindex="-1"><i class="fas fa-chevron-left"></i></a>';
            paginationHtml += '</li>';
            
            // ページ番号
            const startPage = Math.max(1, data.page - 2);
            const endPage = Math.min(data.total_pages, data.page + 2);
            
            if (startPage > 1) {
                paginationHtml += '<li class="page-item"><a class="page-link" href="#" onclick="changePage(1)">1</a></li>';
                if (startPage > 2) {
                    paginationHtml += '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
                }
            }
            
            for (let i = startPage; i <= endPage; i++) {
                paginationHtml += '<li class="page-item ' + (i === data.page ? 'active' : '') + '">';
                paginationHtml += '<a class="page-link" href="#" onclick="changePage(' + i + ')">' + i + '</a>';
                paginationHtml += '</li>';
            }
            
            if (endPage < data.total_pages) {
                if (endPage < data.total_pages - 1) {
                    paginationHtml += '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
                }
                paginationHtml += '<li class="page-item"><a class="page-link" href="#" onclick="changePage(' + data.total_pages + ')">' + data.total_pages + '</a></li>';
            }
            
            // 次へ
            paginationHtml += '<li class="page-item ' + (data.page === data.total_pages ? 'disabled' : '') + '">';
            paginationHtml += '<a class="page-link" href="#" onclick="changePage(' + (data.page + 1) + ')"><i class="fas fa-chevron-right"></i></a>';
            paginationHtml += '</li>';
            
            pagination.innerHTML = paginationHtml;
        }
        
        // ページ変更
        function changePage(page) {
            if (page < 1) return;
            currentPage = page;
            loadInvoices();
        }
        
        // 請求書詳細表示
        function showInvoiceDetail(invoiceId) {
            const modal = new bootstrap.Modal(document.getElementById('invoiceDetailModal'));
            const content = document.getElementById('invoiceDetailContent');
            
            content.innerHTML = '<div class="text-center py-4"><i class="fas fa-spinner fa-spin me-2"></i>読み込み中...</div>';
            modal.show();
            
            fetch('../api/invoices.php?action=detail&invoice_id=' + invoiceId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        renderInvoiceDetail(data.data);
                        currentInvoiceId = invoiceId;
                    } else {
                        throw new Error(data.error || '詳細の読み込みに失敗しました');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    content.innerHTML = '<div class="text-center text-danger py-4"><i class="fas fa-exclamation-triangle me-2"></i>' + error.message + '</div>';
                });
        }
        
        // 請求書詳細レンダリング
        function renderInvoiceDetail(invoice) {
            const content = document.getElementById('invoiceDetailContent');
            
            let detailsHtml = '';
            if (invoice.details && invoice.details.length > 0) {
                detailsHtml = '<div class="mt-4"><h6><i class="fas fa-list me-2"></i>請求書明細</h6><div class="table-responsive"><table class="table table-sm"><thead><tr><th>配達日</th><th>利用者</th><th>商品名</th><th>数量</th><th>単価</th><th>金額</th></tr></thead><tbody>';
                
                invoice.details.forEach(detail => {
                    detailsHtml += '<tr>';
                    detailsHtml += '<td>' + formatDate(detail.order_date) + '</td>';
                    detailsHtml += '<td>' + (detail.user_name || '-') + '</td>';
                    detailsHtml += '<td>' + detail.product_name + '</td>';
                    detailsHtml += '<td class="text-center">' + detail.quantity + '</td>';
                    detailsHtml += '<td class="text-end">¥' + parseFloat(detail.unit_price || 0).toLocaleString() + '</td>';
                    detailsHtml += '<td class="text-end">¥' + parseFloat(detail.amount || 0).toLocaleString() + '</td>';
                    detailsHtml += '</tr>';
                });
                
                detailsHtml += '</tbody></table></div></div>';
            }
            
            const html = '<div class="row"><div class="col-md-6"><h6><i class="fas fa-info-circle me-2"></i>請求書情報</h6><table class="table table-sm"><tr><td width="120">請求書番号:</td><td><strong>' + (invoice.invoice_number || '') + '</strong></td></tr><tr><td>タイプ:</td><td><span class="badge type-badge type-' + (invoice.invoice_type || 'company') + '">' + getInvoiceTypeLabel(invoice.invoice_type || 'company') + '</span></td></tr><tr><td>ステータス:</td><td><span class="badge status-badge status-' + (invoice.status || 'draft') + '">' + getStatusLabel(invoice.status || 'draft') + '</span></td></tr><tr><td>発行日:</td><td>' + formatDate(invoice.invoice_date) + '</td></tr><tr><td>支払期限:</td><td>' + formatDate(invoice.due_date) + '</td></tr><tr><td>請求期間:</td><td>' + formatDate(invoice.period_start) + ' ～ ' + formatDate(invoice.period_end) + '</td></tr></table></div><div class="col-md-6"><h6><i class="fas fa-building me-2"></i>請求先情報</h6><table class="table table-sm"><tr><td width="120">企業名:</td><td><strong>' + (invoice.company_name || '未設定') + '</strong></td></tr><tr><td>利用者:</td><td>' + (invoice.user_name || '-') + '</td></tr><tr><td>利用者コード:</td><td>' + (invoice.user_code || '-') + '</td></tr><tr><td>部署:</td><td>' + (invoice.department || '-') + '</td></tr></table></div></div><div class="row mt-3"><div class="col-md-6"><h6><i class="fas fa-calculator me-2"></i>金額詳細</h6><table class="table table-sm"><tr><td width="120">小計:</td><td class="text-end">¥' + parseFloat(invoice.subtotal || 0).toLocaleString() + '</td></tr><tr><td>消費税:</td><td class="text-end">¥' + parseFloat(invoice.tax_amount || 0).toLocaleString() + '</td></tr><tr class="fw-bold border-top"><td>合計金額:</td><td class="text-end">¥' + parseFloat(invoice.total_amount || 0).toLocaleString() + '</td></tr></table></div><div class="col-md-6"><h6><i class="fas fa-chart-bar me-2"></i>統計情報</h6><table class="table table-sm"><tr><td width="120">作成日:</td><td>' + formatDateTime(invoice.created_at) + '</td></tr><tr><td>更新日:</td><td>' + formatDateTime(invoice.updated_at) + '</td></tr></table></div></div>' + (invoice.notes ? '<div class="mt-3"><h6><i class="fas fa-sticky-note me-2"></i>備考</h6><div class="bg-light p-3 rounded">' + invoice.notes + '</div></div>' : '') + detailsHtml;
            
            content.innerHTML = html;
        }
        
        // ステータス更新モーダル表示
        function showStatusUpdate(invoiceId, currentStatus) {
            currentInvoiceId = invoiceId;
            document.getElementById('updateInvoiceId').value = invoiceId;
            document.getElementById('newStatus').value = '';
            document.getElementById('updateNotes').value = '';
            
            const modal = new bootstrap.Modal(document.getElementById('statusUpdateModal'));
            modal.show();
        }
        
        // ステータス更新実行
        function updateStatus() {
            const invoiceId = document.getElementById('updateInvoiceId').value;
            const newStatus = document.getElementById('newStatus').value;
            const notes = document.getElementById('updateNotes').value;
            
            if (!newStatus) {
                alert('ステータスを選択してください。');
                return;
            }
            
            const data = {
                action: 'update_status',
                invoice_id: parseInt(invoiceId),
                status: newStatus,
                notes: notes
            };
            
            fetch('../api/invoices.php', {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('ステータスを更新しました。');
                    bootstrap.Modal.getInstance(document.getElementById('statusUpdateModal')).hide();
                    loadInvoices();
                    loadStatistics();
                } else {
                    throw new Error(data.error || 'ステータス更新に失敗しました');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('更新エラー: ' + error.message);
            });
        }
        
        // 削除確認モーダル表示
        function showDeleteConfirm(invoiceId, invoiceNumber) {
            currentInvoiceId = invoiceId;
            
            const infoDiv = document.getElementById('deleteInvoiceInfo');
            infoDiv.innerHTML = '<strong>請求書番号:</strong> ' + invoiceNumber + '<br><strong>請求書ID:</strong> ' + invoiceId;
            
            const modal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
            modal.show();
        }
        
        // 削除実行
        function confirmDelete() {
            if (!currentInvoiceId) return;
            
            const data = {
                invoice_id: currentInvoiceId
            };
            
            fetch('../api/invoices.php', {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('請求書を削除しました。');
                    bootstrap.Modal.getInstance(document.getElementById('deleteConfirmModal')).hide();
                    loadInvoices();
                    loadStatistics();
                } else {
                    throw new Error(data.error || '削除に失敗しました');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('削除エラー: ' + error.message);
            });
        }
        
        // フィルタークリア
        function clearFilters() {
            document.getElementById('searchKeyword').value = '';
            document.getElementById('statusFilter').value = '';
            document.getElementById('typeFilter').value = '';
            document.getElementById('periodStart').value = '';
            document.getElementById('periodEnd').value = '';
            currentPage = 1;
            loadInvoices();
        }
        
        // CSV出力
        function exportToCSV() {
            const filters = getFilters();
            const params = new URLSearchParams({
                action: 'export_csv'
            });
            
            for (const key in filters) {
                if (filters[key]) {
                    params.append(key, filters[key]);
                }
            }
            
            window.location.href = '../api/invoices.php?' + params.toString();
        }
        
        // ユーティリティ関数
        
        // 請求書タイプラベル取得
        function getInvoiceTypeLabel(type) {
            const labels = {
                'company_bulk': '企業一括',
                'department_bulk': '部署別',
                'individual': '個人請求',
                'mixed': '混合請求',
                'company': '企業一括'
            };
            return labels[type] || type;
        }
        
        // ステータスラベル取得
        function getStatusLabel(status) {
            const labels = {
                'draft': '下書き',
                'issued': '発行済み',
                'sent': '送付済み',
                'paid': '支払済み',
                'overdue': '期限超過',
                'cancelled': 'キャンセル'
            };
            return labels[status] || status;
        }
        
        // 日付フォーマット
        function formatDate(dateString) {
            if (!dateString) return '-';
            try {
                const date = new Date(dateString);
                if (isNaN(date.getTime())) return '-';
                return date.toLocaleDateString('ja-JP');
            } catch (e) {
                return '-';
            }
        }
        
        // 日時フォーマット
        function formatDateTime(dateString) {
            if (!dateString) return '-';
            try {
                const date = new Date(dateString);
                if (isNaN(date.getTime())) return '-';
                return date.toLocaleString('ja-JP');
            } catch (e) {
                return '-';
            }
        }
        
        // 期限超過チェック
        function isOverdue(dueDate, status) {
            if (status === 'paid' || status === 'cancelled') return false;
            if (!dueDate) return false;
            try {
                return new Date(dueDate) < new Date();
            } catch (e) {
                return false;
            }
        }
        
        // 印刷
        document.getElementById('printInvoice').addEventListener('click', function() {
            if (currentInvoiceId) {
                window.print();
            }
        });
        
        // PDF ダウンロード
        document.getElementById('downloadPDF').addEventListener('click', function() {
            if (currentInvoiceId) {
                alert('PDF機能は今後実装予定です。');
            }
        });
    </script>
</body>
</html>
