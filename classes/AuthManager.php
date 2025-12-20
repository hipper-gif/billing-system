<?php
/**
 * 認証API
 * 
 * ログイン・ログアウト・セッション確認を処理
 * 
 * @package Smiley配食事業システム
 * @version 1.0
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/AuthManager.php';
require_once __DIR__ . '/../classes/SecurityHelper.php';

// アクション取得
$action = $_GET['action'] ?? '';

$authManager = new AuthManager();

try {
    switch ($action) {
        case 'login':
            handleLogin($authManager);
            break;
            
        case 'logout':
            handleLogout($authManager);
            break;
            
        case 'check_session':
            handleCheckSession($authManager);
            break;
            
        default:
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => '無効なアクションです'
            ]);
    }
    
} catch (Exception $e) {
    error_log("Auth API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'サーバーエラーが発生しました'
    ]);
}

/**
 * ログイン処理
 * 
 * @param AuthManager $authManager
 */
function handleLogin($authManager) {
    // POSTリクエストのみ受付
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'error' => '無効なリクエストメソッドです'
        ]);
        return;
    }
    
    // リクエストデータ取得
    $input = json_decode(file_get_contents('php://input'), true);
    
    // 入力値検証
    $errors = [];
    
    if (empty($input['user_code'])) {
        $errors['user_code'] = '利用者コードを入力してください';
    }
    
    if (empty($input['password'])) {
        $errors['password'] = 'パスワードを入力してください';
    }
    
    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'errors' => $errors
        ]);
        return;
    }
    
    // ログイン実行
    $userCode = SecurityHelper::sanitizeInput($input['user_code']);
    $password = $input['password'];
    $rememberMe = $input['remember_me'] ?? false;
    
    $result = $authManager->login($userCode, $password, $rememberMe);
    
    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'data' => $result['user'],
            'message' => 'ログインしました'
        ]);
    } else {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => $result['error']
        ]);
    }
}

/**
 * ログアウト処理
 * 
 * @param AuthManager $authManager
 */
function handleLogout($authManager) {
    $authManager->logout();
    
    echo json_encode([
        'success' => true,
        'message' => 'ログアウトしました'
    ]);
}

/**
 * セッション確認
 * 
 * @param AuthManager $authManager
 */
function handleCheckSession($authManager) {
    // タイムアウトチェック
    if ($authManager->checkTimeout()) {
        $authManager->logout();
        echo json_encode([
            'success' => false,
            'logged_in' => false,
            'error' => 'セッションがタイムアウトしました'
        ]);
        return;
    }
    
    // ログイン状態確認
    if ($authManager->isLoggedIn()) {
        $user = $authManager->getCurrentUser();
        echo json_encode([
            'success' => true,
            'logged_in' => true,
            'user' => $user
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'logged_in' => false
        ]);
    }
}
