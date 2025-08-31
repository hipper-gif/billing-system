<!-- 入金履歴表示 -->
<div class="card">
    <div class="card-header bg-white">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">
                <i class="fas fa-list me-2"></i>
                入金履歴一覧
            </h5>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-primary btn-sm" onclick="exportPaymentHistory()">
                    <i class="fas fa-download me-2"></i>エクスポート
                </button>
                <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#paymentModal">
                    <i class="fas fa-plus me-2"></i>新規入金記録
                </button>
            </div>
        </div>
    </div>
    <div class="card-body p-0">
        <?php if (!empty($data)): ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th width="10%">入金日</th>
                        <th width="15%">請求書</th>
                        <th width="20%">企業・利用者</th>
                        <th width="12%">入金額</th>
                        <th width="12%">支払方法</th>
                        <th width="15%">参照番号</th>
                        <th width="10%">請求書状態</th>
                        <th width="6%">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data as $payment): ?>
                    <tr class="payment-row" data-payment-id="<?php echo $payment['payment_id']; ?>">
                        <td>
                            <div class="fw-bold"><?php echo formatDate($payment['payment_date']); ?></div>
                            <small class="text-muted">
                                <?php echo date('H:i', strtotime($payment['created_at'])); ?>
                            </small>
                        </td>
                        <td>
                            <div>
                                <strong><?php echo htmlspecialchars($payment['invoice_number']); ?></strong>
                                <?php if (!empty($payment['invoice_id'])): ?>
                                <a href="#" onclick="viewInvoiceDetail(<?php echo $payment['invoice_id']; ?>)" 
                                   class="btn btn-link btn-sm p-0 ms-1" title="請求書詳細">
                                    <i class="fas fa-external-link-alt"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                            <small class="text-muted">
                                請求額: <?php echo formatCurrency($payment['invoice_amount']); ?>
                            </small>
                        </td>
                        <td>
                            <div>
                                <strong><?php echo htmlspecialchars($payment['company_name']); ?></strong>
                                <?php if (!empty($payment['department_name'])): ?>
                                <br><small class="text-muted"><?php echo htmlspecialchars($payment['department_name']); ?></small>
                                <?php endif; ?>
                            </div>
                            <div class="small text-primary">
                                <?php echo htmlspecialchars($payment['user_name']); ?>
                            </div>
                        </td>
                        <td>
                            <div class="fw-bold text-success fs-6">
                                <?php echo formatCurrency($payment['amount']); ?>
                            </div>
                            <?php if ($payment['amount'] != $payment['invoice_amount']): ?>
                            <small class="text-warning">
                                <i class="fas fa-exclamation-triangle me-1"></i>
                                差額: <?php echo formatCurrency($payment['invoice_amount'] - $payment['amount']); ?>
                            </small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-secondary fs-7">
                                <?php echo getPaymentMethodText($payment['payment_method']); ?>
                            </span>
                            <?php if (!empty($payment['payment_date']) && $payment['payment_date'] != date('Y-m-d')): ?>
                            <br><small class="text-muted">
                                <?php 
                                $daysDiff = (time() - strtotime($payment['payment_date'])) / (60*60*24);
                                if ($daysDiff < 7) {
                                    echo floor($daysDiff) . '日前';
                                } else {
                                    echo date('m/d', strtotime($payment['payment_date']));
                                }
                                ?>
                            </small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="small">
                                <?php if (!empty($payment['reference_number'])): ?>
                                <div class="text-truncate" style="max-width: 120px;" title="<?php echo htmlspecialchars($payment['reference_number']); ?>">
                                    <?php echo htmlspecialchars($payment['reference_number']); ?>
                                </div>
                                <?php else: ?>
                                <span class="text-muted">-</span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($payment['notes'])): ?>
                            <div class="small text-info" title="<?php echo htmlspecialchars($payment['notes']); ?>">
                                <i class="fas fa-sticky-note"></i> 備考あり
                            </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $statusClass = [
                                'paid' => 'success',
                                'partial' => 'warning', 
                                'issued' => 'primary',
                                'overdue' => 'danger',
                                'cancelled' => 'secondary'
                            ][$payment['invoice_status']] ?? 'secondary';
                            
                            $statusText = [
                                'paid' => '完了',
                                'partial' => '一部',
                                'issued' => '発行済',
                                'overdue' => '期限切れ',
                                'cancelled' => 'キャンセル'
                            ][$payment['invoice_status']] ?? $payment['invoice_status'];
                            ?>
                            <span class="badge bg-<?php echo $statusClass; ?> fs-7">
                                <?php echo $statusText; ?>
                            </span>
                            <?php if (isset($payment['days_until_due']) && $payment['days_until_due'] < 0): ?>
                            <br><small class="text-danger">
                                <?php echo abs($payment['days_until_due']); ?>日超過
                            </small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-primary" 
                                        onclick="viewPaymentDetail(<?php echo $payment['payment_id']; ?>)"
                                        title="詳細表示">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <?php if ($payment['invoice_status'] != 'cancelled'): ?>
                                <button class="btn btn-outline-warning" 
                                        onclick="editPayment(<?php echo $payment['payment_id']; ?>)"
                                        title="編集">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-outline-danger" 
                                        onclick="cancelPayment(<?php echo $payment['payment_id']; ?>)"
                                        title="キャンセル">
                                    <i class="fas fa-times"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- ページネーション -->
        <?php if (isset($totalPages) && $totalPages > 1): ?>
        <div class="card-footer bg-light">
            <nav aria-label="支払履歴ページネーション">
                <ul class="pagination pagination-sm justify-content-center mb-0">
                    <?php
                    $currentPage = $filters['page'];
                    $startPage = max(1, $currentPage - 2);
                    $endPage = min($totalPages, $currentPage + 2);
                    ?>
                    
                    <?php if ($currentPage > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($filters, ['page' => 1])); ?>">
                            <i class="fas fa-angle-double-left"></i>
                        </a>
                    </li>
                    <li class="page-item">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($filters, ['page' => $currentPage - 1])); ?>">
                            <i class="fas fa-angle-left"></i>
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                    <li class="page-item <?php echo $i == $currentPage ? 'active' : ''; ?>">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($filters, ['page' => $i])); ?>">
                            <?php echo $i; ?>
                        </a>
                    </li>
                    <?php endfor; ?>
                    
                    <?php if ($currentPage < $totalPages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($filters, ['page' => $currentPage + 1])); ?>">
                            <i class="fas fa-angle-right"></i>
                        </a>
                    </li>
                    <li class="page-item">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($filters, ['page' => $totalPages])); ?>">
                            <i class="fas fa-angle-double-right"></i>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
                
                <div class="text-center text-muted small mt-2">
                    ページ <?php echo $currentPage; ?> / <?php echo $totalPages; ?> 
                    (全 <?php echo $totalRecords ?? count($data); ?> 件)
                </div>
            </nav>
        </div>
        <?php endif; ?>
        
        <?php else: ?>
        <div class="text-center py-5">
            <div class="mb-4">
                <i class="fas fa-inbox fa-4x text-muted"></i>
            </div>
            <h5 class="text-muted mb-3">入金履歴が見つかりません</h5>
            <p class="text-muted mb-4">
                現在の検索条件では入金記録がありません。<br>
                検索条件を変更するか、新しい入金を記録してください。
            </p>
            <div class="d-flex justify-content-center gap-2">
                <button class="btn btn-outline-primary" onclick="location.href='?view_type=payments'">
                    <i class="fas fa-refresh me-2"></i>すべての履歴を表示
                </button>
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#paymentModal">
                    <i class="fas fa-plus me-2"></i>入金を記録する
                </button>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- 入金履歴用のスタイル -->
