<?php
/**
 * Smiley Kitchen専用請求書PDF生成クラス
 * ロゴ付きの美しい請求書PDFを生成
 * 
 * @author Claude
 * @version 1.0.0
 * @created 2025-08-26
 */

require_once __DIR__ . '/../config/database.php';

class SmileyInvoicePDF {
    private $pdf;
    private $logoPath;
    private $companyInfo;
    
    // Smiley Kitchenブランドカラー
    const BRAND_GREEN = [76, 175, 80];      // #4CAF50
    const BRAND_ORANGE = [255, 152, 0];     // #FF9800
    const BRAND_PINK = [233, 30, 99];       // #E91E63
    const BRAND_GRAY = [66, 66, 66];        // #424242
    const BRAND_LIGHT_GRAY = [245, 245, 245]; // #F5F5F5
    
    public function __construct() {
        // TCPDFを使用（Composerでインストール必要）
        // 代替案: mPDFやFPDFも利用可能
        $this->logoPath = __DIR__ . '/../assets/images/smiley-kitchen-logo.png';
        $this->companyInfo = $this->getCompanyInfo();
    }
    
    /**
     * 請求書PDF生成
     * 
     * @param array $invoiceData 請求書データ
     * @return string PDFファイルパス
     */
    public function generateInvoicePDF($invoiceData) {
        $this->initializePDF();
        $this->addInvoicePage($invoiceData);
        
        // PDFファイル保存
        $filename = $this->generateFilename($invoiceData);
        $filepath = $this->savePDF($filename);
        
        return $filepath;
    }
    
    /**
     * PDF初期化
     */
    private function initializePDF() {
        // SimplePDFクラス（軽量版）を使用
        $this->pdf = new SimplePDF();
        $this->pdf->SetMargins(20, 25, 20);
        $this->pdf->SetAutoPageBreak(true, 25);
        $this->pdf->AddPage();
        
        // 日本語フォント設定
        $this->pdf->SetFont('DejaVu', '', 12);
    }
    
    /**
     * 請求書ページ追加
     * 
     * @param array $invoice 請求書データ
     */
    private function addInvoicePage($invoice) {
        $this->addHeader($invoice);
        $this->addCompanyInfo();
        $this->addBillingInfo($invoice);
        $this->addInvoiceInfo($invoice);
        $this->addInvoiceDetails($invoice);
        $this->addTotalSection($invoice);
        $this->addFooter($invoice);
    }
    
    /**
     * ヘッダー追加（ロゴ含む）
     */
    private function addHeader($invoice) {
        // Smiley Kitchenロゴ配置
        if (file_exists($this->logoPath)) {
            $this->pdf->Image($this->logoPath, 20, 15, 40, 0, 'PNG');
        }
        
        // 請求書タイトル
        $this->pdf->SetFont('DejaVu', 'B', 24);
        $this->pdf->SetTextColor(...self::BRAND_GREEN);
        $this->pdf->SetXY(70, 20);
        $this->pdf->Cell(0, 10, '請求書', 0, 1, 'L');
        
        // 請求書番号
        $this->pdf->SetFont('DejaVu', 'B', 14);
        $this->pdf->SetTextColor(...self::BRAND_GRAY);
        $this->pdf->SetXY(70, 35);
        $this->pdf->Cell(0, 8, 'Invoice No: ' . $invoice['invoice_number'], 0, 1, 'L');
        
        // 発行日・支払期限
        $this->pdf->SetFont('DejaVu', '', 11);
        $this->pdf->SetXY(70, 45);
        $this->pdf->Cell(60, 6, '発行日: ' . $this->formatDate($invoice['issue_date']), 0, 0, 'L');
        $this->pdf->SetXY(130, 45);
        $this->pdf->Cell(0, 6, '支払期限: ' . $this->formatDate($invoice['due_date']), 0, 1, 'L');
        
        // 区切り線
        $this->pdf->SetDrawColor(...self::BRAND_GREEN);
        $this->pdf->SetLineWidth(1);
        $this->pdf->Line(20, 60, 190, 60);
    }
    
