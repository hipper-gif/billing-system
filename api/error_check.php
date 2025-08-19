<?php
/**
 * エラー確認ツール (修正版)
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// エラー報告を有効化してログに記録
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error_check.log');

try {
    // OPTIONSリクエスト対応
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        echo json_encode(['success' => true, 'message' => 'OPTIONS OK']);
        exit;
    }
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'POSTリクエストが必要']);
        exit;
    }
    
    // Step 1: 基本チェック
    $result = ['success' => true, 'steps' => []];
    
    $result['steps']['basic'] = 'OK - 基本設定完了';
    
    // Step 2: ファイルチェック
    if (!isset($_FILES['csv_file'])) {
        echo json_encode(['success' => false, 'message' => 'csv_fileパラメータがありません', 'steps' => $result['steps']]);
        exit;
    }
    
    $file = $_FILES['csv_file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'ファイルアップロードエラー: ' . $file['error'], 'steps' => $result['steps']]);
        exit;
    }
    
    $result['steps']['file'] = 'OK - ファイルアップロード成功';
    
    // Step 3: config/database.php読み込み
    $configPath = __DIR__ . '/../config/database.php';
    if (!file_exists($configPath)) {
        echo json_encode(['success' => false, 'message' => 'config/database.phpが見つかりません: ' . $configPath, 'steps' => $result['steps']]);
        exit;
    }
    
    require_once $configPath;
    $result['steps']['config'] = 'OK - データベース設定読み込み完了';
    
    // Step 4: クラスファイル存在確認
    $importerPath = __DIR__ . '/../classes/SmileyCSVImporter.php';
    if (!file_exists($importerPath)) {
        echo json_encode(['success' => false, 'message' => 'SmileyCSVImporter.phpが見つかりません: ' . $importerPath, 'steps' => $result['steps']]);
        exit;
    }
    
    $result['steps']['class_file'] = 'OK - クラスファイル存在確認';
    
    // Step 5: クラスファイル読み込み（シンタックスチェック）
    $classContent = file_get_contents($importerPath);
    if (strlen($classContent) < 100) {
        echo json_encode(['success' => false, 'message' => 'クラスファイルが小さすぎます（破損の可能性）', 'steps' => $result['steps']]);
        exit;
    }
    
    // シンタックスチェック
    $syntaxCheck = exec("php -l \"$importerPath\" 2>&1", $output, $returnCode);
    if ($returnCode !== 0) {
        echo json_encode(['success' => false, 'message' => 'PHPシンタックスエラー: ' . implode('\n', $output), 'steps' => $result['steps']]);
        exit;
    }
    
    $result['steps']['syntax'] = 'OK - シンタックスチェック完了';
    
    // Step 6: クラス読み込み試行
    require_once $importerPath;
    $result['steps']['require'] = 'OK - クラスファイル読み込み完了';
    
    // Step 7: クラス初期化試行
    if (!class_exists('SmileyCSVImporter')) {
        echo json_encode(['success' => false, 'message' => 'SmileyCSVImporterクラスが定義されていません', 'steps' => $result['steps']]);
        exit;
    }
    
    $importer = new SmileyCSVImporter();
    $result['steps']['instance'] = 'OK - クラスインスタンス作成完了';
    
    // Step 8: ファイル読み込みテスト
    $handle = fopen($file['tmp_name'], 'r');
    if (!$handle) {
        echo json_encode(['success' => false, 'message' => 'CSVファイルを開けませんでした', 'steps' => $result['steps']]);
        exit;
    }
    
    $firstLine = fgets($handle);
    fclose($handle);
    $result['steps']['csv'] = 'OK - CSVファイル読み込み完了';
    
    // 成功レスポンス
    echo json_encode([
        'success' => true,
        'message' => '全てのステップが成功しました',
        'steps' => $result['steps'],
        'data' => [
            'file_info' => [
                'name' => $file['name'],
                'size' => $file['size'],
                'type' => $file['type']
            ],
            'first_line' => trim($firstLine),
            'config_loaded' => defined('DB_HOST'),
            'php_version' => phpversion()
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Exception: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'steps' => isset($result['steps']) ? $result['steps'] : []
    ]);
    
} catch (Error $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Fatal Error: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'steps' => isset($result['steps']) ? $result['steps'] : []
    ]);
}
?>
