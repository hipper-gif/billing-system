<?php
/**
 * Quick Fix: issue_date カラムをNULL許可に変更
 */

session_start();
require_once __DIR__ . '/config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$executed = false;
$success = false;
$errorMessage = null;

// Handle migration execution
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['execute'])) {
    try {
        $db = Database::getInstance();
        $conn = $db->getConnection();

        $sql = "ALTER TABLE receipts MODIFY COLUMN issue_date DATE NULL COMMENT '発行日（入金前領収書の場合はNULL）'";

        $conn->exec($sql);

        $executed = true;
        $success = true;

    } catch (Exception $e) {
        $executed = true;
        $success = false;
        $errorMessage = $e->getMessage();
    }
}

// Check current status
$currentStatus = null;
try {
    $db = Database::getInstance();
    $conn = $db->getConnection();

    $stmt = $conn->query("SHOW COLUMNS FROM receipts WHERE Field = 'issue_date'");
    $column = $stmt->fetch(PDO::FETCH_ASSOC);

    $currentStatus = [
        'field' => $column['Field'],
        'type' => $column['Type'],
        'null' => $column['Null'],
        'is_nullable' => $column['Null'] === 'YES'
    ];
} catch (Exception $e) {
    $errorMessage = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>issue_date 修正 - Smiley配食</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Noto Sans JP', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 700px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            padding: 40px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 24px;
        }
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        .status-box {
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        .status-box.ok {
            background: #d4edda;
            border: 2px solid #28a745;
            color: #155724;
        }
        .status-box.ng {
            background: #fff3cd;
            border: 2px solid #ffc107;
            color: #856404;
        }
        .status-box.error {
            background: #f8d7da;
            border: 2px solid #dc3545;
            color: #721c24;
        }
        .status-box h3 {
            margin-bottom: 10px;
            font-size: 18px;
        }
        .code {
            font-family: monospace;
            background: #f8f9fa;
            padding: 3px 6px;
            border-radius: 3px;
            font-size: 13px;
        }
        .btn {
            padding: 12px 30px;
            font-size: 16px;
            font-weight: 500;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s;
            margin-right: 10px;
        }
        .btn-primary {
            background: #667eea;
            color: white;
        }
        .btn-primary:hover {
            background: #5568d3;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .btn-secondary:hover {
            background: #5a6268;
        }
        .btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }
        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 issue_date カラム修正</h1>
        <p class="subtitle">入金前領収書機能を有効化</p>

        <?php if ($errorMessage && !$executed): ?>
            <div class="status-box error">
                <h3>❌ エラー</h3>
                <p><?php echo htmlspecialchars($errorMessage); ?></p>
            </div>
        <?php endif; ?>

        <?php if (!$executed): ?>
            <div class="info-box">
                <strong>この修正について：</strong><br>
                <code>receipts</code>テーブルの<code>issue_date</code>カラムをNULL許可に変更し、
                入金前に領収書を発行できるようにします。
            </div>

            <div class="status-box <?php echo $currentStatus && $currentStatus['is_nullable'] ? 'ok' : 'ng'; ?>">
                <h3>現在の状態</h3>
                <p>
                    <span class="code">issue_date</span>:
                    <?php if ($currentStatus): ?>
                        <?php if ($currentStatus['is_nullable']): ?>
                            <strong style="color: #28a745;">✓ NULL許可</strong>（修正不要）
                        <?php else: ?>
                            <strong style="color: #dc3545;">✗ NOT NULL</strong>（修正が必要）
                        <?php endif; ?>
                    <?php endif; ?>
                </p>
            </div>

            <form method="POST">
                <button type="submit" name="execute" class="btn btn-primary"
                        <?php echo $currentStatus && $currentStatus['is_nullable'] ? 'disabled' : ''; ?>>
                    🔧 修正を実行
                </button>
                <button type="button" onclick="location.href='pages/payments.php'" class="btn btn-secondary">
                    集金管理画面へ
                </button>
            </form>

        <?php else: ?>
            <?php if ($success): ?>
                <div class="status-box ok">
                    <h3>✅ 修正が完了しました</h3>
                    <p>
                        <code>issue_date</code>カラムがNULL許可に変更されました。<br>
                        入金前領収書の発行が可能になりました！
                    </p>
                </div>
                <button type="button" onclick="location.href='pages/payments.php'" class="btn btn-primary">
                    集金管理画面へ移動
                </button>
            <?php else: ?>
                <div class="status-box error">
                    <h3>❌ エラーが発生しました</h3>
                    <p><?php echo htmlspecialchars($errorMessage); ?></p>
                </div>
                <button type="button" onclick="location.reload()" class="btn btn-primary">
                    再試行
                </button>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>
