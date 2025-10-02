<?php
/**
 * CSVインポートAPI
 * config/database.php統一版（classes/Database.php削除対応）
 */

// エラー表示設定
error_reporting(E_ALL);
ini_set('display_errors', 1);

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
// config/database.php（Databaseクラスはここに含まれている）
require_once '../config/database.php';

// 他の必要クラスを条件付きで読み込み
if (!class_exists('SmileyCSVImporter')) {
    require_once '../classes/SmileyCSVImporter.php';
}
if (!class_exists('FileUploadHandler')) {
    require_once '../classes/FileUploadHandler.php';
}
if (!class_exists('SecurityHelper')) {
    require_once '../classes/SecurityHelper.php';
}

/**
 * JSONレスポンス送信
 */
function sendResponse($success, $message, $data = null, $errors = []) {
    $response = [
        'success' => $success,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    if (!empty($errors)) {
        $response['errors'] = $errors;
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

try {
    // データベース接続（config/database.phpのDatabaseクラスを使用）
    $db = Database::getInstance();
    
    // POST リクエスト処理（CSVアップロード）
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        
        // ファイルアップロード確認（両方のフィールド名に対応）
        $uploadedFile = null;
        
        if (isset($_FILES['csv_file'])) {
            $uploadedFile = $_FILES['csv_file'];
        } elseif (isset($_FILES['csvFile'])) {
            $uploadedFile = $_FILES['csvFile'];
        } else {
            sendResponse(false, 'CSVファイルがアップロードされていません', [
                'hint' => 'csv_file または csvFile フィールドが必要です'
            ]);
        }
        
        if ($uploadedFile['error'] !== UPLOAD_ERR_OK) {
            $errorMessages = [
                UPLOAD_ERR_INI_SIZE => 'ファイルサイズがphp.iniの上限を超えています',
                UPLOAD_ERR_FORM_SIZE => 'ファイルサイズがフォームの上限を超えています',
                UPLOAD_ERR_PARTIAL => 'ファイルが部分的にしかアップロードされませんでした',
                UPLOAD_ERR_NO_FILE => 'ファイルがアップロードされませんでした',
                UPLOAD_ERR_NO_TMP_DIR => '一時ディレクトリが見つかりません',
                UPLOAD_ERR_CANT_WRITE => 'ディスクへの書き込みに失敗しました',
                UPLOAD_ERR_EXTENSION => 'PHPの拡張機能によってアップロードが停止されました'
            ];
            
            $errorMsg = $errorMessages[$uploadedFile['error']] ?? '不明なアップロードエラー';
            sendResponse(false, 'ファイルアップロードエラー: ' . $errorMsg);
        }
        
        // ファイル検証
        $fileHandler = new FileUploadHandler();
        $uploadResult = $fileHandler->uploadFile($uploadedFile);
        
        if (!$uploadResult['success']) {
            sendResponse(false, 'ファイルアップロードエラー', [
                'errors' => $uploadResult['errors']
            ]);
        }
        
        // CSVインポート実行
        $importer = new SmileyCSVImporter();  // 引数なしで初期化
        $importOptions = [
            'encoding' => $_POST['encoding'] ?? 'UTF-8',
            'delimiter' => ',',
            'has_header' => true
        ];
        
        $startTime = microtime(true);
        $importResult = $importer->importCSV($uploadResult['filepath'], $importOptions);  // importCSV()を呼び出し
        $processingTime = round(microtime(true) - $startTime, 2);
        
        // 一時ファイル削除
        if (file_exists($uploadResult['filepath'])) {
            unlink($uploadResult['filepath']);
        }
        
        // 結果レスポンス
        if ($importResult['success']) {
            sendResponse(true, 'CSVインポートが正常に完了しました', [
                'batch_id' => $importResult['batch_id'],
                'filename' => $uploadedFile['name'],
                'stats' => [
                    'total_records' => $importResult['stats']['total_rows'] ?? 0,
                    'processed_records' => $importResult['stats']['processed_rows'] ?? 0,
                    'success_records' => $importResult['stats']['success_rows'] ?? 0,
                    'error_records' => $importResult['stats']['error_rows'] ?? 0,
                    'new_companies' => $importResult['stats']['new_companies'] ?? 0,
                    'new_departments' => $importResult['stats']['new_departments'] ?? 0,
                    'new_users' => $importResult['stats']['new_users'] ?? 0,
                    'new_suppliers' => $importResult['stats']['new_suppliers'] ?? 0,
                    'new_products' => $importResult['stats']['new_products'] ?? 0,
                    'duplicate_orders' => $importResult['stats']['duplicate_orders'] ?? 0,
                    'processing_time' => $processingTime . '秒'
                ],
                'summary_message' => $importResult['summary_message'] ?? '',
                'errors' => array_slice($importResult['errors'] ?? [], 0, 10) // 最初の10件
            ]);
        } else {
            sendResponse(false, 'CSVインポートでエラーが発生しました', [
                'batch_id' => $importResult['batch_id'] ?? null,
                'filename' => $uploadedFile['name'],
                'stats' => $importResult['stats'] ?? [],
                'summary_message' => $importResult['summary_message'] ?? '',
                'errors' => $importResult['errors'] ?? []
            ]);
        }
    }
    
    // GET リクエスト処理（ステータス確認等）
    elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
        
        $action = $_GET['action'] ?? 'status';
        
        switch ($action) {
            case 'status':
                // システム状態確認
                $pdo = $db->getConnection();
                
                // テーブル存在確認
                $tables = ['orders', 'companies', 'departments', 'users', 'suppliers', 'products', 'import_logs'];
                $tableStatus = [];
                
                foreach ($tables as $table) {
                    try {
                        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
                        $tableStatus[$table] = $stmt->rowCount() > 0;
                    } catch (Exception $e) {
                        $tableStatus[$table] = false;
                    }
                }
                
                $status = [
                    'database_connected' => true,
                    'database_class' => get_class($db),
                    'database_source' => 'config/database.php',
                    'tables_exist' => $tableStatus,
                    'php_extensions' => [
                        'pdo' => extension_loaded('pdo'),
                        'pdo_mysql' => extension_loaded('pdo_mysql'),
                        'mbstring' => extension_loaded('mbstring'),
                        'fileinfo' => extension_loaded('fileinfo')
                    ],
                    'required_classes' => [
                        'Database' => class_exists('Database'),
                        'SmileyCSVImporter' => class_exists('SmileyCSVImporter'),
                        'FileUploadHandler' => class_exists('FileUploadHandler'),
                        'SecurityHelper' => class_exists('SecurityHelper')
                    ]
                ];
                
                sendResponse(true, 'システム状態を確認しました', $status);
                break;
                
            case 'history':
                // インポート履歴取得
                $limit = min(50, max(1, intval($_GET['limit'] ?? 20)));
                $offset = max(0, intval($_GET['offset'] ?? 0));
                
                $stmt = $db->query(
                    "SELECT batch_id, file_path, total_records, success_records, 
                           error_records, duplicate_records, processing_time_seconds, 
                           created_at 
                    FROM import_logs 
                    ORDER BY created_at DESC 
                    LIMIT ? OFFSET ?",
                    [$limit, $offset]
                );
                
                $history = $stmt->fetchAll();
                
                sendResponse(true, 'インポート履歴を取得しました', [
                    'history' => $history,
                    'pagination' => [
                        'limit' => $limit,
                        'offset' => $offset
                    ]
                ]);
                break;
                
            case 'batch_detail':
                // バッチ詳細取得
                $batchId = $_GET['batch_id'] ?? '';
                
                if (empty($batchId)) {
                    sendResponse(false, 'バッチIDが指定されていません');
                }
                
                $stmt = $db->query("SELECT * FROM import_logs WHERE batch_id = ?", [$batchId]);
                $batchDetail = $stmt->fetch();
                
                if (!$batchDetail) {
                    sendResponse(false, '指定されたバッチが見つかりません');
                }
                
                // エラー詳細をデコード
                if (!empty($batchDetail['error_details'])) {
                    $batchDetail['error_details'] = json_decode($batchDetail['error_details'], true);
                }
                
                sendResponse(true, 'バッチ詳細を取得しました', $batchDetail);
                break;
                
            default:
                sendResponse(false, '不明なアクションです', [
                    'available_actions' => ['status', 'history', 'batch_detail']
                ]);
        }
    }
    
    else {
        sendResponse(false, 'サポートされていないHTTPメソッドです');
    }
    
} catch (Exception $e) {
    // エラーログ記録
    error_log("CSVインポートAPI エラー: " . $e->getMessage());
    error_log("ファイル: " . $e->getFile() . " 行: " . $e->getLine());
    error_log("トレース: " . $e->getTraceAsString());
    
    sendResponse(false, 'システムエラーが発生しました', [
        'error_message' => $e->getMessage(),
        'error_file' => basename($e->getFile()),
        'error_line' => $e->getLine()
    ], [[
        'type' => 'system_error',
        'message' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]]);
}
?>
