-- ============================================================================
-- Smiley配食事業 - usersテーブル認証関連カラム追加マイグレーション
-- ============================================================================
-- 目的: ログイン認証機能に必要なカラムをusersテーブルに追加
-- 作成日: 2025-12-20
-- 対応issue: ログイン認証問題の修正
-- ============================================================================

-- 文字セット設定
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- ============================================================================
-- 1. 認証関連カラムの追加
-- ============================================================================

-- password_hash カラム追加（ログインに必須）
ALTER TABLE users
ADD COLUMN IF NOT EXISTS password_hash VARCHAR(255) DEFAULT NULL COMMENT 'パスワードハッシュ';

-- company_id カラム追加（企業との紐付けに必須）
ALTER TABLE users
ADD COLUMN IF NOT EXISTS company_id INT DEFAULT NULL COMMENT '企業ID';

-- role カラム追加（権限管理に必須）
ALTER TABLE users
ADD COLUMN IF NOT EXISTS role ENUM('user', 'company_admin', 'smiley_staff', 'admin')
DEFAULT 'user' COMMENT 'ロール（user=一般利用者、company_admin=企業管理者、smiley_staff=Smileyスタッフ、admin=システム管理者）';

-- is_registered カラム追加（登録状態管理）
ALTER TABLE users
ADD COLUMN IF NOT EXISTS is_registered TINYINT(1) DEFAULT 0 COMMENT '本登録完了フラグ（0=未登録、1=登録済み）';

-- registered_at カラム追加（登録日時）
ALTER TABLE users
ADD COLUMN IF NOT EXISTS registered_at TIMESTAMP NULL DEFAULT NULL COMMENT '本登録完了日時';

-- last_login_at カラム追加（最終ログイン日時）
ALTER TABLE users
ADD COLUMN IF NOT EXISTS last_login_at TIMESTAMP NULL DEFAULT NULL COMMENT '最終ログイン日時';

-- ============================================================================
-- 2. 拡張機能用カラムの追加（オプション）
-- ============================================================================

-- department_id カラム追加（部署との紐付け）
ALTER TABLE users
ADD COLUMN IF NOT EXISTS department_id INT DEFAULT NULL COMMENT '部署ID';

-- employee_type_code カラム追加（従業員タイプコード）
ALTER TABLE users
ADD COLUMN IF NOT EXISTS employee_type_code VARCHAR(50) DEFAULT NULL COMMENT '従業員タイプコード';

-- employee_type_name カラム追加（従業員タイプ名）
ALTER TABLE users
ADD COLUMN IF NOT EXISTS employee_type_name VARCHAR(100) DEFAULT NULL COMMENT '従業員タイプ名';

-- ============================================================================
-- 3. インデックスの追加
-- ============================================================================

-- password_hash インデックス（既存チェック）
-- ALTER TABLE users ADD INDEX IF NOT EXISTS idx_password_hash (password_hash);

-- company_id インデックス
ALTER TABLE users ADD INDEX IF NOT EXISTS idx_company_id (company_id);

-- role インデックス
ALTER TABLE users ADD INDEX IF NOT EXISTS idx_role (role);

-- is_registered インデックス
ALTER TABLE users ADD INDEX IF NOT EXISTS idx_is_registered (is_registered);

-- department_id インデックス
ALTER TABLE users ADD INDEX IF NOT EXISTS idx_department_id (department_id);

-- ============================================================================
-- 4. 外部キー制約の追加（companiesテーブルとdepartmentsテーブルが存在する場合）
-- ============================================================================

-- company_id 外部キー制約（companiesテーブルが存在する場合のみ）
-- 注: companiesテーブルが存在しない場合はエラーになるため、手動で実行
-- ALTER TABLE users
-- ADD CONSTRAINT fk_users_company_id
-- FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL;

-- department_id 外部キー制約（departmentsテーブルが存在する場合のみ）
-- 注: departmentsテーブルが存在しない場合はエラーになるため、手動で実行
-- ALTER TABLE users
-- ADD CONSTRAINT fk_users_department_id
-- FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL;

-- ============================================================================
-- 5. 既存データの更新（必要に応じて）
-- ============================================================================

-- 既存のユーザーのroleをデフォルト値に設定（NULLの場合）
UPDATE users SET role = 'user' WHERE role IS NULL;

-- 既存のユーザーのis_registeredをデフォルト値に設定（NULLの場合）
UPDATE users SET is_registered = 0 WHERE is_registered IS NULL;

-- company_nameが設定されているユーザーのcompany_idを設定（companiesテーブルから）
-- 注: companiesテーブルが存在する場合のみ実行
-- UPDATE users u
-- INNER JOIN companies c ON u.company_name = c.company_name
-- SET u.company_id = c.id
-- WHERE u.company_id IS NULL AND u.company_name IS NOT NULL;

-- ============================================================================
-- 6. テスト用データの作成（開発環境のみ）
-- ============================================================================

-- テスト用ユーザー（Smileyスタッフ）
-- password: 'password123'
-- password_hash: $2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TukcvhqdVyO1N8hMp0i5GdBKqRQC
INSERT INTO users (
    user_code,
    user_name,
    company_name,
    department,
    password_hash,
    role,
    is_active,
    is_registered,
    registered_at
) VALUES (
    'Smiley0007',
    'テスト管理者',
    'Smiley配食事業',
    '管理部',
    '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TukcvhqdVyO1N8hMp0i5GdBKqRQC',
    'smiley_staff',
    1,
    1,
    NOW()
) ON DUPLICATE KEY UPDATE
    password_hash = VALUES(password_hash),
    role = VALUES(role),
    is_registered = VALUES(is_registered),
    registered_at = VALUES(registered_at);

-- ============================================================================
-- 7. マイグレーション完了確認
-- ============================================================================

-- usersテーブルの構造確認
SELECT
    'マイグレーション完了' as status,
    'usersテーブルに認証関連カラムが追加されました' as message;

-- カラム一覧表示
SHOW COLUMNS FROM users;

-- テストユーザーの確認
SELECT
    user_code,
    user_name,
    CASE WHEN password_hash IS NOT NULL THEN 'パスワード設定済み' ELSE 'パスワード未設定' END as password_status,
    role,
    is_registered,
    is_active
FROM users
WHERE user_code = 'Smiley0007';
