<?php
/**
 * システム健全性チェック（修正版）
 * Smiley配食システム専用
 * 
 * 問題修正点：
 * 1. テーブル存在チェックの正確性向上
 * 2. データベース権限考慮
 * 3. エラーハンドリング強化
 * 4. デバッグ情報詳細化
 */

require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/SecurityHelper.php';

// セキュリティヘッダー設定
SecurityHelper::setSecurityHeaders();

// システムチェック実行
$systemStatus = checkSystemHealth();
$overallStatus = $systemStatus['overall_status'];
$checkTime = date('Y-m-d H:i:s');

function checkSystemHealth() {
    $results = [
        'overall_status' => true,
        'database_connection' => checkDatabaseConnection(),
        'database_tables' => checkDatabaseTables(),
        'system_config' => checkSystemConfig(),
        'system_info' => getSystemInfo()
    ];
    
    // 全体ステータス判定
    $results['overall_status'] = $results['database_connection']['status'] && 
                                $results['database_tables']['status'];
    
    return $results;
}

function checkDatabaseConnection() {
    try {
        // Singletonパターンのため getInstance() を使用
        $db = Database::getInstance();
        $db->query('SELECT 1');
        
        return [
            'status' => true,
            'message' => 'データベース接続テスト成功',
            'environment' => ENVIRONMENT ?? 'unknown'
        ];
    } catch (Exception $e) {
        return [
            'status' => false,
            'message' => 'データベース接続エラー: ' . $e->getMessage(),
            'environment' => ENVIRONMENT ?? 'unknown'
        ];
    }
}

function checkDatabaseTables() {
    // 必要テーブル一覧（Smiley配食システム仕様）
    $requiredTables = [
        'users' => '利用者マスタ',
        'companies' => '配達先企業マスタ', 
        'departments' => '配達先部署マスタ',
        'orders' => '注文データ',
        'products' => 'メニューマスタ',
        'suppliers' => '給食業者マスタ',
        'invoices' => '請求書',
        'payments' => '支払記録',
        'import_logs' => 'インポートログ'
    ];
    
    try {
        // Singletonパターンのため getInstance() を使用
        $db = Database::getInstance();
        
        // 方法1: SHOW TABLES を使用
        $stmt = $db->query('SHOW TABLES');
        $existingTables = [];
        while ($row = $stmt->fetch()) {
            $tableName = array_values($row)[0];
            $existingTables[] = $tableName;
        }
        
        // テーブル存在チェック
        $tableStatus = [];
        $existingCount = 0;
        
        foreach ($requiredTables as $tableName => $description) {
            $exists = in_array($tableName, $existingTables);
            if ($exists) {
                $existingCount++;
            }
            
            $tableStatus[$tableName] = [
                'exists' => $exists,
                'description' => $description,
                'status' => $exists ? '存在' : '不存在'
            ];
        }
        
        $totalRequired = count($requiredTables);
        $completionRate = $totalRequired > 0 ? round(($existingCount / $totalRequired) * 100) : 0;
        
        // 方法2: INFORMATION_SCHEMA を使用してダブルチェック（prepare不使用版）
        $infoSchemaCheck = [];
        foreach ($requiredTables as $tableName => $description) {
            try {
                $sql = "SELECT COUNT(*) as table_count 
                        FROM INFORMATION_SCHEMA.TABLES 
                        WHERE TABLE_SCHEMA = '" . DB_NAME . "' 
                        AND TABLE_NAME = '" . $tableName . "'";
                $stmt = $db->query($sql);
                $result = $stmt->fetch();
                $infoSchemaCheck[$tableName] = $result['table_count'] > 0;
            } catch (Exception $e) {
                $infoSchemaCheck[$tableName] = false;
            }
        }
        
        return [
            'status' => $existingCount === $totalRequired,
            'existing_count' => $existingCount,
            'total_required' => $totalRequired,
            'completion_rate' => $completionRate,
            'missing_tables' => array_keys(array_filter($requiredTables, function($table, $key) use ($tableStatus) {
                return !$tableStatus[$key]['exists'];
            }, ARRAY_FILTER_USE_BOTH)),
            'table_status' => $tableStatus,
            'existing_tables_list' => $existingTables,
            'info_schema_check' => $infoSchemaCheck,
            'debug_info' => [
                'database_name' => DB_NAME,
                'show_tables_count' => count($existingTables),
                'required_tables_count' => count($requiredTables)
            ]
        ];
        
    } catch (Exception $e) {
        return [
            'status' => false,
            'error' => 'テーブルチェックエラー: ' . $e->getMessage(),
            'existing_count' => 0,
            'total_required' => count($requiredTables),
            'completion_rate' => 0,
            'missing_tables' => array_keys($requiredTables),
            'table_status' => [],
            'debug_info' => [
                'error_details' => $e->getMessage(),
                'error_code' => $e->getCode()
            ]
        ];
    }
}

