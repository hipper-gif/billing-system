<?php
/**
 * 領収書生成エンジン
 * Smiley配食事業 集金管理システム
 * 
 * 機能:
 * - 事前領収書（配達前発行）
 * - 正式領収書（支払後発行）
 * - 収入印紙判定（5万円以上）
 * - 分割領収書（印紙回避）
 * - PDF生成・ファイル管理
 */

require_once 'Database.php';

class ReceiptGenerator 
{
    private $db;
    
    // 収入印紙が必要な金額の境界値
    const STAMP_THRESHOLD = 50000;
    const STAMP_AMOUNT = 200;
    
    // 領収書番号生成フォーマット
    const RECEIPT_NUMBER_FORMAT = 'RCP-%s-%04d';
    
    public function __construct($db = null) 
    {
        // Database Singleton パターン対応
        $this->db = $db ?: Database::getInstance();
    }
    
    /**
     * 領収書生成（メイン処理）
     * 
     * @param array $params 生成パラメータ
     * @return array 生成結果
     */
    public function generateReceipt($params) 
    {
        $this->db->beginTransaction();
        
        try {
            // パラメータ検証
            $this->validateReceiptParams($params);
            
            // 収入印紙判定
            $stampRequired = $this->requiresStamp($params['amount']);
            
            // 領収書番号生成
            $receiptNumber = $this->generateReceiptNumber();
            
            // 領収書データ作成
            $receiptData = [
                'receipt_number' => $receiptNumber,
                'invoice_id' => $params['invoice_id'] ?? null,
                'payment_id' => $params['payment_id'] ?? null,
                'issue_date' => $params['issue_date'] ?? date('Y-m-d'),
                'amount' => $params['amount'],
                'tax_amount' => $params['tax_amount'] ?? $this->calculateTax($params['amount']),
                'recipient_name' => $params['recipient_name'],
                'purpose' => $params['purpose'] ?? 'お弁当代として',
                'stamp_required' => $stampRequired,
                'stamp_amount' => $stampRequired ? self::STAMP_AMOUNT : 0,
                'receipt_type' => $params['receipt_type'] ?? 'payment',
                'status' => 'issued',
                'notes' => $params['notes'] ?? null
            ];
            
            // データベースに挿入
            $receiptId = $this->insertReceipt($receiptData);
            
            // PDF生成
            $pdfPath = $this->generateReceiptPDF($receiptId, $receiptData);
            
            // PDFパス更新
            if ($pdfPath) {
                $this->updateReceiptFilePath($receiptId, $pdfPath);
                $receiptData['file_path'] = $pdfPath;
            }
            
            // 関連する請求書のステータス更新（必要に応じて）
            if (!empty($params['invoice_id'])) {
                $this->updateInvoiceStatus($params['invoice_id']);
            }
            
            $this->db->commit();
            
            return [
                'success' => true,
                'receipt_id' => $receiptId,
                'receipt_number' => $receiptNumber,
                'pdf_path' => $pdfPath,
                'stamp_required' => $stampRequired,
                'data' => $receiptData
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw new Exception("領収書生成に失敗しました: " . $e->getMessage());
        }
    }
    
    /**
     * 事前領収書生成（配達前発行）
     * 
     * @param array $params 生成パラメータ
     * @return array 生成結果
     */
    public function generateAdvanceReceipt($params) 
    {
        $params['receipt_type'] = 'advance';
        $params['purpose'] = $params['purpose'] ?? 'お弁当代として（事前発行）';
        
        return $this->generateReceipt($params);
    }
    
    /**
     * 分割領収書生成（収入印紙回避）
     * 
     * @param array $params 生成パラメータ
     * @return array 生成結果
     */
    public function generateSplitReceipts($params) 
    {
        $totalAmount = $params['amount'];
        $splitAmounts = $params['split_amounts'] ?? [];
        
        // 分割金額が指定されていない場合は自動分割
        if (empty($splitAmounts)) {
            $splitAmounts = $this->calculateOptimalSplit($totalAmount);
        }
        
        // 分割金額の合計チェック
        $splitTotal = array_sum($splitAmounts);
        if ($splitTotal != $totalAmount) {
            throw new Exception("分割金額の合計が総額と一致しません");
        }
        
        $results = [];
        
        foreach ($splitAmounts as $index => $amount) {
            $splitParams = $params;
            $splitParams['amount'] = $amount;
            $splitParams['receipt_type'] = 'split';
            $splitParams['purpose'] = ($params['purpose'] ?? 'お弁当代として') . "（分割 " . ($index + 1) . "/" . count($splitAmounts) . "）";
            
            $result = $this->generateReceipt($splitParams);
            $results[] = $result;
        }
        
        return [
            'success' => true,
            'split_count' => count($results),
            'total_amount' => $totalAmount,
            'receipts' => $results
        ];
    }
    
    /**
     * 領収書一覧取得
     * 
     * @param array $filters フィルター条件
     * @param int $page ページ番号
     * @param int $limit 取得件数
     * @return array 領収書一覧
     */
    public function getReceiptList($filters = [], $page = 1, $limit = 50) 
    {
        $offset = ($page - 1) * $limit;
        
        $sql = "SELECT r.*, 
                       i.invoice_number,
                       p.payment_date,
                       p.payment_method
                FROM receipts r
                LEFT JOIN invoices i ON r.invoice_id = i.id
                LEFT JOIN payments p ON r.payment_id = p.id
                WHERE 1=1";
        
        $params = [];
        
        // フィルター条件追加
        if (!empty($filters['invoice_id'])) {
            $sql .= " AND r.invoice_id = ?";
            $params[] = $filters['invoice_id'];
        }
        
        if (!empty($filters['receipt_type'])) {
            $sql .= " AND r.receipt_type = ?";
            $params[] = $filters['receipt_type'];
        }
        
        if (!empty($filters['status'])) {
            $sql .= " AND r.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND r.issue_date >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND r.issue_date <= ?";
            $params[] = $filters['date_to'];
        }
        
        if (!empty($filters['stamp_required'])) {
            $sql .= " AND r.stamp_required = ?";
            $params[] = $filters['stamp_required'];
        }
        
        if (!empty($filters['recipient_name'])) {
            $sql .= " AND r.recipient_name LIKE ?";
            $params[] = '%' . $filters['recipient_name'] . '%';
        }
        
        // 並び順
        $sql .= " ORDER BY r.issue_date DESC, r.created_at DESC";
        
        // ページネーション
        $sql .= " LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $this->db->prepare($sql);
        $receipts = $stmt->execute($params)->fetchAll();
        
        // 総件数取得
        $countSql = str_replace("SELECT r.*, i.invoice_number, p.payment_date, p.payment_method", "SELECT COUNT(*)", 
                                str_replace(" LIMIT ? OFFSET ?", "", $sql));
        array_pop($params); // OFFSET削除
        array_pop($params); // LIMIT削除
        
        $countStmt = $this->db->prepare($countSql);
        $totalCount = $countStmt->execute($params)->fetchColumn();
        
        return [
            'receipts' => $receipts,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total' => $totalCount,
                'total_pages' => ceil($totalCount / $limit)
            ]
        ];
    }
    
