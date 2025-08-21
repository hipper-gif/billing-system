<?php
/**
 * 部署管理API
 * Smiley配食事業専用
 */

require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/SecurityHelper.php';

// CORS設定
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

// OPTIONSリクエストの処理
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// セキュリティヘッダー設定
SecurityHelper::setSecurityHeaders();

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    
    $method = $_SERVER['REQUEST_METHOD'];
    $input = json_decode(file_get_contents('php://input'), true);
    
    switch ($method) {
        case 'GET':
            handleGetRequest($pdo);
            break;
        case 'POST':
            handlePostRequest($pdo, $input);
            break;
        case 'PUT':
            handlePutRequest($pdo, $input);
            break;
        case 'DELETE':
            handleDeleteRequest($pdo);
            break;
        default:
            throw new Exception('サポートされていないHTTPメソッドです。');
    }
    
} catch (Exception $e) {
    error_log("部署管理API エラー: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * GET リクエストの処理
 */
function handleGetRequest($pdo) {
    $department_id = $_GET['id'] ?? null;
    $company_id = $_GET['company_id'] ?? null;
    
    if ($department_id) {
        // 特定部署の詳細情報取得
        getDepartmentDetail($pdo, $department_id);
    } else {
        // 部署一覧取得
        getDepartmentsList($pdo, $company_id);
    }
}

/**
 * 部署一覧取得
 */
function getDepartmentsList($pdo, $company_id = null) {
    $search = $_GET['search'] ?? '';
    $page = max(1, intval($_GET['page'] ?? 1));
    $per_page = min(100, max(10, intval($_GET['per_page'] ?? 20)));
    $offset = ($page - 1) * $per_page;
    
    // 検索条件の構築
    $where_conditions = [];
    $params = [];
    
    if ($company_id) {
        $where_conditions[] = "d.company_id = :company_id";
        $params['company_id'] = $company_id;
    }
    
    if ($search) {
        $where_conditions[] = "(d.department_name LIKE :search OR d.department_code LIKE :search)";
        $params['search'] = '%' . $search . '%';
    }
    
    $where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // 部署一覧の取得
    $sql = "
        SELECT 
            d.id,
            d.department_code,
            d.department_name,
            d.manager_name,
            d.is_active,
            d.created_at,
            d.updated_at,
            -- 企業情報
            c.company_name,
            c.company_code,
            -- 統計情報
            COUNT(DISTINCT u.id) as user_count,
            COUNT(DISTINCT o.id) as total_order_count,
            COALESCE(SUM(o.total_amount), 0) as total_revenue,
            MAX(o.delivery_date) as last_order_date
        FROM departments d
        LEFT JOIN companies c ON d.company_id = c.id
        LEFT JOIN users u ON d.id = u.department_id
        LEFT JOIN orders o ON d.id = o.department_id
        $where_clause
        GROUP BY d.id
        ORDER BY c.company_name, d.department_name
        LIMIT :limit OFFSET :offset
    ";
    
    $params['limit'] = $per_page;
    $params['offset'] = $offset;
    
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 総件数取得
    $count_sql = "
        SELECT COUNT(DISTINCT d.id) as total 
        FROM departments d
        LEFT JOIN companies c ON d.company_id = c.id
        $where_clause
    ";
    $count_stmt = $pdo->prepare($count_sql);
    foreach ($params as $key => $value) {
        if ($key !== 'limit' && $key !== 'offset') {
            $count_stmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
    }
    $count_stmt->execute();
    $total = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    echo json_encode([
        'success' => true,
        'data' => $departments,
        'pagination' => [
            'page' => $page,
            'per_page' => $per_page,
            'total' => $total,
            'total_pages' => ceil($total / $per_page)
        ]
    ]);
}

/**
 * 部署詳細情報取得
 */
function getDepartmentDetail($pdo, $department_id) {
    $sql = "
        SELECT 
            d.*,
            c.company_name,
            c.company_code,
            c.company_address,
            c.billing_method,
            -- 統計情報
            COUNT(DISTINCT u.id) as user_count,
            COUNT(DISTINCT o.id) as total_order_count,
            COALESCE(SUM(o.total_amount), 0) as total_revenue,
            MAX(o.delivery_date) as last_order_date,
            MIN(o.delivery_date) as first_order_date
        FROM departments d
        LEFT JOIN companies c ON d.company_id = c.id
        LEFT JOIN users u ON d.id = u.department_id
        LEFT JOIN orders o ON d.id = o.department_id
        WHERE d.id = :department_id
        GROUP BY d.id
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':department_id', $department_id, PDO::PARAM_INT);
    $stmt->execute();
    $department = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$department) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => '指定された部署が見つかりません。'
        ]);
        return;
    }
    
    // 所属利用者一覧も取得
    $users_sql = "
        SELECT 
            u.*,
            COUNT(DISTINCT o.id) as order_count,
            COALESCE(SUM(o.total_amount), 0) as total_amount,
            MAX(o.delivery_date) as last_order_date
        FROM users u
        LEFT JOIN orders o ON u.id = o.user_id
        WHERE u.department_id = :department_id
        GROUP BY u.id
        ORDER BY u.user_name
    ";
    
    $users_stmt = $pdo->prepare($users_sql);
    $users_stmt->bindValue(':department_id', $department_id, PDO::PARAM_INT);
    $users_stmt->execute();
    $users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 最近の注文履歴も取得（最新10件）
    $orders_sql = "
        SELECT 
            o.delivery_date,
            o.user_name,
            o.product_name,
            o.quantity,
            o.total_amount
        FROM orders o
        WHERE o.department_id = :department_id
        ORDER BY o.delivery_date DESC, o.id DESC
        LIMIT 10
    ";
    
    $orders_stmt = $pdo->prepare($orders_sql);
    $orders_stmt->bindValue(':department_id', $department_id, PDO::PARAM_INT);
    $orders_stmt->execute();
    $recent_orders = $orders_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $department['users'] = $users;
    $department['recent_orders'] = $recent_orders;
    
    echo json_encode([
        'success' => true,
        'data' => $department
    ]);
}

