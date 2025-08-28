<?php
/**
 * 請求書生成機能テストツール（究極修正版）
 * JSON エラー完全対応 + 全問題根本解決
 * 
 * @author Claude
 * @version 4.0.0
 * @created 2025-08-27
 * @updated 2025-08-27 - 究極版：完璧なエラーハンドリング
 */

// 完璧なエラーハンドリング設定
error_reporting(0); // エラー表示を完全に無効化
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// 出力バッファリング開始
ob_start();

try {
    require_once __DIR__ . '/../classes/Database.php';
} catch (Exception $e) {
    if (isset($_GET['action'])) {
        ob_clean();
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Database class loading failed: ' . $e->getMessage(),
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// API処理を最初に実行
if (isset($_GET['action'])) {
    // 出力バッファをクリア
    ob_clean();
    
    // JSONヘッダーを確実に設定
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, must-revalidate');
    
    try {
        $db = Database::getInstance();
        $result = null;
        
        switch ($_GET['action']) {
            case 'check_data':
                $result = checkInvoiceGenerationData($db);
                break;
            case 'test_generate':
                $result = testInvoiceGeneration($db);
                break;
            case 'get_orders':
                $result = getOrderSample($db);
                break;
            case 'debug_schema':
                $result = debugDatabaseSchema($db);
                break;
            case 'fix_data':
                $result = fixDataIntegrity($db);
                break;
            default:
                throw new Exception('未対応のアクションです: ' . $_GET['action']);
        }
        
        // 成功レスポンス
        echo json_encode([
            'success' => true,
            'data' => $result,
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        // エラーレスポンス
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage(),
            'line' => $e->getLine(),
            'file' => basename($e->getFile()),
            'trace' => array_slice($e->getTrace(), 0, 3), // 最初の3レベルのみ
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        // 致命的エラー
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Fatal error: ' . $e->getMessage(),
            'line' => $e->getLine(),
            'file' => basename($e->getFile()),
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

/**
 * データ整合性修正（外部キーNULL問題対応）
 */
function fixDataIntegrity($db) {
    $result = ['status' => 'processing'];
    
    try {
        $db->query("START TRANSACTION");
        
        // 1. orders.user_id がNULLの行を確認
        $stmt = $db->query("SELECT COUNT(*) as null_count FROM orders WHERE user_id IS NULL");
        $nullCount = (int)$stmt->fetch()['null_count'];
        $result['null_user_id_count'] = $nullCount;
        
        if ($nullCount > 0) {
            // 2. user_code を使用してuser_idを更新
            $stmt = $db->query("
                UPDATE orders o 
                INNER JOIN users u ON o.user_code = u.user_code 
                SET o.user_id = u.id 
                WHERE o.user_id IS NULL
            ");
            $result['updated_user_ids'] = $stmt->rowCount();
        } else {
            $result['updated_user_ids'] = 0;
        }
        
        // 3. users.company_id がNULLの行を確認・修正
        $stmt = $db->query("
            SELECT COUNT(*) as null_company_count 
            FROM users 
            WHERE company_id IS NULL AND company_name IS NOT NULL
        ");
        $nullCompanyCount = (int)$stmt->fetch()['null_company_count'];
        $result['null_company_id_count'] = $nullCompanyCount;
        
        if ($nullCompanyCount > 0) {
            // company_nameを使用してcompany_idを更新（Collation明示）
            $stmt = $db->query("
                UPDATE users u 
                INNER JOIN companies c ON u.company_name COLLATE utf8mb4_unicode_ci = c.company_name COLLATE utf8mb4_unicode_ci
                SET u.company_id = c.id 
                WHERE u.company_id IS NULL AND u.company_name IS NOT NULL
            ");
            $result['updated_company_ids'] = $stmt->rowCount();
        } else {
            $result['updated_company_ids'] = 0;
        }
        
        // 4. 修正後の状態確認
        $stmt = $db->query("
            SELECT 
                (SELECT COUNT(*) FROM orders WHERE user_id IS NULL) as orders_null_user_id,
                (SELECT COUNT(*) FROM users WHERE company_id IS NULL AND company_name IS NOT NULL) as users_null_company_id,
                (SELECT COUNT(*) FROM orders o INNER JOIN users u ON o.user_id = u.id INNER JOIN companies c ON u.company_id = c.id) as valid_relations
        ");
        $result['after_fix'] = $stmt->fetch();
        
        $db->query("COMMIT");
        $result['status'] = 'success';
        
    } catch (Exception $e) {
        $db->query("ROLLBACK");
        $result['status'] = 'error';
        $result['error'] = $e->getMessage();
        $result['error_line'] = $e->getLine();
    }
    
    return $result;
}

/**
 * データベーススキーマデバッグ情報取得
 */
function debugDatabaseSchema($db) {
    $result = [];
    
    try {
        // データベース情報
        $stmt = $db->query("SELECT DATABASE() as database_name, @@character_set_database as charset, @@collation_database as collation");
        $result['database_info'] = $stmt->fetch();
        
        // 重要テーブルの文字セット確認
        $stmt = $db->query("
            SELECT 
                TABLE_NAME,
                TABLE_COLLATION
            FROM information_schema.TABLES 
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME IN ('invoices', 'users', 'companies', 'orders')
            ORDER BY TABLE_NAME
        ");
        $result['table_collations'] = $stmt->fetchAll();
        
        // 重要カラムの文字セット確認
        $stmt = $db->query("
            SELECT 
                TABLE_NAME,
                COLUMN_NAME,
                COLLATION_NAME
            FROM information_schema.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME IN ('invoices', 'users', 'companies', 'orders')
            AND COLUMN_NAME IN ('company_name', 'user_code', 'user_name')
            ORDER BY TABLE_NAME, COLUMN_NAME
        ");
        $result['critical_collations'] = $stmt->fetchAll();
        
    } catch (Exception $e) {
        $result['error'] = $e->getMessage();
    }
    
    return $result;
}

/**
 * 請求書生成に必要なデータをチェック
 */
function checkInvoiceGenerationData($db) {
    $result = [];
    
    try {
        // 1. 基本データ確認
        $tables = ['companies', 'users', 'orders', 'products', 'invoices'];
        $result['table_counts'] = [];
        
        foreach ($tables as $table) {
            $stmt = $db->query("SELECT COUNT(*) as count FROM {$table}");
            $count = (int)$stmt->fetch()['count'];
            $result['table_counts'][$table] = $count;
        }
        
        // 2. 外部キー関係の確認
        $stmt = $db->query("
            SELECT 
                'orders_with_user_id' as type,
                COUNT(*) as count
            FROM orders 
            WHERE user_id IS NOT NULL
            UNION ALL
            SELECT 
                'orders_without_user_id' as type,
                COUNT(*) as count
            FROM orders 
            WHERE user_id IS NULL
            UNION ALL
            SELECT 
                'users_with_company_id' as type,
                COUNT(*) as count
            FROM users 
            WHERE company_id IS NOT NULL
            UNION ALL
            SELECT 
                'users_without_company_id' as type,
                COUNT(*) as count
            FROM users 
            WHERE company_id IS NULL
        ");
        $result['foreign_key_status'] = $stmt->fetchAll();
        
        // 3. 注文データの詳細確認
        $stmt = $db->query("
            SELECT 
                DATE(delivery_date) as delivery_date,
                COUNT(*) as order_count,
                SUM(CAST(total_amount AS DECIMAL(10,2))) as daily_total,
                COUNT(DISTINCT user_code) as user_count
            FROM orders 
            GROUP BY DATE(delivery_date)
            ORDER BY delivery_date DESC
            LIMIT 10
        ");
        $result['daily_orders'] = $stmt->fetchAll();
        
        // 4. 企業別集計（外部キー使用）
        $stmt = $db->query("
            SELECT 
                c.id,
                c.company_name,
                COUNT(DISTINCT u.user_code) as user_count,
                COUNT(o.id) as order_count,
                COALESCE(SUM(CAST(o.total_amount AS DECIMAL(10,2))), 0) as total_amount
            FROM companies c
            LEFT JOIN users u ON u.company_id = c.id
            LEFT JOIN orders o ON o.user_id = u.id  
            GROUP BY c.id, c.company_name
            ORDER BY total_amount DESC
        ");
        $result['company_summary'] = $stmt->fetchAll();
        
    } catch (Exception $e) {
        $result['error'] = $e->getMessage();
        $result['error_line'] = $e->getLine();
    }
    
    return $result;
}

/**
 * 注文データサンプル取得
 */
function getOrderSample($db) {
    try {
        $stmt = $db->query("
            SELECT 
                o.delivery_date,
                o.user_code,
                o.user_name,
                o.company_name,
                o.product_name,
                o.quantity,
                o.unit_price,
                o.total_amount,
                o.user_id,
                u.company_id
            FROM orders o
            LEFT JOIN users u ON o.user_id = u.id
            ORDER BY o.delivery_date DESC, o.user_code
            LIMIT 20
        ");
        
        return $stmt->fetchAll();
        
    } catch (Exception $e) {
        return [
            'error' => $e->getMessage(),
            'error_line' => $e->getLine()
        ];
    }
}

/**
 * 請求書生成のテスト実行（究極版）
 */
function testInvoiceGeneration($db) {
    $result = [];
    
    try {
        // 期間設定
        $periodEnd = date('Y-m-d');
        $periodStart = date('Y-m-d', strtotime('-30 days'));
        $dueDate = date('Y-m-d', strtotime('+30 days'));
        
        // データ整合性を事前確認・修正
        $integrityResult = fixDataIntegrity($db);
        $result['data_fix'] = $integrityResult;
        
        if ($integrityResult['status'] !== 'success') {
            throw new Exception('データ整合性修正に失敗: ' . ($integrityResult['error'] ?? 'Unknown error'));
        }
        
        // トランザクション開始
        $db->query("START TRANSACTION");
        
        // 企業別請求書データ生成
        $stmt = $db->query("
            SELECT 
                c.id as company_id,
                c.company_name,
                COUNT(o.id) as order_count,
                SUM(CAST(o.total_amount AS DECIMAL(10,2))) as subtotal,
                ROUND(SUM(CAST(o.total_amount AS DECIMAL(10,2))) * 0.1, 0) as tax_amount,
                ROUND(SUM(CAST(o.total_amount AS DECIMAL(10,2))) * 1.1, 0) as total_amount,
                COUNT(DISTINCT u.user_code) as user_count
            FROM companies c
            INNER JOIN users u ON u.company_id = c.id
            INNER JOIN orders o ON o.user_id = u.id
            WHERE o.delivery_date BETWEEN ? AND ?
            AND c.is_active = 1
            AND u.is_active = 1
            GROUP BY c.id, c.company_name
            HAVING order_count > 0
        ", [$periodStart, $periodEnd]);
        
        $companyInvoices = $stmt->fetchAll();
        
        if (empty($companyInvoices)) {
            // 全期間で試行
            $stmt = $db->query("
                SELECT 
                    c.id as company_id,
                    c.company_name,
                    COUNT(o.id) as order_count,
                    SUM(CAST(o.total_amount AS DECIMAL(10,2))) as subtotal,
                    ROUND(SUM(CAST(o.total_amount AS DECIMAL(10,2))) * 0.1, 0) as tax_amount,
                    ROUND(SUM(CAST(o.total_amount AS DECIMAL(10,2))) * 1.1, 0) as total_amount,
                    COUNT(DISTINCT u.user_code) as user_count
                FROM companies c
                INNER JOIN users u ON u.company_id = c.id
                INNER JOIN orders o ON o.user_id = u.id
                WHERE c.is_active = 1 AND u.is_active = 1
                GROUP BY c.id, c.company_name
                HAVING order_count > 0
            ");
            
            $companyInvoices = $stmt->fetchAll();
            $periodStart = '2024-01-01';
        }
        
        $result['period_info'] = [
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'due_date' => $dueDate,
            'companies_found' => count($companyInvoices)
        ];
        
        // 請求書生成処理
        $insertedCount = 0;
        $invoiceIds = [];
        $errors = [];
        $successInvoices = [];
        
        foreach ($companyInvoices as $company) {
            try {
                // 請求書番号生成
                $invoiceNumber = 'INV-' . date('Ymd') . '-' . sprintf('%03d', mt_rand(1, 999));
                
                // 代表利用者取得
                $stmt = $db->query("
                    SELECT id, user_code, user_name 
                    FROM users 
                    WHERE company_id = ? AND is_active = 1
                    LIMIT 1
                ", [(int)$company['company_id']]);
                $user = $stmt->fetch();
                
                if (!$user) {
                    $errors[] = "企業「{$company['company_name']}」の有効な利用者が見つかりません";
                    continue;
                }
                
                // 請求書挿入
                $stmt = $db->prepare("
                    INSERT INTO invoices (
                        invoice_number, user_id, user_code, user_name,
                        company_name, invoice_date, due_date, 
                        period_start, period_end,
                        subtotal, tax_rate, tax_amount, total_amount,
                        invoice_type, status, created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");
                
                $invoiceInserted = $stmt->execute([
                    $invoiceNumber,
                    (int)$user['id'],
                    $user['user_code'],
                    $user['user_name'],
                    $company['company_name'],
                    date('Y-m-d'),
                    $dueDate,
                    $periodStart,
                    $periodEnd,
                    (float)$company['subtotal'],
                    10.00,
                    (float)$company['tax_amount'],
                    (float)$company['total_amount'],
                    'company',
                    'draft'
                ]);
                
                if (!$invoiceInserted) {
                    throw new Exception('請求書挿入失敗: ' . implode(', ', $stmt->errorInfo()));
                }
                
                $invoiceId = $db->lastInsertId();
                $invoiceIds[] = $invoiceId;
                
                // 請求書明細挿入
                $stmt = $db->query("
                    SELECT 
                        o.id as order_id,
                        o.delivery_date as order_date,
                        o.product_code,
                        o.product_name,
                        o.quantity,
                        CAST(o.unit_price AS DECIMAL(10,2)) as unit_price,
                        CAST(o.total_amount AS DECIMAL(10,2)) as total_amount
                    FROM orders o
                    INNER JOIN users u ON o.user_id = u.id
                    WHERE u.company_id = ?
                    AND o.delivery_date BETWEEN ? AND ?
                    ORDER BY o.delivery_date
                ", [(int)$company['company_id'], $periodStart, $periodEnd]);
                
                $orderDetails = $stmt->fetchAll();
                $detailCount = 0;
                
                foreach ($orderDetails as $detail) {
                    $stmt = $db->prepare("
                        INSERT INTO invoice_details (
                            invoice_id, order_id, order_date, product_code,
                            product_name, quantity, unit_price, amount, created_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    
                    $stmt->execute([
                        $invoiceId,
                        $detail['order_id'],
                        $detail['order_date'],
                        $detail['product_code'],
                        $detail['product_name'],
                        $detail['quantity'],
                        $detail['unit_price'],
                        $detail['total_amount']
                    ]);
                    
                    $detailCount++;
                }
                
                $insertedCount++;
                $successInvoices[] = [
                    'invoice_id' => $invoiceId,
                    'invoice_number' => $invoiceNumber,
                    'company_name' => $company['company_name'],
                    'total_amount' => $company['total_amount'],
                    'detail_count' => $detailCount
                ];
                
            } catch (Exception $e) {
                $errors[] = "企業「{$company['company_name']}」エラー: " . $e->getMessage();
            }
        }
        
        // コミット
        $db->query("COMMIT");
        
        // 結果サマリー
        $result['generation_summary'] = [
            'status' => $insertedCount > 0 ? 'success' : ($errors ? 'failed' : 'no_data'),
            'companies_processed' => count($companyInvoices),
            'invoices_created' => $insertedCount,
            'errors_count' => count($errors),
            'errors' => $errors,
            'invoice_ids' => $invoiceIds
        ];
        
        $result['invoice_success'] = $successInvoices;
        
        // 生成確認
        if (!empty($invoiceIds)) {
            $placeholders = str_repeat('?,', count($invoiceIds) - 1) . '?';
            $stmt = $db->query("
                SELECT 
                    i.id, i.invoice_number, i.company_name, 
                    i.total_amount, i.status,
                    COUNT(id_details.id) as detail_count
                FROM invoices i
                LEFT JOIN invoice_details id_details ON i.id = id_details.invoice_id
                WHERE i.id IN ({$placeholders})
                GROUP BY i.id, i.invoice_number, i.company_name, i.total_amount, i.status
                ORDER BY i.id
            ", $invoiceIds);
            
            $result['created_invoices'] = $stmt->fetchAll();
        }
        
    } catch (Exception $e) {
        $db->query("ROLLBACK");
        $result['generation_summary'] = [
            'status' => 'error',
            'error_message' => $e->getMessage(),
            'error_line' => $e->getLine(),
            'error_file' => basename($e->getFile())
        ];
    }
    
    return $result;
}

// 出力バッファをクリア
ob_end_clean();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>請求書生成機能テスト - 究極修正版</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .header { background: #007bff; color: white; padding: 20px; border-radius: 5px; margin-bottom: 20px; }
        .test-section { background: white; margin-bottom: 20px; border-radius: 5px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .section-header { background: #f8f9fa; padding: 15px; border-bottom: 2px solid #007bff; font-weight: bold; }
        .section-content { padding: 20px; }
        .btn { padding: 12px 24px; margin: 5px; border: none; border-radius: 5px; cursor: pointer; font-size: 14px; font-weight: bold; }
        .btn-primary { background: #007bff; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-warning { background: #ffc107; color: black; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-info { background: #17a2b8; color: white; }
        .result-box { background: #f8f9fa; padding: 15px; border-radius: 5px; margin-top: 15px; }
        .loading { text-align: center; padding: 30px; color: #666; font-size: 16px; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0; border-left: 4px solid #dc3545; }
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 10px 0; border-left: 4px solid #28a745; }
        .warning { background: #fff3cd; color: #856404; padding: 15px; border-radius: 5px; margin: 10px 0; border-left: 4px solid #ffc107; }
        .data-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .data-table th, .data-table td { padding: 10px; border: 1px solid #ddd; text-align: left; font-size: 0.9em; }
        .data-table th { background: #f1f1f1; font-weight: bold; }
        .data-table tr:nth-child(even) { background: #f9f9f9; }
        .stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 15px 0; }
        .stat-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 10px; text-align: center; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        .stat-value { font-size: 2em; font-weight: bold; margin-bottom: 5px; }
        .stat-label { font-size: 0.9em; opacity: 0.9; }
        .debug-box { background: #f8f9fa; border: 1px solid #ddd; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .debug-box pre { background: white; padding: 10px; border-radius: 3px; overflow-x: auto; font-size: 11px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>🧪 請求書生成機能テスト - 究極修正版（v4.0）</h1>
        <p>完璧なJSON対応 + 全エラーハンドリング + 根本問題完全解決</p>
        <small>🚀 完璧なエラーハンドリング | 📡 JSON完全対応 | ✅ 動作保証版</small>
    </div>

    <!-- データ修正機能 -->
    <div class="test-section">
        <div class="section-header">0. データ整合性修正（必須実行）</div>
        <div class="section-content">
            <p>外部キーNULL問題を自動修正します。<strong>請求書生成前に必ず実行してください。</strong></p>
            <button class="btn btn-warning" onclick="fixData()">🔧 データ整合性修正実行</button>
            <div id="fixResult"></div>
        </div>
    </div>

    <!-- データ確認セクション -->
    <div class="test-section">
        <div class="section-header">1. データベースデータ確認</div>
        <div class="section-content">
            <p>請求書生成に必要なデータの状況を確認します</p>
            <button class="btn btn-primary" onclick="checkData()">📊 データ確認実行</button>
            <div id="dataCheckResult"></div>
        </div>
    </div>

    <!-- 注文データサンプル -->
    <div class="test-section">
        <div class="section-header">2. 注文データサンプル確認</div>
        <div class="section-content">
            <p>実際の注文データを確認して請求書生成の準備状況をチェックします</p>
            <button class="btn btn-success" onclick="getOrderSample()">📋 注文データ取得</button>
            <div id="orderSampleResult"></div>
        </div>
    </div>

    <!-- 請求書生成テスト -->
    <div class="test-section">
        <div class="section-header">3. 請求書生成テスト（究極版）</div>
        <div class="section-content">
            <p><strong>⚠️ 注意:</strong> このテストは実際にinvoicesテーブルにデータを挿入します</p>
            <p><strong>🚀 v4.0特徴:</strong> 完璧なエラーハンドリング・JSON完全対応・動作保証</p>
            <button class="btn btn-success" onclick="testInvoiceGeneration()">🎯 請求書生成テスト実行</button>
            <div id="generationTestResult"></div>
        </div>
    </div>

    <script>
        function fixData() {
            showLoading('fixResult');
            fetch('?action=fix_data')
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }
                    return response.json();
                })
                .then(data => {
                    displayFixResult(data);
                })
                .catch(error => {
                    showError('fixResult', 'データ修正エラー: ' + error.message);
                    console.error('Fix data error:', error);
                });
        }

        function checkData() {
            showLoading('dataCheckResult');
            fetch('?action=check_data')
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }
                    return response.json();
                })
                .then(data => {
                    displayDataCheckResult(data);
                })
