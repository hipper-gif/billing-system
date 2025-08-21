<?php
/**
 * Smiley配食事業専用CSVインポーター（エンコーディング完全対応版）
 * SJIS-win → UTF-8 完全対応
 */

// 重複宣言防止
if (!class_exists('SmileyCSVImporter')) {

require_once __DIR__ . '/../config/database.php';

class SmileyCSVImporter {
    private $pdo;
    private $batchId;
    private $stats;
    private $errors;
    private $fieldMapping = [];
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
    
    public function __construct() {
        try {
            $db = Database::getInstance();
            $this->pdo = $db->getConnection();
            $this->batchId = 'SMILEY_' . date('YmdHis') . '_' . uniqid();
            $this->initializeStats();
        } catch (Exception $e) {
            throw new Exception('データベース接続エラー: ' . $e->getMessage());
        }
    }
    
    /**
     * 統計情報初期化
     */
    private function initializeStats() {
        $this->stats = [
            'total_rows' => 0,
            'processed_rows' => 0,
            'success_rows' => 0,
            'error_rows' => 0,
            'new_companies' => 0,
            'new_departments' => 0,
            'new_users' => 0,
            'new_suppliers' => 0,
            'new_products' => 0,
            'duplicate_orders' => 0,
            'start_time' => microtime(true)
        ];
        $this->errors = [];
    }
    
    /**
     * CSVファイルインポート実行（エンコーディング対応強化）
     */
    public function importCSV($filePath, $options = []) {
        try {
            // ファイル存在チェック
            if (!file_exists($filePath)) {
                throw new Exception('CSVファイルが見つかりません: ' . $filePath);
            }
            
            // ファイルサイズチェック
            $fileSize = filesize($filePath);
            if ($fileSize > (defined('UPLOAD_MAX_SIZE') ? UPLOAD_MAX_SIZE : 10 * 1024 * 1024)) {
                throw new Exception('ファイルサイズが大きすぎます: ' . round($fileSize / 1024 / 1024, 2) . 'MB');
            }
            
            // CSVファイル読み込み（エンコーディング強化版）
            $csvData = $this->readCSVWithEncoding($filePath, $options);
            
            // ヘッダー検証
            $this->validateHeaders($csvData['headers']);
            
            // インポート実行
            $this->processCSVData($csvData['data']);
            
            // インポートログ記録
            $this->logImportResult();
            
            return $this->getImportSummary();
            
        } catch (Exception $e) {
            $this->logError('インポート失敗', $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * CSVファイル読み込み（エンコーディング完全対応版）
     */
    private function readCSVWithEncoding($filePath, $options = []) {
        $delimiter = $options['delimiter'] ?? ',';
        $hasHeader = $options['has_header'] ?? true;
        
        // ファイル内容読み込み
        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new Exception("ファイル読み込みエラー: {$filePath}");
        }
        
        // エンコーディング自動検出（精度向上）
        $encoding = $this->detectEncodingAccurate($content);
        
        // UTF-8に変換（SJIS-win特別対応）
        if ($encoding !== 'UTF-8') {
            // SJIS-win → UTF-8 変換（文字化け防止）
            if ($encoding === 'SJIS-win' || $encoding === 'SJIS') {
                $content = mb_convert_encoding($content, 'UTF-8', 'SJIS-win');
            } else {
                $content = mb_convert_encoding($content, 'UTF-8', $encoding);
            }
        }
        
        // BOM除去
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        
        // 行区切りを正規化
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        
        // CSV解析
        $lines = explode("\n", $content);
        $lines = array_filter($lines, function($line) {
            return trim($line) !== '';
        });
        
        $data = [];
        $headers = [];
        
        foreach ($lines as $index => $line) {
            // CSV行解析
            $row = str_getcsv($line, $delimiter);
            
            // 各フィールドの前後空白を除去
            $row = array_map('trim', $row);
            
            if ($index === 0 && $hasHeader) {
                $headers = $row;
                continue;
            }
            
            $data[] = $row;
        }
        
        $this->stats['total_rows'] = count($data);
        
        return [
            'headers' => $headers,
            'data' => $data,
            'encoding' => $encoding
        ];
    }
    
    /**
     * エンコーディング精密検出
     */
    private function detectEncodingAccurate($content) {
        // BOM付きUTF-8チェック
        if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
            return 'UTF-8';
        }
        
        // エンコーディング候補（優先順位順）
        $encodings = [
            'UTF-8',
            'SJIS-win',
            'eucJP-win',
            'SJIS',
            'EUC-JP',
            'JIS',
            'ASCII'
        ];
        
        // mb_detect_encodingで検出
        $detected = mb_detect_encoding($content, $encodings, true);
        if ($detected !== false) {
            return $detected;
        }
        
        // 日本語文字の存在確認でSJISを判定
        if (preg_match('/[\x81-\x9F\xE0-\xFC][\x40-\x7E\x80-\xFC]/', $content)) {
            return 'SJIS-win';
        }
        
        // デフォルトはUTF-8
        return 'UTF-8';
    }
    
    /**
     * ヘッダー検証（エンコーディング考慮版）
     */
    private function validateHeaders($headers) {
        // フィールド数チェック
        if (count($headers) !== 23) {
            throw new Exception('CSVフィールド数が正しくありません。期待値: 23、実際: ' . count($headers) . 
                              '\nヘッダー: ' . implode(', ', $headers));
        }
        
        // ヘッダーの正規化とマッピング作成
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
            throw new Exception('必須フィールドが見つかりません: ' . implode(', ', $missingFields) . 
                              '\n実際のヘッダー: ' . implode(', ', $headers));
        }
        
        return true;
    }
    
    /**
     * CSVデータ処理（エラー上限緩和）
     */
    private function processCSVData($data) {
        $this->pdo->beginTransaction();
        
        try {
            foreach ($data as $rowIndex => $row) {
                $this->stats['processed_rows']++;
                
                try {
                    // データ正規化
                    $normalizedData = $this->normalizeRowData($row, $rowIndex + 1);
                    
                    // Smiley法人チェック（緩和版）
                    $this->validateSmileyDataRelaxed($normalizedData);
                    
                    // 関連マスターデータ処理
                    $this->processRelatedData($normalizedData);
                    
                    // 注文データ挿入
                    $this->insertOrderData($normalizedData);
                    
                    $this->stats['success_rows']++;
                    
                } catch (Exception $e) {
                    $this->stats['error_rows']++;
                    $this->logError("行 " . ($rowIndex + 2), $e->getMessage(), $row);
                    
                    // エラー上限を500に緩和（デバッグ用）
                    if ($this->stats['error_rows'] > 500) {
                        throw new Exception('エラーが多すぎます。処理を中断します。（500件超過）');
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
     * 行データ正規化（エンコーディング考慮版）
     */
    private function normalizeRowData($row, $rowNumber) {
        if (count($row) !== 23) {
            throw new Exception("フィールド数が正しくありません（期待値: 23、実際: " . count($row) . "）");
        }
        
        // フィールドマッピングを使用してデータを正規化
        $data = [];
        
        foreach ($this->actualFieldMapping as $internalKey => $csvHeader) {
            if (isset($this->fieldMapping[$internalKey])) {
                $index = $this->fieldMapping[$internalKey];
                $value = isset($row[$index]) ? trim($row[$index]) : '';
                
                // エンコーディング問題で空白が入っている場合の対処
                $value = preg_replace('/\s+/', ' ', $value);
                $value = trim($value);
                
                $data[$internalKey] = $value;
            } else {
                $data[$internalKey] = '';
            }
        }
        
        // 必須フィールドチェック
        if (empty($data['delivery_date'])) {
            throw new Exception('配達日が未入力です');
        }
        
        if (empty($data['user_code'])) {
            throw new Exception('社員CDが未入力です');
        }
        
        if (empty($data['company_name'])) {
            throw new Exception('事業所名が未入力です');
        }
        
        if (empty($data['product_code'])) {
            throw new Exception('給食メニューCDが未入力です');
        }
        
        // 日付形式チェック・変換
        $data['delivery_date'] = $this->validateAndFormatDate($data['delivery_date']);
        
        // 数値フィールド変換
        $data['quantity'] = max(1, intval($data['quantity']));
        $data['unit_price'] = floatval(str_replace([',', '円'], '', $data['unit_price']));
        $data['total_amount'] = floatval(str_replace([',', '円'], '', $data['total_amount']));
        
        // 金額妥当性チェック
        if ($data['unit_price'] > 0 && $data['quantity'] > 0) {
            $expectedTotal = $data['quantity'] * $data['unit_price'];
            if (abs($data['total_amount'] - $expectedTotal) > 0.01) {
                $data['total_amount'] = $expectedTotal;
            }
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
    private function validateSmileyDataRelaxed($data) {
        // 法人名チェック（緩和版）
        $corporationName = trim($data['corporation_name']);
        if (!empty($corporationName)) {
            // 「Smiley」が含まれていればOK（柔軟判定）
            if (!preg_match('/(株式会社\s*)?smiley/iu', $corporationName)) {
                // 警告レベルに変更（エラーにしない）
                error_log("法人名警告: " . $corporationName);
            }
        }
        
        // 配達先企業名の妥当性チェック
        if (strlen($data['company_name']) < 1) {
            throw new Exception('配達先企業名が空です');
        }
        
        // 商品コード形式チェック
        if (strlen($data['product_code']) < 1) {
            throw new Exception('商品コードが空です');
        }
    }
    
    /**
     * 関連マスターデータ処理
     */
    private function processRelatedData($data) {
        // 企業ID取得・作成
        $companyId = $this->getOrCreateCompany($data);
        
        // 部署ID取得・作成
        $departmentId = $this->getOrCreateDepartment($companyId, $data);
        
        // 利用者ID取得・作成
        $userId = $this->getOrCreateUser($companyId, $departmentId, $data);
        
        // 給食業者ID取得・作成
        $supplierId = $this->getOrCreateSupplier($data);
        
        // 商品ID取得・作成
        $productId = $this->getOrCreateProduct($supplierId, $data);
        
        // IDを保存
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
        $stmt = $this->pdo->prepare("
            SELECT id FROM companies 
            WHERE company_code = ? OR company_name = ?
        ");
        $stmt->execute([$data['company_code'], $data['company_name']]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            $this->companyCache[$cacheKey] = $existing['id'];
            return $existing['id'];
        }
        
        // 新規作成
        $stmt = $this->pdo->prepare("
            INSERT INTO companies (
                company_code, company_name, is_active, created_at
            ) VALUES (?, ?, 1, NOW())
        ");
        $stmt->execute([
            $data['company_code'],
            $data['company_name']
        ]);
        
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
        $stmt = $this->pdo->prepare("
            SELECT id FROM departments 
            WHERE company_id = ? AND (department_code = ? OR department_name = ?)
        ");
        $stmt->execute([$companyId, $data['department_code'], $data['department_name']]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            $this->departmentCache[$cacheKey] = $existing['id'];
            return $existing['id'];
        }
        
        // 新規作成
        $stmt = $this->pdo->prepare("
            INSERT INTO departments (
                company_id, department_code, department_name, is_active, created_at
            ) VALUES (?, ?, ?, 1, NOW())
        ");
        $stmt->execute([
            $companyId,
            $data['department_code'],
            $data['department_name']
        ]);
        
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
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE user_code = ?");
        $stmt->execute([$data['user_code']]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            $this->userCache[$cacheKey] = $existing['id'];
            return $existing['id'];
        }
        
        // 新規作成
        $stmt = $this->pdo->prepare("
            INSERT INTO users (
                user_code, user_name, company_id, department_id, company_name, 
                department, employee_type_code, employee_type_name, is_active, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
        ");
        $stmt->execute([
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
        $stmt = $this->pdo->prepare("
            SELECT id FROM suppliers 
            WHERE supplier_code = ? OR supplier_name = ?
        ");
        $stmt->execute([$data['supplier_code'], $data['supplier_name']]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            $this->supplierCache[$cacheKey] = $existing['id'];
            return $existing['id'];
        }
        
        // 新規作成
        $stmt = $this->pdo->prepare("
            INSERT INTO suppliers (
                supplier_code, supplier_name, is_active, created_at
            ) VALUES (?, ?, 1, NOW())
        ");
        $stmt->execute([
            $data['supplier_code'],
            $data['supplier_name']
        ]);
        
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
        $stmt = $this->pdo->prepare("SELECT id FROM products WHERE product_code = ?");
        $stmt->execute([$data['product_code']]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            $this->productCache[$cacheKey] = $existing['id'];
            return $existing['id'];
        }
        
        // 新規作成
        $stmt = $this->pdo->prepare("
            INSERT INTO products (
                product_code, product_name, category_code, category_name, 
                supplier_id, unit_price, is_active, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, 1, NOW())
        ");
        $stmt->execute([
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
        $stmt = $this->pdo->prepare("
            SELECT id FROM orders 
            WHERE user_code = ? AND delivery_date = ? AND product_code = ? AND cooperation_code = ?
        ");
        $stmt->execute([
            $data['user_code'],
            $data['delivery_date'],
            $data['product_code'],
            $data['cooperation_code']
        ]);
        
        if ($stmt->fetch()) {
            $this->stats['duplicate_orders']++;
            throw new Exception('重複注文: ' . $data['user_code'] . ' / ' . $data['delivery_date'] . ' / ' . $data['product_code']);
        }
        
        // 注文データ挿入
        $stmt = $this->pdo->prepare("
            INSERT INTO orders (
                order_date, delivery_date, user_id, user_code, user_name,
                company_id, company_code, company_name, department_id,
                product_id, product_code, product_name, category_code, category_name,
                supplier_id, quantity, unit_price, total_amount,
                supplier_code, supplier_name, corporation_code, corporation_name,
                employee_type_code, employee_type_name, department_code, department_name,
                import_batch_id, notes, delivery_time, cooperation_code, created_at
            ) VALUES (
                NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW()
            )
        ");
        
        $stmt->execute([
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
        $formats = ['Y/m/d', 'Y-m-d', 'm/d/Y', 'd/m/Y', 'Ymd'];
        
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
    private function logImportResult() {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO import_logs (
                    batch_id, file_name, total_rows, success_rows, error_rows,
                    new_companies, new_departments, new_users, new_suppliers, new_products,
                    duplicate_orders, import_date, status, notes
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)
            ");
            
            $status = $this->stats['error_rows'] > 0 ? 'partial_success' : 'success';
            $notes = json_encode([
                'errors' => count($this->errors),
                'execution_time' => round(microtime(true) - $this->stats['start_time'], 2)
            ], JSON_UNESCAPED_UNICODE);
            
            $stmt->execute([
                $this->batchId,
                'csv_import_' . date('YmdHis'),
                $this->stats['total_rows'],
                $this->stats['success_rows'],
                $this->stats['error_rows'],
                $this->stats['new_companies'],
                $this->stats['new_departments'],
                $this->stats['new_users'],
                $this->stats['new_suppliers'],
                $this->stats['new_products'],
                $this->stats['duplicate_orders'],
                $status,
                $notes
            ]);
            
        } catch (Exception $e) {
            error_log("インポートログ記録エラー: " . $e->getMessage());
        }
    }
    
    /**
     * インポート結果サマリー取得
     */
    public function getImportSummary() {
        $executionTime = round(microtime(true) - $this->stats['start_time'], 2);
        
        return [
            'success' => $this->stats['error_rows'] === 0,
            'batch_id' => $this->batchId,
            'stats' => $this->stats,
            'execution_time' => $executionTime,
            'errors' => $this->errors,
            'summary_message' => $this->generateSummaryMessage()
        ];
    }
    
    /**
     * サマリーメッセージ生成
     */
    private function generateSummaryMessage() {
        $message = "CSVインポート完了:\n";
        $message .= "• 処理件数: {$this->stats['processed_rows']}件\n";
        $message .= "• 成功: {$this->stats['success_rows']}件\n";
        $message .= "• エラー: {$this->stats['error_rows']}件\n";
        $message .= "• 新規企業: {$this->stats['new_companies']}社\n";
        $message .= "• 新規利用者: {$this->stats['new_users']}名\n";
        
        if ($this->stats['duplicate_orders'] > 0) {
            $message .= "• 重複スキップ: {$this->stats['duplicate_orders']}件\n";
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

} // class_exists check end
?>
