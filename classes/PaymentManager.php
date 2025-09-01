<?php
// classes/PaymentManager.php に追加/更新する内容

class PaymentManager {
    private $db;

    public function __construct() {
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
     * 基本の支払い記録処理（既存メソッドと想定）
     */
    public function recordPayment($paymentData) {
        // 既存の支払い記録処理
        // 実際の実装は既存のPaymentManagerに依存
        return [
            'success' => true,
            'message' => '支払いを記録しました',
            'payment_id' => time() // 仮の実装
        ];
    }

    // 他の支払い方法用メソッドも同様に定義...
    private function processCashPayment($paymentData) {
        return $this->recordPayment($paymentData);
    }

    private function processBankTransferPayment($paymentData) {
        return $this->recordPayment($paymentData);
    }

    private function processAccountDebitPayment($paymentData) {
        return $this->recordPayment($paymentData);
    }
}
?>
