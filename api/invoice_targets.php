<?php
/**
 * invoice_targets.php 修正パッチ
 * 
 * 問題: 38行目で new Database() を使用している
 * 解決: Database::getInstance() に変更
 * 
 * このファイルの内容で既存のinvoice_targets.phpを修正してください
 */

// 修正前（エラーが発生する行）:
// $db = new Database();

// 修正後（正しいSingleton使用）:
// $db = Database::getInstance();

/**
 * 完全なinvoice_targets.php修正版
 * 既存ファイルをこの内容で完全に置き換えてください
 */
?>
<?php
/**
 * 請求書対象選択API（修正版）
 * invoice_generate.php の対象一覧読み込み用APIエンドポイント
 * 
 * Database Singleton対応版
 * 
 * @author Claude
 * @version 1.1.0 (Singleton対応)
 * @created 2025-09-11
 */

// エラー出力を完全に無効化（JSON出力に影響しないように）
error_reporting(0);
ini_set('display_errors', 0);

// JSON Content-Type ヘッダーを最初に設定
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

try {
    // クラスファイルの読み込み（エラー出力を抑制）
    $rootDir = dirname(__DIR__);
    
    @require_once $rootDir . '/classes/Database.php';
    @require_once $rootDir . '/classes/SecurityHelper.php';
    
    // セキュリティヘッダー設定（出力バッファリング前に実行）
    if (class_exists('SecurityHelper')) {
        SecurityHelper::setSecurityHeaders();
    }
    
    // 出力バッファリング開始（予期しない出力をキャッチ）
    ob_start();
    
    // ★★★ 重要: Singletonパターンでデータベース接続 ★★★
    $db = Database::getInstance();
    
    // HTTPメソッドチェック
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('GET メソッドのみサポートしています');
    }
    
    // パラメータ取得
    $invoiceType = $_GET['invoice_type'] ?? 'company_bulk';
    $searchTerm = $_GET['search'] ?? '';
    
    $result = [];
    
    switch ($invoiceType) {
        case 'company_bulk':
            // 企業一括請求の対象企業一覧
            $sql = "SELECT 
                        c.id,
                        c.company_code,
                        c.company_name,
                        COUNT(DISTINCT u.id) as user_count,
                        COALESCE(SUM(o.total_amount), 0) as total_amount,
                        COUNT(DISTINCT o.id) as order_count,
                        MAX(o.delivery_date) as last_order_date
                    FROM companies c
                    LEFT JOIN users u ON c.id = u.company_id AND u.is_active = 1
                    LEFT JOIN orders o ON u.id = o.user_id AND o.delivery_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
                    WHERE c.is_active = 1";
            
            $params = [];
            
            if (!empty($searchTerm)) {
                $sql .= " AND (c.company_name LIKE ? OR c.company_code LIKE ?)";
                $params[] = "%{$searchTerm}%";
                $params[] = "%{$searchTerm}%";
            }
            
            $sql .= " GROUP BY c.id, c.company_code, c.company_name
                     ORDER BY c.company_name";
            
            $stmt = $db->query($sql, $params);
            $companies = $stmt->fetchAll();
            
            $result = [
                'success' => true,
                'data' => [
                    'targets' => array_map(function($company) {
                        return [
                            'id' => (int)$company['id'],
                            'type' => 'company',
                            'code' => $company['company_code'],
                            'name' => $company['company_name'],
                            'description' => "利用者: {$company['user_count']}名 | 注文: {$company['order_count']}件",
                            'user_count' => (int)$company['user_count'],
                            'order_count' => (int)$company['order_count'],
                            'total_amount' => (float)$company['total_amount'],
                            'last_order_date' => $company['last_order_date']
                        ];
                    }, $companies),
                    'total_count' => count($companies),
                    'invoice_type' => 'company_bulk'
                ]
            ];
            break;
            
        case 'department_bulk':
            // 部署別一括請求の対象部署一覧
            $sql = "SELECT 
                        d.id,
                        d.department_code,
                        d.department_name,
                        c.company_name,
                        COUNT(DISTINCT u.id) as user_count,
                        COALESCE(SUM(o.total_amount), 0) as total_amount,
                        COUNT(DISTINCT o.id) as order_count
                    FROM departments d
                    INNER JOIN companies c ON d.company_id = c.id
                    LEFT JOIN users u ON d.id = u.department_id AND u.is_active = 1
                    LEFT JOIN orders o ON u.id = o.user_id AND o.delivery_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
                    WHERE d.is_active = 1 AND c.is_active = 1";
            
            $params = [];
            
            if (!empty($searchTerm)) {
                $sql .= " AND (d.department_name LIKE ? OR d.department_code LIKE ? OR c.company_name LIKE ?)";
                $params[] = "%{$searchTerm}%";
                $params[] = "%{$searchTerm}%";
                $params[] = "%{$searchTerm}%";
            }
            
            $sql .= " GROUP BY d.id, d.department_code, d.department_name, c.company_name
                     ORDER BY c.company_name, d.department_name";
            
            $stmt = $db->query($sql, $params);
            $departments = $stmt->fetchAll();
            
            $result = [
                'success' => true,
                'data' => [
                    'targets' => array_map(function($dept) {
                        return [
                            'id' => (int)$dept['id'],
                            'type' => 'department',
                            'code' => $dept['department_code'],
                            'name' => $dept['department_name'],
                            'description' => "{$dept['company_name']} | 利用者: {$dept['user_count']}名 | 注文: {$dept['order_count']}件",
                            'company_name' => $dept['company_name'],
                            'user_count' => (int)$dept['user_count'],
                            'order_count' => (int)$dept['order_count'],
                            'total_amount' => (float)$dept['total_amount']
                        ];
                    }, $departments),
                    'total_count' => count($departments),
                    'invoice_type' => 'department_bulk'
                ]
            ];
            break;
            
        case 'individual':
            // 個人請求の対象利用者一覧
            $sql = "SELECT 
                        u.id,
                        u.user_code,
                        u.user_name,
                        u.email,
                        c.company_name,
                        d.department_name,
                        COALESCE(SUM(o.total_amount), 0) as total_amount,
                        COUNT(o.id) as order_count,
                        MAX(o.delivery_date) as last_order_date
                    FROM users u
                    INNER JOIN companies c ON u.company_id = c.id
                    LEFT JOIN departments d ON u.department_id = d.id
                    LEFT JOIN orders o ON u.id = o.user_id AND o.delivery_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
                    WHERE u.is_active = 1 AND c.is_active = 1";
            
            $params = [];
            
            if (!empty($searchTerm)) {
                $sql .= " AND (u.user_name LIKE ? OR u.user_code LIKE ? OR u.email LIKE ? OR c.company_name LIKE ?)";
                $params[] = "%{$searchTerm}%";
                $params[] = "%{$searchTerm}%";
                $params[] = "%{$searchTerm}%";
                $params[] = "%{$searchTerm}%";
            }
            
            $sql .= " GROUP BY u.id, u.user_code, u.user_name, u.email, c.company_name, d.department_name
                     HAVING total_amount > 0
                     ORDER BY c.company_name, u.user_name";
            
            $stmt = $db->query($sql, $params);
            $users = $stmt->fetchAll();
            
            $result = [
                'success' => true,
                'data' => [
                    'targets' => array_map(function($user) {
                        $description = $user['company_name'];
                        if (!empty($user['department_name'])) {
                            $description .= " - " . $user['department_name'];
                        }
                        $description .= " | 注文: {$user['order_count']}件 | 金額: ¥" . number_format($user['total_amount']);
                        
                        return [
                            'id' => (int)$user['id'],
                            'type' => 'user',
                            'code' => $user['user_code'],
                            'name' => $user['user_name'],
                            'description' => $description,
                            'email' => $user['email'],
                            'company_name' => $user['company_name'],
                            'department_name' => $user['department_name'],
                            'order_count' => (int)$user['order_count'],
                            'total_amount' => (float)$user['total_amount'],
                            'last_order_date' => $user['last_order_date']
                        ];
                    }, $users),
                    'total_count' => count($users),
                    'invoice_type' => 'individual'
                ]
            ];
            break;
            
        case 'mixed':
            // 混合請求（企業設定に基づく自動判定）
            $sql = "SELECT 
                        c.id,
                        c.company_code,
                        c.company_name,
                        c.invoice_type as preferred_type,
                        COUNT(DISTINCT u.id) as user_count,
                        COUNT(DISTINCT d.id) as department_count,
                        COALESCE(SUM(o.total_amount), 0) as total_amount,
                        COUNT(DISTINCT o.id) as order_count
                    FROM companies c
                    LEFT JOIN users u ON c.id = u.company_id AND u.is_active = 1
                    LEFT JOIN departments d ON c.id = d.company_id AND d.is_active = 1
                    LEFT JOIN orders o ON u.id = o.user_id AND o.delivery_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
                    WHERE c.is_active = 1";
            
            $params = [];
            
            if (!empty($searchTerm)) {
                $sql .= " AND (c.company_name LIKE ? OR c.company_code LIKE ?)";
                $params[] = "%{$searchTerm}%";
                $params[] = "%{$searchTerm}%";
            }
            
            $sql .= " GROUP BY c.id, c.company_code, c.company_name, c.invoice_type
                     HAVING total_amount > 0
                     ORDER BY c.company_name";
            
            $stmt = $db->query($sql, $params);
            $companies = $stmt->fetchAll();
            
            $result = [
                'success' => true,
                'data' => [
                    'targets' => array_map(function($company) {
                        $typeDisplay = [
                            'company' => '企業一括',
                            'department' => '部署別',
                            'individual' => '個人別',
                            'mixed' => '混合'
                        ];
                        
                        $preferredType = $company['preferred_type'] ?? 'company';
                        
                        return [
                            'id' => (int)$company['id'],
                            'type' => 'mixed',
                            'code' => $company['company_code'],
                            'name' => $company['company_name'],
                            'description' => "請求方式: {$typeDisplay[$preferredType]} | 利用者: {$company['user_count']}名 | 部署: {$company['department_count']}個",
                            'preferred_type' => $preferredType,
                            'user_count' => (int)$company['user_count'],
                            'department_count' => (int)$company['department_count'],
                            'order_count' => (int)$company['order_count'],
                            'total_amount' => (float)$company['total_amount']
                        ];
                    }, $companies),
                    'total_count' => count($companies),
                    'invoice_type' => 'mixed'
                ]
            ];
            break;
            
        default:
            throw new Exception('サポートされていない請求書タイプです: ' . $invoiceType);
    }
    
    // 不要な出力をクリア
    ob_clean();
    
    // JSON出力
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    // エラー時もJSONで応答
    ob_clean();
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => [
            'message' => $e->getMessage(),
            'code' => $e->getCode()
        ],
        'data' => [
            'targets' => [],
            'total_count' => 0
        ]
    ], JSON_UNESCAPED_UNICODE);
} finally {
    // 出力バッファリング終了
    if (ob_get_level()) {
        ob_end_flush();
    }
}
?>
