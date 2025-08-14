-- Smiley配食事業 請求書・集金管理システム
-- データベース初期化スクリプト

-- 文字セット設定
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- ============================================================================
-- 1. users（利用者）テーブル
-- ============================================================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_code VARCHAR(50) UNIQUE NOT NULL COMMENT '利用者コード',
    user_name VARCHAR(100) NOT NULL COMMENT '利用者名',
    company_name VARCHAR(100) COMMENT '会社名',
    department VARCHAR(100) COMMENT '部署名',
    email VARCHAR(255) COMMENT 'メールアドレス',
    phone VARCHAR(20) COMMENT '電話番号',
    address TEXT COMMENT '住所',
    payment_method ENUM('cash', 'bank_transfer', 'account_debit', 'mixed') DEFAULT 'cash' COMMENT '支払い方法',
    is_active BOOLEAN DEFAULT TRUE COMMENT '有効フラグ',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時',
    INDEX idx_user_code (user_code),
    INDEX idx_company_name (company_name),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB COMMENT='利用者マスタ';

-- ============================================================================
-- 2. products（商品）テーブル
-- ============================================================================
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_code VARCHAR(50) UNIQUE NOT NULL COMMENT '商品コード',
    product_name VARCHAR(200) NOT NULL COMMENT '商品名',
    category VARCHAR(50) COMMENT 'カテゴリ',
    default_price DECIMAL(10, 2) COMMENT 'デフォルト価格',
    description TEXT COMMENT '商品説明',
    is_active BOOLEAN DEFAULT TRUE COMMENT '有効フラグ',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時',
    INDEX idx_product_code (product_code),
    INDEX idx_category (category),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB COMMENT='商品マスタ';

-- ============================================================================
-- 3. orders（注文）テーブル
-- ============================================================================
CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_date DATE NOT NULL COMMENT '注文日',
    user_id INT COMMENT '利用者ID（外部キー）',
    user_code VARCHAR(50) NOT NULL COMMENT '利用者コード',
    user_name VARCHAR(100) NOT NULL COMMENT '利用者名',
    product_id INT COMMENT '商品ID（外部キー）',
    product_code VARCHAR(50) NOT NULL COMMENT '商品コード',
    product_name VARCHAR(200) NOT NULL COMMENT '商品名',
    quantity INT NOT NULL DEFAULT 1 COMMENT '数量',
    unit_price DECIMAL(10, 2) NOT NULL COMMENT '単価',
    total_amount DECIMAL(10, 2) NOT NULL COMMENT '金額',
    supplier_code VARCHAR(50) COMMENT '仕入先コード',
    supplier_name VARCHAR(100) COMMENT '仕入先名',
    department_code VARCHAR(50) COMMENT '部署コード',
    department_name VARCHAR(100) COMMENT '部署名',
    import_batch_id VARCHAR(100) COMMENT 'インポートバッチID',
    notes TEXT COMMENT '備考',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時',
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
    
    INDEX idx_order_date (order_date),
    INDEX idx_user_id (user_id),
    INDEX idx_user_code (user_code),
    INDEX idx_product_id (product_id),
    INDEX idx_batch_id (import_batch_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB COMMENT='注文データ';

-- ============================================================================
-- 4. invoices（請求書）テーブル
-- ============================================================================
CREATE TABLE IF NOT EXISTS invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(50) UNIQUE NOT NULL COMMENT '請求書番号',
    user_id INT NOT NULL COMMENT '利用者ID',
    user_code VARCHAR(50) NOT NULL COMMENT '利用者コード',
    user_name VARCHAR(100) NOT NULL COMMENT '利用者名',
    company_name VARCHAR(100) COMMENT '会社名',
    department VARCHAR(100) COMMENT '部署名',
    invoice_date DATE NOT NULL COMMENT '請求日',
    due_date DATE NOT NULL COMMENT '支払期限',
    period_start DATE NOT NULL COMMENT '請求期間開始',
    period_end DATE NOT NULL COMMENT '請求期間終了',
    subtotal DECIMAL(12, 2) NOT NULL DEFAULT 0 COMMENT '小計',
    tax_rate DECIMAL(5, 2) DEFAULT 10.00 COMMENT '消費税率',
    tax_amount DECIMAL(12, 2) NOT NULL DEFAULT 0 COMMENT '消費税額',
    total_amount DECIMAL(12, 2) NOT NULL DEFAULT 0 COMMENT '合計金額',
    invoice_type ENUM('company', 'individual', 'mixed') DEFAULT 'company' COMMENT '請求書タイプ',
    status ENUM('draft', 'issued', 'paid', 'overdue', 'cancelled') DEFAULT 'draft' COMMENT 'ステータス',
    payment_method ENUM('cash', 'bank_transfer', 'account_debit', 'mixed') COMMENT '支払い方法',
    notes TEXT COMMENT '備考',
    file_path VARCHAR(500) COMMENT 'PDFファイルパス',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時',
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    
    INDEX idx_invoice_number (invoice_number),
    INDEX idx_user_id (user_id),
    INDEX idx_invoice_date (invoice_date),
    INDEX idx_due_date (due_date),
    INDEX idx_status (status),
    INDEX idx_period (period_start, period_end)
) ENGINE=InnoDB COMMENT='請求書';

