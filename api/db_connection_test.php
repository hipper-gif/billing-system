<?php
// データベース接続テストツール
// このファイルを一時的にサーバーにアップロードして接続確認を行う

// エラー表示を有効にする
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>データベース接続テスト</h2>\n";
echo "<p>現在時刻: " . date('Y-m-d H:i:s') . "</p>\n";
echo "<p>サーバー: " . $_SERVER['HTTP_HOST'] . "</p>\n";

// 複数のホスト名パターンを試行
$connection_patterns = [
    // パターン1: よく使われるホスト名
    [
        'host' => 'mysql1.xserver.jp',
        'name' => 'twinklemark_billing',
        'user' => 'twinklemark_billing',
        'label' => 'パターン1（技術仕様書記載）'
    ],
    [
        'host' => 'mysql1234.xserver.jp',
        'name' => 'twinklemark_billing',
        'user' => 'twinklemark_billing',
        'label' => 'パターン2（一般的な形式）'
    ],
    [
        'host' => 'localhost',
        'name' => 'twinklemark_billing',
        'user' => 'twinklemark_billing',
        'label' => 'パターン3（localhost）'
    ],
    // エラーメッセージに表示されていたユーザー名での試行
    [
        'host' => 'mysql1.xserver.jp',
        'name' => 'twinklemark_billing',
        'user' => 'twinklemark_db',
        'label' => 'パターン4（エラーメッセージのユーザー名）'
    ]
];

foreach ($connection_patterns as $pattern) {
    echo "<h3>■ {$pattern['label']}</h3>\n";
    echo "<p>ホスト: {$pattern['host']}</p>\n";
    echo "<p>DB名: {$pattern['name']}</p>\n";
    echo "<p>ユーザー: {$pattern['user']}</p>\n";
    
    // 複数のパスワードパターンを試行（実際のパスワードを入力）
    $password_patterns = [
        'actual_password_here',  // 実際のパスワードに置き換え
        'test_password',
        'your_test_password',
        ''  // パスワードなし
    ];
    
    foreach ($password_patterns as $password) {
        echo "<p>パスワード: " . (empty($password) ? '(なし)' : str_repeat('*', strlen($password))) . " → ";
        
        try {
            $dsn = "mysql:host={$pattern['host']};dbname={$pattern['name']};charset=utf8mb4";
            $pdo = new PDO($dsn, $pattern['user'], $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            
            // 接続成功時のテスト
            $result = $pdo->query("SELECT 1 as test_result")->fetch();
            if ($result['test_result'] == 1) {
                echo "<span style='color: green; font-weight: bold;'>✅ 接続成功！</span></p>\n";
                
                // テーブル一覧を取得
                echo "<h4>テーブル一覧:</h4>\n";
                $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
                if (count($tables) > 0) {
                    echo "<ul>\n";
                    foreach ($tables as $table) {
                        echo "<li>{$table}</li>\n";
                    }
                    echo "</ul>\n";
                } else {
                    echo "<p>テーブルなし（空のデータベース）</p>\n";
                }
                
                // 正しい設定を出力
                echo "<h4>✅ 正しい設定情報:</h4>\n";
                echo "<pre>\n";
                echo "define('DB_HOST', '{$pattern['host']}');\n";
                echo "define('DB_NAME', '{$pattern['name']}');\n";
                echo "define('DB_USER', '{$pattern['user']}');\n";
                echo "define('DB_PASS', '" . $password . "');\n";
                echo "</pre>\n";
                
                echo "<p style='background-color: #e7f7e7; padding: 10px; border-left: 4px solid green;'>";
                echo "<strong>成功パターン発見！</strong><br>";
                echo "このパターンで接続できました。config/database.phpを上記の設定で更新してください。";
                echo "</p>\n";
                
                // 正常終了なので他のパターンは試行しない
                exit;
            }
            
        } catch (Exception $e) {
            echo "<span style='color: red;'>❌ " . $e->getMessage() . "</span></p>\n";
        }
    }
    
    echo "<hr>\n";
}

// すべて失敗した場合のガイダンス
echo "<h3>❌ すべての接続パターンが失敗しました</h3>\n";
echo "<h4>次の手順で実際の接続情報を確認してください:</h4>\n";
echo "<ol>\n";
echo "<li>エックスサーバーの管理パネルにログイン</li>\n";
echo "<li>「MySQL設定」を開く</li>\n";
echo "<li>「MySQL一覧」で以下を確認:\n";
echo "  <ul>\n";
echo "    <li>MySQLホスト名（mysql####.xserver.jpの形式）</li>\n";
echo "    <li>データベース名</li>\n";
echo "  </ul>\n";
echo "</li>\n";
echo "<li>「MySQLユーザ一覧」で以下を確認:\n";
echo "  <ul>\n";
echo "    <li>ユーザー名</li>\n";
echo "    <li>パスワード（分からない場合は再設定）</li>\n";
echo "  </ul>\n";
echo "</li>\n";
echo "<li>このファイル（db_connection_test.php）の$connection_patternsを修正し再実行</li>\n";
echo "</ol>\n";

echo "<h4>参考情報:</h4>\n";
echo "<ul>\n";
echo "<li>サーバー番号: " . (isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : 'unknown') . "</li>\n";
echo "<li>PHP版本: " . phpversion() . "</li>\n";
echo "<li>PDO拡張: " . (extension_loaded('pdo_mysql') ? '利用可能' : '利用不可') . "</li>\n";
echo "</ul>\n";

// このファイルを削除するリマインダー
echo "<div style='background-color: #fff3cd; padding: 15px; border: 1px solid #ffeaa7; margin-top: 20px;'>\n";
echo "<strong>⚠️ 重要:</strong> 接続確認完了後、このファイル（db_connection_test.php）は必ずサーバーから削除してください。\n";
echo "セキュリティ上の理由で、このような接続情報テストファイルは公開ディレクトリに残すべきではありません。\n";
echo "</div>\n";
?>

<!-- CSS for better readability -->
<style>
body { 
    font-family: Arial, sans-serif; 
    max-width: 800px; 
    margin: 0 auto; 
    padding: 20px; 
    line-height: 1.6; 
}
pre { 
    background-color: #f4f4f4; 
    padding: 10px; 
    border-radius: 5px; 
    overflow-x: auto; 
}
hr { 
    margin: 20px 0; 
    border: 0; 
    height: 1px; 
    background-color: #ddd; 
}
</style>
