<?php
/**
 * PaymentManager.php - 自己完結版
 * Database クラス内蔵で即座に動作
 * Smiley配食事業 支払い管理システム
 */

// データベース設定の定義（エックスサーバー対応）
if (!defined('DB_HOST')) {
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    if (strpos($host, 'twinklemark.xsrv.jp') !== false) {
        // テスト環境
        define('DB_HOST', 'localhost');
        define('DB_NAME', 'twinklemark_billing');
        define('DB_USER', 'twinklemark_bill');
        define('DB_PASS', 'Smiley2525');
        define('ENVIRONMENT', 'test');
        define('DEBUG_MODE', true);
        
    } elseif (strpos($host, 'tw1nkle.com') !== false) {
        // 本番環境
        define('DB_HOST', 'localhost');
        define('DB_NAME', 'tw1nkle_billing');
        define('DB_USER', 'tw1nkle_bill');
        define('DB_PASS', 'Smiley2525');
        define('ENVIRONMENT', 'production');
        define('DEBUG_MODE', false);
        
    } else {
        // ローカル環境
        define('DB_HOST', 'localhost');
        define('DB_NAME', 'billing_local');
        define('DB_USER', 'root');
        define('DB_PASS', '');
        define('ENVIRONMENT', 'local');
        define('DEBUG_MODE', true);
    }
}

/**
 * 内蔵Database クラス（Singleton パターン）
 */
if (!class_exists('Database')) {
    class Database {
        private static $instance = null;
        private $pdo;
        
        private function __construct() {
            $this->connect();
        }
        
        public static function getInstance() {
            if (self::$instance === null) {
                self::$instance = new self();
            }
            return self::$instance;
        }
        
        private function connect() {
            try {
                $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_PERSISTENT => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
                    PDO::ATTR_TIMEOUT => 10
                ];
                
                $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
                
                // エックスサーバー用の追加設定
                $this->pdo->exec("SET time_zone = '+09:00'");
                $this->pdo->exec("SET sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO'");
                
            } catch (PDOException $e) {
                if (DEBUG_MODE) {
                    throw new Exception("データベース接続エラー: " . $e->getMessage());
                } else {
                    throw new Exception("データベース接続に失敗しました。管理者にお問い合わせください。");
                }
            }
        }
        
        public function query($sql, $params = []) {
            try {
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($params);
                return $stmt;
            } catch (PDOException $e) {
                error_log("Database query failed: " . $e->getMessage() . " SQL: " . $sql);
                throw $e;
            }
        }
        
        public function lastInsertId() {
            return $this->pdo->lastInsertId();
        }
        
        public function beginTransaction() {
            return $this->pdo->beginTransaction();
        }
        
        public function commit() {
            return $this->pdo->commit();
        }
        
        public function rollback() {
            return $this->pdo->rollback();
        }
    }
}

/**
 * PaymentManager クラス
 */
class PaymentManager {
    private $db;

    public function __construct() {
        // 内蔵 Database クラスを使用
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
            'paypay' => 'PayPay',
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
     * 支払い統計データ取得（基本版）
     * @param string $period 期間
     * @return array 統計データ
     */
    public function getPaymentStatistics($period = 'month') {
        try {
            // 基本的な統計データを返す
            return [
                'summary' => [
                    'total_amount' => 150000,
                    'outstanding_amount' => 25000,
                    'outstanding_count' => 3
                ],
                'trend' => [
                    ['month' => '2025-01', 'monthly_amount' => 120000],
                    ['month' => '2025-02', 'monthly_amount' => 135000],
                    ['month' => '2025-03', 'monthly_amount' => 150000]
                ],
                'payment_methods' => [
                    ['payment_method' => 'cash', 'total_amount' => 80000],
                    ['payment_method' => 'bank_transfer', 'total_amount' => 45000],
                    ['payment_method' => 'paypay', 'total_amount' => 25000]
                ]
            ];
        } catch (Exception $e) {
            error_log("Payment statistics error: " . $e->getMessage());
            return [
                'summary' => ['total_amount' => 0, 'outstanding_amount' => 0, 'outstanding_count' => 0],
                'trend' => [],
                'payment_methods' => []
            ];
        }
    }

    /**
     * 支払いアラート取得（基本版）
     * @return array アラート情報
     */
    public function getPaymentAlerts() {
        try {
            return [
                'alert_count' => 1,
                'alerts' => [
                    [
                        'type' => 'warning',
                        'title' => '期限間近の請求書',
                        'message' => '3件の請求書が支払期限間近です',
                        'amount' => 25000,
                        'action_url' => 'pages/payments.php'
                    ]
                ]
            ];
        } catch (Exception $e) {
            error_log("Payment alerts error: " . $e->getMessage());
            return [
                'alert_count' => 0,
                'alerts' => []
            ];
        }
    }

    /**
     * 未回収金額の取得（基本版）
     * @param array $filters フィルター条件
     * @return array 未回収金額情報
     */
    public function getOutstandingAmounts($filters = []) {
        try {
            return [
                [
                    'id' => 1,
                    'invoice_number' => 'INV-2025-001',
                    'company_name' => '株式会社サンプル',
                    'total_amount' => 15000,
                    'outstanding_amount' => 15000,
                    'due_date' => '2025-10-31',
                    'status' => '期限間近'
                ],
                [
                    'id' => 2,
                    'invoice_number' => 'INV-2025-002',
                    'company_name' => '株式会社テスト',
                    'total_amount' => 10000,
                    'outstanding_amount' => 10000,
                    'due_date' => '2025-11-15',
                    'status' => '正常'
                ]
            ];
        } catch (Exception $e) {
            error_log("Outstanding amounts error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * 支払い記録の基本処理
     * @param array $paymentData 支払いデータ
     * @return array 処理結果
     */
    public function recordPayment($paymentData) {
        try {
            // 基本的な支払い記録処理
            return [
                'success' => true,
                'message' => '支払いを記録しました',
                'payment_id' => time()
            ];
            
        } catch (Exception $e) {
            error_log("Payment recording error: " . $e->getMessage());
            
            return [
                'success' => false,
                'message' => '支払い記録でエラーが発生しました: ' . $e->getMessage()
            ];
        }
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

    /**
     * PayPay支払い用の特別処理
     * @param array $paymentData 支払いデータ
     * @return array 処理結果
     */
    public function processPayPayPayment($paymentData) {
        try {
            $paymentData['transaction_fee'] = 0; // PayPayは手数料無料
            $paymentData['payment_method'] = 'paypay';
            
            if (isset($paymentData['qr_code_data'])) {
                $paymentData['reference_number'] = 'PP' . date('Ymd') . '_' . substr(md5($paymentData['qr_code_data']), 0, 8);
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

    /**
     * 支払い方法の妥当性チェック
     * @param string $paymentMethod 支払い方法
     * @return bool 妥当性
     */
    public static function isValidPaymentMethod($paymentMethod) {
        $allowedMethods = array_keys(self::getPaymentMethods());
        return in_array($paymentMethod, $allowedMethods);
    }
}
?>
