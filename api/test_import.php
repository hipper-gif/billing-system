<?php
/**
 * シンプルテスト用CSVインポートAPI
 * api/test_import.php
 */

// エラー表示設定
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ヘッダー設定
header('Content-Type: application/json; charset=utf-8');

// GET/POSTどちらでも対応
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? 'test';

// 応答用関数
function respond($success, $message, $data = []) {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'method' => $_SERVER['REQUEST_METHOD'],
        'timestamp' => date('Y-m-d H:i:s'),
        'php_version' => PHP_VERSION
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // アクション別処理
    switch ($action) {
        case 'test':
            respond(true, 'テスト用API正常稼働中', [
                'version' => '1.0.0',
                'method' => $method,
                'supported_methods' => ['GET', 'POST'],
                'current_time' => date('Y-m-d H:i:s')
            ]);
            break;
            
        case 'files':
            // ファイル存在確認
            $files = [
                'database_config' => '../config/database.php',
                'database_class' => '../classes/Database.php',
                'csv_importer' => '../classes/SmileyCSVImporter.php',
                'security_helper' => '../classes/SecurityHelper.php',
                'class_loader' => '../classes/ClassLoader.php',
                'original_import' => '../api/import.php'
            ];
            
            $fileStatus = [];
            foreach ($files as $name => $path) {
                $fileStatus[$name] = [
                    'exists' => file_exists($path),
                    'readable' => file_exists($path) && is_readable($path),
                    'size' => file_exists($path) ? filesize($path) : 0,
                    'modified' => file_exists($path) ? date('Y-m-d H:i:s', filemtime($path)) : null
                ];
            }
            
            respond(true, 'ファイル状況確認完了', $fileStatus);
            break;
            
        case 'classes':
            // クラス読み込みテスト
            $classStatus = [];
            
            // 1. データベース設定
            try {
                require_once '../config/database.php';
                $classStatus['database_config'] = 'OK';
            } catch (Exception $e) {
                $classStatus['database_config'] = 'ERROR: ' . $e->getMessage();
            }
            
            // 2. Databaseクラス
            try {
                if (!class_exists('Database')) {
                    require_once '../classes/Database.php';
                }
                $classStatus['database_class'] = class_exists('Database') ? 'OK' : 'NOT_FOUND';
            } catch (Exception $e) {
                $classStatus['database_class'] = 'ERROR: ' . $e->getMessage();
            }
            
            // 3. SecurityHelperクラス
            try {
                if (!class_exists('SecurityHelper')) {
                    require_once '../classes/SecurityHelper.php';
                }
                $classStatus['security_helper'] = class_exists('SecurityHelper') ? 'OK' : 'NOT_FOUND';
            } catch (Exception $e) {
                $classStatus['security_helper'] = 'ERROR: ' . $e->getMessage();
            }
            
            // 4. SmileyCSVImporterクラス
            try {
                if (!class_exists('SmileyCSVImporter')) {
                    require_once '../classes/SmileyCSVImporter.php';
                }
                $classStatus['csv_importer'] = class_exists('SmileyCSVImporter') ? 'OK' : 'NOT_FOUND';
            } catch (Exception $e) {
                $classStatus['csv_importer'] = 'ERROR: ' . $e->getMessage();
            }
            
            respond(true, 'クラス読み込み状況確認完了', [
                'class_status' => $classStatus,
                'declared_classes' => array_values(array_filter(get_declared_classes(), function($class) {
                    return in_array($class, ['Database', 'SecurityHelper', 'SmileyCSVImporter', 'ClassLoader']);
                }))
            ]);
            break;
            
        case 'database':
            // データベース接続テスト
            try {
                require_once '../config/database.php';
                if (!class_exists('Database')) {
                    require_once '../classes/Database.php';
                }
                
                $db = new Database();
                
                // テーブル確認
                $tables = ['companies', 'departments', 'users', 'suppliers', 'products', 'orders'];
                $tableStatus = [];
                
                foreach ($tables as $table) {
                    try {
                        $stmt = $db->query("SHOW TABLES LIKE '{$table}'");
                        $tableStatus[$table] = $stmt->rowCount() > 0;
                    } catch (Exception $e) {
                        $tableStatus[$table] = 'ERROR: ' . $e->getMessage();
                    }
                }
                
                respond(true, 'データベース接続成功', [
                    'connection' => true,
                    'tables' => $tableStatus
                ]);
                
            } catch (Exception $e) {
                respond(false, 'データベース接続エラー', [
                    'error' => $e->getMessage(),
                    'file' => basename($e->getFile()),
                    'line' => $e->getLine()
                ]);
            }
            break;
            
        case 'csv_test':
            // CSVインポーター動作テスト
            try {
                require_once '../config/database.php';
                if (!class_exists('Database')) {
                    require_once '../classes/Database.php';
                }
                if (!class_exists('SmileyCSVImporter')) {
                    require_once '../classes/SmileyCSVImporter.php';
                }
                
                $db = new Database();
                $importer = new SmileyCSVImporter($db);
                
                respond(true, 'CSVインポーター初期化成功', [
                    'importer_created' => true,
                    'available_methods' => get_class_methods($importer)
                ]);
                
            } catch (Exception $e) {
                respond(false, 'CSVインポーター初期化エラー', [
                    'error' => $e->getMessage(),
                    'file' => basename($e->getFile()),
                    'line' => $e->getLine()
                ]);
            }
            break;
            
        default:
            respond(false, '不明なアクションです', [
                'available_actions' => ['test', 'files', 'classes', 'database', 'csv_test'],
                'requested_action' => $action
            ]);
    }
    
} catch (Exception $e) {
    respond(false, 'システムエラー', [
        'error' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]);
}
?>
