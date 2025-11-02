<?php
/**
 * InvoiceManager - 請求書管理クラス
 * 請求書の生成、更新、状態管理を行う
 */

class InvoiceManager {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * 請求書を生成（個人別または企業別）
     * @param array $params 請求書生成パラメータ
     * @return array 結果
     */
    public function generateInvoice($params) {
        try {
            $conn = $this->db->getConnection();
            $conn->beginTransaction();

            // パラメータ取得
            $invoiceType = $params['invoice_type'] ?? 'individual'; // 'individual' or 'company'
            $periodStart = $params['period_start'];
            $periodEnd = $params['period_end'];
            $groupByCompany = $params['group_by_company'] ?? false;
            $userId = $params['user_id'] ?? null;
            $companyName = $params['company_name'] ?? null;

            // 請求対象の注文を取得
            if ($invoiceType === 'company' && $groupByCompany) {
                // 企業別請求書
                $result = $this->generateCompanyInvoices($periodStart, $periodEnd, $companyName);
            } else {
                // 個人別請求書
                $result = $this->generateIndividualInvoices($periodStart, $periodEnd, $userId);
            }

            $conn->commit();
            return [
                'success' => true,
                'invoices_created' => $result['count'] ?? 0,
                'total_amount' => $result['total_amount'] ?? 0,
                'invoice_ids' => $result['invoice_ids'] ?? []
            ];

        } catch (Exception $e) {
            if (isset($conn)) {
                $conn->rollBack();
            }
            error_log("InvoiceManager::generateInvoice Error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 個人別請求書を生成
     */
    private function generateIndividualInvoices($periodStart, $periodEnd, $userId = null) {
        $conn = $this->db->getConnection();

        // 請求対象ユーザーの注文を取得
        $sql = "
            SELECT
                o.user_id,
                o.user_code,
                o.user_name,
                o.company_name,
                u.department,
                u.payment_method,
                COUNT(o.id) as order_count,
                SUM(o.total_amount) as total_amount
            FROM orders o
            LEFT JOIN users u ON o.user_id = u.id
            WHERE o.order_date BETWEEN :period_start AND :period_end
        ";

        $params = [
            ':period_start' => $periodStart,
            ':period_end' => $periodEnd
        ];

        // 特定ユーザーのみ
        if ($userId) {
            $sql .= " AND o.user_id = :user_id";
            $params[':user_id'] = $userId;
        }

        // 未請求の注文のみ（既に請求書に含まれていない）
        $sql .= "
            AND o.id NOT IN (
                SELECT order_id FROM invoice_details
            )
            GROUP BY o.user_id, o.user_code, o.user_name, o.company_name, u.department, u.payment_method
            HAVING total_amount > 0
        ";

        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $invoiceIds = [];
        $totalAmount = 0;

        foreach ($users as $user) {
            // 請求書番号を生成
            $invoiceNumber = $this->generateInvoiceNumber();

            // 請求日・支払期限を設定
            $invoiceDate = date('Y-m-d');
            $dueDays = 30; // システム設定から取得可能
            $dueDate = date('Y-m-d', strtotime($invoiceDate . " +{$dueDays} days"));

            // 小計・税額・合計を計算
            $subtotal = $user['total_amount'];
            $taxRate = 10.00; // システム設定から取得可能
            $taxAmount = round($subtotal * $taxRate / 100, 2);
            $total = $subtotal + $taxAmount;

            // 請求書を作成
            $insertSql = "
                INSERT INTO invoices (
                    invoice_number, user_id, user_code, user_name, company_name, department,
                    invoice_date, due_date, period_start, period_end,
                    subtotal, tax_rate, tax_amount, total_amount,
                    invoice_type, status, payment_method
                ) VALUES (
                    :invoice_number, :user_id, :user_code, :user_name, :company_name, :department,
                    :invoice_date, :due_date, :period_start, :period_end,
                    :subtotal, :tax_rate, :tax_amount, :total_amount,
                    'individual', 'issued', :payment_method
                )
            ";

            $stmt = $conn->prepare($insertSql);
            $stmt->execute([
                ':invoice_number' => $invoiceNumber,
                ':user_id' => $user['user_id'],
                ':user_code' => $user['user_code'],
                ':user_name' => $user['user_name'],
                ':company_name' => $user['company_name'],
                ':department' => $user['department'],
                ':invoice_date' => $invoiceDate,
                ':due_date' => $dueDate,
                ':period_start' => $periodStart,
                ':period_end' => $periodEnd,
                ':subtotal' => $subtotal,
                ':tax_rate' => $taxRate,
                ':tax_amount' => $taxAmount,
                ':total_amount' => $total,
                ':payment_method' => $user['payment_method']
            ]);

            $invoiceId = $conn->lastInsertId();
            $invoiceIds[] = $invoiceId;
            $totalAmount += $total;

            // 請求書明細を作成
            $this->createInvoiceDetails($invoiceId, $user['user_id'], $periodStart, $periodEnd);
        }

        return [
            'count' => count($invoiceIds),
            'total_amount' => $totalAmount,
            'invoice_ids' => $invoiceIds
        ];
    }

    /**
     * 企業別請求書を生成
     */
    private function generateCompanyInvoices($periodStart, $periodEnd, $companyName = null) {
        $conn = $this->db->getConnection();

        // 企業別の注文を取得
        $sql = "
            SELECT
                MIN(o.user_id) as user_id,
                o.company_name,
                COUNT(DISTINCT o.user_id) as user_count,
                COUNT(o.id) as order_count,
                SUM(o.total_amount) as total_amount
            FROM orders o
            LEFT JOIN users u ON o.user_id = u.id
            WHERE o.order_date BETWEEN :period_start AND :period_end
            AND o.company_name IS NOT NULL
            AND o.company_name != ''
        ";

        $params = [
            ':period_start' => $periodStart,
            ':period_end' => $periodEnd
        ];

        if ($companyName) {
            $sql .= " AND o.company_name = :company_name";
            $params[':company_name'] = $companyName;
        }

        // 未請求の注文のみ
        $sql .= "
            AND o.id NOT IN (
                SELECT order_id FROM invoice_details
            )
            GROUP BY o.company_name
            HAVING total_amount > 0
        ";

        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $invoiceIds = [];
        $totalAmount = 0;

        foreach ($companies as $company) {
            // 請求書番号を生成
            $invoiceNumber = $this->generateInvoiceNumber();

            // 請求日・支払期限を設定
            $invoiceDate = date('Y-m-d');
            $dueDays = 30;
            $dueDate = date('Y-m-d', strtotime($invoiceDate . " +{$dueDays} days"));

            // 小計・税額・合計を計算
            $subtotal = $company['total_amount'];
            $taxRate = 10.00;
            $taxAmount = round($subtotal * $taxRate / 100, 2);
            $total = $subtotal + $taxAmount;

            // 請求書を作成（代表ユーザーIDを使用）
            $insertSql = "
                INSERT INTO invoices (
                    invoice_number, user_id, user_code, user_name, company_name,
                    invoice_date, due_date, period_start, period_end,
                    subtotal, tax_rate, tax_amount, total_amount,
                    invoice_type, status, payment_method,
                    notes
                ) VALUES (
                    :invoice_number, :user_id, '', :company_name, :company_name,
                    :invoice_date, :due_date, :period_start, :period_end,
                    :subtotal, :tax_rate, :tax_amount, :total_amount,
                    'company', 'issued', 'bank_transfer',
                    :notes
                )
            ";

            $notes = "企業別請求書（" . $company['user_count'] . "名分）";

            $stmt = $conn->prepare($insertSql);
            $stmt->execute([
                ':invoice_number' => $invoiceNumber,
                ':user_id' => $company['user_id'],
                ':company_name' => $company['company_name'],
                ':invoice_date' => $invoiceDate,
                ':due_date' => $dueDate,
                ':period_start' => $periodStart,
                ':period_end' => $periodEnd,
                ':subtotal' => $subtotal,
                ':tax_rate' => $taxRate,
                ':tax_amount' => $taxAmount,
                ':total_amount' => $total,
                ':notes' => $notes
            ]);

            $invoiceId = $conn->lastInsertId();
            $invoiceIds[] = $invoiceId;
            $totalAmount += $total;

            // 企業全体の請求書明細を作成
            $this->createInvoiceDetailsForCompany($invoiceId, $company['company_name'], $periodStart, $periodEnd);
        }

        return [
            'count' => count($invoiceIds),
            'total_amount' => $totalAmount,
            'invoice_ids' => $invoiceIds
        ];
    }

    /**
     * 請求書明細を作成（個人別）
     */
    private function createInvoiceDetails($invoiceId, $userId, $periodStart, $periodEnd) {
        $conn = $this->db->getConnection();

        $sql = "
            INSERT INTO invoice_details (
                invoice_id, order_id, order_date,
                product_code, product_name, quantity, unit_price, amount
            )
            SELECT
                :invoice_id,
                o.id,
                o.order_date,
                o.product_code,
                o.product_name,
                o.quantity,
                o.unit_price,
                o.total_amount
            FROM orders o
            WHERE o.user_id = :user_id
            AND o.order_date BETWEEN :period_start AND :period_end
            AND o.id NOT IN (
                SELECT order_id FROM invoice_details WHERE invoice_id != :invoice_id
            )
        ";

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':invoice_id' => $invoiceId,
            ':user_id' => $userId,
            ':period_start' => $periodStart,
            ':period_end' => $periodEnd
        ]);
    }

    /**
     * 請求書明細を作成（企業別）
     */
    private function createInvoiceDetailsForCompany($invoiceId, $companyName, $periodStart, $periodEnd) {
        $conn = $this->db->getConnection();

        $sql = "
            INSERT INTO invoice_details (
                invoice_id, order_id, order_date,
                product_code, product_name, quantity, unit_price, amount
            )
            SELECT
                :invoice_id,
                o.id,
                o.order_date,
                o.product_code,
                o.product_name,
                o.quantity,
                o.unit_price,
                o.total_amount
            FROM orders o
            WHERE o.company_name = :company_name
            AND o.order_date BETWEEN :period_start AND :period_end
            AND o.id NOT IN (
                SELECT order_id FROM invoice_details WHERE invoice_id != :invoice_id
            )
        ";

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':invoice_id' => $invoiceId,
            ':company_name' => $companyName,
            ':period_start' => $periodStart,
            ':period_end' => $periodEnd
        ]);
    }

    /**
     * 請求書番号を生成
     */
    private function generateInvoiceNumber() {
        $prefix = 'SM-'; // システム設定から取得可能
        $date = date('Ymd');

        // 今日の連番を取得
        $conn = $this->db->getConnection();
        $sql = "
            SELECT COUNT(*) as count
            FROM invoices
            WHERE DATE(created_at) = CURDATE()
        ";
        $stmt = $conn->query($sql);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $sequence = ($result['count'] ?? 0) + 1;

        return $prefix . $date . '-' . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * 請求書一覧を取得
     */
    public function getInvoices($filters = []) {
        try {
            $sql = "
                SELECT
                    i.*,
                    COALESCE(SUM(p.amount), 0) as paid_amount,
                    (i.total_amount - COALESCE(SUM(p.amount), 0)) as outstanding_amount,
                    COUNT(DISTINCT id.id) as detail_count
                FROM invoices i
                LEFT JOIN payments p ON i.id = p.invoice_id AND p.payment_status = 'completed'
                LEFT JOIN invoice_details id ON i.id = id.invoice_id
                WHERE 1=1
            ";

            $params = [];

            // フィルタ条件
            if (!empty($filters['status'])) {
                $sql .= " AND i.status = :status";
                $params[':status'] = $filters['status'];
            }

            if (!empty($filters['invoice_type'])) {
                $sql .= " AND i.invoice_type = :invoice_type";
                $params[':invoice_type'] = $filters['invoice_type'];
            }

            if (!empty($filters['company_name'])) {
                $sql .= " AND i.company_name LIKE :company_name";
                $params[':company_name'] = '%' . $filters['company_name'] . '%';
            }

            if (!empty($filters['period_start'])) {
                $sql .= " AND i.invoice_date >= :period_start";
                $params[':period_start'] = $filters['period_start'];
            }

            if (!empty($filters['period_end'])) {
                $sql .= " AND i.invoice_date <= :period_end";
                $params[':period_end'] = $filters['period_end'];
            }

            $sql .= " GROUP BY i.id ORDER BY i.invoice_date DESC, i.id DESC";

            $limit = $filters['limit'] ?? 100;
            $sql .= " LIMIT :limit";
            $params[':limit'] = $limit;

            $stmt = $this->db->getConnection()->prepare($sql);

            foreach ($params as $key => $value) {
                if ($key === ':limit') {
                    $stmt->bindValue($key, $value, PDO::PARAM_INT);
                } else {
                    $stmt->bindValue($key, $value, PDO::PARAM_STR);
                }
            }

            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            error_log("InvoiceManager::getInvoices Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 請求書詳細を取得
     */
    public function getInvoiceDetails($invoiceId) {
        try {
            $conn = $this->db->getConnection();

            // 請求書ヘッダー
            $sql = "
                SELECT
                    i.*,
                    COALESCE(SUM(p.amount), 0) as paid_amount,
                    (i.total_amount - COALESCE(SUM(p.amount), 0)) as outstanding_amount
                FROM invoices i
                LEFT JOIN payments p ON i.id = p.invoice_id AND p.payment_status = 'completed'
                WHERE i.id = :invoice_id
                GROUP BY i.id
            ";
            $stmt = $conn->prepare($sql);
            $stmt->execute([':invoice_id' => $invoiceId]);
            $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$invoice) {
                return null;
            }

            // 請求書明細
            $sql = "
                SELECT * FROM invoice_details
                WHERE invoice_id = :invoice_id
                ORDER BY order_date, id
            ";
            $stmt = $conn->prepare($sql);
            $stmt->execute([':invoice_id' => $invoiceId]);
            $invoice['details'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 入金履歴
            $sql = "
                SELECT * FROM payments
                WHERE invoice_id = :invoice_id
                ORDER BY payment_date DESC, id DESC
            ";
            $stmt = $conn->prepare($sql);
            $stmt->execute([':invoice_id' => $invoiceId]);
            $invoice['payments'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $invoice;

        } catch (Exception $e) {
            error_log("InvoiceManager::getInvoiceDetails Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * 請求書のステータスを更新
     */
    public function updateInvoiceStatus($invoiceId) {
        try {
            $invoice = $this->getInvoiceDetails($invoiceId);
            if (!$invoice) {
                return false;
            }

            $paidAmount = $invoice['paid_amount'];
            $totalAmount = $invoice['total_amount'];

            $newStatus = $invoice['status'];

            // 入金状況に応じてステータスを更新
            if ($paidAmount >= $totalAmount) {
                $newStatus = 'paid';
            } elseif ($paidAmount > 0) {
                // 一部入金の場合は 'issued' のまま（または新しいステータスを追加）
                $newStatus = 'issued';
            } elseif (strtotime($invoice['due_date']) < time()) {
                $newStatus = 'overdue';
            }

            if ($newStatus !== $invoice['status']) {
                $sql = "UPDATE invoices SET status = :status WHERE id = :id";
                $stmt = $this->db->getConnection()->prepare($sql);
                $stmt->execute([
                    ':status' => $newStatus,
                    ':id' => $invoiceId
                ]);
            }

            return true;

        } catch (Exception $e) {
            error_log("InvoiceManager::updateInvoiceStatus Error: " . $e->getMessage());
            return false;
        }
    }
}
