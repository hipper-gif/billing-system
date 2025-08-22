<?php
/**
 * Smiley配食事業専用CSVインポーター
 * 23フィールドCSV対応・階層データ自動生成
 */
class SmileyCSVImporter {
    private $db;
    private $allowedEncodings = ['SJIS-win', 'UTF-8', 'UTF-8-BOM'];
    private $batchSize = 1000;
    
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
    
    public function __construct(Database $db) {
        $this->db = $db;
    }
    
    /**
     * CSVファイルインポート（メイン処理）
     */
    public function importFile($filePath, $options = []) {
        $startTime = microtime(true);
        $batchId = 'BATCH_' . date('YmdHis') . '_' . uniqid();
        
        try {
            // 1. エンコーディング検出
            $encoding = $this->detectEncoding($filePath);
            
            // 2. CSV読み込み
            $rawData = $this->readCsv($filePath, $encoding);
            
            // 3. データ変換・正規化
            $normalizedData = $this->normalizeData($rawData);
            
            // 4. データ検証
            $validationResult = $this->validateData($normalizedData);
            
            // 5. データベース登録
            $importResult = $this->importToDatabase($validationResult['valid_data'], $batchId);
            
            // 6. インポートログ記録
            $this->logImport($batchId, $filePath, $importResult, $validationResult, $startTime);
            
            return [
                'success' => true,
                'batch_id' => $batchId,
                'stats' => [
                    'total' => count($normalizedData),
                    'success' => $importResult['success'],
                    'duplicate' => $importResult['duplicate'],
                    'errors' => count($validationResult['errors'])
                ],
                'errors' => $validationResult['errors'],
                'processing_time' => round(microtime(true) - $startTime, 2)
            ];
            
        } catch (Exception $e) {
            $this->logError($batchId, $e->getMessage());
            throw new Exception("CSVインポート処理エラー: " . $e->getMessage());
        }
    }
    
    /**
     * エンコーディング検出
     */
    private function detectEncoding($filePath) {
        $data = file_get_contents($filePath, false, null, 0, 8192);
        
        // BOMチェック
        if (substr($data, 0, 3) === "\xEF\xBB\xBF") {
            return 'UTF-8-BOM';
        }
        
        // エンコーディング検出
        $encoding = mb_detect_encoding($data, $this->allowedEncodings, true);
        
        return $encoding ?: 'SJIS-win';
    }
    
    /**
     * CSV読み込み
     */
    private function readCsv($filePath, $encoding) {
        $data = file_get_contents($filePath);
        
        // UTF-8に変換
        if ($encoding === 'UTF-8-BOM') {
            $data = substr($data, 3); // BOM除去
        } elseif ($encoding !== 'UTF-8') {
            $data = mb_convert_encoding($data, 'UTF-8', $encoding);
        }
        
        // CSVパース
        $lines = explode("\n", $data);
        $csvData = [];
        
        foreach ($lines as $line) {
            if (trim($line) === '') continue;
            $csvData[] = str_getcsv($line);
        }
        
        return $csvData;
    }
    
    /**
     * データ正規化
     */
    private function normalizeData($rawData) {
        if (empty($rawData)) {
            throw new Exception("CSVデータが空です");
        }
        
        $headers = array_shift($rawData);
        $normalizedData = [];
        
        foreach ($rawData as $rowIndex => $row) {
            if (count($row) < count($headers)) {
                // 足りない列を空文字で補完
                $row = array_pad($row, count($headers), '');
            }
            
            $normalizedRow = [
                'row_number' => $rowIndex + 2, // ヘッダー行+1
                'raw_data' => $row
            ];
            
            // フィールドマッピング
            foreach ($headers as $index => $header) {
                $normalizedHeader = trim($header);
                $mappedField = $this->fieldMapping[$normalizedHeader] ?? null;
                
                if ($mappedField) {
                    $normalizedRow[$mappedField] = trim($row[$index] ?? '');
                }
            }
            
            $normalizedData[] = $normalizedRow;
        }
        
        return $normalizedData;
    }
    
