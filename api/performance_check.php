<?php
/**
 * performance_check.php - パフォーマンス診断ツール
 * 配置: /api/performance_check.php
 * 
 * 全ページ遅延の原因を特定
 */

// エラー表示強制
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// 開始時間記録
$startTime = microtime(true);
$checkPoints = array();

function recordCheckpoint($label) {
    global $startTime, $checkPoints;
    $checkPoints[] = array(
        'label' => $label,
        'time' => round((microtime(true) - $startTime) * 1000, 2),
        'memory' => round(memory_get_usage(true) / 1024 / 1024, 2)
    );
}

recordCheckpoint('スクリプト開始');

// 1. 基本PHP設定確認
$phpInfo = array(
    'version' => phpversion(),
    'max_execution_time' => ini_get('max_execution_time'),
    'memory_limit' => ini_get('memory_limit'),
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'post_max_size' => ini_get('post_max_size'),
    'display_errors' => ini_get('display_errors'),
    'log_errors' => ini_get('log_errors')
);

recordCheckpoint('PHP設定確認完了');

// 2. config/database.php読み込みテスト
$configLoadStart = microtime(true);
try {
    require_once __DIR__ . '/../config/database.php';
    $configLoadTime = round((microtime(true) - $configLoadStart) * 1000, 2);
    $configStatus = 'OK';
    $configError = null;
} catch (Exception $e) {
    $configLoadTime = round((microtime(true) - $configLoadStart) * 1000, 2);
    $configStatus = 'ERROR';
    $configError = $e->getMessage();
}

recordCheckpoint('config/database.php読み込み完了: ' . $configLoadTime . 'ms');

// 3. データベース接続テスト
$dbConnectStart = microtime(true);
$dbStatus = array();
try {
    $db = Database::getInstance();
    $dbConnectTime = round((microtime(true) - $dbConnectStart) * 1000, 2);
    
    // 簡単なクエリ実行
    $queryStart = microtime(true);
    $stmt = $db->query("SELECT 1 as test");
    $queryTime = round((microtime(true) - $queryStart) * 1000, 2);
    
    $dbStatus = array(
        'status' => 'OK',
        'connect_time' => $dbConnectTime,
        'query_time' => $queryTime,
        'total_time' => $dbConnectTime + $queryTime,
        'host' => DB_HOST,
        'database' => DB_NAME,
        'user' => DB_USER
    );
} catch (Exception $e) {
    $dbConnectTime = round((microtime(true) - $dbConnectStart) * 1000, 2);
    $dbStatus = array(
        'status' => 'ERROR',
        'connect_time' => $dbConnectTime,
        'error' => $e->getMessage(),
        'host' => defined('DB_HOST') ? DB_HOST : 'undefined',
        'database' => defined('DB_NAME') ? DB_NAME : 'undefined',
        'user' => defined('DB_USER') ? DB_USER : 'undefined'
    );
}

recordCheckpoint('データベース接続テスト完了: ' . $dbConnectTime . 'ms');

// 4. テーブル存在確認
$tableCheck = array();
if ($dbStatus['status'] === 'OK') {
    try {
        $tables = array('companies', 'users', 'orders', 'invoices', 'payments', 'receipts');
        foreach ($tables as $table) {
            $stmt = $db->query("SELECT COUNT(*) as count FROM {$table}");
            $result = $stmt->fetch();
            $tableCheck[$table] = array(
                'exists' => true,
                'count' => $result['count']
            );
        }
    } catch (Exception $e) {
        $tableCheck['error'] = $e->getMessage();
    }
}

recordCheckpoint('テーブル確認完了');

// 5. ファイルシステムチェック
$fileCheck = array();
$requiredFiles = array(
    'config/database.php' => __DIR__ . '/../config/database.php',
    'classes/Database.php' => __DIR__ . '/../classes/Database.php',
    'classes/PaymentManager.php' => __DIR__ . '/../classes/PaymentManager.php',
    'pages/index.php' => __DIR__ . '/../pages/index.php',
    'pages/payments.php' => __DIR__ . '/../pages/payments.php'
);

