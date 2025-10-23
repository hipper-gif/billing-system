<?php
/**
 * Database接続パターン確認用
 * api/check_database.php
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
    // 1. Database クラス読み込み
    require_once '../config/database.php';

    // 2. Database クラスの詳細情報取得
    $reflection = new ReflectionClass('Database');
    $constructor = $reflection->getConstructor();
    $methods = $reflection->getMethods();
    
    $methodsInfo = [];
    foreach ($methods as $method) {
        if ($method->isPublic() && $method->isStatic()) {
            $methodsInfo['static_public'][] = $method->getName();
        }
        if ($method->isPublic() && !$method->isStatic()) {
            $methodsInfo['instance_public'][] = $method->getName();
        }
    }
    
    // 3. シングルトンパターンかチェック
    $hasGetInstance = $reflection->hasMethod('getInstance');
    $hasConnect = $reflection->hasMethod('connect');
    $hasGetConnection = $reflection->hasMethod('getConnection');
    
    // 4. 接続方法のテスト
    $connectionTests = [];
    
    // Test 1: getInstance() メソッド
    if ($hasGetInstance) {
        try {
            $db = Database::getInstance();
            $connectionTests['getInstance'] = [
                'success' => true,
                'type' => get_class($db)
            ];
        } catch (Exception $e) {
            $connectionTests['getInstance'] = [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    // Test 2: connect() メソッド
    if ($hasConnect) {
        try {
            $db = Database::connect();
            $connectionTests['connect'] = [
                'success' => true,
                'type' => is_object($db) ? get_class($db) : gettype($db)
            ];
        } catch (Exception $e) {
            $connectionTests['connect'] = [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    // Test 3: getConnection() メソッド
    if ($hasGetConnection) {
        try {
            $db = Database::getConnection();
            $connectionTests['getConnection'] = [
                'success' => true,
                'type' => is_object($db) ? get_class($db) : gettype($db)
            ];
        } catch (Exception $e) {
            $connectionTests['getConnection'] = [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    // Test 4: 直接PDO作成
    try {
        $host = DB_HOST ?? 'localhost';
        $dbname = DB_NAME ?? '';
        $username = DB_USER ?? '';
        $password = DB_PASS ?? '';
        
        $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        
        $connectionTests['direct_pdo'] = [
            'success' => true,
            'type' => 'PDO',
            'server_info' => $pdo->getAttribute(PDO::ATTR_SERVER_INFO)
        ];
    } catch (Exception $e) {
        $connectionTests['direct_pdo'] = [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
    
    respond(true, 'Database クラス分析完了', [
        'constructor_visibility' => $constructor ? (
            $constructor->isPublic() ? 'public' : (
                $constructor->isPrivate() ? 'private' : 'protected'
            )
        ) : 'none',
        'has_getInstance' => $hasGetInstance,
        'has_connect' => $hasConnect,
        'has_getConnection' => $hasGetConnection,
        'methods' => $methodsInfo,
        'connection_tests' => $connectionTests,
        'constants' => [
            'DB_HOST' => defined('DB_HOST') ? DB_HOST : 'not defined',
            'DB_NAME' => defined('DB_NAME') ? DB_NAME : 'not defined',
            'DB_USER' => defined('DB_USER') ? DB_USER : 'not defined'
        ]
    ]);
    
} catch (Exception $e) {
    respond(false, 'エラー発生', [
        'error' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]);
}
?>
