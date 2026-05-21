<?php
// =============================================
// Authentication Helpers
// =============================================
require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => SESSION_TIMEOUT,
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    session_start();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . APP_URL . '/login.php');
        exit;
    }
    // Session timeout check
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
        session_destroy();
        header('Location: ' . APP_URL . '/login.php?timeout=1');
        exit;
    }
    $_SESSION['last_activity'] = time();
}

function requireAdmin() {
    requireLogin();
    if ($_SESSION['user_role'] !== 'admin') {
        header('Location: ' . APP_URL . '/dashboard.php?error=unauthorized');
        exit;
    }
}

function getCurrentUser() {
    if (!isLoggedIn()) return null;
    return [
        'id'        => $_SESSION['user_id'],
        'username'  => $_SESSION['username'],
        'full_name' => $_SESSION['full_name'],
        'role'      => $_SESSION['user_role']
    ];
}

function loginUser($username, $password, $remember = false) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE username = ? AND status = 'active'");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id']    = $user['id'];
        $_SESSION['username']   = $user['username'];
        $_SESSION['full_name']  = $user['full_name'];
        $_SESSION['user_role']  = $user['role'];
        $_SESSION['last_activity'] = time();

        // Update last login
        $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);

        if ($remember) {
            $token = bin2hex(random_bytes(32));
            setcookie('remember_token', $token, time() + (86400 * 30), '/', '', false, true);
        }

        // Log action
        logAction($user['id'], 'LOGIN', 'User logged in');
        return true;
    }
    return false;
}

function logoutUser() {
    if (isLoggedIn()) {
        logAction($_SESSION['user_id'], 'LOGOUT', 'User logged out');
    }
    session_destroy();
    setcookie('remember_token', '', time() - 3600, '/');
    header('Location: ' . APP_URL . '/login.php');
    exit;
}

function logAction($userId, $action, $details = '') {
    try {
        $db = getDB();
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $db->prepare("INSERT INTO report_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)")
           ->execute([$userId, $action, $details, $ip]);
    } catch (Exception $e) { /* silent */ }
}

// CSRF protection
function generateCSRF() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRF($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// XSS protection
function h($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

// Generate bill number
function generateBillNumber() {
    $prefix = 'JO';
    $date   = date('ymd');
    $rand   = strtoupper(bin2hex(random_bytes(2)));
    return $prefix . $date . $rand;
}

// Format currency
function formatCurrency($amount) {
    $symbol = getSetting('currency_symbol', 'Rs.');
    return $symbol . ' ' . number_format((float)$amount, 2);
}
