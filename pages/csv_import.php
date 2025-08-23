<?php
/**
 * CSVインポート画面（HTML） - Smiley配食事業仕様対応
 * pages/csv_import.php
 * 既存SecurityHelperクラス対応版
 */

// セキュリティヘッダー
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// 設定読み込み
require_once '../config/database.php';
require_once '../classes/SecurityHelper.php';

// セッション開始（既存メソッド使用）
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// CSRF トークン生成
$csrfToken = SecurityHelper::generateCSRFToken();

// ページタイトル
$pageTitle = 'CSVインポート - Smiley配食事業';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    
    <style>
        .drag-drop-zone {
            border: 2px dashed #dee2e6;
            border-radius: 10px;
            padding: 40px;
            text-align: center;
            transition: all 0.3s ease;
            background-color: #f8f9fa;
            cursor: pointer;
        }
        
        .drag-drop-zone:hover,
        .drag-drop-zone.dragover {
            border-color: #198754;
            background-color: #d1e7dd;
        }
        
        .file-info {
            background-color: #e3f2fd;
            border: 1px solid #2196f3;
            border-radius: 5px;
            padding: 15px;
            margin: 15px 0;
        }
        
        .progress-container {
            display: none;
            margin: 20px 0;
        }
        
        .result-container {
            display: none;
            margin: 20px 0;
        }
        
        .error-details {
            max-height: 300px;
            overflow-y: auto;
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
        }
        
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin: 10px 0;
        }
        
        .format-guide {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 5px;
            padding: 15px;
            margin: 15px 0;
        }
    </style>
