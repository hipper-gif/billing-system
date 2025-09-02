<?php
/**
 * 請求書生成対象取得API
 * invoice_generate.phpのloadTargetList関数から呼び出される
 * 
 * @author Claude
 * @version 1.1.0
 * @fixed 2025-09-02 - データベース接続エラー修正
 */

// セキュリティヘッダー設定
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// OPTIONSリクエスト対応
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    // 設定ファイル読み込み（修正版）
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../classes/Database.php';
    require_once __DIR__ . '/../classes/SecurityHelper.php';

    // セキュリティチェック
    SecurityHelper::validateRequest();

    // データベース接続（修正されたconfig/database.phpを使用）
    $db = new Database();
    
    // リクエストパラメータ取得
    $invoice_type = $_GET['invoice_type'] ?? 'company_bulk';
    $period_start = $_GET['period_start'] ?? '';
    $period_end = $_GET['period_end'] ?? '';
    
    // パラメータバリデーション
    if (empty($period_start) || empty($period_end)) {
        throw new Exception('請求期間が指定されていません');
    }
    
    // 請求書タイプに応じたデータ取得
    $targets = [];
    
    switch ($invoice_type) {
        case 'company_bulk':
            // 企業一括請求の対象企業取得
            $sql = "SELECT DISTINCT 
                        c.id,
                        c.company_name,
                        c.company_code,
                        c.billing_method,
                        c.payment_method,
                        COUNT(DISTINCT u.id) as user_count,
                        COUNT(o.id) as order_count,
                        COALESCE(SUM(o.total_amount), 0) as total_amount
                    FROM companies c
                    INNER JOIN users u ON c.id = u.company_id AND u.is_active = 1
                    INNER JOIN orders o ON u.user_code = o.user_code 
                        AND o.delivery_date BETWEEN ? AND ?
                    WHERE c.is_active = 1
                    GROUP BY c.id, c.company_name, c.company_code, c.billing_method, c.payment_method
                    ORDER BY c.company_name";
            
            $stmt = $db->query($sql, [$period_start, $period_end]);
            $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($companies as $company) {
                $targets[] = [
                    'id' => $company['id'],
                    'type' => 'company',
                    'name' => $company['company_name'],
                    'code' => $company['company_code'],
                    'user_count' => (int)$company['user_count'],
                    'order_count' => (int)$company['order_count'],
                    'amount' => (float)$company['total_amount'],
                    'billing_method' => $company['billing_method'],
                    'payment_method' => $company['payment_method'],
                    'display_text' => $company['company_name'] . " (利用者{$company['user_count']}名, 注文{$company['order_count']}件, ￥" . number_format($company['total_amount']) . ")"
                ];
            }
            break;
            
        case 'department_bulk':
            // 部署別一括請求の対象部署取得
            $sql = "SELECT DISTINCT 
                        d.id,
                        d.department_name,
                        d.department_code,
                        c.company_name,
                        c.billing_method,
                        c.payment_method,
                        COUNT(DISTINCT u.id) as user_count,
                        COUNT(o.id) as order_count,
                        COALESCE(SUM(o.total_amount), 0) as total_amount
                    FROM departments d
                    INNER JOIN companies c ON d.company_id = c.id
                    INNER JOIN users u ON d.id = u.department_id AND u.is_active = 1
                    INNER JOIN orders o ON u.user_code = o.user_code 
                        AND o.delivery_date BETWEEN ? AND ?
                    WHERE d.is_active = 1 AND c.is_active = 1
                    GROUP BY d.id, d.department_name, d.department_code, c.company_name, c.billing_method, c.payment_method
                    ORDER BY c.company_name, d.department_name";
            
            $stmt = $db->query($sql, [$period_start, $period_end]);
            $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($departments as $dept) {
                $targets[] = [
                    'id' => $dept['id'],
                    'type' => 'department',
                    'name' => $dept['department_name'],
                    'code' => $dept['department_code'],
                    'company_name' => $dept['company_name'],
                    'user_count' => (int)$dept['user_count'],
                    'order_count' => (int)$dept['order_count'],
                    'amount' => (float)$dept['total_amount'],
                    'billing_method' => $dept['billing_method'],
                    'payment_method' => $dept['payment_method'],
                    'display_text' => $dept['company_name'] . " - " . $dept['department_name'] . " (利用者{$dept['user_count']}名, 注文{$dept['order_count']}件, ￥" . number_format($dept['total_amount']) . ")"
                ];
            }
            break;
            
        case 'individual':
            // 個人請求の対象利用者取得
            $sql = "SELECT DISTINCT 
                        u.id,
                        u.user_name,
                        u.user_code,
                        u.payment_method,
                        c.company_name,
                        d.department_name,
                        COUNT(o.id) as order_count,
                        COALESCE(SUM(o.total_amount), 0) as total_amount
                    FROM users u
                    LEFT JOIN companies c ON u.company_id = c.id
                    LEFT JOIN departments d ON u.department_id = d.id
                    INNER JOIN orders o ON u.user_code = o.user_code 
                        AND o.delivery_date BETWEEN ? AND ?
                    WHERE u.is_active = 1
                    GROUP BY u.id, u.user_name, u.user_code, u.payment_method, c.company_name, d.department_name
                    ORDER BY c.company_name, d.department_name, u.user_name";
            
            $stmt = $db->query($sql, [$period_start, $period_end]);
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($users as $user) {
                $company_dept = '';
                if ($user['company_name']) {
                    $company_dept = $user['company_name'];
                    if ($user['department_name']) {
                        $company_dept .= ' - ' . $user['department_name'];
                    }
                }
                
                $targets[] = [
                    'id' => $user['id'],
                    'type' => 'user',
                    'name' => $user['user_name'],
                    'code' => $user['user_code'],
                    'company_name' => $user['company_name'],
                    'department_name' => $user['department_name'],
                    'order_count' => (int)$user['order_count'],
                    'amount' => (float)$user['total_amount'],
                    'payment_method' => $user['payment_method'],
                    'display_text' => $user['user_name'] . ($company_dept ? " ({$company_dept})" : '') . " (注文{$user['order_count']}件, ￥" . number_format($user['total_amount']) . ")"
                ];
            }
            break;
            
        case 'mixed':
            // 混合請求（企業設定に基づく）
            // 企業一括請求設定の企業
            $sql = "SELECT DISTINCT 
                        c.id,
                        c.company_name,
                        c.company_code,
                        'company' as target_type,
                        c.billing_method,
                        c.payment_method,
                        COUNT(DISTINCT u.id) as user_count,
                        COUNT(o.id) as order_count,
                        COALESCE(SUM(o.total_amount), 0) as total_amount
                    FROM companies c
                    INNER JOIN users u ON c.id = u.company_id AND u.is_active = 1
                    INNER JOIN orders o ON u.user_code = o.user_code 
                        AND o.delivery_date BETWEEN ? AND ?
                    WHERE c.is_active = 1 AND c.billing_method = 'company'
                    GROUP BY c.id, c.company_name, c.company_code, c.billing_method, c.payment_method
                    
                    UNION ALL
                    
                    SELECT DISTINCT 
                        u.id,
                        u.user_name as company_name,
                        u.user_code as company_code,
                        'user' as target_type,
                        'individual' as billing_method,
                        u.payment_method,
                        1 as user_count,
                        COUNT(o.id) as order_count,
                        COALESCE(SUM(o.total_amount), 0) as total_amount
                    FROM users u
                    LEFT JOIN companies c ON u.company_id = c.id
                    INNER JOIN orders o ON u.user_code = o.user_code 
                        AND o.delivery_date BETWEEN ? AND ?
                    WHERE u.is_active = 1 AND (c.billing_method = 'individual' OR c.billing_method IS NULL)
                    GROUP BY u.id, u.user_name, u.user_code, u.payment_method
                    
                    ORDER BY company_name";
            
            $stmt = $db->query($sql, [$period_start, $period_end, $period_start, $period_end]);
            $mixed_targets = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($mixed_targets as $target) {
                $targets[] = [
                    'id' => $target['id'],
                    'type' => $target['target_type'],
                    'name' => $target['company_name'],
                    'code' => $target['company_code'],
                    'user_count' => (int)$target['user_count'],
                    'order_count' => (int)$target['order_count'],
                    'amount' => (float)$target['total_amount'],
                    'billing_method' => $target['billing_method'],
                    'payment_method' => $target['payment_method'],
                    'display_text' => $target['company_name'] . " (" . ($target['target_type'] === 'company' ? '企業請求' : '個人請求') . ", 注文{$target['order_count']}件, ￥" . number_format($target['total_amount']) . ")"
                ];
            }
            break;
            
        default:
            throw new Exception('サポートされていない請求書タイプです: ' . $invoice_type);
    }
    
    // 統計情報計算
    $total_targets = count($targets);
    $total_amount = array_sum(array_column($targets, 'amount'));
    $total_orders = array_sum(array_column($targets, 'order_count'));
    
    // レスポンス返却
    echo json_encode([
        'success' => true,
        'data' => [
            'targets' => $targets,
            'statistics' => [
                'total_targets' => $total_targets,
                'total_amount' => $total_amount,
                'total_orders' => $total_orders,
                'period_start' => $period_start,
                'period_end' => $period_end,
                'invoice_type' => $invoice_type
            ]
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (PDOException $e) {
    error_log('Database Error in invoice_targets.php: ' . $e->getMessage());
    
    // データベース接続エラーの詳細診断
    $error_details = [
        'error_code' => $e->getCode(),
        'error_message' => $e->getMessage(),
        'suggestion' => ''
    ];
    
    if (strpos($e->getMessage(), 'getaddrinfo') !== false) {
        $error_details['suggestion'] = 'データベースホスト名を確認してください。mysql1.php.xserver.jpではなくmysql1.xserver.jpが正しいホスト名です。';
    } elseif (strpos($e->getMessage(), 'Access denied') !== false) {
        $error_details['suggestion'] = 'データベースユーザー名またはパスワードが間違っています。エックスサーバーの管理画面で確認してください。';
    } elseif (strpos($e->getMessage(), 'Unknown database') !== false) {
        $error_details['suggestion'] = 'データベース名が間違っているか、データベースが存在しません。';
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'データベース接続エラー: ' . $e->getMessage(),
        'error_details' => $error_details,
        'debug_info' => [
            'php_version' => PHP_VERSION,
            'pdo_available' => extension_loaded('pdo'),
            'pdo_mysql_available' => extension_loaded('pdo_mysql'),
            'server_name' => $_SERVER['SERVER_NAME'] ?? 'unknown'
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Exception $e) {
    error_log('General Error in invoice_targets.php: ' . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
?>
