-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 12, 2025 at 01:28 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `inventorypro`
--

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `description`, `created_at`) VALUES
(1, 'Laptop', 'Berbagai jenis laptop', '2025-12-12 00:15:12'),
(2, 'Desktop', 'PC Desktop & Mini PC', '2025-12-12 00:15:12'),
(3, 'Aksesoris', 'Mouse, keyboard, headset, dll', '2025-12-12 00:15:12'),
(4, 'Monitor', 'Monitor komputer', '2025-12-12 00:15:12'),
(5, 'Printer', 'Printer & Scanner', '2025-12-12 00:15:12'),
(6, 'Networking', 'Router, Switch, Access Point', '2025-12-12 00:15:12'),
(7, 'Storage', 'SSD, HDD, Flashdisk', '2025-12-12 00:15:12'),
(8, 'Komponen', 'CPU, GPU, RAM, Motherboard', '2025-12-12 00:15:12'),
(9, 'Server', 'Server rack & tower', '2025-12-12 00:15:12'),
(10, 'Lainnya', 'Produk teknologi lainnya', '2025-12-12 00:15:12');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(10) UNSIGNED NOT NULL,
  `category_id` int(10) UNSIGNED DEFAULT NULL,
  `supplier_id` int(10) UNSIGNED DEFAULT NULL,
  `sku` varchar(50) NOT NULL,
  `name` varchar(150) NOT NULL,
  `brand` varchar(80) DEFAULT NULL,
  `model` varchar(150) DEFAULT NULL,
  `processor` varchar(150) DEFAULT NULL,
  `ram` varchar(50) DEFAULT NULL,
  `storage` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(12,2) DEFAULT 0.00,
  `stock` int(11) DEFAULT 0,
  `status` enum('active','archived') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `category_id`, `supplier_id`, `sku`, `name`, `brand`, `model`, `processor`, `ram`, `storage`, `description`, `price`, `stock`, `status`, `created_at`) VALUES
