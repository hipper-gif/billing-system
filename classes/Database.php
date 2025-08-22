<?php
require_once __DIR__ . '/../config/database.php';

/**
 * データベース接続クラス（完全版）
 * CSVインポート機能対応
 */
class Database {
    private $pdo;
    private $connected = false;
    private $lastError = '';
    
    public function __construct() {
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
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
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
            
            throw new Exception("データベース接続エラー: " . $e->getMessage());
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
     * SQLクエリ実行
     */
    public function query($sql, $params = []) {
        if (!$this->connected) {
            throw new Exception("データベースに接続されていません: " . $this->lastError);
        }
        
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            throw new Exception("SQL実行エラー: " . $e->getMessage() . " | SQL: " . $sql);
        }
    }
    
    /**
     * 最後に挿入されたIDを取得
     */
    public function lastInsertId() {
        if (!$this->connected) {
            throw new Exception("データベースに接続されていません");
        }
        return $this->pdo->lastInsertId();
    }
    
    /**
     * トランザクション開始
     */
    public function beginTransaction() {
        if (!$this->connected) {
            throw new Exception("データベースに接続されていません");
        }
        return $this->pdo->beginTransaction();
    }
    
    /**
     * トランザクションコミット
     */
    public function commit() {
        if (!$this->connected) {
            throw new Exception("データベースに接続されていません");
        }
        return $this->pdo->commit();
    }
    
    /**
     * トランザクションロールバック
     */
    public function rollback() {
        if (!$this->connected) {
            throw new Exception("データベースに接続されていません");
        }
        return $this->pdo->rollback();
    }
    
    /**
     * PDOオブジェクトを直接取得（緊急時用）
     */
    public function getConnection() {
        if (!$this->connected) {
            throw new Exception("データベースに接続されていません");
        }
        return $this->pdo;
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
                'connection_status' => 'Connected',
                'charset' => 'utf8mb4'
            ];
        } catch (PDOException $e) {
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * テーブル存在確認
     */
    public function tableExists($tableName) {
        try {
            if (!$this->connected) {
                return false;
            }
            
            $stmt = $this->pdo->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$tableName]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * カラム存在確認
     */
    public function columnExists($tableName, $columnName) {
        try {
            if (!$this->connected) {
                return false;
            }
            
            $stmt = $this->pdo->prepare("SHOW COLUMNS FROM `{$tableName}` LIKE ?");
            $stmt->execute([$columnName]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * バックアップSQLの実行（大きなSQL文対応）
     */
    public function executeBulkSQL($sql) {
        if (!$this->connected) {
            throw new Exception("データベースに接続されていません");
        }
        
        try {
            // SQLファイルを分割して実行
            $statements = explode(';', $sql);
            $successCount = 0;
            
            foreach ($statements as $statement) {
                $statement = trim($statement);
                if (empty($statement)) continue;
                
                $this->pdo->exec($statement);
                $successCount++;
            }
            
            return [
                'success' => true,
                'executed' => $successCount,
                'message' => "{$successCount}個のSQL文を実行しました"
            ];
            
        } catch (PDOException $e) {
            throw new Exception("バルクSQL実行エラー: " . $e->getMessage());
        }
    }
    
    /**
     * 簡単なテストクエリ実行
     */
    public function testConnection() {
        try {
            if (!$this->connected) {
                return [
                    'success' => false,
                    'message' => 'データベース未接続: ' . $this->lastError
                ];
            }
            
            $result = $this->pdo->query("SELECT 1 as test")->fetch();
            
            if ($result['test'] == 1) {
                return [
                    'success' => true,
                    'message' => 'データベース接続テスト成功'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'テストクエリ実行失敗'
                ];
            }
            
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'テストクエリエラー: ' . $e->getMessage()
            ];
        }
    }
}
?>
