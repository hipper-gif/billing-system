<?php
/**
 * 請求書生成画面
 * Smiley配食事業専用の請求書生成インターフェース
 * 
 * @author Claude
 * @version 3.0.0 - シンプル版（企業・個人のみ）
 * @created 2025-08-26
 * @updated 2025-10-06
 * @changes 
 *   - v3.0: 部署別請求・混合請求を削除（シンプル化）
 *   - v2.0: v5.0仕様準拠、CSP対応
 */

// v5.0仕様: config/database.php から Database クラスを読み込む
require_once __DIR__ . '/../config/database.php';
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
    <link href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0/css/all.min.css" rel="stylesheet">
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
            <p class="mb-0">配達先企業または利用者個人別の請求書を生成します</p>
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
                                    <label class="form-check-label" for="type_company">
                                        <strong>企業一括請求</strong>
                                        <small class="d-block text-muted">配達先企業ごとに一括で請求書を生成</small>
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
                                        <button type="button" class="btn btn-outline-secondary btn-sm w-100" onclick="selectNone()">選択解除</button>
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

        <!-- プログレス表示 -->
        <div class="progress-container" id="progressContainer">
            <div class="card">
                <div class="card-body">
                    <h5><i class="fas fa-cog fa-spin me-2"></i>請求書生成中...</h5>
                    <div class="progress" style="height: 25px;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" 
                             role="progressbar" 
                             style="width: 0%"
                             id="progressBar">0%</div>
                    </div>
                    <p class="mt-2 mb-0" id="progressMessage">処理を開始しています...</p>
                </div>
            </div>
        </div>

        <!-- 結果表示 -->
        <div class="result-container" id="resultContainer">
            <div class="card" id="resultCard">
                <div class="card-body">
                    <div id="resultContent"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/ja.js"></script>
    
    <script>
        // グローバル変数
        let selectedTargets = [];
        let currentInvoiceType = 'company_bulk';

        // ページ読み込み時の初期化
        document.addEventListener('DOMContentLoaded', function() {
            // URLパラメータを取得
            const urlParams = new URLSearchParams(window.location.search);
            const preselectedType = urlParams.get('type');
            const preselectedUserId = urlParams.get('user_id');
            const preselectedUserIds = urlParams.get('user_ids');
            const preselectedCompany = urlParams.get('company');
            const preselectedCompanies = urlParams.get('companies');

            // Flatpickrの初期化
            const fpConfig = {
                locale: 'ja',
                dateFormat: 'Y-m-d',
                allowInput: true
            };

            flatpickr('#period_start', fpConfig);
            flatpickr('#period_end', fpConfig);
            flatpickr('#due_date', fpConfig);

            // デフォルトで先月を設定
            setPeriodTemplate('last_month');

            // URLパラメータに基づいて請求書タイプを設定
            if (preselectedType) {
                const typeRadio = document.querySelector(`input[name="invoice_type"][value="${preselectedType}"]`);
                if (typeRadio) {
                    typeRadio.checked = true;
                    currentInvoiceType = preselectedType;
                    updateInvoiceTypeSelection();
                }
            }

            // 請求書タイプカードのクリックイベント
            document.querySelectorAll('.invoice-type-card').forEach(card => {
                card.addEventListener('click', function() {
                    const radio = this.querySelector('input[type="radio"]');
                    radio.checked = true;
                    updateInvoiceTypeSelection();
                    loadTargets();
                });
            });

            // 請求書タイプラジオボタンの変更イベント
            document.querySelectorAll('input[name="invoice_type"]').forEach(radio => {
                radio.addEventListener('change', function() {
                    updateInvoiceTypeSelection();
                    loadTargets();
                });
            });

            // フォーム送信イベント
            document.getElementById('invoiceGenerationForm').addEventListener('submit', function(e) {
                e.preventDefault();
                generateInvoices();
            });

            // 初回読み込み
            loadTargets();
        });

        // 請求書タイプ選択の更新
        function updateInvoiceTypeSelection() {
            currentInvoiceType = document.querySelector('input[name="invoice_type"]:checked').value;
            
            // すべてのカードから選択状態を削除
            document.querySelectorAll('.invoice-type-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // 選択されたカードに選択状態を追加
            const selectedCard = document.querySelector(`.invoice-type-card[data-type="${currentInvoiceType}"]`);
            if (selectedCard) {
                selectedCard.classList.add('selected');
            }
        }

        // 期間テンプレートの設定
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
                    const quarter = Math.floor(today.getMonth() / 3);
                    startDate = new Date(today.getFullYear(), quarter * 3, 1);
                    endDate = new Date(today.getFullYear(), quarter * 3 + 3, 0);
                    break;
                case 'custom_range':
                    endDate = new Date(today);
                    startDate = new Date(today);
                    startDate.setDate(startDate.getDate() - 30);
                    break;
            }

            // 日付を設定
            document.getElementById('period_start').value = formatDate(startDate);
            document.getElementById('period_end').value = formatDate(endDate);

            // 支払期限日を自動計算（終了日+30日）
            const dueDate = new Date(endDate);
            dueDate.setDate(dueDate.getDate() + 30);
            document.getElementById('due_date').value = formatDate(dueDate);
        }

        // 日付フォーマット
        function formatDate(date) {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        }

        // 対象リストの読み込み
        async function loadTargets() {
            const targetList = document.getElementById('targetList');
            targetList.innerHTML = '<div class="text-center text-muted"><i class="fas fa-spinner fa-spin me-2"></i>読み込み中...</div>';

            try {
                let apiUrl = '';
                switch(currentInvoiceType) {
                    case 'company_bulk':
                        apiUrl = '../api/companies.php';
                        break;
                    case 'individual':
                        apiUrl = '../api/users.php';
                        break;
                    default:
                        throw new Error('不明な請求タイプです');
                }

                console.log('Loading targets from:', apiUrl);
                const response = await fetch(apiUrl);
                
                // レスポンスのテキストを取得
                const text = await response.text();
                console.log('Response text:', text.substring(0, 500));
                
                // JSONとしてパース
                let result;
                try {
                    result = JSON.parse(text);
                } catch (parseError) {
                    console.error('JSON Parse Error:', parseError);
                    throw new Error(`APIレスポンスが不正です。HTMLエラーページが返された可能性があります。\n最初の100文字: ${text.substring(0, 100)}`);
                }

                if (result.success) {
                    displayTargets(result.data);
                } else {
                    throw new Error(result.message || '対象の読み込みに失敗しました');
                }
            } catch (error) {
                console.error('Load targets error:', error);
                targetList.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>エラー:</strong> ${error.message}
                        <div class="mt-2 small">
                            <strong>確認事項:</strong>
                            <ul class="mb-0">
                                <li>APIファイルが存在するか確認してください</li>
                                <li>config/database.phpが正しく設定されているか確認してください</li>
                                <li>ブラウザのコンソールでエラー詳細を確認してください</li>
                            </ul>
                        </div>
                    </div>
                `;
            }
        }

        // 対象の表示
        function displayTargets(data) {
            const targetList = document.getElementById('targetList');
            selectedTargets = [];

            let items = [];
            switch(currentInvoiceType) {
                case 'company_bulk':
                    items = data.companies || data;
                    break;
                case 'individual':
                    items = data.users || data;
                    break;
                default:
                    targetList.innerHTML = '<div class="alert alert-warning"><i class="fas fa-exclamation-triangle me-2"></i>不明な請求タイプです</div>';
                    return;
            }

            if (!items || items.length === 0) {
                targetList.innerHTML = '<div class="alert alert-info"><i class="fas fa-info-circle me-2"></i>対象が見つかりませんでした</div>';
                updateSelectionStats();
                return;
            }

            let html = '';
            items.forEach(item => {
                const id = item.id;
                
                // 請求タイプに応じて適切な名称とコードを取得
                let name, code, subInfo;
                
                switch(currentInvoiceType) {
                    case 'company_bulk':
                        name = item.company_name || '名称不明';
                        code = item.company_code || '';
                        subInfo = '';
                        break;
                        
                    case 'individual':
                        name = item.user_name || '名称不明';
                        code = item.user_code || '';
                        subInfo = item.company_name ? `<small class="text-muted d-block">${item.company_name}</small>` : '';
                        break;
                }
                
                html += `
                    <div class="target-item" data-id="${id}" onclick="toggleTarget(this)">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="target_${id}">
                            <label class="form-check-label" for="target_${id}">
                                <strong>${name}</strong>
                                ${code ? `<small class="text-muted ms-2">(${code})</small>` : ''}
                                ${subInfo}
                            </label>
                        </div>
                    </div>
                `;
            });

            targetList.innerHTML = html;

            // URLパラメータに基づいて対象を事前選択
            const urlParams = new URLSearchParams(window.location.search);
            const preselectedUserId = urlParams.get('user_id');
            const preselectedUserIds = urlParams.get('user_ids');
            const preselectedCompany = urlParams.get('company');
            const preselectedCompanies = urlParams.get('companies');

            if (currentInvoiceType === 'individual' && (preselectedUserId || preselectedUserIds)) {
                // 個人請求の場合
                let userIdsToSelect = [];
                if (preselectedUserId) {
                    userIdsToSelect = [preselectedUserId];
                } else if (preselectedUserIds) {
                    userIdsToSelect = preselectedUserIds.split(',');
                }

                userIdsToSelect.forEach(userId => {
                    const targetElement = document.querySelector(`.target-item[data-id="${userId}"]`);
                    if (targetElement) {
                        const checkbox = targetElement.querySelector('input[type="checkbox"]');
                        checkbox.checked = true;
                        targetElement.classList.add('selected');
                        selectedTargets.push(userId);
                    }
                });
            } else if (currentInvoiceType === 'company_bulk' && (preselectedCompany || preselectedCompanies)) {
                // 企業一括請求の場合
                let companiesToSelect = [];
                if (preselectedCompany) {
                    companiesToSelect = [preselectedCompany];
                } else if (preselectedCompanies) {
                    companiesToSelect = preselectedCompanies.split(',');
                }

                // 企業名で照合
                items.forEach(item => {
                    if (companiesToSelect.includes(item.company_name)) {
                        const targetElement = document.querySelector(`.target-item[data-id="${item.id}"]`);
                        if (targetElement) {
                            const checkbox = targetElement.querySelector('input[type="checkbox"]');
                            checkbox.checked = true;
                            targetElement.classList.add('selected');
                            selectedTargets.push(item.id.toString());
                        }
                    }
                });
            }

            updateSelectionStats();
        }

        // 対象の選択切り替え
        function toggleTarget(element) {
            const checkbox = element.querySelector('input[type="checkbox"]');
            checkbox.checked = !checkbox.checked;
            
            if (checkbox.checked) {
                element.classList.add('selected');
                selectedTargets.push(element.dataset.id);
            } else {
                element.classList.remove('selected');
                selectedTargets = selectedTargets.filter(id => id !== element.dataset.id);
            }
            
            updateSelectionStats();
        }

        // 全選択
        function selectAll() {
            selectedTargets = [];
            document.querySelectorAll('.target-item').forEach(item => {
                const checkbox = item.querySelector('input[type="checkbox"]');
                checkbox.checked = true;
                item.classList.add('selected');
                selectedTargets.push(item.dataset.id);
            });
            updateSelectionStats();
        }

        // 選択解除
        function selectNone() {
            selectedTargets = [];
            document.querySelectorAll('.target-item').forEach(item => {
                const checkbox = item.querySelector('input[type="checkbox"]');
                checkbox.checked = false;
                item.classList.remove('selected');
            });
            updateSelectionStats();
        }

        // 選択状況の更新
        function updateSelectionStats() {
            const totalCount = document.querySelectorAll('.target-item').length;
            document.getElementById('selectedCount').textContent = selectedTargets.length;
            document.getElementById('totalCount').textContent = totalCount;
        }

        // 請求書生成
        async function generateInvoices() {
            if (selectedTargets.length === 0) {
                alert('対象を選択してください');
                return;
            }

            const periodStart = document.getElementById('period_start').value;
            const periodEnd = document.getElementById('period_end').value;
            const dueDate = document.getElementById('due_date').value;
            const autoPdf = document.getElementById('auto_pdf').checked;

            if (!periodStart || !periodEnd) {
                alert('請求期間を設定してください');
                return;
            }

            // ボタン無効化
            const generateButton = document.getElementById('generateButton');
            generateButton.disabled = true;
            generateButton.querySelector('.loading-spinner').style.display = 'inline';

            // プログレス表示
            showProgress();

            try {
                console.log('Generating invoices with:', {
                    invoice_type: currentInvoiceType,
                    targets: selectedTargets,
                    period_start: periodStart,
                    period_end: periodEnd
                });

                const response = await fetch('../api/invoices.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'generate',
                        invoice_type: currentInvoiceType,
                        targets: selectedTargets,
                        period_start: periodStart,
                        period_end: periodEnd,
                        due_date: dueDate,
                        auto_pdf: autoPdf
                    })
                });

                console.log('Response status:', response.status);
                console.log('Response headers:', response.headers);

                const text = await response.text();
                console.log('Response text (first 500 chars):', text.substring(0, 500));

                let result;
                try {
                    result = JSON.parse(text);
                } catch (parseError) {
                    console.error('JSON Parse Error:', parseError);
                    console.error('Full response text:', text);
                    throw new Error(`サーバーエラー: JSONが不正です\n\nレスポンス内容:\n${text.substring(0, 500)}\n\nブラウザのコンソールで完全なレスポンスを確認してください。`);
                }

                if (result.success) {
                    showSuccess(result);
                } else {
                    showError(result.message || '請求書の生成に失敗しました');
                }
            } catch (error) {
                console.error('Generate error:', error);
                showError('通信エラーが発生しました: ' + error.message);
            } finally {
                generateButton.disabled = false;
                generateButton.querySelector('.loading-spinner').style.display = 'none';
                hideProgress();
            }
        }

        // プログレス表示
        function showProgress() {
            document.getElementById('progressContainer').style.display = 'block';
            document.getElementById('resultContainer').style.display = 'none';
            
            let progress = 0;
            const progressBar = document.getElementById('progressBar');
            const progressMessage = document.getElementById('progressMessage');
            
            const interval = setInterval(() => {
                progress += 10;
                if (progress > 90) progress = 90;
                
                progressBar.style.width = progress + '%';
                progressBar.textContent = progress + '%';
                
                if (progress < 30) {
                    progressMessage.textContent = '注文データを集計しています...';
                } else if (progress < 60) {
                    progressMessage.textContent = '請求書を作成しています...';
                } else {
                    progressMessage.textContent = '最終処理中...';
                }
            }, 500);
            
            // インターバルIDを保存
            window.progressInterval = interval;
        }

        // プログレス非表示
        function hideProgress() {
            if (window.progressInterval) {
                clearInterval(window.progressInterval);
            }
            const progressBar = document.getElementById('progressBar');
            progressBar.style.width = '100%';
            progressBar.textContent = '100%';
            
            setTimeout(() => {
                document.getElementById('progressContainer').style.display = 'none';
            }, 500);
        }

        // 成功表示
        function showSuccess(result) {
            const resultCard = document.getElementById('resultCard');
            const resultContent = document.getElementById('resultContent');
            
            resultCard.className = 'card result-success';
            
            let html = `
                <h5 class="text-success"><i class="fas fa-check-circle me-2"></i>請求書生成完了</h5>
                <p class="mb-3">${result.generated_count || selectedTargets.length}件の請求書を生成しました</p>
            `;
            
            if (result.invoices && result.invoices.length > 0) {
                html += '<div class="table-responsive"><table class="table table-sm preview-table">';
                html += '<thead><tr><th>請求書番号</th><th>宛先</th><th>金額</th><th>操作</th></tr></thead><tbody>';
                
                result.invoices.forEach(invoice => {
                    html += `
                        <tr>
                            <td>${invoice.invoice_number}</td>
                            <td>${invoice.company_name || invoice.department_name || invoice.user_name}</td>
                            <td class="text-end">¥${Number(invoice.total_amount).toLocaleString()}</td>
                            <td>
                                <a href="../api/invoices.php?action=pdf&invoice_id=${invoice.id}" 
                                   class="btn btn-sm btn-outline-primary" target="_blank">
                                    <i class="fas fa-file-pdf me-1"></i>PDF
                                </a>
                            </td>
                        </tr>
                    `;
                });
                
                html += '</tbody></table></div>';
            }
            
            html += `
                <div class="mt-3">
                    <a href="../pages/invoices.php" class="btn btn-primary">
                        <i class="fas fa-list me-2"></i>請求書一覧へ
                    </a>
                    <button class="btn btn-outline-secondary" onclick="resetForm()">
                        <i class="fas fa-redo me-2"></i>新規生成
                    </button>
                </div>
            `;
            
            resultContent.innerHTML = html;
            document.getElementById('resultContainer').style.display = 'block';
            
            // 画面をスクロール
            document.getElementById('resultContainer').scrollIntoView({ behavior: 'smooth' });
        }

        // エラー表示
        function showError(message) {
            const resultCard = document.getElementById('resultCard');
            const resultContent = document.getElementById('resultContent');
            
            resultCard.className = 'card result-error';
            resultContent.innerHTML = `
                <h5 class="text-danger"><i class="fas fa-exclamation-triangle me-2"></i>エラー</h5>
                <p>${message}</p>
                <button class="btn btn-outline-secondary" onclick="resetForm()">
                    <i class="fas fa-redo me-2"></i>再試行
                </button>
            `;
            
            document.getElementById('resultContainer').style.display = 'block';
            document.getElementById('resultContainer').scrollIntoView({ behavior: 'smooth' });
        }

        // フォームリセット
        function resetForm() {
            document.getElementById('resultContainer').style.display = 'none';
            selectNone();
            document.getElementById('invoiceGenerationForm').scrollIntoView({ behavior: 'smooth' });
        }
    </script>
</body>
</html>
