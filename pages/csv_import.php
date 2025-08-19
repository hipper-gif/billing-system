<?php
/**
 * 修正版 CSVインポートAPI
 * api/import.php
 */

// エラー表示設定
error_reporting(E_ALL);
ini_set('display_errors', 0); // 本番では0、開発時は1
ini_set('log_errors', 1);

// ヘッダー設定
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// OPTIONS リクエスト対応
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// エラーハンドリング関数
function sendError($message, $code = 400, $details = []) {
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'error' => $message,
        'details' => $details,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 成功レスポンス関数
function sendSuccess($data, $message = 'Success') {
    echo json_encode([
        'success' => true,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // 必要ファイルの読み込み（重複チェック付き）
    if (!class_exists('Database')) {
        require_once '../config/database.php';
        require_once '../classes/Database.php';
    }
    
    if (!class_exists('SmileyCSVImporter')) {
        require_once '../classes/SmileyCSVImporter.php';
    }
    
    if (!class_exists('SecurityHelper')) {
        require_once '../classes/SecurityHelper.php';
    }
    
    // メソッド別処理
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            handleGetRequest();
            break;
            
        case 'POST':
            handlePostRequest();
            break;
            
        default:
            sendError('対応していないメソッドです', 405);
    }
    
} catch (Exception $e) {
    sendError('システムエラーが発生しました', 500, [
        'error_message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}

/**
 * GET リクエスト処理
 */
function handleGetRequest() {
    $action = $_GET['action'] ?? 'status';
    
    switch ($action) {
        case 'status':
            // システム状態確認
            try {
                $db = new Database();
                
                // テーブル存在確認
                $tables = ['companies', 'departments', 'users', 'suppliers', 'products', 'orders'];
                $tableStatus = [];
                
                foreach ($tables as $table) {
                    $stmt = $db->query("SHOW TABLES LIKE '{$table}'");
                    $tableStatus[$table] = $stmt->rowCount() > 0;
                }
                
                sendSuccess([
                    'database_connection' => true,
                    'tables' => $tableStatus,
                    'timestamp' => date('Y-m-d H:i:s')
                ], 'システム正常稼働中');
                
            } catch (Exception $e) {
                sendError('データベース接続エラー', 500, ['error' => $e->getMessage()]);
            }
            break;
            
        case 'test':
            // テスト応答
            sendSuccess([
                'message' => 'CSVインポートAPI正常稼働中',
                'version' => '1.0.0',
                'methods' => ['GET', 'POST'],
                'endpoints' => [
                    'GET ?action=status' => 'システム状態確認',
                    'GET ?action=test' => 'テスト応答',
                    'POST' => 'CSVファイルインポート'
                ]
            ]);
            break;
            
        default:
            sendError('不明なアクションです', 400);
    }
}

/**
 * POST リクエスト処理（CSVインポート）
 */
function handlePostRequest() {
    try {
        // セッション開始
        SecurityHelper::secureSessionStart();
        
        // CSRF トークン検証（開発時はスキップ）
        if (isset($_POST['csrf_token'])) {
            if (!SecurityHelper::validateCSRFToken($_POST['csrf_token'])) {
                sendError('不正なリクエストです（CSRF）', 403);
            }
        }
        
        // ファイルアップロード検証
        if (!isset($_FILES['csv_file'])) {
            sendError('CSVファイルがアップロードされていません', 400);
        }
        
        $file = $_FILES['csv_file'];
        $fileValidation = SecurityHelper::validateFileUpload($file);
        
        if (!$fileValidation['valid']) {
            sendError('ファイル検証エラー', 400, ['errors' => $fileValidation['errors']]);
        }
        
        // データベース接続
        $db = new Database();
        
        // CSVインポーター初期化
        $importer = new SmileyCSVImporter($db);
        
        // インポートオプション設定
        $importOptions = [
            'encoding' => $_POST['encoding'] ?? 'auto',
            'overwrite' => isset($_POST['overwrite']) ? (bool)$_POST['overwrite'] : false,
            'validate_smiley' => true,
            'dry_run' => isset($_POST['dry_run']) ? (bool)$_POST['dry_run'] : false
        ];
        
        // CSVインポート実行
        $startTime = microtime(true);
        $result = $importer->importFile($file['tmp_name'], $importOptions);
        $processingTime = round(microtime(true) - $startTime, 2);
        
        // セキュリティログ記録
        SecurityHelper::logSecurityEvent('csv_import', [
            'filename' => $file['name'],
            'filesize' => $file['size'],
            'records_processed' => $result['stats']['total'] ?? 0,
            'success_records' => $result['stats']['success'] ?? 0,
            'processing_time' => $processingTime
        ]);
        
        // 成功レスポンス
        sendSuccess([
            'batch_id' => $result['batch_id'] ?? null,
            'stats' => [
                'total_records' => $result['stats']['total'] ?? 0,
                'success_records' => $result['stats']['success'] ?? 0,
                'error_records' => count($result['errors'] ?? []),
                'duplicate_records' => $result['stats']['duplicate'] ?? 0,
                'processing_time' => $processingTime . '秒'
            ],
            'errors' => array_slice($result['errors'] ?? [], 0, 10), // 最初の10件のみ
            'has_more_errors' => count($result['errors'] ?? []) > 10
        ], 'CSVインポートが完了しました');
        
    } catch (Exception $e) {
        // エラーログ記録
        SecurityHelper::logSecurityEvent('csv_import_error', [
            'error_message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
        
        sendError('CSVインポート処理でエラーが発生しました', 500, [
            'error_message' => $e->getMessage(),
            'error_file' => basename($e->getFile()),
            'error_line' => $e->getLine()
        ]);
    }
}
?>
