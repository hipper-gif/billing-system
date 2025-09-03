<?php
// config/database.php - 修正版（エックスサーバー実設定対応）
// 環境自動判定による接続設定

$currentHost = $_SERVER['HTTP_HOST'] ?? 'localhost';

// 環境判定とデータベース設定
if (strpos($currentHost, 'twinklemark.xsrv.jp') !== false) {
    // === テスト環境（twinklemark） ===
    // MySQLホスト: localhost（エックスサーバーの標準設定）
    // データベースサーバー: 127.0.0.1 (内部通信)
    define('DB_HOST', 'localhost');              // MySQLサーバーホスト
    define('DB_NAME', 'twinklemark_billing');    // 実際のDB名
    define('DB_USER', 'twinklemark_bill');       // 実際のDBユーザー名（管理画面で確認済み）
    define('DB_PASS', 'Smiley2525');   // 実際のDBパスワード（要設定）
    define('ENVIRONMENT', 'test');
    define('BASE_URL', 'https://twinklemark.xsrv.jp/Smiley/meal-delivery/billing-system/');
    define('DEPLOY_HOST', 'sv16114.xserver.jp'); // デプロイ先サーバー（GitHub Actions用）
    
} elseif (strpos($currentHost, 'tw1nkle.com') !== false) {
    // === 本番環境（tw1nkle） ===
    // デプロイサーバー: sv16114.xserver.jp (GitHub Actions用)
    // データベースサーバー: localhost (実際の接続先)
    define('DB_HOST', 'localhost');              // MySQLサーバーホスト
    define('DB_NAME', 'tw1nkle_billing');        // 本番環境のDB名（要確認）
    define('DB_USER', 'tw1nkle_billing');        // 本番環境のDBユーザー名（要確認）
    define('DB_PASS', 'PRODUCTION_PASSWORD');    // 本番環境のDBパスワード（要設定）
    define('ENVIRONMENT', 'production');
    define('BASE_URL', 'https://tw1nkle.com/Smiley/meal-delivery/billing-system/');
    define('DEPLOY_HOST', 'sv16114.xserver.jp'); // デプロイ先サーバー（GitHub Actions用）
    
} else {
    // === ローカル開発環境 ===
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'billing_local');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('ENVIRONMENT', 'local');
    define('BASE_URL', 'http://localhost/billing-system/');
    define('DEPLOY_HOST', 'localhost');
}

// システム設定
define('SYSTEM_NAME', 'Smiley配食 請求書管理システム');
define('SYSTEM_VERSION', '1.0.0');
define('DEBUG_MODE', ENVIRONMENT !== 'production');

// パス設定
define('BASE_PATH', __DIR__ . '/../');
define('UPLOAD_DIR', BASE_PATH . 'uploads/');
define('TEMP_DIR', BASE_PATH . 'temp/');
define('LOG_DIR', BASE_PATH . 'logs/');
define('CACHE_DIR', BASE_PATH . 'cache/');
define('BACKUP_DIR', BASE_PATH . 'backups/');

// セキュリティ設定
define('SESSION_TIMEOUT', 3600);               // 1時間
define('MAX_LOGIN_ATTEMPTS', 5);
define('PASSWORD_MIN_LENGTH', 8);
define('CSRF_TOKEN_EXPIRE', 1800);             // 30分

// ファイル設定
define('UPLOAD_MAX_SIZE', 10 * 1024 * 1024);   // 10MB
define('ALLOWED_FILE_TYPES', ['csv', 'xlsx']);
define('CSV_ENCODING', ['UTF-8', 'SJIS-win', 'EUC-JP']);

// PDF設定
define('PDF_FONT', 'kozgopromedium');          // 日本語フォント
define('PDF_AUTHOR', 'Smiley配食事業株式会社');
define('PDF_CREATOR', 'Smiley配食請求書管理システム');

// メール設定（環境別）
if (ENVIRONMENT === 'production') {
    define('MAIL_FROM', 'noreply@tw1nkle.com');
    define('MAIL_FROM_NAME', 'Smiley配食事業');
    define('MAIL_SMTP_HOST', 'sv16114.xserver.jp');
} else {
    define('MAIL_FROM', 'test@twinklemark.xsrv.jp');
    define('MAIL_FROM_NAME', 'Smiley配食事業（テスト）');
    define('MAIL_SMTP_HOST', 'sv16114.xserver.jp');
}

// データベース接続テスト（開発・デバッグ用）
if (defined('TEST_DB_CONNECTION') && TEST_DB_CONNECTION === true) {
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
        
        // 接続テスト
        $result = $testPdo->query("SELECT 1 as connection_test")->fetch();
        if ($result['connection_test'] === 1) {
            echo "✅ データベース接続テスト: 成功\n";
        }
        
        $testPdo = null; // 接続を閉じる
        
    } catch (PDOException $e) {
        echo "❌ データベース接続テスト: 失敗\n";
        echo "エラー: " . $e->getMessage() . "\n";
        
        // 詳細なデバッグ情報
        echo "設定情報:\n";
        echo "- ホスト: " . DB_HOST . "\n";
        echo "- DB名: " . DB_NAME . "\n";
        echo "- ユーザー: " . DB_USER . "\n";
        echo "- 環境: " . ENVIRONMENT . "\n";
    }
}

// ログ設定
if (!defined('LOG_LEVEL')) {
    switch (ENVIRONMENT) {
        case 'production':
            define('LOG_LEVEL', 'WARNING');
            break;
        case 'test':
            define('LOG_LEVEL', 'INFO');
            break;
        default:
            define('LOG_LEVEL', 'DEBUG');
    }
}

// キャッシュ設定
define('CACHE_ENABLED', ENVIRONMENT === 'production');
define('CACHE_LIFETIME', 3600); // 1時間

// レート制限設定（API用）
define('API_RATE_LIMIT', ENVIRONMENT === 'production' ? 100 : 1000); // 1時間あたりのリクエスト数
define('API_RATE_WINDOW', 3600); // 1時間

// 📝 設定メモ
/*
=== 重要な修正点 ===
1. DB_HOST: 'mysql1.xserver.jp' → 'localhost' に変更
   - エックスサーバーでは一般的にlocalhostを使用
   
2. DB_USER: 'twinklemark_billing' → 'twinklemark_bill' に変更
   - 管理画面で確認したユーザー名に合わせる
   
3. パスワード設定が必要
   - 'ACTUAL_PASSWORD_HERE' を実際のパスワードに置換
   
=== 次の作業 ===
1. 実際のパスワードを設定
2. ファイルをサーバーにアップロード
3. 接続テスト実行
4. システム動作確認
*/
?>
