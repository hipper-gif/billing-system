-- receiptsテーブルのinvoice_idをNULL許可に変更
-- 入金記録から直接領収書を発行する仕様に対応

-- 既存の外部キー制約を削除
ALTER TABLE receipts DROP FOREIGN KEY receipts_ibfk_1;

-- invoice_idをNULL許可に変更
ALTER TABLE receipts MODIFY COLUMN invoice_id INT NULL COMMENT '請求書ID（オプション）';

-- 外部キー制約を再追加（NULL許可）
ALTER TABLE receipts ADD CONSTRAINT receipts_ibfk_1
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE;

-- 完了メッセージ
SELECT 'receiptsテーブルのinvoice_idをNULL許可に変更しました。' as message;
