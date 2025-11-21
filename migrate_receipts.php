<?php
/**
 * Web-based Migration Tool
 * Execute database migrations from browser
 */

session_start();
require_once __DIR__ . '/config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Get migration status
$migrationStatus = null;
$migrationResult = null;
$errorMessage = null;

// Handle migration execution
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['execute_migration'])) {
    try {
        $db = Database::getInstance();
        $conn = $db->getConnection();

        // Read migration file
        $migrationFile = __DIR__ . '/sql/migration_modify_receipts_allow_null.sql';

        if (!file_exists($migrationFile)) {
            throw new Exception("マイグレーションファイルが見つかりません");
        }

        $sql = file_get_contents($migrationFile);

        // Split into statements
        $statements = array_filter(
            array_map('trim', explode(';', $sql)),
            function($stmt) {
                $stmt = trim($stmt);
                return !empty($stmt) && strpos($stmt, '--') !== 0;
            }
        );

        // Execute statements
        $results = [];
        $successCount = 0;
        $skipCount = 0;

        foreach ($statements as $i => $statement) {
            try {
                $conn->exec($statement);
                $results[] = [
                    'statement_num' => $i + 1,
                    'preview' => substr(str_replace(["\n", "\r", "  "], ' ', $statement), 0, 100),
                    'status' => 'success'
                ];
                $successCount++;
            } catch (PDOException $e) {
                $errorMsg = $e->getMessage();

                if ((strpos($errorMsg, "check that column/key exists") !== false ||
                     strpos($errorMsg, "Can't DROP") !== false) &&
                    strpos($statement, "DROP FOREIGN KEY") !== false) {
                    $results[] = [
                        'statement_num' => $i + 1,
                        'preview' => substr(str_replace(["\n", "\r", "  "], ' ', $statement), 0, 100),
                        'status' => 'skipped',
                        'message' => '外部キーが存在しません（スキップ）'
                    ];
                    $skipCount++;
                } else {
                    throw $e;
                }
            }
        }

        // Verify changes
        $stmt = $conn->query("SHOW COLUMNS FROM receipts WHERE Field IN ('payment_id', 'issue_date')");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $migrationStatus = 'success';
        $migrationResult = [
            'statements' => $results,
            'success_count' => $successCount,
            'skip_count' => $skipCount,
            'total_count' => count($statements),
            'columns' => $columns
        ];

    } catch (Exception $e) {
        $migrationStatus = 'error';
        $errorMessage = $e->getMessage();
    }
}

