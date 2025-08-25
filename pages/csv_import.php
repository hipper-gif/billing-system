<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CSVインポート - Smiley配食事業管理システム</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    
    <style>
        :root {
            --smiley-primary: #2E8B57;
            --smiley-secondary: #90EE90;
            --smiley-light: #F0FFF0;
            --smiley-dark: #1F5F3F;
        }
        
        .navbar-brand {
            color: var(--smiley-primary) !important;
            font-weight: bold;
        }
        
        .btn-smiley {
            background-color: var(--smiley-primary);
            border-color: var(--smiley-primary);
            color: white;
        }
        
        .btn-smiley:hover {
            background-color: var(--smiley-dark);
            border-color: var(--smiley-dark);
            color: white;
        }
        
        .card-header {
            background-color: var(--smiley-light);
            border-bottom: 2px solid var(--smiley-primary);
        }
        
        .upload-area {
            border: 3px dashed #dee2e6;
            background-color: #f8f9fa;
            padding: 3rem;
            text-align: center;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            min-height: 200px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }
        
        .upload-area:hover {
            border-color: var(--smiley-primary);
            background-color: var(--smiley-light);
        }
        
        .upload-area.dragover {
            border-color: var(--smiley-primary);
            background-color: var(--smiley-light);
            transform: scale(1.02);
        }
        
        .upload-icon {
            font-size: 3rem;
            color: var(--smiley-primary);
            margin-bottom: 1rem;
        }
        
        .progress {
            height: 25px;
        }
        
        .stats-card {
            border-left: 4px solid var(--smiley-primary);
        }
        
        .error-row:hover {
            background-color: #fff3cd;
        }
        
        .success-badge {
            background-color: var(--smiley-primary);
        }
        
        .file-info {
            background-color: var(--smiley-light);
            border: 1px solid var(--smiley-secondary);
            border-radius: 8px;
            padding: 1rem;
            margin: 1rem 0;
        }
        
        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2rem;
        }
        
        .step {
            flex: 1;
            text-align: center;
            position: relative;
        }
        
        .step::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 40px;
            height: 40px;
            background-color: #dee2e6;
            border-radius: 50%;
            transform: translate(-50%, -50%);
            z-index: 1;
        }
        
        .step.active::before {
            background-color: var(--smiley-primary);
        }
        
        .step.completed::before {
            background-color: var(--smiley-primary);
        }
        
        .step-number {
            position: relative;
            z-index: 2;
            color: white;
            font-weight: bold;
            line-height: 40px;
        }
        
        .step-title {
            margin-top: 50px;
            font-size: 0.9rem;
        }
        
        @media (max-width: 768px) {
            .upload-area {
                padding: 2rem;
                min-height: 150px;
            }
            
            .upload-icon {
                font-size: 2rem;
            }
        }
    </style>
</head>

