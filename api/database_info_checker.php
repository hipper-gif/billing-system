<?php
/**
 * データベース設定確認ツール（パス修正版）
 * 正しいパスでconfig/database.phpを確認
 * 
 * @author Claude
 * @version 1.1.0
 * @fixed 2025-09-03 - パス修正
 */

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>データベース設定確認（修正版） - Smiley配食システム</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .info-box { margin: 1rem 0; padding: 1rem; border-radius: 8px; }
        .success { background: #d4edda; border: 1px solid #28a745; color: #155724; }
        .error { background: #f8d7da; border: 1px solid #dc3545; color: #721c24; }
        .warning { background: #fff3cd; border: 1px solid #ffc107; color: #856404; }
        .info { background: #d1ecf1; border: 1px solid #17a2b8; color: #0c5460; }
        pre { background: #f8f9fa; padding: 1rem; border-radius: 4px; font-size: 0.9rem; overflow-x: auto; }
        .config-value { font-family: monospace; background: #f8f9fa; padding: 2px 4px; border-radius: 3px; }
        .path-test { margin: 0.5rem 0; padding: 0.5rem; background: #f8f9fa; border-radius: 4px; }
    </style>
</head>
<body class="bg-light">
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-10 mx-auto">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h3 class="mb-0">🔧 データベース設定確認（パス修正版）</h3>
                        <small>正しいパスでconfig/database.phpを探索します</small>
                    </div>
                    <div class="card-body">

                        <?php
                        // 環境情報表示
                        echo "<div class='info-box info'>";
                        echo "<h5>📍 環境情報</h5>";
                        echo "<p><strong>現在のディレクトリ:</strong> " . htmlspecialchars(__DIR__) . "</p>";
                        echo "<p><strong>ホスト:</strong> " . htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'unknown') . "</p>";
                        echo "<p><strong>PHP Version:</strong> " . PHP_VERSION . "</p>";
                        echo "<p><strong>現在時刻:</strong> " . date('Y-m-d H:i:s') . "</p>";
                        echo "</div>";

                        // 複数のパスで config/database.php を探索
                        $possible_paths = [
                            __DIR__ . '/../config/database.php',  // 一つ上のディレクトリのconfig
                            __DIR__ . '/config/database.php',     // 現在ディレクトリのconfig
                            __DIR__ . '/../../config/database.php', // 二つ上のディレクトリのconfig
                            dirname(__DIR__) . '/config/database.php', // 親ディレクトリのconfig
                        ];

                        echo "<div class='info-box warning'>";
                        echo "<h5>🔍 config/database.php ファイル探索</h5>";
                        
                        $config_file = null;
                        $found = false;
                        
                        foreach ($possible_paths as $path) {
                            $normalized_path = realpath($path);
                            $exists = file_exists($path);
                            
                            echo "<div class='path-test'>";
                            echo "<strong>パス:</strong> " . htmlspecialchars($path) . "<br>";
                            echo "<strong>正規化パス:</strong> " . htmlspecialchars($normalized_path ?: 'N/A') . "<br>";
                            echo "<strong>存在:</strong> " . ($exists ? '✅ 存在' : '❌ 不存在') . "<br>";
                            if ($exists) {
                                echo "<strong>サイズ:</strong> " . filesize($path) . " bytes<br>";
                                echo "<strong>更新日時:</strong> " . date('Y-m-d H:i:s', filemtime($path)) . "<br>";
                                if (!$found) {
                                    $config_file = $path;
                                    $found = true;
                                }
                            }
                            echo "</div>";
                        }
                        echo "</div>";

                        if ($found) {
                            echo "<div class='info-box success'>";
                            echo "<h5>✅ config/database.php ファイル発見</h5>";
                            echo "<p><strong>使用パス:</strong> " . htmlspecialchars($config_file) . "</p>";
                            echo "</div>";

                            // 設定ファイルの内容表示
                            try {
                                // 設定ファイル読み込み前の定数クリア（重複定義エラー回避）
                                $defined_constants_before = get_defined_constants(true)['user'] ?? [];
                                
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
                                            $display_value = empty($value) ? '（空）' : str_repeat('*', strlen($value));
                                        } else {
                                            $display_value = $value === true ? 'true' : ($value === false ? 'false' : $value);
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
                                    echo "<h5>🧪 接続テスト実行</h5>";
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
                                        echo "<h5>🎉 データベース接続成功！</h5>";
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
                                                echo "<details><summary>テーブル一覧（{$table_count}個）</summary><div class='mt-2'>";
                                                echo "<div class='row'>";
                                                foreach ($tables as $index => $table) {
                                                    if ($index % 3 === 0) echo "<div class='col-md-4'>";
                                                    echo "• " . htmlspecialchars($table) . "<br>";
                                                    if ($index % 3 === 2 || $index === count($tables) - 1) echo "</div>";
                                                }
                                                echo "</div></div></details>";
                                            } else {
                                                echo "<p class='text-warning'>⚠️ テーブルが存在しません。</p>";
                                            }

                                            // 基本的なクエリテスト
                                            $stmt = $pdo->query("SELECT 1 as test, NOW() as current_time");
                                            $result = $stmt->fetch();
                                            echo "<p><strong>データベース時刻:</strong> " . htmlspecialchars($result['current_time']) . "</p>";

                                        } catch (Exception $e) {
                                            echo "<p><strong>テーブル確認エラー:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
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
                                            echo "<div class='alert alert-danger'>";
                                            echo "<h6>🔍 ホスト名エラー</h6>";
                                            echo "<p>MySQLホスト名が間違っています。</p>";
                                            echo "<ul>";
                                            echo "<li><strong>現在の設定:</strong> <code>" . htmlspecialchars(DB_HOST) . "</code></li>";
                                            echo "<li><strong>確認方法:</strong> エックスサーバー管理画面 → MySQL設定</li>";
                                            echo "<li><strong>正しい形式:</strong> <code>mysql1.xserver.jp</code>, <code>mysql2.xserver.jp</code> など</li>";
                                            echo "</ul>";
                                            echo "</div>";
                                        } elseif (strpos($error_msg, 'Access denied') !== false) {
                                            echo "<div class='alert alert-warning'>";
                                            echo "<h6>🔐 認証エラー</h6>";
                                            echo "<ul>";
                                            echo "<li>ユーザー名: <code>" . htmlspecialchars(DB_USER) . "</code></li>";
                                            echo "<li>データベース名: <code>" . htmlspecialchars(DB_NAME) . "</code></li>";
                                            echo "<li>パスワードを確認してください</li>";
                                            echo "</ul>";
                                            echo "</div>";
                                        } elseif (strpos($error_msg, 'Unknown database') !== false) {
                                            echo "<div class='alert alert-info'>";
                                            echo "<h6>🗄️ データベース名エラー</h6>";
                                            echo "<p>データベース <code>" . htmlspecialchars(DB_NAME) . "</code> が存在しません。</p>";
                                            echo "</div>";
                                        }
                                        echo "</div>";
                                    }
                                    echo "</div>";
                                }

                            } catch (Exception $e) {
                                echo "<div class='info-box error'>";
                                echo "<h5>❌ 設定ファイル読み込みエラー</h5>";
                                echo "<p>エラー: " . htmlspecialchars($e->getMessage()) . "</p>";
                                echo "</div>";
                            }

                            // ファイル内容の生表示
                            $file_content = file_get_contents($config_file);
                            echo "<div class='info-box info'>";
                            echo "<h5>📝 config/database.php の実際の内容</h5>";
                            echo "<details><summary>ファイル内容を表示</summary>";
                            echo "<pre>" . htmlspecialchars($file_content) . "</pre>";
                            echo "</details>";
                            echo "</div>";
                            
                        } else {
                            echo "<div class='info-box error'>";
                            echo "<h5>❌ config/database.php ファイルが見つかりません</h5>";
                            echo "<p>どのパスでもファイルが見つかりませんでした。ファイルを作成する必要があります。</p>";
                            echo "</div>";
                        }
                        ?>

                        <div class="info-box warning mt-4">
                            <h5>📋 次のアクション</h5>
                            <?php if ($found): ?>
                                <p>✅ 設定ファイルが見つかりました。上記の接続テスト結果を確認してください。</p>
                                <p>接続に失敗している場合は、エックスサーバー管理画面で正確な接続情報を確認し、設定ファイルを修正してください。</p>
                            <?php else: ?>
                                <p>❌ 設定ファイルが見つかりませんでした。以下の場所に config/database.php を作成してください:</p>
                                <p><code><?php echo htmlspecialchars(__DIR__ . '/../config/database.php'); ?></code></p>
                            <?php endif; ?>
                        </div>

                        <div class="info-box error mt-4">
                            <h5>⚠️ セキュリティ注意</h5>
                            <p><strong>このファイルは設定確認用です。確認完了後は必ず削除してください。</strong></p>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
