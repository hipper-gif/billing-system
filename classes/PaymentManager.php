<?php
/**
 * PaymentManager - 支払い管理クラス（完全実装版）
 * 
 * 機能:
 * - 支払い記録管理
 * - 未回収金額管理
 * - 統計データ取得
 * - アラート機能
 * - 支払い履歴管理
 * 
 * @author Claude
 * @version 2.0 (Complete Implementation)
 * @date 2025-09-25
 */

// ✅ 正しいDatabase読み込み
if (!class_exists('Database')) {
    require_once __DIR__ . '/../config/database.php';
}

class PaymentManager {
    private $db;
    
    // 支払い方法定数
    const PAYMENT_METHODS = [
        'cash' => '現金',
        'bank_transfer' => '銀行振込',
        'account_debit' => '口座引落',
        'paypay' => 'PayPay',
        'mixed' => '混合',
        'other' => 'その他'
    ];
    
    // 支払いステータス定数
    const STATUS_PENDING = 'pending';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_FAILED = 'failed';
    
    public function __construct() {
        // ✅ 正しい Singleton パターンの使用
        $this->db = Database::getInstance();
    }
    
    /**
     * 支払い履歴一覧を取得
     */
    public function getPaymentsList($filters = []) {
        try {
            $sql = "SELECT 
                        p.*,
                        i.invoice_number,
                        i.total_amount as invoice_amount,
                        i.status as invoice_status,
                        c.company_name,
                        u.user_name,
                        d.department_name
                    FROM payments p
                    LEFT JOIN invoices i ON p.invoice_id = i.id
                    LEFT JOIN users u ON i.user_id = u.id
                    LEFT JOIN companies c ON u.company_id = c.id
                    LEFT JOIN departments d ON u.department_id = d.id
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
            
            if (!empty($filters['search'])) {
                $sql .= " AND (i.invoice_number LIKE ? OR c.company_name LIKE ? OR u.user_name LIKE ?)";
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
            
        } catch (Exception $e) {
            error_log("PaymentManager::getPaymentsList Error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * 未回収金額一覧を取得
     */
    public function getOutstandingAmounts($filters = []) {
        try {
            $sql = "SELECT 
                        i.id as invoice_id,
                        i.invoice_number,
                        i.total_amount,
                        i.due_date,
                        i.status,
                        c.id as company_id,
                        c.company_name,
                        u.user_name,
                        d.department_name,
                        COALESCE(SUM(p.amount), 0) as paid_amount,
                        (i.total_amount - COALESCE(SUM(p.amount), 0)) as outstanding_amount,
                        DATEDIFF(CURDATE(), i.due_date) as days_until_due,
                        CASE 
                            WHEN DATEDIFF(CURDATE(), i.due_date) > 0 THEN 'overdue'
                            WHEN DATEDIFF(CURDATE(), i.due_date) > -3 THEN 'urgent'
                            WHEN DATEDIFF(CURDATE(), i.due_date) > -7 THEN 'warning'
                            ELSE 'normal'
                        END as priority
                    FROM invoices i
                    LEFT JOIN users u ON i.user_id = u.id
                    LEFT JOIN companies c ON u.company_id = c.id
                    LEFT JOIN departments d ON u.department_id = d.id
                    LEFT JOIN payments p ON i.id = p.invoice_id AND p.status = 'completed'
                    WHERE i.status IN ('issued', 'sent', 'partial', 'overdue')
                    GROUP BY i.id
                    HAVING outstanding_amount > 0";
            
            $params = [];
            
            // フィルター条件追加
            if (!empty($filters['company_id'])) {
                $sql .= " AND c.id = ?";
                $params[] = $filters['company_id'];
            }
            
            if (!empty($filters['priority'])) {
                $sql .= " AND CASE 
                            WHEN DATEDIFF(CURDATE(), i.due_date) > 0 THEN 'overdue'
                            WHEN DATEDIFF(CURDATE(), i.due_date) > -3 THEN 'urgent'
                            WHEN DATEDIFF(CURDATE(), i.due_date) > -7 THEN 'warning'
                            ELSE 'normal'
                        END = ?";
                $params[] = $filters['priority'];
            }
            
            if (!empty($filters['large_amount'])) {
                $sql .= " AND (i.total_amount - COALESCE(SUM(p.amount), 0)) >= 50000";
            }
            
            $sql .= " ORDER BY 
                        CASE priority
                            WHEN 'overdue' THEN 1
                            WHEN 'urgent' THEN 2
                            WHEN 'warning' THEN 3
                            ELSE 4
                        END,
                        outstanding_amount DESC";
            
            return $this->db->fetchAll($sql, $params);
            
        } catch (Exception $e) {
            error_log("PaymentManager::getOutstandingAmounts Error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * 支払い統計データを取得
     */
    public function getPaymentStatistics($period = 'current_month') {
        try {
            $dateCondition = $this->getDateCondition($period);
            
            // 基本統計
            $sql = "SELECT 
                        COUNT(DISTINCT p.id) as total_payments,
                        SUM(p.amount) as total_amount,
                        COUNT(DISTINCT i.id) as total_invoices,
                        SUM(CASE WHEN p.payment_method = 'cash' THEN p.amount ELSE 0 END) as cash_amount,
                        SUM(CASE WHEN p.payment_method = 'bank_transfer' THEN p.amount ELSE 0 END) as bank_amount,
                        SUM(CASE WHEN p.payment_method = 'paypay' THEN p.amount ELSE 0 END) as paypay_amount,
                        AVG(p.amount) as average_amount
                    FROM payments p
                    LEFT JOIN invoices i ON p.invoice_id = i.id
                    WHERE p.status = 'completed' AND {$dateCondition}";
            
            $basicStats = $this->db->fetchOne($sql);
            
            // 未回収統計
            $outstandingSql = "SELECT 
                                COUNT(*) as outstanding_invoices,
                                SUM(i.total_amount - COALESCE(paid.amount, 0)) as outstanding_amount
                               FROM invoices i
                               LEFT JOIN (
                                   SELECT invoice_id, SUM(amount) as amount 
                                   FROM payments 
                                   WHERE status = 'completed' 
                                   GROUP BY invoice_id
                               ) paid ON i.id = paid.invoice_id
                               WHERE i.status IN ('issued', 'sent', 'partial', 'overdue')
                               AND (i.total_amount - COALESCE(paid.amount, 0)) > 0";
            
            $outstandingStats = $this->db->fetchOne($outstandingSql);
            
            return [
                'total_payments' => $basicStats['total_payments'] ?? 0,
                'total_amount' => $basicStats['total_amount'] ?? 0,
                'outstanding_invoices' => $outstandingStats['outstanding_invoices'] ?? 0,
                'outstanding_amount' => $outstandingStats['outstanding_amount'] ?? 0,
                'cash_amount' => $basicStats['cash_amount'] ?? 0,
                'bank_amount' => $basicStats['bank_amount'] ?? 0,
                'paypay_amount' => $basicStats['paypay_amount'] ?? 0,
                'average_amount' => $basicStats['average_amount'] ?? 0
            ];
            
        } catch (Exception $e) {
            error_log("PaymentManager::getPaymentStatistics Error: " . $e->getMessage());
            return [
                'total_payments' => 0,
                'total_amount' => 0,
                'outstanding_invoices' => 0,
                'outstanding_amount' => 0,
                'cash_amount' => 0,
                'bank_amount' => 0,
                'paypay_amount' => 0,
                'average_amount' => 0
            ];
        }
    }
    
    /**
     * 支払いアラートを取得
     */
    public function getPaymentAlerts() {
        try {
            // 期限切れ請求書
            $overdueSql = "SELECT 
                            COUNT(*) as count,
                            SUM(i.total_amount - COALESCE(paid.amount, 0)) as total_amount
                           FROM invoices i
                           LEFT JOIN (
                               SELECT invoice_id, SUM(amount) as amount 
                               FROM payments 
                               WHERE status = 'completed' 
                               GROUP BY invoice_id
                           ) paid ON i.id = paid.invoice_id
                           WHERE i.due_date < CURDATE()
                           AND i.status IN ('issued', 'sent', 'partial', 'overdue')
                           AND (i.total_amount - COALESCE(paid.amount, 0)) > 0";
            
            $overdueStats = $this->db->fetchOne($overdueSql);
            
            // 期限間近請求書
            $dueSoonSql = "SELECT 
                            COUNT(*) as count,
                            SUM(i.total_amount - COALESCE(paid.amount, 0)) as total_amount
                           FROM invoices i
                           LEFT JOIN (
                               SELECT invoice_id, SUM(amount) as amount 
                               FROM payments 
                               WHERE status = 'completed' 
                               GROUP BY invoice_id
                           ) paid ON i.id = paid.invoice_id
                           WHERE i.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)
                           AND i.status IN ('issued', 'sent', 'partial')
                           AND (i.total_amount - COALESCE(paid.amount, 0)) > 0";
            
            $dueSoonStats = $this->db->fetchOne($dueSoonSql);
            
            // 高額未回収
            $largeAmountSql = "SELECT 
                                COUNT(*) as count,
                                SUM(i.total_amount - COALESCE(paid.amount, 0)) as total_amount
                               FROM invoices i
                               LEFT JOIN (
                                   SELECT invoice_id, SUM(amount) as amount 
                                   FROM payments 
                                   WHERE status = 'completed' 
                                   GROUP BY invoice_id
                               ) paid ON i.id = paid.invoice_id
                               WHERE i.status IN ('issued', 'sent', 'partial', 'overdue')
                               AND (i.total_amount - COALESCE(paid.amount, 0)) >= 50000";
            
            $largeAmountStats = $this->db->fetchOne($largeAmountSql);
            
            return [
                'overdue' => [
                    'count' => $overdueStats['count'] ?? 0,
                    'total_amount' => $overdueStats['total_amount'] ?? 0
                ],
                'due_soon' => [
                    'count' => $dueSoonStats['count'] ?? 0,
                    'total_amount' => $dueSoonStats['total_amount'] ?? 0
                ],
                'large_amount' => [
                    'count' => $largeAmountStats['count'] ?? 0,
                    'total_amount' => $largeAmountStats['total_amount'] ?? 0
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
     * 支払いを記録
     */
    public function recordPayment($invoiceId, $paymentData) {
        try {
            $this->db->beginTransaction();
            
            // 請求書の存在確認
            $invoice = $this->db->fetchOne("SELECT * FROM invoices WHERE id = ?", [$invoiceId]);
            if (!$invoice) {
                throw new Exception("請求書が見つかりません");
            }
            
            // 既存の支払い合計を取得
            $existingPayments = $this->db->fetchOne(
                "SELECT COALESCE(SUM(amount), 0) as total_paid FROM payments WHERE invoice_id = ? AND status = 'completed'",
                [$invoiceId]
            );
            
            $totalPaid = $existingPayments['total_paid'] + $paymentData['amount'];
            
            // 支払い金額の検証
            if ($totalPaid > $invoice['total_amount']) {
                throw new Exception("支払い金額が請求金額を超過しています");
            }
            
            // 支払い記録を挿入
            $sql = "INSERT INTO payments (
                        invoice_id, amount, payment_date, payment_method,
                        reference_number, notes, status, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, 'completed', NOW())";
            
            $params = [
                $invoiceId,
                $paymentData['amount'],
                $paymentData['payment_date'] ?? date('Y-m-d'),
                $paymentData['payment_method'],
                $paymentData['reference_number'] ?? '',
                $paymentData['notes'] ?? ''
            ];
            
            $this->db->execute($sql, $params);
            $paymentId = $this->db->lastInsertId();
            
            // 請求書ステータスを更新
            $newStatus = ($totalPaid >= $invoice['total_amount']) ? 'paid' : 'partial';
            $this->db->execute(
                "UPDATE invoices SET status = ?, updated_at = NOW() WHERE id = ?",
                [$newStatus, $invoiceId]
            );
            
            // 領収書自動生成（設定されている場合）
            if ($paymentData['auto_generate_receipt'] ?? false) {
                // ReceiptGeneratorクラスがある場合の処理
                // $receiptGenerator = new ReceiptGenerator();
                // $receiptGenerator->generateReceipt($paymentId);
            }
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => '支払いが記録されました',
                'payment_id' => $paymentId,
                'invoice_status' => $newStatus,
                'total_paid' => $totalPaid,
                'remaining_amount' => $invoice['total_amount'] - $totalPaid
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("PaymentManager::recordPayment Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * 支払いをキャンセル
     */
    public function cancelPayment($paymentId, $reason = '') {
        try {
            $this->db->beginTransaction();
            
            // 支払い記録の存在確認
            $payment = $this->db->fetchOne("SELECT * FROM payments WHERE id = ?", [$paymentId]);
            if (!$payment) {
                throw new Exception("支払い記録が見つかりません");
            }
            
            if ($payment['status'] === 'cancelled') {
                throw new Exception("この支払いは既にキャンセル済みです");
            }
            
            // 支払いをキャンセル
            $this->db->execute(
                "UPDATE payments SET status = 'cancelled', cancellation_reason = ?, cancelled_at = NOW() WHERE id = ?",
                [$reason, $paymentId]
            );
            
            // 請求書ステータスを再計算
            $invoiceId = $payment['invoice_id'];
            $remainingPayments = $this->db->fetchOne(
                "SELECT COALESCE(SUM(amount), 0) as total_paid FROM payments WHERE invoice_id = ? AND status = 'completed'",
                [$invoiceId]
            );
            
            $invoice = $this->db->fetchOne("SELECT total_amount FROM invoices WHERE id = ?", [$invoiceId]);
            $totalPaid = $remainingPayments['total_paid'];
            
            if ($totalPaid == 0) {
                $newStatus = 'issued';
            } elseif ($totalPaid < $invoice['total_amount']) {
                $newStatus = 'partial';
            } else {
                $newStatus = 'paid';
            }
            
            $this->db->execute(
                "UPDATE invoices SET status = ?, updated_at = NOW() WHERE id = ?",
                [$newStatus, $invoiceId]
            );
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => '支払いがキャンセルされました'
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("PaymentManager::cancelPayment Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * 支払い方法の選択肢を取得
     */
    public static function getPaymentMethods() {
        return self::PAYMENT_METHODS;
    }
    
    /**
     * 支払い方法の選択肢をHTMLオプションとして取得
     */
    public static function getPaymentMethodOptions($selected = null) {
        $methods = self::getPaymentMethods();
        $options = '';
        
        foreach ($methods as $value => $label) {
            $selectedAttr = ($selected === $value) ? ' selected' : '';
            $emoji = '';
            
            switch ($value) {
                case 'cash':
                    $emoji = '💰 ';
                    break;
                case 'bank_transfer':
                    $emoji = '🏦 ';
                    break;
                case 'account_debit':
                    $emoji = '💳 ';
                    break;
                case 'paypay':
                    $emoji = '📱 ';
                    break;
            }
            
            $options .= "<option value=\"{$value}\"{$selectedAttr}>{$emoji}{$label}</option>\n";
        }
        
        return $options;
    }
    
    /**
     * PayPay支払い用の特別処理
     */
    public function processPayPayPayment($paymentData) {
        try {
            $paymentData['transaction_fee'] = 0;
            $paymentData['payment_method'] = 'paypay';
            
            if (isset($paymentData['qr_code_data'])) {
                $paymentData['reference_number'] = $this->generatePayPayReference($paymentData['qr_code_data']);
            }
            
            return $this->recordPayment($paymentData['invoice_id'], $paymentData);
            
        } catch (Exception $e) {
            error_log("PayPay payment processing error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'PayPay支払い処理でエラーが発生しました: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 支払い方法の妥当性チェック
     */
    public static function isValidPaymentMethod($paymentMethod) {
        return array_key_exists($paymentMethod, self::PAYMENT_METHODS);
    }
    
    /**
     * プライベートメソッド群
     */
    
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
            case 'all':
            default:
                return "1=1";
        }
    }
    
    private function generatePayPayReference($qrData) {
        return 'PP' . date('Ymd') . '_' . substr(md5($qrData), 0, 8);
    }
    
    /**
     * デバッグ用メソッド
     */
    public function getDebugInfo() {
        return [
            'class_name' => __CLASS__,
            'database_connected' => method_exists($this->db, 'testConnection') ? $this->db->testConnection() : true,
            'payment_methods' => self::PAYMENT_METHODS,
            'payment_statuses' => [
                self::STATUS_PENDING,
                self::STATUS_COMPLETED,
                self::STATUS_CANCELLED,
                self::STATUS_FAILED
            ]
        ];
    }
}
