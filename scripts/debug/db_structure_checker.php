<?php
/**
 * データベース構造確認ツール
 * 作成済みテーブルの詳細情報を取得して整合性をチェック
 */

// データベース接続設定（環境に応じて変更）
$host = 'localhost';
$dbname = 'bentosystem_local';  // 実際のDB名に変更
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h1>データベース構造確認レポート</h1>\n";
    echo "<p>データベース: $dbname</p>\n";
    echo "<hr>\n";
    
    // 対象テーブル一覧
    $tables = ['companies', 'departments', 'users', 'orders'];
    
    foreach ($tables as $table) {
        echo "<h2>テーブル: $table</h2>\n";
        
        // テーブル存在確認
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        
        if ($stmt->rowCount() == 0) {
            echo "<p style='color: red;'>❌ テーブル '$table' が存在しません</p>\n";
            continue;
        }
        
        echo "<p style='color: green;'>✅ テーブル存在確認: OK</p>\n";
        
        // カラム詳細情報取得
        $stmt = $pdo->prepare("DESCRIBE $table");
        $stmt->execute();
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h3>カラム一覧 (総数: " . count($columns) . ")</h3>\n";
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
        echo "<tr style='background-color: #f0f0f0;'>";
        echo "<th>No.</th><th>カラム名</th><th>型</th><th>NULL</th><th>キー</th><th>デフォルト</th><th>Extra</th></tr>\n";
        
        $columnCount = 1;
        foreach ($columns as $column) {
            echo "<tr>";
            echo "<td>$columnCount</td>";
            echo "<td><strong>{$column['Field']}</strong></td>";
            echo "<td>{$column['Type']}</td>";
            echo "<td>{$column['Null']}</td>";
            echo "<td>{$column['Key']}</td>";
            echo "<td>" . ($column['Default'] ?? 'NULL') . "</td>";
            echo "<td>{$column['Extra']}</td>";
            echo "</tr>\n";
            $columnCount++;
        }
        echo "</table>\n";
        
        // インデックス情報取得
        echo "<h3>インデックス情報</h3>\n";
        $stmt = $pdo->prepare("SHOW INDEX FROM $table");
        $stmt->execute();
        $indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($indexes) > 0) {
            echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
            echo "<tr style='background-color: #f0f0f0;'>";
            echo "<th>キー名</th><th>カラム名</th><th>ユニーク</th><th>種類</th></tr>\n";
            
            foreach ($indexes as $index) {
                echo "<tr>";
                echo "<td>{$index['Key_name']}</td>";
                echo "<td>{$index['Column_name']}</td>";
                echo "<td>" . ($index['Non_unique'] == 0 ? 'YES' : 'NO') . "</td>";
                echo "<td>{$index['Index_type']}</td>";
                echo "</tr>\n";
            }
            echo "</table>\n";
        } else {
            echo "<p>インデックスなし</p>\n";
        }
        
        // 外部キー制約情報
        echo "<h3>外部キー制約</h3>\n";
        $stmt = $pdo->prepare("
            SELECT 
                CONSTRAINT_NAME,
                COLUMN_NAME,
                REFERENCED_TABLE_NAME,
                REFERENCED_COLUMN_NAME
            FROM information_schema.KEY_COLUMN_USAGE 
            WHERE TABLE_SCHEMA = ? 
                AND TABLE_NAME = ? 
                AND REFERENCED_TABLE_NAME IS NOT NULL
        ");
        $stmt->execute([$dbname, $table]);
        $foreignKeys = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($foreignKeys) > 0) {
            echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
            echo "<tr style='background-color: #f0f0f0;'>";
            echo "<th>制約名</th><th>カラム名</th><th>参照テーブル</th><th>参照カラム</th></tr>\n";
            
            foreach ($foreignKeys as $fk) {
                echo "<tr>";
                echo "<td>{$fk['CONSTRAINT_NAME']}</td>";
                echo "<td>{$fk['COLUMN_NAME']}</td>";
                echo "<td>{$fk['REFERENCED_TABLE_NAME']}</td>";
                echo "<td>{$fk['REFERENCED_COLUMN_NAME']}</td>";
                echo "</tr>\n";
            }
            echo "</table>\n";
        } else {
            echo "<p>外部キー制約なし</p>\n";
        }
        
        echo "<hr>\n";
    }
    
    // 整合性チェック項目
    echo "<h2>整合性チェック結果</h2>\n";
    
    // 1. カラム名の重複チェック
    echo "<h3>1. カラム名重複チェック</h3>\n";
    checkColumnConsistency($pdo, $dbname);
    
    // 2. 外部キー整合性チェック
    echo "<h3>2. 外部キー整合性チェック</h3>\n";
    checkForeignKeyConsistency($pdo, $dbname);
    
    // 3. データ型整合性チェック
    echo "<h3>3. データ型整合性チェック</h3>\n";
    checkDataTypeConsistency($pdo, $dbname);
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ データベース接続エラー: " . $e->getMessage() . "</p>\n";
}

