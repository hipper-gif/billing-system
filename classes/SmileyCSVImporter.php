<?php
/**
 * Smiley配食事業CSVインポーター - 日付処理修正版
 */

class SmileyCSVImporter {
    private $db;
    private $fieldMapping;
    
    public function __construct($database) {
        $this->db = $database;
        $this->initializeFieldMapping();
    }
    
    /**
     * 日付処理を修正したフィールドマッピング
     */
    private function initializeFieldMapping() {
        $this->fieldMapping = [
            '配達日' => 'delivery_date',
            '社員CD' => 'user_code',
            '社員名' => 'user_name',
            '事業所CD' => 'company_code',
            '事業所名' => 'company_name',
            '部門CD' => 'department_code',
            '部門名' => 'department_name',
            '給食ﾒﾆｭｰCD' => 'product_code',
            '給食ﾒﾆｭｰ名' => 'product_name',
            '給食区分CD' => 'category_code',
            '給食区分名' => 'category_name',
            '給食業者CD' => 'supplier_code',
            '給食業者名' => 'supplier_name',
            '数量' => 'quantity',
            '単価' => 'unit_price',
            '金額' => 'total_amount',
            '受取時間' => 'delivery_time',
            '雇用形態CD' => 'employee_type_code',
            '雇用形態名' => 'employee_type_name',
            '備考' => 'notes',
            '法人名' => 'corporation_name',
            '法人CD' => 'corporation_code',
            '協力CD' => 'cooperation_code',
            '協力名' => 'cooperation_name'
        ];
    }
    
