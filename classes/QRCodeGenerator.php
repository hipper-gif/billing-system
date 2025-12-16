<?php
/**
 * QRCodeGenerator - QRコード生成クラス
 * 
 * 企業登録用のQRコードを生成し、PDFとして出力する機能を提供
 * 
 * @package Smiley配食事業システム
 * @version 1.0
 */

require_once __DIR__ . '/../config/database.php';

class QRCodeGenerator {
    private $db;
    
    /**
     * コンストラクタ
     */
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * 企業登録用トークンとQRコードを生成
     * 
     * @param int $companyId 企業ID
     * @param int|null $expiryDays 有効期限（日数）nullの場合は無期限
     * @return array ['token', 'signup_url', 'qr_code_path', 'expires_at']
     * @throws Exception
     */
    public function generateCompanySignupQR($companyId, $expiryDays = null) {
        // トークン生成（64文字のランダム文字列）
        $token = bin2hex(random_bytes(32));
        $expires = $expiryDays ? date('Y-m-d H:i:s', strtotime("+{$expiryDays} days")) : null;
        
        // 登録URL生成
        $baseUrl = $this->getBaseUrl();
        $signupUrl = "{$baseUrl}/pages/join.php?company={$token}";
        
        // QRコード画像生成
        $qrPath = $this->generateQRImage($signupUrl, $token);
        
        // データベースに保存
        $sql = "INSERT INTO company_signup_tokens 
                (company_id, token, signup_url, qr_code_path, expires_at, created_by_user_id) 
                VALUES (:company_id, :token, :signup_url, :qr_code_path, :expires_at, :created_by)";
        
        $this->db->query($sql, [
            'company_id' => $companyId,
            'token' => $token,
            'signup_url' => $signupUrl,
            'qr_code_path' => $qrPath,
            'expires_at' => $expires,
            'created_by' => $_SESSION['user_id'] ?? null
        ]);
        
        return [
            'token' => $token,
            'signup_url' => $signupUrl,
            'qr_code_path' => $qrPath,
            'expires_at' => $expires
        ];
    }
    
    /**
     * QRコード画像を生成
     * 
     * @param string $url エンコードするURL
     * @param string $token トークン（ファイル名用）
     * @return string 生成された画像の相対パス
     * @throws Exception
     */
    private function generateQRImage($url, $token) {
        // QRコード生成ディレクトリ
        $qrDir = __DIR__ . '/../uploads/qr_codes';
        if (!is_dir($qrDir)) {
            if (!mkdir($qrDir, 0755, true)) {
                throw new Exception("QRコードディレクトリの作成に失敗しました");
            }
        }
        
        $filename = "qr_{$token}.png";
        $filepath = "{$qrDir}/{$filename}";
        
        // phpqrcodeライブラリを使用してQRコード生成
        if (file_exists(__DIR__ . '/../vendor/phpqrcode/qrlib.php')) {
            require_once __DIR__ . '/../vendor/phpqrcode/qrlib.php';
            \QRcode::png($url, $filepath, QR_ECLEVEL_L, 10, 2);
        } else {
            // ライブラリがない場合はダミー画像を作成
            $this->createDummyQRImage($filepath, $url);
        }
        
        return "/uploads/qr_codes/{$filename}";
    }
    
    /**
     * ダミーQRコード画像を作成（開発用）
     * 
     * @param string $filepath ファイルパス
     * @param string $url URL
     */
    private function createDummyQRImage($filepath, $url) {
        // 300x300の白い画像を作成
        $image = imagecreatetruecolor(300, 300);
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        $gray = imagecolorallocate($image, 200, 200, 200);
        imagefill($image, 0, 0, $white);

        // 枠線を描画
        imagerectangle($image, 10, 10, 290, 290, $gray);

        // テキストを複数行で追加
        imagestring($image, 5, 80, 120, "QR Code", $black);
        imagestring($image, 3, 30, 150, "URL:", $gray);
        imagestring($image, 2, 30, 170, substr($url, 0, 40), $gray);
        if (strlen($url) > 40) {
            imagestring($image, 2, 30, 185, substr($url, 40, 40), $gray);
        }

        // PNG保存
        imagepng($image, $filepath);
        imagedestroy($image);
    }
    
