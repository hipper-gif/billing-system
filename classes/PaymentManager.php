<?php
/**
 * PaymentManager - 支払い管理クラス（Database読み込み問題解決版）
 * 
 * 🔧 修正内容:
 * - Database クラス読み込み順序問題解決
 * - config/database.php の Singleton パターン使用
 * - エラー "Class Database not found" 完全解決
 */

class PaymentManager {
    private $db;
    
    public function __construct() {
        // 📋 統合版Database読み込み（設定値+クラス）
        if (!class_exists('Database')) {
            require_once __DIR__ . '/../config/database.php';
        }
        
        // ✅ Singleton パターンでDatabase取得
        $this->db = Database::getInstance();
    }
    
    /**
     * 支払い統計情報を取得（index.php対応）
     * @param string $period 期間 ('month'|'year'|'all')
     * @return array 統計データ
     */
    public function getPaymentStatistics($period = 'month') {
        try {
            $dateCondition = $this->getPeriodCondition($period);
            
            // 💰 売上統計取得
            $salesSql = "SELECT 
                            COALESCE(SUM(total_amount), 0) as total_amount,
                            COUNT(*) as order_count
                         FROM orders 
                         WHERE delivery_date {$dateCondition}";
            $salesStmt = $this->db->query($salesSql);
            $salesData = $salesStmt->fetch();
            
            // 📄 請求書統計取得
            $invoiceSql = "SELECT 
                              COUNT(*) as invoice_count,
                              COALESCE(SUM(CASE WHEN status = 'issued' THEN total_amount ELSE 0 END), 0) as outstanding_amount,
                              COUNT(CASE WHEN status = 'issued' THEN 1 END) as outstanding_count,
                              COALESCE(SUM(CASE WHEN status = 'paid' THEN total_amount ELSE 0 END), 0) as paid_amount
                           FROM invoices 
                           WHERE invoice_date {$dateCondition}";
            $invoiceStmt = $this->db->query($invoiceSql);
            $invoiceData = $invoiceStmt->fetch();
            
            // 📊 Chart.js用月別推移データ取得
            $trendSql = "SELECT 
                            DATE_FORMAT(delivery_date, '%Y-%m') as month,
                            COALESCE(SUM(total_amount), 0) as monthly_amount,
                            COUNT(*) as monthly_count
                         FROM orders 
                         WHERE delivery_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                         GROUP BY DATE_FORMAT(delivery_date, '%Y-%m')
                         ORDER BY month ASC";
            $trendStmt = $this->db->query($trendSql);
            $trendData = $trendStmt->fetchAll();
            
            // 💳 支払い方法別統計（模擬データ - 実装後にpaymentsテーブルから取得）
            $paymentMethods = [
                ['payment_method' => 'cash', 'total_amount' => $salesData['total_amount'] * 0.4],
                ['payment_method' => 'bank_transfer', 'total_amount' => $salesData['total_amount'] * 0.3],
                ['payment_method' => 'account_debit', 'total_amount' => $salesData['total_amount'] * 0.2],
                ['payment_method' => 'other', 'total_amount' => $salesData['total_amount'] * 0.1]
            ];
            
            return [
                'summary' => [
                    'total_amount' => (float)$salesData['total_amount'],
                    'outstanding_amount' => (float)$invoiceData['outstanding_amount'],
                    'outstanding_count' => (int)$invoiceData['outstanding_count'],
                    'paid_amount' => (float)$invoiceData['paid_amount'],
                    'order_count' => (int)$salesData['order_count'],
                    'invoice_count' => (int)$invoiceData['invoice_count']
                ],
                'trend' => $trendData,
                'payment_methods' => $paymentMethods
            ];
            
        } catch (Exception $e) {
            error_log("PaymentManager::getPaymentStatistics Error: " . $e->getMessage());
            return [
                'summary' => [
                    'total_amount' => 0,
                    'outstanding_amount' => 0,
                    'outstanding_count' => 0,
                    'paid_amount' => 0,
                    'order_count' => 0,
                    'invoice_count' => 0
                ],
                'trend' => [],
                'payment_methods' => []
            ];
        }
    }
    
