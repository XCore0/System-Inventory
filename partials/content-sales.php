<?php
require_once __DIR__ . '/../config/db.php';
$pdo = getPdo();

$salesErrors = [];
$salesSuccess = null;

// Pagination & Search
$page = max(1, (int)($_GET['p'] ?? 1));
$perPage = 10;
$search = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? 'all';
$offset = ($page - 1) * $perPage;

// Create Sales Order
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'sales_create') {
    $customerName = trim($_POST['customer_name'] ?? '');
    $orderDate = $_POST['order_date'] ?? date('Y-m-d');
    
    if ($customerName === '') {
        $salesErrors[] = 'Nama customer wajib diisi.';
    }
    
    if (!$salesErrors) {
        $code = 'SO-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
        $stmt = $pdo->prepare('INSERT INTO sales_orders (code, customer_name, order_date, status) VALUES (?, ?, ?, "pending")');
        $stmt->execute([$code, $customerName, $orderDate]);
        $newOrderId = $pdo->lastInsertId();
        
        // Clear output buffer and redirect
        ob_clean();
        header('Location: ?page=sales&id=' . $newOrderId . '&search=' . urlencode($search) . '&status=' . urlencode($statusFilter) . '&p=' . $page);
        exit;
    }
}

// Add Item to Sales Order
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'sales_add_item') {
    $salesOrderId = (int)($_POST['sales_order_id'] ?? 0);
    $productId = (int)($_POST['product_id'] ?? 0);
    $qty = (int)($_POST['qty'] ?? 0);
    $price = (float)($_POST['price'] ?? 0);
    
    if ($salesOrderId <= 0 || $productId <= 0) {
        $salesErrors[] = 'Data tidak valid.';
    }
    if ($qty <= 0) {
        $salesErrors[] = 'Quantity harus lebih dari 0.';
    }
    
    // Check stock
    $stockStmt = $pdo->prepare('SELECT stock FROM products WHERE id = ?');
    $stockStmt->execute([$productId]);
    $product = $stockStmt->fetch();
    
    if ($product && $qty > $product['stock']) {
        $salesErrors[] = 'Stock tidak mencukupi. Stock tersedia: ' . $product['stock'];
    }
    
    if (!$salesErrors) {
        $stmt = $pdo->prepare('INSERT INTO sales_order_items (sales_order_id, product_id, qty, price) VALUES (?, ?, ?, ?)');
        $stmt->execute([$salesOrderId, $productId, $qty, $price]);
        
        // Clear output buffer and redirect
        ob_clean();
        header('Location: ?page=sales&id=' . $salesOrderId . '&search=' . urlencode($search) . '&status=' . urlencode($statusFilter) . '&p=' . $page);
        exit;
    }
}

