<?php
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_secure', '1');
session_start();
require __DIR__ . '/../api/db.php';
require __DIR__ . '/../api/helpers.php';

if (isset($_SESSION['authed']) && $_SESSION['authed'] === true) {
    header('Location: /admin/analytics');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim($_POST['username'] ?? '');
    $pass = $_POST['password'] ?? '';

    /* ── IP-based brute-force lockout ──
       File-backed (not session-backed), so an attacker can't reset the
       counter by simply discarding the session cookie. Keyed on the real
       client IP (Cloudflare-aware). 5 fails → 15-minute lockout. ── */
    $lock_dir = __DIR__ . '/../api/logs/ratelimit';
    if (!is_dir($lock_dir)) { @mkdir($lock_dir, 0755, true); }
    $lock_file = $lock_dir . '/login_' . hash('sha256', client_ip()) . '.json';
    $rec = ['attempts' => 0, 'locked_until' => 0];
    if (file_exists($lock_file)) {
        $stored = json_decode(@file_get_contents($lock_file), true);
        if (is_array($stored)) { $rec = array_merge($rec, $stored); }
    }

    /* ── reCAPTCHA v3 (invisible, soft signal) ──
       Blocks ONLY on a confident bot verdict. No token, invalid token, or
       Google unreachable all fail OPEN — a third-party outage must never
       lock you out of your own admin. The IP lockout above is the real gate. ── */
    $rc_is_bot = false;
    $rc_token  = $_POST['recaptcha_token'] ?? '';
    if ($rc_token !== '' && defined('RECAPTCHA_SECRET')) {
        $rc_ctx = stream_context_create(['http' => ['timeout' => 5]]);
        $rc_raw = @file_get_contents(
            'https://www.google.com/recaptcha/api/siteverify?secret=' . urlencode(RECAPTCHA_SECRET)
            . '&response=' . urlencode($rc_token) . '&remoteip=' . urlencode(client_ip()),
            false, $rc_ctx
        );
        $rc = $rc_raw ? json_decode($rc_raw, true) : null;
        if (is_array($rc) && ($rc['success'] ?? false) === true && (float)($rc['score'] ?? 1) < 0.3) {
            $rc_is_bot = true;
        }
    }

    if (time() < $rec['locked_until']) {
        $mins = ceil(($rec['locked_until'] - time()) / 60);
        $error = "Too many failed attempts. Try again in {$mins} minute(s).";
    } elseif (!$rc_is_bot && $user === ADMIN_USER && password_verify($pass, ADMIN_PASS_HASH)) {
        @unlink($lock_file);              // clear lockout on success
        session_regenerate_id(true);     // prevent session fixation
        $_SESSION['authed'] = true;
        /* ── "Do not track" cookie ── so the owner's own visits to the public
           site aren't counted in analytics. Survives IP changes; refreshed on
           every login. 1-year expiry, sent on all paths so track.php sees it. ── */
        setcookie('dp_notrack', '1', [
            'expires'  => time() + 31536000,
            'path'     => '/',
            'secure'   => true,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_write_close();
        header('Location: /admin/analytics');
        exit;
    } else {
        $rec['attempts']++;
        if ($rec['attempts'] >= 5) {
            $rec['locked_until'] = time() + 900; // 15 minutes
            $rec['attempts']     = 0;
            $error = 'Too many failed attempts. Try again in 15 minutes.';
        } else {
            $remaining = 5 - $rec['attempts'];
            $error = $rc_is_bot
                ? "Verification failed — please try again. {$remaining} attempt(s) remaining."
                : "Invalid username or password. {$remaining} attempt(s) remaining.";
        }
        @file_put_contents($lock_file, json_encode($rec), LOCK_EX);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Admin — Login</title>
  <meta name="robots" content="noindex, nofollow"/>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700&family=Geist+Mono:wght@400;500;600&display=swap" rel="stylesheet"/>
  <script src="https://www.google.com/recaptcha/api.js?render=6LdhaJMsAAAAAAJb5MDygyGZks49IXEDUNvrUZgQ" defer></script>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      background: var(--bg); color: var(--text);
      font-family: var(--font-sans);
      min-height: 100vh;
      display: flex; align-items: center; justify-content: center;
    }
    .login-box {
      width: 100%; max-width: 400px;
      padding: 48px 40px;
      border: 1px solid rgba(var(--text-rgb),0.08);
    }
    .login-logo {
      font-size: 14px; letter-spacing: 0.12em; text-transform: uppercase;
      color: rgba(var(--text-rgb),0.4); margin-bottom: 40px;
    }
    h1 { font-size: 28px; font-weight: 600; letter-spacing: -0.03em; margin-bottom: 32px; }
    label {
      display: block; font-size: 12px; letter-spacing: 0.12em;
      text-transform: uppercase; color: rgba(var(--text-rgb),0.4);
      margin-bottom: 8px;
    }
    input {
      width: 100%; background: rgba(var(--text-rgb),0.05);
      border: 1px solid rgba(var(--text-rgb),0.1);
      color: var(--text); font-family: var(--font-sans);
      font-size: 16px; padding: 12px 16px;
      outline: none; margin-bottom: 20px;
      transition: border-color 0.2s;
    }
    input:focus { border-color: var(--red); }
    .error {
      font-size: 14px; color: var(--red);
      margin-bottom: 20px; letter-spacing: 0.02em;
    }
    button {
      width: 100%; background: var(--red); color: var(--text);
      border: none; font-family: var(--font-sans);
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
    <form method="POST" action="" id="login-form">
      <label for="username">Username</label>
      <input type="text" id="username" name="username" autocomplete="username" required/>
      <label for="password">Password</label>
      <input type="password" id="password" name="password" autocomplete="current-password" required/>
      <input type="hidden" name="recaptcha_token" id="recaptcha_token"/>
      <button type="submit">Sign in →</button>
    </form>
  </div>
  <script>
    (function () {
      var KEY  = '6LdhaJMsAAAAAAJb5MDygyGZks49IXEDUNvrUZgQ';
      var form = document.getElementById('login-form');
      form.addEventListener('submit', function (e) {
        if (form.dataset.ok) return;                 // second pass: real submit
        e.preventDefault();
        function go(token) {
          if (token) document.getElementById('recaptcha_token').value = token;
          form.dataset.ok = '1';
          form.submit();
        }
        if (typeof grecaptcha === 'undefined') { go(''); return; }  // script blocked → fail open
        try {
          grecaptcha.ready(function () {
            grecaptcha.execute(KEY, { action: 'login' }).then(go).catch(function () { go(''); });
          });
        } catch (_) { go(''); }
      });
    })();
  </script>
</body>
</html>
