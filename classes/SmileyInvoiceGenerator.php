<?php
/**
 * SmileyInvoicePDF クラスのロゴパス修正
 * 
 * classes/SmileyInvoiceGenerator.php の該当部分を以下に修正
 */

// 修正前（26行目付近）
// $this->logoPath = __DIR__ . '/../assets/images/smiley-kitchen-logo.png';

// 修正後
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
        // ロゴパス修正 - 正しいパスに設定
        $this->logoPath = $_SERVER['DOCUMENT_ROOT'] . '/Smiley/meal-delivery/billing-system/assets/images/smiley-kitchen-logo.png';
        
        // ローカル開発環境用のフォールバック
        if (!file_exists($this->logoPath)) {
            $this->logoPath = __DIR__ . '/../assets/images/smiley-kitchen-logo.png';
        }
        
        $this->companyInfo = $this->getCompanyInfo();
    }
    
    /**
     * 会社情報取得（Smiley Kitchen仕様に更新）
     */
    private function getCompanyInfo() {
        return [
            'company_name' => 'Smiley Kitchen',
            'address' => '〒000-0000 東京都○○区○○1-2-3',
            'phone' => '03-0000-0000',
            'email' => 'info@smiley-kitchen.com',
            'website' => 'https://smiley-kitchen.com'
        ];
    }
    
    /**
     * ヘッダー追加（ロゴ含む・改良版）
     */
    private function addHeader($invoice) {
        // Smiley Kitchenロゴ配置
        if (file_exists($this->logoPath)) {
            $this->pdf->Image($this->logoPath, 20, 15, 50, 0, 'PNG');
        } else {
            // ロゴがない場合のフォールバック
            $this->pdf->SetFont('DejaVu', 'B', 16);
            $this->pdf->SetTextColor(...self::BRAND_GREEN);
            $this->pdf->SetXY(20, 20);
            $this->pdf->Cell(0, 10, 'Smiley Kitchen', 0, 1, 'L');
        }
        
        // 請求書タイトル
        $this->pdf->SetFont('DejaVu', 'B', 24);
        $this->pdf->SetTextColor(...self::BRAND_GREEN);
        $this->pdf->SetXY(80, 20);
        $this->pdf->Cell(0, 10, '請求書', 0, 1, 'L');
        
        // 請求書番号
        $this->pdf->SetFont('DejaVu', 'B', 14);
        $this->pdf->SetTextColor(...self::BRAND_GRAY);
        $this->pdf->SetXY(80, 35);
        $this->pdf->Cell(0, 8, 'Invoice No: ' . $invoice['invoice_number'], 0, 1, 'L');
        
        // 発行日・支払期限
        $this->pdf->SetFont('DejaVu', '', 11);
        $this->pdf->SetXY(80, 50);
        $this->pdf->Cell(60, 6, '発行日: ' . $this->formatDate($invoice['issue_date']), 0, 0, 'L');
        $this->pdf->SetXY(140, 50);
        $this->pdf->Cell(0, 6, '支払期限: ' . $this->formatDate($invoice['due_date']), 0, 1, 'L');
        
        // 区切り線
        $this->pdf->SetDrawColor(...self::BRAND_GREEN);
        $this->pdf->SetLineWidth(1);
        $this->pdf->Line(20, 65, 190, 65);
    }
}
