-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 10, 2025 at 07:37 PM
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
(1, 'Laptop', 'Laptop dan notebook', '2025-12-10 15:41:16'),
(2, 'Accessory', 'Peripherals & aksesoris', '2025-12-10 15:41:16'),
(3, 'Monitor', 'Layar eksternal', '2025-12-10 15:41:16');

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
(1, 1, 1, 'SKU-LEN-L5P', 'Lenovo Legion 5 Pro', 'Lenovo', 'Gen 12', 'Intel Core i7-13650H', '16GB', '512GB SSD', 'Gaming laptop', 25000000.00, 6, 'active', '2025-12-10 15:41:16'),
(2, 1, 2, 'SKU-MSI-DR16', 'MSI Dragon 16', 'MSI', 'Dragon 16', 'Intel Core i7-13700H', '16GB', '1TB SSD', 'Creator laptop', 24000000.00, 6, 'active', '2025-12-10 15:41:16'),
(3, 1, 3, 'SKU-GIGA-A16', 'Gigabyte AERO 16', 'Gigabyte', 'OLED Creator', 'Intel Core i9-13900H', '32GB', '1TB SSD', 'OLED Creator laptop', 40000000.00, 0, 'active', '2025-12-10 15:41:16');

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

--
-- Dumping data for table `purchase_orders`
--

INSERT INTO `purchase_orders` (`id`, `supplier_id`, `code`, `order_date`, `expected_date`, `status`, `notes`, `created_at`) VALUES
(1, 1, 'PO-2045', '2025-01-05', '2025-01-12', 'received', 'Restock Lenovo & LG', '2025-12-10 15:41:16'),
(2, 2, 'PO-2046', '2025-01-07', '2025-01-15', 'ordered', 'SSD & accessories', '2025-12-10 15:41:16');

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

--
-- Dumping data for table `purchase_order_items`
--

INSERT INTO `purchase_order_items` (`id`, `purchase_order_id`, `product_id`, `qty`, `cost`) VALUES
(1, 1, 1, 10, 22000000.00),
(2, 1, 2, 10, 21000000.00),
(3, 2, 3, 5, 23000000.00);

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
(1, 'SO-5001', 'PT Mandiri Jaya', '2025-01-09', 'shipped', '2025-12-10 15:41:16'),
(2, 'SO-5002', 'CV Sinar Abadi', '2025-01-10', 'shipped', '2025-12-10 15:41:16'),
(3, 'SO-20251210-75ED44', 'PT Mandiri Jaya', '2025-12-10', 'shipped', '2025-12-10 18:14:15'),
(5, 'SO-20251210-E1AC7F', 'PT Mandiri Jaya', '2025-12-10', 'shipped', '2025-12-10 18:19:58');

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
(1, 1, 1, 2, 25000000.00),
(2, 1, 3, 1, 40000000.00),
(3, 2, 2, 1, 24000000.00),
(4, 3, 1, 2, 25000000.00),
(5, 5, 3, 3, 40000000.00),
(6, 5, 2, 6, 24000000.00);

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
(1, 1, 'in', 'PO-2045', 10, 'Receive Lenovo Legion 5 Pro', '2025-12-10 15:41:16'),
(2, 2, 'in', 'PO-2045', 10, 'Receive MSI Dragon 16', '2025-12-10 15:41:16'),
(3, 3, 'in', 'PO-2046', 5, 'Receive Gigabyte AERO 16', '2025-12-10 15:41:16'),
(4, 1, 'out', 'SO-5001', 2, 'Sold to PT Mandiri Jaya', '2025-12-10 15:41:16'),
(5, 3, 'out', 'SO-5001', 1, 'Sold to PT Mandiri Jaya', '2025-12-10 15:41:16'),
(6, 2, 'out', 'SO-5002', 1, 'Sold to CV Sinar Abadi', '2025-12-10 15:41:16'),
(7, 1, 'out', 'SO-3', 2, 'Sales order 3', '2025-12-10 18:15:36'),
(8, 3, 'out', 'SO-5', 3, 'Sales order 5', '2025-12-10 18:22:28'),
(9, 2, 'out', 'SO-5', 6, 'Sales order 5', '2025-12-10 18:22:28');

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
(1, 'PT Teknologi Maju', 'Budi Santoso', 'budi@teknologimaju.com', '+62 21 1234 5678', 'Jl. Sudirman No. 123', 'Jakarta', 'Indonesia', 'active', '2025-12-10 15:41:16'),
(2, 'CV Digital Sejahtera', 'Siti Nurhaliza', 'siti@digitalsejahtera.com', '+62 21 8765 4321', 'Jl. Gatot Subroto No. 456', 'Jakarta', 'Indonesia', 'active', '2025-12-10 15:41:16'),
(3, 'PT Komputer Nusantara', 'Ahmad Wijaya', 'ahmad@komputernusantara.com', '+62 21 5555 6666', 'Jl. HR Rasuna Said No. 780', 'Jakarta', 'Indonesia', 'active', '2025-12-10 15:41:16');

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
(1, 'Administrator', 'admin@example.com', '$2y$10$R1ngYG9lH45J6w5nUoQODuyfTRxhXFUujACWF/k1LYInjtZFl/Jza', 'admin', '2025-12-10 15:41:16');

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
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

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
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `sales_order_items`
--
ALTER TABLE `sales_order_items`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `stock_moves`
--
ALTER TABLE `stock_moves`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

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