/**
 * POST リクエストの処理（新規部署作成）
 */
function handlePostRequest($pdo, $input) {
    // フォームデータからの場合
    if (empty($input) && !empty($_POST)) {
        $input = $_POST;
    }
    
    // 必須項目のチェック
    $required_fields = ['company_id', 'department_code', 'department_name'];
    foreach ($required_fields as $field) {
        if (empty($input[$field])) {
            throw new Exception("必須項目「{$field}」が入力されていません。");
        }
    }
    
    // 企業の存在確認
    $company_check_sql = "SELECT id FROM companies WHERE id = :company_id";
    $company_stmt = $pdo->prepare($company_check_sql);
    $company_stmt->bindValue(':company_id', $input['company_id'], PDO::PARAM_INT);
    $company_stmt->execute();
    
    if (!$company_stmt->fetch()) {
        throw new Exception('指定された企業が見つかりません。');
    }
    
    // 部署コードの重複チェック（同一企業内）
    $check_sql = "SELECT id FROM departments WHERE company_id = :company_id AND department_code = :department_code";
    $check_stmt = $pdo->prepare($check_sql);
    $check_stmt->bindValue(':company_id', $input['company_id'], PDO::PARAM_INT);
    $check_stmt->bindValue(':department_code', $input['department_code']);
    $check_stmt->execute();
    
    if ($check_stmt->fetch()) {
        throw new Exception('指定された部署コードは同じ企業内で既に使用されています。');
    }
    
    $pdo->beginTransaction();
    
    try {
        // 部署データの挿入
        $sql = "
            INSERT INTO departments (
                company_id, department_code, department_name,
                manager_name, is_active, created_at, updated_at
            ) VALUES (
                :company_id, :department_code, :department_name,
                :manager_name, 1, NOW(), NOW()
            )
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':company_id', $input['company_id'], PDO::PARAM_INT);
        $stmt->bindValue(':department_code', $input['department_code']);
        $stmt->bindValue(':department_name', $input['department_name']);
        $stmt->bindValue(':manager_name', $input['manager_name'] ?? null);
        
        $stmt->execute();
        $department_id = $pdo->lastInsertId();
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => '部署を正常に追加しました。',
            'data' => ['id' => $department_id]
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * PUT リクエストの処理（部署情報更新）
 */
function handlePutRequest($pdo, $input) {
    $department_id = $_GET['id'] ?? $input['id'] ?? null;
    
    if (!$department_id) {
        throw new Exception('部署IDが指定されていません。');
    }
    
    // 部署の存在確認
    $check_sql = "SELECT id FROM departments WHERE id = :id";
    $check_stmt = $pdo->prepare($check_sql);
    $check_stmt->bindValue(':id', $department_id, PDO::PARAM_INT);
    $check_stmt->execute();
    
    if (!$check_stmt->fetch()) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => '指定された部署が見つかりません。'
        ]);
        return;
    }
    
    $pdo->beginTransaction();
    
    try {
        // 更新用SQL構築
        $update_fields = [];
        $params = ['id' => $department_id];
        
        $allowed_fields = [
            'department_code', 'department_name', 'manager_name', 'is_active'
        ];
        
        foreach ($allowed_fields as $field) {
            if (isset($input[$field])) {
                $update_fields[] = "{$field} = :{$field}";
                $params[$field] = $input[$field];
            }
        }
        
        if (empty($update_fields)) {
            throw new Exception('更新する項目が指定されていません。');
        }
        
        $update_fields[] = "updated_at = NOW()";
        
        $sql = "UPDATE departments SET " . implode(', ', $update_fields) . " WHERE id = :id";
        
        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => '部署情報を正常に更新しました。'
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * DELETE リクエストの処理（部署削除）
 */
function handleDeleteRequest($pdo) {
    $department_id = $_GET['id'] ?? null;
    
    if (!$department_id) {
        throw new Exception('部署IDが指定されていません。');
    }
    
    // 部署の存在確認
    $check_sql = "SELECT id FROM departments WHERE id = :id";
    $check_stmt = $pdo->prepare($check_sql);
    $check_stmt->bindValue(':id', $department_id, PDO::PARAM_INT);
    $check_stmt->execute();
    
    if (!$check_stmt->fetch()) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => '指定された部署が見つかりません。'
        ]);
        return;
    }
    
    // 関連データの確認
    $orders_check_sql = "SELECT COUNT(*) as count FROM orders WHERE department_id = :id";
    $orders_stmt = $pdo->prepare($orders_check_sql);
    $orders_stmt->bindValue(':id', $department_id, PDO::PARAM_INT);
    $orders_stmt->execute();
    $orders_count = $orders_stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $users_check_sql = "SELECT COUNT(*) as count FROM users WHERE department_id = :id";
    $users_stmt = $pdo->prepare($users_check_sql);
    $users_stmt->bindValue(':id', $department_id, PDO::PARAM_INT);
    $users_stmt->execute();
    $users_count = $users_stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($orders_count > 0 || $users_count > 0) {
        // 注文データまたは利用者がある場合は論理削除
        $sql = "UPDATE departments SET is_active = 0, updated_at = NOW() WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':id', $department_id, PDO::PARAM_INT);
        $stmt->execute();
        
        $message = '部署を非アクティブ状態にしました。';
        if ($orders_count > 0) {
            $message .= "（注文履歴があるため完全削除はできません）";
        }
        if ($users_count > 0) {
            $message .= "（所属利用者がいるため完全削除はできません）";
        }
        
        echo json_encode([
            'success' => true,
            'message' => $message
        ]);
    } else {
        // 関連データがない場合は物理削除
        $sql = "DELETE FROM departments WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':id', $department_id, PDO::PARAM_INT);
        $stmt->execute();
        
        echo json_encode([
            'success' => true,
            'message' => '部署を完全に削除しました。'
        ]);
    }
}
?>
