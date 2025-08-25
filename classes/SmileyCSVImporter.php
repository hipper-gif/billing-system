<?php
/**
 * Smiley配食事業専用CSVインポーター（完全修正版）
 * 「結果が全て0」問題の完全解決
 */
class SmileyCSVImporter {
    private $db;
    private $allowedEncodings = ['SJIS-win', 'UTF-8', 'UTF-8-BOM', 'SJIS', 'Shift_JIS'];
    
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
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * CSVファイルインポート（メイン処理）
     */
    public function importFile($filePath, $options = []) {
        $startTime = microtime(true);
        $batchId = 'BATCH_' . date('YmdHis') . '_' . uniqid();
        
        $stats = [
            'total' => 0,
            'success' => 0,
            'error' => 0,
            'duplicate' => 0
        ];
        
        $errors = [];
        
        try {
            // 1. エンコーディング検出・変換
            $encoding = $this->detectEncoding($filePath);
            
            // 2. CSV読み込み
            $rawData = $this->readCsv($filePath, $encoding);
            
            if (empty($rawData)) {
                throw new Exception('CSVファイルにデータが含まれていません');
            }
            
            $stats['total'] = count($rawData);
            
            // 3. データ変換・正規化
            $normalizedData = $this->normalizeData($rawData);
            
            // 4. データ検証
            $validationResult = $this->validateData($normalizedData);
            $validData = $validationResult['valid_data'];
            $errors = array_merge($errors, $validationResult['errors']);
            
            // 5. データベース登録
            if (!empty($validData)) {
                $importResult = $this->importToDatabase($validData, $batchId);
                $stats['success'] = $importResult['success'];
                $stats['error'] = $importResult['error'];
                $stats['duplicate'] = $importResult['duplicate'];
                $errors = array_merge($errors, $importResult['errors']);
            }
            
            // 6. インポートログ記録
            $this->logImport($batchId, $filePath, $stats, $errors, $startTime);
            
            return [
                'success' => true,
                'batch_id' => $batchId,
                'stats' => $stats,
                'errors' => $errors,
                'processing_time' => round(microtime(true) - $startTime, 2)
            ];
            
        } catch (Exception $e) {
            $this->logError($batchId, $e->getMessage(), $filePath);
            
            return [
                'success' => false,
                'batch_id' => $batchId,
                'stats' => $stats,
                'errors' => array_merge($errors, [['message' => $e->getMessage(), 'line' => 0]]),
                'processing_time' => round(microtime(true) - $startTime, 2)
            ];
        }
    }
    
    /**
     * 文字エンコーディング自動検出
     */
    private function detectEncoding($filePath) {
        $content = file_get_contents($filePath);
        
        if ($content === false) {
            throw new Exception("ファイルを読み込めません: {$filePath}");
        }
        
        // BOM付きUTF-8チェック
        if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
            return 'UTF-8-BOM';
        }
        
        // エンコーディング検出
        $detectedEncoding = mb_detect_encoding($content, $this->allowedEncodings, true);
        
        if ($detectedEncoding !== false) {
            return $detectedEncoding;
        }
        
