<?php
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
session_start();
if (!isset($_SESSION['authed'])) { header('Location: login.php'); exit; }
require __DIR__ . '/../api/db.php';

$_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));

$product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
if (!$product_id) { header('Location: ebooks.php'); exit; }

/* ── Load product ── */
$stmt = $pdo->prepare('SELECT * FROM ebook_products WHERE id = ?');
$stmt->execute([$product_id]);
$product = $stmt->fetch();
if (!$product) { header('Location: ebooks.php'); exit; }

$chapter_id   = isset($_GET['chapter_id']) ? (int)$_GET['chapter_id'] : null;
$errors       = [];
$edit_chapter = null;

/* ──────────────────────────────────────────────────────────
   Handle POST actions — all before any HTML output
   ────────────────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['csrf'] ?? '') !== $_SESSION['csrf_token']) {
        http_response_code(403); exit('Forbidden.');
    }
    $action = $_POST['action'] ?? '';

    /* ── Add chapter ── */
    if ($action === 'add_chapter') {
        $max_stmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order), 0) FROM ebook_chapters WHERE product_id = ?');
        $max_stmt->execute([$product_id]);
        $next_order = (int)$max_stmt->fetchColumn() + 1;
        $new_slug   = 'chapter-' . $next_order . '-' . time();
        $ins = $pdo->prepare('INSERT INTO ebook_chapters (product_id, title, slug, sort_order) VALUES (?, ?, ?, ?)');
        $ins->execute([$product_id, 'Untitled Chapter', $new_slug, $next_order]);
        $new_id = (int)$pdo->lastInsertId();
        header("Location: ebook-chapters.php?product_id=$product_id&chapter_id=$new_id");
        exit;
    }

    /* ── Delete chapter ── */
    if ($action === 'delete_chapter') {
        $del_id = (int)($_POST['chapter_id'] ?? 0);
        if ($del_id) {
            $pdo->prepare('DELETE FROM ebook_chapters WHERE id = ? AND product_id = ?')
                ->execute([$del_id, $product_id]);
        }
        header("Location: ebook-chapters.php?product_id=$product_id");
        exit;
    }

    /* ── Reorder chapter (AJAX — returns JSON, no HTML) ── */
    if ($action === 'reorder_chapter') {
        header('Content-Type: application/json');
        $cid = (int)($_POST['chapter_id'] ?? 0);
        $dir = $_POST['direction'] ?? '';
        if (!$cid || !in_array($dir, ['up', 'down'], true)) {
            echo json_encode(['success' => false]); exit;
        }
        $cur_stmt = $pdo->prepare('SELECT id, sort_order FROM ebook_chapters WHERE id = ? AND product_id = ?');
        $cur_stmt->execute([$cid, $product_id]);
        $current = $cur_stmt->fetch();
        if (!$current) { echo json_encode(['success' => false]); exit; }

        if ($dir === 'up') {
            $adj_stmt = $pdo->prepare(
                'SELECT id, sort_order FROM ebook_chapters WHERE product_id = ? AND sort_order < ? ORDER BY sort_order DESC LIMIT 1'
            );
        } else {
            $adj_stmt = $pdo->prepare(
                'SELECT id, sort_order FROM ebook_chapters WHERE product_id = ? AND sort_order > ? ORDER BY sort_order ASC LIMIT 1'
            );
        }
        $adj_stmt->execute([$product_id, $current['sort_order']]);
        $adjacent = $adj_stmt->fetch();
        if (!$adjacent) { echo json_encode(['success' => false]); exit; }

        $pdo->prepare('UPDATE ebook_chapters SET sort_order = ? WHERE id = ?')
            ->execute([$adjacent['sort_order'], $current['id']]);
        $pdo->prepare('UPDATE ebook_chapters SET sort_order = ? WHERE id = ?')
            ->execute([$current['sort_order'], $adjacent['id']]);
        echo json_encode(['success' => true]);
        exit;
    }

    /* ── Save chapter ── */
    if ($action === 'save_chapter') {
        $cid          = (int)($_POST['chapter_id'] ?? 0);
        $title        = trim($_POST['title']        ?? '');
        $slug         = trim($_POST['slug']         ?? '');
        $body         = $_POST['body']              ?? '';
        $is_published = isset($_POST['is_published']) ? 1 : 0;

        if (!$title) $errors[] = 'Chapter title is required.';
        if (!$slug)  $errors[] = 'Slug is required.';
        if ($slug) {
            $chk = $pdo->prepare('SELECT id FROM ebook_chapters WHERE product_id = ? AND slug = ? AND id != ?');
            $chk->execute([$product_id, $slug, $cid]);
            if ($chk->fetch()) $errors[] = 'That slug is already used by another chapter in this product.';
        }

        if (empty($errors) && $cid) {
            $pdo->prepare('UPDATE ebook_chapters SET title=?, slug=?, body=?, is_published=? WHERE id=? AND product_id=?')
                ->execute([$title, $slug, $body, $is_published, $cid, $product_id]);
            header("Location: ebook-chapters.php?product_id=$product_id&chapter_id=$cid");
            exit;
        }

        /* Re-populate form values on error */
        $chapter_id   = $cid;
        $edit_chapter = [
            'id'           => $cid,
            'title'        => $title,
            'slug'         => $slug,
            'body'         => $body,
            'is_published' => $is_published,
        ];
    }
}

