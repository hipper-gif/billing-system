<?php
/**
 * import.php API 専用デバッグ版
 * HTTP 500エラーの詳細診断
 */

// エラー出力を有効化
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

header('Content-Type: application/json; charset=utf-8');

$debugInfo = [
    'timestamp' => date('Y-m-d H:i:s'),
    'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
    'steps' => []
];

try {
    // Step 1: 基本情報確認
    $debugInfo['steps']['basic_info'] = [
        'php_version' => PHP_VERSION,
        'memory_limit' => ini_get('memory_limit'),
        'base_path' => __DIR__,
        'working_directory' => getcwd()
    ];
    
    // Step 2: 必須ファイル読み込み
    $debugInfo['steps']['file_loading'] = [];
    
    $requiredFiles = [
        'database_config' => '../config/database.php',
        'database_class' => '../classes/Database.php',
        'csv_importer' => '../classes/SmileyCSVImporter.php',
        'security_helper' => '../classes/SecurityHelper.php'
    ];
    
    foreach ($requiredFiles as $name => $path) {
        try {
            $fullPath = __DIR__ . '/' . $path;
            
            if (!file_exists($fullPath)) {
                throw new Exception("File not found: {$fullPath}");
            }
            
            require_once $fullPath;
            
            $debugInfo['steps']['file_loading'][$name] = [
                'status' => 'success',
                'path' => $fullPath,
                'size' => filesize($fullPath)
            ];
            
        } catch (Exception $e) {
            $debugInfo['steps']['file_loading'][$name] = [
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    }
    
    // Step 3: クラス存在確認
    $debugInfo['steps']['class_check'] = [
        'Database' => class_exists('Database'),
        'SmileyCSVImporter' => class_exists('SmileyCSVImporter'),
        'SecurityHelper' => class_exists('SecurityHelper')
    ];
    
    // Step 4: データベース接続テスト
    if (class_exists('Database')) {
        try {
            $db = new Database();
            $testQuery = "SELECT 1 as test";
            $stmt = $db->query($testQuery);
            $result = $stmt->fetch();
            
            $debugInfo['steps']['database_test'] = [
                'status' => 'success',
                'connection' => 'OK',
                'test_query' => $result ? 'OK' : 'FAILED'
            ];
        } catch (Exception $e) {
            $debugInfo['steps']['database_test'] = [
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    } else {
        $debugInfo['steps']['database_test'] = [
            'status' => 'error',
            'error' => 'Database class not found'
        ];
    }
    
    // Step 5: CSVImporter インスタンス作成テスト
    if (class_exists('SmileyCSVImporter') && isset($db)) {
        try {
            $importer = new SmileyCSVImporter($db);
            
            $debugInfo['steps']['importer_test'] = [
                'status' => 'success',
                'instance_created' => true,
                'methods' => get_class_methods($importer)
            ];
        } catch (Exception $e) {
            $debugInfo['steps']['importer_test'] = [
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    } else {
        $debugInfo['steps']['importer_test'] = [
            'status' => 'error',
            'error' => 'Prerequisites not met'
        ];
    }
    
    // Step 6: リクエスト処理テスト
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $debugInfo['steps']['request_processing'] = [
            'method' => 'POST',
            'files_received' => isset($_FILES) ? count($_FILES) : 0,
            'post_data' => $_POST,
            'files_data' => $_FILES ?? []
        ];
        
        // ファイルアップロードテスト
        if (isset($_FILES['csv_file'])) {
            $file = $_FILES['csv_file'];
            
            $debugInfo['steps']['file_upload'] = [
                'file_name' => $file['name'],
                'file_size' => $file['size'],
                'file_type' => $file['type'],
                'upload_error' => $file['error'],
                'error_description' => $this->getUploadErrorDescription($file['error'])
            ];
            
            // 実際のインポート処理テスト（安全な方法で）
            if ($file['error'] === UPLOAD_ERR_OK && isset($importer)) {
                try {
                    // テスト用の小さなCSVファイルかチェック
                    if ($file['size'] < 1024) { // 1KB未満の小さなファイルのみテスト
                        $result = $importer->importFile($file['tmp_name'], []);
                        
                        $debugInfo['steps']['import_test'] = [
                            'status' => 'success',
                            'result' => $result
                        ];
                    } else {
                        $debugInfo['steps']['import_test'] = [
                            'status' => 'skipped',
                            'reason' => 'File too large for debug test'
                        ];
                    }
                } catch (Exception $e) {
                    $debugInfo['steps']['import_test'] = [
                        'status' => 'error',
                        'error' => $e->getMessage()
                    ];
                }
            }
        } else {
            $debugInfo['steps']['file_upload'] = [
                'status' => 'no_file',
                'message' => 'No file uploaded'
            ];
        }
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = $_GET['action'] ?? 'status';
        
        $debugInfo['steps']['request_processing'] = [
            'method' => 'GET',
            'action' => $action,
            'get_data' => $_GET
        ];
        
        // GET リクエストの処理テスト
        if ($action === 'status' && isset($db)) {
            try {
                $sql = "SELECT * FROM import_logs ORDER BY created_at DESC LIMIT 5";
                $stmt = $db->query($sql);
                $logs = $stmt->fetchAll();
                
                $debugInfo['steps']['status_check'] = [
                    'status' => 'success',
                    'recent_imports' => $logs
                ];
            } catch (Exception $e) {
                $debugInfo['steps']['status_check'] = [
                    'status' => 'error',
                    'error' => $e->getMessage()
                ];
            }
        }
    }
    
    // 成功レスポンス
    echo json_encode([
        'success' => true,
        'message' => 'Debug information collected successfully',
        'debug_info' => $debugInfo
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    // エラーレスポンス
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
        'debug_info' => $debugInfo
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}

/**
 * アップロードエラーの説明を取得
 */
function getUploadErrorDescription($errorCode) {
    $errors = [
        UPLOAD_ERR_OK => 'No error',
        UPLOAD_ERR_INI_SIZE => 'File size exceeds upload_max_filesize',
        UPLOAD_ERR_FORM_SIZE => 'File size exceeds MAX_FILE_SIZE',
        UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
        UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
    ];
    
    return $errors[$errorCode] ?? 'Unknown error';
}
?>
