# usersテーブル認証カラム追加マイグレーション

## 📋 概要

このマイグレーションは、ログイン認証機能に必要なカラムをusersテーブルに追加します。

### 問題の原因

`sql/init.sql`で定義されているusersテーブルには、AuthManagerやユーザー登録システムが期待している以下のカラムが欠落していました:

- `password_hash` - パスワードハッシュ（ログインに必須）
- `company_id` - 企業ID（企業との紐付けに必須）
- `role` - ロール/権限（権限管理に必須）
- `is_registered` - 本登録完了フラグ
- `registered_at` - 本登録完了日時
- `last_login_at` - 最終ログイン日時
- その他の拡張カラム

### 影響を受けていたファイル

- `classes/AuthManager.php` (line 162-168) - これらのカラムを参照
- `api/join.php` (line 207-233) - これらのカラムへINSERT
- `api/users.php` - company_id, department_idなどを参照

---

## 🚀 マイグレーション実行手順

### 方法1: PHPスクリプトで実行（推奨）

```bash
# マイグレーションスクリプトを実行
php run_users_auth_migration.php
```

**特徴:**
- ✅ トランザクション対応（エラー時自動ロールバック）
- ✅ 既存カラムのスキップ（Duplicate errorを自動処理）
- ✅ 詳細な実行ログ表示
- ✅ 実行結果の自動確認

### 方法2: MySQLコマンドで直接実行

```bash
# MySQLにログイン
mysql -u [username] -p [database_name]

# マイグレーションSQLを実行
source sql/migration_add_users_auth_columns.sql
```

### 方法3: phpMyAdminなどのGUIツール

1. phpMyAdminにログイン
2. 対象のデータベースを選択
3. 「SQL」タブを開く
4. `sql/migration_add_users_auth_columns.sql`の内容をコピー&ペースト
5. 「実行」をクリック

---

## 📦 追加されるカラム

### 認証関連カラム（必須）

| カラム名 | 型 | デフォルト | 説明 |
|---------|-----|-----------|------|
| `password_hash` | VARCHAR(255) | NULL | パスワードハッシュ（bcrypt） |
| `company_id` | INT | NULL | 企業ID（外部キー） |
| `role` | ENUM | 'user' | ロール（user/company_admin/smiley_staff/admin） |
| `is_registered` | TINYINT(1) | 0 | 本登録完了フラグ |
| `registered_at` | TIMESTAMP | NULL | 本登録完了日時 |
| `last_login_at` | TIMESTAMP | NULL | 最終ログイン日時 |

### 拡張機能用カラム（オプション）

| カラム名 | 型 | デフォルト | 説明 |
|---------|-----|-----------|------|
| `department_id` | INT | NULL | 部署ID（外部キー） |
| `employee_type_code` | VARCHAR(50) | NULL | 従業員タイプコード |
| `employee_type_name` | VARCHAR(100) | NULL | 従業員タイプ名 |

### インデックス

- `idx_company_id` - company_idインデックス
- `idx_role` - roleインデックス
- `idx_is_registered` - is_registeredインデックス
- `idx_department_id` - department_idインデックス

---

## 🧪 テストデータ

マイグレーション実行後、以下のテストユーザーが自動的に作成されます:

```
利用者コード: Smiley0007
パスワード: password123
氏名: テスト管理者
ロール: smiley_staff (Smileyスタッフ)
状態: 有効・登録済み
```

このアカウントでログイン機能をテストできます。

---

## ✅ マイグレーション実行後の確認

### 1. カラムが追加されたことを確認

```sql
SHOW COLUMNS FROM users;
```

以下のカラムが存在することを確認:
- password_hash
- company_id
- role
- is_registered
- registered_at
- last_login_at

### 2. テストユーザーが作成されたことを確認

```sql
SELECT
    user_code,
    user_name,
    role,
    is_registered,
    is_active,
    CASE WHEN password_hash IS NOT NULL THEN 'パスワード設定済み' ELSE 'パスワード未設定' END as password_status
FROM users
WHERE user_code = 'Smiley0007';
```

### 3. ログイン機能のテスト

1. ログインページにアクセス
2. 以下の情報でログイン:
   - 利用者コード: `Smiley0007`
   - パスワード: `password123`
3. ログインが成功することを確認

---

## 🔄 ロールバック方法

マイグレーションを取り消す場合は、以下のSQLを実行してください:

```sql
-- カラムを削除
ALTER TABLE users DROP COLUMN password_hash;
ALTER TABLE users DROP COLUMN company_id;
ALTER TABLE users DROP COLUMN role;
ALTER TABLE users DROP COLUMN is_registered;
ALTER TABLE users DROP COLUMN registered_at;
ALTER TABLE users DROP COLUMN last_login_at;
ALTER TABLE users DROP COLUMN department_id;
ALTER TABLE users DROP COLUMN employee_type_code;
ALTER TABLE users DROP COLUMN employee_type_name;

-- インデックスを削除
ALTER TABLE users DROP INDEX idx_company_id;
ALTER TABLE users DROP INDEX idx_role;
ALTER TABLE users DROP INDEX idx_is_registered;
ALTER TABLE users DROP INDEX idx_department_id;

-- テストユーザーを削除
DELETE FROM users WHERE user_code = 'Smiley0007';
```

---

## 📝 注意事項

### 本番環境での実行前に

1. **必ずバックアップを取得してください**
   ```bash
   mysqldump -u [username] -p [database_name] > backup_before_migration_$(date +%Y%m%d_%H%M%S).sql
   ```

2. **テスト環境で先に実行して動作確認**

3. **メンテナンス時間を設定**
   - マイグレーション中はシステムを一時停止することを推奨

### 既存データへの影響

- 既存のusersレコードには、新しいカラムがNULLまたはデフォルト値で追加されます
- `role`カラムはデフォルト値'user'が設定されます
- `is_registered`はデフォルト値0（未登録）が設定されます

### companiesテーブルとの連携

マイグレーションSQLには、companiesテーブルへの外部キー制約がコメントアウトされています。
companiesテーブルが存在する場合は、以下のSQLを実行して外部キー制約を追加してください:

```sql
ALTER TABLE users
ADD CONSTRAINT fk_users_company_id
FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL;

ALTER TABLE users
ADD CONSTRAINT fk_users_department_id
FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL;
```

---

## 🛠️ トラブルシューティング

### エラー: "Duplicate column name"

カラムが既に存在する場合のエラーです。すでにマイグレーションが実行されている可能性があります。
`SHOW COLUMNS FROM users`で確認してください。

### エラー: "Cannot add foreign key constraint"

companiesテーブルやdepartmentsテーブルが存在しない場合のエラーです。
外部キー制約の部分をコメントアウトして実行してください。

### データベース接続エラー

`config/database.php`の設定を確認してください:
- DB_HOST
- DB_NAME
- DB_USER
- DB_PASS

---

## 📞 サポート

問題が発生した場合は、以下の情報を添えてお問い合わせください:

1. エラーメッセージ
2. 実行環境（本番/テスト/ローカル）
3. MySQLバージョン
4. PHPバージョン

---

## 📅 変更履歴

| 日付 | バージョン | 内容 |
|------|-----------|------|
| 2025-12-20 | 1.0 | 初版作成 - usersテーブル認証カラム追加 |
