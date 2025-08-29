<?php
/**
 * Smiley配食事業専用請求書生成クラス
 * 配達先企業別・部署別・個人別請求書生成ロジック
 * 
 * @author Claude
 * @version 1.0.0
 * @created 2025-08-29
 */

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/SecurityHelper.php';

class SmileyInvoiceGenerator {
    private $db;
    
    // 請求書タイプ定義
    const TYPE_COMPANY_BULK = 'company_bulk';        // 企業一括請求
    const TYPE_DEPARTMENT_BULK = 'department_bulk';  // 部署別一括請求
    const TYPE_INDIVIDUAL = 'individual';            // 個人請求
    const TYPE_MIXED = 'mixed';                      // 混合請求
    
    public function __construct() {
        $this->db = Database::getInstance();
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
        
        // 入力値検証
        if (empty($periodStart) || empty($periodEnd)) {
            throw new Exception('請求期間は必須です');
        }
        
        if (empty($targetIds)) {
            throw new Exception('対象を選択してください');
        }
        
        $this->db->beginTransaction();
        
        try {
            $result = [];
            
            switch ($invoiceType) {
                case self::TYPE_COMPANY_BULK:
                    $result = $this->generateCompanyBulkInvoices($targetIds, $periodStart, $periodEnd, $dueDate);
                    break;
                    
                case self::TYPE_DEPARTMENT_BULK:
                    $result = $this->generateDepartmentBulkInvoices($targetIds, $periodStart, $periodEnd, $dueDate);
                    break;
                    
                case self::TYPE_INDIVIDUAL:
                    $result = $this->generateIndividualInvoices($targetIds, $periodStart, $periodEnd, $dueDate);
                    break;
                    
                case self::TYPE_MIXED:
                    $result = $this->generateMixedInvoices($targetIds, $periodStart, $periodEnd, $dueDate);
                    break;
                    
                default:
                    throw new Exception("未対応の請求書タイプ: {$invoiceType}");
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
     */
    private function generateCompanyBulkInvoices($companyIds, $periodStart, $periodEnd, $dueDate) {
        $generatedInvoices = 0;
        $totalAmount = 0;
        $invoiceIds = [];
        
        foreach ($companyIds as $companyId) {
            // 企業の注文データを取得
            $stmt = $this->db->prepare("
                SELECT 
                    c.id as company_id,
                    c.company_code,
                    c.company_name,
                    c.billing_method,
                    COUNT(o.id) as order_count,
                    SUM(o.total_amount) as subtotal,
                    SUM(o.quantity) as total_quantity
                FROM companies c
                LEFT JOIN users u ON u.company_id = c.id
                LEFT JOIN orders o ON o.user_id = u.id 
                    AND o.delivery_date BETWEEN ? AND ?
                WHERE c.id = ? AND c.is_active = 1
                GROUP BY c.id
            ");
            $stmt->execute([$periodStart, $periodEnd, $companyId]);
            $company = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$company || $company['order_count'] == 0) {
                continue; // 注文がない企業はスキップ
            }
            
            // 税額計算
            $subtotal = (float)$company['subtotal'];
            $taxRate = 0.10; // 消費税10%
            $taxAmount = floor($subtotal * $taxRate);
            $totalAmountForInvoice = $subtotal + $taxAmount;
            
            // 請求書レコード作成
            $invoiceNumber = $this->generateInvoiceNumber();
            
            $stmt = $this->db->prepare("
                INSERT INTO invoices (
                    invoice_number, company_id, company_name, user_id,
                    invoice_date, due_date, period_start, period_end,
                    subtotal, tax_rate, tax_amount, total_amount,
                    invoice_type, status, order_count, total_quantity,
                    created_at, updated_at
                ) VALUES (?, ?, ?, NULL, CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?, 'draft', ?, ?, NOW(), NOW())
            ");
            
            $params = [
                $invoiceNumber, $company['company_id'], $company['company_name'],
                $dueDate, $periodStart, $periodEnd,
                $subtotal, $taxRate, $taxAmount, $totalAmountForInvoice,
                self::TYPE_COMPANY_BULK, $company['order_count'], $company['total_quantity']
            ];
            
            $stmt->execute($params);
            $invoiceId = $this->db->lastInsertId();
            
            // 請求書明細作成
            $this->createInvoiceDetails($invoiceId, $companyId, null, null, $periodStart, $periodEnd);
            
            $generatedInvoices++;
            $totalAmount += $totalAmountForInvoice;
            $invoiceIds[] = $invoiceId;
        }
        
        return [
            'total_invoices' => $generatedInvoices,
            'total_amount' => $totalAmount,
            'invoice_ids' => $invoiceIds,
            'generated_invoices' => $generatedInvoices
        ];
    }
    
    /**
     * 部署別一括請求書生成
     */
    private function generateDepartmentBulkInvoices($departmentIds, $periodStart, $periodEnd, $dueDate) {
        $generatedInvoices = 0;
        $totalAmount = 0;
        $invoiceIds = [];
        
        foreach ($departmentIds as $departmentId) {
            // 部署の注文データを取得
            $stmt = $this->db->prepare("
                SELECT 
                    d.id as department_id,
                    d.department_code,
                    d.department_name,
                    c.id as company_id,
                    c.company_code,
                    c.company_name,
                    COUNT(o.id) as order_count,
                    SUM(o.total_amount) as subtotal,
                    SUM(o.quantity) as total_quantity
                FROM departments d
                INNER JOIN companies c ON d.company_id = c.id
                LEFT JOIN users u ON u.department_id = d.id
                LEFT JOIN orders o ON o.user_id = u.id 
                    AND o.delivery_date BETWEEN ? AND ?
                WHERE d.id = ? AND d.is_active = 1
                GROUP BY d.id
            ");
            $stmt->execute([$periodStart, $periodEnd, $departmentId]);
            $department = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$department || $department['order_count'] == 0) {
                continue;
            }
            
            // 税額計算
            $subtotal = (float)$department['subtotal'];
            $taxRate = 0.10;
            $taxAmount = floor($subtotal * $taxRate);
            $totalAmountForInvoice = $subtotal + $taxAmount;
            
            // 請求書レコード作成
            $invoiceNumber = $this->generateInvoiceNumber();
            
            $stmt = $this->db->prepare("
                INSERT INTO invoices (
                    invoice_number, company_id, company_name, department_id, department_name,
                    user_id, invoice_date, due_date, period_start, period_end,
                    subtotal, tax_rate, tax_amount, total_amount,
                    invoice_type, status, order_count, total_quantity,
                    created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, NULL, CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?, 'draft', ?, ?, NOW(), NOW())
            ");
            
            $params = [
                $invoiceNumber, $department['company_id'], $department['company_name'],
                $department['department_id'], $department['department_name'],
                $dueDate, $periodStart, $periodEnd,
                $subtotal, $taxRate, $taxAmount, $totalAmountForInvoice,
                self::TYPE_DEPARTMENT_BULK, $department['order_count'], $department['total_quantity']
            ];
            
            $stmt->execute($params);
            $invoiceId = $this->db->lastInsertId();
            
            // 請求書明細作成（部署別）
            $this->createInvoiceDetails($invoiceId, $department['company_id'], $departmentId, null, $periodStart, $periodEnd);
            
            $generatedInvoices++;
            $totalAmount += $totalAmountForInvoice;
            $invoiceIds[] = $invoiceId;
        }
        
        return [
            'total_invoices' => $generatedInvoices,
            'total_amount' => $totalAmount,
            'invoice_ids' => $invoiceIds,
            'generated_invoices' => $generatedInvoices
        ];
    }
    
    /**
     * 個人請求書生成
     */
    private function generateIndividualInvoices($userIds, $periodStart, $periodEnd, $dueDate) {
        $generatedInvoices = 0;
        $totalAmount = 0;
        $invoiceIds = [];
        
        foreach ($userIds as $userId) {
            // 利用者の注文データを取得
            $stmt = $this->db->prepare("
                SELECT 
                    u.id as user_id,
                    u.user_code,
                    u.user_name,
                    c.id as company_id,
                    c.company_code,
                    c.company_name,
                    d.id as department_id,
                    d.department_name,
                    COUNT(o.id) as order_count,
                    SUM(o.total_amount) as subtotal,
                    SUM(o.quantity) as total_quantity
                FROM users u
                LEFT JOIN companies c ON u.company_id = c.id
                LEFT JOIN departments d ON u.department_id = d.id
                LEFT JOIN orders o ON o.user_id = u.id 
                    AND o.delivery_date BETWEEN ? AND ?
                WHERE u.id = ? AND u.is_active = 1
                GROUP BY u.id
            ");
            $stmt->execute([$periodStart, $periodEnd, $userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user || $user['order_count'] == 0) {
                continue;
            }
            
            // 個人請求最低金額チェック（1,000円未満はスキップ）
            $subtotal = (float)$user['subtotal'];
            if ($subtotal < 1000) {
                continue;
            }
            
            // 税額計算
            $taxRate = 0.10;
            $taxAmount = floor($subtotal * $taxRate);
            $totalAmountForInvoice = $subtotal + $taxAmount;
            
            // 請求書レコード作成
            $invoiceNumber = $this->generateInvoiceNumber();
            
            $stmt = $this->db->prepare("
                INSERT INTO invoices (
                    invoice_number, user_id, user_name, company_id, company_name,
                    department_id, department_name, invoice_date, due_date, 
                    period_start, period_end, subtotal, tax_rate, tax_amount, 
                    total_amount, invoice_type, status, order_count, total_quantity,
                    created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?, 'draft', ?, ?, NOW(), NOW())
            ");
            
            $params = [
                $invoiceNumber, $user['user_id'], $user['user_name'],
                $user['company_id'], $user['company_name'],
                $user['department_id'], $user['department_name'],
                $dueDate, $periodStart, $periodEnd,
                $subtotal, $taxRate, $taxAmount, $totalAmountForInvoice,
                self::TYPE_INDIVIDUAL, $user['order_count'], $user['total_quantity']
            ];
            
            $stmt->execute($params);
            $invoiceId = $this->db->lastInsertId();
            
            // 請求書明細作成（個人別）
            $this->createInvoiceDetails($invoiceId, $user['company_id'], $user['department_id'], $userId, $periodStart, $periodEnd);
            
            $generatedInvoices++;
            $totalAmount += $totalAmountForInvoice;
            $invoiceIds[] = $invoiceId;
        }
        
        return [
            'total_invoices' => $generatedInvoices,
            'total_amount' => $totalAmount,
            'invoice_ids' => $invoiceIds,
            'generated_invoices' => $generatedInvoices
        ];
    }
    
    /**
     * 混合請求書生成
     */
    private function generateMixedInvoices($companyIds, $periodStart, $periodEnd, $dueDate) {
        // 簡易実装：企業一括請求として処理
        return $this->generateCompanyBulkInvoices($companyIds, $periodStart, $periodEnd, $dueDate);
    }
    
    /**
     * 請求書明細作成
     */
    private function createInvoiceDetails($invoiceId, $companyId, $departmentId = null, $userId = null, $periodStart, $periodEnd) {
        $whereClause = "WHERE o.delivery_date BETWEEN ? AND ?";
        $params = [$periodStart, $periodEnd];
        
        if ($userId) {
            // 個人別
            $whereClause .= " AND u.id = ?";
            $params[] = $userId;
        } elseif ($departmentId) {
            // 部署別
            $whereClause .= " AND u.department_id = ?";
            $params[] = $departmentId;
        } elseif ($companyId) {
            // 企業別
            $whereClause .= " AND u.company_id = ?";
            $params[] = $companyId;
        }
        
        $stmt = $this->db->prepare("
            SELECT 
                o.id,
                o.delivery_date,
                o.user_name,
                o.product_name,
                o.quantity,
                o.unit_price,
                o.total_amount,
                u.user_code
            FROM orders o
            INNER JOIN users u ON o.user_id = u.id
            {$whereClause}
            ORDER BY o.delivery_date, u.user_name, o.product_name
        ");
        $stmt->execute($params);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($orders as $order) {
            $stmt = $this->db->prepare("
                INSERT INTO invoice_details (
                    invoice_id, order_id, delivery_date, user_name, user_code,
                    product_name, quantity, unit_price, amount, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $params = [
                $invoiceId, $order['id'], $order['delivery_date'],
                $order['user_name'], $order['user_code'], $order['product_name'],
                $order['quantity'], $order['unit_price'], $order['total_amount']
            ];
            
            $stmt->execute($params);
        }
    }
    
    /**
     * 請求書一覧取得
     */
    public function getInvoiceList($filters, $page, $limit) {
        $whereClauses = [];
        $params = [];
        
        if (!empty($filters['status'])) {
            $whereClauses[] = "i.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['company_id'])) {
            $whereClauses[] = "i.company_id = ?";
            $params[] = $filters['company_id'];
        }
        
        if (!empty($filters['invoice_type'])) {
            $whereClauses[] = "i.invoice_type = ?";
            $params[] = $filters['invoice_type'];
        }
        
        if (!empty($filters['period_start'])) {
            $whereClauses[] = "i.period_start >= ?";
            $params[] = $filters['period_start'];
        }
        
        if (!empty($filters['period_end'])) {
            $whereClauses[] = "i.period_end <= ?";
            $params[] = $filters['period_end'];
        }
        
        $whereClause = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';
        
        // 総件数取得
        $stmt = $this->db->prepare("SELECT COUNT(*) as total FROM invoices i {$whereClause}");
        $stmt->execute($params);
        $totalResult = $stmt->fetch(PDO::FETCH_ASSOC);
        $total = $totalResult['total'];
        
        // データ取得
        $offset = ($page - 1) * $limit;
        $stmt = $this->db->prepare("
            SELECT 
                i.id,
                i.invoice_number,
                i.company_name,
                i.department_name,
                i.user_name,
                i.invoice_date,
                i.due_date,
                i.total_amount,
                i.invoice_type,
                i.status,
                i.order_count,
                i.total_quantity,
                i.created_at
            FROM invoices i 
            {$whereClause}
            ORDER BY i.created_at DESC 
            LIMIT {$limit} OFFSET {$offset}
        ");
        $stmt->execute($params);
        $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'invoices' => $invoices,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'pages' => ceil($total / $limit)
            ]
        ];
    }
    
    /**
     * 請求書詳細取得
     */
    public function getInvoiceData($invoiceId) {
        $stmt = $this->db->prepare("SELECT * FROM invoices WHERE id = ?");
        $stmt->execute([$invoiceId]);
        $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$invoice) {
            return null;
        }
        
        // 明細取得
        $stmt = $this->db->prepare("SELECT * FROM invoice_details WHERE invoice_id = ? ORDER BY delivery_date, user_name");
        $stmt->execute([$invoiceId]);
        $details = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $invoice['details'] = $details;
        
        return $invoice;
    }
    
    /**
     * 請求書ステータス更新
     */
    public function updateInvoiceStatus($invoiceId, $status, $notes = '') {
        $stmt = $this->db->prepare("
            UPDATE invoices 
            SET status = ?, notes = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        return $stmt->execute([$status, $notes, $invoiceId]);
    }
    
    /**
     * 請求書削除
     */
    public function deleteInvoice($invoiceId) {
        $this->db->beginTransaction();
        
        try {
            // 明細削除
            $stmt = $this->db->prepare("DELETE FROM invoice_details WHERE invoice_id = ?");
            $stmt->execute([$invoiceId]);
            
            // 請求書削除
            $stmt = $this->db->prepare("DELETE FROM invoices WHERE id = ?");
            $stmt->execute([$invoiceId]);
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * 請求書番号生成
     */
    private function generateInvoiceNumber() {
        return 'INV-' . date('Ymd') . '-' . str_pad(mt_rand(1, 999), 3, '0', STR_PAD_LEFT);
    }
    
    /**
     * 支払期限日計算
     */
    private function calculateDueDate($periodEnd) {
        $date = new DateTime($periodEnd);
        $date->add(new DateInterval('P30D')); // 30日後
        return $date->format('Y-m-d');
    }
    
    /**
     * システム設定取得
     */
    private function getSystemSetting($key, $default = null) {
        $stmt = $this->db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? $result['setting_value'] : $default;
    }
}