foreach ($requiredFiles as $name => $path) {
    $fileCheck[$name] = array(
        'exists' => file_exists($path),
        'readable' => is_readable($path),
        'size' => file_exists($path) ? filesize($path) : 0
    );
}

recordCheckpoint('ファイルシステムチェック完了');

// 6. エラーログ確認
$errorLogPath = __DIR__ . '/../logs/error.log';
$errorLogInfo = array(
    'exists' => file_exists($errorLogPath),
    'size' => file_exists($errorLogPath) ? filesize($errorLogPath) : 0,
    'readable' => is_readable($errorLogPath)
);

if ($errorLogInfo['exists'] && $errorLogInfo['readable'] && $errorLogInfo['size'] > 0) {
    // 最新10行を取得
    $errorLogContent = file($errorLogPath);
    $errorLogInfo['last_10_lines'] = array_slice($errorLogContent, -10);
}

recordCheckpoint('エラーログ確認完了');

// 7. 外部リソース確認（オプション）
$externalCheck = array();
if (function_exists('curl_version')) {
    $externalResources = array(
        'Bootstrap CSS' => 'https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css',
        'Chart.js' => 'https://cdn.jsdelivr.net/npm/chart.js'
    );
    
    foreach ($externalResources as $name => $url) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        
        $start = microtime(true);
        curl_exec($ch);
        $responseTime = round((microtime(true) - $start) * 1000, 2);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $externalCheck[$name] = array(
            'url' => $url,
            'http_code' => $httpCode,
            'response_time' => $responseTime,
            'status' => $httpCode == 200 ? 'OK' : 'ERROR'
        );
    }
}

recordCheckpoint('外部リソース確認完了');

// 総実行時間
$totalTime = round((microtime(true) - $startTime) * 1000, 2);

// 診断結果判定
$diagnosis = array();

// データベース接続が遅い
if (isset($dbStatus['connect_time']) && $dbStatus['connect_time'] > 3000) {
    $diagnosis[] = array(
        'severity' => 'critical',
        'issue' => 'データベース接続が非常に遅い',
        'detail' => "接続時間: {$dbStatus['connect_time']}ms（正常: <500ms）",
        'solution' => 'DB接続情報を確認してください。特にDB_USER, DB_PASSが正しいか確認。'
    );
}

// config読み込みが遅い
if ($configLoadTime > 1000) {
    $diagnosis[] = array(
        'severity' => 'high',
        'issue' => 'config/database.phpの読み込みが遅い',
        'detail' => "読み込み時間: {$configLoadTime}ms（正常: <100ms）",
        'solution' => 'config/database.phpの不要な処理（ディレクトリ作成等）をコメントアウト'
    );
}

// エラーログが肥大化
if ($errorLogInfo['size'] > 10 * 1024 * 1024) { // 10MB以上
    $diagnosis[] = array(
        'severity' => 'medium',
        'issue' => 'エラーログファイルが肥大化',
        'detail' => "サイズ: " . round($errorLogInfo['size'] / 1024 / 1024, 2) . "MB",
        'solution' => 'logs/error.logを削除またはローテーション'
    );
}

// データベース接続エラー
if ($dbStatus['status'] === 'ERROR') {
    $diagnosis[] = array(
        'severity' => 'critical',
        'issue' => 'データベース接続エラー',
        'detail' => $dbStatus['error'],
        'solution' => 'DB_HOST, DB_NAME, DB_USER, DB_PASSを確認。phpMyAdminでログイン可能か確認。'
    );
}

// メモリ不足
$currentMemory = memory_get_usage(true) / 1024 / 1024;
if ($currentMemory > 50) {
    $diagnosis[] = array(
        'severity' => 'medium',
        'issue' => 'メモリ使用量が多い',
        'detail' => round($currentMemory, 2) . "MB使用中",
        'solution' => 'PHPのmemory_limitを増やすか、処理を最適化'
    );
}

