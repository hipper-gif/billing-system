<?php
/**
 * 注文履歴一覧画面
 * 
 * 過去の注文履歴を表示
 */

session_start();

require_once __DIR__ . '/../classes/AuthManager.php';

$authManager = new AuthManager();

// ログインチェック
if (!$authManager->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// タイムアウトチェック
if ($authManager->checkTimeout()) {
    $authManager->logout();
    header('Location: login.php?timeout=1');
    exit;
}

$user = $authManager->getCurrentUser();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>注文履歴 - Smiley配食</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Material Icons -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    
    <style>
        :root {
            --primary-green: #4CAF50;
            --primary-blue: #2196F3;
            --warning-orange: #FF9800;
            --error-red: #F44336;
            --background-grey: #F5F5F5;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--background-grey);
            padding-bottom: 20px;
        }
        
        /* ヘッダー */
        .app-header {
            background: linear-gradient(135deg, var(--primary-green) 0%, #45a049 100%);
            color: white;
            padding: 15px 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 800px;
            margin: 0 auto;
        }
        
        .back-btn {
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            padding: 5px;
        }
        
        .header-title {
            font-size: 20px;
            font-weight: bold;
        }
        
        /* コンテンツ */
        .content-wrapper {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        
        /* フィルター */
        .filter-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .filter-group {
            margin-bottom: 15px;
        }
        
        .filter-group:last-child {
            margin-bottom: 0;
        }
        
        .filter-label {
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .filter-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .filter-btn {
            padding: 8px 16px;
            border: 2px solid #e0e0e0;
            background: white;
            border-radius: 20px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .filter-btn.active {
            background: var(--primary-green);
            color: white;
            border-color: var(--primary-green);
        }
        
        /* サマリーカード */
        .summary-card {
            background: linear-gradient(135deg, #E3F2FD 0%, #BBDEFB 100%);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-around;
            text-align: center;
        }
        
        .summary-item {
            flex: 1;
        }
        
        .summary-label {
            font-size: 13px;
            color: #666;
            margin-bottom: 5px;
        }
        
        .summary-value {
            font-size: 24px;
            font-weight: bold;
            color: var(--primary-blue);
        }
        
        /* 注文カード */
        .order-card {
            background: white;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .order-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
        }
        
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }
        
        .order-date {
            font-size: 16px;
            font-weight: bold;
            color: #333;
        }
        
        .order-status {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .status-confirmed {
            background: #E8F5E9;
            color: #4CAF50;
        }
        
        .status-cancelled {
            background: #FFEBEE;
            color: #F44336;
        }
        
        .status-delivered {
            background: #E3F2FD;
            color: #2196F3;
        }
        
        .order-body {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .order-info {
            flex: 1;
        }
        
        .order-menu {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }
        
        .order-detail {
            font-size: 13px;
            color: #666;
        }
        
        .order-amount {
            font-size: 20px;
            font-weight: bold;
            color: var(--primary-green);
            text-align: right;
        }
        
        /* ローディング */
        .loading {
            text-align: center;
            padding: 60px 20px;
        }
        
        .spinner-border {
            width: 3rem;
            height: 3rem;
        }
        
        /* 空状態 */
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            color: #999;
        }
        
        .empty-state .material-icons {
            font-size: 100px;
            margin-bottom: 20px;
            opacity: 0.3;
        }
        
        .empty-state-text {
            font-size: 18px;
            margin-bottom: 30px;
        }
        
        .btn-order-now {
            background: linear-gradient(135deg, var(--primary-green) 0%, #45a049 100%);
            border: none;
            color: white;
            padding: 15px 30px;
            font-size: 16px;
            font-weight: bold;
            border-radius: 25px;
            box-shadow: 0 4px 15px rgba(76, 175, 80, 0.4);
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-order-now:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(76, 175, 80, 0.6);
        }
        
        @media (max-width: 576px) {
            .summary-card {
                flex-direction: column;
                gap: 15px;
            }
            
            .order-body {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .order-amount {
                text-align: left;
            }
        }
    </style>
</head>
<body>
    <!-- ヘッダー -->
    <div class="app-header">
        <div class="header-content">
            <button class="back-btn" onclick="goBack()">
                <span class="material-icons">arrow_back</span>
            </button>
            <div class="header-title">注文履歴</div>
            <div style="width: 40px;"></div>
        </div>
    </div>
    
    <!-- コンテンツ -->
    <div class="content-wrapper">
        <!-- フィルター -->
        <div class="filter-card">
            <div class="filter-group">
                <div class="filter-label">期間</div>
                <div class="filter-buttons">
                    <button class="filter-btn active" data-period="all" onclick="filterByPeriod('all')">すべて</button>
                    <button class="filter-btn" data-period="this_month" onclick="filterByPeriod('this_month')">今月</button>
                    <button class="filter-btn" data-period="last_month" onclick="filterByPeriod('last_month')">先月</button>
                    <button class="filter-btn" data-period="3_months" onclick="filterByPeriod('3_months')">過去3ヶ月</button>
                </div>
            </div>
            
            <div class="filter-group">
                <div class="filter-label">ステータス</div>
                <div class="filter-buttons">
                    <button class="filter-btn active" data-status="all" onclick="filterByStatus('all')">すべて</button>
                    <button class="filter-btn" data-status="confirmed" onclick="filterByStatus('confirmed')">注文済み</button>
                    <button class="filter-btn" data-status="cancelled" onclick="filterByStatus('cancelled')">キャンセル</button>
                </div>
            </div>
        </div>
        
        <!-- サマリー -->
        <div class="summary-card" id="summaryCard" style="display: none;">
            <div class="summary-item">
                <div class="summary-label">注文件数</div>
                <div class="summary-value" id="summaryCount">0</div>
            </div>
            <div class="summary-item">
                <div class="summary-label">合計金額</div>
                <div class="summary-value" id="summaryAmount">¥0</div>
            </div>
        </div>
        
        <!-- ローディング -->
        <div id="loading" class="loading">
            <div class="spinner-border text-primary"></div>
            <p class="mt-3">注文履歴を読み込み中...</p>
        </div>
        
        <!-- 注文一覧 -->
        <div id="orderList" style="display: none;"></div>
        
        <!-- 空状態 -->
        <div id="emptyState" class="empty-state" style="display: none;">
            <div class="material-icons">receipt_long</div>
            <div class="empty-state-text">注文履歴がありません</div>
            <button class="btn-order-now" onclick="goToOrder()">
                <span class="material-icons" style="vertical-align: middle;">add_circle</span>
                今すぐ注文する
            </button>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        let allOrders = [];
        let filteredOrders = [];
        let currentPeriod = 'all';
        let currentStatus = 'all';
        
        // ページ読み込み時の処理
        document.addEventListener('DOMContentLoaded', function() {
            loadOrderHistory();
        });
        
        // 注文履歴を読み込み
        async function loadOrderHistory() {
            try {
                const response = await fetch('../api/orders_management.php?action=order_history');
                const result = await response.json();
                
                if (result.success) {
                    allOrders = result.data.orders;
                    applyFilters();
                }
            } catch (error) {
                console.error('Error loading history:', error);
                alert('注文履歴の読み込みに失敗しました');
            }
        }
        
        // 期間でフィルター
        function filterByPeriod(period) {
            currentPeriod = period;
            
            // ボタンの状態更新
            document.querySelectorAll('[data-period]').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');
            
            applyFilters();
        }
        
        // ステータスでフィルター
        function filterByStatus(status) {
            currentStatus = status;
            
            // ボタンの状態更新
            document.querySelectorAll('[data-status]').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');
            
            applyFilters();
        }
        
        // フィルター適用
        function applyFilters() {
            filteredOrders = allOrders.filter(order => {
                // 期間フィルター
                if (currentPeriod !== 'all') {
                    const orderDate = new Date(order.delivery_date);
                    const now = new Date();
                    
                    if (currentPeriod === 'this_month') {
                        if (orderDate.getMonth() !== now.getMonth() || 
                            orderDate.getFullYear() !== now.getFullYear()) {
                            return false;
                        }
                    } else if (currentPeriod === 'last_month') {
                        const lastMonth = new Date(now.getFullYear(), now.getMonth() - 1);
                        if (orderDate.getMonth() !== lastMonth.getMonth() || 
                            orderDate.getFullYear() !== lastMonth.getFullYear()) {
                            return false;
                        }
                    } else if (currentPeriod === '3_months') {
                        const threeMonthsAgo = new Date();
                        threeMonthsAgo.setMonth(threeMonthsAgo.getMonth() - 3);
                        if (orderDate < threeMonthsAgo) {
                            return false;
                        }
                    }
                }
                
                // ステータスフィルター
                if (currentStatus !== 'all' && order.order_status !== currentStatus) {
                    return false;
                }
                
                return true;
            });
            
            renderOrders();
        }
        
        // 注文を表示
        function renderOrders() {
            document.getElementById('loading').style.display = 'none';
            
            if (filteredOrders.length === 0) {
                document.getElementById('summaryCard').style.display = 'none';
                document.getElementById('orderList').style.display = 'none';
                document.getElementById('emptyState').style.display = 'block';
                return;
            }
            
            // サマリー更新
            const totalCount = filteredOrders.length;
            const totalAmount = filteredOrders.reduce((sum, order) => sum + parseFloat(order.total_amount), 0);
            
            document.getElementById('summaryCount').textContent = totalCount;
            document.getElementById('summaryAmount').textContent = '¥' + totalAmount.toLocaleString();
            document.getElementById('summaryCard').style.display = 'flex';
            
            // 注文一覧表示
            const orderList = document.getElementById('orderList');
            orderList.innerHTML = '';
            
            filteredOrders.forEach(order => {
                orderList.appendChild(createOrderCard(order));
            });
            
            document.getElementById('orderList').style.display = 'block';
            document.getElementById('emptyState').style.display = 'none';
        }
        
        // 注文カードを作成
        function createOrderCard(order) {
            const card = document.createElement('div');
            card.className = 'order-card';
            card.onclick = () => goToDetail(order.id);
            
            const date = new Date(order.delivery_date);
            const dateText = `${date.getMonth() + 1}月${date.getDate()}日`;
            
            const statusText = getStatusText(order.order_status);
            const statusClass = 'status-' + order.order_status;
            
            card.innerHTML = `
                <div class="order-header">
                    <div class="order-date">${dateText}</div>
                    <div class="order-status ${statusClass}">${statusText}</div>
                </div>
                <div class="order-body">
                    <div class="order-info">
                        <div class="order-menu">${order.product_name}</div>
                        <div class="order-detail">数量: ${order.quantity}個 | カテゴリ: ${order.category_name}</div>
                    </div>
                    <div class="order-amount">¥${Number(order.total_amount).toLocaleString()}</div>
                </div>
            `;
            
            return card;
        }
        
        // ステータステキスト取得
        function getStatusText(status) {
            const statusMap = {
                'confirmed': '注文済み',
                'cancelled': 'キャンセル',
                'delivered': '配達済み',
                'pending': '保留中'
            };
            return statusMap[status] || status;
        }
        
        // 注文詳細へ
        function goToDetail(orderId) {
            window.location.href = `order_detail.php?id=${orderId}`;
        }
        
        // 注文画面へ
        function goToOrder() {
            window.location.href = 'create_order.php';
        }
        
        // 戻る
        function goBack() {
            window.location.href = 'order_dashboard.php';
        }
    </script>
</body>
</html>
