<?php
/**
 * 注文作成画面
 * 
 * 配達日とメニューを選択して注文を作成
 */

session_start();

require_once __DIR__ . '/../classes/AuthManager.php';

$authManager = new AuthManager();

// ログインチェック
if (!$authManager->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// タイムアウトチェック
if ($authManager->checkTimeout()) {
    $authManager->logout();
    header('Location: login.php?timeout=1');
    exit;
}

$user = $authManager->getCurrentUser();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>注文する - Smiley配食</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Material Icons -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    
    <style>
        :root {
            --primary-green: #4CAF50;
            --primary-blue: #2196F3;
            --warning-orange: #FF9800;
            --background-grey: #F5F5F5;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--background-grey);
            padding-bottom: 100px;
        }
        
        /* ヘッダー */
        .app-header {
            background: linear-gradient(135deg, var(--primary-green) 0%, #45a049 100%);
            color: white;
            padding: 15px 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 600px;
            margin: 0 auto;
        }
        
        .back-btn {
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            padding: 5px;
        }
        
        .header-title {
            font-size: 20px;
            font-weight: bold;
        }
        
        /* コンテンツ */
        .content-wrapper {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        
        /* ステップインジケーター */
        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            padding: 0 20px;
        }
        
        .step {
            flex: 1;
            text-align: center;
            position: relative;
        }
        
        .step-number {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #ddd;
            color: #666;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 8px;
            font-weight: bold;
            transition: all 0.3s;
        }
        
        .step.active .step-number {
            background: var(--primary-green);
            color: white;
        }
        
        .step.completed .step-number {
            background: var(--primary-blue);
            color: white;
        }
        
        .step-label {
            font-size: 12px;
            color: #666;
        }
        
        .step.active .step-label {
            color: var(--primary-green);
            font-weight: bold;
        }
        
        /* カードスタイル */
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .card-header {
            background: white;
            border-bottom: 2px solid #f0f0f0;
            padding: 15px 20px;
            font-weight: bold;
            font-size: 18px;
            border-radius: 15px 15px 0 0;
        }
        
        /* 日付選択 */
        .date-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            padding: 20px;
        }
        
        .date-card {
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            padding: 15px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
        }
        
        .date-card:hover {
            border-color: var(--primary-green);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(76, 175, 80, 0.3);
        }
        
        .date-card.selected {
            background: linear-gradient(135deg, var(--primary-green) 0%, #45a049 100%);
            color: white;
            border-color: var(--primary-green);
        }
        
        .date-card.weekend {
            background: #FFF8E1;
            border-color: #FFB74D;
        }
        
        .date-card.weekend:hover {
            border-color: var(--warning-orange);
            box-shadow: 0 4px 12px rgba(255, 152, 0, 0.3);
        }
        
        .date-card.weekend.selected {
            background: linear-gradient(135deg, var(--warning-orange) 0%, #F57C00 100%);
            color: white;
            border-color: var(--warning-orange);
        }
        
        .weekend-badge {
            position: absolute;
            top: 5px;
            right: 5px;
            background: var(--warning-orange);
            color: white;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 10px;
            font-weight: bold;
        }
        
        .date-card.selected .weekend-badge {
            background: rgba(255, 255, 255, 0.3);
        }
        
        .date-day {
            font-size: 14px;
            margin-bottom: 5px;
        }
        
        .date-number {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .date-weekday {
            font-size: 12px;
            opacity: 0.8;
        }
        
        /* メニュー選択 */
        .menu-tabs {
            display: flex;
            border-bottom: 2px solid #f0f0f0;
            margin-bottom: 20px;
        }
        
        .menu-tab {
            flex: 1;
            padding: 15px;
            text-align: center;
            background: white;
            border: none;
            font-size: 16px;
            font-weight: bold;
            color: #666;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .menu-tab.active {
            color: var(--primary-green);
            border-bottom: 3px solid var(--primary-green);
        }
        
        .menu-list {
            padding: 20px;
        }
        
        .menu-item {
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 15px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .menu-item:hover {
            border-color: var(--primary-green);
            box-shadow: 0 4px 12px rgba(76, 175, 80, 0.2);
        }
        
        .menu-item.selected {
            border-color: var(--primary-green);
            background: #f1f8f4;
        }
        
        .menu-name {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 8px;
        }
        
        .menu-price {
            font-size: 16px;
            color: var(--primary-green);
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .menu-subsidy {
            font-size: 13px;
            color: #666;
        }
        
        .menu-note {
            font-size: 12px;
            color: #888;
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px solid #f0f0f0;
        }
        
        /* 数量選択 */
        .quantity-selector {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 20px;
            padding: 20px;
        }
        
        .qty-btn {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            border: 2px solid var(--primary-green);
            background: white;
            color: var(--primary-green);
            font-size: 24px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .qty-btn:hover {
            background: var(--primary-green);
            color: white;
        }
        
        .qty-btn:disabled {
            border-color: #ccc;
            color: #ccc;
            cursor: not-allowed;
        }
        
        .qty-btn:disabled:hover {
            background: white;
            color: #ccc;
        }
        
        .qty-display {
            font-size: 32px;
            font-weight: bold;
            min-width: 60px;
            text-align: center;
        }
        
        /* 確認カード */
        .summary-card {
            background: linear-gradient(135deg, #E3F2FD 0%, #BBDEFB 100%);
            padding: 20px;
            border-radius: 12px;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid rgba(0,0,0,0.1);
        }
        
        .summary-row:last-child {
            border-bottom: none;
            font-size: 20px;
            font-weight: bold;
            color: var(--primary-green);
        }
        
        /* ボタン */
        .btn-container {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            padding: 15px 20px;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
            z-index: 99;
        }
        
        .btn-container-inner {
            max-width: 600px;
            margin: 0 auto;
            display: flex;
            gap: 10px;
        }
        
        .btn-primary-custom {
            background: linear-gradient(135deg, var(--primary-green) 0%, #45a049 100%);
            border: none;
            color: white;
            padding: 15px;
            font-size: 18px;
            font-weight: bold;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(76, 175, 80, 0.4);
            transition: all 0.3s;
            flex: 1;
        }
        
        .btn-primary-custom:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(76, 175, 80, 0.6);
        }
        
        .btn-primary-custom:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .btn-secondary-custom {
            background: #f0f0f0;
            border: none;
            color: #666;
            padding: 15px;
            font-size: 18px;
            font-weight: bold;
            border-radius: 10px;
            flex: 1;
        }
        
        /* ローディング */
        .loading {
            text-align: center;
            padding: 40px 20px;
        }
        
        .spinner-border {
            width: 3rem;
            height: 3rem;
        }
        
        /* 空状態 */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }
        
        .empty-state .material-icons {
            font-size: 80px;
            margin-bottom: 20px;
        }
        
        @media (max-width: 576px) {
            .date-grid {
                grid-template-columns: 1fr;
            }
            
            .step-indicator {
                padding: 0 10px;
            }
            
            .step-label {
                font-size: 11px;
            }
        }
    </style>
</head>
<body>
    <!-- ヘッダー -->
    <div class="app-header">
        <div class="header-content">
            <button class="back-btn" onclick="goBack()">
                <span class="material-icons">arrow_back</span>
            </button>
            <div class="header-title">注文する</div>
            <div style="width: 40px;"></div> <!-- スペーサー -->
        </div>
    </div>
    
    <!-- コンテンツ -->
    <div class="content-wrapper">
        <!-- ステップインジケーター -->
        <div class="step-indicator">
            <div class="step active" id="step1">
                <div class="step-number">1</div>
                <div class="step-label">配達日</div>
            </div>
            <div class="step" id="step2">
                <div class="step-number">2</div>
                <div class="step-label">メニュー</div>
            </div>
            <div class="step" id="step3">
                <div class="step-number">3</div>
                <div class="step-label">確認</div>
            </div>
        </div>
        
        <!-- Step 1: 配達日選択 -->
        <div id="stepContent1" class="step-content">
            <div class="card">
                <div class="card-header">配達日を選択してください</div>
                <div id="dateLoading" class="loading">
                    <div class="spinner-border text-primary"></div>
                    <p class="mt-3">読み込み中...</p>
                </div>
                <div id="dateGrid" class="date-grid" style="display: none;"></div>
            </div>
        </div>
        
        <!-- Step 2: メニュー選択 -->
        <div id="stepContent2" class="step-content" style="display: none;">
            <div class="card">
                <div class="menu-tabs">
                    <button class="menu-tab active" data-tab="daily" onclick="switchMenuTab('daily')">
                        日替わり
                    </button>
                    <button class="menu-tab" data-tab="standard" onclick="switchMenuTab('standard')">
                        定番メニュー
                    </button>
                </div>
                <div id="menuLoading" class="loading">
                    <div class="spinner-border text-primary"></div>
                    <p class="mt-3">メニューを読み込み中...</p>
                </div>
                <div id="menuListDaily" class="menu-list" style="display: none;"></div>
                <div id="menuListStandard" class="menu-list" style="display: none;"></div>
                <div id="menuEmpty" class="empty-state" style="display: none;">
                    <div class="material-icons">restaurant</div>
                    <p>この日のメニューはまだ設定されていません</p>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">数量を選択してください</div>
                <div class="quantity-selector">
                    <button class="qty-btn" onclick="changeQuantity(-1)" id="qtyMinus">-</button>
                    <div class="qty-display" id="qtyDisplay">1</div>
                    <button class="qty-btn" onclick="changeQuantity(1)" id="qtyPlus">+</button>
                </div>
            </div>
        </div>
        
        <!-- Step 3: 確認 -->
        <div id="stepContent3" class="step-content" style="display: none;">
            <div class="card">
                <div class="card-header">注文内容をご確認ください</div>
                <div class="card-body summary-card">
                    <div class="summary-row">
                        <span>配達日:</span>
                        <span id="confirmDate"></span>
                    </div>
                    <div class="summary-row">
                        <span>メニュー:</span>
                        <span id="confirmMenu"></span>
                    </div>
                    <div class="summary-row">
                        <span>数量:</span>
                        <span id="confirmQuantity"></span>
                    </div>
                    <div class="summary-row">
                        <span>小計:</span>
                        <span id="confirmSubtotal"></span>
                    </div>
                    <div class="summary-row">
                        <span>企業補助:</span>
                        <span id="confirmSubsidy"></span>
                    </div>
                    <div class="summary-row">
                        <span>お支払い金額:</span>
                        <span id="confirmTotal"></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- ボタンコンテナ -->
    <div class="btn-container">
        <div class="btn-container-inner">
            <button class="btn-secondary-custom" id="prevBtn" onclick="previousStep()" style="display: none;">
                戻る
            </button>
            <button class="btn-primary-custom" id="nextBtn" onclick="nextStep()" disabled>
                次へ
            </button>
            <button class="btn-primary-custom" id="submitBtn" onclick="submitOrder()" style="display: none;">
                注文を確定する
            </button>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // グローバル変数
        let currentStep = 1;
        let selectedDate = null;
        let selectedMenu = null;
        let quantity = 1;
        let availableDates = [];
        let menusDaily = [];
        let menusStandard = [];
        let currentMenuTab = 'daily';
        
        // ページ読み込み時の処理
        document.addEventListener('DOMContentLoaded', function() {
            loadAvailableDates();
        });
        
        // 注文可能日を読み込み
        async function loadAvailableDates() {
            try {
                const response = await fetch('../api/orders_management.php?action=available_dates');
                const result = await response.json();
                
                if (result.success) {
                    availableDates = result.data.dates;
                    renderDates();
                }
            } catch (error) {
                console.error('Error loading dates:', error);
                alert('日付の読み込みに失敗しました');
            }
        }
        
        // 日付を表示
        function renderDates() {
            const dateGrid = document.getElementById('dateGrid');
            dateGrid.innerHTML = '';
            
            availableDates.forEach(date => {
                const dateCard = document.createElement('div');
                dateCard.className = 'date-card';
                dateCard.onclick = () => selectDate(date.date);
                
                // 週末の場合はクラスを追加
                if (date.is_weekend) {
                    dateCard.classList.add('weekend');
                }
                
                let html = `
                    <div class="date-day">${date.formatted}</div>
                    <div class="date-number">${date.day_of_week}</div>
                `;
                
                // 週末バッジを追加
                if (date.is_weekend) {
                    html = `<div class="weekend-badge">週末</div>` + html;
                }
                
                dateCard.innerHTML = html;
                dateGrid.appendChild(dateCard);
            });
            
            document.getElementById('dateLoading').style.display = 'none';
            document.getElementById('dateGrid').style.display = 'grid';
        }
        
        // 日付を選択
        function selectDate(date) {
            selectedDate = date;
            
            // ビジュアル更新
            document.querySelectorAll('.date-card').forEach(card => {
                card.classList.remove('selected');
            });
            event.target.closest('.date-card').classList.add('selected');
            
            // 次へボタンを有効化
            document.getElementById('nextBtn').disabled = false;
        }
        
        // メニューを読み込み
        async function loadMenus(date) {
            try {
                const response = await fetch(`../api/orders_management.php?action=menus&date=${date}`);
                const result = await response.json();
                
                if (result.success) {
                    menusDaily = result.data.daily || [];
                    menusStandard = result.data.standard || [];
                    renderMenus();
                }
            } catch (error) {
                console.error('Error loading menus:', error);
                alert('メニューの読み込みに失敗しました');
            }
        }
        
        // メニューを表示
        function renderMenus() {
            // 日替わりメニュー
            const dailyList = document.getElementById('menuListDaily');
            dailyList.innerHTML = '';
            
            if (menusDaily.length > 0) {
                menusDaily.forEach(menu => {
                    dailyList.appendChild(createMenuCard(menu));
                });
            }
            
            // 定番メニュー
            const standardList = document.getElementById('menuListStandard');
            standardList.innerHTML = '';
            
            if (menusStandard.length > 0) {
                menusStandard.forEach(menu => {
                    standardList.appendChild(createMenuCard(menu));
                });
            }
            
            // 表示切り替え
            document.getElementById('menuLoading').style.display = 'none';
            
            if (menusDaily.length > 0 || menusStandard.length > 0) {
                switchMenuTab('daily');
            } else {
                document.getElementById('menuEmpty').style.display = 'block';
            }
        }
        
        // メニューカードを作成
        function createMenuCard(menu) {
            const card = document.createElement('div');
            card.className = 'menu-item';
            card.onclick = () => selectMenu(menu);
            
            let html = `
                <div class="menu-name">${menu.product_name}</div>
                <div class="menu-price">¥${Number(menu.unit_price).toLocaleString()}</div>
            `;
            
            if (menu.special_note) {
                html += `<div class="menu-note">${menu.special_note}</div>`;
            }
            
            card.innerHTML = html;
            return card;
        }
        
        // メニューを選択
        function selectMenu(menu) {
            selectedMenu = menu;
            
            // ビジュアル更新
            document.querySelectorAll('.menu-item').forEach(item => {
                item.classList.remove('selected');
            });
            event.target.closest('.menu-item').classList.add('selected');
            
            // 次へボタンを有効化
            document.getElementById('nextBtn').disabled = false;
        }
        
        // メニュータブ切り替え
        function switchMenuTab(tab) {
            currentMenuTab = tab;
            
            // タブボタンの状態更新
            document.querySelectorAll('.menu-tab').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');
            
            // メニュー表示切り替え
            document.getElementById('menuListDaily').style.display = (tab === 'daily') ? 'block' : 'none';
            document.getElementById('menuListStandard').style.display = (tab === 'standard') ? 'block' : 'none';
        }
        
        // 数量変更
        function changeQuantity(delta) {
            quantity = Math.max(1, Math.min(10, quantity + delta));
            document.getElementById('qtyDisplay').textContent = quantity;
            
            // ボタンの有効/無効
            document.getElementById('qtyMinus').disabled = (quantity <= 1);
            document.getElementById('qtyPlus').disabled = (quantity >= 10);
        }
        
        // 次のステップへ
        async function nextStep() {
            if (currentStep === 1) {
                // Step 1 → 2: メニュー読み込み
                currentStep = 2;
                updateStepIndicator();
                showStepContent(2);
                
                document.getElementById('prevBtn').style.display = 'block';
                document.getElementById('nextBtn').disabled = true;
                
                await loadMenus(selectedDate);
                
            } else if (currentStep === 2) {
                // Step 2 → 3: 確認画面
                currentStep = 3;
                updateStepIndicator();
                showStepContent(3);
                renderConfirmation();
                
                document.getElementById('nextBtn').style.display = 'none';
                document.getElementById('submitBtn').style.display = 'block';
            }
        }
        
        // 前のステップへ
        function previousStep() {
            if (currentStep === 2) {
                currentStep = 1;
                updateStepIndicator();
                showStepContent(1);
                
                document.getElementById('prevBtn').style.display = 'none';
                document.getElementById('nextBtn').disabled = selectedDate === null;
                
            } else if (currentStep === 3) {
                currentStep = 2;
                updateStepIndicator();
                showStepContent(2);
                
                document.getElementById('nextBtn').style.display = 'block';
                document.getElementById('nextBtn').disabled = selectedMenu === null;
                document.getElementById('submitBtn').style.display = 'none';
            }
        }
        
        // ステップインジケーター更新
        function updateStepIndicator() {
            for (let i = 1; i <= 3; i++) {
                const step = document.getElementById(`step${i}`);
                step.classList.remove('active', 'completed');
                
                if (i < currentStep) {
                    step.classList.add('completed');
                } else if (i === currentStep) {
                    step.classList.add('active');
                }
            }
        }
        
        // ステップコンテンツ表示
        function showStepContent(step) {
            for (let i = 1; i <= 3; i++) {
                const content = document.getElementById(`stepContent${i}`);
                content.style.display = (i === step) ? 'block' : 'none';
            }
        }
        
        // 確認画面を表示
        function renderConfirmation() {
            const dateObj = availableDates.find(d => d.date === selectedDate);
            const dateText = dateObj ? `${dateObj.formatted}(${dateObj.day_of_week})` : selectedDate;
            
            const subtotal = selectedMenu.unit_price * quantity;
            const subsidy = 0; // TODO: 企業補助額を取得
            const total = Math.max(0, subtotal - subsidy);
            
            document.getElementById('confirmDate').textContent = dateText;
            document.getElementById('confirmMenu').textContent = selectedMenu.product_name;
            document.getElementById('confirmQuantity').textContent = `${quantity}個`;
            document.getElementById('confirmSubtotal').textContent = `¥${subtotal.toLocaleString()}`;
            document.getElementById('confirmSubsidy').textContent = `¥${subsidy.toLocaleString()}`;
            document.getElementById('confirmTotal').textContent = `¥${total.toLocaleString()}`;
        }
        
        // 注文を確定
        async function submitOrder() {
            if (!confirm('この内容で注文しますか？')) {
                return;
            }
            
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            submitBtn.textContent = '注文中...';
            
            try {
                const orderData = {
                    delivery_date: selectedDate,
                    product_id: selectedMenu.id,
                    quantity: quantity,
                    notes: ''
                };
                
                const response = await fetch('../api/orders_management.php?action=create_order', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(orderData)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('注文を受け付けました！');
                    window.location.href = 'order_dashboard.php';
                } else {
                    alert('エラー: ' + result.error);
                    submitBtn.disabled = false;
                    submitBtn.textContent = '注文を確定する';
                }
                
            } catch (error) {
                console.error('Order submission error:', error);
                alert('注文の送信に失敗しました');
                submitBtn.disabled = false;
                submitBtn.textContent = '注文を確定する';
            }
        }
        
        // 戻る
        function goBack() {
            if (currentStep === 1) {
                window.location.href = 'order_dashboard.php';
            } else {
                previousStep();
            }
        }
    </script>
</body>
</html>
