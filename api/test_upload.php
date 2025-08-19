<?php
/**
 * アップロードテスト用API (改良版)
 * JSONレスポンスの問題を解決
 */

// 出力バッファリングを開始（余計な出力を防ぐ）
ob_start();

// エラー表示設定
error_reporting(E_ALL);
ini_set('display_errors', 0); // 画面表示は無効化
ini_set('log_errors', 1);

// CORS対応
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// 出力バッファをクリア（ヘッダー出力後）
ob_clean();

// ログ関数
function logMessage($message) {
    $logFile = __DIR__ . '/../logs/upload_test.log';
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    @file_put_contents($logFile, "[{$timestamp}] {$message}\n", FILE_APPEND | LOCK_EX);
}

// JSON出力関数
function outputJson($data) {
    // 出力バッファをクリア
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // JSON出力
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// エラーハンドラー
function handleError($message, $details = []) {
    logMessage("ERROR: {$message}");
    http_response_code(400);
    outputJson([
        'success' => false,
        'message' => $message,
        'details' => $details,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

try {
    logMessage("=== Upload Test Start ===");
    logMessage("Request Method: " . $_SERVER['REQUEST_METHOD']);
    logMessage("Content Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'not set'));
    
    // OPTIONSリクエスト対応
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        logMessage("OPTIONS request received");
        http_response_code(200);
        outputJson(['success' => true, 'message' => 'OPTIONS OK']);
    }

    // POSTリクエストチェック
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        handleError('POSTリクエストのみ対応', ['method' => $_SERVER['REQUEST_METHOD']]);
    }

    // PHP設定確認
    $phpInfo = [
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'post_max_size' => ini_get('post_max_size'),
        'max_execution_time' => ini_get('max_execution_time'),
        'memory_limit' => ini_get('memory_limit'),
        'file_uploads' => ini_get('file_uploads') ? 'enabled' : 'disabled',
        'max_file_uploads' => ini_get('max_file_uploads'),
        'upload_tmp_dir' => ini_get('upload_tmp_dir') ?: 'default'
    ];
    
    logMessage("PHP Settings: " . json_encode($phpInfo));

    // POST/FILESデータログ
    logMessage("POST keys: " . implode(', ', array_keys($_POST)));
    logMessage("FILES keys: " . implode(', ', array_keys($_FILES)));

    // ファイルアップロード基本チェック
    if (empty($_FILES)) {
        handleError('ファイルがアップロードされていません', [
            'php_settings' => $phpInfo,
            'post_data' => $_POST,
            'content_length' => $_SERVER['CONTENT_LENGTH'] ?? 0
        ]);
    }

    // csv_fileチェック
    if (!isset($_FILES['csv_file'])) {
        handleError('csv_fileパラメータが見つかりません', [
            'available_files' => array_keys($_FILES),
            'post_data' => $_POST
        ]);
    }

    $file = $_FILES['csv_file'];
    logMessage("File details: " . json_encode($file));
    
    // アップロードエラーチェック
    $uploadErrors = [
        UPLOAD_ERR_OK => 'アップロード成功',
        UPLOAD_ERR_INI_SIZE => 'ファイルサイズがupload_max_filesizeを超過',
        UPLOAD_ERR_FORM_SIZE => 'ファイルサイズがMAX_FILE_SIZEを超過', 
        UPLOAD_ERR_PARTIAL => 'ファイルが一部のみアップロード',
        UPLOAD_ERR_NO_FILE => 'ファイルがアップロードされていない',
        UPLOAD_ERR_NO_TMP_DIR => '一時フォルダが見つからない',
        UPLOAD_ERR_CANT_WRITE => 'ディスクへの書き込み失敗',
        UPLOAD_ERR_EXTENSION => 'PHP拡張によってアップロード停止'
    ];
    
    $errorMessage = $uploadErrors[$file['error']] ?? 'Unknown error';
    logMessage("Upload status: {$file['error']} - {$errorMessage}");
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        handleError('ファイルアップロードエラー: ' . $errorMessage, [
            'error_code' => $file['error'],
            'file_info' => $file,
            'php_settings' => $phpInfo
        ]);
    }

    // 一時ファイル存在確認
    if (!is_uploaded_file($file['tmp_name'])) {
        handleError('無効なアップロードファイル', ['file_info' => $file]);
    }

    // ファイル情報詳細取得
    $fileSize = $file['size'];
    $fileSizeKB = round($fileSize / 1024, 2);
    
    // ファイル内容プレビュー取得
    $contentPreview = '';
    if ($fileSize > 0) {
        $content = file_get_contents($file['tmp_name'], false, null, 0, 1000);
        $contentPreview = mb_substr($content, 0, 500);
    }
    
    logMessage("File processed successfully, size: {$fileSizeKB}KB");
    
    // 成功レスポンス
    outputJson([
        'success' => true,
        'message' => 'テストアップロード成功！',
        'data' => [
            'file_info' => [
                'name' => $file['name'],
                'type' => $file['type'],
                'size' => $fileSize,
                'size_kb' => $fileSizeKB,
                'error_code' => $file['error']
            ],
            'content_preview' => $contentPreview,
            'php_settings' => $phpInfo,
            'upload_options' => [
                'encoding' => $_POST['encoding'] ?? 'not provided',
                'delimiter' => $_POST['delimiter'] ?? 'not provided'
            ]
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    logMessage("Exception: " . $e->getMessage());
    handleError('例外エラー: ' . $e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
} catch (Error $e) {
    logMessage("Fatal Error: " . $e->getMessage());
    handleError('致命的エラー: ' . $e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
} catch (Throwable $e) {
    logMessage("Throwable: " . $e->getMessage());
    handleError('予期しないエラー: ' . $e->getMessage(), [
        'type' => get_class($e),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}

logMessage("=== Upload Test End ===");
?>
