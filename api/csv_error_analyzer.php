<?php
/**
 * CSVエラー詳細分析API
 * エラー内容を詳細に確認して問題を特定
 */

// 出力バッファリング開始
ob_start();

// エラー設定
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// JSONレスポンス関数
function sendResponse($success, $message, $data = [], $code = 200) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s'),
        'version' => 'error-analyzer-1.0'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// OPTIONS対応
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    sendResponse(true, 'OPTIONS OK');
}

try {
    // 必須ファイル読み込み
    require_once '../config/database.php';
    require_once '../classes/Database.php';
    require_once '../classes/FileUploadHandler.php';
    
    // GET リクエスト処理（CSV分析）
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = $_GET['action'] ?? 'analyze';
        
        if ($action === 'analyze') {
            // 最新のアップロードファイルを分析
            $uploadDir = '../uploads/';
            $files = scandir($uploadDir);
            $csvFiles = array_filter($files, function($file) {
                return pathinfo($file, PATHINFO_EXTENSION) === 'csv';
            });
            
            if (empty($csvFiles)) {
                sendResponse(false, 'アップロード済みCSVファイルが見つかりません');
            }
            
            // 最新ファイルを取得
            usort($csvFiles, function($a, $b) use ($uploadDir) {
                return filemtime($uploadDir . $b) - filemtime($uploadDir . $a);
            });
            
            $latestFile = $uploadDir . $csvFiles[0];
            
            // CSV詳細分析
            $analysis = analyzeCsvFile($latestFile);
            
            sendResponse(true, 'CSV分析完了', $analysis);
        }
        
        sendResponse(true, 'CSVエラー分析API', [
            'available_actions' => ['analyze']
        ]);
    }
    
    // POST リクエスト処理（簡易アップロード&分析）
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        
        // ファイル確認
        if (!isset($_FILES['csv_file'])) {
            sendResponse(false, 'CSVファイルがアップロードされていません', [], 400);
        }
        
        $file = $_FILES['csv_file'];
        
        // ファイルアップロード
        $fileHandler = new FileUploadHandler();
        $uploadResult = $fileHandler->uploadFile($file);
        
        if (!$uploadResult['success']) {
            sendResponse(false, 'ファイルアップロード失敗', [
                'errors' => $uploadResult['errors']
            ], 400);
        }
        
        // CSV分析
        $analysis = analyzeCsvFile($uploadResult['filepath']);
        
        // 一時ファイル削除
        $fileHandler->deleteFile($uploadResult['filepath']);
        
        sendResponse(true, 'CSVアップロード&分析完了', [
            'filename' => $uploadResult['original_name'],
            'analysis' => $analysis
        ]);
    }
    
} catch (Throwable $e) {
    sendResponse(false, 'エラー分析中にエラー発生', [
        'error' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ], 500);
}

/**
 * CSV詳細分析関数
 */
