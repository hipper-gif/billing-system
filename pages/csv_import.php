<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CSVインポート - Smiley配食事業 請求書管理システム</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --smiley-primary: #2E8B57;
            --smiley-secondary: #90EE90;
            --smiley-accent: #FFD700;
            --smiley-danger: #DC3545;
            --smiley-success: #28A745;
        }

        body {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
        }

        .main-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .header-card {
            background: linear-gradient(135deg, var(--smiley-primary) 0%, #1e6b3d 100%);
            color: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 32px rgba(46, 139, 87, 0.2);
        }

        .upload-area {
            border: 3px dashed var(--smiley-secondary);
            border-radius: 15px;
            padding: 3rem;
            text-align: center;
            background: white;
            transition: all 0.3s ease;
            position: relative;
            min-height: 300px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            cursor: pointer;
        }

        .upload-area.dragover {
            border-color: var(--smiley-primary);
            background: linear-gradient(135deg, var(--smiley-secondary) 0%, #98fb98 100%);
            transform: scale(1.02);
        }

        .upload-area.processing {
            border-color: var(--smiley-accent);
            background: #fff9e6;
        }

        .upload-icon {
            font-size: 4rem;
            color: var(--smiley-primary);
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }

        .upload-area.dragover .upload-icon {
            color: #1e6b3d;
            transform: scale(1.1);
        }

        .progress-container {
            display: none;
            margin: 2rem 0;
        }

        .progress {
            height: 25px;
            border-radius: 15px;
            background: #e9ecef;
            overflow: hidden;
        }

        .progress-bar {
            background: linear-gradient(90deg, var(--smiley-primary) 0%, var(--smiley-secondary) 100%);
            transition: width 0.3s ease;
            border-radius: 15px;
        }

        .results-container {
            display: none;
            margin-top: 2rem;
        }

        .result-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 4px 16px rgba(0,0,0,0.1);
            border-left: 5px solid var(--smiley-success);
        }

        .result-card.error {
            border-left-color: var(--smiley-danger);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin: 1rem 0;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 4px 16px rgba(0,0,0,0.1);
            border-top: 4px solid var(--smiley-primary);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: var(--smiley-primary);
        }

        .error-list {
            max-height: 300px;
            overflow-y: auto;
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1rem;
        }

        .error-item {
            background: white;
            border-left: 4px solid var(--smiley-danger);
            padding: 0.75rem;
            margin-bottom: 0.5rem;
            border-radius: 5px;
        }

        .btn-smiley {
            background: linear-gradient(135deg, var(--smiley-primary) 0%, #1e6b3d 100%);
            border: none;
            color: white;
            padding: 0.75rem 2rem;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-smiley:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(46, 139, 87, 0.3);
            color: white;
        }

        .csv-format-info {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 16px rgba(0,0,0,0.1);
        }

        .format-table {
            font-size: 0.85rem;
            margin-top: 1rem;
        }

        .spinner {
            width: 2rem;
            height: 2rem;
            border: 3px solid #f3f3f3;
            border-top: 3px solid var(--smiley-primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1050;
            border-radius: 10px;
        }

        @media (max-width: 768px) {
            .main-container {
                padding: 1rem;
            }

            .upload-area {
                padding: 2rem 1rem;
                min-height: 200px;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <div class="main-container">
        <!-- ヘッダー -->
        <div class="header-card">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="mb-2">
                        <i class="fas fa-upload me-3"></i>
                        CSVインポート
                    </h1>
                    <p class="mb-0 fs-5">Smiley配食事業 給食システムCSVデータの取り込み</p>
                </div>
                <div class="col-md-4 text-end">
                    <a href="../index.php" class="btn btn-outline-light btn-lg">
                        <i class="fas fa-arrow-left me-2"></i>
                        ダッシュボードに戻る
                    </a>
                </div>
            </div>
        </div>

        <!-- CSVフォーマット情報 -->
        <div class="csv-format-info">
            <h4 class="text-primary mb-3">
                <i class="fas fa-info-circle me-2"></i>
                対応CSVフォーマット（Smiley配食事業仕様）
            </h4>
            <div class="row">
                <div class="col-md-6">
                    <h6 class="fw-bold text-success">必須条件</h6>
                    <ul class="list-unstyled">
                        <li><i class="fas fa-check text-success me-2"></i>法人名: 「株式会社Smiley」固定</li>
                        <li><i class="fas fa-check text-success me-2"></i>23フィールド形式のCSV</li>
                        <li><i class="fas fa-check text-success me-2"></i>文字エンコーディング: UTF-8, Shift-JIS対応</li>
                        <li><i class="fas fa-check text-success me-2"></i>ファイルサイズ: 10MB以下</li>
                    </ul>
                </div>
                <div class="col-md-6">
                    <h6 class="fw-bold text-warning">主要フィールド</h6>
                    <div class="format-table">
                        <small class="text-muted">
                            法人CD・法人名・事業所CD・事業所名・給食業者CD・給食業者名・給食区分CD・給食区分名・配達日・部門CD・部門名・社員CD・社員名・雇用形態CD・雇用形態名・給食ﾒﾆｭｰCD・給食ﾒﾆｭｰ名・数量・単価・金額・備考・受取時間・連携CD
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <!-- アップロード画面 -->
        <div class="upload-section">
            <div class="upload-area" id="uploadArea">
                <div class="upload-content">
                    <i class="fas fa-cloud-upload-alt upload-icon"></i>
                    <h3 class="text-primary mb-3">CSVファイルをドラッグ&ドロップ</h3>
                    <p class="text-muted mb-4">または、クリックしてファイルを選択してください</p>
                    
                    <form id="csvUploadForm" enctype="multipart/form-data">
                        <input type="file" id="csvFile" name="csv_file" accept=".csv" style="display: none;">
                        
                        <div class="row justify-content-center mb-4">
                            <div class="col-md-8">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">エンコーディング</label>
                                        <select class="form-select" name="encoding">
                                            <option value="auto">自動検出</option>
                                            <option value="utf-8">UTF-8</option>
                                            <option value="shift_jis">Shift-JIS</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">インポートオプション</label>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="overwrite" id="overwrite">
                                            <label class="form-check-label" for="overwrite">
                                                既存データを上書き
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <button type="button" class="btn btn-smiley btn-lg" onclick="document.getElementById('csvFile').click()">
                            <i class="fas fa-folder-open me-2"></i>
                            ファイルを選択
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- プログレスバー -->
        <div class="progress-container" id="progressContainer">
            <h5 class="text-primary mb-3">
                <i class="fas fa-cog fa-spin me-2"></i>
                CSVインポート処理中...
            </h5>
            <div class="progress mb-3">
                <div class="progress-bar" role="progressbar" style="width: 0%">
                    <span class="progress-text">0%</span>
                </div>
            </div>
            <div class="text-center">
                <small class="text-muted" id="progressStatus">ファイルを読み込んでいます...</small>
            </div>
        </div>

        <!-- 結果表示 -->
        <div class="results-container" id="resultsContainer">
            <!-- 成功時の統計情報 -->
            <div class="result-card" id="successCard" style="display: none;">
                <h4 class="text-success mb-3">
                    <i class="fas fa-check-circle me-2"></i>
                    インポート完了
                </h4>
                
                <div class="stats-grid" id="statsGrid">
                    <!-- 統計カードがJavaScriptで動的生成 -->
                </div>
                
                <div class="row mt-4">
                    <div class="col-md-6">
                        <h6 class="fw-bold">インポート詳細</h6>
                        <div id="importDetails"></div>
                    </div>
                    <div class="col-md-6">
                        <h6 class="fw-bold">次のステップ</h6>
                        <div class="d-grid gap-2">
                            <a href="../pages/companies.php" class="btn btn-outline-primary">
                                <i class="fas fa-building me-2"></i>
                                配達先企業を確認
                            </a>
                            <a href="../pages/orders.php" class="btn btn-outline-success">
                                <i class="fas fa-list me-2"></i>
                                注文データを確認
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- エラー時の詳細情報 -->
            <div class="result-card error" id="errorCard" style="display: none;">
                <h4 class="text-danger mb-3">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    インポートエラー
                </h4>
                
                <div id="errorSummary" class="mb-3"></div>
                
                <div class="error-list" id="errorList">
                    <!-- エラー詳細がJavaScriptで動的生成 -->
                </div>
                
                <div class="mt-3">
                    <button class="btn btn-warning" onclick="location.reload()">
                        <i class="fas fa-redo me-2"></i>
                        再試行
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast通知 -->
    <div class="toast-container position-fixed top-0 end-0 p-3">
        <div id="notificationToast" class="toast" role="alert">
            <div class="toast-header">
                <i class="fas fa-bell text-primary me-2"></i>
                <strong class="me-auto">通知</strong>
                <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
            </div>
            <div class="toast-body" id="toastMessage">
                <!-- メッセージがJavaScriptで設定 -->
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        class SmileyCSVUploader {
            constructor() {
                this.uploadArea = document.getElementById('uploadArea');
                this.fileInput = document.getElementById('csvFile');
                this.form = document.getElementById('csvUploadForm');
                this.progressContainer = document.getElementById('progressContainer');
                this.resultsContainer = document.getElementById('resultsContainer');
                
                this.initializeEventListeners();
            }

            initializeEventListeners() {
                // ドラッグ&ドロップイベント
                this.uploadArea.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    this.uploadArea.classList.add('dragover');
                });

                this.uploadArea.addEventListener('dragleave', (e) => {
                    e.preventDefault();
                    this.uploadArea.classList.remove('dragover');
                });

                this.uploadArea.addEventListener('drop', (e) => {
                    e.preventDefault();
                    this.uploadArea.classList.remove('dragover');
                    
                    const files = e.dataTransfer.files;
                    if (files.length > 0) {
                        this.handleFileSelect(files[0]);
                    }
                });

                // ファイル選択イベント
                this.fileInput.addEventListener('change', (e) => {
                    if (e.target.files.length > 0) {
                        this.handleFileSelect(e.target.files[0]);
                    }
                });

                // クリックでファイル選択
                this.uploadArea.addEventListener('click', () => {
                    this.fileInput.click();
                });
            }

            handleFileSelect(file) {
                // ファイル検証
                if (!file.name.toLowerCase().endsWith('.csv')) {
                    this.showToast('CSVファイルを選択してください。', 'error');
                    return;
                }

                if (file.size > 10 * 1024 * 1024) { // 10MB
                    this.showToast('ファイルサイズが10MBを超えています。', 'error');
                    return;
                }

                this.showToast(`${file.name} を選択しました。アップロードを開始します。`, 'success');
                this.startUpload(file);
            }

            async startUpload(file) {
                try {
                    // UI更新
                    this.uploadArea.classList.add('processing');
                    this.progressContainer.style.display = 'block';
                    this.resultsContainer.style.display = 'none';
                    
                    // FormData作成
                    const formData = new FormData();
                    formData.append('csv_file', file);
                    formData.append('encoding', this.form.encoding.value);
                    formData.append('overwrite', this.form.overwrite.checked);

                    // プログレス開始
                    this.updateProgress(10, 'ファイルをアップロード中...');

                    // API呼び出し
                    const response = await fetch('../api/import_500_debug.php', {
                        method: 'POST',
                        body: formData
                    });

                    this.updateProgress(50, 'データを処理中...');

                    if (!response.ok) {
                        throw new Error(`HTTP Error: ${response.status}`);
                    }

                    const result = await response.json();

                    this.updateProgress(90, '結果を処理中...');

                    // 結果処理
                    setTimeout(() => {
                        this.updateProgress(100, '完了');
                        this.showResults(result);
                    }, 500);

                } catch (error) {
                    console.error('Upload error:', error);
                    this.showError('アップロードエラー', error.message);
                }
            }

            updateProgress(percentage, status) {
                const progressBar = document.querySelector('.progress-bar');
                const progressText = document.querySelector('.progress-text');
                const progressStatus = document.getElementById('progressStatus');
                
                progressBar.style.width = `${percentage}%`;
                progressText.textContent = `${percentage}%`;
                progressStatus.textContent = status;
            }

            showResults(result) {
                this.progressContainer.style.display = 'none';
                this.resultsContainer.style.display = 'block';
                this.uploadArea.classList.remove('processing');

                if (result.success) {
                    this.showSuccessResults(result);
                } else {
                    this.showErrorResults(result);
                }
            }

            showSuccessResults(result) {
                const successCard = document.getElementById('successCard');
                const statsGrid = document.getElementById('statsGrid');
                const importDetails = document.getElementById('importDetails');

                // 統計カード作成
                const stats = result.data.stats;
                statsGrid.innerHTML = `
                    <div class="stat-card">
                        <div class="stat-number">${stats.total_records || 0}</div>
                        <div class="text-muted">総レコード数</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number text-success">${stats.success_records || 0}</div>
                        <div class="text-muted">成功</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number text-danger">${stats.error_records || 0}</div>
                        <div class="text-muted">エラー</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number text-warning">${stats.duplicate_records || 0}</div>
                        <div class="text-muted">重複</div>
                    </div>
                `;

                // 詳細情報
                importDetails.innerHTML = `
                    <ul class="list-unstyled">
                        <li><strong>バッチID:</strong> ${result.data.batch_id || 'N/A'}</li>
                        <li><strong>ファイル名:</strong> ${result.data.filename || 'N/A'}</li>
                        <li><strong>処理時間:</strong> ${stats.processing_time || 'N/A'}</li>
                        <li><strong>エンコーディング:</strong> ${result.data.import_summary?.encoding_detected || 'auto'}</li>
                    </ul>
                `;

                // エラーがある場合は表示
                if (result.data.errors && result.data.errors.length > 0) {
                    const errorSection = document.createElement('div');
                    errorSection.className = 'mt-3';
                    errorSection.innerHTML = `
                        <h6 class="text-warning">部分的なエラー (${result.data.errors.length}件)</h6>
                        <div class="error-list" style="max-height: 150px;">
                            ${result.data.errors.map(error => `
                                <div class="error-item">
                                    <strong>行${error.row}:</strong> ${error.errors.join(', ')}
                                </div>
                            `).join('')}
                        </div>
                        ${result.data.has_more_errors ? '<small class="text-muted">※ 他にもエラーがあります</small>' : ''}
                    `;
                    importDetails.appendChild(errorSection);
                }

                successCard.style.display = 'block';
                this.showToast('CSVインポートが正常に完了しました！', 'success');
            }

            showErrorResults(result) {
                const errorCard = document.getElementById('errorCard');
                const errorSummary = document.getElementById('errorSummary');
                const errorList = document.getElementById('errorList');

                errorSummary.innerHTML = `
                    <div class="alert alert-danger">
                        <strong>エラー:</strong> ${result.message || '不明なエラー'}
                    </div>
                `;

                if (result.data && result.data.troubleshooting) {
                    const troubleshooting = result.data.troubleshooting;
                    errorList.innerHTML = `
                        <h6 class="fw-bold">トラブルシューティング</h6>
                        <ul>
                            ${Object.values(troubleshooting).map(tip => `<li>${tip}</li>`).join('')}
                        </ul>
                    `;
                } else if (result.data && result.data.errors) {
                    errorList.innerHTML = `
                        <h6 class="fw-bold">詳細エラー</h6>
                        ${result.data.errors.map(error => `
                            <div class="error-item">
                                ${typeof error === 'object' ? JSON.stringify(error) : error}
                            </div>
                        `).join('')}
                    `;
                }

                errorCard.style.display = 'block';
                this.showToast('CSVインポートでエラーが発生しました。', 'error');
            }

            showError(title, message) {
                this.progressContainer.style.display = 'none';
                this.resultsContainer.style.display = 'block';
                this.uploadArea.classList.remove('processing');

                const errorCard = document.getElementById('errorCard');
                const errorSummary = document.getElementById('errorSummary');
                const errorList = document.getElementById('errorList');

                errorSummary.innerHTML = `
                    <div class="alert alert-danger">
                        <strong>${title}:</strong> ${message}
                    </div>
                `;

                errorList.innerHTML = `
                    <div class="error-item">
                        システムエラーが発生しました。ページを再読み込みして再試行してください。
                    </div>
                `;

                errorCard.style.display = 'block';
                this.showToast(`${title}: ${message}`, 'error');
            }

            showToast(message, type = 'info') {
                const toast = document.getElementById('notificationToast');
                const toastMessage = document.getElementById('toastMessage');
                
                toastMessage.textContent = message;
                
                // Toast色設定
                toast.className = `toast ${type === 'error' ? 'bg-danger text-white' : type === 'success' ? 'bg-success text-white' : 'bg-info text-white'}`;
                
                const bsToast = new bootstrap.Toast(toast);
                bsToast.show();
            }
        }

        // アプリケーション初期化
        document.addEventListener('DOMContentLoaded', () => {
            new SmileyCSVUploader();
        });
    </script>
</body>
</html>
