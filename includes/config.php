<?php
// =============================================
// Database Configuration - JEWEL ONE 1 MATALE
// =============================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');          // Change to your MySQL username
define('DB_PASS', '');              // Change to your MySQL password
define('DB_NAME', 'jewel_one_pos');
define('DB_CHARSET', 'utf8mb4');

define('APP_NAME', 'JEWEL ONE 1 MATALE');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost/jewel_one');
define('SESSION_TIMEOUT', 3600); // 1 hour

// Establish PDO Connection
function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            
            // Run Auto-Migrations
            runMigrations($pdo);
        } catch (PDOException $e) {
            die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
        }
    }
    return $pdo;
}

// Auto-Migrations Helper
function runMigrations($db) {
    try {
        // Create products table
        $db->exec("CREATE TABLE IF NOT EXISTS products (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sku VARCHAR(50) NULL UNIQUE,
            name VARCHAR(300) NOT NULL UNIQUE,
            category VARCHAR(150) NULL,
            current_stock DECIMAL(10,3) NOT NULL DEFAULT 0.000,
            min_stock_level DECIMAL(10,3) NOT NULL DEFAULT 5.000,
            cost_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            unit_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB");

        // Check if product_id column exists in bill_items
        $checkCol = $db->query("SHOW COLUMNS FROM bill_items LIKE 'product_id'")->fetch();
        if (!$checkCol) {
            // Add column product_id to bill_items
            $db->exec("ALTER TABLE bill_items ADD COLUMN product_id INT NULL AFTER bill_id");
            // Add foreign key constraint
            $db->exec("ALTER TABLE bill_items ADD CONSTRAINT fk_bill_items_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL");
        }
    } catch (Exception $e) {
        // Fail silently
    }
}

// Get setting value
function getSetting($key, $default = '') {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        return $row ? $row['setting_value'] : $default;
    } catch (Exception $e) {
        return $default;
    }
}
