<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CSV Import Debug</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .debug-info { background: #f5f5f5; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .error { background: #ffebee; border-left: 4px solid #f44336; }
        .success { background: #e8f5e9; border-left: 4px solid #4caf50; }
        .warning { background: #fff3e0; border-left: 4px solid #ff9800; }
        .upload-area { border: 2px dashed #ddd; padding: 40px; text-align: center; margin: 20px 0; }
        .upload-area.dragover { border-color: #007cba; background-color: #f0f8ff; }
        button { background: #007cba; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background: #005a8b; }
        #result { margin-top: 20px; padding: 15px; border-radius: 5px; display: none; }
    </style>
</head>
<body>
    <h1>CSV Import Debug Tool</h1>
    
    <div class="debug-info">
        <h3>System Information</h3>
        <p><strong>Current Time:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
        <p><strong>PHP Version:</strong> <?php echo PHP_VERSION; ?></p>
        <p><strong>Upload Max Size:</strong> <?php echo ini_get('upload_max_filesize'); ?></p>
        <p><strong>Post Max Size:</strong> <?php echo ini_get('post_max_size'); ?></p>
    </div>
    
    <div class="debug-info">
        <h3>Required Files Check</h3>
        <?php
        $requiredFiles = [
            'import API' => '../api/import.php',
            'Database class' => '../classes/Database.php',
            'CSV Importer' => '../classes/SmileyCSVImporter.php',
            'File Handler' => '../classes/FileUploadHandler.php'
        ];
        
        foreach ($requiredFiles as $name => $path) {
            $exists = file_exists($path);
            $class = $exists ? 'success' : 'error';
            echo "<p class='{$class}'><strong>{$name}:</strong> " . ($exists ? '✓ EXISTS' : '✗ MISSING') . "</p>";
        }
        ?>
    </div>
    
    <div class="debug-info">
        <h3>API Connectivity Test</h3>
        <button onclick="testAPI()">Test Import API</button>
        <div id="api-result"></div>
    </div>
    
    <div class="debug-info">
        <h3>CSV File Upload Test</h3>
        <div class="upload-area" id="uploadArea">
            <p>Drag and drop CSV file here or click to select</p>
            <input type="file" id="csvFile" accept=".csv,.txt" style="display: none;">
            <button onclick="document.getElementById('csvFile').click()">Select File</button>
        </div>
        <button onclick="uploadFile()" id="uploadBtn" style="display: none;">Upload File</button>
        <div id="result"></div>
    </div>

    <script>
        // API接続テスト
        async function testAPI() {
            const resultDiv = document.getElementById('api-result');
            resultDiv.innerHTML = '<p>Testing API connection...</p>';
            
            try {
                const response = await fetch('../api/import.php?action=test');
                const text = await response.text();
                
                console.log('API Response:', text);
                
                try {
                    const data = JSON.parse(text);
                    resultDiv.innerHTML = `
                        <p class="success">✓ API Connection Successful</p>
                        <p><strong>Database Connection:</strong> ${data.data.database_connection}</p>
                        <p><strong>Total Orders:</strong> ${data.data.total_orders}</p>
                        <p><strong>Importer Class:</strong> ${data.data.importer_class}</p>
                    `;
                } catch (jsonError) {
                    resultDiv.innerHTML = `
                        <p class="error">✗ Invalid JSON Response</p>
                        <p><strong>Response:</strong> ${text.substring(0, 500)}...</p>
                        <p><strong>JSON Error:</strong> ${jsonError.message}</p>
                    `;
                }
            } catch (error) {
                resultDiv.innerHTML = `
                    <p class="error">✗ API Connection Failed</p>
                    <p><strong>Error:</strong> ${error.message}</p>
                `;
            }
        }
        
        // ファイルアップロード処理
        const uploadArea = document.getElementById('uploadArea');
        const csvFile = document.getElementById('csvFile');
        const uploadBtn = document.getElementById('uploadBtn');
        const resultDiv = document.getElementById('result');
        
        // ドラッグ&ドロップ
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        });
        
        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('dragover');
        });
        
        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                csvFile.files = files;
                showUploadButton(files[0]);
            }
        });
        
        csvFile.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                showUploadButton(e.target.files[0]);
            }
        });
        
        function showUploadButton(file) {
            uploadArea.innerHTML = `
                <p>Selected: ${file.name}</p>
                <p>Size: ${(file.size / 1024).toFixed(2)} KB</p>
                <p>Type: ${file.type}</p>
            `;
            uploadBtn.style.display = 'block';
        }
        
        async function uploadFile() {
            const file = csvFile.files[0];
            if (!file) {
                alert('Please select a file first');
                return;
            }
            
            resultDiv.style.display = 'block';
            resultDiv.className = 'debug-info';
            resultDiv.innerHTML = '<p>Uploading file...</p>';
            
            const formData = new FormData();
            formData.append('csv_file', file);
            
            try {
                const response = await fetch('../api/import.php', {
                    method: 'POST',
                    body: formData
                });
                
                const text = await response.text();
                console.log('Upload Response:', text);
                
                try {
                    const data = JSON.parse(text);
                    
                    if (data.success) {
                        resultDiv.className = 'debug-info success';
                        resultDiv.innerHTML = `
                            <h4>✓ Upload Successful</h4>
                            <p><strong>Batch ID:</strong> ${data.data.batch_id}</p>
                            <p><strong>Total Records:</strong> ${data.data.total_records}</p>
                            <p><strong>Success Records:</strong> ${data.data.success_records}</p>
                            <p><strong>Error Records:</strong> ${data.data.error_records}</p>
                            <p><strong>Processing Time:</strong> ${data.data.processing_time}s</p>
                        `;
                        
                        if (data.errors && data.errors.length > 0) {
                            resultDiv.innerHTML += '<h5>Errors:</h5><ul>';
                            data.errors.forEach(error => {
                                resultDiv.innerHTML += `<li>Row ${error.row}: ${error.errors.join(', ')}</li>`;
                            });
                            resultDiv.innerHTML += '</ul>';
                        }
                    } else {
                        resultDiv.className = 'debug-info error';
                        resultDiv.innerHTML = `
                            <h4>✗ Upload Failed</h4>
                            <p><strong>Error:</strong> ${data.message}</p>
                        `;
                    }
                } catch (jsonError) {
                    resultDiv.className = 'debug-info error';
                    resultDiv.innerHTML = `
                        <h4>✗ Invalid Response</h4>
                        <p><strong>JSON Error:</strong> ${jsonError.message}</p>
                        <p><strong>Response:</strong></p>
                        <pre>${text}</pre>
                    `;
                }
                
            } catch (error) {
                resultDiv.className = 'debug-info error';
                resultDiv.innerHTML = `
                    <h4>✗ Network Error</h4>
                    <p><strong>Error:</strong> ${error.message}</p>
                `;
            }
        }
        
        // 初期API テスト
        window.addEventListener('load', testAPI);
    </script>
</body>
</html>