-- ============================================================================
-- 5. invoice_details（請求書明細）テーブル
-- ============================================================================
CREATE TABLE IF NOT EXISTS invoice_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL COMMENT '請求書ID',
    order_id INT NOT NULL COMMENT '注文ID',
    order_date DATE NOT NULL COMMENT '注文日',
    product_code VARCHAR(50) NOT NULL COMMENT '商品コード',
    product_name VARCHAR(200) NOT NULL COMMENT '商品名',
    quantity INT NOT NULL COMMENT '数量',
    unit_price DECIMAL(10, 2) NOT NULL COMMENT '単価',
    amount DECIMAL(10, 2) NOT NULL COMMENT '金額',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
    
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    
    INDEX idx_invoice_id (invoice_id),
    INDEX idx_order_id (order_id),
    INDEX idx_order_date (order_date)
) ENGINE=InnoDB COMMENT='請求書明細';

-- ============================================================================
-- 6. payments（支払い記録）テーブル
-- ============================================================================
CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL COMMENT '請求書ID',
    payment_date DATE NOT NULL COMMENT '支払日',
    amount DECIMAL(12, 2) NOT NULL COMMENT '支払金額',
    payment_method ENUM('cash', 'bank_transfer', 'account_debit', 'other') NOT NULL COMMENT '支払方法',
    payment_status ENUM('completed', 'pending', 'failed') DEFAULT 'completed' COMMENT '支払ステータス',
    reference_number VARCHAR(100) COMMENT '振込番号等の参照番号',
    notes TEXT COMMENT '備考',
    created_by VARCHAR(50) COMMENT '登録者',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時',
    
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
    
    INDEX idx_invoice_id (invoice_id),
    INDEX idx_payment_date (payment_date),
    INDEX idx_payment_method (payment_method),
    INDEX idx_payment_status (payment_status)
) ENGINE=InnoDB COMMENT='支払い記録';

-- ============================================================================
-- 7. receipts（領収書）テーブル
-- ============================================================================
CREATE TABLE IF NOT EXISTS receipts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    receipt_number VARCHAR(50) UNIQUE NOT NULL COMMENT '領収書番号',
    invoice_id INT NOT NULL COMMENT '請求書ID',
    payment_id INT COMMENT '支払いID',
    issue_date DATE NOT NULL COMMENT '発行日',
    amount DECIMAL(12, 2) NOT NULL COMMENT '金額',
    tax_amount DECIMAL(12, 2) DEFAULT 0 COMMENT '消費税額',
    recipient_name VARCHAR(200) NOT NULL COMMENT '受領者名',
    purpose VARCHAR(200) DEFAULT 'お弁当代として' COMMENT '但し書き',
    stamp_required BOOLEAN DEFAULT FALSE COMMENT '収入印紙要否',
    stamp_amount DECIMAL(8, 2) DEFAULT 0 COMMENT '印紙代',
    receipt_type ENUM('advance', 'payment', 'split') DEFAULT 'payment' COMMENT '領収書タイプ',
    status ENUM('draft', 'issued', 'delivered') DEFAULT 'draft' COMMENT 'ステータス',
    file_path VARCHAR(500) COMMENT 'PDFファイルパス',
    notes TEXT COMMENT '備考',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時',
    
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
    FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE SET NULL,
    
    INDEX idx_receipt_number (receipt_number),
    INDEX idx_invoice_id (invoice_id),
    INDEX idx_issue_date (issue_date),
    INDEX idx_status (status)
) ENGINE=InnoDB COMMENT='領収書';

