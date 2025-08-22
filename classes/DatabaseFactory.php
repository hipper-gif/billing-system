<?php
/**
 * データベースファクトリークラス
 * 責任: データベース接続の管理と提供
 * 将来的な拡張性を考慮（複数DB対応等）
 */

class DatabaseFactory {
    
    /**
     * デフォルトデータベース接続取得
     */
    public static function getDefaultConnection() {
        return Database::getInstance();
    }
    
    /**
     * 読み取り専用接続取得（将来拡張用）
     * 現在はデフォルト接続と同じだが、将来的に読み取り専用DBを分離する場合に使用
     */
    public static function getReadOnlyConnection() {
        return Database::getInstance();
    }
    
    /**
     * 書き込み用接続取得（将来拡張用）
     */
    public static function getWriteConnection() {
        return Database::getInstance();
    }
    
    /**
     * データベース接続テスト
     */
    public static function testConnection() {
        try {
            $db = self::getDefaultConnection();
            return $db->testConnection();
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'データベース接続テストエラー: ' . $e->getMessage(),
                'environment' => defined('ENVIRONMENT') ? ENVIRONMENT : 'unknown'
            ];
        }
    }
    
    /**
     * データベース情報取得
     */
    public static function getDatabaseInfo() {
        try {
            $db = self::getDefaultConnection();
            return $db->getDatabaseInfo();
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'データベース情報取得エラー: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 複数テーブルの存在確認
     */
    public static function checkRequiredTables() {
        $requiredTables = [
            'users',
            'companies', 
            'departments',
            'orders',
            'products',
            'suppliers',
            'invoices',
            'payments',
            'import_logs'
        ];
        
        try {
            $db = self::getDefaultConnection();
            $results = [];
            
            foreach ($requiredTables as $table) {
                $results[$table] = $db->tableExists($table);
            }
            
            $existingCount = count(array_filter($results));
            $totalCount = count($requiredTables);
            
            return [
                'success' => $existingCount === $totalCount,
                'existing_tables' => $existingCount,
                'total_required' => $totalCount,
                'missing_tables' => array_keys(array_filter($results, function($exists) {
                    return !$exists;
                })),
                'table_status' => $results
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'テーブル確認エラー: ' . $e->getMessage(),
                'table_status' => []
            ];
        }
    }
    
    /**
     * システム全体の健全性チェック
     */
    public static function systemHealthCheck() {
        $health = [
            'database_connection' => false,
            'required_tables' => false,
            'configuration' => false,
            'overall_status' => 'unhealthy'
        ];
        
        try {
            // 1. データベース接続確認
            $connectionTest = self::testConnection();
            $health['database_connection'] = $connectionTest['success'];
            
            // 2. 必要テーブル確認
            $tablesTest = self::checkRequiredTables();
            $health['required_tables'] = $tablesTest['success'];
            
            // 3. 設定確認
            $health['configuration'] = defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER');
            
            // 4. 総合判定
            $health['overall_status'] = (
                $health['database_connection'] && 
                $health['required_tables'] && 
                $health['configuration']
            ) ? 'healthy' : 'unhealthy';
            
            return [
                'success' => $health['overall_status'] === 'healthy',
                'health_check' => $health,
                'connection_info' => $connectionTest,
                'tables_info' => $tablesTest,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'health_check' => $health,
                'error' => $e->getMessage(),
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
    }
    
    /**
     * 緊急時のデータベース修復試行
     */
    public static function emergencyRepair() {
        try {
            $db = self::getDefaultConnection();
            
            // 基本的な修復クエリ実行
            $repairQueries = [
                "REPAIR TABLE users",
                "REPAIR TABLE companies", 
                "REPAIR TABLE departments",
                "REPAIR TABLE orders"
            ];
            
            $results = [];
            foreach ($repairQueries as $query) {
                try {
                    $stmt = $db->query($query);
                    $results[] = $query . " - 成功";
                } catch (Exception $e) {
                    $results[] = $query . " - 失敗: " . $e->getMessage();
                }
            }
            
            return [
                'success' => true,
                'message' => '緊急修復を実行しました',
                'repair_results' => $results
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => '緊急修復に失敗しました: ' . $e->getMessage()
            ];
        }
    }
}
?>
