<?php
/**
 * 集金管理システム データベースセットアップ（根本対応版v2）
 * setup/database_setup.php
 * 
 * 作成日: 2025年9月20日
 * 修正日: 2025年9月20日（構文エラー根本解決）
 * 目的: 集金管理専用VIEW 5個の作成とデータベース基盤整備
 * 
 * 修正内容:
 * - 構文エラー完全解決
 * - config/database.php + classes/Database.php 両対応
 * - 段階的なDatabaseクラス検出
 * - 適切なエラーハンドリング
 * - 詳細な進捗表示とログ出力
 */

// エラー表示設定
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 実行開始
echo "🚀 Smiley配食事業 集金管理システム データベースセットアップ開始\n";
echo "=======================================================================\n\n";

// 実行環境確認
echo "📍 実行環境確認...\n";
echo "実行場所: " . __DIR__ . "\n";
echo "PHP版本: " . PHP_VERSION . "\n";
echo "実行時刻: " . date('Y-m-d H:i:s') . "\n\n";

// 変数初期化
$usingConfigDatabase = false;
$db = null;

// config/database.phpの読み込み（DB定数定義）
$configPath = __DIR__ . '/../config/database.php';
echo "📂 設定ファイル読み込み...\n";
echo "パス: {$configPath}\n";

if (!file_exists($configPath)) {
    echo "❌ エラー: config/database.php が見つかりません\n";
    echo "パス: {$configPath}\n";
    exit(1);
}

try {
    require_once $configPath;
    echo "✅ config/database.php 読み込み成功\n";
    
    // 必要な定数の確認
    $requiredConstants = ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'];
    foreach ($requiredConstants as $constant) {
        if (!defined($constant)) {
            throw new Exception("必要な定数 {$constant} が定義されていません");
        }
    }
    echo "✅ データベース定数確認完了\n";
    
    // Databaseクラスの存在確認
    echo "🔍 Databaseクラス確認...\n";
    if (class_exists('Database')) {
        echo "✅ config/database.php内のDatabaseクラス検出\n";
        $usingConfigDatabase = true;
    } else {
        echo "⚠️ config/database.php内のDatabaseクラス未検出\n";
        echo "📂 classes/Database.php を読み込みます...\n";
        
        $classesDbPath = __DIR__ . '/../classes/Database.php';
        if (!file_exists($classesDbPath)) {
            throw new Exception("classes/Database.php が見つかりません");
        }
        
        require_once $classesDbPath;
        echo "✅ classes/Database.php 読み込み成功\n";
        $usingConfigDatabase = false;
    }
    
    // 使用するDatabaseクラスの詳細確認
    if (class_exists('Database')) {
        $reflection = new ReflectionClass('Database');
        echo "📋 Databaseクラス詳細:\n";
        echo "  ファイル: " . $reflection->getFileName() . "\n";
        echo "  getInstance(): " . (method_exists('Database', 'getInstance') ? '✅' : '❌') . "\n";
        echo "  コンストラクタ: " . ($reflection->getConstructor() && $reflection->getConstructor()->isPrivate() ? 'private' : 'public') . "\n";
    } else {
        throw new Exception("Databaseクラスが見つかりません");
    }
    echo "\n";
    
} catch (Exception $e) {
    echo "❌ エラー: " . $e->getMessage() . "\n";
    exit(1);
}

