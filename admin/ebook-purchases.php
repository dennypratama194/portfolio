<?php
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
session_start();
if (!isset($_SESSION['authed'])) { header('Location: /admin/login'); exit; }
require __DIR__ . '/../api/db.php';

$_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));

/* ── Delete purchase ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (($_POST['csrf'] ?? '') !== $_SESSION['csrf_token']) {
        http_response_code(403); exit('Forbidden.');
    }
    $del_id = (int)($_POST['purchase_id'] ?? 0);
    if ($del_id) {
        $pdo->prepare('DELETE FROM ebook_purchases WHERE id = ?')->execute([$del_id]);
    }
    $redir = 'ebook-purchases.php';
    if (!empty($_POST['product_id'])) $redir .= '?product_id=' . (int)$_POST['product_id'];
    if (!empty($_POST['q']))          $redir .= (str_contains($redir, '?') ? '&' : '?') . 'q=' . urlencode($_POST['q']);
    header('Location: /admin/' . $redir);
    exit;
}

$product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : null;
$q          = trim($_GET['q'] ?? '');
$product    = null;

/* ── Load product if filtering by one ── */
if ($product_id) {
    $stmt = $pdo->prepare('SELECT id, title FROM ebook_products WHERE id = ?');
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
    if (!$product) { header('Location: /admin/ebooks'); exit; }
}

/* ── Build WHERE conditions ── */
$where  = [];
$params = [];

if ($product_id) {
    $where[]  = 'pu.product_id = ?';
    $params[] = $product_id;
}
if ($q !== '') {
    $where[]  = 'pu.email LIKE ?';
    $params[] = '%' . $q . '%';
}

$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

/* ── Stats: count + revenue ── */
$stats_stmt = $pdo->prepare(
    "SELECT COUNT(*) AS total_count, COALESCE(SUM(ep.price), 0) AS total_revenue
     FROM ebook_purchases pu
     JOIN ebook_products ep ON ep.id = pu.product_id
     $where_sql"
);
$stats_stmt->execute($params);
$stats = $stats_stmt->fetch();