<style>
.payment-row {
    cursor: pointer;
    transition: all 0.2s ease;
}

.payment-row:hover {
    background-color: rgba(0, 123, 255, 0.05);
    transform: translateX(2px);
}

.fs-7 {
    font-size: 0.875rem;
}

.btn-group-sm .btn {
    padding: 0.25rem 0.4rem;
    font-size: 0.75rem;
}

.table td {
    vertical-align: middle;
    border-color: rgba(0,0,0,.08);
}

.table thead th {
    border-bottom: 2px solid #dee2e6;
    font-weight: 600;
    font-size: 0.9rem;
    color: #495057;
}

.text-truncate {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

@media (max-width: 768px) {
    .table-responsive table {
        font-size: 0.85rem;
    }
    
    .btn-group-sm .btn {
        padding: 0.2rem 0.3rem;
        font-size: 0.7rem;
    }
    
    .table td, .table th {
        padding: 0.5rem 0.25rem;
    }
}
</style>

<script>
// 入金履歴関連のJavaScript関数
function editPayment(paymentId) {
    // 支払い編集モーダルを開く
    fetch(`../api/payments.php?action=detail&id=${paymentId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                populateEditPaymentModal(data.payment);
                const modal = new bootstrap.Modal(document.getElementById('editPaymentModal'));
                modal.show();
            } else {
                showAlert('error', '支払い情報の取得に失敗しました');
            }
        })
        .catch(error => {
            console.error('支払い詳細取得エラー:', error);
            showAlert('error', '支払い情報の取得中にエラーが発生しました');
        });
}

function populateEditPaymentModal(payment) {
    // 編集モーダルに既存データを設定
    document.getElementById('edit_payment_id').value = payment.id;
    document.getElementById('edit_amount').value = payment.amount;
    document.getElementById('edit_payment_method').value = payment.payment_method;
    document.getElementById('edit_payment_date').value = payment.payment_date;
    document.getElementById('edit_reference_number').value = payment.reference_number || '';
    document.getElementById('edit_notes').value = payment.notes || '';
}

function exportPaymentHistory() {
    // 現在のフィルター条件でCSVエクスポート
    const currentUrl = new URL(window.location);
    const params = new URLSearchParams(currentUrl.search);
    params.set('action', 'export');
    params.set('format', 'csv');
    
    const exportUrl = `../api/payments.php?${params.toString()}`;
    window.open(exportUrl, '_blank');
}

// 行クリックで詳細表示
document.addEventListener('DOMContentLoaded', function() {
    const paymentRows = document.querySelectorAll('.payment-row');
    paymentRows.forEach(row => {
        row.addEventListener('dblclick', function() {
            const paymentId = this.dataset.paymentId;
            if (paymentId) {
                viewPaymentDetail(paymentId);
            }
        });
    });
});
</script>
