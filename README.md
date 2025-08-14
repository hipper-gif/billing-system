# Smiley配食事業 請求書・集金管理システム

## 📋 プロジェクト概要

PC操作に不慣れな方でも使いやすい、お弁当注文システムの請求書発行・集金管理機能を補完するWebアプリケーションです。

### 🎯 主要機能
- 📊 **CSV データ取り込み** - 既存システムからの注文データインポート
- 📄 **請求書自動生成** - 会社・個人・混合形式に対応
- 💰 **集金管理** - 現金・振込・口座引き落とし対応
- 📈 **売上分析** - 月次レポート・利用者別統計
- 🧾 **領収書発行** - 収入印紙対応・分割発行機能

### 🌐 環境構成
- **テスト環境**: https://twinklemark.xsrv.jp/Smiley/meal-delivery/billing-system/
- **本番環境**: https://tw1nkle.com/Smiley/meal-delivery/billing-system/
- **GitHub**: https://github.com/hipper-gif/billing-system.git

## 🛠️ 技術スタック

### バックエンド
- **言語**: PHP 8.2.28
- **データベース**: MySQL 8.0 (エックスサーバー)
- **PDF生成**: TCPDF 6.6+

### フロントエンド
- **フレームワーク**: Bootstrap 5
- **JavaScript**: Vanilla JS
- **UI設計**: PC操作不慣れな方向け（大きなボタン・分かりやすい配色）

### インフラ・デプロイ
- **ホスティング**: エックスサーバー スタンダードプラン
- **CI/CD**: GitHub Actions
- **自動デプロイ**: develop → テスト環境
- **手動デプロイ**: main → 本番環境（承認制）

## 🚀 セットアップ手順

### 1. リポジトリクローン
```bash
git clone https://github.com/hipper-gif/billing-system.git
cd billing-system
```

### 2. ローカル開発環境（XAMPP）
```bash
# XAMPPのhtdocsにコピー
cp -r billing-system/ C:/xampp/htdocs/

# データベース作成（phpMyAdmin）
CREATE DATABASE bentosystem_local CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

# ブラウザでアクセス
http://localhost/billing-system/
```

### 3. エックスサーバー設定

#### SSH鍵設定
```bash
# SSH鍵生成
ssh-keygen -t rsa -b 4096 -C "billing-system-test" -f ~/.ssh/billing_test_key
ssh-keygen -t rsa -b 4096 -C "billing-system-prod" -f ~/.ssh/billing_prod_key

# エックスサーバーのサーバーパネル > SSH設定 で公開鍵を登録
```

#### GitHub Secrets設定
```
TEST_SSH_PRIVATE_KEY: 秘密鍵の内容
TEST_REMOTE_HOST: sv16114.xserver.jp
TEST_REMOTE_USER: twinklemark
TEST_REMOTE_PATH: /home/twinklemark/twinklemark.xsrv.jp/public_html/Smiley/meal-delivery/billing-system

PROD_SSH_PRIVATE_KEY: 秘密鍵の内容
PROD_REMOTE_HOST: sv16114.xserver.jp
PROD_REMOTE_USER: tw1nkle
PROD_REMOTE_PATH: /home/tw1nkle/tw1nkle.com/public_html/Smiley/meal-delivery/billing-system
```

### 4. データベース初期化
```bash
# 各環境のデータベースをエックスサーバー管理画面で作成
# テスト: twinklemark_billing_test
# 本番: tw1nkle_billing_prod

# config/database.php でパスワード設定
# 各環境のWebページでDB初期化実行
```

## 📁 ディレクトリ構成

```
billing-system/
├── index.php                 # メイン画面
├── config/
│   ├── database.php         # 環境別DB接続設定
│   └── constants.php        # 定数定義
├── api/
│   ├── test.php            # システムテスト API
│   ├── install.php         # DB初期化 API
│   ├── import.php          # CSV インポート API
│   ├── invoice.php         # 請求書生成 API
│   ├── payment.php         # 支払い管理 API
│   └── receipt.php         # 領収書生成 API
├── classes/
│   ├── Database.php        # DB接続クラス
│   ├── CsvImporter.php     # CSV処理クラス
│   ├── InvoiceGenerator.php # 請求書生成クラス
│   ├── PdfGenerator.php    # PDF生成クラス
│   └── PaymentManager.php  # 支払い管理クラス
├── assets/
│   ├── css/style.css       # カスタムCSS
│   ├── js/app.js          # JavaScript
│   └── images/logo.png    # 会社ロゴ
├── uploads/               # CSVアップロード先
├── temp/                  # 一時ファイル
├── logs/                  # ログファイル
├── cache/                 # キャッシュファイル
├── sql/
│   └── init.sql          # データベース初期化SQL
├── .github/
│   └── workflows/        # GitHub Actions設定
└── docs/                 # ドキュメント
```

