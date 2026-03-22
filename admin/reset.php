<?php
/* ── One-time password reset — DELETE THIS FILE after use ── */
$secret = 'denny2026reset'; /* simple URL protection */

if (($_GET['key'] ?? '') !== $secret) {
    http_response_code(403); die('Not found.');
}

$done = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pass    = $_POST['password']  ?? '';
    $confirm = $_POST['confirm']   ?? '';
    if (strlen($pass) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($pass !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $hash = password_hash($pass, PASSWORD_BCRYPT);
        if (file_put_contents(__DIR__ . '/../api/.admin_hash', $hash) !== false) {
            $done = true;
        } else {
            $error = 'Could not write file. Check folder permissions.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <title>Reset Password</title>
  <style>
    body { background:#0D0C09; color:#ECEAE2; font-family:system-ui,sans-serif;
           display:flex; align-items:center; justify-content:center; min-height:100vh; }
    .box { width:100%; max-width:380px; padding:40px; border:1px solid rgba(236,234,226,0.1); }
    h1 { font-size:20px; margin-bottom:28px; }
    label { display:block; font-size:11px; letter-spacing:0.1em; text-transform:uppercase;
            color:rgba(236,234,226,0.4); margin-bottom:6px; }
    input { width:100%; background:rgba(236,234,226,0.05); border:1px solid rgba(236,234,226,0.15);
            color:#ECEAE2; font-size:15px; padding:11px 13px; outline:none; margin-bottom:18px; }
    input:focus { border-color:#E8320A; }
    button { background:#E8320A; color:#ECEAE2; border:none; width:100%;
             font-size:13px; font-weight:600; letter-spacing:0.08em; text-transform:uppercase;
             padding:13px; cursor:pointer; }
    .error   { color:#E8320A; font-size:13px; margin-bottom:18px; }
    .success { color:#6fcf97; font-size:14px; line-height:1.6; }
    .warn    { color:rgba(236,234,226,0.35); font-size:11px; margin-top:20px; }
  </style>
</head>
<body>
<div class="box">
  <?php if ($done): ?>
    <div class="success">
      ✓ Password updated successfully.<br/><br/>
      <a href="login.php" style="color:#E8320A;">Go to login →</a>
    </div>
    <div class="warn">⚠ Delete this file from your server now:<br/>admin/reset.php</div>
  <?php else: ?>
    <h1>Reset Admin Password</h1>
    <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="POST">
      <label>New Password</label>
      <input type="password" name="password" placeholder="Min. 8 characters" required/>
      <label>Confirm Password</label>
      <input type="password" name="confirm" placeholder="Repeat password" required/>
      <button type="submit">Set Password →</button>
    </form>
    <div class="warn">⚠ Delete this file after use.</div>
  <?php endif; ?>
</div>
</body>
</html>
