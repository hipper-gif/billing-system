-- 既存のreceiptsテーブルにカラムを追加するマイグレーション
-- エラーが出る場合は、該当カラムが既に存在している可能性があります

-- descriptionカラムを追加
ALTER TABLE receipts
ADD COLUMN description VARCHAR(500) DEFAULT 'お弁当代として' COMMENT '但し書き' AFTER amount;

-- payment_method_displayカラムを追加
ALTER TABLE receipts
ADD COLUMN payment_method_display VARCHAR(50) COMMENT '支払方法表示' AFTER description;

-- issuer_nameカラムを追加
ALTER TABLE receipts
ADD COLUMN issuer_name VARCHAR(100) DEFAULT 'システム管理者' COMMENT '発行者名' AFTER payment_method_display;

-- 完了メッセージ
SELECT 'receiptsテーブルのカラム追加が完了しました。' as message;