    /**
     * ベースURLを取得
     *
     * @return string ベースURL
     */
    private function getBaseUrl() {
        // config/database.php で定義されたBASE_URLを使用
        if (defined('BASE_URL')) {
            return rtrim(BASE_URL, '/');
        }

        // フォールバック: 自動検出
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        // billing-systemディレクトリまでのパスを取得
        $scriptPath = $_SERVER['SCRIPT_NAME'] ?? '';
        // /api/sales/quick_register_company.php から 3階層上へ
        $basePath = dirname(dirname(dirname($scriptPath)));

        return "{$protocol}://{$host}{$basePath}";
    }
    
    /**
     * 企業情報を取得
     * 
     * @param int $companyId 企業ID
     * @return array|null 企業情報
     */
    public function getCompanyInfo($companyId) {
        $sql = "SELECT id, company_code, company_name, company_address, contact_person, phone 
                FROM companies 
                WHERE id = :id";
        
        return $this->db->fetch($sql, ['id' => $companyId]);
    }
    
    /**
     * 企業の既存QRコード情報を取得
     * 
     * @param int $companyId 企業ID
     * @return array|null QRコード情報
     */
    public function getCompanyQRInfo($companyId) {
        $sql = "SELECT id, token, signup_url, qr_code_path, expires_at, created_at 
                FROM company_signup_tokens 
                WHERE company_id = :company_id AND is_active = 1 
                ORDER BY created_at DESC 
                LIMIT 1";
        
        return $this->db->fetch($sql, ['company_id' => $companyId]);
    }
    
    /**
     * QRコード付きPDFを生成
     * 
     * @param int $companyId 企業ID
     * @return string PDFファイルの相対パス
     * @throws Exception
     */
    public function generateQRPDF($companyId) {
        $company = $this->getCompanyInfo($companyId);
        
        if (!$company) {
            throw new Exception("企業が見つかりません");
        }
        
        // QRコード情報取得（なければ生成）
        $qrInfo = $this->getCompanyQRInfo($companyId);
        
        if (!$qrInfo) {
            $qrInfo = $this->generateCompanySignupQR($companyId);
        }
        
        // TCPDFを使用してPDF生成
        if (file_exists(__DIR__ . '/../vendor/tcpdf/tcpdf.php')) {
            return $this->generatePDFWithTCPDF($company, $qrInfo);
        } else {
            // TCPDFがない場合は簡易HTML版を生成
            return $this->generateSimplePDF($company, $qrInfo);
        }
    }
    
    /**
     * TCPDFを使用してPDF生成
     * 
     * @param array $company 企業情報
     * @param array $qrInfo QRコード情報
     * @return string PDFファイルパス
     */
    private function generatePDFWithTCPDF($company, $qrInfo) {
        require_once __DIR__ . '/../vendor/tcpdf/tcpdf.php';
        
        $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8');
        $pdf->SetCreator('Smiley配食システム');
        $pdf->SetTitle('利用者登録用QRコード');
        $pdf->SetMargins(20, 20, 20);
        $pdf->SetAutoPageBreak(true, 20);
        $pdf->AddPage();
        
        // 日本語フォント設定
        $pdf->SetFont('kozminproregular', '', 16);
        
        // PDF内容作成
        $html = $this->createPDFContent($company, $qrInfo);
        $pdf->writeHTML($html, true, false, true, false, '');
        
        // PDF保存
        $pdfDir = __DIR__ . '/../uploads/qr_pdfs';
        if (!is_dir($pdfDir)) {
            mkdir($pdfDir, 0755, true);
        }
        
        $pdfFilename = "qr_signup_{$company['company_code']}_" . date('YmdHis') . ".pdf";
        $pdfPath = "{$pdfDir}/{$pdfFilename}";
        
        $pdf->Output($pdfPath, 'F');
        
        return "/uploads/qr_pdfs/{$pdfFilename}";
    }
    
