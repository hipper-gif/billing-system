<?php
/**
 * Smiley配食事業専用CSVインポートクラス（修正版）
 * Database Singleton パターン対応
 * 23フィールドCSVファイルの完全対応
 */

require_once __DIR__ . '/../config/database.php';

class SmileyCSVImporter {
    private $db;
    private $pdo;
    private $batchId;
    private $stats;
    private $errors;
    private $fieldMapping = []; // フィールドマッピング保存用
    private $companyCache = [];
    private $departmentCache = [];
    private $userCache = [];
    private $supplierCache = [];
    private $productCache = [];
    
    // 実際のSmiley配食システムCSVフィールドマッピング
    private $actualFieldMapping = [
        'corporation_code' => '法人CD',
        'corporation_name' => '法人名', 
        'company_code' => '事業所CD',
        'company_name' => '事業所名',
        'supplier_code' => '給食業者CD',
        'supplier_name' => '給食業者名',
        'category_code' => '給食区分CD',
        'category_name' => '給食区分名',
        'delivery_date' => '配達日',
        'department_code' => '部門CD',
        'department_name' => '部門名',
        'user_code' => '社員CD',
        'user_name' => '社員名',
        'employee_type_code' => '雇用形態CD',
        'employee_type_name' => '雇用形態名',
        'product_code' => '給食ﾒﾆｭｰCD',
        'product_name' => '給食ﾒﾆｭｰ名',
        'quantity' => '数量',
        'unit_price' => '単価',
        'total_amount' => '金額',
        'notes' => '備考',
        'delivery_time' => '受取時間',
        'cooperation_code' => '連携CD'
    ];
    
    /**
     * コンストラクタ（Database Singleton対応）
     */
    public function __construct($database = null) {
        try {
            // Database インスタンス取得（Singleton パターン対応）
            if ($database !== null) {
                $this->db = $database; // 依存性注入対応
            } else {
                $this->db = Database::getInstance(); // Singleton パターン
            }
            
            $this->pdo = $this->db->getConnection();
            
            if (!$this->pdo) {
                throw new Exception('データベース接続が取得できませんでした');
            }
            
            $this->batchId = 'SMILEY_' . date('YmdHis') . '_' . uniqid();
            $this->initializeStats();
            
        } catch (Exception $e) {
            throw new Exception('SmileyCSVImporter 初期化エラー: ' . $e->getMessage());
        }
    }
    
    /**
     * 統計情報初期化
     */
    private function initializeStats() {
        $this->stats = [
            'total' => 0,
            'processed' => 0,
            'success' => 0,
            'error' => 0,
            'duplicate' => 0,
            'new_companies' => 0,
            'new_departments' => 0,
            'new_users' => 0,
            'new_suppliers' => 0,
            'new_products' => 0,
            'start_time' => microtime(true)
        ];
        $this->errors = [];
    }
    
    /**
     * CSVファイルインポート実行（メイン関数）
     */
    public function importFile($filePath, $options = []) {
        try {
            // ファイル存在チェック
            if (!file_exists($filePath)) {
                throw new Exception('CSVファイルが見つかりません: ' . $filePath);
            }
            
            // ファイルサイズチェック
            $fileSize = filesize($filePath);
            $maxSize = $options['max_size'] ?? (10 * 1024 * 1024); // 10MB
            if ($fileSize > $maxSize) {
                throw new Exception('ファイルサイズが大きすぎます: ' . round($fileSize / 1024 / 1024, 2) . 'MB');
            }
            
            // エンコーディング検出・変換
            $encoding = $this->detectAndConvertEncoding($filePath, $options);
            
            // CSVファイル読み込み・解析
            $csvData = $this->readAndParseCSV($filePath, $options);
            
            // ヘッダー検証・マッピング作成
            $this->validateAndMapHeaders($csvData['headers']);
            
            // データ処理実行
            $this->processCSVData($csvData['data']);
            
            // インポートログ記録
            $this->logImportResult($filePath);
            
            return $this->getImportSummary();
            
        } catch (Exception $e) {
            $this->logError('インポート失敗', $e->getMessage());
            return [
                'success' => false,
                'batch_id' => $this->batchId,
                'stats' => $this->stats,
                'errors' => $this->errors,
                'processing_time' => round(microtime(true) - $this->stats['start_time'], 2)
            ];
        }
    }
    
