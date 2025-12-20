<?php
/**
 * データベース状態確認スクリプト
 *
 * ログイン問題のデバッグ用
 * - usersテーブルの構造確認
 * - 必要なカラムの存在確認
 * - テストユーザーの存在確認
 *
 * @package Smiley配食事業システム
 * @version 1.0
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "========================================================================\n";
echo "データベース状態確認 - ログイン問題デバッグ\n";
echo "========================================================================\n\n";

require_once __DIR__ . '/config/database.php';

try {
    echo "🔌 データベース接続中...\n";
    $db = Database::getInstance();
    echo "✅ データベース接続成功\n";
    echo "   環境: " . ENVIRONMENT . "\n";
    echo "   データベース: " . DB_NAME . "\n";
    echo "   ホスト: " . DB_HOST . "\n\n";

    // 1. usersテーブルの存在確認
    echo "========================================================================\n";
    echo "1. usersテーブルの存在確認\n";
    echo "========================================================================\n";

    $tables = $db->fetchAll("SHOW TABLES");
    $tableNames = array_column($tables, 'Tables_in_' . DB_NAME);

    if (in_array('users', $tableNames)) {
        echo "✅ usersテーブルが存在します\n\n";
    } else {
        echo "❌ usersテーブルが存在しません！\n";
        echo "   初期化が必要です: php -f sql/init.sql\n\n";
        exit(1);
    }

    // 2. usersテーブルの構造確認
    echo "========================================================================\n";
    echo "2. usersテーブルの構造確認\n";
    echo "========================================================================\n";

    $columns = $db->fetchAll("SHOW COLUMNS FROM users");
    $columnNames = array_column($columns, 'Field');

    echo "📋 現在のカラム一覧:\n";
    foreach ($columns as $column) {
        $null = $column['Null'] === 'YES' ? 'NULL' : 'NOT NULL';
        $default = $column['Default'] !== null ? "DEFAULT '{$column['Default']}'" : '';
        $key = $column['Key'] ? "[{$column['Key']}]" : '';
        echo "   - {$column['Field']}: {$column['Type']} {$null} {$default} {$key}\n";
    }
    echo "\n";

    // 3. 必須カラムの存在確認
    echo "========================================================================\n";
    echo "3. 認証に必要なカラムの存在確認\n";
    echo "========================================================================\n";

    $requiredColumns = [
        'id' => '必須',
        'user_code' => '必須',
        'user_name' => '必須',
        'password_hash' => '認証に必須',
        'company_id' => 'AuthManagerで参照',
        'company_name' => 'AuthManagerで参照',
        'department' => 'AuthManagerで参照',
        'role' => '権限管理に必須',
        'is_active' => '必須',
        'is_registered' => 'AuthManagerで参照'
    ];

    $missingColumns = [];
    $existingColumns = [];

    foreach ($requiredColumns as $column => $description) {
        if (in_array($column, $columnNames)) {
            echo "   ✅ {$column}: 存在 ({$description})\n";
            $existingColumns[] = $column;
        } else {
            echo "   ❌ {$column}: 不足 ({$description})\n";
            $missingColumns[] = $column;
        }
    }
    echo "\n";

    // 4. マイグレーション状態の判定
    if (!empty($missingColumns)) {
        echo "========================================================================\n";
        echo "⚠️ マイグレーションが必要です\n";
        echo "========================================================================\n";
        echo "不足しているカラム:\n";
        foreach ($missingColumns as $col) {
            echo "   - {$col}\n";
        }
        echo "\n";
        echo "📝 実行コマンド:\n";
        echo "   php run_users_auth_migration.php\n\n";
    } else {
        echo "✅ すべての必須カラムが存在します\n\n";
    }

    // 5. usersテーブルのデータ件数確認
    echo "========================================================================\n";
    echo "4. usersテーブルのデータ確認\n";
    echo "========================================================================\n";

    $userCount = $db->fetch("SELECT COUNT(*) as count FROM users");
    echo "📊 総ユーザー数: {$userCount['count']} 件\n\n";

    if ($userCount['count'] > 0) {
        // 全ユーザーの基本情報を表示
        echo "👥 登録済みユーザー一覧:\n";

        // カラムの存在に応じてクエリを構築
        $selectColumns = ['user_code', 'user_name'];
        if (in_array('password_hash', $columnNames)) $selectColumns[] = 'password_hash';
        if (in_array('role', $columnNames)) $selectColumns[] = 'role';
        if (in_array('is_registered', $columnNames)) $selectColumns[] = 'is_registered';
        if (in_array('is_active', $columnNames)) $selectColumns[] = 'is_active';

        $sql = "SELECT " . implode(', ', $selectColumns) . " FROM users LIMIT 10";
        $users = $db->fetchAll($sql);

        foreach ($users as $user) {
            echo "\n   利用者コード: {$user['user_code']}\n";
            echo "   氏名: {$user['user_name']}\n";

            if (isset($user['password_hash'])) {
                $pwdStatus = !empty($user['password_hash']) ? 'パスワード設定済み' : 'パスワード未設定';
                echo "   パスワード: {$pwdStatus}\n";
            }

            if (isset($user['role'])) {
                echo "   ロール: {$user['role']}\n";
            }

            if (isset($user['is_registered'])) {
                $regStatus = $user['is_registered'] ? '登録済み' : '未登録';
                echo "   登録状態: {$regStatus}\n";
            }

            if (isset($user['is_active'])) {
                $activeStatus = $user['is_active'] ? '有効' : '無効';
                echo "   有効状態: {$activeStatus}\n";
            }
        }
        echo "\n";
    }

    // 6. テストユーザー（Smiley0007）の確認
    echo "========================================================================\n";
    echo "5. テストユーザー（Smiley0007）の確認\n";
    echo "========================================================================\n";

    $testUser = $db->fetch("SELECT * FROM users WHERE user_code = 'Smiley0007'");

    if ($testUser) {
        echo "✅ テストユーザーが存在します\n\n";
        echo "詳細情報:\n";
        foreach ($testUser as $key => $value) {
            if ($key === 'password_hash') {
                $display = $value ? '設定済み (' . substr($value, 0, 20) . '...)' : '未設定';
                echo "   {$key}: {$display}\n";
            } else {
                echo "   {$key}: " . ($value ?? 'NULL') . "\n";
            }
        }
        echo "\n";

        // パスワード検証テスト
        if (isset($testUser['password_hash']) && !empty($testUser['password_hash'])) {
            echo "🔐 パスワード検証テスト:\n";
            $testPassword = 'password123';
            $isValid = password_verify($testPassword, $testUser['password_hash']);

            if ($isValid) {
                echo "   ✅ パスワード 'password123' が正しく検証できました\n";
            } else {
                echo "   ❌ パスワード検証に失敗しました\n";
                echo "   テストパスワード: {$testPassword}\n";
            }
        } else {
            echo "⚠️ password_hashが設定されていません\n";
        }
    } else {
        echo "❌ テストユーザー 'Smiley0007' が見つかりません\n";
        echo "   マイグレーションを実行してテストユーザーを作成してください\n";
    }
    echo "\n";

    // 7. 診断結果まとめ
    echo "========================================================================\n";
    echo "📊 診断結果まとめ\n";
    echo "========================================================================\n";

    $issues = [];
    $status = "OK";

    if (!empty($missingColumns)) {
        $issues[] = "必須カラムが不足しています（" . count($missingColumns) . "個）";
        $status = "NG";
    }

    if (!$testUser) {
        $issues[] = "テストユーザー 'Smiley0007' が存在しません";
        $status = "WARNING";
    } elseif (!isset($testUser['password_hash']) || empty($testUser['password_hash'])) {
        $issues[] = "テストユーザーのパスワードが設定されていません";
        $status = "WARNING";
    }

    if ($status === "OK") {
        echo "✅ ステータス: 正常\n";
        echo "   ログイン機能が使用可能です\n\n";

        echo "🔐 ログインテスト情報:\n";
        echo "   利用者コード: Smiley0007\n";
        echo "   パスワード: password123\n";
    } else {
        echo "⚠️ ステータス: {$status}\n";
        echo "   問題が検出されました:\n";
        foreach ($issues as $issue) {
            echo "   - {$issue}\n";
        }
        echo "\n";

        echo "🔧 推奨アクション:\n";
        if (!empty($missingColumns)) {
            echo "   1. マイグレーションを実行:\n";
            echo "      php run_users_auth_migration.php\n\n";
        }
    }

    echo "========================================================================\n";
    echo "完了時刻: " . date('Y-m-d H:i:s') . "\n";
    echo "========================================================================\n";

} catch (Exception $e) {
    echo "\n";
    echo "========================================================================\n";
    echo "❌ エラーが発生しました\n";
    echo "========================================================================\n";
    echo "エラー詳細: {$e->getMessage()}\n";
    echo "スタックトレース:\n{$e->getTraceAsString()}\n";
    echo "========================================================================\n";
    exit(1);
}
