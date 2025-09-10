<?php
/**
 * CSV インポート API - Smiley配食事業システム
 * 仕様書 v2.0 完全準拠版
 * 
 * ファイル: /api/import.php
 * 用途: CSVファイルインポート処理
 * 対応: 23フィールドSmiley配食事業CSV、SJIS-win対応
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

// セキュリティヘッダー設定
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// OPTIONS リクエスト対応（CORS）
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 必要なクラス読み込み
try {
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../classes/Database.php';
    require_once __DIR__ . '/../classes/SmileyCSVImporter.php';
    require_once __DIR__ . '/../classes/SecurityHelper.php';
    require_once __DIR__ . '/../classes/FileUploadHandler.php';
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'システム初期化エラー',
        'data' => [
            'error' => 'クラスファイルの読み込みに失敗しました',
            'file' => 'import.php',
            'line' => __LINE__
        ],
        'timestamp' => date('Y-m-d H:i:s'),
        'version' => '4.0.0-specification-compliant'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// メイン処理
try {
    // データベース接続（Singleton パターン）
    $db = Database::getInstance();
    
    // 接続テスト（仕様準拠）
    if (!$db->testConnection()) {
        throw new Exception('データベース接続に失敗しました');
    }
    
    // HTTPメソッド判定
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'POST':
            handleCSVImport($db);
            break;
            
        case 'GET':
            handleGetRequest($db);
            break;
            
        default:
            throw new Exception('サポートされていないHTTPメソッドです: ' . $_SERVER['REQUEST_METHOD']);
    }
    
} catch (Exception $e) {
    // エラーハンドリング（仕様準拠）
    $errorResponse = [
        'success' => false,
        'message' => 'システムエラーが発生しました',
        'data' => [
            'error' => $e->getMessage(),
            'file' => 'import.php',
            'line' => $e->getLine()
        ],
        'timestamp' => date('Y-m-d H:i:s'),
        'version' => '4.0.0-specification-compliant'
    ];
    
    // ログ記録
    error_log("[Import API Error] " . json_encode($errorResponse, JSON_UNESCAPED_UNICODE));
    
    http_response_code(500);
    echo json_encode($errorResponse, JSON_UNESCAPED_UNICODE);
}

/**
 * CSV インポート処理（POST）
 */
function handleCSVImport($db) {
    // ファイルアップロード確認
    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('CSVファイルがアップロードされていません');
    }
    
    $uploadedFile = $_FILES['csv_file'];
    
    // セキュリティ検証
    $securityHelper = new SecurityHelper();
    if (!$securityHelper::validateFileUpload($uploadedFile)) {
        throw new Exception('アップロードされたファイルは安全ではありません');
    }
    
    // ファイルアップロード処理
    $fileHandler = new FileUploadHandler();
    $uploadResult = $fileHandler->handleUpload($uploadedFile);
    
    if (!$uploadResult['success']) {
        throw new Exception('ファイルアップロードに失敗しました: ' . implode(', ', $uploadResult['errors']));
    }
    
    $tempFilePath = $uploadResult['filepath'];
    
    try {
        // CSV インポーター初期化
        $importer = new SmileyCSVImporter($db);
        
        // インポートオプション設定
        $importOptions = [
            'encoding' => $_POST['encoding'] ?? 'auto',
            'overwrite' => isset($_POST['overwrite']) ? (bool)$_POST['overwrite'] : false,
            'validate_smiley' => true,
            'batch_size' => 1000,
            'debug_mode' => isset($_POST['debug_mode']) ? (bool)$_POST['debug_mode'] : false
        ];
        
        // CSV インポート実行
        $importResult = $importer->importFile($tempFilePath, $importOptions);
        
        // 成功レスポンス
        $response = [
            'success' => true,
            'message' => 'CSVインポートが完了しました',
            'data' => [
                'batch_id' => $importResult['batch_id'],
                'import_summary' => [
                    'total_records' => $importResult['stats']['total'] ?? 0,
                    'success_records' => $importResult['stats']['success'] ?? 0,
                    'error_records' => count($importResult['errors'] ?? []),
                    'duplicate_records' => $importResult['stats']['duplicate'] ?? 0
                ],
                'processing_time' => $importResult['processing_time'] ?? 0,
                'encoding_detected' => $importResult['encoding'] ?? 'UTF-8',
                'file_info' => [
                    'original_name' => $uploadedFile['name'],
                    'size_bytes' => $uploadedFile['size'],
                    'size_mb' => round($uploadedFile['size'] / 1024 / 1024, 2)
                ]
            ],
            'errors' => $importResult['errors'] ?? [],
            'timestamp' => date('Y-m-d H:i:s'),
            'version' => '4.0.0-specification-compliant'
        ];
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        
    } finally {
        // 一時ファイル削除
        if (file_exists($tempFilePath)) {
            unlink($tempFilePath);
        }
    }
}

/**
 * GET リクエスト処理
 */
function handleGetRequest($db) {
    $action = $_GET['action'] ?? 'status';
    
    switch ($action) {
        case 'status':
            handleSystemStatus($db);
            break;
            
        case 'history':
            handleImportHistory($db);
            break;
            
        case 'health':
            handleHealthCheck($db);
            break;
            
        default:
            throw new Exception('不正なアクションです: ' . $action);
    }
}

