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
