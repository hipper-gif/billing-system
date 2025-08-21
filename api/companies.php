<?php
/**
 * 配達先企業管理API
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
    error_log("企業管理API エラー: " . $e->getMessage());
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
    $company_id = $_GET['id'] ?? null;
    
    if ($company_id) {
        // 特定企業の詳細情報取得
        getCompanyDetail($pdo, $company_id);
    } else {
        // 企業一覧取得
        getCompaniesList($pdo);
    }
}

/**
 * 企業一覧取得
 */
function getCompaniesList($pdo) {
    $search = $_GET['search'] ?? '';
    $page = max(1, intval($_GET['page'] ?? 1));
    $per_page = min(100, max(10, intval($_GET['per_page'] ?? 20)));
    $offset = ($page - 1) * $per_page;
    
    // 検索条件の構築
    $where_conditions = [];
    $params = [];
    
    if ($search) {
        $where_conditions[] = "(company_name LIKE :search OR company_code LIKE :search)";
        $params['search'] = '%' . $search . '%';
    }
    
    $where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // 企業一覧の取得
    $sql = "
        SELECT 
            c.id,
            c.company_code,
            c.company_name,
            c.company_address,
            c.contact_person,
            c.contact_phone,
            c.contact_email,
            c.billing_method,
            c.is_active,
            c.created_at,
            c.updated_at,
            -- 統計情報
            COUNT(DISTINCT d.id) as department_count,
            COUNT(DISTINCT u.id) as user_count,
            COUNT(DISTINCT o.id) as total_order_count,
            COALESCE(SUM(o.total_amount), 0) as total_revenue
        FROM companies c
        LEFT JOIN departments d ON c.id = d.company_id
        LEFT JOIN users u ON c.id = u.company_id
        LEFT JOIN orders o ON c.id = o.company_id
        $where_clause
        GROUP BY c.id
        ORDER BY c.company_name
        LIMIT :limit OFFSET :offset
    ";
    
    $params['limit'] = $per_page;
    $params['offset'] = $offset;
    
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
    $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 総件数取得
    $count_sql = "SELECT COUNT(DISTINCT id) as total FROM companies $where_clause";
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
        'data' => $companies,
        'pagination' => [
            'page' => $page,
            'per_page' => $per_page,
            'total' => $total,
            'total_pages' => ceil($total / $per_page)
        ]
    ]);
}

/**
 * 企業詳細情報取得
 */
function getCompanyDetail($pdo, $company_id) {
    $sql = "
        SELECT 
            c.*,
            -- 統計情報
            COUNT(DISTINCT d.id) as department_count,
            COUNT(DISTINCT u.id) as user_count,
            COUNT(DISTINCT o.id) as total_order_count,
            COALESCE(SUM(o.total_amount), 0) as total_revenue,
            MAX(o.delivery_date) as last_order_date,
            MIN(o.delivery_date) as first_order_date
        FROM companies c
        LEFT JOIN departments d ON c.id = d.company_id
        LEFT JOIN users u ON c.id = u.company_id
        LEFT JOIN orders o ON c.id = o.company_id
        WHERE c.id = :company_id
        GROUP BY c.id
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':company_id', $company_id, PDO::PARAM_INT);
    $stmt->execute();
    $company = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$company) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => '指定された企業が見つかりません。'
        ]);
        return;
    }
    
    // 部署一覧も取得
    $departments_sql = "
        SELECT 
            d.*,
            COUNT(DISTINCT u.id) as user_count,
            COUNT(DISTINCT o.id) as order_count,
            COALESCE(SUM(o.total_amount), 0) as total_amount
        FROM departments d
        LEFT JOIN users u ON d.id = u.department_id
        LEFT JOIN orders o ON d.id = o.department_id
        WHERE d.company_id = :company_id
        GROUP BY d.id
        ORDER BY d.department_name
    ";
    
    $dept_stmt = $pdo->prepare($departments_sql);
    $dept_stmt->bindValue(':company_id', $company_id, PDO::PARAM_INT);
    $dept_stmt->execute();
    $departments = $dept_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 最近の注文履歴も取得（最新10件）
    $orders_sql = "
        SELECT 
            o.delivery_date,
            o.user_name,
            o.product_name,
            o.quantity,
            o.total_amount,
            o.department_name
        FROM orders o
        WHERE o.company_id = :company_id
        ORDER BY o.delivery_date DESC, o.id DESC
        LIMIT 10
    ";
    
    $orders_stmt = $pdo->prepare($orders_sql);
    $orders_stmt->bindValue(':company_id', $company_id, PDO::PARAM_INT);
    $orders_stmt->execute();
    $recent_orders = $orders_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $company['departments'] = $departments;
    $company['recent_orders'] = $recent_orders;
    
    echo json_encode([
        'success' => true,
        'data' => $company
    ]);
}

/**
 * POST リクエストの処理（新規企業作成）
 */
