<?php
/**
 * CSVインポートAPI（修正版）
 * Database統一対応版
 * 
 * 修正内容:
 * 1. Database::getInstance() を使用（統一修正）
 * 2. エラーハンドリング強化
 * 3. FileUploadHandler の正しい使用
 */

require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/SmileyCSVImporter.php';
require_once '../classes/FileUploadHandler.php';
require_once '../classes/SecurityHelper.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// セキュリティヘッダー設定
SecurityHelper::setSecurityHeaders();

// OPTIONS リクエスト対応
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    // Database::getInstance() を使用（修正箇所）
    $db = Database::getInstance();
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        
        // ファイルアップロード検証
        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('ファイルがアップロードされていません。エラーコード: ' . ($_FILES['csv_file']['error'] ?? 'UNKNOWN'));
        }
        
        $file = $_FILES['csv_file'];
        
        // ファイルの基本検証
        if ($file['size'] === 0) {
            throw new Exception('空のファイルがアップロードされました');
        }
        
        if ($file['size'] > 50 * 1024 * 1024) { // 50MB制限
            throw new Exception('ファイルサイズが大きすぎます（最大50MB）');
        }
        
        // ファイル拡張子チェック
        $pathInfo = pathinfo($file['name']);
        $extension = strtolower($pathInfo['extension'] ?? '');
        
        if (!in_array($extension, ['csv', 'txt'])) {
            throw new Exception('CSVファイルまたはテキストファイルをアップロードしてください');
        }
        
        // 一時ファイル処理
        $uploadDir = '../temp/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $tempFilePath = $uploadDir . 'import_' . date('YmdHis') . '_' . uniqid() . '.csv';
        
        if (!move_uploaded_file($file['tmp_name'], $tempFilePath)) {
            throw new Exception('ファイルの保存に失敗しました');
        }
        
        // CSVインポート実行
        $importer = new SmileyCSVImporter($db);
        
        $importOptions = [
            'encoding' => $_POST['encoding'] ?? 'auto',
            'overwrite' => isset($_POST['overwrite']) ? (bool)$_POST['overwrite'] : false,
            'validate_smiley' => true // Smiley配食事業専用検証
        ];
        
        $result = $importer->importFile($tempFilePath, $importOptions);
        
        // 一時ファイル削除
        if (file_exists($tempFilePath)) {
            unlink($tempFilePath);
        }
        
        echo json_encode([
            'success' => true,
            'message' => "CSVインポートが完了しました",
            'data' => [
                'batch_id' => $result['batch_id'],
                'total_records' => $result['stats']['total'],
                'success_records' => $result['stats']['success'],
                'error_records' => count($result['errors']),
                'duplicate_records' => $result['stats']['duplicate'],
                'processing_time' => $result['processing_time']
            ],
            'errors' => $result['errors']
        ], JSON_UNESCAPED_UNICODE);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
        
        $action = $_GET['action'] ?? 'status';
        
        switch ($action) {
            case 'status':
                // インポート状況確認
                echo json_encode([
                    'success' => true,
                    'message' => 'CSVインポートAPIは正常に動作しています',
                    'data' => [
                        'timestamp' => date('Y-m-d H:i:s'),
                        'database' => DB_NAME,
                        'environment' => ENVIRONMENT,
                        'max_upload_size' => ini_get('upload_max_filesize'),
                        'memory_limit' => ini_get('memory_limit')
                    ]
                ], JSON_UNESCAPED_UNICODE);
                break;
                
            case 'logs':
                // インポートログ取得
                $limit = min(100, max(10, intval($_GET['limit'] ?? 20)));
                
                $sql = "
                    SELECT 
                        batch_id,
                        file_name,
                        total_records,
                        success_records,
                        error_records,
                        duplicate_records,
                        status,
                        import_start,
                        import_end,
                        created_by
                    FROM import_logs 
                    ORDER BY import_start DESC 
                    LIMIT ?
                ";
                
                $stmt = $db->query($sql, [$limit]);
                $logs = $stmt->fetchAll();
                
                echo json_encode([
                    'success' => true,
                    'data' => $logs
                ], JSON_UNESCAPED_UNICODE);
                break;
                
            case 'validate':
                // データベース接続テスト
                $testSql = "SELECT COUNT(*) as count FROM users";
                $testStmt = $db->query($testSql);
                $userCount = $testStmt->fetch()['count'];
                
                $testSql2 = "SELECT COUNT(*) as count FROM orders";
                $testStmt2 = $db->query($testSql2);
                $orderCount = $testStmt2->fetch()['count'];
                
                echo json_encode([
                    'success' => true,
                    'message' => 'データベース接続正常',
                    'data' => [
                        'user_count' => $userCount,
                        'order_count' => $orderCount,
                        'connection_test' => 'OK'
                    ]
                ], JSON_UNESCAPED_UNICODE);
                break;
                
            default:
                throw new Exception('不正なアクションです');
        }
        
    } else {
        throw new Exception('サポートされていないHTTPメソッドです');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    error_log("Import API Error: " . $e->getMessage());
    
    // 一時ファイルがある場合は削除
    if (isset($tempFilePath) && file_exists($tempFilePath)) {
        unlink($tempFilePath);
    }
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'debug_info' => [
            'timestamp' => date('Y-m-d H:i:s'),
            'file' => basename(__FILE__),
            'method' => $_SERVER['REQUEST_METHOD'],
            'post_data' => $_POST,
            'files_data' => array_map(function($file) {
                return [
                    'name' => $file['name'],
                    'size' => $file['size'],
                    'error' => $file['error']
                ];
            }, $_FILES)
        ]
    ], JSON_UNESCAPED_UNICODE);
}
?>
