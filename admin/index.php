<?php
session_start();
if (!isset($_SESSION['authed'])) { header('Location: login.php'); exit; }
require __DIR__ . '/../api/db.php';

/* ── Delete action ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
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
    header('Location: index.php');
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
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet"/>
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
    .top-bar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 40px; }
    h1 { font-size: 22px; font-weight: 600; letter-spacing: -0.02em; }
    .btn-new {
      background: #E8320A; color: #ECEAE2; border: none;
      font-family: 'Inter', sans-serif; font-size: 12px; font-weight: 600;
      letter-spacing: 0.08em; text-transform: uppercase;
      padding: 10px 20px; text-decoration: none; cursor: pointer;
      transition: opacity 0.2s;
    }
    .btn-new:hover { opacity: 0.85; }

    /* ── Table ── */
    table { width: 100%; border-collapse: collapse; }
    th {
      text-align: left; font-size: 11px; letter-spacing: 0.1em; text-transform: uppercase;
      color: rgba(236,234,226,0.3); font-weight: 500;
      padding: 0 16px 16px 0; border-bottom: 1px solid rgba(236,234,226,0.07);
    }
    td {
      padding: 18px 16px 18px 0;
      border-bottom: 1px solid rgba(236,234,226,0.05);
      font-size: 14px; color: rgba(236,234,226,0.8); vertical-align: middle;
    }
    tr:hover td { background: rgba(236,234,226,0.02); }
    .post-title { color: #ECEAE2; font-weight: 500; }
    .post-slug { font-size: 12px; color: rgba(236,234,226,0.3); margin-top: 3px; }
    .badge {
      display: inline-block; font-size: 10px; letter-spacing: 0.1em;
      text-transform: uppercase; padding: 3px 8px;
    }
    .badge-pub      { background: rgba(232,50,10,0.15); color: #E8320A; }
    .badge-draft    { background: rgba(236,234,226,0.07); color: rgba(236,234,226,0.4); }
    .badge-scheduled { background: rgba(255,180,0,0.12); color: #f5a623; }
    .action-link {
      font-size: 12px; letter-spacing: 0.06em; text-transform: uppercase;
      color: rgba(236,234,226,0.35); text-decoration: none; margin-right: 16px;
      transition: color 0.2s;
    }
    .action-link:hover { color: #ECEAE2; }
    .action-delete { color: rgba(232,50,10,0.5); }
    .action-delete:hover { color: #E8320A; }
    .empty { padding: 64px 0; text-align: center; color: rgba(236,234,226,0.2); font-size: 14px; }
  </style>
</head>
<body>

  <aside class="sidebar">
    <div class="sidebar-logo">DP — Admin</div>
    <nav class="sidebar-nav">
      <a class="sidebar-link active" href="index.php">Posts</a>
      <a class="sidebar-link" href="edit.php">New Post</a>
      <a class="sidebar-link" href="change-password.php">Change Password</a>
      <a class="sidebar-link" href="analytics.php">Analytics</a>
      <a class="sidebar-link" href="../index.html" target="_blank">View Site →</a>
    </nav>
    <div class="sidebar-bottom">
      <a class="sidebar-logout" href="logout.php">Sign out</a>
    </div>
  </aside>

  <main class="main">
    <div class="top-bar">
      <h1>Posts</h1>
      <a class="btn-new" href="edit.php">+ New Post</a>
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
        <tr>
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
            <a class="action-link" href="edit.php?id=<?= $p['id'] ?>">Edit</a>
            <form method="POST" action="index.php" style="display:inline"
                  onsubmit="return confirm('Delete this post?')">
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

</body>
</html>
