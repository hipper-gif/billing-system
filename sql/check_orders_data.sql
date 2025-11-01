-- ===================================
-- ordersテーブルのデータ確認SQL
-- phpMyAdminで実行してください
-- ===================================

-- 1. 総データ数を確認
SELECT COUNT(*) as '総注文数' FROM orders;

-- 2. 最新5件のデータを確認
SELECT
    id,
    order_date,
    delivery_date,
    user_name,
    company_name,
    total_amount,
    created_at
FROM orders
ORDER BY created_at DESC
LIMIT 5;

-- 3. 月別のデータ数を確認
SELECT
    DATE_FORMAT(order_date, '%Y-%m') as '年月',
    COUNT(*) as '件数',
    SUM(total_amount) as '合計金額'
FROM orders
GROUP BY DATE_FORMAT(order_date, '%Y-%m')
ORDER BY '年月' DESC;

-- 4. 今月のデータを確認
SELECT
    COUNT(*) as '今月の注文数',
    SUM(total_amount) as '今月の合計金額'
FROM orders
WHERE order_date BETWEEN DATE_FORMAT(NOW(), '%Y-%m-01') AND LAST_DAY(NOW());

-- 5. 最新のインポートバッチを確認
SELECT
    import_batch_id,
    COUNT(*) as '件数',
    MIN(created_at) as '最初の登録',
    MAX(created_at) as '最後の登録'
FROM orders
WHERE import_batch_id IS NOT NULL
GROUP BY import_batch_id
ORDER BY MAX(created_at) DESC
LIMIT 5;
