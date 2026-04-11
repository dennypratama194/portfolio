<?php
/* ════════════════════════════════════════════════════════════
   /read/SLUG?t=TOKEN&ch=N — Token-gated ebook reader
   Routed by .htaccess: /read/SLUG → read.php?slug=SLUG
════════════════════════════════════════════════════════════ */

$slug  = trim($_GET['slug'] ?? '');
$token = trim($_GET['t']    ?? '');

/* Redirect helpers — called before DB to handle missing params */
function deny(string $slug): never {
    header('Location: /ebook/' . rawurlencode($slug) . '?access=denied');
    exit;
}

if (!$slug || !$token) deny((string)$slug);

require_once __DIR__ . '/api/db.php';

/* ── Validate token and verify product slug matches ── */
$pu_stmt = $pdo->prepare(
    'SELECT pu.id AS purchase_id, pu.token,
            ep.id AS product_id, ep.title AS product_title, ep.slug AS product_slug
     FROM ebook_purchases pu
     JOIN ebook_products ep ON ep.id = pu.product_id
     WHERE pu.token = ?'
);
$pu_stmt->execute([$token]);
$purchase = $pu_stmt->fetch();

if (!$purchase || $purchase['product_slug'] !== $slug) deny($slug);

/* ── Load all published chapters ordered by sort_order ── */
$ch_stmt = $pdo->prepare(
    'SELECT id, title, slug, body, sort_order
     FROM ebook_chapters
     WHERE product_id = ? AND is_published = 1
     ORDER BY sort_order ASC'
);
$ch_stmt->execute([$purchase['product_id']]);
$chapters = $ch_stmt->fetchAll();

if (empty($chapters)) deny($slug); /* product exists but no published chapters yet */

/* ── Resolve current chapter (1-indexed, by position) ── */
$total   = count($chapters);
$ch_num  = max(1, min($total, (int)($_GET['ch'] ?? 1)));
$ch_idx  = $ch_num - 1;
$chapter = $chapters[$ch_idx];
$prev    = $ch_idx > 0           ? $chapters[$ch_idx - 1] : null;
$next    = $ch_idx < $total - 1  ? $chapters[$ch_idx + 1] : null;

/* ── Update reading progress ── */
$pdo->prepare('UPDATE ebook_purchases SET last_read_chapter = ? WHERE id = ?')
    ->execute([$chapter['id'], $purchase['purchase_id']]);

/* ── Sanitize chapter body HTML ── */
function sanitize_chapter_html(string $html): string {
    /* Strip <script> and <style> blocks entirely */
    $html = preg_replace('/<script\b[^>]*>[\s\S]*?<\/script>/i',  '', $html);
    $html = preg_replace('/<style\b[^>]*>[\s\S]*?<\/style>/i',    '', $html);
    /* Strip inline event handlers */
    $html = preg_replace('/\s+on[a-z]+\s*=\s*(?:"[^"]*"|\'[^\']*\')/i', '', $html);
    /* Neutralise javascript: URLs */
    $html = preg_replace('/\b(href|src)\s*=\s*(["\'])\s*javascript:/i', '$1=$2#', $html);
    return trim($html);
}

$body_html = sanitize_chapter_html($chapter['body'] ?? '');

/* ── URL builder helper ── */
function reader_url(string $slug, string $token, int $ch_num): string {
    return '/read/' . rawurlencode($slug)
        . '?t=' . urlencode($token)
        . '&ch=' . $ch_num;
}

