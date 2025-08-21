<?php
/**
 * 利用者管理API（デバッグ機能付き）
 * Smiley配食事業 請求書・集金管理システム
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

require_once '../classes/Database.php';
require_once '../classes/SecurityHelper.php';

SecurityHelper::setSecurityHeaders();

try {
    $db = Database::getInstance();
    $method = $_SERVER['REQUEST_METHOD'];
    
    // デバッグモード確認
    $debug = $_GET['debug'] ?? null;
    
    if ($debug === 'true') {
        handleDebug($db);
        return;
    }
    
    switch ($method) {
        case 'GET':
            handleGet($db);
            break;
        case 'POST':
            handlePost($db);
            break;
        case 'PUT':
            handlePut($db);
            break;
        case 'DELETE':
            handleDelete($db);
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    }
} catch (Exception $e) {
    http_response_code(500);
    error_log("Users API Error: " . $e->getMessage());
    echo json_encode([
        'error' => 'Internal server error',
        'debug_message' => $e->getMessage(),
        'debug_file' => $e->getFile(),
        'debug_line' => $e->getLine()
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * デバッグ情報取得
 */
function handleDebug($db) {
    $debug_info = [];
    
    try {
        // 1. データベース接続確認
        $debug_info['database_connection'] = 'SUCCESS';
        
        // 2. usersテーブル構造確認
        $tableStructureSql = "DESCRIBE users";
        $tableStmt = $db->prepare($tableStructureSql);
        $tableStmt->execute();
        $debug_info['users_table_structure'] = $tableStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 3. usersテーブルのデータ数確認
        $countSql = "SELECT COUNT(*) as total FROM users";
        $countStmt = $db->prepare($countSql);
        $countStmt->execute();
        $debug_info['users_count'] = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // 4. 簡単なデータサンプル確認（最初の3件）
        $sampleSql = "SELECT * FROM users LIMIT 3";
        $sampleStmt = $db->prepare($sampleSql);
        $sampleStmt->execute();
        $debug_info['users_sample'] = $sampleStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 5. companiesテーブル確認
        $companiesSql = "SELECT COUNT(*) as total FROM companies";
        $companiesStmt = $db->prepare($companiesSql);
        $companiesStmt->execute();
        $debug_info['companies_count'] = $companiesStmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // 6. departmentsテーブル確認
        $departmentsSql = "SELECT COUNT(*) as total FROM departments";
        $departmentsStmt = $db->prepare($departmentsSql);
        $departmentsStmt->execute();
        $debug_info['departments_count'] = $departmentsStmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // 7. テーブル一覧確認
        $tablesSql = "SHOW TABLES";
        $tablesStmt = $db->prepare($tablesSql);
        $tablesStmt->execute();
        $debug_info['all_tables'] = $tablesStmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo json_encode($debug_info, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        
    } catch (Exception $e) {
        echo json_encode([
            'debug_error' => $e->getMessage(),
            'debug_file' => $e->getFile(),
            'debug_line' => $e->getLine()
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}

/**
 * GET: 利用者一覧取得・詳細取得
 */
function handleGet($db) {
    $userId = $_GET['id'] ?? null;
    
    if ($userId) {
        getUserDetail($db, $userId);
    } else {
        getUserList($db);
    }
}

/**
 * 利用者一覧取得（テーブル構造に対応）
 */
function getUserList($db) {
    try {
        $page = intval($_GET['page'] ?? 1);
        $limit = intval($_GET['limit'] ?? 20);
        $offset = ($page - 1) * $limit;
        
        $companyId = $_GET['company_id'] ?? null;
        $departmentId = $_GET['department_id'] ?? null;
        $search = $_GET['search'] ?? '';
        $status = $_GET['status'] ?? 'all';
        
        // まず実際のカラム名を確認
        $columnCheckSql = "SHOW COLUMNS FROM users";
        $columnStmt = $db->prepare($columnCheckSql);
        $columnStmt->execute();
        $columns = $columnStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $availableColumns = array_column($columns, 'Field');
        
        // 利用可能なカラムに基づいてクエリを構築
        $selectFields = ['u.*'];
        $joinTables = [];
        $whereConditions = [];
        $params = [];
        
        // deleted_atカラムがある場合のみ条件追加
        if (in_array('deleted_at', $availableColumns)) {
            $whereConditions[] = 'u.deleted_at IS NULL';
        } elseif (in_array('is_active', $availableColumns)) {
            $whereConditions[] = 'u.is_active = 1';
        }
        
        // companiesテーブルとのJOIN
        if (in_array('company_id', $availableColumns)) {
            $joinTables[] = 'LEFT JOIN companies c ON u.company_id = c.id';
            $selectFields[] = 'c.name as company_name';
        }
        
        // departmentsテーブルとのJOIN
        if (in_array('department_id', $availableColumns)) {
            $joinTables[] = 'LEFT JOIN departments d ON u.department_id = d.id';
            $selectFields[] = 'd.name as department_name';
        }
        
        // 検索条件
        if ($companyId && in_array('company_id', $availableColumns)) {
            $whereConditions[] = 'u.company_id = ?';
            $params[] = $companyId;
        }
        
        if ($departmentId && in_array('department_id', $availableColumns)) {
            $whereConditions[] = 'u.department_id = ?';
            $params[] = $departmentId;
        }
        
        if ($search) {
            $searchConditions = [];
            if (in_array('name', $availableColumns)) {
                $searchConditions[] = 'u.name LIKE ?';
                $params[] = "%{$search}%";
            }
            if (in_array('user_name', $availableColumns)) {
                $searchConditions[] = 'u.user_name LIKE ?';
                $params[] = "%{$search}%";
            }
            if (in_array('email', $availableColumns)) {
                $searchConditions[] = 'u.email LIKE ?';
                $params[] = "%{$search}%";
            }
            
            if (!empty($searchConditions)) {
                $whereConditions[] = '(' . implode(' OR ', $searchConditions) . ')';
            }
        }
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        $joinClause = implode(' ', $joinTables);
        $selectClause = implode(', ', $selectFields);
        
        // 総件数取得
        $countSql = "
            SELECT COUNT(*) as total
            FROM users u
            {$joinClause}
            {$whereClause}
        ";
        $countStmt = $db->prepare($countSql);
        $countStmt->execute($params);
        $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // 利用者一覧取得
        $sql = "
            SELECT {$selectClause}
            FROM users u
            {$joinClause}
            {$whereClause}
            ORDER BY u.created_at DESC
            LIMIT ? OFFSET ?
        ";
        
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 統計情報を簡単に計算
        $stats = [
            'total_users' => $totalCount,
            'active_users' => $totalCount, // 簡易版
            'recent_active_users' => $totalCount, // 簡易版
            'total_sales' => 0 // 簡易版
        ];
        
        echo json_encode([
            'users' => $users,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => ceil($totalCount / $limit),
                'total_count' => $totalCount,
                'per_page' => $limit
            ],
            'stats' => $stats,
            'debug_info' => [
                'available_columns' => $availableColumns,
                'query' => $sql
            ]
        ], JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'error' => 'データ取得エラー',
            'debug_message' => $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
}

/**
 * 利用者詳細取得
 */
function getUserDetail($db, $userId) {
    // 簡易版実装
    try {
        $sql = "SELECT * FROM users WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            http_response_code(404);
            echo json_encode(['error' => '利用者が見つかりません'], JSON_UNESCAPED_UNICODE);
            return;
        }
        
        echo json_encode([
            'user' => $user,
            'orders' => [], // 簡易版
            'monthly_stats' => [], // 簡易版
            'total_stats' => [
                'total_orders' => 0,
                'total_amount' => 0,
                'average_amount' => 0,
                'recent_orders' => 0
            ]
        ], JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'error' => 'データ取得エラー',
            'debug_message' => $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
}

/**
 * POST: 新規利用者作成（簡易版）
 */
function handlePost($db) {
    http_response_code(501);
    echo json_encode(['error' => 'Not implemented yet'], JSON_UNESCAPED_UNICODE);
}

/**
 * PUT: 利用者情報更新（簡易版）
 */
function handlePut($db) {
    http_response_code(501);
    echo json_encode(['error' => 'Not implemented yet'], JSON_UNESCAPED_UNICODE);
}

/**
 * DELETE: 利用者削除（簡易版）
 */
function handleDelete($db) {
    http_response_code(501);
    echo json_encode(['error' => 'Not implemented yet'], JSON_UNESCAPED_UNICODE);
}
?>
