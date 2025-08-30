<?php
/**
 * PaymentManager - 支払い管理クラス
 * 
 * 機能:
 * - 支払い記録・更新
 * - 支払い方法別処理（現金・振込・引落）
 * - 部分支払い・分割支払い対応
 * - 未回収金額自動計算
 * - 支払期限管理・アラート
 * - 督促機能
 * 
 * @author Claude
 * @version 1.0
 * @date 2025-08-31
 */

require_once __DIR__ . '/Database.php';

class PaymentManager {
    private $db;
    
    // 支払い方法定数
    const PAYMENT_METHOD_CASH = 'cash';
    const PAYMENT_METHOD_BANK_TRANSFER = 'bank_transfer';
    const PAYMENT_METHOD_ACCOUNT_DEBIT = 'account_debit';
    const PAYMENT_METHOD_OTHER = 'other';
    
    // 支払いステータス定数
    const STATUS_UNPAID = 'unpaid';
    const STATUS_PARTIAL = 'partial';
    const STATUS_PAID = 'paid';
    const STATUS_OVERDUE = 'overdue';
    const STATUS_CANCELLED = 'cancelled';
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * 支払いを記録
     */
    public function recordPayment($invoiceId, $paymentData) {
        try {
            $this->db->beginTransaction();
            
            // 請求書情報を取得
            $invoice = $this->getInvoice($invoiceId);
            if (!$invoice) {
                throw new Exception("請求書が見つかりません: ID {$invoiceId}");
            }
            
            // 既存の支払い合計を取得
            $existingPayments = $this->getTotalPayments($invoiceId);
            $newPaymentAmount = floatval($paymentData['amount']);
            $totalPaid = $existingPayments + $newPaymentAmount;
            $invoiceAmount = floatval($invoice['total_amount']);
            
            // 支払い金額チェック
            if ($totalPaid > $invoiceAmount) {
                throw new Exception("支払い金額が請求額を超えています。請求額: {$invoiceAmount}円, 支払予定合計: {$totalPaid}円");
            }
            
            // 支払い記録を挿入
            $sql = "INSERT INTO payments (
                        invoice_id, payment_date, amount, payment_method,
                        reference_number, notes, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, NOW())";
            
            $params = [
                $invoiceId,
                $paymentData['payment_date'],
                $newPaymentAmount,
                $paymentData['payment_method'],
                $paymentData['reference_number'] ?? null,
                $paymentData['notes'] ?? null
            ];
            
            $result = $this->db->execute($sql, $params);
            if (!$result) {
                throw new Exception("支払い記録の登録に失敗しました");
            }
            
            $paymentId = $this->db->lastInsertId();
            
            // 請求書のステータスを更新
            $newStatus = $this->calculateInvoiceStatus($totalPaid, $invoiceAmount, $invoice['due_date']);
            $this->updateInvoiceStatus($invoiceId, $newStatus, $totalPaid);
            
            // 自動領収書生成（設定されている場合）
            if ($paymentData['auto_generate_receipt'] ?? false) {
                $this->generateReceipt($invoiceId, $paymentId);
            }
            
            $this->db->commit();
            
            return [
                'success' => true,
                'payment_id' => $paymentId,
                'invoice_status' => $newStatus,
                'total_paid' => $totalPaid,
                'remaining_amount' => $invoiceAmount - $totalPaid,
                'message' => '支払いが正常に記録されました'
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("支払い記録エラー: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * 支払い一覧を取得
     */
    public function getPaymentsList($filters = []) {
        $sql = "SELECT 
                    p.id as payment_id,
                    p.payment_date,
                    p.amount,
                    p.payment_method,
                    p.reference_number,
                    p.notes,
                    i.invoice_number,
                    i.total_amount as invoice_amount,
                    i.status as invoice_status,
                    i.due_date,
                    u.user_name,
                    c.company_name,
                    (SELECT SUM(amount) FROM payments WHERE invoice_id = i.id) as total_paid
                FROM payments p
                LEFT JOIN invoices i ON p.invoice_id = i.id
                LEFT JOIN users u ON i.user_id = u.id
                LEFT JOIN companies c ON u.company_id = c.id
                WHERE 1=1";
        
        $params = [];
        
        // フィルター条件を追加
        if (!empty($filters['date_from'])) {
            $sql .= " AND p.payment_date >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND p.payment_date <= ?";
            $params[] = $filters['date_to'];
        }
        
        if (!empty($filters['payment_method'])) {
            $sql .= " AND p.payment_method = ?";
            $params[] = $filters['payment_method'];
        }
        
        if (!empty($filters['company_id'])) {
            $sql .= " AND c.id = ?";
            $params[] = $filters['company_id'];
        }
        
        if (!empty($filters['invoice_status'])) {
            $sql .= " AND i.status = ?";
            $params[] = $filters['invoice_status'];
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (u.user_name LIKE ? OR c.company_name LIKE ? OR i.invoice_number LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $sql .= " ORDER BY p.payment_date DESC, p.id DESC";
        
        // ページネーション
        if (!empty($filters['limit'])) {
            $offset = (!empty($filters['page']) ? ($filters['page'] - 1) * $filters['limit'] : 0);
            $sql .= " LIMIT ? OFFSET ?";
            $params[] = intval($filters['limit']);
            $params[] = $offset;
        }
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * 未回収金額を取得
     */
    public function getOutstandingAmounts($filters = []) {
        $sql = "SELECT 
                    i.id as invoice_id,
                    i.invoice_number,
                    i.total_amount,
                    i.due_date,
                    i.status,
                    u.user_name,
                    c.company_name,
                    COALESCE(SUM(p.amount), 0) as total_paid,
                    (i.total_amount - COALESCE(SUM(p.amount), 0)) as outstanding_amount,
                    DATEDIFF(i.due_date, CURDATE()) as days_until_due,
                    CASE 
                        WHEN i.due_date < CURDATE() THEN 'overdue'
                        WHEN i.due_date <= DATE_ADD(CURDATE(), INTERVAL 3 DAY) THEN 'urgent'
                        WHEN i.due_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 'warning'
                        ELSE 'normal'
                    END as priority
                FROM invoices i
                LEFT JOIN users u ON i.user_id = u.id
                LEFT JOIN companies c ON u.company_id = c.id
                LEFT JOIN payments p ON i.id = p.invoice_id
                WHERE i.status IN ('issued', 'partial', 'overdue')";
        
        $params = [];
        
        // フィルター条件
        if (!empty($filters['company_id'])) {
            $sql .= " AND c.id = ?";
            $params[] = $filters['company_id'];
        }
        
        if (!empty($filters['priority'])) {
            // 優先度フィルターは後でHAVING句で処理
        }
        
        $sql .= " GROUP BY i.id, i.invoice_number, i.total_amount, i.due_date, i.status, u.user_name, c.company_name";
        
        // 優先度フィルター
        if (!empty($filters['priority'])) {
            $sql .= " HAVING priority = ?";
            $params[] = $filters['priority'];
        }
        
        $sql .= " ORDER BY 
                    CASE priority 
                        WHEN 'overdue' THEN 1
                        WHEN 'urgent' THEN 2
                        WHEN 'warning' THEN 3
                        ELSE 4
                    END,
                    i.due_date ASC";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * 支払い統計を取得
     */
    public function getPaymentStatistics($period = 'current_month') {
        $dateCondition = $this->getDateCondition($period);
        
        $sql = "SELECT 
                    COUNT(DISTINCT p.id) as total_payments,
                    SUM(p.amount) as total_amount,
                    COUNT(DISTINCT p.invoice_id) as paid_invoices,
                    AVG(p.amount) as average_payment,
                    COUNT(CASE WHEN p.payment_method = 'cash' THEN 1 END) as cash_payments,
                    COUNT(CASE WHEN p.payment_method = 'bank_transfer' THEN 1 END) as bank_payments,
                    COUNT(CASE WHEN p.payment_method = 'account_debit' THEN 1 END) as debit_payments,
                    SUM(CASE WHEN p.payment_method = 'cash' THEN p.amount ELSE 0 END) as cash_amount,
                    SUM(CASE WHEN p.payment_method = 'bank_transfer' THEN p.amount ELSE 0 END) as bank_amount,
                    SUM(CASE WHEN p.payment_method = 'account_debit' THEN p.amount ELSE 0 END) as debit_amount
                FROM payments p
                WHERE {$dateCondition}";
        
        $stats = $this->db->fetchOne($sql);
        
        // 未回収統計
        $outstandingSql = "SELECT 
                            COUNT(*) as outstanding_invoices,
                            SUM(i.total_amount - COALESCE(p.total_paid, 0)) as outstanding_amount,
                            COUNT(CASE WHEN i.due_date < CURDATE() THEN 1 END) as overdue_invoices
                        FROM invoices i
                        LEFT JOIN (
                            SELECT invoice_id, SUM(amount) as total_paid
                            FROM payments
                            GROUP BY invoice_id
                        ) p ON i.id = p.invoice_id
                        WHERE i.status IN ('issued', 'partial', 'overdue')
                        AND {$dateCondition}";
        
        $outstandingStats = $this->db->fetchOne($outstandingSql);
        
        return array_merge($stats ?: [], $outstandingStats ?: []);
    }
    
    /**
     * 督促リストを生成
     */
    public function generateDunningList($daysOverdue = 7) {
        $sql = "SELECT 
                    i.id as invoice_id,
                    i.invoice_number,
                    i.total_amount,
                    i.due_date,
                    u.user_name,
                    u.email,
                    u.phone,
                    c.company_name,
                    c.contact_person,
                    c.contact_email,
                    c.contact_phone,
                    COALESCE(SUM(p.amount), 0) as total_paid,
                    (i.total_amount - COALESCE(SUM(p.amount), 0)) as outstanding_amount,
                    DATEDIFF(CURDATE(), i.due_date) as days_overdue,
                    (SELECT COUNT(*) FROM dunning_notices WHERE invoice_id = i.id) as dunning_count
                FROM invoices i
                LEFT JOIN users u ON i.user_id = u.id
                LEFT JOIN companies c ON u.company_id = c.id
                LEFT JOIN payments p ON i.id = p.invoice_id
                WHERE i.status IN ('issued', 'partial', 'overdue')
                AND i.due_date <= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                GROUP BY i.id, i.invoice_number, i.total_amount, i.due_date, u.user_name, u.email, u.phone, c.company_name, c.contact_person, c.contact_email, c.contact_phone
                HAVING outstanding_amount > 0
                ORDER BY days_overdue DESC, outstanding_amount DESC";
        
        return $this->db->fetchAll($sql, [$daysOverdue]);
    }
    
    /**
     * 支払期限アラートを取得
     */
    public function getPaymentAlerts() {
        // 期限切れ・期限間近な請求書
        $sql = "SELECT 
                    'overdue' as alert_type,
                    COUNT(*) as count,
                    SUM(i.total_amount - COALESCE(p.total_paid, 0)) as total_amount
                FROM invoices i
                LEFT JOIN (
                    SELECT invoice_id, SUM(amount) as total_paid
                    FROM payments
                    GROUP BY invoice_id
                ) p ON i.id = p.invoice_id
                WHERE i.status IN ('issued', 'partial', 'overdue')
                AND i.due_date < CURDATE()
                
                UNION ALL
                
                SELECT 
                    'due_soon' as alert_type,
                    COUNT(*) as count,
                    SUM(i.total_amount - COALESCE(p.total_paid, 0)) as total_amount
                FROM invoices i
                LEFT JOIN (
                    SELECT invoice_id, SUM(amount) as total_paid
                    FROM payments
                    GROUP BY invoice_id
                ) p ON i.id = p.invoice_id
                WHERE i.status IN ('issued', 'partial')
                AND i.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)
                
                UNION ALL
                
                SELECT 
                    'large_amount' as alert_type,
                    COUNT(*) as count,
                    SUM(i.total_amount - COALESCE(p.total_paid, 0)) as total_amount
                FROM invoices i
                LEFT JOIN (
                    SELECT invoice_id, SUM(amount) as total_paid
                    FROM payments
                    GROUP BY invoice_id
                ) p ON i.id = p.invoice_id
                WHERE i.status IN ('issued', 'partial', 'overdue')
                AND (i.total_amount - COALESCE(p.total_paid, 0)) >= 50000";
        
        $alerts = $this->db->fetchAll($sql);
        
        // 結果を連想配列に変換
        $result = [];
        foreach ($alerts as $alert) {
            $result[$alert['alert_type']] = [
                'count' => $alert['count'],
                'total_amount' => $alert['total_amount']
            ];
        }
        
        return $result;
    }
    
    /**
     * 支払い履歴を取得
     */
    public function getPaymentHistory($invoiceId) {
        $sql = "SELECT 
                    p.*,
                    u.user_name as recorded_by
                FROM payments p
                LEFT JOIN users u ON p.created_by = u.id
                WHERE p.invoice_id = ?
                ORDER BY p.payment_date DESC, p.created_at DESC";
        
        return $this->db->fetchAll($sql, [$invoiceId]);
    }
    
    /**
     * 支払いをキャンセル
     */
    public function cancelPayment($paymentId, $reason = '') {
        try {
            $this->db->beginTransaction();
            
            // 支払い情報を取得
            $payment = $this->db->fetchOne("SELECT * FROM payments WHERE id = ?", [$paymentId]);
            if (!$payment) {
                throw new Exception("支払い記録が見つかりません");
            }
            
            // 支払いを削除（論理削除の場合はstatusを更新）
            $sql = "UPDATE payments SET 
                        status = 'cancelled',
                        cancellation_reason = ?,
                        cancelled_at = NOW()
                    WHERE id = ?";
            
            $this->db->execute($sql, [$reason, $paymentId]);
            
            // 請求書のステータスを再計算
            $invoiceId = $payment['invoice_id'];
            $remainingPayments = $this->getTotalPayments($invoiceId, $paymentId);
            $invoice = $this->getInvoice($invoiceId);
            
            $newStatus = $this->calculateInvoiceStatus($remainingPayments, $invoice['total_amount'], $invoice['due_date']);
            $this->updateInvoiceStatus($invoiceId, $newStatus, $remainingPayments);
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => '支払いがキャンセルされました'
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("支払いキャンセルエラー: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * プライベートメソッド群
     */
    
    private function getInvoice($invoiceId) {
        $sql = "SELECT * FROM invoices WHERE id = ?";
        return $this->db->fetchOne($sql, [$invoiceId]);
    }
    
    private function getTotalPayments($invoiceId, $excludePaymentId = null) {
        $sql = "SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE invoice_id = ? AND status != 'cancelled'";
        $params = [$invoiceId];
        
        if ($excludePaymentId) {
            $sql .= " AND id != ?";
            $params[] = $excludePaymentId;
        }
        
        $result = $this->db->fetchOne($sql, $params);
        return floatval($result['total']);
    }
    
    private function calculateInvoiceStatus($totalPaid, $invoiceAmount, $dueDate) {
        if ($totalPaid >= $invoiceAmount) {
            return self::STATUS_PAID;
        } elseif ($totalPaid > 0) {
            return date('Y-m-d') > $dueDate ? self::STATUS_OVERDUE : self::STATUS_PARTIAL;
        } else {
            return date('Y-m-d') > $dueDate ? self::STATUS_OVERDUE : 'issued';
        }
    }
    
    private function updateInvoiceStatus($invoiceId, $status, $totalPaid = null) {
        $sql = "UPDATE invoices SET status = ?, updated_at = NOW()";
        $params = [$status, $invoiceId];
        
        if ($totalPaid !== null) {
            $sql .= ", paid_amount = ?";
            array_splice($params, 1, 0, $totalPaid);
        }
        
        $sql .= " WHERE id = ?";
        
        return $this->db->execute($sql, $params);
    }
    
    private function generateReceipt($invoiceId, $paymentId) {
        // 領収書生成ロジック（ReceiptGeneratorクラスと連携）
        // 実装は後続のReceiptGeneratorクラスで対応
        return true;
    }
    
    private function getDateCondition($period) {
        switch ($period) {
            case 'current_month':
                return "DATE_FORMAT(p.payment_date, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')";
            case 'last_month':
                return "DATE_FORMAT(p.payment_date, '%Y-%m') = DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), '%Y-%m')";
            case 'current_year':
                return "YEAR(p.payment_date) = YEAR(CURDATE())";
            case 'last_year':
                return "YEAR(p.payment_date) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 YEAR))";
            default:
                return "1=1";
        }
    }
    
    /**
     * デバッグ用メソッド
     */
    public function getDebugInfo() {
        return [
            'class_name' => __CLASS__,
            'database_connected' => $this->db->testConnection(),
            'constants' => [
                'PAYMENT_METHODS' => [
                    self::PAYMENT_METHOD_CASH,
                    self::PAYMENT_METHOD_BANK_TRANSFER,
                    self::PAYMENT_METHOD_ACCOUNT_DEBIT,
                    self::PAYMENT_METHOD_OTHER
                ],
                'STATUSES' => [
                    self::STATUS_UNPAID,
                    self::STATUS_PARTIAL,
                    self::STATUS_PAID,
                    self::STATUS_OVERDUE,
                    self::STATUS_CANCELLED
                ]
            ]
        ];
    }
}
