<?php
/**
 * 最小限動作確認
 * 段階的にエラー箇所を特定
 */

// Stage 1: Basic PHP Test
echo "Stage 1: Basic PHP Test\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Current Time: " . date('Y-m-d H:i:s') . "\n";
echo "Memory Limit: " . ini_get('memory_limit') . "\n";
echo "\n";

// Stage 2: File Existence Test
echo "Stage 2: File Existence Test\n";
$files = [
    'config' => '../config/database.php',
    'database' => '../classes/Database.php',
    'importer' => '../classes/SmileyCSVImporter.php',
    'import_api' => '../api/import.php'
];

foreach ($files as $name => $path) {
    $exists = file_exists($path);
    $size = $exists ? filesize($path) : 0;
    echo "{$name}: " . ($exists ? "EXISTS ({$size} bytes)" : "MISSING") . "\n";
}
echo "\n";

// Stage 3: Config Loading Test
echo "Stage 3: Config Loading Test\n";
try {
    require_once '../config/database.php';
    echo "✓ Config loaded successfully\n";
} catch (Exception $e) {
    echo "✗ Config error: " . $e->getMessage() . "\n";
    exit;
}
echo "\n";

// Stage 4: Database Class Test
echo "Stage 4: Database Class Test\n";
try {
    require_once '../classes/Database.php';
    echo "✓ Database class loaded\n";
    
    if (class_exists('Database')) {
        echo "✓ Database class exists\n";
        
        $db = Database::getInstance();
        echo "✓ Database instance created\n";
        
        $stmt = $db->query("SELECT 1 as test");
        $result = $stmt->fetch();
        echo "✓ Database query successful: " . $result['test'] . "\n";
        
    } else {
        echo "✗ Database class not found\n";
    }
} catch (Exception $e) {
    echo "✗ Database error: " . $e->getMessage() . "\n";
}
echo "\n";

// Stage 5: SmileyCSVImporter Test
echo "Stage 5: SmileyCSVImporter Test\n";
try {
    require_once '../classes/SmileyCSVImporter.php';
    echo "✓ SmileyCSVImporter class loaded\n";
    
    if (class_exists('SmileyCSVImporter')) {
        echo "✓ SmileyCSVImporter class exists\n";
        
        if (isset($db)) {
            $importer = new SmileyCSVImporter($db);
            echo "✓ SmileyCSVImporter instance created\n";
            
            // Check for required methods
            $methods = ['importFile', 'ensureMasterData'];
            foreach ($methods as $method) {
                if (method_exists($importer, $method)) {
                    echo "✓ Method {$method} exists\n";
                } else {
                    echo "✗ Method {$method} missing\n";
                }
            }
        }
    } else {
        echo "✗ SmileyCSVImporter class not found\n";
    }
} catch (Exception $e) {
    echo "✗ SmileyCSVImporter error: " . $e->getMessage() . "\n";
}
echo "\n";

// Stage 6: Import API Test
echo "Stage 6: Import API Test\n";
try {
    // Set up environment for import.php
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_GET['action'] = 'test';
    
    // Capture output
    ob_start();
    include '../api/import.php';
    $output = ob_get_clean();
    
    echo "✓ Import API executed\n";
    echo "Output length: " . strlen($output) . " characters\n";
    
    // Check if it's JSON
    $json = json_decode($output, true);
    if ($json !== null) {
        echo "✓ Valid JSON response\n";
        echo "Success field: " . ($json['success'] ? 'true' : 'false') . "\n";
    } else {
        echo "✗ Invalid JSON response\n";
        echo "First 200 characters:\n";
        echo substr($output, 0, 200) . "\n";
    }
    
} catch (Exception $e) {
    echo "✗ Import API error: " . $e->getMessage() . "\n";
}

echo "\nTest completed.\n";
?>
