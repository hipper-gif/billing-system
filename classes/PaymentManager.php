<?php
/**
 * PaymentManager.php - 支払い管理クラス（完全実装版）
 * Smiley配食事業システム用
 * 最終更新: 2025年9月16日
 */

require_once __DIR__ . '/Database.php';

class PaymentManager {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * 支払い統計情報を取得（index.phpで必要）
     * @param string $period 期間（'month', 'year', 'all'）
     * @return array 統計情報
     */
    public function getPaymentStatistics($period = 'month') {
        try {
            $result = [
                'summary' => [
                    'total_amount' => 0,
                    'outstanding_amount' => 0,
                    'outstanding_count' => 0,
                    'paid_amount' => 0,
                    'paid_count' => 0
                ],
                'trend' => [],
                'payment_methods' => []
            ];

            // 期間設定
            $dateCondition = $this->getDateCondition($period);
            
            // 1. サマリー情報を取得
            $result['summary'] = $this->getSummaryStatistics($dateCondition);
            
            // 2. 月別推移データを取得
            $result['trend'] = $this->getTrendData($period);
            
            // 3. 支払い方法別統計を取得
            $result['payment_methods'] = $this->getPaymentMethodStatistics($dateCondition);

            return $result;

        } catch (Exception $e) {
            error_log("PaymentManager::getPaymentStatistics Error: " . $e->getMessage());
            return $this->getEmptyStatistics();
        }
    }

    /**
     * 支払いアラート情報を取得（index.phpで必要）
     * @return array アラート情報
     */
    public function getPaymentAlerts() {
        try {
            $alerts = [];
            $alertCount = 0;

            // 1. 期限切れ請求書をチェック
            $overdueInvoices = $this->getOverdueInvoices();
            foreach ($overdueInvoices as $invoice) {
                $alerts[] = [
                    'type' => 'error',
                    'title' => '支払い期限切れ',
                    'message' => $invoice['company_name'] . 'の請求書が期限切れです',
                    'amount' => $invoice['total_amount'],
                    'action_url' => 'pages/payments.php?invoice_id=' . $invoice['id'],
                    'created_at' => date('Y-m-d H:i:s')
                ];
                $alertCount++;
            }

            // 2. 高額未回収をチェック
            $highAmountOutstanding = $this->getHighAmountOutstanding(50000); // 5万円以上
            foreach ($highAmountOutstanding as $outstanding) {
                $alerts[] = [
                    'type' => 'warning',
                    'title' => '高額未回収',
                    'message' => $outstanding['company_name'] . 'の未回収金額が高額です',
                    'amount' => $outstanding['outstanding_amount'],
                    'action_url' => 'pages/payments.php?company_id=' . $outstanding['company_id'],
                    'created_at' => date('Y-m-d H:i:s')
                ];
                $alertCount++;
            }

            // 3. 今週期限の請求書をチェック
            $soonDueInvoices = $this->getSoonDueInvoices(7); // 7日以内
            foreach ($soonDueInvoices as $invoice) {
                $alerts[] = [
                    'type' => 'warning',
                    'title' => '支払い期限間近',
                    'message' => $invoice['company_name'] . 'の請求書が' . $invoice['days_until_due'] . '日後期限です',
                    'amount' => $invoice['total_amount'],
                    'action_url' => 'pages/payments.php?invoice_id=' . $invoice['id'],
                    'created_at' => date('Y-m-d H:i:s')
                ];
                $alertCount++;
            }

            return [
                'alerts' => $alerts,
                'alert_count' => $alertCount
            ];

        } catch (Exception $e) {
            error_log("PaymentManager::getPaymentAlerts Error: " . $e->getMessage());
            return ['alerts' => [], 'alert_count' => 0];
        }
    }

