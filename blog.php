<?php
require __DIR__ . '/api/db.php';
require __DIR__ . '/api/helpers.php';

const POSTS_PER_PAGE = 12;

$page     = max(1, (int)($_GET['page'] ?? 1));
$cat_filter = trim($_GET['cat'] ?? '');
$allowed_cats = ['uiux', 'development', 'ai'];
if ($cat_filter && !in_array($cat_filter, $allowed_cats, true)) $cat_filter = '';

$where = 'WHERE (is_published = 1 OR (scheduled_at IS NOT NULL AND scheduled_at <= NOW()))';
$params = [];
if ($cat_filter) {
    $where .= ' AND category = ?';
    $params[] = $cat_filter;
}

$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM posts $where");
$count_stmt->execute($params);
$total_posts = (int)$count_stmt->fetchColumn();
$total_pages = max(1, (int)ceil($total_posts / POSTS_PER_PAGE));
$page = min($page, $total_pages);
$offset = ($page - 1) * POSTS_PER_PAGE;

$stmt = $pdo->prepare(
    "SELECT title, slug, excerpt, featured_image, category,
            COALESCE(published_at, scheduled_at) AS published_at
     FROM posts
     $where
     ORDER BY COALESCE(published_at, scheduled_at) DESC
     LIMIT " . POSTS_PER_PAGE . " OFFSET $offset"
);
$stmt->execute($params);
$posts = $stmt->fetchAll();

$cat_labels = ['uiux' => 'UI/UX', 'development' => 'Development', 'ai' => 'AI'];

/* ── Metadata ──
   The category filter is a real, linkable server-rendered view, so it gets its
   own title, description and canonical. Previously every ?cat= view inherited
   the unfiltered /blog metadata and canonicalised to /blog, which told Google
   the three category listings were duplicates of page 1.
   Pagination stays self-canonical (rel=prev/next below signal the sequence);
   canonicalising page 2+ back to page 1 would hide those posts from crawlers. */
$cat_descriptions = [
    'uiux'        => 'UI/UX design articles by Denny Pratama — interface craft, usability, and product design decisions explained with real, practical examples.',
    'development' => 'Web development articles by Denny Pratama — practical PHP, JavaScript, and CSS techniques for building fast, maintainable production websites.',
    'ai'          => 'AI articles by Denny Pratama — how designers and developers can use AI as a genuine collaborator without handing over their craft or judgement.',
];

$cat_name = $cat_filter ? ($cat_labels[$cat_filter] ?? $cat_filter) : '';

if ($cat_filter) {
    $title = $page > 1
        ? "$cat_name Articles — Page $page · Denny Pratama"
        : "$cat_name Articles — Denny Pratama";
} else {
    $title = $page > 1 ? "Blog — Page $page · Denny Pratama" : 'Blog — Denny Pratama';
}

$description = $cat_filter
    ? ($cat_descriptions[$cat_filter] ?? 'Articles by Denny Pratama on design, development, and AI.')
    : 'UI/UX design insights, development tips, and AI perspectives from Denny Pratama — practical articles for designers building better digital products.';

/* Build the canonical from the same params that produced this listing. */
$canonical_params = [];
if ($cat_filter) $canonical_params['cat']  = $cat_filter;
if ($page > 1)   $canonical_params['page'] = $page;
$canonical = 'https://dennypratama.com/blog'
    . ($canonical_params ? '?' . http_build_query($canonical_params) : '');

/* Reused by the rel=prev/next links below so they keep the active filter. */
$pag_url = function (int $p) use ($cat_filter) {
    $params = [];
    if ($cat_filter) $params['cat'] = $cat_filter;
    if ($p > 1)      $params['page'] = $p;
    return 'https://dennypratama.com/blog' . ($params ? '?' . http_build_query($params) : '');
};

