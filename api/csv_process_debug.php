<?php
/**
 * CSV処理専用デバッグAPI
 * api/csv_process_debug.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

ob_start();
header('Content-Type: application/json; charset=utf-8');

function debugResponse($step, $success, $message, $data = [], $error = null) {
    ob_clean();
    echo json_encode([
        'debug_step' => $step,
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s'),
        'error' => $error
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

try {
    require_once '../config/database.php';
    require_once '../classes/Database.php';
    require_once '../classes/FileUploadHandler.php';
    require_once '../classes/SmileyCSVImporter.php';
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
        
        $file = $_FILES['csv_file'];
        
        // Step 1: FileUploadHandler テスト
        $fileHandler = new FileUploadHandler();
        $uploadResult = $fileHandler->uploadFile($file);
        
        if (!$uploadResult['success']) {
            debugResponse('file_upload_failed', false, 'ファイルアップロード失敗', $uploadResult);
        }
        
        $filePath = $uploadResult['filepath'];
        
        // Step 2: CSVファイル内容確認
        $csvContent = file_get_contents($filePath);
        $lines = explode("\n", $csvContent);
        $headerLine = isset($lines[0]) ? trim($lines[0]) : '';
        $dataLineCount = count($lines) - 1;
        
        // エンコーディング検出
        $encoding = mb_detect_encoding($csvContent, ['SJIS-win', 'UTF-8', 'EUC-JP'], true);
        
        // UTF-8に変換
        if ($encoding && $encoding !== 'UTF-8') {
            $csvContent = mb_convert_encoding($csvContent, 'UTF-8', $encoding);
            $lines = explode("\n", $csvContent);
            $headerLine = isset($lines[0]) ? trim($lines[0]) : '';
        }
        
        debugResponse('csv_content_analysis', true, 'CSV内容解析完了', [
            'file_path' => $filePath,
            'file_size' => filesize($filePath),
            'detected_encoding' => $encoding,
            'total_lines' => count($lines),
            'data_lines' => $dataLineCount,
            'header_line' => $headerLine,
            'header_fields' => array_map('trim', explode(',', $headerLine)),
            'sample_data_lines' => array_slice($lines, 1, 3),
            'csv_preview' => substr($csvContent, 0, 500) . '...'
        ]);
        
    } else {
        debugResponse('no_post_data', false, 'POST データまたはファイルがありません', [
            'method' => $_SERVER['REQUEST_METHOD'],
            'post_keys' => array_keys($_POST),
            'files_keys' => array_keys($_FILES)
        ]);
    }
    
} catch (Throwable $e) {
    debugResponse('processing_error', false, 'CSV処理エラー', [], [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
?>
