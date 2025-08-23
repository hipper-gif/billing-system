<?php
/**
 * カラム分析・比較ツール
 * 設計仕様と実際のテーブル構造を比較分析
 */

// 設計仕様書での期待されるカラム定義
$expectedColumns = [
    'companies' => [
        'id' => 'INT AUTO_INCREMENT PRIMARY KEY',
        'company_code' => 'VARCHAR(50) UNIQUE NOT NULL',
        'company_name' => 'VARCHAR(100) NOT NULL',
        'address' => 'TEXT',
        'phone' => 'VARCHAR(20)',
        'email' => 'VARCHAR(255)',
        'contact_person' => 'VARCHAR(100)',
        'payment_method' => 'ENUM',
        'payment_cycle' => 'ENUM',
        'payment_due_days' => 'INT DEFAULT 30',
        'is_active' => 'BOOLEAN DEFAULT TRUE',
        'created_at' => 'TIMESTAMP',
        'updated_at' => 'TIMESTAMP'
    ],
    'departments' => [
        'id' => 'INT AUTO_INCREMENT PRIMARY KEY',
        'company_id' => 'INT NOT NULL',
        'department_code' => 'VARCHAR(50) NOT NULL',
        'department_name' => 'VARCHAR(100) NOT NULL',
        'manager_name' => 'VARCHAR(100)',
        'delivery_location' => 'TEXT',
        'delivery_time_default' => 'TIME',
        'special_instructions' => 'TEXT',
        'is_active' => 'BOOLEAN DEFAULT TRUE',
        'created_at' => 'TIMESTAMP',
        'updated_at' => 'TIMESTAMP'
    ],
    'users' => [
        'id' => 'INT AUTO_INCREMENT PRIMARY KEY',
        'user_code' => 'VARCHAR(50) UNIQUE NOT NULL',
        'user_name' => 'VARCHAR(100) NOT NULL',
        'company_id' => 'INT NOT NULL',
        'department_id' => 'INT',
        'employee_type_code' => 'VARCHAR(20)',
        'employee_type_name' => 'VARCHAR(50)',
        'email' => 'VARCHAR(255)',
        'phone' => 'VARCHAR(20)',
        'payment_method' => 'ENUM',
        'is_active' => 'BOOLEAN DEFAULT TRUE',
        'created_at' => 'TIMESTAMP',
        'updated_at' => 'TIMESTAMP'
    ],
    'orders' => [
        'id' => 'INT AUTO_INCREMENT PRIMARY KEY',
        'order_date' => 'DATE NOT NULL',
        'delivery_date' => 'DATE NOT NULL',
        'user_id' => 'INT',
        'user_code' => 'VARCHAR(50) NOT NULL',
        'user_name' => 'VARCHAR(100) NOT NULL',
        'company_id' => 'INT',
        'product_id' => 'INT',
        'product_code' => 'VARCHAR(50) NOT NULL',
        'product_name' => 'VARCHAR(200) NOT NULL',
        'quantity' => 'INT NOT NULL DEFAULT 1',
        'unit_price' => 'DECIMAL(10,2) NOT NULL',
        'total_amount' => 'DECIMAL(10,2) NOT NULL',
        'created_at' => 'TIMESTAMP',
        'updated_at' => 'TIMESTAMP'
    ]
];

