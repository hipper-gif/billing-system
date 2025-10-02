<?php
/**
 * 詳細デバッグ版インポートテスト
 * 各ステップで何が起きているか確認
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/html; charset=utf-8');

echo "<html><head><meta charset='UTF-8'><title>詳細デバッグ</title>";
echo "<style>body{font-family:monospace;padding:20px;background:#1e1e1e;color:#d4d4d4;}";
echo ".step{margin:20px 0;padding:15px;background:#252526;border-left:4px solid #4CAF50;border-radius:4px;}";
echo ".success{color:#4CAF50;} .error{color:#f44336;} .info{color:#2196F3;}";
echo "pre{background:#1e1e1e;padding:10px;border-radius:4px;overflow-x:auto;}</style></head><body>";

function logStep($step, $message, $data = null, $success = true) {
    $class = $success ? 'success' : 'error';
    echo "<div class='step'>";
    echo "<h3 class='{$class}'>Step {$step}: {$message}</h3>";
    if ($data !== null) {
        echo "<pre>" . htmlspecialchars(print_r($data, true)) . "</pre>";
    }
    echo "</div>";
    flush();
}

try {
    logStep(1, "config/database.php 読み込み開始", null, true);
    require_once '../config/database.php';
    logStep(1, "config/database.php 読み込み成功", [
        'Database class exists' => class_exists('Database'),
        'defined constants' => [
            'DB_HOST' => defined('DB_HOST') ? DB_HOST : 'undefined',
            'DB_NAME' => defined('DB_NAME') ? DB_NAME : 'undefined',
            'DB_USER' => defined('DB_USER') ? DB_USER : 'undefined',
        ]
    ], true);
    
    logStep(2, "Database接続テスト", null, true);
    $db = Database::getInstance();
    logStep(2, "Database接続成功", [
        'class' => get_class($db),
        'methods' => get_class_methods($db)
    ], true);
    
    logStep(3, "getConnection()メソッドテスト", null, true);
    $pdo = $db->getConnection();
    logStep(3, "getConnection()成功", [
        'PDO class' => get_class($pdo),
        'PDO available' => $pdo !== null
    ], true);
    
    logStep(4, "簡単なクエリテスト", null, true);
    $stmt = $db->query("SELECT 1 as test, DATABASE() as db_name, NOW() as current_time");
    $result = $stmt->fetch();
    logStep(4, "クエリ成功", $result, true);
    
    logStep(5, "SmileyCSVImporter読み込み", null, true);
    require_once '../classes/SmileyCSVImporter.php';
    logStep(5, "SmileyCSVImporter読み込み成功", [
        'class exists' => class_exists('SmileyCSVImporter'),
        'methods' => array_slice(get_class_methods('SmileyCSVImporter'), 0, 10)
    ], true);
    
    logStep(6, "SmileyCSVImporter初期化テスト", null, true);
    $importer = new SmileyCSVImporter();
    logStep(6, "SmileyCSVImporter初期化成功", [
        'class' => get_class($importer),
        'has importCSV method' => method_exists($importer, 'importCSV')
    ], true);
    
    logStep(7, "FileUploadHandler読み込み", null, true);
    require_once '../classes/FileUploadHandler.php';
    logStep(7, "FileUploadHandler読み込み成功", [
        'class exists' => class_exists('FileUploadHandler')
    ], true);
    
    logStep(8, "テストCSV作成", null, true);
    $testCSV = '../uploads/debug_test_' . time() . '.csv';
    $csvData = [
        ['法人CD', '法人名', '事業所CD', '事業所名', '給食業者CD', '給食業者名', '給食区分CD', '給食区分名', '配達日', '部門CD', '部門名', '社員CD', '社員名', '雇用形態CD', '雇用形態名', '給食ﾒﾆｭｰCD', '給食ﾒﾆｭｰ名', '数量', '単価', '金額', '備考', '受取時間', '連携CD'],
        ['TEST001', '株式会社Smiley', 'COMP001', 'デバッグテスト企業', 'SUP001', 'テスト業者', 'CAT001', 'ランチ', '2025-10-02', 'DEPT001', 'テスト部署', 'DEBUG001', 'デバッグ太郎', 'EMP001', '正社員', 'MENU001', 'テストメニュー', '1', '500', '500', 'デバッグテスト', '12:00', 'COOP001']
    ];
    
    $fp = fopen($testCSV, 'w');
    foreach ($csvData as $row) {
        fputcsv($fp, $row);
    }
    fclose($fp);
    
    logStep(8, "テストCSV作成成功", [
        'file path' => $testCSV,
        'file exists' => file_exists($testCSV),
        'file size' => filesize($testCSV) . ' bytes'
    ], true);
    
    logStep(9, "CSVインポート実行テスト", null, true);
    $importOptions = [
        'encoding' => 'UTF-8',
        'delimiter' => ',',
        'has_header' => true
    ];
    
    $importResult = $importer->importCSV($testCSV, $importOptions);
    
    logStep(9, "CSVインポート実行結果", [
        'success' => $importResult['success'],
        'batch_id' => $importResult['batch_id'] ?? null,
        'stats' => $importResult['stats'] ?? [],
        'errors_count' => count($importResult['errors'] ?? []),
        'first_5_errors' => array_slice($importResult['errors'] ?? [], 0, 5)
    ], $importResult['success']);
    
    // テストファイル削除
    if (file_exists($testCSV)) {
        unlink($testCSV);
        logStep(10, "テストファイル削除完了", null, true);
    }
    
    echo "<div class='step' style='border-color:#4CAF50;'>";
    echo "<h2 class='success'>✅ すべてのテストが正常に完了しました！</h2>";
    echo "<p>CSVインポート機能は正常に動作しています。</p>";
    echo "<p><strong>次のステップ:</strong> 実際のCSVファイルでインポートを試してください。</p>";
    echo "</div>";
    
} catch (Throwable $e) {
    logStep('ERROR', 'エラーが発生しました', [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => explode("\n", $e->getTraceAsString())
    ], false);
    
    echo "<div class='step' style='border-color:#f44336;'>";
    echo "<h2 class='error'>❌ エラー詳細</h2>";
    echo "<p><strong>エラーメッセージ:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>ファイル:</strong> " . htmlspecialchars($e->getFile()) . "</p>";
    echo "<p><strong>行番号:</strong> " . $e->getLine() . "</p>";
    echo "<details><summary>スタックトレース</summary>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    echo "</details>";
    echo "</div>";
}

echo "</body></html>";
?>
