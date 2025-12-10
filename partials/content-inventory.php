<?php
require_once __DIR__ . '/../config/db.php';
$pdo = getPdo();

$productErrors = [];
$productSuccess = null;

// Pagination & Search
$page = max(1, (int)($_GET['p'] ?? 1));
$perPage = 12;
$search = trim($_GET['search'] ?? '');
$offset = ($page - 1) * $perPage;

// Create product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'product_create') {
    $name = trim($_POST['name'] ?? '');
    $brand = trim($_POST['brand'] ?? '');
    $categoryId = $_POST['category_id'] ?? null;
    $supplierId = $_POST['supplier_id'] ?? null;
    $stock = (int)($_POST['stock'] ?? 0);
    $price = (float)($_POST['price'] ?? 0);
    $model = trim($_POST['model'] ?? '');
    $processor = trim($_POST['processor'] ?? '');
    $ram = trim($_POST['ram'] ?? '');
    $storage = trim($_POST['storage'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($name === '') {
        $productErrors[] = 'Nama produk wajib diisi.';
    }
    if ($price < 0) {
        $productErrors[] = 'Harga tidak valid.';
    }

    if (!$productErrors) {
        $sku = 'SKU-' . strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $brand . $name), 0, 6)) . '-' . rand(1000, 9999);
        $stmt = $pdo->prepare('INSERT INTO products (category_id, supplier_id, sku, name, brand, model, processor, ram, storage, description, price, stock) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $categoryId ?: null,
            $supplierId ?: null,
            $sku,
            $name,
            $brand,
            $model,
            $processor,
            $ram,
            $storage,
            $description,
            $price,
            $stock,
        ]);
        $productSuccess = 'Produk berhasil ditambahkan.';
        header('Location: ?page=inventory&search=' . urlencode($search));
        exit;
    }
}

// Update product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'product_update') {
    $id = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $brand = trim($_POST['brand'] ?? '');
    $categoryId = $_POST['category_id'] ?? null;
    $supplierId = $_POST['supplier_id'] ?? null;
    $stock = (int)($_POST['stock'] ?? 0);
    $price = (float)($_POST['price'] ?? 0);
    $model = trim($_POST['model'] ?? '');
    $processor = trim($_POST['processor'] ?? '');
    $ram = trim($_POST['ram'] ?? '');
    $storage = trim($_POST['storage'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($name === '') {
        $productErrors[] = 'Nama produk wajib diisi.';
    }
    if ($price < 0) {
        $productErrors[] = 'Harga tidak valid.';
    }

    if (!$productErrors && $id > 0) {
        $stmt = $pdo->prepare('UPDATE products SET category_id = ?, supplier_id = ?, name = ?, brand = ?, model = ?, processor = ?, ram = ?, storage = ?, description = ?, price = ?, stock = ? WHERE id = ?');
        $stmt->execute([
            $categoryId ?: null,
            $supplierId ?: null,
            $name,
            $brand,
            $model,
            $processor,
            $ram,
            $storage,
            $description,
            $price,
            $stock,
            $id,
        ]);
        $productSuccess = 'Produk berhasil diupdate.';
        header('Location: ?page=inventory&search=' . urlencode($search) . '&p=' . $page);
        exit;
    }
}

// Delete product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'product_delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $stmt = $pdo->prepare('DELETE FROM products WHERE id = ?');
        $stmt->execute([$id]);
        $productSuccess = 'Produk berhasil dihapus.';
        header('Location: ?page=inventory&search=' . urlencode($search) . '&p=' . $page);
        exit;
    }
}

// Fetch data
$categories = $pdo->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();
$suppliers = $pdo->query('SELECT id, name FROM suppliers WHERE status = "active" ORDER BY name')->fetchAll();

// Build query with search
$whereClause = '1=1';
$params = [];
if ($search !== '') {
    $whereClause .= ' AND (p.name LIKE ? OR p.brand LIKE ? OR p.model LIKE ? OR p.sku LIKE ?)';
    $searchParam = '%' . $search . '%';
    $params = [$searchParam, $searchParam, $searchParam, $searchParam];
}

