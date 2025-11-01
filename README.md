# Smiley配食事業システム 完全統合仕様書 v5.0

**最終更新日**: 2025年10月02日  
**文書種別**: プロジェクトナレッジ完全統合版  
**対象**: 開発者・運用者・保守担当者・引き継ぎ担当者  
**重要変更**: メソッド不整合の完全解決、Databaseクラス統一版確定

---

## 📋 **プロジェクト全体概要**

### **基本情報**
- **プロジェクト名**: Smiley配食事業システム（請求書・集金管理システム）
- **事業者**: 株式会社Smiley配食事業部
- **開発期間**: 2025年8月27日～（進行中）
- **進捗率**: **95%完了**（統合テスト・最終調整のみ残存）
- **稼働予定**: 統合テスト完了後即座に本格稼働

### **システム環境**
```yaml
本番環境: https://tw1nkle.com/Smiley/meal-delivery/billing-system/
テスト環境: https://twinklemark.xsrv.jp/Smiley/meal-delivery/billing-system/
GitHub: https://github.com/hipper-gif/billing-system.git
技術構成: PHP 8.2.28 + MySQL 8.0 + Bootstrap 5.1.3 + Chart.js
ホスティング: エックスサーバー スタンダードプラン
自動デプロイ: GitHub Actions完全対応
```

---

## 🏗️ **システムアーキテクチャ詳細**

### **設計原則（v5.0確定版）**

```yaml
1. 自己完結原則 (Self-Contained Principle):
   - 各クラスは外部ファイルに依存しない
   - 内部で必要なクラス・設定を定義
   - ファイル読み込みエラーの根絶
   - コンストラクタで外部依存を受け取らない

2. メソッド統一原則 (Method Consistency Principle):
   - 全クラスで使用するメソッド名を完全統一
   - Databaseクラスは全メソッドを網羅（16メソッド）
   - 呼び出し側と実装側の不整合を完全排除

3. 冗長性による安定性 (Redundancy for Stability):
   - 重要クラスは複数箇所で定義可能
   - 一つのファイルが欠損しても動作継続
   - エラー耐性の最大化

4. 段階的フォールバック (Graceful Degradation):
   - データベース接続エラー時も基本動作継続
   - 統計データ取得失敗時はデフォルト値表示
   - システム全停止の回避

5. 根本解決重視:
   - 緊急対応・表面的修正の禁止
   - 確実なシステム構築重視
   - 問題の根本原因解決
```

---

## 🗄️ **Databaseクラス完全統一版（v5.0）**

### **設計思想**
- **単一ファイル完結**: `config/database.php`に全機能を統合
- **全メソッド網羅**: すべてのクラスが使用するメソッドを含む
- **classes/Database.phpは削除**: 重複を避けるため完全削除

### **Databaseクラス仕様（16メソッド確定版）**

```php
/**
 * Database クラス - 完全統一版
 * ファイル: config/database.php
 * 
 * 全16メソッド:
 * 1. getInstance() - Singletonインスタンス取得
 * 2. getConnection() - PDOオブジェクト取得
 * 3. query($sql, $params) - クエリ実行
 * 4. fetchAll($sql, $params) - 全行取得
 * 5. fetch($sql, $params) - 1行取得
 * 6. fetchColumn($sql, $params) - 単一値取得
 * 7. execute($sql, $params) - INSERT/UPDATE/DELETE
 * 8. beginTransaction() - トランザクション開始
 * 9. commit() - コミット
 * 10. rollback() - ロールバック
 * 11. lastInsertId() - 最終挿入ID
 * 12. tableExists($tableName) - テーブル存在確認
 * 13. getTableInfo($tableName) - テーブル情報取得
 * 14. testConnection() - 接続テスト
 * 15. getDatabaseStats() - データベース統計
 * 16. getDebugInfo() - デバッグ情報
 */
```

### **使用例**

```php
// 基本的な使用パターン
$db = Database::getInstance();

// パターン1: PDOオブジェクトを直接取得（SmileyCSVImporter用）
$pdo = $db->getConnection();
$stmt = $pdo->prepare($sql);

// パターン2: queryメソッドを使用（一般的な用途）
$stmt = $db->query($sql, $params);

// パターン3: fetchAllで全行取得
$results = $db->fetchAll($sql, $params);

// パターン4: トランザクション使用
$db->beginTransaction();
try {
    $db->query($sql1, $params1);
    $db->query($sql2, $params2);
    $db->commit();
} catch (Exception $e) {
    $db->rollback();
    throw $e;
}
```

---

## 📦 **SmileyCSVImporter完全統一版（v5.0）**

