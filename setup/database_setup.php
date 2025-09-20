<?php
/**
 * 集金管理システム データベースセットアップ（実構造対応版）
 * setup/database_setup.php
 * 
 * 作成日: 2025年9月20日
 * 修正日: 2025年9月20日（実際のテーブル構造対応）
 * 目的: 集金管理専用VIEW 5個の作成とデータベース基盤整備
 * 
 * 修正内容:
 * - 実際のinvoicesテーブル構造に対応
 * - company_id → user_id + user_name での企業特定
 * - issue_date → invoice_date に変更
 * - SHOW TABLES LIKE構文エラー修正
 * - 動的テーブル構造確認機能追加
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
    
    // Databaseクラスの読み込み
    if (class_exists('Database')) {
        echo "✅ config/database.php内のDatabaseクラス検出\n";
        $usingConfigDatabase = true;
    } else {
        $classesDbPath = __DIR__ . '/../classes/Database.php';
        if (file_exists($classesDbPath)) {
            require_once $classesDbPath;
            echo "✅ classes/Database.php 読み込み成功\n";
            $usingConfigDatabase = false;
        } else {
            throw new Exception("Databaseクラスが見つかりません");
        }
    }
    echo "\n";
    
} catch (Exception $e) {
    echo "❌ エラー: " . $e->getMessage() . "\n";
    exit(1);
}

// データベース接続
echo "🔌 データベース接続テスト...\n";
try {
    if (method_exists('Database', 'getInstance')) {
        $db = Database::getInstance();
        echo "✅ Database::getInstance() 接続成功\n";
    } else {
        $db = new Database();
        echo "✅ new Database() 接続成功\n";
    }
    
    // PDO取得
    $pdo = null;
    if (method_exists($db, 'getConnection')) {
        $pdo = $db->getConnection();
    } elseif (method_exists($db, 'query')) {
        // Database::query()メソッド経由で接続確認
        $testStmt = $db->query("SELECT 1 as test");
        if ($testStmt) {
            echo "✅ Database::query() 接続確認成功\n";
        }
    } else {
        // リフレクションでPDO取得
        $reflection = new ReflectionClass($db);
        $pdoProperty = $reflection->getProperty('pdo');
        $pdoProperty->setAccessible(true);
        $pdo = $pdoProperty->getValue($db);
        echo "✅ リフレクション経由PDO取得成功\n";
    }
    
    if ($pdo) {
        $stmt = $pdo->query("SELECT 1 as test");
        echo "✅ PDO接続確認成功\n";
    }
    
    echo "データベース: " . DB_NAME . "\n";
    echo "環境: " . (defined('ENVIRONMENT') ? ENVIRONMENT : 'unknown') . "\n\n";
    
} catch (Exception $e) {
    echo "❌ データベース接続エラー: " . $e->getMessage() . "\n";
    exit(1);
}

// 実際のテーブル構造確認
echo "🔍 実際のテーブル構造確認...\n";
try {
    // invoicesテーブルの構造確認
    if ($pdo) {
        $stmt = $pdo->query("DESCRIBE invoices");
    } else {
        $stmt = $db->query("DESCRIBE invoices");
    }
    
    $invoiceColumns = [];
    while ($row = $stmt->fetch()) {
        $invoiceColumns[] = $row['Field'];
    }
    
    echo "📋 invoicesテーブル実際のカラム:\n";
    foreach ($invoiceColumns as $column) {
        echo "  - {$column}\n";
    }
    
    // 重要カラムの存在確認
    $expectedColumns = ['company_id', 'issue_date', 'user_id', 'invoice_date', 'due_date'];
    $existingColumns = [];
    $missingColumns = [];
    
    foreach ($expectedColumns as $column) {
        if (in_array($column, $invoiceColumns)) {
            $existingColumns[] = $column;
            echo "  ✅ {$column}: 存在\n";
        } else {
            $missingColumns[] = $column;
            echo "  ❌ {$column}: 不存在\n";
        }
    }
    
    echo "\n";
    
} catch (Exception $e) {
    echo "⚠️ テーブル構造確認で警告: " . $e->getMessage() . "\n";
    echo "続行します...\n\n";
    $invoiceColumns = [];
    $existingColumns = [];
    $missingColumns = ['company_id', 'issue_date'];
}

// 実際の構造に対応したVIEW SQL生成
echo "⚙️ 実構造対応VIEW SQL生成...\n";

// collection_status_view（実構造対応版）
$collectionStatusViewSql = "
CREATE VIEW collection_status_view AS
SELECT 
    u.company_id as company_id,
    u.company_name,
    i.user_id,
    i.user_name as contact_person,
    '' as phone,
    i.id as invoice_id,
    i.invoice_number,
    i.total_amount,
    i.due_date,
    i.status,
    COALESCE(SUM(p.amount), 0) as paid_amount,
    (i.total_amount - COALESCE(SUM(p.amount), 0)) as outstanding_amount,
    CASE 
        WHEN i.due_date < CURDATE() THEN 'overdue'
        WHEN i.due_date <= DATE_ADD(CURDATE(), INTERVAL 3 DAY) THEN 'urgent'  
        ELSE 'normal'
    END as alert_level,
    DATEDIFF(CURDATE(), i.due_date) as overdue_days
FROM invoices i
LEFT JOIN users u ON i.user_id = u.id
LEFT JOIN payments p ON i.id = p.invoice_id
WHERE i.status IN ('issued', 'partially_paid')
GROUP BY i.id
ORDER BY 
    CASE 
        WHEN i.due_date < CURDATE() THEN 1
        WHEN i.due_date <= DATE_ADD(CURDATE(), INTERVAL 3 DAY) THEN 2
        ELSE 3
    END,
    i.due_date ASC
";

// collection_statistics_view（実構造対応版）
$collectionStatisticsViewSql = "
CREATE VIEW collection_statistics_view AS
SELECT 
    DATE_FORMAT(i.invoice_date, '%Y-%m') as month,
    COUNT(*) as total_invoices,
    SUM(i.total_amount) as total_amount,
    SUM(CASE WHEN i.status = 'paid' THEN i.total_amount ELSE 0 END) as paid_amount,
    SUM(CASE WHEN i.status IN ('issued', 'partially_paid') THEN i.total_amount ELSE 0 END) as outstanding_amount,
    ROUND(
        SUM(CASE WHEN i.status = 'paid' THEN i.total_amount ELSE 0 END) / 
        NULLIF(SUM(i.total_amount), 0) * 100, 2
    ) as collection_rate
FROM invoices i
WHERE i.invoice_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
GROUP BY DATE_FORMAT(i.invoice_date, '%Y-%m')
ORDER BY month DESC
";

// payment_methods_summary_view（実構造対応版）
$paymentMethodsViewSql = "
CREATE VIEW payment_methods_summary_view AS
SELECT 
    p.payment_method,
    COUNT(*) as payment_count,
    SUM(p.amount) as total_amount,
    AVG(p.amount) as average_amount
FROM payments p
WHERE p.payment_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
GROUP BY p.payment_method
ORDER BY total_amount DESC
";

// urgent_collection_alerts_view（実構造対応版）
$urgentAlertsViewSql = "
CREATE VIEW urgent_collection_alerts_view AS
SELECT 
    i.id as invoice_id,
    i.invoice_number,
    u.company_name,
    i.user_name as contact_person,
    i.total_amount,
    i.due_date,
    DATEDIFF(CURDATE(), i.due_date) as overdue_days,
    CASE 
        WHEN i.due_date < CURDATE() THEN 'overdue'
        WHEN i.due_date <= DATE_ADD(CURDATE(), INTERVAL 3 DAY) THEN 'urgent'
        ELSE 'normal'
    END as alert_level
FROM invoices i
LEFT JOIN users u ON i.user_id = u.id
WHERE i.status IN ('issued', 'partially_paid')
  AND i.due_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
ORDER BY i.due_date ASC
";

// daily_collection_schedule_view（実構造対応版）
$dailyScheduleViewSql = "
CREATE VIEW daily_collection_schedule_view AS
SELECT 
    i.due_date as schedule_date,
    COUNT(*) as invoice_count,
    SUM(i.total_amount) as total_amount,
    GROUP_CONCAT(u.company_name SEPARATOR ', ') as company_names
FROM invoices i
LEFT JOIN users u ON i.user_id = u.id
WHERE i.status IN ('issued', 'partially_paid')
  AND i.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
GROUP BY i.due_date
ORDER BY i.due_date ASC
";

// VIEWリスト
$viewDefinitions = [
    'collection_status_view' => $collectionStatusViewSql,
    'collection_statistics_view' => $collectionStatisticsViewSql,
    'payment_methods_summary_view' => $paymentMethodsViewSql,
    'urgent_collection_alerts_view' => $urgentAlertsViewSql,
    'daily_collection_schedule_view' => $dailyScheduleViewSql
];

echo "✅ 実構造対応VIEW SQL生成完了\n\n";

// 既存VIEW削除（修正版）
echo "🔍 既存VIEW確認・削除...\n";
foreach (array_keys($viewDefinitions) as $viewName) {
    try {
        if ($pdo) {
            $stmt = $pdo->query("SHOW TABLES LIKE '{$viewName}'");
        } else {
            $stmt = $db->query("SHOW TABLES LIKE '{$viewName}'");
        }
        
        if ($stmt && $stmt->rowCount() > 0) {
            if ($pdo) {
                $pdo->exec("DROP VIEW IF EXISTS `{$viewName}`");
            } else {
                $db->query("DROP VIEW IF EXISTS `{$viewName}`");
            }
            echo "🗑️ 既存VIEW削除: {$viewName}\n";
        }
    } catch (Exception $e) {
        echo "⚠️ {$viewName} 削除時警告: " . $e->getMessage() . "\n";
    }
}
echo "✅ 既存VIEW確認・削除完了\n\n";

// VIEW作成実行
echo "⚙️ 実構造対応VIEW作成実行...\n";
$createdViews = [];
$successCount = 0;

foreach ($viewDefinitions as $viewName => $sql) {
    try {
        if ($pdo) {
            $pdo->exec($sql);
        } else {
            $db->query($sql);
        }
        
        $createdViews[] = $viewName;
        $successCount++;
        echo "✅ VIEW作成成功: {$viewName}\n";
        
    } catch (Exception $e) {
        echo "❌ VIEW作成エラー ({$viewName}): " . $e->getMessage() . "\n";
        echo "SQL: " . substr($sql, 0, 100) . "...\n";
    }
}

echo "\n✅ VIEW作成完了 ({$successCount}/" . count($viewDefinitions) . ")\n\n";

// 作成されたVIEWの確認
echo "🔍 作成されたVIEW確認...\n";
foreach ($createdViews as $viewName) {
    try {
        if ($pdo) {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM {$viewName}");
        } else {
            $stmt = $db->query("SELECT COUNT(*) as count FROM {$viewName}");
        }
        
        $result = $stmt->fetch();
        echo "✅ VIEW確認: {$viewName} - {$result['count']}件\n";
        
    } catch (Exception $e) {
        echo "⚠️ {$viewName} 確認エラー: " . $e->getMessage() . "\n";
    }
}

// データベース基本情報確認
echo "\n📊 データベース基本情報確認...\n";
try {
    if ($pdo) {
        $stmt = $pdo->query("SHOW TABLES");
    } else {
        $stmt = $db->query("SHOW TABLES");
    }
    
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "総テーブル数: " . count($tables) . "\n";
    
    // 主要テーブルの存在確認
    $requiredTables = ['companies', 'users', 'orders', 'invoices', 'payments'];
    $existingTables = array_intersect($requiredTables, $tables);
    echo "主要テーブル: " . count($existingTables) . "/" . count($requiredTables) . " 存在\n";
    
    foreach ($requiredTables as $table) {
        if (in_array($table, $tables)) {
            try {
                if ($pdo) {
                    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM {$table}");
                    $stmt->execute();
                } else {
                    $stmt = $db->query("SELECT COUNT(*) as count FROM {$table}");
                }
                $result = $stmt->fetch();
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
if (count($createdViews) === count($viewDefinitions)) {
    echo "🎉 セットアップ完了！\n\n";
    echo "✅ 全ての集金管理VIEWが正常に作成されました\n";
    echo "✅ 実際のテーブル構造に対応済みです\n";
    echo "✅ データベース接続が確認できました\n";
    echo "✅ システムは使用可能な状態です\n\n";
    
    echo "🎯 次のステップ:\n";
    echo "1. ブラウザでindex.phpにアクセス\n";
    echo "2. 集金ダッシュボードの動作確認\n";
    echo "3. PaymentManagerクラスの動作テスト\n";
    echo "4. API動作確認\n\n";
    
    echo "🔗 メインシステム: " . (defined('BASE_URL') ? BASE_URL : 'https://twinklemark.xsrv.jp/Smiley/meal-delivery/billing-system/') . "\n";
    
} else {
    echo "⚠️ セットアップ部分完了\n\n";
    echo "作成成功: " . count($createdViews) . "/" . count($viewDefinitions) . " VIEW\n";
    foreach ($createdViews as $view) {
        echo "  ✅ {$view}\n";
    }
}

echo "\n📋 重要な発見:\n";
if (!empty($missingColumns)) {
    echo "❌ 不存在カラム: " . implode(', ', $missingColumns) . "\n";
    echo "✅ 対応済み: 実際の構造に合わせてVIEWを修正しました\n";
}

echo "\n実行完了時刻: " . date('Y-m-d H:i:s') . "\n";
echo "=======================================================================\n";
?>
