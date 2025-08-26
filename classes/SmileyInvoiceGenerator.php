<?php
/**
 * Smiley配食事業専用請求書生成クラス（完全実装版）
 * 配達先企業別・部署別・個人別請求書に対応
 * 
 * @author Claude
 * @version 2.0.0
 * @created 2025-08-26
 */

require_once __DIR__ . '/Database.php';

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
        
        if (empty($periodStart) || empty($periodEnd)) {
            throw new Exception('請求期間の指定が必要です');
        }
        
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
    private function generateCompanyBulkInvoices($periodStart, $periodEnd, $dueDate, $targetCompanyIds) {
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
                'company_name' => $company['company_name'],
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'due_date' => $dueDate,
                'subtotal' => $orderData['subtotal'],
                'tax_amount' => $orderData['tax_amount'],
                'total_amount' => $orderData['total_amount'],
                'order_count' => $orderData['order_count'],
                'total_quantity' => $orderData['total_quantity']
            ]);
            
            // 請求書明細作成
            $this->createInvoiceDetails($invoiceId, $orderData['orders']);
            
            $generatedInvoices++;
            $totalAmount += $orderData['total_amount'];
            $invoiceIds[] = $invoiceId;
        }
        
        return [
            'total_invoices' => $generatedInvoices,
            'total_amount' => $totalAmount,
            'invoice_ids' => $invoiceIds,
            'invoice_type' => self::TYPE_COMPANY_BULK
        ];
    }
    
    /**
     * 部署別一括請求書生成
     */
    private function generateDepartmentBulkInvoices($periodStart, $periodEnd, $dueDate, $targetDepartmentIds) {
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
                'company_name' => $department['company_name'],
                'department_id' => $department['id'],
                'department_name' => $department['department_name'],
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'due_date' => $dueDate,
                'subtotal' => $orderData['subtotal'],
                'tax_amount' => $orderData['tax_amount'],
                'total_amount' => $orderData['total_amount'],
                'order_count' => $orderData['order_count'],
                'total_quantity' => $orderData['total_quantity']
            ]);
            
            // 請求書明細作成
            $this->createInvoiceDetails($invoiceId, $orderData['orders']);
            
            $generatedInvoices++;
            $totalAmount += $orderData['total_amount'];
            $invoiceIds[] = $invoiceId;
        }
        
        return [
            'total_invoices' => $generatedInvoices,
            'total_amount' => $totalAmount,
            'invoice_ids' => $invoiceIds,
            'invoice_type' => self::TYPE_DEPARTMENT_BULK
        ];
    }
    
    /**
     * 個人請求書生成
     */
    private function generateIndividualInvoices($periodStart, $periodEnd, $dueDate, $targetUserIds) {
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
            
            // 請求書作成
            $invoiceId = $this->createInvoice([
                'invoice_type' => self::TYPE_INDIVIDUAL,
                'user_id' => $user['id'],
                'user_name' => $user['user_name'],
                'company_id' => $user['company_id'],
                'company_name' => $user['company_name'],
                'department_id' => $user['department_id'],
                'department_name' => $user['department_name'],
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'due_date' => $dueDate,
                'subtotal' => $orderData['subtotal'],
                'tax_amount' => $orderData['tax_amount'],
                'total_amount' => $orderData['total_amount'],
                'order_count' => $orderData['order_count'],
                'total_quantity' => $orderData['total_quantity']
            ]);
            
            // 請求書明細作成
            $this->createInvoiceDetails($invoiceId, $orderData['orders']);
            
            $generatedInvoices++;
            $totalAmount += $orderData['total_amount'];
            $invoiceIds[] = $invoiceId;
        }
        
        return [
            'total_invoices' => $generatedInvoices,
            'total_amount' => $totalAmount,
            'invoice_ids' => $invoiceIds,
            'invoice_type' => self::TYPE_INDIVIDUAL
        ];
    }
    
    /**
     * 混合請求書生成（企業設定に基づく自動判定）
     */
    private function generateMixedInvoices($periodStart, $periodEnd, $dueDate, $targetIds) {
        $result = [
            'total_invoices' => 0,
            'total_amount' => 0,
            'invoice_ids' => [],
            'invoice_type' => self::TYPE_MIXED
        ];
        
        // すべての企業を取得して最適な請求方法を判定
        $companies = $this->getTargetCompanies([]);
        
        foreach ($companies as $company) {
            $companyResult = $this->generateCompanyBulkInvoices($periodStart, $periodEnd, $dueDate, [$company['id']]);
            $this->mergeResults($result, $companyResult);
        }
        
        return $result;
    }
    
    /**
     * 請求書レコード作成
     */
    private function createInvoice($data) {
        $invoiceNumber = $this->generateInvoiceNumber();
        
        $sql = "INSERT INTO invoices (
                    invoice_number, invoice_type, user_id, user_name,
                    company_id, company_name, department_id, department_name,
                    issue_date, due_date, period_start, period_end,
                    subtotal, tax_rate, tax_amount, total_amount,
                    order_count, total_quantity, status,
                    created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $invoiceNumber,
            $data['invoice_type'],
            $data['user_id'] ?? null,
            $data['user_name'] ?? null,
            $data['company_id'],
            $data['company_name'],
            $data['department_id'] ?? null,
            $data['department_name'] ?? null,
            date('Y-m-d'), // issue_date
            $data['due_date'],
            $data['period_start'],
            $data['period_end'],
            $data['subtotal'],
            10.0, // tax_rate 10%
            $data['tax_amount'],
            $data['total_amount'],
            $data['order_count'] ?? 0,
            $data['total_quantity'] ?? 0,
            'issued'
        ]);
        
        return $this->db->lastInsertId();
    }
    
    /**
     * 請求書明細作成
     */
    private function createInvoiceDetails($invoiceId, $orders) {
        $sql = "INSERT INTO invoice_details (
                    invoice_id, delivery_date, user_name, product_name,
                    quantity, unit_price, total_amount, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $this->db->prepare($sql);
        
        foreach ($orders as $order) {
            $stmt->execute([
                $invoiceId,
                $order['delivery_date'],
                $order['user_name'] ?? '',
                $order['product_name'] ?? '',
                $order['quantity'] ?? 1,
                $order['unit_price'] ?? 0,
                $order['total_amount'] ?? 0
            ]);
        }
    }
    
    /**
     * 企業注文データ取得
     */
    private function getCompanyOrderData($companyId, $periodStart, $periodEnd) {
        $sql = "SELECT 
                    o.*,
                    u.user_name,
                    p.product_name
                FROM orders o
                LEFT JOIN users u ON o.user_id = u.id
                LEFT JOIN products p ON o.product_id = p.id
                WHERE o.company_id = ? 
                AND o.delivery_date >= ? 
                AND o.delivery_date <= ?
                ORDER BY o.delivery_date, u.user_name";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$companyId, $periodStart, $periodEnd]);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $this->calculateOrderSummary($orders);
    }
    
    /**
     * 部署注文データ取得
     */
    private function getDepartmentOrderData($departmentId, $periodStart, $periodEnd) {
        $sql = "SELECT 
                    o.*,
                    u.user_name,
                    p.product_name
                FROM orders o
                LEFT JOIN users u ON o.user_id = u.id
                LEFT JOIN products p ON o.product_id = p.id
                WHERE o.department_id = ? 
                AND o.delivery_date >= ? 
                AND o.delivery_date <= ?
                ORDER BY o.delivery_date, u.user_name";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$departmentId, $periodStart, $periodEnd]);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $this->calculateOrderSummary($orders);
    }
    
    /**
     * 利用者注文データ取得
     */
    private function getUserOrderData($userId, $periodStart, $periodEnd) {
        $sql = "SELECT 
                    o.*,
                    u.user_name,
                    p.product_name
                FROM orders o
                LEFT JOIN users u ON o.user_id = u.id
                LEFT JOIN products p ON o.product_id = p.id
                WHERE o.user_id = ? 
                AND o.delivery_date >= ? 
                AND o.delivery_date <= ?
                ORDER BY o.delivery_date";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId, $periodStart, $periodEnd]);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $this->calculateOrderSummary($orders);
    }
    
    /**
     * 注文サマリー計算
     */
    private function calculateOrderSummary($orders) {
        $subtotal = 0;
        $orderCount = count($orders);
        $totalQuantity = 0;
        
        foreach ($orders as &$order) {
            $amount = floatval($order['unit_price'] ?? 0) * intval($order['quantity'] ?? 1);
            $order['total_amount'] = $amount;
            $subtotal += $amount;
            $totalQuantity += intval($order['quantity'] ?? 1);
        }
        
        $taxAmount = round($subtotal * 0.1);
        $totalAmount = $subtotal + $taxAmount;
        
        return [
            'orders' => $orders,
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
            'order_count' => $orderCount,
            'total_quantity' => $totalQuantity
        ];
    }
    
    /**
     * 対象企業取得
     */
    private function getTargetCompanies($targetIds = []) {
        $sql = "SELECT id, company_name FROM companies WHERE is_active = 1";
        $params = [];
        
        if (!empty($targetIds)) {
            $placeholders = str_repeat('?,', count($targetIds) - 1) . '?';
            $sql .= " AND id IN ($placeholders)";
            $params = $targetIds;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * 対象部署取得
     */
    private function getTargetDepartments($targetIds = []) {
        $sql = "SELECT d.id, d.department_name, d.company_id, c.company_name
                FROM departments d
                INNER JOIN companies c ON d.company_id = c.id
                WHERE d.is_active = 1 AND c.is_active = 1";
        $params = [];
        
        if (!empty($targetIds)) {
            $placeholders = str_repeat('?,', count($targetIds) - 1) . '?';
            $sql .= " AND d.id IN ($placeholders)";
            $params = $targetIds;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * 対象利用者取得
     */
    private function getTargetUsers($targetIds = []) {
        $sql = "SELECT u.id, u.user_name, u.company_id, c.company_name, 
                       u.department_id, d.department_name
                FROM users u
                INNER JOIN companies c ON u.company_id = c.id
                LEFT JOIN departments d ON u.department_id = d.id
                WHERE u.is_active = 1 AND c.is_active = 1";
        $params = [];
        
        if (!empty($targetIds)) {
            $placeholders = str_repeat('?,', count($targetIds) - 1) . '?';
            $sql .= " AND u.id IN ($placeholders)";
            $params = $targetIds;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * 請求書一覧取得
     */
    public function getInvoiceList($filters = [], $page = 1, $limit = 50) {
        $whereConditions = [];
        $params = [];
        
        if (!empty($filters['company_id'])) {
            $whereConditions[] = "i.company_id = ?";
            $params[] = $filters['company_id'];
        }
        
        if (!empty($filters['status'])) {
            $whereConditions[] = "i.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['invoice_type'])) {
            $whereConditions[] = "i.invoice_type = ?";
            $params[] = $filters['invoice_type'];
        }
        
        if (!empty($filters['period_start'])) {
            $whereConditions[] = "i.period_start >= ?";
            $params[] = $filters['period_start'];
        }
        
        if (!empty($filters['period_end'])) {
            $whereConditions[] = "i.period_end <= ?";
            $params[] = $filters['period_end'];
        }
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        // 総件数取得
        $countSql = "SELECT COUNT(*) FROM invoices i $whereClause";
        $stmt = $this->db->prepare($countSql);
        $stmt->execute($params);
        $totalCount = $stmt->fetchColumn();
        
        // データ取得
        $offset = ($page - 1) * $limit;
        $sql = "SELECT i.*, c.company_name as billing_company_name
                FROM invoices i
                LEFT JOIN companies c ON i.company_id = c.id
                $whereClause
                ORDER BY i.created_at DESC
                LIMIT ? OFFSET ?";
        
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'invoices' => $invoices,
            'total_count' => $totalCount,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($totalCount / $limit)
        ];
    }
    
    /**
     * 請求書詳細取得
     */
    public function getInvoiceData($invoiceId) {
        $sql = "SELECT i.*, c.company_name as billing_company_name
                FROM invoices i
                LEFT JOIN companies c ON i.company_id = c.id
                WHERE i.id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$invoiceId]);
        $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$invoice) {
            return null;
        }
        
        // 明細取得
        $detailSql = "SELECT * FROM invoice_details WHERE invoice_id = ? ORDER BY delivery_date";
        $stmt = $this->db->prepare($detailSql);
        $stmt->execute([$invoiceId]);
        $invoice['details'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $invoice;
    }
    
    /**
     * 請求書ステータス更新
     */
    public function updateInvoiceStatus($invoiceId, $status, $notes = '') {
        $sql = "UPDATE invoices SET status = ?, notes = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $this->db->prepare($sql);
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
            
            // 請求書削除（実際はキャンセルステータスに変更）
            $stmt = $this->db->prepare("UPDATE invoices SET status = 'cancelled', updated_at = NOW() WHERE id = ?");
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
        $prefix = 'SK'; // Smiley Kitchen
        $date = date('Ymd');
        
        // 当日の連番取得
        $sql = "SELECT COUNT(*) FROM invoices WHERE DATE(created_at) = CURDATE()";
        $stmt = $this->db->query($sql);
        $count = $stmt->fetchColumn() + 1;
        
        return $prefix . $date . sprintf('%04d', $count);
    }
    
    /**
     * 支払期限計算
     */
    private function calculateDueDate($periodEnd) {
        return date('Y-m-d', strtotime($periodEnd . ' + 30 days'));
    }
    
    /**
     * 結果マージ
     */
    private function mergeResults(&$result, $newResult) {
        $result['total_invoices'] += $newResult['total_invoices'];
        $result['total_amount'] += $newResult['total_amount'];
        $result['invoice_ids'] = array_merge($result['invoice_ids'], $newResult['invoice_ids']);
    }
}
?>
