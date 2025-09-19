/**
 * Smiley配食事業 集金管理システム
 * JavaScript - 集金業務特化版
 * 
 * @version 5.0
 * @date 2025-09-19
 * @purpose 集金管理業務の完全自動化・効率化
 */

'use strict';

/**
 * 集金管理メインクラス
 * 集金業務に特化した全ての機能を統合管理
 */
class CollectionManager {
    constructor() {
        // データ管理
        this.selectedInvoices = new Set();
        this.collectionData = [];
        this.currentFilters = {};
        this.currentSort = 'priority';
        this.currentPage = 1;
        this.itemsPerPage = 20;
        
        // UI状態管理
        this.isLoading = false;
        this.lastUpdateTime = null;
        
        // デバウンス用タイマー
        this.searchTimer = null;
        this.refreshTimer = null;
        
        console.log('CollectionManager初期化完了');
        this.init();
    }
    
    /**
     * システム初期化
     */
    async init() {
        try {
            console.log('集金管理システム初期化開始');
            
            // UI初期化
            this.setupEventListeners();
            this.setupKeyboardShortcuts();
            
            // データ読み込み
            await this.loadSummaryData();
            await this.loadCollectionList();
            
            // 自動更新設定（5分ごと）
            this.startAutoRefresh();
            
            // 初期化完了
            this.showSuccessMessage('集金管理システムが正常に初期化されました');
            console.log('集金管理システム初期化完了');
            
        } catch (error) {
            console.error('初期化エラー:', error);
            this.showErrorMessage('システムの初期化に失敗しました。ページを更新してください。');
        }
    }
    