### **設計思想**
- **自己完結原則準拠**: コンストラクタで外部依存を受け取らない
- **内部でDatabase取得**: `Database::getInstance()`を内部で呼び出し
- **メソッド名統一**: `importCSV()`で統一

### **SmileyCSVImporterクラス仕様**

```php
/**
 * SmileyCSVImporter - 自己完結版
 * ファイル: classes/SmileyCSVImporter.php
 */
class SmileyCSVImporter {
    private $db;
    
    /**
     * コンストラクタ - 引数なし（自己完結）
     */
    public function __construct() {
        // 内部でDatabaseインスタンスを取得
        $this->db = Database::getInstance();
    }
    
    /**
     * CSVインポート実行
     * @param string $filePath CSVファイルパス
     * @param array $options オプション設定
     * @return array インポート結果
     */
    public function importCSV($filePath, $options = []) {
        // インポート処理
    }
}
```

### **使用例**

```php
// api/import.php での使用
$importer = new SmileyCSVImporter();  // 引数なし
$result = $importer->importCSV($filePath, $options);

// テストでの使用
$importer = new SmileyCSVImporter();  // 引数なし
$result = $importer->importCSV($testCSVPath, $importOptions);
```

---

## 🔧 **メソッド不整合の完全解決**

### **v5.0での主な変更**

#### **1. Databaseクラス統一**
```yaml
変更前:
  - GitHub版: getConnection()あり、7メソッド
  - サーバー版: getConnection()なし、15メソッド
  - 結果: メソッド不整合エラー

変更後:
  - 統一版: getConnection()含む16メソッド完備
  - config/database.phpに統合
  - classes/Database.phpは削除
```

#### **2. SmileyCSVImporter統一**
```yaml
変更前:
  - コンストラクタ: public function __construct(Database $db)
  - 呼び出し: new SmileyCSVImporter($db)
  - 結果: 引数不一致エラー

変更後:
  - コンストラクタ: public function __construct()
  - 呼び出し: new SmileyCSVImporter()
  - 内部でDatabase::getInstance()を呼び出し
```

#### **3. メソッド名統一**
```yaml
変更前:
  - 実装: importFile()
  - 呼び出し: importCSV()
  - 結果: メソッド未定義エラー

変更後:
  - 統一: importCSV()
  - すべてのファイルで統一
```

---

## 📝 **ファイル構成（v5.0確定版）**

### **重要な変更**

```yaml
削除されたファイル:
  - classes/Database.php  # config/database.phpに統合済み

確定版ファイル:
  - config/database.php  # Databaseクラス統合版（16メソッド）
  - classes/SmileyCSVImporter.php  # 自己完結版
  - api/import.php  # メソッド名統一済み
```

### **全ファイル構成**

```
📁 billing-system/
├── 📁 config/
│   └── 📄 database.php  # ⭐ Database統合版（16メソッド）
│
├── 📁 classes/
│   ├── 📄 SmileyCSVImporter.php  # ⭐ 自己完結版
│   ├── 📄 SmileyInvoiceGenerator.php
│   ├── 📄 ReceiptGenerator.php
│   ├── 📄 FileUploadHandler.php
│   └── 📄 SecurityHelper.php
│
├── 📁 api/
│   ├── 📄 import.php  # ⭐ メソッド名統一済み
│   ├── 📄 companies.php
│   ├── 📄 users.php
│   ├── 📄 invoices.php
│   ├── 📄 payments.php
│   ├── 📄 receipts.php
│   ├── 📄 dashboard.php
│   ├── 📄 data_check.php  # データ確認ツール
│   ├── 📄 test_import_detailed.php  # 詳細デバッグツール
│   └── 📄 view_errors.php  # エラーログ確認ツール
│
└── 📁 pages/
    ├── 📄 index.php
    ├── 📄 csv_import.php
    ├── 📄 companies.php
    ├── 📄 users.php
    ├── 📄 invoices.php
    └── 📄 payments.php
```

---

## 🎯 **開発時の必須遵守事項（v5.0更新）**

### **技術方針**

```yaml
1. メソッド使用:
   - Databaseクラスの16メソッドのみ使用
   - 存在しないメソッドの呼び出し厳禁
   - 不明な場合は仕様書で確認

2. クラス初期化:
   - SmileyCSVImporter: new SmileyCSVImporter() ← 引数なし
   - Database: Database::getInstance() ← Singleton
   - 引数を渡す初期化は厳禁

3. メソッド命名:
   - CSVインポート: importCSV() で統一
   - importFile()は使用禁止

4. テーブル・カラム情報:
   - 必ずナレッジから確認
   - 推測判断厳禁

5. 緊急対応:
   - 表面的修正は絶対に行わない
   - 根本対応の提案・対処のみ

6. デバッグツール:
   - 新規作成せず、既存ツールを再利用
   - api/test_import_detailed.php
   - api/data_check.php
   - api/view_errors.php
```

