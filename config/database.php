// 環境判定とデータベース設定
if (strpos($currentHost, 'twinklemark.xsrv.jp') !== false) {
    // === テスト環境（twinklemark） ===
    // デプロイサーバー: sv16114.xserver.jp (GitHub Actions用)
    // データベースサーバー: mysql1.xserver.jp (実際の接続先)
    define('DB_HOST', 'mysql1.xserver.jp');        // MySQLサーバーホスト
    define('DB_NAME', 'twinklemark_billing');      // 実際のDB名
    define('DB_USER', 'twinklemark_billing');      // 実際のDBユーザー名
    define('DB_PASS', 'actual_password_here');     // 実際のDBパスワード（要設定）
    define('ENVIRONMENT', 'test');
    define('BASE_URL', 'https://twinklemark.xsrv.jp/Smiley/meal-delivery/billing-system/');
    define('DEPLOY_HOST', 'sv16114.xserver.jp');   // デプロイ先サーバー（GitHub Actions用）
    
} elseif (strpos($currentHost, 'tw1nkle.com') !== false) {
    // === 本番環境（tw1nkle） ===
    // デプロイサーバー: sv16114.xserver.jp (GitHub<?php
/**
 * config/database.php - データベース設定ファイル
 * エックスサーバー対応版
 * 最終更新: 2025年9月3日
 */

// 現在のホスト名取得
$currentHost = $_SERVER['HTTP_HOST'] ?? 'localhost';

// 環境判定とデータベース設定
if (strpos($currentHost, 'twinklemark.xsrv.jp') !== false) {
    // === テスト環境（twinklemark） ===
    define('DB_HOST', 'mysql1.xserver.jp');
    define('DB_NAME', 'twinklemark_billing');
    define('DB_USER', 'twinklemark_db');
    define('DB_PASS', 'smiley2024test');
    define('ENVIRONMENT', 'test');
    define('BASE_URL', 'https://twinklemark.xsrv.jp/Smiley/meal-delivery/billing-system/');
    
} elseif (strpos($currentHost, 'tw1nkle.com') !== false) {
    // === 本番環境（tw1nkle） ===
    define('DB_HOST', 'mysql1.xserver.jp');
    define('DB_NAME', 'tw1nkle_billing');
    define('DB_USER', 'tw1nkle_db');
    define('DB_PASS', 'smiley2024prod');
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

// === システム共通設定 ===
define('SYSTEM_NAME', 'Smiley配食事業システム');
define('SYSTEM_VERSION', '2.0.0');
define('DEBUG_MODE', ENVIRONMENT !== 'production');

// === セキュリティ設定 ===
define('SESSION_TIMEOUT', 3600); // 1時間
define('MAX_LOGIN_ATTEMPTS', 5);
define('PASSWORD_MIN_LENGTH', 8);
define('CSRF_TOKEN_EXPIRE', 1800); // 30分

// === ファイル・アップロード設定 ===
define('UPLOAD_MAX_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_FILE_TYPES', ['csv', 'xlsx']);
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('TEMP_DIR', __DIR__ . '/../temp/');
define('LOG_DIR', __DIR__ . '/../logs/');

// === PDF設定 ===
define('PDF_FONT', 'ipagp'); // 日本語フォント
define('PDF_AUTHOR', '株式会社Smiley');
define('PDF_CREATOR', 'Smiley配食事業システム');

// === メール設定 ===
if (ENVIRONMENT === 'production') {
    define('MAIL_FROM', 'noreply@tw1nkle.com');
    define('MAIL_FROM_NAME', 'Smiley配食事業システム');
    define('SMTP_HOST', 'sv16114.xserver.jp');
    define('SMTP_PORT', 587);
} else {
    define('MAIL_FROM', 'test@twinklemark.xsrv.jp');
    define('MAIL_FROM_NAME', 'Smileyシステム（テスト）');
    define('SMTP_HOST', 'sv16114.xserver.jp');
    define('SMTP_PORT', 587);
}

// === 業務設定 ===
define('BUSINESS_NAME', '株式会社Smiley');
define('BUSINESS_ADDRESS', '〒000-0000 東京都○○区○○ 1-2-3');
define('BUSINESS_PHONE', '03-0000-0000');
define('BUSINESS_EMAIL', 'info@smiley-kitchen.com');

// === 請求書設定 ===
define('INVOICE_NUMBER_PREFIX', 'INV');
define('RECEIPT_NUMBER_PREFIX', 'REC');
define('DEFAULT_PAYMENT_TERMS', 30); // 30日
define('TAX_RATE', 0.10); // 10%

// === 支払い方法設定 ===
define('PAYMENT_METHODS', [
    'cash' => '現金',
    'bank_transfer' => '銀行振込',
    'account_debit' => '口座引き落とし',
    'paypay' => 'PayPay',
    'mixed' => '混合',
    'other' => 'その他'
]);

// === 運用設定 ===
define('BACKUP_RETENTION_DAYS', 90);
define('LOG_RETENTION_DAYS', 30);
define('SESSION_SAVE_PATH', TEMP_DIR . 'sessions/');

// === API設定 ===
define('API_VERSION', 'v1');
define('API_RATE_LIMIT', 1000); // 1時間あたり
define('API_TIMEOUT', 30); // 秒

// === キャッシュ設定 ===
define('CACHE_ENABLED', ENVIRONMENT === 'production');
define('CACHE_DIR', __DIR__ . '/../cache/');
define('CACHE_EXPIRE', 3600); // 1時間

// === ログ設定 ===
define('LOG_LEVEL', DEBUG_MODE ? 'DEBUG' : 'INFO');
define('LOG_FORMAT', '[%datetime%] %level_name%: %message%');

// === 文字エンコーディング ===
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATION', 'utf8mb4_unicode_ci');
define('INTERNAL_ENCODING', 'UTF-8');

// === タイムゾーン設定 ===
date_default_timezone_set('Asia/Tokyo');
define('TIMEZONE', 'Asia/Tokyo');

// === エラーレポート設定 ===
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
} else {
    error_reporting(E_ERROR | E_WARNING | E_PARSE);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', LOG_DIR . 'php_errors.log');
}

// === セッション設定 ===
ini_set('session.save_path', SESSION_SAVE_PATH);
ini_set('session.gc_maxlifetime', SESSION_TIMEOUT);
ini_set('session.cookie_lifetime', SESSION_TIMEOUT);
ini_set('session.cookie_secure', ENVIRONMENT !== 'local');
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);

