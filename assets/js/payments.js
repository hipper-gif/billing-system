/**
 * 支払い管理センター JavaScript
 * 根本解決版 - Parse error対策・保守性向上
 */

// グローバル変数
let currentInvoiceData = null;
let searchTimeout = null;

// ドキュメント読み込み完了時の初期化
document.addEventListener('DOMContentLoaded', function() {
    initializePaymentManagement();
    setupEventListeners();
    setupKeyboardShortcuts();
    startAutoRefresh();
});

/**
 * 支払い管理画面の初期化
 */
function initializePaymentManagement() {
    // カードアニメーション
    animateCards();
    
    // フォームバリデーション設定
    setupFormValidation();
    
    // 金額フィールドの自動フォーマット
    setupAmountFormatting();
    
    // 今日の日付をデフォルト設定
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('payment_date').value = today;
}

/**
 * イベントリスナーの設定
 */
function setupEventListeners() {
    // フォーム送信前の確認
    setupFormSubmissionHandlers();
    
    // モーダル関連イベント
    setupModalEvents();
    
    // 検索関連イベント
    setupSearchEvents();
    
    // 金額入力検証
    setupAmountValidation();
}

/**
 * 入金記録（未回収一覧から）
 */
function recordPaymentForInvoice(invoiceId, outstandingAmount, invoiceNumber = '') {
    // モーダルに請求書情報を設定
    document.getElementById('modal_invoice_id').value = invoiceId;
    document.getElementById('modal_amount').value = outstandingAmount;
    
    // 請求書情報を表示
    if (invoiceNumber) {
        const infoDiv = document.getElementById('selected_invoice_info');
        const detailsDiv = document.getElementById('invoice_details');
        
        detailsDiv.innerHTML = `
            <div><strong>請求書番号:</strong> ${invoiceNumber}</div>
            <div><strong>未払金額:</strong> ${formatCurrency(outstandingAmount)}</div>
        `;
        
        infoDiv.classList.remove('d-none');
    }
    
    // 金額提案テキストを更新
    const suggestionEl = document.getElementById('amount_suggestion');
    if (suggestionEl) {
        suggestionEl.textContent = `推奨入金額: ${formatCurrency(outstandingAmount)}`;
    }
    
    // モーダルを表示
    const modal = new bootstrap.Modal(document.getElementById('paymentModal'));
    modal.show();
    
    // 金額フィールドにフォーカス
    setTimeout(() => {
        document.getElementById('modal_amount').select();
    }, 500);
}

/**
 * 支払いキャンセル
 */
function cancelPayment(paymentId) {
    if (!confirm('この支払い記録をキャンセルしますか？\n関連する領収書も無効になります。')) {
        return;
    }
    
    document.getElementById('cancel_payment_id').value = paymentId;
    const modal = new bootstrap.Modal(document.getElementById('cancelPaymentModal'));
    modal.show();
    
    // フォーカス設定
    setTimeout(() => {
        document.getElementById('cancel_reason_select').focus();
    }, 500);
}

/**
 * 支払い詳細表示
 */
function viewPaymentDetail(paymentId) {
    // AJAX で支払い詳細を取得
    showLoadingSpinner();
    
    fetch(`../api/payments.php?action=detail&id=${paymentId}`)
        .then(response => response.json())
        .then(data => {
            hideLoadingSpinner();
            
            if (data.success) {
                displayPaymentDetail(data.payment);
            } else {
                showAlert('error', '支払い詳細の取得に失敗しました: ' + data.message);
            }
        })
        .catch(error => {
            hideLoadingSpinner();
            console.error('支払い詳細取得エラー:', error);
            showAlert('error', '支払い詳細の取得中にエラーが発生しました');
        });
    }
}

/**
 * 入金フォームのバリデーション
 */
function validatePaymentForm() {
    const form = document.getElementById('paymentForm');
    const amount = parseFloat(form.querySelector('input[name="amount"]').value);
    const paymentMethod = form.querySelector('select[name="payment_method"]').value;
    const invoiceId = form.querySelector('input[name="invoice_id"]').value;
    
    // 請求書選択チェック
    if (!invoiceId) {
        showAlert('warning', '請求書を選択してください');
        document.querySelector('button[onclick="openInvoiceSearch()"]').focus();
        return false;
    }
    
    // 金額チェック
    if (!amount || amount <= 0) {
        showAlert('error', '正しい入金額を入力してください');
        form.querySelector('input[name="amount"]').focus();
        return false;
    }
    
    // 高額チェック
    if (amount > 10000000) {
        if (!confirm('高額な金額が入力されています。正しい金額ですか？')) {
            form.querySelector('input[name="amount"]').focus();
            return false;
        }
    }
    
    // 支払方法チェック
    if (!paymentMethod) {
        showAlert('warning', '支払方法を選択してください');
        form.querySelector('select[name="payment_method"]').focus();
        return false;
    }
    
    return true;
}

