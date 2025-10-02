<?php
/**
 * performance_check.php - パフォーマンス診断ツール（簡略版）
 * 配置: /api/performance_check.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

$startTime = microtime(true);
$results = array();

// 1. 基本情報
$results['php_version'] = phpversion();
$results['server_software'] = isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : 'Unknown';
$results['current_time'] = date('Y-m-d H:i:s');

// 2. config/database.php読み込み
$configStart = microtime(true);
try {
    require_once __DIR__ . '/../config/database.php';
    $results['config_load_time'] = round((microtime(true) - $configStart) * 1000, 2);
    $results['config_status'] = 'OK';
    $results['db_host'] = defined('DB_HOST') ? DB_HOST : 'undefined';
    $results['db_name'] = defined('DB_NAME') ? DB_NAME : 'undefined';
    $results['db_user'] = defined('DB_USER') ? DB_USER : 'undefined';
    $results['environment'] = defined('ENVIRONMENT') ? ENVIRONMENT : 'undefined';
} catch (Exception $e) {
    $results['config_load_time'] = round((microtime(true) - $configStart) * 1000, 2);
    $results['config_status'] = 'ERROR';
    $results['config_error'] = $e->getMessage();
}

// 3. データベース接続テスト
$dbStart = microtime(true);
try {
    $db = Database::getInstance();
    $results['db_connect_time'] = round((microtime(true) - $dbStart) * 1000, 2);
    $results['db_status'] = 'OK';
    
    // 簡単なクエリ
    $queryStart = microtime(true);
    $stmt = $db->query("SELECT 1");
    $results['db_query_time'] = round((microtime(true) - $queryStart) * 1000, 2);
    
} catch (Exception $e) {
    $results['db_connect_time'] = round((microtime(true) - $dbStart) * 1000, 2);
    $results['db_status'] = 'ERROR';
    $results['db_error'] = $e->getMessage();
}

// 4. テーブル確認
if (isset($db)) {
    $tables = array('companies', 'users', 'invoices', 'payments');
    foreach ($tables as $table) {
        try {
            $stmt = $db->query("SELECT COUNT(*) as cnt FROM " . $table);
            $result = $stmt->fetch();
            $results['table_' . $table] = $result['cnt'];
        } catch (Exception $e) {
            $results['table_' . $table] = 'ERROR';
        }
    }
}

// 5. メモリ使用量
$results['memory_usage'] = round(memory_get_usage(true) / 1024 / 1024, 2);
$results['memory_peak'] = round(memory_get_peak_usage(true) / 1024 / 1024, 2);

// 6. 総実行時間
$results['total_time'] = round((microtime(true) - $startTime) * 1000, 2);

// 7. 診断判定
$diagnosis = array();

if (isset($results['db_connect_time']) && $results['db_connect_time'] > 3000) {
    $diagnosis[] = 'データベース接続が非常に遅い (' . $results['db_connect_time'] . 'ms)';
}

if (isset($results['config_load_time']) && $results['config_load_time'] > 1000) {
    $diagnosis[] = 'config/database.phpの読み込みが遅い (' . $results['config_load_time'] . 'ms)';
}

if ($results['db_status'] === 'ERROR') {
    $diagnosis[] = 'データベース接続エラー: ' . $results['db_error'];
}

if ($results['total_time'] > 5000) {
    $diagnosis[] = '総実行時間が非常に遅い (' . $results['total_time'] . 'ms)';
}

if (isset($results['db_host']) && $results['db_host'] === 'localhost') {
    $diagnosis[] = '警告: DB_HOSTがlocalhostです。エックスサーバーでは専用のMySQLホスト名を使用してください';
}

if (empty($diagnosis)) {
    $diagnosis[] = '重大な問題は検出されませんでした';
}

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>パフォーマンス診断</title>
    <style>
        body {
            font-family: sans-serif;
            background: #f5f5f5;
            padding: 20px;
            margin: 0;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #2c3e50;
            border-bottom: 3px solid #3498db;
            padding-bottom: 10px;
        }
        h2 {
            color: #34495e;
            margin-top: 30px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 8px;
        }
        .total-time {
            font-size: 48px;
            font-weight: bold;
            text-align: center;
            margin: 20px 0;
            padding: 20px;
            border-radius: 8px;
        }
        .time-good { color: #27ae60; background: #d5f4e6; }
        .time-warning { color: #f39c12; background: #fef5e7; }
        .time-bad { color: #e74c3c; background: #fadbd8; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #ecf0f1;
            font-weight: bold;
        }
        .status-ok { color: #27ae60; font-weight: bold; }
        .status-error { color: #e74c3c; font-weight: bold; }
        .diagnosis {
            background: #fff3cd;
            border-left: 4px solid #f39c12;
            padding: 15px;
            margin: 10px 0;
            border-radius: 4px;
        }
        .diagnosis-critical {
            background: #f8d7da;
            border-left-color: #e74c3c;
        }
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>パフォーマンス診断レポート</h1>
        <p>実行日時: <?php echo $results['current_time']; ?></p>
        
        <?php
        $timeClass = 'time-good';
        if ($results['total_time'] > 5000) {
            $timeClass = 'time-bad';
        } elseif ($results['total_time'] > 2000) {
            $timeClass = 'time-warning';
        }
        ?>
        
        <div class="total-time <?php echo $timeClass; ?>">
            <?php echo $results['total_time']; ?>ms
        </div>
        
        <h2>診断結果</h2>
        <?php foreach ($diagnosis as $diag): ?>
            <div class="diagnosis <?php echo strpos($diag, 'エラー') !== false || strpos($diag, '非常に') !== false ? 'diagnosis-critical' : ''; ?>">
                <?php echo htmlspecialchars($diag); ?>
            </div>
        <?php endforeach; ?>
        
        <h2>詳細情報</h2>
        <table>
            <tr>
                <th>項目</th>
                <th>値</th>
            </tr>
            <tr>
                <td>PHP バージョン</td>
                <td><?php echo $results['php_version']; ?></td>
            </tr>
            <tr>
                <td>環境</td>
                <td><?php echo isset($results['environment']) ? $results['environment'] : 'N/A'; ?></td>
            </tr>
            <tr>
                <td>config読み込み</td>
                <td class="<?php echo $results['config_status'] === 'OK' ? 'status-ok' : 'status-error'; ?>">
                    <?php echo $results['config_status']; ?> 
                    (<?php echo $results['config_load_time']; ?>ms)
                </td>
            </tr>
            <tr>
                <td>DB接続</td>
                <td class="<?php echo $results['db_status'] === 'OK' ? 'status-ok' : 'status-error'; ?>">
                    <?php echo $results['db_status']; ?>
                    <?php if (isset($results['db_connect_time'])): ?>
                        (<?php echo $results['db_connect_time']; ?>ms)
                    <?php endif; ?>
                </td>
            </tr>
            <?php if (isset($results['db_query_time'])): ?>
            <tr>
                <td>DBクエリ実行</td>
                <td><?php echo $results['db_query_time']; ?>ms</td>
            </tr>
            <?php endif; ?>
            <tr>
                <td>メモリ使用量</td>
                <td><?php echo $results['memory_usage']; ?>MB (ピーク: <?php echo $results['memory_peak']; ?>MB)</td>
            </tr>
        </table>
        
        <h2>データベース設定</h2>
        <table>
            <tr>
                <th>項目</th>
                <th>値</th>
            </tr>
            <tr>
                <td>DB_HOST</td>
                <td><code><?php echo isset($results['db_host']) ? htmlspecialchars($results['db_host']) : 'N/A'; ?></code></td>
            </tr>
            <tr>
                <td>DB_NAME</td>
                <td><code><?php echo isset($results['db_name']) ? htmlspecialchars($results['db_name']) : 'N/A'; ?></code></td>
            </tr>
            <tr>
                <td>DB_USER</td>
                <td><code><?php echo isset($results['db_user']) ? htmlspecialchars($results['db_user']) : 'N/A'; ?></code></td>
            </tr>
        </table>
        
        <?php if (isset($results['table_companies'])): ?>
        <h2>テーブル状態</h2>
        <table>
            <tr>
                <th>テーブル</th>
                <th>データ件数</th>
            </tr>
            <tr>
                <td>companies</td>
                <td><?php echo $results['table_companies']; ?></td>
            </tr>
            <tr>
                <td>users</td>
                <td><?php echo $results['table_users']; ?></td>
            </tr>
            <tr>
                <td>invoices</td>
                <td><?php echo $results['table_invoices']; ?></td>
            </tr>
            <tr>
                <td>payments</td>
                <td><?php echo $results['table_payments']; ?></td>
            </tr>
        </table>
        <?php endif; ?>
        
        <?php if ($results['db_status'] === 'ERROR'): ?>
        <h2>エラー詳細</h2>
        <div class="diagnosis diagnosis-critical">
            <strong>データベース接続エラー:</strong><br>
            <?php echo htmlspecialchars($results['db_error']); ?>
        </div>
        
        <h3>対応方法</h3>
        <ol>
            <li>エックスサーバーのサーバーパネルにログイン</li>
            <li>「MySQL設定」を開く</li>
            <li>「MySQLホスト名」を確認（例: mysql1234.xsrv.jp）</li>
            <li>config/database.phpの<code>DB_HOST</code>を正しいホスト名に変更</li>
            <li>データベース名、ユーザー名、パスワードが正しいか確認</li>
        </ol>
        <?php endif; ?>
        
        <h2>推奨対応</h2>
        <div style="background: #e8f4f8; padding: 15px; border-radius: 4px; margin-top: 20px;">
            <?php if ($results['total_time'] > 5000): ?>
                <p><strong>緊急対応が必要です:</strong></p>
                <ul>
                    <li>DB_HOSTを確認してください（localhostの場合は変更必須）</li>
                    <li>config/database.phpを軽量版に差し替えてください</li>
                    <li>データベース接続情報が正しいか確認してください</li>
                </ul>
            <?php elseif ($results['total_time'] > 2000): ?>
                <p><strong>最適化を推奨します:</strong></p>
                <ul>
                    <li>config/database.phpの不要な処理を削除</li>
                    <li>PaymentManagerを軽量版に更新</li>
                </ul>
            <?php else: ?>
                <p><strong>パフォーマンスは良好です</strong></p>
                <p>特に対応は不要ですが、他のページも確認してください。</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