/* ── Load chapters list ── */
$ch_list_stmt = $pdo->prepare(
    'SELECT id, title, sort_order, is_published FROM ebook_chapters WHERE product_id = ? ORDER BY sort_order ASC'
);
$ch_list_stmt->execute([$product_id]);
$chapters = $ch_list_stmt->fetchAll();

/* ── Load selected chapter for editor ── */
if ($chapter_id && !$edit_chapter) {
    $ch_stmt = $pdo->prepare('SELECT * FROM ebook_chapters WHERE id = ? AND product_id = ?');
    $ch_stmt->execute([$chapter_id, $product_id]);
    $found = $ch_stmt->fetch();
    if ($found) {
        $edit_chapter = $found;
    } else {
        $chapter_id   = null;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Chapters — <?= htmlspecialchars($product['title']) ?> — Admin</title>
  <script>(function(){var t=localStorage.getItem('admin-theme')||'dark';document.documentElement.setAttribute('data-theme',t);})();</script>
  <link rel="icon" type="image/png" href="/assets/logo.png"/>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="theme.css"/>
  <?php if ($chapter_id && $edit_chapter): ?>
  <link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet"/>
  <?php endif; ?>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html, body {
      height: 100%; overflow: hidden;
      background: #0D0C09; color: #ECEAE2;
      font-family: 'Inter', sans-serif;
    }

    /* ── App shell ── */
    .app-wrap { display: flex; height: 100vh; }

    /* ── Sidebar ── */
    .sidebar {
      width: 220px; flex-shrink: 0; height: 100vh; overflow-y: auto;
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

    /* ── Main wrap ── */
    .main-wrap { flex: 1; display: flex; flex-direction: column; overflow: hidden; min-width: 0; }

    /* ── Top bar ── */
    .top-bar {
      flex-shrink: 0; padding: 20px 32px;
      border-bottom: 1px solid rgba(236,234,226,0.07);
      display: flex; align-items: center; gap: 16px;
    }
    .back-link {
      font-size: 12px; letter-spacing: 0.08em; text-transform: uppercase;
      color: rgba(236,234,226,0.3); text-decoration: none; transition: color 0.2s;
    }
    .back-link:hover { color: #ECEAE2; }
    .top-bar h1 { font-size: 16px; font-weight: 600; letter-spacing: -0.01em; color: rgba(236,234,226,0.7); }

    /* ── Two-panel row ── */
    .panels { flex: 1; display: flex; overflow: hidden; }

    /* ── Chapter list panel ── */
    .panel-list {
      width: 280px; flex-shrink: 0; overflow-y: auto;
      border-right: 1px solid rgba(236,234,226,0.07);
      display: flex; flex-direction: column;
    }
    .panel-list-header {
      padding: 20px 20px 12px;
      font-size: 10px; letter-spacing: 0.12em; text-transform: uppercase;
      color: rgba(236,234,226,0.3); flex-shrink: 0;
    }
    .chapter-list { flex: 1; }
    .chapter-item {
      display: flex; align-items: center; justify-content: space-between;
      padding: 10px 12px 10px 16px;
      border-bottom: 1px solid rgba(236,234,226,0.04);
      transition: background 0.15s;
    }
    .chapter-item:hover { background: rgba(236,234,226,0.03); }
    .chapter-item.active { background: rgba(236,234,226,0.06); }
    .chapter-item-left { display: flex; align-items: center; gap: 10px; flex: 1; min-width: 0; text-decoration: none; }
    .chapter-num {
      font-size: 10px; color: rgba(236,234,226,0.25);
      flex-shrink: 0; width: 18px; text-align: right;
    }
    .chapter-title-text {
      font-size: 13px; color: rgba(236,234,226,0.75); white-space: nowrap;
      overflow: hidden; text-overflow: ellipsis;
    }
    .chapter-item.active .chapter-title-text { color: #ECEAE2; }
    .pub-dot {
      width: 6px; height: 6px; border-radius: 50%; flex-shrink: 0;
      background: rgba(236,234,226,0.15);
    }
    .pub-dot.live { background: #4caf50; }
    .chapter-controls { display: flex; align-items: center; gap: 2px; flex-shrink: 0; }
    .btn-order, .btn-del-ch {
      background: none; border: none; cursor: pointer; padding: 3px 5px;
      font-size: 11px; color: rgba(236,234,226,0.2); transition: color 0.15s;
      font-family: inherit; line-height: 1;
    }
    .btn-order:hover { color: rgba(236,234,226,0.7); }
    .btn-del-ch { color: rgba(232,50,10,0.3); }
    .btn-del-ch:hover { color: #E8320A; }

    .panel-list-footer { padding: 16px; flex-shrink: 0; }
    .btn-add-chapter {
      display: block; width: 100%; padding: 10px;
      background: rgba(232,50,10,0.1); border: 1px solid rgba(232,50,10,0.2);
      color: #E8320A; font-family: 'Inter', sans-serif; font-size: 12px;
      letter-spacing: 0.08em; text-transform: uppercase;
      cursor: pointer; transition: background 0.2s;
    }
    .btn-add-chapter:hover { background: rgba(232,50,10,0.2); }

    /* ── Editor panel ── */
    .panel-editor { flex: 1; overflow-y: auto; padding: 40px 48px; min-width: 0; }
    .editor-inner { max-width: 720px; }

    .placeholder-msg {
      display: flex; align-items: center; justify-content: center;
      height: 100%; min-height: 200px;
      font-size: 14px; color: rgba(236,234,226,0.2);
    }

    /* ── Form ── */
    .field { margin-bottom: 24px; }
    label {
      display: block; font-size: 11px; letter-spacing: 0.12em; text-transform: uppercase;
      color: rgba(236,234,226,0.4); margin-bottom: 8px;
    }
    input[type=text] {
      width: 100%; background: rgba(236,234,226,0.05);
      border: 1px solid rgba(236,234,226,0.1); color: #ECEAE2;
      font-family: 'Inter', sans-serif; font-size: 15px;
      padding: 11px 14px; outline: none; transition: border-color 0.2s;
    }
    input[type=text]:focus { border-color: #E8320A; }

    /* ── Quill dark theme ── */
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
      font-size: 16px; line-height: 1.75; min-height: 360px;
    }
    .ql-editor.ql-blank::before { color: rgba(236,234,226,0.2); font-style: normal; }
    .ql-snow .ql-stroke { stroke: rgba(236,234,226,0.5); }
    .ql-snow .ql-fill { fill: rgba(236,234,226,0.5); }
    .ql-snow .ql-picker { color: rgba(236,234,226,0.5); }
    .ql-snow .ql-picker-options { background: #1a1917; border: 1px solid rgba(236,234,226,0.1); }
    .ql-snow .ql-picker-label::before { color: rgba(236,234,226,0.5); }

    /* ── Published toggle ── */
    .toggle-option {
      display: inline-flex; align-items: center; gap: 10px;
      padding: 10px 16px; border: 1px solid rgba(236,234,226,0.1);
      cursor: pointer; font-size: 13px; color: rgba(236,234,226,0.55);
      transition: border-color 0.2s, color 0.2s; user-select: none;
    }
    .toggle-option:has(input:checked) { border-color: #E8320A; color: #ECEAE2; }
    .toggle-option input[type=checkbox] { accent-color: #E8320A; cursor: pointer; width: 15px; height: 15px; }

    /* ── Errors ── */
    .errors {
      background: rgba(232,50,10,0.1); border: 1px solid rgba(232,50,10,0.3);
      padding: 14px 18px; margin-bottom: 24px; list-style: none;
    }
    .errors li { font-size: 13px; color: #E8320A; margin-bottom: 4px; }
    .errors li:last-child { margin-bottom: 0; }

    /* ── Save button ── */
    .btn-row { display: flex; gap: 16px; align-items: center; margin-top: 8px; }
    .btn-save {
      background: #E8320A; color: #ECEAE2; border: none;
      font-family: 'Inter', sans-serif; font-size: 12px; font-weight: 600;
      letter-spacing: 0.08em; text-transform: uppercase;
      padding: 12px 28px; cursor: pointer; transition: opacity 0.2s;
    }
    .btn-save:hover { opacity: 0.85; }

    .editor-title {
      font-size: 18px; font-weight: 600; letter-spacing: -0.01em;
      margin-bottom: 28px; color: rgba(236,234,226,0.5);
    }
    .editor-title span { color: #ECEAE2; }
  </style>
</head>
<body>
<div class="app-wrap">

  <!-- ── Admin nav sidebar ── -->
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

  <!-- ── Main wrap ── -->
  <div class="main-wrap">

    <!-- Top bar -->
    <div class="top-bar">
      <a class="back-link" href="ebook-edit.php?id=<?= $product_id ?>">← <?= htmlspecialchars($product['title']) ?></a>
      <h1>Chapters</h1>
    </div>

    <!-- Two-panel row -->
    <div class="panels">

      <!-- ── Left: chapter list ── -->
      <div class="panel-list">
        <div class="panel-list-header">Chapters (<?= count($chapters) ?>)</div>

        <div class="chapter-list">
          <?php if (empty($chapters)): ?>
            <div style="padding:24px 16px;font-size:13px;color:rgba(236,234,226,0.2);">No chapters yet.</div>
          <?php else: ?>
            <?php foreach ($chapters as $ch): ?>
              <?php $is_active_ch = ($chapter_id && $ch['id'] === $chapter_id); ?>
              <div class="chapter-item <?= $is_active_ch ? 'active' : '' ?>" id="ch-row-<?= $ch['id'] ?>">
                <a class="chapter-item-left"
                   href="ebook-chapters.php?product_id=<?= $product_id ?>&chapter_id=<?= $ch['id'] ?>">
                  <span class="chapter-num"><?= (int)$ch['sort_order'] ?></span>
                  <span class="chapter-title-text"><?= htmlspecialchars($ch['title']) ?></span>
                  <span class="pub-dot <?= $ch['is_published'] ? 'live' : '' ?>"
                        title="<?= $ch['is_published'] ? 'Published' : 'Draft' ?>"></span>
                </a>
                <div class="chapter-controls">
                  <button class="btn-order" data-id="<?= $ch['id'] ?>" data-dir="up" title="Move up">↑</button>
                  <button class="btn-order" data-id="<?= $ch['id'] ?>" data-dir="down" title="Move down">↓</button>
                  <form method="POST" action="ebook-chapters.php?product_id=<?= $product_id ?>"
                        style="display:inline" onsubmit="return confirm('Delete this chapter? This cannot be undone.')">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"/>
                    <input type="hidden" name="action" value="delete_chapter"/>
                    <input type="hidden" name="chapter_id" value="<?= $ch['id'] ?>"/>
                    <button type="submit" class="btn-del-ch" title="Delete chapter">×</button>
                  </form>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <div class="panel-list-footer">
          <form method="POST" action="ebook-chapters.php?product_id=<?= $product_id ?>">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"/>
            <input type="hidden" name="action" value="add_chapter"/>
            <button type="submit" class="btn-add-chapter">+ Add Chapter</button>
          </form>
        </div>
      </div><!-- /panel-list -->

      <!-- ── Right: chapter editor ── -->
      <div class="panel-editor">
        <?php if (!$chapter_id || !$edit_chapter): ?>
          <div class="placeholder-msg">Select a chapter from the list to edit it.</div>
        <?php else: ?>
          <div class="editor-inner">
            <div class="editor-title">
              <span><?= htmlspecialchars($edit_chapter['title']) ?></span>
            </div>

            <?php if (!empty($errors)): ?>
              <ul class="errors">
                <?php foreach ($errors as $e): ?>
                  <li><?= htmlspecialchars($e) ?></li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>

            <form method="POST"
                  action="ebook-chapters.php?product_id=<?= $product_id ?>&chapter_id=<?= $chapter_id ?>">
              <input type="hidden" name="csrf"       value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"/>
              <input type="hidden" name="action"     value="save_chapter"/>
              <input type="hidden" name="chapter_id" value="<?= $chapter_id ?>"/>

              <div class="field">
                <label for="title">Chapter Title</label>
                <input type="text" id="title" name="title"
                       value="<?= htmlspecialchars($edit_chapter['title']) ?>"
                       placeholder="Chapter title" required/>
              </div>

              <div class="field">
                <label for="slug">Slug <span style="color:rgba(236,234,226,0.3);font-size:10px;text-transform:none;letter-spacing:0">(auto-generated, editable)</span></label>
                <input type="text" id="slug" name="slug"
                       value="<?= htmlspecialchars($edit_chapter['slug']) ?>"
                       placeholder="chapter-url-slug" required/>
              </div>

              <div class="field">
                <label>Content</label>
                <div id="quill-editor"><?= $edit_chapter['body'] ?></div>
                <input type="hidden" name="body" id="body-input"/>
              </div>

              <div class="field">
                <label>Visibility</label>
                <label class="toggle-option">
                  <input type="checkbox" name="is_published" value="1"
                         <?= $edit_chapter['is_published'] ? 'checked' : '' ?>/>
                  Published — visible to readers
                </label>
              </div>

              <div class="btn-row">
                <button type="submit" class="btn-save">Save Chapter →</button>
              </div>
            </form>
          </div>
        <?php endif; ?>
      </div><!-- /panel-editor -->

    </div><!-- /panels -->
  </div><!-- /main-wrap -->
</div><!-- /app-wrap -->

<?php if ($chapter_id && $edit_chapter): ?>
<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
<?php endif; ?>
<script>
  /* ── CSRF token for AJAX ── */
  var CSRF_TOKEN = <?= json_encode($_SESSION['csrf_token']) ?>;
  var PRODUCT_ID = <?= $product_id ?>;

  <?php if ($chapter_id && $edit_chapter): ?>
  /* ── Quill editor ── */
  var quill = new Quill('#quill-editor', {
    theme: 'snow',
    placeholder: 'Write chapter content here...',
    modules: {
      toolbar: [
        [{ header: [2, 3, false] }],
        ['bold', 'italic', 'underline'],
        ['blockquote', 'code-block'],
        [{ list: 'ordered' }, { list: 'bullet' }],
        ['link', 'image'],
        ['clean']
      ]
    }
  });

  /* ── Auto-generate slug from title ── */
  var titleEl   = document.getElementById('title');
  var slugEl    = document.getElementById('slug');
  var slugEdited = true; /* always treat as edited — slug already exists */

  titleEl.addEventListener('input', function () {
    if (slugEdited) return;
    slugEl.value = titleEl.value
      .toLowerCase()
      .replace(/[^a-z0-9\s-]/g, '')
      .trim()
      .replace(/\s+/g, '-');
  });
  slugEl.addEventListener('input', function () { slugEdited = true; });

  /* ── Copy Quill HTML to hidden input before submit ── */
  document.querySelector('form[action*="chapter_id"]').addEventListener('submit', function () {
    document.getElementById('body-input').value = quill.root.innerHTML;
  });
  <?php endif; ?>

  /* ── Up / Down reorder ── */
  document.querySelectorAll('.btn-order').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var chapterId = btn.dataset.id;
      var direction = btn.dataset.dir;
      btn.disabled = true;

      fetch('ebook-chapters.php?product_id=' + PRODUCT_ID, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
          csrf:       CSRF_TOKEN,
          action:     'reorder_chapter',
          chapter_id: chapterId,
          direction:  direction,
        }),
      })
      .then(function (r) { return r.json(); })
      .then(function (json) {
        if (json.success) window.location.reload();
        else btn.disabled = false;
      })
      .catch(function () { btn.disabled = false; });
    });
  });

  /* ── Theme toggle ── */
  (function () {
    var btn = document.getElementById('theme-toggle');
    function update() {
      var dark = document.documentElement.getAttribute('data-theme') === 'dark';
      btn.textContent = dark ? '◑ Light mode' : '◐ Dark mode';
    }
    update();
    btn.addEventListener('click', function () {
      var next = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
      document.documentElement.setAttribute('data-theme', next);
      localStorage.setItem('admin-theme', next);
      update();
    });
  }());
</script>
</body>
</html>
