<?php
/**
 * Database.php - 簡潔修正版
 * 構文エラー完全修正・必須メソッドのみ実装
 * 
 * @version 2.2
 * @date 2025-08-31
 */

class Database {
    private static $instance = null;
    private $pdo;
    private $host;
    private $database;
    private $username;
    private $password;
    
    // Singletonパターン: インスタンス取得
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    // コンストラクタをprivateに（Singletonパターン）
    private function __construct() {
        $this->host = DB_HOST;
        $this->database = DB_NAME;
        $this->username = DB_USER;
        $this->password = DB_PASS;
        
        $this->connect();
    }
    
    // クローンを防ぐ
    private function __clone() {}
    
    /**
     * データベース接続
     */
    private function connect() {
        $dsn = "mysql:host={$this->host};dbname={$this->database};charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ];
        
        try {
            $this->pdo = new PDO($dsn, $this->username, $this->password, $options);
        } catch (PDOException $e) {
            error_log("データベース接続エラー: " . $e->getMessage());
            throw new Exception("データベース接続に失敗しました");
        }
    }
    
    /**
     * 単一行を取得
     */
    public function fetchOne($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: null;
        } catch (PDOException $e) {
            error_log("fetchOne エラー: " . $e->getMessage() . " SQL: " . $sql);
            throw new Exception("データの取得に失敗しました");
        }
    }
    
    /**
     * 複数行を取得
     */
    public function fetchAll($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return $result ?: [];
        } catch (PDOException $e) {
            error_log("fetchAll エラー: " . $e->getMessage() . " SQL: " . $sql);
            throw new Exception("データの取得に失敗しました");
        }
    }
    
    /**
     * SQL実行
     */
    public function execute($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("execute エラー: " . $e->getMessage() . " SQL: " . $sql);
            throw new Exception("SQLの実行に失敗しました");
        }
    }
    
    /**
     * クエリ実行
     */
    public function query($sql) {
        try {
            return $this->pdo->query($sql);
        } catch (PDOException $e) {
            error_log("query エラー: " . $e->getMessage() . " SQL: " . $sql);
            throw new Exception("クエリの実行に失敗しました");
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
        return $this->pdo->beginTransaction();
    }
    
    /**
     * トランザクションコミット
     */
    public function commit() {
        return $this->pdo->commit();
    }
    
    /**
     * トランザクションロールバック
     */
    public function rollback() {
        return $this->pdo->rollBack();
    }
    
    /**
     * テーブル存在確認
     */
    public function tableExists($tableName) {
        try {
            $sql = "SELECT COUNT(*) as count 
                    FROM INFORMATION_SCHEMA.TABLES 
                    WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?";
            $result = $this->fetchOne($sql, [$this->database, $tableName]);
            return $result && $result['count'] > 0;
        } catch (Exception $e) {
            error_log("tableExists エラー: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 接続テスト
     */
    public function testConnection() {
        try {
            $result = $this->fetchOne("SELECT 1 as test");
            return $result && $result['test'] == 1;
        } catch (Exception $e) {
            error_log("testConnection エラー: " . $e->getMessage());
            return false;
        }
    }
}
?>
