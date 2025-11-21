<?php
/**
 * Receipts Table Schema Checker
 * Check if migration was executed correctly
 */

session_start();
require_once __DIR__ . '/config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$schemaInfo = [];
$errorMessage = null;

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();

    // Get column information
    $stmt = $conn->query("SHOW COLUMNS FROM receipts");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get foreign key information (with error handling)
    $foreignKeys = [];
    try {
        $stmt = $conn->query("
            SELECT
                kcu.CONSTRAINT_NAME,
                kcu.COLUMN_NAME,
                kcu.REFERENCED_TABLE_NAME,
                kcu.REFERENCED_COLUMN_NAME,
                rc.DELETE_RULE,
                rc.UPDATE_RULE
            FROM information_schema.KEY_COLUMN_USAGE kcu
            JOIN information_schema.REFERENTIAL_CONSTRAINTS rc
                ON kcu.CONSTRAINT_NAME = rc.CONSTRAINT_NAME
                AND kcu.CONSTRAINT_SCHEMA = rc.CONSTRAINT_SCHEMA
            WHERE kcu.TABLE_SCHEMA = '" . DB_NAME . "'
            AND kcu.TABLE_NAME = 'receipts'
            AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
        ");
        $foreignKeys = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Foreign key query error: " . $e->getMessage());
        // Continue without foreign key information
    }

    // Get sample data (with error handling)
    $preReceipts = [];
    try {
        $stmt = $conn->query("
            SELECT *
            FROM receipts
            WHERE payment_id IS NULL OR issue_date IS NULL
            ORDER BY id DESC
            LIMIT 5
        ");
        $preReceipts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Sample data query error: " . $e->getMessage());
        // Continue without sample data
    }

    // Check migration status
    $paymentIdNullable = false;
    $issueDateNullable = false;

    foreach ($columns as $column) {
        if ($column['Field'] === 'payment_id' && $column['Null'] === 'YES') {
            $paymentIdNullable = true;
        }
        if ($column['Field'] === 'issue_date' && $column['Null'] === 'YES') {
            $issueDateNullable = true;
        }
    }

    $migrationComplete = $paymentIdNullable && $issueDateNullable;

    $schemaInfo = [
        'columns' => $columns,
        'foreign_keys' => $foreignKeys,
        'pre_receipts' => $preReceipts,
        'migration_complete' => $migrationComplete,
        'payment_id_nullable' => $paymentIdNullable,
        'issue_date_nullable' => $issueDateNullable
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
    <title>Receipts テーブル スキーマ確認</title>
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
            max-width: 1200px;
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

        .status-banner {
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            font-size: 18px;
            font-weight: 500;
        }

        .status-banner.ok {
            background: #d4edda;
            border: 2px solid #28a745;
            color: #155724;
        }

        .status-banner.ng {
            background: #f8d7da;
            border: 2px solid #dc3545;
            color: #721c24;
        }

        .section {
            margin-bottom: 40px;
        }

        .section h2 {
            color: #333;
            font-size: 20px;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            background: white;
        }

        th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            border: 1px solid #dee2e6;
            font-size: 14px;
        }

        td {
            padding: 10px 12px;
            border: 1px solid #dee2e6;
            font-size: 13px;
        }

        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }

        .badge.ok {
            background: #d4edda;
            color: #155724;
        }

        .badge.ng {
            background: #f8d7da;
            color: #721c24;
        }

        .badge.info {
            background: #d1ecf1;
            color: #0c5460;
        }

        .code {
            font-family: 'Courier New', monospace;
            background: #f8f9fa;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 12px;
        }

        .btn {
            display: inline-block;
            padding: 12px 30px;
            font-size: 16px;
            font-weight: 500;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
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

        .error-box {
            background: #f8d7da;
            border: 2px solid #dc3545;
            padding: 20px;
            border-radius: 8px;
            color: #721c24;
        }

        .empty-state {
            padding: 40px;
            text-align: center;
            color: #999;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .key-value {
            display: flex;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }

        .key-value .key {
            font-weight: 600;
            width: 200px;
            color: #555;
        }

        .key-value .value {
            flex: 1;
            color: #333;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Receipts テーブル スキーマ確認</h1>
        <p class="subtitle">マイグレーション実行状態を確認</p>

        <?php if ($errorMessage): ?>
            <div class="error-box">
                <strong>エラー:</strong> <?php echo htmlspecialchars($errorMessage); ?>
            </div>
        <?php else: ?>

            <div class="status-banner <?php echo $schemaInfo['migration_complete'] ? 'ok' : 'ng'; ?>">
                <?php if ($schemaInfo['migration_complete']): ?>
                    ✅ マイグレーション完了 - 入金前領収書機能が有効です
                <?php else: ?>
                    ❌ マイグレーション未完了 - 入金前領収書機能を使用するにはマイグレーションを実行してください
                <?php endif; ?>
            </div>

            <!-- Migration Status -->
            <div class="section">
                <h2>📊 マイグレーション状態</h2>
                <div class="key-value">
                    <div class="key">payment_id カラム:</div>
                    <div class="value">
                        <?php if ($schemaInfo['payment_id_nullable']): ?>
                            <span class="badge ok">✓ NULL許可</span>
                        <?php else: ?>
                            <span class="badge ng">✗ NOT NULL (マイグレーション必要)</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="key-value">
                    <div class="key">issue_date カラム:</div>
                    <div class="value">
                        <?php if ($schemaInfo['issue_date_nullable']): ?>
                            <span class="badge ok">✓ NULL許可</span>
                        <?php else: ?>
                            <span class="badge ng">✗ NOT NULL (マイグレーション必要)</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Column Information -->
            <div class="section">
                <h2>📋 カラム情報</h2>
                <table>
                    <thead>
                        <tr>
                            <th>カラム名</th>
                            <th>データ型</th>
                            <th>NULL許可</th>
                            <th>キー</th>
                            <th>デフォルト値</th>
                            <th>備考</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($schemaInfo['columns'] as $col): ?>
                            <tr>
                                <td><span class="code"><?php echo htmlspecialchars($col['Field']); ?></span></td>
                                <td><?php echo htmlspecialchars($col['Type']); ?></td>
                                <td>
                                    <?php if ($col['Null'] === 'YES'): ?>
                                        <span class="badge ok">YES</span>
                                    <?php else: ?>
                                        <span class="badge ng">NO</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($col['Key']); ?></td>
                                <td><?php echo htmlspecialchars($col['Default'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($col['Extra']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Foreign Keys -->
            <div class="section">
                <h2>🔗 外部キー制約</h2>
                <?php if (count($schemaInfo['foreign_keys']) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>制約名</th>
                                <th>カラム</th>
                                <th>参照テーブル</th>
                                <th>参照カラム</th>
                                <th>削除時</th>
                                <th>更新時</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($schemaInfo['foreign_keys'] as $fk): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($fk['CONSTRAINT_NAME']); ?></td>
                                    <td><span class="code"><?php echo htmlspecialchars($fk['COLUMN_NAME']); ?></span></td>
                                    <td><?php echo htmlspecialchars($fk['REFERENCED_TABLE_NAME']); ?></td>
                                    <td><span class="code"><?php echo htmlspecialchars($fk['REFERENCED_COLUMN_NAME']); ?></span></td>
                                    <td><span class="badge info"><?php echo htmlspecialchars($fk['DELETE_RULE']); ?></span></td>
                                    <td><span class="badge info"><?php echo htmlspecialchars($fk['UPDATE_RULE']); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">外部キー制約が設定されていません</div>
                <?php endif; ?>
            </div>

            <!-- Pre-issued Receipts -->
            <div class="section">
                <h2>🧾 入金前領収書（サンプル）</h2>
                <?php if (count($schemaInfo['pre_receipts']) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>領収書番号</th>
                                <th>宛名</th>
                                <th>金額</th>
                                <th>入金ID</th>
                                <th>発行日</th>
                                <th>ステータス</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($schemaInfo['pre_receipts'] as $receipt): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($receipt['id']); ?></td>
                                    <td><span class="code"><?php echo htmlspecialchars($receipt['receipt_number']); ?></span></td>
                                    <td><?php echo htmlspecialchars($receipt['recipient_name']); ?></td>
                                    <td>¥<?php echo number_format($receipt['amount']); ?></td>
                                    <td>
                                        <?php if ($receipt['payment_id']): ?>
                                            <?php echo htmlspecialchars($receipt['payment_id']); ?>
                                        <?php else: ?>
                                            <span class="badge info">NULL (入金前)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($receipt['issue_date']): ?>
                                            <?php echo htmlspecialchars($receipt['issue_date']); ?>
                                        <?php else: ?>
                                            <span class="badge info">NULL (未記入)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($receipt['status']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        入金前領収書のデータがまだありません
                        <?php if (!$schemaInfo['migration_complete']): ?>
                            <br><br><small>マイグレーション完了後、集金管理画面から発行できます</small>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Actions -->
            <div style="margin-top: 40px;">
                <?php if (!$schemaInfo['migration_complete']): ?>
                    <a href="migrate_receipts.php" class="btn btn-primary">
                        🚀 マイグレーションを実行
                    </a>
                <?php else: ?>
                    <a href="pages/payments.php" class="btn btn-primary">
                        集金管理画面へ
                    </a>
                <?php endif; ?>
                <a href="index.php" class="btn btn-secondary">
                    ダッシュボードに戻る
                </a>
            </div>

        <?php endif; ?>
    </div>
</body>
</html>
