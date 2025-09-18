<?php if (!empty($outstanding['contact_phone'])): ?>
                                        <tr>
                                            <td class="ps-0 small">電話:</td>
                                            <td class="small">
                                                <a href="tel:<?php echo htmlspecialchars($outstanding['contact_phone']); ?>">
                                                    <?php echo htmlspecialchars($outstanding['contact_phone']); ?>
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endif; ?>
                                    </table>
                                </div>
                            </div>
                            
                            <!-- 操作履歴 -->
                            <?php if (isset($outstanding['payment_history']) && !empty($outstanding['payment_history'])): ?>
                            <div class="mt-3">
                                <h6 class="small fw-bold text-muted mb-2">支払い履歴</h6>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th class="small">日付</th>
                                                <th class="small">金額</th>
                                                <th class="small">方法</th>
                                                <th class="small">備考</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($outstanding['payment_history'] as $payment): ?>
                                            <tr>
                                                <td class="small"><?php echo formatDate($payment['payment_date']); ?></td>
                                                <td class="small"><?php echo formatCurrency($payment['amount']); ?></td>
                                                <td class="small"><?php echo getPaymentMethodText($payment['payment_method']); ?></td>
                                                <td class="small"><?php echo htmlspecialchars($payment['notes'] ?? ''); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <?php endif; ?>
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
function changePriorityFilter(priority) {
    const url = new URL(window.location);
    if (priority) {
        url.searchParams.set('priority', priority);
    } else {
        url.searchParams.delete('priority');
    }
    window.location.href = url.toString();
}

function generateCollectionReport() {
    // 督促状一括生成の実装
    alert('督促状一括生成機能は準備中です');
}

function sendReminder(invoiceId) {
    if (confirm('督促状を送信しますか？')) {
        // 督促状送信の実装
        alert('督促状送信機能は準備中です');
    }
}

function scheduleReminder(invoiceId) {
    // 督促予約の実装
    alert('督促予約機能は準備中です');
}

function viewInvoiceDetail(invoiceId) {
    window.open(`invoice_detail.php?id=${invoiceId}`, '_blank', 'width=1000,height=800');
}

function recordPaymentForInvoice(invoiceId, outstandingAmount, invoiceNumber) {
    // 支払い記録のモーダルを開く処理
    document.getElementById('modal_invoice_id').value = invoiceId;
    document.getElementById('modal_amount').value = outstandingAmount;
    
    const modal = new bootstrap.Modal(document.getElementById('paymentModal'));
    modal.show();
}
</script>
