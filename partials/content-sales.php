<?php
require_once __DIR__ . '/../config/db.php';
$pdo = getPdo();

// Get messages from session
$salesErrors = $_SESSION['sales_errors'] ?? [];
$salesSuccess = $_SESSION['sales_success'] ?? null;

// Clear session messages after reading
unset($_SESSION['sales_errors']);
unset($_SESSION['sales_success']);

// Function to get order details by ID
function getOrderDetails($pdo, $orderId) {
    try {
        $orderStmt = $pdo->prepare('SELECT * FROM sales_orders WHERE id = ?');
        $orderStmt->execute([$orderId]);
        $order = $orderStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($order) {
            $itemsStmt = $pdo->prepare('
                SELECT soi.*, p.name AS product_name, p.sku, p.brand
                FROM sales_order_items soi
                LEFT JOIN products p ON soi.product_id = p.id
                WHERE soi.sales_order_id = ?
                ORDER BY soi.id
            ');
            $itemsStmt->execute([$orderId]);
            $order['items'] = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        return $order;
    } catch (Exception $e) {
        error_log('Error in getOrderDetails: ' . $e->getMessage());
        return false;
    }
}

// Handle AJAX request for products list
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['ajax']) && $_GET['ajax'] === 'get_products') {
    // Clear any previous output
    if (ob_get_level()) {
        ob_clean();
    }
    
    try {
        $products = $pdo->query('SELECT id, name, sku, brand, price, stock FROM products WHERE stock > 0 ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
        
        // Ensure price is properly formatted as float
        foreach ($products as &$product) {
            $product['price'] = (float)$product['price'];
            $product['stock'] = (int)$product['stock'];
        }
        
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => true,
            'products' => $products
        ], JSON_UNESCAPED_UNICODE);
        exit;
    } catch (Exception $e) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => 'Error loading products: ' . $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// Handle AJAX request for order details
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['ajax']) && $_GET['ajax'] === 'order_detail') {
    // Clear any previous output
    if (ob_get_level()) {
        ob_clean();
    }
    
    $orderId = (int)($_GET['order_id'] ?? 0);
    if ($orderId > 0) {
        try {
            $order = getOrderDetails($pdo, $orderId);
            if ($order) {
                $statusColors = [
                    'pending' => 'bg-yellow-100 text-yellow-700',
                    'paid' => 'bg-emerald-100 text-emerald-700',
                    'shipped' => 'bg-blue-100 text-blue-700',
                    'cancelled' => 'bg-rose-100 text-rose-700'
                ];
                $statusColor = $statusColors[$order['status']] ?? 'bg-slate-100 text-slate-700';
                
                // Convert items to array format
                $orderData = [
                    'id' => (int)$order['id'],
                    'code' => $order['code'] ?? '',
                    'customer_name' => $order['customer_name'] ?? '',
                    'order_date' => $order['order_date'] ?? date('Y-m-d'),
                    'status' => $order['status'] ?? 'pending',
                    'created_at' => $order['created_at'] ?? null,
                    'items' => []
                ];
                
                if (isset($order['items']) && is_array($order['items'])) {
                    foreach ($order['items'] as $item) {
                        $orderData['items'][] = [
                            'id' => (int)($item['id'] ?? 0),
                            'product_id' => (int)($item['product_id'] ?? 0),
                            'product_name' => $item['product_name'] ?? 'Unknown Product',
                            'qty' => (int)($item['qty'] ?? 0),
                            'price' => (float)($item['price'] ?? 0),
                            'sku' => $item['sku'] ?? '',
                            'brand' => $item['brand'] ?? ''
                        ];
                    }
                }
                
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success' => true,
                    'order' => $orderData,
                    'statusColor' => $statusColor
                ], JSON_UNESCAPED_UNICODE);
                exit;
            } else {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success' => false,
                    'message' => 'Order not found'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
        } catch (Exception $e) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'message' => 'Error loading order: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    } else {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => 'Invalid order ID'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// Pagination & Search
$page = max(1, (int)($_GET['p'] ?? 1));
$perPage = 6;
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
        
        // Set success message in session
        $_SESSION['sales_success'] = 'Item berhasil ditambahkan ke order.';
        
        // Clear output buffer and redirect
        ob_clean();
        header('Location: index.php?page=sales&search=' . urlencode($search) . '&status=' . urlencode($statusFilter) . '&p=' . $page);
        exit;
    }
}

