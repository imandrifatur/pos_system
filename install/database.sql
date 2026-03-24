-- ============================================================
-- POS SYSTEM + ACCOUNTING DATABASE
-- ============================================================

CREATE DATABASE IF NOT EXISTS `pos_accounting` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `pos_accounting`;

-- ============================================================
-- MASTER DATA
-- ============================================================

CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100),
  `role` ENUM('admin','kasir','akuntan','manajer') DEFAULT 'kasir',
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE `categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `description` TEXT,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE `units` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(50) NOT NULL,
  `symbol` VARCHAR(10) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE `products` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `category_id` INT,
  `unit_id` INT,
  `code` VARCHAR(50) UNIQUE NOT NULL,
  `barcode` VARCHAR(100),
  `name` VARCHAR(200) NOT NULL,
  `description` TEXT,
  `buy_price` DECIMAL(15,2) DEFAULT 0,
  `sell_price` DECIMAL(15,2) DEFAULT 0,
  `stock` DECIMAL(10,2) DEFAULT 0,
  `min_stock` DECIMAL(10,2) DEFAULT 0,
  `image` VARCHAR(255),
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`unit_id`) REFERENCES `units`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE `customers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `code` VARCHAR(20) UNIQUE NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `phone` VARCHAR(20),
  `email` VARCHAR(100),
  `address` TEXT,
  `credit_limit` DECIMAL(15,2) DEFAULT 0,
  `balance` DECIMAL(15,2) DEFAULT 0,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE `suppliers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `code` VARCHAR(20) UNIQUE NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `phone` VARCHAR(20),
  `email` VARCHAR(100),
  `address` TEXT,
  `balance` DECIMAL(15,2) DEFAULT 0,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- ACCOUNTING CHART OF ACCOUNTS
-- ============================================================

CREATE TABLE `coa` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `code` VARCHAR(20) UNIQUE NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `type` ENUM('aset','kewajiban','modal','pendapatan','beban') NOT NULL,
  `normal_balance` ENUM('debit','kredit') NOT NULL,
  `parent_id` INT DEFAULT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`parent_id`) REFERENCES `coa`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- TRANSACTIONS: SALES (POS)
-- ============================================================

CREATE TABLE `sales` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `invoice_no` VARCHAR(30) UNIQUE NOT NULL,
  `customer_id` INT DEFAULT NULL,
  `user_id` INT NOT NULL,
  `sale_date` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `subtotal` DECIMAL(15,2) DEFAULT 0,
  `discount` DECIMAL(15,2) DEFAULT 0,
  `tax` DECIMAL(15,2) DEFAULT 0,
  `total` DECIMAL(15,2) DEFAULT 0,
  `paid` DECIMAL(15,2) DEFAULT 0,
  `change_due` DECIMAL(15,2) DEFAULT 0,
  `payment_method` ENUM('tunai','transfer','kartu','piutang') DEFAULT 'tunai',
  `status` ENUM('selesai','batal','piutang') DEFAULT 'selesai',
  `notes` TEXT,
  `journal_id` INT DEFAULT NULL,
  FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB;

CREATE TABLE `sale_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `sale_id` INT NOT NULL,
  `product_id` INT NOT NULL,
  `quantity` DECIMAL(10,2) NOT NULL,
  `buy_price` DECIMAL(15,2) NOT NULL,
  `sell_price` DECIMAL(15,2) NOT NULL,
  `discount` DECIMAL(15,2) DEFAULT 0,
  `subtotal` DECIMAL(15,2) NOT NULL,
  FOREIGN KEY (`sale_id`) REFERENCES `sales`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`)
) ENGINE=InnoDB;

-- ============================================================
-- TRANSACTIONS: PURCHASES
-- ============================================================

CREATE TABLE `purchases` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `invoice_no` VARCHAR(30) UNIQUE NOT NULL,
  `supplier_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `purchase_date` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `subtotal` DECIMAL(15,2) DEFAULT 0,
  `discount` DECIMAL(15,2) DEFAULT 0,
  `tax` DECIMAL(15,2) DEFAULT 0,
  `total` DECIMAL(15,2) DEFAULT 0,
  `paid` DECIMAL(15,2) DEFAULT 0,
  `payment_method` ENUM('tunai','transfer','hutang') DEFAULT 'tunai',
  `status` ENUM('selesai','batal','hutang') DEFAULT 'selesai',
  `notes` TEXT,
  `journal_id` INT DEFAULT NULL,
  FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB;

CREATE TABLE `purchase_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `purchase_id` INT NOT NULL,
  `product_id` INT NOT NULL,
  `quantity` DECIMAL(10,2) NOT NULL,
  `buy_price` DECIMAL(15,2) NOT NULL,
  `subtotal` DECIMAL(15,2) NOT NULL,
  FOREIGN KEY (`purchase_id`) REFERENCES `purchases`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`)
) ENGINE=InnoDB;

-- ============================================================
-- ACCOUNTING JOURNALS
-- ============================================================

