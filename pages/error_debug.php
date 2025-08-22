<?php
/**
 * CSVインポートエラー詳細調査（修正版）
 * Database統一対応版
 */

require_once '../config/database.php';

echo "🔍 CSVインポートエラー詳細調査\n\n";

// 1. 基本システムチェック
echo "1. 基本システムチェック\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Memory Limit: " . ini_get('memory_limit') . "\n";
echo "Upload Max Size: " . ini_get('upload_max_filesize') . "\n";
echo "Post Max Size: " . ini_get('post_max_size') . "\n";
echo "Max Execution Time: " . ini_get('max_execution_time') . "\n\n";

// 2. ファイル存在確認
echo "2. ファイル存在確認\n";
$files = [
    '../config/database.php',
    '../classes/Database.php',
    '../classes/SmileyCSVImporter.php',
    '../classes/FileUploadHandler.php',
    '../api/import.php'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        $size = filesize($file);
        echo "✅ " . basename($file) . "   Size: {$size} bytes\n";
    } else {
        echo "❌ " . basename($file) . "   Not found\n";
    }
}
echo "\n";

// 3. クラス読み込みテスト
echo "3. クラス読み込みテスト\n";
try {
    require_once '../config/database.php';
    echo "✅ database.php 読み込み成功\n";
} catch (Exception $e) {
    echo "❌ database.php 読み込みエラー: " . $e->getMessage() . "\n";
}

try {
    require_once '../classes/Database.php';
    echo "✅ Database.php 読み込み成功\n";
} catch (Exception $e) {
    echo "❌ Database.php 読み込みエラー: " . $e->getMessage() . "\n";
}

try {
    require_once '../classes/SmileyCSVImporter.php';
    echo "✅ SmileyCSVImporter.php 読み込み成功\n";
} catch (Exception $e) {
    echo "❌ SmileyCSVImporter.php 読み込みエラー: " . $e->getMessage() . "\n";
}

try {
    require_once '../classes/FileUploadHandler.php';
    echo "✅ FileUploadHandler.php 読み込み成功\n";
} catch (Exception $e) {
    echo "❌ FileUploadHandler.php 読み込みエラー: " . $e->getMessage() . "\n";
}
echo "\n";

// 4. データベース接続テスト（修正版）
echo "4. データベース接続テスト\n";
try {
    // Database::getInstance()を使用
    $db = Database::getInstance();
    $connectionTest = $db->testConnection();
    
    if ($connectionTest['status']) {
        echo "✅ データベース接続成功\n";
        echo "   Database: " . $connectionTest['database'] . "\n";
        echo "   Host: " . $connectionTest['host'] . "\n";
        
        // テーブル一覧取得
        $tables = $db->getTables();
        echo "   Tables: " . count($tables) . " 個\n";
        if (!empty($tables)) {
            echo "   Table List: " . implode(', ', array_slice($tables, 0, 5)) . (count($tables) > 5 ? '...' : '') . "\n";
        }
    } else {
        echo "❌ データベース接続失敗: " . $connectionTest['message'] . "\n";
    }
} catch (Exception $e) {
    echo "❌ データベース接続エラー: " . $e->getMessage() . "\n";
}
echo "\n";

// 5. SmileyCSVImporter インスタンス作成テスト
echo "5. SmileyCSVImporter インスタンス作成テスト\n";
try {
    $db = Database::getInstance();
    $importer = new SmileyCSVImporter($db);
    echo "✅ SmileyCSVImporter インスタンス作成成功\n";
} catch (Exception $e) {
    echo "❌ SmileyCSVImporter インスタンス作成エラー: " . $e->getMessage() . "\n";
}
echo "\n";

// 6. API import.php レスポンステスト
echo "6. API import.php レスポンステスト\n";
try {
    $url = 'https://twinklemark.xsrv.jp/Smiley/meal-delivery/billing-system/api/import.php';
    
    // cURLでGETリクエスト
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $contentLength = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
    
    curl_close($ch);
    
    echo "HTTP Code: {$httpCode}\n";
    echo "Content Type: {$contentType}\n";
    echo "Content Length: {$contentLength}\n";
    echo "Response Preview: " . substr($response, 0, 200) . "\n";
    
    if ($httpCode === 200) {
        if (empty($response)) {
            echo "⚠️ レスポンスが空です（Content-Length: {$contentLength}）\n";
        } else {
            echo "✅ レスポンス受信成功\n";
        }
    } else {
        echo "❌ HTTP エラー: {$httpCode}\n";
    }
    
} catch (Exception $e) {
    echo "❌ API テストエラー: " . $e->getMessage() . "\n";
}
echo "\n";

// 7. 環境定数確認
echo "7. 環境定数確認\n";
$constants = ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS', 'ENVIRONMENT', 'DEBUG_MODE'];
foreach ($constants as $const) {
    if (defined($const)) {
        $value = constant($const);
        if ($const === 'DB_PASS') {
            $value = str_repeat('*', strlen($value));
        }
        echo "✅ {$const}: {$value}\n";
    } else {
        echo "❌ {$const}: 未定義\n";
    }
}
echo "\n";

// 8. 権限確認
echo "8. 権限確認\n";
$dirs = ['../uploads', '../logs', '../temp', '../cache'];
foreach ($dirs as $dir) {
    if (is_dir($dir)) {
        $writable = is_writable($dir) ? '書き込み可' : '書き込み不可';
        echo "✅ {$dir}: {$writable}\n";
    } else {
        echo "❌ {$dir}: ディレクトリ不存在\n";
    }
}

echo "\n=== 詳細調査完了 ===\n";
?>
