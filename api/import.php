<?php
/**
 * CSVインポートAPI（v5.0完全対応版）
 * メソッド統一・自己完結原則準拠
 */

// エラー表示設定（デバッグ用）
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
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/SmileyCSVImporter.php';
require_once __DIR__ . '/../classes/FileUploadHandler.php';
require_once __DIR__ . '/../classes/SecurityHelper.php';

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
    // POST リクエスト処理（CSVアップロード）
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        
        // ファイルアップロード確認
        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            sendResponse(false, 'CSVファイルがアップロードされていません');
        }
        
        $uploadedFile = $_FILES['csv_file'];
        
        // ファイル検証
        $fileHandler = new FileUploadHandler();
        $uploadResult = $fileHandler->uploadFile($uploadedFile);
        
        if (!$uploadResult['success']) {
            sendResponse(false, 'ファイルアップロードエラー', null, $uploadResult['errors']);
        }
        
        // ✅ v5.0準拠: 引数なしで初期化
        $importer = new SmileyCSVImporter();
        
        // インポートオプション設定
        $importOptions = [
            'encoding' => $_POST['encoding'] ?? 'Shift_JIS',  // デフォルトをShift_JISに
            'has_header' => true,
            'delimiter' => ','
        ];
        
        // ✅ v5.0準拠: importCSV() メソッド使用
        $importResult = $importer->importCSV($uploadResult['filepath'], $importOptions);
        
        // 一時ファイル削除
        if (file_exists($uploadResult['filepath'])) {
            unlink($uploadResult['filepath']);
        }
        
        // 結果の整形
        $responseData = [
            'batch_id' => $importResult['batch_id'],
            'filename' => basename($uploadedFile['name']),
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
                'processing_time' => $importResult['execution_time'] . '秒'
            ],
            'summary_message' => $importResult['summary_message'] ?? '',
            'errors' => array_slice($importResult['errors'] ?? [], 0, 10)  // 最初の10件のみ
        ];
        
        // 結果レスポンス
        if ($importResult['success']) {
            sendResponse(true, 'CSVインポートが正常に完了しました', $responseData);
        } else {
            sendResponse(false, 'CSVインポートでエラーが発生しました', $responseData);
        }
    }
    
    // GET リクエスト処理（ステータス確認等）
    elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
        
        $db = Database::getInstance();
        $action = $_GET['action'] ?? 'status';
        
        switch ($action) {
            case 'status':
                // システム状態確認
                $status = [
                    'database_connected' => $db->testConnection(),
                    'tables_exist' => [
                        'orders' => $db->tableExists('orders'),
                        'companies' => $db->tableExists('companies'),
                        'departments' => $db->tableExists('departments'),
                        'users' => $db->tableExists('users'),
                        'suppliers' => $db->tableExists('suppliers'),
                        'products' => $db->tableExists('products'),
                        'import_logs' => $db->tableExists('import_logs')
                    ],
                    'php_extensions' => [
                        'mysqli' => extension_loaded('mysqli'),
                        'mbstring' => extension_loaded('mbstring'),
                        'fileinfo' => extension_loaded('fileinfo')
                    ]
                ];
                sendResponse(true, 'システム状態を確認しました', $status);
                break;
                
            case 'history':
                // インポート履歴取得
                $limit = min(50, max(1, intval($_GET['limit'] ?? 20)));
                $offset = max(0, intval($_GET['offset'] ?? 0));
                
                $sql = "SELECT * FROM import_logs 
                        ORDER BY import_date DESC 
                        LIMIT ? OFFSET ?";
                
                $history = $db->fetchAll($sql, [$limit, $offset]);
                
                sendResponse(true, 'インポート履歴を取得しました', [
                    'history' => $history,
                    'pagination' => [
                        'limit' => $limit,
                        'offset' => $offset
                    ]
                ]);
                break;
                
            default:
                sendResponse(false, '不明なアクションです');
        }
    }
    
    else {
        sendResponse(false, 'サポートされていないHTTPメソッドです');
    }
    
} catch (Exception $e) {
    // エラーログ記録
    error_log("CSVインポートAPI エラー: " . $e->getMessage());
    error_log("スタックトレース: " . $e->getTraceAsString());
    
    sendResponse(false, 'システムエラーが発生しました: ' . $e->getMessage(), null, [[
        'message' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]]);
}
?>
