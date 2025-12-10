<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

ob_start(); // Start output buffering
$page = $_GET['page'] ?? 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>InventoryPro Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            background: radial-gradient(circle at 15% 20%, #eef2ff 0, transparent 25%),
                        radial-gradient(circle at 85% 10%, #e0f2fe 0, transparent 22%),
                        radial-gradient(circle at 80% 80%, #ffe4f3 0, transparent 20%),
                        #f1f5f9;
            font-family: "Inter", system-ui, -apple-system, sans-serif;
        }
    </style>
</head>
<body class="min-h-screen w-screen h-screen flex justify-center items-stretch p-0">
    <div class="w-full h-full bg-white/90 backdrop-blur rounded-none shadow-2xl border border-slate-100 overflow-hidden">
        <div class="flex h-full">
            <aside class="w-[300px] bg-gradient-to-b from-white/90 to-slate-50/80 border-r border-slate-100 flex flex-col">
                <?php include 'sidebar.php'; ?>
            </aside>
            <main class="flex-1 lg:pb-8 pr-8 pl-8 bg-gradient-to-br from-slate-50 via-white to-indigo-50 overflow-auto pt-0">
                <?php include 'header.php'; ?>
                <?php
                    switch ($page) {
                        case 'inventory':
                            include 'partials/content-inventory.php';
                            break;
                        case 'suppliers':
                            include 'partials/content-suppliers.php';
                            break;
                        case 'sales':
                            include 'partials/content-sales.php';
                            break;
                        case 'reports':
                            include 'partials/content-reports.php';
                            break;
                        default:
                            include 'partials/content-dashboard.php';
                            break;
                    }
                ?>
            </main>
        </div>
    </div>
</body>
</html>

