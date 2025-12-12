<?php
require_once __DIR__ . '/../config/db.php';
$pdo = getPdo();

// Get statistics
$totalProducts = (int)$pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
$totalStock = (int)$pdo->query('SELECT COALESCE(SUM(stock), 0) FROM products')->fetchColumn();
$totalValue = (float)$pdo->query('SELECT COALESCE(SUM(price * stock), 0) FROM products')->fetchColumn();
$activeSuppliers = (int)$pdo->query('SELECT COUNT(*) FROM suppliers WHERE status = "active"')->fetchColumn();
$totalSuppliers = (int)$pdo->query('SELECT COUNT(*) FROM suppliers')->fetchColumn();

// Calculate percentages (using reasonable targets or relative calculations)
// Total Products: percentage based on target of 100 products
$productsTarget = 100;
$productsPercentage = $totalProducts > 0 ? min(100, round(($totalProducts / $productsTarget) * 100)) : 0;

// Stock Available: percentage based on target of 1000 units
$stockTarget = 1000;
$stockPercentage = $totalStock > 0 ? min(100, round(($totalStock / $stockTarget) * 100)) : 0;

// Total Value: percentage based on target of 1 billion
$valueTarget = 1000000000; // 1 billion
$valuePercentage = $totalValue > 0 ? min(100, round(($totalValue / $valueTarget) * 100)) : 0;

// Active Suppliers: percentage of total suppliers
$suppliersPercentage = $totalSuppliers > 0 ? round(($activeSuppliers / $totalSuppliers) * 100) : 0;

