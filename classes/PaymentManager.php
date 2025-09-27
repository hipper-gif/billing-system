<?php
/**
 * PaymentManager.php - 支払い管理エンジン（完全実装版・構文エラー修正）
 * Smiley配食事業システム
 * 
 * 最終更新: 2025-09-27
 * 対応: payments.php完全対応、invoicesテーブル連携、統計データ生成
 */

class PaymentManager {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * 1. 未回収金額取得
     * @param array $filters フィルター条件
     * @return array 未回収データ
     */
    public function getOutstandingAmounts($filters = array()) {
        try {
            $sql = "
                SELECT 
                    i.id as invoice_id,
                    i.invoice_number,
                    i.company_id,
                    c.company_name,
                    i.total_amount,
                    COALESCE(SUM(p.amount), 0) as paid_amount,
                    (i.total_amount - COALESCE(SUM(p.amount), 0)) as outstanding_amount,
                    i.due_date,
                    DATEDIFF(CURDATE(), i.due_date) as overdue_days,
                    i.status as invoice_status
                FROM invoices i
                LEFT JOIN companies c ON i.company_id = c.id
                LEFT JOIN payments p ON i.id = p.invoice_id AND p.status = 'completed'
                WHERE i.status IN ('issued', 'overdue')
            ";
            
            $params = array();
            
            // フィルター処理
            if (!empty($filters['company_id'])) {
                $sql .= " AND i.company_id = ?";
                $params[] = $filters['company_id'];
            }
            
            if (!empty($filters['overdue_only'])) {
                $sql .= " AND i.due_date < CURDATE()";
            }
            
            if (!empty($filters['amount_min'])) {
                $sql .= " AND i.total_amount >= ?";
                $params[] = $filters['amount_min'];
            }
            
            $sql .= " GROUP BY i.id HAVING outstanding_amount > 0";
            $sql .= " ORDER BY i.due_date ASC, outstanding_amount DESC";
            
            $stmt = $this->db->query($sql, $params);
            $outstanding = $stmt->fetchAll();
            
            // 統計計算
            $totalOutstanding = array_sum(array_column($outstanding, 'outstanding_amount'));
            $overdueCount = count(array_filter($outstanding, function($item) {
                return $item['overdue_days'] > 0;
            }));
            
            return array(
                'success' => true,
                'data' => array(
                    'outstanding' => $outstanding,
                    'total_outstanding' => $totalOutstanding,
                    'total_count' => count($outstanding),
                    'overdue_count' => $overdueCount,
                    'summary' => array(
                        'total_amount' => $totalOutstanding,
                        'overdue_amount' => array_sum(array_column(
                            array_filter($outstanding, function($item) {
                                return $item['overdue_days'] > 0;
                            }), 'outstanding_amount'
                        ))
                    )
                )
            );
            
        } catch (Exception $e) {
            error_log("PaymentManager::getOutstandingAmounts Error: " . $e->getMessage());
            return array(
                'success' => false,
                'message' => '未回収金額の取得でエラーが発生しました: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * 2. 支払い統計取得（Chart.js用）
     * @param string $period 期間（month, quarter, year）
     * @return array 統計データ
     */
    public function getPaymentStatistics($period = 'month') {
        try {
            $dateFormat = '';
            $dateGroupBy = '';
            $limitClause = '';
            
            switch ($period) {
                case 'year':
                    $dateFormat = '%Y';
                    $dateGroupBy = 'YEAR(payment_date)';
                    $limitClause = 'LIMIT 5';
                    break;
                case 'quarter':
                    $dateFormat = '%Y-Q%q';
                    $dateGroupBy = 'YEAR(payment_date), QUARTER(payment_date)';
                    $limitClause = 'LIMIT 8';
                    break;
                case 'month':
                default:
                    $dateFormat = '%Y-%m';
                    $dateGroupBy = 'YEAR(payment_date), MONTH(payment_date)';
                    $limitClause = 'LIMIT 12';
                    break;
            }
            
            // 支払い推移データ
            $sql = "
                SELECT 
                    DATE_FORMAT(payment_date, '{$dateFormat}') as period_label,
                    COUNT(*) as payment_count,
                    SUM(amount) as total_amount,
                    AVG(amount) as average_amount
                FROM payments 
                WHERE status = 'completed' 
                  AND payment_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                GROUP BY {$dateGroupBy}
                ORDER BY payment_date DESC
                {$limitClause}
            ";
            
            $stmt = $this->db->query($sql);
            $trends = array_reverse($stmt->fetchAll());
            
            // 支払い方法別統計
            $methodSql = "
                SELECT 
                    payment_method,
                    COUNT(*) as count,
                    SUM(amount) as total_amount,
                    ROUND((COUNT(*) * 100.0 / (SELECT COUNT(*) FROM payments WHERE status = 'completed')), 2) as percentage
                FROM payments 
                WHERE status = 'completed'
                  AND payment_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                GROUP BY payment_method
                ORDER BY total_amount DESC
            ";
            
            $stmt = $this->db->query($methodSql);
            $paymentMethods = $stmt->fetchAll();
            
            // Chart.js用データ変換
            $chartData = array(
                'trends' => array(
                    'labels' => array_column($trends, 'period_label'),
                    'datasets' => array(
                        array(
                            'label' => '支払い金額',
                            'data' => array_column($trends, 'total_amount'),
                            'backgroundColor' => 'rgba(75, 192, 192, 0.2)',
                            'borderColor' => 'rgba(75, 192, 192, 1)',
                            'borderWidth' => 2
                        ),
                        array(
                            'label' => '件数',
                            'data' => array_column($trends, 'payment_count'),
                            'backgroundColor' => 'rgba(255, 99, 132, 0.2)',
                            'borderColor' => 'rgba(255, 99, 132, 1)',
                            'borderWidth' => 2,
                            'yAxisID' => 'y1'
                        )
                    )
                ),
                'methods' => array(
                    'labels' => array_column($paymentMethods, 'payment_method'),
                    'datasets' => array(
                        array(
                            'data' => array_column($paymentMethods, 'total_amount'),
                            'backgroundColor' => array(
                                '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF'
                            )
                        )
                    )
                )
            );
            
            return array(
                'success' => true,
                'data' => array(
                    'chart_data' => $chartData,
                    'payment_methods' => $paymentMethods,
                    'period' => $period,
                    'summary' => array(
                        'total_payments' => array_sum(array_column($trends, 'payment_count')),
                        'total_amount' => array_sum(array_column($trends, 'total_amount')),
                        'average_amount' => array_sum(array_column($trends, 'total_amount')) / max(1, array_sum(array_column($trends, 'payment_count')))
                    )
                )
            );
            
        } catch (Exception $e) {
            error_log("PaymentManager::getPaymentStatistics Error: " . $e->getMessage());
            return array(
                'success' => false,
                'message' => '支払い統計の取得でエラーが発生しました: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * 3. 支払いアラート取得
     * @return array アラートデータ
     */
    public function getPaymentAlerts() {
        try {
            $alerts = array();
            
            // 期限切れ請求書
            $overdueSql = "
                SELECT 
                    i.id,
                    i.invoice_number,
                    c.company_name,
                    i.total_amount,
                    i.due_date,
                    DATEDIFF(CURDATE(), i.due_date) as overdue_days
                FROM invoices i
                JOIN companies c ON i.company_id = c.id
                WHERE i.status = 'issued' 
                  AND i.due_date < CURDATE()
                ORDER BY overdue_days DESC
                LIMIT 5
            ";
            
            $stmt = $this->db->query($overdueSql);
            $overdueInvoices = $stmt->fetchAll();
            
            foreach ($overdueInvoices as $invoice) {
                $alerts[] = array(
                    'type' => 'overdue',
                    'level' => 'danger',
                    'title' => '期限切れ請求書',
                    'message' => $invoice['company_name'] . " - " . $invoice['invoice_number'] . " (期限切れ " . $invoice['overdue_days'] . "日)",
                    'amount' => $invoice['total_amount'],
                    'invoice_id' => $invoice['id'],
                    'priority' => $invoice['overdue_days'] > 30 ? 'high' : 'medium'
                );
            }
            
            // 期限間近請求書（7日以内）
            $upcomingSql = "
                SELECT 
                    i.id,
                    i.invoice_number,
                    c.company_name,
                    i.total_amount,
                    i.due_date,
                    DATEDIFF(i.due_date, CURDATE()) as days_until_due
                FROM invoices i
                JOIN companies c ON i.company_id = c.id
                WHERE i.status = 'issued' 
                  AND i.due_date >= CURDATE()
                  AND i.due_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                ORDER BY days_until_due ASC
                LIMIT 5
            ";
            
            $stmt = $this->db->query($upcomingSql);
            $upcomingInvoices = $stmt->fetchAll();
            
            foreach ($upcomingInvoices as $invoice) {
                $alerts[] = array(
                    'type' => 'upcoming',
                    'level' => 'warning',
                    'title' => '期限間近請求書',
                    'message' => $invoice['company_name'] . " - " . $invoice['invoice_number'] . " (あと " . $invoice['days_until_due'] . "日)",
                    'amount' => $invoice['total_amount'],
                    'invoice_id' => $invoice['id'],
                    'priority' => 'medium'
                );
            }
            
            // 優先度順ソート
            usort($alerts, function($a, $b) {
                $priorities = array('high' => 3, 'medium' => 2, 'low' => 1);
                return $priorities[$b['priority']] - $priorities[$a['priority']];
            });
            
            return array(
                'success' => true,
                'data' => array(
                    'alerts' => $alerts,
                    'total_count' => count($alerts),
                    'high_priority_count' => count(array_filter($alerts, function($alert) {
                        return $alert['priority'] === 'high';
                    }))
                )
            );
            
        } catch (Exception $e) {
            error_log("PaymentManager::getPaymentAlerts Error: " . $e->getMessage());
            return array(
                'success' => false,
                'message' => 'アラート取得でエラーが発生しました: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * 4. 支払い記録
     * @param int $invoiceId 請求書ID
     * @param array $paymentData 支払いデータ
     * @return array 処理結果
     */
    public function recordPayment($invoiceId, $paymentData) {
        try {
            $this->db->beginTransaction();
            
            // 請求書存在確認
            $invoiceCheck = $this->db->query(
                "SELECT id, total_amount, status FROM invoices WHERE id = ?",
                array($invoiceId)
            )->fetch();
            
            if (!$invoiceCheck) {
                throw new Exception('指定された請求書が見つかりません');
            }
            
            // 支払い記録挿入
            $sql = "
                INSERT INTO payments (
                    invoice_id, amount, payment_date, payment_method, 
                    reference_number, notes, status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, 'completed', NOW())
            ";
            
            $params = array(
                $invoiceId,
                $paymentData['amount'],
                isset($paymentData['payment_date']) ? $paymentData['payment_date'] : date('Y-m-d'),
                isset($paymentData['payment_method']) ? $paymentData['payment_method'] : 'cash',
                isset($paymentData['reference_number']) ? $paymentData['reference_number'] : '',
                isset($paymentData['notes']) ? $paymentData['notes'] : ''
            );
            
            $this->db->query($sql, $params);
            $paymentId = $this->db->lastInsertId();
            
            // 請求書ステータス更新チェック
            $paidTotal = $this->db->query(
                "SELECT SUM(amount) as total FROM payments WHERE invoice_id = ? AND status = 'completed'",
                array($invoiceId)
            )->fetch();
            
            $paidAmount = $paidTotal['total'] ? $paidTotal['total'] : 0;
            
            if ($paidAmount >= $invoiceCheck['total_amount']) {
                // 完全支払い
                $this->db->query(
                    "UPDATE invoices SET status = 'paid', updated_at = NOW() WHERE id = ?",
                    array($invoiceId)
                );
            } elseif ($paidAmount > 0) {
                // 部分支払い
                $this->db->query(
                    "UPDATE invoices SET status = 'partial_paid', updated_at = NOW() WHERE id = ?",
                    array($invoiceId)
                );
            }
            
            $this->db->commit();
            
            return array(
                'success' => true,
                'message' => '支払いを記録しました',
                'data' => array(
                    'payment_id' => $paymentId,
                    'invoice_id' => $invoiceId,
                    'amount' => $paymentData['amount'],
                    'remaining_amount' => max(0, $invoiceCheck['total_amount'] - $paidAmount)
                )
            );
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("PaymentManager::recordPayment Error: " . $e->getMessage());
            return array(
                'success' => false,
                'message' => '支払い記録でエラーが発生しました: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * 5. 支払いキャンセル
     * @param int $paymentId 支払いID
     * @param string $reason キャンセル理由
     * @return array 処理結果
     */
    public function cancelPayment($paymentId, $reason) {
        try {
            $this->db->beginTransaction();
            
            // 支払い記録確認
            $payment = $this->db->query(
                "SELECT id, invoice_id, amount, status FROM payments WHERE id = ?",
                array($paymentId)
            )->fetch();
            
            if (!$payment) {
                throw new Exception('指定された支払い記録が見つかりません');
            }
            
            if ($payment['status'] === 'cancelled') {
                throw new Exception('この支払いは既にキャンセルされています');
            }
            
            // 支払いキャンセル
            $this->db->query(
                "UPDATE payments SET status = 'cancelled', notes = CONCAT(COALESCE(notes, ''), '\nキャンセル理由: ', ?), updated_at = NOW() WHERE id = ?",
                array($reason, $paymentId)
            );
            
            // 請求書ステータス再計算
            $remainingPaid = $this->db->query(
                "SELECT SUM(amount) as total FROM payments WHERE invoice_id = ? AND status = 'completed'",
                array($payment['invoice_id'])
            )->fetch();
            
            $remainingAmount = $remainingPaid['total'] ? $remainingPaid['total'] : 0;
            
            $invoice = $this->db->query(
                "SELECT total_amount FROM invoices WHERE id = ?",
                array($payment['invoice_id'])
            )->fetch();
            
            if ($remainingAmount >= $invoice['total_amount']) {
                $newStatus = 'paid';
            } elseif ($remainingAmount > 0) {
                $newStatus = 'partial_paid';
            } else {
                $newStatus = 'issued';
            }
            
            $this->db->query(
                "UPDATE invoices SET status = ?, updated_at = NOW() WHERE id = ?",
                array($newStatus, $payment['invoice_id'])
            );
            
            $this->db->commit();
            
            return array(
                'success' => true,
                'message' => '支払いをキャンセルしました',
                'data' => array(
                    'payment_id' => $paymentId,
                    'cancelled_amount' => $payment['amount'],
                    'new_invoice_status' => $newStatus
                )
            );
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("PaymentManager::cancelPayment Error: " . $e->getMessage());
            return array(
                'success' => false,
                'message' => '支払いキャンセルでエラーが発生しました: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * 6. 支払い履歴取得
     * @param int $invoiceId 請求書ID
     * @return array 支払い履歴
     */
    public function getPaymentHistory($invoiceId) {
        try {
            $sql = "
                SELECT 
                    p.id,
                    p.amount,
                    p.payment_date,
                    p.payment_method,
                    p.reference_number,
                    p.notes,
                    p.status,
                    p.created_at,
                    i.invoice_number,
                    i.total_amount as invoice_total
                FROM payments p
                JOIN invoices i ON p.invoice_id = i.id
                WHERE p.invoice_id = ?
                ORDER BY p.payment_date DESC, p.created_at DESC
            ";
            
            $stmt = $this->db->query($sql, array($invoiceId));
            $payments = $stmt->fetchAll();
            
            // 累計計算
            $runningTotal = 0;
            foreach ($payments as &$payment) {
                if ($payment['status'] === 'completed') {
                    $runningTotal += $payment['amount'];
                }
                $payment['running_total'] = $runningTotal;
                $payment['payment_method_label'] = $this->getPaymentMethodLabel($payment['payment_method']);
            }
            
            return array(
                'success' => true,
                'data' => array(
                    'payments' => array_reverse($payments), // 日付順に戻す
                    'total_paid' => $runningTotal,
                    'payment_count' => count(array_filter($payments, function($p) {
                        return $p['status'] === 'completed';
                    }))
                )
            );
            
        } catch (Exception $e) {
            error_log("PaymentManager::getPaymentHistory Error: " . $e->getMessage());
            return array(
                'success' => false,
                'message' => '支払い履歴の取得でエラーが発生しました: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * 7. 支払い一覧取得（フィルター対応）
     * @param array $filters フィルター条件
     * @return array 支払い一覧
     */
    public function getPaymentsList($filters = array()) {
        try {
            $sql = "
                SELECT 
                    p.id,
                    p.amount,
                    p.payment_date,
                    p.payment_method,
                    p.reference_number,
                    p.notes,
                    p.status,
                    p.created_at,
                    i.id as invoice_id,
                    i.invoice_number,
                    i.total_amount as invoice_total,
                    c.company_name
                FROM payments p
                JOIN invoices i ON p.invoice_id = i.id
                JOIN companies c ON i.company_id = c.id
                WHERE 1=1
            ";
            
            $params = array();
            
            // フィルター処理
            if (!empty($filters['status'])) {
                $sql .= " AND p.status = ?";
                $params[] = $filters['status'];
            }
            
            if (!empty($filters['payment_method'])) {
                $sql .= " AND p.payment_method = ?";
                $params[] = $filters['payment_method'];
            }
            
            if (!empty($filters['company_id'])) {
                $sql .= " AND i.company_id = ?";
                $params[] = $filters['company_id'];
            }
            
            if (!empty($filters['date_from'])) {
                $sql .= " AND p.payment_date >= ?";
                $params[] = $filters['date_from'];
            }
            
            if (!empty($filters['date_to'])) {
                $sql .= " AND p.payment_date <= ?";
                $params[] = $filters['date_to'];
            }
            
            // ソート
            $orderBy = isset($filters['order_by']) ? $filters['order_by'] : 'payment_date';
            $orderDir = isset($filters['order_dir']) ? strtoupper($filters['order_dir']) : 'DESC';
            $sql .= " ORDER BY {$orderBy} {$orderDir}";
            
            // ページング
            if (isset($filters['limit'])) {
                $page = isset($filters['page']) ? $filters['page'] : 1;
                $offset = ($page - 1) * $filters['limit'];
                $sql .= " LIMIT {$offset}, {$filters['limit']}";
            }
            
            $stmt = $this->db->query($sql, $params);
            $payments = $stmt->fetchAll();
            
            // 各支払いにラベル追加
            foreach ($payments as &$payment) {
                $payment['payment_method_label'] = $this->getPaymentMethodLabel($payment['payment_method']);
                $payment['status_label'] = $this->getStatusLabel($payment['status']);
            }
            
            return array(
                'success' => true,
                'data' => array(
                    'payments' => $payments,
                    'total' => count($payments),
                    'summary' => array(
                        'total_amount' => array_sum(array_column($payments, 'amount')),
                        'completed_count' => count(array_filter($payments, function($p) {
                            return $p['status'] === 'completed';
                        }))
                    )
                )
            );
            
        } catch (Exception $e) {
            error_log("PaymentManager::getPaymentsList Error: " . $e->getMessage());
            return array(
                'success' => false,
                'message' => '支払い一覧の取得でエラーが発生しました: ' . $e->getMessage()
            );
        }
    }
    
    // ========== ユーティリティメソッド ==========
    
    /**
     * 支払い方法の選択肢配列を取得
     */
    public static function getPaymentMethods() {
        return array(
            'cash' => '現金',
            'bank_transfer' => '銀行振込',
            'account_debit' => '口座引き落とし',
            'paypay' => 'PayPay',
            'mixed' => '混合',
            'other' => 'その他'
        );
    }
    
    /**
     * 支払い方法ラベル取得
     */
    private function getPaymentMethodLabel($method) {
        $methods = self::getPaymentMethods();
        return isset($methods[$method]) ? $methods[$method] : $method;
    }
    
    /**
     * ステータスラベル取得
     */
    private function getStatusLabel($status) {
        $statuses = array(
            'completed' => '完了',
            'pending' => '処理中',
            'cancelled' => 'キャンセル',
            'failed' => '失敗'
        );
        return isset($statuses[$status]) ? $statuses[$status] : $status;
    }
    
    /**
     * 支払い方法の妥当性チェック
     */
    public static function isValidPaymentMethod($paymentMethod) {
        $methods = self::getPaymentMethods();
        return array_key_exists($paymentMethod, $methods);
    }
    
    /**
     * PayPay支払い用の特別処理（既存機能保持）
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
            return array(
                'success' => false,
                'message' => 'PayPay支払い処理でエラーが発生しました: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * PayPay用の参照番号生成
     */
    private function generatePayPayReference($qrData) {
        return 'PP' . date('Ymd') . '_' . substr(md5($qrData), 0, 8);
    }
}
?>
