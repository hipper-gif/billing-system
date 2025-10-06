<?php
/**
 * PaymentManager.php - 支払い管理エンジン 完全統一版 v5.0
 * 
 * 設計原則:
 * - 自己完結原則: 内部でDatabase::getInstance()を呼び出し
 * - メソッド統一原則: 仕様書に定義された全メソッドを実装
 * - 段階的フォールバック: エラー時も安全なデフォルト値を返す
 * 
 * 必要なテーブル:
 * - invoices: 請求書データ
 * - payments: 支払い記録
 * - companies: 企業情報
 * 
 * 最終更新: 2025年10月6日
 * バージョン: 5.0
 */

class PaymentManager {
    private $db;

    /**
     * コンストラクタ - 自己完結原則準拠
     * 引数なし、内部でDatabaseインスタンスを取得
     */
    public function __construct() {
        // config/database.php の Database統一版（16メソッド）を使用
        $this->db = Database::getInstance();
    }

    /**
     * 1. 未回収金額一覧取得
     * 
     * @param array $filters フィルター条件
     *   - overdue_only: bool 期限切れのみ
     *   - company_id: int 企業IDフィルタ
     *   - limit: int 取得件数制限
     * @return array 未回収金額データ
     */
    public function getOutstandingAmounts($filters = []) {
        try {
            $overdue_only = $filters['overdue_only'] ?? false;
            $company_id = $filters['company_id'] ?? null;
            $limit = $filters['limit'] ?? 100;

            $sql = "
                SELECT 
                    i.id as invoice_id,
                    i.invoice_number,
                    i.company_id,
                    c.company_name,
                    i.total_amount,
                    i.due_date,
                    i.status,
                    COALESCE(SUM(p.amount), 0) as paid_amount,
                    (i.total_amount - COALESCE(SUM(p.amount), 0)) as outstanding_amount,
                    DATEDIFF(CURDATE(), i.due_date) as overdue_days
                FROM invoices i
                LEFT JOIN companies c ON i.company_id = c.id
                LEFT JOIN payments p ON i.id = p.invoice_id AND p.status = 'completed'
                WHERE i.status IN ('issued', 'partial_paid')
                AND (i.total_amount - COALESCE(SUM(p.amount), 0)) > 0
            ";

            if ($company_id) {
                $sql .= " AND i.company_id = :company_id";
            }

            $sql .= " GROUP BY i.id, i.invoice_number, i.company_id, c.company_name, 
                      i.total_amount, i.due_date, i.status";

            if ($overdue_only) {
                $sql .= " HAVING overdue_days > 0";
            }

            $sql .= " ORDER BY i.due_date ASC LIMIT :limit";

            $stmt = $this->db->query($sql);
            if ($company_id) {
                $stmt->bindValue(':company_id', $company_id, PDO::PARAM_INT);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 合計計算
            $total_outstanding = 0;
            foreach ($results as $row) {
                $total_outstanding += $row['outstanding_amount'];
            }

            return [
                'success' => true,
                'data' => $results,
                'total_outstanding' => $total_outstanding,
                'count' => count($results)
            ];

        } catch (Exception $e) {
            error_log("PaymentManager::getOutstandingAmounts Error: " . $e->getMessage());
            
            // フォールバック: 安全なデフォルト値
            return [
                'success' => false,
                'data' => [],
                'total_outstanding' => 0,
                'count' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 2. 支払い統計データ取得
     * 
     * @param string $period 集計期間 ('month', 'quarter', 'year')
     * @return array 統計データ
     */
    public function getPaymentStatistics($period = 'month') {
        try {
            // 期間設定
            $dateFilter = $this->getPeriodDateFilter($period);

            // サマリー統計
            $summary = $this->getPaymentSummary($dateFilter);

            // 月別推移データ
            $trend = $this->getPaymentTrend($period);

            // 支払い方法別統計
            $paymentMethods = $this->getPaymentMethodStats($dateFilter);

            return [
                'success' => true,
                'summary' => $summary,
                'trend' => $trend,
                'payment_methods' => $paymentMethods,
                'period' => $period
            ];

        } catch (Exception $e) {
            error_log("PaymentManager::getPaymentStatistics Error: " . $e->getMessage());
            
            // フォールバック: 安全なデフォルト値
            return [
                'success' => false,
                'summary' => [
                    'total_amount' => 0,
                    'outstanding_amount' => 0,
                    'outstanding_count' => 0,
                    'paid_amount' => 0
                ],
                'trend' => [],
                'payment_methods' => [],
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 3. 支払いアラート取得
     * 
     * @return array アラート情報
     */
    public function getPaymentAlerts() {
        try {
            $alerts = [];
            $alert_count = 0;

            // 期限切れ請求書チェック
            $overdue = $this->getOverdueInvoices();
            if (!empty($overdue)) {
                foreach ($overdue as $invoice) {
                    $alerts[] = [
                        'type' => 'error',
                        'title' => '支払い期限超過',
                        'message' => "{$invoice['company_name']} の請求書（{$invoice['invoice_number']}）が期限を{$invoice['overdue_days']}日超過しています",
                        'amount' => $invoice['outstanding_amount'],
                        'action_url' => 'pages/payments.php?invoice_id=' . $invoice['invoice_id']
                    ];
                    $alert_count++;
                }
            }

            // 期限間近の請求書チェック（7日以内）
            $upcoming = $this->getUpcomingDueInvoices(7);
            if (!empty($upcoming)) {
                foreach ($upcoming as $invoice) {
                    $alerts[] = [
                        'type' => 'warning',
                        'title' => '支払い期限接近',
                        'message' => "{$invoice['company_name']} の請求書が{$invoice['days_until_due']}日後に期限を迎えます",
                        'amount' => $invoice['outstanding_amount'],
                        'action_url' => 'pages/payments.php?invoice_id=' . $invoice['invoice_id']
                    ];
                    $alert_count++;
                }
            }

            // 高額未回収チェック（50万円以上）
            $highValue = $this->getHighValueOutstanding(500000);
            if (!empty($highValue)) {
                foreach ($highValue as $invoice) {
                    $alerts[] = [
                        'type' => 'warning',
                        'title' => '高額未回収',
                        'message' => "{$invoice['company_name']} に高額な未回収金があります",
                        'amount' => $invoice['outstanding_amount'],
                        'action_url' => 'pages/payments.php?invoice_id=' . $invoice['invoice_id']
                    ];
                    $alert_count++;
                }
            }

            return [
                'success' => true,
                'alert_count' => $alert_count,
                'alerts' => $alerts
            ];

        } catch (Exception $e) {
            error_log("PaymentManager::getPaymentAlerts Error: " . $e->getMessage());
            
            // フォールバック
            return [
                'success' => false,
                'alert_count' => 0,
                'alerts' => [],
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 4. 支払い記録登録
     * 
     * @param int $invoiceId 請求書ID
     * @param array $paymentData 支払いデータ
     * @return array 処理結果
     */
    public function recordPayment($invoiceId, $paymentData) {
        try {
            $this->db->beginTransaction();

            // 請求書情報取得
            $invoice = $this->getInvoiceById($invoiceId);
            if (!$invoice) {
                throw new Exception('請求書が見つかりません');
            }

            // 支払い記録挿入
            $sql = "
                INSERT INTO payments (
                    invoice_id, amount, payment_date, payment_method,
                    reference_number, notes, status, created_at
                ) VALUES (
                    :invoice_id, :amount, :payment_date, :payment_method,
                    :reference_number, :notes, 'completed', NOW()
                )
            ";

            $stmt = $this->db->query($sql);
            $stmt->execute([
                ':invoice_id' => $invoiceId,
                ':amount' => $paymentData['amount'],
                ':payment_date' => $paymentData['payment_date'],
                ':payment_method' => $paymentData['payment_method'],
                ':reference_number' => $paymentData['reference_number'] ?? '',
                ':notes' => $paymentData['notes'] ?? ''
            ]);

            $paymentId = $this->db->lastInsertId();

            // 請求書ステータス更新
            $this->updateInvoiceStatus($invoiceId);

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

    /**
     * 5. 支払いキャンセル
     * 
     * @param int $paymentId 支払いID
     * @param string $reason キャンセル理由
     * @return array 処理結果
     */
    public function cancelPayment($paymentId, $reason) {
        try {
            $this->db->beginTransaction();

            // 支払い情報取得
            $sql = "SELECT * FROM payments WHERE id = :payment_id";
            $stmt = $this->db->query($sql, [':payment_id' => $paymentId]);
            $payment = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$payment) {
                throw new Exception('支払い記録が見つかりません');
            }

            // 支払いステータス更新
            $sql = "
                UPDATE payments 
                SET status = 'cancelled',
                    notes = CONCAT(notes, '\n[キャンセル理由: ', :reason, ']'),
                    updated_at = NOW()
                WHERE id = :payment_id
            ";
            
            $this->db->query($sql, [
                ':payment_id' => $paymentId,
                ':reason' => $reason
            ]);

            // 請求書ステータス更新
            $this->updateInvoiceStatus($payment['invoice_id']);

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
                'message' => '支払いキャンセルに失敗しました: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 6. 支払い履歴取得
     * 
     * @param int $invoiceId 請求書ID
     * @return array 支払い履歴
     */
    public function getPaymentHistory($invoiceId) {
        try {
            $sql = "
                SELECT 
                    p.*,
                    i.invoice_number,
                    i.company_id,
                    c.company_name
                FROM payments p
                JOIN invoices i ON p.invoice_id = i.id
                JOIN companies c ON i.company_id = c.id
                WHERE p.invoice_id = :invoice_id
                ORDER BY p.payment_date DESC, p.created_at DESC
            ";

            $stmt = $this->db->query($sql, [':invoice_id' => $invoiceId]);
            $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'data' => $history,
                'count' => count($history)
            ];

        } catch (Exception $e) {
            error_log("PaymentManager::getPaymentHistory Error: " . $e->getMessage());
            
            return [
                'success' => false,
                'data' => [],
                'count' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 7. 支払い一覧取得
     * 
     * @param array $filters フィルター条件
     * @return array 支払い一覧
     */
    public function getPaymentsList($filters = []) {
        try {
            $sql = "
                SELECT 
                    p.*,
                    i.invoice_number,
                    i.company_id,
                    c.company_name
                FROM payments p
                JOIN invoices i ON p.invoice_id = i.id
                JOIN companies c ON i.company_id = c.id
                WHERE 1=1
            ";

            $params = [];

            if (isset($filters['status'])) {
                $sql .= " AND p.status = :status";
                $params[':status'] = $filters['status'];
            }

            if (isset($filters['date_from'])) {
                $sql .= " AND p.payment_date >= :date_from";
                $params[':date_from'] = $filters['date_from'];
            }

            if (isset($filters['date_to'])) {
                $sql .= " AND p.payment_date <= :date_to";
                $params[':date_to'] = $filters['date_to'];
            }

            $sql .= " ORDER BY p.payment_date DESC, p.created_at DESC";

            $stmt = $this->db->query($sql, $params);
            $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'data' => $payments,
                'count' => count($payments)
            ];

        } catch (Exception $e) {
            error_log("PaymentManager::getPaymentsList Error: " . $e->getMessage());
            
            return [
                'success' => false,
                'data' => [],
                'count' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    // ========================================
    // 支払い方法関連メソッド
    // ========================================

    /**
     * 支払い方法の選択肢配列を取得
     */
    public static function getPaymentMethods() {
        return [
            'cash' => '現金',
            'bank_transfer' => '銀行振込',
            'account_debit' => '口座引き落とし',
            'paypay' => 'PayPay',
            'mixed' => '混合',
            'other' => 'その他'
        ];
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
            
            if ($value === 'paypay') {
                $emoji = '📱 ';
            } elseif ($value === 'cash') {
                $emoji = '💰 ';
            } elseif ($value === 'bank_transfer') {
                $emoji = '🏦 ';
            } elseif ($value === 'account_debit') {
                $emoji = '💳 ';
            }
            
            $options .= "<option value=\"{$value}\"{$selectedAttr}>{$emoji}{$label}</option>\n";
        }
        
        return $options;
    }

    /**
     * 支払い方法の妥当性チェック
     */
    public static function isValidPaymentMethod($paymentMethod) {
        $allowedMethods = array_keys(self::getPaymentMethods());
        return in_array($paymentMethod, $allowedMethods);
    }

    // ========================================
    // プライベートヘルパーメソッド
    // ========================================

    /**
     * 期間フィルター生成
     */
    private function getPeriodDateFilter($period) {
        switch ($period) {
            case 'year':
                $start = date('Y-01-01');
                $end = date('Y-12-31');
                break;
            case 'quarter':
                $currentMonth = date('n');
                $quarterStart = floor(($currentMonth - 1) / 3) * 3 + 1;
                $start = date('Y-' . str_pad($quarterStart, 2, '0', STR_PAD_LEFT) . '-01');
                $end = date('Y-m-t', strtotime($start . ' +2 months'));
                break;
            case 'month':
            default:
                $start = date('Y-m-01');
                $end = date('Y-m-t');
                break;
        }

        return ['start' => $start, 'end' => $end];
    }

    /**
     * 支払いサマリー取得
     */
    private function getPaymentSummary($dateFilter) {
        $sql = "
            SELECT 
                COALESCE(SUM(i.total_amount), 0) as total_amount,
                COALESCE(SUM(CASE WHEN i.status IN ('issued', 'partial_paid') 
                    THEN i.total_amount - COALESCE(p.paid_amount, 0) 
                    ELSE 0 END), 0) as outstanding_amount,
                COUNT(CASE WHEN i.status IN ('issued', 'partial_paid') THEN 1 END) as outstanding_count,
                COALESCE(SUM(CASE WHEN i.status = 'paid' THEN i.total_amount ELSE 0 END), 0) as paid_amount
            FROM invoices i
            LEFT JOIN (
                SELECT invoice_id, SUM(amount) as paid_amount 
                FROM payments 
                WHERE status = 'completed'
                GROUP BY invoice_id
            ) p ON i.id = p.invoice_id
            WHERE i.issue_date BETWEEN :start AND :end
        ";

        $stmt = $this->db->query($sql, [
            ':start' => $dateFilter['start'],
            ':end' => $dateFilter['end']
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [
            'total_amount' => 0,
            'outstanding_amount' => 0,
            'outstanding_count' => 0,
            'paid_amount' => 0
        ];
    }

    /**
     * 月別推移データ取得
     */
    private function getPaymentTrend($period) {
        $months = ($period === 'year') ? 12 : 6;
        
        $sql = "
            SELECT 
                DATE_FORMAT(i.issue_date, '%Y-%m') as month,
                COALESCE(SUM(i.total_amount), 0) as monthly_amount
            FROM invoices i
            WHERE i.issue_date >= DATE_SUB(CURDATE(), INTERVAL :months MONTH)
            GROUP BY DATE_FORMAT(i.issue_date, '%Y-%m')
            ORDER BY month ASC
        ";

        $stmt = $this->db->query($sql, [':months' => $months]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * 支払い方法別統計取得
     */
    private function getPaymentMethodStats($dateFilter) {
        $sql = "
            SELECT 
                p.payment_method,
                COALESCE(SUM(p.amount), 0) as total_amount,
                COUNT(*) as count
            FROM payments p
            WHERE p.payment_date BETWEEN :start AND :end
            AND p.status = 'completed'
            GROUP BY p.payment_method
            ORDER BY total_amount DESC
        ";

        $stmt = $this->db->query($sql, [
            ':start' => $dateFilter['start'],
            ':end' => $dateFilter['end']
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * 期限切れ請求書取得
     */
    private function getOverdueInvoices() {
        $sql = "
            SELECT 
                i.id as invoice_id,
                i.invoice_number,
                c.company_name,
                i.total_amount,
                i.due_date,
                COALESCE(SUM(p.amount), 0) as paid_amount,
                (i.total_amount - COALESCE(SUM(p.amount), 0)) as outstanding_amount,
                DATEDIFF(CURDATE(), i.due_date) as overdue_days
            FROM invoices i
            JOIN companies c ON i.company_id = c.id
            LEFT JOIN payments p ON i.id = p.invoice_id AND p.status = 'completed'
            WHERE i.status IN ('issued', 'partial_paid')
            AND i.due_date < CURDATE()
            GROUP BY i.id, i.invoice_number, c.company_name, i.total_amount, i.due_date
            HAVING outstanding_amount > 0
            ORDER BY overdue_days DESC
            LIMIT 10
        ";

        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 期限間近の請求書取得
     */
    private function getUpcomingDueInvoices($days = 7) {
        $sql = "
            SELECT 
                i.id as invoice_id,
                i.invoice_number,
                c.company_name,
                i.total_amount,
                i.due_date,
                COALESCE(SUM(p.amount), 0) as paid_amount,
                (i.total_amount - COALESCE(SUM(p.amount), 0)) as outstanding_amount,
                DATEDIFF(i.due_date, CURDATE()) as days_until_due
            FROM invoices i
            JOIN companies c ON i.company_id = c.id
            LEFT JOIN payments p ON i.id = p.invoice_id AND p.status = 'completed'
            WHERE i.status IN ('issued', 'partial_paid')
            AND i.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL :days DAY)
            GROUP BY i.id, i.invoice_number, c.company_name, i.total_amount, i.due_date
            HAVING outstanding_amount > 0
            ORDER BY days_until_due ASC
            LIMIT 10
        ";

        $stmt = $this->db->query($sql, [':days' => $days]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 高額未回収取得
     */
    private function getHighValueOutstanding($threshold) {
        $sql = "
            SELECT 
                i.id as invoice_id,
                i.invoice_number,
                c.company_name,
                i.total_amount,
                COALESCE(SUM(p.amount), 0) as paid_amount,
                (i.total_amount - COALESCE(SUM(p.amount), 0)) as outstanding_amount
            FROM invoices i
            JOIN companies c ON i.company_id = c.id
            LEFT JOIN payments p ON i.id = p.invoice_id AND p.status = 'completed'
            WHERE i.status IN ('issued', 'partial_paid')
            GROUP BY i.id, i.invoice_number, c.company_name, i.total_amount
            HAVING outstanding_amount >= :threshold
            ORDER BY outstanding_amount DESC
            LIMIT 5
        ";

        $stmt = $this->db->query($sql, [':threshold' => $threshold]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 請求書取得
     */
    private function getInvoiceById($invoiceId) {
        $sql = "SELECT * FROM invoices WHERE id = :invoice_id";
        $stmt = $this->db->query($sql, [':invoice_id' => $invoiceId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * 請求書ステータス更新
     */
    private function updateInvoiceStatus($invoiceId) {
        // 支払い済み金額の合計を取得
        $sql = "
            SELECT 
                i.total_amount,
                COALESCE(SUM(p.amount), 0) as paid_amount
            FROM invoices i
            LEFT JOIN payments p ON i.id = p.invoice_id AND p.status = 'completed'
            WHERE i.id = :invoice_id
            GROUP BY i.id, i.total_amount
        ";

        $stmt = $this->db->query($sql, [':invoice_id' => $invoiceId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            $status = 'issued';
            if ($result['paid_amount'] >= $result['total_amount']) {
                $status = 'paid';
            } elseif ($result['paid_amount'] > 0) {
                $status = 'partial_paid';
            }

            $updateSql = "UPDATE invoices SET status = :status WHERE id = :invoice_id";
            $this->db->query($updateSql, [
                ':status' => $status,
                ':invoice_id' => $invoiceId
            ]);
        }
    }
}
