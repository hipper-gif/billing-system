<?php
// debug_files.php
echo "=== ファイル構造確認 ===\n\n";

echo "現在のディレクトリ: " . __DIR__ . "\n\n";

echo "classesディレクトリの内容:\n";
$classes_dir = __DIR__ . '/../classes/';
if (is_dir($classes_dir)) {
    $files = scandir($classes_dir);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..') {
            echo "- $file\n";
        }
    }
} else {
    echo "classesディレクトリが存在しません\n";
}

echo "\n設定ファイル確認:\n";
$config_file = __DIR__ . '/../config/database.php';
echo "database.php: " . (file_exists($config_file) ? "EXISTS" : "NOT EXISTS") . "\n";
?>