(1, 1, 1, 'LTP-001', 'Laptop Office 14\"', 'Lenovo', 'ThinkPad L14', 'Intel i5-1135G7', '8GB', '256GB SSD', 'Laptop kerja', 9500000.00, 12, 'active', '2025-12-12 00:17:46'),
(2, 1, 2, 'LTP-002', 'Laptop Gaming 15\"', 'ASUS', 'ROG Strix G15', 'Ryzen 7 5800H', '16GB', '512GB SSD', 'Laptop gaming', 18500000.00, 5, 'active', '2025-12-12 00:17:46'),
(3, 1, 1, 'LTP-003', 'Laptop Ultrabook 13\"', 'Dell', 'XPS 13', 'Intel i7-12500U', '16GB', '1TB SSD', 'Ultrabook premium', 21000000.00, 4, 'active', '2025-12-12 00:17:46'),
(4, 1, 3, 'LTP-004', 'Laptop Budget 14\"', 'Acer', 'Aspire 3', 'Intel i3-1115G4', '4GB', '256GB SSD', 'Laptop murah', 5500000.00, 20, 'active', '2025-12-12 00:17:46'),
(5, 1, 4, 'LTP-005', 'Laptop Designer 15\"', 'MSI', 'Creator 15', 'Intel i7-11800H', '16GB', '1TB SSD', 'Laptop kreator', 23500000.00, 6, 'active', '2025-12-12 00:17:46'),
(6, 1, 5, 'LTP-006', 'Laptop 2in1 Touchscreen', 'HP', 'Envy x360', 'Ryzen 5 5500U', '8GB', '512GB SSD', 'Laptop lipat', 12000000.00, 10, 'active', '2025-12-12 00:17:46'),
(7, 1, 6, 'LTP-007', 'Laptop Chromebook', 'Lenovo', 'Chromebook C340', 'Intel Celeron', '4GB', '64GB SSD', 'Kebutuhan basic', 3500000.00, 30, 'active', '2025-12-12 00:17:46'),
(8, 1, 7, 'LTP-008', 'Laptop Gaming 17\"', 'Gigabyte', 'AORUS 17', 'Intel i9-12900H', '32GB', '1TB SSD', 'Gaming high-end', 34000000.00, 3, 'active', '2025-12-12 00:17:46'),
(9, 1, 8, 'LTP-009', 'Laptop Bisnis 14\"', 'HP', 'ProBook 440 G8', 'Intel i5-1145G7', '8GB', '512GB SSD', 'Laptop bisnis', 10500000.00, 11, 'active', '2025-12-12 00:17:46'),
(10, 1, 9, 'LTP-010', 'Laptop Editing 16\"', 'Apple', 'MacBook Pro M1', 'Apple M1', '16GB', '512GB SSD', 'Video editing', 24000000.00, 7, 'active', '2025-12-12 00:17:46'),
(11, 2, 3, 'DST-001', 'PC Office', 'HP', 'ProDesk 400', 'Intel i5-10400', '8GB', '1TB HDD', 'PC kantor', 8700000.00, 15, 'active', '2025-12-12 00:22:09'),
(12, 2, 4, 'DST-002', 'PC Gaming RTX', 'ASUS', 'ROG Strix', 'Intel i7-11700', '16GB', '512GB SSD', 'PC gaming', 18500000.00, 6, 'active', '2025-12-12 00:22:09'),
(13, 2, 5, 'DST-003', 'Mini PC', 'Intel', 'NUC 11', 'Intel i5-1135G7', '8GB', '512GB SSD', 'Mini PC ringkas', 9000000.00, 10, 'active', '2025-12-12 00:22:09'),
(14, 2, 6, 'DST-004', 'PC Rendering', 'MSI', 'Creator Workstation', 'Ryzen 9 5900X', '32GB', '1TB SSD', 'Rendering 3D', 28000000.00, 4, 'active', '2025-12-12 00:22:09'),
(15, 2, 7, 'DST-005', 'PC UMKM', 'Acer', 'Veriton', 'Intel i3', '4GB', '500GB HDD', 'PC usaha kecil', 4900000.00, 20, 'active', '2025-12-12 00:22:09'),
(16, 2, 8, 'DST-006', 'PC Server Mini', 'Dell', 'PowerEdge T40', 'Xeon E-2224G', '16GB', '2TB HDD', 'Mini server', 13500000.00, 5, 'active', '2025-12-12 00:22:09'),
(17, 2, 9, 'DST-007', 'PC Gaming Entry', 'Lenovo', 'Legion Tower', 'Ryzen 5 3600', '16GB', '512GB SSD', 'Gaming menengah', 12500000.00, 7, 'active', '2025-12-12 00:22:09'),
(18, 2, 1, 'DST-008', 'PC Editing', 'Gigabyte', 'Aero Station', 'Intel i7', '32GB', '1TB SSD', 'Editing video', 21000000.00, 6, 'active', '2025-12-12 00:22:09'),
(19, 2, 2, 'DST-009', 'PC Kantor Slim', 'Fujitsu', 'Esprimo', 'Intel i5', '8GB', '1TB HDD', 'PC kantor hemat daya', 7500000.00, 10, 'active', '2025-12-12 00:22:09'),
(20, 4, 4, 'MON-001', 'Monitor 24\" IPS', 'LG', '24MP88', '-', '-', '-', 'Monitor IPS bezel tipis', 1900000.00, 18, 'active', '2025-12-12 00:24:18'),
(21, 4, 5, 'MON-002', 'Monitor 27\" 144Hz', 'AOC', '27G2', '-', '-', '-', 'Gaming 144Hz', 3300000.00, 7, 'active', '2025-12-12 00:24:18'),
(22, 4, 6, 'MON-003', 'Monitor 32\" 4K', 'Samsung', 'U32J59', '-', '-', '-', 'Monitor 4K profesional', 5200000.00, 8, 'active', '2025-12-12 00:24:18'),
(23, 4, 7, 'MON-004', 'Monitor Curved', 'MSI', 'Optix MAG241C', '-', '-', '-', '144Hz curved', 2900000.00, 11, 'active', '2025-12-12 00:24:18'),
(24, 4, 1, 'MON-005', 'Monitor Budget 22\"', 'Philips', '223V5L', '-', '-', '-', 'Monitor murah', 1200000.00, 25, 'active', '2025-12-12 00:24:18'),
(25, 7, 10, 'STO-001', 'SSD 500GB', 'Samsung', '970 EVO', NULL, NULL, NULL, 'SSD NVMe cepat', 1100000.00, 30, 'active', '2025-12-12 00:24:18'),
(26, 7, 10, 'STO-002', 'HDD 1TB', 'Seagate', 'Barracuda', NULL, NULL, NULL, 'HDD desktop', 650000.00, 40, 'active', '2025-12-12 00:24:18'),
(27, 7, 5, 'STO-003', 'SSD 1TB', 'Kingston', 'A2000', NULL, NULL, NULL, 'SSD NVMe murah', 1300000.00, 22, 'active', '2025-12-12 00:24:18'),
(28, 7, 4, 'STO-004', 'HDD 2TB', 'WD', 'Blue', NULL, NULL, NULL, 'Harddisk besar', 950000.00, 20, 'active', '2025-12-12 00:24:18'),
(29, 7, 3, 'STO-005', 'Flashdisk 64GB', 'Sandisk', 'Cruzer Blade', NULL, NULL, NULL, 'Flashdisk umum', 120000.00, 80, 'active', '2025-12-12 00:24:18'),
(30, 5, 8, 'PRT-001', 'Printer Inkjet', 'Canon', 'IP2770', NULL, NULL, NULL, 'Printer rumahan', 850000.00, 22, 'active', '2025-12-12 00:24:18'),
(31, 5, 9, 'PRT-002', 'Printer Laser Mono', 'Brother', 'HL-L2320D', NULL, NULL, NULL, 'Printer laser hemat', 1650000.00, 15, 'active', '2025-12-12 00:24:18'),
(32, 5, 3, 'PRT-003', 'Printer Wifi', 'Epson', 'L3150', NULL, NULL, NULL, 'Printer wifi tank', 2350000.00, 18, 'active', '2025-12-12 00:24:18'),
(33, 5, 2, 'PRT-004', 'Printer Kantor', 'HP', 'LaserJet Pro', NULL, NULL, NULL, 'Printer kantor cepat', 2750000.00, 10, 'active', '2025-12-12 00:24:18'),
(34, 5, 1, 'PRT-005', 'Scanner Dokumen', 'Fujitsu', 'ScanSnap', NULL, NULL, NULL, 'Scanner kecepatan tinggi', 4500000.00, 7, 'active', '2025-12-12 00:24:18'),
(35, 6, 8, 'NET-001', 'Router Dual Band', 'TP-Link', 'Archer C6', NULL, NULL, NULL, 'Router 1200Mbps', 450000.00, 40, 'active', '2025-12-12 00:24:18'),
(36, 6, 8, 'NET-002', 'Access Point WiFi 6', 'Ubiquiti', 'U6-Lite', NULL, NULL, NULL, 'AP profesional WiFi 6', 1750000.00, 25, 'active', '2025-12-12 00:24:18'),
(37, 6, 7, 'NET-003', 'Switch 24 Port', 'Cisco', 'SG112', NULL, NULL, NULL, 'Switch unmanaged', 3200000.00, 12, 'active', '2025-12-12 00:24:18'),
(38, 6, 6, 'NET-004', 'Router Gaming', 'ASUS', 'RT-AX82U', NULL, NULL, NULL, 'WiFi 6 RGB', 3100000.00, 8, 'active', '2025-12-12 00:24:18'),
(39, 6, 9, 'NET-005', 'Modem Fiber', 'Huawei', 'HG8245H5', NULL, NULL, NULL, 'Modem ISP', 600000.00, 31, 'active', '2025-12-12 00:24:18');

