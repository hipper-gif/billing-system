<?php
/**
 * テーブル構造確認デバッグツール
 * データベース内の全17テーブルの構造を表示
 * 
 * @author Claude
 * @version 1.0.0
 * @created 2025-08-27
 */

require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/SecurityHelper.php';

// セキュリティヘッダー設定
SecurityHelper::setSecurityHeaders();

$pageTitle = 'テーブル構造確認 - Smiley配食事業システム';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --smiley-primary: #ff6b35;
            --smiley-secondary: #ffa500;
            --smiley-success: #4caf50;
            --smiley-warning: #ff9800;
            --smiley-danger: #f44336;
        }

        .smiley-header {
            background: linear-gradient(135deg, var(--smiley-primary), var(--smiley-secondary));
            color: white;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
        }

        .table-card {
            border: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border-radius: 8px;
            margin-bottom: 2rem;
            transition: transform 0.2s ease;
        }

        .table-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }

        .table-header {
            background: linear-gradient(90deg, #f8f9fa, #e9ecef);
            border-bottom: 2px solid var(--smiley-primary);
            padding: 1rem 1.5rem;
            border-radius: 8px 8px 0 0 !important;
        }

        .table-name {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--smiley-primary);
            margin: 0;
        }

        .table-info {
            font-size: 0.9rem;
            color: #6c757d;
            margin: 0;
        }

        .column-table {
            margin-bottom: 0;
            font-size: 0.9rem;
        }

        .column-table th {
            background-color: #f8f9fa;
            border-top: none;
            font-weight: 600;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .column-table td {
            vertical-align: middle;
        }

        .data-type {
            font-family: 'Courier New', monospace;
            background-color: #e9ecef;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.8rem;
        }

        .nullable-yes { color: var(--smiley-warning); }
        .nullable-no { color: var(--smiley-success); }

        .key-primary { 
            background-color: #fff3cd; 
            color: #856404; 
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .key-foreign { 
            background-color: #d4edda; 
            color: #155724; 
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .key-unique { 
            background-color: #cce5ff; 
            color: #004085; 
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .key-index { 
            background-color: #f8f9fa; 
            color: #495057; 
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
        }

        .stats-card {
            background: linear-gradient(135deg, #e3f2fd, #bbdefb);
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-item {
            text-align: center;
            padding: 0.5rem;
        }

        .stat-value {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--smiley-primary);
        }

        .stat-label {
            font-size: 0.9rem;
            color: #666;
            margin-top: 0.25rem;
        }

        .table-container {
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid #dee2e6;
            border-radius: 0 0 8px 8px;
        }

        .search-box {
            position: relative;
            margin-bottom: 2rem;
        }

        .search-box .form-control {
            padding-left: 2.5rem;
            border-radius: 25px;
            border: 2px solid #e9ecef;
        }

        .search-box .form-control:focus {
            border-color: var(--smiley-primary);
            box-shadow: 0 0 0 0.2rem rgba(255, 107, 53, 0.25);
        }

        .search-box .fa-search {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }

        .error-alert {
            background-color: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .loading-spinner {
            text-align: center;
            padding: 2rem;
            color: #6c757d;
        }

        @media (max-width: 768px) {
            .column-table {
                font-size: 0.8rem;
            }
            
            .data-type {
                font-size: 0.7rem;
            }
            
            .table-name {
                font-size: 1rem;
            }
        }
    </style>
</head>
<body class="bg-light">
    <!-- ナビゲーション -->
    <nav class="navbar navbar-expand-lg navbar-dark" style="background: linear-gradient(135deg, var(--smiley-primary), var(--smiley-secondary));">
        <div class="container">
            <a class="navbar-brand" href="../index.php">
                <i class="fas fa-utensils me-2"></i>Smiley配食事業システム
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="../pages/companies.php">企業管理</a>
                <a class="nav-link" href="../pages/csv_import.php">CSV取込</a>
                <a class="nav-link active" href="../pages/table_debug.php">テーブル確認</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- ヘッダー -->
        <div class="smiley-header text-center">
            <h1><i class="fas fa-database me-3"></i>データベーステーブル構造確認</h1>
            <p class="mb-0">全17テーブルの構造とカラム情報を表示します</p>
        </div>

        <!-- 統計情報 -->
        <div class="stats-card" id="statsSection">
            <div class="row">
                <div class="col-md-3 col-6">
                    <div class="stat-item">
                        <div class="stat-value" id="totalTables">-</div>
                        <div class="stat-label">総テーブル数</div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-item">
                        <div class="stat-value" id="totalColumns">-</div>
                        <div class="stat-label">総カラム数</div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-item">
                        <div class="stat-value" id="totalIndexes">-</div>
                        <div class="stat-label">総インデックス数</div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-item">
                        <div class="stat-value" id="totalConstraints">-</div>
                        <div class="stat-label">制約数</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 検索ボックス -->
        <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="text" class="form-control" id="searchInput" placeholder="テーブル名またはカラム名で検索...">
        </div>

        <!-- テーブル一覧 -->
        <div id="tablesContainer">
            <div class="loading-spinner">
                <i class="fas fa-spinner fa-spin me-2"></i>テーブル情報を読み込み中...
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        let tableData = [];
        let filteredData = [];

        // 初期化
        document.addEventListener('DOMContentLoaded', function() {
            loadTableStructure();
            setupEventListeners();
        });

        // イベントリスナー設定
        function setupEventListeners() {
            // 検索フィルター
            document.getElementById('searchInput').addEventListener('input', function() {
                filterTables(this.value);
            });
        }

        // テーブル構造読み込み
        function loadTableStructure() {
            showLoading(true);
            
            // PHPから直接テーブル構造を取得
            fetch('table_debug.php?action=get_structure')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        tableData = data.tables;
                        filteredData = tableData;
                        updateStatistics(data.statistics);
                        renderTables();
                    } else {
                        showError(data.error);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showError('テーブル構造の取得に失敗しました: ' + error.message);
                })
                .finally(() => {
                    showLoading(false);
                });
        }

        // 統計情報更新
        function updateStatistics(stats) {
            document.getElementById('totalTables').textContent = stats.total_tables;
            document.getElementById('totalColumns').textContent = stats.total_columns;
            document.getElementById('totalIndexes').textContent = stats.total_indexes;
            document.getElementById('totalConstraints').textContent = stats.total_constraints;
        }

        // テーブル表示
        function renderTables() {
            const container = document.getElementById('tablesContainer');
            let html = '';

            if (filteredData.length === 0) {
                html = `
                    <div class="text-center py-5">
                        <i class="fas fa-search fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">該当するテーブルが見つかりません</h5>
                        <p class="text-muted">検索条件を変更してください</p>
                    </div>
                `;
            } else {
                filteredData.forEach(table => {
                    html += generateTableCard(table);
                });
            }

            container.innerHTML = html;
        }

        // テーブルカード生成
        function generateTableCard(table) {
            let columnsHtml = '';
            
            table.columns.forEach(column => {
                const keyBadge = getKeyBadge(column);
                const nullableBadge = column.is_nullable === 'YES' ? 
                    '<span class="nullable-yes"><i class="fas fa-question-circle" title="NULL許可"></i></span>' : 
                    '<span class="nullable-no"><i class="fas fa-exclamation-circle" title="NOT NULL"></i></span>';

                columnsHtml += `
                    <tr>
                        <td><strong>${column.column_name}</strong></td>
                        <td><span class="data-type">${column.data_type}</span></td>
                        <td>${column.character_maximum_length || column.numeric_precision || '-'}</td>
                        <td>${nullableBadge}</td>
                        <td>${column.column_default || '-'}</td>
                        <td>${keyBadge}</td>
                        <td><small class="text-muted">${column.column_comment || '-'}</small></td>
                    </tr>
                `;
            });

            return `
                <div class="table-card" data-table="${table.table_name}">
                    <div class="table-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="table-name">${table.table_name}</h5>
                                <p class="table-info">
                                    ${table.columns.length}カラム | 
                                    ${table.table_comment || 'コメントなし'} |
                                    エンジン: ${table.engine || 'Unknown'}
                                </p>
                            </div>
                            <div class="text-end">
                                <small class="text-muted">
                                    行数: <strong>${table.table_rows || 0}</strong><br>
                                    サイズ: <strong>${table.data_size || 'Unknown'}</strong>
                                </small>
                            </div>
                        </div>
                    </div>
                    <div class="table-container">
                        <table class="table column-table">
                            <thead>
                                <tr>
                                    <th>カラム名</th>
                                    <th>データ型</th>
                                    <th>長さ/精度</th>
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
                </div>
            `;
        }

        // キーバッジ生成
        function getKeyBadge(column) {
            let badges = [];

            if (column.column_key === 'PRI') {
                badges.push('<span class="key-primary">PRIMARY</span>');
            }
            if (column.column_key === 'UNI') {
                badges.push('<span class="key-unique">UNIQUE</span>');
            }
            if (column.column_key === 'MUL') {
                badges.push('<span class="key-index">INDEX</span>');
            }
            if (column.constraint_type === 'FOREIGN KEY') {
                badges.push('<span class="key-foreign">FOREIGN</span>');
            }

            return badges.length > 0 ? badges.join(' ') : '-';
        }

        // テーブルフィルター
        function filterTables(searchTerm) {
            if (!searchTerm.trim()) {
                filteredData = tableData;
            } else {
                const term = searchTerm.toLowerCase();
                filteredData = tableData.filter(table => {
                    // テーブル名で検索
                    if (table.table_name.toLowerCase().includes(term)) {
                        return true;
                    }
                    // カラム名で検索
                    return table.columns.some(column => 
                        column.column_name.toLowerCase().includes(term) ||
                        column.data_type.toLowerCase().includes(term) ||
                        (column.column_comment && column.column_comment.toLowerCase().includes(term))
                    );
                });
            }
            renderTables();
        }

        // ローディング表示
        function showLoading(show) {
            const container = document.getElementById('tablesContainer');
            if (show) {
                container.innerHTML = `
                    <div class="loading-spinner">
                        <i class="fas fa-spinner fa-spin me-2"></i>テーブル情報を読み込み中...
                    </div>
                `;
            }
        }

        // エラー表示
        function showError(message) {
            const container = document.getElementById('tablesContainer');
            container.innerHTML = `
                <div class="error-alert">
                    <h6><i class="fas fa-exclamation-triangle me-2"></i>エラー</h6>
                    <p class="mb-0">${message}</p>
                    <button type="button" class="btn btn-outline-danger btn-sm mt-2" onclick="loadTableStructure()">
                        <i class="fas fa-redo me-1"></i>再読み込み
                    </button>
                </div>
            `;
        }
    </script>
</body>
</html>

<?php
// API処理部分
if (isset($_GET['action']) && $_GET['action'] === 'get_structure') {
    header('Content-Type: application/json; charset=utf-8');
    
    try {
        $db = Database::getInstance();
        $result = getTableStructure($db);
        
        echo json_encode([
            'success' => true,
            'tables' => $result['tables'],
            'statistics' => $result['statistics'],
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage(),
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

/**
 * テーブル構造取得
 */
function getTableStructure($db) {
    $tables = [];
    $totalColumns = 0;
    $totalIndexes = 0;
    $totalConstraints = 0;
    
    // データベース名取得
    $stmt = $db->prepare("SELECT DATABASE() as db_name");
    $stmt->execute();
    $dbName = $stmt->fetch(PDO::FETCH_ASSOC)['db_name'];
    
    // テーブル一覧取得（統計情報含む）
    $stmt = $db->prepare("
        SELECT 
            t.TABLE_NAME,
            t.ENGINE,
            t.TABLE_ROWS,
            t.DATA_LENGTH,
            t.TABLE_COMMENT
        FROM INFORMATION_SCHEMA.TABLES t
        WHERE t.TABLE_SCHEMA = ?
        AND t.TABLE_TYPE = 'BASE TABLE'
        ORDER BY t.TABLE_NAME
    ");
    $stmt->execute([$dbName]);
    $tableList = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($tableList as $tableInfo) {
        $tableName = $tableInfo['TABLE_NAME'];
        
        // カラム情報取得
        $stmt = $db->prepare("
            SELECT 
                c.COLUMN_NAME as column_name,
                c.DATA_TYPE as data_type,
                c.CHARACTER_MAXIMUM_LENGTH as character_maximum_length,
                c.NUMERIC_PRECISION as numeric_precision,
                c.NUMERIC_SCALE as numeric_scale,
                c.IS_NULLABLE as is_nullable,
                c.COLUMN_DEFAULT as column_default,
                c.COLUMN_KEY as column_key,
                c.COLUMN_COMMENT as column_comment,
                c.EXTRA as extra
            FROM INFORMATION_SCHEMA.COLUMNS c
            WHERE c.TABLE_SCHEMA = ? AND c.TABLE_NAME = ?
            ORDER BY c.ORDINAL_POSITION
        ");
        $stmt->execute([$dbName, $tableName]);
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 外部キー制約情報取得
        $stmt = $db->prepare("
            SELECT 
                kcu.COLUMN_NAME,
                kcu.REFERENCED_TABLE_NAME,
                kcu.REFERENCED_COLUMN_NAME,
                rc.CONSTRAINT_NAME,
                'FOREIGN KEY' as constraint_type
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE kcu
            JOIN INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS rc 
                ON kcu.CONSTRAINT_NAME = rc.CONSTRAINT_NAME
                AND kcu.TABLE_SCHEMA = rc.CONSTRAINT_SCHEMA
            WHERE kcu.TABLE_SCHEMA = ? AND kcu.TABLE_NAME = ?
        ");
        $stmt->execute([$dbName, $tableName]);
        $constraints = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 制約情報をカラムに統合
        foreach ($columns as &$column) {
            foreach ($constraints as $constraint) {
                if ($column['column_name'] === $constraint['COLUMN_NAME']) {
                    $column['constraint_type'] = $constraint['constraint_type'];
                    $column['referenced_table'] = $constraint['REFERENCED_TABLE_NAME'];
                    $column['referenced_column'] = $constraint['REFERENCED_COLUMN_NAME'];
                    break;
                }
            }
        }
        
        // インデックス情報取得
        $stmt = $db->prepare("
            SELECT COUNT(*) as index_count
            FROM INFORMATION_SCHEMA.STATISTICS 
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
        ");
        $stmt->execute([$dbName, $tableName]);
        $indexCount = $stmt->fetch(PDO::FETCH_ASSOC)['index_count'];
        
        $tables[] = [
            'table_name' => $tableName,
            'engine' => $tableInfo['ENGINE'],
            'table_rows' => $tableInfo['TABLE_ROWS'],
            'data_size' => formatBytes($tableInfo['DATA_LENGTH']),
            'table_comment' => $tableInfo['TABLE_COMMENT'],
            'columns' => $columns,
            'index_count' => $indexCount
        ];
        
        $totalColumns += count($columns);
        $totalIndexes += $indexCount;
        $totalConstraints += count($constraints);
    }
    
    return [
        'tables' => $tables,
        'statistics' => [
            'total_tables' => count($tables),
            'total_columns' => $totalColumns,
            'total_indexes' => $totalIndexes,
            'total_constraints' => $totalConstraints
        ]
    ];
}

/**
 * バイト数フォーマット
 */
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}
?>
