<?php
/**
 * Database接続クラス (Singleton対応 + isConnected()メソッド追加版)
 * CSVインポートエラー修復対応
 */
class Database {
    private static $instance = null;
    private $pdo;
    
    /**
     * コンストラクタ (private)
     */
    private function __construct() {
        $this->connect();
    }
    
    /**
     * Singleton インスタンス取得
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * データベース接続
     */
    private function connect() {
        try {
            $host = defined('DB_HOST') ? DB_HOST : 'mysql1.php.xserver.jp';
            $dbname = defined('DB_NAME') ? DB_NAME : 'twinklemark_billing';
            $username = defined('DB_USER') ? DB_USER : 'twinklemark_billing';
            $password = defined('DB_PASS') ? DB_PASS : 'billing2025';
            
            $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ];
            
            $this->pdo = new PDO($dsn, $username, $password, $options);
            
        } catch (PDOException $e) {
            throw new Exception("データベース接続エラー: " . $e->getMessage());
        }
    }
    
    /**
     * 🚨 緊急追加: 接続状態確認メソッド
     * import.phpのエラー解決用
     */
    public function isConnected() {
        try {
            if ($this->pdo === null) {
                return false;
            }
            
            // 接続テスト実行
            $stmt = $this->pdo->query('SELECT 1');
            return $stmt !== false;
            
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * 接続テスト（詳細版）
     */
    public function testConnection() {
        try {
            $start = microtime(true);
            $result = $this->pdo->query('SELECT 1 as test');
            $end = microtime(true);
            
            return [
                'connected' => true,
                'test_result' => $result->fetch(),
                'response_time_ms' => round(($end - $start) * 1000, 2)
            ];
            
        } catch (Exception $e) {
            return [
                'connected' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * クエリ実行
     */
    public function query($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            throw new Exception("クエリ実行エラー: " . $e->getMessage());
        }
    }
    
    /**
     * 1件取得
     */
    public function fetchOne($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }
    
    /**
     * 複数件取得
     */
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * テーブル存在確認
     */
    public function tableExists($tableName) {
        try {
            $sql = "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES 
                    WHERE TABLE_SCHEMA = DATABASE() 
                    AND TABLE_NAME = ?";
            $stmt = $this->query($sql, [$tableName]);
            return $stmt->fetchColumn() > 0;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * テーブル情報取得
     */
    public function getTableInfo($tableName) {
        if (!$this->tableExists($tableName)) {
            return null;
        }
        
        $sql = "SHOW COLUMNS FROM `{$tableName}`";
        $stmt = $this->query($sql);
        return $stmt->fetchAll();
    }
    
    /**
     * 最後の挿入ID
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
     * レコード数取得
     */
    public function getRecordCount($tableName, $conditions = []) {
        $sql = "SELECT COUNT(*) FROM `{$tableName}`";
        $params = [];
        
        if (!empty($conditions)) {
            $whereClause = [];
            foreach ($conditions as $field => $value) {
                $whereClause[] = "`{$field}` = ?";
                $params[] = $value;
            }
            $sql .= " WHERE " . implode(' AND ', $whereClause);
        }
        
        $stmt = $this->query($sql, $params);
        return $stmt->fetchColumn();
    }
    
    /**
     * システム状態取得
     */
    public function getSystemStatus() {
        return [
            'connected' => $this->isConnected(),
            'connection_test' => $this->testConnection(),
            'required_tables' => $this->checkRequiredTables(),
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * 必要テーブル存在確認
     */
    private function checkRequiredTables() {
        $requiredTables = [
            'companies', 'departments', 'users', 'products', 
            'orders', 'invoices', 'invoice_details', 'payments', 
            'receipts', 'import_logs'
        ];
        
        $results = [];
        foreach ($requiredTables as $table) {
            $results[$table] = $this->tableExists($table);
        }
        
        return $results;
    }
}
?>