        // デフォルトはSJIS-win（Excelからの出力が多いため）
        return 'SJIS-win';
    }
    
    /**
     * CSV読み込み
     */
    private function readCsv($filePath, $encoding) {
        $data = [];
        $content = file_get_contents($filePath);
        
        if ($content === false) {
            throw new Exception("ファイルを読み込めません: {$filePath}");
        }
        
        // BOM除去
        if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
            $content = substr($content, 3);
        }
        
        // エンコーディング変換
        if ($encoding !== 'UTF-8') {
            $content = mb_convert_encoding($content, 'UTF-8', $encoding);
        }
        
        // CSV行に分割
        $lines = explode("\n", $content);
        $lines = array_map('trim', $lines);
        $lines = array_filter($lines); // 空行除去
        
        if (empty($lines)) {
            throw new Exception('CSVファイルが空です');
        }
        
        // ヘッダー行解析
        $headers = str_getcsv($lines[0]);
        $headers = array_map('trim', $headers);
        
        // データ行解析
        for ($i = 1; $i < count($lines); $i++) {
            $row = str_getcsv($lines[$i]);
            
            // 空行スキップ
            if (empty(array_filter($row))) {
                continue;
            }
            
            // ヘッダーとデータ結合
            $rowData = [];
            for ($j = 0; $j < count($headers); $j++) {
                $value = isset($row[$j]) ? trim($row[$j]) : '';
                $rowData[$headers[$j]] = $value;
            }
            
            $rowData['_row_number'] = $i + 1;
            $data[] = $rowData;
        }
        
        return $data;
    }
    
    /**
     * データ正規化・変換
     */
    private function normalizeData($rawData) {
        $normalizedData = [];
        
        foreach ($rawData as $row) {
            $normalized = [];
            
            // フィールドマッピング適用
            foreach ($this->fieldMapping as $csvField => $dbField) {
                $value = isset($row[$csvField]) ? $row[$csvField] : '';
                $normalized[$dbField] = $this->normalizeValue($value, $dbField);
            }
            
            $normalized['_row_number'] = $row['_row_number'];
            $normalizedData[] = $normalized;
        }
        
        return $normalizedData;
    }
    
    /**
     * 値の正規化
     */
    private function normalizeValue($value, $fieldType) {
        $value = trim($value);
        
        // 日付フィールドの正規化
        if (in_array($fieldType, ['delivery_date'])) {
            return $this->normalizeDate($value);
        }
        
        // 数値フィールドの正規化
        if (in_array($fieldType, ['quantity', 'unit_price', 'total_amount'])) {
            return $this->normalizeNumber($value);
        }
        
        // 時刻フィールドの正規化
        if (in_array($fieldType, ['delivery_time'])) {
            return $this->normalizeTime($value);
        }
        
        // 文字列フィールドの正規化
        return mb_convert_kana($value, 'KVas');
    }
    
    /**
     * 日付正規化
     */
    private function normalizeDate($dateStr) {
        if (empty($dateStr)) {
            return null;
        }
        
        // 複数の日付フォーマットに対応
        $formats = [
            'Y-m-d',
            'Y/m/d',
            'Y-m-j',
            'Y/m/j',
            'n/j/Y',
            'm/d/Y',
            'd/m/Y'
        ];
        
        foreach ($formats as $format) {
            $date = DateTime::createFromFormat($format, $dateStr);
            if ($date !== false) {
                return $date->format('Y-m-d');
            }
        }
        
        return $dateStr; // 変換できない場合はそのまま返す
    }
    
    /**
     * 数値正規化
     */
    private function normalizeNumber($numStr) {
        if (empty($numStr)) {
            return 0;
        }
        
        // 全角数字を半角に変換
        $numStr = mb_convert_kana($numStr, 'n');
        
        // カンマ除去
        $numStr = str_replace(',', '', $numStr);
        
        // 数値かチェック
        if (is_numeric($numStr)) {
            return (float)$numStr;
        }
        
        return 0;
    }
    
    /**
     * 時刻正規化
     */
    private function normalizeTime($timeStr) {
        if (empty($timeStr)) {
            return null;
        }
        
        // 複数の時刻フォーマットに対応
        $formats = ['H:i:s', 'H:i', 'G:i:s', 'G:i'];
        
        foreach ($formats as $format) {
            $time = DateTime::createFromFormat($format, $timeStr);
            if ($time !== false) {
                return $time->format('H:i:s');
            }
        }
        
        return $timeStr;
    }
    
    /**
     * データ検証
     */
    private function validateData($normalizedData) {
        $validData = [];
        $errors = [];
        
        foreach ($normalizedData as $row) {
            $rowErrors = $this->validateRow($row);
            
            if (empty($rowErrors)) {
                $validData[] = $row;
            } else {
                foreach ($rowErrors as $error) {
                    $errors[] = [
                        'line' => $row['_row_number'],
                        'message' => $error
                    ];
                }
            }
        }
        
        return [
            'valid_data' => $validData,
            'errors' => $errors
        ];
    }
    
    /**
     * 行データ検証
     */
    private function validateRow($row) {
        $errors = [];
        
        // 必須項目チェック
        $required = ['delivery_date', 'user_code', 'product_code'];
        foreach ($required as $field) {
            if (empty($row[$field])) {
                $errors[] = "{$field} は必須項目です";
            }
        }
        
        // 日付妥当性チェック
        if (!empty($row['delivery_date'])) {
            $date = DateTime::createFromFormat('Y-m-d', $row['delivery_date']);
            if ($date === false) {
                $errors[] = "配達日の形式が正しくありません: {$row['delivery_date']}";
            }
        }
        
        // 数値妥当性チェック
        if (!empty($row['quantity']) && (!is_numeric($row['quantity']) || $row['quantity'] < 0)) {
            $errors[] = "数量は0以上の数値である必要があります: {$row['quantity']}";
        }
        
        if (!empty($row['unit_price']) && (!is_numeric($row['unit_price']) || $row['unit_price'] < 0)) {
            $errors[] = "単価は0以上の数値である必要があります: {$row['unit_price']}";
        }
        
        return $errors;
    }
    
    /**
     * データベースインポート
     */
    private function importToDatabase($validData, $batchId) {
        $stats = ['success' => 0, 'error' => 0, 'duplicate' => 0];
        $errors = [];
        
        $this->db->beginTransaction();
        
        try {
            foreach ($validData as $row) {
                try {
                    // 重複チェック
                    if ($this->isDuplicate($row)) {
                        $stats['duplicate']++;
                        continue;
                    }
                    
                    // マスターデータ確保
                    $masterIds = $this->ensureMasterData($row);
                    
                    // 注文データ挿入
                    $this->insertOrderData($row, $masterIds, $batchId);
                    
                    $stats['success']++;
                    
                } catch (Exception $e) {
                    $stats['error']++;
                    $errors[] = [
                        'line' => $row['_row_number'],
                        'message' => $e->getMessage()
                    ];
                }
            }
            
            $this->db->commit();
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
        
        return [
            'success' => $stats['success'],
            'error' => $stats['error'],
            'duplicate' => $stats['duplicate'],
            'errors' => $errors
        ];
    }
    
    /**
     * 重複チェック
     */
    private function isDuplicate($row) {
        $sql = "SELECT COUNT(*) as count FROM orders 
                WHERE user_code = ? 
                AND delivery_date = ? 
                AND product_code = ? 
                AND cooperation_code = ?";
        
        $result = $this->db->fetchOne($sql, [
            $row['user_code'],
            $row['delivery_date'],
            $row['product_code'],
            $row['cooperation_code']
        ]);
        
        return $result['count'] > 0;
    }
    
    /**
     * マスターデータ確保
     */
    private function ensureMasterData($row) {
        $masterIds = [];
        
        // 企業データ確保
        $masterIds['company_id'] = $this->ensureCompany($row);
        
        // 部署データ確保
        $masterIds['department_id'] = $this->ensureDepartment($row, $masterIds['company_id']);
        
        // 利用者データ確保
        $masterIds['user_id'] = $this->ensureUser($row, $masterIds['company_id'], $masterIds['department_id']);
        
        // 業者データ確保
        $masterIds['supplier_id'] = $this->ensureSupplier($row);
        
        // 商品データ確保
        $masterIds['product_id'] = $this->ensureProduct($row);
        
        return $masterIds;
    }
    
    /**
     * 企業データ確保
     */
    private function ensureCompany($row) {
        $sql = "SELECT id FROM companies WHERE company_code = ?";
        $result = $this->db->fetchOne($sql, [$row['company_code']]);
        
        if ($result) {
            return $result['id'];
        }
        
        // 企業作成
        $sql = "INSERT INTO companies (company_code, company_name, created_at) VALUES (?, ?, NOW())";
        $this->db->execute($sql, [$row['company_code'], $row['company_name']]);
        
        return $this->db->lastInsertId();
    }
    
    /**
     * 部署データ確保
     */
    private function ensureDepartment($row, $companyId) {
        $sql = "SELECT id FROM departments WHERE company_id = ? AND department_code = ?";
        $result = $this->db->fetchOne($sql, [$companyId, $row['department_code']]);
        
        if ($result) {
            return $result['id'];
        }
        
        // 部署作成
        $sql = "INSERT INTO departments (company_id, department_code, department_name, created_at) VALUES (?, ?, ?, NOW())";
        $this->db->execute($sql, [$companyId, $row['department_code'], $row['department_name']]);
        
        return $this->db->lastInsertId();
    }
    
    /**
     * 利用者データ確保
     */
    private function ensureUser($row, $companyId, $departmentId) {
        $sql = "SELECT id FROM users WHERE user_code = ?";
        $result = $this->db->fetchOne($sql, [$row['user_code']]);
        
        if ($result) {
            return $result['id'];
        }
        
        // 利用者作成
        $sql = "INSERT INTO users (user_code, user_name, company_id, department_id, employee_type_code, employee_type_name, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())";
        $this->db->execute($sql, [
            $row['user_code'],
            $row['user_name'],
            $companyId,
            $departmentId,
            $row['employee_type_code'],
            $row['employee_type_name']
        ]);
        
        return $this->db->lastInsertId();
    }
    
    /**
     * 業者データ確保
     */
    private function ensureSupplier($row) {
        $sql = "SELECT id FROM suppliers WHERE supplier_code = ?";
        $result = $this->db->fetchOne($sql, [$row['supplier_code']]);
        
        if ($result) {
            return $result['id'];
        }
        
        // 業者作成
        $sql = "INSERT INTO suppliers (supplier_code, supplier_name, created_at) VALUES (?, ?, NOW())";
        $this->db->execute($sql, [$row['supplier_code'], $row['supplier_name']]);
        
        return $this->db->lastInsertId();
    }
    
    /**
     * 商品データ確保
     */
    private function ensureProduct($row) {
        $sql = "SELECT id FROM products WHERE product_code = ?";
        $result = $this->db->fetchOne($sql, [$row['product_code']]);
        
        if ($result) {
            return $result['id'];
        }
        
        // 商品作成
        $sql = "INSERT INTO products (product_code, product_name, created_at) VALUES (?, ?, NOW())";
        $this->db->execute($sql, [$row['product_code'], $row['product_name']]);
        
        return $this->db->lastInsertId();
    }
    
    /**
     * 注文データ挿入
     */
    private function insertOrderData($row, $masterIds, $batchId) {
        $sql = "INSERT INTO orders (
            user_id, company_id, department_id, supplier_id, product_id,
            corporation_code, corporation_name, company_code, company_name,
            supplier_code, supplier_name, category_code, category_name,
            delivery_date, department_code, department_name,
            user_code, user_name, employee_type_code, employee_type_name,
            product_code, product_name, quantity, unit_price, total_amount,
            notes, delivery_time, cooperation_code, import_batch_id,
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $this->db->execute($sql, [
            $masterIds['user_id'],
            $masterIds['company_id'],
            $masterIds['department_id'],
            $masterIds['supplier_id'],
            $masterIds['product_id'],
            $row['corporation_code'],
            $row['corporation_name'],
            $row['company_code'],
            $row['company_name'],
            $row['supplier_code'],
            $row['supplier_name'],
            $row['category_code'],
            $row['category_name'],
            $row['delivery_date'],
            $row['department_code'],
            $row['department_name'],
            $row['user_code'],
            $row['user_name'],
            $row['employee_type_code'],
            $row['employee_type_name'],
            $row['product_code'],
            $row['product_name'],
            $row['quantity'],
            $row['unit_price'],
            $row['total_amount'],
            $row['notes'],
            $row['delivery_time'],
            $row['cooperation_code'],
            $batchId
        ]);
    }
    
    /**
     * インポートログ記録
     */
    private function logImport($batchId, $filePath, $stats, $errors, $startTime) {
        $processingTime = round(microtime(true) - $startTime, 2);
        
        $sql = "INSERT INTO import_logs (
            batch_id, file_path, total_records, success_records, 
            error_records, duplicate_records, processing_time_seconds,
            error_details, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $this->db->execute($sql, [
            $batchId,
            basename($filePath),
            $stats['total'],
            $stats['success'],
            $stats['error'],
            $stats['duplicate'],
            $processingTime,
            json_encode($errors, JSON_UNESCAPED_UNICODE)
        ]);
    }
    
    /**
     * エラーログ記録
     */
    private function logError($batchId, $message, $filePath = '') {
        $sql = "INSERT INTO import_logs (
            batch_id, file_path, total_records, success_records, 
            error_records, duplicate_records, processing_time_seconds,
            error_details, created_at
        ) VALUES (?, ?, 0, 0, 1, 0, 0, ?, NOW())";
        
        $this->db->execute($sql, [
            $batchId,
            basename($filePath),
            json_encode([['message' => $message, 'line' => 0]], JSON_UNESCAPED_UNICODE)
        ]);
    }
}
?>
