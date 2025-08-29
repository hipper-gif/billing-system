<?php
/**
 * Smiley配食事業専用請求書生成クラス (エラー修正版)
 * 配達先企業別・部署別・個人別請求書に対応
 * 
 * 修正内容:
 * - PHPメソッド引数順序エラー修正 (342行目問題)
 * - Database::prepare()メソッド使用法修正 (91行目問題)
 * - Database Singleton パターン対応
 */
class SmileyInvoiceGenerator {
    private $db;
    private $pdfGenerator;
    
    // 請求書タイプ定義
    const TYPE_COMPANY_BULK = 'company_bulk';        // 企業一括請求
    const TYPE_DEPARTMENT_BULK = 'department_bulk';  // 部署別一括請求
    const TYPE_INDIVIDUAL = 'individual';            // 個人請求
    const TYPE_MIXED = 'mixed';                      // 混合請求
    
    public function __construct($db = null, $pdfGenerator = null) {
        $this->db = $db ?: Database::getInstance();  // Singleton対応
        $this->pdfGenerator = $pdfGenerator;
    }
    
    /**
     * 請求書生成（メイン処理）
     */
    public function generateInvoices($params) {
        $invoiceType = $params['invoice_type'] ?? self::TYPE_COMPANY_BULK;
        $periodStart = $params['period_start'];
        $periodEnd = $params['period_end'];
        $dueDate = $params['due_date'] ?? $this->calculateDueDate($periodEnd);
        $targetIds = $params['target_ids'] ?? [];
        $autoPdf = $params['auto_generate_pdf'] ?? true;
        
        $this->db->beginTransaction();
        
        try {
            $result = [];
            
            switch ($invoiceType) {
                case self::TYPE_COMPANY_BULK:
                    $result = $this->generateCompanyBulkInvoices($periodStart, $periodEnd, $dueDate, $targetIds);
                    break;
                    
                case self::TYPE_DEPARTMENT_BULK:
                    $result = $this->generateDepartmentBulkInvoices($periodStart, $periodEnd, $dueDate, $targetIds);
                    break;
                    
                case self::TYPE_INDIVIDUAL:
                    $result = $this->generateIndividualInvoices($periodStart, $periodEnd, $dueDate, $targetIds);
                    break;
                    
                case self::TYPE_MIXED:
                    $result = $this->generateMixedInvoices($periodStart, $periodEnd, $dueDate, $targetIds);
                    break;
                    
                default:
                    throw new Exception("未対応の請求書タイプ: {$invoiceType}");
            }
            
            // PDF自動生成
            if ($autoPdf && !empty($result['invoice_ids'])) {
                $this->generateInvoicePDFs($result['invoice_ids']);
            }
            
            $this->db->commit();
            return $result;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * 企業一括請求書生成
     * 修正: 引数順序を修正（必須引数を後に配置）
     */
    private function generateCompanyBulkInvoices($periodStart, $periodEnd, $dueDate, $targetCompanyIds = []) {
        $generatedInvoices = 0;
        $totalAmount = 0;
        $invoiceIds = [];
        
        // 対象企業の取得
        $companies = $this->getTargetCompanies($targetCompanyIds);
        
        foreach ($companies as $company) {
            // 企業の注文データ取得
            $orderData = $this->getCompanyOrderData($company['id'], $periodStart, $periodEnd);
            
            if (empty($orderData['orders'])) {
                continue; // 注文がない企業はスキップ
            }
            
            // 請求書作成
            $invoiceId = $this->createInvoice([
                'invoice_type' => self::TYPE_COMPANY_BULK,
                'company_id' => $company['id'],
                'company_code' => $company['company_code'],
                'company_name' => $company['company_name'],
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'due_date' => $dueDate,
                'subtotal' => $orderData['subtotal'],
                'tax_amount' => $orderData['tax_amount'],
                'total_amount' => $orderData['total_amount']
            ]);
            
            // 請求書明細作成
            $this->createInvoiceDetails($invoiceId, $orderData['orders']);
            
            $generatedInvoices++;
            $totalAmount += $orderData['total_amount'];
            $invoiceIds[] = $invoiceId;
        }
        
        return [
            'generated_invoices' => $generatedInvoices,
            'total_amount' => $totalAmount,
            'invoice_ids' => $invoiceIds
        ];
    }
    
    /**
     * 部署別一括請求書生成
     * 修正: 引数順序を修正
     */
    private function generateDepartmentBulkInvoices($periodStart, $periodEnd, $dueDate, $targetDepartmentIds = []) {
        $generatedInvoices = 0;
        $totalAmount = 0;
        $invoiceIds = [];
        
        // 対象部署の取得
        $departments = $this->getTargetDepartments($targetDepartmentIds);
        
        foreach ($departments as $department) {
            // 部署の注文データ取得
            $orderData = $this->getDepartmentOrderData($department['id'], $periodStart, $periodEnd);
            
            if (empty($orderData['orders'])) {
                continue;
            }
            
            // 請求書作成
            $invoiceId = $this->createInvoice([
                'invoice_type' => self::TYPE_DEPARTMENT_BULK,
                'company_id' => $department['company_id'],
                'company_code' => $department['company_code'],
                'company_name' => $department['company_name'],
                'department_id' => $department['id'],
                'department_code' => $department['department_code'],
                'department_name' => $department['department_name'],
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'due_date' => $dueDate,
                'subtotal' => $orderData['subtotal'],
                'tax_amount' => $orderData['tax_amount'],
                'total_amount' => $orderData['total_amount']
            ]);
            
            // 請求書明細作成
            $this->createInvoiceDetails($invoiceId, $orderData['orders']);
            
            $generatedInvoices++;
            $totalAmount += $orderData['total_amount'];
            $invoiceIds[] = $invoiceId;
        }
        
        return [
            'generated_invoices' => $generatedInvoices,
            'total_amount' => $totalAmount,
            'invoice_ids' => $invoiceIds
        ];
    }
    
    /**
     * 個人請求書生成
     * 修正: 引数順序を修正
     */
    private function generateIndividualInvoices($periodStart, $periodEnd, $dueDate, $targetUserIds = []) {
        $generatedInvoices = 0;
        $totalAmount = 0;
        $invoiceIds = [];
        
        // 対象利用者の取得
        $users = $this->getTargetUsers($targetUserIds);
        
        foreach ($users as $user) {
            // 利用者の注文データ取得
            $orderData = $this->getUserOrderData($user['id'], $periodStart, $periodEnd);
            
            if (empty($orderData['orders'])) {
                continue;
            }
            
            // 個人請求最低金額チェック
            $minAmount = $this->getSystemSetting('individual_invoice_min_amount', 1000);
            if ($orderData['total_amount'] < $minAmount) {
                continue; // 最低金額未満はスキップ
            }
            
            // 請求書作成
            $invoiceId = $this->createInvoice([
                'invoice_type' => self::TYPE_INDIVIDUAL,
                'user_id' => $user['id'],
                'user_code' => $user['user_code'],
                'user_name' => $user['user_name'],
                'company_id' => $user['company_id'],
                'company_code' => $user['company_code'],
                'company_name' => $user['company_name'],
                'department_id' => $user['department_id'],
                'department_code' => $user['department_code'],
                'department_name' => $user['department_name'],
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'due_date' => $dueDate,
                'subtotal' => $orderData['subtotal'],
                'tax_amount' => $orderData['tax_amount'],
                'total_amount' => $orderData['total_amount']
            ]);
            
            // 請求書明細作成
            $this->createInvoiceDetails($invoiceId, $orderData['orders']);
            
            $generatedInvoices++;
            $totalAmount += $orderData['total_amount'];
            $invoiceIds[] = $invoiceId;
        }
        
        return [
            'generated_invoices' => $generatedInvoices,
            'total_amount' => $totalAmount,
            'invoice_ids' => $invoiceIds
        ];
    }
    
    /**
     * 混合請求書生成
     * 修正: 引数順序を修正
     */
    private function generateMixedInvoices($periodStart, $periodEnd, $dueDate, $targetIds = []) {
        // 企業・部署・個人の混合請求を自動判定して生成
        $result = [
            'generated_invoices' => 0,
            'total_amount' => 0,
            'invoice_ids' => []
        ];
        
        // 1. 企業一括請求可能な企業を抽出
        $companyBulkResult = $this->generateCompanyBulkInvoices($periodStart, $periodEnd, $dueDate, []);
        $result = $this->mergeResults($result, $companyBulkResult);
        
        // 2. 残りの部署・個人について個別処理
        // ... (詳細実装は省略)
        
        return $result;
    }
    
    /**
     * 請求書レコード作成
     * 修正: Database::query()メソッドを正しく使用
     */
    private function createInvoice($data) {
        $invoiceNumber = $this->generateInvoiceNumber();
        
        $sql = "INSERT INTO invoices (
                    invoice_number, user_id, user_code, user_name,
                    company_id, company_name, department_id, department_name,
                    invoice_date, due_date, period_start, period_end,
                    subtotal, tax_rate, tax_amount, total_amount,
                    invoice_type, status, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', NOW(), NOW())";
        
        // 修正: Database::query()を使用
        $stmt = $this->db->query($sql, [
            $invoiceNumber,
            $data['user_id'] ?? null,
            $data['user_code'] ?? null,
            $data['user_name'] ?? null,
            $data['company_id'],
            $data['company_name'],
            $data['department_id'] ?? null,
            $data['department_name'] ?? null,
            date('Y-m-d'),  // invoice_date
            $data['due_date'],
            $data['period_start'],
            $data['period_end'],
            $data['subtotal'],
            10.00,  // 消費税率10%
            $data['tax_amount'],
            $data['total_amount'],
            $data['invoice_type']
        ]);
        
        return $this->db->lastInsertId();
    }
    
    /**
     * 請求書明細作成
     * 修正: Database::query()を使用
     */
    private function createInvoiceDetails($invoiceId, $orders) {
        $sql = "INSERT INTO invoice_details (
                    invoice_id, order_id, delivery_date, user_code, user_name,
                    product_code, product_name, quantity, unit_price, total_price,
                    created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
        
        foreach ($orders as $order) {
            // 修正: Database::query()を使用
            $this->db->query($sql, [
                $invoiceId,
                $order['id'],
                $order['delivery_date'],
                $order['user_code'],
                $order['user_name'],
                $order['product_code'],
                $order['product_name'],
                $order['quantity'],
                $order['unit_price'],
                $order['total_price']
            ]);
        }
    }
    
    /**
     * 対象企業取得
     */
    private function getTargetCompanies($targetCompanyIds = []) {
        if (empty($targetCompanyIds)) {
            $sql = "SELECT * FROM companies WHERE is_active = 1";
            $stmt = $this->db->query($sql);
        } else {
            $placeholders = str_repeat('?,', count($targetCompanyIds) - 1) . '?';
            $sql = "SELECT * FROM companies WHERE id IN ({$placeholders}) AND is_active = 1";
            $stmt = $this->db->query($sql, $targetCompanyIds);
        }
        
        return $stmt->fetchAll();
    }
    
    /**
     * 対象部署取得
     */
    private function getTargetDepartments($targetDepartmentIds = []) {
        if (empty($targetDepartmentIds)) {
            $sql = "SELECT d.*, c.company_code, c.company_name 
                    FROM departments d 
                    JOIN companies c ON d.company_id = c.id 
                    WHERE d.is_active = 1 AND c.is_active = 1";
            $stmt = $this->db->query($sql);
        } else {
            $placeholders = str_repeat('?,', count($targetDepartmentIds) - 1) . '?';
            $sql = "SELECT d.*, c.company_code, c.company_name 
                    FROM departments d 
                    JOIN companies c ON d.company_id = c.id 
                    WHERE d.id IN ({$placeholders}) AND d.is_active = 1 AND c.is_active = 1";
            $stmt = $this->db->query($sql, $targetDepartmentIds);
        }
        
        return $stmt->fetchAll();
    }
    
    /**
     * 対象利用者取得
     */
    private function getTargetUsers($targetUserIds = []) {
        if (empty($targetUserIds)) {
            $sql = "SELECT u.*, c.company_code, c.company_name, d.department_code, d.department_name
                    FROM users u 
                    LEFT JOIN companies c ON u.company_id = c.id 
                    LEFT JOIN departments d ON u.department_id = d.id
                    WHERE u.is_active = 1";
            $stmt = $this->db->query($sql);
        } else {
            $placeholders = str_repeat('?,', count($targetUserIds) - 1) . '?';
            $sql = "SELECT u.*, c.company_code, c.company_name, d.department_code, d.department_name
                    FROM users u 
                    LEFT JOIN companies c ON u.company_id = c.id 
                    LEFT JOIN departments d ON u.department_id = d.id
                    WHERE u.id IN ({$placeholders}) AND u.is_active = 1";
            $stmt = $this->db->query($sql, $targetUserIds);
        }
        
        return $stmt->fetchAll();
    }
    
    /**
     * 企業の注文データ取得
     */
    private function getCompanyOrderData($companyId, $periodStart, $periodEnd) {
        $sql = "SELECT o.*, u.user_name, p.product_name 
                FROM orders o 
                JOIN users u ON o.user_code = u.user_code COLLATE utf8mb4_unicode_ci
                JOIN products p ON o.product_code = p.product_code COLLATE utf8mb4_unicode_ci
                WHERE o.company_id = ? 
                  AND o.delivery_date BETWEEN ? AND ?
                ORDER BY o.delivery_date, o.user_code";
        
        $stmt = $this->db->query($sql, [$companyId, $periodStart, $periodEnd]);
        $orders = $stmt->fetchAll();
        
        return $this->calculateOrderTotals($orders);
    }
    
    /**
     * 部署の注文データ取得
     */
    private function getDepartmentOrderData($departmentId, $periodStart, $periodEnd) {
        $sql = "SELECT o.*, u.user_name, p.product_name 
                FROM orders o 
                JOIN users u ON o.user_code = u.user_code COLLATE utf8mb4_unicode_ci
                JOIN products p ON o.product_code = p.product_code COLLATE utf8mb4_unicode_ci
                WHERE u.department_id = ? 
                  AND o.delivery_date BETWEEN ? AND ?
                ORDER BY o.delivery_date, o.user_code";
        
        $stmt = $this->db->query($sql, [$departmentId, $periodStart, $periodEnd]);
        $orders = $stmt->fetchAll();
        
        return $this->calculateOrderTotals($orders);
    }
    
    /**
     * 利用者の注文データ取得
     */
    private function getUserOrderData($userId, $periodStart, $periodEnd) {
        $sql = "SELECT o.*, u.user_name, p.product_name 
                FROM orders o 
                JOIN users u ON o.user_code = u.user_code COLLATE utf8mb4_unicode_ci
                JOIN products p ON o.product_code = p.product_code COLLATE utf8mb4_unicode_ci
                WHERE u.id = ? 
                  AND o.delivery_date BETWEEN ? AND ?
                ORDER BY o.delivery_date";
        
        $stmt = $this->db->query($sql, [$userId, $periodStart, $periodEnd]);
        $orders = $stmt->fetchAll();
        
        return $this->calculateOrderTotals($orders);
    }
    
    /**
     * 注文合計計算
     */
    private function calculateOrderTotals($orders) {
        $subtotal = 0;
        $totalAmount = 0;
        
        foreach ($orders as &$order) {
            $orderTotal = $order['quantity'] * $order['unit_price'];
            $order['total_price'] = $orderTotal;
            $subtotal += $orderTotal;
        }
        
        $taxAmount = round($subtotal * 0.10); // 消費税10%
        $totalAmount = $subtotal + $taxAmount;
        
        return [
            'orders' => $orders,
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount
        ];
    }
    
    /**
     * 請求書番号生成
     */
    private function generateInvoiceNumber() {
        $prefix = 'SMI-';
        $date = date('Ym');
        
        // 同月の最大連番取得
        $sql = "SELECT MAX(CAST(SUBSTRING(invoice_number, 9) AS UNSIGNED)) as max_seq 
                FROM invoices 
                WHERE invoice_number LIKE ?";
        
        $stmt = $this->db->query($sql, [$prefix . $date . '%']);
        $maxSeq = $stmt->fetchColumn() ?: 0;
        
        $newSeq = str_pad($maxSeq + 1, 4, '0', STR_PAD_LEFT);
        return $prefix . $date . $newSeq;
    }
    
    /**
     * 支払期限計算
     */
    private function calculateDueDate($periodEnd) {
        // 月末締め、翌月末支払い
        $endDate = new DateTime($periodEnd);
        $endDate->modify('last day of next month');
        return $endDate->format('Y-m-d');
    }
    
    /**
     * システム設定取得
     */
    private function getSystemSetting($key, $defaultValue = null) {
        $sql = "SELECT setting_value FROM system_settings WHERE setting_key = ?";
        $stmt = $this->db->query($sql, [$key]);
        $result = $stmt->fetchColumn();
        
        return $result !== false ? $result : $defaultValue;
    }
    
    /**
     * 結果マージ
     */
    private function mergeResults($result1, $result2) {
        return [
            'generated_invoices' => $result1['generated_invoices'] + $result2['generated_invoices'],
            'total_amount' => $result1['total_amount'] + $result2['total_amount'],
            'invoice_ids' => array_merge($result1['invoice_ids'], $result2['invoice_ids'])
        ];
    }
    
    /**
     * PDF生成
     */
    private function generateInvoicePDFs($invoiceIds) {
        if (!$this->pdfGenerator) {
            return; // PDFジェネレータがない場合はスキップ
        }
        
        foreach ($invoiceIds as $invoiceId) {
            $this->pdfGenerator->generateInvoicePDF($invoiceId);
        }
    }
}
?>
