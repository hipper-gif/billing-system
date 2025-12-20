<?php
/**
 * 注文ダッシュボード
 * 
 * ログイン後のメイン画面
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
    <title>ホーム - Smiley配食</title>
    
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
            padding-bottom: 80px;
        }
        
        /* ヘッダー */
        .app-header {
            background: linear-gradient(135deg, var(--primary-green) 0%, #45a049 100%);
            color: white;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .user-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .user-name {
            font-size: 20px;
            font-weight: bold;
        }
        
        .company-name {
            font-size: 14px;
            opacity: 0.9;
            margin-top: 5px;
        }
        
        .logout-btn {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .logout-btn:hover {
            background: rgba(255,255,255,0.3);
        }
        
        /* コンテンツエリア */
        .content-wrapper {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        
        /* カードスタイル */
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            overflow: hidden;
        }
        
        .card-header {
            background: white;
            border-bottom: 2px solid #f0f0f0;
            padding: 15px 20px;
            font-weight: bold;
            font-size: 18px;
            display: flex;
            align-items: center;
        }
        
        .card-header .material-icons {
            margin-right: 10px;
            font-size: 24px;
        }
        
        .card-body {
            padding: 20px;
        }
        
        /* お知らせカード */
        .notice-card {
            background: linear-gradient(135deg, #FFF3E0 0%, #FFE0B2 100%);
            border-left: 4px solid var(--warning-orange);
        }
        
        .notice-item {
            padding: 10px 0;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        
        .notice-item:last-child {
            border-bottom: none;
        }
        
        .notice-icon {
            color: var(--warning-orange);
            vertical-align: middle;
            margin-right: 8px;
        }
        
        /* 明日の注文カード */
        .tomorrow-order {
            background: linear-gradient(135deg, #E3F2FD 0%, #BBDEFB 100%);
        }
        
        .no-order {
            text-align: center;
            padding: 30px 20px;
        }
        
        .no-order-icon {
            font-size: 60px;
            color: #90CAF9;
            margin-bottom: 15px;
        }
        
        .order-detail {
            background: white;
            padding: 15px;
            border-radius: 10px;
            margin-top: 15px;
        }
        
        .order-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .order-item:last-child {
            border-bottom: none;
        }
        
        /* クイック機能ボタン */
        .quick-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin: 20px 0;
        }
        
        .quick-btn {
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 15px;
            padding: 25px 15px;
            text-align: center;
            text-decoration: none;
            color: #333;
            transition: all 0.3s;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .quick-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
            border-color: var(--primary-green);
        }
        
        .quick-btn .material-icons {
            font-size: 40px;
            margin-bottom: 10px;
        }
        
        .quick-btn-label {
            font-size: 16px;
            font-weight: 600;
        }
        
        .btn-order { color: var(--primary-green); }
        .btn-history { color: var(--primary-blue); }
        
        /* 注文履歴 */
        .history-item {
            background: white;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .history-date {
            font-weight: bold;
            color: #666;
        }
        
        .history-menu {
            font-size: 14px;
            color: #888;
            margin-top: 5px;
        }
        
        .history-amount {
            font-size: 18px;
            font-weight: bold;
            color: var(--primary-green);
        }
        
        /* ボタン */
        .btn-primary-custom {
            background: linear-gradient(135deg, var(--primary-green) 0%, #45a049 100%);
            border: none;
            color: white;
            padding: 15px 30px;
            font-size: 18px;
            font-weight: bold;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(76, 175, 80, 0.4);
            transition: all 0.3s;
        }
        
        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(76, 175, 80, 0.6);
        }
        
        /* ローディング */
        .loading {
            text-align: center;
            padding: 40px 20px;
        }
        
        .spinner-border {
            width: 3rem;
            height: 3rem;
        }
        
        /* 空状態 */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #999;
        }
        
        .empty-state .material-icons {
            font-size: 60px;
            margin-bottom: 15px;
        }
        
        @media (max-width: 576px) {
            .content-wrapper {
                padding: 15px;
            }
            
            .quick-actions {
                gap: 10px;
            }
            
            .quick-btn {
                padding: 20px 10px;
            }
        }
    </style>
</head>
<body>
    <!-- ヘッダー -->
    <div class="app-header">
        <div class="user-info">
            <div>
                <div class="user-name">
                    <?php echo htmlspecialchars($user['user_name']); ?> さん
                </div>
                <div class="company-name">
                    <?php echo htmlspecialchars($user['company_name']); ?>
                    <?php if (!empty($user['department'])): ?>
                        / <?php echo htmlspecialchars($user['department']); ?>
                    <?php endif; ?>
                </div>
            </div>
            <button class="logout-btn" onclick="logout()">
                <span class="material-icons" style="vertical-align: middle; font-size: 18px;">logout</span>
                ログアウト
            </button>
        </div>
    </div>
    
    <!-- コンテンツ -->
    <div class="content-wrapper">
        <!-- お知らせ -->
        <div class="card notice-card">
            <div class="card-body">
                <div class="notice-item">
                    <span class="material-icons notice-icon">schedule</span>
                    <strong>締切時間:</strong> <span id="deadlineTime">翌朝6:00</span>まで
                </div>
                <div class="notice-item">
                    <span class="material-icons notice-icon">info</span>
                    <span id="systemMessage">ご注文は配達日の前日までにお願いします</span>
                </div>
            </div>
        </div>
        
        <!-- 明日の注文 -->
        <div class="card">
            <div class="card-header">
                <span class="material-icons">restaurant</span>
                明日の注文
            </div>
            <div class="card-body tomorrow-order">
                <div id="tomorrowOrderLoading" class="loading">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">読み込み中...</span>
                    </div>
                </div>
                
                <div id="tomorrowOrderContent" style="display: none;">
                    <!-- 注文済みの場合 -->
                    <div id="hasOrder" style="display: none;">
                        <div class="order-detail">
                            <div class="order-item">
                                <span><strong>配達日:</strong></span>
                                <span id="orderDate"></span>
                            </div>
                            <div class="order-item">
                                <span><strong>メニュー:</strong></span>
                                <span id="orderMenu"></span>
                            </div>
                            <div class="order-item">
                                <span><strong>数量:</strong></span>
                                <span id="orderQuantity"></span>
                            </div>
                            <div class="order-item">
                                <span><strong>金額:</strong></span>
                                <span id="orderAmount" style="color: var(--primary-green); font-weight: bold;"></span>
                            </div>
                        </div>
                        <div class="text-center mt-3">
                            <button class="btn btn-outline-secondary" onclick="cancelOrder()">
                                注文をキャンセル
                            </button>
                        </div>
                    </div>
                    
                    <!-- 未注文の場合 -->
                    <div id="noOrder" style="display: none;">
                        <div class="no-order">
                            <div class="material-icons no-order-icon">lunch_dining</div>
                            <p>まだ明日の注文がありません</p>
                            <button class="btn btn-primary-custom w-100" onclick="goToOrder()">
                                <span class="material-icons" style="vertical-align: middle;">add_circle</span>
                                注文する
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- クイック機能 -->
        <div class="quick-actions">
            <a href="create_order.php" class="quick-btn btn-order">
                <div class="material-icons">restaurant_menu</div>
                <div class="quick-btn-label">今すぐ注文</div>
            </a>
            <a href="order_history.php" class="quick-btn btn-history">
                <div class="material-icons">history</div>
                <div class="quick-btn-label">注文履歴</div>
            </a>
        </div>
        
        <!-- 今月の注文履歴 -->
        <div class="card">
            <div class="card-header">
                <span class="material-icons">calendar_today</span>
                今月の注文履歴
            </div>
            <div class="card-body">
                <div id="historyLoading" class="loading">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">読み込み中...</span>
                    </div>
                </div>
                
                <div id="historyContent" style="display: none;">
                    <div id="historyList"></div>
                    
                    <div id="emptyHistory" class="empty-state" style="display: none;">
                        <div class="material-icons">inbox</div>
                        <p>今月の注文履歴はありません</p>
                    </div>
                    
                    <div class="text-center mt-3">
                        <a href="order_history.php" class="btn btn-outline-primary">
                            すべての履歴を見る
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // ページ読み込み時の処理
        document.addEventListener('DOMContentLoaded', function() {
            loadDashboardData();
        });
        
        // ダッシュボードデータ読み込み
        async function loadDashboardData() {
            try {
                // 明日の注文を取得
                await loadTomorrowOrder();
                
                // 今月の注文履歴を取得
                await loadRecentHistory();
                
            } catch (error) {
                console.error('Error loading dashboard:', error);
            }
        }
        
        // 明日の注文を読み込み
        async function loadTomorrowOrder() {
            try {
                const response = await fetch('../api/order_dashboard.php?action=today_order');
                const result = await response.json();
                
                document.getElementById('tomorrowOrderLoading').style.display = 'none';
                document.getElementById('tomorrowOrderContent').style.display = 'block';
                
                if (result.success && result.data && result.data.has_order) {
                    // 注文あり
                    const order = result.data.order;
                    document.getElementById('orderDate').textContent = formatDate(order.delivery_date);
                    document.getElementById('orderMenu').textContent = order.product_name;
                    document.getElementById('orderQuantity').textContent = order.quantity + '個';
                    document.getElementById('orderAmount').textContent = '¥' + Number(order.total_amount).toLocaleString();
                    
                    document.getElementById('hasOrder').style.display = 'block';
                    document.getElementById('noOrder').style.display = 'none';
                } else {
                    // 注文なし
                    document.getElementById('hasOrder').style.display = 'none';
                    document.getElementById('noOrder').style.display = 'block';
                }
                
            } catch (error) {
                console.error('Error loading tomorrow order:', error);
                document.getElementById('tomorrowOrderLoading').style.display = 'none';
                document.getElementById('noOrder').style.display = 'block';
            }
        }
        
        // 今月の注文履歴を読み込み
        async function loadRecentHistory() {
            try {
                const response = await fetch('../api/order_dashboard.php?action=recent_history');
                const result = await response.json();
                
                document.getElementById('historyLoading').style.display = 'none';
                document.getElementById('historyContent').style.display = 'block';
                
                if (result.success && result.data && result.data.orders.length > 0) {
                    const historyList = document.getElementById('historyList');
                    historyList.innerHTML = '';
                    
                    result.data.orders.forEach(order => {
                        const item = document.createElement('div');
                        item.className = 'history-item';
                        item.innerHTML = `
                            <div>
                                <div class="history-date">${formatDate(order.delivery_date)}</div>
                                <div class="history-menu">${order.product_name} × ${order.quantity}</div>
                            </div>
                            <div class="history-amount">¥${Number(order.total_amount).toLocaleString()}</div>
                        `;
                        historyList.appendChild(item);
                    });
                    
                    document.getElementById('emptyHistory').style.display = 'none';
                } else {
                    document.getElementById('emptyHistory').style.display = 'block';
                }
                
            } catch (error) {
                console.error('Error loading history:', error);
                document.getElementById('historyLoading').style.display = 'none';
                document.getElementById('emptyHistory').style.display = 'block';
            }
        }
        
        // 日付フォーマット
        function formatDate(dateString) {
            const date = new Date(dateString);
            const month = date.getMonth() + 1;
            const day = date.getDate();
            const weekdays = ['日', '月', '火', '水', '木', '金', '土'];
            const weekday = weekdays[date.getDay()];
            
            return `${month}月${day}日(${weekday})`;
        }
        
        // 注文画面へ
        function goToOrder() {
            window.location.href = 'create_order.php';
        }
        
        // 注文キャンセル
        async function cancelOrder() {
            if (!confirm('明日の注文をキャンセルしますか？')) {
                return;
            }
            
            // TODO: キャンセル処理実装
            alert('注文キャンセル機能は実装中です');
        }
        
        // ログアウト
        async function logout() {
            if (!confirm('ログアウトしますか？')) {
                return;
            }
            
            try {
                const response = await fetch('../api/auth.php?action=logout');
                const result = await response.json();
                
                if (result.success) {
                    window.location.href = 'login.php';
                }
            } catch (error) {
                console.error('Logout error:', error);
                window.location.href = 'login.php';
            }
        }
    </script>
</body>
</html>
