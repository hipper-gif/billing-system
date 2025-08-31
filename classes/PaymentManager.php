<?php

/**
 * PaymentManager - PayPay対応版
 * 支払い管理・入金記録・未回収金額管理・督促機能
 */

require_once __DIR__ . '/Database.php';

class PaymentManager {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * 支払い方法の選択肢配列（PayPay対応版）
     */
    public static function getPaymentMethods() {
        return [
            'cash' => '現金',
            'bank_transfer' => '銀行振込',
            'account_debit' => '口座引き落とし',
            'paypay' => 'PayPay',           // ✅ 新規追加
            'mixed' => '混合',
            'other' => 'その他'
        ];
    }
    
    /**
     * 支払い方法の日本語名を取得
     */
    public static function getPaymentMethodName($method) {
        $methods = self::getPaymentMethods();
        return $methods[$method] ?? $method;
    }
    
    /**
     * PayPay支払い用の特別な処理
     * @param array $paymentData 支払いデータ
     * @return array 処理結果
     */
    public function processPayPayPayment($paymentData) {
        try {
            // PayPay固有の処理
            $paymentData['payment_method'] = 'paypay';
            $paymentData['transaction_fee'] = 0; // PayPayは手数料なし想定
            $paymentData['notes'] = ($paymentData['notes'] ?? '') . ' [PayPay支払い]';
            
            // QRコード情報がある場合は記録
            if (isset($paymentData['qr_code_id'])) {
                $paymentData['notes'] .= ' QRコード: ' . $paymentData['qr_code_id'];
            }
            
            // 通常の支払い記録処理を実行
            return $this->recordPayment($paymentData);
            
        } catch (Exception $e) {
            throw new Exception('PayPay支払い処理エラー: ' . $e->getMessage());
        }
    }
    
