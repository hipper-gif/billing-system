<?php
/**
 * 注文管理API
 * 
 * 注文の作成・変更・キャンセル・照会を処理
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/AuthManager.php';
require_once __DIR__ . '/../classes/OrderManager.php';
require_once __DIR__ . '/../classes/SecurityHelper.php';

// 認証チェック
$authManager = new AuthManager();

if (!$authManager->isLoggedIn()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => '認証が必要です'
    ]);
    exit;
}

$user = $authManager->getCurrentUser();
$orderManager = new OrderManager();
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'available_dates':
            handleAvailableDates($orderManager, $user);
            break;
            
        case 'menus':
            handleMenus($orderManager);
            break;
            
        case 'check_deadline':
            handleCheckDeadline($orderManager, $user);
            break;
            
        case 'create_order':
            handleCreateOrder($orderManager, $user);
            break;
            
        case 'update_order':
            handleUpdateOrder($orderManager, $user);
            break;
            
        case 'cancel_order':
            handleCancelOrder($orderManager, $user);
            break;
            
        case 'order_history':
            handleOrderHistory($orderManager, $user);
            break;
            
        case 'order_detail':
            handleOrderDetail($orderManager, $user);
            break;
            
        default:
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => '無効なアクションです'
            ]);
    }
    
} catch (Exception $e) {
    error_log("Orders API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'サーバーエラーが発生しました'
    ]);
}

/**
 * 注文可能日を取得
 */
function handleAvailableDates($orderManager, $user) {
    $days = isset($_GET['days']) ? (int)$_GET['days'] : 7;
    
    $dates = $orderManager->getAvailableDates($user['company_id'], $days);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'dates' => $dates,
            'deadline_time' => $orderManager->getDeadlineTime($user['company_id'])
        ]
    ]);
}

/**
 * 指定日のメニューを取得
 */
function handleMenus($orderManager) {
    $date = $_GET['date'] ?? '';
    
    if (empty($date)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => '日付を指定してください'
        ]);
        return;
    }
    
    $menus = $orderManager->getMenusForDate($date);
    
    echo json_encode([
        'success' => true,
        'data' => $menus
    ]);
}

/**
 * 締切時間チェック
 */
function handleCheckDeadline($orderManager, $user) {
    $date = $_GET['date'] ?? '';
    
    if (empty($date)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => '日付を指定してください'
        ]);
        return;
    }
    
    $deadline = $orderManager->getDeadlineTime($user['company_id']);
    $deadlineDateTime = new DateTime($date . ' ' . $deadline);
    $deadlineDateTime->modify('-1 day');
    
    $now = new DateTime();
    $isBeforeDeadline = $now < $deadlineDateTime;
    
    echo json_encode([
        'success' => true,
        'data' => [
            'is_before_deadline' => $isBeforeDeadline,
            'deadline' => $deadlineDateTime->format('Y-m-d H:i:s'),
            'deadline_time' => $deadline
        ]
    ]);
}

/**
 * 注文を作成
 */
function handleCreateOrder($orderManager, $user) {
    // POSTリクエストのみ受付
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'error' => '無効なリクエストメソッドです'
        ]);
        return;
    }
    
    // リクエストデータ取得
    $input = json_decode(file_get_contents('php://input'), true);
    
    // 注文データ準備
    $orderData = [
        'delivery_date' => SecurityHelper::sanitizeInput($input['delivery_date'] ?? ''),
        'product_id' => (int)($input['product_id'] ?? 0),
        'quantity' => (int)($input['quantity'] ?? 1),
        'user_id' => $user['user_id'],
        'user_code' => $user['user_code'],
        'user_name' => $user['user_name'],
        'company_id' => $user['company_id'],
        'company_code' => $user['company_code'] ?? null,
        'company_name' => $user['company_name'],
        'department_code' => $user['department_code'] ?? null,
        'department' => $user['department'] ?? null,
        'notes' => SecurityHelper::sanitizeInput($input['notes'] ?? '')
    ];
    
    // 注文作成
    $result = $orderManager->createOrder($orderData);
    
    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'data' => [
                'order_id' => $result['order_id']
            ],
            'message' => $result['message']
        ]);
    } else {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $result['error']
        ]);
    }
}

/**
 * 注文を更新
 */
function handleUpdateOrder($orderManager, $user) {
    // POSTリクエストのみ受付
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'error' => '無効なリクエストメソッドです'
        ]);
        return;
    }
    
    // リクエストデータ取得
    $input = json_decode(file_get_contents('php://input'), true);
    
    $orderId = (int)($input['order_id'] ?? 0);
    
    if (empty($orderId)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => '注文IDを指定してください'
        ]);
        return;
    }
    
    // 更新データ準備
    $updateData = [];
    
    if (isset($input['quantity'])) {
        $updateData['quantity'] = (int)$input['quantity'];
    }
    
    if (isset($input['notes'])) {
        $updateData['notes'] = SecurityHelper::sanitizeInput($input['notes']);
    }
    
    // 注文更新
    $result = $orderManager->updateOrder($orderId, $updateData);
    
    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'message' => $result['message']
        ]);
    } else {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $result['error']
        ]);
    }
}

/**
 * 注文をキャンセル
 */
function handleCancelOrder($orderManager, $user) {
    // POSTリクエストのみ受付
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'error' => '無効なリクエストメソッドです'
        ]);
        return;
    }
    
    // リクエストデータ取得
    $input = json_decode(file_get_contents('php://input'), true);
    
    $orderId = (int)($input['order_id'] ?? 0);
    
    if (empty($orderId)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => '注文IDを指定してください'
        ]);
        return;
    }
    
    // 注文キャンセル
    $result = $orderManager->cancelOrder($orderId, $user['user_id']);
    
    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'message' => $result['message']
        ]);
    } else {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $result['error']
        ]);
    }
}

/**
 * 注文履歴を取得
 */
function handleOrderHistory($orderManager, $user) {
    // フィルター条件取得
    $filters = [];
    
    if (!empty($_GET['status'])) {
        $filters['status'] = $_GET['status'];
    }
    
    if (!empty($_GET['date_from'])) {
        $filters['date_from'] = $_GET['date_from'];
    }
    
    if (!empty($_GET['date_to'])) {
        $filters['date_to'] = $_GET['date_to'];
    }
    
    // 注文履歴取得
    $orders = $orderManager->getOrderHistory($user['user_id'], $filters);
    
    // 合計金額計算
    $totalAmount = 0;
    $totalPayment = 0;
    
    foreach ($orders as $order) {
        $totalAmount += $order['total_amount'];
        $totalPayment += $order['user_payment_amount'];
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'orders' => $orders,
            'total_count' => count($orders),
            'total_amount' => $totalAmount,
            'total_payment' => $totalPayment
        ]
    ]);
}

/**
 * 注文詳細を取得
 */
function handleOrderDetail($orderManager, $user) {
    $orderId = (int)($_GET['order_id'] ?? 0);
    
    if (empty($orderId)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => '注文IDを指定してください'
        ]);
        return;
    }
    
    // 注文詳細取得
    $order = $orderManager->getOrderById($orderId);
    
    if (!$order) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => '注文が見つかりません'
        ]);
        return;
    }
    
    // 本人確認
    if ($order['user_id'] != $user['user_id']) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'アクセス権限がありません'
        ]);
        return;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $order
    ]);
}
