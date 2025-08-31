<?php
/**
 * 領収書管理API
 * Smiley配食事業 集金管理システム
 * 
 * エンドポイント:
 * GET /api/receipts.php - 領収書一覧・詳細・統計取得
 * POST /api/receipts.php - 領収書生成・再発行
 */

// セキュリティヘッダー設定
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// CORS設定（必要に応じて調整）
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// OPTIONS リクエスト対応
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/ReceiptGenerator.php';
require_once '../classes/SecurityHelper.php';

try {
    // データベース接続
    $db = new Database();
    $receiptGenerator = new ReceiptGenerator($db);
    
    // リクエストメソッド別処理
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            handleGetRequest($receiptGenerator);
            break;
            
        case 'POST':
            handlePostRequest($receiptGenerator);
            break;
            
        default:
            throw new Exception('サポートされていないHTTPメソッドです');
    }
    
} catch (Exception $e) {
    // エラーログ記録
    error_log("Receipts API Error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'timestamp' => date('c')
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * GET リクエスト処理
 */
function handleGetRequest($receiptGenerator) {
    $action = $_GET['action'] ?? 'list';
    
    switch ($action) {
        case 'list':
            handleReceiptList($receiptGenerator);
            break;
            
        case 'detail':
            handleReceiptDetail($receiptGenerator);
            break;
            
        case 'statistics':
            handleReceiptStatistics($receiptGenerator);
            break;
            
        case 'pdf':
            handleReceiptPDF($receiptGenerator);
            break;
            
        case 'export':
            handleReceiptExport($receiptGenerator);
            break;
            
        case 'search':
            handleReceiptSearch($receiptGenerator);
            break;
            
        default:
            throw new Exception('無効なアクションです: ' . $action);
    }
}

/**
 * POST リクエスト処理
 */
function handlePostRequest($receiptGenerator) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('無効なJSONデータです');
    }
    
    $action = $input['action'] ?? '';
    
    switch ($action) {
        case 'generate':
            handleReceiptGenerate($receiptGenerator, $input);
            break;
            
        case 'advance_generate':
            handleAdvanceReceiptGenerate($receiptGenerator, $input);
            break;
            
        case 'split_generate':
            handleSplitReceiptGenerate($receiptGenerator, $input);
            break;
            
        case 'reissue':
            handleReceiptReissue($receiptGenerator, $input);
            break;
            
        default:
            throw new Exception('無効なアクションです: ' . $action);
    }
}

/**
 * 領収書一覧取得
 */
