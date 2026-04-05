<?php
require_once __DIR__ . '/api/db.php';

$title       = 'Ebooks — Denny Pratama';
$description = 'Practical ebooks on UI/UX design, development, and AI by Denny Pratama.';

$products = $pdo->query(
    'SELECT id, title, slug, description, price, cover_image,
            (SELECT COUNT(*) FROM ebook_chapters WHERE product_id = ep.id) AS chapter_count
     FROM ebook_products ep
     WHERE is_active = 1
     ORDER BY created_at DESC'
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include 'partials/head.php'; ?>
</head>
<body>

<?php include 'partials/nav.php'; ?>

<main>
  <!-- ── HERO ── -->
  <section class="blog-hero">
    <div class="blog-hero-eyebrow">Ebooks</div>
    <h1 class="blog-hero-title">Practical guides,<br>no fluff.</h1>
  </section>

  <!-- ── EBOOK GRID ── -->
  <?php if (empty($products)): ?>
    <div class="blog-empty" style="padding:80px var(--pad-x);text-align:center;color:var(--ink-3);font-size:15px;">
      No ebooks available yet. Check back soon.
    </div>
  <?php else: ?>
  <div class="eb-list-grid">
    <?php foreach ($products as $p): ?>
    <a class="eb-list-card" href="/ebook/<?= rawurlencode($p['slug']) ?>">
      <div class="eb-list-cover">
        <?php if ($p['cover_image']): ?>
          <img src="<?= htmlspecialchars($p['cover_image']) ?>" alt="<?= htmlspecialchars($p['title']) ?>" loading="lazy"/>
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

<style>
.eb-list-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 0;
  margin: 48px var(--pad-x) 80px;
}

.eb-list-card {
  display: flex;
  flex-direction: column;
  padding: 40px 32px;
  border-right: 1px solid var(--border);
  border-bottom: 1px solid var(--border);
  text-decoration: none;
  transition: background 0.3s;
}

.eb-list-card:nth-child(3n) { border-right: none; }

.eb-list-card:hover { background: rgba(232,50,10,0.025); }

.eb-list-cover {
  width: 100%;
  aspect-ratio: 3/4;
  max-height: 280px;
  overflow: hidden;
  margin-bottom: 28px;
  background: var(--border);
  display: flex;
  align-items: center;
  justify-content: center;
}

.eb-list-cover img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.eb-list-cover-placeholder {
  width: 100%;
  height: 100%;
  background: var(--border);
  display: flex;
  align-items: center;
  justify-content: center;
  font-family: 'Instrument Serif', serif;
  font-size: 64px;
  color: var(--ink-3);
}

.eb-list-meta {
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: var(--ink-3);
  margin-bottom: 12px;
}

.eb-list-title {
  font-family: 'Instrument Serif', serif;
  font-style: italic;
  font-size: clamp(22px, 2.2vw, 28px);
  font-weight: 400;
  letter-spacing: -0.02em;
  line-height: 1.15;
  color: var(--ink);
  margin: 0 0 12px;
}

.eb-list-desc {
  font-size: 14px;
  font-weight: 300;
  line-height: 1.7;
  color: var(--ink-2);
  margin: 0 0 24px;
  flex: 1;
}

.eb-list-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: auto;
  padding-top: 20px;
  border-top: 1px solid var(--border);
}

.eb-list-price {
  font-size: 15px;
  font-weight: 600;
  letter-spacing: -0.01em;
  color: var(--ink);
}

.eb-list-cta {
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  color: var(--red);
  transition: letter-spacing 0.3s;
}

.eb-list-card:hover .eb-list-cta { letter-spacing: 0.14em; }

@media (max-width: 1024px) {
  .eb-list-grid { grid-template-columns: repeat(2, 1fr); }
  .eb-list-card:nth-child(3n) { border-right: 1px solid var(--border); }
  .eb-list-card:nth-child(2n) { border-right: none; }
}

@media (max-width: 768px) {
  .eb-list-grid { grid-template-columns: 1fr; margin: 24px 24px 48px; }
  .eb-list-card { padding: 32px 24px; border-right: none; }
}
</style>

  <script src="/script.js?v=6" defer></script>
  <script>var PAGE='ebooks',SLUG=null;</script>
  <script src="/api/tracker.js" defer></script>
</body>
</html>
