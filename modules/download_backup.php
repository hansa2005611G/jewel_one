<?php
require_once '../includes/auth.php';
requireAdmin();

$file    = basename($_GET['file'] ?? '');
$allowed = preg_match('/^jewel_one_backup_[\d_]+\.sql$/', $file);
$path    = dirname(__DIR__) . '/backup/' . $file;

if (!$allowed || !file_exists($path)) {
    die('File not found.');
}

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $file . '"');
header('Content-Length: ' . filesize($path));
readfile($path);
exit;
