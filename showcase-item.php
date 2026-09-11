<?php
require __DIR__ . '/api/db.php';
require_once __DIR__ . '/api/showcase.php';
$slug = is_string($_GET['slug'] ?? '') ? ($_GET['slug'] ?? '') : '';
if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug) || strlen($slug) > 180) { include __DIR__ . '/404.php'; exit; }
try {
    $item = showcaseQuery($pdo, 'SELECT s.*, c.name AS category_name,c.slug AS category_slug,p.title AS case_title,p.slug AS case_slug
        FROM showcase_projects s LEFT JOIN showcase_categories c ON c.id=s.category_id
        LEFT JOIN projects p ON p.id=s.related_case_study_id AND p.is_published=1 WHERE s.slug=? AND s.is_published=1', [$slug])->fetch();
    if (!$item) { include __DIR__ . '/404.php'; exit; }
    $images = showcaseQuery($pdo, 'SELECT media,alt_text,caption FROM showcase_images WHERE showcase_id=? ORDER BY sort_order,id', [(int)$item['id']])->fetchAll();
    $previous = showcaseQuery($pdo, 'SELECT title,slug FROM showcase_projects WHERE is_published=1 AND (sort_order < ? OR (sort_order = ? AND id < ?)) ORDER BY sort_order DESC,id DESC LIMIT 1', [(int)$item['sort_order'],(int)$item['sort_order'],(int)$item['id']])->fetch();
    $next = showcaseQuery($pdo, 'SELECT title,slug FROM showcase_projects WHERE is_published=1 AND (sort_order > ? OR (sort_order = ? AND id > ?)) ORDER BY sort_order ASC,id ASC LIMIT 1', [(int)$item['sort_order'],(int)$item['sort_order'],(int)$item['id']])->fetch();
    $related = showcaseCards($pdo, $item['category_id'] ? (int)$item['category_id'] : null, 3, 0, false, (int)$item['id']);
} catch (PDOException $e) {
    error_log('Showcase detail: ' . $e->getMessage());
    http_response_code(503); header('Retry-After: 3600'); exit('The design archive is temporarily unavailable. Please try again shortly.');
}
$title = $item['title'] . ' — Denny Pratama';
$description = mb_substr($item['short_description'] ?: $item['title'] . ' — a design exploration by Denny Pratama. View the interface, details and design process in this personal visual archive.',0,160);
$canonical = 'https://dennypratama.com/showcase/' . rawurlencode($item['slug']);
$cover = showcaseMedia($item['thumbnail']);
$og_image = $cover ? 'https://dennypratama.com/admin/uploads/showcase/' . end($cover['variants']) : 'https://dennypratama.com/assets/logo.png';
$og_type = 'article'; $page_css = '/css/showcase.css?v=2';
$jsonld = json_encode(['@context'=>'https://schema.org','@type'=>'CreativeWork','name'=>$item['title'],'description'=>$description,'url'=>$canonical,'image'=>$og_image,'dateModified'=>$item['updated_at'],'creator'=>['@type'=>'Person','name'=>'Denny Pratama','url'=>'https://dennypratama.com']], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>
<!DOCTYPE html><html lang="en"><head><?php include __DIR__ . '/partials/head.php'; ?></head><body><?php include __DIR__ . '/partials/nav.php'; ?>
<main id="main-content" class="sc-page">
  <article class="sc-detail">
    <header class="sc-detail-head"><a class="sc-text-link" href="/showcase">← All designs</a><div class="sc-detail-meta"><?php if ($item['category_slug']): ?><a href="<?= escHtml(showcaseUrl($item['category_slug'])) ?>"><?= escHtml($item['category_name']) ?></a><?php endif; ?><?php if ($item['year']): ?><span><?= (int)$item['year'] ?></span><?php endif; ?></div><h1><?= escHtml($item['title']) ?></h1><?php if ($item['short_description']): ?><p class="sc-description"><?= nl2br(escHtml($item['short_description'])) ?></p><?php endif; ?></header>
    <figure class="sc-detail-image"><?= showcaseImage($item['thumbnail'],$item['thumbnail_alt'],false,true) ?></figure>
    <?php foreach ($images as $image): ?><figure class="sc-detail-image"><?= showcaseImage($image['media'],$image['alt_text'],false) ?><?php if ($image['caption']): ?><figcaption><?= escHtml($image['caption']) ?></figcaption><?php endif; ?></figure><?php endforeach; ?>
    <?php if ($item['tools'] || $item['client'] || $item['case_slug']): ?><section class="sc-project-info" aria-label="Project details"><dl><?php if ($item['client']): ?><div><dt>Client</dt><dd><?= escHtml($item['client']) ?></dd></div><?php endif; ?><?php if ($item['tools']): ?><div><dt>Tools</dt><dd><?= escHtml($item['tools']) ?></dd></div><?php endif; ?></dl><?php if ($item['case_slug']): ?><a class="btn btn-secondary" href="/case-studies/<?= rawurlencode($item['case_slug']) ?>">View full case study ↗</a><?php endif; ?></section><?php endif; ?>
  </article>
  <nav class="sc-adjacent" aria-label="Design navigation"><div><?php if ($previous): ?><a href="/showcase/<?= rawurlencode($previous['slug']) ?>"><span>← Previous design</span><?= escHtml($previous['title']) ?></a><?php endif; ?></div><div><?php if ($next): ?><a href="/showcase/<?= rawurlencode($next['slug']) ?>"><span>Next design →</span><?= escHtml($next['title']) ?></a><?php endif; ?></div></nav>
  <?php if ($related): ?><section class="sc-related" aria-labelledby="related-title"><div class="sc-section-head"><h2 id="related-title">Keep exploring.</h2><a class="sc-text-link" href="/showcase">All designs ↗</a></div><div class="sc-grid"><?php foreach ($related as $shot): $shot_eager = false; include __DIR__ . '/partials/showcase-card.php'; endforeach; ?></div></section><?php endif; ?>
</main><?php include __DIR__ . '/partials/modal.php'; include __DIR__ . '/partials/footer.php'; ?><script src="/script.js?v=32" defer></script></body></html>
