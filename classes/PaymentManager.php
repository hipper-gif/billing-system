<?php
/**
 * PaymentManager.php - 支払い管理エンジン完全版
 * Smiley配食事業システム - マテリアルデザイン対応
 * 最終更新: 2025年9月3日
 */

class PaymentManager {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * 支払い方法の選択肢配列を取得（PayPay対応）
     * @return array 支払い方法の配列
     */
    public static function getPaymentMethods() {
        return [
            'cash' => '💰 現金',
            'bank_transfer' => '🏦 銀行振込',
            'account_debit' => '💳 口座引き落とし',
            'paypay' => '📱 PayPay',
            'mixed' => '🔄 混合',
            'other' => '📝 その他'
        ];
    }

    /**
     * 未回収金額一覧を取得 - payments.php必須メソッド
     * @param array $filters フィルター条件
     * @return array 未回収データ
     */
    public function getOutstandingAmounts($filters = []) {
        try {
            $sql = "SELECT 
                        i.id,
                        i.invoice_number,
                        i.total_amount,
                        i.due_date,
                        i.payment_status,
                        c.company_name,
                        d.department_name,
                        COALESCE(SUM(p.amount), 0) as paid_amount,
                        (i.total_amount - COALESCE(SUM(p.amount), 0)) as outstanding_amount,
                        DATEDIFF(CURDATE(), i.due_date) as overdue_days
                    FROM invoices i
                    LEFT JOIN companies c ON i.company_id = c.id
                    LEFT JOIN departments d ON i.department_id = d.id
                    LEFT JOIN payments p ON i.id = p.invoice_id
                    WHERE i.payment_status != 'paid'";
            
            $params = [];
            
            // フィルター条件を追加
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
            
            $sql .= " GROUP BY i.id 
                      HAVING outstanding_amount > 0
                      ORDER BY i.due_date ASC, outstanding_amount DESC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            return [
                'success' => true,
                'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
                'total_outstanding' => $this->getTotalOutstanding(),
                'overdue_count' => $this->getOverdueCount()
            ];
            
        } catch (Exception $e) {
            error_log("Error in getOutstandingAmounts: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'データ取得エラー: ' . $e->getMessage(),
                'data' => []
            ];
        }
    }

