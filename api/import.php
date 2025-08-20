<?php
/**
 * CSVインポートAPI（根本解決版）
 * FileUploadHandler対応・エラーハンドリング強化
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

// レスポンス関数
function sendResponse($success, $message, $data = [], $code = 200) {
    http_response_code($code);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s'),
        'version' => '4.0.0'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// OPTIONS対応
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    sendResponse(true, 'OPTIONS OK');
}

try {
    // 必須ファイル読み込み
    require_once '../config/database.php';
    require_once '../classes/Database.php';
    require_once '../classes/SmileyCSVImporter.php';
    require_once '../classes/FileUploadHandler.php';
    
    // SecurityHelperは必須ではないため、存在チェック
    $securityHelperExists = false;
    if (file_exists('../classes/SecurityHelper.php')) {
        require_once '../classes/SecurityHelper.php';
        $securityHelperExists = class_exists('SecurityHelper');
    }
    
    // リクエスト処理
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            handleGetRequest($securityHelperExists);
            break;
        case 'POST':
            handlePostRequest($securityHelperExists);
            break;
        default:
            sendResponse(false, 'サポートされていないHTTPメソッドです', [], 405);
    }
    
} catch (Throwable $e) {
    sendResponse(false, 'システム初期化エラー', [
        'error' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine(),
        'trace' => DEBUG_MODE ? $e->getTraceAsString() : 'デバッグモードで詳細確認可能'
    ], 500);
}

/**
 * GET リクエスト処理
 */
