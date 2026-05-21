<?php
require_once '../includes/auth.php';
requireLogin();
header('Content-Type: application/json');

$key   = trim($_POST['key']   ?? '');
$value = trim($_POST['value'] ?? '');

$allowed = ['theme_mode','thermal_size','receipt_footer'];
if (!in_array($key, $allowed)) { echo json_encode(['error'=>'Not allowed']); exit; }

try {
    $db = getDB();
    $db->prepare("INSERT INTO settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=?")
       ->execute([$key, $value, $value]);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
