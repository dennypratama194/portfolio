<?php
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_secure', '1');
session_start();
if (!isset($_SESSION['authed'])) { header('Location: /admin/login'); exit; }
require __DIR__ . '/../api/db.php';
require __DIR__ . '/../api/helpers.php';

$_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));

/* ── Auto-post token (lets us call /api/auto-post.php?phase=regen for AI image
   regeneration on existing posts). Token is generated on the auto-post settings
   page; if the user hasn't visited that page yet the button is simply hidden. ── */
$auto_token = '';
$auto_cfg_file = __DIR__ . '/../api/.auto_post_config.json';
if (file_exists($auto_cfg_file)) {
    $auto_cfg = json_decode(@file_get_contents($auto_cfg_file), true);
    if (is_array($auto_cfg)) $auto_token = $auto_cfg['token'] ?? '';
}

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
        if (!isAllowedImage($file['tmp_name'])) {
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
  <meta name="robots" content="noindex, nofollow"/>
  <script>(function(){var t=localStorage.getItem('admin-theme')||'dark';document.documentElement.setAttribute('data-theme',t);})();</script>
  <link rel="icon" type="image/png" href="/assets/logo.png"/>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700&family=Geist+Mono:wght@400;500;600&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="theme.css?v=2"/>
  <!-- Quill rich text editor (open source, no API key) -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/quill/1.3.7/quill.snow.min.css" rel="stylesheet"/>
  <style>
    .main { max-width: 900px; }
    .top-bar { justify-content: flex-start; gap: 16px; }
    .field { margin-bottom: 28px; }
    textarea { min-height: 80px; }
    textarea.content-fallback { min-height: 320px; resize: vertical; line-height: 1.75; }
    select { font-size: 16px; }

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
      color: #ECEAE2; font-family: var(--font-sans);
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
      cursor: pointer; font-size: 14px; color: rgba(236,234,226,0.55);
      transition: border-color 0.2s, color 0.2s; user-select: none;
    }
    .radio-option:has(input:checked) {
      border-color: #E8320A; color: #ECEAE2;
    }
    .radio-option input[type=radio] { accent-color: #E8320A; cursor: pointer; }
    input[type=datetime-local] {
      width: 100%; background: rgba(236,234,226,0.05);
      border: 1px solid rgba(236,234,226,0.1); color: #ECEAE2;
      font-family: var(--font-sans); font-size: 14px;
      padding: 11px 14px; outline: none; transition: border-color 0.2s;
      color-scheme: dark;
    }
    input[type=datetime-local]:focus { border-color: #E8320A; }
    .schedule-hint { font-size: 14px; color: rgba(236,234,226,0.3); margin-top: 8px; }

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
    .drop-icon { font-size: 28px; margin-bottom: 12px; opacity: 0.35; line-height: 1; }
    .drop-text { font-size: 14px; color: rgba(236,234,226,0.4); }
    .drop-text span { color: #E8320A; }
    .drop-filename { font-size: 14px; color: rgba(236,234,226,0.5); margin-top: 8px; }
    .img-preview {
      display: block; width: 100%; max-height: 280px; object-fit: cover;
    }
    .img-actions { display: flex; gap: 20px; align-items: center; margin-top: 12px; }
    .img-action, .img-remove {
      font-size: 12px; letter-spacing: 0.1em; text-transform: uppercase;
      background: none; border: none; font-family: inherit; cursor: pointer;
      padding: 0; transition: color 0.2s;
    }
    .img-action { color: rgba(236,234,226,0.5); }
    .img-action:hover { color: #ECEAE2; }
    .img-action:disabled { opacity: 0.4; cursor: not-allowed; }
    .img-remove { color: rgba(232,50,10,0.6); }
    .img-remove:hover { color: #E8320A; }

    .img-regen { margin-top: 16px; }
    .img-regen-status { font-size: 14px; color: rgba(236,234,226,0.5); margin-left: 12px; display: inline-block; }
    .img-regen-status.ok  { color: #4ade80; }
    .img-regen-status.err { color: #E8320A; }
    .img-regen-hint { font-size: 14px; color: rgba(236,234,226,0.35); margin-top: 8px; }

  </style>
</head>
<body>

  <?php include __DIR__ . '/partials/sidebar.php'; ?>

  <main class="main main--wide">
    <div class="top-bar top-bar--gap">
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
        <label for="slug">Slug <span style="color:rgba(236,234,226,0.3);font-size:12px;text-transform:none;letter-spacing:0">(auto-generated, editable)</span></label>
        <input type="text" id="slug" name="slug"
               value="<?= htmlspecialchars($post['slug']) ?>"
               placeholder="post-url-slug" required/>
      </div>

      <div class="field">
        <label for="excerpt">Excerpt <span style="color:rgba(236,234,226,0.3);font-size:12px;text-transform:none;letter-spacing:0">(shown on blog listing)</span></label>
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
        <?php $has_img = !empty($post['featured_image']); ?>

        <!-- Drop zone — shown only when there's no image yet -->
        <div class="drop-zone" id="drop-zone" style="<?= $has_img ? 'display:none' : '' ?>">
          <input type="file" name="featured_image" id="img-input" accept="image/*"/>
          <div id="drop-prompt">
            <div class="drop-icon">⬆</div>
            <div class="drop-text">Drag &amp; drop image here, or <span>browse</span></div>
            <div class="drop-filename" id="drop-filename"></div>
          </div>
        </div>

        <!-- Preview + actions — shown when an image exists or has just been picked -->
        <div class="img-wrap" id="img-wrap" style="<?= $has_img ? '' : 'display:none' ?>">
          <img class="img-preview" id="img-preview"
               src="<?= $has_img ? 'uploads/' . htmlspecialchars($post['featured_image']) : '' ?>"
               alt="Featured image"/>
          <div class="img-actions">
            <button type="button" class="img-action" id="img-replace">↻ Replace image</button>
            <button type="button" class="img-remove" id="img-remove">Remove image</button>
          </div>
        </div>

        <input type="hidden" name="remove_image" id="remove-image-flag" value="0"/>

        <?php $can_regen_image = $id && $auto_token; ?>
        <?php if ($can_regen_image): ?>
        <div class="img-regen">
          <button type="button" class="img-action" id="img-regen">✨ Regenerate with AI</button>
          <span class="img-regen-status" id="img-regen-status"></span>
          <div class="img-regen-hint">Uses the post's saved title and excerpt. Save first if you've changed either.</div>
        </div>
        <?php endif; ?>
      </div>

      <div class="field">
        <label>Body</label>
        <!-- The textarea is the actual saved field + fallback if Quill fails to load.
             Quill enhances it; on submit its HTML is synced back into the textarea. -->
        <textarea name="body" id="body-input" class="content-fallback"><?= htmlspecialchars($post['body'] ?? '') ?></textarea>
        <div id="quill-editor" style="display:none"><?= $post['body'] ?></div>

        <?php if ($can_regen_image): ?>
        <div class="img-regen">
          <button type="button" class="img-action" id="body-reformat">✨ Re-format with AI</button>
          <span class="img-regen-status" id="body-reformat-status"></span>
          <div class="img-regen-hint">Rewrites the body — wraps stripped code in proper code blocks while keeping prose untouched. Loads the result into the editor for review; click Save to commit.</div>
        </div>
        <?php endif; ?>
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

  <script src="https://cdnjs.cloudflare.com/ajax/libs/quill/1.3.7/quill.min.js"></script>
  <?php if ($can_regen_image): ?>
  <script>
    var REGEN_TOKEN = '<?= addslashes($auto_token) ?>';
    var POST_ID     = <?= (int)$id ?>;
  </script>
  <?php endif; ?>
  <script>
    /* ── Quill editor (enhances the textarea; falls back to it on any failure) ── */
    var quill = null;
    try {
    quill = new Quill('#quill-editor', {
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
      /* Quill loaded — swap the plain textarea for the rich editor */
      document.getElementById('body-input').style.display = 'none';
      document.getElementById('quill-editor').style.display = '';
    } catch (e) {
      console.error('Rich editor failed to load; using plain text fallback.', e);
    }

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

    /* ── Featured image: drop-zone (no image) ⇄ preview + actions (has image) ── */
    const dropZone   = document.getElementById('drop-zone');
    const imgInput   = document.getElementById('img-input');
    const imgWrap    = document.getElementById('img-wrap');
    const imgPreview = document.getElementById('img-preview');
    const imgReplace = document.getElementById('img-replace');
    const imgRemove  = document.getElementById('img-remove');
    const removeFlag = document.getElementById('remove-image-flag');

    function showImage() { imgWrap.style.display = ''; dropZone.style.display = 'none'; }
    function showDropZone() { imgWrap.style.display = 'none'; dropZone.style.display = ''; }

    function showPreview(file) {
      if (!file || !file.type.startsWith('image/')) return;
      const reader = new FileReader();
      reader.onload = e => {
        imgPreview.src = e.target.result;
        removeFlag.value = '0';   /* picking a new image cancels any pending removal */
        showImage();
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

    /* Replace → open the file picker (works even though the drop-zone is hidden) */
    if (imgReplace) imgReplace.addEventListener('click', () => imgInput.click());

    if (imgRemove) {
      imgRemove.addEventListener('click', () => {
        imgPreview.src = '';
        imgInput.value = '';
        removeFlag.value = '1';
        showDropZone();
      });
    }

    /* ── Animated-dots loading indicator (CSS in theme.css → .loading-dots) ── */
    function loadingHtml(verb, suffix) {
      return verb + '<span class="loading-dots"><span>.</span><span>.</span><span>.</span></span>' + (suffix || '');
    }

    /* ── Re-format body with AI via /api/auto-post.php?phase=reformat
       (Returns body for review; does NOT save until you click the form's Save button.) ── */
    const bodyReformatBtn    = document.getElementById('body-reformat');
    const bodyReformatStatus = document.getElementById('body-reformat-status');
    if (bodyReformatBtn && typeof REGEN_TOKEN !== 'undefined' && typeof POST_ID !== 'undefined') {
      bodyReformatBtn.addEventListener('click', () => {
        if (!confirm('Re-format the body with AI? It will rewrite this post\'s body so stripped code blocks come back. The new content loads into the editor for review — your current draft will be replaced in the editor, but nothing is saved until you click Save.')) return;
        bodyReformatBtn.disabled = true;
        bodyReformatStatus.className = 'img-regen-status';
        bodyReformatStatus.innerHTML = loadingHtml('Re-formatting', ' (30–60s)');

        fetch('/api/auto-post.php?phase=reformat&token=' + encodeURIComponent(REGEN_TOKEN) + '&post_id=' + encodeURIComponent(POST_ID))
          .then(r => r.json())
          .then(d => {
            if (d.ok && d.body) {
              bodyReformatStatus.className = 'img-regen-status ok';
              bodyReformatStatus.textContent = '✓ Loaded — review and click Save';
              if (quill) {
                quill.root.innerHTML = d.body;
              } else {
                document.getElementById('body-input').value = d.body;
              }
            } else {
              bodyReformatStatus.className = 'img-regen-status err';
              bodyReformatStatus.textContent = '✗ ' + (d.error || 'Failed');
            }
            bodyReformatBtn.disabled = false;
          })
          .catch(() => {
            bodyReformatStatus.className = 'img-regen-status err';
            bodyReformatStatus.textContent = '✗ Request failed';
            bodyReformatBtn.disabled = false;
          });
      });
    }

    /* ── Regenerate featured image via /api/auto-post.php?phase=regen ── */
    const imgRegen       = document.getElementById('img-regen');
    const imgRegenStatus = document.getElementById('img-regen-status');
    if (imgRegen && typeof REGEN_TOKEN !== 'undefined' && typeof POST_ID !== 'undefined') {
      imgRegen.addEventListener('click', () => {
        if (!confirm('Regenerate the featured image with AI? This uses the post\'s saved title + excerpt and replaces the current image.')) return;
        imgRegen.disabled = true;
        imgRegenStatus.className = 'img-regen-status';
        imgRegenStatus.innerHTML = loadingHtml('Generating', ' (30–60s)');

        fetch('/api/auto-post.php?phase=regen&token=' + encodeURIComponent(REGEN_TOKEN) + '&post_id=' + encodeURIComponent(POST_ID))
          .then(r => r.json())
          .then(d => {
            if (d.image) {
              imgRegenStatus.className = 'img-regen-status ok';
              imgRegenStatus.textContent = '✓ New image saved';
              /* Update preview without reloading; cache-bust so the new file shows. */
              imgPreview.src = 'uploads/' + d.image + '?t=' + Date.now();
              imgInput.value = '';
              removeFlag.value = '0';
              showImage();
            } else {
              imgRegenStatus.className = 'img-regen-status err';
              imgRegenStatus.textContent = '✗ ' + (d.image_error || d.error || 'Failed');
            }
            imgRegen.disabled = false;
          })
          .catch(() => {
            imgRegenStatus.className = 'img-regen-status err';
            imgRegenStatus.textContent = '✗ Request failed';
            imgRegen.disabled = false;
          });
      });
    }

    /* ── Toggle schedule date picker ── */
    document.querySelectorAll('input[name=publish_mode]').forEach(radio => {
      radio.addEventListener('change', () => {
        document.getElementById('schedule-picker').style.display =
          radio.value === 'schedule' ? 'block' : 'none';
      });
    });

    /* ── Sync the rich editor into the textarea before submit (textarea is the saved field) ── */
    document.querySelector('form').addEventListener('submit', () => {
      if (quill) document.getElementById('body-input').value = quill.root.innerHTML;
    });

  </script>
  <script src="admin.js"></script>

</body>
</html>
