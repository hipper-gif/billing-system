<?php
/**
 * Database.php - データベース接続クラス（エックスサーバー対応版）
 * Smiley配食事業システム - 根本解決版
 * 最終更新: 2025年9月3日
 */

class Database {
    private static $instance = null;
    private $pdo;
    private $host;
    private $database;
    private $username;
    private $password;
    private $charset = 'utf8mb4';
    
    /**
     * Singletonパターンでインスタンス取得
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * コンストラクタ - プライベートでSingleton実装
     */
    private function __construct() {
        $this->setDatabaseCredentials();
        $this->connect();
    }
    
    /**
     * 環境に応じたデータベース接続情報設定
     */
    private function setDatabaseCredentials() {
        // 現在のホスト名を取得
        $currentHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
        
        if (strpos($currentHost, 'twinklemark.xsrv.jp') !== false) {
            // テスト環境（twinklemark）- GitHubの既存設定を使用
            $this->host = 'mysql1.xserver.jp';  // GitHubの設定に合わせる
            $this->database = 'twinklemark_billing';
            $this->username = 'twinklemark_billing';  // 実際のユーザー名に修正
            $this->password = 'your_actual_password';  // 実際のパスワード設定が必要
            
        } elseif (strpos($currentHost, 'tw1nkle.com') !== false) {
            // 本番環境（tw1nkle）
            $this->host = 'mysql1.xserver.jp';  // エックスサーバーの正しいホスト名
            $this->database = 'tw1nkle_billing';
            $this->username = 'tw1nkle_db';
            $this->password = 'smiley2024prod';
            
        } else {
            // ローカル開発環境
            $this->host = 'localhost';
            $this->database = 'billing_system_local';
            $this->username = 'root';
            $this->password = '';
        }
        
        // 環境変数からの設定上書き（セキュリティ強化）
        if (defined('DB_HOST')) $this->host = DB_HOST;
        if (defined('DB_NAME')) $this->database = DB_NAME;
        if (defined('DB_USER')) $this->username = DB_USER;
        if (defined('DB_PASS')) $this->password = DB_PASS;
    }
    
    /**
     * データベース接続
     */
    private function connect() {
        try {
            $dsn = "mysql:host={$this->host};dbname={$this->database};charset={$this->charset}";
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$this->charset} COLLATE utf8mb4_unicode_ci"
            ];
            
            $this->pdo = new PDO($dsn, $this->username, $this->password, $options);
            
