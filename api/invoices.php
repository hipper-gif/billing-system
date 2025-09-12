<?php
/**
 * 請求書API
 * Smiley配食事業専用の請求書生成・管理API
 * 
 * @author Claude
 * @version 2.0.0 (修正版)
 * @created 2025-09-12
 */

// 出力バッファリング制御
ob_start();

// エラー出力制御
error_reporting(E_ALL);
ini_set('display_errors', 0);

try {
    // 必要なファイルを読み込み
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../classes/Database.php';
    require_once __DIR__ . '/../classes/SmileyInvoiceGenerator.php';
    
    // ヘッダー設定
    header('Content-Type: application/json; charset=utf-8');
    
    // 出力バッファをクリア
    ob_clean();
    
    // Databaseインスタンス取得（Singletonパターン）
    $db = Database::getInstance();
    
    // SmileyInvoiceGeneratorインスタンス作成
    $invoiceGenerator = new SmileyInvoiceGenerator($db);
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? 'generate';
        
        switch ($action) {
            case 'generate':
                // 請求書生成
                $params = [
                    'invoice_type' => $input['invoice_type'] ?? 'company_bulk',
                    'period_start' => $input['period_start'],
                    'period_end' => $input['period_end'],
                    'due_date' => $input['due_date'] ?? null,
                    'target_ids' => $input['target_ids'] ?? [],
                    'auto_generate_pdf' => $input['auto_generate_pdf'] ?? false // PDFは後回し
                ];
                
                // 入力値検証
                $errors = [];
                if (empty($params['period_start'])) {
                    $errors[] = '請求期間開始日は必須です';
                }
                if (empty($params['period_end'])) {
                    $errors[] = '請求期間終了日は必須です';
                }
                if (!empty($errors)) {
                    throw new Exception(implode(', ', $errors));
                }
                
                $result = $invoiceGenerator->generateInvoices($params);
                
                echo json_encode([
                    'success' => true,
                    'message' => "{$result['generated_invoices']}件の請求書を生成しました",
                    'data' => $result
                ], JSON_UNESCAPED_UNICODE);
                break;
                
            case 'update_status':
                // 請求書ステータス更新
                $invoiceId = $input['invoice_id'];
                $status = $input['status'];
                
                $sql = "UPDATE invoices SET status = ?, updated_at = NOW() WHERE id = ?";
                $stmt = $db->prepare($sql);
                $stmt->execute([$status, $invoiceId]);
                
                echo json_encode([
                    'success' => true,
                    'message' => '請求書ステータスを更新しました'
                ], JSON_UNESCAPED_UNICODE);
                break;
                
            default:
                throw new Exception('不正なアクションです: ' . $action);
        }
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
        
        $action = $_GET['action'] ?? 'list';
        
        switch ($action) {
            case 'list':
                // 請求書一覧取得
                $sql = "SELECT 
                            id, invoice_number, company_name, 
                            total_amount, status, invoice_date, due_date,
                            created_at, updated_at
                        FROM invoices 
                        ORDER BY created_at DESC 
                        LIMIT 50";
                
                $stmt = $db->prepare($sql);
                $stmt->execute();
                $invoices = $stmt->fetchAll();
                
                echo json_encode([
                    'success' => true,
                    'data' => [
                        'invoices' => $invoices,
                        'total_count' => count($invoices)
                    ]
                ], JSON_UNESCAPED_UNICODE);
                break;
                
            case 'detail':
                // 請求書詳細取得
                $invoiceId = $_GET['invoice_id'] ?? null;
                if (empty($invoiceId)) {
                    throw new Exception('請求書IDが指定されていません');
                }
                
                // 請求書基本情報
                $sql = "SELECT * FROM invoices WHERE id = ?";
                $stmt = $db->prepare($sql);
                $stmt->execute([$invoiceId]);
                $invoice = $stmt->fetch();
                
                if (!$invoice) {
                    throw new Exception('指定された請求書が見つかりません');
                }
                
                // 請求書明細情報
                $sql = "SELECT * FROM invoice_details WHERE invoice_id = ? ORDER BY delivery_date, user_name";
                $stmt = $db->prepare($sql);
                $stmt->execute([$invoiceId]);
                $details = $stmt->fetchAll();
                
                echo json_encode([
                    'success' => true,
                    'data' => [
                        'invoice' => $invoice,
                        'details' => $details
                    ]
                ], JSON_UNESCAPED_UNICODE);
                break;
                
            case 'summary':
                // 請求書サマリー（ダッシュボード用）
                $sql = "SELECT 
                            COUNT(*) as total_invoices,
                            SUM(CASE WHEN status = 'issued' THEN total_amount ELSE 0 END) as issued_amount,
                            SUM(CASE WHEN status = 'paid' THEN total_amount ELSE 0 END) as paid_amount,
                            SUM(CASE WHEN status = 'draft' THEN total_amount ELSE 0 END) as draft_amount,
                            COUNT(CASE WHEN status = 'overdue' THEN 1 END) as overdue_count
                        FROM invoices 
                        WHERE invoice_date >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)";
                
                $stmt = $db->prepare($sql);
                $stmt->execute();
                $summary = $stmt->fetch();
                
                echo json_encode([
                    'success' => true,
                    'data' => [
                        'summary' => $summary
                    ]
                ], JSON_UNESCAPED_UNICODE);
                break;
                
            default:
                throw new Exception('不正なアクションです: ' . $action);
        }
        
    } else {
        throw new Exception('サポートされていないHTTPメソッドです: ' . $_SERVER['REQUEST_METHOD']);
    }
    
} catch (Exception $e) {
    // エラー時の出力制御
    ob_clean();
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_type' => get_class($e),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ], JSON_UNESCAPED_UNICODE);
} finally {
    // 出力バッファ終了
    ob_end_flush();
}
?>
