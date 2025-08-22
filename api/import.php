<?php
/**
 * CSVインポートAPI（根本修正版）
 * JSON純粋化・HTMLエラー出力完全阻止
 * 
 * 根本修正内容:
 * 1. 全エラー出力の完全制御
 * 2. 純粋なJSONレスポンス保証
 * 3. HTMLエラーページ出力の阻止
 * 4. ファイルクラス存在チェック
 * 5. セキュアなエラーハンドリング
 */

// 📌 重要: 全エラー出力を無効化（HTMLエラーページ阻止）
error_reporting(0);
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);

// 📌 出力バッファリング開始（予期しない出力をキャッチ）
ob_start();

try {
    // 📌 JSONヘッダーを最優先で設定
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');

    // OPTIONS リクエスト対応
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        ob_end_clean();
        http_response_code(200);
        exit;
    }

    // 📌 必要ファイルの段階的読み込み（存在チェック付き）
    $requiredFiles = [
        '../config/database.php',
        '../classes/Database.php',
        '../classes/SmileyCSVImporter.php',
        '../classes/SecurityHelper.php'
    ];

    foreach ($requiredFiles as $file) {
        if (!file_exists($file)) {
            throw new Exception("必要なファイルが見つかりません: " . basename($file));
        }
        require_once $file;
    }

    // 📌 クラス存在チェック
    $requiredClasses = ['Database', 'SmileyCSVImporter', 'SecurityHelper'];
    foreach ($requiredClasses as $class) {
        if (!class_exists($class)) {
            throw new Exception("必要なクラスが定義されていません: {$class}");
        }
    }

    // 📌 データベース接続テスト
    $db = Database::getInstance();
    if (!$db) {
        throw new Exception("データベース接続に失敗しました");
    }

    // セキュリティヘッダー設定
    SecurityHelper::setSecurityHeaders();
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        
        // 📌 ファイルアップロード基本検証
        if (!isset($_FILES['csv_file'])) {
            throw new Exception('ファイルがアップロードされていません');
        }

        $file = $_FILES['csv_file'];

        // アップロードエラーチェック
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errorMessages = [
                UPLOAD_ERR_INI_SIZE => 'ファイルサイズが大きすぎます（サーバー設定上限）',
                UPLOAD_ERR_FORM_SIZE => 'ファイルサイズが大きすぎます（フォーム上限）',
                UPLOAD_ERR_PARTIAL => 'ファイルが部分的にしかアップロードされませんでした',
                UPLOAD_ERR_NO_FILE => 'ファイルがアップロードされていません',
                UPLOAD_ERR_NO_TMP_DIR => '一時ディレクトリがありません',
                UPLOAD_ERR_CANT_WRITE => 'ファイルの書き込みに失敗しました',
                UPLOAD_ERR_EXTENSION => 'PHPの拡張機能によりアップロードが停止されました'
            ];
            
            $message = $errorMessages[$file['error']] ?? 'アップロードエラーが発生しました（エラーコード: ' . $file['error'] . '）';
            throw new Exception($message);
        }

        // ファイルサイズチェック
        if ($file['size'] === 0) {
            throw new Exception('空のファイルがアップロードされました');
        }

        if ($file['size'] > 50 * 1024 * 1024) { // 50MB制限
            throw new Exception('ファイルサイズが大きすぎます（最大50MB）');
        }

        // ファイル拡張子チェック
        $pathInfo = pathinfo($file['name']);
        $extension = strtolower($pathInfo['extension'] ?? '');
        
        if (!in_array($extension, ['csv', 'txt'])) {
            throw new Exception('CSVファイルまたはテキストファイルをアップロードしてください');
        }

        // 📌 一時ファイル処理（安全なディレクトリ）
        $uploadDir = '../temp/';
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                throw new Exception('一時ディレクトリの作成に失敗しました');
            }
        }

        $tempFilePath = $uploadDir . 'import_' . date('YmdHis') . '_' . uniqid() . '.csv';
        
        if (!move_uploaded_file($file['tmp_name'], $tempFilePath)) {
            throw new Exception('ファイルの保存に失敗しました');
        }

        // 📌 CSVインポート実行（エラーキャッチ強化）
        try {
            $importer = new SmileyCSVImporter($db);
            
            $importOptions = [
                'encoding' => $_POST['encoding'] ?? 'auto',
                'overwrite' => isset($_POST['overwrite']) ? (bool)$_POST['overwrite'] : false,
                'validate_smiley' => true
            ];
            
            $result = $importer->importFile($tempFilePath, $importOptions);
            
            // 📌 出力バッファクリア後にJSONレスポンス
            ob_end_clean();
            
            echo json_encode([
                'success' => true,
                'message' => "CSVインポートが完了しました",
                'data' => [
                    'batch_id' => $result['batch_id'],
                    'total_records' => $result['stats']['total'],
                    'success_records' => $result['stats']['success'],
                    'error_records' => count($result['errors']),
                    'duplicate_records' => $result['stats']['duplicate'],
                    'processing_time' => $result['processing_time']
                ],
                'errors' => $result['errors']
            ], JSON_UNESCAPED_UNICODE);
            
        } catch (Exception $importError) {
            throw new Exception('CSVインポート処理エラー: ' . $importError->getMessage());
        } finally {
            // 一時ファイル削除
            if (file_exists($tempFilePath)) {
                unlink($tempFilePath);
            }
        }
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
        
        $action = $_GET['action'] ?? 'status';
        
        switch ($action) {
            case 'status':
                // インポート状況確認
                ob_end_clean();
                echo json_encode([
                    'success' => true,
                    'message' => 'CSVインポートAPIは正常に動作しています',
                    'data' => [
                        'timestamp' => date('Y-m-d H:i:s'),
                        'database' => DB_NAME,
                        'environment' => ENVIRONMENT,
                        'max_upload_size' => ini_get('upload_max_filesize'),
                        'memory_limit' => ini_get('memory_limit'),
                        'php_version' => phpversion()
                    ]
                ], JSON_UNESCAPED_UNICODE);
                break;
                
            case 'validate':
                // データベース接続テスト
                $testSql = "SELECT COUNT(*) as count FROM users";
                $testStmt = $db->query($testSql);
                $userCount = $testStmt->fetch()['count'];
                
                $testSql2 = "SELECT COUNT(*) as count FROM orders";
                $testStmt2 = $db->query($testSql2);
                $orderCount = $testStmt2->fetch()['count'];
                
                ob_end_clean();
                echo json_encode([
                    'success' => true,
                    'message' => 'データベース接続正常',
                    'data' => [
                        'user_count' => $userCount,
                        'order_count' => $orderCount,
                        'connection_test' => 'OK'
                    ]
                ], JSON_UNESCAPED_UNICODE);
                break;
                
            default:
                throw new Exception('不正なアクションです');
        }
        
    } else {
        throw new Exception('サポートされていないHTTPメソッドです');
    }
    
} catch (Exception $e) {
    // 📌 エラー時も純粋なJSONレスポンス
    ob_end_clean();
    http_response_code(500);
    
    // エラーログに記録
    error_log("Import API Error: " . $e->getMessage());
    
    // 一時ファイルがある場合は削除
    if (isset($tempFilePath) && file_exists($tempFilePath)) {
        unlink($tempFilePath);
    }
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'debug_info' => [
            'timestamp' => date('Y-m-d H:i:s'),
            'file' => basename(__FILE__),
            'method' => $_SERVER['REQUEST_METHOD'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown'
        ]
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Throwable $t) {
    // 📌 致命的エラー（Syntax Error等）もキャッチ
    ob_end_clean();
    http_response_code(500);
    
    error_log("Import API Fatal Error: " . $t->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'システムで致命的なエラーが発生しました',
        'debug_info' => [
            'timestamp' => date('Y-m-d H:i:s'),
            'error_type' => 'Fatal Error'
        ]
    ], JSON_UNESCAPED_UNICODE);
}

// 📌 最終的な出力バッファクリーンアップ
if (ob_get_level()) {
    ob_end_flush();
}
?>
