<?php
/**
 * Smiley配食事業 集金管理システム
 * 支払い管理API - 集金業務特化版
 * 
 * @version 5.0
 * @date 2025-09-19
 * @purpose 集金管理業務の完全API化・効率化
 */

// エラー報告設定
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// セキュリティヘッダー設定
header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// CORS設定（必要に応じて）
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// OPTIONSリクエスト対応
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 必要なクラスを読み込み
require_once __DIR__ . '/../classes/PaymentManager.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/SecurityHelper.php';

/**
 * APIレスポンス送信
 */
function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    
    // レスポンス形式統一
    if (!isset($data['success'])) {
        if ($statusCode >= 400) {
            $data = ['success' => false, 'error' => $data['error'] ?? 'エラーが発生しました', 'data' => null];
        } else {
            $data = ['success' => true, 'data' => $data, 'error' => null];
        }
    }
    
    // タイムスタンプ追加
    $data['timestamp'] = date('Y-m-d H:i:s');
    $data['request_id'] = uniqid('req_', true);
    
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/**
 * エラーレスポンス送信
 */
function sendError($message, $statusCode = 400, $details = null) {
    error_log("API Error: {$message}" . ($details ? " Details: " . json_encode($details) : ""));
    
    sendResponse([
        'success' => false,
        'error' => $message,
        'details' => $details,
        'status_code' => $statusCode
    ], $statusCode);
}

/**
 * バリデーションエラー送信
 */
function sendValidationError($errors) {
    sendError('入力値に不正があります', 400, ['validation_errors' => $errors]);
}

try {
    // PaymentManager初期化
    $paymentManager = new PaymentManager();
    
    // リクエストメソッド別処理
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? $_POST['action'] ?? null;
    
    // JSONリクエストボディ取得
    $requestBody = null;
    if ($method === 'POST' || $method === 'PUT') {
        $input = file_get_contents('php://input');
        if (!empty($input)) {
            $requestBody = json_decode($input, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                sendError('無効なJSON形式です', 400);
            }
            // POSTデータとマージ
            $_POST = array_merge($_POST, $requestBody);
            $action = $action ?? $requestBody['action'] ?? null;
        }
    }
    
    if (!$action) {
        sendError('アクションが指定されていません', 400);
    }
    
    // セキュリティチェック（簡易版）
    if (isset($_POST) && !empty($_POST)) {
        foreach ($_POST as $key => $value) {
            if (is_string($value)) {
                $_POST[$key] = SecurityHelper::sanitizeInput($value);
            }
        }
    }
    
    // =====================================================
    // アクション別処理
    // =====================================================
    
    switch ($action) {
        
        // 集金リスト取得
        case 'collection_list':
            handleCollectionList($paymentManager);
            break;
            
        // 集金サマリー取得
        case 'collection_summary':
            handleCollectionSummary($paymentManager);
            break;
            
        // 緊急アラート取得
        case 'urgent_alerts':
            handleUrgentAlerts($paymentManager);
            break;
            
        // 今日の集金予定取得
        case 'today_schedule':
            handleTodaySchedule($paymentManager);
            break;
            
        // 満額入金記録
        case 'record_full_payment':
            handleRecordFullPayment($paymentManager);
            break;
            
        // 一括満額入金記録
        case 'record_bulk_full_payments':
            handleRecordBulkFullPayments($paymentManager);
            break;
            
        // 印刷用データ取得
        case 'print_data':
            handlePrintData($paymentManager);
            break;
            
        // 支払方法一覧取得
        case 'payment_methods':
            handlePaymentMethods();
            break;
            
        // 支払方法別統計
        case 'payment_method_stats':
            handlePaymentMethodStats($paymentManager);
            break;
            
        // システム状態確認（デバッグ用）
        case 'system_status':
            handleSystemStatus($paymentManager);
            break;
            
        // 既存互換性（従来のAPI）
        case 'list':
            handleLegacyPaymentList($paymentManager);
            break;
            
        case 'stats':
            handleLegacyPaymentStats($paymentManager);
            break;
            
        default:
            sendError("未対応のアクション: {$action}", 404);
    }
    
} catch (Exception $e) {
    error_log("API Exception: " . $e->getMessage() . "\nTrace: " . $e->getTraceAsString());
    sendError('システムエラーが発生しました', 500, [
        'error_type' => get_class($e),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]);
}

