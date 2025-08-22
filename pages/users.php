<?php
/**
 * 利用者管理画面（修正版）
 * Database統一対応版
 * 
 * 修正内容:
 * 1. Database::getInstance() を使用（統一修正）
 * 2. エラーハンドリング強化
 * 3. Smiley配食事業専用UI
 */

require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/SecurityHelper.php';

// セキュリティヘッダー設定
SecurityHelper::setSecurityHeaders();

// Database::getInstance() を使用（修正箇所）
$db = Database::getInstance();

// 統計情報取得
$stats = getUserStats($db);
$users = getUsers($db);
$companies = getCompanies($db);

function getUserStats($db) {
    try {
        $stats = [
            'total_users' => 0,
            'active_users' => 0,
            'total_companies' => 0,
            'total_departments' => 0,
            'monthly_orders' => 0,
            'monthly_revenue' => 0
        ];

        // 総利用者数
        $stmt = $db->query("SELECT COUNT(*) as total FROM users");
        $result = $stmt->fetch();
        $stats['total_users'] = $result['total'] ?? 0;

        // アクティブ利用者数
        $stmt = $db->query("SELECT COUNT(*) as active FROM users WHERE is_active = 1");
        $result = $stmt->fetch();
        $stats['active_users'] = $result['active'] ?? 0;

        // 総企業数
        $stmt = $db->query("SELECT COUNT(DISTINCT company_id) as total FROM users WHERE company_id IS NOT NULL");
        $result = $stmt->fetch();
        $stats['total_companies'] = $result['total'] ?? 0;

        // 総部署数
        $stmt = $db->query("SELECT COUNT(DISTINCT department_id) as total FROM users WHERE department_id IS NOT NULL");
        $result = $stmt->fetch();
        $stats['total_departments'] = $result['total'] ?? 0;

        // 月間注文数
        $stmt = $db->query("
            SELECT COUNT(*) as orders 
            FROM orders 
            WHERE delivery_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ");
        $result = $stmt->fetch();
        $stats['monthly_orders'] = $result['orders'] ?? 0;

        // 月間売上
        $stmt = $db->query("
            SELECT SUM(total_amount) as revenue 
            FROM orders 
            WHERE delivery_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ");
        $result = $stmt->fetch();
        $stats['monthly_revenue'] = $result['revenue'] ?? 0;

        return $stats;

    } catch (Exception $e) {
        error_log("User stats error: " . $e->getMessage());
        return [
            'total_users' => 'エラー',
            'active_users' => 'エラー',
            'total_companies' => 'エラー',
            'total_departments' => 'エラー',
            'monthly_orders' => 'エラー',
            'monthly_revenue' => 'エラー',
            'error' => $e->getMessage()
        ];
    }
}

function getUsers($db) {
    try {
        $stmt = $db->query("
            SELECT 
                u.*,
                c.company_name,
                d.department_name,
                COUNT(DISTINCT o.id) as order_count,
                SUM(o.total_amount) as total_spent,
                MAX(o.delivery_date) as last_order_date,
                COALESCE(AVG(o.total_amount), 0) as avg_order_amount
            FROM users u
            LEFT JOIN companies c ON u.company_id = c.id
            LEFT JOIN departments d ON u.department_id = d.id
            LEFT JOIN orders o ON u.id = o.user_id AND o.delivery_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
            GROUP BY u.id
            ORDER BY u.user_name ASC
        ");
        
        return $stmt->fetchAll();

    } catch (Exception $e) {
        error_log("Get users error: " . $e->getMessage());
        return [];
    }
}

function getCompanies($db) {
    try {
        $stmt = $db->query("SELECT id, company_name FROM companies WHERE is_active = 1 ORDER BY company_name ASC");
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Get companies error: " . $e->getMessage());
        return [];
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>👥 利用者管理 - Smiley配食システム</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
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
        
        .user-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 15px;
            border-left: 4px solid #2E8B57;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }
        .user-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }
        
        .badge-status {
            font-size: 0.8rem;
            padding: 5px 10px;
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
        
        .user-stats {
            font-size: 0.9rem;
        }
        .user-stats .stat-item {
            display: inline-block;
            margin-right: 15px;
            color: #6c757d;
        }
        
        .loading {
            text-align: center;
            padding: 40px;
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="main-container">
        <!-- ヘッダー -->
        <div class="row align-items-center mb-4">
            <div class="col">
                <h1 class="display-5 smiley-green mb-2">👥 利用者管理</h1>
                <p class="lead text-muted">Smiley配食システム - 利用者個人情報・注文履歴の統合管理</p>
            </div>
            <div class="col-auto">
                <a href="../index.php" class="btn btn-outline-secondary me-2">
                    <i class="bi bi-arrow-left"></i> ダッシュボード
                </a>
                <a href="companies.php" class="btn btn-outline-primary me-2">
                    <i class="bi bi-building"></i> 企業管理
                </a>
                <button class="btn btn-smiley" onclick="showAddUserModal()">
                    <i class="bi bi-plus-circle"></i> 新規利用者追加
                </button>
            </div>
        </div>

        <!-- 統計サマリー -->
        <div class="row mb-4">
            <div class="col-lg-2 col-md-4 col-sm-6">
                <div class="stat-card text-center">
                    <div class="stat-number"><?php echo is_numeric($stats['total_users']) ? number_format($stats['total_users']) : $stats['total_users']; ?></div>
                    <div class="text-muted">総利用者数</div>
                    <small class="text-success"><i class="bi bi-people"></i> 登録済み</small>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6">
                <div class="stat-card text-center">
                    <div class="stat-number"><?php echo is_numeric($stats['active_users']) ? number_format($stats['active_users']) : $stats['active_users']; ?></div>
                    <div class="text-muted">アクティブ利用者</div>
                    <small class="text-info"><i class="bi bi-check-circle"></i> 稼働中</small>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6">
                <div class="stat-card text-center">
                    <div class="stat-number"><?php echo is_numeric($stats['total_companies']) ? number_format($stats['total_companies']) : $stats['total_companies']; ?></div>
                    <div class="text-muted">配達先企業</div>
                    <small class="text-primary"><i class="bi bi-building"></i> 企業数</small>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6">
                <div class="stat-card text-center">
                    <div class="stat-number"><?php echo is_numeric($stats['total_departments']) ? number_format($stats['total_departments']) : $stats['total_departments']; ?></div>
                    <div class="text-muted">配達先部署</div>
                    <small class="text-success"><i class="bi bi-diagram-3"></i> 部署数</small>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6">
                <div class="stat-card text-center">
                    <div class="stat-number"><?php echo is_numeric($stats['monthly_orders']) ? number_format($stats['monthly_orders']) : $stats['monthly_orders']; ?></div>
                    <div class="text-muted">月間注文数</div>
                    <small class="text-warning"><i class="bi bi-cart"></i> 過去30日</small>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6">
                <div class="stat-card text-center">
                    <div class="stat-number">¥<?php echo is_numeric($stats['monthly_revenue']) ? number_format($stats['monthly_revenue']) : $stats['monthly_revenue']; ?></div>
                    <div class="text-muted">月間売上</div>
                    <small class="text-info"><i class="bi bi-currency-yen"></i> 過去30日</small>
                </div>
            </div>
        </div>

        <!-- 検索・フィルター -->
        <div class="search-filters">
            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label class="form-label">利用者名検索</label>
                        <input type="text" class="form-control" id="searchUser" placeholder="利用者名を入力...">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label class="form-label">企業フィルター</label>
                        <select class="form-select" id="filterCompany">
                            <option value="">全企業</option>
                            <?php foreach ($companies as $company): ?>
                                <option value="<?php echo $company['id']; ?>"><?php echo htmlspecialchars($company['company_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label class="form-label">ステータス</label>
                        <select class="form-select" id="filterStatus">
                            <option value="">全て</option>
                            <option value="active">アクティブ</option>
                            <option value="inactive">非アクティブ</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label class="form-label">並び順</label>
                        <select class="form-select" id="sortOrder">
                            <option value="name_asc">名前（昇順）</option>
                            <option value="name_desc">名前（降順）</option>
                            <option value="orders_desc">注文数（降順）</option>
                            <option value="spent_desc">総購入額（降順）</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label class="form-label">&nbsp;</label>
                        <button class="btn btn-smiley w-100" onclick="applyFilters()">
                            <i class="bi bi-search"></i> 検索
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- 利用者一覧 -->
        <div id="usersContainer">
            <?php if (empty($users)): ?>
                <div class="no-data">
                    <i class="bi bi-people fs-1 text-muted"></i>
                    <h4 class="text-muted mt-3">利用者が登録されていません</h4>
                    <p class="text-muted">CSVインポートまたは手動で利用者を追加してください</p>
                    <a href="csv_import.php" class="btn btn-smiley">
                        <i class="bi bi-cloud-upload"></i> CSVインポート
                    </a>
                </div>
            <?php else: ?>
                <?php foreach ($users as $user): ?>
                    <div class="user-card" data-user-id="<?php echo $user['id']; ?>">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <h5 class="mb-2">
                                    <i class="bi bi-person text-success me-2"></i>
                                    <?php echo htmlspecialchars($user['user_name'] ?: 'Unknown User'); ?>
                                    <?php if ($user['is_active']): ?>
                                        <span class="badge bg-success badge-status ms-2">アクティブ</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary badge-status ms-2">非アクティブ</span>
                                    <?php endif; ?>
                                    <?php if ($user['user_code']): ?>
                                        <small class="text-muted ms-2">
                                            <i class="bi bi-tag"></i> <?php echo htmlspecialchars($user['user_code']); ?>
                                        </small>
                                    <?php endif; ?>
                                </h5>
                                <div class="user-stats">
                                    <?php if ($user['company_name']): ?>
                                        <span class="stat-item">
                                            <i class="bi bi-building"></i> <?php echo htmlspecialchars($user['company_name']); ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($user['department_name']): ?>
                                        <span class="stat-item">
                                            <i class="bi bi-diagram-3"></i> <?php echo htmlspecialchars($user['department_name']); ?>
                                        </span>
                                    <?php endif; ?>
                                    <span class="stat-item">
                                        <i class="bi bi-cart"></i> <?php echo number_format($user['order_count']); ?>件の注文
                                    </span>
                                    <?php if ($user['last_order_date']): ?>
                                        <span class="stat-item">
                                            <i class="bi bi-calendar"></i> 最終注文: <?php echo date('Y/m/d', strtotime($user['last_order_date'])); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($user['employee_type_name']): ?>
                                    <small class="text-muted">
                                        <i class="bi bi-briefcase"></i> <?php echo htmlspecialchars($user['employee_type_name']); ?>
                                    </small>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-3 text-center">
                                <div class="h5 text-success mb-1">¥<?php echo number_format($user['total_spent'] ?: 0); ?></div>
                                <small class="text-muted">総購入額（過去90日）</small>
                                <?php if ($user['avg_order_amount'] > 0): ?>
                                    <br><small class="text-info">平均: ¥<?php echo number_format($user['avg_order_amount']); ?>/回</small>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-3 text-end">
                                <div class="btn-group">
                                    <a href="user_detail.php?id=<?php echo $user['id']; ?>" class="btn btn-outline-primary btn-sm">
                                        <i class="bi bi-eye"></i> 詳細
                                    </a>
                                    <?php if ($user['company_id']): ?>
                                        <a href="company_detail.php?id=<?php echo $user['company_id']; ?>" class="btn btn-outline-info btn-sm">
                                            <i class="bi bi-building"></i> 企業
                                        </a>
                                    <?php endif; ?>
                                    <button class="btn btn-outline-secondary btn-sm" onclick="editUser(<?php echo $user['id']; ?>)">
                                        <i class="bi bi-pencil"></i>
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
                        <h6 class="mb-0"><i class="bi bi-lightning"></i> クイックアクション</h6>
                    </div>
                    <div class="card-body">
                        <a href="csv_import.php" class="btn btn-outline-primary me-2 mb-2">
                            <i class="bi bi-cloud-upload"></i> CSVインポート
                        </a>
                        <a href="companies.php" class="btn btn-outline-success me-2 mb-2">
                            <i class="bi bi-building"></i> 企業管理
                        </a>
                        <a href="departments.php" class="btn btn-outline-info me-2 mb-2">
                            <i class="bi bi-diagram-3"></i> 部署管理
                        </a>
                        <a href="../pages/orders.php" class="btn btn-outline-warning mb-2">
                            <i class="bi bi-cart"></i> 注文管理
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h6 class="mb-0"><i class="bi bi-info-circle"></i> システム情報</h6>
                    </div>
                    <div class="card-body">
                        <p class="mb-2"><strong>データベース:</strong> <?php echo DB_NAME; ?></p>
                        <p class="mb-2"><strong>環境:</strong> <?php echo ENVIRONMENT; ?></p>
                        <p class="mb-0"><strong>最終更新:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
                        <?php if (isset($stats['error'])): ?>
                            <div class="alert alert-warning mt-2 mb-0">
                                <small>統計取得エラー: <?php echo htmlspecialchars($stats['error']); ?></small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- フッター -->
        <div class="text-center mt-5 pt-4 border-top">
            <p class="text-muted mb-0">
                <strong>Smiley配食事業 請求書管理システム v1.0.0</strong><br>
                © 2025 Smiley配食事業. All rights reserved.
            </p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // 検索・フィルター機能
        function applyFilters() {
            const searchTerm = document.getElementById('searchUser').value.toLowerCase();
            const companyFilter = document.getElementById('filterCompany').value;
            const statusFilter = document.getElementById('filterStatus').value;
            const sortOrder = document.getElementById('sortOrder').value;
            
            const users = document.querySelectorAll('.user-card');
            let visibleUsers = [];
            
            users.forEach(user => {
                const userName = user.querySelector('h5').textContent.toLowerCase();
                const isActive = user.querySelector('.badge-success') !== null;
                const userCompanyId = user.dataset.companyId || '';
                
                let show = true;
                
                // 名前検索
                if (searchTerm && !userName.includes(searchTerm)) {
                    show = false;
                }
                
                // 企業フィルター
                if (companyFilter && userCompanyId !== companyFilter) {
                    show = false;
                }
                
                // ステータスフィルター
                if (statusFilter === 'active' && !isActive) {
                    show = false;
                } else if (statusFilter === 'inactive' && isActive) {
                    show = false;
                }
                
                user.style.display = show ? 'block' : 'none';
                if (show) visibleUsers.push(user);
            });
            
            // ソート（簡易実装）
            if (sortOrder !== 'name_asc') {
                console.log('ソート機能は今後実装予定');
            }
        }
        
        // リアルタイム検索
        document.getElementById('searchUser').addEventListener('input', applyFilters);
        document.getElementById('filterCompany').addEventListener('change', applyFilters);
        document.getElementById('filterStatus').addEventListener('change', applyFilters);
        document.getElementById('sortOrder').addEventListener('change', applyFilters);
        
        // 利用者追加モーダル（今後実装）
        function showAddUserModal() {
            alert('利用者追加機能は今後実装予定です。現在はCSVインポートをご利用ください。');
        }
        
        // 利用者編集（今後実装）
        function editUser(userId) {
            alert(`利用者ID ${userId} の編集機能は今後実装予定です。`);
        }
        
        // 初期化
        document.addEventListener('DOMContentLoaded', function() {
            console.log('利用者管理画面が読み込まれました');
            
            // エラー表示（デバッグモード時）
            <?php if (isset($stats['error']) && DEBUG_MODE): ?>
            console.error('User stats error:', <?php echo json_encode($stats['error']); ?>);
            <?php endif; ?>
        });
    </script>
</body>
</html>