// データベース接続設定
$host = 'localhost';
$dbname = 'bentosystem_local';  // 実際のDB名に変更
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h1>カラム分析・比較レポート</h1>\n";
    echo "<p>データベース: $dbname</p>\n";
    echo "<hr>\n";
    
    foreach ($expectedColumns as $tableName => $expectedCols) {
        echo "<h2>テーブル: $tableName</h2>\n";
        
        // 実際のカラム情報を取得
        try {
            $stmt = $pdo->prepare("DESCRIBE $tableName");
            $stmt->execute();
            $actualColumns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $actualColMap = [];
            foreach ($actualColumns as $col) {
                $actualColMap[$col['Field']] = $col;
            }
            
            echo "<h3>カラム比較結果</h3>\n";
            echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
            echo "<tr style='background-color: #f0f0f0;'>";
            echo "<th>カラム名</th><th>期待値</th><th>実際の値</th><th>状態</th><th>備考</th></tr>\n";
            
            // 期待されるカラムと実際のカラムを比較
            foreach ($expectedCols as $colName => $expectedType) {
                $status = '';
                $comment = '';
                $rowStyle = '';
                
                if (isset($actualColMap[$colName])) {
                    $actual = $actualColMap[$colName];
                    $actualType = $actual['Type'];
                    
                    // 基本的な型チェック
                    $typeMatch = checkTypeCompatibility($expectedType, $actualType);
                    
                    if ($typeMatch) {
                        $status = '✅ OK';
                        $rowStyle = ' style="background-color: #e8f5e8;"';
                    } else {
                        $status = '⚠️ 型不整合';
                        $rowStyle = ' style="background-color: #fff2cc;"';
                        $comment = "期待型と実際の型が異なります";
                    }
                    
                    echo "<tr$rowStyle>";
                    echo "<td><strong>$colName</strong></td>";
                    echo "<td>$expectedType</td>";
                    echo "<td>$actualType</td>";
                    echo "<td>$status</td>";
                    echo "<td>$comment</td>";
                    echo "</tr>\n";
                    
                } else {
                    $status = '❌ 不足';
                    $rowStyle = ' style="background-color: #ffcccc;"';
                    
                    echo "<tr$rowStyle>";
                    echo "<td><strong>$colName</strong></td>";
                    echo "<td>$expectedType</td>";
                    echo "<td>-</td>";
                    echo "<td>$status</td>";
                    echo "<td>カラムが存在しません</td>";
                    echo "</tr>\n";
                }
            }
            
            // 実際には存在するが期待値にないカラム（追加カラム）
            echo "<tr style='background-color: #f9f9f9;'>";
            echo "<td colspan='5'><strong>追加で存在するカラム</strong></td>";
            echo "</tr>\n";
            
            foreach ($actualColMap as $colName => $colInfo) {
                if (!isset($expectedCols[$colName])) {
                    echo "<tr style='background-color: #e6f3ff;'>";
                    echo "<td><strong>$colName</strong></td>";
                    echo "<td>-</td>";
                    echo "<td>{$colInfo['Type']}</td>";
                    echo "<td>➕ 追加</td>";
                    echo "<td>設計仕様にない追加カラム</td>";
                    echo "</tr>\n";
                }
            }
            
            echo "</table>\n";
            
            // 統計情報
            $totalExpected = count($expectedCols);
            $totalActual = count($actualColMap);
            $matching = 0;
            $missing = 0;
            $extra = 0;
            
            foreach ($expectedCols as $colName => $expectedType) {
                if (isset($actualColMap[$colName])) {
                    $matching++;
                } else {
                    $missing++;
                }
            }
            
            foreach ($actualColMap as $colName => $colInfo) {
                if (!isset($expectedCols[$colName])) {
                    $extra++;
                }
            }
            
            echo "<h3>統計サマリー</h3>\n";
            echo "<ul>\n";
            echo "<li><strong>期待カラム数:</strong> $totalExpected</li>\n";
            echo "<li><strong>実際カラム数:</strong> $totalActual</li>\n";
            echo "<li><strong>一致カラム数:</strong> <span style='color: green;'>$matching</span></li>\n";
            echo "<li><strong>不足カラム数:</strong> <span style='color: red;'>$missing</span></li>\n";
            echo "<li><strong>追加カラム数:</strong> <span style='color: blue;'>$extra</span></li>\n";
            echo "</ul>\n";
            
        } catch (PDOException $e) {
            echo "<p style='color: red;'>❌ テーブル '$tableName' の情報取得エラー: " . $e->getMessage() . "</p>\n";
        }
        
        echo "<hr>\n";
    }
    
    // 全体的な問題点の分析
    echo "<h2>全体分析結果</h2>\n";
    analyzeOverallIssues($pdo, $dbname);
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ データベース接続エラー: " . $e->getMessage() . "</p>\n";
}

