#!/bin/bash
# データベースクラス重複解決スクリプト
# Smiley配食システム根本解決版

echo "🔧 Smiley配食システム - データベースクラス重複解決"
echo "=============================================="

# 現在のディレクトリ確認
if [ ! -f "index.php" ]; then
    echo "❌ billing-systemのルートディレクトリで実行してください"
    exit 1
fi

echo "✅ billing-systemルートディレクトリで実行中"

# バックアップディレクトリ作成
BACKUP_DIR="backup_$(date +%Y%m%d_%H%M%S)"
mkdir -p "$BACKUP_DIR"
echo "📁 バックアップディレクトリ作成: $BACKUP_DIR"

# 重複ファイルのバックアップ
if [ -f "classes/Database.php" ]; then
    cp "classes/Database.php" "$BACKUP_DIR/Database_old.php"
    echo "📦 classes/Database.php をバックアップ"
fi

# classes/Database.php の削除
if [ -f "classes/Database.php" ]; then
    rm "classes/Database.php"
    echo "🗑️  classes/Database.php を削除"
else
    echo "ℹ️  classes/Database.php は存在しません"
fi

# 確認
if [ ! -f "classes/Database.php" ]; then
    echo "✅ classes/Database.php の削除完了"
else
    echo "❌ classes/Database.php の削除失敗"
    exit 1
fi

# config/database.php の存在確認
if [ -f "config/database.php" ]; then
    echo "✅ config/database.php が存在（こちらを使用）"
else
    echo "❌ config/database.php が存在しません"
    exit 1
fi

echo ""
echo "🎉 データベースクラス重複解決完了！"
echo "=============================================="
echo "📋 実行した内容:"
echo "   - classes/Database.php を削除"
echo "   - config/database.php のDatabaseクラス(Singleton)を使用"
echo "   - バックアップ: $BACKUP_DIR/Database_old.php"
echo ""
echo "🔄 次のステップ:"
echo "   1. GitHubにプッシュ"
echo "   2. 元のエラーページで動作確認"
echo "   3. PaymentManager.php の正常動作確認"
echo ""
echo "✨ これで「Cannot declare class Database」エラーが解決されます！"
