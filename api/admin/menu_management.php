<?php
/**
 * メニュー管理API（管理者用）
 * 
 * 商品マスタの管理API
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/AuthManager.php';

// 認証チェック
$authManager = new AuthManager();

if (!$authManager->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => '認証が必要です']);
    exit;
}

// 管理者権限チェック
if (!$authManager->isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => '管理者権限が必要です']);
    exit;
}

$db = Database::getInstance();
$action = $_GET['action'] ?? 'list';

try {
    switch ($action) {
        case 'list':
            handleList($db);
            break;
            
        case 'create':
            handleCreate($db);
            break;
            
        case 'update':
            handleUpdate($db);
            break;
            
        case 'delete':
            handleDelete($db);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => '無効なアクションです']);
    }
    
} catch (Exception $e) {
    error_log("Menu Management API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'サーバーエラーが発生しました']);
}

/**
 * メニュー一覧取得
 */
function handleList($db) {
    $sql = "SELECT * FROM products ORDER BY category_code, product_code";
    $menus = $db->fetchAll($sql);
    
    echo json_encode([
        'success' => true,
        'data' => $menus
    ]);
}

/**
 * メニュー作成
 */
function handleCreate($db) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => '無効なリクエストメソッドです']);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    // バリデーション
    $required = ['product_code', 'product_name', 'category_code', 'unit_price'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "{$field}は必須です"]);
            return;
        }
    }
    
    // 重複チェック
    $checkSql = "SELECT COUNT(*) as count FROM products WHERE product_code = :product_code";
    $result = $db->fetch($checkSql, ['product_code' => $input['product_code']]);
    
    if ($result['count'] > 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'この商品コードは既に使用されています']);
        return;
    }
    
    // 挿入
    $sql = "INSERT INTO products (
                product_code, product_name, category_code, category_name, 
                unit_price, is_active, created_at, updated_at
            ) VALUES (
                :product_code, :product_name, :category_code, :category_name,
                :unit_price, 1, NOW(), NOW()
            )";
    
    $db->query($sql, [
        'product_code' => $input['product_code'],
        'product_name' => $input['product_name'],
        'category_code' => $input['category_code'],
        'category_name' => $input['category_name'] ?? ($input['category_code'] === 'DAILY' ? '日替わり' : '定番'),
        'unit_price' => $input['unit_price']
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'メニューを追加しました'
    ]);
}

/**
 * メニュー更新
 */
function handleUpdate($db) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => '無効なリクエストメソッドです']);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'IDは必須です']);
        return;
    }
    
    // 更新
    $sql = "UPDATE products SET
                product_code = :product_code,
                product_name = :product_name,
                category_code = :category_code,
                category_name = :category_name,
                unit_price = :unit_price,
                is_active = :is_active,
                updated_at = NOW()
            WHERE id = :id";
    
    $db->query($sql, [
        'id' => $input['id'],
        'product_code' => $input['product_code'],
        'product_name' => $input['product_name'],
        'category_code' => $input['category_code'],
        'category_name' => $input['category_name'] ?? ($input['category_code'] === 'DAILY' ? '日替わり' : '定番'),
        'unit_price' => $input['unit_price'],
        'is_active' => $input['is_active'] ?? 1
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'メニューを更新しました'
    ]);
}

/**
 * メニュー削除
 */
function handleDelete($db) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => '無効なリクエストメソッドです']);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'IDは必須です']);
        return;
    }
    
    // 論理削除
    $sql = "UPDATE products SET is_active = 0, updated_at = NOW() WHERE id = :id";
    $db->query($sql, ['id' => $input['id']]);
    
    echo json_encode([
        'success' => true,
        'message' => 'メニューを削除しました'
    ]);
}
