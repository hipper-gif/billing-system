<?php
/**
 * InvoiceGenerator - 請求書生成クラス
 * 
 * 機能:
 * - 企業一括請求（企業別まとめ）
 * - 部署別請求（部署毎に分割）
 * - 個人請求（利用者個別）
 * - 請求タイプ自動判定
 * - 金額計算・消費税処理
 * - 請求書番号自動採番
 * - PDF生成機能
 * 
 * @author Claude
 * @version 1.0
 * @date 2025-08-31
 */

// ✅ 修正版: config/database.php の正しい読み込み
if (!class_exists('Database')) {
    require_once __DIR__ . '/../config/database.php';
}

class SmileyInvoiceGenerator {
    private $db;
    
    // 請求タイプ定数
    const BILLING_TYPE_COMPANY = 'company';         // 企業一括
    const BILLING_TYPE_DEPARTMENT = 'department';   // 部署別
    const BILLING_TYPE_INDIVIDUAL = 'individual';   // 個人別
    const BILLING_TYPE_MIXED = 'mixed';             // 混合
    
    // 請求書ステータス定数
    const STATUS_DRAFT = 'draft';
    const STATUS_ISSUED = 'issued';
    const STATUS_SENT = 'sent';
    const STATUS_PAID = 'paid';
    const STATUS_PARTIAL = 'partial';
    const STATUS_OVERDUE = 'overdue';
    const STATUS_CANCELLED = 'cancelled';
    
    // 消費税率
    const TAX_RATE = 0.10; // 10%
    
    // 請求書番号プレフィックス
    const INVOICE_PREFIX = 'INV';
    
    public function __construct() {
        // ✅ 修正版: 正しい Singleton パターンの使用
        $this->db = Database::getInstance();
    }
    