// =====================================================
// ハンドラー関数群
// =====================================================

/**
 * 集金リスト取得処理
 */
function handleCollectionList($paymentManager) {
    // フィルター条件取得
    $filters = [
        'company_name' => $_GET['company_name'] ?? null,
        'alert_level' => $_GET['alert_level'] ?? null,
        'amount_min' => !empty($_GET['amount_min']) ? (float)$_GET['amount_min'] : null,
        'amount_max' => !empty($_GET['amount_max']) ? (float)$_GET['amount_max'] : null,
        'due_date_filter' => $_GET['due_date_filter'] ?? null,
        'sort' => $_GET['sort'] ?? 'priority',
        'page' => !empty($_GET['page']) ? (int)$_GET['page'] : 1,
        'limit' => !empty($_GET['limit']) ? min((int)$_GET['limit'], 100) : 20
    ];
    
    // 入力値検証
    $validSorts = ['priority', 'amount_desc', 'due_date', 'company_name'];
    if (!in_array($filters['sort'], $validSorts)) {
        $filters['sort'] = 'priority';
    }
    
    $validAlertLevels = ['overdue', 'urgent', 'normal'];
    if ($filters['alert_level'] && !in_array($filters['alert_level'], $validAlertLevels)) {
        $filters['alert_level'] = null;
    }
    
    // データ取得
    $result = $paymentManager->getCollectionList($filters);
    
    if (is_array($result) && !isset($result['success'])) {
        // 正常な配列データの場合
        sendResponse([
            'items' => $result,
            'pagination' => [
                'current_page' => $filters['page'],
                'per_page' => $filters['limit'],
                'total_items' => count($result),
                'has_more' => count($result) >= $filters['limit']
            ],
            'filters' => $filters,
            'message' => count($result) > 0 ? "集金リストを取得しました（{count($result)}件）" : '該当する集金案件はありません'
        ]);
    } elseif (isset($result['success']) && $result['success']) {
        // 成功レスポンスの場合
        sendResponse($result['data']);
    } else {
        // エラーの場合
        sendError($result['error'] ?? '集金リストの取得に失敗しました', 500);
    }
}

/**
 * 集金サマリー取得処理
 */
function handleCollectionSummary($paymentManager) {
    $result = $paymentManager->getCollectionSummary();
    
    if ($result['success']) {
        sendResponse($result['data']);
    } else {
        sendError($result['error'] ?? 'サマリー情報の取得に失敗しました', 500);
    }
}

/**
 * 緊急アラート取得処理
 */
function handleUrgentAlerts($paymentManager) {
    $result = $paymentManager->getUrgentCollectionAlerts();
    
    if ($result['success']) {
        sendResponse($result['data']);
    } else {
        sendError($result['error'] ?? '緊急アラートの取得に失敗しました', 500);
    }
}

/**
 * 今日の集金予定取得処理
 */
function handleTodaySchedule($paymentManager) {
    $result = $paymentManager->getTodayCollectionSchedule();
    
    if ($result['success']) {
        sendResponse($result['data']);
    } else {
        sendError($result['error'] ?? '今日の予定の取得に失敗しました', 500);
    }
}

/**
 * 満額入金記録処理
 */
