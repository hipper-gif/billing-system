<?php
/**
 * クラス自動読み込みシステム
 * classes/ClassLoader.php
 */

class ClassLoader {
    private static $loaded = [];
    private static $instance = null;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * 安全なクラス読み込み
     */
    public static function loadClass($className, $filePath) {
        // 既に読み込み済みかチェック
        if (self::isLoaded($className)) {
            return true;
        }
        
        // ファイル存在確認
        if (!file_exists($filePath)) {
            throw new Exception("クラスファイルが見つかりません: {$filePath}");
        }
        
        // クラスが既に定義されているかチェック
        if (class_exists($className, false)) {
            self::$loaded[$className] = $filePath;
            return true;
        }
        
        // ファイル読み込み
        require_once $filePath;
        
        // 読み込み後にクラス存在確認
        if (!class_exists($className, false)) {
            throw new Exception("クラスが正しく読み込まれませんでした: {$className}");
        }
        
        // 読み込み済みとして記録
        self::$loaded[$className] = $filePath;
        
        return true;
    }
    
    /**
     * クラスが読み込み済みかチェック
     */
    public static function isLoaded($className) {
        return isset(self::$loaded[$className]) || class_exists($className, false);
    }
    
    /**
     * 読み込み済みクラス一覧取得
     */
    public static function getLoadedClasses() {
        return self::$loaded;
    }
    
    /**
     * 必要クラスを一括読み込み
     */
    public static function loadRequiredClasses($baseDir = '') {
        if (empty($baseDir)) {
            $baseDir = __DIR__;
        }
        
        $classes = [
            'Database' => $baseDir . '/Database.php',
            'SmileyCSVImporter' => $baseDir . '/SmileyCSVImporter.php',
            'SecurityHelper' => $baseDir . '/SecurityHelper.php'
        ];
        
        $loadedClasses = [];
        $errors = [];
        
        foreach ($classes as $className => $filePath) {
            try {
                if (self::loadClass($className, $filePath)) {
                    $loadedClasses[] = $className;
                }
            } catch (Exception $e) {
                $errors[] = "クラス {$className} の読み込みエラー: " . $e->getMessage();
            }
        }
        
        return [
            'success' => empty($errors),
            'loaded' => $loadedClasses,
            'errors' => $errors
        ];
    }
}
?>
