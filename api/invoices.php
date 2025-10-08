<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/SmileyInvoiceGenerator.php';
require_once __DIR__ . '/../classes/SecurityHelper.php';

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            $result = handleGetRequest();
            break;
        default:
            throw new Exception('サポートされていないメソッドです');
    }
    
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => true, 'data' => $result], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    error_log('[Invoices API Error] ' . $e->getMessage());
}

function handleGetRequest() {
    $action = $_GET['action'] ?? 'list';
    
    switch ($action) {
        case 'list':
            return getInvoiceList();
        case 'detail':
            $invoiceId = (int)($_GET['invoice_id'] ?? 0);
            if ($invoiceId === 0) throw new Exception('請求書IDが指定されていません');
            return getInvoiceDetail($invoiceId);
        case 'statistics':
            return getInvoiceStatistics();
        default:
            throw new Exception('不正なアクションです');
    }
}

function getInvoiceList() {
    $db = Database::getInstance();
    
    $filters = [
        'keyword' => $_GET['keyword'] ?? '',
        'status' => $_GET['status'] ?? '',
        'invoice_type' => $_GET['invoice_type'] ?? '',
        'period_start' => $_GET['period_start'] ?? '',
        'period_end' => $_GET['period_end'] ?? '',
        'page' => max(1, (int)($_GET['page'] ?? 1)),
        'limit' => max(1, min(100, (int)($_GET['limit'] ?? 50)))
    ];
    
    $sql = "SELECT 
                i.id, i.invoice_number, i.invoice_type, i.issue_date, i.due_date,
                i.period_start, i.period_end, i.subtotal, i.tax_amount, i.total_amount,
                i.status, i.notes, i.created_at, i.updated_at,
                c.company_name as billing_company_name, c.company_code,
                c.contact_person as billing_contact_person
            FROM invoices i
            LEFT JOIN companies c ON i.company_id = c.id
            WHERE 1=1";
    
    $params = [];
    
    if (!empty($filters['keyword'])) {
        $sql .= " AND (i.invoice_number LIKE :keyword OR c.company_name LIKE :keyword)";
        $params['keyword'] = '%' . $filters['keyword'] . '%';
    }
    if (!empty($filters['status'])) {
        $sql .= " AND i.status = :status";
        $params['status'] = $filters['status'];
    }
    if (!empty($filters['invoice_type'])) {
        $sql .= " AND i.invoice_type = :invoice_type";
        $params['invoice_type'] = $filters['invoice_type'];
    }
    if (!empty($filters['period_start'])) {
        $sql .= " AND i.period_start >= :period_start";
        $params['period_start'] = $filters['period_start'];
    }
    if (!empty($filters['period_end'])) {
        $sql .= " AND i.period_end <= :period_end";
        $params['period_end'] = $filters['period_end'];
    }
    
    $countSql = "SELECT COUNT(*) as total FROM invoices i LEFT JOIN companies c ON i.company_id = c.id WHERE 1=1";
    if (!empty($filters['keyword'])) {
        $countSql .= " AND (i.invoice_number LIKE :keyword OR c.company_name LIKE :keyword)";
    }
    if (!empty($filters['status'])) {
        $countSql .= " AND i.status = :status";
    }
    if (!empty($filters['invoice_type'])) {
        $countSql .= " AND i.invoice_type = :invoice_type";
    }
    if (!empty($filters['period_start'])) {
        $countSql .= " AND i.period_start >= :period_start";
    }
    if (!empty($filters['period_end'])) {
        $countSql .= " AND i.period_end <= :period_end";
    }
    
    $totalCount = (int)$db->fetchColumn($countSql, $params);
    
    $sql .= " ORDER BY i.created_at DESC";
    $offset = ($filters['page'] - 1) * $filters['limit'];
    $sql .= " LIMIT :limit OFFSET :offset";
    $params['limit'] = $filters['limit'];
    $params['offset'] = $offset;
    
    $invoices = $db->fetchAll($sql, $params);
    
    foreach ($invoices as &$invoice) {
        $orderCountSql = "SELECT COUNT(*) FROM invoice_details WHERE invoice_id = :invoice_id";
        $invoice['order_count'] = (int)$db->fetchColumn($orderCountSql, ['invoice_id' => $invoice['id']]);
    }
    
    $totalPages = $filters['limit'] > 0 ? ceil($totalCount / $filters['limit']) : 1;
    
    return [
        'invoices' => $invoices,
        'total_count' => $totalCount,
        'page' => $filters['page'],
        'limit' => $filters['limit'],
        'total_pages' => $totalPages
    ];
}

function getInvoiceDetail($invoiceId) {
    $db = Database::getInstance();
    
    $sql = "SELECT 
                i.*, c.company_name as billing_company_name, c.company_code,
                c.contact_person as billing_contact_person, c.email as billing_email,
                c.company_address as billing_address
            FROM invoices i
            LEFT JOIN companies c ON i.company_id = c.id
            WHERE i.id = :invoice_id";
    
    $invoice = $db->fetch($sql, ['invoice_id' => $invoiceId]);
    
    if (!$invoice) throw new Exception('請求書が見つかりません');
    
    $detailSql = "SELECT id, delivery_date, user_name, product_name, quantity, unit_price, total_amount
                  FROM invoice_details WHERE invoice_id = :invoice_id 
                  ORDER BY delivery_date, user_name";
    
    $invoice['details'] = $db->fetchAll($detailSql, ['invoice_id' => $invoiceId]);
    $invoice['order_count'] = count($invoice['details']);
    $invoice['total_quantity'] = array_sum(array_column($invoice['details'], 'quantity'));
    
    return $invoice;
}

function getInvoiceStatistics() {
    $db = Database::getInstance();
    
    $sql = "SELECT 
                COUNT(*) as total_invoices,
                COALESCE(SUM(total_amount), 0) as total_amount,
                COALESCE(SUM(CASE WHEN status = 'paid' THEN total_amount ELSE 0 END), 0) as paid_amount,
                COALESCE(SUM(CASE WHEN status IN ('issued', 'sent', 'overdue') THEN total_amount ELSE 0 END), 0) as pending_amount
            FROM invoices";
    
    $stats = $db->fetch($sql);
    
    return [
        'basic' => [
            'total_invoices' => (int)$stats['total_invoices'],
            'total_amount' => (float)$stats['total_amount'],
            'paid_amount' => (float)$stats['paid_amount'],
            'pending_amount' => (float)$stats['pending_amount']
        ]
    ];
}
?>
