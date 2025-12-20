<?php
/**
 * OrderManager - 注文管理クラス
 * 
 * 注文の作成・変更・キャンセル・締切チェックを管理
 * 
 * @package Smiley配食事業システム
 * @version 1.0
 */

require_once __DIR__ . '/../config/database.php';

class OrderManager {
    private $db;
    
    /**
     * コンストラクタ
     */
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * 注文可能日を取得
     * 
     * @param int $companyId 企業ID
     * @param int $days 取得日数（デフォルト: 7日間）
     * @return array 注文可能日の配列
     */
    public function getAvailableDates($companyId, $days = 7) {
        $availableDates = [];
        $deadline = $this->getDeadlineTime($companyId);
        
        // 現在時刻と締切時間を比較
        $currentTime = new DateTime();
        $deadlineDateTime = new DateTime($deadline);
        
        // 今日の締切を過ぎているか確認
        $startDay = ($currentTime > $deadlineDateTime) ? 2 : 1;
        
        for ($i = $startDay; $i <= $days + $startDay; $i++) {
            $date = new DateTime("+{$i} days");
            $dayOfWeek = (int)$date->format('w');
            
            $availableDates[] = [
                'date' => $date->format('Y-m-d'),
                'formatted' => $date->format('m月d日'),
                'day_of_week' => $this->getDayOfWeekJp($dayOfWeek),
                'is_today' => false,
                'is_tomorrow' => ($i === 1),
                'is_weekend' => ($dayOfWeek === 0 || $dayOfWeek === 6)
            ];
        }
        
        return $availableDates;
    }
    
    /**
     * 締切時間を取得
     * 
     * @param int $companyId 企業ID
     * @return string 締切時間（HH:MM:SS）
     */
    public function getDeadlineTime($companyId) {
        $sql = "SELECT deadline_time 
                FROM order_deadlines 
                WHERE (company_id = :company_id OR company_id IS NULL)
                  AND is_active = 1
                ORDER BY company_id DESC
                LIMIT 1";
        
        $result = $this->db->fetch($sql, ['company_id' => $companyId]);
        
        return $result ? $result['deadline_time'] : '06:00:00';
    }
    
    /**
     * 指定日のメニューを取得
     * 
     * @param string $date 配達日（Y-m-d）
     * @return array メニュー配列
     */
    public function getMenusForDate($date) {
        // 日替わりメニュー取得
        $dailySql = "SELECT 
                        p.id,
                        p.product_code,
                        p.product_name,
                        p.category_code,
                        p.category_name,
                        p.unit_price,
                        dm.special_note,
                        'daily' as menu_type
                    FROM daily_menus dm
                    INNER JOIN products p ON dm.product_id = p.id
                    WHERE dm.menu_date = :menu_date
                      AND dm.is_available = 1
                      AND p.is_active = 1
                    ORDER BY dm.display_order, p.product_name";
        
        $dailyMenus = $this->db->fetchAll($dailySql, ['menu_date' => $date]);
        
        // 定番メニュー取得
        $standardSql = "SELECT 
                            id,
                            product_code,
                            product_name,
                            category_code,
                            category_name,
                            unit_price,
                            NULL as special_note,
                            'standard' as menu_type
                        FROM products
                        WHERE category_code = 'STANDARD'
                          AND is_active = 1
                        ORDER BY product_name";
        
        $standardMenus = $this->db->fetchAll($standardSql);
        
        return [
            'daily' => $dailyMenus,
            'standard' => $standardMenus,
            'date' => $date
        ];
    }
    
    /**
     * 注文を作成
     * 
     * @param array $orderData 注文データ
     * @return array 結果 ['success' => bool, 'order_id' => int, 'error' => string]
     */
    public function createOrder($orderData) {
        try {
            // バリデーション
            $validation = $this->validateOrder($orderData);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'error' => $validation['error']
                ];
            }
            
            // 締切時間チェック
            if (!$this->checkDeadline($orderData['delivery_date'], $orderData['company_id'])) {
                return [
                    'success' => false,
                    'error' => '指定日の注文締切時間を過ぎています'
                ];
            }
            
