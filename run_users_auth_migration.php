<?php
/**
 * usersテーブル認証カラム追加マイグレーション実行スクリプト
 *
 * 目的: ログイン認証に必要なカラムをusersテーブルに追加
 * 実行方法: php run_users_auth_migration.php
 *
 * @package Smiley配食事業システム
 * @version 1.0
 * @date 2025-12-20
 */

// エラー表示設定
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "========================================================================\n";
echo "Smiley配食事業 - usersテーブル認証カラム追加マイグレーション\n";
echo "========================================================================\n\n";

// config/database.php読み込み
require_once __DIR__ . '/config/database.php';

try {
    echo "📝 マイグレーション開始: " . date('Y-m-d H:i:s') . "\n\n";

    // データベース接続
    echo "🔌 データベース接続中...\n";
    $db = Database::getInstance();
    echo "✅ データベース接続成功\n";
    echo "   環境: " . ENVIRONMENT . "\n";
    echo "   データベース: " . DB_NAME . "\n\n";

    // トランザクション開始
    echo "🔄 トランザクション開始...\n";
    $db->beginTransaction();

    // マイグレーションSQLファイル読み込み
    $sqlFile = __DIR__ . '/sql/migration_add_users_auth_columns.sql';

    if (!file_exists($sqlFile)) {
        throw new Exception("マイグレーションファイルが見つかりません: {$sqlFile}");
    }

    echo "📂 マイグレーションファイル読み込み...\n";
    echo "   ファイル: {$sqlFile}\n";
    $sqlContent = file_get_contents($sqlFile);
    echo "✅ ファイル読み込み成功\n\n";

    // SQLをセミコロンで分割して実行
    echo "⚙️ マイグレーション実行中...\n\n";

    // コメントと空行を削除
    $sqlStatements = array_filter(
        array_map('trim', explode(';', $sqlContent)),
        function($statement) {
            // 空行とコメント行を除外
            $statement = trim($statement);
            if (empty($statement)) return false;
            if (strpos($statement, '--') === 0) return false;
            if (strpos($statement, '/*') === 0) return false;
            return true;
        }
    );

    $executedCount = 0;
    $skippedCount = 0;

    foreach ($sqlStatements as $index => $statement) {
        $statement = trim($statement);

        // 空のステートメントをスキップ
        if (empty($statement)) {
            continue;
        }

        // コメント行をスキップ
        if (strpos($statement, '--') === 0 || strpos($statement, '/*') === 0) {
            continue;
        }

        // SELECT文は結果を表示
        if (stripos($statement, 'SELECT') === 0) {
            try {
                echo "📊 クエリ実行: " . substr($statement, 0, 60) . "...\n";
                $result = $db->fetchAll($statement);
                if ($result) {
                    foreach ($result as $row) {
                        foreach ($row as $key => $value) {
                            echo "   {$key}: {$value}\n";
                        }
                    }
                }
                $executedCount++;
            } catch (Exception $e) {
                echo "⚠️ SELECT実行時の警告: " . $e->getMessage() . "\n";
            }
            echo "\n";
            continue;
        }

        // SHOW文は結果を表示
        if (stripos($statement, 'SHOW') === 0) {
            try {
                echo "📋 " . substr($statement, 0, 60) . "...\n";
                $result = $db->fetchAll($statement);
                if ($result) {
                    echo "   カラム数: " . count($result) . "\n";
                    foreach ($result as $row) {
                        if (isset($row['Field'])) {
                            echo "   - {$row['Field']} ({$row['Type']})\n";
                        }
                    }
                }
                $executedCount++;
            } catch (Exception $e) {
                echo "⚠️ SHOW実行時の警告: " . $e->getMessage() . "\n";
            }
            echo "\n";
            continue;
        }

        // その他のSQL文を実行
        try {
            // ALTER TABLEの場合
            if (stripos($statement, 'ALTER TABLE') === 0) {
                echo "🔧 " . substr($statement, 0, 100) . "...\n";
            }
            // INSERTの場合
            elseif (stripos($statement, 'INSERT') === 0) {
                echo "➕ " . substr($statement, 0, 100) . "...\n";
            }
            // UPDATEの場合
            elseif (stripos($statement, 'UPDATE') === 0) {
                echo "🔄 " . substr($statement, 0, 100) . "...\n";
            }
            // その他
            else {
                echo "⚙️ " . substr($statement, 0, 100) . "...\n";
            }

            $db->query($statement);
            echo "   ✅ 実行成功\n";
            $executedCount++;

        } catch (Exception $e) {
            // "Duplicate column name"エラーはスキップ（既に存在する場合）
            if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
                echo "   ⏭️ スキップ（既に存在）\n";
                $skippedCount++;
            }
            // "Duplicate key name"エラーもスキップ
            elseif (strpos($e->getMessage(), 'Duplicate key name') !== false) {
                echo "   ⏭️ スキップ（既に存在）\n";
                $skippedCount++;
            }
            // "Duplicate entry"エラーもスキップ
            elseif (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                echo "   ⏭️ スキップ（既に存在）\n";
                $skippedCount++;
            }
            else {
                throw $e; // その他のエラーは再スロー
            }
        }

        echo "\n";
    }

    // コミット
    echo "💾 トランザクションコミット中...\n";
    $db->commit();
    echo "✅ コミット成功\n\n";

    // 結果サマリー
    echo "========================================================================\n";
    echo "✅ マイグレーション完了\n\n";
    echo "📊 実行結果:\n";
    echo "   実行成功: {$executedCount} 件\n";
    echo "   スキップ: {$skippedCount} 件\n";
    echo "   合計: " . ($executedCount + $skippedCount) . " 件\n\n";

    // usersテーブルの構造確認
    echo "🔍 usersテーブル構造確認:\n";
    $columns = $db->fetchAll("SHOW COLUMNS FROM users");
    foreach ($columns as $column) {
        $null = $column['Null'] === 'YES' ? 'NULL' : 'NOT NULL';
        $default = $column['Default'] !== null ? "DEFAULT '{$column['Default']}'" : '';
        echo "   - {$column['Field']}: {$column['Type']} {$null} {$default}\n";
    }
    echo "\n";

    // テストユーザー確認
    echo "👤 テストユーザー確認:\n";
    $testUser = $db->fetch("SELECT user_code, user_name, role, is_registered, is_active FROM users WHERE user_code = 'Smiley0007'");
    if ($testUser) {
        echo "   ✅ テストユーザー 'Smiley0007' が作成されました\n";
        echo "      - 氏名: {$testUser['user_name']}\n";
        echo "      - ロール: {$testUser['role']}\n";
        echo "      - 登録状態: " . ($testUser['is_registered'] ? '登録済み' : '未登録') . "\n";
        echo "      - 有効状態: " . ($testUser['is_active'] ? '有効' : '無効') . "\n";
        echo "\n";
        echo "   🔐 ログイン情報:\n";
        echo "      利用者コード: Smiley0007\n";
        echo "      パスワード: password123\n";
    } else {
        echo "   ⚠️ テストユーザーが見つかりません\n";
    }

    echo "\n";
    echo "========================================================================\n";
    echo "🎉 マイグレーション処理が正常に完了しました\n";
    echo "完了時刻: " . date('Y-m-d H:i:s') . "\n";
    echo "========================================================================\n";

} catch (Exception $e) {
    // ロールバック
    if (isset($db)) {
        echo "\n❌ エラーが発生しました。ロールバック中...\n";
        $db->rollback();
        echo "✅ ロールバック完了\n\n";
    }

    echo "========================================================================\n";
    echo "❌ マイグレーション失敗\n";
    echo "========================================================================\n";
    echo "エラー詳細:\n";
    echo $e->getMessage() . "\n";
    echo "\nスタックトレース:\n";
    echo $e->getTraceAsString() . "\n";
    echo "========================================================================\n";

    exit(1);
}
