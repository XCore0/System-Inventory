<?php
require_once __DIR__ . '/config/db.php';
$pdo = getPdo();

$exportType = $_GET['type'] ?? 'csv'; // csv, xls, or pdf

// Get all data for export
$totalProducts = (int)$pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
$totalStock = (int)$pdo->query('SELECT COALESCE(SUM(stock), 0) FROM products')->fetchColumn();
$lowStockCount = (int)$pdo->query('SELECT COUNT(*) FROM products WHERE stock <= 3')->fetchColumn();

$totalSales = (float)$pdo->query('
    SELECT COALESCE(SUM(soi.qty * soi.price), 0)
    FROM sales_order_items soi
    INNER JOIN sales_orders so ON soi.sales_order_id = so.id
    WHERE so.status IN ("paid", "shipped")
')->fetchColumn();

$pendingOrders = (int)$pdo->query('SELECT COUNT(*) FROM sales_orders WHERE status = "pending"')->fetchColumn();
$paidOrders = (int)$pdo->query('SELECT COUNT(*) FROM sales_orders WHERE status = "paid"')->fetchColumn();
$shippedOrders = (int)$pdo->query('SELECT COUNT(*) FROM sales_orders WHERE status = "shipped"')->fetchColumn();

$topSuppliers = $pdo->query('
    SELECT s.name,
           COUNT(DISTINCT p.id) AS product_count,
           COALESCE(SUM(p.price * p.stock), 0) AS total_value
    FROM suppliers s
    LEFT JOIN products p ON s.id = p.supplier_id
    WHERE s.status = "active"
    GROUP BY s.id
    ORDER BY total_value DESC
    LIMIT 10
')->fetchAll();

$topProducts = $pdo->query('
    SELECT p.name,
           SUM(soi.qty) AS total_sold,
           SUM(soi.qty * soi.price) AS total_revenue
    FROM sales_order_items soi
    INNER JOIN sales_orders so ON soi.sales_order_id = so.id
    INNER JOIN products p ON soi.product_id = p.id
    WHERE so.status IN ("paid", "shipped")
    GROUP BY p.id
    ORDER BY total_sold DESC
    LIMIT 10
')->fetchAll();

$salesByMonth = $pdo->query('
    SELECT DATE_FORMAT(so.created_at, "%Y-%m") AS month,
           DATE_FORMAT(so.created_at, "%b %Y") AS month_label,
           COUNT(DISTINCT so.id) AS order_count,
           COALESCE(SUM(soi.qty * soi.price), 0) AS total_sales
    FROM sales_orders so
    LEFT JOIN sales_order_items soi ON so.id = soi.sales_order_id
    WHERE so.status IN ("paid", "shipped")
      AND so.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY month
    ORDER BY month DESC
    LIMIT 6
')->fetchAll();

if ($exportType === 'csv') {
    // Export CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="inventory_report_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Stock Overview
    fputcsv($output, ['INVENTORY REPORT - ' . date('d M Y H:i:s')]);
    fputcsv($output, []);
    fputcsv($output, ['STOCK OVERVIEW']);
    fputcsv($output, ['Total Products', $totalProducts]);
    fputcsv($output, ['Total Stock Available', number_format($totalStock, 0, ',', '.')]);
    fputcsv($output, ['Low Stock Items', $lowStockCount]);
    fputcsv($output, []);
    
    // Sales Overview
    fputcsv($output, ['SALES OVERVIEW']);
    fputcsv($output, ['Total Sales', 'Rp ' . number_format($totalSales, 0, ',', '.')]);
    fputcsv($output, ['Pending Orders', $pendingOrders]);
    fputcsv($output, ['Paid Orders', $paidOrders]);
    fputcsv($output, ['Shipped Orders', $shippedOrders]);
    fputcsv($output, []);
    
    // Top Suppliers
    fputcsv($output, ['TOP SUPPLIERS']);
    fputcsv($output, ['Supplier Name', 'Product Count', 'Total Value']);
    foreach ($topSuppliers as $supplier) {
        fputcsv($output, [
            $supplier['name'],
            $supplier['product_count'],
            'Rp ' . number_format((float)$supplier['total_value'], 0, ',', '.')
        ]);
    }
    fputcsv($output, []);
    
    // Top Products
    fputcsv($output, ['TOP SELLING PRODUCTS']);
    fputcsv($output, ['Product Name', 'Total Sold', 'Total Revenue']);
    foreach ($topProducts as $product) {
        fputcsv($output, [
            $product['name'],
            number_format((int)$product['total_sold'], 0, ',', '.') . ' units',
            'Rp ' . number_format((float)$product['total_revenue'], 0, ',', '.')
        ]);
    }
    fputcsv($output, []);
    
    // Sales by Month
    fputcsv($output, ['SALES BY MONTH (Last 6 Months)']);
    fputcsv($output, ['Month', 'Order Count', 'Total Sales']);
    foreach ($salesByMonth as $month) {
        fputcsv($output, [
            $month['month_label'],
            $month['order_count'],
            'Rp ' . number_format((float)$month['total_sales'], 0, ',', '.')
        ]);
    }
    
    fclose($output);
    exit;
} else if ($exportType === 'xls') {
    // Export XLS using Excel XML format (SpreadsheetML)
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="inventory_report_' . date('Y-m-d') . '.xls"');
    
    // Excel XML header
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<?mso-application progid="Excel.Sheet"?>' . "\n";
    echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"' . "\n";
    echo ' xmlns:o="urn:schemas-microsoft-com:office:office"' . "\n";
    echo ' xmlns:x="urn:schemas-microsoft-com:office:excel"' . "\n";
    echo ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"' . "\n";
    echo ' xmlns:html="http://www.w3.org/TR/REC-html40">' . "\n";
    echo '<Worksheet ss:Name="Inventory Report">' . "\n";
    echo '<Table>' . "\n";
    
    // Helper function to escape XML
    function escapeXml($text) {
        return htmlspecialchars($text, ENT_XML1, 'UTF-8');
    }
    
    // Helper function to add row
    function addRow($values, $isHeader = false) {
        echo '<Row>' . "\n";
        foreach ($values as $value) {
            $cellType = $isHeader ? 'String' : (is_numeric($value) ? 'Number' : 'String');
            $style = $isHeader ? 'ss:StyleID="Header"' : '';
            echo '<Cell ' . $style . '><Data ss:Type="' . $cellType . '">' . escapeXml($value) . '</Data></Cell>' . "\n";
        }
        echo '</Row>' . "\n";
    }
    
    // Add styles
    echo '<Styles>' . "\n";
    echo '<Style ss:ID="Header">' . "\n";
    echo '<Font ss:Bold="1"/>' . "\n";
    echo '<Interior ss:Color="#CCCCCC" ss:Pattern="Solid"/>' . "\n";
    echo '</Style>' . "\n";
    echo '</Styles>' . "\n";
    
    // Stock Overview
    addRow(['INVENTORY REPORT - ' . date('d M Y H:i:s')], true);
    addRow([]);
    addRow(['STOCK OVERVIEW'], true);
    addRow(['Total Products', $totalProducts]);
    addRow(['Total Stock Available', number_format($totalStock, 0, ',', '.')]);
    addRow(['Low Stock Items', $lowStockCount]);
    addRow([]);
    
    // Sales Overview
    addRow(['SALES OVERVIEW'], true);
    addRow(['Total Sales', 'Rp ' . number_format($totalSales, 0, ',', '.')]);
    addRow(['Pending Orders', $pendingOrders]);
    addRow(['Paid Orders', $paidOrders]);
    addRow(['Shipped Orders', $shippedOrders]);
    addRow([]);
    
    // Top Suppliers
    addRow(['TOP SUPPLIERS'], true);
    addRow(['Supplier Name', 'Product Count', 'Total Value'], true);
    foreach ($topSuppliers as $supplier) {
        addRow([
            $supplier['name'],
            $supplier['product_count'],
            'Rp ' . number_format((float)$supplier['total_value'], 0, ',', '.')
        ]);
    }
    addRow([]);
    
    // Top Products
    addRow(['TOP SELLING PRODUCTS'], true);
    addRow(['Product Name', 'Total Sold', 'Total Revenue'], true);
    foreach ($topProducts as $product) {
        addRow([
            $product['name'],
            number_format((int)$product['total_sold'], 0, ',', '.') . ' units',
            'Rp ' . number_format((float)$product['total_revenue'], 0, ',', '.')
        ]);
    }
    addRow([]);
    
    // Sales by Month
    addRow(['SALES BY MONTH (Last 6 Months)'], true);
    addRow(['Month', 'Order Count', 'Total Sales'], true);
    foreach ($salesByMonth as $month) {
        addRow([
            $month['month_label'],
            $month['order_count'],
            'Rp ' . number_format((float)$month['total_sales'], 0, ',', '.')
        ]);
    }
    
    echo '</Table>' . "\n";
    echo '</Worksheet>' . "\n";
    echo '</Workbook>' . "\n";
    exit;
} else if ($exportType === 'pdf') {
    // Export PDF using HTML to PDF approach
    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Inventory Report - <?php echo date('d M Y'); ?></title>
        <style>
            @media print {
                body { margin: 0; }
                .no-print { display: none; }
            }
            body {
                font-family: Arial, sans-serif;
                font-size: 12px;
                margin: 20px;
            }
            h1 {
                color: #1e293b;
                border-bottom: 2px solid #3b82f6;
                padding-bottom: 10px;
                margin-bottom: 20px;
            }
            h2 {
                color: #475569;
                margin-top: 25px;
                margin-bottom: 10px;
                font-size: 14px;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 20px;
            }
            th, td {
                border: 1px solid #e2e8f0;
                padding: 8px;
                text-align: left;
            }
            th {
                background-color: #f1f5f9;
                font-weight: bold;
                color: #1e293b;
            }
            .summary {
                background-color: #f8fafc;
                padding: 15px;
                border-radius: 5px;
                margin-bottom: 20px;
            }
            .summary-item {
                display: inline-block;
                margin-right: 30px;
                margin-bottom: 10px;
            }
            .summary-label {
                font-size: 10px;
                color: #64748b;
            }
            .summary-value {
                font-size: 16px;
                font-weight: bold;
                color: #1e293b;
            }
        </style>
    </head>
    <body>
        <h1>INVENTORY REPORT</h1>
        <p style="color: #64748b; margin-bottom: 20px;">Generated on <?php echo date('d M Y, H:i:s'); ?></p>
        
        <div class="summary">
            <h2 style="margin-top: 0;">Stock Overview</h2>
            <div class="summary-item">
                <div class="summary-label">Total Products</div>
                <div class="summary-value"><?php echo $totalProducts; ?></div>
            </div>
            <div class="summary-item">
                <div class="summary-label">Stock Available</div>
                <div class="summary-value"><?php echo number_format($totalStock, 0, ',', '.'); ?></div>
            </div>
            <div class="summary-item">
                <div class="summary-label">Low Stock Items</div>
                <div class="summary-value" style="color: #ef4444;"><?php echo $lowStockCount; ?></div>
            </div>
        </div>
        
        <div class="summary">
            <h2 style="margin-top: 0;">Sales Overview</h2>
            <div class="summary-item">
                <div class="summary-label">Total Sales</div>
                <div class="summary-value">Rp <?php echo number_format($totalSales, 0, ',', '.'); ?></div>
            </div>
            <div class="summary-item">
                <div class="summary-label">Pending Orders</div>
                <div class="summary-value"><?php echo $pendingOrders; ?></div>
            </div>
            <div class="summary-item">
                <div class="summary-label">Paid Orders</div>
                <div class="summary-value"><?php echo $paidOrders; ?></div>
            </div>
            <div class="summary-item">
                <div class="summary-label">Shipped Orders</div>
                <div class="summary-value"><?php echo $shippedOrders; ?></div>
            </div>
        </div>
        
        <h2>Top Suppliers</h2>
        <table>
            <thead>
                <tr>
                    <th>Supplier Name</th>
                    <th>Product Count</th>
                    <th>Total Value</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($topSuppliers as $supplier): ?>
                <tr>
                    <td><?php echo htmlspecialchars($supplier['name']); ?></td>
                    <td><?php echo $supplier['product_count']; ?></td>
                    <td>Rp <?php echo number_format((float)$supplier['total_value'], 0, ',', '.'); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <h2>Top Selling Products</h2>
        <table>
            <thead>
                <tr>
                    <th>Product Name</th>
                    <th>Total Sold</th>
                    <th>Total Revenue</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($topProducts as $product): ?>
                <tr>
                    <td><?php echo htmlspecialchars($product['name']); ?></td>
                    <td><?php echo number_format((int)$product['total_sold'], 0, ',', '.'); ?> units</td>
                    <td>Rp <?php echo number_format((float)$product['total_revenue'], 0, ',', '.'); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <h2>Sales by Month (Last 6 Months)</h2>
        <table>
            <thead>
                <tr>
                    <th>Month</th>
                    <th>Order Count</th>
                    <th>Total Sales</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($salesByMonth as $month): ?>
                <tr>
                    <td><?php echo htmlspecialchars($month['month_label']); ?></td>
                    <td><?php echo $month['order_count']; ?></td>
                    <td>Rp <?php echo number_format((float)$month['total_sales'], 0, ',', '.'); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </body>
    </html>
    <?php
    $html = ob_get_clean();
    
    // Add print button and auto-print script to HTML
    $html = str_replace('</style>', '
            .print-button {
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 10px 20px;
                background-color: #3b82f6;
                color: white;
                border: none;
                border-radius: 5px;
                cursor: pointer;
                font-size: 14px;
                z-index: 1000;
            }
            .print-button:hover {
                background-color: #2563eb;
            }
        </style>', $html);
    
    $html = str_replace('<body>', '<body><button class="print-button no-print" onclick="window.print()">Print / Save as PDF</button>', $html);
    $html = str_replace('</body>', '<script>
            // Auto-trigger print dialog
            window.onload = function() {
                setTimeout(function() {
                    window.print();
                }, 500);
            };
        </script></body>', $html);
    
    // Output HTML for print to PDF
    // User can use browser\'s "Print to PDF" functionality
    // In production, consider using a library like TCPDF or DomPDF for server-side PDF generation
    echo $html;
    exit;
}