/* ── Purchases list ── */
$list_stmt = $pdo->prepare(
    "SELECT pu.id, pu.email, pu.token, pu.paid_at, pu.last_read_chapter, pu.product_id,
            ep.title AS product_title
     FROM ebook_purchases pu
     JOIN ebook_products ep ON ep.id = pu.product_id
     $where_sql
     ORDER BY pu.paid_at DESC"
);
$list_stmt->execute($params);
$purchases = $list_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Purchases<?= $product ? ' — ' . htmlspecialchars($product['title']) : '' ?> — Admin</title>
  <script>(function(){var t=localStorage.getItem('admin-theme')||'dark';document.documentElement.setAttribute('data-theme',t);})();</script>
  <link rel="icon" type="image/png" href="/assets/logo.png"/>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="theme.css"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { background: #0D0C09; color: #ECEAE2; font-family: 'Inter', sans-serif; min-height: 100vh; }

    /* ── Sidebar ── */
    .sidebar {
      position: fixed; top: 0; left: 0; bottom: 0; width: 220px;
      border-right: 1px solid rgba(236,234,226,0.07);
      padding: 32px 24px; display: flex; flex-direction: column; gap: 32px;
    }
    .sidebar-logo { font-size: 13px; letter-spacing: 0.12em; text-transform: uppercase; color: rgba(236,234,226,0.4); }
    .sidebar-nav { display: flex; flex-direction: column; gap: 4px; }
    .sidebar-link {
      font-size: 13px; color: rgba(236,234,226,0.5); text-decoration: none;
      padding: 8px 12px; transition: color 0.2s;
    }
    .sidebar-link:hover, .sidebar-link.active { color: #ECEAE2; }
    .sidebar-link.active { background: rgba(236,234,226,0.05); }
    .sidebar-bottom { margin-top: auto; }
    .sidebar-logout {
      font-size: 11px; letter-spacing: 0.1em; text-transform: uppercase;
      color: rgba(236,234,226,0.25); text-decoration: none; transition: color 0.2s;
    }
    .sidebar-logout:hover { color: #E8320A; }

    /* ── Main ── */
    .main { margin-left: 220px; padding: 48px 48px 80px; }

    /* ── Top bar ── */
    .top-bar { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 32px; gap: 24px; }
    .top-bar-left { display: flex; flex-direction: column; gap: 6px; }
    .back-link {
      font-size: 12px; letter-spacing: 0.08em; text-transform: uppercase;
      color: rgba(236,234,226,0.3); text-decoration: none; transition: color 0.2s;
    }
    .back-link:hover { color: #ECEAE2; }
    h1 { font-size: 22px; font-weight: 600; letter-spacing: -0.02em; }

    /* ── Revenue stat ── */
    .revenue-stat {
      font-size: 13px; color: rgba(236,234,226,0.4);
      padding: 10px 18px;
      border: 1px solid rgba(236,234,226,0.08);
      text-align: right; white-space: nowrap;
    }
    .revenue-stat strong { color: #ECEAE2; font-size: 15px; font-weight: 600; }
    .revenue-count { display: block; font-size: 11px; color: rgba(236,234,226,0.3); margin-top: 2px; }

    /* ── Search ── */
    .search-bar { display: flex; gap: 8px; margin-bottom: 32px; }
    .search-input {
      flex: 1; max-width: 320px;
      background: rgba(236,234,226,0.05); border: 1px solid rgba(236,234,226,0.1);
      color: #ECEAE2; font-family: 'Inter', sans-serif; font-size: 14px;
      padding: 10px 14px; outline: none; transition: border-color 0.2s;
    }
    .search-input:focus { border-color: #E8320A; }
    .search-input::placeholder { color: rgba(236,234,226,0.25); }
    .btn-search {
      background: rgba(236,234,226,0.08); border: 1px solid rgba(236,234,226,0.1);
      color: rgba(236,234,226,0.6); font-family: 'Inter', sans-serif; font-size: 12px;
      letter-spacing: 0.08em; text-transform: uppercase;
      padding: 10px 18px; cursor: pointer; transition: background 0.2s, color 0.2s;
    }
    .btn-search:hover { background: rgba(236,234,226,0.12); color: #ECEAE2; }
    .btn-clear {
      font-size: 12px; letter-spacing: 0.06em; text-transform: uppercase;
      color: rgba(236,234,226,0.25); text-decoration: none; padding: 10px 0;
      transition: color 0.2s; align-self: center;
    }
    .btn-clear:hover { color: #ECEAE2; }

    /* ── Table ── */
    table { width: 100%; border-collapse: collapse; }
    th {
      text-align: left; font-size: 11px; letter-spacing: 0.1em; text-transform: uppercase;
      color: rgba(236,234,226,0.3); font-weight: 500;
      padding: 0 16px 16px 0; border-bottom: 1px solid rgba(236,234,226,0.07);
    }
    td {
      padding: 16px 16px 16px 0;
      border-bottom: 1px solid rgba(236,234,226,0.05);
      font-size: 14px; color: rgba(236,234,226,0.7); vertical-align: middle;
    }
    tr:hover td { background: rgba(236,234,226,0.02); }
    .email-cell { color: #ECEAE2; font-weight: 500; }
    .product-cell { font-size: 13px; color: rgba(236,234,226,0.45); }
    .token-cell {
      font-family: monospace; font-size: 12px;
      color: rgba(236,234,226,0.3); letter-spacing: 0.04em;
    }
    .date-cell { font-size: 13px; white-space: nowrap; }
    .chapter-cell { font-size: 13px; color: rgba(236,234,226,0.4); }
    .btn-resend {
      background: none; border: none; cursor: pointer; padding: 0;
      font-family: 'Inter', sans-serif; font-size: 12px; letter-spacing: 0.06em;
      text-transform: uppercase; color: rgba(236,234,226,0.3); transition: color 0.2s;
    }
    .btn-resend:hover { color: #ECEAE2; }
    .btn-resend:disabled { color: rgba(236,234,226,0.15); cursor: default; }
    .btn-delete {
      background: none; border: none; cursor: pointer; padding: 0;
      font-family: 'Inter', sans-serif; font-size: 12px; letter-spacing: 0.06em;
      text-transform: uppercase; color: rgba(232,50,10,0.4); transition: color 0.2s;
    }
    .btn-delete:hover { color: #E8320A; }

    .empty { padding: 64px 0; text-align: center; color: rgba(236,234,226,0.2); font-size: 14px; }

    /* ── Toast ── */
    #toast {
      position: fixed; bottom: 32px; right: 32px;
      padding: 12px 20px; font-size: 13px;
      opacity: 0; pointer-events: none;
      transition: opacity 0.25s; z-index: 9999;
    }
    #toast.show { opacity: 1; }
    #toast.ok  { background: rgba(76,175,80,0.15); border: 1px solid rgba(76,175,80,0.35); color: #81c784; }
    #toast.err { background: rgba(232,50,10,0.12); border: 1px solid rgba(232,50,10,0.3);  color: #E8320A; }
  </style>
</head>
<body>

  <aside class="sidebar">
    <div class="sidebar-logo"><img src="/assets/logo.png" alt="Denny Pratama" style="height:28px;width:auto;opacity:0.85;"/></div>
    <nav class="sidebar-nav">
      <a class="sidebar-link" href="analytics.php">Dashboard</a>
      <a class="sidebar-link" href="index.php">Posts</a>
      <a class="sidebar-link" href="auto-post.php">Auto Post</a>
      <a class="sidebar-link active" href="ebooks.php">Ebooks</a>
      <a class="sidebar-link" href="change-password.php">Change Password</a>
      <a class="sidebar-link" href="../index.html" target="_blank">View Site →</a>
    </nav>
    <div class="sidebar-bottom">
      <button class="theme-toggle" id="theme-toggle">◑ Light mode</button>
      <a class="sidebar-logout" href="logout.php">Sign out</a>
    </div>
  </aside>

  <main class="main">

    <div class="top-bar">
      <div class="top-bar-left">
        <?php if ($product): ?>
          <a class="back-link" href="ebooks.php">← All Ebooks</a>
        <?php endif; ?>
        <h1><?= $product ? htmlspecialchars($product['title']) . ' — Purchases' : 'All Purchases' ?></h1>
      </div>
      <div class="revenue-stat">
        <strong>IDR <?= number_format((int)$stats['total_revenue'], 0, ',', '.') ?></strong>
        <span class="revenue-count">from <?= (int)$stats['total_count'] ?> purchase<?= $stats['total_count'] != 1 ? 's' : '' ?></span>
      </div>
    </div>

    <!-- ── Search ── -->
    <form class="search-bar" method="GET" action="">
      <?php if ($product_id): ?>
        <input type="hidden" name="product_id" value="<?= $product_id ?>"/>
      <?php endif; ?>
      <input class="search-input" type="text" name="q"
             value="<?= htmlspecialchars($q) ?>"
             placeholder="Search by email…"/>
      <button class="btn-search" type="submit">Search</button>
      <?php if ($q !== ''): ?>
        <a class="btn-clear"
           href="ebook-purchases.php<?= $product_id ? '?product_id=' . $product_id : '' ?>">Clear</a>
      <?php endif; ?>
    </form>

    <!-- ── Table ── -->
    <?php if (empty($purchases)): ?>
      <div class="empty">
        <?= $q !== '' ? 'No purchases match "' . htmlspecialchars($q) . '".' : 'No purchases yet.' ?>
      </div>
    <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>Email</th>
          <?php if (!$product_id): ?><th>Product</th><?php endif; ?>
          <th>Paid At</th>
          <th>Token</th>
          <th>Last Read</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($purchases as $pu): ?>
        <tr>
          <td class="email-cell"><?= htmlspecialchars($pu['email']) ?></td>
          <?php if (!$product_id): ?>
            <td class="product-cell"><?= htmlspecialchars($pu['product_title']) ?></td>
          <?php endif; ?>
          <td class="date-cell"><?= date('d M Y, H:i', strtotime($pu['paid_at'])) ?></td>
          <td class="token-cell"><?= htmlspecialchars(substr($pu['token'], 0, 8)) ?>…</td>
          <td class="chapter-cell">
            <?= $pu['last_read_chapter'] !== null ? 'Ch. ' . (int)$pu['last_read_chapter'] : '—' ?>
          </td>
          <td style="display:flex;align-items:center;gap:16px;flex-wrap:wrap">
            <button class="btn-resend"
                    data-email="<?= htmlspecialchars($pu['email']) ?>"
                    data-product="<?= (int)$pu['product_id'] ?>">
              Resend Link
            </button>
            <form method="POST" action=""
                  onsubmit="return confirm('Remove purchase for <?= htmlspecialchars(addslashes($pu['email'])) ?>? This revokes their access.')">
              <input type="hidden" name="csrf"        value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"/>
              <input type="hidden" name="action"      value="delete"/>
              <input type="hidden" name="purchase_id" value="<?= (int)$pu['id'] ?>"/>
              <?php if ($product_id): ?>
                <input type="hidden" name="product_id" value="<?= $product_id ?>"/>
              <?php endif; ?>
              <?php if ($q !== ''): ?>
                <input type="hidden" name="q" value="<?= htmlspecialchars($q) ?>"/>
              <?php endif; ?>
              <button type="submit" class="btn-delete">Remove</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>

  </main>

  <!-- Toast notification -->
  <div id="toast"></div>

  <script>
    var CSRF_TOKEN = <?= json_encode($_SESSION['csrf_token']) ?>;

    /* ── Resend magic link ── */
    document.querySelectorAll('.btn-resend').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var email     = btn.dataset.email;
        var productId = btn.dataset.product;
        btn.disabled    = true;
        btn.textContent = 'Sending…';

        fetch('/api/ebook-recover.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ email: email, product_id: productId, csrf: CSRF_TOKEN }),
        })
        .then(function (r) { return r.json(); })
        .then(function (json) {
          if (json.success) {
            showToast('Link sent to ' + email, 'ok');
            btn.textContent = 'Sent ✓';
          } else {
            showToast(json.message || 'Failed to send link.', 'err');
            btn.disabled    = false;
            btn.textContent = 'Resend Link';
          }
        })
        .catch(function () {
          showToast('Network error — try again.', 'err');
          btn.disabled    = false;
          btn.textContent = 'Resend Link';
        });
      });
    });

    /* ── Toast ── */
    var toastTimer;
    function showToast(msg, type) {
      var el = document.getElementById('toast');
      el.textContent = msg;
      el.className   = 'show ' + type;
      clearTimeout(toastTimer);
      toastTimer = setTimeout(function () { el.className = ''; }, 3500);
    }

    /* ── Theme toggle ── */
    (function () {
      var btn = document.getElementById('theme-toggle');
      function update() {
        var dark = document.documentElement.getAttribute('data-theme') === 'dark';
        btn.textContent = dark ? '◑ Light mode' : '◐ Dark mode';
      }
      update();
      btn.addEventListener('click', function () {
        var next = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', next);
        localStorage.setItem('admin-theme', next);
        update();
      });
    }());
  </script>
</body>
</html>
