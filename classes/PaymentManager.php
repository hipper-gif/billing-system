<?php
/**
 * PaymentManager.php - 支払い管理システム
 * 支払い記録・未回収金額管理・統計情報・アラート機能を提供
 * 
 * @author Smiley配食事業システム
 * @version 2.0 - Fatal Error解決・根本対応版
 * @date 2025-09-04
 */

require_once __DIR__ . '/Database.php';

class PaymentManager 
{
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * 支払い方法の選択肢配列を取得
     * @return array 支払い方法配列
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
     * 支払い一覧を取得（フィルター・ページネーション対応）
     * @param array $filters フィルター条件
     * @param int $page ページ番号
     * @param int $limit 1ページあたりの件数
     * @return array 支払い一覧データ
     */
    public function getPaymentsList($filters = [], $page = 1, $limit = 50) {
        try {
            // WHERE条件とパラメータの構築
            $whereConditions = ['1=1'];
            $params = [];
            
            // フィルター条件の構築
            if (!empty($filters['company_id'])) {
                $whereConditions[] = 'c.id = :company_id';
                $params[':company_id'] = $filters['company_id'];
            }
            
            if (!empty($filters['payment_method'])) {
                $whereConditions[] = 'p.payment_method = :payment_method';
                $params[':payment_method'] = $filters['payment_method'];
            }
            
            if (!empty($filters['date_from'])) {
                $whereConditions[] = 'p.payment_date >= :date_from';
                $params[':date_from'] = $filters['date_from'];
            }
            
            if (!empty($filters['date_to'])) {
                $whereConditions[] = 'p.payment_date <= :date_to';
                $params[':date_to'] = $filters['date_to'];
            }
            
            if (!empty($filters['invoice_id'])) {
                $whereConditions[] = 'p.invoice_id = :invoice_id';
                $params[':invoice_id'] = $filters['invoice_id'];
            }
            
            // OFFSET計算
            $offset = ($page - 1) * $limit;
            
            // メインクエリ
            $sql = "SELECT 
                        p.id,
                        p.invoice_id,
                        p.payment_date,
                        p.amount,
                        p.payment_method,
                        p.payment_status,
                        p.reference_number,
                        p.notes,
                        p.created_at,
                        i.invoice_number,
                        i.total_amount as invoice_amount,
                        i.status as invoice_status,
                        c.company_name,
                        c.company_code,
                        u.user_name,
                        u.user_code
                    FROM payments p
                    LEFT JOIN invoices i ON p.invoice_id = i.id
                    LEFT JOIN users u ON i.user_id = u.id 
                    LEFT JOIN companies c ON u.company_id = c.id
                    WHERE " . implode(' AND ', $whereConditions) . "
                    ORDER BY p.payment_date DESC, p.created_at DESC
                    LIMIT :limit OFFSET :offset";
            
            $stmt = $this->db->prepare($sql);
            
            // パラメータバインド
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
            
            $stmt->execute();
            $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // 総件数取得
            $countSql = "SELECT COUNT(*) as total
                        FROM payments p
                        LEFT JOIN invoices i ON p.invoice_id = i.id
                        LEFT JOIN users u ON i.user_id = u.id 
                        LEFT JOIN companies c ON u.company_id = c.id
                        WHERE " . implode(' AND ', $whereConditions);
            
            $countStmt = $this->db->prepare($countSql);
            foreach ($params as $key => $value) {
                $countStmt->bindValue($key, $value);
            }
            $countStmt->execute();
            $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // 支払い方法の日本語変換
            $paymentMethods = self::getPaymentMethods();
            foreach ($payments as &$payment) {
                $payment['payment_method_label'] = $paymentMethods[$payment['payment_method']] ?? $payment['payment_method'];
                $payment['amount_formatted'] = number_format($payment['amount']);
            }
            
            return [
                'payments' => $payments,
                'pagination' => [
                    'current_page' => $page,
                    'total_count' => (int)$totalCount,
                    'total_pages' => ceil($totalCount / $limit),
                    'limit' => $limit,
                    'has_next' => $page < ceil($totalCount / $limit),
                    'has_prev' => $page > 1
                ]
            ];
            
        } catch (Exception $e) {
            error_log("PaymentManager::getPaymentsList Error: " . $e->getMessage());
            return [
                'payments' => [],
                'pagination' => [
                    'current_page' => 1,
                    'total_count' => 0,
                    'total_pages' => 0,
                    'limit' => $limit,
                    'has_next' => false,
                    'has_prev' => false
                ]
            ];
        }
    }
    
