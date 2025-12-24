<?php
/**
 * 週替わりメニュー設定画面
 * 
 * 週単位でメニューを設定
 */

session_start();

require_once __DIR__ . '/../../classes/AuthManager.php';

$authManager = new AuthManager();

if (!$authManager->isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

$authManager->requireAdmin('../order_dashboard.php');
$user = $authManager->getCurrentUser();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>週替わりメニュー設定 - Smiley配食</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    
    <style>
        :root {
            --primary-green: #4CAF50;
            --admin-blue: #1976D2;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
        }
        
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 250px;
            height: 100vh;
            background: linear-gradient(180deg, var(--admin-blue) 0%, #1565C0 100%);
            color: white;
            padding: 20px;
            overflow-y: auto;
        }
        
        .sidebar-header {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.2);
        }
        
        .sidebar-title {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .sidebar-subtitle {
            font-size: 12px;
            opacity: 0.8;
        }
        
        .sidebar-nav {
            list-style: none;
            padding: 0;
        }
        
        .sidebar-nav-item {
            margin-bottom: 5px;
        }
        
        .sidebar-nav-link {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s;
        }
        
        .sidebar-nav-link:hover {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        
        .sidebar-nav-link.active {
            background: rgba(255,255,255,0.2);
        }
        
        .sidebar-nav-link .material-icons {
            margin-right: 10px;
        }
        
        .main-content {
            margin-left: 250px;
            padding: 30px;
        }
        
        .page-header {
            margin-bottom: 30px;
        }
        
        .page-title {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        /* 週カード */
        .week-card {
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s;
        }
        
        .week-card:hover {
            border-color: var(--primary-green);
            box-shadow: 0 4px 12px rgba(76, 175, 80, 0.2);
        }
        
        .week-card.current {
            border-color: var(--primary-green);
            background: #E8F5E9;
        }
        
        .week-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .week-title {
            font-size: 20px;
            font-weight: bold;
        }
        
        .week-badge {
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .badge-current {
            background: var(--primary-green);
            color: white;
        }
        
        .badge-future {
            background: #2196F3;
            color: white;
        }
        
        .badge-past {
            background: #9E9E9E;
            color: white;
        }
        
        .week-period {
            color: #666;
            margin-bottom: 10px;
        }
        
        .week-menu {
            font-size: 18px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        
        .no-menu {
            color: #999;
            font-style: italic;
        }
        
        .week-actions {
            display: flex;
            gap: 10px;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 200px;
            }
            
            .main-content {
                margin-left: 200px;
                padding: 20px 15px;
            }
        }
    </style>
</head>
<body>
    <!-- サイドバー -->
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-title">🍱 Smiley配食</div>
            <div class="sidebar-subtitle">管理画面</div>
        </div>
        
        <ul class="sidebar-nav">
            <li class="sidebar-nav-item">
                <a href="menu_management.php" class="sidebar-nav-link">
                    <span class="material-icons">restaurant_menu</span>
                    メニュー管理
                </a>
            </li>
            <li class="sidebar-nav-item">
                <a href="weekly_menu_settings.php" class="sidebar-nav-link active">
                    <span class="material-icons">event</span>
                    週替わり設定
                </a>
            </li>
            <li class="sidebar-nav-item">
                <a href="orders_list.php" class="sidebar-nav-link">
                    <span class="material-icons">list_alt</span>
                    注文一覧
                </a>
            </li>
            <li class="sidebar-nav-item">
                <a href="delivery_schedule.php" class="sidebar-nav-link">
                    <span class="material-icons">local_shipping</span>
                    配達スケジュール
                </a>
            </li>
            <li class="sidebar-nav-item">
                <a href="../order_dashboard.php" class="sidebar-nav-link">
                    <span class="material-icons">home</span>
                    ユーザー画面へ
                </a>
            </li>
        </ul>
    </div>
    
    <!-- メインコンテンツ -->
    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">週替わりメニュー設定</h1>
            <p class="text-muted">週単位でメニューを設定（毎週月曜日〜日曜日）</p>
        </div>
        
        <!-- 説明カード -->
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">📌 週替わりメニューについて</h5>
                <ul class="mb-0">
                    <li>週単位（月曜日〜日曜日）で同じメニューが提供されます</li>
                    <li>毎週メニューを変更する必要があります</li>
                    <li>設定は週の開始前に行ってください</li>
                </ul>
            </div>
        </div>
        
        <!-- 週一覧 -->
        <div id="weeksList">
            <div class="text-center py-5">
                <div class="spinner-border text-primary"></div>
                <p class="mt-3">読み込み中...</p>
            </div>
        </div>
    </div>
    
    <!-- メニュー選択モーダル -->
    <div class="modal fade" id="menuSelectModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">週替わりメニューを選択</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p><strong>期間:</strong> <span id="selectedWeekPeriod"></span></p>
                    <input type="hidden" id="selectedWeekStart">
                    <div class="mb-3">
                        <label class="form-label">メニューを選択 *</label>
                        <select class="form-select" id="menuSelect" size="8">
                            <!-- JavaScript で生成 -->
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">特記事項（任意）</label>
                        <textarea class="form-control" id="specialNote" rows="3" placeholder="例: 今週のおすすめ！"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger" onclick="removeMenu()">削除</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
                    <button type="button" class="btn btn-primary" onclick="saveMenu()">設定</button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        let menus = [];
        let weeklyMenus = {};
        let selectedWeekStart = null;
        
        document.addEventListener('DOMContentLoaded', function() {
            loadMenus();
        });
        
        // メニュー一覧を読み込み
        async function loadMenus() {
            try {
                const response = await fetch('../../api/admin/menu_management.php?action=list');
                const result = await response.json();
                
                if (result.success) {
                    menus = result.data.filter(m => m.category_code === 'DAILY' && m.is_active);
                    await loadWeeklyMenus();
                    renderWeeks();
                }
            } catch (error) {
                console.error('Error loading menus:', error);
            }
        }
        
        // 週替わりメニュー設定を読み込み
        async function loadWeeklyMenus() {
            try {
                const response = await fetch('../../api/admin/weekly_menu_api.php?action=list');
                const result = await response.json();
                
                if (result.success) {
                    weeklyMenus = {};
                    result.data.forEach(item => {
                        weeklyMenus[item.week_start_date] = item;
                    });
                }
            } catch (error) {
                console.error('Error loading weekly menus:', error);
            }
        }
        
        // 週一覧を表示
        function renderWeeks() {
            const container = document.getElementById('weeksList');
            container.innerHTML = '';
            
            // 今週から8週間分表示
            const today = new Date();
            const currentMonday = getMonday(today);
            
            for (let i = -1; i < 7; i++) {
                const weekStart = new Date(currentMonday);
                weekStart.setDate(weekStart.getDate() + (i * 7));
                
                const weekEnd = new Date(weekStart);
                weekEnd.setDate(weekEnd.getDate() + 6);
                
                const weekStartStr = formatDate(weekStart);
                const menu = weeklyMenus[weekStartStr];
                
                const card = createWeekCard(weekStart, weekEnd, menu);
                container.appendChild(card);
            }
        }
        
        // 週カードを作成
        function createWeekCard(weekStart, weekEnd, menu) {
            const card = document.createElement('div');
            card.className = 'week-card';
            
            const today = new Date();
            const currentMonday = getMonday(today);
            
            let badge = '';
            if (formatDate(weekStart) === formatDate(currentMonday)) {
                badge = '<span class="week-badge badge-current">今週</span>';
                card.classList.add('current');
            } else if (weekStart > currentMonday) {
                badge = '<span class="week-badge badge-future">今後</span>';
            } else {
                badge = '<span class="week-badge badge-past">過去</span>';
            }
            
            const weekStartStr = formatDate(weekStart);
            const periodText = `${weekStart.getMonth() + 1}/${weekStart.getDate()} 〜 ${weekEnd.getMonth() + 1}/${weekEnd.getDate()}`;
            
            card.innerHTML = `
                <div class="week-header">
                    <div class="week-title">${weekStart.getMonth() + 1}月第${getWeekOfMonth(weekStart)}週</div>
                    ${badge}
                </div>
                <div class="week-period">${periodText}</div>
                ${menu ? 
                    `<div class="week-menu">
                        ${menu.product_name}
                        ${menu.special_note ? `<div style="font-size: 14px; color: #666; margin-top: 5px;">${menu.special_note}</div>` : ''}
                    </div>` :
                    `<div class="week-menu no-menu">未設定</div>`
                }
                <div class="week-actions">
                    <button class="btn btn-primary" onclick="openMenuModal('${weekStartStr}', '${periodText}')">
                        ${menu ? '変更' : '設定'}
                    </button>
                    ${menu ? 
                        `<button class="btn btn-outline-secondary" onclick="copyToNextWeek('${weekStartStr}')">
                            次週にコピー
                        </button>` : ''
                    }
                </div>
            `;
            
            return card;
        }
        
        // メニュー選択モーダルを開く
        function openMenuModal(weekStartStr, periodText) {
            selectedWeekStart = weekStartStr;
            
            document.getElementById('selectedWeekPeriod').textContent = periodText;
            document.getElementById('selectedWeekStart').value = weekStartStr;
            
            const select = document.getElementById('menuSelect');
            select.innerHTML = '';
            
            menus.forEach(menu => {
                const option = document.createElement('option');
                option.value = menu.id;
                option.textContent = `${menu.product_name} (¥${Number(menu.unit_price).toLocaleString()})`;
                
                const weekMenu = weeklyMenus[weekStartStr];
                if (weekMenu && weekMenu.product_id === menu.id) {
                    option.selected = true;
                    document.getElementById('specialNote').value = weekMenu.special_note || '';
                }
                
                select.appendChild(option);
            });
            
            new bootstrap.Modal(document.getElementById('menuSelectModal')).show();
        }
        
        // メニューを保存
        async function saveMenu() {
            const productId = document.getElementById('menuSelect').value;
            const specialNote = document.getElementById('specialNote').value;
            
            if (!productId) {
                alert('メニューを選択してください');
                return;
            }
            
            try {
                const response = await fetch('../../api/admin/weekly_menu_api.php?action=set', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        week_start_date: selectedWeekStart,
                        product_id: parseInt(productId),
                        special_note: specialNote
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    bootstrap.Modal.getInstance(document.getElementById('menuSelectModal')).hide();
                    await loadWeeklyMenus();
                    renderWeeks();
                    alert('週替わりメニューを設定しました');
                } else {
                    alert('エラー: ' + result.error);
                }
            } catch (error) {
                console.error('Error saving menu:', error);
                alert('保存に失敗しました');
            }
        }
        
        // メニューを削除
        async function removeMenu() {
            if (!confirm('この週のメニューを削除しますか？')) {
                return;
            }
            
            try {
                const response = await fetch('../../api/admin/weekly_menu_api.php?action=remove', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        week_start_date: selectedWeekStart
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    bootstrap.Modal.getInstance(document.getElementById('menuSelectModal')).hide();
                    await loadWeeklyMenus();
                    renderWeeks();
                    alert('週替わりメニューを削除しました');
                } else {
                    alert('エラー: ' + result.error);
                }
            } catch (error) {
                console.error('Error removing menu:', error);
                alert('削除に失敗しました');
            }
        }
        
        // 次週にコピー
        async function copyToNextWeek(weekStartStr) {
            const currentMenu = weeklyMenus[weekStartStr];
            if (!currentMenu) return;
            
            const weekStart = new Date(weekStartStr);
            weekStart.setDate(weekStart.getDate() + 7);
            const nextWeekStr = formatDate(weekStart);
            
            if (!confirm(`${weekStart.getMonth() + 1}月第${getWeekOfMonth(weekStart)}週にコピーしますか？`)) {
                return;
            }
            
            try {
                const response = await fetch('../../api/admin/weekly_menu_api.php?action=set', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        week_start_date: nextWeekStr,
                        product_id: currentMenu.product_id,
                        special_note: currentMenu.special_note
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    await loadWeeklyMenus();
                    renderWeeks();
                    alert('次週にコピーしました');
                } else {
                    alert('エラー: ' + result.error);
                }
            } catch (error) {
                console.error('Error copying menu:', error);
                alert('コピーに失敗しました');
            }
        }
        
        // ユーティリティ関数
        function getMonday(date) {
            const d = new Date(date);
            const day = d.getDay();
            const diff = d.getDate() - day + (day === 0 ? -6 : 1);
            return new Date(d.setDate(diff));
        }
        
        function formatDate(date) {
            const y = date.getFullYear();
            const m = String(date.getMonth() + 1).padStart(2, '0');
            const d = String(date.getDate()).padStart(2, '0');
            return `${y}-${m}-${d}`;
        }
        
        function getWeekOfMonth(date) {
            const firstDay = new Date(date.getFullYear(), date.getMonth(), 1);
            const firstMonday = getMonday(firstDay);
            if (firstMonday.getMonth() !== date.getMonth()) {
                firstMonday.setDate(firstMonday.getDate() + 7);
            }
            const diffDays = Math.floor((date - firstMonday) / (1000 * 60 * 60 * 24));
            return Math.floor(diffDays / 7) + 1;
        }
    </script>
</body>
</html>