// データベース接続テスト
echo "🔌 データベース接続テスト...\n";
try {
    // Singletonパターンが利用可能な場合の接続テスト
    if (method_exists('Database', 'getInstance')) {
        echo "🔧 Database::getInstance() を使用\n";
        $db = Database::getInstance();
        echo "✅ Singleton接続成功\n";
    } else {
        echo "🔧 new Database() を使用\n";
        $db = new Database();
        echo "✅ 通常接続成功\n";
    }
    
    // 接続テスト
    $connectionTestPassed = false;
    if (method_exists($db, 'getConnection')) {
        $pdo = $db->getConnection();
        $stmt = $pdo->query("SELECT 1 as test");
        $result = $stmt->fetch();
        echo "✅ PDO接続確認成功\n";
        $connectionTestPassed = true;
    } elseif (method_exists($db, 'query')) {
        $stmt = $db->query("SELECT 1 as test");
        echo "✅ データベースクエリテスト成功\n";
        $connectionTestPassed = true;
    } else {
        // リフレクションでPDOオブジェクト取得して接続確認
        $reflection = new ReflectionClass($db);
        $pdoProperty = $reflection->getProperty('pdo');
        $pdoProperty->setAccessible(true);
        $pdo = $pdoProperty->getValue($db);
        $stmt = $pdo->query("SELECT 1 as test");
        $result = $stmt->fetch();
        echo "✅ リフレクション経由接続確認成功\n";
        $connectionTestPassed = true;
    }
    
    if ($connectionTestPassed) {
        echo "データベース: " . DB_NAME . "\n";
        echo "ユーザー: " . DB_USER . "\n";
        echo "環境: " . (defined('ENVIRONMENT') ? ENVIRONMENT : 'unknown') . "\n";
        echo "使用クラス: " . ($usingConfigDatabase ? 'config/database.php' : 'classes/Database.php') . "\n\n";
    } else {
        throw new Exception("接続テストが実行できませんでした");
    }
    
} catch (Exception $e) {
    echo "❌ データベース接続エラー: " . $e->getMessage() . "\n";
    echo "\n🔍 確認事項:\n";
    echo "- データベース名: " . (defined('DB_NAME') ? DB_NAME : '未定義') . "\n";
    echo "- ユーザー名: " . (defined('DB_USER') ? DB_USER : '未定義') . "\n"; 
    echo "- ホスト: " . (defined('DB_HOST') ? DB_HOST : '未定義') . "\n";
    echo "- パスワード設定: " . (defined('DB_PASS') && !empty(DB_PASS) ? '設定済み' : '未設定') . "\n";
    echo "- 使用予定クラス: " . ($usingConfigDatabase ? 'config/database.php' : 'classes/Database.php') . "\n";
    echo "\n📋 対処方法:\n";
    echo "1. データベースが作成されているか確認してください\n";
    echo "2. ユーザー権限が適切に設定されているか確認してください\n";
    echo "3. エックスサーバーのデータベース設定を確認してください\n";
    exit(1);
}

// collection_views.sqlの読み込み
$sqlPath = __DIR__ . '/../database/collection_views.sql';
echo "📄 SQLファイル読み込み...\n";
echo "パス: {$sqlPath}\n";

if (!file_exists($sqlPath)) {
    echo "❌ エラー: database/collection_views.sql が見つかりません\n";
    echo "パス: {$sqlPath}\n";
    exit(1);
}

try {
    $setupSql = file_get_contents($sqlPath);
    if (empty($setupSql)) {
        throw new Exception("SQLファイルが空です");
    }
    echo "✅ collection_views.sql 読み込み成功 (" . strlen($setupSql) . " bytes)\n\n";
    
} catch (Exception $e) {
    echo "❌ SQLファイル読み込みエラー: " . $e->getMessage() . "\n";
    exit(1);
}

// 既存VIEWの確認・削除
echo "🔍 既存VIEW確認・削除...\n";
$viewsToCheck = [
    'collection_status_view',
    'collection_statistics_view', 
    'payment_methods_summary_view',
    'urgent_collection_alerts_view',
    'daily_collection_schedule_view'
];

try {
    foreach ($viewsToCheck as $viewName) {
        try {
            // データベースクラスのメソッドに応じて適切な方法を選択
            if (method_exists($db, 'query')) {
                $stmt = $db->query("SHOW TABLES LIKE ?", [$viewName]);
                if ($stmt && $stmt->rowCount() > 0) {
                    $db->query("DROP VIEW IF EXISTS `{$viewName}`");
                    echo "🗑️ 既存VIEW削除: {$viewName}\n";
                }
            } else {
                // 代替方法：PDO直接アクセス
                $pdo = null;
                if (method_exists($db, 'getConnection')) {
                    $pdo = $db->getConnection();
                } else {
                    // さらなる代替方法：リフレクションでPDOオブジェクト取得
                    $reflection = new ReflectionClass($db);
                    $pdoProperty = $reflection->getProperty('pdo');
                    $pdoProperty->setAccessible(true);
                    $pdo = $pdoProperty->getValue($db);
                }
                
                $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
                $stmt->execute([$viewName]);
                if ($stmt->rowCount() > 0) {
                    $dropStmt = $pdo->prepare("DROP VIEW IF EXISTS `{$viewName}`");
                    $dropStmt->execute();
                    echo "🗑️ 既存VIEW削除: {$viewName}\n";
                }
            }
        } catch (Exception $e) {
            echo "⚠️ {$viewName} 削除時警告: " . $e->getMessage() . "\n";
        }
    }
    echo "✅ 既存VIEW確認・削除完了\n\n";
    
} catch (Exception $e) {
    echo "⚠️ 既存VIEW削除で警告: " . $e->getMessage() . "\n";
    echo "続行します...\n\n";
}

