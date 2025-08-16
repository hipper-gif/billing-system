<?php
/**
 * PHP動作確認用テストファイル
 */
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHP動作テスト</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        .success { color: green; }
        .info { color: blue; }
        .warning { color: orange; }
    </style>
</head>
<body>
    <h1>🧪 PHP動作テスト</h1>
    
    <h2>基本情報</h2>
    <p class="success">✅ PHP Version: <?php echo phpversion(); ?></p>
    <p class="info">📅 現在時刻: <?php echo date('Y-m-d H:i:s'); ?></p>
    <p class="info">🌐 サーバー: <?php echo $_SERVER['HTTP_HOST'] ?? 'unknown'; ?></p>
    
    <h2>拡張モジュール</h2>
    <p class="<?php echo extension_loaded('pdo') ? 'success' : 'warning'; ?>">
        PDO: <?php echo extension_loaded('pdo') ? '✅ 利用可能' : '❌ 未インストール'; ?>
    </p>
    <p class="<?php echo extension_loaded('pdo_mysql') ? 'success' : 'warning'; ?>">
        PDO MySQL: <?php echo extension_loaded('pdo_mysql') ? '✅ 利用可能' : '❌ 未インストール'; ?>
    </p>
    <p class="<?php echo extension_loaded('mbstring') ? 'success' : 'warning'; ?>">
        mbstring: <?php echo extension_loaded('mbstring') ? '✅ 利用可能' : '❌ 未インストール'; ?>
    </p>
    
    <h2>設定値</h2>
    <p>Memory Limit: <?php echo ini_get('memory_limit'); ?></p>
    <p>Upload Max: <?php echo ini_get('upload_max_filesize'); ?></p>
    <p>Post Max: <?php echo ini_get('post_max_size'); ?></p>
    
    <h2>ディレクトリテスト</h2>
    <?php
    $dirs = ['config', 'classes', 'api', 'uploads', 'temp', 'logs', 'cache'];
    foreach ($dirs as $dir) {
        $exists = is_dir(__DIR__ . '/' . $dir);
        echo "<p class='" . ($exists ? 'success' : 'warning') . "'>";
        echo $dir . ": " . ($exists ? '✅ 存在' : '❌ 不存在');
        echo "</p>";
    }
    ?>
    
    <hr>
    <p><a href="index.php">← メイン画面に戻る</a></p>
</body>
</html>
