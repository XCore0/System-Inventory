<?php
require_once __DIR__ . '/config/db.php';
$pdo = getPdo();

// Get total inventory value
$totalValue = (float)$pdo->query('SELECT COALESCE(SUM(price * stock), 0) FROM products')->fetchColumn();

// Get notifications count
$lowStockCount = (int)$pdo->query('SELECT COUNT(*) FROM products WHERE stock <= 3')->fetchColumn();
$pendingOrders = (int)$pdo->query('SELECT COUNT(*) FROM sales_orders WHERE status = "pending"')->fetchColumn();
$totalNotifications = $lowStockCount + $pendingOrders;

// Get notifications list
$notifications = [];
if ($lowStockCount > 0) {
    $notifications[] = [
        'type' => 'low_stock',
        'message' => $lowStockCount . ' product' . ($lowStockCount > 1 ? 's' : '') . ' with low stock',
        'count' => $lowStockCount,
        'color' => 'rose'
    ];
}
if ($pendingOrders > 0) {
    $notifications[] = [
        'type' => 'pending_order',
        'message' => $pendingOrders . ' pending sales order' . ($pendingOrders > 1 ? 's' : ''),
        'count' => $pendingOrders,
        'color' => 'yellow'
    ];
}
?>

<header class="sticky top-0 z-30 -mx-6 lg:-mx-8 px-6 lg:px-8 py-4 bg-gradient-to-r from-white/80 via-white/70 to-indigo-50/70 backdrop-blur border-b border-slate-100 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
  <div class="flex-1 max-w-2xl">
    <form method="GET" action="index.php" id="headerSearchForm" class="flex items-center gap-2 rounded-[18px] border border-slate-200 bg-white px-4 py-2.5 shadow-sm">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="m15.5 15.5 3 3M11 17a6 6 0 1 1 0-12 6 6 0 0 1 0 12Z" />
      </svg>
      <input type="text" name="search" id="headerSearchInput" value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>" placeholder="Search products, suppliers, or reports..." class="w-full bg-transparent text-sm text-slate-600 placeholder:text-slate-400 focus:outline-none" />
      <input type="hidden" name="page" id="headerSearchPage" value="<?php echo htmlspecialchars($page ?? 'dashboard'); ?>" />
    </form>
  </div>
  <div class="flex items-center gap-3">
    <div class="px-4 py-3 rounded-xl bg-emerald-500 text-white shadow-md min-w-[140px] text-right">
      <p class="text-sm leading-tight font-medium">Total Value</p>
      <p class="text-lg font-semibold leading-tight">
        <?php
          if ($totalValue >= 1000000000) {
            echo 'Rp ' . number_format($totalValue / 1000000000, 2, ',', '.') . 'B';
          } elseif ($totalValue >= 1000000) {
            echo 'Rp ' . number_format($totalValue / 1000000, 1, ',', '.') . 'M';
          } else {
            echo 'Rp ' . number_format($totalValue, 0, ',', '.');
          }
        ?>
      </p>
    </div>
    <div class="relative">
      <button id="notificationBtn" class="h-10 w-10 rounded-full bg-slate-100 flex items-center justify-center text-slate-500 relative hover:bg-slate-200 transition">
        <?php if ($totalNotifications > 0): ?>
          <span class="absolute -top-1 -right-1 h-5 w-5 rounded-full bg-rose-500 text-white text-xs flex items-center justify-center font-semibold"><?php echo $totalNotifications > 9 ? '9+' : $totalNotifications; ?></span>
        <?php endif; ?>
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.4-2.8A2 2 0 0 1 18 12V9a6 6 0 1 0-12 0v3a2 2 0 0 1-.6 1.2L4 17h5m6 0v1a3 3 0 1 1-6 0v-1m6 0H9" />
        </svg>
      </button>
      
      <!-- Notification Dropdown -->
      <div id="notificationDropdown" class="hidden absolute right-0 mt-2 w-80 bg-white rounded-xl shadow-lg border border-slate-200 z-50 max-h-96 overflow-y-auto">
        <div class="p-4 border-b border-slate-200">
          <h3 class="text-sm font-semibold text-slate-900">Notifications</h3>
        </div>
        <div class="divide-y divide-slate-100">
          <?php if (count($notifications) > 0): ?>
            <?php foreach ($notifications as $notif): ?>
              <div class="p-4 hover:bg-slate-50 transition">
                <div class="flex items-start gap-3">
                  <div class="h-8 w-8 rounded-lg bg-<?php echo $notif['color']; ?>-100 text-<?php echo $notif['color']; ?>-600 flex items-center justify-center flex-shrink-0">
                    <?php if ($notif['type'] === 'low_stock'): ?>
                      <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v3.5m0 3h.01M10.29 3.86 1.82 18a1 1 0 0 0 .86 1.5h18.64a1 1 0 0 0 .86-1.5L13.71 3.86a1 1 0 0 0-1.72 0Z" />
                      </svg>
                    <?php else: ?>
                      <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z" />
                      </svg>
                    <?php endif; ?>
                  </div>
                  <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-slate-900"><?php echo htmlspecialchars($notif['message']); ?></p>
                    <p class="text-xs text-slate-500 mt-1">
                      <?php if ($notif['type'] === 'low_stock'): ?>
                        <a href="?page=inventory" class="text-indigo-600 hover:text-indigo-700">View inventory →</a>
                      <?php else: ?>
                        <a href="?page=sales" class="text-indigo-600 hover:text-indigo-700">View sales →</a>
                      <?php endif; ?>
                    </p>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="p-8 text-center text-slate-500 text-sm">
              <p>No notifications</p>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</header>

<script>
  const notificationBtn = document.getElementById('notificationBtn');
  const notificationDropdown = document.getElementById('notificationDropdown');
  
  if (notificationBtn && notificationDropdown) {
    notificationBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      notificationDropdown.classList.toggle('hidden');
    });
    
    document.addEventListener('click', (e) => {
      if (!notificationBtn.contains(e.target) && !notificationDropdown.contains(e.target)) {
        notificationDropdown.classList.add('hidden');
      }
    });
  }

  // Smart search functionality
  const headerSearchForm = document.getElementById('headerSearchForm');
  const headerSearchInput = document.getElementById('headerSearchInput');
  const headerSearchPage = document.getElementById('headerSearchPage');
  
  if (headerSearchForm && headerSearchInput && headerSearchPage) {
    const currentPage = headerSearchPage.value;
    
    // If on dashboard or reports, redirect to inventory when searching
    if (currentPage === 'dashboard' || currentPage === 'reports') {
      headerSearchForm.addEventListener('submit', function(e) {
        const searchValue = headerSearchInput.value.trim();
        if (searchValue !== '') {
          e.preventDefault();
          window.location.href = 'index.php?page=inventory&search=' + encodeURIComponent(searchValue);
        }
      });
    }
    
    // Auto-submit on Enter key
    headerSearchInput.addEventListener('keypress', function(e) {
      if (e.key === 'Enter') {
        const searchValue = this.value.trim();
        if (searchValue !== '' && (currentPage === 'dashboard' || currentPage === 'reports')) {
          e.preventDefault();
          window.location.href = 'index.php?page=inventory&search=' + encodeURIComponent(searchValue);
        }
      }
    });
  }
</script>
