<?php
/**
 * データベース接続クラス
 * Singletonパターンで実装
 * 責任: データベース接続とクエリ実行のみ
 */

class Database {
    private static $instance = null;
    private $pdo;
    private $connected = false;
    private $lastError = '';
    
    /**
     * Singletonパターン: インスタンス取得
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * コンストラクタ（private）
     */
    private function __construct() {
        $this->connect();
    }
    
    /**
     * データベース接続
     */
    private function connect() {
        // 設定ファイルを読み込み（まだ読み込まれていない場合）
        if (!defined('DB_HOST')) {
            require_once __DIR__ . '/../config/database.php';
        }
        
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => false, // エックスサーバーでは false 推奨
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
                PDO::ATTR_TIMEOUT => 10 // 接続タイムアウト10秒
            ];
            
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            $this->connected = true;
            
            // エックスサーバー用の追加設定
            $this->pdo->exec("SET time_zone = '+09:00'");
            $this->pdo->exec("SET sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO'");
            
            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                error_log("Database connected successfully to " . DB_NAME);
            }
            
        } catch (PDOException $e) {
            $this->connected = false;
            $this->lastError = $e->getMessage();
            
            // エラーログに記録
            error_log("Database connection failed: " . $e->getMessage());
            
            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                throw new Exception("データベース接続エラー: " . $e->getMessage());
            } else {
                throw new Exception("データベース接続に失敗しました。管理者にお問い合わせください。");
            }
        }
    }
    
    /**
     * 接続状態確認
     */
    public function isConnected() {
        return $this->connected;
    }
    
    /**
     * 最後のエラーメッセージ取得
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
            $errorMessage = "SQL実行エラー: " . $e->getMessage();
            
            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                $errorMessage .= " | SQL: " . $sql;
                if (!empty($params)) {
                    $errorMessage .= " | Params: " . json_encode($params);
                }
            }
            
            error_log($errorMessage);
            throw new Exception($errorMessage);
        }
    }
    
    /**
     * PDOオブジェクト直接取得（高度な操作用）
     */
    public function getConnection() {
        if (!$this->connected) {
            throw new Exception("データベースに接続されていません");
        }
        return $this->pdo;
    }
    
    /**
     * 最後のInsert ID取得
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
     * データベース情報取得
     */
    public function getDatabaseInfo() {
        try {
            if (!$this->connected) {
                return [
                    'success' => false,
                    'message' => 'データベース未接続: ' . $this->lastError
                ];
            }
            
            $version = $this->pdo->query("SELECT VERSION() as version")->fetch();
            $database = $this->pdo->query("SELECT DATABASE() as current_db")->fetch();
            
            return [
                'success' => true,
                'mysql_version' => $version['version'],
                'database_name' => $database['current_db'],
                'connection_status' => 'Connected',
                'charset' => 'utf8mb4',
                'environment' => defined('ENVIRONMENT') ? ENVIRONMENT : 'unknown'
            ];
            
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'データベース情報取得エラー: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 接続テスト
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
            
            if ($result['test'] === 1) {
                return [
                    'success' => true,
                    'message' => 'データベース接続テスト成功',
                    'environment' => defined('ENVIRONMENT') ? ENVIRONMENT : 'unknown'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'テストクエリの結果が異常です'
                ];
            }
            
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'テストクエリエラー: ' . $e->getMessage()
            ];
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
            error_log("Table existence check failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 複製防止
     */
    private function __clone() {
        throw new Exception("Cannot clone singleton");
    }
    
    /**
     * シリアライゼーション防止
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}
?>
