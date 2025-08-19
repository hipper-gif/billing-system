<?php
/**
 * エラー確認ツール
 * import.phpのエラーを段階的にチェック
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// エラー報告を有効化
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// バッファリング開始
ob_start();

try {
    echo json_encode(['step' => 1, 'message' => 'エラーチェック開始']);
    
    // Step 1: 基本設定確認
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        echo json_encode(['step' => 'OPTIONS', 'message' => 'OPTIONS OK']);
        exit;
    }
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('POSTリクエストが必要です');
    }
    
    // Step 2: ファイル確認
    if (!isset($_FILES['csv_file'])) {
        throw new Exception('csv_fileパラメータがありません');
    }
    
    $file = $_FILES['csv_file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('ファイルアップロードエラー: ' . $file['error']);
    }
    
    // Step 3: config/database.php読み込みテスト
    $configPath = __DIR__ . '/../config/database.php';
    if (!file_exists($configPath)) {
        throw new Exception('config/database.phpが見つかりません');
    }
    
    require_once $configPath;
    
    // Step 4: SmileyCSVImporter読み込みテスト
    $importerPath = __DIR__ . '/../classes/SmileyCSVImporter.php';
    if (!file_exists($importerPath)) {
        throw new Exception('SmileyCSVImporter.phpが見つかりません');
    }
    
    require_once $importerPath;
    
    // Step 5: クラス初期化テスト
    $importer = new SmileyCSVImporter();
    
    // Step 6: 簡単なファイル読み込みテスト
    $tempPath = $file['tmp_name'];
    $handle = fopen($tempPath, 'r');
    if (!$handle) {
        throw new Exception('ファイルを開けませんでした');
    }
    
    $firstLine = fgets($handle);
    fclose($handle);
    
    // 成功レスポンス
    ob_clean();
    echo json_encode([
        'success' => true,
        'message' => '全てのチェックが成功しました',
        'data' => [
            'file_info' => [
                'name' => $file['name'],
                'size' => $file['size'],
                'type' => $file['type']
            ],
            'first_line' => trim($firstLine),
            'first_line_length' => strlen($firstLine),
            'config_loaded' => defined('DB_HOST'),
            'importer_class' => class_exists('SmileyCSVImporter'),
            'php_version' => phpversion()
        ]
    ]);
    
} catch (Exception $e) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'エラー: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
    
} catch (Error $e) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => '致命的エラー: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    
} catch (Throwable $e) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => '予期しないエラー: ' . $e->getMessage(),
        'type' => get_class($e)
    ]);
}
?>
