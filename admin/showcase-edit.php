<?php
require __DIR__ . '/showcase-bootstrap.php';
$id = max(0, (int)($_GET['id'] ?? 0));
$item = ['title'=>'','slug'=>'','short_description'=>'','category_id'=>null,'thumbnail'=>null,'thumbnail_alt'=>'','year'=>date('Y'),'tools'=>'','client'=>'','related_case_study_id'=>null,'is_featured'=>0,'is_published'=>0,'sort_order'=>0];
$gallery = []; $errors = []; $categories = []; $cases = [];
try {
    if ($id) {
        $found = showcaseQuery($pdo, 'SELECT * FROM showcase_projects WHERE id=?', [$id])->fetch();
        if (!$found) { http_response_code(404); exit('Showcase item not found.'); }
        $item = $found;
        $gallery = showcaseQuery($pdo, 'SELECT * FROM showcase_images WHERE showcase_id=? ORDER BY sort_order,id', [$id])->fetchAll();
    }
    $categories = showcaseQuery($pdo, 'SELECT id,name FROM showcase_categories ORDER BY sort_order,name')->fetchAll();
    $cases = showcaseQuery($pdo, 'SELECT id,title,is_published FROM projects ORDER BY title')->fetchAll();
} catch (PDOException $e) { $errors[] = 'Showcase could not load. Check that the database migration is installed.'; }