---

## 📊 **完成度評価（v5.0）**

### **機能完成度: 95%**

```yaml
✅ システム基盤: 100%完成
  - データベース設計: 17テーブル、368カラム
  - Databaseクラス: 16メソッド完全統一
  - CSVインポート: メソッド統一版完成
  - 自動デプロイ: GitHub Actions完全対応

✅ マスタ管理: 100%完成
  - 企業管理: 完全CRUD
  - 利用者管理: 統計表示
  - 部署管理: 企業連携

✅ 請求書システム: 90%完成
  - 請求書生成: PDF対応
  - 請求書管理: 検索・フィルター
  - 請求書番号: 自動採番

✅ 領収書システム: 100%完成
  - 領収書生成エンジン: 実装済み
  - 収入印紙対応: 5万円以上自動判定
  - 事前・事後発行: 両タイプ対応

⚠️ 支払い管理: 80%完成（統合テスト待ち）
```

---

## 🚀 **残り作業（v5.0時点）**

### **Priority 1: 統合テスト（最優先）**

```yaml
作業内容:
  1. デバッグツールで全ステップ成功確認
     - api/test_import_detailed.php
     - すべてのステップが✅になること
  
  2. 実際のCSVファイルでインポートテスト
     - 注文明細.CSV（46KB）
     - 処理件数が正しく表示されること
  
  3. データ確認
     - api/data_check.php
     - ordersテーブルにデータが入っていること
  
  4. エンドツーエンドテスト
     - CSVインポート → 請求書生成 → 支払い記録

期限: 即座に実行
重要度: ⭐⭐⭐（最高）
```

### **Priority 2: 本番環境設定**

```yaml
作業内容:
  1. config/database.phpの環境設定変更
     - ENVIRONMENT: 'development' → 'production'
     - DEBUG_MODE: true → false
  
  2. エラー表示設定
     - display_errors: 1 → 0
     - 本番環境でエラー詳細を非表示

期限: 統合テスト完了後
重要度: ⭐⭐（高）
```

### **Priority 3: ドキュメント整備**

```yaml
作業内容:
  - 操作マニュアル作成
  - トラブルシューティングガイド
  - 運用手順書

期限: 稼働前
重要度: ⭐（中）
```

---

## 🔍 **トラブルシューティング（v5.0更新）**

### **よくあるエラーと解決方法**

#### **1. "Call to undefined method Database::XXX"**
```yaml
原因: Databaseクラスに該当メソッドが存在しない
解決: 
  - 仕様書でメソッド名を確認
  - 16メソッドのみ使用可能
  - 存在しないメソッドの場合は仕様書を参照
```

#### **2. "Too few arguments to function"**
```yaml
原因: コンストラクタの引数が不一致
解決:
  - SmileyCSVImporter: 引数なしで初期化
  - new SmileyCSVImporter() ← 正しい
  - new SmileyCSVImporter($db) ← 間違い
```

#### **3. "Call to undefined method XXX::importFile()"**
```yaml
原因: メソッド名が importFile() になっている
解決:
  - importCSV() に統一
  - importFile()は使用禁止
```

#### **4. "SQLSTATE[42000]: Syntax error near 'current_time'"**
```yaml
原因: 予約語をカラム名に使用
解決:
  - current_time → current_dt に変更
  - 予約語の使用を避ける
```

---

## 📋 **デプロイ済みファイル一覧（v5.0）**

### **必須ファイル**

```yaml
✅ config/database.php:
  - バージョン: 5.0統一版
  - メソッド数: 16個
  - 状態: デプロイ済み

✅ classes/SmileyCSVImporter.php:
  - バージョン: 4.0自己完結版
  - コンストラクタ: 引数なし
  - メソッド名: importCSV()
  - 状態: デプロイ済み

✅ api/import.php:
  - バージョン: 3.0メソッド統一版
  - 呼び出し: new SmileyCSVImporter()
  - メソッド: importCSV()
  - 状態: デプロイ済み

✅ api/test_import_detailed.php:
  - バージョン: 2.0修正版
  - SQL: current_time → current_dt
  - 状態: デプロイ済み
```

---

## 🎉 **v5.0での達成事項**

### **根本的問題の完全解決**

