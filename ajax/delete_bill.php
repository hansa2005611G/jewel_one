<?php
require_once '../includes/auth.php';
requireLogin();
requireAdmin();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Invalid request']); exit;
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) { echo json_encode(['error' => 'Invalid bill ID']); exit; }

try {
    $db = getDB();
    $db->beginTransaction();

    // Check existing status
    $stmt = $db->prepare("SELECT status FROM bills WHERE id = ?");
    $stmt->execute([$id]);
    $bill = $stmt->fetch();

    if ($bill && $bill['status'] === 'completed') {
        // Fetch items and restore stock
        $itemsStmt = $db->prepare("SELECT product_id, quantity FROM bill_items WHERE bill_id = ? AND product_id IS NOT NULL");
        $itemsStmt->execute([$id]);
        $items = $itemsStmt->fetchAll();

        $restoreStockStmt = $db->prepare("UPDATE products SET current_stock = current_stock + ? WHERE id = ?");
        foreach ($items as $item) {
            $restoreStockStmt->execute([$item['quantity'], $item['product_id']]);
        }
    }

    $stmt = $db->prepare("UPDATE bills SET status='cancelled' WHERE id=?");
    $stmt->execute([$id]);

    $db->commit();

    logAction($_SESSION['user_id'], 'DELETE_BILL', "Bill ID #{$id} cancelled and stock restored");
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    echo json_encode(['error' => $e->getMessage()]);
}
