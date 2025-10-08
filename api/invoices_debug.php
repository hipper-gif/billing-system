<?php
/**
 * 請求書API デバッグ版
 * エラー箇所を特定するため
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

header('Content-Type: application/json; charset=utf-8');

$debug = [];
$debug['step'] = 'start';
$debug['time'] = date('Y-m-d H:i:s');

try {
    $debug['step'] = 'loading config';
    
    // Step 1: config/database.php 読み込み
    if (!file_exists(__DIR__ . '/../config/database.php')) {
        throw new Exception('config/database.php が見つかりません');
    }
    require_once __DIR__ . '/../config/database.php';
    $debug['config_loaded'] = true;
    
    // Step 2: Database クラス確認
    $debug['step'] = 'checking Database class';
    if (!class_exists('Database')) {
        throw new Exception('Database クラスが読み込まれていません');
    }
    $debug['database_class_exists'] = true;
    
    // Step 3: Database 接続テスト
    $debug['step'] = 'testing database connection';
    $db = Database::getInstance();
    $debug['database_connected'] = true;
    
    // Step 4: SmileyInvoiceGenerator 読み込み
    $debug['step'] = 'loading SmileyInvoiceGenerator';
    if (!file_exists(__DIR__ . '/../classes/SmileyInvoiceGenerator.php')) {
        throw new Exception('SmileyInvoiceGenerator.php が見つかりません');
    }
    require_once __DIR__ . '/../classes/SmileyInvoiceGenerator.php';
    $debug['invoice_generator_loaded'] = true;
    
    // Step 5: SmileyInvoiceGenerator インスタンス作成
    $debug['step'] = 'creating SmileyInvoiceGenerator instance';
    if (!class_exists('SmileyInvoiceGenerator')) {
        throw new Exception('SmileyInvoiceGenerator クラスが読み込まれていません');
    }
    $invoiceGenerator = new SmileyInvoiceGenerator();
    $debug['invoice_generator_created'] = true;
    
    // Step 6: POST データ受信
    $debug['step'] = 'receiving POST data';
    $input = json_decode(file_get_contents('php://input'), true);
    $debug['post_data'] = $input;
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('JSON Parse Error: ' . json_last_error_msg());
    }
    
    // Step 7: パラメータ構築
    $debug['step'] = 'building parameters';
    $params = [
        'invoice_type' => $input['invoice_type'] ?? null,
        'period_start' => $input['period_start'] ?? null,
        'period_end' => $input['period_end'] ?? null,
        'due_date' => $input['due_date'] ?? null,
        'targets' => $input['targets'] ?? [],
        'auto_pdf' => $input['auto_pdf'] ?? false
    ];
    $debug['params'] = $params;
    
    // Step 8: メソッド存在確認
    $debug['step'] = 'checking generateInvoices method';
    if (!method_exists($invoiceGenerator, 'generateInvoices')) {
        throw new Exception('generateInvoices メソッドが存在しません');
    }
    $debug['method_exists'] = true;
    
    // Step 9: generateInvoices 実行
    $debug['step'] = 'executing generateInvoices';
    $result = $invoiceGenerator->generateInvoices($params);
    $debug['result'] = $result;
    
    // 成功
    echo json_encode([
        'success' => true,
        'message' => 'デバッグ成功',
        'debug' => $debug,
        'result' => $result
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    $debug['error'] = [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ];
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'debug' => $debug
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