    /**
     * 領収書詳細取得
     * 
     * @param int $receiptId 領収書ID
     * @return array 領収書詳細
     */
    public function getReceiptDetail($receiptId) 
    {
        $sql = "SELECT r.*, 
                       i.invoice_number,
                       i.total_amount as invoice_total,
                       p.payment_date,
                       p.payment_method,
                       p.amount as payment_amount
                FROM receipts r
                LEFT JOIN invoices i ON r.invoice_id = i.id
                LEFT JOIN payments p ON r.payment_id = p.id
                WHERE r.id = ?";
        
        $stmt = $this->db->prepare($sql);
        $receipt = $stmt->execute([$receiptId])->fetch();
        
        if (!$receipt) {
            throw new Exception("指定された領収書が見つかりません");
        }
        
        return $receipt;
    }
    
    /**
     * 領収書統計情報取得
     * 
     * @param string $month 対象月（YYYY-MM）
     * @return array 統計情報
     */
    public function getReceiptStatistics($month = null) 
    {
        if (!$month) {
            $month = date('Y-m');
        }
        
        $sql = "SELECT 
                    COUNT(*) as total_count,
                    COUNT(CASE WHEN status = 'issued' THEN 1 END) as issued_count,
                    COUNT(CASE WHEN receipt_type = 'advance' THEN 1 END) as advance_count,
                    COUNT(CASE WHEN stamp_required = 1 THEN 1 END) as stamp_required_count,
                    SUM(amount) as total_amount,
                    SUM(CASE WHEN stamp_required = 1 THEN stamp_amount ELSE 0 END) as total_stamp_amount
                FROM receipts 
                WHERE DATE_FORMAT(issue_date, '%Y-%m') = ?";
        
        $stmt = $this->db->prepare($sql);
        $stats = $stmt->execute([$month])->fetch();
        
        return [
            'total_count' => (int)$stats['total_count'],
            'issued_count' => (int)$stats['issued_count'],
            'advance_count' => (int)$stats['advance_count'],
            'stamp_required_count' => (int)$stats['stamp_required_count'],
            'total_amount' => (float)$stats['total_amount'],
            'total_stamp_amount' => (float)$stats['total_stamp_amount']
        ];
    }
    
