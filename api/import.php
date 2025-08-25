<?php
/**
 * CSVインポートAPI（根本修正版）
 * Database Singleton パターン対応
 */

// エラー表示設定
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// レスポンスヘッダー設定
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// OPTIONSリクエスト対応
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 必要ファイル読み込み
require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/SmileyCSVImporter.php';
require_once '../classes/FileUploadHandler.php';
require_once '../classes/SecurityHelper.php';

/**
 * JSONレスポンス送信
 */
function sendResponse($success, $message, $data = [], $code = 200) {
    http_response_code($code);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s'),
        'version' => '4.0.0-singleton'
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

try {
    // リクエスト処理
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
    // エラーログ記録
    error_log("CSVインポートAPI システムエラー: " . $e->getMessage());
    error_log("ファイル: " . $e->getFile() . " 行: " . $e->getLine());
    
    sendResponse(false, 'システムエラーが発生しました', [
        'error' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ], 500);
}

/**
 * GET リクエスト処理
 */
function handleGetRequest() {
    $action = $_GET['action'] ?? 'status';
    
    switch ($action) {
        case 'test':
            sendResponse(true, 'CSVインポートAPI正常稼働中 - Singleton対応版', [
                'version' => '4.0.0-singleton',
                'methods' => ['GET', 'POST'],
                'php_version' => PHP_VERSION,
                'database_pattern' => 'Singleton',
                'endpoints' => [
                    'GET ?action=test' => 'API動作確認',
                    'GET ?action=status' => 'システム状況確認',
                    'GET ?action=db_test' => 'データベース接続確認',
                    'POST with csv_file' => 'CSVファイルインポート'
                ]
            ]);
            break;
            
        case 'status':
            try {
                // Database Singleton接続確認
                $db = Database::getInstance();
                
                // 基本テーブル確認
                $tables = ['companies', 'departments', 'users', 'suppliers', 'products', 'orders', 'import_logs'];
                $tableStatus = [];
                
                foreach ($tables as $table) {
                    try {
                        $tableStatus[$table] = $db->tableExists($table);
                    } catch (Exception $e) {
                        $tableStatus[$table] = false;
                    }
                }
                
                sendResponse(true, 'システム正常稼働中', [
                    'database_connection' => $db->isConnected(),
                    'database_pattern' => 'Singleton',
                    'database_class' => get_class($db),
                    'pdo_connection' => $db->getConnection() !== null,
                    'tables' => $tableStatus,
                    'required_classes' => [
                        'Database' => class_exists('Database'),
                        'SecurityHelper' => class_exists('SecurityHelper'),
                        'SmileyCSVImporter' => class_exists('SmileyCSVImporter'),
                        'FileUploadHandler' => class_exists('FileUploadHandler')
                    ],
                    'php_extensions' => [
                        'pdo' => extension_loaded('pdo'),
                        'pdo_mysql' => extension_loaded('pdo_mysql'),
                        'mbstring' => extension_loaded('mbstring'),
                        'fileinfo' => extension_loaded('fileinfo')
                    ]
                ]);
                
            } catch (Exception $e) {
                sendResponse(false, 'システム異常', [
                    'error' => $e->getMessage(),
                    'database_pattern' => 'Singleton',
                    'connection_attempt' => 'Database::getInstance()'
                ], 500);
            }
            break;
            
        case 'db_test':
            try {
                $db = Database::getInstance();
                
                // 接続テスト
                $connectionTest = $db->testConnection();
                
                // 基本情報取得
                $systemInfo = $db->getSystemInfo();
                $dbCheck = $db->checkDatabase();
                
                sendResponse(true, 'データベース接続成功', [
                    'connection_method' => 'Database::getInstance()',
                    'connection_test' => $connectionTest,
                    'system_info' => $systemInfo,
                    'database_info' => $dbCheck,
                    'pdo_available' => $db->getConnection() !== null
                ]);
                
            } catch (Exception $e) {
                sendResponse(false, 'データベース接続エラー', [
                    'error' => $e->getMessage(),
                    'connection_method' => 'Database::getInstance()'
                ], 500);
            }
            break;
            
        case 'history':
            try {
                $db = Database::getInstance();
                $limit = min(50, max(1, intval($_GET['limit'] ?? 20)));
                $offset = max(0, intval($_GET['offset'] ?? 0));
                
                $sql = "SELECT batch_id, file_path, total_records, success_records, 
                              error_records, duplicate_records, processing_time_seconds, 
                              created_at, status 
                        FROM import_logs 
                        ORDER BY created_at DESC 
                        LIMIT ? OFFSET ?";
                
                $history = $db->fetchAll($sql, [$limit, $offset]);
                
                sendResponse(true, 'インポート履歴を取得しました', [
                    'history' => $history,
                    'pagination' => [
                        'limit' => $limit,
                        'offset' => $offset
                    ]
                ]);
                
            } catch (Exception $e) {
                sendResponse(false, 'インポート履歴取得エラー', [
                    'error' => $e->getMessage()
                ], 500);
            }
            break;
            
        default:
            sendResponse(false, '不明なアクションです', [
                'available_actions' => ['test', 'status', 'db_test', 'history']
            ], 400);
    }
}

/**
 * POST リクエスト処理（CSVインポート）
 */
function handlePostRequest() {
    try {
        // 1. セキュリティヘッダー設定
        SecurityHelper::setSecurityHeaders();
        
        // 2. レート制限チェック
        if (!SecurityHelper::checkRateLimit('csv_import', 10, 3600)) {
            sendResponse(false, 'アップロード頻度制限に達しました。1時間後に再試行してください。', [], 429);
        }
        
        // 3. ファイル検証
        if (!isset($_FILES['csv_file'])) {
            sendResponse(false, 'CSVファイルがアップロードされていません', [], 400);
        }
        
        $file = $_FILES['csv_file'];
        $fileValidation = SecurityHelper::validateFileUpload($file);
        
        if (!$fileValidation['valid']) {
            SecurityHelper::logSecurityEvent('csv_upload_validation_failed', [
                'errors' => $fileValidation['errors'],
                'filename' => $file['name'] ?? 'unknown'
            ]);
            
            sendResponse(false, 'ファイル検証エラー', [
                'errors' => $fileValidation['errors']
            ], 400);
        }
        
        // 4. Database Singleton接続
        $db = Database::getInstance();
        
        if (!$db->isConnected()) {
            throw new Exception('データベース接続に失敗しました: ' . $db->getLastError());
        }
        
        // 5. CSVインポーター初期化（Singleton対応）
        $importer = new SmileyCSVImporter($db);
        
        // 6. インポートオプション
        $options = [
            'encoding' => $_POST['encoding'] ?? 'auto',
            'overwrite' => isset($_POST['overwrite']) ? (bool)$_POST['overwrite'] : false,
            'validate_smiley' => true,
            'dry_run' => isset($_POST['dry_run']) ? (bool)$_POST['dry_run'] : false,
            'max_size' => 50 * 1024 * 1024 // 50MB
        ];
        
        // 7. インポート実行
        $startTime = microtime(true);
        
        // 一時ファイルとして保存
        $tempFile = sys_get_temp_dir() . '/csv_import_' . uniqid() . '.csv';
        if (!move_uploaded_file($file['tmp_name'], $tempFile)) {
            throw new Exception('一時ファイルの作成に失敗しました');
        }
        
        try {
            $result = $importer->importFile($tempFile, $options);
            $processingTime = round(microtime(true) - $startTime, 2);
            
            // 8. ログ記録
            SecurityHelper::logSecurityEvent('csv_import_completed', [
                'filename' => $file['name'],
                'filesize' => $file['size'],
                'batch_id' => $result['batch_id'],
                'records_total' => $result['stats']['total'] ?? 0,
                'records_success' => $result['stats']['success'] ?? 0,
                'records_error' => $result['stats']['error'] ?? 0,
                'processing_time' => $processingTime
            ]);
            
            // 9. 成功レスポンス
            sendResponse(
                $result['success'], 
                $result['success'] ? 'CSVインポートが正常に完了しました' : 'CSVインポートが部分的に完了しました',
                [
                    'batch_id' => $result['batch_id'],
                    'filename' => $file['name'],
                    'stats' => [
                        'total_records' => $result['stats']['total'] ?? 0,
                        'success_records' => $result['stats']['success'] ?? 0,
                        'error_records' => $result['stats']['error'] ?? 0,
                        'duplicate_records' => $result['stats']['duplicate'] ?? 0,
                        'new_companies' => $result['stats']['new_companies'] ?? 0,
                        'new_users' => $result['stats']['new_users'] ?? 0,
                        'processing_time' => $processingTime . '秒'
                    ],
                    'errors' => array_slice($result['errors'] ?? [], 0, 10), // 最初の10件のみ
                    'has_more_errors' => count($result['errors'] ?? []) > 10,
                    'import_summary' => [
                        'database_connection' => 'Database::getInstance() - Success',
                        'validation_passed' => true,
                        'encoding_detected' => $result['encoding'] ?? 'unknown',
                        'singleton_pattern' => true
                    ]
                ]
            );
            
        } finally {
            // 一時ファイル削除
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
        
    } catch (Throwable $e) {
        // エラーログ記録
        SecurityHelper::logSecurityEvent('csv_import_error', [
            'error_message' => $e->getMessage(),
            'file' => basename($e->getFile()),
            'line' => $e->getLine(),
            'uploaded_file' => $_FILES['csv_file']['name'] ?? 'unknown'
        ]);
        
        sendResponse(false, 'CSVインポート中にエラーが発生しました', [
            'error_message' => $e->getMessage(),
            'error_file' => basename($e->getFile()),
            'error_line' => $e->getLine(),
            'troubleshooting' => [
                'database_connection' => 'Database::getInstance()を使用してください',
                'check_csv_format' => 'CSVファイルの形式（23フィールド）を確認してください',
                'check_file_encoding' => 'ファイルのエンコーディングを確認してください（SJIS-win推奨）',
                'check_file_size' => 'ファイルサイズが50MB以下であることを確認してください'
            ]
        ], 500);
    }
}
?>
