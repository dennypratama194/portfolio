<?php
require __DIR__ . '/api/db.php';
require_once __DIR__ . '/api/showcase.php';
$category_slug = is_string($_GET['category'] ?? '') ? trim($_GET['category'] ?? '') : '';
$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);
if (!$page || $page < 1 || ($category_slug !== '' && !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $category_slug))) {
    include __DIR__ . '/404.php'; exit;
}
$category = null; $items = []; $categories = []; $total = 0; $pages = 1; $unavailable = false;
try {
    $categories = showcaseQuery($pdo, 'SELECT id,name,slug FROM showcase_categories ORDER BY sort_order,name')->fetchAll();
    if ($category_slug !== '') {
        foreach ($categories as $candidate) if ($candidate['slug'] === $category_slug) $category = $candidate;
        if (!$category) { include __DIR__ . '/404.php'; exit; }
    }
    $where = 'is_published=1' . ($category ? ' AND category_id=?' : '');
    $total = (int)showcaseQuery($pdo, 'SELECT COUNT(*) FROM showcase_projects WHERE ' . $where, $category ? [(int)$category['id']] : [])->fetchColumn();
    $pages = max(1, (int)ceil($total / 12));
    if ($page > $pages) { include __DIR__ . '/404.php'; exit; }
    $items = showcaseCards($pdo, $category ? (int)$category['id'] : null, 12, ($page - 1) * 12);
} catch (PDOException $e) {
    error_log('Showcase listing: ' . $e->getMessage());
    http_response_code(503); header('Retry-After: 3600'); $unavailable = true;
}
$title = ($category ? $category['name'] . ' — ' : '') . 'Design Showcase — Denny Pratama' . ($page > 1 ? ' · Page ' . $page : '');
$description = $category ? $category['name'] . ' designs and visual explorations by Denny Pratama. Browse the archive of interfaces, ideas and experiments.' : 'A curated archive of UI/UX design, web interfaces, dashboards and digital product experiments by Denny Pratama. Individual ideas, explored through design.';
$canonical = 'https://dennypratama.com' . showcaseUrl($category_slug, $page);
$og_image = 'https://dennypratama.com/assets/logo.png';
$page_css = '/css/showcase.css?v=3';
$shot_heading_level = 2;
$jsonld = json_encode(['@context'=>'https://schema.org','@type'=>'CollectionPage','name'=>$title,'url'=>$canonical,'description'=>$description,'author'=>['@type'=>'Person','name'=>'Denny Pratama'],
    'mainEntity'=>['@type'=>'ItemList','itemListElement'=>array_map(function ($shot, $index) use ($page) { return ['@type'=>'ListItem','position'=>($page-1)*12+$index+1,'url'=>'https://dennypratama.com/showcase/' . rawurlencode($shot['slug'])]; }, $items, array_keys($items))]], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>
<!DOCTYPE html><html lang="en"><head><?php include __DIR__ . '/partials/head.php'; ?>
<?php if ($unavailable): ?><meta name="robots" content="noindex"><?php endif; ?>
<?php if ($page > 1): ?><link rel="prev" href="<?= escHtml('https://dennypratama.com' . showcaseUrl($category_slug,$page-1)) ?>"><?php endif; ?>
<?php if ($page < $pages): ?><link rel="next" href="<?= escHtml('https://dennypratama.com' . showcaseUrl($category_slug,$page+1)) ?>"><?php endif; ?>
</head><body><?php include __DIR__ . '/partials/nav.php'; ?>
<main id="main-content" class="sc-page">
  <section class="sc-hero"><p class="sc-eyebrow">The design archive</p><h1>Ideas, made visible.</h1><div class="sc-hero-bottom"><p>Interfaces, experiments, and details worth keeping.<br>A collection of my work, one design at a time.</p><a class="sc-text-link" href="/case-studies">Looking for the full story? Case studies ↗</a></div></section>
  <section class="sc-archive" aria-label="Design showcase">
    <div class="sc-toolbar"><span class="sc-count"><?= $total ?> <?= $total === 1 ? 'design' : 'designs' ?></span></div>
    <?php if (!$items): ?><div class="sc-empty"><h2><?= $unavailable ? 'The archive is taking a moment.' : ($category ? 'More to explore soon.' : 'A new archive is on its way.') ?></h2><p><?= $unavailable ? 'Please check back shortly.' : ($category ? 'No designs in this category yet. Explore the rest of the collection.' : 'Designs and experiments will appear here as they are published.') ?></p><?php if ($category): ?><a class="btn btn-secondary" href="/showcase">Explore all work</a><?php endif; ?></div>
    <?php else: ?><div class="sc-grid"><?php foreach ($items as $i=>$shot): $shot_eager = $i === 0; include __DIR__ . '/partials/showcase-card.php'; endforeach; ?></div><?php endif; ?>
    <?php if ($pages > 1): ?><nav class="sc-pagination" aria-label="Showcase pagination"><?php if ($page > 1): ?><a class="btn btn-secondary" href="<?= escHtml(showcaseUrl($category_slug,$page-1)) ?>">← Previous</a><?php endif; ?><span>Page <?= $page ?> of <?= $pages ?></span><?php if ($page < $pages): ?><a class="btn btn-secondary" href="<?= escHtml(showcaseUrl($category_slug,$page+1)) ?>">Next →</a><?php endif; ?></nav><?php endif; ?>
  </section>
</main><?php include __DIR__ . '/partials/modal.php'; include __DIR__ . '/partials/footer.php'; ?><script src="/script.js?v=32" defer></script></body></html>