    /**
     * 会社情報追加
     */
    private function addCompanyInfo() {
        $y = 70;
        
        // 発行者情報
        $this->pdf->SetFont('DejaVu', 'B', 12);
        $this->pdf->SetTextColor(...self::BRAND_GRAY);
        $this->pdf->SetXY(20, $y);
        $this->pdf->Cell(0, 8, '発行者', 0, 1, 'L');
        
        $this->pdf->SetFont('DejaVu', '', 10);
        $this->pdf->SetXY(20, $y + 10);
        $this->pdf->Cell(0, 5, $this->companyInfo['company_name'], 0, 1, 'L');
        $this->pdf->SetXY(20, $y + 16);
        $this->pdf->Cell(0, 5, $this->companyInfo['address'], 0, 1, 'L');
        $this->pdf->SetXY(20, $y + 22);
        $this->pdf->Cell(0, 5, 'TEL: ' . $this->companyInfo['phone'], 0, 1, 'L');
        $this->pdf->SetXY(20, $y + 28);
        $this->pdf->Cell(0, 5, 'Email: ' . $this->companyInfo['email'], 0, 1, 'L');
    }
    
    /**
     * 請求先情報追加
     */
    private function addBillingInfo($invoice) {
        $y = 70;
        
        // 請求先情報
        $this->pdf->SetFont('DejaVu', 'B', 12);
        $this->pdf->SetTextColor(...self::BRAND_GRAY);
        $this->pdf->SetXY(110, $y);
        $this->pdf->Cell(0, 8, '請求先', 0, 1, 'L');
        
        $this->pdf->SetFont('DejaVu', '', 10);
        $this->pdf->SetXY(110, $y + 10);
        $this->pdf->Cell(0, 5, $invoice['billing_company_name'], 0, 1, 'L');
        
        if (!empty($invoice['billing_address'])) {
            $this->pdf->SetXY(110, $y + 16);
            $this->pdf->Cell(0, 5, $invoice['billing_address'], 0, 1, 'L');
        }
        
        if (!empty($invoice['billing_contact_person'])) {
            $this->pdf->SetXY(110, $y + 22);
            $this->pdf->Cell(0, 5, '担当: ' . $invoice['billing_contact_person'], 0, 1, 'L');
        }
        
        if (!empty($invoice['billing_email'])) {
            $this->pdf->SetXY(110, $y + 28);
            $this->pdf->Cell(0, 5, 'Email: ' . $invoice['billing_email'], 0, 1, 'L');
        }
    }
    
    /**
     * 請求書情報追加
     */
    private function addInvoiceInfo($invoice) {
        $y = 115;
        
        // 背景色付きヘッダー
        $this->pdf->SetFillColor(...self::BRAND_LIGHT_GRAY);
        $this->pdf->Rect(20, $y, 170, 20, 'F');
        
        $this->pdf->SetFont('DejaVu', 'B', 11);
        $this->pdf->SetTextColor(...self::BRAND_GRAY);
        
        // 請求期間
        $this->pdf->SetXY(25, $y + 5);
        $this->pdf->Cell(80, 6, '請求期間: ' . $this->formatDate($invoice['period_start']) . ' ～ ' . $this->formatDate($invoice['period_end']), 0, 0, 'L');
        
        // 請求タイプ
        $typeLabel = $this->getInvoiceTypeLabel($invoice['invoice_type']);
        $this->pdf->SetXY(110, $y + 5);
        $this->pdf->Cell(0, 6, '請求タイプ: ' . $typeLabel, 0, 1, 'L');
        
        // 注文件数・数量
        $this->pdf->SetXY(25, $y + 12);
        $this->pdf->Cell(80, 6, '注文件数: ' . ($invoice['order_count'] ?? 0) . '件', 0, 0, 'L');
        $this->pdf->SetXY(110, $y + 12);
        $this->pdf->Cell(0, 6, '総数量: ' . ($invoice['total_quantity'] ?? 0) . '食', 0, 1, 'L');
    }
    
