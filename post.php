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
$jsonld = json_encode([
    '@context' => 'https://schema.org',
    '@graph' => [
        [
            '@type'         => 'BlogPosting',
            'headline'      => $post['title'],
            'description'   => $post['excerpt'],
            'author'        => ['@type' => 'Person', 'name' => 'Denny Pratama', 'url' => 'https://dennypratama.com', 'image' => 'https://dennypratama.com/assets/logo.png'],
            'datePublished' => $post['published_at'],
            'url'           => $canonical,
            'image'         => $og_image,
        ],
        [
            '@type'           => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => 'https://dennypratama.com'],
                ['@type' => 'ListItem', 'position' => 2, 'name' => 'Blog', 'item' => 'https://dennypratama.com/blog'],
                ['@type' => 'ListItem', 'position' => 3, 'name' => $post['title'], 'item' => $canonical],
            ],
        ],
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

/* Reading time: ~200 wpm */
$word_count   = str_word_count(strip_tags($post['body'] ?? ''));
$reading_mins = max(1, (int) round($word_count / 200));
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
      <div class="post-hero-reading-time"><?= $reading_mins ?> min read</div>
    </div>

    <?php if ($featured_image_url): ?>
      <img class="post-featured-img"
           src="<?= escHtml($featured_image_url) ?>"
           alt="<?= escHtml($post['title']) ?>"
           loading="eager"
           fetchpriority="high"
           width="1200" height="630"/>
    <?php endif; ?>

    <div class="post-body"><?= $post['body'] /* admin-only Quill HTML */ ?></div>

    <div class="post-share">
      <span class="post-share-label">Share</span>
      <a class="post-share-link"
         href="https://twitter.com/intent/tweet?url=<?= rawurlencode($canonical) ?>&text=<?= rawurlencode($post['title']) ?>"
         target="_blank" rel="noopener noreferrer">Twitter / X</a>
      <a class="post-share-link"
         href="https://www.linkedin.com/sharing/share-offsite/?url=<?= rawurlencode($canonical) ?>"
         target="_blank" rel="noopener noreferrer">LinkedIn</a>
    </div>

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

<script src="/script.js?v=13" defer></script>
<script>var PAGE='post', SLUG=<?= json_encode($post['slug']) ?>;</script>
<script src="/api/tracker.js" defer></script>
</body>
</html>
