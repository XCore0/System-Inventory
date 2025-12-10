<?php
require_once __DIR__ . '/../config/db.php';
$pdo = getPdo();

$supplierErrors = [];
$supplierSuccess = null;

// Pagination & Search
$page = max(1, (int)($_GET['p'] ?? 1));
$perPage = 9;
$search = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? 'all';
$offset = ($page - 1) * $perPage;

// Create supplier
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'supplier_create') {
    $name = trim($_POST['name'] ?? '');
    $contact = trim($_POST['contact_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');

    if ($name === '') {
        $supplierErrors[] = 'Nama supplier wajib diisi.';
    }

    if (!$supplierErrors) {
        $stmt = $pdo->prepare('INSERT INTO suppliers (name, contact_name, email, phone, address, city, status) VALUES (?, ?, ?, ?, ?, ?, "active")');
        $stmt->execute([$name, $contact, $email, $phone, $address, $city]);
        $supplierSuccess = 'Supplier berhasil ditambahkan.';
        header('Location: ?page=suppliers&search=' . urlencode($search) . '&status=' . urlencode($statusFilter));
        exit;
    }
}

// Update supplier
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'supplier_update') {
    $id = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $contact = trim($_POST['contact_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');

    if ($name === '') {
        $supplierErrors[] = 'Nama supplier wajib diisi.';
    }

    if (!$supplierErrors && $id > 0) {
        $stmt = $pdo->prepare('UPDATE suppliers SET name = ?, contact_name = ?, email = ?, phone = ?, address = ?, city = ? WHERE id = ?');
        $stmt->execute([$name, $contact, $email, $phone, $address, $city, $id]);
        $supplierSuccess = 'Supplier berhasil diupdate.';
        header('Location: ?page=suppliers&search=' . urlencode($search) . '&status=' . urlencode($statusFilter) . '&p=' . $page);
        exit;
    }
}

// Delete supplier
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'supplier_delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $stmt = $pdo->prepare('DELETE FROM suppliers WHERE id = ?');
        $stmt->execute([$id]);
        $supplierSuccess = 'Supplier berhasil dihapus.';
        header('Location: ?page=suppliers&search=' . urlencode($search) . '&status=' . urlencode($statusFilter) . '&p=' . $page);
        exit;
    }
}

// Build query with search and filter
$whereClause = '1=1';
$params = [];

if ($search !== '') {
    $whereClause .= ' AND (s.name LIKE ? OR s.contact_name LIKE ? OR s.email LIKE ?)';
    $searchParam = '%' . $search . '%';
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam]);
}

if ($statusFilter !== 'all') {
    $whereClause .= ' AND s.status = ?';
    $params[] = $statusFilter;
}

// Count total
$countQuery = "SELECT COUNT(*) FROM suppliers s WHERE $whereClause";
$countStmt = $pdo->prepare($countQuery);
$countStmt->execute($params);
$totalSuppliers = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($totalSuppliers / $perPage));

// Fetch suppliers with pagination and statistics
$query = "
    SELECT s.*,
           COUNT(DISTINCT p.id) AS product_count,
           COALESCE(SUM(p.stock), 0) AS total_stock,
           COALESCE(SUM(p.price * p.stock), 0) AS total_value
    FROM suppliers s
    LEFT JOIN products p ON s.id = p.supplier_id
    WHERE $whereClause
    GROUP BY s.id
    ORDER BY s.created_at DESC
    LIMIT ? OFFSET ?
";
$stmt = $pdo->prepare($query);
$stmt->execute(array_merge($params, [$perPage, $offset]));
$suppliers = $stmt->fetchAll();
?>