    /**
     * 請求書明細追加
     */
    private function addInvoiceDetails($invoice) {
        $y = 145;
        
        // 明細ヘッダー
        $this->pdf->SetFont('DejaVu', 'B', 10);
        $this->pdf->SetTextColor(255, 255, 255);
        $this->pdf->SetFillColor(...self::BRAND_GREEN);
        
        $this->pdf->SetXY(20, $y);
        $this->pdf->Cell(25, 8, '配達日', 1, 0, 'C', true);
        $this->pdf->Cell(40, 8, '利用者', 1, 0, 'C', true);
        $this->pdf->Cell(60, 8, '商品名', 1, 0, 'C', true);
        $this->pdf->Cell(15, 8, '数量', 1, 0, 'C', true);
        $this->pdf->Cell(30, 8, '金額', 1, 1, 'C', true);
        
        // 明細データ
        $this->pdf->SetFont('DejaVu', '', 9);
        $this->pdf->SetTextColor(...self::BRAND_GRAY);
        $this->pdf->SetFillColor(255, 255, 255);
        
        $y += 8;
        $rowHeight = 6;
        $maxRows = 20; // 1ページあたり最大行数
        $currentRow = 0;
        
        if (!empty($invoice['details'])) {
            foreach ($invoice['details'] as $detail) {
                if ($currentRow >= $maxRows) {
                    // 新しいページ
                    $this->pdf->AddPage();
                    $y = 30;
                    $currentRow = 0;
                    
                    // ヘッダーを再描画
                    $this->addDetailHeader($y - 8);
                }
                
                $fillColor = ($currentRow % 2 == 0) ? [255, 255, 255] : self::BRAND_LIGHT_GRAY;
                $this->pdf->SetFillColor(...$fillColor);
                
                $this->pdf->SetXY(20, $y);
                $this->pdf->Cell(25, $rowHeight, $this->formatDate($detail['delivery_date']), 1, 0, 'C', true);
                $this->pdf->Cell(40, $rowHeight, $this->truncateText($detail['user_name'] ?? '-', 15), 1, 0, 'L', true);
                $this->pdf->Cell(60, $rowHeight, $this->truncateText($detail['product_name'], 25), 1, 0, 'L', true);
                $this->pdf->Cell(15, $rowHeight, $detail['quantity'], 1, 0, 'C', true);
                $this->pdf->Cell(30, $rowHeight, '¥' . number_format($detail['total_amount']), 1, 1, 'R', true);
                
                $y += $rowHeight;
                $currentRow++;
            }
        } else {
            // 明細がない場合
            $this->pdf->SetXY(20, $y);
            $this->pdf->Cell(170, $rowHeight, '明細データがありません', 1, 1, 'C');
        }
        
        return $y;
    }
    
    /**
     * 合計セクション追加
     */
    private function addTotalSection($invoice) {
        // 現在のY位置を取得（明細の下）
        $y = $this->pdf->GetY() + 10;
        
        // 合計金額エリア
        $this->pdf->SetFont('DejaVu', '', 11);
        $this->pdf->SetTextColor(...self::BRAND_GRAY);
        
        // 小計
        $this->pdf->SetXY(140, $y);
        $this->pdf->Cell(25, 6, '小計:', 0, 0, 'R');
        $this->pdf->Cell(25, 6, '¥' . number_format($invoice['subtotal']), 0, 1, 'R');
        
        // 消費税
        $this->pdf->SetXY(140, $y + 8);
        $this->pdf->Cell(25, 6, '消費税:', 0, 0, 'R');
        $this->pdf->Cell(25, 6, '¥' . number_format($invoice['tax_amount']), 0, 1, 'R');
        
        // 区切り線
        $this->pdf->SetDrawColor(...self::BRAND_GREEN);
        $this->pdf->Line(140, $y + 18, 190, $y + 18);
        
        // 合計金額
        $this->pdf->SetFont('DejaVu', 'B', 14);
        $this->pdf->SetTextColor(...self::BRAND_GREEN);
        $this->pdf->SetXY(140, $y + 22);
        $this->pdf->Cell(25, 8, '合計金額:', 0, 0, 'R');
        $this->pdf->Cell(25, 8, '¥' . number_format($invoice['total_amount']), 0, 1, 'R');
    }
    
    /**
     * フッター追加
     */
    private function addFooter($invoice) {
        $y = 250; // 固定位置
        
        // お支払い方法
        $this->pdf->SetFont('DejaVu', 'B', 10);
        $this->pdf->SetTextColor(...self::BRAND_GRAY);
        $this->pdf->SetXY(20, $y);
        $this->pdf->Cell(0, 6, 'お支払い方法', 0, 1, 'L');
        
        $this->pdf->SetFont('DejaVu', '', 9);
        $this->pdf->SetXY(20, $y + 8);
        $this->pdf->Cell(0, 5, 'お支払期限: ' . $this->formatDate($invoice['due_date']), 0, 1, 'L');
        $this->pdf->SetXY(20, $y + 14);
        $this->pdf->Cell(0, 5, 'お支払い方法の詳細については、別途ご連絡いたします。', 0, 1, 'L');
        
        // 備考
        if (!empty($invoice['notes'])) {
            $this->pdf->SetXY(20, $y + 22);
            $this->pdf->Cell(0, 5, '備考: ' . $invoice['notes'], 0, 1, 'L');
        }
        
        // フッターライン
        $this->pdf->SetDrawColor(...self::BRAND_ORANGE);
        $this->pdf->SetLineWidth(0.5);
        $this->pdf->Line(20, 280, 190, 280);
        
        // フッターテキスト
        $this->pdf->SetFont('DejaVu', '', 8);
        $this->pdf->SetTextColor(...self::BRAND_GRAY);
        $this->pdf->SetXY(20, 285);
        $this->pdf->Cell(0, 4, 'Smiley Kitchen - 美味しい配食サービス', 0, 0, 'C');
    }
    