$page_title = htmlspecialchars($chapter['title'])
    . ' — ' . htmlspecialchars($purchase['product_title']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <!-- No-index: reader pages are private, token-gated -->
  <meta name="robots" content="noindex, nofollow"/>
  <title><?= $page_title ?></title>

  <!-- Theme before paint — prevents flash of wrong color scheme -->
  <script>
    (function(){
      var saved = localStorage.getItem('reader-theme');
      var theme = saved || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
      document.documentElement.setAttribute('data-theme', theme);
    })();
  </script>

  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Inter:wght@300;400;500;600&display=swap"
        rel="stylesheet" media="print" onload="this.media='all'"/>
  <noscript><link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet"/></noscript>

  <style>
    /* ── Design tokens ────────────────────────────────────── */
    :root {
      --bg:          #FAFAF5;
      --bg-2:        #F3F2EC;
      --text:        #1A1A1A;
      --text-2:      #5A5855;
      --text-3:      #9B9895;
      --border:      rgba(26,26,26,0.1);
      --accent:      #E8320A;
      --header-h:    56px;
      --sidebar-w:   280px;
      --content-max: 680px;
    }
    [data-theme="dark"] {
      --bg:    #0D0C09;
      --bg-2:  #131210;
      --text:  #ECEAE2;
      --text-2:#8B8885;
      --text-3:#5A5855;
      --border:rgba(236,234,226,0.08);
    }

    /* ── Reset ───────────────────────────────────────────── */
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html { font-size: 18px; scroll-behavior: smooth; }
    body {
      background: var(--bg); color: var(--text);
      font-family: 'Inter', system-ui, sans-serif;
      line-height: 1.8;
      -webkit-font-smoothing: antialiased;
      transition: background 0.2s, color 0.2s;
    }

    /* ── Reading progress bar (top of page) ──────────────── */
    #read-progress {
      position: fixed; top: 0; left: 0; z-index: 1001;
      height: 2px; width: 0%;
      background: var(--accent);
      transition: width 0.1s linear;
      pointer-events: none;
    }

    /* ── Header ─────────────────────────────────────────── */
    #read-header {
      position: sticky; top: 0; z-index: 100;
      height: var(--header-h);
      background: var(--bg);
      border-bottom: 1px solid var(--border);
      display: flex; align-items: center; justify-content: space-between;
      padding: 0 24px;
      backdrop-filter: blur(12px);
      -webkit-backdrop-filter: blur(12px);
      transition: background 0.2s, border-color 0.2s;
    }
    .rh-left { display: flex; align-items: center; gap: 14px; min-width: 0; }
    .rh-product {
      font-size: 14px; font-weight: 600; letter-spacing: -0.01em;
      color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
      max-width: 280px;
    }
    #sidebar-toggle {
      background: none; border: none; cursor: pointer; padding: 6px;
      color: var(--text-2); font-size: 18px; line-height: 1;
      display: none; /* shown on mobile */
      transition: color 0.2s;
    }
    #sidebar-toggle:hover { color: var(--accent); }
    .rh-right { display: flex; align-items: center; gap: 20px; flex-shrink: 0; }
    .rh-theme-btn {
      background: none; border: 1px solid var(--border);
      color: var(--text-2); cursor: pointer;
      font-size: 12px; letter-spacing: 0.05em;
      padding: 5px 12px; transition: border-color 0.2s, color 0.2s;
    }
    .rh-theme-btn:hover { border-color: var(--accent); color: var(--text); }
    .rh-back {
      font-size: 12px; letter-spacing: 0.08em; text-transform: uppercase;
      color: var(--text-3); text-decoration: none; transition: color 0.2s;
    }
    .rh-back:hover { color: var(--accent); }

    /* ── Layout ─────────────────────────────────────────── */
    #read-layout { display: flex; min-height: calc(100vh - var(--header-h)); }

    /* ── Sidebar ─────────────────────────────────────────── */
    #read-sidebar {
      width: var(--sidebar-w); flex-shrink: 0;
      position: sticky; top: var(--header-h);
      height: calc(100vh - var(--header-h));
      overflow-y: auto; overflow-x: hidden;
      background: var(--bg-2);
      border-right: 1px solid var(--border);
      display: flex; flex-direction: column;
      transition: background 0.2s, border-color 0.2s;
    }
    .rs-header {
      padding: 24px 20px 16px; flex-shrink: 0;
      border-bottom: 1px solid var(--border);
    }
    .rs-product-title {
      font-size: 11px; letter-spacing: 0.1em; text-transform: uppercase;
      color: var(--text-3); margin-bottom: 14px;
    }
    .rs-progress-label {
      font-size: 12px; color: var(--text-3); margin-bottom: 8px;
    }
    .rs-bar {
      height: 3px; background: var(--border); border-radius: 2px; overflow: hidden;
    }
    .rs-bar-fill {
      height: 100%; background: var(--accent); border-radius: 2px;
      transition: width 0.3s ease;
    }
    .rs-nav { flex: 1; padding: 12px 0; }
    .rs-ch-link {
      display: flex; align-items: baseline; gap: 12px;
      padding: 10px 20px; text-decoration: none;
      font-size: 14px; color: var(--text-2); line-height: 1.4;
      transition: background 0.15s, color 0.15s;
      border-left: 2px solid transparent;
    }
    .rs-ch-link:hover { background: var(--border); color: var(--text); }
    .rs-ch-link.active {
      color: var(--accent); border-left-color: var(--accent);
      background: rgba(232,50,10,0.05);
    }
    .rs-ch-num {
      font-size: 10px; color: var(--text-3); flex-shrink: 0;
      width: 20px; text-align: right;
    }
    .rs-ch-link.active .rs-ch-num { color: var(--accent); }

    /* ── Main content ────────────────────────────────────── */
    #read-main {
      flex: 1; min-width: 0;
      padding: 64px 48px 120px;
    }
    .read-inner { max-width: var(--content-max); margin: 0 auto; }

    /* ── Chapter header ──────────────────────────────────── */
    .read-chapter-label {
      font-size: 11px; letter-spacing: 0.12em; text-transform: uppercase;
      color: var(--text-3); margin-bottom: 20px;
    }
    #read-article h1 {
      font-family: 'Instrument Serif', serif; font-weight: 400;
      font-size: clamp(34px, 5vw, 52px); line-height: 1.1;
      letter-spacing: -0.02em; color: var(--text);
      margin-bottom: 48px;
    }

    /* ── Chapter body typography ─────────────────────────── */
    .read-body {
      user-select: none;
      -webkit-user-select: none;
    }
    .read-body p   { margin-bottom: 1em; }
    .read-body p:has(> br:only-child) { margin-bottom: 8px; line-height: 0; }
    .read-body h2  {
      font-family: 'Instrument Serif', serif; font-weight: 400;
      font-size: 1.9rem; line-height: 1.2; letter-spacing: -0.02em;
      color: var(--text); margin: 2.2em 0 0.7em;
    }
    .read-body h3  {
      font-size: 1.15rem; font-weight: 600; letter-spacing: -0.01em;
      color: var(--text); margin: 1.8em 0 0.5em;
    }
    .read-body h4  {
      font-size: 1rem; font-weight: 600;
      color: var(--text); margin: 1.5em 0 0.4em;
    }
    .read-body strong { font-weight: 600; color: var(--text); }
    .read-body em     { font-style: italic; }
    .read-body a      { color: var(--accent); text-decoration: underline; text-underline-offset: 3px; }
    .read-body blockquote {
      border-left: 3px solid var(--accent);
      padding: 4px 0 4px 24px;
      margin: 1.8em 0;
      font-style: italic;
      color: var(--text-2);
    }
    .read-body pre {
      background: var(--bg-2); border: 1px solid var(--border);
      padding: 20px 24px; overflow-x: auto;
      font-size: 0.8rem; line-height: 1.65; margin: 1.5em 0;
    }
    .read-body code {
      font-family: 'Courier New', Courier, monospace;
      font-size: 0.85rem;
      background: var(--bg-2); padding: 2px 7px;
      border: 1px solid var(--border);
    }
    .read-body pre code { background: none; border: none; padding: 0; font-size: 0.8rem; }
    .read-body ol, .read-body ul { padding-left: 28px; margin-bottom: 1.5em; }
    .read-body li   { margin-bottom: 0.45em; }
    .read-body img  { max-width: 100%; height: auto; display: block; margin: 2em 0; }
    .read-body hr   { border: none; border-top: 1px solid var(--border); margin: 3em 0; }

    /* ── Chapter navigation (prev / next) ────────────────── */
    .read-nav-bottom {
      display: flex; justify-content: space-between; align-items: center;
      margin-top: 80px; padding-top: 32px;
      border-top: 1px solid var(--border);
      gap: 24px;
    }
    .rnb-link {
      font-size: 14px; color: var(--text-2); text-decoration: none;
      transition: color 0.2s; max-width: 280px; line-height: 1.4;
    }
    .rnb-link:hover { color: var(--accent); }
    .rnb-link-label {
      font-size: 11px; letter-spacing: 0.1em; text-transform: uppercase;
      color: var(--text-3); display: block; margin-bottom: 4px;
    }
    .rnb-spacer { flex: 1; }

    /* Completion card */
    .read-complete {
      margin-top: 80px; padding: 48px;
      background: var(--bg-2); border: 1px solid var(--border);
      text-align: center;
    }
    .rc-icon {
      width: 48px; height: 48px; border-radius: 50%;
      background: rgba(232,50,10,0.1); color: var(--accent);
      display: flex; align-items: center; justify-content: center;
      font-size: 22px; margin: 0 auto 20px;
    }
    .rc-title {
      font-family: 'Instrument Serif', serif; font-weight: 400;
      font-size: 1.8rem; color: var(--text); margin-bottom: 12px;
    }
    .rc-desc { font-size: 15px; color: var(--text-2); margin-bottom: 28px; }
    .rc-link {
      display: inline-block; font-size: 12px; font-weight: 600;
      letter-spacing: 0.08em; text-transform: uppercase;
      color: var(--accent); text-decoration: none;
      border: 1.5px solid var(--accent); padding: 10px 24px;
      transition: background 0.2s, color 0.2s;
    }
    .rc-link:hover { background: var(--accent); color: #fff; }

    /* ── Sidebar overlay (mobile) ────────────────────────── */
    #sidebar-overlay {
      display: none; position: fixed; inset: 0; z-index: 49;
      background: rgba(0,0,0,0.45);
    }

    /* ── Mobile ──────────────────────────────────────────── */
    @media (max-width: 768px) {
      #sidebar-toggle { display: flex; }
      .rh-product { max-width: 160px; }

      #read-sidebar {
        position: fixed; top: var(--header-h); left: 0; z-index: 50;
        height: calc(100vh - var(--header-h));
        transform: translateX(-100%);
        transition: transform 0.3s cubic-bezier(0.23,1,0.32,1);
        box-shadow: 4px 0 24px rgba(0,0,0,0.15);
      }
      body.sidebar-open #read-sidebar    { transform: translateX(0); }
      body.sidebar-open #sidebar-overlay { display: block; }

      #read-main { padding: 40px 24px 80px; }
      #read-article h1 { font-size: clamp(28px, 7vw, 40px); margin-bottom: 36px; }
      .read-nav-bottom { flex-direction: column; align-items: stretch; }
      .rnb-link { max-width: 100%; }
    }
    @media (max-width: 480px) {
      .rh-back span { display: none; } /* hide "Back to site" text, keep arrow */
    }
  </style>
