<?php
/**
 * データベース設定確認ツール
 * 現在の設定値を表示し、接続テストを行います
 * 
 * 使用方法:
 * 1. このファイルをサーバーにアップロード
 * 2. ブラウザで直接アクセス
 * 3. 設定値を確認
 * 4. セキュリティのため使用後は必ず削除
 * 
 * @author Claude
 * @version 1.0.0
 * @created 2025-09-03
 */

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>データベース設定確認 - Smiley配食システム</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .info-box { margin: 1rem 0; padding: 1rem; border-radius: 8px; }
        .success { background: #d4edda; border: 1px solid #28a745; color: #155724; }
        .error { background: #f8d7da; border: 1px solid #dc3545; color: #721c24; }
        .warning { background: #fff3cd; border: 1px solid #ffc107; color: #856404; }
        .info { background: #d1ecf1; border: 1px solid #17a2b8; color: #0c5460; }
        pre { background: #f8f9fa; padding: 1rem; border-radius: 4px; font-size: 0.9rem; overflow-x: auto; }
        .config-value { font-family: monospace; background: #f8f9fa; padding: 2px 4px; border-radius: 3px; }
    </style>
</head>
<body class="bg-light">
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-10 mx-auto">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0">🔍 データベース設定確認ツール</h3>
                        <small>現在の設定値とconfig/database.phpの内容を確認します</small>
                    </div>
                    <div class="card-body">

                        <?php
                        // 環境情報表示
                        echo "<div class='info-box info'>";
                        echo "<h5>📍 環境情報</h5>";
                        echo "<p><strong>ホスト:</strong> " . htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'unknown') . "</p>";
                        echo "<p><strong>サーバー名:</strong> " . htmlspecialchars($_SERVER['SERVER_NAME'] ?? 'unknown') . "</p>";
                        echo "<p><strong>PHP Version:</strong> " . PHP_VERSION . "</p>";
                        echo "<p><strong>現在時刻:</strong> " . date('Y-m-d H:i:s') . "</p>";
                        echo "</div>";

                        // config/database.php の存在確認と読み込み
                        $config_file = __DIR__ . '/config/database.php';
                        $config_exists = file_exists($config_file);
                        
                        echo "<div class='info-box " . ($config_exists ? 'success' : 'error') . "'>";
                        echo "<h5>📄 config/database.php ファイル</h5>";
                        if ($config_exists) {
                            echo "<p>✅ ファイル存在: " . htmlspecialchars($config_file) . "</p>";
                            echo "<p><strong>ファイルサイズ:</strong> " . filesize($config_file) . " bytes</p>";
                            echo "<p><strong>最終更新:</strong> " . date('Y-m-d H:i:s', filemtime($config_file)) . "</p>";
                        } else {
                            echo "<p>❌ ファイル不存在: " . htmlspecialchars($config_file) . "</p>";
                        }
                        echo "</div>";

                        // 設定ファイルの内容表示
                        if ($config_exists) {
                            try {
                                // 設定ファイル読み込み（エラーキャッチ）
                                ob_start();
                                include $config_file;
                                $include_output = ob_get_clean();
                                
                                echo "<div class='info-box success'>";
                                echo "<h5>✅ 設定ファイル読み込み成功</h5>";
                                if (!empty($include_output)) {
                                    echo "<p><strong>出力:</strong></p><pre>" . htmlspecialchars($include_output) . "</pre>";
                                }
                                echo "</div>";

                                // 定数の確認
                                echo "<div class='info-box info'>";
                                echo "<h5>🔧 データベース設定値</h5>";
                                
                                $db_constants = [
                                    'DB_HOST' => 'データベースホスト',
                                    'DB_NAME' => 'データベース名', 
                                    'DB_USER' => 'ユーザー名',
                                    'DB_PASS' => 'パスワード',
                                    'ENVIRONMENT' => '環境',
                                    'DEBUG_MODE' => 'デバッグモード',
                                    'BASE_URL' => 'ベースURL'
                                ];
                                
                                foreach ($db_constants as $const => $label) {
                                    if (defined($const)) {
                                        $value = constant($const);
                                        // パスワードはマスク表示
                                        if ($const === 'DB_PASS') {
                                            $display_value = str_repeat('*', strlen($value));
                                        } else {
                                            $display_value = $value;
                                        }
                                        echo "<p><strong>{$label}:</strong> <span class='config-value'>{$display_value}</span></p>";
                                    } else {
                                        echo "<p><strong>{$label}:</strong> <span class='text-danger'>未定義</span></p>";
                                    }
                                }
                                echo "</div>";

                                // データベース接続テスト
                                if (defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER') && defined('DB_PASS')) {
                                    echo "<div class='info-box warning'>";
                                    echo "<h5>🧪 接続テスト</h5>";
                                    echo "<p>データベースへの接続を試行します...</p>";
                                    
                                    try {
                                        $start_time = microtime(true);
                                        
                                        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
                                        $options = [
                                            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                                            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                                            PDO::ATTR_EMULATE_PREPARES => false,
                                            PDO::ATTR_TIMEOUT => 10,
                                        ];
                                        
                                        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
                                        $connection_time = round((microtime(true) - $start_time) * 1000, 2);
                                        
                                        echo "</div><div class='info-box success'>";
                                        echo "<h5>✅ データベース接続成功</h5>";
                                        echo "<p><strong>接続時間:</strong> {$connection_time}ms</p>";
                                        
                                        // サーバー情報
                                        try {
                                            $server_version = $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
                                            echo "<p><strong>MySQL バージョン:</strong> {$server_version}</p>";
                                        } catch (Exception $e) {
                                            echo "<p><strong>MySQL バージョン:</strong> 取得失敗</p>";
                                        }

                                        // テーブル数確認
                                        try {
                                            $stmt = $pdo->query("SHOW TABLES");
                                            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
                                            $table_count = count($tables);
                                            
                                            echo "<p><strong>テーブル数:</strong> {$table_count}</p>";
                                            
                                            if ($table_count > 0) {
                                                echo "<details><summary>テーブル一覧表示</summary><ul>";
                                                foreach ($tables as $table) {
                                                    echo "<li>" . htmlspecialchars($table) . "</li>";
                                                }
                                                echo "</ul></details>";
                                            }
                                        } catch (Exception $e) {
                                            echo "<p><strong>テーブル確認:</strong> エラー - " . htmlspecialchars($e->getMessage()) . "</p>";
                                        }
                                        
                                    } catch (PDOException $e) {
                                        echo "</div><div class='info-box error'>";
                                        echo "<h5>❌ データベース接続失敗</h5>";
                                        echo "<p><strong>エラーコード:</strong> " . htmlspecialchars($e->getCode()) . "</p>";
                                        echo "<p><strong>エラーメッセージ:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
                                        
                                        // 具体的な対処法
                                        $error_msg = $e->getMessage();
                                        echo "<div class='mt-3 p-3 bg-light rounded'>";
                                        echo "<h6>💡 対処法</h6>";
                                        if (strpos($error_msg, 'getaddrinfo') !== false || strpos($error_msg, 'Name or service not known') !== false) {
                                            echo "<p>🔍 <strong>ホスト名エラー:</strong></p>";
                                            echo "<ul>";
                                            echo "<li>エックスサーバー管理画面で正確なMySQLホスト名を確認してください</li>";
                                            echo "<li>現在の設定: <code>" . htmlspecialchars(DB_HOST) . "</code></li>";
                                            echo "<li>正しい形式: <code>mysql1.xserver.jp</code> など</li>";
                                            echo "</ul>";
                                        } elseif (strpos($error_msg, 'Access denied') !== false) {
                                            echo "<p>🔐 <strong>認証エラー:</strong></p>";
                                            echo "<ul>";
                                            echo "<li>ユーザー名・パスワードを確認してください</li>";
                                            echo "<li>現在のユーザー名: <code>" . htmlspecialchars(DB_USER) . "</code></li>";
                                            echo "<li>データベース名: <code>" . htmlspecialchars(DB_NAME) . "</code></li>";
                                            echo "</ul>";
                                        } elseif (strpos($error_msg, 'Unknown database') !== false) {
                                            echo "<p>🗄️ <strong>データベース名エラー:</strong></p>";
                                            echo "<ul>";
                                            echo "<li>データベース名を確認してください</li>";
                                            echo "<li>現在の設定: <code>" . htmlspecialchars(DB_NAME) . "</code></li>";
                                            echo "<li>エックスサーバー管理画面でデータベース一覧を確認してください</li>";
                                            echo "</ul>";
                                        }
                                        echo "</div>";
                                    }
                                    echo "</div>";
                                } else {
                                    echo "<div class='info-box error'>";
                                    echo "<h5>❌ 設定不完全</h5>";
                                    echo "<p>必要な設定値が定義されていません。接続テストをスキップします。</p>";
                                    echo "</div>";
                                }

                            } catch (Exception $e) {
                                echo "<div class='info-box error'>";
                                echo "<h5>❌ 設定ファイル読み込みエラー</h5>";
                                echo "<p>エラー: " . htmlspecialchars($e->getMessage()) . "</p>";
                                echo "</div>";
                            }
                        }

                        // ファイル内容の生表示
                        if ($config_exists) {
                            $file_content = file_get_contents($config_file);
                            echo "<div class='info-box info'>";
                            echo "<h5>📝 config/database.php の実際の内容</h5>";
                            echo "<pre>" . htmlspecialchars($file_content) . "</pre>";
                            echo "</div>";
                        }
                        ?>

                        <div class="info-box error mt-4">
                            <h5>⚠️ セキュリティ注意</h5>
                            <p><strong>このファイルは設定確認用です。確認完了後は必ず削除してください。</strong></p>
                            <p>データベース情報が表示されるため、セキュリティリスクがあります。</p>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