/**
 * 金額フォーマット設定
 */
function setupAmountFormatting() {
    const amountFields = document.querySelectorAll('input[type="number"][name="amount"]');
    
    amountFields.forEach(field => {
        // 入力時のリアルタイムバリデーション
        field.addEventListener('input', function() {
            const value = parseFloat(this.value);
            
            if (value && value > 0) {
                // 桁区切りカンマ表示用のヒント
                const formatted = formatCurrency(value);
                const hint = this.parentNode.querySelector('.amount-hint');
                if (hint) {
                    hint.textContent = formatted;
                }
                
                // 高額警告
                if (value > 10000000) {
                    this.classList.add('border-warning');
                    showTooltip(this, '高額な金額です。確認してください。');
                } else {
                    this.classList.remove('border-warning');
                }
            }
        });
        
        // フォーカス時に全選択
        field.addEventListener('focus', function() {
            this.select();
        });
    });
}

/**
 * モーダルイベント設定
 */
function setupModalEvents() {
    // モーダル表示時のフォーカス設定
    document.getElementById('paymentModal').addEventListener('shown.bs.modal', function() {
        const amountField = document.getElementById('modal_amount');
        if (amountField && amountField.value) {
            amountField.select();
        } else {
            document.getElementById('invoice_search').focus();
        }
    });
    
    // モーダル非表示時のリセット
    document.getElementById('paymentModal').addEventListener('hidden.bs.modal', function() {
        resetPaymentModal();
    });
    
    document.getElementById('cancelPaymentModal').addEventListener('hidden.bs.modal', function() {
        resetCancelModal();
    });
    
    document.getElementById('invoiceSearchModal').addEventListener('hidden.bs.modal', function() {
        resetSearchModal();
    });
}

/**
 * モーダルリセット関数群
 */
function resetPaymentModal() {
    const form = document.getElementById('paymentForm');
    if (form) {
        form.reset();
        form.querySelector('button[type="submit"]').disabled = false;
        form.querySelector('button[type="submit"]').innerHTML = '<i class="fas fa-save me-2"></i>入金を記録';
    }
    
    document.getElementById('modal_invoice_id').value = '';
    document.getElementById('selected_invoice_info').classList.add('d-none');
    document.getElementById('amount_suggestion').textContent = '';
    
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('payment_date').value = today;
}

function resetCancelModal() {
    const form = document.getElementById('cancelPaymentForm');
    if (form) {
        form.reset();
        form.querySelector('button[type="submit"]').disabled = true;
        form.querySelector('button[type="submit"]').innerHTML = '<i class="fas fa-trash me-2"></i>キャンセル実行';
    }
    
    document.getElementById('cancel_payment_id').value = '';
    document.getElementById('confirm_cancel').checked = false;
}

function resetSearchModal() {
    document.getElementById('invoice_search').value = '';
    document.getElementById('search_status').value = '';
    document.getElementById('search_min_amount').value = '';
    document.getElementById('invoice_search_results').innerHTML = `
        <div class="text-center py-4">
            <i class="fas fa-search fa-3x text-muted mb-3"></i>
            <p class="text-muted">検索条件を入力して請求書を検索してください</p>
        </div>`;
}

/**
 * 検索イベント設定
 */
function setupSearchEvents() {
    // 検索フィールドでのEnterキー
    const searchField = document.getElementById('invoice_search');
    if (searchField) {
        searchField.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                searchInvoices();
            }
        });
        
        // リアルタイム検索（デバウンス付き）
        searchField.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                if (this.value.length >= 3) {
                    searchInvoices();
                }
            }, 500);
        });
    }
}

/**
 * キーボードショートカット設定
 */
