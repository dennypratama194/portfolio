<?php
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
session_start();
if (!isset($_SESSION['authed'])) { header('Location: /admin/login'); exit; }
require __DIR__ . '/../api/db.php';

$_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));

$id   = isset($_GET['id']) ? (int)$_GET['id'] : null;
$post = ['title'=>'','slug'=>'','excerpt'=>'','body'=>'','is_published'=>0,'featured_image'=>'','category'=>'','scheduled_at'=>null,'published_at'=>null];
$errors = [];

/* ── Load existing post for edit ── */
if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM posts WHERE id = ?');
    $stmt->execute([$id]);
    $found = $stmt->fetch();
    if ($found) $post = $found;
}

/* ── Handle form submit ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['csrf'] ?? '') !== $_SESSION['csrf_token']) {
        http_response_code(403); exit('Forbidden.');
    }
    $title             = trim($_POST['title']       ?? '');
    $slug              = trim($_POST['slug']        ?? '');
    $excerpt           = trim($_POST['excerpt']     ?? '');
    $body              = $_POST['body']             ?? '';
    $category          = trim($_POST['category']    ?? '');
    $publish_mode      = $_POST['publish_mode']     ?? 'draft'; // 'draft' | 'schedule' | 'publish'
    $scheduled_at_raw  = trim($_POST['scheduled_at'] ?? '');
    $keep_img          = $post['featured_image'];   /* existing image filename */

    /* Resolve publish state */
    if ($publish_mode === 'publish') {
        $is_pub           = 1;
        $new_scheduled_at = null;
        $pub_at           = $post['published_at'] ?: date('Y-m-d H:i:s');
    } elseif ($publish_mode === 'schedule' && $scheduled_at_raw) {
        $is_pub           = 0;
        $new_scheduled_at = date('Y-m-d H:i:s', strtotime($scheduled_at_raw));
        $pub_at           = null;
        if (!$new_scheduled_at || $new_scheduled_at === '1970-01-01 00:00:00') {
            $errors[] = 'Invalid scheduled date/time.';
            $new_scheduled_at = null;
        }
    } elseif ($publish_mode === 'schedule' && !$scheduled_at_raw) {
        $errors[] = 'Please pick a date and time to schedule this post.';
        $is_pub = 0; $new_scheduled_at = null; $pub_at = null;
    } else {
        $is_pub           = 0;
        $new_scheduled_at = null;
        $pub_at           = null;
    }

    /* Basic validation */
    if (!$title) $errors[] = 'Title is required.';
    if (!$slug)  $errors[] = 'Slug is required.';


    /* Slug unique check */
    if ($slug) {
        $chk = $pdo->prepare('SELECT id FROM posts WHERE slug = ? AND id != ?');
        $chk->execute([$slug, $id ?? 0]);
        if ($chk->fetch()) $errors[] = 'That slug is already in use.';
    }

    /* ── Image upload ── */
    $new_img = $keep_img;

    /* Handle explicit remove */
    if (($_POST['remove_image'] ?? '0') === '1' && empty($_FILES['featured_image']['name'])) {
        if ($keep_img && file_exists(__DIR__ . '/uploads/' . $keep_img)) {
            unlink(__DIR__ . '/uploads/' . $keep_img);
        }
        $new_img = '';
    }

    if (!empty($_FILES['featured_image']['name'])) {
        $file    = $_FILES['featured_image'];
        $allowed = ['image/jpeg','image/png','image/webp','image/gif'];
        $mime    = mime_content_type($file['tmp_name']);

        if (!in_array($mime, $allowed)) {
            $errors[] = 'Image must be JPG, PNG, WebP or GIF.';
        } elseif ($file['size'] > 5 * 1024 * 1024) {
            $errors[] = 'Image must be under 5 MB.';
        } else {
            $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = uniqid('img_', true) . '.' . strtolower($ext);
            $dest     = __DIR__ . '/uploads/' . $filename;

            if (!is_dir(__DIR__ . '/uploads')) {
                mkdir(__DIR__ . '/uploads', 0755, true);
            }

            if (move_uploaded_file($file['tmp_name'], $dest)) {
                /* Delete old image if replacing */
                if ($keep_img && file_exists(__DIR__ . '/uploads/' . $keep_img)) {
                    unlink(__DIR__ . '/uploads/' . $keep_img);
                }
                $new_img = $filename;
            } else {
                $errors[] = 'Failed to save image.';
            }
        }
    }

    if (empty($errors)) {
        try {
            if ($id) {
                $stmt = $pdo->prepare(
                    'UPDATE posts SET title=?, slug=?, excerpt=?, body=?, featured_image=?, category=?, is_published=?, published_at=?, scheduled_at=? WHERE id=?'
                );
                $stmt->execute([$title, $slug, $excerpt, $body, $new_img, $category, $is_pub, $pub_at, $new_scheduled_at, $id]);
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO posts (title, slug, excerpt, body, featured_image, category, is_published, published_at, scheduled_at) VALUES (?,?,?,?,?,?,?,?,?)'
                );
                $stmt->execute([$title, $slug, $excerpt, $body, $new_img, $category, $is_pub, $pub_at, $new_scheduled_at]);
            }
            header('Location: /admin/index');
            exit;
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'category')) {
                $errors[] = 'Database missing "category" column — run this SQL in phpMyAdmin: ALTER TABLE posts ADD COLUMN category VARCHAR(50) DEFAULT NULL AFTER excerpt;';
            } else {
                $errors[] = 'Database error: ' . $e->getMessage();
            }
        }
    }

    /* Re-populate form values on error */
    $post['title']        = $title;
    $post['slug']         = $slug;
    $post['excerpt']      = $excerpt;
    $post['body']         = $body;
    $post['category']     = $category;
    $post['is_published'] = $is_pub;
    $post['scheduled_at'] = $new_scheduled_at ?? null;
}

