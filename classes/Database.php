<?php
require_once __DIR__ . '/../config/database.php';

/**
 * データベース接続クラス（Singleton パターン対応版）
 * 既存機能を維持しつつ getInstance() メソッドを追加
 */
class Database {
    private static $instance = null;
    private $pdo;
    private $connected = false;
    private $lastError = '';
    
    /**
     * Singleton パターン - インスタンス取得
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * コンストラクタ（private でSingleton パターンを強制）
     */
    private function __construct() {
        // データベース設定が定義されているかチェック
        if (!defined('DB_HOST') || !defined('DB_NAME') || !defined('DB_USER')) {
            $this->lastError = 'データベース設定が不完全です';
            return;
        }
        
        $this->connect();
    }
    
    /**
     * データベースに接続
     */
    private function connect() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            $this->connected = true;
            
            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                error_log("Database connected successfully");
            }
            
        } catch (PDOException $e) {
            $this->connected = false;
            $this->lastError = $e->getMessage();
            
            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                error_log("Database connection failed: " . $e->getMessage());
            }
        }
    }
    
    /**
     * PDO接続オブジェクト取得
     */
    public function getConnection() {
        return $this->pdo;
    }
    
    /**
     * 接続状態を確認
     */
    public function isConnected() {
        return $this->connected;
    }
    
    /**
     * 接続テスト（SmileyCSVImporter用）
     */
    public function testConnection() {
        try {
            if (!$this->connected) {
                return false;
            }
            
            $stmt = $this->pdo->query("SELECT 1");
            return $stmt !== false;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * 最後のエラーメッセージを取得
     */
    public function getLastError() {
        return $this->lastError;
    }
    
    /**
     * データベース存在チェック
     */
    public function checkDatabase() {
        try {
            if (!$this->connected) {
                return [
                    'success' => false,
                    'database' => null,
                    'message' => $this->lastError
                ];
            }
            
            $result = $this->pdo->query("SELECT DATABASE() as current_db")->fetch();
            return [
                'success' => true,
                'database' => $result['current_db'],
                'message' => 'データベース接続成功'
            ];
        } catch (PDOException $e) {
            return [
                'success' => false,
                'database' => null,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * システム情報取得
     */
    public function getSystemInfo() {
        try {
            if (!$this->connected) {
                return ['error' => $this->lastError];
            }
            
            $version = $this->pdo->query("SELECT VERSION() as version")->fetch();
            return [
                'mysql_version' => $version['version'],
                'connection_status' => 'Connected'
            ];
        } catch (PDOException $e) {
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * クエリ実行（SmileyCSVImporter用）
     */
    public function query($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            throw new Exception("Database query error: " . $e->getMessage());
        }
    }
    
    /**
     * 単一行取得
     */
    public function fetchOne($sql, $params = []) {
        try {
            $stmt = $this->query($sql, $params);
            return $stmt->fetch();
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    /**
     * 複数行取得
     */
    public function fetchAll($sql, $params = []) {
        try {
            $stmt = $this->query($sql, $params);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            throw $e;
        }
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
     * 最後のINSERT ID取得
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
     * クローン禁止（Singleton パターン）
     */
    private function __clone() {}
    
    /**
     * アンシリアライズ禁止（Singleton パターン）
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}
?>
