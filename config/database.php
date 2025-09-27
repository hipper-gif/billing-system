<?php
/**
 * config/database.php - Database統合版
 * 
 * 🎯 設計方針:
 * - 設定値とDatabaseクラスを統合
 * - Singletonパターン完全実装
 * - クラス重複問題の根本解決
 * - エックスサーバー最適化
 */

// 🔧 データベース設定値
define('DB_HOST', 'mysql8005.xserver.jp');
define('DB_NAME', 'twinklemark_billing');  
define('DB_USER', 'twinklemark_bill');
define('DB_PASS', 'Smiley2525');
define('DB_CHARSET', 'utf8mb4');

// 🌍 環境設定
define('ENVIRONMENT', 'production'); // production, development, testing

/**
 * Database クラス - Singleton統合版
 * 
 * 🏆 機能:
 * - Singletonパターンでインスタンス管理
 * - エックスサーバー最適化設定
 * - 包括的エラーハンドリング
 * - セキュリティ強化
 */
class Database {
    private static $instance = null;
    private $pdo;
    
    /**
     * Singletonパターン - インスタンス取得
     * @return Database
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * プライベートコンストラクタ（Singleton強制）
     */
    private function __construct() {
        $this->connect();
    }
    
    /**
     * データベース接続
     */
    private function connect() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            
            // 🔧 エックスサーバー最適化オプション
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET . " COLLATE " . DB_CHARSET . "_unicode_ci",
                PDO::ATTR_TIMEOUT => 30,
                PDO::ATTR_PERSISTENT => false,
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true
            ];
            
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            
            // 🎯 環境別設定
            if (ENVIRONMENT === 'development') {
                $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            } else {
                // 本番環境ではエラー詳細を隠す
                error_reporting(0);
                ini_set('display_errors', 0);
            }
            
        } catch (PDOException $e) {
            error_log("Database Connection Error: " . $e->getMessage());
            
            if (ENVIRONMENT === 'development') {
                throw new Exception("データベース接続エラー: " . $e->getMessage());
            } else {
                throw new Exception("データベース接続に失敗しました");
            }
        }
    }
    
    /**
     * SQLクエリ実行
     * @param string $sql
     * @param array $params
     * @return PDOStatement
     */
    public function query($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Database Query Error: " . $e->getMessage() . " | SQL: " . $sql . " | Params: " . json_encode($params));
            
            if (ENVIRONMENT === 'development') {
                throw new Exception("クエリエラー: " . $e->getMessage() . " | SQL: " . $sql);
            } else {
                throw new Exception("データベース処理でエラーが発生しました");
            }
        }
    }
    
    /**
     * 全行取得
     * @param string $sql
     * @param array $params
     * @return array
     */
    public function fetchAll($sql, $params = []) {
        return $this->query($sql, $params)->fetchAll();
    }
    
    /**
     * 1行取得
     * @param string $sql
     * @param array $params
     * @return array|false
     */
    public function fetch($sql, $params = []) {
        return $this->query($sql, $params)->fetch();
    }
    
    /**
     * 単一値取得
     * @param string $sql
     * @param array $params
     * @return mixed
     */
    public function fetchColumn($sql, $params = []) {
        return $this->query($sql, $params)->fetchColumn();
    }
    
    /**
     * INSERT・UPDATE・DELETE実行
     * @param string $sql
     * @param array $params
     * @return int 影響行数
     */
    public function execute($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }
    
    /**
     * 最後のINSERT ID取得
     * @return string
     */
    public function lastInsertId() {
        return $this->pdo->lastInsertId();
    }
    
    /**
     * トランザクション開始
     * @return bool
     */
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }
    
    /**
     * コミット
     * @return bool
     */
    public function commit() {
        return $this->pdo->commit();
    }
    
    /**
     * ロールバック
     * @return bool
     */
    public function rollback() {
        return $this->pdo->rollback();
    }
    
    /**
     * テーブル存在確認
     * @param string $tableName
     * @return bool
     */
    public function tableExists($tableName) {
        try {
            $sql = "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES 
                    WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?";
            $count = $this->fetchColumn($sql, [DB_NAME, $tableName]);
            return $count > 0;
        } catch (Exception $e) {
            error_log("Table exists check error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * テーブル情報取得
     * @param string $tableName
     * @return array|null
     */
    public function getTableInfo($tableName) {
        try {
            if (!$this->tableExists($tableName)) {
                return null;
            }
            
            $sql = "SELECT 
                        COLUMN_NAME as column_name,
                        DATA_TYPE as data_type,
                        IS_NULLABLE as is_nullable,
                        COLUMN_DEFAULT as column_default,
                        COLUMN_KEY as column_key
                    FROM INFORMATION_SCHEMA.COLUMNS 
                    WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
                    ORDER BY ORDINAL_POSITION";
            
            return $this->fetchAll($sql, [DB_NAME, $tableName]);
        } catch (Exception $e) {
            error_log("Get table info error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * 接続テスト
     * @return bool
     */
    public function testConnection() {
        try {
            $stmt = $this->pdo->query("SELECT 1 as test");
            $result = $stmt->fetch();
            return $result['test'] == 1;
        } catch (Exception $e) {
            error_log("Connection test error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * データベース統計取得
     * @return array
     */
    public function getDatabaseStats() {
        try {
            $stats = [];
            
            // テーブル一覧・行数取得
            $sql = "SELECT TABLE_NAME, TABLE_ROWS 
                    FROM INFORMATION_SCHEMA.TABLES 
                    WHERE TABLE_SCHEMA = ?
                    ORDER BY TABLE_NAME";
            $tables = $this->fetchAll($sql, [DB_NAME]);
            
            $stats['database_name'] = DB_NAME;
            $stats['tables'] = $tables;
            $stats['total_tables'] = count($tables);
            $stats['total_rows'] = array_sum(array_column($tables, 'TABLE_ROWS'));
            $stats['connection_test'] = $this->testConnection();
            $stats['timestamp'] = date('Y-m-d H:i:s');
            
            return $stats;
        } catch (Exception $e) {
            error_log("Get database stats error: " . $e->getMessage());
            return [
                'error' => $e->getMessage(),
                'connection_test' => false,
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
    }
    
    /**
     * 環境確認用デバッグ情報取得
     * @return array
     */
    public function getDebugInfo() {
        try {
            return [
                'environment' => ENVIRONMENT,
                'db_host' => DB_HOST,
                'db_name' => DB_NAME,
                'db_user' => DB_USER,
                'connection_test' => $this->testConnection(),
                'php_version' => PHP_VERSION,
                'pdo_version' => $this->pdo->getAttribute(PDO::ATTR_SERVER_VERSION),
                'charset' => DB_CHARSET,
                'timestamp' => date('Y-m-d H:i:s')
            ];
        } catch (Exception $e) {
            return [
                'error' => $e->getMessage(),
                'environment' => ENVIRONMENT,
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
    }
    
    /**
     * クローン防止
     */
    private function __clone() {}
    
    /**
     * アンシリアライズ防止
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}

// 🎯 統合完了の確認
if (class_exists('Database')) {
    // Database クラスの統合が成功
    if (ENVIRONMENT === 'development') {
        error_log("Database class integrated successfully in config/database.php");
    }
} else {
    error_log("Database class integration failed");
}
?>
