<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

if (isLoggedIn()) {
    header('Location: ' . APP_URL . '/dashboard.php');
    exit;
}

$error   = '';
$timeout = isset($_GET['timeout']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    if (empty($username) || empty($password)) {
        $error = 'Please enter your username and password.';
    } elseif (loginUser($username, $password, $remember)) {
        header('Location: ' . APP_URL . '/dashboard.php');
        exit;
    } else {
        $error = 'Invalid username or password.';
    }
}

$csrf = generateCSRF();
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — JEWEL ONE 1 MATALE POS</title>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@300;400;600;700&family=Josefin+Sans:wght@300;400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="<?= APP_URL ?>/css/style.css">
<style>
  .login-particles { position: absolute; inset: 0; pointer-events: none; overflow: hidden; }
  .particle {
    position: absolute;
    width: 2px; height: 2px;
    background: var(--gold);
    border-radius: 50%;
    opacity: 0;
    animation: float linear infinite;
  }
  @keyframes float {
    0%   { transform: translateY(100vh) scale(0); opacity: 0; }
    10%  { opacity: 0.6; }
    90%  { opacity: 0.3; }
    100% { transform: translateY(-10vh) scale(1); opacity: 0; }
  }
</style>
</head>
<body class="dark-mode">
<div class="login-page">
  <div class="login-bg"></div>
  <div class="login-particles" id="particles"></div>

  <div class="login-box fade-in">
    <div class="login-logo">
      <span class="login-diamond">◆</span>
      <span class="login-shop-name">JEWEL ONE 1</span>
      <span class="login-subtitle">Matale · Point of Sale System</span>
    </div>

    <?php if ($timeout): ?>
    <div class="login-error"><i class="fas fa-clock"></i> Session expired. Please login again.</div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="login-error"><i class="fas fa-exclamation-circle"></i> <?= h($error) ?></div>
    <?php endif; ?>

    <form method="POST" id="loginForm">
      <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">

      <div class="form-group">
        <label class="form-label"><i class="fas fa-user"></i> Username</label>
        <input type="text" name="username" class="form-control" placeholder="Enter your username"
          value="<?= h($_POST['username'] ?? '') ?>" required autofocus autocomplete="username">
      </div>

      <div class="form-group">
        <label class="form-label"><i class="fas fa-lock"></i> Password</label>
        <div style="position:relative">
          <input type="password" name="password" class="form-control" id="passwordInput"
            placeholder="Enter your password" required autocomplete="current-password">
          <button type="button" onclick="togglePass()" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--gray-4);cursor:pointer;font-size:14px" id="eyeBtn">
            <i class="fas fa-eye"></i>
          </button>
        </div>
      </div>

      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px">
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:12px;color:var(--gray-3)">
          <input type="checkbox" name="remember" style="accent-color:var(--gold)">
          Remember me
        </label>
        <span style="font-size:12px;color:var(--gray-4)">Admin: admin / admin123</span>
      </div>

      <button type="submit" class="btn btn-gold" style="width:100%;justify-content:center;padding:13px;font-size:13px">
        <i class="fas fa-sign-in-alt"></i> Sign In to POS
      </button>
    </form>

    <div style="text-align:center;margin-top:24px">
      <div class="gold-line" style="margin:16px 0"></div>
      <span style="font-size:11px;color:var(--gray-4);letter-spacing:1.5px">JEWEL ONE 1 MATALE · v1.0.0</span>
    </div>
  </div>
</div>

<script>
function togglePass() {
  const inp = document.getElementById('passwordInput');
  const ico = document.querySelector('#eyeBtn i');
  if (inp.type === 'password') { inp.type = 'text'; ico.className = 'fas fa-eye-slash'; }
  else { inp.type = 'password'; ico.className = 'fas fa-eye'; }
}

// Animated particles
(function() {
  const container = document.getElementById('particles');
  for (let i = 0; i < 25; i++) {
    const p = document.createElement('div');
    p.className = 'particle';
    p.style.left = Math.random() * 100 + '%';
    p.style.width = p.style.height = (Math.random() * 3 + 1) + 'px';
    p.style.animationDuration = (Math.random() * 15 + 8) + 's';
    p.style.animationDelay    = (Math.random() * 10) + 's';
    p.style.opacity = Math.random() * 0.5;
    container.appendChild(p);
  }
})();
</script>
</body>
</html>
