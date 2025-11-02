<?php
/**
 * PaymentManager - 入金管理クラス
 * 入金の登録、更新、売掛金残高の管理を行う
 */

class PaymentManager {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * 入金を登録
     * @param array $params 入金パラメータ
     * @return array 結果
     */
    public function registerPayment($params) {
        try {
            $conn = $this->db->getConnection();
            $conn->beginTransaction();

            // パラメータ検証
            $invoiceId = $params['invoice_id'] ?? null;
            $paymentDate = $params['payment_date'] ?? date('Y-m-d');
            $amount = $params['amount'] ?? 0;
            $paymentMethod = $params['payment_method'] ?? 'cash';
            $referenceNumber = $params['reference_number'] ?? '';
            $notes = $params['notes'] ?? '';
            $createdBy = $params['created_by'] ?? 'system';

            if (!$invoiceId || $amount <= 0) {
                throw new Exception('請求書IDと入金額は必須です');
            }

            // 請求書の存在確認
            $sql = "SELECT * FROM invoices WHERE id = :invoice_id";
            $stmt = $conn->prepare($sql);
            $stmt->execute([':invoice_id' => $invoiceId]);
            $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$invoice) {
                throw new Exception('請求書が見つかりません');
            }

            // 既存の入金額を確認
            $sql = "
                SELECT COALESCE(SUM(amount), 0) as paid_amount
                FROM payments
                WHERE invoice_id = :invoice_id AND payment_status = 'completed'
            ";
            $stmt = $conn->prepare($sql);
            $stmt->execute([':invoice_id' => $invoiceId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $paidAmount = $result['paid_amount'];

            // 残額確認
            $remainingAmount = $invoice['total_amount'] - $paidAmount;
            if ($amount > $remainingAmount) {
                throw new Exception("入金額が残額（¥" . number_format($remainingAmount) . "）を超えています");
            }

            // 入金を登録
            $sql = "
                INSERT INTO payments (
                    invoice_id, payment_date, amount, payment_method,
                    payment_status, reference_number, notes, created_by
                ) VALUES (
                    :invoice_id, :payment_date, :amount, :payment_method,
                    'completed', :reference_number, :notes, :created_by
                )
            ";

            $stmt = $conn->prepare($sql);
            $stmt->execute([
                ':invoice_id' => $invoiceId,
                ':payment_date' => $paymentDate,
                ':amount' => $amount,
                ':payment_method' => $paymentMethod,
                ':reference_number' => $referenceNumber,
                ':notes' => $notes,
                ':created_by' => $createdBy
            ]);

            $paymentId = $conn->lastInsertId();

            // 請求書のステータスを更新
            require_once __DIR__ . '/InvoiceManager.php';
            $invoiceManager = new InvoiceManager();
            $invoiceManager->updateInvoiceStatus($invoiceId);

            $conn->commit();

            return [
                'success' => true,
                'payment_id' => $paymentId,
                'message' => '入金を登録しました'
            ];

        } catch (Exception $e) {
            if (isset($conn)) {
                $conn->rollBack();
            }
            error_log("PaymentManager::registerPayment Error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 入金一覧を取得
     */
    public function getPayments($filters = []) {
        try {
            $sql = "
                SELECT
                    p.*,
                    i.invoice_number,
                    i.user_name,
                    i.company_name,
                    i.total_amount as invoice_total,
                    i.invoice_date,
                    i.due_date
                FROM payments p
                INNER JOIN invoices i ON p.invoice_id = i.id
                WHERE 1=1
            ";

            $params = [];

            // フィルタ条件
            if (!empty($filters['invoice_id'])) {
                $sql .= " AND p.invoice_id = :invoice_id";
                $params[':invoice_id'] = $filters['invoice_id'];
            }

            if (!empty($filters['payment_method'])) {
                $sql .= " AND p.payment_method = :payment_method";
                $params[':payment_method'] = $filters['payment_method'];
            }

            if (!empty($filters['date_from'])) {
                $sql .= " AND p.payment_date >= :date_from";
                $params[':date_from'] = $filters['date_from'];
            }

            if (!empty($filters['date_to'])) {
                $sql .= " AND p.payment_date <= :date_to";
                $params[':date_to'] = $filters['date_to'];
            }

            if (!empty($filters['company_name'])) {
                $sql .= " AND i.company_name LIKE :company_name";
                $params[':company_name'] = '%' . $filters['company_name'] . '%';
            }

            $sql .= " ORDER BY p.payment_date DESC, p.id DESC";

            $limit = $filters['limit'] ?? 100;
            $sql .= " LIMIT :limit";
            $params[':limit'] = $limit;

            $stmt = $this->db->getConnection()->prepare($sql);

            foreach ($params as $key => $value) {
                if ($key === ':limit' || $key === ':invoice_id') {
                    $stmt->bindValue($key, $value, PDO::PARAM_INT);
                } else {
                    $stmt->bindValue($key, $value, PDO::PARAM_STR);
                }
            }

            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            error_log("PaymentManager::getPayments Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 売掛金残高を取得（個人別または企業別）
     */
    public function getReceivables($groupBy = 'individual', $filters = []) {
        try {
            $conn = $this->db->getConnection();

            if ($groupBy === 'company') {
                // 企業別売掛金
                $sql = "
                    SELECT
                        i.company_name,
                        COUNT(DISTINCT i.id) as invoice_count,
                        SUM(i.total_amount) as total_billed,
                        COALESCE(SUM(p.paid_amount), 0) as total_paid,
                        (SUM(i.total_amount) - COALESCE(SUM(p.paid_amount), 0)) as total_outstanding,
                        MIN(i.invoice_date) as first_invoice_date,
                        MAX(i.invoice_date) as last_invoice_date,
                        MIN(CASE WHEN i.status != 'paid' THEN i.due_date END) as nearest_due_date
                    FROM invoices i
                    LEFT JOIN (
                        SELECT invoice_id, SUM(amount) as paid_amount
                        FROM payments
                        WHERE payment_status = 'completed'
                        GROUP BY invoice_id
                    ) p ON i.id = p.invoice_id
                    WHERE i.status != 'cancelled'
                    AND i.company_name IS NOT NULL
                    AND i.company_name != ''
                ";

                $params = [];

                if (!empty($filters['company_name'])) {
                    $sql .= " AND i.company_name LIKE :company_name";
                    $params[':company_name'] = '%' . $filters['company_name'] . '%';
                }

                $sql .= "
                    GROUP BY i.company_name
                    HAVING total_outstanding > 0
                    ORDER BY total_outstanding DESC
                ";

            } else {
                // 個人別売掛金
                $sql = "
                    SELECT
                        i.user_id,
                        i.user_code,
                        i.user_name,
                        i.company_name,
                        COUNT(DISTINCT i.id) as invoice_count,
                        SUM(i.total_amount) as total_billed,
                        COALESCE(SUM(p.paid_amount), 0) as total_paid,
                        (SUM(i.total_amount) - COALESCE(SUM(p.paid_amount), 0)) as total_outstanding,
                        MIN(i.invoice_date) as first_invoice_date,
                        MAX(i.invoice_date) as last_invoice_date,
                        MIN(CASE WHEN i.status != 'paid' THEN i.due_date END) as nearest_due_date
                    FROM invoices i
                    LEFT JOIN (
                        SELECT invoice_id, SUM(amount) as paid_amount
                        FROM payments
                        WHERE payment_status = 'completed'
                        GROUP BY invoice_id
                    ) p ON i.id = p.invoice_id
                    WHERE i.status != 'cancelled'
                ";

                $params = [];

                if (!empty($filters['user_id'])) {
                    $sql .= " AND i.user_id = :user_id";
                    $params[':user_id'] = $filters['user_id'];
                }

                if (!empty($filters['company_name'])) {
                    $sql .= " AND i.company_name LIKE :company_name";
                    $params[':company_name'] = '%' . $filters['company_name'] . '%';
                }

                $sql .= "
                    GROUP BY i.user_id, i.user_code, i.user_name, i.company_name
                    HAVING total_outstanding > 0
                    ORDER BY total_outstanding DESC
                ";
            }

            $limit = $filters['limit'] ?? 100;
            $sql .= " LIMIT :limit";
            $params[':limit'] = $limit;

            $stmt = $conn->prepare($sql);

            foreach ($params as $key => $value) {
                if ($key === ':limit' || $key === ':user_id') {
                    $stmt->bindValue($key, $value, PDO::PARAM_INT);
                } else {
                    $stmt->bindValue($key, $value, PDO::PARAM_STR);
                }
            }

            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            error_log("PaymentManager::getReceivables Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 売掛金サマリーを取得
     */
    public function getReceivablesSummary() {
        try {
            $conn = $this->db->getConnection();

            $sql = "
                SELECT
                    COUNT(DISTINCT i.id) as total_invoices,
                    SUM(i.total_amount) as total_billed,
                    COALESCE(SUM(p.paid_amount), 0) as total_paid,
                    (SUM(i.total_amount) - COALESCE(SUM(p.paid_amount), 0)) as total_outstanding,
                    SUM(CASE WHEN i.status = 'overdue' THEN (i.total_amount - COALESCE(p.paid_amount, 0)) ELSE 0 END) as overdue_amount,
                    COUNT(DISTINCT CASE WHEN i.status = 'overdue' THEN i.id END) as overdue_count
                FROM invoices i
                LEFT JOIN (
                    SELECT invoice_id, SUM(amount) as paid_amount
                    FROM payments
                    WHERE payment_status = 'completed'
                    GROUP BY invoice_id
                ) p ON i.id = p.invoice_id
                WHERE i.status NOT IN ('cancelled', 'paid')
            ";

            $stmt = $conn->query($sql);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return [
                'total_invoices' => (int)($result['total_invoices'] ?? 0),
                'total_billed' => (float)($result['total_billed'] ?? 0),
                'total_paid' => (float)($result['total_paid'] ?? 0),
                'total_outstanding' => (float)($result['total_outstanding'] ?? 0),
                'overdue_amount' => (float)($result['overdue_amount'] ?? 0),
                'overdue_count' => (int)($result['overdue_count'] ?? 0)
            ];

        } catch (Exception $e) {
            error_log("PaymentManager::getReceivablesSummary Error: " . $e->getMessage());
            return [
                'total_invoices' => 0,
                'total_billed' => 0,
                'total_paid' => 0,
                'total_outstanding' => 0,
                'overdue_amount' => 0,
                'overdue_count' => 0
            ];
        }
    }

    /**
     * 入金を削除（取り消し）
     */
    public function deletePayment($paymentId, $reason = '') {
        try {
            $conn = $this->db->getConnection();
            $conn->beginTransaction();

            // 入金情報を取得
            $sql = "SELECT * FROM payments WHERE id = :payment_id";
            $stmt = $conn->prepare($sql);
            $stmt->execute([':payment_id' => $paymentId]);
            $payment = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$payment) {
                throw new Exception('入金情報が見つかりません');
            }

            // 入金を削除
            $sql = "DELETE FROM payments WHERE id = :payment_id";
            $stmt = $conn->prepare($sql);
            $stmt->execute([':payment_id' => $paymentId]);

            // 請求書のステータスを更新
            require_once __DIR__ . '/InvoiceManager.php';
            $invoiceManager = new InvoiceManager();
            $invoiceManager->updateInvoiceStatus($payment['invoice_id']);

            $conn->commit();

            return [
                'success' => true,
                'message' => '入金を取り消しました'
            ];

        } catch (Exception $e) {
            if (isset($conn)) {
                $conn->rollBack();
            }
            error_log("PaymentManager::deletePayment Error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 未請求の注文データを取得（請求書生成前）
     */
    public function getUnbilledOrders($groupBy = 'individual', $periodStart = null, $periodEnd = null) {
        try {
            $conn = $this->db->getConnection();

            // デフォルトは今月
            if (!$periodStart) {
                $periodStart = date('Y-m-01');
            }
            if (!$periodEnd) {
                $periodEnd = date('Y-m-t');
            }

            if ($groupBy === 'company') {
                // 企業別未請求額
                $sql = "
                    SELECT
                        o.company_name,
                        COUNT(DISTINCT o.user_id) as user_count,
                        COUNT(o.id) as order_count,
                        SUM(o.total_amount) as total_amount
                    FROM orders o
                    WHERE o.order_date BETWEEN :period_start AND :period_end
                    AND o.company_name IS NOT NULL
                    AND o.company_name != ''
                    AND o.id NOT IN (SELECT order_id FROM invoice_details)
                    GROUP BY o.company_name
                    HAVING total_amount > 0
                    ORDER BY total_amount DESC
                ";
            } else {
                // 個人別未請求額
                $sql = "
                    SELECT
                        o.user_id,
                        o.user_code,
                        o.user_name,
                        o.company_name,
                        COUNT(o.id) as order_count,
                        SUM(o.total_amount) as total_amount
                    FROM orders o
                    WHERE o.order_date BETWEEN :period_start AND :period_end
                    AND o.id NOT IN (SELECT order_id FROM invoice_details)
                    GROUP BY o.user_id, o.user_code, o.user_name, o.company_name
                    HAVING total_amount > 0
                    ORDER BY total_amount DESC
                ";
            }

            $stmt = $conn->prepare($sql);
            $stmt->execute([
                ':period_start' => $periodStart,
                ':period_end' => $periodEnd
            ]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            error_log("PaymentManager::getUnbilledOrders Error: " . $e->getMessage());
            return [];
        }
    }
}
