<?php
/**
 * 請求書API (エラー修正版)
 * 
 * 修正内容:
 * - Database Singleton パターン対応
 * - SmileyInvoiceGenerator エラー修正版使用
 * - エラーハンドリング強化
 */

require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/SmileyInvoiceGenerator.php';

header('Content-Type: application/json; charset=utf-8');

// エラーハンドリング関数
function handlePostRequest($invoiceGenerator) {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? 'generate';
    
    switch ($action) {
        case 'generate':
            return generateInvoices($invoiceGenerator, $input);
            
        case 'update_status':
            return updateInvoiceStatus($input);
            
        default:
            throw new Exception('不正なアクションです: ' . $action);
    }
}

function handleGetRequest() {
    $action = $_GET['action'] ?? 'list';
    
    switch ($action) {
        case 'list':
            return getInvoiceList();
            
        case 'detail':
            return getInvoiceDetail($_GET['id'] ?? 0);
            
        case 'summary':
            return getInvoiceSummary();
            
        default:
            throw new Exception('不正なアクションです: ' . $action);
    }
}

function generateInvoices($invoiceGenerator, $input) {
    // 入力値検証
    $params = [
        'invoice_type' => $input['invoice_type'] ?? 'company_bulk',
        'period_start' => $input['period_start'] ?? '',
        'period_end' => $input['period_end'] ?? '',
        'due_date' => $input['due_date'] ?? null,
        'target_ids' => $input['target_ids'] ?? [],
        'auto_generate_pdf' => $input['auto_generate_pdf'] ?? true
    ];
    
    // 必須項目チェック
    if (empty($params['period_start']) || empty($params['period_end'])) {
        throw new Exception('請求期間の開始日と終了日は必須です');
    }
    
    // 日付妥当性チェック
    if (!validateDate($params['period_start']) || !validateDate($params['period_end'])) {
        throw new Exception('日付形式が正しくありません（YYYY-MM-DD形式で入力してください）');
    }
    
    if ($params['period_start'] > $params['period_end']) {
        throw new Exception('開始日は終了日より前である必要があります');
    }
    
    // 請求書生成実行
    $result = $invoiceGenerator->generateInvoices($params);
    
    echo json_encode([
        'success' => true,
        'message' => "{$result['generated_invoices']}件の請求書を生成しました（総額: ¥" . number_format($result['total_amount']) . "）",
        'data' => $result
    ], JSON_UNESCAPED_UNICODE);
}

function updateInvoiceStatus($input) {
    $db = Database::getInstance();
    
    $invoiceId = $input['invoice_id'] ?? 0;
    $status = $input['status'] ?? '';
    
    if (empty($invoiceId) || empty($status)) {
        throw new Exception('請求書IDとステータスは必須です');
    }
    
    // 有効なステータスチェック
    $validStatuses = ['draft', 'issued', 'sent', 'paid', 'overdue', 'cancelled'];
    if (!in_array($status, $validStatuses)) {
        throw new Exception('無効なステータスです');
    }
    
    $sql = "UPDATE invoices SET status = ?, updated_at = NOW() WHERE id = ?";
    $stmt = $db->query($sql, [$status, $invoiceId]);
    
    if ($stmt->rowCount() === 0) {
        throw new Exception('指定された請求書が見つかりません');
    }
    
    echo json_encode([
        'success' => true,
        'message' => '請求書ステータスを更新しました'
    ], JSON_UNESCAPED_UNICODE);
}

