-- 領収書管理テーブル

-- ============================================================================
-- 1. receipts（領収書）テーブル
-- ============================================================================
CREATE TABLE IF NOT EXISTS receipts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    receipt_number VARCHAR(50) NOT NULL UNIQUE COMMENT '領収書番号（例: RCP-2025-00001）',
    payment_id INT NOT NULL COMMENT '入金記録ID',
    issue_date DATE NOT NULL COMMENT '発行日',
    recipient_name VARCHAR(200) NOT NULL COMMENT '宛名（個人名または企業名）',
    amount DECIMAL(12, 2) NOT NULL COMMENT '金額',
    description VARCHAR(500) DEFAULT 'お弁当代として' COMMENT '但し書き',
    payment_method_display VARCHAR(50) COMMENT '支払方法表示',
    issuer_name VARCHAR(100) DEFAULT 'システム管理者' COMMENT '発行者名',
    notes TEXT COMMENT '備考',
    created_by VARCHAR(50) DEFAULT 'system' COMMENT '登録者',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時',

    FOREIGN KEY (payment_id) REFERENCES order_payments(id) ON DELETE RESTRICT,

    INDEX idx_receipt_number (receipt_number),
    INDEX idx_payment_id (payment_id),
    INDEX idx_issue_date (issue_date),
    INDEX idx_recipient_name (recipient_name),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB COMMENT='領収書';

-- ============================================================================
-- 2. receipt_sequence（領収書番号採番）テーブル
-- 年ごとに連番をリセットする
-- ============================================================================
CREATE TABLE IF NOT EXISTS receipt_sequence (
    year INT NOT NULL PRIMARY KEY COMMENT '年度',
    last_number INT NOT NULL DEFAULT 0 COMMENT '最終採番',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時'
) ENGINE=InnoDB COMMENT='領収書番号採番管理';

-- 完了メッセージ
SELECT '領収書管理テーブルの作成が完了しました。' as message;
