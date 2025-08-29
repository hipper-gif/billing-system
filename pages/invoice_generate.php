<?php
/**
 * 請求書生成画面
 * Smiley配食事業専用の請求書生成インターフェース
 * 
 * @author Claude
 * @version 2.0.0 - 根本解決版
 * @created 2025-08-29
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

        .btn-preview {
            background: linear-gradient(135deg, #007bff, #0056b3);
            border: none;
            color: white;
            padding: 8px 20px;
            border-radius: 20px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-preview:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0, 123, 255, 0.3);
            color: white;
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
        }

        .target-item {
            padding: 8px 12px;
            border-radius: 6px;
            margin-bottom: 5px;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }

        .target-item:hover {
            background-color: #f8f9fa;
        }

        .target-item.selected {
            background-color: var(--smiley-accent);
            color: #333;
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
                            
                            <div class="invoice-type-card" data-type="company_bulk" onclick="selectInvoiceType('company_bulk')">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="invoice_type" id="type_company" value="company_bulk" checked>
                                    <label class="form-check-label" for="type_company">
                                        <strong>企業一括請求</strong>
                                        <small class="d-block text-muted">配達先企業ごとに一括で請求書を生成</small>
                                    </label>
                                </div>
                            </div>

                            <div class="invoice-type-card" data-type="department_bulk" onclick="selectInvoiceType('department_bulk')">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="invoice_type" id="type_department" value="department_bulk">
                                    <label class="form-check-label" for="type_department">
                                        <strong>部署別一括請求</strong>
                                        <small class="d-block text-muted">部署ごとに分けて請求書を生成</small>
                                    </label>
                                </div>
                            </div>

                            <div class="invoice-type-card" data-type="individual" onclick="selectInvoiceType('individual')">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="invoice_type" id="type_individual" value="individual">
                                    <label class="form-check-label" for="type_individual">
                                        <strong>個人請求</strong>
                                        <small class="d-block text-muted">利用者個人ごとに請求書を生成</small>
                                    </label>
                                </div>
                            </div>

                            <div class="invoice-type-card" data-type="mixed" onclick="selectInvoiceType('mixed')">
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
                                <div class="btn-group d-block" role="group">
                                    <button type="button" class="btn btn-outline-primary btn-sm me-1" onclick="setPeriodTemplate('this_month')">今月</button>
                                    <button type="button" class="btn btn-outline-primary btn-sm me-1" onclick="setPeriodTemplate('last_month')">先月</button>
                                    <button type="button" class="btn btn-outline-primary btn-sm me-1" onclick="setPeriodTemplate('this_quarter')">今四半期</button>
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
                                    </div>
                                    <div class="mt-3">
                                        <button type="button" class="btn btn-outline-primary btn-sm w-100 mb-2" onclick="selectAll()">全選択</button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm w-100 mb-2" onclick="selectNone()">選択解除</button>
                                        <button type="button" class="btn btn-preview btn-sm w-100" onclick="showPreview()">
                                            <i class="fas fa-eye me-1"></i>プレビュー
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

        <!-- プログレスバー -->
        <div class="progress-container">
            <div class="card">
                <div class="card-body">
                    <h6><i class="fas fa-cog fa-spin me-2"></i>請求書生成中...</h6>
                    <div class="progress">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 100%"></div>
                    </div>
                    <small class="text-muted">しばらくお待ちください</small>
                </div>
            </div>
        </div>

        <!-- 結果表示エリア -->
        <div class="result-container"></div>
    </div>

    <!-- JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/ja.js"></script>
    <script>
        // グローバル変数
        let currentInvoiceType = 'company_bulk';

        document.addEventListener('DOMContentLoaded', function() {
            // Flatpickr初期化（日付選択）
            flatpickr("#period_start", {
                dateFormat: "Y-m-d",
                defaultDate: new Date(new Date().getFullYear(), new Date().getMonth(), 1),
                locale: "ja"
            });
            
            flatpickr("#period_end", {
                dateFormat: "Y-m-d",
                defaultDate: new Date(new Date().getFullYear(), new Date().getMonth() + 1, 0),
                locale: "ja"
            });
            
            flatpickr("#due_date", {
                dateFormat: "Y-m-d",
                locale: "ja"
            });

            // 初期読み込み
            updateInvoiceTypeSelection('company_bulk');
            loadTargetList('company_bulk');

            // フォーム送信処理
            document.getElementById('invoiceGenerationForm').addEventListener('submit', function(e) {
                e.preventDefault();
                generateInvoices();
            });
        });

        /**
         * 請求書タイプ選択
         */
        function selectInvoiceType(type) {
            // ラジオボタンを選択
            document.getElementById('type_' + type.split('_')[0]).checked = true;
            
            // 視覚的更新
            updateInvoiceTypeSelection(type);
            
            // 対象一覧読み込み
            loadTargetList(type);
            
            currentInvoiceType = type;
        }

        /**
         * 請求書タイプ選択の視覚的更新
         */
        function updateInvoiceTypeSelection(selectedType) {
            // すべてのカードから選択状態を削除
            document.querySelectorAll('.invoice-type-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // 選択されたカードに選択状態を追加
            const selectedCard = document.querySelector(`[data-type="${selectedType}"]`);
            if (selectedCard) {
                selectedCard.classList.add('selected');
            }
        }

        /**
         * 対象一覧の読み込み
         */
        async function loadTargetList(invoiceType) {
            const targetList = document.getElementById('targetList');
            const selectedCount = document.getElementById('selectedCount');
            const totalCount = document.getElementById('totalCount');
            
            try {
                // ローディング表示
                targetList.innerHTML = '<div class="text-center text-muted"><i class="fas fa-spinner fa-spin me-2"></i>読み込み中...</div>';
                
                // APIエンドポイントの決定
                let action = '';
                switch (invoiceType) {
                    case 'company_bulk':
                        action = 'companies';
                        break;
                    case 'department_bulk':
                        action = 'departments';
                        break;
                    case 'individual':
                        action = 'users';
                        break;
                    case 'mixed':
                        action = 'mixed';
                        break;
                    default:
                        action = 'companies';
                }
                
                // API呼び出し
                const response = await fetch(`../api/invoice_targets.php?action=${action}&invoice_type=${invoiceType}`);
                const data = await response.json();
                
                if (!data.success) {
                    throw new Error(data.message || 'データの読み込みに失敗しました');
                }
                
                // データの取得
                const targets = data.data[action] || data.data.companies || [];
                const total = data.data.total_count || 0;
                
                // HTML生成
                let html = '';
                if (targets.length === 0) {
                    html = '<div class="text-center text-muted">対象データがありません</div>';
                } else {
                    targets.forEach(target => {
                        const targetInfo = getTargetInfo(target, invoiceType);
                        html += `
                            <div class="target-item" data-id="${target.id}" onclick="toggleTargetSelection(this)">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="form-check">
                                            <input class="form-check-input target-checkbox" type="checkbox" id="target_${target.id}" value="${target.id}">
                                            <label class="form-check-label" for="target_${target.id}">
                                                <strong>${targetInfo.name}</strong>
                                                ${targetInfo.subtitle ? `<br><small class="text-muted">${targetInfo.subtitle}</small>` : ''}
                                            </label>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <div class="small text-muted">
                                            ${targetInfo.stats}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                }
                
                targetList.innerHTML = html;
                
                // 統計情報更新
                totalCount.textContent = total;
                selectedCount.textContent = '0';
                
            } catch (error) {
                console.error('対象一覧の読み込みエラー:', error);
                targetList.innerHTML = `
                    <div class="text-center text-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        対象一覧の読み込みに失敗しました<br>
                        <small>${error.message}</small>
                    </div>
                `;
            }
        }

        /**
         * 対象情報の整形
         */
        function getTargetInfo(target, invoiceType) {
            let name = '';
            let subtitle = '';
            let stats = '';
            
            switch (invoiceType) {
                case 'company_bulk':
                case 'mixed':
                    name = target.company_name;
                    subtitle = `企業コード: ${target.company_code}`;
                    stats = `利用者: ${target.user_count || 0}名 | 最近90日: ${target.recent_orders || 0}件 (¥${Number(target.recent_amount || 0).toLocaleString()})`;
                    if (invoiceType === 'mixed') {
                        stats += ` | ${target.billing_type_label || '企業一括'}`;
                    }
                    break;
                    
                case 'department_bulk':
                    name = target.department_name;
                    subtitle = `${target.company_name} - ${target.department_code}`;
                    stats = `利用者: ${target.user_count || 0}名 | 最近90日: ${target.recent_orders || 0}件 (¥${Number(target.recent_amount || 0).toLocaleString()})`;
                    break;
                    
                case 'individual':
                    name = target.user_name;
                    subtitle = `${target.company_name || ''} ${target.department_name || ''} (${target.user_code})`;
                    stats = `最近90日: ${target.recent_orders || 0}件 (¥${Number(target.recent_amount || 0).toLocaleString()})`;
                    if (target.last_order_date) {
                        stats += ` | 最終注文: ${target.last_order_date}`;
                    }
                    break;
            }
            
            return { name, subtitle, stats };
        }

        /**
         * 対象選択の切り替え
         */
        function toggleTargetSelection(element) {
            const checkbox = element.querySelector('.target-checkbox');
            checkbox.checked = !checkbox.checked;
            
            if (checkbox.checked) {
                element.classList.add('selected');
            } else {
                element.classList.remove('selected');
            }
            
            updateSelectionCount();
        }

        /**
         * 選択数の更新
         */
        function updateSelectionCount() {
            const selectedCheckboxes = document.querySelectorAll('.target-checkbox:checked');
            document.getElementById('selectedCount').textContent = selectedCheckboxes.length;
        }

        /**
         * 全選択
         */
        function selectAll() {
            document.querySelectorAll('.target-checkbox').forEach(checkbox => {
                checkbox.checked = true;
                checkbox.closest('.target-item').classList.add('selected');
            });
            updateSelectionCount();
        }

        /**
         * 選択解除
         */
        function selectNone() {
            document.querySelectorAll('.target-checkbox').forEach(checkbox => {
                checkbox.checked = false;
                checkbox.closest('.target-item').classList.remove('selected');
            });
            updateSelectionCount();
        }

        /**
         * 期間テンプレートの設定
         */
        function setPeriodTemplate(template) {
            const periodStart = document.getElementById('period_start');
            const periodEnd = document.getElementById('period_end');
            const today = new Date();
            let startDate, endDate;
            
            switch (template) {
                case 'this_month':
                    startDate = new Date(today.getFullYear(), today.getMonth(), 1);
                    endDate = new Date(today.getFullYear(), today.getMonth() + 1, 0);
                    break;
                    
                case 'last_month':
                    startDate = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                    endDate = new Date(today.getFullYear(), today.getMonth(), 0);
                    break;
                    
                case 'this_quarter':
                    const quarter = Math.floor(today.getMonth() / 3);
                    startDate = new Date(today.getFullYear(), quarter * 3, 1);
                    endDate = new Date(today.getFullYear(), quarter * 3 + 3, 0);
                    break;
                    
                case 'custom_range':
                    startDate = new Date(today.getTime() - 30 * 24 * 60 * 60 * 1000);
                    endDate = today;
                    break;
            }
            
            if (startDate && endDate) {
                periodStart.value = startDate.toISOString().split('T')[0];
                periodEnd.value = endDate.toISOString().split('T')[0];
            }
        }

        /**
         * 請求書生成処理
         */
        async function generateInvoices() {
    const generateButton = document.getElementById('generateButton');
    const loadingSpinner = generateButton.querySelector('.loading-spinner');
    const progressContainer = document.querySelector('.progress-container');
    const resultContainer = document.querySelector('.result-container');
    
    try {
        // バリデーション
        const selectedTargets = Array.from(document.querySelectorAll('.target-checkbox:checked')).map(cb => cb.value);
        if (selectedTargets.length === 0) {
            alert('請求書を生成する対象を選択してください。');
            return;
        }
        
        const formData = new FormData(document.getElementById('invoiceGenerationForm'));
        const periodStart = formData.get('period_start');
        const periodEnd = formData.get('period_end');
        
        if (!periodStart || !periodEnd) {
            alert('請求期間を入力してください。');
            return;
        }
        
        // UI状態更新
        generateButton.disabled = true;
        loadingSpinner.style.display = 'inline';
        progressContainer.style.display = 'block';
        resultContainer.style.display = 'none';
        
        // 既存のinvoices.php APIに合わせたFormData作成
        const apiFormData = new FormData();
        apiFormData.append('action', 'generate');
        apiFormData.append('invoice_type', formData.get('invoice_type'));
        apiFormData.append('period_start', periodStart);
        apiFormData.append('period_end', periodEnd);
        
        if (formData.get('due_date')) {
            apiFormData.append('due_date', formData.get('due_date'));
        }
        
        // 対象IDsを配列として追加
        selectedTargets.forEach(id => {
            apiFormData.append('target_ids[]', id);
        });
        
        if (formData.get('auto_pdf')) {
            apiFormData.append('auto_generate_pdf', '1');
        }
        
        // 既存のinvoices.php APIを呼び出し（修正済み）
        const response = await fetch('../api/invoices.php', {
            method: 'POST',
            body: apiFormData
        });
        
        const result = await response.json();
        
        if (!result.success) {
            throw new Error(result.error || result.message || '請求書の生成に失敗しました');
        }
        
        // 成功時の処理
        showResult(true, result.message, result.data);
        
        // フォームリセット（オプション）
        if (confirm('請求書の生成が完了しました。画面をリセットしますか？')) {
            location.reload();
        }
        
    } catch (error) {
        console.error('請求書生成エラー:', error);
        showResult(false, error.message);
    } finally {
        // UI状態復元
        generateButton.disabled = false;
        loadingSpinner.style.display = 'none';
        progressContainer.style.display = 'none';
    }
}

        /**
         * 結果表示
         */
        function showResult(success, message, data = null) {
            const resultContainer = document.querySelector('.result-container');
            const resultClass = success ? 'result-success' : 'result-error';
            const iconClass = success ? 'fas fa-check-circle text-success' : 'fas fa-exclamation-circle text-danger';
            
            let resultHtml = `
                <div class="card ${resultClass}">
                    <div class="card-body">
                        <h5><i class="${iconClass} me-2"></i>${success ? '生成完了' : 'エラー'}</h5>
                        <p class="mb-0">${message}</p>
            `;
            
            if (success && data) {
                resultHtml += `
                        <div class="mt-3">
                            <div class="row text-center">
                                <div class="col-md-3">
                                    <div class="fw-bold text-primary">${data.generated_invoices || 0}</div>
                                    <div class="small text-muted">生成件数</div>
                                </div>
                                <div class="col-md-3">
                                    <div class="fw-bold text-success">¥${Number(data.total_amount || 0).toLocaleString()}</div>
                                    <div class="small text-muted">総金額</div>
                                </div>
                                <div class="col-md-3">
                                    <div class="fw-bold text-info">${(data.invoice_ids || []).length}</div>
                                    <div class="small text-muted">請求書ID数</div>
                                </div>
                                <div class="col-md-3">
                                    <a href="../pages/invoices.php" class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-list me-1"></i>一覧確認
                                    </a>
                                </div>
                            </div>
                        </div>
                `;
            }
            
            resultHtml += `
                    </div>
                </div>
            `;
            
            resultContainer.innerHTML = resultHtml;
            resultContainer.style.display = 'block';
            
            // 結果エリアにスクロール
            resultContainer.scrollIntoView({ behavior: 'smooth' });
        }

        /**
         * プレビュー機能
         */
        async function showPreview() {
            const selectedTargets = Array.from(document.querySelectorAll('.target-checkbox:checked')).map(cb => cb.value);
            const periodStart = document.getElementById('period_start').value;
            const periodEnd = document.getElementById('period_end').value;
            
            if (selectedTargets.length === 0 || !periodStart || !periodEnd) {
                alert('対象と期間を選択してください。');
                return;
            }
            
            try {
                let targetType = '';
                switch (currentInvoiceType) {
                    case 'company_bulk':
                    case 'mixed':
                        targetType = 'companies';
                        break;
                    case 'department_bulk':
                        targetType = 'departments';
                        break;
                    case 'individual':
                        targetType = 'users';
                        break;
                }
                
                const response = await fetch(`../api/invoice_targets.php?action=preview&target_type=${targetType}&target_ids=${JSON.stringify(selectedTargets)}&period_start=${periodStart}&period_end=${periodEnd}`);
                const data = await response.json();
                
                if (!data.success) {
                    throw new Error(data.message || 'プレビューの取得に失敗しました');
                }
                
                // プレビューモーダル表示
                showPreviewModal(data.data);
                
            } catch (error) {
                console.error('プレビューエラー:', error);
                alert('プレビューの表示に失敗しました: ' + error.message);
            }
        }

        /**
         * プレビューモーダル表示
         */
        function showPreviewModal(previewData) {
            // 既存のモーダルを削除
            const existingModal = document.getElementById('previewModal');
            if (existingModal) {
                existingModal.remove();
            }
            
            // モーダルHTML生成
            let tableRows = '';
            previewData.preview.forEach(item => {
                tableRows += `
                    <tr>
                        <td>${item.target_name}</td>
                        <td><span class="badge bg-secondary">${item.target_type}</span></td>
                        <td class="text-end">${item.order_count}件</td>
                        <td class="text-end">¥${Number(item.total_amount).toLocaleString()}</td>
                        <td class="text-end">${item.user_count}名</td>
                    </tr>
                `;
            });
            
            const modalHtml = `
                <div class="modal fade" id="previewModal" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title"><i class="fas fa-eye me-2"></i>請求書生成プレビュー</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row mb-3">
                                    <div class="col-md-3 text-center">
                                        <div class="h4 text-primary">${previewData.summary.total_targets}</div>
                                        <div class="small text-muted">対象数</div>
                                    </div>
                                    <div class="col-md-3 text-center">
                                        <div class="h4 text-success">¥${Number(previewData.summary.total_amount).toLocaleString()}</div>
                                        <div class="small text-muted">総請求額</div>
                                    </div>
                                    <div class="col-md-3 text-center">
                                        <div class="h4 text-info">${previewData.summary.total_orders}</div>
                                        <div class="small text-muted">総注文数</div>
                                    </div>
                                    <div class="col-md-3 text-center">
                                        <div class="h4 text-warning">${previewData.summary.total_users}</div>
                                        <div class="small text-muted">利用者数</div>
                                    </div>
                                </div>
                                
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>対象名</th>
                                                <th>タイプ</th>
                                                <th class="text-end">注文数</th>
                                                <th class="text-end">請求額</th>
                                                <th class="text-end">利用者</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            ${tableRows}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">閉じる</button>
                                <button type="button" class="btn btn-primary" data-bs-dismiss="modal" onclick="generateInvoices()">
                                    <i class="fas fa-magic me-1"></i>請求書生成実行
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // 新しいモーダルを追加
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            
            // モーダル表示
            const modal = new bootstrap.Modal(document.getElementById('previewModal'));
            modal.show();
        }
    </script>
</body>
</html>
