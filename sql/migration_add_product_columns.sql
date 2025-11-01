-- ============================================================================
-- productsテーブル カラム追加マイグレーション
-- CSVインポート機能に必要なカラムを追加
-- 実行日: 2025-11-01
-- ============================================================================

-- 既存のcategoryカラムをcategory_codeにリネーム（データ保持）
ALTER TABLE products
CHANGE COLUMN category category_code VARCHAR(50) COMMENT '給食区分CD';

-- category_name カラムを追加（給食区分名）
ALTER TABLE products
ADD COLUMN category_name VARCHAR(100) COMMENT '給食区分名'
AFTER category_code;

-- supplier_id カラムを追加（給食業者ID）
ALTER TABLE products
ADD COLUMN supplier_id INT COMMENT '給食業者ID'
AFTER category_name;

-- unit_price カラムを追加（単価）
ALTER TABLE products
ADD COLUMN unit_price DECIMAL(10, 2) COMMENT '単価'
AFTER supplier_id;

-- インデックスを追加
ALTER TABLE products
ADD INDEX idx_category_code (category_code);

ALTER TABLE products
ADD INDEX idx_supplier_id (supplier_id);

-- 確認用クエリ
SELECT COLUMN_NAME, DATA_TYPE, COLUMN_TYPE, COLUMN_COMMENT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products'
ORDER BY ORDINAL_POSITION;