// Count total
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM products p WHERE $whereClause");
$countStmt->execute($params);
$totalProducts = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($totalProducts / $perPage));

// Fetch products with pagination
$query = "
    SELECT p.*, c.name AS category_name, s.name AS supplier_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN suppliers s ON p.supplier_id = s.id
    WHERE $whereClause
    ORDER BY p.created_at DESC
    LIMIT ? OFFSET ?
";
$stmt = $pdo->prepare($query);
$stmt->execute(array_merge($params, [$perPage, $offset]));
$products = $stmt->fetchAll();

// Count good stock (stock > 3)
$goodStockWhere = 'stock > 3';
if ($search !== '') {
    $goodStockWhere .= ' AND (name LIKE ? OR brand LIKE ? OR model LIKE ? OR sku LIKE ?)';
}
$goodStockStmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE $goodStockWhere");
$goodStockStmt->execute($search !== '' ? $params : []);
$goodStockCount = (int)$goodStockStmt->fetchColumn();
?>

<section class="flex flex-col gap-4">
  <div class="flex items-center justify-between mt-8">
    <div>
      <h1 class="text-3xl font-semibold text-slate-900">Inventory Management</h1>
      <p class="text-slate-500 mt-1">Kelola produk dan stok sesuai database.</p>
    </div>
    <button id="btnOpenProduct" class="px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-semibold shadow hover:bg-indigo-700">+ Add Product</button>
  </div>

  <?php if ($productSuccess): ?>
    <div class="rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-700 px-4 py-3 text-sm"><?php echo htmlspecialchars($productSuccess); ?></div>
  <?php endif; ?>
  <?php if ($productErrors): ?>
    <div class="rounded-lg border border-rose-200 bg-rose-50 text-rose-700 px-4 py-3 text-sm">
      <?php foreach ($productErrors as $err): ?>
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
        <input type="hidden" name="page" value="inventory">
        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by name, brand, or model..." class="w-full bg-transparent text-sm text-slate-600 placeholder:text-slate-400 focus:outline-none">
      </form>
    </div>
    <button class="h-10 w-10 rounded-lg border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 flex items-center justify-center shadow-sm">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 3c.132 0 .263 0 .393 0a7.5 7.5 0 0 1 7.92 12.39 7.5 7.5 0 0 1-14.626 0A7.5 7.5 0 0 1 5.607 3H6a7.5 7.5 0 0 1 6 0Z" />
      </svg>
    </button>
    <button class="h-10 w-10 rounded-lg border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 flex items-center justify-center shadow-sm">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 6h18M7 12h10M5 18h14" />
      </svg>
    </button>
  </div>

  <!-- Product Info -->
  <div class="flex items-center justify-between text-sm text-slate-600">
    <div>
      Showing <?php echo $totalProducts > 0 ? $offset + 1 : 0; ?>-<?php echo min($offset + $perPage, $totalProducts); ?> of <?php echo $totalProducts; ?> products
    </div>
    <div class="flex items-center gap-2">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
      </svg>
      <span><?php echo $goodStockCount; ?> in good stock</span>
    </div>
  </div>

  <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
    <?php foreach ($products as $product): ?>
      <?php
        $lowStock = $product['stock'] <= 3;
        $badgeClass = $lowStock ? 'bg-rose-500 text-white' : 'bg-emerald-100 text-emerald-700';
        $badgeText = $lowStock ? 'Low Stock' : 'In Stock';
      ?>
      <article class="rounded-2xl bg-white shadow-md border border-slate-100 overflow-hidden">
        <div class="relative h-20 bg-gradient-to-r from-indigo-500 via-fuchsia-500 to-cyan-400 px-4 flex items-center gap-3">
          <div class="h-11 w-11 rounded-2xl bg-white/95 text-indigo-500 flex items-center justify-center text-xl shadow-sm">🗃</div>
          <span class="absolute right-3 top-3 rounded-full <?php echo $badgeClass; ?> text-[11px] px-2 py-0.5 shadow-sm"><?php echo $badgeText; ?></span>
        </div>
        <div class="px-4 py-3 flex flex-col gap-2">
          <div class="flex items-start justify-between">
            <div>
              <p class="text-sm font-semibold text-slate-900"><?php echo htmlspecialchars($product['name']); ?></p>
              <p class="text-[12px] text-slate-500"><?php echo htmlspecialchars(trim(($product['brand'] ? $product['brand'] . ' • ' : '') . ($product['model'] ?? ''))); ?></p>
              <?php if ($product['category_name']): ?>
                <p class="text-[11px] text-slate-400"><?php echo htmlspecialchars($product['category_name']); ?></p>
              <?php endif; ?>
            </div>
          </div>

          <div class="flex flex-col gap-2 text-[12px] text-slate-600">
            <div class="flex justify-between gap-3">
              <span class="text-slate-500">Processor</span>
              <span class="text-slate-800 font-medium leading-tight text-right"><?php echo htmlspecialchars($product['processor'] ?: '-'); ?></span>
            </div>
            <div class="flex justify-between gap-3">
              <span class="text-slate-500">RAM / Storage</span>
              <span class="text-slate-800 font-medium leading-tight text-right"><?php echo htmlspecialchars(trim(($product['ram'] ?: '-') . ' / ' . ($product['storage'] ?: '-'), ' /')); ?></span>
            </div>
          </div>

          <div class="border border-slate-100 rounded-xl p-3 bg-slate-50 text-[12px] text-slate-600 mt-1">
            <div class="grid grid-cols-2 items-center">
              <div>
                <p class="text-xs text-slate-400">Stock</p>
                <p class="text-lg font-semibold text-slate-900 leading-tight"><?php echo (int)$product['stock']; ?></p>
              </div>
              <div class="text-right">
                <p class="text-xs text-slate-400">Price</p>
                <p class="text-2xl font-semibold text-slate-900 leading-tight">Rp <?php echo number_format((float)$product['price'], 0, ',', '.'); ?></p>
              </div>
            </div>
          </div>

          <?php if ($product['description']): ?>
            <div class="text-[12px] text-slate-500 leading-snug whitespace-pre-line">
              <?php echo htmlspecialchars($product['description']); ?>
            </div>
          <?php endif; ?>

          <div class="text-[12px] text-slate-500 mt-1">
            <p class="text-xs text-slate-400">Supplier</p>
            <p class="font-semibold text-slate-800"><?php echo htmlspecialchars($product['supplier_name'] ?: '-'); ?></p>
          </div>
        </div>
        <div class="flex items-center gap-2 px-4 pb-4">
          <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($product)); ?>)" class="flex-1 rounded-lg bg-indigo-500 text-white text-sm font-semibold py-2 shadow hover:bg-indigo-600 flex items-center justify-center gap-1">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21h-8.5A2.25 2.25 0 0 1 5 18.75V10.25A2.25 2.25 0 0 1 7.25 8h4.75" />
            </svg>
            Edit
          </button>
          <form method="POST" class="flex-1">
            <input type="hidden" name="form" value="product_delete">
            <input type="hidden" name="id" value="<?php echo (int)$product['id']; ?>">
            <button class="w-full rounded-lg border border-rose-200 text-rose-600 text-sm font-semibold py-2 bg-white hover:bg-rose-50 flex items-center justify-center gap-1" onclick="return confirm('Hapus produk ini?')">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 6h12M9 6V4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2m-7 5v6m4-6v6M4 6h16l-1 14H5L4 6Z" />
              </svg>
              Delete
            </button>
          </form>
        </div>
      </article>
    <?php endforeach; ?>
  </div>

  <?php if (count($products) === 0): ?>
    <div class="text-center py-12 text-slate-500">
      <p>Tidak ada produk ditemukan.</p>
    </div>
  <?php endif; ?>

  <!-- Pagination -->
  <?php if ($totalPages > 1): ?>
    <div class="flex items-center justify-between rounded-2xl bg-gradient-to-r from-blue-50 via-purple-50 to-pink-50 px-6 py-4 border border-slate-200">
      <div class="flex items-center gap-3 text-sm text-slate-700">
        <span>Page</span>
        <span class="px-3 py-1 rounded-full bg-blue-100 text-blue-700 font-medium"><?php echo $page; ?></span>
        <span>of</span>
        <span class="px-3 py-1 rounded-full bg-purple-100 text-purple-700 font-medium"><?php echo $totalPages; ?></span>
        <span>• Total <?php echo $totalProducts; ?> products</span>
      </div>
      <div class="flex items-center gap-2">
        <a href="?page=inventory&search=<?php echo urlencode($search); ?>&p=<?php echo max(1, $page - 1); ?>" class="px-3 py-2 rounded-lg bg-slate-100 text-slate-600 hover:bg-slate-200 <?php echo $page <= 1 ? 'opacity-50 cursor-not-allowed pointer-events-none' : ''; ?>">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
        </a>
        <?php
          $startPage = max(1, $page - 1);
          $endPage = min($totalPages, $page + 1);
          if ($startPage > 1) $startPage = max(1, $page - 1);
          if ($endPage < $totalPages) $endPage = min($totalPages, $page + 1);
          for ($i = $startPage; $i <= $endPage; $i++):
        ?>
          <a href="?page=inventory&search=<?php echo urlencode($search); ?>&p=<?php echo $i; ?>" class="px-4 py-2 rounded-lg <?php echo $i === $page ? 'bg-gradient-to-r from-blue-500 to-purple-500 text-white shadow' : 'bg-white border border-slate-200 hover:bg-slate-50'; ?> font-medium">
            <?php echo $i; ?>
          </a>
        <?php endfor; ?>
        <a href="?page=inventory&search=<?php echo urlencode($search); ?>&p=<?php echo min($totalPages, $page + 1); ?>" class="px-3 py-2 rounded-lg bg-gradient-to-r from-blue-500 to-purple-500 text-white hover:from-blue-600 hover:to-purple-600 <?php echo $page >= $totalPages ? 'opacity-50 cursor-not-allowed pointer-events-none' : ''; ?>">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
          </svg>
        </a>
      </div>
    </div>
  <?php endif; ?>