function setupKeyboardShortcuts() {
    document.addEventListener('keydown', function(e) {
        // Ctrl + N で新規入金記録
        if (e.ctrlKey && e.key === 'n') {
            e.preventDefault();
            const modal = new bootstrap.Modal(document.getElementById('paymentModal'));
            modal.show();
        }
        
        // Ctrl + F で請求書検索
        if (e.ctrlKey && e.key === 'f') {
            e.preventDefault();
            openInvoiceSearch();
        }
        
        // Escでモーダルを閉じる
        if (e.key === 'Escape') {
            const openModals = document.querySelectorAll('.modal.show');
            openModals.forEach(modal => {
                const modalInstance = bootstrap.Modal.getInstance(modal);
                if (modalInstance) {
                    modalInstance.hide();
                }
            });
        }
    });
}

/**
 * カードアニメーション
 */
function animateCards() {
    const cards = document.querySelectorAll('.payment-card, .outstanding-item');
    cards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        card.style.transition = 'all 0.5s ease';
        
        setTimeout(() => {
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 100);
    });
}

/**
 * 自動更新機能
 */
function startAutoRefresh() {
    // 5分ごとにページを自動更新（アクティブ時のみ）
    setInterval(function() {
        if (document.visibilityState === 'visible') {
            // モーダルが開いていない場合のみ更新
            const openModals = document.querySelectorAll('.modal.show');
            if (openModals.length === 0) {
                location.reload();
            }
        }
    }, 5 * 60 * 1000);
}

/**
 * ユーティリティ関数群
 */

// 通貨フォーマット
function formatCurrency(amount) {
    return new Intl.NumberFormat('ja-JP', {
        style: 'currency',
        currency: 'JPY',
        minimumFractionDigits: 0
    }).format(amount || 0);
}

// 日付フォーマット
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('ja-JP', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit'
    });
}

// 日時フォーマット
function formatDateTime(dateString) {
    const date = new Date(dateString);
    return date.toLocaleString('ja-JP', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit'
    });
}

// 期限までの日数計算
function getDaysUntilDue(dueDateString) {
    const today = new Date();
    const dueDate = new Date(dueDateString);
    const diffTime = dueDate - today;
    return Math.ceil(diffTime / (1000 * 60 * 60 * 24));
}

// 期限表示フォーマット
function formatDaysUntilDue(days) {
    if (days < 0) {
        return `${Math.abs(days)}日超過`;
    } else if (days === 0) {
        return '本日期限';
    } else {
        return `あと${days}日`;
    }
}

// ステータス色取得
function getStatusColor(status) {
    const colors = {
        'paid': 'success',
        'partial': 'warning',
        'issued': 'primary',
        'sent': 'info',
        'overdue': 'danger',
        'cancelled': 'secondary'
    };
    return colors[status] || 'secondary';
}

// 支払方法テキスト取得
function getPaymentMethodText(method) {
    const methods = {
        'cash': '現金',
        'bank_transfer': '銀行振込',
        'account_debit': '口座引落',
        'credit_card': 'クレジットカード',
        'electronic_money': '電子マネー',
        'other': 'その他'
    };
    return methods[method] || method;
}

// HTMLエスケープ
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// アラート表示
function showAlert(type, message, duration = 5000) {
    const alertContainer = document.createElement('div');
    alertContainer.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    alertContainer.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    
    alertContainer.innerHTML = `
        <i class="fas fa-${getAlertIcon(type)} me-2"></i>
        ${escapeHtml(message)}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(alertContainer);
    
    // 自動削除
    setTimeout(() => {
        if (alertContainer.parentNode) {
            alertContainer.remove();
        }
    }, duration);
}

// アラートアイコン取得
function getAlertIcon(type) {
    const icons = {
        'success': 'check-circle',
        'error': 'exclamation-triangle',
        'warning': 'exclamation-circle',
        'info': 'info-circle'
    };
    return icons[type] || 'info-circle';
}

// ローディングスピナー表示
function showLoadingSpinner() {
    const spinner = document.createElement('div');
    spinner.id = 'loadingSpinner';
    spinner.className = 'position-fixed d-flex align-items-center justify-content-center';
    spinner.style.cssText = 'top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999;';
    
    spinner.innerHTML = `
        <div class="text-center text-white">
            <div class="spinner-border mb-2" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <div>処理中...</div>
        </div>
    `;
    
    document.body.appendChild(spinner);
}

// ローディングスピナー非表示
function hideLoadingSpinner() {
    const spinner = document.getElementById('loadingSpinner');
    if (spinner) {
        spinner.remove();
    }
}

// ツールチップ表示
function showTooltip(element, message) {
    const tooltip = new bootstrap.Tooltip(element, {
        title: message,
        trigger: 'manual',
        placement: 'top'
    });
    
    tooltip.show();
    
    setTimeout(() => {
        tooltip.dispose();
    }, 3000);
}

// 印刷機能
function printPaymentDetail() {
    const content = document.getElementById('payment_detail_content').innerHTML;
    const printWindow = window.open('', '_blank');
    
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>支払い詳細</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
            <style>
                @media print {
                    .no-print { display: none !important; }
                    body { font-size: 12px; }
                }
            </style>
        </head>
        <body class="p-3">
            <h4 class="mb-4">支払い詳細</h4>
            ${content}
            <div class="text-center mt-4 no-print">
                <button class="btn btn-primary" onclick="window.print()">印刷</button>
                <button class="btn btn-secondary ms-2" onclick="window.close()">閉じる</button>
            </div>
        </body>
        </html>
    `);
    
    printWindow.document.close();
    printWindow.focus();
}

