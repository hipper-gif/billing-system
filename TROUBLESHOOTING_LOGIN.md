# ログイン認証問題のトラブルシューティング

## 🚨 現象

ログイン画面で正しい利用者コードとパスワードを入力しても、以下のエラーが表示される:

```
利用者コードまたはパスワードが正しくありません
```

ブラウザのコンソールには以下のエラーが表示:

```
POST https://twinklemark.xsrv.jp/Smiley/meal-delivery/billing-system/api/auth.php?action=login 401 (Unauthorized)
```

---

## 🔍 診断手順

### ステップ1: データベース状態の確認（Web版）

ブラウザで以下のURLにアクセス:

```
https://twinklemark.xsrv.jp/Smiley/meal-delivery/billing-system/check_db_web.php
```

このページで以下を確認:
- ✅ データベース接続が成功しているか
- ✅ usersテーブルが存在するか
- ✅ 必須カラム（password_hash, role, company_id等）が存在するか
- ✅ テストユーザー（Smiley0007）が存在するか
- ✅ パスワード検証が成功するか

### ステップ2: データベース状態の確認（CLI版）

SSHでサーバーにログインして実行:

```bash
cd /path/to/billing-system
php check_database_status.php
```

---

## 🔧 問題のパターンと解決方法

### パターン1: 必須カラムが不足している

**診断結果の例:**
```
❌ password_hash: 不足 (認証に必須)
❌ role: 不足 (権限管理に必須)
❌ company_id: 不足 (AuthManagerで参照)
```

**原因:**
- usersテーブルが古いスキーマで作成されている
- マイグレーションが実行されていない

**解決方法:**

#### 方法1: PHPスクリプトで実行（推奨）

```bash
cd /path/to/billing-system
php run_users_auth_migration.php
```

#### 方法2: MySQLで直接実行

```bash
mysql -u [username] -p [database] < sql/migration_add_users_auth_columns.sql
```

#### 方法3: phpMyAdmin経由

1. phpMyAdminにログイン
2. 対象のデータベースを選択
3. 「SQL」タブを開く
4. `sql/migration_add_users_auth_columns.sql` の内容をコピー&ペースト
5. 「実行」をクリック

---

### パターン2: テストユーザーが存在しない

**診断結果の例:**
```
❌ テストユーザー 'Smiley0007' が見つかりません
```

**原因:**
- マイグレーションが未実行
- または手動でユーザーを削除した

**解決方法:**

マイグレーションを実行すると、テストユーザーが自動作成されます:

```bash
php run_users_auth_migration.php
```

または、手動でテストユーザーを作成:

```sql
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
);
```

パスワード: `password123`

---

### パターン3: パスワードハッシュが設定されていない

**診断結果の例:**
```
⚠️ password_hashが設定されていません
```

**原因:**
- ユーザーレコードは存在するが、password_hashカラムがNULL

**解決方法:**

既存ユーザーにパスワードを設定:

```sql
-- 'password123' のハッシュを設定
UPDATE users
SET password_hash = '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TukcvhqdVyO1N8hMp0i5GdBKqRQC',
    is_registered = 1,
    registered_at = NOW()
WHERE user_code = 'Smiley0007';
```

または、PHPでパスワードハッシュを生成:

```php
<?php
$password = 'your_password_here';
$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
echo "Password Hash: {$hash}\n";
?>
```

---

### パターン4: パスワード検証が失敗する

**診断結果の例:**
```
❌ パスワード検証に失敗しました
```

**原因:**
- password_hashが正しくない形式
- 異なるハッシュアルゴリズムが使用されている

**解決方法:**

正しいbcryptハッシュに更新:

```bash
# 新しいパスワードハッシュを生成
php -r "echo password_hash('password123', PASSWORD_BCRYPT, ['cost' => 12]) . PHP_EOL;"
```

生成されたハッシュでデータベースを更新:

```sql
UPDATE users
SET password_hash = '生成されたハッシュ値'
WHERE user_code = 'Smiley0007';
```

---

### パターン5: roleカラムがNULL

**診断結果の例:**
```
role: NULL
```

**原因:**
- マイグレーション実行後に作成されたユーザー
- roleが明示的に設定されていない

**解決方法:**

```sql
UPDATE users
SET role = 'user'
WHERE role IS NULL;

-- Smileyスタッフの場合
UPDATE users
SET role = 'smiley_staff'
WHERE user_code = 'Smiley0007';
```

---

## 🧪 ログイン機能のテスト

### テストユーザーでログイン

1. ログインページにアクセス:
   ```
   https://twinklemark.xsrv.jp/Smiley/meal-delivery/billing-system/pages/login.php
   ```

2. 以下の情報を入力:
   - **利用者コード**: `Smiley0007`
   - **パスワード**: `password123`

3. 「ログイン」をクリック

4. 成功すれば、ダッシュボードにリダイレクトされます

---

## 🔐 既存ユーザーのパスワード設定

既存のユーザーがログインできるようにするには、各ユーザーにパスワードを設定する必要があります。

### 方法1: 管理画面から設定（推奨）

1. Smileyスタッフでログイン
2. ユーザー管理画面に移動
3. 各ユーザーのパスワードを設定

### 方法2: SQLで一括設定（開発環境のみ）

**注意: 本番環境では推奨しません**

```sql
-- すべてのユーザーに仮パスワード 'temp123' を設定
UPDATE users
SET password_hash = '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TukcvhqdVyO1N8hMp0i5GdBKqRQC',
    is_registered = 0  -- 初回ログイン時にパスワード変更を促す
WHERE password_hash IS NULL;
```

### 方法3: ユーザー登録フローを使用

QRコード経由でユーザー自身にパスワードを設定してもらう:

1. 企業登録トークンを発行
2. QRコードを生成
3. ユーザーがQRコードをスキャン
4. 登録画面でパスワードを設定

---

## 📊 よくある質問

### Q1: マイグレーションを実行すると既存データは消えますか？

**A:** いいえ、消えません。マイグレーションはカラムを追加するだけで、既存のデータは保持されます。

### Q2: 本番環境で実行する前にバックアップは必要ですか？

**A:** はい、必ず取得してください:

```bash
mysqldump -u [username] -p [database] > backup_$(date +%Y%m%d_%H%M%S).sql
```

### Q3: マイグレーション実行中にエラーが発生したらどうなりますか？

**A:** トランザクション機能により、自動的にロールバックされます。データは変更されません。

### Q4: マイグレーションを元に戻すには？

**A:** `MIGRATION_README_users_auth.md` の「ロールバック方法」セクションを参照してください。

### Q5: テストユーザー以外でログインできませんか？

**A:** 他のユーザーでログインするには、そのユーザーのpassword_hashを設定する必要があります。

---

## 📞 サポート

上記の手順で解決しない場合は、以下の情報を添えてお問い合わせください:

1. `check_db_web.php` の実行結果（スクリーンショット）
2. ブラウザのコンソールログ（エラーメッセージ）
3. 実行環境（本番/テスト/ローカル）
4. 試した解決方法

---

## 📝 関連ドキュメント

- [マイグレーション実行ガイド](MIGRATION_README_users_auth.md)
- [データベース初期化スクリプト](sql/init.sql)
- [AuthManagerクラス](classes/AuthManager.php)

---

## 🔄 最終更新

- **日付**: 2025-12-20
- **バージョン**: 1.0
- **対応issue**: ログイン認証問題の修正