    /**
     * CSVインポート実行（日付処理修正版）
     */
    public function import($filePath) {
        $startTime = microtime(true);
        $batchId = $this->generateBatchId();
        
        try {
            // 1. ファイル読み込み・エンコーディング変換
            $csvContent = $this->readAndConvertFile($filePath);
            
            // 2. CSVパース（日付処理改善）
            $parsedData = $this->parseCSV($csvContent, $batchId);
            
            // 3. データ検証
            $validationResult = $this->validateData($parsedData['data']);
            
            // 4. 有効なデータのみインポート
            if (!empty($validationResult['valid_data'])) {
                $importResult = $this->importToDatabase($validationResult['valid_data'], $batchId);
                
                // 5. インポートログ記録
                $this->logImport($batchId, $filePath, $importResult, $validationResult, $startTime);
                
                return [
                    'success' => true,
                    'batch_id' => $batchId,
                    'summary' => $importResult,
                    'errors' => $validationResult['errors']
                ];
            } else {
                throw new Exception('有効なデータが見つかりませんでした');
            }
            
        } catch (Exception $e) {
            $this->logError($batchId, $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * CSV解析（日付処理改善版）
     */
    private function parseCSV($csvContent, $batchId) {
        $lines = explode("\n", $csvContent);
        $lines = array_filter($lines, function($line) {
            return trim($line) !== '';
        });
        
        if (empty($lines)) {
            throw new Exception('CSVファイルが空です');
        }
        
        // ヘッダー行処理
        $headerLine = array_shift($lines);
        $headers = str_getcsv($headerLine);
        $headers = array_map('trim', $headers);
        
        // ヘッダーマッピング確認
        $mappedHeaders = $this->mapHeaders($headers);
        
        $data = [];
        $rowNumber = 2; // ヘッダーの次の行から
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            $row = str_getcsv($line);
            $mappedRow = $this->mapRowData($row, $mappedHeaders, $rowNumber, $batchId);
            
            if ($mappedRow) {
                $data[] = $mappedRow;
            }
            
            $rowNumber++;
        }
        
        return [
            'headers' => $mappedHeaders,
            'data' => $data
        ];
    }
    
    /**
     * 行データマッピング（日付処理修正版）
     */
    private function mapRowData($row, $mappedHeaders, $rowNumber, $batchId) {
        $mappedRow = [];
        
        for ($i = 0; $i < count($mappedHeaders); $i++) {
            $fieldName = $mappedHeaders[$i];
            $value = isset($row[$i]) ? trim($row[$i]) : '';
            
            // 日付フィールドの特別処理
            if ($fieldName === 'delivery_date') {
                $mappedRow[$fieldName] = $this->processDateField($value, $rowNumber);
            }
            // 数値フィールドの処理
            elseif (in_array($fieldName, ['quantity', 'unit_price', 'total_amount'])) {
                $mappedRow[$fieldName] = $this->processNumericField($value, $fieldName);
            }
            // 時刻フィールドの処理
            elseif ($fieldName === 'delivery_time') {
                $mappedRow[$fieldName] = $this->processTimeField($value);
            }
            // その他の文字列フィールド
            else {
                $mappedRow[$fieldName] = $value;
            }
        }
        
        // 必須フィールドチェック
        if (empty($mappedRow['user_code']) || empty($mappedRow['user_name'])) {
            return null; // 必須データが欠けている行はスキップ
        }
        
        // 注文日を配達日と同じに設定（データがない場合）
        if (empty($mappedRow['order_date'])) {
            $mappedRow['order_date'] = $mappedRow['delivery_date'];
        }
        
        return $mappedRow;
    }
    
    /**
     * 日付フィールド処理（修正版）
     */
    private function processDateField($value, $rowNumber) {
        if (empty($value)) {
            // 空の場合は今日の日付を使用
            return date('Y-m-d');
        }
        
        // 様々な日付フォーマットに対応
        $dateFormats = [
            'Y-m-d',     // 2025-08-22
            'Y/m/d',     // 2025/08/22
            'm/d/Y',     // 08/22/2025
            'd/m/Y',     // 22/08/2025
            'Y年m月d日',  // 2025年8月22日
        ];
        
        foreach ($dateFormats as $format) {
            $date = DateTime::createFromFormat($format, $value);
            if ($date && $date->format($format) === $value) {
                return $date->format('Y-m-d');
            }
        }
        
        // Excel日付シリアル値の処理
        if (is_numeric($value) && $value > 25569) { // 1970年1月1日以降
            $unixTimestamp = ($value - 25569) * 86400;
            return date('Y-m-d', $unixTimestamp);
        }
        
        // どの形式でも解析できない場合
        error_log("日付解析失敗 行{$rowNumber}: {$value}");
        return date('Y-m-d'); // デフォルトで今日の日付
    }
    
    /**
     * 数値フィールド処理
     */
    private function processNumericField($value, $fieldName) {
        if (empty($value)) {
            return $fieldName === 'quantity' ? 1 : 0;
        }
        
        // カンマ区切り数値の処理
        $value = str_replace(',', '', $value);
        
        // 全角数字の変換
        $value = mb_convert_kana($value, 'n', 'UTF-8');
        
        return is_numeric($value) ? (float)$value : 0;
    }
    
    /**
     * 時刻フィールド処理
     */
    private function processTimeField($value) {
        if (empty($value)) {
            return '12:00:00'; // デフォルト配達時間
        }
        
        // 時刻形式の正規化
        $timeFormats = [
            'H:i:s',  // 12:30:00
            'H:i',    // 12:30
            'Hi',     // 1230
        ];
        
        foreach ($timeFormats as $format) {
            $time = DateTime::createFromFormat($format, $value);
            if ($time) {
                return $time->format('H:i:s');
            }
        }
        
        return '12:00:00'; // デフォルト値
    }
    
    /**
     * 注文データ挿入（修正版）
     */
    private function insertOrderData($row, $batchId) {
        // 関連IDの取得
        $userId = $this->getUserId($row['user_code']);
        $companyId = $this->getCompanyId($row['company_code']);
        $departmentId = $this->getDepartmentId($companyId, $row['department_code']);
        $supplierId = $this->getSupplierId($row['supplier_code']);
        $productId = $this->getProductId($row['product_code']);
        
        $sql = "INSERT INTO orders (
                    order_date, delivery_date, user_id, user_code, user_name,
                    company_id, company_code, company_name,
                    department_id, department_code, department_name,
                    product_id, product_code, product_name,
                    category_code, category_name,
                    supplier_id, supplier_code, supplier_name,
                    quantity, unit_price, total_amount,
                    delivery_time, employee_type_code, employee_type_name,
                    notes, cooperation_code, import_batch_id,
                    created_at, updated_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW()
                )";
        
        $this->db->query($sql, [
            $row['order_date'] ?? $row['delivery_date'],
            $row['delivery_date'],
            $userId, $row['user_code'], $row['user_name'],
            $companyId, $row['company_code'], $row['company_name'],
            $departmentId, $row['department_code'], $row['department_name'],
            $productId, $row['product_code'], $row['product_name'],
            $row['category_code'], $row['category_name'],
            $supplierId, $row['supplier_code'], $row['supplier_name'],
            $row['quantity'], $row['unit_price'], $row['total_amount'],
            $row['delivery_time'], $row['employee_type_code'], $row['employee_type_name'],
            $row['notes'], $row['cooperation_code'], $batchId
        ]);
    }
    
    // 他のメソッドは変更なし...
    // （readAndConvertFile, validateData, importToDatabase等は既存のまま）
    
    private function readAndConvertFile($filePath) {
        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new Exception('ファイルを読み込めませんでした');
        }
        
        // SJIS-win から UTF-8 への変換
        $encoding = mb_detect_encoding($content, ['SJIS-win', 'UTF-8', 'EUC-JP'], true);
        if ($encoding !== 'UTF-8') {
            $content = mb_convert_encoding($content, 'UTF-8', $encoding);
        }
        
        return $content;
    }
    
