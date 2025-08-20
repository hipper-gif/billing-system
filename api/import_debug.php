<?php
/**
 * CSVインポートAPI（JSON混入問題修正版）
 */

// 出力バッファリング開始（PHPエラーキャッチ用）
ob_start();

// エラー設定（JSON出力を汚染しないよう設定）
error_reporting(E_ALL);
ini_set('display_errors', 0);  // 画面出力無効化
ini_set('log_errors', 1);

// JSONレスポンス関数（出力バッファクリア機能付き）
function sendResponse($success, $message, $data = [], $code = 200) {
    // PHPエラー出力をクリア
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // ヘッダー設定
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s'),
        'version' => '4.1.0'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// エラーハンドラー設定
set_error_handler(function($severity, $message, $file, $line) {
    error_log("CSV Import Error: {$message} in {$file}:{$line}");
    
    if ($severity === E_ERROR || $severity === E_PARSE) {
        throw new ErrorException($message, 0, $severity, $file, $line);
    }
});

// 例外ハンドラー
set_exception_handler(function($exception) {
    sendResponse(false, 'システムエラー', [
        'error' => $exception->getMessage(),
        'file' => basename($exception->getFile()),
        'line' => $exception->getLine()
    ], 500);
});

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
    
    // GET リクエスト処理
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = $_GET['action'] ?? 'status';
        
        switch ($action) {
            case 'test':
                sendResponse(true, 'CSVインポートAPI修正版 - 正常稼働中', [
                    'version' => '4.1.0',
                    'fixes' => ['JSON混入問題修正', '出力バッファリング対応'],
                    'components' => [
                        'Database' => class_exists('Database'),
                        'SmileyCSVImporter' => class_exists('SmileyCSVImporter'),
                        'FileUploadHandler' => class_exists('FileUploadHandler')
                    ]
                ]);
                break;
                
            case 'status':
                $db = Database::getInstance();
                sendResponse(true, 'システム正常稼働中', [
                    'database_connection' => true,
                    'required_classes' => [
                        'Database' => class_exists('Database'),
                        'SmileyCSVImporter' => class_exists('SmileyCSVImporter'),
                        'FileUploadHandler' => class_exists('FileUploadHandler')
                    ]
                ]);
                break;
                
            default:
                sendResponse(false, '不明なアクション', [
                    'available_actions' => ['test', 'status']
                ], 400);
        }
    }
    
    // POST リクエスト処理
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // ファイル確認
        if (!isset($_FILES['csv_file'])) {
            sendResponse(false, 'CSVファイルがアップロードされていません', [
                'received_files' => array_keys($_FILES)
            ], 400);
        }
        
        $file = $_FILES['csv_file'];
        
        // ファイルアップロード処理
        $fileHandler = new FileUploadHandler();
        $uploadResult = $fileHandler->uploadFile($file);
        
        if (!$uploadResult['success']) {
            sendResponse(false, 'ファイルアップロードエラー', [
                'errors' => $uploadResult['errors']
            ], 400);
        }
        
        // CSVインポート実行
        $importer = new SmileyCSVImporter();
        $importOptions = [
            'encoding' => $_POST['encoding'] ?? 'UTF-8',
            'delimiter' => ',',
            'has_header' => true
        ];
        
        $startTime = microtime(true);
        $result = $importer->importCSV($uploadResult['filepath'], $importOptions);
        $processingTime = round(microtime(true) - $startTime, 2);
        
        // 一時ファイル削除
        $fileHandler->deleteFile($uploadResult['filepath']);
        
        // 成功レスポンス
        sendResponse(true, 'CSVインポートが正常に完了しました', [
            'batch_id' => $result['batch_id'],
            'filename' => $uploadResult['original_name'],
            'stats' => [
                'total_records' => $result['stats']['total_rows'] ?? 0,
                'success_records' => $result['stats']['success_rows'] ?? 0,
                'error_records' => $result['stats']['error_rows'] ?? 0,
                'new_companies' => $result['stats']['new_companies'] ?? 0,
                'new_users' => $result['stats']['new_users'] ?? 0,
                'processing_time' => $processingTime . '秒'
            ],
            'errors' => array_slice($result['errors'] ?? [], 0, 5),
            'has_more_errors' => count($result['errors'] ?? []) > 5,
            'summary_message' => $result['summary_message'] ?? ''
        ]);
    }
    
} catch (Throwable $e) {
    sendResponse(false, 'エラーが発生しました', [
        'error' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ], 500);
}
?>
