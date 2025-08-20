<?php
/**
 * 重複回避版Database確認ツール
 * api/safe_check.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json; charset=utf-8');

function respond($success, $message, $data = []) {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // 1. 現在読み込まれているクラス確認
    $declaredClasses = get_declared_classes();
    $isDatabaseLoaded = in_array('Database', $declaredClasses);
    
    $analysis = [
        'database_class_loaded' => $isDatabaseLoaded,
        'total_classes_loaded' => count($declaredClasses)
    ];
    
    // 2. Database クラスが既に読み込まれている場合
    if ($isDatabaseLoaded) {
        $reflection = new ReflectionClass('Database');
        
        // コンストラクタ情報
        $constructor = $reflection->getConstructor();
        $analysis['constructor'] = [
            'exists' => $constructor !== null,
            'is_public' => $constructor ? $constructor->isPublic() : false,
            'is_private' => $constructor ? $constructor->isPrivate() : false,
            'is_protected' => $constructor ? $constructor->isProtected() : false
        ];
        
        // メソッド一覧
        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
        $analysis['public_methods'] = [];
        $analysis['static_methods'] = [];
        
        foreach ($methods as $method) {
            $methodName = $method->getName();
            $analysis['public_methods'][] = $methodName;
            
            if ($method->isStatic()) {
                $analysis['static_methods'][] = $methodName;
            }
        }
        
        // 3. 接続テスト（安全な方法で）
        $connectionTests = [];
        
        // Test A: getInstance() があるかテスト
        if (in_array('getInstance', $analysis['static_methods'])) {
            try {
                $db = Database::getInstance();
                $connectionTests['getInstance'] = [
                    'success' => true,
                    'class' => get_class($db),
                    'has_query_method' => method_exists($db, 'query')
                ];
                
                // クエリテスト
                if (method_exists($db, 'query')) {
                    try {
                        $stmt = $db->query("SELECT 1 as test");
                        $result = $stmt->fetch();
                        $connectionTests['query_test'] = [
                            'success' => true,
                            'result' => $result
                        ];
                    } catch (Exception $e) {
                        $connectionTests['query_test'] = [
                            'success' => false,
                            'error' => $e->getMessage()
                        ];
                    }
                }
                
            } catch (Exception $e) {
                $connectionTests['getInstance'] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        // Test B: connect() があるかテスト
        if (in_array('connect', $analysis['static_methods'])) {
            try {
                $result = Database::connect();
                $connectionTests['connect'] = [
                    'success' => true,
                    'type' => gettype($result),
                    'class' => is_object($result) ? get_class($result) : null
                ];
            } catch (Exception $e) {
                $connectionTests['connect'] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        // Test C: getConnection() があるかテスト
        if (in_array('getConnection', $analysis['static_methods'])) {
            try {
                $result = Database::getConnection();
                $connectionTests['getConnection'] = [
                    'success' => true,
                    'type' => gettype($result),
                    'class' => is_object($result) ? get_class($result) : null
                ];
            } catch (Exception $e) {
                $connectionTests['getConnection'] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        $analysis['connection_tests'] = $connectionTests;
        
    } else {
        // Database クラスが読み込まれていない場合
        $analysis['note'] = 'Database クラスがまだ読み込まれていません';
        
        // 設定ファイルの確認
        if (file_exists('../config/database.php')) {
            $configContent = file_get_contents('../config/database.php');
            $analysis['config_file_size'] = strlen($configContent);
            $analysis['has_db_constants'] = [
                'DB_HOST' => strpos($configContent, 'DB_HOST') !== false,
                'DB_NAME' => strpos($configContent, 'DB_NAME') !== false,
                'DB_USER' => strpos($configContent, 'DB_USER') !== false,
                'DB_PASS' => strpos($configContent, 'DB_PASS') !== false
            ];
        }
    }
    
    // 4. 現在の定数確認
    $analysis['current_constants'] = [
        'DB_HOST' => defined('DB_HOST') ? DB_HOST : 'not defined',
        'DB_NAME' => defined('DB_NAME') ? DB_NAME : 'not defined',
        'DB_USER' => defined('DB_USER') ? DB_USER : 'not defined',
        'DB_PASS' => defined('DB_PASS') ? '***' : 'not defined'
    ];
    
    // 5. ファイル情報
    $files = [
        'config' => '../config/database.php',
        'class' => '../classes/Database.php'
    ];
    
    foreach ($files as $key => $file) {
        if (file_exists($file)) {
            $analysis['files'][$key] = [
                'exists' => true,
                'size' => filesize($file),
                'modified' => date('Y-m-d H:i:s', filemtime($file))
            ];
        } else {
            $analysis['files'][$key] = ['exists' => false];
        }
    }
    
    respond(true, 'Database クラス分析完了（重複回避版）', $analysis);
    
} catch (Exception $e) {
    respond(false, 'エラー発生', [
        'error' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
}
?>
