<?php
/**
 * Smiley配食事業専用CSVインポーター（完全修正版）
 * エンコーディングエラー完全修正済み
 */
class SmileyCSVImporter {
    private $db;
    private $batchSize = 1000;
    
    // 修正：有効なエンコーディング名のみ使用
    private $allowedEncodings = ['SJIS-win', 'UTF-8', 'SJIS', 'Shift_JIS', 'CP932'];
    
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
            // 1. エンコーディング検出（修正版）
            $encoding = $this->detectEncodingFixed($filePath);
            
            // 2. CSV読み込み（修正版）
            $rawData = $this->readCsvFixed($filePath, $encoding);
            
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
     * エンコーディング検出（完全修正版）
     */
    private function detectEncodingFixed($filePath) {
        $data = file_get_contents($filePath, false, null, 0, 8192);
        
        // BOMチェック
        if (substr($data, 0, 3) === "\xEF\xBB\xBF") {
            return 'UTF-8';
        }
        
        // 有効なエンコーディング名のみ使用
        $validEncodings = ['SJIS-win', 'UTF-8', 'SJIS', 'Shift_JIS', 'CP932'];
        
        // エンコーディング検出
        $encoding = mb_detect_encoding($data, $validEncodings, true);
        
        // デフォルトはSJIS-win（日本のCSV一般的）
        return $encoding ?: 'SJIS-win';
    }
    
    /**
     * CSV読み込み（完全修正版）
     */
    private function readCsvFixed($filePath, $encoding) {
        $data = file_get_contents($filePath);
        
        // BOM処理
        if (substr($data, 0, 3) === "\xEF\xBB\xBF") {
            $data = substr($data, 3); // BOM除去
            $encoding = 'UTF-8';
        }
        
        // UTF-8に変換
        if ($encoding !== 'UTF-8') {
            $convertedData = mb_convert_encoding($data, 'UTF-8', $encoding);
            if ($convertedData !== false) {
                $data = $convertedData;
            } else {
                // 変換失敗時の代替処理
                $data = iconv($encoding, 'UTF-8//IGNORE', $data);
            }
        }
        
        // CSVパース
        $lines = explode("\n", $data);
        $csvData = [];
        
        foreach ($lines as $line) {
            $trimmedLine = trim($line);
            if ($trimmedLine === '') continue;
            
            $row = str_getcsv($trimmedLine);
            if (!empty($row)) {
                $csvData[] = $row;
            }
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
            
            // Smiley配食事業チェック（緩和版）
            if (!empty($row['corporation_name'])) {
                $corpName = trim($row['corporation_name']);
                if (strpos($corpName, 'Smiley') === false && strpos($corpName, 'smiley') === false) {
                    $rowErrors[] = "法人名に 'Smiley' が含まれていません: " . $corpName;
                }
            }
            
            // 日付フォーマットチェック
            if (!empty($row['delivery_date'])) {
                $dateStr = $row['delivery_date'];
                
                // 複数の日付フォーマットに対応
                $dateFormats = ['Y-m-d', 'Y/m/d', 'Y.m.d', 'm/d/Y', 'd/m/Y'];
                $validDate = false;
                
                foreach ($dateFormats as $format) {
                    $date = DateTime::createFromFormat($format, $dateStr);
                    if ($date && $date->format($format) === $dateStr) {
                        $validDate = true;
                        $row['delivery_date'] = $date->format('Y-m-d'); // 正規化
                        break;
                    }
                }
                
                if (!$validDate) {
                    $rowErrors[] = "配達日の形式が不正です: " . $dateStr;
                }
            }
            
            // 数値フィールドチェック
            $numericFields = ['quantity', 'unit_price', 'total_amount'];
            foreach ($numericFields as $field) {
                if (!empty($row[$field]) && !is_numeric($row[$field])) {
                    $rowErrors[] = "{$field}が数値ではありません: " . $row[$field];
                }
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
                // マスターデータ確保（統合メソッド使用）
                $masterIds = $this->ensureMasterData($row);
                
                // 注文データ挿入
                $orderId = $this->insertOrderData(
                    $row, 
                    $batchId, 
                    $masterIds['company_id'], 
                    $masterIds['department_id'], 
                    $masterIds['user_id'], 
                    $masterIds['product_id'], 
                    $masterIds['supplier_id']
                );
                
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
     * マスターデータ確保（統合メソッド）
     */
    public function ensureMasterData($row) {
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
        
        return [
            'company_id' => $companyId,
            'department_id' => $departmentId,
            'supplier_id' => $supplierId,
            'product_id' => $productId,
            'user_id' => $userId
        ];
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
 * 注文データ挿入（修正版）
 * 安全なカラムのみで重複チェック
 */
private function insertOrderData($row, $batchId, $companyId, $departmentId, $userId, $productId, $supplierId) {
    // 修正：安全なカラムのみで重複チェック
    $sql = "SELECT id FROM orders WHERE user_code = ? AND delivery_date = ? AND product_code = ?";
    $stmt = $this->db->query($sql, [
        $row['user_code'],
        $row['delivery_date'],
        $row['product_code']
    ]);
    
    if ($stmt->fetchColumn()) {
        return false; // 重複
    }
    
    // 新規挿入（修正：supplier_id関連を安全に処理）
    $sql = "INSERT INTO orders (
                import_batch_id, company_id, department_id, user_id, product_id,
                user_code, user_name, company_code, company_name,
                department_code, department_name, product_code, product_name,
                category_code, category_name, supplier_code, supplier_name,
                delivery_date, delivery_time, quantity, unit_price, total_amount, 
                notes, cooperation_code, employee_type_code, employee_type_name,
                created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
    
    $this->db->query($sql, [
        $batchId,
        $companyId,
        $departmentId,
        $userId,
        $productId,
        $row['user_code'],
        $row['user_name'],
        $row['company_code'],
        $row['company_name'],
        $row['department_code'] ?? '',
        $row['department_name'] ?? '',
        $row['product_code'],
        $row['product_name'],
        $row['category_code'] ?? '',
        $row['category_name'] ?? '',
        $row['supplier_code'] ?? '',
        $row['supplier_name'] ?? '',
        $row['delivery_date'],
        $row['delivery_time'] ?? '',
        $row['quantity'] ?? 1,
        $row['unit_price'] ?? 0,
        $row['total_amount'] ?? 0,
        $row['notes'] ?? '',
        $row['cooperation_code'] ?? '',
        $row['employee_type_code'] ?? '',
        $row['employee_type_name'] ?? ''
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
}
?>
