<?php
require_once __DIR__ . '/../config/database.php';

/**
 * データベース接続クラス（tableExists修正版）
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
     * テーブル存在確認（修正版）
     * 元の問題: SHOW TABLESの結果処理が不正確だった
     */
    public function tableExists($tableName) {
        try {
            if (!$this->connected) {
                return false;
            }
            
            // 方法1: INFORMATION_SCHEMA を使用（より確実）
            $sql = "SELECT COUNT(*) as table_count 
                   FROM INFORMATION_SCHEMA.TABLES 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = ?";
            
            $stmt = $this->query($sql, [$tableName]);
            $result = $stmt->fetch();
            
            $exists = ($result && $result['table_count'] > 0);
            
            // デバッグログ
            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                error_log("Table check: {$tableName} = " . ($exists ? 'EXISTS' : 'NOT EXISTS'));
            }
            
            return $exists;
            
        } catch (Exception $e) {
            // エラーの場合はfalseを返すが、ログに記録
            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                error_log("Table exists check error for {$tableName}: " . $e->getMessage());
            }
            return false;
        }
    }
    
    /**
     * 全テーブル一覧取得（デバッグ用）
     */
    public function getAllTables() {
        try {
            if (!$this->connected) {
                return [];
            }
            
            $sql = "SELECT TABLE_NAME 
                   FROM INFORMATION_SCHEMA.TABLES 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   ORDER BY TABLE_NAME";
            
            $stmt = $this->query($sql);
            $results = $stmt->fetchAll();
            
            return array_column($results, 'TABLE_NAME');
            
        } catch (Exception $e) {
            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                error_log("Get all tables error: " . $e->getMessage());
            }
            return [];
        }
    }
    
    /**
     * テーブル詳細情報取得（デバッグ用）
     */
    public function getTableInfo($tableName) {
        try {
            if (!$this->connected) {
                return null;
            }
            
            // テーブル存在確認
            if (!$this->tableExists($tableName)) {
                return null;
            }
            
            // レコード数取得
            $countSql = "SELECT COUNT(*) as record_count FROM `{$tableName}`";
            $countStmt = $this->query($countSql);
            $countResult = $countStmt->fetch();
            
            // カラム情報取得
            $columnSql = "SHOW COLUMNS FROM `{$tableName}`";
            $columnStmt = $this->query($columnSql);
            $columns = $columnStmt->fetchAll();
            
            return [
                'table_name' => $tableName,
                'record_count' => $countResult['record_count'],
                'column_count' => count($columns),
                'columns' => $columns
            ];
            
        } catch (Exception $e) {
            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                error_log("Get table info error for {$tableName}: " . $e->getMessage());
            }
            return null;
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
