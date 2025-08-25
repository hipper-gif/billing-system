<?php
/**
 * CSV処理専用デバッグAPI
 * CSVインポート処理の段階的詳細分析用
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once '../config/database.php';
require_once '../classes/Database.php';
require_once '../classes/SmileyCSVImporter.php';
require_once '../classes/FileUploadHandler.php';
require_once '../classes/SecurityHelper.php';

function debugResponse($step, $data = null, $error = null) {
    echo json_encode([
        'debug_step' => $step,
        'success' => $error === null,
        'data' => $data,
        'error' => $error,
        'timestamp' => date('Y-m-d H:i:s'),
        'memory_usage' => round(memory_get_usage() / 1024 / 1024, 2) . 'MB'
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

try {
    // Step 1: クラスローディングテスト
    $step = "class_loading_test";
    
    $classTests = [];
    
    // Database クラステスト
    try {
        $db = Database::getInstance();
        $classTests['Database'] = [
            'loaded' => true,
            'methods' => get_class_methods($db),
            'connection_test' => $db->testConnection()
        ];
    } catch (Exception $e) {
        $classTests['Database'] = [
            'loaded' => false,
            'error' => $e->getMessage()
        ];
    }
    
    // SmileyCSVImporter クラステスト
    try {
        $importer = new SmileyCSVImporter($db);
        $classTests['SmileyCSVImporter'] = [
            'loaded' => true,
            'methods' => get_class_methods($importer)
        ];
        
        // リフレクションでprivateプロパティの確認
        $reflection = new ReflectionClass($importer);
        $properties = [];
        foreach ($reflection->getProperties() as $prop) {
            $properties[] = $prop->getName();
        }
        $classTests['SmileyCSVImporter']['properties'] = $properties;
        
    } catch (Exception $e) {
        $classTests['SmileyCSVImporter'] = [
            'loaded' => false,
            'error' => $e->getMessage()
        ];
    }
    
    // FileUploadHandler クラステスト
    try {
        $handler = new FileUploadHandler();
        $classTests['FileUploadHandler'] = [
            'loaded' => true,
            'methods' => get_class_methods($handler)
        ];
    } catch (Exception $e) {
        $classTests['FileUploadHandler'] = [
            'loaded' => false,
            'error' => $e->getMessage()
        ];
    }
    
    debugResponse($step, $classTests);
    
} catch (Exception $e) {
    debugResponse("critical_error", null, [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
}

// Step 2: CSV処理シミュレーション（テストデータ使用）
if (isset($_GET['step']) && $_GET['step'] === 'csv_simulation') {
    try {
        $step = "csv_processing_simulation";
        
        // テスト用CSVデータを作成
        $testCSVData = [
            ['法人CD', '法人名', '事業所CD', '事業所名', '給食業者CD', '給食業者名', '給食区分CD', '給食区分名', '配達日', '部門CD', '部門名', '社員CD', '社員名', '雇用形態CD', '雇用形態名', '給食ﾒﾆｭｰCD', '給食ﾒﾆｭｰ名', '数量', '単価', '金額', '備考', '受取時間', '連携CD'],
            ['001', '株式会社Smiley', 'C001', 'テスト企業', 'S001', 'Smiley', 'K001', '通常', '2025-08-25', 'D001', 'テスト部署', 'U001', 'テスト利用者', 'E001', '正社員', 'M001', 'テスト弁当', '1', '500', '500', 'テスト', '12:00', 'R001']
        ];
        
        // 一時CSVファイル作成
        $tempFile = tempnam(sys_get_temp_dir(), 'csv_test_');
        $handle = fopen($tempFile, 'w');
        
        foreach ($testCSVData as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);
        
        $processingResult = [];
        
        // Step 2-1: エンコーディング検出テスト
        try {
            $reflection = new ReflectionClass($importer);
            $detectMethod = $reflection->getMethod('detectEncoding');
            $detectMethod->setAccessible(true);
            
            $encoding = $detectMethod->invoke($importer, $tempFile);
            $processingResult['encoding_detection'] = [
                'success' => true,
                'detected_encoding' => $encoding
            ];
        } catch (Exception $e) {
            $processingResult['encoding_detection'] = [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
        
        // Step 2-2: CSV読み込みテスト
        try {
            $reflection = new ReflectionClass($importer);
            $readMethod = $reflection->getMethod('readCsv');
            $readMethod->setAccessible(true);
            
            $rawData = $readMethod->invoke($importer, $tempFile, 'UTF-8');
            $processingResult['csv_reading'] = [
                'success' => true,
                'row_count' => count($rawData),
                'sample_data' => array_slice($rawData, 0, 2),
                'headers' => array_keys($rawData[0] ?? [])
            ];
        } catch (Exception $e) {
            $processingResult['csv_reading'] = [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
        
        // Step 2-3: データ正規化テスト
        if (isset($rawData)) {
            try {
                $reflection = new ReflectionClass($importer);
                $normalizeMethod = $reflection->getMethod('normalizeData');
                $normalizeMethod->setAccessible(true);
                
                $normalizedData = $normalizeMethod->invoke($importer, $rawData);
                $processingResult['data_normalization'] = [
                    'success' => true,
                    'row_count' => count($normalizedData),
                    'sample_normalized' => array_slice($normalizedData, 0, 1),
                    'field_mapping_applied' => array_keys($normalizedData[0] ?? [])
                ];
            } catch (Exception $e) {
                $processingResult['data_normalization'] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        // Step 2-4: データ検証テスト
        if (isset($normalizedData)) {
            try {
                $reflection = new ReflectionClass($importer);
                $validateMethod = $reflection->getMethod('validateData');
                $validateMethod->setAccessible(true);
                
                $validationResult = $validateMethod->invoke($importer, $normalizedData);
                $processingResult['data_validation'] = [
                    'success' => true,
                    'valid_records' => count($validationResult['valid_data'] ?? []),
                    'invalid_records' => count($validationResult['errors'] ?? []),
                    'validation_errors' => $validationResult['errors'] ?? [],
                    'sample_valid_data' => array_slice($validationResult['valid_data'] ?? [], 0, 1)
                ];
            } catch (Exception $e) {
                $processingResult['data_validation'] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        // Step 2-5: データベース挿入テスト（ドライラン）
        if (isset($validationResult) && !empty($validationResult['valid_data'])) {
            try {
                $db->beginTransaction();
                
                $reflection = new ReflectionClass($importer);
                $importMethod = $reflection->getMethod('importToDatabase');
                $importMethod->setAccessible(true);
                
                $importResult = $importMethod->invoke($importer, $validationResult['valid_data'], 'TEST_BATCH_' . uniqid());
                
                // ドライランなのでロールバック
                $db->rollback();
                
                $processingResult['database_import_test'] = [
                    'success' => true,
                    'import_stats' => $importResult,
                    'note' => 'Transaction rolled back (dry run)'
                ];
            } catch (Exception $e) {
                $db->rollback();
                $processingResult['database_import_test'] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        // 一時ファイル削除
        unlink($tempFile);
        
        debugResponse($step, $processingResult);
        
    } catch (Exception $e) {
        debugResponse("csv_simulation_error", null, [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
    }
}

// Step 3: 実際のimport.php APIテスト
if (isset($_GET['step']) && $_GET['step'] === 'api_test') {
    try {
        $step = "api_integration_test";
        
        // import.php の実際の処理をシミュレート
        $apiTests = [];
        
        // POSTリクエストシミュレーション準備
        $apiTests['csrf_token_generation'] = [
            'generated' => SecurityHelper::generateCSRFToken(),
            'session_check' => session_status() === PHP_SESSION_ACTIVE
        ];
        
        // ファイルアップロードハンドラーテスト
        $handler = new FileUploadHandler();
        $apiTests['upload_handler_methods'] = get_class_methods($handler);
        
        // エラーログの確認
        $errorLog = error_get_last();
        $apiTests['last_php_error'] = $errorLog;
        
        debugResponse($step, $apiTests);
        
    } catch (Exception $e) {
        debugResponse("api_test_error", null, [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
    }
}

// デフォルトレスポンス（使用方法案内）
debugResponse("usage_info", [
    'available_steps' => [
        'default' => 'クラスローディング基本テスト',
        'csv_simulation' => 'CSV処理完全シミュレーション',
        'api_test' => 'import.php API統合テスト'
    ],
    'usage' => [
        'basic_test' => 'GET /api/csv_process_debug.php',
        'csv_test' => 'GET /api/csv_process_debug.php?step=csv_simulation',
        'api_test' => 'GET /api/csv_process_debug.php?step=api_test'
    ],
    'note' => 'このAPIでCSVインポート処理の各段階を詳細分析できます'
]);

?>
