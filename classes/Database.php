<?php
/**
 * Database.php - 統一版
 * Smiley配食事業システム対応
 * 
 * 機能:
 * 1. Singletonパターン対応
 * 2. 通常のコンストラクタ対応
 * 3. 全メソッド統一実装
 * 4. エラーハンドリング強化
 */

require_once __DIR__ . '/../config/database.php';

class Database {
    private static $instance = null;
    private $pdo;
    private $host;
    private $database;
    private $username;
    private $password;
    
    /**
     * Singletonパターン用のプライベートコンストラクタ
     */
    private function __construct() {
        $this->host = DB_HOST;
        $this->database = DB_NAME;
        $this->username = DB_USER;
        $this->password = DB_PASS;
        
        $this->connect();
    }
    
    /**
     * Singletonインスタンス取得
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * ファクトリーメソッド（通常のコンストラクタ代替）
     */
    public static function create() {
        return self::getInstance();
    }
    
    /**
     * データベース接続
     */
    private function connect() {
        $dsn = "mysql:host={$this->host};dbname={$this->database};charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
        ];
        
        try {
            $this->pdo = new PDO($dsn, $this->username, $this->password, $options);
            
            // タイムゾーン設定
            $this->pdo->exec("SET time_zone = '+09:00'");
            
        } catch (PDOException $e) {
            error_log("Database connection error: " . $e->getMessage());
            throw new Exception("データベース接続エラー: " . $e->getMessage());
        }
    }
    
    /**
     * クエリ実行（SELECT）
     */
    public function query($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Query error: " . $e->getMessage() . " SQL: " . $sql);
            throw new Exception("クエリエラー: " . $e->getMessage());
        }
    }
    
    /**
     * プリペアドステートメント準備
     */
    public function prepare($sql) {
        try {
            return $this->pdo->prepare($sql);
        } catch (PDOException $e) {
            error_log("Prepare error: " . $e->getMessage() . " SQL: " . $sql);
            throw new Exception("プリペアドステートメントエラー: " . $e->getMessage());
        }
    }
    
    /**
     * 単一実行（INSERT/UPDATE/DELETE）
     */
    public function execute($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute($params);
            return $result;
        } catch (PDOException $e) {
            error_log("Execute error: " . $e->getMessage() . " SQL: " . $sql);
            throw new Exception("実行エラー: " . $e->getMessage());
        }
    }
    
    /**
     * 単一レコード取得
     */
    public function fetchOne($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }
    
    /**
     * 全レコード取得
     */
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * レコード数取得
     */
    public function count($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
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
        return $this->pdo->beginTransaction();
    }
    
    /**
     * コミット
     */
    public function commit() {
        return $this->pdo->commit();
    }
    
    /**
     * ロールバック
     */
    public function rollback() {
        return $this->pdo->rollback();
    }
    
    /**
     * テーブル存在確認
     */
    public function tableExists($tableName) {
        try {
            $sql = "SHOW TABLES LIKE ?";
            $stmt = $this->query($sql, [$tableName]);
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * 全テーブル一覧取得
     */
    public function getTables() {
        try {
            $stmt = $this->query('SHOW TABLES');
            $tables = [];
            while ($row = $stmt->fetch()) {
                $tables[] = array_values($row)[0];
            }
            return $tables;
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * データベース情報取得
     */
    public function getDatabaseInfo() {
        try {
            $info = [];
            
            // MySQLバージョン
            $stmt = $this->query('SELECT VERSION() as version');
            $result = $stmt->fetch();
            $info['mysql_version'] = $result['version'] ?? 'Unknown';
            
            // データベース名
            $info['database_name'] = $this->database;
            
            // 文字セット
            $stmt = $this->query('SELECT @@character_set_database as charset');
            $result = $stmt->fetch();
            $info['charset'] = $result['charset'] ?? 'Unknown';
            
            // テーブル数
            $tables = $this->getTables();
            $info['table_count'] = count($tables);
            $info['tables'] = $tables;
            
            return $info;
            
        } catch (Exception $e) {
            return [
                'error' => $e->getMessage(),
                'mysql_version' => 'Error',
                'database_name' => $this->database,
                'charset' => 'Unknown',
                'table_count' => 0,
                'tables' => []
            ];
        }
    }
    
    /**
     * 接続テスト
     */
    public function testConnection() {
        try {
            $this->query('SELECT 1');
            return [
                'status' => true,
                'message' => '接続成功',
                'database' => $this->database,
                'host' => $this->host
            ];
        } catch (Exception $e) {
            return [
                'status' => false,
                'message' => '接続失敗: ' . $e->getMessage(),
                'database' => $this->database,
                'host' => $this->host
            ];
        }
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

/**
 * グローバル関数（後方互換性のため）
 */
function getDatabase() {
    return Database::getInstance();
}

/**
 * ファクトリー関数（new Database()の代替）
 */
function createDatabase() {
    return Database::getInstance();
}
?>