// Update Sales Order Status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'sales_update_status') {
    $id = (int)($_POST['id'] ?? 0);
    $newStatus = $_POST['status'] ?? '';
    
    if ($id > 0 && in_array($newStatus, ['pending', 'paid', 'shipped', 'cancelled'])) {
        // If changing to paid, reduce stock
        if ($newStatus === 'paid') {
            $pdo->beginTransaction();
            try {
                // Get all items
                $itemsStmt = $pdo->prepare('SELECT product_id, qty FROM sales_order_items WHERE sales_order_id = ?');
                $itemsStmt->execute([$id]);
                $items = $itemsStmt->fetchAll();
                
                // Check current status
                $orderStmt = $pdo->prepare('SELECT status FROM sales_orders WHERE id = ?');
                $orderStmt->execute([$id]);
                $order = $orderStmt->fetch();
                
                // Only reduce stock if not already paid
                if ($order && $order['status'] !== 'paid') {
                    foreach ($items as $item) {
                        // Reduce stock
                        $updateStmt = $pdo->prepare('UPDATE products SET stock = stock - ? WHERE id = ?');
                        $updateStmt->execute([$item['qty'], $item['product_id']]);
                        
                        // Log stock move
                        $logStmt = $pdo->prepare('INSERT INTO stock_moves (product_id, move_type, reference, qty, note) VALUES (?, "out", ?, ?, ?)');
                        $logStmt->execute([
                            $item['product_id'],
                            'SO-' . $id,
                            $item['qty'],
                            'Sales order ' . $id
                        ]);
                    }
                }
                
                // Update status
                $updateStatusStmt = $pdo->prepare('UPDATE sales_orders SET status = ? WHERE id = ?');
                $updateStatusStmt->execute([$newStatus, $id]);
                
                $pdo->commit();
                $salesSuccess = 'Status berhasil diupdate.';
            } catch (Exception $e) {
                $pdo->rollBack();
                $salesErrors[] = 'Error: ' . $e->getMessage();
            }
        } else {
            // If cancelling paid order, restore stock
            if ($newStatus === 'cancelled') {
                $orderStmt = $pdo->prepare('SELECT status FROM sales_orders WHERE id = ?');
                $orderStmt->execute([$id]);
                $order = $orderStmt->fetch();
                
                if ($order && $order['status'] === 'paid') {
                    $pdo->beginTransaction();
                    try {
                        $itemsStmt = $pdo->prepare('SELECT product_id, qty FROM sales_order_items WHERE sales_order_id = ?');
                        $itemsStmt->execute([$id]);
                        $items = $itemsStmt->fetchAll();
                        
                        foreach ($items as $item) {
                            $updateStmt = $pdo->prepare('UPDATE products SET stock = stock + ? WHERE id = ?');
                            $updateStmt->execute([$item['qty'], $item['product_id']]);
                        }
                        
                        $updateStatusStmt = $pdo->prepare('UPDATE sales_orders SET status = ? WHERE id = ?');
                        $updateStatusStmt->execute([$newStatus, $id]);
                        
                        $pdo->commit();
                        $salesSuccess = 'Order dibatalkan dan stock dikembalikan.';
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        $salesErrors[] = 'Error: ' . $e->getMessage();
                    }
                } else {
                    $updateStatusStmt = $pdo->prepare('UPDATE sales_orders SET status = ? WHERE id = ?');
                    $updateStatusStmt->execute([$newStatus, $id]);
                    $salesSuccess = 'Status berhasil diupdate.';
                }
            } else {
                $updateStatusStmt = $pdo->prepare('UPDATE sales_orders SET status = ? WHERE id = ?');
                $updateStatusStmt->execute([$newStatus, $id]);
                $salesSuccess = 'Status berhasil diupdate.';
            }
        }
        
        // Clear output buffer and redirect
        ob_clean();
        header('Location: ?page=sales&id=' . $id . '&search=' . urlencode($search) . '&status=' . urlencode($statusFilter) . '&p=' . $page);
        exit;
    }
}

// Delete Item from Sales Order
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'sales_delete_item') {
    $itemId = (int)($_POST['item_id'] ?? 0);
    $salesOrderId = (int)($_POST['sales_order_id'] ?? 0);
    
    if ($itemId > 0) {
        $stmt = $pdo->prepare('DELETE FROM sales_order_items WHERE id = ?');
        $stmt->execute([$itemId]);
        
        // Clear output buffer and redirect
        ob_clean();
        header('Location: ?page=sales&id=' . $salesOrderId . '&search=' . urlencode($search) . '&status=' . urlencode($statusFilter) . '&p=' . $page);
        exit;
    }
}

// Build query with search and filter
$whereClause = '1=1';
$params = [];

if ($search !== '') {
    $whereClause .= ' AND (so.code LIKE ? OR so.customer_name LIKE ?)';
    $searchParam = '%' . $search . '%';
    $params = [$searchParam, $searchParam];
}

if ($statusFilter !== 'all') {
    $whereClause .= ' AND so.status = ?';
    $params[] = $statusFilter;
}

// Count total
$countQuery = "SELECT COUNT(*) FROM sales_orders so WHERE $whereClause";
$countStmt = $pdo->prepare($countQuery);
$countStmt->execute($params);
$totalOrders = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($totalOrders / $perPage));

// Fetch sales orders with pagination
$query = "
    SELECT so.*,
           COUNT(soi.id) AS item_count,
           COALESCE(SUM(soi.qty * soi.price), 0) AS total_amount
    FROM sales_orders so
    LEFT JOIN sales_order_items soi ON so.id = soi.sales_order_id
    WHERE $whereClause
    GROUP BY so.id
    ORDER BY so.created_at DESC
    LIMIT ? OFFSET ?
";
$stmt = $pdo->prepare($query);
$stmt->execute(array_merge($params, [$perPage, $offset]));
$salesOrders = $stmt->fetchAll();

