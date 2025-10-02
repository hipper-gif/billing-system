<?php
/**
 * config/database.php - Database完全統一版
 * 
 * 🎯 設計方針:
 * - 全クラスが使用する全メソッドを網羅
 * - 仕様書の「自己完結原則」完全準拠
 * - メソッド不整合の完全排除
 * - エックスサーバー最適化
 * 
 * @version 5.0 - 完全統一版
 * @date 2025-10-02
 */

// 🔧 データベース設定値
define('DB_HOST', 'localhost');
define('DB_NAME', 'twinklemark_billing');  
define('DB_USER', 'twinklemark_bill');
define('DB_PASS', 'Smiley2525');
define('DB_CHARSET', 'utf8mb4');

// 🌍 環境設定
define('ENVIRONMENT', 'production'); // production, development, testing
define('DEBUG_MODE', ENVIRONMENT === 'development');

/**
 * Database クラス - 完全統一版
 * 
 * 🏆 網羅するメソッド:
 * 1. getInstance() - Singleton
 * 2. getConnection() - PDO取得（SmileyCSVImporter用）
 * 3. query() - 汎用クエリ実行
 * 4. fetchAll() - 全行取得
 * 5. fetch() - 1行取得
 * 6. fetchColumn() - 単一値取得
 * 7. execute() - INSERT/UPDATE/DELETE
 * 8. lastInsertId() - 最終挿入ID
 * 9. beginTransaction() - トランザクション開始
 * 10. commit() - コミット
 * 11. rollback() - ロールバック
 * 12. tableExists() - テーブル存在確認
 * 13. getTableInfo() - テーブル情報取得
 * 14. testConnection() - 接続テスト
 * 15. getDatabaseStats() - DB統計
 * 16. getDebugInfo() - デバッグ情報
 */
class Database {
    private static $instance = null;
    private $pdo;
    
    // ========================================
    // Singleton パターン
    // ========================================
    
    /**
     * インスタンス取得（Singleton）
     * @return Database
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * プライベートコンストラクタ
     */
    private function __construct() {
        $this->connect();
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
    
    // ========================================
    // データベース接続
    // ========================================
    
    /**
     * データベース接続
     */
    private function connect() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            
            // エックスサーバー最適化オプション
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
            
            // タイムゾーン設定
            $this->pdo->exec("SET time_zone = '+09:00'");
            
        } catch (PDOException $e) {
            error_log("Database Connection Error: " . $e->getMessage());
            
            if (DEBUG_MODE) {
                throw new Exception("データベース接続エラー: " . $e->getMessage());
            } else {
                throw new Exception("データベース接続に失敗しました");
            }
        }
    }
    
    // ========================================
    // PDO直接アクセス（SmileyCSVImporter用）
    // ========================================
    
    /**
     * PDO接続オブジェクト取得
     * 
     * 用途: SmileyCSVImporterで直接PDOを使用
     * @return PDO
     */
    public function getConnection() {
        return $this->pdo;
    }
    
    // ========================================
    // クエリ実行系メソッド
    // ========================================
    
    /**
     * SQLクエリ実行（プリペアドステートメント）
     * 
     * @param string $sql SQL文
     * @param array $params パラメータ配列
     * @return PDOStatement
     * @throws Exception
     */
    public function query($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Database Query Error: " . $e->getMessage() . " | SQL: " . $sql . " | Params: " . json_encode($params));
            
            if (DEBUG_MODE) {
                throw new Exception("クエリエラー: " . $e->getMessage() . " | SQL: " . $sql);
            } else {
                throw new Exception("データベース処理でエラーが発生しました");
            }
        }
    }
    
    /**
     * 全行取得
     * 
     * @param string $sql SQL文
     * @param array $params パラメータ配列
     * @return array 結果の配列
     */
    public function fetchAll($sql, $params = []) {
        return $this->query($sql, $params)->fetchAll();
    }
    
    /**
     * 1行取得
     * 
     * @param string $sql SQL文
     * @param array $params パラメータ配列
     * @return array|false 結果の連想配列、またはfalse
     */
    public function fetch($sql, $params = []) {
        return $this->query($sql, $params)->fetch();
    }
    
    /**
     * 単一値取得
     * 
     * @param string $sql SQL文
     * @param array $params パラメータ配列
     * @return mixed 単一値
     */
    public function fetchColumn($sql, $params = []) {
        return $this->query($sql, $params)->fetchColumn();
    }
    
    /**
     * INSERT・UPDATE・DELETE実行
     * 
     * @param string $sql SQL文
     * @param array $params パラメータ配列
     * @return int 影響を受けた行数
     */
    public function execute($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }
    
    // ========================================
    // トランザクション管理
    // ========================================
    
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
     * 最後のINSERT ID取得
     * @return string
     */
    public function lastInsertId() {
        return $this->pdo->lastInsertId();
    }
    
    // ========================================
    // ユーティリティメソッド
    // ========================================
    
    /**
     * テーブル存在確認
     * 
     * @param string $tableName テーブル名
     * @return bool 存在する場合true
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
     * 
     * @param string $tableName テーブル名
     * @return array|null カラム情報の配列
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
                        COLUMN_KEY as column_key,
                        EXTRA as extra
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
     * @return bool 接続成功の場合true
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
     * @return array 統計情報の配列
     */
    public function getDatabaseStats() {
        try {
            $stats = [];
            
            // テーブル一覧・行数取得
            $sql = "SELECT TABLE_NAME, TABLE_ROWS, 
                           ROUND(DATA_LENGTH / 1024 / 1024, 2) as size_mb
                    FROM INFORMATION_SCHEMA.TABLES 
                    WHERE TABLE_SCHEMA = ?
                    ORDER BY TABLE_NAME";
            $tables = $this->fetchAll($sql, [DB_NAME]);
            
            $stats['database_name'] = DB_NAME;
            $stats['tables'] = $tables;
            $stats['total_tables'] = count($tables);
            $stats['total_rows'] = array_sum(array_column($tables, 'TABLE_ROWS'));
            $stats['total_size_mb'] = array_sum(array_column($tables, 'size_mb'));
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
     * デバッグ情報取得
     * @return array デバッグ情報の配列
     */
    public function getDebugInfo() {
        try {
            return [
                'environment' => ENVIRONMENT,
                'debug_mode' => DEBUG_MODE,
                'db_host' => DB_HOST,
                'db_name' => DB_NAME,
                'db_user' => DB_USER,
                'db_charset' => DB_CHARSET,
                'connection_test' => $this->testConnection(),
                'php_version' => PHP_VERSION,
                'pdo_version' => $this->pdo->getAttribute(PDO::ATTR_SERVER_VERSION),
                'available_methods' => get_class_methods($this),
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
}

// ========================================
// 初期化確認
// ========================================

if (class_exists('Database')) {
    if (DEBUG_MODE) {
        error_log("✅ Database class (v5.0 Unified) loaded successfully");
    }
} else {
    error_log("❌ Database class loading failed");
}
?>