function checkTypeCompatibility($expected, $actual) {
    // 簡単な型チェック（より詳細な実装が可能）
    $expected = strtolower($expected);
    $actual = strtolower($actual);
    
    // INT系の チェック
    if (strpos($expected, 'int') !== false && strpos($actual, 'int') !== false) {
        return true;
    }
    
    // VARCHAR系のチェック
    if (strpos($expected, 'varchar') !== false && strpos($actual, 'varchar') !== false) {
        return true;
    }
    
    // TEXT系のチェック
    if (strpos($expected, 'text') !== false && strpos($actual, 'text') !== false) {
        return true;
    }
    
    // TIMESTAMP系のチェック
    if (strpos($expected, 'timestamp') !== false && strpos($actual, 'timestamp') !== false) {
        return true;
    }
    
    // DECIMAL系のチェック
    if (strpos($expected, 'decimal') !== false && strpos($actual, 'decimal') !== false) {
        return true;
    }
    
    // ENUM系のチェック
    if (strpos($expected, 'enum') !== false && strpos($actual, 'enum') !== false) {
        return true;
    }
    
    // DATE系のチェック
    if (strpos($expected, 'date') !== false && strpos($actual, 'date') !== false) {
        return true;
    }
    
    // TIME系のチェック
    if (strpos($expected, 'time') !== false && strpos($actual, 'time') !== false) {
        return true;
    }
    
    // BOOLEAN/TINYINT系のチェック
    if ((strpos($expected, 'boolean') !== false || strpos($expected, 'tinyint') !== false) && 
        strpos($actual, 'tinyint') !== false) {
        return true;
    }
    
    return false;
}

function analyzeOverallIssues($pdo, $dbname) {
    echo "<h3>データ整合性の問題点</h3>\n";
    
    // 1. 重複カラムの分析
    echo "<h4>1. 重複・類似カラム名の検出</h4>\n";
    $duplicateColumns = [
        'address系' => ['address', 'company_address', 'billing_address'],
        'name系' => ['user_name', 'company_name', 'department_name', 'product_name'],
        'code系' => ['user_code', 'company_code', 'department_code', 'product_code'],
        'contact系' => ['contact_person', 'contact_phone', 'contact_email', 'billing_contact_person']
    ];
    
    foreach ($duplicateColumns as $group => $columns) {
        echo "<p><strong>$group:</strong> ";
        $found = [];
        foreach ($columns as $col) {
            if (checkColumnExists($pdo, $dbname, $col)) {
                $found[] = $col;
            }
        }
        if (count($found) > 1) {
            echo "<span style='color: orange;'>" . implode(', ', $found) . " (要整理)</span>";
        } else {
            echo "<span style='color: green;'>" . implode(', ', $found) . " (OK)</span>";
        }
        echo "</p>\n";
    }
    
    // 2. 必須リレーションの確認
    echo "<h4>2. 必須リレーション確認</h4>\n";
    $requiredRelations = [
        'departments.company_id → companies.id',
        'users.company_id → companies.id',
        'users.department_id → departments.id',
        'orders.user_id → users.id'
    ];
    
    foreach ($requiredRelations as $relation) {
        echo "<p>$relation: ";
        // 実際のチェック処理はより複雑になるが、ここでは簡略化
        echo "<span style='color: blue;'>要確認</span></p>\n";
    }
}

function checkColumnExists($pdo, $dbname, $columnName) {
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as cnt
            FROM information_schema.COLUMNS 
            WHERE TABLE_SCHEMA = ? 
                AND COLUMN_NAME = ?
                AND TABLE_NAME IN ('companies', 'departments', 'users', 'orders')
        ");
        $stmt->execute([$dbname, $columnName]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['cnt'] > 0;
    } catch (PDOException $e) {
        return false;
    }
}
?>
