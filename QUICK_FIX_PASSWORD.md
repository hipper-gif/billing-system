# 🚨 パスワード検証失敗の即座修正ガイド

## 現象

診断ツール（check_db_web.php）で以下のエラーが表示される:

```
❌ パスワード検証失敗
テストパスワード: password123
```

---

## 🚀 即座修正（3つの方法）

### 方法1: Webツールで自動修正（最も簡単・推奨）

1. ブラウザで以下のURLにアクセス:
   ```
   https://twinklemark.xsrv.jp/Smiley/meal-delivery/billing-system/fix_password_hash.php
   ```

2. 「**テストユーザーのパスワードハッシュを修正**」ボタンをクリック

3. ✅ 成功メッセージが表示されたら完了

4. ログインページで以下の情報でログイン:
   - 利用者コード: `Smiley0007`
   - パスワード: `password123`

---

### 方法2: SQLファイルを実行

#### phpMyAdmin経由

1. phpMyAdminにログイン

2. データベース `twinklemark_billing` を選択

3. 「SQL」タブをクリック

4. 以下のSQLをコピー&ペースト:

```sql
UPDATE users
SET password_hash = '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TukcvhqdVyO1N8hMp0i5GdBKqRQC',
    role = 'smiley_staff',
    is_registered = 1,
    is_active = 1,
    registered_at = NOW()
WHERE user_code = 'Smiley0007';
```

5. 「実行」をクリック

#### SSH経由

```bash
cd /path/to/billing-system
mysql -u [username] -p [database] < sql/fix_test_user_password.sql
```

---

### 方法3: 手動でパスワードハッシュを生成

カスタムパスワードを使用したい場合:

1. `fix_password_hash.php` にアクセス

2. 「カスタムパスワードのハッシュを生成」セクションで任意のパスワードを入力

3. 「ハッシュを生成」をクリック

4. 生成されたSQLをコピーして実行

---

## 🔍 修正後の確認

### ステップ1: 診断ツールで確認

```
https://twinklemark.xsrv.jp/Smiley/meal-delivery/billing-system/check_db_web.php
```

以下が表示されればOK:
```
✅ パスワード検証成功
テストパスワード: password123
```

### ステップ2: ログインテスト

1. ログインページにアクセス:
   ```
   https://twinklemark.xsrv.jp/Smiley/meal-delivery/billing-system/pages/login.php
   ```

2. ログイン情報を入力:
   - 利用者コード: `Smiley0007`
   - パスワード: `password123`

3. ログイン成功を確認

---

## 🛡️ セキュリティ対策

修正完了後、以下のファイルを削除してください:

```bash
# SSH経由
rm fix_password_hash.php

# またはFTP/ファイルマネージャーで削除
```

---

## ❓ なぜこの問題が発生したのか？

### 原因

マイグレーションSQL (`migration_add_users_auth_columns.sql`) 内のINSERT文で使用されているpassword_hashが、何らかの理由で正しく機能していない可能性があります。

考えられる原因:
1. SQL実行時の文字エンコーディングの問題
2. MySQLのバージョンによるハッシュ処理の違い
3. マイグレーション実行時のエラー（気づかれなかった）

### 解決策

本修正ツールでは、PHPの `password_hash()` 関数を使用して、確実に正しいbcryptハッシュを生成します。

---

## 📊 技術的な詳細

### 正しいパスワードハッシュ

```
パスワード: password123
ハッシュ: $2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TukcvhqdVyO1N8hMp0i5GdBKqRQC
アルゴリズム: bcrypt
コスト: 12
```

### 検証コード（PHP）

```php
$password = 'password123';
$hash = '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TukcvhqdVyO1N8hMp0i5GdBKqRQC';

if (password_verify($password, $hash)) {
    echo "検証成功";
} else {
    echo "検証失敗";
}
```

---

## 📞 それでも解決しない場合

以下の情報を添えてお問い合わせください:

1. `check_db_web.php` の実行結果（スクリーンショット）
2. `fix_password_hash.php` の実行結果
3. エラーメッセージ（あれば）
4. MySQLのバージョン
5. PHPのバージョン

---

## 🔗 関連ドキュメント

- [トラブルシューティングガイド](TROUBLESHOOTING_LOGIN.md)
- [マイグレーション実行ガイド](MIGRATION_README_users_auth.md)
- [データベース診断ツール](check_db_web.php)

---

## 📝 更新履歴

| 日付 | 内容 |
|------|------|
| 2025-12-20 | 初版作成 - パスワードハッシュ修正ツール追加 |
