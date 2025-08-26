<?php
/**
 * 請求書生成機能テストツール
 * 実際のテーブル構造に基づいて請求書生成をテスト
 * 
 * @author Claude
 * @version 1.0.0
 * @created 2025-08-27
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
 * 請求書生成に必要なデータをチェック（照合順序エラー対応版）
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
                SUM(total_amount) as daily_total,
                COUNT(DISTINCT user_code) as user_count
            FROM orders 
            GROUP BY DATE(delivery_date)
            ORDER BY delivery_date DESC
            LIMIT 10
        ");
        $result['daily_orders'] = $stmt->fetchAll();
        
        // 3. 利用者別集計（照合順序エラー対応）
        $stmt = $db->query("
            SELECT 
                u.user_code,
                u.user_name,
                u.company_name,
                COUNT(o.id) as order_count,
                COALESCE(SUM(o.total_amount), 0) as total_amount,
                MIN(o.delivery_date) as first_order,
                MAX(o.delivery_date) as last_order
            FROM users u
            LEFT JOIN orders o ON u.user_code COLLATE utf8mb4_unicode_ci = o.user_code COLLATE utf8mb4_unicode_ci
            GROUP BY u.user_code, u.user_name, u.company_name
            ORDER BY total_amount DESC
        ");
        $result['user_summary'] = $stmt->fetchAll();
        
        // 4. 企業別集計（照合順序エラー対応）
        $stmt = $db->query("
            SELECT 
                c.company_name,
                COUNT(DISTINCT u.user_code) as user_count,
                COUNT(o.id) as order_count,
                COALESCE(SUM(o.total_amount), 0) as total_amount
            FROM companies c
            LEFT JOIN users u ON c.company_name COLLATE utf8mb4_unicode_ci = u.company_name COLLATE utf8mb4_unicode_ci
            LEFT JOIN orders o ON u.user_code COLLATE utf8mb4_unicode_ci = o.user_code COLLATE utf8mb4_unicode_ci
            GROUP BY c.id, c.company_name
            ORDER BY total_amount DESC
        ");
        $result['company_summary'] = $stmt->fetchAll();
        
        // 5. データ整合性チェック
        $stmt = $db->query("
            SELECT 
                'orders' as source_table,
                COUNT(DISTINCT user_code) as unique_user_codes
            FROM orders
            UNION ALL
            SELECT 
                'users' as source_table,
                COUNT(DISTINCT user_code) as unique_user_codes
            FROM users
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
 * 請求書生成のテスト実行（照合順序エラー対応版）
 */