function analyzeCsvFile($filePath) {
    $analysis = [
        'file_info' => [
            'path' => $filePath,
            'size' => filesize($filePath),
            'size_mb' => round(filesize($filePath) / 1024 / 1024, 3)
        ],
        'content_analysis' => [],
        'header_analysis' => [],
        'data_sample' => [],
        'validation_results' => [],
        'recommendations' => []
    ];
    
    // ファイル存在確認
    if (!file_exists($filePath)) {
        $analysis['error'] = 'ファイルが存在しません';
        return $analysis;
    }
    
    // エンコーディング検出
    $content = file_get_contents($filePath);
    $encoding = mb_detect_encoding($content, ['UTF-8', 'SJIS-win', 'eucJP-win'], true);
    
    $analysis['content_analysis'] = [
        'original_encoding' => $encoding,
        'file_size_bytes' => strlen($content),
        'bom_detected' => substr($content, 0, 3) === "\xEF\xBB\xBF"
    ];
    
    // UTF-8に変換
    if ($encoding !== 'UTF-8') {
        $content = mb_convert_encoding($content, 'UTF-8', $encoding);
    }
    
    // BOM除去
    $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
    
    // CSV解析
    $lines = explode("\n", $content);
    $lines = array_filter($lines, function($line) {
        return trim($line) !== '';
    });
    
    $analysis['content_analysis']['total_lines'] = count($lines);
    
    if (empty($lines)) {
        $analysis['error'] = 'CSVファイルが空です';
        return $analysis;
    }
    
    // ヘッダー分析
    $headerLine = $lines[0];
    $headers = str_getcsv($headerLine);
    $headers = array_map('trim', $headers);
    
    $analysis['header_analysis'] = [
        'header_count' => count($headers),
        'headers' => $headers,
        'expected_count' => 23,
        'matches_expected' => count($headers) === 23
    ];
    
    // 期待されるSmileyヘッダー
    $expectedHeaders = [
        '法人CD', '法人名', '事業所CD', '事業所名', '給食業者CD', '給食業者名',
        '給食区分CD', '給食区分名', '配達日', '部門CD', '部門名', '社員CD',
        '社員名', '雇用形態CD', '雇用形態名', '給食ﾒﾆｭｰCD', '給食ﾒﾆｭｰ名',
        '数量', '単価', '金額', '備考', '受取時間', '連携CD'
    ];
    
    // ヘッダーマッチング確認
    $headerMatches = [];
    $missingHeaders = [];
    $extraHeaders = [];
    
    foreach ($expectedHeaders as $expected) {
        if (in_array($expected, $headers)) {
            $headerMatches[] = $expected;
        } else {
            $missingHeaders[] = $expected;
        }
    }
    
    foreach ($headers as $header) {
        if (!in_array($header, $expectedHeaders)) {
            $extraHeaders[] = $header;
        }
    }
    
    $analysis['header_analysis']['matching_headers'] = $headerMatches;
    $analysis['header_analysis']['missing_headers'] = $missingHeaders;
    $analysis['header_analysis']['extra_headers'] = $extraHeaders;
    $analysis['header_analysis']['match_percentage'] = round(count($headerMatches) / count($expectedHeaders) * 100, 1);
    
    // データサンプル分析（最初の5行）
    $sampleRows = [];
    for ($i = 1; $i <= min(5, count($lines) - 1); $i++) {
        if (isset($lines[$i])) {
            $row = str_getcsv($lines[$i]);
            $sampleRows[] = [
                'row_number' => $i + 1,
                'field_count' => count($row),
                'data' => array_slice($row, 0, 10), // 最初の10フィールドのみ
                'matches_header_count' => count($row) === count($headers)
            ];
        }
    }
    
    $analysis['data_sample'] = $sampleRows;
    
    // 検証結果
    $validationResults = [];
    
    // フィールド数チェック
    if (count($headers) !== 23) {
        $validationResults[] = [
            'type' => 'error',
            'message' => "フィールド数が正しくありません（期待: 23、実際: " . count($headers) . "）"
        ];
    }
    
    // 必須ヘッダーチェック
    $requiredHeaders = ['法人名', '事業所名', '配達日', '社員CD', '社員名', '給食ﾒﾆｭｰCD'];
    foreach ($requiredHeaders as $required) {
        if (!in_array($required, $headers)) {
            $validationResults[] = [
                'type' => 'error',
                'message' => "必須ヘッダーが見つかりません: {$required}"
            ];
        }
    }
    
    // データ行の整合性チェック
    foreach ($sampleRows as $sample) {
        if (!$sample['matches_header_count']) {
            $validationResults[] = [
                'type' => 'warning',
                'message' => "行{$sample['row_number']}: フィールド数がヘッダーと一致しません（ヘッダー: " . count($headers) . "、データ: {$sample['field_count']}）"
            ];
        }
    }
    
    $analysis['validation_results'] = $validationResults;
    
    // 推奨事項
    $recommendations = [];
    
    if (count($headers) !== 23) {
        $recommendations[] = "CSVファイルのフィールド数を23に調整してください";
    }
    
    if (!empty($missingHeaders)) {
        $recommendations[] = "不足しているヘッダーを追加してください: " . implode(', ', $missingHeaders);
    }
    
    if (!empty($extraHeaders)) {
        $recommendations[] = "余分なヘッダーを削除してください: " . implode(', ', $extraHeaders);
    }
    
    if ($encoding !== 'UTF-8') {
        $recommendations[] = "ファイルエンコーディングをUTF-8に変更することを推奨します";
    }
    
    $analysis['recommendations'] = $recommendations;
    
    return $analysis;
}
?>