    /**
     * 支払いアラート情報を取得
     * @return array アラートデータ
     */
    public function getPaymentAlerts() {
        try {
            $alerts = [];
            
            // 🔴 期限切れ請求書チェック
            $overdueSql = "SELECT 
                              COUNT(*) as overdue_count,
                              COALESCE(SUM(total_amount), 0) as overdue_amount
                           FROM invoices 
                           WHERE status = 'issued' AND due_date < CURDATE()";
            $overdueStmt = $this->db->query($overdueSql);
            $overdueData = $overdueStmt->fetch();
            
            if ($overdueData['overdue_count'] > 0) {
                $alerts[] = [
                    'type' => 'error',
                    'title' => '期限切れ請求書',
                    'message' => "期限切れの請求書が{$overdueData['overdue_count']}件あります（￥" . number_format($overdueData['overdue_amount']) . "）",
                    'amount' => (float)$overdueData['overdue_amount'],
                    'action_url' => 'pages/payments.php?filter=overdue'
                ];
            }
            
            // 🟡 高額未回収チェック
            $highAmountSql = "SELECT 
                                 COUNT(*) as high_amount_count,
                                 COALESCE(SUM(total_amount), 0) as high_amount_total
                              FROM invoices 
                              WHERE status = 'issued' AND total_amount >= 50000";
            $highAmountStmt = $this->db->query($highAmountSql);
            $highAmountData = $highAmountStmt->fetch();
            
            if ($highAmountData['high_amount_count'] > 0) {
                $alerts[] = [
                    'type' => 'warning',
                    'title' => '高額未回収',
                    'message' => "5万円以上の未回収が{$highAmountData['high_amount_count']}件あります",
                    'amount' => (float)$highAmountData['high_amount_total'],
                    'action_url' => 'pages/payments.php?filter=high_amount'
                ];
            }
            
            // 🔵 期限間近チェック
            $soonDueSql = "SELECT 
                              COUNT(*) as soon_due_count
                           FROM invoices 
                           WHERE status = 'issued' 
                           AND due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
            $soonDueStmt = $this->db->query($soonDueSql);
            $soonDueData = $soonDueStmt->fetch();
            
            if ($soonDueData['soon_due_count'] > 0) {
                $alerts[] = [
                    'type' => 'info',
                    'title' => '期限間近',
                    'message' => "7日以内に期限を迎える請求書が{$soonDueData['soon_due_count']}件あります",
                    'amount' => 0,
                    'action_url' => 'pages/payments.php?filter=soon_due'
                ];
            }
            
            return [
                'alerts' => $alerts,
                'alert_count' => count($alerts)
            ];
            
        } catch (Exception $e) {
            error_log("PaymentManager::getPaymentAlerts Error: " . $e->getMessage());
            return [
                'alerts' => [],
                'alert_count' => 0
            ];
        }
    }
    