    private function mapHeaders($headers) {
        $mappedHeaders = [];
        foreach ($headers as $header) {
            $mappedHeaders[] = $this->fieldMapping[$header] ?? $header;
        }
        return $mappedHeaders;
    }
    
    private function generateBatchId() {
        return 'BATCH_' . date('YmdHis') . '_' . uniqid();
    }
    
    private function validateData($data) {
        $validData = [];
        $errors = [];
        
        foreach ($data as $index => $row) {
            $rowErrors = $this->validateRow($row, $index + 2);
            if (empty($rowErrors)) {
                $validData[] = $row;
            } else {
                $errors = array_merge($errors, $rowErrors);
            }
        }
        
        return [
            'valid_data' => $validData,
            'errors' => $errors
        ];
    }
    
    private function validateRow($row, $rowNumber) {
        $errors = [];
        
        // 必須フィールドチェック
        if (empty($row['user_code'])) {
            $errors[] = "行{$rowNumber}: 社員CDが必須です";
        }
        
        if (empty($row['user_name'])) {
            $errors[] = "行{$rowNumber}: 社員名が必須です";
        }
        
        // 日付妥当性チェック
        if (!$this->isValidDate($row['delivery_date'])) {
            $errors[] = "行{$rowNumber}: 配達日が無効です: {$row['delivery_date']}";
        }
        
        // 数値チェック
        if ($row['quantity'] <= 0) {
            $errors[] = "行{$rowNumber}: 数量は1以上である必要があります";
        }
        
        if ($row['unit_price'] < 0) {
            $errors[] = "行{$rowNumber}: 単価は0以上である必要があります";
        }
        
        return $errors;
    }
    
    private function isValidDate($dateString) {
        $date = DateTime::createFromFormat('Y-m-d', $dateString);
        return $date && $date->format('Y-m-d') === $dateString;
    }
    