## 🔄 デプロイフロー

### 自動デプロイ（テスト環境）
```bash
git checkout develop
git add .
git commit -m "機能追加: XXX"
git push origin develop
# → GitHub Actions が自動実行
# → テスト環境に自動デプロイ
```

### 手動デプロイ（本番環境）
```bash
git checkout main
git merge develop
git push origin main

# GitHub > Actions > Deploy to Production Environment
# → "Run workflow" をクリック
# → confirm_deployment: "yes" を選択
# → デプロイメントの説明を入力
# → "Run workflow" で実行
```

## 🗄️ データベース設計

### 主要テーブル
- **users** - 利用者マスタ
- **products** - 商品マスタ  
- **orders** - 注文データ
- **invoices** - 請求書
- **invoice_details** - 請求書明細
- **payments** - 支払い記録
- **receipts** - 領収書
- **import_logs** - インポートログ
- **system_settings** - システム設定

### ER図
```
users (1) ←→ (N) orders
orders (N) ←→ (1) invoices ←→ (N) invoice_details
invoices (1) ←→ (N) payments
invoices (1) ←→ (N) receipts
```

## 🔧 開発フロー

### ブランチ戦略
- **main** - 本番環境（手動デプロイ）
- **develop** - テスト環境（自動デプロイ）
- **feature/xxx** - 機能開発ブランチ

### 開発手順
1. `git checkout develop`
2. `git checkout -b feature/新機能名`
3. 機能開発・テスト
4. `git checkout develop`
5. `git merge feature/新機能名`
6. `git push origin develop` → テスト環境デプロイ
7. テスト環境で動作確認
8. `git checkout main`
9. `git merge develop`
10. `git push origin main` → 本番環境デプロイ準備

## 🛡️ セキュリティ対策

### 実装済み
- SQLインジェクション対策（PDO準備済み文）
- XSS対策（HTMLエスケープ）
- CSRF対策（トークン検証）
- ファイルアップロード制限
- 設定ファイルアクセス拒否

### 追加予定
- ログイン認証システム
- アクセス権限管理
- 操作ログ記録
- セッション管理強化

## 📈 パフォーマンス要件

- **画面表示**: 2秒以内
- **CSV処理**: 1,000件/5秒以内  
- **PDF生成**: 1件/2秒以内
- **同時アクセス**: 10ユーザー

## 🎨 UI/UX設計指針

### PC操作不慣れな方向け配慮
- **大きなボタン**: 最小60x40px
- **分かりやすい配色**: 重要=赤、安全=緑、情報=青
- **操作ガイド**: 「次に○○してください」
- **確認画面**: 重要操作前の確認ダイアログ

### レスポンシブ対応
- スマートフォン・タブレット対応
- Bootstrap 5によるモバイルファースト
- タッチ操作対応

## 🚀 開発ロードマップ

### Phase 1: 基盤構築 ✅
- [x] GitHub リポジトリ設定
- [x] エックスサーバー環境準備  
- [x] GitHub Actions 自動デプロイ設定
- [x] データベース設計・初期化

### Phase 2: 基本機能（開発中）
- [ ] CSV インポート機能
- [ ] 請求書生成機能  
- [ ] PDF出力機能
- [ ] 集金管理機能

### Phase 3: 拡張機能
- [ ] 領収書発行機能
- [ ] メール送信機能
- [ ] レポート・分析機能
- [ ] バックアップ・復旧機能

### Phase 4: 品質向上
- [ ] ユーザビリティテスト
- [ ] セキュリティ監査
- [ ] パフォーマンス最適化
- [ ] ドキュメント整備

## 🤝 貢献方法

1. Issue を作成して機能要求・バグ報告
2. Fork してブランチ作成
3. 機能開発・テスト作成
4. Pull Request 作成
5. コードレビュー・マージ

## 📞 サポート

### 開発者
- **開発チーム**: Claude + hipper-gif
- **GitHub**: https://github.com/hipper-gif/billing-system

### 問い合わせ
- GitHub Issues でバグ報告・機能要求
- プルリクエストで改善提案

## 📄 ライセンス

本プロジェクトは Smiley配食事業専用のカスタムシステムです。

---

**Last Updated**: 2025年8月14日  
**Version**: 1.0.0-dev
