<?php
/**
 * テーブル構造確認
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../config/database.php';
require_once '../classes/Database.php';

echo "<h1>テーブル構造確認</h1>";

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    
    $tables = ['companies', 'departments', 'users', 'orders'];
    
    foreach ($tables as $table) {
        echo "<h2>テーブル: {$table}</h2>";
        
        try {
            $stmt = $pdo->query("DESCRIBE {$table}");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "<table border='1' cellpadding='5'>";
            echo "<tr><th>カラム名</th><th>型</th><th>NULL</th><th>キー</th><th>デフォルト</th></tr>";
            
            foreach ($columns as $column) {
                echo "<tr>";
                echo "<td>{$column['Field']}</td>";
                echo "<td>{$column['Type']}</td>";
                echo "<td>{$column['Null']}</td>";
                echo "<td>{$column['Key']}</td>";
                echo "<td>{$column['Default']}</td>";
                echo "</tr>";
            }
            echo "</table><br>";
            
        } catch (Exception $e) {
            echo "エラー: " . $e->getMessage() . "<br><br>";
        }
    }
    
    echo "<h2>必要カラムチェック</h2>";
    
    // companiesテーブルに必要なカラム
    $required_company_columns = [
        'id', 'company_code', 'company_name', 'company_address', 
        'contact_person', 'contact_phone', 'contact_email', 
        'billing_method', 'is_active', 'created_at', 'updated_at'
    ];
    
    $stmt = $pdo->query("DESCRIBE companies");
    $existing_columns = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'Field');
    
    echo "<h3>companiesテーブル:</h3>";
    foreach ($required_company_columns as $required) {
        $exists = in_array($required, $existing_columns);
        $status = $exists ? "✅" : "❌";
        echo "{$status} {$required}<br>";
    }
    
    echo "<h3>不足カラムの追加SQL:</h3>";
    $missing_columns = array_diff($required_company_columns, $existing_columns);
    
    if (empty($missing_columns)) {
        echo "✅ すべてのカラムが存在します<br>";
    } else {
        echo "<pre>";
        echo "-- 以下のSQLを実行してカラムを追加してください:\n";
        echo "ALTER TABLE companies\n";
        
        $alter_statements = [];
        foreach ($missing_columns as $column) {
            switch ($column) {
                case 'company_address':
                    $alter_statements[] = "ADD COLUMN company_address VARCHAR(500) COMMENT '企業住所'";
                    break;
                case 'contact_person':
                    $alter_statements[] = "ADD COLUMN contact_person VARCHAR(100) COMMENT '担当者名'";
                    break;
                case 'contact_phone':
                    $alter_statements[] = "ADD COLUMN contact_phone VARCHAR(20) COMMENT '電話番号'";
                    break;
                case 'contact_email':
                    $alter_statements[] = "ADD COLUMN contact_email VARCHAR(255) COMMENT 'メールアドレス'";
                    break;
                case 'billing_method':
                    $alter_statements[] = "ADD COLUMN billing_method ENUM('company', 'department', 'individual', 'mixed') DEFAULT 'company' COMMENT '請求方法'";
                    break;
                case 'is_active':
                    $alter_statements[] = "ADD COLUMN is_active BOOLEAN DEFAULT TRUE COMMENT 'アクティブフラグ'";
                    break;
                case 'created_at':
                    $alter_statements[] = "ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時'";
                    break;
                case 'updated_at':
                    $alter_statements[] = "ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時'";
                    break;
            }
        }
        
        echo implode(",\n", $alter_statements) . ";";
        echo "</pre>";
    }
    
} catch (Exception $e) {
    echo "エラー: " . $e->getMessage();
}
?>
