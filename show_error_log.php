<?php
/**
 * エラーログ表示スクリプト
 */
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>エラーログ</title>
    <style>
        body { font-family: monospace; margin: 20px; }
        .error { color: red; }
        .warning { color: orange; }
        .info { color: blue; }
        pre { background: #f5f5f5; padding: 10px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>PHPエラーログ</h1>

<?php
// 可能性のあるエラーログのパス
$logPaths = [
    __DIR__ . '/error_log',
    __DIR__ . '/../error_log',
    ini_get('error_log'),
    '/home/hipper-gif/twinklemark.xsrv.jp/public_html/Smiley/meal-delivery/billing-system/error_log',
    '/var/log/php-error.log',
    '/tmp/php-error.log'
];

$foundLog = false;

foreach ($logPaths as $path) {
    if (empty($path)) continue;

    echo "<h2>チェック中: {$path}</h2>";

    if (file_exists($path)) {
        $foundLog = true;
        echo "<p style='color:green;'>✓ ファイルが見つかりました</p>";

        $fileSize = filesize($path);
        echo "<p>ファイルサイズ: " . number_format($fileSize) . " bytes</p>";

        if ($fileSize > 0) {
            // 最新1000行を表示
            $lines = file($path);
            $totalLines = count($lines);
            $displayLines = array_slice($lines, -1000);

            echo "<p>総行数: {$totalLines} 行 (最新1000行を表示)</p>";
            echo "<pre>";

            foreach ($displayLines as $line) {
                $line = htmlspecialchars($line);
                if (stripos($line, 'error') !== false) {
                    echo "<span class='error'>{$line}</span>";
                } elseif (stripos($line, 'warning') !== false) {
                    echo "<span class='warning'>{$line}</span>";
                } elseif (stripos($line, 'invoice') !== false || stripos($line, '===') !== false) {
                    echo "<span class='info'>{$line}</span>";
                } else {
                    echo $line;
                }
            }
            echo "</pre>";
        } else {
            echo "<p style='color:orange;'>ファイルは空です</p>";
        }

        echo "<hr>";
    } else {
        echo "<p style='color:gray;'>✗ ファイルが見つかりません</p>";
        echo "<hr>";
    }
}

if (!$foundLog) {
    echo "<h2 style='color:red;'>エラーログが見つかりませんでした</h2>";
    echo "<p>PHPエラーログの設定:</p>";
    echo "<pre>";
    echo "error_log = " . ini_get('error_log') . "\n";
    echo "log_errors = " . ini_get('log_errors') . "\n";
    echo "display_errors = " . ini_get('display_errors') . "\n";
    echo "</pre>";
}
?>

</body>
</html>
