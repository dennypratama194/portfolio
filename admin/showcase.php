<?php
require __DIR__ . '/showcase-bootstrap.php';
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $id = showcaseInt($_POST, 'id', 1);
        $action = showcaseText($_POST, 'action', 20);
        $pdo->beginTransaction();
        $item = showcaseQuery($pdo, 'SELECT * FROM showcase_projects WHERE id = ? FOR UPDATE', [$id])->fetch();
        if (!$item) throw new RuntimeException('This showcase item no longer exists.');
        $removed = [];
        if ($action === 'delete') {
            $removed = showcaseQuery($pdo, 'SELECT media FROM showcase_images WHERE showcase_id = ?', [$id])->fetchAll(PDO::FETCH_COLUMN);
            $removed[] = $item['thumbnail'];
            showcaseQuery($pdo, 'DELETE FROM showcase_projects WHERE id = ?', [$id]);
        } elseif ($action === 'publish' || $action === 'unpublish') {
            if ($action === 'publish') {
                if (!showcaseMedia($item['thumbnail']) || trim($item['thumbnail_alt']) === '') throw new RuntimeException('Add a thumbnail and its alt text in the editor before publishing.');
                $missing = showcaseQuery($pdo, "SELECT COUNT(*) FROM showcase_images WHERE showcase_id = ? AND TRIM(alt_text) = ''", [$id])->fetchColumn();
                if ($missing) throw new RuntimeException('Add alt text to every gallery image before publishing.');
            }
            showcaseQuery($pdo, 'UPDATE showcase_projects SET is_published = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?', [$action === 'publish' ? 1 : 0, $id]);
        } else { throw new RuntimeException('Unknown action.'); }
        $pdo->commit();
        foreach ($removed as $media) showcaseDeleteMedia($media);
        header('Location: /admin/showcase?saved=1'); exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Showcase list: ' . $e->getMessage());
        $errors[] = $e instanceof RuntimeException && !($e instanceof PDOException) ? $e->getMessage() : 'Could not update Showcase. Please try again.';
    }
}
$page = max(1, (int)($_GET['page'] ?? 1));
try {
    $total = (int)showcaseQuery($pdo, 'SELECT COUNT(*) FROM showcase_projects')->fetchColumn();
    $pages = max(1, (int)ceil($total / 24));
    $page = min($page, $pages);
    $items = showcaseQuery($pdo, 'SELECT s.id,s.title,s.slug,s.thumbnail,s.thumbnail_alt,s.is_published,s.is_featured,s.sort_order,s.updated_at,c.name AS category_name
        FROM showcase_projects s LEFT JOIN showcase_categories c ON c.id=s.category_id ORDER BY s.sort_order,s.id ASC LIMIT 24 OFFSET ?', [($page - 1) * 24])->fetchAll();
} catch (PDOException $e) {
    error_log('Showcase list: ' . $e->getMessage());
    $items = []; $pages = 1;
    $errors[] = 'Showcase is not available yet. Apply the Showcase database migration before using this section.';
}
$admin_title = 'Showcase';
?>
<!DOCTYPE html><html lang="en"><head><?php include __DIR__ . '/partials/showcase-head.php'; ?></head><body>
<?php include __DIR__ . '/partials/sidebar.php'; ?>
<main class="main sc-admin">
  <div class="top-bar"><h1>Showcase</h1><div class="btn-row"><a class="btn-outline" href="/admin/showcase-categories">Categories</a><a class="btn-new" href="/admin/showcase-edit">+ New design</a></div></div>
  <p class="sc-admin-intro">Your visual archive. Publish individual designs and link them to a full case study when there is more to tell.</p>
  <?php if ($errors): ?><ul class="errors" role="alert"><?php foreach ($errors as $error): ?><li><?= escHtml($error) ?></li><?php endforeach; ?></ul><?php endif; ?>
  <?php if (isset($_GET['saved'])): ?><p role="status">Showcase updated.</p><?php endif; ?>
  <?php if (!$items): ?><p class="empty">No designs yet. Start with your first showcase item.</p><?php else: ?>
  <table><thead><tr><th>Design</th><th>Category</th><th>Status</th><th>Featured</th><th>Order</th><th>Updated</th><th>Actions</th></tr></thead><tbody>
  <?php foreach ($items as $item): ?>
    <tr><td><div class="sc-admin-design"><div class="sc-admin-thumb"><?= showcaseImage($item['thumbnail'], $item['thumbnail_alt']) ?></div><a href="/admin/showcase-edit?id=<?= (int)$item['id'] ?>"><?= escHtml($item['title']) ?></a></div></td>
      <td><?= escHtml($item['category_name'] ?? 'Uncategorized') ?></td><td><span class="badge"><?= $item['is_published'] ? 'Published' : 'Draft' ?></span></td><td><?= $item['is_featured'] ? 'Yes' : '—' ?></td><td><?= (int)$item['sort_order'] ?></td><td><?= escHtml(formatDate($item['updated_at'])) ?></td>
      <td><div class="sc-admin-actions"><a class="action-link" href="/admin/showcase-edit?id=<?= (int)$item['id'] ?>">Edit</a>
      <?php if ($item['is_published']): ?><a class="action-link" href="/showcase/<?= rawurlencode($item['slug']) ?>">View</a><?php endif; ?>
      <form method="post"><input type="hidden" name="csrf" value="<?= escHtml($_SESSION['csrf_token']) ?>"><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><button class="btn-outline" name="action" value="<?= $item['is_published'] ? 'unpublish' : 'publish' ?>"><?= $item['is_published'] ? 'Unpublish' : 'Publish' ?></button></form>
      <form method="post" onsubmit="return confirm('Delete this design and its images permanently?')"><input type="hidden" name="csrf" value="<?= escHtml($_SESSION['csrf_token']) ?>"><input type="hidden" name="id" value="<?= (int)$item['id'] ?>"><button class="btn-outline" name="action" value="delete">Delete</button></form></div></td></tr>
  <?php endforeach; ?></tbody></table>
  <nav class="sc-admin-actions" aria-label="Showcase pages"><?php if ($page > 1): ?><a href="?page=<?= $page - 1 ?>">← Previous</a><?php endif; ?><span>Page <?= $page ?> of <?= $pages ?></span><?php if ($page < $pages): ?><a href="?page=<?= $page + 1 ?>">Next →</a><?php endif; ?></nav>
  <?php endif; ?>
</main><script src="/admin/admin.js"></script></body></html>
