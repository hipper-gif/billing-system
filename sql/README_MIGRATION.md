# ordersテーブル マイグレーション手順

## 問題

CSVインポート処理が使用するカラムと、ordersテーブルの実際のスキーマが一致していないため、CSVインポートが正常に動作していません。

## 症状

- CSVファイルの読み込みは成功する
- しかし、集金管理画面で「総注文数: 0件」と表示される
- データがordersテーブルに保存されていない

## 解決方法

`migration_add_orders_columns.sql`を実行して、不足しているカラムを追加します。

## 実行手順

### 方法1: phpMyAdminを使用

1. phpMyAdminにログイン
2. データベース `twinklemark_billing` を選択（テスト環境の場合）
3. 「SQL」タブをクリック
4. `migration_add_orders_columns.sql`の内容をコピー＆ペースト
5. 「実行」ボタンをクリック

### 方法2: MySQLコマンドラインを使用

```bash
# サーバーにSSH接続後
mysql -u twinklemark_bill -p twinklemark_billing < /path/to/migration_add_orders_columns.sql
```

パスワード: `Smiley2525`

### 方法3: PHPスクリプトで実行

`run_migration.php`を作成して実行することもできます（下記参照）。

## 追加されるカラム

以下のカラムがordersテーブルに追加されます：

- `delivery_date` - 配達日
- `company_id` - 企業ID
- `company_code` - 企業コード
- `company_name` - 企業名
- `department_id` - 部署ID
- `category_code` - カテゴリコード
- `category_name` - カテゴリ名
- `supplier_id` - 仕入先ID
- `corporation_code` - 法人コード
- `corporation_name` - 法人名
- `employee_type_code` - 社員区分コード
- `employee_type_name` - 社員区分名
- `delivery_time` - 配達時間
- `cooperation_code` - 協力コード

## 確認方法

マイグレーション実行後、以下のSQLで確認できます：

```sql
-- カラムが追加されたか確認
SHOW COLUMNS FROM orders;

-- CSVを再インポート後、データが保存されたか確認
SELECT COUNT(*) FROM orders;
SELECT * FROM orders LIMIT 5;
```

## トラブルシューティング

### エラー: "Duplicate column name"

すでにカラムが存在する場合、このエラーが表示されます。
その行をコメントアウトするか、スキップしてください。

### エラー: "Cannot add foreign key constraint"

外部キー制約の追加に失敗した場合、該当する行をコメントアウトして実行してください。
データの保存には影響ありません。

## マイグレーション実行後の手順

1. **CSVファイルを再インポート**
   - データ取込ページ（`pages/csv_import.php`）にアクセス
   - CSVファイルをアップロード

2. **集金管理画面で確認**
   - ダッシュボードまたは集金管理ページにアクセス
   - 「総注文数」が0件以外になっていることを確認
   - 金額が表示されることを確認

## 注意事項

- **バックアップを推奨**: マイグレーション実行前に、念のためデータベースのバックアップを取得してください
- **本番環境での実行**: 必ずテスト環境で動作確認後、本番環境で実行してください
- **既存データへの影響**: このマイグレーションは既存データに影響を与えません（カラムの追加のみ）
