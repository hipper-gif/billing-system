<?php
/**
 * ログイン画面
 * 
 * 利用者がログインするための画面
 */

session_start();

// 既にログインしている場合は注文画面へリダイレクト
require_once __DIR__ . '/../classes/AuthManager.php';
$authManager = new AuthManager();

if ($authManager->isLoggedIn()) {
    header('Location: /pages/order_dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>ログイン - Smiley配食</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Material Icons -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-container {
            max-width: 450px;
            width: 100%;
        }
        
        .login-card {
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            border: none;
            overflow: hidden;
        }
        
        .login-header {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        
        .login-header h1 {
            font-size: 28px;
            margin: 0 0 10px 0;
            font-weight: bold;
        }
        
        .login-header p {
            margin: 0;
            font-size: 16px;
            opacity: 0.9;
        }
        
        .login-body {
            padding: 40px 30px;
            background: white;
        }
        
        .form-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            font-size: 16px;
        }
        
        .form-control {
            height: 55px;
            font-size: 18px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 15px;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: #4CAF50;
            box-shadow: 0 0 0 0.2rem rgba(76, 175, 80, 0.25);
        }
        
        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #757575;
            user-select: none;
        }
        
        .btn-login {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            border: none;
            height: 60px;
            font-size: 20px;
            font-weight: bold;
            border-radius: 10px;
            margin-top: 10px;
            box-shadow: 0 4px 15px rgba(76, 175, 80, 0.4);
            transition: all 0.3s;
            color: white;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(76, 175, 80, 0.6);
            background: linear-gradient(135deg, #45a049 0%, #4CAF50 100%);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .form-check {
            margin-top: 15px;
        }
        
        .form-check-input {
            width: 20px;
            height: 20px;
            margin-top: 0.15em;
        }
        
        .form-check-label {
            font-size: 16px;
            margin-left: 8px;
        }
        
        .alert {
            border-radius: 10px;
            border: none;
            font-size: 16px;
        }
        
        .loading {
            display: none;
            text-align: center;
            padding: 20px;
        }
        
        .spinner-border {
            width: 3rem;
            height: 3rem;
        }
        
        .help-link {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
        }
        
        .help-link a {
            color: #666;
            text-decoration: none;
            font-size: 14px;
        }
        
        .help-link a:hover {
            color: #4CAF50;
            text-decoration: underline;
        }
        
        .new-user-link {
            text-align: center;
            margin-top: 20px;
            padding: 15px;
            background: #f5f5f5;
            border-radius: 10px;
        }
        
        .new-user-link p {
            margin: 0 0 10px 0;
            color: #666;
            font-size: 14px;
        }
        
        .new-user-link a {
            color: #4CAF50;
            font-weight: bold;
            text-decoration: none;
        }
        
        @media (max-width: 576px) {
            .login-body {
                padding: 30px 20px;
            }
            
            .login-header {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card card">
            <div class="login-header">
                <h1>🍱 Smiley配食</h1>
                <p>ログイン</p>
            </div>
            
            <div class="login-body">
                <div id="errorAlert" class="alert alert-danger" style="display: none;"></div>
                
                <form id="loginForm">
                    <div class="mb-3">
                        <label for="userCode" class="form-label">利用者コード</label>
                        <input type="text" class="form-control" id="userCode" name="user_code" required 
                               placeholder="例: ABC0001" autocomplete="username">
                        <small class="text-danger" id="userCodeError"></small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">パスワード</label>
                        <div style="position: relative;">
                            <input type="password" class="form-control" id="password" name="password" required 
                                   placeholder="パスワードを入力" autocomplete="current-password">
                            <span class="material-icons password-toggle" onclick="togglePassword()">visibility</span>
                        </div>
                        <small class="text-danger" id="passwordError"></small>
                    </div>
                    
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="rememberMe" name="remember_me">
                        <label class="form-check-label" for="rememberMe">
                            ログイン状態を保持する
                        </label>
                    </div>
                    
                    <button type="submit" class="btn btn-login w-100">
                        <span class="material-icons" style="vertical-align: middle;">login</span>
                        ログイン
                    </button>
                </form>
                
                <!-- ローディング -->
                <div class="loading" id="loadingSpinner">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">処理中...</span>
                    </div>
                    <p class="mt-3">ログイン中...</p>
                </div>
                
                <div class="help-link">
                    <a href="#" onclick="alert('パスワードを忘れた場合は、Smiley配食サポートまでお電話ください。\\n電話: 0120-XXX-XXX\\n受付時間: 平日 9:00-17:00'); return false;">
                        <span class="material-icons" style="vertical-align: middle; font-size: 18px;">help_outline</span>
                        パスワードを忘れた方
                    </a>
                </div>
                
                <div class="new-user-link">
                    <p>初めてご利用の方は、企業から受け取ったQRコードを読み取って登録してください。</p>
                    <a href="#" onclick="alert('QRコードは企業の担当者からお受け取りください。'); return false;">
                        <span class="material-icons" style="vertical-align: middle; font-size: 18px;">qr_code_2</span>
                        QRコードで新規登録
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // パスワード表示/非表示切り替え
        function togglePassword() {
            const field = document.getElementById('password');
            const icon = document.querySelector('.password-toggle');
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.textContent = 'visibility_off';
            } else {
                field.type = 'password';
                icon.textContent = 'visibility';
            }
        }
        
        // フォーム送信処理
        document.getElementById('loginForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            // エラーメッセージクリア
            document.getElementById('errorAlert').style.display = 'none';
            document.getElementById('userCodeError').textContent = '';
            document.getElementById('passwordError').textContent = '';
            
            // フォームデータ取得
            const formData = new FormData(this);
            const data = {
                user_code: formData.get('user_code'),
                password: formData.get('password'),
                remember_me: formData.get('remember_me') === 'on'
            };
            
            // ローディング表示
            document.getElementById('loginForm').style.display = 'none';
            document.getElementById('loadingSpinner').style.display = 'block';
            
            try {
                // API呼び出し
                const response = await fetch('../api/auth.php?action=login', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // ログイン成功 - 注文画面へリダイレクト
                    window.location.href = '/pages/order_dashboard.php';
                } else {
                    // エラー表示
                    if (result.errors) {
                        // フィールドごとのエラー
                        if (result.errors.user_code) {
                            document.getElementById('userCodeError').textContent = result.errors.user_code;
                        }
                        if (result.errors.password) {
                            document.getElementById('passwordError').textContent = result.errors.password;
                        }
                    } else if (result.error) {
                        // 全体エラー
                        const errorAlert = document.getElementById('errorAlert');
                        errorAlert.textContent = result.error;
                        errorAlert.style.display = 'block';
                    }
                    
                    document.getElementById('loginForm').style.display = 'block';
                    document.getElementById('loadingSpinner').style.display = 'none';
                }
                
            } catch (error) {
                console.error('Error:', error);
                const errorAlert = document.getElementById('errorAlert');
                errorAlert.textContent = '通信エラーが発生しました。もう一度お試しください。';
                errorAlert.style.display = 'block';
                
                document.getElementById('loginForm').style.display = 'block';
                document.getElementById('loadingSpinner').style.display = 'none';
            }
        });
        
        // Enterキーでログイン
        document.getElementById('password').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.getElementById('loginForm').dispatchEvent(new Event('submit'));
            }
        });
    </script>
</body>
</html>
