<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CSV分析ツール - Smiley配食事業</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .analysis-container {
            max-width: 1000px;
            margin: 2rem auto;
            padding: 2rem;
        }
        .header-card {
            background: linear-gradient(135deg, #2E8B57 0%, #1e6b3d 100%);
            color: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
        }
        .upload-area {
            border: 3px dashed #90EE90;
            border-radius: 15px;
            padding: 3rem;
            text-align: center;
            background: white;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .upload-area:hover {
            border-color: #2E8B57;
            background: #f0f8f0;
        }
        .results-section {
            display: none;
            margin-top: 2rem;
        }
        .analysis-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .error-item {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 5px;
            padding: 0.75rem;
            margin-bottom: 0.5rem;
        }
        .warning-item {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 5px;
            padding: 0.75rem;
            margin-bottom: 0.5rem;
        }
        .success-item {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 5px;
            padding: 0.75rem;
            margin-bottom: 0.5rem;
        }
        .header-match {
            display: inline-block;
            background: #28a745;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 3px;
            margin: 0.1rem;
            font-size: 0.8rem;
        }
        .header-missing {
            display: inline-block;
            background: #dc3545;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 3px;
            margin: 0.1rem;
            font-size: 0.8rem;
        }
        .header-extra {
            display: inline-block;
            background: #ffc107;
            color: black;
            padding: 0.25rem 0.5rem;
            border-radius: 3px;
            margin: 0.1rem;
            font-size: 0.8rem;
        }
    </style>
</head>
<body>
    <div class="analysis-container">
        <!-- ヘッダー -->
        <div class="header-card">
            <h1 class="mb-2">
                <i class="fas fa-search me-3"></i>
                CSV分析ツール
            </h1>
            <p class="mb-0 fs-5">Smiley配食事業CSVファイルの詳細分析・エラー特定</p>
        </div>

        <!-- アップロード -->
        <div class="upload-area" id="uploadArea">
            <div class="upload-content">
                <i class="fas fa-file-csv" style="font-size: 4rem; color: #2E8B57; margin-bottom: 1rem;"></i>
                <h3 class="text-primary mb-3">CSVファイルを分析</h3>
                <p class="text-muted mb-4">ファイルをドラッグ&ドロップまたはクリックして選択</p>
                
                <form id="analysisForm" enctype="multipart/form-data">
                    <input type="file" id="csvFile" name="csv_file" accept=".csv" style="display: none;">
                    <button type="button" class="btn btn-primary btn-lg" onclick="document.getElementById('csvFile').click()">
                        <i class="fas fa-folder-open me-2"></i>
                        ファイルを選択
                    </button>
                </form>
            </div>
        </div>

        <!-- 分析結果 -->
        <div class="results-section" id="resultsSection">
            <!-- ファイル情報 -->
            <div class="analysis-card">
                <h4 class="text-primary mb-3">ファイル情報</h4>
                <div id="fileInfo"></div>
            </div>

            <!-- ヘッダー分析 -->
            <div class="analysis-card">
                <h4 class="text-primary mb-3">ヘッダー分析</h4>
                <div id="headerAnalysis"></div>
            </div>

            <!-- データサンプル -->
            <div class="analysis-card">
                <h4 class="text-primary mb-3">データサンプル</h4>
                <div id="dataSample"></div>
            </div>

            <!-- 検証結果 -->
            <div class="analysis-card">
                <h4 class="text-primary mb-3">検証結果</h4>
                <div id="validationResults"></div>
            </div>

            <!-- 推奨事項 -->
            <div class="analysis-card">
                <h4 class="text-primary mb-3">推奨事項</h4>
                <div id="recommendations"></div>
            </div>
        </div>

        <!-- 戻るボタン -->
        <div class="text-center mt-4">
            <a href="csv_import.php" class="btn btn-outline-primary">
                <i class="fas fa-arrow-left me-2"></i>
                CSVインポート画面に戻る
            </a>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
    <script>
        class CSVAnalyzer {
            constructor() {
                this.uploadArea = document.getElementById('uploadArea');
                this.fileInput = document.getElementById('csvFile');
                this.resultsSection = document.getElementById('resultsSection');
                
                this.initializeEventListeners();
            }

            initializeEventListeners() {
                // ドラッグ&ドロップ
                this.uploadArea.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    this.uploadArea.style.borderColor = '#2E8B57';
                    this.uploadArea.style.background = '#f0f8f0';
                });

                this.uploadArea.addEventListener('dragleave', (e) => {
                    e.preventDefault();
                    this.uploadArea.style.borderColor = '#90EE90';
                    this.uploadArea.style.background = 'white';
                });

                this.uploadArea.addEventListener('drop', (e) => {
                    e.preventDefault();
                    this.uploadArea.style.borderColor = '#90EE90';
                    this.uploadArea.style.background = 'white';
                    
                    const files = e.dataTransfer.files;
                    if (files.length > 0) {
                        this.analyzeFile(files[0]);
                    }
                });

                // ファイル選択
                this.fileInput.addEventListener('change', (e) => {
                    if (e.target.files.length > 0) {
                        this.analyzeFile(e.target.files[0]);
                    }
                });

                // クリックでファイル選択
                this.uploadArea.addEventListener('click', () => {
                    this.fileInput.click();
                });
            }

            async analyzeFile(file) {
                try {
                    // ファイル検証
                    if (!file.name.toLowerCase().endsWith('.csv')) {
                        alert('CSVファイルを選択してください。');
                        return;
                    }

                    // FormData作成
                    const formData = new FormData();
                    formData.append('csv_file', file);

                    // 分析実行
                    const response = await fetch('../api/csv_error_analyzer.php', {
                        method: 'POST',
                        body: formData
                    });

                    const result = await response.json();

                    if (result.success) {
                        this.displayResults(result.data);
                    } else {
                        alert('分析エラー: ' + result.message);
                    }

                } catch (error) {
                    console.error('Analysis error:', error);
                    alert('分析中にエラーが発生しました: ' + error.message);
                }
            }

            displayResults(data) {
                // ファイル情報表示
                this.displayFileInfo(data.analysis.file_info, data.analysis.content_analysis);
                
                // ヘッダー分析表示
                this.displayHeaderAnalysis(data.analysis.header_analysis);
                
                // データサンプル表示
                this.displayDataSample(data.analysis.data_sample);
                
                // 検証結果表示
                this.displayValidationResults(data.analysis.validation_results);
                
                // 推奨事項表示
                this.displayRecommendations(data.analysis.recommendations);
                
                // 結果セクション表示
                this.resultsSection.style.display = 'block';
                this.resultsSection.scrollIntoView({ behavior: 'smooth' });
            }

            displayFileInfo(fileInfo, contentInfo) {
                const html = `
                    <div class="row">
                        <div class="col-md-6">
                            <h6>ファイル基本情報</h6>
                            <ul class="list-unstyled">
                                <li><strong>サイズ:</strong> ${fileInfo.size_mb}MB (${fileInfo.size.toLocaleString()}バイト)</li>
                                <li><strong>総行数:</strong> ${contentInfo.total_lines}行</li>
                                <li><strong>エンコーディング:</strong> ${contentInfo.original_encoding}</li>
                                <li><strong>BOM:</strong> ${contentInfo.bom_detected ? 'あり' : 'なし'}</li>
                            </ul>
                        </div>
                    </div>
                `;
                document.getElementById('fileInfo').innerHTML = html;
            }

            displayHeaderAnalysis(headerAnalysis) {
                const matchPercentage = headerAnalysis.match_percentage;
                const progressColor = matchPercentage >= 90 ? 'success' : matchPercentage >= 70 ? 'warning' : 'danger';
                
                let html = `
                    <div class="mb-3">
                        <h6>ヘッダー適合率: ${matchPercentage}%</h6>
                        <div class="progress mb-3">
                            <div class="progress-bar bg-${progressColor}" style="width: ${matchPercentage}%"></div>
                        </div>
                        <p><strong>フィールド数:</strong> ${headerAnalysis.header_count} / ${headerAnalysis.expected_count} (期待値)</p>
                    </div>
                `;

                if (headerAnalysis.matching_headers.length > 0) {
                    html += `
                        <div class="mb-3">
                            <h6 class="text-success">適合ヘッダー (${headerAnalysis.matching_headers.length}個)</h6>
                            <div>
                                ${headerAnalysis.matching_headers.map(h => `<span class="header-match">${h}</span>`).join('')}
                            </div>
                        </div>
                    `;
                }

                if (headerAnalysis.missing_headers.length > 0) {
                    html += `
                        <div class="mb-3">
                            <h6 class="text-danger">不足ヘッダー (${headerAnalysis.missing_headers.length}個)</h6>
                            <div>
                                ${headerAnalysis.missing_headers.map(h => `<span class="header-missing">${h}</span>`).join('')}
                            </div>
                        </div>
                    `;
                }

                if (headerAnalysis.extra_headers.length > 0) {
                    html += `
                        <div class="mb-3">
                            <h6 class="text-warning">余分なヘッダー (${headerAnalysis.extra_headers.length}個)</h6>
                            <div>
                                ${headerAnalysis.extra_headers.map(h => `<span class="header-extra">${h}</span>`).join('')}
                            </div>
                        </div>
                    `;
                }

                html += `
                    <details class="mt-3">
                        <summary class="btn btn-outline-info btn-sm">実際のヘッダー一覧</summary>
                        <div class="mt-2 p-2 bg-light rounded">
                            <small>${headerAnalysis.headers.join(', ')}</small>
                        </div>
                    </details>
                `;

                document.getElementById('headerAnalysis').innerHTML = html;
            }

            displayDataSample(dataSample) {
                if (!dataSample || dataSample.length === 0) {
                    document.getElementById('dataSample').innerHTML = '<p class="text-muted">データサンプルがありません</p>';
                    return;
                }

                let html = '<div class="table-responsive"><table class="table table-sm table-bordered">';
                html += '<thead class="table-light"><tr><th>行</th><th>フィールド数</th><th>データサンプル（最初の10フィールド）</th><th>整合性</th></tr></thead><tbody>';

                dataSample.forEach(sample => {
                    const statusColor = sample.matches_header_count ? 'success' : 'warning';
                    const statusText = sample.matches_header_count ? 'OK' : 'フィールド数不一致';
                    
                    html += `
                        <tr>
                            <td>${sample.row_number}</td>
                            <td>${sample.field_count}</td>
                            <td><small>${sample.data.join(', ')}</small></td>
                            <td><span class="badge bg-${statusColor}">${statusText}</span></td>
                        </tr>
                    `;
                });

                html += '</tbody></table></div>';
                document.getElementById('dataSample').innerHTML = html;
            }

            displayValidationResults(validationResults) {
                if (!validationResults || validationResults.length === 0) {
                    document.getElementById('validationResults').innerHTML = '<div class="success-item">検証エラーはありません</div>';
                    return;
                }

                let html = '';
                validationResults.forEach(result => {
                    const itemClass = result.type === 'error' ? 'error-item' : 'warning-item';
                    html += `<div class="${itemClass}"><strong>${result.type.toUpperCase()}:</strong> ${result.message}</div>`;
                });

                document.getElementById('validationResults').innerHTML = html;
            }

            displayRecommendations(recommendations) {
                if (!recommendations || recommendations.length === 0) {
                    document.getElementById('recommendations').innerHTML = '<div class="success-item">特に推奨事項はありません</div>';
                    return;
                }

                let html = '<ol>';
                recommendations.forEach(rec => {
                    html += `<li class="mb-2">${rec}</li>`;
                });
                html += '</ol>';

                document.getElementById('recommendations').innerHTML = html;
            }
        }

        // アプリケーション初期化
        document.addEventListener('DOMContentLoaded', () => {
            new CSVAnalyzer();
        });
    </script>
</body>
</html>
