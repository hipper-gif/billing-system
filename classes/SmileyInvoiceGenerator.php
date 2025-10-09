<?php
/**
 * Smiley配食事業 請求書生成エンジン
 * 請求書データ生成・管理・PDF出力を担当
 * 
 * @author Claude
 * @version 1.0.0
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/SmileyInvoicePDF.php';

class SmileyInvoiceGenerator {
    
    private $db;
    
    // 請求書タイプ定数
    const TYPE_COMPANY_BULK = 'company_bulk';
    const TYPE_DEPARTMENT_BULK = 'department_bulk';
    const TYPE_INDIVIDUAL = 'individual';
    const TYPE_MIXED = 'mixed';
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * 請求書生成メイン処理
     * 
     * @param array $params 生成パラメータ
     * @return array 生成結果
     */
    public function generateInvoices($params) {
        $invoiceType = $params['invoice_type'] ?? self::TYPE_COMPANY_BULK;
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
                case self::TYPE_COMPANY_BULK:
                    $generatedInvoices = $this->generateCompanyBulkInvoices($targetIds, $periodStart, $periodEnd, $dueDate);
                    break;
                    
                case self::TYPE_DEPARTMENT_BULK:
                    $generatedInvoices = $this->generateDepartmentBulkInvoices($targetIds, $periodStart, $periodEnd, $dueDate);
                    break;
                    
                case self::TYPE_INDIVIDUAL:
                    $generatedInvoices = $this->generateIndividualInvoices($targetIds, $periodStart, $periodEnd, $dueDate);
                    break;
                    
                case self::TYPE_MIXED:
                    $generatedInvoices = $this->generateMixedInvoices($periodStart, $periodEnd, $dueDate);
                    break;
                    
                default:
                    throw new Exception('未対応の請求書タイプです');
            }
            
            // PDF自動生成
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
        
        // 対象企業が指定されていない場合は全企業
        if (empty($companyIds)) {
            $sql = "SELECT id FROM companies WHERE is_active = 1";
            $companies = $this->db->fetchAll($sql);
            $companyIds = array_column($companies, 'id');
        }
        
        foreach ($companyIds as $companyId) {
            // 企業の注文データ取得
            $orders = $this->getOrdersByCompany($companyId, $periodStart, $periodEnd);
            
            if (empty($orders)) {
                continue; // 注文がない企業はスキップ
            }
            
            // 請求書データ作成
            $invoiceId = $this->createInvoice([
                'invoice_type' => self::TYPE_COMPANY_BULK,
                'company_id' => $companyId,
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
        
        // 対象部署が指定されていない場合は全部署
        if (empty($departmentIds)) {
            $sql = "SELECT id FROM departments WHERE is_active = 1";
            $departments = $this->db->fetchAll($sql);
            $departmentIds = array_column($departments, 'id');
        }
        
        foreach ($departmentIds as $departmentId) {
            // 部署の注文データ取得
            $orders = $this->getOrdersByDepartment($departmentId, $periodStart, $periodEnd);
            
            if (empty($orders)) {
                continue;
            }
            
            // 請求書データ作成
            $invoiceId = $this->createInvoice([
                'invoice_type' => self::TYPE_DEPARTMENT_BULK,
                'department_id' => $departmentId,
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
        
        // 対象利用者が指定されていない場合は全利用者
        if (empty($userIds)) {
            $sql = "SELECT id FROM users WHERE is_active = 1";
            $users = $this->db->fetchAll($sql);
            $userIds = array_column($users, 'id');
        }
        
        foreach ($userIds as $userId) {
            // 利用者の注文データ取得
            $orders = $this->getOrdersByUser($userId, $periodStart, $periodEnd);
            
            if (empty($orders)) {
                continue;
            }
            
            // 請求書データ作成
            $invoiceId = $this->createInvoice([
                'invoice_type' => self::TYPE_INDIVIDUAL,
                'user_id' => $userId,
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
     * 混合請求書生成（企業設定に基づいて自動判定）
     */
    private function generateMixedInvoices($periodStart, $periodEnd, $dueDate) {
        $invoices = [];
        
        // 全企業の支払い方法を取得
        $sql = "SELECT id, payment_method FROM companies WHERE is_active = 1";
        $companies = $this->db->fetchAll($sql);
        
        foreach ($companies as $company) {
            switch ($company['payment_method']) {
                case 'company_bulk':
                    $companyInvoices = $this->generateCompanyBulkInvoices([$company['id']], $periodStart, $periodEnd, $dueDate);
                    $invoices = array_merge($invoices, $companyInvoices);
                    break;
                    
                case 'department_bulk':
                    // この企業の全部署
                    $sql = "SELECT id FROM departments WHERE company_id = ? AND is_active = 1";
                    $departments = $this->db->fetchAll($sql, [$company['id']]);
                    $departmentIds = array_column($departments, 'id');
                    $deptInvoices = $this->generateDepartmentBulkInvoices($departmentIds, $periodStart, $periodEnd, $dueDate);
                    $invoices = array_merge($invoices, $deptInvoices);
                    break;
                    
                case 'individual':
                    // この企業の全利用者
                    $sql = "SELECT id FROM users WHERE company_id = ? AND is_active = 1";
                    $users = $this->db->fetchAll($sql, [$company['id']]);
                    $userIds = array_column($users, 'id');
                    $userInvoices = $this->generateIndividualInvoices($userIds, $periodStart, $periodEnd, $dueDate);
                    $invoices = array_merge($invoices, $userInvoices);
                    break;
            }
        }
        
        return $invoices;
    }
    
    /**
     * 請求書データベースレコード作成（実際のテーブル構造に対応）
     */
    private function createInvoice($data) {
        $invoiceType = $data['invoice_type'];
        $orders = $data['orders'];
        $periodStart = $data['period_start'];
        $periodEnd = $data['period_end'];
        $dueDate = $data['due_date'];
        
        // 金額計算
        $subtotal = array_sum(array_column($orders, 'total_amount'));
        $taxRate = 10.00; // 10%
        $taxAmount = round($subtotal * 0.10);
        $totalAmount = $subtotal + $taxAmount;
        
        // 請求書番号生成
        $invoiceNumber = $this->generateInvoiceNumber();
        
        // 注文データから情報を取得
        $firstOrder = $orders[0];
        $companyName = $firstOrder['company_name'] ?? '';
        $department = $firstOrder['department_name'] ?? null;
        $userId = $data['user_id'] ?? $firstOrder['user_id'] ?? null;
        $userCode = $firstOrder['user_code'] ?? '';
        $userName = $firstOrder['user_name'] ?? '';
        
        // 請求書レコード挿入（実際のテーブル構造に対応）
        $sql = "INSERT INTO invoices (
                    invoice_number, user_id, user_code, user_name,
                    company_name, department,
                    invoice_date, due_date, period_start, period_end,
                    subtotal, tax_rate, tax_amount, total_amount,
                    invoice_type, status,
                    created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, 'draft', NOW(), NOW())";
        
        $params = [
            $invoiceNumber,
            $userId,
            $userCode,
            $userName,
            $companyName,
            $department,
            $dueDate,
            $periodStart,
            $periodEnd,
            $subtotal,
            $taxRate,
            $taxAmount,
            $totalAmount,
            $invoiceType
        ];
        
        $this->db->execute($sql, $params);
        $invoiceId = $this->db->lastInsertId();
        
        // 請求書明細挿入（invoice_detailsテーブルが存在する場合）
        try {
            foreach ($orders as $order) {
                $detailSql = "INSERT INTO invoice_details (
                                invoice_id, delivery_date, user_id, user_name,
                                product_id, product_name, quantity, unit_price, total_amount
                              ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                $this->db->execute($detailSql, [
                    $invoiceId,
                    $order['delivery_date'],
                    $order['user_id'] ?? null,
                    $order['user_name'],
                    $order['product_id'] ?? null,
                    $order['product_name'],
                    $order['quantity'],
                    $order['unit_price'],
                    $order['total_amount']
                ]);
            }
        } catch (Exception $e) {
            // invoice_detailsテーブルが存在しない場合はスキップ
            error_log("Invoice details insertion failed: " . $e->getMessage());
        }
        
        return $invoiceId;
    }
    
    /**
     * 企業別注文データ取得
     */
    private function getOrdersByCompany($companyId, $periodStart, $periodEnd) {
        $sql = "SELECT * FROM orders 
                WHERE company_id = ? 
                AND delivery_date >= ? 
                AND delivery_date <= ?
                ORDER BY delivery_date, user_name";
        
        return $this->db->fetchAll($sql, [$companyId, $periodStart, $periodEnd]);
    }
    
    /**
     * 部署別注文データ取得
     */
    private function getOrdersByDepartment($departmentId, $periodStart, $periodEnd) {
        $sql = "SELECT * FROM orders 
                WHERE department_id = ? 
                AND delivery_date >= ? 
                AND delivery_date <= ?
                ORDER BY delivery_date, user_name";
        
        return $this->db->fetchAll($sql, [$departmentId, $periodStart, $periodEnd]);
    }
    
    /**
     * 個人別注文データ取得
     */
    private function getOrdersByUser($userId, $periodStart, $periodEnd) {
        $sql = "SELECT * FROM orders 
                WHERE user_id = ? 
                AND delivery_date >= ? 
                AND delivery_date <= ?
                ORDER BY delivery_date";
        
        return $this->db->fetchAll($sql, [$userId, $periodStart, $periodEnd]);
    }
    
    /**
     * 請求書番号生成
     */
    private function generateInvoiceNumber() {
        $year = date('Y');
        $month = date('m');
        
        // 同月の最新番号取得
        $sql = "SELECT invoice_number FROM invoices 
                WHERE invoice_number LIKE ? 
                ORDER BY created_at DESC LIMIT 1";
        
        $prefix = "SMY-{$year}{$month}-";
        $lastInvoice = $this->db->fetch($sql, [$prefix . '%']);
        
        if ($lastInvoice) {
            // 既存番号から連番取得
            $lastNumber = intval(substr($lastInvoice['invoice_number'], -3));
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }
        
        return $prefix . str_pad($newNumber, 3, '0', STR_PAD_LEFT);
    }
    
    /**
     * 支払期限計算（期間終了日+30日）
     */
    private function calculateDueDate($periodEnd) {
        $date = new DateTime($periodEnd);
        $date->modify('+30 days');
        return $date->format('Y-m-d');
    }
    
    /**
     * 注文データから企業ID取得
     */
    private function getCompanyIdFromOrders($orders) {
        if (empty($orders)) {
            return null;
        }
        return $orders[0]['company_id'] ?? null;
    }
    
    /**
     * 請求書データ取得（実際のテーブル構造に対応）
     */
    public function getInvoiceData($invoiceId) {
        // 基本情報
        $sql = "SELECT i.*,
                       i.invoice_date as issue_date,
                       i.company_name as billing_company_name
                FROM invoices i
                WHERE i.id = ?";
        
        $invoice = $this->db->fetch($sql, [$invoiceId]);
        
        if (!$invoice) {
            throw new Exception('請求書が見つかりません');
        }
        
        // 明細取得（invoice_detailsテーブルが存在する場合）
        try {
            $detailSql = "SELECT * FROM invoice_details WHERE invoice_id = ? ORDER BY delivery_date, user_name";
            $invoice['details'] = $this->db->fetchAll($detailSql, [$invoiceId]);
        } catch (Exception $e) {
            // invoice_detailsテーブルが存在しない場合は空配列
            $invoice['details'] = [];
        }
        
        // 統計計算
        $invoice['order_count'] = count($invoice['details']);
        $invoice['total_quantity'] = array_sum(array_column($invoice['details'], 'quantity'));
        
        return $invoice;
    }
    
    /**
     * 請求書一覧取得（実際のテーブル構造に対応）
     */
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
        
        // 総件数
        $countSQL = "SELECT COUNT(*) as total FROM invoices i {$whereSQL}";
        $countResult = $this->db->fetch($countSQL, $params);
        $totalCount = $countResult['total'];
        
        // データ取得
        $sql = "SELECT i.*,
                       i.invoice_date as issue_date,
                       i.company_name as billing_company_name
                FROM invoices i
                {$whereSQL}
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
    
    /**
     * 請求書ステータス更新
     */
    public function updateInvoiceStatus($invoiceId, $status, $notes = '') {
        $sql = "UPDATE invoices SET status = ?, notes = ?, updated_at = NOW() WHERE id = ?";
        $this->db->execute($sql, [$status, $notes, $invoiceId]);
        return true;
    }
    
    /**
     * 請求書削除（論理削除）
     */
    public function deleteInvoice($invoiceId) {
        return $this->updateInvoiceStatus($invoiceId, 'cancelled', '削除');
    }
    
    /**
     * PDF生成
     * 
     * @param int $invoiceId 請求書ID
     * @return string PDFファイルパス
     */
    public function generatePDF($invoiceId) {
        // 請求書データ取得
        $invoiceData = $this->getInvoiceData($invoiceId);
        
        // SmileyInvoicePDF を使用してPDF生成
        $pdfGenerator = new SmileyInvoicePDF();
        $pdfPath = $pdfGenerator->generateInvoicePDF($invoiceData);
        
        return $pdfPath;
    }
    
    /**
     * PDFをブラウザに出力
     */
    public function outputPDF($invoiceId) {
        $invoiceData = $this->getInvoiceData($invoiceId);
        
        $pdfGenerator = new SmileyInvoicePDF();
        $pdfGenerator->outputInvoicePDF($invoiceData);
    }
}
?>