            // 接続成功ログ（デバッグ時のみ）
            if ($this->isDebugMode()) {
                error_log("Database connected successfully to {$this->host}/{$this->database}");
            }
            
        } catch (PDOException $e) {
            // 接続エラーの詳細ログ
            $errorMsg = "データベース接続エラー: " . $e->getMessage();
            $errorMsg .= "\nHost: {$this->host}";
            $errorMsg .= "\nDatabase: {$this->database}";
            $errorMsg .= "\nUsername: {$this->username}";
            
            error_log($errorMsg);
            
            // 本番環境では詳細を隠す
            if ($this->isProductionMode()) {
                throw new Exception("データベース接続に失敗しました。システム管理者にお問い合わせください。");
            } else {
                throw new Exception($errorMsg);
            }
        }
    }
    
    /**
     * PDOインスタンス取得
     */
    public function getConnection() {
        return $this->pdo;
    }
    
    /**
     * プリペアドステートメント実行
     */
    public function prepare($sql) {
        try {
            return $this->pdo->prepare($sql);
        } catch (PDOException $e) {
            error_log("SQL Prepare Error: " . $e->getMessage() . " | SQL: " . $sql);
            throw new Exception("SQL準備エラー: " . $e->getMessage());
        }
    }
    
    /**
     * クエリ実行
     */
    public function query($sql) {
        try {
            return $this->pdo->query($sql);
        } catch (PDOException $e) {
            error_log("SQL Query Error: " . $e->getMessage() . " | SQL: " . $sql);
            throw new Exception("SQLクエリエラー: " . $e->getMessage());
        }
    }
    
    /**
     * 最後に挿入されたIDを取得
     */
    public function lastInsertId() {
        return $this->pdo->lastInsertId();
    }
    
    /**
     * トランザクション開始
     */
    public function beginTransaction() {
        try {
            return $this->pdo->beginTransaction();
        } catch (PDOException $e) {
            error_log("Transaction Begin Error: " . $e->getMessage());
            throw new Exception("トランザクション開始エラー: " . $e->getMessage());
        }
    }
    
    /**
     * コミット
     */
    public function commit() {
        try {
            return $this->pdo->commit();
        } catch (PDOException $e) {
            error_log("Transaction Commit Error: " . $e->getMessage());
            throw new Exception("トランザクションコミットエラー: " . $e->getMessage());
        }
    }
    
    /**
     * ロールバック
     */
    public function rollback() {
        try {
            return $this->pdo->rollback();
        } catch (PDOException $e) {
            error_log("Transaction Rollback Error: " . $e->getMessage());
            throw new Exception("トランザクションロールバックエラー: " . $e->getMessage());
        }
    }
    
    /**
     * 接続テスト
     */
    public function testConnection() {
        try {
            $stmt = $this->pdo->query('SELECT 1 as test');
            $result = $stmt->fetch();
            return $result['test'] == 1;
        } catch (PDOException $e) {
            error_log("Connection Test Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * データベース情報取得
     */
    public function getDatabaseInfo() {
        try {
            $stmt = $this->pdo->query('SELECT VERSION() as version, DATABASE() as database');
            $result = $stmt->fetch();
            
            return [
                'host' => $this->host,
                'database' => $this->database,
                'username' => $this->username,
                'version' => $result['version'] ?? 'Unknown',
                'current_database' => $result['database'] ?? 'Unknown',
                'charset' => $this->charset,
                'connection_status' => 'Connected'
            ];
        } catch (PDOException $e) {
            return [
                'host' => $this->host,
                'database' => $this->database,
                'username' => $this->username,
                'error' => $e->getMessage(),
                'connection_status' => 'Failed'
            ];
        }
    }
    
    /**
     * テーブル一覧取得
     */
    public function getTables() {
        try {
            $stmt = $this->pdo->query("SHOW TABLES");
            $tables = [];
            while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
                $tables[] = $row[0];
            }
            return $tables;
        } catch (PDOException $e) {
            error_log("Get Tables Error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * テーブルの行数取得
     */
    public function getTableRowCount($tableName) {
        try {
            // SQLインジェクション対策のためテーブル名をサニタイズ
            $tableName = preg_replace('/[^a-zA-Z0-9_]/', '', $tableName);
            $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM `{$tableName}`");
            $result = $stmt->fetch();
            return (int)$result['count'];
        } catch (PDOException $e) {
            error_log("Get Table Row Count Error for {$tableName}: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * セキュリティ：プリペアドステートメント用のエスケープ
     */
    public function quote($string) {
        return $this->pdo->quote($string);
    }
    
    /**
     * デバッグモード判定
     */
    private function isDebugMode() {
        return defined('DEBUG_MODE') && DEBUG_MODE === true;
    }
    
    /**
     * 本番環境判定
     */
    private function isProductionMode() {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return strpos($host, 'tw1nkle.com') !== false;
    }
    
    /**
     * 健全性チェック
     */
    public function healthCheck() {
        $health = [
            'database' => 'OK',
            'connection' => 'OK',
            'tables' => [],
            'errors' => []
        ];
        
        try {
            // 接続テスト
            if (!$this->testConnection()) {
                $health['connection'] = 'FAILED';
                $health['errors'][] = 'Database connection test failed';
            }
            
            // テーブル存在確認
            $requiredTables = [
                'companies', 'departments', 'users', 'orders', 
                'invoices', 'payments', 'receipts'
            ];
            
            $existingTables = $this->getTables();
            
            foreach ($requiredTables as $table) {
                if (in_array($table, $existingTables)) {
                    $rowCount = $this->getTableRowCount($table);
                    $health['tables'][$table] = [
                        'exists' => true,
                        'row_count' => $rowCount
                    ];
                } else {
                    $health['tables'][$table] = [
                        'exists' => false,
                        'row_count' => 0
                    ];
                    $health['errors'][] = "Required table '{$table}' does not exist";
                }
            }
            
            // 全体的な健全性判定
            if (count($health['errors']) > 0) {
                $health['database'] = 'WARNING';
            }
            
        } catch (Exception $e) {
            $health['database'] = 'FAILED';
            $health['connection'] = 'FAILED';
            $health['errors'][] = $e->getMessage();
        }
        
        return $health;
    }
    
    /**
     * クローン禁止（Singletonパターン）
     */
    private function __clone() {}
    
    /**
     * アンシリアライズ禁止（Singletonパターン）
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
    
    /**
     * デストラクタ
     */
    public function __destruct() {
        $this->pdo = null;
    }
}

// データベース接続の互換性関数（既存コードとの互換性）
if (!function_exists('getDatabase')) {
    function getDatabase() {
        return Database::getInstance();
    }
}
?>