    /**
     * エンコーディング検出・変換
     */
    private function detectAndConvertEncoding($filePath, $options) {
        $content = file_get_contents($filePath);
        
        // エンコーディング自動検出
        $encodings = ['UTF-8', 'SJIS-win', 'EUC-JP', 'ASCII'];
        $detectedEncoding = mb_detect_encoding($content, $encodings, true);
        
        if ($detectedEncoding === false) {
            // 検出できない場合はSJIS-winとして処理
            $detectedEncoding = 'SJIS-win';
        }
        
        // UTF-8以外の場合は変換
        if ($detectedEncoding !== 'UTF-8') {
            $convertedContent = mb_convert_encoding($content, 'UTF-8', $detectedEncoding);
            
            // BOM削除
            $convertedContent = ltrim($convertedContent, "\xEF\xBB\xBF");
            
            // 変換されたコンテンツを一時ファイルに書き出し
            $tempFile = $filePath . '.utf8.tmp';
            file_put_contents($tempFile, $convertedContent);
            
            // 元ファイルを置換
            rename($tempFile, $filePath);
        }
        
        return $detectedEncoding;
    }
    
    /**
     * CSV読み込み・解析
     */
    private function readAndParseCSV($filePath, $options) {
        $delimiter = $options['delimiter'] ?? ',';
        $hasHeader = $options['has_header'] ?? true;
        
        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            throw new Exception('CSVファイルを開けませんでした');
        }
        
        $data = [];
        $headers = [];
        $lineNumber = 0;
        
        while (($row = fgetcsv($handle, 0, $delimiter)) !== FALSE) {
            $lineNumber++;
            
            // 空行スキップ
            if (empty(array_filter($row))) {
                continue;
            }
            
            if ($lineNumber === 1 && $hasHeader) {
                $headers = array_map('trim', $row);
                continue;
            }
            
            $data[] = $row;
        }
        
        fclose($handle);
        
        $this->stats['total'] = count($data);
        
