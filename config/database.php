<?php
/**
 * 修正版データベース設定
 * config/database.php
 * エックスサーバー4文字制限対応版
 */

// 環境自動判定
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

if (strpos($host, 'twinklemark.xsrv.jp') !== false) {
    // テスト環境（エックスサーバー）
    define('DB_HOST', 'localhost'); // 実際のMySQLホストに変更
    define('DB_NAME', 'twinklemark_billing');
    define('DB_USER', 'twinklemark_bill'); // 4文字制限: bill
    define('DB_PASS', 'Smiley2525'); // 実際のパスワードに変更
    define('ENVIRONMENT', 'test');
    define('BASE_URL', 'https://twinklemark.xsrv.jp/Smiley/meal-delivery/billing-system/');
    define('DEBUG_MODE', true);
    
} elseif (strpos($host, 'tw1nkle.com') !== false) {
    // 本番環境（エックスサーバー）
    define('DB_HOST', 'localhost'); // 実際のMySQLホストに変更
    define('DB_NAME', 'tw1nkle_billing');
    define('DB_USER', 'tw1nkle_bill'); // 4文字制限: bill
    define('DB_PASS', 'Smiley2525'); // 実際のパスワードに変更
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
    // PHP設定最適化
    ini_set('max_execution_time', 300);
    ini_set('memory_limit', '256M');
    ini_set('upload_max_filesize', '10M');
    ini_set('post_max_size', '10M');
    
    // タイムゾーン設定
    date_default_timezone_set('Asia/Tokyo');
    
    // HTTPS強制リダイレクト
    if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
        $redirectURL = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        header("Location: $redirectURL");
        exit();
    }
}

// セキュリティ設定
define('SESSION_TIMEOUT', 3600);
define('CSRF_TOKEN_EXPIRE', 3600);

// ファイル設定
define('UPLOAD_MAX_SIZE', 10 * 1024 * 1024); // 10MB
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

// セッション設定
ini_set('session.gc_maxlifetime', SESSION_TIMEOUT);
ini_set('session.cookie_lifetime', SESSION_TIMEOUT);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', ENVIRONMENT !== 'local');
ini_set('session.cookie_samesite', 'Strict');

/**
 * エックスサーバー最適化データベース接続クラス
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
                PDO::ATTR_PERSISTENT => false, // エックスサーバーでは false 推奨
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
                PDO::ATTR_TIMEOUT => 10 // 接続タイムアウト10秒
            ];
            
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            
            // エックスサーバー用の追加設定
            $this->pdo->exec("SET time_zone = '+09:00'");
            $this->pdo->exec("SET sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO'");
            
        } catch (PDOException $e) {
            // エラーログに記録
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
    
    public function query($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Database query failed: " . $e->getMessage() . " SQL: " . $sql);
            throw $e;
        }
    }
    
    public function lastInsertId() {
        return $this->pdo->lastInsertId();
    }
    
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }
    
    public function commit() {
        return $this->pdo->commit();
    }
    
    public function rollback() {
        return $this->pdo->rollback();
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
        BASE_PATH . 'pdf/'
    ];
    
    foreach ($directories as $dir) {
        if (!is_dir($dir)) {
            if (mkdir($dir, 0755, true)) {
                // セキュリティ用.htaccess作成
                if (in_array($dir, [UPLOAD_DIR, TEMP_DIR, LOG_DIR])) {
                    file_put_contents($dir . '.htaccess', "Order Deny,Allow\nDeny from all\n");
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

/**
 * 環境情報表示（デバッグ用）
 */
if (DEBUG_MODE && isset($_GET['debug']) && $_GET['debug'] === 'env') {
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!DOCTYPE html>
    <html lang="ja">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>環境設定確認 - Smiley配食システム</title>
        <style>
            body { font-family: 'Helvetica Neue', Arial, sans-serif; margin: 20px; background: #f8f9fa; }
            .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            table { width: 100%; border-collapse: collapse; margin: 20px 0; }
            th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
            th { background-color: #f8f9fa; font-weight: bold; }
            .success { color: #28a745; font-weight: bold; }
            .error { color: #dc3545; font-weight: bold; }
            .badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; }
            .badge-success { background: #d4edda; color: #155724; }
            .badge-danger { background: #f8d7da; color: #721c24; }
            .badge-warning { background: #fff3cd; color: #856404; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>🍱 Smiley配食システム - 環境設定確認</h1>
            
            <h2>基本情報</h2>
            <table>
                <tr><th>項目</th><th>値</th></tr>
                <tr><td>環境</td><td><span class="badge badge-success"><?= ENVIRONMENT ?></span></td></tr>
                <tr><td>ホスト</td><td><?= $_SERVER['HTTP_HOST'] ?? 'Unknown' ?></td></tr>
                <tr><td>ベースURL</td><td><a href="<?= BASE_URL ?>"><?= BASE_URL ?></a></td></tr>
                <tr><td>デバッグモード</td><td><?= DEBUG_MODE ? '<span class="badge badge-warning">ON</span>' : '<span class="badge badge-success">OFF</span>' ?></td></tr>
            </table>
            
            <h2>データベース設定</h2>
            <table>
                <tr><th>項目</th><th>値</th></tr>
                <tr><td>データベース名</td><td><?= DB_NAME ?></td></tr>
                <tr><td>DBホスト</td><td><?= DB_HOST ?></td></tr>
                <tr><td>DBユーザー</td><td><?= DB_USER ?></td></tr>
                <tr><td>パスワード設定</td><td><?= !empty(DB_PASS) ? '<span class="success">設定済み</span>' : '<span class="error">未設定</span>' ?></td></tr>
            </table>
            
            <h2>データベース接続テスト</h2>
            <?php $dbTest = testDatabaseConnection(); ?>
            <div style="padding: 20px; border-radius: 8px; margin: 20px 0; <?= $dbTest['success'] ? 'background: #d4edda; color: #155724;' : 'background: #f8d7da; color: #721c24;' ?>">
                <h3><?= $dbTest['success'] ? '✅ 接続成功' : '❌ 接続失敗' ?></h3>
                <p><?= $dbTest['message'] ?></p>
                <?php if ($dbTest['success']): ?>
                <p>テーブル数: <?= $dbTest['table_count'] ?>個</p>
                <?php endif; ?>
            </div>
            
            <h2>次のステップ</h2>
            <div style="background: #e7f3ff; padding: 20px; border-radius: 8px; margin: 20px 0;">
                <?php if ($dbTest['success'] && $dbTest['table_count'] == 0): ?>
                <p>✅ データベース接続は成功していますが、テーブルが作成されていません。</p>
                <p><strong>次の手順:</strong> phpMyAdminでテーブル作成SQLを実行してください。</p>
                <p><a href="https://<?= $_SERVER['HTTP_HOST'] ?>/phpmyadmin" target="_blank">📊 phpMyAdminを開く</a></p>
                <?php elseif ($dbTest['success'] && $dbTest['table_count'] > 0): ?>
                <p>✅ データベースは正常に設定されています！</p>
                <p><strong>次の手順:</strong> <a href="index.php">メインシステム</a>にアクセスしてください。</p>
                <?php else: ?>
                <p>❌ データベース接続に問題があります。</p>
                <p><strong>確認事項:</strong></p>
                <ul>
                    <li>MySQLホスト名が正しいか確認</li>
                    <li>データベース名: <?= DB_NAME ?></li>
                    <li>ユーザー名: <?= DB_USER ?></li>
                    <li>パスワードが正しいか確認</li>
                    <li>ユーザーがデータベースに紐付けられているか確認</li>
                </ul>
                <?php endif; ?>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}
?>
