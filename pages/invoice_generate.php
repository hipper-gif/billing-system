<?php
/**
 * 請求書生成画面
 * Smiley配食事業専用の請求書生成インターフェース
 * 
 * JSON解析エラー修正版
 * 
 * @author Claude
 * @version 1.1.0
 * @created 2025-08-26
 * @updated 2025-09-11
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
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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

        .debug-info {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 10px;
            margin: 10px 0;
            font-family: monospace;
            font-size: 12px;
            max-height: 200px;
            overflow-y: auto;
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

        <!-- デバッグ情報表示（開発時のみ） -->
        <div id="debugInfo" class="debug-info" style="display: none;">
            <strong>デバッグ情報:</strong><br>
            <span id="debugContent">待機中...</span>
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
                                <div class="btn-group d-block" role="group">
                                    <button type="button" class="btn btn-outline-primary btn-sm me-1" onclick="setPeriodTemplate('this_month')">今月</button>
                                    <button type="button" class="btn btn-outline-primary btn-sm me-1" onclick="setPeriodTemplate('last_month')">先月</button>
                                    <button type="button" class="btn btn-outline-primary btn-sm me-1" onclick="setPeriodTemplate('this_quarter')">今四半期</button>
                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="setPeriodTemplate('custom_range')">過去30日</button>
                                </div>
                            </div>

                            <!-- デバッグボタン（開発時のみ） -->
                            <div class="mb-3">
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="toggleDebugInfo()">
                                    <i class="fas fa-bug me-1"></i>デバッグ情報
                                </button>
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

        <!-- 進捗表示 -->
        <div id="progressContainer" class="progress-container">
            <div class="card">
                <div class="card-body">
                    <h6><i class="fas fa-cogs me-2"></i>請求書生成中...</h6>
                    <div class="progress">
                        <div class="progress-bar" role="progressbar" style="width: 0%"></div>
                    </div>
                    <div class="mt-2">
                        <small id="progressText">準備中...</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- 結果表示 -->
        <div id="resultContainer" class="result-container">
            <!-- 動的に生成される -->
        </div>
    </div>

    <!-- JavaScriptライブラリ -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/ja.js"></script>

    <script>
        /**
         * invoice_generate.php 用 JavaScript
         * JSON解析エラー対応版
         * 
         * 対象一覧読み込み機能のエラーハンドリング強化
         */

        // グローバル変数
        let currentInvoiceType = 'company_bulk';
        let selectedTargets = new Set();
        let targetData = [];
        let debugMode = false;

        // DOM読み込み完了時の初期化
        document.addEventListener('DOMContentLoaded', function() {
            initializePage();
        });

        /**
         * ページ初期化
         */
        function initializePage() {
            debugLog('ページ初期化開始');
            
            // 日付フィールドの初期化
            initializeDatePickers();
            
            // 請求書タイプ選択の初期化
            initializeInvoiceTypeSelection();
            
            // フォーム送信の初期化
            initializeFormSubmission();
            
            // 初期対象読み込み
            loadTargets();
            
            debugLog('ページ初期化完了');
        }

        /**
         * 日付フィールドの初期化
         */
        function initializeDatePickers() {
            debugLog('日付フィールド初期化');
            
            // Flatpickrの初期化
            if (typeof flatpickr !== 'undefined') {
                flatpickr('#period_start', {
                    dateFormat: 'Y-m-d',
                    locale: 'ja'
                });
                
                flatpickr('#period_end', {
                    dateFormat: 'Y-m-d',
                    locale: 'ja'
                });
                
                flatpickr('#due_date', {
                    dateFormat: 'Y-m-d',
                    locale: 'ja'
                });
                
                debugLog('Flatpickr初期化完了');
            } else {
                debugLog('Flatpickrライブラリが見つかりません');
            }
        }

        /**
         * 請求書タイプ選択の初期化
         */
        function initializeInvoiceTypeSelection() {
            debugLog('請求書タイプ選択初期化');
            
            const typeCards = document.querySelectorAll('.invoice-type-card');
            
            typeCards.forEach(card => {
                card.addEventListener('click', function() {
                    const radio = this.querySelector('input[type="radio"]');
                    if (radio) {
                        radio.checked = true;
                        currentInvoiceType = radio.value;
                        
                        // 他のカードの選択を解除
                        typeCards.forEach(c => c.classList.remove('selected'));
                        this.classList.add('selected');
                        
                        debugLog(`請求書タイプ変更: ${currentInvoiceType}`);
                        
                        // 対象一覧を再読み込み
                        loadTargets();
                    }
                });
            });
        }

        /**
         * フォーム送信の初期化
         */
        function initializeFormSubmission() {
            debugLog('フォーム送信初期化');
            
            const form = document.getElementById('invoiceGenerationForm');
            if (form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    generateInvoices();
                });
            }
        }

        /**
         * 対象一覧の読み込み（エラーハンドリング強化版）
         */
        async function loadTargets() {
            const targetList = document.getElementById('targetList');
            const selectedCount = document.getElementById('selectedCount');
            const totalCount = document.getElementById('totalCount');
            
            debugLog(`対象一覧読み込み開始: ${currentInvoiceType}`);
            
            // ローディング表示
            targetList.innerHTML = `
                <div class="text-center text-muted">
                    <i class="fas fa-spinner fa-spin me-2"></i>読み込み中...
                </div>
            `;
            
            try {
                // URLパラメータ構築
                const params = new URLSearchParams({
                    invoice_type: currentInvoiceType
                });
                
                const url = `../api/invoice_targets.php?${params.toString()}`;
                debugLog(`リクエストURL: ${url}`);
                
                // フェッチリクエスト
                const response = await fetch(url, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    },
                    cache: 'no-cache'
                });
                
                debugLog(`レスポンスステータス: ${response.status}`);
                
                // レスポンステキストを取得
                const responseText = await response.text();
                debugLog(`レスポンステキスト長: ${responseText.length}文字`);
                debugLog(`レスポンス内容（最初の200文字）: ${responseText.substring(0, 200)}`);
                
                // HTMLタグが含まれている場合はエラー
                if (responseText.includes('<') || responseText.includes('**')) {
                    throw new Error('APIから不正な形式のレスポンスが返されました。HTMLまたはMarkdownが含まれています。');
                }
                
                // JSONパース
                let data;
                try {
                    data = JSON.parse(responseText);
                    debugLog('JSON解析成功');
                } catch (parseError) {
                    debugLog(`JSON解析エラー: ${parseError.message}`);
                    throw new Error(`JSON解析エラー: ${parseError.message}\n\nレスポンス内容:\n${responseText.substring(0, 200)}...`);
                }
                
                // レスポンス構造チェック
                if (!data || typeof data !== 'object') {
                    throw new Error('APIレスポンスが正しいオブジェクト形式ではありません');
                }
                
                if (!data.success) {
                    throw new Error(data.error?.message || data.message || '対象一覧の取得に失敗しました');
                }
                
                if (!data.data || !Array.isArray(data.data.targets)) {
                    throw new Error('対象データが正しい形式で取得できませんでした');
                }
                
                // データ保存
                targetData = data.data.targets;
                selectedTargets.clear();
                
                debugLog(`対象データ取得完了: ${targetData.length}件`);
                
                // UI更新
                renderTargetList(targetData);
                updateSelectionStats();
                
            } catch (error) {
                debugLog(`エラー: ${error.message}`);
                console.error('Error loading targets:', error);
                
                // エラー表示
                targetList.innerHTML = `
                    <div class="alert alert-danger">
                        <h6><i class="fas fa-exclamation-triangle me-2"></i>対象一覧の読み込みに失敗</h6>
                        <p class="mb-2"><strong>エラー内容:</strong></p>
                        <p class="small text-danger">${error.message}</p>
                        <div class="mt-3">
                            <button class="btn btn-outline-danger btn-sm me-2" onclick="loadTargets()">
                                <i class="fas fa-redo me-1"></i>再試行
                            </button>
                            <button class="btn btn-outline-info btn-sm" onclick="window.open('../json_debug.php', '_blank')">
                                <i class="fas fa-bug me-1"></i>デバッグツール
                            </button>
                        </div>
                    </div>
                `;
                
                // 統計情報リセット
                if (selectedCount) selectedCount.textContent = '0';
                if (totalCount) totalCount.textContent = '0';
            }
        }

        /**
         * 対象一覧のレンダリング
         */
        function renderTargetList(targets) {
            const targetList = document.getElementById('targetList');
            
            if (!targets || targets.length === 0) {
                targetList.innerHTML = `
                    <div class="text-center text-muted">
                        <i class="fas fa-info-circle me-2"></i>対象データが見つかりませんでした
                        <div class="mt-2">
                            <small>選択した請求書タイプに該当するデータがありません</small>
                        </div>
                    </div>
                `;
                return;
            }
            
            const html = targets.map(target => `
                <div class="target-item" data-id="${target.id}" data-type="${target.type}" onclick="toggleTarget(${target.id})">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="target_${target.id}" onchange="toggleTarget(${target.id})">
                        <label class="form-check-label" for="target_${target.id}">
                            <strong>${escapeHtml(target.name)}</strong>
                            <small class="d-block text-muted">${escapeHtml(target.description)}</small>
                        </label>
                    </div>
                </div>
            `).join('');
            
            targetList.innerHTML = html;
            debugLog(`対象一覧レンダリング完了: ${targets.length}件`);
        }

        /**
         * 対象の選択/選択解除
         */
        function toggleTarget(targetId) {
            const checkbox = document.getElementById(`target_${targetId}`);
            const targetItem = document.querySelector(`[data-id="${targetId}"]`);
            
            if (selectedTargets.has(targetId)) {
                selectedTargets.delete(targetId);
                checkbox.checked = false;
                targetItem.classList.remove('selected');
                debugLog(`対象選択解除: ${targetId}`);
            } else {
                selectedTargets.add(targetId);
                checkbox.checked = true;
                targetItem.classList.add('selected');
                debugLog(`対象選択: ${targetId}`);
            }
            
            updateSelectionStats();
        }

        /**
         * 全選択
         */
        function selectAll() {
            debugLog('全選択実行');
            
            targetData.forEach(target => {
                selectedTargets.add(target.id);
                const checkbox = document.getElementById(`target_${target.id}`);
                const targetItem = document.querySelector(`[data-id="${target.id}"]`);
                
                if (checkbox) checkbox.checked = true;
                if (targetItem) targetItem.classList.add('selected');
            });
            
            updateSelectionStats();
        }

        /**
         * 選択解除
         */
        function selectNone() {
            debugLog('選択解除実行');
            
            selectedTargets.clear();
            
            targetData.forEach(target => {
                const checkbox = document.getElementById(`target_${target.id}`);
                const targetItem = document.querySelector(`[data-id="${target.id}"]`);
                
                if (checkbox) checkbox.checked = false;
                if (targetItem) targetItem.classList.remove('selected');
            });
            
            updateSelectionStats();
        }

        /**
         * 選択状況の更新
         */
        function updateSelectionStats() {
            const selectedCount = document.getElementById('selectedCount');
            const totalCount = document.getElementById('totalCount');
            
            if (selectedCount) selectedCount.textContent = selectedTargets.size;
            if (totalCount) totalCount.textContent = targetData.length;
        }

        /**
         * 期間テンプレート設定
         */
        function setPeriodTemplate(template) {
            debugLog(`期間テンプレート設定: ${template}`);
            
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
                    startDate = new Date();
                    startDate.setDate(startDate.getDate() - 30);
                    endDate = today;
                    break;
            }
            
            const formatDate = (date) => date.toISOString().split('T')[0];
            
            document.getElementById('period_start').value = formatDate(startDate);
            document.getElementById('period_end').value = formatDate(endDate);
            
            debugLog(`期間設定完了: ${formatDate(startDate)} ～ ${formatDate(endDate)}`);
        }

        /**
         * 請求書生成実行
         */
        async function generateInvoices() {
            const generateButton = document.getElementById('generateButton');
            const loadingSpinner = generateButton.querySelector('.loading-spinner');
            
            debugLog('請求書生成開始');
            
            try {
                // バリデーション
                if (selectedTargets.size === 0) {
                    alert('請求書を生成する対象を選択してください。');
                    return;
                }
                
                const periodStart = document.getElementById('period_start').value;
                const periodEnd = document.getElementById('period_end').value;
                
                if (!periodStart || !periodEnd) {
                    alert('請求期間を設定してください。');
                    return;
                }
                
                // UI状態変更
                generateButton.disabled = true;
                loadingSpinner.style.display = 'inline-block';
                
                showProgress('請求書生成を開始しています...', 10);
                
                // 請求書生成APIリクエスト
                const requestData = {
                    action: 'generate',
                    invoice_type: currentInvoiceType,
                    period_start: periodStart,
                    period_end: periodEnd,
                    due_date: document.getElementById('due_date').value,
                    target_ids: Array.from(selectedTargets),
                    auto_generate_pdf: document.getElementById('auto_pdf').checked
                };
                
                debugLog('請求書生成リクエストデータ:', JSON.stringify(requestData, null, 2));
                
                showProgress('APIに送信中...', 30);
                
                const response = await fetch('../api/invoices.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(requestData)
                });
                
                showProgress('レスポンス処理中...', 70);
                
                const responseText = await response.text();
                debugLog(`生成レスポンス: ${responseText}`);
                
                // JSONパース（エラーハンドリング付き）
                let result;
                try {
                    result = JSON.parse(responseText);
                } catch (parseError) {
                    throw new Error(`JSONパースエラー: ${parseError.message}\n\nレスポンス: ${responseText.substring(0, 200)}...`);
                }
                
                showProgress('処理完了', 100);
                
                if (!result.success) {
                    throw new Error(result.message || '請求書生成に失敗しました');
                }
                
                // 成功表示
                hideProgress();
                showGenerationResult(result);
                debugLog('請求書生成完了');
                
            } catch (error) {
                debugLog(`請求書生成エラー: ${error.message}`);
                console.error('Invoice generation error:', error);
                
                hideProgress();
                showGenerationError(error.message);
                
            } finally {
                // UI状態復旧
                generateButton.disabled = false;
                loadingSpinner.style.display = 'none';
            }
        }

        /**
         * 進捗表示
         */
        function showProgress(message, percentage) {
            const progressContainer = document.getElementById('progressContainer');
            const progressBar = progressContainer.querySelector('.progress-bar');
            const progressText = document.getElementById('progressText');
            
            progressContainer.style.display = 'block';
            progressBar.style.width = `${percentage}%`;
            progressBar.setAttribute('aria-valuenow', percentage);
            progressText.textContent = message;
            
            debugLog(`進捗: ${percentage}% - ${message}`);
        }

        /**
         * 進捗非表示
         */
        function hideProgress() {
            const progressContainer = document.getElementById('progressContainer');
            progressContainer.style.display = 'none';
        }

        /**
         * 生成結果の表示
         */
        function showGenerationResult(result) {
            const resultContainer = document.getElementById('resultContainer');
            
            resultContainer.className = 'result-container alert alert-success result-success';
            resultContainer.style.display = 'block';
            resultContainer.innerHTML = `
                <h5><i class="fas fa-check-circle me-2"></i>請求書生成完了</h5>
                <p class="mb-2">${escapeHtml(result.message)}</p>
                <div class="row">
                    <div class="col-md-4">
                        <strong>生成件数:</strong> ${result.data.generated_invoices}件
                    </div>
                    <div class="col-md-4">
                        <strong>合計金額:</strong> ¥${numberFormat(result.data.total_amount)}
                    </div>
                    <div class="col-md-4">
                        <a href="../pages/invoices.php" class="btn btn-primary btn-sm">
                            <i class="fas fa-list me-1"></i>請求書一覧で確認
                        </a>
                    </div>
                </div>
            `;
            
            // 成功時は選択をクリア
            selectNone();
        }

        /**
         * 生成エラーの表示
         */
        function showGenerationError(errorMessage) {
            const resultContainer = document.getElementById('resultContainer');
            
            resultContainer.className = 'result-container alert alert-danger result-error';
            resultContainer.style.display = 'block';
            resultContainer.innerHTML = `
                <h5><i class="fas fa-exclamation-triangle me-2"></i>請求書生成エラー</h5>
                <p class="mb-2"><strong>エラー内容:</strong></p>
                <p class="small text-danger">${escapeHtml(errorMessage)}</p>
                <div class="mt-3">
                    <button class="btn btn-outline-danger btn-sm me-2" onclick="generateInvoices()">
                        <i class="fas fa-redo me-1"></i>再試行
                    </button>
                    <button class="btn btn-outline-info btn-sm" onclick="toggleDebugInfo()">
                        <i class="fas fa-bug me-1"></i>詳細情報
                    </button>
                </div>
            `;
        }

        /**
         * デバッグ情報の表示/非表示切り替え
         */
        function toggleDebugInfo() {
            debugMode = !debugMode;
            const debugInfo = document.getElementById('debugInfo');
            
            if (debugMode) {
                debugInfo.style.display = 'block';
                updateDebugInfo();
            } else {
                debugInfo.style.display = 'none';
            }
            
            debugLog(`デバッグモード: ${debugMode ? 'ON' : 'OFF'}`);
        }

        /**
         * デバッグ情報の更新
         */
        function updateDebugInfo() {
            const debugContent = document.getElementById('debugContent');
            
            const info = {
                'Current Invoice Type': currentInvoiceType,
                'Selected Targets': Array.from(selectedTargets),
                'Target Data Count': targetData.length,
                'Form Data': {
                    period_start: document.getElementById('period_start').value,
                    period_end: document.getElementById('period_end').value,
                    due_date: document.getElementById('due_date').value,
                    auto_pdf: document.getElementById('auto_pdf').checked
                },
                'Browser Info': {
                    userAgent: navigator.userAgent,
                    url: window.location.href,
                    timestamp: new Date().toISOString()
                }
            };
            
            debugContent.innerHTML = JSON.stringify(info, null, 2);
        }

        /**
         * デバッグログ出力
         */
        function debugLog(message, data = null) {
            const timestamp = new Date().toISOString();
            const logMessage = `[${timestamp}] ${message}`;
            
            console.log(logMessage, data || '');
            
            // デバッグモードが有効な場合は画面にも表示
            if (debugMode) {
                updateDebugInfo();
            }
        }

        /**
         * HTMLエスケープ
         */
        function escapeHtml(unsafe) {
            return unsafe
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        /**
         * 数値フォーマット（カンマ区切り）
         */
        function numberFormat(num) {
            return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        }

        /**
         * エラーレポート送信（開発時のみ）
         */
        function sendErrorReport(error, context = {}) {
            const report = {
                error: {
                    message: error.message,
                    stack: error.stack,
                    timestamp: new Date().toISOString()
                },
                context: {
                    currentInvoiceType: currentInvoiceType,
                    selectedTargets: Array.from(selectedTargets),
                    targetDataCount: targetData.length,
                    url: window.location.href,
                    userAgent: navigator.userAgent,
                    ...context
                }
            };
            
            console.error('Error Report:', report);
            
            // 本番環境では実際のエラーレポートAPIに送信
            // fetch('/api/error_report.php', {
            //     method: 'POST',
            //     headers: { 'Content-Type': 'application/json' },
            //     body: JSON.stringify(report)
            // });
        }

        // グローバル関数として公開
        window.loadTargets = loadTargets;
        window.selectAll = selectAll;
        window.selectNone = selectNone;
        window.toggleTarget = toggleTarget;
        window.setPeriodTemplate = setPeriodTemplate;
        window.generateInvoices = generateInvoices;
        window.toggleDebugInfo = toggleDebugInfo;
        window.debugLog = debugLog;

        // グローバルエラーハンドラー
        window.addEventListener('error', function(event) {
            debugLog('グローバルエラー:', event.error);
            sendErrorReport(event.error, { type: 'global_error' });
        });

        window.addEventListener('unhandledrejection', function(event) {
            debugLog('未処理のPromise拒否:', event.reason);
            sendErrorReport(new Error(event.reason), { type: 'unhandled_rejection' });
        });
    </script>
</body>
</html>
