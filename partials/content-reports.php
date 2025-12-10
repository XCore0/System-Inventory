<?php
require_once __DIR__ . '/../config/db.php';
$pdo = getPdo();

// Stock Overview
$totalProducts = (int)$pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
$totalStock = (int)$pdo->query('SELECT COALESCE(SUM(stock), 0) FROM products')->fetchColumn();
$lowStockCount = (int)$pdo->query('SELECT COUNT(*) FROM products WHERE stock <= 3')->fetchColumn();

// Top Suppliers by value
$topSuppliers = $pdo->query('
    SELECT s.name,
           COUNT(DISTINCT p.id) AS product_count,
           COALESCE(SUM(p.price * p.stock), 0) AS total_value
    FROM suppliers s
    LEFT JOIN products p ON s.id = p.supplier_id
    WHERE s.status = "active"
    GROUP BY s.id
    ORDER BY total_value DESC
    LIMIT 5
')->fetchAll();

// Sales Statistics
$totalSales = (float)$pdo->query('
    SELECT COALESCE(SUM(soi.qty * soi.price), 0)
    FROM sales_order_items soi
    INNER JOIN sales_orders so ON soi.sales_order_id = so.id
    WHERE so.status IN ("paid", "shipped")
')->fetchColumn();

$pendingOrders = (int)$pdo->query('SELECT COUNT(*) FROM sales_orders WHERE status = "pending"')->fetchColumn();
$paidOrders = (int)$pdo->query('SELECT COUNT(*) FROM sales_orders WHERE status = "paid"')->fetchColumn();
$shippedOrders = (int)$pdo->query('SELECT COUNT(*) FROM sales_orders WHERE status = "shipped"')->fetchColumn();

// Top Selling Products
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
    LIMIT 5
')->fetchAll();

// Sales by Month (last 6 months)
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

// Recent Activity (from stock_moves and sales_orders)
$recentActivities = $pdo->query('
    (SELECT 
        CONCAT("Restocked ", p.name) AS activity,
        sm.created_at,
        CONCAT("+", sm.qty, " units") AS detail,
        "emerald" AS color,
        "inventory" AS link
    FROM stock_moves sm
    LEFT JOIN products p ON sm.product_id = p.id
    WHERE sm.move_type = "in"
    ORDER BY sm.created_at DESC
    LIMIT 3)
    UNION ALL
    (SELECT 
        CONCAT("Low stock: ", p.name) AS activity,
        NOW() AS created_at,
        CONCAT(p.stock, " units left") AS detail,
        "rose" AS color,
        "inventory" AS link
    FROM products p
    WHERE p.stock <= 3
    ORDER BY p.stock ASC
    LIMIT 2)
    UNION ALL
    (SELECT 
        CONCAT("Sales order: ", so.code) AS activity,
        so.created_at,
        so.customer_name AS detail,
        "blue" AS color,
        "sales" AS link
    FROM sales_orders so
    WHERE so.status = "pending"
    ORDER BY so.created_at DESC
    LIMIT 2)
    ORDER BY created_at DESC
    LIMIT 5
')->fetchAll();
?>

<section class="flex flex-col gap-4">
  <div class="flex items-center justify-between mt-8">
    <div>
      <h1 class="text-3xl font-semibold text-slate-900">Reports</h1>
      <p class="text-slate-500 mt-1">Overview of stock, sales, and supplier performance.</p>
    </div>
    <div class="flex items-center gap-2">
      <a href="export-reports.php?type=csv" class="px-4 py-2 rounded-lg bg-slate-100 text-slate-700 text-sm font-semibold hover:bg-slate-200 transition">Export CSV</a>
      <a href="export-reports.php?type=pdf" class="px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-semibold shadow hover:bg-indigo-700 transition">Export PDF</a>
    </div>
  </div>

  <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3 mt-6">
    <!-- Stock Overview -->
    <div class="rounded-2xl bg-white shadow-sm border border-slate-100 p-4">
      <div class="flex items-center justify-between">
        <p class="text-sm font-semibold text-slate-800">Stock Overview</p>
        <span class="text-xs text-emerald-600 bg-emerald-50 px-2 py-1 rounded-full">+12%</span>
      </div>
      <div class="mt-3 h-2 w-full rounded-full bg-slate-100 overflow-hidden">
        <div class="h-full <?php echo $totalProducts > 0 ? 'w-3/4' : 'w-0'; ?> rounded-full bg-gradient-to-r from-sky-500 to-cyan-400"></div>
      </div>
      <div class="mt-4 grid grid-cols-3 gap-2 text-sm text-slate-600">
        <div>
          <p class="text-xs text-slate-500">Total Products</p>
          <p class="text-lg font-semibold text-slate-900"><?php echo $totalProducts; ?></p>
        </div>
        <div>
          <p class="text-xs text-slate-500">Stock Available</p>
          <p class="text-lg font-semibold text-slate-900"><?php echo number_format($totalStock, 0, ',', '.'); ?></p>
        </div>
        <div>
          <p class="text-xs text-slate-500">Low Stock</p>
          <p class="text-lg font-semibold text-rose-500"><?php echo $lowStockCount; ?></p>
        </div>
      </div>
    </div>

    <!-- Sales Overview -->
    <div class="rounded-2xl bg-white shadow-sm border border-slate-100 p-4">
      <div class="flex items-center justify-between">
        <p class="text-sm font-semibold text-slate-800">Sales Overview</p>
        <span class="text-xs text-slate-500">This Month</span>
      </div>
      <div class="mt-3 space-y-2">
        <div>
          <p class="text-xs text-slate-500">Total Sales</p>
          <p class="text-lg font-semibold text-slate-900">
            Rp <?php echo number_format($totalSales, 0, ',', '.'); ?>
          </p>
        </div>
        <div class="grid grid-cols-3 gap-2 text-xs">
          <div>
            <p class="text-slate-500">Pending</p>
            <p class="font-semibold text-yellow-600"><?php echo $pendingOrders; ?></p>
          </div>
          <div>
            <p class="text-slate-500">Paid</p>
            <p class="font-semibold text-emerald-600"><?php echo $paidOrders; ?></p>
          </div>
          <div>
            <p class="text-slate-500">Shipped</p>
            <p class="font-semibold text-blue-600"><?php echo $shippedOrders; ?></p>
          </div>
        </div>
      </div>
    </div>

    <!-- Top Suppliers -->
    <div class="rounded-2xl bg-white shadow-sm border border-slate-100 p-4">
      <div class="flex items-center justify-between">
        <p class="text-sm font-semibold text-slate-800">Top Suppliers</p>
        <span class="text-xs text-slate-500">By Value</span>
      </div>
      <div class="mt-3 space-y-3 text-sm text-slate-700">
        <?php foreach ($topSuppliers as $supplier): ?>
          <div class="flex items-center justify-between">
            <span class="truncate"><?php echo htmlspecialchars($supplier['name']); ?></span>
            <span class="font-semibold text-slate-900">
              <?php
                $value = (float)$supplier['total_value'];
                if ($value >= 1000000) {
                  echo number_format($value / 1000000, 1, '.', '') . 'M';
                } else {
                  echo number_format($value / 1000, 0, '.', '') . 'K';
                }
              ?>
            </span>
          </div>
        <?php endforeach; ?>
        <?php if (count($topSuppliers) === 0): ?>
          <p class="text-xs text-slate-500 text-center py-2">No data available</p>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Top Selling Products -->
  <div class="rounded-2xl bg-white shadow-sm border border-slate-100 p-4">
    <div class="flex items-center justify-between">
      <p class="text-sm font-semibold text-slate-800">Top Selling Products</p>
      <span class="text-xs text-slate-500">All Time</span>
    </div>
    <div class="mt-3 space-y-3 text-sm text-slate-700">
      <?php if (count($topProducts) > 0): ?>
        <?php foreach ($topProducts as $product): ?>
          <div class="flex items-center justify-between">
            <div class="flex-1 min-w-0">
              <p class="font-medium text-slate-900 truncate"><?php echo htmlspecialchars($product['name']); ?></p>
              <p class="text-xs text-slate-500"><?php echo number_format((int)$product['total_sold'], 0, ',', '.'); ?> units sold</p>
            </div>
            <span class="font-semibold text-slate-900 ml-4">
              Rp <?php echo number_format((float)$product['total_revenue'], 0, ',', '.'); ?>
            </span>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="py-4 text-center text-slate-500 text-sm">No sales data available</div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Sales by Month -->
  <div class="rounded-2xl bg-white shadow-sm border border-slate-100 p-4">
    <div class="flex items-center justify-between">
      <p class="text-sm font-semibold text-slate-800">Sales by Month</p>
      <span class="text-xs text-slate-500">Last 6 Months</span>
    </div>
    <div class="mt-3 space-y-3">
      <?php if (count($salesByMonth) > 0): ?>
        <?php 
        $maxSales = max(array_map(function($m) { return (float)$m['total_sales']; }, $salesByMonth));
        foreach ($salesByMonth as $month): 
          $salesValue = (float)$month['total_sales'];
          $percentage = $maxSales > 0 ? ($salesValue / $maxSales) * 100 : 0;
        ?>
          <div>
            <div class="flex items-center justify-between mb-1">
              <span class="text-xs font-medium text-slate-700"><?php echo htmlspecialchars($month['month_label']); ?></span>
              <span class="text-xs font-semibold text-slate-900">
                Rp <?php echo number_format($salesValue, 0, ',', '.'); ?>
              </span>
            </div>
            <div class="h-2 w-full rounded-full bg-slate-100 overflow-hidden">
              <div class="h-full rounded-full bg-gradient-to-r from-indigo-500 to-purple-500" style="width: <?php echo $percentage; ?>%"></div>
            </div>
            <p class="text-xs text-slate-500 mt-1"><?php echo (int)$month['order_count']; ?> orders</p>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="py-4 text-center text-slate-500 text-sm">No sales data available</div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Recent Activity -->
  <div class="rounded-2xl bg-white shadow-sm border border-slate-100 p-4">
    <div class="flex items-center justify-between">
      <p class="text-sm font-semibold text-slate-800">Recent Activity</p>
      <span class="text-xs text-slate-500">Latest</span>
    </div>
    <div class="mt-3 divide-y divide-slate-100 text-sm text-slate-700">
      <?php if (count($recentActivities) > 0): ?>
        <?php foreach ($recentActivities as $activity): ?>
          <div class="py-3 flex items-center justify-between hover:bg-slate-50 transition rounded-lg px-2 -mx-2">
            <div class="flex-1 min-w-0">
              <span class="font-medium text-slate-900"><?php echo htmlspecialchars($activity['activity']); ?></span>
              <p class="text-xs text-slate-500 mt-0.5">
                <?php 
                  $createdAt = new DateTime($activity['created_at']);
                  echo $createdAt->format('d M Y, H:i');
                ?>
              </p>
            </div>
            <div class="flex items-center gap-2 ml-4">
              <span class="text-xs font-semibold <?php 
                echo $activity['color'] === 'rose' ? 'text-rose-500' : 
                ($activity['color'] === 'emerald' ? 'text-emerald-500' : 'text-blue-500'); 
              ?>"><?php echo htmlspecialchars($activity['detail']); ?></span>
              <a href="?page=<?php echo htmlspecialchars($activity['link'] ?? 'dashboard'); ?>" class="text-indigo-600 hover:text-indigo-700 text-xs">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
              </a>
            </div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="py-4 text-center text-slate-500 text-sm">No recent activity</div>
      <?php endif; ?>
    </div>
  </div>
</section>