CREATE TABLE `journals` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `journal_no` VARCHAR(30) UNIQUE NOT NULL,
  `journal_date` DATE NOT NULL,
  `description` VARCHAR(255) NOT NULL,
  `type` ENUM('penjualan','pembelian','kas_masuk','kas_keluar','umum','penyesuaian') DEFAULT 'umum',
  `reference_type` VARCHAR(50) DEFAULT NULL,
  `reference_id` INT DEFAULT NULL,
  `total_debit` DECIMAL(15,2) DEFAULT 0,
  `total_credit` DECIMAL(15,2) DEFAULT 0,
  `user_id` INT NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB;

CREATE TABLE `journal_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `journal_id` INT NOT NULL,
  `coa_id` INT NOT NULL,
  `description` VARCHAR(255),
  `debit` DECIMAL(15,2) DEFAULT 0,
  `credit` DECIMAL(15,2) DEFAULT 0,
  FOREIGN KEY (`journal_id`) REFERENCES `journals`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`coa_id`) REFERENCES `coa`(`id`)
) ENGINE=InnoDB;

-- ============================================================
-- STOCK MOVEMENTS
-- ============================================================

CREATE TABLE `stock_movements` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT NOT NULL,
  `type` ENUM('masuk','keluar','koreksi') NOT NULL,
  `quantity` DECIMAL(10,2) NOT NULL,
  `stock_before` DECIMAL(10,2) NOT NULL,
  `stock_after` DECIMAL(10,2) NOT NULL,
  `reference_type` VARCHAR(50),
  `reference_id` INT,
  `notes` TEXT,
  `user_id` INT NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB;

-- ============================================================
-- EXPENSES
-- ============================================================

CREATE TABLE `expenses` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `expense_no` VARCHAR(30) UNIQUE NOT NULL,
  `coa_id` INT NOT NULL,
  `expense_date` DATE NOT NULL,
  `description` VARCHAR(255) NOT NULL,
  `amount` DECIMAL(15,2) NOT NULL,
  `payment_method` ENUM('tunai','transfer') DEFAULT 'tunai',
  `journal_id` INT DEFAULT NULL,
  `user_id` INT NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`coa_id`) REFERENCES `coa`(`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB;

-- ============================================================
-- SEED DATA
-- ============================================================

INSERT INTO `users` (`username`,`password`,`full_name`,`email`,`role`) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin@pos.com', 'admin'),
('kasir1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Kasir Satu', 'kasir@pos.com', 'kasir');
-- Default password: password

INSERT INTO `units` (`name`,`symbol`) VALUES ('Pcs','pcs'),('Kg','kg'),('Liter','ltr'),('Lusin','lsn'),('Box','box'),('Botol','btl');

INSERT INTO `categories` (`name`,`description`) VALUES
('Makanan','Produk makanan'),('Minuman','Produk minuman'),('Elektronik','Perangkat elektronik'),('Pakaian','Produk pakaian'),('Peralatan','Peralatan rumah tangga');

-- Chart of Accounts
INSERT INTO `coa` (`code`,`name`,`type`,`normal_balance`) VALUES
('1000','Kas dan Setara Kas','aset','debit'),
('1001','Kas','aset','debit'),
('1002','Bank BCA','aset','debit'),
('1100','Piutang Usaha','aset','debit'),
('1200','Persediaan Barang','aset','debit'),
('1300','Perlengkapan','aset','debit'),
('1500','Aset Tetap','aset','debit'),
('2000','Kewajiban Lancar','kewajiban','kredit'),
('2001','Hutang Usaha','kewajiban','kredit'),
('2002','Hutang Pajak','kewajiban','kredit'),
('3000','Modal','modal','kredit'),
('3001','Modal Pemilik','modal','kredit'),
('3002','Laba Ditahan','modal','kredit'),
('4000','Pendapatan','pendapatan','kredit'),
('4001','Penjualan','pendapatan','kredit'),
('4002','Retur Penjualan','pendapatan','debit'),
('4003','Diskon Penjualan','pendapatan','debit'),
('5000','Harga Pokok Penjualan','beban','debit'),
('5001','HPP','beban','debit'),
('6000','Beban Operasional','beban','debit'),
('6001','Beban Gaji','beban','debit'),
('6002','Beban Sewa','beban','debit'),
('6003','Beban Listrik & Air','beban','debit'),
('6004','Beban Telepon','beban','debit'),
('6005','Beban Transportasi','beban','debit'),
('6006','Beban Perlengkapan','beban','debit'),
('6099','Beban Lain-lain','beban','debit');

-- Sample products
INSERT INTO `products` (`category_id`,`unit_id`,`code`,`name`,`buy_price`,`sell_price`,`stock`,`min_stock`) VALUES
(1,1,'PRD001','Mie Instan Goreng',2500,3500,100,20),
(1,1,'PRD002','Roti Tawar',8000,12000,50,10),
(2,6,'PRD003','Air Mineral 600ml',2000,3000,200,50),
(2,6,'PRD004','Minuman Teh Botol',4000,6000,150,30),
(1,5,'PRD005','Gula Pasir 1kg',12000,15000,80,15);

-- Sample customer & supplier
INSERT INTO `customers` (`code`,`name`,`phone`) VALUES ('CUST001','Umum / Walk-in','-'),('CUST002','Budi Santoso','081234567890');
INSERT INTO `suppliers` (`code`,`name`,`phone`) VALUES ('SUP001','PT. Indofood Sukses Makmur','021-1234567'),('SUP002','CV. Sumber Jaya','031-9876543');
