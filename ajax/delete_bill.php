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
    $stmt = $db->prepare("UPDATE bills SET status='cancelled' WHERE id=?");
    $stmt->execute([$id]);
    logAction($_SESSION['user_id'], 'DELETE_BILL', "Bill ID #{$id} cancelled");
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