// エラーハンドリング
window.addEventListener('error', function(e) {
    console.error('JavaScript Error:', e.error);
    showAlert('error', 'システムエラーが発生しました。ページを更新してお試しください。');
});

window.addEventListener('unhandledrejection', function(e) {
    console.error('Promise Rejection:', e.reason);
    showAlert('error', '通信エラーが発生しました。接続を確認してお試しください。');
});
}

/**
 * 支払い詳細をモーダルに表示
 */
function displayPaymentDetail(payment) {
    const content = document.getElementById('payment_detail_content');
    
    content.innerHTML = `
        <div class="row g-3">
            <div class="col-md-6">
                <h6><i class="fas fa-receipt me-2"></i>支払い情報</h6>
                <table class="table table-borderless table-sm">
                    <tr><td><strong>支払ID:</strong></td><td>${payment.id}</td></tr>
                    <tr><td><strong>入金日:</strong></td><td>${formatDate(payment.payment_date)}</td></tr>
                    <tr><td><strong>入金額:</strong></td><td class="text-success fw-bold">${formatCurrency(payment.amount)}</td></tr>
                    <tr><td><strong>支払方法:</strong></td><td>${getPaymentMethodText(payment.payment_method)}</td></tr>
                    <tr><td><strong>参照番号:</strong></td><td>${payment.reference_number || '-'}</td></tr>
                    <tr><td><strong>処理日時:</strong></td><td>${formatDateTime(payment.created_at)}</td></tr>
                </table>
            </div>
            <div class="col-md-6">
                <h6><i class="fas fa-file-invoice me-2"></i>請求書情報</h6>
                <table class="table table-borderless table-sm">
                    <tr><td><strong>請求書番号:</strong></td><td>${payment.invoice_number}</td></tr>
                    <tr><td><strong>企業名:</strong></td><td>${payment.company_name}</td></tr>
                    <tr><td><strong>利用者:</strong></td><td>${payment.user_name}</td></tr>
                    <tr><td><strong>請求額:</strong></td><td>${formatCurrency(payment.invoice_total)}</td></tr>
                    <tr><td><strong>請求書状態:</strong></td><td><span class="badge bg-${getStatusColor(payment.invoice_status)}">${payment.invoice_status}</span></td></tr>
                </table>
            </div>
            ${payment.notes ? `
            <div class="col-12">
                <h6><i class="fas fa-sticky-note me-2"></i>備考</h6>
                <div class="alert alert-light">${escapeHtml(payment.notes)}</div>
            </div>
            ` : ''}
        </div>
    `;
    
    const modal = new bootstrap.Modal(document.getElementById('paymentDetailModal'));
    modal.show();
}

/**
 * 請求書詳細表示
 */
function viewInvoiceDetail(invoiceId) {
    window.open(`invoice_detail.php?id=${invoiceId}`, '_blank', 'width=1000,height=800,scrollbars=yes');
}

/**
 * 請求書検索機能
 */
function searchInvoices() {
    const searchTerm = document.getElementById('invoice_search').value.trim();
    const status = document.getElementById('search_status').value;
    const minAmount = document.getElementById('search_min_amount').value;
    
    if (!searchTerm && !status && !minAmount) {
        showAlert('warning', '検索条件を入力してください');
        return;
    }
    
    showLoadingSpinner();
    
    const params = new URLSearchParams({
        action: 'search',
        term: searchTerm,
        status: status,
        min_amount: minAmount
    });
    
    fetch(`../api/invoices.php?${params}`)
        .then(response => response.json())
        .then(data => {
            hideLoadingSpinner();
            displayInvoiceSearchResults(data);
        })
        .catch(error => {
            hideLoadingSpinner();
            console.error('請求書検索エラー:', error);
            showAlert('error', '請求書検索中にエラーが発生しました');
        });
}

