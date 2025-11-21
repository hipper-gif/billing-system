-- Migration: Allow NULL values for payment_id and issue_date in receipts table
-- This enables pre-receipt issuance (printing receipts before payment collection)

-- Step 1: Drop the foreign key constraint temporarily
ALTER TABLE receipts DROP FOREIGN KEY IF EXISTS receipts_ibfk_1;
ALTER TABLE receipts DROP FOREIGN KEY IF EXISTS fk_receipts_payment;

-- Step 2: Modify payment_id to allow NULL
ALTER TABLE receipts
MODIFY COLUMN payment_id INT NULL COMMENT '入金記録ID（入金前領収書の場合はNULL）';

-- Step 3: Modify issue_date to allow NULL
ALTER TABLE receipts
MODIFY COLUMN issue_date DATE NULL COMMENT '発行日（入金前領収書の場合はNULL）';

-- Step 4: Re-add the foreign key constraint with ON DELETE SET NULL
ALTER TABLE receipts
ADD CONSTRAINT fk_receipts_payment
FOREIGN KEY (payment_id) REFERENCES payments(id)
ON DELETE SET NULL;
