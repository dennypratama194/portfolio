<?php
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
session_start();
if (!isset($_SESSION['authed'])) { header('Location: /admin/login'); exit; }
require __DIR__ . '/../api/db.php';

$_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));

/* ── Delete action ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (($_POST['csrf'] ?? '') !== $_SESSION['csrf_token']) {
        http_response_code(403); exit('Forbidden.');
    }
    $id = (int)($_POST['id'] ?? 0);
    if ($id) {
        $pdo->prepare('DELETE FROM ebook_products WHERE id = ?')->execute([$id]);
    }
    header('Location: /admin/ebooks');
    exit;
}

/* ── Fetch products with purchase count ── */
$products = $pdo->query(
    'SELECT p.*,
            (SELECT COUNT(*) FROM ebook_purchases pu WHERE pu.product_id = p.id) AS purchase_count
     FROM ebook_products p
     ORDER BY p.created_at DESC'
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Admin — Ebooks</title>
  <script>(function(){var t=localStorage.getItem('admin-theme')||'dark';document.documentElement.setAttribute('data-theme',t);})();</script>
  <link rel="icon" type="image/png" href="/assets/logo.png"/>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="theme.css"/>
  <style>
    td { padding: 18px 16px 18px 0; }
    .empty { padding: 64px 0; }
    .price { font-variant-numeric: tabular-nums; }
  </style>
</head>
<body>

  <div class="mobile-topbar">
    <div class="mobile-topbar-logo"><img src="/assets/logo.png" alt="Denny Pratama"/></div>
    <button class="mobile-burger" id="mobile-burger" aria-label="Menu"><span></span><span></span><span></span></button>
  </div>
  <div class="sidebar-overlay" id="sidebar-overlay"></div>

  <aside class="sidebar" id="sidebar">
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
      <h1>Ebooks</h1>
      <a class="btn-new" href="ebook-edit.php">+ New Product</a>
    </div>

    <?php if (empty($products)): ?>
      <div class="empty">No ebook products yet. <a href="ebook-edit.php" style="color:#E8320A;text-decoration:none;">Create your first one →</a></div>
    <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>Title</th>
          <th>Price</th>
          <th>Status</th>
          <th>Purchases</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($products as $p): ?>
        <tr>
          <td>
            <div class="product-title"><?= htmlspecialchars($p['title']) ?></div>
            <div class="product-slug">/<?= htmlspecialchars($p['slug']) ?></div>
          </td>
          <td class="price">IDR <?= number_format((int)$p['price'], 0, ',', '.') ?></td>
          <td>
            <?php if ($p['is_active']): ?>
              <span class="badge badge-active">Active</span>
            <?php else: ?>
              <span class="badge badge-draft">Inactive</span>
            <?php endif; ?>
          </td>
          <td><?= (int)$p['purchase_count'] ?></td>
          <td>
            <a class="action-link" href="ebook-edit.php?id=<?= $p['id'] ?>">Edit</a>
            <a class="action-link" href="ebook-chapters.php?product_id=<?= $p['id'] ?>">Chapters</a>
            <a class="action-link" href="ebook-purchases.php?product_id=<?= $p['id'] ?>">Purchases</a>
            <a class="action-link" href="/ebook/<?= rawurlencode($p['slug']) ?>" target="_blank">View →</a>
            <form method="POST" action="" style="display:inline"
                  onsubmit="return confirm('Delete this product and all its chapters? This cannot be undone.')">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"/>
              <input type="hidden" name="action" value="delete"/>
              <input type="hidden" name="id" value="<?= $p['id'] ?>"/>
              <button type="submit" class="action-link action-delete"
                      style="background:none;border:none;cursor:pointer;font-family:inherit;">Delete</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </main>

  <script src="admin.js"></script>
</body>
</html>
