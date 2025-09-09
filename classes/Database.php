<?php
/**
 * Database クラス - Singleton パターン + tableExists() メソッド追加版
 * HTTPステータス0エラー根本解決対応
 */

class Database {
    private static $instance = null;
    private $connection = null;

    private function __construct() {
        $this->connect();
    }

    private function __clone() {}

    public function __wakeup() {
        throw new Exception("Cannot unserialize a singleton.");
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
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }

    /**
     * PDO 接続オブジェクト取得
     */
    public function getConnection() {
        return $this->connection;
    }

    /**
     * プリペアドステートメント実行
     */
    public function prepare($sql) {
        return $this->connection->prepare($sql);
    }

    /**
     * クエリ実行
     */
    public function query($sql, $params = []) {
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * 最後のインサートID取得
     */
    public function lastInsertId() {
        return $this->connection->lastInsertId();
    }

    /**
     * トランザクション開始
     */
    public function beginTransaction() {
        return $this->connection->beginTransaction();
    }

    /**
     * コミット
     */
    public function commit() {
        return $this->connection->commit();
    }

    /**
     * ロールバック
     */
    public function rollback() {
        return $this->connection->rollback();
    }

    /**
     * 接続テスト
     */
    public function testConnection() {
        try {
            $stmt = $this->query("SELECT 1 as test");
            $result = $stmt->fetch();
            return $result['test'] === 1;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * テーブル存在確認メソッド（根本解決対応）
     * INFORMATION_SCHEMA を使用した確実な確認
     */
    public function tableExists($tableName) {
        try {
            $sql = "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES 
                    WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?";
            $stmt = $this->prepare($sql);
            $stmt->execute([DB_NAME, $tableName]);
            $count = $stmt->fetchColumn();
            return $count > 0;
        } catch (Exception $e) {
            // フォールバック: 従来の方法も試す
            try {
                $sql = "SHOW TABLES LIKE ?";
                $stmt = $this->prepare($sql);
                $stmt->execute([$tableName]);
                return $stmt->rowCount() > 0;
            } catch (Exception $fallbackError) {
                throw new Exception("テーブル存在確認に失敗: " . $e->getMessage());
            }
        }
    }

    /**
     * データベース情報取得
     */
    public function getDatabaseInfo() {
        try {
            $info = [];
            
            // データベース名
            $info['database'] = DB_NAME;
            
            // バージョン情報
            $stmt = $this->query("SELECT VERSION() as version");
            $result = $stmt->fetch();
            $info['version'] = $result['version'];
            
            // 文字セット
            $stmt = $this->query("SELECT DEFAULT_CHARACTER_SET_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?", [DB_NAME]);
            $result = $stmt->fetch();
            $info['charset'] = $result['DEFAULT_CHARACTER_SET_NAME'] ?? 'unknown';
            
            return $info;
        } catch (Exception $e) {
            throw new Exception("データベース情報取得に失敗: " . $e->getMessage());
        }
    }

    /**
     * テーブル一覧取得
     */
    public function getTables() {
        try {
            $sql = "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES 
                    WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME";
            $stmt = $this->prepare($sql);
            $stmt->execute([DB_NAME]);
            
            $tables = [];
            while ($row = $stmt->fetch()) {
                $tables[] = $row['TABLE_NAME'];
            }
            
            return $tables;
        } catch (Exception $e) {
            throw new Exception("テーブル一覧取得に失敗: " . $e->getMessage());
        }
    }

    /**
     * テーブル行数取得
     */
    public function getTableRowCount($tableName) {
        try {
            // テーブル存在確認
            if (!$this->tableExists($tableName)) {
                throw new Exception("テーブル '{$tableName}' は存在しません");
            }
            
            $sql = "SELECT COUNT(*) FROM `{$tableName}`";
            $stmt = $this->query($sql);
            return $stmt->fetchColumn();
        } catch (Exception $e) {
            throw new Exception("テーブル行数取得に失敗: " . $e->getMessage());
        }
    }

    /**
     * クオート処理
     */
    public function quote($value) {
        return $this->connection->quote($value);
    }

    /**
     * システムヘルスチェック
     */
    public function healthCheck() {
        try {
            $startTime = microtime(true);
            
            // 基本接続テスト
            $connectionTest = $this->testConnection();
            
            // レスポンス時間計測
            $endTime = microtime(true);
            $responseTime = round(($endTime - $startTime) * 1000, 2);
            
            // データベース情報取得
            $dbInfo = $this->getDatabaseInfo();
            
            // 主要テーブル存在確認
            $requiredTables = ['users', 'companies', 'departments', 'orders', 'products', 'suppliers'];
            $tableStatus = [];
            foreach ($requiredTables as $table) {
                $tableStatus[$table] = $this->tableExists($table);
            }
            
            return [
                'connection' => $connectionTest,
                'response_time_ms' => $responseTime,
                'database_info' => $dbInfo,
                'table_status' => $tableStatus,
                'timestamp' => date('Y-m-d H:i:s')
            ];
        } catch (Exception $e) {
            return [
                'connection' => false,
                'error' => $e->getMessage(),
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
    }

    /**
     * デストラクタ
     */
    public function __destruct() {
        $this->connection = null;
    }
}