function handleRecordFullPayment($paymentManager) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendError('POSTメソッドが必要です', 405);
    }
    
    // 必須パラメータ検証
    $required = ['invoice_id', 'payment_method', 'payment_date'];
    $errors = [];
    
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            $errors[$field] = "{$field}は必須項目です";
        }
    }
    
    if (!empty($errors)) {
        sendValidationError($errors);
    }
    
    // データ型検証
    $invoiceId = filter_var($_POST['invoice_id'], FILTER_VALIDATE_INT);
    if ($invoiceId === false) {
        $errors['invoice_id'] = '請求書IDは数値である必要があります';
    }
    
    $paymentDate = $_POST['payment_date'];
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $paymentDate)) {
        $errors['payment_date'] = '入金日の形式が正しくありません（YYYY-MM-DD）';
    }
    
    $validMethods = array_keys(PaymentManager::getPaymentMethods());
    if (!in_array($_POST['payment_method'], $validMethods)) {
        $errors['payment_method'] = '無効な支払方法です';
    }
    
    if (!empty($errors)) {
        sendValidationError($errors);
    }
    
    // 入金データ準備
    $paymentData = [
        'payment_method' => $_POST['payment_method'],
        'payment_date' => $paymentDate,
        'notes' => $_POST['notes'] ?? null,
        'reference_number' => $_POST['reference_number'] ?? null
    ];
    
    // 処理実行
    $result = $paymentManager->recordFullPayment($invoiceId, $paymentData);
    
    if ($result['success']) {
        sendResponse($result);
    } else {
        sendError($result['error'], 400);
    }
}

/**
 * 一括満額入金記録処理
 */
function handleRecordBulkFullPayments($paymentManager) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendError('POSTメソッドが必要です', 405);
    }
    
    // 必須パラメータ検証
    $required = ['invoice_ids', 'payment_method', 'payment_date'];
    $errors = [];
    
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            $errors[$field] = "{$field}は必須項目です";
        }
    }
    
    if (!empty($errors)) {
        sendValidationError($errors);
    }
    
    // invoice_ids検証
    $invoiceIds = $_POST['invoice_ids'];
    if (!is_array($invoiceIds)) {
        $invoiceIds = json_decode($invoiceIds, true);
    }
    
    if (!is_array($invoiceIds) || empty($invoiceIds)) {
        $errors['invoice_ids'] = '請求書IDの配列が必要です';
    } else {
        // 各IDが数値かチェック
        foreach ($invoiceIds as $id) {
            if (!is_numeric($id)) {
                $errors['invoice_ids'] = '請求書IDは数値である必要があります';
                break;
            }
        }
        
        // 処理可能件数制限（安全対策）
        if (count($invoiceIds) > 50) {
            $errors['invoice_ids'] = '一度に処理できるのは50件までです';
        }
    }
    
    // その他の検証（満額入金と共通）
    $paymentDate = $_POST['payment_date'];
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $paymentDate)) {
        $errors['payment_date'] = '入金日の形式が正しくありません（YYYY-MM-DD）';
    }
    
    $validMethods = array_keys(PaymentManager::getPaymentMethods());
    if (!in_array($_POST['payment_method'], $validMethods)) {
        $errors['payment_method'] = '無効な支払方法です';
    }
    
    if (!empty($errors)) {
        sendValidationError($errors);
    }
    
    // 入金データ準備
    $paymentData = [
        'payment_method' => $_POST['payment_method'],
        'payment_date' => $paymentDate,
        'notes' => $_POST['notes'] ?? '一括入金処理',
        'reference_number' => $_POST['reference_number'] ?? null
    ];
    
    // 処理実行
    $result = $paymentManager->recordBulkFullPayments($invoiceIds, $paymentData);
    
    if ($result['success']) {
        sendResponse($result);
    } else {
        sendError($result['error'] ?? '一括入金処理に失敗しました', 400);
    }
}

/**
 * 印刷用データ取得処理
 */
