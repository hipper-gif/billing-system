-- receiptsテーブルのpayment_id外部キー制約を修正
-- paymentsテーブルではなく、order_paymentsテーブルを参照するように変更

-- 既存の外部キー制約を削除
ALTER TABLE receipts DROP FOREIGN KEY fk_receipts_payment;

-- 正しいテーブル（order_payments）を参照する外部キー制約を追加
ALTER TABLE receipts ADD CONSTRAINT fk_receipts_payment
    FOREIGN KEY (payment_id) REFERENCES order_payments(id) ON DELETE SET NULL;

-- 完了メッセージ
SELECT 'receiptsテーブルのpayment_id外部キー制約をorder_paymentsテーブル参照に修正しました。' as message;
