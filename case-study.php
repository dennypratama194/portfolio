<?php
require __DIR__ . '/api/db.php';
require __DIR__ . '/api/helpers.php';

$slug = trim($_GET['slug'] ?? '');
if (!$slug) { header('Location: /case-studies'); exit; }

$stmt = $pdo->prepare(
    'SELECT * FROM projects WHERE slug = ? AND is_published = 1'
);
$stmt->execute([$slug]);
$project = $stmt->fetch();

if (!$project) {
    http_response_code(404);
    include __DIR__ . '/404.php';
    exit;
}

/* Adjacent projects for prev / next navigation */
$all = $pdo->query(
    'SELECT slug, title FROM projects WHERE is_published = 1 ORDER BY sort_order ASC, created_at DESC'
)->fetchAll();
$keys = array_column($all, 'slug');
$idx  = array_search($project['slug'], $keys);
$prev = ($idx !== false && $idx > 0) ? $all[$idx - 1] : null;
$next = ($idx !== false && $idx < count($all) - 1) ? $all[$idx + 1] : null;

$tools = array_filter(array_map('trim', explode(',', $project['tools'] ?? '')));

$sections = [
    ['num' => '01', 'label' => 'The Brief',            'body' => $project['s1_body'], 'images' => json_decode($project['s1_images'] ?? '[]', true) ?: [], 'ai_notice' => false],
    ['num' => '02', 'label' => 'AI-Assisted Analysis', 'body' => $project['s2_body'], 'images' => json_decode($project['s2_images'] ?? '[]', true) ?: [], 'ai_notice' => true],
    ['num' => '03', 'label' => 'AI Concepts',          'body' => $project['s3_body'], 'images' => json_decode($project['s3_images'] ?? '[]', true) ?: []],
    ['num' => '04', 'label' => 'My Design Process',    'body' => $project['s4_body'], 'images' => json_decode($project['s4_images'] ?? '[]', true) ?: []],
    ['num' => '05', 'label' => 'Final Design',         'body' => $project['s5_body'], 'images' => json_decode($project['s5_images'] ?? '[]', true) ?: []],
];

$title       = escHtml($project['title']) . ' Case Study — Denny Pratama';
$description = $project['excerpt'] ?: 'A UI/UX design case study by Denny Pratama — from brief and AI-assisted analysis to final Figma delivery.';
$canonical   = 'https://dennypratama.com/case-studies/' . rawurlencode($project['slug']);
$og_image    = $project['cover_image'] ?: 'https://dennypratama.com/assets/logo.png';
$og_type     = 'article';
$page_css    = '/css/case-study.css?v=2';
$jsonld      = json_encode([
    '@context'    => 'https://schema.org',
    '@type'       => 'CreativeWork',
    'name'        => $project['title'],
    'description' => $project['excerpt'] ?: '',
    'url'         => $canonical,
    'image'       => $og_image,
    'author'      => ['@type' => 'Person', 'name' => 'Denny Pratama', 'url' => 'https://dennypratama.com'],
    'creator'     => ['@type' => 'Person', 'name' => 'Denny Pratama'],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include 'partials/head.php'; ?>
</head>
<body>

<?php include 'partials/nav.php'; ?>

<main id="main-content">

  <?php if ($project['cover_image']): ?>
  <div class="csp-cover-wrap">
    <img class="csp-cover"
         src="<?= escHtml($project['cover_image']) ?>"
         alt="<?= escHtml($project['title']) ?>"
         fetchpriority="high"/>
  </div>
  <?php endif; ?>

  <div class="csp-header">
    <div class="csp-meta">
      <?php if ($project['client']): ?><span><?= escHtml($project['client']) ?></span><?php endif; ?>
      <?php if ($project['role']): ?><span><?= escHtml($project['role']) ?></span><?php endif; ?>
      <?php if ($project['year']): ?><span><?= escHtml((string)$project['year']) ?></span><?php endif; ?>
    </div>
    <h1 class="csp-title"><?= escHtml($project['title']) ?></h1>
    <?php if ($project['excerpt']): ?>
      <p class="csp-excerpt"><?= escHtml($project['excerpt']) ?></p>
    <?php endif; ?>
    <?php if ($tools): ?>
    <div class="csp-tools">
      <?php foreach ($tools as $tool): ?>
        <span class="csp-chip"><?= escHtml($tool) ?></span>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <div class="csp-divider"></div>

  <?php foreach ($sections as $sec):
    if (!trim((string)$sec['body']) && empty($sec['images'])) continue;
  ?>
  <section class="csp-section">
    <div class="csp-section-head">
      <span class="csp-section-num"><?= escHtml($sec['num']) ?></span>
      <h2 class="csp-section-title"><?= escHtml($sec['label']) ?></h2>
    </div>

    <?php if (!empty($sec['ai_notice'])): ?>
    <div class="csp-notice">
      <span class="csp-notice-label">AI as a collaborator</span>
      I use AI to accelerate analysis and surface patterns faster — not to replace my design judgment. Every decision you see here was made by me.
    </div>
    <?php endif; ?>

    <?php if (trim((string)$sec['body'])): ?>
    <div class="csp-section-body">
      <?= $sec['body'] ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($sec['images'])): ?>
    <div class="csp-screenshots<?= count($sec['images']) === 1 ? ' csp-screenshots--single' : '' ?>">
      <?php foreach ($sec['images'] as $img): ?>
        <img class="csp-img"
             src="<?= escHtml($img) ?>"
             alt="<?= escHtml($sec['label']) ?>"
             loading="lazy"/>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </section>
  <?php endforeach; ?>

  <div class="csp-divider"></div>

  <nav class="csp-nav" aria-label="Case study navigation">
    <div class="csp-nav-left">
      <a class="csp-nav-back" href="/case-studies">&larr; All Case Studies</a>
    </div>
    <div class="csp-nav-right">
      <?php if ($next): ?>
        <a class="csp-nav-next" href="/case-studies/<?= rawurlencode($next['slug']) ?>">
          <?= escHtml($next['title']) ?> &rarr;
        </a>
      <?php endif; ?>
    </div>
  </nav>

</main>

<?php include 'partials/modal.php'; ?>
<?php include 'partials/footer.php'; ?>
<script src="/script.js?v=24" defer></script>
<script>var PAGE='case-study', SLUG='<?= addslashes($project['slug']) ?>';</script>
<script src="/api/tracker.js?v=1" defer></script>
</body>
</html>
