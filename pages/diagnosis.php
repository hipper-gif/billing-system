<?php
/**
 * データ診断ページ
 * ordersテーブルのデータ状況を確認
 */

require_once __DIR__ . '/../config/database.php';

$pageTitle = 'データ診断 - Smiley配食事業システム';
$activePage = '';
$basePath = '..';

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();

    // 1. 総データ数
    $stmt = $conn->query("SELECT COUNT(*) as count FROM orders");
    $totalOrders = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // 2. 最新5件
    $stmt = $conn->query("
        SELECT id, order_date, delivery_date, user_name, company_name, total_amount, created_at
        FROM orders
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $latestOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. 月別データ
    $stmt = $conn->query("
        SELECT
            DATE_FORMAT(order_date, '%Y-%m') as month,
            COUNT(*) as count,
            SUM(total_amount) as total
        FROM orders
        GROUP BY DATE_FORMAT(order_date, '%Y-%m')
        ORDER BY month DESC
        LIMIT 12
    ");
    $monthlyData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. 今月のデータ
    $stmt = $conn->query("
        SELECT
            COUNT(*) as count,
            SUM(total_amount) as total
        FROM orders
        WHERE order_date BETWEEN DATE_FORMAT(NOW(), '%Y-%m-01') AND LAST_DAY(NOW())
    ");
    $thisMonth = $stmt->fetch(PDO::FETCH_ASSOC);

    // 5. インポートバッチ
    $stmt = $conn->query("
        SELECT
            import_batch_id,
            COUNT(*) as count,
            MIN(created_at) as first,
            MAX(created_at) as last
        FROM orders
        WHERE import_batch_id IS NOT NULL
        GROUP BY import_batch_id
        ORDER BY MAX(created_at) DESC
        LIMIT 5
    ");
    $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 6. テーブル構造
    $stmt = $conn->query("SHOW COLUMNS FROM orders");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $error = $e->getMessage();
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h2 class="h4 mb-3">
            <span class="material-icons" style="vertical-align: middle; font-size: 2rem;">analytics</span>
            データ診断
        </h2>
        <p class="text-white-50">ordersテーブルのデータ状況を確認します</p>
    </div>
</div>

<?php if (isset($error)): ?>
<div class="alert alert-danger">
    <strong>エラー:</strong> <?php echo htmlspecialchars($error); ?>
</div>
<?php else: ?>

<!-- サマリー -->
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="stat-card <?php echo $totalOrders > 0 ? 'success' : 'warning'; ?>">
            <div class="stat-value"><?php echo number_format($totalOrders); ?></div>
            <div class="stat-label">総注文数</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card <?php echo $thisMonth['count'] > 0 ? 'success' : 'warning'; ?>">
            <div class="stat-value"><?php echo number_format($thisMonth['count']); ?></div>
            <div class="stat-label">今月の注文数</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card info">
            <div class="stat-value"><?php echo number_format($thisMonth['total'] ?? 0); ?></div>
            <div class="stat-label">今月の合計金額 (円)</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-value"><?php echo count($columns); ?></div>
            <div class="stat-label">テーブルカラム数</div>
        </div>
    </div>
</div>

<!-- 診断結果 -->
<?php if ($totalOrders === 0): ?>
<div class="alert alert-warning">
    <h5><span class="material-icons" style="vertical-align: middle;">warning</span> データが登録されていません</h5>
    <p>ordersテーブルにデータが1件も登録されていません。</p>
    <p><strong>考えられる原因：</strong></p>
    <ul>
        <li>CSVインポートがエラーで失敗している</li>
        <li>CSVファイルのフォーマットが正しくない</li>
        <li>データベース接続エラーが発生している</li>
    </ul>
    <a href="csv_import.php" class="btn btn-warning">CSVインポートページへ</a>
</div>
<?php elseif ($thisMonth['count'] === 0): ?>
<div class="alert alert-info">
    <h5><span class="material-icons" style="vertical-align: middle;">info</span> 今月のデータがありません</h5>
    <p>ordersテーブルにはデータがありますが、<strong>今月（<?php echo date('Y年m月'); ?>）のデータがありません。</strong></p>
    <p>集金管理画面は<strong>今月のデータのみ</strong>を表示するため、金額が0円になっています。</p>
    <p><strong>対処方法：</strong></p>
    <ul>
        <li>今月の注文データを含むCSVファイルをインポートしてください</li>
        <li>または、過去のデータも表示するようにシステムを変更します（開発者に依頼）</li>
    </ul>
</div>
<?php else: ?>
<div class="alert alert-success">
    <h5><span class="material-icons" style="vertical-align: middle;">check_circle</span> データは正常に登録されています</h5>
    <p>今月のデータが <?php echo number_format($thisMonth['count']); ?> 件登録されています。</p>
    <p>集金管理画面で金額が表示されるはずです。表示されない場合は、ページをリロードしてください。</p>
</div>
<?php endif; ?>

<!-- 月別データ -->
<div class="payment-summary mb-4">
    <h4 class="mb-3">月別データ（直近12ヶ月）</h4>
    <?php if (!empty($monthlyData)): ?>
    <div class="table-responsive">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>年月</th>
                    <th class="text-end">件数</th>
                    <th class="text-end">合計金額</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($monthlyData as $row): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($row['month']); ?></strong></td>
                    <td class="text-end"><?php echo number_format($row['count']); ?>件</td>
                    <td class="text-end"><?php echo number_format($row['total']); ?>円</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <p class="text-muted">データがありません</p>
    <?php endif; ?>
</div>

<!-- 最新データ -->
<div class="payment-summary mb-4">
    <h4 class="mb-3">最新の注文データ（5件）</h4>
    <?php if (!empty($latestOrders)): ?>
    <div class="table-responsive">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>注文日</th>
                    <th>配達日</th>
                    <th>利用者</th>
                    <th>企業</th>
                    <th class="text-end">金額</th>
                    <th>登録日時</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($latestOrders as $row): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['id']); ?></td>
                    <td><?php echo htmlspecialchars($row['order_date']); ?></td>
                    <td><?php echo htmlspecialchars($row['delivery_date']); ?></td>
                    <td><?php echo htmlspecialchars($row['user_name']); ?></td>
                    <td><?php echo htmlspecialchars($row['company_name']); ?></td>
                    <td class="text-end"><?php echo number_format($row['total_amount']); ?>円</td>
                    <td><?php echo htmlspecialchars($row['created_at']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <p class="text-muted">データがありません</p>
    <?php endif; ?>
</div>

<!-- インポートバッチ -->
<div class="payment-summary mb-4">
    <h4 class="mb-3">最近のCSVインポート（5件）</h4>
    <?php if (!empty($batches)): ?>
    <div class="table-responsive">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>バッチID</th>
                    <th class="text-end">件数</th>
                    <th>開始時刻</th>
                    <th>終了時刻</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($batches as $row): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['import_batch_id']); ?></td>
                    <td class="text-end"><?php echo number_format($row['count']); ?>件</td>
                    <td><?php echo htmlspecialchars($row['first']); ?></td>
                    <td><?php echo htmlspecialchars($row['last']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <p class="text-muted">インポート履歴がありません</p>
    <?php endif; ?>
</div>

<!-- テーブル構造 -->
<div class="payment-summary">
    <h4 class="mb-3">ordersテーブル構造（全 <?php echo count($columns); ?> カラム）</h4>
    <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
        <table class="table table-sm table-striped">
            <thead>
                <tr>
                    <th>カラム名</th>
                    <th>データ型</th>
                    <th>NULL</th>
                    <th>デフォルト</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($columns as $col): ?>
                <tr>
                    <td><code><?php echo htmlspecialchars($col['Field']); ?></code></td>
                    <td><?php echo htmlspecialchars($col['Type']); ?></td>
                    <td><?php echo htmlspecialchars($col['Null']); ?></td>
                    <td><?php echo htmlspecialchars($col['Default'] ?? 'NULL'); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php endif; ?>

<div class="mt-4">
    <a href="<?php echo $basePath; ?>/index.php" class="btn btn-material btn-primary">
        <span class="material-icons" style="vertical-align: middle;">arrow_back</span>
        ダッシュボードに戻る
    </a>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