        return [
            'headers' => $headers,
            'data' => $data
        ];
    }
    
    /**
     * ヘッダー検証・マッピング作成
     */
    private function validateAndMapHeaders($headers) {
        // フィールド数チェック（23フィールド期待）
        if (count($headers) !== 23) {
            throw new Exception('CSVフィールド数が正しくありません。期待値: 23、実際: ' . count($headers) . 
                              '\nヘッダー: ' . implode(', ', $headers));
        }
        
        // ヘッダーマッピング作成
        $this->fieldMapping = [];
        
        foreach ($headers as $index => $header) {
            $cleanHeader = trim($header);
            
            // 実際のフィールドマッピングと照合
            $mappedField = array_search($cleanHeader, $this->actualFieldMapping);
            if ($mappedField !== false) {
                $this->fieldMapping[$mappedField] = $index;
            }
        }
        
        // 必須フィールドチェック
        $requiredFields = ['corporation_name', 'company_name', 'delivery_date', 'user_code', 'user_name', 'product_code'];
        $missingFields = [];
        
        foreach ($requiredFields as $required) {
            if (!isset($this->fieldMapping[$required])) {
                $missingFields[] = $required . ' (期待ヘッダー: ' . $this->actualFieldMapping[$required] . ')';
            }
        }
        
        if (!empty($missingFields)) {
            throw new Exception('必須フィールドが見つかりません: ' . implode(', ', $missingFields));
        }
    }
    
    /**
     * CSVデータ処理
     */
    private function processCSVData($data) {
        $this->pdo->beginTransaction();
        
        try {
            foreach ($data as $rowIndex => $row) {
                $this->stats['processed']++;
                
                try {
                    // データ正規化
                    $normalizedData = $this->normalizeRowData($row, $rowIndex + 1);
                    
                    // Smiley法人チェック（緩和版）
                    $this->validateSmileyData($normalizedData);
                    
                    // 関連マスターデータ処理
                    $normalizedData = $this->processRelatedData($normalizedData);
                    
                    // 注文データ挿入
                    $this->insertOrderData($normalizedData);
                    
                    $this->stats['success']++;
                    
                } catch (Exception $e) {
                    $this->stats['error']++;
                    $this->logError("行 " . ($rowIndex + 1), $e->getMessage(), $row);
                    
                    // エラーが多すぎる場合は中断
                    if ($this->stats['error'] > 50) {
                        throw new Exception('エラーが多すぎます（50件超）。処理を中断します。');
                    }
                }
            }
            
            $this->pdo->commit();
            
        } catch (Exception $e) {
            $this->pdo->rollback();
            throw $e;
        }
    }
    
    /**
     * 行データ正規化
     */
    private function normalizeRowData($row, $rowNumber) {
        if (count($row) !== 23) {
            throw new Exception("フィールド数が正しくありません（期待値: 23、実際: " . count($row) . "）");
        }
        
        $data = [];
        
        // フィールドマッピング使用してデータ正規化
        foreach ($this->actualFieldMapping as $internalKey => $csvHeader) {
            if (isset($this->fieldMapping[$internalKey])) {
                $index = $this->fieldMapping[$internalKey];
                $data[$internalKey] = isset($row[$index]) ? trim($row[$index]) : '';
            } else {
                $data[$internalKey] = '';
            }
        }
        
        // 必須フィールドチェック
        $requiredChecks = [
            'delivery_date' => '配達日が未入力です',
            'user_code' => '社員CDが未入力です',
            'company_name' => '事業所名が未入力です',
            'product_code' => '給食メニューCDが未入力です'
        ];
        
        foreach ($requiredChecks as $field => $message) {
            if (empty($data[$field])) {
                throw new Exception($message);
            }
        }
        
        // データ型変換
        $data['delivery_date'] = $this->validateAndFormatDate($data['delivery_date']);
        $data['quantity'] = max(1, intval($data['quantity']) ?: 1);
        $data['unit_price'] = floatval(str_replace(',', '', $data['unit_price']));
        $data['total_amount'] = floatval(str_replace(',', '', $data['total_amount']));
        
        // 金額妥当性チェック
        $expectedTotal = $data['quantity'] * $data['unit_price'];
        if (abs($data['total_amount'] - $expectedTotal) > 0.01) {
            $data['total_amount'] = $expectedTotal;
        }
        
        // 時間フィールド処理
        if (!empty($data['delivery_time'])) {
            $data['delivery_time'] = $this->normalizeTime($data['delivery_time']);
        }
        
        return $data;
    }
    
    /**
     * Smiley配食事業データ検証（緩和版）
     */
    private function validateSmileyData($data) {
        // 法人名チェック（緩和版）
        if (!empty($data['corporation_name']) && 
            !preg_match('/smiley/i', $data['corporation_name'])) {
            // 警告のみ、処理は継続
            $this->logError('警告', '法人名にSmileyが含まれていません: ' . $data['corporation_name']);
        }
        
        // 基本データ妥当性チェック
        if (strlen($data['company_name']) < 2) {
            throw new Exception('配達先企業名が短すぎます: ' . $data['company_name']);
        }
        
        if (strlen($data['product_code']) < 1) {
            throw new Exception('商品コードが空です');
        }
    }
    
    /**
     * 関連マスターデータ処理
     */
    private function processRelatedData($data) {
        // 配達先企業処理
        $companyId = $this->getOrCreateCompany($data);
        
        // 部署処理
        $departmentId = $this->getOrCreateDepartment($companyId, $data);
        
        // 利用者処理
        $userId = $this->getOrCreateUser($companyId, $departmentId, $data);
        
        // 給食業者処理
        $supplierId = $this->getOrCreateSupplier($data);
        
        // 商品処理
        $productId = $this->getOrCreateProduct($supplierId, $data);
        
        // IDを追加
        $data['company_id'] = $companyId;
        $data['department_id'] = $departmentId;
        $data['user_id'] = $userId;
        $data['supplier_id'] = $supplierId;
        $data['product_id'] = $productId;
        
        return $data;
    }
    
    /**
     * 配達先企業取得・作成
     */
    private function getOrCreateCompany($data) {
        $cacheKey = $data['company_code'] . '|' . $data['company_name'];
        
        if (isset($this->companyCache[$cacheKey])) {
            return $this->companyCache[$cacheKey];
        }
        
        // 既存チェック
        $sql = "SELECT id FROM companies WHERE company_code = ? OR company_name = ?";
        $stmt = $this->db->query($sql, [$data['company_code'], $data['company_name']]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            $this->companyCache[$cacheKey] = $existing['id'];
            return $existing['id'];
        }
        
        // 新規作成
        $sql = "INSERT INTO companies (company_code, company_name, is_active, created_at) VALUES (?, ?, 1, NOW())";
        $stmt = $this->db->query($sql, [$data['company_code'], $data['company_name']]);
        
        $companyId = $this->pdo->lastInsertId();
        $this->companyCache[$cacheKey] = $companyId;
        $this->stats['new_companies']++;
        
        return $companyId;
    }
    
    /**
     * 部署取得・作成
     */
    private function getOrCreateDepartment($companyId, $data) {
        if (empty($data['department_name'])) {
            return null;
        }
        
        $cacheKey = $companyId . '|' . $data['department_code'] . '|' . $data['department_name'];
        
        if (isset($this->departmentCache[$cacheKey])) {
            return $this->departmentCache[$cacheKey];
        }
        
        // 既存チェック
        $sql = "SELECT id FROM departments WHERE company_id = ? AND (department_code = ? OR department_name = ?)";
        $stmt = $this->db->query($sql, [$companyId, $data['department_code'], $data['department_name']]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            $this->departmentCache[$cacheKey] = $existing['id'];
            return $existing['id'];
        }
        
        // 新規作成
        $sql = "INSERT INTO departments (company_id, department_code, department_name, is_active, created_at) VALUES (?, ?, ?, 1, NOW())";
        $stmt = $this->db->query($sql, [$companyId, $data['department_code'], $data['department_name']]);
        
        $departmentId = $this->pdo->lastInsertId();
        $this->departmentCache[$cacheKey] = $departmentId;
        $this->stats['new_departments']++;
        
        return $departmentId;
    }
    
    /**
     * 利用者取得・作成
     */
    private function getOrCreateUser($companyId, $departmentId, $data) {
        $cacheKey = $data['user_code'];
        
        if (isset($this->userCache[$cacheKey])) {
            return $this->userCache[$cacheKey];
        }
        
        // 既存チェック
        $sql = "SELECT id FROM users WHERE user_code = ?";
        $stmt = $this->db->query($sql, [$data['user_code']]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            $this->userCache[$cacheKey] = $existing['id'];
            return $existing['id'];
        }
        
        // 新規作成
        $sql = "INSERT INTO users (user_code, user_name, company_id, department_id, company_name, department, employee_type_code, employee_type_name, is_active, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())";
        $stmt = $this->db->query($sql, [
            $data['user_code'],
            $data['user_name'],
            $companyId,
            $departmentId,
            $data['company_name'],
            $data['department_name'],
            $data['employee_type_code'],
            $data['employee_type_name']
        ]);
        
        $userId = $this->pdo->lastInsertId();
        $this->userCache[$cacheKey] = $userId;
        $this->stats['new_users']++;
        
        return $userId;
    }
    
    /**
     * 給食業者取得・作成
     */
    private function getOrCreateSupplier($data) {
        if (empty($data['supplier_name'])) {
            return null;
        }
        
        $cacheKey = $data['supplier_code'] . '|' . $data['supplier_name'];
        
        if (isset($this->supplierCache[$cacheKey])) {
            return $this->supplierCache[$cacheKey];
        }
        
        // 既存チェック
        $sql = "SELECT id FROM suppliers WHERE supplier_code = ? OR supplier_name = ?";
        $stmt = $this->db->query($sql, [$data['supplier_code'], $data['supplier_name']]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            $this->supplierCache[$cacheKey] = $existing['id'];
            return $existing['id'];
        }
        
        // 新規作成
        $sql = "INSERT INTO suppliers (supplier_code, supplier_name, is_active, created_at) VALUES (?, ?, 1, NOW())";
        $stmt = $this->db->query($sql, [$data['supplier_code'], $data['supplier_name']]);
        
        $supplierId = $this->pdo->lastInsertId();
        $this->supplierCache[$cacheKey] = $supplierId;
        $this->stats['new_suppliers']++;
        
        return $supplierId;
    }
    
    /**
     * 商品取得・作成
     */
    private function getOrCreateProduct($supplierId, $data) {
        $cacheKey = $data['product_code'];
        
        if (isset($this->productCache[$cacheKey])) {
            return $this->productCache[$cacheKey];
        }
        
        // 既存チェック
        $sql = "SELECT id FROM products WHERE product_code = ?";
        $stmt = $this->db->query($sql, [$data['product_code']]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            $this->productCache[$cacheKey] = $existing['id'];
            return $existing['id'];
        }
        
        // 新規作成
        $sql = "INSERT INTO products (product_code, product_name, category_code, category_name, supplier_id, unit_price, is_active, created_at) VALUES (?, ?, ?, ?, ?, ?, 1, NOW())";
        $stmt = $this->db->query($sql, [
            $data['product_code'],
            $data['product_name'],
            $data['category_code'],
            $data['category_name'],
            $supplierId,
            $data['unit_price']
        ]);
        
        $productId = $this->pdo->lastInsertId();
        $this->productCache[$cacheKey] = $productId;
        $this->stats['new_products']++;
        
        return $productId;
    }
    
    /**
     * 注文データ挿入
     */
    private function insertOrderData($data) {
        // 重複チェック
        $sql = "SELECT id FROM orders WHERE user_code = ? AND delivery_date = ? AND product_code = ? AND cooperation_code = ?";
        $stmt = $this->db->query($sql, [
            $data['user_code'],
            $data['delivery_date'],
            $data['product_code'],
            $data['cooperation_code']
        ]);
        
        if ($stmt->fetch()) {
            $this->stats['duplicate']++;
            throw new Exception('重複注文: ' . $data['user_code'] . ' / ' . $data['delivery_date'] . ' / ' . $data['product_code']);
        }
        
        // 注文データ挿入
        $sql = "INSERT INTO orders (
            order_date, delivery_date, user_id, user_code, user_name,
            company_id, company_code, company_name, department_id,
            product_id, product_code, product_name, category_code, category_name,
            supplier_id, quantity, unit_price, total_amount,
            supplier_code, supplier_name, corporation_code, corporation_name,
            employee_type_code, employee_type_name, department_code, department_name,
            import_batch_id, notes, delivery_time, cooperation_code, created_at
        ) VALUES (
            NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW()
        )";
        
        $stmt = $this->db->query($sql, [
            $data['delivery_date'],
            $data['user_id'],
            $data['user_code'],
            $data['user_name'],
            $data['company_id'],
            $data['company_code'],
            $data['company_name'],
            $data['department_id'],
            $data['product_id'],
            $data['product_code'],
            $data['product_name'],
            $data['category_code'],
            $data['category_name'],
            $data['supplier_id'],
            $data['quantity'],
            $data['unit_price'],
            $data['total_amount'],
            $data['supplier_code'],
            $data['supplier_name'],
            $data['corporation_code'],
            $data['corporation_name'],
            $data['employee_type_code'],
            $data['employee_type_name'],
            $data['department_code'],
            $data['department_name'],
            $this->batchId,
            $data['notes'],
            $data['delivery_time'],
            $data['cooperation_code']
        ]);
    }
    
    /**
     * 日付検証・フォーマット
     */
    private function validateAndFormatDate($dateStr) {
        $formats = ['Y-m-d', 'Y/m/d', 'm/d/Y', 'd/m/Y', 'Ymd'];
        
        foreach ($formats as $format) {
            $date = DateTime::createFromFormat($format, $dateStr);
            if ($date !== false) {
                return $date->format('Y-m-d');
            }
        }
        
        throw new Exception('日付形式が正しくありません: ' . $dateStr);
    }
    
    /**
     * 時間正規化
     */
    private function normalizeTime($timeStr) {
        if (preg_match('/(\d{1,2}):(\d{2})/', $timeStr, $matches)) {
            return sprintf('%02d:%02d:00', $matches[1], $matches[2]);
        }
        return null;
    }
    
    /**
     * エラーログ記録
     */
    private function logError($context, $message, $data = null) {
        $this->errors[] = [
            'context' => $context,
            'message' => $message,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            error_log("CSV Import Error [{$context}]: {$message}");
        }
    }
    
    /**
     * インポート結果ログ記録
     */
    private function logImportResult($filePath) {
        try {
            $sql = "INSERT INTO import_logs (
                batch_id, file_path, total_records, success_records, error_records,
                new_companies, new_departments, new_users, new_suppliers, new_products,
                duplicate_records, processing_time_seconds, created_at, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)";
            
            $status = $this->stats['error'] > 0 ? 'partial_success' : 'success';
            $processingTime = round(microtime(true) - $this->stats['start_time'], 2);
            
            $stmt = $this->db->query($sql, [
                $this->batchId,
                basename($filePath),
                $this->stats['total'],
                $this->stats['success'],
                $this->stats['error'],
                $this->stats['new_companies'],
                $this->stats['new_departments'],
                $this->stats['new_users'],
                $this->stats['new_suppliers'],
                $this->stats['new_products'],
                $this->stats['duplicate'],
                $processingTime,
                $status
            ]);
            
        } catch (Exception $e) {
            error_log("インポートログ記録エラー: " . $e->getMessage());
        }
    }
    
    /**
     * インポート結果サマリー取得
     */
    public function getImportSummary() {
        $processingTime = round(microtime(true) - $this->stats['start_time'], 2);
        
        return [
            'success' => $this->stats['error'] === 0,
            'batch_id' => $this->batchId,
            'stats' => $this->stats,
            'processing_time' => $processingTime,
            'errors' => $this->errors,
            'summary_message' => $this->generateSummaryMessage(),
            'encoding' => 'UTF-8 (converted)'
        ];
    }
    
    /**
     * サマリーメッセージ生成
     */
    private function generateSummaryMessage() {
        $message = "CSVインポート完了:\n";
        $message .= "• 処理件数: {$this->stats['processed']}件\n";
        $message .= "• 成功: {$this->stats['success']}件\n";
        $message .= "• エラー: {$this->stats['error']}件\n";
        $message .= "• 新規企業: {$this->stats['new_companies']}社\n";
        $message .= "• 新規利用者: {$this->stats['new_users']}名\n";
        
        if ($this->stats['duplicate'] > 0) {
            $message .= "• 重複スキップ: {$this->stats['duplicate']}件\n";
        }
        
        return $message;
    }
    
    /**
     * エラーリスト取得
     */
    public function getErrors() {
        return $this->errors;
    }
    
    /**
     * 統計情報取得
     */
    public function getStats() {
        return $this->stats;
    }
}
?>
