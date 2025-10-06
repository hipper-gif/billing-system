<?php
/**
 * 企業管理API
 * 完全CRUD対応
 * 
 * @version 2.0.0 - v5.0仕様準拠版
 * @updated 2025-10-06
 * @changes config/database.php読み込みに変更
 */

// エラーレポート設定（開発時）
error_reporting(E_ALL);
ini_set('display_errors', 0); // 本番ではエラー表示しない
ini_set('log_errors', 1);

// v5.0仕様: config/database.php から Database クラスを読み込む
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/SecurityHelper.php';

// JSONヘッダー設定
SecurityHelper::setJsonHeaders();

// HTTPメソッド取得
$method = $_SERVER['REQUEST_METHOD'];

try {
    // Database接続
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
    // エラーログ記録
    error_log("Companies API Error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    // エラーレスポンス
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
 * GET: 企業一覧・詳細取得
 */
function handleGet($db) {
    $id = $_GET['id'] ?? null;
    
    if ($id) {
        // 企業詳細取得
        getCompanyDetail($db, $id);
    } else {
        // 企業一覧取得
        getCompaniesList($db);
    }
}

/**
 * 企業一覧取得
 */
function getCompaniesList($db) {
    // パラメータ取得
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? min(100, max(1, intval($_GET['limit']))) : 20;
    $search = $_GET['search'] ?? '';
    $isActive = isset($_GET['is_active']) ? intval($_GET['is_active']) : null;
    
    $offset = ($page - 1) * $limit;
    
    // クエリ構築
    $sql = "SELECT 
                id,
                company_code,
                company_name,
                company_address,
                phone,
                email,
                contact_person,
                payment_method,
                billing_method,
                payment_cycle,
                is_active,
                created_at,
                updated_at
            FROM companies
            WHERE 1=1";
    
    $params = [];
    
    // 検索条件
    if (!empty($search)) {
        $sql .= " AND (
            company_name LIKE ? OR 
            company_code LIKE ? OR 
            contact_person LIKE ?
        )";
        $searchParam = "%{$search}%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    // 有効フラグフィルタ
    if ($isActive !== null) {
        $sql .= " AND is_active = ?";
        $params[] = $isActive;
    }
    
    // ソート
    $sql .= " ORDER BY company_name ASC";
    
    // 総件数取得
    $countSql = str_replace(
        "SELECT id, company_code, company_name, company_address, phone, email, contact_person, payment_method, billing_method, payment_cycle, is_active, created_at, updated_at",
        "SELECT COUNT(*) as total",
        $sql
    );
    $totalResult = $db->fetch($countSql, $params);
    $total = $totalResult['total'] ?? 0;
    
    // ページング適用
    $sql .= " LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    // データ取得
    $companies = $db->fetchAll($sql, $params);
    
    // レスポンス
    echo json_encode([
        'success' => true,
        'data' => [
            'companies' => $companies,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($total / $limit)
        ]
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * 企業詳細取得
 */
function getCompanyDetail($db, $id) {
    $sql = "SELECT * FROM companies WHERE id = ?";
    $company = $db->fetch($sql, [$id]);
    
    if (!$company) {
        throw new Exception('企業が見つかりません');
    }
    
    // レスポンス
    echo json_encode([
        'success' => true,
        'data' => $company
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * POST: 企業登録
 */
function handlePost($db) {
    // リクエストボディ取得
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('不正なJSONデータです');
    }
    
    // 必須項目チェック
    $required = ['company_code', 'company_name'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            throw new Exception("{$field}は必須項目です");
        }
    }
    
    // 企業コード重複チェック
    $checkSql = "SELECT COUNT(*) as count FROM companies WHERE company_code = ?";
    $checkResult = $db->fetch($checkSql, [$input['company_code']]);
    if ($checkResult['count'] > 0) {
        throw new Exception('この企業コードは既に使用されています');
    }
    
    // データ挿入
    $sql = "INSERT INTO companies (
        company_code,
        company_name,
        company_address,
        phone,
        email,
        contact_person,
        contact_department,
        contact_phone,
        contact_email,
        payment_method,
        billing_method,
        payment_cycle,
        payment_due_days,
        closing_date,
        is_active,
        created_at,
        updated_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
    
    $params = [
        $input['company_code'],
        $input['company_name'],
        $input['company_address'] ?? null,
        $input['phone'] ?? null,
        $input['email'] ?? null,
        $input['contact_person'] ?? null,
        $input['contact_department'] ?? null,
        $input['contact_phone'] ?? null,
        $input['contact_email'] ?? null,
        $input['payment_method'] ?? 'company_bulk',
        $input['billing_method'] ?? 'company',
        $input['payment_cycle'] ?? 'monthly',
        $input['payment_due_days'] ?? 30,
        $input['closing_date'] ?? 31,
        isset($input['is_active']) ? $input['is_active'] : 1
    ];
    
    $db->execute($sql, $params);
    $newId = $db->lastInsertId();
    
    // 登録データ取得
    $newCompany = $db->fetch("SELECT * FROM companies WHERE id = ?", [$newId]);
    
    // レスポンス
    echo json_encode([
        'success' => true,
        'message' => '企業を登録しました',
        'data' => $newCompany
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * PUT: 企業更新
 */
function handlePut($db) {
    // リクエストボディ取得
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('不正なJSONデータです');
    }
    
    // ID必須チェック
    if (empty($input['id'])) {
        throw new Exception('IDは必須項目です');
    }
    
    $id = $input['id'];
    
    // 存在チェック
    $existing = $db->fetch("SELECT * FROM companies WHERE id = ?", [$id]);
    if (!$existing) {
        throw new Exception('企業が見つかりません');
    }
    
    // 更新データ構築
    $updateFields = [];
    $params = [];
    
    $allowedFields = [
        'company_code', 'company_name', 'company_address',
        'phone', 'email', 'contact_person', 'contact_department',
        'contact_phone', 'contact_email', 'payment_method',
        'billing_method', 'payment_cycle', 'payment_due_days',
        'closing_date', 'is_active'
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
    
    // 企業コード重複チェック（自分以外）
    if (isset($input['company_code'])) {
        $checkSql = "SELECT COUNT(*) as count FROM companies WHERE company_code = ? AND id != ?";
        $checkResult = $db->fetch($checkSql, [$input['company_code'], $id]);
        if ($checkResult['count'] > 0) {
            throw new Exception('この企業コードは既に使用されています');
        }
    }
    
    // 更新実行
    $updateFields[] = "updated_at = NOW()";
    $params[] = $id;
    
    $sql = "UPDATE companies SET " . implode(', ', $updateFields) . " WHERE id = ?";
    $db->execute($sql, $params);
    
    // 更新後データ取得
    $updatedCompany = $db->fetch("SELECT * FROM companies WHERE id = ?", [$id]);
    
    // レスポンス
    echo json_encode([
        'success' => true,
        'message' => '企業を更新しました',
        'data' => $updatedCompany
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * DELETE: 企業削除（論理削除）
 */
function handleDelete($db) {
    $id = $_GET['id'] ?? null;
    
    if (!$id) {
        throw new Exception('IDが指定されていません');
    }
    
    // 存在チェック
    $existing = $db->fetch("SELECT * FROM companies WHERE id = ?", [$id]);
    if (!$existing) {
        throw new Exception('企業が見つかりません');
    }
    
    // 論理削除
    $sql = "UPDATE companies SET is_active = 0, updated_at = NOW() WHERE id = ?";
    $db->execute($sql, [$id]);
    
    // レスポンス
    echo json_encode([
        'success' => true,
        'message' => '企業を削除しました'
    ], JSON_UNESCAPED_UNICODE);
}
