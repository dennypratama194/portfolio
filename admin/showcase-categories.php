<?php
require __DIR__ . '/showcase-bootstrap.php';
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $id = showcaseInt($_POST, 'id');
        if (($_POST['action'] ?? '') === 'delete') {
            showcaseQuery($pdo, 'DELETE FROM showcase_categories WHERE id = ?', [$id]);
        } else {
            $name = showcaseText($_POST, 'name', 100);
            $slug = showcaseText($_POST, 'slug', 100);
            $order = showcaseInt($_POST, 'sort_order');
            if ($name === '' || !showcaseSlug($slug, 100)) throw new RuntimeException('Enter a name and a URL slug using lowercase letters, numbers and single hyphens.');
            if ($id) showcaseQuery($pdo, 'UPDATE showcase_categories SET name=?,slug=?,sort_order=? WHERE id=?', [$name,$slug,$order,$id]);
            else showcaseQuery($pdo, 'INSERT INTO showcase_categories (name,slug,sort_order) VALUES (?,?,?)', [$name,$slug,$order]);
        }
        header('Location: /admin/showcase-categories?saved=1'); exit;
    } catch (Throwable $e) {
        error_log('Showcase categories: ' . $e->getMessage());
        $errors[] = $e instanceof RuntimeException && !($e instanceof PDOException) ? $e->getMessage() : 'Could not save. Use a unique category slug and check that the migration is installed.';
    }
}
$categories = [];
try { $categories = showcaseQuery($pdo, 'SELECT * FROM showcase_categories ORDER BY sort_order,name')->fetchAll(); }
catch (PDOException $e) { $errors[] = 'Apply the Showcase migration before managing categories.'; }
$admin_title = 'Showcase categories';
?>
<!DOCTYPE html><html lang="en"><head><?php include __DIR__ . '/partials/showcase-head.php'; ?></head><body>
<?php include __DIR__ . '/partials/sidebar.php'; ?>
<main class="main sc-admin"><a class="back-link" href="/admin/showcase">← Showcase</a><h1>Categories</h1>
<p class="sc-admin-intro">Create the filters visitors use to browse your archive. Removing a category keeps its designs.</p>
<?php if ($errors): ?><ul class="errors" role="alert"><?php foreach ($errors as $error): ?><li><?= escHtml($error) ?></li><?php endforeach; ?></ul><?php endif; ?>
<?php if (isset($_GET['saved'])): ?><p role="status">Categories updated.</p><?php endif; ?>
<?php $categories[] = ['id'=>0,'name'=>'','slug'=>'','sort_order'=>0]; foreach ($categories as $category): $cid = (int)$category['id']; ?>
<form method="post" class="sc-admin-category"><input type="hidden" name="csrf" value="<?= escHtml($_SESSION['csrf_token']) ?>"><input type="hidden" name="id" value="<?= $cid ?>">
<div class="field"><label for="cat-name-<?= $cid ?>"><?= $cid ? 'Name' : 'New category' ?></label><input id="cat-name-<?= $cid ?>" type="text" name="name" maxlength="100" value="<?= escHtml($category['name']) ?>" required></div>
<div class="field"><label for="cat-slug-<?= $cid ?>">Slug</label><input id="cat-slug-<?= $cid ?>" type="text" name="slug" maxlength="100" pattern="[a-z0-9]+(-[a-z0-9]+)*" value="<?= escHtml($category['slug']) ?>" required></div>
<div class="field"><label for="cat-order-<?= $cid ?>">Order</label><input id="cat-order-<?= $cid ?>" type="number" name="sort_order" min="0" max="2147483647" value="<?= (int)$category['sort_order'] ?>"></div>
<button class="btn-save" name="action" value="save"><?= $cid ? 'Save' : 'Add category' ?></button>
<?php if ($cid): ?><button class="btn-outline" name="action" value="delete" formnovalidate onclick="return confirm('Remove this category? Its designs will stay in the archive.')">Delete</button><?php endif; ?>
</form><?php endforeach; ?></main><script src="/admin/admin.js"></script></body></html>
