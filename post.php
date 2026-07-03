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
$canonical   = 'https://dennypratama.com/blog/' . rawurlencode($post['slug']);
$og_image    = $featured_image_url ?: 'https://dennypratama.com/assets/logo.png';
$og_type     = 'article';
$pub_iso    = $post['published_at'] ? date('c', strtotime($post['published_at'])) : null;
$word_count = str_word_count(strip_tags($post['body'] ?? ''));

/* Extract FAQ embedded by auto-post (<!-- faq:[...] -->) for FAQPage schema */
$faq_schema = null;
if (preg_match('/<!--\s*faq:(\[.*?\])\s*-->/s', $post['body'] ?? '', $fm)) {
    $faq_items = json_decode($fm[1], true);
    if (is_array($faq_items) && count($faq_items)) {
        $faq_schema = [
            '@type'      => 'FAQPage',
            'mainEntity' => array_map(fn($f) => [
                '@type'          => 'Question',
                'name'           => $f['q'] ?? '',
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a'] ?? ''],
            ], $faq_items),
        ];
    }
}

$jsonld = json_encode([
    '@context' => 'https://schema.org',
    '@graph' => [
        [
            '@type'         => 'BlogPosting',
            'headline'      => $post['title'],
            'description'   => $post['excerpt'],
            'author'        => [
                '@type'  => 'Person',
                'name'   => 'Denny Pratama',
                'url'    => 'https://dennypratama.com',
                'image'  => 'https://dennypratama.com/assets/logo.png',
                'sameAs' => [
                    'https://dribbble.com/dennypratama',
                    'https://www.linkedin.com/in/denny-pratama-740a14151/',
                ],
            ],
            'datePublished' => $pub_iso,
            'dateModified'  => $pub_iso,
            'wordCount'     => $word_count,
            'url'           => $canonical,
            'image'         => $og_image,
            'inLanguage'    => 'en',
            'publisher'     => [
                '@type' => 'Person',
                'name'  => 'Denny Pratama',
                'url'   => 'https://dennypratama.com',
            ],
        ],
        [
            '@type'           => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => 'https://dennypratama.com'],
                ['@type' => 'ListItem', 'position' => 2, 'name' => 'Blog', 'item' => 'https://dennypratama.com/blog'],
                ['@type' => 'ListItem', 'position' => 3, 'name' => $post['title'], 'item' => $canonical],
            ],
        ],
        ...($faq_schema ? [$faq_schema] : []),
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

/* Reading time: ~200 wpm */
$reading_mins = max(1, (int) round($word_count / 200));

/* Build the table of contents and add anchor ids to each <h2> in the body */
$processed = injectHeadingIds($post['body'] ?? '');
$body_html = $processed['html'];
$toc       = $processed['toc'];
$show_toc  = count($toc) >= 2;

/* Related posts: same category first, then most recent, excluding this post */
$related_stmt = $pdo->prepare(
    'SELECT title, slug, excerpt, featured_image, category,
            COALESCE(published_at, scheduled_at) AS published_at
     FROM posts
     WHERE slug != ?
       AND (is_published = 1 OR (scheduled_at IS NOT NULL AND scheduled_at <= NOW()))
     ORDER BY (category <=> ?) DESC, COALESCE(published_at, scheduled_at) DESC
     LIMIT 3'
);
$related_stmt->execute([$post['slug'], $post['category']]);
$related = $related_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include 'partials/head.php'; ?>
</head>
<body>

<?php include 'partials/nav.php'; ?>

<main id="main-content">
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

    <div class="post-layout">
      <?php if ($show_toc): ?>
        <aside class="post-toc" id="post-toc" aria-label="Table of contents">
          <button class="post-toc-toggle" id="post-toc-toggle"
                  aria-expanded="false" aria-controls="post-toc-nav">
            <span>Contents</span>
            <span class="post-toc-chevron" aria-hidden="true"></span>
          </button>
          <div class="post-toc-nav" id="post-toc-nav">
            <?php foreach ($toc as $item): ?>
              <a class="post-toc-link" href="#<?= escHtml($item['id']) ?>"><?= escHtml($item['text']) ?></a>
            <?php endforeach; ?>
          </div>
        </aside>
      <?php endif; ?>
      <div class="post-body"><?= $body_html /* admin-only Quill HTML, h2 ids injected */ ?></div>
    </div>

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

    <?php if ($related): ?>
    <section class="post-related">
      <div class="post-related-head">
        <span class="post-related-eyebrow">Keep reading</span>
        <h2 class="post-related-title">More articles</h2>
      </div>
      <div class="post-related-grid">
        <?php foreach ($related as $rp):
          $r_img_url   = $rp['featured_image'] ? '/admin/uploads/' . $rp['featured_image'] : null;
          $r_cat_label = $rp['category'] ? ($cat_labels[$rp['category']] ?? $rp['category']) : null;
        ?>
          <a class="blog-card" href="/blog/<?= rawurlencode($rp['slug']) ?>">
            <?php if ($r_img_url): ?>
              <img class="blog-card-img" src="<?= escHtml($r_img_url) ?>"
                   alt="<?= escHtml($rp['title']) ?>" loading="lazy"/>
            <?php else: ?>
              <div class="blog-card-img"></div>
            <?php endif; ?>
            <div class="blog-card-head">
              <?php if ($r_cat_label): ?>
                <span class="blog-card-cat"><?= escHtml($r_cat_label) ?></span>
              <?php endif; ?>
              <span class="blog-card-meta"><?= escHtml(formatDate($rp['published_at'])) ?></span>
            </div>
            <div class="blog-card-title"><?= escHtml($rp['title']) ?></div>
            <?php if ($rp['excerpt']): ?>
              <div class="blog-card-excerpt"><?= escHtml($rp['excerpt']) ?></div>
            <?php endif; ?>
            <div class="blog-card-readmore">Read &rarr;</div>
          </a>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

    <div class="post-footer">
      <a class="post-footer-back" href="/blog">← All posts</a>
    </div>
  </article>
</main>

<?php include 'partials/modal.php'; ?>
<?php include 'partials/footer.php'; ?>

<script src="/script.js?v=25" defer></script>
<script>var PAGE='post', SLUG=<?= json_encode($post['slug']) ?>;</script>
<script src="/api/tracker.js?v=1" defer></script>
</body>
</html>
