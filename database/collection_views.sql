-- Smiley配食事業 集金管理システム専用 VIEW定義
-- 作成日: 2025年9月19日
-- 目的: 集金業務に特化したデータ統合・効率化

-- =====================================================
-- 1. collection_status_view (集金状況統合VIEW)
-- 目的: 企業・請求・支払データを統合して集金状況を一覧表示
-- =====================================================

CREATE OR REPLACE VIEW collection_status_view AS
SELECT 
    -- 企業情報
    c.id as company_id,
    c.company_name,
    c.contact_person,
    c.phone,
    c.address,
    c.delivery_location,
    c.delivery_instructions,
    c.access_instructions,
    
    -- 請求書情報
    i.id as invoice_id,
    i.invoice_number,
    i.total_amount,
    i.due_date,
    i.status as invoice_status,
    i.issue_date,
    
    -- 支払い情報（集計）
    COALESCE(SUM(p.amount), 0) as paid_amount,
    (i.total_amount - COALESCE(SUM(p.amount), 0)) as outstanding_amount,
    
    -- アラートレベル自動判定
    CASE 
        WHEN i.due_date < CURDATE() THEN 'overdue'
        WHEN i.due_date <= DATE_ADD(CURDATE(), INTERVAL 3 DAY) THEN 'urgent'  
        ELSE 'normal'
    END as alert_level,
    
    -- 期限切れ日数計算
    CASE
        WHEN i.due_date < CURDATE() THEN DATEDIFF(CURDATE(), i.due_date)
        ELSE 0
    END as overdue_days,
    
    -- 期限までの残り日数
    DATEDIFF(i.due_date, CURDATE()) as days_until_due,
    
    -- 支払い状況判定
    CASE
        WHEN COALESCE(SUM(p.amount), 0) = 0 THEN 'unpaid'
        WHEN COALESCE(SUM(p.amount), 0) >= i.total_amount THEN 'paid'
        ELSE 'partially_paid'
    END as payment_status,
    
    -- 最新支払日
    MAX(p.payment_date) as last_payment_date,
    
    -- 支払件数
    COUNT(p.id) as payment_count

FROM companies c
JOIN invoices i ON c.id = i.company_id
LEFT JOIN payments p ON i.id = p.invoice_id

-- 未回収がある請求書のみ表示（集金業務対象）
WHERE i.status IN ('issued', 'partially_paid')
  AND (i.total_amount - COALESCE(SUM(p.amount), 0)) > 0

GROUP BY c.id, i.id

-- 優先度順でソート（期限切れ→期限間近→通常、期限順）
ORDER BY 
    CASE alert_level
        WHEN 'overdue' THEN 1
        WHEN 'urgent' THEN 2  
        ELSE 3
    END,
    i.due_date ASC,
    outstanding_amount DESC;

-- =====================================================
-- 2. collection_statistics_view (集金統計VIEW)
-- 目的: 月別・年別の集金統計情報を自動計算
-- =====================================================

CREATE OR REPLACE VIEW collection_statistics_view AS
SELECT 
    -- 集計期間
    DATE_FORMAT(i.issue_date, '%Y-%m') as month,
    YEAR(i.issue_date) as year,
    MONTH(i.issue_date) as month_num,
    
    -- 請求書統計
    COUNT(i.id) as total_invoices,
    SUM(i.total_amount) as total_amount,
    
    -- 支払い統計
    SUM(CASE WHEN i.status = 'paid' THEN i.total_amount ELSE 0 END) as collected_amount,
    SUM(CASE WHEN i.status != 'paid' THEN i.total_amount ELSE 0 END) as outstanding_amount,
    
    -- 回収率計算
    ROUND(
        SUM(CASE WHEN i.status = 'paid' THEN i.total_amount ELSE 0 END) / 
        SUM(i.total_amount) * 100, 
        1
    ) as collection_rate,
    
    -- 期限切れ統計
    COUNT(CASE WHEN i.due_date < CURDATE() AND i.status != 'paid' THEN 1 END) as overdue_count,
    SUM(CASE WHEN i.due_date < CURDATE() AND i.status != 'paid' THEN i.total_amount ELSE 0 END) as overdue_amount,
    
    -- 企業数統計
    COUNT(DISTINCT i.company_id) as total_companies,
    COUNT(DISTINCT CASE WHEN i.status != 'paid' THEN i.company_id END) as companies_with_outstanding

FROM invoices i
WHERE i.issue_date >= DATE_SUB(CURDATE(), INTERVAL 24 MONTH)  -- 過去2年分
GROUP BY DATE_FORMAT(i.issue_date, '%Y-%m')
ORDER BY month DESC;

-- =====================================================
-- 3. payment_methods_summary_view (支払方法別統計VIEW)
-- 目的: 支払方法別の統計情報（PayPay対応含む）
-- =====================================================

CREATE OR REPLACE VIEW payment_methods_summary_view AS
SELECT 
    p.payment_method,
    CASE p.payment_method
        WHEN 'cash' THEN '💵 現金'
        WHEN 'bank_transfer' THEN '🏦 銀行振込'
        WHEN 'paypay' THEN '📱 PayPay'
        WHEN 'account_debit' THEN '🏦 口座引き落とし'
        WHEN 'mixed' THEN '💳 混合'
        ELSE '💼 その他'
    END as payment_method_display,
    
    -- 件数・金額統計
    COUNT(*) as payment_count,
    SUM(p.amount) as total_amount,
    AVG(p.amount) as average_amount,
    MIN(p.amount) as min_amount,
    MAX(p.amount) as max_amount,
    
    -- 時系列統計
    MIN(p.payment_date) as first_payment_date,
    MAX(p.payment_date) as last_payment_date,
    
    -- 企業数統計
    COUNT(DISTINCT i.company_id) as companies_count

