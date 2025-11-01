<?php
/**
 * productsテーブル マイグレーション実行API
 * CSVインポート機能に必要なカラムを追加
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json; charset=utf-8');

function sendResponse($success, $message, $data = []) {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

try {
    require_once __DIR__ . '/../config/database.php';

    $db = Database::getInstance();
    $pdo = $db->getConnection();

    $results = [];
    $errors = [];

    // 1. 既存のカラム構造を確認
    $stmt = $pdo->query("
        SELECT COLUMN_NAME
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products'
    ");
    $existingColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $results['existing_columns'] = $existingColumns;

    // 2. categoryをcategory_codeにリネーム
    if (in_array('category', $existingColumns) && !in_array('category_code', $existingColumns)) {
        try {
            $pdo->exec("
                ALTER TABLE products
                CHANGE COLUMN category category_code VARCHAR(50) COMMENT '給食区分CD'
            ");
            $results['rename_category'] = 'success';
        } catch (Exception $e) {
            $errors[] = 'category rename error: ' . $e->getMessage();
            $results['rename_category'] = 'failed: ' . $e->getMessage();
        }
    } else {
        $results['rename_category'] = 'skipped (already exists or not needed)';
    }

    // 3. category_name カラムを追加
    if (!in_array('category_name', $existingColumns)) {
        try {
            $pdo->exec("
                ALTER TABLE products
                ADD COLUMN category_name VARCHAR(100) COMMENT '給食区分名'
                AFTER category_code
            ");
            $results['add_category_name'] = 'success';
        } catch (Exception $e) {
            $errors[] = 'category_name add error: ' . $e->getMessage();
            $results['add_category_name'] = 'failed: ' . $e->getMessage();
        }
    } else {
        $results['add_category_name'] = 'skipped (already exists)';
    }

    // 4. supplier_id カラムを追加
    if (!in_array('supplier_id', $existingColumns)) {
        try {
            $pdo->exec("
                ALTER TABLE products
                ADD COLUMN supplier_id INT COMMENT '給食業者ID'
                AFTER category_name
            ");
            $results['add_supplier_id'] = 'success';
        } catch (Exception $e) {
            $errors[] = 'supplier_id add error: ' . $e->getMessage();
            $results['add_supplier_id'] = 'failed: ' . $e->getMessage();
        }
    } else {
        $results['add_supplier_id'] = 'skipped (already exists)';
    }

    // 5. unit_price カラムを追加
    if (!in_array('unit_price', $existingColumns)) {
        try {
            $pdo->exec("
                ALTER TABLE products
                ADD COLUMN unit_price DECIMAL(10, 2) COMMENT '単価'
                AFTER supplier_id
            ");
            $results['add_unit_price'] = 'success';
        } catch (Exception $e) {
            $errors[] = 'unit_price add error: ' . $e->getMessage();
            $results['add_unit_price'] = 'failed: ' . $e->getMessage();
        }
    } else {
        $results['add_unit_price'] = 'skipped (already exists)';
    }

    // 6. インデックスを追加
    try {
        $pdo->exec("ALTER TABLE products ADD INDEX idx_category_code (category_code)");
        $results['add_index_category'] = 'success';
    } catch (Exception $e) {
        // インデックスが既に存在する場合はスキップ
        $results['add_index_category'] = 'skipped or failed: ' . $e->getMessage();
    }

    try {
        $pdo->exec("ALTER TABLE products ADD INDEX idx_supplier_id (supplier_id)");
        $results['add_index_supplier'] = 'success';
    } catch (Exception $e) {
        $results['add_index_supplier'] = 'skipped or failed: ' . $e->getMessage();
    }

    // 7. 最終的なカラム構造を確認
    $stmt = $pdo->query("
        SELECT COLUMN_NAME, DATA_TYPE, COLUMN_TYPE, COLUMN_COMMENT
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products'
        ORDER BY ORDINAL_POSITION
    ");
    $finalColumns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $results['final_columns'] = $finalColumns;

    // 8. 結果判定
    $hasErrors = !empty($errors);
    $message = $hasErrors
        ? 'マイグレーション完了（一部エラーあり）'
        : 'マイグレーション完了（全て成功）';

    sendResponse(!$hasErrors, $message, [
        'results' => $results,
        'errors' => $errors
    ]);

} catch (Exception $e) {
    sendResponse(false, 'マイグレーションエラー: ' . $e->getMessage(), [
        'error' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]);
}
?>