// Handle AJAX request for add item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['ajax']) && $_GET['ajax'] === 'add_item') {
    // Clear any previous output
    if (ob_get_level()) {
        ob_clean();
    }
    
    $salesOrderId = (int)($_POST['sales_order_id'] ?? 0);
    $productId = (int)($_POST['product_id'] ?? 0);
    $qty = (int)($_POST['qty'] ?? 0);
    $price = (float)($_POST['price'] ?? 0);
    
    header('Content-Type: application/json; charset=utf-8');
    
    if ($salesOrderId <= 0 || $productId <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Data tidak valid.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    if ($qty <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Quantity harus lebih dari 0.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Check stock
    $stockStmt = $pdo->prepare('SELECT stock FROM products WHERE id = ?');
    $stockStmt->execute([$productId]);
    $product = $stockStmt->fetch();
    
    if ($product && $qty > $product['stock']) {
        echo json_encode([
            'success' => false,
            'message' => 'Stock tidak mencukupi. Stock tersedia: ' . $product['stock']
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare('INSERT INTO sales_order_items (sales_order_id, product_id, qty, price) VALUES (?, ?, ?, ?)');
        $stmt->execute([$salesOrderId, $productId, $qty, $price]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Item berhasil ditambahkan ke order.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// Add Item to Sales Order (non-AJAX fallback)
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
        
        // Set success message in session
        $_SESSION['sales_success'] = 'Item berhasil ditambahkan ke order.';
        
        // Clear output buffer and redirect
        ob_clean();
        header('Location: index.php?page=sales&search=' . urlencode($search) . '&status=' . urlencode($statusFilter) . '&p=' . $page);
        exit;
    }
}

// Handle AJAX request for complete order
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['ajax']) && $_GET['ajax'] === 'complete_order') {
    // Clear any previous output
    if (ob_get_level()) {
        ob_clean();
    }
    
    $orderId = (int)($_POST['order_id'] ?? 0);
    
    header('Content-Type: application/json; charset=utf-8');
    
    if ($orderId <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Order ID tidak valid.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    try {
        // Check if order has items
        $itemsStmt = $pdo->prepare('SELECT COUNT(*) FROM sales_order_items WHERE sales_order_id = ?');
        $itemsStmt->execute([$orderId]);
        $itemCount = (int)$itemsStmt->fetchColumn();
        
        if ($itemCount === 0) {
            echo json_encode([
                'success' => false,
                'message' => 'Order harus memiliki minimal 1 item sebelum diselesaikan.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        // Update status to paid
        $updateStatusStmt = $pdo->prepare('UPDATE sales_orders SET status = "paid" WHERE id = ?');
        $updateStatusStmt->execute([$orderId]);
        
        // Reduce stock
        $pdo->beginTransaction();
        try {
            $itemsStmt = $pdo->prepare('SELECT product_id, qty FROM sales_order_items WHERE sales_order_id = ?');
            $itemsStmt->execute([$orderId]);
            $items = $itemsStmt->fetchAll();
            
            foreach ($items as $item) {
                // Reduce stock
                $updateStmt = $pdo->prepare('UPDATE products SET stock = stock - ? WHERE id = ?');
                $updateStmt->execute([$item['qty'], $item['product_id']]);
                
                // Log stock move
                $logStmt = $pdo->prepare('INSERT INTO stock_moves (product_id, move_type, reference, qty, note) VALUES (?, "out", ?, ?, ?)');
                $logStmt->execute([
                    $item['product_id'],
                    'SO-' . $orderId,
                    $item['qty'],
                    'Sales order ' . $orderId
                ]);
            }
            
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Order berhasil diselesaikan dan stock telah dikurangi.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
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
                $_SESSION['sales_success'] = 'Status berhasil diupdate.';
            } catch (Exception $e) {
                $pdo->rollBack();
                $_SESSION['sales_errors'] = ['Error: ' . $e->getMessage()];
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
                        $_SESSION['sales_success'] = 'Order dibatalkan dan stock dikembalikan.';
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        $_SESSION['sales_errors'] = ['Error: ' . $e->getMessage()];
                    }
                } else {
                    $updateStatusStmt = $pdo->prepare('UPDATE sales_orders SET status = ? WHERE id = ?');
                    $updateStatusStmt->execute([$newStatus, $id]);
                    $_SESSION['sales_success'] = 'Status berhasil diupdate.';
                }
            } else {
                $updateStatusStmt = $pdo->prepare('UPDATE sales_orders SET status = ? WHERE id = ?');
                $updateStatusStmt->execute([$newStatus, $id]);
                $_SESSION['sales_success'] = 'Status berhasil diupdate.';
            }
        }
        
        // Clear output buffer and redirect
        ob_clean();
        header('Location: index.php?page=sales&search=' . urlencode($search) . '&status=' . urlencode($statusFilter) . '&p=' . $page);
        exit;
    }
}

// Handle AJAX request for delete item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['ajax']) && $_GET['ajax'] === 'delete_item') {
    // Clear any previous output
    if (ob_get_level()) {
        ob_clean();
    }
    
    $itemId = (int)($_POST['item_id'] ?? 0);
    
    header('Content-Type: application/json; charset=utf-8');
    
    if ($itemId <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Item ID tidak valid.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare('DELETE FROM sales_order_items WHERE id = ?');
        $stmt->execute([$itemId]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Item berhasil dihapus dari order.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// Delete Item from Sales Order (non-AJAX fallback)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'sales_delete_item') {
    $itemId = (int)($_POST['item_id'] ?? 0);
    $salesOrderId = (int)($_POST['sales_order_id'] ?? 0);
    
    if ($itemId > 0) {
        $stmt = $pdo->prepare('DELETE FROM sales_order_items WHERE id = ?');
        $stmt->execute([$itemId]);
        
        $_SESSION['sales_success'] = 'Item berhasil dihapus dari order.';
        
        // Clear output buffer and redirect
        ob_clean();
        header('Location: index.php?page=sales&search=' . urlencode($search) . '&status=' . urlencode($statusFilter) . '&p=' . $page);
        exit;
    }
}

// Delete Sales Order
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'sales_delete_order') {
    $id = (int)($_POST['id'] ?? 0);
    
    if ($id > 0) {
        // Check if order is paid - if so, restore stock
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
                
                $pdo->commit();
            } catch (Exception $e) {
                $pdo->rollBack();
                $salesErrors[] = 'Error: ' . $e->getMessage();
            }
        }
        
        // Delete order (cascade will delete items)
        $stmt = $pdo->prepare('DELETE FROM sales_orders WHERE id = ?');
        $stmt->execute([$id]);
        
        $_SESSION['sales_success'] = 'Order berhasil dihapus.';
        
        // Clear output buffer and redirect
        ob_clean();
        header('Location: index.php?page=sales&search=' . urlencode($search) . '&status=' . urlencode($statusFilter) . '&p=' . $page);
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
    <div id="salesSuccessNotification" class="rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-700 px-4 py-3 text-sm flex items-center justify-between">
      <span><?php echo htmlspecialchars($salesSuccess); ?></span>
      <button onclick="this.parentElement.remove()" class="ml-4 text-emerald-700 hover:text-emerald-900">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
        </svg>
      </button>
    </div>
  <?php endif; ?>
  <?php if ($salesErrors): ?>
    <div id="salesErrorNotification" class="rounded-lg border border-rose-200 bg-rose-50 text-rose-700 px-4 py-3 text-sm">
      <div class="flex items-center justify-between">
        <div>
          <?php foreach ($salesErrors as $err): ?>
            <div><?php echo htmlspecialchars($err); ?></div>
          <?php endforeach; ?>
        </div>
        <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-rose-700 hover:text-rose-900">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>
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
      <div class="rounded-xl border border-slate-200 bg-white p-4 hover:shadow-md transition">
        <!-- Top Section: Order Code and Status -->
        <div class="flex items-center gap-2 mb-3">
          <span class="text-sm font-semibold text-slate-900"><?php echo htmlspecialchars($order['code']); ?></span>
          <span class="text-xs px-2 py-0.5 rounded-full <?php echo $statusColor; ?>"><?php echo ucfirst($order['status']); ?></span>
        </div>
        
        <!-- Middle Section: Details with Icons -->
        <div class="border-t border-b border-slate-200 py-3 mb-3">
          <div class="grid grid-cols-3 gap-4">
            <!-- Customer -->
            <div class="flex items-center gap-2">
              <div class="w-8 h-8 rounded bg-blue-100 flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                </svg>
              </div>
              <div>
                <p class="text-xs text-slate-500">Customer</p>
                <p class="text-sm font-medium text-slate-900"><?php echo htmlspecialchars($order['customer_name']); ?></p>
              </div>
            </div>
            
            <!-- Order Date -->
            <div class="flex items-center gap-2">
              <div class="w-8 h-8 rounded bg-purple-100 flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
              </div>
              <div>
                <p class="text-xs text-slate-500">Order Date</p>
                <p class="text-sm font-medium text-slate-900"><?php echo date('d/m/Y', strtotime($order['order_date'])); ?></p>
              </div>
            </div>
            
            <!-- Items -->
            <div class="flex items-center gap-2">
              <div class="w-8 h-8 rounded bg-orange-100 flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-orange-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                </svg>
              </div>
              <div>
                <p class="text-xs text-slate-500">Items</p>
                <p class="text-sm font-medium text-slate-900"><?php echo (int)$order['item_count']; ?> items</p>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Bottom Section: Total, Status Dropdown, and Action Buttons -->
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <span class="text-sm font-semibold text-emerald-600">Rp</span>
            <span class="text-sm font-semibold text-slate-900"><?php echo number_format((float)$order['total_amount'], 0, ',', '.'); ?></span>
          </div>
          <div class="flex items-center gap-2">
            <!-- Status Dropdown -->
            <form method="POST" class="inline" onsubmit="return confirm('Ubah status order ini?')">
              <input type="hidden" name="form" value="sales_update_status">
              <input type="hidden" name="id" value="<?php echo $order['id']; ?>">
              <select name="status" onchange="this.form.submit()" class="text-xs rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                <option value="pending" <?php echo $order['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                <option value="paid" <?php echo $order['status'] === 'paid' ? 'selected' : ''; ?>>Paid</option>
                <option value="shipped" <?php echo $order['status'] === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                <option value="cancelled" <?php echo $order['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
              </select>
            </form>
            
            <!-- View Detail Button -->
            <button onclick="openOrderDetail(<?php echo $order['id']; ?>)" class="w-8 h-8 rounded-lg bg-blue-100 hover:bg-blue-200 flex items-center justify-center transition">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
              </svg>
            </button>
            
            <!-- Delete Button -->
            <form method="POST" class="inline" onsubmit="return confirm('Hapus order ini? Tindakan ini tidak dapat dibatalkan.')">
              <input type="hidden" name="form" value="sales_delete_order">
              <input type="hidden" name="id" value="<?php echo $order['id']; ?>">
              <button type="submit" class="w-8 h-8 rounded-lg bg-red-100 hover:bg-red-200 flex items-center justify-center transition">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
              </button>
            </form>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
      
    <?php if (count($salesOrders) === 0): ?>
      <div class="text-center py-12 text-slate-500 rounded-xl border border-slate-200 bg-white">
        <p>Tidak ada sales order ditemukan.</p>
      </div>
    <?php endif; ?>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
      <div class="flex items-center justify-between rounded-2xl bg-gradient-to-r from-emerald-50 to-green-50 px-6 py-4 border border-slate-200 shadow-sm">
        <div class="flex items-center gap-3 text-sm text-slate-700">
          <span>Page</span>
          <span class="px-3 py-1 rounded-full bg-emerald-100 text-emerald-700 font-medium"><?php echo $page; ?></span>
          <span>of</span>
          <span class="px-3 py-1 rounded-full bg-emerald-100 text-emerald-700 font-medium"><?php echo $totalPages; ?></span>
          <span>• Total <?php echo $totalOrders; ?> orders</span>
        </div>
        <div class="flex items-center gap-2">
          <a href="?page=sales&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($statusFilter); ?>&p=<?php echo max(1, $page - 1); ?>" class="px-3 py-2 rounded-lg bg-slate-100 text-slate-600 hover:bg-slate-200 <?php echo $page <= 1 ? 'opacity-50 cursor-not-allowed pointer-events-none' : ''; ?>">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
          </a>
          <?php
            $startPage = max(1, $page - 1);
            $endPage = min($totalPages, $page + 1);
            for ($i = $startPage; $i <= $endPage; $i++):
          ?>
            <a href="?page=sales&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($statusFilter); ?>&p=<?php echo $i; ?>" class="px-4 py-2 rounded-lg <?php echo $i === $page ? 'bg-emerald-500 text-white shadow' : 'bg-white border border-slate-200 hover:bg-slate-50'; ?> font-medium">
              <?php echo $i; ?>
            </a>
          <?php endfor; ?>
          <a href="?page=sales&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($statusFilter); ?>&p=<?php echo min($totalPages, $page + 1); ?>" class="px-3 py-2 rounded-lg bg-emerald-500 text-white hover:bg-emerald-600 <?php echo $page >= $totalPages ? 'opacity-50 cursor-not-allowed pointer-events-none' : ''; ?>">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
          </a>
        </div>
      </div>
    <?php endif; ?>
</section>

<!-- Order Detail Modal -->
<div id="orderDetailModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm hidden items-center justify-center z-50">
  <div class="bg-white w-full max-w-2xl rounded-2xl shadow-2xl border border-slate-200 overflow-hidden max-h-[90vh] overflow-y-auto">
    <div class="px-6 py-4 bg-gradient-to-r from-emerald-500 to-teal-500 text-white flex items-center justify-between sticky top-0 z-10">
      <h3 class="text-lg font-semibold">Order Details</h3>
      <button id="btnCloseOrderDetail" class="text-white hover:text-slate-100 text-2xl leading-none">&times;</button>
    </div>
    <div id="orderDetailContent" class="p-6">
      <!-- Content will be loaded via AJAX -->
      <div class="text-center py-8 text-slate-500">Loading...</div>
    </div>
  </div>
</div>

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
          this.value = stock;
        }
      }
    });
  }

  // Order Detail Modal
  const orderDetailModal = document.getElementById('orderDetailModal');
  const orderDetailContent = document.getElementById('orderDetailContent');
  const orderDetailClose = document.getElementById('btnCloseOrderDetail');

  const toggleOrderDetail = (show) => {
    if (!orderDetailModal) return;
    orderDetailModal.classList.toggle('hidden', !show);
    orderDetailModal.classList.toggle('flex', show);
  };

  if (orderDetailClose) {
    orderDetailClose.addEventListener('click', () => toggleOrderDetail(false));
  }
  orderDetailModal?.addEventListener('click', (e) => {
    if (e.target === orderDetailModal) toggleOrderDetail(false);
  });
  
  // Function to reload order detail (without closing modal)
  function reloadOrderDetail(orderId) {
    if (!orderDetailContent) return;
    
    // Show loading indicator
    const currentContent = orderDetailContent.innerHTML;
    orderDetailContent.innerHTML = '<div class="text-center py-4 text-slate-500 text-sm">Memperbarui...</div>';
    
    // Fetch order details
    const url = new URL(window.location);
    url.searchParams.set('ajax', 'order_detail');
    url.searchParams.set('order_id', orderId);
    url.searchParams.set('page', 'sales');
    
    fetch(url.toString())
      .then(response => {
        if (!response.ok) {
          throw new Error('Network response was not ok');
        }
        return response.text();
      })
      .then(text => {
        try {
          const data = JSON.parse(text);
          if (data.success && data.order) {
            renderOrderDetail(data.order, data.statusColor);
          } else {
            console.error('Failed to reload order:', data.message);
            orderDetailContent.innerHTML = currentContent; // Restore previous content
          }
        } catch (e) {
          console.error('JSON Parse Error:', e);
          console.error('Response text:', text);
          orderDetailContent.innerHTML = currentContent; // Restore previous content
        }
      })
      .catch(error => {
        console.error('Fetch Error:', error);
        orderDetailContent.innerHTML = currentContent; // Restore previous content
      });
  }

  // Function to delete order item
  window.deleteOrderItem = function(itemId, orderId) {
    if (!confirm('Hapus item ini dari order?')) {
      return;
    }
    
    const url = new URL(window.location);
    const searchParams = new URLSearchParams();
    searchParams.set('ajax', 'delete_item');
    searchParams.set('page', 'sales');
    
    const formData = new FormData();
    formData.append('item_id', itemId);
    
    fetch('index.php?' + searchParams.toString(), {
      method: 'POST',
      body: formData
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        // Reload order detail
        reloadOrderDetail(orderId);
        showNotification('Item berhasil dihapus.', 'success');
      } else {
        showNotification(data.message || 'Gagal menghapus item.', 'error');
      }
    })
    .catch(error => {
      console.error('Error:', error);
      showNotification('Terjadi kesalahan saat menghapus item.', 'error');
    });
  };

  // Function to complete order
  window.completeOrder = function(orderId) {
    if (!confirm('Selesaikan order ini? Order akan diubah status menjadi "Paid" dan stock akan dikurangi.')) {
      return;
    }
    
    const url = new URL(window.location);
    const searchParams = new URLSearchParams();
    searchParams.set('ajax', 'complete_order');
    searchParams.set('page', 'sales');
    
    const formData = new FormData();
    formData.append('order_id', orderId);
    
    fetch('index.php?' + searchParams.toString(), {
      method: 'POST',
      body: formData
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        showNotification(data.message || 'Order berhasil diselesaikan.', 'success');
        // Close modal and reload page
        toggleOrderDetail(false);
        setTimeout(() => {
          window.location.reload();
        }, 1000);
      } else {
        showNotification(data.message || 'Gagal menyelesaikan order.', 'error');
      }
    })
    .catch(error => {
      console.error('Error:', error);
      showNotification('Terjadi kesalahan saat menyelesaikan order.', 'error');
    });
  };

  // Function to open order detail
  window.openOrderDetail = function(orderId) {
    if (!orderDetailContent) return;
    
    orderDetailContent.innerHTML = '<div class="text-center py-8 text-slate-500">Loading...</div>';
    toggleOrderDetail(true);
    
    // Fetch order details
    const url = new URL(window.location);
    url.searchParams.set('ajax', 'order_detail');
    url.searchParams.set('order_id', orderId);
    url.searchParams.set('page', 'sales');
    
    fetch(url.toString())
      .then(response => {
        if (!response.ok) {
          throw new Error('Network response was not ok');
        }
        return response.text();
      })
      .then(text => {
        try {
          const data = JSON.parse(text);
          if (data.success && data.order) {
            renderOrderDetail(data.order, data.statusColor);
          } else {
            orderDetailContent.innerHTML = `<div class="text-center py-8 text-rose-500">${data.message || 'Order not found'}</div>`;
          }
        } catch (e) {
          console.error('JSON Parse Error:', e);
          console.error('Response text:', text);
          orderDetailContent.innerHTML = '<div class="text-center py-8 text-rose-500">Error parsing response. Check console for details.</div>';
        }
      })
      .catch(error => {
        console.error('Fetch Error:', error);
        orderDetailContent.innerHTML = '<div class="text-center py-8 text-rose-500">Error loading order details. Please check console.</div>';
      });
  };

  // Function to render order detail
  function renderOrderDetail(order, statusColor) {
    const items = order.items || [];
    let itemsHtml = '';
    let total = 0;
    
    items.forEach(item => {
      const itemTotal = parseFloat(item.qty) * parseFloat(item.price);
      total += itemTotal;
      itemsHtml += `
        <div class="flex items-center justify-between py-3 border-b border-slate-100 last:border-0">
          <div class="flex-1">
            <p class="text-sm font-medium text-slate-900">${escapeHtml(item.product_name || 'Unknown Product')}</p>
            ${item.sku ? `<p class="text-xs text-slate-500">SKU: ${escapeHtml(item.sku)}</p>` : ''}
            <p class="text-xs text-slate-500">Qty: ${item.qty} x Rp ${formatNumber(item.price)}</p>
          </div>
          <div class="flex items-center gap-3">
            <p class="text-sm font-semibold text-slate-900">Rp ${formatNumber(itemTotal)}</p>
            ${order.status === 'pending' ? `
            <button onclick="deleteOrderItem(${item.id}, ${order.id})" class="w-8 h-8 rounded-lg bg-red-100 hover:bg-red-200 flex items-center justify-center transition" title="Hapus Item">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
              </svg>
            </button>
            ` : ''}
          </div>
        </div>
      `;
    });
    
    const orderDate = new Date(order.order_date + 'T00:00:00');
    const formattedDate = orderDate.toLocaleDateString('en-GB', { day: 'numeric', month: 'numeric', year: 'numeric' });
    
    orderDetailContent.innerHTML = `
      <!-- Order Summary -->
      <div class="bg-slate-50 rounded-lg p-4 mb-4">
        <div class="grid grid-cols-2 gap-4 text-sm">
          <div>
            <p class="text-slate-500 mb-1">Order Code</p>
            <p class="font-semibold text-slate-900">${escapeHtml(order.code)}</p>
          </div>
          <div>
            <p class="text-slate-500 mb-1">Status</p>
            <span class="inline-block text-xs px-2 py-1 rounded-full ${statusColor}">${capitalizeFirst(order.status)}</span>
          </div>
          <div>
            <p class="text-slate-500 mb-1">Customer</p>
            <p class="font-semibold text-slate-900">${escapeHtml(order.customer_name)}</p>
          </div>
          <div>
            <p class="text-slate-500 mb-1">Order Date</p>
            <p class="font-semibold text-slate-900">${formattedDate}</p>
          </div>
        </div>
      </div>

      <!-- Order Items -->
      <div class="mb-4">
        <h3 class="text-sm font-semibold text-slate-900 mb-3">Order Items</h3>
        ${items.length > 0 ? itemsHtml : '<p class="text-sm text-slate-500 text-center py-4">No items</p>'}
      </div>


      <!-- Total Amount -->
      <div class="border-t border-slate-200 pt-4 mt-4">
        <div class="flex items-center justify-between">
          <span class="text-sm font-semibold text-slate-900">Total Amount</span>
          <span class="text-lg font-bold text-slate-900">Rp ${formatNumber(total)}</span>
        </div>
      </div>

      <!-- Add Item Form (only for pending orders) -->
      ${order.status === 'pending' ? `
      <div class="border-t border-slate-200 pt-4 mt-4">
        <h3 class="text-sm font-semibold text-slate-900 mb-3">Add Item</h3>
        <form id="addItemForm" class="space-y-3 p-3 bg-slate-50 rounded-lg">
          <div class="grid grid-cols-2 gap-2">
            <select id="modalProductSelect" required class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
              <option value="">Pilih Produk</option>
            </select>
            <input type="number" id="modalQtyInput" min="1" value="1" required placeholder="Qty" class="rounded-lg border border-slate-200 px-3 py-2 text-sm">
            <input type="number" id="modalPriceInput" step="0.01" min="0" required readonly placeholder="Price (auto-filled)" class="rounded-lg border border-slate-200 bg-slate-100 px-3 py-2 text-sm cursor-not-allowed">
            <button type="submit" class="rounded-lg bg-emerald-500 text-white text-sm font-semibold py-2 hover:bg-emerald-600">Add Item</button>
          </div>
        </form>
      </div>
      
      <!-- Complete Order Button (only if order has items) -->
      ${items.length > 0 ? `
      <div class="border-t border-slate-200 pt-4 mt-4">
        <button onclick="completeOrder(${order.id})" class="w-full rounded-lg bg-gradient-to-r from-emerald-500 to-teal-500 text-white text-sm font-semibold py-3 hover:opacity-90 shadow-md">
          ✓ Selesaikan Order
        </button>
      </div>
      ` : ''}
      ` : ''}
    `;
    
    // Load products if status is pending
    if (order.status === 'pending') {
      loadProductsForModal(order.id);
    }
  }
  
  // Function to load products for modal with proper event handlers
  function loadProductsForModal(orderId) {
    const url = new URL(window.location);
    url.searchParams.set('ajax', 'get_products');
    url.searchParams.set('page', 'sales');
    
    fetch(url.toString())
      .then(response => {
        if (!response.ok) {
          throw new Error('Network response was not ok');
        }
        return response.json();
      })
      .then(data => {
        if (data.success && data.products) {
          const productSelect = document.getElementById('modalProductSelect');
          const priceInput = document.getElementById('modalPriceInput');
          const qtyInput = document.getElementById('modalQtyInput');
          
          if (!productSelect || !priceInput || !qtyInput) {
            console.error('Form elements not found');
            return;
          }
          
          // Populate product select
          productSelect.innerHTML = '<option value="">-- Pilih Produk --</option>';
          data.products.forEach(product => {
            const option = document.createElement('option');
            option.value = product.id;
            option.textContent = `${product.name} (Stock: ${product.stock})`;
            option.dataset.price = product.price;
            option.dataset.stock = product.stock;
            productSelect.appendChild(option);
          });
          
          // Store handlers for re-attachment after clone
          let productSelectChangeHandler = null;
          let qtyInputHandler = null;
          
          // PENTING: Auto-fill price handler
          productSelectChangeHandler = function() {
            const selectedOption = this.options[this.selectedIndex];
            const currentPriceInput = document.getElementById('modalPriceInput');
            const currentQtyInput = document.getElementById('modalQtyInput');
            
            // Reset price jika tidak ada produk dipilih
            if (!selectedOption.value || selectedOption.value === '') {
              if (currentPriceInput) currentPriceInput.value = '';
              if (currentQtyInput) currentQtyInput.removeAttribute('max');
              return;
            }
            
            // Auto-fill price dari data-price attribute
            const price = parseFloat(selectedOption.dataset.price);
            const stock = parseInt(selectedOption.dataset.stock);
            
            if (!isNaN(price) && price > 0) {
              if (currentPriceInput) {
                currentPriceInput.value = price;
                console.log('✓ Price auto-filled:', price);
              }
            } else {
              console.error('× Invalid price:', selectedOption.dataset.price);
              if (currentPriceInput) currentPriceInput.value = '';
            }
            
            // Set max quantity sesuai stock
            if (currentQtyInput && !isNaN(stock) && stock > 0) {
              currentQtyInput.setAttribute('max', stock);
              const currentQty = parseInt(currentQtyInput.value) || 1;
              if (currentQty > stock) {
                currentQtyInput.value = stock;
              }
            }
          };
          
          productSelect.addEventListener('change', productSelectChangeHandler);
          
          // Validate quantity against stock
          qtyInputHandler = function() {
            const currentProductSelect = document.getElementById('modalProductSelect');
            const currentQtyInput = document.getElementById('modalQtyInput');
            
            if (currentProductSelect) {
              const selectedOption = currentProductSelect.options[currentProductSelect.selectedIndex];
              if (selectedOption && selectedOption.dataset.stock) {
                const maxStock = parseInt(selectedOption.dataset.stock);
                const currentQty = parseInt(this.value);
                
                if (currentQty > maxStock) {
                  if (currentQtyInput) currentQtyInput.value = maxStock;
                } else if (currentQty < 1) {
                  if (currentQtyInput) currentQtyInput.value = 1;
                }
              }
            }
          };
          
          qtyInput.addEventListener('input', qtyInputHandler);
          
          // Handle form submission dengan validasi lengkap
          const addItemForm = document.getElementById('addItemForm');
          if (addItemForm) {
            // Remove existing handler dan buat baru
            const newForm = addItemForm.cloneNode(true);
            addItemForm.parentNode.replaceChild(newForm, addItemForm);
            
            // Get updated references
            const updatedProductSelect = document.getElementById('modalProductSelect');
            const updatedQtyInput = document.getElementById('modalQtyInput');
            const updatedPriceInput = document.getElementById('modalPriceInput');
            
            // Re-attach handlers to cloned elements
            if (updatedProductSelect && productSelectChangeHandler) {
              updatedProductSelect.addEventListener('change', productSelectChangeHandler);
            }
            if (updatedQtyInput && qtyInputHandler) {
              updatedQtyInput.addEventListener('input', qtyInputHandler);
            }
            
            // Submit handler dengan validasi ketat
            document.getElementById('addItemForm').addEventListener('submit', function(e) {
              e.preventDefault();
              
              const productId = updatedProductSelect.value.trim();
              const qty = updatedQtyInput.value.trim();
              const price = updatedPriceInput.value.trim();
              
              console.log('Form submit:', { productId, qty, price, orderId });
              
              // VALIDASI 1: Product harus dipilih
              if (!productId || productId === '') {
                showNotification('Mohon pilih produk terlebih dahulu.', 'error');
                updatedProductSelect.focus();
                return false;
              }
              
              // VALIDASI 2: Quantity harus valid
              if (!qty || qty === '' || parseInt(qty) <= 0) {
                showNotification('Mohon isi quantity dengan benar (minimal 1).', 'error');
                updatedQtyInput.focus();
                return false;
              }
              
              // VALIDASI 3: Price HARUS terisi (ini yang paling penting!)
              if (!price || price === '' || parseFloat(price) <= 0) {
                showNotification('Price belum terisi! Mohon pilih produk terlebih dahulu untuk auto-fill price.', 'error');
                updatedProductSelect.focus();
                return false;
              }
              
              // VALIDASI 4: Check stock
              const selectedOption = updatedProductSelect.options[updatedProductSelect.selectedIndex];
              const maxStock = parseInt(selectedOption.dataset.stock) || 0;
              if (parseInt(qty) > maxStock) {
                showNotification(`Stock tidak mencukupi. Stock tersedia: ${maxStock} unit`, 'error');
                updatedQtyInput.focus();
                return false;
              }
              
              // Semua validasi OK - submit form via AJAX
              console.log('✓ Validation passed - submitting form via AJAX');
              
              // Disable submit button
              const submitBtn = e.target.querySelector('button[type="submit"]');
              const originalText = submitBtn ? submitBtn.textContent : 'Add Item';
              if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Adding...';
              }
              
              // Build action URL
              const currentUrl = new URL(window.location);
              const searchParams = new URLSearchParams();
              searchParams.set('ajax', 'add_item');
              searchParams.set('page', 'sales');
              
              // Create form data
              const formData = new FormData();
              formData.append('sales_order_id', orderId);
              formData.append('product_id', productId);
              formData.append('qty', qty);
              formData.append('price', price);
              
              // Submit via AJAX
              fetch('index.php?' + searchParams.toString(), {
                method: 'POST',
                body: formData
              })
              .then(response => response.json())
              .then(data => {
                if (data.success) {
                  // Reset form
                  if (updatedProductSelect) updatedProductSelect.value = '';
                  if (updatedQtyInput) updatedQtyInput.value = '1';
                  if (updatedPriceInput) updatedPriceInput.value = '';
                  
                  // Reload order detail
                  reloadOrderDetail(orderId);
                  showNotification('Item berhasil ditambahkan.', 'success');
                } else {
                  showNotification(data.message || 'Gagal menambahkan item.', 'error');
                }
              })
              .catch(error => {
                console.error('Error:', error);
                showNotification('Terjadi kesalahan saat menambahkan item.', 'error');
              })
              .finally(() => {
                if (submitBtn) {
                  submitBtn.disabled = false;
                  submitBtn.textContent = originalText;
                }
              });
              
              return false;
            });
          }
        } else {
          console.error('Failed to load products:', data.message);
          showNotification('Gagal memuat daftar produk. Silakan refresh halaman.', 'error');
        }
      })
      .catch(error => {
        console.error('Error loading products:', error);
        showNotification('Error memuat produk. Silakan coba lagi.', 'error');
      });
  }

  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  function formatNumber(num) {
    return parseFloat(num).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
  }

  function capitalizeFirst(str) {
    return str.charAt(0).toUpperCase() + str.slice(1);
  }

  // Function to show inline notification (replaces alert)
  function showNotification(message, type = 'error') {
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 z-50 px-4 py-3 rounded-lg shadow-lg max-w-md ${
      type === 'error' ? 'bg-rose-50 border border-rose-200 text-rose-700' : 
      type === 'success' ? 'bg-emerald-50 border border-emerald-200 text-emerald-700' : 
      'bg-blue-50 border border-blue-200 text-blue-700'
    }`;
    notification.innerHTML = `
      <div class="flex items-center justify-between">
        <span class="text-sm font-medium">${escapeHtml(message)}</span>
        <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-current hover:opacity-70">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>
    `;
    document.body.appendChild(notification);
    setTimeout(() => {
      if (notification.parentElement) {
        notification.remove();
      }
    }, 5000);
  }
</script>

