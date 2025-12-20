<?php
/**
 * ユーザーパスワード設定ツール
 *
 * 任意のユーザーのパスワードを設定
 * URL: https://twinklemark.xsrv.jp/Smiley/meal-delivery/billing-system/set_user_password.php
 *
 * @package Smiley配食事業システム
 * @version 1.0
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/html; charset=UTF-8');

require_once __DIR__ . '/config/database.php';

// POSTリクエスト処理
$updated = false;
$error = null;
$message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        $db = Database::getInstance();

        if ($_POST['action'] === 'set_password') {
            $userCode = trim($_POST['user_code'] ?? '');
            $password = $_POST['password'] ?? '';
            $role = $_POST['role'] ?? 'user';

            // バリデーション
            if (empty($userCode)) {
                $error = "利用者コードを入力してください";
            } elseif (empty($password)) {
                $error = "パスワードを入力してください";
            } elseif (strlen($password) < 6) {
                $error = "パスワードは6文字以上で入力してください";
            } else {
                // ユーザーの存在確認
                $existingUser = $db->fetch("SELECT * FROM users WHERE user_code = :user_code", ['user_code' => $userCode]);

                if (!$existingUser) {
                    $error = "利用者コード '{$userCode}' は存在しません";
                } else {
                    // パスワードハッシュを生成
                    $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

                    // データベース更新
                    $sql = "UPDATE users
                            SET password_hash = :password_hash,
                                role = :role,
                                is_registered = 1,
                                is_active = 1,
                                registered_at = COALESCE(registered_at, NOW()),
                                updated_at = NOW()
                            WHERE user_code = :user_code";

                    $db->query($sql, [
                        'password_hash' => $passwordHash,
                        'role' => $role,
                        'user_code' => $userCode
                    ]);

                    // 検証
                    $updatedUser = $db->fetch("SELECT * FROM users WHERE user_code = :user_code", ['user_code' => $userCode]);

                    if ($updatedUser && password_verify($password, $updatedUser['password_hash'])) {
                        $updated = true;
                        $message = "利用者コード '{$userCode}' のパスワードを正常に設定しました";
                    } else {
                        $error = "更新は完了しましたが、検証に失敗しました";
                    }
                }
            }
        }

    } catch (Exception $e) {
        $error = "エラー: " . $e->getMessage();
    }
}

// ユーザー一覧取得
try {
    $db = Database::getInstance();

    // カラムの存在確認
    $columns = $db->fetchAll("SHOW COLUMNS FROM users");
    $columnNames = array_column($columns, 'Field');

    $selectColumns = ['user_code', 'user_name'];
    if (in_array('password_hash', $columnNames)) $selectColumns[] = 'password_hash';
    if (in_array('role', $columnNames)) $selectColumns[] = 'role';
    if (in_array('is_registered', $columnNames)) $selectColumns[] = 'is_registered';
    if (in_array('is_active', $columnNames)) $selectColumns[] = 'is_active';
    if (in_array('company_name', $columnNames)) $selectColumns[] = 'company_name';

    $sql = "SELECT " . implode(', ', $selectColumns) . " FROM users ORDER BY user_code";
    $users = $db->fetchAll($sql);

} catch (Exception $e) {
    $error = "データベース接続エラー: " . $e->getMessage();
    $users = [];
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ユーザーパスワード設定ツール</title>
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
        .success-box {
            background: #e8f5e9;
            border-left: 4px solid #4CAF50;
            padding: 20px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .error-box {
            background: #ffebee;
            border-left: 4px solid #f44336;
            padding: 20px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196F3;
            padding: 20px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .warning-box {
            background: #fff3e0;
            border-left: 4px solid #FF9800;
            padding: 20px;
            margin: 20px 0;
            border-radius: 4px;
        }
        button {
            background: #4CAF50;
            color: white;
            border: none;
            padding: 12px 30px;
            font-size: 16px;
            border-radius: 4px;
            cursor: pointer;
            margin: 10px 5px 10px 0;
        }
        button:hover {
            background: #45a049;
        }
        input[type="text"], input[type="password"], select {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 14px;
        }
        .form-group {
            margin: 20px 0;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #555;
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
        .user-code-link {
            color: #2196F3;
            cursor: pointer;
            text-decoration: underline;
        }
        .user-code-link:hover {
            color: #0b7dda;
        }
        code {
            background: #f5f5f5;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }
        .quick-buttons {
            margin: 20px 0;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .quick-button {
            background: #2196F3;
            padding: 10px 20px;
            font-size: 14px;
        }
        .quick-button:hover {
            background: #0b7dda;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>🔐 ユーザーパスワード設定ツール</h1>
    <p>実行日時: <?php echo date('Y-m-d H:i:s'); ?></p>

    <?php if ($updated): ?>
        <div class="success-box">
            <h3>✅ パスワード設定完了</h3>
            <p><?php echo htmlspecialchars($message); ?></p>
            <p><a href="pages/login.php" style="color: #4CAF50; font-weight: bold;">→ ログインページへ</a></p>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="error-box">
            <h3>❌ エラー</h3>
            <p><?php echo htmlspecialchars($error); ?></p>
        </div>
    <?php endif; ?>

    <div class="warning-box">
        <h3>⚠️ 重要な注意事項</h3>
        <ul>
            <li><strong>このツールは管理者のみが使用してください</strong></li>
            <li>ユーザーのパスワードを設定・変更します</li>
            <li>設定完了後は、このファイルを削除することを推奨します</li>
            <li>パスワードは6文字以上で設定してください</li>
        </ul>
    </div>

    <h2>1. パスワード設定フォーム</h2>

    <div class="info-box">
        <p><strong>クイックアクション:</strong> よく使用されるユーザー</p>
        <div class="quick-buttons">
            <button class="quick-button" onclick="setUserCode('Smiley9999')">Smiley9999（管理者）</button>
            <button class="quick-button" onclick="setUserCode('Smiley0007')">Smiley0007（テスト）</button>
        </div>
    </div>

    <form method="POST" id="passwordForm">
        <input type="hidden" name="action" value="set_password">

        <div class="form-group">
            <label for="user_code">利用者コード *</label>
            <input type="text"
                   id="user_code"
                   name="user_code"
                   placeholder="例: Smiley9999"
                   required
                   autocomplete="off">
            <small>下の一覧表から選択することもできます</small>
        </div>

        <div class="form-group">
            <label for="password">新しいパスワード *</label>
            <input type="password"
                   id="password"
                   name="password"
                   placeholder="6文字以上"
                   required
                   minlength="6">
        </div>

        <div class="form-group">
            <label for="password_confirm">パスワード確認 *</label>
            <input type="password"
                   id="password_confirm"
                   name="password_confirm"
                   placeholder="パスワードを再入力"
                   required
                   minlength="6">
        </div>

        <div class="form-group">
            <label for="role">ロール *</label>
            <select id="role" name="role" required>
                <option value="user">user - 一般利用者</option>
                <option value="company_admin">company_admin - 企業管理者</option>
                <option value="smiley_staff">smiley_staff - Smileyスタッフ</option>
                <option value="admin">admin - システム管理者</option>
            </select>
        </div>

        <button type="submit">パスワードを設定</button>
    </form>

    <h2>2. 登録済みユーザー一覧</h2>

    <?php if (count($users) > 0): ?>
        <p>ユーザーコードをクリックすると、上のフォームに自動入力されます</p>
        <table>
            <thead>
                <tr>
                    <th>利用者コード</th>
                    <th>氏名</th>
                    <?php if (in_array('company_name', $columnNames)): ?>
                    <th>会社名</th>
                    <?php endif; ?>
                    <?php if (in_array('password_hash', $columnNames)): ?>
                    <th>パスワード</th>
                    <?php endif; ?>
                    <?php if (in_array('role', $columnNames)): ?>
                    <th>ロール</th>
                    <?php endif; ?>
                    <?php if (in_array('is_active', $columnNames)): ?>
                    <th>状態</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                <tr>
                    <td>
                        <span class="user-code-link" onclick="setUserCode('<?php echo htmlspecialchars($user['user_code']); ?>')">
                            <?php echo htmlspecialchars($user['user_code']); ?>
                        </span>
                    </td>
                    <td><?php echo htmlspecialchars($user['user_name']); ?></td>
                    <?php if (in_array('company_name', $columnNames)): ?>
                    <td><?php echo htmlspecialchars($user['company_name'] ?? '-'); ?></td>
                    <?php endif; ?>
                    <?php if (in_array('password_hash', $columnNames)): ?>
                    <td>
                        <?php if (!empty($user['password_hash'])): ?>
                            <span style="color: #4CAF50;">✓ 設定済み</span>
                        <?php else: ?>
                            <span style="color: #f44336;">✗ 未設定</span>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                    <?php if (in_array('role', $columnNames)): ?>
                    <td><?php echo htmlspecialchars($user['role'] ?? '-'); ?></td>
                    <?php endif; ?>
                    <?php if (in_array('is_active', $columnNames)): ?>
                    <td>
                        <?php if ($user['is_active']): ?>
                            <span style="color: #4CAF50;">✓ 有効</span>
                        <?php else: ?>
                            <span style="color: #999;">✗ 無効</span>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="warning-box">
            <p>ユーザーが見つかりません</p>
        </div>
    <?php endif; ?>

    <h2>3. 推奨設定</h2>

    <div class="info-box">
        <h4>管理者アカウント（Smiley9999）の推奨設定:</h4>
        <ul>
            <li>利用者コード: <code>Smiley9999</code></li>
            <li>ロール: <code>admin</code> または <code>smiley_staff</code></li>
            <li>パスワード: <strong>安全なパスワードを設定</strong>（8文字以上推奨）</li>
        </ul>

        <h4>テストアカウント（Smiley0007）:</h4>
        <ul>
            <li>利用者コード: <code>Smiley0007</code></li>
            <li>パスワード: <code>password123</code>（既に設定済み）</li>
            <li>ロール: <code>smiley_staff</code></li>
        </ul>
    </div>

</div>

<script>
// ユーザーコードをフォームに設定
function setUserCode(userCode) {
    document.getElementById('user_code').value = userCode;
    document.getElementById('user_code').focus();

    // スクロールしてフォームを表示
    document.getElementById('passwordForm').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

// パスワード確認のバリデーション
document.getElementById('passwordForm').addEventListener('submit', function(e) {
    var password = document.getElementById('password').value;
    var passwordConfirm = document.getElementById('password_confirm').value;

    if (password !== passwordConfirm) {
        e.preventDefault();
        alert('パスワードとパスワード確認が一致しません');
        return false;
    }

    if (password.length < 6) {
        e.preventDefault();
        alert('パスワードは6文字以上で入力してください');
        return false;
    }

    return true;
});
</script>

</body>
</html>