/**
 * 請求書検索結果表示
 */
function displayInvoiceSearchResults(data) {
    const resultsContainer = document.getElementById('invoice_search_results');
    
    if (!data.success || !data.invoices || data.invoices.length === 0) {
        resultsContainer.innerHTML = `
            <div class="text-center py-4">
                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                <p class="text-muted">該当する請求書が見つかりませんでした</p>
                <p class="small text-muted">検索条件を変更してお試しください</p>
            </div>`;
        return;
    }
    
    let html = `
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>
            ${data.invoices.length}件の請求書が見つかりました
        </div>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>請求書番号</th>
                        <th>企業・利用者</th>
                        <th>未払金額</th>
                        <th>期限</th>
                        <th>状態</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>`;
    
    data.invoices.forEach(invoice => {
        const statusColor = getStatusColor(invoice.status);
        const daysUntilDue = getDaysUntilDue(invoice.due_date);
        const dueDateClass = daysUntilDue < 0 ? 'text-danger' : daysUntilDue <= 3 ? 'text-warning' : '';
        
        html += `
            <tr>
                <td><strong>${escapeHtml(invoice.invoice_number)}</strong></td>
                <td>
                    <strong>${escapeHtml(invoice.company_name)}</strong><br>
                    <small class="text-muted">${escapeHtml(invoice.user_name)}</small>
                </td>
                <td class="fw-bold">${formatCurrency(invoice.outstanding_amount)}</td>
                <td class="${dueDateClass}">
                    ${formatDate(invoice.due_date)}<br>
                    <small>${formatDaysUntilDue(daysUntilDue)}</small>
                </td>
                <td><span class="badge bg-${statusColor}">${invoice.status}</span></td>
                <td>
                    <button class="btn btn-success btn-sm" 
                            onclick="selectInvoiceForPayment(${invoice.id}, '${invoice.outstanding_amount}', '${escapeHtml(invoice.invoice_number)}')">
                        <i class="fas fa-plus me-1"></i>入金記録
                    </button>
                </td>
            </tr>`;
    });
    
    html += '</tbody></table></div>';
    resultsContainer.innerHTML = html;
}

/**
 * 請求書選択（検索モーダルから）
 */
function selectInvoiceForPayment(invoiceId, outstandingAmount, invoiceNumber) {
    // 検索モーダルを閉じる
    const searchModal = bootstrap.Modal.getInstance(document.getElementById('invoiceSearchModal'));
    if (searchModal) {
        searchModal.hide();
    }
    
    // 入金記録モーダルを開く
    setTimeout(() => {
        recordPaymentForInvoice(invoiceId, outstandingAmount, invoiceNumber);
    }, 300);
}

/**
 * 請求書検索モーダルを開く
 */
function openInvoiceSearch() {
    const modal = new bootstrap.Modal(document.getElementById('invoiceSearchModal'));
    modal.show();
    
    // 検索フィールドにフォーカス
    setTimeout(() => {
        document.getElementById('invoice_search').focus();
    }, 500);
}

/**
 * フォーム送信ハンドラーの設定
 */
function setupFormSubmissionHandlers() {
    // 入金記録フォーム
    const paymentForm = document.getElementById('paymentForm');
    if (paymentForm) {
        paymentForm.addEventListener('submit', function(e) {
            if (!validatePaymentForm()) {
                e.preventDefault();
                return false;
            }
            
            const amount = parseFloat(this.querySelector('input[name="amount"]').value);
            if (!confirm(`入金額 ${formatCurrency(amount)} で記録しますか？`)) {
                e.preventDefault();
                return false;
            }
            
            // 送信ボタンを無効化
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>処理中...';
        });
    }
    
    // キャンセルフォーム
    const cancelForm = document.getElementById('cancelPaymentForm');
    if (cancelForm) {
        cancelForm.addEventListener('submit', function(e) {
            const reason = this.querySelector('textarea[name="reason"]').value.trim();
            if (!reason) {
                e.preventDefault();
                showAlert('warning', 'キャンセル理由を入力してください');
                return false;
            }
            
            if (!confirm('支払い記録を完全にキャンセルしますか？\nこの操作は元に戻せません。')) {
                e.preventDefault();
                return false;
            }
            
            // 送信ボタンを無効化
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>処理中...';
        });
