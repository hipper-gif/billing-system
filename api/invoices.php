<?php
/**
 * 請求書API v5.0仕様準拠
 * Database直接使用、SmileyInvoiceGeneratorは不使用
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/SecurityHelper.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'GET':
            handleGetRequest();
            break;
        case 'POST':
            handlePostRequest();
            break;
        case 'PUT':
            handlePutRequest();
            break;
        case 'DELETE':
            handleDeleteRequest();
            break;
        default:
            throw new Exception('未対応のHTTPメソッドです');
    }

} catch (Exception $e) {
    error_log("Invoices API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * GETリクエスト処理
 */
function handleGetRequest() {
    $action = $_GET['action'] ?? 'list';
    
    switch ($action) {
        case 'list':
            getInvoiceList();
            break;
        case 'detail':
            getInvoiceDetail();
            break;
        case 'statistics':
            getInvoiceStatistics();
            break;
        default:
            throw new Exception('未対応のアクション');
    }
}

/**
 * 請求書一覧取得（v5.0仕様: Database直接使用）
 */
function getInvoiceList() {
    $db = Database::getInstance();
    
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = min(100, max(1, intval($_GET['limit'] ?? 50)));
    $offset = ($page - 1) * $limit;
    
    // フィルター条件構築
    $whereClauses = [];
    $params = [];
    
    if (!empty($_GET['company_id'])) {
        $whereClauses[] = "i.company_id = ?";
        $params[] = intval($_GET['company_id']);
    }
    
    if (!empty($_GET['status'])) {
        $whereClauses[] = "i.status = ?";
        $params[] = $_GET['status'];
    }
    
    if (!empty($_GET['invoice_type'])) {
        $whereClauses[] = "i.invoice_type = ?";
        $params[] = $_GET['invoice_type'];
    }
    
    if (!empty($_GET['period_start'])) {
        $whereClauses[] = "i.period_start >= ?";
        $params[] = $_GET['period_start'];
    }
    
    if (!empty($_GET['period_end'])) {
        $whereClauses[] = "i.period_end <= ?";
        $params[] = $_GET['period_end'];
    }
    
    if (!empty($_GET['keyword'])) {
        $whereClauses[] = "(i.invoice_number LIKE ? OR c.company_name LIKE ?)";
        $keyword = '%' . $_GET['keyword'] . '%';
        $params[] = $keyword;
        $params[] = $keyword;
    }
    
    $whereSQL = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';
    
    // 総件数取得
    $countSQL = "SELECT COUNT(*) as total 
                 FROM invoices i
                 LEFT JOIN companies c ON i.company_id = c.id
                 {$whereSQL}";
    
    $countResult = $db->fetch($countSQL, $params);
    $totalCount = (int)$countResult['total'];
    
    // データ取得
    $sql = "SELECT 
                i.id,
                i.invoice_number,
                i.invoice_type,
                i.issue_date,
                i.due_date,
                i.period_start,
                i.period_end,
                i.subtotal,
                i.tax_amount,
                i.total_amount,
                i.status,
                i.notes,
                i.created_at,
                i.updated_at,
                c.company_name as billing_company_name,
                c.company_code,
                c.contact_person as billing_contact_person
            FROM invoices i
            LEFT JOIN companies c ON i.company_id = c.id
            {$whereSQL}
            ORDER BY i.created_at DESC
            LIMIT ? OFFSET ?";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $invoices = $db->fetchAll($sql, $params);
    
    // 各請求書の注文件数を取得
    foreach ($invoices as &$invoice) {
        $detailCountSQL = "SELECT COUNT(*) as count FROM invoice_details WHERE invoice_id = ?";
        $detailCount = $db->fetch($detailCountSQL, [$invoice['id']]);
        $invoice['order_count'] = (int)$detailCount['count'];
    }
    
    $totalPages = $limit > 0 ? ceil($totalCount / $limit) : 1;
    
    echo json_encode([
        'success' => true,
        'data' => [
            'invoices' => $invoices,
            'total_count' => $totalCount,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => $totalPages
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * 請求書詳細取得（v5.0仕様: Database直接使用）
 */
function getInvoiceDetail() {
    $invoiceId = intval($_GET['invoice_id'] ?? 0);
    
    if (!$invoiceId) {
        throw new Exception('請求書IDが必要です');
    }
    
    $db = Database::getInstance();
    
    // 基本情報取得
    $sql = "SELECT 
                i.*,
                c.company_name as billing_company_name,
                c.company_code,
                c.contact_person as billing_contact_person,
                c.email as billing_email,
                c.company_address as billing_address
            FROM invoices i
            LEFT JOIN companies c ON i.company_id = c.id
            WHERE i.id = ?";
    
    $invoice = $db->fetch($sql, [$invoiceId]);
    
    if (!$invoice) {
        http_response_code(404);
        throw new Exception('請求書が見つかりません');
    }
    
    // 明細取得
    $detailSQL = "SELECT 
                    id,
                    delivery_date,
                    user_name,
                    product_name,
                    quantity,
                    unit_price,
                    total_amount
                  FROM invoice_details
                  WHERE invoice_id = ?
                  ORDER BY delivery_date, user_name";
    
    $invoice['details'] = $db->fetchAll($detailSQL, [$invoiceId]);
    
    // 統計計算
    $invoice['order_count'] = count($invoice['details']);
    $invoice['total_quantity'] = array_sum(array_column($invoice['details'], 'quantity'));
    
    echo json_encode([
        'success' => true,
        'data' => $invoice,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * 統計情報取得（v5.0仕様: Database直接使用）
 */
function getInvoiceStatistics() {
    $db = Database::getInstance();
    
    $sql = "SELECT 
                COUNT(*) as total_invoices,
                COALESCE(SUM(total_amount), 0) as total_amount,
                COALESCE(SUM(CASE WHEN status = 'paid' THEN total_amount ELSE 0 END), 0) as paid_amount,
                COALESCE(SUM(CASE WHEN status IN ('issued', 'sent', 'overdue') THEN total_amount ELSE 0 END), 0) as pending_amount
            FROM invoices
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)";
    
    $stats = $db->fetch($sql);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'basic' => [
                'total_invoices' => (int)$stats['total_invoices'],
                'total_amount' => (float)$stats['total_amount'],
                'paid_amount' => (float)$stats['paid_amount'],
                'pending_amount' => (float)$stats['pending_amount']
            ]
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * POSTリクエスト処理
 */
function handlePostRequest() {
    // 請求書生成機能は別APIで実装
    throw new Exception('請求書生成はinvoice_generate APIを使用してください');
}

/**
 * PUTリクエスト処理
 */
function handlePutRequest() {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? 'update_status';
    
    if ($action === 'update_status') {
        updateInvoiceStatus($input);
    } else {
        throw new Exception('未対応のアクション');
    }
}

/**
 * ステータス更新（v5.0仕様: Database直接使用）
 */
function updateInvoiceStatus($input) {
    $invoiceId = intval($input['invoice_id'] ?? 0);
    $status = $input['status'] ?? '';
    $notes = $input['notes'] ?? '';
    
    if (!$invoiceId || !$status) {
        throw new Exception('請求書IDとステータスが必要です');
    }
    
    $db = Database::getInstance();
    
    $sql = "UPDATE invoices 
            SET status = ?, 
                notes = ?,
                updated_at = NOW()
            WHERE id = ?";
    
    $db->execute($sql, [$status, $notes, $invoiceId]);
    
    echo json_encode([
        'success' => true,
        'message' => 'ステータスを更新しました',
        'invoice_id' => $invoiceId,
        'status' => $status
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * DELETEリクエスト処理
 */
function handleDeleteRequest() {
    $input = json_decode(file_get_contents('php://input'), true);
    $invoiceId = intval($input['invoice_id'] ?? 0);
    
    if (!$invoiceId) {
        throw new Exception('請求書IDが必要です');
    }
    
    $db = Database::getInstance();
    
    // 論理削除（キャンセル）
    $sql = "UPDATE invoices 
            SET status = 'cancelled', 
                updated_at = NOW()
            WHERE id = ?";
    
    $db->execute($sql, [$invoiceId]);
    
    echo json_encode([
        'success' => true,
        'message' => '請求書を削除しました',
        'invoice_id' => $invoiceId
    ], JSON_UNESCAPED_UNICODE);
}
?>
