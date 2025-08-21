<?php
// debug_security.php
echo "=== SecurityHelper デバッグ ===\n\n";

echo "1. ファイル存在確認:\n";
$file_path = __DIR__ . '/../classes/SecurityHelper.php';
echo "パス: $file_path\n";
echo "存在: " . (file_exists($file_path) ? "YES" : "NO") . "\n\n";

if (file_exists($file_path)) {
    echo "2. ファイル読み込みテスト:\n";
    try {
        require_once $file_path;
        echo "読み込み: SUCCESS\n";
    } catch (Exception $e) {
        echo "読み込みエラー: " . $e->getMessage() . "\n";
    }
    
    echo "\n3. クラス存在確認:\n";
    echo "SecurityHelperクラス: " . (class_exists('SecurityHelper') ? "EXISTS" : "NOT EXISTS") . "\n";
    
    if (class_exists('SecurityHelper')) {
        echo "\n4. メソッド存在確認:\n";
        echo "setSecurityHeaders: " . (method_exists('SecurityHelper', 'setSecurityHeaders') ? "EXISTS" : "NOT EXISTS") . "\n";
        
        if (method_exists('SecurityHelper', 'setSecurityHeaders')) {
            echo "\n5. メソッド実行テスト:\n";
            try {
                SecurityHelper::setSecurityHeaders();
                echo "実行: SUCCESS\n";
            } catch (Exception $e) {
                echo "実行エラー: " . $e->getMessage() . "\n";
            }
        }
    }
}
?>
