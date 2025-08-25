<?php
/**
 * SecurityHelper クラス（修正版）
 * Smiley配食事業システム用セキュリティユーティリティ
 * ファイルアップロード検証機能を強化
 */

class SecurityHelper {
    
    /**
     * セキュアセッション開始
     */
    public static function secureSessionStart() {
        if (session_status() === PHP_SESSION_NONE) {
            // セキュアなセッション設定
            ini_set('session.cookie_httponly', 1);
            ini_set('session.use_only_cookies', 1);
            ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
            ini_set('session.cookie_samesite', 'Strict');
            
            session_start();
            
            // セッション固定攻撃対策
            if (!isset($_SESSION['initiated'])) {
                session_regenerate_id(true);
                $_SESSION['initiated'] = true;
            }
        }
    }
    
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
     * ファイルアップロードの安全性チェック（CSVインポート用）
     */
    public static function validateFileUpload($file, $options = []) {
        $maxSize = $options['max_size'] ?? (10 * 1024 * 1024); // デフォルト10MB
        $allowedTypes = $options['allowed_types'] ?? [
            'text/csv',
            'text/plain',
            'application/csv',
            'application/vnd.ms-excel'
        ];
        $allowedExtensions = $options['allowed_extensions'] ?? ['csv', 'txt'];
        
        $result = [
            'valid' => true,
            'errors' => []
        ];
        
        // ファイルがアップロードされているかチェック
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            $result['valid'] = false;
            $result['errors'][] = 'ファイルが正常にアップロードされませんでした。';
            return $result;
        }
        
        // アップロードエラーチェック
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $result['valid'] = false;
            $result['errors'][] = self::getUploadErrorMessage($file['error']);
        }
        
        // ファイルサイズチェック
        if ($file['size'] > $maxSize) {
            $result['valid'] = false;
            $result['errors'][] = 'ファイルサイズが大きすぎます。(' . number_format($maxSize / 1024 / 1024, 1) . 'MB以下)';
        }
        
        // 拡張子チェック
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedExtensions)) {
            $result['valid'] = false;
            $result['errors'][] = '許可されていない拡張子です: .' . $extension;
        }
        
        // MIMEタイプチェック（実際のファイル内容を確認）
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $detectedType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            if (!in_array($detectedType, $allowedTypes)) {
                $result['valid'] = false;
                $result['errors'][] = '許可されていないファイル形式です: ' . $detectedType;
            }
        }
        
        // ファイル名の安全性チェック
        if (preg_match('/[<>:"|?*\\\\\/]/', $file['name'])) {
            $result['valid'] = false;
            $result['errors'][] = 'ファイル名に使用できない文字が含まれています。';
        }
        
        // ファイル内容の安全性チェック
        $contentCheck = self::checkFileContent($file['tmp_name']);
        if (!$contentCheck['safe']) {
            $result['valid'] = false;
            $result['errors'] = array_merge($result['errors'], $contentCheck['errors']);
        }
        
        return $result;
    }
    
    /**
     * ファイル内容の安全性チェック
     */
    private static function checkFileContent($tmpPath) {
        $result = [
            'safe' => true,
            'errors' => []
        ];
        
        $handle = fopen($tmpPath, 'r');
        if ($handle) {
            $firstBytes = fread($handle, 1024);
            fclose($handle);
            
            // 実行可能ファイルの署名をチェック
            $dangerousSignatures = [
                "\x4D\x5A" => 'PE executable',
                "\x7F\x45\x4C\x46" => 'ELF executable',
                "<?php" => 'PHP script',
                "<script" => 'JavaScript',
                "<%" => 'ASP/JSP',
            ];
            
            foreach ($dangerousSignatures as $signature => $type) {
                if (strpos($firstBytes, $signature) === 0 || strpos($firstBytes, $signature) !== false) {
                    $result['safe'] = false;
                    $result['errors'][] = '危険なファイル内容が検出されました: ' . $type;
                }
            }
            
            // CSVファイルとして妥当かチェック
            if (!self::isValidCSVContent($firstBytes)) {
                $result['safe'] = false;
                $result['errors'][] = '有効なCSVファイルではありません。';
            }
        } else {
            $result['safe'] = false;
            $result['errors'][] = 'ファイル内容を確認できませんでした。';
        }
        
        return $result;
    }
    
    /**
     * CSV内容の妥当性チェック
     */
    private static function isValidCSVContent($content) {
        // 最初の行がCSVらしいかチェック
        $firstLine = strtok($content, "\n");
        if (empty($firstLine)) {
            return false;
        }
        
        // カンマまたはタブ区切りがあるかチェック
        $commaCount = substr_count($firstLine, ',');
        $tabCount = substr_count($firstLine, "\t");
        
        // 最低2つのフィールドがあることを確認
        return ($commaCount > 0 || $tabCount > 0);
    }
    
    /**
     * アップロードエラーメッセージ取得
     */
    private static function getUploadErrorMessage($errorCode) {
        $messages = [
            UPLOAD_ERR_INI_SIZE => 'ファイルサイズがPHP設定の上限を超えています',
            UPLOAD_ERR_FORM_SIZE => 'ファイルサイズがフォームの上限を超えています',
            UPLOAD_ERR_PARTIAL => 'ファイルが部分的にしかアップロードされませんでした',
            UPLOAD_ERR_NO_FILE => 'ファイルがアップロードされませんでした',
            UPLOAD_ERR_NO_TMP_DIR => '一時ディレクトリが見つかりません',
            UPLOAD_ERR_CANT_WRITE => 'ディスクへの書き込みに失敗しました',
            UPLOAD_ERR_EXTENSION => 'PHPの拡張機能によってアップロードが停止されました'
        ];
        
        return $messages[$errorCode] ?? '不明なアップロードエラーが発生しました';
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
            self::secureSessionStart();
        }
        
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;
        $_SESSION['csrf_token_time'] = time();
        
        return $token;
    }
    
    /**
     * CSRFトークンの検証
     */
    public static function validateCSRFToken($token) {
        if (session_status() === PHP_SESSION_NONE) {
            self::secureSessionStart();
        }
        
        if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time'])) {
            return false;
        }
        
        // トークンの有効期限チェック（1時間）
        if (time() - $_SESSION['csrf_token_time'] > 3600) {
            unset($_SESSION['csrf_token']);
            unset($_SESSION['csrf_token_time']);
            return false;
        }
        
        $isValid = hash_equals($_SESSION['csrf_token'], $token);
        
        // トークン使用後は削除（ワンタイムトークン）
        if ($isValid) {
            unset($_SESSION['csrf_token']);
            unset($_SESSION['csrf_token_time']);
        }
        
        return $isValid;
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
     * セキュリティイベントログ出力
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
    
    /**
     * 安全なランダム文字列生成
     */
    public static function generateRandomString($length = 32) {
        return bin2hex(random_bytes($length / 2));
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
     * レート制限チェック（簡易版）
     */
    public static function checkRateLimit($key, $limit = 60, $window = 3600) {
        if (session_status() === PHP_SESSION_NONE) {
            self::secureSessionStart();
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
}
?>
