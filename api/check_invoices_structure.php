<?php
/**
 * invoicesテーブル構造確認ツール
 * 実際のテーブル構造を確認して仕様書との差異を特定
 */

// エラー表示を有効化
error_reporting(E_ALL);
ini_set('display_errors', 1);

// データベース接続情報
define('DB_HOST', 'mysql1086.xserver.jp');
define('DB_NAME', 'twinklemark_billing');
define('DB_USER', 'twinklemark_admin');
define('DB_PASS', 'Kiyoshi07171120');

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    
    echo "<h2>✅ データベース接続成功</h2>";
    
    // 1. invoicesテーブルの存在確認
    $stmt = $pdo->query("SHOW TABLES LIKE 'invoices'");
    $tableExists = $stmt->fetch();
    
    if (!$tableExists) {
        echo "<h3>❌ invoicesテーブルが存在しません</h3>";
        echo "<p>テーブルを作成する必要があります。</p>";
        exit;
    }
    
    echo "<h3>✅ invoicesテーブルが存在します</h3>";
    
    // 2. テーブル構造を取得
    echo "<h3>📋 現在のテーブル構造:</h3>";
    $stmt = $pdo->query("DESCRIBE invoices");
    $columns = $stmt->fetchAll();
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background-color: #4CAF50; color: white;'>";
    echo "<th>カラム名</th><th>型</th><th>NULL</th><th>キー</th><th>デフォルト</th><th>Extra</th>";
    echo "</tr>";
    
    $columnNames = [];
    foreach ($columns as $column) {
        $columnNames[] = $column['Field'];
        echo "<tr>";
        echo "<td><strong>{$column['Field']}</strong></td>";
        echo "<td>{$column['Type']}</td>";
        echo "<td>{$column['Null']}</td>";
        echo "<td>{$column['Key']}</td>";
        echo "<td>" . ($column['Default'] ?? 'NULL') . "</td>";
        echo "<td>{$column['Extra']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // 3. 仕様書との比較
    echo "<h3>🔍 仕様書との比較:</h3>";
    
    $requiredColumns = [
        'id' => 'INT AUTO_INCREMENT PRIMARY KEY',
        'invoice_number' => 'VARCHAR(50) UNIQUE',
        'company_id' => 'INT',
        'company_name' => 'VARCHAR(100)',
        'billing_period_start' => 'DATE',
        'billing_period_end' => 'DATE',
        'issue_date' => 'DATE',
        'due_date' => 'DATE',
        'subtotal' => 'DECIMAL(10,2)',
        'tax_amount' => 'DECIMAL(10,2)',
        'total_amount' => 'DECIMAL(10,2)',
        'status' => "ENUM('draft','issued','paid','cancelled')",
        'notes' => 'TEXT',
        'created_at' => 'TIMESTAMP',
        'updated_at' => 'TIMESTAMP'
    ];
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background-color: #2196F3; color: white;'>";
    echo "<th>カラム名</th><th>状態</th><th>備考</th>";
    echo "</tr>";
    
    $missingColumns = [];
    foreach ($requiredColumns as $colName => $colType) {
        $exists = in_array($colName, $columnNames);
        echo "<tr>";
        echo "<td><strong>{$colName}</strong></td>";
        if ($exists) {
            echo "<td style='color: green;'>✅ 存在</td>";
            echo "<td>{$colType}</td>";
        } else {
            echo "<td style='color: red;'>❌ 不足</td>";
            echo "<td style='color: red;'>{$colType} が必要</td>";
            $missingColumns[$colName] = $colType;
        }
        echo "</tr>";
    }
    echo "</table>";
    
    // 4. 不足カラムの追加SQL生成
    if (!empty($missingColumns)) {
        echo "<h3>🔧 必要な修正SQL:</h3>";
        echo "<pre style='background-color: #f5f5f5; padding: 15px; border-radius: 5px;'>";
        
        foreach ($missingColumns as $colName => $colType) {
            switch ($colName) {
                case 'company_id':
                    echo "ALTER TABLE invoices ADD COLUMN company_id INT AFTER invoice_number;\n";
                    echo "ALTER TABLE invoices ADD FOREIGN KEY (company_id) REFERENCES companies(id);\n\n";
                    break;
                case 'company_name':
                    echo "ALTER TABLE invoices ADD COLUMN company_name VARCHAR(100);\n\n";
                    break;
                case 'billing_period_start':
                    echo "ALTER TABLE invoices ADD COLUMN billing_period_start DATE;\n\n";
                    break;
                case 'billing_period_end':
                    echo "ALTER TABLE invoices ADD COLUMN billing_period_end DATE;\n\n";
                    break;
                case 'issue_date':
                    echo "ALTER TABLE invoices ADD COLUMN issue_date DATE;\n\n";
                    break;
                case 'due_date':
                    echo "ALTER TABLE invoices ADD COLUMN due_date DATE;\n\n";
                    break;
                case 'subtotal':
                    echo "ALTER TABLE invoices ADD COLUMN subtotal DECIMAL(10,2) DEFAULT 0.00;\n\n";
                    break;
                case 'tax_amount':
                    echo "ALTER TABLE invoices ADD COLUMN tax_amount DECIMAL(10,2) DEFAULT 0.00;\n\n";
                    break;
                case 'status':
                    echo "ALTER TABLE invoices ADD COLUMN status ENUM('draft','issued','paid','cancelled') DEFAULT 'draft';\n\n";
                    break;
                case 'notes':
                    echo "ALTER TABLE invoices ADD COLUMN notes TEXT;\n\n";
                    break;
            }
        }
        
        echo "</pre>";
    }
    
    // 5. データ件数確認
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM invoices");
    $count = $stmt->fetch();
    echo "<h3>📊 データ件数: {$count['count']}件</h3>";
    
    if ($count['count'] > 0) {
        echo "<h3>📋 最新レコード（最大5件）:</h3>";
        $stmt = $pdo->query("SELECT * FROM invoices ORDER BY id DESC LIMIT 5");
        $records = $stmt->fetchAll();
        
        echo "<pre style='background-color: #f5f5f5; padding: 15px; border-radius: 5px;'>";
        print_r($records);
        echo "</pre>";
    }
    
} catch (PDOException $e) {
    echo "<h3 style='color: red;'>❌ エラー発生:</h3>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
