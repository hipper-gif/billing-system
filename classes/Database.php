<?php
/**
 * Database.php - 修正版
 * fetchOne(), fetchAll()メソッドを追加
 * Singletonパターン対応・既存機能維持
 * 
 * @version 2.1 
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
    
    // シリアライゼーションを防ぐ
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
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
            PDO::ATTR_PERSISTENT => false
        ];
        
        try {
            $this->pdo = new PDO($dsn, $this->username, $this->password, $options);
        } catch (PDOException $e) {
            error_log("データベース接続エラー: " . $e->getMessage());
            throw new Exception("データベース接続に失敗しました");
        }
    }
    
    /**
     * 単一行を取得（新規追加）
     * 
     * @param string $sql SQL文
     * @param array $params パラメータ配列
     * @return array|null 結果配列または null
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
     * 複数行を取得（新規追加）
     * 
     * @param string $sql SQL文
     * @param array $params パラメータ配列
     * @return array 結果配列
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
     * SQL実行（既存メソッド・互換性維持）
     * 
     * @param string $sql SQL文
     * @param array $params パラメータ配列
     * @return bool 実行結果
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
     * クエリ実行（既存メソッド・互換性維持）
     * 
     * @param string $sql SQL文
     * @return PDOStatement
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
     * 
     * @return string 挿入ID
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
     * テーブル存在確認（INFORMATION_SCHEMA使用）
     * 
     * @param string $tableName テーブル名
     * @return bool 存在するかどうか
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
     * 
     * @return bool 接続状況
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
    
    /**
     * テーブル情報取得
     * 
     * @param string $tableName テーブル名
     * @return array テーブル情報
     */
    public function getTableInfo($tableName) {
        try {
            $sql = "SELECT 
                        COLUMN_NAME, 
                        DATA_TYPE, 
                        IS_NULLABLE, 
                        COLUMN_DEFAULT,
                        COLUMN_KEY,
                        EXTRA
                    FROM INFORMATION_SCHEMA.COLUMNS 
                    WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
                    ORDER BY ORDINAL_POSITION";
            
            return $this->fetchAll($sql, [$this->database, $tableName]);
        } catch (Exception $e) {
            error_log("getTableInfo エラー: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * 全テーブル一覧取得
     * 
     * @return array テーブル名配列
     */
    public function getAllTables() {
        try {
            $sql = "SELECT TABLE_NAME 
                    FROM INFORMATION_SCHEMA.TABLES 
                    WHERE TABLE_SCHEMA = ? 
                    ORDER BY TABLE_NAME";
            
            $result = $this->fetchAll($sql, [$this->database]);
            return array_column($result, 'TABLE_NAME');
        } catch (Exception $e) {
            error_log("getAllTables エラー: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * レコード数取得
     * 
     * @param string $tableName テーブル名
     * @param string $whereClause WHERE句（オプション）
     * @param array $params パラメータ配列
     * @return int レコード数
     */
    public function getRecordCount($tableName, $whereClause = '', $params = []) {
        try {
            $sql = "SELECT COUNT(*) as count FROM " . $tableName;
            if ($whereClause) {
                $sql .= " WHERE " . $whereClause;
            }
            
            $result = $this->fetchOne($sql, $params);
            return $result ? intval($result['count']) : 0;
        } catch (Exception $e) {
            error_log("getRecordCount エラー: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * データベース情報取得
     * 
     * @return array データベース統計情報
     */
    public function getDatabaseInfo() {
        try {
            $tables = $this->getAllTables();
            $totalRecords = 0;
            $tableStats = [];
            
            foreach ($tables as $table) {
                $count = $this->getRecordCount($table);
                $totalRecords += $count;
                $tableStats[$table] = $count;
            }
            
            return [
                'database_name' => $this->database,
                'table_count' => count($tables),
                'total_records' => $totalRecords,
                'table_stats' => $tableStats,
                'connection_status' => $this->testConnection()
            ];
        } catch (Exception $e) {
            error_log("getDatabaseInfo エラー: " . $e->getMessage());
            return [
                'database_name' => $this->database,
                'table_count' => 0,
                'total_records' => 0,
                'table_stats' => [],
                'connection_status' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * プリペアドステートメント実行（高度な使用）
     * 
     * @param string $sql SQL文
     * @param array $params パラメータ配列
     * @return PDOStatement
     */
    public function prepare($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            if (!empty($params)) {
                $stmt->execute($params);
            }
            return $stmt;
        } catch (PDOException $e) {
            error_log("prepare エラー: " . $e->getMessage() . " SQL: " . $sql);
            throw new Exception("プリペアドステートメントの実行に失敗しました");
        }
    }
    
    /**
     * バッチ実行（大量データ処理用）
     * 
     * @param string $sql SQL文
     * @param array $batchData バッチデータ配列
     * @param int $batchSize バッチサイズ
     * @return array 実行結果
     */
    public function executeBatch($sql, $batchData, $batchSize = 100) {
        $results = [
            'success' => 0,
            'error' => 0,
            'errors' => []
        ];
        
        try {
            $this->beginTransaction();
            $stmt = $this->pdo->prepare($sql);
            
            $batches = array_chunk($batchData, $batchSize);
            
            foreach ($batches as $batch) {
                foreach ($batch as $params) {
                    try {
                        $stmt->execute($params);
                        $results['success']++;
                    } catch (PDOException $e) {
                        $results['error']++;
                        $results['errors'][] = $e->getMessage();
                        error_log("executeBatch エラー: " . $e->getMessage());い・・８・
                    }
                }
            }
            
            $this->commit();
            
        } catch (Exception $e) {
            $this->rollback();
            error_log("executeBatch 全体エラー: " . $e->getMessage());
            throw new Exception("バッチ実行に失敗しました");
        }
        
        return $results;
    }
    
    /**
     * デバッグ情報取得
     * 
     * @return array デバッグ情報
     */
    public function getDebugInfo() {
        return [
            'class_name' => __CLASS__,
            'singleton_instance' => (self::$instance !== null),
            'connection_status' => $this->testConnection(),
            'database_name' => $this->database,
            'host' => $this->host,
            'php_version' => PHP_VERSION,
            'pdo_version' => $this->pdo->getAttribute(PDO::ATTR_SERVER_VERSION),
            'methods' => get_class_methods($this)
        ];
    }
}
