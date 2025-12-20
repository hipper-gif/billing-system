<?php
/**
 * AuthManager - 認証管理クラス
 * 
 * 利用者のログイン・ログアウト・セッション管理を行う
 * 
 * @package Smiley配食事業システム
 * @version 1.0
 */

require_once __DIR__ . '/../config/database.php';

class AuthManager {
    private $db;
    private $maxLoginAttempts = 5;
    private $lockoutDuration = 300; // 5分（秒）
    
    /**
     * コンストラクタ
     */
    public function __construct() {
        $this->db = Database::getInstance();
        
        // セッション開始（未開始の場合）
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    /**
     * ログイン処理
     * 
     * @param string $userCode 利用者コード
     * @param string $password パスワード
     * @param bool $rememberMe ログイン状態を保持するか
     * @return array ['success' => bool, 'error' => string, 'user' => array]
     */
    public function login($userCode, $password, $rememberMe = false) {
        try {
            // レート制限チェック
            if ($this->isLocked($userCode)) {
                return [
                    'success' => false,
                    'error' => 'ログイン試行回数が上限に達しました。5分後に再試行してください。'
                ];
            }
            
            // 利用者情報取得
            $user = $this->getUserByCode($userCode);
            
            if (!$user) {
                $this->recordFailedAttempt($userCode);
                return [
                    'success' => false,
                    'error' => '利用者コードまたはパスワードが正しくありません。'
                ];
            }
            
            // 無効なアカウントチェック
            if (!$user['is_active']) {
                return [
                    'success' => false,
                    'error' => 'このアカウントは無効化されています。管理者にお問い合わせください。'
                ];
            }
            
            // パスワード検証
            if (!password_verify($password, $user['password_hash'])) {
                $this->recordFailedAttempt($userCode);
                return [
                    'success' => false,
                    'error' => '利用者コードまたはパスワードが正しくありません。'
                ];
            }
            
            // ログイン成功
            $this->clearFailedAttempts($userCode);
            $this->createSession($user, $rememberMe);
            $this->updateLastLogin($user['id']);
            
            return [
                'success' => true,
                'user' => [
                    'user_id' => $user['id'],
                    'user_code' => $user['user_code'],
                    'user_name' => $user['user_name'],
                    'company_id' => $user['company_id'],
                    'company_name' => $user['company_name'],
                    'department' => $user['department'],
                    'role' => $user['role']
                ]
            ];
            
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'ログイン処理中にエラーが発生しました。'
            ];
        }
    }
    
    /**
     * ログアウト処理
     * 
     * @return bool
     */
    public function logout() {
        // セッションデータを削除
        $_SESSION = [];
        
        // セッションCookieを削除
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }
        
        // セッション破棄
        session_destroy();
        
