<?php
/**
 * CSVアップロード専用デバッグツール
 * POSTリクエスト時のエラーを詳細に確認
 */

// エラー出力を有効化
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/html; charset=utf-8');

echo "<html><head><title>CSV Upload Debug</title></head><body>";
echo "<h1>CSV Upload Debug Tool</h1>";

echo "<h2>1. Request Information</h2>";
echo "<pre>";
echo "Method: " . ($_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN') . "\n";
echo "Content-Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'UNKNOWN') . "\n";
echo "Content-Length: " . ($_SERVER['CONTENT_LENGTH'] ?? 'UNKNOWN') . "\n";
echo "User-Agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN') . "\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n";
echo "</pre>";

echo "<h2>2. PHP Configuration</h2>";
echo "<pre>";
echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "\n";
echo "post_max_size: " . ini_get('post_max_size') . "\n";
echo "max_execution_time: " . ini_get('max_execution_time') . "\n";
echo "memory_limit: " . ini_get('memory_limit') . "\n";
echo "file_uploads: " . (ini_get('file_uploads') ? 'On' : 'Off') . "\n";
echo "max_file_uploads: " . ini_get('max_file_uploads') . "\n";
echo "</pre>";

echo "<h2>3. Upload Test Form</h2>";
echo '<form method="POST" enctype="multipart/form-data" action="upload_debug.php">';
echo '<input type="file" name="csv_file" accept=".csv,.txt"><br><br>';
echo '<input type="submit" value="Upload Test">';
echo '</form>';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<h2>4. POST Data Analysis</h2>";
    echo "<pre>";
    
    echo "POST Data:\n";
    print_r($_POST);
    
    echo "\nFILES Data:\n";
    print_r($_FILES);
    
    echo "\nServer Variables:\n";
    foreach ($_SERVER as $key => $value) {
        if (strpos($key, 'HTTP_') === 0 || strpos($key, 'CONTENT_') === 0) {
            echo "{$key}: {$value}\n";
        }
    }
    echo "</pre>";
    
    if (isset($_FILES['csv_file'])) {
        $file = $_FILES['csv_file'];
        
        echo "<h2>5. File Analysis</h2>";
        echo "<pre>";
        echo "File Name: " . $file['name'] . "\n";
        echo "File Size: " . $file['size'] . " bytes\n";
        echo "File Type: " . $file['type'] . "\n";
        echo "Temp Name: " . $file['tmp_name'] . "\n";
        echo "Error Code: " . $file['error'] . "\n";
        echo "Error Message: " . $this->getUploadErrorMessage($file['error']) . "\n";
        
        if ($file['error'] === UPLOAD_ERR_OK && file_exists($file['tmp_name'])) {
            echo "\nFile Content Analysis:\n";
            
            // ファイル内容の最初の500文字
            $handle = fopen($file['tmp_name'], 'r');
            if ($handle) {
                $firstLine = fgets($handle);
                echo "First line: " . htmlspecialchars($firstLine) . "\n";
                
                $content = fread($handle, 500);
                echo "First 500 chars: " . htmlspecialchars($content) . "\n";
                
                fclose($handle);
            }
            
            // MIMEタイプ確認
            if (function_exists('finfo_open')) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $detectedType = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);
                echo "Detected MIME: " . $detectedType . "\n";
            }
        }
        echo "</pre>";
        
        echo "<h2>6. Import API Test</h2>";
        echo "<pre>";
        
        try {
            // import.php を実際に呼び出してテスト
            
            // 必須ファイル確認
            echo "Checking required files...\n";
            $requiredFiles = [
                '../config/database.php',
                '../classes/Database.php',
                '../classes/SmileyCSVImporter.php',
                '../api/import.php'
            ];
            
            foreach ($requiredFiles as $reqFile) {
                if (file_exists($reqFile)) {
                    echo "✓ {$reqFile}\n";
                } else {
                    echo "✗ {$reqFile} - MISSING\n";
                    throw new Exception("Required file missing: {$reqFile}");
                }
            }
            
            // クラス読み込み
            echo "\nLoading classes...\n";
            require_once '../config/database.php';
            require_once '../classes/Database.php';
            require_once '../classes/SmileyCSVImporter.php';
            
            echo "✓ Classes loaded\n";
            
            // データベース接続
            echo "\nTesting database connection...\n";
            $db = Database::getInstance();
            $stmt = $db->query("SELECT 1 as test");
            $result = $stmt->fetch();
            echo "✓ Database connected (test result: {$result['test']})\n";
            
            // CSVインポーター初期化
            echo "\nInitializing CSV importer...\n";
            $importer = new SmileyCSVImporter($db);
            echo "✓ CSV Importer created\n";
            
            // 実際のインポート実行（ファイルがアップロードされている場合）
            if ($file['error'] === UPLOAD_ERR_OK && $file['size'] > 0) {
                echo "\nExecuting import...\n";
                
                $result = $importer->importFile($file['tmp_name'], [
                    'encoding' => 'auto',
                    'validate_smiley' => true
                ]);
                
                echo "✓ Import completed successfully\n";
                echo "Batch ID: " . $result['batch_id'] . "\n";
                echo "Total records: " . $result['stats']['total'] . "\n";
                echo "Success records: " . $result['stats']['success'] . "\n";
                echo "Error records: " . count($result['errors']) . "\n";
                echo "Processing time: " . $result['processing_time'] . " seconds\n";
                
                if (!empty($result['errors'])) {
                    echo "\nErrors:\n";
                    foreach ($result['errors'] as $error) {
                        echo "Row {$error['row']}: " . implode(', ', $error['errors']) . "\n";
                    }
                }
                
            } else {
                echo "No valid file uploaded for import test\n";
            }
            
        } catch (Exception $e) {
            echo "ERROR: " . $e->getMessage() . "\n";
            echo "File: " . $e->getFile() . "\n";
            echo "Line: " . $e->getLine() . "\n";
            echo "Trace:\n" . $e->getTraceAsString() . "\n";
        }
        
        echo "</pre>";
    }
}

// ヘルパー関数
function getUploadErrorMessage($errorCode) {
    $messages = [
        UPLOAD_ERR_OK => 'No error',
        UPLOAD_ERR_INI_SIZE => 'File size exceeds upload_max_filesize',
        UPLOAD_ERR_FORM_SIZE => 'File size exceeds MAX_FILE_SIZE',
        UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
        UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
    ];
    
    return $messages[$errorCode] ?? 'Unknown error';
}

echo "</body></html>";
?>