    /**
     * 領収書再発行
     * 
     * @param int $originalReceiptId 元の領収書ID
     * @return array 再発行結果
     */
    public function reissueReceipt($originalReceiptId) 
    {
        $originalReceipt = $this->getReceiptDetail($originalReceiptId);
        
        if (!$originalReceipt) {
            throw new Exception("元の領収書が見つかりません");
        }
        
        // 再発行用パラメータ作成
        $params = [
            'invoice_id' => $originalReceipt['invoice_id'],
            'payment_id' => $originalReceipt['payment_id'],
            'amount' => $originalReceipt['amount'],
            'recipient_name' => $originalReceipt['recipient_name'],
            'purpose' => $originalReceipt['purpose'] . '（再発行）',
            'receipt_type' => $originalReceipt['receipt_type'],
            'notes' => '元領収書番号: ' . $originalReceipt['receipt_number']
        ];
        
        return $this->generateReceipt($params);
    }
    
    /**
     * 収入印紙が必要かどうか判定
     * 
     * @param float $amount 金額
     * @return bool 印紙が必要かどうか
     */
    public function requiresStamp($amount) 
    {
        return $amount >= self::STAMP_THRESHOLD;
    }
    
    /**
     * 最適な分割金額計算（収入印紙回避）
     * 
     * @param float $totalAmount 総額
     * @return array 分割金額配列
     */
    private function calculateOptimalSplit($totalAmount) 
    {
        if ($totalAmount < self::STAMP_THRESHOLD) {
            return [$totalAmount];
        }
        
        // 印紙が必要な場合は49,999円と残額に分割
        $splitAmounts = [];
        $remaining = $totalAmount;
        
        while ($remaining >= self::STAMP_THRESHOLD) {
            $splitAmounts[] = self::STAMP_THRESHOLD - 1; // 49,999円
            $remaining -= (self::STAMP_THRESHOLD - 1);
        }
        
        if ($remaining > 0) {
            $splitAmounts[] = $remaining;
        }
        
        return $splitAmounts;
    }
    
    /**
     * 領収書番号生成
     * 
     * @return string 領収書番号
     */
    private function generateReceiptNumber() 
    {
        $datePrefix = date('Ymd');
        
        // 同日の領収書番号の最大値取得
        $sql = "SELECT MAX(CAST(SUBSTRING(receipt_number, -4) AS UNSIGNED)) as max_number
                FROM receipts 
                WHERE receipt_number LIKE ?";
        
        $stmt = $this->db->prepare($sql);
        $maxNumber = $stmt->execute(['RCP-' . $datePrefix . '-%'])->fetchColumn();
        
        $nextNumber = ($maxNumber ?? 0) + 1;
        
        return sprintf(self::RECEIPT_NUMBER_FORMAT, $datePrefix, $nextNumber);
    }
    
