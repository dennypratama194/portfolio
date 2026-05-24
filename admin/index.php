<?php
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_secure', '1');
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
        /* remove image file if exists */
        $row = $pdo->prepare('SELECT featured_image FROM posts WHERE id = ?');
        $row->execute([$id]);
        $img = $row->fetchColumn();
        if ($img && file_exists(__DIR__ . '/uploads/' . $img)) {
            unlink(__DIR__ . '/uploads/' . $img);
        }
        $pdo->prepare('DELETE FROM posts WHERE id = ?')->execute([$id]);
    }
    header('Location: /admin/index');
    exit;
}

$posts = $pdo->query(
    'SELECT id, title, slug, is_published, published_at, scheduled_at, created_at FROM posts ORDER BY created_at DESC'
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Admin — Posts</title>
  <meta name="robots" content="noindex, nofollow"/>
  <script>(function(){var t=localStorage.getItem('admin-theme')||'dark';document.documentElement.setAttribute('data-theme',t);})();</script>
  <link rel="icon" type="image/png" href="/assets/logo.png"/>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700&family=Geist+Mono:wght@400;500;600&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="theme.css"/>
  <style>
    td { padding: 18px 16px 18px 0; }
    .empty { padding: 64px 0; }
  </style>
</head>
<body>

  <?php include __DIR__ . '/partials/sidebar.php'; ?>

  <main class="main">
    <div class="top-bar">
      <h1>Posts</h1>
      <div class="top-bar-actions">
        <a class="btn-outline" href="auto-post.php">⚡ Auto Post</a>
        <a class="btn-new" href="edit.php">+ New Post</a>
      </div>
    </div>

    <?php if (empty($posts)): ?>
      <div class="empty">No posts yet. <a href="edit.php" style="color:#E8320A;text-decoration:none;">Write your first one →</a></div>
    <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>Title</th>
          <th>Status</th>
          <th>Date</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($posts as $p): ?>
        <tr class="row-link" data-href="edit.php?id=<?= $p['id'] ?>">
          <td>
            <div class="post-title"><?= htmlspecialchars($p['title']) ?></div>
            <div class="post-slug">/<?= htmlspecialchars($p['slug']) ?></div>
          </td>
          <td>
            <?php if ($p['is_published']): ?>
              <span class="badge badge-pub">Published</span>
            <?php elseif (!empty($p['scheduled_at'])): ?>
              <span class="badge badge-scheduled">Scheduled</span>
            <?php else: ?>
              <span class="badge badge-draft">Draft</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($p['is_published'] && $p['published_at']): ?>
              <?= date('d M Y', strtotime($p['published_at'])) ?>
            <?php elseif (!empty($p['scheduled_at'])): ?>
              <?= date('d M Y, H:i', strtotime($p['scheduled_at'])) ?>
            <?php else: ?>
              —
            <?php endif; ?>
          </td>
          <td>
            <form method="POST" action="" style="display:inline"
                  onsubmit="return confirm('Delete this post?')">
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

  <script>
    /* Whole post row is clickable → edit; action buttons (Delete) opt out */
    document.querySelectorAll('tr.row-link').forEach(function (row) {
      row.addEventListener('click', function (e) {
        if (e.target.closest('a, button, form, input')) return;
        window.location.href = row.dataset.href;
      });
    });
  </script>
  <script src="admin.js"></script>
</body>
</html>