</head>
<body>

<!-- Reading progress bar -->
<div id="read-progress"></div>

<!-- Sidebar overlay (mobile tap-to-close) -->
<div id="sidebar-overlay" id="sidebar-overlay"></div>

<!-- ── Header ── -->
<header id="read-header">
  <div class="rh-left">
    <button id="sidebar-toggle" aria-label="Toggle chapter list" aria-expanded="false">☰</button>
    <span class="rh-product"><?= htmlspecialchars($purchase['product_title']) ?></span>
  </div>
  <div class="rh-right">
    <button class="rh-theme-btn" id="theme-toggle" aria-label="Toggle dark mode">◑ Dark</button>
    <a class="rh-back" href="/ebook/<?= rawurlencode($slug) ?>">
      <span>Back to site</span> ↗
    </a>
  </div>
</header>

<!-- ── Reader layout ── -->
<div id="read-layout">

  <!-- ── Sidebar: chapter list ── -->
  <aside id="read-sidebar" aria-label="Chapter navigation">
    <div class="rs-header">
      <div class="rs-product-title"><?= htmlspecialchars($purchase['product_title']) ?></div>
      <div class="rs-progress-label">
        <?= $ch_num ?> of <?= $total ?> chapter<?= $total !== 1 ? 's' : '' ?>
      </div>
      <div class="rs-bar">
        <div class="rs-bar-fill" style="width:<?= round(($ch_num / $total) * 100) ?>%"></div>
      </div>
    </div>
    <nav class="rs-nav" aria-label="Chapters">
      <?php foreach ($chapters as $i => $ch): ?>
        <?php $num = $i + 1; ?>
        <a class="rs-ch-link <?= $num === $ch_num ? 'active' : '' ?>"
           href="<?= htmlspecialchars(reader_url($slug, $token, $num)) ?>"
           aria-current="<?= $num === $ch_num ? 'page' : 'false' ?>">
          <span class="rs-ch-num"><?= str_pad($num, 2, '0', STR_PAD_LEFT) ?></span>
          <?= htmlspecialchars($ch['title']) ?>
        </a>
      <?php endforeach; ?>
    </nav>
  </aside>

  <!-- ── Main reading area ── -->
  <main id="read-main">
    <div class="read-inner">

      <article id="read-article">
        <div class="read-chapter-label">
          Chapter <?= $ch_num ?> of <?= $total ?>
        </div>
        <h1><?= htmlspecialchars($chapter['title']) ?></h1>
        <div class="read-body">
          <?= $body_html ?>
        </div>
      </article>

      <!-- ── Chapter navigation ── -->
      <?php if (!$next): ?>
        <!-- Completion card (last chapter) -->
        <div class="read-complete" id="read-complete">
          <div class="rc-icon">✓</div>
          <div class="rc-title">You've completed the ebook!</div>
          <p class="rc-desc">
            Well done. Apply what you've learned — that's where the real value is.
          </p>
          <a class="rc-link" href="/ebook/<?= rawurlencode($slug) ?>">Back to ebook page →</a>
        </div>
        <?php if ($prev): ?>
          <nav class="read-nav-bottom" style="border-top:none;margin-top:24px;">
            <a class="rnb-link"
               href="<?= htmlspecialchars(reader_url($slug, $token, $ch_num - 1)) ?>">
              <span class="rnb-link-label">← Previous chapter</span>
              <?= htmlspecialchars($prev['title']) ?>
            </a>
            <div class="rnb-spacer"></div>
          </nav>
        <?php endif; ?>
      <?php else: ?>
        <nav class="read-nav-bottom" aria-label="Chapter navigation">
          <?php if ($prev): ?>
            <a class="rnb-link"
               href="<?= htmlspecialchars(reader_url($slug, $token, $ch_num - 1)) ?>">
              <span class="rnb-link-label">← Previous</span>
              <?= htmlspecialchars($prev['title']) ?>
            </a>
          <?php else: ?>
            <div class="rnb-spacer"></div>
          <?php endif; ?>
          <?php if ($next): ?>
            <a class="rnb-link" style="text-align:right;"
               href="<?= htmlspecialchars(reader_url($slug, $token, $ch_num + 1)) ?>">
              <span class="rnb-link-label">Next →</span>
              <?= htmlspecialchars($next['title']) ?>
            </a>
          <?php endif; ?>
        </nav>
      <?php endif; ?>

    </div><!-- /read-inner -->
  </main><!-- /read-main -->

