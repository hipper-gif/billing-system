<?php
/**
 * CSVインポート画面（HTMLアップロード画面）
 * pages/csv_import.php
 */
require_once '../config/database.php';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CSVインポート - Smiley配食事業システム</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <style>
        :root {
            --smiley-primary: #2E8B57;
            --smiley-secondary: #20B2AA;
            --smiley-success: #28a745;
            --smiley-warning: #ffc107;
            --smiley-danger: #dc3545;
            --smiley-light: #f8f9fa;
        }
        
        body {
            background: linear-gradient(135deg, var(--smiley-light) 0%, #e8f5e8 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .navbar {
            background: linear-gradient(135deg, var(--smiley-primary) 0%, var(--smiley-secondary) 100%);
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .card {
            border: none;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            border-radius: 15px;
            overflow: hidden;
        }
        
        .card-header {
            background: linear-gradient(135deg, var(--smiley-primary) 0%, var(--smiley-secondary) 100%);
            color: white;
            border: none;
            padding: 1.5rem;
        }
        
        .upload-area {
            border: 3px dashed #ddd;
            border-radius: 15px;
            padding: 3rem;
            text-align: center;
            background: white;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
        }
        
        .upload-area:hover,
        .upload-area.dragover {
            border-color: var(--smiley-primary);
            background: #f0f8f0;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(46, 139, 87, 0.2);
        }
        
        .upload-icon {
            font-size: 4rem;
            color: var(--smiley-primary);
            margin-bottom: 1rem;
        }
        
        .progress {
            height: 25px;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .progress-bar {
            background: linear-gradient(135deg, var(--smiley-primary) 0%, var(--smiley-secondary) 100%);
            transition: width 0.3s ease;
        }
        
        .btn-smiley {
            background: linear-gradient(135deg, var(--smiley-primary) 0%, var(--smiley-secondary) 100%);
            border: none;
            color: white;
            padding: 0.75rem 2rem;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(46, 139, 87, 0.3);
        }
        
        .btn-smiley:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(46, 139, 87, 0.4);
            color: white;
        }
        
        .alert {
            border: none;
            border-radius: 10px;
            padding: 1rem 1.5rem;
        }
        
        .stats-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: transform 0.3s ease;
        }
        
        .stats-card:hover {
            transform: translateY(-3px);
        }
        
        .stats-number {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        
        .file-info {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1rem;
            margin: 1rem 0;
            border-left: 4px solid var(--smiley-primary);
        }
        
        .spinner-border-sm {
            width: 1rem;
            height: 1rem;
        }
        
        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .error-list {
            max-height: 300px;
            overflow-y: auto;
        }
        
        .error-item {
            background: #fff5f5;
            border-left: 4px solid var(--smiley-danger);
            padding: 0.75rem;
            margin-bottom: 0.5rem;
            border-radius: 0 5px 5px 0;
        }
    </style>
</head>

<body>
    <!-- ナビゲーション -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand fw-bold" href="../index.php">
                <i class="fas fa-utensils me-2"></i>
                Smiley配食事業システム
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
                            <i class="fas fa-building me-1"></i>配達先企業
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container my-5">
        <!-- ページヘッダー -->
        <div class="row mb-4">
            <div class="col-12">
                <h1 class="display-6 text-center mb-3">
                    <i class="fas fa-file-csv text-primary me-3"></i>
                    CSVファイルインポート
                </h1>
                <p class="text-center text-muted">
                    Smiley配食事業の給食注文データをCSVファイルから一括取り込みします
                </p>
            </div>
        </div>

        <!-- アップロード画面 -->
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-cloud-upload-alt me-2"></i>
                            ファイルアップロード
                        </h5>
                    </div>
                    <div class="card-body p-4">
                        <!-- アップロード エリア -->
                        <div class="upload-area" id="uploadArea">
                            <div class="upload-icon">
                                <i class="fas fa-cloud-upload-alt"></i>
                            </div>
                            <h4>CSVファイルをドラッグ&ドロップ</h4>
                            <p class="text-muted mb-3">または、クリックしてファイルを選択</p>
                            <button type="button" class="btn btn-smiley" id="fileSelectBtn">
                                <i class="fas fa-folder-open me-2"></i>
                                ファイルを選択
                            </button>
                            <input type="file" id="csvFileInput" accept=".csv,.txt" style="display: none;">
                        </div>

                        <!-- ファイル情報 -->
                        <div id="fileInfo" class="file-info" style="display: none;">
                            <h6><i class="fas fa-file-csv me-2"></i>選択されたファイル</h6>
                            <div id="fileDetails"></div>
                        </div>

                        <!-- インポート オプション -->
                        <div id="importOptions" class="mt-4" style="display: none;">
                            <h6><i class="fas fa-cogs me-2"></i>インポート設定</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="encodingSelect" class="form-label">文字エンコーディング</label>
                                        <select id="encodingSelect" class="form-select">
                                            <option value="auto">自動検出</option>
                                            <option value="SJIS-win">Shift-JIS (Windows)</option>
                                            <option value="UTF-8">UTF-8</option>
                                            <option value="UTF-8-BOM">UTF-8 (BOM付き)</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label class="form-label">処理モード</label>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="overwriteCheck">
                                            <label class="form-check-label" for="overwriteCheck">
                                                重複データを上書き
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="dryRunCheck">
                                            <label class="form-check-label" for="dryRunCheck">
                                                テスト実行（データベースに保存しない）
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- インポート実行ボタン -->
                        <div id="uploadControls" class="text-center mt-4" style="display: none;">
                            <button type="button" class="btn btn-smiley btn-lg me-3" id="startImportBtn">
                                <i class="fas fa-play me-2"></i>
                                インポート開始
                            </button>
                            <button type="button" class="btn btn-outline-secondary" id="resetBtn">
                                <i class="fas fa-redo me-2"></i>
                                リセット
                            </button>
                        </div>

                        <!-- プログレスバー -->
                        <div id="progressSection" class="mt-4" style="display: none;">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span id="progressText">処理中...</span>
                                <span id="progressPercent">0%</span>
                            </div>
                            <div class="progress">
                                <div id="progressBar" class="progress-bar" role="progressbar" style="width: 0%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 結果表示 -->
        <div id="resultSection" class="row mt-4" style="display: none;">
            <div class="col-12">
                <div class="card fade-in">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-bar me-2"></i>
                            インポート結果
                        </h5>
                    </div>
                    <div class="card-body">
                        <!-- 統計表示 -->
                        <div class="row mb-4" id="statsRow">
                            <div class="col-md-3">
                                <div class="stats-card text-primary">
                                    <div class="stats-number" id="totalRecords">0</div>
                                    <div class="text-muted">総レコード数</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stats-card text-success">
                                    <div class="stats-number" id="successRecords">0</div>
                                    <div class="text-muted">成功</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stats-card text-danger">
                                    <div class="stats-number" id="errorRecords">0</div>
                                    <div class="text-muted">エラー</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="stats-card text-warning">
                                    <div class="stats-number" id="duplicateRecords">0</div>
                                    <div class="text-muted">重複</div>
                                </div>
                            </div>
                        </div>

                        <!-- 成功メッセージ -->
                        <div id="successAlert" class="alert alert-success" style="display: none;">
                            <i class="fas fa-check-circle me-2"></i>
                            <span id="successMessage"></span>
                        </div>

                        <!-- エラー表示 -->
                        <div id="errorAlert" class="alert alert-danger" style="display: none;">
                            <h6><i class="fas fa-exclamation-triangle me-2"></i>エラー詳細</h6>
                            <div id="errorList" class="error-list"></div>
                        </div>

                        <!-- 処理詳細 -->
                        <div id="processDetails" class="mt-3" style="display: none;">
                            <h6><i class="fas fa-info-circle me-2"></i>処理詳細</h6>
                            <div id="processInfo"></div>
                        </div>

                        <!-- 次のアクション -->
                        <div id="nextActions" class="mt-4 text-center" style="display: none;">
                            <h6>次のステップ</h6>
                            <a href="companies.php" class="btn btn-outline-primary me-2">
                                <i class="fas fa-building me-1"></i>配達先企業管理
                            </a>
                            <a href="users.php" class="btn btn-outline-primary me-2">
                                <i class="fas fa-users me-1"></i>利用者管理
                            </a>
                            <button type="button" class="btn btn-smiley" onclick="location.reload()">
                                <i class="fas fa-upload me-1"></i>新しいCSVをインポート
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // グローバル変数
        let selectedFile = null;
        let isUploading = false;

        // DOM要素取得
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('csvFileInput');
        const fileSelectBtn = document.getElementById('fileSelectBtn');
        const fileInfo = document.getElementById('fileInfo');
        const fileDetails = document.getElementById('fileDetails');
        const importOptions = document.getElementById('importOptions');
        const uploadControls = document.getElementById('uploadControls');
        const startImportBtn = document.getElementById('startImportBtn');
        const resetBtn = document.getElementById('resetBtn');
        const progressSection = document.getElementById('progressSection');
        const progressBar = document.getElementById('progressBar');
        const progressText = document.getElementById('progressText');
        const progressPercent = document.getElementById('progressPercent');
        const resultSection = document.getElementById('resultSection');

        // イベントリスナー設定
        document.addEventListener('DOMContentLoaded', function() {
            // ファイル選択ボタン
            fileSelectBtn.addEventListener('click', () => fileInput.click());
            
            // ファイル入力変更
            fileInput.addEventListener('change', handleFileSelect);
            
            // ドラッグ&ドロップ
            uploadArea.addEventListener('click', () => fileInput.click());
            uploadArea.addEventListener('dragover', handleDragOver);
            uploadArea.addEventListener('dragleave', handleDragLeave);
            uploadArea.addEventListener('drop', handleFileDrop);
            
            // ボタンイベント
            startImportBtn.addEventListener('click', startImport);
            resetBtn.addEventListener('click', resetForm);
            
            // フォーム送信防止
            document.addEventListener('submit', e => e.preventDefault());
        });

        // ドラッグオーバー処理
        function handleDragOver(e) {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        }

        // ドラッグ離脱処理
        function handleDragLeave(e) {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
        }

        // ファイルドロップ処理
        function handleFileDrop(e) {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                handleFile(files[0]);
            }
        }

        // ファイル選択処理
        function handleFileSelect(e) {
            if (e.target.files.length > 0) {
                handleFile(e.target.files[0]);
            }
        }

        // ファイル処理
        function handleFile(file) {
            // ファイル形式チェック
            if (!file.name.toLowerCase().endsWith('.csv') && !file.name.toLowerCase().endsWith('.txt')) {
                showAlert('CSVファイルまたはTXTファイルを選択してください。', 'danger');
                return;
            }

            // ファイルサイズチェック（10MB制限）
            if (file.size > 10 * 1024 * 1024) {
                showAlert('ファイルサイズが10MBを超えています。', 'danger');
                return;
            }

            selectedFile = file;
            displayFileInfo(file);
            showImportOptions();
        }

        // ファイル情報表示
        function displayFileInfo(file) {
            const sizeText = formatFileSize(file.size);
            const lastModified = new Date(file.lastModified).toLocaleString('ja-JP');
            
            fileDetails.innerHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <strong>ファイル名:</strong> ${file.name}<br>
                        <strong>サイズ:</strong> ${sizeText}
                    </div>
                    <div class="col-md-6">
                        <strong>種類:</strong> ${file.type || 'text/csv'}<br>
                        <strong>更新日:</strong> ${lastModified}
                    </div>
                </div>
            `;
            
            fileInfo.style.display = 'block';
        }

        // インポートオプション表示
        function showImportOptions() {
            importOptions.style.display = 'block';
            uploadControls.style.display = 'block';
        }

        // インポート開始
        async function startImport() {
            if (!selectedFile || isUploading) return;

            isUploading = true;
            startImportBtn.disabled = true;
            resetBtn.disabled = true;
            
            // プログレスバー表示
            progressSection.style.display = 'block';
            updateProgress(0, '処理開始中...');

            try {
                // FormData作成
                const formData = new FormData();
                formData.append('csv_file', selectedFile);
                formData.append('encoding', document.getElementById('encodingSelect').value);
                formData.append('overwrite', document.getElementById('overwriteCheck').checked ? '1' : '0');
                formData.append('dry_run', document.getElementById('dryRunCheck').checked ? '1' : '0');

                // プログレス更新
                updateProgress(25, 'ファイルアップロード中...');

                // APIリクエスト
                const response = await fetch('../api/debug_import.php', {
                    method: 'POST',
                    body: formData
                });

                updateProgress(75, 'データ処理中...');

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const result = await response.json();
                updateProgress(100, '完了');

                // 結果表示
                displayResult(result);

            } catch (error) {
                console.error('Import error:', error);
                updateProgress(0, 'エラーが発生しました');
                showAlert(`インポート中にエラーが発生しました: ${error.message}`, 'danger');
            } finally {
                isUploading = false;
                startImportBtn.disabled = false;
                resetBtn.disabled = false;
                
                setTimeout(() => {
                    progressSection.style.display = 'none';
                }, 3000);
            }
        }

        // 結果表示
        function displayResult(result) {
            if (result.success) {
                // 統計更新
                if (result.data && result.data.stats) {
                    const stats = result.data.stats;
                    document.getElementById('totalRecords').textContent = stats.total_records || 0;
                    document.getElementById('successRecords').textContent = stats.success_records || 0;
                    document.getElementById('errorRecords').textContent = stats.error_records || 0;
                    document.getElementById('duplicateRecords').textContent = stats.duplicate_records || 0;
                }

                // 成功メッセージ
                document.getElementById('successMessage').textContent = result.message;
                document.getElementById('successAlert').style.display = 'block';

                // エラー表示（エラーがある場合）
                if (result.data && result.data.errors && result.data.errors.length > 0) {
                    displayErrors(result.data.errors);
                }

                // 処理詳細表示
                if (result.data) {
                    displayProcessDetails(result.data);
                }

                // 次のアクション表示
                document.getElementById('nextActions').style.display = 'block';
            } else {
                showAlert(result.message || 'インポートに失敗しました', 'danger');
                
                if (result.data && result.data.errors) {
                    displayErrors(result.data.errors);
                }
            }

            resultSection.style.display = 'block';
            resultSection.scrollIntoView({ behavior: 'smooth' });
        }

        // エラー表示
        function displayErrors(errors) {
            const errorList = document.getElementById('errorList');
            errorList.innerHTML = errors.map(error => `
                <div class="error-item">
                    <strong>行 ${error.row || '?'}:</strong> ${error.message || error}
                </div>
            `).join('');
            
            document.getElementById('errorAlert').style.display = 'block';
        }

        // 処理詳細表示
        function displayProcessDetails(data) {
            const details = [];
            
            if (data.batch_id) details.push(`バッチID: ${data.batch_id}`);
            if (data.filename) details.push(`ファイル名: ${data.filename}`);
            if (data.stats && data.stats.processing_time) details.push(`処理時間: ${data.stats.processing_time}`);
            if (data.import_summary && data.import_summary.encoding_detected) details.push(`エンコーディング: ${data.import_summary.encoding_detected}`);

            if (details.length > 0) {
                document.getElementById('processInfo').innerHTML = details.map(detail => `
                    <span class="badge bg-info me-2 mb-1">${detail}</span>
                `).join('');
                document.getElementById('processDetails').style.display = 'block';
            }
        }

        // プログレス更新
        function updateProgress(percent, text) {
            progressBar.style.width = percent + '%';
            progressPercent.textContent = percent + '%';
            progressText.textContent = text;
        }

        // アラート表示
        function showAlert(message, type = 'info') {
            // 既存のアラートを削除
            const existingAlert = document.querySelector('.alert-custom');
            if (existingAlert) {
                existingAlert.remove();
            }

            // 新しいアラート作成
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show alert-custom`;
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;

            // アップロードエリアの前に挿入
            uploadArea.parentNode.insertBefore(alertDiv, uploadArea);
            
            // 5秒後に自動削除
            setTimeout(() => {
                if (alertDiv && alertDiv.parentNode) {
                    alertDiv.remove();
                }
            }, 5000);
        }

        // ファイルサイズフォーマット
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        // フォームリセット
        function resetForm() {
            selectedFile = null;
            fileInput.value = '';
            
            // 表示要素をリセット
            fileInfo.style.display = 'none';
            importOptions.style.display = 'none';
            uploadControls.style.display = 'none';
            progressSection.style.display = 'none';
            resultSection.style.display = 'none';
            
            // フォーム値をリセット
            document.getElementById('encodingSelect').value = 'auto';
            document.getElementById('overwriteCheck').checked = false;
            document.getElementById('dryRunCheck').checked = false;
            
            // 既存のアラートを削除
            const existingAlert = document.querySelector('.alert-custom');
            if (existingAlert) {
                existingAlert.remove();
            }
            
            showAlert('フォームがリセットされました。', 'info');
        }

        // ページ読み込み時の初期化
        window.addEventListener('load', function() {
            // API接続テスト
            fetch('../api/import.php?action=test')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        console.log('✅ CSVインポートAPI接続確認:', data.message);
                        console.log('📊 システム情報:', data.data);
                    } else {
                        console.warn('⚠️ API接続警告:', data.message);
                        showAlert('API接続に問題があります。管理者に連絡してください。', 'warning');
                    }
                })
                .catch(error => {
                    console.error('❌ API接続エラー:', error);
                    showAlert('APIとの接続に失敗しました。ネットワーク接続を確認してください。', 'danger');
                });
        });

        // エラーハンドリング
        window.addEventListener('error', function(e) {
            console.error('JavaScript Error:', e.error);
            showAlert('予期しないエラーが発生しました。ページを再読み込みしてください。', 'danger');
        });

        // 未処理の Promise エラーをキャッチ
        window.addEventListener('unhandledrejection', function(e) {
            console.error('Unhandled Promise Rejection:', e.reason);
            showAlert('処理中にエラーが発生しました。再試行してください。', 'warning');
        });

        // デバッグ用ログ機能
        function logDebugInfo(message, data = null) {
            const timestamp = new Date().toLocaleTimeString('ja-JP');
            console.log(`[${timestamp}] ${message}`, data || '');
        }

        // システム状況確認機能
        async function checkSystemStatus() {
            try {
                const response = await fetch('../api/import.php?action=status');
                const result = await response.json();
                
                if (result.success) {
                    logDebugInfo('✅ システム状況確認完了', result.data);
                    return result.data;
                } else {
                    logDebugInfo('⚠️ システム状況に問題があります', result.message);
                    return null;
                }
            } catch (error) {
                logDebugInfo('❌ システム状況確認エラー', error.message);
                return null;
            }
        }

        // CSV形式事前チェック機能
        function preCheckCSVFormat(file) {
            return new Promise((resolve, reject) => {
                const reader = new FileReader();
                reader.onload = function(e) {
                    try {
                        const content = e.target.result;
                        const lines = content.split('\n');
                        
                        if (lines.length < 2) {
                            reject(new Error('CSVファイルにヘッダー行とデータ行が必要です'));
                            return;
                        }

                        const headerLine = lines[0].trim();
                        if (!headerLine) {
                            reject(new Error('CSVファイルのヘッダー行が空です'));
                            return;
                        }

                        // フィールド数チェック
                        const fields = headerLine.split(',');
                        if (fields.length < 10) {
                            reject(new Error(`フィールド数が少なすぎます（${fields.length}個）。Smiley配食事業用CSVは20個以上のフィールドが必要です`));
                            return;
                        }

                        // Smiley配食事業必須フィールドチェック
                        const requiredFields = ['法人名', '事業所名', '配達日', '社員名'];
                        const missingFields = requiredFields.filter(field => 
                            !fields.some(f => f.trim().includes(field))
                        );

                        if (missingFields.length > 0) {
                            reject(new Error(`必須フィールドが不足しています: ${missingFields.join(', ')}`));
                            return;
                        }

                        resolve({
                            headerFields: fields.map(f => f.trim()),
                            lineCount: lines.length,
                            dataRows: lines.length - 1,
                            encoding: 'UTF-8' // FileReader はUTF-8で読み込む
                        });

                    } catch (error) {
                        reject(new Error(`CSVファイル形式チェックエラー: ${error.message}`));
                    }
                };
                
                reader.onerror = function() {
                    reject(new Error('ファイル読み込みエラーが発生しました'));
                };
                
                // 先頭1KBだけ読み込んでチェック
                const blob = file.slice(0, 1024);
                reader.readAsText(blob, 'UTF-8');
            });
        }

        // 拡張ファイル処理（事前チェック付き）
        async function handleFileWithValidation(file) {
            try {
                // 基本チェック
                handleFile(file);

                // CSV形式事前チェック
                updateProgress(10, 'CSVファイル形式をチェック中...');
                
                const csvInfo = await preCheckCSVFormat(file);
                logDebugInfo('📋 CSV事前チェック完了', csvInfo);

                // ファイル詳細情報を更新
                const enhancedDetails = `
                    <div class="row">
                        <div class="col-md-6">
                            <strong>ファイル名:</strong> ${file.name}<br>
                            <strong>サイズ:</strong> ${formatFileSize(file.size)}<br>
                            <strong>データ行数:</strong> ${csvInfo.dataRows}行
                        </div>
                        <div class="col-md-6">
                            <strong>種類:</strong> CSV (検証済み)<br>
                            <strong>フィールド数:</strong> ${csvInfo.headerFields.length}個<br>
                            <strong>エンコーディング:</strong> ${csvInfo.encoding}
                        </div>
                    </div>
                    <div class="mt-2">
                        <strong>検出されたヘッダー:</strong><br>
                        <div class="text-muted small">
                            ${csvInfo.headerFields.slice(0, 5).join(', ')}${csvInfo.headerFields.length > 5 ? '...' : ''}
                        </div>
                    </div>
                `;
                
                fileDetails.innerHTML = enhancedDetails;
                updateProgress(0, ''); // プログレスバーをリセット

                showAlert('CSVファイル形式の確認が完了しました。インポートを開始できます。', 'success');

            } catch (error) {
                logDebugInfo('❌ ファイル検証エラー', error.message);
                showAlert(error.message, 'danger');
                resetForm();
            }
        }

        // 高度な結果表示機能
        function displayAdvancedResult(result) {
            displayResult(result);

            // 成功時の追加情報
            if (result.success && result.data) {
                // インポート成功アクション
                setTimeout(() => {
                    if (result.data.stats && result.data.stats.success_records > 0) {
                        showAlert(
                            `🎉 ${result.data.stats.success_records}件のデータが正常にインポートされました！配達先企業管理画面で確認できます。`,
                            'success'
                        );
                    }
                }, 2000);

                // 統計情報の詳細表示
                if (result.data.stats) {
                    const stats = result.data.stats;
                    const successRate = stats.total_records > 0 
                        ? Math.round((stats.success_records / stats.total_records) * 100)
                        : 0;
                    
                    // 成功率バッジを追加
                    const successBadge = document.createElement('div');
                    successBadge.className = 'text-center mt-3';
                    successBadge.innerHTML = `
                        <span class="badge bg-primary fs-6 px-3 py-2">
                            成功率: ${successRate}%
                        </span>
                    `;
                    document.getElementById('statsRow').appendChild(successBadge);
                }
            }
        }
    </script>
</body>
</html>