// Get selected order details if ID provided
$selectedOrder = null;
$selectedOrderItems = [];
if (isset($_GET['id'])) {
    $orderId = (int)$_GET['id'];
    $orderStmt = $pdo->prepare('SELECT * FROM sales_orders WHERE id = ?');
    $orderStmt->execute([$orderId]);
    $selectedOrder = $orderStmt->fetch();
    
    if ($selectedOrder) {
        $itemsStmt = $pdo->prepare('
            SELECT soi.*, p.name AS product_name, p.sku, p.brand
            FROM sales_order_items soi
            LEFT JOIN products p ON soi.product_id = p.id
            WHERE soi.sales_order_id = ?
            ORDER BY soi.id
        ');
        $itemsStmt->execute([$orderId]);
        $selectedOrderItems = $itemsStmt->fetchAll();
    }
}

// Get products for dropdown
$products = $pdo->query('SELECT id, name, sku, brand, price, stock FROM products WHERE stock > 0 ORDER BY name')->fetchAll();
?>

<section class="flex flex-col gap-4">
  <div class="flex items-center justify-between mt-8">
    <div>
      <h1 class="text-3xl font-semibold text-slate-900">Sales Management</h1>
      <p class="text-slate-500 mt-1">Manage sales orders and transactions</p>
    </div>
    <button id="btnOpenSales" class="px-4 py-2 rounded-lg bg-emerald-500 text-white text-sm font-semibold shadow hover:bg-emerald-600">+ New Sales Order</button>
  </div>

  <?php if ($salesSuccess): ?>
    <div class="rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-700 px-4 py-3 text-sm"><?php echo htmlspecialchars($salesSuccess); ?></div>
  <?php endif; ?>
  <?php if ($salesErrors): ?>
    <div class="rounded-lg border border-rose-200 bg-rose-50 text-rose-700 px-4 py-3 text-sm">
      <?php foreach ($salesErrors as $err): ?>
        <div><?php echo htmlspecialchars($err); ?></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <!-- Search & Filter Bar -->
  <div class="flex items-center gap-3 mt-4">
    <div class="flex-1 flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 shadow-sm">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="m15.5 15.5 3 3M11 17a6 6 0 1 1 0-12 6 6 0 0 1 0 12Z" />
      </svg>
      <form method="GET" class="flex-1">
        <input type="hidden" name="page" value="sales">
        <input type="hidden" name="status" value="<?php echo htmlspecialchars($statusFilter); ?>">
        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by order code or customer name..." class="w-full bg-transparent text-sm text-slate-600 placeholder:text-slate-400 focus:outline-none">
      </form>
    </div>
    <select id="statusFilter" class="rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-600 shadow-sm">
      <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All Status</option>
      <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
      <option value="paid" <?php echo $statusFilter === 'paid' ? 'selected' : ''; ?>>Paid</option>
      <option value="shipped" <?php echo $statusFilter === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
      <option value="cancelled" <?php echo $statusFilter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
    </select>
  </div>

  <div class="grid gap-4 md:grid-cols-2">
    <!-- Sales Orders List -->
    <div class="space-y-3">
      <h2 class="text-lg font-semibold text-slate-900">Sales Orders</h2>
      <?php foreach ($salesOrders as $order): ?>
        <?php
          $statusColors = [
            'pending' => 'bg-yellow-100 text-yellow-700',
            'paid' => 'bg-emerald-100 text-emerald-700',
            'shipped' => 'bg-blue-100 text-blue-700',
            'cancelled' => 'bg-rose-100 text-rose-700'
          ];
          $statusColor = $statusColors[$order['status']] ?? 'bg-slate-100 text-slate-700';
        ?>
        <a href="?page=sales&id=<?php echo $order['id']; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($statusFilter); ?>&p=<?php echo $page; ?><?php echo isset($_GET['id']) && $_GET['id'] == $order['id'] ? '' : ''; ?>" class="block rounded-xl border <?php echo $selectedOrder && $selectedOrder['id'] == $order['id'] ? 'border-emerald-500 bg-emerald-50' : 'border-slate-200 bg-white'; ?> p-4 hover:shadow-md transition">
          <div class="flex items-start justify-between">
            <div class="flex-1">
              <div class="flex items-center gap-2 mb-1">
                <span class="text-sm font-semibold text-slate-900"><?php echo htmlspecialchars($order['code']); ?></span>
                <span class="text-xs px-2 py-0.5 rounded-full <?php echo $statusColor; ?>"><?php echo ucfirst($order['status']); ?></span>
              </div>
              <p class="text-sm text-slate-600"><?php echo htmlspecialchars($order['customer_name']); ?></p>
              <p class="text-xs text-slate-500 mt-1"><?php echo date('d M Y', strtotime($order['order_date'])); ?></p>
            </div>
            <div class="text-right">
              <p class="text-sm font-semibold text-slate-900">Rp <?php echo number_format((float)$order['total_amount'], 0, ',', '.'); ?></p>
              <p class="text-xs text-slate-500"><?php echo (int)$order['item_count']; ?> items</p>
            </div>
          </div>
        </a>
      <?php endforeach; ?>
      
      <?php if (count($salesOrders) === 0): ?>
        <div class="text-center py-12 text-slate-500 rounded-xl border border-slate-200 bg-white">
          <p>Tidak ada sales order ditemukan.</p>
        </div>
      <?php endif; ?>

      <!-- Pagination -->
      <?php if ($totalPages > 1): ?>
        <div class="flex items-center justify-between pt-4">
          <div class="text-sm text-slate-600">
            Page <span class="font-semibold text-emerald-500"><?php echo $page; ?></span> of <span class="font-semibold text-slate-700"><?php echo $totalPages; ?></span>
          </div>
          <div class="flex items-center gap-2">
            <a href="?page=sales<?php echo isset($_GET['id']) ? '&id=' . (int)$_GET['id'] : ''; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($statusFilter); ?>&p=<?php echo max(1, $page - 1); ?>" class="px-3 py-2 rounded-lg bg-slate-100 text-slate-600 hover:bg-slate-200 <?php echo $page <= 1 ? 'opacity-50 cursor-not-allowed pointer-events-none' : ''; ?>">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
              </svg>
            </a>
            <?php
              $startPage = max(1, $page - 1);
              $endPage = min($totalPages, $page + 1);
              for ($i = $startPage; $i <= $endPage; $i++):
            ?>
              <a href="?page=sales<?php echo isset($_GET['id']) ? '&id=' . (int)$_GET['id'] : ''; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($statusFilter); ?>&p=<?php echo $i; ?>" class="px-4 py-2 rounded-lg <?php echo $i === $page ? 'bg-emerald-500 text-white shadow' : 'bg-white border border-slate-200 hover:bg-slate-50'; ?> font-medium">
                <?php echo $i; ?>
              </a>
            <?php endfor; ?>
            <a href="?page=sales<?php echo isset($_GET['id']) ? '&id=' . (int)$_GET['id'] : ''; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($statusFilter); ?>&p=<?php echo min($totalPages, $page + 1); ?>" class="px-3 py-2 rounded-lg bg-emerald-500 text-white hover:bg-emerald-600 <?php echo $page >= $totalPages ? 'opacity-50 cursor-not-allowed pointer-events-none' : ''; ?>">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
              </svg>
            </a>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <!-- Order Details -->
    <div class="space-y-3">
      <?php if ($selectedOrder): ?>
        <div class="rounded-xl border border-slate-200 bg-white p-4">
          <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-slate-900">Order Details</h2>
            <span class="text-xs px-2 py-1 rounded-full <?php echo $statusColors[$selectedOrder['status']] ?? 'bg-slate-100 text-slate-700'; ?>"><?php echo ucfirst($selectedOrder['status']); ?></span>
          </div>
          
          <div class="space-y-2 text-sm mb-4">
            <div class="flex justify-between">
              <span class="text-slate-500">Order Code:</span>
              <span class="font-semibold text-slate-900"><?php echo htmlspecialchars($selectedOrder['code']); ?></span>
            </div>
            <div class="flex justify-between">
              <span class="text-slate-500">Customer:</span>
              <span class="font-semibold text-slate-900"><?php echo htmlspecialchars($selectedOrder['customer_name']); ?></span>
            </div>
            <div class="flex justify-between">
              <span class="text-slate-500">Date:</span>
              <span class="font-semibold text-slate-900"><?php echo date('d M Y', strtotime($selectedOrder['order_date'])); ?></span>
            </div>
          </div>

          <!-- Items List -->
          <div class="space-y-2 mb-4">
            <h3 class="text-sm font-semibold text-slate-900">Items</h3>
            <?php if (count($selectedOrderItems) > 0): ?>
              <?php 
                $subtotal = 0;
                foreach ($selectedOrderItems as $item):
                  $itemTotal = $item['qty'] * $item['price'];
                  $subtotal += $itemTotal;
              ?>
                <div class="flex items-center justify-between p-2 bg-slate-50 rounded-lg text-sm">
                  <div class="flex-1">
                    <p class="font-medium text-slate-900"><?php echo htmlspecialchars($item['product_name']); ?></p>
                    <p class="text-xs text-slate-500"><?php echo (int)$item['qty']; ?> x Rp <?php echo number_format($item['price'], 0, ',', '.'); ?></p>
                  </div>
                  <div class="text-right">
                    <p class="font-semibold text-slate-900">Rp <?php echo number_format($itemTotal, 0, ',', '.'); ?></p>
                    <?php if ($selectedOrder['status'] === 'pending'): ?>
                      <form method="POST" class="inline">
                        <input type="hidden" name="form" value="sales_delete_item">
                        <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                        <input type="hidden" name="sales_order_id" value="<?php echo $selectedOrder['id']; ?>">
                        <button onclick="return confirm('Hapus item ini?')" class="text-xs text-rose-500 hover:text-rose-700">Hapus</button>
                      </form>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>
              
              <div class="border-t border-slate-200 pt-2 mt-2">
                <div class="flex justify-between text-sm font-semibold text-slate-900">
                  <span>Total:</span>
                  <span>Rp <?php echo number_format($subtotal, 0, ',', '.'); ?></span>
                </div>
              </div>
            <?php else: ?>
              <p class="text-sm text-slate-500 text-center py-4">Belum ada items</p>
            <?php endif; ?>
          </div>

          <!-- Add Item Form (only for pending orders) -->
          <?php if ($selectedOrder && $selectedOrder['status'] === 'pending'): ?>
            <form method="POST" class="space-y-2 p-3 bg-slate-50 rounded-lg">
              <input type="hidden" name="form" value="sales_add_item">
              <input type="hidden" name="sales_order_id" value="<?php echo $selectedOrder['id']; ?>">
              <div class="grid grid-cols-2 gap-2">
                <select name="product_id" id="productSelect" required class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                  <option value="">Pilih Produk</option>
                  <?php foreach ($products as $prod): ?>
                    <option value="<?php echo $prod['id']; ?>" data-price="<?php echo $prod['price']; ?>" data-stock="<?php echo $prod['stock']; ?>">
                      <?php echo htmlspecialchars($prod['name']); ?> (Stock: <?php echo $prod['stock']; ?>)
                    </option>
                  <?php endforeach; ?>
                </select>
                <input type="number" name="qty" id="qtyInput" min="1" required placeholder="Qty" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
                <input type="number" name="price" id="priceInput" step="0.01" min="0" required readonly placeholder="Price" class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm cursor-not-allowed">
                <button type="submit" class="rounded-lg bg-emerald-500 text-white text-sm font-semibold py-2 hover:bg-emerald-600">Add Item</button>
              </div>
            </form>
          <?php endif; ?>

          <!-- Status Update -->
          <?php if ($selectedOrder['status'] !== 'cancelled'): ?>
            <form method="POST" class="mt-4">
              <input type="hidden" name="form" value="sales_update_status">
              <input type="hidden" name="id" value="<?php echo $selectedOrder['id']; ?>">
              <div class="flex gap-2">
                <?php if ($selectedOrder['status'] === 'pending'): ?>
                  <?php if (count($selectedOrderItems) > 0): ?>
                    <button type="submit" name="status" value="paid" class="flex-1 rounded-lg bg-emerald-500 text-white text-sm font-semibold py-2 hover:bg-emerald-600">Mark as Paid</button>
                  <?php else: ?>
                    <button type="button" disabled class="flex-1 rounded-lg bg-slate-300 text-slate-500 text-sm font-semibold py-2 cursor-not-allowed">Add items first</button>
                  <?php endif; ?>
                  <button type="submit" name="status" value="cancelled" class="flex-1 rounded-lg bg-rose-500 text-white text-sm font-semibold py-2 hover:bg-rose-600">Cancel</button>
                <?php elseif ($selectedOrder['status'] === 'paid'): ?>
                  <button type="submit" name="status" value="shipped" class="flex-1 rounded-lg bg-blue-500 text-white text-sm font-semibold py-2 hover:bg-blue-600">Mark as Shipped</button>
                  <button type="submit" name="status" value="cancelled" onclick="return confirm('Cancel order ini? Stock akan dikembalikan.')" class="flex-1 rounded-lg bg-rose-500 text-white text-sm font-semibold py-2 hover:bg-rose-600">Cancel</button>
                <?php elseif ($selectedOrder['status'] === 'shipped'): ?>
                  <div class="flex-1 rounded-lg bg-blue-100 text-blue-700 text-sm font-semibold py-2 text-center">Order Shipped</div>
                <?php endif; ?>
              </div>
            </form>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <div class="rounded-xl border border-slate-200 bg-white p-8 text-center text-slate-500">
          <p>Pilih sales order untuk melihat detail</p>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- Sales Order Modal -->
<div id="salesModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm hidden items-center justify-center z-40">
  <div class="bg-white w-full max-w-md rounded-2xl shadow-2xl border border-slate-200 overflow-hidden">
    <div class="px-6 py-4 bg-gradient-to-r from-emerald-500 to-teal-500 text-white flex items-center justify-between">
      <div>
        <h3 class="text-lg font-semibold">New Sales Order</h3>
        <p class="text-sm text-white/80">Create a new sales order</p>
      </div>
      <button id="btnCloseSales" class="text-white hover:text-slate-100 text-2xl leading-none">&times;</button>
    </div>
    <form method="POST" class="p-6 space-y-4">
      <input type="hidden" name="form" value="sales_create">
      <div class="space-y-1">
        <label class="text-sm font-medium text-slate-700">Customer Name *</label>
        <input name="customer_name" required class="w-full rounded-lg border border-slate-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500" placeholder="PT Mandiri Jaya" autofocus>
      </div>
      <div class="space-y-1">
        <label class="text-sm font-medium text-slate-700">Order Date *</label>
        <input type="date" name="order_date" required value="<?php echo date('Y-m-d'); ?>" class="w-full rounded-lg border border-slate-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
      </div>
      <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-200">
        <button type="button" id="btnCancelSales" class="px-5 py-2 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 font-medium">Cancel</button>
        <button type="submit" class="px-6 py-2 rounded-lg bg-gradient-to-r from-emerald-500 to-teal-500 text-white text-sm font-semibold shadow hover:opacity-90">Create Order</button>
      </div>
    </form>
  </div>
</div>

<script>
  const salesModal = document.getElementById('salesModal');
  const salesOpen = document.getElementById('btnOpenSales');
  const salesClose = document.getElementById('btnCloseSales');
  const salesCancel = document.getElementById('btnCancelSales');
  const statusFilter = document.getElementById('statusFilter');

  const toggleSales = (show) => {
    if (!salesModal) return;
    salesModal.classList.toggle('hidden', !show);
    salesModal.classList.toggle('flex', show);
  };

  if (salesOpen) salesOpen.addEventListener('click', () => toggleSales(true));
  if (salesClose) salesClose.addEventListener('click', () => toggleSales(false));
  if (salesCancel) salesCancel.addEventListener('click', () => toggleSales(false));
  salesModal?.addEventListener('click', (e) => {
    if (e.target === salesModal) toggleSales(false);
  });

  // Filter change
  if (statusFilter) {
    statusFilter.addEventListener('change', function() {
      const url = new URL(window.location);
      url.searchParams.set('status', this.value);
      url.searchParams.set('p', '1');
      // Preserve id if exists
      if (url.searchParams.has('id')) {
        // Keep id
      }
      window.location.href = url.toString();
    });
  }

  // Auto-fill price when product selected
  const productSelect = document.getElementById('productSelect');
  const priceInput = document.getElementById('priceInput');
  const qtyInput = document.getElementById('qtyInput');
  
  if (productSelect && priceInput) {
    productSelect.addEventListener('change', function() {
      const option = this.options[this.selectedIndex];
      if (option.value && option.dataset.price) {
        priceInput.value = option.dataset.price;
        // Validate stock
        const stock = parseInt(option.dataset.stock);
        if (qtyInput && qtyInput.value > stock) {
          alert('Stock tidak mencukupi. Stock tersedia: ' + stock);
          qtyInput.value = stock;
        }
        if (qtyInput) {
          qtyInput.setAttribute('max', stock);
        }
      } else {
        priceInput.value = '';
        if (qtyInput) {
          qtyInput.removeAttribute('max');
        }
      }
    });
  }
  
  // Validate qty against stock
  if (qtyInput && productSelect) {
    qtyInput.addEventListener('change', function() {
      const option = productSelect.options[productSelect.selectedIndex];
      if (option.value && option.dataset.stock) {
        const stock = parseInt(option.dataset.stock);
        if (parseInt(this.value) > stock) {
          alert('Stock tidak mencukupi. Stock tersedia: ' + stock);
          this.value = stock;
        }
      }
    });
  }
</script>

