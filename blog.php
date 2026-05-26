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

$title     = $page > 1 ? "Blog — Page $page · Denny Pratama" : 'Blog — Denny Pratama';
$description = 'Thoughts and ideas on UI/UX design, development, and AI from Denny Pratama.';
$canonical = 'https://dennypratama.com/blog' . ($page > 1 ? '?page=' . $page : '');
$og_image  = 'https://dennypratama.com/assets/og-image.png';
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
  <link rel="prev" href="https://dennypratama.com/blog<?= $page === 2 ? '' : '?page=' . ($page - 1) ?>"/>
<?php endif; ?>
<?php if ($page < $total_pages): ?>
  <link rel="next" href="https://dennypratama.com/blog?page=<?= $page + 1 ?>"/>
<?php endif; ?>
</head>
<body>

<?php include 'partials/nav.php'; ?>

<main>
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
    <?php else: foreach ($posts as $post):
      $img_url = $post['featured_image']
          ? '/admin/uploads/' . $post['featured_image']
          : null;
      $cat_label = $post['category'] ? ($cat_labels[$post['category']] ?? $post['category']) : null;
    ?>
      <a class="blog-card"
         href="/blog/<?= rawurlencode($post['slug']) ?>"
         data-cat="<?= escHtml($post['category'] ?? '') ?>">
        <?php if ($img_url): ?>
          <img class="blog-card-img"
               src="<?= escHtml($img_url) ?>"
               alt="<?= escHtml($post['title']) ?>"
               loading="lazy"/>
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
    <?php if ($page > 1): ?>
      <a class="blog-page-link" href="/blog?page=<?= $page - 1 ?><?= $cat_filter ? '&cat=' . urlencode($cat_filter) : '' ?>">← Newer</a>
    <?php endif; ?>
    <span class="blog-page-info"><?= $page ?> / <?= $total_pages ?></span>
    <?php if ($page < $total_pages): ?>
      <a class="blog-page-link" href="/blog?page=<?= $page + 1 ?><?= $cat_filter ? '&cat=' . urlencode($cat_filter) : '' ?>">Older →</a>
    <?php endif; ?>
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

<script src="/script.js?v=21" defer></script>
<script>var PAGE='blog', SLUG=null;</script>
<script src="/api/tracker.js" defer></script>
</body>
</html>
