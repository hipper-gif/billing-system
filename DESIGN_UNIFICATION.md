# デザイン統一計画

## 現状
多くのページが独自のHTMLヘッダー・フッターを使用しており、デザインが統一されていません。

## 統一すべきページ
### 優先度: 高
- [x] payments.php - ✅ 既に統一済み
- [ ] companies.php - 企業管理
- [ ] users.php - 利用者管理
- [ ] departments.php - 部署管理
- [ ] csv_import.php - CSVインポート

### 優先度: 中
- [ ] bulk_payment_list.php - 一括入金リスト
- [ ] company_detail.php - 企業詳細
- [ ] invoices.php - 請求書管理

### 優先度: 低（システム管理系）
- [ ] system_health.php - システムヘルス
- [ ] diagnosis.php - 診断
- [ ] html_error_viewer.php - エラービューワー

### 印刷専用（独自デザイン維持）
- receipt.php - 領収書印刷
- bulk_receipt_print.php - 一括印刷

## 統一方法

### 1. 共通ヘッダー・フッターの使用
```php
// ページ設定
$pageTitle = 'ページタイトル';
$activePage = 'page-id';  // dashboard, payments, companies, users, etc.
$basePath = '..';

// ヘッダー読み込み
require_once __DIR__ . '/../includes/header.php';

// ページコンテンツ

// フッター読み込み
require_once __DIR__ . '/../includes/footer.php';
```

### 2. 共通CSSの読み込み
```html
<link href="../assets/css/material-common.css" rel="stylesheet">
```

### 3. Material Icons の使用
```html
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
<span class="material-icons">icon_name</span>
```

## 作業手順（各ページ）

1. **バックアップ作成**
   ```bash
   cp pages/xxx.php pages/xxx.php.backup
   ```

2. **ヘッダー部分の置き換え**
   - 独自の`<!DOCTYPE>`から`<body>`まで削除
   - ページ設定変数を追加
   - `require_once __DIR__ . '/../includes/header.php';` を追加

3. **フッター部分の置き換え**
   - `</body></html>` 削除
   - `require_once __DIR__ . '/../includes/footer.php';` を追加

4. **カスタムCSSの確認**
   - ページ固有のスタイルがあれば `assets/css/` に移動

5. **動作確認**
   - ページが正常に表示されるか確認
   - ナビゲーションが動作するか確認

## 完了状況
- [x] 共通CSS作成 (material-common.css)
- [ ] companies.php 統一
- [ ] users.php 統一
- [ ] departments.php 統一
- [ ] その他のページ統一
