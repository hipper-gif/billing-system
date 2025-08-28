<?php
/**
 * 請求書生成画面
 * Smiley配食事業専用の請求書生成インターフェース
 * 
 * @author Claude
 * @version 2.0.0 - 完全動作版
 * @created 2025-08-28
 */

require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/SecurityHelper.php';

// セキュリティヘッダー設定
SecurityHelper::setSecurityHeaders();

$pageTitle = '請求書生成 - Smiley配食事業システム';
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

        .generation-card {
            border: none;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            border-radius: 12px;
            margin-bottom: 2rem;
        }

        .generation-card .card-header {
            background: linear-gradient(90deg, #f8f9fa, #e9ecef);
            border-bottom: 2px solid var(--smiley-primary);
            border-radius: 12px 12px 0 0 !important;
            font-weight: 600;
        }

        .btn-generate {
            background: linear-gradient(135deg, var(--smiley-primary), var(--smiley-secondary));
            border: none;
            color: white;
            padding: 12px 30px;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-generate:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(255, 107, 53, 0.3);
            color: white;
        }

        .btn-generate:disabled {
            background: #6c757d;
            transform: none;
            box-shadow: none;
        }

        .invoice-type-card {
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .invoice-type-card:hover {
            border-color: var(--smiley-primary);
            box-shadow: 0 2px 8px rgba(255, 107, 53, 0.1);
        }

        .invoice-type-card.selected {
            border-color: var(--smiley-primary);
            background: rgba(255, 107, 53, 0.05);
        }

        .target-selector {
            min-height: 200px;
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 1rem;
            background: white;
        }

        .target-item {
            padding: 12px;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            margin-bottom: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            background: white;
        }

        .target-item:hover {
            border-color: var(--smiley-primary);
            background-color: rgba(255, 107, 53, 0.05);
        }

        .target-item.selected {
            border-color: var(--smiley-primary);
            background-color: rgba(255, 107, 53, 0.1);
        }

        .target-item.selected .form-check-input {
            background-color: var(--smiley-primary);
            border-color: var(--smiley-primary);
        }

        .progress-container {
            display: none;
            margin: 2rem 0;
        }

        .result-container {
            display: none;
            margin: 2rem 0;
        }

        .result-success {
            border-left: 4px solid var(--smiley-success);
            background: rgba(76, 175, 80, 0.1);
        }

        .result-error {
            border-left: 4px solid var(--smiley-danger);
            background: rgba(244, 67, 54, 0.1);
        }

        .statistics-card {
            background: linear-gradient(135deg, #e3f2fd, #bbdefb);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .form-check-input:checked {
            background-color: var(--smiley-primary);
            border-color: var(--smiley-primary);
        }

        .loading-spinner {
            display: none;
        }

        .preview-table {
            font-size: 0.9rem;
        }

        .badge-invoice-type {
            font-size: 0.8rem;
            padding: 0.4em 0.8em;
        }

        .empty-state {
            text-align: center;
            padding: 2rem;
            color: #6c757d;
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
                <a class="nav-link active" href="../pages/invoice_generate.php">請求書生成</a>
                <a class="nav-link" href="../pages/invoices.php">請求書一覧</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- ヘッダー -->
        <div class="smiley-header text-center">
            <h1><i class="fas fa-file-invoice-dollar me-3"></i>請求書生成</h1>
            <p class="mb-0">配達先企業・部署・個人別の請求書を生成します</p>
        </div>

        <!-- 請求書生成フォーム -->
        <div class="card generation-card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-cog me-2"></i>生成設定</h5>
            </div>
            <div class="card-body">
                <form id="invoiceGenerationForm">
                    <div class="row">
                        <!-- 請求書タイプ選択 -->
                        <div class="col-md-6">
                            <h6 class="mb-3"><i class="fas fa-layer-group me-2"></i>請求書タイプ</h6>
                            
                            <div class="invoice-type-card selected" data-type="company_bulk">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="invoice_type" id="type_company" value="company_bulk" checked>
                                    <label class="form-check-label" for="type_company">
                                        <strong>企業一括請求</strong>
                                        <small class="d-block text-muted">配達先企業ごとに一括で請求書を生成</small>
                                    </label>
                                </div>
                            </div>

                            <div class="invoice-type-card" data-type="department_bulk">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="invoice_type" id="type_department" value="department_bulk">
                                    <label class="form-check-label" for="type_department">
                                        <strong>部署別一括請求</strong>
                                        <small class="d-block text-muted">部署ごとに分けて請求書を生成</small>
                                    </label>
                                </div>
                            </div>

                            <div class="invoice-type-card" data-type="individual">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="invoice_type" id="type_individual" value="individual">
                                    <label class="form-check-label" for="type_individual">
                                        <strong>個人請求</strong>
                                        <small class="d-block text-muted">利用者個人ごとに請求書を生成</small>
                                    </label>
                                </div>
                            </div>

                            <div class="invoice-type-card" data-type="mixed">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="invoice_type" id="type_mixed" value="mixed">
                                    <label class="form-check-label" for="type_mixed">
                                        <strong>混合請求（自動判定）</strong>
                                        <small class="d-block text-muted">企業設定に基づいて最適な請求方法を自動選択</small>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- 期間・オプション設定 -->
                        <div class="col-md-6">
                            <h6 class="mb-3"><i class="fas fa-calendar-alt me-2"></i>請求期間</h6>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="period_start" class="form-label">開始日</label>
                                    <input type="text" class="form-control" id="period_start" name="period_start" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="period_end" class="form-label">終了日</label>
                                    <input type="text" class="form-control" id="period_end" name="period_end" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="due_date" class="form-label">支払期限日</label>
                                <input type="text" class="form-control" id="due_date" name="due_date" placeholder="自動計算（期間終了日+30日）">
                            </div>

                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="auto_pdf" name="auto_pdf" checked>
                                    <label class="form-check-label" for="auto_pdf">
                                        PDF自動生成
                                    </label>
                                </div>
                            </div>

                            <!-- 期間テンプレート -->
                            <div class="mb-3">
                                <label class="form-label">期間テンプレート</label>
                                <div class="btn-group-vertical d-grid gap-2">
                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="setPeriodTemplate('this_month')">今月</button>
                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="setPeriodTemplate('last_month')">先月</button>
                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="setPeriodTemplate('custom_range')">過去30日</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 対象選択セクション -->
                    <div id="targetSelection" class="mt-4">
                        <h6 class="mb-3"><i class="fas fa-users me-2"></i>対象選択</h6>
                        <div class="row">
                            <div class="col-md-8">
                                <div id="targetList" class="target-selector">
                                    <div class="text-center text-muted">
                                        <i class="fas fa-spinner fa-spin me-2"></i>読み込み中...
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="statistics-card">
                                    <h6><i class="fas fa-chart-bar me-2"></i>選択状況</h6>
                                    <div id="selectionStats">
                                        <div class="d-flex justify-content-between">
                                            <span>選択数:</span>
                                            <span id="selectedCount">0</span>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <span>総対象数:</span>
                                            <span id="totalCount">0</span>
                                        </div>
                                        <div class="d-flex justify-content-between mt-2">
                                            <span>予想請求書数:</span>
                                            <span id="expectedInvoices">0</span>
                                        </div>
                                    </div>
                                    <div class="mt-3">
                                        <button type="button" class="btn btn-outline-primary btn-sm w-100 mb-2" onclick="selectAll()">全選択</button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm w-100" onclick="selectNone()">選択解除</button>
                                    </div>
                                </div>

                                <!-- プレビュー情報 -->
                                <div class="card mt-3" id="previewCard" style="display: none;">
                                    <div class="card-header">
                                        <h6 class="mb-0"><i class="fas fa-eye me-2"></i>プレビュー</h6>
                                    </div>
                                    <div class="card-body" id="previewContent">
                                        <!-- プレビュー内容 -->
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 生成ボタン -->
                    <div class="text-center mt-4">
                        <button type="submit" class="btn btn-generate btn-lg" id="generateButton">
                            <i class="fas fa-magic me-2"></i>請求書生成
                            <span class="loading-spinner">
                                <i class="fas fa-spinner fa-spin ms-2"></i>
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- 進捗表示 -->
        <div class="progress-container" id="progressContainer">
            <div class="card">
                <div class="card-body">
                    <h6><i class="fas fa-cogs me-2"></i>請求書生成中...</h6>
                    <div class="progress">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" 
                             role="progressbar" style="width: 0%" id="progressBar"></div>
                    </div>
                    <div class="text-center mt-2" id="progressText">準備中...</div>
                </div>
            </div>
        </div>

        <!-- 結果表示 -->
        <div class="result-container" id="resultContainer">
            <div class="card" id="resultCard">
                <div class="card-body" id="resultContent">
                    <!-- 結果内容 -->
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
        let currentTargets = [];
        let selectedTargets = [];
        let currentType = 'company_bulk';
        
        // 初期化
        document.addEventListener('DOMContentLoaded', function() {
            initializeDatePickers();
            initializeEventListeners();
            loadTargets();
            
            // デフォルト期間設定（先月）
            setPeriodTemplate('last_month');
        });
        
        // 日付ピッカー初期化
        function initializeDatePickers() {
            const commonConfig = {
                locale: 'ja',
                dateFormat: 'Y-m-d',
                allowInput: true,
                onChange: function() {
                    updatePreview();
                }
            };
            
            flatpickr('#period_start', commonConfig);
            flatpickr('#period_end', commonConfig);
            flatpickr('#due_date', commonConfig);
        }
        
        // イベントリスナー初期化
        function initializeEventListeners() {
            // 請求書タイプ選択
            document.querySelectorAll('input[name="invoice_type"]').forEach(radio => {
                radio.addEventListener('change', function() {
                    currentType = this.value;
                    updateTypeCardSelection();
                    loadTargets();
                });
            });
            
            // タイプカードクリック
            document.querySelectorAll('.invoice-type-card').forEach(card => {
                card.addEventListener('click', function() {
                    const type = this.dataset.type;
                    document.querySelector('input[value="' + type + '"]').checked = true;
                    currentType = type;
                    updateTypeCardSelection();
                    loadTargets();
                });
            });
            
            // フォーム送信
            document.getElementById('invoiceGenerationForm').addEventListener('submit', function(e) {
                e.preventDefault();
                generateInvoices();
            });
        }
        
        // タイプカード選択状態更新
        function updateTypeCardSelection() {
            document.querySelectorAll('.invoice-type-card').forEach(card => {
                card.classList.remove('selected');
            });
            document.querySelector('.invoice-type-card[data-type="' + currentType + '"]').classList.add('selected');
        }
        
        // 対象一覧読み込み
        function loadTargets() {
            const targetList = document.getElementById('targetList');
            targetList.innerHTML = '<div class="text-center text-muted"><i class="fas fa-spinner fa-spin me-2"></i>読み込み中...</div>';
            
            let action = '';
            switch(currentType) {
                case 'company_bulk':
                case 'mixed':
                    action = 'companies';
                    break;
                case 'department_bulk':
                    action = 'departments';
                    break;
                case 'individual':
                    action = 'users';
                    break;
            }
            
            fetch('../api/invoices.php?action=' + action)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        currentTargets = data.data;
                        renderTargets(data.data);
                    } else {
                        throw new Error(data.error || '対象一覧の読み込みに失敗しました');
                    }
                })
                .catch(error => {
                    console.error('Error loading targets:', error);
                    targetList.innerHTML = '<div class="empty-state"><i class="fas fa-exclamation-triangle text-warning"></i><div class="mt-2">対象一覧の読み込みに失敗しました</div><div class="text-muted">' + error.message + '</div></div>';
                });
        }
        
        // 対象一覧表示
        function renderTargets(targets) {
            const targetList = document.getElementById('targetList');
            
            if (!targets || targets.length === 0) {
                targetList.innerHTML = '<div class="empty-state"><i class="fas fa-inbox fa-2x text-muted"></i><div class="mt-2">対象が見つかりません</div><div class="text-muted">データがないか、期間を調整してください</div></div>';
                updateStats();
                return;
            }
            
            let html = '';
            targets.forEach((target, index) => {
                const isSelected = selectedTargets.includes(target.id);
                const targetInfo = getTargetDisplayInfo(target);
                
                html += '<div class="target-item ' + (isSelected ? 'selected' : '') + '" data-id="' + target.id + '" onclick="toggleTarget(' + target.id + ')">';
                html += '<div class="form-check">';
                html += '<input class="form-check-input" type="checkbox" id="target_' + target.id + '" ' + (isSelected ? 'checked' : '') + ' onclick="event.stopPropagation()">';
                html += '<label class="form-check-label w-100" for="target_' + target.id + '">';
                html += '<div class="fw-bold">' + targetInfo.name + '</div>';
                if (targetInfo.subtitle) {
                    html += '<div class="text-muted small">' + targetInfo.subtitle + '</div>';
                }
                if (targetInfo.stats) {
                    html += '<div class="small text-success">' + targetInfo.stats + '</div>';
                }
                html += '</label>';
                html += '</div>';
                html += '</div>';
            });
            
            targetList.innerHTML = html;
            updateStats();
        }
        
        // 対象表示情報取得
        function getTargetDisplayInfo(target) {
            switch(currentType) {
                case 'company_bulk':
                case 'mixed':
                    return {
                        name: target.company_name,
                        subtitle: '利用者: ' + (target.user_count || 0) + '名, 部署: ' + (target.department_count || 0) + '個',
                        stats: '請求方法: ' + (target.billing_method || '未設定')
                    };
                case 'department_bulk':
                    return {
                        name: target.department_name,
                        subtitle: target.company_name,
                        stats: '利用者: ' + (target.user_count || 0) + '名'
                    };
                case 'individual':
                    return {
                        name: target.user_name,
                        subtitle: (target.company_name || '') + (target.department_name ? ' / ' + target.department_name : ''),
                        stats: '直近30日: ' + (target.recent_order_count || 0) + '件 (¥' + (parseInt(target.recent_total_amount) || 0).toLocaleString() + ')'
                    };
            }
            return { name: 'Unknown', subtitle: '', stats: '' };
        }
        
        // 対象選択切り替え
        function toggleTarget(targetId) {
            const index = selectedTargets.indexOf(targetId);
            const targetElement = document.querySelector('.target-item[data-id="' + targetId + '"]');
            const checkbox = document.getElementById('target_' + targetId);
            
            if (index > -1) {
                selectedTargets.splice(index, 1);
                targetElement.classList.remove('selected');
                checkbox.checked = false;
            } else {
                selectedTargets.push(targetId);
                targetElement.classList.add('selected');
                checkbox.checked = true;
            }
            
            updateStats();
            updatePreview();
        }
        
        // 統計情報更新
        function updateStats() {
            document.getElementById('selectedCount').textContent = selectedTargets.length;
            document.getElementById('totalCount').textContent = currentTargets.length;
            document.getElementById('expectedInvoices').textContent = selectedTargets.length;
        }
        
        // 全選択
        function selectAll() {
            selectedTargets = currentTargets.map(target => target.id);
            document.querySelectorAll('.target-item').forEach(item => {
                item.classList.add('selected');
                const checkbox = item.querySelector('.form-check-input');
                checkbox.checked = true;
            });
            updateStats();
            updatePreview();
        }
        
        // 選択解除
        function selectNone() {
            selectedTargets = [];
            document.querySelectorAll('.target-item').forEach(item => {
                item.classList.remove('selected');
                const checkbox = item.querySelector('.form-check-input');
                checkbox.checked = false;
            });
            updateStats();
            updatePreview();
        }
        
        // 期間テンプレート設定
        function setPeriodTemplate(template) {
            const today = new Date();
            let startDate, endDate;
            
            switch(template) {
                case 'this_month':
                    startDate = new Date(today.getFullYear(), today.getMonth(), 1);
                    endDate = new Date(today.getFullYear(), today.getMonth() + 1, 0);
                    break;
                case 'last_month':
                    startDate = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                    endDate = new Date(today.getFullYear(), today.getMonth(), 0);
                    break;
                case 'this_quarter':
                    const quarterMonth = Math.floor(today.getMonth() / 3) * 3;
                    startDate = new Date(today.getFullYear(), quarterMonth, 1);
                    endDate = new Date(today.getFullYear(), quarterMonth + 3, 0);
                    break;
                case 'custom_range':
                    endDate = new Date(today);
                    startDate = new Date(today.getTime() - (30 * 24 * 60 * 60 * 1000));
                    break;
            }
            
            document.getElementById('period_start').value = formatDate(startDate);
            document.getElementById('period_end').value = formatDate(endDate);
            
            // 支払期限を自動計算（終了日+30日）
            const dueDate = new Date(endDate.getTime() + (30 * 24 * 60 * 60 * 1000));
            document.getElementById('due_date').value = formatDate(dueDate);
            
            updatePreview();
        }
        
        // 日付フォーマット
        function formatDate(date) {
            return date.getFullYear() + '-' + 
                   String(date.getMonth() + 1).padStart(2, '0') + '-' + 
                   String(date.getDate()).padStart(2, '0');
        }
        
        // プレビュー更新
        function updatePreview() {
            const previewCard = document.getElementById('previewCard');
            const previewContent = document.getElementById('previewContent');
            
            if (selectedTargets.length === 0) {
                previewCard.style.display = 'none';
                return;
            }
            
            const periodStart = document.getElementById('period_start').value;
            const periodEnd = document.getElementById('period_end').value;
            const dueDate = document.getElementById('due_date').value;
            
            if (!periodStart || !periodEnd) {
                previewCard.style.display = 'none';
                return;
            }
            
            let html = '<div class="small">';
            html += '<div><strong>期間:</strong> ' + periodStart + ' ～ ' + periodEnd + '</div>';
            html += '<div><strong>支払期限:</strong> ' + (dueDate || '自動計算') + '</div>';
            html += '<div><strong>生成数:</strong> ' + selectedTargets.length + '件</div>';
            html += '<div><strong>タイプ:</strong> ' + getTypeLabel(currentType) + '</div>';
            html += '</div>';
            
            previewContent.innerHTML = html;
            previewCard.style.display = 'block';
        }
        
        // タイプラベル取得
        function getTypeLabel(type) {
            const labels = {
                'company_bulk': '企業一括',
                'department_bulk': '部署別',
                'individual': '個人請求',
                'mixed': '混合請求'
            };
            return labels[type] || type;
        }
        
        // 請求書生成実行
        function generateInvoices() {
            if (selectedTargets.length === 0) {
                alert('対象を選択してください。');
                return;
            }
            
            const periodStart = document.getElementById('period_start').value;
            const periodEnd = document.getElementById('period_end').value;
            
            if (!periodStart || !periodEnd) {
                alert('請求期間を設定してください。');
                return;
            }
            
            // 確認ダイアログ
            if (!confirm('選択した条件で請求書を生成しますか？\n\n' +
                        '対象数: ' + selectedTargets.length + '件\n' +
                        '期間: ' + periodStart + ' ～ ' + periodEnd + '\n' +
                        'タイプ: ' + getTypeLabel(currentType))) {
                return;
            }
            
            // UI更新
            const generateButton = document.getElementById('generateButton');
            const progressContainer = document.getElementById('progressContainer');
            const resultContainer = document.getElementById('resultContainer');
            
            generateButton.disabled = true;
            generateButton.querySelector('.loading-spinner').style.display = 'inline';
            progressContainer.style.display = 'block';
            resultContainer.style.display = 'none';
            
            // 進捗更新
            updateProgress(0, '請求書生成を開始しています...');
            
            // 生成パラメータ
            const formData = new FormData();
            formData.append('action', 'generate');
            formData.append('invoice_type', currentType);
            formData.append('period_start', periodStart);
            formData.append('period_end', periodEnd);
            formData.append('due_date', document.getElementById('due_date').value);
            formData.append('auto_generate_pdf', document.getElementById('auto_pdf').checked ? '1' : '0');
            
            // 選択された対象IDを追加
            selectedTargets.forEach(targetId => {
                formData.append('target_ids[]', targetId);
            });
            
            // 生成実行
            fetch('../api/invoices.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                updateProgress(50, '請求書データを処理中...');
                return response.json();
            })
            .then(data => {
                updateProgress(100, '完了');
                
                if (data.success) {
                    showSuccessResult(data.data);
                } else {
                    throw new Error(data.error || '請求書生成に失敗しました');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showErrorResult(error.message);
            })
            .finally(() => {
                // UI復旧
                generateButton.disabled = false;
                generateButton.querySelector('.loading-spinner').style.display = 'none';
                
                setTimeout(() => {
                    progressContainer.style.display = 'none';
                }, 2000);
            });
        }
        
        // 進捗更新
        function updateProgress(percentage, text) {
            const progressBar = document.getElementById('progressBar');
            const progressText = document.getElementById('progressText');
            
            progressBar.style.width = percentage + '%';
            progressText.textContent = text;
        }
        
        // 成功結果表示
        function showSuccessResult(data) {
            const resultContainer = document.getElementById('resultContainer');
            const resultCard = document.getElementById('resultCard');
            const resultContent = document.getElementById('resultContent');
            
            resultCard.className = 'card result-success';
            
            let html = '<h6 class="text-success"><i class="fas fa-check-circle me-2"></i>請求書生成完了</h6>';
            html += '<div class="row mt-3">';
            
            // 統計情報
            html += '<div class="col-md-6">';
            html += '<h6>生成結果</h6>';
            html += '<table class="table table-sm">';
            html += '<tr><td>生成数:</td><td><strong>' + (data.generated_invoices || data.total_invoices || 0) + '件</strong></td></tr>';
            html += '<tr><td>総金額:</td><td><strong>¥' + (parseFloat(data.total_amount || 0).toLocaleString()) + '</strong></td></tr>';
            html += '<tr><td>タイプ:</td><td>' + getTypeLabel(data.type || currentType) + '</td></tr>';
            html += '<tr><td>生成日時:</td><td>' + (data.timestamp || new Date().toLocaleString('ja-JP')) + '</td></tr>';
            html += '</table>';
            html += '</div>';
            
            // アクション
            html += '<div class="col-md-6">';
            html += '<h6>次のアクション</h6>';
            html += '<div class="d-grid gap-2">';
            html += '<a href="../pages/invoices.php" class="btn btn-primary"><i class="fas fa-list me-2"></i>請求書一覧を確認</a>';
            html += '<button type="button" class="btn btn-outline-primary" onclick="resetForm()"><i class="fas fa-redo me-2"></i>新しい請求書を生成</button>';
            html += '</div>';
            html += '</div>';
            
            html += '</div>';
            
            // 生成された請求書詳細（もしあれば）
            if (data.invoice_ids && data.invoice_ids.length > 0) {
                html += '<div class="mt-3">';
                html += '<h6>生成された請求書</h6>';
                html += '<div class="table-responsive">';
                html += '<table class="table table-sm preview-table">';
                html += '<thead><tr><th>請求書ID</th><th>タイプ</th><th>対象</th><th>金額</th><th>操作</th></tr></thead>';
                html += '<tbody>';
                
                data.invoice_ids.forEach(id => {
                    html += '<tr>';
                    html += '<td>' + id + '</td>';
                    html += '<td><span class="badge badge-invoice-type bg-primary">' + getTypeLabel(currentType) + '</span></td>';
                    html += '<td>-</td>';
                    html += '<td>-</td>';
                    html += '<td><a href="../pages/invoices.php?id=' + id + '" class="btn btn-outline-primary btn-sm">詳細</a></td>';
                    html += '</tr>';
                });
                
                html += '</tbody></table>';
                html += '</div>';
                html += '</div>';
            }
            
            resultContent.innerHTML = html;
            resultContainer.style.display = 'block';
        }
        
        // エラー結果表示
        function showErrorResult(errorMessage) {
            const resultContainer = document.getElementById('resultContainer');
            const resultCard = document.getElementById('resultCard');
            const resultContent = document.getElementById('resultContent');
            
            resultCard.className = 'card result-error';
            
            let html = '<h6 class="text-danger"><i class="fas fa-exclamation-triangle me-2"></i>請求書生成エラー</h6>';
            html += '<div class="mt-3">';
            html += '<div class="alert alert-danger">' + errorMessage + '</div>';
            html += '</div>';
            
            html += '<div class="mt-3">';
            html += '<h6>対処方法</h6>';
            html += '<ul>';
            html += '<li>選択した期間にデータが存在するか確認してください</li>';
            html += '<li>対象の企業・部署・利用者が有効な状態か確認してください</li>';
            html += '<li>システム管理者にお問い合わせください</li>';
            html += '</ul>';
            html += '</div>';
            
            html += '<div class="d-grid gap-2 mt-3">';
            html += '<button type="button" class="btn btn-outline-primary" onclick="resetForm()"><i class="fas fa-redo me-2"></i>再試行</button>';
            html += '</div>';
            
            resultContent.innerHTML = html;
            resultContainer.style.display = 'block';
        }
        
        // フォームリセット
        function resetForm() {
            // 結果非表示
            document.getElementById('resultContainer').style.display = 'none';
            document.getElementById('progressContainer').style.display = 'none';
            
            // 選択解除
            selectNone();
            
            // プレビューリセット
            document.getElementById('previewCard').style.display = 'none';
            
            // フォーカスを対象選択に移動
            document.getElementById('targetList').scrollIntoView({ behavior: 'smooth' });
        }
        
        // バリデーション
        function validateForm() {
            const periodStart = document.getElementById('period_start').value;
            const periodEnd = document.getElementById('period_end').value;
            
            if (!periodStart || !periodEnd) {
                alert('請求期間を設定してください。');
                return false;
            }
            
            if (new Date(periodStart) > new Date(periodEnd)) {
                alert('期間の設定が正しくありません。');
                return false;
            }
            
            if (selectedTargets.length === 0) {
                alert('対象を選択してください。');
                return false;
            }
            
            return true;
        }
    </script>
</body>
</html>