// SQLの実行
echo "⚙️ 集金管理VIEW作成実行...\n";
try {
    // SQLを文ごとに分割（セミコロンで区切り）
    $sqlStatements = array_filter(
        array_map('trim', explode(';', $setupSql)), 
        function($sql) { return !empty($sql) && !preg_match('/^\s*--/', $sql); }
    );
    
    $successCount = 0;
    $totalStatements = count($sqlStatements);
    
    echo "実行予定SQL文数: {$totalStatements}\n\n";
    
    foreach ($sqlStatements as $index => $sql) {
        if (trim($sql)) {
            try {
                // データベースクラスのメソッドに応じて適切な方法を選択
                if (method_exists($db, 'query')) {
                    $db->query($sql);
                } else {
                    // 代替方法：PDO直接アクセス
                    $pdo = null;
                    if (method_exists($db, 'getConnection')) {
                        $pdo = $db->getConnection();
                    } else {
                        // さらなる代替方法：リフレクションでPDOオブジェクト取得
                        $reflection = new ReflectionClass($db);
                        $pdoProperty = $reflection->getProperty('pdo');
                        $pdoProperty->setAccessible(true);
                        $pdo = $pdoProperty->getValue($db);
                    }
                    $pdo->exec($sql);
                }
                
                $successCount++;
                
                // VIEW作成の場合は特別表示
                if (preg_match('/CREATE\s+VIEW\s+`?(\w+)`?/i', $sql, $matches)) {
                    echo "✅ VIEW作成成功: {$matches[1]}\n";
                } else {
                    echo "✅ SQL実行成功 (" . ($index + 1) . "/{$totalStatements})\n";
                }
                
            } catch (Exception $e) {
                echo "❌ SQL実行エラー (" . ($index + 1) . "/{$totalStatements}): " . $e->getMessage() . "\n";
                echo "問題のSQL: " . substr($sql, 0, 100) . "...\n";
                throw $e;
            }
        }
    }
    
    echo "\n✅ 全SQL実行完了 ({$successCount}/{$totalStatements})\n\n";
    
} catch (Exception $e) {
    echo "❌ SQL実行で致命的エラー: " . $e->getMessage() . "\n";
    exit(1);
}

