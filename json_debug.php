<?php
/**
 * JSON出力確認デバッグツール（修正版）
 * invoice_targets.php APIの動作確認用
 * 
 * Database Singleton対応
 * ファイルパス修正
 * 
 * @author Claude
 * @version 1.1.0
 * @created 2025-09-11
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>\n";
echo "<html lang='ja'>\n<head>\n";
echo "<meta charset='UTF-8'>\n";
echo "<title>JSON API デバッグツール（修正版）</title>\n";
echo "<style>body{font-family:monospace;margin:20px;background:#f5f5f5;} .test{background:white;padding:15px;margin:10px 0;border-radius:5px;border-left:4px solid #007bff;} .success{border-left-color:#28a745;} .error{border-left-color:#dc3545;} .warning{border-left-color:#ffc107;} pre{background:#f8f9fa;padding:10px;border-radius:3px;overflow-x:auto;max-height:300px;} .btn{padding:8px 16px;margin:4px;border:none;border-radius:4px;cursor:pointer;} .btn-primary{background:#007bff;color:white;} .btn-success{background:#28a745;color:white;} .btn-danger{background:#dc3545;color:white;}</style>\n";
echo "</head>\n<body>\n";

echo "<h1>🔧 JSON API デバッグツール（修正版）</h1>\n";
echo "<p>invoice_targets.php APIの動作確認</p>\n";

// 現在のディレクトリ情報
echo "<div class='test'>\n";
echo "<h3>📂 ディレクトリ情報</h3>\n";
echo "<p><strong>現在のディレクトリ:</strong> " . __DIR__ . "</p>\n";
echo "<p><strong>ドキュメントルート:</strong> " . $_SERVER['DOCUMENT_ROOT'] . "</p>\n";
echo "<p><strong>スクリプトファイル名:</strong> " . $_SERVER['SCRIPT_NAME'] . "</p>\n";
echo "</div>\n";

// テスト対象のAPIエンドポイント
$apiEndpoints = [
    'company_bulk' => 'company_bulk',
    'department_bulk' => 'department_bulk', 
    'individual' => 'individual',
    'mixed' => 'mixed'
];

// invoice_targets.php の存在確認と実際のテスト
echo "<div class='test'>\n";
echo "<h3>📋 invoice_targets.php 動作テスト</h3>\n";

// APIファイルのパス確認
$apiFilePath = __DIR__ . '/api/invoice_targets.php';
$alternativeApiPath = __DIR__ . '/../api/invoice_targets.php';

$correctApiPath = null;
if (file_exists($apiFilePath)) {
    $correctApiPath = $apiFilePath;
    echo "<p>✅ <strong>APIファイル発見:</strong> {$apiFilePath}</p>\n";
} elseif (file_exists($alternativeApiPath)) {
    $correctApiPath = $alternativeApiPath;
    echo "<p>✅ <strong>APIファイル発見:</strong> {$alternativeApiPath}</p>\n";
} else {
    echo "<p>❌ <strong>APIファイルが見つかりません</strong></p>\n";
    echo "<p>確認パス1: {$apiFilePath}</p>\n";
    echo "<p>確認パス2: {$alternativeApiPath}</p>\n";
    
    // ディレクトリ構造の確認
    echo "<h4>📁 ディレクトリ構造確認</h4>\n";
    $dirs = [__DIR__, __DIR__ . '/api', __DIR__ . '/../api'];
    foreach ($dirs as $dir) {
        if (is_dir($dir)) {
            echo "<p><strong>{$dir}:</strong></p>\n";
            echo "<ul>\n";
            $files = scandir($dir);
            foreach ($files as $file) {
                if ($file != '.' && $file != '..' && !is_dir($dir . '/' . $file)) {
                    echo "<li>{$file}</li>\n";
                }
            }
            echo "</ul>\n";
        }
    }
}

echo "</div>\n";

if ($correctApiPath) {
    foreach ($apiEndpoints as $type => $param) {
        echo "<div class='test'>\n";
        echo "<h4>📋 テスト: {$type}</h4>\n";
        
        try {
            // GETパラメータ設定
            $_GET['invoice_type'] = $param;
            
            // 出力バッファリング開始
            ob_start();
            
            // エラーを一時的にキャッチ
            set_error_handler(function($severity, $message, $file, $line) {
                throw new ErrorException($message, 0, $severity, $file, $line);
            });
            
            // APIファイル実行
            include $correctApiPath;
            
            // エラーハンドラー復元
            restore_error_handler();
            
            // 出力取得
            $output = ob_get_clean();
            
            // 出力内容の分析
            echo "<h5>📤 出力内容分析:</h5>\n";
            
            // 空の出力チェック
            if (empty($output)) {
                echo "<div class='warning'>\n";
                echo "<p>⚠️ <strong>出力が空です</strong></p>\n";
                echo "</div>\n";
            }
            // HTMLタグが含まれているかチェック
            elseif (strpos($output, '<') !== false) {
                echo "<div class='error'>\n";
                echo "<p>❌ <strong>HTMLタグが含まれています（JSON以外の出力）</strong></p>\n";
                echo "<details>\n";
                echo "<summary>出力内容を表示</summary>\n";
                echo "<pre>" . htmlspecialchars($output) . "</pre>\n";
                echo "</details>\n";
                echo "</div>\n";
            }
            // **記号が含まれているかチェック
            elseif (strpos($output, '**') !== false) {
                echo "<div class='error'>\n";
                echo "<p>❌ <strong>Markdown記号が含まれています</strong></p>\n";
                echo "<details>\n";
                echo "<summary>出力内容を表示</summary>\n";
                echo "<pre>" . htmlspecialchars($output) . "</pre>\n";
                echo "</details>\n";
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
                        
                        // サンプルデータ表示
                        if ($count > 0) {
                            $sample = $jsonData['data']['targets'][0];
                            echo "<p><strong>サンプルデータ:</strong></p>\n";
                            echo "<ul>\n";
                            echo "<li>ID: " . ($sample['id'] ?? 'N/A') . "</li>\n";
                            echo "<li>名前: " . ($sample['name'] ?? 'N/A') . "</li>\n";
                            echo "<li>タイプ: " . ($sample['type'] ?? 'N/A') . "</li>\n";
                            echo "</ul>\n";
                        }
                    }
                    
                    echo "<details>\n";
                    echo "<summary>JSON詳細表示</summary>\n";
                    echo "<pre>" . htmlspecialchars(json_encode($jsonData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) . "</pre>\n";
                    echo "</details>\n";
                    echo "</div>\n";
                } else {
                    echo "<div class='error'>\n";
                    echo "<p>❌ <strong>JSON解析エラー:</strong> " . json_last_error_msg() . "</p>\n";
                    echo "<p><strong>出力長:</strong> " . strlen($output) . " 文字</p>\n";
                    echo "<details>\n";
                    echo "<summary>出力内容を表示</summary>\n";
                    echo "<pre>" . htmlspecialchars($output) . "</pre>\n";
                    echo "</details>\n";
                    echo "</div>\n";
                }
            }
            
        } catch (Exception $e) {
            echo "<div class='error'>\n";
            echo "<p>❌ <strong>実行エラー:</strong> " . htmlspecialchars($e->getMessage()) . "</p>\n";
            echo "<p><strong>ファイル:</strong> " . htmlspecialchars($e->getFile()) . "</p>\n";
            echo "<p><strong>行:</strong> " . $e->getLine() . "</p>\n";
            echo "</div>\n";
        } catch (Error $e) {
            echo "<div class='error'>\n";
            echo "<p>❌ <strong>Fatal Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>\n";
            echo "<p><strong>ファイル:</strong> " . htmlspecialchars($e->getFile()) . "</p>\n";
            echo "<p><strong>行:</strong> " . $e->getLine() . "</p>\n";
            echo "</div>\n";
        }
        
        echo "</div>\n";
    }
}

// データベース接続テスト（Singleton対応）
echo "<div class='test'>\n";
echo "<h3>🗄️ データベース接続テスト</h3>\n";

try {
    // Database クラスの読み込み試行
    $dbPaths = [
        __DIR__ . '/classes/Database.php',
        __DIR__ . '/../classes/Database.php'
    ];
    
    $dbClassLoaded = false;
    foreach ($dbPaths as $dbPath) {
        if (file_exists($dbPath)) {
            require_once $dbPath;
            echo "<p>✅ <strong>Database クラス読み込み成功:</strong> {$dbPath}</p>\n";
            $dbClassLoaded = true;
            break;
        }
    }
    
    if (!$dbClassLoaded) {
        throw new Exception('Database.php が見つかりません');
    }
    
    // Singleton パターンでのインスタンス取得
    $db = Database::getInstance();
    
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
    echo "<p>✅ <strong>データベース接続成功（Singleton使用）</strong></p>\n";
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
echo "<h3>📁 重要ファイル存在確認</h3>\n";

$checkFiles = [
    'classes/Database.php' => ['classes/Database.php', '../classes/Database.php'],
    'api/invoice_targets.php' => ['api/invoice_targets.php', '../api/invoice_targets.php'],
    'api/invoices.php' => ['api/invoices.php', '../api/invoices.php'],
    'pages/invoice_generate.php' => ['pages/invoice_generate.php', '../pages/invoice_generate.php']
];

foreach ($checkFiles as $fileName => $paths) {
    $found = false;
    $foundPath = '';
    
    foreach ($paths as $path) {
        $fullPath = __DIR__ . '/' . $path;
        if (file_exists($fullPath)) {
            $size = filesize($fullPath);
            echo "<p>✅ <strong>{$fileName}</strong> - {$fullPath} (サイズ: " . number_format($size) . " bytes)</p>\n";
            $found = true;
            $foundPath = $fullPath;
            break;
        }
    }
    
    if (!$found) {
        echo "<p>❌ <strong>{$fileName}</strong> - ファイルが見つかりません</p>\n";
        foreach ($paths as $path) {
            echo "<p style='margin-left: 20px; color: #666;'>確認済み: " . __DIR__ . '/' . $path . "</p>\n";
        }
    }
}

echo "</div>\n";

// 実際のAPIテスト（HTTPリクエスト）
echo "<div class='test'>\n";
echo "<h3>🌐 HTTP APIテスト</h3>\n";
echo "<p>実際のHTTPリクエストでAPIをテスト</p>\n";

foreach ($apiEndpoints as $type => $param) {
    $url = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . "/api/invoice_targets.php?invoice_type=" . $param;
    
    echo "<div style='border: 1px solid #ddd; padding: 10px; margin: 10px 0;'>\n";
    echo "<h5>🔗 {$type}</h5>\n";
    echo "<p><strong>URL:</strong> <a href='{$url}' target='_blank'>{$url}</a></p>\n";
    echo "<button class='btn btn-primary' onclick=\"window.open('{$url}', '_blank')\">新しいタブで開く</button>\n";
    echo "</div>\n";
}

echo "</div>\n";

echo "<div class='test'>\n";
echo "<h3>🔧 対処方法</h3>\n";
echo "<ol>\n";
echo "<li><strong>invoice_targets.php が存在する場合:</strong> 上記のHTTPテストで実際のレスポンスを確認</li>\n";
echo "<li><strong>JSON解析エラーが発生する場合:</strong> APIが正しいJSON以外を出力している</li>\n";
echo "<li><strong>Database エラーが発生する場合:</strong> Singleton パターンの Database::getInstance() を使用</li>\n";
echo "<li><strong>ファイルが見つからない場合:</strong> パス構造を確認してファイルを正しい場所に配置</li>\n";
echo "</ol>\n";
echo "</div>\n";

echo "</body>\n</html>\n";
?>
