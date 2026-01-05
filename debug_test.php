<?php
/**
 * テスト環境デバッグ用スクリプト
 * このファイルをテスト環境にアップロードしてブラウザでアクセスしてください
 */

// エラー表示を強制的に有効化
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', 1);

echo "<h1>Billing System - 診断ツール</h1>";
echo "<hr>";

// 1. PHP情報
echo "<h2>1. PHP情報</h2>";
echo "PHPバージョン: " . phpversion() . "<br>";
echo "サーバーソフトウェア: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "<br>";
echo "ドキュメントルート: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'Unknown') . "<br>";
echo "HTTP_HOST: " . ($_SERVER['HTTP_HOST'] ?? 'Unknown') . "<br>";
echo "<hr>";

// 2. ファイル存在確認
echo "<h2>2. ファイル存在確認</h2>";
$files = [
    'config/database.php',
    'classes/SmileyInvoicePDF.php',
    'classes/SmileyInvoiceGenerator.php',
    'classes/SimpleCollectionManager.php',
    'vendor/autoload.php'
];

foreach ($files as $file) {
    $path = __DIR__ . '/' . $file;
    if (file_exists($path)) {
        echo "✅ {$file} - 存在します<br>";
    } else {
        echo "❌ {$file} - <strong>存在しません</strong><br>";
    }
}
echo "<hr>";

// 3. データベース設定読み込みテスト
echo "<h2>3. データベース設定</h2>";
try {
    require_once __DIR__ . '/config/database.php';
    echo "✅ database.php 読み込み成功<br>";
    echo "環境: " . (defined('ENVIRONMENT') ? ENVIRONMENT : 'Unknown') . "<br>";
    echo "データベース名: " . (defined('DB_NAME') ? DB_NAME : 'Unknown') . "<br>";
    echo "ユーザー名: " . (defined('DB_USER') ? DB_USER : 'Unknown') . "<br>";
    echo "ホスト: " . (defined('DB_HOST') ? DB_HOST : 'Unknown') . "<br>";
} catch (Exception $e) {
    echo "❌ database.php 読み込みエラー: " . $e->getMessage() . "<br>";
}
echo "<hr>";

// 4. データベース接続テスト
echo "<h2>4. データベース接続テスト</h2>";
try {
    if (class_exists('Database')) {
        $db = Database::getInstance();
        echo "✅ Database クラスのインスタンス化成功<br>";

        $result = $db->fetch("SELECT 1 as test");
        if ($result && $result['test'] == 1) {
            echo "✅ データベース接続成功<br>";

            // テーブル確認
            $tables = $db->fetchAll("SHOW TABLES");
            echo "テーブル数: " . count($tables) . "<br>";
        } else {
            echo "❌ データベース接続テスト失敗<br>";
        }
    } else {
        echo "❌ Database クラスが見つかりません<br>";
    }
} catch (Exception $e) {
    echo "❌ データベース接続エラー: " . $e->getMessage() . "<br>";
    echo "詳細: " . $e->getFile() . ":" . $e->getLine() . "<br>";
}
echo "<hr>";

// 5. クラス読み込みテスト
echo "<h2>5. クラス読み込みテスト</h2>";
try {
    require_once __DIR__ . '/classes/SmileyInvoicePDF.php';
    echo "✅ SmileyInvoicePDF.php 読み込み成功<br>";

    if (class_exists('SmileyInvoicePDF')) {
        echo "✅ SmileyInvoicePDF クラス定義確認<br>";
    }
} catch (Exception $e) {
    echo "❌ SmileyInvoicePDF.php エラー: " . $e->getMessage() . "<br>";
}

try {
    require_once __DIR__ . '/classes/SmileyInvoiceGenerator.php';
    echo "✅ SmileyInvoiceGenerator.php 読み込み成功<br>";

    if (class_exists('SmileyInvoiceGenerator')) {
        echo "✅ SmileyInvoiceGenerator クラス定義確認<br>";
    }
} catch (Exception $e) {
    echo "❌ SmileyInvoiceGenerator.php エラー: " . $e->getMessage() . "<br>";
}
echo "<hr>";

// 6. mPDF確認
echo "<h2>6. mPDF確認</h2>";
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
    if (class_exists('Mpdf\Mpdf')) {
        echo "✅ mPDF ライブラリ利用可能<br>";
    } else {
        echo "❌ mPDF クラスが見つかりません（Composerパッケージ不足）<br>";
    }
} else {
    echo "❌ vendor/autoload.php が見つかりません<br>";
    echo "→ <strong>composer install を実行する必要があります</strong><br>";
}
echo "<hr>";

// 7. ディレクトリパーミッション確認
echo "<h2>7. ディレクトリパーミッション</h2>";
$dirs = ['uploads', 'temp', 'logs', 'cache', 'storage'];
foreach ($dirs as $dir) {
    $path = __DIR__ . '/' . $dir;
    if (is_dir($path)) {
        $perms = substr(sprintf('%o', fileperms($path)), -4);
        $writable = is_writable($path) ? '書込可' : '書込不可';
        echo "✅ {$dir}/ - パーミッション: {$perms} ({$writable})<br>";
    } else {
        echo "❌ {$dir}/ - ディレクトリが存在しません<br>";
    }
}
echo "<hr>";

echo "<h2>診断完了</h2>";
echo "<p>この結果をスクリーンショットして共有してください。</p>";
?>
