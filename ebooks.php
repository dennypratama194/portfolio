<?php
require_once __DIR__ . '/api/db.php';

$title       = 'Ebooks — Denny Pratama';
$description = 'Practical ebooks on UI/UX design, product development, and AI by Denny Pratama. Hands-on guides to help you build better digital products and grow your skills.';
$canonical   = 'https://dennypratama.com/ebooks';
$og_image    = 'https://dennypratama.com/assets/logo.png';
$jsonld      = json_encode([
    '@context'    => 'https://schema.org',
    '@type'       => 'CollectionPage',
    'name'        => 'Ebooks — Denny Pratama',
    'description' => $description,
    'url'         => $canonical,
    'author'      => ['@type' => 'Person', 'name' => 'Denny Pratama', 'url' => 'https://dennypratama.com'],
]);

$products = $pdo->query(
    'SELECT id, title, slug, description, price, cover_image,
            (SELECT COUNT(*) FROM ebook_chapters WHERE product_id = ep.id) AS chapter_count
     FROM ebook_products ep
     WHERE is_active = 1
     ORDER BY created_at DESC'
)->fetchAll();

$page_css = '/css/ebooks.css?v=1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include 'partials/head.php'; ?>
</head>
<body>

<?php include 'partials/nav.php'; ?>

<main id="main-content">
  <!-- ── HERO ── -->
  <section class="blog-hero">
    <div class="blog-hero-eyebrow">Ebooks</div>
    <h1 class="blog-hero-title">Practical guides,<br>no fluff.</h1>
  </section>

  <!-- ── EBOOK GRID ── -->
  <?php if (empty($products)): ?>
    <div class="blog-empty" style="padding:80px var(--pad-x);text-align:center;color:var(--ink-3);font-size:16px;">
      No ebooks available yet. Check back soon.
    </div>
  <?php else: ?>
  <div class="eb-list-grid">
    <?php foreach ($products as $p): ?>
    <a class="eb-list-card" href="/ebook/<?= rawurlencode($p['slug']) ?>">
      <div class="eb-list-cover">
        <?php if ($p['cover_image']): ?>
          <img src="/admin/uploads/<?= htmlspecialchars($p['cover_image']) ?>" alt="<?= htmlspecialchars($p['title']) ?>" loading="lazy"/>
        <?php else: ?>
          <div class="eb-list-cover-placeholder">
            <span><?= htmlspecialchars(mb_substr($p['title'], 0, 1)) ?></span>
          </div>
        <?php endif; ?>
      </div>
      <div class="eb-list-body">
        <div class="eb-list-meta">
          <?= (int)$p['chapter_count'] ?> chapter<?= $p['chapter_count'] != 1 ? 's' : '' ?>
        </div>
        <h2 class="eb-list-title"><?= htmlspecialchars($p['title']) ?></h2>
        <?php if ($p['description']): ?>
          <p class="eb-list-desc"><?= htmlspecialchars($p['description']) ?></p>
        <?php endif; ?>
        <div class="eb-list-footer">
          <span class="eb-list-price">IDR <?= number_format((int)$p['price'], 0, ',', '.') ?></span>
          <span class="eb-list-cta">Get access →</span>
        </div>
      </div>
    </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</main>

<?php include 'partials/modal.php'; ?>
<?php include 'partials/footer.php'; ?>


  <script src="/script.js?v=24" defer></script>
  <script>var PAGE='ebooks',SLUG=null;</script>
  <script src="/api/tracker.js?v=1" defer></script>
</body>
</html>
