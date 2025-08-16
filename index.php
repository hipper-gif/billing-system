<?php
// Smiley配食事業 請求書・集金管理システム
// 環境判定とデバッグ情報表示

error_reporting(E_ALL);
ini_set('display_errors', 1);

// 環境判定
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$environment = 'unknown';
$db_info = [];

if (strpos($host, 'twinklemark.xsrv.jp') !== false) {
    $environment = 'test';
    $db_info = [
        'host' => 'mysql1.xserver.jp',
        'database' => 'twinklemark_billing_test',
        'user' => 'twinklemark_test',
        'note' => 'テスト環境'
    ];
} elseif (strpos($host, 'tw1nkle.com') !== false) {
    $environment = 'production';
    $db_info = [
        'host' => 'mysql1.xserver.jp', 
        'database' => 'tw1nkle_billing_prod',
        'user' => 'tw1nkle_prod',
        'note' => '本番環境'
    ];
} else {
    $environment = 'local';
    $db_info = [
        'host' => 'localhost',
        'database' => 'bentosystem_local',
        'user' => 'root',
        'note' => 'ローカル開発環境'
    ];
}

// PHP環境情報
$php_info = [
    'version' => phpversion(),
    'extensions' => get_loaded_extensions(),
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'post_max_size' => ini_get('post_max_size'),
    'memory_limit' => ini_get('memory_limit'),
    'max_execution_time' => ini_get('max_execution_time')
];

// ディレクトリチェック
$directories = [
    'uploads' => 'uploads/',
    'temp' => 'temp/',
    'logs' => 'logs/',
    'cache' => 'cache/',
    'config' => 'config/',
    'api' => 'api/',
    'classes' => 'classes/',
    'assets' => 'assets/'
];

$dir_status = [];
foreach ($directories as $name => $path) {
    $dir_status[$name] = [
        'exists' => is_dir($path),
        'writable' => is_dir($path) ? is_writable($path) : false,
        'path' => $path
    ];
}

