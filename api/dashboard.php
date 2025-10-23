<?php
/**
 * ダッシュボードAPI
 * メイン画面で使用する統計情報・活動履歴・アラートを提供
 * 
 * @author Claude
 * @version 1.0.0
 * @created 2025-08-26
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// プリフライトリクエスト対応
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../classes/SecurityHelper.php';

try {
    // セキュリティヘッダー設定
    SecurityHelper::setSecurityHeaders();
    
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? 'statistics';
    
    if ($method !== 'GET') {
        throw new Exception('GET メソッドのみサポートされています');
    }
    
    $db = Database::getInstance();
    
    switch ($action) {
        case 'statistics':
            getDashboardStatistics($db);
            break;
            
        case 'monthly_sales':
            getMonthlySales($db);
            break;
            
        case 'recent_activities':
            getRecentActivities($db);
            break;
            
        case 'system_alerts':
            getSystemAlerts($db);
            break;
            
        default:
            throw new Exception('未対応のアクションです');
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * ダッシュボード統計情報取得
 */
function getDashboardStatistics($db) {
    // 今月の売上
    $stmt = $db->prepare("
        SELECT 
            COALESCE(SUM(o.total_amount), 0) as total_sales,
            COUNT(o.id) as total_orders,
            COUNT(DISTINCT o.user_id) as active_users,
            COALESCE(AVG(o.unit_price), 0) as avg_unit_price
        FROM orders o
        WHERE DATE_FORMAT(o.delivery_date, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')
    ");
    $stmt->execute();
    $salesData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // 請求書統計
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_invoices,
            COUNT(CASE WHEN status = 'issued' THEN 1 END) as issued_count,
            COUNT(CASE WHEN status = 'sent' THEN 1 END) as sent_count,
            COUNT(CASE WHEN status = 'paid' THEN 1 END) as paid_count,
            COUNT(CASE WHEN status = 'overdue' THEN 1 END) as overdue_count,
            COALESCE(SUM(CASE WHEN status IN ('issued', 'sent') THEN total_amount ELSE 0 END), 0) as pending_amount,
            COALESCE(SUM(CASE WHEN status = 'overdue' THEN total_amount ELSE 0 END), 0) as overdue_amount
        FROM invoices
        WHERE DATE_FORMAT(issue_date, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')
    ");
    $stmt->execute();
    $invoiceData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // システム概要
    $stmt = $db->prepare("
        SELECT 
            (SELECT COUNT(*) FROM companies WHERE is_active = 1) as total_companies,
            (SELECT COUNT(*) FROM users WHERE is_active = 1) as total_users,
            (SELECT COUNT(*) FROM departments WHERE is_active = 1) as total_departments
    ");
    $stmt->execute();
    $systemData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $result = array_merge($salesData, $invoiceData, $systemData);
    
    echo json_encode([
        'success' => true,
        'data' => $result,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * 月別売上データ取得
 */
function getMonthlySales($db) {
    $stmt = $db->prepare("
        SELECT 
            DATE_FORMAT(delivery_date, '%Y-%m') as month,
            COALESCE(SUM(total_amount), 0) as sales
        FROM orders
        WHERE delivery_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(delivery_date, '%Y-%m')
        ORDER BY month ASC
    ");
    $stmt->execute();
    $monthlyData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 過去6ヶ月のラベル生成
    $labels = [];
    $values = [];
    $dataMap = [];
    
    foreach ($monthlyData as $data) {
        $dataMap[$data['month']] = floatval($data['sales']);
    }
    
    for ($i = 5; $i >= 0; $i--) {
        $month = date('Y-m', strtotime("-{$i} month"));
        $monthLabel = date('n月', strtotime("-{$i} month"));
        $labels[] = $monthLabel;
        $values[] = $dataMap[$month] ?? 0;
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'labels' => $labels,
            'values' => $values
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * 最近の活動取得
 */
function getRecentActivities($db) {
    // CSVインポート履歴
    $stmt = $db->prepare("
        SELECT 
            CONCAT('CSVインポート: ', processed_count, '件処理') as title,
            created_at
        FROM import_logs
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $stmt->execute();
    $importActivities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 請求書生成履歴
    $stmt = $db->prepare("
        SELECT 
            CONCAT('請求書生成: ', invoice_number, ' (¥', FORMAT(total_amount, 0), ')') as title,
            created_at
        FROM invoices
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $stmt->execute();
    $invoiceActivities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 活動をマージして時系列順にソート
    $activities = array_merge($importActivities, $invoiceActivities);
    
    usort($activities, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    
    $activities = array_slice($activities, 0, 10);
    
    echo json_encode([
        'success' => true,
        'data' => $activities,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * システムアラート取得
 */
function getSystemAlerts($db) {
    $alerts = [];
    
    // 期限超過請求書チェック
    $stmt = $db->prepare("
        SELECT COUNT(*) as overdue_count
        FROM invoices
        WHERE due_date < CURDATE() 
        AND status IN ('issued', 'sent')
    ");
    $stmt->execute();
    $overdueData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($overdueData['overdue_count'] > 0) {
        $alerts[] = [
            'type' => 'error',
            'title' => '期限超過請求書',
            'message' => "{$overdueData['overdue_count']}件の請求書が支払期限を過ぎています",
            'created_at' => date('Y-m-d H:i:s')
        ];
    }
    
    // 未インポートファイルチェック（例）
    $stmt = $db->prepare("
        SELECT COUNT(*) as recent_imports
        FROM import_logs
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $stmt->execute();
    $importData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($importData['recent_imports'] == 0) {
        $alerts[] = [
            'type' => 'warning',
            'title' => 'データ更新',
            'message' => '過去7日間CSVデータの取り込みがありません',
            'created_at' => date('Y-m-d H:i:s')
        ];
    }
    
    // 新機能お知らせ（例）
    $alerts[] = [
        'type' => 'info',
        'title' => 'システム更新',
        'message' => 'Smiley Kitchen専用デザインに更新されました',
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    echo json_encode([
        'success' => true,
        'data' => $alerts,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * 日付フォーマット
 */
function formatDate($date) {
    return date('Y年m月d日', strtotime($date));
}

/**
 * 数値フォーマット
 */
function formatNumber($number) {
    return number_format(floatval($number));
}
?>
