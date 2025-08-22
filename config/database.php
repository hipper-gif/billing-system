<?php
/**
 * データベース設定ファイル
 * 設定値の定義のみ、クラス定義は含まない
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
    // PHP設定最適化
    ini_set('max_execution_time', 300);
    ini_set('memory_limit', '256M');
    ini_set('upload_max_filesize', '10M');
    ini_set('post_max_size', '10M');
    
    // タイムゾーン設定
    date_default_timezone_set('Asia/Tokyo');
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
?>
