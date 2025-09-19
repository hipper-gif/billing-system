<?php
/**
 * PaymentManager - 集金管理特化版
 * 
 * Smiley配食事業の集金管理業務に特化したクラス
 * 「どこにいくら集金が必要で、いくら集金済みか」を効率管理
 * 
 * @version 5.0
 * @date 2025-09-19
 * @purpose 集金業務の完全自動化・効率化
 */

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/SecurityHelper.php';

class PaymentManager {
    
    private $db;
    
    /**
     * コンストラクタ
     */
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    // =====================================================
    // 集金管理メイン機能
    // =====================================================
    
    /**
     * 集金状況一覧取得
     * 
     * @param array $filters フィルター条件
     * @return array 集金対象企業一覧
     */
    public function getCollectionList($filters = []) {
        try {
            $sql = "SELECT * FROM collection_status_view WHERE 1=1";
            $params = [];
            
            // 企業名検索
            if (!empty($filters['company_name'])) {
                $sql .= " AND company_name LIKE ?";
                $params[] = '%' . $filters['company_name'] . '%';
            }
            
            // アラートレベルフィルター
            if (!empty($filters['alert_level'])) {
                $sql .= " AND alert_level = ?";
                $params[] = $filters['alert_level'];
            }
            
            // 金額範囲フィルター
            if (!empty($filters['amount_min'])) {
                $sql .= " AND outstanding_amount >= ?";
                $params[] = $filters['amount_min'];
            }
            
            if (!empty($filters['amount_max'])) {
                $sql .= " AND outstanding_amount <= ?";
                $params[] = $filters['amount_max'];
            }
            
            // 期限フィルター
            if (!empty($filters['due_date_filter'])) {
                switch ($filters['due_date_filter']) {
                    case 'today':
                        $sql .= " AND due_date <= CURDATE()";
                        break;
                    case 'this_week':
                        $sql .= " AND due_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
                        break;
                    case 'this_month':
                        $sql .= " AND due_date <= LAST_DAY(CURDATE())";
                        break;
                }
            }
            
            // ソート（デフォルト：優先度順）
            $sort_order = $filters['sort'] ?? 'priority';
            switch ($sort_order) {
                case 'amount_desc':
                    $sql .= " ORDER BY outstanding_amount DESC";
                    break;
                case 'due_date':
                    $sql .= " ORDER BY due_date ASC";
                    break;
                case 'company_name':
                    $sql .= " ORDER BY company_name ASC";
                    break;
                default: // priority
                    $sql .= " ORDER BY 
                        CASE alert_level
                            WHEN 'overdue' THEN 1
                            WHEN 'urgent' THEN 2  
                            ELSE 3
                        END,
                        due_date ASC,
                        outstanding_amount DESC";
            }
            
            // ページネーション
            $limit = $filters['limit'] ?? 50;
            $offset = ($filters['page'] ?? 1 - 1) * $limit;
            $sql .= " LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            
            return $this->db->query($sql, $params);
            
        } catch (Exception $e) {
            error_log("PaymentManager::getCollectionList Error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'データ取得中にエラーが発生しました',
                'debug' => $e->getMessage()
            ];
        }
    }
    
