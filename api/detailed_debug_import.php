<?php
/**
 * 詳細CSVインポートデバッグAPI
 * api/detailed_debug_import.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

ob_start();
header('Content-Type: application/json; charset=utf-8');

function debugResponse($step, $success, $message, $data = [], $error = null) {
    $response = [
        'debug_step' => $step,
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s'),
        'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2) . 'MB',
        'error' => $error
    ];
    
    ob_clean();
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

try {
    // 必要ファイル読み込み
    require_once '../config/database.php';
    require_once '../classes/Database.php';
    require_once '../classes/SecurityHelper.php';
    require_once '../classes/FileUploadHandler.php';
    require_once '../classes/SmileyCSVImporter.php';
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
        
        $file = $_FILES['csv_file'];
        
        // Step 1: ファイル基本情報
        debugResponse('file_info', true, 'アップロードファイル情報取得', [
            'filename' => $file['name'],
            'size' => $file['size'],
            'type' => $file['type'],
            'error' => $file['error'],
            'tmp_name' => $file['tmp_name'],
            'tmp_file_exists' => file_exists($file['tmp_name']),
            'tmp_file_readable' => is_readable($file['tmp_name'])
        ]);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        debugResponse('no_file', false, 'ファイルがアップロードされていません', [
            'POST_data' => array_keys($_POST),
            'FILES_data' => array_keys($_FILES)
        ]);
        
    } else {
        // GET リクエスト：システム詳細確認
        try {
            // Database接続テスト
            $db = Database::getInstance();
            
            // テーブル存在確認
            $tables = ['companies', 'departments', 'users', 'suppliers', 'products', 'orders', 'import_logs'];
            $tableStatus = [];
            
            foreach ($tables as $table) {
                try {
                    $stmt = $db->query("SELECT COUNT(*) as count FROM {$table}");
                    $result = $stmt->fetch();
                    $tableStatus[$table] = [
                        'exists' => true,
                        'count' => $result['count']
                    ];
                } catch (Exception $e) {
                    $tableStatus[$table] = [
                        'exists' => false,
                        'error' => $e->getMessage()
                    ];
                }
            }
            
            // データベース詳細情報
            try {
                $stmt = $db->query("SELECT DATABASE() as db_name, USER() as user_name, VERSION() as version");
                $dbInfo = $stmt->fetch();
            } catch (Exception $e) {
                $dbInfo = ['error' => $e->getMessage()];
            }
            
            debugResponse('system_status', true, 'システム詳細状況', [
                'database_info' => $dbInfo,
                'table_status' => $tableStatus,
                'class_methods' => [
                    'Database' => get_class_methods('Database'),
                    'SmileyCSVImporter' => array_slice(get_class_methods('SmileyCSVImporter'), 0, 15),
                    'FileUploadHandler' => get_class_methods('FileUploadHandler'),
                    'SecurityHelper' => array_slice(get_class_methods('SecurityHelper'), 0, 10)
                ],
                'php_extensions' => [
                    'mysqli' => extension_loaded('mysqli'),
                    'pdo' => extension_loaded('pdo'),
                    'pdo_mysql' => extension_loaded('pdo_mysql'),
                    'mbstring' => extension_loaded('mbstring'),
                    'iconv' => extension_loaded('iconv')
                ]
            ]);
            
        } catch (Exception $e) {
            debugResponse('system_error', false, 'システム確認エラー', [], [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
        }
    }
    
} catch (Throwable $e) {
    debugResponse('critical_error', false, 'クリティカルエラー', [], [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => array_slice($e->getTrace(), 0, 5)
    ]);
}
?>
