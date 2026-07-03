<?php
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_secure', '1');
session_start();
if (!isset($_SESSION['authed'])) { header('Location: /admin/login'); exit; }
require __DIR__ . '/../api/db.php';
require __DIR__ . '/../api/helpers.php';

$_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));

$id      = isset($_GET['id']) ? (int)$_GET['id'] : null;
$project = [
    'title' => '', 'slug' => '', 'excerpt' => '', 'cover_image' => '',
    'client' => '', 'role' => 'UI/UX Designer', 'year' => date('Y'), 'tools' => '',
    's1_body' => '', 's2_body' => '', 's3_body' => '', 's4_body' => '', 's5_body' => '',
    's1_images' => '[]', 's2_images' => '[]', 's3_images' => '[]', 's4_images' => '[]', 's5_images' => '[]',
    'is_published' => 0, 'sort_order' => 0,
];
$errors = [];
$success = false;

/* ── Load existing project ── */
if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM projects WHERE id = ?');
    $stmt->execute([$id]);
    $found = $stmt->fetch();
    if ($found) {
        $project = $found;
        /* Ensure images fields are valid JSON strings */
        foreach (['s1_images','s2_images','s3_images','s4_images','s5_images'] as $f) {
            if (empty($project[$f])) $project[$f] = '[]';
        }
    }
}

/* ── Handle form submit ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['csrf'] ?? '') !== $_SESSION['csrf_token']) {
        http_response_code(403); exit('Forbidden.');
    }

    $title       = trim($_POST['title']       ?? '');
    $slug        = trim($_POST['slug']        ?? '');
    $excerpt     = trim($_POST['excerpt']     ?? '');
    $client      = trim($_POST['client']      ?? '');
    $role        = trim($_POST['role']        ?? 'UI/UX Designer');
    $year        = (int)($_POST['year']       ?? date('Y'));
    $tools       = trim($_POST['tools']       ?? '');
    $sort_order  = (int)($_POST['sort_order'] ?? 0);
    $is_pub      = isset($_POST['is_published']) ? 1 : 0;
    $cover_img   = $project['cover_image'];

    /* Section bodies (synced from Quill via JS before submit) */
    $s1_body = $_POST['s1_body'] ?? '';
    $s2_body = $_POST['s2_body'] ?? '';
    $s3_body = $_POST['s3_body'] ?? '';
    $s4_body = $_POST['s4_body'] ?? '';
    $s5_body = $_POST['s5_body'] ?? '';

    /* Section images (JSON arrays managed by JS) */
    $s1_images = $_POST['s1_images'] ?? '[]';
    $s2_images = $_POST['s2_images'] ?? '[]';
    $s3_images = $_POST['s3_images'] ?? '[]';
    $s4_images = $_POST['s4_images'] ?? '[]';
    $s5_images = $_POST['s5_images'] ?? '[]';

    /* Validate JSON arrays */
    foreach (['s1_images'=>$s1_images,'s2_images'=>$s2_images,'s3_images'=>$s3_images,'s4_images'=>$s4_images,'s5_images'=>$s5_images] as $k => $v) {
        if (json_decode($v) === null) $$k = '[]';
    }

    /* Validation */
    if (!$title) $errors[] = 'Title is required.';
    if (!$slug)  $errors[] = 'Slug is required.';
    if ($slug && !preg_match('/^[a-z0-9-]+$/', $slug)) $errors[] = 'Slug may only contain lowercase letters, numbers, and hyphens.';

    if ($slug) {
        $chk = $pdo->prepare('SELECT id FROM projects WHERE slug = ? AND id != ?');
        $chk->execute([$slug, $id ?? 0]);
        if ($chk->fetch()) $errors[] = 'That slug is already in use.';
    }

    /* ── Cover image upload ── */
    if (($_POST['remove_cover'] ?? '0') === '1' && empty($_FILES['cover_image']['name'])) {
        $cover_img = '';
    }
    if (!empty($_FILES['cover_image']['name'])) {
        $file = $_FILES['cover_image'];
        if (!isAllowedImage($file['tmp_name'])) {
            $errors[] = 'Cover image must be JPG, PNG, WebP or GIF.';
        } elseif ($file['size'] > 5 * 1024 * 1024) {
            $errors[] = 'Cover image must be under 5 MB.';
        } else {
            $ext      = $ext_map[getUploadMime($file['tmp_name'])] ?? 'jpg';
            $ext_map  = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'];
            $ext      = ($ext_map[getUploadMime($file['tmp_name'])] ?? 'jpg');
            $filename = 'proj_' . uniqid('', true) . '.' . $ext;
            $dest     = __DIR__ . '/uploads/' . $filename;
            if (!is_dir(__DIR__ . '/uploads')) mkdir(__DIR__ . '/uploads', 0755, true);
            if (move_uploaded_file($file['tmp_name'], $dest)) {
                $cover_img = '/admin/uploads/' . $filename;
            } else {
                $errors[] = 'Failed to save cover image.';
            }
        }
    }

    if (empty($errors)) {
        try {
            if ($id) {
                $stmt = $pdo->prepare(
                    'UPDATE projects SET title=?,slug=?,excerpt=?,cover_image=?,client=?,role=?,year=?,tools=?,
                     s1_body=?,s2_body=?,s3_body=?,s4_body=?,s5_body=?,
                     s1_images=?,s2_images=?,s3_images=?,s4_images=?,s5_images=?,
                     is_published=?,sort_order=? WHERE id=?'
                );
                $stmt->execute([
                    $title,$slug,$excerpt,$cover_img,$client,$role,$year,$tools,
                    $s1_body,$s2_body,$s3_body,$s4_body,$s5_body,
                    $s1_images,$s2_images,$s3_images,$s4_images,$s5_images,
                    $is_pub,$sort_order,$id
                ]);
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO projects (title,slug,excerpt,cover_image,client,role,year,tools,
                     s1_body,s2_body,s3_body,s4_body,s5_body,
                     s1_images,s2_images,s3_images,s4_images,s5_images,
                     is_published,sort_order)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
                );
                $stmt->execute([
                    $title,$slug,$excerpt,$cover_img,$client,$role,$year,$tools,
                    $s1_body,$s2_body,$s3_body,$s4_body,$s5_body,
                    $s1_images,$s2_images,$s3_images,$s4_images,$s5_images,
                    $is_pub,$sort_order
                ]);
                $id = (int)$pdo->lastInsertId();
            }
            header('Location: /admin/project-edit?id=' . $id . '&saved=1');
            exit;
        } catch (PDOException $e) {
            error_log('admin/project-edit.php: ' . $e->getMessage());
            $errors[] = 'Database error. Please try again or check the server logs.';
        }
    }

    /* Re-populate on error */
    $project = array_merge($project, compact(
        'title','slug','excerpt','cover_img','client','role','year','tools',
        's1_body','s2_body','s3_body','s4_body','s5_body',
        's1_images','s2_images','s3_images','s4_images','s5_images',
        'is_pub','sort_order'
    ));
    $project['cover_image']  = $cover_img;
    $project['is_published'] = $is_pub;
}

