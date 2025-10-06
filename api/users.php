<?php
/**
 * 利用者管理API
 * 完全CRUD対応
 * 
 * @version 2.0.0 - v5.0仕様準拠版
 * @updated 2025-10-06
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// v5.0仕様: config/database.php から Database クラスを読み込む
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/SecurityHelper.php';

SecurityHelper::setJsonHeaders();

$method = $_SERVER['REQUEST_METHOD'];

try {
    $db = Database::getInstance();
    
    switch ($method) {
        case 'GET':
            handleGet($db);
            break;
        case 'POST':
            handlePost($db);
            break;
        case 'PUT':
            handlePut($db);
            break;
        case 'DELETE':
            handleDelete($db);
            break;
        default:
            throw new Exception('サポートされていないHTTPメソッドです');
    }
    
} catch (Exception $e) {
    error_log("Users API Error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * GET: 利用者一覧・詳細取得
 */
function handleGet($db) {
    $id = $_GET['id'] ?? null;
    
    if ($id) {
        getUserDetail($db, $id);
    } else {
        getUsersList($db);
    }
}

/**
 * 利用者一覧取得
 */
function getUsersList($db) {
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? min(100, max(1, intval($_GET['limit']))) : 20;
    $search = $_GET['search'] ?? '';
    $companyId = $_GET['company_id'] ?? null;
    $departmentId = $_GET['department_id'] ?? null;
    $isActive = isset($_GET['is_active']) ? intval($_GET['is_active']) : null;
    
    $offset = ($page - 1) * $limit;
    
    $sql = "SELECT 
                u.id,
                u.user_code,
                u.user_name,
                u.company_id,
                u.department_id,
                c.company_name,
                d.department_name,
                u.employee_type_code,
                u.employee_type_name,
                u.email,
                u.phone,
                u.payment_method,
                u.is_active,
                u.created_at,
                u.updated_at
            FROM users u
            LEFT JOIN companies c ON u.company_id = c.id
            LEFT JOIN departments d ON u.department_id = d.id
            WHERE 1=1";
    
    $params = [];
    
    if (!empty($search)) {
        $sql .= " AND (
            u.user_name LIKE ? OR 
            u.user_code LIKE ? OR 
            c.company_name LIKE ?
        )";
        $searchParam = "%{$search}%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    if ($companyId) {
        $sql .= " AND u.company_id = ?";
        $params[] = $companyId;
    }
    
    if ($departmentId) {
        $sql .= " AND u.department_id = ?";
        $params[] = $departmentId;
    }
    
    if ($isActive !== null) {
        $sql .= " AND u.is_active = ?";
        $params[] = $isActive;
    }
    
    $sql .= " ORDER BY u.user_name ASC";
    
    // 総件数
    $countSql = "SELECT COUNT(*) as total FROM users u 
                 LEFT JOIN companies c ON u.company_id = c.id 
                 LEFT JOIN departments d ON u.department_id = d.id 
                 WHERE 1=1";
    
    if (!empty($search)) {
        $countSql .= " AND (u.user_name LIKE ? OR u.user_code LIKE ? OR c.company_name LIKE ?)";
    }
    if ($companyId) {
        $countSql .= " AND u.company_id = ?";
    }
    if ($departmentId) {
        $countSql .= " AND u.department_id = ?";
    }
    if ($isActive !== null) {
        $countSql .= " AND u.is_active = ?";
    }
    
    $totalResult = $db->fetch($countSql, $params);
    $total = $totalResult['total'] ?? 0;
    
    // ページング
    $sql .= " LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    $users = $db->fetchAll($sql, $params);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'users' => $users,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($total / $limit)
        ]
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * 利用者詳細取得
 */
