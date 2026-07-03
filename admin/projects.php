<?php
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_secure', '1');
session_start();
if (!isset($_SESSION['authed'])) { header('Location: /admin/login'); exit; }
require __DIR__ . '/../api/db.php';

$_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));

/* ── Delete action ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (($_POST['csrf'] ?? '') !== $_SESSION['csrf_token']) {
        http_response_code(403); exit('Forbidden.');
    }
    $id = (int)($_POST['id'] ?? 0);
    if ($id) {
        $pdo->prepare('DELETE FROM projects WHERE id = ?')->execute([$id]);
    }
    header('Location: /admin/projects');
    exit;
}

$projects = $pdo->query(
    'SELECT id, title, slug, client, year, is_published, sort_order FROM projects ORDER BY sort_order ASC, created_at DESC'
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Admin — Case Studies</title>
  <meta name="robots" content="noindex, nofollow"/>
  <script>(function(){var t=localStorage.getItem('admin-theme')||'dark';document.documentElement.setAttribute('data-theme',t);})();</script>
  <link rel="icon" type="image/png" href="/assets/logo.png"/>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700&family=Geist+Mono:wght@400;500;600&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="theme.css?v=6"/>
  <style>
    td { padding: 16px 16px 16px 0; }
  </style>
</head>
<body>

<?php include __DIR__ . '/partials/sidebar.php'; ?>

<main class="main">
  <div class="top-bar">
    <h1>Case Studies</h1>
    <a class="btn-new" href="project-edit.php">+ New Project</a>
  </div>

  <?php if (empty($projects)): ?>
    <div class="empty">No case studies yet. <a href="project-edit.php" style="color:var(--red);text-decoration:none;">Create your first one &rarr;</a></div>
  <?php else: ?>
  <table>
    <thead>
      <tr>
        <th>Project</th>
        <th>Client</th>
        <th>Year</th>
        <th>Order</th>
        <th>Status</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($projects as $p): ?>
      <tr>
        <td>
          <div class="project-title"><?= htmlspecialchars($p['title']) ?></div>
          <div class="project-slug">/case-studies/<?= htmlspecialchars($p['slug']) ?></div>
        </td>
        <td><?= htmlspecialchars($p['client'] ?: '—') ?></td>
        <td><?= htmlspecialchars($p['year'] ?: '—') ?></td>
        <td><?= (int)$p['sort_order'] ?></td>
        <td>
          <span class="badge<?= $p['is_published'] ? ' badge-green' : '' ?>">
            <?= $p['is_published'] ? 'Published' : 'Draft' ?>
          </span>
        </td>
        <td>
          <a class="action-link" href="project-edit.php?id=<?= $p['id'] ?>">Edit</a>
          <?php if ($p['is_published']): ?>
          <a class="action-link" href="/case-studies/<?= rawurlencode($p['slug']) ?>" target="_blank" rel="noopener noreferrer">View</a>
          <?php endif; ?>
          <form method="POST" style="display:inline" onsubmit="return confirm('Delete this case study? This cannot be undone.')">
            <input type="hidden" name="action" value="delete"/>
            <input type="hidden" name="id"     value="<?= $p['id'] ?>"/>
            <input type="hidden" name="csrf"   value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"/>
            <button type="submit" class="action-link" style="background:none;border:none;cursor:pointer;color:var(--red);padding:0;font:inherit">Delete</button>
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
