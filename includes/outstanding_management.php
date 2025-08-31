<!-- 未回収金額管理表示 -->
<div class="card">
    <div class="card-header bg-white">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">
                <i class="fas fa-exclamation-triangle me-2 text-warning"></i>
                未回収金額管理
            </h5>
            <div class="d-flex gap-2">
                <div class="btn-group" role="group">
                    <input type="radio" class="btn-check" name="priority_filter" id="all_priority" value="" 
                           <?php echo empty($_GET['priority']) ? 'checked' : ''; ?> onchange="changePriorityFilter(this.value)">
                    <label class="btn btn-outline-secondary btn-sm" for="all_priority">全て</label>

                    <input type="radio" class="btn-check" name="priority_filter" id="overdue_priority" value="overdue"
                           <?php echo ($_GET['priority'] ?? '') === 'overdue' ? 'checked' : ''; ?> onchange="changePriorityFilter(this.value)">
                    <label class="btn btn-outline-danger btn-sm" for="overdue_priority">期限切れ</label>

                    <input type="radio" class="btn-check" name="priority_filter" id="urgent_priority" value="urgent"
                           <?php echo ($_GET['priority'] ?? '') === 'urgent' ? 'checked' : ''; ?> onchange="changePriorityFilter(this.value)">
                    <label class="btn btn-outline-warning btn-sm" for="urgent_priority">緊急</label>
                </div>
                
                <button class="btn btn-outline-primary btn-sm" onclick="generateCollectionReport()">
                    <i class="fas fa-file-alt me-2"></i>督促状一括生成
                </button>
            </div>
        </div>
    </div>
    <div class="card-body">
        <?php if (!empty($data)): ?>
            <!-- 未回収サマリー -->
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="card bg-danger text-white">
                        <div class="card-body text-center py-2">
                            <h6 class="card-title mb-1">期限切れ</h6>
                            <h4 class="mb-0"><?php echo count(array_filter($data, fn($item) => $item['priority'] === 'overdue')); ?>件</h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-dark">
                        <div class="card-body text-center py-2">
                            <h6 class="card-title mb-1">緊急</h6>
                            <h4 class="mb-0"><?php echo count(array_filter($data, fn($item) => $item['priority'] === 'urgent')); ?>件</h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body text-center py-2">
                            <h6 class="card-title mb-1">注意</h6>
                            <h4 class="mb-0"><?php echo count(array_filter($data, fn($item) => $item['priority'] === 'warning')); ?>件</h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body text-center py-2">
                            <h6 class="card-title mb-1">総未回収額</h6>
                            <h4 class="mb-0"><?php echo formatCurrency(array_sum(array_column($data, 'outstanding_amount'))); ?></h4>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- 未回収一覧 -->
            <div class="outstanding-list">
                <?php foreach ($data as $outstanding): ?>
                <div class="outstanding-item <?php echo $outstanding['priority']; ?> mb-3" data-invoice-id="<?php echo $outstanding['invoice_id']; ?>">
                    <div class="row align-items-center">
                        <!-- 企業・請求書情報 -->
                        <div class="col-lg-4 col-md-12 mb-2 mb-lg-0">
                            <div class="d-flex align-items-start">
                                <div class="flex-grow-1">
                                    <h6 class="mb-1 fw-bold">
                                        <i class="fas fa-building me-2"></i>
                                        <?php echo htmlspecialchars($outstanding['company_name']); ?>
                                    </h6>
                                    <?php if (!empty($outstanding['department_name'])): ?>
                                    <div class="text-muted small mb-1">
                                        <i class="fas fa-users me-1"></i>
                                        <?php echo htmlspecialchars($outstanding['department_name']); ?>
                                    </div>
                                    <?php endif; ?>
                                    <div class="text-primary small">
                                        <i class="fas fa-user me-1"></i>
                                        <?php echo htmlspecialchars($outstanding['user_name']); ?>
                                    </div>
                                    <div class="small text-muted mt-1">
                                        <strong>請求書:</strong> <?php echo htmlspecialchars($outstanding['invoice_number']); ?>
                                        <?php if (!empty($outstanding['invoice_date'])): ?>
                                        <span class="ms-2">発行: <?php echo formatDate($outstanding['invoice_date']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- 金額情報 -->
                        <div class="col-lg-2 col-md-6 text-center mb-2 mb-lg-0">
                            <div class="fw-bold text-danger fs-4">
                                <?php echo formatCurrency($outstanding['outstanding_amount']); ?>
                            </div>
                            <small class="text-muted">
                                請求額: <?php echo formatCurrency($outstanding['total_amount']); ?>
                            </small>
                            <?php if ($outstanding['outstanding_amount'] != $outstanding['total_amount']): ?>
                            <div class="small text-success">
                                入金済: <?php echo formatCurrency($outstanding['total_amount'] - $outstanding['outstanding_amount']); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- 期限情報 -->
                        <div class="col-lg-2 col-md-6 text-center mb-2 mb-lg-0">
                            <div class="fw-bold">
                                <?php echo formatDate($outstanding['due_date']); ?>
                            </div>
                            <small class="<?php echo $outstanding['days_until_due'] < 0 ? 'text-danger fw-bold' : 'text-muted'; ?>">
                                <?php 
                                $days = $outstanding['days_until_due'];
                                if ($days < 0) {
                                    echo '<i class="fas fa-exclamation-triangle me-1"></i>' . abs($days) . '日超過';
                                } elseif ($days == 0) {
                                    echo '<i class="fas fa-clock me-1"></i>本日期限';
                                } elseif ($days <= 3) {
                                    echo '<i class="fas fa-clock me-1 text-warning"></i>あと' . $days . '日';
                                } else {
                                    echo 'あと' . $days . '日';
                                }
                                ?>
                            </small>
                            <?php if (isset($outstanding['last_reminder_date']) && $outstanding['last_reminder_date']): ?>
                            <div class="small text-info mt-1">
                                <i class="fas fa-paper-plane me-1"></i>
                                督促: <?php echo formatDate($outstanding['last_reminder_date']); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- 優先度・ステータス -->
                        <div class="col-lg-2 col-md-12 text-center mb-2 mb-lg-0">
                            <div class="mb-2">
                                <span class="<?php echo getPriorityBadge($outstanding['priority']); ?>">
                                    <?php echo getPriorityText($outstanding['priority']); ?>
                                </span>
                            </div>
                            <?php if ($outstanding['outstanding_amount'] >= 50000): ?>
                            <div class="small">
                                <span class="badge bg-warning text-dark">
                                    <i class="fas fa-exclamation-circle me-1"></i>高額
                                </span>
                            </div>
                            <?php endif; ?>
                            <?php if (isset($outstanding['reminder_count']) && $outstanding['reminder_count'] > 0): ?>
                            <div class="small text-muted">
                                督促回数: <?php echo $outstanding['reminder_count']; ?>回
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- アクションボタン -->
                        <div class="col-lg-2 col-md-12">
                            <div class="d-grid gap-1">
                                <button class="btn btn-success btn-sm" 
                                        onclick="recordPaymentForInvoice(<?php echo $outstanding['invoice_id']; ?>, '<?php echo $outstanding['outstanding_amount']; ?>', '<?php echo htmlspecialchars($outstanding['invoice_number']); ?>')">
                                    <i class="fas fa-plus me-1"></i>入金記録
                                </button>
                                <div class="btn-group w-100">
                                    <button class="btn btn-outline-primary btn-sm" 
                                            onclick="viewInvoiceDetail(<?php echo $outstanding['invoice_id']; ?>)">
                                        <i class="fas fa-eye me-1"></i>詳細
                                    </button>
                                    <button class="btn btn-outline-warning btn-sm" 
                                            onclick="sendReminder(<?php echo $outstanding['invoice_id']; ?>)"
                                            title="督促状送信">
                                        <i class="fas fa-paper-plane"></i>
                                    </button>
                                    <button class="btn btn-outline-info btn-sm" 
                                            onclick="scheduleReminder(<?php echo $outstanding['invoice_id']; ?>)"
                                            title="督促予約">
                                        <i class="fas fa-calendar-plus"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- 追加情報（展開可能） -->
                    <div class="collapse mt-3" id="detail<?php echo $outstanding['invoice_id']; ?>">
                        <div class="border-top pt-3">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6 class="small fw-bold text-muted mb-2">請求詳細</h6>
                                    <table class="table table-sm table-borderless">
                                        <?php if (!empty($outstanding['billing_period_start']) && !empty($outstanding['billing_period_end'])): ?>
                                        <tr>
                                            <td class="ps-0 small">請求期間:</td>
                                            <td class="small">
                                                <?php echo formatDate($outstanding['billing_period_start']); ?>
                                                〜 <?php echo formatDate($outstanding['billing_period_end']); ?>
                                            </td>
                                        </tr>
                                        <?php endif; ?>
                                        <?php if (isset($outstanding['item_count'])): ?>
                                        <tr>
                                            <td class="ps-0 small">注文件数:</td>
                                            <td class="small"><?php echo $outstanding['item_count']; ?>件</td>
                                        </tr>
                                        <?php endif; ?>
                                        <?php if (!empty($outstanding['notes'])): ?>
                                        <tr>
                                            <td class="ps-0 small">備考:</td>
                                            <td class="small"><?php echo htmlspecialchars($outstanding['notes']); ?></td>
                                        </tr>
                                        <?php endif; ?>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="small fw-bold text-muted mb-2">連絡先情報</h6>
                                    <table class="table table-sm table-borderless">
                                        <?php if (!empty($outstanding['contact_person'])): ?>
                                        <tr>
                                            <td class="ps-0 small">担当者:</td>
                                            <td class="small"><?php echo htmlspecialchars($outstanding['contact_person']); ?></td>
                                        </tr>
                                        <?php endif; ?>
                                        <?php if (!empty($outstanding['contact_email'])): ?>
                                        <tr>
                                            <td class="ps-0 small">Email:</td>
                                            <td class="small">
                                                <a href="mailto:<?php echo htmlspecialchars($outstanding['contact_email']); ?>">
                                                    <?php echo htmlspecialchars($outstanding['contact_email']); ?>
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endif; ?>
                                        <?php if (!empty($outstanding['contact_phone'])): ?>
                                        <tr>
                                            <td class="ps-0