-- ============================================================================
-- 8. import_logs（インポートログ）テーブル
-- ============================================================================
CREATE TABLE IF NOT EXISTS import_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    batch_id VARCHAR(100) UNIQUE NOT NULL COMMENT 'バッチID',
    file_name VARCHAR(255) NOT NULL COMMENT 'ファイル名',
    file_type ENUM('order_summary', 'detail_list', 'other') NOT NULL COMMENT 'ファイルタイプ',
    file_size INT COMMENT 'ファイルサイズ',
    encoding VARCHAR(20) COMMENT '文字エンコーディング',
    total_records INT NOT NULL DEFAULT 0 COMMENT '総レコード数',
    success_records INT NOT NULL DEFAULT 0 COMMENT '成功レコード数',
    error_records INT NOT NULL DEFAULT 0 COMMENT 'エラーレコード数',
    duplicate_records INT NOT NULL DEFAULT 0 COMMENT '重複レコード数',
    import_start TIMESTAMP COMMENT 'インポート開始時刻',
    import_end TIMESTAMP COMMENT 'インポート終了時刻',
    status ENUM('processing', 'completed', 'failed', 'cancelled') DEFAULT 'processing' COMMENT 'ステータス',
    error_details TEXT COMMENT 'エラー詳細',
    created_by VARCHAR(50) COMMENT '実行者',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
    
    INDEX idx_batch_id (batch_id),
    INDEX idx_file_type (file_type),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB COMMENT='インポートログ';

-- ============================================================================
-- 9. system_settings（システム設定）テーブル
-- ============================================================================
CREATE TABLE IF NOT EXISTS system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL COMMENT '設定キー',
    setting_value TEXT COMMENT '設定値',
    setting_type ENUM('string', 'integer', 'decimal', 'boolean', 'json') DEFAULT 'string' COMMENT '設定タイプ',
    description TEXT COMMENT '設定説明',
    category VARCHAR(50) COMMENT 'カテゴリ',
    is_editable BOOLEAN DEFAULT TRUE COMMENT '編集可能フラグ',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時',
    
    INDEX idx_setting_key (setting_key),
    INDEX idx_category (category)
) ENGINE=InnoDB COMMENT='システム設定';

-- ============================================================================
-- 初期データ投入
-- ============================================================================

-- システム設定の初期データ
INSERT IGNORE INTO system_settings (setting_key, setting_value, setting_type, description, category) VALUES
('company_name', 'Smiley配食事業', 'string', '会社名', 'company'),
('company_address', '〒000-0000 東京都○○区○○', 'string', '会社住所', 'company'),
('company_phone', '03-0000-0000', 'string', '会社電話番号', 'company'),
('company_email', 'info@smiley-meal.com', 'string', '会社メールアドレス', 'company'),
('tax_rate', '10.00', 'decimal', '消費税率', 'invoice'),
('invoice_prefix', 'SM-', 'string', '請求書番号プレフィックス', 'invoice'),
('receipt_prefix', 'REC-', 'string', '領収書番号プレフィックス', 'receipt'),
('payment_due_days', '30', 'integer', '支払期限日数', 'payment'),
('stamp_threshold', '50000', 'decimal', '収入印紙必要金額', 'receipt'),
('backup_retention_days', '90', 'integer', 'バックアップ保持日数', 'system'),
('csv_encoding', 'SJIS-win', 'string', 'CSVファイルデフォルトエンコーディング', 'import'),
('max_upload_size', '10485760', 'integer', '最大アップロードサイズ（バイト）', 'system'),
('session_timeout', '3600', 'integer', 'セッションタイムアウト（秒）', 'system'),
('debug_mode', 'false', 'boolean', 'デバッグモード', 'system');

-- テスト用の商品データ
INSERT IGNORE INTO products (product_code, product_name, category, default_price, description) VALUES
('BENTO001', 'お弁当A（日替わり）', '日替わり弁当', 600.00, '日替わりおかずのお弁当'),
('BENTO002', 'お弁当B（唐揚げ）', '定番弁当', 650.00, '唐揚げがメインのお弁当'),
('BENTO003', 'お弁当C（焼き魚）', '定番弁当', 680.00, '焼き魚がメインのお弁当'),
('DRINK001', 'お茶', '飲み物', 120.00, 'ペットボトルお茶'),
('DRINK002', 'コーヒー', '飲み物', 150.00, 'ペットボトルコーヒー'),
('SIDE001', 'サラダ', 'サイドメニュー', 200.00, '野菜サラダ');

-- テスト用の利用者データ
INSERT IGNORE INTO users (user_code, user_name, company_name, department, email, payment_method) VALUES
('U001', '田中太郎', 'サンプル株式会社', '営業部', 'tanaka@sample.co.jp', 'cash'),
('U002', '佐藤花子', 'サンプル株式会社', '経理部', 'sato@sample.co.jp', 'bank_transfer'),
('U003', '鈴木一郎', 'テスト会社', '開発部', 'suzuki@test.co.jp', 'cash'),
('U004', '高橋美和', 'テスト会社', '総務部', 'takahashi@test.co.jp', 'account_debit'),
('U005', '山田次郎', '個人', '', 'yamada@personal.jp', 'cash');