</head>
<body class="bg-light">
    <!-- ナビゲーション -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="../index.php">
                <i class="bi bi-building"></i> Smiley配食事業
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="../index.php">
                    <i class="bi bi-house"></i> ダッシュボード
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- ページヘッダー -->
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 text-primary">
                        <i class="bi bi-file-earmark-arrow-up"></i> CSVインポート
                    </h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="../index.php">ホーム</a></li>
                            <li class="breadcrumb-item active">CSVインポート</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>

        <!-- CSVフォーマットガイド -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="format-guide">
                    <h5 class="text-warning">
                        <i class="bi bi-info-circle"></i> Smiley配食事業 CSVフォーマット仕様
                    </h5>
                    <div class="row">
                        <div class="col-md-6">
                            <strong>必須フィールド（5項目）：</strong>
                            <ul class="mb-2">
                                <li>配達日</li>
                                <li>社員CD（利用者コード）</li>
                                <li>社員名（利用者名）</li>
                                <li>事業所CD（配達先企業コード）</li>
                                <li>事業所名（配達先企業名）</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <strong>対応フィールド：</strong>
                            <span class="badge bg-success">全23フィールド対応</span>
                            <br><small class="text-muted">部門、メニュー、給食業者、雇用形態等</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- メインコンテンツ -->
        <div class="row">
            <!-- アップロード画面 -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">
                            <i class="bi bi-cloud-upload"></i> CSVファイルアップロード
                        </h5>
                    </div>
                    <div class="card-body">
                        <!-- ファイルアップロードフォーム -->
                        <form id="csvUploadForm" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                            
                            <!-- ドラッグ&ドロップエリア -->
                            <div class="drag-drop-zone" id="dropZone">
                                <i class="bi bi-cloud-upload fs-1 text-muted"></i>
                                <h5 class="mt-3">CSVファイルをドラッグ&ドロップ</h5>
                                <p class="text-muted">または <strong>クリックしてファイルを選択</strong></p>
                                <input type="file" id="csvFile" name="csv_file" accept=".csv,.txt" style="display: none;">
                            </div>

                            <!-- ファイル情報表示 -->
                            <div id="fileInfo" class="file-info" style="display: none;">
                                <h6><i class="bi bi-file-earmark-text"></i> 選択されたファイル</h6>
                                <div id="fileName"></div>
                                <div id="fileSize"></div>
                                <div id="fileType"></div>
                            </div>

                            <!-- インポートオプション -->
                            <div class="row mt-3">
                                <div class="col-md-6">
                                    <label for="encoding" class="form-label">文字エンコーディング</label>
                                    <select name="encoding" id="encoding" class="form-select">
                                        <option value="auto">自動検出</option>
                                        <option value="UTF-8">UTF-8</option>
                                        <option value="SJIS-win">Shift-JIS（Windows）</option>
                                        <option value="EUC-JP">EUC-JP</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">オプション</label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="overwrite" id="overwrite">
                                        <label class="form-check-label" for="overwrite">
                                            重複データを上書き
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="dry_run" id="dryRun">
                                        <label class="form-check-label" for="dryRun">
                                            テスト実行（実際には保存しない）
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- アップロードボタン -->
                            <div class="d-grid gap-2 mt-4">
                                <button type="submit" id="uploadBtn" class="btn btn-success btn-lg" disabled>
                                    <i class="bi bi-upload"></i> CSVインポート実行
                                </button>
                            </div>
                        </form>

                        <!-- プログレスバー -->
                        <div class="progress-container" id="progressContainer">
                            <h6>インポート進行中...</h6>
                            <div class="progress">
                                <div class="progress-bar progress-bar-striped progress-bar-animated" 
                                     id="progressBar" role="progressbar" style="width: 0%"></div>
                            </div>
                            <div class="text-center mt-2">
                                <small id="progressText">処理中...</small>
                            </div>
                        </div>

                        <!-- 結果表示 -->
                        <div class="result-container" id="resultContainer">
                            <div id="resultContent"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- サイドバー -->
            <div class="col-lg-4">
                <!-- 統計情報 -->
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h6 class="mb-0">
                            <i class="bi bi-graph-up"></i> インポート統計
                        </h6>
                    </div>
                    <div class="card-body">
                        <div id="importStats">
                            <div class="text-center text-muted">
                                <i class="bi bi-hourglass-split fs-1"></i>
                                <p>CSVをアップロードして統計を表示</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- インポート履歴 -->
                <div class="card mt-3">
                    <div class="card-header bg-secondary text-white">
                        <h6 class="mb-0">
                            <i class="bi bi-clock-history"></i> 最近のインポート
                        </h6>
                    </div>
                    <div class="card-body">
                        <div id="importHistory">
                            <small class="text-muted">履歴を読み込み中...</small>
                        </div>
                    </div>
                </div>

                <!-- ヘルプ -->
                <div class="card mt-3">
                    <div class="card-header bg-warning text-dark">
                        <h6 class="mb-0">
                            <i class="bi bi-question-circle"></i> ヘルプ
                        </h6>
                    </div>
                    <div class="card-body">
                        <small>
                            <strong>サポートファイル形式：</strong><br>
                            .csv, .txt（10MB以下）<br><br>
                            
                            <strong>文字エンコーディング：</strong><br>
                            UTF-8、Shift-JIS、EUC-JP<br><br>
                            
                            <strong>問題がある場合：</strong><br>
                            <a href="mailto:support@smiley.co.jp">support@smiley.co.jp</a>
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // ページ読み込み時の処理
        document.addEventListener('DOMContentLoaded', function() {
            initializeFileUpload();
            loadImportHistory();
        });

        /**
         * ファイルアップロード機能初期化
         */
        function initializeFileUpload() {
            const dropZone = document.getElementById('dropZone');
            const fileInput = document.getElementById('csvFile');
            const uploadForm = document.getElementById('csvUploadForm');
            const uploadBtn = document.getElementById('uploadBtn');

            // ドラッグ&ドロップイベント
            dropZone.addEventListener('click', () => fileInput.click());
            
            dropZone.addEventListener('dragover', (e) => {
                e.preventDefault();
                dropZone.classList.add('dragover');
            });
            
            dropZone.addEventListener('dragleave', () => {
                dropZone.classList.remove('dragover');
            });
            
            dropZone.addEventListener('drop', (e) => {
                e.preventDefault();
                dropZone.classList.remove('dragover');
                
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    fileInput.files = files;
                    handleFileSelect(files[0]);
                }
            });

            // ファイル選択イベント
            fileInput.addEventListener('change', (e) => {
                if (e.target.files.length > 0) {
                    handleFileSelect(e.target.files[0]);
                }
            });

            // フォーム送信イベント
            uploadForm.addEventListener('submit', handleFormSubmit);
        }

        /**
         * ファイル選択処理
         */
        function handleFileSelect(file) {
            const fileInfo = document.getElementById('fileInfo');
            const fileName = document.getElementById('fileName');
            const fileSize = document.getElementById('fileSize');
            const fileType = document.getElementById('fileType');
            const uploadBtn = document.getElementById('uploadBtn');

            // ファイル検証
            if (!validateFile(file)) {
                return;
            }

            // ファイル情報表示
            fileName.innerHTML = `<strong>ファイル名:</strong> ${file.name}`;
            fileSize.innerHTML = `<strong>サイズ:</strong> ${formatFileSize(file.size)}`;
            fileType.innerHTML = `<strong>形式:</strong> ${file.type || 'text/csv'}`;
            
            fileInfo.style.display = 'block';
            uploadBtn.disabled = false;
        }

        /**
         * ファイル検証
         */
        function validateFile(file) {
            const allowedTypes = ['text/csv', 'application/csv', 'text/plain'];
            const allowedExtensions = ['csv', 'txt'];
            const maxSize = 10 * 1024 * 1024; // 10MB

            // 拡張子チェック
            const extension = file.name.split('.').pop().toLowerCase();
            if (!allowedExtensions.includes(extension)) {
                showError('CSV またはTXT ファイルを選択してください');
                return false;
            }

            // サイズチェック
            if (file.size > maxSize) {
                showError('ファイルサイズは10MB以下にしてください');
                return false;
            }

            return true;
        }

        /**
         * フォーム送信処理
         */
        async function handleFormSubmit(e) {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            const progressContainer = document.getElementById('progressContainer');
            const resultContainer = document.getElementById('resultContainer');
            const uploadBtn = document.getElementById('uploadBtn');

            try {
                // UI更新
                uploadBtn.disabled = true;
                progressContainer.style.display = 'block';
                resultContainer.style.display = 'none';
                
                updateProgress(0, 'アップロード開始...');

                // APIリクエスト
                const response = await fetch('../api/import.php', {
                    method: 'POST',
                    body: formData
                });

                updateProgress(50, 'サーバー処理中...');

                const result = await response.json();
                
                updateProgress(100, '完了');

                // 結果表示
                setTimeout(() => {
                    displayResult(result);
                    progressContainer.style.display = 'none';
                }, 1000);

            } catch (error) {
                console.error('アップロードエラー:', error);
                showError('アップロード中にエラーが発生しました: ' + error.message);
                progressContainer.style.display = 'none';
            } finally {
                uploadBtn.disabled = false;
            }
        }

        /**
         * プログレス更新
         */
        function updateProgress(percent, text) {
            const progressBar = document.getElementById('progressBar');
            const progressText = document.getElementById('progressText');
            
            progressBar.style.width = percent + '%';
            progressText.textContent = text;
        }

        /**
         * 結果表示
         */
        function displayResult(result) {
            const resultContainer = document.getElementById('resultContainer');
            const resultContent = document.getElementById('resultContent');
            
            if (result.success) {
                resultContent.innerHTML = createSuccessResult(result);
                if (result.data && result.data.stats) {
                    updateImportStats(result.data.stats);
                }
            } else {
                resultContent.innerHTML = createErrorResult(result);
            }
            
            resultContainer.style.display = 'block';
            loadImportHistory(); // 履歴更新
        }

        /**
         * 成功結果HTML生成
         */
        function createSuccessResult(result) {
            const stats = result.data?.stats || {
                total_records: 0,
                success_records: 0,
                error_records: 0,
                duplicate_records: 0
            };
            
            return `
                <div class="alert alert-success">
                    <h5><i class="bi bi-check-circle"></i> インポート完了</h5>
                    <p>${result.message}</p>
                </div>
                
                <div class="row">
                    <div class="col-6 col-md-3">
                        <div class="text-center p-3 bg-primary text-white rounded">
                            <div class="fs-4">${stats.total_records || 0}</div>
                            <small>総レコード数</small>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="text-center p-3 bg-success text-white rounded">
                            <div class="fs-4">${stats.success_records || 0}</div>
                            <small>成功</small>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="text-center p-3 bg-danger text-white rounded">
                            <div class="fs-4">${stats.error_records || 0}</div>
                            <small>エラー</small>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="text-center p-3 bg-warning text-dark rounded">
                            <div class="fs-4">${stats.duplicate_records || 0}</div>
                            <small>重複</small>
                        </div>
                    </div>
                </div>
                
                ${(stats.error_records > 0 && result.data?.errors) ? createErrorDetails(result.data.errors) : ''}
            `;
        }

        /**
         * エラー結果HTML生成
         */
        function createErrorResult(result) {
            return `
                <div class="alert alert-danger">
                    <h5><i class="bi bi-exclamation-triangle"></i> インポートエラー</h5>
                    <p>${result.message}</p>
                    ${result.data?.error_message ? 
                        `<hr><small><strong>詳細:</strong> ${result.data.error_message}</small>` : ''}
                </div>
            `;
        }

        /**
         * エラー詳細HTML生成
         */
        function createErrorDetails(errors) {
            if (!errors || errors.length === 0) return '';
            
            let html = '<div class="mt-3"><h6>エラー詳細:</h6><div class="error-details">';
            
            errors.forEach((error, index) => {
                html += `<div class="mb-2">
                    <strong>行 ${error.row || (index + 1)}:</strong> ${error.message || error}
                </div>`;
            });
            
            html += '</div></div>';
            return html;
        }

        /**
         * 統計情報更新
         */
        function updateImportStats(stats) {
            const statsContainer = document.getElementById('importStats');
            
            statsContainer.innerHTML = `
                <div class="stats-card">
                    <div class="row text-center">
                        <div class="col-6">
                            <div class="fs-5">${stats.total_records || 0}</div>
                            <small>総レコード</small>
                        </div>
                        <div class="col-6">
                            <div class="fs-5">${stats.success_records || 0}</div>
                            <small>成功</small>
                        </div>
                    </div>
                    <div class="row text-center mt-2">
                        <div class="col-6">
                            <div class="fs-6">${stats.error_records || 0}</div>
                            <small>エラー</small>
                        </div>
                        <div class="col-6">
                            <div class="fs-6">${stats.processing_time || '不明'}</div>
                            <small>処理時間</small>
                        </div>
                    </div>
                </div>
            `;
        }

        /**
         * インポート履歴読み込み
         */
        async function loadImportHistory() {
            try {
                const response = await fetch('../api/import.php?action=history&limit=5');
                const result = await response.json();
                
                const historyContainer = document.getElementById('importHistory');
                
                if (result.success && result.data && result.data.length > 0) {
                    let html = '';
                    result.data.forEach(item => {
                        html += `
                            <div class="border-bottom pb-2 mb-2">
                                <div class="d-flex justify-content-between">
                                    <small><strong>${item.filename || '不明'}</strong></small>
                                    <small class="text-muted">${item.created_at || '不明'}</small>
                                </div>
                                <small class="text-muted">
                                    ${item.success_records || 0}件成功 / ${item.total_records || 0}件中
                                </small>
                            </div>
                        `;
                    });
                    historyContainer.innerHTML = html;
                } else {
                    historyContainer.innerHTML = '<small class="text-muted">履歴なし</small>';
                }
            } catch (error) {
                document.getElementById('importHistory').innerHTML = 
                    '<small class="text-muted">履歴読み込みエラー</small>';
            }
        }

        /**
         * エラー表示
         */
        function showError(message) {
            const resultContainer = document.getElementById('resultContainer');
            const resultContent = document.getElementById('resultContent');
            
            resultContent.innerHTML = `
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle"></i> ${message}
                </div>
            `;
            
            resultContainer.style.display = 'block';
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