/* ── Step 1: drop an image, get a draft with everything else left for Step 2 ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quick_create']) && !$id && !$errors) {
    $thumbnail = null;
    try {
        if (!isset($_FILES['thumbnail']) || $_FILES['thumbnail']['error'] === UPLOAD_ERR_NO_FILE) {
            throw new RuntimeException('Choose an image to get started.');
        }
        $thumbnail = showcaseUpload($_FILES['thumbnail']);
        $slug = showcaseUniqueSlug($pdo, 'untitled-design');
        $order = (int)showcaseQuery($pdo, 'SELECT COALESCE(MAX(sort_order),0)+1 FROM showcase_projects')->fetchColumn();
        showcaseQuery($pdo, 'INSERT INTO showcase_projects (title,slug,thumbnail,sort_order) VALUES (?,?,?,?)', ['Untitled design', $slug, $thumbnail, $order]);
        header('Location: /admin/showcase-edit?id=' . $pdo->lastInsertId() . '&draft=1'); exit;
    } catch (Throwable $e) {
        if ($thumbnail) showcaseDeleteMedia($thumbnail);
        error_log('Showcase quick create: ' . $e->getMessage());
        $errors[] = $e instanceof RuntimeException && !($e instanceof PDOException) ? $e->getMessage() : 'Could not upload this image. Please try again.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['quick_create']) && !$errors) {
    $created = []; $removed = [];
    try {
        foreach (['title'=>255,'short_description'=>2000,'thumbnail_alt'=>500,'tools'=>255,'client'=>100] as $field=>$max) $item[$field] = showcaseText($_POST, $field, $max);
        if ($item['title'] === '') throw new RuntimeException('Enter a title.');
        // Slug is derived from the title, not user-entered. It's generated once — on
        // creation, or the first time a real title replaces the "untitled-design"
        // placeholder — then left alone so a published URL never moves under you.
        if (!$id || preg_match('/^untitled-design(-\d+)?$/D', $item['slug'])) {
            $item['slug'] = showcaseUniqueSlug($pdo, showcaseSlugify($item['title']) ?: 'design', $id ?: null);
        }
        $item['year'] = ($_POST['year'] ?? '') === '' ? null : showcaseInt($_POST, 'year', 1900, 2155);
        $item['sort_order'] = showcaseInt($_POST, 'sort_order');
        $item['category_id'] = showcaseInt($_POST, 'category_id') ?: null;
        $item['related_case_study_id'] = showcaseInt($_POST, 'related_case_study_id') ?: null;
        $item['is_featured'] = isset($_POST['is_featured']) ? 1 : 0;
        $item['is_published'] = isset($_POST['is_published']) ? 1 : 0;
        foreach (['category_id'=>$categories,'related_case_study_id'=>$cases] as $field=>$choices) {
            if ($item[$field] !== null && !in_array($item[$field], array_map('intval', array_column($choices, 'id')), true)) throw new RuntimeException('The selected category or case study no longer exists.');
        }
        $edits = $_POST['gallery'] ?? [];
        if (!is_array($edits)) throw new RuntimeException('Invalid gallery. Reload and try again.');
        foreach ($gallery as &$image) {
            if (!isset($edits[$image['id']])) throw new RuntimeException('Some image fields were not received. Reload the editor and try again.');
            $edit = $edits[$image['id']];
            if (!is_array($edit)) throw new RuntimeException('Invalid image details.');
            $image['alt_text'] = showcaseText($edit, 'alt_text', 500);
            $image['caption'] = showcaseText($edit, 'caption', 1000);
            $image['sort_order'] = showcaseInt($edit, 'sort_order');
            $image['remove'] = isset($edit['remove']);
            if (!$image['remove'] && $item['is_published'] && $image['alt_text'] === '') throw new RuntimeException('Add alt text to every gallery image before publishing.');
        }
        unset($image);
        if (!empty($_POST['remove_thumbnail'])) { $removed[] = $item['thumbnail']; $item['thumbnail'] = null; }
        if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] !== UPLOAD_ERR_NO_FILE) {
            $new = showcaseUpload($_FILES['thumbnail']); $created[] = $new;
            $removed[] = $item['thumbnail']; $item['thumbnail'] = $new;
        }
        if ($item['is_published'] && (!showcaseMedia($item['thumbnail']) || $item['thumbnail_alt'] === '')) throw new RuntimeException('A thumbnail and its alt text are required before publishing.');
        $newImages = [];
        $files = $_FILES['images'] ?? null;
        if ($files && is_array($files['name'])) {
            if (count($files['name']) > 12) throw new RuntimeException('Upload up to 12 images at a time.');
            foreach ($files['name'] as $i=>$name) {
                if ($files['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
                $file = ['name'=>$name,'tmp_name'=>$files['tmp_name'][$i],'size'=>$files['size'][$i],'error'=>$files['error'][$i]];
                $new = showcaseUpload($file); $created[] = $new;
                $newImages[] = $new;
            }
        }
        // New images start as draft until the author supplies meaningful alt text.
        if ($newImages) $item['is_published'] = 0;
        $pdo->beginTransaction();
        if ($id && !showcaseQuery($pdo, 'SELECT id FROM showcase_projects WHERE id=? FOR UPDATE', [$id])->fetch()) throw new RuntimeException('This design was deleted in another session.');
        $fields = ['title','slug','short_description','category_id','thumbnail','thumbnail_alt','year','tools','client','related_case_study_id','is_featured','is_published','sort_order'];
        $values = array_map(function ($field) use ($item) { return $item[$field]; }, $fields);
        if ($id) {
            $values[] = $id;
            showcaseQuery($pdo, 'UPDATE showcase_projects SET ' . implode(',', array_map(function ($field) { return $field . '=?'; }, $fields)) . ',updated_at=CURRENT_TIMESTAMP WHERE id=?', $values);
        } else {
            showcaseQuery($pdo, 'INSERT INTO showcase_projects (' . implode(',', $fields) . ') VALUES (' . implode(',', array_fill(0,count($fields),'?')) . ')', $values);
            $id = (int)$pdo->lastInsertId();
        }
        foreach ($gallery as $image) {
            if ($image['remove']) {
                showcaseQuery($pdo, 'DELETE FROM showcase_images WHERE id=? AND showcase_id=?', [(int)$image['id'],$id]);
                $removed[] = $image['media'];
            } else showcaseQuery($pdo, 'UPDATE showcase_images SET alt_text=?,caption=?,sort_order=? WHERE id=? AND showcase_id=?', [$image['alt_text'],$image['caption'],$image['sort_order'],(int)$image['id'],$id]);
        }
        $order = (int)showcaseQuery($pdo, 'SELECT COALESCE(MAX(sort_order),0) FROM showcase_images WHERE showcase_id=?', [$id])->fetchColumn();
        foreach ($newImages as $media) showcaseQuery($pdo, 'INSERT INTO showcase_images (showcase_id,media,sort_order) VALUES (?,?,?)', [$id,$media,++$order]);
        $pdo->commit();
        foreach ($removed as $media) showcaseDeleteMedia($media);
        header('Location: /admin/showcase-edit?id=' . $id . '&saved=1' . ($newImages ? '&images=1' : '')); exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        foreach ($created as $media) showcaseDeleteMedia($media);
        if ($created || $removed) $item['thumbnail'] = $found['thumbnail'] ?? null;
        error_log('Showcase editor: ' . $e->getMessage());
        $errors[] = $e instanceof RuntimeException && !($e instanceof PDOException) ? $e->getMessage() : 'Could not save this design. Check the slug is unique and try again.';
    }
}
$admin_title = $id ? 'Edit showcase' : 'New showcase';
?>
<!DOCTYPE html><html lang="en"><head><?php include __DIR__ . '/partials/showcase-head.php'; ?></head><body>
<?php include __DIR__ . '/partials/sidebar.php'; ?>
<main class="main sc-admin"><a class="back-link" href="/admin/showcase">← Showcase</a>

<?php if (!$id): ?>

  <div class="sc-quickstart">
    <h1>What have you been working on?</h1>
    <p class="sc-admin-intro">Drop in an image to start a new design. Add the title and details on the next step.</p>
    <?php if ($errors): ?><ul class="errors" role="alert"><?php foreach ($errors as $error): ?><li><?= escHtml($error) ?></li><?php endforeach; ?></ul><?php endif; ?>
    <form method="post" enctype="multipart/form-data" id="quickstart-form">
      <input type="hidden" name="csrf" value="<?= escHtml($_SESSION['csrf_token']) ?>">
      <input type="hidden" name="quick_create" value="1">
      <label class="sc-dropzone" id="dropzone" for="quickstart-file">
        <input class="sc-dropzone-input" type="file" id="quickstart-file" name="thumbnail" accept="image/jpeg,image/png,image/webp">
        <svg class="sc-dropzone-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="1.5"/><path d="m3 16 5-5 4 4 4-6 5 7"/></svg>
        <span class="sc-dropzone-label">Drag and drop an image, or <span class="sc-dropzone-browse">browse</span></span>
        <span class="sc-admin-hint">JPG, PNG or WebP, up to 5 MB.</span>
        <span class="sc-dropzone-status" id="dropzone-status" role="status"></span>
      </label>
      <noscript><div class="btn-row"><button type="submit" class="btn-save">Upload</button></div></noscript>
    </form>
  </div>
  <script>
  (function () {
    var form = document.getElementById('quickstart-form'), zone = document.getElementById('dropzone'),
        input = document.getElementById('quickstart-file'), status = document.getElementById('dropzone-status'),
        allowed = ['image/jpeg', 'image/png', 'image/webp'], submitting = false;
    function go(files) {
      if (submitting || !files.length) return;
      if (allowed.indexOf(files[0].type) === -1) { status.textContent = 'Use a JPG, PNG or WebP image.'; status.dataset.error = 'true'; return; }
      submitting = true;
      zone.classList.add('is-loading');
      status.dataset.error = 'false';
      status.textContent = 'Uploading…';
      input.files = files;
      form.submit();
    }
    input.addEventListener('change', function () { go(input.files); });
    ['dragenter', 'dragover'].forEach(function (evt) { zone.addEventListener(evt, function (e) { e.preventDefault(); zone.classList.add('is-dragover'); }); });
    ['dragleave', 'drop'].forEach(function (evt) { zone.addEventListener(evt, function (e) { e.preventDefault(); zone.classList.remove('is-dragover'); }); });
    zone.addEventListener('drop', function (e) { go(e.dataTransfer.files); });
  })();
  </script>

<?php else: ?>

<div class="top-bar"><h1>Edit design</h1><?php if ($item['is_published']): ?><a class="btn-outline" href="/showcase/<?= rawurlencode($item['slug']) ?>">View design →</a><?php endif; ?></div>
<?php if ($errors): ?><ul class="errors" role="alert"><?php foreach ($errors as $error): ?><li><?= escHtml($error) ?></li><?php endforeach; ?></ul><?php endif; ?>
<?php if (isset($_GET['draft'])): ?><p role="status">Image uploaded. Add a title and a few details, then publish when ready.</p>
<?php elseif (isset($_GET['saved'])): ?><p role="status"><?= isset($_GET['images']) ? 'Images uploaded. Add their alt text below, then publish when ready.' : 'Design saved.' ?></p><?php endif; ?>
<form method="post" enctype="multipart/form-data" class="sc-admin-form"><input type="hidden" name="csrf" value="<?= escHtml($_SESSION['csrf_token']) ?>">
<div class="field"><label for="title">Title</label><input type="text" id="title" name="title" maxlength="255" value="<?= escHtml($item['title']) ?>" required autofocus></div>
<div class="field"><label for="short_description">Short description</label><textarea id="short_description" name="short_description" maxlength="2000"><?= escHtml($item['short_description'] ?? '') ?></textarea></div>
<fieldset class="sc-admin-section"><legend>Cover image</legend>
<div class="sc-cover">
  <input class="sc-dropzone-input" type="file" id="thumbnail" name="thumbnail" accept="image/jpeg,image/png,image/webp">
  <label class="sc-cover-preview" for="thumbnail">
    <?php if ($item['thumbnail']): ?><?= showcaseImage($item['thumbnail'], $item['thumbnail_alt']) ?><?php else: ?><span class="sc-cover-empty">Click to upload a cover</span><?php endif; ?>
    <span class="sc-cover-hint" aria-hidden="true">Click to replace</span>
  </label>
  <?php if ($item['thumbnail']): ?>
  <input class="sc-dropzone-input" type="checkbox" id="remove_thumbnail" name="remove_thumbnail" value="1">
  <label class="sc-cover-remove" for="remove_thumbnail" aria-label="Mark cover for removal">✕</label>
  <?php endif; ?>
</div>
<p class="sc-admin-hint" id="cover-status" role="status">JPG, PNG or WebP, up to 5 MB and 16 megapixels. Cards use a centered 4:3 crop; the detail page keeps the full image.</p>
<div class="field"><label for="thumbnail_alt">Cover alt text</label><input type="text" id="thumbnail_alt" name="thumbnail_alt" maxlength="500" value="<?= escHtml($item['thumbnail_alt']) ?>"><p class="sc-admin-hint">Describe what is visible in the design. Required before publishing.</p></div></fieldset>
<script>
(function () {
  var file = document.getElementById('thumbnail'), status = document.getElementById('cover-status'), original = status.textContent;
  file.addEventListener('change', function () {
    status.textContent = file.files.length ? 'New cover selected — click Save design below to apply it.' : original;
  });
})();
</script>

<details class="sc-admin-more"><summary>More details</summary>
<div class="sc-admin-fields">
<div class="field"><label for="category_id">Category (optional)</label><select id="category_id" name="category_id"><option value="0">Uncategorized</option><?php foreach ($categories as $category): ?><option value="<?= (int)$category['id'] ?>" <?= (int)$item['category_id'] === (int)$category['id'] ? 'selected' : '' ?>><?= escHtml($category['name']) ?></option><?php endforeach; ?></select><a href="/admin/showcase-categories" target="_blank" rel="noopener">Manage categories</a></div>
<div class="field"><label for="year">Year (optional)</label><input type="number" id="year" name="year" min="1900" max="2155" value="<?= escHtml((string)$item['year']) ?>"></div>
<div class="field"><label for="tools">Tools (optional)</label><input type="text" id="tools" name="tools" maxlength="255" value="<?= escHtml($item['tools']) ?>"></div>
<div class="field"><label for="client">Client (optional)</label><input type="text" id="client" name="client" maxlength="100" value="<?= escHtml($item['client']) ?>"></div>
<div class="field"><label for="related_case_study_id">Related case study (optional)</label><select id="related_case_study_id" name="related_case_study_id"><option value="0">None</option><?php foreach ($cases as $case): ?><option value="<?= (int)$case['id'] ?>" <?= (int)$item['related_case_study_id'] === (int)$case['id'] ? 'selected' : '' ?>><?= escHtml($case['title'] . ($case['is_published'] ? '' : ' (draft)')) ?></option><?php endforeach; ?></select></div>
<div class="field"><label for="sort_order">Display order (optional)</label><input type="number" id="sort_order" name="sort_order" min="0" max="2147483647" value="<?= (int)$item['sort_order'] ?>"><p class="sc-admin-hint">Lower numbers appear first.</p></div>
</div>
</details>

<details class="sc-admin-more" <?= $gallery ? 'open' : '' ?>><summary>Gallery<?= $gallery ? ' (' . count($gallery) . ')' : '' ?></summary>
<fieldset class="sc-admin-section"><legend class="sr-only">Gallery</legend><p class="sc-admin-hint">Set image order, descriptions and captions. Check Remove and save to delete an image.</p>
<?php foreach ($gallery as $image): $iid = (int)$image['id']; ?>
<div class="sc-admin-gallery-row"><div class="sc-admin-preview"><?= showcaseImage($image['media'], $image['alt_text']) ?></div><div>
<div class="field"><label for="alt-<?= $iid ?>">Alt text</label><input type="text" id="alt-<?= $iid ?>" name="gallery[<?= $iid ?>][alt_text]" maxlength="500" value="<?= escHtml($image['alt_text']) ?>"></div>
<div class="field"><label for="caption-<?= $iid ?>">Caption (optional)</label><textarea id="caption-<?= $iid ?>" name="gallery[<?= $iid ?>][caption]" maxlength="1000"><?= escHtml($image['caption']) ?></textarea></div>
<div class="field"><label for="order-<?= $iid ?>">Image order</label><input type="number" id="order-<?= $iid ?>" name="gallery[<?= $iid ?>][sort_order]" min="0" max="2147483647" value="<?= (int)$image['sort_order'] ?>"></div>
<label class="sc-admin-check"><input type="checkbox" name="gallery[<?= $iid ?>][remove]" value="1" <?= !empty($image['remove']) ? 'checked' : '' ?>>Remove image</label>
</div></div><?php endforeach; ?>
<div class="field"><label for="images">Add gallery images</label><input type="file" id="images" name="images[]" accept="image/jpeg,image/png,image/webp" multiple><p class="sc-admin-hint">Up to 12 images per upload, 5 MB each. Adding images saves the design as a draft so you can describe them before publishing.</p></div></fieldset>
</details>

<div class="sc-admin-actions"><label class="sc-admin-check"><input type="checkbox" name="is_featured" value="1" <?= $item['is_featured'] ? 'checked' : '' ?>>Featured on homepage</label><label class="sc-admin-check"><input type="checkbox" name="is_published" value="1" <?= $item['is_published'] ? 'checked' : '' ?>>Published</label></div>
<div class="btn-row"><button type="submit" class="btn-save"><?= $item['is_published'] ? 'Save design' : 'Save & publish when ready' ?></button><a class="btn-cancel" href="/admin/showcase">Back to archive</a>
<?php if (!$item['is_published']): ?><form method="post" action="/admin/showcase" onsubmit="return confirm('Discard this draft? Its image will be deleted.')" class="sc-admin-discard"><input type="hidden" name="csrf" value="<?= escHtml($_SESSION['csrf_token']) ?>"><input type="hidden" name="id" value="<?= $id ?>"><button class="btn-outline" name="action" value="delete">Discard draft</button></form><?php endif; ?>
</div>
</form>

<?php endif; ?>

</main><script src="/admin/admin.js"></script></body></html>
