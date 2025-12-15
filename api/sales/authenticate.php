<?php
/**
 * 営業スタッフ簡易認証API
 *
 * パスワードを検証してセッションに認証情報を保存
 *
 * @package Smiley配食事業システム
 * @version 1.0
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/SecurityHelper.php';

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

    // パスワード検証
    if (empty($input['password'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'パスワードを入力してください'
        ]);
        exit;
    }

    // レート制限チェック
    if (!SecurityHelper::checkRateLimit('sales_auth', 5, 300)) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'error' => '試行回数が多すぎます。5分後に再度お試しください。'
        ]);
        exit;
    }

    // パスワード検証
    if ($input['password'] === SALES_STAFF_PASSWORD) {
        // 認証成功
        $_SESSION['sales_staff_authenticated'] = true;
        $_SESSION['sales_staff_auth_time'] = time();
        $_SESSION['sales_staff_ip'] = $_SERVER['REMOTE_ADDR'] ?? '';

        echo json_encode([
            'success' => true,
            'message' => '認証に成功しました'
        ]);
    } else {
        // 認証失敗
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'パスワードが正しくありません'
        ]);
    }

} catch (Exception $e) {
    error_log("営業スタッフ認証エラー: " . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => '認証処理中にエラーが発生しました'
    ]);
}
