<?php
// エラー表示を有効化
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "PHP Version: " . phpversion() . "<br>";
echo "Current Directory: " . __DIR__ . "<br><br>";

try {
    echo "Step 1: Loading database.php...<br>";
    require_once __DIR__ . '/config/database.php';
    echo "✓ database.php loaded successfully<br><br>";

    echo "Step 2: Loading SimpleCollectionManager.php...<br>";
    require_once __DIR__ . '/classes/SimpleCollectionManager.php';
    echo "✓ SimpleCollectionManager.php loaded successfully<br><br>";

    echo "Step 3: Creating SimpleCollectionManager instance...<br>";
    $manager = new SimpleCollectionManager();
    echo "✓ SimpleCollectionManager instantiated successfully<br><br>";

    echo "Step 4: Testing database connection...<br>";
    $db = Database::getInstance();
    echo "✓ Database connection successful<br><br>";

    echo "Step 5: Loading ReceiptManager.php...<br>";
    require_once __DIR__ . '/classes/ReceiptManager.php';
    echo "✓ ReceiptManager.php loaded successfully<br><br>";

    echo "Step 6: Creating ReceiptManager instance...<br>";
    $receiptManager = new ReceiptManager();
    echo "✓ ReceiptManager instantiated successfully<br><br>";

    echo "<strong>All tests passed!</strong>";

} catch (Exception $e) {
    echo "<strong style='color: red;'>ERROR:</strong> " . $e->getMessage() . "<br>";
    echo "<strong>File:</strong> " . $e->getFile() . "<br>";
    echo "<strong>Line:</strong> " . $e->getLine() . "<br>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