</section>

<!-- Product Modal (Add/Edit) -->
<div id="productModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm hidden items-center justify-center z-40">
  <div class="bg-white w-full max-w-4xl rounded-2xl shadow-2xl border border-slate-200 overflow-hidden max-h-[90vh] overflow-y-auto">
    <div class="px-6 py-4 bg-gradient-to-r from-indigo-500 via-fuchsia-500 to-cyan-400 text-white flex items-center justify-between sticky top-0">
      <div>
        <h3 id="modalTitle" class="text-lg font-semibold">Add New Product</h3>
        <p class="text-sm text-white/80">Fill in the product details below</p>
      </div>
      <button id="btnCloseProduct" class="text-white hover:text-slate-100 text-2xl leading-none">&times;</button>
    </div>
    <form id="productForm" method="POST" class="p-6 space-y-4">
      <input type="hidden" name="form" id="formType" value="product_create">
      <input type="hidden" name="id" id="productId" value="">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="space-y-1 md:col-span-2">
          <label class="text-sm font-medium text-slate-700">Product Name *</label>
          <input name="name" id="inputName" required class="w-full rounded-lg border border-slate-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="e.g., ThinkPad X1 Carbon" autofocus>
        </div>
        <div class="space-y-1">
          <label class="text-sm font-medium text-slate-700">Brand *</label>
          <input name="brand" id="inputBrand" required class="w-full rounded-lg border border-slate-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="e.g., Lenovo">
        </div>
        <div class="space-y-1">
          <label class="text-sm font-medium text-slate-700">Model</label>
          <input name="model" id="inputModel" class="w-full rounded-lg border border-slate-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="e.g., X1 Carbon Gen 11">
        </div>
        <div class="space-y-1">
          <label class="text-sm font-medium text-slate-700">Processor *</label>
          <input name="processor" id="inputProcessor" required class="w-full rounded-lg border border-slate-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="e.g., Intel Core i7-1365U">
        </div>
        <div class="space-y-1">
          <label class="text-sm font-medium text-slate-700">RAM *</label>
          <input name="ram" id="inputRam" required class="w-full rounded-lg border border-slate-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="e.g., 16GB">
        </div>
        <div class="space-y-1">
          <label class="text-sm font-medium text-slate-700">Storage *</label>
          <input name="storage" id="inputStorage" required class="w-full rounded-lg border border-slate-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="e.g., 512GB SSD">
        </div>
        <div class="space-y-1">
          <label class="text-sm font-medium text-slate-700">Supplier *</label>
          <select name="supplier_id" id="inputSupplier" required class="w-full rounded-lg border border-slate-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option value="">-- Pilih --</option>
            <?php foreach ($suppliers as $sup): ?>
              <option value="<?php echo (int)$sup['id']; ?>"><?php echo htmlspecialchars($sup['name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="space-y-1">
          <label class="text-sm font-medium text-slate-700">Stock *</label>
          <input type="number" name="stock" id="inputStock" min="0" required class="w-full rounded-lg border border-slate-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" value="0">
        </div>
        <div class="space-y-1 md:col-span-2">
          <label class="text-sm font-medium text-slate-700">Price (Rp) *</label>
          <input type="number" step="1" name="price" id="inputPrice" min="0" required class="w-full rounded-lg border border-slate-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" value="0" placeholder="40000000">
        </div>
        <div class="space-y-1 md:col-span-2">
          <label class="text-sm font-medium text-slate-700">Description</label>
          <textarea name="description" id="inputDescription" rows="3" class="w-full rounded-lg border border-slate-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="Catatan produk, opsi konfigurasi, atau catatan stok..."></textarea>
        </div>
        <div class="space-y-1">
          <label class="text-sm font-medium text-slate-700">Category</label>
          <select name="category_id" id="inputCategory" class="w-full rounded-lg border border-slate-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <option value="">-- Pilih --</option>
            <?php foreach ($categories as $cat): ?>
              <option value="<?php echo (int)$cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-200">
        <button type="button" id="btnCancelProduct" class="px-5 py-2 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 font-medium">Cancel</button>
        <button type="submit" id="submitBtn" class="px-6 py-2 rounded-lg bg-gradient-to-r from-indigo-500 via-fuchsia-500 to-cyan-400 text-white text-sm font-semibold shadow hover:opacity-90">Add Product</button>
      </div>
    </form>
  </div>
</div>

<script>
  const modal = document.getElementById('productModal');
  const openBtn = document.getElementById('btnOpenProduct');
  const closeBtn = document.getElementById('btnCloseProduct');
  const cancelBtn = document.getElementById('btnCancelProduct');
  const form = document.getElementById('productForm');
  const formType = document.getElementById('formType');
  const productId = document.getElementById('productId');
  const modalTitle = document.getElementById('modalTitle');
  const submitBtn = document.getElementById('submitBtn');

  const toggleModal = (show) => {
    if (!modal) return;
    modal.classList.toggle('hidden', !show);
    modal.classList.toggle('flex', show);
    if (!show) {
      form.reset();
      formType.value = 'product_create';
      productId.value = '';
      modalTitle.textContent = 'Add New Product';
      submitBtn.textContent = 'Add Product';
    }
  };

  function openEditModal(product) {
    formType.value = 'product_update';
    productId.value = product.id;
    document.getElementById('inputName').value = product.name || '';
    document.getElementById('inputBrand').value = product.brand || '';
    document.getElementById('inputModel').value = product.model || '';
    document.getElementById('inputProcessor').value = product.processor || '';
    document.getElementById('inputRam').value = product.ram || '';
    document.getElementById('inputStorage').value = product.storage || '';
    document.getElementById('inputSupplier').value = product.supplier_id || '';
    document.getElementById('inputStock').value = product.stock || 0;
    document.getElementById('inputPrice').value = product.price || 0;
    document.getElementById('inputDescription').value = product.description || '';
    document.getElementById('inputCategory').value = product.category_id || '';
    modalTitle.textContent = 'Edit Product';
    submitBtn.textContent = 'Update Product';
    toggleModal(true);
  }

  if (openBtn) openBtn.addEventListener('click', () => toggleModal(true));
  if (closeBtn) closeBtn.addEventListener('click', () => toggleModal(false));
  if (cancelBtn) cancelBtn.addEventListener('click', () => toggleModal(false));
  modal?.addEventListener('click', (e) => {
    if (e.target === modal) toggleModal(false);
  });
</script>
