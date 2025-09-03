<?php
/**
 * データベース接続設定ファイル（Smiley配食システム用）
 * エックスサーバー環境自動判定対応
 * 
 * @author Claude
 * @version 2.0.0
 * @created 2025-09-03
 * @fixed データベース接続エラー根本解決版
 */

// エラーレポート設定（初期段階）
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// 環境自動判定
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

if (strpos($host, 'twinklemark.xsrv.jp') !== false) {
    // === テスト環境（twinklemark） ===
    define('DB_HOST', 'mysql1.xserver.jp');
    define('DB_NAME', 'twinklemark_billing');
    define('DB_USER', 'twinklemark_billing');
    define('DB_PASS', 'your_actual_password_here'); // ←実際のパスワードに変更
    define('ENVIRONMENT', 'test');
    define('BASE_URL', 'https://twinklemark.xsrv.jp/Smiley/meal-delivery/billing-system/');
    
} elseif (strpos($host, 'tw1nkle.com') !== false) {
    // === 本番環境（tw1nkle） ===
    define('DB_HOST', 'mysql1.xserver.jp');
    define('DB_NAME', 'tw1nkle_billing');
    define('DB_USER', 'tw1nkle_billing');
    define('DB_PASS', 'your_production_password_here'); // ←実際のパスワードに変更
    define('ENVIRONMENT', 'production');
    define('BASE_URL', 'https://tw1nkle.com/Smiley/meal-delivery/billing-system/');
    
} else {
    // === ローカル開発環境 ===
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'billing_system_local');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('ENVIRONMENT', 'local');
    define('BASE_URL', 'http://localhost/billing-system/');
}

// システム設定
define('SYSTEM_NAME', 'Smiley配食 請求書管理システム');
define('SYSTEM_VERSION', '2.0.0');
define('DEBUG_MODE', ENVIRONMENT !== 'production');

// パス設定
define('BASE_PATH', dirname(__DIR__) . '/');
define('UPLOAD_DIR', BASE_PATH . 'uploads/');
define('TEMP_DIR', BASE_PATH . 'temp/');
define('LOG_DIR', BASE_PATH . 'logs/');
define('CACHE_DIR', BASE_PATH . 'cache/');

// セキュリティ設定
define('SESSION_TIMEOUT', 3600); // 1時間
define('MAX_LOGIN_ATTEMPTS', 5);
define('PASSWORD_MIN_LENGTH', 8);

// ファイル設定
define('UPLOAD_MAX_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_FILE_TYPES', ['csv']);

// メール設定
define('MAIL_FROM', 'noreply@tw1nkle.com');
define('MAIL_FROM_NAME', 'Smiley配食システム');

// PDF設定
define('PDF_FONT', 'kozgopromedium'); // 日本語フォント
define('PDF_AUTHOR', 'Smiley配食事業');

// エラーハンドリング設定
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
    ini_set('error_log', LOG_DIR . 'php_errors.log');
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', LOG_DIR . 'php_errors.log');
}

// タイムゾーン設定
date_default_timezone_set('Asia/Tokyo');

// 文字エンコーディング設定
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

/**
 * 設定値検証関数
 * 必要な設定値がすべて定義されているかチェック
 */
function validateDatabaseConfig() {
    $required_constants = ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'];
    $missing = [];
    
    foreach ($required_constants as $const) {
        if (!defined($const) || empty(constant($const))) {
            $missing[] = $const;
        }
    }
    
    if (!empty($missing)) {
        $error_msg = 'データベース設定が不完全です。不足している設定: ' . implode(', ', $missing);
        error_log($error_msg);
        if (DEBUG_MODE) {
            throw new Exception($error_msg);
        }
    }
    
    return empty($missing);
}

// 設定値検証実行
validateDatabaseConfig();

/**
 * データベース接続テスト関数
 * 設定値でのデータベース接続が可能かテスト
 */
function testDatabaseConnection() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 5,
        ];
        
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        return true;
    } catch (PDOException $e) {
        error_log('Database connection test failed: ' . $e->getMessage());
        return false;
    }
}

// 開発環境でのみ接続テスト実行
if (DEBUG_MODE && ENVIRONMENT === 'local') {
    if (!testDatabaseConnection()) {
        error_log('Warning: Database connection test failed in local environment');
    }
}
?>