function handlePrintData($paymentManager) {
    $invoiceIds = $_GET['invoice_ids'] ?? $_POST['invoice_ids'] ?? null;
    
    if (empty($invoiceIds)) {
        sendError('印刷対象の請求書IDが指定されていません', 400);
    }
    
    // 文字列の場合はJSON展開
    if (is_string($invoiceIds)) {
        $invoiceIds = json_decode($invoiceIds, true);
    }
    
    // 配列でない場合はカンマ区切りとして処理
    if (!is_array($invoiceIds)) {
        $invoiceIds = explode(',', $invoiceIds);
    }
    
    // 数値配列に変換
    $invoiceIds = array_filter(array_map('intval', $invoiceIds));
    
    if (empty($invoiceIds)) {
        sendError('有効な請求書IDがありません', 400);
    }
    
    $result = $paymentManager->getCollectionPrintData($invoiceIds);
    
    if ($result['success']) {
        sendResponse($result['data']);
    } else {
        sendError($result['error'] ?? '印刷データの取得に失敗しました', 500);
    }
}

/**
 * 支払方法一覧取得処理
 */
function handlePaymentMethods() {
    $methods = PaymentManager::getPaymentMethods();
    
    sendResponse([
        'payment_methods' => $methods,
        'count' => count($methods),
        'message' => '支払方法一覧を取得しました'
    ]);
}

/**
 * 支払方法別統計取得処理
 */
function handlePaymentMethodStats($paymentManager) {
    $result = $paymentManager->getPaymentMethodsStatistics();
    
    if ($result['success']) {
        sendResponse($result['data']);
    } else {
        sendError($result['error'] ?? '支払方法統計の取得に失敗しました', 500);
    }
}

/**
 * システム状態確認処理
 */
function handleSystemStatus($paymentManager) {
    // デバッグモードでない場合は無効化
    if (!defined('DEBUG_MODE') || !DEBUG_MODE) {
        sendError('この機能は無効化されています', 403);
    }
    
    $status = [
        'database' => $paymentManager->testDatabaseConnection(),
        'php_version' => PHP_VERSION,
        'memory_usage' => [
            'current' => memory_get_usage(true),
            'peak' => memory_get_peak_usage(true),
            'limit' => ini_get('memory_limit')
        ],
        'server_time' => date('Y-m-d H:i:s'),
        'timezone' => date_default_timezone_get()
    ];
    
    sendResponse($status);
}

/**
 * 従来互換：支払リスト取得
 */
function handleLegacyPaymentList($paymentManager) {
    // 従来のAPIとの互換性のため
    $filters = [
        'invoice_id' => $_GET['invoice_id'] ?? null,
        'status' => $_GET['status'] ?? null,
        'date_range' => $_GET['date_range'] ?? null,
        'limit' => min((int)($_GET['limit'] ?? 50), 100)
    ];
    
    // 簡易実装（詳細は別途実装）
    sendResponse([
        'payments' => [],
        'message' => '従来API - 詳細実装予定',
        'filters' => $filters
    ]);
}

/**
 * 従来互換：支払統計取得
 */
function handleLegacyPaymentStats($paymentManager) {
    // 従来のAPIとの互換性のため
    $period = $_GET['period'] ?? 'month';
    
    sendResponse([
        'statistics' => [
            'period' => $period,
            'total_payments' => 0,
            'total_amount' => 0
        ],
        'message' => '従来API - 詳細実装予定'
    ]);
}

/**
 * リクエストログ記録（オプション）
 */
function logRequest() {
    $logData = [
        'timestamp' => date('Y-m-d H:i:s'),
        'method' => $_SERVER['REQUEST_METHOD'],
        'uri' => $_SERVER['REQUEST_URI'],
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
        'action' => $_GET['action'] ?? $_POST['action'] ?? 'unknown'
    ];
    
    error_log("API Request: " . json_encode($logData));
}

// デバッグモード時のみリクエストログ
if (defined('DEBUG_MODE') && DEBUG_MODE) {
    logRequest();
}

?>
