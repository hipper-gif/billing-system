<?php
/**
 * 請求書生成画面
 * Smiley配食事業専用の請求書生成インターフェース
 * 
 * @author Claude
 * @version 1.0.0
 * @created 2025-08-26
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
                            
                            <div class="invoice-type-card" data-type="company_bulk">
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
