// 仕様書準拠Chart.js実装 - 集金管理特化版
document.addEventListener('DOMContentLoaded', function() {
    // Chart.js設定
    const materialColors = {
        primary: '#2196F3',
        success: '#4CAF50',
        warning: '#FFC107',
        error: '#F44336',
        info: '#2196F3',
        // 企業別カラーパレット
        companies: ['#4CAF50', '#2196F3', '#FF9800', '#9C27B0', '#F44336', '#00BCD4']
    };

    // Canvas要素の取得とサイズ設定
    const salesTrendCanvas = document.getElementById('salesTrendChart');
    const companyRatioCanvas = document.getElementById('companyRatioChart');
    const productQuantityCanvas = document.getElementById('productQuantityChart');
    const paymentMethodCanvas = document.getElementById('paymentMethodChart');
    
    if (salesTrendCanvas) {
        // 1. 月別売上推移チャート（仕様書準拠）
        const salesTrendCtx = salesTrendCanvas.getContext('2d');
        new Chart(salesTrendCtx, {
            type: 'line',
            data: {
                labels: monthLabels || [],
                datasets: [{
                    label: '売上金額',
                    data: monthAmounts || [],
                    borderColor: materialColors.primary,
                    backgroundColor: materialColors.primary + '20',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: materialColors.primary,
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: '月別売上推移（集金管理用）',
                        font: { size: 16, weight: 'bold' }
                    },
                    legend: { display: false },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: 'white',
                        bodyColor: 'white',
                        borderColor: materialColors.primary,
                        borderWidth: 1,
                        callbacks: {
                            label: function(context) {
                                return '売上: ¥' + context.parsed.y.toLocaleString();
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '¥' + value.toLocaleString();
                            },
                            font: { size: 12 }
                        },
                        grid: { color: '#E0E0E0', drawBorder: false }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 12 } }
                    }
                },
                elements: { point: { hoverRadius: 8 } },
                interaction: { intersect: false, mode: 'index' },
                layout: { padding: { left: 10, right: 10, top: 10, bottom: 10 } }
            }
        });
    }

    if (companyRatioCanvas) {
        // 2. 企業別売上比率（仕様書要件）
        const companyRatioCtx = companyRatioCanvas.getContext('2d');
        new Chart(companyRatioCtx, {
            type: 'doughnut',
            data: {
                labels: companyLabels || ['データなし'],
                datasets: [{
                    data: companyAmounts || [0],
                    backgroundColor: materialColors.companies,
                    borderWidth: 0,
                    hoverBorderWidth: 2,
                    hoverBorderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: '企業別売上比率（集金重要指標）',
                        font: { size: 14, weight: 'bold' }
                    },
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 15,
                            usePointStyle: true,
                            font: { size: 11 },
                            generateLabels: function(chart) {
                                const data = chart.data;
                                if (data.labels.length && data.datasets.length) {
                                    const total = data.datasets[0].data.reduce((a, b) => a + b, 0);
                                    return data.labels.map((label, index) => {
                                        const value = data.datasets[0].data[index];
                                        const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : '0.0';
                                        return {
                                            text: `${label}: ${percentage}%`,
                                            fillStyle: data.datasets[0].backgroundColor[index],
                                            strokeStyle: data.datasets[0].backgroundColor[index],
                                            pointStyle: 'circle',
                                            hidden: false,
                                            index: index
                                        };
                                    });
                                }
                                return [];
                            }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: 'white',
                        bodyColor: 'white',
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : '0.0';
                                return `${label}: ¥${value.toLocaleString()} (${percentage}%)`;
                            }
                        }
                    }
                },
                cutout: '60%',
                layout: { padding: { left: 5, right: 5, top: 5, bottom: 5 } }
            }
        });
    }

    if (productQuantityCanvas) {
        // 3. 商品別注文数量（仕様書要件）
        const productQuantityCtx = productQuantityCanvas.getContext('2d');
        new Chart(productQuantityCtx, {
            type: 'bar',
            data: {
                labels: productLabels || ['データなし'],
                datasets: [{
                    label: '注文数量',
                    data: productQuantities || [0],
                    backgroundColor: materialColors.success + '80',
                    borderColor: materialColors.success,
                    borderWidth: 2,
                    borderRadius: 4,
                    borderSkipped: false
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: '商品別注文数量（人気商品分析）',
                        font: { size: 14, weight: 'bold' }
                    },
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: 'white',
                        bodyColor: 'white',
                        callbacks: {
                            label: function(context) {
                                return `注文数: ${context.parsed.y.toLocaleString()}個`;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return value.toLocaleString() + '個';
                            },
                            font: { size: 12 }
                        },
                        grid: { color: '#E0E0E0', drawBorder: false }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 10 }, maxRotation: 45 }
                    }
                },
                layout: { padding: { left: 10, right: 10, top: 10, bottom: 10 } }
            }
        });
    }

    if (paymentMethodCanvas) {
        // 4. 支払方法別集計（仕様書要件）
        const paymentMethodCtx = paymentMethodCanvas.getContext('2d');
        new Chart(paymentMethodCtx, {
            type: 'horizontalBar',
            data: {
                labels: methodLabels || ['データなし'],
                datasets: [{
                    label: '支払金額',
                    data: methodAmounts || [0],
                    backgroundColor: [
                        materialColors.success + '80',
                        materialColors.primary + '80',
                        materialColors.warning + '80',
                        materialColors.info + '80'
                    ],
                    borderColor: [
                        materialColors.success,
                        materialColors.primary,
                        materialColors.warning,
                        materialColors.info
                    ],
                    borderWidth: 2,
                    borderRadius: 4
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: '支払方法別集計（集金効率分析）',
                        font: { size: 14, weight: 'bold' }
                    },
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: 'white',
                        bodyColor: 'white',
                        callbacks: {
                            label: function(context) {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = total > 0 ? ((context.parsed.x / total) * 100).toFixed(1) : '0.0';
                                return `¥${context.parsed.x.toLocaleString()} (${percentage}%)`;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '¥' + value.toLocaleString();
                            },
                            font: { size: 12 }
                        },
                        grid: { color: '#E0E0E0', drawBorder: false }
                    },
                    y: {
                        grid: { display: false },
                        ticks: { font: { size: 12 } }
                    }
                },
                layout: { padding: { left: 10, right: 10, top: 10, bottom: 10 } }
            }
        });
    }

    // カードのスタガーアニメーション
    const cards = document.querySelectorAll('.animate-fade-in');
    cards.forEach((card, index) => {
        card.style.animationDelay = (index * 0.1) + 's';
    });

    // 統計数値のカウントアップアニメーション
    const statValues = document.querySelectorAll('.stat-value');
    statValues.forEach(stat => {
        const finalValue = parseInt(stat.textContent.replace(/[^\d]/g, ''));
        if (!isNaN(finalValue) && finalValue > 0) {
            animateNumber(stat, finalValue);
        }
    });
});

// 数値アニメーション関数
function animateNumber(element, finalValue, duration = 1000) {
    let startValue = 0;
    const increment = finalValue / (duration / 16);
    
    function updateNumber() {
        startValue += increment;
        if (startValue < finalValue) {
            element.innerHTML = element.innerHTML.replace(/[\d,]+/, Math.floor(startValue).toLocaleString());
            requestAnimationFrame(updateNumber);
        } else {
            element.innerHTML = element.innerHTML.replace(/[\d,]+/, finalValue.toLocaleString());
        }
    }
    
    updateNumber();
}

// データ更新関数（PaymentManagerから呼び出し用）
function updateDashboardData() {
    // PaymentManagerからの最新データでグラフを更新
    fetch('api/dashboard.php?action=chart_data')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // 各グラフのデータを更新
                updateChartData(data.charts);
            }
        })
        .catch(error => {
            console.error('Dashboard data update failed:', error);
        });
}

function updateChartData(chartData) {
    // 実装は PaymentManager 完成後に追加
    console.log('Chart data updated:', chartData);
}
