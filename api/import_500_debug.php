<?php
/**
 * CSVインポートAPI（500エラー特定版）
 * 詳細なエラーログとステップ別実行
 */

// 出力バッファリング開始
ob_start();

// エラー設定強化
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('memory_limit', '512M');  // メモリ制限拡張
ini_set('max_execution_time', 300); // 実行時間拡張

// ログファイル設定
$logFile = __DIR__ . '/../logs/csv_import_debug.log';
$logDir = dirname($logFile);
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

// カスタムログ関数
function debugLog($message, $data = null) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] {$message}";
    if ($data !== null) {
        $logMessage .= " Data: " . json_encode($data, JSON_UNESCAPED_UNICODE);
    }
    file_put_contents($logFile, $logMessage . "\n", FILE_APPEND | LOCK_EX);
}

// JSONレスポンス関数
function sendResponse($success, $message, $data = [], $code = 200) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    
    $response = [
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s'),
        'version' => 'debug-500-1.0',
        'memory_usage' => memory_get_usage(true),
        'memory_peak' => memory_get_peak_usage(true)
    ];
    
    debugLog("Response", $response);
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

// エラーハンドラー
set_error_handler(function($severity, $message, $file, $line) {
    debugLog("PHP Error", [
        'severity' => $severity,
        'message' => $message,
        'file' => basename($file),
        'line' => $line
    ]);
    
    if ($severity === E_ERROR || $severity === E_PARSE) {
        throw new ErrorException($message, 0, $severity, $file, $line);
    }
});

// 例外ハンドラー
set_exception_handler(function($exception) {
    debugLog("Uncaught Exception", [
        'message' => $exception->getMessage(),
        'file' => basename($exception->getFile()),
        'line' => $exception->getLine(),
        'trace' => $exception->getTraceAsString()
    ]);
    
    sendResponse(false, '500エラー詳細', [
        'error' => $exception->getMessage(),
        'file' => basename($exception->getFile()),
        'line' => $exception->getLine(),
        'step' => $_SESSION['debug_step'] ?? 'unknown'
    ], 500);
});

// セッション開始
session_start();

debugLog("=== CSV Import Debug Start ===");
debugLog("Request Method", $_SERVER['REQUEST_METHOD']);
debugLog("Files", $_FILES);
debugLog("Post", $_POST);

// OPTIONS対応
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    sendResponse(true, 'OPTIONS OK');
}

