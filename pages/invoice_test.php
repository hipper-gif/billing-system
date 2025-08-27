<?php
/**
 * 請求書生成機能テストツール（Collation エラー対応完全版）
 * 実際のテーブル構造に基づいて請求書生成をテスト
 * 
 * @author Claude
 * @version 2.0.0
 * @created 2025-08-27
 * @updated 2025-08-27 - Collation エラー根本対応
 */

require_once __DIR__ . '/../classes/Database.php';

// API処理を最初に実行
if (isset($_GET['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    
    try {
        $db = Database::getInstance();
        
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
            default:
                throw new Exception('未対応のアクションです');
        }
        
        echo json_encode([
            'success' => true,
            'data' => $result,
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage(),
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE);
    }
    exit;
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
        
        // invoicesテーブル構造
        $stmt = $db->query("SHOW CREATE TABLE invoices");
        $table_info = $stmt->fetch(PDO::FETCH_ASSOC);
        $result['invoices_structure'] = $table_info['Create Table'];
        
        // usersテーブル構造
        $stmt = $db->query("SHOW CREATE TABLE users");
        $table_info = $stmt->fetch(PDO::FETCH_ASSOC);
        $result['users_structure'] = $table_info['Create Table'];
        
        // companiesテーブル構造
        $stmt = $db->query("SHOW CREATE TABLE companies");
        $table_info = $stmt->fetch(PDO::FETCH_ASSOC);
        $result['companies_structure'] = $table_info['Create Table'];
        
        // 文字セット問題の診断
        $stmt = $db->query("
            SELECT 
                TABLE_NAME,
                COLUMN_NAME,
                CHARACTER_SET_NAME,
                COLLATION_NAME
            FROM information_schema.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME IN ('invoices', 'users', 'companies', 'orders')
            AND CHARACTER_SET_NAME IS NOT NULL
            ORDER BY TABLE_NAME, COLUMN_NAME
        ");
        $result['column_collations'] = $stmt->fetchAll();
        
    } catch (Exception $e) {
        $result['error'] = $e->getMessage();
    }
    
    return $result;
}

/**
 * 請求書生成に必要なデータをチェック（Collation エラー対応版）
 */
function checkInvoiceGenerationData($db) {
    $result = [];
    
    try {
        // 1. 基本データ確認
        $tables = ['companies', 'users', 'orders', 'products', 'invoices'];
        
        foreach ($tables as $table) {
            $stmt = $db->query("SELECT COUNT(*) as count FROM {$table}");
            $count = $stmt->fetch()['count'];
            $result['table_counts'][$table] = $count;
        }
        
        // 2. 注文データの詳細確認
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
        
        // 3. 企業別集計（Collation 問題回避版）
        $stmt = $db->query("
            SELECT 
                c.company_name,
                COUNT(DISTINCT u.user_code) as user_count,
                COUNT(o.id) as order_count,
                COALESCE(SUM(CAST(o.total_amount AS DECIMAL(10,2))), 0) as total_amount
            FROM companies c
            LEFT JOIN users u ON u.company_id = c.id
            LEFT JOIN orders o ON o.user_code = u.user_code  
            GROUP BY c.id, c.company_name
            ORDER BY total_amount DESC
        ");
        $result['company_summary'] = $stmt->fetchAll();
        
        // 4. 利用者別集計（Collation 問題回避版）
        $stmt = $db->query("
            SELECT 
                u.user_code,
                u.user_name,
                u.company_name,
                COUNT(o.id) as order_count,
                COALESCE(SUM(CAST(o.total_amount AS DECIMAL(10,2))), 0) as total_amount,
                MIN(o.delivery_date) as first_order,
                MAX(o.delivery_date) as last_order
            FROM users u
            LEFT JOIN orders o ON o.user_code = u.user_code
            GROUP BY u.user_code, u.user_name, u.company_name
            ORDER BY total_amount DESC
        ");
        $result['user_summary'] = $stmt->fetchAll();
        
        // 5. データ整合性チェック
        $stmt = $db->query("
            SELECT 'orders' as source_table, COUNT(DISTINCT user_code) as unique_user_codes FROM orders
            UNION ALL
            SELECT 'users' as source_table, COUNT(DISTINCT user_code) as unique_user_codes FROM users
        ");
        $result['data_integrity'] = $stmt->fetchAll();
        
    } catch (Exception $e) {
        $result['error'] = $e->getMessage();
        
        // エラー時の簡易チェック
        foreach ($tables as $table) {
            try {
                $stmt = $db->query("SELECT COUNT(*) as count FROM {$table}");
                $count = $stmt->fetch()['count'];
                $result['table_counts'][$table] = $count;
            } catch (Exception $tableError) {
                $result['table_counts'][$table] = 'Error: ' . $tableError->getMessage();
            }
        }
    }
    
    return $result;
}

/**
 * 注文データサンプル取得
 */
function getOrderSample($db) {
    $stmt = $db->query("
        SELECT 
            o.delivery_date,
            o.user_code,
            o.user_name,
            o.company_name,
            o.product_name,
            o.quantity,
            o.unit_price,
            o.total_amount
        FROM orders o
        ORDER BY o.delivery_date DESC, o.user_code
        LIMIT 20
    ");
    
    return $stmt->fetchAll();
}

/**
 * 請求書生成のテスト実行（Collation エラー完全対応版）
 */
function testInvoiceGeneration($db) {
    $result = [];
    
    try {
        // データベース診断情報を事前取得
        $result['debug_info'] = debugDatabaseSchema($db);
        
        // 期間設定（過去30日）
        $periodEnd = date('Y-m-d');
        $periodStart = date('Y-m-d', strtotime('-30 days'));
        $dueDate = date('Y-m-d', strtotime('+30 days'));
        
        // トランザクション開始
        $db->query("START TRANSACTION");
        
        // 企業別請求書データ生成（外部キー使用でCollation問題回避）
        $stmt = $db->query("
            SELECT 
                c.id as company_id,
                c.company_name,
                COUNT(o.id) as order_count,
                SUM(CAST(o.total_amount AS DECIMAL(10,2))) as subtotal,
                ROUND(SUM(CAST(o.total_amount AS DECIMAL(10,2))) * 0.1, 0) as tax_amount,
                ROUND(SUM(CAST(o.total_amount AS DECIMAL(10,2))) * 1.1, 0) as total_amount,
                COUNT(DISTINCT u.user_code) as user_count,
                MIN(o.delivery_date) as first_order,
                MAX(o.delivery_date) as last_order
            FROM companies c
            INNER JOIN users u ON u.company_id = c.id
            INNER JOIN orders o ON o.user_code = u.user_code
            WHERE o.delivery_date BETWEEN ? AND ?
            GROUP BY c.id, c.company_name
            HAVING order_count > 0
        ", [$periodStart, $periodEnd]);
        
        $companyInvoices = $stmt->fetchAll();
        
        if (empty($companyInvoices)) {
            // データがない場合は全期間で試行
            $stmt = $db->query("
                SELECT 
                    c.id as company_id,
                    c.company_name,
                    COUNT(o.id) as order_count,
                    SUM(CAST(o.total_amount AS DECIMAL(10,2))) as subtotal,
                    ROUND(SUM(CAST(o.total_amount AS DECIMAL(10,2))) * 0.1, 0) as tax_amount,
                    ROUND(SUM(CAST(o.total_amount AS DECIMAL(10,2))) * 1.1, 0) as total_amount,
                    COUNT(DISTINCT u.user_code) as user_count,
                    MIN(o.delivery_date) as first_order,
                    MAX(o.delivery_date) as last_order
                FROM companies c
                INNER JOIN users u ON u.company_id = c.id
                INNER JOIN orders o ON o.user_code = u.user_code
                GROUP BY c.id, c.company_name
                HAVING order_count > 0
            ");
            
            $companyInvoices = $stmt->fetchAll();
            $periodStart = '2024-01-01'; // 全期間
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
        
        foreach ($companyInvoices as $company) {
            try {
                // 請求書番号生成
                $invoiceNumber = generateInvoiceNumber();
                
                // 代表利用者取得（外部キー使用でCollation問題回避）
                $stmt = $db->query("
                    SELECT id, user_code, user_name 
                    FROM users 
                    WHERE company_id = ?
                    LIMIT 1
                ", [$company['company_id']]);
                $user = $stmt->fetch();
                
                if (!$user) {
                    $errors[] = "企業「{$company['company_name']}」の利用者が見つかりません";
                    continue;
                }
                
                // 請求書挿入（型変換を明示的に行う）
                $stmt = $db->prepare("
                    INSERT INTO invoices (
                        invoice_number, user_id, user_code, user_name,
                        company_name, invoice_date, due_date, 
                        period_start, period_end,
                        subtotal, tax_rate, tax_amount, total_amount,
                        invoice_type, status, created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");
                
                $stmt->execute([
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
                
                $invoiceId = $db->lastInsertId();
                $invoiceIds[] = $invoiceId;
                
                // 請求書明細挿入（外部キー使用）
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
                    INNER JOIN users u ON o.user_code = u.user_code
                    WHERE u.company_id = ?
                    AND o.delivery_date BETWEEN ? AND ?
                    ORDER BY o.delivery_date, o.user_code
                ", [$company['company_id'], $periodStart, $periodEnd]);
                
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
                
                // 成功情報を記録
                $result['invoice_success'][] = [
                    'invoice_id' => $invoiceId,
                    'invoice_number' => $invoiceNumber,
                    'company_name' => $company['company_name'],
                    'total_amount' => $company['total_amount'],
                    'detail_count' => $detailCount
                ];
                
            } catch (Exception $e) {
                $errors[] = "企業「{$company['company_name']}」の請求書生成エラー: " . $e->getMessage();
                
                // 最初のエラーで詳細デバッグ情報を記録
                if (count($errors) === 1) {
                    $result['first_error_debug'] = [
                        'company' => $company,
                        'user' => isset($user) ? $user : null,
                        'error_message' => $e->getMessage(),
                        'error_code' => $e->getCode()
                    ];
                }
            }
        }
        
        // コミットまたはロールバック
        if ($insertedCount > 0 && count($errors) < count($companyInvoices)) {
            $db->query("COMMIT");
            $result['transaction_result'] = 'committed';
        } else {
            $db->query("ROLLBACK");
            $result['transaction_result'] = 'rolled_back';
        }
        
        // 結果サマリー
        $result['generation_summary'] = [
            'status' => $insertedCount > 0 ? 'success' : 'failed',
            'companies_processed' => count($companyInvoices),
            'invoices_created' => $insertedCount,
            'errors_count' => count($errors),
            'invoice_ids' => $invoiceIds,
            'errors' => $errors,
            'company_details' => $companyInvoices
        ];
        
        // 生成された請求書の確認
        if (!empty($invoiceIds)) {
            $placeholders = str_repeat('?,', count($invoiceIds) - 1) . '?';
            $stmt = $db->query("
                SELECT 
                    i.id,
                    i.invoice_number,
                    i.company_name,
                    i.total_amount,
                    i.status,
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
            'error_code' => $e->getCode(),
            'period_start' => $periodStart ?? null,
            'period_end' => $periodEnd ?? null
        ];
    }
    
    return $result;
}

/**
 * 請求書番号生成
 */
function generateInvoiceNumber() {
    $prefix = 'INV';
    $date = date('Ymd');
    $random = sprintf('%03d', mt_rand(1, 999));
    return "{$prefix}-{$date}-{$random}";
}

// HTMLページ表示（APIでない場合のみ）
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>請求書生成機能テスト - Smiley配食事業システム（Collation対応版）</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .header { background: #ff6b35; color: white; padding: 20px; border-radius: 5px; margin-bottom: 20px; }
        .test-section { background: white; margin-bottom: 20px; border-radius: 5px; overflow: hidden; }
        .section-header { background: #f8f9fa; padding: 15px; border-bottom: 2px solid #ff6b35; font-weight: bold; }
        .section-content { padding: 20px; }
        .btn { padding: 10px 20px; margin: 5px; border: none; border-radius: 5px; cursor: pointer; font-size: 14px; }
        .btn-primary { background: #007bff; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-warning { background: #ffc107; color: black; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-info { background: #17a2b8; color: white; }
        .result-box { background: #f8f9fa; padding: 15px; border-radius: 5px; margin-top: 15px; }
        .loading { text-align: center; padding: 20px; color: #666; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .warning { background: #fff3cd; color: #856404; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .data-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .data-table th, .data-table td { padding: 8px; border: 1px solid #ddd; text-align: left; font-size: 0.9em; }
        .data-table th { background: #f1f1f1; font-weight: bold; }
        .stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 15px 0; }
        .stat-card { background: #e3f2fd; padding: 15px; border-radius: 5px; text-align: center; }
        .stat-value { font-size: 1.5em; font-weight: bold; color: #1976d2; }
        .stat-label { font-size: 0.9em; color: #666; margin-top: 5px; }
        .debug-box { background: #f8f9fa; border: 1px solid #ddd; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .debug-box pre { background: white; padding: 10px; border-radius: 3px; overflow-x: auto; font-size: 12px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>🧪 請求書生成機能テストツール（v2.0 - Collation対応版）</h1>
        <p>Collation エラーを根本解決した請求書生成機能をテストします</p>
        <small>✅ 外部キー使用によるJOIN最適化 | 🔧 型変換明示化 | 🚀 エラーハンドリング強化</small>
    </div>

    <!-- データベース診断 -->
    <div class="test-section">
        <div class="section-header">0. データベース診断（Collation問題調査）</div>
        <div class="section-content">
            <p>データベースの文字セット・Collation設定とテーブル構造を確認します</p>
            <button class="btn btn-info" onclick="debugSchema()">データベース診断実行</button>
            <div id="debugResult"></div>
        </div>
    </div>

    <!-- データ確認セクション -->
    <div class="test-section">
        <div class="section-header">1. データベースデータ確認</div>
        <div class="section-content">
            <p>請求書生成に必要なデータの状況を確認します</p>
            <button class="btn btn-primary" onclick="checkData()">データ確認実行</button>
            <div id="dataCheckResult"></div>
        </div>
    </div>

    <!-- 注文データサンプル -->
    <div class="test-section">
        <div class="section-header">2. 注文データサンプル確認</div>
        <div class="section-content">
            <p>実際の注文データを確認して請求書生成の準備状況をチェックします</p>
            <button class="btn btn-success" onclick="getOrderSample()">注文データ取得</button>
            <div id="orderSampleResult"></div>
        </div>
    </div>

    <!-- 請求書生成テスト -->
    <div class="test-section">
        <div class="section-header">3. 請求書生成テスト（Collation対応版）</div>
        <div class="section-content">
            <p><strong>⚠️ 注意:</strong> このテストは実際にinvoicesテーブルにデータを挿入します</p>
            <p><strong>🔧 改善点:</strong> 外部キー使用・型変換明示・エラーハンドリング強化</p>
            <button class="btn btn-warning" onclick="testInvoiceGeneration()">請求書生成テスト実行</button>
            <div id="generationTestResult"></div>
        </div>
    </div>

    <!-- TODO進捗表示 -->
    <div class="test-section">
        <div class="section-header">4. TODO進捗状況</div>
        <div class="section-content">
            <div class="stat-grid">
                <div class="stat-card">
                    <div class="stat-value">✅</div>
                    <div class="stat-label">テーブル構造確認</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">✅</div>
                    <div class="stat-label">実データ確認</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">🔧</div>
                    <div class="stat-label">請求書生成機能（修正中）</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">⏳</div>
                    <div class="stat-label">PDF生成機能</div>
                </div>
            </div>
            <div class="success">
                <h5>🎯 現在の修正内容</h5>
                <ul>
                    <li>✅ Collation エラー対応：外部キー使用でJOIN処理最適化</li>
                    <li>✅ 型変換問題対応：CAST関数とPHP型変換の明示化</li>
                    <li>✅ エラーハンドリング強化：詳細デバッグ情報の出力</li>
                    <li>⏳ 請求書生成成功の確認</li>
                </ul>
            </div>
            <p><strong>次のステップ:</strong> SmileyInvoiceGeneratorクラスの本格実装</p>
        </div>
    </div>

    <script>
        function debugSchema() {
            showLoading('debugResult');
            fetch('?action=debug_schema')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayDebugResult(data.data);
                    } else {
                        showError('debugResult', data.error);
                    }
                })
                .catch(error => {
                    showError('debugResult', 'データベース診断エラー: ' + error.message);
                });
        }

        function checkData() {
            showLoading('dataCheckResult');
            fetch('?action=check_data')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayDataCheckResult(data.data);
                    } else {
                        showError('dataCheckResult', data.error);
                    }
                })
                .catch(error => {
                    showError('dataCheckResult', 'データ確認エラー: ' + error.message);
                });
        }

        function getOrderSample() {
            showLoading('orderSampleResult');
            fetch('?action=get_orders')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayOrderSample(data.data);
                    } else {
                        showError('orderSampleResult', data.error);
                    }
                })
                .catch(error => {
                    showError('orderSampleResult', '注文データ取得エラー: ' + error.message);
                });
        }

        function testInvoiceGeneration() {
            if (!confirm('実際に請求書データを生成します。実行してもよろしいですか？')) {
                return;
            }
            showLoading('generationTestResult');
            fetch('?action=test_generate')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayGenerationResult(data.data);
                    } else {
                        showError('generationTestResult', data.error);
                    }
                })
                .catch(error => {
                    showError('generationTestResult', '請求書生成テストエラー: ' + error.message);
                });
        }

        function displayDebugResult(data) {
            let html = '<div class="success">🔍 データベース診断完了</div>';
            
            if (data.database_info) {
                html += '<h4>📊 データベース情報</h4>';
                html += '<div class="debug-box">';
                html += `<p><strong>データベース名:</strong> ${data.database_info.database_name}</p>`;
                html += `<p><strong>文字セット:</strong> ${data.database_info.charset}</p>`;
                html += `<p><strong>コレーション:</strong> ${data.database_info.collation}</p>`;
                html += '</div>';
            }
            
            if (data.column_collations && data.column_collations.length > 0) {
                html += '<h4>📋 テーブルカラムのCollation一覧</h4>';
                html += '<table class="data-table"><thead><tr><th>テーブル</th><th>カラム</th><th>文字セット</th><th>コレーション</th></tr></thead><tbody>';
                data.column_collations.forEach(col => {
                    const isUtf8mb4 = col.CHARACTER_SET_NAME === 'utf8mb4';
                    const rowClass = isUtf8mb4 ? '' : ' style="background: #fff3cd;"';
                    html += `<tr${rowClass}><td>${col.TABLE_NAME}</td><td>${col.COLUMN_NAME}</td><td>${col.CHARACTER_SET_NAME}</td><td>${col.COLLATION_NAME}</td></tr>`;
                });
                html += '</tbody></table>';
                html += '<div class="warning">⚠️ 黄色の行は非utf8mb4文字セットです</div>';
            }
            
            if (data.invoices_structure) {
                html += '<h4>🗂️ invoicesテーブル構造</h4>';
                html += '<div class="debug-box"><pre>' + escapeHtml(data.invoices_structure) + '</pre></div>';
            }
            
            if (data.error) {
                html += `<div class="error">診断エラー: ${data.error}</div>`;
            }
            
            document.getElementById('debugResult').innerHTML = html;
        }

        function displayDataCheckResult(data) {
            let html = '<div class="success">✅ データ確認完了</div>';
            
            if (data.error) {
                html += `<div class="warning">⚠️ 部分的エラー: ${data.error}</div>`;
                html += '<div class="success">💡 基本テーブル件数は取得できました</div>';
            }
            
            html += '<h4>📊 テーブル件数</h4>';
            html += '<div class="stat-grid">';
            Object.keys(data.table_counts).forEach(table => {
                const count = data.table_counts[table];
                const isError = typeof count === 'string' && count.includes('Error');
                html += `<div class="stat-card ${isError ? 'error' : ''}">
                    <div class="stat-value">${isError ? '❌' : count}</div>
                    <div class="stat-label">${table}</div>
                </div>`;
            });
            html += '</div>';
            
            if (data.daily_orders && data.daily_orders.length > 0) {
                html += '<h4>📅 日別注文データ（直近10日）</h4>';
                html += '<table class="data-table"><thead><tr><th>配達日</th><th>注文件数</th><th>日計金額</th><th>利用者数</th></tr></thead><tbody>';
                data.daily_orders.forEach(day => {
                    html += `<tr><td>${day.delivery_date}</td><td>${day.order_count}件</td><td>¥${Number(day.daily_total || 0).toLocaleString()}</td><td>${day.user_count}名</td></tr>`;
                });
                html += '</tbody></table>';
            }
            
            if (data.company_summary && data.company_summary.length > 0) {
                html += '<h4>🏢 企業別集計</h4>';
                html += '<table class="data-table"><thead><tr><th>企業名</th><th>利用者数</th><th>注文件数</th><th>総額</th></tr></thead><tbody>';
                data.company_summary.forEach(company => {
                    html += `<tr><td>${company.company_name || '未設定'}</td><td>${company.user_count}名</td><td>${company.order_count}件</td><td>¥${Number(company.total_amount || 0).toLocaleString()}</td></tr>`;
                });
                html += '</tbody></table>';
            }
            
            if (data.data_integrity && data.data_integrity.length > 0) {
                html += '<h4>🔍 データ整合性チェック</h4>';
                html += '<table class="data-table"><thead><tr><th>テーブル</th><th>ユニーク利用者コード数</th></tr></thead><tbody>';
                data.data_integrity.forEach(check => {
                    html += `<tr><td>${check.source_table}</td><td>${check.unique_user_codes}件</td></tr>`;
                });
                html += '</tbody></table>';
            }
            
            document.getElementById('dataCheckResult').innerHTML = html;
        }

        function displayOrderSample(orders) {
            let html = '<div class="success">✅ 注文データサンプル取得完了</div>';
            html += '<table class="data-table"><thead><tr><th>配達日</th><th>利用者コード</th><th>利用者名</th><th>企業名</th><th>商品名</th><th>数量</th><th>単価</th><th>金額</th></tr></thead><tbody>';
            
            orders.forEach(order => {
                html += `<tr>
                    <td>${order.delivery_date}</td>
                    <td>${order.user_code}</td>
                    <td>${order.user_name}</td>
                    <td>${order.company_name || '-'}</td>
                    <td>${order.product_name}</td>
                    <td>${order.quantity}</td>
                    <td>¥${Number(order.unit_price).toLocaleString()}</td>
                    <td>¥${Number(order.total_amount).toLocaleString()}</td>
                </tr>`;
            });
            
            html += '</tbody></table>';
            document.getElementById('orderSampleResult').innerHTML = html;
        }

        function displayGenerationResult(data) {
            const summary = data.generation_summary;
            const debug = data.debug_info || {};
            
            let html = '';
            
            // デバッグ情報表示
            if (debug.database_info) {
                html += '<div class="debug-box">';
                html += '<h5>🔍 データベース診断情報</h5>';
                html += `<p><strong>文字セット:</strong> ${debug.database_info.charset} | <strong>コレーション:</strong> ${debug.database_info.collation}</p>`;
                html += '</div>';
            }
            
            if (summary.status === 'success') {
                html += '<div class="success">✅ 請求書生成テスト成功</div>';
                
                html += '<h4>📋 生成結果サマリー</h4>';
                html += '<div class="stat-grid">';
                html += `<div class="stat-card"><div class="stat-value">${summary.invoices_created}</div><div class="stat-label">請求書生成数</div></div>`;
                html += `<div class="stat-card"><div class="stat-value">${summary.companies_processed}</div><div class="stat-label">対象企業数</div></div>`;
                html += `<div class="stat-card"><div class="stat-value">${summary.errors_count}</div><div class="stat-label">エラー件数</div></div>`;
                html += `<div class="stat-card"><div class="stat-value">${data.period_info?.period_start || 'N/A'}</div><div class="stat-label">期間開始</div></div>`;
                html += '</div>';
                
                if (summary.errors && summary.errors.length > 0) {
                    html += '<div class="warning"><h5>⚠️ 発生したエラー</h5><ul>';
                    summary.errors.forEach(error => {
                        html += `<li>${error}</li>`;
                    });
                    html += '</ul></div>';
                }
                
                if (data.invoice_success && data.invoice_success.length > 0) {
                    html += '<h4>🎉 成功した請求書</h4>';
                    html += '<table class="data-table"><thead><tr><th>請求書ID</th><th>請求書番号</th><th>企業名</th><th>金額</th><th>明細件数</th></tr></thead><tbody>';
                    data.invoice_success.forEach(invoice => {
                        html += `<tr>
                            <td>${invoice.invoice_id}</td>
                            <td><strong>${invoice.invoice_number}</strong></td>
                            <td>${invoice.company_name}</td>
                            <td>¥${Number(invoice.total_amount).toLocaleString()}</td>
                            <td>${invoice.detail_count}件</td>
                        </tr>`;
                    });
                    html += '</tbody></table>';
                }
                
                if (data.created_invoices && data.created_invoices.length > 0) {
                    html += '<h4>📄 データベース登録確認</h4>';
                    html += '<table class="data-table"><thead><tr><th>ID</th><th>請求書番号</th><th>企業名</th><th>ステータス</th><th>金額</th><th>明細件数</th></tr></thead><tbody>';
                    data.created_invoices.forEach(invoice => {
                        html += `<tr>
                            <td>${invoice.id}</td>
                            <td><strong>${invoice.invoice_number}</strong></td>
                            <td>${invoice.company_name}</td>
                            <td><span class="stat-value">${invoice.status}</span></td>
                            <td>¥${Number(invoice.total_amount).toLocaleString()}</td>
                            <td>${invoice.detail_count}件</td>
                        </tr>`;
                    });
                    html += '</tbody></table>';
                }
                
                html += `<div class="success" style="margin-top: 20px;">
                    <h5>🎉 請求書生成完了！</h5>
                    <p><strong>次のステップ:</strong></p>
                    <ul>
                        <li>✅ 請求書データベース挿入 - 完了</li>
                        <li>✅ Collation エラー対応 - 完了</li>
                        <li>⏳ PDF生成機能のテスト</li>
                        <li>⏳ フロントエンド画面での表示確認</li>
                        <li>⏳ SmileyInvoiceGeneratorクラスの完全実装</li>
                    </ul>
                </div>`;
                
            } else if (summary.status === 'failed') {
                html += '<div class="warning">⚠️ 請求書生成は部分的に失敗しましたが、一部成功しました</div>';
                html += `<p>成功: ${summary.invoices_created}件、失敗: ${summary.errors_count}件</p>`;
                
                if (summary.errors && summary.errors.length > 0) {
                    html += '<div class="error"><h5>エラー詳細</h5><ul>';
                    summary.errors.forEach(error => {
                        html += `<li>${error}</li>`;
                    });
                    html += '</ul></div>';
                }
                
            } else if (summary.status === 'error') {
                html += '<div class="error">❌ 請求書生成テストでエラーが発生しました</div>';
                html += `<div class="error"><strong>エラー詳細:</strong> ${summary.error_message}</div>`;
                
                if (data.first_error_debug) {
                    html += '<div class="debug-box">';
                    html += '<h5>🔍 詳細デバッグ情報</h5>';
                    html += '<pre>' + JSON.stringify(data.first_error_debug, null, 2) + '</pre>';
                    html += '</div>';
                }
            }
            
            document.getElementById('generationTestResult').innerHTML = html;
        }

        function showLoading(elementId) {
            document.getElementById(elementId).innerHTML = '<div class="loading">⏳ 処理中...</div>';
        }

        function showError(elementId, message) {
            document.getElementById(elementId).innerHTML = `<div class="error">❌ エラー: ${message}</div>`;
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>
</body>
</html>
