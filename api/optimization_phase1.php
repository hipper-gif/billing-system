<?php
/**
 * API: データベース最適化 Phase 1
 * 
 * バックアップ作成と現状分析を行うAPIエンドポイント
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// プリフライトリクエスト対応
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../classes/Database.php';
require_once '../classes/SecurityHelper.php';

try {
    // セキュリティヘッダー設定
    SecurityHelper::setSecurityHeaders();
    
    // POSTメソッドのみ許可
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('POST メソッドのみ許可されています');
    }
    
    // リクエストデータ取得
    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('無効なJSONリクエストです');
    }
    
    $action = $input['action'] ?? '';
    
    // データベース接続
    $db = Database::getInstance();
    
    $response = ['success' => false, 'message' => '', 'data' => null];
    
    switch ($action) {
        case 'analyze_structure':
            $response = analyzeTableStructure($db);
            break;
            
        case 'create_backup':
            $response = createFullBackup($db);
            break;
            
        case 'check_data_volume':
            $response = checkDataVolume($db);
            break;
            
        case 'check_dependencies':
            $response = checkDependencies($db);
            break;
            
        case 'generate_plan':
            $response = generateOptimizationPlan($db);
            break;
            
        default:
            throw new Exception('無効なアクションです: ' . $action);
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    $errorResponse = [
        'success' => false,
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ];
    
    echo json_encode($errorResponse, JSON_UNESCAPED_UNICODE);
}

/**
 * テーブル構造分析
 */
function analyzeTableStructure($db) {
    $tables = ['companies', 'departments', 'users', 'orders'];
    $analysis = [];
    
    foreach ($tables as $table) {
        // カラム情報取得
        $stmt = $db->prepare("SHOW COLUMNS FROM {$table}");
        $stmt->execute();
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // データ件数取得
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM {$table}");
        $stmt->execute();
        $rowCount = $stmt->fetch()['count'];
        
        // テーブルサイズ取得
        $stmt = $db->prepare("
            SELECT ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb
            FROM information_schema.TABLES
            WHERE table_schema = DATABASE() AND table_name = ?
        ");
        $stmt->execute([$table]);
        $sizeResult = $stmt->fetch();
        $tableSize = $sizeResult ? $sizeResult['size_mb'] . 'MB' : '不明';
        
        // 不要カラム特定
        $unnecessaryColumns = identifyUnnecessaryColumns($table, $columns);
        
        $analysis[$table] = [
            'columns' => $columns,
            'column_count' => count($columns),
            'row_count' => $rowCount,
            'data_size' => $tableSize,
            'unnecessary_columns' => $unnecessaryColumns,
            'unnecessary_count' => count($unnecessaryColumns)
        ];
    }
    
    return [
        'success' => true,
        'message' => 'テーブル構造分析完了',
        'data' => $analysis
    ];
}

/**
 * 完全バックアップ作成
 */
function createFullBackup($db) {
    $backupDir = '../backups/optimization/';
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0755, true);
    }
    
    $timestamp = date('YmdHis');
    $filename = "full_backup_{$timestamp}.sql";
    $filepath = $backupDir . $filename;
    
    $tables = ['companies', 'departments', 'users', 'orders', 'suppliers', 'products', 'invoices', 'payments', 'receipts'];
    
    $sql = "-- Smiley配食システム 最適化前バックアップ\n";
    $sql .= "-- 作成日時: " . date('Y-m-d H:i:s') . "\n\n";
    $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
    
    foreach ($tables as $table) {
        // テーブル存在チェック
        $stmt = $db->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        if (!$stmt->fetch()) {
            continue; // テーブルが存在しない場合はスキップ
        }
        
        // テーブル構造
        $stmt = $db->prepare("SHOW CREATE TABLE {$table}");
        $stmt->execute();
        $createResult = $stmt->fetch();
        if ($createResult) {
            $sql .= "-- テーブル: {$table}\n";
            $sql .= "DROP TABLE IF EXISTS {$table};\n";
            $sql .= $createResult['Create Table'] . ";\n\n";
        }
        
        // データ
        $stmt = $db->prepare("SELECT * FROM {$table}");
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($rows)) {
            $columns = array_keys($rows[0]);
            $sql .= "-- {$table}テーブルデータ\n";
            $sql .= "INSERT INTO {$table} (`" . implode('`, `', $columns) . "`) VALUES\n";
            
            $values = [];
            foreach ($rows as $row) {
                $escapedValues = [];
                foreach ($row as $value) {
                    if ($value === null) {
                        $escapedValues[] = 'NULL';
                    } else {
                        $escapedValues[] = "'" . addslashes($value) . "'";
                    }
                }
                $values[] = "(" . implode(', ', $escapedValues) . ")";
            }
            
            $sql .= implode(",\n", $values) . ";\n\n";
        }
    }
    
    $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
    
    $written = file_put_contents($filepath, $sql);
    if ($written === false) {
        throw new Exception('バックアップファイルの作成に失敗しました');
    }
    
    $fileSize = filesize($filepath);
    $fileSizeFormatted = formatFileSize($fileSize);
    
    return [
        'success' => true,
        'message' => 'バックアップ作成完了',
        'data' => [
            'filename' => $filename,
            'filepath' => $filepath,
            'size' => $fileSizeFormatted,
            'tables_backed_up' => count($tables)
        ]
    ];
}

/**
 * データ量確認
 */