FROM payments p
JOIN invoices i ON p.invoice_id = i.id
WHERE p.payment_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)  -- 過去1年分
GROUP BY p.payment_method
ORDER BY total_amount DESC;

-- =====================================================
-- 4. urgent_collection_alerts_view (緊急回収アラートVIEW)
-- 目的: 緊急対応が必要な案件を優先度付きで表示
-- =====================================================

CREATE OR REPLACE VIEW urgent_collection_alerts_view AS
SELECT 
    csv.*,
    
    -- 緊急度レベル（数値）
    CASE csv.alert_level
        WHEN 'overdue' THEN 
            CASE 
                WHEN csv.overdue_days > 30 THEN 4  -- Critical（30日超過）
                WHEN csv.overdue_days > 14 THEN 3  -- High（2週間超過）
                ELSE 2                             -- Medium（期限切れ）
            END
        WHEN 'urgent' THEN 1                      -- Low（期限間近）
        ELSE 0                                     -- Normal
    END as urgency_level,
    
    -- 緊急度表示
    CASE csv.alert_level
        WHEN 'overdue' THEN 
            CASE 
                WHEN csv.overdue_days > 30 THEN '🚨 Critical'
                WHEN csv.overdue_days > 14 THEN '🔴 High'
                ELSE '🟡 Medium'
            END
        WHEN 'urgent' THEN '🟠 Low'
        ELSE '🟢 Normal'
    END as urgency_display,
    
    -- 優先度スコア（期限切れ日数 + 金額による重み付け）
    (
        CASE csv.alert_level
            WHEN 'overdue' THEN csv.overdue_days * 10
            WHEN 'urgent' THEN 5
            ELSE 1
        END +
        CASE 
            WHEN csv.outstanding_amount >= 100000 THEN 50
            WHEN csv.outstanding_amount >= 50000 THEN 30
            WHEN csv.outstanding_amount >= 20000 THEN 10
            ELSE 1
        END
    ) as priority_score

FROM collection_status_view csv
WHERE csv.alert_level IN ('overdue', 'urgent')
ORDER BY priority_score DESC, csv.outstanding_amount DESC;

-- =====================================================
-- 5. daily_collection_schedule_view (日別集金予定VIEW)
-- 目的: 今日・明日・今週の集金予定を表示
-- =====================================================

CREATE OR REPLACE VIEW daily_collection_schedule_view AS
SELECT 
    csv.*,
    
    -- 予定区分
    CASE 
        WHEN csv.due_date = CURDATE() THEN 'today'
        WHEN csv.due_date = DATE_ADD(CURDATE(), INTERVAL 1 DAY) THEN 'tomorrow'
        WHEN csv.due_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 'this_week'
        ELSE 'later'
    END as schedule_category,
    
    -- 予定区分表示
    CASE 
        WHEN csv.due_date = CURDATE() THEN '🎯 今日'
        WHEN csv.due_date = DATE_ADD(CURDATE(), INTERVAL 1 DAY) THEN '📅 明日'
        WHEN csv.due_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN '📆 今週'
        ELSE '🗓️ 来週以降'
    END as schedule_display,
    
    -- 曜日
    DAYOFWEEK(csv.due_date) as day_of_week,
    CASE DAYOFWEEK(csv.due_date)
        WHEN 1 THEN '日'
        WHEN 2 THEN '月'
        WHEN 3 THEN '火'
        WHEN 4 THEN '水'
        WHEN 5 THEN '木'
        WHEN 6 THEN '金'
        WHEN 7 THEN '土'
    END as day_of_week_jp

FROM collection_status_view csv
WHERE csv.due_date <= DATE_ADD(CURDATE(), INTERVAL 14 DAY)  -- 2週間以内
ORDER BY csv.due_date ASC, csv.outstanding_amount DESC;

-- =====================================================
-- インデックス作成（パフォーマンス最適化）
-- =====================================================

-- 既存テーブルのインデックス最適化
CREATE INDEX IF NOT EXISTS idx_invoices_status_due ON invoices(status, due_date);
CREATE INDEX IF NOT EXISTS idx_payments_invoice_date ON payments(invoice_id, payment_date);
CREATE INDEX IF NOT EXISTS idx_companies_active ON companies(is_active);
CREATE INDEX IF NOT EXISTS idx_invoices_company_issue ON invoices(company_id, issue_date);

-- =====================================================
-- VIEW作成確認用クエリ（テスト用）
-- =====================================================

-- 作成したVIEWの確認
-- SELECT 'collection_status_view' as view_name, COUNT(*) as record_count FROM collection_status_view
-- UNION ALL
-- SELECT 'collection_statistics_view' as view_name, COUNT(*) as record_count FROM collection_statistics_view  
-- UNION ALL
-- SELECT 'payment_methods_summary_view' as view_name, COUNT(*) as record_count FROM payment_methods_summary_view
-- UNION ALL
-- SELECT 'urgent_collection_alerts_view' as view_name, COUNT(*) as record_count FROM urgent_collection_alerts_view
-- UNION ALL
-- SELECT 'daily_collection_schedule_view' as view_name, COUNT(*) as record_count FROM daily_collection_schedule_view;
