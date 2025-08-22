<?php
/**
 * APIレスポンス診断ツール
 * import.php の出力を詳細に確認
 */

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>APIレスポンス診断</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .console { 
            background: #1e1e1e; 
            color: #00ff00; 
            font-family: monospace; 
            padding: 15px; 
            border-radius: 5px;
            white-space: pre-wrap;
            max-height: 500px;
            overflow-y: auto;
        }
        .response-box {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
            white-space: pre-wrap;
            font-family: monospace;
            max-height: 300px;
            overflow-y: auto;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container py-4">
        <div class="row">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header bg-danger text-white">
                        <h4 class="mb-0">🔍 APIレスポンス診断ツール</h4>
                    </div>
                    <div class="card-body">
                        
                        <!-- 診断結果表示エリア -->
                        <div id="diagnosticResults">
                            <div class="alert alert-info">
                                診断を開始します...
                            </div>
                        </div>
                        
                        <!-- 手動テストフォーム -->
                        <div class="card mt-4">
                            <div class="card-header">
                                <h5>📁 ファイルアップロードテスト</h5>
                            </div>
                            <div class="card-body">
                                <form id="uploadForm" enctype="multipart/form-data">
                                    <div class="mb-3">
                                        <label for="csvFile" class="form-label">CSVファイル選択</label>
                                        <input type="file" class="form-control" id="csvFile" name="csvFile" accept=".csv,.txt">
                                    </div>
                                    <button type="submit" class="btn btn-primary">テストアップロード</button>
                                </form>
                                
                                <div id="uploadResults" class="mt-3" style="display: none;">
                                    <h6>📤 アップロード結果:</h6>
                                    <div id="uploadResponse" class="response-box"></div>
                                </div>
                            </div>
                        </div>
                        
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // 診断開始
        document.addEventListener('DOMContentLoaded', function() {
            runDiagnostics();
            setupUploadTest();
        });

        async function runDiagnostics() {
            const resultsDiv = document.getElementById('diagnosticResults');
            
            let html = '<div class="alert alert-warning">🔍 診断実行中...</div>';
            resultsDiv.innerHTML = html;
            
            // 1. import.php へのGETリクエスト
            html += '<div class="card mb-3">';
            html += '<div class="card-header"><strong>Test 1: import.php GET リクエスト</strong></div>';
            html += '<div class="card-body">';
            
            try {
                const response = await fetch('../api/import.php', {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json'
                    }
                });
                
                const responseText = await response.text();
                
                html += `<p><strong>Status:</strong> ${response.status} ${response.statusText}</p>`;
                html += `<p><strong>Content-Type:</strong> ${response.headers.get('content-type')}</p>`;
                html += `<p><strong>Response Length:</strong> ${responseText.length} characters</p>`;
                html += '<div class="response-box">' + escapeHtml(responseText) + '</div>';
                
                // JSON解析テスト
                try {
                    const jsonData = JSON.parse(responseText);
                    html += '<div class="alert alert-success">✅ JSON解析成功</div>';
                } catch (e) {
                    html += '<div class="alert alert-danger">❌ JSON解析失敗: ' + e.message + '</div>';
                }
                
            } catch (error) {
                html += '<div class="alert alert-danger">❌ リクエスト失敗: ' + error.message + '</div>';
            }
            
            html += '</div></div>';
            
            // 2. SmileyCSVImporter クラス確認
            html += '<div class="card mb-3">';
            html += '<div class="card-header"><strong>Test 2: SmileyCSVImporter クラス確認</strong></div>';
            html += '<div class="card-body">';
            
            try {
                const response = await fetch('../api/debug_orders.php');
                const debugData = await response.json();
                
                if (debugData.debug_info && debugData.debug_info.importer_class) {
                    const classStatus = debugData.debug_info.importer_class;
                    if (classStatus === 'EXISTS') {
                        html += '<div class="alert alert-success">✅ SmileyCSVImporter クラスが存在します</div>';
                        if (debugData.debug_info.importer_methods) {
                            html += '<p><strong>利用可能メソッド:</strong></p>';
                            html += '<ul>';
                            debugData.debug_info.importer_methods.forEach(method => {
                                html += '<li>' + method + '</li>';
                            });
                            html += '</ul>';
                        }
                    } else {
                        html += '<div class="alert alert-danger">❌ SmileyCSVImporter クラスが存在しません</div>';
                    }
                }
            } catch (error) {
                html += '<div class="alert alert-warning">⚠️ クラス確認エラー: ' + error.message + '</div>';
            }
            
            html += '</div></div>';
            
            // 3. ファイルシステム確認
            html += '<div class="card mb-3">';
            html += '<div class="card-header"><strong>Test 3: ファイルシステム確認</strong></div>';
            html += '<div class="card-body">';
            
            try {
                const response = await fetch('../api/file_check.php');
                const responseText = await response.text();
                
                html += '<div class="response-box">' + escapeHtml(responseText) + '</div>';
                
            } catch (error) {
                html += '<div class="alert alert-warning">⚠️ ファイル確認スクリプトが見つかりません</div>';
            }
            
            html += '</div></div>';
            
            resultsDiv.innerHTML = html;
        }

        function setupUploadTest() {
            const form = document.getElementById('uploadForm');
            form.addEventListener('submit', async function(e) {
                e.preventDefault();
                
                const fileInput = document.getElementById('csvFile');
                const file = fileInput.files[0];
                
                if (!file) {
                    alert('ファイルを選択してください');
                    return;
                }
                
                const formData = new FormData();
                formData.append('csvFile', file);
                
                const resultsDiv = document.getElementById('uploadResults');
                const responseDiv = document.getElementById('uploadResponse');
                
                resultsDiv.style.display = 'block';
                responseDiv.innerHTML = '🔄 アップロード中...';
                
                try {
                    const response = await fetch('../api/import.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const responseText = await response.text();
                    
                    let resultHtml = `Status: ${response.status} ${response.statusText}\n`;
                    resultHtml += `Content-Type: ${response.headers.get('content-type')}\n`;
                    resultHtml += `Response Length: ${responseText.length} characters\n\n`;
                    resultHtml += 'Raw Response:\n';
                    resultHtml += responseText;
                    
                    responseDiv.innerHTML = escapeHtml(resultHtml);
                    
                    // JSON解析試行
                    try {
                        const jsonData = JSON.parse(responseText);
                        responseDiv.innerHTML += '\n\n✅ JSON解析成功:\n' + JSON.stringify(jsonData, null, 2);
                    } catch (e) {
                        responseDiv.innerHTML += '\n\n❌ JSON解析失敗: ' + e.message;
                    }
                    
                } catch (error) {
                    responseDiv.innerHTML = '❌ アップロードエラー: ' + error.message;
                }
            });
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>
</body>
</html>