function checkColumnConsistency($pdo, $dbname) {
    // 同名カラムの型チェック
    $stmt = $pdo->prepare("
        SELECT 
            COLUMN_NAME,
            TABLE_NAME,
            DATA_TYPE,
            CHARACTER_MAXIMUM_LENGTH,
            IS_NULLABLE,
            COLUMN_DEFAULT
        FROM information_schema.COLUMNS 
        WHERE TABLE_SCHEMA = ? 
            AND TABLE_NAME IN ('companies', 'departments', 'users', 'orders')
        ORDER BY COLUMN_NAME, TABLE_NAME
    ");
    $stmt->execute([$dbname]);
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $columnGroups = [];
    foreach ($columns as $col) {
        $columnGroups[$col['COLUMN_NAME']][] = $col;
    }
    
    foreach ($columnGroups as $columnName => $tables) {
        if (count($tables) > 1) {
            echo "<h4>カラム: $columnName</h4>\n";
            echo "<table border='1' style='border-collapse: collapse;'>\n";
            echo "<tr style='background-color: #f0f0f0;'>";
            echo "<th>テーブル</th><th>データ型</th><th>長さ</th><th>NULL可</th><th>デフォルト</th></tr>\n";
            
            $consistent = true;
            $firstType = null;
            
            foreach ($tables as $table) {
                $typeInfo = $table['DATA_TYPE'];
                if ($table['CHARACTER_MAXIMUM_LENGTH']) {
                    $typeInfo .= "({$table['CHARACTER_MAXIMUM_LENGTH']})";
                }
                
                if ($firstType === null) {
                    $firstType = $typeInfo;
                } elseif ($firstType !== $typeInfo) {
                    $consistent = false;
                }
                
                $rowStyle = $consistent ? '' : ' style="background-color: #ffcccc;"';
                echo "<tr$rowStyle>";
                echo "<td>{$table['TABLE_NAME']}</td>";
                echo "<td>$typeInfo</td>";
                echo "<td>" . ($table['CHARACTER_MAXIMUM_LENGTH'] ?? '-') . "</td>";
                echo "<td>{$table['IS_NULLABLE']}</td>";
                echo "<td>" . ($table['COLUMN_DEFAULT'] ?? 'NULL') . "</td>";
                echo "</tr>\n";
            }
            echo "</table>\n";
            
            if (!$consistent) {
                echo "<p style='color: red;'>⚠️ データ型の不整合があります</p>\n";
            } else {
                echo "<p style='color: green;'>✅ データ型は整合しています</p>\n";
            }
        }
    }
}

function checkForeignKeyConsistency($pdo, $dbname) {
    echo "<p>外部キー制約の参照整合性をチェックしています...</p>\n";
    
    // 基本的な外部キー関係をチェック
    $expectedFKs = [
        'departments' => [
            'company_id' => 'companies.id'
        ],
        'users' => [
            'company_id' => 'companies.id',
            'department_id' => 'departments.id'
        ],
        'orders' => [
            'user_id' => 'users.id',
            'company_id' => 'companies.id',
            'department_id' => 'departments.id'
        ]
    ];
    
    foreach ($expectedFKs as $table => $fks) {
        echo "<h4>テーブル: $table</h4>\n";
        foreach ($fks as $column => $reference) {
            // カラムが存在するかチェック
            $stmt = $pdo->prepare("
                SELECT COLUMN_NAME 
                FROM information_schema.COLUMNS 
                WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?
            ");
            $stmt->execute([$dbname, $table, $column]);
            
            if ($stmt->rowCount() > 0) {
                echo "<p style='color: green;'>✅ $table.$column カラム存在</p>\n";
            } else {
                echo "<p style='color: red;'>❌ $table.$column カラムが存在しません</p>\n";
            }
        }
    }
}

function checkDataTypeConsistency($pdo, $dbname) {
    echo "<p>ID関連カラムのデータ型整合性をチェックしています...</p>\n";
    
    // ID系カラムの型チェック
    $stmt = $pdo->prepare("
        SELECT 
            TABLE_NAME,
            COLUMN_NAME,
            DATA_TYPE,
            NUMERIC_PRECISION
        FROM information_schema.COLUMNS 
        WHERE TABLE_SCHEMA = ? 
            AND TABLE_NAME IN ('companies', 'departments', 'users', 'orders')
            AND (COLUMN_NAME LIKE '%_id' OR COLUMN_NAME = 'id')
        ORDER BY COLUMN_NAME, TABLE_NAME
    ");
    $stmt->execute([$dbname]);
    $idColumns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='border-collapse: collapse;'>\n";
    echo "<tr style='background-color: #f0f0f0;'>";
    echo "<th>テーブル</th><th>カラム名</th><th>データ型</th><th>精度</th></tr>\n";
    
    foreach ($idColumns as $col) {
        $consistent = ($col['DATA_TYPE'] === 'int' && $col['NUMERIC_PRECISION'] == 11);
        $rowStyle = $consistent ? ' style="color: green;"' : ' style="color: red;"';
        
        echo "<tr$rowStyle>";
        echo "<td>{$col['TABLE_NAME']}</td>";
        echo "<td>{$col['COLUMN_NAME']}</td>";
        echo "<td>{$col['DATA_TYPE']}</td>";
        echo "<td>{$col['NUMERIC_PRECISION']}</td>";
        echo "</tr>\n";
    }
    echo "</table>\n";
}
?>
