<?php
/**
 * PaymentManager.php - 修正版
 * config/database.php の Singleton Database クラス対応
 * Smiley配食事業 支払い管理システム
 */

// config/database.php を読み込み（Singleton Database クラス含む）
require_once __DIR__ . '/../config/database.php';

/**
 * 支払い管理クラス
 * PayPay対応、多様な支払い方法管理
 */
class PaymentManager {
    private $db;

    public function __construct() {
        // config/database.php の Singleton Database クラスを使用
        $this->db = Database::getInstance();
    }

    /**
     * 支払い方法の選択肢配列を取得（PayPay追加）
     * @return array 支払い方法の配列
     */
    public static function getPaymentMethods() {
        return [
            'cash' => '現金',
            'bank_transfer' => '銀行振込',
            'account_debit' => '口座引き落とし',
            'paypay' => 'PayPay',           // ⭐ 新規追加
            'mixed' => '混合',
            'other' => 'その他'
        ];
    }

    /**
     * 支払い方法の選択肢をHTMLオプションとして取得
     * @param string|null $selected 選択済みの値
     * @return string HTMLオプション文字列
     */
    public static function getPaymentMethodOptions($selected = null) {
        $methods = self::getPaymentMethods();
        $options = '';
        
        foreach ($methods as $value => $label) {
            $selectedAttr = ($selected === $value) ? ' selected' : '';
            $emoji = '';
            
            // PayPay用の絵文字追加
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
     * PayPay支払い用の特別処理
     * @param array $paymentData 支払いデータ
     * @return array 処理結果
     */
    public function processPayPayPayment($paymentData) {
        try {
            // PayPay固有の処理
            $paymentData['transaction_fee'] = 0; // PayPayは手数料無料
            $paymentData['payment_method'] = 'paypay';
            
            // 将来的なQRコード処理の準備
            if (isset($paymentData['qr_code_data'])) {
                $paymentData['reference_number'] = $this->generatePayPayReference($paymentData['qr_code_data']);
            }
            
            // 通常の支払い記録処理
            return $this->recordPayment($paymentData);
            
        } catch (Exception $e) {
            error_log("PayPay payment processing error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'PayPay支払い処理でエラーが発生しました: ' . $e->getMessage()
            ];
        }
    }

    /**
     * PayPay用の参照番号生成
     * @param string $qrData QRコードデータ
     * @return string 参照番号
     */
    private function generatePayPayReference($qrData) {
        return 'PP' . date('Ymd') . '_' . substr(md5($qrData), 0, 8);
    }

    /**
     * 支払い方法の妥当性チェック（PayPay追加）
     * @param string $paymentMethod 支払い方法
     * @return bool 妥当性
     */
    public static function isValidPaymentMethod($paymentMethod) {
        $allowedMethods = array_keys(self::getPaymentMethods());
        return in_array($paymentMethod, $allowedMethods);
    }

    /**
     * 支払い方法別の処理分岐
     * @param array $paymentData 支払いデータ
     * @return array 処理結果
     */
    public function processPaymentByMethod($paymentData) {
        $method = $paymentData['payment_method'] ?? '';
        
        switch ($method) {
            case 'paypay':
                return $this->processPayPayPayment($paymentData);
                
            case 'cash':
                return $this->processCashPayment($paymentData);
                
            case 'bank_transfer':
                return $this->processBankTransferPayment($paymentData);
                
            case 'account_debit':
                return $this->processAccountDebitPayment($paymentData);
                
            default:
                return $this->recordPayment($paymentData);
        }
    }

    /**
     * 支払い記録の基本処理
     * @param array $paymentData 支払いデータ
     * @return array 処理結果
     */
    public function recordPayment($paymentData) {
        try {
            $this->db->beginTransaction();
            
            // paymentsテーブルへの挿入
            $sql = "INSERT INTO payments (
                invoice_id, amount, payment_date, payment_method, 
                reference_number, notes, transaction_fee, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $stmt = $this->db->query($sql, [
                $paymentData['invoice_id'] ?? null,
                $paymentData['amount'] ?? 0,
                $paymentData['payment_date'] ?? date('Y-m-d'),
                $paymentData['payment_method'] ?? 'cash',
                $paymentData['reference_number'] ?? null,
                $paymentData['notes'] ?? null,
                $paymentData['transaction_fee'] ?? 0
            ]);
            
            $paymentId = $this->db->lastInsertId();
            
            // 関連する請求書の支払い状況更新
            if (!empty($paymentData['invoice_id'])) {
                $this->updateInvoicePaymentStatus($paymentData['invoice_id']);
            }
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => '支払いを記録しました',
                'payment_id' => $paymentId
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Payment recording error: " . $e->getMessage());
            
            return [
                'success' => false,
                'message' => '支払い記録でエラーが発生しました: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 請求書の支払い状況更新
     * @param int $invoiceId 請求書ID
     */
    private function updateInvoicePaymentStatus($invoiceId) {
        // 請求書の総額と支払い済み額を計算
        $sql = "
            SELECT 
                i.total_amount,
                COALESCE(SUM(p.amount), 0) as paid_amount
            FROM invoices i
            LEFT JOIN payments p ON i.id = p.invoice_id
            WHERE i.id = ?
            GROUP BY i.id, i.total_amount
        ";
        
        $stmt = $this->db->query($sql, [$invoiceId]);
        $result = $stmt->fetch();
        
        if ($result) {
            $status = 'unpaid';
            if ($result['paid_amount'] >= $result['total_amount']) {
                $status = 'paid';
            } elseif ($result['paid_amount'] > 0) {
                $status = 'partial';
            }
            
            $updateSql = "UPDATE invoices SET payment_status = ? WHERE id = ?";
            $this->db->query($updateSql, [$status, $invoiceId]);
        }
    }

    /**
     * 支払い履歴取得
     * @param array $filters フィルター条件
     * @return array 支払い履歴
     */
    public function getPaymentHistory($filters = []) {
        $sql = "
            SELECT 
                p.*,
                i.invoice_number,
                i.company_name,
                i.total_amount as invoice_total
            FROM payments p
            LEFT JOIN invoices i ON p.invoice_id = i.id
            WHERE 1=1
        ";
        $params = [];
        
        // フィルター条件の追加
        if (!empty($filters['start_date'])) {
            $sql .= " AND p.payment_date >= ?";
            $params[] = $filters['start_date'];
        }
        
        if (!empty($filters['end_date'])) {
            $sql .= " AND p.payment_date <= ?";
            $params[] = $filters['end_date'];
        }
        
        if (!empty($filters['payment_method'])) {
            $sql .= " AND p.payment_method = ?";
            $params[] = $filters['payment_method'];
        }
        
        $sql .= " ORDER BY p.payment_date DESC, p.created_at DESC";
        
        $stmt = $this->db->query($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * 未回収金額の取得
     * @return array 未回収金額情報
     */
    public function getOutstandingAmounts() {
        $sql = "
            SELECT 
                i.id,
                i.invoice_number,
                i.company_name,
                i.total_amount,
                COALESCE(SUM(p.amount), 0) as paid_amount,
                (i.total_amount - COALESCE(SUM(p.amount), 0)) as outstanding_amount,
                i.due_date,
                CASE 
                    WHEN i.due_date < CURDATE() THEN '期限超過'
                    WHEN i.due_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN '期限間近'
                    ELSE '正常'
                END as status
            FROM invoices i
            LEFT JOIN payments p ON i.id = p.invoice_id
            WHERE i.payment_status != 'paid'
            GROUP BY i.id
            HAVING outstanding_amount > 0
            ORDER BY i.due_date ASC
        ";
        
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }

    // 個別支払い方法の処理メソッド
    private function processCashPayment($paymentData) {
        $paymentData['transaction_fee'] = 0; // 現金は手数料なし
        return $this->recordPayment($paymentData);
    }

    private function processBankTransferPayment($paymentData) {
        // 振込手数料を考慮
        $paymentData['transaction_fee'] = $paymentData['transaction_fee'] ?? 220;
        return $this->recordPayment($paymentData);
    }

    private function processAccountDebitPayment($paymentData) {
        // 口座引き落とし手数料
        $paymentData['transaction_fee'] = $paymentData['transaction_fee'] ?? 110;
        return $this->recordPayment($paymentData);
    }

    /**
     * 接続確認メソッド
     * @return bool データベース接続状況
     */
    public function isConnected() {
        try {
            $stmt = $this->db->query("SELECT 1");
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}
?>
