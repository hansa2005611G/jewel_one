<?php
require_once '../includes/auth.php';
requireLogin();
header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 1) {
    echo json_encode([]);
    exit;
}

try {
    $db = getDB();
    // Search by name or SKU
    $stmt = $db->prepare("SELECT id, sku, name, category, current_stock, unit_price FROM products WHERE name LIKE ? OR sku LIKE ? LIMIT 10");
    $stmt->execute(["%$q%", "%$q%"]);
    $products = $stmt->fetchAll();
    echo json_encode($products);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
