<?php
/**
 * 強制修正版 Database.php - 完全新規作成
 * 
 * 戦略: 古いコードを完全に排除し、全く新しいアプローチで実装
 * 48行目のエラーを完全に回避するため、構造を根本的に変更
 * 
 * @version 6.0.0 - FORCE_FIX
 * @date 2025-09-10 23:20:00
 */

class Database {
    private static $instance = null;
    private $pdo = null;
    private $connectionStatus = false;
    
    // エラーの原因となった testConnection() を完全に削除
    // 代わりに、より安全な初期化方式を採用
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->initializeConnection();
    }
    
    /**
     * 新しい初期化方式（テスト用SQLを一切使用しない）
     */
    private function initializeConnection() {
        try {
            // 基本的な接続のみ実行
            $dsn = sprintf(
                "mysql:host=%s;dbname=%s;charset=utf8mb4", 
                DB_HOST, 
                DB_NAME
            );
            
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
            
            // 接続成功フラグのみ設定（テストクエリは実行しない）
            $this->connectionStatus = true;
            
        } catch (PDOException $e) {
            $this->connectionStatus = false;
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }
    
    /**
     * 接続状態確認（シンプル版）
     */
    public function isConnected() {
        return $this->connectionStatus && $this->pdo !== null;
    }
    
    /**
     * クエリ実行（基本機能のみ）
     */
    public function query($sql, $params = []) {
        if (!$this->isConnected()) {
            throw new Exception("Database not connected");
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
    
    /**
     * 単一行取得
     */
    public function fetchOne($sql, $params = []) {
        return $this->query($sql, $params)->fetch();
    }
    
    /**
     * 全行取得
     */
    public function fetchAll($sql, $params = []) {
        return $this->query($sql, $params)->fetchAll();
    }
    
    /**
     * 最後の挿入ID
     */
    public function lastInsertId() {
        return $this->pdo->lastInsertId();
    }
    
    /**
     * トランザクション管理
     */
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
     * PDO直接アクセス
     */
    public function getConnection() {
        return $this->pdo;
    }
    
    /**
     * テーブル存在確認（問題のあるクエリを使用しない）
     */
    public function tableExists($tableName) {
        try {
            $stmt = $this->query("SHOW TABLES LIKE ?", [$tableName]);
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * システム情報（安全版 - 複雑なクエリを避ける）
     */
    public function getSystemInfo() {
        return [
            'connected' => $this->isConnected(),
            'host' => DB_HOST,
            'database' => DB_NAME,
            'version' => '6.0.0-FORCE_FIX'
        ];
    }
    
    // Singleton 保護
    private function __clone() {}
    public function __wakeup() {}
}

/*
 * 注意: この版では以下を完全に削除しました：
 * - testConnection() メソッド
 * - NOW(), DATABASE(), VERSION() などのSQL関数
 * - 複雑なエラーハンドリング
 * - 48行目付近の問題コード
 * 
 * 目的: まず動作させることを最優先とし、
 *       その後で機能を段階的に追加する。
 */
?>
