<?php
/**
 * Smiley配食事業専用CSVインポーター
 * 自己完結原則準拠版 - コンストラクタ引数なし
 * 
 * 設計原則:
 * - 自己完結: Database::getInstance()を内部で呼び出し
 * - 外部依存なし: 引数でDatabaseを受け取らない
 * 
 * @version 4.0.0 - 自己完結版
 * @date 2025-10-02
 */

// config/database.phpを読み込み（Database統一版）
require_once __DIR__ . '/../config/database.php';

class SmileyCSVImporter {
    private $db;
    
    // 拡張エンコーディング対応（優先順位順）
    private $allowedEncodings = [
        'SJIS-win',      // Windows版Shift-JIS（最優先）
        'CP932',         // Code Page 932
        'SJIS',          // 標準Shift-JIS
        'Shift_JIS',     // 別名
        'UTF-8',         // UTF-8
        'UTF-8-BOM',     // BOM付きUTF-8
        'EUC-JP',        // EUC-JP
        'ISO-2022-JP'    // JIS
    ];
    
    // CSVフィールドマッピング（Smiley配食事業仕様）
    private $fieldMapping = [
        '法人CD' => 'corporation_code',
        '法人名' => 'corporation_name',
        '事業所CD' => 'company_code',      // 実際：配達先企業コード
        '事業所名' => 'company_name',      // 実際：配達先企業名
        '給食業者CD' => 'supplier_code',
        '給食業者名' => 'supplier_name',
        '給食区分CD' => 'category_code',
        '給食区分名' => 'category_name',
        '配達日' => 'delivery_date',
        '部門CD' => 'department_code',
        '部門名' => 'department_name',
        '社員CD' => 'user_code',           // 実際：利用者コード
        '社員名' => 'user_name',           // 実際：利用者名
        '雇用形態CD' => 'employee_type_code',
        '雇用形態名' => 'employee_type_name',
        '給食ﾒﾆｭｰCD' => 'product_code',
        '給食ﾒﾆｭｰ名' => 'product_name',
        '数量' => 'quantity',
        '単価' => 'unit_price',
        '金額' => 'total_amount',
        '備考' => 'notes',
        '受取時間' => 'delivery_time',
        '連携CD' => 'cooperation_code'
    ];
    
    // ログ用
    private $importLog = [];
    
    /**
     * コンストラクタ - 自己完結版（引数なし）
     * 
     * 設計原則: 
     * - 内部でDatabase::getInstance()を呼び出し
     * - 外部からDatabaseインスタンスを受け取らない
     */
    public function __construct() {
        // 自己完結: 内部でDatabaseインスタンスを取得
        $this->db = Database::getInstance();
        $this->log("SmileyCSVImporter初期化完了（自己完結版）");
    }
    
