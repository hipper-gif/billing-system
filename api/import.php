<?php
/**
 * 修正版 CSVインポートAPI - データベース接続エラー完全対策版
 * 
 * 修正内容:
 * - Database::getInstance() エラーハンドリング強化
 * - 接続状態確認の追加
 * - 詳細エラー情報の出力
 * 
 * @version 4.0.0
 */

// エラー設定
error_reporting(E_ALL);
ini_set('display_errors', 0); // 本番では0
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

/**
 * 安全なレスポンス送信
 */
function sendResponse($success, $message, $data = [], $code = 200) {
    http_response_code($code);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s'),
        'version' => '4.0.0',
        'debug_info' => [
            'method' => $_SERVER['REQUEST_METHOD'],
            'php_version' => PHP_VERSION,
            'memory_usage' => memory_get_usage(true),
            'max_execution_time' => ini_get('max_execution_time')
        ]
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // 1. 設定ファイル読み込み（エラーハンドリング強化）
    $configFile = __DIR__ . '/../config/database.php';
    if (!file_exists($configFile)) {
        throw new Exception("設定ファイルが見つかりません: {$configFile}");
    }
    
    require_once $configFile;
    
    // 2. 必要な定数確認
    $requiredConstants = ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'];
    $missingConstants = [];
    
    foreach ($requiredConstants as $constant) {
        if (!defined($constant)) {
            $missingConstants[] = $constant;
        }
    }
    
    if (!empty($missingConstants)) {
        throw new Exception("データベース設定が不完全: " . implode(', ', $missingConstants));
    }
    
    // 3. 必要クラス読み込み（順序重要）
    $requiredClasses = [
        'Database' => __DIR__ . '/../classes/Database.php',
        'SecurityHelper' => __DIR__ . '/../classes/SecurityHelper.php',
        'SmileyCSVImporter' => __DIR__ . '/../classes/SmileyCSVImporter.php'
    ];
    
    foreach ($requiredClasses as $className => $classFile) {
        if (!file_exists($classFile)) {
            throw new Exception("{$className}クラスファイルが見つかりません: {$classFile}");
        }
        
        if (!class_exists($className)) {
            require_once $classFile;
        }
        
        if (!class_exists($className)) {
            throw new Exception("{$className}クラスの読み込みに失敗しました");
        }
    }
    
    // 4. リクエスト処理
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
    // 詳細エラー情報
    $errorData = [
        'error_type' => get_class($e),
        'error_message' => $e->getMessage(),
        'error_file' => basename($e->getFile()),
        'error_line' => $e->getLine(),
        'system_info' => [
            'php_version' => PHP_VERSION,
            'memory_limit' => ini_get('memory_limit'),
            'memory_usage' => memory_get_usage(true),
            'loaded_extensions' => [
                'mbstring' => extension_loaded('mbstring'),
                'pdo' => extension_loaded('pdo'),
                'pdo_mysql' => extension_loaded('pdo_mysql')
            ]
        ]
    ];
    
    // 開発環境では詳細エラーを表示
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        $errorData['stack_trace'] = $e->getTraceAsString();
    }
    
    sendResponse(false, 'システムエラーが発生しました', $errorData, 500);
}

/**
 * GET リクエスト処理（改良版）
 */
function handleGetRequest() {
    $action = $_GET['action'] ?? 'status';
    
    switch ($action) {
        case 'test':
            sendResponse(true, 'CSVインポートAPI正常稼働中 - 修正版 4.0.0', [
                'version' => '4.0.0',
                'methods' => ['GET', 'POST'],
                'php_version' => PHP_VERSION,
                'system_status' => getSystemStatus(),
                'endpoints' => [
                    'GET ?action=test' => 'API動作確認',
                    'GET ?action=status' => 'システム状況確認',
                    'GET ?action=db_test' => 'データベース接続確認',
                    'POST with csv_file' => 'CSVファイルインポート'
                ]
            ]);
            break;
            
        case 'status':
            $systemStatus = getSystemStatus();
            sendResponse(
                $systemStatus['overall_status'] === 'OK',
                'システム状態確認完了',
                $systemStatus
            );
            break;
            
        case 'db_test':
            $dbStatus = testDatabaseConnection();
            sendResponse(
                $dbStatus['success'],
                $dbStatus['message'],
                $dbStatus['data']
            );
            break;
            
        default:
            sendResponse(false, '不明なアクションです', [
                'available_actions' => ['test', 'status', 'db_test']
            ], 400);
    }
}

/**
 * POST リクエスト処理（改良版）
 */
