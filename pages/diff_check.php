<?php
/**
 * 差分検証ツール
 * 動作していた時と現在の違いを確認
 */

header('Content-Type: text/html; charset=utf-8');

echo "<html><head><title>Diff Checker</title></head><body>";
echo "<h1>動作時との差分検証</h1>";

echo "<h2>1. 現在のシステム状況確認</h2>";
echo "<pre>";

// API テスト（動作していたもの）
echo "=== API Test (動作確認済み) ===\n";
try {
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_GET['action'] = 'test';
    
    ob_start();
    include '../api/import.php';
    $output = ob_get_clean();
    
    echo "✓ API実行成功\n";
    echo "出力長: " . strlen($output) . " 文字\n";
    
    $json = json_decode($output, true);
    if ($json) {
        echo "✓ JSON解析成功\n";
        echo "Success: " . ($json['success'] ? 'true' : 'false') . "\n";
        echo "Total orders: " . ($json['data']['total_orders'] ?? 'N/A') . "\n";
    } else {
        echo "✗ JSON解析失敗\n";
        echo "Raw output: " . substr($output, 0, 200) . "\n";
    }
} catch (Exception $e) {
    echo "✗ API実行エラー: " . $e->getMessage() . "\n";
}

echo "\n=== ファイル状況確認 ===\n";

// 重要ファイルのハッシュ値確認
$importantFiles = [
    '../api/import.php',
    '../classes/Database.php',
    '../classes/SmileyCSVImporter.php',
    '../config/database.php'
];

foreach ($importantFiles as $file) {
    if (file_exists($file)) {
        $size = filesize($file);
        $modified = date('Y-m-d H:i:s', filemtime($file));
        $hash = substr(md5_file($file), 0, 8);
        echo "✓ {$file}\n";
        echo "  Size: {$size} bytes\n";
        echo "  Modified: {$modified}\n";
        echo "  Hash: {$hash}\n";
    } else {
        echo "✗ {$file} - MISSING\n";
    }
    echo "\n";
}

echo "</pre>";

echo "<h2>2. 動作していた時の想定状況</h2>";
echo "<pre>";
echo "=== 成功していた条件 ===\n";
echo "- GETリクエスト ?action=test: ✓ 動作確認済み\n";
echo "- データベース接続: ✓ 正常\n";
echo "- 既存データ: 141件の注文データ存在\n";
echo "- 全クラス: 正常読み込み確認済み\n";
echo "\n";

echo "=== POSTリクエスト時の問題 ===\n";
echo "- ファイルアップロード: 正常（58KB確認済み）\n";
echo "- エンコーディング検出: エラー発生\n";
echo "- mb_detect_encoding: UTF-8-BOM無効エラー\n";
echo "</pre>";

echo "<h2>3. 最小限修正提案</h2>";
echo "<pre>";
echo "=== 確実な修正方法 ===\n";
echo "1. SmileyCSVImporter.php の detectEncoding メソッドのみ修正\n";
echo "2. 他のコードは一切変更しない\n";
echo "3. エンコーディング検出を簡素化\n";
echo "\n";

echo "修正箇所:\n";
echo "OLD: private \$allowedEncodings = ['SJIS-win', 'UTF-8', 'UTF-8-BOM'];\n";
echo "NEW: private \$allowedEncodings = ['SJIS-win', 'UTF-8'];\n";
echo "\n";
echo "OLD: if (substr(\$data, 0, 3) === \"\\xEF\\xBB\\xBF\") {\n";
echo "    return 'UTF-8-BOM';\n";
echo "}\n";
echo "NEW: if (substr(\$data, 0, 3) === \"\\xEF\\xBB\\xBF\") {\n";
echo "    return 'UTF-8';\n";
echo "}\n";
echo "</pre>";

echo "<h2>4. 現在のSmileyCSVImporter.php確認</h2>";
echo "<pre>";

$importerPath = '../classes/SmileyCSVImporter.php';
if (file_exists($importerPath)) {
    $content = file_get_contents($importerPath);
    
    // 問題のある行を検索
    $lines = explode("\n", $content);
    $problemLines = [];
    
    foreach ($lines as $lineNum => $line) {
        if (strpos($line, 'UTF-8-BOM') !== false) {
            $problemLines[] = "Line " . ($lineNum + 1) . ": " . trim($line);
        }
    }
    
    if (!empty($problemLines)) {
        echo "✗ 問題のある行が見つかりました:\n";
        foreach ($problemLines as $problemLine) {
            echo "  " . $problemLine . "\n";
        }
    } else {
        echo "✓ UTF-8-BOM の記述は見つかりませんでした\n";
    }
    
    // detectEncoding メソッドの確認
    if (strpos($content, 'detectEncoding') !== false) {
        echo "\n✓ detectEncoding メソッドが存在します\n";
        
        // メソッド内容の一部を表示
        preg_match('/private function detectEncoding.*?{(.*?)}/s', $content, $matches);
        if (isset($matches[1])) {
            $methodContent = trim($matches[1]);
            echo "メソッド内容（最初の200文字）:\n";
            echo substr($methodContent, 0, 200) . "...\n";
        }
    } else {
        echo "\n✗ detectEncoding メソッドが見つかりません\n";
    }
    
} else {
    echo "✗ SmileyCSVImporter.php が見つかりません\n";
}

echo "</pre>";

echo "<h2>5. 推奨アクション</h2>";
echo "<div style='background: #e8f5e9; padding: 15px; border-radius: 5px;'>";
echo "<h3>最小限修正で確実に動作させる方法:</h3>";
echo "<ol>";
echo "<li><strong>SmileyCSVImporter.php の2行だけ修正</strong></li>";
echo "<li>private \$allowedEncodings から 'UTF-8-BOM' を削除</li>";
echo "<li>return 'UTF-8-BOM' を return 'UTF-8' に変更</li>";
echo "<li>他のコードは一切触らない</li>";
echo "</ol>";
echo "<p><strong>この修正だけで確実に動作するはずです。</strong></p>";
echo "</div>";

echo "</body></html>";
?>