// 作成されたVIEWの確認
echo "🔍 作成されたVIEW確認...\n";
try {
    $createdViews = [];
    foreach ($viewsToCheck as $viewName) {
        try {
            // データベースクラスのメソッドに応じて適切な方法を選択
            if (method_exists($db, 'query')) {
                $stmt = $db->query("SHOW TABLES LIKE ?", [$viewName]);
                if ($stmt && $stmt->rowCount() > 0) {
                    $createdViews[] = $viewName;
                    echo "✅ VIEW確認: {$viewName}\n";
                    
                    // サンプルデータ取得テスト
                    if ($viewName === 'collection_status_view') {
                        try {
                            $testStmt = $db->query("SELECT COUNT(*) as count FROM {$viewName}");
                            $testResult = $testStmt->fetch();
                            echo "   📊 データ件数: {$testResult['count']}件\n";
                        } catch (Exception $e) {
                            echo "   ⚠️ データ取得テスト失敗: " . $e->getMessage() . "\n";
                        }
                    }
                } else {
                    echo "❌ VIEW未作成: {$viewName}\n";
                }
            } else {
                // 代替方法：PDO直接アクセス
                $pdo = null;
                if (method_exists($db, 'getConnection')) {
                    $pdo = $db->getConnection();
                } else {
                    // さらなる代替方法：リフレクションでPDOオブジェクト取得
                    $reflection = new ReflectionClass($db);
                    $pdoProperty = $reflection->getProperty('pdo');
                    $pdoProperty->setAccessible(true);
                    $pdo = $pdoProperty->getValue($db);
                }
                
                $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
                $stmt->execute([$viewName]);
                if ($stmt->rowCount() > 0) {
                    $createdViews[] = $viewName;
                    echo "✅ VIEW確認: {$viewName}\n";
                    
                    // サンプルデータ取得テスト
                    if ($viewName === 'collection_status_view') {
                        try {
                            $testStmt = $pdo->prepare("SELECT COUNT(*) as count FROM {$viewName}");
                            $testStmt->execute();
                            $testResult = $testStmt->fetch();
                            echo "   📊 データ件数: {$testResult['count']}件\n";
                        } catch (Exception $e) {
                            echo "   ⚠️ データ取得テスト失敗: " . $e->getMessage() . "\n";
                        }
                    }
                } else {
                    echo "❌ VIEW未作成: {$viewName}\n";
                }
            }
        } catch (Exception $e) {
            echo "❌ VIEW確認エラー({$viewName}): " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n📋 作成結果サマリー:\n";
    echo "作成済みVIEW: " . count($createdViews) . "/" . count($viewsToCheck) . "\n";
    foreach ($createdViews as $view) {
        echo "  ✅ {$view}\n";
    }
    
    if (count($createdViews) !== count($viewsToCheck)) {
        echo "\n⚠️ 一部のVIEWが作成されていません。SQLファイルを確認してください。\n";
    }
    
} catch (Exception $e) {
    echo "❌ VIEW確認エラー: " . $e->getMessage() . "\n";
}

// データベース基本情報確認
echo "\n📊 データベース基本情報確認...\n";
try {
    // テーブル一覧取得
    $tables = [];
    if (method_exists($db, 'query')) {
        $stmt = $db->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } else {
        $pdo = null;
        if (method_exists($db, 'getConnection')) {
            $pdo = $db->getConnection();
        } else {
            // さらなる代替方法：リフレクションでPDOオブジェクト取得
            $reflection = new ReflectionClass($db);
            $pdoProperty = $reflection->getProperty('pdo');
            $pdoProperty->setAccessible(true);
            $pdo = $pdoProperty->getValue($db);
        }
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    echo "総テーブル数: " . count($tables) . "\n";
    
    // 主要テーブルの存在確認
    $requiredTables = ['companies', 'users', 'orders', 'invoices', 'payments'];
    $existingTables = array_intersect($requiredTables, $tables);
    echo "主要テーブル: " . count($existingTables) . "/" . count($requiredTables) . " 存在\n";
    
    foreach ($requiredTables as $table) {
        if (in_array($table, $tables)) {
            try {
                if (method_exists($db, 'query')) {
                    $stmt = $db->query("SELECT COUNT(*) as count FROM {$table}");
                    $result = $stmt->fetch();
                } else {
                    $pdo = null;
                    if (method_exists($db, 'getConnection')) {
                        $pdo = $db->getConnection();
                    } else {
                        // さらなる代替方法：リフレクションでPDOオブジェクト取得
                        $reflection = new ReflectionClass($db);
                        $pdoProperty = $reflection->getProperty('pdo');
                        $pdoProperty->setAccessible(true);
                        $pdo = $pdoProperty->getValue($db);
                    }
                    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM {$table}");
                    $stmt->execute();
                    $result = $stmt->fetch();
                }
                echo "  ✅ {$table}: {$result['count']}件\n";
            } catch (Exception $e) {
                echo "  ⚠️ {$table}: データ取得エラー\n";
            }
        } else {
            echo "  ❌ {$table}: 未作成\n";
        }
    }
    
} catch (Exception $e) {
    echo "⚠️ データベース情報確認で警告: " . $e->getMessage() . "\n";
}

// 完了メッセージ
echo "\n" . str_repeat("=", 70) . "\n";
if (isset($createdViews) && count($createdViews) === count($viewsToCheck)) {
    echo "🎉 セットアップ完了！\n\n";
    echo "✅ 全ての集金管理VIEWが正常に作成されました\n";
    echo "✅ データベース接続が確認できました\n";
    echo "✅ システムは使用可能な状態です\n\n";
    
    echo "🎯 次のステップ:\n";
    echo "1. ブラウザでindex.phpにアクセス\n";
    echo "2. 集金ダッシュボードの動作確認\n";
    echo "3. PaymentManagerクラスの動作テスト\n";
    echo "4. API動作確認\n\n";
    
    echo "🔗 リンク:\n";
    echo "メインシステム: " . (defined('BASE_URL') ? BASE_URL : 'https://twinklemark.xsrv.jp/Smiley/meal-delivery/billing-system/') . "\n";
    echo "環境確認: " . (defined('BASE_URL') ? BASE_URL : 'https://twinklemark.xsrv.jp/Smiley/meal-delivery/billing-system/') . "config/database.php?debug=env\n";
    
} else {
    echo "⚠️ セットアップ部分完了\n\n";
    echo "一部のVIEWが作成されていません。\n";
    echo "database/collection_views.sqlの内容を確認してください。\n";
}

echo "\n実行完了時刻: " . date('Y-m-d H:i:s') . "\n";
echo "=======================================================================\n";

/**
 * セットアップ状況のログ出力
 */
function logSetupResult($createdViews, $viewsToCheck) {
    $logDir = defined('LOG_DIR') ? LOG_DIR : __DIR__ . '/../logs/';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    
    $logFile = $logDir . 'setup_' . date('Y-m-d_H-i-s') . '.log';
    $logContent = [
        'timestamp' => date('Y-m-d H:i:s'),
        'database' => defined('DB_NAME') ? DB_NAME : 'unknown',
        'environment' => defined('ENVIRONMENT') ? ENVIRONMENT : 'unknown',
        'views_total' => count($viewsToCheck),
        'views_created' => count($createdViews),
        'views_success_rate' => round((count($createdViews) / count($viewsToCheck)) * 100, 2) . '%',
        'created_views' => $createdViews,
        'status' => count($createdViews) === count($viewsToCheck) ? 'success' : 'partial'
    ];
    
    @file_put_contents($logFile, json_encode($logContent, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// ログ出力
if (isset($createdViews) && isset($viewsToCheck)) {
    logSetupResult($createdViews, $viewsToCheck);
}
?>
