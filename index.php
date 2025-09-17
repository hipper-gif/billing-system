<!-- JavaScript - 修正版 -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- データ変数定義 -->
    <script>
        // PHP データをJavaScript変数に変換
        const monthLabels = <?php echo $monthLabels; ?>;
        const monthAmounts = <?php echo $monthAmounts; ?>;
        const methodLabels = <?php echo $methodLabels; ?>;
        const methodAmounts = <?php echo $methodAmounts; ?>;
        
        // 仕様書準拠: 追加データ変数
        const companyLabels = <?php echo json_encode(array_column($companyData ?? [], 'company_name')); ?>;
        const companyAmounts = <?php echo json_encode(array_column($companyData ?? [], 'total_amount')); ?>;
        const productLabels = <?php echo json_encode(array_column($productData ?? [], 'product_name')); ?>;
        const productQuantities = <?php echo json_encode(array_column($productData ?? [], 'quantity')); ?>;
    </script>
    
    <!-- 仕様書準拠Chart.js -->
    <script src="assets/js/dashboard-charts.js"></script>
    
    <!-- その他の機能 -->
    <script>
        // フローティングアクションボタン機能
        function showQuickMenu() {
            const actions = [
                { icon: 'receipt_long', text: '請求書生成', url: 'pages/invoice_generate.php' },
                { icon: 'payments', text: '支払い確認', url: 'pages/payments.php' },
                { icon: 'file_upload', text: 'CSV取込', url: 'pages/csv_import.php' }
            ];
            
            alert('クイックメニュー機能（実装予定）');
        }

        // ダークモード切り替え（将来機能）
        function toggleDarkMode() {
            document.body.classList.toggle('dark-mode');
            localStorage.setItem('darkMode', document.body.classList.contains('dark-mode'));
        }

        // ローカルストレージからダークモード設定を復元
        if (localStorage.getItem('darkMode') === 'true') {
            document.body.classList.add('dark-mode');
        }
    </script>
</body>
</html>
