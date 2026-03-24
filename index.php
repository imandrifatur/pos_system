<?php
session_start();
require_once __DIR__ . '/config/database.php';

if (isLoggedIn()) { header('Location: ' . APP_URL . '/dashboard.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['username']  = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role']      = $user['role'];
        header('Location: ' . APP_URL . '/dashboard.php');
        exit;
    } else {
        $error = 'Username atau password salah.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — <?= APP_NAME ?></title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
</head>
<body>
<div class="login-wrapper">
  <div class="login-box">
    <div class="login-logo">
      <div class="logo-icon"><i class="fas fa-store-alt"></i></div>
      <h1><?= APP_NAME ?></h1>
      <p>Point of Sale & Sistem Akuntansi</p>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-danger" style="margin-bottom:16px">
      <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <form method="POST" action="">
      <div class="form-group" style="margin-bottom:14px">
        <label class="form-label">Username</label>
        <input type="text" name="username" class="form-control" placeholder="Masukkan username" required autofocus>
      </div>
      <div class="form-group" style="margin-bottom:20px">
        <label class="form-label">Password</label>
        <div style="position:relative">
          <input type="password" name="password" id="pwd" class="form-control" placeholder="Masukkan password" required style="padding-right:40px">
          <button type="button" onclick="togglePwd()" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--text-muted);cursor:pointer">
            <i class="fas fa-eye" id="eye-icon"></i>
          </button>
        </div>
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:12px">
        <i class="fas fa-sign-in-alt"></i> Masuk
      </button>
    </form>

    <p style="text-align:center;margin-top:16px;font-size:12px;color:var(--text-muted)">
      Default: admin / password
    </p>
  </div>
</div>
<script>
function togglePwd() {
  const p = document.getElementById('pwd');
  const i = document.getElementById('eye-icon');
  if (p.type === 'password') { p.type = 'text'; i.className = 'fas fa-eye-slash'; }
  else { p.type = 'password'; i.className = 'fas fa-eye'; }
}
</script>
</body>
</html>
