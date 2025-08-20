<?php
/**
 * ファイルアップロード処理クラス
 * Smiley配食事業CSV専用
 */
class FileUploadHandler {
    private $allowedTypes = ['text/csv', 'application/csv', 'text/plain', 'application/vnd.ms-excel'];
    private $allowedExtensions = ['csv'];
    private $maxFileSize = 10 * 1024 * 1024; // 10MB
    private $uploadDir = '../uploads/';
    
    public function __construct() {
        // アップロードディレクトリ作成
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
    }
    
    /**
     * ファイルアップロード実行
     */
    public function uploadFile($file) {
        try {
            // 基本検証
            $validationResult = $this->validateFile($file);
            if (!$validationResult['success']) {
                return $validationResult;
            }
            
            // 安全なファイル名生成
            $filename = $this->generateSafeFilename($file['name']);
            $filepath = $this->uploadDir . $filename;
            
            // ファイル移動
            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                return [
                    'success' => true,
                    'filename' => $filename,
                    'filepath' => $filepath,
                    'filesize' => $file['size'],
                    'original_name' => $file['name']
                ];
            } else {
                return [
                    'success' => false,
                    'errors' => ['ファイルの移動に失敗しました']
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'errors' => ['アップロードエラー: ' . $e->getMessage()]
            ];
        }
    }
    
    /**
     * ファイル検証
     */
    public function validateFile($file) {
        $errors = [];
        
        // ファイル存在チェック
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            $errors[] = 'ファイルがアップロードされていません';
            return ['success' => false, 'errors' => $errors];
        }
        
        // アップロードエラーチェック
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = $this->getUploadErrorMessage($file['error']);
        }
        
        // ファイルサイズチェック
        if ($file['size'] > $this->maxFileSize) {
            $errors[] = 'ファイルサイズが' . round($this->maxFileSize / 1024 / 1024, 1) . 'MBを超えています';
        }
        
        if ($file['size'] == 0) {
            $errors[] = 'ファイルが空です';
        }
        
        // 拡張子チェック
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $this->allowedExtensions)) {
            $errors[] = 'CSVファイルのみアップロード可能です（現在: .' . $extension . '）';
        }
        
        // MIMEタイプチェック（可能な場合）
        if (function_exists('finfo_open') && function_exists('finfo_file')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            if (!in_array($mimeType, $this->allowedTypes)) {
                // CSVファイルは様々なMIMEタイプで認識される可能性があるため、警告のみ
                error_log("Unexpected MIME type for CSV: $mimeType");
            }
        }
        
        // ファイル内容の基本チェック
        $this->validateFileContent($file['tmp_name'], $errors);
        
        return [
            'success' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * ファイル内容の基本検証
     */
    private function validateFileContent($tmpPath, &$errors) {
        $handle = fopen($tmpPath, 'r');
        if (!$handle) {
            $errors[] = 'ファイルを読み込めません';
            return;
        }
        
        // 最初の数行を読んで基本的な形式チェック
        $lineCount = 0;
        $maxCheckLines = 5;
        $csvRowCounts = [];
        
        while (($line = fgets($handle)) !== false && $lineCount < $maxCheckLines) {
            $lineCount++;
            
            // 空行をスキップ
            if (trim($line) === '') continue;
            
            // CSV行の解析
            $row = str_getcsv($line);
            $csvRowCounts[] = count($row);
        }
        
        fclose($handle);
        
        if ($lineCount === 0) {
            $errors[] = 'ファイルに有効なデータがありません';
            return;
        }
        
        // 列数の一貫性チェック
        $uniqueCounts = array_unique($csvRowCounts);
        if (count($uniqueCounts) > 1) {
            $errors[] = 'CSV行の列数が一貫していません';
        }
        
        // Smiley配食システムの期待する23列チェック
        $expectedColumns = 23;
        if (!empty($csvRowCounts) && $csvRowCounts[0] !== $expectedColumns) {
            $errors[] = "列数が期待値と異なります（期待: {$expectedColumns}列、実際: {$csvRowCounts[0]}列）";
        }
    }
    
    /**
     * 安全なファイル名生成
     */
    private function generateSafeFilename($originalName) {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $baseName = pathinfo($originalName, PATHINFO_FILENAME);
        
        // ファイル名をサニタイズ
        $safeName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $baseName);
        $safeName = substr($safeName, 0, 50); // 長さ制限
        
        // タイムスタンプとユニークIDを追加
        $timestamp = date('YmdHis');
        $uniqueId = substr(uniqid(), -6);
        
        return "smiley_csv_{$timestamp}_{$uniqueId}_{$safeName}.{$extension}";
    }
    
    /**
     * アップロードエラーメッセージ取得
     */
    private function getUploadErrorMessage($errorCode) {
        switch ($errorCode) {
            case UPLOAD_ERR_INI_SIZE:
                return 'ファイルサイズがサーバーの上限を超えています';
            case UPLOAD_ERR_FORM_SIZE:
                return 'ファイルサイズがフォームの上限を超えています';
            case UPLOAD_ERR_PARTIAL:
                return 'ファイルが部分的にしかアップロードされませんでした';
            case UPLOAD_ERR_NO_FILE:
                return 'ファイルがアップロードされませんでした';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'サーバーの一時ディレクトリがありません';
            case UPLOAD_ERR_CANT_WRITE:
                return 'ディスクへの書き込みに失敗しました';
            case UPLOAD_ERR_EXTENSION:
                return 'PHPの拡張機能によってアップロードが中断されました';
            default:
                return "不明なアップロードエラー（コード: {$errorCode}）";
        }
    }
    
    /**
     * アップロード済みファイルの削除
     */
    public function deleteFile($filepath) {
        if (file_exists($filepath) && strpos($filepath, $this->uploadDir) === 0) {
            return unlink($filepath);
        }
        return false;
    }
    
    /**
     * 古いアップロードファイルの削除（クリーンアップ）
     */
    public function cleanupOldFiles($olderThanHours = 24) {
        $cutoffTime = time() - ($olderThanHours * 3600);
        $deletedCount = 0;
        
        if (is_dir($this->uploadDir)) {
            $files = scandir($this->uploadDir);
            foreach ($files as $file) {
                if ($file === '.' || $file === '..') continue;
                
                $filepath = $this->uploadDir . $file;
                if (is_file($filepath) && filemtime($filepath) < $cutoffTime) {
                    if (unlink($filepath)) {
                        $deletedCount++;
                    }
                }
            }
        }
        
        return $deletedCount;
    }
    
    /**
     * アップロード設定情報取得
     */
    public function getUploadInfo() {
        return [
            'max_file_size' => $this->maxFileSize,
            'max_file_size_mb' => round($this->maxFileSize / 1024 / 1024, 1),
            'allowed_extensions' => $this->allowedExtensions,
            'allowed_mime_types' => $this->allowedTypes,
            'upload_dir' => $this->uploadDir,
            'server_upload_max_filesize' => ini_get('upload_max_filesize'),
            'server_post_max_size' => ini_get('post_max_size'),
            'server_max_execution_time' => ini_get('max_execution_time')
        ];
    }
}
?>
