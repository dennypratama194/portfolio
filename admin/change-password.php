<?php
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
session_start();
if (!isset($_SESSION['authed'])) { header('Location: login.php'); exit; }
require __DIR__ . '/../api/db.php';

$_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['csrf'] ?? '') !== $_SESSION['csrf_token']) {
        http_response_code(403); exit('Forbidden.');
    }
    $new     = $_POST['new_password']     ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (strlen($new) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($new !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $hash      = password_hash($new, PASSWORD_BCRYPT);
        $hash_file = __DIR__ . '/../api/.admin_hash';

        if (file_put_contents($hash_file, $hash) !== false) {
            $success = 'Password updated successfully.';
        } else {
            $error = 'Could not save password. Check folder permissions.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Change Password — Admin</title>
  <script>(function(){var t=localStorage.getItem('admin-theme')||'dark';document.documentElement.setAttribute('data-theme',t);})();</script>
  <link rel="icon" type="image/png" href="/assets/logo.png"/>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="/admin/admin.css"/>
  <link rel="stylesheet" href="/admin/theme.css"/>
</head>
<body>

  <aside class="sidebar">
    <div class="sidebar-logo"><img src="/assets/logo.png" alt="Denny Pratama" style="height:28px;width:auto;opacity:0.85;"/></div>
    <nav class="sidebar-nav">
      <a class="sidebar-link" href="analytics.php">Dashboard</a>
      <a class="sidebar-link" href="index.php">Posts</a>
      <a class="sidebar-link" href="auto-post.php">Auto Post</a>
      <a class="sidebar-link active" href="change-password.php">Change Password</a>
      <a class="sidebar-link" href="../index.html" target="_blank">View Site →</a>
    </nav>
    <div class="sidebar-bottom">
      <button class="theme-toggle" id="theme-toggle">◑ Light mode</button>
      <a class="sidebar-logout" href="logout.php">Sign out</a>
    </div>
  </aside>

  <main class="main main--narrow">
    <div class="top-bar top-bar--gap">
      <a class="back-link" href="index.php">← Posts</a>
      <h1>Change Password</h1>
    </div>

    <?php if ($error):   ?><div class="msg-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="msg-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

    <form method="POST" action="change-password.php">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"/>
      <div class="field">
        <label for="new_password">New Password</label>
        <input type="password" id="new_password" name="new_password"
               placeholder="Min. 8 characters" required/>
        <div class="hint">At least 8 characters.</div>
      </div>
      <div class="field">
        <label for="confirm_password">Confirm Password</label>
        <input type="password" id="confirm_password" name="confirm_password"
               placeholder="Repeat new password" required/>
      </div>
      <button type="submit" class="btn-save">Update Password →</button>
    </form>
  </main>

  <script>
    (function(){
      var btn = document.getElementById('theme-toggle');
      function update(){
        var dark = document.documentElement.getAttribute('data-theme') === 'dark';
        btn.textContent = dark ? '◑ Light mode' : '◐ Dark mode';
      }
      update();
      btn.addEventListener('click', function(){
        var next = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', next);
        localStorage.setItem('admin-theme', next);
        update();
      });
    })();
  </script>
</body>
</html>
