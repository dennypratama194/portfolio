<?php
require __DIR__ . '/api/db.php';
require __DIR__ . '/api/helpers.php';

$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';

if (!$slug) {
    header('Location: /blog');
    exit;
}

$stmt = $pdo->prepare(
    'SELECT title, slug, excerpt, featured_image, body,
            COALESCE(published_at, scheduled_at) AS published_at, category
     FROM posts
     WHERE slug = ?
       AND (is_published = 1 OR (scheduled_at IS NOT NULL AND scheduled_at <= NOW()))
     LIMIT 1'
);
$stmt->execute([$slug]);
$post = $stmt->fetch();

if (!$post) {
    http_response_code(404);
    header('Location: /blog');
    exit;
}

$featured_image_url = $post['featured_image']
    ? 'https://dennypratama.com/admin/uploads/' . $post['featured_image']
    : null;

$cat_labels = ['uiux' => 'UI/UX', 'development' => 'Development', 'ai' => 'AI'];
$category_label = $post['category'] ? ($cat_labels[$post['category']] ?? $post['category']) : null;

$title       = $post['title'] . ' — Denny Pratama';
$description = $post['excerpt']
    ?: 'Read the latest articles on UI/UX design, development, and AI by Denny Pratama.';
$canonical   = 'https://dennypratama.com/post?slug=' . urlencode($post['slug']);
$og_image    = $featured_image_url ?: 'https://dennypratama.com/assets/og-image.png';
$og_type     = 'article';
$jsonld      = json_encode([
    '@context'      => 'https://schema.org',
    '@type'         => 'BlogPosting',
    'headline'      => $post['title'],
    'description'   => $post['excerpt'],
    'author'        => ['@type' => 'Person', 'name' => 'Denny Pratama'],
    'datePublished' => $post['published_at'],
    'url'           => $canonical,
    'image'         => $og_image,
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
  <article id="post-root">
    <div class="post-hero">
      <div class="post-hero-meta">
        <a class="post-hero-back" href="/blog">← Blog</a>
        <?php if ($category_label): ?>
          <span class="cat-badge"><?= escHtml($category_label) ?></span>
        <?php endif; ?>
        <span><?= escHtml(formatDate($post['published_at'])) ?></span>
      </div>
      <h1 class="post-hero-title"><?= escHtml($post['title']) ?></h1>
    </div>

    <?php if ($featured_image_url): ?>
      <img class="post-featured-img"
           src="<?= escHtml($featured_image_url) ?>"
           alt="<?= escHtml($post['title']) ?>"/>
    <?php endif; ?>

    <div class="post-body"><?= $post['body'] /* admin-only Quill HTML */ ?></div>

    <div class="post-cta">
      <p class="post-cta-label">Enjoyed this? Let's build something.</p>
      <a class="post-cta-btn js-open-modal" href="#">Start a project →</a>
    </div>

    <div class="post-footer">
      <a class="post-footer-back" href="/blog">← All posts</a>
    </div>
  </article>
</main>

<?php include 'partials/modal.php'; ?>
<?php include 'partials/footer.php'; ?>

<script src="/script.js?v=11" defer></script>
<script>var PAGE='post', SLUG=<?= json_encode($post['slug']) ?>;</script>
<script src="/api/tracker.js" defer></script>
</body>
</html>
