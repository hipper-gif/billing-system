<?php
/**
 * Smiley配食事業専用請求書生成クラス
 * 配達先企業別・部署別・個人別請求書に対応
 * 
 * 実装方針：
 * - 実際のテーブル構造（invoices, invoice_details）に対応
 * - Databaseクラスの標準メソッドを使用
 * - セキュリティ対策実装
 * - 詳細なエラーハンドリング
 */
class SmileyInvoiceGenerator {
    private $db;
    
    // 請求書タイプ定義
    const TYPE_COMPANY_BULK = 'company_bulk';        // 企業一括請求
    const TYPE_DEPARTMENT_BULK = 'department_bulk';  // 部署別一括請求
    const TYPE_INDIVIDUAL = 'individual';            // 個人請求
    const TYPE_MIXED = 'mixed';                      // 混合請求
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * 請求書生成（メイン処理）
     * 
     * @param array $params 生成パラメータ
     * @return array 生成結果
     */
    public function generateInvoices($params) {
        // パラメータバリデーション
        if (empty($params['period_start']) || empty($params['period_end'])) {
            throw new InvalidArgumentException('請求期間の指定が必要です');
        }
        
        $invoiceType = $params['invoice_type'] ?? self::TYPE_COMPANY_BULK;
        $periodStart = $params['period_start'];
        $periodEnd = $params['period_end'];
        $dueDate = $params['due_date'] ?? $this->calculateDueDate($periodEnd);
        $targetIds = $params['target_ids'] ?? [];
        
        $this->db->beginTransaction();
        
        try {
            $result = [];
            
            switch ($invoiceType) {
                case self::TYPE_COMPANY_BULK:
                    $result = $this->generateCompanyBulkInvoices($periodStart, $periodEnd, $dueDate, $targetIds);
                    break;
                    
                case self::TYPE_DEPARTMENT_BULK:
                    $result = $this->generateDepartmentBulkInvoices($periodStart, $periodEnd, $dueDate, $targetIds);
                    break;
                    
                case self::TYPE_INDIVIDUAL:
                    $result = $this->generateIndividualInvoices($periodStart, $periodEnd, $dueDate, $targetIds);
                    break;
                    
                case self::TYPE_MIXED:
                    $result = $this->generateMixedInvoices($periodStart, $periodEnd, $dueDate, $targetIds);
                    break;
                    
                default:
                    throw new InvalidArgumentException("未対応の請求書タイプ: {$invoiceType}");
            }
            
            $this->db->commit();
            return $result;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * 企業一括請求書生成
     */
    private function generateCompanyBulkInvoices($periodStart, $periodEnd, $dueDate, $targetCompanyIds) {
        $generatedInvoices = 0;
        $totalAmount = 0;
        $invoiceIds = [];
        
        // 対象企業の取得
        $companies = $this->getTargetCompanies($targetCompanyIds);
        
        foreach ($companies as $company) {
            // 企業の注文データ取得
            $orderData = $this->getCompanyOrderData($company['id'], $periodStart, $periodEnd);
            
            if (empty($orderData['orders'])) {
                continue; // 注文がない企業はスキップ
            }
            
            // 請求書作成
            $invoiceData = [
                'invoice_type' => self::TYPE_COMPANY_BULK,
                'company_id' => $company['id'],
                'company_name' => $company['company_name'],
                'department_id' => null,
                'department_name' => null,
                'user_id' => null,
                'user_code' => null,
                'user_name' => null,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'due_date' => $dueDate,
                'subtotal' => $orderData['subtotal'],
                'tax_amount' => $orderData['tax_amount'],
                'total_amount' => $orderData['total_amount']
            ];
            
            $invoiceId = $this->createInvoice($invoiceData);
            
            // 請求書明細作成
            $this->createInvoiceDetails($invoiceId, $orderData['orders']);
            
            $generatedInvoices++;
            $totalAmount += $orderData['total_amount'];
            $invoiceIds[] = $invoiceId;
        }
        
        return [
            'generated_invoices' => $generatedInvoices,
            'total_amount' => $totalAmount,
            'invoice_ids' => $invoiceIds,
            'type' => self::TYPE_COMPANY_BULK
        ];
    }
    
    /**
     * 部署別一括請求書生成
     */
    private function generateDepartmentBulkInvoices($periodStart, $periodEnd, $dueDate, $targetDepartmentIds) {
        $generatedInvoices = 0;
        $totalAmount = 0;
        $invoiceIds = [];
        
        // 対象部署の取得
        $departments = $this->getTargetDepartments($targetDepartmentIds);
        
        foreach ($departments as $department) {
            // 部署の注文データ取得
            $orderData = $this->getDepartmentOrderData($department['id'], $periodStart, $periodEnd);
            
            if (empty($orderData['orders'])) {
                continue;
            }
            
            // 請求書作成
            $invoiceData = [
                'invoice_type' => self::TYPE_DEPARTMENT_BULK,
                'company_id' => $department['company_id'],
                'company_name' => $department['company_name'],
                'department_id' => $department['id'],
                'department_name' => $department['department_name'],
                'user_id' => null,
                'user_code' => null,
                'user_name' => null,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'due_date' => $dueDate,
                'subtotal' => $orderData['subtotal'],
                'tax_amount' => $orderData['tax_amount'],
                'total_amount' => $orderData['total_amount']
            ];
            
            $invoiceId = $this->createInvoice($invoiceData);
            
            // 請求書明細作成
            $this->createInvoiceDetails($invoiceId, $orderData['orders']);
            
            $generatedInvoices++;
            $totalAmount += $orderData['total_amount'];
            $invoiceIds[] = $invoiceId;
        }
        
        return [
            'generated_invoices' => $generatedInvoices,
            'total_amount' => $totalAmount,
            'invoice_ids' => $invoiceIds,
            'type' => self::TYPE_DEPARTMENT_BULK
        ];
    }
    
    /**
     * 個人請求書生成
     */
    private function generateIndividualInvoices($periodStart, $periodEnd, $dueDate, $targetUserIds) {
        $generatedInvoices = 0;
        $totalAmount = 0;
        $invoiceIds = [];
        
        // 対象利用者の取得
        $users = $this->getTargetUsers($targetUserIds);
        
        foreach ($users as $user) {
            // 利用者の注文データ取得
            $orderData = $this->getUserOrderData($user['id'], $periodStart, $periodEnd);
            
            if (empty($orderData['orders'])) {
                continue;
            }
            
            // 個人請求最低金額チェック
            $minAmount = $this->getSystemSetting('individual_invoice_min_amount', 1000);
            if ($orderData['total_amount'] < $minAmount) {
                continue; // 最低金額未満はスキップ
            }
            
            // 請求書作成
            $invoiceData = [
                'invoice_type' => self::TYPE_INDIVIDUAL,
                'company_id' => $user['company_id'],
                'company_name' => $user['company_name'],
                'department_id' => $user['department_id'],
                'department_name' => $user['department_name'],
                'user_id' => $user['id'],
                'user_code' => $user['user_code'],
                'user_name' => $user['user_name'],
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'due_date' => $dueDate,
                'subtotal' => $orderData['subtotal'],
                'tax_amount' => $orderData['tax_amount'],
                'total_amount' => $orderData['total_amount']
            ];
            
            $invoiceId = $this->createInvoice($invoiceData);
            
            // 請求書明細作成
            $this->createInvoiceDetails($invoiceId, $orderData['orders']);
            
            $generatedInvoices++;
            $totalAmount += $orderData['total_amount'];
            $invoiceIds[] = $invoiceId;
        }
        
        return [
            'generated_invoices' => $generatedInvoices,
            'total_amount' => $totalAmount,
            'invoice_ids' => $invoiceIds,
            'type' => self::TYPE_INDIVIDUAL
        ];
    }
    
    /**
     * 混合請求書生成
     */
    private function generateMixedInvoices($periodStart, $periodEnd, $dueDate, $targetIds) {
        // 企業・部署・個人の混合請求を自動判定して生成
        $result = [
            'generated_invoices' => 0,
            'total_amount' => 0,
            'invoice_ids' => [],
            'type' => self::TYPE_MIXED
        ];
        
        // 1. 企業一括請求可能な企業を抽出
        $companyBulkResult = $this->generateCompanyBulkInvoices($periodStart, $periodEnd, $dueDate, []);
        $result = $this->mergeResults($result, $companyBulkResult);
        
        // 2. 残りの部署・個人について個別処理
        // (詳細実装は実際のビジネスルールに応じて調整)
        
        return $result;
    }
    
    /**
     * 請求書レコード作成
     */
    private function createInvoice($data) {
        $invoiceNumber = $this->generateInvoiceNumber();
        
        $sql = "INSERT INTO invoices (
                    invoice_number, company_id, company_name, 
                    department_id, department_name,
                    user_id, user_code, user_name,
                    invoice_date, due_date, period_start, period_end,
                    subtotal, tax_rate, tax_amount, total_amount,
                    invoice_type, status, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', NOW(), NOW())";
        
        $taxRate = 10.00; // 消費税率
        
        $params = [
            $invoiceNumber,
            $data['company_id'],
            $data['company_name'],
            $data['department_id'],
            $data['department_name'],
            $data['user_id'],
            $data['user_code'],
            $data['user_name'],
            date('Y-m-d'),
            $data['due_date'],
            $data['period_start'],
            $data['period_end'],
            $data['subtotal'],
            $taxRate,
            $data['tax_amount'],
            $data['total_amount'],
            $data['invoice_type']
        ];
        
        return $this->db->insert($sql, $params);
    }
    
    /**
     * 請求書明細作成
     */
    private function createInvoiceDetails($invoiceId, $orders) {
        $sql = "INSERT INTO invoice_details (
                    invoice_id, order_id, order_date, 
                    product_code, product_name, quantity, unit_price, amount,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        foreach ($orders as $order) {
            $params = [
                $invoiceId,
                $order['id'],
                $order['order_date'],
                $order['product_code'],
                $order['product_name'],
                $order['quantity'],
                $order['unit_price'],
                $order['total_amount']
            ];
            
            $this->db->insert($sql, $params);
        }
    }
    
    /**
     * 企業の注文データ取得
     */
    private function getCompanyOrderData($companyId, $periodStart, $periodEnd) {
        $sql = "SELECT o.*, u.user_name
                FROM orders o 
                INNER JOIN users u ON o.user_code = u.user_code COLLATE utf8mb4_unicode_ci
                WHERE u.company_id = ? 
                AND o.order_date BETWEEN ? AND ?
                ORDER BY o.order_date";
        
        $orders = $this->db->fetchAll($sql, [$companyId, $periodStart, $periodEnd]);
        
        return $this->calculateOrderTotals($orders);
    }
    
    /**
     * 部署の注文データ取得
     */
    private function getDepartmentOrderData($departmentId, $periodStart, $periodEnd) {
        $sql = "SELECT o.*, u.user_name
                FROM orders o 
                INNER JOIN users u ON o.user_code = u.user_code COLLATE utf8mb4_unicode_ci
                WHERE u.department_id = ? 
                AND o.order_date BETWEEN ? AND ?
                ORDER BY o.order_date";
        
        $orders = $this->db->fetchAll($sql, [$departmentId, $periodStart, $periodEnd]);
        
        return $this->calculateOrderTotals($orders);
    }
    
    /**
     * 個人の注文データ取得
     */
    private function getUserOrderData($userId, $periodStart, $periodEnd) {
        $sql = "SELECT o.*, u.user_name
                FROM orders o 
                INNER JOIN users u ON o.user_code = u.user_code COLLATE utf8mb4_unicode_ci
                WHERE u.id = ? 
                AND o.order_date BETWEEN ? AND ?
                ORDER BY o.order_date";
        
        $orders = $this->db->fetchAll($sql, [$userId, $periodStart, $periodEnd]);
        
        return $this->calculateOrderTotals($orders);
    }
    
    /**
     * 注文金額計算
     */
    private function calculateOrderTotals($orders) {
        $subtotal = 0;
        
        foreach ($orders as &$order) {
            $subtotal += $order['total_amount'];
        }
        
        $taxAmount = round($subtotal * 0.10); // 消費税10%
        $totalAmount = $subtotal + $taxAmount;
        
        return [
            'orders' => $orders,
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount
        ];
    }
    
    /**
     * 対象企業取得
     */
    private function getTargetCompanies($targetCompanyIds = []) {
        $sql = "SELECT * FROM companies WHERE is_active = 1";
        $params = [];
        
        if (!empty($targetCompanyIds)) {
            $placeholders = str_repeat('?,', count($targetCompanyIds) - 1) . '?';
            $sql .= " AND id IN ($placeholders)";
            $params = $targetCompanyIds;
        }
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * 対象部署取得
     */
    private function getTargetDepartments($targetDepartmentIds = []) {
        $sql = "SELECT d.*, c.company_name 
                FROM departments d 
                INNER JOIN companies c ON d.company_id = c.id 
                WHERE d.is_active = 1 AND c.is_active = 1";
        $params = [];
        
        if (!empty($targetDepartmentIds)) {
            $placeholders = str_repeat('?,', count($targetDepartmentIds) - 1) . '?';
            $sql .= " AND d.id IN ($placeholders)";
            $params = $targetDepartmentIds;
        }
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * 対象利用者取得
     */
    private function getTargetUsers($targetUserIds = []) {
        $sql = "SELECT u.*, c.company_name, d.department_name
                FROM users u 
                LEFT JOIN companies c ON u.company_id = c.id 
                LEFT JOIN departments d ON u.department_id = d.id 
                WHERE u.is_active = 1";
        $params = [];
        
        if (!empty($targetUserIds)) {
            $placeholders = str_repeat('?,', count($targetUserIds) - 1) . '?';
            $sql .= " AND u.id IN ($placeholders)";
            $params = $targetUserIds;
        }
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * 請求書番号生成
     */
    private function generateInvoiceNumber() {
        $prefix = date('Y');
        
        // 年度内の連番取得
        $sql = "SELECT COUNT(*) as count FROM invoices WHERE invoice_number LIKE ?";
        $result = $this->db->fetchOne($sql, [$prefix . '%']);
        $sequence = $result['count'] + 1;
        
        return $prefix . sprintf('%06d', $sequence);
    }
    
    /**
     * 支払期限計算
     */
    private function calculateDueDate($periodEnd) {
        // 月末締め翌月末支払い
        $date = new DateTime($periodEnd);
        $date->modify('+1 month');
        $date->modify('last day of this month');
        return $date->format('Y-m-d');
    }
    
    /**
     * システム設定値取得
     */
    private function getSystemSetting($key, $default = null) {
        $sql = "SELECT setting_value FROM system_settings WHERE setting_key = ?";
        $result = $this->db->fetchOne($sql, [$key]);
        
        return $result ? $result['setting_value'] : $default;
    }
    
    /**
     * 結果マージ
     */
    private function mergeResults($result1, $result2) {
        return [
            'generated_invoices' => $result1['generated_invoices'] + $result2['generated_invoices'],
            'total_amount' => $result1['total_amount'] + $result2['total_amount'],
            'invoice_ids' => array_merge($result1['invoice_ids'], $result2['invoice_ids']),
            'type' => self::TYPE_MIXED
        ];
    }
    
    /**
     * 請求書一覧取得
     * 
     * @param array $filters フィルター条件
     * @param int $page ページ番号
     * @param int $limit 取得件数
     * @return array 請求書一覧データ
     */
    public function getInvoiceList($filters = [], $page = 1, $limit = 50) {
        $offset = ($page - 1) * $limit;
        
        // WHERE条件構築
        $whereClauses = [];
        $params = [];
        
        if (!empty($filters['company_id'])) {
            $whereClauses[] = "i.company_id = ?";
            $params[] = $filters['company_id'];
        }
        
        if (!empty($filters['status'])) {
            $whereClauses[] = "i.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['invoice_type'])) {
            $whereClauses[] = "i.invoice_type = ?";
            $params[] = $filters['invoice_type'];
        }
        
        if (!empty($filters['period_start'])) {
            $whereClauses[] = "i.period_start >= ?";
            $params[] = $filters['period_start'];
        }
        
        if (!empty($filters['period_end'])) {
            $whereClauses[] = "i.period_end <= ?";
            $params[] = $filters['period_end'];
        }
        
        if (!empty($filters['keyword'])) {
            $whereClauses[] = "(i.invoice_number LIKE ? OR i.company_name LIKE ?)";
            $keyword = '%' . $filters['keyword'] . '%';
            $params[] = $keyword;
            $params[] = $keyword;
        }
        
        $whereClause = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';
        
        // 総件数取得
        $countSql = "SELECT COUNT(*) as total FROM invoices i {$whereClause}";
        $totalCount = $this->db->fetchOne($countSql, $params)['total'];
        
        // 請求書一覧取得
        $sql = "SELECT 
                    i.*,
                    (SELECT COUNT(*) FROM invoice_details WHERE invoice_id = i.id) as detail_count
                FROM invoices i
                {$whereClause}
                ORDER BY i.created_at DESC
                LIMIT ? OFFSET ?";
        
        $params[] = $limit;
        $params[] = $offset;
        
        $invoices = $this->db->fetchAll($sql, $params);
        
        // ページネーション情報
        $totalPages = ceil($totalCount / $limit);
        
        return [
            'invoices' => $invoices,
            'total_count' => $totalCount,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => $totalPages,
            'has_next' => $page < $totalPages,
            'has_prev' => $page > 1
        ];
    }
    
    /**
     * 請求書詳細データ取得
     * 
     * @param int $invoiceId 請求書ID
     * @return array|null 請求書詳細データ
     */
    public function getInvoiceData($invoiceId) {
        // 請求書基本情報取得
        $sql = "SELECT * FROM invoices WHERE id = ?";
        $invoice = $this->db->fetchOne($sql, [$invoiceId]);
        
        if (!$invoice) {
            return null;
        }
        
        // 請求書明細取得
        $detailsSql = "SELECT * FROM invoice_details WHERE invoice_id = ? ORDER BY order_date, product_name";
        $details = $this->db->fetchAll($detailsSql, [$invoiceId]);
        
        $invoice['details'] = $details;
        
        return $invoice;
    }
    
    /**
     * 請求書ステータス更新
     * 
     * @param int $invoiceId 請求書ID
     * @param string $status 新しいステータス
     * @param string $notes 備考
     * @return bool 更新成功可否
     */
    public function updateInvoiceStatus($invoiceId, $status, $notes = '') {
        $validStatuses = ['draft', 'issued', 'sent', 'paid', 'overdue', 'cancelled'];
        
        if (!in_array($status, $validStatuses)) {
            throw new InvalidArgumentException('無効なステータスです');
        }
        
        $this->db->beginTransaction();
        
        try {
            // ステータス更新
            $sql = "UPDATE invoices SET 
                        status = ?, 
                        updated_at = NOW() 
                    WHERE id = ?";
            
            $result = $this->db->execute($sql, [$status, $invoiceId]);
            
            // ステータス変更履歴記録（将来拡張用）
            if (!empty($notes)) {
                $historySql = "INSERT INTO invoice_status_history 
                              (invoice_id, old_status, new_status, notes, created_at) 
                              SELECT ?, 
                                     (SELECT status FROM invoices WHERE id = ? LIMIT 1), 
                                     ?, ?, NOW()
                              ON DUPLICATE KEY UPDATE notes = VALUES(notes)";
                // テーブルが存在しない場合はスキップ
                try {
                    $this->db->execute($historySql, [$invoiceId, $invoiceId, $status, $notes]);
                } catch (Exception $e) {
                    // 履歴テーブルが未作成の場合は無視
                }
            }
            
            $this->db->commit();
            return $result > 0;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * 請求書削除（論理削除）
     * 
     * @param int $invoiceId 請求書ID
     * @return bool 削除成功可否
     */
    public function deleteInvoice($invoiceId) {
        // 実際には論理削除（ステータスをキャンセルに変更）
        return $this->updateInvoiceStatus($invoiceId, 'cancelled', '削除処理により自動キャンセル');
    }
    
    /**
     * 請求書統計情報取得
     * 
     * @return array 統計情報
     */
    public function getInvoiceStatistics() {
        $sql = "SELECT 
                    COUNT(*) as total_invoices,
                    COUNT(CASE WHEN status = 'issued' THEN 1 END) as issued_count,
                    COUNT(CASE WHEN status = 'sent' THEN 1 END) as sent_count,
                    COUNT(CASE WHEN status = 'paid' THEN 1 END) as paid_count,
                    COUNT(CASE WHEN status = 'overdue' THEN 1 END) as overdue_count,
                    COALESCE(SUM(total_amount), 0) as total_amount,
                    COALESCE(SUM(CASE WHEN status = 'paid' THEN total_amount ELSE 0 END), 0) as paid_amount,
                    COALESCE(SUM(CASE WHEN status IN ('issued', 'sent') THEN total_amount ELSE 0 END), 0) as pending_amount,
                    COALESCE(SUM(CASE WHEN status = 'overdue' THEN total_amount ELSE 0 END), 0) as overdue_amount
                FROM invoices 
                WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)";
        
        return $this->db->fetchOne($sql, []);
    }
}
?>
