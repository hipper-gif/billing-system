<!-- 入金記録モーダル -->
<div class="modal fade" id="paymentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-plus me-2"></i>入金記録
                </h5>
                <button type="button" class="btn btn-outline-primary me-3" onclick="openInvoiceSearch()">
                    <i class="fas fa-search me-2"></i>請求書を検索
                </button>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="paymentForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="record_payment">
                    <input type="hidden" name="invoice_id" id="modal_invoice_id">
                    
                    <div id="selected_invoice_info" class="alert alert-info d-none">
                        <h6><i class="fas fa-file-invoice me-2"></i>選択された請求書</h6>
                        <div id="invoice_details"></div>
                    </div>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="payment_date" class="form-label">入金日 <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="payment_date" id="payment_date" 
                                   value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="amount" class="form-label">入金額 <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" class="form-control" name="amount" id="modal_amount" 
                                       step="0.01" min="0" required>
                                <span class="input-group-text">円</span>
                            </div>
                            <div class="form-text">
                                <small id="amount_suggestion" class="text-muted"></small>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="payment_method" class="form-label">支払方法 <span class="text-danger">*</span></label>
                            <select class="form-select" name="payment_method" id="payment_method" required>
                                <option value="">選択してください</option>
                                <option value="cash">現金</option>
                                <option value="bank_transfer">銀行振込</option>
                                <option value="account_debit">口座引落</option>
                                <option value="credit_card">クレジットカード</option>
                                <option value="electronic_money">電子マネー</option>
                                <option value="other">その他</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="reference_number" class="form-label">参照番号・振込名義</label>
                            <input type="text" class="form-control" name="reference_number" id="reference_number"
                                   placeholder="振込名義、取引番号など">
                        </div>
                        
                        <div class="col-12">
                            <label for="notes" class="form-label">備考</label>
                            <textarea class="form-control" name="notes" id="payment_notes" rows="3" 
                                      placeholder="入金に関する補足情報"></textarea>
                        </div>
                        
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="auto_generate_receipt" 
                                       id="auto_generate_receipt" checked>
                                <label class="form-check-label" for="auto_generate_receipt">
                                    <i class="fas fa-receipt me-1"></i>領収書を自動生成する
                                </label>
                            </div>
                        </div>
                        
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="send_notification" 
                                       id="send_notification">
                                <label class="form-check-label" for="send_notification">
                                    <i class="fas fa-envelope me-1"></i>入金確認メールを送信する
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>キャンセル
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save me-2"></i>入金を記録
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 支払いキャンセルモーダル -->
<div class="modal fade" id="cancelPaymentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>支払いキャンセル
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="cancelPaymentForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="cancel_payment">
                    <input type="hidden" name="payment_id" id="cancel_payment_id">
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>注意:</strong> 支払いをキャンセルすると、以下の影響があります：
                        <ul class="mb-0 mt-2">
                            <li>関連する領収書が無効になります</li>
                            <li>請求書のステータスが未払いに戻ります</li>
                            <li>この操作は取り消せません</li>
                        </ul>
                    </div>
                    
                    <div class="mb-3">
                        <label for="cancel_reason" class="form-label">キャンセル理由 <span class="text-danger">*</span></label>
                        <select class="form-select mb-2" id="cancel_reason_select">
                            <option value="">理由を選択...</option>
                            <option value="入金確認エラー">入金確認エラー</option>
                            <option value="重複入力">重複入力</option>
                            <option value="金額間違い">金額間違い</option>
                            <option value="返金処理">返金処理</option>
                            <option value="その他">その他</option>
                        </select>
                        <textarea class="form-control" name="reason" id="cancel_reason" rows="3" 
                                  placeholder="キャンセルの詳細理由を入力してください" required></textarea>
                    </div>
                    
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="confirm_cancel" required>
                        <label class="form-check-label" for="confirm_cancel">
                            上記の内容を理解し、支払いキャンセルを実行することを確認します
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>戻る
                    </button>
                    <button type="submit" class="btn btn-danger" disabled id="cancel_submit_btn">
                        <i class="fas fa-trash me-2"></i>キャンセル実行
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 請求書検索モーダル -->
<div class="modal fade" id="invoiceSearchModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-search me-2"></i>請求書検索
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <input type="text" class="form-control" id="invoice_search" 
                               placeholder="請求書番号・企業名・利用者名で検索">
                    </div>
                    <div class="col-md-2">
                        <button type="button" class="btn btn-primary w-100" onclick="searchInvoices()">
                            <i class="fas fa-search me-2"></i>検索
                        </button>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" id="search_status">
                            <option value="">全ステータス</option>
                            <option value="unpaid">未払のみ</option>
                            <option value="partial">一部支払のみ</option>
                            <option value="overdue">期限切れのみ</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <input type="number" class="form-control" id="search_min_amount" 
                               placeholder="最小金額" min="0">
                    </div>
                    <div class="col-md-2">
                        <button type="button" class="btn btn-outline-secondary w-100" onclick="clearSearchFilters()">
                            <i class="fas fa-times me-1"></i>クリア
                        </button>
                    </div>
                </div>
                
                <div id="invoice_search_results">
                    <div class="text-center py-4">
                        <i class="fas fa-search fa-3x text-muted mb-3"></i>
                        <p class="text-muted">検索条件を入力して請求書を検索してください</p>
                        <p class="small text-muted">
                            <i class="fas fa-lightbulb me-1"></i>
                            ヒント: 請求書番号、企業名、利用者名の一部でも検索できます
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 入金詳細表示モーダル -->
<div class="modal fade" id="paymentDetailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-receipt me-2"></i>入金詳細
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="payment_detail_content">
                <!-- 動的に挿入 -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>閉じる
                </button>
                <button type="button" class="btn btn-primary" onclick="printPaymentDetail()">
                    <i class="fas fa-print me-2"></i>印刷
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// キャンセル理由選択時の自動入力
document.getElementById('cancel_reason_select').addEventListener('change', function() {
    const reasonText = document.getElementById('cancel_reason');
    if (this.value && this.value !== 'その他') {
        reasonText.value = this.value + ': ';
        reasonText.focus();
        reasonText.setSelectionRange(reasonText.value.length, reasonText.value.length);
    }
});

// キャンセル確認チェックボックス
document.getElementById('confirm_cancel').addEventListener('change', function() {
    document.getElementById('cancel_submit_btn').disabled = !this.checked;
});

// 検索フィルタークリア
function clearSearchFilters() {
    document.getElementById('invoice_search').value = '';
    document.getElementById('search_status').value = '';
    document.getElementById('search_min_amount').value = '';
    document.getElementById('invoice_search_results').innerHTML = `
        <div class="text-center py-4">
            <i class="fas fa-search fa-3x text-muted mb-3"></i>
            <p class="text-muted">検索条件を入力して請求書を検索してください</p>
        </div>`;
}

// Enterキーで検索実行
document.getElementById('invoice_search').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        searchInvoices();
    }
});
</script>
