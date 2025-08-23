<?php
/**
 * 安全な確認・検証ツールセット
 * データベースを変更せずに現在の状況を詳細分析
 */

// データベース接続設定
$config = [
    'host' => 'localhost',
    'dbname' => 'twinklemark_billing',  // 実際のDB名に変更
    'username' => 'twinklemark_bill',
    'password' => 'Smiley2525'
];

try {
    $pdo = new PDO("mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4", 
                   $config['username'], $config['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h1>🔍 データベース安全確認レポート</h1>\n";
    echo "<p><strong>重要:</strong> このツールはデータベースを変更しません。確認のみ実行します。</p>\n";
    echo "<hr>\n";
    
    // 1. 基本統計情報
    echo "<h2>📊 基本統計情報</h2>\n";
    displayBasicStats($pdo);
    
    // 2. カラム使用率分析
    echo "<h2>📈 カラム使用率分析</h2>\n";
    analyzeColumnUsage($pdo);
    
    // 3. データ整合性チェック
    echo "<h2>🔗 データ整合性チェック</h2>\n";
    checkDataIntegrity($pdo);
    
    // 4. 削除候補カラムの影響分析
    echo "<h2>⚠️ 削除候補カラムの影響分析</h2>\n";
    analyzeDeletionImpact($pdo);
    
    // 5. 請求書生成に必要なデータの確認
    echo "<h2>🧾 請求書生成必須データ確認</h2>\n";
    checkInvoiceRequiredData($pdo);
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ エラー: " . $e->getMessage() . "</p>\n";
}

function displayBasicStats($pdo) {
    $tables = ['companies', 'departments', 'users', 'orders'];
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
    echo "<tr style='background-color: #f0f0f0;'><th>テーブル</th><th>レコード数</th><th>カラム数</th><th>最終更新</th></tr>\n";
    
    foreach ($tables as $table) {
        try {
            // レコード数取得
            $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM $table");
            $count = $stmt->fetch(PDO::FETCH_ASSOC)['cnt'];
            
            // カラム数取得
            $stmt = $pdo->query("DESCRIBE $table");
            $columnCount = $stmt->rowCount();
            
            // 最終更新日取得
            $stmt = $pdo->prepare("
                SELECT UPDATE_TIME 
                FROM information_schema.tables 
                WHERE table_schema = DATABASE() AND table_name = ?
            ");
            $stmt->execute([$table]);
            $updateTime = $stmt->fetch(PDO::FETCH_ASSOC)['UPDATE_TIME'] ?? 'N/A';
            
            echo "<tr>";
            echo "<td><strong>$table</strong></td>";
            echo "<td style='text-align: right;'>" . number_format($count) . "</td>";
            echo "<td style='text-align: right;'>$columnCount</td>";
            echo "<td>$updateTime</td>";
            echo "</tr>\n";
            
        } catch (Exception $e) {
            echo "<tr style='color: red;'>";
            echo "<td><strong>$table</strong></td>";
            echo "<td colspan='3'>エラー: " . $e->getMessage() . "</td>";
            echo "</tr>\n";
        }
    }
    echo "</table>\n";
}

function analyzeColumnUsage($pdo) {
    echo "<h3>NULL値の多いカラム（削除候補）</h3>\n";
    
    $tables = ['companies', 'departments', 'users', 'orders'];
    
    foreach ($tables as $table) {
        echo "<h4>テーブル: $table</h4>\n";
        
        try {
            $stmt = $pdo->query("DESCRIBE $table");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
            echo "<tr style='background-color: #f0f0f0;'>";
            echo "<th>カラム名</th><th>総レコード</th><th>NULL数</th><th>使用率</th><th>削除可否</th></tr>\n";
            
            $totalRecords = $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
            
            foreach ($columns as $column) {
                $colName = $column['Field'];
                
                // NULL数カウント
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM $table WHERE $colName IS NULL OR $colName = ''");
                $stmt->execute();
                $nullCount = $stmt->fetchColumn();
                
                $usage = $totalRecords > 0 ? (($totalRecords - $nullCount) / $totalRecords * 100) : 0;
                
                // 削除可否判定
                $deletionSafety = judgeDeletionSafety($table, $colName, $usage);
                
                $rowStyle = '';
                if ($usage < 10) {
                    $rowStyle = ' style="background-color: #ffcccc;"'; // 赤：削除候補
                } elseif ($usage < 50) {
                    $rowStyle = ' style="background-color: #fff2cc;"'; // 黄：要検討
                } else {
                    $rowStyle = ' style="background-color: #e8f5e8;"'; // 緑：重要
                }
                
                echo "<tr$rowStyle>";
                echo "<td><strong>$colName</strong></td>";
                echo "<td style='text-align: right;'>" . number_format($totalRecords) . "</td>";
                echo "<td style='text-align: right;'>" . number_format($nullCount) . "</td>";
                echo "<td style='text-align: right;'>" . number_format($usage, 1) . "%</td>";
                echo "<td>$deletionSafety</td>";
                echo "</tr>\n";
            }
            echo "</table>\n";
            
        } catch (Exception $e) {
            echo "<p style='color: red;'>エラー: " . $e->getMessage() . "</p>\n";
        }
        
        echo "<br>\n";
    }
}

function judgeDeletionSafety($table, $column, $usage) {
    // 絶対に削除してはいけないカラム
    $critical = ['id', 'created_at', 'updated_at'];
    if (in_array($column, $critical)) {
        return '❌ 必須';
    }
    
    // 請求書生成に必要なカラム
    $invoiceRequired = [
        'companies' => ['company_code', 'company_name', 'billing_method', 'billing_contact_person', 'billing_email'],
        'users' => ['user_code', 'user_name', 'company_name', 'payment_method'],
        'orders' => ['user_name', 'company_name', 'product_name', 'quantity', 'unit_price', 'total_amount'],
        'departments' => ['department_code', 'department_name']
    ];
    
    if (isset($invoiceRequired[$table]) && in_array($column, $invoiceRequired[$table])) {
        return '🧾 請求書必須';
    }
    
    // 外部キー
    if (strpos($column, '_id') !== false && $column !== 'id') {
        return '🔗 外部キー';
    }
    
    // 使用率による判定
    if ($usage < 5) {
        return '✅ 削除可能';
    } elseif ($usage < 20) {
        return '⚠️ 要検討';
    } else {
        return '⭐ 重要';
    }
}

function checkDataIntegrity($pdo) {
    $checks = [];
    
    // 1. 外部キー整合性
    echo "<h3>外部キー整合性チェック</h3>\n";
    
    $fkChecks = [
        'departments.company_id → companies.id' => 
            "SELECT COUNT(*) FROM departments d LEFT JOIN companies c ON d.company_id = c.id WHERE d.company_id IS NOT NULL AND c.id IS NULL",
        'users.company_id → companies.id' => 
            "SELECT COUNT(*) FROM users u LEFT JOIN companies c ON u.company_id = c.id WHERE u.company_id IS NOT NULL AND c.id IS NULL",
        'users.department_id → departments.id' => 
            "SELECT COUNT(*) FROM users u LEFT JOIN departments d ON u.department_id = d.id WHERE u.department_id IS NOT NULL AND d.id IS NULL",
        'orders.user_id → users.id' => 
            "SELECT COUNT(*) FROM orders o LEFT JOIN users u ON o.user_id = u.id WHERE o.user_id IS NOT NULL AND u.id IS NULL"
    ];
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
    echo "<tr style='background-color: #f0f0f0;'><th>外部キー関係</th><th>不整合件数</th><th>状態</th></tr>\n";
    
    foreach ($fkChecks as $description => $query) {
        try {
            $errorCount = $pdo->query($query)->fetchColumn();
            $status = $errorCount > 0 ? '❌ 要修正' : '✅ OK';
            $rowStyle = $errorCount > 0 ? ' style="background-color: #ffcccc;"' : '';
            
            echo "<tr$rowStyle>";
            echo "<td>$description</td>";
            echo "<td style='text-align: right;'>" . number_format($errorCount) . "</td>";
            echo "<td>$status</td>";
            echo "</tr>\n";
            
        } catch (Exception $e) {
            echo "<tr style='background-color: #ffcccc;'>";
            echo "<td>$description</td>";
            echo "<td colspan='2'>エラー: " . $e->getMessage() . "</td>";
            echo "</tr>\n";
        }
    }
    echo "</table>\n";
    
    // 2. 重複データチェック
    echo "<h3>重複データチェック</h3>\n";
    
    $duplicateChecks = [
        'companies.company_code' => "SELECT company_code, COUNT(*) as cnt FROM companies GROUP BY company_code HAVING cnt > 1",
        'users.user_code' => "SELECT user_code, COUNT(*) as cnt FROM users GROUP BY user_code HAVING cnt > 1",
        'departments（company_id + department_code）' => "SELECT company_id, department_code, COUNT(*) as cnt FROM departments GROUP BY company_id, department_code HAVING cnt > 1"
    ];
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
    echo "<tr style='background-color: #f0f0f0;'><th>チェック対象</th><th>重複グループ数</th><th>状態</th></tr>\n";
    
    foreach ($duplicateChecks as $description => $query) {
        try {
            $stmt = $pdo->query($query);
            $duplicateCount = $stmt->rowCount();
            $status = $duplicateCount > 0 ? '⚠️ 重複あり' : '✅ OK';
            $rowStyle = $duplicateCount > 0 ? ' style="background-color: #fff2cc;"' : '';
            
            echo "<tr$rowStyle>";
            echo "<td>$description</td>";
            echo "<td style='text-align: right;'>" . number_format($duplicateCount) . "</td>";
            echo "<td>$status</td>";
            echo "</tr>\n";
            
        } catch (Exception $e) {
            echo "<tr style='background-color: #ffcccc;'>";
            echo "<td>$description</td>";
            echo "<td colspan='2'>エラー: " . $e->getMessage() . "</td>";
            echo "</tr>\n";
        }
    }
    echo "</table>\n";
}

function analyzeDeletionImpact($pdo) {
    echo "<p>Phase 1で削除予定のカラムの影響を分析します。</p>\n";
    
    $deletionCandidates = [
        'companies' => ['postal_code', 'prefecture', 'city', 'address_detail', 'fax', 'is_vip', 'credit_rating', 'business_type', 'employee_count', 'daily_order_estimate'],
        'orders' => ['corporation_code', 'corporation_name', 'category_code', 'category_name'],
        'departments' => ['parent_department_id', 'department_level', 'department_path', 'manager_title', 'floor_building', 'room_number']
    ];
    
    foreach ($deletionCandidates as $table => $columns) {
        echo "<h4>テーブル: $table</h4>\n";
        
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
        echo "<tr style='background-color: #f0f0f0;'><th>削除予定カラム</th><th>データ使用状況</th><th>削除リスク</th></tr>\n";
        
        foreach ($columns as $column) {
            try {
                // カラム存在確認
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
                $stmt->execute([$table, $column]);
                
                if ($stmt->fetchColumn() == 0) {
                    echo "<tr style='background-color: #f0f0f0;'>";
                    echo "<td>$column</td>";
                    echo "<td>カラム存在しない</td>";
                    echo "<td>✅ 問題なし</td>";
                    echo "</tr>\n";
                    continue;
                }
                
                // データ使用状況確認
                $stmt = $pdo->query("SELECT COUNT(*) FROM $table WHERE $column IS NOT NULL AND $column != ''");
                $usedCount = $stmt->fetchColumn();
                
                $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
                $totalCount = $stmt->fetchColumn();
                
                $usageRate = $totalCount > 0 ? ($usedCount / $totalCount * 100) : 0;
                
                $risk = 'ℹ️ 低リスク';
                if ($usageRate > 50) {
                    $risk = '⚠️ 中リスク';
                } elseif ($usageRate > 80) {
                    $risk = '❌ 高リスク';
                }
                
                $rowStyle = '';
                if ($usageRate > 50) {
                    $rowStyle = ' style="background-color: #fff2cc;"';
                }
                if ($usageRate > 80) {
                    $rowStyle = ' style="background-color: #ffcccc;"';
                }
                
                echo "<tr$rowStyle>";
                echo "<td>$column</td>";
                echo "<td>$usedCount / $totalCount (" . number_format($usageRate, 1) . "%)</td>";
                echo "<td>$risk</td>";
                echo "</tr>\n";
                
            } catch (Exception $e) {
                echo "<tr style='background-color: #ffcccc;'>";
                echo "<td>$column</td>";
                echo "<td colspan='2'>エラー: " . $e->getMessage() . "</td>";
                echo "</tr>\n";
            }
        }
        echo "</table>\n";
        echo "<br>\n";
    }
}

function checkInvoiceRequiredData($pdo) {
    echo "<p>請求書生成に必要なデータの状況を確認します。</p>\n";
    
    $requiredData = [
        '企業基本情報' => [
            'query' => "SELECT COUNT(*) FROM companies WHERE company_name IS NOT NULL AND company_name != ''",
            'description' => '企業名が登録されている企業数'
        ],
        '請求先情報' => [
            'query' => "SELECT COUNT(*) FROM companies WHERE billing_method IS NOT NULL",
            'description' => '請求方法が設定されている企業数'
        ],
        '利用者情報' => [
            'query' => "SELECT COUNT(*) FROM users WHERE user_name IS NOT NULL AND user_name != ''",
            'description' => '利用者名が登録されている利用者数'
        ],
        '注文データ' => [
            'query' => "SELECT COUNT(*) FROM orders WHERE total_amount IS NOT NULL AND total_amount > 0",
            'description' => '有効な金額データがある注文数'
        ],
        '今月の注文' => [
            'query' => "SELECT COUNT(*) FROM orders WHERE delivery_date >= DATE_FORMAT(NOW(), '%Y-%m-01')",
            'description' => '今月の注文件数'
        ]
    ];
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
    echo "<tr style='background-color: #f0f0f0;'><th>チェック項目</th><th>件数</th><th>説明</th></tr>\n";
    
    foreach ($requiredData as $name => $check) {
        try {
            $count = $pdo->query($check['query'])->fetchColumn();
            
            echo "<tr>";
            echo "<td><strong>$name</strong></td>";
            echo "<td style='text-align: right;'>" . number_format($count) . "</td>";
            echo "<td>{$check['description']}</td>";
            echo "</tr>\n";
            
        } catch (Exception $e) {
            echo "<tr style='background-color: #ffcccc;'>";
            echo "<td><strong>$name</strong></td>";
            echo "<td colspan='2'>エラー: " . $e->getMessage() . "</td>";
            echo "</tr>\n";
        }
    }
    echo "</table>\n";
    
    // 請求書生成の現実性チェック
    echo "<h3>請求書生成の現実性チェック</h3>\n";
    
    try {
        $stmt = $pdo->query("
            SELECT 
                c.company_name,
                c.billing_method,
                COUNT(DISTINCT u.id) as user_count,
                COUNT(o.id) as order_count,
                SUM(o.total_amount) as total_amount,
                MIN(o.delivery_date) as first_order,
                MAX(o.delivery_date) as last_order
            FROM companies c
            LEFT JOIN users u ON c.id = u.company_id
            LEFT JOIN orders o ON u.id = o.user_id
            WHERE o.delivery_date >= DATE_FORMAT(NOW(), '%Y-%m-01')
            GROUP BY c.id
            HAVING order_count > 0
            ORDER BY total_amount DESC
            LIMIT 10
        ");
        
        $invoiceableCompanies = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($invoiceableCompanies) > 0) {
            echo "<p style='color: green;'>✅ 請求書生成可能な企業が見つかりました</p>\n";
            echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
            echo "<tr style='background-color: #f0f0f0;'>";
            echo "<th>企業名</th><th>請求方法</th><th>利用者数</th><th>注文数</th><th>金額</th><th>期間</th></tr>\n";
            
            foreach ($invoiceableCompanies as $company) {
                echo "<tr>";
                echo "<td>{$company['company_name']}</td>";
                echo "<td>{$company['billing_method']}</td>";
                echo "<td style='text-align: right;'>" . number_format($company['user_count']) . "</td>";
                echo "<td style='text-align: right;'>" . number_format($company['order_count']) . "</td>";
                echo "<td style='text-align: right;'>¥" . number_format($company['total_amount']) . "</td>";
                echo "<td>{$company['first_order']} 〜 {$company['last_order']}</td>";
                echo "</tr>\n";
            }
            echo "</table>\n";
        } else {
            echo "<p style='color: red;'>❌ 請求書生成可能なデータが見つかりません</p>\n";
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>エラー: " . $e->getMessage() . "</p>\n";
    }
}

// 最終的な推奨事項を表示
function displayRecommendations() {
    echo "<h2>🎯 推奨事項</h2>\n";
    
    echo "<div style='border: 2px solid #007bff; padding: 15px; margin: 10px 0; background-color: #e7f1ff;'>\n";
    echo "<h3>即座に実行可能な安全な作業</h3>\n";
    echo "<ol>\n";
    echo "<li><strong>完全バックアップの作成</strong> - 任意の修正作業前に必須</li>\n";
    echo "<li><strong>テスト環境での検証</strong> - 本番への影響を避けるため</li>\n";
    echo "<li><strong>Phase 1のカラム削除</strong> - 明らかに不要なカラムのみ（影響度低）</li>\n";
    echo "</ol>\n";
    echo "</div>\n";
    
    echo "<div style='border: 2px solid #ffc107; padding: 15px; margin: 10px 0; background-color: #fff8e1;'>\n";
    echo "<h3>慎重に検討すべき作業</h3>\n";
    echo "<ol>\n";
    echo "<li><strong>カラム名の統一</strong> - データ移行を伴うため慎重に</li>\n";
    echo "<li><strong>請求書機能関連カラム</strong> - 機能実装後に削除可否を判断</li>\n";
    echo "<li><strong>外部キー制約</strong> - データ整合性への影響大</li>\n";
    echo "</ol>\n";
    echo "</div>\n";
    
    echo "<div style='border: 2px solid #dc3545; padding: 15px; margin: 10px 0; background-color: #ffebee;'>\n";
    echo "<h3>実行してはいけない作業</h3>\n";
    echo "<ol>\n";
    echo "<li><strong>バックアップなしの修正</strong> - 復旧不可能なリスク</li>\n";
    echo "<li><strong>本番環境での直接テスト</strong> - サービス停止リスク</li>\n";
    echo "<li><strong>請求書必須カラムの削除</strong> - 機能停止リスク</li>\n";
    echo "</ol>\n";
    echo "</div>\n";
}

// 推奨事項を表示
displayRecommendations();

echo "<hr>\n";
echo "<p><strong>📞 サポート:</strong> 問題や質問がある場合は、GitHub Issuesで報告してください。</p>\n";
echo "<p><strong>⏰ 実行時間:</strong> " . date('Y-m-d H:i:s') . "</p>\n";

?>
