<?php
/**
 * 設定ファイル読み込みテスト
 * api/config_test.php
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
    // 1. 設定ファイル読み込み前の状態
    $beforeConstants = [
        'DB_HOST' => defined('DB_HOST') ? DB_HOST : 'not defined',
        'DB_NAME' => defined('DB_NAME') ? DB_NAME : 'not defined',
        'DB_USER' => defined('DB_USER') ? DB_USER : 'not defined',
        'DB_PASS' => defined('DB_PASS') ? '***' : 'not defined'
    ];
    
    // 2. 設定ファイル内容の一部確認
    $configFile = '../config/database.php';
    $configContent = file_get_contents($configFile);
    
    // ファイル先頭100文字を確認
    $fileStart = substr($configContent, 0, 200);
    
    // 定数定義の存在確認
    $hasDefines = [
        'define_DB_HOST' => preg_match('/define\s*\(\s*[\'"]DB_HOST[\'"]/', $configContent),
        'define_DB_NAME' => preg_match('/define\s*\(\s*[\'"]DB_NAME[\'"]/', $configContent),
        'define_DB_USER' => preg_match('/define\s*\(\s*[\'"]DB_USER[\'"]/', $configContent),
        'define_DB_PASS' => preg_match('/define\s*\(\s*[\'"]DB_PASS[\'"]/', $configContent)
    ];
    
    // 3. 設定ファイル読み込み実行
    $loadResult = [];
    try {
        ob_start();
        $result = require_once $configFile;
        $output = ob_get_clean();
        
        $loadResult = [
            'success' => true,
            'require_result' => $result,
            'output' => $output,
            'output_length' => strlen($output)
        ];
    } catch (Exception $e) {
        ob_end_clean();
        $loadResult = [
            'success' => false,
            'error' => $e->getMessage(),
            'file' => basename($e->getFile()),
            'line' => $e->getLine()
        ];
    }
    
    // 4. 設定ファイル読み込み後の状態
    $afterConstants = [
        'DB_HOST' => defined('DB_HOST') ? DB_HOST : 'not defined',
        'DB_NAME' => defined('DB_NAME') ? DB_NAME : 'not defined',
        'DB_USER' => defined('DB_USER') ? DB_USER : 'not defined',
        'DB_PASS' => defined('DB_PASS') ? '***' : 'not defined'
    ];
    
    // 5. Database クラス読み込みテスト
    $databaseTest = [];
    try {
        if (!class_exists('Database')) {
            require_once '../classes/Database.php';
        }
        
        $reflection = new ReflectionClass('Database');
        $constructor = $reflection->getConstructor();
        
        $databaseTest = [
            'class_loaded' => true,
            'constructor_public' => $constructor ? $constructor->isPublic() : false,
            'constructor_private' => $constructor ? $constructor->isPrivate() : false,
            'methods' => array_map(function($method) {
                return [
                    'name' => $method->getName(),
                    'static' => $method->isStatic(),
                    'public' => $method->isPublic()
                ];
            }, $reflection->getMethods(ReflectionMethod::IS_PUBLIC))
        ];
        
        // 6. インスタンス生成テスト
        $instanceTest = [];
        
        // パターン1: 直接インスタンス化
        if ($constructor && $constructor->isPublic()) {
            try {
                $db = new Database();
                $instanceTest['direct_new'] = [
                    'success' => true,
                    'class' => get_class($db)
                ];
            } catch (Exception $e) {
                $instanceTest['direct_new'] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        // パターン2: getInstance メソッド
        if ($reflection->hasMethod('getInstance')) {
            try {
                $db = Database::getInstance();
                $instanceTest['getInstance'] = [
                    'success' => true,
                    'class' => get_class($db)
                ];
            } catch (Exception $e) {
                $instanceTest['getInstance'] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        // パターン3: connect メソッド
        if ($reflection->hasMethod('connect')) {
            try {
                $db = Database::connect();
                $instanceTest['connect'] = [
                    'success' => true,
                    'type' => gettype($db),
                    'class' => is_object($db) ? get_class($db) : null
                ];
            } catch (Exception $e) {
                $instanceTest['connect'] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        $databaseTest['instance_tests'] = $instanceTest;
        
    } catch (Exception $e) {
        $databaseTest = [
            'class_loaded' => false,
            'error' => $e->getMessage()
        ];
    }
    
    respond(true, '設定ファイル読み込み分析完了', [
        'config_file' => [
            'path' => $configFile,
            'exists' => file_exists($configFile),
            'size' => filesize($configFile),
            'readable' => is_readable($configFile),
            'file_start' => $fileStart
        ],
        'constants_before' => $beforeConstants,
        'constants_after' => $afterConstants,
        'has_define_statements' => $hasDefines,
        'load_result' => $loadResult,
        'database_test' => $databaseTest
    ]);
    
} catch (Exception $e) {
    respond(false, 'エラー発生', [
        'error' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]);
}
?>