function testInvoiceGeneration($db) {
    // 期間設定（過去30日）
    $periodEnd = date('Y-m-d');
    $periodStart = date('Y-m-d', strtotime('-30 days'));
    $dueDate = date('Y-m-d', strtotime('+30 days'));
    
    // テスト用請求書データ生成
    $result = [];
    
    try {
        // 1. 企業別請求書データを生成（照合順序対応）
        $stmt = $db->query("
            SELECT 
                c.id as company_id,
                c.company_name,
                COUNT(o.id) as order_count,
                SUM(o.total_amount) as subtotal,
                ROUND(SUM(o.total_amount) * 0.1, 0) as tax_amount,
                ROUND(SUM(o.total_amount) * 1.1, 0) as total_amount,
                COUNT(DISTINCT u.user_code) as user_count
            FROM companies c
            INNER JOIN users u ON c.company_name COLLATE utf8mb4_unicode_ci = u.company_name COLLATE utf8mb4_unicode_ci
            INNER JOIN orders o ON u.user_code COLLATE utf8mb4_unicode_ci = o.user_code COLLATE utf8mb4_unicode_ci
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
                    SUM(o.total_amount) as subtotal,
                    ROUND(SUM(o.total_amount) * 0.1, 0) as tax_amount,
                    ROUND(SUM(o.total_amount) * 1.1, 0) as total_amount,
                    COUNT(DISTINCT u.user_code) as user_count
                FROM companies c
                INNER JOIN users u ON c.company_name COLLATE utf8mb4_unicode_ci = u.company_name COLLATE utf8mb4_unicode_ci
                INNER JOIN orders o ON u.user_code COLLATE utf8mb4_unicode_ci = o.user_code COLLATE utf8mb4_unicode_ci
                GROUP BY c.id, c.company_name
                HAVING order_count > 0
            ");
            
            $companyInvoices = $stmt->fetchAll();
            $periodStart = '2024-01-01'; // 全期間
        }
        
        // 2. 請求書テーブルに挿入テスト
        $db->query("START TRANSACTION");
        
        $insertedCount = 0;
        $invoiceIds = [];
        
        foreach ($companyInvoices as $company) {
            // 請求書番号生成
            $invoiceNumber = generateInvoiceNumber();
            
            // 代表利用者取得（最初の利用者を代表として使用）
            $stmt = $db->query("
                SELECT u.id, u.user_code, u.user_name 
                FROM users u 
                WHERE u.company_name COLLATE utf8mb4_unicode_ci = ? COLLATE utf8mb4_unicode_ci
                LIMIT 1
            ", [$company['company_name']]);
            $user = $stmt->fetch();
            
if (!$user) continue;
            
            // データベースの文字セット確認とテーブル構造確認を追加
            try {
                // テーブル構造確認
                $stmt = $db->query("SHOW CREATE TABLE invoices");
                $table_info = $stmt->fetch(PDO::FETCH_ASSOC);
                $result['debug_info']['invoices_table_structure'] = $table_info['Create Table'];
                
                // データベース文字セット確認
                $stmt = $db->query("SELECT @@character_set_database, @@collation_database");
                $charset_info = $stmt->fetch(PDO::FETCH_ASSOC);
                $result['debug_info']['database_charset'] = $charset_info['@@character_set_database'];
                $result['debug_info']['database_collation'] = $charset_info['@@collation_database'];
                
            } catch (Exception $debug_error) {
                $result['debug_info']['debug_error'] = $debug_error->getMessage();
            }
            
            // 請求書挿入（エラー詳細キャッチ付き）
            try {
                $stmt = $db->query("
                    INSERT INTO invoices (
                        invoice_number, user_id, user_code, user_name,
                        company_name, invoice_date, due_date, 
                        period_start, period_end,
                        subtotal, tax_rate, tax_amount, total_amount,
                        invoice_type, status, created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ", [
                    $invoiceNumber,
                    (int)$user['id'],
                    $user['user_code'],
                    $user['user_name'],
                    $company['company_name'],
                    date('Y-m-d'),
                    $dueDate,
                    $periodStart,
                    $periodEnd,
                    $company['subtotal'],
                    10.00,
                    $company['tax_amount'],
                    $company['total_amount'],
                    'company',
                    'draft'
                ]);
                
            } catch (Exception $insert_error) {
                // 詳細エラー情報を結果に追加
                $result['insert_error'] = [
                    'message' => $insert_error->getMessage(),
                    'code' => $insert_error->getCode(),
                    'company_name' => $company['company_name'],
                    'user_data' => $user,
                    'invoice_data' => [
                        'invoice_number' => $invoiceNumber,
                        'user_id' => (int)$user['id'],
                        'user_code' => $user['user_code'],
                        'user_name' => $user['user_name'],
                        'company_name' => $company['company_name']
                    ]
                ];
                
                // 最初のエラーで中断して詳細を返す
                $db->query("ROLLBACK");
                return $result;
            }
        
        // 生成された請求書の確認
        if (!empty($invoiceIds)) {
            $placeholders = str_repeat('?,', count($invoiceIds) - 1) . '?';
            $stmt = $db->query("
                SELECT 
                    i.id,
                    i.invoice_number,
                    i.company_name,
                    i.total_amount,
                    COUNT(id_details.id) as detail_count
                FROM invoices i
                LEFT JOIN invoice_details id_details ON i.id = id_details.invoice_id
                WHERE i.id IN ({$placeholders})
                GROUP BY i.id, i.invoice_number, i.company_name, i.total_amount
            ", $invoiceIds);
            
            $result['test_generation']['created_invoices'] = $stmt->fetchAll();
        }
        
    } catch (Exception $e) {
        $db->query("ROLLBACK");
        $result['test_generation'] = [
            'status' => 'error',
            'error' => $e->getMessage(),
            'period_start' => $periodStart,
            'period_end' => $periodEnd
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
    <title>請求書生成機能テスト - Smiley配食事業システム</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .header { background: #ff6b35; color: white; padding: 20px; border-radius: 5px; margin-bottom: 20px; }
        .test-section { background: white; margin-bottom: 20px; border-radius: 5px; overflow: hidden; }
        .section-header { background: #f8f9fa; padding: 15px; border-bottom: 2px solid #ff6b35; font-weight: bold; }
        .section-content { padding: 20px; }
        .btn { padding: 10px 20px; margin: 5px; border: none; border-radius: 5px; cursor: pointer; }
        .btn-primary { background: #007bff; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-warning { background: #ffc107; color: black; }
        .btn-danger { background: #dc3545; color: white; }
        .result-box { background: #f8f9fa; padding: 15px; border-radius: 5px; margin-top: 15px; }
        .loading { text-align: center; padding: 20px; color: #666; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; }
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; }
        .data-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .data-table th, .data-table td { padding: 8px; border: 1px solid #ddd; text-align: left; font-size: 0.9em; }
        .data-table th { background: #f1f1f1; }
        .stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 15px 0; }
        .stat-card { background: #e3f2fd; padding: 15px; border-radius: 5px; text-align: center; }
        .stat-value { font-size: 1.5em; font-weight: bold; color: #1976d2; }
        .stat-label { font-size: 0.9em; color: #666; margin-top: 5px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>🧪 請求書生成機能テストツール</h1>
        <p>実際のデータベース構造に基づいて請求書生成機能をテストします</p>
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
        <div class="section-header">3. 請求書生成テスト</div>
        <div class="section-content">
            <p><strong>⚠️ 注意:</strong> このテストは実際にinvoicesテーブルにデータを挿入します</p>
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
                    <div class="stat-value">⏳</div>
                    <div class="stat-label">請求書生成機能</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">⏳</div>
                    <div class="stat-label">PDF生成機能</div>
                </div>
            </div>
            <p><strong>次のステップ:</strong> SmileyInvoiceGeneratorクラスの実装とテスト</p>
        </div>
    </div>

    <script>
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

        function displayDataCheckResult(data) {
            let html = '<div class="success">✅ データ確認完了</div>';
            
            if (data.error) {
                html += `<div class="error">⚠️ 部分的エラー: ${data.error}</div>`;
                html += '<div class="success">💡 テーブル件数は取得できました</div>';
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
            const result = data.test_generation;
            
            if (result.status === 'success') {
                let html = '<div class="success">✅ 請求書生成テスト成功</div>';
                
                html += '<h4>📋 生成結果</h4>';
                html += '<div class="stat-grid">';
                html += `<div class="stat-card"><div class="stat-value">${result.invoices_created}</div><div class="stat-label">請求書生成数</div></div>`;
                html += `<div class="stat-card"><div class="stat-value">${result.companies_processed}</div><div class="stat-label">対象企業数</div></div>`;
                html += `<div class="stat-card"><div class="stat-value">${result.period_start}</div><div class="stat-label">期間開始</div></div>`;
                html += `<div class="stat-card"><div class="stat-value">${result.period_end}</div><div class="stat-label">期間終了</div></div>`;
                html += '</div>';
                
                if (result.company_details && result.company_details.length > 0) {
                    html += '<h4>🏢 生成された請求書詳細</h4>';
                    html += '<table class="data-table"><thead><tr><th>企業名</th><th>利用者数</th><th>注文件数</th><th>小計</th><th>消費税</th><th>合計</th></tr></thead><tbody>';
                    result.company_details.forEach(company => {
                        html += `<tr>
                            <td>${company.company_name}</td>
                            <td>${company.user_count || 0}名</td>
                            <td>${company.order_count}件</td>
                            <td>¥${Number(company.subtotal || 0).toLocaleString()}</td>
                            <td>¥${Number(company.tax_amount || 0).toLocaleString()}</td>
                            <td><strong>¥${Number(company.total_amount || 0).toLocaleString()}</strong></td>
                        </tr>`;
                    });
                    html += '</tbody></table>';
                }
                
                if (result.created_invoices && result.created_invoices.length > 0) {
                    html += '<h4>📄 作成された請求書</h4>';
                    html += '<table class="data-table"><thead><tr><th>請求書ID</th><th>請求書番号</th><th>企業名</th><th>金額</th><th>明細件数</th></tr></thead><tbody>';
                    result.created_invoices.forEach(invoice => {
                        html += `<tr>
                            <td>${invoice.id}</td>
                            <td><strong>${invoice.invoice_number}</strong></td>
                            <td>${invoice.company_name}</td>
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
                        <li>⏳ PDF生成機能のテスト</li>
                        <li>⏳ フロントエンド画面での表示確認</li>
                        <li>⏳ SmileyInvoiceGeneratorクラスの完全実装</li>
                    </ul>
                </div>`;
                
                document.getElementById('generationTestResult').innerHTML = html;
            } else if (result.status === 'error') {
                let html = '<div class="error">❌ 請求書生成テストでエラーが発生しました</div>';
                html += `<div class="error">エラー詳細: ${result.error}</div>`;
                document.getElementById('generationTestResult').innerHTML = html;
            }
        }

        function showLoading(elementId) {
            document.getElementById(elementId).innerHTML = '<div class="loading">⏳ 処理中...</div>';
        }

        function showError(elementId, message) {
            document.getElementById(elementId).innerHTML = `<div class="error">❌ エラー: ${message}</div>`;
        }
    </script>
</body>
</html>
