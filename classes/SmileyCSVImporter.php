<?php
/**
 * Smiley配食事業専用CSVインポーター（完全修正版）
 * 注文データ未反映問題対応
 */
class SmileyCSVImporter {
    private $db;
    private $allowedEncodings = ['SJIS-win', 'UTF-8', 'UTF-8-BOM'];
    
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
    
    public function __construct($db = null) {
        if ($db instanceof Database) {
            $this->db = $db;
        } else {
            $this->db = Database::getInstance();
        }
    }
    
    /**
     * CSVファイルインポート（メイン処理）
     */
    public function importFile($filePath, $options = []) {
        $startTime = microtime(true);
        $batchId = 'BATCH_' . date('YmdHis') . '_' . uniqid();
        
        try {
            // 1. エンコーディング検出・変換
            $encoding = $this->detectEncoding($filePath);
            $convertedData = $this->convertEncoding($filePath, $encoding);
            
            // 2. CSV解析
            $rawData = $this->parseCsv($convertedData);
            
            // 3. データ正規化
            $normalizedData = $this->normalizeData($rawData);
            
            // 4. データ検証
            $validationResult = $this->validateData($normalizedData);
            
            // 5. データベース登録
            $importResult = $this->importToDatabase($validationResult['valid_data'], $batchId);
            
            // 6. ログ記録
            $this->logImport($batchId, $filePath, $importResult, $validationResult, $startTime);
            
            return [
                'success' => true,
                'batch_id' => $batchId,
                'summary' => [
                    'total_records' => $importResult['total'],
                    'success_records' => $importResult['success'],
                    'error_records' => count($validationResult['errors']),
                    'duplicate_records' => $importResult['duplicate']
                ],
                'errors' => $validationResult['errors'],
                'processing_time' => round(microtime(true) - $startTime, 2)
            ];
            
        } catch (Exception $e) {
            $this->logError($batchId, $e->getMessage());
            throw new Exception("CSVインポートエラー: " . $e->getMessage());
        }
    }
    
    /**
     * エンコーディング検出
     */
    private function detectEncoding($filePath) {
        $content = file_get_contents($filePath);
        
        // BOM検出
        if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
            return 'UTF-8-BOM';
        }
        
        // 文字エンコーディング検出
        $detected = mb_detect_encoding($content, ['UTF-8', 'SJIS-win', 'EUC-JP'], true);
        
