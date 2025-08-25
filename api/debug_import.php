<?php
/**
 * CSVインポートAPI デバッグ専用ツール
 * api/debug_import.php
 */

// エラー表示を有効化（デバッグ用）
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// 出力バッファリング開始
ob_start();

// JSONヘッダー設定
header('Content-Type: application/json; charset=utf-8');

function debugResponse($step, $success, $message, $data = [], $error = null) {
    $response = [
        'debug_step' => $step,
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s'),
        'php_version' => PHP_VERSION,
        'memory_usage' => memory_get_usage(true),
        'error' => $error
    ];
    
    // 既存の出力をクリア
    ob_clean();
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

try {
    // Step 1: 基本環境確認
    debugResponse('step1_environment', true, '基本環境確認完了', [
        'current_directory' => __DIR__,
        'file_exists_config' => file_exists('../config/database.php'),
        'file_exists_database' => file_exists('../classes/Database.php'),
        'file_exists_security' => file_exists('../classes/SecurityHelper.php'),
        'file_exists_importer' => file_exists('../classes/SmileyCSVImporter.php'),
        'file_exists_filehandler' => file_exists('../classes/FileUploadHandler.php'),
        'request_method' => $_SERVER['REQUEST_METHOD'],
        'post_data' => !empty($_POST) ? array_keys($_POST) : 'empty',
        'files_data' => !empty($_FILES) ? array_keys($_FILES) : 'empty'
    ]);

} catch (Throwable $e) {
    debugResponse('step1_environment', false, 'Step 1 失敗: ' . $e->getMessage(), [], [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
}

try {
    // Step 2: 設定ファイル読み込み
    require_once '../config/database.php';
    
    debugResponse('step2_config', true, '設定ファイル読み込み完了', [
        'config_loaded' => true,
        'constants_defined' => defined('DB_HOST') ? 'DB_HOST defined' : 'DB_HOST not defined'
    ]);

} catch (Throwable $e) {
    debugResponse('step2_config', false, 'Step 2 失敗: ' . $e->getMessage(), [], [
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}

try {
    // Step 3: クラスファイル読み込み
    if (!class_exists('Database')) {
        require_once '../classes/Database.php';
    }
    
    debugResponse('step3_database_class', true, 'Databaseクラス読み込み完了', [
        'class_exists' => class_exists('Database'),
        'class_methods' => class_exists('Database') ? get_class_methods('Database') : []
    ]);

} catch (Throwable $e) {
    debugResponse('step3_database_class', false, 'Step 3 失敗: ' . $e->getMessage(), [], [
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}

try {
    // Step 4: Database接続テスト
    $db = Database::getInstance();
    
    debugResponse('step4_database_connection', true, 'Database接続成功', [
        'database_instance' => get_class($db),
        'connection_test' => 'success'
    ]);

} catch (Throwable $e) {
    debugResponse('step4_database_connection', false, 'Step 4 失敗: ' . $e->getMessage(), [], [
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}

try {
    // Step 5: SecurityHelper読み込み
    if (!class_exists('SecurityHelper')) {
        require_once '../classes/SecurityHelper.php';
    }
    
    debugResponse('step5_security_helper', true, 'SecurityHelper読み込み完了', [
        'class_exists' => class_exists('SecurityHelper'),
        'class_methods' => class_exists('SecurityHelper') ? array_slice(get_class_methods('SecurityHelper'), 0, 10) : []
    ]);

} catch (Throwable $e) {
    debugResponse('step5_security_helper', false, 'Step 5 失敗: ' . $e->getMessage(), [], [
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}

try {
    // Step 6: FileUploadHandler読み込み
    if (!class_exists('FileUploadHandler')) {
        require_once '../classes/FileUploadHandler.php';
    }
    
    debugResponse('step6_file_upload_handler', true, 'FileUploadHandler読み込み完了', [
        'class_exists' => class_exists('FileUploadHandler'),
        'class_methods' => class_exists('FileUploadHandler') ? array_slice(get_class_methods('FileUploadHandler'), 0, 10) : []
    ]);

} catch (Throwable $e) {
    debugResponse('step6_file_upload_handler', false, 'Step 6 失敗: ' . $e->getMessage(), [], [
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}

try {
    // Step 7: SmileyCSVImporter読み込み
    if (!class_exists('SmileyCSVImporter')) {
        require_once '../classes/SmileyCSVImporter.php';
    }
    
    debugResponse('step7_smiley_csv_importer', true, 'SmileyCSVImporter読み込み完了', [
        'class_exists' => class_exists('SmileyCSVImporter'),
        'class_methods' => class_exists('SmileyCSVImporter') ? array_slice(get_class_methods('SmileyCSVImporter'), 0, 10) : []
    ]);

} catch (Throwable $e) {
    debugResponse('step7_smiley_csv_importer', false, 'Step 7 失敗: ' . $e->getMessage(), [], [
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}

try {
    // Step 8: ファイルアップロード処理テスト
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['csv_file'])) {
        
        $file = $_FILES['csv_file'];
        
        // FileUploadHandlerインスタンス作成
        $fileHandler = new FileUploadHandler();
        
        debugResponse('step8_file_processing', true, 'ファイル処理テスト完了', [
            'uploaded_file' => [
                'name' => $file['name'],
                'size' => $file['size'],
                'type' => $file['type'],
                'error' => $file['error']
            ],
            'file_handler_instance' => get_class($fileHandler)
        ]);
        
    } else {
        debugResponse('step8_file_processing', true, 'ファイルアップロードなし（GETリクエストまたはファイル未選択）', [
            'request_method' => $_SERVER['REQUEST_METHOD'],
            'files_available' => !empty($_FILES)
        ]);
    }

} catch (Throwable $e) {
    debugResponse('step8_file_processing', false, 'Step 8 失敗: ' . $e->getMessage(), [], [
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}

// 全ステップ完了
debugResponse('final_success', true, '全デバッグステップ完了 - システムは正常に動作可能', [
    'all_classes_loaded' => [
        'Database' => class_exists('Database'),
        'SecurityHelper' => class_exists('SecurityHelper'),
        'FileUploadHandler' => class_exists('FileUploadHandler'),
        'SmileyCSVImporter' => class_exists('SmileyCSVImporter')
    ],
    'ready_for_csv_import' => true,
    'next_action' => 'このデバッグが成功した場合、api/import.phpの問題を修正できます'
]);

?>
