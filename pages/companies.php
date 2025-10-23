<?php
/**
 * 配達先企業管理画面（構文エラー修正版）
 * 222-223行目の括弧閉じ忘れ修正
 * 
 * 修正内容:
 * 1. 括弧の対応確認・修正
 * 2. Database::getInstance() 使用
 * 3. 構文チェック完了
 */

require_once '../config/database.php';
require_once '../classes/SecurityHelper.php';

// セキュリティヘッダー設定
SecurityHelper::setSecurityHeaders();

// Database::getInstance() を使用
$db = Database::getInstance();

// 統計情報取得
$stats = getCompanyStats($db);
$companies = getCompanies($db);

function getCompanyStats($db) {
    try {
        $stats = [
            'total_companies' => 0,
            'active_companies' => 0,
            'total_departments' => 0,
            'total_users' => 0,
            'monthly_revenue' => 0,
            'recent_orders' => 0
        ];

        // 総企業数
        $stmt = $db->query("SELECT COUNT(*) as total FROM companies");
        $result = $stmt->fetch();
        $stats['total_companies'] = $result['total'] ?? 0;

        // アクティブ企業数
        $stmt = $db->query("SELECT COUNT(*) as active FROM companies WHERE is_active = 1");
        $result = $stmt->fetch();
        $stats['active_companies'] = $result['active'] ?? 0;

        // 総部署数
        $stmt = $db->query("SELECT COUNT(*) as total FROM departments WHERE is_active = 1");
        $result = $stmt->fetch();
        $stats['total_departments'] = $result['total'] ?? 0;

        // 総利用者数
        $stmt = $db->query("SELECT COUNT(*) as total FROM users WHERE is_active = 1");
        $result = $stmt->fetch();
        $stats['total_users'] = $result['total'] ?? 0;

        // 月間売上
        $stmt = $db->query("
            SELECT SUM(total_amount) as revenue 
            FROM orders 
            WHERE delivery_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ");
        $result = $stmt->fetch();
        $stats['monthly_revenue'] = $result['revenue'] ?? 0;

        // 最近の注文数
        $stmt = $db->query("
            SELECT COUNT(*) as recent 
            FROM orders 
            WHERE delivery_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        ");
        $result = $stmt->fetch();
        $stats['recent_orders'] = $result['recent'] ?? 0;

        return $stats;

    } catch (Exception $e) {
        error_log("Company stats error: " . $e->getMessage());
        return [
            'total_companies' => 'エラー',
            'active_companies' => 'エラー',
            'total_departments' => 'エラー',
            'total_users' => 'エラー',
            'monthly_revenue' => 'エラー',
            'recent_orders' => 'エラー',
            'error' => $e->getMessage()
        ];
    }
}

function getCompanies($db) {
    try {
        $stmt = $db->query("
            SELECT 
                c.*,
                COUNT(DISTINCT d.id) as department_count,
                COUNT(DISTINCT u.id) as user_count,
                COUNT(DISTINCT o.id) as order_count,
                SUM(o.total_amount) as total_revenue,
                MAX(o.delivery_date) as last_order_date
            FROM companies c
            LEFT JOIN departments d ON c.id = d.company_id AND d.is_active = 1
            LEFT JOIN users u ON c.id = u.company_id AND u.is_active = 1
            LEFT JOIN orders o ON u.user_code = o.user_code AND o.delivery_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
            WHERE c.is_active = 1
            GROUP BY c.id
            ORDER BY c.company_name ASC
        ");
        
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
    <title>🏢 配達先企業管理 - Smiley配食システム</title>
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
        
        .company-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 15px;
            border-left: 4px solid #2E8B57;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }
        .company-card:hover {
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
        
        .company-stats {
            font-size: 0.9rem;
        }
        .company-stats .stat-item {
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
                <h1 class="display-5 smiley-green mb-2">🏢 配達先企業管理</h1>
                <p class="lead text-muted">Smiley配食システム - 企業・部署・利用者の統合管理</p>
            </div>
            <div class="col-auto">
                <a href="../index.php" class="btn btn-outline-secondary me-2">
                    <i class="bi bi-arrow-left"></i> ダッシュボード
                </a>
                <button class="btn btn-smiley" onclick="showAddCompanyModal()">
                    <i class="bi bi-plus-circle"></i> 新規企業追加
                </button>
            </div>
        </div>

        <!-- 統計サマリー -->
        <div class="row mb-4">
            <div class="col-lg-2 col-md-4 col-sm-6">
                <div class="stat-card text-center">
                    <div class="stat-number"><?php echo is_numeric($stats['total_companies']) ? number_format($stats['total_companies']) : $stats['total_companies']; ?></div>
                    <div class="text-muted">総企業数</div>
                    <small class="text-success"><i class="bi bi-building"></i> 登録済み</small>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6">
                <div class="stat-card text-center">
                    <div class="stat-number"><?php echo is_numeric($stats['active_companies']) ? number_format($stats['active_companies']) : $stats['active_companies']; ?></div>
                    <div class="text-muted">アクティブ企業</div>
                    <small class="text-info"><i class="bi bi-check-circle"></i> 稼働中</small>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6">
                <div class="stat-card text-center">
                    <div class="stat-number"><?php echo is_numeric($stats['total_departments']) ? number_format($stats['total_departments']) : $stats['total_departments']; ?></div>
                    <div class="text-muted">総部署数</div>
                    <small class="text-primary"><i class="bi bi-diagram-3"></i> 配達先</small>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6">
                <div class="stat-card text-center">
                    <div class="stat-number"><?php echo is_numeric($stats['total_users']) ? number_format($stats['total_users']) : $stats['total_users']; ?></div>
                    <div class="text-muted">総利用者数</div>
                    <small class="text-success"><i class="bi bi-people"></i> 登録済み</small>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6">
                <div class="stat-card text-center">
                    <div class="stat-number">¥<?php echo is_numeric($stats['monthly_revenue']) ? number_format($stats['monthly_revenue']) : $stats['monthly_revenue']; ?></div>
                    <div class="text-muted">月間売上</div>
                    <small class="text-warning"><i class="bi bi-currency-yen"></i> 過去30日</small>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6">
                <div class="stat-card text-center">
                    <div class="stat-number"><?php echo is_numeric($stats['recent_orders']) ? number_format($stats['recent_orders']) : $stats['recent_orders']; ?></div>
                    <div class="text-muted">週間注文数</div>
                    <small class="text-info"><i class="bi bi-cart"></i> 過去7日</small>
                </div>
            </div>
        </div>

        <!-- 検索・フィルター -->
        <div class="search-filters">
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label class="form-label">企業名検索</label>
                        <input type="text" class="form-control" id="searchCompany" placeholder="企業名を入力...">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label class="form-label">ステータス</label>
                        <select class="form-select" id="filterStatus">
                            <option value="">全て</option>
                            <option value="active">アクティブ</option>
                            <option value="inactive">非アクティブ</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label class="form-label">並び順</label>
                        <select class="form-select" id="sortOrder">
                            <option value="name_asc">企業名（昇順）</option>
                            <option value="name_desc">企業名（降順）</option>
                            <option value="revenue_desc">売上（降順）</option>
                            <option value="orders_desc">注文数（降順）</option>
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

        <!-- 企業一覧 -->
        <div id="companiesContainer">
            <?php if (empty($companies)): ?>
                <div class="no-data">
                    <i class="bi bi-building fs-1 text-muted"></i>
                    <h4 class="text-muted mt-3">配達先企業が登録されていません</h4>
                    <p class="text-muted">CSVインポートまたは手動で企業を追加してください</p>
                    <a href="csv_import.php" class="btn btn-smiley">
                        <i class="bi bi-cloud-upload"></i> CSVインポート
                    </a>
                </div>
            <?php else: ?>
                <?php foreach ($companies as $company): ?>
                    <div class="company-card" data-company-id="<?php echo $company['id']; ?>">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <h5 class="mb-2">
                                    <i class="bi bi-building text-success me-2"></i>
                                    <?php echo htmlspecialchars($company['company_name']); ?>
                                    <?php if ($company['is_active']): ?>
                                        <span class="badge bg-success badge-status ms-2">アクティブ</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary badge-status ms-2">非アクティブ</span>
                                    <?php endif; ?>
                                </h5>
                                <div class="company-stats">
                                    <span class="stat-item">
                                        <i class="bi bi-diagram-3"></i> <?php echo number_format($company['department_count']); ?>部署
                                    </span>
                                    <span class="stat-item">
                                        <i class="bi bi-people"></i> <?php echo number_format($company['user_count']); ?>名
                                    </span>
                                    <span class="stat-item">
                                        <i class="bi bi-cart"></i> <?php echo number_format($company['order_count']); ?>件
                                    </span>
                                    <?php if ($company['last_order_date']): ?>
                                        <span class="stat-item">
                                            <i class="bi bi-calendar"></i> 最終注文: <?php echo date('Y/m/d', strtotime($company['last_order_date'])); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($company['address_detail']): ?>
                                    <small class="text-muted">
                                        <i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($company['address_detail']); ?>
                                    </small>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-3 text-center">
                                <div class="h5 text-success mb-1">¥<?php echo number_format($company['total_revenue'] ?: 0); ?></div>
                                <small class="text-muted">過去90日売上</small>
                            </div>
                            <div class="col-md-3 text-end">
                                <div class="btn-group">
                                    <a href="company_detail.php?id=<?php echo $company['id']; ?>" class="btn btn-outline-primary btn-sm">
                                        <i class="bi bi-eye"></i> 詳細
                                    </a>
                                    <a href="departments.php?company_id=<?php echo $company['id']; ?>" class="btn btn-outline-info btn-sm">
                                        <i class="bi bi-diagram-3"></i> 部署
                                    </a>
                                    <a href="users.php?company_id=<?php echo $company['id']; ?>" class="btn btn-outline-success btn-sm">
                                        <i class="bi bi-people"></i> 利用者
                                    </a>
                                    <button class="btn btn-outline-secondary btn-sm" onclick="editCompany(<?php echo $company['id']; ?>)">
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
                        <a href="users.php" class="btn btn-outline-success me-2 mb-2">
                            <i class="bi bi-people"></i> 利用者管理
                        </a>
                        <a href="departments.php" class="btn btn-outline-info me-2 mb-2">
                            <i class="bi bi-diagram-3"></i> 部署管理
                        </a>
                        <a href="../pages/system_health.php" class="btn btn-outline-warning mb-2">
                            <i class="bi bi-gear"></i> システム状況
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
            const searchTerm = document.getElementById('searchCompany').value.toLowerCase();
            const statusFilter = document.getElementById('filterStatus').value;
            const sortOrder = document.getElementById('sortOrder').value;
            
            const companies = document.querySelectorAll('.company-card');
            let visibleCompanies = [];
            
            companies.forEach(company => {
                const companyName = company.querySelector('h5').textContent.toLowerCase();
                const isActive = company.querySelector('.badge-success') !== null;
                
                let show = true;
                
                // 名前検索
                if (searchTerm && !companyName.includes(searchTerm)) {
                    show = false;
                }
                
                // ステータスフィルター
                if (statusFilter === 'active' && !isActive) {
                    show = false;
                } else if (statusFilter === 'inactive' && isActive) {
                    show = false;
                }
                
                company.style.display = show ? 'block' : 'none';
                if (show) visibleCompanies.push(company);
            });
            
            // ソート（簡易実装）
            if (sortOrder !== 'name_asc') {
                console.log('ソート機能は今後実装予定');
            }
        }
        
        // リアルタイム検索
        document.getElementById('searchCompany').addEventListener('input', applyFilters);
        document.getElementById('filterStatus').addEventListener('change', applyFilters);
        document.getElementById('sortOrder').addEventListener('change', applyFilters);
        
        // 企業追加モーダル（今後実装）
        function showAddCompanyModal() {
            alert('企業追加機能は今後実装予定です。現在はCSVインポートをご利用ください。');
        }
        
        // 企業編集（今後実装）
        function editCompany(companyId) {
            alert(`企業ID ${companyId} の編集機能は今後実装予定です。`);
        }
        
        // 初期化
        document.addEventListener('DOMContentLoaded', function() {
            console.log('配達先企業管理画面が読み込まれました');
            
            // エラー表示（デバッグモード時）
            <?php if (isset($stats['error']) && DEBUG_MODE): ?>
            console.error('Company stats error:', <?php echo json_encode($stats['error']); ?>);
            <?php endif; ?>
        });
    </script>
</body>
</html>