        return $detected ?: 'SJIS-win'; // デフォルトはSJIS-win
    }
    
    /**
     * エンコーディング変換
     */
    private function convertEncoding($filePath, $encoding) {
        $content = file_get_contents($filePath);
        
        // BOM除去
        if ($encoding === 'UTF-8-BOM') {
            $content = substr($content, 3);
            $encoding = 'UTF-8';
        }
        
        // UTF-8に変換
        if ($encoding !== 'UTF-8') {
            $content = mb_convert_encoding($content, 'UTF-8', $encoding);
        }
        
        return $content;
    }
    
    /**
     * CSV解析
     */
    private function parseCsv($content) {
        $lines = explode("\n", $content);
        $data = [];
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            $row = str_getcsv($line);
            $data[] = $row;
        }
        
        return $data;
    }
    
    /**
     * データ正規化
     */
    private function normalizeData($rawData) {
        if (empty($rawData)) {
            throw new Exception('CSVデータが空です');
        }
        
        $headers = array_shift($rawData);
        $normalizedData = [];
        
        foreach ($rawData as $rowIndex => $row) {
            $normalizedRow = [];
            
            foreach ($headers as $colIndex => $header) {
                $value = isset($row[$colIndex]) ? trim($row[$colIndex]) : '';
                $fieldName = $this->fieldMapping[$header] ?? $header;
                $normalizedRow[$fieldName] = $value;
            }
            
            // 必須フィールドチェック
            if (!empty($normalizedRow['user_code']) && !empty($normalizedRow['product_code'])) {
                $normalizedData[] = $normalizedRow;
            }
        }
        
        return $normalizedData;
    }
    
    /**
     * データ検証
     */
    private function validateData($data) {
        $validData = [];
        $errors = [];
        
        foreach ($data as $index => $row) {
            $rowErrors = [];
            
            // 必須フィールド検証
            if (empty($row['user_code'])) {
                $rowErrors[] = '利用者コードが空です';
            }
            
            if (empty($row['product_code'])) {
                $rowErrors[] = '商品コードが空です';
            }
            
            if (empty($row['delivery_date'])) {
                $rowErrors[] = '配達日が空です';
            }
            
            // 日付フォーマット検証
            if (!empty($row['delivery_date'])) {
                $date = DateTime::createFromFormat('Y-m-d', $row['delivery_date']);
                if (!$date) {
                    $date = DateTime::createFromFormat('Y/m/d', $row['delivery_date']);
                    if ($date) {
                        $row['delivery_date'] = $date->format('Y-m-d');
                    } else {
                        $rowErrors[] = '配達日の形式が正しくありません';
                    }
                }
            }
            
            // 数値検証
            if (!empty($row['quantity']) && !is_numeric($row['quantity'])) {
                $rowErrors[] = '数量は数値である必要があります';
            }
            
            if (!empty($row['unit_price']) && !is_numeric($row['unit_price'])) {
                $rowErrors[] = '単価は数値である必要があります';
            }
            
            if (!empty($row['total_amount']) && !is_numeric($row['total_amount'])) {
                $rowErrors[] = '金額は数値である必要があります';
            }
            
            if (empty($rowErrors)) {
                $validData[] = $row;
            } else {
                $errors[] = [
                    'row' => $index + 2, // ヘッダー行を考慮
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
     * データベースへのインポート
     */
    private function importToDatabase($validData, $batchId) {
        $total = count($validData);
        $success = 0;
        $duplicate = 0;
        
        foreach ($validData as $row) {
            try {
                // マスタデータ作成/取得
                $this->ensureMasterData($row);
                
                // 注文データ挿入
                $this->insertOrderData($row, $batchId);
                $success++;
                
            } catch (Exception $e) {
                // 重複エラーの場合
                if (strpos($e->getMessage(), 'Duplicate') !== false) {
                    $duplicate++;
                } else {
                    error_log("Order insert error: " . $e->getMessage());
                }
            }
        }
        
        return [
            'total' => $total,
            'success' => $success,
            'duplicate' => $duplicate
        ];
    }
    
    /**
     * マスタデータ確保
     */
    private function ensureMasterData($row) {
        // 企業データ確保
        if (!empty($row['company_code'])) {
            $this->ensureCompany($row);
        }
        
        // 部署データ確保
        if (!empty($row['department_code']) && !empty($row['company_code'])) {
            $this->ensureDepartment($row);
        }
        
        // 利用者データ確保
        if (!empty($row['user_code'])) {
            $this->ensureUser($row);
        }
        
        // 商品データ確保
        if (!empty($row['product_code'])) {
            $this->ensureProduct($row);
        }
        
        // 業者データ確保
        if (!empty($row['supplier_code'])) {
            $this->ensureSupplier($row);
        }
    }
    
    /**
     * 企業データ確保
     */
    private function ensureCompany($row) {
        $sql = "INSERT IGNORE INTO companies (company_code, company_name, created_at, updated_at) 
                VALUES (?, ?, NOW(), NOW())";
        
        $this->db->query($sql, [
            $row['company_code'],
            $row['company_name'] ?? $row['company_code']
        ]);
    }
    
    /**
     * 部署データ確保
     */
    private function ensureDepartment($row) {
        $companyId = $this->getCompanyId($row['company_code']);
        
        if ($companyId) {
            $sql = "INSERT IGNORE INTO departments (company_id, department_code, department_name, created_at, updated_at) 
                    VALUES (?, ?, ?, NOW(), NOW())";
            
            $this->db->query($sql, [
                $companyId,
                $row['department_code'],
                $row['department_name'] ?? $row['department_code']
            ]);
        }
    }
    
    /**
     * 利用者データ確保
     */
    private function ensureUser($row) {
        $companyId = null;
        $departmentId = null;
        
        if (!empty($row['company_code'])) {
            $companyId = $this->getCompanyId($row['company_code']);
        }
        
        if (!empty($row['department_code']) && $companyId) {
            $departmentId = $this->getDepartmentId($companyId, $row['department_code']);
        }
        
        $sql = "INSERT INTO users (user_code, user_name, company_id, department_id, employee_type_code, employee_type_name, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
                ON DUPLICATE KEY UPDATE 
                user_name = VALUES(user_name),
                company_id = VALUES(company_id),
                department_id = VALUES(department_id),
                employee_type_code = VALUES(employee_type_code),
                employee_type_name = VALUES(employee_type_name),
                updated_at = NOW()";
        
        $this->db->query($sql, [
            $row['user_code'],
            $row['user_name'] ?? $row['user_code'],
            $companyId,
            $departmentId,
            $row['employee_type_code'] ?? null,
            $row['employee_type_name'] ?? null
        ]);
    }
    
    /**
     * 商品データ確保
     */
    private function ensureProduct($row) {
        $sql = "INSERT IGNORE INTO products (product_code, product_name, category, created_at, updated_at) 
                VALUES (?, ?, ?, NOW(), NOW())";
        
        $this->db->query($sql, [
            $row['product_code'],
            $row['product_name'] ?? $row['product_code'],
            $row['category_name'] ?? null
        ]);
    }
    
    /**
     * 業者データ確保
     */
    private function ensureSupplier($row) {
        $sql = "INSERT IGNORE INTO suppliers (supplier_code, supplier_name, created_at, updated_at) 
                VALUES (?, ?, NOW(), NOW())";
        
        $this->db->query($sql, [
            $row['supplier_code'],
            $row['supplier_name'] ?? $row['supplier_code']
        ]);
    }
    
    /**
     * 注文データ挿入 - 修正版
     */
    private function insertOrderData($row, $batchId) {
        // 関連IDを取得
        $userId = $this->getUserId($row['user_code']);
        $companyId = !empty($row['company_code']) ? $this->getCompanyId($row['company_code']) : null;
        $departmentId = (!empty($row['department_code']) && $companyId) ? $this->getDepartmentId($companyId, $row['department_code']) : null;
        $productId = !empty($row['product_code']) ? $this->getProductId($row['product_code']) : null;
        $supplierId = !empty($row['supplier_code']) ? $this->getSupplierId($row['supplier_code']) : null;
        
        // order_dateのデフォルト値設定
        $orderDate = !empty($row['order_date']) ? $row['order_date'] : $row['delivery_date'];
        
        // デフォルト値設定
        $quantity = !empty($row['quantity']) ? intval($row['quantity']) : 1;
        $unitPrice = !empty($row['unit_price']) ? floatval($row['unit_price']) : 0.00;
        $totalAmount = !empty($row['total_amount']) ? floatval($row['total_amount']) : ($quantity * $unitPrice);
        
        // 重複チェック用ユニークキー
        $uniqueKey = $row['user_code'] . '|' . $row['delivery_date'] . '|' . $row['product_code'] . '|' . ($row['cooperation_code'] ?? '');
        
        $sql = "INSERT INTO orders (
                    order_date, delivery_date, delivery_time,
                    user_id, user_code, user_name,
                    company_id, company_code, company_name,
                    department_id, department_code, department_name,
                    product_id, product_code, product_name,
                    category_code, category_name,
                    supplier_id, supplier_code, supplier_name,
                    corporation_code, corporation_name,
                    employee_type_code, employee_type_name,
                    quantity, unit_price, total_amount,
                    notes, cooperation_code, import_batch_id,
                    created_at, updated_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW()
                )";
        
        $params = [
            $orderDate,
            $row['delivery_date'],
            $row['delivery_time'] ?? null,
            $userId,
            $row['user_code'],
            $row['user_name'] ?? '',
            $companyId,
            $row['company_code'] ?? null,
            $row['company_name'] ?? null,
            $departmentId,
            $row['department_code'] ?? null,
            $row['department_name'] ?? null,
            $productId,
            $row['product_code'],
            $row['product_name'] ?? '',
            $row['category_code'] ?? null,
            $row['category_name'] ?? null,
            $supplierId,
            $row['supplier_code'] ?? null,
            $row['supplier_name'] ?? null,
            $row['corporation_code'] ?? null,
            $row['corporation_name'] ?? null,
            $row['employee_type_code'] ?? null,
            $row['employee_type_name'] ?? null,
            $quantity,
            $unitPrice,
            $totalAmount,
            $row['notes'] ?? null,
            $row['cooperation_code'] ?? null,
            $batchId
        ];
        
        $this->db->query($sql, $params);
    }
    
    /**
     * 企業ID取得
     */
    private function getCompanyId($companyCode) {
        $sql = "SELECT id FROM companies WHERE company_code = ?";
        $stmt = $this->db->query($sql, [$companyCode]);
        $result = $stmt->fetch();
        return $result ? $result['id'] : null;
    }
    
    /**
     * 部署ID取得
     */
    private function getDepartmentId($companyId, $departmentCode) {
        $sql = "SELECT id FROM departments WHERE company_id = ? AND department_code = ?";
        $stmt = $this->db->query($sql, [$companyId, $departmentCode]);
        $result = $stmt->fetch();
        return $result ? $result['id'] : null;
    }
    
    /**
     * 利用者ID取得
     */
    private function getUserId($userCode) {
        $sql = "SELECT id FROM users WHERE user_code = ?";
        $stmt = $this->db->query($sql, [$userCode]);
        $result = $stmt->fetch();
        return $result ? $result['id'] : null;
    }
    
    /**
     * 商品ID取得
     */
    private function getProductId($productCode) {
        $sql = "SELECT id FROM products WHERE product_code = ?";
        $stmt = $this->db->query($sql, [$productCode]);
        $result = $stmt->fetch();
        return $result ? $result['id'] : null;
    }
    
    /**
     * 業者ID取得
     */
    private function getSupplierId($supplierCode) {
        $sql = "SELECT id FROM suppliers WHERE supplier_code = ?";
        $stmt = $this->db->query($sql, [$supplierCode]);
        $result = $stmt->fetch();
        return $result ? $result['id'] : null;
    }
    
    /**
     * インポートログ記録
     */
    private function logImport($batchId, $filePath, $importResult, $validationResult, $startTime) {
        try {
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
                $importResult['total'], $importResult['success'], count($validationResult['errors']), $importResult['duplicate'],
                date('Y-m-d H:i:s', $startTime), date('Y-m-d H:i:s'), $status, $errorDetails, 'system'
            ]);
        } catch (Exception $e) {
            error_log("Import log error: " . $e->getMessage());
        }
    }
    
    /**
     * エラーログ記録
     */
    private function logError($batchId, $errorMessage) {
        try {
            $sql = "INSERT INTO import_logs (batch_id, status, error_details, created_at) 
                    VALUES (?, 'failed', ?, NOW())";
            
            $this->db->query($sql, [$batchId, $errorMessage]);
        } catch (Exception $e) {
            error_log("Import Error [{$batchId}]: {$errorMessage}");
        }
    }
}
?>
