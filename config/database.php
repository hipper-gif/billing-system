<?php
/**
 * データベース接続設定（環境自動判定版）
 * エラー修正: mysql1.php.xserver.jp → mysql1.xserver.jp
 * 
 * @author Claude
 * @version 1.1.0
 * @fixed 2025-09-02 - ホスト名修正
 */

// 環境自動判定
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

if (strpos($host, 'twinklemark.xsrv.jp') !== false) {
    // テスト環境
    define('DB_HOST', 'mysql1.xserver.jp'); // 修正: .php削除
    define('DB_NAME', 'twinklemark_billing');
    define('DB_USER', 'twinklemark_billing');
    define('DB_PASS', 'your_test_password'); // ここに実際のパスワード
    define('ENVIRONMENT', 'test');
    define('BASE_URL', 'https://twinklemark.xsrv.jp/Smiley/meal-delivery/billing-system/');
    
} elseif (strpos($host, 'tw1nkle.com') !== false) {
    // 本番環境
    define('DB_HOST', 'mysql1.xserver.jp'); // 修正: .php削除
    define('DB_NAME', 'tw1nkle_billing');
    define('DB_USER', 'tw1nkle_billing');
    define('DB_PASS', 'your_prod_password'); // ここに実際のパスワード
    define('ENVIRONMENT', 'production');
    define('BASE_URL', 'https://tw1nkle.com/Smiley/meal-delivery/billing-system/');
    
} else {
    // ローカル開発環境
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'billing_system_local');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('ENVIRONMENT', 'local');
    define('BASE_URL', 'http://localhost/billing-system/');
}

// システム設定
define('SYSTEM_NAME', 'Smiley配食 請求書管理システム');
define('SYSTEM_VERSION', '1.1.0');
define('DEBUG_MODE', ENVIRONMENT !== 'production');

// パス設定
define('BASE_PATH', __DIR__ . '/../');
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

// エラーレポート設定
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
?>