    /**
     * 請求書を一括生成
     */
    public function generateInvoices($generationData) {
        try {
            $this->db->beginTransaction();
            
            $results = [
                'success' => true,
                'generated_invoices' => [],
                'total_invoices' => 0,
                'total_amount' => 0,
                'errors' => []
            ];
            
            // 注文データを取得
            $orders = $this->getOrdersForInvoicing($generationData);
            if (empty($orders)) {
                throw new Exception("指定期間に請求対象の注文データが見つかりません");
            }
            
            // 請求タイプに基づいてグループ化
            $groupedOrders = $this->groupOrdersByBillingType($orders, $generationData['billing_type']);
            
            // 各グループごとに請求書を生成
            foreach ($groupedOrders as $groupKey => $groupOrders) {
                try {
                    $invoiceData = $this->prepareInvoiceData($groupOrders, $generationData);
                    $invoice = $this->createInvoice($invoiceData);
                    
                    if ($invoice['success']) {
                        $results['generated_invoices'][] = $invoice;
                        $results['total_invoices']++;
                        $results['total_amount'] += $invoice['total_amount'];
                        
                        // PDF生成（設定されている場合）
                        if ($generationData['auto_generate_pdf'] ?? false) {
                            $this->generateInvoicePDF($invoice['invoice_id']);
                        }
                    } else {
                        $results['errors'][] = "グループ {$groupKey}: " . $invoice['message'];
                    }
                    
                } catch (Exception $e) {
                    $results['errors'][] = "グループ {$groupKey}: " . $e->getMessage();
                }
            }
            
            // エラーがある場合は部分的な成功として処理
            if (!empty($results['errors']) && $results['total_invoices'] === 0) {
                throw new Exception("すべての請求書生成に失敗しました: " . implode(', ', $results['errors']));
            }
            
            $this->db->commit();
            
            $results['message'] = "{$results['total_invoices']}件の請求書を生成しました（総額: " . number_format($results['total_amount']) . "円）";
            
            return $results;
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("請求書一括生成エラー: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * 単一の請求書を生成
     */
    public function generateSingleInvoice($invoiceData) {
        try {
            $this->db->beginTransaction();
            
            $invoice = $this->createInvoice($invoiceData);
            
            if ($invoice['success']) {
                // 請求書明細を追加
                $this->addInvoiceDetails($invoice['invoice_id'], $invoiceData['orders']);
                
                // PDF生成
                if ($invoiceData['auto_generate_pdf'] ?? true) {
                    $pdfPath = $this->generateInvoicePDF($invoice['invoice_id']);
                    $this->db->execute("UPDATE invoices SET pdf_path = ? WHERE id = ?", [$pdfPath, $invoice['invoice_id']]);
                }
                
                $this->db->commit();
                return $invoice;
            } else {
                throw new Exception($invoice['message']);
            }
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("単一請求書生成エラー: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * 請求書一覧を取得
     */
    public function getInvoicesList($filters = []) {
        $sql = "SELECT 
                    i.*,
                    u.user_name,
                    c.company_name,
                    d.department_name,
                    COUNT(id_details.id) as detail_count,
                    COALESCE(SUM(p.amount), 0) as paid_amount,
                    (i.total_amount - COALESCE(SUM(p.amount), 0)) as outstanding_amount
                FROM invoices i
                LEFT JOIN users u ON i.user_id = u.id
                LEFT JOIN companies c ON u.company_id = c.id
                LEFT JOIN departments d ON u.department_id = d.id
                LEFT JOIN invoice_details id_details ON i.id = id_details.invoice_id
                LEFT JOIN payments p ON i.id = p.invoice_id AND p.status != 'cancelled'
                WHERE 1=1";
        
        $params = [];
        
        // フィルター条件を追加
        if (!empty($filters['status'])) {
            $sql .= " AND i.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND i.invoice_date >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND i.invoice_date <= ?";
            $params[] = $filters['date_to'];
        }
        
        if (!empty($filters['company_id'])) {
            $sql .= " AND c.id = ?";
            $params[] = $filters['company_id'];
        }
        
        if (!empty($filters['billing_type'])) {
            $sql .= " AND i.billing_type = ?";
            $params[] = $filters['billing_type'];
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (i.invoice_number LIKE ? OR u.user_name LIKE ? OR c.company_name LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $sql .= " GROUP BY i.id ORDER BY i.invoice_date DESC, i.id DESC";
        
        // ページネーション
        if (!empty($filters['limit'])) {
            $offset = (!empty($filters['page']) ? ($filters['page'] - 1) * $filters['limit'] : 0);
            $sql .= " LIMIT ? OFFSET ?";
            $params[] = intval($filters['limit']);
            $params[] = $offset;
        }
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * 請求書詳細を取得
     */
    public function getInvoiceDetails($invoiceId) {
        // 請求書基本情報
        $sql = "SELECT 
                    i.*,
                    u.user_name,
                    u.email,
                    u.phone,
                    c.company_name,
                    c.company_address,
                    c.contact_person,
                    c.contact_email,
                    c.contact_phone,
                    d.department_name,
                    COALESCE(SUM(p.amount), 0) as paid_amount
                FROM invoices i
                LEFT JOIN users u ON i.user_id = u.id
                LEFT JOIN companies c ON u.company_id = c.id
                LEFT JOIN departments d ON u.department_id = d.id
                LEFT JOIN payments p ON i.id = p.invoice_id AND p.status != 'cancelled'
                WHERE i.id = ?
                GROUP BY i.id";
        
        $invoice = $this->db->fetchOne($sql, [$invoiceId]);
        
        if ($invoice) {
            // 請求書明細を取得
            $invoice['details'] = $this->getInvoiceDetailItems($invoiceId);
            
            // 支払い履歴を取得
            $invoice['payment_history'] = $this->getPaymentHistory($invoiceId);
            
            // 残金計算
            $invoice['outstanding_amount'] = $invoice['total_amount'] - $invoice['paid_amount'];
        }
        
        return $invoice;
    }
    
    /**
     * 請求書統計を取得
     */
    public function getInvoiceStatistics($period = 'current_month') {
        $dateCondition = $this->getDateCondition($period);
        
        $sql = "SELECT 
                    COUNT(*) as total_invoices,
                    SUM(total_amount) as total_amount,
                    COUNT(CASE WHEN status = 'draft' THEN 1 END) as draft_invoices,
                    COUNT(CASE WHEN status = 'issued' THEN 1 END) as issued_invoices,
                    COUNT(CASE WHEN status = 'sent' THEN 1 END) as sent_invoices,
                    COUNT(CASE WHEN status = 'paid' THEN 1 END) as paid_invoices,
                    COUNT(CASE WHEN status = 'partial' THEN 1 END) as partial_invoices,
                    COUNT(CASE WHEN status = 'overdue' THEN 1 END) as overdue_invoices,
                    SUM(CASE WHEN status = 'paid' THEN total_amount ELSE 0 END) as paid_amount,
                    SUM(CASE WHEN status IN ('issued', 'sent', 'partial', 'overdue') THEN total_amount ELSE 0 END) as outstanding_amount,
                    AVG(total_amount) as average_amount,
                    COUNT(CASE WHEN billing_type = 'company' THEN 1 END) as company_invoices,
                    COUNT(CASE WHEN billing_type = 'department' THEN 1 END) as department_invoices,
                    COUNT(CASE WHEN billing_type = 'individual' THEN 1 END) as individual_invoices
                FROM invoices
                WHERE {$dateCondition}";
        
        return $this->db->fetchOne($sql);
    }
    
    /**
     * 請求書ステータスを更新
     */
    public function updateInvoiceStatus($invoiceId, $status, $notes = '') {
        try {
            $sql = "UPDATE invoices SET 
                        status = ?,
                        status_updated_at = NOW(),
                        updated_at = NOW()";
            
            $params = [$status, $invoiceId];
            
            if ($notes) {
                $sql .= ", notes = CONCAT(COALESCE(notes, ''), '\n', ?)";
                array_splice($params, 1, 0, $notes);
            }
            
            $sql .= " WHERE id = ?";
            
            $result = $this->db->execute($sql, $params);
            
            if ($result) {
                return [
                    'success' => true,
                    'message' => 'ステータスが更新されました'
                ];
            } else {
                throw new Exception("ステータス更新に失敗しました");
            }
            
        } catch (Exception $e) {
            error_log("請求書ステータス更新エラー: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * 請求書をキャンセル
     */
    public function cancelInvoice($invoiceId, $reason = '') {
        try {
            $this->db->beginTransaction();
            
            // 支払い記録があるかチェック
            $payments = $this->db->fetchAll("SELECT * FROM payments WHERE invoice_id = ? AND status != 'cancelled'", [$invoiceId]);
            if (!empty($payments)) {
                throw new Exception("支払い記録がある請求書はキャンセルできません");
            }
            
            // 請求書をキャンセル
            $sql = "UPDATE invoices SET 
                        status = 'cancelled',
                        cancellation_reason = ?,
                        cancelled_at = NOW(),
                        updated_at = NOW()
                    WHERE id = ?";
            
            $this->db->execute($sql, [$reason, $invoiceId]);
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => '請求書がキャンセルされました'
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("請求書キャンセルエラー: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * プライベートメソッド群
     */
    
    private function getOrdersForInvoicing($generationData) {
        $sql = "SELECT 
                    o.*,
                    u.company_id,
                    u.department_id,
                    c.billing_method,
                    d.separate_billing
                FROM orders o
                LEFT JOIN users u ON o.user_code = u.user_code
                LEFT JOIN companies c ON u.company_id = c.id
                LEFT JOIN departments d ON u.department_id = d.id
                WHERE o.order_date BETWEEN ? AND ?";
        
        $params = [$generationData['period_start'], $generationData['period_end']];
        
        // 企業フィルター
        if (!empty($generationData['company_ids'])) {
            $placeholders = str_repeat('?,', count($generationData['company_ids']) - 1) . '?';
            $sql .= " AND u.company_id IN ($placeholders)";
            $params = array_merge($params, $generationData['company_ids']);
        }
        
        // ユーザーフィルター
        if (!empty($generationData['user_ids'])) {
            $placeholders = str_repeat('?,', count($generationData['user_ids']) - 1) . '?';
            $sql .= " AND u.id IN ($placeholders)";
            $params = array_merge($params, $generationData['user_ids']);
        }
        
        // 既に請求済みの注文を除外
        $sql .= " AND o.id NOT IN (
                    SELECT order_id FROM invoice_details 
                    WHERE order_id IS NOT NULL
                )";
        
        $sql .= " ORDER BY u.company_id, u.department_id, o.user_code, o.order_date";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    private function groupOrdersByBillingType($orders, $billingType) {
        $groups = [];
        
        foreach ($orders as $order) {
            $groupKey = $this->generateGroupKey($order, $billingType);
            
            if (!isset($groups[$groupKey])) {
                $groups[$groupKey] = [];
            }
            
            $groups[$groupKey][] = $order;
        }
        
        return $groups;
    }
    
    private function generateGroupKey($order, $billingType) {
        switch ($billingType) {
            case self::BILLING_TYPE_COMPANY:
                return "company_{$order['company_id']}";
            
            case self::BILLING_TYPE_DEPARTMENT:
                return "department_{$order['department_id']}";
            
            case self::BILLING_TYPE_INDIVIDUAL:
                return "user_{$order['user_code']}";
            
            case self::BILLING_TYPE_MIXED:
                // 企業の請求方法に基づいて自動判定
                if ($order['separate_billing']) {
                    return "department_{$order['department_id']}";
                } elseif ($order['billing_method'] === 'individual') {
                    return "user_{$order['user_code']}";
                } else {
                    return "company_{$order['company_id']}";
                }
            
            default:
                return "individual_{$order['user_code']}";
        }
    }
    
    private function prepareInvoiceData($orders, $generationData) {
        if (empty($orders)) {
            throw new Exception("請求対象の注文がありません");
        }
        
        $firstOrder = $orders[0];
        
        // 小計計算
        $subtotal = array_sum(array_column($orders, 'total_amount'));
        
        // 消費税計算
        $taxAmount = $this->calculateTax($subtotal);
        
        // 合計金額
        $totalAmount = $subtotal + $taxAmount;
        
        return [
            'user_id' => $this->getUserIdFromOrders($orders),
            'invoice_number' => $this->generateInvoiceNumber(),
            'invoice_date' => $generationData['invoice_date'] ?? date('Y-m-d'),
            'due_date' => $generationData['due_date'],
            'period_start' => $generationData['period_start'],
            'period_end' => $generationData['period_end'],
            'billing_type' => $generationData['billing_type'],
            'subtotal' => $subtotal,
            'tax_rate' => self::TAX_RATE,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
            'notes' => $generationData['notes'] ?? '',
            'orders' => $orders
        ];
    }
    
    private function createInvoice($invoiceData) {
        try {
            $sql = "INSERT INTO invoices (
                        user_id, invoice_number, invoice_date, due_date,
                        period_start, period_end, billing_type,
                        subtotal, tax_rate, tax_amount, total_amount,
                        status, notes, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', ?, NOW())";
            
            $params = [
                $invoiceData['user_id'],
                $invoiceData['invoice_number'],
                $invoiceData['invoice_date'],
                $invoiceData['due_date'],
                $invoiceData['period_start'],
                $invoiceData['period_end'],
                $invoiceData['billing_type'],
                $invoiceData['subtotal'],
                $invoiceData['tax_rate'],
                $invoiceData['tax_amount'],
                $invoiceData['total_amount'],
                $invoiceData['notes']
            ];
            
            $result = $this->db->execute($sql, $params);
            if (!$result) {
                throw new Exception("請求書の作成に失敗しました");
            }
            
            $invoiceId = $this->db->lastInsertId();
            
            // 請求書明細を追加
            $this->addInvoiceDetails($invoiceId, $invoiceData['orders']);
            
            return [
                'success' => true,
                'invoice_id' => $invoiceId,
                'invoice_number' => $invoiceData['invoice_number'],
                'total_amount' => $invoiceData['total_amount'],
                'message' => '請求書が作成されました'
            ];
            
        } catch (Exception $e) {
            throw new Exception("請求書作成エラー: " . $e->getMessage());
        }
    }
    
    private function addInvoiceDetails($invoiceId, $orders) {
        $sql = "INSERT INTO invoice_details (
                    invoice_id, order_id, product_name, quantity,
                    unit_price, total_amount, order_date, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
        
        foreach ($orders as $order) {
            $params = [
                $invoiceId,
                $order['id'],
                $order['product_name'],
                $order['quantity'],
                $order['unit_price'],
                $order['total_amount'],
                $order['order_date']
            ];
            
            $this->db->execute($sql, $params);
        }
    }
    
    private function getUserIdFromOrders($orders) {
        // 注文から代表的なユーザーIDを取得
        $userCodes = array_unique(array_column($orders, 'user_code'));
        
        if (count($userCodes) === 1) {
            // 単一ユーザーの場合
            $user = $this->db->fetchOne("SELECT id FROM users WHERE user_code = ?", [$userCodes[0]]);
            return $user['id'];
        } else {
            // 複数ユーザーの場合は最初のユーザー（企業・部署代表）
            $user = $this->db->fetchOne("SELECT id FROM users WHERE user_code = ?", [$userCodes[0]]);
            return $user['id'];
        }
    }
    
    private function generateInvoiceNumber() {
        $year = date('Y');
        $month = date('m');
        
        // 年月ベースの連番を取得
        $sql = "SELECT MAX(CAST(SUBSTRING(invoice_number, 8) AS UNSIGNED)) as max_num 
                FROM invoices 
                WHERE invoice_number LIKE ?";
        
        $pattern = self::INVOICE_PREFIX . $year . $month . '%';
        $result = $this->db->fetchOne($sql, [$pattern]);
        
        $nextNumber = ($result['max_num'] ?? 0) + 1;
        
        return self::INVOICE_PREFIX . $year . $month . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }
    
    private function calculateTax($subtotal) {
        return floor($subtotal * self::TAX_RATE);
    }
    
    private function generateInvoicePDF($invoiceId) {
        // 請求書詳細を取得
        $invoice = $this->getInvoiceDetails($invoiceId);
        if (!$invoice) {
            throw new Exception("請求書データが見つかりません");
        }
        
        // PDFファイル名を生成
        $fileName = "invoice_{$invoice['invoice_number']}.pdf";
        $filePath = "invoices/" . date('Y/m/') . $fileName;
        $fullPath = __DIR__ . "/../storage/" . $filePath;
        
        // ディレクトリを作成
        $dir = dirname($fullPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        // PDF生成（簡易HTML to PDF）
        $html = $this->generateInvoiceHTML($invoice);
        
        // HTMLからPDFを生成
        if ($this->saveHTMLToPDF($html, $fullPath)) {
            return $filePath;
        } else {
            throw new Exception("PDF生成に失敗しました");
        }
    }
    
    private function generateInvoiceHTML($invoice) {
        $invoiceDate = date('年n月j日', strtotime($invoice['invoice_date']));
        $dueDate = date('年n月j日', strtotime($invoice['due_date']));
        $periodStart = date('年n月j日', strtotime($invoice['period_start']));
        $periodEnd = date('年n月j日', strtotime($invoice['period_end']));
        
        $subtotal = number_format($invoice['subtotal']);
        $taxAmount = number_format($invoice['tax_amount']);
        $totalAmount = number_format($invoice['total_amount']);
        
        $detailsHtml = '';
        foreach ($invoice['details'] as $detail) {
            $detailsHtml .= "
                <tr>
                    <td>" . date('m/d', strtotime($detail['order_date'])) . "</td>
                    <td>{$detail['product_name']}</td>
                    <td class='text-center'>{$detail['quantity']}</td>
                    <td class='text-right'>" . number_format($detail['unit_price']) . "</td>
                    <td class='text-right'>" . number_format($detail['total_amount']) . "</td>
                </tr>";
        }
        
        $html = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: 'MS Gothic', monospace; font-size: 12px; margin: 20px; }
                .invoice { width: 100%; max-width: 800px; margin: 0 auto; }
                .header { display: flex; justify-content: space-between; margin-bottom: 30px; }
                .title { font-size: 20px; font-weight: bold; text-align: center; margin-bottom: 20px; }
                .company-info { text-align: right; }
                .customer-info { margin-bottom: 20px; }
                .invoice-info { margin-bottom: 30px; }
                table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                th, td { border: 1px solid #000; padding: 8px; }
                th { background-color: #f0f0f0; text-align: center; }
                .text-center { text-align: center; }
                .text-right { text-align: right; }
                .total-section { margin-top: 20px; }
                .total-row { display: flex; justify-content: flex-end; margin-bottom: 5px; }
                .total-label { width: 100px; text-align: right; margin-right: 20px; }
                .total-amount { width: 120px; text-align: right; border-bottom: 1px solid #000; padding: 5px; }
                .grand-total { font-size: 16px; font-weight: bold; }
            </style>
        </head>
        <body>
            <div class='invoice'>
                <div class='header'>
                    <div>
                        <div style='font-size: 16px; font-weight: bold;'>{$invoice['company_name']}</div>
                        <div>{$invoice['user_name']} 様</div>
                    </div>
                    <div class='company-info'>
                        <div style='font-weight: bold;'>株式会社Smiley</div>
                        <div>〒000-0000 住所</div>
                        <div>TEL: 000-000-0000</div>
                    </div>
                </div>
                
                <div class='title'>請 求 書</div>
                
                <div class='invoice-info'>
                    <div style='display: flex; justify-content: space-between;'>
                        <div>
                            <div>請求書番号: {$invoice['invoice_number']}</div>
                            <div>請求日: {$invoiceDate}</div>
                            <div>支払期限: {$dueDate}</div>
                        </div>
                        <div>
                            <div>対象期間: {$periodStart} ～ {$periodEnd}</div>
                        </div>
                    </div>
                </div>
                
                <table>
                    <thead>
                        <tr>
                            <th width='80px'>日付</th>
                            <th>商品名</th>
                            <th width='60px'>数量</th>
                            <th width='80px'>単価</th>
                            <th width='100px'>金額</th>
                        </tr>
                    </thead>
                    <tbody>
                        {$detailsHtml}
                    </tbody>
                </table>
                
                <div class='total-section'>
                    <div class='total-row'>
                        <div class='total-label'>小計:</div>
                        <div class='total-amount'>￥{$subtotal}</div>
                    </div>
                    <div class='total-row'>
                        <div class='total-label'>消費税(10%):</div>
                        <div class='total-amount'>￥{$taxAmount}</div>
                    </div>
                    <div class='total-row grand-total'>
                        <div class='total-label'>合計:</div>
                        <div class='total-amount'>￥{$totalAmount}</div>
                    </div>
                </div>
                
                <div style='margin-top: 30px; font-size: 11px;'>
                    <div>お支払い方法: 銀行振込</div>
                    <div>振込先: ○○銀行 ○○支店 普通 1234567</div>
                    <div>口座名: 株式会社Smiley</div>
                    <div style='margin-top: 10px;'>※振込手数料はお客様負担でお願いいたします。</div>
                </div>
                
                " . (!empty($invoice['notes']) ? "<div style='margin-top: 20px;'><strong>備考:</strong><br>{$invoice['notes']}</div>" : "") . "
            </div>
        </body>
        </html>";
        
        return $html;
    }
    
    private function saveHTMLToPDF($html, $filePath) {
        // 実際の実装ではDOMPDFやTCPDF等のライブラリを使用
        // ここでは簡易的にHTMLファイルとして保存
        $htmlFile = str_replace('.pdf', '.html', $filePath);
        return file_put_contents($htmlFile, $html) !== false;
    }
    
    private function getInvoiceDetailItems($invoiceId) {
        $sql = "SELECT * FROM invoice_details WHERE invoice_id = ? ORDER BY order_date, id";
        return $this->db->fetchAll($sql, [$invoiceId]);
    }
    
    private function getPaymentHistory($invoiceId) {
        $sql = "SELECT * FROM payments WHERE invoice_id = ? AND status != 'cancelled' ORDER BY payment_date DESC";
        return $this->db->fetchAll($sql, [$invoiceId]);
    }
    
    private function getDateCondition($period) {
        switch ($period) {
            case 'current_month':
                return "DATE_FORMAT(invoice_date, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')";
            case 'last_month':
                return "DATE_FORMAT(invoice_date, '%Y-%m') = DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), '%Y-%m')";
            case 'current_year':
                return "YEAR(invoice_date) = YEAR(CURDATE())";
            case 'last_year':
                return "YEAR(invoice_date) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 YEAR))";
            default:
                return "1=1";
        }
    }
    
    /**
     * デバッグ用メソッド
     */
    public function getDebugInfo() {
        return [
            'class_name' => __CLASS__,
            'database_connected' => $this->db->testConnection(),
            'constants' => [
                'BILLING_TYPES' => [
                    self::BILLING_TYPE_COMPANY,
                    self::BILLING_TYPE_DEPARTMENT,
                    self::BILLING_TYPE_INDIVIDUAL,
                    self::BILLING_TYPE_MIXED
                ],
                'STATUSES' => [
                    self::STATUS_DRAFT,
                    self::STATUS_ISSUED,
                    self::STATUS_SENT,
                    self::STATUS_PAID,
                    self::STATUS_PARTIAL,
                    self::STATUS_OVERDUE,
                    self::STATUS_CANCELLED
                ],
                'TAX_RATE' => self::TAX_RATE,
                'INVOICE_PREFIX' => self::INVOICE_PREFIX
            ]
        ];
    }
}