-- --------------------------------------------------------

--
-- Table structure for table `purchase_orders`
--

CREATE TABLE `purchase_orders` (
  `id` int(10) UNSIGNED NOT NULL,
  `supplier_id` int(10) UNSIGNED NOT NULL,
  `code` varchar(30) NOT NULL,
  `order_date` date NOT NULL,
  `expected_date` date DEFAULT NULL,
  `status` enum('draft','ordered','received','cancelled') DEFAULT 'ordered',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `purchase_order_items`
--

CREATE TABLE `purchase_order_items` (
  `id` int(10) UNSIGNED NOT NULL,
  `purchase_order_id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `qty` int(11) NOT NULL,
  `cost` decimal(12,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sales_orders`
--

CREATE TABLE `sales_orders` (
  `id` int(10) UNSIGNED NOT NULL,
  `code` varchar(30) NOT NULL,
  `customer_name` varchar(150) DEFAULT NULL,
  `order_date` date NOT NULL,
  `status` enum('pending','paid','shipped','cancelled') DEFAULT 'paid',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sales_orders`
--

INSERT INTO `sales_orders` (`id`, `code`, `customer_name`, `order_date`, `status`, `created_at`) VALUES
(10, 'SO-20251212-0129D6', 'Yuzki', '2025-12-12', 'paid', '2025-12-12 00:25:20');

-- --------------------------------------------------------

--
-- Table structure for table `sales_order_items`
--

CREATE TABLE `sales_order_items` (
  `id` int(10) UNSIGNED NOT NULL,
  `sales_order_id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `qty` int(11) NOT NULL,
  `price` decimal(12,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sales_order_items`
--

INSERT INTO `sales_order_items` (`id`, `sales_order_id`, `product_id`, `qty`, `price`) VALUES
(15, 10, 26, 10, 650000.00),
(16, 10, 39, 4, 600000.00),
(17, 10, 3, 4, 21000000.00),
(18, 10, 21, 5, 3300000.00);

-- --------------------------------------------------------

--
-- Table structure for table `stock_moves`
--

CREATE TABLE `stock_moves` (
  `id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `move_type` enum('in','out','adjust') NOT NULL,
  `reference` varchar(50) DEFAULT NULL,
  `qty` int(11) NOT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `stock_moves`
--

INSERT INTO `stock_moves` (`id`, `product_id`, `move_type`, `reference`, `qty`, `note`, `created_at`) VALUES
(18, 26, 'out', 'SO-10', 10, 'Sales order 10', '2025-12-12 00:26:20'),
(19, 39, 'out', 'SO-10', 4, 'Sales order 10', '2025-12-12 00:26:20'),
(20, 3, 'out', 'SO-10', 4, 'Sales order 10', '2025-12-12 00:26:20'),
(21, 21, 'out', 'SO-10', 5, 'Sales order 10', '2025-12-12 00:26:20');

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `contact_name` varchar(100) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(80) DEFAULT NULL,
  `country` varchar(80) DEFAULT 'Indonesia',
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`id`, `name`, `contact_name`, `email`, `phone`, `address`, `city`, `country`, `status`, `created_at`) VALUES
(1, 'Tech Supplier Indonesia', 'Budi Santoso', 'budi@tsi.co.id', '08123456701', 'Jl. Merdeka 21', 'Jakarta', 'Indonesia', 'active', '2025-12-12 00:17:23'),
(2, 'Global Computer Parts', 'Andi Wijaya', 'andi@gcp.com', '08123456702', 'Jl. Asia Afrika 5', 'Bandung', 'Indonesia', 'active', '2025-12-12 00:17:23'),
(3, 'Digital Hardware Corp', 'Sarah Lee', 'sarah@dhcorp.com', '08123456703', 'Jl. Gatsu 18', 'Surabaya', 'Indonesia', 'active', '2025-12-12 00:17:23'),
(4, 'MegaTech Distributor', 'Joko Hadi', 'joko@megatech.com', '08123456704', 'Jl. Kuningan 9', 'Jakarta', 'Indonesia', 'active', '2025-12-12 00:17:23'),
(5, 'IndoKom Hardware', 'Rama Putra', 'rama@indokom.com', '08123456705', 'Jl. Diponegoro 10', 'Semarang', 'Indonesia', 'active', '2025-12-12 00:17:23'),
(6, 'Asia PC Supply', 'Kevin Lim', 'kevin@apcs.com', '08123456706', 'Jl. Veteran 77', 'Medan', 'Indonesia', 'active', '2025-12-12 00:17:23'),
(7, 'ByteStation', 'Agus Setiawan', 'agus@bytestation.com', '08123456707', 'Jl. Imam Bonjol 12', 'Palembang', 'Indonesia', 'active', '2025-12-12 00:17:23'),
(8, 'NetworkPro', 'Rizki Dwi', 'rizki@netpro.com', '08123456708', 'Jl. Cipto 101', 'Malang', 'Indonesia', 'active', '2025-12-12 00:17:23'),
(9, 'ServerTech Asia', 'Dewi Anggraini', 'dewi@sta.co', '08123456709', 'Jl. Ahmad Yani 20', 'Makassar', 'Indonesia', 'active', '2025-12-12 00:17:23'),
(10, 'Garuda Components', 'Michael Tan', 'michael@gardcomp.com', '08123456710', 'Jl. Riau 33', 'Pekanbaru', 'Indonesia', 'active', '2025-12-12 00:17:23');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(120) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','staff') DEFAULT 'staff',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `created_at`) VALUES
(3, 'Admin Laptop', 'admin@laptop.com', '$2y$10$dT22j3XDoP3j/tP6vdX0qeecCnwPRtpoUL0wXGNVlHpuU8bWo405y', 'admin', '2025-12-10 19:10:27');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sku` (`sku`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `supplier_id` (`supplier_id`);

--
-- Indexes for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `supplier_id` (`supplier_id`);

--
-- Indexes for table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `purchase_order_id` (`purchase_order_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `sales_orders`
--
ALTER TABLE `sales_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `sales_order_items`
--
ALTER TABLE `sales_order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sales_order_id` (`sales_order_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `stock_moves`
--
ALTER TABLE `stock_moves`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `sales_orders`
--
ALTER TABLE `sales_orders`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `sales_order_items`
--
ALTER TABLE `sales_order_items`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `stock_moves`
--
ALTER TABLE `stock_moves`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `products_ibfk_2` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  ADD CONSTRAINT `purchase_orders_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  ADD CONSTRAINT `purchase_order_items_ibfk_1` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `purchase_order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sales_order_items`
--
ALTER TABLE `sales_order_items`
  ADD CONSTRAINT `sales_order_items_ibfk_1` FOREIGN KEY (`sales_order_id`) REFERENCES `sales_orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sales_order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `stock_moves`
--
ALTER TABLE `stock_moves`
  ADD CONSTRAINT `stock_moves_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