// 問題なし
if (empty($diagnosis)) {
    $diagnosis[] = array(
        'severity' => 'info',
        'issue' => '重大な問題は検出されませんでした',
        'detail' => '外部CDNの読み込み速度を確認してください',
        'solution' => 'ブラウザの開発者ツール（F12）でネットワークタブを確認'
    );
}

// HTML出力
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>パフォーマンス診断 - Smiley配食システム</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; 
            background: #f5f5f5; 
            padding: 20px;
            line-height: 1.6;
        }
        .container { max-width: 1200px; margin: 0 auto; }
        .card { 
            background: white; 
            border-radius: 8px; 
            padding: 24px; 
            margin-bottom: 20px; 
            box-shadow: 0 2px 8px rgba(0,0,0,0.1); 
        }
        h1 { 
            color: #2c3e50; 
            margin-bottom: 10px; 
            font-size: 28px;
        }
        h2 { 
            color: #34495e; 
            margin: 20px 0 15px 0; 
            font-size: 20px;
            border-bottom: 2px solid #3498db;
            padding-bottom: 8px;
        }
        .total-time {
            font-size: 48px;
            font-weight: bold;
            color: <?php echo $totalTime > 5000 ? '#e74c3c' : ($totalTime > 2000 ? '#f39c12' : '#27ae60'); ?>;
            text-align: center;
            margin: 20px 0;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-ok { background: #d4edda; color: #155724; }
        .status-error { background: #f8d7da; color: #721c24; }
        .status-warning { background: #fff3cd; color: #856404; }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin: 15px 0;
        }
        th, td { 
            padding: 12px; 
            text-align: left; 
            border-bottom: 1px solid #dee2e6; 
        }
        th { 
            background: #f8f9fa; 
            font-weight: 600;
            color: #495057;
        }
        tr:hover { background: #f8f9fa; }
        .checkpoint { 
            display: flex; 
            justify-content: space-between; 
            padding: 8px 0; 
            border-bottom: 1px solid #eee;
        }
        .checkpoint:last-child { border-bottom: none; }
        .diagnosis-item {
            padding: 15px;
            margin: 10px 0;
            border-radius: 6px;
            border-left: 4px solid;
        }
        .diagnosis-critical { 
            background: #fee; 
            border-color: #e74c3c; 
        }
        .diagnosis-high { 
            background: #fef5e7; 
            border-color: #f39c12; 
        }
        .diagnosis-medium { 
            background: #eaf2f8; 
            border-color: #3498db; 
        }
        .diagnosis-info { 
            background: #eafaf1; 
            border-color: #27ae60; 
        }
        .diagnosis-item h3 { 
            margin-bottom: 8px; 
            font-size: 16px;
        }
        .diagnosis-item p { 
            margin: 5px 0; 
            font-size: 14px;
        }
        code { 
            background: #f4f4f4; 
            padding: 2px 6px; 
            border-radius: 3px; 
            font-family: 'Courier New', monospace;
            font-size: 13px;
        }
        .log-line {
            font-family: 'Courier New', monospace;
            font-size: 12px;
            padding: 4px;
            background: #f8f9fa;
            margin: 2px 0;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <h1>🔍 パフォーマンス診断レポート</h1>
            <p style="color: #666; margin-top: 10px;">
                実行日時: <?php echo date('Y-m-d H:i:s'); ?> | 
                環境: <?php echo defined('ENVIRONMENT') ? ENVIRONMENT : 'unknown'; ?>
            </p>
            
            <div class="total-time">
                <?php echo $totalTime; ?>ms
            </div>
            <p style="text-align: center; color: #666; margin-bottom: 20px;">
                総実行時間
                <?php if ($totalTime > 5000): ?>
                    <strong style="color: #e74c3c;">（非常に遅い）</strong>
                <?php elseif ($totalTime > 2000): ?>
                    <strong style="color: #f39c12;">（遅い）</strong>
                <?php else: ?>
                    <strong style="color: #27ae60;">（正常）</strong>
                <?php endif; ?>
            </p>
        </div>

        <!-- 診断結果 -->
        <div class="card">
            <h2>🎯 診断結果と推奨対応</h2>
            <?php foreach ($diagnosis as $item): ?>
                <div class="diagnosis-item diagnosis-<?php echo $item['severity']; ?>">
                    <h3><?php echo htmlspecialchars($item['issue']); ?></h3>
                    <p><strong>詳細:</strong> <?php echo htmlspecialchars($item['detail']); ?></p>
                    <p><strong>対応策:</strong> <?php echo htmlspecialchars($item['solution']); ?></p>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- チェックポイント -->
        <div class="card">
            <h2>⏱️ 処理時間詳細</h2>
            <?php foreach ($checkPoints as $cp): ?>
                <div class="checkpoint">
                    <span><?php echo htmlspecialchars($cp['label']); ?></span>
                    <span>
                        <strong><?php echo $cp['time']; ?>ms</strong> / 
                        <?php echo $cp['memory']; ?>MB
                    </span>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- データベース状態 -->
        <div class="card">
            <h2>💾 データベース接続状態</h2>
            <table>
                <tr>
                    <th>項目</th>
                    <th>値</th>
                </tr>
                <tr>
                    <td>ステータス</td>
                    <td>
                        <span class="status-badge status-<?php echo $dbStatus['status'] === 'OK' ? 'ok' : 'error'; ?>">
                            <?php echo $dbStatus['status']; ?>
                        </span>
                    </td>
                </tr>
                <tr>
                    <td>接続時間</td>
                    <td><?php echo $dbStatus['connect_time']; ?>ms</td>
                </tr>
                <?php if (isset($dbStatus['query_time'])): ?>
                <tr>
                    <td>クエリ実行時間</td>
                    <td><?php echo $dbStatus['query_time']; ?>ms</td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td>ホスト</td>
                    <td><code><?php echo htmlspecialchars($dbStatus['host']); ?></code></td>
                </tr>
                <tr>
                    <td>データベース名</td>
                    <td><code><?php echo htmlspecialchars($dbStatus['database']); ?></code></td>
                </tr>
                <tr>
                    <td>ユーザー名</td>
                    <td><code><?php echo htmlspecialchars($dbStatus['user']); ?></code></td>
                </tr>
                <?php if (isset($dbStatus['error'])): ?>
                <tr>
                    <td>エラー内容</td>
                    <td style="color: #e74c3c;"><code><?php echo htmlspecialchars($dbStatus['error']); ?></code></td>
                </tr>
                <?php endif; ?>
            </table>
        </div>

        <!-- テーブル状態 -->
        <?php if (!empty($tableCheck) && !isset($tableCheck['error'])): ?>
        <div class="card">
            <h2>📊 テーブル状態</h2>
            <table>
                <tr>
                    <th>テーブル名</th>
                    <th>データ件数</th>
                </tr>
                <?php foreach ($tableCheck as $table => $info): ?>
                <tr>
                    <td><code><?php echo htmlspecialchars($table); ?></code></td>
                    <td><?php echo number_format($info['count']); ?>件</td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
        <?php endif; ?>

        <!-- PHP設定 -->
        <div class="card">
            <h2>⚙️ PHP設定</h2>
            <table>
                <?php foreach ($phpInfo as $key => $value): ?>
                <tr>
                    <td><?php echo htmlspecialchars($key); ?></td>
                    <td><code><?php echo htmlspecialchars($value); ?></code></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <!-- エラーログ -->
        <?php if ($errorLogInfo['exists'] && isset($errorLogInfo['last_10_lines'])): ?>
        <div class="card">
            <h2>📝 エラーログ（最新10行）</h2>
            <p style="color: #666; margin-bottom: 10px;">
                ログサイズ: <?php echo round($errorLogInfo['size'] / 1024, 2); ?>KB
            </p>
            <?php foreach ($errorLogInfo['last_10_lines'] as $line): ?>
                <div class="log-line"><?php echo htmlspecialchars($line); ?></div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
