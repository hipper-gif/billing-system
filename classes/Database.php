<?php
require_once __DIR__ . '/../config/database.php';

/**
 * データベース接続クラス
 * PDOを使用した安全なデータベース操作を提供
 */
class Database {
    private $pdo;
    private $connected = false;
    private $lastError = '';
    
    public function __construct() {
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
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ];
            
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            $this->connected = true;
            
            if (DEBUG_MODE) {
                error_log("Database connected successfully to " . DB_HOST . "/" . DB_NAME);
            }
            
        } catch (PDOException $e) {
            $this->connected = false;
            $this->lastError = $e->getMessage();
            
            if (DEBUG_MODE) {
                error_log("Database connection failed: " . $e->getMessage());
                throw new Exception("データベース接続エラー: " . $e->getMessage());
            } else {
                throw new Exception("データベース接続に失敗しました");
            }
        }
    }
    
    /**
     * 接続状態を確認
     */
    public function isConnected() {
        return $this->connected;
    }
    
    /**
     * 最後のエラーメッセージを取得
     */
    public function getLastError() {
        return $this->lastError;
    }
    
    /**
     * SQLクエリを実行
     */
    public function query($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            $this->lastError = $e->getMessage();
            if (DEBUG_MODE) {
                error_log("Query error: " . $e->getMessage() . " SQL: " . $sql);
                throw new Exception("クエリエラー: " . $e->getMessage());
            } else {
                throw new Exception("データベース操作でエラーが発生しました");
            }
        }
    }
    
    /**
     * SELECT文を実行してすべての行を取得
     */
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * SELECT文を実行して1行のみ取得
     */
    public function fetchOne($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }
    
    /**
     * INSERT文を実行して挿入されたIDを返す
     */
    public function insert($sql, $params = []) {
        $this->query($sql, $params);
        return $this->pdo->lastInsertId();
    }
    
    /**
     * UPDATE/DELETE文を実行して影響を受けた行数を返す
     */
    public function execute($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
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
        return $this->pdo->rollback();
    }
    
    /**
     * データベース存在チェック
     */
    public function checkDatabase() {
        try {
            $result = $this->fetchOne("SELECT DATABASE() as current_db");
            return [
                'success' => true,
                'database' => $result['current_db'],
                'message' => 'データベース接続成功'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'database' => null,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * テーブル一覧取得
     */
    public function getTables() {
        try {
            $tables = $this->fetchAll("SHOW TABLES");
            $tableNames = [];
            foreach ($tables as $table) {
                $tableNames[] = array_values($table)[0];
            }
            return $tableNames;
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * テーブル存在チェック
     */
    public function tableExists($tableName) {
        try {
            $result = $this->fetchOne(
                "SELECT COUNT(*) as count FROM information_schema.tables 
                 WHERE table_schema = ? AND table_name = ?",
                [DB_NAME, $tableName]
            );
            return $result['count'] > 0;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * システム情報取得
     */
    public function getSystemInfo() {
        try {
            $info = [
                'mysql_version' => $this->fetchOne("SELECT VERSION() as version")['version'],
                'character_set' => $this->fetchOne("SELECT @@character_set_database as charset")['charset'],
                'collation' => $this->fetchOne("SELECT @@collation_database as collation")['collation'],
                'timezone' => $this->fetchOne("SELECT @@system_time_zone as timezone")['timezone'],
                'tables' => $this->getTables()
            ];
            return $info;
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * 接続終了
     */
    public function close() {
        $this->pdo = null;
        $this->connected = false;
    }
    
    /**
     * デストラクタ
     */
    public function __destruct() {
        $this->close();
    }
}
?>