-- =============================================
-- JEWEL ONE 1 MATALE - POS System Database
-- =============================================

CREATE DATABASE IF NOT EXISTS jewel_one_pos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE jewel_one_pos;

-- Users Table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(200) NOT NULL,
    email VARCHAR(200),
    role ENUM('admin', 'cashier') NOT NULL DEFAULT 'cashier',
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    avatar VARCHAR(255),
    last_login DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Bills Table
CREATE TABLE IF NOT EXISTS bills (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bill_number VARCHAR(50) NOT NULL UNIQUE,
    cashier_id INT NOT NULL,
    customer_name VARCHAR(200),
    customer_phone VARCHAR(20),
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    total_discount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    tax_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    grand_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    balance_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    payment_method ENUM('cash', 'card', 'bank_transfer') NOT NULL DEFAULT 'cash',
    status ENUM('completed', 'draft', 'cancelled') NOT NULL DEFAULT 'completed',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (cashier_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Bill Items Table
CREATE TABLE IF NOT EXISTS bill_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bill_id INT NOT NULL,
    product_name VARCHAR(300) NOT NULL,
    quantity DECIMAL(10,3) NOT NULL DEFAULT 1,
    unit_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    discount_type ENUM('none', 'percentage', 'fixed') NOT NULL DEFAULT 'none',
    discount_value DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    original_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    final_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bill_id) REFERENCES bills(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Settings Table
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Report Logs Table
CREATE TABLE IF NOT EXISTS report_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action VARCHAR(200) NOT NULL,
    details TEXT,
    ip_address VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =============================================
-- DEFAULT DATA
-- =============================================

-- Default Admin User (password: admin123)
INSERT INTO users (username, password, full_name, email, role) VALUES
('admin', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMZJool/YLFN.I0J.TzGYgCjN2', 'System Administrator', 'admin@jewelone.lk', 'admin'),
('cashier1', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMZJool/YLFN.I0J.TzGYgCjN2', 'Kasun Perera', 'kasun@jewelone.lk', 'cashier')
ON DUPLICATE KEY UPDATE id=id;

-- Default Settings
INSERT INTO settings (setting_key, setting_value) VALUES
('shop_name', 'JEWEL ONE 1'),
('shop_address', 'Matale, Sri Lanka'),
('shop_phone', '+94 66 222 3333'),
('shop_email', 'info@jewelone.lk'),
('tax_enabled', '0'),
('tax_percentage', '0'),
('currency_symbol', 'Rs.'),
('receipt_footer', 'Thank You Come Again'),
('thermal_size', '80mm'),
('theme_mode', 'dark'),
('logo_path', ''),
('receipt_copies', '1')
ON DUPLICATE KEY UPDATE id=id;

-- =============================================
-- INDEXES FOR PERFORMANCE
-- =============================================
CREATE INDEX idx_bills_cashier ON bills(cashier_id);
CREATE INDEX idx_bills_created ON bills(created_at);
CREATE INDEX idx_bills_status ON bills(status);
CREATE INDEX idx_bill_items_bill ON bill_items(bill_id);
CREATE INDEX idx_bill_items_product ON bill_items(product_name);
