<?php
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
session_start();
require __DIR__ . '/../api/db.php';

if (isset($_SESSION['authed']) && $_SESSION['authed'] === true) {
    header('Location: /admin/analytics');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim($_POST['username'] ?? '');
    $pass = $_POST['password'] ?? '';

    /* Brute force lockout */
    $_SESSION['login_attempts']    ??= 0;
    $_SESSION['login_locked_until'] ??= 0;

    if (time() < $_SESSION['login_locked_until']) {
        $mins = ceil(($_SESSION['login_locked_until'] - time()) / 60);
        $error = "Too many failed attempts. Try again in {$mins} minute(s).";
    } elseif ($user === ADMIN_USER && password_verify($pass, ADMIN_PASS_HASH)) {
        $_SESSION['login_attempts']    = 0;
        $_SESSION['login_locked_until'] = 0;
        $_SESSION['authed'] = true;
        session_write_close();
        header('Location: /admin/analytics');
        exit;
    } else {
        $_SESSION['login_attempts']++;
        if ($_SESSION['login_attempts'] >= 5) {
            $_SESSION['login_locked_until'] = time() + 900; // 15 minutes
            $_SESSION['login_attempts']     = 0;
            $error = 'Too many failed attempts. Try again in 15 minutes.';
        } else {
            $remaining = 5 - $_SESSION['login_attempts'];
            $error = "Invalid username or password. {$remaining} attempt(s) remaining.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Admin — Login</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      background: #0D0C09; color: #ECEAE2;
      font-family: 'Inter', sans-serif;
      min-height: 100vh;
      display: flex; align-items: center; justify-content: center;
    }
    .login-box {
      width: 100%; max-width: 400px;
      padding: 48px 40px;
      border: 1px solid rgba(236,234,226,0.08);
    }
    .login-logo {
      font-size: 13px; letter-spacing: 0.12em; text-transform: uppercase;
      color: rgba(236,234,226,0.4); margin-bottom: 40px;
    }
    h1 { font-size: 28px; font-weight: 600; letter-spacing: -0.03em; margin-bottom: 32px; }
    label {
      display: block; font-size: 11px; letter-spacing: 0.12em;
      text-transform: uppercase; color: rgba(236,234,226,0.4);
      margin-bottom: 8px;
    }
    input {
      width: 100%; background: rgba(236,234,226,0.05);
      border: 1px solid rgba(236,234,226,0.1);
      color: #ECEAE2; font-family: 'Inter', sans-serif;
      font-size: 15px; padding: 12px 14px;
      outline: none; margin-bottom: 20px;
      transition: border-color 0.2s;
    }
    input:focus { border-color: #E8320A; }
    .error {
      font-size: 12px; color: #E8320A;
      margin-bottom: 20px; letter-spacing: 0.02em;
    }
    button {
      width: 100%; background: #E8320A; color: #ECEAE2;
      border: none; font-family: 'Inter', sans-serif;
      font-size: 12px; font-weight: 600; letter-spacing: 0.1em;
      text-transform: uppercase; padding: 14px;
      cursor: pointer; transition: opacity 0.2s;
    }
    button:hover { opacity: 0.85; }
  </style>
</head>
<body>
  <div class="login-box">
    <div class="login-logo">DP — Admin</div>
    <h1>Sign in</h1>
    <?php if ($error): ?>
      <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="POST" action="login.php">
      <label for="username">Username</label>
      <input type="text" id="username" name="username" autocomplete="username" required/>
      <label for="password">Password</label>
      <input type="password" id="password" name="password" autocomplete="current-password" required/>
      <button type="submit">Sign in →</button>
    </form>
  </div>
</body>
</html>
