<?php
/**
 * Smiley配食事業専用CSVインポートAPI
 * Singleton対応修復版
 */

// エラー報告設定
error_reporting(E_ALL);
ini_set('display_errors', 0); // 本番では非表示
ini_set('log_errors', 1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// OPTIONSリクエスト対応
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    // 必須ファイル読み込み
    require_once '../config/database.php';
    require_once '../classes/Database.php';
    require_once '../classes/SmileyCSVImporter.php';
    require_once '../classes/SecurityHelper.php';
    
    // Singleton パターンでデータベース接続
    $db = Database::getInstance(); // ← 修正：new Database() → Database::getInstance()
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        
        // ファイルアップロード確認
        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('ファイルがアップロードされていません');
        }
        
        $file = $_FILES['csv_file'];
        
        // ファイル基本検証
        $allowedTypes = ['text/csv', 'text/plain', 'application/vnd.ms-excel'];
        $allowedExtensions = ['csv', 'txt'];
        $maxFileSize = 10 * 1024 * 1024; // 10MB
        
        // MIMEタイプチェック
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detectedType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($detectedType, $allowedTypes) && !in_array($file['type'], $allowedTypes)) {
            throw new Exception('許可されていないファイル形式です: ' . $detectedType);
        }
        
        // 拡張子チェック
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedExtensions)) {
            throw new Exception('許可されていない拡張子です: ' . $extension);
        }
        
        // ファイルサイズチェック
        if ($file['size'] > $maxFileSize) {
            throw new Exception('ファイルサイズが上限（10MB）を超えています: ' . round($file['size'] / 1024 / 1024, 2) . 'MB');
        }
        
        // CSVインポーター初期化
        $importer = new SmileyCSVImporter($db);
        
        // インポート実行
        $result = $importer->importFile($file['tmp_name'], [
            'encoding' => $_POST['encoding'] ?? 'auto',
            'validate_smiley' => true
        ]);
        
        // 成功レスポンス
        echo json_encode([
            'success' => true,
            'message' => "CSVインポートが完了しました",
            'data' => [
                'batch_id' => $result['batch_id'],
                'total_records' => $result['stats']['total'],
                'success_records' => $result['stats']['success'],
                'error_records' => $result['stats']['errors'],
                'duplicate_records' => $result['stats']['duplicate'] ?? 0,
                'processing_time' => $result['processing_time']
            ],
            'errors' => $result['errors'] ?? []
        ], JSON_UNESCAPED_UNICODE);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
        
        $action = $_GET['action'] ?? 'status';
        
        switch ($action) {
            case 'status':
                // インポート履歴取得
                $sql = "SELECT * FROM import_logs ORDER BY created_at DESC LIMIT 10";
                $stmt = $db->query($sql);
                $logs = $stmt->fetchAll();
                
                echo json_encode([
                    'success' => true,
                    'data' => [
                        'recent_imports' => $logs,
                        'system_status' => 'operational'
                    ]
                ], JSON_UNESCAPED_UNICODE);
                break;
                
            case 'test':
                // システムテスト
                $testQuery = "SELECT COUNT(*) as total_orders FROM orders";
                $stmt = $db->query($testQuery);
                $result = $stmt->fetch();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'システムは正常に動作しています',
                    'data' => [
                        'database_connection' => 'OK',
                        'total_orders' => $result['total_orders'],
                        'timestamp' => date('Y-m-d H:i:s'),
                        'importer_class' => class_exists('SmileyCSVImporter') ? 'EXISTS' : 'NOT_EXISTS'
                    ]
                ], JSON_UNESCAPED_UNICODE);
                break;
                
            case 'companies':
                // 配達先企業一覧
                $sql = "SELECT id, company_code, company_name, 
                               (SELECT COUNT(*) FROM users WHERE company_id = companies.id AND is_active = 1) as user_count,
                               (SELECT COUNT(*) FROM orders WHERE company_id = companies.id AND delivery_date >= CURDATE() - INTERVAL 30 DAY) as recent_orders
                        FROM companies 
                        WHERE is_active = 1 
                        ORDER BY company_name";
                $stmt = $db->query($sql);
                $companies = $stmt->fetchAll();
                
                echo json_encode([
                    'success' => true,
                    'data' => [
                        'companies' => $companies
                    ]
                ], JSON_UNESCAPED_UNICODE);
                break;
                
            default:
                throw new Exception('不正なアクションです: ' . $action);
        }
        
    } else {
        throw new Exception('サポートされていないHTTPメソッドです: ' . $_SERVER['REQUEST_METHOD']);
    }
    
} catch (Exception $e) {
    // エラーログ記録
    error_log("CSV Import API Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_code' => 'IMPORT_ERROR',
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
}
?>
