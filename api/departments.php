<?php
/**
 * 部署管理API
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
    error_log("Departments API Error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    // 詳細なエラー情報を返す（開発時のみ）
    $errorResponse = [
        'success' => false,
        'message' => $e->getMessage(),
        'error_type' => get_class($e),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ];
    
    // 開発環境ではスタックトレースも返す
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        $errorResponse['trace'] = $e->getTraceAsString();
    }
    
    echo json_encode($errorResponse, JSON_UNESCAPED_UNICODE);
}

/**
 * GET: 部署一覧・詳細取得
 */
function handleGet($db) {
    $id = $_GET['id'] ?? null;
    
    if ($id) {
        getDepartmentDetail($db, $id);
    } else {
        getDepartmentsList($db);
    }
}

/**
 * 部署一覧取得
 */
function getDepartmentsList($db) {
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? min(100, max(1, intval($_GET['limit']))) : 20;
    $search = $_GET['search'] ?? '';
    $companyId = $_GET['company_id'] ?? null;
    $isActive = isset($_GET['is_active']) ? intval($_GET['is_active']) : null;
    
    $offset = ($page - 1) * $limit;
    
    $sql = "SELECT 
                d.id,
                d.department_code,
                d.department_name,
                d.company_id,
                c.company_name,
                d.contact_person,
                d.contact_email,
                d.contact_phone,
                d.payment_method,
                d.is_active,
                d.created_at,
                d.updated_at
            FROM departments d
            LEFT JOIN companies c ON d.company_id = c.id
            WHERE 1=1";
    
    $params = [];
    
    if (!empty($search)) {
        $sql .= " AND (
            d.department_name LIKE ? OR 
            d.department_code LIKE ? OR 
            c.company_name LIKE ?
        )";
        $searchParam = "%{$search}%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    if ($companyId) {
        $sql .= " AND d.company_id = ?";
        $params[] = $companyId;
    }
    
    if ($isActive !== null) {
        $sql .= " AND d.is_active = ?";
        $params[] = $isActive;
    }
    
    $sql .= " ORDER BY c.company_name ASC, d.department_name ASC";
    
    // 総件数
    $countSql = "SELECT COUNT(*) as total FROM departments d 
                 LEFT JOIN companies c ON d.company_id = c.id 
                 WHERE 1=1";
    
    if (!empty($search)) {
        $countSql .= " AND (d.department_name LIKE ? OR d.department_code LIKE ? OR c.company_name LIKE ?)";
    }
    if ($companyId) {
        $countSql .= " AND d.company_id = ?";
    }
    if ($isActive !== null) {
        $countSql .= " AND d.is_active = ?";
    }
    
    $totalResult = $db->fetch($countSql, $params);
    $total = $totalResult['total'] ?? 0;
    
    // ページング
    $sql .= " LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    $departments = $db->fetchAll($sql, $params);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'departments' => $departments,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($total / $limit)
        ]
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * 部署詳細取得
 */
function getDepartmentDetail($db, $id) {
    $sql = "SELECT d.*, c.company_name
            FROM departments d
            LEFT JOIN companies c ON d.company_id = c.id
            WHERE d.id = ?";
    
    $department = $db->fetch($sql, [$id]);
    
    if (!$department) {
        throw new Exception('部署が見つかりません');
    }
    
    echo json_encode([
        'success' => true,
        'data' => $department
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * POST: 部署登録
 */
function handlePost($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('不正なJSONデータです');
    }
    
    $required = ['department_code', 'department_name', 'company_id'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            throw new Exception("{$field}は必須項目です");
        }
    }
    
    // コード重複チェック
    $checkSql = "SELECT COUNT(*) as count FROM departments WHERE department_code = ? AND company_id = ?";
    $checkResult = $db->fetch($checkSql, [$input['department_code'], $input['company_id']]);
    if ($checkResult['count'] > 0) {
        throw new Exception('この部署コードは既に使用されています');
    }
    
    $sql = "INSERT INTO departments (
        department_code, department_name, company_id,
        contact_person, contact_email, contact_phone,
        payment_method, is_active,
        created_at, updated_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
    
    $params = [
        $input['department_code'],
        $input['department_name'],
        $input['company_id'],
        $input['contact_person'] ?? null,
        $input['contact_email'] ?? null,
        $input['contact_phone'] ?? null,
        $input['payment_method'] ?? 'department_bulk',
        isset($input['is_active']) ? $input['is_active'] : 1
    ];
    
    $db->execute($sql, $params);
    $newId = $db->lastInsertId();
    
    $newDepartment = $db->fetch("SELECT * FROM departments WHERE id = ?", [$newId]);
    
    echo json_encode([
        'success' => true,
        'message' => '部署を登録しました',
        'data' => $newDepartment
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * PUT: 部署更新
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
    
    $existing = $db->fetch("SELECT * FROM departments WHERE id = ?", [$id]);
    if (!$existing) {
        throw new Exception('部署が見つかりません');
    }
    
    $updateFields = [];
    $params = [];
    
    $allowedFields = [
        'department_code', 'department_name', 'company_id',
        'contact_person', 'contact_email', 'contact_phone',
        'payment_method', 'is_active'
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
    if (isset($input['department_code']) && isset($input['company_id'])) {
        $checkSql = "SELECT COUNT(*) as count FROM departments WHERE department_code = ? AND company_id = ? AND id != ?";
        $checkResult = $db->fetch($checkSql, [$input['department_code'], $input['company_id'], $id]);
        if ($checkResult['count'] > 0) {
            throw new Exception('この部署コードは既に使用されています');
        }
    }
    
    $updateFields[] = "updated_at = NOW()";
    $params[] = $id;
    
    $sql = "UPDATE departments SET " . implode(', ', $updateFields) . " WHERE id = ?";
    $db->execute($sql, $params);
    
    $updatedDepartment = $db->fetch("SELECT * FROM departments WHERE id = ?", [$id]);
    
    echo json_encode([
        'success' => true,
        'message' => '部署を更新しました',
        'data' => $updatedDepartment
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * DELETE: 部署削除（論理削除）
 */
function handleDelete($db) {
    $id = $_GET['id'] ?? null;
    
    if (!$id) {
        throw new Exception('IDが指定されていません');
    }
    
    $existing = $db->fetch("SELECT * FROM departments WHERE id = ?", [$id]);
    if (!$existing) {
        throw new Exception('部署が見つかりません');
    }
    
    $sql = "UPDATE departments SET is_active = 0, updated_at = NOW() WHERE id = ?";
    $db->execute($sql, [$id]);
    
    echo json_encode([
        'success' => true,
        'message' => '部署を削除しました'
    ], JSON_UNESCAPED_UNICODE);
}