    /**
     * 未回収金額を取得（フィルター対応）
     * @param array $filters フィルター条件
     * @return array 未回収金額データ
     */
    public function getOutstandingAmounts($filters = []) {
        try {
            $whereConditions = ['i.status IN ("issued", "overdue", "partial_paid")'];
            $params = [];
            
            // フィルター条件の構築
            if (!empty($filters['company_id'])) {
                $whereConditions[] = 'c.id = :company_id';
                $params[':company_id'] = $filters['company_id'];
            }
            
            if (!empty($filters['overdue_only'])) {
                $whereConditions[] = 'i.due_date < CURDATE()';
            }
            
            $sql = "SELECT 
                        c.id as company_id,
                        c.company_name,
                        c.company_code,
                        COUNT(DISTINCT i.id) as outstanding_invoices,
                        SUM(i.total_amount) as total_invoiced,
                        COALESCE(SUM(paid.amount), 0) as total_paid,
                        (SUM(i.total_amount) - COALESCE(SUM(paid.amount), 0)) as outstanding_amount,
                        COUNT(CASE WHEN i.due_date < CURDATE() THEN 1 END) as overdue_count,
                        AVG(DATEDIFF(CURDATE(), i.due_date)) as avg_days_overdue
                    FROM invoices i
                    JOIN users u ON i.user_id = u.id
                    JOIN companies c ON u.company_id = c.id
                    LEFT JOIN (
                        SELECT invoice_id, SUM(amount) as amount
                        FROM payments 
                        WHERE payment_status = 'completed'
                        GROUP BY invoice_id
                    ) paid ON i.id = paid.invoice_id
                    WHERE " . implode(' AND ', $whereConditions) . "
                    GROUP BY c.id, c.company_name, c.company_code
                    HAVING outstanding_amount > 0
                    ORDER BY outstanding_amount DESC";
            
            $stmt = $this->db->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();
            
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // 金額フォーマット処理
            foreach ($results as &$result) {
                $result['total_invoiced_formatted'] = number_format($result['total_invoiced']);
                $result['total_paid_formatted'] = number_format($result['total_paid']);
                $result['outstanding_amount_formatted'] = number_format($result['outstanding_amount']);
                $result['avg_days_overdue'] = round($result['avg_days_overdue'], 1);
            }
            
            return $results;
            
        } catch (Exception $e) {
            error_log("PaymentManager::getOutstandingAmounts Error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * 支払い統計情報を取得
     * @param string $period 期間('current_month', 'last_month', 'current_year')
     * @return array 統計データ
     */
    public function getPaymentStatistics($period = 'current_month') {
        try {
            $dateCondition = '';
            switch ($period) {
                case 'current_month':
                    $dateCondition = "DATE_FORMAT(p.payment_date, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')";
                    break;
                case 'last_month':
                    $dateCondition = "DATE_FORMAT(p.payment_date, '%Y-%m') = DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), '%Y-%m')";
                    break;
                case 'current_year':
                    $dateCondition = "YEAR(p.payment_date) = YEAR(CURDATE())";
                    break;
                default:
                    $dateCondition = "1=1";
            }
            
            // 基本統計
            $basicStatsSql = "SELECT 
                                COUNT(*) as total_payments,
                                SUM(p.amount) as total_amount,
                                AVG(p.amount) as average_amount,
                                MIN(p.amount) as min_amount,
                                MAX(p.amount) as max_amount,
                                COUNT(DISTINCT c.id) as companies_count
                              FROM payments p
                              LEFT JOIN invoices i ON p.invoice_id = i.id
                              LEFT JOIN users u ON i.user_id = u.id
                              LEFT JOIN companies c ON u.company_id = c.id
                              WHERE p.payment_status = 'completed' AND {$dateCondition}";
            
            $stmt = $this->db->prepare($basicStatsSql);
            $stmt->execute();
            $basicStats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // 支払い方法別統計
            $methodStatsSql = "SELECT 
                                  p.payment_method,
                                  COUNT(*) as count,
                                  SUM(p.amount) as total_amount,
                                  ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM payments WHERE payment_status = 'completed' AND {$dateCondition}), 1) as percentage
                               FROM payments p
                               WHERE p.payment_status = 'completed' AND {$dateCondition}
                               GROUP BY p.payment_method
                               ORDER BY total_amount DESC";
            
            $stmt = $this->db->prepare($methodStatsSql);
            $stmt->execute();
            $methodStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // 支払い方法ラベル追加
            $paymentMethods = self::getPaymentMethods();
            foreach ($methodStats as &$stat) {
                $stat['method_label'] = $paymentMethods[$stat['payment_method']] ?? $stat['payment_method'];
                $stat['total_amount_formatted'] = number_format($stat['total_amount']);
            }
            
            // 日別統計（当月のみ）
            $dailyStatsSql = "SELECT 
                                DATE(p.payment_date) as payment_date,
                                COUNT(*) as count,
                                SUM(p.amount) as amount
                              FROM payments p
                              WHERE p.payment_status = 'completed' 
                                AND DATE_FORMAT(p.payment_date, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')
                              GROUP BY DATE(p.payment_date)
                              ORDER BY payment_date";
            
            $stmt = $this->db->prepare($dailyStatsSql);
            $stmt->execute();
            $dailyStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'basic' => array_merge($basicStats, [
                    'total_amount_formatted' => number_format($basicStats['total_amount'] ?? 0),
                    'average_amount_formatted' => number_format($basicStats['average_amount'] ?? 0),
                    'min_amount_formatted' => number_format($basicStats['min_amount'] ?? 0),
                    'max_amount_formatted' => number_format($basicStats['max_amount'] ?? 0)
                ]),
                'by_method' => $methodStats,
                'daily' => $dailyStats,
                'period' => $period
            ];
            
        } catch (Exception $e) {
            error_log("PaymentManager::getPaymentStatistics Error: " . $e->getMessage());
            return [
                'basic' => ['total_payments' => 0, 'total_amount' => 0],
                'by_method' => [],
                'daily' => [],
                'period' => $period
            ];
        }
    }
    
    /**
     * 支払いアラート情報を取得
     * @return array アラート情報
     */
    public function getPaymentAlerts() {
        try {
            $alerts = [];
            
            // 期限超過請求書アラート
            $overdueSql = "SELECT 
                              COUNT(*) as count,
                              SUM(i.total_amount - COALESCE(paid.amount, 0)) as amount
                           FROM invoices i
                           LEFT JOIN (
                               SELECT invoice_id, SUM(amount) as amount
                               FROM payments WHERE payment_status = 'completed'
                               GROUP BY invoice_id
                           ) paid ON i.id = paid.invoice_id
                           WHERE i.status IN ('issued', 'overdue') 
                             AND i.due_date < CURDATE()
                             AND (i.total_amount - COALESCE(paid.amount, 0)) > 0";
            
            $stmt = $this->db->prepare($overdueSql);
            $stmt->execute();
            $overdueData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($overdueData['count'] > 0) {
                $alerts[] = [
                    'type' => 'danger',
                    'title' => '期限超過',
                    'message' => "期限超過の請求書が{$overdueData['count']}件あります（未回収額：" . number_format($overdueData['amount']) . "円）",
                    'count' => $overdueData['count'],
                    'amount' => $overdueData['amount']
                ];
            }
            
            // 期限間近アラート（7日以内）
            $dueSoonSql = "SELECT 
                              COUNT(*) as count,
                              SUM(i.total_amount - COALESCE(paid.amount, 0)) as amount
                           FROM invoices i
                           LEFT JOIN (
                               SELECT invoice_id, SUM(amount) as amount
                               FROM payments WHERE payment_status = 'completed'
                               GROUP BY invoice_id
                           ) paid ON i.id = paid.invoice_id
                           WHERE i.status IN ('issued') 
                             AND i.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                             AND (i.total_amount - COALESCE(paid.amount, 0)) > 0";
            
            $stmt = $this->db->prepare($dueSoonSql);
            $stmt->execute();
            $dueSoonData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($dueSoonData['count'] > 0) {
                $alerts[] = [
                    'type' => 'warning',
                    'title' => '期限間近',
                    'message' => "7日以内に期限を迎える請求書が{$dueSoonData['count']}件あります（金額：" . number_format($dueSoonData['amount']) . "円）",
                    'count' => $dueSoonData['count'],
                    'amount' => $dueSoonData['amount']
                ];
            }
            
            // 高額未回収アラート（10万円以上）
            $highAmountSql = "SELECT 
                                 c.company_name,
                                 SUM(i.total_amount - COALESCE(paid.amount, 0)) as amount
                              FROM invoices i
                              JOIN users u ON i.user_id = u.id
                              JOIN companies c ON u.company_id = c.id
                              LEFT JOIN (
                                  SELECT invoice_id, SUM(amount) as amount
                                  FROM payments WHERE payment_status = 'completed'
                                  GROUP BY invoice_id
                              ) paid ON i.id = paid.invoice_id
                              WHERE i.status IN ('issued', 'overdue')
                                AND (i.total_amount - COALESCE(paid.amount, 0)) > 0
                              GROUP BY c.id, c.company_name
                              HAVING amount >= 100000
                              ORDER BY amount DESC";
            
            $stmt = $this->db->prepare($highAmountSql);
            $stmt->execute();
            $highAmountData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($highAmountData as $data) {
                $alerts[] = [
                    'type' => 'info',
                    'title' => '高額未回収',
                    'message' => "{$data['company_name']}の未回収額が" . number_format($data['amount']) . "円です",
                    'company' => $data['company_name'],
                    'amount' => $data['amount']
                ];
            }
            
            return $alerts;
            
        } catch (Exception $e) {
            error_log("PaymentManager::getPaymentAlerts Error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * 支払いを記録
     * @param int $invoiceId 請求書ID
     * @param array $paymentData 支払いデータ
     * @return array 処理結果
     */
    public function recordPayment($invoiceId, $paymentData) {
        try {
            $this->db->beginTransaction();
            
            // 請求書の存在確認
            $invoiceCheckSql = "SELECT id, total_amount, status FROM invoices WHERE id = :invoice_id";
            $stmt = $this->db->prepare($invoiceCheckSql);
            $stmt->bindValue(':invoice_id', $invoiceId, PDO::PARAM_INT);
            $stmt->execute();
            $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$invoice) {
                throw new Exception("請求書が見つかりません (ID: {$invoiceId})");
            }
            
            // 支払い記録の挿入
            $insertSql = "INSERT INTO payments (
                            invoice_id, payment_date, amount, payment_method,
                            payment_status, reference_number, notes, created_by
                          ) VALUES (
                            :invoice_id, :payment_date, :amount, :payment_method,
                            :payment_status, :reference_number, :notes, :created_by
                          )";
            
            $stmt = $this->db->prepare($insertSql);
            $stmt->bindValue(':invoice_id', $invoiceId, PDO::PARAM_INT);
            $stmt->bindValue(':payment_date', $paymentData['payment_date']);
            $stmt->bindValue(':amount', $paymentData['amount'], PDO::PARAM_STR);
            $stmt->bindValue(':payment_method', $paymentData['payment_method']);
            $stmt->bindValue(':payment_status', $paymentData['payment_status'] ?? 'completed');
            $stmt->bindValue(':reference_number', $paymentData['reference_number'] ?? '');
            $stmt->bindValue(':notes', $paymentData['notes'] ?? '');
            $stmt->bindValue(':created_by', $paymentData['created_by'] ?? 'system');
            
            $stmt->execute();
            $paymentId = $this->db->lastInsertId();
            
            // 請求書ステータス更新の判定
            $totalPaidSql = "SELECT COALESCE(SUM(amount), 0) as total_paid 
                            FROM payments 
                            WHERE invoice_id = :invoice_id AND payment_status = 'completed'";
            $stmt = $this->db->prepare($totalPaidSql);
            $stmt->bindValue(':invoice_id', $invoiceId, PDO::PARAM_INT);
            $stmt->execute();
            $totalPaid = $stmt->fetch(PDO::FETCH_ASSOC)['total_paid'];
            
            // ステータス判定と更新
            $newStatus = 'issued';
            if ($totalPaid >= $invoice['total_amount']) {
                $newStatus = 'paid';
            } elseif ($totalPaid > 0) {
                $newStatus = 'partial_paid';
            }
            
            $updateInvoiceSql = "UPDATE invoices SET status = :status, updated_at = CURRENT_TIMESTAMP WHERE id = :invoice_id";
            $stmt = $this->db->prepare($updateInvoiceSql);
            $stmt->bindValue(':status', $newStatus);
            $stmt->bindValue(':invoice_id', $invoiceId, PDO::PARAM_INT);
            $stmt->execute();
            
            $this->db->commit();
            
            return [
                'success' => true,
                'payment_id' => $paymentId,
                'invoice_id' => $invoiceId,
                'amount' => $paymentData['amount'],
                'new_status' => $newStatus,
                'total_paid' => $totalPaid,
                'remaining_amount' => max(0, $invoice['total_amount'] - $totalPaid),
                'message' => '支払いを正常に記録しました'
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("PaymentManager::recordPayment Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'エラーが発生しました: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 支払いをキャンセル
     * @param int $paymentId 支払いID
     * @param string $reason キャンセル理由
     * @return array 処理結果
     */
    public function cancelPayment($paymentId, $reason) {
        try {
            $this->db->beginTransaction();
            
            // 支払い情報の取得
            $paymentSql = "SELECT id, invoice_id, amount, payment_status FROM payments WHERE id = :payment_id";
            $stmt = $this->db->prepare($paymentSql);
            $stmt->bindValue(':payment_id', $paymentId, PDO::PARAM_INT);
            $stmt->execute();
            $payment = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$payment) {
                throw new Exception("支払い記録が見つかりません (ID: {$paymentId})");
            }
            
            if ($payment['payment_status'] === 'cancelled') {
                throw new Exception("この支払いは既にキャンセル済みです");
            }
            
            // 支払いステータス更新
            $updateSql = "UPDATE payments 
                         SET payment_status = 'cancelled', 
                             notes = CONCAT(COALESCE(notes, ''), '\n[キャンセル理由: {$reason}]'),
                             updated_at = CURRENT_TIMESTAMP 
                         WHERE id = :payment_id";
            
            $stmt = $this->db->prepare($updateSql);
            $stmt->bindValue(':payment_id', $paymentId, PDO::PARAM_INT);
            $stmt->execute();
            
            // 請求書ステータスの再計算
            $totalPaidSql = "SELECT 
                                i.total_amount,
                                COALESCE(SUM(p.amount), 0) as total_paid
                             FROM invoices i
                             LEFT JOIN payments p ON i.id = p.invoice_id AND p.payment_status = 'completed'
                             WHERE i.id = :invoice_id
                             GROUP BY i.id, i.total_amount";
            
            $stmt = $this->db->prepare($totalPaidSql);
            $stmt->bindValue(':invoice_id', $payment['invoice_id'], PDO::PARAM_INT);
            $stmt->execute();
            $invoiceData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // 新しいステータスの判定
            $newStatus = 'issued';
            if ($invoiceData['total_paid'] >= $invoiceData['total_amount']) {
                $newStatus = 'paid';
            } elseif ($invoiceData['total_paid'] > 0) {
                $newStatus = 'partial_paid';
            }
            
            // 請求書ステータス更新
            $updateInvoiceSql = "UPDATE invoices SET status = :status WHERE id = :invoice_id";
            $stmt = $this->db->prepare($updateInvoiceSql);
            $stmt->bindValue(':status', $newStatus);
            $stmt->bindValue(':invoice_id', $payment['invoice_id'], PDO::PARAM_INT);
            $stmt->execute();
            
            $this->db->commit();
            
            return [
                'success' => true,
                'payment_id' => $paymentId,
                'cancelled_amount' => $payment['amount'],
                'new_invoice_status' => $newStatus,
                'message' => '支払いを正常にキャンセルしました'
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("PaymentManager::cancelPayment Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'エラーが発生しました: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * PayPay支払い用の特別処理
     * @param array $paymentData 支払いデータ
     * @return array 処理結果
     */
    public function processPayPayPayment($paymentData) {
        // PayPay固有の処理（将来的にQRコード生成等）
        // 現在は通常の支払い記録として処理
        $paymentData['payment_method'] = 'paypay';
        return $this->recordPayment($paymentData['invoice_id'], $paymentData);
    }
    
    /**
     * 企業別支払い履歴統計
     * @param int $companyId 企業ID
     * @param string $period 期間
     * @return array 統計データ
     */
    public function getCompanyPaymentHistory($companyId, $period = 'last_6_months') {
        try {
            $dateCondition = '';
            switch ($period) {
                case 'last_6_months':
                    $dateCondition = "p.payment_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)";
                    break;
                case 'current_year':
                    $dateCondition = "YEAR(p.payment_date) = YEAR(CURDATE())";
                    break;
                case 'last_year':
                    $dateCondition = "YEAR(p.payment_date) = YEAR(CURDATE()) - 1";
                    break;
                default:
                    $dateCondition = "1=1";
            }
            
            $sql = "SELECT 
                        DATE_FORMAT(p.payment_date, '%Y-%m') as month,
                        COUNT(*) as payment_count,
                        SUM(p.amount) as total_amount,
                        AVG(p.amount) as average_amount,
                        COUNT(DISTINCT p.payment_method) as method_variety
                    FROM payments p
                    JOIN invoices i ON p.invoice_id = i.id
                    JOIN users u ON i.user_id = u.id
                    WHERE u.company_id = :company_id
                      AND p.payment_status = 'completed'
                      AND {$dateCondition}
                    GROUP BY DATE_FORMAT(p.payment_date, '%Y-%m')
                    ORDER BY month DESC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':company_id', $companyId, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("PaymentManager::getCompanyPaymentHistory Error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * 一括支払い記録（複数請求書同時処理）
     * @param array $payments 支払いデータ配列
     * @return array 処理結果
     */
    public function recordBulkPayments($payments) {
        try {
            $this->db->beginTransaction();
            
            $results = [];
            $successCount = 0;
            $errorCount = 0;
            
            foreach ($payments as $paymentData) {
                try {
                    $result = $this->recordPayment($paymentData['invoice_id'], $paymentData);
                    if ($result['success']) {
                        $successCount++;
                        $results[] = $result;
                    } else {
                        $errorCount++;
                        $results[] = $result;
                    }
                } catch (Exception $e) {
                    $errorCount++;
                    $results[] = [
                        'success' => false,
                        'invoice_id' => $paymentData['invoice_id'] ?? 'unknown',
                        'message' => $e->getMessage()
                    ];
                }
            }
            
            if ($errorCount === 0) {
                $this->db->commit();
            } else {
                $this->db->rollback();
            }
            
            return [
                'success' => $errorCount === 0,
                'processed' => $successCount,
                'errors' => $errorCount,
                'details' => $results,
                'message' => "{$successCount}件の支払いを処理しました（エラー: {$errorCount}件）"
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("PaymentManager::recordBulkPayments Error: " . $e->getMessage());
            return [
                'success' => false,
                'processed' => 0,
                'errors' => count($payments),
                'message' => 'バッチ処理でエラーが発生しました: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 支払い詳細情報を取得
     * @param int $paymentId 支払いID
     * @return array|null 支払い詳細
     */
    public function getPaymentDetail($paymentId) {
        try {
            $sql = "SELECT 
                        p.*,
                        i.invoice_number,
                        i.total_amount as invoice_amount,
                        i.invoice_date,
                        i.due_date,
                        i.status as invoice_status,
                        c.company_name,
                        c.company_code,
                        u.user_name,
                        u.user_code
                    FROM payments p
                    JOIN invoices i ON p.invoice_id = i.id
                    JOIN users u ON i.user_id = u.id
                    JOIN companies c ON u.company_id = c.id
                    WHERE p.id = :payment_id";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':payment_id', $paymentId, PDO::PARAM_INT);
            $stmt->execute();
            
            $payment = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($payment) {
                // 支払い方法の日本語化
                $paymentMethods = self::getPaymentMethods();
                $payment['payment_method_label'] = $paymentMethods[$payment['payment_method']] ?? $payment['payment_method'];
                $payment['amount_formatted'] = number_format($payment['amount']);
                $payment['invoice_amount_formatted'] = number_format($payment['invoice_amount']);
            }
            
            return $payment;
            
        } catch (Exception $e) {
            error_log("PaymentManager::getPaymentDetail Error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * 支払い方法別集計データ取得
     * @param array $filters フィルター条件
     * @return array 集計データ
     */
    public function getPaymentMethodSummary($filters = []) {
        try {
            $whereConditions = ['p.payment_status = "completed"'];
            $params = [];
            
            if (!empty($filters['date_from'])) {
                $whereConditions[] = 'p.payment_date >= :date_from';
                $params[':date_from'] = $filters['date_from'];
            }
            
            if (!empty($filters['date_to'])) {
                $whereConditions[] = 'p.payment_date <= :date_to';
                $params[':date_to'] = $filters['date_to'];
            }
            
            $sql = "SELECT 
                        p.payment_method,
                        COUNT(*) as count,
                        SUM(p.amount) as total_amount,
                        AVG(p.amount) as average_amount,
                        MIN(p.amount) as min_amount,
                        MAX(p.amount) as max_amount
                    FROM payments p
                    WHERE " . implode(' AND ', $whereConditions) . "
                    GROUP BY p.payment_method
                    ORDER BY total_amount DESC";
            
            $stmt = $this->db->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();
            
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $paymentMethods = self::getPaymentMethods();
            
            foreach ($results as &$result) {
                $result['method_label'] = $paymentMethods[$result['payment_method']] ?? $result['payment_method'];
                $result['total_amount_formatted'] = number_format($result['total_amount']);
                $result['average_amount_formatted'] = number_format($result['average_amount']);
                $result['min_amount_formatted'] = number_format($result['min_amount']);
                $result['max_amount_formatted'] = number_format($result['max_amount']);
            }
            
            return $results;
            
        } catch (Exception $e) {
            error_log("PaymentManager::getPaymentMethodSummary Error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * 督促対象の請求書リストを取得
     * @param int $daysOverdue 期限超過日数（デフォルト: 7日）
     * @return array 督促対象リスト
     */
    public function getDunningTargets($daysOverdue = 7) {
        try {
            $sql = "SELECT 
                        i.id as invoice_id,
                        i.invoice_number,
                        i.total_amount,
                        i.due_date,
                        DATEDIFF(CURDATE(), i.due_date) as days_overdue,
                        (i.total_amount - COALESCE(paid.amount, 0)) as outstanding_amount,
                        c.company_name,
                        c.contact_person,
                        c.contact_email,
                        c.contact_phone,
                        u.user_name
                    FROM invoices i
                    JOIN users u ON i.user_id = u.id
                    JOIN companies c ON u.company_id = c.id
                    LEFT JOIN (
                        SELECT invoice_id, SUM(amount) as amount
                        FROM payments 
                        WHERE payment_status = 'completed'
                        GROUP BY invoice_id
                    ) paid ON i.id = paid.invoice_id
                    WHERE i.status IN ('issued', 'overdue')
                      AND DATEDIFF(CURDATE(), i.due_date) >= :days_overdue
                      AND (i.total_amount - COALESCE(paid.amount, 0)) > 0
                    ORDER BY days_overdue DESC, outstanding_amount DESC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':days_overdue', $daysOverdue, PDO::PARAM_INT);
            $stmt->execute();
            
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($results as &$result) {
                $result['total_amount_formatted'] = number_format($result['total_amount']);
                $result['outstanding_amount_formatted'] = number_format($result['outstanding_amount']);
                $result['urgency_level'] = $this->calculateUrgencyLevel($result['days_overdue'], $result['outstanding_amount']);
            }
            
            return $results;
            
        } catch (Exception $e) {
            error_log("PaymentManager::getDunningTargets Error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * 督促緊急度を計算
     * @param int $daysOverdue 期限超過日数
     * @param float $amount 未回収金額
     * @return string 緊急度レベル
     */
    private function calculateUrgencyLevel($daysOverdue, $amount) {
        if ($daysOverdue >= 30 || $amount >= 500000) {
            return 'critical';
        } elseif ($daysOverdue >= 14 || $amount >= 100000) {
            return 'high';
        } elseif ($daysOverdue >= 7 || $amount >= 50000) {
            return 'medium';
        } else {
            return 'low';
        }
    }
    
    /**
     * 月別支払い推移データを取得
     * @param int $months 取得月数（デフォルト: 12ヶ月）
     * @return array 月別推移データ
     */
    public function getMonthlyPaymentTrends($months = 12) {
        try {
            $sql = "SELECT 
                        DATE_FORMAT(p.payment_date, '%Y-%m') as month,
                        DATE_FORMAT(p.payment_date, '%Y年%m月') as month_label,
                        COUNT(*) as payment_count,
                        SUM(p.amount) as total_amount,
                        COUNT(DISTINCT c.id) as company_count,
                        AVG(p.amount) as average_payment
                    FROM payments p
                    JOIN invoices i ON p.invoice_id = i.id
                    JOIN users u ON i.user_id = u.id
                    JOIN companies c ON u.company_id = c.id
                    WHERE p.payment_status = 'completed'
                      AND p.payment_date >= DATE_SUB(CURDATE(), INTERVAL :months MONTH)
                    GROUP BY DATE_FORMAT(p.payment_date, '%Y-%m')
                    ORDER BY month ASC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':months', $months, PDO::PARAM_INT);
            $stmt->execute();
            
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($results as &$result) {
                $result['total_amount_formatted'] = number_format($result['total_amount']);
                $result['average_payment_formatted'] = number_format($result['average_payment']);
            }
            
            return $results;
            
        } catch (Exception $e) {
            error_log("PaymentManager::getMonthlyPaymentTrends Error: " . $e->getMessage());
            return [];
        }
    }
}
?>
