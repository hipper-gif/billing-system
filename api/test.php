<?php
/**
 * システムテスト API（シンプル版）
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// エラーハンドリング
function sendError($message, $code = 500) {
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'error' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function sendSuccess($data, $message = 'Success') {
    echo json_encode([
        'success' => true,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'db':
            // データベース接続テスト
            require_once __DIR__ . '/../classes/Database.php';
            
            $db = new Database();
            
            if (!$db->isConnected()) {
                sendError('データベース接続に失敗: ' . $db->getLastError());
            }
            
            $dbInfo = $db->checkDatabase();
            $systemInfo = $db->getSystemInfo();
            
            sendSuccess([
                'connection' => $dbInfo,
                'system' => $systemInfo,
                'environment' => defined('ENVIRONMENT') ? ENVIRONMENT : 'unknown'
            ], 'データベース接続成功');
            break;
            
        case 'info':
            // システム情報取得
            sendSuccess([
                'php_version' => phpversion(),
                'environment' => defined('ENVIRONMENT') ? ENVIRONMENT : 'unknown',
                'system_name' => defined('SYSTEM_NAME') ? SYSTEM_NAME : 'Smiley配食システム',
                'debug_mode' => defined('DEBUG_MODE') ? DEBUG_MODE : false,
                'timezone' => date_default_timezone_get(),
                'memory_limit' => ini_get('memory_limit'),
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'extensions' => [
                    'pdo' => extension_loaded('pdo'),
                    'pdo_mysql' => extension_loaded('pdo_mysql'),
                    'mbstring' => extension_loaded('mbstring'),
                    'json' => extension_loaded('json')
                ]
            ], 'システム情報取得成功');
            break;
            
        case 'health':
            // ヘルスチェック
            $checks = [
                'php' => true,
                'extensions' => extension_loaded('pdo') && extension_loaded('pdo_mysql'),
                'directories' => true
            ];
            
            // ディレクトリチェック
            $requiredDirs = ['uploads', 'temp', 'logs', 'cache'];
            foreach ($requiredDirs as $dir) {
                $path = __DIR__ . '/../' . $dir . '/';
                if (!is_dir($path)) {
                    @mkdir($path, 0755, true);
                }
            }
            
            $allOk = $checks['php'] && $checks['extensions'] && $checks['directories'];
            
            sendSuccess([
                'overall' => $allOk,
                'checks' => $checks,
                'environment' => defined('ENVIRONMENT') ? ENVIRONMENT : 'unknown'
            ], $allOk ? 'システム正常' : 'システムに問題があります');
            break;
            
        default:
            sendError('無効なアクション: ' . $action, 400);
    }
    
} catch (Exception $e) {
    sendError('システムエラー: ' . $e->getMessage());
}
?>
