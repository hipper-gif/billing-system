-- companiesテーブルに企業補助額カラムを追加
-- MySQL 5.7互換

-- subsidy_amount (企業補助額)
ALTER TABLE companies ADD COLUMN subsidy_amount DECIMAL(10, 2) DEFAULT 0 COMMENT '企業補助額（1食あたり）' AFTER company_name;

-- 完了メッセージ
SELECT '企業補助額カラムの追加が完了しました。' as message;
