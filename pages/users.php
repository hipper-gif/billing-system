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
        
        .filter-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .loading-spinner {
            display: none;
            text-align: center;
            padding: 2rem;
        }
        
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
        
        .pagination .page-link {
            color: var(--smiley-green);
            border-color: #dee2e6;
        }
        
        .pagination .page-item.active .page-link {
            background-color: var(--smiley-green);
            border-color: var(--smiley-green);
        }
        
        .form-select:focus, .form-control:focus {
            border-color: var(--smiley-green);
            box-shadow: 0 0 0 0.2rem rgba(46, 139, 87, 0.25);
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
            <div class="col-auto">
                <button class="btn btn-smiley" data-bs-toggle="modal" data-bs-target="#addUserModal">
                    <i class="fas fa-plus me-2"></i>利用者追加
                </button>
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

        <!-- フィルター -->
        <div class="filter-card">
            <div class="row">
                <div class="col-lg-3 col-md-6 mb-3">
                    <label for="companyFilter" class="form-label">企業フィルター</label>
                    <select class="form-select" id="companyFilter">
                        <option value="">すべての企業</option>
                    </select>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <label for="departmentFilter" class="form-label">部署フィルター</label>
                    <select class="form-select" id="departmentFilter">
                        <option value="">すべての部署</option>
                    </select>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <label for="statusFilter" class="form-label">活動状況</label>
                    <select class="form-select" id="statusFilter">
                        <option value="all">すべて</option>
                        <option value="active">活動中 (30日以内)</option>
                        <option value="warning">注意 (30-90日前)</option>
                        <option value="inactive">非活動 (90日以上)</option>
                    </select>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <label for="searchInput" class="form-label">検索</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="searchInput" placeholder="名前・メール・電話">
                        <button class="btn btn-outline-secondary" type="button" id="searchBtn">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- 利用者一覧 -->
        <div class="card">
            <div class="card-header bg-white">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">利用者一覧</h5>
                    <div class="d-flex align-items-center">
                        <span class="text-muted me-3" id="resultCount">-</span>
                        <select class="form-select form-select-sm" id="perPageSelect" style="width: auto;">
                            <option value="20">20件/ページ</option>
                            <option value="50">50件/ページ</option>
                            <option value="100">100件/ページ</option>
                        </select>
                    </div>
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

                <!-- ページネーション -->
                <div class="d-flex justify-content-between align-items-center p-3">
                    <div class="text-muted" id="paginationInfo">-</div>
                    <nav>
                        <ul class="pagination mb-0" id="pagination">
                            <!-- 動的に生成 -->
                        </ul>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <!-- 利用者追加モーダル -->
    <div class="modal fade" id="addUserModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">新規利用者追加</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addUserForm">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="addCompanyId" class="form-label">企業 <span class="text-danger">*</span></label>
                                <select class="form-select" id="addCompanyId" name="company_id" required>
                                    <option value="">企業を選択</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="addDepartmentId" class="form-label">部署 <span class="text-danger">*</span></label>
                                <select class="form-select" id="addDepartmentId" name="department_id" required>
                                    <option value="">部署を選択</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="addName" class="form-label">利用者名 <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="addName" name="name" required maxlength="100">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="addEmail" class="form-label">メールアドレス</label>
                                <input type="email" class="form-control" id="addEmail" name="email" maxlength="255">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="addPhone" class="form-label">電話番号</label>
                                <input type="tel" class="form-control" id="addPhone" name="phone" maxlength="20">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="addPaymentMethod" class="form-label">支払い方法</label>
                                <select class="form-select" id="addPaymentMethod" name="payment_method">
                                    <option value="company">企業請求</option>
                                    <option value="individual">個人請求</option>
                                    <option value="cash">現金</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="addAddress" class="form-label">住所</label>
                            <textarea class="form-control" id="addAddress" name="address" rows="2" maxlength="255"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="addAllergies" class="form-label">アレルギー情報</label>
                                <textarea class="form-control" id="addAllergies" name="allergies" rows="2" maxlength="500"></textarea>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="addDietaryRestrictions" class="form-label">食事制限</label>
                                <textarea class="form-control" id="addDietaryRestrictions" name="dietary_restrictions" rows="2" maxlength="500"></textarea>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
                    <button type="button" class="btn btn-smiley" id="saveUserBtn">
                        <i class="fas fa-save me-2"></i>保存
                    </button>
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
        class UserManager {
            constructor() {
                this.currentPage = 1;
                this.perPage = 20;
                this.totalPages = 1;
                this.filters = {
                    company_id: '',
                    department_id: '',
                    status: 'all',
                    search: ''
                };
                
                this.init();
            }
            
            async init() {
                await this.loadCompanies();
                await this.loadUsers();
                this.setupEventListeners();
            }
            
            setupEventListeners() {
                // フィルター変更
                document.getElementById('companyFilter').addEventListener('change', (e) => {
                    this.filters.company_id = e.target.value;
                    this.loadDepartments(e.target.value);
                    this.resetPagination();
                    this.loadUsers();
                });
                
                document.getElementById('departmentFilter').addEventListener('change', (e) => {
                    this.filters.department_id = e.target.value;
                    this.resetPagination();
                    this.loadUsers();
                });
                
                document.getElementById('statusFilter').addEventListener('change', (e) => {
                    this.filters.status = e.target.value;
                    this.resetPagination();
                    this.loadUsers();
                });
                
                // 検索
                document.getElementById('searchBtn').addEventListener('click', () => {
                    this.filters.search = document.getElementById('searchInput').value;
                    this.resetPagination();
                    this.loadUsers();
                });
                
                document.getElementById('searchInput').addEventListener('keypress', (e) => {
                    if (e.key === 'Enter') {
                        this.filters.search = e.target.value;
                        this.resetPagination();
                        this.loadUsers();
                    }
                });
                
                // ページサイズ変更
                document.getElementById('perPageSelect').addEventListener('change', (e) => {
                    this.perPage = parseInt(e.target.value);
                    this.resetPagination();
                    this.loadUsers();
                });
                
                // 利用者追加
                document.getElementById('saveUserBtn').addEventListener('click', () => {
                    this.saveUser();
                });
                
                // 利用者更新
                document.getElementById('updateUserBtn').addEventListener('click', () => {
                    this.updateUser();
                });
                
                // モーダル内企業選択
                document.getElementById('addCompanyId').addEventListener('change', (e) => {
                    this.loadDepartmentsForModal(e.target.value, 'addDepartmentId');
                });
                
                document.getElementById('editCompanyId').addEventListener('change', (e) => {
                    this.loadDepartmentsForModal(e.target.value, 'editDepartmentId');
                });
            }
            
            async loadCompanies() {
                try {
                    const response = await fetch('../api/companies.php');
                    const data = await response.json();
                    
                    if (data.companies) {
                        this.populateCompanySelect('companyFilter', data.companies);
                        this.populateCompanySelect('addCompanyId', data.companies);
                        this.populateCompanySelect('editCompanyId', data.companies);
                    }
                } catch (error) {
                    console.error('企業データの読み込みに失敗:', error);
                }
            }
            
            populateCompanySelect(selectId, companies) {
                const select = document.getElementById(selectId);
                const defaultOption = select.querySelector('option[value=""]');
                select.innerHTML = '';
                select.appendChild(defaultOption);
                
                companies.forEach(company => {
                    const option = document.createElement('option');
                    option.value = company.id;
                    option.textContent = company.name;
                    select.appendChild(option);
                });
            }
            
            async loadDepartments(companyId) {
                const departmentSelect = document.getElementById('departmentFilter');
                departmentSelect.innerHTML = '<option value="">すべての部署</option>';
                
                if (!companyId) return;
                
                try {
                    const response = await fetch(`../api/departments.php?company_id=${companyId}`);
                    const data = await response.json();
                    
                    if (data.departments) {
                        data.departments.forEach(dept => {
                            const option = document.createElement('option');
                            option.value = dept.id;
                            option.textContent = dept.name;
                            departmentSelect.appendChild(option);
                        });
                    }
                } catch (error) {
                    console.error('部署データの読み込みに失敗:', error);
                }
            }
            
            async loadDepartmentsForModal(companyId, selectId) {
                const select = document.getElementById(selectId);
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
            
            async loadUsers() {
                this.showLoading();
                
                try {
                    const params = new URLSearchParams({
                        page: this.currentPage,
                        limit: this.perPage,
                        ...this.filters
                    });
                    
                    const response = await fetch(`../api/users.php?${params}`);
                    const data = await response.json();
                    
                    if (data.users) {
                        this.renderUsers(data.users);
                        this.renderPagination(data.pagination);
                        this.renderStats(data.stats);
                        this.updateResultCount(data.pagination.total_count);
                    }
                } catch (error) {
                    console.error('利用者データの読み込みに失敗:', error);
                    this.showError('利用者データの読み込みに失敗しました');
                } finally {
                    this.hideLoading();
                }
            }
            
            renderUsers(users) {
                const tbody = document.getElementById('usersTableBody');
                tbody.innerHTML = '';
                
                users.forEach(user => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="user-avatar me-3">
                                    ${user.name.charAt(0)}
                                </div>
                                <div>
                                    <div class="fw-semibold">${this.escapeHtml(user.name)}</div>
                                    <small class="text-muted">${user.email || '-'}</small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="fw-semibold">${this.escapeHtml(user.company_name)}</div>
                            <small class="text-muted">${this.escapeHtml(user.department_name)}</small>
                        </td>
                        <td class="d-none-mobile">
                            <div>${user.phone || '-'}</div>
                            <small class="text-muted">${this.getPaymentMethodText(user.payment_method)}</small>
                        </td>
                        <td>
                            <span class="badge badge-${user.activity_status}">
                                ${this.getActivityStatusText(user.activity_status)}
                            </span>
                        </td>
                        <td class="d-none-mobile">
                            <div>注文: ${parseInt(user.total_orders).toLocaleString()}回</div>
                            <small class="text-muted">金額: ¥${parseInt(user.total_amount).toLocaleString()}</small>
                        </td>
                        <td class="d-none-mobile">
                            ${user.last_order_date ? this.formatDate(user.last_order_date) : '-'}
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
                                    <li><a class="dropdown-item" href="#" onclick="userManager.editUser(${user.id})">
                                        <i class="fas fa-edit me-2"></i>編集
                                    </a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-danger" href="#" onclick="userManager.deleteUser(${user.id}, '${this.escapeHtml(user.name)}')">
                                        <i class="fas fa-trash me-2"></i>削除
                                    </a></li>
                                </ul>
                            </div>
                        </td>
                    `;
                    tbody.appendChild(row);
                });
            }
            
            renderPagination(pagination) {
                this.totalPages = pagination.total_pages;
                const paginationEl = document.getElementById('pagination');
                paginationEl.innerHTML = '';
                
                // 前へボタン
                const prevLi = document.createElement('li');
                prevLi.className = `page-item ${this.currentPage === 1 ? 'disabled' : ''}`;
                prevLi.innerHTML = `<a class="page-link" href="#" onclick="userManager.changePage(${this.currentPage - 1})">前へ</a>`;
                paginationEl.appendChild(prevLi);
                
                // ページ番号
                const startPage = Math.max(1, this.currentPage - 2);
                const endPage = Math.min(this.totalPages, this.currentPage + 2);
                
                for (let i = startPage; i <= endPage; i++) {
                    const li = document.createElement('li');
                    li.className = `page-item ${i === this.currentPage ? 'active' : ''}`;
                    li.innerHTML = `<a class="page-link" href="#" onclick="userManager.changePage(${i})">${i}</a>`;
                    paginationEl.appendChild(li);
                }
                
                // 次へボタン
                const nextLi = document.createElement('li');
                nextLi.className = `page-item ${this.currentPage === this.totalPages ? 'disabled' : ''}`;
                nextLi.innerHTML = `<a class="page-link" href="#" onclick="userManager.changePage(${this.currentPage + 1})">次へ</a>`;
                paginationEl.appendChild(nextLi);
                
                // ページ情報更新
                document.getElementById('paginationInfo').textContent = 
                    `${pagination.total_count}件中 ${((this.currentPage - 1) * this.perPage) + 1}-${Math.min(this.currentPage * this.perPage, pagination.total_count)}件を表示`;
            }
            
            renderStats(stats) {
                document.getElementById('totalUsers').textContent = parseInt(stats.total_users).toLocaleString();
                document.getElementById('activeUsers').textContent = parseInt(stats.active_users).toLocaleString();
                document.getElementById('recentActiveUsers').textContent = parseInt(stats.recent_active_users).toLocaleString();
                document.getElementById('totalSales').textContent = `¥${parseInt(stats.total_sales).toLocaleString()}`;
            }
            
            updateResultCount(count) {
                document.getElementById('resultCount').textContent = `${count.toLocaleString()}件`;
            }
            
            changePage(page) {
                if (page >= 1 && page <= this.totalPages) {
                    this.currentPage = page;
                    this.loadUsers();
                }
            }
            
            resetPagination() {
                this.currentPage = 1;
            }
            
            async saveUser() {
                const form = document.getElementById('addUserForm');
                const formData = new FormData(form);
                const userData = Object.fromEntries(formData);
                
                try {
                    const response = await fetch('../api/users.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify(userData)
                    });
                    
                    const result = await response.json();
                    
                    if (response.ok && result.success) {
                        bootstrap.Modal.getInstance(document.getElementById('addUserModal')).hide();
                        form.reset();
                        this.loadUsers();
                        this.showAlert('利用者を追加しました', 'success');
                    } else {
                        this.showValidationErrors(result.errors || [result.error]);
                    }
                } catch (error) {
                    console.error('利用者追加エラー:', error);
                    this.showAlert('利用者の追加に失敗しました', 'danger');
                }
            }
            
            async editUser(userId) {
                try {
                    const response = await fetch(`../api/users.php?id=${userId}`);
                    const data = await response.json();
