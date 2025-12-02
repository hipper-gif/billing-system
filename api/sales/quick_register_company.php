<?php
/**
 * 企業クイック登録API
 * 
 * 営業スタッフが営業先でその場で企業を登録し、
 * QRコードを即座に発行するためのAPI
 * 
 * @package Smiley配食事業システム
 * @version 1.0
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/QRCodeGenerator.php';
require_once __DIR__ . '/../../classes/SecurityHelper.php';

// 権限チェック（smiley_staffのみ）
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'smiley_staff') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'この機能を使用する権限がありません'
    ]);
    exit;
}

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
    
    // トランザクション開始
    $db->beginTransaction();
    
    // 企業コード生成（3桁英字）
    $companyCode = generateCompanyCode($db);
    
    // 企業情報を挿入
    $companyId = insertCompany($db, $companyCode, $input);
    
    // QRコード生成
    $qrGenerator = new QRCodeGenerator();
    $qrInfo = $qrGenerator->generateCompanySignupQR($companyId);
    
    // コミット
    $db->commit();
    
    // 成功レスポンス
    echo json_encode([
        'success' => true,
        'data' => [
            'company_id' => $companyId,
            'company_code' => $companyCode,
            'company_name' => $input['company_name'],
            'signup_token' => $qrInfo['token'],
            'signup_url' => $qrInfo['signup_url'],
            'qr_code_path' => $qrInfo['qr_code_path']
        ],
        'message' => '企業登録が完了しました'
    ]);
    
} catch (Exception $e) {
    // ロールバック
    if (isset($db)) {
        $db->rollback();
    }
    
    // エラーログ記録
    error_log("企業登録エラー: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => '企業登録中にエラーが発生しました',
        'debug' => $e->getMessage() // 開発環境のみ
    ]);
}

/**
 * 入力値のバリデーション
 * 
 * @param array $input 入力データ
 * @return array エラー配列
 */
function validateInput($input) {
    $errors = [];
    
    // 必須項目チェック
    if (empty($input['company_name'])) {
        $errors['company_name'] = '企業名は必須です';
    }
    
    if (empty($input['company_address'])) {
        $errors['company_address'] = '住所は必須です';
    }
    
    if (empty($input['contact_person'])) {
        $errors['contact_person'] = '担当者名は必須です';
    }
    
    if (empty($input['phone'])) {
        $errors['phone'] = '電話番号は必須です';
    } else {
        // 電話番号の形式チェック
        $phone = preg_replace('/[^0-9]/', '', $input['phone']);
        if (strlen($phone) < 10) {
            $errors['phone'] = '正しい電話番号を入力してください';
        }
    }
    
    // 補助金額のバリデーション
    if (isset($input['subsidy_amount'])) {
        if (!is_numeric($input['subsidy_amount']) || $input['subsidy_amount'] < 0) {
            $errors['subsidy_amount'] = '補助金額は0以上の数値を入力してください';
        }
    }
    
    // 支払方法のバリデーション
    $validPaymentMethods = ['company_bulk', 'department_bulk', 'individual', 'mixed'];
    if (isset($input['payment_method']) && !in_array($input['payment_method'], $validPaymentMethods)) {
        $errors['payment_method'] = '無効な支払方法です';
    }
    
    return $errors;
}

/**
 * 企業コードを生成
 * 
 * @param Database $db データベース接続
 * @return string 企業コード（3桁英字）
 */
function generateCompanyCode($db) {
    $maxAttempts = 100;
    
    for ($i = 0; $i < $maxAttempts; $i++) {
        // 3桁のランダム英字を生成
        $code = '';
        for ($j = 0; $j < 3; $j++) {
            $code .= chr(rand(65, 90)); // A-Z
        }
        
        // 既存チェック
        $sql = "SELECT COUNT(*) FROM companies WHERE company_code = :code";
        $count = $db->fetchColumn($sql, ['code' => $code]);
        
        if ($count == 0) {
            return $code;
        }
    }
    
    throw new Exception("企業コードの生成に失敗しました");
}

/**
 * 企業情報を挿入
 * 
 * @param Database $db データベース接続
 * @param string $companyCode 企業コード
 * @param array $input 入力データ
 * @return int 挿入された企業ID
 */
function insertCompany($db, $companyCode, $input) {
    $sql = "INSERT INTO companies (
        company_code,
        company_name,
        company_address,
        contact_person,
        phone,
        email,
        payment_method,
        billing_method,
        service_fee_rate,
        is_active,
        created_at,
        updated_at
    ) VALUES (
        :company_code,
        :company_name,
        :company_address,
        :contact_person,
        :phone,
        :email,
        :payment_method,
        :billing_method,
        :service_fee_rate,
        1,
        NOW(),
        NOW()
    )";
    
    $params = [
        'company_code' => $companyCode,
        'company_name' => SecurityHelper::sanitizeInput($input['company_name']),
        'company_address' => SecurityHelper::sanitizeInput($input['company_address']),
        'contact_person' => SecurityHelper::sanitizeInput($input['contact_person']),
        'phone' => preg_replace('/[^0-9-]/', '', $input['phone']),
        'email' => $input['email'] ?? null,
        'payment_method' => $input['payment_method'] ?? 'company_bulk',
        'billing_method' => $input['billing_method'] ?? 'company',
        'service_fee_rate' => $input['subsidy_amount'] ?? 0
    ];
    
    $db->query($sql, $params);
    
    return $db->lastInsertId();
}
