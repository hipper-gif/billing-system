<?php
/**
 * SecurityHelper - セキュリティ機能統合版
 * 
 * @version 2.0.0 - v5.0仕様準拠・CSP修正版
 * @updated 2025-10-06
 * @changes CDN許可リストを拡張（cdn.jsdelivr.net + cdnjs.cloudflare.com）
 */

class SecurityHelper {
    
    /**
     * セキュリティヘッダーを設定
     */
    public static function setSecurityHeaders() {
        // すでにヘッダーが送信されている場合はスキップ
        if (headers_sent()) {
            return;
        }

        // Content Security Policy (CSP) - 開発・本番両対応版
        // 複数のCDNを許可し、開発しやすく設定
        $csp = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com",
            "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com",
            "font-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com data:",
            "img-src 'self' data: https:",
            "connect-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com",
            "frame-ancestors 'self'",
            "base-uri 'self'",
            "form-action 'self'"
        ];
        
        header("Content-Security-Policy: " . implode('; ', $csp));
        
        // その他のセキュリティヘッダー
        header("X-Content-Type-Options: nosniff");
        header("X-Frame-Options: SAMEORIGIN");
        header("X-XSS-Protection: 1; mode=block");
        header("Referrer-Policy: strict-origin-when-cross-origin");
        
        // HTTPS強制（本番環境のみ）
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
        }
    }
    
    /**
     * XSS対策 - 入力値のサニタイズ
     * 
     * @param mixed $input サニタイズ対象
     * @return mixed サニタイズ済みデータ
     */
    public static function sanitizeInput($input) {
        if (is_array($input)) {
            return array_map([self::class, 'sanitizeInput'], $input);
        }
        
        if (is_string($input)) {
            return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
        }
        
        return $input;
    }
    
    /**
     * CSRF対策 - トークン生成
     * 
     * @return string CSRFトークン
     */
    public static function generateToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;
        $_SESSION['csrf_token_time'] = time();
        
        return $token;
    }
    
    /**
     * CSRF対策 - トークン検証
     * 
     * @param string $token 検証対象トークン
     * @return bool 検証結果
     */
    public static function validateToken($token) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // トークンが存在しない
        if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time'])) {
            return false;
        }
        
        // トークンの有効期限（1時間）
        if (time() - $_SESSION['csrf_token_time'] > 3600) {
            unset($_SESSION['csrf_token']);
            unset($_SESSION['csrf_token_time']);
            return false;
        }
        
        // トークン検証
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * ファイルアップロード安全性検証
     * 
     * @param array $file $_FILES配列の要素
     * @return array エラーメッセージ配列（空なら検証成功）
     */
    public static function validateUploadedFile($file) {
        $errors = [];
        
        // ファイルが存在しない
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            $errors[] = 'ファイルが正しくアップロードされていません';
            return $errors;
        }
        
        // ファイルサイズチェック（10MB）
        if ($file['size'] > 10 * 1024 * 1024) {
            $errors[] = 'ファイルサイズが大きすぎます（10MB以下にしてください）';
        }
        
        // 拡張子チェック
        $allowedExtensions = ['csv', 'txt'];
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedExtensions)) {
            $errors[] = '許可されていないファイル形式です（CSV、TXTのみ）';
        }
        
        // MIMEタイプチェック
        $allowedMimes = ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, $allowedMimes)) {
            $errors[] = 'ファイル内容が不正です（CSVファイルをアップロードしてください）';
        }
        
        return $errors;
    }
    
    /**
     * パスワードハッシュ生成
     * 
     * @param string $password 平文パスワード
     * @return string ハッシュ化されたパスワード
     */
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost' => 4,
            'threads' => 3
        ]);
    }
    
    /**
     * パスワード検証
     * 
     * @param string $password 平文パスワード
     * @param string $hash ハッシュ化されたパスワード
     * @return bool 検証結果
     */
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    /**
     * SQLインジェクション対策 - 入力値検証
     * 
     * @param string $input 検証対象文字列
     * @return bool 危険な文字列が含まれているか
     */
    public static function containsSqlInjection($input) {
        $patterns = [
            '/(\bunion\b.*\bselect\b)/i',
            '/(\bselect\b.*\bfrom\b)/i',
            '/(\binsert\b.*\binto\b)/i',
            '/(\bupdate\b.*\bset\b)/i',
            '/(\bdelete\b.*\bfrom\b)/i',
            '/(\bdrop\b.*\btable\b)/i',
            '/;.*--/',
            '/\/\*.*\*\//'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * XSS対策 - 出力エスケープ
     * 
     * @param string $value 出力する値
     * @return string エスケープ済み値
     */
    public static function escape($value) {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * JSONレスポンスのセキュリティヘッダー設定
     */
    public static function setJsonHeaders() {
        if (headers_sent()) {
            return;
        }
        
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
    }
    
    /**
     * セッション開始（セキュア設定）
     */
    public static function startSecureSession() {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        
        // セッション設定
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_samesite', 'Strict');
        
        // HTTPS環境ではセキュアクッキーを使用
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            ini_set('session.cookie_secure', 1);
        }
        
        session_start();
        
        // セッションハイジャック対策
        if (!isset($_SESSION['initiated'])) {
            session_regenerate_id(true);
            $_SESSION['initiated'] = true;
            $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $_SESSION['remote_addr'] = $_SERVER['REMOTE_ADDR'] ?? '';
        }
        
        // ユーザーエージェントとIPアドレスの検証
        if (isset($_SESSION['user_agent']) && $_SESSION['user_agent'] !== ($_SERVER['HTTP_USER_AGENT'] ?? '')) {
            session_destroy();
            session_start();
        }
    }
    
    /**
     * レート制限チェック（簡易版）
     * 
     * @param string $key レート制限のキー
     * @param int $limit 制限回数
     * @param int $period 期間（秒）
     * @return bool 制限内か
     */
    public static function checkRateLimit($key, $limit = 10, $period = 60) {
        if (session_status() === PHP_SESSION_NONE) {
            self::startSecureSession();
        }
        
        $now = time();
        $rateLimitKey = 'rate_limit_' . $key;
        
        if (!isset($_SESSION[$rateLimitKey])) {
            $_SESSION[$rateLimitKey] = [
                'count' => 1,
                'start' => $now
            ];
            return true;
        }
        
        $data = $_SESSION[$rateLimitKey];
        
        // 期間をリセット
        if ($now - $data['start'] > $period) {
            $_SESSION[$rateLimitKey] = [
                'count' => 1,
                'start' => $now
            ];
            return true;
        }
        
        // 制限チェック
        if ($data['count'] >= $limit) {
            return false;
        }
        
        // カウント増加
        $_SESSION[$rateLimitKey]['count']++;
        return true;
    }
}
