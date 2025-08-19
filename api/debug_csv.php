<?php
/**
 * CSVインポート機能デバッグ用エラーチェッカー
 * api/debug_csv.php
 */

// エラー表示を有効化
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

echo "<h2>CSVインポート機能 デバッグ診断</h2>";
echo "<hr>";

// 1. 基本ファイル存在確認
echo "<h3>1. ファイル存在確認</h3>";
$requiredFiles = [
    '../config/database.php',
    '../classes/Database.php', 
    '../classes/SmileyCSVImporter.php',
    '../classes/SecurityHelper.php',
    '../api/import.php'
];

foreach ($requiredFiles as $file) {
    if (file_exists($file)) {
        echo "✅ {$file} - 存在<br>";
    } else {
        echo "❌ {$file} - 存在しない<br>";
    }
}

echo "<hr>";

// 2. SmileyCSVImporter.phpのシンタックスチェック
echo "<h3>2. SmileyCSVImporter.php シンタックスチェック</h3>";
$importerFile = '../classes/SmileyCSVImporter.php';

if (file_exists($importerFile)) {
    $output = [];
    $return_var = 0;
    exec("php -l {$importerFile} 2>&1", $output, $return_var);
    
    if ($return_var === 0) {
        echo "✅ シンタックス OK<br>";
    } else {
        echo "❌ シンタックスエラー発見:<br>";
        foreach ($output as $line) {
            echo "<code>{$line}</code><br>";
        }
    }
} else {
    echo "❌ ファイルが存在しません<br>";
}

echo "<hr>";

// 3. クラス読み込みテスト
echo "<h3>3. クラス読み込みテスト</h3>";

try {
    require_once '../config/database.php';
    echo "✅ database.php 読み込み成功<br>";
} catch (Exception $e) {
    echo "❌ database.php 読み込みエラー: " . $e->getMessage() . "<br>";
}

try {
    require_once '../classes/Database.php';
    echo "✅ Database.php 読み込み成功<br>";
} catch (Exception $e) {
    echo "❌ Database.php 読み込みエラー: " . $e->getMessage() . "<br>";
}

try {
    require_once '../classes/SmileyCSVImporter.php';
    echo "✅ SmileyCSVImporter.php 読み込み成功<br>";
    
    // クラス存在確認
    if (class_exists('SmileyCSVImporter')) {
        echo "✅ SmileyCSVImporter クラス定義確認<br>";
    } else {
        echo "❌ SmileyCSVImporter クラスが定義されていません<br>";
    }
    
} catch (Exception $e) {
    echo "❌ SmileyCSVImporter.php 読み込みエラー: " . $e->getMessage() . "<br>";
    echo "詳細: " . $e->getFile() . " line " . $e->getLine() . "<br>";
}

echo "<hr>";

// 4. データベース接続テスト  
echo "<h3>4. データベース接続テスト</h3>";

try {
    $db = new Database();
    echo "✅ データベース接続成功<br>";
    
    // テーブル存在確認
    $tables = ['companies', 'departments', 'users', 'suppliers', 'products', 'orders'];
    foreach ($tables as $table) {
        $stmt = $db->query("SHOW TABLES LIKE '{$table}'");
        if ($stmt->rowCount() > 0) {
            echo "✅ テーブル {$table} 存在<br>";
        } else {
            echo "❌ テーブル {$table} 存在しない<br>";
        }
    }
    
} catch (Exception $e) {
    echo "❌ データベース接続エラー: " . $e->getMessage() . "<br>";
}

echo "<hr>";

// 5. SmileyCSVImporter インスタンス生成テスト
echo "<h3>5. SmileyCSVImporter インスタンス生成テスト</h3>";

try {
    $db = new Database();
    $importer = new SmileyCSVImporter($db);
    echo "✅ SmileyCSVImporter インスタンス生成成功<br>";
    
    // メソッド存在確認
    $methods = ['importFile', 'validateData', 'normalizeData'];
    foreach ($methods as $method) {
        if (method_exists($importer, $method)) {
            echo "✅ メソッド {$method} 存在<br>";
        } else {
            echo "❌ メソッド {$method} 存在しない<br>";
        }
    }
    
} catch (Exception $e) {
    echo "❌ インスタンス生成エラー: " . $e->getMessage() . "<br>";
    echo "詳細: " . $e->getFile() . " line " . $e->getLine() . "<br>";
    echo "スタックトレース:<br><pre>" . $e->getTraceAsString() . "</pre>";
}

echo "<hr>";

// 6. import.php 直接実行テスト（GET）
echo "<h3>6. import.php GET実行テスト</h3>";

try {
    ob_start();
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_GET['action'] = 'test';
    
    include '../api/import.php';
    
    $output = ob_get_clean();
    
    if (!empty($output)) {
        echo "✅ import.php レスポンス取得:<br>";
        echo "<pre>" . htmlspecialchars($output) . "</pre>";
    } else {
        echo "❌ import.php レスポンス空<br>";
    }
    
} catch (Exception $e) {
    ob_end_clean();
    echo "❌ import.php 実行エラー: " . $e->getMessage() . "<br>";
}

echo "<hr>";
echo "<h3>診断完了</h3>";
echo "上記結果を確認して、エラーの原因を特定してください。";
?>
