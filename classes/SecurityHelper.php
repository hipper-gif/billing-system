<?php
/**
 * SecurityHelper クラス
 * Smiley配食事業システム用セキュリティユーティリティ
 */

class SecurityHelper {
    
    /**
     * セキュリティヘッダーを設定
     */
    public static function setSecurityHeaders() {
        // XSS攻撃対策
        header('X-XSS-Protection: 1; mode=block');
        
        // クリックジャッキング対策
        header('X-Frame-Options: DENY');
        
        // MIME-typeスニッフィング対策
        header('X-Content-Type-Options: nosniff');
        
        // リファラーポリシー
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // HTTPS強制（本番環境のみ）
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
        
        // コンテンツセキュリティポリシー（基本的な設定）
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; img-src 'self' data: https:; font-src 'self' https://cdn.jsdelivr.net;");
    }
    
    /**
     * 入力値のサニタイズ
     */
    public static function sanitizeInput($input, $type = 'string') {
        if (is_array($input)) {
            return array_map(function($item) use ($type) {
                return self::sanitizeInput($item, $type);
            }, $input);
        }
        
        switch ($type) {
            case 'string':
                return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
            case 'email':
                return filter_var(trim($input), FILTER_SANITIZE_EMAIL);
            case 'int':
                return filter_var($input, FILTER_SANITIZE_NUMBER_INT);
            case 'float':
                return filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            case 'url':
                return filter_var(trim($input), FILTER_SANITIZE_URL);
            case 'filename':
                // ファイル名用の安全化
                return preg_replace('/[^a-zA-Z0-9._-]/', '', basename($input));
            default:
                return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
        }
    }
    
    /**
     * 入力値の検証
     */
    public static function validateInput($input, $rules) {
        $errors = [];
        
        foreach ($rules as $field => $rule) {
            $value = $input[$field] ?? null;
            
            // 必須チェック
            if (isset($rule['required']) && $rule['required'] && empty($value)) {
                $errors[$field] = $field . 'は必須項目です。';
                continue;
            }
            
            // 空の場合はスキップ（必須チェック以外）
            if (empty($value)) {
                continue;
            }
            
            // 最小長チェック
            if (isset($rule['min_length']) && strlen($value) < $rule['min_length']) {
                $errors[$field] = $field . 'は' . $rule['min_length'] . '文字以上で入力してください。';
            }
            
            // 最大長チェック
            if (isset($rule['max_length']) && strlen($value) > $rule['max_length']) {
                $errors[$field] = $field . 'は' . $rule['max_length'] . '文字以下で入力してください。';
            }
            
            // 型チェック
            if (isset($rule['type'])) {
                switch ($rule['type']) {
                    case 'email':
                        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            $errors[$field] = $field . 'の形式が正しくありません。';
                        }
                        break;
                    case 'int':
                        if (!filter_var($value, FILTER_VALIDATE_INT)) {
                            $errors[$field] = $field . 'は整数で入力してください。';
                        }
                        break;
                    case 'float':
                        if (!filter_var($value, FILTER_VALIDATE_FLOAT)) {
                            $errors[$field] = $field . 'は数値で入力してください。';
                        }
                        break;
                    case 'url':
                        if (!filter_var($value, FILTER_VALIDATE_URL)) {
                            $errors[$field] = $field . 'のURL形式が正しくありません。';
                        }
                        break;
                    case 'date':
                        if (!self::validateDate($value)) {
                            $errors[$field] = $field . 'の日付形式が正しくありません。';
                        }
                        break;
                }
            }
            
            // 正規表現チェック
            if (isset($rule['pattern']) && !preg_match($rule['pattern'], $value)) {
                $errors[$field] = $field . 'の形式が正しくありません。';
            }
        }
        
        return $errors;
    }
    
    /**
     * 日付形式の検証
     */
    public static function validateDate($date, $format = 'Y-m-d') {
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }
    
    /**
     * CSRFトークンの生成
     */
    public static function generateCSRFToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;
        return $token;
    }
    
    /**
     * CSRFトークンの検証
     */
    public static function validateCSRFToken($token) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }
        
        $isValid = hash_equals($_SESSION['csrf_token'], $token);
        
        // トークンを使い回さないために削除
        unset($_SESSION['csrf_token']);
        
        return $isValid;
    }
    
    /**
     * SQLインジェクション対策用のパラメータ準備
     */
    public static function prepareSqlParams($data, $allowedFields = []) {
        $params = [];
        
        foreach ($data as $key => $value) {
            // 許可されたフィールドのみ
            if (!empty($allowedFields) && !in_array($key, $allowedFields)) {
                continue;
            }
            
            // キー名の検証（英数字とアンダースコアのみ）
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $key)) {
                continue;
            }
            
            $params[$key] = $value;
        }
        
        return $params;
    }
    
    /**
     * ファイルアップロードの安全性チェック
     */
    public static function validateUploadedFile($file, $allowedTypes = [], $maxSize = 5242880) {
        $errors = [];
        
        // ファイルがアップロードされているかチェック
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            $errors[] = 'ファイルが正常にアップロードされませんでした。';
            return $errors;
        }
        
        // ファイルサイズチェック
        if ($file['size'] > $maxSize) {
            $errors[] = 'ファイルサイズが大きすぎます。(' . number_format($maxSize / 1024 / 1024, 1) . 'MB以下)';
        }
        
        // MIMEタイプチェック
        if (!empty($allowedTypes)) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            if (!in_array($mimeType, $allowedTypes)) {
                $errors[] = '許可されていないファイル形式です。';
            }
        }
        
        // ファイル名の安全性チェック
        $filename = $file['name'];
        if (preg_match('/[<>:"|?*]/', $filename)) {
            $errors[] = 'ファイル名に使用できない文字が含まれています。';
        }
        
        return $errors;
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
     * 安全なランダム文字列生成
     */
    public static function generateRandomString($length = 32) {
        return bin2hex(random_bytes($length / 2));
    }
    
    /**
     * IPアドレスの取得（プロキシ対応）
     */
    public static function getClientIP() {
        $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                $ip = trim($ips[0]);
                
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * レート制限チェック（簡易版）
     */
    public static function checkRateLimit($key, $limit = 60, $window = 3600) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $now = time();
        $sessionKey = 'rate_limit_' . $key;
        
        if (!isset($_SESSION[$sessionKey])) {
            $_SESSION[$sessionKey] = ['count' => 1, 'start' => $now];
            return true;
        }
        
        $data = $_SESSION[$sessionKey];
        
        // ウィンドウリセット
        if ($now - $data['start'] > $window) {
            $_SESSION[$sessionKey] = ['count' => 1, 'start' => $now];
            return true;
        }
        
        // 制限チェック
        if ($data['count'] >= $limit) {
            return false;
        }
        
        $_SESSION[$sessionKey]['count']++;
        return true;
    }
    
    /**
     * ログ出力（セキュリティイベント用）
     */
    public static function logSecurityEvent($event, $details = []) {
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'event' => $event,
            'ip' => self::getClientIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'details' => $details
        ];
        
        $logFile = __DIR__ . '/../logs/security.log';
        $logDir = dirname($logFile);
        
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        error_log(json_encode($logData, JSON_UNESCAPED_UNICODE) . "\n", 3, $logFile);
    }
}
?>
