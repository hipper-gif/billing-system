<?php
/**
 * Smiley Kitchen専用請求書PDF生成クラス
 * mPDFを使用したロゴ付きの美しい請求書PDFを生成
 *
 * @author Claude
 * @version 2.0.0 - mPDF実装版
 * @created 2025-08-26
 * @updated 2025-12-04
 */

require_once __DIR__ . '/../config/database.php';

// Composer autoloadを読み込み（存在する場合のみ）
$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}

class SmileyInvoicePDF {
    private $pdf;
    private $logoPath;
    private $companyInfo;
    private $mpdfAvailable;

    // Smiley Kitchenブランドカラー
    const BRAND_GREEN = '#4CAF50';
    const BRAND_ORANGE = '#FF9800';
    const BRAND_PINK = '#E91E63';
    const BRAND_GRAY = '#424242';
    const BRAND_LIGHT_GRAY = '#F5F5F5';

    public function __construct() {
        $this->logoPath = __DIR__ . '/../assets/images/smiley-kitchen-logo.png';
        $this->companyInfo = $this->getCompanyInfo();
        $this->mpdfAvailable = class_exists('Mpdf\Mpdf');

        if (!$this->mpdfAvailable) {
            error_log("Warning: mPDF not available. Install with: composer install");
        }
    }

    /**
     * 請求書PDF生成
     *
     * @param array $invoiceData 請求書データ
     * @return string PDFファイルパス
     */
    public function generateInvoicePDF($invoiceData) {
        $html = $this->generateInvoiceHTML($invoiceData);

        // mPDF初期化
        $this->initializeMPDF();
        $this->pdf->WriteHTML($html);

        // PDFファイル保存
        $filename = $this->generateFilename($invoiceData);
        $filepath = $this->savePDF($filename);

        return $filepath;
    }