function checkSystemConfig() {
    return [
        'database_host' => [
            'value' => DB_HOST,
            'status' => !empty(DB_HOST) ? 'OK' : 'Error'
        ],
        'database_name' => [
            'value' => DB_NAME,
            'status' => !empty(DB_NAME) ? 'OK' : 'Error'
        ],
        'database_user' => [
            'value' => DB_USER,
            'status' => !empty(DB_USER) ? 'OK' : 'Error'
        ],
        'password_set' => [
            'value' => !empty(DB_PASS) ? '設定済み' : '未設定',
            'status' => !empty(DB_PASS) ? 'OK' : 'Warning'
        ],
        'environment' => [
            'value' => ENVIRONMENT ?? 'unknown',
            'status' => ENVIRONMENT ?? 'test'
        ],
        'debug_mode' => [
            'value' => DEBUG_MODE ? 'ON' : 'OFF',
            'status' => DEBUG_MODE ? 'ON' : 'OFF'
        ]
    ];
}

function getSystemInfo() {
    try {
        // Singletonパターンのため getInstance() を使用
        $db = Database::getInstance();
        $stmt = $db->query('SELECT VERSION() as mysql_version');
        $mysqlVersion = $stmt->fetch()['mysql_version'] ?? 'Unknown';
    } catch (Exception $e) {
        $mysqlVersion = 'Error: ' . $e->getMessage();
    }
    
    return [
        'mysql_version' => $mysqlVersion,
        'database_name' => DB_NAME,
        'charset' => 'utf8mb4',
        'php_version' => PHP_VERSION,
        'memory_limit' => ini_get('memory_limit'),
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'max_execution_time' => ini_get('max_execution_time')
    ];
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🍱 システム健全性チェック - Smiley配食システム</title>
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
            max-width: 1200px;
        }
        .status-ok { color: #28a745; }
        .status-warning { color: #ffc107; }
        .status-error { color: #dc3545; }
        .check-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 4px solid #007bff;
        }
        .table-status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
            margin-top: 15px;
        }
        .table-status-item {
            background: white;
            padding: 10px;
            border-radius: 8px;
            border-left: 4px solid #dee2e6;
            font-size: 0.9rem;
        }
        .table-status-item.exists {
            border-left-color: #28a745;
            background-color: #f8fff9;
        }
        .table-status-item.missing {
            border-left-color: #dc3545;
            background-color: #fff8f8;
        }
        .progress-custom {
            height: 25px;
            background-color: #e9ecef;
            border-radius: 12px;
            overflow: hidden;
        }
        .overall-status {
            text-align: center;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 30px;
        }
        .overall-status.success {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
        }
        .overall-status.error {
            background: linear-gradient(135deg, #dc3545, #fd7e14);
            color: white;
        }
    </style>
</head>
<body>
    <div class="main-container">
        <!-- ヘッダー -->
        <div class="text-center mb-4">
            <h1 class="display-5 mb-2">🍱 システム健全性チェック</h1>
            <p class="text-muted">Smiley配食システムの動作状況を確認します</p>
        </div>

        <!-- 総合ステータス -->
        <div class="overall-status <?php echo $overallStatus ? 'success' : 'error'; ?>">
            <h2 class="mb-0">
                <?php if ($overallStatus): ?>
                    ✅ 総合ステータス
                    <br><strong>システムは正常に動作しています</strong>
                <?php else: ?>
                    ❌ 総合ステータス
                    <br><strong>システムに問題があります</strong>
                <?php endif; ?>
            </h2>
            <p class="mb-0 mt-2">チェック実行時刻: <?php echo $checkTime; ?></p>
        </div>

        <!-- 詳細チェック結果タイトル -->
        <h3 class="mb-4">📊 詳細チェック結果</h3>

        <!-- データベース接続チェック -->
        <div class="check-section">
            <h4 class="mb-3">
                <i class="bi bi-database"></i> データベース接続
            </h4>
            <div class="row">
                <div class="col-md-3">
                    <strong>ステータス:</strong>
                    <span class="<?php echo $systemStatus['database_connection']['status'] ? 'status-ok' : 'status-error'; ?>">
                        <strong><?php echo $systemStatus['database_connection']['status'] ? '接続成功' : '接続失敗'; ?></strong>
                    </span>
                </div>
                <div class="col-md-6">
                    <strong>メッセージ:</strong> <?php echo htmlspecialchars($systemStatus['database_connection']['message']); ?>
                </div>
                <div class="col-md-3">
                    <strong>環境:</strong> <span class="badge bg-info"><?php echo $systemStatus['database_connection']['environment']; ?></span>
                </div>
            </div>
        </div>

        <!-- データベーステーブルチェック -->
        <div class="check-section">
            <h4 class="mb-3">
                <i class="bi bi-table"></i> データベーステーブル
            </h4>
            
            <?php if ($systemStatus['database_tables']['status']): ?>
                <div class="row">
                    <div class="col-md-3">
                        <strong>ステータス:</strong> <span class="status-ok"><strong>全テーブル存在</strong></span>
                    </div>
                    <div class="col-md-9">
                        <strong><?php echo $systemStatus['database_tables']['existing_count']; ?>/<?php echo $systemStatus['database_tables']['total_required']; ?> テーブル存在</strong>
                        <div class="progress progress-custom mt-2">
                            <div class="progress-bar bg-success" style="width: <?php echo $systemStatus['database_tables']['completion_rate']; ?>%">
                                <?php echo $systemStatus['database_tables']['completion_rate']; ?>% 完了
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="row">
                    <div class="col-md-3">
                        <strong>ステータス:</strong> <span class="status-error"><strong><?php echo $systemStatus['database_tables']['existing_count']; ?>/<?php echo $systemStatus['database_tables']['total_required']; ?> テーブル存在</strong></span>
                    </div>
                    <div class="col-md-9">
                        <div class="progress progress-custom">
                            <div class="progress-bar bg-danger" style="width: <?php echo $systemStatus['database_tables']['completion_rate']; ?>%">
                                <?php echo $systemStatus['database_tables']['completion_rate']; ?>% 完了
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if (!empty($systemStatus['database_tables']['missing_tables'])): ?>
                    <div class="alert alert-danger mt-3">
                        <strong>不足テーブル:</strong> <?php echo implode(', ', $systemStatus['database_tables']['missing_tables']); ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <!-- テーブル名状態（詳細表示） -->
            <?php if (!empty($systemStatus['database_tables']['table_status'])): ?>
                <div class="table-status-grid">
                    <?php foreach ($systemStatus['database_tables']['table_status'] as $tableName => $info): ?>
                        <div class="table-status-item <?php echo $info['exists'] ? 'exists' : 'missing'; ?>">
                            <strong><?php echo $tableName; ?></strong><br>
                            <small class="text-muted"><?php echo $info['description']; ?></small><br>
                            <span class="<?php echo $info['exists'] ? 'status-ok' : 'status-error'; ?>">
                                <strong><?php echo $info['status']; ?></strong>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- システム設定チェック -->
        <div class="check-section">
            <h4 class="mb-3">
                <i class="bi bi-gear"></i> システム設定
            </h4>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>設定項目</th>
                            <th>値</th>
                            <th>状態</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($systemStatus['system_config'] as $key => $config): ?>
                            <tr>
                                <td><strong><?php echo str_replace('_', ' ', ucwords($key, '_')); ?></strong></td>
                                <td><code><?php echo htmlspecialchars($config['value']); ?></code></td>
                                <td>
                                    <span class="badge bg-<?php echo $config['status'] === 'OK' ? 'success' : ($config['status'] === 'Warning' ? 'warning' : 'secondary'); ?>">
                                        <?php echo $config['status']; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- システム情報 -->
        <div class="check-section">
            <h4 class="mb-3">
                <i class="bi bi-info-circle"></i> システム情報
            </h4>
            <div class="table-responsive">
                <table class="table table-sm">
                    <tbody>
                        <?php foreach ($systemStatus['system_info'] as $key => $value): ?>
                            <tr>
                                <td><strong><?php echo str_replace('_', ' ', ucwords($key, '_')); ?></strong></td>
                                <td><code><?php echo htmlspecialchars($value); ?></code></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- アクションボタン -->
        <div class="text-center mt-4">
            <button onclick="location.reload()" class="btn btn-primary btn-lg me-3">
                <i class="bi bi-arrow-clockwise"></i> 🔄 再チェック
            </button>
            <a href="https://sv16114.xserver.jp:2083/frontend/paper_lantern/sql/index.html" target="_blank" class="btn btn-info btn-lg">
                <i class="bi bi-tools"></i> 🔧 phpMyAdmin で修復
            </a>
        </div>

        <!-- トラブルシューティング -->
        <?php if (!$overallStatus): ?>
            <div class="mt-5">
                <h4 class="text-danger">🛠️ トラブルシューティング</h4>
                <div class="alert alert-warning">
                    <h5>テーブルの問題</h5>
                    <p>以下のテーブルが不足しています。phpMyAdminでテーブル作成SQLを実行してください：</p>
                    <strong>不足テーブル:</strong> <?php echo implode(', ', $systemStatus['database_tables']['missing_tables'] ?? []); ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- デバッグ情報（DEBUG_MODEの場合のみ表示） -->
        <?php if (DEBUG_MODE && !empty($systemStatus['database_tables']['debug_info'])): ?>
            <div class="mt-4">
                <h5 class="text-muted">🔍 デバッグ情報</h5>
                <pre class="bg-light p-3 rounded"><code><?php echo json_encode($systemStatus['database_tables']['debug_info'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE); ?></code></pre>
            </div>
        <?php endif; ?>

        <!-- フッター -->
        <div class="text-center mt-5 pt-4 border-top">
            <p class="text-muted mb-0">
                <strong>Smiley配食事業 請求書管理システム v1.0.0</strong><br>
                © 2025 Smiley配食事業. All rights reserved.
            </p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
