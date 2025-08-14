<?php
/**
 * データベース設定ファイル
 * 環境自動判定により適切な設定を読み込み
 */

// 環境判定
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

if (strpos($host, 'twinklemark.xsrv.jp') !== false) {
    // テスト環境
    define('DB_HOST', 'mysql1.xserver.jp');
    define('DB_NAME', 'twinklemark_billing_test');
    define('DB_USER', 'twinklemark_test');
    define('DB_PASS', 'your_test_password_here'); // 実際のパスワードに変更
    define('ENVIRONMENT', 'test');
    define('BASE_URL', 'https://twinklemark.xsrv.jp/Smiley/meal-delivery/billing-system/');
    define('DEBUG_MODE', true);
    
} elseif (strpos($host, 'tw1nkle.com') !== false) {
    // 本番環境
    define('DB_HOST', 'mysql1.xserver.jp');
    define('DB_NAME', 'tw1nkle_billing_prod');
    define('DB_USER', 'tw1nkle_prod');
    define('DB_PASS', 'your_prod_password_here'); // 実際のパスワードに変更
    define('ENVIRONMENT', 'production');
    define('BASE_URL', 'https://tw1nkle.com/Smiley/meal-delivery/billing-system/');
    define('DEBUG_MODE', false);
    
} else {
    // ローカル開発環境
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'bentosystem_local');
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
if (ENVIRONMENT === 'production') {
    define('MAIL_FROM', 'noreply@tw1nkle.com');
    define('MAIL_FROM_NAME', 'Smiley配食システム');
} else {
    define('MAIL_FROM', 'test@example.com');
    define('MAIL_FROM_NAME', 'Smiley配食システム（テスト）');
}

// PDF設定
define('PDF_FONT', 'kozgopromedium'); // 日本語フォント
define('PDF_AUTHOR', 'Smiley配食事業');

// エラー表示設定
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', LOG_DIR . 'php_errors.log');
}

// タイムゾーン設定
date_default_timezone_set('Asia/Tokyo');

// セッション設定
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_lifetime', SESSION_TIMEOUT);
    ini_set('session.gc_maxlifetime', SESSION_TIMEOUT);
    ini_set('session.cookie_secure', !DEBUG_MODE);
    ini_set('session.cookie_httponly', 1);
    session_start();
}
?>