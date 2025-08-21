<?php
/**
 * 配達先企業管理画面
 * Smiley配食事業専用 - 実際のインポート済みデータを活用
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
    $search_company = $_GET['search_company'] ?? '';
    $search_period_start = $_GET['period_start'] ?? date('Y-m-01'); // 今月の1日
    $search_period_end = $_GET['period_end'] ?? date('Y-m-t'); // 今月の末日
    $page = max(1, intval($_GET['page'] ?? 1));
    $per_page = 20;
    $offset = ($page - 1) * $per_page;
    
    // 配達先企業一覧を取得（統計情報付き）
    $where_conditions = [];
    $params = [];
    
    if ($search_company) {
        $where_conditions[] = "(c.company_name LIKE :search_company OR c.company_code LIKE :search_company)";
        $params['search_company'] = '%' . $search_company . '%';
    }
    
    $where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // 企業一覧と統計情報を取得
    $companies_sql = "
        SELECT 
            c.id,
            c.company_code,
            c.company_name,
            c.company_address,
            c.contact_person,
            c.contact_phone,
            c.contact_email,
            c.billing_method,
            c.is_active,
            c.created_at,
            -- 部署数
            COUNT(DISTINCT d.id) as department_count,
            -- 利用者数
            COUNT(DISTINCT u.id) as user_count,
            -- 期間内注文統計
            COALESCE(stats.order_count, 0) as period_order_count,
            COALESCE(stats.total_amount, 0) as period_total_amount,
            stats.last_order_date
        FROM companies c
        LEFT JOIN departments d ON c.id = d.company_id
        LEFT JOIN users u ON c.id = u.company_id
        LEFT JOIN (
            SELECT 
                company_id,
                COUNT(*) as order_count,
                SUM(total_amount) as total_amount,
                MAX(delivery_date) as last_order_date
            FROM orders 
            WHERE delivery_date BETWEEN :period_start AND :period_end
            GROUP BY company_id
        ) stats ON c.id = stats.company_id
        $where_clause
        GROUP BY c.id
        ORDER BY c.company_name
        LIMIT :limit OFFSET :offset
    ";
    
    $params['period_start'] = $search_period_start;
    $params['period_end'] = $search_period_end;
    $params['limit'] = $per_page;
    $params['offset'] = $offset;
    
    $stmt = $pdo->prepare($companies_sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
    $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 総件数を取得
    $count_sql = "
        SELECT COUNT(DISTINCT c.id) as total
        FROM companies c
        $where_clause
    ";
    $count_stmt = $pdo->prepare($count_sql);
    foreach ($params as $key => $value) {
        if ($key !== 'limit' && $key !== 'offset' && $key !== 'period_start' && $key !== 'period_end') {
            $count_stmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
    }
    $count_stmt->execute();
    $total_companies = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_companies / $per_page);
    
    // サマリー統計を取得
    $summary_sql = "
        SELECT 
            COUNT(DISTINCT c.id) as active_companies,
            COUNT(DISTINCT d.id) as total_departments,
            COUNT(DISTINCT u.id) as total_users,
            COALESCE(SUM(order_stats.order_count), 0) as period_total_orders,
            COALESCE(SUM(order_stats.total_amount), 0) as period_total_revenue
        FROM companies c
        LEFT JOIN departments d ON c.id = d.company_id AND c.is_active = 1
        LEFT JOIN users u ON c.id = u.company_id AND c.is_active = 1
        LEFT JOIN (
            SELECT 
                company_id,
                COUNT(*) as order_count,
                SUM(total_amount) as total_amount
            FROM orders 
            WHERE delivery_date BETWEEN :period_start AND :period_end
            GROUP BY company_id
        ) order_stats ON c.id = order_stats.company_id
        WHERE c.is_active = 1
    ";
    
    $summary_stmt = $pdo->prepare($summary_sql);
    $summary_stmt->bindValue(':period_start', $search_period_start);
    $summary_stmt->bindValue(':period_end', $search_period_end);
    $summary_stmt->execute();
    $summary = $summary_stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("配達先企業管理画面エラー: " . $e->getMessage());
    $error_message = "データの取得中にエラーが発生しました。";
    $companies = [];
    $summary = [
        'active_companies' => 0,
        'total_departments' => 0,
        'total_users' => 0,
        'period_total_orders' => 0,
        'period_total_revenue' => 0
    ];
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>配達先企業管理 - Smiley配食事業</title>
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
        
        .company-row:hover {
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
                <a class="nav-link active" href="../pages/companies.php">配達先企業</a>
                <a class="nav-link" href="../pages/departments.php">部署管理</a>
                <a class="nav-link" href="../pages/users.php">利用者管理</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- ページヘッダー -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-building"></i> 配達先企業管理</h2>
            <div>
                <button class="btn btn-smiley me-2" data-bs-toggle="modal" data-bs-target="#addCompanyModal">
                    <i class="bi bi-plus"></i> 新規企業追加
                </button>
                <a href="../pages/csv_import.php" class="btn btn-outline-secondary">
                    <i class="bi bi-upload"></i> CSVインポート
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
            <div class="col-md-2">
                <div class="card stats-card">
                    <div class="card-body">
                        <h6 class="card-title text-muted">アクティブ企業</h6>
                        <h3 class="text-primary"><?= number_format($summary['active_companies']) ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card stats-card">
                    <div class="card-body">
                        <h6 class="card-title text-muted">総部署数</h6>
                        <h3 class="text-info"><?= number_format($summary['total_departments']) ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
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
                        <h6 class="card-title text-muted">期間内注文数</h6>
                        <h3 class="text-warning"><?= number_format($summary['period_total_orders']) ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card">
                    <div class="card-body">
                        <h6 class="card-title text-muted">期間内売上</h6>
                        <h3 class="amount-highlight">¥<?= number_format($summary['period_total_revenue']) ?></h3>
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
                        <label class="form-label">企業名・企業コード</label>
                        <input type="text" class="form-control" name="search_company" 
                               value="<?= htmlspecialchars($search_company) ?>" 
                               placeholder="企業名または企業コードで検索">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">集計期間（開始）</label>
                        <input type="date" class="form-control" name="period_start" 
                               value="<?= htmlspecialchars($search_period_start) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">集計期間（終了）</label>
                        <input type="date" class="form-control" name="period_end" 
                               value="<?= htmlspecialchars($search_period_end) ?>">
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

        <!-- 企業一覧テーブル -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-list"></i> 配達先企業一覧</span>
                <span class="badge bg-light text-dark"><?= number_format($total_companies) ?>件中 <?= number_format(($page-1)*$per_page + 1) ?>-<?= number_format(min($page*$per_page, $total_companies)) ?>件表示</span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($companies)): ?>
                    <div class="p-4 text-center text-muted">
                        <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                        <p class="mt-2">該当する配達先企業が見つかりません。</p>
                        <a href="../pages/csv_import.php" class="btn btn-smiley">CSVインポートで企業データを追加</a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>企業コード</th>
                                    <th>企業名</th>
                                    <th>部署数</th>
                                    <th>利用者数</th>
                                    <th>期間内注文数</th>
                                    <th>期間内売上</th>
                                    <th>最終注文日</th>
                                    <th>状態</th>
                                    <th>操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($companies as $company): ?>
                                    <tr class="company-row">
                                        <td>
                                            <code><?= htmlspecialchars($company['company_code']) ?></code>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($company['company_name']) ?></strong>
                                            <?php if ($company['contact_person']): ?>
                                                <br><small class="text-muted">担当: <?= htmlspecialchars($company['contact_person']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?= number_format($company['department_count']) ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-success"><?= number_format($company['user_count']) ?></span>
                                        </td>
                                        <td>
                                            <?php if ($company['period_order_count'] > 0): ?>
                                                <span class="badge bg-warning text-dark"><?= number_format($company['period_order_count']) ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($company['period_total_amount'] > 0): ?>
                                                <span class="amount-highlight">¥<?= number_format($company['period_total_amount']) ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($company['last_order_date']): ?>
                                                <?= date('m/d', strtotime($company['last_order_date'])) ?>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($company['is_active']): ?>
                                                <i class="bi bi-check-circle-fill status-active" title="アクティブ"></i>
                                            <?php else: ?>
                                                <i class="bi bi-x-circle-fill status-inactive" title="非アクティブ"></i>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-primary" title="詳細"
                                                        onclick="viewCompanyDetail(<?= $company['id'] ?>)">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                                <button class="btn btn-outline-success" title="部署管理"
                                                        onclick="manageDepartments(<?= $company['id'] ?>)">
                                                    <i class="bi bi-diagram-3"></i>
                                                </button>
                                                <button class="btn btn-outline-info" title="利用者管理"
                                                        onclick="manageUsers(<?= $company['id'] ?>)">
                                                    <i class="bi bi-people"></i>
                                                </button>
                                                <button class="btn btn-outline-warning" title="請求書生成"
                                                        onclick="generateInvoice(<?= $company['id'] ?>)">
                                                    <i class="bi bi-receipt"></i>
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
                            <a class="page-link" href="?page=<?= $i ?>&search_company=<?= urlencode($search_company) ?>&period_start=<?= urlencode($search_period_start) ?>&period_end=<?= urlencode($search_period_end) ?>">
                                <?= $i ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>

    <!-- 新規企業追加モーダル -->
    <div class="modal fade" id="addCompanyModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">新規配達先企業追加</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addCompanyForm">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">企業コード *</label>
                                <input type="text" class="form-control" name="company_code" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">企業名 *</label>
                                <input type="text" class="form-control" name="company_name" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">住所</label>
                            <input type="text" class="form-control" name="company_address">
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">担当者名</label>
                                <input type="text" class="form-control" name="contact_person">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">電話番号</label>
                                <input type="tel" class="form-control" name="contact_phone">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">メールアドレス</label>
                                <input type="email" class="form-control" name="contact_email">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">請求方法</label>
                            <select class="form-select" name="billing_method">
                                <option value="company">企業一括請求</option>
                                <option value="department">部署別請求</option>
                                <option value="individual">個人請求</option>
                                <option value="mixed">混合請求</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
                    <button type="button" class="btn btn-smiley" onclick="saveCompany()">保存</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // 企業詳細表示
        function viewCompanyDetail(companyId) {
            window.location.href = `company_detail.php?id=${companyId}`;
        }

        // 部署管理
        function manageDepartments(companyId) {
            window.location.href = `departments.php?company_id=${companyId}`;
        }

        // 利用者管理
        function manageUsers(companyId) {
            window.location.href = `users.php?company_id=${companyId}`;
        }

        // 請求書生成
        function generateInvoice(companyId) {
            window.location.href = `invoice_generate.php?company_id=${companyId}`;
        }

        // 新規企業保存
        function saveCompany() {
            const form = document.getElementById('addCompanyForm');
            const formData = new FormData(form);
            
            fetch('../api/companies.php', {
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
