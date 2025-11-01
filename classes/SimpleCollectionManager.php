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
     * 今月の集金統計を取得（ordersテーブルから直接）
     */
    public function getMonthlyCollectionStats() {
        try {
            // 今月の開始日・終了日
            $startDate = date('Y-m-01');
            $endDate = date('Y-m-t');

            // 今月の注文データから集計
            $sql = "
                SELECT
                    COUNT(*) as total_orders,
                    COUNT(DISTINCT o.user_id) as total_users,
                    COALESCE(SUM(o.total_amount), 0) as total_amount,
                    COALESCE(SUM(CASE WHEN p.id IS NOT NULL THEN o.total_amount ELSE 0 END), 0) as collected_amount,
                    COALESCE(SUM(CASE WHEN p.id IS NULL THEN o.total_amount ELSE 0 END), 0) as outstanding_amount,
                    COUNT(CASE WHEN p.id IS NOT NULL THEN 1 END) as paid_count,
                    COUNT(CASE WHEN p.id IS NULL THEN 1 END) as outstanding_count
                FROM orders o
                LEFT JOIN payments p ON o.id = p.order_id AND p.status = 'completed'
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
                'collected_amount' => (float)($result['collected_amount'] ?? 0),
                'outstanding_amount' => (float)($result['outstanding_amount'] ?? 0),
                'paid_count' => (int)($result['paid_count'] ?? 0),
                'outstanding_count' => (int)($result['outstanding_count'] ?? 0),
                'period' => [
                    'start' => $startDate,
                    'end' => $endDate
                ]
            ];

        } catch (Exception $e) {
            error_log("SimpleCollectionManager::getMonthlyCollectionStats Error: " . $e->getMessage());
            return [
                'success' => false,
                'total_orders' => 0,
                'total_users' => 0,
                'total_amount' => 0,
                'collected_amount' => 0,
                'outstanding_amount' => 0,
                'paid_count' => 0,
                'outstanding_count' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 未回収の注文一覧を取得
     */
    public function getOutstandingOrders($filters = []) {
        try {
            $limit = $filters['limit'] ?? 100;
            $company_id = $filters['company_id'] ?? null;
            $search = $filters['search'] ?? '';

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
                LEFT JOIN payments p ON o.id = p.order_id AND p.status = 'completed'
                WHERE p.id IS NULL
            ";

            $params = [];

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
    public function getAlerts() {
        try {
            // 期限切れ（30日以上経過）
            $overdueSql = "
                SELECT
                    COUNT(*) as count,
                    COALESCE(SUM(o.total_amount), 0) as total_amount
                FROM orders o
                LEFT JOIN payments p ON o.id = p.order_id AND p.status = 'completed'
                WHERE p.id IS NULL
                AND DATEDIFF(CURDATE(), o.order_date) > 30
            ";

            $stmt = $this->db->getConnection()->query($overdueSql);
            $overdue = $stmt->fetch(PDO::FETCH_ASSOC);

            // 期限間近（14-30日）
            $dueSoonSql = "
                SELECT
                    COUNT(*) as count,
                    COALESCE(SUM(o.total_amount), 0) as total_amount
                FROM orders o
                LEFT JOIN payments p ON o.id = p.order_id AND p.status = 'completed'
                WHERE p.id IS NULL
                AND DATEDIFF(CURDATE(), o.order_date) BETWEEN 14 AND 30
            ";

            $stmt = $this->db->getConnection()->query($dueSoonSql);
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
