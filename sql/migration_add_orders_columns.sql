-- ordersテーブルに不足しているカラムを追加
-- CSVインポート処理で使用されるカラムを追加
-- MySQL 5.7互換

-- delivery_date (配達日)
ALTER TABLE orders ADD COLUMN delivery_date DATE COMMENT '配達日' AFTER order_date;

-- 企業関連
ALTER TABLE orders ADD COLUMN company_id INT COMMENT '企業ID' AFTER user_name;
ALTER TABLE orders ADD COLUMN company_code VARCHAR(50) COMMENT '企業コード' AFTER company_id;
ALTER TABLE orders ADD COLUMN company_name VARCHAR(100) COMMENT '企業名' AFTER company_code;

-- 部署関連
ALTER TABLE orders ADD COLUMN department_id INT COMMENT '部署ID' AFTER company_name;

-- カテゴリ関連
ALTER TABLE orders ADD COLUMN category_code VARCHAR(50) COMMENT 'カテゴリコード' AFTER product_name;
ALTER TABLE orders ADD COLUMN category_name VARCHAR(100) COMMENT 'カテゴリ名' AFTER category_code;

-- 仕入先関連
ALTER TABLE orders ADD COLUMN supplier_id INT COMMENT '仕入先ID' AFTER category_name;

-- 法人・社員区分
ALTER TABLE orders ADD COLUMN corporation_code VARCHAR(50) COMMENT '法人コード' AFTER supplier_name;
ALTER TABLE orders ADD COLUMN corporation_name VARCHAR(100) COMMENT '法人名' AFTER corporation_code;
ALTER TABLE orders ADD COLUMN employee_type_code VARCHAR(50) COMMENT '社員区分コード' AFTER corporation_name;
ALTER TABLE orders ADD COLUMN employee_type_name VARCHAR(100) COMMENT '社員区分名' AFTER employee_type_code;

-- その他
ALTER TABLE orders ADD COLUMN delivery_time VARCHAR(20) COMMENT '配達時間' AFTER department_name;
ALTER TABLE orders ADD COLUMN cooperation_code VARCHAR(50) COMMENT '協力コード' AFTER delivery_time;

-- インデックスを追加
ALTER TABLE orders ADD INDEX idx_delivery_date (delivery_date);
ALTER TABLE orders ADD INDEX idx_company_id (company_id);
ALTER TABLE orders ADD INDEX idx_department_id (department_id);
ALTER TABLE orders ADD INDEX idx_supplier_id (supplier_id);

-- 外部キー制約を追加
-- 注意: すでに外部キーが存在する場合はエラーになります
-- その場合は、この部分をコメントアウトしてください

-- ALTER TABLE orders ADD CONSTRAINT fk_orders_company
--   FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL;

-- ALTER TABLE orders ADD CONSTRAINT fk_orders_department
--   FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL;

-- ALTER TABLE orders ADD CONSTRAINT fk_orders_supplier
--   FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL;

