<?php
/**
 * データベース接続クラス（Singleton + 完全対応版）
 * SmileyCSVImporter.php対応
 */

// 重複宣言防止
if (!class_exists('Database')) {

require_once __DIR__ . '/../config/database.php';

class Database {
    private static $instance = null;
    private $pdo;
    private $connected = false;
    private $lastError = '';
    
    /**
     * プライベートコンストラクタ（Singletonパターン）
     */
    private function __construct() {
        $this->connect();
    }
    
    /**
     * Singletonインスタンス取得
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
            // データベース設定確認
            if (!defined('DB_HOST') || !defined('DB_NAME') || !defined('DB_USER')) {
                throw new Exception('データベース設定が不完全です');
            }
            
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
            ];
            
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            $this->connected = true;
            
            // デバッグログ
            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                error_log("Database connected successfully to " . DB_NAME);
            }
            
        } catch (PDOException $e) {
            $this->connected = false;
            $this->lastError = $e->getMessage();
            
            error_log("Database connection failed: " . $e->getMessage());
            throw new Exception("データベース接続エラー: " . $e->getMessage());
        }
    }
    
    /**
     * PDO接続オブジェクト取得（SmileyCSVImporter対応）
     */
    public function getConnection() {
        if (!$this->connected) {
            throw new Exception("データベースに接続されていません: " . $this->lastError);
        }
        return $this->pdo;
    }
    
    /**
     * クエリ実行（SmileyCSVImporter対応）
     */
    public function query($sql, $params = []) {
        try {
            if (!$this->connected) {
                throw new Exception("データベースに接続されていません");
            }
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            return $stmt;
            
        } catch (PDOException $e) {
            error_log("Query error: " . $e->getMessage() . " SQL: " . $sql);
            throw new Exception("クエリ実行エラー: " . $e->getMessage());
        }
    }
    
    /**
     * 最後のInsert ID取得
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
     * 接続状態確認
     */
    public function isConnected() {
        return $this->connected;
    }
    
    /**
     * 最後のエラー取得
     */
    public function getLastError() {
        return $this->lastError;
    }
    
    /**
     * データベース存在・接続確認
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
            
            $stmt = $this->query("SELECT DATABASE() as current_db, NOW() as current_time");
            $result = $stmt->fetch();
            
            return [
                'success' => true,
                'database' => $result['current_db'],
                'current_time' => $result['current_time'],
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
     * テーブル存在確認
     */
    public function tableExists($tableName) {
        try {
            $stmt = $this->query("SHOW TABLES LIKE ?", [$tableName]);
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            error_log("Table check error: " . $e->getMessage());
            return false;
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
            
            $stmt = $this->query("SELECT VERSION() as version, @@sql_mode as sql_mode");
            $info = $stmt->fetch();
            
            return [
                'mysql_version' => $info['version'],
                'sql_mode' => $info['sql_mode'],
                'connection_status' => 'Connected',
                'database_name' => DB_NAME,
                'charset' => 'utf8mb4'
            ];
            
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * 簡易テーブル作成（開発用）
     */
    public function createBasicTables() {
        $tables = [
            'companies' => "
                CREATE TABLE IF NOT EXISTS companies (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    company_code VARCHAR(50),
                    company_name VARCHAR(255) NOT NULL,
                    is_active BOOLEAN DEFAULT TRUE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ",
            'departments' => "
                CREATE TABLE IF NOT EXISTS departments (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    company_id INT,
                    department_code VARCHAR(50),
                    department_name VARCHAR(255) NOT NULL,
                    is_active BOOLEAN DEFAULT TRUE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (company_id) REFERENCES companies(id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ",
            'users' => "
                CREATE TABLE IF NOT EXISTS users (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_code VARCHAR(50) NOT NULL UNIQUE,
                    user_name VARCHAR(255) NOT NULL,
                    company_id INT,
                    department_id INT,
                    company_name VARCHAR(255),
                    department VARCHAR(255),
                    employee_type_code VARCHAR(50),
                    employee_type_name VARCHAR(100),
                    is_active BOOLEAN DEFAULT TRUE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (company_id) REFERENCES companies(id),
                    FOREIGN KEY (department_id) REFERENCES departments(id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ",
            'suppliers' => "
                CREATE TABLE IF NOT EXISTS suppliers (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    supplier_code VARCHAR(50),
                    supplier_name VARCHAR(255) NOT NULL,
                    is_active BOOLEAN DEFAULT TRUE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ",
            'products' => "
                CREATE TABLE IF NOT EXISTS products (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    product_code VARCHAR(50) NOT NULL UNIQUE,
                    product_name VARCHAR(255) NOT NULL,
                    category_code VARCHAR(50),
                    category_name VARCHAR(100),
                    supplier_id INT,
                    unit_price DECIMAL(10,2),
                    is_active BOOLEAN DEFAULT TRUE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (supplier_id) REFERENCES suppliers(id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ",
            'orders' => "
                CREATE TABLE IF NOT EXISTS orders (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    delivery_date DATE NOT NULL,
                    user_id INT,
                    user_code VARCHAR(50),
                    user_name VARCHAR(255),
                    company_id INT,
                    company_code VARCHAR(50),
                    company_name VARCHAR(255),
                    department_id INT,
                    product_id INT,
                    product_code VARCHAR(50),
                    product_name VARCHAR(255),
                    category_code VARCHAR(50),
                    category_name VARCHAR(100),
                    supplier_id INT,
                    quantity INT DEFAULT 1,
                    unit_price DECIMAL(10,2),
                    total_amount DECIMAL(10,2),
                    supplier_code VARCHAR(50),
                    supplier_name VARCHAR(255),
                    corporation_code VARCHAR(50),
                    corporation_name VARCHAR(255),
                    employee_type_code VARCHAR(50),
                    employee_type_name VARCHAR(100),
                    department_code VARCHAR(50),
                    department_name VARCHAR(255),
                    import_batch_id VARCHAR(100),
                    notes TEXT,
                    delivery_time TIME,
                    cooperation_code VARCHAR(50),
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id),
                    FOREIGN KEY (company_id) REFERENCES companies(id),
                    FOREIGN KEY (product_id) REFERENCES products(id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ",
            'import_logs' => "
                CREATE TABLE IF NOT EXISTS import_logs (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    batch_id VARCHAR(100) NOT NULL,
                    file_name VARCHAR(255),
                    total_rows INT DEFAULT 0,
                    success_rows INT DEFAULT 0,
                    error_rows INT DEFAULT 0,
                    new_companies INT DEFAULT 0,
                    new_departments INT DEFAULT 0,
                    new_users INT DEFAULT 0,
                    new_suppliers INT DEFAULT 0,
                    new_products INT DEFAULT 0,
                    duplicate_orders INT DEFAULT 0,
                    import_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    status ENUM('success', 'partial_success', 'failed') DEFAULT 'success',
                    notes TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            "
        ];
        
        $results = [];
        
        foreach ($tables as $tableName => $sql) {
            try {
                $this->pdo->exec($sql);
                $results[$tableName] = 'created';
            } catch (PDOException $e) {
                $results[$tableName] = 'error: ' . $e->getMessage();
                error_log("Table creation error ({$tableName}): " . $e->getMessage());
            }
        }
        
        return $results;
    }
    
    /**
     * クローン防止
     */
    private function __clone() {}
    
    /**
     * アンシリアライズ防止
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}

} // class_exists check end
?>
