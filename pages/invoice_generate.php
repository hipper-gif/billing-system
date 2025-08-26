<?php
/**
 * 請求書生成画面（改良版）
 * カレンダー機能対応・エラーハンドリング強化
 * 
 * @author Claude
 * @version 2.0.0
 * @created 2025-08-26
 */

require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/SecurityHelper.php';

SecurityHelper::setSecurityHeaders();
$pageTitle = '請求書生成 - Smiley Kitchen';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/material_blue.css" rel="stylesheet">
    <style>
        :root {
            --smiley-primary: #4CAF50;
            --smiley-orange: #FF9800;
            --smiley-pink: #E91E63;
            --smiley-light-green: #81C784;
        }

        body {
            background: linear-gradient(135deg, #e8f5e8, #f0f8e8);
        }

        .smiley-header {
            background: linear-gradient(135deg, var(--smiley-primary), var(--smiley-light-green));
            color: white;
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            box-shadow: 0 8px 32px rgba(76, 175, 80, 0.3);
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
            background: linear-gradient(135deg, var(--smiley-primary), var(--smiley-light-green));
            border: none;
            color: white;
            padding: 12px 30px;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-generate:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(76, 175, 80, 0.4);
            color: white;
        }

        .invoice-type-card {
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .invoice-type-card:hover {
            border-color: var(--smiley-primary);
            box-shadow: 0 2px 8px rgba(76, 175, 80, 0.1);
        }

        .invoice-type-card.selected {
            border-color: var(--smiley-primary);
            background: rgba(76, 175, 80, 0.05);
        }

        .calendar-input {
            position: relative;
        }

        .calendar-input .form-control {
            padding-right: 40px;
        }

        .calendar-input .fa-calendar {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--smiley-primary);
            pointer-events: none;
        }

        .target-selector {
            min-height: 200px;
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 1rem;
        }

        .target-item {
            padding: 8px 12px;
            border-radius: 6px;
            margin-bottom: 5px;
            cursor: pointer;
            transition: all 0.2s ease;
            border: 1px solid transparent;
        }

        .target-item:hover {
            background-color: #f8f9fa;
            border-color: var(--smiley-primary);
        }

        .target-item.selected {
            background-color: rgba(76, 175, 80, 0.1);
            border-color: var(--smiley-primary);
            color: #2e7d32;
        }

        .statistics-card {
            background: linear-gradient(135deg, #e3f2fd, #bbdefb);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
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
            border-left: 4px solid var(--smiley-primary);
            background: rgba(76, 175, 80, 0.1);
            padding: 1rem;
            border-radius: 8px;
        }

        .result-error {
            border-left: 4px solid #f44336;
            background: rgba(244, 67, 54, 0.1);
            padding: 1rem;
            border-radius: 8px;
        }

        .loading-spinner {
            display: none;
        }

        .period-templates {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .period-template-btn {
            background: #fff;
            border: 1px solid var(--smiley-primary);
            color: var(--smiley-primary);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .period-template-btn:hover {
            background: var(--smiley-primary);
            color: white;
        }

        @media (max-width: 768px) {
            .generation-card .card-body {
                padding: 1rem;
            }
            
            .btn-generate {
                width: 100%;
                margin-top: 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- ナビゲーション -->
    <nav class="navbar navbar-expand-lg navbar-dark" style="background: linear-gradient(135deg, var(--smiley-primary), var(--smiley-light-green));">
        <div class="container">
            <a class="navbar-brand" href="../index.php">
                <i class="fas fa-utensils me-2"></i>Smiley Kitchen
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
            <h1><i class="fas fa-magic me-3"></i>請求書生成</h1>
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
                            
                            <div class="invoice-type-card" data-type="company_bulk">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="invoice_type" id="type_company" value="company_bulk" checked>
                                    <label class="form-check-label w-100" for="type_company">
                                        <strong><i class="fas fa-building me-2"></i>企業一括請求</strong>
                                        <small class="d-block text-muted mt-1">配達先企業ごとに一括で請求書を生成（推奨）</small>
                                    </label>
                                </div>
                            </div>

                            <div class="invoice-type-card" data-type="department_bulk">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="invoice_type" id="type_department" value="department_bulk">
                                    <label class="form-check-label w-100" for="type_department">
                                        <strong><i class="fas fa-sitemap me-2"></i>部署別一括請求</strong>
                                        <small class="d-block text-muted mt-1">部署ごとに分けて請求書を生成</small>
                                    </label>
                                </div>
                            </div>

                            <div class="invoice-type-card" data-type="individual">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="invoice_type" id="type_individual" value="individual">
                                    <label class="form-check-label w-100" for="type_individual">
                                        <strong><i class="fas fa-user me-2"></i>個人請求</strong>
                                        <small class="d-block text-muted mt-1">利用者個人ごとに請求書を生成</small>
                                    </label>
                                </div>
                            </div>

                            <div class="invoice-type-card" data-type="mixed">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="invoice_type" id="type_mixed" value="mixed">
                                    <label class="form-check-label w-100" for="type_mixed">
                                        <strong><i class="fas fa-magic me-2"></i>混合請求（自動判定）</strong>
                                        <small class="d-block text-muted mt-1">企業設定に基づいて最適な請求方法を自動選択</small>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- 期間・オプション設定 -->
                        <div class="col-md-6">
                            <h6 class="mb-3"><i class="fas fa-calendar-alt me-2"></i>請求期間</h6>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="period_start" class="form-label">開始日 <span class="text-danger">*</span></label>
                                    <div class="calendar-input">
                                        <input type="text" class="form-control" id="period_start" name="period_start" required placeholder="YYYY-MM-DD">
                                        <i class="fas fa-calendar"></i>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label for="period_end" class="form-label">終了日 <span class="text-danger">*</span></label>
                                    <div class="calendar-input">
                                        <input type="text" class="form-control" id="period_end" name="period_end" required placeholder="YYYY-MM-DD">
                                        <i class="fas fa-calendar"></i>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="due_date" class="form-label">支払期限日</label>
                                <div class="calendar-input">
                                    <input type="text" class="form-control" id="due_date" name="due_date" placeholder="未設定時は終了日+30日">
                                    <i class="fas fa-calendar"></i>
                                </div>
                            </div>

                            <!-- 期間テンプレート -->
                            <div class="mb-3">
                                <label class="form-label">期間テンプレート</label>
                                <div class="period-templates">
                                    <button type="button" class="period-template-btn" onclick="setPeriodTemplate('this_month')">今月</button>
                                    <button type="button" class="period-template-btn" onclick="setPeriodTemplate('last_month')">先月</button>
                                    <button type="button" class="period-template-btn" onclick="setPeriodTemplate('last_week')">先週</button>
                                    <button type="button" class="period-template-btn" onclick="setPeriodTemplate('custom_30days')">過去30日</button>
                                </div>
                            </div>

                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="auto_pdf" name="auto_pdf" checked>
                                    <label class="form-check-label" for="auto_pdf">
                                        <i class="fas fa-file-pdf me-1"></i>PDF自動生成
                                    </label>
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
                                        <i class="fas fa-info-circle me-2"></i>請求書タイプを選択してください
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="statistics-card">
                                    <h6><i class="fas fa-chart-bar me-2"></i>選択状況</h6>
                                    <div id="selectionStats">
                                        <div class="d-flex justify-content-between mb-2">
                                            <span>選択数:</span>
                                            <span id="selectedCount" class="fw-bold">0</span>
                                        </div>
                                        <div class="d-flex justify-content-between mb-3">
                                            <span>総対象数:</span>
                                            <span id="totalCount" class="fw-bold">0</span>
                                        </div>
                                    </div>
                                    <div class="d-grid gap-2">
                                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="selectAll()">
                                            <i class="fas fa-check-double me-1"></i>全選択
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="selectNone()">
                                            <i class="fas fa-times me-1"></i>選択解除
                                        </button>
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
                    <h6><i class="fas fa-hourglass-half me-2"></i>請求書生成中...</h6>
                    <div class="progress mb-3">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" 
                             style="width: 0%; background-color: var(--smiley-primary);" 
                             id="progressBar"></div>
                    </div>
                    <p class="mb-0 text-muted" id="progressMessage">初期化中...</p>
                </div>
            </div>
        </div>

        <!-- 結果表示 -->
        <div class="result-container" id="resultContainer">
            <div class="card">
                <div class="card-body">
                    <div id="resultContent">
                        <!-- 結果がここに表示される -->
                    </div>
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
        let selectedTargets = [];
        let currentTargetType = '';
        let datePickers = {};
        
        // 初期化
        document.addEventListener('DOMContentLoaded', function() {
            initializeDatePickers();
            initializeEventListeners();
            loadTargetsForType('company_bulk');
        });
        
        // 日付ピッカー初期化（日本語カレンダー対応）
        function initializeDatePickers() {
            const commonConfig = {
                locale: 'ja',
                dateFormat: 'Y-m-d',
                allowInput: true,
                theme: 'material_blue',
                onChange: function(selectedDates, dateStr, instance) {
                    // 終了日が開始日より前の場合は調整
                    if (instance.element.id === 'period_start' && datePickers.period_end) {
                        const endDate = datePickers.period_end.selectedDates[0];
                        if (endDate && selectedDates[0] > endDate) {
                            datePickers.period_end.setDate(selectedDates[0]);
                        }
                    }
                    
                    // 支払期限を自動設定
                    if (instance.element.id === 'period_end') {
                        updateDueDate(dateStr);
                    }
                }
            };
            
            datePickers.period_start = flatpickr('#period_start', {
                ...commonConfig,
                defaultDate: getFirstDayOfLastMonth()
            });
            
            datePickers.period_end = flatpickr('#period_end', {
                ...commonConfig,
                defaultDate: getLastDayOfLastMonth()
            });
            
            datePickers.due_date = flatpickr('#due_date', {
                ...commonConfig,
                defaultDate: getDefaultDueDate()
            });
        }
        
        // 支払期限自動更新
        function updateDueDate(endDate) {
            if (endDate) {
                const dueDate = new Date(endDate);
                dueDate.setDate(dueDate.getDate() + 30);
                datePickers.due_date.setDate(dueDate);
            }
        }
        
        // イベントリスナー初期化
        function initializeEventListeners() {
            // 請求書タイプ変更
            document.querySelectorAll('input[name="invoice_type"]').forEach(radio => {
                radio.addEventListener('change', function() {
                    // カード選択状態更新
                    document.querySelectorAll('.invoice-type-card').forEach(card => {
                        card.classList.remove('selected');
                    });
                    this.closest('.invoice-type-card').classList.add('selected');
                    
                    // 対象選択エリア更新
                    loadTargetsForType(this.value);
                });
            });
            
            // カードクリックでラジオボタン選択
            document.querySelectorAll('.invoice-type-card').forEach(card => {
                card.addEventListener('click', function() {
                    const radio = this.querySelector('input[type="radio"]');
                    radio.checked = true;
                    radio.dispatchEvent(new Event('change'));
                });
            });
            
            // フォーム送信
            document.getElementById('invoiceGenerationForm').addEventListener('submit', function(e) {
                e.preventDefault();
                generateInvoices();
            });
        }
        
        // 対象データ読み込み
        function loadTargetsForType(type) {
            currentTargetType = type;
            selectedTargets = [];
            updateSelectionStats();
            
            const targetList = document.getElementById('targetList');
            targetList.innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin me-2"></i>読み込み中...</div>';
            
            let apiEndpoint = '';
            switch(type) {
                case 'company_bulk':
                    apiEndpoint = '../api/invoices.php?action=companies';
                    break;
                case 'department_bulk':
                    apiEndpoint = '../api/invoices.php?action=departments';
                    break;
                case 'individual':
                    apiEndpoint = '../api/invoices.php?action=users';
                    break;
                case 'mixed':
                    apiEndpoint = '../api/invoices.php?action=companies';
                    break;
            }
            
            fetch(apiEndpoint)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        renderTargetList(data.data, type);
                    } else {
                        throw new Error(data.error);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    targetList.innerHTML = `<div class="text-center text-danger"><i class="fas fa-exclamation-triangle me-2"></i>読み込みエラー: ${error.message}</div>`;
                });
        }
        
        // 対象一覧表示
        function renderTargetList(data, type) {
            const targetList = document.getElementById('targetList');
            
            if (!data || data.length === 0) {
                targetList.innerHTML = '<div class="text-center text-muted"><i class="fas fa-info-circle me-2"></i>対象データがありません</div>';
                return;
            }
            
            let html = '';
            data.forEach(item => {
                let displayText = '';
                let detailText = '';
                
                switch(type) {
                    case 'company_bulk':
                    case 'mixed':
                        displayText = item.company_name || item.name;
                        detailText = `利用者: ${item.user_count || 0}名`;
                        break;
                    case 'department_bulk':
                        displayText = `${item.company_name} - ${item.department_name}`;
                        detailText = `利用者: ${item.user_count || 0}名`;
                        break;
                    case 'individual':
                        displayText = item.user_name || item.name;
                        detailText = `${item.company_name}${item.department_name ? ' - ' + item.department_name : ''}`;
                        break;
                }
                
                html += `
                    <div class="target-item" data-id="${item.id}" onclick="toggleTarget(this)">
                        <div class="fw-bold">${displayText}</div>
                        <small class="text-muted">${detailText}</small>
                    </div>
                `;
            });
            
            targetList.innerHTML = html;
            updateSelectionStats();
        }
        
        // 対象選択切り替え
        function toggleTarget(element) {
            const id = parseInt(element.dataset.id);
            const index = selectedTargets.indexOf(id);
            
            if (index === -1) {
                selectedTargets.push(id);
                element.classList.add('selected');
            } else {
                selectedTargets.splice(index, 1);
                element.classList.remove('selected');
            }
            
            updateSelectionStats();
        }
        
        // 全選択
        function selectAll() {
            selectedTargets = [];
            document.querySelectorAll('.target-item').forEach(item => {
                selectedTargets.push(parseInt(item.dataset.id));
                item.classList.add('selected');
            });
            updateSelectionStats();
        }
        
        // 選択解除
        function selectNone() {
            selectedTargets = [];
            document.querySelectorAll('.target-item').forEach(item => {
                item.classList.remove('selected');
            });
            updateSelectionStats();
        }
        
        // 選択状況更新
        function updateSelectionStats() {
            document.getElementById('selectedCount').textContent = selectedTargets.length;
            document.getElementById('totalCount').textContent = document.querySelectorAll('.target-item').length;
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
                case 'last_week':
                    startDate = new Date(today);
                    startDate.setDate(today.getDate() - 7);
                    endDate = new Date(today);
                    endDate.setDate(today.getDate() - 1);
                    break;
                case 'custom_30days':
                    startDate = new Date(today);
                    startDate.setDate(today.getDate() - 30);
                    endDate = new Date(today);
                    endDate.setDate(today.getDate() - 1);
                    break;
            }
            
            if (startDate && endDate) {
                datePickers.period_start.setDate(startDate);
                datePickers.period_end.setDate(endDate);
                updateDueDate(formatDate(endDate));
            }
        }
        
        // 請求書生成実行
        function generateInvoices() {
            const form = document.getElementById('invoiceGenerationForm');
            const formData = new FormData(form);
            
            // バリデーション
            if (!formData.get('period_start') || !formData.get('period_end')) {
                alert('請求期間を選択してください。');
                return;
            }
            
            if (selectedTargets.length === 0 && currentTargetType !== 'mixed') {
                alert('対象を選択してください。');
                return;
            }
            
            // UI更新
            showProgress();
            
            // API呼び出し
            const requestData = {
                action: 'generate',
                invoice_type: formData.get('invoice_type'),
                period_start: formData.get('period_start'),
                period_end: formData.get('period_end'),
                due_date: formData.get('due_date') || null,
                target_ids: selectedTargets,
                auto_generate_pdf: formData.get('auto_pdf') === 'on'
            };
            
            fetch('../api/invoices.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(requestData)
            })
            .then(response => response.json())
            .then(data => {
                hideProgress();
                if (data.success) {
                    showResult(true, data.data, data.message);
                } else {
                    showResult(false, null, data.error);
                }
            })
            .catch(error => {
                hideProgress();
                console.error('Error:', error);
                showResult(false, null, 'ネットワークエラーが発生しました: ' + error.message);
            });
        }
        
        // 進捗表示
        function showProgress() {
            document.getElementById('generateButton').disabled = true;
            document.querySelector('.loading-spinner').style.display = 'inline-block';
            document.getElementById('progressContainer').style.display = 'block';
            
            // プログレスバー アニメーション
            let progress = 0;
            const progressBar = document.getElementById('progressBar');
            const progressMessage = document.getElementById('progressMessage');
            
            const messages = [
                '注文データを取得中...',
                '請求書を生成中...',
                'PDF を作成中...',
                '完了しました！'
            ];
            
            const interval = setInterval(() => {
                progress += 25;
                progressBar.style.width = progress + '%';
                
                if (progress <= 100) {
                    progressMessage.textContent = messages[Math.floor(progress / 25) - 1] || messages[0];
                }
                
                if (progress >= 100) {
                    clearInterval(interval);
                }
            }, 1000);
        }
        
        // 進捗非表示
        function hideProgress() {
            document.getElementById('generateButton').disabled = false;
            document.querySelector('.loading-spinner').style.display = 'none';
            document.getElementById('progressContainer').style.display = 'none';
        }
        
        // 結果表示
        function showResult(success, data, message) {
            const resultContainer = document.getElementById('resultContainer');
            const resultContent = document.getElementById('resultContent');
            
            resultContainer.style.display = 'block';
            
            if (success) {
                resultContent.innerHTML = `
                    <div class="result-success">
                        <h6 class="text-success"><i class="fas fa-check-circle me-2"></i>請求書生成完了</h6>
                        <p class="mb-2">${message}</p>
                        <ul class="mb-3">
                            <li>生成件数: ${data.total_invoices || 0} 件</li>
                            <li>合計金額: ¥${(data.total_amount || 0).toLocaleString()}</li>
                            <li>請求書タイプ: ${getInvoiceTypeLabel(data.invoice_type)}</li>
                        </ul>
                        <div class="d-flex gap-2">
                            <a href="../pages/invoices.php" class="btn btn-primary">
                                <i class="fas fa-list me-2"></i>請求書一覧で確認
                            </a>
                            <button type="button" class="btn btn-success" onclick="generateAgain()">
                                <i class="fas fa-redo me-2"></i>再生成
                            </button>
                        </div>
                    </div>
                `;
            } else {
                resultContent.innerHTML = `
                    <div class="result-error">
                        <h6 class="text-danger"><i class="fas fa-exclamation-triangle me-2"></i>請求書生成エラー</h6>
                        <p class="mb-3">${message}</p>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-outline-danger" onclick="hideResult()">
                                <i class="fas fa-times me-2"></i>閉じる
                            </button>
                            <button type="button" class="btn btn-warning" onclick="generateAgain()">
                                <i class="fas fa-redo me-2"></i>再試行
                            </button>
                        </div>
                    </div>
                `;
            }
            
            // ページ上部にスクロール
            resultContainer.scrollIntoView({ behavior: 'smooth' });
        }
        
        // 結果非表示
        function hideResult() {
            document.getElementById('resultContainer').style.display = 'none';
        }
        
        // 再生成
        function generateAgain() {
            hideResult();
            generateInvoices();
        }
        
        // ユーティリティ関数
        
        // 請求書タイプラベル取得
        function getInvoiceTypeLabel(type) {
            const labels = {
                'company_bulk': '企業一括請求',
                'department_bulk': '部署別一括請求',
                'individual': '個人請求',
                'mixed': '混合請求'
            };
            return labels[type] || type;
        }
        
        // 先月の初日取得
        function getFirstDayOfLastMonth() {
            const date = new Date();
            date.setMonth(date.getMonth() - 1, 1);
            return date;
        }
        
        // 先月の末日取得
        function getLastDayOfLastMonth() {
            const date = new Date();
            date.setDate(0);
            return date;
        }
        
        // デフォルト支払期限取得（先月末日+30日）
        function getDefaultDueDate() {
            const date = getLastDayOfLastMonth();
            date.setDate(date.getDate() + 30);
            return date;
        }
        
        // 日付フォーマット
        function formatDate(date) {
            return date.toISOString().split('T')[0];
        }
        
        // 初期設定
        document.querySelector('input[value="company_bulk"]').closest('.invoice-type-card').classList.add('selected');
    </script>
</body>
</html>
