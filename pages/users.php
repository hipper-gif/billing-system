<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>利用者管理 - Smiley配食事業システム</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --smiley-green: #2E8B57;
            --smiley-light-green: #90EE90;
            --smiley-dark-green: #1F5F3F;
        }
        
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .navbar {
            background: linear-gradient(135deg, var(--smiley-green), var(--smiley-dark-green));
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .navbar-brand {
            font-weight: 700;
            font-size: 1.4rem;
        }
        
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 25px rgba(0,0,0,0.12);
        }
        
        .stats-card {
            border-left: 4px solid var(--smiley-green);
        }
        
        .btn-smiley {
            background: linear-gradient(135deg, var(--smiley-green), var(--smiley-dark-green));
            border: none;
            color: white;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .btn-smiley:hover {
            background: linear-gradient(135deg, var(--smiley-dark-green), var(--smiley-green));
            transform: translateY(-1px);
            color: white;
        }
        
        .table th {
            background-color: var(--smiley-green);
            color: white;
            border: none;
            font-weight: 600;
        }
        
        .table td {
            vertical-align: middle;
            border-color: #e9ecef;
        }
        
        .badge-activity {
            padding: 0.5rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .badge-active { background-color: #d4edda; color: #155724; }
        .badge-warning { background-color: #fff3cd; color: #856404; }
        .badge-inactive { background-color: #f8d7da; color: #721c24; }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--smiley-light-green), var(--smiley-green));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 1.1rem;
        }
        
        .loading-spinner {
            display: none;
            text-align: center;
            padding: 2rem;
        }
        
        @media (max-width: 768px) {
            .table-responsive {
                font-size: 0.9rem;
            }
            
            .d-none-mobile {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <!-- ナビゲーション -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="../index.php">
                <i class="fas fa-utensils me-2"></i>Smiley配食システム
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../index.php"><i class="fas fa-home me-1"></i>ダッシュボード</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="companies.php"><i class="fas fa-building me-1"></i>企業管理</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="departments.php"><i class="fas fa-sitemap me-1"></i>部署管理</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="users.php"><i class="fas fa-users me-1"></i>利用者管理</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- ページヘッダー -->
        <div class="row mb-4">
            <div class="col">
                <h2><i class="fas fa-users text-success me-2"></i>利用者管理</h2>
                <p class="text-muted mb-0">配食サービス利用者の管理・統計確認</p>
            </div>
        </div>

        <!-- 統計サマリー -->
        <div class="row mb-4" id="statsContainer">
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card stats-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <h6 class="card-title text-muted mb-1">総利用者数</h6>
                                <h3 class="mb-0" id="totalUsers">-</h3>
                            </div>
                            <div class="text-success">
                                <i class="fas fa-users fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card stats-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <h6 class="card-title text-muted mb-1">アクティブ利用者</h6>
                                <h3 class="mb-0" id="activeUsers">-</h3>
                            </div>
                            <div class="text-success">
                                <i class="fas fa-user-check fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card stats-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <h6 class="card-title text-muted mb-1">30日以内注文</h6>
                                <h3 class="mb-0" id="recentActiveUsers">-</h3>
                            </div>
                            <div class="text-success">
                                <i class="fas fa-shopping-cart fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card stats-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <h6 class="card-title text-muted mb-1">総売上</h6>
                                <h3 class="mb-0" id="totalSales">-</h3>
                            </div>
                            <div class="text-success">
                                <i class="fas fa-yen-sign fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 利用者一覧 -->
        <div class="card">
            <div class="card-header bg-white">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">利用者一覧</h5>
                    <span class="text-muted" id="resultCount">-</span>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="loading-spinner" id="loadingSpinner">
                    <div class="spinner-border text-success" role="status">
                        <span class="visually-hidden">読み込み中...</span>
                    </div>
                    <p class="mt-2 text-muted">利用者データを読み込んでいます...</p>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>利用者</th>
                                <th>企業・部署</th>
                                <th class="d-none-mobile">連絡先</th>
                                <th>活動状況</th>
                                <th class="d-none-mobile">注文統計</th>
                                <th class="d-none-mobile">最終注文</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody id="usersTableBody">
                            <!-- 動的に生成 -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // シンプルで確実な実装
        async function loadUsers() {
            console.log('利用者データ読み込み開始');
            
            // ローディング表示
            document.getElementById('loadingSpinner').style.display = 'block';
            
            try {
                const response = await fetch('../api/users.php');
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                const data = await response.json();
                console.log('データ取得成功:', data);
                
                // 統計情報表示
                if (data.stats) {
                    document.getElementById('totalUsers').textContent = parseInt(data.stats.total_users || 0).toLocaleString();
                    document.getElementById('activeUsers').textContent = parseInt(data.stats.active_users || 0).toLocaleString();
                    document.getElementById('recentActiveUsers').textContent = parseInt(data.stats.recent_active_users || 0).toLocaleString();
                    document.getElementById('totalSales').textContent = `¥${parseInt(data.stats.total_sales || 0).toLocaleString()}`;
                }
                
                // 利用者一覧表示
                if (data.users && data.users.length > 0) {
                    displayUsers(data.users);
                    document.getElementById('resultCount').textContent = `${data.users.length}件`;
                } else {
                    showNoData();
                }
                
            } catch (error) {
                console.error('エラー:', error);
                showError('データの読み込みに失敗しました: ' + error.message);
            } finally {
                document.getElementById('loadingSpinner').style.display = 'none';
            }
        }
        
        function displayUsers(users) {
            const tbody = document.getElementById('usersTableBody');
            tbody.innerHTML = '';
            
            users.forEach(user => {
                const row = document.createElement('tr');
                
                // 企業名・部署名の表示
                const companyName = user.company_name_display || user.company_name_from_table || user.company_name || '-';
                const departmentName = user.department_name_display || user.department_name || user.department || '-';
                
                // 活動状況の表示
                const activityBadge = getActivityBadge(user.activity_status);
                
                // 支払い方法の表示
                const paymentMethod = getPaymentMethodText(user.payment_method);
                
                // 最終注文日の表示
                const lastOrderDate = user.last_order_date ? formatDate(user.last_order_date) : '-';
                
                row.innerHTML = `
                    <td>
                        <div class="d-flex align-items-center">
                            <div class="user-avatar me-3">
                                ${escapeHtml(user.user_name).charAt(0)}
                            </div>
                            <div>
                                <div class="fw-semibold">${escapeHtml(user.user_name)}</div>
                                <small class="text-muted">${user.email || '-'}</small>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="fw-semibold">${escapeHtml(companyName)}</div>
                        <small class="text-muted">${escapeHtml(departmentName)}</small>
                    </td>
                    <td class="d-none-mobile">
                        <div>${user.phone || '-'}</div>
                        <small class="text-muted">${paymentMethod}</small>
                    </td>
                    <td>
                        ${activityBadge}
                    </td>
                    <td class="d-none-mobile">
                        <div>注文: ${parseInt(user.total_orders || 0).toLocaleString()}回</div>
                        <small class="text-muted">金額: ¥${parseInt(user.total_amount || 0).toLocaleString()}</small>
                    </td>
                    <td class="d-none-mobile">
                        ${lastOrderDate}
                    </td>
                    <td>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                操作
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="user_detail.php?id=${user.id}">
                                    <i class="fas fa-eye me-2"></i>詳細
                                </a></li>
                            </ul>
                        </div>
                    </td>
                `;
                
                tbody.appendChild(row);
            });
            
            console.log(`${users.length}件の利用者を表示しました`);
        }
        
        function getActivityBadge(status) {
            const statusMap = {
                'active': { class: 'badge-active', text: '活動中' },
                'warning': { class: 'badge-warning', text: '注意' },
                'inactive': { class: 'badge-inactive', text: '非活動' }
            };
            
            const statusInfo = statusMap[status] || statusMap['inactive'];
            return `<span class="badge badge-activity ${statusInfo.class}">${statusInfo.text}</span>`;
        }
        
        function getPaymentMethodText(method) {
            const methodMap = {
                'cash': '現金',
                'bank_transfer': '銀行振込',
                'account_debit': '口座振替',
                'mixed': '混合'
            };
            return methodMap[method] || '不明';
        }
        
        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('ja-JP', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function showError(message) {
            const tbody = document.getElementById('usersTableBody');
            tbody.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center py-4">
                        <i class="fas fa-exclamation-circle text-danger me-2"></i>
                        ${message}
                    </td>
                </tr>
            `;
        }
        
        function showNoData() {
            const tbody = document.getElementById('usersTableBody');
            tbody.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center py-4">
                        <i class="fas fa-info-circle text-muted me-2"></i>
                        利用者データがありません
                    </td>
                </tr>
            `;
        }
        
        // ページ読み込み完了後に実行
        document.addEventListener('DOMContentLoaded', function() {
            console.log('ページ読み込み完了 - 利用者データ読み込み開始');
            loadUsers();
        });
    </script>
</body>
</html>
