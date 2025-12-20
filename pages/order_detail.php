<?php
/**
 * 注文詳細画面
 * 
 * 注文の詳細を表示・変更・キャンセル
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
$orderId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (empty($orderId)) {
    header('Location: order_history.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>注文詳細 - Smiley配食</title>
    
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
            padding-bottom: 100px;
        }
        
        /* ヘッダー */
        .app-header {
            background: linear-gradient(135deg, var(--primary-green) 0%, #45a049 100%);
            color: white;
            padding: 15px 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 600px;
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
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        
        /* カード */
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .card-header {
            background: white;
            border-bottom: 2px solid #f0f0f0;
            padding: 15px 20px;
            font-weight: bold;
            font-size: 18px;
            border-radius: 15px 15px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .status-badge {
            padding: 6px 15px;
            border-radius: 15px;
            font-size: 13px;
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
        
        /* 詳細情報 */
        .detail-row {
            padding: 15px 20px;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .detail-row:last-child {
            border-bottom: none;
        }
        
        .detail-label {
            color: #666;
            font-size: 14px;
        }
        
        .detail-value {
            font-weight: 600;
            font-size: 16px;
            text-align: right;
        }
        
        .detail-value.large {
            font-size: 24px;
            color: var(--primary-green);
        }
        
        /* アクションボタン */
        .action-buttons {
            display: flex;
            gap: 10px;
            padding: 20px;
        }
        
        .btn-cancel {
            flex: 1;
            background: #f0f0f0;
            border: 2px solid #e0e0e0;
            color: #666;
            padding: 15px;
            font-size: 16px;
            font-weight: bold;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-cancel:hover:not(:disabled) {
            background: #FFF3E0;
            border-color: var(--warning-orange);
            color: var(--warning-orange);
        }
        
        .btn-cancel:disabled {
            opacity: 0.5;
            cursor: not-allowed;
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
        
        /* エラー */
        .error-state {
            text-align: center;
            padding: 60px 20px;
        }
        
        .error-icon {
            font-size: 80px;
            color: #ccc;
            margin-bottom: 20px;
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
            <div class="header-title">注文詳細</div>
            <div style="width: 40px;"></div>
        </div>
    </div>
    
    <!-- コンテンツ -->
    <div class="content-wrapper">
        <!-- ローディング -->
        <div id="loading" class="loading">
            <div class="spinner-border text-primary"></div>
            <p class="mt-3">読み込み中...</p>
        </div>
        
        <!-- 注文詳細 -->
        <div id="orderDetail" style="display: none;">
            <!-- ステータスカード -->
            <div class="card">
                <div class="card-header">
                    <span>注文情報</span>
                    <span class="status-badge" id="statusBadge"></span>
                </div>
                <div class="card-body p-0">
                    <div class="detail-row">
                        <span class="detail-label">注文番号</span>
                        <span class="detail-value" id="orderId"></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">注文日</span>
                        <span class="detail-value" id="orderDate"></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">配達日</span>
                        <span class="detail-value" id="deliveryDate"></span>
                    </div>
                </div>
            </div>
            
            <!-- メニュー情報 -->
            <div class="card">
                <div class="card-header">メニュー</div>
                <div class="card-body p-0">
                    <div class="detail-row">
                        <span class="detail-label">商品名</span>
                        <span class="detail-value" id="productName"></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">カテゴリ</span>
                        <span class="detail-value" id="categoryName"></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">数量</span>
                        <span class="detail-value" id="quantity"></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">単価</span>
                        <span class="detail-value" id="unitPrice"></span>
                    </div>
                </div>
            </div>
            
            <!-- 金額情報 -->
            <div class="card">
                <div class="card-header">お支払い</div>
                <div class="card-body p-0">
                    <div class="detail-row">
                        <span class="detail-label">小計</span>
                        <span class="detail-value" id="totalAmount"></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">企業補助</span>
                        <span class="detail-value" id="subsidyAmount"></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">お支払い金額</span>
                        <span class="detail-value large" id="paymentAmount"></span>
                    </div>
                </div>
            </div>
            
            <!-- 備考 -->
            <div class="card" id="notesCard" style="display: none;">
                <div class="card-header">備考</div>
                <div class="card-body">
                    <p id="notes" style="margin: 0;"></p>
                </div>
            </div>
            
            <!-- アクションボタン -->
            <div class="card">
                <div class="action-buttons">
                    <button class="btn-cancel" id="cancelBtn" onclick="cancelOrder()">
                        <span class="material-icons" style="vertical-align: middle;">cancel</span>
                        注文をキャンセル
                    </button>
                </div>
            </div>
        </div>
        
        <!-- エラー状態 -->
        <div id="errorState" class="error-state" style="display: none;">
            <div class="material-icons error-icon">error_outline</div>
            <p>注文情報の読み込みに失敗しました</p>
            <button class="btn btn-primary mt-3" onclick="loadOrderDetail()">再読み込み</button>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        const orderId = <?php echo $orderId; ?>;
        let orderData = null;
        
        // ページ読み込み時の処理
        document.addEventListener('DOMContentLoaded', function() {
            loadOrderDetail();
        });
        
        // 注文詳細を読み込み
        async function loadOrderDetail() {
            document.getElementById('loading').style.display = 'block';
            document.getElementById('orderDetail').style.display = 'none';
            document.getElementById('errorState').style.display = 'none';
            
            try {
                const response = await fetch(`../api/orders_management.php?action=order_detail&order_id=${orderId}`);
                const result = await response.json();
                
                if (result.success) {
                    orderData = result.data;
                    renderOrderDetail();
                } else {
                    showError();
                }
            } catch (error) {
                console.error('Error loading order detail:', error);
                showError();
            }
        }
        
        // 注文詳細を表示
        function renderOrderDetail() {
            // ステータス
            const statusText = getStatusText(orderData.order_status);
            const statusClass = 'status-' + orderData.order_status;
            document.getElementById('statusBadge').textContent = statusText;
            document.getElementById('statusBadge').className = 'status-badge ' + statusClass;
            
            // 注文情報
            document.getElementById('orderId').textContent = '#' + orderData.id;
            document.getElementById('orderDate').textContent = formatDate(orderData.order_date);
            document.getElementById('deliveryDate').textContent = formatDate(orderData.delivery_date);
            
            // メニュー情報
            document.getElementById('productName').textContent = orderData.product_name;
            document.getElementById('categoryName').textContent = orderData.category_name;
            document.getElementById('quantity').textContent = orderData.quantity + '個';
            document.getElementById('unitPrice').textContent = '¥' + Number(orderData.unit_price).toLocaleString();
            
            // 金額情報
            document.getElementById('totalAmount').textContent = '¥' + Number(orderData.total_amount).toLocaleString();
            document.getElementById('subsidyAmount').textContent = '¥' + Number(orderData.subsidy_amount || 0).toLocaleString();
            document.getElementById('paymentAmount').textContent = '¥' + Number(orderData.user_payment_amount || orderData.total_amount).toLocaleString();
            
            // 備考
            if (orderData.notes) {
                document.getElementById('notes').textContent = orderData.notes;
                document.getElementById('notesCard').style.display = 'block';
            }
            
            // キャンセルボタンの状態
            const cancelBtn = document.getElementById('cancelBtn');
            if (orderData.order_status === 'cancelled') {
                cancelBtn.disabled = true;
                cancelBtn.textContent = 'キャンセル済み';
            }
            
            // 表示切り替え
            document.getElementById('loading').style.display = 'none';
            document.getElementById('orderDetail').style.display = 'block';
        }
        
        // エラー表示
        function showError() {
            document.getElementById('loading').style.display = 'none';
            document.getElementById('errorState').style.display = 'block';
        }
        
        // 注文をキャンセル
        async function cancelOrder() {
            if (!confirm('この注文をキャンセルしますか？\n\n注文締切時間を過ぎている場合はキャンセルできません。')) {
                return;
            }
            
            const cancelBtn = document.getElementById('cancelBtn');
            cancelBtn.disabled = true;
            cancelBtn.textContent = 'キャンセル中...';
            
            try {
                const response = await fetch('../api/orders_management.php?action=cancel_order', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        order_id: orderId
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('注文をキャンセルしました');
                    window.location.href = 'order_dashboard.php';
                } else {
                    alert('エラー: ' + result.error);
                    cancelBtn.disabled = false;
                    cancelBtn.textContent = '注文をキャンセル';
                }
                
            } catch (error) {
                console.error('Cancel error:', error);
                alert('キャンセル処理に失敗しました');
                cancelBtn.disabled = false;
                cancelBtn.textContent = '注文をキャンセル';
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
        
        // 戻る
        function goBack() {
            window.location.href = 'order_history.php';
        }
    </script>
</body>
</html>
