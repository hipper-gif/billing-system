<?php
require_once __DIR__ . '/../config/database.php';

/**
 * データベース接続クラス（シンプル版）
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
                'connection_status' => 'Connected'
            ];
        } catch (PDOException $e) {
            return ['error' => $e->getMessage()];
        }
    }
}
?>
