<?php
/**
 * 部署管理画面
 * Smiley配食事業システム
 *
 * 共通ヘッダー/フッター使用版
 */

require_once '../config/database.php';
require_once '../classes/SecurityHelper.php';

// セキュリティヘッダー設定
SecurityHelper::setSecurityHeaders();

// Database::getInstance() を使用
$db = Database::getInstance();

// フィルター取得
$companyFilter = $_GET['company_id'] ?? '';
$searchTerm = $_GET['search'] ?? '';

// 統計情報取得
$stats = getDepartmentStats($db, $companyFilter);
$departments = getDepartments($db, $companyFilter, $searchTerm);
$companies = getCompanies($db);

function getDepartmentStats($db, $companyFilter = '') {
    try {
        $stats = [
            'total_departments' => 0,
            'active_departments' => 0,
            'companies_with_departments' => 0,
            'users_in_departments' => 0,
            'departments_with_orders' => 0,
            'avg_users_per_department' => 0
        ];

        $whereClause = '';
        $params = [];

        if ($companyFilter) {
            $whereClause = 'WHERE d.company_id = ?';
            $params[] = $companyFilter;
        }

        // 総部署数
        $stmt = $db->query("SELECT COUNT(*) as total FROM departments d $whereClause", $params);
        $result = $stmt->fetch();
        $stats['total_departments'] = $result['total'] ?? 0;

        // アクティブ部署数
        $activeWhere = $whereClause ? $whereClause . ' AND d.is_active = 1' : 'WHERE d.is_active = 1';
        $stmt = $db->query("SELECT COUNT(*) as active FROM departments d $activeWhere", $params);
        $result = $stmt->fetch();
        $stats['active_departments'] = $result['active'] ?? 0;

        // 部署を持つ企業数
        $stmt = $db->query("
            SELECT COUNT(DISTINCT d.company_id) as companies
            FROM departments d
            WHERE d.is_active = 1
        ");
        $result = $stmt->fetch();
        $stats['companies_with_departments'] = $result['companies'] ?? 0;

        // 部署に所属する利用者数
        $stmt = $db->query("
            SELECT COUNT(u.id) as users
            FROM users u
            JOIN departments d ON u.department_id = d.id
            WHERE u.is_active = 1 AND d.is_active = 1
        ");
        $result = $stmt->fetch();
        $stats['users_in_departments'] = $result['users'] ?? 0;

        // 注文のある部署数
        $stmt = $db->query("
            SELECT COUNT(DISTINCT d.id) as departments
            FROM departments d
            JOIN users u ON d.id = u.department_id
            JOIN orders o ON u.user_code = o.user_code
            WHERE d.is_active = 1 AND o.delivery_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ");
        $result = $stmt->fetch();
        $stats['departments_with_orders'] = $result['departments'] ?? 0;

        // 部署あたりの平均利用者数
        if ($stats['active_departments'] > 0) {
            $stats['avg_users_per_department'] = round($stats['users_in_departments'] / $stats['active_departments'], 1);
        }

        return $stats;

    } catch (Exception $e) {
        error_log("Department stats error: " . $e->getMessage());
        return [
            'total_departments' => 'エラー',
            'active_departments' => 'エラー',
            'companies_with_departments' => 'エラー',
            'users_in_departments' => 'エラー',
            'departments_with_orders' => 'エラー',
            'avg_users_per_department' => 'エラー',
            'error' => $e->getMessage()
        ];
    }
}

function getDepartments($db, $companyFilter = '', $searchTerm = '') {
    try {
        $whereConditions = ['d.is_active = 1'];
        $params = [];

        if ($companyFilter) {
            $whereConditions[] = 'd.company_id = ?';
            $params[] = $companyFilter;
        }

        if ($searchTerm) {
            $whereConditions[] = '(d.department_name LIKE ? OR c.company_name LIKE ?)';
            $params[] = "%$searchTerm%";
            $params[] = "%$searchTerm%";
        }

        $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

        $stmt = $db->query("
            SELECT
                d.*,
                c.company_name,
                c.company_code,
                COUNT(DISTINCT u.id) as user_count,
                COUNT(DISTINCT CASE WHEN u.is_active = 1 THEN u.id END) as active_user_count,
                COUNT(DISTINCT o.id) as order_count,
                SUM(o.total_amount) as total_revenue,
                MAX(o.delivery_date) as last_order_date,
                AVG(u.is_active) as activity_rate
            FROM departments d
            LEFT JOIN companies c ON d.company_id = c.id
            LEFT JOIN users u ON d.id = u.department_id
            LEFT JOIN orders o ON u.user_code = o.user_code AND o.delivery_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
            $whereClause
            GROUP BY d.id
            ORDER BY c.company_name ASC, d.department_name ASC
        ", $params);

        return $stmt->fetchAll();

    } catch (Exception $e) {
        error_log("Get departments error: " . $e->getMessage());
        return [];
    }
}

function getCompanies($db) {
    try {
        $stmt = $db->query("
            SELECT id, company_name, company_code
            FROM companies
            WHERE is_active = 1
            ORDER BY company_name ASC
        ");

        return $stmt->fetchAll();

    } catch (Exception $e) {
        error_log("Get companies error: " . $e->getMessage());
        return [];
    }
}

// ページ設定
$pageTitle = '部署管理 - Smiley配食事業システム';
$activePage = 'departments';
$basePath = '..';
$pageSpecificCSS = "
    .main-container {
        background: rgba(255, 255, 255, 0.95);
        border-radius: 20px;
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
        margin: 20px auto;
        padding: 30px;
        max-width: 1400px;
    }
    .smiley-green { color: #2E8B57; }
    .bg-smiley-green { background-color: #2E8B57; }

    .stat-card {
        background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
        border-radius: 15px;
        padding: 25px;
        margin-bottom: 20px;
        border-left: 5px solid #2E8B57;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        transition: transform 0.3s ease;
    }
    .stat-card:hover {
        transform: translateY(-5px);
    }
    .stat-number {
        font-size: 2.2rem;
        font-weight: bold;
        color: #2E8B57;
    }

    .department-card {
        background: white;
        border-radius: 15px;
        padding: 20px;
        margin-bottom: 15px;
        border-left: 4px solid #2E8B57;
        box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
    }
    .department-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
    }

    .company-badge {
        background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
        color: white;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: bold;
    }

    .search-filters {
        background: white;
        border-radius: 15px;
        padding: 20px;
        margin-bottom: 20px;
        box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
    }

    .btn-smiley {
        background-color: #2E8B57;
        border-color: #2E8B57;
        color: white;
    }
    .btn-smiley:hover {
        background-color: #228B22;
        border-color: #228B22;
        color: white;
    }

    .department-stats {
        font-size: 0.9rem;
    }
    .department-stats .stat-item {
        display: inline-block;
        margin-right: 15px;
        color: #6c757d;
    }

    .activity-indicator {
        display: inline-block;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        margin-right: 5px;
    }
    .activity-high { background-color: #28a745; }
    .activity-medium { background-color: #ffc107; }
    .activity-low { background-color: #dc3545; }
    .activity-none { background-color: #6c757d; }

    .no-data {
        text-align: center;
        padding: 40px;
        color: #6c757d;
    }
";

// 共通ヘッダー読み込み
require_once __DIR__ . '/../includes/header.php';
?>

<!-- ページヘッダー -->
<div class="row align-items-center mb-4">
    <div class="col">
        <h1 class="display-5 smiley-green mb-2">部署管理</h1>
        <p class="lead text-muted">Smiley配食システム - 企業別部署の統合管理</p>
    </div>
    <div class="col-auto">
        <button class="btn btn-smiley" onclick="showAddDepartmentModal()">
            新規部署追加
        </button>
    </div>
</div>

<!-- 統計サマリー -->
<div class="row mb-4">
    <div class="col-lg-2 col-md-4 col-sm-6">
        <div class="stat-card text-center">
            <div class="stat-number"><?php echo is_numeric($stats['total_departments']) ? number_format($stats['total_departments']) : $stats['total_departments']; ?></div>
            <div class="text-muted">総部署数</div>
            <small class="text-success">登録済み</small>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6">
        <div class="stat-card text-center">
            <div class="stat-number"><?php echo is_numeric($stats['active_departments']) ? number_format($stats['active_departments']) : $stats['active_departments']; ?></div>
            <div class="text-muted">アクティブ部署</div>
            <small class="text-info">稼働中</small>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6">
        <div class="stat-card text-center">
            <div class="stat-number"><?php echo is_numeric($stats['companies_with_departments']) ? number_format($stats['companies_with_departments']) : $stats['companies_with_departments']; ?></div>
            <div class="text-muted">対象企業数</div>
            <small class="text-primary">部署有り</small>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6">
        <div class="stat-card text-center">
            <div class="stat-number"><?php echo is_numeric($stats['users_in_departments']) ? number_format($stats['users_in_departments']) : $stats['users_in_departments']; ?></div>
            <div class="text-muted">所属利用者数</div>
            <small class="text-success">アクティブ</small>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6">
        <div class="stat-card text-center">
            <div class="stat-number"><?php echo is_numeric($stats['departments_with_orders']) ? number_format($stats['departments_with_orders']) : $stats['departments_with_orders']; ?></div>
            <div class="text-muted">注文実績部署</div>
            <small class="text-warning">過去30日</small>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-sm-6">
        <div class="stat-card text-center">
            <div class="stat-number"><?php echo is_numeric($stats['avg_users_per_department']) ? $stats['avg_users_per_department'] : $stats['avg_users_per_department']; ?></div>
            <div class="text-muted">平均利用者数</div>
            <small class="text-info">部署あたり</small>
        </div>
    </div>
</div>

<!-- 検索・フィルター -->
<div class="search-filters">
    <form method="GET" action="">
        <div class="row">
            <div class="col-md-4">
                <div class="form-group">
                    <label class="form-label">企業フィルター</label>
                    <select class="form-select" name="company_id" onchange="this.form.submit()">
                        <option value="">全ての企業</option>
                        <?php foreach ($companies as $company): ?>
                            <option value="<?php echo $company['id']; ?>"
                                    <?php echo $companyFilter == $company['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($company['company_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label class="form-label">部署名検索</label>
                    <input type="text" class="form-control" name="search"
                           value="<?php echo htmlspecialchars($searchTerm); ?>"
                           placeholder="部署名または企業名を入力...">
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-smiley w-100">
                        検索
                    </button>
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group">
                    <label class="form-label">&nbsp;</label>
                    <a href="departments.php" class="btn btn-outline-secondary w-100">
                        リセット
                    </a>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- 部署一覧 -->
<div id="departmentsContainer">
    <?php if (empty($departments)): ?>
        <div class="no-data">
            <h4 class="text-muted mt-3">
                <?php if ($companyFilter): ?>
                    選択された企業に部署が登録されていません
                <?php else: ?>
                    部署が登録されていません
                <?php endif; ?>
            </h4>
            <p class="text-muted">CSVインポートまたは手動で部署を追加してください</p>
            <a href="csv_import.php" class="btn btn-smiley me-2">
                CSVインポート
            </a>
            <a href="companies.php" class="btn btn-outline-primary">
                企業管理
            </a>
        </div>
    <?php else: ?>
        <?php foreach ($departments as $department): ?>
            <div class="department-card" data-department-id="<?php echo $department['id']; ?>">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <div class="d-flex align-items-center mb-2">
                            <span class="company-badge me-3">
                                <?php echo htmlspecialchars($department['company_name']); ?>
                            </span>
                            <?php
                            $activityClass = 'activity-none';
                            if ($department['order_count'] > 50) $activityClass = 'activity-high';
                            elseif ($department['order_count'] > 10) $activityClass = 'activity-medium';
                            elseif ($department['order_count'] > 0) $activityClass = 'activity-low';
                            ?>
                            <span class="activity-indicator <?php echo $activityClass; ?>"
                                  title="注文活動レベル"></span>
                        </div>
                        <h5 class="mb-2">
                            <?php echo htmlspecialchars($department['department_name']); ?>
                        </h5>
                        <div class="department-stats">
                            <span class="stat-item">
                                <?php echo number_format($department['active_user_count']); ?>/<?php echo number_format($department['user_count']); ?>名
                            </span>
                            <span class="stat-item">
                                <?php echo number_format($department['order_count']); ?>件
                            </span>
                            <?php if ($department['last_order_date']): ?>
                                <span class="stat-item">
                                    最終注文: <?php echo date('Y/m/d', strtotime($department['last_order_date'])); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <?php if (isset($department['floor_building']) && $department['floor_building'] || isset($department['room_number']) && $department['room_number']): ?>
                            <small class="text-muted">
                                <?php echo htmlspecialchars($department['floor_building'] ?? ''); ?>
                                <?php if (isset($department['room_number']) && $department['room_number']): ?>
                                    <?php echo htmlspecialchars($department['room_number']); ?>
                                <?php endif; ?>
                            </small>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-3 text-center">
                        <div class="h5 text-success mb-1"><?php echo number_format($department['total_revenue'] ?: 0); ?>円</div>
                        <small class="text-muted">過去90日売上</small>
                        <?php if ($department['user_count'] > 0): ?>
                            <div class="mt-2">
                                <small class="text-info">
                                    稼働率: <?php echo round(($department['active_user_count'] / $department['user_count']) * 100); ?>%
                                </small>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-3 text-end">
                        <div class="btn-group-vertical btn-group-sm">
                            <a href="users.php?department_id=<?php echo $department['id']; ?>"
                               class="btn btn-outline-success btn-sm">
                                利用者 (<?php echo $department['user_count']; ?>)
                            </a>
                            <a href="company_detail.php?id=<?php echo $department['company_id']; ?>"
                               class="btn btn-outline-primary btn-sm">
                                企業詳細
                            </a>
                            <button class="btn btn-outline-secondary btn-sm"
                                    onclick="editDepartment(<?php echo $department['id']; ?>)">
                                編集
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- クイックアクション -->
<div class="row mt-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-smiley-green text-white">
                <h6 class="mb-0">クイックアクション</h6>
            </div>
            <div class="card-body">
                <a href="companies.php" class="btn btn-outline-primary me-2 mb-2">
                    企業管理
                </a>
                <a href="users.php" class="btn btn-outline-success me-2 mb-2">
                    利用者管理
                </a>
                <a href="csv_import.php" class="btn btn-outline-info me-2 mb-2">
                    CSVインポート
                </a>
                <a href="../pages/system_health.php" class="btn btn-outline-warning mb-2">
                    システム状況
                </a>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-info text-white">
                <h6 class="mb-0">フィルター情報</h6>
            </div>
            <div class="card-body">
                <?php if ($companyFilter): ?>
                    <?php
                    $selectedCompany = array_filter($companies, function($c) use ($companyFilter) {
                        return $c['id'] == $companyFilter;
                    });
                    $selectedCompany = reset($selectedCompany);
                    ?>
                    <p class="mb-2"><strong>選択企業:</strong> <?php echo htmlspecialchars($selectedCompany['company_name']); ?></p>
                <?php endif; ?>
                <?php if ($searchTerm): ?>
                    <p class="mb-2"><strong>検索キーワード:</strong> "<?php echo htmlspecialchars($searchTerm); ?>"</p>
                <?php endif; ?>
                <p class="mb-2"><strong>表示件数:</strong> <?php echo count($departments); ?>件</p>
                <p class="mb-0"><strong>最終更新:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
            </div>
        </div>
    </div>
</div>

<script>
    // 部署追加モーダル（今後実装）
    function showAddDepartmentModal() {
        alert('部署追加機能は今後実装予定です。現在はCSVインポートをご利用ください。');
    }

    // 部署編集（今後実装）
    function editDepartment(departmentId) {
        alert(`部署ID ${departmentId} の編集機能は今後実装予定です。`);
    }

    // 初期化
    document.addEventListener('DOMContentLoaded', function() {
        console.log('部署管理画面が読み込まれました');

        // フィルター状況をコンソールに表示
        const companyFilter = '<?php echo $companyFilter; ?>';
        const searchTerm = '<?php echo $searchTerm; ?>';

        if (companyFilter) {
            console.log('企業フィルター適用:', companyFilter);
        }
        if (searchTerm) {
            console.log('検索フィルター適用:', searchTerm);
        }

        // エラー表示（デバッグモード時）
        <?php if (isset($stats['error']) && defined('DEBUG_MODE') && DEBUG_MODE): ?>
        console.error('Department stats error:', <?php echo json_encode($stats['error']); ?>);
        <?php endif; ?>
    });
</script>

<?php
// 共通フッター読み込み
require_once __DIR__ . '/../includes/footer.php';
?>
