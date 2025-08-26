<?php
/**
 * シンプルテーブル構造確認ツール
 * 17テーブルの構造をシンプルに表示
 * 
 * @author Claude
 * @version 1.0.0
 * @created 2025-08-27
 */

require_once __DIR__ . '/../classes/Database.php';

// API処理（JSONレスポンス）
if (isset($_GET['action']) && $_GET['action'] === 'get_structure') {
    header('Content-Type: application/json; charset=utf-8');
    
    try {
        $db = Database::getInstance();
        $result = getTableStructure($db);
        
        echo json_encode([
            'success' => true,
            'tables' => $result['tables'],
            'statistics' => $result['statistics']
        ], JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

/**
 * テーブル構造取得（Database::query()使用）
 */
function getTableStructure($db) {
    $tables = [];
    $totalColumns = 0;
    
    try {
        // データベース名取得
        $stmt = $db->query("SELECT DATABASE() as db_name");
        $dbName = $stmt->fetch()['db_name'];
        
        // テーブル一覧取得
        $stmt = $db->query("
            SELECT 
                TABLE_NAME,
                TABLE_COMMENT,
                TABLE_ROWS,
                ENGINE
            FROM INFORMATION_SCHEMA.TABLES 
            WHERE TABLE_SCHEMA = ? 
            AND TABLE_TYPE = 'BASE TABLE'
            ORDER BY TABLE_NAME
        ", [$dbName]);
        
        $tableList = $stmt->fetchAll();
        
        foreach ($tableList as $tableInfo) {
            $tableName = $tableInfo['TABLE_NAME'];
            
            // カラム情報取得
            $stmt = $db->query("
                SELECT 
                    COLUMN_NAME,
                    DATA_TYPE,
                    CHARACTER_MAXIMUM_LENGTH,
                    IS_NULLABLE,
                    COLUMN_DEFAULT,
                    COLUMN_KEY,
                    COLUMN_COMMENT
                FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
                ORDER BY ORDINAL_POSITION
            ", [$dbName, $tableName]);
            
            $columns = $stmt->fetchAll();
            
            $tables[] = [
                'table_name' => $tableName,
                'table_comment' => $tableInfo['TABLE_COMMENT'] ?: '-',
                'table_rows' => $tableInfo['TABLE_ROWS'] ?: 0,
                'engine' => $tableInfo['ENGINE'] ?: '-',
                'columns' => $columns
            ];
            
            $totalColumns += count($columns);
        }
        
        return [
            'tables' => $tables,
            'statistics' => [
                'total_tables' => count($tables),
                'total_columns' => $totalColumns
            ]
        ];
        
    } catch (Exception $e) {
        throw new Exception("テーブル情報取得エラー: " . $e->getMessage());
    }
}

// HTMLページ表示
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>テーブル構造確認 - Smiley配食事業システム</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .header { background: #ff6b35; color: white; padding: 20px; border-radius: 5px; margin-bottom: 20px; }
        .stats { background: white; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .stats span { margin-right: 30px; font-weight: bold; }
        .table-card { background: white; margin-bottom: 20px; border-radius: 5px; overflow: hidden; }
        .table-header { background: #f8f9fa; padding: 15px; border-bottom: 2px solid #ff6b35; }
        .table-name { margin: 0; color: #ff6b35; font-size: 1.2em; }
        .table-info { margin: 5px 0 0 0; color: #666; font-size: 0.9em; }
        .columns-table { width: 100%; border-collapse: collapse; }
        .columns-table th { background: #f8f9fa; padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        .columns-table td { padding: 8px 10px; border-bottom: 1px solid #eee; }
        .data-type { background: #e9ecef; padding: 2px 6px; border-radius: 3px; font-family: monospace; font-size: 0.8em; }
        .key-primary { background: #fff3cd; color: #856404; padding: 2px 6px; border-radius: 3px; font-size: 0.8em; }
        .key-index { background: #d4edda; color: #155724; padding: 2px 6px; border-radius: 3px; font-size: 0.8em; }
        .loading { text-align: center; padding: 40px; color: #666; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="header">
        <h1>📊 テーブル構造確認ツール</h1>
        <p>データベース内の全テーブル構造を表示します</p>
    </div>

    <div class="stats" id="stats">
        <span>総テーブル数: <span id="totalTables">-</span></span>
        <span>総カラム数: <span id="totalColumns">-</span></span>
    </div>

    <div id="tablesContainer">
        <div class="loading">📥 テーブル情報を読み込み中...</div>
    </div>

    <script>
        // データ読み込み
        fetch('?action=get_structure')
            .then(response => response.text())
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    if (data.success) {
                        renderData(data);
                    } else {
                        showError(data.error);
                    }
                } catch (e) {
                    console.error('JSON Parse Error:', e);
                    console.error('Response:', text);
                    showError('JSONパースエラー: ' + e.message + '<br><pre>' + text.substring(0, 500) + '...</pre>');
                }
            })
            .catch(error => {
                console.error('Fetch Error:', error);
                showError('取得エラー: ' + error.message);
            });

        // データ表示
        function renderData(data) {
            // 統計情報更新
            document.getElementById('totalTables').textContent = data.statistics.total_tables;
            document.getElementById('totalColumns').textContent = data.statistics.total_columns;

            // テーブル表示
            let html = '';
            data.tables.forEach(table => {
                html += generateTableCard(table);
            });
            document.getElementById('tablesContainer').innerHTML = html;
        }

        // テーブルカード生成
        function generateTableCard(table) {
            let columnsHtml = '';
            table.columns.forEach(column => {
                const keyBadge = getKeyBadge(column);
                columnsHtml += `
                    <tr>
                        <td><strong>${column.COLUMN_NAME}</strong></td>
                        <td><span class="data-type">${column.DATA_TYPE}</span></td>
                        <td>${column.CHARACTER_MAXIMUM_LENGTH || '-'}</td>
                        <td>${column.IS_NULLABLE}</td>
                        <td>${column.COLUMN_DEFAULT || '-'}</td>
                        <td>${keyBadge}</td>
                        <td><small>${column.COLUMN_COMMENT || '-'}</small></td>
                    </tr>
                `;
            });

            return `
                <div class="table-card">
                    <div class="table-header">
                        <h3 class="table-name">${table.table_name}</h3>
                        <p class="table-info">
                            ${table.columns.length}カラム | 行数: ${table.table_rows} | 
                            エンジン: ${table.engine} | ${table.table_comment}
                        </p>
                    </div>
                    <table class="columns-table">
                        <thead>
                            <tr>
                                <th>カラム名</th>
                                <th>型</th>
                                <th>長さ</th>
                                <th>NULL</th>
                                <th>デフォルト</th>
                                <th>キー</th>
                                <th>コメント</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${columnsHtml}
                        </tbody>
                    </table>
                </div>
            `;
        }

        // キーバッジ生成
        function getKeyBadge(column) {
            if (column.COLUMN_KEY === 'PRI') {
                return '<span class="key-primary">PRIMARY</span>';
            }
            if (column.COLUMN_KEY === 'MUL') {
                return '<span class="key-index">INDEX</span>';
            }
            return '-';
        }

        // エラー表示
        function showError(message) {
            document.getElementById('tablesContainer').innerHTML = `
                <div class="error">
                    <strong>❌ エラー</strong><br>
                    ${message}
                </div>
            `;
        }
    </script>
</body>
</html>
