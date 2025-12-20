<?php
/**
 * パスワードハッシュ修正ツール（Web版）
 *
 * テストユーザーのパスワードハッシュを正しい値に更新
 * URL: https://twinklemark.xsrv.jp/Smiley/meal-delivery/billing-system/fix_password_hash.php
 *
 * @package Smiley配食事業システム
 * @version 1.0
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/html; charset=UTF-8');

// POSTリクエスト処理
$updated = false;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    require_once __DIR__ . '/config/database.php';

    try {
        $db = Database::getInstance();

        if ($_POST['action'] === 'fix_test_user') {
            // テストユーザーのパスワードハッシュを修正
            $userCode = 'Smiley0007';
            $password = 'password123';

            // 新しいパスワードハッシュを生成
            $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

            // データベース更新
            $sql = "UPDATE users
                    SET password_hash = :password_hash,
                        role = 'smiley_staff',
                        is_registered = 1,
                        is_active = 1,
                        registered_at = NOW()
                    WHERE user_code = :user_code";

            $db->query($sql, [
                'password_hash' => $passwordHash,
                'user_code' => $userCode
            ]);

            // 検証
            $testUser = $db->fetch("SELECT * FROM users WHERE user_code = :user_code", ['user_code' => $userCode]);

            if ($testUser && password_verify($password, $testUser['password_hash'])) {
                $updated = true;
            } else {
                $error = "更新は完了しましたが、検証に失敗しました。";
            }
        } elseif ($_POST['action'] === 'generate_hash') {
            // カスタムパスワードのハッシュを生成
            $customPassword = $_POST['custom_password'] ?? '';
            if (!empty($customPassword)) {
                $generatedHash = password_hash($customPassword, PASSWORD_BCRYPT, ['cost' => 12]);
            }
        }

    } catch (Exception $e) {
        $error = "エラー: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>パスワードハッシュ修正ツール</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 1000px;
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
            margin: 10px 5px;
        }
        button:hover {
            background: #45a049;
        }
        button.secondary {
            background: #2196F3;
        }
        button.secondary:hover {
            background: #0b7dda;
        }
        code {
            background: #f5f5f5;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
        }
        pre {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
            border: 1px solid #ddd;
        }
        input[type="text"], input[type="password"] {
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
        .hash-output {
            background: #f9f9f9;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-top: 10px;
            word-break: break-all;
            font-family: 'Courier New', monospace;
            font-size: 12px;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>🔐 パスワードハッシュ修正ツール</h1>
    <p>実行日時: <?php echo date('Y-m-d H:i:s'); ?></p>

    <?php if ($updated): ?>
        <div class="success-box">
            <h3>✅ パスワードハッシュの修正が完了しました！</h3>
            <p>テストユーザー <strong>Smiley0007</strong> のパスワードハッシュを正常に更新しました。</p>
            <p>以下の情報でログインできます：</p>
            <ul>
                <li>利用者コード: <code>Smiley0007</code></li>
                <li>パスワード: <code>password123</code></li>
            </ul>
            <p><a href="pages/login.php" style="color: #4CAF50; font-weight: bold;">→ ログインページへ</a></p>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="error-box">
            <h3>❌ エラーが発生しました</h3>
            <p><?php echo htmlspecialchars($error); ?></p>
        </div>
    <?php endif; ?>

    <div class="warning-box">
        <h3>⚠️ 注意事項</h3>
        <ul>
            <li>このツールはテスト環境でのみ使用してください</li>
            <li>本番環境で使用する場合は、必ずバックアップを取得してください</li>
            <li>修正完了後は、このファイルを削除することを推奨します</li>
        </ul>
    </div>

    <h2>1. テストユーザーのパスワードハッシュを修正</h2>

    <div class="info-box">
        <p>テストユーザー <strong>Smiley0007</strong> のパスワードハッシュを以下の内容で更新します：</p>
        <ul>
            <li>利用者コード: <code>Smiley0007</code></li>
            <li>パスワード: <code>password123</code></li>
            <li>ロール: <code>smiley_staff</code></li>
            <li>登録状態: <code>登録済み</code></li>
            <li>有効状態: <code>有効</code></li>
        </ul>
    </div>

    <form method="POST">
        <input type="hidden" name="action" value="fix_test_user">
        <button type="submit">テストユーザーのパスワードハッシュを修正</button>
    </form>

    <h2>2. カスタムパスワードのハッシュを生成</h2>

    <div class="info-box">
        <p>任意のパスワードのハッシュ値を生成します。</p>
        <p>生成されたハッシュを使用して、手動でデータベースを更新できます。</p>
    </div>

    <form method="POST">
        <input type="hidden" name="action" value="generate_hash">
        <div class="form-group">
            <label for="custom_password">パスワードを入力:</label>
            <input type="password" id="custom_password" name="custom_password" placeholder="パスワードを入力">
        </div>
        <button type="submit" class="secondary">ハッシュを生成</button>
    </form>

    <?php if (isset($generatedHash)): ?>
        <div class="success-box">
            <h3>✅ ハッシュ生成完了</h3>
            <p>入力されたパスワードのbcryptハッシュ:</p>
            <div class="hash-output"><?php echo htmlspecialchars($generatedHash); ?></div>

            <h4>このハッシュを使用してユーザーを更新するSQL:</h4>
            <pre>UPDATE users
SET password_hash = '<?php echo htmlspecialchars($generatedHash); ?>',
    is_registered = 1,
    registered_at = NOW()
WHERE user_code = 'ユーザーコード';</pre>
        </div>
    <?php endif; ?>

    <h2>3. 現在のテストユーザーの状態確認</h2>

    <?php
    try {
        require_once __DIR__ . '/config/database.php';
        $db = Database::getInstance();
        $testUser = $db->fetch("SELECT * FROM users WHERE user_code = 'Smiley0007'");

        if ($testUser):
    ?>
        <div class="info-box">
            <h4>現在の状態:</h4>
            <table style="width: 100%; border-collapse: collapse;">
                <tr style="border-bottom: 1px solid #ddd;">
                    <td style="padding: 10px; font-weight: bold;">利用者コード:</td>
                    <td style="padding: 10px;"><?php echo htmlspecialchars($testUser['user_code']); ?></td>
                </tr>
                <tr style="border-bottom: 1px solid #ddd;">
                    <td style="padding: 10px; font-weight: bold;">氏名:</td>
                    <td style="padding: 10px;"><?php echo htmlspecialchars($testUser['user_name']); ?></td>
                </tr>
                <tr style="border-bottom: 1px solid #ddd;">
                    <td style="padding: 10px; font-weight: bold;">password_hash:</td>
                    <td style="padding: 10px; word-break: break-all; font-family: monospace; font-size: 11px;">
                        <?php echo htmlspecialchars($testUser['password_hash'] ?? 'NULL'); ?>
                    </td>
                </tr>
                <tr style="border-bottom: 1px solid #ddd;">
                    <td style="padding: 10px; font-weight: bold;">ロール:</td>
                    <td style="padding: 10px;"><?php echo htmlspecialchars($testUser['role'] ?? 'NULL'); ?></td>
                </tr>
                <tr style="border-bottom: 1px solid #ddd;">
                    <td style="padding: 10px; font-weight: bold;">登録状態:</td>
                    <td style="padding: 10px;">
                        <?php echo isset($testUser['is_registered']) && $testUser['is_registered'] ? '✓ 登録済み' : '✗ 未登録'; ?>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 10px; font-weight: bold;">有効状態:</td>
                    <td style="padding: 10px;">
                        <?php echo $testUser['is_active'] ? '✓ 有効' : '✗ 無効'; ?>
                    </td>
                </tr>
            </table>

            <?php
            // パスワード検証テスト
            if (!empty($testUser['password_hash'])):
                $testPassword = 'password123';
                $isValid = password_verify($testPassword, $testUser['password_hash']);
            ?>
                <h4 style="margin-top: 20px;">パスワード検証テスト:</h4>
                <?php if ($isValid): ?>
                    <p style="color: #4CAF50; font-weight: bold;">✅ パスワード 'password123' で検証成功</p>
                <?php else: ?>
                    <p style="color: #f44336; font-weight: bold;">❌ パスワード 'password123' で検証失敗</p>
                    <p>→ 上記の「テストユーザーのパスワードハッシュを修正」ボタンで修正してください</p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    <?php
        else:
    ?>
        <div class="warning-box">
            <p>⚠️ テストユーザー 'Smiley0007' が見つかりません</p>
            <p>マイグレーションを実行してテストユーザーを作成してください</p>
        </div>
    <?php
        endif;
    } catch (Exception $e) {
        echo '<div class="error-box">';
        echo '<p>データベース接続エラー: ' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '</div>';
    }
    ?>

    <h2>4. 手動SQL実行（上級者向け）</h2>

    <div class="info-box">
        <p>phpMyAdminまたはSSH経由で以下のSQLを実行することもできます：</p>
        <pre>-- テストユーザーのパスワードハッシュを修正
UPDATE users
SET password_hash = '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TukcvhqdVyO1N8hMp0i5GdBKqRQC',
    role = 'smiley_staff',
    is_registered = 1,
    is_active = 1,
    registered_at = NOW()
WHERE user_code = 'Smiley0007';

-- 検証
SELECT
    user_code,
    user_name,
    role,
    is_registered,
    is_active,
    CASE WHEN password_hash IS NOT NULL THEN 'パスワード設定済み' ELSE 'パスワード未設定' END as password_status
FROM users
WHERE user_code = 'Smiley0007';</pre>
    </div>

    <div class="warning-box">
        <h3>🗑️ 修正完了後の推奨事項</h3>
        <p>このツールを使用してパスワードハッシュの修正が完了したら、セキュリティのため以下のファイルを削除してください：</p>
        <pre>rm fix_password_hash.php</pre>
    </div>

</div>
</body>
</html>
