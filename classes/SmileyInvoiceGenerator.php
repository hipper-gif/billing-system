<?php
/**
 * Smiley配食事業専用請求書生成クラス
 * 企業一括・部署別・個人請求に対応した請求書生成エンジン
 * 
 * @author Claude
 * @version 1.0.0
 * @created 2025-08-26
 */

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/SecurityHelper.php';

class SmileyInvoiceGenerator {
    private $db;
    
    // 請求書タイプ定義
    const TYPE_COMPANY_BULK = 'company_bulk';        // 企業一括請求
    const TYPE_DEPARTMENT_BULK = 'department_bulk';  // 部署別一括請求
    const TYPE_INDIVIDUAL = 'individual';            // 個人請求
    const TYPE_MIXED = 'mixed';                      // 混合請求（自動判定）
    
    // 請求書ステータス
    const STATUS_DRAFT = 'draft';                    // 下書き
    const STATUS_ISSUED = 'issued';                  // 発行済み
    const STATUS_SENT = 'sent';                      // 送付済み
    const STATUS_PAID = 'paid';                      // 支払済み
    const STATUS_OVERDUE = 'overdue';                // 支払期限超過
    const STATUS_CANCELLED = 'cancelled';            // キャンセル
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * 請求書生成ログ記録
     * 
     * @param string $invoiceType 請求書タイプ
     * @param array $result 生成結果
     */
    private function logInvoiceGeneration($invoiceType, $result) {
        $logData = [
            'action' => 'invoice_generation',
            'invoice_type' => $invoiceType,
            'total_invoices' => $result['total_invoices'] ?? 0,
            'total_amount' => $result['total_amount'] ?? 0,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        error_log("請求書生成完了: " . json_encode($logData, JSON_UNESCAPED_UNICODE));
    }
    
    /**
     * 対象部署データ取得
     * 
     * @param array $targetIds 対象部署ID配列
     * @return array 部署データ配列
     */
    private function getTargetDepartments($targetIds = []) {
        $whereClause = "WHERE d.is_active = 1 AND c.is_active = 1";
        $params = [];
        
        if (!empty($targetIds)) {
            $placeholders = str_repeat('?,', count($targetIds) - 1) . '?';
            $whereClause .= " AND d.id IN ({$placeholders})";
            $params = $targetIds;
        }
        
        $stmt = $this->db->prepare("
            SELECT d.*, c.company_name, c.billing_contact_person, c.billing_email
            FROM departments d
            INNER JOIN companies c ON d.company_id = c.id
            {$whereClause}
            ORDER BY c.company_name, d.department_name
        ");
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * 部署の注文データ取得
     * 
     * @param int $departmentId 部署ID
     * @param string $periodStart 期間開始日
     * @param string $periodEnd 期間終了日
     * @return array 注文データ
     */
    private function getDepartmentOrderData($departmentId, $periodStart, $periodEnd) {
        $stmt = $this->db->prepare("
            SELECT o.*, u.user_name
            FROM orders o
            LEFT JOIN users u ON o.user_id = u.id
            WHERE o.department_id = ?
                AND o.delivery_date BETWEEN ? AND ?
            ORDER BY o.delivery_date, o.user_name
        ");
        $stmt->execute([$departmentId, $periodStart, $periodEnd]);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 統計データ計算
        $totalAmount = array_sum(array_column($orders, 'total_amount'));
        $totalQuantity = array_sum(array_column($orders, 'quantity'));
        $orderCount = count($orders);
        
        return [
            'orders' => $orders,
            'total_amount' => $totalAmount,
            'total_quantity' => $totalQuantity,
            'order_count' => $orderCount,
            'unique_users' => count(array_unique(array_column($orders, 'user_id'))),
            'unique_products' => count(array_unique(array_column($orders, 'product_id')))
        ];
    }
    
    /**
     * 部署請求書データ構築
     * 
     * @param array $department 部署データ
     * @param array $orderData 注文データ
     * @param string $periodStart 期間開始日
     * @param string $periodEnd 期間終了日
     * @param string $dueDate 支払期限日
     * @return array 請求書データ
     */
    private function buildDepartmentInvoiceData($department, $orderData, $periodStart, $periodEnd, $dueDate) {
        $invoiceNumber = $this->generateInvoiceNumber();
        
        return [
            'invoice_number' => $invoiceNumber,
            'invoice_type' => self::TYPE_DEPARTMENT_BULK,
            'company_id' => $department['company_id'],
            'department_id' => $department['id'],
            'user_id' => null,
            'billing_company_name' => $department['company_name'] . ' ' . $department['department_name'],
            'billing_address' => '', // 企業住所を使用
            'billing_contact_person' => $department['manager_name'] ?: $department['billing_contact_person'],
            'billing_email' => $department['manager_email'] ?: $department['billing_email'],
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'issue_date' => date('Y-m-d'),
            'due_date' => $dueDate,
            'subtotal' => $orderData['total_amount'],
            'tax_rate' => $this->getTaxRate(),
            'tax_amount' => $this->calculateTaxAmount($orderData['total_amount']),
            'total_amount' => $this->calculateTotalWithTax($orderData['total_amount']),
            'order_count' => $orderData['order_count'],
            'total_quantity' => $orderData['total_quantity'],
            'status' => self::STATUS_ISSUED,
            'notes' => "部署別一括請求（部署：{$department['department_name']}、期間：{$periodStart}〜{$periodEnd}）",
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * 対象利用者データ取得
     * 
     * @param array $targetIds 対象利用者ID配列
     * @return array 利用者データ配列
     */
    private function getTargetUsers($targetIds = []) {
        $whereClause = "WHERE u.is_active = 1";
        $params = [];
        
        if (!empty($targetIds)) {
            $placeholders = str_repeat('?,', count($targetIds) - 1) . '?';
            $whereClause .= " AND u.id IN ({$placeholders})";
            $params = $targetIds;
        }
        
        $stmt = $this->db->prepare("
            SELECT u.*, c.company_name, d.department_name
            FROM users u
            LEFT JOIN companies c ON u.company_id = c.id
            LEFT JOIN departments d ON u.department_id = d.id
            {$whereClause}
            ORDER BY c.company_name, u.user_name
        ");
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * 利用者の注文データ取得
     * 
     * @param int $userId 利用者ID
     * @param string $periodStart 期間開始日
     * @param string $periodEnd 期間終了日
     * @return array 注文データ
     */
    private function getUserOrderData($userId, $periodStart, $periodEnd) {
        $stmt = $this->db->prepare("
            SELECT o.*
            FROM orders o
            WHERE o.user_id = ?
                AND o.delivery_date BETWEEN ? AND ?
            ORDER BY o.delivery_date
        ");
        $stmt->execute([$userId, $periodStart, $periodEnd]);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 統計データ計算
        $totalAmount = array_sum(array_column($orders, 'total_amount'));
        $totalQuantity = array_sum(array_column($orders, 'quantity'));
        $orderCount = count($orders);
        
        return [
            'orders' => $orders,
            'total_amount' => $totalAmount,
            'total_quantity' => $totalQuantity,
            'order_count' => $orderCount,
            'unique_products' => count(array_unique(array_column($orders, 'product_id')))
        ];
    }
    
    /**
     * 個人請求書データ構築
     * 
     * @param array $user 利用者データ
     * @param array $orderData 注文データ
     * @param string $periodStart 期間開始日
     * @param string $periodEnd 期間終了日
     * @param string $dueDate 支払期限日
     * @return array 請求書データ
     */
    private function buildIndividualInvoiceData($user, $orderData, $periodStart, $periodEnd, $dueDate) {
        $invoiceNumber = $this->generateInvoiceNumber();
        
        return [
            'invoice_number' => $invoiceNumber,
            'invoice_type' => self::TYPE_INDIVIDUAL,
            'company_id' => $user['company_id'],
            'department_id' => $user['department_id'],
            'user_id' => $user['id'],
            'billing_company_name' => $user['user_name'],
            'billing_address' => $user['address'] ?: '',
            'billing_contact_person' => $user['user_name'],
            'billing_email' => $user['email'],
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'issue_date' => date('Y-m-d'),
            'due_date' => $dueDate,
            'subtotal' => $orderData['total_amount'],
            'tax_rate' => $this->getTaxRate(),
            'tax_amount' => $this->calculateTaxAmount($orderData['total_amount']),
            'total_amount' => $this->calculateTotalWithTax($orderData['total_amount']),
            'order_count' => $orderData['order_count'],
            'total_quantity' => $orderData['total_quantity'],
            'status' => self::STATUS_ISSUED,
            'notes' => "個人請求（利用者：{$user['user_name']}、所属：{$user['company_name']} {$user['department_name']}、期間：{$periodStart}〜{$periodEnd}）",
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * 企業の部署ID取得
     * 
     * @param int $companyId 企業ID
     * @return array 部署ID配列
     */
    private function getCompanyDepartmentIds($companyId) {
        $stmt = $this->db->prepare("
            SELECT id FROM departments 
            WHERE company_id = ? AND is_active = 1
        ");
        $stmt->execute([$companyId]);
        
        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id');
    }
    
    /**
     * 企業の利用者ID取得
     * 
     * @param int $companyId 企業ID
     * @return array 利用者ID配列
     */
    private function getCompanyUserIds($companyId) {
        $stmt = $this->db->prepare("
            SELECT id FROM users 
            WHERE company_id = ? AND is_active = 1
        ");
        $stmt->execute([$companyId]);
        
        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id');
    }
    
    /**
     * 請求書データ取得
     * 
     * @param int $invoiceId 請求書ID
     * @return array|null 請求書データ
     */
    public function getInvoiceData($invoiceId) {
        $stmt = $this->db->prepare("
            SELECT i.*, c.company_name, c.company_address, 
                   d.department_name, u.user_name
            FROM invoices i
            LEFT JOIN companies c ON i.company_id = c.id
            LEFT JOIN departments d ON i.department_id = d.id
            LEFT JOIN users u ON i.user_id = u.id
            WHERE i.id = ?
        ");
        $stmt->execute([$invoiceId]);
        $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$invoice) {
            return null;
        }
        
        // 請求書明細取得
        $stmt = $this->db->prepare("
            SELECT * FROM invoice_details 
            WHERE invoice_id = ?
            ORDER BY delivery_date, user_name
        ");
        $stmt->execute([$invoiceId]);
        $invoice['details'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $invoice;
    }
    
    /**
     * 請求書ステータス更新
     * 
     * @param int $invoiceId 請求書ID
     * @param string $status 新しいステータス
     * @param string $notes 備考
     * @return bool 更新成功
     */
    public function updateInvoiceStatus($invoiceId, $status, $notes = '') {
        $validStatuses = [
            self::STATUS_DRAFT, self::STATUS_ISSUED, self::STATUS_SENT,
            self::STATUS_PAID, self::STATUS_OVERDUE, self::STATUS_CANCELLED
        ];
        
        if (!in_array($status, $validStatuses)) {
            throw new Exception("無効なステータスです: {$status}");
        }
        
        $stmt = $this->db->prepare("
            UPDATE invoices 
            SET status = ?, 
                notes = CONCAT(COALESCE(notes, ''), CASE WHEN notes IS NOT NULL AND notes != '' THEN '\n' ELSE '' END, ?),
                updated_at = NOW()
            WHERE id = ?
        ");
        
        $updateNote = date('Y-m-d H:i:s') . " ステータス変更: {$status}";
        if ($notes) {
            $updateNote .= " ({$notes})";
        }
        
        return $stmt->execute([$status, $updateNote, $invoiceId]);
    }
    
    /**
     * 請求書一覧取得
     * 
     * @param array $filters フィルター条件
     * @param int $page ページ番号
     * @param int $limit 1ページあたり件数
     * @return array 請求書一覧データ
     */
    public function getInvoiceList($filters = [], $page = 1, $limit = 50) {
        $whereConditions = [];
        $params = [];
        
        // フィルター処理
        if (!empty($filters['company_id'])) {
            $whereConditions[] = "i.company_id = ?";
            $params[] = $filters['company_id'];
        }
        
        if (!empty($filters['status'])) {
            $whereConditions[] = "i.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['period_start'])) {
            $whereConditions[] = "i.period_start >= ?";
            $params[] = $filters['period_start'];
        }
        
        if (!empty($filters['period_end'])) {
            $whereConditions[] = "i.period_end <= ?";
            $params[] = $filters['period_end'];
        }
        
        if (!empty($filters['invoice_type'])) {
            $whereConditions[] = "i.invoice_type = ?";
            $params[] = $filters['invoice_type'];
        }
        
        $whereClause = '';
        if (!empty($whereConditions)) {
            $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
        }
        
        // 総件数取得
        $countStmt = $this->db->prepare("
            SELECT COUNT(*) as total_count
            FROM invoices i
            LEFT JOIN companies c ON i.company_id = c.id
            {$whereClause}
        ");
        $countStmt->execute($params);
        $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total_count'];
        
        // データ取得
        $offset = ($page - 1) * $limit;
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $this->db->prepare("
            SELECT i.*, c.company_name, c.billing_contact_person
            FROM invoices i
            LEFT JOIN companies c ON i.company_id = c.id
            {$whereClause}
            ORDER BY i.issue_date DESC, i.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute($params);
        $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'invoices' => $invoices,
            'total_count' => $totalCount,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($totalCount / $limit)
        ];
    }
    
    /**
     * 請求書削除（論理削除）
     * 
     * @param int $invoiceId 請求書ID
     * @return bool 削除成功
     */
    public function deleteInvoice($invoiceId) {
        $this->db->beginTransaction();
        
        try {
            // 請求書ステータス確認
            $stmt = $this->db->prepare("SELECT status FROM invoices WHERE id = ?");
            $stmt->execute([$invoiceId]);
            $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$invoice) {
                throw new Exception('請求書が見つかりません');
            }
            
            if ($invoice['status'] === self::STATUS_PAID) {
                throw new Exception('支払済みの請求書は削除できません');
            }
            
            // キャンセル状態に変更
            $this->updateInvoiceStatus($invoiceId, self::STATUS_CANCELLED, '削除により自動キャンセル');
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
}
?>生成（メイン処理）
     * 
     * @param array $params 生成パラメータ
     * @return array 生成結果
     */
    public function generateInvoices($params) {
        $invoiceType = $params['invoice_type'] ?? self::TYPE_COMPANY_BULK;
        $periodStart = $params['period_start'];
        $periodEnd = $params['period_end'];
        $targetIds = $params['target_ids'] ?? [];
        $dueDate = $params['due_date'] ?? $this->calculateDueDate($periodEnd);
        $autoPdf = $params['auto_generate_pdf'] ?? false;
        
        // 入力値検証
        $this->validateGenerationParams($params);
        
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
                    throw new Exception("未対応の請求書タイプ: {$invoiceType}");
            }
            
            $this->db->commit();
            
            // ログ記録
            $this->logInvoiceGeneration($invoiceType, $result);
            
            return $result;
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("請求書生成エラー: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * 企業一括請求書生成
     * 
     * @param string $periodStart 期間開始日
     * @param string $periodEnd 期間終了日
     * @param string $dueDate 支払期限日
     * @param array $targetCompanyIds 対象企業ID配列
     * @return array 生成結果
     */
    private function generateCompanyBulkInvoices($periodStart, $periodEnd, $dueDate, $targetCompanyIds) {
        $generatedInvoices = [];
        $totalAmount = 0;
        
        // 対象企業の取得
        $companies = $this->getTargetCompanies($targetCompanyIds);
        
        foreach ($companies as $company) {
            // 企業の注文データ取得
            $orderData = $this->getCompanyOrderData($company['id'], $periodStart, $periodEnd);
            
            if (empty($orderData['orders'])) {
                continue; // 注文がない企業はスキップ
            }
            
            // 請求書データ作成
            $invoiceData = $this->buildCompanyInvoiceData($company, $orderData, $periodStart, $periodEnd, $dueDate);
            
            // 請求書レコード挿入
            $invoiceId = $this->insertInvoiceRecord($invoiceData);
            $generatedInvoices[] = $invoiceId;
            $totalAmount += $invoiceData['total_amount'];
            
            // 請求書明細挿入
            $this->insertInvoiceDetails($invoiceId, $orderData['orders']);
        }
        
        return [
            'success' => true,
            'type' => self::TYPE_COMPANY_BULK,
            'invoice_ids' => $generatedInvoices,
            'total_invoices' => count($generatedInvoices),
            'total_amount' => $totalAmount,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'due_date' => $dueDate,
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * 部署別一括請求書生成
     * 
     * @param string $periodStart 期間開始日
     * @param string $periodEnd 期間終了日
     * @param string $dueDate 支払期限日
     * @param array $targetDepartmentIds 対象部署ID配列
     * @return array 生成結果
     */
    private function generateDepartmentBulkInvoices($periodStart, $periodEnd, $dueDate, $targetDepartmentIds) {
        $generatedInvoices = [];
        $totalAmount = 0;
        
        // 対象部署の取得
        $departments = $this->getTargetDepartments($targetDepartmentIds);
        
        foreach ($departments as $department) {
            // 部署の注文データ取得
            $orderData = $this->getDepartmentOrderData($department['id'], $periodStart, $periodEnd);
            
            if (empty($orderData['orders'])) {
                continue; // 注文がない部署はスキップ
            }
            
            // 請求書データ作成
            $invoiceData = $this->buildDepartmentInvoiceData($department, $orderData, $periodStart, $periodEnd, $dueDate);
            
            // 請求書レコード挿入
            $invoiceId = $this->insertInvoiceRecord($invoiceData);
            $generatedInvoices[] = $invoiceId;
            $totalAmount += $invoiceData['total_amount'];
            
            // 請求書明細挿入
            $this->insertInvoiceDetails($invoiceId, $orderData['orders']);
        }
        
        return [
            'success' => true,
            'type' => self::TYPE_DEPARTMENT_BULK,
            'invoice_ids' => $generatedInvoices,
            'total_invoices' => count($generatedInvoices),
            'total_amount' => $totalAmount,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'due_date' => $dueDate,
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * 個人請求書生成
     * 
     * @param string $periodStart 期間開始日
     * @param string $periodEnd 期間終了日
     * @param string $dueDate 支払期限日
     * @param array $targetUserIds 対象利用者ID配列
     * @return array 生成結果
     */
    private function generateIndividualInvoices($periodStart, $periodEnd, $dueDate, $targetUserIds) {
        $generatedInvoices = [];
        $totalAmount = 0;
        
        // 対象利用者の取得
        $users = $this->getTargetUsers($targetUserIds);
        
        foreach ($users as $user) {
            // 利用者の注文データ取得
            $orderData = $this->getUserOrderData($user['id'], $periodStart, $periodEnd);
            
            if (empty($orderData['orders'])) {
                continue; // 注文がない利用者はスキップ
            }
            
            // 個人請求の最低金額チェック
            if ($orderData['total_amount'] < $this->getIndividualInvoiceThreshold()) {
                continue; // 最低金額未満はスキップ
            }
            
            // 請求書データ作成
            $invoiceData = $this->buildIndividualInvoiceData($user, $orderData, $periodStart, $periodEnd, $dueDate);
            
            // 請求書レコード挿入
            $invoiceId = $this->insertInvoiceRecord($invoiceData);
            $generatedInvoices[] = $invoiceId;
            $totalAmount += $invoiceData['total_amount'];
            
            // 請求書明細挿入
            $this->insertInvoiceDetails($invoiceId, $orderData['orders']);
        }
        
        return [
            'success' => true,
            'type' => self::TYPE_INDIVIDUAL,
            'invoice_ids' => $generatedInvoices,
            'total_invoices' => count($generatedInvoices),
            'total_amount' => $totalAmount,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'due_date' => $dueDate,
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * 混合請求書生成（自動判定）
     * 各企業の設定に基づいて最適な請求書タイプを自動選択
     * 
     * @param string $periodStart 期間開始日
     * @param string $periodEnd 期間終了日
     * @param string $dueDate 支払期限日
     * @param array $targetCompanyIds 対象企業ID配列
     * @return array 生成結果
     */
    private function generateMixedInvoices($periodStart, $periodEnd, $dueDate, $targetCompanyIds) {
        $results = [];
        $totalAmount = 0;
        $totalInvoices = 0;
        
        // 対象企業を取得
        $companies = $this->getTargetCompanies($targetCompanyIds);
        
        foreach ($companies as $company) {
            // 企業の請求書設定に基づいて最適タイプを判定
            $optimalType = $this->determineOptimalInvoiceType($company['id'], $periodStart, $periodEnd);
            
            switch ($optimalType) {
                case self::TYPE_COMPANY_BULK:
                    $result = $this->generateCompanyBulkInvoices($periodStart, $periodEnd, $dueDate, [$company['id']]);
                    break;
                case self::TYPE_DEPARTMENT_BULK:
                    $departmentIds = $this->getCompanyDepartmentIds($company['id']);
                    $result = $this->generateDepartmentBulkInvoices($periodStart, $periodEnd, $dueDate, $departmentIds);
                    break;
                case self::TYPE_INDIVIDUAL:
                    $userIds = $this->getCompanyUserIds($company['id']);
                    $result = $this->generateIndividualInvoices($periodStart, $periodEnd, $dueDate, $userIds);
                    break;
                default:
                    continue 2; // 次の企業へ
            }
            
            $results[] = $result;
            $totalAmount += $result['total_amount'];
            $totalInvoices += $result['total_invoices'];
        }
        
        return [
            'success' => true,
            'type' => self::TYPE_MIXED,
            'results' => $results,
            'total_invoices' => $totalInvoices,
            'total_amount' => $totalAmount,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'due_date' => $dueDate,
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * 最適な請求書タイプを判定
     * 
     * @param int $companyId 企業ID
     * @param string $periodStart 期間開始日
     * @param string $periodEnd 期間終了日
     * @return string 最適な請求書タイプ
     */
    private function determineOptimalInvoiceType($companyId, $periodStart, $periodEnd) {
        // 企業の請求書設定取得
        $stmt = $this->db->prepare("
            SELECT billing_method, payment_method 
            FROM companies 
            WHERE id = ? AND is_active = 1
        ");
        $stmt->execute([$companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$company) {
            return self::TYPE_COMPANY_BULK; // デフォルト
        }
        
        // 設定に基づく判定
        switch ($company['billing_method']) {
            case 'company':
                return self::TYPE_COMPANY_BULK;
            case 'department':
                return self::TYPE_DEPARTMENT_BULK;
            case 'individual':
                return self::TYPE_INDIVIDUAL;
            case 'mixed':
                // 注文データを分析して最適タイプを判定
                return $this->analyzeAndDetermineType($companyId, $periodStart, $periodEnd);
            default:
                return self::TYPE_COMPANY_BULK;
        }
    }
    
    /**
     * 注文データ分析による最適タイプ判定
     * 
     * @param int $companyId 企業ID
     * @param string $periodStart 期間開始日
     * @param string $periodEnd 期間終了日
     * @return string 最適な請求書タイプ
     */
    private function analyzeAndDetermineType($companyId, $periodStart, $periodEnd) {
        // 企業の注文統計を取得
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(DISTINCT user_id) as user_count,
                COUNT(DISTINCT department_id) as department_count,
                COUNT(*) as order_count,
                SUM(total_amount) as total_amount,
                AVG(total_amount) as avg_amount
            FROM orders 
            WHERE company_id = ? 
                AND delivery_date BETWEEN ? AND ?
        ");
        $stmt->execute([$companyId, $periodStart, $periodEnd]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$stats || $stats['order_count'] == 0) {
            return self::TYPE_COMPANY_BULK;
        }
        
        // 判定ロジック
        $userCount = intval($stats['user_count']);
        $departmentCount = intval($stats['department_count']);
        $orderCount = intval($stats['order_count']);
        $avgAmount = floatval($stats['avg_amount']);
        
        // 個人請求の判定（利用者少数かつ高額）
        if ($userCount <= 5 && $avgAmount >= 10000) {
            return self::TYPE_INDIVIDUAL;
        }
        
        // 部署別請求の判定（部署数が多くバランス良い）
        if ($departmentCount >= 3 && $userCount / $departmentCount >= 3) {
            return self::TYPE_DEPARTMENT_BULK;
        }
        
        // デフォルトは企業一括
        return self::TYPE_COMPANY_BULK;
    }
    
    /**
     * 対象企業データ取得
     * 
     * @param array $targetIds 対象企業ID配列
     * @return array 企業データ配列
     */
    private function getTargetCompanies($targetIds = []) {
        $whereClause = "WHERE is_active = 1";
        $params = [];
        
        if (!empty($targetIds)) {
            $placeholders = str_repeat('?,', count($targetIds) - 1) . '?';
            $whereClause .= " AND id IN ({$placeholders})";
            $params = $targetIds;
        }
        
        $stmt = $this->db->prepare("
            SELECT * FROM companies {$whereClause}
            ORDER BY company_name
        ");
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * 企業の注文データ取得
     * 
     * @param int $companyId 企業ID
     * @param string $periodStart 期間開始日
     * @param string $periodEnd 期間終了日
     * @return array 注文データ
     */
    private function getCompanyOrderData($companyId, $periodStart, $periodEnd) {
        // 注文データ取得
        $stmt = $this->db->prepare("
            SELECT o.*, u.user_name, d.department_name
            FROM orders o
            LEFT JOIN users u ON o.user_id = u.id
            LEFT JOIN departments d ON o.department_id = d.id
            WHERE o.company_id = ?
                AND o.delivery_date BETWEEN ? AND ?
            ORDER BY o.delivery_date, o.user_name
        ");
        $stmt->execute([$companyId, $periodStart, $periodEnd]);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 統計データ計算
        $totalAmount = array_sum(array_column($orders, 'total_amount'));
        $totalQuantity = array_sum(array_column($orders, 'quantity'));
        $orderCount = count($orders);
        
        return [
            'orders' => $orders,
            'total_amount' => $totalAmount,
            'total_quantity' => $totalQuantity,
            'order_count' => $orderCount,
            'unique_users' => count(array_unique(array_column($orders, 'user_id'))),
            'unique_products' => count(array_unique(array_column($orders, 'product_id')))
        ];
    }
    
    /**
     * 企業請求書データ構築
     * 
     * @param array $company 企業データ
     * @param array $orderData 注文データ
     * @param string $periodStart 期間開始日
     * @param string $periodEnd 期間終了日
     * @param string $dueDate 支払期限日
     * @return array 請求書データ
     */
    private function buildCompanyInvoiceData($company, $orderData, $periodStart, $periodEnd, $dueDate) {
        $invoiceNumber = $this->generateInvoiceNumber();
        
        return [
            'invoice_number' => $invoiceNumber,
            'invoice_type' => self::TYPE_COMPANY_BULK,
            'company_id' => $company['id'],
            'department_id' => null,
            'user_id' => null,
            'billing_company_name' => $company['company_name'],
            'billing_address' => $company['company_address'],
            'billing_contact_person' => $company['billing_contact_person'],
            'billing_email' => $company['billing_email'],
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'issue_date' => date('Y-m-d'),
            'due_date' => $dueDate,
            'subtotal' => $orderData['total_amount'],
            'tax_rate' => $this->getTaxRate(),
            'tax_amount' => $this->calculateTaxAmount($orderData['total_amount']),
            'total_amount' => $this->calculateTotalWithTax($orderData['total_amount']),
            'order_count' => $orderData['order_count'],
            'total_quantity' => $orderData['total_quantity'],
            'status' => self::STATUS_ISSUED,
            'notes' => "配達先企業一括請求（期間：{$periodStart}〜{$periodEnd}）",
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * 請求書レコード挿入
     * 
     * @param array $invoiceData 請求書データ
     * @return int 挿入された請求書ID
     */
    private function insertInvoiceRecord($invoiceData) {
        $columns = implode(', ', array_keys($invoiceData));
        $placeholders = ':' . implode(', :', array_keys($invoiceData));
        
        $stmt = $this->db->prepare("
            INSERT INTO invoices ({$columns}) 
            VALUES ({$placeholders})
        ");
        $stmt->execute($invoiceData);
        
        return $this->db->lastInsertId();
    }
    
    /**
     * 請求書明細挿入
     * 
     * @param int $invoiceId 請求書ID
     * @param array $orders 注文データ配列
     */
    private function insertInvoiceDetails($invoiceId, $orders) {
        $stmt = $this->db->prepare("
            INSERT INTO invoice_details (
                invoice_id, order_id, product_name, quantity, 
                unit_price, total_amount, user_name, department_name,
                delivery_date, notes, created_at, updated_at
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW()
            )
        ");
        
        foreach ($orders as $order) {
            $stmt->execute([
                $invoiceId,
                $order['id'],
                $order['product_name'],
                $order['quantity'],
                $order['unit_price'],
                $order['total_amount'],
                $order['user_name'],
                $order['department_name'],
                $order['delivery_date'],
                $order['notes']
            ]);
        }
    }
    
    /**
     * 支払期限日計算
     * 
     * @param string $periodEnd 期間終了日
     * @param int $dueDays 支払期限日数
     * @return string 支払期限日
     */
    private function calculateDueDate($periodEnd, $dueDays = 30) {
        return date('Y-m-d', strtotime($periodEnd . ' + ' . $dueDays . ' days'));
    }
    
    /**
     * 請求書番号生成
     * 
     * @return string 請求書番号
     */
    private function generateInvoiceNumber() {
        $prefix = 'SMI';
        $dateStr = date('Ymd');
        
        // 当日の連番取得
        $stmt = $this->db->prepare("
            SELECT COUNT(*) + 1 as next_number
            FROM invoices 
            WHERE DATE(created_at) = CURDATE()
        ");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $sequenceNumber = str_pad($result['next_number'], 4, '0', STR_PAD_LEFT);
        
        return "{$prefix}-{$dateStr}-{$sequenceNumber}";
    }
    
    /**
     * 消費税率取得
     * 
     * @return float 消費税率
     */
    private function getTaxRate() {
        // システム設定から取得（デフォルト10%）
        return 0.10;
    }
    
    /**
     * 消費税額計算
     * 
     * @param float $subtotal 小計
     * @return float 消費税額
     */
    private function calculateTaxAmount($subtotal) {
        return floor($subtotal * $this->getTaxRate());
    }
    
    /**
     * 税込み合計計算
     * 
     * @param float $subtotal 小計
     * @return float 税込み合計
     */
    private function calculateTotalWithTax($subtotal) {
        return $subtotal + $this->calculateTaxAmount($subtotal);
    }
    
    /**
     * 個人請求最低金額取得
     * 
     * @return float 最低金額
     */
    private function getIndividualInvoiceThreshold() {
        // システム設定から取得（デフォルト1,000円）
        return 1000;
    }
    
    /**
     * 生成パラメータ検証
     * 
     * @param array $params パラメータ
     * @throws Exception 検証エラー
     */
    private function validateGenerationParams($params) {
        if (empty($params['period_start']) || empty($params['period_end'])) {
            throw new Exception('期間の指定が必要です');
        }
        
        if (strtotime($params['period_start']) > strtotime($params['period_end'])) {
            throw new Exception('期間の開始日が終了日より後になっています');
        }
        
        $invoiceType = $params['invoice_type'] ?? '';
        $validTypes = [self::TYPE_COMPANY_BULK, self::TYPE_DEPARTMENT_BULK, self::TYPE_INDIVIDUAL, self::TYPE_MIXED];
        
        if (!in_array($invoiceType, $validTypes)) {
            throw new Exception('無効な請求書タイプです');
        }
    }
    
    /**
     * 請求書
