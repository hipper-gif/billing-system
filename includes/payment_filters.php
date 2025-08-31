<div class="filter-section">
    <form method="GET" class="row g-3">
        <input type="hidden" name="view_type" value="<?php echo htmlspecialchars($filters['view_type']); ?>">
        
        <div class="col-md-2">
            <label for="date_from" class="form-label">開始日</label>
            <input type="date" class="form-control" id="date_from" name="date_from" 
                   value="<?php echo htmlspecialchars($filters['date_from']); ?>">
        </div>
        
        <div class="col-md-2">
            <label for="date_to" class="form-label">終了日</label>
            <input type="date" class="form-control" id="date_to" name="date_to" 
                   value="<?php echo htmlspecialchars($filters['date_to']); ?>">
        </div>
        
        <div class="col-md-2">
            <label for="company_id" class="form-label">企業</label>
            <select class="form-select" id="company_id" name="company_id">
                <option value="">全企業</option>
                <?php foreach ($companies as $company): ?>
                <option value="<?php echo $company['id']; ?>" 
                        <?php echo $filters['company_id'] == $company['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($company['company_name']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <?php if ($filters['view_type'] === 'payments'): ?>
        <div class="col-md-2">
            <label for="payment_method" class="form-label">支払方法</label>
            <select class="form-select" id="payment_method" name="payment_method">
                <option value="">全方法</option>
                <option value="cash" <?php echo $filters['payment_method'] === 'cash' ? 'selected' : ''; ?>>現金</option>
                <option value="bank_transfer" <?php echo $filters['payment_method'] === 'bank_transfer' ? 'selected' : ''; ?>>銀行振込</option>
                <option value="account_debit" <?php echo $filters['payment_method'] === 'account_debit' ? 'selected' : ''; ?>>口座引落</option>
                <option value="other" <?php echo $filters['payment_method'] === 'other' ? 'selected' : ''; ?>>その他</option>
            </select>
        </div>
        <?php elseif ($filters['view_type'] === 'outstanding'): ?>
        <div class="col-md-2">
            <label for="priority" class="form-label">優先度</label>
            <select class="form-select" id="priority" name="priority">
                <option value="">全て</option>
                <option value="overdue" <?php echo ($_GET['priority'] ?? '') === 'overdue' ? 'selected' : ''; ?>>期限切れ</option>
                <option value="urgent" <?php echo ($_GET['priority'] ?? '') === 'urgent' ? 'selected' : ''; ?>>緊急</option>
                <option value="warning" <?php echo ($_GET['priority'] ?? '') === 'warning' ? 'selected' : ''; ?>>注意</option>
                <option value="normal" <?php echo ($_GET['priority'] ?? '') === 'normal' ? 'selected' : ''; ?>>通常</option>
            </select>
        </div>
        <?php endif; ?>
        
        <div class="col-md-2">
            <label for="search" class="form-label">検索</label>
            <input type="text" class="form-control" id="search" name="search" 
                   placeholder="企業名・利用者名・請求書番号" 
                   value="<?php echo htmlspecialchars($filters['search']); ?>">
        </div>
        
        <div class="col-md-2">
            <label class="form-label">&nbsp;</label>
            <div class="d-grid">
                <button type="submit" class="btn btn-primary action-button">
                    <i class="fas fa-search me-2"></i>検索
                </button>
            </div>
        </div>
        
        <?php if ($filters['view_type'] === 'outstanding'): ?>
        <div class="col-md-12">
            <div class="row g-2">
                <div class="col-md-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="only_overdue" id="only_overdue" 
                               value="1" <?php echo !empty($_GET['only_overdue']) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="only_overdue">
                            期限切れのみ表示
                        </label>
                    </div>
                </div>
                <div class="col-md-3">
                    <button type="button" class="btn btn-outline-secondary" onclick="clearFilters()">
                        <i class="fas fa-times me-2"></i>フィルタークリア
                    </button>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </form>
</div>

<script>
function clearFilters() {
    const url = new URL(window.location);
    url.search = '?view_type=' + '<?php echo htmlspecialchars($filters['view_type']); ?>';
    window.location = url.toString();
}
</script><div class="form-check">
                        <input class="form-check-input" type="checkbox" name="large_amount" id="large_amount" 
                               value="1" <?php echo !empty($_GET['large_amount']) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="large_amount">
                            高額（5万円以上）のみ表示
                        </label>
                    </div>
                </div>
                <div class="col-md-3">