</div><!-- /read-layout -->

<!-- GSAP for page fade-in -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js" defer></script>
<script>
  /* ── Theme toggle ── */
  var themeBtn = document.getElementById('theme-toggle');
  function applyTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    localStorage.setItem('reader-theme', theme);
    themeBtn.textContent = theme === 'dark' ? '◐ Light' : '◑ Dark';
  }
  /* Sync button label on load */
  var initTheme = document.documentElement.getAttribute('data-theme') || 'light';
  themeBtn.textContent = initTheme === 'dark' ? '◐ Light' : '◑ Dark';
  themeBtn.addEventListener('click', function () {
    var current = document.documentElement.getAttribute('data-theme');
    applyTheme(current === 'dark' ? 'light' : 'dark');
  });

  /* ── Reading progress bar ── */
  var progressBar = document.getElementById('read-progress');
  window.addEventListener('scroll', function () {
    var total = document.body.scrollHeight - window.innerHeight;
    var pct   = total > 0 ? (window.scrollY / total) * 100 : 0;
    progressBar.style.width = Math.min(100, pct).toFixed(1) + '%';
  }, { passive: true });

  /* ── Mobile sidebar toggle ── */
  var sidebarToggle  = document.getElementById('sidebar-toggle');
  var sidebarOverlay = document.getElementById('sidebar-overlay');
  function openSidebar()  {
    document.body.classList.add('sidebar-open');
    sidebarToggle.setAttribute('aria-expanded', 'true');
  }
  function closeSidebar() {
    document.body.classList.remove('sidebar-open');
    sidebarToggle.setAttribute('aria-expanded', 'false');
  }
  sidebarToggle.addEventListener('click', function () {
    document.body.classList.contains('sidebar-open') ? closeSidebar() : openSidebar();
  });
  sidebarOverlay.addEventListener('click', closeSidebar);
  /* Close sidebar when a chapter link is tapped on mobile */
  document.querySelectorAll('.rs-ch-link').forEach(function (link) {
    link.addEventListener('click', closeSidebar);
  });

  /* ── Anti-copy: right-click on content ── */
  var readBody = document.querySelector('.read-body');
  if (readBody) {
    readBody.addEventListener('contextmenu', function (e) {
      e.preventDefault();
      console.log('📚 This content is your personal copy — enjoy reading it!');
    });
  }

  /* ── GSAP article fade-in ── */
  window.addEventListener('load', function () {
    if (typeof gsap === 'undefined') return;
    gsap.from('#read-article', {
      opacity: 0, y: 18, duration: 0.55, ease: 'power2.out', delay: 0.05,
    });
  });

  /* ── Scroll to top on chapter navigation ── */
  /* (page reloads naturally scroll to top — this handles back/forward cache) */
  window.addEventListener('pageshow', function () {
    window.scrollTo({ top: 0, behavior: 'instant' });
  });
</script>
</body>
</html>
