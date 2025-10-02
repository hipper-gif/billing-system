<?php
/**
 * エラーログ確認ツール
 * 最新のエラーログを表示
 */

header('Content-Type: text/html; charset=utf-8');

$logFiles = [
    'System Error Log' => '../logs/error.log',
    'PHP Error Log' => ini_get('error_log'),
    'Apache Error Log (if accessible)' => '/var/log/apache2/error.log'
];

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>エラーログ確認 - Smiley配食システム</title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            margin: 20px;
            background: #1e1e1e;
            color: #d4d4d4;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: #252526;
            padding: 20px;
            border-radius: 8px;
        }
        h1 {
            color: #4CAF50;
            border-bottom: 2px solid #4CAF50;
            padding-bottom: 10px;
        }
        h2 {
            color: #2196F3;
            margin-top: 30px;
        }
        .log-section {
            margin: 20px 0;
            background: #1e1e1e;
            padding: 15px;
            border-radius: 4px;
            border-left: 4px solid #4CAF50;
        }
        .log-content {
            white-space: pre-wrap;
            word-wrap: break-word;
            font-size: 12px;
            line-height: 1.5;
            max-height: 400px;
            overflow-y: auto;
        }
        .error-line {
            color: #f44336;
        }
        .warning-line {
            color: #FFC107;
        }
        .info-line {
            color: #2196F3;
        }
        .not-found {
            color: #999;
            font-style: italic;
        }
        .timestamp {
            color: #4CAF50;
        }
        pre {
            margin: 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 エラーログ確認ツール</h1>
        <p>最新のエラーログを表示します（最新50行）</p>
        
        <?php foreach ($logFiles as $title => $logPath): ?>
            <div class="log-section">
                <h2><?= htmlspecialchars($title) ?></h2>
                <p style="color: #999; font-size: 12px;">ログファイル: <?= htmlspecialchars($logPath) ?></p>
                
                <div class="log-content">
                    <?php
                    if (file_exists($logPath) && is_readable($logPath)) {
                        $lines = file($logPath);
                        if ($lines !== false) {
                            // 最新50行を取得
                            $recentLines = array_slice($lines, -50);
                            
                            foreach ($recentLines as $line) {
                                $line = htmlspecialchars($line);
                                
                                // エラーレベルで色分け
                                if (stripos($line, 'error') !== false || stripos($line, 'fatal') !== false) {
                                    echo '<div class="error-line">' . $line . '</div>';
                                } elseif (stripos($line, 'warning') !== false) {
                                    echo '<div class="warning-line">' . $line . '</div>';
                                } elseif (preg_match('/\d{4}-\d{2}-\d{2}/', $line)) {
                                    echo '<div class="timestamp">' . $line . '</div>';
                                } else {
                                    echo '<div>' . $line . '</div>';
                                }
                            }
                            
                            echo '<hr style="border-color: #444; margin: 10px 0;">';
                            echo '<p style="color: #4CAF50;">✅ 合計 ' . count($lines) . ' 行（最新50行を表示）</p>';
                        } else {
                            echo '<p class="not-found">❌ ログファイルを読み込めませんでした</p>';
                        }
                    } else {
                        echo '<p class="not-found">❌ ログファイルが見つからないか、読み込み権限がありません</p>';
                    }
                    ?>
                </div>
            </div>
        <?php endforeach; ?>
        
        <div class="log-section">
            <h2>📊 PHP設定情報</h2>
            <div class="log-content">
                <pre><?php
                echo "display_errors: " . ini_get('display_errors') . "\n";
                echo "error_reporting: " . error_reporting() . "\n";
                echo "log_errors: " . ini_get('log_errors') . "\n";
                echo "error_log: " . ini_get('error_log') . "\n";
                echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "\n";
                echo "post_max_size: " . ini_get('post_max_size') . "\n";
                echo "memory_limit: " . ini_get('memory_limit') . "\n";
                echo "max_execution_time: " . ini_get('max_execution_time') . "\n";
                ?></pre>
            </div>
        </div>
        
        <div style="margin-top: 30px; padding: 15px; background: #1e3a1e; border-radius: 4px;">
            <h3 style="color: #4CAF50; margin-top: 0;">💡 トラブルシューティング</h3>
            <ul style="line-height: 1.8;">
                <li>500エラーの場合: 上記のエラーログでPHPのfatal errorやparse errorを確認</li>
                <li>ファイルが見つからない: パス設定やrequire_onceを確認</li>
                <li>データベース接続エラー: config/database.phpの設定を確認</li>
                <li>クラスが見つからない: オートローダーの設定を確認</li>
            </ul>
        </div>
        
        <div style="margin-top: 20px; text-align: center; color: #666;">
            <p>最終更新: <?= date('Y-m-d H:i:s') ?></p>
            <button onclick="location.reload()" style="background: #4CAF50; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer;">
                🔄 再読み込み
            </button>
        </div>
    </div>
</body>
</html>
