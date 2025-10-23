<?php
/**
 * ファイル存在確認スクリプト
 */

header('Content-Type: text/plain; charset=utf-8');

echo "=== ファイルシステム診断 ===\n\n";

// 1. 基本パス確認
echo "1. 基本パス確認:\n";
echo "現在のディレクトリ: " . __DIR__ . "\n";
echo "親ディレクトリ: " . dirname(__DIR__) . "\n\n";

// 2. 重要ファイル存在確認
$files = [
    '../classes/Database.php',
    '../classes/SmileyCSVImporter.php',
    '../classes/SecurityHelper.php',
    '../config/database.php',
    '../api/import.php'
];

echo "2. 重要ファイル存在確認:\n";
foreach ($files as $file) {
    $exists = file_exists($file);
    $readable = $exists ? is_readable($file) : false;
    
    echo sprintf("%-35s: %s %s\n", 
        $file, 
        $exists ? '✅ EXISTS' : '❌ NOT_FOUND',
        $readable ? '(readable)' : '(not readable)'
    );
    
    if ($exists) {
        echo "   サイズ: " . filesize($file) . " bytes\n";
        echo "   更新日: " . date('Y-m-d H:i:s', filemtime($file)) . "\n";
    }
}

echo "\n";

// 3. SmileyCSVImporter クラス詳細確認
echo "3. SmileyCSVImporter クラス詳細:\n";
$importerFile = '../classes/SmileyCSVImporter.php';

if (file_exists($importerFile)) {
    echo "✅ ファイルが存在します\n";
    
    // ファイル内容の先頭部分を確認
    $content = file_get_contents($importerFile);
    echo "ファイルサイズ: " . strlen($content) . " characters\n";
    echo "先頭100文字:\n";
    echo substr($content, 0, 100) . "...\n\n";
    
    // クラス定義確認
    if (strpos($content, 'class SmileyCSVImporter') !== false) {
        echo "✅ クラス定義が見つかりました\n";
    } else {
        echo "❌ クラス定義が見つかりません\n";
    }
    
    // 重要メソッド確認
    $methods = ['importFile', 'insertOrderData', 'ensureMasterData'];
    foreach ($methods as $method) {
        if (strpos($content, "function {$method}") !== false || strpos($content, "private function {$method}") !== false) {
            echo "✅ メソッド {$method} が見つかりました\n";
        } else {
            echo "❌ メソッド {$method} が見つかりません\n";
        }
    }
    
} else {
    echo "❌ SmileyCSVImporter.php が見つかりません\n";
}

echo "\n";

// 4. PHPエラーログ確認
echo "4. PHP設定とエラー:\n";
echo "error_reporting: " . error_reporting() . "\n";
echo "display_errors: " . (ini_get('display_errors') ? 'ON' : 'OFF') . "\n";
echo "log_errors: " . (ini_get('log_errors') ? 'ON' : 'OFF') . "\n";

// 5. require_once テスト
echo "\n5. require_once テスト:\n";
try {
    // Note: Database class is now defined in config/database.php, not in classes/Database.php
    if (file_exists('../config/database.php')) {
        require_once '../config/database.php';
        echo "✅ config/database.php 読み込み成功\n";

        if (class_exists('Database')) {
            echo "✅ Database クラス利用可能\n";
        } else {
            echo "❌ Database クラスが見つかりません\n";
        }
    }
    
    if (file_exists('../classes/SmileyCSVImporter.php')) {
        require_once '../classes/SmileyCSVImporter.php';
        echo "✅ SmileyCSVImporter.php 読み込み成功\n";
        
        if (class_exists('SmileyCSVImporter')) {
            echo "✅ SmileyCSVImporter クラス利用可能\n";
            
            // メソッド一覧取得
            $reflection = new ReflectionClass('SmileyCSVImporter');
            $methods = $reflection->getMethods();
            echo "利用可能メソッド数: " . count($methods) . "\n";
            foreach ($methods as $method) {
                echo "  - " . $method->getName() . "\n";
            }
        } else {
            echo "❌ SmileyCSVImporter クラスが見つかりません\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ require_once エラー: " . $e->getMessage() . "\n";
} catch (ParseError $e) {
    echo "❌ 構文エラー: " . $e->getMessage() . "\n";
    echo "ファイル: " . $e->getFile() . "\n";
    echo "行番号: " . $e->getLine() . "\n";
}

echo "\n=== 診断完了 ===\n";
?>
