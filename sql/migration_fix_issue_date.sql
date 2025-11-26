-- Migration Fix: issue_date カラムをNULL許可に変更
-- payment_idは既に変更済みなので、issue_dateのみを変更

ALTER TABLE receipts
MODIFY COLUMN issue_date DATE NULL COMMENT '発行日（入金前領収書の場合はNULL）';
