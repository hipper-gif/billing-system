<?php
/**
 * 請求書生成・管理API
 * 
 * @version 3.0.0 - v5.0仕様準拠
 * @updated 2025-10-06
 */
/**
 * デバッグ用：エラー表示を強制有効化
 * TODO: 問題解決後に削除
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');


// v5.0仕様
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/SmileyInvoiceGenerator.php';
require_once __DIR__ . '/../classes/SecurityHelper.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

SecurityHelper::setJsonHeaders();

try {
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
    error_log("Invoices API Error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_type' => get_class($e),
        'file' => basename($e->getFile()),
        'line' => $e->getLine(),
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
}

function handleGetRequest($invoiceGenerator) {
    $action = $_GET['action'] ?? 'list';
    
    switch ($action) {
        case 'list':
            getInvoiceList($invoiceGenerator);
            break;
        case 'detail':
            getInvoiceDetail($invoiceGenerator);
            break;
        case 'statistics':
            getInvoiceStatistics();
            break;
        default:
            throw new Exception('未対応のアクション: ' . $action);
    }
}

function handlePostRequest($invoiceGenerator) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('不正なJSONデータ: ' . json_last_error_msg());
    }
    
    $action = $input['action'] ?? 'generate';
    
    error_log("POST Action: {$action}");
    error_log("POST Data: " . json_encode($input));
    
    switch ($action) {
        case 'generate':
            generateInvoices($invoiceGenerator, $input);
            break;
        default:
            throw new Exception('未対応のアクション: ' . $action);
    }
}

function handlePutRequest($invoiceGenerator) {
    $input = json_decode(file_get_contents('php://input'), true);
    updateInvoiceStatus($invoiceGenerator, $input);
}

function handleDeleteRequest($invoiceGenerator) {
    $input = json_decode(file_get_contents('php://input'), true);
    $invoiceId = $input['invoice_id'] ?? null;
    
    if (!$invoiceId) {
        throw new Exception('請求書IDが必要です');
    }
    
    $invoiceGenerator->deleteInvoice($invoiceId);
    
    echo json_encode([
        'success' => true,
        'message' => '請求書を削除しました',
        'invoice_id' => $invoiceId
    ], JSON_UNESCAPED_UNICODE);
}

function getInvoiceList($invoiceGenerator) {
    $page = intval($_GET['page'] ?? 1);
    $limit = min(intval($_GET['limit'] ?? 50), 100);
    
    $filters = [];
    if (!empty($_GET['company_id'])) $filters['company_id'] = intval($_GET['company_id']);
    if (!empty($_GET['status'])) $filters['status'] = $_GET['status'];
    if (!empty($_GET['invoice_type'])) $filters['invoice_type'] = $_GET['invoice_type'];
    if (!empty($_GET['period_start'])) $filters['period_start'] = $_GET['period_start'];
    if (!empty($_GET['period_end'])) $filters['period_end'] = $_GET['period_end'];
    
    $result = $invoiceGenerator->getInvoiceList($filters, $page, $limit);
    
    echo json_encode([
        'success' => true,
        'data' => $result,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
}

function getInvoiceDetail($invoiceGenerator) {
    $invoiceId = intval($_GET['invoice_id'] ?? 0);
    
    if (!$invoiceId) {
        throw new Exception('請求書IDが必要です');
    }
    
    $invoice = $invoiceGenerator->getInvoiceData($invoiceId);
    
    if (!$invoice) {
        http_response_code(404);
        throw new Exception('請求書が見つかりません');
    }
    
    echo json_encode([
        'success' => true,
        'data' => $invoice,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
}

function generateInvoices($invoiceGenerator, $input) {
    // 必須パラメータ検証
    if (empty($input['invoice_type'])) throw new Exception('invoice_typeは必須です');
    if (empty($input['period_start'])) throw new Exception('period_startは必須です');
    if (empty($input['period_end'])) throw new Exception('period_endは必須です');
    
    $params = [
        'invoice_type' => $input['invoice_type'],
        'period_start' => $input['period_start'],
        'period_end' => $input['period_end'],
        'due_date' => $input['due_date'] ?? null,
        'targets' => !empty($input['targets']) ? array_map('intval', $input['targets']) : [],
        'auto_pdf' => !empty($input['auto_pdf'])
    ];
    
    error_log("Generate Params: " . json_encode($params));
    
    // 日付検証
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $params['period_start'])) {
        throw new Exception('period_startの形式が不正です');
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $params['period_end'])) {
        throw new Exception('period_endの形式が不正です');
    }
    
    // 対象チェック
    if (empty($params['targets'])) {
        throw new Exception('対象を選択してください');
    }
    
    $result = $invoiceGenerator->generateInvoices($params);
    
    echo json_encode([
        'success' => true,
        'data' => $result,
        'generated_count' => $result['total_invoices'] ?? 0,
        'invoices' => $result['invoices'] ?? [],
        'message' => "請求書を生成しました",
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
}

function updateInvoiceStatus($invoiceGenerator, $input) {
    $invoiceId = intval($input['invoice_id'] ?? 0);
    $status = $input['status'] ?? '';
    $notes = $input['notes'] ?? '';
    
    if (!$invoiceId || !$status) {
        throw new Exception('請求書IDとステータスが必要です');
    }
    
    $invoiceGenerator->updateInvoiceStatus($invoiceId, $status, $notes);
    
    echo json_encode([
        'success' => true,
        'message' => 'ステータスを更新しました',
        'invoice_id' => $invoiceId,
        'status' => $status
    ], JSON_UNESCAPED_UNICODE);
}

function getInvoiceStatistics() {
    $db = Database::getInstance();
    
    $sql = "SELECT 
                COUNT(*) as total_invoices,
                COALESCE(SUM(total_amount), 0) as total_amount,
                COALESCE(SUM(CASE WHEN status = 'paid' THEN total_amount ELSE 0 END), 0) as paid_amount,
                COALESCE(SUM(CASE WHEN status != 'paid' THEN total_amount ELSE 0 END), 0) as pending_amount
            FROM invoices
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)";
    
    $stats = $db->fetch($sql);
    
    echo json_encode([
        'success' => true,
        'data' => $stats,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
}
