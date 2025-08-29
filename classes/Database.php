<?php
/**
 * Database接続クラス (Singleton + prepare()メソッド対応版)
 * 請求書生成機能で必要なprepare()メソッドを追加
 */
class Database {
    private static $instance = null;
    private $host;
    private $database;
    private $username;
    private $password;
    private $pdo;
    
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
     * プライベートコンストラクタ（Singleton強制）
     */
    private function __construct() {
        $this->host = DB_HOST;
        $this->database = DB_NAME;
        $this->username = DB_USER;
        $this->password = DB_PASS;
        
        $this->connect();
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
        ];
        
        try {
            $this->pdo = new PDO($dsn, $this->username, $this->password, $options);
        } catch (PDOException $e) {
            throw new Exception("データベース接続エラー: " . $e->getMessage());
        }
    }
    
    /**
     * 既存のqueryメソッド（互換性維持）
     */
    public function query($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
    
    /**
     * NEW: prepareメソッド追加（SmileyInvoiceGenerator対応）
     */
    public function prepare($sql) {
        return $this->pdo->prepare($sql);
    }
    
    /**
     * 直接PDOオブジェクト取得（必要に応じて）
     */
    public function getPdo() {
        return $this->pdo;
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
    
    /**
     * テーブル存在確認（INFORMATION_SCHEMA使用）
     */
    public function tableExists($tableName) {
        $sql = "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES 
                WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?";
        $stmt = $this->query($sql, [$this->database, $tableName]);
        return $stmt->fetchColumn() > 0;
    }
    
    /**
     * 接続テスト
     */
    public function testConnection() {
        try {
            $stmt = $this->query("SELECT 1");
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}
?>