        return true;
    }
    
    /**
     * セッションチェック
     * 
     * @return bool ログイン中の場合true
     */
    public function isLoggedIn() {
        return isset($_SESSION['user_id']) && 
               isset($_SESSION['user_code']) &&
               isset($_SESSION['role']);
    }
    
    /**
     * 現在のユーザー情報取得
     * 
     * @return array|null ユーザー情報
     */
    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        return [
            'user_id' => $_SESSION['user_id'],
            'user_code' => $_SESSION['user_code'],
            'user_name' => $_SESSION['user_name'],
            'company_id' => $_SESSION['company_id'],
            'company_name' => $_SESSION['company_name'],
            'department' => $_SESSION['department'] ?? '',
            'role' => $_SESSION['role']
        ];
    }
    
    /**
     * 利用者コードからユーザー情報取得
     * 
     * @param string $userCode 利用者コード
     * @return array|null ユーザー情報
     */
    private function getUserByCode($userCode) {
        $sql = "SELECT 
                    id, user_code, user_name, password_hash, 
                    company_id, company_name, department, 
                    role, is_active, is_registered
                FROM users 
                WHERE user_code = :user_code 
                LIMIT 1";
        
        return $this->db->fetch($sql, ['user_code' => $userCode]);
    }
    
    /**
     * セッション作成
     * 
     * @param array $user ユーザー情報
     * @param bool $rememberMe ログイン状態を保持
     */
    private function createSession($user, $rememberMe = false) {
        // セッションIDの再生成（セキュリティ対策）
        session_regenerate_id(true);
        
        // セッション変数に保存
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_code'] = $user['user_code'];
        $_SESSION['user_name'] = $user['user_name'];
        $_SESSION['company_id'] = $user['company_id'];
        $_SESSION['company_name'] = $user['company_name'];
        $_SESSION['department'] = $user['department'] ?? '';
        $_SESSION['role'] = $user['role'];
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();
        
        // ログイン状態を保持する場合
        if ($rememberMe) {
            // セッションの有効期限を30日に設定
            ini_set('session.gc_maxlifetime', 30 * 24 * 60 * 60);
            session_set_cookie_params(30 * 24 * 60 * 60);
        }
    }
    
    /**
     * 最終ログイン日時を更新
     * 
     * @param int $userId ユーザーID
     */
    private function updateLastLogin($userId) {
        $sql = "UPDATE users 
                SET last_login_at = NOW() 
                WHERE id = :user_id";
        
        $this->db->query($sql, ['user_id' => $userId]);
    }
    
    /**
     * ログイン失敗回数を記録
     * 
     * @param string $userCode 利用者コード
     */
    private function recordFailedAttempt($userCode) {
        $key = 'login_attempts_' . $userCode;
        
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = [];
        }
        
        $_SESSION[$key][] = time();
        
        // 古い試行記録を削除（5分以上前）
        $_SESSION[$key] = array_filter($_SESSION[$key], function($timestamp) {
            return (time() - $timestamp) < $this->lockoutDuration;
        });
    }
    
    /**
     * ログイン失敗記録をクリア
     * 
     * @param string $userCode 利用者コード
     */
    private function clearFailedAttempts($userCode) {
        $key = 'login_attempts_' . $userCode;
        unset($_SESSION[$key]);
    }
    
    /**
     * アカウントがロックされているか確認
     * 
     * @param string $userCode 利用者コード
     * @return bool ロックされている場合true
     */
    private function isLocked($userCode) {
        $key = 'login_attempts_' . $userCode;
        
        if (!isset($_SESSION[$key])) {
            return false;
        }
        
        // 5分以内の失敗回数をカウント
        $recentAttempts = array_filter($_SESSION[$key], function($timestamp) {
            return (time() - $timestamp) < $this->lockoutDuration;
        });
        
        return count($recentAttempts) >= $this->maxLoginAttempts;
    }
    
    /**
     * パスワード変更
     * 
     * @param int $userId ユーザーID
     * @param string $currentPassword 現在のパスワード
     * @param string $newPassword 新しいパスワード
     * @return array ['success' => bool, 'error' => string]
     */
    public function changePassword($userId, $currentPassword, $newPassword) {
        try {
            // 現在のパスワード確認
            $sql = "SELECT password_hash FROM users WHERE id = :user_id";
            $user = $this->db->fetch($sql, ['user_id' => $userId]);
            
            if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
                return [
                    'success' => false,
                    'error' => '現在のパスワードが正しくありません。'
                ];
            }
            
            // パスワード強度チェック
            if (strlen($newPassword) < 8) {
                return [
                    'success' => false,
                    'error' => '新しいパスワードは8文字以上で入力してください。'
                ];
            }
            
            if (!preg_match('/[a-zA-Z]/', $newPassword) || !preg_match('/[0-9]/', $newPassword)) {
                return [
                    'success' => false,
                    'error' => '新しいパスワードには英字と数字を含めてください。'
                ];
            }
            
            // パスワード更新
            $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
            
            $sql = "UPDATE users 
                    SET password_hash = :password_hash, 
                        updated_at = NOW() 
                    WHERE id = :user_id";
            
            $this->db->query($sql, [
                'password_hash' => $passwordHash,
                'user_id' => $userId
            ]);
            
            return ['success' => true];
            
        } catch (Exception $e) {
            error_log("Password change error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'パスワード変更中にエラーが発生しました。'
            ];
        }
    }
    
    /**
     * 管理者権限チェック
     * 
     * @return bool 管理者の場合true
     */
    public function isAdmin() {
        if (!$this->isLoggedIn()) {
            return false;
        }
        
        $role = $_SESSION['role'] ?? 'user';
        return in_array($role, ['smiley_staff', 'admin']);
    }
    
    /**
     * 管理者権限を要求
     * 管理者でない場合はリダイレクト
     * 
     * @param string $redirectUrl リダイレクト先URL
     */
    public function requireAdmin($redirectUrl = '../login.php') {
        if (!$this->isAdmin()) {
            header('Location: ' . $redirectUrl);
            exit;
        }
    }
    
    /**
     * セッションタイムアウトチェック
     * 
     * @param int $timeout タイムアウト時間（秒）デフォルト30分
     * @return bool タイムアウトの場合true
     */
    public function checkTimeout($timeout = 1800) {
        if (!isset($_SESSION['last_activity'])) {
            return true;
        }
        
        if ((time() - $_SESSION['last_activity']) > $timeout) {
            return true;
        }
        
        // 最終アクティビティ時刻を更新
        $_SESSION['last_activity'] = time();
        
        return false;
    }
}
