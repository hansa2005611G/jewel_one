-- JEWEL ONE POS BACKUP
-- Date: 2026-05-22 06:39:56
-- DB: jewel_one_pos

SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS `bill_items`;
CREATE TABLE `bill_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bill_id` int(11) NOT NULL,
  `product_name` varchar(300) NOT NULL,
  `quantity` decimal(10,3) NOT NULL DEFAULT 1.000,
  `unit_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `discount_type` enum('none','percentage','fixed') NOT NULL DEFAULT 'none',
  `discount_value` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `original_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `final_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_bill_items_bill` (`bill_id`),
  KEY `idx_bill_items_product` (`product_name`),
  CONSTRAINT `bill_items_ibfk_1` FOREIGN KEY (`bill_id`) REFERENCES `bills` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `bill_items` (`id`, `bill_id`, `product_name`, `quantity`, `unit_price`, `discount_type`, `discount_value`, `discount_amount`, `original_price`, `final_total`, `created_at`) VALUES
('1', '1', 'gold ring', '1.000', '2500.00', 'fixed', '200.00', '200.00', '2500.00', '2300.00', '2026-05-21 12:17:41'),
('2', '1', 'nose ring', '1.000', '500.00', 'none', '0.00', '0.00', '500.00', '500.00', '2026-05-21 12:17:41'),
('3', '2', 'ring', '2.000', '500.00', 'fixed', '200.00', '200.00', '1000.00', '800.00', '2026-05-21 12:19:55'),
('4', '3', 'ring', '1.000', '10000.00', 'percentage', '10.00', '1000.00', '10000.00', '9000.00', '2026-05-21 12:26:45'),
('5', '4', 'chanin', '1.000', '200.00', 'none', '0.00', '0.00', '200.00', '200.00', '2026-05-22 10:05:33');