    /**
     * イベントリスナー設定
     */
    setupEventListeners() {
        console.log('イベントリスナー設定中...');
        
        // 全選択チェックボックス
        const selectAllCheckbox = document.getElementById('select-all');
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', (e) => {
                this.handleSelectAll(e.target.checked);
            });
        }
        
        // 検索フィールド（デバウンス付き）
        const searchInput = document.getElementById('search-company');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                this.debounceSearch(e.target.value);
            });
            
            // Enterキーで即座検索
            searchInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    this.executeSearch(e.target.value);
                }
            });
        }
        
        // フィルターラジオボタン
        document.querySelectorAll('input[name="filter"]').forEach(radio => {
            radio.addEventListener('change', (e) => {
                this.applyFilter(e.target.value);
            });
        });
        
        // ソートドロップダウン
        document.querySelectorAll('[data-sort]').forEach(item => {
            item.addEventListener('click', (e) => {
                e.preventDefault();
                this.applySorting(e.target.dataset.sort);
            });
        });
        
        // 一括入金ボタン
        const bulkPaymentBtn = document.getElementById('bulk-payment-btn');
        if (bulkPaymentBtn) {
            bulkPaymentBtn.addEventListener('click', () => {
                this.showBulkPaymentModal();
            });
        }
        
        // モーダル確認ボタン
        this.setupModalEventListeners();
        
        // リサイズ対応
        window.addEventListener('resize', this.debounce(() => {
            this.adjustLayoutForDevice();
        }, 250));
        
        console.log('イベントリスナー設定完了');
    }
    
    /**
     * キーボードショートカット設定
     */
    setupKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            // Ctrl/Cmd + A: 全選択
            if ((e.ctrlKey || e.metaKey) && e.key === 'a' && e.target.tagName !== 'INPUT') {
                e.preventDefault();
                this.handleSelectAll(true);
            }
            
            // Ctrl/Cmd + R: 更新
            if ((e.ctrlKey || e.metaKey) && e.key === 'r') {
                e.preventDefault();
                this.refreshAllData();
            }
            
            // F5: 更新（標準動作を維持）
            if (e.key === 'F5') {
                this.refreshAllData();
            }
            
            // Escape: モーダルを閉じる
            if (e.key === 'Escape') {
                this.closeAllModals();
            }
        });
    }
    
    /**
     * モーダルイベントリスナー設定
     */
    setupModalEventListeners() {
        // 満額入金確認ボタン
        const confirmPaymentBtn = document.getElementById('confirm-payment-btn');
        if (confirmPaymentBtn) {
            confirmPaymentBtn.addEventListener('click', () => {
                this.confirmSinglePayment();
            });
        }
        
        // 一括入金確認ボタン
        const confirmBulkBtn = document.getElementById('confirm-bulk-payment-btn');
        if (confirmBulkBtn) {
            confirmBulkBtn.addEventListener('click', () => {
                this.confirmBulkPayment();
            });
        }
        
        // モーダルクローズ時のクリーンアップ
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('hidden.bs.modal', () => {
                this.resetModalForms();
            });
        });
    }
    
    /**
     * サマリーデータ読み込み
     */
    async loadSummaryData() {
        try {
            console.log('サマリーデータ読み込み開始');
            
            const response = await this.fetchWithTimeout('api/payments.php?action=collection_summary', {
                timeout: 10000
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.updateSummaryDisplay(data.data);
            } else {
                console.warn('サマリーデータ取得失敗:', data);
                this.showWarningMessage('統計情報の取得に失敗しました');
            }
            
        } catch (error) {
            console.error('サマリーデータ読み込みエラー:', error);
            // サマリーエラーは致命的ではないので、警告のみ表示
            this.showWarningMessage('統計情報が取得できませんでした');
        }
    }
    
    /**
     * 集金リスト読み込み
     */
    async loadCollectionList(filters = {}) {
        try {
            this.showLoading(true);
            console.log('集金リスト読み込み開始', filters);
            
            // フィルター条件をマージ
            const mergedFilters = {
                ...this.currentFilters,
                ...filters,
                sort: this.currentSort,
                page: this.currentPage,
                limit: this.itemsPerPage
            };
            
            const params = new URLSearchParams();
            params.append('action', 'collection_list');
            
            // フィルター条件を追加
            Object.entries(mergedFilters).forEach(([key, value]) => {
                if (value !== null && value !== undefined && value !== '') {
                    params.append(key, value);
                }
            });
            
            const response = await this.fetchWithTimeout(`api/payments.php?${params}`, {
                timeout: 15000
            });
            
            const data = await response.json();
            
            if (data && data.success !== false) {
                // APIが配列を返す場合と成功オブジェクトを返す場合の両方に対応
                const listData = Array.isArray(data) ? data : (data.data || []);
                this.collectionData = listData;
                this.renderCollectionList(listData);
                this.updatePagination(data.pagination || {});
                
                console.log(`集金リスト読み込み完了: ${listData.length}件`);
            } else {
                throw new Error(data.error || '集金リストの取得に失敗しました');
            }
            
        } catch (error) {
            console.error('集金リスト読み込みエラー:', error);
            this.showErrorMessage(`集金リストの読み込みに失敗しました: ${error.message}`);
            this.renderEmptyState();
        } finally {
            this.showLoading(false);
            this.lastUpdateTime = new Date();
        }
    }
    
    /**
     * 集金リスト表示
     */
    renderCollectionList(data) {
        console.log('集金リスト表示開始', data);
        
        const tbody = document.getElementById('collection-list');
        const tableContainer = document.getElementById('collection-table');
        const noDataContainer = document.getElementById('no-data');
        
        if (!tbody) {
            console.error('集金リストテーブルが見つかりません');
            return;
        }
        
        // テーブルクリア
        tbody.innerHTML = '';
        
        // データが空の場合
        if (!data || data.length === 0) {
            this.showEmptyState(tableContainer, noDataContainer);
            return;
        }
        
        // テーブル表示
        this.showTableState(tableContainer, noDataContainer);
        
        // 行を生成して追加
        data.forEach((item, index) => {
            const row = this.createCollectionRow(item, index);
            tbody.appendChild(row);
        });
        
        // 選択状態を更新
        this.updateSelectedSummary();
        
        // アクセシビリティ対応
        this.setupTableAccessibility();
        
        console.log(`集金リスト表示完了: ${data.length}行生成`);
    }
    
    /**
     * 集金リスト行作成
     */
    createCollectionRow(item, index) {
        const tr = document.createElement('tr');
        tr.className = `collection-row ${item.alert_level || 'normal'} js-collection-row`;
        tr.dataset.invoiceId = item.invoice_id || item.id || `temp_${index}`;
        tr.dataset.amount = item.outstanding_amount || item.total_amount || 0;
        tr.dataset.companyName = item.company_name || '企業名不明';
        
        // ARIA属性でアクセシビリティ向上
        tr.setAttribute('role', 'row');
        tr.setAttribute('aria-label', `${item.company_name} ${this.formatCurrency(item.outstanding_amount || 0)}の集金案件`);
        
        const alertIcon = this.getAlertIcon(item.alert_level);
        const alertBadge = this.getAlertBadge(item.alert_level, item.overdue_days);
        const statusBadge = this.getStatusBadge(item.alert_level);
        
        tr.innerHTML = `
            <td class="no-print" role="gridcell">
                <div class="form-check">
                    <input type="checkbox" 
                           class="form-check-input row-checkbox" 
                           data-invoice-id="${tr.dataset.invoiceId}" 
                           data-amount="${tr.dataset.amount}"
                           aria-label="${item.company_name}を選択">
                </div>
            </td>
            <td role="gridcell">
                <div class="d-flex align-items-center">
                    <div class="me-2" aria-hidden="true">${alertIcon}</div>
                    <div>
                        <div class="fw-bold text-large">${this.escapeHtml(item.company_name || '企業名不明')}</div>
                        <small class="text-muted">
                            ${this.escapeHtml(item.contact_person || '')}
                            ${item.phone ? `・${this.escapeHtml(item.phone)}` : ''}
                        </small>
                        ${item.delivery_location ? `<br><small class="text-info">📍 ${this.escapeHtml(item.delivery_location)}</small>` : ''}
                    </div>
                </div>
            </td>
            <td role="gridcell">
                <span class="fw-bold fs-4 text-primary">
                    ${this.formatCurrency(item.outstanding_amount || item.total_amount || 0)}
                </span>
                ${item.paid_amount > 0 ? `<br><small class="text-success">入金済: ${this.formatCurrency(item.paid_amount)}</small>` : ''}
            </td>
            <td role="gridcell">
                <div class="fw-bold">${this.formatDate(item.due_date)}</div>
                ${alertBadge}
                ${item.overdue_days > 0 ? `<br><small class="text-danger">期限切れ ${item.overdue_days}日経過</small>` : ''}
                ${item.days_until_due > 0 && item.days_until_due <= 3 ? `<br><small class="text-warning">あと${item.days_until_due}日</small>` : ''}
            </td>
            <td role="gridcell">
                ${statusBadge}
            </td>
            <td class="no-print" role="gridcell">
                <button type="button" 
                        class="btn btn-full-payment btn-sm js-payment-button" 
                        onclick="collectionManager.showSinglePaymentModal('${tr.dataset.invoiceId}')"
                        aria-label="${item.company_name}の満額入金処理">
                    <i class="material-icons me-1" aria-hidden="true">payments</i>
                    満額入金<br>
                    <small>${this.formatCurrency(item.outstanding_amount || item.total_amount || 0)}</small>
                </button>
            </td>
        `;
        
        // チェックボックスイベント設定
        const checkbox = tr.querySelector('.row-checkbox');
        if (checkbox) {
            checkbox.addEventListener('change', (e) => {
                this.handleRowSelection(e.target);
            });
        }
        
        // ホバー効果でプレビュー情報表示
        tr.addEventListener('mouseenter', () => {
            this.showRowPreview(tr, item);
        });
        
        tr.addEventListener('mouseleave', () => {
            this.hideRowPreview(tr);
        });
        
        return tr;
    }
    
    /**
     * アラートアイコン取得
     */
    getAlertIcon(level) {
        const icons = {
            'overdue': '<i class="material-icons text-danger fs-4" title="期限切れ">error</i>',
            'urgent': '<i class="material-icons text-warning fs-4" title="期限間近">warning</i>',
            'normal': '<i class="material-icons text-success fs-4" title="正常">check_circle</i>'
        };
        return icons[level] || icons['normal'];
    }
    
    /**
     * アラートバッジ取得
     */
    getAlertBadge(level, overdueDays) {
        if (level === 'overdue' && overdueDays > 0) {
            const badgeClass = overdueDays > 30 ? 'bg-danger' : overdueDays > 14 ? 'bg-warning' : 'bg-secondary';
            return `<span class="badge ${badgeClass} alert-badge overdue">${overdueDays}日経過</span>`;
        } else if (level === 'urgent') {
            return `<span class="badge bg-warning alert-badge urgent">期限間近</span>`;
        }
        return '';
    }
    
    /**
     * ステータスバッジ取得
     */
    getStatusBadge(level) {
        const badges = {
            'overdue': '<span class="badge bg-danger fs-6">🚨 期限切れ</span>',
            'urgent': '<span class="badge bg-warning text-dark fs-6">⚠️ 期限間近</span>',
            'normal': '<span class="badge bg-success fs-6">✅ 正常</span>'
        };
        return badges[level] || badges['normal'];
    }
    
    /**
     * 行選択処理
     */
    handleRowSelection(checkbox) {
        const invoiceId = checkbox.dataset.invoiceId;
        const row = checkbox.closest('tr');
        
        if (checkbox.checked) {
            this.selectedInvoices.add(invoiceId);
            row.classList.add('selected');
            row.setAttribute('aria-selected', 'true');
        } else {
            this.selectedInvoices.delete(invoiceId);
            row.classList.remove('selected');
            row.setAttribute('aria-selected', 'false');
        }
        
        this.updateSelectedSummary();
        this.updateSelectAllState();
        
        // アクセシビリティ通知
        this.announceSelectionChange(checkbox.checked, row.dataset.companyName);
    }
    
    /**
     * 全選択処理
     */
    handleSelectAll(checked) {
        const checkboxes = document.querySelectorAll('.row-checkbox');
        
        checkboxes.forEach(checkbox => {
            checkbox.checked = checked;
            this.handleRowSelection(checkbox);
        });
        
        // アクセシビリティ通知
        const message = checked ? 
            `全${checkboxes.length}件を選択しました` : 
            '全選択を解除しました';
        this.announceToScreenReader(message);
    }
    
    /**
     * 選択サマリー更新
     */
    updateSelectedSummary() {
        const selectedCheckboxes = document.querySelectorAll('.row-checkbox:checked');
        const selectedCount = selectedCheckboxes.length;
        const selectedAmount = Array.from(selectedCheckboxes).reduce((sum, cb) => {
            return sum + parseFloat(cb.dataset.amount || 0);
        }, 0);
        
        const summaryEl = document.getElementById('selected-summary');
        if (summaryEl) {
            summaryEl.textContent = `選択: ${selectedCount}件 ${this.formatCurrency(selectedAmount)}`;
            summaryEl.className = selectedCount > 0 ? 'me-3 badge bg-primary fs-6' : 'me-3 badge bg-info fs-6';
        }
        
        const bulkBtn = document.getElementById('bulk-payment-btn');
        if (bulkBtn) {
            bulkBtn.disabled = selectedCount === 0;
            bulkBtn.classList.toggle('pulse-effect', selectedCount > 0);
        }
    }
    
    /**
     * 全選択チェックボックス状態更新
     */
    updateSelectAllState() {
        const selectAllCheckbox = document.getElementById('select-all');
        const checkboxes = document.querySelectorAll('.row-checkbox');
        const checkedCheckboxes = document.querySelectorAll('.row-checkbox:checked');
        
        if (selectAllCheckbox && checkboxes.length > 0) {
            if (checkedCheckboxes.length === 0) {
                selectAllCheckbox.checked = false;
                selectAllCheckbox.indeterminate = false;
            } else if (checkedCheckboxes.length === checkboxes.length) {
                selectAllCheckbox.checked = true;
                selectAllCheckbox.indeterminate = false;
            } else {
                selectAllCheckbox.checked = false;
                selectAllCheckbox.indeterminate = true;
            }
        }
    }
    
    /**
     * 満額入金モーダル表示
     */
    showSinglePaymentModal(invoiceId) {
        const row = document.querySelector(`tr[data-invoice-id="${invoiceId}"]`);
        if (!row) {
            this.showErrorMessage('選択された項目が見つかりません');
            return;
        }
        
        const companyName = row.dataset.companyName;
        const amount = parseFloat(row.dataset.amount);
        
        // モーダルに情報を設定
        document.getElementById('modal-company-name').textContent = companyName;
        document.getElementById('modal-amount').textContent = this.formatCurrency(amount);
        document.getElementById('modal-invoice-id').value = invoiceId;
        
        // 今日の日付を設定
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('payment-date').value = today;
        
        // フォームをリセット
        document.getElementById('payment-method').value = '';
        document.getElementById('payment-notes').value = '';
        
        // モーダルを表示
        const modal = new bootstrap.Modal(document.getElementById('paymentModal'));
        modal.show();
        
        // フォーカスを支払方法に設定
        setTimeout(() => {
            document.getElementById('payment-method').focus();
        }, 500);
        
        console.log(`満額入金モーダル表示: ${companyName} ${this.formatCurrency(amount)}`);
    }
    
    /**
     * 一括入金モーダル表示
     */
    showBulkPaymentModal() {
        const selectedCheckboxes = document.querySelectorAll('.row-checkbox:checked');
        const selectedCount = selectedCheckboxes.length;
        
        if (selectedCount === 0) {
            this.showWarningMessage('処理する企業を選択してください');
            return;
        }
        
        const totalAmount = Array.from(selectedCheckboxes).reduce((sum, cb) => {
            return sum + parseFloat(cb.dataset.amount || 0);
        }, 0);
        
        // モーダルに情報を設定
        document.getElementById('bulk-company-count').textContent = selectedCount;
        document.getElementById('bulk-total-amount').textContent = this.formatCurrency(totalAmount);
        
        // 今日の日付を設定
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('bulk-payment-date').value = today;
        
        // フォームをリセット
        document.getElementById('bulk-payment-method').value = '';
        document.getElementById('bulk-payment-notes').value = '';
        
        // 選択企業リストを表示（オプション）
        this.displaySelectedCompanies(selectedCheckboxes);
        
        // モーダルを表示
        const modal = new bootstrap.Modal(document.getElementById('bulkPaymentModal'));
        modal.show();
        
        // フォーカスを支払方法に設定
        setTimeout(() => {
            document.getElementById('bulk-payment-method').focus();
        }, 500);
        
        console.log(`一括入金モーダル表示: ${selectedCount}社 ${this.formatCurrency(totalAmount)}`);
    }
    
    /**
     * 満額入金確認・実行
     */
    async confirmSinglePayment() {
        try {
            const form = document.getElementById('payment-form');
            const formData = new FormData(form);
            const invoiceId = document.getElementById('modal-invoice-id').value;
            const companyName = document.getElementById('modal-company-name').textContent;
            const amount = document.getElementById('modal-amount').textContent;
            
            // バリデーション
            if (!formData.get('payment_method')) {
                this.showValidationError('支払方法を選択してください', 'payment-method');
                return;
            }
            
            if (!formData.get('payment_date')) {
                this.showValidationError('入金日を入力してください', 'payment-date');
                return;
            }
            
            // 確認ダイアログ
            const confirmed = await this.showConfirmDialog(
                '入金記録確認',
                `${companyName}\n${amount}\n\nこの内容で入金記録を行いますか？\n\n※この操作は取り消せません。`,
                'confirm-payment'
            );
            
            if (!confirmed) return;
            
            // ローディング開始
            this.setButtonLoading('confirm-payment-btn', true);
            
            // API呼び出し
            const response = await this.fetchWithTimeout('api/payments.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'record_full_payment',
                    invoice_id: parseInt(invoiceId),
                    payment_method: formData.get('payment_method'),
                    payment_date: formData.get('payment_date'),
                    notes: formData.get('notes'),
                    reference_number: this.generateReferenceNumber(formData.get('payment_method'))
                }),
                timeout: 30000
            });
            
            const result = await response.json();
            
            if (result && result.success) {
                // 成功メッセージ
                this.showSuccessMessage(`✅ 入金記録が完了しました\n${companyName}: ${this.formatCurrency(result.amount || 0)}`);
                
                // モーダルを閉じる
                const modal = bootstrap.Modal.getInstance(document.getElementById('paymentModal'));
                modal.hide();
                
                // データを更新
                await this.refreshAllData();
                
                // 成功音を再生（オプション）
                this.playSuccessSound();
                
            } else {
                throw new Error(result.error || '入金記録に失敗しました');
            }
            
        } catch (error) {
            console.error('入金記録エラー:', error);
            this.showErrorMessage(`❌ 入金記録中にエラーが発生しました: ${error.message}`);
        } finally {
            this.setButtonLoading('confirm-payment-btn', false);
        }
    }
    
    /**
     * 一括入金確認・実行
     */
    async confirmBulkPayment() {
        try {
            const form = document.getElementById('bulk-payment-form');
            const formData = new FormData(form);
            const selectedCheckboxes = document.querySelectorAll('.row-checkbox:checked');
            const invoiceIds = Array.from(selectedCheckboxes).map(cb => parseInt(cb.dataset.invoiceId));
            const companyCount = invoiceIds.length;
            const totalAmount = Array.from(selectedCheckboxes).reduce((sum, cb) => sum + parseFloat(cb.dataset.amount || 0), 0);
            
            // バリデーション
            if (!formData.get('payment_method')) {
                this.showValidationError('支払方法を選択してください', 'bulk-payment-method');
                return;
            }
            
            if (invoiceIds.length === 0) {
                this.showWarningMessage('処理する企業が選択されていません');
                return;
            }
            
            // 確認ダイアログ（詳細版）
            const confirmed = await this.showConfirmDialog(
                '一括入金記録確認',
                `${companyCount}社の一括入金記録を実行します。\n\n合計金額: ${this.formatCurrency(totalAmount)}\n支払方法: ${this.getPaymentMethodLabel(formData.get('payment_method'))}\n\n※この操作は取り消せません。\n処理を続行しますか？`,
                'confirm-bulk-payment'
            );
            
            if (!confirmed) return;
            
            // ローディング開始
            this.setButtonLoading('confirm-bulk-payment-btn', true);
            this.showProgressDialog(`一括処理中... (0/${companyCount})`);
            
            // API呼び出し
            const response = await this.fetchWithTimeout('api/payments.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'record_bulk_full_payments',
                    invoice_ids: invoiceIds,
                    payment_method: formData.get('payment_method'),
                    payment_date: formData.get('payment_date'),
                    notes: formData.get('notes') || '一括入金処理',
                    reference_number: this.generateBulkReferenceNumber()
                }),
                timeout: 60000
            });
            
            const result = await response.json();
            
            if (result && result.success) {
                // 成功メッセージ（詳細版）
                const message = `✅ 一括入金記録が完了しました\n\n` +
                    `成功: ${result.success_count}件\n` +
                    `合計: ${this.formatCurrency(result.total_amount || 0)}`;
                
                if (result.failed_count > 0) {
                    message += `\n\n⚠️ 失敗: ${result.failed_count}件\n詳細はログを確認してください。`;
                }
                
                this.showSuccessMessage(message);
                
                // モーダルを閉じる
                const modal = bootstrap.Modal.getInstance(document.getElementById('bulkPaymentModal'));
                modal.hide();
                
                // 選択をクリア
                this.clearAllSelections();
                
                // データを更新
                await this.refreshAllData();
                
                // 成功音を再生
                this.playSuccessSound();
                
            } else {
                throw new Error(result.error || '一括入金記録に失敗しました');
            }
            
        } catch (error) {
            console.error('一括入金記録エラー:', error);
            this.showErrorMessage(`❌ 一括入金記録中にエラーが発生しました: ${error.message}`);
        } finally {
            this.setButtonLoading('confirm-bulk-payment-btn', false);
            this.hideProgressDialog();
        }
    }
    
    /**
     * 検索機能（デバウンス付き）
     */
    debounceSearch(query) {
        clearTimeout(this.searchTimer);
        this.searchTimer = setTimeout(() => {
            this.executeSearch(query);
        }, 500);
    }
    
    /**
     * 検索実行
     */
    async executeSearch(query) {
        console.log('検索実行:', query);
        this.currentFilters.company_name = query;
        this.currentPage = 1;
        await this.loadCollectionList();
    }
    
    /**
     * フィルター適用
     */
    async applyFilter(filterValue) {
        console.log('フィルター適用:', filterValue);
        this.currentFilters.alert_level = filterValue;
        this.currentPage = 1;
        await this.loadCollectionList();
    }
    
    /**
     * ソート適用
     */
    async applySorting(sortValue) {
        console.log('ソート適用:', sortValue);
        this.currentSort = sortValue;
        this.currentPage = 1;
        await this.loadCollectionList();
    }
    
    /**
     * 全データ更新
     */
    async refreshAllData() {
        console.log('全データ更新開始');
        
        try {
            // 並行してデータ取得
            await Promise.all([
                this.loadSummaryData(),
                this.loadCollectionList()
            ]);
            
            this.showSuccessMessage('データを更新しました');
            console.log('全データ更新完了');
            
        } catch (error) {
            console.error('データ更新エラー:', error);
            this.showErrorMessage('データ更新中にエラーが発生しました');
        }
    }
    
    /**
     * 自動更新開始
     */
    startAutoRefresh() {
        // 5分ごとに自動更新
        this.refreshTimer = setInterval(async () => {
            console.log('自動更新実行');
            await this.refreshAllData();
        }, 300000); // 5分
        
        console.log('自動更新タイマー開始（5分間隔）');
    }
    
    /**
     * ローディング表示制御
     */
    showLoading(show) {
        const loading = document.getElementById('loading');
        const table = document.getElementById('collection-table');
        
        if (loading) {
            loading.style.display = show ? 'block' : 'none';
        }
        if (table) {
            table.style.display = show ? 'none' : 'block';
        }
        
        this.isLoading = show;
    }
    
    /**
     * 空状態表示
     */
    showEmptyState(tableContainer, noDataContainer) {
        if (tableContainer) tableContainer.style.display = 'none';
        if (noDataContainer) noDataContainer.style.display = 'block';
    }
    
    /**
     * テーブル状態表示
     */
    showTableState(tableContainer, noDataContainer) {
        if (tableContainer) tableContainer.style.display = 'block';
        if (noDataContainer) noDataContainer.style.display = 'none';
    }
    
    // =====================================================
    // ユーティリティメソッド
    // =====================================================
    
    /**
     * 通貨フォーマット
     */
    formatCurrency(amount) {
        const num = parseFloat(amount) || 0;
        return `¥${num.toLocaleString()}`;
    }
    
    /**
     * 日付フォーマット
     */
    formatDate(dateString) {
        if (!dateString) return '未設定';
        
        try {
            const date = new Date(dateString);
            return date.toLocaleDateString('ja-JP', {
                year: 'numeric',
                month: 'numeric',
                day: 'numeric',
                weekday: 'short'
            });
        } catch (error) {
            return dateString;
        }
    }
    
    /**
     * HTMLエスケープ
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    /**
     * デバウンス関数
     */
    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
    
    /**
     * タイムアウト付きfetch
     */
    async fetchWithTimeout(url, options = {}) {
        const { timeout = 10000, ...fetchOptions } = options;
        
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), timeout);
        
        try {
            const response = await fetch(url, {
                ...fetchOptions,
                signal: controller.signal
            });
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            return response;
        } finally {
            clearTimeout(timeoutId);
        }
    }
    
    /**
     * 参照番号生成
     */
    generateReferenceNumber(paymentMethod) {
        const date = new Date().toISOString().slice(0, 10).replace(/-/g, '');
        const random = Math.random().toString(36).substr(2, 6).toUpperCase();
        const methodCode = {
            'cash': 'CSH',
            'bank_transfer': 'BNK',
            'paypay': 'PAY',
            'account_debit': 'DBT',
            'other': 'OTH'
        }[paymentMethod] || 'GEN';
        
        return `${methodCode}${date}_${random}`;
    }
    
    /**
     * 一括参照番号生成
     */
    generateBulkReferenceNumber() {
        const date = new Date().toISOString().slice(0, 10).replace(/-/g, '');
        const time = new Date().toTimeString().slice(0, 5).replace(':', '');
        const random = Math.random().toString(36).substr(2, 4).toUpperCase();
        
        return `BULK_${date}_${time}_${random}`;
    }
    
    /**
     * 支払方法ラベル取得
     */
    getPaymentMethodLabel(method) {
        const labels = {
            'cash': '💵 現金',
            'bank_transfer': '🏦 銀行振込',
            'paypay': '📱 PayPay',
            'account_debit': '🏦 口座引き落とし',
            'mixed': '💳 混合',
            'other': '💼 その他'
        };
        return labels[method] || method;
    }
    
    /**
     * メッセージ表示（成功）
     */
    showSuccessMessage(message) {
        this.showToast(message, 'success');
    }
    
    /**
     * メッセージ表示（エラー）
     */
    showErrorMessage(message) {
        this.showToast(message, 'error');
    }
    
    /**
     * メッセージ表示（警告）
     */
    showWarningMessage(message) {
        this.showToast(message, 'warning');
    }
    
    /**
     * トースト通知表示
     */
    showToast(message, type = 'info') {
        // シンプルなアラート実装（後でtoast通知に改良）
        const icon = {
            'success': '✅',
            'error': '❌',
            'warning': '⚠️',
            'info': 'ℹ️'
        }[type] || 'ℹ️';
        
        alert(`${icon} ${message}`);
    }
    
    /**
     * 確認ダイアログ表示
     */
    async showConfirmDialog(title, message, type = 'confirm') {
        // シンプルなconfirm実装（後でモーダルダイアログに改良）
        return confirm(`${title}\n\n${message}`);
    }
    
    /**
     * 成功音再生（オプション）
     */
    playSuccessSound() {
        // Web Audio API または Audio要素での効果音再生
        // 現在は無音（ユーザー体験向上のため将来実装）
        console.log('🔔 成功音再生（無音）');
    }
    
    /**
     * クリーンアップ（ページ離脱時）
     */
    cleanup() {
        if (this.refreshTimer) {
            clearInterval(this.refreshTimer);
        }
        
        if (this.searchTimer) {
            clearTimeout(this.searchTimer);
        }
        
        console.log('CollectionManager クリーンアップ完了');
    }
}

/**
 * グローバル変数・初期化
 */
let collectionManager = null;

// ページ読み込み完了時に初期化
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 集金管理システム JavaScript 初期化開始');
    
    try {
        collectionManager = new CollectionManager();
        
        // グローバルアクセス用（デバッグ・テスト用）
        window.collectionManager = collectionManager;
        
    } catch (error) {
        console.error('❌ CollectionManager初期化エラー:', error);
        alert('❌ システムの初期化に失敗しました。ページを再読み込みしてください。');
    }
});

// ページ離脱時のクリーンアップ
window.addEventListener('beforeunload', function() {
    if (collectionManager) {
        collectionManager.cleanup();
    }
});

// ダークモード対応（将来の機能拡張）
const prefersDarkMode = window.matchMedia('(prefers-color-scheme: dark)');
prefersDarkMode.addEventListener('change', function(e) {
    console.log('ダークモード変更:', e.matches ? 'dark' : 'light');
    // 将来的にダークモード対応時に実装
});

console.log('✅ collection.js 読み込み完了 - 集金管理システム準備完了');
