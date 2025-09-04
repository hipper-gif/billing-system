<?php
// config/database.php - 社内用シンプル版
// 環境自動判定による接続設定

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

// システム設定
define('SYSTEM_NAME', 'Smiley配食 請求書管理システム');
define('SYSTEM_VERSION', '1.0.0');

// パス設定
define('BASE_PATH', __DIR__ . '/../');
define('UPLOAD_DIR', BASE_PATH . 'uploads/');
define('TEMP_DIR', BASE_PATH . 'temp/');
define('LOG_DIR', BASE_PATH . 'logs/');
define('CACHE_DIR', BASE_PATH . 'cache/');

// セキュリティ設定
define('SESSION_TIMEOUT', 3600); // 1時間
define('MAX_LOGIN_ATTEMPTS', 5);
define('CSRF_TOKEN_EXPIRE', 1800); // 30分

// ファイル設定
define('UPLOAD_MAX_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_FILE_TYPES', ['csv', 'xlsx']);

// PDF設定
define('PDF_FONT', 'kozgopromedium');
define('PDF_AUTHOR', 'Smiley配食事業株式会社');

// メール設定
define('MAIL_FROM', ENVIRONMENT === 'production' ? 'noreply@tw1nkle.com' : 'test@twinklemark.xsrv.jp');
define('MAIL_FROM_NAME', 'Smiley配食事業');

// 接続テスト（デバッグ時のみ）
if (defined('TEST_CONNECTION') && TEST_CONNECTION === true) {
    try {
        $testPdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
        
        $result = $testPdo->query("SELECT 1 as test, NOW() as time")->fetch();
        echo "✅ データベース接続テスト成功: " . $result['time'] . "\n";
        $testPdo = null;
        
    } catch (PDOException $e) {
        echo "❌ データベース接続テスト失敗: " . $e->getMessage() . "\n";
        echo "設定: " . DB_HOST . "/" . DB_NAME . "/" . DB_USER . "\n";
    }
}
?>