DROP TABLE IF EXISTS `bills`;
CREATE TABLE `bills` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bill_number` varchar(50) NOT NULL,
  `cashier_id` int(11) NOT NULL,
  `customer_name` varchar(200) DEFAULT NULL,
  `customer_phone` varchar(20) DEFAULT NULL,
  `subtotal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_discount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `tax_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `grand_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `paid_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `balance_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `payment_method` enum('cash','card','bank_transfer') NOT NULL DEFAULT 'cash',
  `status` enum('completed','draft','cancelled') NOT NULL DEFAULT 'completed',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `bill_number` (`bill_number`),
  KEY `idx_bills_cashier` (`cashier_id`),
  KEY `idx_bills_created` (`created_at`),
  KEY `idx_bills_status` (`status`),
  CONSTRAINT `bills_ibfk_1` FOREIGN KEY (`cashier_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `bills` (`id`, `bill_number`, `cashier_id`, `customer_name`, `customer_phone`, `subtotal`, `total_discount`, `tax_amount`, `grand_total`, `paid_amount`, `balance_amount`, `payment_method`, `status`, `notes`, `created_at`, `updated_at`) VALUES
('1', 'JO2605213769', '4', 'hansa', NULL, '3000.00', '200.00', '0.00', '2800.00', '3000.00', '200.00', 'cash', 'draft', NULL, '2026-05-21 12:17:41', '2026-05-21 12:17:41'),
('2', 'JO2605214761', '4', NULL, NULL, '1000.00', '200.00', '0.00', '800.00', '1000.00', '200.00', 'cash', 'completed', NULL, '2026-05-21 12:19:55', '2026-05-21 12:19:55'),
('3', 'JO2605212B05', '4', 'hansa', NULL, '10000.00', '1000.00', '0.00', '9000.00', '9000.00', '0.00', 'card', 'completed', NULL, '2026-05-21 12:26:45', '2026-05-21 12:26:45'),
('4', 'JO260522DF79', '4', NULL, NULL, '200.00', '0.00', '0.00', '200.00', '0.00', '-200.00', 'cash', 'completed', NULL, '2026-05-22 10:05:33', '2026-05-22 10:05:33');

DROP TABLE IF EXISTS `report_logs`;
CREATE TABLE `report_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `action` varchar(200) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `report_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `report_logs` (`id`, `user_id`, `action`, `details`, `ip_address`, `created_at`) VALUES
('1', '4', 'LOGIN', 'User logged in', '::1', '2026-05-21 11:19:15'),
('2', '4', 'LOGIN', 'User logged in', '::1', '2026-05-21 11:53:35'),
('3', '4', 'CREATE_BILL', 'Bill #JO2605213769 created, Total: 2800', '::1', '2026-05-21 12:17:41'),
('4', '4', 'CREATE_BILL', 'Bill #JO2605214761 created, Total: 800', '::1', '2026-05-21 12:19:56'),
('5', '4', 'CREATE_BILL', 'Bill #JO2605212B05 created, Total: 9000', '::1', '2026-05-21 12:26:45'),
('6', '4', 'LOGIN', 'User logged in', '::1', '2026-05-22 09:40:46'),
('7', '4', 'LOGOUT', 'User logged out', '::1', '2026-05-22 09:41:27'),
('8', '5', 'LOGIN', 'User logged in', '::1', '2026-05-22 09:41:38'),
('9', '5', 'LOGOUT', 'User logged out', '::1', '2026-05-22 10:04:49'),
('10', '4', 'LOGIN', 'User logged in', '::1', '2026-05-22 10:04:58'),
('11', '4', 'CREATE_BILL', 'Bill #JO260522DF79 created, Total: 200', '::1', '2026-05-22 10:05:34'),
('12', '4', 'DB_BACKUP', 'Backup created: jewel_one_backup_2026-05-22_06-39-40.sql', '::1', '2026-05-22 10:09:40');

DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `updated_at`) VALUES
('1', 'shop_name', 'JEWEL ONE 1', '2026-05-21 08:00:01'),
('2', 'shop_address', 'Matale, Sri Lanka', '2026-05-21 08:00:01'),
('3', 'shop_phone', '+94 66 222 3333', '2026-05-21 08:00:01'),
('4', 'shop_email', 'info@jewelone.lk', '2026-05-21 08:00:01'),
('5', 'tax_enabled', '0', '2026-05-21 08:00:01'),
('6', 'tax_percentage', '0', '2026-05-21 08:00:01'),
('7', 'currency_symbol', 'Rs.', '2026-05-21 08:00:01'),
('8', 'receipt_footer', 'Thank You Come Again', '2026-05-21 08:00:01'),
('9', 'thermal_size', '80mm', '2026-05-21 08:00:01'),
('10', 'theme_mode', 'dark', '2026-05-22 10:05:07'),
('11', 'logo_path', '', '2026-05-21 08:00:01'),
('12', 'receipt_copies', '1', '2026-05-21 08:00:01');

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(200) NOT NULL,
  `email` varchar(200) DEFAULT NULL,
  `role` enum('admin','cashier') NOT NULL DEFAULT 'cashier',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `avatar` varchar(255) DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `users` (`id`, `username`, `password`, `full_name`, `email`, `role`, `status`, `avatar`, `last_login`, `created_at`, `updated_at`) VALUES
('4', 'admin', '$2y$10$iNmSKFvId.X32MVnz4VDVOd7Vb1HArlCtgm7NEilcNnbR4kRlYmE6', 'admin hansa', 'hansa@gmail.com', 'admin', 'active', NULL, '2026-05-22 10:04:58', '2026-05-21 11:13:37', '2026-05-22 10:04:58'),
('5', 'hishma', '$2y$12$9ris5e1wEclY6yYU3ZFx3.heKXjuSyohLm.LcH2MEggRImdLNsJyq', 'hishma', '', 'cashier', 'active', NULL, '2026-05-22 09:41:38', '2026-05-22 09:41:16', '2026-05-22 09:41:38');

SET FOREIGN_KEY_CHECKS=1;
