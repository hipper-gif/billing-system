<?php
/**
 * 緊急システム診断ツール
 * csv_import.php のエラー原因特定用
 */

// エラー表示を有効化
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>🚨 緊急システム診断ツール</h2>";
echo "<p>現在時刻: " . date('Y-m-d H:i:s') . "</p>";

echo "<h3>1. ファイル存在確認</h3>";
$files_to_check = [
    '../config/database.php',
    '../classes/Database.php',
    '../classes/SecurityHelper.php',
    '../api/import.php'
];

foreach ($files_to_check as $file) {
    $exists = file_exists($file);
    $status = $exists ? "✅ 存在" : "❌ 不在";
    echo "<p><strong>{$file}</strong>: {$status}</p>";
    
    if ($exists) {
        $size = filesize($file);
        echo "<small>　└ ファイルサイズ: {$size} bytes</small><br>";
    }
}

echo "<h3>2. PHP エラーログ確認</h3>";
$error_log = ini_get('error_log');
if ($error_log && file_exists($error_log)) {
    echo "<p>エラーログファイル: {$error_log}</p>";
    $errors = file_get_contents($error_log);
    $recent_errors = array_slice(explode("\n", $errors), -10);
    echo "<pre style='background: #f8f9fa; padding: 10px; border-radius: 5px;'>";
    echo implode("\n", $recent_errors);
    echo "</pre>";
} else {
    echo "<p>❌ エラーログファイルが見つかりません</p>";
}

echo "<h3>3. Database接続テスト</h3>";
try {
    if (file_exists('../config/database.php')) {
        require_once '../config/database.php';
        echo "<p>✅ database.php 読み込み成功</p>";
    } else {
        throw new Exception("database.php が見つかりません");
    }
    
    if (file_exists('../classes/Database.php')) {
        require_once '../classes/Database.php';
        echo "<p>✅ Database.php 読み込み成功</p>";
    } else {
        throw new Exception("Database.php が見つかりません");
    }
    
    if (class_exists('Database')) {
        echo "<p>✅ Database クラス存在確認</p>";
        
        $db = Database::getInstance();
        echo "<p>✅ Database::getInstance() 成功</p>";
        
        $connection = $db->getConnection();
        if ($connection) {
            echo "<p>✅ PDO接続取得成功</p>";
            
            // 簡単なクエリ実行
            $stmt = $connection->query("SELECT 1 as test");
            $result = $stmt->fetch();
            if ($result && $result['test'] == 1) {
                echo "<p>✅ データベースクエリ実行成功</p>";
            } else {
                echo "<p>❌ データベースクエリ実行失敗</p>";
            }
        } else {
            echo "<p>❌ PDO接続取得失敗</p>";
        }
    } else {
        echo "<p>❌ Database クラスが存在しません</p>";
    }
    
} catch (Exception $e) {
    echo "<p>❌ Database接続エラー: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>ファイル: " . htmlspecialchars($e->getFile()) . " 行: " . $e->getLine() . "</p>";
}

