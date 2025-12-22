-- ordersテーブルに注文管理用のカラムを追加
-- 企業補助、ユーザー支払い額、注文ステータスなどを管理
-- MySQL 5.7互換

-- subsidy_amount (企業補助額)
-- 既存の場合はエラーになりますが、その場合は無視してください
ALTER TABLE orders ADD COLUMN subsidy_amount DECIMAL(10, 2) DEFAULT 0 COMMENT '企業補助額' AFTER total_amount;

-- user_payment_amount (ユーザー支払い額)
ALTER TABLE orders ADD COLUMN user_payment_amount DECIMAL(10, 2) DEFAULT 0 COMMENT 'ユーザー支払い額' AFTER subsidy_amount;

-- ordered_by_user_id (注文者ID - 代理注文の場合に使用)
ALTER TABLE orders ADD COLUMN ordered_by_user_id INT COMMENT '注文者ID' AFTER user_payment_amount;

-- order_type (注文タイプ: self=本人注文, proxy=代理注文)
ALTER TABLE orders ADD COLUMN order_type ENUM('self', 'proxy') DEFAULT 'self' COMMENT '注文タイプ' AFTER ordered_by_user_id;

-- order_status (注文ステータス: confirmed=確定, cancelled=キャンセル済み)
ALTER TABLE orders ADD COLUMN order_status ENUM('confirmed', 'cancelled', 'pending') DEFAULT 'confirmed' COMMENT '注文ステータス' AFTER order_type;

-- インデックスを追加
ALTER TABLE orders ADD INDEX idx_order_status (order_status);
ALTER TABLE orders ADD INDEX idx_ordered_by_user_id (ordered_by_user_id);

-- 完了メッセージ
SELECT '注文管理用カラムの追加が完了しました。' as message;