    /**
     * 支払い記録
     * @param array $paymentData 支払いデータ
     * @return array 処理結果
     */
    public function recordPayment($paymentData) {
        try {
            $this->db->beginTransaction();
            
            // 必須フィールドの検証
            $requiredFields = ['invoice_id', 'amount', 'payment_date', 'payment_method'];
            foreach ($requiredFields as $field) {
                if (!isset($paymentData[$field]) || $paymentData[$field] === '') {
                    throw new Exception("必須フィールド {$field} が不足しています");
                }
            }
            
            // 請求書の存在確認
            $stmt = $this->db->prepare("SELECT id, total_amount, paid_amount FROM invoices WHERE id = ?");
            $stmt->execute([$paymentData['invoice_id']]);
            $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$invoice) {
                throw new Exception('指定された請求書が見つかりません');
            }
            
            // paymentsテーブルに支払い記録を挿入
            $insertPaymentSql = "INSERT INTO payments (
                invoice_id, 
                payment_date, 
                amount, 
                payment_method, 
                reference_number, 
                notes,
                transaction_fee,
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $stmt = $this->db->prepare($insertPaymentSql);
            $stmt->execute([
                $paymentData['invoice_id'],
                $paymentData['payment_date'],
                $paymentData['amount'],
                $paymentData['payment_method'],
                $paymentData['reference_number'] ?? null,
                $paymentData['notes'] ?? null,
                $paymentData['transaction_fee'] ?? 0
            ]);
            
            $paymentId = $this->db->lastInsertId();
            
            // 請求書の支払い状況を更新
            $newPaidAmount = $invoice['paid_amount'] + $paymentData['amount'];
            $paymentStatus = ($newPaidAmount >= $invoice['total_amount']) ? 'paid' : 'partial';
            
            $updateInvoiceSql = "UPDATE invoices SET paid_amount = ?, payment_status = ? WHERE id = ?";
            $stmt = $this->db->prepare($updateInvoiceSql);
            $stmt->execute([$newPaidAmount, $paymentStatus, $paymentData['invoice_id']]);
            
            $this->db->commit();
            
            return [
                'payment_id' => $paymentId,
                'message' => 'お支払いを記録しました',
                'payment_status' => $paymentStatus,
                'paid_amount' => $newPaidAmount,
                'remaining_amount' => max(0, $invoice['total_amount'] - $newPaidAmount)
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    /**
     * 未回収金額一覧を取得
     * @param array $filters フィルター条件
     * @return array 未回収金額データ
     */
    public function getOutstandingAmounts($filters = []) {
        $sql = "SELECT 
                    i.id,
                    i.invoice_number,
                    i.company_name,
                    i.department_name,
                    i.total_amount,
                    i.paid_amount,
                    (i.total_amount - i.paid_amount) as outstanding_amount,
                    i.issue_date,
                    i.due_date,
                    i.payment_method,
                    i.payment_status,
                    DATEDIFF(NOW(), i.due_date) as overdue_days,
                    CASE 
                        WHEN DATEDIFF(NOW(), i.due_date) > 30 THEN '危険'
                        WHEN DATEDIFF(NOW(), i.due_date) > 0 THEN '注意'
                        ELSE '正常'
                    END as status_level
                FROM invoices i 
                WHERE i.payment_status IN ('unpaid', 'partial')
                AND (i.total_amount - i.paid_amount) > 0";
        
        $params = [];
        
        // フィルター条件を追加
        if (!empty($filters['company_name'])) {
            $sql .= " AND i.company_name LIKE ?";
            $params[] = '%' . $filters['company_name'] . '%';
        }
        
        if (!empty($filters['payment_method'])) {
            $sql .= " AND i.payment_method = ?";
            $params[] = $filters['payment_method'];
        }
        
        if (!empty($filters['overdue_only'])) {
            $sql .= " AND i.due_date < NOW()";
        }
        
        $sql .= " ORDER BY i.due_date ASC, outstanding_amount DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * 支払い統計情報を取得
     * @param string $period 期間 ('today', 'week', 'month', 'year')
     * @return array 統計データ
     */
    public function getPaymentStatistics($period = 'month') {
        $dateCondition = match($period) {
            'today' => "DATE(p.payment_date) = CURDATE()",
            'week' => "p.payment_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
            'month' => "p.payment_date >= DATE_SUB(NOW(), INTERVAL 1 MONTH)",
            'year' => "p.payment_date >= DATE_SUB(NOW(), INTERVAL 1 YEAR)",
            default => "p.payment_date >= DATE_SUB(NOW(), INTERVAL 1 MONTH)"
        };
        
        // 支払い方法別統計
        $sql = "SELECT 
                    p.payment_method,
                    COUNT(*) as payment_count,
                    SUM(p.amount) as total_amount,
                    AVG(p.amount) as average_amount
                FROM payments p 
                WHERE {$dateCondition}
                GROUP BY p.payment_method
                ORDER BY total_amount DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $paymentMethods = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 支払い方法名を日本語に変換
        foreach ($paymentMethods as &$method) {
            $method['payment_method_name'] = self::getPaymentMethodName($method['payment_method']);
        }
        
        // 全体統計
        $totalSql = "SELECT 
                        COUNT(*) as total_payments,
                        SUM(amount) as total_amount,
                        AVG(amount) as average_payment
                    FROM payments 
                    WHERE {$dateCondition}";
        
        $stmt = $this->db->prepare($totalSql);
        $stmt->execute();
        $totalStats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'period' => $period,
            'total_statistics' => $totalStats,
            'payment_methods' => $paymentMethods
        ];
    }
    
    /**
     * 支払いアラート情報を取得
     * @return array アラート情報
     */
    public function getPaymentAlerts() {
        $alerts = [];
        
        // 期限切れ請求書
        $overdueSql = "SELECT COUNT(*) as count, SUM(total_amount - paid_amount) as amount 
                      FROM invoices 
                      WHERE payment_status IN ('unpaid', 'partial') 
                      AND due_date < NOW()";
        $stmt = $this->db->prepare($overdueSql);
        $stmt->execute();
        $overdue = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($overdue['count'] > 0) {
            $alerts[] = [
                'type' => 'danger',
                'title' => '期限切れ請求書',
                'message' => "{$overdue['count']}件の請求書が支払期限を過ぎています（未回収金額: ¥" . number_format($overdue['amount']) . "）",
                'count' => $overdue['count']
            ];
        }
        
        // 期限間近の請求書（7日以内）
        $soonSql = "SELECT COUNT(*) as count, SUM(total_amount - paid_amount) as amount 
                   FROM invoices 
                   WHERE payment_status IN ('unpaid', 'partial') 
                   AND due_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)";
        $stmt = $this->db->prepare($soonSql);
        $stmt->execute();
        $soon = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($soon['count'] > 0) {
            $alerts[] = [
                'type' => 'warning',
                'title' => '支払期限間近',
                'message' => "{$soon['count']}件の請求書が7日以内に支払期限を迎えます（金額: ¥" . number_format($soon['amount']) . "）",
                'count' => $soon['count']
            ];
        }
        
        return $alerts;
    }
    
    /**
     * 支払い一覧を取得（ページネーション対応）
     * @param array $filters フィルター条件
     * @param int $page ページ番号
     * @param int $limit 1ページあたりの件数
     * @return array 支払い一覧データ
     */
    public function getPaymentsList($filters = [], $page = 1, $limit = 50) {
        $offset = ($page - 1) * $limit;
        
        $sql = "SELECT 
                    p.id,
                    p.payment_date,
                    p.amount,
                    p.payment_method,
                    p.reference_number,
                    p.notes,
                    i.invoice_number,
                    i.company_name,
                    i.department_name,
                    i.total_amount as invoice_total,
                    i.payment_status
                FROM payments p
                INNER JOIN invoices i ON p.invoice_id = i.id
                WHERE 1=1";
        
        $params = [];
        
        // フィルター条件
        if (!empty($filters['payment_method'])) {
            $sql .= " AND p.payment_method = ?";
            $params[] = $filters['payment_method'];
        }
        
        if (!empty($filters['company_name'])) {
            $sql .= " AND i.company_name LIKE ?";
            $params[] = '%' . $filters['company_name'] . '%';
        }
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND p.payment_date >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND p.payment_date <= ?";
            $params[] = $filters['date_to'];
        }
        
        $sql .= " ORDER BY p.payment_date DESC, p.id DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 支払い方法名を日本語に変換
        foreach ($payments as &$payment) {
            $payment['payment_method_name'] = self::getPaymentMethodName($payment['payment_method']);
        }
        
        // 総件数を取得
        $countSql = str_replace("SELECT p.id, p.payment_date, p.amount, p.payment_method, p.reference_number, p.notes, i.invoice_number, i.company_name, i.department_name, i.total_amount as invoice_total, i.payment_status FROM", "SELECT COUNT(*) as total FROM", explode(" ORDER BY", $sql)[0]);
        $countParams = array_slice($params, 0, -2); // LIMIT, OFFSETを除く
        
        $stmt = $this->db->prepare($countSql);
        $stmt->execute($countParams);
        $totalCount = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        return [
            'payments' => $payments,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total' => $totalCount,
                'last_page' => ceil($totalCount / $limit)
            ]
        ];
    }
    
    /**
     * 支払いキャンセル
     * @param int $paymentId 支払いID
     * @param string $reason キャンセル理由
     * @return array 処理結果
     */
    public function cancelPayment($paymentId, $reason = '') {
        try {
            $this->db->beginTransaction();
            
            // 支払い情報を取得
            $stmt = $this->db->prepare("SELECT * FROM payments WHERE id = ?");
            $stmt->execute([$paymentId]);
            $payment = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$payment) {
                throw new Exception('指定された支払いが見つかりません');
            }
            
            // 請求書の支払い金額を調整
            $stmt = $this->db->prepare("SELECT paid_amount, total_amount FROM invoices WHERE id = ?");
            $stmt->execute([$payment['invoice_id']]);
            $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $newPaidAmount = max(0, $invoice['paid_amount'] - $payment['amount']);
            $paymentStatus = ($newPaidAmount == 0) ? 'unpaid' : (($newPaidAmount >= $invoice['total_amount']) ? 'paid' : 'partial');
            
            // 支払いを削除（または無効化）
            $stmt = $this->db->prepare("DELETE FROM payments WHERE id = ?");
            $stmt->execute([$paymentId]);
            
            // 請求書の支払い状況を更新
            $stmt = $this->db->prepare("UPDATE invoices SET paid_amount = ?, payment_status = ? WHERE id = ?");
            $stmt->execute([$newPaidAmount, $paymentStatus, $payment['invoice_id']]);
            
            // キャンセルログを記録（将来的に実装）
            // TODO: payment_cancellation_logs テーブルに記録
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => '支払いをキャンセルしました',
                'cancelled_amount' => $payment['amount'],
                'new_paid_amount' => $newPaidAmount,
                'payment_status' => $paymentStatus
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}

?>
