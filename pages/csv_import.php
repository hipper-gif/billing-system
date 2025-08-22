<?php
/**
 * CSVインポート画面（完全修正版）
 * Smiley配食システム専用
 * 
 * 修正内容:
 * 1. 正しいPOSTリクエスト送信
 * 2. ドラッグ&ドロップ対応
 * 3. リアルタイム進捗表示
 * 4. エラーハンドリング強化
 */

require_once '../config/database.php';
require_once '../classes/SecurityHelper.php';

// セキュリティヘッダー設定
SecurityHelper::setSecurityHeaders();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🍱 CSVインポート - Smiley配食システム</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .main-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            margin: 20px auto;
            padding: 30px;
            max-width: 800px;
        }
        .smiley-green { color: #2E8B57; }
        .bg-smiley-green { background-color: #2E8B57; }
        
        /* ドラッグ&ドロップエリア */
        .drop-zone {
            border: 3px dashed #ced4da;
            border-radius: 15px;
            padding: 60px 20px;
            text-align: center;
            transition: all 0.3s ease;
            background: #f8f9fa;
            cursor: pointer;
            min-height: 200px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }
        .drop-zone:hover, .drop-zone.dragover {
            border-color: #2E8B57;
            background: #f0fff0;
            transform: scale(1.02);
        }
        .drop-zone.dragover {
            border-color: #228B22;
            background: #e8f5e8;
        }
        
        /* プログレスバー */
        .progress-custom {
            height: 25px;
            border-radius: 12px;
            background: #e9ecef;
            overflow: hidden;
        }
        .progress-bar-animated {
            animation: progress-bar-stripes 1s linear infinite;
        }
        
        /* 結果表示 */
        .result-container {
            margin-top: 30px;
            padding: 20px;
            border-radius: 10px;
            background: #f8f9fa;
            border-left: 5px solid #2E8B57;
        }
        .error-container {
            border-left-color: #dc3545;
            background: #fff5f5;
        }
        
        /* アニメーション */
        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* ファイル情報 */
        .file-info {
            background: white;
            border-radius: 10px;
            padding: 15px;
            margin: 15px 0;
            border: 1px solid #dee2e6;
        }
        
        /* ステップ表示 */
        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }
        .step {
            flex: 1;
            text-align: center;
            padding: 10px;
            position: relative;
        }
        .step::after {
            content: '';
            position: absolute;
            top: 25px;
            right: -50%;
            width: 100%;
            height: 2px;
            background: #dee2e6;
            z-index: -1;
        }
        .step:last-child::after {
            display: none;
        }
        .step.active {
            color: #2E8B57;
            font-weight: bold;
        }
        .step.completed {
            color: #28a745;
        }
        .step.completed::after {
            background: #28a745;
        }
    </style>
</head>
<body>
    <div class="main-container">
        <!-- ヘッダー -->
        <div class="text-center mb-4">
            <h1 class="display-5 smiley-green mb-3">🍱 CSVインポート</h1>
            <p class="lead text-muted">Smiley配食システム専用</p>
            <a href="../index.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> ダッシュボードに戻る
            </a>
        </div>

        <!-- ステップ表示 -->
        <div class="step-indicator">
            <div class="step active" id="step1">
                <div class="h5">1</div>
                <div>ファイル選択</div>
            </div>
            <div class="step" id="step2">
                <div class="h5">2</div>
                <div>アップロード</div>
            </div>
            <div class="step" id="step3">
                <div class="h5">3</div>
                <div>インポート処理</div>
            </div>
            <div class="step" id="step4">
                <div class="h5">4</div>
                <div>完了</div>
            </div>
        </div>

        <!-- ファイルアップロードエリア -->
        <div class="drop-zone" id="dropZone" onclick="document.getElementById('fileInput').click()">
            <i class="bi bi-cloud-upload-fill fs-1 smiley-green mb-3"></i>
            <h4 class="smiley-green">CSVファイルをドラッグ&ドロップ</h4>
            <p class="text-muted mb-3">または、クリックしてファイルを選択</p>
            <div class="btn btn-outline-success">
                <i class="bi bi-folder2-open"></i> ファイルを選択
            </div>
            <small class="text-muted mt-2 d-block">
                対応形式：CSV（SJIS-win、UTF-8）<br>
                最大サイズ：50MB
            </small>
        </div>

        <!-- 隠しファイル入力 -->
        <input type="file" id="fileInput" accept=".csv" style="display: none;">

        <!-- ファイル情報表示 -->
        <div id="fileInfo" class="file-info" style="display: none;">
            <h5><i class="bi bi-file-earmark-text"></i> 選択されたファイル</h5>
            <div id="fileDetails"></div>
            <div class="mt-3">
                <button class="btn btn-success" id="uploadBtn">
                    <i class="bi bi-upload"></i> インポート開始
                </button>
                <button class="btn btn-secondary ms-2" onclick="resetForm()">
                    <i class="bi bi-arrow-clockwise"></i> リセット
                </button>
            </div>
        </div>

        <!-- プログレス表示 -->
        <div id="progressContainer" style="display: none;">
            <h5><i class="bi bi-gear-fill"></i> インポート進行中...</h5>
            <div class="progress progress-custom mb-3">
                <div class="progress-bar bg-success progress-bar-striped progress-bar-animated" 
                     id="progressBar" style="width: 0%">0%</div>
            </div>
            <div id="progressStatus" class="text-muted">処理を開始しています...</div>
        </div>

        <!-- 結果表示 -->
        <div id="resultContainer" class="result-container" style="display: none;">
            <h5 id="resultTitle"></h5>
            <div id="resultContent"></div>
        </div>

        <!-- フッター -->
        <div class="text-center mt-5 pt-4 border-top">
            <p class="text-muted mb-0">
                <strong>Smiley配食事業 請求書管理システム v1.0.0</strong><br>
                CSVファイルは23フィールド形式に対応しています
            </p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // グローバル変数
        let selectedFile = null;
        let isUploading = false;

        // DOM要素
        const dropZone = document.getElementById('dropZone');
        const fileInput = document.getElementById('fileInput');
        const fileInfo = document.getElementById('fileInfo');
        const fileDetails = document.getElementById('fileDetails');
        const uploadBtn = document.getElementById('uploadBtn');
        const progressContainer = document.getElementById('progressContainer');
        const progressBar = document.getElementById('progressBar');
        const progressStatus = document.getElementById('progressStatus');
        const resultContainer = document.getElementById('resultContainer');
        const resultTitle = document.getElementById('resultTitle');
        const resultContent = document.getElementById('resultContent');

        // ドラッグ&ドロップイベント
        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.classList.add('dragover');
        });

        dropZone.addEventListener('dragleave', (e) => {
            e.preventDefault();
            dropZone.classList.remove('dragover');
        });

        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                handleFileSelect(files[0]);
            }
        });

        // ファイル選択イベント
        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                handleFileSelect(e.target.files[0]);
            }
        });

        // ファイル選択処理
        function handleFileSelect(file) {
            // ファイル形式チェック
            if (!file.name.toLowerCase().endsWith('.csv')) {
                showError('CSVファイルを選択してください。');
                return;
            }

            // ファイルサイズチェック（50MB）
            if (file.size > 50 * 1024 * 1024) {
                showError('ファイルサイズが50MBを超えています。');
                return;
            }

            selectedFile = file;
            displayFileInfo(file);
            updateStep(2);
        }

        // ファイル情報表示
        function displayFileInfo(file) {
            const fileSize = (file.size / 1024 / 1024).toFixed(2);
            const lastModified = new Date(file.lastModified).toLocaleString('ja-JP');

            fileDetails.innerHTML = `
                <div class="row">
                    <div class="col-sm-3"><strong>ファイル名:</strong></div>
                    <div class="col-sm-9">${file.name}</div>
                </div>
                <div class="row">
                    <div class="col-sm-3"><strong>サイズ:</strong></div>
                    <div class="col-sm-9">${fileSize} MB</div>
                </div>
                <div class="row">
                    <div class="col-sm-3"><strong>更新日時:</strong></div>
                    <div class="col-sm-9">${lastModified}</div>
                </div>
                <div class="row">
                    <div class="col-sm-3"><strong>形式:</strong></div>
                    <div class="col-sm-9">CSV (Smiley配食システム23フィールド対応)</div>
                </div>
            `;

            fileInfo.style.display = 'block';
            fileInfo.classList.add('fade-in');
        }

        // アップロードボタンクリック
        uploadBtn.addEventListener('click', () => {
            if (!selectedFile || isUploading) return;
            
            uploadFile();
        });

        // ファイルアップロード処理
        function uploadFile() {
            isUploading = true;
            updateStep(3);
            
            // プログレス表示開始
            progressContainer.style.display = 'block';
            progressContainer.classList.add('fade-in');
            resultContainer.style.display = 'none';

            // FormData作成
            const formData = new FormData();
            formData.append('csv_file', selectedFile);
            formData.append('action', 'import');

            // XMLHttpRequest使用（進捗監視のため）
            const xhr = new XMLHttpRequest();

            // 進捗監視
            xhr.upload.onprogress = (e) => {
                if (e.lengthComputable) {
                    const percentComplete = Math.round((e.loaded / e.total) * 100);
                    updateProgress(percentComplete, 'ファイルをアップロード中...');
                }
            };

            // 完了時の処理
            xhr.onload = () => {
                if (xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        handleUploadResponse(response);
                    } catch (e) {
                        showError('レスポンスの解析に失敗しました: ' + e.message);
                    }
                } else {
                    showError(`HTTP エラー: ${xhr.status} - ${xhr.statusText}`);
                }
                isUploading = false;
            };

            // エラー時の処理
            xhr.onerror = () => {
                showError('ネットワークエラーが発生しました。');
                isUploading = false;
            };

            // タイムアウト設定
            xhr.timeout = 300000; // 5分
            xhr.ontimeout = () => {
                showError('アップロードがタイムアウトしました。');
                isUploading = false;
            };

            // リクエスト送信
            xhr.open('POST', '../api/import.php', true);
            xhr.send(formData);

            updateProgress(10, 'サーバーに接続中...');
        }

        // アップロード結果処理
        function handleUploadResponse(response) {
            updateProgress(100, '処理完了');
            
            setTimeout(() => {
                progressContainer.style.display = 'none';
                
                if (response.success) {
                    showSuccess(response);
                    updateStep(4);
                } else {
                    showError(response.message, response);
                }
            }, 1000);
        }

        // 成功時の表示
        function showSuccess(response) {
            resultTitle.innerHTML = '<i class="bi bi-check-circle-fill text-success"></i> インポート成功';
            
            let content = '<div class="alert alert-success">';
            content += `<h6>インポート結果</h6>`;
            
            if (response.stats) {
                content += `<ul class="mb-0">`;
                content += `<li>処理レコード数: ${response.stats.total_processed || 0}</li>`;
                content += `<li>成功: ${response.stats.success_count || 0}</li>`;
                if (response.stats.error_count > 0) {
                    content += `<li>エラー: ${response.stats.error_count}</li>`;
                }
                content += `</ul>`;
            } else {
                content += response.message;
            }
            
            content += '</div>';
            
            // エラー詳細表示
            if (response.errors && response.errors.length > 0) {
                content += '<div class="alert alert-warning mt-3">';
                content += '<h6>エラー詳細</h6>';
                content += '<ul class="mb-0">';
                response.errors.slice(0, 10).forEach(error => {
                    content += `<li>${error}</li>`;
                });
                if (response.errors.length > 10) {
                    content += `<li>他 ${response.errors.length - 10} 件のエラーがあります</li>`;
                }
                content += '</ul></div>';
            }
            
            content += '<div class="mt-3">';
            content += '<a href="../pages/companies.php" class="btn btn-primary me-2">';
            content += '<i class="bi bi-building"></i> 企業管理画面で確認</a>';
            content += '<button class="btn btn-success" onclick="resetForm()">';
            content += '<i class="bi bi-plus-circle"></i> 新しいファイルをインポート</button>';
            content += '</div>';
            
            resultContent.innerHTML = content;
            resultContainer.className = 'result-container fade-in';
            resultContainer.style.display = 'block';
        }

        // エラー時の表示
        function showError(message, response = null) {
            resultTitle.innerHTML = '<i class="bi bi-exclamation-triangle-fill text-danger"></i> エラーが発生しました';
            
            let content = `<div class="alert alert-danger">${message}</div>`;
            
            if (response && response.debug_info && response.debug_info.trace) {
                content += '<div class="alert alert-warning">';
                content += '<h6>デバッグ情報</h6>';
                content += `<small><pre>${response.debug_info.trace}</pre></small>`;
                content += '</div>';
            }
            
            content += '<div class="mt-3">';
            content += '<button class="btn btn-primary" onclick="resetForm()">';
            content += '<i class="bi bi-arrow-clockwise"></i> 再試行</button>';
            content += '<a href="../pages/system_health.php" class="btn btn-info ms-2">';
            content += '<i class="bi bi-gear"></i> システム状況確認</a>';
            content += '</div>';
            
            resultContent.innerHTML = content;
            resultContainer.className = 'result-container error-container fade-in';
            resultContainer.style.display = 'block';
            
            progressContainer.style.display = 'none';
            isUploading = false;
        }

        // プログレス更新
        function updateProgress(percent, status) {
            progressBar.style.width = percent + '%';
            progressBar.textContent = percent + '%';
            progressStatus.textContent = status;
        }

        // ステップ更新
        function updateStep(step) {
            for (let i = 1; i <= 4; i++) {
                const stepElement = document.getElementById(`step${i}`);
                stepElement.classList.remove('active', 'completed');
                
                if (i < step) {
                    stepElement.classList.add('completed');
                } else if (i === step) {
                    stepElement.classList.add('active');
                }
            }
        }

        // フォームリセット
        function resetForm() {
            selectedFile = null;
            isUploading = false;
            fileInput.value = '';
            fileInfo.style.display = 'none';
            progressContainer.style.display = 'none';
            resultContainer.style.display = 'none';
            dropZone.classList.remove('dragover');
            updateStep(1);
        }

        // 初期化
        document.addEventListener('DOMContentLoaded', () => {
            console.log('CSVインポート画面が読み込まれました');
        });
    </script>
</body>
</html>
