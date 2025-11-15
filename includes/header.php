<?php
/**
 * 共通ヘッダーコンポーネント - Material Design
 * Smiley配食事業システム
 *
 * 使い方:
 * $pageTitle = 'ページタイトル';
 * $activePage = 'dashboard'; // dashboard, payments, companies, users, etc.
 * require_once __DIR__ . '/includes/header.php';
 */

// デフォルト値の設定
$pageTitle = $pageTitle ?? 'Smiley配食事業システム';
$activePage = $activePage ?? '';
$showBackButton = $showBackButton ?? false;
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>

    <!-- Bootstrap 5.3 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Material Design Icons -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

    <!-- Google Fonts: Roboto -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">

    <!-- Material Theme CSS -->
    <link href="<?php echo htmlspecialchars($basePath ?? '..'); ?>/assets/css/material-theme.css" rel="stylesheet">

    <?php if (isset($pageSpecificCSS)): ?>
    <!-- Page-specific CSS -->
    <style>
        <?php echo $pageSpecificCSS; ?>
    </style>
    <?php endif; ?>

    <style>
        body {
            font-family: 'Roboto', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding-bottom: 2rem;
        }

        /* Material Design App Bar */
        .app-bar {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            padding: 0;
            margin-bottom: 2rem;
        }

        .app-bar-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem 2rem;
            max-width: 1400px;
            margin: 0 auto;
        }

        .app-bar-brand {
            display: flex;
            align-items: center;
            text-decoration: none;
            color: #2196F3;
            font-size: 1.5rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .app-bar-brand:hover {
            color: #1976D2;
            transform: translateY(-2px);
        }

        .app-bar-brand .material-icons {
            font-size: 2rem;
            margin-right: 0.5rem;
        }

        .app-bar-title {
            display: flex;
            flex-direction: column;
        }

        .app-bar-title-main {
            font-size: 1.25rem;
            font-weight: 500;
            line-height: 1.2;
        }

        .app-bar-title-sub {
            font-size: 0.875rem;
            color: #757575;
            font-weight: 400;
        }

        /* Navigation Menu */
        .nav-menu {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        .nav-item-btn {
            display: flex;
            align-items: center;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            text-decoration: none;
            color: #616161;
            font-weight: 500;
            transition: all 0.3s ease;
            background: transparent;
            border: none;
        }

        .nav-item-btn:hover {
            background: rgba(33, 150, 243, 0.08);
            color: #2196F3;
        }

        .nav-item-btn.active {
            background: #2196F3;
            color: white;
            box-shadow: 0 2px 8px rgba(33, 150, 243, 0.3);
        }

        .nav-item-btn .material-icons {
            font-size: 1.25rem;
            margin-right: 0.5rem;
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            text-decoration: none;
            color: #616161;
            font-weight: 500;
            transition: all 0.3s ease;
            background: rgba(0, 0, 0, 0.04);
            margin-right: 1rem;
        }

        .back-button:hover {
            background: rgba(0, 0, 0, 0.08);
            color: #212121;
            transform: translateX(-4px);
        }

        .back-button .material-icons {
            margin-right: 0.25rem;
        }

        /* Container */
        .main-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .app-bar-content {
                padding: 1rem;
                flex-direction: column;
                gap: 1rem;
            }

            .nav-menu {
                width: 100%;
                justify-content: center;
                flex-wrap: wrap;
            }

            .nav-item-btn {
                font-size: 0.875rem;
                padding: 0.5rem 0.75rem;
            }

            .app-bar-title-main {
                font-size: 1.1rem;
            }

            .app-bar-title-sub {
                font-size: 0.75rem;
            }

            .main-container {
                padding: 0 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Material Design App Bar -->
    <header class="app-bar">
        <div class="app-bar-content">
            <div class="d-flex align-items-center">
                <?php if ($showBackButton): ?>
                <a href="<?php echo htmlspecialchars($backUrl ?? '../index.php'); ?>" class="back-button">
                    <span class="material-icons">arrow_back</span>
                    戻る
                </a>
                <?php endif; ?>

                <a href="<?php echo htmlspecialchars($basePath ?? '..'); ?>/index.php" class="app-bar-brand">
                    <span class="material-icons">restaurant</span>
                    <div class="app-bar-title">
                        <div class="app-bar-title-main">Smiley配食事業システム</div>
                        <div class="app-bar-title-sub">集金管理システム</div>
                    </div>
                </a>
            </div>

            <nav class="nav-menu">
                <a href="<?php echo htmlspecialchars($basePath ?? '..'); ?>/index.php"
                   class="nav-item-btn <?php echo $activePage === 'dashboard' ? 'active' : ''; ?>">
                    <span class="material-icons">dashboard</span>
                    <span>ダッシュボード</span>
                </a>

                <a href="<?php echo htmlspecialchars($basePath ?? '..'); ?>/pages/payments.php"
                   class="nav-item-btn <?php echo $activePage === 'payments' ? 'active' : ''; ?>">
                    <span class="material-icons">payment</span>
                    <span>集金管理</span>
                </a>

                <a href="<?php echo htmlspecialchars($basePath ?? '..'); ?>/pages/companies.php"
                   class="nav-item-btn <?php echo $activePage === 'companies' ? 'active' : ''; ?>">
                    <span class="material-icons">business</span>
                    <span>企業管理</span>
                </a>

                <a href="<?php echo htmlspecialchars($basePath ?? '..'); ?>/pages/csv_import.php"
                   class="nav-item-btn <?php echo $activePage === 'import' ? 'active' : ''; ?>">
                    <span class="material-icons">upload_file</span>
                    <span>データ取込</span>
                </a>

                <?php if (defined('DEBUG_MODE') && DEBUG_MODE): ?>
                <a href="<?php echo htmlspecialchars($basePath ?? '..'); ?>/pages/diagnosis.php"
                   class="nav-item-btn <?php echo $activePage === 'diagnosis' ? 'active' : ''; ?>"
                   style="background: rgba(255, 193, 7, 0.1);">
                    <span class="material-icons">bug_report</span>
                    <span>診断</span>
                </a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <!-- Main Container -->
    <div class="main-container">
