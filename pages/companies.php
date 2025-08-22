<?php
/**
 * 企業管理API（根本修正版）
 * SQLパラメータ数不一致エラーの完全解決
 * 
 * 根本修正内容:
 * 1. WHERE句構築とパラメータバインディングの完全分離
 * 2. 動的SQL生成の安全な実装
 * 3. デバッグ情報の追加
 * 4. エラーハンドリングの強化
 */

require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/SecurityHelper.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// セキュリティヘッダー設定
SecurityHelper::setSecurityHeaders();

// OPTIONS リクエスト対応
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    // Database::getInstance() を使用
    $db = Database::getInstance();
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        
        $action = $_GET['action'] ?? 'list';
        
        switch ($action) {
            case 'list':
                // 企業一覧取得（根本修正版：WHERE句とパラメータの完全対応）
                $isActive = $_GET['is_active'] ?? null;
                $page = max(1, intval($_GET['page'] ?? 1));
                $limit = min(100, max(10, intval($_GET['limit'] ?? 50)));
                $offset = ($page - 1) * $limit;
                
                // WHERE句とパラメータを段階的に構築
                $whereParts = [];
                $params = [];
                
                // アクティブ状態フィルター
                if ($isActive !== null) {
                    $whereParts[] = "c.is_active = ?";
                    $params[] = $isActive ? 1 : 0;
                } else {
                    // デフォルトはアクティブのみ
                    $whereParts[] = "c.is_active = ?";
                    $params[] = 1;
                }
                
                // WHERE句構築
                $whereClause = !empty($whereParts) ? 'WHERE ' . implode(' AND ', $whereParts) : '';
                
                // メインクエリ実行
                $sql = "
                    SELECT 
                        c.id,
                        c.company_code,
                        c.company_name,
                        c.address_detail,
                        c.contact_person,
                        c.phone_number,
                        c.email_address,
                        c.billing_method,
                        c.is_active,
                        c.created_at,
                        c.updated_at,
                        COUNT(DISTINCT d.id) as department_count,
                        COUNT(DISTINCT u.id) as user_count,
                        COUNT(DISTINCT o.id) as order_count,
                        COALESCE(SUM(o.total_amount), 0) as total_revenue,
                        MAX(o.delivery_date) as last_order_date,
                        COUNT(DISTINCT CASE WHEN o.delivery_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN o.id END) as recent_orders,
                        COALESCE(SUM(CASE WHEN o.delivery_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN o.total_amount ELSE 0 END), 0) as recent_revenue
                    FROM companies c
                    LEFT JOIN departments d ON c.id = d.company_id AND d.is_active = 1
                    LEFT JOIN users u ON c.id = u.company_id AND u.is_active = 1
                    LEFT JOIN orders o ON u.user_code = o.user_code AND o.delivery_date >= DATE_SUB(CURDATE(), INTERVAL 180 DAY)
                    {$whereClause}
                    GROUP BY c.id, c.company_code, c.company_name, c.address_detail, c.contact_person, c.phone_number, c.email_address, c.billing_method, c.is_active, c.created_at, c.updated_at
                    ORDER BY c.company_name ASC
                    LIMIT ? OFFSET ?
                ";
                
                // LIMITとOFFSETパラメータを追加
                $queryParams = array_merge($params, [$limit, $offset]);
                
                $stmt = $db->query($sql, $queryParams);
                $companies = $stmt->fetchAll();
                
                // 総件数取得（同じWHERE条件を使用）
                $countSql = "SELECT COUNT(*) as total FROM companies c {$whereClause}";
                $countStmt = $db->query($countSql, $params);
                $totalCount = $countStmt->fetch()['total'];
                
                echo json_encode([
                    'success' => true,
                    'data' => $companies,
                    'pagination' => [
                        'current_page' => $page,
                        'per_page' => $limit,
                        'total' => $totalCount,
                        'total_pages' => ceil($totalCount / $limit)
                    ],
                    'debug' => [
                        'where_clause' => $whereClause,
                        'params_count' => count($params),
                        'query_params_count' => count($queryParams)
                    ]
                ], JSON_UNESCAPED_UNICODE);
                break;
                
            case 'detail':
                // 企業詳細取得
                $companyId = $_GET['id'] ?? null;
                if (!$companyId) {
                    throw new Exception('企業IDが指定されていません');
                }
                
                // 基本情報取得
                $sql = "
                    SELECT 
                        c.*,
                        COUNT(DISTINCT d.id) as department_count,
                        COUNT(DISTINCT u.id) as user_count
                    FROM companies c
                    LEFT JOIN departments d ON c.id = d.company_id AND d.is_active = 1
                    LEFT JOIN users u ON c.id = u.company_id AND u.is_active = 1
                    WHERE c.id = ?
                    GROUP BY c.id, c.company_code, c.company_name, c.address_detail, c.contact_person, c.phone_number, c.email_address, c.billing_method, c.is_active, c.created_at, c.updated_at
                ";
                
                $stmt = $db->query($sql, [$companyId]);
                $company = $stmt->fetch();
                
                if (!$company) {
                    throw new Exception('企業が見つかりません');
                }
                
                // 注文統計取得
                $orderStatsSql = "
                    SELECT 
                        COUNT(DISTINCT o.id) as total_orders,
                        COALESCE(SUM(o.total_amount), 0) as total_revenue,
                        MAX(o.delivery_date) as last_order_date,
                        MIN(o.delivery_date) as first_order_date,
                        COALESCE(AVG(o.total_amount), 0) as avg_order_amount
                    FROM orders o
                    INNER JOIN users u ON o.user_code = u.user_code
                    WHERE u.company_id = ?
                ";
                
                $orderStatsStmt = $db->query($orderStatsSql, [$companyId]);
                $orderStats = $orderStatsStmt->fetch();
                
                // 部署情報取得
                $departmentsSql = "
                    SELECT 
                        d.id,
                        d.department_code,
                        d.department_name,
                        d.contact_person,
                        d.is_active,
                        COUNT(DISTINCT u.id) as user_count,
                        COUNT(DISTINCT o.id) as order_count,
                        COALESCE(SUM(o.total_amount), 0) as total_revenue
                    FROM departments d
                    LEFT JOIN users u ON d.id = u.department_id AND u.is_active = 1
                    LEFT JOIN orders o ON u.user_code = o.user_code AND o.delivery_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
                    WHERE d.company_id = ? AND d.is_active = 1
                    GROUP BY d.id, d.department_code, d.department_name, d.contact_person, d.is_active
                    ORDER BY d.department_name ASC
                ";
                
                $deptStmt = $db->query($departmentsSql, [$companyId]);
                $departments = $deptStmt->fetchAll();
                
                echo json_encode([
                    'success' => true,
                    'data' => [
                        'company' => array_merge($company, $orderStats ?: []),
                        'departments' => $departments
                    ]
                ], JSON_UNESCAPED_UNICODE);
                break;
                
            case 'stats':
                // 企業統計取得
                $sql = "
                    SELECT 
                        COUNT(*) as total_companies,
                        COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_companies,
                        COUNT(CASE WHEN is_active = 0 THEN 1 END) as inactive_companies
                    FROM companies
                ";
                
                $stmt = $db->query($sql);
                $stats = $stmt->fetch();
                
                // 部署・利用者統計
                $detailStatsSql = "
                    SELECT 
                        COUNT(DISTINCT d.id) as total_departments,
                        COUNT(DISTINCT u.id) as total_users,
                        COUNT(DISTINCT CASE WHEN u.is_active = 1 THEN u.id END) as active_users
                    FROM companies c
                    LEFT JOIN departments d ON c.id = d.company_id AND d.is_active = 1
                    LEFT JOIN users u ON c.id = u.company_id
                    WHERE c.is_active = 1
                ";
                
                $detailStatsStmt = $db->query($detailStatsSql