<section class="flex flex-col gap-4">
  <div class="flex items-center justify-between mt-8">
    <div>
      <h1 class="text-3xl font-semibold text-slate-900">Supplier Management</h1>
      <p class="text-slate-500 mt-1">Manage your supplier relationships</p>
    </div>
    <button id="btnOpenSupplier" class="px-4 py-2 rounded-lg bg-rose-500 text-white text-sm font-semibold shadow hover:bg-rose-600">+ Add Supplier</button>
  </div>

  <?php if ($supplierSuccess): ?>
    <div class="rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-700 px-4 py-3 text-sm"><?php echo htmlspecialchars($supplierSuccess); ?></div>
  <?php endif; ?>
  <?php if ($supplierErrors): ?>
    <div class="rounded-lg border border-rose-200 bg-rose-50 text-rose-700 px-4 py-3 text-sm">
      <?php foreach ($supplierErrors as $err): ?>
        <div><?php echo htmlspecialchars($err); ?></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <!-- Search Bar -->
  <div class="flex items-center gap-3 mt-4">
    <div class="flex-1 flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 shadow-sm">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="m15.5 15.5 3 3M11 17a6 6 0 1 1 0-12 6 6 0 0 1 0 12Z" />
      </svg>
      <form method="GET" class="flex-1">
        <input type="hidden" name="page" value="suppliers">
        <input type="hidden" name="status" value="<?php echo htmlspecialchars($statusFilter); ?>">
        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search suppliers by name, contact, or email..." class="w-full bg-transparent text-sm text-slate-600 placeholder:text-slate-400 focus:outline-none">
      </form>
    </div>
    <select id="statusFilter" class="rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-600 shadow-sm">
      <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All Status</option>
      <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
      <option value="inactive" <?php echo $statusFilter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
    </select>
  </div>

  <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
    <?php foreach ($suppliers as $sup): ?>
      <article class="rounded-2xl bg-white shadow-md border border-slate-100 overflow-hidden">
        <!-- Header with gradient -->
        <div class="relative h-20 bg-gradient-to-r from-orange-400 via-rose-400 to-pink-500 px-4 flex items-center gap-3">
          <div class="h-11 w-11 rounded-xl bg-white/95 text-orange-600 flex items-center justify-center shadow-sm">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 0 0-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 0 1 5.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 0 1 9.288 0M15 7a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2 2 0 1 1-4 0 2 2 0 0 1 4 0ZM7 10a2 2 0 1 1-4 0 2 2 0 0 1 4 0Z" />
            </svg>
          </div>
          <h3 class="text-sm font-semibold text-white flex-1"><?php echo htmlspecialchars($sup['name']); ?></h3>
        </div>

        <!-- Content -->
        <div class="px-4 py-4 flex flex-col gap-3">
          <!-- Contact Details -->
          <div class="space-y-3 text-sm">
            <?php if ($sup['contact_name']): ?>
              <div class="flex items-start gap-3">
                <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-blue-100 text-blue-600 flex-shrink-0">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 0 0-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 0 1 5.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 0 1 9.288 0M15 7a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2 2 0 1 1-4 0 2 2 0 0 1 4 0ZM7 10a2 2 0 1 1-4 0 2 2 0 0 1 4 0Z" />
                  </svg>
                </span>
                <div class="flex-1 min-w-0">
                  <p class="text-xs text-slate-500 mb-1">Contact Person</p>
                  <p class="text-sm font-semibold text-slate-900"><?php echo htmlspecialchars($sup['contact_name']); ?></p>
                </div>
              </div>
            <?php endif; ?>
            <?php if ($sup['email']): ?>
              <div class="flex items-start gap-3">
                <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-100 text-emerald-600 flex-shrink-0">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
                  </svg>
                </span>
                <div class="flex-1 min-w-0">
                  <p class="text-xs text-slate-500 mb-1">Email</p>
                  <p class="text-sm font-semibold text-slate-900 truncate"><?php echo htmlspecialchars($sup['email']); ?></p>
                </div>
              </div>
            <?php endif; ?>
            <?php if ($sup['phone']): ?>
              <div class="flex items-start gap-3">
                <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-purple-100 text-purple-600 flex-shrink-0">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z" />
                  </svg>
                </span>
                <div class="flex-1 min-w-0">
                  <p class="text-xs text-slate-500 mb-1">Phone</p>
                  <p class="text-sm font-semibold text-slate-900"><?php echo htmlspecialchars($sup['phone']); ?></p>
                </div>
              </div>
            <?php endif; ?>
            <?php if ($sup['address'] || $sup['city']): ?>
              <div class="flex items-start gap-3">
                <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-orange-100 text-orange-600 flex-shrink-0">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
                  </svg>
                </span>
                <div class="flex-1 min-w-0">
                  <p class="text-xs text-slate-500 mb-1">Address</p>
                  <p class="text-sm font-semibold text-slate-900 leading-snug"><?php echo htmlspecialchars(trim(($sup['address'] ?: '') . ($sup['address'] && $sup['city'] ? ', ' : '') . ($sup['city'] ?: ''))); ?></p>
                </div>
              </div>
            <?php endif; ?>
          </div>

          <!-- Statistics -->
          <div class="grid grid-cols-3 gap-2 pt-2 border-t border-slate-100">
            <div class="text-center">
              <p class="text-xs text-slate-500">Products</p>
              <p class="text-lg font-semibold text-slate-900"><?php echo (int)$sup['product_count']; ?></p>
            </div>
            <div class="text-center">
              <p class="text-xs text-slate-500">Stock</p>
              <p class="text-lg font-semibold text-slate-900"><?php echo number_format((int)$sup['total_stock'], 0, ',', '.'); ?></p>
            </div>
            <div class="text-center">
              <p class="text-xs text-slate-500">Value</p>
              <p class="text-lg font-semibold text-slate-900">
                <?php
                  $value = (float)$sup['total_value'];
                  if ($value >= 1000000) {
                    echo number_format($value / 1000000, 1, '.', '') . 'M';
                  } else {
                    echo number_format($value / 1000, 0, '.', '') . 'K';
                  }
                ?>
              </p>
            </div>
          </div>

          <!-- Action Buttons -->
          <div class="flex items-center gap-2 pt-2">
            <button onclick="openEditSupplier(<?php echo htmlspecialchars(json_encode($sup)); ?>)" class="flex-1 rounded-lg bg-orange-500 text-white text-sm font-semibold py-2 shadow hover:bg-orange-600 flex items-center justify-center gap-1">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21h-8.5A2.25 2.25 0 0 1 5 18.75V10.25A2.25 2.25 0 0 1 7.25 8h4.75" />
              </svg>
              Edit
            </button>
            <form method="POST" class="flex-1">
              <input type="hidden" name="form" value="supplier_delete">
              <input type="hidden" name="id" value="<?php echo (int)$sup['id']; ?>">
              <button class="w-full rounded-lg border border-rose-200 text-rose-600 text-sm font-semibold py-2 bg-white hover:bg-rose-50 flex items-center justify-center gap-1" onclick="return confirm('Hapus supplier ini?')">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 6h12M9 6V4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2m-7 5v6m4-6v6M4 6h16l-1 14H5L4 6Z" />
                </svg>
                Delete
              </button>
            </form>
          </div>
        </div>
      </article>
    <?php endforeach; ?>
  </div>

  <?php if (count($suppliers) === 0): ?>
    <div class="text-center py-12 text-slate-500">
      <p>Tidak ada supplier ditemukan.</p>
    </div>
  <?php endif; ?>

  <!-- Pagination -->
  <?php if ($totalPages > 1): ?>
    <div class="flex items-center justify-between mt-4">
      <div class="text-sm text-slate-600">
        Page <span class="font-semibold text-orange-500"><?php echo $page; ?></span> of <span class="font-semibold text-slate-700"><?php echo $totalPages; ?></span>
      </div>
      <div class="flex items-center gap-2">
        <a href="?page=suppliers&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($statusFilter); ?>&p=<?php echo max(1, $page - 1); ?>" class="px-3 py-2 rounded-lg bg-slate-100 text-slate-600 hover:bg-slate-200 <?php echo $page <= 1 ? 'opacity-50 cursor-not-allowed pointer-events-none' : ''; ?>">
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
          <a href="?page=suppliers&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($statusFilter); ?>&p=<?php echo $i; ?>" class="px-4 py-2 rounded-lg <?php echo $i === $page ? 'bg-orange-500 text-white shadow' : 'bg-white border border-slate-200 hover:bg-slate-50'; ?> font-medium">
            <?php echo $i; ?>
          </a>
        <?php endfor; ?>
        <a href="?page=suppliers&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($statusFilter); ?>&p=<?php echo min($totalPages, $page + 1); ?>" class="px-3 py-2 rounded-lg bg-orange-500 text-white hover:bg-orange-600 <?php echo $page >= $totalPages ? 'opacity-50 cursor-not-allowed pointer-events-none' : ''; ?>">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
          </svg>
        </a>
      </div>
    </div>
  <?php endif; ?>
