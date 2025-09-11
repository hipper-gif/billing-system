<?php
/**
 * JSON出力確認デバッグツール
 * invoice_targets.php APIの動作確認用
 * 
 * @author Claude
 * @version 1.0.0
 * @created 2025-09-11
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>\n";
echo "<html lang='ja'>\n<head>\n";
echo "<meta charset='UTF-8'>\n";
echo "<title>JSON API デバッグツール</title>\n";
echo "<style>body{font-family:monospace;margin:20px;background:#f5f5f5;} .test{background:white;padding:15px;margin:10px 0;border-radius:5px;border-left:4px solid #007bff;} .success{border-left-color:#28a745;} .error{border-left-color:#dc3545;} pre{background:#f8f9fa;padding:10px;border-radius:3px;overflow-x:auto;}</style>\n";
echo "</head>\n<body>\n";

echo "<h1>🔧 JSON API デバッグツール</h1>\n";
echo "<p>invoice_targets.php APIの動作確認</p>\n";

// テスト対象のAPIエンドポイント
$apiEndpoints = [
    'company_bulk' => '/api/invoice_targets.php?invoice_type=company_bulk',
    'department_bulk' => '/api/invoice_targets.php?invoice_type=department_bulk',
    'individual' => '/api/invoice_targets.php?invoice_type=individual',
    'mixed' => '/api/invoice_targets.php?invoice_type=mixed'
];

foreach ($apiEndpoints as $type => $endpoint) {
    echo "<div class='test'>\n";
    echo "<h3>📋 テスト: {$type}</h3>\n";
    echo "<p><strong>URL:</strong> <code>{$endpoint}</code></p>\n";
    
    try {
        // 相対パスでファイル存在確認
        $filePath = __DIR__ . $endpoint;
        
        if (!file_exists($filePath)) {
            echo "<div class='error'>\n";
            echo "<p>❌ <strong>ファイルが存在しません:</strong></p>\n";
            echo "<pre>{$filePath}</pre>\n";
            echo "</div>\n";
            echo "</div>\n";
            continue;
        }
        
        // 出力バッファリング開始
        ob_start();
        
        // GETパラメータ設定
        $_GET['invoice_type'] = $type;
        
        // APIファイル実行
        include $filePath;
        
        // 出力取得
        $output = ob_get_clean();
        
        // 出力内容の分析
        echo "<h4>📤 出力内容:</h4>\n";
        
        // HTMLタグが含まれているかチェック
        if (strpos($output, '<') !== false) {
            echo "<div class='error'>\n";
            echo "<p>⚠️ <strong>HTMLタグが含まれています（JSON以外の出力）</strong></p>\n";
            echo "<pre>" . htmlspecialchars($output) . "</pre>\n";
            echo "</div>\n";
        }
        // **記号が含まれているかチェック
        elseif (strpos($output, '**') !== false) {
            echo "<div class='error'>\n";
            echo "<p>⚠️ <strong>Markdown記号が含まれています</strong></p>\n";
            echo "<pre>" . htmlspecialchars($output) . "</pre>\n";
            echo "</div>\n";
        }
        else {
            // JSON解析テスト
            $jsonData = json_decode($output, true);
            
            if (json_last_error() === JSON_ERROR_NONE) {
                echo "<div class='success'>\n";
                echo "<p>✅ <strong>正常なJSON出力</strong></p>\n";
                
                // JSON構造確認
                if (isset($jsonData['success'])) {
                    echo "<p><strong>success:</strong> " . ($jsonData['success'] ? 'true' : 'false') . "</p>\n";
                }
                
                if (isset($jsonData['data']['targets'])) {
                    $count = count($jsonData['data']['targets']);
                    echo "<p><strong>対象件数:</strong> {$count}件</p>\n";
                }
                
                echo "<details>\n";
                echo "<summary>JSON詳細表示</summary>\n";
                echo "<pre>" . htmlspecialchars(json_encode($jsonData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) . "</pre>\n";
                echo "</details>\n";
                echo "</div>\n";
            } else {
                echo "<div class='error'>\n";
                echo "<p>❌ <strong>JSON解析エラー:</strong> " . json_last_error_msg() . "</p>\n";
                echo "<pre>" . htmlspecialchars($output) . "</pre>\n";
                echo "</div>\n";
            }
        }
        
    } catch (Exception $e) {
        echo "<div class='error'>\n";
        echo "<p>❌ <strong>実行エラー:</strong> " . htmlspecialchars($e->getMessage()) . "</p>\n";
        echo "</div>\n";
    } catch (Error $e) {
        echo "<div class='error'>\n";
        echo "<p>❌ <strong>Fatal Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>\n";
        echo "</div>\n";
    }
    
    echo "</div>\n";
}

// データベース接続テスト
echo "<div class='test'>\n";
echo "<h3>🗄️ データベース接続テスト</h3>\n";

try {
    require_once __DIR__ . '/classes/Database.php';
    
    $db = new Database();
    
    // 企業数確認
    $stmt = $db->query("SELECT COUNT(*) as count FROM companies WHERE is_active = 1");
    $companyCount = $stmt->fetch()['count'];
    
    // 利用者数確認
    $stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE is_active = 1");
    $userCount = $stmt->fetch()['count'];
    
    // 注文数確認
    $stmt = $db->query("SELECT COUNT(*) as count FROM orders WHERE delivery_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)");
    $orderCount = $stmt->fetch()['count'];
    
    echo "<div class='success'>\n";
    echo "<p>✅ <strong>データベース接続成功</strong></p>\n";
    echo "<p><strong>アクティブ企業:</strong> {$companyCount}社</p>\n";
    echo "<p><strong>アクティブ利用者:</strong> {$userCount}名</p>\n";
    echo "<p><strong>過去90日の注文:</strong> {$orderCount}件</p>\n";
    echo "</div>\n";
    
} catch (Exception $e) {
    echo "<div class='error'>\n";
    echo "<p>❌ <strong>データベース接続エラー:</strong> " . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "</div>\n";
}

echo "</div>\n";

// ファイル存在確認
echo "<div class='test'>\n";
echo "<h3>📁 ファイル存在確認</h3>\n";

$checkFiles = [
    'classes/Database.php',
    'api/invoice_targets.php',
    'api/invoices.php',
    'pages/invoice_generate.php'
];

foreach ($checkFiles as $file) {
    $fullPath = __DIR__ . '/' . $file;
    if (file_exists($fullPath)) {
        $size = filesize($fullPath);
        echo "<p>✅ <strong>{$file}</strong> (サイズ: " . number_format($size) . " bytes)</p>\n";
    } else {
        echo "<p>❌ <strong>{$file}</strong> - ファイルが見つかりません</p>\n";
    }
}

echo "</div>\n";

echo "<div class='test'>\n";
echo "<h3>🔧 対処方法</h3>\n";
echo "<ol>\n";
echo "<li><strong>invoice_targets.php</strong> を上記の修正版で置き換える</li>\n";
echo "<li><strong>invoice_generate.php</strong> のJavaScript部分を修正版で置き換える</li>\n";
echo "<li>ブラウザのキャッシュをクリアして再テスト</li>\n";
echo "</ol>\n";
echo "</div>\n";

echo "</body>\n</html>\n";
?>
