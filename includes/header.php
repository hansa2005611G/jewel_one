<?php
require_once __DIR__ . '/auth.php';
requireLogin();
$currentUser = getCurrentUser();
$shopName    = getSetting('shop_name', 'JEWEL ONE 1');
$theme       = getSetting('theme_mode', 'dark');
$currency    = getSetting('currency_symbol', 'Rs.');

$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= h($theme) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($shopName) ?> - POS System</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@300;400;500;600;700&family=Josefin+Sans:wght@300;400;600;700&family=Playfair+Display:wght@400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="<?= APP_URL ?>/css/style.css">
<link rel="stylesheet" href="<?= APP_URL ?>/css/extra.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body class="<?= $theme === 'dark' ? 'dark-mode' : 'light-mode' ?>">

<!-- Sidebar -->
<nav class="sidebar" id="sidebar">
  <div class="sidebar-header">
    <div class="sidebar-logo">
      <div class="logo-diamond">◆</div>
      <div class="logo-text">
        <span class="logo-main">JEWEL ONE</span>
        <span class="logo-sub">1 MATALE · POS</span>
      </div>
    </div>
    <button class="sidebar-toggle" id="sidebarToggle"><i class="fas fa-bars"></i></button>
  </div>

  <div class="sidebar-user">
    <div class="user-avatar"><i class="fas fa-user-circle"></i></div>
    <div class="user-info">
      <span class="user-name"><?= h($currentUser['full_name']) ?></span>
      <span class="user-role"><?= ucfirst(h($currentUser['role'])) ?></span>
    </div>
  </div>

  <ul class="sidebar-menu">
    <li class="menu-label">MAIN</li>
    <li class="<?= $currentPage === 'dashboard' ? 'active' : '' ?>">
      <a href="<?= APP_URL ?>/dashboard.php"><i class="fas fa-chart-line"></i><span>Dashboard</span></a>
    </li>
    <li class="<?= $currentPage === 'billing' ? 'active' : '' ?>">
      <a href="<?= APP_URL ?>/billing.php"><i class="fas fa-cash-register"></i><span>New Bill / POS</span></a>
    </li>
    <li class="<?= $currentPage === 'bill_history' ? 'active' : '' ?>">
      <a href="<?= APP_URL ?>/bill_history.php"><i class="fas fa-receipt"></i><span>Bill History</span></a>
    </li>

    <?php if ($currentUser['role'] === 'admin'): ?>
    <li class="menu-label">REPORTS</li>
    <li class="<?= $currentPage === 'reports' ? 'active' : '' ?>">
      <a href="<?= APP_URL ?>/reports.php"><i class="fas fa-chart-bar"></i><span>Reports & Analytics</span></a>
    </li>
    <li class="<?= $currentPage === 'ai_predictions' ? 'active' : '' ?>">
      <a href="<?= APP_URL ?>/ai_predictions.php"><i class="fas fa-magic"></i><span>AI Predictions</span></a>
    </li>

    <li class="menu-label">ADMIN</li>
    <li class="<?= $currentPage === 'users' ? 'active' : '' ?>">
      <a href="<?= APP_URL ?>/modules/users.php"><i class="fas fa-users"></i><span>Manage Users</span></a>
    </li>
    <li class="<?= $currentPage === 'inventory' ? 'active' : '' ?>">
      <a href="<?= APP_URL ?>/modules/inventory.php"><i class="fas fa-boxes"></i><span>Inventory</span></a>
    </li>
    <li class="<?= $currentPage === 'settings' ? 'active' : '' ?>">
      <a href="<?= APP_URL ?>/modules/settings.php"><i class="fas fa-cog"></i><span>Settings</span></a>
    </li>
    <li class="<?= $currentPage === 'backup' ? 'active' : '' ?>">
      <a href="<?= APP_URL ?>/modules/backup.php"><i class="fas fa-database"></i><span>DB Backup</span></a>
    </li>
    <?php endif; ?>
  </ul>

  <div class="sidebar-footer">
    <a href="<?= APP_URL ?>/logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
  </div>
</nav>

<!-- Main content wrapper -->
<div class="main-wrapper" id="mainWrapper">

  <!-- Top navbar -->
  <header class="topbar">
    <button class="topbar-toggle" id="topbarToggle"><i class="fas fa-bars"></i></button>
    <div class="topbar-title"><?= h($shopName) ?></div>
    <div class="topbar-actions">
      <button class="theme-toggle" id="themeToggle" title="Toggle Theme">
        <i class="fas fa-<?= $theme === 'dark' ? 'sun' : 'moon' ?>"></i>
      </button>
      <div class="topbar-time" id="topbarTime"></div>
      <a href="<?= APP_URL ?>/billing.php" class="btn-new-bill">
        <i class="fas fa-plus"></i> New Bill
      </a>
    </div>
  </header>

  <div class="content-area">
