<?php
/**
 * Smiley配食事業 請求書・集金管理システム
 * メイン画面（index.php）
 * PC操作不慣れな方向けの直感的なUI設計
 */

require_once __DIR__ . '/config/database.php';

// セッション開始
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// データベース接続
try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
} catch (Exception $e) {
    $error_message = "システムエラーが発生しました。管理者にお問い合わせください。";
    if (DEBUG_MODE) {
        $error_message = "データベース接続エラー: " . $e->getMessage();
    }
}

// ダッシュボードデータ取得
function getDashboardData($pdo) {
    try {
        $data = [];
        $currentMonth = date('Y-m');
        $currentYear = date('Y');
        
        // 今月の売上
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(total_amount), 0) as monthly_sales,
                   COUNT(*) as monthly_orders,
                   COUNT(DISTINCT user_id) as monthly_users
            FROM orders 
            WHERE DATE_FORMAT(delivery_date, '%Y-%m') = ?
        ");
        $stmt->execute([$currentMonth]);
        $monthlySales = $stmt->fetch();
        
        // 配達先企業数・利用者数
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(DISTINCT c.id) as total_companies,
                COUNT(DISTINCT u.id) as total_users,
                COUNT(DISTINCT CASE WHEN c.is_active = 1 THEN c.id END) as active_companies
            FROM companies c
            LEFT JOIN users u ON c.id = u.company_id
        ");
        $stmt->execute();
        $companyStats = $stmt->fetch();
        
        // 未回収金額
        $stmt = $pdo->prepare("
            SELECT 
                COALESCE(SUM(i.total_amount - COALESCE(p.paid_amount, 0)), 0) as unpaid_amount,
                COUNT(i.id) as unpaid_invoices
            FROM invoices i
            LEFT JOIN (
                SELECT invoice_id, SUM(amount) as paid_amount
                FROM payments 
                WHERE payment_status = 'completed'
                GROUP BY invoice_id
            ) p ON i.id = p.invoice_id
            WHERE i.status IN ('issued', 'overdue')
            AND (i.total_amount - COALESCE(p.paid_amount, 0)) > 0
        ");
        $stmt->execute();
        $unpaidStats = $stmt->fetch();
        
        // 期限切れ件数
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as overdue_count
            FROM invoices 
            WHERE (status = 'overdue' OR (status = 'issued' AND due_date < CURDATE()))
            AND total_amount > 0
        ");
        $stmt->execute();
        $overdueStats = $stmt->fetch();
        
        // 今月の請求書件数
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as monthly_invoices
            FROM invoices 
            WHERE DATE_FORMAT(invoice_date, '%Y-%m') = ?
        ");
        $stmt->execute([$currentMonth]);
        $invoiceStats = $stmt->fetch();
        
        // 最近のアクティビティ（シンプル版）
        $stmt = $pdo->prepare("
            SELECT 
                'order' as type,
                CONCAT(company_name, ' - ', user_name) as title,
                CONCAT('¥', FORMAT(total_amount, 0), ' (', product_name, ')') as description,
                delivery_date as activity_date,
                created_at
            FROM orders 
            ORDER BY created_at DESC 
            LIMIT 5
        ");
        $stmt->execute();
        $recentActivities = $stmt->fetchAll();
        
        return [
            'monthly_sales' => $monthlySales['monthly_sales'] ?? 0,
            'monthly_orders' => $monthlySales['monthly_orders'] ?? 0,
            'monthly_users' => $monthlySales['monthly_users'] ?? 0,
            'total_companies' => $companyStats['total_companies'] ?? 0,
            'total_users' => $companyStats['total_users'] ?? 0,
            'active_companies' => $companyStats['active_companies'] ?? 0,
            'unpaid_amount' => $unpaidStats['unpaid_amount'] ?? 0,
            'unpaid_invoices' => $unpaidStats['unpaid_invoices'] ?? 0,
            'overdue_count' => $overdueStats['overdue_count'] ?? 0,
            'monthly_invoices' => $invoiceStats['monthly_invoices'] ?? 0,
            'recent_activities' => $recentActivities
        ];
        
    } catch (Exception $e) {
        error_log("Dashboard data error: " . $e->getMessage());
        return [
            'monthly_sales' => 0,
            'monthly_orders' => 0,
            'monthly_users' => 0,
            'total_companies' => 0,
            'total_users' => 0,
            'active_companies' => 0,
            'unpaid_amount' => 0,
            'unpaid_invoices' => 0,
            'overdue_count' => 0,
            'monthly_invoices' => 0,
            'recent_activities' => []
        ];
    }
}

// ダッシュボードデータ取得
$dashboardData = [];
if (isset($pdo)) {
    $dashboardData = getDashboardData($pdo);
}

// 次のアクション提案
function getNextActionSuggestion($data) {
    if ($data['overdue_count'] > 0) {
        return [
            'priority' => 'high',
            'icon' => '⚠️',
            'message' => $data['overdue_count'] . '件の請求書が期限切れです。回収作業を行ってください。',
            'action' => 'payment_management'
        ];
    }
    
    if ($data['monthly_invoices'] == 0 && date('d') > 25) {
        return [
            'priority' => 'medium',
            'icon' => '📄',
            'message' => '今月の請求書がまだ作成されていません。月末なので作成をお勧めします。',
            'action' => 'generate_invoices'
        ];
    }
    
    if ($data['monthly_orders'] == 0) {
        return [
            'priority' => 'medium',
            'icon' => '📊',
            'message' => '今月の注文データがありません。CSVファイルを取り込んでください。',
            'action' => 'import_csv'
        ];
    }
    
    return [
        'priority' => 'low',
        'icon' => '✨',
        'message' => 'システムは正常に動作しています。CSVファイルを取り込んで請求書を作成しましょう。',
        'action' => 'import_csv'
    ];
}

$nextAction = getNextActionSuggestion($dashboardData);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🍱 Smiley配食 請求書・集金管理システム</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- カスタムCSS -->
    <style>
        /* PC操作不慣れな方向けのUI設計 */
        body {
            font-family: 'Helvetica Neue', Arial, 'Hiragino Kaku Gothic ProN', 'Hiragino Sans', Meiryo, sans-serif;
            background-color: #f8f9fa;
            font-size: 16px;
        }
        
        .main-btn {
            min-height: 120px;
            font-size: 1.2rem;
            font-weight: bold;
            border-radius: 15px;
            transition: all 0.3s ease;
            border: 3px solid transparent;
            margin-bottom: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .main-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.15);
            border-color: rgba(255,255,255,0.3);
        }
        
        .main-btn:active {
            transform: translateY(0);
        }
        
        .main-btn .btn-icon {
            font-size: 2.5rem;
            display: block;
            margin-bottom: 8px;
        }
        
        .stats-card {
            border-radius: 15px;
            border: none;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: transform 0.3s ease;
        }
        
        .stats-card:hover {
            transform: translateY(-2px);
        }
        
        .stats-value {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stats-label {
            color: #6c757d;
            font-size: 0.9rem;
            margin-bottom: 0;
        }
        
        .header-title {
            color: #2c3e50;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .header-subtitle {
            color: #7f8c8d;
            font-size: 1rem;
        }
        
        .action-alert {
            border-radius: 12px;
            border: none;
            font-size: 1.1rem;
        }
        
        .action-alert.priority-high {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
            color: white;
        }
        
        .action-alert.priority-medium {
            background: linear-gradient(135deg, #feca57 0%, #ff9ff3 100%);
            color: #2c3e50;
        }
        
        .action-alert.priority-low {
            background: linear-gradient(135deg, #48cae4 0%, #023e8a 100%);
            color: white;
        }
        
        .activity-item {
            border-left: 4px solid #007bff;
            padding-left: 15px;
            margin-bottom: 15px;
        }
        
        .activity-date {
            font-size: 0.85rem;
            color: #6c757d;
        }
        
        @media (max-width: 768px) {
            .main-btn {
                min-height: 100px;
                font-size: 1rem;
            }
            
            .stats-value {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid px-4 py-3">
        <!-- ヘッダー -->
        <header class="mb-4">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="header-title">🍱 Smiley配食事業</h1>
                    <p class="header-subtitle">請求書・集金管理システム</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <span class="badge bg-success px-3 py-2">
                        <?= ENVIRONMENT === 'test' ? 'テスト環境' : '本番環境' ?>
                    </span>
                    <div class="text-muted small mt-1">
                        <?= date('Y年m月d日 H:i') ?>
                    </div>
                </div>
            </div>
        </header>

        <?php if (isset($error_message)): ?>
        <!-- エラー表示 -->
        <div class="alert alert-danger" role="alert">
            <h4 class="alert-heading">⚠️ システムエラー</h4>
            <p><?= htmlspecialchars($error_message) ?></p>
        </div>
        <?php else: ?>

        <!-- ダッシュボード統計 -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card stats-card text-center">
                    <div class="card-body">
                        <div class="stats-value text-primary">
                            ¥<?= number_format($dashboardData['monthly_sales']) ?>
                        </div>
                        <p class="stats-label">今月の売上</p>
                        <small class="text-muted"><?= $dashboardData['monthly_orders'] ?>件の注文</small>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card stats-card text-center">
                    <div class="card-body">
                        <div class="stats-value text-warning">
                            ¥<?= number_format($dashboardData['unpaid_amount']) ?>
                        </div>
                        <p class="stats-label">未回収金額</p>
                        <small class="text-muted"><?= $dashboardData['unpaid_invoices'] ?>件の請求書</small>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card stats-card text-center">
                    <div class="card-body">
                        <div class="stats-value text-info">
                            <?= $dashboardData['active_companies'] ?>
                        </div>
                        <p class="stats-label">配達先企業</p>
                        <small class="text-muted">利用者<?= $dashboardData['total_users'] ?>名</small>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card stats-card text-center">
                    <div class="card-body">
                        <div class="stats-value text-danger">
                            <?= $dashboardData['overdue_count'] ?>
                        </div>
                        <p class="stats-label">期限切れ</p>
                        <small class="text-muted">緊急対応必要</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- 次にすべきアクション -->
        <div class="alert action-alert priority-<?= $nextAction['priority'] ?> mb-4" role="alert">
            <h5><?= $nextAction['icon'] ?> 次にすることは：</h5>
            <p class="mb-0"><?= $nextAction['message'] ?></p>
        </div>

        <!-- メイン操作ボタン -->
        <div class="row g-3 mb-4">
            <div class="col-lg-3 col-md-6">
                <button class="btn btn-primary w-100 main-btn" onclick="location.href='pages/csv_import.php'">
                    <span class="btn-icon">📊</span>
                    <div>データ取り込み</div>
                    <small>CSVファイルから注文データを読み込み</small>
                </button>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <button class="btn btn-success w-100 main-btn" onclick="location.href='pages/invoice_generate.php'">
                    <span class="btn-icon">📄</span>
                    <div>請求書作成</div>
                    <small>配達先企業別に請求書を生成</small>
                </button>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <button class="btn btn-info w-100 main-btn" onclick="location.href='pages/payment_management.php'">
                    <span class="btn-icon">💰</span>
                    <div>集金管理</div>
                    <small>支払い状況・未回収金額の管理</small>
                </button>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <button class="btn btn-warning w-100 main-btn" onclick="location.href='pages/companies.php'">
                    <span class="btn-icon">🏢</span>
                    <div>配達先企業</div>
                    <small>企業・部署・利用者の管理</small>
                </button>
            </div>
        </div>

        <!-- 最近のアクティビティ -->
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">📈 最近のアクティビティ</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($dashboardData['recent_activities'])): ?>
                        <div class="text-center text-muted py-4">
                            <p>📋 最近のアクティビティはありません</p>
                            <p>CSVファイルを取り込んで、システムを開始しましょう</p>
                        </div>
                        <?php else: ?>
                        <?php foreach ($dashboardData['recent_activities'] as $activity): ?>
                        <div class="activity-item">
                            <div class="fw-bold"><?= htmlspecialchars($activity['title']) ?></div>
                            <div class="text-muted"><?= htmlspecialchars($activity['description']) ?></div>
                            <div class="activity-date"><?= date('m/d H:i', strtotime($activity['created_at'])) ?></div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">🔧 システム情報</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled mb-0">
                            <li><strong>データベース:</strong> <?= DB_NAME ?></li>
                            <li><strong>環境:</strong> <?= ENVIRONMENT ?></li>
                            <li><strong>バージョン:</strong> <?= SYSTEM_VERSION ?></li>
                            <li class="mt-2">
                                <a href="config/database.php?debug=env" class="btn btn-outline-secondary btn-sm" target="_blank">
                                    🔍 詳細情報
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
                
                <!-- クイックヘルプ -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h5 class="mb-0">💡 クイックヘルプ</h5>
                    </div>
                    <div class="card-body">
                        <div class="small">
                            <p><strong>📊 データ取り込み:</strong><br>
                            Smiley配食システムのCSVファイルをアップロード</p>
                            
                            <p><strong>📄 請求書作成:</strong><br>
                            配達先企業別に月次請求書を自動生成</p>
                            
                            <p><strong>💰 集金管理:</strong><br>
                            支払い状況の確認と記録</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php endif; ?>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- カスタムJavaScript -->
    <script>
        // PC操作不慣れな方向けの追加機能
        document.addEventListener('DOMContentLoaded', function() {
            // ボタンクリック時の視覚的フィードバック
            const buttons = document.querySelectorAll('.main-btn');
            buttons.forEach(button => {
                button.addEventListener('click', function() {
                    this.style.transform = 'scale(0.95)';
                    setTimeout(() => {
                        this.style.transform = '';
                    }, 150);
                });
            });
            
            // ツールチップの有効化
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
        
        // アラート自動フェード（必要に応じて）
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert:not(.action-alert)');
            alerts.forEach(alert => {
                if (alert.classList.contains('alert-success')) {
                    alert.style.transition = 'opacity 0.5s';
                    alert.style.opacity = '0.8';
                }
            });
        }, 5000);
    </script>
</body>
</html>