try {
    $_SESSION['debug_step'] = 'file_loading';
    debugLog("Step: File Loading");
    
    // 必須ファイル読み込み
    require_once '../config/database.php';
    require_once '../classes/Database.php';
    require_once '../classes/SmileyCSVImporter.php';
    require_once '../classes/FileUploadHandler.php';
    
    debugLog("Files loaded successfully");
    
    // GET リクエスト処理
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = $_GET['action'] ?? 'debug';
        
        switch ($action) {
            case 'debug':
            case 'status':
                $_SESSION['debug_step'] = 'get_status';
                
                // Database接続テスト
                $db = Database::getInstance();
                debugLog("Database connection", "success");
                
                sendResponse(true, 'デバッグAPI稼働中', [
                    'database_connection' => true,
                    'log_file' => $logFile,
                    'php_config' => [
                        'memory_limit' => ini_get('memory_limit'),
                        'max_execution_time' => ini_get('max_execution_time'),
                        'upload_max_filesize' => ini_get('upload_max_filesize')
                    ]
                ]);
                break;
                
            case 'clear_log':
                if (file_exists($logFile)) {
                    file_put_contents($logFile, '');
                }
                sendResponse(true, 'ログクリア完了');
                break;
                
            case 'view_log':
                $logs = file_exists($logFile) ? file_get_contents($logFile) : 'ログファイルなし';
                sendResponse(true, 'ログ内容', ['logs' => $logs]);
                break;
                
            default:
                sendResponse(true, 'デバッグAPI', [
                    'available_actions' => ['debug', 'status', 'clear_log', 'view_log']
                ]);
        }
    }
    
    // POST リクエスト処理（段階的実行）
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        
        $_SESSION['debug_step'] = 'post_start';
        debugLog("Step: POST Start");
        
        // Step 1: ファイル確認
        $_SESSION['debug_step'] = 'file_check';
        debugLog("Step: File Check");
        
        if (!isset($_FILES['csv_file'])) {
            sendResponse(false, 'CSVファイル未検出', [
                'received_files' => array_keys($_FILES),
                'files_detail' => $_FILES
            ], 400);
        }
        
        $file = $_FILES['csv_file'];
        debugLog("File Info", $file);
        
        // Step 2: FileUploadHandler初期化
        $_SESSION['debug_step'] = 'upload_handler_init';
        debugLog("Step: FileUploadHandler Init");
        
        $fileHandler = new FileUploadHandler();
        debugLog("FileUploadHandler created");
        
        // Step 3: ファイルアップロード
        $_SESSION['debug_step'] = 'file_upload';
        debugLog("Step: File Upload");
        
        $uploadResult = $fileHandler->uploadFile($file);
        debugLog("Upload Result", $uploadResult);
        
        if (!$uploadResult['success']) {
            sendResponse(false, 'ファイルアップロード失敗', [
                'errors' => $uploadResult['errors'],
                'file_info' => $file
            ], 400);
        }
        
        // Step 4: SmileyCSVImporter初期化
        $_SESSION['debug_step'] = 'csv_importer_init';
        debugLog("Step: SmileyCSVImporter Init");
        
        $importer = new SmileyCSVImporter();
        debugLog("SmileyCSVImporter created");
        
        // Step 5: CSVファイル基本チェック
        $_SESSION['debug_step'] = 'csv_basic_check';
        debugLog("Step: CSV Basic Check");
        
        $csvPath = $uploadResult['filepath'];
        if (!file_exists($csvPath)) {
            throw new Exception("アップロードファイルが見つかりません: {$csvPath}");
        }
        
        $fileSize = filesize($csvPath);
        debugLog("CSV File", [
            'path' => $csvPath,
            'size' => $fileSize,
            'size_mb' => round($fileSize / 1024 / 1024, 2)
        ]);
        
        // ファイルサイズチェック（50MBまで）
        if ($fileSize > 50 * 1024 * 1024) {
            $fileHandler->deleteFile($csvPath);
            sendResponse(false, 'ファイルサイズが大きすぎます', [
                'file_size_mb' => round($fileSize / 1024 / 1024, 2),
                'max_size_mb' => 50
            ], 400);
        }
        
        // Step 6: CSV内容プレビュー
        $_SESSION['debug_step'] = 'csv_preview';
        debugLog("Step: CSV Preview");
        
        $handle = fopen($csvPath, 'r');
        if ($handle) {
            $firstLine = fgets($handle);
            $secondLine = fgets($handle);
            fclose($handle);
            
            debugLog("CSV Preview", [
                'first_line' => $firstLine,
                'second_line' => $secondLine
            ]);
        }
        
        // Step 7: インポートオプション設定
        $_SESSION['debug_step'] = 'import_options';
        debugLog("Step: Import Options");
        
        $importOptions = [
            'encoding' => $_POST['encoding'] ?? 'UTF-8',
            'delimiter' => ',',
            'has_header' => true
        ];
        debugLog("Import Options", $importOptions);
        
        // Step 8: CSVインポート実行（制限付き）
        $_SESSION['debug_step'] = 'csv_import_execute';
        debugLog("Step: CSV Import Execute");
        
        $startTime = microtime(true);
        $result = $importer->importCSV($csvPath, $importOptions);
        $processingTime = round(microtime(true) - $startTime, 2);
        
        debugLog("Import Completed", [
            'processing_time' => $processingTime,
            'result' => $result
        ]);
        
        // Step 9: 一時ファイル削除
        $_SESSION['debug_step'] = 'cleanup';
        debugLog("Step: Cleanup");
        
        $fileHandler->deleteFile($csvPath);
        
        // Step 10: 成功レスポンス
        $_SESSION['debug_step'] = 'success_response';
        debugLog("Step: Success Response");
        
        sendResponse(true, 'CSVインポート成功（デバッグ版）', [
            'batch_id' => $result['batch_id'],
            'filename' => $uploadResult['original_name'],
            'processing_time' => $processingTime,
            'stats' => $result['stats'],
            'summary' => $result['summary_message'] ?? '',
            'errors' => array_slice($result['errors'] ?? [], 0, 3),
            'total_errors' => count($result['errors'] ?? []),
            'debug_info' => [
                'memory_peak' => memory_get_peak_usage(true),
                'execution_time' => $processingTime
            ]
        ]);
    }
    
} catch (Throwable $e) {
    debugLog("Fatal Error", [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
        'step' => $_SESSION['debug_step'] ?? 'unknown'
    ]);
    
    // 一時ファイル削除
    if (isset($csvPath) && file_exists($csvPath)) {
        unlink($csvPath);
    }
    
    sendResponse(false, '500エラー詳細分析', [
        'error_message' => $e->getMessage(),
        'error_file' => basename($e->getFile()),
        'error_line' => $e->getLine(),
        'debug_step' => $_SESSION['debug_step'] ?? 'unknown',
        'memory_usage' => memory_get_usage(true),
        'log_file' => $logFile
    ], 500);
}

debugLog("=== CSV Import Debug End ===");
?>
