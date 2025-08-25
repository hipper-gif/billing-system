<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CSVインポート - Smiley配食事業システム</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        :root {
            --smiley-green: #2E8B57;
            --smiley-light-green: #3CB371;
            --smiley-dark-green: #228B22;
        }
        
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .navbar {
            background: linear-gradient(135deg, var(--smiley-green), var(--smiley-light-green));
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .navbar-brand {
            font-weight: bold;
            font-size: 1.5rem;
        }
        
        .main-container {
            max-width: 1000px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        
        .upload-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .card-header {
            background: linear-gradient(135deg, var(--smiley-green), var(--smiley-light-green));
            color: white;
            padding: 1.5rem;
            text-align: center;
        }
        
        .drop-zone {
            border: 3px dashed #ddd;
            border-radius: 10px;
            padding: 3rem;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
            background: #fafafa;
            margin: 2rem;
        }
        
        .drop-zone.drag-over {
            border-color: var(--smiley-green);
            background: #f0f8f4;
        }
        
        .drop-zone:hover {
            border-color: var(--smiley-light-green);
            background: #f8fffe;
        }
        
        .upload-icon {
            font-size: 4rem;
            color: var(--smiley-green);
            margin-bottom: 1rem;
        }
        
        .progress-container {
            display: none;
            margin: 2rem;
        }
        
        .result-container {
            display: none;
            margin: 2rem;
        }
        
        .btn-smiley {
            background: linear-gradient(135deg, var(--smiley-green), var(--smiley-light-green));
            border: none;
            color: white;
            padding: 0.75rem 2rem;
            border-radius: 25px;
            font-weight: bold;
            transition: all 0.3s ease;
        }
        
        .btn-smiley:hover {
            background: linear-gradient(135deg, var(--smiley-dark-green), var(--smiley-green));
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .stats-card {
            background: white;
            border-radius: 10px;
            padding: 1rem;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .stats-number {
            font-size: 2rem;
            font-weight: bold;
            color: var(--smiley-green);
        }
        
        .alert-info {
            border-left: 4px solid var(--smiley-green);
            background: #f8fff8;
        }
        
        .error-list {
            max-height: 300px;
            overflow-y: auto;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <!-- ナビゲーション -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="../index.php">
                <i class="fas fa-utensils me-2"></i>
                Smiley配食事業システム
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="#">CSVインポート</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="companies.php">配達先企業</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="users.php">利用者管理</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- メインコンテンツ -->
    <div class="main-container">
        <!-- 説明カード -->
        <div class="alert alert-info mb-4">
            <h5><i class="fas fa-info-circle me-2"></i>CSVインポートについて</h5>
            <ul class="mb-0">
                <li>配食システムから出力されたCSVファイル（23フィールド）をアップロードしてください</li>
                <li>対応エンコーディング: SJIS-win, UTF-8</li>
                <li>最大ファイルサイズ: 50MB</li>
                <li>重複データは自動的にスキップされます</li>
            </ul>
        </div>

        <!-- アップロードカード -->
        <div class="upload-card">
            <div class="card-header">
                <h3><i class="fas fa-file-csv me-2"></i>CSVファイル インポート</h3>
                <p class="mb-0">給食システムのCSVファイルをここにドラッグ&ドロップするか、クリックして選択してください</p>
            </div>
            
            <!-- ファイルアップロード -->
            <form id="csvUploadForm" enctype="multipart/form-data">
                <div class="drop-zone" onclick="document.getElementById('csvFile').click()">
                    <i class="fas fa-cloud-upload-alt upload-icon"></i>
                    <h4>CSVファイルをアップロード</h4>
                    <p class="text-muted">クリックしてファイルを選択するか、ここにドラッグ&ドロップしてください</p>
                    <input type="file" id="csvFile" name="csv_file" accept=".csv,.txt" style="display: none;" required>
                    <div class="mt-3">
                        <button type="submit" class="btn btn-smiley" disabled>
                            <i class="fas fa-upload me-2"></i>インポート開始
                        </button>
                    </div>
                </div>
            </form>
            
            <!-- プログレスバー -->
            <div class="progress-container">
                <h5><i class="fas fa-cog fa-spin me-2"></i>処理中...</h5>
                <div class="progress mb-3">
                    <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" 
                         role="progressbar" style="width: 0%"></div>
                </div>
                <div class="text-center">
                    <small class="text-muted">CSVファイルを解析・インポートしています...</small>
                </div>
            </div>
            
            <!-- 結果表示 -->
            <div class="result-container">
                <div id="importResults"></div>
            </div>
        </div>

        <!-- システム状態確認 -->
        <div class="mt-4 text-center">
            <button class="btn btn-outline-secondary" onclick="checkSystemStatus()">
                <i class="fas fa-heartbeat me-2"></i>システム状態確認
            </button>
        </div>
        
        <div id="systemStatus" class="mt-3"></div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // グローバル変数
        let selectedFile = null;
        
        // DOM読み込み完了時の処理
        document.addEventListener('DOMContentLoaded', function() {
            initializeUpload();
        });
        
        // アップロード機能初期化
        function initializeUpload() {
            const dropZone = document.querySelector('.drop-zone');
            const fileInput = document.getElementById('csvFile');
            const form = document.getElementById('csvUploadForm');
            const submitBtn = form.querySelector('button[type="submit"]');
            
            // ドラッグ&ドロップ処理
            dropZone.addEventListener('dragover', (e) => {
                e.preventDefault();
                dropZone.classList.add('drag-over');
            });
            
            dropZone.addEventListener('dragleave', (e) => {
                e.preventDefault();
                dropZone.classList.remove('drag-over');
            });
            
            dropZone.addEventListener('drop', (e) => {
                e.preventDefault();
                dropZone.classList.remove('drag-over');
                
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    handleFileSelection(files[0]);
                }
            });
            
            // ファイル選択処理
            fileInput.addEventListener('change', (e) => {
                if (e.target.files.length > 0) {
                    handleFileSelection(e.target.files[0]);
                }
            });
            
            // フォーム送信処理
            form.addEventListener('submit', (e) => {
                e.preventDefault();
                if (selectedFile) {
                    uploadFile(selectedFile);
                }
            });
        }
        
        // ファイル選択処理
        function handleFileSelection(file) {
            selectedFile = file;
            const submitBtn = document.querySelector('button[type="submit"]');
            const dropZone = document.querySelector('.drop-zone');
            
            // ファイル検証
            if (!validateFile(file)) {
                return;
            }
            
            // UI更新
            dropZone.innerHTML = `
                <i class="fas fa-file-csv upload-icon" style="color: var(--smiley-green);"></i>
                <h5>選択されたファイル</h5>
                <p class="text-success"><strong>${file.name}</strong></p>
                <p class="text-muted">サイズ: ${formatFileSize(file.size)}</p>
                <div class="mt-3">
                    <button type="submit" class="btn btn-smiley">
                        <i class="fas fa-upload me-2"></i>インポート開始
                    </button>
                    <button type="button" class="btn btn-outline-secondary ms-2" onclick="resetUpload()">
                        <i class="fas fa-times me-2"></i>キャンセル
                    </button>
                </div>
            `;
            
            submitBtn.disabled = false;
        }
        
        // ファイル検証
        function validateFile(file) {
            const maxSize = 50 * 1024 * 1024; // 50MB
            const allowedTypes = ['text/csv', 'text/plain', 'application/csv'];
            const allowedExtensions = ['csv', 'txt'];
            
            // 拡張子チェック
            const extension = file.name.split('.').pop().toLowerCase();
            if (!allowedExtensions.includes(extension)) {
                showAlert('danger', 'ファイル形式エラー', `許可されていない拡張子です: .${extension}`);
                return false;
            }
            
            // サイズチェック
            if (file.size > maxSize) {
                showAlert('danger', 'ファイルサイズエラー', `ファイルサイズが大きすぎます: ${formatFileSize(file.size)} (上限: 50MB)`);
                return false;
            }
            
            // 空ファイルチェック
            if (file.size === 0) {
                showAlert('danger', 'ファイルエラー', 'ファイルが空です');
                return false;
            }
            
            return true;
        }
        
        // ファイルアップロード実行
        function uploadFile(file) {
            const formData = new FormData();
            formData.append('csv_file', file);
            formData.append('encoding', 'auto');
            formData.append('overwrite', '0');
            
            // UI状態変更
            showProgress();
            
            // アップロード実行
            fetch('../api/import.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                hideProgress();
                displayResults(data);
            })
            .catch(error => {
                hideProgress();
                console.error('Upload error:', error);
                showAlert('danger', 'アップロードエラー', 'ファイルのアップロード中にエラーが発生しました: ' + error.message);
            });
        }
        
        // プログレス表示
        function showProgress() {
            document.querySelector('.drop-zone').style.display = 'none';
            document.querySelector('.progress-container').style.display = 'block';
            
            // プログレスバーアニメーション
            let progress = 0;
            const progressBar = document.querySelector('.progress-bar');
            const interval = setInterval(() => {
                progress += Math.random() * 15;
                if (progress > 90) progress = 90;
                progressBar.style.width = progress + '%';
                if (progress >= 90) clearInterval(interval);
            }, 200);
        }
        
        // プログレス非表示
        function hideProgress() {
            document.querySelector('.progress-container').style.display = 'none';
            document.querySelector('.progress-bar').style.width = '100%';
        }
        
        // 結果表示
        function displayResults(data) {
            const resultsContainer = document.querySelector('.result-container');
            const resultsDiv = document.getElementById('importResults');
            
            let html = '';
            
            if (data.success) {
                html = `
                    <div class="alert alert-success">
                        <h5><i class="fas fa-check-circle me-2"></i>インポート完了</h5>
                        <p>${data.message}</p>
                    </div>
                    
                    <div class="row g-3 mb-4">
                        <div class="col-md-2">
                            <div class="stats-card">
                                <div class="stats-number">${data.data.stats.total_records}</div>
                                <div class="text-muted">総件数</div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="stats-card">
                                <div class="stats-number text-success">${data.data.stats.success_records}</div>
                                <div class="text-muted">成功</div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="stats-card">
                                <div class="stats-number text-danger">${data.data.stats.error_records}</div>
                                <div class="text-muted">エラー</div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="stats-card">
                                <div class="stats-number text-warning">${data.data.stats.duplicate_records}</div>
                                <div class="text-muted">重複</div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="stats-card">
                                <div class="stats-number text-info">${data.data.stats.new_companies}</div>
                                <div class="text-muted">新規企業</div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="stats-card">
                                <div class="stats-number text-info">${data.data.stats.new_users}</div>
                                <div class="text-muted">新規利用者</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <strong>バッチID:</strong> ${data.data.batch_id}<br>
                        <strong>処理時間:</strong> ${data.data.stats.processing_time}
                    </div>
                `;
            } else {
                html = `
                    <div class="alert alert-danger">
                        <h5><i class="fas fa-exclamation-triangle me-2"></i>インポートエラー</h5>
                        <p>${data.message}</p>
                    </div>
                `;
            }
            
            // エラー詳細表示
            if (data.data && data.data.errors && data.data.errors.length > 0) {
                html += `
                    <div class="alert alert-warning">
                        <h6><i class="fas fa-list me-2"></i>エラー詳細 (${data.data.errors.length}件)</h6>
                        <div class="error-list">
                `;
                
                data.data.errors.forEach((error, index) => {
                    html += `<div class="small mb-1"><strong>${index + 1}.</strong> ${error.context}: ${error.message}</div>`;
                });
                
                html += `</div></div>`;
            }
            
            html += `
                <div class="text-center mt-4">
                    <button class="btn btn-outline-primary me-2" onclick="resetUpload()">
                        <i class="fas fa-plus me-2"></i>新しいファイルをインポート
                    </button>
                    <button class="btn btn-outline-info" onclick="location.href='users.php'">
                        <i class="fas fa-users me-2"></i>利用者管理画面へ
                    </button>
                </div>
            `;
            
            resultsDiv.innerHTML = html;
            resultsContainer.style.display = 'block';
        }
        
        // アップロードリセット
        function resetUpload() {
            selectedFile = null;
            document.getElementById('csvFile').value = '';
            document.querySelector('.result-container').style.display = 'none';
            
            // ドロップゾーンを元に戻す
            const dropZone = document.querySelector('.drop-zone');
            dropZone.style.display = 'block';
            dropZone.innerHTML = `
                <i class="fas fa-cloud-upload-alt upload-icon"></i>
                <h4>CSVファイルをアップロード</h4>
                <p class="text-muted">クリックしてファイルを選択するか、ここにドラッグ&ドロップしてください</p>
                <input type="file" id="csvFile" name="csv_file" accept=".csv,.txt" style="display: none;" required>
                <div class="mt-3">
                    <button type="submit" class="btn btn-smiley" disabled>
                        <i class="fas fa-upload me-2"></i>インポート開始
                    </button>
                </div>
            `;
            
            // イベントリスナー再設定
            initializeUpload();
        }
        
        // システム状態確認
        function checkSystemStatus() {
            const statusDiv = document.getElementById('systemStatus');
            
            statusDiv.innerHTML = `
                <div class="text-center">
                    <i class="fas fa-spinner fa-spin"></i> システム状態を確認中...
                </div>
            `;
            
            fetch('../api/import.php?action=status')
            .then(response => response.json())
            .then(data => {
                let html = '';
                
                if (data.success) {
                    html = `
                        <div class="alert alert-success">
                            <h6><i class="fas fa-check-circle me-2"></i>システム状態: 正常</h6>
                            <div class="row g-2 mt-2">
                                <div class="col-md-6">
                                    <small><strong>データベース接続:</strong> ${data.data.database_connection ? '✓ 正常' : '✗ エラー'}</small><br>
                                    <small><strong>データベースパターン:</strong> ${data.data.database_pattern}</small><br>
                                    <small><strong>PDO接続:</strong> ${data.data.pdo_connection ? '✓ 正常' : '✗ エラー'}</small>
                                </div>
                                <div class="col-md-6">
                                    <small><strong>必要クラス:</strong></small><br>
                                    <small>Database: ${data.data.required_classes.Database ? '✓' : '✗'}</small><br>
                                    <small>SmileyCSVImporter: ${data.data.required_classes.SmileyCSVImporter ? '✓' : '✗'}</small><br>
                                    <small>SecurityHelper: ${data.data.required_classes.SecurityHelper ? '✓' : '✗'}</small>
                                </div>
                            </div>
                        </div>
                    `;
                    
                    // テーブル状態表示
                    if (data.data.tables) {
                        html += `
                            <div class="alert alert-info">
                                <h6><i class="fas fa-database me-2"></i>データベーステーブル状態</h6>
                                <div class="row g-1">
                        `;
                        
                        Object.entries(data.data.tables).forEach(([table, exists]) => {
                            html += `<div class="col-md-3"><small>${table}: ${exists ? '✓' : '✗'}</small></div>`;
                        });
                        
                        html += `</div></div>`;
                    }
                } else {
                    html = `
                        <div class="alert alert-danger">
                            <h6><i class="fas fa-exclamation-triangle me-2"></i>システム状態: エラー</h6>
                            <p>${data.message}</p>
                        </div>
                    `;
                }
                
                statusDiv.innerHTML = html;
            })
            .catch(error => {
                statusDiv.innerHTML = `
                    <div class="alert alert-danger">
                        <h6><i class="fas fa-exclamation-triangle me-2"></i>システム状態確認エラー</h6>
                        <p>システム状態の確認中にエラーが発生しました: ${error.message}</p>
                    </div>
                `;
            });
        }
        
        // ユーティリティ関数
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
        
        function showAlert(type, title, message) {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
            alertDiv.innerHTML = `
                <h6><i class="fas fa-exclamation-triangle me-2"></i>${title}</h6>
                <p>${message}</p>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            document.querySelector('.main-container').insertBefore(alertDiv, document.querySelector('.upload-card'));
            
            // 5秒後に自動削除
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.remove();
                }
            }, 5000);
        }
        
        // ページ読み込み時にシステム状態を確認
        window.addEventListener('load', () => {
            // 3秒後に自動でシステム状態確認
            setTimeout(() => {
                checkSystemStatus();
            }, 3000);
        });
    </script>
</body>
</html>