$og_image  = 'https://dennypratama.com/assets/logo.png';
$jsonld    = json_encode([
    '@context'    => 'https://schema.org',
    '@type'       => 'Blog',
    'name'        => 'Denny Pratama — Blog',
    'description' => $description,
    'url'         => 'https://dennypratama.com/blog',
    'author'      => ['@type' => 'Person', 'name' => 'Denny Pratama', 'url' => 'https://dennypratama.com'],
    'blogPost'    => array_map(function ($p) {
        return [
            '@type'         => 'BlogPosting',
            'headline'      => $p['title'],
            'url'           => 'https://dennypratama.com/blog/' . rawurlencode($p['slug']),
            'datePublished' => $p['published_at'],
            'description'   => $p['excerpt'],
        ];
    }, array_slice($posts, 0, 10)),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include 'partials/head.php'; ?>
<?php if ($page > 1): ?>
  <link rel="prev" href="<?= escHtml($pag_url($page - 1)) ?>"/>
<?php endif; ?>
<?php if ($page < $total_pages): ?>
  <link rel="next" href="<?= escHtml($pag_url($page + 1)) ?>"/>
<?php endif; ?>
</head>
<body>

<?php include 'partials/nav.php'; ?>

<main id="main-content">
  <section class="blog-hero">
    <div class="blog-hero-eyebrow">Blog</div>
    <h1 class="blog-hero-title">Thoughts &amp; ideas.</h1>
  </section>

  <div class="blog-filter">
    <button class="blog-filter-btn active" data-cat="">All</button>
    <button class="blog-filter-btn" data-cat="uiux">UI/UX</button>
    <button class="blog-filter-btn" data-cat="development">Development</button>
    <button class="blog-filter-btn" data-cat="ai">AI</button>
  </div>

  <div class="blog-grid" id="blog-grid">
    <?php if (!$posts): ?>
      <div class="blog-empty">No posts yet.</div>
    <?php else: foreach ($posts as $i => $post):
      $img_url = $post['featured_image']
          ? '/admin/uploads/' . $post['featured_image']
          : null;
      $cat_label = $post['category'] ? ($cat_labels[$post['category']] ?? $post['category']) : null;
      /* The first card sits above the fold on every breakpoint and is the LCP
         candidate — lazy-loading it delays the largest paint by a round trip.
         Everything after it stays lazy. */
      $is_lcp = ($i === 0);
    ?>
      <a class="blog-card"
         href="/blog/<?= rawurlencode($post['slug']) ?>"
         data-cat="<?= escHtml($post['category'] ?? '') ?>">
        <?php if ($img_url): ?>
          <img class="blog-card-img"
               src="<?= escHtml($img_url) ?>"
               alt="<?= escHtml($post['title']) ?>"
               <?= $is_lcp ? 'loading="eager" fetchpriority="high"' : 'loading="lazy"' ?>
               decoding="async"/>
        <?php else: ?>
          <div class="blog-card-img"></div>
        <?php endif; ?>
        <div class="blog-card-head">
          <?php if ($cat_label): ?>
            <span class="blog-card-cat"><?= escHtml($cat_label) ?></span>
          <?php endif; ?>
          <span class="blog-card-meta"><?= escHtml(formatDate($post['published_at'])) ?></span>
        </div>
        <div class="blog-card-title"><?= escHtml($post['title']) ?></div>
        <?php if ($post['excerpt']): ?>
          <div class="blog-card-excerpt"><?= escHtml($post['excerpt']) ?></div>
        <?php endif; ?>
        <div class="blog-card-readmore">Read &rarr;</div>
      </a>
    <?php endforeach; endif; ?>
  </div>

  <?php if ($total_pages > 1): ?>
  <nav class="blog-pagination" aria-label="Posts pagination">
    <div class="blog-pag-left">
      <?php if ($page > 1): ?>
        <a class="blog-pag-link" href="/blog?page=<?= $page - 1 ?><?= $cat_filter ? '&cat=' . urlencode($cat_filter) : '' ?>">← Newer</a>
      <?php endif; ?>
    </div>
    <span class="blog-pag-count"><?= str_pad($page, 2, '0', STR_PAD_LEFT) ?> / <?= str_pad($total_pages, 2, '0', STR_PAD_LEFT) ?></span>
    <div class="blog-pag-right">
      <?php if ($page < $total_pages): ?>
        <a class="blog-pag-link" href="/blog?page=<?= $page + 1 ?><?= $cat_filter ? '&cat=' . urlencode($cat_filter) : '' ?>">Older →</a>
      <?php endif; ?>
    </div>
  </nav>
  <?php endif; ?>

</main>

<?php include 'partials/modal.php'; ?>
<?php include 'partials/footer.php'; ?>

<script>
  (function () {
    /* Category filter — reloads page with ?cat= param for server-side filtering */
    document.querySelectorAll('.blog-filter-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        const cat = btn.dataset.cat;
        const url = cat ? '/blog?cat=' + encodeURIComponent(cat) : '/blog';
        window.location.href = url;
      });
    });
    /* Mark active filter from URL */
    const urlCat = new URLSearchParams(location.search).get('cat') || '';
    document.querySelectorAll('.blog-filter-btn').forEach(btn => {
      btn.classList.toggle('active', btn.dataset.cat === urlCat);
    });
  })();
</script>

<script src="/script.js?v=32" defer></script>
<script>var PAGE='blog', SLUG=null;</script>
<script src="/api/tracker.js?v=1" defer></script>
</body>
</html>
