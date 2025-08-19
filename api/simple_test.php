<?php
/**
 * 最小限のテストAPI
 * 基本的な動作確認用
 */

// 最初にヘッダーを設定
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// OPTIONSリクエスト対応
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 即座にJSONレスポンスを返す
echo json_encode([
    'success' => true,
    'message' => 'テストAPI動作確認',
    'timestamp' => date('Y-m-d H:i:s'),
    'method' => $_SERVER['REQUEST_METHOD'],
    'has_files' => !empty($_FILES),
    'files_count' => count($_FILES),
    'post_count' => count($_POST)
]);
?>
