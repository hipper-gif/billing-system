<?php
/**
 * マイグレーション実行スクリプト
 * ordersテーブルに不足しているカラムを追加
 */

require_once __DIR__ . '/config/database.php';

echo "=== ordersテーブル マイグレーション ===" . PHP_EOL . PHP_EOL;
echo "環境: " . ENVIRONMENT . PHP_EOL;
echo "データベース: " . DB_NAME . PHP_EOL . PHP_EOL;

// 実行確認
if (php_sapi_name() !== 'cli') {
    echo "このスクリプトはコマンドラインから実行してください。" . PHP_EOL;
    exit(1);
}

echo "このマイグレーションは以下のカラムをordersテーブルに追加します:" . PHP_EOL;
echo "- delivery_date (配達日)" . PHP_EOL;
echo "- company_id, company_code, company_name (企業関連)" . PHP_EOL;
echo "- department_id (部署ID)" . PHP_EOL;
echo "- category_code, category_name (カテゴリ関連)" . PHP_EOL;
echo "- supplier_id (仕入先ID)" . PHP_EOL;
echo "- corporation_code, corporation_name (法人関連)" . PHP_EOL;
echo "- employee_type_code, employee_type_name (社員区分)" . PHP_EOL;
echo "- delivery_time, cooperation_code (その他)" . PHP_EOL . PHP_EOL;

echo "実行しますか？ [y/N]: ";
$handle = fopen("php://stdin", "r");
$line = fgets($handle);
fclose($handle);

if (trim(strtolower($line)) !== 'y') {
    echo "キャンセルしました。" . PHP_EOL;
    exit(0);
}

echo PHP_EOL . "マイグレーションを実行中..." . PHP_EOL . PHP_EOL;

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();

    // マイグレーションSQLを読み込み
    $migrationFile = __DIR__ . '/sql/migration_add_orders_columns.sql';

    if (!file_exists($migrationFile)) {
        throw new Exception("マイグレーションファイルが見つかりません: " . $migrationFile);
    }

    $sql = file_get_contents($migrationFile);

    // コメント行と空行を削除
    $lines = explode("\n", $sql);
    $statements = [];
    $currentStatement = '';

    foreach ($lines as $line) {
        $line = trim($line);

        // コメント行と空行をスキップ
        if (empty($line) || substr($line, 0, 2) === '--') {
            continue;
        }

        $currentStatement .= $line . ' ';

        // セミコロンで終わる場合、ステートメント完了
        if (substr($line, -1) === ';') {
            $statements[] = trim($currentStatement);
            $currentStatement = '';
        }
    }

    // 各ステートメントを実行
    $successCount = 0;
    $errorCount = 0;
    $errors = [];

    foreach ($statements as $i => $statement) {
        if (empty($statement)) continue;

        try {
            $conn->exec($statement);
            $successCount++;
            echo "✓ ステートメント " . ($i + 1) . " 実行成功" . PHP_EOL;
        } catch (PDOException $e) {
            $errorCount++;
            $errorMsg = $e->getMessage();
            $errors[] = [
                'statement' => substr($statement, 0, 100) . '...',
                'error' => $errorMsg
            ];

            // Duplicate column nameエラーは無視（すでに存在する場合）
            if (strpos($errorMsg, 'Duplicate column name') !== false) {
                echo "⊘ ステートメント " . ($i + 1) . " スキップ（カラムは既に存在）" . PHP_EOL;
            } else {
                echo "✗ ステートメント " . ($i + 1) . " エラー: " . $errorMsg . PHP_EOL;
            }
        }
    }

    echo PHP_EOL . "=== マイグレーション完了 ===" . PHP_EOL;
    echo "成功: " . $successCount . " ステートメント" . PHP_EOL;
    echo "エラー/スキップ: " . $errorCount . " ステートメント" . PHP_EOL . PHP_EOL;

    if (!empty($errors)) {
        echo "エラー詳細:" . PHP_EOL;
        foreach ($errors as $error) {
            echo "- " . $error['error'] . PHP_EOL;
        }
        echo PHP_EOL;
    }

    // テーブル構造を確認
    echo "=== ordersテーブルの構造 ===" . PHP_EOL;
    $stmt = $conn->query("SHOW COLUMNS FROM orders");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "カラム数: " . count($columns) . PHP_EOL;
    echo "主要カラム:" . PHP_EOL;
    $importantColumns = ['delivery_date', 'company_id', 'department_id', 'category_code', 'supplier_id'];

    foreach ($columns as $column) {
        if (in_array($column['Field'], $importantColumns)) {
            echo "  ✓ " . $column['Field'] . " (" . $column['Type'] . ")" . PHP_EOL;
        }
    }

    echo PHP_EOL . "次の手順:" . PHP_EOL;
    echo "1. データ取込ページにアクセス" . PHP_EOL;
    echo "2. CSVファイルを再インポート" . PHP_EOL;
    echo "3. 集金管理画面でデータを確認" . PHP_EOL . PHP_EOL;

} catch (Exception $e) {
    echo "エラー: " . $e->getMessage() . PHP_EOL;
    exit(1);
}

echo "マイグレーションが正常に完了しました！" . PHP_EOL;
exit(0);
