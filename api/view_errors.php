<?php
/**
 * エラーログ確認ツール
 * PHPエラーログの最新50行を表示
 */

$logFiles = [
    'PHP Error Log' => ini_get('error_log'),
    'Apache Error Log' => '/home/twinklemark/twinklemark.xsrv.jp/log/twinklemark.xsrv.jp/error.log',
    'Local Error Log' => __DIR__ . '/../logs/error.log'
];

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>エラーログ確認</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        pre { 
            background: #1e1e1e; 
            color: #d4d4d4; 
            padding: 20px; 
            border-radius: 5px;
            max-height: 600px;
            overflow: auto;
            font-size: 12px;
        }
        .error-line { color: #f48771; }
        .warning-line { color: #dcdcaa; }
        .fatal-line { 
            color: #ff0000; 
            font-weight: bold;
            background: #3a0000;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container mt-5">
        <h1 class="mb-4">🔍 エラーログ確認</h1>
        
        <?php foreach ($logFiles as $name => $logFile): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h5><?php echo $name; ?></h5>
                    <small class="text-muted"><?php echo $logFile; ?></small>
                </div>
                <div class="card-body">
                    <?php
                    if (file_exists($logFile) && is_readable($logFile)) {
                        $lines = file($logFile);
                        $recentLines = array_slice($lines, -50); // 最新50行
                        
                        echo '<pre>';
                        foreach ($recentLines as $line) {
                            $line = htmlspecialchars($line);
                            
                            // エラー種別で色分け
                            if (stripos($line, 'Fatal error') !== false || stripos($line, 'Parse error') !== false) {
                                echo '<span class="fatal-line">' . $line . '</span>';
                            } elseif (stripos($line, 'Warning') !== false) {
                                echo '<span class="warning-line">' . $line . '</span>';
                            } elseif (stripos($line, 'Error') !== false) {
                                echo '<span class="error-line">' . $line . '</span>';
                            } else {
                                echo $line;
                            }
                        }
                        echo '</pre>';
                    } else {
                        echo '<p class="text-danger">ログファイルが見つからないか、読み取れません</p>';
                    }
                    ?>
                </div>
            </div>
        <?php endforeach; ?>
        
        <div class="card">
            <div class="card-header">
                <h5>PHPエラー設定</h5>
            </div>
            <div class="card-body">
                <table class="table table-sm">
                    <tr>
                        <th>display_errors</th>
                        <td><?php echo ini_get('display_errors'); ?></td>
                    </tr>
                    <tr>
                        <th>log_errors</th>
                        <td><?php echo ini_get('log_errors'); ?></td>
                    </tr>
                    <tr>
                        <th>error_log</th>
                        <td><?php echo ini_get('error_log'); ?></td>
                    </tr>
                    <tr>
                        <th>error_reporting</th>
                        <td><?php echo error_reporting(); ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
