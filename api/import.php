<?php
/**
 * CSVインポートAPI - Database.php修正対応版
 */

// エラーレポートを有効化（デバッグ用）
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json; charset=utf-8');

try {
    // 必要ファイルの読み込み
    require_once '../config/database.php';
    require_once '../classes/Database.php';
    require_once '../classes/FileUploadHandler.php';
    require_once '../classes/SmileyCSVImporter.php';
    
    // HTTPメソッドチェック
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('POSTメソッドが必要です');
    }
    
    // ファイルアップロードチェック
    if (!isset($_FILES['csvFile'])) {
        throw new Exception('CSVファイルがアップロードされていません');
    }
    
    $file = $_FILES['csvFile'];
    
    // アップロードエラーチェック
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'ファイルサイズが上限を超えています',
            UPLOAD_ERR_FORM_SIZE => 'フォームで指定されたサイズを超えています',
            UPLOAD_ERR_PARTIAL => 'ファイルが部分的にしかアップロードされませんでした',
            UPLOAD_ERR_NO_FILE => 'ファイルがアップロードされませんでした',
            UPLOAD_ERR_NO_TMP_DIR => '一時ディレクトリがありません',
            UPLOAD_ERR_CANT_WRITE => 'ディスクへの書き込みに失敗しました',
            UPLOAD_ERR_EXTENSION => 'PHPの拡張によってアップロードが停止されました'
        ];
        
        $message = $errorMessages[$file['error']] ?? '不明なアップロードエラー';
        throw new Exception($message . ' (エラーコード: ' . $file['error'] . ')');
    }
    
    // ファイルサイズチェック
    $maxSize = 10 * 1024 * 1024; // 10MB
    if ($file['size'] > $maxSize) {
        throw new Exception('ファイルサイズが大きすぎます。10MB以下にしてください。');
    }
    
    // ファイル形式チェック
    $allowedExtensions = ['csv', 'txt'];
    $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($fileExtension, $allowedExtensions)) {
        throw new Exception('CSVファイルのみアップロード可能です');
    }
    
    // データベース接続テスト
    $db = new Database();
    if (!$db->isConnected()) {
        throw new Exception('データベース接続エラー: ' . $db->getLastError());
    }
    
    // 接続テスト実行
    $testResult = $db->testConnection();
    if (!$testResult['success']) {
        throw new Exception('データベーステスト失敗: ' . $testResult['message']);
    }
    
    // ファイルハンドラーでのファイル処理
    $fileHandler = new FileUploadHandler();
    $uploadResult = $fileHandler->handleUpload($file, '../uploads/');
    
    if (!$uploadResult['success']) {
        throw new Exception('ファイル処理エラー: ' . $uploadResult['message']);
    }
    
    $uploadedFilePath = $uploadResult['file_path'];
    
    // CSVインポーター実行
    $importer = new SmileyCSVImporter($db);
    $importResult = $importer->import($uploadedFilePath);
    
    // 一時ファイルの削除
    if (file_exists($uploadedFilePath)) {
        unlink($uploadedFilePath);
    }
    
    // 成功レスポンス
    echo json_encode([
        'success' => true,
        'message' => 'CSVインポートが完了しました',
        'data' => [
            'batch_id' => $importResult['batch_id'],
            'total_records' => $importResult['summary']['total'],
            'success_records' => $importResult['summary']['success'],
            'error_records' => $importResult['summary']['error'],
            'duplicate_records' => $importResult['summary']['duplicate'],
            'errors' => $importResult['errors']
        ]
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    // エラーログに記録
    error_log("CSV Import Error: " . $e->getMessage());
    error_log("File: " . $e->getFile() . " Line: " . $e->getLine());
    
    // エラーレスポンス
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'debug_info' => [
            'file' => basename($e->getFile()),
            'line' => $e->getLine(),
            'php_version' => PHP_VERSION,
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ], JSON_UNESCAPED_UNICODE);
}
?>
