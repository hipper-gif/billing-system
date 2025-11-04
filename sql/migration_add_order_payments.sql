-- 注文ベースの入金管理テーブル
-- 別システムで請求書発行済みのため、本システムでは入金記録のみを管理

-- ============================================================================
-- 1. order_payments（入金記録）テーブル
-- ============================================================================
CREATE TABLE IF NOT EXISTS order_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT COMMENT '利用者ID（個人単位の場合）',
    user_code VARCHAR(50) COMMENT '利用者コード',
    user_name VARCHAR(100) COMMENT '利用者名',
    company_name VARCHAR(100) COMMENT '企業名（企業単位の場合）',
    payment_date DATE NOT NULL COMMENT '入金日',
    amount DECIMAL(12, 2) NOT NULL COMMENT '入金額',
    payment_method ENUM('cash', 'bank_transfer', 'account_debit', 'other') DEFAULT 'cash' COMMENT '支払方法',
    payment_type ENUM('individual', 'company') NOT NULL COMMENT '入金タイプ（個人/企業）',
    reference_number VARCHAR(100) COMMENT '振込番号等の参照番号',
    notes TEXT COMMENT '備考',
    created_by VARCHAR(50) DEFAULT 'system' COMMENT '登録者',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時',

    INDEX idx_user_id (user_id),
    INDEX idx_user_code (user_code),
    INDEX idx_company_name (company_name),
    INDEX idx_payment_date (payment_date),
    INDEX idx_payment_type (payment_type),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB COMMENT='注文ベースの入金記録';

-- ============================================================================
-- 2. order_payment_details（入金明細）テーブル
-- 入金と注文の紐付け、按分金額を管理
-- ============================================================================
CREATE TABLE IF NOT EXISTS order_payment_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payment_id INT NOT NULL COMMENT '入金記録ID',
    order_id INT NOT NULL COMMENT '注文ID',
    allocated_amount DECIMAL(12, 2) NOT NULL COMMENT '割り当て金額',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',

    FOREIGN KEY (payment_id) REFERENCES order_payments(id) ON DELETE CASCADE,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,

    INDEX idx_payment_id (payment_id),
    INDEX idx_order_id (order_id),
    UNIQUE KEY unique_payment_order (payment_id, order_id)
) ENGINE=InnoDB COMMENT='入金明細（注文との紐付け）';

-- ============================================================================
-- ビューの作成: 利用者別の入金状況
-- ============================================================================
CREATE OR REPLACE VIEW user_payment_summary AS
SELECT
    u.id as user_id,
    u.user_code,
    u.user_name,
    u.company_name,
    COUNT(DISTINCT o.id) as total_orders,
    COALESCE(SUM(o.total_amount), 0) as total_ordered,
    COALESCE(SUM(opd.allocated_amount), 0) as total_paid,
    (COALESCE(SUM(o.total_amount), 0) - COALESCE(SUM(opd.allocated_amount), 0)) as outstanding_amount
FROM users u
LEFT JOIN orders o ON u.id = o.user_id
LEFT JOIN order_payment_details opd ON o.id = opd.order_id
GROUP BY u.id, u.user_code, u.user_name, u.company_name;

-- ============================================================================
-- ビューの作成: 企業別の入金状況
-- ============================================================================
CREATE OR REPLACE VIEW company_payment_summary AS
SELECT
    o.company_name,
    COUNT(DISTINCT o.id) as total_orders,
    COUNT(DISTINCT o.user_id) as user_count,
    COALESCE(SUM(o.total_amount), 0) as total_ordered,
    COALESCE(SUM(opd.allocated_amount), 0) as total_paid,
    (COALESCE(SUM(o.total_amount), 0) - COALESCE(SUM(opd.allocated_amount), 0)) as outstanding_amount
FROM orders o
LEFT JOIN order_payment_details opd ON o.id = opd.order_id
WHERE o.company_name IS NOT NULL AND o.company_name != ''
GROUP BY o.company_name;

-- 完了メッセージ
SELECT '入金管理テーブルの作成が完了しました。' as message;
