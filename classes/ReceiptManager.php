<?php
/**
 * ReceiptManager - 領収書管理
 * 入金記録から領収書を発行・管理
 */

class ReceiptManager {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * 領収書番号を生成
     * フォーマット: RCP-YYYY-NNNNN
     * @return string 領収書番号
     */
    private function generateReceiptNumber() {
        try {
            $year = date('Y');
            $conn = $this->db->getConnection();

            // トランザクション開始
            $conn->beginTransaction();

            // 該当年のシーケンスを取得または作成（FOR UPDATE でロック）
            $sql = "SELECT last_number FROM receipt_sequence WHERE year = :year FOR UPDATE";
            $stmt = $conn->prepare($sql);
            $stmt->execute([':year' => $year]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                // 既存の場合は+1
                $nextNumber = $row['last_number'] + 1;
                $updateSql = "UPDATE receipt_sequence SET last_number = :last_number WHERE year = :year";
                $updateStmt = $conn->prepare($updateSql);
                $updateStmt->execute([
                    ':last_number' => $nextNumber,
                    ':year' => $year
                ]);
            } else {
                // 新規の場合は1から開始
                $nextNumber = 1;
                $insertSql = "INSERT INTO receipt_sequence (year, last_number) VALUES (:year, :last_number)";
                $insertStmt = $conn->prepare($insertSql);
                $insertStmt->execute([
                    ':year' => $year,
                    ':last_number' => $nextNumber
                ]);
            }

            $conn->commit();

            // RCP-2025-00001 形式
            return sprintf('RCP-%s-%05d', $year, $nextNumber);

        } catch (Exception $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            throw $e;
        }
    }