function checkDataVolume($db) {
    $tables = ['companies', 'departments', 'users', 'orders'];
    $volumeData = [];
    $totalRows = 0;
    
    foreach ($tables as $table) {
        // データ件数
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM {$table}");
        $stmt->execute();
        $count = $stmt->fetch()['count'];
        $totalRows += $count;
        
        // サンプルデータ
        $sampleData = null;
        if ($count > 0) {
            $stmt = $db->prepare("SELECT * FROM {$table} ORDER BY created_at DESC LIMIT 1");
            $stmt->execute();
            $sampleData = $stmt->fetch();
        }
        
        $volumeData[$table] = [
            'count' => $count,
            'sample_data' => $sampleData
        ];
    }
    
    return [
        'success' => true,
        'message' => 'データ量確認完了',
        'data' => [
            'tables' => $volumeData,
            'total_rows' => $totalRows
        ]
    ];
}

/**
 * 依存関係確認
 */
function checkDependencies($db) {
    $tables = ['companies', 'departments', 'users', 'suppliers', 'products'];
    $dependencies = [];
    
    foreach ($tables as $table) {
        $stmt = $db->prepare("
            SELECT 
                TABLE_NAME as `table`,
                COLUMN_NAME as `column`,
                REFERENCED_TABLE_NAME as `referenced_table`,
                REFERENCED_COLUMN_NAME as `referenced_column`
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE REFERENCED_TABLE_NAME = ?
            AND TABLE_SCHEMA = DATABASE()
        ");
        $stmt->execute([$table]);
        $deps = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($deps)) {
            $dependencies[$table] = $deps;
        }
    }
    
    return [
        'success' => true,
        'message' => '依存関係確認完了',
        'data' => $dependencies
    ];
}

/**
 * 最適化プラン生成
 */
function generateOptimizationPlan($db) {
    $plan = [
        'companies' => [
            'current_columns' => 45,
            'target_columns' => 17,
            'remove_columns' => [
                'postal_code', 'prefecture', 'city', 'address_detail',
                'fax', 'contact_department', 'delivery_instructions', 
                'access_instructions', 'parking_info', 'payment_method',
                'closing_date', 'billing_contact_person', 'billing_department',
                'billing_email', 'billing_postal_code', 'billing_address',
                'contract_start_date', 'contract_end_date', 'service_fee_rate',
                'volume_discount_rate', 'minimum_order_amount', 'business_type',
                'employee_count', 'daily_order_estimate', 'special_terms',
                'is_vip', 'credit_rating'
            ],
            'merge_columns' => [
                'company_address' => ['postal_code', 'prefecture', 'city', 'address_detail']
            ]
        ],
        'departments' => [
            'current_columns' => 25,
            'target_columns' => 12,
            'remove_columns' => [
                'parent_department_id', 'department_level', 'department_path',
                'manager_title', 'floor_building', 'room_number',
                'separate_billing', 'billing_contact_person', 'cost_center_code',
                'budget_code', 'employee_count', 'daily_order_average'
            ]
        ],
        'users' => [
            'current_columns' => 16,
            'target_columns' => 12,
            'remove_columns' => [
                'company_name', 'department', 'address', 'phone'
            ]
        ],
        'orders' => [
            'current_columns' => 33,
            'target_columns' => 18,
            'remove_columns' => [
                'user_name', 'company_name', 'department_name', 'product_name',
                'supplier_name', 'corporation_code', 'corporation_name',
                'employee_type_code', 'employee_type_name', 'department_code',
                'department_name', 'category_code', 'category_name', 'notes'
            ]
        ]
    ];
    
    // 実際のカラム数を取得して更新
    foreach ($plan as $table => &$tableplan) {
        $stmt = $db->prepare("SHOW COLUMNS FROM {$table}");
        $stmt->execute();
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $tableplan['current_columns'] = count($columns);
    }
    
    return [
        'success' => true,
        'message' => '最適化プラン生成完了',
        'data' => $plan
    ];
}

/**
 * 不要カラム特定
 */
function identifyUnnecessaryColumns($table, $columns) {
    $unnecessary = [];
    
    switch ($table) {
        case 'companies':
            $unnecessary = [
                'postal_code', 'prefecture', 'city', 'address_detail', 'fax',
                'is_vip', 'credit_rating', 'business_type', 'employee_count',
                'special_terms', 'contract_start_date', 'contract_end_date'
            ];
            break;
            
        case 'departments':
            $unnecessary = [
                'parent_department_id', 'department_level', 'department_path',
                'floor_building', 'room_number', 'cost_center_code', 'budget_code'
            ];
            break;
            
        case 'users':
            $unnecessary = [
                'company_name', 'department', 'address'
            ];
            break;
            
        case 'orders':
            $unnecessary = [
                'user_name', 'company_name', 'department_name', 'product_name',
                'supplier_name', 'corporation_code', 'corporation_name'
            ];
            break;
    }
    
    // 実際に存在するカラムのみフィルタリング
    $existingColumns = array_column($columns, 'Field');
    $unnecessary = array_intersect($unnecessary, $existingColumns);
    
    return array_values($unnecessary);
}

/**
 * ファイルサイズフォーマット
 */
function formatFileSize($size) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $power = $size > 0 ? floor(log($size, 1024)) : 0;
    return number_format($size / pow(1024, $power), 2, '.', ',') . ' ' . $units[$power];
}
?>
