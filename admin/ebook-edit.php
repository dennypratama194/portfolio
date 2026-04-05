<?php
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
session_start();
if (!isset($_SESSION['authed'])) { header('Location: /admin/login'); exit; }
require __DIR__ . '/../api/db.php';

$_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));

$id      = isset($_GET['id']) ? (int)$_GET['id'] : null;
$product = ['title'=>'','slug'=>'','price'=>'','description'=>'','tagline'=>'','cover_image'=>'','is_active'=>0];
$errors  = [];

/* ── Load existing product for edit ── */
if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM ebook_products WHERE id = ?');
    $stmt->execute([$id]);
    $found = $stmt->fetch();
    if ($found) $product = $found;
}

/* ── Handle form submit ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['csrf'] ?? '') !== $_SESSION['csrf_token']) {
        http_response_code(403); exit('Forbidden.');
    }

    $title       = trim($_POST['title']       ?? '');
    $slug        = trim($_POST['slug']        ?? '');
    $price       = (int)($_POST['price']      ?? 0);
    $description = trim($_POST['description'] ?? '');
    $tagline     = trim($_POST['tagline']     ?? '');
    $is_active   = isset($_POST['is_active']) ? 1 : 0;
    $keep_img    = $product['cover_image'];

    /* ── Validation ── */
    if (!$title)   $errors[] = 'Title is required.';
    if (!$slug)    $errors[] = 'Slug is required.';
    if ($price <= 0) $errors[] = 'Price must be a positive number.';

    /* Slug unique check */
    if ($slug) {
        $chk = $pdo->prepare('SELECT id FROM ebook_products WHERE slug = ? AND id != ?');
        $chk->execute([$slug, $id ?? 0]);
        if ($chk->fetch()) $errors[] = 'That slug is already in use by another product.';
    }

    /* ── Cover image upload ── */
    $new_img = $keep_img;

    /* Handle explicit remove */
    if (($_POST['remove_image'] ?? '0') === '1' && empty($_FILES['cover_image']['name'])) {
        if ($keep_img && file_exists(__DIR__ . '/uploads/' . $keep_img)) {
            unlink(__DIR__ . '/uploads/' . $keep_img);
        }
        $new_img = '';
    }

    if (!empty($_FILES['cover_image']['name'])) {
        $file    = $_FILES['cover_image'];
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        $mime    = mime_content_type($file['tmp_name']);

        if (!in_array($mime, $allowed)) {
            $errors[] = 'Cover image must be JPG, PNG, WebP or GIF.';
        } elseif ($file['size'] > 5 * 1024 * 1024) {
            $errors[] = 'Cover image must be under 5 MB.';
        } else {
            $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = uniqid('cover_', true) . '.' . strtolower($ext);
            $dest     = __DIR__ . '/uploads/' . $filename;

            if (!is_dir(__DIR__ . '/uploads')) {
                mkdir(__DIR__ . '/uploads', 0755, true);
            }

            if (move_uploaded_file($file['tmp_name'], $dest)) {
                /* Delete old cover if replacing */
                if ($keep_img && file_exists(__DIR__ . '/uploads/' . $keep_img)) {
                    unlink(__DIR__ . '/uploads/' . $keep_img);
                }
                $new_img = $filename;
            } else {
                $errors[] = 'Failed to save cover image.';
            }
        }
    }

    if (empty($errors)) {
        if ($id) {
            $stmt = $pdo->prepare(
                'UPDATE ebook_products SET title=?, slug=?, price=?, description=?, tagline=?, cover_image=?, is_active=? WHERE id=?'
            );
            $stmt->execute([$title, $slug, $price, $description, $tagline, $new_img, $is_active, $id]);
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO ebook_products (title, slug, price, description, tagline, cover_image, is_active) VALUES (?,?,?,?,?,?,?)'
            );
            $stmt->execute([$title, $slug, $price, $description, $tagline, $new_img, $is_active]);
        }
        header('Location: /admin/ebooks');
        exit;
    }

    /* Re-populate form values on error */
    $product['title']       = $title;
    $product['slug']        = $slug;
    $product['price']       = $price ?: '';
    $product['description'] = $description;
    $product['tagline']     = $tagline;
    $product['is_active']   = $is_active;
    $product['cover_image'] = $new_img;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= $id ? 'Edit Ebook' : 'New Ebook' ?> — Admin</title>
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
    .main { margin-left: 220px; padding: 48px 48px 80px; max-width: 900px; }
    .top-bar { display: flex; align-items: center; gap: 16px; margin-bottom: 40px; }
    .back-link {
      font-size: 12px; letter-spacing: 0.08em; text-transform: uppercase;
      color: rgba(236,234,226,0.3); text-decoration: none; transition: color 0.2s;
    }
    .back-link:hover { color: #ECEAE2; }
    h1 { font-size: 22px; font-weight: 600; letter-spacing: -0.02em; }

    /* ── Form ── */
    .field { margin-bottom: 28px; }
    label {
      display: block; font-size: 11px; letter-spacing: 0.12em; text-transform: uppercase;
      color: rgba(236,234,226,0.4); margin-bottom: 8px;
    }
    input[type=text], input[type=number], textarea {
      width: 100%; background: rgba(236,234,226,0.05);
      border: 1px solid rgba(236,234,226,0.1); color: #ECEAE2;
      font-family: 'Inter', sans-serif; font-size: 15px;
      padding: 12px 14px; outline: none; transition: border-color 0.2s;
    }
    input[type=text]:focus, input[type=number]:focus, textarea:focus { border-color: #E8320A; }
    input[type=number] { appearance: textfield; }
    input[type=number]::-webkit-inner-spin-button,
    input[type=number]::-webkit-outer-spin-button { -webkit-appearance: none; }
    textarea { resize: vertical; min-height: 120px; line-height: 1.6; }
    .field-hint {
      font-size: 11px; color: rgba(236,234,226,0.3); margin-top: 6px;
    }
    .price-wrap { position: relative; }
    .price-prefix {
      position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
      font-size: 13px; color: rgba(236,234,226,0.35); pointer-events: none;
      font-family: 'Inter', sans-serif;
    }
    .price-wrap input[type=number] { padding-left: 44px; }

    /* ── Active toggle ── */
    .toggle-option {
      display: inline-flex; align-items: center; gap: 10px;
      padding: 10px 16px; border: 1px solid rgba(236,234,226,0.1);
      cursor: pointer; font-size: 13px; color: rgba(236,234,226,0.55);
      transition: border-color 0.2s, color 0.2s; user-select: none;
    }
    .toggle-option:has(input:checked) {
      border-color: #E8320A; color: #ECEAE2;
    }
    .toggle-option input[type=checkbox] { accent-color: #E8320A; cursor: pointer; width: 15px; height: 15px; }

    /* ── Drag-and-drop image zone ── */
    .drop-zone {
      border: 2px dashed rgba(236,234,226,0.15); padding: 36px 24px;
      text-align: center; cursor: pointer; transition: border-color 0.2s, background 0.2s;
      position: relative;
    }
    .drop-zone:hover, .drop-zone.dragover {
      border-color: #E8320A; background: rgba(232,50,10,0.04);
    }
    .drop-zone input[type=file] {
      position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%;
    }
    .drop-icon { font-size: 28px; margin-bottom: 10px; opacity: 0.35; line-height: 1; }
    .drop-text { font-size: 13px; color: rgba(236,234,226,0.4); }
    .drop-text span { color: #E8320A; }
    .drop-filename { font-size: 12px; color: rgba(236,234,226,0.5); margin-top: 6px; }
    .img-preview {
      display: block; width: 100%; max-height: 280px; object-fit: cover;
      margin-top: 12px; opacity: 0.85;
    }
    .img-remove {
      display: inline-block; margin-top: 8px; font-size: 11px; letter-spacing: 0.08em;
      text-transform: uppercase; color: rgba(232,50,10,0.6); cursor: pointer;
      background: none; border: none; font-family: inherit; transition: color 0.2s;
    }
    .img-remove:hover { color: #E8320A; }

    /* ── Errors ── */
    .errors { background: rgba(232,50,10,0.1); border: 1px solid rgba(232,50,10,0.3); padding: 16px 20px; margin-bottom: 28px; }
    .errors li { font-size: 13px; color: #E8320A; list-style: none; margin-bottom: 4px; }

    /* ── Buttons ── */
    .btn-row { display: flex; gap: 16px; align-items: center; }
    .btn-save {
      background: #E8320A; color: #ECEAE2; border: none;
      font-family: 'Inter', sans-serif; font-size: 12px; font-weight: 600;
      letter-spacing: 0.08em; text-transform: uppercase;
      padding: 12px 28px; cursor: pointer; transition: opacity 0.2s;
    }
    .btn-save:hover { opacity: 0.85; }
    .btn-cancel {
      font-size: 12px; letter-spacing: 0.08em; text-transform: uppercase;
      color: rgba(236,234,226,0.3); text-decoration: none; transition: color 0.2s;
    }
    .btn-cancel:hover { color: #ECEAE2; }
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
      <a class="back-link" href="ebooks.php">← All Ebooks</a>
      <h1><?= $id ? 'Edit Ebook' : 'New Ebook' ?></h1>
    </div>

    <?php if (!empty($errors)): ?>
      <ul class="errors">
        <?php foreach ($errors as $e): ?>
          <li><?= htmlspecialchars($e) ?></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <form method="POST" action="ebook-edit.php<?= $id ? '?id='.$id : '' ?>" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"/>

      <div class="field">
        <label for="title">Title</label>
        <input type="text" id="title" name="title"
               value="<?= htmlspecialchars($product['title']) ?>"
               placeholder="Ebook title" required/>
      </div>

      <div class="field">
        <label for="slug">Slug <span style="color:rgba(236,234,226,0.3);font-size:10px;text-transform:none;letter-spacing:0">(auto-generated, editable)</span></label>
        <input type="text" id="slug" name="slug"
               value="<?= htmlspecialchars($product['slug']) ?>"
               placeholder="ebook-url-slug" required/>
      </div>

      <div class="field">
        <label for="price">Price</label>
        <div class="price-wrap">
          <span class="price-prefix">IDR</span>
          <input type="number" id="price" name="price"
                 value="<?= htmlspecialchars((string)$product['price']) ?>"
                 placeholder="99000" min="1" required/>
        </div>
        <div class="field-hint">Enter full amount in IDR, e.g. 99000</div>
      </div>

      <div class="field">
        <label for="tagline">Tagline <span style="color:rgba(236,234,226,0.3);font-size:10px;text-transform:none;letter-spacing:0">(used for OG meta / social preview)</span></label>
        <input type="text" id="tagline" name="tagline"
               value="<?= htmlspecialchars($product['tagline'] ?? '') ?>"
               placeholder="Short one-line hook for social sharing"/>
      </div>

      <div class="field">
        <label for="description">Description <span style="color:rgba(236,234,226,0.3);font-size:10px;text-transform:none;letter-spacing:0">(appears on the sales page)</span></label>
        <textarea id="description" name="description"
                  rows="6"
                  placeholder="Full sales page description..."><?= htmlspecialchars($product['description'] ?? '') ?></textarea>
      </div>

      <div class="field">
        <label>Cover Image</label>
        <div class="drop-zone" id="drop-zone">
          <input type="file" name="cover_image" id="img-input" accept="image/*"/>
          <div id="drop-prompt">
            <div class="drop-icon">⬆</div>
            <div class="drop-text">Drag &amp; drop cover here, or <span>browse</span></div>
            <div class="drop-filename" id="drop-filename"></div>
          </div>
        </div>
        <?php if ($product['cover_image']): ?>
          <img class="img-preview" id="img-preview"
               src="uploads/<?= htmlspecialchars($product['cover_image']) ?>"
               alt="Current cover image"/>
          <button type="button" class="img-remove" id="img-remove">Remove image</button>
          <input type="hidden" name="remove_image" id="remove-image-flag" value="0"/>
        <?php else: ?>
          <img class="img-preview" id="img-preview" src="" alt="" style="display:none"/>
          <input type="hidden" name="remove_image" id="remove-image-flag" value="0"/>
        <?php endif; ?>
      </div>

      <div class="field">
        <label>Visibility</label>
        <label class="toggle-option">
          <input type="checkbox" name="is_active" value="1"
                 <?= $product['is_active'] ? 'checked' : '' ?>/>
          Active — sales page is live
        </label>
        <div class="field-hint">When inactive, the sales page returns 404 to the public.</div>
      </div>

      <div class="btn-row">
        <button type="submit" class="btn-save">Save Ebook →</button>
        <a class="btn-cancel" href="ebooks.php">Cancel</a>
      </div>

    </form>
  </main>

  <script>
    /* ── Auto-generate slug from title ── */
    const titleEl  = document.getElementById('title');
    const slugEl   = document.getElementById('slug');
    let slugEdited = <?= $id ? 'true' : 'false' ?>;

    titleEl.addEventListener('input', () => {
      if (slugEdited) return;
      slugEl.value = titleEl.value
        .toLowerCase()
        .replace(/[^a-z0-9\s-]/g, '')
        .trim()
        .replace(/\s+/g, '-');
    });
    slugEl.addEventListener('input', () => { slugEdited = true; });

    /* ── Drag-and-drop cover image ── */
    const dropZone     = document.getElementById('drop-zone');
    const imgInput     = document.getElementById('img-input');
    const imgPreview   = document.getElementById('img-preview');
    const dropFilename = document.getElementById('drop-filename');
    const imgRemove    = document.getElementById('img-remove');
    const removeFlag   = document.getElementById('remove-image-flag');

    function showPreview(file) {
      if (!file || !file.type.startsWith('image/')) return;
      dropFilename.textContent = file.name;
      const reader = new FileReader();
      reader.onload = e => {
        imgPreview.src = e.target.result;
        imgPreview.style.display = 'block';
        if (imgRemove) imgRemove.style.display = 'inline-block';
      };
      reader.readAsDataURL(file);
    }

    imgInput.addEventListener('change', () => {
      if (imgInput.files[0]) showPreview(imgInput.files[0]);
    });

    dropZone.addEventListener('dragover', e => {
      e.preventDefault();
      dropZone.classList.add('dragover');
    });
    dropZone.addEventListener('dragleave', () => dropZone.classList.remove('dragover'));
    dropZone.addEventListener('drop', e => {
      e.preventDefault();
      dropZone.classList.remove('dragover');
      const file = e.dataTransfer.files[0];
      if (file) {
        const dt = new DataTransfer();
        dt.items.add(file);
        imgInput.files = dt.files;
        showPreview(file);
      }
    });

    if (imgRemove) {
      imgRemove.addEventListener('click', () => {
        imgPreview.src = '';
        imgPreview.style.display = 'none';
        imgInput.value = '';
        dropFilename.textContent = '';
        removeFlag.value = '1';
        imgRemove.style.display = 'none';
      });
    }

    /* ── Theme toggle ── */
    (function(){
      var btn = document.getElementById('theme-toggle');
      function update(){
        var dark = document.documentElement.getAttribute('data-theme') === 'dark';
        btn.textContent = dark ? '◑ Light mode' : '◐ Dark mode';
      }
      update();
      btn.addEventListener('click', function(){
        var next = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', next);
        localStorage.setItem('admin-theme', next);
        update();
      });
    })();
  </script>

</body>
</html>