function getUserDetail($db, $id) {
    $sql = "SELECT u.*, c.company_name, d.department_name
            FROM users u
            LEFT JOIN companies c ON u.company_id = c.id
            LEFT JOIN departments d ON u.department_id = d.id
            WHERE u.id = ?";
    
    $user = $db->fetch($sql, [$id]);
    
    if (!$user) {
        throw new Exception('利用者が見つかりません');
    }
    
    echo json_encode([
        'success' => true,
        'data' => $user
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * POST: 利用者登録
 */
function handlePost($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('不正なJSONデータです');
    }
    
    $required = ['user_code', 'user_name'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            throw new Exception("{$field}は必須項目です");
        }
    }
    
    // コード重複チェック
    $checkSql = "SELECT COUNT(*) as count FROM users WHERE user_code = ?";
    $checkResult = $db->fetch($checkSql, [$input['user_code']]);
    if ($checkResult['count'] > 0) {
        throw new Exception('この利用者コードは既に使用されています');
    }
    
    $sql = "INSERT INTO users (
        user_code, user_name, company_id, department_id,
        employee_type_code, employee_type_name,
        email, phone, payment_method, is_active,
        created_at, updated_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
    
    $params = [
        $input['user_code'],
        $input['user_name'],
        $input['company_id'] ?? null,
        $input['department_id'] ?? null,
        $input['employee_type_code'] ?? null,
        $input['employee_type_name'] ?? null,
        $input['email'] ?? null,
        $input['phone'] ?? null,
        $input['payment_method'] ?? 'cash',
        isset($input['is_active']) ? $input['is_active'] : 1
    ];
    
    $db->execute($sql, $params);
    $newId = $db->lastInsertId();
    
    $newUser = $db->fetch("SELECT * FROM users WHERE id = ?", [$newId]);
    
    echo json_encode([
        'success' => true,
        'message' => '利用者を登録しました',
        'data' => $newUser
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * PUT: 利用者更新
 */
function handlePut($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('不正なJSONデータです');
    }
    
    if (empty($input['id'])) {
        throw new Exception('IDは必須項目です');
    }
    
    $id = $input['id'];
    
    $existing = $db->fetch("SELECT * FROM users WHERE id = ?", [$id]);
    if (!$existing) {
        throw new Exception('利用者が見つかりません');
    }
    
    $updateFields = [];
    $params = [];
    
    $allowedFields = [
        'user_code', 'user_name', 'company_id', 'department_id',
        'employee_type_code', 'employee_type_name',
        'email', 'phone', 'payment_method', 'is_active'
    ];
    
    foreach ($allowedFields as $field) {
        if (isset($input[$field])) {
            $updateFields[] = "{$field} = ?";
            $params[] = $input[$field];
        }
    }
    
    if (empty($updateFields)) {
        throw new Exception('更新するデータがありません');
    }
    
    // コード重複チェック
    if (isset($input['user_code'])) {
        $checkSql = "SELECT COUNT(*) as count FROM users WHERE user_code = ? AND id != ?";
        $checkResult = $db->fetch($checkSql, [$input['user_code'], $id]);
        if ($checkResult['count'] > 0) {
            throw new Exception('この利用者コードは既に使用されています');
        }
    }
    
    $updateFields[] = "updated_at = NOW()";
    $params[] = $id;
    
    $sql = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = ?";
    $db->execute($sql, $params);
    
    $updatedUser = $db->fetch("SELECT * FROM users WHERE id = ?", [$id]);
    
    echo json_encode([
        'success' => true,
        'message' => '利用者を更新しました',
        'data' => $updatedUser
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * DELETE: 利用者削除（論理削除）
 */
function handleDelete($db) {
    $id = $_GET['id'] ?? null;
    
    if (!$id) {
        throw new Exception('IDが指定されていません');
    }
    
    $existing = $db->fetch("SELECT * FROM users WHERE id = ?", [$id]);
    if (!$existing) {
        throw new Exception('利用者が見つかりません');
    }
    
    $sql = "UPDATE users SET is_active = 0, updated_at = NOW() WHERE id = ?";
    $db->execute($sql, [$id]);
    
    echo json_encode([
        'success' => true,
        'message' => '利用者を削除しました'
    ], JSON_UNESCAPED_UNICODE);
}
