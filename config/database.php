<?php
/**
 * 修正版データベース設定（メソッド追加版）
 * config/database.php
 * エックスサーバー4文字制限対応版
 */

// 環境自動判定
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

if (strpos($host, 'twinklemark.xsrv.jp') !== false) {
    // テスト環境（エックスサーバー）
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'twinklemark_billing');
    define('DB_USER', 'twinklemark_bill');
    define('DB_PASS', 'Smiley2525');
    define('ENVIRONMENT', 'test');
    define('BASE_URL', 'https://twinklemark.xsrv.jp/Smiley/meal-delivery/billing-system/');
    define('DEBUG_MODE', true);
    
} elseif (strpos($host, 'tw1nkle.com') !== false) {
    // 本番環境（エックスサーバー）
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'tw1nkle_billing');
    define('DB_USER', 'tw1nkle_bill');
    define('DB_PASS', 'Smiley2525');
    define('ENVIRONMENT', 'production');
    define('BASE_URL', 'https://tw1nkle.com/Smiley/meal-delivery/billing-system/');
    define('DEBUG_MODE', false);
    
} else {
    // ローカル開発環境
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'billing_local');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('ENVIRONMENT', 'local');
    define('BASE_URL', 'http://localhost/billing-system/');
    define('DEBUG_MODE', true);
}

// 共通設定
define('SYSTEM_NAME', 'Smiley配食 請求書管理システム');
define('SYSTEM_VERSION', '1.0.0');

// パス設定
define('BASE_PATH', realpath(__DIR__ . '/../') . '/');
define('UPLOAD_DIR', BASE_PATH . 'uploads/');
define('TEMP_DIR', BASE_PATH . 'temp/');
define('LOG_DIR', BASE_PATH . 'logs/');
define('CACHE_DIR', BASE_PATH . 'cache/');

// エックスサーバー固有設定
if (ENVIRONMENT === 'test' || ENVIRONMENT === 'production') {
    ini_set('max_execution_time', 300);
    ini_set('memory_limit', '256M');
    ini_set('upload_max_filesize', '10M');
    ini_set('post_max_size', '10M');
    date_default_timezone_set('Asia/Tokyo');
}

// セキュリティ設定
define('SESSION_TIMEOUT', 3600);
define('CSRF_TOKEN_EXPIRE', 3600);

// ファイル設定
define('UPLOAD_MAX_SIZE', 10 * 1024 * 1024);
define('ALLOWED_FILE_TYPES', ['csv']);
define('CSV_MAX_RECORDS', 10000);

// PDF設定
define('PDF_FONT', 'kozgopromedium');
define('PDF_AUTHOR', 'Smiley配食事業');

// メール設定
if (ENVIRONMENT === 'production') {
    define('MAIL_FROM', 'billing@tw1nkle.com');
    define('MAIL_FROM_NAME', 'Smiley配食 請求システム');
} else {
    define('MAIL_FROM', 'test-billing@tw1nkle.com');
    define('MAIL_FROM_NAME', 'Smiley配食 請求システム（テスト）');
}

// エラー報告設定
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
    ini_set('error_log', LOG_DIR . 'error.log');
} else {
    error_reporting(E_ERROR | E_WARNING | E_PARSE);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', LOG_DIR . 'error.log');
}

// セッション設定（セッション開始前のみ）
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', SESSION_TIMEOUT);
    ini_set('session.cookie_lifetime', SESSION_TIMEOUT);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', ENVIRONMENT !== 'local');
    ini_set('session.cookie_samesite', 'Strict');
}

/**
 * エックスサーバー最適化データベース接続クラス（メソッド追加版）
 */
class Database {
    private static $instance = null;
    private $pdo;
    
    private function __construct() {
        $this->connect();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function connect() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
                PDO::ATTR_TIMEOUT => 10
            ];
            
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            
            // エックスサーバー用の追加設定
            $this->pdo->exec("SET time_zone = '+09:00'");
            $this->pdo->exec("SET sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO'");
            
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            
            if (DEBUG_MODE) {
                throw new Exception("データベース接続エラー: " . $e->getMessage());
            } else {
                throw new Exception("データベース接続に失敗しました。管理者にお問い合わせください。");
            }
        }
    }
    
    public function getConnection() {
        return $this->pdo;
    }
    
    /**
     * クエリ実行（PDOStatement返却）
     */
    public function query($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Query error: " . $e->getMessage() . " | SQL: " . $sql);
            throw new Exception("クエリエラー: " . $e->getMessage() . " | SQL: " . $sql);
        }
    }
    
    /**
     * 全行取得
     */
    public function fetchAll($sql, $params = []) {
        try {
            $stmt = $this->query($sql, $params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    /**
     * 1行取得
     */
    public function fetch($sql, $params = []) {
        try {
            $stmt = $this->query($sql, $params);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    /**
     * INSERT/UPDATE/DELETE実行
     */
    public function execute($sql, $params = []) {
        try {
            $stmt = $this->query($sql, $params);
            return $stmt->rowCount();
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    /**
     * 最後に挿入されたID取得
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
     * データベース接続テスト
     */
    public function testConnection() {
        try {
            $stmt = $this->pdo->query("SELECT 1 as test");
            $result = $stmt->fetch();

            return [
                'success' => true,
                'message' => 'データベース接続成功',
                'test_result' => $result['test'] ?? null
            ];

        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'データベース接続テスト失敗: ' . $e->getMessage()
            ];
        }
    }
}

/**
 * 必要なディレクトリ作成
 */
function createRequiredDirectories() {
    $directories = [
        UPLOAD_DIR,
        TEMP_DIR,
        LOG_DIR,
        CACHE_DIR,
        BASE_PATH . 'backups/',
        BASE_PATH . 'pdf/',
        BASE_PATH . 'storage/',
        BASE_PATH . 'storage/invoices/'
    ];
    
    foreach ($directories as $dir) {
        if (!is_dir($dir)) {
            if (mkdir($dir, 0755, true)) {
                // セキュリティ用.htaccess作成
                if (in_array($dir, [UPLOAD_DIR, TEMP_DIR, LOG_DIR])) {
                    file_put_contents($dir . '.htaccess', "Require all denied\n");
                }
                
                if (DEBUG_MODE) {
                    error_log("ディレクトリ作成: {$dir}");
                }
            } else {
                error_log("ディレクトリ作成失敗: {$dir}");
            }
        }
    }
}

// 必要なディレクトリを作成
createRequiredDirectories();

/**
 * データベース接続テスト関数
 */
function testDatabaseConnection() {
    try {
        $db = Database::getInstance();
        $stmt = $db->query("SELECT COUNT(*) as table_count FROM information_schema.tables WHERE table_schema = ?", [DB_NAME]);
        $result = $stmt->fetch();
        
        return [
            'success' => true,
            'message' => '接続成功',
            'environment' => ENVIRONMENT,
            'database' => DB_NAME,
            'user' => DB_USER,
            'host' => DB_HOST,
            'table_count' => $result['table_count']
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => '接続失敗: ' . $e->getMessage(),
            'environment' => ENVIRONMENT,
            'database' => DB_NAME,
            'user' => DB_USER,
            'host' => DB_HOST
        ];
    }
}
?>
