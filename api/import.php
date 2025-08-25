<?php
/**
 * CSVインポートAPI（根本修正版）
 * 「結果が全て0」問題の完全解決
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
require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/SmileyCSVImporter.php';
require_once '../classes/FileUploadHandler.php';
require_once '../classes/SecurityHelper.php';

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
    // データベース接続
    $db = Database::getInstance();
    
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
        
        // CSVインポート実行
        $importer = new SmileyCSVImporter($db);
        $importOptions = [
            'encoding' => $_POST['encoding'] ?? 'auto',
            'overwrite' => isset($_POST['overwrite']) ? (bool)$_POST['overwrite'] : false
        ];
        
        $importResult = $importer->importFile($uploadResult['filepath'], $importOptions);
        
        // 一時ファイル削除
        if (file_exists($uploadResult['filepath'])) {
            unlink($uploadResult['filepath']);
        }
        
        // 結果レスポンス
        if ($importResult['success']) {
            sendResponse(true, 'CSVインポートが正常に完了しました', [
                'batch_id' => $importResult['batch_id'],
                'stats' => $importResult['stats'],
                'processing_time' => $importResult['processing_time']
            ], $importResult['errors']);
        } else {
            sendResponse(false, 'CSVインポートでエラーが発生しました', [
                'batch_id' => $importResult['batch_id'],
                'stats' => $importResult['stats'],
                'processing_time' => $importResult['processing_time']
            ], $importResult['errors']);
        }
    }
    
    // GET リクエスト処理（ステータス確認等）
    elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
        
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
                
                $sql = "SELECT batch_id, file_path, total_records, success_records, 
                              error_records, duplicate_records, processing_time_seconds, 
                              created_at 
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
                break;
                
            case 'batch_detail':
                // バッチ詳細取得
                $batchId = $_GET['batch_id'] ?? '';
                
                if (empty($batchId)) {
                    sendResponse(false, 'バッチIDが指定されていません');
                }
                
                $sql = "SELECT * FROM import_logs WHERE batch_id = ?";
                $batchDetail = $db->fetchOne($sql, [$batchId]);
                
                if (!$batchDetail) {
                    sendResponse(false, '指定されたバッチが見つかりません');
                }
                
                // エラー詳細をデコード
                if ($batchDetail['error_details']) {
                    $batchDetail['error_details'] = json_decode($batchDetail['error_details'], true);
                }
                
                sendResponse(true, 'バッチ詳細を取得しました', $batchDetail);
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
    error_log("ファイル: " . $e->getFile() . " 行: " . $e->getLine());
    
    sendResponse(false, 'システムエラーが発生しました', null, [[
        'message' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]]);
}
?>