    /**
     * 未回収金額詳細情報を取得
     * @param array $filters フィルター条件
     * @return array 未回収データ
     */
    public function getOutstandingAmounts($filters = []) {
        try {
            $whereConditions = ["i.status = 'issued'"];
            $params = [];
            
            // 🔍 フィルター条件追加
            if (isset($filters['overdue_only']) && $filters['overdue_only']) {
                $whereConditions[] = "i.due_date < CURDATE()";
            }
            
            if (isset($filters['company_id']) && $filters['company_id']) {
                $whereConditions[] = "c.id = ?";
                $params[] = $filters['company_id'];
            }
            
            $whereClause = implode(' AND ', $whereConditions);
            
            $sql = "SELECT 
                       i.id as invoice_id,
                       i.invoice_number,
                       c.company_name,
                       i.total_amount as outstanding_amount,
                       i.due_date,
                       DATEDIFF(CURDATE(), i.due_date) as days_overdue,
                       CASE 
                           WHEN i.due_date < CURDATE() THEN 'overdue'
                           WHEN i.due_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 'soon_due'
                           ELSE 'normal'
                       END as status
                    FROM invoices i
                    LEFT JOIN companies c ON i.company_id = c.id
                    WHERE {$whereClause}
                    ORDER BY i.due_date ASC, i.total_amount DESC";
            
            $stmt = $this->db->query($sql, $params);
            return $stmt->fetchAll();
            
        } catch (Exception $e) {
            error_log("PaymentManager::getOutstandingAmounts Error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * 支払い記録を追加
     * @param array $paymentData 支払いデータ
     * @return array 処理結果
     */
    public function recordPayment($paymentData) {
        try {
            $this->db->beginTransaction();
            
            // 💰 支払いテーブルに記録
            $paymentSql = "INSERT INTO payments (
                              invoice_id, payment_date, amount, 
                              payment_method, reference_number, notes
                           ) VALUES (?, ?, ?, ?, ?, ?)";
            $this->db->query($paymentSql, [
                $paymentData['invoice_id'],
                $paymentData['payment_date'],
                $paymentData['amount'],
                $paymentData['payment_method'],
                $paymentData['reference_number'] ?? null,
                $paymentData['notes'] ?? null
            ]);
            
            // 📄 請求書ステータス更新
            $this->updateInvoiceStatus($paymentData['invoice_id']);
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => '支払いが記録されました'
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("PaymentManager::recordPayment Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => '支払い記録に失敗しました: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 支払い履歴取得
     * @param array $filters フィルター条件
     * @return array 支払い履歴
     */
    public function getPaymentHistory($filters = []) {
        try {
            $whereConditions = ["1=1"];
            $params = [];
            
            if (isset($filters['company_id']) && $filters['company_id']) {
                $whereConditions[] = "c.id = ?";
                $params[] = $filters['company_id'];
            }
            
            $whereClause = implode(' AND ', $whereConditions);
            
            $sql = "SELECT 
                       p.id,
                       p.payment_date,
                       p.amount,
                       p.payment_method,
                       p.reference_number,
                       i.invoice_number,
                       c.company_name
                    FROM payments p
                    LEFT JOIN invoices i ON p.invoice_id = i.id
                    LEFT JOIN companies c ON i.company_id = c.id
                    WHERE {$whereClause}
                    ORDER BY p.payment_date DESC
                    LIMIT 50";
            
            $stmt = $this->db->query($sql, $params);
            return $stmt->fetchAll();
            
        } catch (Exception $e) {
            error_log("PaymentManager::getPaymentHistory Error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * 支払い予定取得
     * @param array $filters フィルター条件
     * @return array 支払い予定
     */
    public function getPaymentSchedule($filters = []) {
        try {
            $sql = "SELECT 
                       i.id as invoice_id,
                       i.invoice_number,
                       i.due_date,
                       i.total_amount,
                       c.company_name,
                       DATEDIFF(i.due_date, CURDATE()) as days_until_due
                    FROM invoices i
                    LEFT JOIN companies c ON i.company_id = c.id
                    WHERE i.status = 'issued'
                    ORDER BY i.due_date ASC";
            
            $stmt = $this->db->query($sql);
            return $stmt->fetchAll();
            
        } catch (Exception $e) {
            error_log("PaymentManager::getPaymentSchedule Error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * 期間条件を生成
     * @param string $period 期間
     * @return string SQL条件
     */
    private function getPeriodCondition($period) {
        switch ($period) {
            case 'month':
                return ">= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
            case 'year':
                return ">= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
            case 'all':
            default:
                return ">= '2020-01-01'";
        }
    }
    
    /**
     * 請求書ステータス更新
     * @param int $invoiceId 請求書ID
     */
    private function updateInvoiceStatus($invoiceId) {
        // 💰 支払い総額取得
        $paidSql = "SELECT COALESCE(SUM(amount), 0) as total_paid 
                    FROM payments 
                    WHERE invoice_id = ?";
        $paidStmt = $this->db->query($paidSql, [$invoiceId]);
        $paidData = $paidStmt->fetch();
        
        // 📄 請求書金額取得
        $invoiceSql = "SELECT total_amount FROM invoices WHERE id = ?";
        $invoiceStmt = $this->db->query($invoiceSql, [$invoiceId]);
        $invoiceData = $invoiceStmt->fetch();
        
        // ✅ ステータス判定・更新
        $totalAmount = (float)$invoiceData['total_amount'];
        $totalPaid = (float)$paidData['total_paid'];
        
        if ($totalPaid >= $totalAmount) {
            $newStatus = 'paid';
        } elseif ($totalPaid > 0) {
            $newStatus = 'partially_paid';
        } else {
            $newStatus = 'issued';
        }
        
        $updateSql = "UPDATE invoices SET status = ? WHERE id = ?";
        $this->db->query($updateSql, [$newStatus, $invoiceId]);
    }
}
?>