$saved = isset($_GET['saved']);

$section_labels = [
    1 => ['num' => '01', 'title' => 'The Brief',            'hint' => 'Describe the client\'s challenge, goals, and constraints.'],
    2 => ['num' => '02', 'title' => 'AI-Assisted Analysis', 'hint' => 'What you prompted Claude with, what it surfaced, how it shaped your thinking.'],
    3 => ['num' => '03', 'title' => 'AI Concepts',          'hint' => 'What Figma Make / Claude Design generated. Screenshots go below.'],
    4 => ['num' => '04', 'title' => 'My Design Process',    'hint' => 'How you rebuilt it on Figma — decisions, rationale, best practices applied.'],
    5 => ['num' => '05', 'title' => 'Final Design',         'hint' => 'The polished result and outcome. Upload final screenshots below.'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Admin — <?= $id ? 'Edit' : 'New' ?> Case Study</title>
  <meta name="robots" content="noindex, nofollow"/>
  <script>(function(){var t=localStorage.getItem('admin-theme')||'dark';document.documentElement.setAttribute('data-theme',t);})();</script>
  <link rel="icon" type="image/png" href="/assets/logo.png"/>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700&family=Geist+Mono:wght@400;500;600&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/quill/1.3.7/quill.snow.min.css"/>
  <link rel="stylesheet" href="theme.css?v=4"/>
  <style>
    /* ── Project editor extras ── */
    .pe-section {
      background: rgba(255,255,255,0.03);
      border: 1px solid rgba(236,234,226,0.08);
      border-radius: 8px;
      margin-bottom: 32px;
      overflow: hidden;
    }
    .pe-section-head {
      display: flex; align-items: center; gap: 12px;
      padding: 16px 20px;
      border-bottom: 1px solid rgba(236,234,226,0.08);
      background: rgba(255,255,255,0.02);
    }
    .pe-section-num {
      font-family: var(--font-mono); font-size: 12px;
      letter-spacing: 0.12em; text-transform: uppercase;
      color: var(--red); flex-shrink: 0;
    }
    .pe-section-title { font-size: 14px; font-weight: 600; }
    .pe-section-hint  { font-size: 12px; opacity: 0.4; }
    .pe-section-body  { padding: 0 0 20px; }

    /* Quill skin overrides for dark admin */
    .ql-toolbar.ql-snow {
      border: none;
      border-bottom: 1px solid rgba(236,234,226,0.08);
      background: rgba(255,255,255,0.02);
    }
    .ql-container.ql-snow { border: none; }
    .ql-editor { min-height: 140px; font-size: 14px; color: var(--text); }
    .ql-editor.ql-blank::before { color: rgba(236,234,226,0.25); }
    .ql-snow .ql-stroke { stroke: rgba(236,234,226,0.5); }
    .ql-snow .ql-fill  { fill:   rgba(236,234,226,0.5); }
    .ql-snow .ql-picker { color: rgba(236,234,226,0.5); }
    .ql-snow .ql-picker-options { background: #1a1916; border-color: rgba(236,234,226,0.1); }
    [data-theme="light"] .ql-editor { color: #0D0C09; }
    [data-theme="light"] .ql-snow .ql-stroke { stroke: #0D0C09; }
    [data-theme="light"] .ql-snow .ql-fill   { fill: #0D0C09; }
    [data-theme="light"] .ql-snow .ql-picker  { color: #0D0C09; }
    [data-theme="light"] .ql-snow .ql-picker-options { background: #fff; }
    [data-theme="light"] .ql-toolbar.ql-snow { background: rgba(0,0,0,0.02); }

    /* Image gallery */
    .pe-img-gallery {
      display: flex; flex-wrap: wrap; gap: 12px;
      padding: 16px 20px 0;
    }
    .pe-img-thumb {
      position: relative; width: 120px; height: 80px;
      border-radius: 6px; overflow: hidden;
      border: 1px solid rgba(236,234,226,0.1);
      background: rgba(255,255,255,0.05);
    }
    .pe-img-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .pe-img-thumb-remove {
      position: absolute; top: 4px; right: 4px;
      width: 20px; height: 20px; border-radius: 50%;
      background: rgba(0,0,0,0.7); border: none; cursor: pointer;
      color: #fff; font-size: 12px; line-height: 20px; text-align: center;
      display: flex; align-items: center; justify-content: center;
    }
    .pe-img-upload-row { padding: 12px 20px 0; display: flex; align-items: center; gap: 12px; }
    .pe-img-upload-btn {
      font-size: 12px; letter-spacing: 0.06em; text-transform: uppercase;
      border: 1px dashed rgba(var(--text-rgb),0.2); padding: 8px 16px;
      border-radius: 6px; cursor: pointer; color: rgba(236,234,226,0.5);
      transition: border-color 0.2s, color 0.2s; background: none;
      font-family: var(--font-mono);
    }
    .pe-img-upload-btn:hover { border-color: rgba(236,234,226,0.5); color: var(--text); }
    .pe-img-status { font-size: 12px; color: rgba(var(--text-rgb),0.4); }

    /* Cover image */
    .cover-preview { max-width: 320px; border-radius: 6px; margin-bottom: 12px; display: block; border: 1px solid rgba(236,234,226,0.1); }
    .cover-actions { display: flex; gap: 12px; margin-bottom: 8px; }
    .cover-remove  { font-size: 12px; color: var(--red); cursor: pointer; background: none; border: none; font-family: var(--font-sans); }

    /* Success banner */
    .save-success { background: rgba(34,197,94,0.12); border: 1px solid rgba(34,197,94,0.3); color: #4ade80; padding: 12px 16px; border-radius: 6px; margin-bottom: 24px; font-size: 14px; }

    /* Form two-column meta */
    .pe-meta-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
    @media (max-width: 900px) { .pe-meta-grid { grid-template-columns: 1fr; } }
  </style>
</head>
<body>

<?php include __DIR__ . '/partials/sidebar.php'; ?>

<main class="main">
  <div class="top-bar">
    <h1><?= $id ? 'Edit Case Study' : 'New Case Study' ?></h1>
    <a class="btn-new" href="projects.php">&larr; All Projects</a>
  </div>

  <?php if ($saved): ?>
  <div class="save-success">Project saved successfully.</div>
  <?php endif; ?>

  <?php if ($errors): ?>
  <div class="error-banner" style="background:rgba(var(--red-rgb),0.1);border:1px solid rgba(var(--red-rgb),0.3);color:var(--error-text);padding:12px 16px;border-radius:6px;margin-bottom:24px;font-size:14px;">
    <?php foreach ($errors as $e): ?>
      <div><?= escHtml($e) ?></div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <form method="POST" enctype="multipart/form-data" id="project-form">
    <input type="hidden" name="csrf" value="<?= escHtml($_SESSION['csrf_token']) ?>"/>
    <input type="hidden" name="remove_cover" id="remove-cover-flag" value="0"/>

    <!-- ── Meta fields ── -->
    <div class="section-heading" style="margin-bottom:16px;">Project Details</div>

    <div class="field">
      <label class="hint" for="title">Title</label>
      <input class="field-input" type="text" id="title" name="title"
             value="<?= escHtml($project['title']) ?>" placeholder="Project title" required/>
    </div>

    <div class="field">
      <label class="hint" for="slug">Slug <span style="opacity:0.4;font-size:14px;">(/case-studies/SLUG)</span></label>
      <input class="field-input" type="text" id="slug" name="slug"
             value="<?= escHtml($project['slug']) ?>" placeholder="project-name"
             pattern="[a-z0-9-]+" title="Lowercase letters, numbers and hyphens only"/>
    </div>

    <div class="field">
      <label class="hint" for="excerpt">Excerpt <span style="opacity:0.4;font-size:14px;">(short description for the listing card)</span></label>
      <textarea class="field-input" id="excerpt" name="excerpt" rows="2"
                placeholder="One or two sentences about this project."><?= escHtml($project['excerpt']) ?></textarea>
    </div>

    <div class="pe-meta-grid">
      <div class="field">
        <label class="hint" for="client">Client / Project name</label>
        <input class="field-input" type="text" id="client" name="client"
               value="<?= escHtml($project['client']) ?>" placeholder="e.g. Xertra"/>
      </div>
      <div class="field">
        <label class="hint" for="role">Your role</label>
        <input class="field-input" type="text" id="role" name="role"
               value="<?= escHtml($project['role']) ?>" placeholder="UI/UX Designer"/>
      </div>
      <div class="field">
        <label class="hint" for="year">Year</label>
        <input class="field-input" type="number" id="year" name="year"
               value="<?= escHtml((string)$project['year']) ?>" min="2000" max="2099"/>
      </div>
      <div class="field">
        <label class="hint" for="sort_order">Display order <span style="opacity:0.4;font-size:14px;">(lower = first)</span></label>
        <input class="field-input" type="number" id="sort_order" name="sort_order"
               value="<?= (int)$project['sort_order'] ?>" min="0"/>
      </div>
    </div>

    <div class="field">
      <label class="hint" for="tools">Tools <span style="opacity:0.4;font-size:14px;">(comma-separated: Figma, Claude, Figma Make)</span></label>
      <input class="field-input" type="text" id="tools" name="tools"
             value="<?= escHtml($project['tools']) ?>" placeholder="Figma, Claude, Figma Make"/>
    </div>

    <!-- ── Cover image ── -->
    <div class="field">
      <label class="hint">Cover Image</label>
      <?php if ($project['cover_image']): ?>
        <img id="cover-preview" class="cover-preview"
             src="<?= escHtml($project['cover_image']) ?>"
             alt="Cover image"/>
        <div class="cover-actions">
          <label class="pe-img-upload-btn" style="cursor:pointer;">
            Replace
            <input type="file" name="cover_image" id="cover-input" accept="image/*" hidden/>
          </label>
          <button type="button" class="cover-remove" id="cover-remove-btn">Remove</button>
        </div>
      <?php else: ?>
        <div id="cover-drop" style="border:1px dashed rgba(var(--text-rgb),0.2);border-radius:8px;padding:32px;text-align:center;cursor:pointer;margin-bottom:8px;">
          <span style="font-size:14px;color:rgba(var(--text-rgb),0.4);">Click or drag to upload a cover image</span>
        </div>
        <input type="file" name="cover_image" id="cover-input" accept="image/*" hidden/>
      <?php endif; ?>
    </div>

    <!-- ── Publish controls ── -->
    <div class="field" style="display:flex;align-items:center;gap:12px;">
      <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:14px;">
        <input type="checkbox" name="is_published" id="is_published"
               <?= $project['is_published'] ? 'checked' : '' ?>
               style="width:16px;height:16px;accent-color:var(--red);"/>
        Publish this case study
      </label>
    </div>

    <!-- ── Process sections ── -->
    <div class="section-heading" style="margin:32px 0 16px;">Process Sections</div>
    <p class="hint" style="margin-bottom:24px;opacity:0.5;">Write in each section, then upload screenshots below it. Sections without content are hidden on the public page.</p>

    <?php foreach ($section_labels as $n => $sec): ?>
    <div class="pe-section">
      <div class="pe-section-head">
        <span class="pe-section-num"><?= $sec['num'] ?></span>
        <span class="pe-section-title"><?= escHtml($sec['title']) ?></span>
        <span class="pe-section-hint">— <?= escHtml($sec['hint']) ?></span>
      </div>
      <div class="pe-section-body">
        <!-- Quill editor -->
        <div id="quill-s<?= $n ?>"></div>
        <!-- Hidden input synced on submit -->
        <textarea name="s<?= $n ?>_body" id="s<?= $n ?>-body-input" hidden><?= escHtml($project['s' . $n . '_body'] ?? '') ?></textarea>

        <!-- Section images -->
        <div class="pe-img-gallery" id="s<?= $n ?>-gallery">
          <!-- Thumbnails injected by JS on load -->
        </div>
        <div class="pe-img-upload-row">
          <button type="button" class="pe-img-upload-btn" data-section="<?= $n ?>">+ Upload images</button>
          <input type="file" id="s<?= $n ?>-file-input" accept="image/*" multiple hidden/>
          <span class="pe-img-status" id="s<?= $n ?>-status"></span>
        </div>
        <input type="hidden" name="s<?= $n ?>_images" id="s<?= $n ?>-images-input"
               value="<?= escHtml($project['s' . $n . '_images'] ?? '[]') ?>"/>
      </div>
    </div>
    <?php endforeach; ?>

    <div style="display:flex;gap:16px;margin-top:8px;">
      <button type="submit" class="btn-save">Save Project</button>
      <a class="back-link" href="projects.php">Cancel</a>
    </div>
  </form>
</main>

<script src="https://cdnjs.cloudflare.com/ajax/libs/quill/1.3.7/quill.min.js" integrity="sha512-P2W2rr8ikUPfa31PLBo5bcBQrsa+TNj8jiKadtaIrHQGMo6hQM6RdPjQYxlNguwHz8AwSQ28VkBK6kHBLgd/8g==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script>
(function () {
  var CSRF = <?= json_encode($_SESSION['csrf_token']) ?>;

  /* ── Slug auto-generate from title ── */
  var titleEl    = document.getElementById('title');
  var slugEl     = document.getElementById('slug');
  var slugEdited = <?= $id ? 'true' : 'false' ?>;
  titleEl.addEventListener('input', function () {
    if (slugEdited) return;
    slugEl.value = titleEl.value.toLowerCase()
      .replace(/[^a-z0-9\s-]/g, '').trim().replace(/\s+/g, '-');
  });
  slugEl.addEventListener('input', function () { slugEdited = true; });

  /* ── Cover image drag-and-drop ── */
  var coverDrop  = document.getElementById('cover-drop');
  var coverInput = document.getElementById('cover-input');
  if (coverDrop) {
    coverDrop.addEventListener('click', function () { coverInput.click(); });
    coverDrop.addEventListener('dragover', function (e) { e.preventDefault(); coverDrop.style.borderColor = 'rgba(236,234,226,0.5)'; });
    coverDrop.addEventListener('dragleave', function () { coverDrop.style.borderColor = ''; });
    coverDrop.addEventListener('drop', function (e) {
      e.preventDefault(); coverDrop.style.borderColor = '';
      if (e.dataTransfer.files[0]) {
        var dt = new DataTransfer(); dt.items.add(e.dataTransfer.files[0]);
        coverInput.files = dt.files;
        var reader = new FileReader();
        reader.onload = function (ev) { coverDrop.innerHTML = '<img src="' + ev.target.result + '" style="max-width:100%;border-radius:4px;"/>'; };
        reader.readAsDataURL(e.dataTransfer.files[0]);
      }
    });
    coverInput.addEventListener('change', function () {
      if (!coverInput.files[0]) return;
      var reader = new FileReader();
      reader.onload = function (ev) { coverDrop.innerHTML = '<img src="' + ev.target.result + '" style="max-width:100%;border-radius:4px;"/>'; };
      reader.readAsDataURL(coverInput.files[0]);
    });
  }
  var coverRemoveBtn = document.getElementById('cover-remove-btn');
  if (coverRemoveBtn) {
    coverRemoveBtn.addEventListener('click', function () {
      document.getElementById('remove-cover-flag').value = '1';
      var preview = document.getElementById('cover-preview');
      if (preview) preview.remove();
      coverRemoveBtn.closest('.cover-actions').innerHTML = '<span style="font-size:12px;opacity:0.4;">Cover removed — save to apply.</span>';
    });
  }

  /* ── Quill editors ── */
  var quills = {};
  var toolbar = [
    [{ header: [2, 3, false] }],
    ['bold', 'italic', 'underline'],
    ['blockquote'],
    [{ list: 'ordered' }, { list: 'bullet' }],
    ['link'],
    ['clean']
  ];

  [1, 2, 3, 4, 5].forEach(function (n) {
    var el = document.getElementById('quill-s' + n);
    if (!el) return;
    try {
      var q = new Quill('#quill-s' + n, {
        theme: 'snow',
        placeholder: 'Write content for this section...',
        modules: { toolbar: toolbar }
      });
      /* Pre-populate with existing content */
      var existing = document.getElementById('s' + n + '-body-input').value;
      if (existing.trim()) q.root.innerHTML = existing;
      quills[n] = q;
    } catch (e) {
      console.error('Quill failed for section ' + n, e);
    }
  });

  /* Sync Quill content to hidden inputs before submit */
  document.getElementById('project-form').addEventListener('submit', function () {
    [1, 2, 3, 4, 5].forEach(function (n) {
      if (quills[n]) {
        document.getElementById('s' + n + '-body-input').value = quills[n].root.innerHTML;
      }
    });
  });

  /* ── Section image upload (AJAX via existing admin/upload-image.php) ── */
  function getImages(n) {
    try { return JSON.parse(document.getElementById('s' + n + '-images-input').value || '[]'); }
    catch (e) { return []; }
  }
  function setImages(n, arr) {
    document.getElementById('s' + n + '-images-input').value = JSON.stringify(arr);
  }

  function addThumb(n, url) {
    var gallery = document.getElementById('s' + n + '-gallery');
    var wrap = document.createElement('div');
    wrap.className = 'pe-img-thumb';
    wrap.innerHTML =
      '<img src="' + url + '" alt=""/>' +
      '<button type="button" class="pe-img-thumb-remove" title="Remove">&times;</button>';
    wrap.querySelector('.pe-img-thumb-remove').addEventListener('click', function () {
      var imgs = getImages(n);
      var idx = imgs.indexOf(url);
      if (idx > -1) imgs.splice(idx, 1);
      setImages(n, imgs);
      wrap.remove();
    });
    gallery.appendChild(wrap);
  }

  function uploadFile(n, file) {
    var status = document.getElementById('s' + n + '-status');
    status.textContent = 'Uploading…';
    var fd = new FormData();
    fd.append('image', file);
    fd.append('csrf', CSRF);
    fetch('/admin/upload-image.php', { method: 'POST', body: fd })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (d.url) {
          var imgs = getImages(n);
          imgs.push(d.url);
          setImages(n, imgs);
          addThumb(n, d.url);
          status.textContent = '';
        } else {
          status.textContent = d.error || 'Upload failed.';
        }
      })
      .catch(function () { status.textContent = 'Upload failed.'; });
  }

  document.querySelectorAll('.pe-img-upload-btn').forEach(function (btn) {
    var n = parseInt(btn.dataset.section, 10);
    var fileInput = document.getElementById('s' + n + '-file-input');

    btn.addEventListener('click', function () { fileInput.click(); });
    fileInput.addEventListener('change', function () {
      Array.from(fileInput.files).forEach(function (f) { uploadFile(n, f); });
      fileInput.value = '';
    });
  });

  /* Render existing images on page load */
  [1, 2, 3, 4, 5].forEach(function (n) {
    var imgs = getImages(n);
    imgs.forEach(function (url) { addThumb(n, url); });
  });
})();
</script>
<script src="admin.js"></script>
</body>
</html>
