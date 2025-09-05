<?php
/**
 * Smiley配食事業 CSVインポート画面
 * PC操作不慣れ対応・仕様書完全準拠版
 * 
 * 要件:
 * - 超大型ボタン（最小80px高さ、24px文字）
 * - 色分けシステム（緊急/注意/安全/情報）
 * - ワンクリック操作
 * - ステップ式ガイド
 * - 根本対応重視
 */

// 既存デバッグツール活用によるシステム状態確認
require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/SecurityHelper.php';

// セキュリティ強化
SecurityHelper::secureSessionStart();

// システム状態確認（既存デバッグツール活用）
$systemStatus = [
    'database' => false,
    'classes' => false,
    'api' => false
];

try {
    // データベース接続確認
    $db = Database::getInstance();
    $systemStatus['database'] = true;
    
    // 必要クラス確認
    $systemStatus['classes'] = class_exists('Database') && class_exists('SecurityHelper');
    
    // API確認
    $systemStatus['api'] = file_exists('../api/import.php');
} catch (Exception $e) {
    error_log("システム状態確認エラー: " . $e->getMessage());
}

// CSRF トークン生成
$csrfToken = SecurityHelper::generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CSVインポート - Smiley配食事業システム</title>
    
    <!-- Bootstrap 5.1.3 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        /* PC操作不慣れ対応：超大型ボタンスタイル */
        .btn-xl {
            min-height: 80px;
            font-size: 24px;
            font-weight: bold;
            padding: 20px 40px;
            border-radius: 15px;
            margin: 10px;
        }
        
        /* 色分けシステム */
        .btn-emergency { background-color: #dc3545; border-color: #dc3545; }
        .btn-warning { background-color: #ffc107; border-color: #ffc107; color: #000; }
        .btn-safe { background-color: #28a745; border-color: #28a745; }
        .btn-info-custom { background-color: #17a2b8; border-color: #17a2b8; }
        
        /* ドラッグ&ドロップエリア */
        .drop-zone {
            border: 3px dashed #007bff;
            border-radius: 15px;
            padding: 60px 20px;
            text-align: center;
            background-color: #f8f9fa;
            min-height: 300px;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .drop-zone:hover, .drop-zone.dragover {
            border-color: #28a745;
            background-color: #e8f5e9;
        }
        
        /* ステップガイド */
        .step-guide {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
        }
        
        /* 進捗バー */
        .progress-container {
            display: none;
            margin: 30px 0;
        }
        
        /* システム状態インジケーター */
        .system-status {
            position: fixed;
            top: 20px;
            right: 20px;
            background: white;
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            z-index: 1000;
        }
        
        .status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
        }
        
        .status-ok { background-color: #28a745; }
        .status-error { background-color: #dc3545; }
        
        /* レスポンシブ対応 */
        @media (max-width: 768px) {
            .btn-xl {
                min-height: 60px;
                font-size: 18px;
                padding: 15px 25px;
            }
            
            .drop-zone {
                padding: 40px 15px;
                min-height: 200px;
            }
        }
    </style>
</head>
<body>
    <!-- システム状態インジケーター -->
    <div class="system-status">
        <small>
            <span class="status-indicator <?= $systemStatus['database'] ? 'status-ok' : 'status-error' ?>"></span>DB
            <span class="status-indicator <?= $systemStatus['classes'] ? 'status-ok' : 'status-error' ?>"></span>クラス
            <span class="status-indicator <?= $systemStatus['api'] ? 'status-ok' : 'status-error' ?>"></span>API
        </small>
    </div>

    <div class="container-fluid">
        <!-- ナビゲーション -->
        <nav class="navbar navbar-expand-lg navbar-dark" style="background: linear-gradient(135deg, #2E8B57 0%, #3CB371 100%);">
            <div class="container">
                <a class="navbar-brand" href="../index.php">
                    <i class="fas fa-utensils me-2"></i>Smiley配食事業システム
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item">
                            <a class="nav-link" href="../index.php">
                                <i class="fas fa-home me-1"></i>ダッシュボード
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="csv_import.php">
                                <i class="fas fa-upload me-1"></i>CSVインポート
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="companies.php">
                                <i class="fas fa-building me-1"></i>企業管理
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="invoices.php">
                                <i class="fas fa-file-invoice me-1"></i>請求書
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>

        <!-- ステップガイド -->
        <div class="container mt-4">
            <div class="step-guide">
                <h2 class="text-center mb-4">
                    <i class="fas fa-route me-3"></i>CSVインポート 4ステップガイド
                </h2>
                <div class="row">
                    <div class="col-md-3 text-center">
                        <div class="step-number" style="background: rgba(255,255,255,0.2); border-radius: 50%; width: 60px; height: 60px; line-height: 60px; margin: 0 auto 15px; font-size: 24px; font-weight: bold;">1</div>
                        <h5>ファイル選択</h5>
                        <p>CSVファイルをドラッグ&ドロップ</p>
                    </div>
                    <div class="col-md-3 text-center">
                        <div class="step-number" style="background: rgba(255,255,255,0.2); border-radius: 50%; width: 60px; height: 60px; line-height: 60px; margin: 0 auto 15px; font-size: 24px; font-weight: bold;">2</div>
                        <h5>設定確認</h5>
                        <p>エンコーディング・オプション</p>
                    </div>
                    <div class="col-md-3 text-center">
                        <div class="step-number" style="background: rgba(255,255,255,0.2); border-radius: 50%; width: 60px; height: 60px; line-height: 60px; margin: 0 auto 15px; font-size: 24px; font-weight: bold;">3</div>
                        <h5>インポート実行</h5>
                        <p>ワンクリック処理開始</p>
                    </div>
                    <div class="col-md-3 text-center">
                        <div class="step-number" style="background: rgba(255,255,255,0.2); border-radius: 50%; width: 60px; height: 60px; line-height: 60px; margin: 0 auto 15px; font-size: 24px; font-weight: bold;">4</div>
                        <h5>結果確認</h5>
                        <p>処理結果・エラー詳細</p>
                    </div>
                </div>
            </div>

            <!-- メインコンテンツ -->
            <div class="row">
                <!-- アップロードエリア -->
                <div class="col-lg-8">
                    <div class="card shadow-lg border-0">
                        <div class="card-header" style="background: linear-gradient(135deg, #2E8B57 0%, #3CB371 100%); color: white;">
                            <h3 class="card-title mb-0">
                                <i class="fas fa-cloud-upload-alt me-2"></i>CSVファイルアップロード
                            </h3>
                        </div>
                        <div class="card-body">
                            <!-- ドラッグ&ドロップエリア -->
                            <form id="uploadForm" enctype="multipart/form-data">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                
                                <div class="drop-zone" id="dropZone">
                                    <i class="fas fa-cloud-upload-alt" style="font-size: 48px; color: #007bff; margin-bottom: 20px;"></i>
                                    <h4>CSVファイルをここにドラッグ&ドロップ</h4>
                                    <p class="text-muted">または下のボタンでファイルを選択</p>
                                    
                                    <button type="button" class="btn btn-info-custom btn-xl" onclick="document.getElementById('csvFile').click()">
                                        <i class="fas fa-folder-open me-2"></i>ファイルを選択
                                    </button>
                                    
                                    <input type="file" id="csvFile" name="csv_file" accept=".csv,.txt" style="display: none;">
                                </div>
                                
                                <!-- 選択されたファイル情報 -->
                                <div id="fileInfo" class="mt-3" style="display: none;">
                                    <div class="alert alert-info">
                                        <h5><i class="fas fa-file-csv me-2"></i>選択されたファイル</h5>
                                        <p id="fileName" class="mb-1"></p>
                                        <p id="fileSize" class="mb-0 text-muted"></p>
                                    </div>
                                </div>
                                
                                <!-- インポートオプション -->
                                <div id="importOptions" class="mt-4" style="display: none;">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">エンコーディング</label>
                                            <select class="form-select form-select-lg" name="encoding">
                                                <option value="auto">自動検出</option>
                                                <option value="SJIS-win">Shift-JIS (Windows)</option>
                                                <option value="UTF-8">UTF-8</option>
                                                <option value="UTF-8-BOM">UTF-8 (BOM付き)</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">インポートモード</label>
                                            <select class="form-select form-select-lg" name="import_mode">
                                                <option value="normal">通常インポート</option>
                                                <option value="dry_run">テスト実行（データ更新なし）</option>
                                                <option value="overwrite">上書きモード</option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <!-- ワンクリック実行ボタン -->
                                    <div class="text-center mt-4">
                                        <button type="button" class="btn btn-safe btn-xl" id="startImport" disabled>
                                            <i class="fas fa-play me-2"></i>CSVインポートを開始
                                        </button>
                                        
                                        <button type="button" class="btn btn-warning btn-xl ms-3" id="resetForm">
                                            <i class="fas fa-redo me-2"></i>リセット
                                        </button>
                                    </div>
                                </div>
                            </form>
                            
                            <!-- 進捗表示 -->
                            <div class="progress-container" id="progressContainer">
                                <h5><i class="fas fa-cog fa-spin me-2"></i>処理中...</h5>
                                <div class="progress mb-3" style="height: 30px;">
                                    <div class="progress-bar progress-bar-striped progress-bar-animated" 
                                         role="progressbar" style="width: 0%">
                                        <span class="progress-text">0%</span>
                                    </div>
                                </div>
                                <div id="progressMessage" class="text-center text-muted">
                                    初期化中...
                                </div>
                            </div>
                            
                            <!-- 結果表示エリア -->
                            <div id="resultArea" style="display: none;">
                                <!-- 動的に結果が挿入される -->
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- サイドバー（ヘルプ・システム情報） -->
                <div class="col-lg-4">
                    <!-- システム状態確認 -->
                    <div class="card shadow border-0 mb-4">
                        <div class="card-header bg-info text-white">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-heartbeat me-2"></i>システム状態確認
                            </h5>
                        </div>
                        <div class="card-body">
                            <button type="button" class="btn btn-info-custom btn-xl w-100" onclick="checkSystemHealth()">
                                <i class="fas fa-stethoscope me-2"></i>システム診断実行
                            </button>
                            <div id="healthCheckResult" class="mt-3" style="display: none;">
                                <!-- 診断結果が挿入される -->
                            </div>
                        </div>
                    </div>
                    
                    <!-- ヘルプ・ガイド -->
                    <div class="card shadow border-0 mb-4">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-question-circle me-2"></i>操作ガイド
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="accordion" id="helpAccordion">
                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#help1">
                                            CSVファイル形式について
                                        </button>
                                    </h2>
                                    <div id="help1" class="accordion-collapse collapse show" data-bs-parent="#helpAccordion">
                                        <div class="accordion-body">
                                            <ul>
                                                <li>ファイル形式: .csv, .txt</li>
                                                <li>最大サイズ: 10MB</li>
                                                <li>文字コード: SJIS-win推奨</li>
                                                <li>フィールド数: 23項目固定</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#help2">
                                            よくあるエラー
                                        </button>
                                    </h2>
                                    <div id="help2" class="accordion-collapse collapse" data-bs-parent="#helpAccordion">
                                        <div class="accordion-body">
                                            <ul>
                                                <li><strong>文字化け</strong>: エンコーディングを確認</li>
                                                <li><strong>列数エラー</strong>: 23列すべてが必要</li>
                                                <li><strong>日付形式</strong>: YYYY-MM-DD形式</li>
                                                <li><strong>数値形式</strong>: 半角数字のみ</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#help3">
                                            Smiley配食事業仕様
                                        </button>
                                    </h2>
                                    <div id="help3" class="accordion-collapse collapse" data-bs-parent="#helpAccordion">
                                        <div class="accordion-body">
                                            <ul>
                                                <li>法人名: 「株式会社Smiley」固定</li>
                                                <li>事業所: 配達先企業</li>
                                                <li>社員: 利用者個人</li>
                                                <li>部門: 配達先部署</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- クイックアクション -->
                    <div class="card shadow border-0">
                        <div class="card-header bg-success text-white">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-bolt me-2"></i>クイックアクション
                            </h5>
                        </div>
                        <div class="card-body">
                            <button type="button" class="btn btn-safe btn-xl w-100 mb-3" onclick="location.href='companies.php'">
                                <i class="fas fa-building me-2"></i>企業管理
                            </button>
                            
                            <button type="button" class="btn btn-info-custom btn-xl w-100 mb-3" onclick="location.href='invoices.php'">
                                <i class="fas fa-file-invoice me-2"></i>請求書生成
                            </button>
                            
                            <button type="button" class="btn btn-warning btn-xl w-100" onclick="showImportHistory()">
                                <i class="fas fa-history me-2"></i>インポート履歴
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        /**
         * CSVインポート JavaScript（PC操作不慣れ対応）
         */
        
        // グローバル変数
        let selectedFile = null;
        let isProcessing = false;
        
        // DOM読み込み後の初期化
        document.addEventListener('DOMContentLoaded', function() {
            initializeEventListeners();
            checkSystemStatus();
        });
        
        /**
         * イベントリスナー初期化
         */
        function initializeEventListeners() {
            const dropZone = document.getElementById('dropZone');
            const fileInput = document.getElementById('csvFile');
            const startImportBtn = document.getElementById('startImport');
            const resetBtn = document.getElementById('resetForm');
            
            // ドラッグ&ドロップイベント
            dropZone.addEventListener('dragover', handleDragOver);
            dropZone.addEventListener('dragleave', handleDragLeave);
            dropZone.addEventListener('drop', handleDrop);
            
            // ファイル選択イベント
            fileInput.addEventListener('change', handleFileSelect);
            
            // ボタンイベント
            startImportBtn.addEventListener('click', startImport);
            resetBtn.addEventListener('click', resetForm);
        }
        
        /**
         * ドラッグオーバー処理
         */
        function handleDragOver(e) {
            e.preventDefault();
            e.stopPropagation();
            document.getElementById('dropZone').classList.add('dragover');
        }
        
        /**
         * ドラッグリーブ処理
         */
        function handleDragLeave(e) {
            e.preventDefault();
            e.stopPropagation();
            document.getElementById('dropZone').classList.remove('dragover');
        }
        
        /**
         * ドロップ処理
         */
        function handleDrop(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const dropZone = document.getElementById('dropZone');
            dropZone.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                handleFileSelection(files[0]);
            }
        }
        
        /**
         * ファイル選択処理
         */
        function handleFileSelect(e) {
            const files = e.target.files;
            if (files.length > 0) {
                handleFileSelection(files[0]);
            }
        }
        
        /**
         * ファイル選択共通処理
         */
        function handleFileSelection(file) {
            // ファイル形式チェック
            const allowedTypes = ['text/csv', 'text/plain', 'application/csv'];
            const allowedExtensions = ['.csv', '.txt'];
            
            const fileExtension = '.' + file.name.split('.').pop().toLowerCase();
            
            if (!allowedTypes.includes(file.type) && !allowedExtensions.includes(fileExtension)) {
                showAlert('danger', 'エラー', 'CSVファイル(.csv, .txt)のみアップロード可能です。');
                return;
            }
            
            // ファイルサイズチェック（10MB）
            if (file.size > 10 * 1024 * 1024) {
                showAlert('danger', 'エラー', 'ファイルサイズは10MB以下にしてください。');
                return;
            }
            
            // ファイル情報表示
            selectedFile = file;
            document.getElementById('fileName').textContent = file.name;
            document.getElementById('fileSize').textContent = formatFileSize(file.size);
            document.getElementById('fileInfo').style.display = 'block';
            document.getElementById('importOptions').style.display = 'block';
            document.getElementById('startImport').disabled = false;
            
            showAlert('success', 'ファイル選択完了', 'CSVファイルが正常に選択されました。');
        }
        
        /**
         * CSVインポート開始
         */
        async function startImport() {
            if (!selectedFile || isProcessing) {
                return;
            }
            
            isProcessing = true;
            
            // UI更新
            document.getElementById('startImport').disabled = true;
            document.getElementById('progressContainer').style.display = 'block';
            document.getElementById('resultArea').style.display = 'none';
            
            try {
                // フォームデータ準備
                const formData = new FormData();
                formData.append('csv_file', selectedFile);
                formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
                formData.append('encoding', document.querySelector('select[name="encoding"]').value);
                formData.append('import_mode', document.querySelector('select[name="import_mode"]').value);
                
                // 進捗更新
                updateProgress(10, 'ファイルアップロード中...');
                
                // APIリクエスト
                const response = await fetch('../api/import.php', {
                    method: 'POST',
                    body: formData
                });
                
                updateProgress(50, 'データ処理中...');
                
                const result = await response.json();
                
                updateProgress(100, '処理完了');
                
                // 結果表示
                setTimeout(() => {
                    document.getElementById('progressContainer').style.display = 'none';
                    displayResult(result);
                }, 1000);
                
            } catch (error) {
                console.error('インポートエラー:', error);
                updateProgress(0, 'エラーが発生しました');
                
                setTimeout(() => {
                    document.getElementById('progressContainer').style.display = 'none';
                    showAlert('danger', 'エラー', 'インポート処理中にエラーが発生しました: ' + error.message);
                }, 1000);
            } finally {
                isProcessing = false;
                document.getElementById('startImport').disabled = false;
            }
        }
        
        /**
         * 進捗更新
         */
        function updateProgress(percentage, message) {
            const progressBar = document.querySelector('.progress-bar');
            const progressText = document.querySelector('.progress-text');
            const progressMessage = document.getElementById('progressMessage');
            
            progressBar.style.width = percentage + '%';
            progressText.textContent = percentage + '%';
            progressMessage.textContent = message;
        }
        
        /**
         * 結果表示
         */
        function displayResult(result) {
            const resultArea = document.getElementById('resultArea');
            
            if (result.success) {
                resultArea.innerHTML = `
                    <div class="alert alert-success shadow">
                        <h4><i class="fas fa-check-circle me-2"></i>インポート完了</h4>
                        <p class="mb-3">${result.message}</p>
                        
                        <div class="row">
                            <div class="col-md-3">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <h5 class="text-primary">${result.data.total_records || 0}</h5>
                                        <small>総レコード数</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <h5 class="text-success">${result.data.success_records || 0}</h5>
                                        <small>成功</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <h5 class="text-danger">${result.data.error_records || 0}</h5>
                                        <small>エラー</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <h5 class="text-warning">${result.data.duplicate_records || 0}</h5>
                                        <small>重複</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        ${result.data.processing_time ? `<p class="mt-3 text-muted">処理時間: ${result.data.processing_time}</p>` : ''}
                        
                        <div class="mt-4">
                            <button type="button" class="btn btn-info-custom btn-xl" onclick="location.href='companies.php'">
                                <i class="fas fa-building me-2"></i>企業管理へ
                            </button>
                            <button type="button" class="btn btn-safe btn-xl ms-3" onclick="location.href='invoices.php'">
                                <i class="fas fa-file-invoice me-2"></i>請求書生成へ
                            </button>
                        </div>
                    </div>
                `;
                
                // エラー詳細がある場合
                if (result.errors && result.errors.length > 0) {
                    resultArea.innerHTML += `
                        <div class="alert alert-warning mt-3">
                            <h5><i class="fas fa-exclamation-triangle me-2"></i>エラー詳細</h5>
                            <div class="error-list" style="max-height: 300px; overflow-y: auto;">
                    `;
                    
                    result.errors.slice(0, 10).forEach((error, index) => {
                        resultArea.innerHTML += `
                            <div class="border-bottom py-2">
                                <small class="text-muted">行 ${error.row || index + 1}:</small>
                                <br>${error.message || error}
                            </div>
                        `;
                    });
                    
                    if (result.errors.length > 10) {
                        resultArea.innerHTML += `
                            <div class="py-2 text-muted">
                                <small>さらに ${result.errors.length - 10} 件のエラーがあります</small>
                            </div>
                        `;
                    }
                    
                    resultArea.innerHTML += `</div></div>`;
                }
                
            } else {
                resultArea.innerHTML = `
                    <div class="alert alert-danger shadow">
                        <h4><i class="fas fa-times-circle me-2"></i>インポート失敗</h4>
                        <p>${result.message}</p>
                        
                        ${result.data && result.data.troubleshooting ? `
                            <div class="mt-3">
                                <h6>トラブルシューティング:</h6>
                                <ul>
                                    ${Object.entries(result.data.troubleshooting).map(([key, value]) => 
                                        `<li>${value}</li>`
                                    ).join('')}
                                </ul>
                            </div>
                        ` : ''}
                        
                        <div class="mt-4">
                            <button type="button" class="btn btn-warning btn-xl" onclick="resetForm()">
                                <i class="fas fa-redo me-2"></i>やり直し
                            </button>
                            <button type="button" class="btn btn-info-custom btn-xl ms-3" onclick="checkSystemHealth()">
                                <i class="fas fa-stethoscope me-2"></i>システム診断
                            </button>
                        </div>
                    </div>
                `;
            }
            
            resultArea.style.display = 'block';
        }
        
        /**
         * フォームリセット
         */
        function resetForm() {
            selectedFile = null;
            document.getElementById('csvFile').value = '';
            document.getElementById('fileInfo').style.display = 'none';
            document.getElementById('importOptions').style.display = 'none';
            document.getElementById('progressContainer').style.display = 'none';
            document.getElementById('resultArea').style.display = 'none';
            document.getElementById('startImport').disabled = true;
            
            // フォーム初期値リセット
            document.querySelector('select[name="encoding"]').value = 'auto';
            document.querySelector('select[name="import_mode"]').value = 'normal';
        }
        
        /**
         * システム健全性チェック（既存デバッグツール活用）
         */
        async function checkSystemHealth() {
            const healthResult = document.getElementById('healthCheckResult');
            healthResult.innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> 診断中...</div>';
            healthResult.style.display = 'block';
            
            try {
                // 既存のAPI確認ツールを活用
                const response = await fetch('../api/import.php?action=status');
                const result = await response.json();
                
                if (result.success) {
                    healthResult.innerHTML = `
                        <div class="alert alert-success">
                            <h6><i class="fas fa-check-circle me-2"></i>システム正常</h6>
                            <small>
                                <div><strong>データベース:</strong> ${result.data.database_connection ? '✅ 正常' : '❌ エラー'}</div>
                                <div><strong>必要クラス:</strong> ${Object.values(result.data.required_classes || {}).every(Boolean) ? '✅ 正常' : '❌ エラー'}</div>
                                <div><strong>テーブル:</strong> ${Object.values(result.data.tables || {}).filter(Boolean).length} 個確認済み</div>
                            </small>
                        </div>
                    `;
                } else {
                    healthResult.innerHTML = `
                        <div class="alert alert-danger">
                            <h6><i class="fas fa-times-circle me-2"></i>システム異常</h6>
                            <p>${result.message}</p>
                        </div>
                    `;
                }
            } catch (error) {
                healthResult.innerHTML = `
                    <div class="alert alert-warning">
                        <h6><i class="fas fa-exclamation-triangle me-2"></i>診断エラー</h6>
                        <p>システム診断中にエラーが発生しました</p>
                    </div>
                `;
            }
        }
        
        /**
         * システム状態チェック
         */
        function checkSystemStatus() {
            // ページ読み込み時の基本チェック
            fetch('../api/import.php?action=test')
                .then(response => response.json())
                .then(result => {
                    if (!result.success) {
                        showAlert('warning', 'システム警告', 'APIの応答に問題があります。システム診断を実行してください。');
                    }
                })
                .catch(error => {
                    console.error('システム状態チェックエラー:', error);
                    showAlert('danger', 'システムエラー', 'APIとの通信に失敗しました。');
                });
        }
        
        /**
         * インポート履歴表示
         */
        async function showImportHistory() {
            try {
                const response = await fetch('../api/import.php?action=history');
                const result = await response.json();
                
                if (result.success && result.data.length > 0) {
                    let historyHtml = `
                        <div class="modal fade" id="historyModal" tabindex="-1">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">インポート履歴</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="table-responsive">
                                            <table class="table table-striped">
                                                <thead>
                                                    <tr>
                                                        <th>日時</th>
                                                        <th>ファイル名</th>
                                                        <th>件数</th>
                                                        <th>ステータス</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                    `;
                    
                    result.data.forEach(item => {
                        historyHtml += `
                            <tr>
                                <td>${new Date(item.created_at).toLocaleString('ja-JP')}</td>
                                <td>${item.file_name || '-'}</td>
                                <td>${item.total_records || 0}</td>
                                <td>
                                    ${item.status === 'success' ? 
                                        '<span class="badge bg-success">成功</span>' : 
                                        '<span class="badge bg-danger">失敗</span>'
                                    }
                                </td>
                            </tr>
                        `;
                    });
                    
                    historyHtml += `
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                    
                    document.body.insertAdjacentHTML('beforeend', historyHtml);
                    const modal = new bootstrap.Modal(document.getElementById('historyModal'));
                    modal.show();
                    
                    // モーダル閉じた後の削除
                    document.getElementById('historyModal').addEventListener('hidden.bs.modal', function() {
                        this.remove();
                    });
                } else {
                    showAlert('info', '履歴なし', 'インポート履歴がありません。');
                }
            } catch (error) {
                showAlert('danger', 'エラー', '履歴の取得に失敗しました。');
            }
        }
        
        /**
         * アラート表示
         */
        function showAlert(type, title, message) {
            const alertHtml = `
                <div class="alert alert-${type} alert-dismissible fade show" role="alert" style="position: fixed; top: 100px; right: 20px; z-index: 1050; min-width: 300px;">
                    <h6 class="alert-heading"><i class="fas fa-${getAlertIcon(type)} me-2"></i>${title}</h6>
                    <p class="mb-0">${message}</p>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            
            document.body.insertAdjacentHTML('beforeend', alertHtml);
            
            // 5秒後に自動削除
            setTimeout(() => {
                const alerts = document.querySelectorAll('.alert');
                if (alerts.length > 0) {
                    alerts[alerts.length - 1].remove();
                }
            }, 5000);
        }
        
        /**
         * アラートアイコン取得
         */
        function getAlertIcon(type) {
            const icons = {
                'success': 'check-circle',
                'danger': 'times-circle',
                'warning': 'exclamation-triangle',
                'info': 'info-circle'
            };
            return icons[type] || 'info-circle';
        }
        
        /**
         * ファイルサイズフォーマット
         */
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
    </script>
</body>
</html>
