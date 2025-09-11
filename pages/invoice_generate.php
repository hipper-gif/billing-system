<?php
/**
 * 請求書生成画面
 * Smiley配食事業専用の請求書生成インターフェース
 * 
 * @author Claude
 * @version 1.0.1 - 根本修正版
 * @modified 2025-09-11
 */

require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/SecurityHelper.php';
require_once __DIR__ . '/../classes/InvoiceGenerator.php'; // ← 重要: 追加

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

        /* PC操作不慣れ対応 - 仕様書準拠の大型ボタン */
        .btn-generate {
            background: linear-gradient(135deg, var(--smiley-primary), var(--smiley-secondary));
            border: none;
            color: white;
            padding: 20px 40px; /* 大型化 */
            border-radius: 25px;
            font-weight: 600;
            font-size: 24px; /* 仕様書準拠: 24px以上 */
            min-height: 80px; /* 仕様書準拠: 80px以上 */
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

        /* PC操作不慣れ対応 - 大型選択カード */
        .invoice-type-card {
            border: 3px solid #e9ecef; /* 太い境界線 */
            border-radius: 12px;
            padding: 20px; /* 大型化 */
            margin-bottom: 15px; /* 間隔拡大 */
            cursor: pointer;
            transition: all 0.3s ease;
            min-height: 100px; /* 最小高さ確保 */
        }

        .invoice-type-card:hover {
            border-color: var(--smiley-primary);
            box-shadow: 0 4px 12px rgba(255, 107, 53, 0.2);
            transform: translateY(-2px);
        }

        .invoice-type-card.selected {
            border-color: var(--smiley-primary);
            background: rgba(255, 107, 53, 0.1);
            box-shadow: 0 4px 12px rgba(255, 107, 53, 0.3);
        }

        /* PC操作不慣れ対応 - 大型入力フィールド */
        .form-control {
            min-height: 50px; /* 大型化 */
            font-size: 18px; /* 大きな文字 */
            padding: 15px 20px;
        }

        .form-check-input {
            width: 24px; /* 大型チェックボックス */
            height: 24px;
        }

        .form-check-label {
            font-size: 18px; /* 大きな文字 */
            margin-left: 10px;
        }

        .target-selector {
            min-height: 250px; /* 高さ拡大 */
            max-height: 400px;
            overflow-y: auto;
            border: 2px solid #dee2e6; /* 太い境界線 */
            border-radius: 12px;
            padding: 20px; /* パディング拡大 */
        }

        .target-item {
            padding: 15px 20px; /* 大型化 */
            border-radius: 8px;
            margin-bottom: 10px; /* 間隔拡大 */
            cursor: pointer;
            transition: background-color 0.2s ease;
            font-size: 16px; /* 文字サイズ */
            border: 2px solid transparent;
        }

        .target-item:hover {
            background-color: #f8f9fa;
            border-color: var(--smiley-primary);
        }

        .target-item.selected {
            background-color: var(--smiley-accent);
            color: #333;
            border-color: var(--smiley-primary);
            font-weight: bold;
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
            padding: 20px;
            border-radius: 8px;
            font-size: 18px;
        }

        .result-error {
            border-left: 4px solid var(--smiley-danger);
            background: rgba(244, 67, 54, 0.1);
            padding: 20px;
            border-radius: 8px;
            font-size: 18px;
        }

        .statistics-card {
            background: linear-gradient(135deg, #e3f2fd, #bbdefb);
            border-radius: 12px;
            padding: 20px; /* パディング拡大 */
            margin-bottom: 1rem;
            border: 2px solid #2196f3;
        }

        .form-check-input:checked {
            background-color: var(--smiley-primary);
            border-color: var(--smiley-primary);
        }

        .loading-spinner {
            display: none;
        }

        .preview-table {
            font-size: 16px; /* 大きな文字 */
        }

        .badge-invoice-type {
            font-size: 14px; /* 大きな文字 */
            padding: 8px 16px; /* 大型化 */
        }

        /* 操作ガイド - PC操作不慣れ対応 */
        .operation-guide {
            background: linear-gradient(135deg, #e8f5e8, #c8e6c8);
            border: 3px solid var(--smiley-success);
            border-radius: 15px;
            padding: 25px;
            margin: 25px 0;
            font-size: 20px;
            font-weight: bold;
        }

        .step-indicator {
            display: flex;
            align-items: center;
            gap: 15px;
            margin: 15px 0;
        }

        .step-number {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--smiley-primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 18px;
        }

        .step-arrow {
            font-size: 24px;
            color: var(--smiley-primary);
        }

        /* 期間テンプレートボタン - 大型化 */
        .btn-template {
            min-height: 60px;
            font-size: 16px;
            padding: 15px 25px;
            margin: 5px;
        }

        /* 確認モーダル - PC操作不慣れ対応 */
        .confirmation-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }

        .confirmation-content {
            background: white;
            border-radius: 20px;
            padding: 40px;
            max-width: 600px;
            margin: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
            text-align: center;
        }

        .confirmation-title {
            font-size: 28px;
            font-weight: bold;
            color: var(--smiley-danger);
            margin-bottom: 20px;
        }

        .confirmation-details {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
            text-align: left;
            font-size: 18px;
        }

        .confirmation-buttons {
            display: flex;
            gap: 20px;
            justify-content: center;
            margin-top: 30px;
        }

        .btn-confirm {
            min-height: 80px;
            font-size: 20px;
            padding: 20px 40px;
            border-radius: 15px;
            font-weight: bold;
            min-width: 160px;
        }

        /* レスポンシブ対応 */
        @media (max-width: 768px) {
            .btn-generate {
                width: 100%;
                margin: 20px 0;
            }
            
            .invoice-type-card {
                margin-bottom: 20px;
            }
            
            .confirmation-buttons {
                flex-direction: column;
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
                <a class="nav-link active" href="../pages/invoice_generate.php">請求書生成</a>
                <a class="nav-link" href="../pages/invoices.php">請求書一覧</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- 操作ガイド - PC操作不慣れ対応 -->
        <div class="operation-guide">
            <div style="font-size: 24px; margin-bottom: 20px;">
                📋 現在の作業: 請求書生成
            </div>
            <div class="step-indicator">
                <div class="step-number">1</div>
                <span>請求書タイプ選択</span>
                <span class="step-arrow">→</span>
                <div class="step-number">2</div>
                <span>期間設定</span>
                <span class="step-arrow">→</span>
                <div class="step-number">3</div>
                <span>対象選択</span>
                <span class="step-arrow">→</span>
                <div class="step-number">4</div>
                <span>生成実行</span>
            </div>
        </div>

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
                            
                            <div class="invoice-type-card" data-type="company">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="billing_type" id="type_company" value="company" checked>
                                    <label class="form-check-label" for="type_company">
                                        <strong>企業一括請求</strong>
                                        <small class="d-block text-muted">配達先企業ごとに一括で請求書を生成</small>
                                    </label>
                                </div>
                            </div>

                            <div class="invoice-type-card" data-type="department">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="billing_type" id="type_department" value="department">
                                    <label class="form-check-label" for="type_department">
                                        <strong>部署別一括請求</strong>
                                        <small class="d-block text-muted">部署ごとに分けて請求書を生成</small>
                                    </label>
                                </div>
                            </div>

                            <div class="invoice-type-card" data-type="individual">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="billing_type" id="type_individual" value="individual">
                                    <label class="form-check-label" for="type_individual">
                                        <strong>個人請求</strong>
                                        <small class="d-block text-muted">利用者個人ごとに請求書を生成</small>
                                    </label>
                                </div>
                            </div>

                            <div class="invoice-type-card" data-type="mixed">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="billing_type" id="type_mixed" value="mixed">
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
                                    <input type="date" class="form-control" id="period_start" name="period_start" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="period_end" class="form-label">終了日</label>
                                    <input type="date" class="form-control" id="period_end" name="period_end" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="due_date" class="form-label">支払期限日</label>
                                <input type="date" class="form-control" id="due_date" name="due_date" placeholder="自動計算（期間終了日+30日）">
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
                                <div class="d-flex flex-wrap gap-2">
                                    <button type="button" class="btn btn-outline-primary btn-template" onclick="setPeriodTemplate('this_month')">今月</button>
                                    <button type="button" class="btn btn-outline-primary btn-template" onclick="setPeriodTemplate('last_month')">先月</button>
                                    <button type="button" class="btn btn-outline-primary btn-template" onclick="setPeriodTemplate('this_quarter')">今四半期</button>
                                    <button type="button" class="btn btn-outline-primary btn-template" onclick="setPeriodTemplate('custom_range')">過去30日</button>
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

                    <!-- プログレス表示 -->
                    <div class="progress-container" id="progressContainer">
                        <h6><i class="fas fa-clock me-2"></i>処理中...</h6>
                        <div class="progress mb-3" style="height: 30px;">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" 
                                 role="progressbar" style="width: 0%" id="progressBar">0%</div>
                        </div>
                        <p class="text-center text-muted" id="progressMessage">請求書を生成しています...</p>
                    </div>

                    <!-- 結果表示 -->
                    <div class="result-container" id="resultContainer">
                        <div id="resultContent"></div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- 確認モーダル -->
    <div class="confirmation-modal" id="confirmationModal">
        <div class="confirmation-content">
            <div class="confirmation-title">
                ⚠️ 本当に請求書を作成しますか？
            </div>
            
            <div class="confirmation-details" id="confirmationDetails">
                <!-- JavaScript で動的に設定 -->
            </div>
            
            <div style="color: #666; margin: 20px 0; font-size: 16px;">
                この操作は取り消すことができません。<br>
                内容をよく確認してから実行してください。
            </div>
            
            <div class="confirmation-buttons">
                <button class="btn btn-success btn-confirm" onclick="executeGeneration()">
                    <i class="fas fa-check me-2"></i>はい、作成する
                </button>
                <button class="btn btn-danger btn-confirm" onclick="closeConfirmation()">
                    <i class="fas fa-times me-2"></i>いいえ、やめる
                </button>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        let selectedTargets = [];
        let allTargets = [];
        let currentBillingType = 'company';

        // 初期化
        document.addEventListener('DOMContentLoaded', function() {
            initializeDatePickers();
            loadTargets();
            setupEventListeners();
            setPeriodTemplate('this_month'); // デフォルトで今月を設定
        });

        // 日付ピッカー初期化
        function initializeDatePickers() {
            flatpickr("#period_start", {
                dateFormat: "Y-m-d",
                maxDate: "today"
            });
            
            flatpickr("#period_end", {
                dateFormat: "Y-m-d",
                maxDate: "today"
            });
            
            flatpickr("#due_date", {
                dateFormat: "Y-m-d",
                minDate: "today"
            });
        }

        // イベントリスナー設定
        function setupEventListeners() {
            // 請求書タイプ変更
            document.querySelectorAll('input[name="billing_type"]').forEach(radio => {
                radio.addEventListener('change', function() {
                    currentBillingType = this.value;
                    updateInvoiceTypeCards();
                    loadTargets();
                });
            });

            // 請求書タイプカードクリック
            document.querySelectorAll('.invoice-type-card').forEach(card => {
                card.addEventListener('click', function() {
                    const type = this.getAttribute('data-type');
                    const radio = document.getElementById('type_' + type);
                    if (radio) {
                        radio.checked = true;
                        currentBillingType = type;
                        updateInvoiceTypeCards();
                        loadTargets();
                    }
                });
            });

            // フォーム送信
            document.getElementById('invoiceGenerationForm').addEventListener('submit', function(e) {
                e.preventDefault();
                showConfirmation();
            });
        }

        // 請求書タイプカードの表示更新
        function updateInvoiceTypeCards() {
            document.querySelectorAll('.invoice-type-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            const selectedCard = document.querySelector(`.invoice-type-card[data-type="${currentBillingType}"]`);
            if (selectedCard) {
                selectedCard.classList.add('selected');
            }
        }

        // 対象一覧読み込み
        function loadTargets() {
            const targetList = document.getElementById('targetList');
            targetList.innerHTML = '<div class="text-center text-muted"><i class="fas fa-spinner fa-spin me-2"></i>読み込み中...</div>';

            let action = 'companies';
            if (currentBillingType === 'department') action = 'departments';
            if (currentBillingType === 'individual') action = 'users';
            if (currentBillingType === 'mixed') action = 'mixed';

            fetch(`../api/invoice_targets.php?action=${action}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayTargets(data.data);
                    } else {
                        throw new Error(data.message);
                    }
                })
                .catch(error => {
                    console.error('Error loading targets:', error);
                    targetList.innerHTML = `<div class="alert alert-danger">対象一覧の読み込みに失敗しました。<br>${error.message}</div>`;
                });
        }

        // 対象一覧表示
        function displayTargets(data) {
            const targetList = document.getElementById('targetList');
            const targets = data.companies || data.departments || data.users || [];
            allTargets = targets;
            selectedTargets = [];

            if (targets.length === 0) {
                targetList.innerHTML = '<div class="alert alert-warning">対象が見つかりません。</div>';
                updateSelectionStats();
                return;
            }

            let html = '';
            targets.forEach(target => {
                const name = target.company_name || target.department_name || target.user_name || '名前不明';
                const subtitle = getTargetSubtitle(target);
                const stats = getTargetStats(target);

                html += `
                    <div class="target-item" data-id="${target.id}" onclick="toggleTarget(${target.id})">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong>${name}</strong>
                                ${subtitle ? `<small class="d-block text-muted">${subtitle}</small>` : ''}
                            </div>
                            <div class="text-end">
                                <small class="text-muted">${stats}</small>
                            </div>
                        </div>
                    </div>
                `;
            });

            targetList.innerHTML = html;
            updateSelectionStats();
        }

        // 対象のサブタイトル取得
        function getTargetSubtitle(target) {
            if (target.company_name && target.department_name) {
                return `${target.company_name} - ${target.department_name}`;
            }
            if (target.company_name && target.user_name) {
                return target.company_name;
            }
            return target.company_code || target.department_code || target.user_code || '';
        }

        // 対象の統計情報取得
        function getTargetStats(target) {
            const userCount = target.user_count || 0;
            const recentOrders = target.recent_orders || 0;
            const recentAmount = target.recent_amount || 0;

            if (currentBillingType === 'individual') {
                return `${recentOrders}件 (¥${Number(recentAmount).toLocaleString()})`;
            } else {
                return `${userCount}名 ${recentOrders}件 (¥${Number(recentAmount).toLocaleString()})`;
            }
        }

        // 対象選択切り替え
        function toggleTarget(targetId) {
            const index = selectedTargets.indexOf(targetId);
            const targetElement = document.querySelector(`[data-id="${targetId}"]`);

            if (index > -1) {
                selectedTargets.splice(index, 1);
                targetElement.classList.remove('selected');
            } else {
                selectedTargets.push(targetId);
                targetElement.classList.add('selected');
            }

            updateSelectionStats();
        }

        // 全選択
        function selectAll() {
            selectedTargets = allTargets.map(target => target.id);
            document.querySelectorAll('.target-item').forEach(item => {
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
            document.getElementById('totalCount').textContent = allTargets.length;
        }

        // 期間テンプレート設定
        function setPeriodTemplate(templateType) {
            const today = new Date();
            let startDate, endDate;

            switch (templateType) {
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
                    startDate = new Date(today.getTime() - 30 * 24 * 60 * 60 * 1000);
                    break;
                default:
                    return;
            }

            document.getElementById('period_start').value = formatDate(startDate);
            document.getElementById('period_end').value = formatDate(endDate);

            // 支払期限日を自動計算（終了日+30日）
            const dueDate = new Date(endDate.getTime() + 30 * 24 * 60 * 60 * 1000);
            document.getElementById('due_date').value = formatDate(dueDate);
        }

        // 日付フォーマット
        function formatDate(date) {
            return date.getFullYear() + '-' + 
                   String(date.getMonth() + 1).padStart(2, '0') + '-' + 
                   String(date.getDate()).padStart(2, '0');
        }

        // 確認モーダル表示
        function showConfirmation() {
            const formData = new FormData(document.getElementById('invoiceGenerationForm'));
            const periodStart = formData.get('period_start');
            const periodEnd = formData.get('period_end');
            const dueDate = formData.get('due_date');
            const autoPdf = formData.get('auto_pdf') ? 'あり' : 'なし';

            // 入力値検証
            if (!periodStart || !periodEnd) {
                alert('請求期間の開始日と終了日を入力してください。');
                return;
            }

            if (selectedTargets.length === 0) {
                alert('請求対象を選択してください。');
                return;
            }

            const billingTypeNames = {
                'company': '企業一括請求',
                'department': '部署別請求',
                'individual': '個人請求',
                'mixed': '混合請求（自動判定）'
            };

            const confirmationDetails = `
                <div><strong>請求書タイプ:</strong> ${billingTypeNames[currentBillingType]}</div>
                <div><strong>対象:</strong> ${selectedTargets.length}件</div>
                <div><strong>期間:</strong> ${periodStart} ～ ${periodEnd}</div>
                <div><strong>支払期限:</strong> ${dueDate || '自動計算'}</div>
                <div><strong>PDF自動生成:</strong> ${autoPdf}</div>
            `;

            document.getElementById('confirmationDetails').innerHTML = confirmationDetails;
            document.getElementById('confirmationModal').style.display = 'flex';
        }

        // 確認モーダル閉じる
        function closeConfirmation() {
            document.getElementById('confirmationModal').style.display = 'none';
        }

        // 請求書生成実行
        function executeGeneration() {
            closeConfirmation();
            
            const formData = new FormData(document.getElementById('invoiceGenerationForm'));
            const generateButton = document.getElementById('generateButton');
            const progressContainer = document.getElementById('progressContainer');
            const resultContainer = document.getElementById('resultContainer');

            // UI状態更新
            generateButton.disabled = true;
            generateButton.querySelector('.loading-spinner').style.display = 'inline';
            progressContainer.style.display = 'block';
            resultContainer.style.display = 'none';

            // リクエストデータ準備
            const requestData = {
                billing_type: currentBillingType,
                period_start: formData.get('period_start'),
                period_end: formData.get('period_end'),
                due_date: formData.get('due_date'),
                auto_generate_pdf: formData.get('auto_pdf') ? true : false,
                company_ids: currentBillingType === 'company' ? selectedTargets : undefined,
                department_ids: currentBillingType === 'department' ? selectedTargets : undefined,
                user_ids: currentBillingType === 'individual' ? selectedTargets : undefined,
                invoice_date: new Date().toISOString().split('T')[0]
            };

            // プログレス更新開始
            let progress = 0;
            const progressInterval = setInterval(() => {
                progress += 10;
                updateProgress(progress, '請求書を生成しています...');
                if (progress >= 90) {
                    clearInterval(progressInterval);
                }
            }, 200);

            // API呼び出し
            fetch('../api/invoices.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(requestData)
            })
            .then(response => response.json())
            .then(data => {
                clearInterval(progressInterval);
                updateProgress(100, '完了しました！');
                
                setTimeout(() => {
                    showResult(data);
                    resetForm();
                }, 1000);
            })
            .catch(error => {
                clearInterval(progressInterval);
                console.error('Error generating invoices:', error);
                showResult({
                    success: false,
                    message: '請求書生成中にエラーが発生しました: ' + error.message
                });
                resetForm();
            });
        }

        // プログレス更新
        function updateProgress(percent, message) {
            const progressBar = document.getElementById('progressBar');
            const progressMessage = document.getElementById('progressMessage');
            
            progressBar.style.width = percent + '%';
            progressBar.textContent = percent + '%';
            progressMessage.textContent = message;
        }

        // 結果表示
        function showResult(data) {
            const resultContainer = document.getElementById('resultContainer');
            const resultContent = document.getElementById('resultContent');
            
            let html = '';
            if (data.success) {
                html = `
                    <div class="result-success">
                        <h5><i class="fas fa-check-circle me-2"></i>請求書生成完了</h5>
                        <p><strong>${data.message || '請求書が正常に生成されました'}</strong></p>
                        ${data.data ? `
                            <div class="mt-3">
                                <div>生成件数: ${data.data.total_invoices || data.generated_invoices || 0}件</div>
                                <div>総金額: ¥${Number(data.data.total_amount || data.total_amount || 0).toLocaleString()}</div>
                            </div>
                        ` : ''}
                        <div class="mt-3">
                            <a href="../pages/invoices.php" class="btn btn-primary">
                                <i class="fas fa-list me-2"></i>請求書一覧を確認
                            </a>
                        </div>
                    </div>
                `;
            } else {
                html = `
                    <div class="result-error">
                        <h5><i class="fas fa-exclamation-triangle me-2"></i>エラーが発生しました</h5>
                        <p><strong>${data.message || '請求書生成に失敗しました'}</strong></p>
                        <div class="mt-3">
                            <button class="btn btn-warning" onclick="location.reload()">
                                <i class="fas fa-redo me-2"></i>ページを更新
                            </button>
                        </div>
                    </div>
                `;
            }
            
            resultContent.innerHTML = html;
            resultContainer.style.display = 'block';
        }

        // フォームリセット
        function resetForm() {
            const generateButton = document.getElementById('generateButton');
            const progressContainer = document.getElementById('progressContainer');
            
            generateButton.disabled = false;
            generateButton.querySelector('.loading-spinner').style.display = 'none';
            
            setTimeout(() => {
                progressContainer.style.display = 'none';
            }, 2000);
        }

        // エラーハンドリング
        window.addEventListener('error', function(e) {
            console.error('JavaScript Error:', e.error);
        });

        // 未処理のPromise拒否をキャッチ
        window.addEventListener('unhandledrejection', function(e) {
            console.error('Unhandled Promise Rejection:', e.reason);
        });
    </script>
</body>
</html>
