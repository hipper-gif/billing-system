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

// API処理（JSONレスポンス）
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
            'data' => $result
        ], JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

/**
 * 請求書生成に必要なデータをチェック
 */
function checkInvoiceGenerationData($db) {
    $result = [];
    
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
    
    // 3. 利用者別集計
    $stmt = $db->query("
        SELECT 
            u.user_code,
            u.user_name,
            u.company_name,
            COUNT(o.id) as order_count,
            SUM(o.total_amount) as total_amount,
            MIN(o.delivery_date) as first_order,
            MAX(o.delivery_date) as last_order
        FROM users u
        LEFT JOIN orders o ON u.user_code = o.user_code
        GROUP BY u.user_code, u.user_name, u.company_name
        ORDER BY total_amount DESC
    ");
    $result['user_summary'] = $stmt->fetchAll();
    
    // 4. 企業別集計
    $stmt = $db->query("
        SELECT 
            c.company_name,
            COUNT(DISTINCT u.user_code) as user_count,
            COUNT(o.id) as order_count,
            COALESCE(SUM(o.total_amount), 0) as total_amount
        FROM companies c
        LEFT JOIN users u ON c.company_name = u.company_name
        LEFT JOIN orders o ON u.user_code = o.user_code
        GROUP BY c.id, c.company_name
        ORDER BY total_amount DESC
    ");
    $result['company_summary'] = $stmt->fetchAll();
    
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
 * 請求書生成のテスト実行
 */
function testInvoiceGeneration($db) {
    // 期間設定（過去30日）
    $periodEnd = date('Y-m-d');
    $periodStart = date('Y-m-d', strtotime('-30 days'));
    $dueDate = date('Y-m-d', strtotime('+30 days'));
    
    // テスト用請求書データ生成
    $result = [];
    
    // 1. 企業別請求書データを生成
    $stmt = $db->query("
        SELECT 
            c.id as company_id,
            c.company_name,
            COUNT(o.id) as order_count,
            SUM(o.total_amount) as subtotal,
            SUM(o.total_amount) * 0.1 as tax_amount,
            SUM(o.total_amount) * 1.1 as total_amount
        FROM companies c
        INNER JOIN users u ON c.company_name = u.company_name
        INNER JOIN orders o ON u.user_code = o.user_code
        WHERE o.delivery_date BETWEEN ? AND ?
        GROUP BY c.id, c.company_name
        HAVING order_count > 0
    ", [$periodStart, $periodEnd]);
    
    $companyInvoices = $stmt->fetchAll();
    
    // 2. 請求書テーブルに挿入テスト
    $db->query("START TRANSACTION");
    
    try {
        $insertedCount = 0;
        
        foreach ($companyInvoices as $company) {
            // 請求書番号生成
            $invoiceNumber = generateInvoiceNumber();
            
            // 代表利用者取得（最初の利用者を代表として使用）
            $stmt = $db->query("
                SELECT u.id, u.user_code, u.user_name 
                FROM users u 
                WHERE u.company_name = ? 
                LIMIT 1
            ", [$company['company_name']]);
            $user = $stmt->fetch();
            
            if (!$user) continue;
            
            // 請求書挿入
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
                $user['id'],
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
            
            $invoiceId = $db->lastInsertId();
            
            // 請求書明細挿入
            $stmt = $db->query("
                SELECT 
                    o.id as order_id,
                    o.delivery_date as order_date,
                    o.product_code,
                    o.product_name,
                    o.quantity,
                    o.unit_price,
                    o.total_amount
                FROM orders o
                INNER JOIN users u ON o.user_code = u.user_code
                WHERE u.company_name = ? 
                AND o.delivery_date BETWEEN ? AND ?
            ", [$company['company_name'], $periodStart, $periodEnd]);
            
            $orderDetails = $stmt->fetchAll();
            
            foreach ($orderDetails as $detail) {
                $db->query("
                    INSERT INTO invoice_details (
                        invoice_id, order_id, order_date, product_code,
                        product_name, quantity, unit_price, amount, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ", [
                    $invoiceId,
                    $detail['order_id'],
                    $detail['order_date'],
                    $detail['product_code'],
                    $detail['product_name'],
                    $detail['quantity'],
                    $detail['unit_price'],
                    $detail['total_amount']
                ]);
            }
            
            $insertedCount++;
        }
        
        $db->query("COMMIT");
        
        $result['test_generation'] = [
            'status' => 'success',
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'due_date' => $dueDate,
            'companies_processed' => count($companyInvoices),
            'invoices_created' => $insertedCount,
            'company_details' => $companyInvoices
        ];
        
    } catch (Exception $e) {
        $db->query("ROLLBACK");
        throw $e;
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

// HTMLページ表示
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
            let html = '<div class="success">データ確認完了</div>';
            
            // テーブル件数表示
            html += '<h4>📊 テーブル件数</h4>';
            html += '<div class="stat-grid">';
            Object.keys(data.table_counts).forEach(table => {
                html += `
                    <div class="stat-card">
                        <div class="stat-value">${data.table_counts[table]}</div>
                        <div class="stat-label">${table}</div>
                    </div>
                `;
            });
            html += '</div>';
            
            // 日別注文データ
            if (data.daily_orders && data.daily_orders.length > 0) {
                html += '<h4>📅 日別注文データ（直近10日）</h4>';
                html += '<table class="data-table"><thead><tr><th>配達日</th><th>注文件数</th><th>日計金額</th><th>利用者数</th></tr></thead><tbody>';
                data.daily_orders.forEach(day => {
                    html += `<tr><td>${day.delivery_date}</td><td>${day.order_count}件</td><td>¥${Number(day.daily_total).toLocaleString()}</td><td>${day.user_count}名</td></tr>`;
                });
                html += '</tbody></table>';
            }
            
            // 企業別集計
            if (data.company_summary && data.company_summary.length > 0) {
                html += '<h4>🏢 企業別集計</h4>';
                html += '<table class="data-table"><thead><tr><th>企業名</th><th>利用者数</th><th>注文件数</th><th>総額</th></tr></thead><tbody>';
                data.company_summary.forEach(company => {
                    html += `<tr><td>${company.company_name || '未設定'}</td><td>${company.user_count}名</td><td>${company.order_count}件</td><td>¥${Number(company.total_amount).toLocaleString()}</td></tr>`;
                });
                html += '</tbody></table>';
            }
            
            document.getElementById('dataCheckResult').innerHTML = html;
        }

        function displayOrderSample(orders) {
            let html = '<div class="success">注文データサンプル取得完了</div>';
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
            if (data.test_generation.status === 'success') {
                let html = '<div class="success">✅ 請求書生成テスト成功</div>';
                
                const result = data.test_generation;
                html += `
                    <h4>📋 生成結果</h4>
                    <div class="stat-grid">
                        <div class="stat-card">
                            <div class="stat-value">${result.invoices_created}</div>
                            <div class="stat-label">請求書生成数</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value">${result.companies_processed}</div>
                            <div class="stat-label">対象企業数</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value">${result.period_start}</div>
                            <div class="stat-label">期間開始</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value">${result.period_end}</div>
                            <div class="stat-label">期間終了</div>
                        </div>
                    </div>
                `;
                
                if (result.company_details && result.company_details.length > 0) {
                    html += '<h4>🏢 生成された請求書詳細</h4>';
                    html += '<table class="data-table"><thead><tr><th>企業名</th><th>注文件数</th><th>小計</th><th>消費税</th><th>合計</th></tr></thead><tbody>';
                    result.company_details.forEach(company => {
                        html += `<tr>
                            <td>${company.company_name}</td>
                            <td>${company.order_count}件</td>
                            <td>¥${Number(company.subtotal).toLocaleString()}</td>
                            <td>¥${Number(company.tax_amount).toLocaleString()}</td>
                            <td><strong>¥${Number(company.total_amount).toLocaleString()}</strong></td>
                        </tr>`;
                    });
                    html += '</tbody></table>';
                }
                
                document.getElementById('generationTestResult').innerHTML = html;
            } else {
                showError('generationTestResult', '請求書生成テストに失敗しました');
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
