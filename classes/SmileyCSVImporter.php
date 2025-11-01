<?php
/**
 * Smiley配食事業専用CSVインポートクラス v5.2
 * 23フィールドCSVファイルの完全対応
 * Shift_JIS / UTF-8 自動検出対応
 * 重複スキップ機能実装
 * 法人名「株式会社Smiley」の自動チェック
 * 
 * 最終更新: 2025-10-06
 * 変更履歴:
 * - v5.2: エンコーディング自動検出追加（UTF-8 / Shift_JIS対応）
 * - v5.1: Shift_JIS固定、重複スキップ、詳細ログ、import_logs仕様書準拠
 */

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
    
    /**
     * コンストラクタ（自己完結原則準拠）
     */
    public function __construct() {
        try {
            $db = Database::getInstance();
            $this->pdo = $db->getConnection();
            $this->batchId = 'BATCH_' . date('YmdHis') . '_' . uniqid();
            $this->initializeStats();
            
            error_log("SmileyCSVImporter v5.2 初期化完了: バッチID = {$this->batchId}");
        } catch (Exception $e) {
            error_log("SmileyCSVImporter初期化エラー: " . $e->getMessage());
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
     * CSVファイルインポート実行
     */
    public function importCSV($filePath, $options = []) {
        try {
            error_log("========================================");
            error_log("CSVインポート開始 v5.2: {$filePath}");
            error_log("========================================");
            
            // ファイル存在チェック
            if (!file_exists($filePath)) {
                throw new Exception('CSVファイルが見つかりません: ' . $filePath);
            }
            
            // ファイルサイズチェック
            $fileSize = filesize($filePath);
            error_log("ファイルサイズ: " . round($fileSize / 1024, 2) . " KB");
            
            if ($fileSize > (defined('UPLOAD_MAX_SIZE') ? UPLOAD_MAX_SIZE : 10 * 1024 * 1024)) {
                throw new Exception('ファイルサイズが大きすぎます: ' . round($fileSize / 1024 / 1024, 2) . 'MB');
            }
            
            // CSVファイル読み込み
            $csvData = $this->readCSV($filePath, $options);
            
            // ヘッダー検証
            $this->validateHeaders($csvData['headers']);
            
            // インポート実行
            $this->processCSVData($csvData['data']);
            
            // インポートログ記録
            $this->logImportResult();
            
            error_log("========================================");
            error_log("CSVインポート完了");
            error_log("統計: 処理={$this->stats['processed_rows']}, 成功={$this->stats['success_rows']}, エラー={$this->stats['error_rows']}, 重複={$this->stats['duplicate_orders']}");
            error_log("========================================");
            
            return $this->getImportSummary();
            
        } catch (Exception $e) {
            error_log("CSVインポートエラー: " . $e->getMessage());
            error_log("スタックトレース: " . $e->getTraceAsString());
            $this->logError('インポート失敗', $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * CSVファイル読み込み（エンコーディング自動検出版）
     */
    private function readCSV($filePath, $options = []) {
        $delimiter = $options['delimiter'] ?? ',';
        $hasHeader = $options['has_header'] ?? true;
        
        error_log("=== CSV読み込み開始 ===");
        error_log("ファイルパス: {$filePath}");
        
        // ファイル内容読み込み
        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new Exception('ファイルの読み込みに失敗しました');
        }
        
        $originalSize = strlen($content);
        error_log("元のファイルサイズ: {$originalSize} bytes");
        
        // エンコーディング自動検出（Shift_JISを優先）
        $encodingPriority = ['SJIS-win', 'CP932', 'Shift_JIS', 'SJIS', 'UTF-8', 'EUC-JP'];
        $detectedEncoding = mb_detect_encoding($content, $encodingPriority, true);
        
        if ($detectedEncoding === false) {
            // 検出失敗時はShift_JISを試行
            $detectedEncoding = 'SJIS-win';
            error_log("エンコーディング自動検出失敗、Shift_JISを試行");
        } else {
            error_log("エンコーディング検出成功: {$detectedEncoding}");
        }
        
        // UTF-8以外の場合は変換
        if (strtoupper($detectedEncoding) !== 'UTF-8') {
            $content = mb_convert_encoding($content, 'UTF-8', $detectedEncoding);
            if ($content === false) {
                throw new Exception("エンコーディング変換失敗: {$detectedEncoding} → UTF-8");
            }
            error_log("エンコーディング変換: {$detectedEncoding} → UTF-8 完了");
        } else {
            error_log("UTF-8ファイル、変換不要");
        }
        
        $convertedSize = strlen($content);
        error_log("変換後サイズ: {$convertedSize} bytes");
        
        // UTF-8 BOM除去
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        
        // 改行コード統一
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        
        // CSV解析
        $lines = explode("\n", $content);
        error_log("総行数: " . count($lines));
        
        $data = [];
        $headers = [];
        $dataLineCount = 0;
        
        foreach ($lines as $index => $line) {
            // 空行スキップ
            if (empty(trim($line))) {
                continue;
            }
            
            // CSV行解析
            $row = str_getcsv($line, $delimiter);
            
            // ヘッダー行処理
            if ($index === 0 && $hasHeader) {
                $headers = array_map('trim', $row);
                error_log("ヘッダー検出: " . count($headers) . "フィールド");
                error_log("ヘッダー例: " . implode(', ', array_slice($headers, 0, 5)));
                continue;
            }
            
            // データ行追加
            if (count($row) >= 23) {
                $data[] = $row;
                $dataLineCount++;
            } else {
                error_log("フィールド不足の行をスキップ: 行" . ($index + 1) . ", フィールド数: " . count($row));
            }
        }
        
        error_log("データ行数: {$dataLineCount}");
        error_log("=== CSV読み込み完了 ===");
        
        $this->stats['total_rows'] = count($data);
        
        if (count($data) === 0) {
            throw new Exception('CSVファイルにデータ行が見つかりません');
        }
        
        if (empty($headers)) {
            throw new Exception('CSVファイルのヘッダー行が見つかりません');
        }
        
        return [
            'headers' => $headers,
            'data' => $data
        ];
    }
    
    /**
     * ヘッダー検証（実際のSmiley配食システム形式対応）
     */
    private function validateHeaders($headers) {
        error_log("=== ヘッダー検証開始 ===");
        
        // 実際のヘッダー数チェック（23フィールド期待）
        if (count($headers) !== 23) {
            $errorMsg = 'CSVフィールド数が正しくありません。期待値: 23、実際: ' . count($headers) . 
                       '\nヘッダー: ' . implode(', ', $headers);
            error_log($errorMsg);
            throw new Exception($errorMsg);
        }
        
        // ヘッダーの正規化とマッピング作成
        $this->fieldMapping = [];
        
        foreach ($headers as $index => $header) {
            $cleanHeader = trim($header);
            
            // 実際のフィールドマッピングと照合
            $mappedField = array_search($cleanHeader, $this->actualFieldMapping);
            if ($mappedField !== false) {
                $this->fieldMapping[$mappedField] = $index;
                error_log("フィールドマッピング: {$mappedField} => {$cleanHeader} (位置: {$index})");
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
            $errorMsg = '必須フィールドが見つかりません: ' . implode(', ', $missingFields) . 
                       '\n実際のヘッダー: ' . implode(', ', $headers);
            error_log($errorMsg);
            throw new Exception($errorMsg);
        }
        
        error_log("ヘッダー検証成功: 全23フィールド認識");
        error_log("=== ヘッダー検証完了 ===");
        
        return true;
    }
    
    /**
     * CSVデータ処理
     */
    private function processCSVData($data) {
        error_log("=== データ処理開始 ===");
        error_log("処理対象行数: " . count($data));
        
        $this->pdo->beginTransaction();
        
        try {
            foreach ($data as $rowIndex => $row) {
                $this->stats['processed_rows']++;
                
                try {
                    // データ正規化
                    $normalizedData = $this->normalizeRowData($row, $rowIndex + 1);
                    
                    // Smiley法人チェック
                    $this->validateSmileyData($normalizedData);
                    
                    // 関連マスターデータ処理
                    $normalizedData = $this->processRelatedData($normalizedData);
                    
                    // 注文データ挿入
                    $this->insertOrderData($normalizedData);
                    
                    $this->stats['success_rows']++;
                    
                    if (($this->stats['success_rows'] % 10) === 0) {
                        error_log("処理進捗: {$this->stats['success_rows']} / {$this->stats['total_rows']} 件");
                    }
                    
                } catch (Exception $e) {
                    $this->stats['error_rows']++;
                    $errorMsg = "行 " . ($rowIndex + 2) . ": " . $e->getMessage();

                    // 最初の10件のエラーは詳細ログ出力
                    if ($this->stats['error_rows'] <= 10) {
                        error_log("詳細エラー[{$this->stats['error_rows']}]: {$errorMsg}");
                        error_log("データ内容: " . implode('|', array_slice($row, 0, 10)));
                    }

                    $this->logError("行 " . ($rowIndex + 2), $e->getMessage(), $row);

                    // エラーが多すぎる場合は中断
                    if ($this->stats['error_rows'] > 50) {
                        error_log("エラーが多すぎます（50件超）。処理を中断します。");
                        error_log("最初のエラー例: " . (isset($this->errors[0]) ? $this->errors[0]['message'] : 'N/A'));
                        throw new Exception('エラーが多すぎます（50件超）。処理を中断します。最初のエラー: ' . (isset($this->errors[0]) ? $this->errors[0]['message'] : 'N/A'));
                    }
                }
            }
            
            $this->pdo->commit();
            error_log("トランザクションコミット成功");
            error_log("=== データ処理完了 ===");
            
        } catch (Exception $e) {
            $this->pdo->rollback();
            error_log("トランザクションロールバック: " . $e->getMessage());
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
        
        // フィールドマッピングを使用してデータを正規化
        $data = [];
        
        foreach ($this->actualFieldMapping as $internalKey => $csvHeader) {
            if (isset($this->fieldMapping[$internalKey])) {
                $index = $this->fieldMapping[$internalKey];
                $data[$internalKey] = isset($row[$index]) ? trim($row[$index]) : '';
            } else {
                $data[$internalKey] = '';
            }
        }
        
        // データクリーニング
        foreach ($data as $key => $value) {
            $data[$key] = trim($value);
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
        // 法人名チェック（空欄も許可、警告のみ）
        if (!empty($data['corporation_name'])) {
            // より柔軟な法人名チェック：Smiley、smiley、スマイリー等を許可
            if (!preg_match('/(株式会社|㈱)?\s*(smiley|スマイリー|すまいりー)/iu', $data['corporation_name'])) {
                error_log("警告: 法人名が想定と異なります: " . $data['corporation_name']);
                // エラーにはせず、警告のみ
            }
        }

        // 配達先企業名の妥当性チェック（緩和）
        if (empty($data['company_name']) || mb_strlen($data['company_name']) < 1) {
            throw new Exception('配達先企業名が未入力です');
        }

        // 商品コード形式チェック（緩和）
        if (empty($data['product_code']) || mb_strlen($data['product_code']) < 1) {
            throw new Exception('商品コードが未入力です');
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
        
        error_log("新規企業作成: {$data['company_name']} (ID: {$companyId})");
        
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
        
        error_log("新規部署作成: {$data['department_name']} (ID: {$departmentId})");
        
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
        
        error_log("新規利用者作成: {$data['user_name']} (ID: {$userId})");
        
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
        
        error_log("新規業者作成: {$data['supplier_name']} (ID: {$supplierId})");
        
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
        
        error_log("新規商品作成: {$data['product_name']} (ID: {$productId})");
        
        return $productId;
    }
    
    /**
     * 注文データ挿入（重複スキップ対応版）
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
            // 重複時は静かにスキップ
            $this->stats['duplicate_orders']++;
            error_log('重複スキップ: ' . $data['user_code'] . ' / ' . $data['delivery_date'] . ' / ' . $data['product_code']);
            return;
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
        
        error_log("CSV Import Error [{$context}]: {$message}");
    }
    
    /**
     * インポート結果ログ記録（仕様書準拠版）
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
            
            $status = $this->stats['error_rows'] > 0 ? 'failed' : 'completed';
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
            
            error_log("インポートログ記録成功: バッチID = {$this->batchId}");
            
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
?>