// Get low stock products (stock <= 3)
$lowStockProducts = $pdo->query('
    SELECT name, brand, stock 
    FROM products 
    WHERE stock <= 3 
    ORDER BY stock ASC 
    LIMIT 8
')->fetchAll();

$lowStockCount = count($lowStockProducts);

// Recent sales orders
$recentSales = $pdo->query('
    SELECT so.*, COUNT(soi.id) AS item_count, COALESCE(SUM(soi.qty * soi.price), 0) AS total_amount
    FROM sales_orders so
    LEFT JOIN sales_order_items soi ON so.id = soi.sales_order_id
    GROUP BY so.id
    ORDER BY so.created_at DESC
    LIMIT 5
')->fetchAll();

// Recent stock moves
$recentStockMoves = $pdo->query('
    SELECT sm.*, p.name AS product_name
    FROM stock_moves sm
    LEFT JOIN products p ON sm.product_id = p.id
    ORDER BY sm.created_at DESC
    LIMIT 5
')->fetchAll();
?>

<section class="flex-1">
  <div class="mt-8">
    <h1 class="text-3xl font-semibold text-slate-900">Welcome back, Admin! 👋</h1>
    <p class="text-slate-500 mt-1">Here's what's happening with your inventory today.</p>
  </div>

  <div class="grid mt-6 gap-4 sm:grid-cols-2 lg:grid-cols-4">
    <div class="rounded-2xl bg-white shadow-sm border border-slate-100 p-4">
      <div class="flex items-center justify-between">
        <div class="h-12 w-12 rounded-xl bg-sky-50 text-sky-600 flex items-center justify-center">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 7h7V3H3v4Zm0 14h7v-8H3v8Zm11 0h7v-5h-7v5Zm0-14v5h7V7h-7Z" />
          </svg>
        </div>
        <span class="px-2 py-1 rounded-lg bg-emerald-50 text-emerald-600 text-xs font-medium"><?php echo $productsPercentage; ?>%</span>
      </div>
      <p class="mt-4 text-slate-500 text-sm">Total Products</p>
      <p class="text-3xl font-semibold text-slate-900"><?php echo $totalProducts; ?></p>
      <div class="mt-3 h-1.5 w-full rounded-full bg-slate-100">
        <div class="h-full rounded-full bg-gradient-to-r from-sky-500 to-cyan-400" style="width: <?php echo min(100, $productsPercentage); ?>%"></div>
      </div>
    </div>

    <div class="rounded-2xl bg-white shadow-sm border border-slate-100 p-4">
      <div class="flex items-center justify-between">
        <div class="h-12 w-12 rounded-xl bg-fuchsia-50 text-fuchsia-600 flex items-center justify-center">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 9.5 12 5l8 4.5-8 4.5-8-4.5Zm0 5L12 19l8-4.5" />
          </svg>
        </div>
        <span class="px-2 py-1 rounded-lg bg-emerald-50 text-emerald-600 text-xs font-medium"><?php echo $stockPercentage; ?>%</span>
      </div>
      <p class="mt-4 text-slate-500 text-sm">Stock Available</p>
      <p class="text-3xl font-semibold text-slate-900"><?php echo number_format($totalStock, 0, ',', '.'); ?></p>
      <div class="mt-3 h-1.5 w-full rounded-full bg-slate-100">
        <div class="h-full rounded-full bg-gradient-to-r from-fuchsia-500 to-rose-500" style="width: <?php echo min(100, $stockPercentage); ?>%"></div>
      </div>
    </div>

    <div class="rounded-2xl bg-white shadow-sm border border-slate-100 p-4">
      <div class="flex items-center justify-between">
        <div class="h-12 w-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 4h18M5 8h14v9a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V8Z" />
          </svg>
        </div>
        <span class="px-2 py-1 rounded-lg bg-emerald-50 text-emerald-600 text-xs font-medium"><?php echo $valuePercentage; ?>%</span>
      </div>
      <p class="mt-4 text-slate-500 text-sm">Total Value</p>
      <p class="text-3xl font-semibold text-slate-900">
        <?php echo 'Rp ' . number_format($totalValue, 0, ',', '.'); ?>
      </p>
      <div class="mt-3 h-1.5 w-full rounded-full bg-slate-100">
        <div class="h-full rounded-full bg-gradient-to-r from-emerald-500 to-teal-500" style="width: <?php echo min(100, $valuePercentage); ?>%"></div>
      </div>
    </div>

    <div class="rounded-2xl bg-white shadow-sm border border-slate-100 p-4">
      <div class="flex items-center justify-between">
        <div class="h-12 w-12 rounded-xl bg-orange-50 text-orange-600 flex items-center justify-center">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 13V5a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v8m14 0v6H5v-6m14 0H5" />
          </svg>
        </div>
        <span class="px-2 py-1 rounded-lg bg-emerald-50 text-emerald-600 text-xs font-medium"><?php echo $suppliersPercentage; ?>%</span>
      </div>
      <p class="mt-4 text-slate-500 text-sm">Active Suppliers</p>
      <p class="text-3xl font-semibold text-slate-900"><?php echo $activeSuppliers; ?></p>
      <div class="mt-3 h-1.5 w-full rounded-full bg-slate-100">
        <div class="h-full rounded-full bg-gradient-to-r from-orange-500 to-amber-500" style="width: <?php echo min(100, $suppliersPercentage); ?>%"></div>
      </div>
    </div>
  </div>

  <?php if ($lowStockCount > 0): ?>
    <div class="mt-8 rounded-2xl border-2 border-fuchsia-400 bg-white shadow-sm">
      <div class="flex items-center justify-between px-4 py-3 border-b border-slate-100">
        <div class="flex items-center gap-3">
          <div class="h-10 w-10 rounded-xl bg-rose-100 text-rose-600 flex items-center justify-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v3.5m0 3h.01M10.29 3.86 1.82 18a1 1 0 0 0 .86 1.5h18.64a1 1 0 0 0 .86-1.5L13.71 3.86a1 1 0 0 0-1.72 0Z" />
            </svg>
          </div>
          <div>
            <p class="text-base font-semibold text-slate-800">Low Stock Alert</p>
            <p class="text-sm text-slate-500">These products are running low and need to be restocked soon</p>
          </div>
        </div>
        <span class="rounded-full bg-rose-500 text-white text-xs px-3 py-1"><?php echo $lowStockCount; ?> items</span>
      </div>

      <div class="grid gap-4 p-4 sm:grid-cols-2 lg:grid-cols-3">
        <?php foreach ($lowStockProducts as $product): ?>
          <div class="rounded-xl bg-slate-50 p-3 shadow-sm border border-slate-100">
            <p class="text-sm font-semibold text-slate-800"><?php echo htmlspecialchars($product['name']); ?></p>
            <div class="flex items-center justify-between text-sm text-slate-500 mt-1">
              <span><?php echo htmlspecialchars($product['brand'] ?: '-'); ?></span>
              <span class="text-rose-500 font-medium"><?php echo (int)$product['stock']; ?> units</span>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <!-- Recent Sales Orders -->
  <?php if (count($recentSales) > 0): ?>
    <div class="mt-8 rounded-2xl border border-slate-200 bg-white shadow-sm p-4">
      <h2 class="text-lg font-semibold text-slate-900 mb-4">Recent Sales Orders</h2>
      <div class="space-y-2">
        <?php foreach ($recentSales as $sale): ?>
          <div class="flex items-center justify-between p-3 bg-slate-50 rounded-lg">
            <div>
              <p class="text-sm font-semibold text-slate-900"><?php echo htmlspecialchars($sale['code']); ?></p>
              <p class="text-xs text-slate-500"><?php echo htmlspecialchars($sale['customer_name']); ?> • <?php echo date('d M Y', strtotime($sale['order_date'])); ?></p>
            </div>
            <div class="text-right">
              <p class="text-sm font-semibold text-slate-900">Rp <?php echo number_format((float)$sale['total_amount'], 0, ',', '.'); ?></p>
              <span class="text-xs px-2 py-0.5 rounded-full <?php 
                echo $sale['status'] === 'paid' ? 'bg-emerald-100 text-emerald-700' : 
                ($sale['status'] === 'shipped' ? 'bg-blue-100 text-blue-700' : 
                ($sale['status'] === 'cancelled' ? 'bg-rose-100 text-rose-700' : 'bg-yellow-100 text-yellow-700')); 
              ?>"><?php echo ucfirst($sale['status']); ?></span>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>
</section>
