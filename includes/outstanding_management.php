<?php
// 最小限の未回収管理表示
if (!isset($data)) {
    $data = array();
}
?>

<!-- 未回収金額管理表示 -->
<div class="card">
    <div class="card-header bg-white">
        <h5 class="card-title mb-0">
            <i class="fas fa-exclamation-triangle me-2 text-warning"></i>
            未回収金額管理
        </h5>
    </div>
    <div class="card-body">
        <?php if (!empty($data)): ?>
            <div class="outstanding-list">
                <?php foreach ($data as $outstanding): ?>
                <div class="outstanding-item <?php echo isset($outstanding['priority']) ? $outstanding['priority'] : 'normal'; ?> mb-3">
                    <div class="row align-items-center">
                        <!-- 企業・請求書情報 -->
                        <div class="col-lg-4 col-md-12 mb-2 mb-lg-0">
                            <h6 class="mb-1 fw-bold">
                                <i class="fas fa-building me-2"></i>
                                <?php echo htmlspecialchars($outstanding['company_name'] ?? '不明'); ?>
                            </h6>
                            <div class="text-primary small">
                                <i class="fas fa-user me-1"></i>
                                <?php echo htmlspecialchars($outstanding['user_name'] ?? '不明'); ?>
                            </div>
                            <div class="small text-muted mt-1">
                                <strong>請求書:</strong> <?php echo htmlspecialchars($outstanding['invoice_number'] ?? '不明'); ?>
                            </div>
                        </div>
                        
                        <!-- 金額情報 -->
                        <div class="col-lg-3 col-md-6 text-center mb-2 mb-lg-0">
                            <div class="fw-bold text-danger fs-4">
                                <?php echo formatCurrency($outstanding['outstanding_amount'] ?? 0); ?>
                            </div>
                            <small class="text-muted">
                                請求額: <?php echo formatCurrency($outstanding['total_amount'] ?? 0); ?>
                            </small>
                        </div>
                        
                        <!-- 期限情報 -->
                        <div class="col-lg-2 col-md-6 text-center mb-2 mb-lg-0">
                            <div class="fw-bold">
                                <?php echo isset($outstanding['due_date']) ? formatDate($outstanding['due_date']) : '不明'; ?>
                            </div>
                            <small class="text-muted">
                                <?php 
                                $days = $outstanding['days_until_due'] ?? 0;
                                if ($days < 0) {
                                    echo '<span class="text-danger">' . abs($days) . '日超過</span>';
                                } elseif ($days == 0) {
                                    echo '<span class="text-warning">本日期限</span>';
                                } else {
                                    echo 'あと' . $days . '日';
                                }
                                ?>
                            </small>
                        </div>
                        
                        <!-- 優先度 -->
                        <div class="col-lg-1 col-md-12 text-center mb-2 mb-lg-0">
                            <span class="<?php echo getPriorityBadge($outstanding['priority'] ?? 'normal'); ?>">
                                <?php echo getPriorityText($outstanding['priority'] ?? 'normal'); ?>
                            </span>
                        </div>
                        
                        <!-- アクションボタン -->
                        <div class="col-lg-2 col-md-12">
                            <div class="d-grid gap-1">
                                <button class="btn btn-success btn-sm" 
                                        onclick="recordPaymentForInvoice(<?php echo $outstanding['invoice_id'] ?? 0; ?>, '<?php echo $outstanding['outstanding_amount'] ?? 0; ?>', '<?php echo htmlspecialchars($outstanding['invoice_number'] ?? ''); ?>')">
                                    <i class="fas fa-plus me-1"></i>入金記録
                                </button>
                                <button class="btn btn-outline-primary btn-sm" 
                                        onclick="viewInvoiceDetail(<?php echo $outstanding['invoice_id'] ?? 0; ?>)">
                                    <i class="fas fa-eye me-1"></i>詳細
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                <h5 class="text-success">すべての請求書が回収済みです</h5>
                <p class="text-muted">未回収の請求書はありません。</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function viewInvoiceDetail(invoiceId) {
    if (invoiceId > 0) {
        window.open(`invoice_detail.php?id=${invoiceId}`, '_blank', 'width=1000,height=800');
    }
}

function recordPaymentForInvoice(invoiceId, outstandingAmount, invoiceNumber) {
    if (invoiceId > 0) {
        if (typeof document.getElementById('modal_invoice_id') !== 'undefined') {
            document.getElementById('modal_invoice_id').value = invoiceId;
        }
        if (typeof document.getElementById('modal_amount') !== 'undefined') {
            document.getElementById('modal_amount').value = outstandingAmount;
        }
        
        if (typeof bootstrap !== 'undefined' && typeof document.getElementById('paymentModal') !== 'undefined') {
            const modal = new bootstrap.Modal(document.getElementById('paymentModal'));
            modal.show();
        } else {
            alert('入金記録機能は準備中です');
        }
    }
}
</script>
