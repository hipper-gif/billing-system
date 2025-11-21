<?php
/**
 * Receipts Table Migration Runner
 * Modify receipts table to allow NULL values for pre-receipt functionality
 */

require_once __DIR__ . '/config/database.php';

echo "=== Receipts Table Migration ===" . PHP_EOL . PHP_EOL;
echo "Environment: " . ENVIRONMENT . PHP_EOL;
echo "Database: " . DB_NAME . PHP_EOL . PHP_EOL;

// Check if running from CLI
if (php_sapi_name() !== 'cli') {
    echo "This script must be run from command line." . PHP_EOL;
    exit(1);
}

echo "This migration will modify the receipts table to:" . PHP_EOL;
echo "- Allow NULL values for payment_id column" . PHP_EOL;
echo "- Allow NULL values for issue_date column" . PHP_EOL;
echo "- Update foreign key constraint to allow NULL" . PHP_EOL . PHP_EOL;

echo "Execute migration? [y/N]: ";
$handle = fopen("php://stdin", "r");
$line = fgets($handle);
fclose($handle);

if (trim(strtolower($line)) !== 'y') {
    echo "Cancelled." . PHP_EOL;
    exit(0);
}

echo PHP_EOL . "Executing migration..." . PHP_EOL . PHP_EOL;

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();

    // Read migration file
    $migrationFile = __DIR__ . '/sql/migration_modify_receipts_allow_null.sql';

    if (!file_exists($migrationFile)) {
        throw new Exception("Migration file not found: " . $migrationFile);
    }

    $sql = file_get_contents($migrationFile);

    // Split into statements
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        function($stmt) {
            $stmt = trim($stmt);
            return !empty($stmt) &&
                   strpos($stmt, '--') !== 0;
        }
    );

    echo "Found " . count($statements) . " SQL statements" . PHP_EOL . PHP_EOL;

    // Execute each statement
    $successCount = 0;
    $skipCount = 0;

    foreach ($statements as $i => $statement) {
        $statementNum = $i + 1;
        $preview = substr(str_replace(["\n", "\r", "  "], ' ', $statement), 0, 80);
        echo "[{$statementNum}] {$preview}..." . PHP_EOL;

        try {
            $conn->exec($statement);
            echo "    ✓ Success" . PHP_EOL;
            $successCount++;
        } catch (PDOException $e) {
            $errorMsg = $e->getMessage();

            // Skip "doesn't exist" errors for DROP FOREIGN KEY
            if ((strpos($errorMsg, "check that column/key exists") !== false ||
                 strpos($errorMsg, "Can't DROP") !== false) &&
                strpos($statement, "DROP FOREIGN KEY") !== false) {
                echo "    ⊘ Skipped (foreign key doesn't exist)" . PHP_EOL;
                $skipCount++;
            } else {
                throw $e;
            }
        }
    }

    echo PHP_EOL . "=== Migration Complete ===" . PHP_EOL;
    echo "Success: {$successCount} statements" . PHP_EOL;
    echo "Skipped: {$skipCount} statements" . PHP_EOL . PHP_EOL;

    // Verify changes
    echo "=== Verifying Schema Changes ===" . PHP_EOL;
    $stmt = $conn->query("SHOW COLUMNS FROM receipts WHERE Field IN ('payment_id', 'issue_date')");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($columns as $column) {
        $nullable = $column['Null'] === 'YES' ? '✓ NULL allowed' : '✗ NOT NULL';
        $status = $column['Null'] === 'YES' ? '✓' : '✗';
        echo "  {$status} {$column['Field']}: {$column['Type']} - {$nullable}" . PHP_EOL;
    }

    // Check foreign keys
    echo PHP_EOL . "Foreign Keys:" . PHP_EOL;
    $stmt = $conn->query("
        SELECT CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = '" . DB_NAME . "'
        AND TABLE_NAME = 'receipts'
        AND REFERENCED_TABLE_NAME IS NOT NULL
    ");
    $fks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($fks as $fk) {
        echo "  ✓ {$fk['CONSTRAINT_NAME']}: {$fk['COLUMN_NAME']} -> {$fk['REFERENCED_TABLE_NAME']}.{$fk['REFERENCED_COLUMN_NAME']}" . PHP_EOL;
    }

    echo PHP_EOL . "Migration completed successfully!" . PHP_EOL;
    echo "Pre-receipt functionality is now enabled." . PHP_EOL;

} catch (Exception $e) {
    echo PHP_EOL . "Error: " . $e->getMessage() . PHP_EOL;
    exit(1);
}

exit(0);
