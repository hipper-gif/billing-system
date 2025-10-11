<?php
/**
 * 請求書INSERT動作テスト
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $db = Database::getInstance();
    
    echo json_encode([
        'step' => 'Database connection',
        'status' => 'success'
    ]) . "\n";
    
    // テストデータ作成
    $testData = [
        'invoice_number' => 'TEST-' . date('YmdHis'),
        'user_id' => 34,
        'user_code' => 'TEST001',
        'user_name' => 'テスト利用者',
        'company_name' => 'テスト企業',
        'department' => 'テスト部署',
        'due_date' => '2025-11-30',
        'period_start' => '2025-10-01',
        'period_end' => '2025-10-31',
        'subtotal' => 10000.00,
        'tax_rate' => 10.00,
        'tax_amount' => 1000.00,
        'total_amount' => 11000.00,
        'invoice_type' => 'company_bulk'
    ];
    
    echo json_encode([
        'step' => 'Test data prepared',
        'data' => $testData
    ]) . "\n";
    
    // SQL作成
    $sql = "INSERT INTO invoices (
                invoice_number, user_id, user_code, user_name,
                company_name, department,
                invoice_date, due_date, period_start, period_end,
                subtotal, tax_rate, tax_amount, total_amount,
                invoice_type, status,
                created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, 'draft', NOW(), NOW())";
    
    echo json_encode([
        'step' => 'SQL prepared',
        'sql' => $sql
    ]) . "\n";
    
    $params = [
        $testData['invoice_number'],
        $testData['user_id'],
        $testData['user_code'],
        $testData['user_name'],
        $testData['company_name'],
        $testData['department'],
        $testData['due_date'],
        $testData['period_start'],
        $testData['period_end'],
        $testData['subtotal'],
        $testData['tax_rate'],
        $testData['tax_amount'],
        $testData['total_amount'],
        $testData['invoice_type']
    ];
    
    echo json_encode([
        'step' => 'Parameters prepared',
        'params' => $params
    ]) . "\n";
    
    // 実行
    $rowCount = $db->execute($sql, $params);
    $lastId = $db->lastInsertId();
    
    echo json_encode([
        'step' => 'INSERT executed',
        'row_count' => $rowCount,
        'last_insert_id' => $lastId,
        'status' => 'success'
    ]) . "\n";
    
    // 確認
    $checkSql = "SELECT * FROM invoices WHERE id = ?";
    $result = $db->fetch($checkSql, [$lastId]);
    
    echo json_encode([
        'step' => 'Verification',
        'inserted_record' => $result,
        'status' => 'success'
    ]) . "\n";
    
    echo json_encode([
        'final_status' => 'ALL TESTS PASSED',
        'message' => '請求書のINSERTは正常に動作します'
    ]) . "\n";
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], JSON_PRETTY_PRINT) . "\n";
}
?>