function getInvoiceList() {
    $db = Database::getInstance();
    
    // フィルター条件取得
    $filters = [
        'status' => $_GET['status'] ?? null,
        'company_id' => $_GET['company_id'] ?? null,
        'date_from' => $_GET['date_from'] ?? null,
        'date_to' => $_GET['date_to'] ?? null,
        'search' => $_GET['search'] ?? null
    ];
    
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(100, max(10, (int)($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;
    
    // WHERE条件構築
    $where = ['1=1'];
    $params = [];
    
    if (!empty($filters['status'])) {
        $where[] = 'status = ?';
        $params[] = $filters['status'];
    }
    
    if (!empty($filters['company_id'])) {
        $where[] = 'company_id = ?';
        $params[] = $filters['company_id'];
    }
    
    if (!empty($filters['date_from'])) {
        $where[] = 'invoice_date >= ?';
        $params[] = $filters['date_from'];
    }
    
    if (!empty($filters['date_to'])) {
        $where[] = 'invoice_date <= ?';
        $params[] = $filters['date_to'];
    }
    
    if (!empty($filters['search'])) {
        $where[] = '(invoice_number LIKE ? OR company_name LIKE ? OR user_name LIKE ?)';
        $searchParam = '%' . $filters['search'] . '%';
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    $whereClause = implode(' AND ', $where);
    
    // 件数取得
    $countSql = "SELECT COUNT(*) FROM invoices WHERE {$whereClause}";
    $stmt = $db->query($countSql, $params);
    $totalCount = $stmt->fetchColumn();
    
    // データ取得
    $sql = "SELECT 
                id, invoice_number, invoice_type, 
                user_name, company_name, department_name,
                invoice_date, due_date, period_start, period_end,
                subtotal, tax_amount, total_amount, status,
                created_at, updated_at
            FROM invoices 
            WHERE {$whereClause}
            ORDER BY created_at DESC 
            LIMIT {$limit} OFFSET {$offset}";
    
    $stmt = $db->query($sql, $params);
    $invoices = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'data' => [
            'invoices' => $invoices,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total_count' => $totalCount,
                'total_pages' => ceil($totalCount / $limit)
            ]
        ]
    ], JSON_UNESCAPED_UNICODE);
}

function getInvoiceDetail($invoiceId) {
    $db = Database::getInstance();
    
    if (empty($invoiceId)) {
        throw new Exception('請求書IDが指定されていません');
    }
    
    // 請求書基本情報取得
    $sql = "SELECT * FROM invoices WHERE id = ?";
    $stmt = $db->query($sql, [$invoiceId]);
    $invoice = $stmt->fetch();
    
    if (!$invoice) {
        throw new Exception('指定された請求書が見つかりません');
    }
    
    // 請求書明細取得
    $sql = "SELECT * FROM invoice_details WHERE invoice_id = ? ORDER BY delivery_date, user_code";
    $stmt = $db->query($sql, [$invoiceId]);
    $details = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'data' => [
            'invoice' => $invoice,
            'details' => $details
        ]
    ], JSON_UNESCAPED_UNICODE);
}

function getInvoiceSummary() {
    $db = Database::getInstance();
    
    // 全体サマリー取得
    $sql = "SELECT 
                COUNT(*) as total_invoices,
                SUM(total_amount) as total_amount,
                SUM(CASE WHEN status = 'draft' THEN total_amount ELSE 0 END) as draft_amount,
                SUM(CASE WHEN status = 'issued' THEN total_amount ELSE 0 END) as issued_amount,
                SUM(CASE WHEN status = 'sent' THEN total_amount ELSE 0 END) as sent_amount,
                SUM(CASE WHEN status = 'paid' THEN total_amount ELSE 0 END) as paid_amount,
                SUM(CASE WHEN status = 'overdue' THEN total_amount ELSE 0 END) as overdue_amount,
                COUNT(CASE WHEN status = 'overdue' THEN 1 END) as overdue_count
            FROM invoices 
            WHERE invoice_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)";
    
    $stmt = $db->query($sql);
    $summary = $stmt->fetch();
    
    // ステータス別件数
    $sql = "SELECT status, COUNT(*) as count, SUM(total_amount) as amount 
            FROM invoices 
            WHERE invoice_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
            GROUP BY status";
    
    $stmt = $db->query($sql);
    $statusBreakdown = $stmt->fetchAll();
    
    // 月別推移
    $sql = "SELECT 
                DATE_FORMAT(invoice_date, '%Y-%m') as month,
                COUNT(*) as count,
                SUM(total_amount) as amount
            FROM invoices 
            WHERE invoice_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
            GROUP BY DATE_FORMAT(invoice_date, '%Y-%m')
            ORDER BY month";
    
    $stmt = $db->query($sql);
    $monthlyTrend = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'data' => [
            'summary' => $summary,
            'status_breakdown' => $statusBreakdown,
            'monthly_trend' => $monthlyTrend
        ]
    ], JSON_UNESCAPED_UNICODE);
}

// ユーティリティ関数
function validateDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

// メイン処理
try {
    // Database Singleton インスタンス取得
    $db = Database::getInstance();
    
    // SmileyInvoiceGenerator インスタンス作成（修正版使用）
    $invoiceGenerator = new SmileyInvoiceGenerator($db);
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        handlePostRequest($invoiceGenerator);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
        handleGetRequest();
        
    } else {
        throw new Exception('サポートされていないHTTPメソッドです');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'debug' => [
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ], JSON_UNESCAPED_UNICODE);
}
?>