    /**
     * 明細ヘッダー再描画
     */
    private function addDetailHeader($y) {
        $this->pdf->SetFont('DejaVu', 'B', 10);
        $this->pdf->SetTextColor(255, 255, 255);
        $this->pdf->SetFillColor(...self::BRAND_GREEN);
        
        $this->pdf->SetXY(20, $y);
        $this->pdf->Cell(25, 8, '配達日', 1, 0, 'C', true);
        $this->pdf->Cell(40, 8, '利用者', 1, 0, 'C', true);
        $this->pdf->Cell(60, 8, '商品名', 1, 0, 'C', true);
        $this->pdf->Cell(15, 8, '数量', 1, 0, 'C', true);
        $this->pdf->Cell(30, 8, '金額', 1, 1, 'C', true);
    }
    
    /**
     * ファイル名生成
     */
    private function generateFilename($invoice) {
        $date = date('Ymd');
        $invoiceNumber = preg_replace('/[^a-zA-Z0-9-_]/', '_', $invoice['invoice_number']);
        return "invoice_{$invoiceNumber}_{$date}.pdf";
    }
    
    /**
     * PDF保存
     */
    private function savePDF($filename) {
        $directory = __DIR__ . '/../storage/invoices/';
        
        // ディレクトリが存在しない場合は作成
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        
        $filepath = $directory . $filename;
        $this->pdf->Output($filepath, 'F');
        
        return $filepath;
    }
    
    /**
     * 会社情報取得
     */
    private function getCompanyInfo() {
        return [
            'company_name' => 'Smiley Kitchen',
            'address' => '〒000-0000 東京都○○区○○1-2-3',
            'phone' => '03-0000-0000',
            'email' => 'info@smiley-kitchen.com'
        ];
    }
    
    /**
     * 請求書タイプラベル取得
     */
    private function getInvoiceTypeLabel($type) {
        $labels = [
            'company_bulk' => '企業一括請求',
            'department_bulk' => '部署別一括請求',
            'individual' => '個人請求',
            'mixed' => '混合請求'
        ];
        return $labels[$type] ?? $type;
    }
    
    /**
     * 日付フォーマット
     */
    private function formatDate($date) {
        if (empty($date)) return '-';
        return date('Y年m月d日', strtotime($date));
    }
    
    /**
     * テキスト切り詰め
     */
    private function truncateText($text, $maxLength) {
        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }
        return mb_substr($text, 0, $maxLength - 2) . '...';
    }
    
    /**
     * PDF直接出力（ブラウザ表示用）
     */
    public function outputInvoicePDF($invoiceData, $filename = null) {
        $this->initializePDF();
        $this->addInvoicePage($invoiceData);
        
        if (!$filename) {
            $filename = $this->generateFilename($invoiceData);
        }
        
        // ブラウザに出力
        $this->pdf->Output($filename, 'I');
    }
    
    /**
     * PDF ダウンロード用
     */
    public function downloadInvoicePDF($invoiceData, $filename = null) {
        $this->initializePDF();
        $this->addInvoicePage($invoiceData);
        
        if (!$filename) {
            $filename = $this->generateFilename($invoiceData);
        }
        
        // ダウンロード
        $this->pdf->Output($filename, 'D');
    }
}

/**
 * 軽量PDF生成クラス（TCPDF/mPDFの代替）
 * 基本的なPDF生成機能を提供
 */
class SimplePDF {
    private $content;
    private $x;
    private $y;
    private $margins;
    private $font;
    private $fontSize;
    private $textColor;
    private $fillColor;
    private $drawColor;
    private $lineWidth;
    
    public function __construct() {
        $this->content = [];
        $this->x = 0;
        $this->y = 0;
        $this->margins = [20, 20, 20, 20]; // left, top, right, bottom
        $this->font = 'Arial';
        $this->fontSize = 12;
        $this->textColor = [0, 0, 0];
        $this->fillColor = [255, 255, 255];
        $this->drawColor = [0, 0, 0];
        $this->lineWidth = 1;
    }
    
    public function AddPage() {
        $this->content[] = ['type' => 'page'];
        $this->x = $this->margins[0];
        $this->y = $this->margins[1];
    }
    
