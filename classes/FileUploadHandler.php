<?php
/**
 * ファイルアップロード処理クラス
 * セキュアなファイルアップロード機能
 */
class FileUploadHandler {
    
    private $allowedTypes = [
        'text/csv',
        'text/plain',
        'application/vnd.ms-excel',
        'application/csv'
    ];
    
    private $allowedExtensions = ['csv', 'txt'];
    private $maxFileSize = 10485760; // 10MB
    private $uploadDir = '../uploads/';
    
    public function __construct($uploadDir = null) {
        if ($uploadDir) {
            $this->uploadDir = rtrim($uploadDir, '/') . '/';
        }
        
        // アップロードディレクトリ作成
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
    }
    
    /**
     * ファイルアップロード処理
     */
    public function uploadFile($file) {
        try {
            // 基本検証
            $this->validateFile($file);
            
            // セキュリティチェック
            $this->securityCheck($file);
            
            // ファイル移動
            $targetPath = $this->moveUploadedFile($file);
            
            return [
                'success' => true,
                'filepath' => $targetPath,
                'filename' => basename($targetPath),
                'size' => $file['size'],
                'type' => $file['type']
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'errors' => [$e->getMessage()]
            ];
        }
    }
    
    /**
     * ファイル基本検証
     */
    private function validateFile($file) {
        // アップロードエラーチェック
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception($this->getUploadErrorMessage($file['error']));
        }
        
        // ファイルサイズチェック
        if ($file['size'] > $this->maxFileSize) {
            $maxSizeMB = round($this->maxFileSize / 1024 / 1024, 1);
            $fileSizeMB = round($file['size'] / 1024 / 1024, 1);
            throw new Exception("ファイルサイズが上限を超えています（上限: {$maxSizeMB}MB, アップロード: {$fileSizeMB}MB）");
        }
        
        // 拡張子チェック
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $this->allowedExtensions)) {
            throw new Exception("許可されていない拡張子です: .{$extension}");
        }
    }
    
    /**
     * セキュリティチェック
     */
    private function securityCheck($file) {
        // MIMEタイプチェック（実際のファイル内容を確認）
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $detectedType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            if (!in_array($detectedType, $this->allowedTypes)) {
                throw new Exception("許可されていないファイル形式です: {$detectedType}");
            }
        }
        
        // ファイル内容の安全性チェック
        $this->checkFileContent($file['tmp_name']);
    }
    
    /**
     * ファイル内容チェック
     */
    private function checkFileContent($tmpPath) {
        // ファイルの最初の1024バイトを確認
        $handle = fopen($tmpPath, 'r');
        if ($handle) {
            $firstBytes = fread($handle, 1024);
            fclose($handle);
            
            // 実行可能ファイルの署名をチェック
            $dangerousSignatures = [
                "\x4D\x5A", // PE executable
                "\x7F\x45\x4C\x46", // ELF executable
                "<?php", // PHP script
                "<script", // JavaScript
                "<%", // ASP/JSP
            ];
            
            foreach ($dangerousSignatures as $signature) {
                if (strpos($firstBytes, $signature) === 0 || strpos($firstBytes, $signature) !== false) {
                    throw new Exception("危険なファイル内容が検出されました");
                }
            }
        }
    }
    
    /**
     * アップロードされたファイルを安全な場所に移動
     */
    private function moveUploadedFile($file) {
        // 安全なファイル名生成
        $safeFilename = $this->generateSafeFilename($file['name']);
        $targetPath = $this->uploadDir . $safeFilename;
        
        // ファイル移動
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new Exception("ファイルの移動に失敗しました");
        }
        
        // 権限設定
        chmod($targetPath, 0644);
        
        return $targetPath;
    }
    
    /**
     * 安全なファイル名生成
     */
    private function generateSafeFilename($originalName) {
        $pathInfo = pathinfo($originalName);
        $baseName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $pathInfo['filename']);
        $extension = strtolower($pathInfo['extension']);
        
        // タイムスタンプとランダム文字列を追加
        $timestamp = date('YmdHis');
        $random = substr(uniqid(), -6);
        
        return "{$baseName}_{$timestamp}_{$random}.{$extension}";
    }
    
    /**
     * アップロードエラーメッセージ取得
     */
    private function getUploadErrorMessage($errorCode) {
        $messages = [
            UPLOAD_ERR_INI_SIZE => 'ファイルサイズがphp.iniの上限を超えています',
            UPLOAD_ERR_FORM_SIZE => 'ファイルサイズがフォームの上限を超えています',
            UPLOAD_ERR_PARTIAL => 'ファイルが部分的にしかアップロードされませんでした',
            UPLOAD_ERR_NO_FILE => 'ファイルがアップロードされませんでした',
            UPLOAD_ERR_NO_TMP_DIR => '一時ディレクトリが見つかりません',
            UPLOAD_ERR_CANT_WRITE => 'ディスクへの書き込みに失敗しました',
            UPLOAD_ERR_EXTENSION => 'PHPの拡張機能によってアップロードが停止されました'
        ];
        
        return $messages[$errorCode] ?? '不明なアップロードエラーが発生しました';
    }
    
    /**
     * 古いアップロードファイルを削除
     */
    public function cleanupOldFiles($maxAge = 3600) {
        if (!is_dir($this->uploadDir)) {
            return;
        }
        
        $files = glob($this->uploadDir . '*');
        $now = time();
        
        foreach ($files as $file) {
            if (is_file($file) && ($now - filemtime($file)) > $maxAge) {
                unlink($file);
            }
        }
    }
    
    /**
     * CSVファイル専用バリデーション
     */
    public function validateCSVFile($filePath) {
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new Exception('CSVファイルを開けません');
        }
        
        // 最初の行（ヘッダー）をチェック
        $firstLine = fgets($handle);
        fclose($handle);
        
        if (empty(trim($firstLine))) {
            throw new Exception('CSVファイルが空です');
        }
        
        // CSV形式かチェック
        $columns = str_getcsv($firstLine);
        if (count($columns) < 2) {
            throw new Exception('有効なCSVファイルではありません');
        }
        
        return true;
    }
}
?>
