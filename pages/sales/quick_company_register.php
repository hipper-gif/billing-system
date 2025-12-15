<?php
session_start();

// 認証チェック
$isAuthenticated = isset($_SESSION['sales_staff_authenticated']) && $_SESSION['sales_staff_authenticated'] === true;
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>企業クイック登録 - Smiley配食</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Material Icons -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .main-container {
            max-width: 500px;
            margin: 0 auto;
        }
        
        .card {
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            border: none;
        }
        
        .card-header {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 25px;
            text-align: center;
        }
        
        .card-header h1 {
            font-size: 24px;
            margin: 0;
            font-weight: bold;
        }
        
        .card-body {
            padding: 30px;
        }
        
        .form-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            font-size: 16px;
        }
        
        .form-control, .form-select {
            height: 50px;
            font-size: 16px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 12px;
            transition: all 0.3s;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #4CAF50;
            box-shadow: 0 0 0 0.2rem rgba(76, 175, 80, 0.25);
        }
        
        textarea.form-control {
            height: auto;
            min-height: 100px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            border: none;
            height: 60px;
            font-size: 20px;
            font-weight: bold;
            border-radius: 10px;
            margin-top: 20px;
            box-shadow: 0 4px 15px rgba(76, 175, 80, 0.4);
            transition: all 0.3s;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(76, 175, 80, 0.6);
        }
        
        .btn-primary:active {
            transform: translateY(0);
        }
        
        .qr-result-card {
            display: none;
            margin-top: 20px;
            border-radius: 15px;
            overflow: hidden;
        }
        
        .qr-result-card .card-header {
            background: linear-gradient(135deg, #2196F3 0%, #1976D2 100%);
        }
        
        #qrCodeImage {
            max-width: 300px;
            margin: 20px auto;
            display: block;
            border: 5px solid #4CAF50;
            border-radius: 10px;
        }
        
        .url-box {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 10px;
            word-break: break-all;
            font-size: 14px;
            margin: 15px 0;
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
        
        .alert {
            border-radius: 10px;
            border: none;
        }
        
        .form-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .form-section-title {
            font-size: 18px;
            font-weight: bold;
            color: #4CAF50;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }
        
        .form-section-title .material-icons {
            margin-right: 10px;
        }
    </style>
</head>
<body>
    <div class="main-container">
        <!-- 認証カード（未認証時のみ表示） -->
        <div class="card" id="authCard" style="display: <?php echo $isAuthenticated ? 'none' : 'block'; ?>">
            <div class="card-header">
                <h1>🔒 営業スタッフ認証</h1>
                <p style="margin: 10px 0 0 0; font-size: 14px;">パスワードを入力してください</p>
            </div>

            <div class="card-body">
                <div class="alert alert-info">
                    <strong>営業スタッフ専用</strong><br>
                    企業登録を行うには、営業スタッフ用のパスワードが必要です
                </div>

                <form id="authForm">
                    <div class="mb-3">
                        <label for="staffPassword" class="form-label">営業スタッフパスワード *</label>
                        <div style="position: relative;">
                            <input type="password" class="form-control" id="staffPassword" name="password" required placeholder="パスワードを入力">
                            <span class="material-icons password-toggle" onclick="toggleAuthPassword()" style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #757575;">visibility</span>
                        </div>
                        <small class="text-danger" id="authError"></small>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <span class="material-icons" style="vertical-align: middle;">lock_open</span>
                        認証する
                    </button>
                </form>

                <div class="loading" id="authLoadingSpinner">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">認証中...</span>
                    </div>
                    <p class="mt-3">認証処理中...</p>
                </div>
            </div>
        </div>

        <!-- 企業登録カード（認証後に表示） -->
        <div class="card" id="registerCard" style="display: <?php echo $isAuthenticated ? 'block' : 'none'; ?>">
            <div class="card-header">
                <h1>🚀 企業クイック登録</h1>
                <p style="margin: 10px 0 0 0; font-size: 14px;">営業先でその場で登録</p>
            </div>

            <div class="card-body">
                <form id="companyRegisterForm">
                    <!-- 基本情報 -->
                    <div class="form-section">
                        <div class="form-section-title">
                            <span class="material-icons">business</span>
                            基本情報
                        </div>
                        
                        <div class="mb-3">
                            <label for="companyName" class="form-label">企業名 *</label>
                            <input type="text" class="form-control" id="companyName" name="company_name" required placeholder="例: 株式会社サンプル">
                        </div>
                        
                        <div class="mb-3">
                            <label for="companyAddress" class="form-label">住所 *</label>
                            <textarea class="form-control" id="companyAddress" name="company_address" required placeholder="例: 東京都○○区○○ 1-2-3"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="contactPerson" class="form-label">担当者名 *</label>
                            <input type="text" class="form-control" id="contactPerson" name="contact_person" required placeholder="例: 山田　太郎">
                        </div>
                        
                        <div class="mb-3">
                            <label for="phone" class="form-label">電話番号 *</label>
                            <input type="tel" class="form-control" id="phone" name="phone" required placeholder="例: 03-1234-5678">
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">メールアドレス</label>
                            <input type="email" class="form-control" id="email" name="email" placeholder="例: info@example.com">
                        </div>
                    </div>
                    
                    <!-- 料金設定 -->
                    <div class="form-section">
                        <div class="form-section-title">
                            <span class="material-icons">payments</span>
                            料金設定
                        </div>
                        
                        <div class="mb-3">
                            <label for="subsidyAmount" class="form-label">企業補助額（円/食）</label>
                            <input type="number" class="form-control" id="subsidyAmount" name="subsidy_amount" min="0" value="300" placeholder="例: 300">
                        </div>
                        
                        <div class="mb-3">
                            <label for="paymentMethod" class="form-label">支払方法</label>
                            <select class="form-select" id="paymentMethod" name="payment_method">
                                <option value="company_bulk">企業一括払い</option>
                                <option value="individual">個人支払い</option>
                                <option value="mixed">混合（企業補助+個人負担）</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <strong>📱 登録後すぐに：</strong><br>
                        QRコードが発行されます。社員の方々にQRコードを見せて、その場で登録してもらえます！
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100">
                        <span class="material-icons" style="vertical-align: middle;">add_business</span>
                        企業を登録してQRコード発行
                    </button>
                </form>
                
                <!-- ローディング -->
                <div class="loading" id="loadingSpinner">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">処理中...</span>
                    </div>
                    <p class="mt-3">登録処理中...</p>
                </div>
            </div>
        </div>
        
        <!-- QRコード表示カード -->
        <div class="card qr-result-card" id="qrResultCard">
            <div class="card-header">
                <h2 style="font-size: 20px; margin: 0;">✅ 登録完了！</h2>
            </div>
            <div class="card-body text-center">
                <h3 id="registeredCompanyName" style="color: #4CAF50; font-size: 24px;"></h3>
                <p>企業コード: <strong id="companyCode"></strong></p>
                
                <div class="alert alert-success">
                    <strong>🎉 QRコードが発行されました！</strong><br>
                    このQRコードを社員の皆さんに見せてください
                </div>
                
                <img id="qrCodeImage" src="" alt="QRコード">
                
                <div class="url-box">
                    <small>登録URL:</small><br>
                    <span id="signupUrl"></span>
                </div>
                
                <button class="btn btn-primary w-100 mb-2" onclick="window.print()">
                    <span class="material-icons" style="vertical-align: middle;">print</span>
                    印刷する
                </button>
                
                <button class="btn btn-outline-primary w-100" onclick="location.reload()">
                    <span class="material-icons" style="vertical-align: middle;">add</span>
                    続けて別の企業を登録
                </button>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // パスワード表示/非表示切り替え（認証用）
        function toggleAuthPassword() {
            const field = document.getElementById('staffPassword');
            const icon = field.nextElementSibling;

            if (field.type === 'password') {
                field.type = 'text';
                icon.textContent = 'visibility_off';
            } else {
                field.type = 'password';
                icon.textContent = 'visibility';
            }
        }

        // 認証フォーム送信処理
        document.getElementById('authForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            // エラーメッセージクリア
            document.getElementById('authError').textContent = '';

            // フォームデータ取得
            const formData = new FormData(this);
            const data = Object.fromEntries(formData.entries());

            // ローディング表示
            document.getElementById('authForm').style.display = 'none';
            document.getElementById('authLoadingSpinner').style.display = 'block';

            try {
                // API呼び出し
                const response = await fetch('../../api/sales/authenticate.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (result.success) {
                    // 認証成功：企業登録カードを表示
                    document.getElementById('authCard').style.display = 'none';
                    document.getElementById('registerCard').style.display = 'block';
                    document.getElementById('registerCard').scrollIntoView({ behavior: 'smooth' });
                } else {
                    // 認証失敗
                    document.getElementById('authError').textContent = result.error || '認証に失敗しました';
                    document.getElementById('authForm').style.display = 'block';
                    document.getElementById('authLoadingSpinner').style.display = 'none';
                }

            } catch (error) {
                console.error('Error:', error);
                alert('通信エラーが発生しました');
                document.getElementById('authForm').style.display = 'block';
                document.getElementById('authLoadingSpinner').style.display = 'none';
            }
        });

        // フォーム送信処理
        document.getElementById('companyRegisterForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            // フォームデータ取得
            const formData = new FormData(this);
            const data = Object.fromEntries(formData.entries());
            
            // ローディング表示
            document.getElementById('companyRegisterForm').style.display = 'none';
            document.getElementById('loadingSpinner').style.display = 'block';
            
            try {
                // API呼び出し
                const response = await fetch('../../api/sales/quick_register_company.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();

                if (result.success) {
                    // 成功時の処理
                    displayQRCode(result.data);
                } else {
                    // エラー表示（詳細情報を含む）
                    let errorMessage = result.error || 'エラーが発生しました';

                    // バリデーションエラーがある場合
                    if (result.errors) {
                        errorMessage += '\n\n【詳細】\n';
                        for (const [field, message] of Object.entries(result.errors)) {
                            errorMessage += `- ${field}: ${message}\n`;
                        }
                    }

                    // デバッグ情報がある場合
                    if (result.debug) {
                        errorMessage += '\n【デバッグ情報】\n' + result.debug;
                    }

                    console.error('Registration error:', result);
                    console.error('Error details:', JSON.stringify(result, null, 2));
                    alert(errorMessage);
                    document.getElementById('companyRegisterForm').style.display = 'block';
                    document.getElementById('loadingSpinner').style.display = 'none';
                }
                
            } catch (error) {
                console.error('Error:', error);
                alert('通信エラーが発生しました');
                document.getElementById('companyRegisterForm').style.display = 'block';
                document.getElementById('loadingSpinner').style.display = 'none';
            }
        });
        
        // QRコード表示
        function displayQRCode(data) {
            document.getElementById('loadingSpinner').style.display = 'none';
            
            document.getElementById('registeredCompanyName').textContent = data.company_name;
            document.getElementById('companyCode').textContent = data.company_code;
            document.getElementById('qrCodeImage').src = '../..' + data.qr_code_path;
            document.getElementById('signupUrl').textContent = data.signup_url;
            
            document.getElementById('qrResultCard').style.display = 'block';
            
            // QRコード表示位置にスクロール
            document.getElementById('qrResultCard').scrollIntoView({ behavior: 'smooth' });
        }
    </script>
</body>
</html>