    /**
     * データ検証
     */
    private function validateData($data) {
        $validData = [];
        $errors = [];
        
        foreach ($data as $row) {
            $rowErrors = [];
            
            // 必須フィールドチェック
            $requiredFields = ['corporation_name', 'company_name', 'delivery_date', 'user_name', 'product_name'];
            foreach ($requiredFields as $field) {
                if (empty($row[$field])) {
                    $rowErrors[] = "必須フィールド '{$field}' が空です";
                }
            }
            
            // Smiley配食事業チェック
            if (!empty($row['corporation_name']) && $row['corporation_name'] !== '株式会社Smiley') {
                $rowErrors[] = "法人名が 'Smiley' 以外です: " . $row['corporation_name'];
            }
            
            // 日付フォーマットチェック
            if (!empty($row['delivery_date'])) {
                $date = DateTime::createFromFormat('Y-m-d', $row['delivery_date']);
                if (!$date || $date->format('Y-m-d') !== $row['delivery_date']) {
                    $rowErrors[] = "配達日の形式が不正です: " . $row['delivery_date'];
                }
            }
            
            // 数値フィールドチェック
            if (!empty($row['quantity']) && !is_numeric($row['quantity'])) {
                $rowErrors[] = "数量が数値ではありません: " . $row['quantity'];
            }
            
            if (!empty($row['unit_price']) && !is_numeric($row['unit_price'])) {
                $rowErrors[] = "単価が数値ではありません: " . $row['unit_price'];
            }
            
            if (!empty($row['total_amount']) && !is_numeric($row['total_amount'])) {
                $rowErrors[] = "金額が数値ではありません: " . $row['total_amount'];
            }
            
            if (!empty($rowErrors)) {
                $errors[] = [
                    'row' => $row['row_number'],
                    'errors' => $rowErrors,
                    'data' => $row['raw_data']
                ];
            } else {
                $validData[] = $row;
            }
        }
        
        return [
            'valid_data' => $validData,
            'errors' => $errors
        ];
    }
    