echo "<h3>4. SecurityHelper クラステスト</h3>";
try {
    if (file_exists('../classes/SecurityHelper.php')) {
        require_once '../classes/SecurityHelper.php';
        echo "<p>✅ SecurityHelper.php 読み込み成功</p>";
        
        if (class_exists('SecurityHelper')) {
            echo "<p>✅ SecurityHelper クラス存在確認</p>";
            
            // セッション開始テスト
            SecurityHelper::secureSessionStart();
            echo "<p>✅ secureSessionStart() 成功</p>";
            
            // CSRFトークン生成テスト
            $token = SecurityHelper::generateCSRFToken();
            if ($token && strlen($token) > 0) {
                echo "<p>✅ CSRF トークン生成成功: " . substr($token, 0, 8) . "...</p>";
            } else {
                echo "<p>❌ CSRF トークン生成失敗</p>";
            }
        } else {
            echo "<p>❌ SecurityHelper クラスが存在しません</p>";
        }
    } else {
        echo "<p>❌ SecurityHelper.php が見つかりません</p>";
    }
} catch (Exception $e) {
    echo "<p>❌ SecurityHelper エラー: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<h3>5. API通信テスト</h3>";
$api_url = '../api/import.php?action=test';
try {
    if (file_exists('../api/import.php')) {
        echo "<p>✅ import.php ファイル存在確認</p>";
        
        // APIファイルの内容確認（最初の100文字）
        $api_content = file_get_contents('../api/import.php');
        $first_line = substr($api_content, 0, 100);
        echo "<p>API ファイル先頭: <code>" . htmlspecialchars($first_line) . "...</code></p>";
        
        // cURLでテスト（利用可能な場合）
        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $api_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_HEADER, true);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            echo "<p>HTTP ステータス: {$http_code}</p>";
            
            if ($http_code == 200) {
                echo "<p>✅ API通信成功</p>";
                
                // レスポンスヘッダーとボディを分離
                $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
                $body = substr($response, $header_size);
                
                echo "<p>レスポンス（最初の200文字）:</p>";
                echo "<pre style='background: #f8f9fa; padding: 10px; border-radius: 5px;'>";
                echo htmlspecialchars(substr($body, 0, 200));
                echo "</pre>";
            } else {
                echo "<p>❌ API通信失敗</p>";
            }
        } else {
            echo "<p>⚠️ cURL拡張が利用できません。file_get_contents でテスト</p>";
            
            $context = stream_context_create([
                'http' => [
                    'timeout' => 10,
                    'method' => 'GET'
                ]
            ]);
            
            $response = @file_get_contents($api_url, false, $context);
            if ($response !== false) {
                echo "<p>✅ file_get_contents による通信成功</p>";
                echo "<p>レスポンス（最初の200文字）:</p>";
                echo "<pre style='background: #f8f9fa; padding: 10px; border-radius: 5px;'>";
                echo htmlspecialchars(substr($response, 0, 200));
                echo "</pre>";
            } else {
                echo "<p>❌ file_get_contents による通信失敗</p>";
            }
        }
    } else {
        echo "<p>❌ import.php ファイルが見つかりません</p>";
    }
} catch (Exception $e) {
    echo "<p>❌ API通信テストエラー: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<h3>6. JavaScript エラー確認</h3>";
echo "<p>ブラウザのコンソールログを確認してください：</p>";
echo "<ol>";
echo "<li>F12 キーを押してデベロッパーツールを開く</li>";
echo "<li>「Console」タブを選択</li>";
echo "<li>ページを再読み込みして、赤色のエラーメッセージを確認</li>";
echo "</ol>";

echo "<h3>7. 推奨対応策</h3>";
echo "<div style='background: #d1ecf1; border: 1px solid #bee5eb; padding: 15px; border-radius: 5px;'>";
echo "<h4>問題が特定できた場合：</h4>";
echo "<ul>";
echo "<li><strong>ファイル不在</strong>: 不足ファイルをGitHubから再取得</li>";
echo "<li><strong>Database接続エラー</strong>: config/database.php の設定確認</li>";
echo "<li><strong>API通信エラー</strong>: import.php の構文エラー確認</li>";
echo "<li><strong>JavaScript エラー</strong>: ブラウザコンソールでエラー詳細確認</li>";
echo "</ul>";
echo "<h4>問題が特定できない場合：</h4>";
echo "<ul>";
echo "<li>既存の <code>csv_import.php</code> ファイルに戻す</li>";
echo "<li>段階的にコードを更新して原因を特定</li>";
echo "<li>既存デバッグツール（25ファイル）を活用</li>";
echo "</ul>";
echo "</div>";

echo "<h3>8. 緊急回避策</h3>";
echo "<p>新しいcsv_import.phpでエラーが発生する場合、元のファイルに戻してください：</p>";
echo "<ol>";
echo "<li>GitHubから元の <code>csv_import.php</code> を取得</li>";
echo "<li>新しいファイルをバックアップ（別名保存）</li>";
echo "<li>元のファイルで正常動作確認</li>";
echo "<li>段階的に新機能を追加</li>";
echo "</ol>";

?>

<script>
// JavaScript エラーキャッチ
window.addEventListener('error', function(event) {
    console.error('JavaScript エラー:', event.error);
    document.getElementById('jsErrorInfo').innerHTML = 
        '<div style="background: #f8d7da; border: 1px solid #f5c6cb; padding: 10px; border-radius: 5px; margin-top: 10px;">' +
        '<strong>JavaScript エラー検出:</strong><br>' +
        'メッセージ: ' + event.message + '<br>' +
        'ファイル: ' + event.filename + '<br>' +
        '行: ' + event.lineno +
        '</div>';
});

// AJAX通信テスト
fetch('../api/import.php?action=test')
    .then(response => {
        if (!response.ok) {
            throw new Error('HTTP ' + response.status);
        }
        return response.json();
    })
    .then(data => {
        document.getElementById('ajaxTestResult').innerHTML = 
            '<div style="background: #d4edda; border: 1px solid #c3e6cb; padding: 10px; border-radius: 5px; margin-top: 10px;">' +
            '<strong>✅ AJAX通信成功:</strong><br>' +
            JSON.stringify(data, null, 2) +
            '</div>';
    })
    .catch(error => {
        document.getElementById('ajaxTestResult').innerHTML = 
            '<div style="background: #f8d7da; border: 1px solid #f5c6cb; padding: 10px; border-radius: 5px; margin-top: 10px;">' +
            '<strong>❌ AJAX通信失敗:</strong><br>' +
            error.message +
            '</div>';
    });
</script>

<div id="jsErrorInfo"></div>
<div id="ajaxTestResult"></div>

<style>
body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
h2, h3 { color: #333; }
p { margin: 8px 0; }
pre { font-size: 12px; max-height: 200px; overflow-y: auto; }
code { background: #f1f3f4; padding: 2px 4px; border-radius: 3px; }
</style>
