<?php
/**
 * 請求書API v5.0仕様準拠（修正版）
 * Database直接使用、請求書生成はSmileyInvoiceGeneratorを使用
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/SecurityHelper.php';
require_once __DIR__ . '/../classes/SmileyInvoiceGenerator.php';

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
        case 'pdf':
            outputInvoicePDF();
            break;
        default:
            throw new Exception('未対応のアクション');
    }
}

/**
 * 請求書一覧取得（実際のテーブル構造に対応）
 */
function getInvoiceList() {
    $db = Database::getInstance();
    
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = min(100, max(1, intval($_GET['limit'] ?? 50)));
    $offset = ($page - 1) * $limit;
    
    // フィルター条件構築
    $whereClauses = [];
    $params = [];
    
    if (!empty($_GET['company_name'])) {
        $whereClauses[] = "i.company_name LIKE ?";
        $params[] = '%' . $_GET['company_name'] . '%';
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
        $whereClauses[] = "(i.invoice_number LIKE ? OR i.company_name LIKE ? OR i.user_name LIKE ?)";
        $keyword = '%' . $_GET['keyword'] . '%';
        $params[] = $keyword;
        $params[] = $keyword;
        $params[] = $keyword;
    }
    
    $whereSQL = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';
    
    // 総件数取得
    $countSQL = "SELECT COUNT(*) as total FROM invoices i {$whereSQL}";
    $countResult = $db->fetch($countSQL, $params);
    $totalCount = (int)$countResult['total'];
    
    // データ取得
    $sql = "SELECT 
                i.id,
                i.invoice_number,
                i.invoice_type,
                i.invoice_date as issue_date,
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
                i.company_name as billing_company_name,
                i.user_name,
                i.department
            FROM invoices i
            {$whereSQL}
            ORDER BY i.created_at DESC
            LIMIT ? OFFSET ?";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $invoices = $db->fetchAll($sql, $params);
    
    // 各請求書の注文件数を取得（invoice_detailsテーブルが存在する場合）
    foreach ($invoices as &$invoice) {
        // invoice_detailsテーブルの存在確認
        try {
            $detailCountSQL = "SELECT COUNT(*) as count FROM invoice_details WHERE invoice_id = ?";
            $detailCount = $db->fetch($detailCountSQL, [$invoice['id']]);
            $invoice['order_count'] = (int)($detailCount['count'] ?? 0);
        } catch (Exception $e) {
            // invoice_detailsテーブルが存在しない場合は0
            $invoice['order_count'] = 0;
        }
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
 * 請求書詳細取得（実際のテーブル構造に対応）
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
                i.invoice_date as issue_date
            FROM invoices i
            WHERE i.id = ?";
    
    $invoice = $db->fetch($sql, [$invoiceId]);
    
    if (!$invoice) {
        http_response_code(404);
        throw new Exception('請求書が見つかりません');
    }
    
    // 明細取得（invoice_detailsテーブルが存在する場合）
    try {
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
    } catch (Exception $e) {
        // invoice_detailsテーブルが存在しない場合は空配列
        $invoice['details'] = [];
    }
    
    // 統計計算
    $invoice['order_count'] = count($invoice['details']);
    $invoice['total_quantity'] = array_sum(array_column($invoice['details'], 'quantity'));
    
    // company_nameをbilling_company_nameとしても返す（互換性のため）
    $invoice['billing_company_name'] = $invoice['company_name'];
    
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
 * POSTリクエスト処理（修正版）
 */
function handlePostRequest() {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? 'generate';
    
    if ($action === 'generate') {
        generateInvoices($input);
    } else {
        throw new Exception('未対応のアクション');
    }
}

/**
 * 請求書生成（新規実装）
 * pages/invoice_generate.php からの呼び出しに対応
 */
function generateInvoices($input) {
    // 必須パラメータ検証
    $requiredParams = ['invoice_type', 'period_start', 'period_end'];
    foreach ($requiredParams as $param) {
        if (empty($input[$param])) {
            throw new Exception("{$param}は必須です");
        }
    }

    // 日付形式検証
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $input['period_start']) ||
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $input['period_end'])) {
        throw new Exception('日付形式が正しくありません（YYYY-MM-DD形式で入力してください）');
    }

    // SmileyInvoiceGeneratorインスタンス作成
    try {
        $generator = new SmileyInvoiceGenerator();
    } catch (Exception $e) {
        error_log("Failed to create SmileyInvoiceGenerator: " . $e->getMessage());
        throw new Exception("請求書生成エンジンの初期化に失敗しました: " . $e->getMessage());
    }
    
    // パラメータ構築
    $params = [
        'invoice_type' => $input['invoice_type'],
        'period_start' => $input['period_start'],
        'period_end' => $input['period_end'],
        'due_date' => $input['due_date'] ?? null,
        'target_ids' => $input['targets'] ?? [],
        'auto_generate_pdf' => $input['auto_pdf'] ?? false
    ];
    
    // 支払期限が未設定の場合、期間終了日+30日を設定
    if (empty($params['due_date'])) {
        $endDate = new DateTime($params['period_end']);
        $endDate->modify('+30 days');
        $params['due_date'] = $endDate->format('Y-m-d');
    }
    
    // 請求書生成実行
    try {
        $result = $generator->generateInvoices($params);
        
        // レスポンス構築
        $response = [
            'success' => true,
            'generated_count' => $result['total_invoices'] ?? count($result['invoices'] ?? []),
            'invoices' => $result['invoices'] ?? [],
            'message' => '請求書を生成しました',
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        // エラーがある場合は追加
        if (!empty($result['errors'])) {
            $response['errors'] = $result['errors'];
        }
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        error_log("Invoice generation error: " . $e->getMessage());
        throw new Exception('請求書生成に失敗しました: ' . $e->getMessage());
    }
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

/**
 * 請求書PDF出力
 */
function outputInvoicePDF() {
    $invoiceId = intval($_GET['invoice_id'] ?? 0);
    
    if (!$invoiceId) {
        throw new Exception('請求書IDが必要です');
    }
    
    // SmileyInvoiceGenerator を使用してPDF出力
    $generator = new SmileyInvoiceGenerator();
    $generator->outputPDF($invoiceId);
    
    exit; // PDF出力後は終了
}
?>
