<?php
/**
 * SimpleCollectionManager - シンプル集金管理
 * ordersテーブルから直接集金データを取得
 */

class SimpleCollectionManager {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * 指定期間の集金統計を取得（ordersテーブルから直接）
     * @param string $startDate 開始日 (YYYY-MM-DD)
     * @param string $endDate 終了日 (YYYY-MM-DD)
     */
    public function getCollectionStats($startDate, $endDate) {
        try {
            // 期間内の注文データから集計
            // 注意: paymentsテーブルはinvoice_idしか持たないため、
            // ordersとの直接のJOINはできません
            // そのため、すべてのordersを「未回収」として扱います
            $sql = "
                SELECT
                    COUNT(*) as total_orders,
                    COUNT(DISTINCT o.user_id) as total_users,
                    COALESCE(SUM(o.total_amount), 0) as total_amount
                FROM orders o
                WHERE o.order_date BETWEEN :start_date AND :end_date
            ";

            $stmt = $this->db->getConnection()->prepare($sql);
            $stmt->execute([
                ':start_date' => $startDate,
                ':end_date' => $endDate
            ]);

            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'total_orders' => (int)($result['total_orders'] ?? 0),
                'total_users' => (int)($result['total_users'] ?? 0),
                'total_amount' => (float)($result['total_amount'] ?? 0),
                'collected_amount' => 0, // 現在は未実装（請求書・支払いシステムと連携が必要）
                'outstanding_amount' => (float)($result['total_amount'] ?? 0),
                'paid_count' => 0,
                'outstanding_count' => (int)($result['total_orders'] ?? 0),
                'period' => [
                    'start' => $startDate,
                    'end' => $endDate
                ]
            ];

        } catch (Exception $e) {
            error_log("SimpleCollectionManager::getCollectionStats Error: " . $e->getMessage());
            return [
                'success' => false,
                'total_orders' => 0,
                'total_users' => 0,
                'total_amount' => 0,
                'collected_amount' => 0,
                'outstanding_amount' => 0,
                'paid_count' => 0,
                'outstanding_count' => 0,
                'period' => [
                    'start' => $startDate,
                    'end' => $endDate
                ],
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 今月の集金統計を取得（デフォルト）
     */
    public function getMonthlyCollectionStats() {
        // 今月のデータを取得（デフォルト）
        $startDate = date('Y-m-01');
        $endDate = date('Y-m-t');

        return $this->getCollectionStats($startDate, $endDate);
    }

    /**
     * 未回収の注文一覧を取得
     */
    public function getOutstandingOrders($filters = []) {
        try {
            $limit = $filters['limit'] ?? 100;
            $company_id = $filters['company_id'] ?? null;
            $search = $filters['search'] ?? '';
            $startDate = $filters['start_date'] ?? date('Y-m-01');
            $endDate = $filters['end_date'] ?? date('Y-m-t');

            $sql = "
                SELECT
                    o.id,
                    o.order_date,
                    o.user_id,
                    o.user_name,
                    o.total_amount,
                    u.company_id,
                    c.company_name,
                    DATEDIFF(CURDATE(), o.order_date) as days_since_order
                FROM orders o
                LEFT JOIN users u ON o.user_id = u.id
                LEFT JOIN companies c ON u.company_id = c.id
                WHERE o.order_date BETWEEN :start_date AND :end_date
            ";

            $params = [
                ':start_date' => $startDate,
                ':end_date' => $endDate
            ];

            if ($company_id) {
                $sql .= " AND u.company_id = :company_id";
                $params[':company_id'] = $company_id;
            }

            if ($search) {
                $sql .= " AND (c.company_name LIKE :search OR o.user_name LIKE :search)";
                $params[':search'] = '%' . $search . '%';
            }

            $sql .= " ORDER BY o.order_date DESC LIMIT :limit";
            $params[':limit'] = $limit;

            $stmt = $this->db->getConnection()->prepare($sql);

            foreach ($params as $key => $value) {
                if ($key === ':limit' || $key === ':company_id') {
                    $stmt->bindValue($key, $value, PDO::PARAM_INT);
                } else {
                    $stmt->bindValue($key, $value, PDO::PARAM_STR);
                }
            }

            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 優先度を追加
            foreach ($results as &$row) {
                $days = (int)$row['days_since_order'];
                if ($days > 30) {
                    $row['priority'] = 'overdue';
                } elseif ($days > 14) {
                    $row['priority'] = 'urgent';
                } else {
                    $row['priority'] = 'normal';
                }
                $row['amount'] = $row['total_amount'];
                $row['invoice_number'] = 'ORD-' . str_pad($row['id'], 6, '0', STR_PAD_LEFT);
                $row['invoice_date'] = $row['order_date'];
                $row['due_date'] = date('Y-m-d', strtotime($row['order_date'] . ' +30 days'));
            }

            return $results;

        } catch (Exception $e) {
            error_log("SimpleCollectionManager::getOutstandingOrders Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * アラート情報を取得
     */
    public function getAlerts($startDate = null, $endDate = null) {
        try {
            // 期間指定がない場合は今月
            if (!$startDate || !$endDate) {
                $startDate = date('Y-m-01');
                $endDate = date('Y-m-t');
            }

            // 期限切れ（30日以上経過）
            $overdueSql = "
                SELECT
                    COUNT(*) as count,
                    COALESCE(SUM(o.total_amount), 0) as total_amount
                FROM orders o
                WHERE o.order_date BETWEEN :start_date AND :end_date
                AND DATEDIFF(CURDATE(), o.order_date) > 30
            ";

            $stmt = $this->db->getConnection()->prepare($overdueSql);
            $stmt->execute([':start_date' => $startDate, ':end_date' => $endDate]);
            $overdue = $stmt->fetch(PDO::FETCH_ASSOC);

            // 期限間近（14-30日）
            $dueSoonSql = "
                SELECT
                    COUNT(*) as count,
                    COALESCE(SUM(o.total_amount), 0) as total_amount
                FROM orders o
                WHERE o.order_date BETWEEN :start_date AND :end_date
                AND DATEDIFF(CURDATE(), o.order_date) BETWEEN 14 AND 30
            ";

            $stmt = $this->db->getConnection()->prepare($dueSoonSql);
            $stmt->execute([':start_date' => $startDate, ':end_date' => $endDate]);
            $dueSoon = $stmt->fetch(PDO::FETCH_ASSOC);

            return [
                'alert_count' => (int)($overdue['count'] ?? 0) + (int)($dueSoon['count'] ?? 0),
                'overdue' => [
                    'count' => (int)($overdue['count'] ?? 0),
                    'total_amount' => (float)($overdue['total_amount'] ?? 0)
                ],
                'due_soon' => [
                    'count' => (int)($dueSoon['count'] ?? 0),
                    'total_amount' => (float)($dueSoon['total_amount'] ?? 0)
                ]
            ];

        } catch (Exception $e) {
            error_log("SimpleCollectionManager::getAlerts Error: " . $e->getMessage());
            return [
                'alert_count' => 0,
                'overdue' => ['count' => 0, 'total_amount' => 0],
                'due_soon' => ['count' => 0, 'total_amount' => 0]
            ];
        }
    }

    /**
     * 月別推移データを取得
     */
    public function getMonthlyTrend($months = 6) {
        try {
            $sql = "
                SELECT
                    DATE_FORMAT(o.order_date, '%Y-%m') as month,
                    COALESCE(SUM(o.total_amount), 0) as monthly_amount,
                    COUNT(*) as order_count
                FROM orders o
                WHERE o.order_date >= DATE_SUB(CURDATE(), INTERVAL :months MONTH)
                GROUP BY DATE_FORMAT(o.order_date, '%Y-%m')
                ORDER BY month ASC
            ";

            $stmt = $this->db->getConnection()->prepare($sql);
            $stmt->execute([':months' => $months]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $results ?: [];

        } catch (Exception $e) {
            error_log("SimpleCollectionManager::getMonthlyTrend Error: " . $e->getMessage());
            return [];
        }
    }
}