```yaml
✅ メソッド不整合の完全排除:
  - Databaseクラス: 16メソッド統一
  - SmileyCSVImporter: 自己完結版
  - 全ファイルでメソッド名統一

✅ 自己完結原則の完全準拠:
  - 外部依存の排除
  - コンストラクタ引数なし
  - 内部でDatabase::getInstance()

✅ 設計原則の確立:
  - メソッド統一原則の追加
  - 開発時の遵守事項明確化
  - トラブルシューティング充実
```

# invoicesテーブル仕様（実テーブル構造準拠版）

## 📋 テーブル概要

- **テーブル名**: invoices
- **カラム数**: 22カラム
- **データ件数**: 2件（2025-10-17時点）
- **用途**: 請求書情報の管理

## 🗄️ テーブル構造詳細

```sql
CREATE TABLE invoices (
    -- 基本情報
    id INT(11) AUTO_INCREMENT PRIMARY KEY COMMENT '請求書ID',
    invoice_number VARCHAR(50) NOT NULL UNIQUE COMMENT '請求書番号',
    
    -- 利用者情報
    user_id INT(11) NOT NULL COMMENT '利用者ID',
    user_code VARCHAR(50) NOT NULL COMMENT '利用者コード',
    user_name VARCHAR(100) NOT NULL COMMENT '利用者名',
    
    -- 企業・部署情報
    company_name VARCHAR(100) COMMENT '配達先企業名',
    department VARCHAR(100) COMMENT '部署名',
    
    -- 日付情報
    invoice_date DATE NOT NULL COMMENT '請求書発行日',
    due_date DATE NOT NULL COMMENT '支払期限日',
    period_start DATE NOT NULL COMMENT '請求期間開始日',
    period_end DATE NOT NULL COMMENT '請求期間終了日',
    
    -- 金額情報
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '小計',
    tax_rate DECIMAL(5,2) DEFAULT 10.00 COMMENT '消費税率',
    tax_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '消費税額',
    total_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '合計金額',
    
    -- 請求書種別・ステータス
    invoice_type ENUM('company','department','individual','mixed') DEFAULT 'company' COMMENT '請求書種別',
    status ENUM('draft','issued','paid','partial','overdue','cancelled') DEFAULT 'draft' COMMENT 'ステータス',
    
    -- 支払方法
    payment_method ENUM('cash','bank_transfer','account_debit','paypay','mixed') COMMENT '支払方法',
    paypay_qr_code_path VARCHAR(500) COMMENT 'PayPay QRコードパス',
    
    -- その他
    notes TEXT COMMENT '備考',
    file_path VARCHAR(500) COMMENT 'PDFファイルパス',
    
    -- システム管理
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時',
    
    -- インデックス
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_total_amount (total_amount),
    INDEX idx_invoice_date (invoice_date),
    INDEX idx_due_date (due_date)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='請求書テーブル';
```

## 📊 実データ例

```yaml
実データ（2件）:
  1. invoice_number: INV-20250828-001
     user_name: 福井　美智代
     company_name: 株式会社Smiley
     period: 2025-08-01 〜 2025-08-31
     total_amount: 27,720円
     status: cancelled
     
  2. invoice_number: INV-20250828-002
     user_name: テスト利用者
     company_name: 環境局
     period: 2025-08-01 〜 2025-08-31
     total_amount: 2,640円
     status: cancelled
```

## 🔍 不足カラムの分析

### **company_id カラムの欠如**

**問題**:
- `PaymentManager` と `api/invoices.php` が `company_id` を参照
- エラー: `Unknown column 'i.company_id' in 'field list'`

**原因**:
- テーブル設計時に `company_id` が未実装
- `company_name` のみで企業を識別している

**影響**:
- companiesテーブルとの外部キー関連付けができない
- 企業情報の変更時に整合性が取れない
- 正規化されていない設計

**推奨される修正**:

```sql
-- company_idカラムを追加
ALTER TABLE invoices 
ADD COLUMN company_id INT NULL COMMENT '配達先企業ID' 
AFTER invoice_number;

-- 既存データの整合性を保つ
UPDATE invoices i
INNER JOIN companies c ON i.company_name = c.company_name
SET i.company_id = c.id;

-- インデックス追加
ALTER TABLE invoices 
ADD INDEX idx_company_id (company_id);

-- 外部キー制約追加（オプション）
ALTER TABLE invoices 
ADD CONSTRAINT fk_invoices_company 
FOREIGN KEY (company_id) REFERENCES companies(id) 
ON DELETE SET NULL 
ON UPDATE CASCADE;
```

## 🎯 仕様書への追記推奨事項

### **データベース設計詳細セクションに追加**

```markdown
##### **invoices（請求書）テーブル - 22カラム**