    /**
     * データベースインポート
     */
    private function importToDatabase($validData, $batchId) {
        $this->db->beginTransaction();
        
        try {
            $successCount = 0;
            $duplicateCount = 0;
            
            foreach ($validData as $row) {
                // 1. 配達先企業確保
                $companyId = $this->ensureCompany($row);
                
                // 2. 部署確保
                $departmentId = $this->ensureDepartment($row, $companyId);
                
                // 3. 給食業者確保
                $supplierId = $this->ensureSupplier($row);
                
                // 4. 商品確保
                $productId = $this->ensureProduct($row, $supplierId);
                
                // 5. 利用者確保
                $userId = $this->ensureUser($row, $companyId, $departmentId);
                
                // 6. 注文データ挿入
                $orderId = $this->insertOrderData($row, $batchId, $companyId, $departmentId, $userId, $productId, $supplierId);
                
                if ($orderId) {
                    $successCount++;
                } else {
                    $duplicateCount++;
                }
            }
            
            $this->db->commit();
            
            return [
                'success' => $successCount,
                'duplicate' => $duplicateCount
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * 配達先企業確保
     */
    private function ensureCompany($row) {
        $companyCode = $row['company_code'] ?? '';
        $companyName = $row['company_name'] ?? '';
        
        if (empty($companyCode) || empty($companyName)) {
            throw new Exception("企業コードまたは企業名が空です");
        }
        
        // 既存チェック
        $sql = "SELECT id FROM companies WHERE company_code = ?";
        $stmt = $this->db->query($sql, [$companyCode]);
        $existingId = $stmt->fetchColumn();
        
        if ($existingId) {
            return $existingId;
        }
        
        // 新規作成
        $sql = "INSERT INTO companies (company_code, company_name, created_at, updated_at) 
                VALUES (?, ?, NOW(), NOW())";
        
        $this->db->query($sql, [$companyCode, $companyName]);
        return $this->db->lastInsertId();
    }
    
    /**
     * 部署確保
     */
    private function ensureDepartment($row, $companyId) {
        $departmentCode = $row['department_code'] ?? '';
        $departmentName = $row['department_name'] ?? '';
        
        if (empty($departmentCode) || empty($departmentName)) {
            throw new Exception("部署コードまたは部署名が空です");
        }
        
        // 既存チェック
        $sql = "SELECT id FROM departments WHERE department_code = ? AND company_id = ?";
        $stmt = $this->db->query($sql, [$departmentCode, $companyId]);
        $existingId = $stmt->fetchColumn();
        
        if ($existingId) {
            return $existingId;
        }
        
        // 新規作成
        $sql = "INSERT INTO departments (company_id, department_code, department_name, created_at, updated_at) 
                VALUES (?, ?, ?, NOW(), NOW())";
        
        $this->db->query($sql, [$companyId, $departmentCode, $departmentName]);
        return $this->db->lastInsertId();
    }
    
    /**
     * 給食業者確保
     */
    private function ensureSupplier($row) {
        $supplierCode = $row['supplier_code'] ?? '';
        $supplierName = $row['supplier_name'] ?? '';
        
        if (empty($supplierCode) || empty($supplierName)) {
            throw new Exception("給食業者コードまたは給食業者名が空です");
        }
        
        // 既存チェック
        $sql = "SELECT id FROM suppliers WHERE supplier_code = ?";
        $stmt = $this->db->query($sql, [$supplierCode]);
        $existingId = $stmt->fetchColumn();
        
        if ($existingId) {
            return $existingId;
        }
        
        // 新規作成
        $sql = "INSERT INTO suppliers (supplier_code, supplier_name, created_at, updated_at) 
                VALUES (?, ?, NOW(), NOW())";
        
        $this->db->query($sql, [$supplierCode, $supplierName]);
        return $this->db->lastInsertId();
    }
    
    /**
     * 商品確保
     */
    private function ensureProduct($row, $supplierId) {
        $productCode = $row['product_code'] ?? '';
        $productName = $row['product_name'] ?? '';
        $categoryCode = $row['category_code'] ?? '';
        $categoryName = $row['category_name'] ?? '';
        $unitPrice = $row['unit_price'] ?? 0;
        
        if (empty($productCode) || empty($productName)) {
            throw new Exception("商品コードまたは商品名が空です");
        }
        
        // 既存チェック
        $sql = "SELECT id FROM products WHERE product_code = ? AND supplier_id = ?";
        $stmt = $this->db->query($sql, [$productCode, $supplierId]);
        $existingId = $stmt->fetchColumn();
        
        if ($existingId) {
            return $existingId;
        }
        
        // 新規作成
        $sql = "INSERT INTO products (supplier_id, product_code, product_name, category_code, category_name, unit_price, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())";
        
        $this->db->query($sql, [$supplierId, $productCode, $productName, $categoryCode, $categoryName, $unitPrice]);
        return $this->db->lastInsertId();
    }
    
    /**
     * 利用者確保
     */
    private function ensureUser($row, $companyId, $departmentId) {
        $userCode = $row['user_code'] ?? '';
        $userName = $row['user_name'] ?? '';
        $employeeTypeCode = $row['employee_type_code'] ?? '';
        $employeeTypeName = $row['employee_type_name'] ?? '';
        
        if (empty($userCode) || empty($userName)) {
            throw new Exception("利用者コードまたは利用者名が空です");
        }
        
        // 既存チェック
        $sql = "SELECT id FROM users WHERE user_code = ? AND company_id = ?";
        $stmt = $this->db->query($sql, [$userCode, $companyId]);
        $existingId = $stmt->fetchColumn();
        
        if ($existingId) {
            return $existingId;
        }
        
        // 新規作成
        $sql = "INSERT INTO users (company_id, department_id, user_code, user_name, employee_type_code, employee_type_name, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())";
        
        $this->db->query($sql, [$companyId, $departmentId, $userCode, $userName, $employeeTypeCode, $employeeTypeName]);
        return $this->db->lastInsertId();
    }
    
    /**
     * 注文データ挿入
     */
    private function insertOrderData($row, $batchId, $companyId, $departmentId, $userId, $productId, $supplierId) {
        // 重複チェック
        $sql = "SELECT id FROM orders WHERE user_id = ? AND product_id = ? AND delivery_date = ? AND delivery_time = ?";
        $stmt = $this->db->query($sql, [
            $userId,
            $productId,
            $row['delivery_date'],
            $row['delivery_time'] ?? ''
        ]);
        
        if ($stmt->fetchColumn()) {
            return false; // 重複
        }
        
        // 新規挿入
        $sql = "INSERT INTO orders (
                    batch_id, company_id, department_id, user_id, supplier_id, product_id,
                    delivery_date, delivery_time, quantity, unit_price, total_amount, 
                    notes, cooperation_code, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
        
        $this->db->query($sql, [
            $batchId,
            $companyId,
            $departmentId,
            $userId,
            $supplierId,
            $productId,
            $row['delivery_date'],
            $row['delivery_time'] ?? '',
            $row['quantity'] ?? 1,
            $row['unit_price'] ?? 0,
            $row['total_amount'] ?? 0,
            $row['notes'] ?? '',
            $row['cooperation_code'] ?? ''
        ]);
        
        return $this->db->lastInsertId();
    }
    
    /**
     * インポートログ記録
     */
    private function logImport($batchId, $filePath, $importResult, $validationResult, $startTime) {
        $fileName = basename($filePath);
        $fileSize = filesize($filePath);
        $processingTime = round(microtime(true) - $startTime, 2);
        
        $sql = "INSERT INTO import_logs (
                    batch_id, file_name, file_type, file_size, encoding,
                    total_records, success_records, error_records, duplicate_records,
                    import_start, import_end, status, error_details, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $errorDetails = !empty($validationResult['errors']) ? json_encode($validationResult['errors'], JSON_UNESCAPED_UNICODE) : null;
        $status = empty($validationResult['errors']) ? 'completed' : 'completed_with_errors';
        
        $this->db->query($sql, [
            $batchId, $fileName, 'smiley_meal_order', $fileSize, 'UTF-8',
            $importResult['success'] + count($validationResult['errors']), 
            $importResult['success'], 
            count($validationResult['errors']), 
            $importResult['duplicate'],
            date('Y-m-d H:i:s', $startTime), 
            date('Y-m-d H:i:s'), 
            $status, 
            $errorDetails, 
            'system'
        ]);
    }
    
    /**
     * エラーログ記録
     */
    private function logError($batchId, $errorMessage) {
        $sql = "INSERT INTO import_logs (batch_id, status, error_details, created_at) 
                VALUES (?, 'failed', ?, NOW())";
        
        try {
            $this->db->query($sql, [$batchId, $errorMessage]);
        } catch (Exception $e) {
            error_log("Import Error [{$batchId}]: {$errorMessage}");
        }
    }
    
    /**
     * バッチ処理（大容量CSV対応）
     */
    public function importLargeCSV($filePath, $options = []) {
        set_time_limit(300); // 5分
        ini_set('memory_limit', '256M');
        
        $startTime = microtime(true);
        $batchId = 'LARGE_' . date('YmdHis') . '_' . uniqid();
        
        try {
            $handle = fopen($filePath, 'r');
            
            if (!$handle) {
                throw new Exception("ファイルを開けません: " . $filePath);
            }
            
            // ヘッダー読み取り
            $headers = fgetcsv($handle);
            if (!$headers) {
                throw new Exception("ヘッダー行が読み取れません");
            }
            
            $batch = [];
            $rowCount = 0;
            $totalSuccess = 0;
            $totalErrors = 0;
            
            while (($row = fgetcsv($handle)) !== FALSE) {
                $rowCount++;
                
                // データ正規化
                $normalizedRow = $this->normalizeSingleRow($headers, $row, $rowCount);
                $batch[] = $normalizedRow;
                
                // バッチサイズに達したら処理
                if (count($batch) >= $this->batchSize) {
                    $result = $this->processBatch($batch, $batchId);
                    $totalSuccess += $result['success'];
                    $totalErrors += $result['errors'];
                    $batch = [];
                    
                    // メモリクリア
                    if ($rowCount % 5000 === 0) {
                        gc_collect_cycles();
                    }
                }
            }
            
            // 残りのデータを処理
            if (!empty($batch)) {
                $result = $this->processBatch($batch, $batchId);
                $totalSuccess += $result['success'];
                $totalErrors += $result['errors'];
            }
            
            fclose($handle);
            
            // 処理結果ログ
            $processingTime = round(microtime(true) - $startTime, 2);
            $this->logLargeImport($batchId, $filePath, $rowCount, $totalSuccess, $totalErrors, $processingTime);
            
            return [
                'success' => true,
                'batch_id' => $batchId,
                'stats' => [
                    'total' => $rowCount,
                    'success' => $totalSuccess,
                    'errors' => $totalErrors
                ],
                'processing_time' => $processingTime
            ];
            
        } catch (Exception $e) {
            $this->logError($batchId, $e->getMessage());
            throw new Exception("大容量CSVインポート処理エラー: " . $e->getMessage());
        }
    }
    
    /**
     * 単一行正規化
     */
    private function normalizeSingleRow($headers, $row, $rowNumber) {
        $normalizedRow = [
            'row_number' => $rowNumber + 1,
            'raw_data' => $row
        ];
        
        foreach ($headers as $index => $header) {
            $normalizedHeader = trim($header);
            $mappedField = $this->fieldMapping[$normalizedHeader] ?? null;
            
            if ($mappedField) {
                $normalizedRow[$mappedField] = trim($row[$index] ?? '');
            }
        }
        
        return $normalizedRow;
    }
    
    /**
     * バッチ処理
     */
    private function processBatch($batch, $batchId) {
        $validationResult = $this->validateData($batch);
        
        if (!empty($validationResult['valid_data'])) {
            $importResult = $this->importToDatabase($validationResult['valid_data'], $batchId);
            return [
                'success' => $importResult['success'],
                'errors' => count($validationResult['errors'])
            ];
        }
        
        return [
            'success' => 0,
            'errors' => count($validationResult['errors'])
        ];
    }
    
    /**
     * 大容量インポートログ記録
     */
    private function logLargeImport($batchId, $filePath, $totalRecords, $successRecords, $errorRecords, $processingTime) {
        $fileName = basename($filePath);
        $fileSize = filesize($filePath);
        
        $sql = "INSERT INTO import_logs (
                    batch_id, file_name, file_type, file_size, encoding,
                    total_records, success_records, error_records, duplicate_records,
                    import_start, import_end, status, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $status = $errorRecords > 0 ? 'completed_with_errors' : 'completed';
        
        $this->db->query($sql, [
            $batchId, $fileName, 'smiley_large_csv', $fileSize, 'UTF-8',
            $totalRecords, $successRecords, $errorRecords, 0,
            date('Y-m-d H:i:s'), date('Y-m-d H:i:s'), $status, 'system'
        ]);
    }
}
?>
