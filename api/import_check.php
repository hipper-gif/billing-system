<?php
/**
 * インポート結果確認API
 * データベースの実際の登録状況を確認
 */

// ヘッダー設定
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// レスポンス関数
function sendResponse($success, $message, $data = [], $code = 200) {
    http_response_code($code);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s'),
        'version' => 'check-1.0'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// OPTIONS対応
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    sendResponse(true, 'OPTIONS OK');
}

try {
    // 必須ファイル読み込み
    require_once '../config/database.php';
    require_once '../classes/Database.php';
    
    // データベース接続
    $db = Database::getInstance();
    
    $action = $_GET['action'] ?? 'summary';
    
    switch ($action) {
        case 'summary':
            // データベース全体のサマリー
            $summary = getDataSummary($db);
            sendResponse(true, 'データサマリー取得成功', $summary);
            break;
            
        case 'recent_imports':
            // 最近のインポート履歴
            $imports = getRecentImports($db);
            sendResponse(true, '最近のインポート履歴', $imports);
            break;
            
        case 'latest_orders':
            // 最新の注文データ
            $orders = getLatestOrders($db);
            sendResponse(true, '最新の注文データ', $orders);
            break;
            
        case 'table_counts':
            // 各テーブルの件数
            $counts = getTableCounts($db);
            sendResponse(true, 'テーブル件数', $counts);
            break;
            
        default:
            sendResponse(false, '不明なアクション', [
                'available_actions' => ['summary', 'recent_imports', 'latest_orders', 'table_counts']
            ], 400);
    }
    
} catch (Exception $e) {
    sendResponse(false, 'データ確認エラー', [
        'error' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ], 500);
}

/**
 * データサマリー取得
 */
function getDataSummary($db) {
    $summary = [
        'database_info' => [],
        'table_counts' => [],
        'recent_activity' => [],
        'data_quality' => []
    ];
    
    // データベース情報
    $stmt = $db->query("SELECT DATABASE() as db_name, NOW() as current_time");
    $dbInfo = $stmt->fetch();
    $summary['database_info'] = $dbInfo;
    
    // テーブル件数
    $tables = ['companies', 'departments', 'users', 'suppliers', 'products', 'orders', 'import_logs'];
    foreach ($tables as $table) {
        try {
            $stmt = $db->query("SELECT COUNT(*) as count FROM {$table}");
            $result = $stmt->fetch();
            $summary['table_counts'][$table] = $result['count'];
        } catch (Exception $e) {
            $summary['table_counts'][$table] = 'エラー: ' . $e->getMessage();
        }
    }
    
    // 最新のインポート活動
    try {
        $stmt = $db->query("
            SELECT batch_id, total_rows, success_rows, error_rows, import_date, status 
            FROM import_logs 
            ORDER BY import_date DESC 
            LIMIT 5
        ");
        $summary['recent_activity'] = $stmt->fetchAll();
    } catch (Exception $e) {
        $summary['recent_activity'] = ['エラー: ' . $e->getMessage()];
    }
    
    // データ品質チェック
    try {
        // 最新の注文データ
        $stmt = $db->query("
            SELECT COUNT(*) as total_orders,
                   COUNT(DISTINCT company_id) as unique_companies,
                   COUNT(DISTINCT user_id) as unique_users,
                   MIN(delivery_date) as earliest_delivery,
                   MAX(delivery_date) as latest_delivery
            FROM orders
        ");
        $summary['data_quality'] = $stmt->fetch();
    } catch (Exception $e) {
        $summary['data_quality'] = ['エラー: ' . $e->getMessage()];
    }
    
    return $summary;
}

/**
 * 最近のインポート履歴取得
 */
function getRecentImports($db) {
    $stmt = $db->query("
        SELECT batch_id, file_name, total_rows, success_rows, error_rows, 
               new_companies, new_users, import_date, status, notes
        FROM import_logs 
        ORDER BY import_date DESC 
        LIMIT 10
    ");
    
    $imports = $stmt->fetchAll();
    
    // notes（JSON）をデコード
    foreach ($imports as &$import) {
        if (!empty($import['notes'])) {
            $import['notes_decoded'] = json_decode($import['notes'], true);
        }
    }
    
    return $imports;
}

/**
 * 最新の注文データ取得
 */
function getLatestOrders($db) {
    $stmt = $db->query("
        SELECT o.id, o.delivery_date, o.user_name, o.company_name, 
               o.product_name, o.quantity, o.unit_price, o.total_amount,
               o.import_batch_id, o.created_at
        FROM orders o
        ORDER BY o.created_at DESC
        LIMIT 20
    ");
    
    return $stmt->fetchAll();
}

/**
 * テーブル件数取得
 */
function getTableCounts($db) {
    $tables = [
        'companies' => '配達先企業',
        'departments' => '部署', 
        'users' => '利用者',
        'suppliers' => '給食業者',
        'products' => '商品',
        'orders' => '注文',
        'import_logs' => 'インポートログ'
    ];
    
    $counts = [];
    
    foreach ($tables as $table => $description) {
        try {
            $stmt = $db->query("SELECT COUNT(*) as count FROM {$table}");
            $result = $stmt->fetch();
            $counts[$table] = [
                'description' => $description,
                'count' => (int)$result['count'],
                'status' => 'success'
            ];
        } catch (Exception $e) {
            $counts[$table] = [
                'description' => $description,
                'count' => 0,
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    }
    
    return $counts;
}
?>
