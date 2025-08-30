<?php
/**
 * ReceiptGenerator - 領収書生成クラス
 * 
 * 機能:
 * - 事前領収書（配達前発行）
 * - 正式領収書（支払後発行）
 * - 収入印紙判定（5万円以上）
 * - 宛名事前設定対応
 * - 領収書番号管理
 * - PDF生成機能
 * 
 * @author Claude
 * @version 1.0
 * @date 2025-08-31
 */

require_once __DIR__ . '/Database.php';

class ReceiptGenerator {
    private $db;
    
    // 領収書タイプ定数
    const TYPE_ADVANCE = 'advance';     // 事前領収書
    const TYPE_PAYMENT = 'payment';     // 支払い後領収書
    const TYPE_SPLIT = 'split';         // 分割領収書
    
    // 印紙要否判定額（50,000円以上）
    const STAMP_REQUIRED_AMOUNT = 50000;
    
    // 領収書番号プレフィックス
    const RECEIPT_PREFIX = 'R';
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * 領収書を生成
     */
    public function generateReceipt($receiptData) {
        try {
            $this->db->beginTransaction();
            
            // 領収書番号を生成
            $receiptNumber = $this->generateReceiptNumber();
            
            // 印紙の要否を判定
            $stampRequired = $this->isStampRequired($receiptData['amount']);
            
            // 領収書データを準備
            $sql = "INSERT INTO receipts (
                        receipt_number, receipt_type, invoice_id, payment_id,
                        amount, recipient_name, recipient_company, purpose,
                        stamp_required, issue_date, fiscal_year,
                        notes, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $params = [
                $receiptNumber,
                $receiptData['receipt_type'],
                $receiptData['invoice_id'] ?? null,
                $receiptData['payment_id'] ?? null,
                $receiptData['amount'],
                $receiptData['recipient_name'],
                $receiptData['recipient_company'] ?? '',
                $receiptData['purpose'],
                $stampRequired ? 1 : 0,
                $receiptData['issue_date'] ?? date('Y-m-d'),
                $this->getFiscalYear($receiptData['issue_date'] ?? date('Y-m-d')),
                $receiptData['notes'] ?? ''
            ];
            
            $result = $this->db->execute($sql, $params);
            if (!$result) {
                throw new Exception("領収書の登録に失敗しました");
            }
            
            $receiptId = $this->db->lastInsertId();
            
            // 分割領収書の場合は明細を追加
            if ($receiptData['receipt_type'] === self::TYPE_SPLIT && !empty($receiptData['split_details'])) {
                $this->addSplitDetails($receiptId, $receiptData['split_details']);
            }
            
            // PDF生成
            $pdfPath = $this->generatePDF($receiptId);
            
            // PDF保存パスを更新
            $this->db->execute("UPDATE receipts SET pdf_path = ? WHERE id = ?", [$pdfPath, $receiptId]);
            
            $this->db->commit();
            
            return [
                'success' => true,
                'receipt_id' => $receiptId,
                'receipt_number' => $receiptNumber,
                'stamp_required' => $stampRequired,
                'pdf_path' => $pdfPath,
                'message' => '領収書が正常に生成されました'
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("領収書生成エラー: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * 領収書一覧を取得
     */
    public function getReceiptsList($filters = []) {
        $sql = "SELECT 
                    r.*,
                    i.invoice_number,
                    u.user_name,
                    c.company_name,
                    p.payment_date,
                    p.payment_method
                FROM receipts r
                LEFT JOIN invoices i ON r.invoice_id = i.id
                LEFT JOIN users u ON i.user_id = u.id
                LEFT JOIN companies c ON u.company_id = c.id
                LEFT JOIN payments p ON r.payment_id = p.id
                WHERE 1=1";
        
        $params = [];
        
        // フィルター条件を追加
        if (!empty($filters['date_from'])) {
            $sql .= " AND r.issue_date >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND r.issue_date <= ?";
            $params[] = $filters['date_to'];
        }
        
        if (!empty($filters['receipt_type'])) {
            $sql .= " AND r.receipt_type = ?";
            $params[] = $filters['receipt_type'];
        }
        
        if (!empty($filters['company_id'])) {
            $sql .= " AND c.id = ?";
            $params[] = $filters['company_id'];
        }
        
        if (!empty($filters['stamp_required']) && $filters['stamp_required'] !== 'all') {
            $stampValue = ($filters['stamp_required'] === 'yes') ? 1 : 0;
            $sql .= " AND r.stamp_required = ?";
            $params[] = $stampValue;
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (r.receipt_number LIKE ? OR r.recipient_name LIKE ? OR c.company_name LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $sql .= " ORDER BY r.issue_date DESC, r.id DESC";
        
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
     * 領収書詳細を取得
     */
    public function getReceiptDetails($receiptId) {
        $sql = "SELECT 
                    r.*,
                    i.invoice_number,
                    i.total_amount as invoice_amount,
                    u.user_name,
                    u.email,
                    c.company_name,
                    c.company_address,
                    c.contact_person,
                    p.payment_date,
                    p.payment_method,
                    p.reference_number
                FROM receipts r
                LEFT JOIN invoices i ON r.invoice_id = i.id
                LEFT JOIN users u ON i.user_id = u.id
                LEFT JOIN companies c ON u.company_id = c.id
                LEFT JOIN payments p ON r.payment_id = p.id
                WHERE r.id = ?";
        
        $receipt = $this->db->fetchOne($sql, [$receiptId]);
        
        if ($receipt && $receipt['receipt_type'] === self::TYPE_SPLIT) {
            // 分割領収書の明細を取得
            $receipt['split_details'] = $this->getSplitDetails($receiptId);
        }
        
        return $receipt;
    }
    
    /**
     * 領収書統計を取得
     */
    public function getReceiptStatistics($period = 'current_month') {
        $dateCondition = $this->getDateCondition($period);
        
        $sql = "SELECT 
                    COUNT(*) as total_receipts,
                    SUM(amount) as total_amount,
                    COUNT(CASE WHEN receipt_type = 'advance' THEN 1 END) as advance_receipts,
                    COUNT(CASE WHEN receipt_type = 'payment' THEN 1 END) as payment_receipts,
                    COUNT(CASE WHEN receipt_type = 'split' THEN 1 END) as split_receipts,
                    COUNT(CASE WHEN stamp_required = 1 THEN 1 END) as stamp_required_count,
                    SUM(CASE WHEN stamp_required = 1 THEN amount ELSE 0 END) as stamp_required_amount,
                    AVG(amount) as average_amount
                FROM receipts
                WHERE {$dateCondition}";
        
        return $this->db->fetchOne($sql);
    }
    
    /**
     * 事前領収書を生成
     */
    public function generateAdvanceReceipt($invoiceId, $advanceData) {
        // 請求書情報を取得
        $invoice = $this->getInvoiceForReceipt($invoiceId);
        if (!$invoice) {
            throw new Exception("請求書が見つかりません");
        }
        
        $receiptData = [
            'receipt_type' => self::TYPE_ADVANCE,
            'invoice_id' => $invoiceId,
            'amount' => $advanceData['amount'] ?? $invoice['total_amount'],
            'recipient_name' => $advanceData['recipient_name'] ?? $invoice['user_name'],
            'recipient_company' => $advanceData['recipient_company'] ?? $invoice['company_name'],
            'purpose' => $advanceData['purpose'] ?? '配食サービス料金として',
            'issue_date' => $advanceData['issue_date'] ?? date('Y-m-d'),
            'notes' => $advanceData['notes'] ?? '事前発行'
        ];
        
        return $this->generateReceipt($receiptData);
    }
    
    /**
     * 支払い後領収書を生成
     */
    public function generatePaymentReceipt($paymentId, $receiptData = []) {
        // 支払い情報を取得
        $payment = $this->getPaymentForReceipt($paymentId);
        if (!$payment) {
            throw new Exception("支払い記録が見つかりません");
        }
        
        $defaultReceiptData = [
            'receipt_type' => self::TYPE_PAYMENT,
            'invoice_id' => $payment['invoice_id'],
            'payment_id' => $paymentId,
            'amount' => $payment['amount'],
            'recipient_name' => $receiptData['recipient_name'] ?? $payment['user_name'],
            'recipient_company' => $receiptData['recipient_company'] ?? $payment['company_name'],
            'purpose' => $receiptData['purpose'] ?? '配食サービス料金として',
            'issue_date' => $receiptData['issue_date'] ?? $payment['payment_date'],
            'notes' => $receiptData['notes'] ?? ''
        ];
        
        return $this->generateReceipt($defaultReceiptData);
    }
    
    /**
     * 分割領収書を生成
     */
    public function generateSplitReceipt($invoiceId, $splitData) {
        $receiptData = [
            'receipt_type' => self::TYPE_SPLIT,
            'invoice_id' => $invoiceId,
            'amount' => array_sum(array_column($splitData['split_details'], 'amount')),
            'recipient_name' => $splitData['recipient_name'],
            'recipient_company' => $splitData['recipient_company'] ?? '',
            'purpose' => $splitData['purpose'] ?? '配食サービス料金として',
            'issue_date' => $splitData['issue_date'] ?? date('Y-m-d'),
            'split_details' => $splitData['split_details'],
            'notes' => $splitData['notes'] ?? '分割発行'
        ];
        
        return $this->generateReceipt($receiptData);
    }
    
    /**
     * 領収書を再発行
     */
    public function reissueReceipt($originalReceiptId, $reason = '') {
        try {
            // 元の領収書を取得
            $original = $this->getReceiptDetails($originalReceiptId);
            if (!$original) {
                throw new Exception("元の領収書が見つかりません");
            }
            
            // 再発行データを準備
            $reissueData = [
                'receipt_type' => $original['receipt_type'],
                'invoice_id' => $original['invoice_id'],
                'payment_id' => $original['payment_id'],
                'amount' => $original['amount'],
                'recipient_name' => $original['recipient_name'],
                'recipient_company' => $original['recipient_company'],
                'purpose' => $original['purpose'],
                'issue_date' => date('Y-m-d'), // 再発行日
                'notes' => "再発行 (元: {$original['receipt_number']}) - {$reason}"
            ];
            
            // 分割領収書の場合は明細も含める
            if ($original['receipt_type'] === self::TYPE_SPLIT && !empty($original['split_details'])) {
                $reissueData['split_details'] = $original['split_details'];
            }
            
            $result = $this->generateReceipt($reissueData);
            
            if ($result['success']) {
                // 元の領収書に再発行フラグを立てる
                $this->db->execute(
                    "UPDATE receipts SET reissued = 1, reissue_reason = ?, updated_at = NOW() WHERE id = ?",
                    [$reason, $originalReceiptId]
                );
            }
            
            return $result;
            
        } catch (Exception $e) {
            error_log("領収書再発行エラー: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * 領収書をキャンセル
     */
    public function cancelReceipt($receiptId, $reason = '') {
        try {
            $sql = "UPDATE receipts SET 
                        cancelled = 1,
                        cancellation_reason = ?,
                        cancelled_at = NOW(),
                        updated_at = NOW()
                    WHERE id = ?";
            
            $result = $this->db->execute($sql, [$reason, $receiptId]);
            
            if ($result) {
                return [
                    'success' => true,
                    'message' => '領収書がキャンセルされました'
                ];
            } else {
                throw new Exception("領収書のキャンセルに失敗しました");
            }
            
        } catch (Exception $e) {
            error_log("領収書キャンセルエラー: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * プライベートメソッド群
     */
    
    private function generateReceiptNumber() {
        $year = date('Y');
        $month = date('m');
        
        // 年月ベースの連番を取得
        $sql = "SELECT MAX(CAST(SUBSTRING(receipt_number, 8) AS UNSIGNED)) as max_num 
                FROM receipts 
                WHERE receipt_number LIKE ?";
        
        $pattern = self::RECEIPT_PREFIX . $year . $month . '%';
        $result = $this->db->fetchOne($sql, [$pattern]);
        
        $nextNumber = ($result['max_num'] ?? 0) + 1;
        
        return self::RECEIPT_PREFIX . $year . $month . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }
    
    private function isStampRequired($amount) {
        return floatval($amount) >= self::STAMP_REQUIRED_AMOUNT;
    }
    
    private function getFiscalYear($date) {
        $year = date('Y', strtotime($date));
        $month = date('n', strtotime($date));
        
        // 4月～3月を会計年度とする場合
        if ($month >= 4) {
            return $year;
        } else {
            return $year - 1;
        }
    }
    
    private function addSplitDetails($receiptId, $splitDetails) {
        $sql = "INSERT INTO receipt_details (receipt_id, description, amount, created_at) VALUES (?, ?, ?, NOW())";
        
        foreach ($splitDetails as $detail) {
            $this->db->execute($sql, [
                $receiptId,
                $detail['description'],
                $detail['amount']
            ]);
        }
    }
    
    private function getSplitDetails($receiptId) {
        $sql = "SELECT * FROM receipt_details WHERE receipt_id = ? ORDER BY id";
        return $this->db->fetchAll($sql, [$receiptId]);
    }
    
    private function getInvoiceForReceipt($invoiceId) {
        $sql = "SELECT 
                    i.*,
                    u.user_name,
                    c.company_name,
                    c.company_address
                FROM invoices i
                LEFT JOIN users u ON i.user_id = u.id
                LEFT JOIN companies c ON u.company_id = c.id
                WHERE i.id = ?";
        
        return $this->db->fetchOne($sql, [$invoiceId]);
    }
    
    private function getPaymentForReceipt($paymentId) {
        $sql = "SELECT 
                    p.*,
                    i.invoice_number,
                    u.user_name,
                    c.company_name
                FROM payments p
                LEFT JOIN invoices i ON p.invoice_id = i.id
                LEFT JOIN users u ON i.user_id = u.id
                LEFT JOIN companies c ON u.company_id = c.id
                WHERE p.id = ?";
        
        return $this->db->fetchOne($sql, [$paymentId]);
    }
    
    private function generatePDF($receiptId) {
        // 領収書詳細を取得
        $receipt = $this->getReceiptDetails($receiptId);
        if (!$receipt) {
            throw new Exception("領収書データが見つかりません");
        }
        
        // PDFファイル名を生成
        $fileName = "receipt_{$receipt['receipt_number']}.pdf";
        $filePath = "receipts/" . date('Y/m/') . $fileName;
        $fullPath = __DIR__ . "/../storage/" . $filePath;
        
        // ディレクトリを作成
        $dir = dirname($fullPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        // PDF生成（簡易HTML to PDF）
        $html = $this->generateReceiptHTML($receipt);
        
        // HTMLからPDFを生成（実際の環境では適切なPDFライブラリを使用）
        if ($this->saveHTMLToPDF($html, $fullPath)) {
            return $filePath;
        } else {
            throw new Exception("PDF生成に失敗しました");
        }
    }
    
    private function generateReceiptHTML($receipt) {
        $stampText = $receipt['stamp_required'] ? '※収入印紙（200円）を貼付' : '';
        $issueDate = date('年n月j日', strtotime($receipt['issue_date']));
        $amount = number_format($receipt['amount']);
        
        $html = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: 'MS Gothic', monospace; font-size: 14px; }
                .receipt { width: 100%; max-width: 800px; margin: 0 auto; border: 2px solid #000; padding: 20px; }
                .header { text-align: center; margin-bottom: 20px; }
                .title { font-size: 24px; font-weight: bold; margin-bottom: 10px; }
                .number { font-size: 14px; margin-bottom: 20px; }
                .content { margin: 20px 0; }
                .amount-line { border-top: 2px solid #000; border-bottom: 2px solid #000; padding: 10px; margin: 20px 0; text-align: center; }
                .amount { font-size: 20px; font-weight: bold; }
                .purpose { margin: 15px 0; }
                .date-signature { display: flex; justify-content: space-between; margin-top: 30px; }
                .stamp-area { border: 1px dashed #ccc; width: 100px; height: 60px; text-align: center; line-height: 60px; font-size: 10px; }
                .footer { margin-top: 30px; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='receipt'>
                <div class='header'>
                    <div class='title'>領 収 書</div>
                    <div class='number'>No. {$receipt['receipt_number']}</div>
                </div>
                
                <div class='content'>
                    <div style='margin-bottom: 20px;'>
                        <strong>{$receipt['recipient_company']}</strong><br>
                        <strong>{$receipt['recipient_name']}</strong> 様
                    </div>
                    
                    <div class='amount-line'>
                        <div class='amount'>￥ {$amount}-</div>
                    </div>
                    
                    <div class='purpose'>
                        上記金額を<strong>{$receipt['purpose']}</strong>として領収いたしました。
                    </div>
                    
                    <div class='date-signature'>
                        <div>
                            <strong>{$issueDate}</strong>
                        </div>
                        <div>
                            <div>株式会社Smiley</div>
                            <div>〒000-0000 住所</div>
                            <div>TEL: 000-000-0000</div>
                        </div>
                    </div>
                    
                    <div style='display: flex; justify-content: space-between; margin-top: 20px; align-items: center;'>
                        <div class='stamp-area'>{$stampText}</div>
                        <div style='text-align: right; font-size: 12px;'>
                            <div>印</div>
                        </div>
                    </div>
                </div>
                
                <div class='footer'>
                    {$receipt['notes']}
                </div>
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
    
    private function getDateCondition($period) {
        switch ($period) {
            case 'current_month':
                return "DATE_FORMAT(issue_date, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')";
            case 'last_month':
                return "DATE_FORMAT(issue_date, '%Y-%m') = DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), '%Y-%m')";
            case 'current_year':
                return "YEAR(issue_date) = YEAR(CURDATE())";
            case 'last_year':
                return "YEAR(issue_date) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 YEAR))";
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
                'RECEIPT_TYPES' => [
                    self::TYPE_ADVANCE,
                    self::TYPE_PAYMENT,
                    self::TYPE_SPLIT
                ],
                'STAMP_REQUIRED_AMOUNT' => self::STAMP_REQUIRED_AMOUNT,
                'RECEIPT_PREFIX' => self::RECEIPT_PREFIX
            ]
        ];
    }
}
