# データベース マイグレーションガイド

## 問題: CSVインポート時のエラー

CSVインポート時に以下のエラーが発生する場合：

```
Column not found: 1054 Unknown column 'category_code' in 'field list'
```

これは`products`テーブルに必要なカラムが不足しているためです。

## 解決方法

### 方法1: ブラウザから実行（推奨）

1. ブラウザで以下のURLにアクセスします：

```
https://あなたのドメイン/api/migrate_products_table.php
```

例：
- テスト環境: `https://twinklemark.xsrv.jp/Smiley/meal-delivery/billing-system/api/migrate_products_table.php`
- 本番環境: `https://tw1nkle.com/Smiley/meal-delivery/billing-system/api/migrate_products_table.php`

2. 成功すると以下のようなJSONレスポンスが表示されます：

```json
{
    "success": true,
    "message": "マイグレーション完了（全て成功）",
    "data": {
        "results": {
            "rename_category": "success",
            "add_category_name": "success",
            "add_supplier_id": "success",
            "add_unit_price": "success"
        }
    }
}
```

### 方法2: MySQLクライアントから実行

MySQLに接続して、以下のSQLファイルを実行します：

```bash
mysql -u ユーザー名 -p データベース名 < sql/migration_add_product_columns.sql
```

## マイグレーション内容

以下のカラムが`products`テーブルに追加されます：

| カラム名 | 型 | 説明 |
|---------|-------|------|
| `category_code` | VARCHAR(50) | 給食区分CD（既存のcategoryをリネーム） |
| `category_name` | VARCHAR(100) | 給食区分名 |
| `supplier_id` | INT | 給食業者ID |
| `unit_price` | DECIMAL(10,2) | 単価 |

## マイグレーション後の確認

マイグレーション後、以下を確認してください：

1. **カラムが正しく追加されたか確認**

```sql
SHOW COLUMNS FROM products;
```

2. **CSVインポートをテスト**

   - CSVファイルを再度アップロードしてインポートを試してください
   - エラーが解消されているはずです

## トラブルシューティング

### Q: マイグレーションが失敗する

**A:** 既にカラムが存在する可能性があります。以下のSQLで確認してください：

```sql
SELECT COLUMN_NAME
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products';
```

### Q: 既存データはどうなる？

**A:**
- `category` → `category_code`へのリネームは既存データを保持します
- 新規追加カラムは全て`NULL`で初期化されます
- 既存のproductsレコードに影響はありません

### Q: ロールバックしたい

**A:** 以下のSQLで元に戻すことができます：

```sql
-- category_codeをcategoryに戻す
ALTER TABLE products CHANGE COLUMN category_code category VARCHAR(50);

-- 追加したカラムを削除
ALTER TABLE products DROP COLUMN category_name;
ALTER TABLE products DROP COLUMN supplier_id;
ALTER TABLE products DROP COLUMN unit_price;

-- インデックスを削除
ALTER TABLE products DROP INDEX idx_category_code;
ALTER TABLE products DROP INDEX idx_supplier_id;
```

## 参考情報

- マイグレーションSQLファイル: `sql/migration_add_product_columns.sql`
- マイグレーション実行API: `api/migrate_products_table.php`
- CSVインポーター: `classes/SmileyCSVImporter.php`

---

## 注文管理機能のマイグレーション

### 問題: 注文作成時のエラー

注文作成時に以下のエラーが発生する場合：

```
注文の作成中にエラーが発生しました
```

これは`orders`テーブルに注文管理用のカラム（`subsidy_amount`, `user_payment_amount`, `order_status`など）が不足しているためです。

### 解決方法

**2つのマイグレーションを実行する必要があります：**

#### ステップ1: ordersテーブルのマイグレーション

MySQLクライアントから以下を実行：

```bash
mysql -u ユーザー名 -p データベース名 < sql/migration_add_order_management_columns.sql
```

または、phpMyAdminで以下のSQLを実行：

```sql
-- ordersテーブルに注文管理用のカラムを追加
ALTER TABLE orders ADD COLUMN subsidy_amount DECIMAL(10, 2) DEFAULT 0 COMMENT '企業補助額' AFTER total_amount;
ALTER TABLE orders ADD COLUMN user_payment_amount DECIMAL(10, 2) DEFAULT 0 COMMENT 'ユーザー支払い額' AFTER subsidy_amount;
ALTER TABLE orders ADD COLUMN ordered_by_user_id INT COMMENT '注文者ID' AFTER user_payment_amount;
ALTER TABLE orders ADD COLUMN order_type ENUM('self', 'proxy') DEFAULT 'self' COMMENT '注文タイプ' AFTER ordered_by_user_id;
ALTER TABLE orders ADD COLUMN order_status ENUM('confirmed', 'cancelled', 'pending') DEFAULT 'confirmed' COMMENT '注文ステータス' AFTER order_type;
ALTER TABLE orders ADD INDEX idx_order_status (order_status);
ALTER TABLE orders ADD INDEX idx_ordered_by_user_id (ordered_by_user_id);
```

#### ステップ2: companiesテーブルのマイグレーション

MySQLクライアントから以下を実行：

```bash
mysql -u ユーザー名 -p データベース名 < sql/migration_add_companies_subsidy.sql
```

または、phpMyAdminで以下のSQLを実行：

```sql
-- companiesテーブルに企業補助額カラムを追加
ALTER TABLE companies ADD COLUMN subsidy_amount DECIMAL(10, 2) DEFAULT 0 COMMENT '企業補助額（1食あたり）' AFTER company_name;
```

### マイグレーション内容

#### ordersテーブルに追加されるカラム：

| カラム名 | 型 | 説明 |
|---------|-------|------|
| `subsidy_amount` | DECIMAL(10,2) | 企業補助額 |
| `user_payment_amount` | DECIMAL(10,2) | ユーザー支払い額 |
| `ordered_by_user_id` | INT | 注文者ID（代理注文用） |
| `order_type` | ENUM | 注文タイプ（self/proxy） |
| `order_status` | ENUM | 注文ステータス（confirmed/cancelled/pending） |

#### companiesテーブルに追加されるカラム：

| カラム名 | 型 | 説明 |
|---------|-------|------|
| `subsidy_amount` | DECIMAL(10,2) | 企業補助額（1食あたり） |

### マイグレーション後の確認

```sql
DESCRIBE orders;
DESCRIBE companies;
```

上記のカラムが両方のテーブルに存在することを確認してください。

---

最終更新: 2025-12-22
