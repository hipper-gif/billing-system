<?php
/**
 * 注文ダッシュボードAPI
 * 
 * ダッシュボード画面に表示するデータを提供
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/AuthManager.php';

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
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'summary':
            handleSummary($user);
            break;
            
        case 'today_order':
            handleTodayOrder($user);
            break;
            
        case 'recent_history':
            handleRecentHistory($user);
            break;
            
        default:
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => '無効なアクションです'
            ]);
    }
    
} catch (Exception $e) {
    error_log("Dashboard API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'サーバーエラーが発生しました'
    ]);
}

/**
 * サマリー情報取得
 * 
 * @param array $user ユーザー情報
 */
function handleSummary($user) {
    $db = Database::getInstance();
    
    // 今月の注文件数
    $sql = "SELECT COUNT(*) as order_count, 
                   COALESCE(SUM(total_amount), 0) as total_amount
            FROM orders 
            WHERE user_id = :user_id 
              AND MONTH(delivery_date) = MONTH(CURDATE())
              AND YEAR(delivery_date) = YEAR(CURDATE())
              AND order_status != 'cancelled'";
    
    $summary = $db->fetch($sql, ['user_id' => $user['user_id']]);
    
    // 締切時間取得
    $deadlineSql = "SELECT deadline_time 
                    FROM order_deadlines 
                    WHERE (company_id = :company_id OR company_id IS NULL)
                      AND is_active = 1
                    ORDER BY company_id DESC
                    LIMIT 1";
    
    $deadline = $db->fetch($deadlineSql, ['company_id' => $user['company_id']]);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'order_count' => (int)$summary['order_count'],
            'total_amount' => (float)$summary['total_amount'],
            'deadline_time' => $deadline ? $deadline['deadline_time'] : '06:00:00'
        ]
    ]);
}

/**
 * 明日の注文取得
 * 
 * @param array $user ユーザー情報
 */
function handleTodayOrder($user) {
    $db = Database::getInstance();
    
    // 明日の日付
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    
    // 明日の注文を取得
    $sql = "SELECT 
                id,
                delivery_date,
                product_id,
                product_code,
                product_name,
                quantity,
                unit_price,
                total_amount,
                order_status
            FROM orders 
            WHERE user_id = :user_id 
              AND delivery_date = :delivery_date
              AND order_status != 'cancelled'
            LIMIT 1";
    
    $order = $db->fetch($sql, [
        'user_id' => $user['user_id'],
        'delivery_date' => $tomorrow
    ]);
    
    if ($order) {
        echo json_encode([
            'success' => true,
            'data' => [
                'has_order' => true,
                'order' => $order
            ]
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'data' => [
                'has_order' => false
            ]
        ]);
    }
}

/**
 * 最近の注文履歴取得
 * 
 * @param array $user ユーザー情報
 */
function handleRecentHistory($user) {
    $db = Database::getInstance();
    
    // 今月の注文履歴（最新10件）
    $sql = "SELECT 
                id,
                delivery_date,
                product_id,
                product_code,
                product_name,
                quantity,
                unit_price,
                total_amount,
                order_status,
                created_at
            FROM orders 
            WHERE user_id = :user_id 
              AND MONTH(delivery_date) = MONTH(CURDATE())
              AND YEAR(delivery_date) = YEAR(CURDATE())
              AND order_status != 'cancelled'
            ORDER BY delivery_date DESC, created_at DESC
            LIMIT 10";
    
    $orders = $db->fetchAll($sql, ['user_id' => $user['user_id']]);
    
    // 合計金額計算
    $totalAmount = 0;
    foreach ($orders as $order) {
        $totalAmount += $order['total_amount'];
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'orders' => $orders,
            'total_count' => count($orders),
            'total_amount' => $totalAmount
        ]
    ]);
}
