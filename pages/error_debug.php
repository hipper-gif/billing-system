<?php
/**
 * エラー詳細調査ツール
 */

// エラー表示を有効化
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

echo "<h2>🔍 CSVインポートエラー詳細調査</h2>";

try {
    // 1. 基本システムチェック
    echo "<h3>1. 基本システムチェック</h3>";
    echo "PHP Version: " . PHP_VERSION . "<br>";
    echo "Memory Limit: " . ini_get('memory_limit') . "<br>";
    echo "Upload Max Size: " . ini_get('upload_max_filesize') . "<br>";
    echo "Post Max Size: " . ini_get('post_max_size') . "<br>";
    echo "Max Execution Time: " . ini_get('max_execution_time') . "<br>";
    
    // 2. ファイル存在確認
    echo "<h3>2. ファイル存在確認</h3>";
    $files = [
        '../config/database.php',
        '../classes/Database.php', 
        '../classes/SmileyCSVImporter.php',
        '../classes/FileUploadHandler.php',
        '../api/import.php'
    ];
    
    foreach ($files as $file) {
        $exists = file_exists($file);
        $status = $exists ? "✅" : "❌";
        echo "{$status} {$file}<br>";
        
        if ($exists) {
            $size = filesize($file);
            echo "&nbsp;&nbsp;&nbsp;Size: {$size} bytes<br>";
        }
    }
    
    // 3. クラス読み込みテスト
    echo "<h3>3. クラス読み込みテスト</h3>";
    
    try {
        require_once '../config/database.php';
        echo "✅ database.php 読み込み成功<br>";
        
        require_once '../classes/Database.php';
        echo "✅ Database.php 読み込み成功<br>";
        
        require_once '../classes/SmileyCSVImporter.php';
        echo "✅ SmileyCSVImporter.php 読み込み成功<br>";
        
        require_once '../classes/FileUploadHandler.php';
        echo "✅ FileUploadHandler.php 読み込み成功<br>";
        
    } catch (Exception $e) {
        echo "❌ クラス読み込みエラー: " . $e->getMessage() . "<br>";
        echo "File: " . $e->getFile() . "<br>";
        echo "Line: " . $e->getLine() . "<br>";
    }
    
    // 4. データベース接続テスト
    echo "<h3>4. データベース接続テスト</h3>";
    try {
        $db = new Database();
        echo "✅ データベース接続成功<br>";
        
        // テストクエリ
        $stmt = $db->query("SELECT COUNT(*) as count FROM users");
        $result = $stmt->fetch();
        echo "✅ ユーザー数: " . $result['count'] . "<br>";
        
    } catch (Exception $e) {
        echo "❌ データベース接続エラー: " . $e->getMessage() . "<br>";
    }
    
    // 5. SmileyCSVImporterインスタンス化テスト
    echo "<h3>5. SmileyCSVImporterインスタンス化テスト</h3>";
    try {
        $db = new Database();
        $importer = new SmileyCSVImporter($db);
        echo "✅ SmileyCSVImporter インスタンス化成功<br>";
        
    } catch (Exception $e) {
        echo "❌ SmileyCSVImporter エラー: " . $e->getMessage() . "<br>";
        echo "File: " . $e->getFile() . "<br>";
        echo "Line: " . $e->getLine() . "<br>";
    }
    
    // 6. アップロードディレクトリチェック
    echo "<h3>6. アップロードディレクトリチェック</h3>";
    $uploadDir = '../uploads/';
    if (!is_dir($uploadDir)) {
        echo "❌ アップロードディレクトリが存在しません: {$uploadDir}<br>";
        echo "ディレクトリを作成中...<br>";
        if (mkdir($uploadDir, 0755, true)) {
            echo "✅ ディレクトリ作成成功<br>";
        } else {
            echo "❌ ディレクトリ作成失敗<br>";
        }
    } else {
        echo "✅ アップロードディレクトリ存在<br>";
        echo "パーミッション: " . substr(sprintf('%o', fileperms($uploadDir)), -4) . "<br>";
        echo "書き込み可能: " . (is_writable($uploadDir) ? "はい" : "いいえ") . "<br>";
    }
    
    // 7. 直接importAPIテスト（POSTシミュレーション）
    echo "<h3>7. Import API 基本テスト</h3>";
    
    // 小さなテストCSVを作成
    $testCsv = "配達日,社員CD,社員名,事業所CD,事業所名\n2025-08-22,TEST001,テスト太郎,T001,テスト会社";
    $testFile = $uploadDir . 'test.csv';
    
    if (file_put_contents($testFile, $testCsv) !== false) {
        echo "✅ テストCSVファイル作成成功<br>";
        
        // API呼び出しテスト
        $_FILES = [
            'csvFile' => [
                'name' => 'test.csv',
                'type' => 'text/csv',
                'tmp_name' => $testFile,
                'error' => 0,
                'size' => filesize($testFile)
            ]
        ];
        
        $_SERVER['REQUEST_METHOD'] = 'POST';
        
        ob_start();
        try {
            include '../api/import.php';
            $output = ob_get_clean();
            echo "✅ API実行完了<br>";
            echo "出力: <pre>" . htmlspecialchars($output) . "</pre>";
            
        } catch (Exception $e) {
            ob_end_clean();
            echo "❌ API実行エラー: " . $e->getMessage() . "<br>";
            echo "File: " . $e->getFile() . "<br>";
            echo "Line: " . $e->getLine() . "<br>";
        }
        
        // テストファイル削除
        unlink($testFile);
        
    } else {
        echo "❌ テストCSVファイル作成失敗<br>";
    }
    
    // 8. PHPエラーログ表示
    echo "<h3>8. 最新のPHPエラーログ</h3>";
    $errorLog = ini_get('error_log');
    if ($errorLog && file_exists($errorLog)) {
        $errors = file_get_contents($errorLog);
        $recentErrors = implode("\n", array_slice(explode("\n", $errors), -20));
        echo "<pre style='background:#f5f5f5; padding:10px; max-height:300px; overflow:auto;'>";
        echo htmlspecialchars($recentErrors);
        echo "</pre>";
    } else {
        echo "エラーログファイルが見つかりません<br>";
    }
    
} catch (Exception $e) {
    echo "❌ 全体的なエラー: " . $e->getMessage() . "<br>";
    echo "File: " . $e->getFile() . "<br>";
    echo "Line: " . $e->getLine() . "<br>";
}

echo "<hr>";
echo "<p><strong>この結果をコピーして、問題の特定に使用してください。</strong></p>";
?>
