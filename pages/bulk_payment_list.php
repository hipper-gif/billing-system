.full-payment-btn {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            border: none;
            padding: 12px 25px;
            font-size: 16px;
            font-weight: 600;
            border-radius: 8px;
            min-width: 120px;
            transition: all 0.3s ease;
            position: relative;
        }

        .full-payment-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(40, 167, 69, 0.3);
            color: white;
        }

        .full-payment-btn:before {
            content: "⚠️";
            position: absolute;
            top: -5px;
            right: -5px;
            background: #ffc107;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            animation: warning-pulse 2s infinite;
        }

        @keyframes warning-pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.7; transform: scale(1.1); }
        }

        .bulk-actions {
            background: #f8f9fa;
            padding: 20px;
            border-bottom: 1px solid #dee2e6;
            position: relative;
        }

        .bulk-actions:before {
            content: "⚠️ 重要: 必ず入金確認後に操作してください";
            position: absolute;
            top: 5px;
            right: 20px;
            background: #fff3cd;
            color: #856404;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 12px;
            font-weight: 600;
            border: 1px solid #ffeaa7;
        }

        .mega-button {
            min-height: 60px;
            font-size: 18px;
            font-weight: 600;
            border-radius: 10px;
            margin: 5px;
            transition: all 0.3s ease;
            position: relative;
        }

        .mega-button.btn-success:before {
            content: "⚠️ 重要操作";
            position: absolute;
            top: -8px;
            left: 50%;
            transform: translateX(-50%);
            background: #dc3545;
            color: white;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 10px;
            font-weight: 600;
        }

        .warning-text {
            background: #fff3cd;
            border: 2px solid #ffc107;
            border-radius: 8px;
            padding: 15px;
            margin: 15px 0;
            text-align: center;
            font-weight: 600;
            color: #856404;
        }

        .critical-warning {
            background: #f8d7da;
            border: 2px solid #dc3545;
            color: #721c24;
            animation: critical-blink 3s infinite;
        }

        @keyframes critical-blink {
            0%, 90%, 100% { opacity: 1; }
            45%, 55% { opacity: 0.7; }
        }

        .confirmation-required {
            background: #f8d7da !important;
            border-color: #dc3545 !important;
        }

        .confirmation-required:focus {
            box-shadow: 0 0 10px rgba(220,<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>満額入金リスト - 月末締め特化版</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px 0;
        }

        .main-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .header-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .stats-row {
            display: flex;
            justify-content: space-around;
            margin-top: 20px;
        }

        .stat-card {
            text-align: center;
            padding: 10px;
        }

        .stat-number {
            font-size: 28px;
            font-weight: bold;
        }

        .stat-label {
            font-size: 14px;
            opacity: 0.9;
        }

        .bulk-actions {
            background: #f8f9fa;
            padding: 20px;
            border-bottom: 1px solid #dee2e6;
        }

        .mega-button {
            min-height: 60px;
            font-size: 18px;
            font-weight: 600;
            border-radius: 10px;
            margin: 5px;
            transition: all 0.3s ease;
        }

        .mega-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.15);
        }

        .invoice-list {
            padding: 20px;
        }

        .invoice-item {
            border: 2px solid #e9ecef;
            border-radius: 12px;
            margin-bottom: 15px;
            padding: 20px;
            transition: all 0.3s ease;
            position: relative;
        }

        .invoice-item:hover {
            border-color: #667eea;
            box-shadow: 0 8px 16px rgba(102, 126, 234, 0.1);
        }

        .invoice-item.paid {
            background: #f8fff8;
            border-color: #28a745;
        }

        .invoice-item.overdue {
            background: #fff5f5;
            border-color: #dc3545;
        }

        .invoice-item.due-soon {
            background: #fffbf0;
            border-color: #ffc107;
        }

        .company-name {
            font-size: 20px;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .invoice-details {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .detail-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 16px;
        }

        .amount-display {
            font-size: 24px;
            font-weight: bold;
            color: #e74c3c;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-paid { background: #d4edda; color: #155724; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-overdue { background: #f8d7da; color: #721c24; }

        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .full-payment-btn {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            border: none;
            padding: 12px 25px;
            font-size: 16px;
            font-weight: 600;
            border-radius: 8px;
            min-width: 120px;
            transition: all 0.3s ease;
        }

        .full-payment-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(40, 167, 69, 0.3);
            color: white;
        }

        .partial-payment-btn {
            background: #6c757d;
            color: white;
            border: none;
            padding: 12px 20px;
            font-size: 14px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .contact-btn {
            background: #17a2b8;
            color: white;
            border: none;
            padding: 12px 20px;
            font-size: 14px;
            border-radius: 8px;
        }

        .priority-indicator {
            position: absolute;
            top: -5px;
            right: -5px;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .priority-critical {
            background: #dc3545;
            animation: pulse 2s infinite;
        }

        .priority-high {
            background: #ffc107;
        }

        .priority-medium {
            background: #6c757d;
        }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }

        .filter-tabs {
            display: flex;
            margin-bottom: 20px;
            border-bottom: 2px solid #e9ecef;
        }

        .filter-tab {
            padding: 12px 25px;
            border: none;
            background: none;
            font-size: 16px;
            font-weight: 500;
            color: #6c757d;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
        }

        .filter-tab.active {
            color: #667eea;
            border-bottom-color: #667eea;
            background: rgba(102, 126, 234, 0.05);
        }

        .summary-total {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
        }

        .total-amount {
            font-size: 36px;
            font-weight: bold;
            color: #e74c3c;
            margin-bottom: 10px;
        }

        .payment-method-icons {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }

        .payment-icon {
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 12px;
            background: #e9ecef;
            color: #495057;
        }

        @media (max-width: 768px) {
            .mega-button {
                min-height: 50px;
                font-size: 16px;
                margin: 3px;
            }
            
            .invoice-details {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="main-container">
            <!-- ヘッダーセクション -->
            <div class="header-section">
                <h1><i class="material-icons" style="font-size: 40px; vertical-align: middle;">account_balance_wallet</i> 満額入金リスト</h1>
                <p style="font-size: 18px; margin-bottom: 0;">月末締め - 一括入金処理センター</p>
                
                <div class="stats-row">
                    <div class="stat-card">
                        <div class="stat-number">8</div>
                        <div class="stat-label">未収企業</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number">¥485,200</div>
                        <div class="stat-label">未収総額</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number">3</div>
                        <div class="stat-label">期限切れ</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number">76%</div>
                        <div class="stat-label">回収率</div>
                    </div>
                </div>
            </div>

            <!-- 一括操作セクション -->
            <div class="bulk-actions">
                <div class="row">
                    <div class="col-md-8">
                        <button class="btn btn-success mega-button">
                            <i class="material-icons">done_all</i> 選択した請求書の満額入金を一括処理
                        </button>
                        <button class="btn btn-primary mega-button">
                            <i class="material-icons">payment</i> PayPay一括確認
                        </button>
                        <button class="btn btn-info mega-button">
                            <i class="material-icons">account_balance</i> 振込一括確認
                        </button>
                    </div>
                    <div class="col-md-4 text-end">
                        <button class="btn btn-secondary mega-button">
                            <i class="material-icons">print</i> 入金確認書印刷
                        </button>
                    </div>
                </div>
            </div>

            <!-- フィルタータブ -->
            <div class="invoice-list">
                <div class="filter-tabs">
                    <button class="filter-tab active" data-filter="all">全て (8件)</button>
                    <button class="filter-tab" data-filter="overdue">期限切れ (3件)</button>
                    <button class="filter-tab" data-filter="due-soon">期限間近 (2件)</button>
                    <button class="filter-tab" data-filter="paid">入金済み (3件)</button>
                </div>

                <!-- 未収総額サマリー -->
                <div class="summary-total">
                    <div class="total-amount">¥485,200</div>
                    <div>未収総額 (選択可能な請求書)</div>
                    <div style="margin-top: 10px;">
                        <span class="badge bg-danger">期限切れ: ¥180,000</span>
                        <span class="badge bg-warning">期限間近: ¥125,200</span>
                        <span class="badge bg-secondary">通常: ¥180,000</span>
                    </div>
                </div>

                <!-- 請求書リスト -->
                <div id="invoice-list">
                    <!-- 期限切れ案件 -->
                    <div class="invoice-item overdue" data-status="overdue">
                        <div class="priority-indicator priority-critical">!</div>
                        <input type="checkbox" class="form-check-input position-absolute" style="top: 20px; left: 20px;" value="INV-001" checked>
                        
                        <div class="company-name">株式会社○○商事</div>
                        <div class="invoice-details">
                            <div class="detail-item">
                                <i class="material-icons text-danger">schedule</i>
                                <span>期限切れ 15日経過</span>
                            </div>
                            <div class="detail-item">
                                <i class="material-icons">receipt</i>
                                <span>INV-20250815-001</span>
                            </div>
                            <div class="detail-item">
                                <i class="material-icons">calendar_today</i>
                                <span>期限: 8/31</span>
                            </div>
                            <div class="amount-display">¥89,500</div>
                        </div>
                        
                        <div class="payment-method-icons">
                            <span class="payment-icon"><i class="material-icons">account_balance</i> 振込</span>
                            <span class="payment-icon"><i class="material-icons">phone</i> 090-1234-5678</span>
                        </div>
                        
                        <div class="action-buttons">
                            <button class="full-payment-btn" onclick="recordFullPayment('INV-001', 89500)">
                                <i class="material-icons">payment</i> 満額入金 ¥89,500
                            </button>
                            <button class="partial-payment-btn">部分入金</button>
                            <button class="contact-btn">督促連絡</button>
                        </div>
                    </div>

                    <!-- 期限間近案件 -->
                    <div class="invoice-item due-soon" data-status="due-soon">
                        <div class="priority-indicator priority-high">⚠</div>
                        <input type="checkbox" class="form-check-input position-absolute" style="top: 20px; left: 20px;" value="INV-002" checked>
                        
                        <div class="company-name">環境局</div>
                        <div class="invoice-details">
                            <div class="detail-item">
                                <i class="material-icons text-warning">schedule</i>
                                <span>期限まで 2日</span>
                            </div>
                            <div class="detail-item">
                                <i class="material-icons">receipt</i>
                                <span>INV-20250825-002</span>
                            </div>
                            <div class="detail-item">
                                <i class="material-icons">calendar_today</i>
                                <span>期限: 9/15</span>
                            </div>
                            <div class="amount-display">¥125,200</div>
                        </div>
                        
                        <div class="payment-method-icons">
                            <span class="payment-icon"><i class="material-icons">smartphone</i> PayPay</span>
                            <span class="payment-icon"><i class="material-icons">account_balance</i> 振込併用</span>
                        </div>
                        
                        <div class="action-buttons">
                            <button class="full-payment-btn" onclick="recordFullPayment('INV-002', 125200)">
                                <i class="material-icons">payment</i> 満額入金 ¥125,200
                            </button>
                            <button class="partial-payment-btn">部分入金</button>
                            <button class="contact-btn">入金確認</button>
                        </div>
                    </div>

                    <!-- 通常案件 -->
                    <div class="invoice-item" data-status="pending">
                        <input type="checkbox" class="form-check-input position-absolute" style="top: 20px; left: 20px;" value="INV-003">
                        
                        <div class="company-name">株式会社Smiley</div>
                        <div class="invoice-details">
                            <div class="detail-item">
                                <i class="material-icons text-success">schedule</i>
                                <span>期限まで 10日</span>
                            </div>
                            <div class="detail-item">
                                <i class="material-icons">receipt</i>
                                <span>INV-20250901-003</span>
                            </div>
                            <div class="detail-item">
                                <i class="material-icons">calendar_today</i>
                                <span>期限: 9/25</span>
                            </div>
                            <div class="amount-display">¥270,500</div>
                        </div>
                        
                        <div class="payment-method-icons">
                            <span class="payment-icon"><i class="material-icons">account_balance</i> 自動振込</span>
                            <span class="payment-icon"><i class="material-icons">verified</i> 優良企業</span>
                        </div>
                        
                        <div class="action-buttons">
                            <button class="full-payment-btn" onclick="recordFullPayment('INV-003', 270500)">
                                <i class="material-icons">payment</i> 満額入金 ¥270,500
                            </button>
                            <button class="partial-payment-btn">部分入金</button>
                            <button class="contact-btn">入金照会</button>
                        </div>
                    </div>

                    <!-- 入金済み案件（参考表示） -->
                    <div class="invoice-item paid" data-status="paid">
                        <div class="company-name">△△会社 <span class="status-badge status-paid">入金済み</span></div>
                        <div class="invoice-details">
                            <div class="detail-item">
                                <i class="material-icons text-success">check_circle</i>
                                <span>入金確認済み</span>
                            </div>
                            <div class="detail-item">
                                <i class="material-icons">receipt</i>
                                <span>INV-20250810-004</span>
                            </div>
                            <div class="detail-item">
                                <i class="material-icons">calendar_today</i>
                                <span>入金日: 9/10</span>
                            </div>
                            <div class="amount-display" style="color: #28a745;">¥45,800</div>
                        </div>
                        
                        <div class="action-buttons">
                            <button class="btn btn-outline-success" disabled>
                                <i class="material-icons">check_circle</i> 入金完了
                            </button>
                            <button class="btn btn-outline-primary">領収書発行</button>
                        </div>
                    </div>
                </div>

                <!-- 一括処理確認ボタン -->
                <div class="text-center mt-4">
                    <button class="btn btn-success mega-button" style="min-width: 300px;" onclick="confirmBulkPayment()">
                        <i class="material-icons">done_all</i> 選択した 3件 の満額入金を実行 (¥485,200)
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- 満額入金確認モーダル -->
    <div class="modal fade" id="paymentModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="material-icons">payment</i> 満額入金記録</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="payment-summary">
                        <h6>入金詳細</h6>
                        <table class="table">
                            <tbody>
                                <tr>
                                    <td><strong>企業名:</strong></td>
                                    <td id="modal-company-name"></td>
                                </tr>
                                <tr>
                                    <td><strong>請求書番号:</strong></td>
                                    <td id="modal-invoice-number"></td>
                                </tr>
                                <tr>
                                    <td><strong>請求金額:</strong></td>
                                    <td id="modal-amount" style="font-size: 20px; color: #e74c3c; font-weight: bold;"></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="payment-details mt-4">
                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-label">入金日</label>
                                <input type="date" class="form-control" id="payment-date" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">支払方法</label>
                                <select class="form-select" id="payment-method" required>
                                    <option value="cash">💵 現金</option>
                                    <option value="bank_transfer">🏦 銀行振込</option>
                                    <option value="paypay">📱 PayPay</option>
                                    <option value="account_debit">🏦 口座引落</option>
                                    <option value="other">💳 その他</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <label class="form-label">入金金額</label>
                                <input type="number" class="form-control" id="payment-amount" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">参照番号 (振込番号等)</label>
                                <input type="text" class="form-control" id="reference-number" placeholder="任意">
                            </div>
                        </div>
                        
                        <div class="mt-3">
                            <label class="form-label">備考</label>
                            <textarea class="form-control" id="payment-notes" rows="3" placeholder="入金に関する特記事項があれば記入"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
                    <button type="button" class="btn btn-success" onclick="executePayment()">
                        <i class="material-icons">check</i> 入金を記録する
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // 現在の日付を設定
        document.getElementById('payment-date').value = new Date().toISOString().split('T')[0];

        // フィルタータブ機能
        document.querySelectorAll('.filter-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                document.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                
                const filter = this.dataset.filter;
                const items = document.querySelectorAll('.invoice-item');
                
                items.forEach(item => {
                    if (filter === 'all') {
                        item.style.display = 'block';
                    } else {
                        const status = item.dataset.status;
                        item.style.display = status === filter ? 'block' : 'none';
                    }
                });
            });
        });

        // 満額入金ボタン処理
        function recordFullPayment(invoiceNumber, amount) {
            // モーダルに情報を設定
            document.getElementById('modal-invoice-number').textContent = invoiceNumber;
            document.getElementById('modal-amount').textContent = `¥${amount.toLocaleString()}`;
            document.getElementById('payment-amount').value = amount;
            
            // 企業名を取得（実際は請求書番号から取得）
            const companyName = document.querySelector(`input[value="${invoiceNumber}"]`)
                ?.closest('.invoice-item')
                ?.querySelector('.company-name')
                ?.textContent || '不明';
            document.getElementById('modal-company-name').textContent = companyName;
            
            // モーダル表示
            const modal = new bootstrap.Modal(document.getElementById('paymentModal'));
            modal.show();
        }

        // 入金実行処理
        function executePayment() {
            const paymentData = {
                invoice_number: document.getElementById('modal-invoice-number').textContent,
                payment_date: document.getElementById('payment-date').value,
                payment_method: document.getElementById('payment-method').value,
                amount: document.getElementById('payment-amount').value,
                reference_number: document.getElementById('reference-number').value,
                notes: document.getElementById('payment-notes').value
            };

            // ここで実際のAPI呼び出しを行う
            console.log('入金記録:', paymentData);
            
            // API呼び出し例
            fetch('/api/payments.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'record_payment',
                    ...paymentData
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('入金記録が完了しました！');
                    
                    // 該当の請求書をUIから更新
                    const invoiceItem = document.querySelector(`input[value="${paymentData.invoice_number}"]`)?.closest('.invoice-item');
                    if (invoiceItem) {
                        invoiceItem.classList.remove('overdue', 'due-soon');
                        invoiceItem.classList.add('paid');
                        invoiceItem.querySelector('.company-name').innerHTML += ' <span class="status-badge status-paid">入金済み</span>';
                        invoiceItem.querySelector('.action-buttons').innerHTML = `
                            <button class="btn btn-outline-success" disabled>
                                <i class="material-icons">check_circle</i> 入金完了
                            </button>
                            <button class="btn btn-outline-primary">領収書発行</button>
                        `;
                    }
                    
                    // モーダルを閉じる
                    bootstrap.Modal.getInstance(document.getElementById('paymentModal')).hide();
                } else {
                    alert('入金記録でエラーが発生しました: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('システムエラーが発生しました');
            });
        }

        // 一括処理確認
        function confirmBulkPayment() {
            const checkedItems = document.querySelectorAll('input[type="checkbox"]:checked');
            const invoiceNumbers = Array.from(checkedItems).map(cb => cb.value);
            
            if (invoiceNumbers.length === 0) {
                alert('処理する請求書を選択してください');
                return;
            }
            
            if (confirm(`選択した${invoiceNumbers.length}件の請求書の満額入金を記録しますか？`)) {
                // 一括処理API呼び出し
                console.log('一括処理:', invoiceNumbers);
                
                fetch('/api/payments.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'bulk_record_payments',
                        invoice_numbers: invoiceNumbers,
                        payment_date: new Date().toISOString().split('T')[0],
                        payment_method: 'bulk_processing'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(`${invoiceNumbers.length}件の入金記録が完了しました！`);
                        location.reload(); // 画面更新
                    } else {
                        alert('一括処理でエラーが発生しました: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('システムエラーが発生しました');
                });
            }
        }

        // チェックボックス変更時の処理
        document.addEventListener('change', function(e) {
            if (e.target.type === 'checkbox') {
                updateBulkActionButton();