    /**
     * 簡易HTML版PDF生成（TCPDFがない場合）
     * 
     * @param array $company 企業情報
     * @param array $qrInfo QRコード情報
     * @return string HTMLファイルパス
     */
    private function generateSimplePDF($company, $qrInfo) {
        $htmlDir = __DIR__ . '/../uploads/qr_pdfs';
        if (!is_dir($htmlDir)) {
            mkdir($htmlDir, 0755, true);
        }
        
        $htmlFilename = "qr_signup_{$company['company_code']}_" . date('YmdHis') . ".html";
        $htmlPath = "{$htmlDir}/{$htmlFilename}";
        
        $html = $this->createPDFContent($company, $qrInfo);
        $fullHtml = "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>利用者登録用QRコード</title>
    <style>
        body { font-family: sans-serif; }
        @media print {
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    {$html}
    <div class='no-print' style='margin-top: 30px; text-align: center;'>
        <button onclick='window.print()'>印刷する</button>
    </div>
</body>
</html>";
        
        file_put_contents($htmlPath, $fullHtml);
        
        return "/uploads/qr_pdfs/{$htmlFilename}";
    }
    
    /**
     * PDF内容のHTML生成
     * 
     * @param array $company 企業情報
     * @param array $qrInfo QRコード情報
     * @return string HTML
     */
    private function createPDFContent($company, $qrInfo) {
        $qrImagePath = $qrInfo['qr_code_path'];
        $qrImageFullPath = __DIR__ . '/..' . $qrImagePath;
        
        // 画像が存在するか確認
        $qrImageTag = file_exists($qrImageFullPath) 
            ? "<img src='{$qrImageFullPath}' style='width: 200px; height: 200px;'>" 
            : "<div style='width: 200px; height: 200px; border: 2px solid #ccc; display: flex; align-items: center; justify-content: center;'>QRコード</div>";
        
        return "
        <div style='text-align: center; font-family: sans-serif;'>
            <h1 style='color: #4CAF50;'>Smiley配食サービスのご案内</h1>
            
            <div style='margin: 30px 0; padding: 20px; border: 2px solid #4CAF50; border-radius: 10px;'>
                <h2>{$company['company_name']} 様</h2>
                <p style='font-size: 14px; color: #666;'>
                    企業コード: {$company['company_code']}
                </p>
            </div>
            
            <h2 style='margin-top: 40px;'>■ 初回登録方法</h2>
            
            <div style='margin: 30px 0; padding: 30px; background-color: #f5f5f5; border-radius: 10px;'>
                {$qrImageTag}<br>
                <p style='font-size: 16px; font-weight: bold; margin-top: 20px;'>
                    ↑スマホのカメラで<br>
                    このQRコードを読み取ってください
                </p>
            </div>
            
            <div style='margin: 30px 0; padding: 20px; background-color: #e8f5e9; border-radius: 10px;'>
                <p style='font-size: 14px;'>
                    <strong>または、以下のURLにアクセス:</strong><br>
                    <span style='font-size: 10px; word-break: break-all; color: #666;'>
                        {$qrInfo['signup_url']}
                    </span>
                </p>
            </div>
            
            <hr style='margin: 40px 0; border: 1px solid #ddd;'>
            
            <div style='text-align: left; padding: 20px;'>
                <h3>登録手順:</h3>
                <ol style='font-size: 14px; line-height: 2;'>
                    <li>QRコードをスマホで読み取ります</li>
                    <li>お名前と部署名を入力します</li>
                    <li>パスワード（8文字以上）を設定します</li>
                    <li>「登録する」ボタンを押します</li>
                    <li>利用者コードが表示されます（スクリーンショット推奨）</li>
                    <li>すぐに注文が可能になります</li>
                </ol>
            </div>
            
            <hr style='margin: 40px 0; border: 1px solid #ddd;'>
            
            <p style='font-size: 12px; color: #666;'>
                <strong>お問い合わせ:</strong><br>
                株式会社Smiley 配食事業部<br>
                TEL: 0120-XXX-XXX<br>
                受付時間: 平日 9:00-17:00
            </p>
        </div>
        ";
    }
}
