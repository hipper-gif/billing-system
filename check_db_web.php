<?php
/**
 * データベース状態確認（Web版）
 *
 * ブラウザからアクセスしてデータベースの状態を確認
 * URL: https://twinklemark.xsrv.jp/Smiley/meal-delivery/billing-system/check_db_web.php
 *
 * @package Smiley配食事業システム
 * @version 1.0
 */

// セキュリティ: IPアドレス制限（必要に応じて設定）
// $allowedIPs = ['xxx.xxx.xxx.xxx'];
// if (!in_array($_SERVER['REMOTE_ADDR'], $allowedIPs)) {
//     die('Access Denied');
// }

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>データベース状態確認</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #4CAF50;
            padding-bottom: 10px;
        }
        h2 {
            color: #555;
            margin-top: 30px;
            border-left: 4px solid #2196F3;
            padding-left: 15px;
        }
        .status-ok { color: #4CAF50; font-weight: bold; }
        .status-warning { color: #FF9800; font-weight: bold; }
        .status-error { color: #f44336; font-weight: bold; }
        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196F3;
            padding: 15px;
            margin: 15px 0;
            border-radius: 4px;
        }
        .warning-box {
            background: #fff3e0;
            border-left: 4px solid #FF9800;
            padding: 15px;
            margin: 15px 0;
            border-radius: 4px;
        }
        .error-box {
            background: #ffebee;
            border-left: 4px solid #f44336;
            padding: 15px;
            margin: 15px 0;
            border-radius: 4px;
        }
        .success-box {
            background: #e8f5e9;
            border-left: 4px solid #4CAF50;
            padding: 15px;
            margin: 15px 0;
            border-radius: 4px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f5f5f5;
            font-weight: bold;
        }
        tr:hover {
            background-color: #f9f9f9;
        }
        code {
            background: #f5f5f5;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }
        pre {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
        }
        .icon-ok::before { content: "✅ "; }
        .icon-warning::before { content: "⚠️ "; }
        .icon-error::before { content: "❌ "; }
        .icon-info::before { content: "📊 "; }
    </style>
</head>
<body>
<div class="container">
    <h1>🔍 データベース状態確認 - ログイン問題デバッグ</h1>
    <p>実行日時: <?php echo date('Y-m-d H:i:s'); ?></p>

<?php
require_once __DIR__ . '/config/database.php';

try {
    echo '<h2>1. データベース接続確認</h2>';
    $db = Database::getInstance();

    echo '<div class="success-box">';
    echo '<span class="icon-ok">データベース接続成功</span><br>';
    echo '環境: <strong>' . ENVIRONMENT . '</strong><br>';
    echo 'データベース: <strong>' . DB_NAME . '</strong><br>';
    echo 'ホスト: <strong>' . DB_HOST . '</strong>';
    echo '</div>';

    // usersテーブルの存在確認
    echo '<h2>2. usersテーブルの存在確認</h2>';
    $tables = $db->fetchAll("SHOW TABLES");
    $tableNames = array_column($tables, 'Tables_in_' . DB_NAME);

    if (in_array('users', $tableNames)) {
        echo '<div class="success-box"><span class="icon-ok">usersテーブルが存在します</span></div>';
    } else {
        echo '<div class="error-box"><span class="icon-error">usersテーブルが存在しません！</span></div>';
        exit;
    }

    // usersテーブルの構造確認
    echo '<h2>3. usersテーブルの構造確認</h2>';
    $columns = $db->fetchAll("SHOW COLUMNS FROM users");
    $columnNames = array_column($columns, 'Field');

    echo '<table>';
    echo '<thead><tr><th>カラム名</th><th>型</th><th>NULL</th><th>デフォルト</th><th>キー</th></tr></thead>';
    echo '<tbody>';
    foreach ($columns as $column) {
        echo '<tr>';
        echo '<td><strong>' . htmlspecialchars($column['Field']) . '</strong></td>';
        echo '<td>' . htmlspecialchars($column['Type']) . '</td>';
        echo '<td>' . htmlspecialchars($column['Null']) . '</td>';
        echo '<td>' . htmlspecialchars($column['Default'] ?? 'NULL') . '</td>';
        echo '<td>' . htmlspecialchars($column['Key']) . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';

    // 必須カラムの存在確認
    echo '<h2>4. 認証に必要なカラムの存在確認</h2>';

    $requiredColumns = [
        'id' => '必須',
        'user_code' => '必須（ログインID）',
        'user_name' => '必須',
        'password_hash' => '認証に必須（パスワード保存）',
        'company_id' => 'AuthManagerで参照',
        'company_name' => 'AuthManagerで参照',
        'department' => 'AuthManagerで参照',
        'role' => '権限管理に必須',
        'is_active' => '必須（有効/無効フラグ）',
        'is_registered' => 'AuthManagerで参照（登録済みフラグ）'
    ];

    $missingColumns = [];
    $existingColumns = [];

    echo '<table>';
    echo '<thead><tr><th>カラム名</th><th>説明</th><th>ステータス</th></tr></thead>';
    echo '<tbody>';

    foreach ($requiredColumns as $column => $description) {
        echo '<tr>';
        echo '<td><code>' . htmlspecialchars($column) . '</code></td>';
        echo '<td>' . htmlspecialchars($description) . '</td>';

        if (in_array($column, $columnNames)) {
            echo '<td class="status-ok"><span class="icon-ok">存在</span></td>';
            $existingColumns[] = $column;
        } else {
            echo '<td class="status-error"><span class="icon-error">不足</span></td>';
            $missingColumns[] = $column;
        }
        echo '</tr>';
    }
    echo '</tbody></table>';

    // マイグレーション状態の判定
    if (!empty($missingColumns)) {
        echo '<div class="warning-box">';
        echo '<h3><span class="icon-warning">マイグレーションが必要です</span></h3>';
        echo '<p>以下のカラムが不足しています:</p>';
        echo '<ul>';
        foreach ($missingColumns as $col) {
            echo '<li><code>' . htmlspecialchars($col) . '</code></li>';
        }
        echo '</ul>';
        echo '<p><strong>実行コマンド:</strong></p>';
        echo '<pre>php run_users_auth_migration.php</pre>';
        echo '</div>';
    } else {
        echo '<div class="success-box"><span class="icon-ok">すべての必須カラムが存在します</span></div>';
    }

    // usersテーブルのデータ確認
    echo '<h2>5. usersテーブルのデータ確認</h2>';
    $userCount = $db->fetch("SELECT COUNT(*) as count FROM users");

    echo '<div class="info-box">';
    echo '<span class="icon-info">総ユーザー数: <strong>' . $userCount['count'] . '</strong> 件</span>';
    echo '</div>';

    if ($userCount['count'] > 0) {
        // 全ユーザーの基本情報を表示
        $selectColumns = ['user_code', 'user_name'];
        if (in_array('password_hash', $columnNames)) $selectColumns[] = 'password_hash';
        if (in_array('role', $columnNames)) $selectColumns[] = 'role';
        if (in_array('is_registered', $columnNames)) $selectColumns[] = 'is_registered';
        if (in_array('is_active', $columnNames)) $selectColumns[] = 'is_active';

        $sql = "SELECT " . implode(', ', $selectColumns) . " FROM users LIMIT 10";
        $users = $db->fetchAll($sql);

        echo '<h3>登録済みユーザー一覧（最大10件）</h3>';
        echo '<table>';
        echo '<thead><tr>';
        foreach ($selectColumns as $col) {
            echo '<th>' . htmlspecialchars($col) . '</th>';
        }
        echo '</tr></thead>';
        echo '<tbody>';

        foreach ($users as $user) {
            echo '<tr>';
            foreach ($selectColumns as $col) {
                if ($col === 'password_hash') {
                    $value = !empty($user[$col]) ? '設定済み (' . substr($user[$col], 0, 20) . '...)' : '未設定';
                } elseif ($col === 'is_registered' || $col === 'is_active') {
                    $value = $user[$col] ? '✓' : '✗';
                } else {
                    $value = $user[$col] ?? 'NULL';
                }
                echo '<td>' . htmlspecialchars($value) . '</td>';
            }
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    // テストユーザーの確認
    echo '<h2>6. テストユーザー（Smiley0007）の確認</h2>';
    $testUser = $db->fetch("SELECT * FROM users WHERE user_code = 'Smiley0007'");

    if ($testUser) {
        echo '<div class="success-box"><span class="icon-ok">テストユーザーが存在します</span></div>';

        echo '<h3>詳細情報</h3>';
        echo '<table>';
        echo '<thead><tr><th>項目</th><th>値</th></tr></thead>';
        echo '<tbody>';
        foreach ($testUser as $key => $value) {
            echo '<tr>';
            echo '<td><strong>' . htmlspecialchars($key) . '</strong></td>';

            if ($key === 'password_hash') {
                $display = $value ? '設定済み (' . substr($value, 0, 30) . '...)' : '未設定';
                echo '<td>' . htmlspecialchars($display) . '</td>';
            } else {
                echo '<td>' . htmlspecialchars($value ?? 'NULL') . '</td>';
            }
            echo '</tr>';
        }
        echo '</tbody></table>';

        // パスワード検証テスト
        if (isset($testUser['password_hash']) && !empty($testUser['password_hash'])) {
            echo '<h3>パスワード検証テスト</h3>';
            $testPassword = 'password123';
            $isValid = password_verify($testPassword, $testUser['password_hash']);

            if ($isValid) {
                echo '<div class="success-box">';
                echo '<span class="icon-ok">パスワード検証成功</span><br>';
                echo 'テストパスワード: <code>' . htmlspecialchars($testPassword) . '</code>';
                echo '</div>';
            } else {
                echo '<div class="error-box">';
                echo '<span class="icon-error">パスワード検証失敗</span><br>';
                echo 'テストパスワード: <code>' . htmlspecialchars($testPassword) . '</code>';
                echo '</div>';
            }
        } else {
            echo '<div class="warning-box">';
            echo '<span class="icon-warning">password_hashが設定されていません</span>';
            echo '</div>';
        }
    } else {
        echo '<div class="warning-box">';
        echo '<span class="icon-warning">テストユーザー "Smiley0007" が見つかりません</span><br>';
        echo 'マイグレーションを実行してテストユーザーを作成してください';
        echo '</div>';
    }

    // 診断結果まとめ
    echo '<h2>7. 診断結果まとめ</h2>';

    $issues = [];
    $status = "OK";

    if (!empty($missingColumns)) {
        $issues[] = "必須カラムが不足しています（" . count($missingColumns) . "個）";
        $status = "NG";
    }

    if (!$testUser) {
        $issues[] = "テストユーザー 'Smiley0007' が存在しません";
        if ($status === "OK") $status = "WARNING";
    } elseif (!isset($testUser['password_hash']) || empty($testUser['password_hash'])) {
        $issues[] = "テストユーザーのパスワードが設定されていません";
        if ($status === "OK") $status = "WARNING";
    }

    if ($status === "OK") {
        echo '<div class="success-box">';
        echo '<h3><span class="icon-ok">ステータス: 正常</span></h3>';
        echo '<p>ログイン機能が使用可能です</p>';
        echo '<h4>ログインテスト情報:</h4>';
        echo '<ul>';
        echo '<li>利用者コード: <code>Smiley0007</code></li>';
        echo '<li>パスワード: <code>password123</code></li>';
        echo '</ul>';
        echo '</div>';
    } else {
        echo '<div class="warning-box">';
        echo '<h3><span class="icon-warning">ステータス: ' . htmlspecialchars($status) . '</span></h3>';
        echo '<p>問題が検出されました:</p>';
        echo '<ul>';
        foreach ($issues as $issue) {
            echo '<li>' . htmlspecialchars($issue) . '</li>';
        }
        echo '</ul>';

        echo '<h4>推奨アクション:</h4>';
        if (!empty($missingColumns)) {
            echo '<p>マイグレーションを実行:</p>';
            echo '<pre>php run_users_auth_migration.php</pre>';
        }
        echo '</div>';
    }

} catch (Exception $e) {
    echo '<div class="error-box">';
    echo '<h3><span class="icon-error">エラーが発生しました</span></h3>';
    echo '<p><strong>エラー詳細:</strong></p>';
    echo '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
    echo '<p><strong>スタックトレース:</strong></p>';
    echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
    echo '</div>';
}
?>

</div>
</body>
</html>