    /**
     * 消費税計算
     * 
     * @param float $amount 税抜金額
     * @param float $taxRate 消費税率（デフォルト10%）
     * @return float 消費税額
     */
    private function calculateTax($amount, $taxRate = 0.10) 
    {
        return floor($amount * $taxRate);
    }
    
    /**
     * パラメータ検証
     * 
     * @param array $params パラメータ
     * @throws Exception バリデーションエラー
     */
    private function validateReceiptParams($params) 
    {
        $requiredFields = ['amount', 'recipient_name'];
        
        foreach ($requiredFields as $field) {
            if (empty($params[$field])) {
                throw new Exception("必須項目が不足しています: {$field}");
            }
        }
        
        if (!is_numeric($params['amount']) || $params['amount'] <= 0) {
            throw new Exception("金額は正の数値である必要があります");
        }
        
        if (!empty($params['receipt_type']) && 
            !in_array($params['receipt_type'], ['advance', 'payment', 'split'])) {
            throw new Exception("無効な領収書タイプです");
        }
        
        if (!empty($params['invoice_id']) && !is_numeric($params['invoice_id'])) {
            throw new Exception("請求書IDは数値である必要があります");
        }
    }
    
    /**
     * 領収書データをデータベースに挿入
     * 
     * @param array $receiptData 領収書データ
     * @return int 挿入されたレコードID
     */
    private function insertReceipt($receiptData) 
    {
        $sql = "INSERT INTO receipts (
                    receipt_number, invoice_id, payment_id, issue_date,
                    amount, tax_amount, recipient_name, purpose,
                    stamp_required, stamp_amount, receipt_type, status, notes
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $receiptData['receipt_number'],
            $receiptData['invoice_id'],
            $receiptData['payment_id'],
            $receiptData['issue_date'],
            $receiptData['amount'],
            $receiptData['tax_amount'],
            $receiptData['recipient_name'],
            $receiptData['purpose'],
            $receiptData['stamp_required'] ? 1 : 0,
            $receiptData['stamp_amount'],
            $receiptData['receipt_type'],
            $receiptData['status'],
            $receiptData['notes']
        ];
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $this->db->lastInsertId();
    }
    
    /**
     * 領収書PDFファイル生成
     * 
     * @param int $receiptId 領収書ID
     * @param array $receiptData 領収書データ
     * @return string|null PDFファイルパス
     */
    private function generateReceiptPDF($receiptId, $receiptData) 
    {
        try {
            // PDFディレクトリ作成
            $pdfDir = '../uploads/receipts/' . date('Y/m');
            if (!is_dir($pdfDir)) {
                mkdir($pdfDir, 0755, true);
            }
            
            $filename = 'receipt_' . $receiptData['receipt_number'] . '.pdf';
            $filepath = $pdfDir . '/' . $filename;
            
            // 簡易PDF生成（実際の実装ではTCPDFやDomPDFを使用）
            $pdfContent = $this->generateReceiptHTML($receiptData);
            
            // HTMLをPDFに変換（実装例）
            if (function_exists('wkhtmltopdf_convert')) {
                // wkhtmltopdf拡張が利用可能な場合
                $pdfData = wkhtmltopdf_convert([
                    'html' => $pdfContent,
                    'orientation' => 'Portrait',
                    'page-size' => 'A4',
                    'margin-top' => '10mm',
                    'margin-right' => '10mm',
                    'margin-bottom' => '10mm',
                    'margin-left' => '10mm'
                ]);
                
                file_put_contents($filepath, $pdfData);
            } else {
                // 代替実装：HTMLファイルとして保存
                $htmlFilepath = str_replace('.pdf', '.html', $filepath);
                file_put_contents($htmlFilepath, $pdfContent);
                return $htmlFilepath;
            }
            
            return $filepath;
            
        } catch (Exception $e) {
            error_log("PDF生成エラー: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * 領収書HTML生成
     * 
     * @param array $receiptData 領収書データ
     * @return string HTML内容
     */
    private function generateReceiptHTML($receiptData) 
    {
        $issueDate = date('Y年m月d日', strtotime($receiptData['issue_date']));
        $amount = number_format($receiptData['amount']);
        $taxAmount = number_format($receiptData['tax_amount']);
        
        $html = '
        <!DOCTYPE html>
        <html lang="ja">
        <head>
            <meta charset="UTF-8">
            <title>領収書 - ' . htmlspecialchars($receiptData['receipt_number']) . '</title>
            <style>
                body {
                    font-family: "MS Gothic", monospace;
                    font-size: 12px;
                    margin: 20px;
                    line-height: 1.5;
                }
                .header {
                    text-align: center;
                    margin-bottom: 30px;
                }
                .title {
                    font-size: 24px;
                    font-weight: bold;
                    border-bottom: 2px solid #000;
                    display: inline-block;
                    padding: 10px 50px;
                    margin-bottom: 20px;
                }
                .receipt-info {
                    display: table;
                    width: 100%;
                    margin-bottom: 30px;
                }
                .info-row {
                    display: table-row;
                }
                .info-label, .info-value {
                    display: table-cell;
                    padding: 5px;
                    border: 1px solid #000;
                    vertical-align: middle;
                }
                .info-label {
                    background: #f0f0f0;
                    font-weight: bold;
                    width: 150px;
                }
                .amount-section {
                    text-align: center;
                    margin: 30px 0;
                    font-size: 18px;
                    font-weight: bold;
                }
                .amount-box {
                    border: 3px solid #000;
                    padding: 20px;
                    display: inline-block;
                    min-width: 300px;
                }
                .company-info {
                    text-align: right;
                    margin-top: 50px;
                    font-size: 14px;
                }
                .stamp-notice {
                    margin-top: 30px;
                    padding: 10px;
                    border: 1px solid #ccc;
                    background: #fffacd;
                    font-size: 11px;
                }
                @media print {
                    body { margin: 0; }
                    .stamp-notice { background: white; }
                }
            </style>
        </head>
        <body>
            <div class="header">
                <div class="title">領 収 書</div>
                <div>領収書番号: ' . htmlspecialchars($receiptData['receipt_number']) . '</div>
            </div>
            
            <div class="receipt-info">
                <div class="info-row">
                    <div class="info-label">宛名</div>
                    <div class="info-value">' . htmlspecialchars($receiptData['recipient_name']) . ' 様</div>
                </div>
                <div class="info-row">
                    <div class="info-label">発行日</div>
                    <div class="info-value">' . $issueDate . '</div>
                </div>
                <div class="info-row">
                    <div class="info-label">但し書き</div>
                    <div class="info-value">' . htmlspecialchars($receiptData['purpose']) . '</div>
                </div>
            </div>
            
            <div class="amount-section">
                <div class="amount-box">
                    ¥ ' . $amount . ' 円
                    <div style="font-size: 12px; margin-top: 10px;">
                        （うち消費税: ¥' . $taxAmount . '円）
                    </div>
                </div>
            </div>';
            
        // 収入印紙欄
        if ($receiptData['stamp_required']) {
            $html .= '
            <div class="stamp-notice">
                <strong>※ 収入印紙（¥' . number_format($receiptData['stamp_amount']) . '）貼付欄</strong><br>
                この領収書は5万円以上のため、収入印紙の貼付が必要です。
                <div style="border: 1px dashed #999; height: 60px; width: 80px; margin: 10px 0; display: inline-block;"></div>
            </div>';
        }
        
        $html .= '
            <div class="company-info">
                <div><strong>株式会社 Smiley</strong></div>
                <div>〒000-0000 大阪市○○区○○町○○番○○号</div>
                <div>TEL: 000-0000-0000</div>
                <div>代表者: ○○ ○○</div>
            </div>
            
            <div style="margin-top: 30px; font-size: 10px; color: #666;">
                領収書タイプ: ' . $this->getReceiptTypeLabel($receiptData['receipt_type']) . '<br>
                システム生成日時: ' . date('Y-m-d H:i:s') . '
            </div>
        </body>
        </html>';
        
        return $html;
    }
    
    /**
     * 領収書タイプラベル取得
     * 
     * @param string $type タイプ
     * @return string ラベル
     */
    private function getReceiptTypeLabel($type) 
    {
        $labels = [
            'advance' => '事前領収書',
            'payment' => '正式領収書',
            'split' => '分割領収書'
        ];
        
        return $labels[$type] ?? $type;
    }
    
    /**
     * 領収書ファイルパス更新
     * 
     * @param int $receiptId 領収書ID
     * @param string $filePath ファイルパス
     */
    private function updateReceiptFilePath($receiptId, $filePath) 
    {
        $sql = "UPDATE receipts SET file_path = ? WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$filePath, $receiptId]);
    }
    
    /**
     * 請求書ステータス更新
     * 
     * @param int $invoiceId 請求書ID
     */
    private function updateInvoiceStatus($invoiceId) 
    {
        // 請求書の支払状況確認
        $sql = "SELECT 
                    i.total_amount,
                    COALESCE(SUM(p.amount), 0) as paid_amount,
                    COUNT(r.id) as receipt_count
                FROM invoices i
                LEFT JOIN payments p ON i.id = p.invoice_id
                LEFT JOIN receipts r ON i.id = r.invoice_id
                WHERE i.id = ?
                GROUP BY i.id";
        
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([$invoiceId])->fetch();
        
        if ($result) {
            $status = 'pending';
            
            if ($result['receipt_count'] > 0) {
                if ($result['paid_amount'] >= $result['total_amount']) {
                    $status = 'paid';
                } else {
                    $status = 'partial_paid';
                }
            }
            
            // 請求書ステータス更新
            $updateSql = "UPDATE invoices SET status = ? WHERE id = ?";
            $updateStmt = $this->db->prepare($updateSql);
            $updateStmt->execute([$status, $invoiceId]);
        }
    }
    
    /**
     * 領収書検索
     * 
     * @param array $searchParams 検索パラメータ
     * @return array 検索結果
     */
    public function searchReceipts($searchParams) 
    {
        $sql = "SELECT r.*, 
                       i.invoice_number,
                       p.payment_date
                FROM receipts r
                LEFT JOIN invoices i ON r.invoice_id = i.id
                LEFT JOIN payments p ON r.payment_id = p.id
                WHERE 1=1";
        
        $params = [];
        
        if (!empty($searchParams['receipt_number'])) {
            $sql .= " AND r.receipt_number LIKE ?";
            $params[] = '%' . $searchParams['receipt_number'] . '%';
        }
        
        if (!empty($searchParams['recipient_name'])) {
            $sql .= " AND r.recipient_name LIKE ?";
            $params[] = '%' . $searchParams['recipient_name'] . '%';
        }
        
        if (!empty($searchParams['amount_min'])) {
            $sql .= " AND r.amount >= ?";
            $params[] = $searchParams['amount_min'];
        }
        
        if (!empty($searchParams['amount_max'])) {
            $sql .= " AND r.amount <= ?";
            $params[] = $searchParams['amount_max'];
        }
        
        if (!empty($searchParams['purpose'])) {
            $sql .= " AND r.purpose LIKE ?";
            $params[] = '%' . $searchParams['purpose'] . '%';
        }
        
        $sql .= " ORDER BY r.issue_date DESC, r.created_at DESC LIMIT 100";
        
        $stmt = $this->db->prepare($sql);
        $receipts = $stmt->execute($params)->fetchAll();
        
        return [
            'receipts' => $receipts,
            'count' => count($receipts)
        ];
    }
    
    /**
     * 領収書一括出力用データ取得
     * 
     * @param string $dateFrom 開始日
     * @param string $dateTo 終了日
     * @return array 出力用データ
     */
    public function getReceiptsForExport($dateFrom, $dateTo) 
    {
        $sql = "SELECT r.*, 
                       i.invoice_number,
                       p.payment_date,
                       p.payment_method
                FROM receipts r
                LEFT JOIN invoices i ON r.invoice_id = i.id
                LEFT JOIN payments p ON r.payment_id = p.id
                WHERE r.issue_date BETWEEN ? AND ?
                ORDER BY r.issue_date ASC, r.receipt_number ASC";
        
        $stmt = $this->db->prepare($sql);
        $receipts = $stmt->execute([$dateFrom, $dateTo])->fetchAll();
        
        return $receipts;
    }
}
?>
