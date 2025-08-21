<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>利用者詳細 - Smiley配食事業システム</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
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
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.12);
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
        
        .user-header {
            background: linear-gradient(135deg, var(--smiley-green), var(--smiley-dark-green));
            color: white;
            border-radius: 12px 12px 0 0;
            padding: 2rem;
        }
        
        .user-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 2rem;
            border: 3px solid rgba(255, 255, 255, 0.3);
        }
        
        .info-card {
            border-left: 4px solid var(--smiley-green);
            margin-bottom: 1rem;
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
        
        .badge-status {
            padding: 0.5rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .badge-active { background-color: #d4edda; color: #155724; }
        .badge-inactive { background-color: #f8d7da; color: #721c24; }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--smiley-green);
        }
        
        .loading-spinner {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 200px;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
        }
        
        @media (max-width: 768px) {
            .user-header {
                text-align: center;
            }
            
            .d-none-mobile {
                display: none !important;
            }
            
            .stat-number {
                font-size: 1.5rem;
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
                        <a class="nav-link" href="users.php"><i class="fas fa-users me-1"></i>利用者管理</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- 戻るボタン -->
        <div class="mb-3">
            <a href="users.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>利用者一覧に戻る
            </a>
        </div>

        <!-- ローディング -->
        <div id="loadingContainer" class="loading-spinner">
            <div class="text-center">
                <div class="spinner-border text-success" role="status" style="width: 3rem; height: 3rem;">
                    <span class="visually-hidden">読み込み中...</span>
                </div>
                <p class="mt-3 text-muted">利用者情報を読み込んでいます...</p>
            </div>
        </div>

        <!-- メインコンテンツ -->
        <div id="mainContent" style="display: none;">
            <!-- 利用者ヘッダー -->
            <div class="card mb-4">
                <div class="user-header">
                    <div class="row align-items-center">
                        <div class="col-md-auto">
                            <div class="user-avatar" id="userAvatar">
                                <!-- アバター文字 -->
                            </div>
                        </div>
                        <div class="col-md">
                            <h2 class="mb-1" id="userName">-</h2>
                            <p class="mb-2 opacity-75" id="userCompanyDept">-</p>
                            <div class="d-flex flex-wrap align-items-center">
                                <span class="badge badge-status me-2" id="userStatus">-</span>
                                <span class="text-white-50" id="userJoinDate">-</span>
                            </div>
                        </div>
                        <div class="col-md-auto">
                            <button class="btn btn-light" onclick="userDetail.editUser()">
                                <i class="fas fa-edit me-2"></i>編集
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 統計サマリー -->
            <div class="row mb-4">
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="card stats-card">
                        <div class="card-body text-center">
                            <div class="stat-number" id="totalOrders">-</div>
                            <div class="text-muted">総注文数</div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="card stats-card">
                        <div class="card-body text-center">
                            <div class="stat-number" id="totalAmount">-</div>
                            <div class="text-muted">総購入金額</div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="card stats-card">
                        <div class="card-body text-center">
                            <div class="stat-number" id="averageAmount">-</div>
                            <div class="text-muted">平均注文額</div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="card stats-card">
                        <div class="card-body text-center">
                            <div class="stat-number" id="recentOrders">-</div>
                            <div class="text-muted">30日以内注文</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- 左カラム -->
                <div class="col-lg-8">
                    <!-- 月別注文推移 -->
                    <div class="card mb-4">
                        <div class="card-header bg-white">
                            <h5 class="mb-0"><i class="fas fa-chart-line text-success me-2"></i>月別注文推移</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="monthlyChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- 注文履歴 -->
                    <div class="card">
                        <div class="card-header bg-white">
                            <h5 class="mb-0"><i class="fas fa-history text-success me-2"></i>最近の注文履歴</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>注文日</th>
                                            <th>商品名</th>
                                            <th class="d-none-mobile">数量</th>
                                            <th>金額</th>
                                            <th class="d-none-mobile">ステータス</th>
                                        </tr>
                                    </thead>
                                    <tbody id="ordersTableBody">
                                        <!-- 動的に生成 -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 右カラム -->
                <div class="col-lg-4">
                    <!-- 基本情報 -->
                    <div class="card info-card mb-3">
                        <div class="card-header bg-white">
                            <h6 class="mb-0"><i class="fas fa-user text-success me-2"></i>基本情報</h6>
                        </div>
                        <div class="card-body">
                            <div class="row mb-2">
                                <div class="col-sm-4 text-muted">メール:</div>
                                <div class="col-sm-8" id="userEmail">-</div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-sm-4 text-muted">電話:</div>
                                <div class="col-sm-8" id="userPhone">-</div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-sm-4 text-muted">住所:</div>
                                <div class="col-sm-8" id="userAddress">-</div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-sm-4 text-muted">支払方法:</div>
                                <div class="col-sm-8" id="userPaymentMethod">-</div>
                            </div>
                        </div>
                    </div>

                    <!-- 企業・部署情報 -->
                    <div class="card info-card mb-3">
                        <div class="card-header bg-white">
                            <h6 class="mb-0"><i class="fas fa-building text-success me-2"></i>所属情報</h6>
                        </div>
                        <div class="card-body">
                            <div class="row mb-2">
                                <div class="col-sm-4 text-muted">企業:</div>
                                <div class="col-sm-8" id="companyName">-</div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-sm-4 text-muted">部署:</div>
                                <div class="col-sm-8" id="departmentName">-</div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-sm-4 text-muted">部署責任者:</div>
                                <div class="col-sm-8" id="departmentManager">-</div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-sm-4 text-muted">部署電話:</div>
                                <div class="col-sm-8" id="departmentPhone">-</div>
                            </div>
                        </div>
                    </div>

                    <!-- 健康情報 -->
                    <div class="card info-card">
                        <div class="card-header bg-white">
                            <h6 class="mb-0"><i class="fas fa-heartbeat text-success me-2"></i>健康・食事情報</h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <small class="text-muted">アレルギー情報:</small>
                                <div id="userAllergies" class="mt-1">-</div>
                            </div>
                            <div>
                                <small class="text-muted">食事制限:</small>
                                <div id="userDietaryRestrictions" class="mt-1">-</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 利用者編集モーダル -->
    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">利用者情報編集</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="editUserForm">
                        <input type="hidden" id="editUserId" name="user_id">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="editCompanyId" class="form-label">企業 <span class="text-danger">*</span></label>
                                <select class="form-select" id="editCompanyId" name="company_id" required>
                                    <option value="">企業を選択</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="editDepartmentId" class="form-label">部署 <span class="text-danger">*</span></label>
                                <select class="form-select" id="editDepartmentId" name="department_id" required>
                                    <option value="">部署を選択</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="editName" class="form-label">利用者名 <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="editName" name="name" required maxlength="100">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="editEmail" class="form-label">メールアドレス</label>
                                <input type="email" class="form-control" id="editEmail" name="email" maxlength="255">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="editPhone" class="form-label">電話番号</label>
                                <input type="tel" class="form-control" id="editPhone" name="phone" maxlength="20">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="editPaymentMethod" class="form-label">支払い方法</label>
                                <select class="form-select" id="editPaymentMethod" name="payment_method">
                                    <option value="company">企業請求</option>
                                    <option value="individual">個人請求</option>
                                    <option value="cash">現金</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="editStatus" class="form-label">ステータス</label>
                                <select class="form-select" id="editStatus" name="status">
                                    <option value="active">有効</option>
                                    <option value="inactive">無効</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="editAddress" class="form-label">住所</label>
                            <textarea class="form-control" id="editAddress" name="address" rows="2" maxlength="255"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="editAllergies" class="form-label">アレルギー情報</label>
                                <textarea class="form-control" id="editAllergies" name="allergies" rows="2" maxlength="500"></textarea>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="editDietaryRestrictions" class="form-label">食事制限</label>
                                <textarea class="form-control" id="editDietaryRestrictions" name="dietary_restrictions" rows="2" maxlength="500"></textarea>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
                    <button type="button" class="btn btn-smiley" id="updateUserBtn">
                        <i class="fas fa-save me-2"></i>更新
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        class UserDetail {
            constructor() {
                this.userId = this.getUserIdFromUrl();
                this.user = null;
                this.monthlyChart = null;
                
                if (this.userId) {
                    this.init();
                } else {
                    this.showError('利用者IDが指定されていません');
                }
            }
            
            getUserIdFromUrl() {
                const urlParams = new URLSearchParams(window.location.search);
                return urlParams.get('id');
            }
            
            async init() {
                await this.loadUserDetail();
                await this.loadCompanies();
                this.setupEventListeners();
            }
            
            setupEventListeners() {
                document.getElementById('updateUserBtn').addEventListener('click', () => {
                    this.updateUser();
                });
                
                document.getElementById('editCompanyId').addEventListener('change', (e) => {
                    this.loadDepartmentsForModal(e.target.value);
                });
            }
            
            async loadUserDetail() {
                try {
                    const response = await fetch(`../api/users.php?id=${this.userId}`);
                    const data = await response.json();
                    
                    if (response.ok && data.user) {
                        this.user = data.user;
                        this.renderUserDetail(data);
                        this.renderMonthlyChart(data.monthly_stats);
                        this.renderOrders(data.orders);
                        document.getElementById('loadingContainer').style.display = 'none';
                        document.getElementById('mainContent').style.display = 'block';
                    } else {
                        this.showError(data.error || '利用者情報の取得に失敗しました');
                    }
                } catch (error) {
                    console.error('利用者詳細取得エラー:', error);
                    this.showError('利用者情報の取得に失敗しました');
                }
            }
            
            renderUserDetail(data) {
                const user = data.user;
                const totalStats = data.total_stats;
                
                // ヘッダー情報
                document.getElementById('userAvatar').textContent = user.name.charAt(0);
                document.getElementById('userName').textContent = user.name;
                document.getElementById('userCompanyDept').textContent = `${user.company_name} / ${user.department_name}`;
                
                const statusBadge = document.getElementById('userStatus');
                statusBadge.textContent = user.status === 'active' ? '有効' : '無効';
                statusBadge.className = `badge badge-status me-2 badge-${user.status}`;
                
                document.getElementById('userJoinDate').textContent = 
                    `登録日: ${this.formatDate(user.created_at)}`;
                
                // 統計情報
                document.getElementById('totalOrders').textContent = 
                    parseInt(totalStats.total_orders || 0).toLocaleString();
                document.getElementById('totalAmount').textContent = 
                    `¥${parseInt(totalStats.total_amount || 0).toLocaleString()}`;
                document.getElementById('averageAmount').textContent = 
                    `¥${parseInt(totalStats.average_amount || 0).toLocaleString()}`;
                document.getElementById('recentOrders').textContent = 
                    parseInt(totalStats.recent_orders || 0).toLocaleString();
                
                // 基本情報
                document.getElementById('userEmail').textContent = user.email || '-';
                document.getElementById('userPhone').textContent = user.phone || '-';
                document.getElementById('userAddress').textContent = user.address || '-';
                document.getElementById('userPaymentMethod').textContent = 
                    this.getPaymentMethodText(user.payment_method);
                
                // 所属情報
                document.getElementById('companyName').textContent = user.company_name;
                document.getElementById('departmentName').textContent = user.department_name;
                document.getElementById('departmentManager').textContent = user.department_manager || '-';
                document.getElementById('departmentPhone').textContent = user.department_phone || '-';
                
                // 健康情報
                document.getElementById('userAllergies').textContent = user.allergies || 'なし';
                document.getElementById('userDietaryRestrictions').textContent = user.dietary_restrictions || 'なし';
            }
            
            renderMonthlyChart(monthlyStats) {
                const ctx = document.getElementById('monthlyChart').getContext('2d');
                
                // データを月順にソート
                const sortedData = monthlyStats.sort((a, b) => a.month.localeCompare(b.month));
                
                const labels = sortedData.map(item => {
                    const date = new Date(item.month + '-01');
                    return date.toLocaleDateString('ja-JP', { year: 'numeric', month: 'short' });
                });
                
                const orderCounts = sortedData.map(item => parseInt(item.order_count));
                const amounts = sortedData.map(item => parseInt(item.total_amount));
                
                if (this.monthlyChart) {
                    this.monthlyChart.destroy();
                }
                
                this.monthlyChart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: '注文数',
                            data: orderCounts,
                            borderColor: '#2E8B57',
                            backgroundColor: 'rgba(46, 139, 87, 0.1)',
                            borderWidth: 3,
                            fill: true,
                            tension: 0.4,
                            yAxisID: 'y'
                        }, {
                            label: '金額',
                            data: amounts,
                            borderColor: '#90EE90',
                            backgroundColor: 'rgba(144, 238, 144, 0.1)',
                            borderWidth: 3,
                            fill: false,
                            tension: 0.4,
                            yAxisID: 'y1'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'top',
                            }
                        },
                        scales: {
                            y: {
                                type: 'linear',
                                display: true,
                                position: 'left',
                                title: {
                                    display: true,
                                    text: '注文数'
                                }
                            },
                            y1: {
                                type: 'linear',
                                display: true,
                                position: 'right',
                                title: {
                                    display: true,
                                    text: '金額 (円)'
                                },
                                grid: {
                                    drawOnChartArea: false,
                                },
                                ticks: {
                                    callback: function(value) {
                                        return '¥' + value.toLocaleString();
                                    }
                                }
                            }
                        }
                    }
                });
            }
            
            renderOrders(orders) {
                const tbody = document.getElementById('ordersTableBody');
                tbody.innerHTML = '';
                
                if (orders.length === 0) {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="5" class="text-center py-4 text-muted">
                                注文履歴がありません
                            </td>
                        </tr>
                    `;
                    return;
                }
                
                orders.forEach(order => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${this.formatDate(order.order_date)}</td>
                        <td>
                            <div class="fw-semibold">${this.escapeHtml(order.product_name || '商品情報なし')}</div>
                            <small class="text-muted">単価: ¥${parseInt(order.product_price || 0).toLocaleString()}</small>
                        </td>
                        <td class="d-none-mobile">${parseInt(order.quantity).toLocaleString()}</td>
                        <td class="fw-semibold">¥${parseInt(order.total_amount).toLocaleString()}</td>
                        <td class="d-none-mobile">
                            <span class="badge bg-success">完了</span>
                        </td>
                    `;
                    tbody.appendChild(row);
                });
            }
            
            async loadCompanies() {
                try {
                    const response = await fetch('../api/companies.php');
                    const data = await response.json();
                    
                    if (data.companies) {
                        this.populateCompanySelect(data.companies);
                    }
                } catch (error) {
                    console.error('企業データの読み込みに失敗:', error);
                }
            }
            
            populateCompanySelect(companies) {
                const select = document.getElementById('editCompanyId');
                select.innerHTML = '<option value="">企業を選択</option>';
                
                companies.forEach(company => {
                    const option = document.createElement('option');
                    option.value = company.id;
                    option.textContent = company.name;
                    select.appendChild(option);
                });
            }
            
            async loadDepartmentsForModal(companyId) {
                const select = document.getElementById('editDepartmentId');
                select.innerHTML = '<option value="">部署を選択</option>';
                
                if (!companyId) return;
                
                try {
                    const response = await fetch(`../api/departments.php?company_id=${companyId}`);
                    const data = await response.json();
                    
                    if (data.departments) {
                        data.departments.forEach(dept => {
                            const option = document.createElement('option');
                            option.value = dept.id;
                            option.textContent = dept.name;
                            select.appendChild(option);
                        });
                    }
                } catch (error) {
                    console.error('部署データの読み込みに失敗:', error);
                }
            }
            
            editUser() {
                if (!this.user) return;
                
                this.populateEditForm();
                new bootstrap.Modal(document.getElementById('editUserModal')).show();
            }
            
            populateEditForm() {
                const user = this.user;
                
                document.getElementById('editUserId').value = user.id;
                document.getElementById('editCompanyId').value = user.company_id;
                document.getElementById('editName').value = user.name;
                document.getElementById('editEmail').value = user.email || '';
                document.getElementById('editPhone').value = user.phone || '';
                document.getElementById('editAddress').value = user.address || '';
                document.getElementById('editAllergies').value = user.allergies || '';
                document.getElementById('editDietaryRestrictions').value = user.dietary_restrictions || '';
                document.getElementById('editPaymentMethod').value = user.payment_method || 'company';
                document.getElementById('editStatus').value = user.status || 'active';
                
                // 部署選択肢を読み込んでから値を設定
                this.loadDepartmentsForModal(user.company_id).then(() => {
                    document.getElementById('editDepartmentId').value = user.department_id;
                });
            }
            
            async updateUser() {
                const userId = document.getElementById('editUserId').value;
                const form = document.getElementById('editUserForm');
                const formData = new FormData(form);
                const userData = Object.fromEntries(formData);
                delete userData.user_id;
                
                try {
                    const response = await fetch(`../api/users.php?id=${userId}`, {
                        method: 'PUT',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify(userData)
                    });
                    
                    const result = await response.json();
                    
                    if (response.ok && result.success) {
                        bootstrap.Modal.getInstance(document.getElementById('editUserModal')).hide();
                        this.showAlert('利用者情報を更新しました', 'success');
                        // ページをリロードして最新情報を表示
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    } else {
                        this.showValidationErrors(result.errors || [result.error]);
                    }
                } catch (error) {
                    console.error('利用者更新エラー:', error);
                    this.showAlert('利用者情報の更新に失敗しました', 'danger');
                }
            }
            
            showError(message) {
                document.getElementById('loadingContainer').innerHTML = `
                    <div class="text-center">
                        <i class="fas fa-exclamation-circle text-danger" style="font-size: 3rem;"></i>
                        <h4 class="mt-3 text-danger">エラー</h4>
                        <p class="text-muted">${message}</p>
                        <a href="users.php" class="btn btn-secondary mt-3">
                            <i class="fas fa-arrow-left me-2"></i>利用者一覧に戻る
                        </a>
                    </div>
                `;
            }
            
            showAlert(message, type = 'info') {
                const existingAlert = document.querySelector('.alert-custom');
                if (existingAlert) {
                    existingAlert.remove();
                }
                
                const alertDiv = document.createElement('div');
                alertDiv.className = `alert alert-${type} alert-dismissible fade show alert-custom`;
                alertDiv.style.position = 'fixed';
                alertDiv.style.top = '20px';
                alertDiv.style.right = '20px';
                alertDiv.style.zIndex = '9999';
                alertDiv.style.minWidth = '300px';
                alertDiv.innerHTML = `
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                `;
                
                document.body.appendChild(alertDiv);
                
                setTimeout(() => {
                    if (alertDiv.parentNode) {
                        alertDiv.remove();
                    }
                }, 5000);
            }
            
            showValidationErrors(errors) {
                if (Array.isArray(errors)) {
                    errors.forEach(error => this.showAlert(error, 'danger'));
                } else if (typeof errors === 'object') {
                    Object.values(errors).forEach(error => this.showAlert(error, 'danger'));
                } else {
                    this.showAlert(errors, 'danger');
                }
            }
            
            escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }
            
            formatDate(dateString) {
                const date = new Date(dateString);
                return date.toLocaleDateString('ja-JP', {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric'
                });
            }
            
            getPaymentMethodText(method) {
                const methodMap = {
                    'company': '企業請求',
                    'individual': '個人請求',
                    'cash': '現金'
                };
                return methodMap[method] || '不明';
            }
        }
        
        // 初期化
        const userDetail = new UserDetail();
    </script>
</body>
</html>