    /**
     * 支払い統計情報を取得 - ダッシュボード必須メソッド
     * @param string $period 集計期間 ('today', 'week', 'month', 'year')
     * @return array 統計データ
     */
    public function getPaymentStatistics($period = 'month') {
        try {
            $dateCondition = $this->getDateCondition($period);
            
            // 基本統計
            $sql = "SELECT 
                        COUNT(*) as total_payments,
                        SUM(amount) as total_amount,
                        AVG(amount) as average_amount,
                        payment_method,
                        COUNT(*) as method_count
                    FROM payments 
                    WHERE {$dateCondition}
                    GROUP BY payment_method";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $methodStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // 未回収統計
            $outstandingSql = "SELECT 
                                  COUNT(*) as outstanding_count,
                                  SUM(total_amount - COALESCE(paid_amount, 0)) as outstanding_amount
                               FROM (
                                   SELECT i.id, i.total_amount, SUM(p.amount) as paid_amount
                                   FROM invoices i
                                   LEFT JOIN payments p ON i.id = p.invoice_id
                                   WHERE i.payment_status != 'paid'
                                   GROUP BY i.id
                               ) as outstanding_data";
            
            $stmt = $this->db->prepare($outstandingSql);
            $stmt->execute();
            $outstanding = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // 月別推移データ
            $trendSql = "SELECT 
                            DATE_FORMAT(payment_date, '%Y-%m') as month,
                            SUM(amount) as monthly_amount,
                            COUNT(*) as monthly_count
                         FROM payments 
                         WHERE payment_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                         GROUP BY DATE_FORMAT(payment_date, '%Y-%m')
                         ORDER BY month";
            
            $stmt = $this->db->prepare($trendSql);
            $stmt->execute();
            $trend = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'success' => true,
                'period' => $period,
                'payment_methods' => $methodStats,
                'outstanding' => $outstanding,
                'trend' => $trend,
                'summary' => [
                    'total_amount' => array_sum(array_column($methodStats, 'total_amount')),
                    'total_count' => array_sum(array_column($methodStats, 'method_count')),
                    'outstanding_amount' => $outstanding['outstanding_amount'] ?? 0,
                    'outstanding_count' => $outstanding['outstanding_count'] ?? 0
                ]
            ];
            
        } catch (Exception $e) {
            error_log("Error in getPaymentStatistics: " . $e->getMessage());
            return [
                'success' => false,
                'message' => '統計データ取得エラー: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 支払いアラート情報を取得 - ダッシュボード必須メソッド
     * @return array アラート情報
     */
    public function getPaymentAlerts() {
        try {
            $alerts = [];
            
            // 期限超過アラート
            $overdueSql = "SELECT 
                              COUNT(*) as count,
                              SUM(total_amount - COALESCE(paid_amount, 0)) as amount
                           FROM (
                               SELECT i.id, i.total_amount, i.due_date, SUM(p.amount) as paid_amount
                               FROM invoices i
                               LEFT JOIN payments p ON i.id = p.invoice_id
                               WHERE i.payment_status != 'paid' AND i.due_date < CURDATE()
                               GROUP BY i.id
                               HAVING (i.total_amount - COALESCE(SUM(p.amount), 0)) > 0
                           ) as overdue_data";
            
            $stmt = $this->db->prepare($overdueSql);
            $stmt->execute();
            $overdue = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($overdue['count'] > 0) {
                $alerts[] = [
                    'type' => 'error',
                    'title' => '期限超過',
                    'message' => "支払い期限を過ぎた請求書が{$overdue['count']}件あります",
                    'amount' => $overdue['amount'],
                    'priority' => 'high',
                    'action_url' => 'pages/payments.php?filter=overdue'
                ];
            }
            
            // 期限間近アラート（3日以内）
            $soonSql = "SELECT 
                           COUNT(*) as count,
                           SUM(total_amount - COALESCE(paid_amount, 0)) as amount
                        FROM (
                            SELECT i.id, i.total_amount, i.due_date, SUM(p.amount) as paid_amount
                            FROM invoices i
                            LEFT JOIN payments p ON i.id = p.invoice_id
                            WHERE i.payment_status != 'paid' 
                              AND i.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)
                            GROUP BY i.id
                            HAVING (i.total_amount - COALESCE(SUM(p.amount), 0)) > 0
                        ) as soon_data";
            
            $stmt = $this->db->prepare($soonSql);
            $stmt->execute();
            $soon = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($soon['count'] > 0) {
                $alerts[] = [
                    'type' => 'warning',
                    'title' => '期限間近',
                    'message' => "3日以内に期限を迎える請求書が{$soon['count']}件あります",
                    'amount' => $soon['amount'],
                    'priority' => 'medium',
                    'action_url' => 'pages/payments.php?filter=due_soon'
                ];
            }
            
            // 高額未回収アラート（10万円以上）
            $highAmountSql = "SELECT 
                                 COUNT(*) as count,
                                 SUM(outstanding_amount) as total_amount
                              FROM (
                                  SELECT (i.total_amount - COALESCE(SUM(p.amount), 0)) as outstanding_amount
                                  FROM invoices i
                                  LEFT JOIN payments p ON i.id = p.invoice_id
                                  WHERE i.payment_status != 'paid'
                                  GROUP BY i.id
                                  HAVING outstanding_amount >= 100000
                              ) as high_amount_data";
            
            $stmt = $this->db->prepare($highAmountSql);
            $stmt->execute();
            $highAmount = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($highAmount['count'] > 0) {
                $alerts[] = [
                    'type' => 'info',
                    'title' => '高額未回収',
                    'message' => "10万円以上の未回収請求書が{$highAmount['count']}件あります",
                    'amount' => $highAmount['total_amount'],
                    'priority' => 'medium',
                    'action_url' => 'pages/payments.php?filter=high_amount'
                ];
            }
            
            return [
                'success' => true,
                'alerts' => $alerts,
                'alert_count' => count($alerts)
            ];
            
        } catch (Exception $e) {
            error_log("Error in getPaymentAlerts: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'アラート取得エラー: ' . $e->getMessage(),
                'alerts' => []
            ];
        }
    }

    /**
     * 支払い記録を登録 - 支払い管理画面必須メソッド
     * @param array $paymentData 支払いデータ
     * @return array 処理結果
     */
    public function recordPayment($paymentData) {
        try {
            $this->db->beginTransaction();
            
            // 支払いデータの検証
            if (!$this->validatePaymentData($paymentData)) {
                throw new Exception('支払いデータが不正です');
            }
            
            // 支払い記録を挿入
            $sql = "INSERT INTO payments (
                        invoice_id, 
                        payment_date, 
                        amount, 
                        payment_method, 
                        reference_number, 
                        notes,
                        created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, NOW())";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $paymentData['invoice_id'],
                $paymentData['payment_date'],
                $paymentData['amount'],
                $paymentData['payment_method'],
                $paymentData['reference_number'] ?? '',
                $paymentData['notes'] ?? ''
            ]);
            
            $paymentId = $this->db->lastInsertId();
            
