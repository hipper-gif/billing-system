<?php
/**
 * Smiley配食事業 CSVインポート画面
 * PC操作不慣れな方向けの直感的なUI
 */

require_once __DIR__ . '/../config/database.php';

// セッション開始
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 最近のインポート履歴取得
function getRecentImports($pdo, $limit = 5) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                batch_id, file_name, total_rows, success_rows, error_rows,
                new_companies, new_users, import_date, status
            FROM import_logs 
            ORDER BY import_date DESC 
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

// データベース接続
$recentImports = [];
try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    $recentImports = getRecentImports($pdo);
} catch (Exception $e) {
    $error_message = "データベース接続エラー: " . $e->getMessage();
}

// CSVテンプレート情報
$csvTemplate = [
    'fields' => [
        'delivery_date' => '配達日（例: 2024-03-01）',
        'user_code' => '利用者コード（例: U001）',
        'user_name' => '利用者名（例: 田中太郎）',
        'company_code' => '配達先企業コード（例: C001）',
        'company_name' => '配達先企業名（例: ◯◯株式会社）',
        'department_code' => '配達先部署コード（例: D001）',
        'department_name' => '配達先部署名（例: 営業部）',
        'product_code' => '商品コード（例: P001）',
        'product_name' => '商品名（例: 幕の内弁当）',
        'category_code' => '商品カテゴリコード（例: CAT001）',
        'category_name' => '商品カテゴリ名（例: 弁当）',
        'quantity' => '数量（例: 1）',
        'unit_price' => '単価（例: 500）',
        'total_amount' => '合計金額（例: 500）',
        'supplier_code' => '給食業者コード（例: S001）',
        'supplier_name' => '給食業者名（例: ◯◯給食）',
        'corporation_code' => '法人コード（例: CORP001）',
        'corporation_name' => '法人名（株式会社Smiley）',
        'employee_type_code' => '従業員区分コード（例: EMP001）',
        'employee_type_name' => '従業員区分名（例: 正社員）',
        'delivery_time' => '配達時間（例: 12:00）',
        'cooperation_code' => '協力会社コード（例: COOP001）',
        'notes' => '備考（例: 特別指示）'
    ]
];
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📊 CSVデータ取り込み - Smiley配食システム</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- カスタムCSS -->
    <style>
        body {
            font-family: 'Helvetica Neue', Arial, 'Hiragino Kaku Gothic ProN', 'Hiragino Sans', Meiryo, sans-serif;
            background-color: #f8f9fa;
            font-size: 16px;
        }
        
        .upload-area {
            border: 3px dashed #007bff;
            border-radius: 15px;
            padding: 60px 20px;
            text-align: center;
            background: linear-gradient(135deg, #f8f9ff 0%, #e3f2fd 100%);
            transition: all 0.3s ease;
            cursor: pointer;
            margin-bottom: 30px;
        }
        
        .upload-area:hover {
            border-color: #0056b3;
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            transform: translateY(-2px);
        }
        
        .upload-area.dragover {
            border-color: #28a745;
            background: linear-gradient(135deg, #f0fff4 0%, #c8f7c5 100%);
        }
        
        .upload-icon {
            font-size: 4rem;
            color: #007bff;
            margin-bottom: 20px;
            display: block;
        }
        
        .upload-text {
            font-size: 1.3rem;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .upload-subtext {
            color: #6c757d;
            font-size: 1rem;
        }
        
        .file-input {
            position: absolute;
            width: 1px;
            height: 1px;
            opacity: 0;
            overflow: hidden;
        }
        
        .progress-container {
            display: none;
            margin-top: 20px;
        }
        
        .step-card {
            border-radius: 15px;
            border: none;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        
        .step-number {
            background: #007bff;
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 15px;
        }
        
        .template-table {
            font-size: 0.9rem;
            max-height: 400px;
            overflow-y: auto;
        }
        
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: bold;
        }
        
        .status-success { background: #d4edda; color: #155724; }
        .status-partial { background: #fff3cd; color: #856404; }
        .status-error { background: #f8d7da; color: #721c24; }
        
        .btn-large {
            min-height: 60px;
            font-size: 1.2rem;
            font-weight: bold;
            border-radius: 10px;
        }
        
        @media (max-width: 768px) {
            .upload-area {
                padding: 40px 15px;
            }
            
            .upload-icon {
                font-size: 3rem;
            }
            
            .upload-text {
                font-size: 1.1rem;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid px-4 py-3">
        <!-- ヘッダー -->
        <header class="mb-4">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="text-primary fw-bold">📊 CSVデータ取り込み</h1>
                    <p class="text-muted">Smiley配食事業の注文データをシステムに取り込みます</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <a href="../index.php" class="btn btn-outline-secondary">
                        ← メイン画面に戻る
                    </a>
                </div>
            </div>
        </header>

        <?php if (isset($error_message)): ?>
        <!-- エラー表示 -->
        <div class="alert alert-danger" role="alert">
            <h4 class="alert-heading">⚠️ システムエラー</h4>
            <p><?= htmlspecialchars($error_message) ?></p>
        </div>
        <?php endif; ?>

        <div class="row">
            <!-- メインコンテンツ -->
            <div class="col-lg-8">
                <!-- アップロードエリア -->
                <div class="card step-card">
                    <div class="card-header bg-primary text-white">
                        <div class="d-flex align-items-center">
                            <div class="step-number bg-white text-primary">1</div>
                            <h5 class="mb-0">CSVファイルをアップロード</h5>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="upload-area" id="uploadArea">
                            <div class="upload-icon">📁</div>
                            <div class="upload-text">ここにCSVファイルをドラッグ&ドロップ</div>
                            <div class="upload-subtext">または クリックしてファイルを選択</div>
                            <input type="file" id="csvFile" class="file-input" accept=".csv" />
                        </div>
                        
                        <!-- ファイル情報表示 -->
                        <div id="fileInfo" style="display: none;">
                            <div class="alert alert-info">
                                <h6>選択されたファイル:</h6>
                                <div id="fileName"></div>
                                <div id="fileSize"></div>
                            </div>
                        </div>
                        
                        <!-- 処理オプション -->
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <label class="form-label">文字コード</label>
                                <select class="form-select" id="encoding">
                                    <option value="UTF-8">UTF-8</option>
                                    <option value="Shift_JIS">Shift_JIS (Excel標準)</option>
                                    <option value="EUC-JP">EUC-JP</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">区切り文字</label>
                                <select class="form-select" id="delimiter">
                                    <option value=",">カンマ (,)</option>
                                    <option value="\t">タブ</option>
                                    <option value=";">セミコロン (;)</option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- アップロードボタン -->
                        <div class="text-center mt-4">
                            <button id="uploadBtn" class="btn btn-primary btn-large px-5" disabled>
                                🚀 インポート開始
                            </button>
                        </div>
                        
                        <!-- プログレスバー -->
                        <div class="progress-container">
                            <div class="progress mb-3" style="height: 25px;">
                                <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated" 
                                     role="progressbar" style="width: 0%"></div>
                            </div>
                            <div id="progressText" class="text-center text-muted"></div>
                        </div>
                    </div>
                </div>
                
                <!-- 結果表示エリア -->
                <div id="resultArea" style="display: none;">
                    <div class="card step-card">
                        <div class="card-header">
                            <h5 class="mb-0">📈 インポート結果</h5>
                        </div>
                        <div class="card-body" id="resultContent">
                            <!-- 結果がここに表示されます -->
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- サイドバー -->
            <div class="col-lg-4">
                <!-- CSVテンプレート情報 -->
                <div class="card step-card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">📋 CSVフォーマット</h5>
                    </div>
                    <div class="card-body">
                        <p class="card-text">
                            <strong>23フィールド</strong>のCSVファイルが必要です。<br>
                            必ずヘッダー行を含めてください。
                        </p>
                        
                        <div class="accordion" id="templateAccordion">
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" 
                                            data-bs-toggle="collapse" data-bs-target="#templateFields">
                                        📝 必要フィールド一覧
                                    </button>
                                </h2>
                                <div id="templateFields" class="accordion-collapse collapse" 
                                     data-bs-parent="#templateAccordion">
                                    <div class="accordion-body">
                                        <div class="template-table">
                                            <?php foreach ($csvTemplate['fields'] as $field => $description): ?>
                                            <div class="row mb-2">
                                                <div class="col-12">
                                                    <small class="text-primary fw-bold"><?= htmlspecialchars($field) ?></small><br>
                                                    <small class="text-muted"><?= htmlspecialchars($description) ?></small>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-3">
                            <button class="btn btn-outline-info btn-sm w-100" onclick="downloadTemplate()">
                                💾 テンプレートダウンロード
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- 重要な注意事項 -->
                <div class="card step-card">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0">⚠️ 重要な注意事項</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled mb-0">
                            <li class="mb-2">
                                <span class="text-danger fw-bold">🏢 法人名確認</span><br>
                                <small>「株式会社Smiley」以外のデータはエラーになります</small>
                            </li>
                            <li class="mb-2">
                                <span class="text-warning fw-bold">📅 日付フォーマット</span><br>
                                <small>YYYY-MM-DD形式（例: 2024-03-01）を推奨</small>
                            </li>
                            <li class="mb-2">
                                <span class="text-info fw-bold">🔄 重複チェック</span><br>
                                <small>同じ利用者・日付・商品の組み合わせは自動スキップ</small>
                            </li>
                            <li class="mb-0">
                                <span class="text-success fw-bold">💾 バックアップ</span><br>
                                <small>元ファイルは必ず保管してください</small>
                            </li>
                        </ul>
                    </div>
                </div>
                
                <!-- 最近のインポート履歴 -->
                <?php if (!empty($recentImports)): ?>
                <div class="card step-card">
                    <div class="card-header">
                        <h5 class="mb-0">🕒 最近のインポート履歴</h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($recentImports as $import): ?>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div>
                                <small class="fw-bold"><?= date('m/d H:i', strtotime($import['import_date'])) ?></small><br>
                                <small class="text-muted">
                                    <?= $import['success_rows'] ?>件成功
                                    <?php if ($import['error_rows'] > 0): ?>
                                    / <?= $import['error_rows'] ?>件エラー
                                    <?php endif; ?>
                                </small>
                            </div>
                            <div>
                                <?php
                                $statusClass = 'status-success';
                                $statusText = '成功';
                                if ($import['status'] === 'partial_success') {
                                    $statusClass = 'status-partial';
                                    $statusText = '一部成功';
                                } elseif ($import['error_rows'] > 0) {
                                    $statusClass = 'status-error';
                                    $statusText = 'エラー';
                                }
                                ?>
                                <span class="status-badge <?= $statusClass ?>"><?= $statusText ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <div class="text-center mt-3">
                            <a href="import_history.php" class="btn btn-outline-secondary btn-sm">
                                📊 詳細履歴を見る
                            </a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- CSVインポート専用JavaScript -->
    <script>
        class SmileyCSVUploader {
            constructor() {
                this.uploadArea = document.getElementById('uploadArea');
                this.fileInput = document.getElementById('csvFile');
                this.uploadBtn = document.getElementById('uploadBtn');
                this.progressContainer = document.querySelector('.progress-container');
                this.progressBar = document.getElementById('progressBar');
                this.progressText = document.getElementById('progressText');
                this.resultArea = document.getElementById('resultArea');
                this.resultContent = document.getElementById('resultContent');
                
                this.initializeEventListeners();
            }
            
            initializeEventListeners() {
                // ドラッグ&ドロップ
                this.uploadArea.addEventListener('click', () => this.fileInput.click());
                this.uploadArea.addEventListener('dragover', this.handleDragOver.bind(this));
                this.uploadArea.addEventListener('dragleave', this.handleDragLeave.bind(this));
                this.uploadArea.addEventListener('drop', this.handleDrop.bind(this));
                
                // ファイル選択
                this.fileInput.addEventListener('change', this.handleFileSelect.bind(this));
                
                // アップロードボタン
                this.uploadBtn.addEventListener('click', this.startUpload.bind(this));
            }
            
            handleDragOver(e) {
                e.preventDefault();
                this.uploadArea.classList.add('dragover');
            }
            
            handleDragLeave(e) {
                e.preventDefault();
                this.uploadArea.classList.remove('dragover');
            }
            
            handleDrop(e) {
                e.preventDefault();
                this.uploadArea.classList.remove('dragover');
                
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    this.handleFile(files[0]);
                }
            }
            
            handleFileSelect(e) {
                if (e.target.files.length > 0) {
                    this.handleFile(e.target.files[0]);
                }
            }
            
            handleFile(file) {
                // ファイル形式チェック
                if (!file.name.toLowerCase().endsWith('.csv')) {
                    alert('CSVファイルを選択してください。');
                    return;
                }
                
                // ファイルサイズチェック (10MB制限)
                if (file.size > 10 * 1024 * 1024) {
                    alert('ファイルサイズが大きすぎます（10MB以下にしてください）。');
                    return;
                }
                
                // ファイル情報表示
                document.getElementById('fileName').textContent = file.name;
                document.getElementById('fileSize').textContent = 
                    `ファイルサイズ: ${(file.size / 1024).toFixed(1)} KB`;
                document.getElementById('fileInfo').style.display = 'block';
                
                // アップロードボタン有効化
                this.uploadBtn.disabled = false;
                this.selectedFile = file;
                
                // アップロードエリアの表示変更
                this.uploadArea.querySelector('.upload-text').textContent = '✅ ファイルが選択されました';
                this.uploadArea.querySelector('.upload-subtext').textContent = 
                    '別のファイルを選択する場合はクリックしてください';
            }
            
            async startUpload() {
                if (!this.selectedFile) {
                    alert('ファイルを選択してください。');
                    return;
                }
                
                // UI状態変更
                this.uploadBtn.disabled = true;
                this.uploadBtn.innerHTML = '⏳ 処理中...';
                this.progressContainer.style.display = 'block';
                this.resultArea.style.display = 'none';
                
                try {
                    await this.uploadFile();
                } catch (error) {
                    this.showError('アップロードに失敗しました: ' + error.message);
                } finally {
                    this.uploadBtn.disabled = false;
                    this.uploadBtn.innerHTML = '🚀 インポート開始';
                }
            }
            
            async uploadFile() {
                const formData = new FormData();
                formData.append('csv_file', this.selectedFile);
                formData.append('encoding', document.getElementById('encoding').value);
                formData.append('delimiter', document.getElementById('delimiter').value);
                
                // プログレス更新開始
                this.updateProgress(10, 'ファイルをアップロード中...');
                
                const response = await fetch('../api/test_upload.php', {
                    method: 'POST',
                    body: formData
                });
                
                this.updateProgress(30, 'CSVファイルを解析中...');
                
                if (!response.ok) {
                    throw new Error(`サーバーエラー: ${response.status}`);
                }
                
                this.updateProgress(50, 'データベースに保存中...');
                
                const result = await response.json();
                
                this.updateProgress(100, '完了');
                
                // 結果表示
                setTimeout(() => {
                    this.showResult(result);
                }, 500);
            }
            
            updateProgress(percent, message) {
                this.progressBar.style.width = `${percent}%`;
                this.progressBar.textContent = `${percent}%`;
                this.progressText.textContent = message;
            }
            
            showResult(result) {
                this.progressContainer.style.display = 'none';
                this.resultArea.style.display = 'block';
                
                if (result.success) {
                    this.resultContent.innerHTML = this.generateSuccessResult(result);
                } else {
                    this.resultContent.innerHTML = this.generateErrorResult(result);
                }
                
                // 結果エリアにスクロール
                this.resultArea.scrollIntoView({ behavior: 'smooth' });
            }
            
            generateSuccessResult(result) {
                const stats = result.stats;
                return `
                    <div class="alert alert-success">
                        <h4 class="alert-heading">✅ インポート完了</h4>
                        <p class="mb-0">CSVファイルの取り込みが正常に完了しました。</p>
                    </div>
                    
                    <div class="row text-center mb-4">
                        <div class="col-md-3">
                            <div class="card border-primary">
                                <div class="card-body">
                                    <h3 class="text-primary">${stats.success_rows}</h3>
                                    <small>成功件数</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card border-info">
                                <div class="card-body">
                                    <h3 class="text-info">${stats.new_companies}</h3>
                                    <small>新規企業</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card border-success">
                                <div class="card-body">
                                    <h3 class="text-success">${stats.new_users}</h3>
                                    <small>新規利用者</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card border-warning">
                                <div class="card-body">
                                    <h3 class="text-warning">${stats.duplicate_orders}</h3>
                                    <small>重複スキップ</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-center">
                        <a href="../index.php" class="btn btn-primary btn-lg me-3">
                            📊 ダッシュボードで確認
                        </a>
                        <a href="invoice_generate.php" class="btn btn-success btn-lg">
                            📄 請求書作成に進む
                        </a>
                    </div>
                `;
            }
            
            generateErrorResult(result) {
                let errorList = '';
                if (result.errors && result.errors.length > 0) {
                    errorList = '<h6>エラー詳細:</h6><ul>';
                    result.errors.slice(0, 10).forEach(error => {
                        errorList += `<li><strong>${error.context}:</strong> ${error.message}</li>`;
                    });
                    if (result.errors.length > 10) {
                        errorList += `<li>他 ${result.errors.length - 10} 件のエラー</li>`;
                    }
                    errorList += '</ul>';
                }
                
                return `
                    <div class="alert alert-danger">
                        <h4 class="alert-heading">❌ インポートエラー</h4>
                        <p>${result.message || 'CSVファイルの処理中にエラーが発生しました。'}</p>
                        ${errorList}
                    </div>
                    
                    <div class="text-center">
                        <button class="btn btn-warning" onclick="location.reload()">
                            🔄 もう一度試す
                        </button>
                    </div>
                `;
            }
            
            showError(message) {
                this.progressContainer.style.display = 'none';
                this.resultArea.style.display = 'block';
                
                this.resultContent.innerHTML = `
                    <div class="alert alert-danger">
                        <h4 class="alert-heading">❌ エラー</h4>
                        <p>${message}</p>
                    </div>
                    
                    <div class="text-center">
                        <button class="btn btn-warning" onclick="location.reload()">
                            🔄 もう一度試す
                        </button>
                    </div>
                `;
            }
        }
        
        // テンプレートダウンロード機能
        function downloadTemplate() {
            const fields = <?= json_encode(array_keys($csvTemplate['fields'])) ?>;
            const csvContent = fields.join(',') + '\n';
            
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = 'smiley_csv_template.csv';
            link.click();
        }
        
        // 初期化
        document.addEventListener('DOMContentLoaded', function() {
            new SmileyCSVUploader();
        });
    </script>
</body>
</html>