// Check if migration is needed
$needsMigration = false;
$currentSchema = [];

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();

    $stmt = $conn->query("SHOW COLUMNS FROM receipts WHERE Field IN ('payment_id', 'issue_date')");
    $currentSchema = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($currentSchema as $column) {
        if ($column['Null'] === 'NO') {
            $needsMigration = true;
            break;
        }
    }
} catch (Exception $e) {
    $errorMessage = "データベース接続エラー: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>データベースマイグレーション - 入金前領収書機能</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Noto Sans JP', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            padding: 40px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }

        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }

        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }

        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 20px;
            margin-bottom: 30px;
            border-radius: 4px;
        }

        .info-box h2 {
            color: #1976d2;
            font-size: 18px;
            margin-bottom: 15px;
        }

        .info-box ul {
            margin-left: 20px;
            color: #555;
        }

        .info-box li {
            margin-bottom: 8px;
        }

        .status-box {
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }

        .status-box.needs-migration {
            background: #fff3cd;
            border: 2px solid #ffc107;
        }

        .status-box.migration-ok {
            background: #d4edda;
            border: 2px solid #28a745;
        }

        .status-box h3 {
            margin-bottom: 15px;
            font-size: 16px;
        }

        .current-schema {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            margin-top: 15px;
            font-family: monospace;
            font-size: 13px;
        }

        .schema-row {
            padding: 5px 0;
            display: flex;
            justify-content: space-between;
        }

        .schema-status {
            font-weight: bold;
        }

        .schema-status.ok {
            color: #28a745;
        }

        .schema-status.ng {
            color: #dc3545;
        }

        .btn {
            padding: 12px 30px;
            font-size: 16px;
            font-weight: 500;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s;
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

        .btn-primary:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }

        .btn-back {
            background: #6c757d;
            color: white;
            margin-left: 10px;
        }

        .btn-back:hover {
            background: #5a6268;
        }

        .result-box {
            margin-top: 30px;
            padding: 20px;
            border-radius: 8px;
        }

        .result-box.success {
            background: #d4edda;
            border: 2px solid #28a745;
        }

        .result-box.error {
            background: #f8d7da;
            border: 2px solid #dc3545;
        }

        .result-box h3 {
            margin-bottom: 15px;
        }

        .statement-list {
            background: white;
            padding: 15px;
            border-radius: 4px;
            margin: 15px 0;
            max-height: 300px;
            overflow-y: auto;
        }

        .statement-item {
            padding: 8px;
            margin-bottom: 8px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 12px;
        }

        .statement-item.success {
            background: #d4edda;
        }

        .statement-item.skipped {
            background: #fff3cd;
        }

        .verification {
            background: white;
            padding: 15px;
            border-radius: 4px;
            margin-top: 15px;
        }

        .verification h4 {
            margin-bottom: 10px;
            color: #333;
        }

        .error-message {
            color: #721c24;
            font-weight: 500;
        }

        .back-link {
            margin-top: 20px;
            display: inline-block;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📋 データベースマイグレーション</h1>
        <p class="subtitle">入金前領収書機能を有効化</p>

        <div class="info-box">
            <h2>このマイグレーションについて</h2>
            <p style="margin-bottom: 15px;">
                このマイグレーションは、領収書を入金前に発行できるようにするため、<code>receipts</code>テーブルのスキーマを変更します。
            </p>
            <ul>
                <li><strong>payment_id</strong> カラムをNULL許可に変更</li>
                <li><strong>issue_date</strong> カラムをNULL許可に変更</li>
                <li>外部キー制約を更新してNULLを許可</li>
            </ul>
            <p style="margin-top: 15px; color: #666; font-size: 13px;">
                これにより、配達現場で使用するために事前に領収書を印刷できるようになります。
            </p>
        </div>

        <?php if ($errorMessage && !$migrationStatus): ?>
            <div class="result-box error">
                <h3>❌ エラー</h3>
                <p class="error-message"><?php echo htmlspecialchars($errorMessage); ?></p>
            </div>
        <?php endif; ?>

        <?php if (!$migrationStatus): ?>
            <div class="status-box <?php echo $needsMigration ? 'needs-migration' : 'migration-ok'; ?>">
                <h3>
                    <?php if ($needsMigration): ?>
                        ⚠️ マイグレーションが必要です
                    <?php else: ?>
                        ✅ マイグレーションは既に適用されています
                    <?php endif; ?>
                </h3>

                <div class="current-schema">
                    <strong>現在のスキーマ:</strong>
                    <?php foreach ($currentSchema as $column): ?>
                        <div class="schema-row">
                            <span><?php echo htmlspecialchars($column['Field']); ?>: <?php echo htmlspecialchars($column['Type']); ?></span>
                            <span class="schema-status <?php echo $column['Null'] === 'YES' ? 'ok' : 'ng'; ?>">
                                <?php echo $column['Null'] === 'YES' ? '✓ NULL許可' : '✗ NOT NULL'; ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <form method="POST">
                <button type="submit" name="execute_migration" class="btn btn-primary" <?php echo !$needsMigration ? 'disabled' : ''; ?>>
                    🚀 マイグレーションを実行
                </button>
                <button type="button" onclick="location.href='index.php'" class="btn btn-back">
                    ← ダッシュボードに戻る
                </button>
            </form>
        <?php endif; ?>

        <?php if ($migrationStatus === 'success'): ?>
            <div class="result-box success">
                <h3>✅ マイグレーションが正常に完了しました</h3>

                <p style="margin: 15px 0;">
                    <strong>実行結果:</strong><br>
                    成功: <?php echo $migrationResult['success_count']; ?> ステートメント<br>
                    スキップ: <?php echo $migrationResult['skip_count']; ?> ステートメント<br>
                    合計: <?php echo $migrationResult['total_count']; ?> ステートメント
                </p>

                <div class="statement-list">
                    <strong>実行したステートメント:</strong>
                    <?php foreach ($migrationResult['statements'] as $stmt): ?>
                        <div class="statement-item <?php echo $stmt['status']; ?>">
                            [<?php echo $stmt['statement_num']; ?>]
                            <?php echo htmlspecialchars($stmt['preview']); ?>...
                            <?php if (isset($stmt['message'])): ?>
                                <br><small><?php echo htmlspecialchars($stmt['message']); ?></small>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="verification">
                    <h4>📊 スキーマ検証結果:</h4>
                    <?php foreach ($migrationResult['columns'] as $column): ?>
                        <div class="schema-row">
                            <span><?php echo htmlspecialchars($column['Field']); ?>: <?php echo htmlspecialchars($column['Type']); ?></span>
                            <span class="schema-status ok">
                                ✓ <?php echo $column['Null'] === 'YES' ? 'NULL許可' : 'NOT NULL'; ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>

                <p style="margin-top: 20px; padding: 15px; background: #e8f5e9; border-radius: 4px;">
                    <strong>🎉 入金前領収書機能が有効になりました！</strong><br>
                    集金管理画面から領収書を発行できます。
                </p>

                <button type="button" onclick="location.href='pages/payments.php'" class="btn btn-primary" style="margin-top: 15px;">
                    集金管理画面へ
                </button>
                <button type="button" onclick="location.href='index.php'" class="btn btn-back">
                    ダッシュボードに戻る
                </button>
            </div>
        <?php endif; ?>

        <?php if ($migrationStatus === 'error'): ?>
            <div class="result-box error">
                <h3>❌ マイグレーションエラー</h3>
                <p class="error-message"><?php echo htmlspecialchars($errorMessage); ?></p>
                <button type="button" onclick="location.reload()" class="btn btn-primary" style="margin-top: 15px;">
                    再試行
                </button>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