// 自動でディレクトリ作成
foreach ($directories as $name => $path) {
    if (!is_dir($path)) {
        mkdir($path, 0755, true);
        $dir_status[$name]['exists'] = true;
        $dir_status[$name]['writable'] = is_writable($path);
    }
}

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smiley配食 請求書・集金管理システム</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .status-badge-success { background-color: #d4edda; color: #155724; }
        .status-badge-warning { background-color: #fff3cd; color: #856404; }
        .status-badge-error { background-color: #f8d7da; color: #721c24; }
        .card-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .main-btn {
            min-height: 120px;
            font-size: 1.1rem;
            font-weight: bold;
            border-radius: 15px;
            transition: all 0.3s ease;
        }
        .main-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid py-4">
        <!-- ヘッダー -->
        <div class="row mb-4">
            <div class="col">
                <div class="card">
                    <div class="card-header text-center">
                        <h1 class="h3 mb-0">🍱 Smiley配食事業</h1>
                        <p class="mb-0">請求書・集金管理システム</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- 環境情報 -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">🌐 環境情報</h5>
                    </div>
                    <div class="card-body">
                        <p><strong>環境:</strong> 
                            <span class="badge <?php echo $environment === 'production' ? 'bg-danger' : ($environment === 'test' ? 'bg-warning' : 'bg-info'); ?>">
                                <?php echo strtoupper($environment); ?>
                            </span>
                        </p>
                        <p><strong>ホスト:</strong> <?php echo htmlspecialchars($host); ?></p>
                        <p><strong>URL:</strong> <br><small><?php echo htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/'); ?></small></p>
                        <p><strong>説明:</strong> <?php echo $db_info['note']; ?></p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">⚙️ PHP設定</h5>
                    </div>
                    <div class="card-body">
                        <p><strong>PHP Version:</strong> <?php echo $php_info['version']; ?></p>
                        <p><strong>Upload Max:</strong> <?php echo $php_info['upload_max_filesize']; ?></p>
                        <p><strong>Post Max:</strong> <?php echo $php_info['post_max_size']; ?></p>
                        <p><strong>Memory Limit:</strong> <?php echo $php_info['memory_limit']; ?></p>
                        <p><strong>Execution Time:</strong> <?php echo $php_info['max_execution_time']; ?>秒</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">💾 データベース設定</h5>
                    </div>
                    <div class="card-body">
                        <p><strong>Host:</strong> <?php echo $db_info['host']; ?></p>
                        <p><strong>Database:</strong> <?php echo $db_info['database']; ?></p>
                        <p><strong>User:</strong> <?php echo $db_info['user']; ?></p>
                        <div class="mt-3">
                            <button class="btn btn-sm btn-outline-primary" onclick="testDbConnection()">
                                接続テスト
                            </button>
                            <div id="db-test-result" class="mt-2"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ディレクトリ状況 -->
        <div class="row mb-4">
            <div class="col">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">📁 ディレクトリ状況</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($dir_status as $name => $status): ?>
                            <div class="col-md-3 mb-2">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-folder me-2"></i>
                                    <span class="me-2"><?php echo $name; ?></span>
                                    <?php if ($status['exists']): ?>
                                        <span class="badge status-badge-success">
                                            <i class="fas fa-check"></i>
                                        </span>
                                        <?php if (!$status['writable'] && in_array($name, ['uploads', 'temp', 'logs', 'cache'])): ?>
                                            <span class="badge status-badge-warning ms-1">読み取り専用</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge status-badge-error">
                                            <i class="fas fa-times"></i>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- メイン操作ボタン -->
        <div class="row g-3 mb-4">
            <div class="col-md-6 col-lg-3">
                <button class="btn btn-primary btn-lg w-100 main-btn" onclick="showFeature('import')">
                    <i class="fas fa-upload fa-2x mb-2"></i><br>
                    📊 データ取り込み
                </button>
            </div>
            <div class="col-md-6 col-lg-3">
                <button class="btn btn-success btn-lg w-100 main-btn" onclick="showFeature('invoice')">
                    <i class="fas fa-file-invoice fa-2x mb-2"></i><br>
                    📄 請求書作成
                </button>
            </div>
            <div class="col-md-6 col-lg-3">
                <button class="btn btn-info btn-lg w-100 main-btn" onclick="showFeature('payment')">
                    <i class="fas fa-money-bill fa-2x mb-2"></i><br>
                    💰 集金管理
                </button>
            </div>
            <div class="col-md-6 col-lg-3">
                <button class="btn btn-warning btn-lg w-100 main-btn" onclick="showFeature('report')">
                    <i class="fas fa-chart-bar fa-2x mb-2"></i><br>
                    📈 売上分析
                </button>
            </div>
        </div>

        <!-- 開発状況 -->
        <div class="row">
            <div class="col">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">🚧 開発状況</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <h6>✅ 完了済み</h6>
                            <ul class="mb-0">
                                <li>GitHub リポジトリ設定</li>
                                <li>エックスサーバー環境準備</li>
                                <li>自動デプロイ設定（準備中）</li>
                                <li>基本環境チェック機能</li>
                            </ul>
                        </div>
                        
                        <div class="alert alert-warning">
                            <h6>⏳ 開発予定</h6>
                            <ul class="mb-0">
                                <li>データベース接続・テーブル作成</li>
                                <li>CSV インポート機能</li>
                                <li>請求書生成機能</li>
                                <li>PDF出力機能</li>
                                <li>集金管理機能</li>
                            </ul>
                        </div>
                        
                        <div class="mt-3">
                            <p><strong>次のステップ:</strong> GitHub Actions設定とデータベース接続確立</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // ページ読み込み時に自動でデータベース状況チェック
        document.addEventListener('DOMContentLoaded', function() {
            checkInstallStatus();
        });

        function testDbConnection() {
            const resultDiv = document.getElementById('db-test-result');
            resultDiv.innerHTML = '<div class="spinner-border spinner-border-sm" role="status"></div> 接続テスト中...';
            
            fetch('/api/test.php?action=db')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        resultDiv.innerHTML = `
                            <span class="badge status-badge-success">接続成功</span>
                            <small class="d-block mt-1">DB: ${data.data.connection.database}</small>
                        `;
                    } else {
                        resultDiv.innerHTML = `<span class="badge status-badge-error">接続失敗</span>`;
                    }
                })
                .catch(error => {
                    resultDiv.innerHTML = `<span class="badge status-badge-error">エラー</span>`;
                });
        }

        function checkInstallStatus() {
            const statusDiv = document.getElementById('db-status');
            statusDiv.innerHTML = `
                <div class="d-flex align-items-center">
                    <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                    データベース状況を確認中...
                </div>
            `;

            fetch('/api/install.php')
                .then(response => response.json())
                .then(data => {
                    const detailsDiv = document.getElementById('db-details');
                    
                    if (data.success) {
                        if (data.data.installed) {
                            statusDiv.innerHTML = `
                                <div class="alert alert-success mb-0">
                                    <i class="fas fa-check-circle me-2"></i>
                                    <strong>データベースは正常にインストールされています</strong>
                                </div>
                            `;
                            
                            // テーブル詳細表示
                            let tableInfo = '<h6>📊 テーブル情報</h6><div class="row">';
                            for (let [table, count] of Object.entries(data.data.table_counts)) {
                                tableInfo += `
                                    <div class="col-md-4 mb-2">
                                        <div class="d-flex justify-content-between">
                                            <span>${table}:</span>
                                            <span class="badge bg-info">${count}件</span>
                                        </div>
                                    </div>
                                `;
                            }
                            tableInfo += '</div>';
                            detailsDiv.innerHTML = tableInfo;
                            detailsDiv.style.display = 'block';
                            
                        } else {
                            statusDiv.innerHTML = `
                                <div class="alert alert-warning mb-0">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <strong>データベースの初期化が必要です</strong>
                                    <br><small>不足テーブル: ${data.data.missing_tables.join(', ')}</small>
                                </div>
                            `;
                            detailsDiv.style.display = 'none';
                        }
                    } else {
                        statusDiv.innerHTML = `
                            <div class="alert alert-danger mb-0">
                                <i class="fas fa-times-circle me-2"></i>
                                <strong>データベースエラー</strong>
                                <br><small>${data.error}</small>
                            </div>
                        `;
                        detailsDiv.style.display = 'none';
                    }
                })
                .catch(error => {
                    statusDiv.innerHTML = `
                        <div class="alert alert-danger mb-0">
                            <i class="fas fa-times-circle me-2"></i>
                            <strong>接続エラー</strong>
                            <br><small>APIに接続できません</small>
                        </div>
                    `;
                });
        }

        function installDatabase() {
            const password = prompt('データベース初期化用パスワードを入力してください:');
            if (!password) return;

            if (!confirm('データベースを初期化しますか？\n既存のデータは保持されます。')) return;

            const statusDiv = document.getElementById('db-status');
            statusDiv.innerHTML = `
                <div class="d-flex align-items-center">
                    <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                    データベースを初期化中...
                </div>
            `;

            fetch('/api/install.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'install',
                    password: password
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    statusDiv.innerHTML = `
                        <div class="alert alert-success mb-0">
                            <i class="fas fa-check-circle me-2"></i>
                            <strong>データベース初期化完了</strong>
                            <br><small>${data.data.executed_statements}個のSQLを実行しました</small>
                        </div>
                    `;
                    setTimeout(() => checkInstallStatus(), 1000);
                } else {
                    statusDiv.innerHTML = `
                        <div class="alert alert-danger mb-0">
                            <i class="fas fa-times-circle me-2"></i>
                            <strong>初期化失敗</strong>
                            <br><small>${data.error}</small>
                        </div>
                    `;
                }
            })
            .catch(error => {
                statusDiv.innerHTML = `
                    <div class="alert alert-danger mb-0">
                        <i class="fas fa-times-circle me-2"></i>
                        <strong>エラー</strong>
                        <br><small>初期化処理でエラーが発生しました</small>
                    </div>
                `;
            });
        }

        function addSampleData() {
            const password = prompt('サンプルデータ追加用パスワードを入力してください:');
            if (!password) return;

            if (!confirm('サンプルデータを追加しますか？\n過去3ヶ月分の注文データが追加されます。')) return;

            fetch('/api/install.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'sample_data',
                    password: password
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('サンプルデータの追加が完了しました');
                    checkInstallStatus();
                } else {
                    alert('エラー: ' + data.error);
                }
            });
        }

        function resetDatabase() {
            const password = prompt('データベースリセット用パスワードを入力してください:');
            if (!password) return;

            if (!confirm('⚠️ 警告 ⚠️\n\nデータベースを完全にリセットしますか？\n全てのデータが削除されます！')) return;
            
            if (!confirm('本当によろしいですか？\nこの操作は取り消せません！')) return;

            fetch('/api/install.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'reset',
                    password: password
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('データベースのリセットが完了しました');
                    checkInstallStatus();
                } else {
                    alert('エラー: ' + data.error);
                }
            });
        }
        
        function showFeature(feature) {
            const features = {
                'import': 'CSV データ取り込み',
                'invoice': '請求書作成',
                'payment': '集金管理',
                'report': '売上分析'
            };
            
            alert(`${features[feature]} 機能は開発中です。\n\n現在の開発フェーズ: データベース構築完了\n次の開発: CSV インポート機能`);
        }
        
        // 環境情報表示
        console.log('Environment:', '<?php echo $environment; ?>');
        console.log('PHP Version:', '<?php echo $php_info['version']; ?>');
        console.log('Host:', '<?php echo $host; ?>');
        console.log('Base URL:', '<?php echo defined('BASE_URL') ? BASE_URL : 'undefined'; ?>');
    </script>
</body>
</html>