function handleReceiptList($receiptGenerator) {
    $filters = [
        'invoice_id' => $_GET['invoice_id'] ?? null,
        'payment_id' => $_GET['payment_id'] ?? null,
        'receipt_type' => $_GET['receipt_type'] ?? null,
        'status' => $_GET['status'] ?? null,
        'date_from' => $_GET['date_from'] ?? null,
        'date_to' => $_GET['date_to'] ?? null,
        'stamp_required' => $_GET['stamp_required'] ?? null,
        'recipient_name' => $_GET['recipient_name'] ?? null
    ];
    
    // 空の値を除去
    $filters = array_filter($filters, function($value) {
        return $value !== null && $value !== '';
    });
    
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = max(1, min(100, (int)($_GET['limit'] ?? 50)));
    
    $result = $receiptGenerator->getReceiptList($filters, $page, $limit);
    
    echo json_encode([
        'success' => true,
        'data' => $result,
        'timestamp' => date('c')
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * 領収書詳細取得
 */
function handleReceiptDetail($receiptGenerator) {
    $receiptId = $_GET['receipt_id'] ?? null;
    
    if (!$receiptId || !is_numeric($receiptId)) {
        throw new Exception('有効な領収書IDを指定してください');
    }
    
    $receipt = $receiptGenerator->getReceiptDetail($receiptId);
    
    echo json_encode([
        'success' => true,
        'data' => $receipt,
        'timestamp' => date('c')
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * 領収書統計情報取得
 */
function handleReceiptStatistics($receiptGenerator) {
    $month = $_GET['month'] ?? null;
    
    // 月の形式検証
    if ($month && !preg_match('/^\d{4}-\d{2}$/', $month)) {
        throw new Exception('月は YYYY-MM の形式で指定してください');
    }
    
    $statistics = $receiptGenerator->getReceiptStatistics($month);
    
    echo json_encode([
        'success' => true,
        'data' => $statistics,
        'timestamp' => date('c')
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * 領収書PDF出力
 */
function handleReceiptPDF($receiptGenerator) {
    $receiptId = $_GET['receipt_id'] ?? null;
    
    if (!$receiptId || !is_numeric($receiptId)) {
        throw new Exception('有効な領収書IDを指定してください');
    }
    
    $receipt = $receiptGenerator->getReceiptDetail($receiptId);
    
    if (empty($receipt['file_path']) || !file_exists($receipt['file_path'])) {
        throw new Exception('領収書PDFファイルが見つかりません');
    }
    
    $filename = basename($receipt['file_path']);
    $fileExtension = pathinfo($filename, PATHINFO_EXTENSION);
    
    // ファイルタイプ判定
    if ($fileExtension === 'pdf') {
        header('Content-Type: application/pdf');
    } else {
        header('Content-Type: text/html; charset=utf-8');
    }
    
    header('Content-Disposition: inline; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($receipt['file_path']));
    
    // ファイル出力
    readfile($receipt['file_path']);
    exit;
}

/**
 * 領収書一括出力
 */
function handleReceiptExport($receiptGenerator) {
    $dateFrom = $_GET['date_from'] ?? date('Y-m-01');
    $dateTo = $_GET['date_to'] ?? date('Y-m-t');
    
    // 日付形式検証
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) || 
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
        throw new Exception('日付は YYYY-MM-DD の形式で指定してください');
    }
    
    $receipts = $receiptGenerator->getReceiptsForExport($dateFrom, $dateTo);
    
    if (empty($receipts)) {
        throw new Exception('指定期間に領収書が見つかりません');
    }
    
    // CSV形式で出力
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="receipts_' . $dateFrom . '_' . $dateTo . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // BOM付きUTF-8出力
    fprintf($output, "\xEF\xBB\xBF");
    
    // ヘッダー行
    fputcsv($output, [
        '領収書番号',
        '発行日',
        '受領者名',
        '金額',
        '消費税額',
        '但し書き',
        '収入印紙',
        'タイプ',
        'ステータス',
        '請求書番号',
        '支払日',
        '支払方法'
    ]);
    
    // データ行
    foreach ($receipts as $receipt) {
        fputcsv($output, [
            $receipt['receipt_number'],
            $receipt['issue_date'],
            $receipt['recipient_name'],
            $receipt['amount'],
            $receipt['tax_amount'],
            $receipt['purpose'],
            $receipt['stamp_required'] ? '要' : '不要',
            getReceiptTypeText($receipt['receipt_type']),
            getReceiptStatusText($receipt['status']),
            $receipt['invoice_number'] ?? '',
            $receipt['payment_date'] ?? '',
            $receipt['payment_method'] ?? ''
        ]);
    }
    
    fclose($output);
    exit;
}

/**
 * 領収書検索
 */
function handleReceiptSearch($receiptGenerator) {
    $searchParams = [
        'receipt_number' => $_GET['receipt_number'] ?? null,
        'recipient_name' => $_GET['recipient_name'] ?? null,
        'amount_min' => $_GET['amount_min'] ?? null,
        'amount_max' => $_GET['amount_max'] ?? null,
        'purpose' => $_GET['purpose'] ?? null
    ];
    
    // 空の値を除去
    $searchParams = array_filter($searchParams, function($value) {
        return $value !== null && $value !== '';
    });
    
    $result = $receiptGenerator->searchReceipts($searchParams);
    
    echo json_encode([
        'success' => true,
        'data' => $result,
        'timestamp' => date('c')
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * 領収書生成
 */
function handleReceiptGenerate($receiptGenerator, $input) {
    // 必須パラメータ検証
    $requiredFields = ['amount', 'recipient_name'];
    foreach ($requiredFields as $field) {
        if (empty($input[$field])) {
            throw new Exception("必須項目が不足しています: {$field}");
        }
    }
    
    // 入力値サニタイズ
    $params = [
        'invoice_id' => !empty($input['invoice_id']) ? (int)$input['invoice_id'] : null,
        'payment_id' => !empty($input['payment_id']) ? (int)$input['payment_id'] : null,
        'amount' => (float)$input['amount'],
        'recipient_name' => SecurityHelper::sanitizeInput($input['recipient_name']),
        'purpose' => SecurityHelper::sanitizeInput($input['purpose'] ?? 'お弁当代として'),
        'receipt_type' => $input['receipt_type'] ?? 'payment',
        'issue_date' => $input['issue_date'] ?? date('Y-m-d'),
        'notes' => !empty($input['notes']) ? SecurityHelper::sanitizeInput($input['notes']) : null
    ];
    
    $result = $receiptGenerator->generateReceipt($params);
    
    echo json_encode([
        'success' => true,
        'message' => '領収書を生成しました',
        'data' => $result,
        'timestamp' => date('c')
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * 事前領収書生成
 */
function handleAdvanceReceiptGenerate($receiptGenerator, $input) {
    // 必須パラメータ検証
    $requiredFields = ['amount', 'recipient_name', 'company_id'];
    foreach ($requiredFields as $field) {
        if (empty($input[$field])) {
            throw new Exception("必須項目が不足しています: {$field}");
        }
    }
    
    // 入力値サニタイズ
    $params = [
        'company_id' => (int)$input['company_id'],
        'amount' => (float)$input['amount'],
        'recipient_name' => SecurityHelper::sanitizeInput($input['recipient_name']),
        'purpose' => SecurityHelper::sanitizeInput($input['purpose'] ?? 'お弁当代として（事前発行）'),
        'issue_date' => $input['issue_date'] ?? date('Y-m-d')
    ];
    
    $result = $receiptGenerator->generateAdvanceReceipt($params);
    
    echo json_encode([
        'success' => true,
        'message' => '事前領収書を生成しました',
        'data' => $result,
        'timestamp' => date('c')
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * 分割領収書生成
 */
function handleSplitReceiptGenerate($receiptGenerator, $input) {
    // 必須パラメータ検証
    $requiredFields = ['amount', 'recipient_name'];
    foreach ($requiredFields as $field) {
        if (empty($input[$field])) {
            throw new Exception("必須項目が不足しています: {$field}");
        }
    }
    
    // 入力値サニタイズ
    $params = [
        'invoice_id' => !empty($input['invoice_id']) ? (int)$input['invoice_id'] : null,
        'payment_id' => !empty($input['payment_id']) ? (int)$input['payment_id'] : null,
        'amount' => (float)$input['amount'],
        'recipient_name' => SecurityHelper::sanitizeInput($input['recipient_name']),
        'purpose' => SecurityHelper::sanitizeInput($input['purpose'] ?? 'お弁当代として'),
        'split_amounts' => $input['split_amounts'] ?? [],
        'issue_date' => $input['issue_date'] ?? date('Y-m-d'),
        'notes' => !empty($input['notes']) ? SecurityHelper::sanitizeInput($input['notes']) : null
    ];
    
    $result = $receiptGenerator->generateSplitReceipts($params);
    
    echo json_encode([
        'success' => true,
        'message' => '分割領収書を生成しました',
        'data' => $result,
        'timestamp' => date('c')
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * 領収書再発行
 */
function handleReceiptReissue($receiptGenerator, $input) {
    $receiptId = $input['receipt_id'] ?? null;
    
    if (!$receiptId || !is_numeric($receiptId)) {
        throw new Exception('有効な領収書IDを指定してください');
    }
    
    $result = $receiptGenerator->reissueReceipt($receiptId);
    
    echo json_encode([
        'success' => true,
        'message' => '領収書を再発行しました',
        'data' => $result,
        'timestamp' => date('c')
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * 領収書タイプのテキスト変換
 */
function getReceiptTypeText($type) {
    $types = [
        'advance' => '事前領収書',
        'payment' => '正式領収書',
        'split' => '分割領収書'
    ];
    
    return $types[$type] ?? $type;
}

/**
 * 領収書ステータスのテキスト変換
 */
function getReceiptStatusText($status) {
    $statuses = [
        'draft' => '下書き',
        'issued' => '発行済み',
        'delivered' => '配達済み'
    ];
    
    return $statuses[$status] ?? $status;
}

/**
 * セキュリティヘルパークラス（簡易版）
 */
if (!class_exists('SecurityHelper')) {
    class SecurityHelper {
        public static function sanitizeInput($input) {
            return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
        }
        
        public static function validateCSRFToken($token) {
            // CSRF トークン検証の実装
            // 実際の実装では、セッションに保存されたトークンと比較
            return true; // 簡易実装
        }
    }
}
?>
