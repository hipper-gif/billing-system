<?php
/**
 * データベース接続テスト API
 * GET /api/test.php?action=db
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../classes/Database.php';

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
            $db = new Database();
            
            if (!$db->isConnected()) {
                sendError('データベース接続に失敗しました: ' . $db->getLastError());
            }
            
            // 基本情報取得
            $dbInfo = $db->checkDatabase();
            $systemInfo = $db->getSystemInfo();
            
            sendSuccess([
                'connection' => $dbInfo,
                'system' => $systemInfo,
                'environment' => ENVIRONMENT,
                'config' => [
                    'host' => DB_HOST,
                    'database' => DB_NAME,
                    'user' => DB_USER
                ]
            ], 'データベース接続成功');
            break;
            
        case 'info':
            // システム情報取得
            sendSuccess([
                'php_version' => phpversion(),
                'environment' => ENVIRONMENT,
                'system_name' => SYSTEM_NAME,
                'system_version' => SYSTEM_VERSION,
                'debug_mode' => DEBUG_MODE,
                'timezone' => date_default_timezone_get(),
                'memory_limit' => ini_get('memory_limit'),
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size'),
                'max_execution_time' => ini_get('max_execution_time'),
                'extensions' => [
                    'pdo' => extension_loaded('pdo'),
                    'pdo_mysql' => extension_loaded('pdo_mysql'),
                    'mbstring' => extension_loaded('mbstring'),
                    'json' => extension_loaded('json'),
                    'curl' => extension_loaded('curl'),
                    'gd' => extension_loaded('gd')
                ]
            ], 'システム情報取得成功');
            break;
            
        case 'dirs':
            // ディレクトリ状況チェック
            $directories = [
                'uploads' => 'uploads/',
                'temp' => 'temp/',
                'logs' => 'logs/',
                'cache' => 'cache/',
                'config' => 'config/',
                'api' => 'api/',
                'classes' => 'classes/',
                'assets' => 'assets/'
            ];
            
            $status = [];
            foreach ($directories as $name => $path) {
                $fullPath = __DIR__ . '/../' . $path;
                $status[$name] = [
                    'path' => $path,
                    'exists' => is_dir($fullPath),
                    'readable' => is_readable($fullPath),
                    'writable' => is_writable($fullPath),
                    'permissions' => is_dir($fullPath) ? substr(sprintf('%o', fileperms($fullPath)), -4) : null
                ];
                
                // 必要なディレクトリを自動作成
                if (!$status[$name]['exists'] && in_array($name, ['uploads', 'temp', 'logs', 'cache'])) {
                    if (mkdir($fullPath, 0755, true)) {
                        $status[$name]['exists'] = true;
                        $status[$name]['writable'] = is_writable($fullPath);
                        $status[$name]['created'] = true;
                    }
                }
            }
            
            sendSuccess($status, 'ディレクトリ状況チェック完了');
            break;
            
        case 'health':
            // ヘルスチェック
            $checks = [
                'database' => false,
                'directories' => false,
                'extensions' => false
            ];
            
            // DB接続チェック
            try {
                $db = new Database();
                $checks['database'] = $db->isConnected();
            } catch (Exception $e) {
                $checks['database'] = false;
            }
            
            // 必要ディレクトリチェック
            $requiredDirs = ['uploads', 'temp', 'logs', 'cache'];
            $dirOk = true;
            foreach ($requiredDirs as $dir) {
                $path = __DIR__ . '/../' . $dir . '/';
                if (!is_dir($path) || !is_writable($path)) {
                    $dirOk = false;
                    break;
                }
            }
            $checks['directories'] = $dirOk;
            
            // 必要拡張モジュールチェック
            $requiredExts = ['pdo', 'pdo_mysql', 'mbstring', 'json'];
            $extOk = true;
            foreach ($requiredExts as $ext) {
                if (!extension_loaded($ext)) {
                    $extOk = false;
                    break;
                }
            }
            $checks['extensions'] = $extOk;
            
            $allOk = $checks['database'] && $checks['directories'] && $checks['extensions'];
            
            sendSuccess([
                'overall' => $allOk,
                'checks' => $checks,
                'environment' => ENVIRONMENT,
                'timestamp' => date('Y-m-d H:i:s')
            ], $allOk ? 'システム正常' : 'システムに問題があります');
            break;
            
        default:
            sendError('無効なアクション: ' . $action, 400);
    }
    
} catch (Exception $e) {
    if (DEBUG_MODE) {
        sendError('エラー: ' . $e->getMessage() . ' (' . $e->getFile() . ':' . $e->getLine() . ')');
    } else {
        sendError('システムエラーが発生しました');
    }
}
?>