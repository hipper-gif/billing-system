-- ============================================================================
-- Smiley9999 管理者アカウント設定SQL
-- ============================================================================
-- 目的: 実際の管理者アカウント（Smiley9999）のパスワード設定
-- 実行日: 2025-12-20
-- ============================================================================

-- 文字セット設定
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- ============================================================================
-- 方法1: デフォルトパスワードで設定（後で変更推奨）
-- ============================================================================

-- パスワード: admin9999
-- ※セキュリティのため、設定後すぐに変更してください
UPDATE users
SET password_hash = '$2y$12$qOXW0dVp0YhvKwPnBjUHOONH8vMJiG.hW2jXKJGZQZxEbQK7GxJ8K',
    role = 'admin',
    is_registered = 1,
    is_active = 1,
    registered_at = COALESCE(registered_at, NOW()),
    updated_at = NOW()
WHERE user_code = 'Smiley9999';

-- ============================================================================
-- または、カスタムパスワードを使用する場合
-- ============================================================================

-- 以下のPHPコードでカスタムパスワードのハッシュを生成:
-- <?php echo password_hash('your_password', PASSWORD_BCRYPT, ['cost' => 12]); ?>
--
-- 生成されたハッシュで以下を実行:
-- UPDATE users
-- SET password_hash = '生成されたハッシュ',
--     role = 'admin',
--     is_registered = 1,
--     is_active = 1
-- WHERE user_code = 'Smiley9999';

-- ============================================================================
-- 設定後の確認
-- ============================================================================

SELECT
    '=== Smiley9999 設定状況 ===' as status,
    user_code,
    user_name,
    company_name,
    CASE WHEN password_hash IS NOT NULL THEN 'パスワード設定済み' ELSE 'パスワード未設定' END as password_status,
    role,
    is_registered,
    is_active
FROM users
WHERE user_code = 'Smiley9999';

-- ============================================================================
-- ログイン情報
-- ============================================================================

SELECT
    '=== ログイン情報 ===' as info,
    'Smiley9999' as user_code,
    'admin9999' as default_password,
    '※ ログイン後すぐにパスワードを変更してください' as warning;

-- ============================================================================
-- 補足: すべてのユーザーの状態確認
-- ============================================================================

SELECT
    user_code,
    user_name,
    CASE WHEN password_hash IS NOT NULL THEN '✓' ELSE '✗' END as pwd,
    role,
    is_active
FROM users
ORDER BY
    CASE
        WHEN user_code LIKE 'Smiley%' THEN 0
        ELSE 1
    END,
    user_code;
