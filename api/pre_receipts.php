<?php
/**
 * 入金前領収書発行API
 * 配達現場での使用を想定
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/ReceiptManager.php';
require_once __DIR__ . '/../classes/SimpleCollectionManager.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('POSTメソッドで送信してください');
    }

    $data = json_decode(file_get_contents('php://input'), true);

    $action = $data['action'] ?? '';

    $receiptManager = new ReceiptManager();
    $collectionManager = new SimpleCollectionManager();

    switch ($action) {
        case 'issue_by_user':
            // ユーザーIDで未払い金額を計算して領収書発行
            $userId = $data['user_id'] ?? null;

            if (!$userId) {
                throw new Exception('ユーザーIDが指定されていません');
            }

            $db = Database::getInstance();
            $conn = $db->getConnection();

            // ユーザー情報と未払い金額を取得
            $sql = "
                SELECT
                    u.id,
                    u.user_name,
                    c.company_name,
                    COALESCE(SUM(o.total_amount), 0) as total_ordered,
                    COALESCE(SUM(opd.allocated_amount), 0) as total_paid,
                    (COALESCE(SUM(o.total_amount), 0) - COALESCE(SUM(opd.allocated_amount), 0)) as outstanding
                FROM users u
                LEFT JOIN companies c ON u.company_id = c.id
                LEFT JOIN orders o ON u.id = o.user_id
                LEFT JOIN order_payment_details opd ON o.id = opd.order_id
                WHERE u.id = :user_id
                GROUP BY u.id
            ";

            $stmt = $conn->prepare($sql);
            $stmt->execute([':user_id' => $userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                throw new Exception('ユーザーが見つかりません');
            }

            if ($user['outstanding'] <= 0) {
                throw new Exception('未払い金額がありません');
            }

            // 領収書発行
            $result = $receiptManager->issuePreReceipt([
                'user_id' => $user['id'],
                'user_name' => $user['user_name'],
                'company_name' => $user['company_name'],
                'amount' => $user['outstanding'],
                'description' => $_POST['description'] ?? 'お弁当代として'
            ]);

            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            break;

        case 'issue_by_company':
            // 企業名で未払い金額を計算して領収書発行
            $companyName = $data['company_name'] ?? null;

            if (!$companyName) {
                throw new Exception('企業名が指定されていません');
            }

            $db = Database::getInstance();
            $conn = $db->getConnection();

            // 企業の未払い金額を取得
            $sql = "
                SELECT
                    o.company_name,
                    COALESCE(SUM(o.total_amount), 0) as total_ordered,
                    COALESCE(SUM(opd.allocated_amount), 0) as total_paid,
                    (COALESCE(SUM(o.total_amount), 0) - COALESCE(SUM(opd.allocated_amount), 0)) as outstanding
                FROM orders o
                LEFT JOIN order_payment_details opd ON o.id = opd.order_id
                WHERE o.company_name = :company_name
                GROUP BY o.company_name
            ";

            $stmt = $conn->prepare($sql);
            $stmt->execute([':company_name' => $companyName]);
            $company = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$company) {
                throw new Exception('企業が見つかりません');
            }

            if ($company['outstanding'] <= 0) {
                throw new Exception('未払い金額がありません');
            }

            // 領収書発行
            $result = $receiptManager->issuePreReceipt([
                'user_name' => $companyName,
                'company_name' => $companyName,
                'amount' => $company['outstanding'],
                'description' => $_POST['description'] ?? 'お弁当代として'
            ]);

            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            break;

        case 'issue_custom':
            // カスタム金額で領収書発行
            $userName = $data['user_name'] ?? '';
            $companyName = $data['company_name'] ?? '';
            $amount = $data['amount'] ?? 0;
            $description = $data['description'] ?? 'お弁当代として';

            if (!$userName && !$companyName) {
                throw new Exception('宛名が指定されていません');
            }

            if ($amount <= 0) {
                throw new Exception('金額が不正です');
            }

            // 領収書発行
            $result = $receiptManager->issuePreReceipt([
                'user_name' => $userName,
                'company_name' => $companyName,
                'amount' => $amount,
                'description' => $description
            ]);

            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            break;

        default:
            throw new Exception('不明なアクションです');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
