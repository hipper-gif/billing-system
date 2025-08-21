<?php
/**
 * 部署管理画面
 * Smiley配食事業専用 - 部署一覧・管理
 */

require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/SecurityHelper.php';

// セキュリティヘッダー設定
SecurityHelper::setSecurityHeaders();

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    
    // 検索条件の処理
    $search_department = $_GET['search_department'] ?? '';
    $company_id = intval($_GET['company_id'] ?? 0);
    $page = max(1, intval($_GET['page'] ?? 1));
    $per_page = 20;
    $offset = ($page - 1) * $per_page;
    
    // 企業一覧取得（フィルター用）
    $companies_sql = "SELECT id, company_name FROM companies WHERE is_active = 1 ORDER BY company_name";
    $companies_stmt = $pdo->prepare($companies_sql);
    $companies_stmt->execute();
    $companies = $companies_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 部署一覧を取得（統計情報付き）
    $where_conditions = [];
    $params = [];
    
    if ($company_id) {
        $where_conditions[] = "d.company_id = :company_id";
        $params['company_id'] = $company_id;
    }
    
    if ($search_department) {
        $where_conditions[] = "(d.department_name LIKE :search_department OR d.department_code LIKE :search_department)";
        $params['search_department'] = '%' . $search_department . '%';
    }
    
    $where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // 部署一覧と統計情報を取得
    $departments_sql = "
        SELECT 
            d.id,
            d.department_code,
            d.department_name,
            d.manager_name,
            d.is_active,
            d.created_at,
            c.company_name,
            c.company_code,
            -- 統計情報
            COUNT(DISTINCT u.id) as user_count,
            COUNT(DISTINCT o.id) as order_count,
            COALESCE(SUM(o.total_amount), 0) as total_amount,
            MAX(o.delivery_date) as last_order_date
        FROM departments d
        LEFT JOIN companies c ON d.company_id = c.id
        LEFT JOIN users u ON d.id = u.department_id
        LEFT JOIN orders o ON d.id = o.department_id
        $where_clause
        GROUP BY d.id
        ORDER BY c.company_name, d.department_name
        LIMIT :limit OFFSET :offset
    ";
    
    $params['limit'] = $per_page;
    $params['offset'] = $offset;
    
    $stmt = $pdo->prepare($departments_sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 総件数を取得
    $count_sql = "
        SELECT COUNT(DISTINCT d.id) as total
        FROM departments d
        LEFT JOIN companies c ON d.company_id = c.id
        $where_clause
    ";
    $count_stmt = $pdo->prepare($count_sql);
    foreach ($params as $key => $value) {
        if ($key !== 'limit' && $key !== 'offset') {
            $count_stmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
    }
    $count_stmt->execute();
    $total_departments = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_departments / $per_page);
    
    // サマリー統計を取得
    $summary_sql = "
        SELECT 
            COUNT(DISTINCT d.id) as active_departments,
            COUNT(DISTINCT u.id) as total_users,
            COALESCE(SUM(order_stats.order_count), 0) as total_orders,
            COALESCE(SUM(order_stats.total_amount), 0) as total_revenue
        FROM departments d
        LEFT JOIN users u ON d.id = u.department_id AND d.is_active = 1
        LEFT JOIN (
            SELECT 
                department_id,
                COUNT(*) as order_count,
                SUM(total_amount) as total_amount
            FROM orders 
            WHERE department_id IS NOT NULL
            GROUP BY department_id
        ) order_stats ON d.id = order_stats.department_id
        WHERE d.is_active = 1
    ";
    
    $summary_stmt = $pdo->prepare($summary_sql);
    $summary_stmt->execute();
    $summary = $summary_stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("部署管理画面エラー: " . $e->getMessage());
    $error_message = "データの取得中にエラーが発生しました。";
    $departments = [];
    $companies = [];
    $summary = [
        'active_departments' => 0,
        'total_users' => 0,
        'total_orders' => 0,
        'total_revenue' => 0
    ];
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>部署管理 - Smiley配食事業</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --smiley-green: #2E8B57;
            --smiley-light-green: #90EE90;
            --smiley-dark-green: #006400;
        }
        
        .navbar-brand {
            color: var(--smiley-green) !important;
            font-weight: bold;
        }
        
        .btn-smiley {
            background-color: var(--smiley-green);
            border-color: var(--smiley-green);
            color: white;
        }
        
        .btn-smiley:hover {
            background-color: var(--smiley-dark-green);
            border-color: var(--smiley-dark-green);
            color: white;
        }
        
        .card-header {
            background-color: var(--smiley-green);
            color: white;
        }
        
        .stats-card {
            border-left: 4px solid var(--smiley-green);
        }
        
        .department-row:hover {
            background-color: rgba(46, 139, 87, 0.1);
        }
        
        .status-active {
            color: var(--smiley-green);
        }
        
        .status-inactive {
            color: #dc3545;
        }
        
        .amount-highlight {
            font-weight: bold;
            color: var(--smiley-dark-green);
        }
        
        .company-badge {
            background-color: rgba(46, 139, 87, 0.1);
            color: var(--smiley-dark-green);
            border: 1px solid var(--smiley-green);
        }
    </style>
