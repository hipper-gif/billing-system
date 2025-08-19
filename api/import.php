<?php
/**
 * Smiley配食事業 CSVインポートAPI
 * Ajax通信でCSVファイルを処理
 */

// CORS対応
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// OPTIONSリクエスト対応
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// POSTリクエストのみ受付
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'POSTリクエストのみ対応しています'
    ]);
    exit;
}

// 必要なファイル読み込み
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/SmileyCSVImporter.php';

// セッション開始
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// エラーハンドリング設定
set_error_handler(function($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

try {
    // アップロードファイルチェック
    if (!isset($_FILES['csv_file'])) {
        throw new Exception('CSVファイルがアップロードされていません');
    }
    
    $uploadedFile = $_FILES['csv_file'];
    
    // アップロードエラーチェック
    if ($uploadedFile['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'ファイルサイズが上限を超えています（php.ini）',
            UPLOAD_ERR_FORM_SIZE => 'ファイルサイズが上限を超えています（フォーム）',
            UPLOAD_ERR_PARTIAL => 'ファイルが一部しかアップロードされませんでした',
            UPLOAD_ERR_NO_FILE => 'ファイルがアップロードされませんでした',
            UPLOAD_ERR_NO_TMP_DIR => '一時ディレクトリがありません',
            UPLOAD_ERR_CANT_WRITE => 'ディスクへの書き込みに失敗しました',
            UPLOAD_ERR_EXTENSION => 'PHP拡張によりアップロードが停止されました',
        ];
        
        $message = $errorMessages[$uploadedFile['error']] ?? 'アップロードエラーが発生しました';
        throw new Exception($message);
    }
    
    // ファイル形式チェック
    $fileName = $uploadedFile['name'];
    $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    
    if ($fileExtension !== 'csv') {
        throw new Exception('CSVファイルのみアップロード可能です（拡張子: .csv）');
    }
    
    // MIMEタイプチェック
    $allowedMimeTypes = [
        'text/csv',
        'text/plain',
        'application/csv',
        'application/vnd.ms-excel'
    ];
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $uploadedFile['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, $allowedMimeTypes)) {
        throw new Exception('無効なファイル形式です。CSVファイルをアップロードしてください。');
    }
    
    // ファイルサイズチェック
    $maxSize = defined('UPLOAD_MAX_SIZE') ? UPLOAD_MAX_SIZE : 10 * 1024 * 1024; // 10MB
    if ($uploadedFile['size'] > $maxSize) {
        $maxSizeMB = round($maxSize / 1024 / 1024, 1);
        throw new Exception("ファイルサイズが大きすぎます（最大{$maxSizeMB}MB）");
    }
    
    // 空ファイルチェック
    if ($uploadedFile['size'] === 0) {
        throw new Exception('ファイルが空です');
    }
    
    // 一時保存ディレクトリ確認・作成
    $tempDir = defined('TEMP_DIR') ? TEMP_DIR : __DIR__ . '/../temp/';
    if (!is_dir($tempDir)) {
        if (!mkdir($tempDir, 0755, true)) {
            throw new Exception('一時ディレクトリの作成に失敗しました');
        }
    }
    
    // 安全なファイル名生成
    $safeFileName = 'csv_import_' . date('YmdHis') . '_' . uniqid() . '.csv';
    $tempFilePath = $tempDir . $safeFileName;
    
    // ファイル移動
    if (!move_uploaded_file($uploadedFile['tmp_name'], $tempFilePath)) {
        throw new Exception('ファイルの保存に失敗しました');
    }
    
    // インポートオプション設定
    $options = [
        'encoding' => $_POST['encoding'] ?? 'UTF-8',
        'delimiter' => $_POST['delimiter'] ?? ',',
        'has_header' => true
    ];
    
    // 区切り文字の変換
    if ($options['delimiter'] === '\t') {
        $options['delimiter'] = "\t";
    }
    
    // セキュリティ: オプション値検証
    $validEncodings = ['UTF-8', 'Shift_JIS', 'EUC-JP'];
    if (!in_array($options['encoding'], $validEncodings)) {
        $options['encoding'] = 'UTF-8';
    }
    
    $validDelimiters = [',', "\t", ';'];
    if (!in_array($options['delimiter'], $validDelimiters)) {
        $options['delimiter'] = ',';
    }
    
    // CSVインポート実行
    $importer = new SmileyCSVImporter();
    
    // タイムアウト対策
    set_time_limit(300); // 5分
    ini_set('memory_limit', '256M');
    
    // インポート処理開始時間記録
    $startTime = microtime(true);
    
    // メイン処理実行
    $result = $importer->importCSV($tempFilePath, $options);
    
    // 処理時間計算
    $executionTime = round(microtime(true) - $startTime, 2);
    $result['execution_time'] = $executionTime;
    
    // 成功レスポンス
    $response = [
        'success' => true,
        'message' => 'CSVインポートが正常に完了しました',
        'data' => $result,
        'stats' => $importer->getStats(),
        'errors' => $importer->getErrors(),
        'execution_time' => $executionTime,
        'file_info' => [
            'name' => $fileName,
            'size' => $uploadedFile['size'],
            'rows_processed' => $result['stats']['processed_rows'] ?? 0
        ]
    ];
    
    // ログ記録
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        error_log("CSV Import Success: {$fileName}, Rows: {$result['stats']['processed_rows']}, Time: {$executionTime}s");
    }
    
    // 一時ファイル削除
    if (file_exists($tempFilePath)) {
        unlink($tempFilePath);
    }
    
    // 結果出力
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    // エラーレスポンス
    $errorResponse = [
        'success' => false,
        'message' => $e->getMessage(),
        'error_code' => $e->getCode(),
        'file_info' => [
            'name' => $_FILES['csv_file']['name'] ?? 'unknown',
            'size' => $_FILES['csv_file']['size'] ?? 0
        ]
    ];
    
    // デバッグ情報追加
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        $errorResponse['debug'] = [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ];
    }
    
    // エラーログ記録
    error_log("CSV Import Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    
    // 一時ファイル削除（エラーの場合も）
    if (isset($tempFilePath) && file_exists($tempFilePath)) {
        unlink($tempFilePath);
    }
    
    // HTTPステータス設定
    http_response_code(400);
    
    // エラーレスポンス出力
    echo json_encode($errorResponse, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (Error $e) {
    // 致命的エラー対応
    $fatalErrorResponse = [
        'success' => false,
        'message' => 'システムエラーが発生しました。管理者にお問い合わせください。',
        'error_type' => 'fatal_error'
    ];
    
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        $fatalErrorResponse['debug'] = [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ];
    }
    
    error_log("CSV Import Fatal Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    
    http_response_code(500);
    echo json_encode($fatalErrorResponse, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}

/**
 * ファイルサイズを人間が読みやすい形式に変換
 */
function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= pow(1024, $pow);
    
    return round($bytes, 2) . ' ' . $units[$pow];
}

/**
 * CSV文字エンコーディング自動検出
 */
function detectCSVEncoding($filePath) {
    $content = file_get_contents($filePath, false, null, 0, 1024); // 最初の1KBで判定
    
    $encodings = ['UTF-8', 'Shift_JIS', 'EUC-JP', 'ASCII'];
    
    foreach ($encodings as $encoding) {
        if (mb_check_encoding($content, $encoding)) {
            return $encoding;
        }
    }
    
    return 'UTF-8'; // デフォルト
}

/**
 * CSVファイルの基本検証
 */
function validateCSVStructure($filePath, $expectedFieldCount = 23) {
    $handle = fopen($filePath, 'r');
    if (!$handle) {
        throw new Exception('CSVファイルを開けませんでした');
    }
    
    // ヘッダー行読み込み
    $header = fgetcsv($handle);
    fclose($handle);
    
    if ($header === false) {
        throw new Exception('CSVファイルが空または読み込めませんでした');
    }
    
    // フィールド数チェック
    if (count($header) !== $expectedFieldCount) {
        throw new Exception("CSVフィールド数が正しくありません。期待値: {$expectedFieldCount}、実際: " . count($header));
    }
    
    return true;
}

// 実行時間制限解除（大容量ファイル対応）
if (function_exists('ignore_user_abort')) {
    ignore_user_abort(true);
}

// メモリ制限緩和
if (function_exists('ini_set')) {
    ini_set('memory_limit', '512M');
}
?>