    private function importToDatabase($validData, $batchId) {
        $stats = [
            'total' => count($validData),
            'success' => 0,
            'duplicate' => 0,
            'error' => 0
        ];
        
        $this->db->beginTransaction();
        
        try {
            foreach ($validData as $row) {
                // マスタデータの作成・更新
                $this->upsertMasterData($row);
                
                // 注文データの挿入
                $this->insertOrderData($row, $batchId);
                
                $stats['success']++;
            }
            
            $this->db->commit();
            return $stats;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    // その他のヘルパーメソッド（既存のまま）
    private function upsertMasterData($row) {
        $this->upsertCompany($row);
        $this->upsertDepartment($row);
        $this->upsertSupplier($row);
        $this->upsertProduct($row);
        $this->upsertUser($row);
    }
    
    private function upsertCompany($row) {
        if (empty($row['company_code']) || empty($row['company_name'])) return;
        
        $sql = "INSERT INTO companies (company_code, company_name, is_active, created_at, updated_at) 
                VALUES (?, ?, 1, NOW(), NOW())
                ON DUPLICATE KEY UPDATE 
                company_name = VALUES(company_name), updated_at = NOW()";
        
        $this->db->query($sql, [$row['company_code'], $row['company_name']]);
    }
    
    private function upsertDepartment($row) {
        if (empty($row['department_code']) || empty($row['department_name'])) return;
        
        $companyId = $this->getCompanyId($row['company_code']);
        if (!$companyId) return;
        
        $sql = "INSERT INTO departments (department_code, department_name, company_id, is_active, created_at, updated_at) 
                VALUES (?, ?, ?, 1, NOW(), NOW())
                ON DUPLICATE KEY UPDATE 
                department_name = VALUES(department_name), updated_at = NOW()";
        
        $this->db->query($sql, [$row['department_code'], $row['department_name'], $companyId]);
    }
    
    private function upsertSupplier($row) {
        if (empty($row['supplier_code']) || empty($row['supplier_name'])) return;
        
        $sql = "INSERT INTO suppliers (supplier_code, supplier_name, is_active, created_at, updated_at) 
                VALUES (?, ?, 1, NOW(), NOW())
                ON DUPLICATE KEY UPDATE 
                supplier_name = VALUES(supplier_name), updated_at = NOW()";
        
        $this->db->query($sql, [$row['supplier_code'], $row['supplier_name']]);
    }
    
    private function upsertProduct($row) {
        if (empty($row['product_code']) || empty($row['product_name'])) return;
        
        $supplierId = !empty($row['supplier_code']) ? $this->getSupplierId($row['supplier_code']) : null;
        
        $sql = "INSERT INTO products (product_code, product_name, category_code, category_name, supplier_id, is_active, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, 1, NOW(), NOW())
                ON DUPLICATE KEY UPDATE 
                product_name = VALUES(product_name), 
                category_code = VALUES(category_code), 
                category_name = VALUES(category_name), 
                supplier_id = VALUES(supplier_id), 
                updated_at = NOW()";
        
        $this->db->query($sql, [
            $row['product_code'], $row['product_name'], 
            $row['category_code'], $row['category_name'], $supplierId
        ]);
    }
    
    private function upsertUser($row) {
        if (empty($row['user_code']) || empty($row['user_name'])) return;
        
        $companyId = $this->getCompanyId($row['company_code']);
        $departmentId = !empty($row['department_code']) ? $this->getDepartmentId($companyId, $row['department_code']) : null;
        
        $sql = "INSERT INTO users (
                    user_code, user_name, company_id, department_id, 
                    company_name, department, employee_type_code, employee_type_name,
                    is_active, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())
                ON DUPLICATE KEY UPDATE 
                user_name = VALUES(user_name),
                company_id = VALUES(company_id),
                department_id = VALUES(department_id),
                company_name = VALUES(company_name),
                department = VALUES(department),
                employee_type_code = VALUES(employee_type_code),
                employee_type_name = VALUES(employee_type_name),
                updated_at = NOW()";
        
        $this->db->query($sql, [
            $row['user_code'], $row['user_name'], $companyId, $departmentId,
            $row['company_name'], $row['department_name'], 
            $row['employee_type_code'], $row['employee_type_name']
        ]);
    }
    
    // ID取得メソッド群
    private function getCompanyId($companyCode) {
        if (empty($companyCode)) return null;
        $sql = "SELECT id FROM companies WHERE company_code = ?";
        $stmt = $this->db->query($sql, [$companyCode]);
        return $stmt->fetchColumn();
    }
    
    private function getDepartmentId($companyId, $departmentCode) {
        if (empty($companyId) || empty($departmentCode)) return null;
        $sql = "SELECT id FROM departments WHERE company_id = ? AND department_code = ?";
        $stmt = $this->db->query($sql, [$companyId, $departmentCode]);
        return $stmt->fetchColumn();
    }
    
    private function getSupplierId($supplierCode) {
        if (empty($supplierCode)) return null;
        $sql = "SELECT id FROM suppliers WHERE supplier_code = ?";
        $stmt = $this->db->query($sql, [$supplierCode]);
        return $stmt->fetchColumn();
    }
    
    private function getProductId($productCode) {
        if (empty($productCode)) return null;
        $sql = "SELECT id FROM products WHERE product_code = ?";
        $stmt = $this->db->query($sql, [$productCode]);
        return $stmt->fetchColumn();
    }
    
    private function getUserId($userCode) {
        if (empty($userCode)) return null;
        $sql = "SELECT id FROM users WHERE user_code = ?";
        $stmt = $this->db->query($sql, [$userCode]);
        return $stmt->fetchColumn();
    }
    
    private function logImport($batchId, $filePath, $importResult, $validationResult, $startTime) {
        // ログ記録処理（既存のまま）
    }
    
    private function logError($batchId, $errorMessage) {
        error_log("CSV Import Error [{$batchId}]: {$errorMessage}");
    }
}
?>