</head>
<body>
    <!-- ナビゲーション -->
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <div class="container">
            <a class="navbar-brand" href="../index.php">
                <i class="bi bi-house-heart"></i> Smiley配食事業
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="../pages/csv_import.php">CSVインポート</a>
                <a class="nav-link" href="../pages/companies.php">配達先企業</a>
                <a class="nav-link active" href="../pages/departments.php">部署管理</a>
                <a class="nav-link" href="../pages/users.php">利用者管理</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- ページヘッダー -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-diagram-3"></i> 部署管理</h2>
            <div>
                <button class="btn btn-smiley me-2" data-bs-toggle="modal" data-bs-target="#addDepartmentModal">
                    <i class="bi bi-plus"></i> 新規部署追加
                </button>
                <a href="../pages/companies.php" class="btn btn-outline-secondary">
                    <i class="bi bi-building"></i> 企業管理
                </a>
            </div>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger" role="alert">
                <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <!-- サマリー統計 -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stats-card">
                    <div class="card-body">
                        <h6 class="card-title text-muted">アクティブ部署</h6>
                        <h3 class="text-primary"><?= number_format($summary['active_departments']) ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card">
                    <div class="card-body">
                        <h6 class="card-title text-muted">総利用者数</h6>
                        <h3 class="text-success"><?= number_format($summary['total_users']) ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card">
                    <div class="card-body">
                        <h6 class="card-title text-muted">総注文数</h6>
                        <h3 class="text-warning"><?= number_format($summary['total_orders']) ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card">
                    <div class="card-body">
                        <h6 class="card-title text-muted">総売上</h6>
                        <h3 class="amount-highlight">¥<?= number_format($summary['total_revenue']) ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- 検索・フィルター -->
        <div class="card mb-4">
            <div class="card-header">
                <i class="bi bi-search"></i> 検索・フィルター
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">企業</label>
                        <select class="form-select" name="company_id">
                            <option value="">全ての企業</option>
                            <?php foreach ($companies as $company): ?>
                                <option value="<?= $company['id'] ?>" <?= $company_id === $company['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($company['company_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">部署名・部署コード</label>
                        <input type="text" class="form-control" name="search_department" 
                               value="<?= htmlspecialchars($search_department) ?>" 
                               placeholder="部署名または部署コードで検索">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <div>
                            <button type="submit" class="btn btn-smiley">
                                <i class="bi bi-search"></i> 検索
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- 部署一覧テーブル -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-list"></i> 部署一覧</span>
                <span class="badge bg-light text-dark"><?= number_format($total_departments) ?>件中 <?= number_format(($page-1)*$per_page + 1) ?>-<?= number_format(min($page*$per_page, $total_departments)) ?>件表示</span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($departments)): ?>
                    <div class="p-4 text-center text-muted">
                        <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                        <p class="mt-2">該当する部署が見つかりません。</p>
                        <button class="btn btn-smiley" data-bs-toggle="modal" data-bs-target="#addDepartmentModal">
                            新規部署を追加
                        </button>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>企業名</th>
                                    <th>部署コード</th>
                                    <th>部署名</th>
                                    <th>責任者</th>
                                    <th>利用者数</th>
                                    <th>注文数</th>
                                    <th>売上</th>
                                    <th>最終注文日</th>
                                    <th>状態</th>
                                    <th>操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($departments as $dept): ?>
                                    <tr class="department-row">
                                        <td>
                                            <span class="badge company-badge"><?= htmlspecialchars($dept['company_name']) ?></span>
                                            <br><small class="text-muted"><?= htmlspecialchars($dept['company_code']) ?></small>
                                        </td>
                                        <td>
                                            <code><?= htmlspecialchars($dept['department_code']) ?></code>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($dept['department_name']) ?></strong>
                                        </td>
                                        <td>
                                            <?php if ($dept['manager_name']): ?>
                                                <small><?= htmlspecialchars($dept['manager_name']) ?></small>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-success"><?= number_format($dept['user_count']) ?></span>
                                        </td>
                                        <td>
                                            <?php if ($dept['order_count'] > 0): ?>
                                                <span class="badge bg-warning text-dark"><?= number_format($dept['order_count']) ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($dept['total_amount'] > 0): ?>
                                                <span class="amount-highlight">¥<?= number_format($dept['total_amount']) ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($dept['last_order_date']): ?>
                                                <?= date('m/d', strtotime($dept['last_order_date'])) ?>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($dept['is_active']): ?>
                                                <i class="bi bi-check-circle-fill status-active" title="アクティブ"></i>
                                            <?php else: ?>
                                                <i class="bi bi-x-circle-fill status-inactive" title="非アクティブ"></i>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-primary" title="詳細"
                                                        onclick="viewDepartmentDetail(<?= $dept['id'] ?>)">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                                <button class="btn btn-outline-success" title="利用者管理"
                                                        onclick="manageUsers(<?= $dept['id'] ?>)">
                                                    <i class="bi bi-people"></i>
                                                </button>
                                                <button class="btn btn-outline-warning" title="編集"
                                                        onclick="editDepartment(<?= $dept['id'] ?>)">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ページネーション -->
        <?php if ($total_pages > 1): ?>
            <nav class="mt-4">
                <ul class="pagination justify-content-center">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>&search_department=<?= urlencode($search_department) ?>&company_id=<?= $company_id ?>">
                                <?= $i ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>

    <!-- 新規部署追加モーダル -->
    <div class="modal fade" id="addDepartmentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">新規部署追加</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addDepartmentForm">
                        <div class="mb-3">
                            <label class="form-label">企業 *</label>
                            <select class="form-select" name="company_id" required>
                                <option value="">企業を選択してください</option>
                                <?php foreach ($companies as $company): ?>
                                    <option value="<?= $company['id'] ?>" <?= $company_id === $company['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($company['company_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">部署コード *</label>
                            <input type="text" class="form-control" name="department_code" required>
                            <div class="form-text">同一企業内でユニークなコードを入力してください</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">部署名 *</label>
                            <input type="text" class="form-control" name="department_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">部署責任者</label>
                            <input type="text" class="form-control" name="manager_name">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
                    <button type="button" class="btn btn-smiley" onclick="saveDepartment()">保存</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // 部署詳細表示
        function viewDepartmentDetail(departmentId) {
            window.location.href = `department_detail.php?id=${departmentId}`;
        }

        // 利用者管理
        function manageUsers(departmentId) {
            window.location.href = `users.php?department_id=${departmentId}`;
        }

        // 部署編集
        function editDepartment(departmentId) {
            window.location.href = `department_detail.php?id=${departmentId}&edit=1`;
        }

        // 新規部署保存
        function saveDepartment() {
            const form = document.getElementById('addDepartmentForm');
            const formData = new FormData(form);
            
            fetch('../api/departments.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('エラー: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('保存中にエラーが発生しました。');
            });
        }
    </script>
</body>
</html>
