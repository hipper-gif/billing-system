<?php
/**
 * CSVインポートAPI
 * 根本解決版: 正しい依存関係と責任分離
 */

// 設定読み込み
require_once '../config/database.php';

// 必要クラス読み込み
require_once '../classes/Database.php';
require_once '../classes/DatabaseFactory.php';
require_once '../classes/FileUploadHandler.php';
require_once '../classes/SmileyCSVImporter.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // 1. HTTPメソッドチェック
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('POSTメソッドが必要です');
    }
    
    // 2. ファイルアップロードチェック
    if (!isset($_FILES['csvFile'])) {
        throw new Exception('CSVファイルがアップロードされていません');
    }
    
    $file = $_FILES['csvFile'];
    
    // 3. アップロードエラーチェック
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'ファイルサイズが上限を超えています',
            UPLOAD_ERR_FORM_SIZE => 'フォームで指定されたサイズを超えています',
            UPLOAD_ERR_PARTIAL => 'ファイルが部分的にしかアップロードされませんでした',
            UPLOAD_ERR_NO_FILE => 'ファイルがアップロードされませんでした',
            UPLOAD_ERR_NO_TMP_DIR => '一時ディレクトリがありません',
            UPLOAD_ERR_CANT_WRITE => 'ディスクへの書き込みに失敗しました',
            UPLOAD_ERR_EXTENSION => 'PHPの拡張によってアップロードが停止されました'
        ];
        
        $message = $errorMessages[$file['error']] ?? '不明なアップロードエラー';
        throw new Exception($message . ' (エラーコード: ' . $file['error'] . ')');
    }
    
    // 4. ファイルサイズチェック
    if ($file['size'] > UPLOAD_MAX_SIZE) {
        $maxSizeMB = UPLOAD_MAX_SIZE / (1024 * 1024);
        throw new Exception("ファイルサイズが大きすぎます。{$maxSizeMB}MB以下にしてください。");
    }
    
    // 5. ファイル形式チェック
    $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($fileExtension, ALLOWED_FILE_TYPES)) {
        $allowedTypesStr = implode(', ', ALLOWED_FILE_TYPES);
        throw new Exception("許可されていないファイル形式です。許可形式: {$allowedTypesStr}");
    }
    
    // 6. システム健全性チェック
    $healthCheck = DatabaseFactory::systemHealthCheck();
    if (!$healthCheck['success']) {
        throw new Exception('システムの健全性チェックに失敗しました。管理者にお問い合わせください。');
    }
    
    if (DEBUG_MODE) {
        error_log("System Health Check: " . json_encode($healthCheck['health_check']));
    }
    
    // 7. ファイル処理
    $fileHandler = new FileUploadHandler();
    $uploadResult = $fileHandler->handleUpload($file, UPLOAD_DIR);
    
    if (!$uploadResult['success']) {
        throw new Exception('ファイル処理エラー: ' . $uploadResult['message']);
    }
    
    $uploadedFilePath = $uploadResult['file_path'];
    
    // 8. CSVインポート実行
    $db = DatabaseFactory::getDefaultConnection();
    $importer = new SmileyCSVImporter($db);
    
    // インポート開始ログ
    if (DEBUG_MODE) {
        error_log("CSV Import Start: " . $file['name'] . " (" . $file['size'] . " bytes)");
    }
    
    $importResult = $importer->import($uploadedFilePath);
    
    // インポート完了ログ
    if (DEBUG_MODE) {
        error_log("CSV Import Complete: " . json_encode($importResult['summary']));
    }
    
    // 9. 一時ファイル削除
    if (file_exists($uploadedFilePath)) {
        unlink($uploadedFilePath);
    }
    
    // 10. 成功レスポンス
    echo json_encode([
        'success' => true,
        'message' => 'CSVインポートが完了しました',
        'data' => [
            'batch_id' => $importResult['batch_id'],
            'file_info' => [
                'name' => $file['name'],
                'size' => $file['size'],
                'type' => $file['type']
            ],
            'import_summary' => [
                'total_records' => $importResult['summary']['total'],
                'success_records' => $importResult['summary']['success'],
                'error_records' => $importResult['summary']['error'],
                'duplicate_records' => $importResult['summary']['duplicate']
            ],
            'errors' => $importResult['errors'],
            'system_info' => [
                'environment' => ENVIRONMENT,
                'import_time' => date('Y-m-d H:i:s'),
                'memory_usage' => memory_get_peak_usage(true)
            ]
        ]
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    // エラーログに詳細記録
    $errorDetails = [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'request_info' => [
            'method' => $_SERVER['REQUEST_METHOD'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown'
        ],
        'file_info' => isset($_FILES['csvFile']) ? [
            'name' => $_FILES['csvFile']['name'],
            'size' => $_FILES['csvFile']['size'],
            'error' => $_FILES['csvFile']['error']
        ] : null
    ];
    
    error_log("CSV Import Error: " . json_encode($errorDetails));
    
    // 一時ファイルがあれば削除
    if (isset($uploadedFilePath) && file_exists($uploadedFilePath)) {
        unlink($uploadedFilePath);
    }
    
    // エラーレスポンス
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_code' => 'CSV_IMPORT_ERROR',
        'debug_info' => DEBUG_MODE ? [
            'file' => basename($e->getFile()),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
            'environment' => ENVIRONMENT,
            'php_version' => PHP_VERSION,
            'memory_usage' => memory_get_peak_usage(true)
        ] : null,
        'system_info' => [
            'timestamp' => date('Y-m-d H:i:s'),
            'environment' => ENVIRONMENT
        ]
    ], JSON_UNESCAPED_UNICODE);
}
?>
