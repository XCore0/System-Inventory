<?php
require_once __DIR__ . '/../config/db.php';
$pdo = getPdo();

// Stock Distribution by Brand
$stockDistribution = $pdo->query('
    SELECT 
        p.brand,
        SUM(p.stock) AS total_stock,
        COUNT(p.id) AS product_count
    FROM products p
    WHERE p.brand IS NOT NULL AND p.brand != ""
    GROUP BY p.brand
    ORDER BY total_stock DESC
')->fetchAll();

$totalStockUnits = array_sum(array_column($stockDistribution, 'total_stock'));

// Value Distribution by Brand
$valueDistribution = $pdo->query('
    SELECT 
        p.brand,
        SUM(p.stock * p.price) AS total_value,
        COUNT(p.id) AS product_count
    FROM products p
    WHERE p.brand IS NOT NULL AND p.brand != ""
    GROUP BY p.brand
    ORDER BY total_value DESC
')->fetchAll();

$totalInventoryValue = array_sum(array_column($valueDistribution, 'total_value'));

// Detailed Inventory Report
$inventoryReport = $pdo->query('
    SELECT 
        p.id,
        p.name,
        p.brand,
        p.model,
        p.processor,
        p.ram,
        p.storage,
        p.stock,
        p.price,
        (p.stock * p.price) AS total_value,
        CASE 
            WHEN p.stock > 3 THEN "Good"
            WHEN p.stock > 0 THEN "Low"
            ELSE "Out"
        END AS status
    FROM products p
    ORDER BY p.name ASC
')->fetchAll();

$totalProducts = count($inventoryReport);
$totalStock = array_sum(array_column($inventoryReport, 'stock'));
$avgUnitPrice = $totalProducts > 0 ? array_sum(array_column($inventoryReport, 'price')) / $totalProducts : 0;
$grandTotalValue = array_sum(array_column($inventoryReport, 'total_value'));

// Supplier Summary
$supplierSummary = $pdo->query('
    SELECT 
        s.name,
        COUNT(DISTINCT p.id) AS product_count,
        COALESCE(SUM(p.stock), 0) AS total_stock,
        COALESCE(SUM(p.stock * p.price), 0) AS total_value
    FROM suppliers s
    LEFT JOIN products p ON s.id = p.supplier_id
    WHERE s.status = "active"
    GROUP BY s.id
    ORDER BY total_value DESC
')->fetchAll();

// Helper function for Indonesian Rupiah format
function formatRupiah($amount) {
    return 'Rp ' . number_format((float)$amount, 0, ',', '.');
}

// Helper function for percentage
function formatPercentage($value, $total) {
    if ($total == 0) return '0.0%';
    return number_format(($value / $total) * 100, 1, '.', '') . '%';
}
?>

<section class="flex flex-col gap-4">
  <div class="flex items-center justify-between mt-8">
    <div>
      <h1 class="text-3xl font-semibold text-slate-900">Inventory Reports</h1>
      <p class="text-slate-500 mt-1">Comprehensive analytics and insights</p>
    </div>
    <div class="flex items-center gap-2">
      <a href="export-reports.php?type=xls" class="px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-semibold shadow hover:bg-indigo-700 transition">Export XLS</a>
    </div>
  </div>

  <!-- Stock & Value Distribution Cards -->
  <div class="grid gap-4 md:grid-cols-2 mt-6">
    <!-- Stock Distribution -->
    <div class="rounded-2xl bg-gradient-to-br from-blue-50 to-blue-100 border border-blue-200 p-6 shadow-sm">
      <h3 class="text-lg font-semibold text-slate-900 mb-4">Stock Distribution</h3>
      <p class="text-xs text-slate-600 mb-4">Number of units by brand</p>
      <div class="space-y-3 max-h-96 overflow-y-auto">
        <?php foreach ($stockDistribution as $item): 
          $percentage = formatPercentage($item['total_stock'], $totalStockUnits);
        ?>
          <div class="flex items-center justify-between">
            <div class="flex-1">
              <div class="flex items-center justify-between mb-1">
                <span class="text-sm font-medium text-slate-900"><?php echo htmlspecialchars($item['brand']); ?></span>
                <span class="text-sm font-semibold text-slate-700"><?php echo number_format((int)$item['total_stock'], 0, ',', '.'); ?> units</span>
              </div>
              <div class="h-2 w-full rounded-full bg-blue-200 overflow-hidden">
                <div class="h-full rounded-full bg-blue-500" style="width: <?php echo ($item['total_stock'] / $totalStockUnits) * 100; ?>%"></div>
              </div>
              <span class="text-xs text-slate-600 mt-1"><?php echo $percentage; ?></span>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Value Distribution -->
    <div class="rounded-2xl bg-gradient-to-br from-emerald-50 to-green-100 border border-emerald-200 p-6 shadow-sm">
      <h3 class="text-lg font-semibold text-slate-900 mb-4">Value Distribution</h3>
      <p class="text-xs text-slate-600 mb-4">Inventory value by brand</p>
      <div class="space-y-3 max-h-96 overflow-y-auto">
        <?php foreach ($valueDistribution as $item): 
          $percentage = formatPercentage($item['total_value'], $totalInventoryValue);
        ?>
          <div class="flex items-center justify-between">
            <div class="flex-1">
              <div class="flex items-center justify-between mb-1">
                <span class="text-sm font-medium text-slate-900"><?php echo htmlspecialchars($item['brand']); ?></span>
                <span class="text-sm font-semibold text-slate-700"><?php echo formatRupiah($item['total_value']); ?></span>
              </div>
              <div class="h-2 w-full rounded-full bg-emerald-200 overflow-hidden">
                <div class="h-full rounded-full bg-emerald-500" style="width: <?php echo ($item['total_value'] / $totalInventoryValue) * 100; ?>%"></div>
              </div>
              <span class="text-xs text-slate-600 mt-1"><?php echo $percentage; ?></span>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Detailed Inventory Report -->
  <div class="rounded-2xl bg-white shadow-sm border border-slate-200 p-6 mt-6">
    <h3 class="text-lg font-semibold text-slate-900 mb-4">Detailed Inventory Report</h3>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-slate-200">
            <th class="text-left py-3 px-4 font-semibold text-slate-700">No</th>
            <th class="text-left py-3 px-4 font-semibold text-slate-700">Product</th>
            <th class="text-left py-3 px-4 font-semibold text-slate-700">Brand</th>
            <th class="text-left py-3 px-4 font-semibold text-slate-700">Specs</th>
            <th class="text-right py-3 px-4 font-semibold text-slate-700">Stock</th>
            <th class="text-right py-3 px-4 font-semibold text-slate-700">Unit Price</th>
            <th class="text-right py-3 px-4 font-semibold text-slate-700">Total Value</th>
            <th class="text-center py-3 px-4 font-semibold text-slate-700">Status</th>
          </tr>
        </thead>
        <tbody>
          <?php $no = 1; foreach ($inventoryReport as $product): 
            $specs = trim(($product['processor'] ?? '') . '/' . ($product['ram'] ?? '') . '/' . ($product['storage'] ?? ''));
            $statusClass = $product['status'] === 'Good' ? 'text-emerald-600' : ($product['status'] === 'Low' ? 'text-orange-600' : 'text-rose-600');
            $statusBgClass = $product['status'] === 'Good' ? 'bg-emerald-100' : ($product['status'] === 'Low' ? 'bg-orange-100' : 'bg-rose-100');
          ?>
            <tr class="border-b border-slate-100 hover:bg-slate-50">
              <td class="py-3 px-4 text-slate-600"><?php echo $no++; ?></td>
              <td class="py-3 px-4 font-medium text-slate-900"><?php echo htmlspecialchars($product['name']); ?></td>
              <td class="py-3 px-4 text-slate-700"><?php echo htmlspecialchars($product['brand'] ?? '-'); ?></td>
              <td class="py-3 px-4 text-slate-600"><?php echo htmlspecialchars($specs ?: '-'); ?></td>
              <td class="py-3 px-4 text-right text-slate-700"><?php echo number_format((int)$product['stock'], 0, ',', '.'); ?></td>
              <td class="py-3 px-4 text-right font-medium text-slate-900"><?php echo formatRupiah($product['price']); ?></td>
              <td class="py-3 px-4 text-right font-semibold text-slate-900"><?php echo formatRupiah($product['total_value']); ?></td>
              <td class="py-3 px-4 text-center">
                <?php if ($product['status'] === 'Good'): ?>
                  <span class="inline-flex items-center justify-center w-6 h-6 rounded-full <?php echo $statusBgClass; ?> <?php echo $statusClass; ?> font-semibold text-xs">✓</span>
                <?php elseif ($product['status'] === 'Low'): ?>
                  <span class="inline-flex items-center justify-center w-6 h-6 rounded-full <?php echo $statusBgClass; ?> <?php echo $statusClass; ?> font-semibold text-xs">!</span>
                <?php else: ?>
                  <span class="inline-flex items-center justify-center w-6 h-6 rounded-full <?php echo $statusBgClass; ?> <?php echo $statusClass; ?> font-semibold text-xs">✗</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          <!-- Total Row -->
          <tr class="bg-slate-50 font-semibold">
            <td colspan="4" class="py-4 px-4 text-slate-900">Total</td>
            <td class="py-4 px-4 text-right text-slate-900"><?php echo number_format($totalStock, 0, ',', '.'); ?></td>
            <td class="py-4 px-4 text-right text-slate-900"><?php echo formatRupiah($avgUnitPrice); ?></td>
            <td class="py-4 px-4 text-right text-slate-900"><?php echo formatRupiah($grandTotalValue); ?></td>
            <td class="py-4 px-4 text-center text-slate-900"><?php echo $totalProducts; ?> products</td>
          </tr>
        </tbody>
      </table>
    </div>
    <div class="mt-4 text-sm text-slate-600">
      <p><strong>Total:</strong> <?php echo $totalProducts; ?> products • Average Unit Price: <?php echo formatRupiah($avgUnitPrice); ?> • Total Value: <?php echo formatRupiah($grandTotalValue); ?></p>
    </div>
  </div>

  <!-- Supplier Summary -->
  <div class="mt-6">
    <h3 class="text-lg font-semibold text-slate-900 mb-4">Supplier Summary</h3>
    <div class="grid gap-4 md:grid-cols-3">
      <?php foreach ($supplierSummary as $supplier): ?>
        <div class="rounded-2xl bg-white shadow-sm border border-slate-200 p-6">
          <h4 class="text-base font-semibold text-slate-900 mb-4"><?php echo htmlspecialchars($supplier['name']); ?></h4>
          <div class="space-y-3">
            <div class="flex items-center justify-between">
              <span class="text-sm text-slate-600">Products</span>
              <span class="text-sm font-semibold text-slate-900"><?php echo (int)$supplier['product_count']; ?></span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-sm text-slate-600">Units in Stock</span>
              <span class="text-sm font-semibold text-slate-900"><?php echo number_format((int)$supplier['total_stock'], 0, ',', '.'); ?></span>
            </div>
            <div class="flex items-center justify-between pt-2 border-t border-slate-200">
              <span class="text-sm font-medium text-slate-700">Value</span>
              <span class="text-base font-bold text-slate-900"><?php echo formatRupiah($supplier['total_value']); ?></span>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
      <?php if (count($supplierSummary) === 0): ?>
        <div class="col-span-3 text-center py-8 text-slate-500">
          <p>No supplier data available</p>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>