/**
 * システム状態確認
 */
function handleSystemStatus($db) {
    $systemStatus = [
        'database' => [
            'connected' => $db->testConnection(),
            'tables_exist' => checkRequiredTables($db),
            'version' => getDatabaseVersion($db)
        ],
        'php' => [
            'version' => PHP_VERSION,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize')
        ],
        'disk_space' => [
            'free_mb' => round(disk_free_space('.') / 1024 / 1024, 2),
            'total_mb' => round(disk_total_space('.') / 1024 / 1024, 2)
        ],
        'import_stats' => getRecentImportStats($db)
    ];
    
    $response = [
        'success' => true,
        'message' => 'システム状態を正常に取得しました',
        'data' => $systemStatus,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
}

/**
 * インポート履歴取得
 */
function handleImportHistory($db) {
    $limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 100) : 20;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    
    $sql = "SELECT 
                batch_id, file_name, file_type, file_size,
                total_records, success_records, error_records,
                import_start, import_end, status, created_by
            FROM import_logs 
            ORDER BY import_start DESC 
            LIMIT ? OFFSET ?";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$limit, $offset]);
    $importHistory = $stmt->fetchAll();
    
    // 総件数取得
    $countSql = "SELECT COUNT(*) FROM import_logs";
    $totalCount = $db->query($countSql)->fetchColumn();
    
    $response = [
        'success' => true,
        'message' => 'インポート履歴を取得しました',
        'data' => [
            'import_history' => $importHistory,
            'pagination' => [
                'total_count' => $totalCount,
                'limit' => $limit,
                'offset' => $offset,
                'has_more' => ($offset + $limit) < $totalCount
            ]
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
}

/**
 * ヘルスチェック
 */
function handleHealthCheck($db) {
    $healthStatus = [
        'overall' => 'healthy',
        'checks' => [
            'database' => $db->testConnection() ? 'healthy' : 'unhealthy',
            'required_tables' => checkRequiredTables($db) ? 'healthy' : 'unhealthy',
            'disk_space' => checkDiskSpace(),
            'memory_usage' => checkMemoryUsage()
        ]
    ];
    
    // 総合判定
    $unhealthyChecks = array_filter($healthStatus['checks'], function($status) {
        return $status !== 'healthy';
    });
    
    if (!empty($unhealthyChecks)) {
        $healthStatus['overall'] = 'unhealthy';
    }
    
    $response = [
        'success' => true,
        'message' => 'ヘルスチェックが完了しました',
        'data' => $healthStatus,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
}

/**
 * 必須テーブル存在確認
 */
function checkRequiredTables($db) {
    $requiredTables = [
        'companies', 'departments', 'users', 'orders', 
        'products', 'suppliers', 'invoices', 'payments', 'import_logs'
    ];
    
    foreach ($requiredTables as $table) {
        if (!$db->tableExists($table)) {
            return false;
        }
    }
    
    return true;
}

/**
 * データベースバージョン取得
 */
function getDatabaseVersion($db) {
    try {
        $stmt = $db->query("SELECT VERSION() as version");
        $result = $stmt->fetch();
        return $result['version'] ?? 'unknown';
    } catch (Exception $e) {
        return 'error: ' . $e->getMessage();
    }
}

/**
 * 最近のインポート統計取得
 */
function getRecentImportStats($db) {
    try {
        $sql = "SELECT 
                    COUNT(*) as total_imports,
                    SUM(success_records) as total_success_records,
                    SUM(error_records) as total_error_records,
                    MAX(import_start) as last_import_date
                FROM import_logs 
                WHERE import_start >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        
        $stmt = $db->query($sql);
        return $stmt->fetch();
    } catch (Exception $e) {
        return [
            'total_imports' => 0,
            'total_success_records' => 0,
            'total_error_records' => 0,
            'last_import_date' => null,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * ディスク容量チェック
 */
function checkDiskSpace() {
    $freeBytes = disk_free_space('.');
    $totalBytes = disk_total_space('.');
    $usagePercent = (($totalBytes - $freeBytes) / $totalBytes) * 100;
    
    if ($usagePercent > 90) {
        return 'critical';
    } elseif ($usagePercent > 80) {
        return 'warning';
    } else {
        return 'healthy';
    }
}

/**
 * メモリ使用量チェック
 */
function checkMemoryUsage() {
    $memoryUsage = memory_get_usage(true);
    $memoryLimit = ini_get('memory_limit');
    
    // memory_limit を bytes に変換
    $memoryLimitBytes = parseMemoryLimit($memoryLimit);
    
    if ($memoryLimitBytes > 0) {
        $usagePercent = ($memoryUsage / $memoryLimitBytes) * 100;
        
        if ($usagePercent > 90) {
            return 'critical';
        } elseif ($usagePercent > 80) {
            return 'warning';
        }
    }
    
    return 'healthy';
}

/**
 * memory_limit 文字列をバイトに変換
 */
function parseMemoryLimit($limit) {
    $limit = trim($limit);
    $last = strtolower($limit[strlen($limit)-1]);
    $value = (int)$limit;
    
    switch ($last) {
        case 'g':
            $value *= 1024;
        case 'm':
            $value *= 1024;
        case 'k':
            $value *= 1024;
    }
    
    return $value;
}
