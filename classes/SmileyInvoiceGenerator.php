<?php
/**
 * Smiley配食事業 請求書生成エンジン
 * 請求書データ生成・管理・PDF出力を担当
 * 
 * @author Claude
 * @version 2.1.0 - company_id/user_id自動取得対応
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/SmileyInvoicePDF.php';

class SmileyInvoiceGenerator {

    private $db;
    private $lastInvoiceNumber = null; // セッション内で生成された最後の請求書番号を追跡

    // 請求書タイプ定数（データベースENUMに合わせる）
    const TYPE_COMPANY_BULK = 'company';
    const TYPE_DEPARTMENT_BULK = 'department';
    const TYPE_INDIVIDUAL = 'individual';
    const TYPE_MIXED = 'mixed';

    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * フロントエンドからの invoice_type を正規化
     */
    private function normalizeInvoiceType($type) {
        $mapping = [
            'company_bulk' => 'company',
            'department_bulk' => 'department',
            'individual' => 'individual',
            'mixed' => 'mixed',
            'company' => 'company',
            'department' => 'department'
        ];
        
        return $mapping[$type] ?? 'company';
    }
    
    /**
     * 請求書生成メイン処理
     */
    public function generateInvoices($params) {
        $invoiceType = $this->normalizeInvoiceType($params['invoice_type'] ?? 'company_bulk');
        $periodStart = $params['period_start'];
        $periodEnd = $params['period_end'];
        $dueDate = $params['due_date'] ?? $this->calculateDueDate($periodEnd);
        $targetIds = $params['target_ids'] ?? [];
        $autoPdf = $params['auto_generate_pdf'] ?? false;
        
        $generatedInvoices = [];
        $errors = [];
        
        try {
            $this->db->beginTransaction();
            
            switch ($invoiceType) {
                case 'company':
                    $generatedInvoices = $this->generateCompanyBulkInvoices($targetIds, $periodStart, $periodEnd, $dueDate);
                    break;
                    
                case 'department':
                    $generatedInvoices = $this->generateDepartmentBulkInvoices($targetIds, $periodStart, $periodEnd, $dueDate);
                    break;
                    
                case 'individual':
                    $generatedInvoices = $this->generateIndividualInvoices($targetIds, $periodStart, $periodEnd, $dueDate);
                    break;
                    
                case 'mixed':
                    $generatedInvoices = $this->generateMixedInvoices($periodStart, $periodEnd, $dueDate);
                    break;
                    
                default:
                    throw new Exception('未対応の請求書タイプです: ' . $invoiceType);
            }
            
            if ($autoPdf) {
                foreach ($generatedInvoices as &$invoice) {
                    try {
                        $pdfPath = $this->generatePDF($invoice['id']);
                        $invoice['pdf_path'] = $pdfPath;
                    } catch (Exception $e) {
                        $errors[] = "請求書ID {$invoice['id']} のPDF生成に失敗: " . $e->getMessage();
                    }
                }
            }
            
            $this->db->commit();
            
            return [
                'success' => true,
                'total_invoices' => count($generatedInvoices),
                'invoices' => $generatedInvoices,
                'errors' => $errors
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * 企業一括請求書生成
     */
    private function generateCompanyBulkInvoices($companyIds, $periodStart, $periodEnd, $dueDate) {
        $invoices = [];
        
        if (empty($companyIds)) {
            $sql = "SELECT DISTINCT company_name FROM orders WHERE order_date >= ? AND order_date <= ?";
            $companies = $this->db->fetchAll($sql, [$periodStart, $periodEnd]);
            $companyNames = array_column($companies, 'company_name');
        } else {
            $sql = "SELECT company_name FROM companies WHERE id IN (" . implode(',', array_map('intval', $companyIds)) . ")";
            $companies = $this->db->fetchAll($sql);
            $companyNames = array_column($companies, 'company_name');
        }
        
        foreach ($companyNames as $companyName) {
            $orders = $this->getOrdersByCompanyName($companyName, $periodStart, $periodEnd);
            
            if (empty($orders)) {
                continue;
            }
            
            $invoiceId = $this->createInvoice([
                'invoice_type' => 'company',
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'due_date' => $dueDate,
                'orders' => $orders
            ]);
            
            $invoices[] = $this->getInvoiceData($invoiceId);
        }
        
        return $invoices;
    }
    
    /**
     * 部署別一括請求書生成
     */
    private function generateDepartmentBulkInvoices($departmentIds, $periodStart, $periodEnd, $dueDate) {
        $invoices = [];
        
        if (empty($departmentIds)) {
            $sql = "SELECT DISTINCT company_name, department_name FROM orders
                    WHERE order_date >= ? AND order_date <= ? AND department_name IS NOT NULL";
            $departments = $this->db->fetchAll($sql, [$periodStart, $periodEnd]);
        } else {
            $sql = "SELECT c.company_name, d.department_name 
                    FROM departments d 
                    JOIN companies c ON d.company_id = c.id 
                    WHERE d.id IN (" . implode(',', array_map('intval', $departmentIds)) . ")";
            $departments = $this->db->fetchAll($sql);
        }
        
        foreach ($departments as $dept) {
            $orders = $this->getOrdersByDepartmentName($dept['company_name'], $dept['department_name'], $periodStart, $periodEnd);
            
            if (empty($orders)) {
                continue;
            }
            
            $invoiceId = $this->createInvoice([
                'invoice_type' => 'department',
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'due_date' => $dueDate,
                'orders' => $orders
            ]);
            
            $invoices[] = $this->getInvoiceData($invoiceId);
        }
        
        return $invoices;
    }
    
    /**
     * 個人別請求書生成
     */
    private function generateIndividualInvoices($userIds, $periodStart, $periodEnd, $dueDate) {
        $invoices = [];
        
        if (empty($userIds)) {
            $sql = "SELECT DISTINCT user_id, user_code, user_name FROM orders
                    WHERE order_date >= ? AND order_date <= ?";
            $users = $this->db->fetchAll($sql, [$periodStart, $periodEnd]);
        } else {
            $sql = "SELECT id as user_id, user_code, user_name FROM users WHERE id IN (" . implode(',', array_map('intval', $userIds)) . ")";
            $users = $this->db->fetchAll($sql);
        }
        
        foreach ($users as $user) {
            $orders = $this->getOrdersByUserId($user['user_id'], $periodStart, $periodEnd);
            
            if (empty($orders)) {
                continue;
            }
            
            $invoiceId = $this->createInvoice([
                'invoice_type' => 'individual',
                'user_id' => $user['user_id'],
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'due_date' => $dueDate,
                'orders' => $orders
            ]);
            
            $invoices[] = $this->getInvoiceData($invoiceId);
        }
        
        return $invoices;
    }
    
    /**
     * 混合請求書生成（企業内で個人・部署の混在）
     */
    private function generateMixedInvoices($periodStart, $periodEnd, $dueDate) {
        $invoices = [];
        
        $sql = "SELECT DISTINCT company_name FROM orders WHERE order_date >= ? AND order_date <= ?";
        $companies = $this->db->fetchAll($sql, [$periodStart, $periodEnd]);
        
        foreach ($companies as $company) {
            $companyName = $company['company_name'];
            $orders = $this->getOrdersByCompanyName($companyName, $periodStart, $periodEnd);
            
            if (empty($orders)) {
                continue;
            }
            
            $invoiceId = $this->createInvoice([
                'invoice_type' => 'mixed',
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'due_date' => $dueDate,
                'orders' => $orders
            ]);
            
            $invoices[] = $this->getInvoiceData($invoiceId);
        }
        
        return $invoices;
    }
    
    /**
     * 請求書データベースレコード作成
     * 
     * ★★★ v2.1.0 修正箇所 ★★★
     * - company_idの自動取得追加
     * - user_idの再取得処理追加
     * - INSERT文にcompany_idカラム追加
     */
    private function createInvoice($data) {
        $invoiceType = $this->normalizeInvoiceType($data['invoice_type']);
        $orders = $data['orders'];
        $periodStart = $data['period_start'];
        $periodEnd = $data['period_end'];
        $dueDate = $data['due_date'];
        
        // 金額計算（税込み価格）
        $totalAmount = array_sum(array_column($orders, 'total_amount'));
        $subtotal = $totalAmount;  // 互換性のため同じ値を設定
        $taxRate = 0.00;  // 税計算なし
        $taxAmount = 0;   // 税額なし
        
        // 請求書番号生成
        $invoiceNumber = $this->generateInvoiceNumber();
        
        // 基本情報取得
        $firstOrder = $orders[0];
        $companyName = $firstOrder['company_name'] ?? '';
        $department = $firstOrder['department_name'] ?? null;
        $userId = $data['user_id'] ?? $firstOrder['user_id'] ?? null;
        $userCode = $firstOrder['user_code'] ?? '';
        $userName = $firstOrder['user_name'] ?? '';
        
        // ===== ★修正1: company_id の取得 =====
        $companyId = null;
        if (!empty($companyName)) {
            $companyQuery = "SELECT id FROM companies WHERE company_name = ? LIMIT 1";
            $companyResult = $this->db->fetch($companyQuery, [$companyName]);
            $companyId = $companyResult ? $companyResult['id'] : null;
            
            // company_idが取得できない場合はログに記録（エラーにはしない・後方互換性維持）
            if (!$companyId) {
                error_log("Warning: company_id not found for company_name: {$companyName}");
            }
        }
        
        // ===== ★修正2: user_id の再取得（user_idがnullの場合） =====
        if (!$userId && !empty($userCode)) {
            $userQuery = "SELECT id FROM users WHERE user_code = ? LIMIT 1";
            $userResult = $this->db->fetch($userQuery, [$userCode]);
            $userId = $userResult ? $userResult['id'] : null;
            
            // user_idが取得できない場合はログに記録（エラーにはしない・後方互換性維持）
            if (!$userId) {
                error_log("Warning: user_id not found for user_code: {$userCode}");
            }
        }
        
        // ===== ★修正3: INSERT文にcompany_idカラムを追加 =====
        $sql = "INSERT INTO invoices (
                    invoice_number, company_id, user_id, user_code, user_name,
                    company_name, department,
                    invoice_date, due_date, period_start, period_end,
                    subtotal, tax_rate, tax_amount, total_amount,
                    invoice_type, status,
                    created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, 'draft', NOW(), NOW())";
        
        // ===== ★修正4: パラメータ配列にcompany_idを追加 =====
        $params = [
            $invoiceNumber, $companyId, $userId, $userCode, $userName,
            $companyName, $department, $dueDate, $periodStart, $periodEnd,
            $subtotal, $taxRate, $taxAmount, $totalAmount, $invoiceType
        ];
        
        $this->db->execute($sql, $params);
        $invoiceId = $this->db->lastInsertId();

        // 明細データ挿入
        $detailCount = 0;
        $totalOrders = count($orders);

        error_log("======================================");
        error_log("Starting invoice detail insertion for Invoice #{$invoiceId}");
        error_log("Total orders to insert: {$totalOrders}");
        error_log("First order sample: " . json_encode($orders[0] ?? []));
        error_log("======================================");

        foreach ($orders as $index => $order) {
            try {
                // デバッグ: 各フィールドの値を確認
                $orderId = $order['id'] ?? null;
                $orderDate = $order['order_date'] ?? null;
                $productCode = $order['product_code'] ?? '';
                $productName = $order['product_name'] ?? '商品名不明';
                $quantity = $order['quantity'] ?? 0;
                $unitPrice = $order['unit_price'] ?? 0;
                $amount = $order['total_amount'] ?? 0;

                if ($orderId === null) {
                    error_log("WARNING: Order #{$index} has NULL id! Skipping. Order data: " . json_encode($order));
                    continue;
                }

                // ★修正: order_date が '0000-00-00' の場合は period_start を使用
                if ($orderDate === null || $orderDate === '' || $orderDate === '0000-00-00') {
                    error_log("WARNING: Order #{$orderId} has invalid order_date: '{$orderDate}'. Using period_start instead: {$periodStart}");
                    $orderDate = $periodStart;
                }

                $detailSql = "INSERT INTO invoice_details (
                                invoice_id, order_id, order_date,
                                product_code, product_name,
                                quantity, unit_price, amount, created_at
                              ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";

                $this->db->execute($detailSql, [
                    $invoiceId,
                    $orderId,
                    $orderDate,
                    $productCode,
                    $productName,
                    $quantity,
                    $unitPrice,
                    $amount
                ]);
                $detailCount++;
            } catch (Exception $e) {
                error_log("ERROR: Invoice detail insertion failed for order " . ($order['id'] ?? 'unknown') . ": " . $e->getMessage());
                error_log("ERROR: SQL Error Code: " . ($e->getCode() ?? 'N/A'));
                error_log("ERROR: Full order data: " . json_encode($order));
            }
        }

        error_log("======================================");
        error_log("Invoice #{$invoiceId}: Successfully inserted {$detailCount} of {$totalOrders} order details");
        error_log("======================================");
        
        return $invoiceId;
    }
    
    private function getOrdersByCompanyName($companyName, $periodStart, $periodEnd) {
        $sql = "SELECT * FROM orders
                WHERE company_name = ?
                AND order_date >= ?
                AND order_date <= ?
                ORDER BY order_date, user_name";

        return $this->db->fetchAll($sql, [$companyName, $periodStart, $periodEnd]);
    }
    
    private function getOrdersByDepartmentName($companyName, $departmentName, $periodStart, $periodEnd) {
        $sql = "SELECT * FROM orders
                WHERE company_name = ?
                AND department_name = ?
                AND order_date >= ?
                AND order_date <= ?
                ORDER BY order_date, user_name";

        return $this->db->fetchAll($sql, [$companyName, $departmentName, $periodStart, $periodEnd]);
    }
    
    private function getOrdersByUserId($userId, $periodStart, $periodEnd) {
        $sql = "SELECT * FROM orders
                WHERE user_id = ?
                AND order_date >= ?
                AND order_date <= ?
                ORDER BY order_date";

        return $this->db->fetchAll($sql, [$userId, $periodStart, $periodEnd]);
    }
    
    private function generateInvoiceNumber() {
        $year = date('Y');
        $month = date('m');
        $prefix = "SMY-{$year}{$month}-";

        // セッション内で既に請求書番号を生成している場合は、それをベースにする
        if ($this->lastInvoiceNumber !== null && strpos($this->lastInvoiceNumber, $prefix) === 0) {
            // 同じ月の請求書番号が既に生成されている場合、インクリメント
            $lastNumber = intval(substr($this->lastInvoiceNumber, -3));
            $newNumber = $lastNumber + 1;
        } else {
            // データベースから最後の請求書番号を取得
            $sql = "SELECT invoice_number FROM invoices
                    WHERE invoice_number LIKE ?
                    ORDER BY created_at DESC LIMIT 1";

            $lastInvoice = $this->db->fetch($sql, [$prefix . '%']);

            if ($lastInvoice) {
                $lastNumber = intval(substr($lastInvoice['invoice_number'], -3));
                $newNumber = $lastNumber + 1;
            } else {
                $newNumber = 1;
            }
        }

        $invoiceNumber = $prefix . str_pad($newNumber, 3, '0', STR_PAD_LEFT);

        // 生成した請求書番号を記憶
        $this->lastInvoiceNumber = $invoiceNumber;

        return $invoiceNumber;
    }
    
    private function calculateDueDate($periodEnd) {
        $date = new DateTime($periodEnd);
        $date->modify('+30 days');
        return $date->format('Y-m-d');
    }
    
    public function getInvoiceData($invoiceId) {
        $sql = "SELECT i.*, i.invoice_date as issue_date,
                       i.company_name as billing_company_name
                FROM invoices i WHERE i.id = ?";
        
        $invoice = $this->db->fetch($sql, [$invoiceId]);
        
        if (!$invoice) {
            throw new Exception('請求書が見つかりません');
        }
        
        try {
            $detailSql = "SELECT * FROM invoice_details WHERE invoice_id = ? ORDER BY order_date";
            $invoice['details'] = $this->db->fetchAll($detailSql, [$invoiceId]);
        } catch (Exception $e) {
            $invoice['details'] = [];
        }
        
        $invoice['order_count'] = count($invoice['details']);
        $invoice['total_quantity'] = array_sum(array_column($invoice['details'], 'quantity'));
        
        return $invoice;
    }
    
    public function getInvoiceList($filters = [], $page = 1, $limit = 50) {
        $offset = ($page - 1) * $limit;
        $whereClauses = [];
        $params = [];
        
        if (!empty($filters['company_name'])) {
            $whereClauses[] = "i.company_name LIKE ?";
            $params[] = '%' . $filters['company_name'] . '%';
        }
        
        if (!empty($filters['status'])) {
            $whereClauses[] = "i.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['invoice_type'])) {
            $whereClauses[] = "i.invoice_type = ?";
            $params[] = $filters['invoice_type'];
        }
        
        $whereSQL = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';
        
        $countSQL = "SELECT COUNT(*) as total FROM invoices i {$whereSQL}";
        $countResult = $this->db->fetch($countSQL, $params);
        $totalCount = $countResult['total'];
        
        $sql = "SELECT i.*, i.invoice_date as issue_date,
                       i.company_name as billing_company_name
                FROM invoices i {$whereSQL}
                ORDER BY i.created_at DESC
                LIMIT ? OFFSET ?";
        
        $params[] = $limit;
        $params[] = $offset;
        
        $invoices = $this->db->fetchAll($sql, $params);
        
        return [
            'invoices' => $invoices,
            'total_count' => $totalCount,
            'page' => $page,
            'limit' => $limit
        ];
    }
    
    public function updateInvoiceStatus($invoiceId, $status, $notes = '') {
        $sql = "UPDATE invoices SET status = ?, notes = ?, updated_at = NOW() WHERE id = ?";
        $this->db->execute($sql, [$status, $notes, $invoiceId]);
        return true;
    }
    
    public function deleteInvoice($invoiceId) {
        return $this->updateInvoiceStatus($invoiceId, 'cancelled', '削除');
    }
    
    public function generatePDF($invoiceId) {
        $invoiceData = $this->getInvoiceData($invoiceId);
        $pdfGenerator = new SmileyInvoicePDF();
        $pdfPath = $pdfGenerator->generateInvoicePDF($invoiceData);
        return $pdfPath;
    }
    
    public function outputPDF($invoiceId) {
        $invoiceData = $this->getInvoiceData($invoiceId);
        $pdfGenerator = new SmileyInvoicePDF();
        $pdfGenerator->outputInvoicePDF($invoiceData);
    }
}
?>