<body class="bg-light">
    <!-- ナビゲーションバー -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom">
        <div class="container">
            <a class="navbar-brand" href="../index.php">
                <i class="bi bi-emoji-smile"></i> Smiley配食事業管理システム
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="../pages/companies.php">企業管理</a>
                <a class="nav-link" href="../pages/departments.php">部署管理</a>
                <a class="nav-link" href="../pages/users.php">利用者管理</a>
                <a class="nav-link active" href="../pages/csv_import.php">CSVインポート</a>
            </div>
        </div>
    </nav>

    <div class="container my-5">
        <!-- ページヘッダー -->
        <div class="row mb-4">
            <div class="col">
                <h1 class="h2 text-dark">
                    <i class="bi bi-file-earmark-arrow-up"></i> CSVインポート
                </h1>
                <p class="text-muted">Smiley配食事業の注文データをCSVファイルからインポートします</p>
            </div>
        </div>

        <!-- ステップインジケーター -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="step-indicator">
                    <div class="step active" id="step1">
                        <div class="step-number">1</div>
                        <div class="step-title">ファイル選択</div>
                    </div>
                    <div class="step" id="step2">
                        <div class="step-number">2</div>
                        <div class="step-title">アップロード</div>
                    </div>
                    <div class="step" id="step3">
                        <div class="step-number">3</div>
                        <div class="step-title">処理中</div>
                    </div>
                    <div class="step" id="step4">
                        <div class="step-number">4</div>
                        <div class="step-title">完了</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- メインコンテンツ -->
        <div class="row">
            <div class="col-lg-8">
                <!-- ファイルアップロードエリア -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-cloud-upload"></i> CSVファイルアップロード
                        </h5>
                    </div>
                    <div class="card-body">
                        <form id="uploadForm" enctype="multipart/form-data">
                            <div class="upload-area" id="uploadArea">
                                <i class="bi bi-file-earmark-plus upload-icon"></i>
                                <h4>ファイルをドラッグ&ドロップ</h4>
                                <p class="text-muted mb-3">または、クリックしてファイルを選択してください</p>
                                <input type="file" id="csvFile" name="csv_file" accept=".csv,.txt" style="display: none;">
                                <button type="button" class="btn btn-smiley" onclick="document.getElementById('csvFile').click()">
                                    <i class="bi bi-folder2-open"></i> ファイルを選択
                                </button>
                                <div class="mt-3">
                                    <small class="text-muted">
                                        対応形式: CSV, TXT | 最大サイズ: 10MB | エンコーディング: SJIS-win, UTF-8
                                    </small>
                                </div>
                            </div>

                            <!-- ファイル情報表示 -->
                            <div id="fileInfo" class="file-info" style="display: none;">
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <h6 class="mb-1" id="fileName"></h6>
                                        <div class="text-muted">
                                            <i class="bi bi-file-earmark-text"></i>
                                            <span id="fileSize"></span> | 
                                            <span id="fileType"></span>
                                        </div>
                                    </div>
                                    <div class="col-md-4 text-end">
                                        <button type="button" class="btn btn-outline-danger btn-sm" id="removeFile">
                                            <i class="bi bi-trash"></i> 削除
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- オプション設定 -->
                            <div class="row mt-3">
                                <div class="col-md-6">
                                    <label for="encoding" class="form-label">エンコーディング</label>
                                    <select class="form-select" id="encoding" name="encoding">
                                        <option value="auto">自動判定</option>
                                        <option value="SJIS-win">Shift-JIS (Windows)</option>
                                        <option value="UTF-8">UTF-8</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check mt-4">
                                        <input class="form-check-input" type="checkbox" id="overwrite" name="overwrite">
                                        <label class="form-check-label" for="overwrite">
                                            既存データを上書き
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-4">
                                <button type="submit" class="btn btn-smiley btn-lg" id="importBtn" disabled>
                                    <i class="bi bi-upload"></i> インポート開始
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-lg ms-2" id="cancelBtn" style="display: none;">
                                    <i class="bi bi-x-circle"></i> キャンセル
                                </button>
                            </div>
                        </form>

                        <!-- 進捗バー -->
                        <div id="progressSection" style="display: none;" class="mt-4">
                            <div class="d-flex justify-content-between mb-2">
                                <span>処理中...</span>
                                <span id="progressPercent">0%</span>
                            </div>
                            <div class="progress">
                                <div id="progressBar" class="progress-bar bg-success progress-bar-striped progress-bar-animated" 
                                     role="progressbar" style="width: 0%"></div>
                            </div>
                            <div class="mt-2">
                                <small id="progressMessage" class="text-muted">ファイルを処理しています...</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <!-- 使用方法ガイド -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="card-title mb-0">
                            <i class="bi bi-info-circle"></i> 使用方法
                        </h6>
                    </div>
                    <div class="card-body">
                        <ol class="list-unstyled">
                            <li class="mb-2">
                                <span class="badge bg-primary rounded-pill me-2">1</span>
                                CSVファイルを準備
                            </li>
                            <li class="mb-2">
                                <span class="badge bg-primary rounded-pill me-2">2</span>
                                ドラッグ&ドロップまたは選択
                            </li>
                            <li class="mb-2">
                                <span class="badge bg-primary rounded-pill me-2">3</span>
                                設定を確認
                            </li>
                            <li class="mb-2">
                                <span class="badge bg-primary rounded-pill me-2">4</span>
                                インポート開始
                            </li>
                        </ol>
                        
                        <hr>
                        
                        <h6>CSVフォーマット要件</h6>
                        <ul class="small text-muted">
                            <li>23フィールド形式</li>
                            <li>ヘッダー行必須</li>
                            <li>Smiley配食事業仕様準拠</li>
                            <li>最大10,000レコード</li>
                        </ul>
                    </div>
                </div>

                <!-- システム状態 -->
                <div class="card">
                    <div class="card-header">
                        <h6 class="card-title mb-0">
                            <i class="bi bi-gear"></i> システム状態
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span>データベース:</span>
                            <span id="dbStatus" class="badge bg-secondary">確認中</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>API接続:</span>
                            <span id="apiStatus" class="badge bg-secondary">確認中</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>最終更新:</span>
                            <span id="lastUpdate" class="small text-muted">-</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 結果表示エリア -->
        <div id="resultSection" style="display: none;" class="mt-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-check-circle"></i> インポート結果
                    </h5>
                </div>
                <div class="card-body">
                    <!-- 統計サマリー -->
                    <div class="row mb-4" id="statsRow">
                        <div class="col-md-3">
                            <div class="card stats-card">
                                <div class="card-body text-center">
                                    <h3 id="totalRecords" class="text-primary mb-1">-</h3>
                                    <div class="text-muted small">総レコード数</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stats-card">
                                <div class="card-body text-center">
                                    <h3 id="successRecords" class="text-success mb-1">-</h3>
                                    <div class="text-muted small">成功</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stats-card">
                                <div class="card-body text-center">
                                    <h3 id="errorRecords" class="text-danger mb-1">-</h3>
                                    <div class="text-muted small">エラー</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stats-card">
                                <div class="card-body text-center">
                                    <h3 id="duplicateRecords" class="text-warning mb-1">-</h3>
                                    <div class="text-muted small">重複</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 処理詳細 -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="d-flex justify-content-between">
                                <span>バッチID:</span>
                                <span id="batchId" class="font-monospace small">-</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex justify-content-between">
                                <span>処理時間:</span>
                                <span id="processingTime">-</span>
                            </div>
                        </div>
                    </div>

                    <!-- エラー詳細 -->
                    <div id="errorSection" style="display: none;">
                        <h6 class="mb-3">
                            <i class="bi bi-exclamation-triangle"></i> エラー詳細
                        </h6>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>行番号</th>
                                        <th>エラー内容</th>
                                    </tr>
                                </thead>
                                <tbody id="errorTable">
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- アクションボタン -->
                    <div class="mt-4">
                        <button type="button" class="btn btn-smiley" id="newImportBtn">
                            <i class="bi bi-arrow-clockwise"></i> 新しいインポート
                        </button>
                        <button type="button" class="btn btn-outline-primary" id="viewDataBtn">
                            <i class="bi bi-table"></i> データを確認
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // グローバル変数
        let selectedFile = null;
        let isUploading = false;

        // DOM読み込み完了後の初期化
        document.addEventListener('DOMContentLoaded', function() {
            initializeUploadArea();
            initializeFormHandlers();
            checkSystemStatus();
        });

        /**
         * アップロードエリアの初期化
         */
        function initializeUploadArea() {
            const uploadArea = document.getElementById('uploadArea');
            const fileInput = document.getElementById('csvFile');

            // ドラッグ&ドロップイベント
            uploadArea.addEventListener('dragover', function(e) {
                e.preventDefault();
                uploadArea.classList.add('dragover');
            });

            uploadArea.addEventListener('dragleave', function(e) {
                e.preventDefault();
                uploadArea.classList.remove('dragover');
            });

            uploadArea.addEventListener('drop', function(e) {
                e.preventDefault();
                uploadArea.classList.remove('dragover');
                
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    handleFileSelection(files[0]);
                }
            });

            // クリックでファイル選択
            uploadArea.addEventListener('click', function() {
                fileInput.click();
            });

            // ファイル選択変更
            fileInput.addEventListener('change', function(e) {
                if (e.target.files.length > 0) {
                    handleFileSelection(e.target.files[0]);
                }
            });
        }

        /**
         * フォームハンドラーの初期化
         */
        function initializeFormHandlers() {
            const uploadForm = document.getElementById('uploadForm');
            const removeFileBtn = document.getElementById('removeFile');
            const newImportBtn = document.getElementById('newImportBtn');
            const viewDataBtn = document.getElementById('viewDataBtn');

            // フォーム送信
            uploadForm.addEventListener('submit', function(e) {
                e.preventDefault();
                startImport();
            });

            // ファイル削除
            removeFileBtn.addEventListener('click', function() {
                removeSelectedFile();
            });

            // 新しいインポート
            newImportBtn.addEventListener('click', function() {
                resetForm();
            });

            // データ確認
            viewDataBtn.addEventListener('click', function() {
                window.open('../pages/companies.php', '_blank');
            });
        }

        /**
         * ファイル選択処理
         */
        function handleFileSelection(file) {
            // ファイル形式チェック
            const allowedTypes = ['text/csv', 'text/plain', 'application/vnd.ms-excel'];
            const allowedExtensions = ['.csv', '.txt'];
            const fileExtension = '.' + file.name.split('.').pop().toLowerCase();

            if (!allowedExtensions.includes(fileExtension)) {
                showAlert('danger', 'CSVまたはTXTファイルを選択してください');
                return;
            }

            // ファイルサイズチェック（10MB）
            if (file.size > 10 * 1024 * 1024) {
                showAlert('danger', 'ファイルサイズは10MB以下にしてください');
                return;
            }

            selectedFile = file;
            displayFileInfo(file);
            updateStepIndicator(2);
            
            document.getElementById('importBtn').disabled = false;
        }

        /**
         * ファイル情報表示
         */
        function displayFileInfo(file) {
            const fileInfo = document.getElementById('fileInfo');
            const fileName = document.getElementById('fileName');
            const fileSize = document.getElementById('fileSize');
            const fileType = document.getElementById('fileType');

            fileName.textContent = file.name;
            fileSize.textContent = formatFileSize(file.size);
            fileType.textContent = file.type || 'text/csv';

            fileInfo.style.display = 'block';
        }

        /**
         * 選択ファイル削除
         */
        function removeSelectedFile() {
            selectedFile = null;
            document.getElementById('fileInfo').style.display = 'none';
            document.getElementById('csvFile').value = '';
            document.getElementById('importBtn').disabled = true;
            updateStepIndicator(1);
        }

        /**
         * インポート開始
         */
        async function startImport() {
            if (!selectedFile || isUploading) {
                return;
            }

            isUploading = true;
            updateStepIndicator(3);
            showProgressSection();

            const formData = new FormData();
            formData.append('csv_file', selectedFile);
            formData.append('encoding', document.getElementById('encoding').value);
            
            if (document.getElementById('overwrite').checked) {
                formData.append('overwrite', '1');
            }

            try {
                updateProgress(10, 'ファイルをアップロード中...');

                const response = await fetch('../api/import.php', {
                    method: 'POST',
                    body: formData
                });

                updateProgress(50, 'サーバーで処理中...');

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }

                const result = await response.json();
                
                updateProgress(100, '処理完了');

                setTimeout(() => {
                    hideProgressSection();
                    displayResults(result);
                    updateStepIndicator(4);
                }, 1000);

            } catch (error) {
                console.error('Import error:', error);
                hideProgressSection();
                showAlert('danger', `インポートエラー: ${error.message}`);
                updateStepIndicator(2);
            } finally {
                isUploading = false;
            }
        }

        /**
         * 結果表示
         */
        function displayResults(result) {
            if (result.success) {
                showAlert('success', result.message);
                displaySuccessResults(result);
            } else {
                showAlert('danger', result.message);
                if (result.data && result.data.stats) {
                    displaySuccessResults(result);
                }
            }
        }

        /**
         * 成功結果表示
         */
        function displaySuccessResults(result) {
            const data = result.data || {};
            const stats = data.stats || {};

            // 統計表示
            document.getElementById('totalRecords').textContent = stats.total_records || 0;
            document.getElementById('successRecords').textContent = stats.success_records || 0;
            document.getElementById('errorRecords').textContent = stats.error_records || 0;
            document.getElementById('duplicateRecords').textContent = stats.duplicate_records || 0;

            // 詳細情報
            document.getElementById('batchId').textContent = data.batch_id || '-';
            document.getElementById('processingTime').textContent = (stats.processing_time || '-');

            // エラー詳細
            if (result.errors && result.errors.length > 0) {
                displayErrors(result.errors);
            }

            document.getElementById('resultSection').style.display = 'block';
        }

        /**
         * エラー詳細表示
         */
        function displayErrors(errors) {
            const errorSection = document.getElementById('errorSection');
            const errorTable = document.getElementById('errorTable');

            errorTable.innerHTML = '';

            errors.forEach(error => {
                const row = document.createElement('tr');
                row.className = 'error-row';
                row.innerHTML = `
                    <td>${error.line || '-'}</td>
                    <td>${error.message || 'エラー詳細なし'}</td>
                `;
                errorTable.appendChild(row);
            });

            errorSection.style.display = 'block';
        }

        /**
         * 進捗セクション表示
         */
        function showProgressSection() {
            document.getElementById('progressSection').style.display = 'block';
            document.getElementById('importBtn').disabled = true;
            document.getElementById('cancelBtn').style.display = 'inline-block';
        }

        /**
         * 進捗セクション非表示
         */
        function hideProgressSection() {
            document.getElementById('progressSection').style.display = 'none';
            document.getElementById('importBtn').disabled = false;
            document.getElementById('cancelBtn').style.display = 'none';
        }

        /**
         * 進捗更新
         */
        function updateProgress(percent, message) {
            document.getElementById('progressBar').style.width = percent + '%';
            document.getElementById('progressPercent').textContent = percent + '%';
            document.getElementById('progressMessage').textContent = message;
        }

        /**
         * ステップインジケーター更新
         */
        function updateStepIndicator(activeStep) {
            for (let i = 1; i <= 4; i++) {
                const step = document.getElementById(`step${i}`);
                step.classList.remove('active', 'completed');
                
                if (i < activeStep) {
                    step.classList.add('completed');
                } else if (i === activeStep) {
                    step.classList.add('active');
                }
            }
        }

        /**
         * システム状態チェック
         */
        async function checkSystemStatus() {
            try {
                const response = await fetch('../api/import.php?action=status');
                const result = await response.json();

                if (result.success) {
                    document.getElementById('dbStatus').textContent = '正常';
                    document.getElementById('dbStatus').className = 'badge bg-success';
                    document.getElementById('apiStatus').textContent = '正常';
                    document.getElementById('apiStatus').className = 'badge bg-success';
                } else {
                    document.getElementById('dbStatus').textContent = 'エラー';
                    document.getElementById('dbStatus').className = 'badge bg-danger';
                    document.getElementById('apiStatus').textContent = 'エラー';
                    document.getElementById('apiStatus').className = 'badge bg-danger';
                }

                document.getElementById('lastUpdate').textContent = new Date().toLocaleString('ja-JP');

            } catch (error) {
                document.getElementById('dbStatus').textContent = '不明';
                document.getElementById('dbStatus').className = 'badge bg-warning';
                document.getElementById('apiStatus').textContent = '不明';
                document.getElementById('apiStatus').className = 'badge bg-warning';
            }
        }

        /**
         * フォームリセット
         */
        function resetForm() {
            removeSelectedFile();
            document.getElementById('encoding').value = 'auto';
            document.getElementById('overwrite').checked = false;
            document.getElementById('resultSection').style.display = 'none';
            hideProgressSection();
            updateStepIndicator(1);
            clearAlerts();
        }

        /**
         * アラート表示
         */
        function showAlert(type, message) {
            clearAlerts();
            
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            document.querySelector('.container').insertBefore(alertDiv, document.querySelector('.row'));
            
            // 自動削除
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.remove();
                }
            }, 5000);
        }

        /**
         * アラート削除
         */
        function clearAlerts() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => alert.remove());
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

        /**
         * 定期的なシステム状態更新
         */
        setInterval(checkSystemStatus, 60000); // 1分ごと
    </script>
</body>
</html>
