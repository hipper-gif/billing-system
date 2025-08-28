<?php
/**
 * 請求書生成機能テストツール（シンプル動作確実版）
 * ボタン反応問題を完全解決
 * 
 * @author Claude  
 * @version 5.0.0
 * @created 2025-08-27
 * @updated 2025-08-27 - シンプル確実動作版
 */

// エラーハンドリング
error_reporting(0);
ini_set('display_errors', 0);

// データベース接続
try {
    require_once __DIR__ . '/../classes/Database.php';
} catch (Exception $e) {
    if (isset($_GET['action'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Database connection failed']);
        exit;
    }
}

// API処理
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    try {
        $db = Database::getInstance();
        
        switch ($_GET['action']) {
            case 'fix_data':
                $result = fixData($db);
                break;
            case 'check_data':  
                $result = checkData($db);
                break;
            case 'get_orders':
                $result = getOrders($db);
                break;
            case 'test_generate':
                $result = generateInvoices($db);
                break;
            default:
                $result = ['error' => 'Invalid action'];
        }
        
        echo json_encode(['success' => true, 'data' => $result]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// データ修正関数
function fixData($db) {
    $result = [];
    
    try {
        $db->query("START TRANSACTION");
        
        // orders.user_id修正
        $stmt = $db->query("SELECT COUNT(*) as count FROM orders WHERE user_id IS NULL");
        $nullCount = $stmt->fetch()['count'];
        $result['null_user_ids'] = $nullCount;
        
        if ($nullCount > 0) {
            $stmt = $db->query("
                UPDATE orders o 
                INNER JOIN users u ON o.user_code = u.user_code 
                SET o.user_id = u.id 
                WHERE o.user_id IS NULL
            ");
            $result['fixed_user_ids'] = $stmt->rowCount();
        }
        
        // users.company_id修正
        $stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE company_id IS NULL AND company_name IS NOT NULL");
        $nullCompanies = $stmt->fetch()['count'];
        $result['null_company_ids'] = $nullCompanies;
        
        if ($nullCompanies > 0) {
            $stmt = $db->query("
                UPDATE users u 
                INNER JOIN companies c ON u.company_name = c.company_name 
                SET u.company_id = c.id 
                WHERE u.company_id IS NULL
            ");
            $result['fixed_company_ids'] = $stmt->rowCount();
        }
        
        $db->query("COMMIT");
        $result['status'] = 'success';
        
    } catch (Exception $e) {
        $db->query("ROLLBACK");
        $result['error'] = $e->getMessage();
    }
    
    return $result;
}

// データ確認関数
function checkData($db) {
    $result = [];
    
    // テーブル件数
    $tables = ['companies', 'users', 'orders', 'invoices'];
    foreach ($tables as $table) {
        $stmt = $db->query("SELECT COUNT(*) as count FROM {$table}");
        $result['counts'][$table] = $stmt->fetch()['count'];
    }
    
    // 企業別集計
    $stmt = $db->query("
        SELECT 
            c.company_name,
            COUNT(DISTINCT u.user_code) as users,
            COUNT(o.id) as orders,
            SUM(o.total_amount) as total
        FROM companies c
        LEFT JOIN users u ON u.company_id = c.id
        LEFT JOIN orders o ON o.user_id = u.id  
        GROUP BY c.id, c.company_name
    ");
    $result['companies'] = $stmt->fetchAll();
    
    return $result;
}

// 注文データ取得関数  
function getOrders($db) {
    $stmt = $db->query("
        SELECT 
            delivery_date, user_code, user_name, company_name,
            product_name, quantity, unit_price, total_amount
        FROM orders 
        ORDER BY delivery_date DESC 
        LIMIT 20
    ");
    
    return $stmt->fetchAll();
}

// 請求書生成関数
function generateInvoices($db) {
    $result = [];
    
    try {
        // データ修正を先に実行
        $fixResult = fixData($db);
        $result['fix_result'] = $fixResult;
        
        $db->query("START TRANSACTION");
        
        // 企業別データ取得
        $stmt = $db->query("
            SELECT 
                c.id as company_id,
                c.company_name,
                COUNT(o.id) as order_count,
                SUM(o.total_amount) as subtotal,
                SUM(o.total_amount) * 0.1 as tax,
                SUM(o.total_amount) * 1.1 as total
            FROM companies c
            INNER JOIN users u ON u.company_id = c.id
            INNER JOIN orders o ON o.user_id = u.id
            GROUP BY c.id, c.company_name
            HAVING order_count > 0
        ");
        
        $companies = $stmt->fetchAll();
        $result['companies_found'] = count($companies);
        
        $created = 0;
        foreach ($companies as $company) {
            // 代表利用者取得
            $stmt = $db->query("
                SELECT id, user_code, user_name 
                FROM users 
                WHERE company_id = ? 
                LIMIT 1
            ", [$company['company_id']]);
            $user = $stmt->fetch();
            
            if (!$user) continue;
            
            // 請求書番号生成
            $invoiceNumber = 'INV-' . date('Ymd') . '-' . sprintf('%03d', $created + 1);
            
            // 請求書挿入
            $stmt = $db->query("
                INSERT INTO invoices (
                    invoice_number, user_id, user_code, user_name, company_name,
                    invoice_date, due_date, period_start, period_end,
                    subtotal, tax_rate, tax_amount, total_amount,
                    invoice_type, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ", [
                $invoiceNumber,
                $user['id'],
                $user['user_code'], 
                $user['user_name'],
                $company['company_name'],
                date('Y-m-d'),
                date('Y-m-d', strtotime('+30 days')),
                date('Y-m-01'),
                date('Y-m-t'), 
                $company['subtotal'],
                10.00,
                $company['tax'],
                $company['total'],
                'company',
                'draft'
            ]);
            
            $created++;
        }
        
        $db->query("COMMIT");
        
        $result['invoices_created'] = $created;
        $result['status'] = 'success';
        
    } catch (Exception $e) {
        $db->query("ROLLBACK");
        $result['error'] = $e->getMessage();
        $result['status'] = 'error';
    }
    
    return $result;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>請求書生成テスト - シンプル確実版</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 20px; 
            background: #f5f5f5; 
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header { 
            background: #007bff; 
            color: white; 
            padding: 20px; 
            border-radius: 5px; 
            margin-bottom: 30px;
            text-align: center;
        }
        .section { 
            margin-bottom: 30px; 
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .section h3 {
            margin-top: 0;
            color: #333;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
        }
        .btn { 
            padding: 12px 30px; 
            margin: 10px 5px; 
            border: none; 
            border-radius: 5px; 
            cursor: pointer; 
            font-size: 16px;
            font-weight: bold;
            transition: all 0.3s;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        .btn-primary { background: #007bff; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-warning { background: #ffc107; color: black; }
        .btn-danger { background: #dc3545; color: white; }
        
        .result { 
            margin-top: 20px; 
            padding: 15px; 
            border-radius: 5px;
            min-height: 50px;
        }
        .loading { 
            background: #e3f2fd; 
            color: #1976d2; 
            text-align: center;
            font-size: 18px;
        }
        .success { 
            background: #d4edda; 
            color: #155724; 
            border-left: 5px solid #28a745;
        }
        .error { 
            background: #f8d7da; 
            color: #721c24; 
            border-left: 5px solid #dc3545;
        }
        .warning { 
            background: #fff3cd; 
            color: #856404; 
            border-left: 5px solid #ffc107;
        }
        
        .data-table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 15px; 
        }
        .data-table th, .data-table td { 
            padding: 10px; 
            border: 1px solid #ddd; 
            text-align: left; 
        }
        .data-table th { 
            background: #f8f9fa; 
            font-weight: bold; 
        }
        .data-table tr:nth-child(even) { 
            background: #f9f9f9; 
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }
        .stat-value {
            font-size: 2em;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .stat-label {
            font-size: 0.9em;
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🧪 請求書生成機能テスト v5.0</h1>
            <p>シンプル・確実・動作保証版</p>
        </div>

        <!-- データ修正セクション -->
        <div class="section">
            <h3>1. データ整合性修正</h3>
            <p>外部キーNULL問題を修正します。最初に実行してください。</p>
            <button class="btn btn-warning" onclick="testFunction('fix_data', 'fixResult')">
                🔧 データ修正実行
            </button>
            <div id="fixResult" class="result"></div>
        </div>

        <!-- データ確認セクション -->
        <div class="section">
            <h3>2. データ状況確認</h3>
            <p>現在のデータ状況を確認します。</p>
            <button class="btn btn-primary" onclick="testFunction('check_data', 'checkResult')">
                📊 データ確認実行  
            </button>
            <div id="checkResult" class="result"></div>
        </div>

        <!-- 注文データ確認 -->
        <div class="section">
            <h3>3. 注文データ確認</h3>
            <p>注文データのサンプルを表示します。</p>
            <button class="btn btn-success" onclick="testFunction('get_orders', 'ordersResult')">
                📋 注文データ取得
            </button>
            <div id="ordersResult" class="result"></div>
        </div>

        <!-- 請求書生成テスト -->
        <div class="section">
            <h3>4. 請求書生成テスト</h3>
            <p><strong>注意:</strong> 実際にinvoicesテーブルにデータを挿入します。</p>
            <button class="btn btn-danger" onclick="confirmAndRun('test_generate', 'generateResult')">
                🎯 請求書生成実行
            </button>
            <div id="generateResult" class="result"></div>
        </div>
    </div>

    <script>
        // テスト実行関数
        function testFunction(action, resultId) {
            console.log('Testing function:', action);
            showLoading(resultId);
            
            fetch('?action=' + action)
                .then(response => {
                    console.log('Response status:', response.status);
                    if (!response.ok) {
                        throw new Error('HTTP ' + response.status);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Response data:', data);
                    displayResult(data, resultId);
                })
                .catch(error => {
                    console.error('Error:', error);
                    showError(resultId, error.message);
                });
        }

        // 確認付き実行
        function confirmAndRun(action, resultId) {
            if (confirm('請求書データを実際に生成します。実行しますか？')) {
                testFunction(action, resultId);
            }
        }

        // ローディング表示
        function showLoading(resultId) {
            document.getElementById(resultId).innerHTML = 
                '<div class="loading">⏳ 処理中...</div>';
        }

        // エラー表示
        function showError(resultId, message) {
            document.getElementById(resultId).innerHTML = 
                '<div class="error">❌ エラー: ' + message + '</div>';
        }

        // 結果表示
        function displayResult(data, resultId) {
            let html = '';
            
            if (!data.success) {
                html = '<div class="error">❌ エラー: ' + (data.error || 'Unknown error') + '</div>';
            } else {
                switch (resultId) {
                    case 'fixResult':
                        html = displayFixResult(data.data);
                        break;
                    case 'checkResult':
                        html = displayCheckResult(data.data);
                        break;
                    case 'ordersResult':
                        html = displayOrdersResult(data.data);
                        break;
                    case 'generateResult':
                        html = displayGenerateResult(data.data);
                        break;
                    default:
                        html = '<div class="success">✅ 処理完了</div>';
                }
            }
            
            document.getElementById(resultId).innerHTML = html;
        }

        // データ修正結果表示
        function displayFixResult(data) {
            if (data.error) {
                return '<div class="error">❌ 修正エラー: ' + data.error + '</div>';
            }
            
            let html = '<div class="success">✅ データ修正完了</div>';
            html += '<div class="stats">';
            html += '<div class="stat-card"><div class="stat-value">' + (data.null_user_ids || 0) + '</div><div class="stat-label">NULL user_id</div></div>';
            html += '<div class="stat-card"><div class="stat-value">' + (data.fixed_user_ids || 0) + '</div><div class="stat-label">修正済み user_id</div></div>';
            html += '<div class="stat-card"><div class="stat-value">' + (data.null_company_ids || 0) + '</div><div class="stat-label">NULL company_id</div></div>';
            html += '<div class="stat-card"><div class="stat-value">' + (data.fixed_company_ids || 0) + '</div><div class="stat-label">修正済み company_id</div></div>';
            html += '</div>';
            
            return html;
        }

        // データ確認結果表示
        function displayCheckResult(data) {
            let html = '<div class="success">✅ データ確認完了</div>';
            
            // テーブル件数
            if (data.counts) {
                html += '<h4>📊 テーブル件数</h4>';
                html += '<div class="stats">';
                Object.keys(data.counts).forEach(table => {
                    html += '<div class="stat-card"><div class="stat-value">' + data.counts[table] + '</div><div class="stat-label">' + table + '</div></div>';
                });
                html += '</div>';
            }
            
            // 企業別集計
            if (data.companies && data.companies.length > 0) {
                html += '<h4>🏢 企業別集計</h4>';
                html += '<table class="data-table"><thead><tr><th>企業名</th><th>利用者数</th><th>注文件数</th><th>総額</th></tr></thead><tbody>';
                data.companies.forEach(company => {
                    html += '<tr><td>' + (company.company_name || '未設定') + '</td><td>' + (company.users || 0) + '</td><td>' + (company.orders || 0) + '</td><td>¥' + Number(company.total || 0).toLocaleString() + '</td></tr>';
                });
                html += '</tbody></table>';
            }
            
            return html;
        }

        // 注文データ結果表示
        function displayOrdersResult(data) {
            if (!data || data.length === 0) {
                return '<div class="warning">⚠️ 注文データがありません</div>';
            }
            
            let html = '<div class="success">✅ 注文データ取得完了 (' + data.length + '件)</div>';
            html += '<table class="data-table"><thead><tr><th>配達日</th><th>利用者</th><th>企業</th><th>商品</th><th>数量</th><th>単価</th><th>金額</th></tr></thead><tbody>';
            
            data.forEach(order => {
                html += '<tr>';
                html += '<td>' + order.delivery_date + '</td>';
                html += '<td>' + order.user_name + '</td>';
                html += '<td>' + (order.company_name || '-') + '</td>';
                html += '<td>' + order.product_name + '</td>';
                html += '<td>' + order.quantity + '</td>';
                html += '<td>¥' + Number(order.unit_price).toLocaleString() + '</td>';
                html += '<td>¥' + Number(order.total_amount).toLocaleString() + '</td>';
                html += '</tr>';
            });
            
            html += '</tbody></table>';
            return html;
        }

        // 請求書生成結果表示
        function displayGenerateResult(data) {
            let html = '';
            
            if (data.error) {
                html += '<div class="error">❌ 請求書生成エラー: ' + data.error + '</div>';
                return html;
            }
            
            if (data.status === 'success') {
                html += '<div class="success">🎉 請求書生成完了!</div>';
                html += '<div class="stats">';
                html += '<div class="stat-card"><div class="stat-value">' + (data.companies_found || 0) + '</div><div class="stat-label">対象企業数</div></div>';
                html += '<div class="stat-card"><div class="stat-value">' + (data.invoices_created || 0) + '</div><div class="stat-label">生成請求書数</div></div>';
                html += '</div>';
                
                if (data.fix_result) {
                    html += '<h4>🔧 事前データ修正</h4>';
                    html += '<p>user_id修正: ' + (data.fix_result.fixed_user_ids || 0) + '件, company_id修正: ' + (data.fix_result.fixed_company_ids || 0) + '件</p>';
                }
                
                html += '<div class="success" style="margin-top: 20px;"><h4>🚀 完了!</h4><p>請求書生成システムが正常に動作しました。次はPDF生成機能の実装です。</p></div>';
            } else {
                html += '<div class="error">❌ 請求書生成に失敗しました</div>';
            }
            
            return html;
        }

        // ページ読み込み時の初期化
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Invoice test tool v5.0 loaded');
        });
        
        // デバッグ用：ボタンクリック確認
        document.querySelectorAll('.btn').forEach(btn => {
            btn.addEventListener('click', function() {
                console.log('Button clicked:', this.textContent);
            });
        });
    </script>
</body>
</html>