    /**
     * 領収書を発行
     * @param array $params 領収書発行パラメータ
     * @return array 発行結果
     */
    public function issueReceipt($params) {
        try {
            $paymentId = $params['payment_id'] ?? null;
            $issueDate = $params['issue_date'] ?? date('Y-m-d');
            $description = $params['description'] ?? 'お弁当代として';
            $issuerName = $params['issuer_name'] ?? 'システム管理者';
            $createdBy = $params['created_by'] ?? 'system';

            if (!$paymentId) {
                return ['success' => false, 'message' => '入金IDが指定されていません'];
            }

            $conn = $this->db->getConnection();

            // 既に領収書が発行されていないかチェック
            $checkSql = "SELECT id, receipt_number FROM receipts WHERE payment_id = :payment_id";
            $checkStmt = $conn->prepare($checkSql);
            $checkStmt->execute([':payment_id' => $paymentId]);
            $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                return [
                    'success' => false,
                    'message' => 'この入金記録には既に領収書が発行されています',
                    'receipt_number' => $existing['receipt_number']
                ];
            }

            // 入金記録を取得
            $paymentSql = "
                SELECT
                    op.id,
                    op.amount,
                    op.payment_method,
                    op.user_name,
                    op.company_name,
                    op.payment_type
                FROM order_payments op
                WHERE op.id = :payment_id
            ";
            $paymentStmt = $conn->prepare($paymentSql);
            $paymentStmt->execute([':payment_id' => $paymentId]);
            $payment = $paymentStmt->fetch(PDO::FETCH_ASSOC);

            if (!$payment) {
                return ['success' => false, 'message' => '入金記録が見つかりません'];
            }

            // 宛名を決定（企業名がある場合は企業名、なければ個人名）
            if ($payment['payment_type'] === 'company' && !empty($payment['company_name'])) {
                $recipientName = $payment['company_name'] . ' 御中';
            } else {
                $recipientName = $payment['user_name'] . ' 様';
            }

            // 支払方法の表示名
            $paymentMethodDisplay = $this->getPaymentMethodDisplay($payment['payment_method']);

            // 領収書番号を生成
            $receiptNumber = $this->generateReceiptNumber();

            // 備考欄に発行者と支払方法を記録
            $notes = "発行者: {$issuerName}\n支払方法: {$paymentMethodDisplay}";

            // 領収書を登録（既存のテーブル構造に合わせる）
            $insertSql = "
                INSERT INTO receipts (
                    receipt_number,
                    payment_id,
                    issue_date,
                    recipient_name,
                    amount,
                    purpose,
                    notes,
                    status
                ) VALUES (
                    :receipt_number,
                    :payment_id,
                    :issue_date,
                    :recipient_name,
                    :amount,
                    :purpose,
                    :notes,
                    'issued'
                )
            ";

            $insertStmt = $conn->prepare($insertSql);
            $insertStmt->execute([
                ':receipt_number' => $receiptNumber,
                ':payment_id' => $paymentId,
                ':issue_date' => $issueDate,
                ':recipient_name' => $recipientName,
                ':amount' => $payment['amount'],
                ':purpose' => $description,
                ':notes' => $notes
            ]);

            return [
                'success' => true,
                'message' => '領収書を発行しました',
                'receipt_id' => $conn->lastInsertId(),
                'receipt_number' => $receiptNumber
            ];

        } catch (Exception $e) {
            error_log("Receipt issue error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => '領収書の発行に失敗しました: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 領収書を取得
     * @param int $receiptId 領収書ID
     * @return array|null 領収書データ
     */
    public function getReceipt($receiptId) {
        try {
            $sql = "
                SELECT
                    r.*,
                    op.payment_date,
                    op.payment_method,
                    op.user_code,
                    op.user_name,
                    op.company_name,
                    op.payment_type
                FROM receipts r
                INNER JOIN order_payments op ON r.payment_id = op.id
                WHERE r.id = :receipt_id
            ";

            $stmt = $this->db->getConnection()->prepare($sql);
            $stmt->execute([':receipt_id' => $receiptId]);

            $receipt = $stmt->fetch(PDO::FETCH_ASSOC);

            // 既存テーブル構造に合わせてエイリアスを追加
            if ($receipt) {
                $receipt['description'] = $receipt['purpose'] ?? '';
                $receipt['payment_method_display'] = $this->getPaymentMethodDisplay($receipt['payment_method'] ?? '');
                // notesから発行者名を抽出（"発行者: XXX"の形式）
                if (!empty($receipt['notes']) && preg_match('/発行者:\s*(.+?)(?:\n|$)/', $receipt['notes'], $matches)) {
                    $receipt['issuer_name'] = $matches[1];
                } else {
                    $receipt['issuer_name'] = 'システム管理者';
                }
            }

            return $receipt;

        } catch (Exception $e) {
            error_log("Get receipt error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * 領収書番号から領収書を取得
     * @param string $receiptNumber 領収書番号
     * @return array|null 領収書データ
     */
    public function getReceiptByNumber($receiptNumber) {
        try {
            $sql = "
                SELECT
                    r.*,
                    op.payment_date,
                    op.payment_method,
                    op.user_code,
                    op.user_name,
                    op.company_name,
                    op.payment_type
                FROM receipts r
                INNER JOIN order_payments op ON r.payment_id = op.id
                WHERE r.receipt_number = :receipt_number
            ";

            $stmt = $this->db->getConnection()->prepare($sql);
            $stmt->execute([':receipt_number' => $receiptNumber]);

            $receipt = $stmt->fetch(PDO::FETCH_ASSOC);

            // 既存テーブル構造に合わせてエイリアスを追加
            if ($receipt) {
                $receipt['description'] = $receipt['purpose'] ?? '';
                $receipt['payment_method_display'] = $this->getPaymentMethodDisplay($receipt['payment_method'] ?? '');
                // notesから発行者名を抽出
                if (!empty($receipt['notes']) && preg_match('/発行者:\s*(.+?)(?:\n|$)/', $receipt['notes'], $matches)) {
                    $receipt['issuer_name'] = $matches[1];
                } else {
                    $receipt['issuer_name'] = 'システム管理者';
                }
            }

            return $receipt;

        } catch (Exception $e) {
            error_log("Get receipt by number error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * 入金IDから領収書を取得
     * @param int $paymentId 入金ID
     * @return array|null 領収書データ
     */
    public function getReceiptByPaymentId($paymentId) {
        try {
            $sql = "
                SELECT
                    r.*,
                    op.payment_date,
                    op.payment_method,
                    op.user_code,
                    op.user_name,
                    op.company_name,
                    op.payment_type
                FROM receipts r
                INNER JOIN order_payments op ON r.payment_id = op.id
                WHERE r.payment_id = :payment_id
            ";

            $stmt = $this->db->getConnection()->prepare($sql);
            $stmt->execute([':payment_id' => $paymentId]);

            $receipt = $stmt->fetch(PDO::FETCH_ASSOC);

            // 既存テーブル構造に合わせてエイリアスを追加
            if ($receipt) {
                $receipt['description'] = $receipt['purpose'] ?? '';
                $receipt['payment_method_display'] = $this->getPaymentMethodDisplay($receipt['payment_method'] ?? '');
                // notesから発行者名を抽出
                if (!empty($receipt['notes']) && preg_match('/発行者:\s*(.+?)(?:\n|$)/', $receipt['notes'], $matches)) {
                    $receipt['issuer_name'] = $matches[1];
                } else {
                    $receipt['issuer_name'] = 'システム管理者';
                }
            }

            return $receipt;

        } catch (Exception $e) {
            error_log("Get receipt by payment ID error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * 入金記録に領収書が発行済みかチェック
     * @param int $paymentId 入金ID
     * @return bool 発行済みならtrue
     */
    public function checkReceiptExists($paymentId) {
        try {
            $sql = "SELECT COUNT(*) as count FROM receipts WHERE payment_id = :payment_id";
            $stmt = $this->db->getConnection()->prepare($sql);
            $stmt->execute([':payment_id' => $paymentId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return ($result['count'] > 0);

        } catch (Exception $e) {
            error_log("Check receipt exists error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 支払方法の表示名を取得
     * @param string $method 支払方法コード
     * @return string 表示名
     */
    private function getPaymentMethodDisplay($method) {
        $methods = [
            'cash' => '現金',
            'bank_transfer' => '銀行振込',
            'account_debit' => '口座振替',
            'other' => 'その他'
        ];

        return $methods[$method] ?? 'その他';
    }

    /**
     * 領収書一覧を取得（ページネーション対応）
     * @param array $params フィルタパラメータ
     * @return array 領収書一覧
     */
    public function getReceiptList($params = []) {
        try {
            $page = $params['page'] ?? 1;
            $perPage = $params['per_page'] ?? 50;
            $offset = ($page - 1) * $perPage;

            $sql = "
                SELECT
                    r.*,
                    op.payment_date,
                    op.user_name,
                    op.company_name,
                    op.payment_type
                FROM receipts r
                INNER JOIN order_payments op ON r.payment_id = op.id
                ORDER BY r.issue_date DESC, r.id DESC
                LIMIT :limit OFFSET :offset
            ";

            $stmt = $this->db->getConnection()->prepare($sql);
            $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            $receipts = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 総件数を取得
            $countSql = "SELECT COUNT(*) as total FROM receipts";
            $countStmt = $this->db->getConnection()->query($countSql);
            $countResult = $countStmt->fetch(PDO::FETCH_ASSOC);
            $total = (int)$countResult['total'];

            return [
                'success' => true,
                'receipts' => $receipts,
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => ceil($total / $perPage)
            ];

        } catch (Exception $e) {
            error_log("Get receipt list error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => '領収書一覧の取得に失敗しました: ' . $e->getMessage(),
                'receipts' => []
            ];
        }
    }
}