            // 請求書の支払い状況を更新
            $this->updateInvoicePaymentStatus($paymentData['invoice_id']);
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => '支払いを記録しました',
                'payment_id' => $paymentId,
                'amount_formatted' => number_format($paymentData['amount'])
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error in recordPayment: " . $e->getMessage());
            return [
                'success' => false,
                'message' => '支払い記録エラー: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 支払いをキャンセル - 支払い管理画面必須メソッド
     * @param int $paymentId 支払いID
     * @param string $reason キャンセル理由
     * @return array 処理結果
     */
    public function cancelPayment($paymentId, $reason = '') {
        try {
            $this->db->beginTransaction();
            
            // 支払い情報を取得
            $sql = "SELECT * FROM payments WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$paymentId]);
            $payment = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$payment) {
                throw new Exception('支払い情報が見つかりません');
            }
            
            // 支払いを削除（または無効化）
            $deleteSql = "DELETE FROM payments WHERE id = ?";
            $stmt = $this->db->prepare($deleteSql);
            $stmt->execute([$paymentId]);
            
            // キャンセル履歴を記録
            $historySql = "INSERT INTO payment_history (
                               payment_id, 
                               action, 
                               amount, 
                               reason, 
                               created_at
                           ) VALUES (?, 'cancelled', ?, ?, NOW())";
            $stmt = $this->db->prepare($historySql);
            $stmt->execute([$paymentId, $payment['amount'], $reason]);
            
            // 請求書の支払い状況を再計算
            $this->updateInvoicePaymentStatus($payment['invoice_id']);
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => '支払いをキャンセルしました',
                'cancelled_amount' => $payment['amount']
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error in cancelPayment: " . $e->getMessage());
            return [
                'success' => false,
                'message' => '支払いキャンセルエラー: ' . $e->getMessage()
            ];
        }
    }

    /**
     * PayPay支払い用の特別処理
     */
    public function processPayPayPayment($paymentData) {
        try {
            $paymentData['transaction_fee'] = 0; // PayPayは手数料無料
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

    // === プライベートメソッド群 ===

    /**
     * 期間条件を生成
     */
    private function getDateCondition($period) {
        switch ($period) {
            case 'today':
                return "payment_date = CURDATE()";
            case 'week':
                return "payment_date >= DATE_SUB(CURDATE(), INTERVAL 1 WEEK)";
            case 'month':
                return "payment_date >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
            case 'year':
                return "payment_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
            default:
                return "payment_date >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
        }
    }

    /**
     * 総未回収金額を取得
     */
    private function getTotalOutstanding() {
        $sql = "SELECT SUM(i.total_amount - COALESCE(p.paid_amount, 0)) as total
                FROM invoices i
                LEFT JOIN (
                    SELECT invoice_id, SUM(amount) as paid_amount 
                    FROM payments 
                    GROUP BY invoice_id
                ) p ON i.id = p.invoice_id
                WHERE i.payment_status != 'paid'";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'] ?? 0;
    }

    /**
     * 期限超過件数を取得
     */
    private function getOverdueCount() {
        $sql = "SELECT COUNT(*) as count
                FROM invoices i
                LEFT JOIN (
                    SELECT invoice_id, SUM(amount) as paid_amount 
                    FROM payments 
                    GROUP BY invoice_id
                ) p ON i.id = p.invoice_id
                WHERE i.payment_status != 'paid' 
                  AND i.due_date < CURDATE()
                  AND (i.total_amount - COALESCE(p.paid_amount, 0)) > 0";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] ?? 0;
    }

    /**
     * 支払いデータの検証
     */
    private function validatePaymentData($data) {
        return isset($data['invoice_id']) && 
               isset($data['payment_date']) && 
               isset($data['amount']) && 
               isset($data['payment_method']) &&
               $data['amount'] > 0 &&
               self::isValidPaymentMethod($data['payment_method']);
    }

    /**
     * 請求書の支払い状況を更新
     */
    private function updateInvoicePaymentStatus($invoiceId) {
        // 支払い済み金額を計算
        $sql = "SELECT i.total_amount, COALESCE(SUM(p.amount), 0) as paid_amount
                FROM invoices i
                LEFT JOIN payments p ON i.id = p.invoice_id
                WHERE i.id = ?
                GROUP BY i.id, i.total_amount";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$invoiceId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            $status = 'unpaid';
            if ($result['paid_amount'] >= $result['total_amount']) {
                $status = 'paid';
            } elseif ($result['paid_amount'] > 0) {
                $status = 'partial';
            }
            
            // ステータス更新
            $updateSql = "UPDATE invoices SET payment_status = ? WHERE id = ?";
            $stmt = $this->db->prepare($updateSql);
            $stmt->execute([$status, $invoiceId]);
        }
    }

    /**
     * PayPay用の参照番号生成
     */
    private function generatePayPayReference($qrData) {
        return 'PP' . date('Ymd') . '_' . substr(md5($qrData), 0, 8);
    }

    /**
     * 支払い方法の妥当性チェック
     */
    public static function isValidPaymentMethod($paymentMethod) {
        $allowedMethods = array_keys(self::getPaymentMethods());
        return in_array($paymentMethod, $allowedMethods);
    }

    /**
     * 支払い方法別の処理分岐
     */
    public function processPaymentByMethod($paymentData) {
        $method = $paymentData['payment_method'] ?? '';
        
        switch ($method) {
            case 'paypay':
                return $this->processPayPayPayment($paymentData);
            case 'cash':
            case 'bank_transfer':
            case 'account_debit':
            default:
                return $this->recordPayment($paymentData);
        }
    }
}
?>
