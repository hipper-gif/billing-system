<?php
/**
 * 完全動作版 CSVインポートAPI - 最終版
 * api/import.php
 */

// エラー設定
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// ヘッダー設定
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// OPTIONS対応
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// レスポンス関数
function sendResponse($success, $message, $data = [], $code = 200) {
    http_response_code($code);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s'),
        'version' => '3.0.0'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // 1. 必須設定読み込み
    require_once '../config/database.php';
    
    // 2. 必要クラス読み込み（重複チェック）
    if (!class_exists('Database')) {
        require_once '../classes/Database.php';
    }
    if (!class_exists('SecurityHelper')) {
        require_once '../classes/SecurityHelper.php';
    }
    if (!class_exists('SmileyCSVImporter')) {
        require_once '../classes/SmileyCSVImporter.php';
    }
    
    // 3. リクエスト処理
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            handleGetRequest();
            break;
        case 'POST':
            handlePostRequest();
            break;
        default:
            sendResponse(false, 'サポートされていないメソッドです', [], 405);
    }
    
} catch (Throwable $e) {
    sendResponse(false, 'システムエラーが発生しました', [
        'error' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ], 500);
}

/**
 * GET リクエスト処理
 */
function handleGetRequest() {
    $action = $_GET['action'] ?? 'status';
    
    switch ($action) {
        case 'test':
            sendResponse(true, 'CSVインポートAPI正常稼働中 - 最終版', [
                'version' => '3.0.0',
                'methods' => ['GET', 'POST'],
                'php_version' => PHP_VERSION,
                'endpoints' => [
                    'GET ?action=test' => 'API動作確認',
                    'GET ?action=status' => 'システム状況確認',
                    'GET ?action=db_test' => 'データベース接続確認',
                    'POST with csv_file' => 'CSVファイルインポート'
                ]
            ]);
            break;
            
        case 'status':
            try {
                // Database接続確認
                $db = Database::getInstance();
                
                // 基本テーブル確認
                $tables = ['companies', 'departments', 'users', 'suppliers', 'products', 'orders'];
                $tableStatus = [];
                
                foreach ($tables as $table) {
                    try {
                        $stmt = $db->query("SHOW TABLES LIKE '{$table}'");
                        $tableStatus[$table] = $stmt->rowCount() > 0;
                    } catch (Exception $e) {
                        $tableStatus[$table] = false;
                    }
                }
                
                sendResponse(true, 'システム正常稼働中', [
                    'database_connection' => true,
                    'database_class' => get_class($db),
                    'tables' => $tableStatus,
                    'required_classes' => [
                        'Database' => class_exists('Database'),
                        'SecurityHelper' => class_exists('SecurityHelper'),
                        'SmileyCSVImporter' => class_exists('SmileyCSVImporter')
                    ]
                ]);
                
            } catch (Exception $e) {
                sendResponse(false, 'システム異常', [
                    'error' => $e->getMessage()
                ], 500);
            }
            break;
            
        case 'db_test':
            try {
                $db = Database::getInstance();
                $stmt = $db->query("SELECT 1 as test, NOW() as current_time, DATABASE() as db_name");
                $result = $stmt->fetch();
                
                sendResponse(true, 'データベース接続成功', [
                    'connection_method' => 'Database::getInstance()',
                    'query_result' => $result,
                    'pdo_available' => $db->getConnection() !== null
                ]);
                
            } catch (Exception $e) {
                sendResponse(false, 'データベース接続エラー', [
                    'error' => $e->getMessage()
                ], 500);
            }
            break;
            
        default:
            sendResponse(false, '不明なアクションです', [
                'available_actions' => ['test', 'status', 'db_test']
            ], 400);
    }
}

/**
 * POST リクエスト処理（CSVインポート）
 */
function handlePostRequest() {
    try {
        // 1. セッション開始
        SecurityHelper::secureSessionStart();
        
        // 2. ファイル検証
        if (!isset($_FILES['csv_file'])) {
            sendResponse(false, 'CSVファイルがアップロードされていません', [], 400);
        }
        
        $file = $_FILES['csv_file'];
        $fileValidation = SecurityHelper::validateFileUpload($file);
        
        if (!$fileValidation['valid']) {
            sendResponse(false, 'ファイル検証エラー', [
                'errors' => $fileValidation['errors']
            ], 400);
        }
        
        // 3. データベース接続
        $db = Database::getInstance();
        
        // 4. CSVインポーター初期化
        $importer = new SmileyCSVImporter($db);
        
        // 5. インポートオプション
        $options = [
            'encoding' => $_POST['encoding'] ?? 'auto',
            'overwrite' => isset($_POST['overwrite']) ? (bool)$_POST['overwrite'] : false,
            'validate_smiley' => true,
            'dry_run' => isset($_POST['dry_run']) ? (bool)$_POST['dry_run'] : false
        ];
        
        // 6. インポート実行
        $startTime = microtime(true);
        $result = $importer->importFile($file['tmp_name'], $options);
        $processingTime = round(microtime(true) - $startTime, 2);
        
        // 7. ログ記録
        SecurityHelper::logSecurityEvent('csv_import_success', [
            'filename' => $file['name'],
            'filesize' => $file['size'],
            'records_total' => $result['stats']['total'] ?? 0,
            'records_success' => $result['stats']['success'] ?? 0,
            'processing_time' => $processingTime
        ]);
        
        // 8. 成功レスポンス
        sendResponse(true, 'CSVインポートが正常に完了しました', [
            'batch_id' => $result['batch_id'] ?? null,
            'filename' => $file['name'],
            'stats' => [
                'total_records' => $result['stats']['total'] ?? 0,
                'success_records' => $result['stats']['success'] ?? 0,
                'error_records' => count($result['errors'] ?? []),
                'duplicate_records' => $result['stats']['duplicate'] ?? 0,
                'processing_time' => $processingTime . '秒'
            ],
            'errors' => array_slice($result['errors'] ?? [], 0, 5), // 最初の5件のみ
            'has_more_errors' => count($result['errors'] ?? []) > 5,
            'import_summary' => [
                'database_connection' => 'Database::getInstance()',
                'validation_passed' => true,
                'encoding_detected' => $result['encoding'] ?? 'unknown'
            ]
        ]);
        
    } catch (Throwable $e) {
        // エラーログ記録
        SecurityHelper::logSecurityEvent('csv_import_error', [
            'error_message' => $e->getMessage(),
            'file' => basename($e->getFile()),
            'line' => $e->getLine(),
            'uploaded_file' => $_FILES['csv_file']['name'] ?? 'unknown'
        ]);
        
        sendResponse(false, 'CSVインポート中にエラーが発生しました', [
            'error_message' => $e->getMessage(),
            'error_file' => basename($e->getFile()),
            'error_line' => $e->getLine(),
            'troubleshooting' => [
                'check_csv_format' => 'CSVファイルの形式を確認してください',
                'check_file_encoding' => 'ファイルのエンコーディングを確認してください',
                'check_file_size' => 'ファイルサイズが10MB以下であることを確認してください'
            ]
        ], 500);
    }
}
?>
