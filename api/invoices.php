<?php
/**
 * 請求書生成・管理API（修正版）
 * POST処理エラー修正・請求書生成機能完全対応
 * 
 * @author Claude
 * @version 2.0.0
 * @created 2025-08-26
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// プリフライトリクエスト対応
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/SmileyInvoiceGenerator.php';
require_once __DIR__ . '/../classes/SecurityHelper.php';

try {
    // セキュリティヘッダー設定
    SecurityHelper::setSecurityHeaders();
    
    $method = $_SERVER['REQUEST_METHOD'];
    $invoiceGenerator = new SmileyInvoiceGenerator();
    
    switch ($method) {
        case 'GET':
            handleGetRequest($invoiceGenerator);
            break;
            
        case 'POST':
            handlePostRequest($invoiceGenerator);
            break;
            
        case 'PUT':
            handlePutRequest($invoiceGenerator);
            break;
            
        case 'DELETE':
            handleDeleteRequest($invoiceGenerator);
            break;
            
        default:
            throw new Exception('未対応のHTTPメソッドです');
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s'),
        'debug' => [
            'method' => $_SERVER['REQUEST_METHOD'],
            'file' => basename(__FILE__),
            'line' => __LINE__
        ]
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * GET リクエスト処理
 * 請求書一覧取得、詳細取得、対象データ取得
 */
function handleGetRequest($invoiceGenerator) {
    $action = $_GET['action'] ?? 'list';
    
    switch ($action) {
        case 'list':
            getInvoiceList($invoiceGenerator);
            break;
            
        case 'detail':
            getInvoiceDetail($invoiceGenerator);
            break;
            
        case 'companies':
            getTargetCompanies();
            break;
            
        case 'departments':
            getTargetDepartments();
            break;
            
        case 'users':
            getTargetUsers();
            break;
            
        case 'statistics':
            getInvoiceStatistics();
            break;
            
        default:
            throw new Exception('未対応のアクションです: ' . $action);
    }
}

/**
 * POST リクエスト処理
 * 請求書生成
 */
function handlePostRequest($invoiceGenerator) {
    // Content-Type チェック
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    
    if (strpos($contentType, 'application/json') !== false) {
        // JSON データ処理
        $input = json_decode(file_get_contents('php://input'), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('JSON データの解析に失敗しました: ' . json_last_error_msg());
        }
    } else {
        // フォームデータ処理
        $input = $_POST;
    }
    
    $action = $input['action'] ?? 'generate';
    
    switch ($action) {
        case 'generate':
            generateInvoices($invoiceGenerator, $input);
            break;
            
        case 'regenerate':
            regenerateInvoice($invoiceGenerator, $input);
            break;
            
        default:
            throw new Exception('未対応のアクションです: ' . $action);
    }
}

/**
 * PUT リクエスト処理
 * 請求書ステータス更新
 */
function handlePutRequest($invoiceGenerator) {
    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('JSON データの解析に失敗しました');
    }
    
    $action = $input['action'] ?? 'update_status';
    
    switch ($action) {
        case 'update_status':
            updateInvoiceStatus($invoiceGenerator, $input);
            break;
            
        default:
            throw new Exception('未対応のアクションです: ' . $action);
    }
}

/**
 * DELETE リクエスト処理
 * 請求書削除
 */
