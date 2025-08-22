<?php
/**
 * シンプルCSV処理テスト
 * アップロードされたCSVを直接処理
 */

header('Content-Type: text/html; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<html><head><title>Simple CSV Test</title></head><body>";
echo "<h1>Simple CSV Import Test</h1>";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];
    
    echo "<h2>File Information</h2>";
    echo "<p><strong>Name:</strong> " . htmlspecialchars($file['name']) . "</p>";
    echo "<p><strong>Size:</strong> " . number_format($file['size']) . " bytes</p>";
    echo "<p><strong>Type:</strong> " . htmlspecialchars($file['type']) . "</p>";
    echo "<p><strong>Error:</strong> " . $file['error'] . "</p>";
    
    if ($file['error'] === 0 && $file['size'] > 0) {
        echo "<h2>Step 1: File Content Preview</h2>";
        echo "<pre>";
        
        // ファイル内容の最初の10行を表示
        $handle = fopen($file['tmp_name'], 'r');
        if ($handle) {
            $lineCount = 0;
            while (($line = fgets($handle)) !== false && $lineCount < 10) {
                echo htmlspecialchars($line);
                $lineCount++;
            }
            fclose($handle);
        }
        echo "</pre>";
        
        echo "<h2>Step 2: CSV Parsing Test</h2>";
        echo "<pre>";
        
        try {
            $handle = fopen($file['tmp_name'], 'r');
            if (!$handle) {
                throw new Exception("Cannot open file");
            }
            
            // ヘッダー行読み取り
            $headers = fgetcsv($handle);
            echo "Headers (" . count($headers) . " columns):\n";
            foreach ($headers as $i => $header) {
                echo "  [{$i}] " . htmlspecialchars(trim($header)) . "\n";
            }
            
            // データ行を3行読み取り
            echo "\nFirst 3 data rows:\n";
            $rowCount = 0;
            while (($row = fgetcsv($handle)) !== false && $rowCount < 3) {
                echo "Row " . ($rowCount + 1) . " (" . count($row) . " columns):\n";
                foreach ($row as $i => $cell) {
                    echo "  [{$i}] " . htmlspecialchars(trim($cell)) . "\n";
                }
                echo "\n";
                $rowCount++;
            }
            
            fclose($handle);
            
        } catch (Exception $e) {
            echo "CSV Parsing Error: " . $e->getMessage() . "\n";
        }
        
        echo "</pre>";
        
        echo "<h2>Step 3: Import Processing Test</h2>";
        echo "<pre>";
        
        try {
            // 必須ファイル読み込み
            echo "Loading required files...\n";
            require_once '../config/database.php';
            require_once '../classes/Database.php';
            require_once '../classes/SmileyCSVImporter.php';
            echo "✓ Files loaded\n";
            
            // データベース接続
            echo "Connecting to database...\n";
            $db = Database::getInstance();
            echo "✓ Database connected\n";
            
            // インポーター初期化
            echo "Initializing importer...\n";
            $importer = new SmileyCSVImporter($db);
            echo "✓ Importer created\n";
            
            // 実際のインポート実行
            echo "Executing import...\n";
            $startTime = microtime(true);
            
            $result = $importer->importFile($file['tmp_name'], [
                'encoding' => 'auto',
                'validate_smiley' => true
            ]);
            
            $processingTime = microtime(true) - $startTime;
            
            echo "✓ Import completed!\n";
            echo "\nResults:\n";
            echo "  Batch ID: " . $result['batch_id'] . "\n";
            echo "  Total records: " . $result['stats']['total'] . "\n";
            echo "  Success records: " . $result['stats']['success'] . "\n";
            echo "  Error records: " . count($result['errors']) . "\n";
            echo "  Processing time: " . round($processingTime, 2) . " seconds\n";
            
            if (!empty($result['errors'])) {
                echo "\nErrors:\n";
                $errorCount = 0;
                foreach ($result['errors'] as $error) {
                    if ($errorCount >= 5) {
                        echo "  ... and " . (count($result['errors']) - 5) . " more errors\n";
                        break;
                    }
                    echo "  Row {$error['row']}: " . implode(', ', $error['errors']) . "\n";
                    $errorCount++;
                }
            }
            
            // データベース確認
            echo "\nDatabase verification:\n";
            $stmt = $db->query("SELECT COUNT(*) as count FROM orders WHERE import_batch_id = ?", [$result['batch_id']]);
            $orderCount = $stmt->fetch()['count'];
            echo "  Orders inserted: {$orderCount}\n";
            
        } catch (Exception $e) {
            echo "ERROR: " . $e->getMessage() . "\n";
            echo "File: " . $e->getFile() . "\n";
            echo "Line: " . $e->getLine() . "\n";
            echo "\nTrace:\n" . $e->getTraceAsString() . "\n";
        }
        
        echo "</pre>";
    }
} else {
    echo "<h2>Upload CSV File</h2>";
    echo '<form method="POST" enctype="multipart/form-data">';
    echo '<input type="file" name="csv_file" accept=".csv,.txt"><br><br>';
    echo '<input type="submit" value="Process CSV">';
    echo '</form>';
}

echo "</body></html>";
?>