            // 重複注文チェック
            if ($this->isDuplicateOrder($orderData)) {
                return [
                    'success' => false,
                    'error' => 'この日付にすでに注文が存在します'
                ];
            }
            
            // 商品情報取得
            $product = $this->getProductInfo($orderData['product_id']);
            if (!$product) {
                return [
                    'success' => false,
                    'error' => '商品が見つかりません'
                ];
            }
            
            // 金額計算
            $quantity = $orderData['quantity'];
            $unitPrice = $product['unit_price'];
            $subtotal = $unitPrice * $quantity;
            
            // 企業補助額取得
            $subsidyAmount = $this->getSubsidyAmount($orderData['company_id']);
            $userPaymentAmount = max(0, $subtotal - ($subsidyAmount * $quantity));
            
            // 注文データ準備
            $insertData = [
                'order_date' => date('Y-m-d'),
                'delivery_date' => $orderData['delivery_date'],
                'user_id' => $orderData['user_id'],
                'user_code' => $orderData['user_code'],
                'user_name' => $orderData['user_name'],
                'company_id' => $orderData['company_id'],
                'company_code' => $orderData['company_code'] ?? null,
                'company_name' => $orderData['company_name'],
                'department_code' => $orderData['department_code'] ?? null,
                'department_name' => $orderData['department'] ?? null,
                'product_id' => $product['id'],
                'product_code' => $product['product_code'],
                'product_name' => $product['product_name'],
                'category_code' => $product['category_code'],
                'category_name' => $product['category_name'],
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'total_amount' => $subtotal,
                'subsidy_amount' => $subsidyAmount * $quantity,
                'user_payment_amount' => $userPaymentAmount,
                'ordered_by_user_id' => $orderData['user_id'],
                'order_type' => 'self',
                'order_status' => 'confirmed',
                'notes' => $orderData['notes'] ?? null
            ];
            