/* ── Derive display mode ── */
if ($post['is_published']) {
    $display_mode = 'publish';
} elseif (!empty($post['scheduled_at'])) {
    $display_mode = 'schedule';
} else {
    $display_mode = 'draft';
}
$sched_val = !empty($post['scheduled_at'])
    ? date('Y-m-d\TH:i', strtotime($post['scheduled_at']))
    : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= $id ? 'Edit Post' : 'New Post' ?> — Admin</title>
  <script>(function(){var t=localStorage.getItem('admin-theme')||'dark';document.documentElement.setAttribute('data-theme',t);})();</script>
  <link rel="icon" type="image/png" href="/assets/logo.png"/>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="theme.css"/>
  <!-- Quill rich text editor (open source, no API key) -->
  <link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet"/>
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
    .sidebar-link:hover { color: #ECEAE2; }
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
    select {
      width: 100%; background: rgba(236,234,226,0.05);
      border: 1px solid rgba(236,234,226,0.1); color: #ECEAE2;
      font-family: 'Inter', sans-serif; font-size: 15px;
      padding: 12px 14px; outline: none; transition: border-color 0.2s;
      appearance: none; cursor: pointer;
    }
    select:focus { border-color: #E8320A; }
    select option { background: #1a1917; }
    input[type=text], input[type=file], textarea {
      width: 100%; background: rgba(236,234,226,0.05);
      border: 1px solid rgba(236,234,226,0.1); color: #ECEAE2;
      font-family: 'Inter', sans-serif; font-size: 15px;
      padding: 12px 14px; outline: none; transition: border-color 0.2s;
    }
    input[type=text]:focus, textarea:focus { border-color: #E8320A; }
    textarea { resize: vertical; min-height: 80px; line-height: 1.6; }
    input[type=file] { cursor: pointer; padding: 10px 14px; }

    /* Quill editor styling to match dark theme */
    .ql-toolbar.ql-snow {
      background: rgba(236,234,226,0.05);
      border: 1px solid rgba(236,234,226,0.1) !important;
      border-bottom: none !important;
    }
    .ql-container.ql-snow {
      border: 1px solid rgba(236,234,226,0.1) !important;
      background: rgba(236,234,226,0.03);
    }
    .ql-editor {
      color: #ECEAE2; font-family: 'Inter', sans-serif;
      font-size: 16px; line-height: 1.75; min-height: 320px;
    }
    .ql-editor p { margin-bottom: 0; }
    .ql-editor.ql-blank::before { color: rgba(236,234,226,0.2); font-style: normal; }
    .ql-snow .ql-stroke { stroke: rgba(236,234,226,0.5); }
    .ql-snow .ql-fill { fill: rgba(236,234,226,0.5); }
    .ql-snow .ql-picker { color: rgba(236,234,226,0.5); }
    .ql-snow .ql-picker-options { background: #1a1917; border: 1px solid rgba(236,234,226,0.1); }

    /* ── Publish mode ── */
    .publish-options { display: flex; gap: 8px; margin-bottom: 12px; }
    .radio-option {
      display: flex; align-items: center; gap: 8px;
      padding: 10px 16px; border: 1px solid rgba(236,234,226,0.1);
      cursor: pointer; font-size: 13px; color: rgba(236,234,226,0.55);
      transition: border-color 0.2s, color 0.2s; user-select: none;
    }
    .radio-option:has(input:checked) {
      border-color: #E8320A; color: #ECEAE2;
    }
    .radio-option input[type=radio] { accent-color: #E8320A; cursor: pointer; }
    input[type=datetime-local] {
      width: 100%; background: rgba(236,234,226,0.05);
      border: 1px solid rgba(236,234,226,0.1); color: #ECEAE2;
      font-family: 'Inter', sans-serif; font-size: 14px;
      padding: 11px 14px; outline: none; transition: border-color 0.2s;
      color-scheme: dark;
    }
    input[type=datetime-local]:focus { border-color: #E8320A; }
    .schedule-hint { font-size: 11px; color: rgba(236,234,226,0.3); margin-top: 6px; }

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
      display: block; width: 100%; max-height: 220px; object-fit: cover;
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
      <a class="sidebar-link" href="ebooks.php">Ebooks</a>
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
      <a class="back-link" href="index.php">← Posts</a>
      <h1><?= $id ? 'Edit Post' : 'New Post' ?></h1>
    </div>

    <?php if (!empty($errors)): ?>
      <ul class="errors">
        <?php foreach ($errors as $e): ?>
          <li><?= htmlspecialchars($e) ?></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <form method="POST" action="edit.php<?= $id ? '?id='.$id : '' ?>" enctype="multipart/form-data">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"/>

      <div class="field">
        <label for="title">Title</label>
        <input type="text" id="title" name="title"
               value="<?= htmlspecialchars($post['title']) ?>"
               placeholder="Post title" required/>
      </div>

      <div class="field">
        <label for="slug">Slug <span style="color:rgba(236,234,226,0.3);font-size:10px;text-transform:none;letter-spacing:0">(auto-generated, editable)</span></label>
        <input type="text" id="slug" name="slug"
               value="<?= htmlspecialchars($post['slug']) ?>"
               placeholder="post-url-slug" required/>
      </div>

      <div class="field">
        <label for="excerpt">Excerpt <span style="color:rgba(236,234,226,0.3);font-size:10px;text-transform:none;letter-spacing:0">(shown on blog listing)</span></label>
        <textarea id="excerpt" name="excerpt" rows="3"
                  placeholder="Short summary of the post..."><?= htmlspecialchars($post['excerpt']) ?></textarea>
      </div>

      <div class="field">
        <label for="category">Category</label>
        <select id="category" name="category">
          <option value="" <?= $post['category']==='' ? 'selected' : '' ?>>— No category —</option>
          <option value="uiux"        <?= $post['category']==='uiux'        ? 'selected' : '' ?>>UI/UX</option>
          <option value="development" <?= $post['category']==='development' ? 'selected' : '' ?>>Development</option>
          <option value="ai"          <?= $post['category']==='ai'          ? 'selected' : '' ?>>AI</option>
        </select>
      </div>

      <div class="field">
        <label>Featured Image</label>
        <div class="drop-zone" id="drop-zone">
          <input type="file" name="featured_image" id="img-input" accept="image/*"/>
          <div id="drop-prompt">
            <div class="drop-icon">⬆</div>
            <div class="drop-text">Drag &amp; drop image here, or <span>browse</span></div>
            <div class="drop-filename" id="drop-filename"></div>
          </div>
        </div>
        <?php if ($post['featured_image']): ?>
          <img class="img-preview" id="img-preview"
               src="uploads/<?= htmlspecialchars($post['featured_image']) ?>"
               alt="Current featured image"/>
          <button type="button" class="img-remove" id="img-remove">Remove image</button>
          <input type="hidden" name="remove_image" id="remove-image-flag" value="0"/>
        <?php else: ?>
          <img class="img-preview" id="img-preview" src="" alt="" style="display:none"/>
          <input type="hidden" name="remove_image" id="remove-image-flag" value="0"/>
        <?php endif; ?>
      </div>

      <div class="field">
        <label>Body</label>
        <!-- Quill editor container -->
        <div id="quill-editor"><?= $post['body'] ?></div>
        <!-- Hidden input that gets submitted -->
        <input type="hidden" name="body" id="body-input"/>
      </div>

      <div class="field">
        <label>Visibility</label>
        <div class="publish-options">
          <label class="radio-option">
            <input type="radio" name="publish_mode" value="draft"
                   <?= $display_mode === 'draft' ? 'checked' : '' ?>>
            Draft
          </label>
          <label class="radio-option">
            <input type="radio" name="publish_mode" value="schedule"
                   <?= $display_mode === 'schedule' ? 'checked' : '' ?>>
            Schedule
          </label>
          <label class="radio-option">
            <input type="radio" name="publish_mode" value="publish"
                   <?= $display_mode === 'publish' ? 'checked' : '' ?>>
            Publish Now
          </label>
        </div>
        <div id="schedule-picker" style="<?= $display_mode === 'schedule' ? '' : 'display:none' ?>">
          <input type="datetime-local" name="scheduled_at" id="scheduled_at"
                 value="<?= htmlspecialchars($sched_val) ?>"/>
          <div class="schedule-hint">Post will go live automatically at the scheduled time.</div>
        </div>
      </div>

      <div class="btn-row">
        <button type="submit" class="btn-save">Save Post →</button>
        <a class="btn-cancel" href="index.php">Cancel</a>
      </div>

    </form>
  </main>

  <script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
  <script>
    /* ── Quill editor ── */
    const quill = new Quill('#quill-editor', {
      theme: 'snow',
      placeholder: 'Write your post here...',
      modules: {
        toolbar: [
          [{ header: [2, 3, false] }],
          ['bold', 'italic', 'underline'],
          ['blockquote'],
          [{ list: 'ordered' }, { list: 'bullet' }],
          ['link', 'image'],
          ['clean']
        ]
      }
    });

    /* ── Auto-generate slug from title ── */
    const titleEl = document.getElementById('title');
    const slugEl  = document.getElementById('slug');
    let slugEdited = <?= $id ? 'true' : 'false' ?>;  /* don't overwrite on edit */

    titleEl.addEventListener('input', () => {
      if (slugEdited) return;
      slugEl.value = titleEl.value
        .toLowerCase()
        .replace(/[^a-z0-9\s-]/g, '')
        .trim()
        .replace(/\s+/g, '-');
    });
    slugEl.addEventListener('input', () => { slugEdited = true; });

    /* ── Drag-and-drop image upload ── */
    const dropZone  = document.getElementById('drop-zone');
    const imgInput  = document.getElementById('img-input');
    const imgPreview = document.getElementById('img-preview');
    const dropFilename = document.getElementById('drop-filename');
    const imgRemove = document.getElementById('img-remove');
    const removeFlag = document.getElementById('remove-image-flag');

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
        /* Transfer dropped file to the real input */
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

    /* ── Toggle schedule date picker ── */
    document.querySelectorAll('input[name=publish_mode]').forEach(radio => {
      radio.addEventListener('change', () => {
        document.getElementById('schedule-picker').style.display =
          radio.value === 'schedule' ? 'block' : 'none';
      });
    });

    /* ── Copy Quill HTML to hidden input before submit ── */
    document.querySelector('form').addEventListener('submit', () => {
      document.getElementById('body-input').value = quill.root.innerHTML;
    });

  </script>
  <script src="admin.js"></script>

</body>
</html>