    /**
     * mPDF初期化
     */
    private function initializeMPDF() {
        if (!$this->mpdfAvailable) {
            throw new Exception('PDF生成ライブラリ(mPDF)がインストールされていません。サーバーで "composer install" を実行してください。');
        }

        $this->pdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 20,
            'margin_bottom' => 20,
            'margin_header' => 10,
            'margin_footer' => 10,
            'default_font' => 'ipagp',  // 日本語対応フォント
            'autoScriptToLang' => true,
            'autoLangToFont' => true
        ]);

        $this->pdf->SetTitle('請求書');
        $this->pdf->SetAuthor('Smiley Kitchen');
        $this->pdf->SetCreator('Smiley配食システム');
    }

    /**
     * 請求書HTML生成
     *
     * @param array $invoice 請求書データ
     * @return string HTML
     */
    private function generateInvoiceHTML($invoice) {
        $brandGreen = self::BRAND_GREEN;
        $brandOrange = self::BRAND_ORANGE;
        $brandGray = self::BRAND_GRAY;
        $brandLightGray = self::BRAND_LIGHT_GRAY;

        // ロゴの埋め込み
        $logoHtml = '';
        if (file_exists($this->logoPath)) {
            $logoData = base64_encode(file_get_contents($this->logoPath));
            $logoHtml = '<img src="data:image/png;base64,' . $logoData . '" style="height: 40px; margin-bottom: 10px;" />';
        }

        // 請求書情報
        $invoiceNumber = htmlspecialchars($invoice['invoice_number'] ?? '');
        $issueDate = $this->formatDate($invoice['invoice_date'] ?? date('Y-m-d'));
        $dueDate = $this->formatDate($invoice['due_date'] ?? '');
        $periodStart = $this->formatDate($invoice['period_start'] ?? '');
        $periodEnd = $this->formatDate($invoice['period_end'] ?? '');

        // 請求先情報
        $billingCompany = htmlspecialchars($invoice['company_name'] ?? '');
        $billingDepartment = htmlspecialchars($invoice['department'] ?? '');

        // 金額情報（税込み価格）
        $totalAmount = number_format($invoice['total_amount'] ?? 0);

        // 請求書タイプ
        $invoiceType = $this->getInvoiceTypeLabel($invoice['invoice_type'] ?? 'company');

        // 明細データ
        $detailsHtml = '';
        if (!empty($invoice['details'])) {
            $rowNum = 0;
            foreach ($invoice['details'] as $detail) {
                $rowNum++;
                $rowBg = ($rowNum % 2 == 0) ? $brandLightGray : '#FFFFFF';
                $orderDate = $this->formatDate($detail['order_date'] ?? '');
                $productName = htmlspecialchars($detail['product_name'] ?? '');
                $quantity = htmlspecialchars($detail['quantity'] ?? 0);
                $unitPrice = number_format($detail['unit_price'] ?? 0);
                $amount = number_format($detail['amount'] ?? 0);

                $detailsHtml .= "
                <tr style=\"background-color: {$rowBg};\">
                    <td style=\"padding: 8px; border: 1px solid #ddd; text-align: center;\">{$orderDate}</td>
                    <td style=\"padding: 8px; border: 1px solid #ddd;\">{$productName}</td>
                    <td style=\"padding: 8px; border: 1px solid #ddd; text-align: center;\">{$quantity}</td>
                    <td style=\"padding: 8px; border: 1px solid #ddd; text-align: right;\">¥{$unitPrice}</td>
                    <td style=\"padding: 8px; border: 1px solid #ddd; text-align: right;\">¥{$amount}</td>
                </tr>";
            }
        } else {
            $detailsHtml = "
            <tr>
                <td colspan=\"5\" style=\"padding: 20px; text-align: center; border: 1px solid #ddd; color: #999;\">
                    明細データがありません
                </td>
            </tr>";
        }

        // 備考
        $notes = htmlspecialchars($invoice['notes'] ?? '');
        $notesHtml = '';
        if (!empty($notes)) {
            $notesHtml = "
            <div style=\"margin-top: 25px; padding: 15px; background-color: {$brandLightGray}; border-left: 4px solid {$brandGreen};\">
                <div style=\"font-weight: bold; font-size: 11pt; margin-bottom: 8px; color: {$brandGray};\">備考</div>
                <div style=\"font-size: 10pt; line-height: 1.6;\">{$notes}</div>
            </div>";
        }

        $html = "
<!DOCTYPE html>
<html lang=\"ja\">
<head>
    <meta charset=\"UTF-8\">
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            color: {$brandGray};
            font-size: 10pt;
            line-height: 1.4;
        }
        .header {
            margin-bottom: 25px;
            border-bottom: 3px solid {$brandGreen};
            padding-bottom: 15px;
        }
        .invoice-title {
            font-size: 28pt;
            font-weight: bold;
            color: {$brandGreen};
            letter-spacing: 2px;
        }
        .invoice-number {
            font-size: 11pt;
            color: {$brandGray};
            margin-top: 8px;
        }
        .section-title {
            font-size: 11pt;
            font-weight: bold;
            color: {$brandGray};
            margin-bottom: 8px;
            padding-bottom: 3px;
            border-bottom: 1px solid #ddd;
        }
        .info-box {
            background-color: {$brandLightGray};
            padding: 12px;
            margin: 15px 0;
            border-left: 4px solid {$brandGreen};
        }
        .info-label {
            font-weight: bold;
            color: {$brandGray};
            display: inline-block;
            min-width: 80px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        th {
            background-color: {$brandGreen};
            color: white;
            padding: 12px 8px;
            text-align: left;
            font-weight: bold;
            font-size: 10pt;
        }
        td {
            padding: 10px 8px;
        }
        .total-section {
            margin-top: 25px;
        }
        .total-box {
            background-color: {$brandLightGray};
            border: 2px solid {$brandGreen};
            padding: 20px;
            margin-top: 10px;
        }
        .total-label {
            font-size: 14pt;
            font-weight: bold;
            color: {$brandGray};
            display: inline-block;
            margin-right: 30px;
        }
        .total-amount {
            font-size: 20pt;
            font-weight: bold;
            color: {$brandGreen};
            display: inline-block;
            min-width: 180px;
            text-align: right;
        }
        .payment-box {
            margin-top: 25px;
            padding: 15px;
            background-color: #fafafa;
            border: 1px solid #ddd;
        }
        .footer {
            margin-top: 40px;
            padding-top: 15px;
            border-top: 1px solid #ccc;
            font-size: 9pt;
            color: #888;
            text-align: center;
        }
        .company-info {
            font-size: 9.5pt;
            line-height: 1.6;
        }
        .billing-info {
            font-size: 10.5pt;
            line-height: 1.6;
        }
        .billing-company {
            font-size: 13pt;
            font-weight: bold;
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <!-- ヘッダー -->
    <div class=\"header\">
        <table style=\"border: none; margin: 0;\">
            <tr>
                <td style=\"width: 40%; border: none; vertical-align: middle;\">
                    {$logoHtml}
                </td>
                <td style=\"width: 60%; text-align: right; border: none; vertical-align: middle;\">
                    <div class=\"invoice-title\">請求書</div>
                    <div class=\"invoice-number\">請求書番号: {$invoiceNumber}</div>
                </td>
            </tr>
        </table>
    </div>

    <!-- 発行者・請求先情報 -->
    <table style=\"border: none; margin-bottom: 25px;\">
        <tr>
            <td style=\"width: 50%; vertical-align: top; border: none; padding-right: 20px;\">
                <div class=\"section-title\">発行者</div>
                <div class=\"company-info\">
                    <div style=\"font-weight: bold; margin-bottom: 5px;\">{$this->companyInfo['company_name']}</div>
                    <div>{$this->companyInfo['address']}</div>
                    <div>TEL: {$this->companyInfo['phone']}</div>
                    <div>E-mail: {$this->companyInfo['email']}</div>
                </div>
            </td>
            <td style=\"width: 50%; vertical-align: top; border: none; padding-left: 20px;\">
                <div class=\"section-title\">請求先</div>
                <div class=\"billing-info\">
                    <div class=\"billing-company\">{$billingCompany} 御中</div>
                    " . (!empty($billingDepartment) ? "<div>{$billingDepartment}</div>" : "") . "
                </div>
            </td>
        </tr>
    </table>

    <!-- 請求書情報 -->
    <div class=\"info-box\">
        <table style=\"border: none; margin: 0;\">
            <tr>
                <td style=\"border: none; width: 33%;\">
                    <span class=\"info-label\">発行日:</span> {$issueDate}
                </td>
                <td style=\"border: none; width: 34%;\">
                    <span class=\"info-label\">お支払期限:</span> <strong>{$dueDate}</strong>
                </td>
                <td style=\"border: none; width: 33%;\">
                    <span class=\"info-label\">請求期間:</span> {$periodStart} ～ {$periodEnd}
                </td>
            </tr>
        </table>
    </div>

    <!-- 明細テーブル -->
    <div class=\"section-title\" style=\"margin-top: 25px;\">ご請求明細</div>
    <table>
        <thead>
            <tr>
                <th style=\"width: 15%; text-align: center;\">配達日</th>
                <th style=\"width: 40%;\">商品名</th>
                <th style=\"width: 10%; text-align: center;\">数量</th>
                <th style=\"width: 17%; text-align: right;\">単価</th>
                <th style=\"width: 18%; text-align: right;\">金額</th>
            </tr>
        </thead>
        <tbody>
            {$detailsHtml}
        </tbody>
    </table>

    <!-- 合計金額（税込） -->
    <div class=\"total-section\">
        <table style=\"border: none; margin: 0;\">
            <tr>
                <td style=\"border: none; width: 60%;\"></td>
                <td style=\"border: none; width: 40%;\">
                    <div class=\"total-box\">
                        <table style=\"border: none; margin: 0; width: 100%;\">
                            <tr>
                                <td style=\"border: none; text-align: left; padding: 5px 0;\">
                                    <span class=\"total-label\">ご請求金額（税込）</span>
                                </td>
                                <td style=\"border: none; text-align: right; padding: 5px 0;\">
                                    <span class=\"total-amount\">¥{$totalAmount}</span>
                                </td>
                            </tr>
                        </table>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <!-- 備考 -->
    {$notesHtml}

    <!-- お支払い情報 -->
    <div class=\"payment-box\">
        <div style=\"font-weight: bold; font-size: 11pt; margin-bottom: 10px; color: {$brandGray};\">お支払いについて</div>
        <div style=\"font-size: 9.5pt; line-height: 1.7;\">
            ・お支払期限: {$dueDate}<br>
            ・お支払い方法の詳細については、別途ご連絡いたします。<br>
            ・ご不明な点がございましたら、上記連絡先までお気軽にお問い合わせください。
        </div>
    </div>

    <!-- フッター -->
    <div class=\"footer\">
        <div>
            {$this->companyInfo['company_name']} - 配食サービス
        </div>
    </div>
</body>
</html>";

        return $html;
    }

    /**
     * ファイル名生成
     */
    private function generateFilename($invoice) {
        $date = date('Ymd');
        $invoiceNumber = preg_replace('/[^a-zA-Z0-9-_]/', '_', $invoice['invoice_number'] ?? 'INV');
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
     * PDF直接出力（ブラウザ表示用）
     */
    public function outputInvoicePDF($invoiceData, $filename = null) {
        $html = $this->generateInvoiceHTML($invoiceData);

        $this->initializeMPDF();
        $this->pdf->WriteHTML($html);

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
        $html = $this->generateInvoiceHTML($invoiceData);

        $this->initializeMPDF();
        $this->pdf->WriteHTML($html);

        if (!$filename) {
            $filename = $this->generateFilename($invoiceData);
        }

        // ダウンロード
        $this->pdf->Output($filename, 'D');
    }

    /**
     * 会社情報取得
     */
    private function getCompanyInfo() {
        // デフォルト値
        $defaultInfo = [
            'company_name' => 'Smiley Kitchen',
            'address' => '〒000-0000 東京都○○区○○1-2-3',
            'phone' => '03-0000-0000',
            'email' => 'info@smiley-kitchen.com'
        ];

        // データベースから取得を試みる
        try {
            if (!class_exists('Database')) {
                return $defaultInfo;
            }

            $db = Database::getInstance();
            if (!$db) {
                return $defaultInfo;
            }

            $settings = $db->fetch("SELECT * FROM system_settings WHERE id = 1");

            if ($settings) {
                return [
                    'company_name' => $settings['company_name'] ?? $defaultInfo['company_name'],
                    'address' => $settings['company_address'] ?? $defaultInfo['address'],
                    'phone' => $settings['company_phone'] ?? $defaultInfo['phone'],
                    'email' => $settings['company_email'] ?? $defaultInfo['email']
                ];
            }
        } catch (Exception $e) {
            // エラー時はデフォルト値を返す
            error_log("SmileyInvoicePDF: Failed to get company info from database: " . $e->getMessage());
        }

        return $defaultInfo;
    }

    /**
     * 請求書タイプラベル取得
     */
    private function getInvoiceTypeLabel($type) {
        $labels = [
            'company' => '企業一括請求',
            'company_bulk' => '企業一括請求',
            'department' => '部署別一括請求',
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

        try {
            $timestamp = strtotime($date);
            if ($timestamp === false) return $date;
            return date('Y年m月d日', $timestamp);
        } catch (Exception $e) {
            return $date;
        }
    }
}
?>