            // データベースに挿入
            $sql = "INSERT INTO orders (
                        order_date, delivery_date, user_id, user_code, user_name,
                        company_id, company_code, company_name, department_code, department_name,
                        product_id, product_code, product_name, category_code, category_name,
                        quantity, unit_price, total_amount, subsidy_amount, user_payment_amount,
                        ordered_by_user_id, order_type, order_status, notes,
                        created_at, updated_at
                    ) VALUES (
                        :order_date, :delivery_date, :user_id, :user_code, :user_name,
                        :company_id, :company_code, :company_name, :department_code, :department_name,
                        :product_id, :product_code, :product_name, :category_code, :category_name,
                        :quantity, :unit_price, :total_amount, :subsidy_amount, :user_payment_amount,
                        :ordered_by_user_id, :order_type, :order_status, :notes,
                        NOW(), NOW()
                    )";
            
            $this->db->query($sql, $insertData);
            $orderId = $this->db->lastInsertId();
            
            return [
                'success' => true,
                'order_id' => $orderId,
                'message' => '注文を受け付けました'
            ];
            
        } catch (Exception $e) {
            error_log("Order creation error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => '注文の作成中にエラーが発生しました'
            ];
        }
    }
    
    /**
     * 注文を更新
     * 
     * @param int $orderId 注文ID
     * @param array $updateData 更新データ
     * @return array 結果
     */
    public function updateOrder($orderId, $updateData) {
        try {
            // 注文の存在確認
            $order = $this->getOrderById($orderId);
            if (!$order) {
                return [
                    'success' => false,
                    'error' => '注文が見つかりません'
                ];
            }
            
            // キャンセル済みチェック
            if ($order['order_status'] === 'cancelled') {
                return [
                    'success' => false,
                    'error' => 'キャンセル済みの注文は変更できません'
                ];
            }
            
            // 締切時間チェック
            if (!$this->checkDeadline($order['delivery_date'], $order['company_id'])) {
                return [
                    'success' => false,
                    'error' => '注文締切時間を過ぎているため変更できません'
                ];
            }
            
            // 更新可能なフィールド
            $allowedFields = ['quantity', 'notes'];
            $updateFields = [];
            $params = ['order_id' => $orderId];
            
            foreach ($allowedFields as $field) {
                if (isset($updateData[$field])) {
                    $updateFields[] = "{$field} = :{$field}";
                    $params[$field] = $updateData[$field];
                }
            }
            
            // 数量変更時は金額も再計算
            if (isset($updateData['quantity'])) {
                $newQuantity = $updateData['quantity'];
                $unitPrice = $order['unit_price'];
                $subtotal = $unitPrice * $newQuantity;
                
                $subsidyAmount = $this->getSubsidyAmount($order['company_id']);
                $userPaymentAmount = max(0, $subtotal - ($subsidyAmount * $newQuantity));
                
                $updateFields[] = "total_amount = :total_amount";
                $updateFields[] = "subsidy_amount = :subsidy_amount";
                $updateFields[] = "user_payment_amount = :user_payment_amount";
                
                $params['total_amount'] = $subtotal;
                $params['subsidy_amount'] = $subsidyAmount * $newQuantity;
                $params['user_payment_amount'] = $userPaymentAmount;
            }
            
            if (empty($updateFields)) {
                return [
                    'success' => false,
                    'error' => '更新するデータがありません'
                ];
            }
            
            $updateFields[] = "updated_at = NOW()";
            $sql = "UPDATE orders SET " . implode(', ', $updateFields) . " WHERE id = :order_id";
            
            $this->db->query($sql, $params);
            
            return [
                'success' => true,
                'message' => '注文を更新しました'
            ];
            
        } catch (Exception $e) {
            error_log("Order update error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => '注文の更新中にエラーが発生しました'
            ];
        }
    }
    
    /**
     * 注文をキャンセル
     * 
     * @param int $orderId 注文ID
     * @param int $userId ユーザーID
     * @return array 結果
     */
    public function cancelOrder($orderId, $userId) {
        try {
            // 注文の存在確認
            $order = $this->getOrderById($orderId);
            if (!$order) {
                return [
                    'success' => false,
                    'error' => '注文が見つかりません'
                ];
            }
            
            // 本人確認
            if ($order['user_id'] != $userId) {
                return [
                    'success' => false,
                    'error' => '他人の注文はキャンセルできません'
                ];
            }
            
            // すでにキャンセル済みチェック
            if ($order['order_status'] === 'cancelled') {
                return [
                    'success' => false,
                    'error' => 'すでにキャンセル済みです'
                ];
            }
            
            // 締切時間チェック
            if (!$this->checkDeadline($order['delivery_date'], $order['company_id'])) {
                return [
                    'success' => false,
                    'error' => '注文締切時間を過ぎているためキャンセルできません'
                ];
            }
            
            // ステータスを更新
            $sql = "UPDATE orders 
                    SET order_status = 'cancelled', 
                        updated_at = NOW() 
                    WHERE id = :order_id";
            
            $this->db->query($sql, ['order_id' => $orderId]);
            
            return [
                'success' => true,
                'message' => '注文をキャンセルしました'
            ];
            
        } catch (Exception $e) {
            error_log("Order cancel error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'キャンセル処理中にエラーが発生しました'
            ];
        }
    }
    
    /**
     * 注文履歴を取得
     * 
     * @param int $userId ユーザーID
     * @param array $filters フィルター条件
     * @return array 注文履歴
     */
    public function getOrderHistory($userId, $filters = []) {
        $conditions = ['user_id = :user_id'];
        $params = ['user_id' => $userId];
        
        // ステータスフィルター
        if (!empty($filters['status'])) {
            $conditions[] = 'order_status = :status';
            $params['status'] = $filters['status'];
        }
        
        // 日付範囲フィルター
        if (!empty($filters['date_from'])) {
            $conditions[] = 'delivery_date >= :date_from';
            $params['date_from'] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $conditions[] = 'delivery_date <= :date_to';
            $params['date_to'] = $filters['date_to'];
        }
        
        $whereClause = implode(' AND ', $conditions);
        
        $sql = "SELECT 
                    id, order_date, delivery_date,
                    product_code, product_name, category_name,
                    quantity, unit_price, total_amount,
                    subsidy_amount, user_payment_amount,
                    order_status, notes, created_at
                FROM orders
                WHERE {$whereClause}
                ORDER BY delivery_date DESC, created_at DESC
                LIMIT 100";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * 注文詳細を取得
     * 
     * @param int $orderId 注文ID
     * @return array|null 注文情報
     */
    public function getOrderById($orderId) {
        $sql = "SELECT * FROM orders WHERE id = :order_id LIMIT 1";
        return $this->db->fetch($sql, ['order_id' => $orderId]);
    }
    
    /**
     * 注文のバリデーション
     * 
     * @param array $orderData 注文データ
     * @return array ['valid' => bool, 'error' => string]
     */
    private function validateOrder($orderData) {
        // 必須項目チェック
        $required = ['delivery_date', 'user_id', 'company_id', 'product_id', 'quantity'];
        
        foreach ($required as $field) {
            if (empty($orderData[$field])) {
                return [
                    'valid' => false,
                    'error' => "{$field} は必須です"
                ];
            }
        }
        
        // 数量チェック
        if ($orderData['quantity'] < 1 || $orderData['quantity'] > 10) {
            return [
                'valid' => false,
                'error' => '数量は1〜10の範囲で指定してください'
            ];
        }
        
        // 日付形式チェック
        $date = DateTime::createFromFormat('Y-m-d', $orderData['delivery_date']);
        if (!$date) {
            return [
                'valid' => false,
                'error' => '無効な日付形式です'
            ];
        }
        
        return ['valid' => true];
    }
    
    /**
     * 締切時間チェック
     * 
     * @param string $deliveryDate 配達日
     * @param int $companyId 企業ID
     * @return bool 締切前ならtrue
     */
    private function checkDeadline($deliveryDate, $companyId) {
        $deadline = $this->getDeadlineTime($companyId);
        
        $now = new DateTime();
        $deliveryDateTime = new DateTime($deliveryDate);
        $deadlineDateTime = new DateTime($deliveryDate . ' ' . $deadline);
        
        // 配達日の前日の締切時間
        $deadlineDateTime->modify('-1 day');
        
        return $now < $deadlineDateTime;
    }
    
    /**
     * 重複注文チェック
     * 
     * @param array $orderData 注文データ
     * @return bool 重複があればtrue
     */
    private function isDuplicateOrder($orderData) {
        $sql = "SELECT COUNT(*) as count 
                FROM orders 
                WHERE user_id = :user_id 
                  AND delivery_date = :delivery_date
                  AND order_status != 'cancelled'";
        
        $result = $this->db->fetch($sql, [
            'user_id' => $orderData['user_id'],
            'delivery_date' => $orderData['delivery_date']
        ]);
        
        return $result['count'] > 0;
    }
    
    /**
     * 商品情報取得
     * 
     * @param int $productId 商品ID
     * @return array|null 商品情報
     */
    private function getProductInfo($productId) {
        $sql = "SELECT * FROM products WHERE id = :product_id LIMIT 1";
        return $this->db->fetch($sql, ['product_id' => $productId]);
    }
    
    /**
     * 企業の補助金額を取得
     * 
     * @param int $companyId 企業ID
     * @return float 補助金額
     */
    private function getSubsidyAmount($companyId) {
        $sql = "SELECT subsidy_amount FROM companies WHERE id = :company_id LIMIT 1";
        $result = $this->db->fetch($sql, ['company_id' => $companyId]);
        
        return $result ? (float)$result['subsidy_amount'] : 0.0;
    }
    
    /**
     * 曜日を日本語に変換
     * 
     * @param int $dayOfWeek 曜日（0=日曜, 6=土曜）
     * @return string 日本語曜日
     */
    private function getDayOfWeekJp($dayOfWeek) {
        $days = ['日', '月', '火', '水', '木', '金', '土'];
        return $days[$dayOfWeek];
    }
}
