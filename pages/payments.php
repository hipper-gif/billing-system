<?php
/**
 * PaymentManager.php - 支払い管理クラス（完全実装版）
 * 
 * 機能:
 * - 支払い記録管理
 * - 未回収金額計算
 * - 支払い統計生成
 * - アラート機能
 * - データ取得・フィルタリング
 * 
 * 対応エラー:
 * - Fatal error: Call to undefined method PaymentManager::getPaymentsList() 解決
 * 
 * @version 2.0
 * @date 2025-09-17
 */

require_once __DIR__ . '/../classes/Database.php';

class PaymentManager {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * 支払い方法の選択肢を取得
     * PayPay対応済み
     */
    public static function getPaymentMethods() {
        return [
            'cash' => '💵 現金',
            'bank_transfer' => '🏦 銀行振込',
            'account_debit' => '🏦 口座引き落とし',
            'paypay' => '📱 PayPay',
            'mixed' => '💳 混合',
            'other' => '💼 その他'
        ];
    }
    
    /**
     * ✅ 支払い一覧取得（payments.phpの45行目で使用）
     * Fatal error解決用メソッド
     */
    public function getPaymentsList($filters = []) {
        try {
            $sql = "
                SELECT 
                    p.id as payment_id,
                    p.payment_date,
                    p.amount,
                    p.payment_method,
                    p.reference_number,
                    p.notes,
                    p.created_at,
                    i.id as invoice_id,
                    i.invoice_number,
                    i.total_amount as invoice_amount,
                    i.status as invoice_status,
                    c.company_name,
                    u.user_name,
                    u.user_code
                FROM payments p
                LEFT JOIN invoices i ON p.invoice_id = i.id
                LEFT JOIN users u ON i.user_id = u.id
                LEFT JOIN companies c ON u.company_id = c.id
                WHERE 1=1
            ";
            
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
            
            if (!empty($filters['search'])) {
                $sql .= " AND (
                    c.company_name LIKE ? OR 
                    u.user_name LIKE ? OR 
                    i.invoice_number LIKE ? OR
                    p.reference_number LIKE ?
                )";
                $searchTerm = '%' . $filters['search'] . '%';
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }
            
            $sql .= " ORDER BY p.payment_date DESC, p.created_at DESC";
            
            // ページネーション
            $limit = $filters['limit'] ?? 20;
            $page = $filters['page'] ?? 1;
            $offset = ($page - 1) * $limit;
            
            $sql .= " LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            
            return $this->db->fetchAll($sql, $params);
            
        } catch (Exception $e) {
            error_log("PaymentManager::getPaymentsList Error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * ✅ 未回収金額一覧取得（payments.phpで使用）
     */
    public function getOutstandingAmounts($filters = []) {
        try {
            $sql = "
                SELECT 
                    i.id as invoice_id,
                    i.invoice_number,
                    i.total_amount,
                    i.due_date,
                    i.status,
                    i.created_at as invoice_date,
                    c.company_name,
                    u.user_name,
                    u.user_code,
                    COALESCE(SUM(p.amount), 0) as paid_amount,
                    (i.total_amount - COALESCE(SUM(p.amount), 0)) as outstanding_amount,
                    DATEDIFF(CURDATE(), i.due_date) as days_overdue,
                    DATEDIFF(i.due_date, CURDATE()) as days_until_due,
                    CASE 
                        WHEN DATEDIFF(CURDATE(), i.due_date) > 0 THEN 'overdue'
                        WHEN DATEDIFF(i.due_date, CURDATE()) <= 3 THEN 'urgent'
                        WHEN DATEDIFF(i.due_date, CURDATE()) <= 7 THEN 'warning'
                        ELSE 'normal'
                    END as priority
                FROM invoices i
                LEFT JOIN payments p ON i.id = p.invoice_id
                LEFT JOIN users u ON i.user_id = u.id
                LEFT JOIN companies c ON u.company_id = c.id
                WHERE i.status IN ('issued', 'sent', 'partially_paid')
                GROUP BY i.id, i.invoice_number, i.total_amount, i.due_date, i.status, 
                         i.created_at, c.company_name, u.user_name, u.user_code
                HAVING outstanding_amount > 0
            ";
            
            $params = [];
            
            // フィルター条件
            if (!empty($filters['priority'])) {
                $sql .= " AND CASE 
                    WHEN DATEDIFF(CURDATE(), i.due_date) > 0 THEN 'overdue'
                    WHEN DATEDIFF(i.due_date, CURDATE()) <= 3 THEN 'urgent'
                    WHEN DATEDIFF(i.due_date, CURDATE()) <= 7 THEN 'warning'
                    ELSE 'normal'
                END = ?";
                $params[] = $filters['priority'];
            }
            
            if (!empty($filters['company_id'])) {
                $sql .= " AND c.id = ?";
                $params[] = $filters['company_id'];
            }
            
            if (isset($filters['large_amount']) && $filters['large_amount']) {
                $sql .= " AND (i.total_amount - COALESCE(SUM(p.amount), 0)) >= 50000";
            }
            
            $sql .= " ORDER BY 
                CASE 
                    WHEN DATEDIFF(CURDATE(), i.due_date) > 0 THEN 1
                    WHEN DATEDIFF(i.due_date, CURDATE()) <= 3 THEN 2
                    WHEN DATEDIFF(i.due_date, CURDATE()) <= 7 THEN 3
                    ELSE 4
                END,
                outstanding_amount DESC,
                i.due_date ASC";
            
            return $this->db->fetchAll($sql, $params);
            
        } catch (Exception $e) {
            error_log("PaymentManager::getOutstandingAmounts Error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * ✅ 支払い統計取得（payments.phpで使用）
     */
    public function getPaymentStatistics($period = 'current_month') {
        try {
            // 期間設定
            switch ($period) {
                case 'current_month':
                    $dateFrom = date('Y-m-01');
                    $dateTo = date('Y-m-t');
                    break;
                case 'last_month':
                    $dateFrom = date('Y-m-01', strtotime('last month'));
                    $dateTo = date('Y-m-t', strtotime('last month'));
                    break;
                case 'current_year':
                    $dateFrom = date('Y-01-01');
                    $dateTo = date('Y-12-31');
                    break;
                default:
                    $dateFrom = date('Y-m-01');
                    $dateTo = date('Y-m-t');
            }
            
            // 今月の入金統計
            $paymentStats = $this->db->fetchRow("
                SELECT 
                    COUNT(*) as total_payments,
                    COALESCE(SUM(amount), 0) as total_amount,
                    AVG(amount) as average_amount
                FROM payments 
                WHERE payment_date BETWEEN ? AND ?
            ", [$dateFrom, $dateTo]);
            
            // 未回収統計
            $outstandingStats = $this->db->fetchRow("
                SELECT 
                    COUNT(DISTINCT i.id) as outstanding_invoices,
                    COALESCE(SUM(i.total_amount - COALESCE(paid.amount, 0)), 0) as outstanding_amount
                FROM invoices i
                LEFT JOIN (
                    SELECT invoice_id, SUM(amount) as amount 
                    FROM payments 
                    GROUP BY invoice_id
                ) paid ON i.id = paid.invoice_id
                WHERE i.status IN ('issued', 'sent', 'partially_paid')
                AND (i.total_amount - COALESCE(paid.amount, 0)) > 0
            ");
            
            // 支払い方法別統計
            $methodStats = $this->db->fetchAll("
                SELECT 
                    payment_method,
                    COUNT(*) as count,
                    SUM(amount) as total_amount
                FROM payments 
                WHERE payment_date BETWEEN ? AND ?
                GROUP BY payment_method
                ORDER BY total_amount DESC
            ", [$dateFrom, $dateTo]);
            
            return [
                'period' => $period,
                'date_range' => [
                    'from' => $dateFrom,
                    'to' => $dateTo
                ],
                'total_payments' => $paymentStats['total_payments'] ?? 0,
                'total_amount' => $paymentStats['total_amount'] ?? 0,
                'average_amount' => $paymentStats['average_amount'] ?? 0,
                'outstanding_invoices' => $outstandingStats['outstanding_invoices'] ?? 0,
                'outstanding_amount' => $outstandingStats['outstanding_amount'] ?? 0,
                'payment_methods' => $methodStats ?? []
            ];
            
        } catch (Exception $e) {
            error_log("PaymentManager::getPaymentStatistics Error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * ✅ 支払いアラート取得（payments.phpで使用）
     */
    public function getPaymentAlerts() {
        try {
            // 期限切れ（overdue）
            $overdueAlerts = $this->db->fetchRow("
                SELECT 
                    COUNT(DISTINCT i.id) as count,
                    COALESCE(SUM(i.total_amount - COALESCE(paid.amount, 0)), 0) as total_amount
                FROM invoices i
                LEFT JOIN (
                    SELECT invoice_id, SUM(amount) as amount 
                    FROM payments 
                    GROUP BY invoice_id
                ) paid ON i.id = paid.invoice_id
                WHERE i.status IN ('issued', 'sent', 'partially_paid')
                AND (i.total_amount - COALESCE(paid.amount, 0)) > 0
                AND i.due_date < CURDATE()
            ");
            
            // 期限間近（3日以内）
            $dueSoonAlerts = $this->db->fetchRow("
                SELECT 
                    COUNT(DISTINCT i.id) as count,
                    COALESCE(SUM(i.total_amount - COALESCE(paid.amount, 0)), 0) as total_amount
                FROM invoices i
                LEFT JOIN (
                    SELECT invoice_id, SUM(amount) as amount 
                    FROM payments 
                    GROUP BY invoice_id
                ) paid ON i.id = paid.invoice_id
                WHERE i.status IN ('issued', 'sent', 'partially_paid')
                AND (i.total_amount - COALESCE(paid.amount, 0)) > 0
                AND i.due_date >= CURDATE()
                AND i.due_date <= DATE_ADD(CURDATE(), INTERVAL 3 DAY)
            ");
            
            // 高額未回収（5万円以上）
            $largeAmountAlerts = $this->db->fetchRow("
                SELECT 
                    COUNT(DISTINCT i.id) as count,
                    COALESCE(SUM(i.total_amount - COALESCE(paid.amount, 0)), 0) as total_amount
                FROM invoices i
                LEFT JOIN (
                    SELECT invoice_id, SUM(amount) as amount 
                    FROM payments 
                    GROUP BY invoice_id
                ) paid ON i.id = paid.invoice_id
                WHERE i.status IN ('issued', 'sent', 'partially_paid')
                AND (i.total_amount - COALESCE(paid.amount, 0)) >= 50000
            ");
            
            return [
                'overdue' => [
                    'count' => $overdueAlerts['count'] ?? 0,
                    'total_amount' => $overdueAlerts['total_amount'] ?? 0
                ],
                'due_soon' => [
                    'count' => $dueSoonAlerts['count'] ?? 0,
                    'total_amount' => $dueSoonAlerts['total_amount'] ?? 0
                ],
                'large_amount' => [
                    'count' => $largeAmountAlerts['count'] ?? 0,
                    'total_amount' => $largeAmountAlerts['total_amount'] ?? 0
                ]
            ];
            
        } catch (Exception $e) {
            error_log("PaymentManager::getPaymentAlerts Error: " . $e->getMessage());
            return [
                'overdue' => ['count' => 0, 'total_amount' => 0],
                'due_soon' => ['count' => 0, 'total_amount' => 0],
                'large_amount' => ['count' => 0, 'total_amount' => 0]
            ];
        }
    }
    
    /**
     * ✅ 支払い記録（payments.phpで使用）
     */
    public function recordPayment($invoiceId, $paymentData) {
        try {
            $this->db->beginTransaction();
            
            // 入力値検証
            if (!$this->validatePaymentData($paymentData)) {
                throw new Exception('入力データが不正です');
            }
            
            // 請求書情報を取得
            $invoice = $this->db->fetchRow("
                SELECT id, total_amount, status 
                FROM invoices 
                WHERE id = ?
            ", [$invoiceId]);
            
            if (!$invoice) {
                throw new Exception('請求書が見つかりません');
            }
            
            // 既存の支払い額を計算
            $paidAmount = $this->db->fetchRow("
                SELECT COALESCE(SUM(amount), 0) as total_paid 
                FROM payments 
                WHERE invoice_id = ?
            ", [$invoiceId])['total_paid'];
            
            $newTotalPaid = $paidAmount + $paymentData['amount'];
            
            // 支払い記録を挿入
            $paymentId = $this->db->insert("
                INSERT INTO payments (
                    invoice_id, payment_date, amount, payment_method, 
                    reference_number, notes, created_by, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ", [
                $invoiceId,
                $paymentData['payment_date'],
                $paymentData['amount'],
                $paymentData['payment_method'],
                $paymentData['reference_number'] ?? '',
                $paymentData['notes'] ?? '',
                'system'
            ]);
            
            // 請求書ステータスを更新
            if ($newTotalPaid >= $invoice['total_amount']) {
                $newStatus = 'paid';
            } elseif ($newTotalPaid > 0) {
                $newStatus = 'partially_paid';
            } else {
                $newStatus = 'issued';
            }
            
            $this->db->execute("
                UPDATE invoices 
                SET status = ?, updated_at = NOW() 
                WHERE id = ?
            ", [$newStatus, $invoiceId]);
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => '支払いを記録しました',
                'payment_id' => $paymentId,
                'invoice_status' => $newStatus,
                'total_paid' => $newTotalPaid
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("PaymentManager::recordPayment Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => '支払いの記録に失敗しました: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * ✅ 支払いキャンセル（payments.phpで使用）
     */
    public function cancelPayment($paymentId, $reason = '') {
        try {
            $this->db->beginTransaction();
            
            // 支払い情報を取得
            $payment = $this->db->fetchRow("
                SELECT p.*, i.total_amount 
                FROM payments p
                LEFT JOIN invoices i ON p.invoice_id = i.id
                WHERE p.id = ?
            ", [$paymentId]);
            
            if (!$payment) {
                throw new Exception('支払い記録が見つかりません');
            }
            
            // 支払い記録を削除
            $this->db->execute("DELETE FROM payments WHERE id = ?", [$paymentId]);
            
            // 請求書ステータスを再計算
            $remainingPaid = $this->db->fetchRow("
                SELECT COALESCE(SUM(amount), 0) as total_paid 
                FROM payments 
                WHERE invoice_id = ?
            ", [$payment['invoice_id']])['total_paid'];
            
            if ($remainingPaid >= $payment['total_amount']) {
                $newStatus = 'paid';
            } elseif ($remainingPaid > 0) {
                $newStatus = 'partially_paid';
            } else {
                $newStatus = 'issued';
            }
            
            $this->db->execute("
                UPDATE invoices 
                SET status = ?, updated_at = NOW() 
                WHERE id = ?
            ", [$newStatus, $payment['invoice_id']]);
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => '支払いをキャンセルしました'
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("PaymentManager::cancelPayment Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => '支払いのキャンセルに失敗しました: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * PayPay支払い専用処理
     */
    public function processPayPayPayment($paymentData) {
        // PayPay固有の処理（手数料なし、QRコード処理等）
        $paymentData['payment_method'] = 'paypay';
        return $this->recordPayment($paymentData['invoice_id'], $paymentData);
    }
    
    /**
     * 満額入金記録（一括処理用）
     */
    public function recordFullPayment($invoiceId, $paymentDate = null, $paymentMethod = 'cash', $notes = '') {
        try {
            // 請求書の未払い金額を取得
            $invoice = $this->db->fetchRow("
                SELECT 
                    i.id, i.total_amount,
                    COALESCE(SUM(p.amount), 0) as paid_amount,
                    (i.total_amount - COALESCE(SUM(p.amount), 0)) as outstanding_amount
                FROM invoices i
                LEFT JOIN payments p ON i.id = p.invoice_id
                WHERE i.id = ?
                GROUP BY i.id, i.total_amount
            ", [$invoiceId]);
            
            if (!$invoice || $invoice['outstanding_amount'] <= 0) {
                return [
                    'success' => false,
                    'message' => '未払い金額がありません'
                ];
            }
            
            // 満額入金を記録
            return $this->recordPayment($invoiceId, [
                'payment_date' => $paymentDate ?? date('Y-m-d'),
                'amount' => $invoice['outstanding_amount'],
                'payment_method' => $paymentMethod,
                'reference_number' => '',
                'notes' => $notes ?: '満額入金'
            ]);
            
        } catch (Exception $e) {
            error_log("PaymentManager::recordFullPayment Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => '満額入金の記録に失敗しました: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 入力値検証
     */
    private function validatePaymentData($paymentData) {
        // 必須項目チェック
        if (empty($paymentData['payment_date']) || 
            empty($paymentData['amount']) || 
            empty($paymentData['payment_method'])) {
            return false;
        }
        
        // 金額チェック
        if (!is_numeric($paymentData['amount']) || $paymentData['amount'] <= 0) {
            return false;
        }
        
        // 支払い方法チェック
        $allowedMethods = array_keys(self::getPaymentMethods());
        if (!in_array($paymentData['payment_method'], $allowedMethods)) {
            return false;
        }
        
        // 日付チェック
        if (!strtotime($paymentData['payment_date'])) {
            return false;
        }
        
        return true;
    }
}
?>
