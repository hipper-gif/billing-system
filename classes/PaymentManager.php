<?php
/**
 * PaymentManager.php - 満額入金リスト対応版
 * 月末締めの入金管理を劇的に効率化する機能を実装
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/Database.php';

class PaymentManager {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * 満額入金リスト取得 - 月末締め特化
     * 期限切れ、期限間近、通常案件を優先度順で返却
     */
    public function getFullPaymentList($filters = []) {
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
                    c.contact_phone,
                    c.payment_method as preferred_payment_method,
                    c.is_vip,
                    CASE 
                        WHEN i.status = 'paid' THEN 0
                        WHEN i.due_date < CURDATE() THEN 
                            (DATEDIFF(CURDATE(), i.due_date) * 10) + (i.total_amount / 1000)
                        WHEN DATEDIFF(i.due_date, CURDATE()) <= 7 THEN 
                            (8 - DATEDIFF(i.due_date, CURDATE())) * 5 + (i.total_amount / 2000)
                        ELSE 1
                    END as priority_score,
                    CASE 
                        WHEN i.status = 'paid' THEN 'paid'
                        WHEN i.due_date < CURDATE() THEN 'overdue'
                        WHEN DATEDIFF(i.due_date, CURDATE()) <= 7 THEN 'due_soon'
                        ELSE 'pending'
                    END as payment_status,
                    DATEDIFF(CURDATE(), i.due_date) as overdue_days,
                    DATEDIFF(i.due_date, CURDATE()) as days_until_due,
                    COALESCE(SUM(p.payment_amount), 0) as paid_amount,
                    (i.total_amount - COALESCE(SUM(p.payment_amount), 0)) as outstanding_amount
                FROM invoices i
                LEFT JOIN companies c ON i.company_id = c.id
                LEFT JOIN payments p ON i.id = p.invoice_id AND p.payment_status = 'completed'
                WHERE i.is_active = 1
                    AND (i.total_amount - COALESCE(SUM(p.payment_amount), 0)) > 0
                GROUP BY i.id
                ORDER BY priority_score DESC, i.total_amount DESC
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 統計情報を計算
            $stats = $this->calculatePaymentListStats($invoices);

            return [
                'success' => true,
                'data' => [
                    'invoices' => $invoices,
                    'stats' => $stats
                ]
            ];

        } catch (Exception $e) {
            error_log("満額入金リスト取得エラー: " . $e->getMessage());
            return [
                'success' => false,
                'error' => '入金リストの取得中にエラーが発生しました: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 満額入金記録 - ワンクリック処理
     */
    public function recordFullPayment($invoiceId, $paymentData) {
        try {
            $this->db->beginTransaction();

            // 請求書情報を取得
            $invoice = $this->getInvoiceDetails($invoiceId);
            if (!$invoice) {
                throw new Exception('請求書が見つかりません');
            }

            // 既に支払済みかチェック
            if ($invoice['status'] === 'paid') {
                throw new Exception('この請求書は既に支払済みです');
            }

            // 未払い金額を計算
            $outstandingAmount = $this->calculateOutstandingAmount($invoiceId);
            
            // 満額入金として記録
            $paymentId = $this->insertPaymentRecord([
                'invoice_id' => $invoiceId,
                'payment_amount' => $outstandingAmount,
                'payment_date' => $paymentData['payment_date'] ?? date('Y-m-d'),
                'payment_method' => $paymentData['payment_method'] ?? 'cash',
                'reference_number' => $paymentData['reference_number'] ?? '',
                'notes' => $paymentData['notes'] ?? '満額入金処理',
                'payment_status' => 'completed'
            ]);

            // 請求書ステータスを「paid」に更新
            $this->updateInvoiceStatus($invoiceId, 'paid');

            // 入金履歴ログを記録
            $this->logPaymentActivity($invoiceId, $paymentId, 'full_payment_recorded', [
                'amount' => $outstandingAmount,
                'method' => $paymentData['payment_method'] ?? 'cash'
            ]);

            $this->db->commit();

            return [
                'success' => true,
                'message' => '満額入金を記録しました',
                'data' => [
                    'payment_id' => $paymentId,
                    'invoice_id' => $invoiceId,
                    'amount' => $outstandingAmount,
                    'company_name' => $invoice['company_name']
                ]
            ];

        } catch (Exception $e) {
            $this->db->rollback();
            error_log("満額入金記録エラー: " . $e->getMessage());
            return [
                'success' => false,
                'error' => '入金記録中にエラーが発生しました: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 一括満額入金処理 - 複数請求書の同時処理
     */
    public function recordBulkFullPayments($invoiceIds, $commonPaymentData = []) {
        try {
            $this->db->beginTransaction();
            
            $successCount = 0;
            $failCount = 0;
            $results = [];
            
            foreach ($invoiceIds as $invoiceId) {
                $result = $this->recordFullPayment($invoiceId, $commonPaymentData);
                
                if ($result['success']) {
                    $successCount++;
                    $results[] = [
                        'invoice_id' => $invoiceId,
                        'status' => 'success',
                        'payment_id' => $result['data']['payment_id'],
                        'amount' => $result['data']['amount']
                    ];
                } else {
                    $failCount++;
                    $results[] = [
                        'invoice_id' => $invoiceId,
                        'status' => 'failed',
                        'error' => $result['error']
                    ];
                }
            }

            if ($failCount > 0) {
                // 一部失敗がある場合はロールバック
                $this->db->rollback();
                return [
                    'success' => false,
                    'message' => '一括処理中にエラーが発生しました',
                    'data' => [
                        'total' => count($invoiceIds),
                        'success' => $successCount,
                        'failed' => $failCount,
                        'results' => $results
                    ]
                ];
            }

            $this->db->commit();

            return [
                'success' => true,
                'message' => "{$successCount}件の満額入金を記録しました",
                'data' => [
                    'processed' => $successCount,
                    'results' => $results
                ]
            ];

        } catch (Exception $e) {
            $this->db->rollback();
            error_log("一括満額入金エラー: " . $e->getMessage());
            return [
                'success' => false,
                'error' => '一括入金処理中にエラーが発生しました: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 入金統計情報取得 - ダッシュボード用
     */
    public function getPaymentStatistics($period = 'current_month') {
        try {
            $dateCondition = $this->getDateCondition($period);
            
            $sql = "
                SELECT 
                    COUNT(DISTINCT i.id) as total_invoices,
                    COUNT(DISTINCT CASE WHEN i.status = 'paid' THEN i.id END) as paid_invoices,
                    COUNT(DISTINCT CASE WHEN i.status != 'paid' THEN i.id END) as unpaid_invoices,
                    COUNT(DISTINCT CASE WHEN i.due_date < CURDATE() AND i.status != 'paid' THEN i.id END) as overdue_invoices,
                    COALESCE(SUM(CASE WHEN i.status = 'paid' THEN i.total_amount END), 0) as total_collected,
                    COALESCE(SUM(CASE WHEN i.status != 'paid' THEN i.total_amount END), 0) as total_outstanding,
                    COALESCE(SUM(CASE WHEN i.due_date < CURDATE() AND i.status != 'paid' THEN i.total_amount END), 0) as overdue_amount,
                    ROUND(
                        CASE 
                            WHEN SUM(i.total_amount) > 0 
                            THEN (SUM(CASE WHEN i.status = 'paid' THEN i.total_amount END) / SUM(i.total_amount)) * 100
                            ELSE 0 
                        END, 1
                    ) as collection_rate
                FROM invoices i
                LEFT JOIN companies c ON i.company_id = c.id
                WHERE i.is_active = 1 {$dateCondition}
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);

            // 支払方法別統計
            $paymentMethodStats = $this->getPaymentMethodStatistics($period);

            return [
                'success' => true,
                'data' => [
                    'summary' => $stats,
                    'payment_methods' => $paymentMethodStats,
                    'period' => $period,
                    'generated_at' => date('Y-m-d H:i:s')
                ]
            ];

        } catch (Exception $e) {
            error_log("入金統計取得エラー: " . $e->getMessage());
            return [
                'success' => false,
                'error' => '統計データの取得中にエラーが発生しました'
            ];
        }
    }

    /**
     * 緊急回収アラート取得
     */
    public function getUrgentCollectionAlerts() {
        try {
            $sql = "
                SELECT 
                    i.id as invoice_id,
                    i.invoice_number,
                    i.total_amount,
                    i.due_date,
                    c.company_name,
                    c.contact_phone,
                    c.contact_email,
                    DATEDIFF(CURDATE(), i.due_date) as overdue_days,
                    CASE 
                        WHEN i.total_amount >= 100000 AND DATEDIFF(CURDATE(), i.due_date) >= 30 THEN 'critical'
                        WHEN i.total_amount >= 50000 OR DATEDIFF(CURDATE(), i.due_date) >= 14 THEN 'high'
                        WHEN DATEDIFF(CURDATE(), i.due_date) > 0 THEN 'medium'
                        ELSE 'low'
                    END as alert_level
                FROM invoices i
                LEFT JOIN companies c ON i.company_id = c.id
                WHERE i.status != 'paid' 
                    AND i.is_active = 1
                    AND (
                        i.due_date < CURDATE() OR 
                        DATEDIFF(i.due_date, CURDATE()) <= 3
                    )
                ORDER BY 
                    CASE alert_level
                        WHEN 'critical' THEN 1
                        WHEN 'high' THEN 2
                        WHEN 'medium' THEN 3
                        ELSE 4
                    END,
                    i.total_amount DESC
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'data' => [
                    'alerts' => $alerts,
                    'urgent_count' => count(array_filter($alerts, function($alert) {
                        return in_array($alert['alert_level'], ['critical', 'high']);
                    })),
                    'total_overdue_amount' => array_sum(array_column($alerts, 'total_amount'))
                ]
            ];

        } catch (Exception $e) {
            error_log("緊急回収アラート取得エラー: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'アラートデータの取得中にエラーが発生しました'
            ];
        }
    }

    /**
     * 支払い方法の選択肢を取得（PayPay対応）
     */
    public static function getPaymentMethods() {
        return [
            'cash' => '💵 現金',
            'bank_transfer' => '🏦 銀行振込',
            'paypay' => '📱 PayPay',
            'account_debit' => '🏦 口座引き落とし',
            'mixed' => '💳 混合',
            'other' => '💼 その他'
        ];
    }

    /**
     * 部分入金記録
     */
    public function recordPartialPayment($invoiceId, $amount, $paymentData) {
        try {
            $this->db->beginTransaction();

            // 請求書情報を取得
            $invoice = $this->getInvoiceDetails($invoiceId);
            if (!$invoice) {
                throw new Exception('請求書が見つかりません');
            }

            // 未払い金額を確認
            $outstandingAmount = $this->calculateOutstandingAmount($invoiceId);
            
            if ($amount > $outstandingAmount) {
                throw new Exception('入金金額が未払い金額を超えています');
            }

            // 部分入金として記録
            $paymentId = $this->insertPaymentRecord([
                'invoice_id' => $invoiceId,
                'payment_amount' => $amount,
                'payment_date' => $paymentData['payment_date'] ?? date('Y-m-d'),
                'payment_method' => $paymentData['payment_method'] ?? 'cash',
                'reference_number' => $paymentData['reference_number'] ?? '',
                'notes' => $paymentData['notes'] ?? '部分入金',
                'payment_status' => 'completed'
            ]);

            // 残額がゼロになった場合は請求書ステータスを「paid」に更新
            $remainingAmount = $outstandingAmount - $amount;
            if ($remainingAmount <= 0) {
                $this->updateInvoiceStatus($invoiceId, 'paid');
            } else {
                $this->updateInvoiceStatus($invoiceId, 'partial_paid');
            }

            $this->logPaymentActivity($invoiceId, $paymentId, 'partial_payment_recorded', [
                'amount' => $amount,
                'remaining' => $remainingAmount
            ]);

            $this->db->commit();

            return [
                'success' => true,
                'message' => '部分入金を記録しました',
                'data' => [
                    'payment_id' => $paymentId,
                    'invoice_id' => $invoiceId,
                    'paid_amount' => $amount,
                    'remaining_amount' => $remainingAmount,
                    'is_fully_paid' => ($remainingAmount <= 0)
                ]
            ];

        } catch (Exception $e) {
            $this->db->rollback();
            error_log("部分入金記録エラー: " . $e->getMessage());
            return [
                'success' => false,
                'error' => '部分入金記録中にエラーが発生しました: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 請求書の支払い詳細取得
     */
    public function getInvoicePaymentDetails($invoiceId) {
        try {
            $sql = "
                SELECT 
                    i.*,
                    c.company_name,
                    c.contact_phone,
                    c.contact_email,
                    COALESCE(SUM(p.payment_amount), 0) as paid_amount,
                    (i.total_amount - COALESCE(SUM(p.payment_amount), 0)) as outstanding_amount
                FROM invoices i
                LEFT JOIN companies c ON i.company_id = c.id
                LEFT JOIN payments p ON i.id = p.invoice_id AND p.payment_status = 'completed'
                WHERE i.id = ? AND i.is_active = 1
                GROUP BY i.id
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$invoiceId]);
            $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$invoice) {
                throw new Exception('請求書が見つかりません');
            }

            // 支払い履歴を取得
            $paymentHistory = $this->getPaymentHistory($invoiceId);

            return [
                'success' => true,
                'data' => [
                    'invoice' => $invoice,
                    'payment_history' => $paymentHistory
                ]
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => '請求書詳細の取得中にエラーが発生しました: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 支払い履歴取得
     */
    public function getPaymentHistory($invoiceId) {
        $sql = "
            SELECT 
                p.*,
                DATE_FORMAT(p.payment_date, '%Y/%m/%d') as formatted_date,
                DATE_FORMAT(p.created_at, '%Y/%m/%d %H:%i') as recorded_at
            FROM payments p
            WHERE p.invoice_id = ?
            ORDER BY p.payment_date DESC, p.created_at DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$invoiceId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 支払いステータス更新
     */
    public function updatePaymentStatus($paymentId, $status, $notes = '') {
        try {
            $this->db->beginTransaction();

            $sql = "UPDATE payments SET payment_status = ?, notes = ?, updated_at = NOW() WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([$status, $notes, $paymentId]);

            if (!$result) {
                throw new Exception('支払いステータスの更新に失敗しました');
            }

            $this->db->commit();

            return [
                'success' => true,
                'message' => '支払いステータスを更新しました'
            ];

        } catch (Exception $e) {
            $this->db->rollback();
            return [
                'success' => false,
                'error' => 'ステータス更新中にエラーが発生しました: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 支払い期限変更
     */
    public function rescheduleDueDate($invoiceId, $newDueDate, $reason = '') {
        try {
            $this->db->beginTransaction();

            $sql = "UPDATE invoices SET due_date = ?, updated_at = NOW() WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([$newDueDate, $invoiceId]);

            if (!$result) {
                throw new Exception('支払期限の更新に失敗しました');
            }

            // 変更ログを記録
            $this->logPaymentActivity($invoiceId, null, 'due_date_rescheduled', [
                'new_due_date' => $newDueDate,
                'reason' => $reason
            ]);

            $this->db->commit();

            return [
                'success' => true,
                'message' => '支払期限を変更しました'
            ];

        } catch (Exception $e) {
            $this->db->rollback();
            return [
                'success' => false,
                'error' => '期限変更中にエラーが発生しました: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 入金記録キャンセル
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
                throw new Exception('支払い記録が見つかりません');
            }

            // 支払い記録を削除（論理削除）
            $sql = "UPDATE payments SET payment_status = 'cancelled', notes = ?, updated_at = NOW() WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$reason, $paymentId]);

            // 請求書ステータスを再計算
            $this->recalculateInvoiceStatus($payment['invoice_id']);

            // キャンセルログを記録
            $this->logPaymentActivity($payment['invoice_id'], $paymentId, 'payment_cancelled', [
                'reason' => $reason,
                'cancelled_amount' => $payment['payment_amount']
            ]);

            $this->db->commit();

            return [
                'success' => true,
                'message' => '入金記録をキャンセルしました'
            ];

        } catch (Exception $e) {
            $this->db->rollback();
            return [
                'success' => false,
                'error' => 'キャンセル処理中にエラーが発生しました: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 月次サマリー取得
     */
    public function getMonthlySummary($year, $month) {
        try {
            $sql = "
                SELECT 
                    COUNT(DISTINCT i.id) as total_invoices,
                    SUM(i.total_amount) as total_billed,
                    COUNT(DISTINCT CASE WHEN i.status = 'paid' THEN i.id END) as paid_invoices,
                    SUM(CASE WHEN i.status = 'paid' THEN i.total_amount END) as total_collected,
                    COUNT(DISTINCT CASE WHEN i.status != 'paid' THEN i.id END) as unpaid_invoices,
                    SUM(CASE WHEN i.status != 'paid' THEN i.total_amount END) as total_outstanding,
                    AVG(DATEDIFF(p.payment_date, i.due_date)) as avg_payment_delay
                FROM invoices i
                LEFT JOIN payments p ON i.id = p.invoice_id AND p.payment_status = 'completed'
                WHERE YEAR(i.created_at) = ? AND MONTH(i.created_at) = ?
                    AND i.is_active = 1
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$year, $month]);
            $summary = $stmt->fetch(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'data' => [
                    'year' => $year,
                    'month' => $month,
                    'summary' => $summary
                ]
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => '月次サマリー取得中にエラーが発生しました: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 請求書ステータス再計算
     */
    private function recalculateInvoiceStatus($invoiceId) {
        $outstandingAmount = $this->calculateOutstandingAmount($invoiceId);
        
        if ($outstandingAmount <= 0) {
            $status = 'paid';
        } elseif ($outstandingAmount > 0) {
            // 支払い記録があるかチェック
            $sql = "SELECT COUNT(*) as payment_count FROM payments WHERE invoice_id = ? AND payment_status = 'completed'";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$invoiceId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $status = $result['payment_count'] > 0 ? 'partial_paid' : 'issued';
        }

        $this->updateInvoiceStatus($invoiceId, $status);
    }

    private function calculatePaymentListStats($invoices) {
        $stats = [
            'total_companies' => 0,
            'total_outstanding' => 0,
            'overdue_count' => 0,
            'overdue_amount' => 0,
            'due_soon_count' => 0,
            'due_soon_amount' => 0,
            'paid_count' => 0,
            'paid_amount' => 0
        ];

        $companies = [];
        
        foreach ($invoices as $invoice) {
            $companies[$invoice['invoice_id']] = true;
            
            switch ($invoice['payment_status']) {
                case 'overdue':
                    $stats['overdue_count']++;
                    $stats['overdue_amount'] += $invoice['outstanding_amount'];
                    $stats['total_outstanding'] += $invoice['outstanding_amount'];
                    break;
                case 'due_soon':
                    $stats['due_soon_count']++;
                    $stats['due_soon_amount'] += $invoice['outstanding_amount'];
                    $stats['total_outstanding'] += $invoice['outstanding_amount'];
                    break;
                case 'paid':
                    $stats['paid_count']++;
                    $stats['paid_amount'] += $invoice['total_amount'];
                    break;
                default:
                    $stats['total_outstanding'] += $invoice['outstanding_amount'];
            }
        }

        $stats['total_companies'] = count($companies);
        $stats['collection_rate'] = $stats['paid_amount'] > 0 ? 
            round(($stats['paid_amount'] / ($stats['paid_amount'] + $stats['total_outstanding'])) * 100, 1) : 0;

        return $stats;
    }

    private function getInvoiceDetails($invoiceId) {
        $sql = "
            SELECT i.*, c.company_name 
            FROM invoices i 
            LEFT JOIN companies c ON i.company_id = c.id 
            WHERE i.id = ? AND i.is_active = 1
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$invoiceId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function calculateOutstandingAmount($invoiceId) {
        $sql = "
            SELECT 
                i.total_amount - COALESCE(SUM(p.payment_amount), 0) as outstanding
            FROM invoices i
            LEFT JOIN payments p ON i.id = p.invoice_id AND p.payment_status = 'completed'
            WHERE i.id = ?
            GROUP BY i.id
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$invoiceId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['outstanding'] : 0;
    }

    private function insertPaymentRecord($paymentData) {
        $sql = "
            INSERT INTO payments (
                invoice_id, payment_amount, payment_date, payment_method,
                reference_number, notes, payment_status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $paymentData['invoice_id'],
            $paymentData['payment_amount'],
            $paymentData['payment_date'],
            $paymentData['payment_method'],
            $paymentData['reference_number'],
            $paymentData['notes'],
            $paymentData['payment_status']
        ]);
        
        return $this->db->lastInsertId();
    }

    private function updateInvoiceStatus($invoiceId, $status) {
        $sql = "UPDATE invoices SET status = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$status, $invoiceId]);
    }

    private function logPaymentActivity($invoiceId, $paymentId, $action, $data = []) {
        // ログ記録（将来の監査用）
        error_log("Payment Activity: {$action} - Invoice: {$invoiceId}, Payment: {$paymentId}, Data: " . json_encode($data));
    }

    private function getDateCondition($period) {
        switch ($period) {
            case 'current_month':
                return "AND DATE_FORMAT(i.created_at, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')";
            case 'last_month':
                return "AND DATE_FORMAT(i.created_at, '%Y-%m') = DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 1 MONTH), '%Y-%m')";
            case 'current_year':
                return "AND YEAR(i.created_at) = YEAR(NOW())";
            default:
                return "";
        }
    }

    private function getPaymentMethodStatistics($period) {
        $dateCondition = $this->getDateCondition($period);
        
        $sql = "
            SELECT 
                p.payment_method,
                COUNT(*) as count,
                SUM(p.payment_amount) as total_amount
            FROM payments p
            LEFT JOIN invoices i ON p.invoice_id = i.id
            WHERE p.payment_status = 'completed' {$dateCondition}
            GROUP BY p.payment_method
            ORDER BY total_amount DESC
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
