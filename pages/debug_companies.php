<?php
/**
 * 企業管理機能デバッグページ
 */

// エラー表示を有効化
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>企業管理機能デバッグ</h1>";

try {
    echo "<h2>1. ファイル読み込みテスト</h2>";
    
    echo "config/database.php: ";
    if (file_exists('../config/database.php')) {
        require_once '../config/database.php';
        echo "✅ 成功<br>";
    } else {
        echo "❌ ファイルが見つかりません<br>";
    }
    
    echo "classes/Database.php: ";
    if (file_exists('../classes/Database.php')) {
        require_once '../classes/Database.php';
        echo "✅ 成功<br>";
    } else {
        echo "❌ ファイルが見つかりません<br>";
    }
    
    echo "classes/SecurityHelper.php: ";
    if (file_exists('../classes/SecurityHelper.php')) {
        require_once '../classes/SecurityHelper.php';
        echo "✅ 成功<br>";
    } else {
        echo "❌ ファイルが見つかりません<br>";
    }
    
    echo "<h2>2. データベース接続テスト</h2>";
    
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    echo "データベース接続: ✅ 成功<br>";
    
    echo "<h2>3. テーブル存在確認</h2>";
    
    $tables = ['companies', 'departments', 'users', 'orders'];
    foreach ($tables as $table) {
        $stmt = $pdo->prepare("SHOW TABLES LIKE :table");
        $stmt->bindValue(':table', $table);
        $stmt->execute();
        $exists = $stmt->fetch() ? "✅ 存在" : "❌ 存在しない";
        echo "テーブル '{$table}': {$exists}<br>";
    }
    
    echo "<h2>4. 各テーブルのレコード数確認</h2>";
    
    foreach ($tables as $table) {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM {$table}");
            $stmt->execute();
            $count = $stmt->fetch()['count'];
            echo "テーブル '{$table}': {$count}件<br>";
        } catch (Exception $e) {
            echo "テーブル '{$table}': エラー - " . $e->getMessage() . "<br>";
        }
    }
    
    echo "<h2>5. 企業データ確認</h2>";
    
    $sql = "SELECT id, company_code, company_name FROM companies LIMIT 5";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($companies)) {
        echo "❌ 企業データが見つかりません<br>";
    } else {
        echo "✅ 企業データ（最初の5件）:<br>";
        foreach ($companies as $company) {
            echo "- ID: {$company['id']}, コード: {$company['company_code']}, 名前: {$company['company_name']}<br>";
        }
    }
    
    echo "<h2>6. 複雑なクエリテスト</h2>";
    
    $test_sql = "
        SELECT 
            c.id,
            c.company_code,
            c.company_name,
            COUNT(DISTINCT d.id) as department_count,
            COUNT(DISTINCT u.id) as user_count
        FROM companies c
        LEFT JOIN departments d ON c.id = d.company_id
        LEFT JOIN users u ON c.id = u.company_id
        GROUP BY c.id
        LIMIT 3
    ";
    
    try {
        $stmt = $pdo->prepare($test_sql);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "✅ 複雑なクエリ成功（3件）:<br>";
        foreach ($result as $row) {
            echo "- {$row['company_name']}: 部署{$row['department_count']}件, 利用者{$row['user_count']}件<br>";
        }
    } catch (Exception $e) {
        echo "❌ 複雑なクエリエラー: " . $e->getMessage() . "<br>";
    }
    
    echo "<h2>7. 問題のあるクエリ実行</h2>";
    
    // companies.phpと同じクエリを実行
    $where_conditions = [];
    $params = [];
    $page = 1;
    $per_page = 20;
    $offset = 0;
    
    $where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    $companies_sql = "
        SELECT 
            c.id,
            c.company_code,
            c.company_name,
            c.company_address,
            c.contact_person,
            c.contact_phone,
            c.contact_email,
            c.billing_method,
            c.is_active,
            c.created_at,
            -- 部署数
            COUNT(DISTINCT d.id) as department_count,
            -- 利用者数
            COUNT(DISTINCT u.id) as user_count,
            -- 期間内注文統計
            COALESCE(stats.order_count, 0) as period_order_count,
            COALESCE(stats.total_amount, 0) as period_total_amount,
            stats.last_order_date
        FROM companies c
        LEFT JOIN departments d ON c.id = d.company_id
        LEFT JOIN users u ON c.id = u.company_id
        LEFT JOIN (
            SELECT 
                company_id,
                COUNT(*) as order_count,
                SUM(total_amount) as total_amount,
                MAX(delivery_date) as last_order_date
            FROM orders 
            WHERE delivery_date BETWEEN :period_start AND :period_end
            GROUP BY company_id
        ) stats ON c.id = stats.company_id
        $where_clause
        GROUP BY c.id
        ORDER BY c.company_name
        LIMIT :limit OFFSET :offset
    ";
    
    $params['period_start'] = date('Y-m-01');
    $params['period_end'] = date('Y-m-t');
    $params['limit'] = $per_page;
    $params['offset'] = $offset;
    
    try {
        $stmt = $pdo->prepare($companies_sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "✅ 企業一覧クエリ成功: " . count($companies) . "件取得<br>";
        
        // 総件数取得テスト
        $count_sql = "
            SELECT COUNT(DISTINCT c.id) as total
            FROM companies c
            $where_clause
        ";
        $count_stmt = $pdo->prepare($count_sql);
        $count_stmt->execute();
        $total_companies = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        echo "✅ 総件数取得成功: {$total_companies}件<br>";
        
    } catch (Exception $e) {
        echo "❌ 企業一覧クエリエラー: " . $e->getMessage() . "<br>";
        echo "SQLエラー詳細: " . print_r($pdo->errorInfo(), true) . "<br>";
    }
    
} catch (Exception $e) {
    echo "<h2>❌ 重大なエラー</h2>";
    echo "エラーメッセージ: " . $e->getMessage() . "<br>";
    echo "ファイル: " . $e->getFile() . "<br>";
    echo "行: " . $e->getLine() . "<br>";
    echo "スタックトレース:<br><pre>" . $e->getTraceAsString() . "</pre>";
}
?>
