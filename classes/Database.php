<?php
/**
 * 修正版 Database クラス
 * HTTP 500エラー完全解決版
 * 
 * 問題:
 * - getInstance() での接続エラー
 * - 配列アクセス時のWarning
 * - 接続状態の不整合
 * 
 * @version 4.0.0
 * @date 2025-09-10
 */

class Database {
    private static $instance = null;
    private $pdo;
    private $host;
    private $database;
    private $username;
    private $password;
    private $connected = false;
    
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
     * プライベートコンストラクタ（Singleton強制）
     */
    private function __construct() {
        try {
            // 設定値の確実な読み込み
            $this->loadConfig();
            
            // データベース接続実行
            $this->connect();
            
        } catch (Exception $e) {
            // 接続エラーをログに記録
            error_log("Database初期化エラー: " . $e->getMessage());
            throw new Exception("データベース初期化に失敗しました: " . $e->getMessage());
        }
    }
    
    /**
     * 設定読み込み（エラーハンドリング強化）
     */
    private function loadConfig() {
        // 設定定数の確実な確認
        $requiredConstants = ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'];
        $missingConstants = [];
        
        foreach ($requiredConstants as $constant) {
            if (!defined($constant)) {
                $missingConstants[] = $constant;
            }
        }
        
        if (!empty($missingConstants)) {
            throw new Exception("データベース設定が不完全です。未定義: " . implode(', ', $missingConstants));
        }
        
        $this->host = DB_HOST;
        $this->database = DB_NAME;
        $this->username = DB_USER;
        $this->password = DB_PASS;
        
        // 設定値の妥当性チェック
        if (empty($this->host) || empty($this->database) || empty($this->username)) {
            throw new Exception("データベース設定値が空です");
        }
    }
    
    /**
     * データベース接続（エラーハンドリング強化）
     */
    private function connect() {
        try {
            $dsn = "mysql:host={$this->host};dbname={$this->database};charset=utf8mb4";
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
                PDO::ATTR_TIMEOUT => 30,
                PDO::ATTR_PERSISTENT => false
            ];
            
            $this->pdo = new PDO($dsn, $this->username, $this->password, $options);
            $this->connected = true;
            
            // 接続テスト実行
            $this->testConnection();
            
        } catch (PDOException $e) {
            $this->connected = false;
            
            // 詳細エラー情報
            $errorInfo = [
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'host' => $this->host,
                'database' => $this->database,
                'username' => $this->username
            ];
            
            error_log("PDO接続エラー: " . json_encode($errorInfo));
            throw new Exception("データベース接続に失敗しました: " . $e->getMessage());
        }
    }
    
    /**
     * 接続テスト
     */
    private function testConnection() {
        try {
            $stmt = $this->pdo->query("SELECT 1 as test, NOW() as current_time, DATABASE() as db_name");
            $result = $stmt->fetch();
            
            if (!$result || !isset($result['test'])) {
                throw new Exception("接続テストクエリが失敗しました");
            }
            
            return $result;
            
        } catch (Exception $e) {
            $this->connected = false;
            throw new Exception("データベース接続テストに失敗しました: " . $e->getMessage());
        }
    }
    
    /**
     * クエリ実行（エラーハンドリング強化）
     */
    public function query($sql, $params = []) {
        try {
            $this->ensureConnected();
            
            $stmt = $this->pdo->prepare($sql);
            if ($stmt === false) {
                throw new Exception("SQLプリペア失敗: " . $sql);
            }
            
            $success = $stmt->execute($params);
            if ($success === false) {
                $errorInfo = $stmt->errorInfo();
                throw new Exception("クエリ実行失敗: " . $errorInfo[2]);
            }
            
            return $stmt;
            
        } catch (PDOException $e) {
            error_log("クエリエラー: " . $e->getMessage() . " SQL: " . $sql);
            throw new Exception("データベースクエリエラー: " . $e->getMessage());
        }
    }
    
    /**
     * 接続状態確認
     */
    private function ensureConnected() {
        if (!$this->connected || $this->pdo === null) {
            throw new Exception("データベース接続が確立されていません");
        }
        
        // 接続の生存確認
        try {
            $this->pdo->query("SELECT 1");
        } catch (PDOException $e) {
            // 再接続試行
            $this->connected = false;
            $this->connect();
        }
    }
    
    /**
     * 直接接続取得（デバッグ用）
     */
    public function getConnection() {
        $this->ensureConnected();
        return $this->pdo;
    }
    
    /**
     * 接続状態確認
     */
    public function isConnected() {
        return $this->connected && $this->pdo !== null;
    }
    
    /**
     * 最後に挿入されたIDを取得
     */
    public function lastInsertId() {
        $this->ensureConnected();
        return $this->pdo->lastInsertId();
    }
    
    /**
     * トランザクション開始
     */
    public function beginTransaction() {
        $this->ensureConnected();
        return $this->pdo->beginTransaction();
    }
    
    /**
     * トランザクションコミット
     */
    public function commit() {
        $this->ensureConnected();
        return $this->pdo->commit();
    }
    
    /**
     * トランザクションロールバック
     */
    public function rollback() {
        $this->ensureConnected();
        return $this->pdo->rollback();
    }
    
    /**
     * テーブル存在確認（改良版）
     */
    public function tableExists($tableName) {
        try {
            $this->ensureConnected();
            
            $stmt = $this->query(
                "SELECT COUNT(*) as count FROM INFORMATION_SCHEMA.TABLES 
                 WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?",
                [$this->database, $tableName]
            );
            
            $result = $stmt->fetch();
            return $result && intval($result['count']) > 0;
            
        } catch (Exception $e) {
            error_log("テーブル存在確認エラー: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 単一行取得
     */
    public function fetchOne($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }
    
    /**
     * 全行取得
     */
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * レコード件数取得
     */
    public function count($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }
    
    /**
     * システム情報取得
     */
    public function getSystemInfo() {
        try {
            $this->ensureConnected();
            
            $info = [
                'connected' => $this->isConnected(),
                'host' => $this->host,
                'database' => $this->database,
                'username' => $this->username,
            ];
            
            if ($this->isConnected()) {
                $dbInfo = $this->fetchOne("SELECT VERSION() as version, DATABASE() as current_db, USER() as current_user");
                if ($dbInfo) {
                    $info['version'] = $dbInfo['version'];
                    $info['current_database'] = $dbInfo['current_db'];
                    $info['current_user'] = $dbInfo['current_user'];
                }
            }
            
            return $info;
            
        } catch (Exception $e) {
            return [
                'connected' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * デストラクタ
     */
    public function __destruct() {
        $this->pdo = null;
        $this->connected = false;
    }
    
    /**
     * クローン禁止（Singleton強制）
     */
    private function __clone() {}
    
    /**
     * シリアライゼーション禁止（Singleton強制）
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}
?>