function handlePostRequest($pdo, $input) {
    // フォームデータからの場合
    if (empty($input) && !empty($_POST)) {
        $input = $_POST;
    }
    
    // 必須項目のチェック
    $required_fields = ['company_code', 'company_name'];
    foreach ($required_fields as $field) {
        if (empty($input[$field])) {
            throw new Exception("必須項目「{$field}」が入力されていません。");
        }
    }
    
    // 企業コードの重複チェック
    $check_sql = "SELECT id FROM companies WHERE company_code = :company_code";
    $check_stmt = $pdo->prepare($check_sql);
    $check_stmt->bindValue(':company_code', $input['company_code']);
    $check_stmt->execute();
    
    if ($check_stmt->fetch()) {
        throw new Exception('指定された企業コードは既に使用されています。');
    }
    
    $pdo->beginTransaction();
    
    try {
        // 企業データの挿入
        $sql = "
            INSERT INTO companies (
                company_code, company_name, company_address,
                contact_person, contact_phone, contact_email,
                billing_method, is_active, created_at, updated_at
            ) VALUES (
                :company_code, :company_name, :company_address,
                :contact_person, :contact_phone, :contact_email,
                :billing_method, 1, NOW(), NOW()
            )
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':company_code', $input['company_code']);
        $stmt->bindValue(':company_name', $input['company_name']);
        $stmt->bindValue(':company_address', $input['company_address'] ?? null);
        $stmt->bindValue(':contact_person', $input['contact_person'] ?? null);
        $stmt->bindValue(':contact_phone', $input['contact_phone'] ?? null);
        $stmt->bindValue(':contact_email', $input['contact_email'] ?? null);
        $stmt->bindValue(':billing_method', $input['billing_method'] ?? 'company');
        
        $stmt->execute();
        $company_id = $pdo->lastInsertId();
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => '配達先企業を正常に追加しました。',
            'data' => ['id' => $company_id]
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * PUT リクエストの処理（企業情報更新）
 */
function handlePutRequest($pdo, $input) {
    $company_id = $_GET['id'] ?? $input['id'] ?? null;
    
    if (!$company_id) {
        throw new Exception('企業IDが指定されていません。');
    }
    
    // 企業の存在確認
    $check_sql = "SELECT id FROM companies WHERE id = :id";
    $check_stmt = $pdo->prepare($check_sql);
    $check_stmt->bindValue(':id', $company_id, PDO::PARAM_INT);
    $check_stmt->execute();
    
    if (!$check_stmt->fetch()) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => '指定された企業が見つかりません。'
        ]);
        return;
    }
    
    $pdo->beginTransaction();
    
    try {
        // 更新用SQL構築
        $update_fields = [];
        $params = ['id' => $company_id];
        
        $allowed_fields = [
            'company_code', 'company_name', 'company_address',
            'contact_person', 'contact_phone', 'contact_email',
            'billing_method', 'is_active'
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
        
        $sql = "UPDATE companies SET " . implode(', ', $update_fields) . " WHERE id = :id";
        
        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => '企業情報を正常に更新しました。'
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * DELETE リクエストの処理（企業削除）
 */
function handleDeleteRequest($pdo) {
    $company_id = $_GET['id'] ?? null;
    
    if (!$company_id) {
        throw new Exception('企業IDが指定されていません。');
    }
    
    // 企業の存在確認
    $check_sql = "SELECT id FROM companies WHERE id = :id";
    $check_stmt = $pdo->prepare($check_sql);
    $check_stmt->bindValue(':id', $company_id, PDO::PARAM_INT);
    $check_stmt->execute();
    
    if (!$check_stmt->fetch()) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => '指定された企業が見つかりません。'
        ]);
        return;
    }
    
    // 関連データの確認
    $orders_check_sql = "SELECT COUNT(*) as count FROM orders WHERE company_id = :id";
    $orders_stmt = $pdo->prepare($orders_check_sql);
    $orders_stmt->bindValue(':id', $company_id, PDO::PARAM_INT);
    $orders_stmt->execute();
    $orders_count = $orders_stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($orders_count > 0) {
        // 注文データがある場合は論理削除
        $sql = "UPDATE companies SET is_active = 0, updated_at = NOW() WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':id', $company_id, PDO::PARAM_INT);
        $stmt->execute();
        
        echo json_encode([
            'success' => true,
            'message' => '企業を非アクティブ状態にしました。（注文履歴があるため完全削除はできません）'
        ]);
    } else {
        // 注文データがない場合は物理削除
        $pdo->beginTransaction();
        
        try {
            // 関連する部署と利用者も削除
            $pdo->prepare("DELETE FROM users WHERE company_id = :id")->execute(['id' => $company_id]);
            $pdo->prepare("DELETE FROM departments WHERE company_id = :id")->execute(['id' => $company_id]);
            $pdo->prepare("DELETE FROM companies WHERE id = :id")->execute(['id' => $company_id]);
            
            $pdo->commit();
            
            echo json_encode([
                'success' => true,
                'message' => '企業を完全に削除しました。'
            ]);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
?>
