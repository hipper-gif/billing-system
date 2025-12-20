# 管理者アカウント（Smiley9999）の設定ガイド

## 🎯 目的

実際の管理者アカウント `Smiley9999` のパスワードを設定して、ログインできるようにします。

---

## 🚀 最も簡単な方法（推奨）

### Webツールで設定

1. ブラウザで以下のURLにアクセス:
   ```
   https://twinklemark.xsrv.jp/Smiley/meal-delivery/billing-system/set_user_password.php
   ```

2. 「**Smiley9999（管理者）**」のクイックボタンをクリック

3. 以下の情報を入力:
   - **利用者コード**: `Smiley9999`（自動入力済み）
   - **新しいパスワード**: 任意のパスワード（8文字以上推奨）
   - **パスワード確認**: 同じパスワードを再入力
   - **ロール**: `admin` または `smiley_staff`

4. 「**パスワードを設定**」ボタンをクリック

5. ✅ 成功メッセージが表示されたら完了

6. ログインページで設定したパスワードでログイン

---

## 📋 その他の方法

### 方法2: SQLで直接設定（デフォルトパスワード使用）

#### phpMyAdmin経由

1. phpMyAdminにログイン
2. データベース `twinklemark_billing` を選択
3. 「SQL」タブをクリック
4. 以下のSQLを実行:

```sql
-- パスワード: admin9999
UPDATE users
SET password_hash = '$2y$12$qOXW0dVp0YhvKwPnBjUHOONH8vMJiG.hW2jXKJGZQZxEbQK7GxJ8K',
    role = 'admin',
    is_registered = 1,
    is_active = 1,
    registered_at = COALESCE(registered_at, NOW())
WHERE user_code = 'Smiley9999';
```

5. ログイン情報:
   - 利用者コード: `Smiley9999`
   - パスワード: `admin9999`

⚠️ **重要**: セキュリティのため、ログイン後すぐにパスワードを変更してください

#### SSH経由

```bash
cd /path/to/billing-system
mysql -u [username] -p [database] < sql/setup_smiley9999_admin.sql
```

---

### 方法3: カスタムパスワードのハッシュを生成

独自のパスワードを使用したい場合:

#### ステップ1: パスワードハッシュを生成

以下のいずれかの方法:

**a) Webツールで生成（fix_password_hash.php）:**
1. `fix_password_hash.php` にアクセス
2. 「カスタムパスワードのハッシュを生成」セクションで任意のパスワードを入力
3. 生成されたハッシュをコピー

**b) PHPコマンドで生成:**
```bash
php -r "echo password_hash('your_password', PASSWORD_BCRYPT, ['cost' => 12]) . PHP_EOL;"
```

#### ステップ2: SQLで更新

```sql
UPDATE users
SET password_hash = '生成されたハッシュ',
    role = 'admin',
    is_registered = 1,
    is_active = 1
WHERE user_code = 'Smiley9999';
```

---

## ✅ 設定後の確認

### 1. データベースで確認

```sql
SELECT
    user_code,
    user_name,
    CASE WHEN password_hash IS NOT NULL THEN 'パスワード設定済み' ELSE 'パスワード未設定' END as password_status,
    role,
    is_active
FROM users
WHERE user_code = 'Smiley9999';
```

### 2. ログインテスト

1. ログインページにアクセス:
   ```
   https://twinklemark.xsrv.jp/Smiley/meal-delivery/billing-system/pages/login.php
   ```

2. ログイン情報を入力:
   - 利用者コード: `Smiley9999`
   - パスワード: 設定したパスワード

3. ログイン成功を確認

---

## 🔐 パスワード変更方法（ログイン後）

ログインできたら、セキュリティのためパスワードを変更することを推奨します:

1. ダッシュボードにログイン
2. 「設定」または「プロフィール」メニューを開く
3. 「パスワード変更」を選択
4. 現在のパスワードと新しいパスワードを入力
5. 保存

---

## 👥 複数ユーザーのパスワード設定

他のユーザーのパスワードも設定する場合:

### Webツール使用（推奨）

`set_user_password.php` で以下の操作を繰り返し:

1. ユーザー一覧から利用者コードをクリック
2. パスワードとロールを入力
3. 「パスワードを設定」をクリック

### 一括設定（SQLで）

```sql
-- ユーザーA
UPDATE users
SET password_hash = '[ハッシュ]',
    role = 'user',
    is_registered = 1,
    is_active = 1
WHERE user_code = 'USER_CODE_A';

-- ユーザーB
UPDATE users
SET password_hash = '[ハッシュ]',
    role = 'user',
    is_registered = 1,
    is_active = 1
WHERE user_code = 'USER_CODE_B';
```

---

## 🔒 ロールの説明

| ロール | 説明 | 推奨対象 |
|--------|------|----------|
| `admin` | システム管理者（全権限） | Smiley9999等のシステム管理者 |
| `smiley_staff` | Smileyスタッフ（管理機能） | Smiley従業員 |
| `company_admin` | 企業管理者（自社のみ管理） | 各企業の管理者 |
| `user` | 一般利用者（閲覧のみ） | 一般ユーザー |

### 推奨設定

- **Smiley9999**: `admin` または `smiley_staff`
- **Smiley0007**: `smiley_staff`（テストアカウント）
- **企業管理者**: `company_admin`
- **一般ユーザー**: `user`

---

## 🛡️ セキュリティ対策

### 設定完了後

以下のファイルを削除してください:

```bash
rm set_user_password.php
rm fix_password_hash.php
```

または、FTP/ファイルマネージャーで削除。

### パスワード強度の推奨事項

- **最低8文字以上**（システムは6文字以上で許可）
- 英数字と記号を組み合わせる
- 推測されやすいパスワードは避ける（生年月日、連番など）
- 定期的にパスワードを変更する

---

## ❓ よくある質問

### Q1: Smiley9999がユーザー一覧に表示されません

**A:** usersテーブルにSmiley9999のレコードが存在しない可能性があります。

確認SQL:
```sql
SELECT * FROM users WHERE user_code LIKE 'Smiley%';
```

存在しない場合は、レコードを作成:
```sql
INSERT INTO users (user_code, user_name, role, is_active)
VALUES ('Smiley9999', '管理者', 'admin', 1);
```

その後、`set_user_password.php` でパスワードを設定。

### Q2: パスワードを設定したのにログインできません

**A:** 以下を確認:

1. `check_db_web.php` でパスワード検証テストを実行
2. `is_active` が 1 になっているか確認
3. 入力した利用者コードとパスワードが正しいか確認
4. ブラウザのキャッシュをクリア

### Q3: 管理者と一般ユーザーの違いは？

**A:** 権限の違い:

- **admin**: すべての機能にアクセス可能
- **smiley_staff**: 管理機能（ユーザー管理、請求書発行等）
- **company_admin**: 自社のデータのみ管理
- **user**: 閲覧のみ、編集不可

---

## 📞 サポート

問題が解決しない場合は、以下の情報を添えてお問い合わせください:

1. `set_user_password.php` の実行結果（スクリーンショット）
2. `check_db_web.php` の診断結果
3. エラーメッセージ
4. 試した方法

---

## 🔗 関連ドキュメント

- [パスワード修正クイックガイド](QUICK_FIX_PASSWORD.md)
- [ログイントラブルシューティング](TROUBLESHOOTING_LOGIN.md)
- [マイグレーション実行ガイド](MIGRATION_README_users_auth.md)

---

## 📝 更新履歴

| 日付 | 内容 |
|------|------|
| 2025-12-20 | 初版作成 - 管理者アカウント設定ガイド |
