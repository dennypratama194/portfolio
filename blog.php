<?php
require __DIR__ . '/api/db.php';
require __DIR__ . '/api/helpers.php';

$stmt = $pdo->query(
    'SELECT title, slug, excerpt, featured_image, category,
            COALESCE(published_at, scheduled_at) AS published_at
     FROM posts
     WHERE is_published = 1
        OR (scheduled_at IS NOT NULL AND scheduled_at <= NOW())
     ORDER BY COALESCE(published_at, scheduled_at) DESC'
);
$posts = $stmt->fetchAll();

$cat_labels = ['uiux' => 'UI/UX', 'development' => 'Development', 'ai' => 'AI'];

$title       = 'Blog — Denny Pratama';
$description = 'Thoughts and ideas on UI/UX design, development, and AI from Denny Pratama.';
$canonical   = 'https://dennypratama.com/blog';
$jsonld      = json_encode([
    '@context'    => 'https://schema.org',
    '@type'       => 'Blog',
    'name'        => 'Denny Pratama — Blog',
    'description' => $description,
    'url'         => $canonical,
    'author'      => ['@type' => 'Person', 'name' => 'Denny Pratama'],
    'blogPost'    => array_map(function ($p) {
        return [
            '@type'         => 'BlogPosting',
            'headline'      => $p['title'],
            'url'           => 'https://dennypratama.com/post?slug=' . rawurlencode($p['slug']),
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
         href="/post?slug=<?= rawurlencode($post['slug']) ?>"
         data-cat="<?= escHtml($post['category'] ?? '') ?>">
        <?php if ($img_url): ?>
          <img class="blog-card-img"
               src="<?= escHtml($img_url) ?>"
               alt="<?= escHtml($post['title']) ?>"
               loading="lazy"/>
        <?php else: ?>
          <div class="blog-card-img"></div>
        <?php endif; ?>
        <?php if ($cat_label): ?>
          <span class="blog-card-cat"><?= escHtml($cat_label) ?></span>
        <?php endif; ?>
        <div class="blog-card-meta"><?= escHtml(formatDate($post['published_at'])) ?></div>
        <div class="blog-card-title"><?= escHtml($post['title']) ?></div>
        <?php if ($post['excerpt']): ?>
          <div class="blog-card-excerpt"><?= escHtml($post['excerpt']) ?></div>
        <?php endif; ?>
        <div class="blog-card-readmore">Read &rarr;</div>
      </a>
    <?php endforeach; endif; ?>
  </div>
</main>

<?php include 'partials/modal.php'; ?>
<?php include 'partials/footer.php'; ?>

<script>
  (function () {
    const grid = document.getElementById('blog-grid');
    if (!grid) return;
    const cards = grid.querySelectorAll('.blog-card');
    const empty = document.createElement('div');
    empty.className = 'blog-empty';
    empty.textContent = 'No posts in this category yet.';
    empty.style.display = 'none';
    grid.appendChild(empty);

    document.querySelectorAll('.blog-filter-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        document.querySelectorAll('.blog-filter-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        const cat = btn.dataset.cat;
        let visible = 0;
        cards.forEach(card => {
          const show = !cat || card.dataset.cat === cat;
          card.style.display = show ? '' : 'none';
          if (show) visible++;
        });
        empty.style.display = visible ? 'none' : '';
      });
    });
  })();
</script>

<script src="/script.js?v=11" defer></script>
<script>var PAGE='blog', SLUG=null;</script>
<script src="/api/tracker.js" defer></script>
</body>
</html>
