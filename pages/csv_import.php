<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CSV インポート - Smiley 配食事業システム</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --smiley-green: #2E8B57;
            --smiley-light-green: #90EE90;
            --smiley-dark-green: #1F5F3F;
        }
        
        .navbar {
            background-color: var(--smiley-green) !important;
        }
        
        .btn-primary {
            background-color: var(--smiley-green);
            border-color: var(--smiley-green);
        }
        
        .btn-primary:hover {
            background-color: var(--smiley-dark-green);
            border-color: var(--smiley-dark-green);
        }
        
        .upload-area {
            border: 2px dashed #ccc;
            border-radius: 10px;
            padding: 40px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            min-height: 200px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }
        
        .upload-area:hover {
            border-color: var(--smiley-green);
            background-color: #f8f9fa;
        }
        
        .upload-area.dragover {
            border-color: var(--smiley-green);
            background-color: var(--smiley-light-green);
        }
        
        .upload-icon {
            font-size: 3rem;
            color: #6c757d;
            margin-bottom: 1rem;
        }
        
        .progress {
            margin-top: 1rem;
            display: none;
        }
        
        .results-section {
            margin-top: 2rem;
            display: none;
        }
        
        .system-status {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border: 1px solid #dee2e6;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .error-details {
            background-color: #fff5f5;
            border: 1px solid #fed7d7;
            border-radius: 5px;
            padding: 1rem;
            margin-top: 1rem;
            max-height: 300px;
            overflow-y: auto;
        }
        
        .status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 0.5rem;
        }
        
        .status-success { background-color: #28a745; }
        .status-warning { background-color: #ffc107; }
        .status-error { background-color: #dc3545; }
        .status-info { background-color: #17a2b8; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="../index.php">
                <i class="bi bi-house-heart-fill"></i>
                Smiley 配食事業システム
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="../index.php">
                    <i class="bi bi-arrow-left"></i> メインメニューに戻る
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="card shadow">
                    <div class="card-header bg-white">
                        <h2 class="card-title mb-0">
                            <i class="bi bi-file-earmark-spreadsheet text-success"></i>
                            CSV ファイル インポート
                        </h2>
                        <p class="text-muted mb-0">Smiley 配食事業の注文データを一括取り込みします</p>
                    </div>
                    <div class="card-body">
                        <!-- システム状態確認 -->
                        <div class="system-status">
                            <h5><i class="bi bi-shield-check"></i> システム状態確認</h5>
                            <div id="system-status-content">
                                <button type="button" class="btn btn-outline-info btn-sm" onclick="checkSystemStatus()">
                                    <i class="bi bi-arrow-clockwise"></i> システム状態を確認
                                </button>
                            </div>
                        </div>

                        <!-- アップロードエリア -->
                        <div class="upload-area" id="uploadArea">
                            <div class="upload-icon">
                                <i class="bi bi-cloud-upload"></i>
                            </div>
                            <h4>CSV ファイルをドラッグ&ドロップ</h4>
                            <p class="text-muted">またはクリックしてファイルを選択</p>
                            <input type="file" id="csvFile" accept=".csv" style="display: none;">
                            <div class="mt-3">
                                <small class="text-muted">
                                    <i class="bi bi-info-circle"></i>
                                    対応ファイル: .csv（最大10MB）<br>
                                    文字エンコード: SJIS-win, UTF-8 自動判別
                                </small>
                            </div>
                        </div>

                        <!-- プログレスバー -->
                        <div class="progress" id="progressBar">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" 
                                 role="progressbar" style="width: 0%"></div>
                        </div>

                        <!-- 結果表示エリア -->
                        <div class="results-section" id="resultsSection">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="card border-success">
                                        <div class="card-header bg-success text-white">
                                            <h6 class="mb-0"><i class="bi bi-check-circle"></i> 処理結果</h6>
                                        </div>
                                        <div class="card-body" id="successResults">
                                            <!-- 処理結果がここに表示されます -->
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card border-warning">
                                        <div class="card-header bg-warning text-dark">
                                            <h6 class="mb-0"><i class="bi bi-exclamation-triangle"></i> エラー詳細</h6>
                                        </div>
                                        <div class="card-body" id="errorResults">
                                            <!-- エラー詳細がここに表示されます -->
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // グローバル変数
        let selectedFile = null;
        
        // DOM 要素取得
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('csvFile');
        const progressBar = document.getElementById('progressBar');
        const resultsSection = document.getElementById('resultsSection');
        const successResults = document.getElementById('successResults');
        const errorResults = document.getElementById('errorResults');

        // ページ読み込み時の初期化
        document.addEventListener('DOMContentLoaded', function() {
            console.log('CSV インポートページが初期化されました');
            setupEventListeners();
        });

        // イベントリスナー設定
        function setupEventListeners() {
            // ファイル選択
            uploadArea.addEventListener('click', () => fileInput.click());
            fileInput.addEventListener('change', handleFileSelect);

            // ドラッグ&ドロップ
            uploadArea.addEventListener('dragover', handleDragOver);
            uploadArea.addEventListener('dragleave', handleDragLeave);
            uploadArea.addEventListener('drop', handleDrop);
        }

        // ドラッグオーバー処理
        function handleDragOver(e) {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        }

        // ドラッグリーブ処理
        function handleDragLeave(e) {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
        }

        // ドロップ処理
        function handleDrop(e) {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                const file = files[0];
                validateAndSetFile(file);
            }
        }

        // ファイル選択処理
        function handleFileSelect(e) {
            const file = e.target.files[0];
            if (file) {
                validateAndSetFile(file);
            }
        }

        // ファイル検証と設定
        function validateAndSetFile(file) {
            console.log('選択されたファイル:', file.name, file.type, file.size);
            
            // ファイル拡張子チェック
            if (!file.name.toLowerCase().endsWith('.csv')) {
                showError('CSV ファイルを選択してください（.csv）');
                return;
            }

            // ファイルサイズチェック（10MB）
            if (file.size > 10 * 1024 * 1024) {
                showError('ファイルサイズが10MBを超えています');
                return;
            }

            selectedFile = file;
            updateUploadArea(file);
            uploadFile();
        }

        // アップロードエリア更新
        function updateUploadArea(file) {
            uploadArea.innerHTML = `
                <div class="upload-icon text-success">
                    <i class="bi bi-file-earmark-check"></i>
                </div>
                <h5>選択されたファイル</h5>
                <p class="text-success">${file.name}</p>
                <small class="text-muted">サイズ: ${(file.size / 1024).toFixed(1)} KB</small>
                <div class="mt-3">
                    <button type="button" class="btn btn-primary" onclick="uploadFile()">
                        <i class="bi bi-upload"></i> インポート開始
                    </button>
                    <button type="button" class="btn btn-outline-secondary ms-2" onclick="resetUpload()">
                        <i class="bi bi-arrow-clockwise"></i> リセット
                    </button>
                </div>
            `;
        }

        // アップロードリセット
        function resetUpload() {
            selectedFile = null;
            fileInput.value = '';
            progressBar.style.display = 'none';
            resultsSection.style.display = 'none';
            
            uploadArea.innerHTML = `
                <div class="upload-icon">
                    <i class="bi bi-cloud-upload"></i>
                </div>
                <h4>CSV ファイルをドラッグ&ドロップ</h4>
                <p class="text-muted">またはクリックしてファイルを選択</p>
                <div class="mt-3">
                    <small class="text-muted">
                        <i class="bi bi-info-circle"></i>
                        対応ファイル: .csv（最大10MB）<br>
                        文字エンコード: SJIS-win, UTF-8 自動判別
                    </small>
                </div>
            `;
        }

        // ファイルアップロード実行（HTTPステータス0エラー修正版）
        function uploadFile() {
            if (!selectedFile) {
                showError('ファイルが選択されていません');
                return;
            }

            console.log('アップロード開始:', selectedFile.name);
            
            // プログレスバー表示
            progressBar.style.display = 'block';
            updateProgress(10);

            // FormData 作成
            const formData = new FormData();
            formData.append('csvFile', selectedFile);  // パラメータ名を 'csvFile' に修正
            
            console.log('FormData作成完了');
            updateProgress(30);

            // fetch リクエスト（エラーハンドリング強化版）
            fetch('../api/import.php', {
                method: 'POST',
                body: formData,
                // Content-Type ヘッダーは自動設定されるため削除
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'  // AJAX識別用
                }
            })
            .then(response => {
                console.log('レスポンス受信:', response.status, response.statusText);
                updateProgress(70);
                
                // レスポンステキストを取得
                return response.text().then(text => {
                    console.log('レスポンステキスト:', text);
                    
                    // JSONパース試行
                    try {
                        const data = JSON.parse(text);
                        return { response, data };
                    } catch (parseError) {
                        console.error('JSON パースエラー:', parseError);
                        throw new Error(`サーバーレスポンスが不正です: ${text.substring(0, 200)}`);
                    }
                });
            })
            .then(({response, data}) => {
                updateProgress(90);
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${data.message || 'サーバーエラー'}`);
                }
                
                updateProgress(100);
                handleUploadSuccess(data);
            })
            .catch(error => {
                console.error('アップロードエラー:', error);
                handleUploadError(error);
            })
            .finally(() => {
                // プログレスバー非表示（少し遅延）
                setTimeout(() => {
                    progressBar.style.display = 'none';
                }, 1000);
            });
        }

        // プログレス更新
        function updateProgress(percent) {
            const progressBarInner = progressBar.querySelector('.progress-bar');
            progressBarInner.style.width = percent + '%';
            progressBarInner.textContent = percent + '%';
        }

// アップロード成功処理
function handleUploadSuccess(data) {
    console.log('アップロード成功:', data);
    
    resultsSection.style.display = 'block';
    
    const stats = data.data?.stats || {};
    
    // 成功結果表示
    successResults.innerHTML = `
        <div class="mb-3">
            <span class="status-indicator status-success"></span>
            <strong>インポート完了</strong>
        </div>
        <ul class="list-unstyled">
            <li><i class="bi bi-check2"></i> 処理件数: ${stats.total_records || 0}件</li>
            <li><i class="bi bi-check2"></i> 成功: ${stats.success_records || 0}件</li>
            <li><i class="bi bi-exclamation-triangle text-warning"></i> エラー: ${stats.error_records || 0}件</li>
            <li><i class="bi bi-info-circle"></i> 重複: ${stats.duplicate_orders || 0}件</li>
        </ul>
        <div class="mt-2">
            <small class="text-muted">
                処理時間: ${stats.processing_time || '0'}秒<br>
                バッチID: ${data.data?.batch_id || 'N/A'}
            </small>
        </div>
    `;

            // エラー詳細表示
            if (data.errors && data.errors.length > 0) {
                errorResults.innerHTML = `
                    <div class="error-details">
                        <h6>検出されたエラー (${data.errors.length}件)</h6>
                        ${data.errors.map(error => `
                            <div class="mb-2 p-2 border-start border-warning border-3">
                                <strong>行 ${error.row || 'N/A'}:</strong> ${error.message || error}
                            </div>
                        `).join('')}
                    </div>
                `;
            } else {
                errorResults.innerHTML = `
                    <div class="text-center text-muted">
                        <i class="bi bi-check-circle text-success"></i>
                        <p>エラーはありませんでした</p>
                    </div>
                `;
            }

            showSuccess(data.message || 'CSVインポートが完了しました');
        }

        // アップロードエラー処理
        function handleUploadError(error) {
            console.error('アップロードエラー:', error);
            
            resultsSection.style.display = 'block';
            
            successResults.innerHTML = `
                <div class="text-center text-danger">
                    <i class="bi bi-x-circle"></i>
                    <p>インポートに失敗しました</p>
                </div>
            `;
            
            errorResults.innerHTML = `
                <div class="error-details">
                    <h6>エラー詳細</h6>
                    <div class="alert alert-danger">
                        <strong>エラータイプ:</strong> ${error.name || 'Error'}<br>
                        <strong>メッセージ:</strong> ${error.message}<br>
                        <strong>発生時刻:</strong> ${new Date().toLocaleString()}
                    </div>
                    <div class="mt-3">
                        <h6>トラブルシューティング</h6>
                        <ul>
                            <li>ファイル形式がCSVであることを確認してください</li>
                            <li>ファイルサイズが10MB以下であることを確認してください</li>
                            <li>ネットワーク接続を確認してください</li>
                            <li>問題が続く場合は管理者にお問い合わせください</li>
                        </ul>
                    </div>
                </div>
            `;
            
            showError('アップロードに失敗しました: ' + error.message);
        }

        // システム状態確認
        function checkSystemStatus() {
            const statusContent = document.getElementById('system-status-content');
            statusContent.innerHTML = '<div class="spinner-border spinner-border-sm" role="status"></div> システム確認中...';
            
            fetch('../api/safe_db_test.php')
                .then(response => response.json())
                .then(data => {
                    console.log('システム状態:', data);
                    displaySystemStatus(data);
                })
                .catch(error => {
                    console.error('システム確認エラー:', error);
                    statusContent.innerHTML = `
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle"></i>
                            システム状態の確認に失敗しました
                        </div>
                    `;
                });
        }

        // システム状態表示
        function displaySystemStatus(data) {
            const statusContent = document.getElementById('system-status-content');
            
            if (data.success) {
                statusContent.innerHTML = `
                    <div class="row">
                        <div class="col-md-6">
                            <span class="status-indicator status-success"></span>
                            <strong>データベース接続:</strong> 正常
                        </div>
                        <div class="col-md-6">
                            <span class="status-indicator status-info"></span>
                            <strong>応答時間:</strong> ${data.data?.direct_connection_test?.query_result?.time || 'N/A'}
                        </div>
                    </div>
                `;
            } else {
                statusContent.innerHTML = `
                    <div class="alert alert-danger">
                        <span class="status-indicator status-error"></span>
                        <strong>システムエラー:</strong> ${data.message || '不明なエラー'}
                    </div>
                `;
            }
        }

        // 成功メッセージ表示
        function showSuccess(message) {
            console.log('成功:', message);
            // 必要に応じてトーストやアラート表示
        }

        // エラーメッセージ表示
        function showError(message) {
            console.error('エラー:', message);
            alert('エラー: ' + message);
        }
    </script>
</body>
</html>
