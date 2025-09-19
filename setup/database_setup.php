<?php
/**
 * Smiley配食事業 集金管理システム
 * データベースセットアップスクリプト
 * 
 * @version 5.0
 * @date 2025-09-19
 * @purpose 集金管理システム用のVIEWとインデックスを作成
 */

// エラー報告設定
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 実行時間制限を延長
set_time_limit(300);

echo "🚀 Smiley配食事業 集金管理システム データベースセットアップ開始\n";
echo "=" . str_repeat("=", 70) . "\n";

try {
    // データベース接続
    require_once __DIR__ . '/../classes/Database.php';
    $db = Database::getInstance();
    
    echo "✅ データベース接続成功\n\n";
    
    // =====================================================
    // STEP 1: 既存VIEWの削除（再作成のため）
    // =====================================================
    
    echo "📋 STEP 1: 既存VIEWの削除\n";
    echo "-" . str_repeat("-", 30) . "\n";
    
    $viewsToDelete = [
        'collection_status_view',
        'collection_statistics_view', 
        'payment_methods_summary_view',
        'urgent_collection_alerts_view',
        'daily_collection_schedule_view'
    ];
    
    foreach ($viewsToDelete as $viewName) {
        try {
            $db->query("DROP VIEW IF EXISTS {$viewName}");
            echo "  ✓ {$viewName} 削除完了\n";
        } catch (Exception $e) {
            echo "  ⚠️  {$viewName} 削除エラー: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n";
    
    // =====================================================
    // STEP 2: インデックス作成（パフォーマンス最適化）
    // =====================================================
    
    echo "📊 STEP 2: インデックス作成\n";
    echo "-" . str_repeat("-", 30) . "\n";
    
    $indexes = [
        "CREATE INDEX IF NOT EXISTS idx_invoices_status_due ON invoices(status, due_date)",
        "CREATE INDEX IF NOT EXISTS idx_payments_invoice_date ON payments(invoice_id, payment_date)",
        "CREATE INDEX IF NOT EXISTS idx_companies_active ON companies(is_active)",
        "CREATE INDEX IF NOT EXISTS idx_invoices_company_issue ON invoices(company_id, issue_date)",
        "CREATE INDEX IF NOT EXISTS idx_orders_delivery_date ON orders(delivery_date)",
        "CREATE INDEX IF NOT EXISTS idx_orders_user_code ON orders(user_code)",
        "CREATE INDEX IF NOT EXISTS idx_users_company_id ON users(company_id)"
    ];
    
    foreach ($indexes as $indexSQL) {
        try {
            $db->query($indexSQL);
            echo "  ✓ インデックス作成: " . substr($indexSQL, strpos($indexSQL, 'idx_'), 30) . "...\n";
        } catch (Exception $e) {
            echo "  ⚠️  インデックス作成エラー: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n";
    
    // =====================================================
    // STEP 3: 集金状況VIEW作成
    // =====================================================
    
    echo "🎯 STEP 3: 集金状況VIEW作成\n";
    echo "-" . str_repeat("-", 30) . "\n";
    
    $collectionStatusViewSQL = "
    CREATE VIEW collection_status_view AS
    SELECT 
        -- 企業情報
        c.id as company_id,
        c.company_name,
        c.contact_person,
        c.phone,
        c.address,
        c.delivery_location,
        c.delivery_instructions,
        c.access_instructions,
        
        -- 請求書情報
        i.id as invoice_id,
        i.invoice_number,
        i.total_amount,
        i.due_date,
        i.status as invoice_status,
        i.issue_date,
        
        -- 支払い情報（集計）
        COALESCE(SUM(p.amount), 0) as paid_amount,
        (i.total_amount - COALESCE(SUM(p.amount), 0)) as outstanding_amount,
        
        -- アラートレベル自動判定
        CASE 
            WHEN i.due_date < CURDATE() THEN 'overdue'
            WHEN i.due_date <= DATE_ADD(CURDATE(), INTERVAL 3 DAY) THEN 'urgent'  
            ELSE 'normal'
        END as alert_level,
        
        -- 期限切れ日数計算
        CASE
            WHEN i.due_date < CURDATE() THEN DATEDIFF(CURDATE(), i.due_date)
            ELSE 0
        END as overdue_days,
        
        -- 期限までの残り日数
        DATEDIFF(i.due_date, CURDATE()) as days_until_due,
        
        -- 支払い状況判定
        CASE
            WHEN COALESCE(SUM(p.amount), 0) = 0 THEN 'unpaid'
            WHEN COALESCE(SUM(p.amount), 0) >= i.total_amount THEN 'paid'
            ELSE 'partially_paid'
        END as payment_status,
        
        -- 最新支払日
        MAX(p.payment_date) as last_payment_date,
        
        -- 支払件数
        COUNT(p.id) as payment_count

    FROM companies c
    JOIN invoices i ON c.id = i.company_id
    LEFT JOIN payments p ON i.id = p.invoice_id

    -- 未回収がある請求書のみ表示（集金業務対象）
    WHERE i.status IN ('issued', 'partially_paid')
      AND (i.total_amount - COALESCE(SUM(p.amount), 0)) > 0
      AND c.is_active = 1

    GROUP BY c.id, i.id

    -- 優先度順でソート（期限切れ→期限間近→通常、期限順）
    ORDER BY 
        CASE alert_level
            WHEN 'overdue' THEN 1
            WHEN 'urgent' THEN 2  
            ELSE 3
        END,
        i.due_date ASC,
        outstanding_amount DESC
    ";
    
    try {
        $db->query($collectionStatusViewSQL);
        echo "  ✅ collection_status_view 作成成功\n";
    } catch (Exception $e) {
        echo "  ❌ collection_status_view 作成失敗: " . $e->getMessage() . "\n";
        throw $e;
    }
    
    // =====================================================
    // STEP 4: 集金統計VIEW作成
    // =====================================================
    
    echo "\n📈 STEP 4: 集金統計VIEW作成\n";
    echo "-" . str_repeat("-", 30) . "\n";
    
    $collectionStatisticsViewSQL = "
    CREATE VIEW collection_statistics_view AS
    SELECT 
        -- 集計期間
        DATE_FORMAT(i.issue_date, '%Y-%m') as month,
        YEAR(i.issue_date) as year,
        MONTH(i.issue_date) as month_num,
        
        -- 請求書統計
        COUNT(i.id) as total_invoices,
        SUM(i.total_amount) as total_amount,
        
        -- 支払い統計
        SUM(CASE WHEN i.status = 'paid' THEN i.total_amount ELSE 0 END) as collected_amount,
        SUM(CASE WHEN i.status != 'paid' THEN i.total_amount ELSE 0 END) as outstanding_amount,
        
        -- 回収率計算
        ROUND(
            SUM(CASE WHEN i.status = 'paid' THEN i.total_amount ELSE 0 END) / 
            NULLIF(SUM(i.total_amount), 0) * 100, 
            1
        ) as collection_rate,
        
        -- 期限切れ統計
        COUNT(CASE WHEN i.due_date < CURDATE() AND i.status != 'paid' THEN 1 END) as overdue_count,
        SUM(CASE WHEN i.due_date < CURDATE() AND i.status != 'paid' THEN i.total_amount ELSE 0 END) as overdue_amount,
        
        -- 企業数統計
        COUNT(DISTINCT i.company_id) as total_companies,
        COUNT(DISTINCT CASE WHEN i.status != 'paid' THEN i.company_id END) as companies_with_outstanding

    FROM invoices i
    WHERE i.issue_date >= DATE_SUB(CURDATE(), INTERVAL 24 MONTH)  -- 過去2年分
    GROUP BY DATE_FORMAT(i.issue_date, '%Y-%m')
    ORDER BY month DESC
    ";
    
    try {
        $db->query($collectionStatisticsViewSQL);
        echo "  ✅ collection_statistics_view 作成成功\n";
    } catch (Exception $e) {
        echo "  ❌ collection_statistics_view 作成失敗: " . $e->getMessage() . "\n";
        throw $e;
    }
    
    // =====================================================
    // STEP 5: 支払方法別統計VIEW作成
    // =====================================================
    
    echo "\n💳 STEP 5: 支払方法別統計VIEW作成\n";
    echo "-" . str_repeat("-", 30) . "\n";
    
    $paymentMethodsSummaryViewSQL = "
    CREATE VIEW payment_methods_summary_view AS
    SELECT 
        p.payment_method,
        CASE p.payment_method
            WHEN 'cash' THEN '💵 現金'
            WHEN 'bank_transfer' THEN '🏦 銀行振込'
            WHEN 'paypay' THEN '📱 PayPay'
            WHEN 'account_debit' THEN '🏦 口座引き落とし'
            WHEN 'mixed' THEN '💳 混合'
            ELSE '💼 その他'
        END as payment_method_display,
        
        -- 件数・金額統計
        COUNT(*) as payment_count,
        SUM(p.amount) as total_amount,
        AVG(p.amount) as average_amount,
        MIN(p.amount) as min_amount,
        MAX(p.amount) as max_amount,
        
        -- 時系列統計
        MIN(p.payment_date) as first_payment_date,
        MAX(p.payment_date) as last_payment_date,
        
        -- 企業数統計
        COUNT(DISTINCT i.company_id) as companies_count

    FROM payments p
    JOIN invoices i ON p.invoice_id = i.id
    WHERE p.payment_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)  -- 過去1年分
    GROUP BY p.payment_method
    ORDER BY total_amount DESC
    ";
    
    try {
        $db->query($paymentMethodsSummaryViewSQL);
        echo "  ✅ payment_methods_summary_view 作成成功\n";
    } catch (Exception $e) {
        echo "  ❌ payment_methods_summary_view 作成失敗: " . $e->getMessage() . "\n";
        // このVIEWは支払いデータがない場合失敗する可能性があるため、警告に留める
        echo "  ⚠️  支払いデータがない場合、このVIEWは後で作成されます\n";
    }
    
    // =====================================================
    // STEP 6: 緊急回収アラートVIEW作成
    // =====================================================
    
    echo "\n🚨 STEP 6: 緊急回収アラートVIEW作成\n";
    echo "-" . str_repeat("-", 30) . "\n";
    
    $urgentCollectionAlertsViewSQL = "
    CREATE VIEW urgent_collection_alerts_view AS
    SELECT 
        csv.*,
        
        -- 緊急度レベル（数値）
        CASE csv.alert_level
            WHEN 'overdue' THEN 
                CASE 
                    WHEN csv.overdue_days > 30 THEN 4  -- Critical（30日超過）
                    WHEN csv.overdue_days > 14 THEN 3  -- High（2週間超過）
                    ELSE 2                             -- Medium（期限切れ）
                END
            WHEN 'urgent' THEN 1                      -- Low（期限間近）
            ELSE 0                                     -- Normal
        END as urgency_level,
        
        -- 緊急度表示
        CASE csv.alert_level
            WHEN 'overdue' THEN 
                CASE 
                    WHEN csv.overdue_days > 30 THEN '🚨 Critical'
                    WHEN csv.overdue_days > 14 THEN '🔴 High'
                    ELSE '🟡 Medium'
                END
            WHEN 'urgent' THEN '🟠 Low'
            ELSE '🟢 Normal'
        END as urgency_display,
        
        -- 優先度スコア（期限切れ日数 + 金額による重み付け）
        (
            CASE csv.alert_level
                WHEN 'overdue' THEN csv.overdue_days * 10
                WHEN 'urgent' THEN 5
                ELSE 1
            END +
            CASE 
                WHEN csv.outstanding_amount >= 100000 THEN 50
                WHEN csv.outstanding_amount >= 50000 THEN 30
                WHEN csv.outstanding_amount >= 20000 THEN 10
                ELSE 1
            END
        ) as priority_score

    FROM collection_status_view csv
    WHERE csv.alert_level IN ('overdue', 'urgent')
    ORDER BY priority_score DESC, csv.outstanding_amount DESC
    ";
    
    try {
        $db->query($urgentCollectionAlertsViewSQL);
        echo "  ✅ urgent_collection_alerts_view 作成成功\n";
    } catch (Exception $e) {
        echo "  ❌ urgent_collection_alerts_view 作成失敗: " . $e->getMessage() . "\n";
        echo "  ⚠️  collection_status_viewが作成されていない可能性があります\n";
    }
    
    // =====================================================
    // STEP 7: 日別集金予定VIEW作成
    // =====================================================
    
    echo "\n📅 STEP 7: 日別集金予定VIEW作成\n";
    echo "-" . str_repeat("-", 30) . "\n";
    
    $dailyCollectionScheduleViewSQL = "
    CREATE VIEW daily_collection_schedule_view AS
    SELECT 
        csv.*,
        
        -- 予定区分
        CASE 
            WHEN csv.due_date = CURDATE() THEN 'today'
            WHEN csv.due_date = DATE_ADD(CURDATE(), INTERVAL 1 DAY) THEN 'tomorrow'
            WHEN csv.due_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 'this_week'
            ELSE 'later'
        END as schedule_category,
        
        -- 予定区分表示
        CASE 
            WHEN csv.due_date = CURDATE() THEN '🎯 今日'
            WHEN csv.due_date = DATE_ADD(CURDATE(), INTERVAL 1 DAY) THEN '📅 明日'
            WHEN csv.due_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN '📆 今週'
            ELSE '🗓️ 来週以降'
        END as schedule_display,
        
        -- 曜日
        DAYOFWEEK(csv.due_date) as day_of_week,
        CASE DAYOFWEEK(csv.due_date)
            WHEN 1 THEN '日'
            WHEN 2 THEN '月'
            WHEN 3 THEN '火'
            WHEN 4 THEN '水'
            WHEN 5 THEN '木'
            WHEN 6 THEN '金'
            WHEN 7 THEN '土'
        END as day_of_week_jp

    FROM collection_status_view csv
    WHERE csv.due_date <= DATE_ADD(CURDATE(), INTERVAL 14 DAY)  -- 2週間以内
    ORDER BY csv.due_date ASC, csv.outstanding_amount DESC
    ";
    
    try {
        $db->query($dailyCollectionScheduleViewSQL);
        echo "  ✅ daily_collection_schedule_view 作成成功\n";
    } catch (Exception $e) {
        echo "  ❌ daily_collection_schedule_view 作成失敗: " . $e->getMessage() . "\n";
        echo "  ⚠️  collection_status_viewが作成されていない可能性があります\n";
    }
    
    // =====================================================
    // STEP 8: VIEW作成確認テスト
    // =====================================================
    
    echo "\n🔍 STEP 8: VIEW作成確認テスト\n";
    echo "-" . str_repeat("-", 30) . "\n";
    
    $testViews = [
        'collection_status_view',
        'collection_statistics_view',
        'payment_methods_summary_view',
        'urgent_collection_alerts_view', 
        'daily_collection_schedule_view'
    ];
    
    $createdViews = [];
    $failedViews = [];
    
    foreach ($testViews as $viewName) {
        try {
            $result = $db->queryOne("SELECT COUNT(*) as count FROM {$viewName}");
            $count = $result['count'] ?? 0;
            echo "  ✅ {$viewName}: {$count}件のデータ\n";
            $createdViews[] = $viewName;
        } catch (Exception $e) {
            echo "  ❌ {$viewName}: エラー - " . $e->getMessage() . "\n";
            $failedViews[] = $viewName;
        }
    }
    
    echo "\n";
    
    // =====================================================
    // STEP 9: PaymentManagerクラステスト
    // =====================================================
    
    echo "🧪 STEP 9: PaymentManagerクラステスト\n";
    echo "-" . str_repeat("-", 30) . "\n";
    
    require_once __DIR__ . '/../classes/PaymentManager.php';
    $paymentManager = new PaymentManager();
    
    // データベース接続テスト
    $dbTest = $paymentManager->testDatabaseConnection();
    if ($dbTest['success']) {
        echo "  ✅ PaymentManager データベース接続: OK\n";
    } else {
        echo "  ❌ PaymentManager データベース接続: " . $dbTest['error'] . "\n";
    }
    
    // サマリーデータ取得テスト
    try {
        $summaryResult = $paymentManager->getCollectionSummary();
        if ($summaryResult['success']) {
            $summary = $summaryResult['data'];
            echo "  ✅ 集金サマリー取得: 今月売上 ¥" . number_format($summary['current_month_sales'] ?? 0) . "\n";
        } else {
            echo "  ⚠️  集金サマリー取得: " . ($summaryResult['error'] ?? 'データなし') . "\n";
        }
    } catch (Exception $e) {
        echo "  ❌ 集金サマリー取得エラー: " . $e->getMessage() . "\n";
    }
    
    // 集金リスト取得テスト
    try {
        $listResult = $paymentManager->getCollectionList(['limit' => 5]);
        if (is_array($listResult)) {
            echo "  ✅ 集金リスト取得: " . count($listResult) . "件\n";
        } else {
            echo "  ⚠️  集金リスト取得: " . ($listResult['error'] ?? 'データなし') . "\n";
        }
    } catch (Exception $e) {
        echo "  ❌ 集金リスト取得エラー: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
    
    // =====================================================
    // 完了報告
    // =====================================================
    
    echo "🎉 データベースセットアップ完了\n";
    echo "=" . str_repeat("=", 70) . "\n";
    echo "✅ 作成成功: " . count($createdViews) . "/" . count($testViews) . " VIEW\n";
    
    if (!empty($createdViews)) {
        echo "\n📋 作成されたVIEW:\n";
        foreach ($createdViews as $view) {
            echo "   • {$view}\n";
        }
    }
    
    if (!empty($failedViews)) {
        echo "\n⚠️ 作成に失敗したVIEW:\n";
        foreach ($failedViews as $view) {
            echo "   • {$view}\n";
        }
        echo "\n※失敗したVIEWは、データが蓄積された後に再実行してください\n";
    }
    
    echo "\n🚀 集金管理システムの使用準備が完了しました！\n";
    echo "\n次の手順:\n";
    echo "1. ブラウザで index.php にアクセス\n";
    echo "2. CSV インポート機能でデータを読み込み\n"; 
    echo "3. 集金管理業務を開始\n";
    
} catch (Exception $e) {
    echo "\n❌ セットアップ中に重大なエラーが発生しました:\n";
    echo "エラー: " . $e->getMessage() . "\n";
    echo "ファイル: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "\n対処方法:\n";
    echo "1. データベース接続設定を確認してください\n";
    echo "2. 必要な権限があることを確認してください\n";
    echo "3. エラーメッセージを確認して修正してください\n";
    
    exit(1);
}

echo "\n" . str_repeat("=", 70) . "\n";
echo "💫 Smiley配食事業 集金管理システム セットアップ完了! 💫\n";
echo str_repeat("=", 70) . "\n";

?>
