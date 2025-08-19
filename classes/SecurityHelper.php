<?php
/**
 * セキュリティヘルパークラス
 * classes/SecurityHelper.php
 */

class SecurityHelper {
    
    /**
     * CSRFトークン生成
     */
    public static function generateCSRFToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;
        $_SESSION['csrf_token_time'] = time();
        
        return $token;
    }
    
    /**
     * CSRFトークン検証
     */
    public static function validateCSRFToken($token) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // トークンが存在しない場合
        if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time'])) {
            return false;
        }
        
        // トークンの有効期限チェック（1時間）
        if (time() - $_SESSION['csrf_token_time'] > 3600) {
            unset($_SESSION['csrf_token']);
            unset($_SESSION['csrf_token_time']);
            return false;
        }
        
        // トークン比較
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * ファイルアップロード検証
     */
    public static function validateFileUpload($file) {
        $errors = [];
        
        // ファイルがアップロードされているか
        if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
            $errors[] = 'ファイルがアップロードされていません';
            return ['valid' => false, 'errors' => $errors];
        }
        
        // アップロードエラーチェック
        if ($file['error'] !== UPLOAD_ERR_OK) {
            switch ($file['error']) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    $errors[] = 'ファイルサイズが大きすぎます';
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $errors[] = 'ファイルのアップロードが中断されました';
                    break;
                case UPLOAD_ERR_NO_FILE:
                    $errors[] = 'ファイルが選択されていません';
                    break;
                default:
                    $errors[] = 'ファイルアップロードでエラーが発生しました';
            }
            return ['valid' => false, 'errors' => $errors];
        }
        
        // ファイルサイズチェック（最大10MB）
        if ($file['size'] > 10 * 1024 * 1024) {
            $errors[] = 'ファイルサイズは10MB以下にしてください';
        }
        
        // CSV拡張子チェック
        $allowedExtensions = ['csv', 'txt'];
        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($fileExtension, $allowedExtensions)) {
            $errors[] = 'CSVファイル(.csv)をアップロードしてください';
        }
        
        // MIMEタイプチェック
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        $allowedMimeTypes = [
            'text/csv',
            'text/plain',
            'application/csv',
            'text/comma-separated-values'
        ];
        
        if (!in_array($mimeType, $allowedMimeTypes)) {
            $errors[] = 'ファイル形式が正しくありません（CSV形式のみ対応）';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * 入力値サニタイズ
     */
    public static function sanitize($input) {
        if (is_string($input)) {
            return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
        }
        
        if (is_array($input)) {
            return array_map([self::class, 'sanitize'], $input);
        }
        
        return $input;
    }
    
    /**
     * SQLインジェクション対策用エスケープ
     */
    public static function escapeSql($input) {
        return addslashes($input);
    }
    
    /**
     * パスワードハッシュ化
     */
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_DEFAULT);
    }
    
    /**
     * パスワード検証
     */
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    /**
     * セッション安全開始
     */
    public static function secureSessionStart() {
        if (session_status() === PHP_SESSION_NONE) {
            // セッション設定
            ini_set('session.cookie_httponly', 1);
            ini_set('session.use_only_cookies', 1);
            ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
            
            session_start();
            
            // セッション固定化攻撃対策
            if (!isset($_SESSION['initiated'])) {
                session_regenerate_id(true);
                $_SESSION['initiated'] = true;
            }
        }
    }
    
    /**
     * IPアドレス取得
     */
    public static function getRealIpAddress() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            return $_SERVER['REMOTE_ADDR'];
        }
    }
    
    /**
     * ログ記録
     */
    public static function logSecurityEvent($event, $details = []) {
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'ip' => self::getRealIpAddress(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'event' => $event,
            'details' => $details
        ];
        
        $logFile = __DIR__ . '/../logs/security.log';
        $logDir = dirname($logFile);
        
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        file_put_contents($logFile, json_encode($logData, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);
    }
}
?>
