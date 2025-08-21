<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>利用者管理テスト - Smiley配食事業システム</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <h2>利用者管理テスト</h2>
        
        <div class="alert alert-info">
            <h5>デバッグ情報</h5>
            <div id="debugInfo">読み込み中...</div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h5>利用者一覧</h5>
            </div>
            <div class="card-body">
                <div id="loadingMessage" class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">読み込み中...</span>
                    </div>
                    <p class="mt-2">データを読み込んでいます...</p>
                </div>
                
                <div id="errorMessage" class="alert alert-danger" style="display: none;">
                    エラーが発生しました
                </div>
                
                <div id="userList" style="display: none;">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>利用者コード</th>
                                <th>利用者名</th>
                                <th>企業名</th>
                                <th>部署</th>
                                <th>注文数</th>
                            </tr>
                        </thead>
                        <tbody id="userTableBody">
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        console.log('JavaScript開始');
        
        // シンプルなAPI呼び出しテスト
        async function loadUsers() {
            try {
                console.log('API呼び出し開始');
                
                const response = await fetch('../api/users.php');
                console.log('レスポンス取得:', response.status);
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                const data = await response.json();
                console.log('データ取得成功:', data);
                
                // デバッグ情報表示
                document.getElementById('debugInfo').innerHTML = `
                    <strong>API呼び出し成功</strong><br>
                    総利用者数: ${data.users ? data.users.length : 0}<br>
                    統計情報: ${JSON.stringify(data.stats || {}, null, 2)}<br>
                    最初の利用者: ${data.users && data.users[0] ? data.users[0].user_name : 'なし'}
                `;
                
                // テーブル表示
                if (data.users && data.users.length > 0) {
                    displayUsers(data.users);
                } else {
                    document.getElementById('errorMessage').innerHTML = 'データが見つかりません';
                    document.getElementById('errorMessage').style.display = 'block';
                }
                
            } catch (error) {
                console.error('エラー発生:', error);
                document.getElementById('debugInfo').innerHTML = `
                    <strong>エラー発生</strong><br>
                    ${error.message}
                `;
                document.getElementById('errorMessage').innerHTML = `エラー: ${error.message}`;
                document.getElementById('errorMessage').style.display = 'block';
            } finally {
                document.getElementById('loadingMessage').style.display = 'none';
            }
        }
        
        function displayUsers(users) {
            console.log('利用者表示開始:', users.length + '件');
            
            const tbody = document.getElementById('userTableBody');
            tbody.innerHTML = '';
            
            users.forEach((user, index) => {
                console.log(`利用者${index + 1}:`, user.user_name);
                
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${user.id}</td>
                    <td>${user.user_code}</td>
                    <td>${user.user_name}</td>
                    <td>${user.company_name_display || user.company_name_from_table || user.company_name || '-'}</td>
                    <td>${user.department_name_display || user.department_name || user.department || '-'}</td>
                    <td>${user.total_orders || 0}</td>
                `;
                tbody.appendChild(row);
            });
            
            document.getElementById('userList').style.display = 'block';
            console.log('利用者表示完了');
        }
        
        // ページ読み込み完了後に実行
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOMContentLoaded');
            loadUsers();
        });
        
        console.log('JavaScript設定完了');
    </script>
</body>
</html>