    /**
     * CSVファイルインポート（メイン処理）
     */
    public function importFile($filePath, $options = []) {
        $startTime = microtime(true);
        $batchId = 'BATCH_' . date('YmdHis') . '_' . uniqid();
        
        try {
            $this->log("インポート開始", ['batch_id' => $batchId, 'file' => $filePath]);
            
            // 1. ファイル存在確認
            if (!file_exists($filePath)) {
                throw new Exception("ファイルが存在しません: {$filePath}");
            }
            
            // 2. ファイル読み取り権限確認
            if (!is_readable($filePath)) {
                throw new Exception("ファイルの読み取り権限がありません: {$filePath}");
            }
            
            // 3. ファイルサイズ確認
            $fileSize = filesize($filePath);
            if ($fileSize === false || $fileSize === 0) {
                throw new Exception("ファイルサイズを取得できません、またはファイルが空です");
            }
            
            $this->log("ファイル確認完了", ['size' => $fileSize]);
            
            // 4. エンコーディング検出
            $encoding = $this->detectEncodingAdvanced($filePath);
            $this->log("エンコーディング検出", ['encoding' => $encoding]);
            
            // 5. CSV読み込み
            $rawData = $this->readCsvAdvanced($filePath, $encoding);
            $this->log("CSV読み込み完了", ['rows' => count($rawData)]);
            
            // 6. データ変換・正規化
            $normalizedData = $this->normalizeDataAdvanced($rawData);
            $this->log("データ正規化完了", ['normalized_rows' => count($normalizedData)]);
            
            // 7. データ検証
            $validationResult = $this->validateDataAdvanced($normalizedData);
            $this->log("データ検証完了", [
                'valid' => count($validationResult['valid_data']),
                'errors' => count($validationResult['errors'])
            ]);
            
            // 8. データベース登録
            $importResult = $this->importToDatabaseAdvanced($validationResult['valid_data'], $batchId);
            $this->log("データベース登録完了", $importResult);
            
            // 9. ログ記録
            $this->logImportResult($batchId, $filePath, $importResult, $validationResult, $startTime);
            
            return [
                'success' => true,
                'batch_id' => $batchId,
                'stats' => $importResult,
                'errors' => $validationResult['errors'],
                'processing_time' => round(microtime(true) - $startTime, 2),
                'encoding' => $encoding,
                'debug_log' => $this->importLog
            ];
            
        } catch (Exception $e) {
            $this->log("エラー発生", [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            $this->logError($batchId, $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }
    
    /**
     * エンコーディング検出
     */
    private function detectEncodingAdvanced($filePath) {
        try {
            $content = file_get_contents($filePath);
            if ($content === false) {
                throw new Exception("ファイル内容を読み込めません");
            }
            
            $this->log("ファイル内容読み込み", ['length' => strlen($content)]);
            
            // BOM付きUTF-8チェック
            if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
                $this->log("BOM付きUTF-8検出");
                return 'UTF-8-BOM';
            }
            
            // 各エンコーディングでテスト
            foreach ($this->allowedEncodings as $encoding) {
                if ($this->testEncoding($content, $encoding)) {
                    $this->log("エンコーディング確定", ['encoding' => $encoding]);
                    return $encoding;
                }
            }
            
            // 最終手段: SJIS-winで強制処理
            $this->log("エンコーディング検出失敗、SJIS-winで強制処理");
            return 'SJIS-win';
            
        } catch (Exception $e) {
            $this->log("エンコーディング検出エラー", ['error' => $e->getMessage()]);
            throw new Exception("エンコーディング検出エラー: " . $e->getMessage());
        }
    }
    
    /**
     * エンコーディングテスト
     */
    private function testEncoding($content, $encoding) {
        try {
            // 1. mb_check_encoding でチェック
            if (!mb_check_encoding($content, $encoding)) {
                return false;
            }
            
            // 2. 実際に変換してみる
            $testContent = substr($content, 0, 1000);
            $converted = mb_convert_encoding($testContent, 'UTF-8', $encoding);
            
            if ($converted === false || strlen($converted) === 0) {
                return false;
            }
            
            // 3. 日本語文字が含まれているかチェック
            if ($encoding !== 'UTF-8' && $encoding !== 'UTF-8-BOM') {
                if (preg_match('/[\x{3040}-\x{309F}\x{30A0}-\x{30FF}\x{4E00}-\x{9FAF}]/u', $converted)) {
                    $this->log("日本語文字検出", ['encoding' => $encoding]);
                    return true;
                }
            }
            
            return true;
            
        } catch (Exception $e) {
            $this->log("エンコーディングテストエラー", [
                'encoding' => $encoding,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * CSV読み込み
     */
    private function readCsvAdvanced($filePath, $encoding) {
        $data = [];
        $tmpFile = null;
        
        try {
            $this->log("CSV読み込み開始", ['encoding' => $encoding]);
            
            // ファイル内容を一括読み込み
            $content = file_get_contents($filePath);
            if ($content === false) {
                throw new Exception("ファイル内容の読み込みに失敗しました");
            }
            
            // BOM除去
            if ($encoding === 'UTF-8-BOM') {
                $content = substr($content, 3);
                $encoding = 'UTF-8';
            }
            
            // エンコーディング変換
            if ($encoding !== 'UTF-8') {
                $this->log("エンコーディング変換開始", [
                    'from' => $encoding,
                    'to' => 'UTF-8'
                ]);
                
                $converted = mb_convert_encoding($content, 'UTF-8', $encoding);
                if ($converted === false) {
                    throw new Exception("エンコーディング変換に失敗しました: {$encoding} → UTF-8");
                }
                $content = $converted;
            }
            
            // 改行コード統一
            $content = str_replace(["\r\n", "\r"], "\n", $content);
            
            // 一時ファイルに書き込み
            $tmpFile = tmpfile();
            if ($tmpFile === false) {
                throw new Exception("一時ファイルの作成に失敗しました");
            }
            
            fwrite($tmpFile, $content);
            rewind($tmpFile);
            
            // ヘッダー行読み込み
            $headers = fgetcsv($tmpFile);
            if ($headers === false) {
                throw new Exception("ヘッダー行を読み込めません");
            }
            
            // ヘッダー正規化
            $headers = array_map('trim', $headers);
            $this->log("ヘッダー読み込み", ['headers' => $headers]);
            
            // ヘッダー数チェック
            if (count($headers) !== 23) {
                $this->log("ヘッダー数警告", [
                    'expected' => 23,
                    'actual' => count($headers)
                ]);
            }
            
            // データ行読み込み
            $rowNumber = 1;
            while (($row = fgetcsv($tmpFile)) !== false) {
                $rowNumber++;
                
                // 空行スキップ
                if (count(array_filter($row)) === 0) {
                    continue;
                }
                
                // カラム数調整
                $row = array_pad($row, count($headers), '');
                
                // ヘッダーとデータを結合
                $rowData = array_combine($headers, $row);
                if ($rowData === false) {
                    $this->log("行データ結合エラー", [
                        'row' => $rowNumber,
                        'headers_count' => count($headers),
                        'data_count' => count($row)
                    ]);
                    continue;
                }
                
                $rowData['_row_number'] = $rowNumber;
                $data[] = $rowData;
            }
            
            $this->log("CSV読み込み完了", [
                'total_rows' => count($data),
                'headers' => count($headers)
            ]);
            
            return $data;
            
        } catch (Exception $e) {
            $this->log("CSV読み込みエラー", ['error' => $e->getMessage()]);
            throw new Exception("CSV読み込みエラー: " . $e->getMessage());
        } finally {
            if ($tmpFile) {
                fclose($tmpFile);
            }
        }
    }
    
    /**
     * データ正規化
     */
    private function normalizeDataAdvanced($rawData) {
        $normalizedData = [];
        
        try {
            foreach ($rawData as $index => $row) {
                $normalized = [];
                
                // フィールドマッピング適用
                foreach ($this->fieldMapping as $csvField => $dbField) {
                    $value = isset($row[$csvField]) ? trim($row[$csvField]) : '';
                    
                    // データ型別正規化
                    switch ($dbField) {
                        case 'delivery_date':
                            $normalized[$dbField] = $this->normalizeDate($value);
                            break;
                        case 'quantity':
                            $normalized[$dbField] = $this->normalizeInteger($value);
                            break;
                        case 'unit_price':
                        case 'total_amount':
                            $normalized[$dbField] = $this->normalizeDecimal($value);
                            break;
                        case 'delivery_time':
                            $normalized[$dbField] = $this->normalizeTime($value);
                            break;
                        default:
                            $normalized[$dbField] = $this->normalizeString($value);
                    }
                }
                
                $normalized['_row_number'] = $row['_row_number'];
                $normalizedData[] = $normalized;
            }
            
            return $normalizedData;
            
        } catch (Exception $e) {
            throw new Exception("データ正規化エラー: " . $e->getMessage());
        }
    }
    
    /**
     * データ検証
     */
    private function validateDataAdvanced($normalizedData) {
        $validData = [];
        $errors = [];
        
        foreach ($normalizedData as $index => $row) {
            $rowErrors = [];
            
            // 必須項目チェック
            $required = ['corporation_name', 'company_code', 'company_name', 
                        'user_code', 'user_name', 'product_code', 'product_name', 'delivery_date'];
            
            foreach ($required as $field) {
                if (empty($row[$field])) {
                    $rowErrors[] = "{$field} は必須です";
                }
            }
            
            // Smiley配食事業専用チェック（緩和版）
            if (!empty($row['corporation_name']) && 
                $row['corporation_name'] !== '株式会社Smiley' && 
                $row['corporation_name'] !== 'Smiley') {
                $rowErrors[] = "法人名が「株式会社Smiley」ではありません: {$row['corporation_name']}（警告）";
            }
            
            // 金額整合性チェック
            if (!empty($row['quantity']) && !empty($row['unit_price']) && !empty($row['total_amount'])) {
                $calculated = floatval($row['quantity']) * floatval($row['unit_price']);
                $actual = floatval($row['total_amount']);
                if (abs($calculated - $actual) > 0.01) {
                    $rowErrors[] = "金額が正しくありません（数量×単価≠金額）: 計算値={$calculated}, 実際値={$actual}";
                }
            }
            
            if (empty($rowErrors)) {
                $validData[] = $row;
            } else {
                $errors[] = [
                    'row' => $row['_row_number'],
                    'errors' => $rowErrors,
                    'data' => $row
                ];
            }
        }
        
        return [
            'valid_data' => $validData,
            'errors' => $errors
        ];
    }
    
    /**
     * データベース登録
     */
    private function importToDatabaseAdvanced($validData, $batchId) {
        $success = 0;
        $errors = 0;
        $duplicates = 0;
        
        try {
            $this->db->beginTransaction();
            
            foreach ($validData as $row) {
                try {
                    // 重複チェック
                    $stmt = $this->db->query(
                        "SELECT id FROM orders WHERE user_code = ? AND delivery_date = ? AND product_code = ?",
                        [$row['user_code'], $row['delivery_date'], $row['product_code']]
                    );
                    
                    if ($stmt->rowCount() > 0) {
                        $duplicates++;
                        continue;
                    }
                    
                    // データ挿入
                    $sql = "INSERT INTO orders (
                        corporation_code, corporation_name, company_code, company_name,
                        supplier_code, supplier_name, category_code, category_name,
                        delivery_date, department_code, department_name, user_code, user_name,
                        employee_type_code, employee_type_name, product_code, product_name,
                        quantity, unit_price, total_amount, notes, delivery_time, cooperation_code,
                        import_batch_id, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                    
                    $this->db->query($sql, [
                        $row['corporation_code'], $row['corporation_name'],
                        $row['company_code'], $row['company_name'],
                        $row['supplier_code'], $row['supplier_name'],
                        $row['category_code'], $row['category_name'],
                        $row['delivery_date'], $row['department_code'], $row['department_name'],
                        $row['user_code'], $row['user_name'],
                        $row['employee_type_code'], $row['employee_type_name'],
                        $row['product_code'], $row['product_name'],
                        $row['quantity'], $row['unit_price'], $row['total_amount'],
                        $row['notes'], $row['delivery_time'], $row['cooperation_code'],
                        $batchId
                    ]);
                    
                    $success++;
                    
                } catch (Exception $e) {
                    $errors++;
                    $this->log("行挿入エラー", [
                        'row' => $row['_row_number'],
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
            $this->db->commit();
            
            return [
                'total' => count($validData),
                'success' => $success,
                'error' => $errors,
                'duplicate' => $duplicates
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw new Exception("データベース登録エラー: " . $e->getMessage());
        }
    }
    
    /**
     * 正規化ヘルパー関数
     */
    private function normalizeDate($value) {
        if (empty($value)) return null;
        
        $formats = ['Y-m-d', 'Y/m/d', 'm/d/Y', 'd/m/Y', 'Ymd'];
        
        foreach ($formats as $format) {
            $date = DateTime::createFromFormat($format, $value);
            if ($date !== false) {
                return $date->format('Y-m-d');
            }
        }
        
        return $value;
    }
    
    private function normalizeInteger($value) {
        if (empty($value)) return 0;
        $value = mb_convert_kana($value, 'n', 'UTF-8');
        return intval($value);
    }
    
    private function normalizeDecimal($value) {
        if (empty($value)) return 0.00;
        $value = mb_convert_kana($value, 'n', 'UTF-8');
        $value = str_replace(',', '', $value);
        return floatval($value);
    }
    
    private function normalizeTime($value) {
        if (empty($value)) return null;
        
        if (preg_match('/(\d{1,2}):(\d{2})/', $value, $matches)) {
            return sprintf('%02d:%02d', $matches[1], $matches[2]);
        }
        
        return $value;
    }
    
    private function normalizeString($value) {
        if (empty($value)) return '';
        $value = trim($value);
        $value = str_replace(["\r\n", "\r", "\n"], ' ', $value);
        return $value;
    }
    
    /**
     * ログ記録
     */
    private function log($message, $context = []) {
        $this->importLog[] = [
            'timestamp' => date('Y-m-d H:i:s'),
            'message' => $message,
            'context' => $context
        ];
    }
    
    /**
     * インポート結果ログ記録
     */
    private function logImportResult($batchId, $filePath, $importResult, $validationResult, $startTime) {
        try {
            $logData = [
                'batch_id' => $batchId,
                'file_path' => basename($filePath),
                'total_records' => $importResult['total'],
                'success_records' => $importResult['success'],
                'error_records' => $importResult['error'],
                'duplicate_records' => $importResult['duplicate'],
                'validation_errors' => count($validationResult['errors']),
                'processing_time_seconds' => round(microtime(true) - $startTime, 2),
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            // import_logsテーブルに記録
            $sql = "INSERT INTO import_logs (
                batch_id, file_path, total_records, success_records, 
                error_records, duplicate_records, processing_time_seconds, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            
            $this->db->query($sql, [
                $logData['batch_id'], $logData['file_path'],
                $logData['total_records'], $logData['success_records'],
                $logData['error_records'], $logData['duplicate_records'],
                $logData['processing_time_seconds'], $logData['created_at']
            ]);
            
        } catch (Exception $e) {
            error_log("インポートログ記録エラー: " . $e->getMessage());
        }
    }
    
    /**
     * エラーログ記録
     */
    private function logError($batchId, $message, $context = []) {
        try {
            error_log("CSV Import Error [{$batchId}]: {$message} " . json_encode($context));
        } catch (Exception $e) {
            // エラーログの記録に失敗しても処理は続行
        }
    }
}
?>
