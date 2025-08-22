<?php
/**
 * システム健全性確認ツール
 * 根本解決後の動作確認用
 */

// 設定読み込み
require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/DatabaseFactory.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>システム健全性チェック - Smiley配食システム</title>
    <style>
        body { 
            font-family: 'Helvetica Neue', Arial, sans-serif; 
            margin: 0; 
            padding: 20px; 
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            color: #333;
        }
        .container { 
            max-width: 1000px; 
            margin: 0 auto; 
            background: white; 
            padding: 40px; 
            border-radius: 12px; 
            box-shadow: 0 4px 20px rgba(0,0,0,0.1); 
        }
        h1 {
            color: #2E8B57;
            text-align: center;
            margin-bottom: 30px;
            font-size: 2.5rem;
        }
        h2 {
            color: #2E8B57;
            border-bottom: 2px solid #2E8B57;
            padding-bottom: 10px;
            margin-top: 40px;
        }
        .status-card {
            background: #f8f9fa;
            border-left: 4px solid #2E8B57;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
        }
        .status-success {
            background: #d4edda;
            border-left-color: #28a745;
            color: #155724;
        }
        .status-warning {
            background: #fff3cd;
            border-left-color: #ffc107;
            color: #856404;
        }
        .status-error {
            background: #f8d7da;
            border-left-color: #dc3545;
            color: #721c24;
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin: 20px 0;
            background: white;
        }
        th, td { 
            padding: 12px; 
            text-align: left; 
            border-bottom: 1px solid #ddd; 
        }
        th { 
            background-color: #2E8B57; 
            color: white;
            font-weight: bold; 
        }
        .badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        .badge-success { background: #28a745; color: white; }
        .badge-danger { background: #dc3545; color: white; }
        .badge-warning { background: #ffc107; color: #333; }
        .badge-info { background: #17a2b8; color: white; }
        .icon { font-size: 1.5em; margin-right: 10px; }
        .action-buttons {
            text-align: center;
            margin: 30px 0;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            margin: 0 10px;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-primary {
            background: #2E8B57;
            color: white;
        }
        .btn-primary:hover {
            background: #245A41;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .code-block {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            padding: 15px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            overflow-x: auto;
            margin: 10px 0;
        }
        .progress-bar {
            background: #e9ecef;
            border-radius: 10px;
            overflow: hidden;
            height: 20px;
            margin: 10px 0;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #28a745, #20c997);
            transition: width 0.3s ease;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🍱 システム健全性チェック</h1>
        
        <?php
        try {
            // システム健全性チェック実行
            $healthCheck = DatabaseFactory::systemHealthCheck();
            $overallHealth = $healthCheck['success'];
            
            // 全体ステータス表示
            $statusClass = $overallHealth ? 'status-success' : 'status-error';
            $statusIcon = $overallHealth ? '✅' : '❌';
            $statusMessage = $overallHealth ? 'システムは正常に動作しています' : 'システムに問題があります';
            ?>
            
            <div class="status-card <?= $statusClass ?>">
                <h2><?= $statusIcon ?> 総合ステータス</h2>
                <p><strong><?= $statusMessage ?></strong></p>
                <p>チェック実行時刻: <?= $healthCheck['timestamp'] ?></p>
            </div>
            
            <h2>📊 詳細チェック結果</h2>
            
            <!-- データベース接続 -->
            <div class="status-card <?= $healthCheck['connection_info']['success'] ? 'status-success' : 'status-error' ?>">
                <h3><span class="icon">🔗</span>データベース接続</h3>
                <p><strong>ステータス:</strong> 
                    <span class="badge <?= $healthCheck['connection_info']['success'] ? 'badge-success' : 'badge-danger' ?>">
                        <?= $healthCheck['connection_info']['success'] ? '接続成功' : '接続失敗' ?>
                    </span>
                </p>
                <p><strong>メッセージ:</strong> <?= htmlspecialchars($healthCheck['connection_info']['message']) ?></p>
                <p><strong>環境:</strong> <span class="badge badge-info"><?= $healthCheck['connection_info']['environment'] ?></span></p>
            </div>
            
            <!-- テーブル状況 -->
            <div class="status-card <?= $healthCheck['tables_info']['success'] ? 'status-success' : 'status-warning' ?>">
                <h3><span class="icon">🗄️</span>データベーステーブル</h3>
                <p><strong>ステータス:</strong> 
                    <span class="badge <?= $healthCheck['tables_info']['success'] ? 'badge-success' : 'badge-warning' ?>">
                        <?= $healthCheck['tables_info']['existing_tables'] ?>/<?= $healthCheck['tables_info']['total_required'] ?> テーブル存在
                    </span>
                </p>
                
                <!-- プログレスバー -->
                <?php 
                $percentage = ($healthCheck['tables_info']['existing_tables'] / $healthCheck['tables_info']['total_required']) * 100;
                ?>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?= $percentage ?>%"></div>
                </div>
                <p><?= round($percentage, 1) ?>% 完了</p>
                
                <?php if (!empty($healthCheck['tables_info']['missing_tables'])): ?>
                <p><strong>不足テーブル:</strong> <?= implode(', ', $healthCheck['tables_info']['missing_tables']) ?></p>
                <?php endif; ?>
                
                <!-- テーブル詳細 -->
                <table>
                    <tr><th>テーブル名</th><th>状態</th></tr>
                    <?php foreach ($healthCheck['tables_info']['table_status'] as $table => $exists): ?>
                    <tr>
                        <td><?= htmlspecialchars($table) ?></td>
                        <td>
                            <span class="badge <?= $exists ? 'badge-success' : 'badge-danger' ?>">
                                <?= $exists ? '存在' : '不存在' ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
            
            <!-- 設定状況 -->
            <div class="status-card <?= $healthCheck['health_check']['configuration'] ? 'status-success' : 'status-error' ?>">
                <h3><span class="icon">⚙️</span>システム設定</h3>
                <table>
                    <tr><th>設定項目</th><th>値</th><th>状態</th></tr>
                    <tr>
                        <td>データベースホスト</td>
                        <td><?= defined('DB_HOST') ? DB_HOST : '未定義' ?></td>
                        <td><span class="badge <?= defined('DB_HOST') ? 'badge-success' : 'badge-danger' ?>"><?= defined('DB_HOST') ? 'OK' : 'NG' ?></span></td>
                    </tr>
                    <tr>
                        <td>データベース名</td>
                        <td><?= defined('DB_NAME') ? DB_NAME : '未定義' ?></td>
                        <td><span class="badge <?= defined('DB_NAME') ? 'badge-success' : 'badge-danger' ?>"><?= defined('DB_NAME') ? 'OK' : 'NG' ?></span></td>
                    </tr>
                    <tr>
                        <td>データベースユーザー</td>
                        <td><?= defined('DB_USER') ? DB_USER : '未定義' ?></td>
                        <td><span class="badge <?= defined('DB_USER') ? 'badge-success' : 'badge-danger' ?>"><?= defined('DB_USER') ? 'OK' : 'NG' ?></span></td>
                    </tr>
                    <tr>
                        <td>パスワード設定</td>
                        <td><?= defined('DB_PASS') && !empty(DB_PASS) ? '設定済み' : '未設定' ?></td>
                        <td><span class="badge <?= defined('DB_PASS') && !empty(DB_PASS) ? 'badge-success' : 'badge-danger' ?>"><?= defined('DB_PASS') && !empty(DB_PASS) ? 'OK' : 'NG' ?></span></td>
                    </tr>
                    <tr>
                        <td>環境</td>
                        <td><?= defined('ENVIRONMENT') ? ENVIRONMENT : '未定義' ?></td>
                        <td><span class="badge badge-info"><?= defined('ENVIRONMENT') ? ENVIRONMENT : 'unknown' ?></span></td>
                    </tr>
                    <tr>
                        <td>デバッグモード</td>
                        <td><?= defined('DEBUG_MODE') ? (DEBUG_MODE ? 'ON' : 'OFF') : '未定義' ?></td>
                        <td><span class="badge <?= defined('DEBUG_MODE') && DEBUG_MODE ? 'badge-warning' : 'badge-success' ?>"><?= defined('DEBUG_MODE') && DEBUG_MODE ? 'ON' : 'OFF' ?></span></td>
                    </tr>
                </table>
            </div>
            
            <!-- システム情報 -->
            <?php
            $dbInfo = DatabaseFactory::getDatabaseInfo();
            if ($dbInfo['success']):
            ?>
            <div class="status-card status-success">
                <h3><span class="icon">📋</span>システム情報</h3>
                <table>
                    <tr><th>項目</th><th>値</th></tr>
                    <tr><td>MySQL バージョン</td><td><?= htmlspecialchars($dbInfo['mysql_version']) ?></td></tr>
                    <tr><td>データベース名</td><td><?= htmlspecialchars($dbInfo['database_name']) ?></td></tr>
                    <tr><td>文字セット</td><td><?= htmlspecialchars($dbInfo['charset']) ?></td></tr>
                    <tr><td>PHP バージョン</td><td><?= PHP_VERSION ?></td></tr>
                    <tr><td>メモリ制限</td><td><?= ini_get('memory_limit') ?></td></tr>
                    <tr><td>アップロード上限</td><td><?= ini_get('upload_max_filesize') ?></td></tr>
                    <tr><td>実行時間制限</td><td><?= ini_get('max_execution_time') ?>秒</td></tr>
                </table>
            </div>
            <?php endif; ?>
            
            <!-- アクションボタン -->
            <div class="action-buttons">
                <?php if ($overallHealth): ?>
                    <a href="../pages/csv_import.php" class="btn btn-primary">📁 CSVインポートを開始</a>
                    <a href="../index.php" class="btn btn-primary">🏠 メインシステムへ</a>
                <?php else: ?>
                    <button onclick="location.reload()" class="btn btn-secondary">🔄 再チェック</button>
                    <?php if (!$healthCheck['tables_info']['success']): ?>
                    <a href="https://<?= $_SERVER['HTTP_HOST'] ?>/phpmyadmin" target="_blank" class="btn btn-secondary">🔧 phpMyAdmin で修復</a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            
            <!-- トラブルシューティング -->
            <?php if (!$overallHealth): ?>
            <div class="status-card status-warning">
                <h3><span class="icon">🛠️</span>トラブルシューティング</h3>
                
                <?php if (!$healthCheck['connection_info']['success']): ?>
                <h4>データベース接続の問題</h4>
                <ol>
                    <li>データベース設定を確認してください</li>
                    <li>MySQLサーバーが稼働しているか確認してください</li>
                    <li>ユーザー権限が正しく設定されているか確認してください</li>
                </ol>
                <div class="code-block">
# エックスサーバーでの確認手順
1. サーバーパネル → MySQL設定
2. データベース名: <?= defined('DB_NAME') ? DB_NAME : '[データベース名]' ?>
3. ユーザー名: <?= defined('DB_USER') ? DB_USER : '[ユーザー名]' ?>
4. ユーザーがデータベースに紐付けられているか確認
                </div>
                <?php endif; ?>
                
                <?php if (!$healthCheck['tables_info']['success']): ?>
                <h4>テーブルの問題</h4>
                <p>以下のテーブルが不足しています。phpMyAdminでテーブル作成SQLを実行してください：</p>
                <div class="code-block">
不足テーブル: <?= implode(', ', $healthCheck['tables_info']['missing_tables']) ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <?php
        } catch (Exception $e) {
            ?>
            <div class="status-card status-error">
                <h2>❌ システムエラー</h2>
                <p><strong>エラーメッセージ:</strong> <?= htmlspecialchars($e->getMessage()) ?></p>
                <p><strong>ファイル:</strong> <?= htmlspecialchars($e->getFile()) ?></p>
                <p><strong>行:</strong> <?= $e->getLine() ?></p>
                
                <h3>対処法</h3>
                <ol>
                    <li>config/database.php ファイルが正しく配置されているか確認</li>
                    <li>classes/Database.php ファイルが正しく配置されているか確認</li>
                    <li>classes/DatabaseFactory.php ファイルが正しく配置されているか確認</li>
                    <li>ファイルの権限が正しく設定されているか確認</li>
                </ol>
            </div>
            <?php
        }
        ?>
        
        <div style="text-align: center; margin-top: 40px; color: #6c757d;">
            <p>Smiley配食事業 請求書管理システム v<?= defined('SYSTEM_VERSION') ? SYSTEM_VERSION : '1.0.0' ?></p>
            <p>© 2025 Smiley配食事業. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
