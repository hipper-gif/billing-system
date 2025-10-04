<?php
/**
 * CSVインポートAPI v5.0完全版
 * メソッド統一・自己完結原則準拠
 */

// エラー設定
error_reporting(E_ALL);
ini_set('display_errors', 1);
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

// レスポンス関数
function sendResponse($success, $message, $data = [], $code = 200) {
    http_response_code($code);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

try {
    // 1. 必須設定読み込み
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../classes/SmileyCSVImporter.php';
    
    // 2. リクエスト処理
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
    error_log("CSVインポートAPI エラー: " . $e->getMessage());
    error_log("スタックトレース: " . $e->getTraceAsString());
    
    sendResponse(false, 'システムエラー: ' . $e->getMessage(), [
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
            sendResponse(true, 'CSVインポートAPI v5.0 稼働中', [
                'version' => '5.0',
                'methods' => ['GET', 'POST'],
                'php_version' => PHP_VERSION
            ]);
            break;
            
        case 'status':
            try {
                $db = Database::getInstance();
                
                sendResponse(true, 'システム正常稼働中', [
                    'database_connected' => $db->testConnection(),
                    'classes_loaded' => [
                        'Database' => class_exists('Database'),
                        'SmileyCSVImporter' => class_exists('SmileyCSVImporter')
                    ]
                ]);
                
            } catch (Exception $e) {
                sendResponse(false, 'システム異常', [
                    'error' => $e->getMessage()
                ], 500);
            }
            break;
            
        default:
            sendResponse(false, '不明なアクション', [], 400);
    }
}

/**
 * POST リクエスト処理（CSVインポート）
 */
function handlePostRequest() {
    try {
        // デバッグ: 受信した全ての$_FILESをログ出力
        error_log("受信した\$_FILES: " . print_r($_FILES, true));
        error_log("受信した\$_POST: " . print_r($_POST, true));
        
        // ファイル確認（複数のフィールド名に対応）
        $fileFieldNames = ['csv_file', 'csvFile', 'file'];
        $uploadedFile = null;
        $usedFieldName = null;
        
        foreach ($fileFieldNames as $fieldName) {
            if (isset($_FILES[$fieldName]) && $_FILES[$fieldName]['error'] === UPLOAD_ERR_OK) {
                $uploadedFile = $_FILES[$fieldName];
                $usedFieldName = $fieldName;
                error_log("ファイルフィールド検出: {$fieldName}");
                break;
            }
        }
        
        if ($uploadedFile === null) {
            // デバッグ情報を含めたエラーレスポンス
            sendResponse(false, 'CSVファイルがアップロードされていません', [
                'debug' => [
                    'files_count' => count($_FILES),
                    'files_keys' => array_keys($_FILES),
                    'expected_field_names' => $fileFieldNames,
                    'post_keys' => array_keys($_POST)
                ]
            ], 400);
        }
        
        // ファイル基本検証
        if ($uploadedFile['size'] > 10 * 1024 * 1024) {
            sendResponse(false, 'ファイルサイズが大きすぎます（10MB以下）', [], 400);
        }
        
        $allowedMimes = ['text/csv', 'application/csv', 'text/plain', 'application/vnd.ms-excel'];
        if (!in_array($uploadedFile['type'], $allowedMimes)) {
            sendResponse(false, 'CSVファイルのみアップロード可能です', [
                'uploaded_mime' => $uploadedFile['type']
            ], 400);
        }
        
        // ✅ v5.0準拠: SmileyCSVImporter初期化（引数なし）
        $importer = new SmileyCSVImporter();
        
        // インポートオプション
        $options = [
            'encoding' => 'Shift_JIS',  // デフォルトをShift_JISに
            'has_header' => true,
            'delimiter' => ','
        ];
        
        // ✅ v5.0準拠: importCSV()メソッド使用
        error_log("CSVインポート開始: " . $uploadedFile['tmp_name']);
        $result = $importer->importCSV($uploadedFile['tmp_name'], $options);
        error_log("CSVインポート完了: " . print_r($result, true));
        
        // レスポンスデータ整形
        $responseData = [
            'batch_id' => $result['batch_id'] ?? 'unknown',
            'filename' => $uploadedFile['name'],
            'stats' => [
                'total_records' => $result['stats']['total_rows'] ?? 0,
                'processed_records' => $result['stats']['processed_rows'] ?? 0,
                'success_records' => $result['stats']['success_rows'] ?? 0,
                'error_records' => $result['stats']['error_rows'] ?? 0,
                'new_companies' => $result['stats']['new_companies'] ?? 0,
                'new_departments' => $result['stats']['new_departments'] ?? 0,
                'new_users' => $result['stats']['new_users'] ?? 0,
                'new_suppliers' => $result['stats']['new_suppliers'] ?? 0,
                'new_products' => $result['stats']['new_products'] ?? 0,
                'duplicate_orders' => $result['stats']['duplicate_orders'] ?? 0,
                'processing_time' => ($result['execution_time'] ?? 0) . '秒'
            ],
            'summary_message' => $result['summary_message'] ?? '',
            'errors' => array_slice($result['errors'] ?? [], 0, 10)
        ];
        
        // 成功レスポンス
        if ($result['success']) {
            sendResponse(true, 'CSVインポートが正常に完了しました', $responseData);
        } else {
            sendResponse(false, 'CSVインポートでエラーが発生しました', $responseData);
        }
        
    } catch (Throwable $e) {
        error_log("CSVインポートエラー: " . $e->getMessage());
        error_log("スタックトレース: " . $e->getTraceAsString());
        
        sendResponse(false, 'インポートエラー: ' . $e->getMessage(), [
            'error_file' => basename($e->getFile()),
            'error_line' => $e->getLine(),
            'trace' => explode("\n", $e->getTraceAsString())
        ], 500);
    }
}
?>
