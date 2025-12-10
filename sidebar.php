<?php 
$page = $page ?? 'dashboard';
// Get low stock count for inventory badge
require_once __DIR__ . '/config/db.php';
$pdo = getPdo();
$lowStockCount = (int)$pdo->query('SELECT COUNT(*) FROM products WHERE stock <= 3')->fetchColumn();
?>
<div class="flex items-center justify-between px-6 py-6">
    <div class="flex items-center gap-3">
        <div class="h-12 w-12 rounded-2xl bg-gradient-to-br from-fuchsia-500 via-violet-500 to-purple-600 shadow-md flex items-center justify-center text-white text-xl font-bold">IP</div>
        <div>
            <div class="text-lg font-semibold text-slate-900">InventoryPro</div>
            <div class="text-xs text-slate-500">Modern System</div>
        </div>
    </div>
    <button class="h-10 w-10 rounded-xl hover:bg-slate-100 flex items-center justify-center text-slate-600">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 6h16M4 12h16M4 18h16" />
        </svg>
    </button>
</div>

<nav class="flex flex-col gap-3 px-4">
    <?php
    $items = [
        ['id' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'M3 7h7V3H3v4Zm0 14h7v-8H3v8Zm11 0h7v-5h-7v5Zm0-14v5h7V7h-7Z', 'gradient' => 'from-sky-500 to-cyan-400'],
        ['id' => 'inventory', 'label' => 'Inventory', 'icon' => 'M4 9.5 12 5l8 4.5-8 4.5-8-4.5Zm0 5L12 19l8-4.5', 'gradient' => 'from-fuchsia-500 to-amber-400', 'badge' => $lowStockCount > 0 ? (string)$lowStockCount : null],
        ['id' => 'suppliers', 'label' => 'Suppliers', 'icon' => 'M12 12c2.21 0 4-1.57 4-3.5S14.21 5 12 5 8 6.57 8 8.5 9.79 12 12 12Z M4 19.5c0-2.21 3.58-4 8-4s8 1.79 8 4', 'gradient' => 'from-orange-500 to-amber-500'],
        ['id' => 'sales', 'label' => 'Sales', 'icon' => 'M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z', 'gradient' => 'from-emerald-500 to-teal-500'],
        ['id' => 'reports', 'label' => 'Reports', 'icon' => 'M7 7h10M7 12h10M7 17h6', 'gradient' => 'from-indigo-500 to-purple-500'],
    ];

    foreach ($items as $item) {
        $active = $page === $item['id'];
        $classes = "sidebar-link group flex items-center gap-3 rounded-2xl px-5 py-3.5 relative overflow-hidden";
        $textActive = $active ? "text-white" : "text-slate-700";
        $iconActive = $active ? "text-white" : "text-slate-600";
        $shadow = $active ? "shadow-lg" : "";
        $href = $item['id'] === 'dashboard' ? 'index.php' : 'index.php?page=' . $item['id'];
        ?>
        <a href="<?php echo $href; ?>" class="<?php echo $classes . ' ' . $shadow; ?>">
            <div class="sidebar-active-bg absolute inset-0 <?php echo $active ? 'opacity-100' : 'opacity-0'; ?> transition-opacity bg-gradient-to-r <?php echo $item['gradient']; ?>"></div>
            <div class="relative flex items-center gap-3 w-full <?php echo $textActive; ?> transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 <?php echo $iconActive; ?> transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="<?php echo $item['icon']; ?>" />
                </svg>
                <span class="font-medium"><?php echo $item['label']; ?></span>
                <?php if (!empty($item['badge'])) : ?>
                    <span class="ml-auto flex h-6 w-6 items-center justify-center rounded-full bg-rose-500 text-white text-xs"><?php echo $item['badge']; ?></span>
                <?php endif; ?>
            </div>
        </a>
    <?php } ?>
</nav>

<div class="mt-auto px-4 pb-6 pt-10">
    <div class="flex items-center gap-3 rounded-2xl bg-slate-100 px-4 py-4">

        <div class="h-10 w-10 rounded-full bg-gradient-to-br from-sky-500 to-indigo-500 
                    text-white font-semibold flex items-center justify-center">
            <?php echo strtoupper(substr($_SESSION['user_name'], 0, 1)); ?>
        </div>

        <div>
            <div class="text-sm font-semibold text-slate-800">
                <?php echo htmlspecialchars($_SESSION['user_name']); ?>
            </div>
            <div class="text-xs text-slate-500">
                <?php echo htmlspecialchars($_SESSION['user_email']); ?>
            </div>
        </div>

    </div>
</div>