// === ディレクトリ作成 ===
$directories = [UPLOAD_DIR, TEMP_DIR, LOG_DIR, CACHE_DIR, SESSION_SAVE_PATH];
foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// === 環境情報出力（デバッグ時のみ） ===
if (DEBUG_MODE) {
    $environmentInfo = [
        'Environment' => ENVIRONMENT,
        'Database Host' => DB_HOST,
        'Database Name' => DB_NAME,
        'Database User' => DB_USER,
        'Base URL' => BASE_URL,
        'PHP Version' => PHP_VERSION,
        'Server Software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
        'Document Root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown',
        'Script Name' => $_SERVER['SCRIPT_NAME'] ?? 'Unknown'
    ];
    
    // ログに環境情報を記録
    error_log("=== Environment Info ===");
    foreach ($environmentInfo as $key => $value) {
        error_log("{$key}: {$value}");
    }
    error_log("========================");
}

// === 設定検証 ===
function validateConfiguration() {
    $requiredConstants = [
        'DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS',
        'ENVIRONMENT', 'BASE_URL', 'SYSTEM_NAME'
    ];
    
    $missing = [];
    foreach ($requiredConstants as $constant) {
        if (!defined($constant)) {
            $missing[] = $constant;
        }
    }
    
    if (!empty($missing)) {
        throw new Exception('Missing required configuration constants: ' . implode(', ', $missing));
    }
    
    // ディレクトリ書き込み権限チェック
    $writableDirectories = [UPLOAD_DIR, TEMP_DIR, LOG_DIR];
    foreach ($writableDirectories as $dir) {
        if (!is_writable($dir)) {
            throw new Exception("Directory is not writable: {$dir}");
        }
    }
    
    return true;
}

// 設定の検証実行
try {
    validateConfiguration();
    
    if (DEBUG_MODE) {
        error_log("Configuration validation passed for environment: " . ENVIRONMENT);
    }
    
} catch (Exception $e) {
    error_log("Configuration Error: " . $e->getMessage());
    
    if (DEBUG_MODE) {
        die("Configuration Error: " . $e->getMessage());
    } else {
        die("システム設定エラーが発生しました。システム管理者にお問い合わせください。");
    }
}

// === 便利関数 ===

/**
 * 環境判定
 */
function isProduction() {
    return ENVIRONMENT === 'production';
}

function isTest() {
    return ENVIRONMENT === 'test';
}

function isLocal() {
    return ENVIRONMENT === 'local';
}

/**
 * URL生成
 */
function baseUrl($path = '') {
    return rtrim(BASE_URL, '/') . '/' . ltrim($path, '/');
}

/**
 * ファイルパス生成
 */
function basePath($path = '') {
    return rtrim(__DIR__ . '/../', '/') . '/' . ltrim($path, '/');
}

/**
 * ログ出力
 */
function logMessage($message, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] {$level}: {$message}";
    
    if (DEBUG_MODE) {
        error_log($logMessage);
    }
    
    // 本番環境では別途ログファイルに記録
    if (isProduction()) {
        file_put_contents(LOG_DIR . 'system.log', $logMessage . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}

/**
 * 設定情報取得
 */
function getSystemInfo() {
    return [
        'system_name' => SYSTEM_NAME,
        'version' => SYSTEM_VERSION,
        'environment' => ENVIRONMENT,
        'php_version' => PHP_VERSION,
        'database' => [
            'host' => DB_HOST,
            'name' => DB_NAME,
            'user' => DB_USER
        ],
        'timezone' => TIMEZONE,
        'debug_mode' => DEBUG_MODE,
        'base_url' => BASE_URL
    ];
}
?>