-- テスト用の注文データ（直近1ヶ月分）
INSERT IGNORE INTO orders (order_date, user_code, user_name, product_code, product_name, quantity, unit_price, total_amount, import_batch_id) VALUES
-- 今月のテストデータ
(CURDATE() - INTERVAL 1 DAY, 'U001', '田中太郎', 'BENTO001', 'お弁当A（日替わり）', 1, 600.00, 600.00, 'TEST_BATCH_001'),
(CURDATE() - INTERVAL 1 DAY, 'U001', '田中太郎', 'DRINK001', 'お茶', 1, 120.00, 120.00, 'TEST_BATCH_001'),
(CURDATE() - INTERVAL 2 DAY, 'U002', '佐藤花子', 'BENTO002', 'お弁当B（唐揚げ）', 1, 650.00, 650.00, 'TEST_BATCH_001'),
(CURDATE() - INTERVAL 3 DAY, 'U003', '鈴木一郎', 'BENTO003', 'お弁当C（焼き魚）', 1, 680.00, 680.00, 'TEST_BATCH_001'),
(CURDATE() - INTERVAL 3 DAY, 'U003', '鈴木一郎', 'SIDE001', 'サラダ', 1, 200.00, 200.00, 'TEST_BATCH_001'),
(CURDATE() - INTERVAL 5 DAY, 'U004', '高橋美和', 'BENTO001', 'お弁当A（日替わり）', 2, 600.00, 1200.00, 'TEST_BATCH_001'),
(CURDATE() - INTERVAL 7 DAY, 'U005', '山田次郎', 'BENTO002', 'お弁当B（唐揚げ）', 1, 650.00, 650.00, 'TEST_BATCH_001');

-- テスト用のインポートログ
INSERT IGNORE INTO import_logs (batch_id, file_name, file_type, total_records, success_records, error_records, duplicate_records, import_start, import_end, status, created_by) VALUES
('TEST_BATCH_001', 'test_orders.csv', 'order_summary', 7, 7, 0, 0, NOW() - INTERVAL 1 HOUR, NOW() - INTERVAL 59 MINUTE, 'completed', 'system');

-- ============================================================================
-- ビューの作成
-- ============================================================================

-- 利用者別売上集計ビュー
CREATE OR REPLACE VIEW user_sales_summary AS
SELECT 
    u.id,
    u.user_code,
    u.user_name,
    u.company_name,
    u.department,
    COUNT(o.id) as order_count,
    SUM(o.total_amount) as total_amount,
    MAX(o.order_date) as last_order_date,
    u.payment_method
FROM users u
LEFT JOIN orders o ON u.user_code = o.user_code
GROUP BY u.id, u.user_code, u.user_name, u.company_name, u.department, u.payment_method;

-- 月次売上集計ビュー
CREATE OR REPLACE VIEW monthly_sales_summary AS
SELECT 
    DATE_FORMAT(order_date, '%Y-%m') as month,
    COUNT(*) as order_count,
    SUM(total_amount) as total_amount,
    COUNT(DISTINCT user_code) as unique_users
FROM orders
GROUP BY DATE_FORMAT(order_date, '%Y-%m')
ORDER BY month DESC;

-- 請求書ステータス集計ビュー
CREATE OR REPLACE VIEW invoice_status_summary AS
SELECT 
    status,
    COUNT(*) as count,
    SUM(total_amount) as total_amount
FROM invoices
GROUP BY status;

-- ============================================================================
-- 動作確認用クエリ（コメントアウト）
-- ============================================================================

/*
-- データベース構築完了後の確認クエリ

-- 1. テーブル一覧確認
SHOW TABLES;

-- 2. 利用者データ確認
SELECT * FROM users;

-- 3. 商品データ確認
SELECT * FROM products;

-- 4. 注文データ確認
SELECT * FROM orders;

-- 5. 利用者別売上確認
SELECT * FROM user_sales_summary;

-- 6. 月次売上確認
SELECT * FROM monthly_sales_summary;

-- 7. システム設定確認
SELECT * FROM system_settings;

-- 8. インポートログ確認
SELECT * FROM import_logs;
*/

-- ============================================================================
-- 完了メッセージ
-- ============================================================================
SELECT 'データベース初期化が完了しました。' as message;