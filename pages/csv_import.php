<?php
/**
 * 完全修正版 CSVインポートAPI
 * api/import.php - クラス重複エラー対策版
 */

// エラー表示設定
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// ヘッダー設定
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// OPTIONS リクエスト対応
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// エラーハンドリング関数
function sendError($message, $code = 400, $details = []) {
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'error' => $message,
        'details' => $details,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 成功レスポンス関数
function sendSuccess($data, $message = 'Success') {
    echo json_encode([
        'success' => true,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // 1. データベース設定読み込み（一度だけ）
    if (!defined('DB_CONFIG_LOADED')) {
        require_once '../config/database.php';
        define('DB_CONFIG_LOADED', true);
    }
    
    // 2. ClassLoader使用した安全な読み込み
    if (!class_exists('ClassLoader')) {
        require_once '../classes/ClassLoader.php';
    }
    
    // 3. 必要クラスを安全に読み込み
    $loader = ClassLoader::getInstance();
    $loadResult = ClassLoader::loadRequiredClasses('../classes');
    
    if (!$loadResult['success']) {
        sendError('クラス読み込みエラー', 500, $loadResult);
    }
    
    // 4. メソッド別処理
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            handleGetRequest();
            break;
            
        case 'POST':
            handlePostRequest();
            break;
            
        default:
            sendError('対応していないメソッドです', 405);
    }
    
} catch (Throwable $e) {
    // PHP7対応のエラーキャッチ
    sendError('システムエラーが発生しました', 500, [
        'error_message' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine(),
        'type' => get_class($e)
    ]);
}

/**
 * GET リクエスト処理
 */
function handleGetRequest() {
    $action = $_GET['action'] ?? 'status';
    
    switch ($action) {
        case 'test':
            // テスト応答
            sendSuccess([
                'message' => 'CSVインポートAPI正常稼働中',
                'version' => '2.0.0',
                'methods' => ['GET', 'POST'],
                'loaded_classes' => ClassLoader::getLoadedClasses(),
                'php_version' => PHP_VERSION,
                'memory_usage' => memory_get_usage(true) . ' bytes'
            ]);
            break;
            
        case 'status':
            // システム状態確認
            try {
                if (!class_exists('Database')) {
                    sendError('Databaseクラスが読み込まれていません', 500);
                }
                
                $db = new Database();
                
                // 基本テーブル存在確認
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
                
                sendSuccess([
                    'database_connection' => true,
                    'tables' => $tableStatus,
                    'loaded_classes' => ClassLoader::getLoadedClasses(),
                    'timestamp' => date('Y-m-d H:i:s')
                ], 'システム正常稼働中');
                
            } catch (Exception $e) {
                sendError('データベース接続エラー', 500, [
                    'error' => $e->getMessage(),
                    'loaded_classes' => ClassLoader::getLoadedClasses()
                ]);
            }
            break;
            
        case 'classes':
            // クラス読み込み状況確認
            sendSuccess([
                'loaded_classes' => ClassLoader::getLoadedClasses(),
                'declared_classes' => array_filter(get_declared_classes(), function($class) {
                    return in_array($class, ['Database', 'SmileyCSVImporter', 'SecurityHelper', 'ClassLoader']);
                })
            ]);
            break;
            
        default:
            sendError('不明なアクションです', 400);
    }
}

/**
 * POST リクエスト処理（CSVインポート）
 */
function handlePostRequest() {
    try {
        // 必要クラスの存在確認
        $requiredClasses = ['Database', 'SmileyCSVImporter', 'SecurityHelper'];
        foreach ($requiredClasses as $class) {
            if (!class_exists($class)) {
                sendError("必要なクラスが読み込まれていません: {$class}", 500);
            }
        }
        
        // セッション開始
        SecurityHelper::secureSessionStart();
        
        // ファイルアップロード検証
        if (!isset($_FILES['csv_file'])) {
            sendError('CSVファイルがアップロードされていません', 400);
        }
        
        $file = $_FILES['csv_file'];
        $fileValidation = SecurityHelper::validateFileUpload($file);
        
        if (!$fileValidation['valid']) {
            sendError('ファイル検証エラー', 400, ['errors' => $fileValidation['errors']]);
        }
        
        // データベース接続
        $db = new Database();
        
        // CSVインポーター初期化
        $importer = new SmileyCSVImporter($db);
        
        // インポートオプション設定
        $importOptions = [
            'encoding' => $_POST['encoding'] ?? 'auto',
            'overwrite' => isset($_POST['overwrite']) ? (bool)$_POST['overwrite'] : false,
            'validate_smiley' => true,
            'dry_run' => isset($_POST['dry_run']) ? (bool)$_POST['dry_run'] : false
        ];
        
        // CSVインポート実行
        $startTime = microtime(true);
        $result = $importer->importFile($file['tmp_name'], $importOptions);
        $processingTime = round(microtime(true) - $startTime, 2);
        
        // セキュリティログ記録
        SecurityHelper::logSecurityEvent('csv_import', [
            'filename' => $file['name'],
            'filesize' => $file['size'],
            'records_processed' => $result['stats']['total'] ?? 0,
            'processing_time' => $processingTime
        ]);
        
        // 成功レスポンス
        sendSuccess([
            'batch_id' => $result['batch_id'] ?? null,
            'stats' => [
                'total_records' => $result['stats']['total'] ?? 0,
                'success_records' => $result['stats']['success'] ?? 0,
                'error_records' => count($result['errors'] ?? []),
                'duplicate_records' => $result['stats']['duplicate'] ?? 0,
                'processing_time' => $processingTime . '秒'
            ],
            'errors' => array_slice($result['errors'] ?? [], 0, 10),
            'has_more_errors' => count($result['errors'] ?? []) > 10
        ], 'CSVインポートが完了しました');
        
    } catch (Throwable $e) {
        SecurityHelper::logSecurityEvent('csv_import_error', [
            'error_message' => $e->getMessage(),
            'file' => basename($e->getFile()),
            'line' => $e->getLine()
        ]);
        
        sendError('CSVインポート処理でエラーが発生しました', 500, [
            'error_message' => $e->getMessage(),
            'error_file' => basename($e->getFile()),
            'error_line' => $e->getLine(),
            'loaded_classes' => ClassLoader::getLoadedClasses()
        ]);
    }
}
?>