</section>

<!-- Supplier Modal (Add/Edit) -->
<div id="supplierModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm hidden items-center justify-center z-40">
  <div class="bg-white w-full max-w-2xl rounded-2xl shadow-2xl border border-slate-200 overflow-hidden max-h-[90vh] overflow-y-auto">
    <div class="px-6 py-4 bg-gradient-to-r from-orange-500 via-rose-500 to-pink-500 text-white flex items-center justify-between sticky top-0">
      <div>
        <h3 id="supplierModalTitle" class="text-lg font-semibold">Add New Supplier</h3>
        <p class="text-sm text-white/80">Fill in the supplier details below</p>
      </div>
      <button id="btnCloseSupplier" class="text-white hover:text-slate-100 text-2xl leading-none">&times;</button>
    </div>
    <form id="supplierForm" method="POST" class="p-6 space-y-4">
      <input type="hidden" name="form" id="supplierFormType" value="supplier_create">
      <input type="hidden" name="id" id="supplierId" value="">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="space-y-1">
          <label class="text-sm font-medium text-slate-700">Supplier Name *</label>
          <input name="name" id="inputSupplierName" required class="w-full rounded-lg border border-slate-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500" placeholder="PT Sumber Rejeki" autofocus>
        </div>
        <div class="space-y-1">
          <label class="text-sm font-medium text-slate-700">Contact Person</label>
          <input name="contact_name" id="inputContactName" class="w-full rounded-lg border border-slate-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500" placeholder="John Doe">
        </div>
        <div class="space-y-1">
          <label class="text-sm font-medium text-slate-700">Email</label>
          <input type="email" name="email" id="inputEmail" class="w-full rounded-lg border border-slate-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500" placeholder="supplier@mail.com">
        </div>
        <div class="space-y-1">
          <label class="text-sm font-medium text-slate-700">Phone</label>
          <input name="phone" id="inputPhone" class="w-full rounded-lg border border-slate-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500" placeholder="+62 812 3456 7890">
        </div>
        <div class="space-y-1 md:col-span-2">
          <label class="text-sm font-medium text-slate-700">Address</label>
          <input name="address" id="inputAddress" class="w-full rounded-lg border border-slate-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500" placeholder="Full address...">
        </div>
        <div class="space-y-1">
          <label class="text-sm font-medium text-slate-700">City</label>
          <input name="city" id="inputCity" class="w-full rounded-lg border border-slate-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-orange-500" placeholder="Jakarta">
        </div>
      </div>
      <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-200">
        <button type="button" id="btnCancelSupplier" class="px-5 py-2 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 font-medium">Cancel</button>
        <button type="submit" id="supplierSubmitBtn" class="px-6 py-2 rounded-lg bg-gradient-to-r from-orange-500 via-rose-500 to-pink-500 text-white text-sm font-semibold shadow hover:opacity-90">Add Supplier</button>
      </div>
    </form>
  </div>
