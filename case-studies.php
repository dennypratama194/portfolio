<?php
require __DIR__ . '/api/db.php';
require __DIR__ . '/api/helpers.php';

$stmt = $pdo->query(
    "SELECT title, slug, excerpt, cover_image, client, year, tools
     FROM projects
     WHERE is_published = 1
     ORDER BY sort_order ASC, created_at DESC"
);
$projects = $stmt->fetchAll();

$title       = 'Case Studies — Denny Pratama';
$description = 'A behind-the-scenes look at how Denny Pratama approaches design projects — from brief and AI-assisted analysis to Figma craft and final delivery.';
$canonical   = 'https://dennypratama.com/case-studies';
$og_image    = 'https://dennypratama.com/assets/logo.png';
$page_css    = '/css/case-studies.css?v=1';
$jsonld      = json_encode([
    '@context' => 'https://schema.org',
    '@type'    => 'CollectionPage',
    'name'     => 'Case Studies — Denny Pratama',
    'url'      => 'https://dennypratama.com/case-studies',
    'author'   => ['@type' => 'Person', 'name' => 'Denny Pratama', 'url' => 'https://dennypratama.com'],
], JSON_UNESCAPED_SLASHES);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include 'partials/head.php'; ?>
</head>
<body>

<?php include 'partials/nav.php'; ?>

<main id="main-content">

  <section class="cs-hero">
    <div class="cs-hero-eyebrow">Case Studies</div>
    <h1 class="cs-hero-title">Process &amp; craft.</h1>
    <p class="cs-hero-sub">A behind-the-scenes look at how I work — from brief to final delivery, with AI as a collaborator, not a replacement.</p>
  </section>

  <div class="cs-grid">
    <?php if (empty($projects)): ?>
      <div class="cs-empty">No case studies published yet — check back soon.</div>
    <?php else: foreach ($projects as $p):
      $tools = array_filter(array_map('trim', explode(',', $p['tools'] ?? '')));
    ?>
      <a class="cs-card" href="/case-studies/<?= rawurlencode($p['slug']) ?>">
        <?php if ($p['cover_image']): ?>
          <img class="cs-card-cover"
               src="<?= escHtml($p['cover_image']) ?>"
               alt="<?= escHtml($p['title']) ?>"
               loading="lazy"/>
        <?php else: ?>
          <div class="cs-card-cover cs-card-cover--empty"></div>
        <?php endif; ?>
        <div class="cs-card-body">
          <div class="cs-card-eyebrow">
            <?= escHtml($p['client'] ?: 'Project') ?>
            <?php if ($p['year']): ?>&ensp;·&ensp;<?= escHtml((string)$p['year']) ?><?php endif; ?>
          </div>
          <div class="cs-card-title"><?= escHtml($p['title']) ?></div>
          <?php if ($p['excerpt']): ?>
            <div class="cs-card-excerpt"><?= escHtml($p['excerpt']) ?></div>
          <?php endif; ?>
          <?php if ($tools): ?>
          <div class="cs-card-tools">
            <?php foreach ($tools as $tool): ?>
              <span class="cs-card-chip"><?= escHtml($tool) ?></span>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
          <div class="cs-card-cta">View Case Study &rarr;</div>
        </div>
      </a>
    <?php endforeach; endif; ?>
  </div>

</main>

<?php include 'partials/modal.php'; ?>
<?php include 'partials/footer.php'; ?>
<script src="/script.js?v=25" defer></script>
<script>var PAGE='case-studies', SLUG=null;</script>
<script src="/api/tracker.js?v=1" defer></script>
</body>
</html>