    public function SetMargins($left, $top, $right = null) {
        $this->margins[0] = $left;
        $this->margins[1] = $top;
        if ($right !== null) {
            $this->margins[2] = $right;
        }
    }
    
    public function SetAutoPageBreak($auto, $margin = 0) {
        // 簡易実装
        $this->margins[3] = $margin;
    }
    
    public function SetFont($family, $style = '', $size = 0) {
        $this->font = $family;
        if ($size > 0) {
            $this->fontSize = $size;
        }
    }
    
    public function SetTextColor($r, $g = null, $b = null) {
        if ($g === null && $b === null) {
            $this->textColor = [$r, $r, $r]; // グレースケール
        } else {
            $this->textColor = [$r, $g, $b];
        }
    }
    
    public function SetFillColor($r, $g = null, $b = null) {
        if ($g === null && $b === null) {
            $this->fillColor = [$r, $r, $r];
        } else {
            $this->fillColor = [$r, $g, $b];
        }
    }
    
    public function SetDrawColor($r, $g = null, $b = null) {
        if ($g === null && $b === null) {
            $this->drawColor = [$r, $r, $r];
        } else {
            $this->drawColor = [$r, $g, $b];
        }
    }
    
    public function SetLineWidth($width) {
        $this->lineWidth = $width;
    }
    
    public function SetXY($x, $y) {
        $this->x = $x;
        $this->y = $y;
    }
    
    public function GetY() {
        return $this->y;
    }
    
    public function Cell($w, $h = 0, $txt = '', $border = 0, $ln = 0, $align = '', $fill = false) {
        $this->content[] = [
            'type' => 'cell',
            'x' => $this->x,
            'y' => $this->y,
            'w' => $w,
            'h' => $h,
            'text' => $txt,
            'border' => $border,
            'align' => $align,
            'fill' => $fill,
            'font' => $this->font,
            'fontSize' => $this->fontSize,
            'textColor' => $this->textColor,
            'fillColor' => $this->fillColor
        ];
        
        if ($ln == 1) {
            $this->y += $h;
            $this->x = $this->margins[0];
        } else {
            $this->x += $w;
        }
    }
    
    public function Image($file, $x = null, $y = null, $w = 0, $h = 0, $type = '') {
        if ($x === null) $x = $this->x;
        if ($y === null) $y = $this->y;
        
        $this->content[] = [
            'type' => 'image',
            'file' => $file,
            'x' => $x,
            'y' => $y,
            'w' => $w,
            'h' => $h
        ];
    }
    
    public function Line($x1, $y1, $x2, $y2) {
        $this->content[] = [
            'type' => 'line',
            'x1' => $x1,
            'y1' => $y1,
            'x2' => $x2,
            'y2' => $y2,
            'color' => $this->drawColor,
            'width' => $this->lineWidth
        ];
    }
    
    public function Rect($x, $y, $w, $h, $style = '') {
        $this->content[] = [
            'type' => 'rect',
            'x' => $x,
            'y' => $y,
            'w' => $w,
            'h' => $h,
            'style' => $style,
            'fillColor' => $this->fillColor,
            'drawColor' => $this->drawColor
        ];
    }
    
    /**
     * PDF出力
     * 実際の実装では、TCPDF、mPDF、またはFPDFを使用
     */
    public function Output($name = '', $dest = '') {
        switch ($dest) {
            case 'I': // ブラウザに出力
                header('Content-Type: application/pdf');
                header('Content-Disposition: inline; filename="' . $name . '"');
                break;
            case 'D': // ダウンロード
                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; filename="' . $name . '"');
                break;
            case 'F': // ファイル保存
                // 実際にはPDFライブラリを使用してファイル保存
                file_put_contents($name, $this->generatePDFContent());
                return;
        }
        
        // 実際にはPDFライブラリを使用してPDF生成
        echo $this->generatePDFContent();
    }
    
    /**
     * PDF コンテンツ生成（簡易版）
     * 実際の実装では適切なPDFライブラリを使用
     */
    private function generatePDFContent() {
        // 実際の実装では、TCPDFやmPDFを使用してPDFを生成
        // ここでは簡易的なPDF構造を返す
        
        $pdf_content = "%PDF-1.4\n";
        $pdf_content .= "1 0 obj\n<<\n/Type /Catalog\n/Pages 2 0 R\n>>\nendobj\n";
        
        // 実際のPDF生成処理...
        // この部分は、TCPDF、mPDF、FPDFなどのライブラリで実装
        
        return $pdf_content;
    }
}
?>
