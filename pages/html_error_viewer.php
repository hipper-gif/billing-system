<?php
/**
 * HTMLエラー内容確認ツール
 * import.phpのエラー詳細をHTMLとして表示
 */

echo "<html><head><title>Error Debug</title></head><body>";
echo "<h1>Import.php Error Debug</h1>";

echo "<h2>1. Direct File Include Test</h2>";
echo "<pre>";

try {
    // エラー表示を有効化
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    
    echo "Setting up environment...\n";
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_GET['action'] = 'test';
    
    echo "Including import.php...\n";
    
    // import.phpを直接includeしてエラーをキャッチ
    ob_start();
    include '../api/import.php';
    $output = ob_get_clean();
    
    echo "Success! Output received:\n";
    echo "Output length: " . strlen($output) . " characters\n";
    echo "First 500 characters:\n";
    echo htmlspecialchars(substr($output, 0, 500));
    
} catch (ParseError $e) {
    echo "PARSE ERROR:\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
} catch (Error $e) {
    echo "FATAL ERROR:\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
} catch (Exception $e) {
    echo "EXCEPTION:\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}

echo "</pre>";

echo "<h2>2. File Content Analysis</h2>";
echo "<pre>";

$importPath = '../api/import.php';
if (file_exists($importPath)) {
    echo "File exists: YES\n";
    echo "File size: " . filesize($importPath) . " bytes\n";
    echo "Last modified: " . date('Y-m-d H:i:s', filemtime($importPath)) . "\n";
    
    // ファイル内容の最初と最後をチェック
    $content = file_get_contents($importPath);
    echo "Content length: " . strlen($content) . " characters\n";
    echo "First 200 characters:\n";
    echo htmlspecialchars(substr($content, 0, 200)) . "\n\n";
    echo "Last 200 characters:\n";
    echo htmlspecialchars(substr($content, -200)) . "\n";
    
    // PHP構文チェック
    echo "\nPHP Syntax Check:\n";
    $tempFile = tempnam(sys_get_temp_dir(), 'syntax_check');
    file_put_contents($tempFile, $content);
    
    $output = [];
    $returnVar = 0;
    exec("php -l " . escapeshellarg($tempFile) . " 2>&1", $output, $returnVar);
    
    if ($returnVar === 0) {
        echo "Syntax: OK\n";
    } else {
        echo "Syntax errors found:\n";
        foreach ($output as $line) {
            echo $line . "\n";
        }
    }
    unlink($tempFile);
    
} else {
    echo "File does not exist: {$importPath}\n";
}

echo "</pre>";

echo "<h2>3. Required Files Check</h2>";
echo "<pre>";

$requiredFiles = [
    '../config/database.php',
    '../classes/Database.php',
    '../classes/SmileyCSVImporter.php',
    '../classes/SecurityHelper.php',
    '../classes/FileUploadHandler.php'
];

foreach ($requiredFiles as $file) {
    echo "Checking: {$file}\n";
    if (file_exists($file)) {
        echo "  EXISTS (size: " . filesize($file) . " bytes)\n";
        
        // 各ファイルの構文チェック
        $content = file_get_contents($file);
        $tempFile = tempnam(sys_get_temp_dir(), 'syntax_check');
        file_put_contents($tempFile, $content);
        
        $output = [];
        $returnVar = 0;
        exec("php -l " . escapeshellarg($tempFile) . " 2>&1", $output, $returnVar);
        
        if ($returnVar === 0) {
            echo "  Syntax: OK\n";
        } else {
            echo "  Syntax ERROR:\n";
            foreach ($output as $line) {
                echo "    " . $line . "\n";
            }
        }
        unlink($tempFile);
        
    } else {
        echo "  MISSING\n";
    }
    echo "\n";
}

echo "</pre>";

echo "<h2>4. Manual Class Loading Test</h2>";
echo "<pre>";

try {
    echo "Loading config/database.php...\n";
    require_once '../config/database.php';
    echo "OK\n";
    
    echo "Loading classes/Database.php...\n";
    require_once '../classes/Database.php';
    echo "OK\n";
    echo "Database class exists: " . (class_exists('Database') ? 'YES' : 'NO') . "\n";
    
    echo "Testing Database::getInstance()...\n";
    $db = Database::getInstance();
    echo "OK\n";
    
    echo "Testing database query...\n";
    $stmt = $db->query("SELECT 1 as test");
    $result = $stmt->fetch();
    echo "Query result: " . ($result['test'] ?? 'NULL') . "\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}

echo "</pre>";

echo "</body></html>";
?>
