<?php
/**
 * 安全なDatabase分析ツール（privateメソッド呼び出し回避）
 * api/safe_db_test.php
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
    // 1. 設定ファイル読み込み
    $configFile = '../config/database.php';
    
    // 読み込み前の定数状態
    $beforeConstants = [
        'DB_HOST' => defined('DB_HOST'),
        'DB_NAME' => defined('DB_NAME'),
        'DB_USER' => defined('DB_USER'),
        'DB_PASS' => defined('DB_PASS')
    ];
    
    // 設定ファイル読み込み
    if (file_exists($configFile)) {
        require_once $configFile;
    }
    
    // 読み込み後の定数状態
    $afterConstants = [
        'DB_HOST' => defined('DB_HOST') ? DB_HOST : 'not defined',
        'DB_NAME' => defined('DB_NAME') ? DB_NAME : 'not defined', 
        'DB_USER' => defined('DB_USER') ? DB_USER : 'not defined',
        'DB_PASS' => defined('DB_PASS') ? 'defined' : 'not defined'
    ];
    
    // 2. Database クラス読み込み（安全に）
    if (!class_exists('Database')) {
        require_once '../classes/Database.php';
    }
    
    // 3. リフレクションでクラス分析（privateメソッド呼び出し無し）
    $reflection = new ReflectionClass('Database');
    
    // コンストラクタ分析
    $constructor = $reflection->getConstructor();
    $constructorInfo = [
        'exists' => $constructor !== null,
        'public' => $constructor ? $constructor->isPublic() : false,
        'private' => $constructor ? $constructor->isPrivate() : false,
        'protected' => $constructor ? $constructor->isProtected() : false
    ];
    
    // メソッド分析（可視性別）
    $methods = $reflection->getMethods();
    $methodsAnalysis = [
        'public_static' => [],
        'public_instance' => [],
        'private_static' => [],
        'private_instance' => [],
        'protected_static' => [],
        'protected_instance' => []
    ];
    
    foreach ($methods as $method) {
        $name = $method->getName();
        $visibility = '';
        $type = '';
        
        if ($method->isPublic()) $visibility = 'public';
        elseif ($method->isPrivate()) $visibility = 'private'; 
        elseif ($method->isProtected()) $visibility = 'protected';
        
        if ($method->isStatic()) $type = 'static';
        else $type = 'instance';
        
        $key = $visibility . '_' . $type;
        $methodsAnalysis[$key][] = $name;
    }
    
    // 4. 安全な接続テスト（publicな静的メソッドのみ）
    $connectionTests = [];
    
    // getInstance メソッドテスト（publicで静的な場合のみ）
    if (in_array('getInstance', $methodsAnalysis['public_static'])) {
        try {
            $db = Database::getInstance();
            $connectionTests['getInstance'] = [
                'success' => true,
                'class' => get_class($db),
                'instance_methods' => get_class_methods($db)
            ];
            
            // 取得したインスタンスでクエリテスト
            if (method_exists($db, 'query')) {
                try {
                    $stmt = $db->query("SELECT 1 as test_connection, NOW() as current_time");
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
    
    // getConnection メソッドテスト（publicで静的な場合のみ）
    if (in_array('getConnection', $methodsAnalysis['public_static'])) {
        try {
            $connection = Database::getConnection();
            $connectionTests['getConnection'] = [
                'success' => true,
                'type' => gettype($connection),
                'class' => is_object($connection) ? get_class($connection) : null
            ];
        } catch (Exception $e) {
            $connectionTests['getConnection'] = [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    // 5. 代替接続方法（直接PDO）
    $directConnectionTest = [];
    if (defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER') && defined('DB_PASS')) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
            
            $stmt = $pdo->query("SELECT 1 as test, NOW() as time");
            $result = $stmt->fetch();
            
            $directConnectionTest = [
                'success' => true,
                'connection_class' => get_class($pdo),
                'query_result' => $result
            ];
            
        } catch (Exception $e) {
            $directConnectionTest = [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    } else {
        $directConnectionTest = [
            'success' => false,
            'error' => 'Database constants not defined'
        ];
    }
    
    respond(true, 'Database分析完了（安全版）', [
        'constants_before' => $beforeConstants,
        'constants_after' => $afterConstants,
        'constructor' => $constructorInfo,
        'methods' => $methodsAnalysis,
        'connection_tests' => $connectionTests,
        'direct_connection_test' => $directConnectionTest,
        'recommended_usage' => !empty($connectionTests) ? 
            array_keys(array_filter($connectionTests, function($test) { return $test['success']; })) : 
            ['direct_pdo']
    ]);
    
} catch (Exception $e) {
    respond(false, 'エラー発生', [
        'error' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]);
}
?>