</div>

<script>
  const supModal = document.getElementById('supplierModal');
  const supOpen = document.getElementById('btnOpenSupplier');
  const supClose = document.getElementById('btnCloseSupplier');
  const supCancel = document.getElementById('btnCancelSupplier');
  const supForm = document.getElementById('supplierForm');
  const supFormType = document.getElementById('supplierFormType');
  const supId = document.getElementById('supplierId');
  const supModalTitle = document.getElementById('supplierModalTitle');
  const supSubmitBtn = document.getElementById('supplierSubmitBtn');
  const statusFilter = document.getElementById('statusFilter');

  const toggleSup = (show) => {
    if (!supModal) return;
    supModal.classList.toggle('hidden', !show);
    supModal.classList.toggle('flex', show);
    if (!show) {
      supForm.reset();
      supFormType.value = 'supplier_create';
      supId.value = '';
      supModalTitle.textContent = 'Add New Supplier';
      supSubmitBtn.textContent = 'Add Supplier';
    }
  };

  function openEditSupplier(supplier) {
    supFormType.value = 'supplier_update';
    supId.value = supplier.id;
    document.getElementById('inputSupplierName').value = supplier.name || '';
    document.getElementById('inputContactName').value = supplier.contact_name || '';
    document.getElementById('inputEmail').value = supplier.email || '';
    document.getElementById('inputPhone').value = supplier.phone || '';
    document.getElementById('inputAddress').value = supplier.address || '';
    document.getElementById('inputCity').value = supplier.city || '';
    supModalTitle.textContent = 'Edit Supplier';
    supSubmitBtn.textContent = 'Update Supplier';
    toggleSup(true);
  }

  if (supOpen) supOpen.addEventListener('click', () => toggleSup(true));
  if (supClose) supClose.addEventListener('click', () => toggleSup(false));
  if (supCancel) supCancel.addEventListener('click', () => toggleSup(false));
  supModal?.addEventListener('click', (e) => {
    if (e.target === supModal) toggleSup(false);
  });

  // Filter change
  if (statusFilter) {
    statusFilter.addEventListener('change', function() {
      const url = new URL(window.location);
      url.searchParams.set('status', this.value);
      url.searchParams.set('p', '1');
      window.location.href = url.toString();
    });
  }
</script>