    /**
     * 未回収金額情報を取得（index.phpで必要）
     * @param array $filters フィルター条件
     * @return array 未回収金額情報
     */
    public function getOutstandingAmounts($filters = []) {
        try {
            $overdueOnly = $filters['overdue_only'] ?? false;
            $companyId = $filters['company_id'] ?? null;
            
            $sql = "
                SELECT 
                    i.id as invoice_id,
                    i.invoice_number,
                    i.total_amount,
                    i.due_date,
                    i.created_at as invoice_date,
                    c.id as company_id,
                    c.company_name,
                    COALESCE(SUM(p.amount), 0) as paid_amount,
                    (i.total_amount - COALESCE(SUM(p.amount), 0)) as outstanding_amount,
                    CASE 
                        WHEN i.due_date < CURDATE() THEN 'overdue'
                        WHEN i.due_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 'soon_due'
                        ELSE 'normal'
                    END as status,
                    DATEDIFF(CURDATE(), i.due_date) as days_overdue
                FROM invoices i
                JOIN companies c ON i.company_id = c.id
                LEFT JOIN payments p ON i.id = p.invoice_id AND p.payment_status = 'completed'
                WHERE i.status IN ('issued', 'overdue')
            ";

            $params = [];
            
            if ($companyId) {
                $sql .= " AND c.id = ?";
                $params[] = $companyId;
            }
            
            $sql .= " GROUP BY i.id, c.id HAVING outstanding_amount > 0";
            
            if ($overdueOnly) {
                $sql .= " AND status = 'overdue'";
            }
            
            $sql .= " ORDER BY outstanding_amount DESC, days_overdue DESC";
            
            $stmt = $this->db->query($sql, $params);
            $results = $stmt->fetchAll();

            return $results;

        } catch (Exception $e) {
            error_log("PaymentManager::getOutstandingAmounts Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 支払い方法の選択肢配列を取得
     * @return array 支払い方法の配列
     */
    public static function getPaymentMethods() {
        return [
            'cash' => '現金',
            'bank_transfer' => '銀行振込',
            'account_debit' => '口座引き落とし',
            'paypay' => 'PayPay',
            'credit_card' => 'クレジットカード',
            'mixed' => '混合',
            'other' => 'その他'
        ];
    }

    /**
     * 支払い記録を登録
     * @param array $paymentData 支払いデータ
     * @return array 処理結果
     */
    public function recordPayment($paymentData) {
        try {
            $this->db->beginTransaction();

            // 1. 支払い記録を挿入
            $sql = "
                INSERT INTO payments (
                    invoice_id, amount, payment_date, payment_method, 
                    payment_status, reference_number, notes, created_at
                ) VALUES (?, ?, ?, ?, 'completed', ?, ?, NOW())
            ";
            
            $params = [
                $paymentData['invoice_id'],
                $paymentData['amount'],
                $paymentData['payment_date'] ?? date('Y-m-d'),
                $paymentData['payment_method'] ?? 'cash',
                $paymentData['reference_number'] ?? null,
                $paymentData['notes'] ?? null
            ];
            
            $stmt = $this->db->query($sql, $params);
            $paymentId = $this->db->lastInsertId();

            // 2. 請求書のステータスを更新
            $this->updateInvoiceStatus($paymentData['invoice_id']);

            $this->db->commit();

            return [
                'success' => true,
                'message' => '支払いを記録しました',
                'payment_id' => $paymentId
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

    // ======================
    // プライベートメソッド群
    // ======================

    /**
     * 期間条件を取得
     */
    private function getDateCondition($period) {
        switch ($period) {
            case 'month':
                return "AND DATE_FORMAT(created_at, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')";
            case 'year':
                return "AND YEAR(created_at) = YEAR(CURDATE())";
            case 'last_month':
                return "AND DATE_FORMAT(created_at, '%Y-%m') = DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), '%Y-%m')";
            default:
                return "";
        }
    }

    /**
     * サマリー統計を取得
     */
    private function getSummaryStatistics($dateCondition) {
        try {
            // 今月の売上（注文ベース）
            $sql = "
                SELECT 
                    COALESCE(SUM(total_amount), 0) as total_amount,
                    COUNT(*) as order_count
                FROM orders 
                WHERE 1=1 " . str_replace('created_at', 'delivery_date', $dateCondition);
            
            $stmt = $this->db->query($sql);
            $salesData = $stmt->fetch();

            // 未回収金額計算
            $sql = "
                SELECT 
                    COALESCE(SUM(i.total_amount - COALESCE(p.paid_amount, 0)), 0) as outstanding_amount,
                    COUNT(i.id) as outstanding_count,
                    COALESCE(SUM(COALESCE(p.paid_amount, 0)), 0) as paid_amount
                FROM invoices i
                LEFT JOIN (
                    SELECT 
                        invoice_id, 
                        SUM(amount) as paid_amount,
                        COUNT(*) as payment_count
                    FROM payments 
                    WHERE payment_status = 'completed'
                    GROUP BY invoice_id
                ) p ON i.id = p.invoice_id
                WHERE i.status IN ('issued', 'overdue', 'paid')
            ";
            
            $stmt = $this->db->query($sql);
            $paymentData = $stmt->fetch();

            return [
                'total_amount' => $salesData['total_amount'] ?? 0,
                'order_count' => $salesData['order_count'] ?? 0,
                'outstanding_amount' => $paymentData['outstanding_amount'] ?? 0,
                'outstanding_count' => $paymentData['outstanding_count'] ?? 0,
                'paid_amount' => $paymentData['paid_amount'] ?? 0
            ];

        } catch (Exception $e) {
            error_log("getSummaryStatistics Error: " . $e->getMessage());
            return [
                'total_amount' => 0,
                'order_count' => 0,
                'outstanding_amount' => 0,
                'outstanding_count' => 0,
                'paid_amount' => 0
            ];
        }
    }

    /**
     * 月別推移データを取得
     */
    private function getTrendData($period) {
        try {
            $sql = "
                SELECT 
                    DATE_FORMAT(delivery_date, '%Y-%m') as month,
                    DATE_FORMAT(delivery_date, '%m月') as month_label,
                    SUM(total_amount) as monthly_amount,
                    COUNT(*) as monthly_count
                FROM orders 
                WHERE delivery_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                GROUP BY DATE_FORMAT(delivery_date, '%Y-%m')
                ORDER BY month ASC
            ";
            
            $stmt = $this->db->query($sql);
            return $stmt->fetchAll();

        } catch (Exception $e) {
            error_log("getTrendData Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 支払い方法別統計を取得
     */
    private function getPaymentMethodStatistics($dateCondition) {
        try {
            $sql = "
                SELECT 
                    payment_method,
                    SUM(amount) as total_amount,
                    COUNT(*) as payment_count,
                    AVG(amount) as average_amount
                FROM payments 
                WHERE payment_status = 'completed' " . 
                str_replace('created_at', 'payment_date', $dateCondition) . "
                GROUP BY payment_method
                ORDER BY total_amount DESC
            ";
            
            $stmt = $this->db->query($sql);
            return $stmt->fetchAll();

        } catch (Exception $e) {
            error_log("getPaymentMethodStatistics Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 期限切れ請求書を取得
     */
    private function getOverdueInvoices() {
        try {
            $sql = "
                SELECT 
                    i.id,
                    i.invoice_number,
                    i.total_amount,
                    i.due_date,
                    c.company_name,
                    DATEDIFF(CURDATE(), i.due_date) as days_overdue
                FROM invoices i
                JOIN companies c ON i.company_id = c.id
                WHERE i.due_date < CURDATE() 
                AND i.status = 'issued'
                ORDER BY days_overdue DESC
                LIMIT 10
            ";
            
            $stmt = $this->db->query($sql);
            return $stmt->fetchAll();

        } catch (Exception $e) {
            error_log("getOverdueInvoices Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 高額未回収を取得
     */
    private function getHighAmountOutstanding($threshold = 50000) {
        try {
            $sql = "
                SELECT 
                    c.id as company_id,
                    c.company_name,
                    SUM(i.total_amount - COALESCE(p.paid_amount, 0)) as outstanding_amount
                FROM companies c
                JOIN invoices i ON c.id = i.company_id
                LEFT JOIN (
                    SELECT invoice_id, SUM(amount) as paid_amount
                    FROM payments 
                    WHERE payment_status = 'completed'
                    GROUP BY invoice_id
                ) p ON i.id = p.invoice_id
                WHERE i.status IN ('issued', 'overdue')
                GROUP BY c.id, c.company_name
                HAVING outstanding_amount >= ?
                ORDER BY outstanding_amount DESC
                LIMIT 5
            ";
            
            $stmt = $this->db->query($sql, [$threshold]);
            return $stmt->fetchAll();

        } catch (Exception $e) {
            error_log("getHighAmountOutstanding Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 期限間近請求書を取得
     */
    private function getSoonDueInvoices($days = 7) {
        try {
            $sql = "
                SELECT 
                    i.id,
                    i.invoice_number,
                    i.total_amount,
                    i.due_date,
                    c.company_name,
                    DATEDIFF(i.due_date, CURDATE()) as days_until_due
                FROM invoices i
                JOIN companies c ON i.company_id = c.id
                WHERE i.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
                AND i.status = 'issued'
                ORDER BY days_until_due ASC
                LIMIT 5
            ";
            
            $stmt = $this->db->query($sql, [$days]);
            return $stmt->fetchAll();

        } catch (Exception $e) {
            error_log("getSoonDueInvoices Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 請求書ステータスを更新
     */
    private function updateInvoiceStatus($invoiceId) {
        try {
            // 支払い合計を計算
            $sql = "
                SELECT 
                    i.total_amount,
                    COALESCE(SUM(p.amount), 0) as paid_amount
                FROM invoices i
                LEFT JOIN payments p ON i.id = p.invoice_id AND p.payment_status = 'completed'
                WHERE i.id = ?
                GROUP BY i.id, i.total_amount
            ";
            
            $stmt = $this->db->query($sql, [$invoiceId]);
            $result = $stmt->fetch();
            
            if ($result) {
                $totalAmount = $result['total_amount'];
                $paidAmount = $result['paid_amount'];
                
                if ($paidAmount >= $totalAmount) {
                    $status = 'paid';
                } elseif ($paidAmount > 0) {
                    $status = 'partially_paid';
                } else {
                    $status = 'issued';
                }
                
                // ステータス更新
                $updateSql = "UPDATE invoices SET status = ?, updated_at = NOW() WHERE id = ?";
                $this->db->query($updateSql, [$status, $invoiceId]);
            }

        } catch (Exception $e) {
            error_log("updateInvoiceStatus Error: " . $e->getMessage());
        }
    }

    /**
     * 空の統計データを返す
     */
    private function getEmptyStatistics() {
        return [
            'summary' => [
                'total_amount' => 0,
                'outstanding_amount' => 0,
                'outstanding_count' => 0,
                'paid_amount' => 0,
                'order_count' => 0
            ],
            'trend' => [],
            'payment_methods' => []
        ];
    }

    // ======================
    // 追加の支払い管理メソッド
    // ======================

    /**
     * 支払い履歴を取得
     */
    public function getPaymentHistory($filters = []) {
        try {
            $sql = "
                SELECT 
                    p.*,
                    i.invoice_number,
                    c.company_name
                FROM payments p
                JOIN invoices i ON p.invoice_id = i.id
                JOIN companies c ON i.company_id = c.id
                WHERE p.payment_status = 'completed'
                ORDER BY p.payment_date DESC, p.created_at DESC
                LIMIT 50
            ";
            
            $stmt = $this->db->query($sql);
            return $stmt->fetchAll();

        } catch (Exception $e) {
            error_log("getPaymentHistory Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 支払い予定を取得
     */
    public function getPaymentSchedule($filters = []) {
        try {
            $sql = "
                SELECT 
                    i.id as invoice_id,
                    i.invoice_number,
                    i.total_amount,
                    i.due_date,
                    c.company_name,
                    COALESCE(SUM(p.amount), 0) as paid_amount,
                    (i.total_amount - COALESCE(SUM(p.amount), 0)) as remaining_amount
                FROM invoices i
                JOIN companies c ON i.company_id = c.id
                LEFT JOIN payments p ON i.id = p.invoice_id AND p.payment_status = 'completed'
                WHERE i.status IN ('issued', 'overdue', 'partially_paid')
                AND i.due_date >= CURDATE()
                GROUP BY i.id
                HAVING remaining_amount > 0
                ORDER BY i.due_date ASC
            ";
            
            $stmt = $this->db->query($sql);
            return $stmt->fetchAll();

        } catch (Exception $e) {
            error_log("getPaymentSchedule Error: " . $e->getMessage());
            return [];
        }
    }

    // PayPay支払い用の既存メソッド（そのまま保持）
    public function processPayPayPayment($paymentData) {
        try {
            $paymentData['transaction_fee'] = 0;
            $paymentData['payment_method'] = 'paypay';
            
            if (isset($paymentData['qr_code_data'])) {
                $paymentData['reference_number'] = $this->generatePayPayReference($paymentData['qr_code_data']);
            }
            
            return $this->recordPayment($paymentData);
            
        } catch (Exception $e) {
            error_log("PayPay payment processing error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'PayPay支払い処理でエラーが発生しました: ' . $e->getMessage()
            ];
        }
    }

    private function generatePayPayReference($qrData) {
        return 'PP' . date('Ymd') . '_' . substr(md5($qrData), 0, 8);
    }

    public static function isValidPaymentMethod($paymentMethod) {
        $allowedMethods = array_keys(self::getPaymentMethods());
        return in_array($paymentMethod, $allowedMethods);
    }
}
?>
