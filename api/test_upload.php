<?php
/**
 * アップロードテスト用API
 * エラー原因特定のための軽量版
 */

// エラー表示を有効化
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// CORS対応
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// ログ関数
function logMessage($message) {
    $logFile = __DIR__ . '/../logs/upload_test.log';
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[{$timestamp}] {$message}\n", FILE_APPEND | LOCK_EX);
}

try {
    logMessage("=== Upload Test Start ===");
    logMessage("Request Method: " . $_SERVER['REQUEST_METHOD']);
    logMessage("Content Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'not set'));
    logMessage("Post Data: " . json_encode($_POST));
    logMessage("Files Data: " . json_encode($_FILES));
    
    // OPTIONSリクエスト対応
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        logMessage("OPTIONS request received");
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'OPTIONS OK']);
        exit;
    }

    // POSTリクエストチェック
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        logMessage("Invalid method: " . $_SERVER['REQUEST_METHOD']);
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'POSTリクエストのみ対応',
            'method' => $_SERVER['REQUEST_METHOD']
        ]);
        exit;
    }

    // PHP設定確認
    $phpInfo = [
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'post_max_size' => ini_get('post_max_size'),
        'max_execution_time' => ini_get('max_execution_time'),
        'memory_limit' => ini_get('memory_limit'),
        'file_uploads' => ini_get('file_uploads') ? 'enabled' : 'disabled'
    ];
    
    logMessage("PHP Settings: " . json_encode($phpInfo));

    // ファイルアップロード基本チェック
    if (empty($_FILES)) {
        logMessage("No files uploaded");
        echo json_encode([
            'success' => false,
            'message' => 'ファイルがアップロードされていません',
            'php_settings' => $phpInfo,
            'server_info' => [
                'max_file_uploads' => ini_get('max_file_uploads'),
                'upload_tmp_dir' => ini_get('upload_tmp_dir')
            ]
        ]);
        exit;
    }

    // ファイル情報詳細
    if (isset($_FILES['csv_file'])) {
        $file = $_FILES['csv_file'];
        
        logMessage("File details: " . json_encode($file));
        
        $fileInfo = [
            'name' => $file['name'],
            'type' => $file['type'],
            'size' => $file['size'],
            'tmp_name' => $file['tmp_name'],
            'error' => $file['error']
        ];
        
        // エラーコード詳細
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
        logMessage("Upload error: {$file['error']} - {$errorMessage}");
        
        if ($file['error'] === UPLOAD_ERR_OK) {
            // 一時ファイル存在確認
            if (is_uploaded_file($file['tmp_name'])) {
                logMessage("Valid uploaded file detected");
                
                // ファイル内容の先頭を確認
                $fileContent = file_get_contents($file['tmp_name'], false, null, 0, 500);
                logMessage("File content preview: " . substr($fileContent, 0, 200));
                
                echo json_encode([
                    'success' => true,
                    'message' => 'テストアップロード成功',
                    'file_info' => $fileInfo,
                    'php_settings' => $phpInfo,
                    'content_preview' => substr($fileContent, 0, 200)
                ]);
                
            } else {
                logMessage("Invalid uploaded file");
                echo json_encode([
                    'success' => false,
                    'message' => '無効なアップロードファイル',
                    'file_info' => $fileInfo
                ]);
            }
        } else {
            logMessage("Upload error detected: " . $file['error']);
            echo json_encode([
                'success' => false,
                'message' => 'ファイルアップロードエラー: ' . $errorMessage,
                'error_code' => $file['error'],
                'file_info' => $fileInfo,
                'php_settings' => $phpInfo
            ]);
        }
    } else {
        logMessage("csv_file parameter not found");
        echo json_encode([
            'success' => false,
            'message' => 'csv_fileパラメータが見つかりません',
            'available_files' => array_keys($_FILES),
            'post_data' => $_POST
        ]);
    }

} catch (Exception $e) {
    logMessage("Exception: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'エラー: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
} catch (Error $e) {
    logMessage("Fatal Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => '致命的エラー: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}

logMessage("=== Upload Test End ===");
?>
