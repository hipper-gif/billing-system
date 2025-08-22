<?php
/**
 * 強化版CSVインポートデバッグツール
 * HTTP 500エラー詳細診断
 */

header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);

$basePath = dirname(__FILE__);
$results = [
    'timestamp' => date('Y-m-d H:i:s'),
    'base_path' => $basePath,
    'debug_info' => []
];

try {
    // 1. PHP環境確認
    $results['debug_info']['php_environment'] = [
        'php_version' => PHP_VERSION,
        'memory_limit' => ini_get('memory_limit'),
        'max_execution_time' => ini_get('max_execution_time'),
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'post_max_size' => ini_get('post_max_size'),
        'error_reporting' => error_reporting(),
        'display_errors' => ini_get('display_errors'),
        'log_errors' => ini_get('log_errors')
    ];
    
    // 2. 必須ファイル存在確認
    $criticalFiles = [
        'database_config' => '../config/database.php',
        'database_class' => '../classes/Database.php',
        'csv_importer' => '../classes/SmileyCSVImporter.php',
        'security_helper' => '../classes/SecurityHelper.php',
        'file_handler' => '../classes/FileUploadHandler.php',
        'import_api' => '../api/import.php'
    ];
    
    $results['debug_info']['file_existence'] = [];
    foreach ($criticalFiles as $name => $path) {
        $fullPath = $basePath . '/' . $path;
        $exists = file_exists($fullPath);
        
        $results['debug_info']['file_existence'][$name] = [
            'path' => $fullPath,
            'exists' => $exists,
            'readable' => $exists ? is_readable($fullPath) : false,
            'size' => $exists ? filesize($fullPath) : 0,
            'modified' => $exists ? date('Y-m-d H:i:s', filemtime($fullPath)) : null
        ];
    }
    
    // 3. クラス読み込みテスト
    $results['debug_info']['class_loading'] = [];
    
    // Database クラス
    try {
        require_once $basePath . '/../config/database.php';
        require_once $basePath . '/../classes/Database.php';
        
        $results['debug_info']['class_loading']['Database'] = [
            'loaded' => true,
            'exists' => class_exists('Database'),
            'methods' => class_exists('Database') ? get_class_methods('Database') : []
        ];
        
        // データベース接続テスト
        if (class_exists('Database')) {
            $db = new Database();
            $results['debug_info']['database_connection'] = 'OK';
            
            // テーブル確認
            $tables = ['orders', 'users', 'companies', 'departments', 'products', 'suppliers'];
            foreach ($tables as $table) {
                $stmt = $db->query("SHOW TABLES LIKE ?", [$table]);
                $results['debug_info']['tables'][$table] = $stmt->fetch() ? 'EXISTS' : 'MISSING';
            }
        }
        
    } catch (Exception $e) {
        $results['debug_info']['class_loading']['Database'] = [
            'loaded' => false,
            'error' => $e->getMessage()
        ];
    }
    
    // SmileyCSVImporter クラス
    try {
        if (file_exists($basePath . '/../classes/SmileyCSVImporter.php')) {
            // ファイル内容の構文チェック
            $importerContent = file_get_contents($basePath . '/../classes/SmileyCSVImporter.php');
            
            // PHP構文チェック
            $tempFile = tempnam(sys_get_temp_dir(), 'syntax_check');
            file_put_contents($tempFile, $importerContent);
            
            $output = [];
            $returnVar = 0;
            exec("php -l " . escapeshellarg($tempFile) . " 2>&1", $output, $returnVar);
            
            unlink($tempFile);
            
            $results['debug_info']['syntax_check'] = [
                'valid' => $returnVar === 0,
                'output' => implode("\n", $output)
            ];
            
            if ($returnVar === 0) {
                require_once $basePath . '/../classes/SmileyCSVImporter.php';
                
                $results['debug_info']['class_loading']['SmileyCSVImporter'] = [
                    'loaded' => true,
                    'exists' => class_exists('SmileyCSVImporter'),
                    'methods' => class_exists('SmileyCSVImporter') ? get_class_methods('SmileyCSVImporter') : []
                ];
            } else {
                $results['debug_info']['class_loading']['SmileyCSVImporter'] = [
                    'loaded' => false,
                    'error' => 'Syntax error in file'
                ];
            }
        } else {
            $results['debug_info']['class_loading']['SmileyCSVImporter'] = [
                'loaded' => false,
                'error' => 'File does not exist'
            ];
        }
    } catch (Exception $e) {
        $results['debug_info']['class_loading']['SmileyCSVImporter'] = [
            'loaded' => false,
            'error' => $e->getMessage()
        ];
    }
    
    // 4. FileUploadHandler クラス
    try {
        if (file_exists($basePath . '/../classes/FileUploadHandler.php')) {
            require_once $basePath . '/../classes/FileUploadHandler.php';
            
            $results['debug_info']['class_loading']['FileUploadHandler'] = [
                'loaded' => true,
                'exists' => class_exists('FileUploadHandler'),
                'methods' => class_exists('FileUploadHandler') ? get_class_methods('FileUploadHandler') : []
            ];
        } else {
            $results['debug_info']['class_loading']['FileUploadHandler'] = [
                'loaded' => false,
                'error' => 'File does not exist'
            ];
        }
    } catch (Exception $e) {
        $results['debug_info']['class_loading']['FileUploadHandler'] = [
            'loaded' => false,
            'error' => $e->getMessage()
        ];
    }
    
    // 5. import.php API エラーログ確認
    if (isset($db)) {
        try {
            // 最新のimport_logs確認
            $stmt = $db->query("SELECT * FROM import_logs ORDER BY created_at DESC LIMIT 5");
            $results['debug_info']['recent_import_logs'] = $stmt->fetchAll();
        } catch (Exception $e) {
            $results['debug_info']['recent_import_logs'] = [];
        }
        
        // 最新の注文データ確認
        try {
            $stmt = $db->query("SELECT * FROM orders ORDER BY created_at DESC LIMIT 5");
            $results['debug_info']['recent_orders'] = $stmt->fetchAll();
            
            $stmt = $db->query("SELECT COUNT(*) as count FROM orders");
            $result = $stmt->fetch();
            $results['debug_info']['orders_count'] = $result['count'];
        } catch (Exception $e) {
            $results['debug_info']['orders_count'] = 'ERROR: ' . $e->getMessage();
        }
        
        // 利用者統計確認
        try {
            $stmt = $db->query("
                SELECT u.user_code, u.user_name, u.company_id, c.company_code, c.company_name, 
                       COUNT(o.id) as order_count
                FROM users u
                LEFT JOIN companies c ON u.company_id = c.id
                LEFT JOIN orders o ON u.id = o.user_id
                GROUP BY u.id, u.user_code, u.user_name, u.company_id, c.company_code, c.company_name
                ORDER BY order_count DESC
                LIMIT 10
            ");
            $results['debug_info']['user_order_stats'] = $stmt->fetchAll();
        } catch (Exception $e) {
            $results['debug_info']['user_order_stats'] = 'ERROR: ' . $e->getMessage();
        }
    }
    
    // 6. API直接テスト
    try {
        // import.php を直接includeしてテスト
        ob_start();
        
        // 疑似的なPOSTリクエスト設定
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET['action'] = 'test';
        
        include $basePath . '/../api/import.php';
        
        $apiOutput = ob_get_clean();
        
        $results['debug_info']['api_direct_test'] = [
            'output' => $apiOutput,
            'length' => strlen($apiOutput),
            'json_valid' => json_decode($apiOutput) !== null
        ];
        
    } catch (Exception $e) {
        $results['debug_info']['api_direct_test'] = [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ];
    }
    
    // 7. 権限・ディレクトリ確認
    $directories = [
        'uploads' => '../uploads',
        'temp' => '../temp',
        'logs' => '../logs'
    ];
    
    $results['debug_info']['directory_permissions'] = [];
    foreach ($directories as $name => $path) {
        $fullPath = $basePath . '/' . $path;
        
        $results['debug_info']['directory_permissions'][$name] = [
            'path' => $fullPath,
            'exists' => is_dir($fullPath),
            'writable' => is_dir($fullPath) ? is_writable($fullPath) : false,
            'readable' => is_dir($fullPath) ? is_readable($fullPath) : false
        ];
    }
    
    // 8. エラーログファイル確認
    $errorLogPath = ini_get('error_log');
    if ($errorLogPath && file_exists($errorLogPath)) {
        $results['debug_info']['error_log'] = [
            'path' => $errorLogPath,
            'size' => filesize($errorLogPath),
            'last_modified' => date('Y-m-d H:i:s', filemtime($errorLogPath)),
            'recent_errors' => array_slice(file($errorLogPath), -10)
        ];
    } else {
        $results['debug_info']['error_log'] = 'No error log file found';
    }
    
    // 9. 総合診断
    $criticalIssues = [];
    
    if (!$results['debug_info']['class_loading']['Database']['exists']) {
        $criticalIssues[] = 'Database クラスが読み込まれていません';
    }
    
    if (!$results['debug_info']['class_loading']['SmileyCSVImporter']['exists']) {
        $criticalIssues[] = 'SmileyCSVImporter クラスが読み込まれていません';
    }
    
    if ($results['debug_info']['database_connection'] !== 'OK') {
        $criticalIssues[] = 'データベース接続に問題があります';
    }
    
    if (!empty($criticalIssues)) {
        $results['overall_status'] = 'CRITICAL';
        $results['critical_issues'] = $criticalIssues;
    } else {
        $results['overall_status'] = 'OK';
        $results['critical_issues'] = [];
    }
    
} catch (Exception $e) {
    $results['overall_status'] = 'ERROR';
    $results['error'] = $e->getMessage();
    $results['trace'] = $e->getTraceAsString();
}

// 最終出力
echo json_encode($results, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>