    /**
     * 集金サマリー情報取得
     * 
     * @return array サマリー統計情報
     */
    public function getCollectionSummary() {
        try {
            $sql = "
                SELECT 
                    -- 今月の売上統計
                    (SELECT COALESCE(SUM(total_amount), 0) 
                     FROM invoices 
                     WHERE DATE_FORMAT(issue_date, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')) as current_month_sales,
                    
                    -- 未回収統計
                    SUM(outstanding_amount) as total_outstanding,
                    COUNT(*) as outstanding_count,
                    
                    -- 期限切れ統計
                    SUM(CASE WHEN alert_level = 'overdue' THEN outstanding_amount ELSE 0 END) as overdue_amount,
                    COUNT(CASE WHEN alert_level = 'overdue' THEN 1 END) as overdue_count,
                    
                    -- 期限間近統計
                    SUM(CASE WHEN alert_level = 'urgent' THEN outstanding_amount ELSE 0 END) as urgent_amount,
                    COUNT(CASE WHEN alert_level = 'urgent' THEN 1 END) as urgent_count,
                    
                    -- 回収率計算
                    ROUND(
                        (current_month_sales - SUM(outstanding_amount)) / 
                        NULLIF(current_month_sales, 0) * 100, 1
                    ) as collection_rate
                FROM collection_status_view
            ";
            
            $result = $this->db->queryOne($sql);
            
            // 回収率計算の安全対策
            if ($result && $result['current_month_sales'] > 0) {
                $collected = $result['current_month_sales'] - $result['total_outstanding'];
                $result['collection_rate'] = round(($collected / $result['current_month_sales']) * 100, 1);
            } else {
                $result['collection_rate'] = 0;
            }
            
            return [
                'success' => true,
                'data' => $result
            ];
            
        } catch (Exception $e) {
            error_log("PaymentManager::getCollectionSummary Error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'サマリー取得中にエラーが発生しました',
                'debug' => $e->getMessage()
            ];
        }
    }
    
    /**
     * 満額入金記録
     * 
     * @param int $invoice_id 請求書ID
     * @param array $payment_data 入金データ
     * @return array 処理結果
     */
    public function recordFullPayment($invoice_id, $payment_data) {
        try {
            $this->db->beginTransaction();
            
            // 請求書情報取得
            $invoice = $this->getInvoiceById($invoice_id);
            if (!$invoice) {
                throw new Exception("請求書が見つかりません（ID: {$invoice_id}）");
            }
            
            // 未回収金額計算
            $outstanding = $this->calculateOutstandingAmount($invoice_id);
            if ($outstanding <= 0) {
                throw new Exception("この請求書は既に完済済みです");
            }
            
            // 満額入金データ準備
            $full_payment_data = [
                'invoice_id' => $invoice_id,
                'amount' => $outstanding,
                'payment_method' => $payment_data['payment_method'] ?? 'cash',
                'payment_date' => $payment_data['payment_date'] ?? date('Y-m-d'),
                'reference_number' => $payment_data['reference_number'] ?? null,
                'notes' => $payment_data['notes'] ?? '満額入金記録',
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            // 支払い記録挿入
            $payment_id = $this->insertPaymentRecord($full_payment_data);
            
            // 請求書ステータス更新
            $this->updateInvoiceStatus($invoice_id, 'paid');
            
            // 操作ログ記録
            $this->logPaymentAction('record_full_payment', [
                'invoice_id' => $invoice_id,
                'amount' => $outstanding,
                'payment_method' => $full_payment_data['payment_method']
            ]);
            
            $this->db->commit();
            
            return [
                'success' => true,
                'payment_id' => $payment_id,
                'amount' => $outstanding,
                'message' => "満額入金記録が完了しました（¥" . number_format($outstanding) . "）"
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("PaymentManager::recordFullPayment Error: " . $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * 一括満額入金記録
     * 
     * @param array $invoice_ids 請求書ID配列
     * @param array $payment_data 入金データ
     * @return array 処理結果
     */
    public function recordBulkFullPayments($invoice_ids, $payment_data) {
        $results = [];
        $total_amount = 0;
        $success_count = 0;
        $failed_invoices = [];
        
        foreach ($invoice_ids as $invoice_id) {
            $result = $this->recordFullPayment($invoice_id, $payment_data);
            $results[$invoice_id] = $result;
            
            if ($result['success']) {
                $success_count++;
                $total_amount += $result['amount'];
            } else {
                $failed_invoices[] = [
                    'invoice_id' => $invoice_id,
                    'error' => $result['error']
                ];
            }
        }
        
        return [
            'success' => $success_count > 0,
            'total_processed' => count($invoice_ids),
            'success_count' => $success_count,
            'failed_count' => count($failed_invoices),
            'total_amount' => $total_amount,
            'failed_invoices' => $failed_invoices,
            'message' => "{$success_count}件の入金記録が完了しました（合計¥" . number_format($total_amount) . "）"
        ];
    }
    
    /**
     * 緊急回収アラート取得
     * 
     * @return array 緊急対応が必要な案件一覧
     */
    public function getUrgentCollectionAlerts() {
        try {
            $sql = "
                SELECT * 
                FROM urgent_collection_alerts_view 
                ORDER BY priority_score DESC, outstanding_amount DESC
                LIMIT 20
            ";
            
            $alerts = $this->db->query($sql);
            
            return [
                'success' => true,
                'data' => [
                    'urgent_count' => count($alerts),
                    'total_urgent_amount' => array_sum(array_column($alerts, 'outstanding_amount')),
                    'alerts' => $alerts
                ]
            ];
            
        } catch (Exception $e) {
            error_log("PaymentManager::getUrgentCollectionAlerts Error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'アラート取得中にエラーが発生しました'
            ];
        }
    }
    
    /**
     * 今日の集金予定取得
     * 
     * @return array 今日の集金予定一覧
     */
    public function getTodayCollectionSchedule() {
        try {
            $sql = "
                SELECT * 
                FROM daily_collection_schedule_view 
                WHERE schedule_category IN ('today', 'tomorrow')
                ORDER BY due_date ASC, outstanding_amount DESC
            ";
            
            $schedule = $this->db->query($sql);
            
            $today_items = array_filter($schedule, function($item) {
                return $item['schedule_category'] === 'today';
            });
            
            $tomorrow_items = array_filter($schedule, function($item) {
                return $item['schedule_category'] === 'tomorrow';
            });
            
            return [
                'success' => true,
                'data' => [
                    'today' => array_values($today_items),
                    'tomorrow' => array_values($tomorrow_items),
                    'today_count' => count($today_items),
                    'tomorrow_count' => count($tomorrow_items),
                    'today_amount' => array_sum(array_column($today_items, 'outstanding_amount')),
                    'tomorrow_amount' => array_sum(array_column($tomorrow_items, 'outstanding_amount'))
                ]
            ];
            
        } catch (Exception $e) {
            error_log("PaymentManager::getTodayCollectionSchedule Error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => '予定取得中にエラーが発生しました'
            ];
        }
    }
    
    /**
     * 印刷用データ取得
     * 
     * @param array $invoice_ids 請求書ID配列
     * @return array 印刷用データ
     */
    public function getCollectionPrintData($invoice_ids) {
        try {
            if (empty($invoice_ids)) {
                return [
                    'success' => false,
                    'error' => '印刷対象が選択されていません'
                ];
            }
            
            $placeholders = str_repeat('?,', count($invoice_ids) - 1) . '?';
            $sql = "
                SELECT 
                    csv.*,
                    -- 配達・アクセス情報
                    c.delivery_location,
                    c.delivery_instructions,
                    c.access_instructions,
                    c.parking_info
                FROM collection_status_view csv
                JOIN companies c ON csv.company_id = c.id  
                WHERE csv.invoice_id IN ({$placeholders})
                ORDER BY csv.due_date ASC, csv.company_name ASC
            ";
            
            $print_data = $this->db->query($sql, $invoice_ids);
            
            return [
                'success' => true,
                'data' => [
                    'items' => $print_data,
                    'total_count' => count($print_data),
                    'total_amount' => array_sum(array_column($print_data, 'outstanding_amount')),
                    'print_date' => date('Y年m月d日'),
                    'print_time' => date('H:i')
                ]
            ];
            
        } catch (Exception $e) {
            error_log("PaymentManager::getCollectionPrintData Error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => '印刷データ取得中にエラーが発生しました'
            ];
        }
    }
    
    // =====================================================
    // 支払方法管理
    // =====================================================
    
    /**
     * 支払方法選択肢取得（PayPay対応）
     * 
     * @return array 支払方法選択肢
     */
    public static function getPaymentMethods() {
        return [
            'cash' => '💵 現金',
            'bank_transfer' => '🏦 銀行振込',
            'paypay' => '📱 PayPay',
            'account_debit' => '🏦 口座引き落とし',
            'mixed' => '💳 混合',
            'other' => '💼 その他'
        ];
    }
    
    /**
     * 支払方法別統計取得
     * 
     * @return array 支払方法別統計
     */
    public function getPaymentMethodsStatistics() {
        try {
            $sql = "SELECT * FROM payment_methods_summary_view ORDER BY total_amount DESC";
            $stats = $this->db->query($sql);
            
            return [
                'success' => true,
                'data' => $stats
            ];
            
        } catch (Exception $e) {
            error_log("PaymentManager::getPaymentMethodsStatistics Error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => '支払方法統計取得中にエラーが発生しました'
            ];
        }
    }
    
    // =====================================================
    // 内部ヘルパーメソッド
    // =====================================================
    
    /**
     * 請求書情報取得
     */
    private function getInvoiceById($invoice_id) {
        $sql = "SELECT * FROM invoices WHERE id = ?";
        return $this->db->queryOne($sql, [$invoice_id]);
    }
    
    /**
     * 未回収金額計算
     */
    private function calculateOutstandingAmount($invoice_id) {
        $sql = "
            SELECT 
                i.total_amount - COALESCE(SUM(p.amount), 0) as outstanding
            FROM invoices i
            LEFT JOIN payments p ON i.id = p.invoice_id
            WHERE i.id = ?
            GROUP BY i.id
        ";
        
        $result = $this->db->queryOne($sql, [$invoice_id]);
        return $result ? $result['outstanding'] : 0;
    }
    
    /**
     * 支払い記録挿入
     */
    private function insertPaymentRecord($payment_data) {
        $sql = "
            INSERT INTO payments (
                invoice_id, amount, payment_method, payment_date,
                reference_number, notes, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ";
        
        $params = [
            $payment_data['invoice_id'],
            $payment_data['amount'],
            $payment_data['payment_method'],
            $payment_data['payment_date'],
            $payment_data['reference_number'],
            $payment_data['notes'],
            $payment_data['created_at']
        ];
        
        $this->db->query($sql, $params);
        return $this->db->getLastInsertId();
    }
    
    /**
     * 請求書ステータス更新
     */
    private function updateInvoiceStatus($invoice_id, $status) {
        $sql = "UPDATE invoices SET status = ?, updated_at = NOW() WHERE id = ?";
        return $this->db->query($sql, [$status, $invoice_id]);
    }
    
    /**
     * 操作ログ記録
     */
    private function logPaymentAction($action, $details) {
        try {
            $sql = "
                INSERT INTO audit_logs (
                    action_type, table_name, record_id, user_name,
                    description, new_values, executed_at
                ) VALUES (?, ?, ?, ?, ?, ?, NOW())
            ";
            
            $this->db->query($sql, [
                'update',
                'payments',
                $details['invoice_id'] ?? null,
                'system', // TODO: 実際のユーザー情報に置き換え
                $action . ': ' . json_encode($details),
                json_encode($details)
            ]);
            
        } catch (Exception $e) {
            error_log("PaymentManager::logPaymentAction Error: " . $e->getMessage());
            // ログ記録失敗は処理を止めない
        }
    }
    
    /**
     * データ入力値検証
     */
    private function validatePaymentData($payment_data) {
        $errors = [];
        
        if (empty($payment_data['payment_method'])) {
            $errors[] = '支払方法は必須です';
        }
        
        if (!in_array($payment_data['payment_method'], array_keys(self::getPaymentMethods()))) {
            $errors[] = '無効な支払方法です';
        }
        
        if (empty($payment_data['payment_date'])) {
            $errors[] = '入金日は必須です';
        }
        
        if (!empty($payment_data['amount']) && !is_numeric($payment_data['amount'])) {
            $errors[] = '金額は数値で入力してください';
        }
        
        return $errors;
    }
    
    /**
     * デバッグ用：データベース接続テスト
     */
    public function testDatabaseConnection() {
        try {
            $result = $this->db->queryOne("SELECT 1 as test");
            return [
                'success' => true,
                'message' => 'データベース接続正常',
                'data' => $result
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'データベース接続エラー: ' . $e->getMessage()
            ];
        }
    }
}

?>