function handlePostRequest() {
    try {
        // 1. ファイル確認
        if (!isset($_FILES['csvFile'])) {
            sendResponse(false, 'CSVファイルがアップロードされていません', [
                'received_files' => array_keys($_FILES),
                'post_data' => array_keys($_POST)
            ], 400);
        }
        
        $file = $_FILES['csvFile'];
        
        // 2. ファイル検証
        $validation = SecurityHelper::validateFileUpload($file);
        if (!$validation['valid']) {
            sendResponse(false, 'ファイル検証エラー', [
                'errors' => $validation['errors']
            ], 400);
        }
        
        // 3. データベース接続確認
        $dbStatus = testDatabaseConnection();
        if (!$dbStatus['success']) {
            sendResponse(false, 'データベース接続エラー', $dbStatus, 500);
        }
        
        $db = $dbStatus['data']['db_instance'];
        
        // 4. CSVインポーター初期化
        $importer = new SmileyCSVImporter($db);
        
        // 5. インポート実行
        $startTime = microtime(true);
        $result = $importer->importFile($file['tmp_name'], [
            'encoding' => $_POST['encoding'] ?? 'auto',
            'overwrite' => isset($_POST['overwrite']) ? (bool)$_POST['overwrite'] : false,
            'validate_smiley' => true,
            'dry_run' => isset($_POST['dry_run']) ? (bool)$_POST['dry_run'] : false
        ]);
        
        $processingTime = round(microtime(true) - $startTime, 2);
        
        // 6. 成功レスポンス
        sendResponse(true, 'CSVインポートが正常に完了しました', [
            'batch_id' => $result['batch_id'] ?? null,
            'filename' => $file['name'],
            'filesize' => $file['size'],
            'encoding_detected' => $result['encoding'] ?? 'unknown',
            'stats' => [
                'total_records' => $result['stats']['total'] ?? 0,
                'success_records' => $result['stats']['success'] ?? 0,
                'error_records' => $result['stats']['error'] ?? 0,
                'duplicate_records' => $result['stats']['duplicate'] ?? 0,
                'processing_time' => $processingTime . '秒'
            ],
            'errors' => array_slice($result['errors'] ?? [], 0, 10), // 最初の10件のみ
            'has_more_errors' => count($result['errors'] ?? []) > 10,
            'debug_log' => $result['debug_log'] ?? []
        ]);
        
    } catch (Throwable $e) {
        sendResponse(false, 'CSVインポート中にエラーが発生しました', [
            'error_type' => get_class($e),
            'error_message' => $e->getMessage(),
            'error_file' => basename($e->getFile()),
            'error_line' => $e->getLine(),
            'uploaded_file' => $_FILES['csvFile']['name'] ?? 'unknown'
        ], 500);
    }
}

/**
 * システム状態確認（改良版）
 */
function getSystemStatus() {
    $status = [
        'overall_status' => 'OK',
        'timestamp' => date('Y-m-d H:i:s'),
        'checks' => []
    ];
    
    // PHP基本チェック
    $status['checks']['php'] = [
        'version' => PHP_VERSION,
        'memory_limit' => ini_get('memory_limit'),
        'memory_usage' => memory_get_usage(true),
        'max_execution_time' => ini_get('max_execution_time'),
        'file_uploads' => ini_get('file_uploads') ? 'enabled' : 'disabled'
    ];
    
    // 拡張機能チェック
    $requiredExtensions = ['mbstring', 'pdo', 'pdo_mysql'];
    $status['checks']['extensions'] = [];
    
    foreach ($requiredExtensions as $ext) {
        $loaded = extension_loaded($ext);
        $status['checks']['extensions'][$ext] = $loaded ? 'OK' : 'MISSING';
        
        if (!$loaded) {
            $status['overall_status'] = 'ERROR';
        }
    }
    
    // ファイル存在チェック
    $requiredFiles = [
        '../config/database.php',
        '../classes/Database.php',
        '../classes/SmileyCSVImporter.php',
        '../classes/SecurityHelper.php'
    ];
    
    $status['checks']['files'] = [];
    foreach ($requiredFiles as $file) {
        $exists = file_exists($file);
        $status['checks']['files'][$file] = $exists ? 'EXISTS' : 'MISSING';
        
        if (!$exists) {
            $status['overall_status'] = 'ERROR';
        }
    }
    
    // データベース接続チェック
    $dbStatus = testDatabaseConnection();
    $status['checks']['database'] = $dbStatus['data'];
    
    if (!$dbStatus['success']) {
        $status['overall_status'] = 'ERROR';
    }
    
    return $status;
}

/**
 * データベース接続テスト（改良版）
 */
function testDatabaseConnection() {
    try {
        // Database インスタンス作成
        $db = Database::getInstance();
        
        if (!$db->isConnected()) {
            throw new Exception("Database::getInstance() は成功したが、接続状態が false");
        }
        
        // 接続テスト実行
        $testResult = $db->fetchOne("SELECT 1 as test, NOW() as current_time, DATABASE() as db_name, VERSION() as version");
        
        if (!$testResult) {
            throw new Exception("データベーステストクエリの実行に失敗");
        }
        
        // システム情報取得
        $systemInfo = $db->getSystemInfo();
        
        return [
            'success' => true,
            'message' => 'データベース接続成功',
            'data' => [
                'db_instance' => $db,
                'connection_status' => 'connected',
                'test_query_result' => $testResult,
                'system_info' => $systemInfo
            ]
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'データベース接続エラー',
            'data' => [
                'error' => $e->getMessage(),
                'error_file' => basename($e->getFile()),
                'error_line' => $e->getLine(),
                'db_config' => [
                    'host' => defined('DB_HOST') ? DB_HOST : 'undefined',
                    'database' => defined('DB_NAME') ? DB_NAME : 'undefined',
                    'username' => defined('DB_USER') ? DB_USER : 'undefined'
                ]
            ]
        ];
    }
}
?>
