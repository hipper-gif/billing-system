<?php
/**
 * 利用者即時登録API
 * 
 * QRコードから遷移してきた利用者を即座に登録するAPI
 * 
 * @package Smiley配食事業システム
 * @version 1.0
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/UserCodeGenerator.php';
require_once __DIR__ . '/../classes/SecurityHelper.php';

// POSTリクエストのみ受付
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => '無効なリクエストメソッドです'
    ]);
    exit;
}

try {
    // リクエストデータ取得
    $input = json_decode(file_get_contents('php://input'), true);
    
    // トークン検証
    $companyId = verifyToken($input['company_token'] ?? '');
    
    if (!$companyId) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => '無効な登録URLです。QRコードを再度読み取ってください。'
        ]);
        exit;
    }
    
    // 入力値のバリデーション
    $errors = validateInput($input);
    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'errors' => $errors
        ]);
        exit;
    }
    
    // データベース接続
    $db = Database::getInstance();
    $userCodeGen = new UserCodeGenerator();
    
    // トランザクション開始
    $db->beginTransaction();
    
    // 利用者コード生成
    $userCode = $userCodeGen->generateUserCode($companyId);
    
    // パスワードハッシュ化
    $passwordHash = $userCodeGen->hashPassword($input['password']);
    
    // 利用者情報を挿入
    $userId = insertUser($db, $companyId, $userCode, $passwordHash, $input);
    
    // トークンを使用済みにマーク（オプション）
    // markTokenAsUsed($db, $input['company_token']);
    
    // コミット
    $db->commit();
    
    // 自動ログイン用のセッション設定
    $_SESSION['user_id'] = $userId;
    $_SESSION['user_code'] = $userCode;
    $_SESSION['user_name'] = $input['user_name'];
    $_SESSION['company_id'] = $companyId;
    $_SESSION['role'] = 'user';
    
    // 企業情報取得
    $company = getCompanyInfo($db, $companyId);
    
    // 成功レスポンス
    echo json_encode([
        'success' => true,
        'data' => [
            'user_id' => $userId,
            'user_code' => $userCode,
            'user_name' => SecurityHelper::sanitizeInput($input['user_name']),
            'company_name' => $company['company_name'],
            'department' => SecurityHelper::sanitizeInput($input['department'] ?? ''),
            'auto_login' => true
        ],
        'message' => '登録が完了しました'
    ]);
    
} catch (Exception $e) {
    // ロールバック
    if (isset($db)) {
        $db->rollback();
    }
    
    // エラーログ記録
    error_log("利用者登録エラー: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => '登録中にエラーが発生しました',
        'debug' => $e->getMessage() // 開発環境のみ
    ]);
}

/**
 * トークンを検証して企業IDを取得
 * 
 * @param string $token 企業登録トークン
 * @return int|null 企業ID
 */
function verifyToken($token) {
    if (empty($token)) {
        return null;
    }
    
    $db = Database::getInstance();
    
    $sql = "SELECT company_id, expires_at 
            FROM company_signup_tokens 
            WHERE token = :token AND is_active = 1";
    
    $tokenInfo = $db->fetch($sql, ['token' => $token]);
    
    if (!$tokenInfo) {
        return null;
    }
    
    // 有効期限チェック
    if ($tokenInfo['expires_at'] !== null) {
        $expiresAt = strtotime($tokenInfo['expires_at']);
        if ($expiresAt < time()) {
            return null; // 期限切れ
        }
    }
    
    return $tokenInfo['company_id'];
}

/**
 * 入力値のバリデーション
 * 
 * @param array $input 入力データ
 * @return array エラー配列
 */
function validateInput($input) {
    $errors = [];
    
    // 氏名チェック
    if (empty($input['user_name'])) {
        $errors['user_name'] = '氏名は必須です';
    } elseif (mb_strlen($input['user_name']) > 100) {
        $errors['user_name'] = '氏名は100文字以内で入力してください';
    }
    
    // パスワードチェック
    if (empty($input['password'])) {
        $errors['password'] = 'パスワードは必須です';
    } else {
        $userCodeGen = new UserCodeGenerator();
        $passwordValidation = $userCodeGen->validatePassword($input['password']);
        
        if (!$passwordValidation['valid']) {
            $errors['password'] = implode('、', $passwordValidation['errors']);
        }
    }
    
    // パスワード確認チェック
    if (!empty($input['password']) && $input['password'] !== ($input['password_confirm'] ?? '')) {
        $errors['password_confirm'] = 'パスワードが一致しません';
    }
    
    // 部署名チェック（任意）
    if (!empty($input['department']) && mb_strlen($input['department']) > 100) {
        $errors['department'] = '部署名は100文字以内で入力してください';
    }
    
    return $errors;
}

/**
 * 利用者情報を挿入
 * 
 * @param Database $db データベース接続
 * @param int $companyId 企業ID
 * @param string $userCode 利用者コード
 * @param string $passwordHash パスワードハッシュ
 * @param array $input 入力データ
 * @return int 挿入された利用者ID
 */
function insertUser($db, $companyId, $userCode, $passwordHash, $input) {
    // 企業情報取得（company_nameを設定するため）
    $company = getCompanyInfo($db, $companyId);
    
    $sql = "INSERT INTO users (
        user_code,
        user_name,
        company_id,
        company_name,
        department,
        password_hash,
        role,
        is_active,
        is_registered,
        registered_at,
        created_at,
        updated_at
    ) VALUES (
        :user_code,
        :user_name,
        :company_id,
        :company_name,
        :department,
        :password_hash,
        'user',
        1,
        1,
        NOW(),
        NOW(),
        NOW()
    )";
    
    $params = [
        'user_code' => $userCode,
        'user_name' => SecurityHelper::sanitizeInput($input['user_name']),
        'company_id' => $companyId,
        'company_name' => $company['company_name'],
        'department' => SecurityHelper::sanitizeInput($input['department'] ?? ''),
        'password_hash' => $passwordHash
    ];
    
    $db->query($sql, $params);
    
    return $db->lastInsertId();
}

/**
 * 企業情報を取得
 * 
 * @param Database $db データベース接続
 * @param int $companyId 企業ID
 * @return array 企業情報
 */
function getCompanyInfo($db, $companyId) {
    $sql = "SELECT company_code, company_name 
            FROM companies 
            WHERE id = :id";
    
    return $db->fetch($sql, ['id' => $companyId]);
}