function handleGetRequest($securityHelperExists) {
    $action = $_GET['action'] ?? 'status';
    
    switch ($action) {
        case 'test':
            sendResponse(true, 'CSVインポートAPI根本解決版 - 正常稼働中', [
                'version' => '4.0.0',
                'methods' => ['GET', 'POST'],
                'php_version' => PHP_VERSION,
                'security_helper' => $securityHelperExists,
                'components' => [
                    'Database' => class_exists('Database'),
                    'SmileyCSVImporter' => class_exists('SmileyCSVImporter'),
                    'FileUploadHandler' => class_exists('FileUploadHandler'),
                    'SecurityHelper' => $securityHelperExists
                ],
                'endpoints' => [
                    'GET ?action=test' => 'API動作確認',
                    'GET ?action=status' => 'システム状況確認',
                    'GET ?action=upload_info' => 'アップロード設定確認',
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
                
                // ディレクトリ確認
                $uploadDir = '../uploads/';
                $dirStatus = [
                    'uploads_dir_exists' => is_dir($uploadDir),
                    'uploads_dir_writable' => is_writable($uploadDir),
                    'uploads_dir_path' => realpath($uploadDir)
                ];
                
                sendResponse(true, 'システム正常稼働中', [
                    'database_connection' => true,
                    'database_class' => get_class($db),
                    'tables' => $tableStatus,
                    'directories' => $dirStatus,
                    'required_classes' => [
                        'Database' => class_exists('Database'),
                        'SmileyCSVImporter' => class_exists('SmileyCSVImporter'),
                        'FileUploadHandler' => class_exists('FileUploadHandler'),
                        'SecurityHelper' => $securityHelperExists
                    ],
                    'php_config' => [
                        'upload_max_filesize' => ini_get('upload_max_filesize'),
                        'post_max_size' => ini_get('post_max_size'),
                        'max_execution_time' => ini_get('max_execution_time'),
                        'memory_limit' => ini_get('memory_limit')
                    ]
                ]);
                
            } catch (Exception $e) {
                sendResponse(false, 'システム異常', [
                    'error' => $e->getMessage(),
                    'file' => basename($e->getFile()),
                    'line' => $e->getLine()
                ], 500);
            }
            break;
            
        case 'upload_info':
            try {
                $fileHandler = new FileUploadHandler();
                $uploadInfo = $fileHandler->getUploadInfo();
                
                sendResponse(true, 'アップロード設定情報', $uploadInfo);
                
            } catch (Exception $e) {
                sendResponse(false, 'アップロード設定取得エラー', [
                    'error' => $e->getMessage()
                ], 500);
            }
            break;
            
        default:
            sendResponse(false, '不明なアクションです', [
                'available_actions' => ['test', 'status', 'upload_info'],
                'provided_action' => $action
            ], 400);
    }
}

/**
 * POST リクエスト処理（CSVインポート）
 */
function handlePostRequest($securityHelperExists) {
    try {
        // セッション開始（SecurityHelperがある場合のみ）
        if ($securityHelperExists && method_exists('SecurityHelper', 'secureSessionStart')) {
            SecurityHelper::secureSessionStart();
        } else {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
        }
        
        // CSRF検証（SecurityHelperがある場合のみ）
        if ($securityHelperExists && method_exists('SecurityHelper', 'validateCSRFToken')) {
            $csrfToken = $_POST['csrf_token'] ?? '';
            if (!empty($csrfToken) && !SecurityHelper::validateCSRFToken($csrfToken)) {
                sendResponse(false, 'CSRF検証エラー', [], 403);
            }
        }
        
        // ファイル存在確認
        if (!isset($_FILES['csv_file'])) {
            sendResponse(false, 'CSVファイルがアップロードされていません', [
                'received_files' => array_keys($_FILES),
                'expected_field' => 'csv_file'
            ], 400);
        }
        
        $file = $_FILES['csv_file'];
        
        // ファイルアップロード処理
        $fileHandler = new FileUploadHandler();
        $uploadResult = $fileHandler->uploadFile($file);
        
        if (!$uploadResult['success']) {
            sendResponse(false, 'ファイルアップロードエラー', [
                'errors' => $uploadResult['errors'],
                'file_info' => [
                    'name' => $file['name'] ?? 'unknown',
                    'size' => $file['size'] ?? 0,
                    'error' => $file['error'] ?? 'unknown'
                ]
            ], 400);
        }
        
        // CSVインポート実行
        $importer = new SmileyCSVImporter();
        
        $importOptions = [
            'encoding' => $_POST['encoding'] ?? 'UTF-8',
            'delimiter' => $_POST['delimiter'] ?? ',',
            'has_header' => true
        ];
        
        $startTime = microtime(true);
        $result = $importer->importCSV($uploadResult['filepath'], $importOptions);
        $processingTime = round(microtime(true) - $startTime, 2);
        
        // 一時ファイル削除
        $fileHandler->deleteFile($uploadResult['filepath']);
        
        // セキュリティログ記録（SecurityHelperがある場合のみ）
        if ($securityHelperExists && method_exists('SecurityHelper', 'logSecurityEvent')) {
            SecurityHelper::logSecurityEvent('csv_import_success', [
                'filename' => $uploadResult['original_name'],
                'filesize' => $uploadResult['filesize'],
                'records_total' => $result['stats']['total_rows'] ?? 0,
                'records_success' => $result['stats']['success_rows'] ?? 0,
                'processing_time' => $processingTime
            ]);
        }
        
        // 成功レスポンス
        sendResponse(true, 'CSVインポートが正常に完了しました', [
            'batch_id' => $result['batch_id'],
            'filename' => $uploadResult['original_name'],
            'stats' => [
                'total_records' => $result['stats']['total_rows'] ?? 0,
                'success_records' => $result['stats']['success_rows'] ?? 0,
                'error_records' => $result['stats']['error_rows'] ?? 0,
                'new_companies' => $result['stats']['new_companies'] ?? 0,
                'new_departments' => $result['stats']['new_departments'] ?? 0,
                'new_users' => $result['stats']['new_users'] ?? 0,
                'new_products' => $result['stats']['new_products'] ?? 0,
                'duplicate_orders' => $result['stats']['duplicate_orders'] ?? 0,
                'processing_time' => $processingTime . '秒'
            ],
            'errors' => array_slice($result['errors'] ?? [], 0, 10), // 最初の10件のみ
            'has_more_errors' => count($result['errors'] ?? []) > 10,
            'summary_message' => $result['summary_message'] ?? '',
            'import_details' => [
                'upload_info' => [
                    'original_filename' => $uploadResult['original_name'],
                    'upload_filename' => $uploadResult['filename'],
                    'file_size' => round($uploadResult['filesize'] / 1024, 2) . 'KB'
                ],
                'processing_info' => [
                    'encoding_used' => $importOptions['encoding'],
                    'delimiter_used' => $importOptions['delimiter'],
                    'batch_id' => $result['batch_id']
                ]
            ]
        ]);
        
    } catch (Throwable $e) {
        // エラーログ記録（SecurityHelperがある場合のみ）
        if ($securityHelperExists && method_exists('SecurityHelper', 'logSecurityEvent')) {
            SecurityHelper::logSecurityEvent('csv_import_error', [
                'error_message' => $e->getMessage(),
                'file' => basename($e->getFile()),
                'line' => $e->getLine(),
                'uploaded_file' => $_FILES['csv_file']['name'] ?? 'unknown'
            ]);
        }
        
        // アップロードファイルがある場合は削除
        if (isset($uploadResult) && $uploadResult['success']) {
            $fileHandler->deleteFile($uploadResult['filepath']);
        }
        
        sendResponse(false, 'CSVインポート中にエラーが発生しました', [
            'error_message' => $e->getMessage(),
            'error_file' => basename($e->getFile()),
            'error_line' => $e->getLine(),
            'troubleshooting' => [
                'check_csv_format' => 'CSVファイルが23列の正しい形式であることを確認してください',
                'check_file_encoding' => 'ファイルのエンコーディング（UTF-8またはShift-JIS）を確認してください',
                'check_file_size' => 'ファイルサイズが10MB以下であることを確認してください',
                'check_corporation_name' => '法人名が「株式会社Smiley」であることを確認してください'
            ],
            'debug_info' => DEBUG_MODE ? [
                'trace' => $e->getTraceAsString(),
                'previous' => $e->getPrevious() ? $e->getPrevious()->getMessage() : null
            ] : 'デバッグモードで詳細確認可能'
        ], 500);
    }
}

/**
 * システム定数定義（config/database.phpで定義されていない場合のフォールバック）
 */
if (!defined('DEBUG_MODE')) {
    define('DEBUG_MODE', false);
}

if (!defined('UPLOAD_MAX_SIZE')) {
    define('UPLOAD_MAX_SIZE', 10 * 1024 * 1024); // 10MB
}
?>