function handleDeleteRequest($invoiceGenerator) {
    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('JSON データの解析に失敗しました');
    }
    
    $invoiceId = $input['invoice_id'] ?? null;
    
    if (!$invoiceId) {
        throw new Exception('請求書IDが必要です');
    }
    
    $result = $invoiceGenerator->deleteInvoice($invoiceId);
    
    echo json_encode([
        'success' => true,
        'message' => '請求書が削除されました',
        'invoice_id' => $invoiceId,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * 請求書生成実行
 */
function generateInvoices($invoiceGenerator, $input) {
    // 必須パラメータ検証
    $requiredParams = ['invoice_type', 'period_start', 'period_end'];
    foreach ($requiredParams as $param) {
        if (empty($input[$param])) {
            throw new Exception("必須パラメータが不足しています: {$param}");
        }
    }
    
    // 日付形式検証
    if (!validateDateFormat($input['period_start']) || !validateDateFormat($input['period_end'])) {
        throw new Exception('日付形式が正しくありません（YYYY-MM-DD形式で入力してください）');
    }
    
    // 期間チェック
    if (strtotime($input['period_start']) > strtotime($input['period_end'])) {
        throw new Exception('開始日は終了日より前の日付を選択してください');
    }
    
    // パラメータ構築
    $params = [
        'invoice_type' => SecurityHelper::sanitizeInput($input['invoice_type']),
        'period_start' => $input['period_start'],
        'period_end' => $input['period_end'],
        'due_date' => $input['due_date'] ?? null,
        'target_ids' => !empty($input['target_ids']) ? array_map('intval', $input['target_ids']) : [],
        'auto_generate_pdf' => !empty($input['auto_generate_pdf'])
    ];
    
    // 請求書生成実行
    try {
        $result = $invoiceGenerator->generateInvoices($params);
        
        echo json_encode([
            'success' => true,
            'data' => $result,
            'message' => "請求書を{$result['total_invoices']}件生成しました（合計金額: ¥" . number_format($result['total_amount']) . "）",
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        throw new Exception('請求書生成エラー: ' . $e->getMessage());
    }
}

/**
 * 請求書一覧取得
 */
function getInvoiceList($invoiceGenerator) {
    $page = intval($_GET['page'] ?? 1);
    $limit = min(intval($_GET['limit'] ?? 50), 100); // 最大100件
    
    $filters = [];
    if (!empty($_GET['company_id'])) {
        $filters['company_id'] = intval($_GET['company_id']);
    }
    if (!empty($_GET['status'])) {
        $filters['status'] = SecurityHelper::sanitizeInput($_GET['status']);
    }
    if (!empty($_GET['invoice_type'])) {
        $filters['invoice_type'] = SecurityHelper::sanitizeInput($_GET['invoice_type']);
    }
    if (!empty($_GET['period_start'])) {
        $filters['period_start'] = $_GET['period_start'];
    }
    if (!empty($_GET['period_end'])) {
        $filters['period_end'] = $_GET['period_end'];
    }
    
    $result = $invoiceGenerator->getInvoiceList($filters, $page, $limit);
    
    echo json_encode([
        'success' => true,
        'data' => $result,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * 請求書詳細取得
 */
function getInvoiceDetail($invoiceGenerator) {
    $invoiceId = intval($_GET['invoice_id'] ?? 0);
    
    if (!$invoiceId) {
        throw new Exception('請求書IDが必要です');
    }
    
    $invoice = $invoiceGenerator->getInvoiceData($invoiceId);
    
    if (!$invoice) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => '請求書が見つかりません',
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $invoice,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * 対象企業一覧取得
 */
function getTargetCompanies() {
    $db = Database::getInstance();
    
    $stmt = $db->prepare("
        SELECT 
            c.id, c.company_name,
            COUNT(DISTINCT u.id) as user_count,
            COUNT(DISTINCT o.id) as recent_order_count,
            COALESCE(SUM(o.total_amount), 0) as recent_total_amount
        FROM companies c
        LEFT JOIN users u ON c.id = u.company_id AND u.is_active = 1
        LEFT JOIN orders o ON c.id = o.company_id AND o.delivery_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        WHERE c.is_active = 1
        GROUP BY c.id, c.company_name
        HAVING user_count > 0
        ORDER BY c.company_name
    ");
    $stmt->execute();
    $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $companies,
        'count' => count($companies),
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * 対象部署一覧取得
 */
function getTargetDepartments() {
    $companyId = intval($_GET['company_id'] ?? 0);
    $db = Database::getInstance();
    
    $whereClause = "WHERE d.is_active = 1 AND c.is_active = 1";
    $params = [];
    
    if ($companyId > 0) {
        $whereClause .= " AND d.company_id = ?";
        $params[] = $companyId;
    }
    
    $stmt = $db->prepare("
        SELECT 
            d.id, d.department_name, d.company_id,
            c.company_name,
            COUNT(DISTINCT u.id) as user_count,
            COUNT(DISTINCT o.id) as recent_order_count
        FROM departments d
        INNER JOIN companies c ON d.company_id = c.id
        LEFT JOIN users u ON d.id = u.department_id AND u.is_active = 1
        LEFT JOIN orders o ON d.id = o.department_id AND o.delivery_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        {$whereClause}
        GROUP BY d.id, d.department_name, d.company_id, c.company_name
        HAVING user_count > 0
        ORDER BY c.company_name, d.department_name
    ");
    $stmt->execute($params);
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $departments,
        'count' => count($departments),
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * 対象利用者一覧取得
 */
function getTargetUsers() {
    $companyId = intval($_GET['company_id'] ?? 0);
    $departmentId = intval($_GET['department_id'] ?? 0);
    $db = Database::getInstance();
    
    $whereClause = "WHERE u.is_active = 1 AND c.is_active = 1";
    $params = [];
    
    if ($companyId > 0) {
        $whereClause .= " AND u.company_id = ?";
        $params[] = $companyId;
    }
    
    if ($departmentId > 0) {
        $whereClause .= " AND u.department_id = ?";
        $params[] = $departmentId;
    }
    
    $stmt = $db->prepare("
        SELECT 
            u.id, u.user_name, u.company_id, u.department_id,
            c.company_name, d.department_name,
            COUNT(o.id) as recent_order_count,
            COALESCE(SUM(o.total_amount), 0) as recent_total_amount
        FROM users u
        LEFT JOIN companies c ON u.company_id = c.id
        LEFT JOIN departments d ON u.department_id = d.id
        LEFT JOIN orders o ON u.id = o.user_id AND o.delivery_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        {$whereClause}
        GROUP BY u.id, u.user_name, u.company_id, u.department_id, c.company_name, d.department_name
        HAVING recent_order_count > 0
        ORDER BY c.company_name, d.department_name, u.user_name
    ");
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $users,
        'count' => count($users),
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * 請求書統計情報取得
 */
function getInvoiceStatistics() {
    $db = Database::getInstance();
    
    // 基本統計
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_invoices,
            COUNT(CASE WHEN status = 'issued' THEN 1 END) as issued_count,
            COUNT(CASE WHEN status = 'sent' THEN 1 END) as sent_count,
            COUNT(CASE WHEN status = 'paid' THEN 1 END) as paid_count,
            COUNT(CASE WHEN status = 'overdue' THEN 1 END) as overdue_count,
            COALESCE(SUM(total_amount), 0) as total_amount,
            COALESCE(SUM(CASE WHEN status = 'paid' THEN total_amount ELSE 0 END), 0) as paid_amount,
            COALESCE(SUM(CASE WHEN status IN ('issued', 'sent') THEN total_amount ELSE 0 END), 0) as pending_amount
        FROM invoices
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    ");
    $stmt->execute();
    $basicStats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $basicStats,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * 請求書ステータス更新
 */
function updateInvoiceStatus($invoiceGenerator, $input) {
    $invoiceId = intval($input['invoice_id'] ?? 0);
    $status = SecurityHelper::sanitizeInput($input['status'] ?? '');
    $notes = SecurityHelper::sanitizeInput($input['notes'] ?? '');
    
    if (!$invoiceId || !$status) {
        throw new Exception('請求書IDとステータスが必要です');
    }
    
    $result = $invoiceGenerator->updateInvoiceStatus($invoiceId, $status, $notes);
    
    if (!$result) {
        throw new Exception('ステータス更新に失敗しました');
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'ステータスを更新しました',
        'invoice_id' => $invoiceId,
        'status' => $status,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * 請求書再生成
 */
function regenerateInvoice($invoiceGenerator, $input) {
    $invoiceId = intval($input['invoice_id'] ?? 0);
    
    if (!$invoiceId) {
        throw new Exception('請求書IDが必要です');
    }
    
    // 既存請求書データ取得
    $invoice = $invoiceGenerator->getInvoiceData($invoiceId);
    if (!$invoice) {
        throw new Exception('請求書が見つかりません');
    }
    
    // 支払済みまたは送付済みは再生成不可
    if (in_array($invoice['status'], ['paid', 'sent'])) {
        throw new Exception('支払済みまたは送付済みの請求書は再生成できません');
    }
    
    // 元請求書をキャンセル
    $invoiceGenerator->updateInvoiceStatus($invoiceId, 'cancelled', '再生成のためキャンセル');
    
    $params = [
        'invoice_type' => $invoice['invoice_type'],
        'period_start' => $invoice['period_start'],
        'period_end' => $invoice['period_end'],
        'due_date' => $invoice['due_date'],
        'target_ids' => []
    ];
    
    // ターゲットID設定
    switch ($invoice['invoice_type']) {
        case 'company_bulk':
            $params['target_ids'] = [$invoice['company_id']];
            break;
        case 'department_bulk':
            $params['target_ids'] = [$invoice['department_id']];
            break;
        case 'individual':
            $params['target_ids'] = [$invoice['user_id']];
            break;
    }
    
    $result = $invoiceGenerator->generateInvoices($params);
    
    echo json_encode([
        'success' => true,
        'data' => $result,
        'message' => '請求書を再生成しました',
        'old_invoice_id' => $invoiceId,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * 日付形式検証
 */
function validateDateFormat($date) {
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && strtotime($date) !== false;
}

/**
 * エラーレスポンス送信
 */
function sendErrorResponse($message, $code = 400) {
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'error' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
?>
