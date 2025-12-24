<?php
/**
 * メニュー管理画面
 * 
 * 商品マスタの管理（追加・編集・削除）
 */

session_start();

require_once __DIR__ . '/../../classes/AuthManager.php';

$authManager = new AuthManager();

// ログイン＆管理者チェック
if (!$authManager->isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

$authManager->requireAdmin('../order_dashboard.php');

$user = $authManager->getCurrentUser();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>メニュー管理 - Smiley配食</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    
    <style>
        :root {
            --primary-green: #4CAF50;
            --admin-blue: #1976D2;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
        }
        
        /* サイドバー */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 250px;
            height: 100vh;
            background: linear-gradient(180deg, var(--admin-blue) 0%, #1565C0 100%);
            color: white;
            padding: 20px;
            overflow-y: auto;
        }
        
        .sidebar-header {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.2);
        }
        
        .sidebar-title {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .sidebar-subtitle {
            font-size: 12px;
            opacity: 0.8;
        }
        
        .sidebar-nav {
            list-style: none;
            padding: 0;
        }
        
        .sidebar-nav-item {
            margin-bottom: 5px;
        }
        
        .sidebar-nav-link {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s;
        }
        
        .sidebar-nav-link:hover {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        
        .sidebar-nav-link.active {
            background: rgba(255,255,255,0.2);
        }
        
        .sidebar-nav-link .material-icons {
            margin-right: 10px;
        }
        
        /* メインコンテンツ */
        .main-content {
            margin-left: 250px;
            padding: 30px;
        }
        
        .page-header {
            margin-bottom: 30px;
        }
        
        .page-title {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        /* カード */
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        /* テーブル */
        .table-container {
            background: white;
            border-radius: 12px;
            overflow: hidden;
        }
        
        .table {
            margin-bottom: 0;
        }
        
        .table thead {
            background: #f8f9fa;
        }
        
        .badge-active {
            background: #4CAF50;
        }
        
        .badge-inactive {
            background: #9E9E9E;
        }
        
        /* ボタン */
        .btn-primary {
            background: var(--primary-green);
            border: none;
        }
        
        .btn-primary:hover {
            background: #45a049;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 200px;
            }
            
            .main-content {
                margin-left: 200px;
                padding: 20px 15px;
            }
        }
    </style>
</head>
<body>
    <!-- サイドバー -->
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-title">🍱 Smiley配食</div>
            <div class="sidebar-subtitle">管理画面</div>
        </div>
        
        <ul class="sidebar-nav">
            <li class="sidebar-nav-item">
                <a href="menu_management.php" class="sidebar-nav-link active">
                    <span class="material-icons">restaurant_menu</span>
                    メニュー管理
                </a>
            </li>
            <li class="sidebar-nav-item">
                <a href="daily_menu_settings.php" class="sidebar-nav-link">
                    <span class="material-icons">event</span>
                    日替わり設定
                </a>
            </li>
            <li class="sidebar-nav-item">
                <a href="orders_list.php" class="sidebar-nav-link">
                    <span class="material-icons">list_alt</span>
                    注文一覧
                </a>
            </li>
            <li class="sidebar-nav-item">
                <a href="delivery_schedule.php" class="sidebar-nav-link">
                    <span class="material-icons">local_shipping</span>
                    配達スケジュール
                </a>
            </li>
            <li class="sidebar-nav-item">
                <a href="../order_dashboard.php" class="sidebar-nav-link">
                    <span class="material-icons">home</span>
                    ユーザー画面へ
                </a>
            </li>
        </ul>
    </div>
    
    <!-- メインコンテンツ -->
    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">メニュー管理</h1>
            <p class="text-muted">商品マスタの管理</p>
        </div>
        
        <!-- アクションバー -->
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addMenuModal">
                            <span class="material-icons" style="vertical-align: middle;">add</span>
                            新しいメニューを追加
                        </button>
                    </div>
                    <div>
                        <select class="form-select" id="categoryFilter" onchange="filterByCategory()">
                            <option value="all">すべてのカテゴリ</option>
                            <option value="DAILY">日替わり</option>
                            <option value="STANDARD">定番</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- メニュー一覧 -->
        <div class="table-container">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>商品コード</th>
                        <th>商品名</th>
                        <th>カテゴリ</th>
                        <th>価格</th>
                        <th>ステータス</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody id="menuTableBody">
                    <tr>
                        <td colspan="6" class="text-center py-5">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">読み込み中...</span>
                            </div>
                            <p class="mt-3">メニューを読み込み中...</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- 追加モーダル -->
    <div class="modal fade" id="addMenuModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">新しいメニューを追加</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addMenuForm">
                        <div class="mb-3">
                            <label class="form-label">商品コード *</label>
                            <input type="text" class="form-control" name="product_code" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">商品名 *</label>
                            <input type="text" class="form-control" name="product_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">カテゴリ *</label>
                            <select class="form-select" name="category_code" required>
                                <option value="DAILY">日替わり</option>
                                <option value="STANDARD">定番</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">価格 *</label>
                            <input type="number" class="form-control" name="unit_price" required min="0" step="10">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
                    <button type="button" class="btn btn-primary" onclick="addMenu()">追加</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 編集モーダル -->
    <div class="modal fade" id="editMenuModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">メニューを編集</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="editMenuForm">
                        <input type="hidden" name="id" id="editMenuId">
                        <div class="mb-3">
                            <label class="form-label">商品コード *</label>
                            <input type="text" class="form-control" name="product_code" id="editProductCode" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">商品名 *</label>
                            <input type="text" class="form-control" name="product_name" id="editProductName" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">カテゴリ *</label>
                            <select class="form-select" name="category_code" id="editCategoryCode" required>
                                <option value="DAILY">日替わり</option>
                                <option value="STANDARD">定番</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">価格 *</label>
                            <input type="number" class="form-control" name="unit_price" id="editUnitPrice" required min="0" step="10">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">ステータス</label>
                            <select class="form-select" name="is_active" id="editIsActive">
                                <option value="1">有効</option>
                                <option value="0">無効</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
                    <button type="button" class="btn btn-primary" onclick="updateMenu()">更新</button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        let allMenus = [];
        let currentCategory = 'all';
        
        document.addEventListener('DOMContentLoaded', function() {
            loadMenus();
        });
        
        // メニュー一覧を読み込み
        async function loadMenus() {
            try {
                const response = await fetch('../../api/admin/menu_management.php?action=list');
                const result = await response.json();
                
                if (result.success) {
                    allMenus = result.data;
                    renderMenus();
                }
            } catch (error) {
                console.error('Error loading menus:', error);
                alert('メニューの読み込みに失敗しました');
            }
        }
        
        // メニューを表示
        function renderMenus() {
            const tbody = document.getElementById('menuTableBody');
            tbody.innerHTML = '';
            
            const filteredMenus = allMenus.filter(menu => {
                if (currentCategory === 'all') return true;
                return menu.category_code === currentCategory;
            });
            
            if (filteredMenus.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4">メニューがありません</td></tr>';
                return;
            }
            
            filteredMenus.forEach(menu => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${menu.product_code}</td>
                    <td>${menu.product_name}</td>
                    <td>${menu.category_name}</td>
                    <td>¥${Number(menu.unit_price).toLocaleString()}</td>
                    <td>
                        <span class="badge ${menu.is_active ? 'badge-active' : 'badge-inactive'}">
                            ${menu.is_active ? '有効' : '無効'}
                        </span>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary" onclick="editMenu(${menu.id})">
                            編集
                        </button>
                    </td>
                `;
                tbody.appendChild(row);
            });
        }
        
        // カテゴリでフィルター
        function filterByCategory() {
            currentCategory = document.getElementById('categoryFilter').value;
            renderMenus();
        }
        
        // メニューを追加
        async function addMenu() {
            const form = document.getElementById('addMenuForm');
            const formData = new FormData(form);
            
            const data = {
                product_code: formData.get('product_code'),
                product_name: formData.get('product_name'),
                category_code: formData.get('category_code'),
                category_name: formData.get('category_code') === 'DAILY' ? '日替わり' : '定番',
                unit_price: parseFloat(formData.get('unit_price'))
            };
            
            try {
                const response = await fetch('../../api/admin/menu_management.php?action=create', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('メニューを追加しました');
                    bootstrap.Modal.getInstance(document.getElementById('addMenuModal')).hide();
                    form.reset();
                    loadMenus();
                } else {
                    alert('エラー: ' + result.error);
                }
            } catch (error) {
                console.error('Error adding menu:', error);
                alert('追加に失敗しました');
            }
        }
        
        // メニューを編集
        function editMenu(id) {
            const menu = allMenus.find(m => m.id === id);
            if (!menu) return;
            
            document.getElementById('editMenuId').value = menu.id;
            document.getElementById('editProductCode').value = menu.product_code;
            document.getElementById('editProductName').value = menu.product_name;
            document.getElementById('editCategoryCode').value = menu.category_code;
            document.getElementById('editUnitPrice').value = menu.unit_price;
            document.getElementById('editIsActive').value = menu.is_active;
            
            new bootstrap.Modal(document.getElementById('editMenuModal')).show();
        }
        
        // メニューを更新
        async function updateMenu() {
            const form = document.getElementById('editMenuForm');
            const formData = new FormData(form);
            
            const data = {
                id: parseInt(formData.get('id')),
                product_code: formData.get('product_code'),
                product_name: formData.get('product_name'),
                category_code: formData.get('category_code'),
                category_name: formData.get('category_code') === 'DAILY' ? '日替わり' : '定番',
                unit_price: parseFloat(formData.get('unit_price')),
                is_active: parseInt(formData.get('is_active'))
            };
            
            try {
                const response = await fetch('../../api/admin/menu_management.php?action=update', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('メニューを更新しました');
                    bootstrap.Modal.getInstance(document.getElementById('editMenuModal')).hide();
                    loadMenus();
                } else {
                    alert('エラー: ' + result.error);
                }
            } catch (error) {
                console.error('Error updating menu:', error);
                alert('更新に失敗しました');
            }
        }
    </script>
</body>
</html>
