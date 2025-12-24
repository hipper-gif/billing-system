<?php
/**
 * 週替わりメニューAPI（管理者用）
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/AuthManager.php';

$authManager = new AuthManager();

if (!$authManager->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => '認証が必要です']);
    exit;
}

if (!$authManager->isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => '管理者権限が必要です']);
    exit;
}

$db = Database::getInstance();
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'list':
            handleList($db);
            break;
            
        case 'set':
            handleSet($db);
            break;
            
        case 'remove':
            handleRemove($db);
            break;
            
        case 'get_current':
            handleGetCurrent($db);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => '無効なアクションです']);
    }
    
} catch (Exception $e) {
    error_log("Weekly Menu API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'サーバーエラーが発生しました']);
}

/**
 * 週替わりメニュー一覧取得
 */
function handleList($db) {
    $sql = "SELECT 
                wm.id,
                wm.week_start_date,
                wm.product_id,
                p.product_code,
                p.product_name,
                p.unit_price,
                wm.is_available,
                wm.special_note
            FROM weekly_menus wm
            INNER JOIN products p ON wm.product_id = p.id
            WHERE wm.is_available = 1
            ORDER BY wm.week_start_date DESC
            LIMIT 20";
    
    $result = $db->fetchAll($sql);
    
    echo json_encode([
        'success' => true,
        'data' => $result
    ]);
}

/**
 * 週替わりメニュー設定
 */
function handleSet($db) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => '無効なリクエストメソッドです']);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['week_start_date']) || empty($input['product_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => '必須項目が不足しています']);
        return;
    }
    
    // 週の開始日（月曜日）であることを確認
    $date = new DateTime($input['week_start_date']);
    if ($date->format('w') != 1) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => '週の開始日は月曜日である必要があります']);
        return;
    }
    
    // 既存レコード確認
    $checkSql = "SELECT id FROM weekly_menus WHERE week_start_date = :week_start_date";
    $existing = $db->fetch($checkSql, ['week_start_date' => $input['week_start_date']]);
    
    if ($existing) {
        // 更新
        $sql = "UPDATE weekly_menus 
                SET product_id = :product_id,
                    special_note = :special_note,
                    is_available = 1,
                    updated_at = NOW()
                WHERE week_start_date = :week_start_date";
    } else {
        // 挿入
        $sql = "INSERT INTO weekly_menus 
                (week_start_date, product_id, special_note, is_available, created_at, updated_at)
                VALUES (:week_start_date, :product_id, :special_note, 1, NOW(), NOW())";
    }
    
    $db->query($sql, [
        'week_start_date' => $input['week_start_date'],
        'product_id' => $input['product_id'],
        'special_note' => $input['special_note'] ?? null
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => '週替わりメニューを設定しました'
    ]);
}

/**
 * 週替わりメニュー削除
 */
function handleRemove($db) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => '無効なリクエストメソッドです']);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['week_start_date'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => '日付を指定してください']);
        return;
    }
    
    $sql = "DELETE FROM weekly_menus WHERE week_start_date = :week_start_date";
    $db->query($sql, ['week_start_date' => $input['week_start_date']]);
    
    echo json_encode([
        'success' => true,
        'message' => '週替わりメニューを削除しました'
    ]);
}

/**
 * 現在の週のメニューを取得
 */
function handleGetCurrent($db) {
    // 今週の月曜日を取得
    $today = new DateTime();
    $dayOfWeek = $today->format('w');
    $daysToMonday = ($dayOfWeek == 0) ? -6 : -(($dayOfWeek - 1));
    $monday = clone $today;
    $monday->modify("{$daysToMonday} days");
    
    $weekStartDate = $monday->format('Y-m-d');
    
    $sql = "SELECT 
                wm.id,
                wm.week_start_date,
                wm.product_id,
                p.product_code,
                p.product_name,
                p.unit_price,
                p.category_name,
                wm.special_note
            FROM weekly_menus wm
            INNER JOIN products p ON wm.product_id = p.id
            WHERE wm.week_start_date = :week_start_date
              AND wm.is_available = 1
            LIMIT 1";
    
    $result = $db->fetch($sql, ['week_start_date' => $weekStartDate]);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'data' => $result
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => '今週のメニューが設定されていません'
        ]);
    }
}
