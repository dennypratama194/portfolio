<article class="sc-card">
  <a href="/showcase/<?= rawurlencode($shot['slug']) ?>">
    <div class="sc-card-image"><?= showcaseImage($shot['thumbnail'], $shot['thumbnail_alt'], true, $shot_eager ?? false) ?></div>
    <div class="sc-card-heading"><?php if (($shot_heading_level ?? 3) === 2): ?><h2><?= escHtml($shot['title']) ?></h2><?php else: ?><h3><?= escHtml($shot['title']) ?></h3><?php endif; ?><span aria-hidden="true">↗</span></div>
    <p class="sc-card-meta"><?= escHtml($shot['category_name'] ?? 'Design exploration') ?><?php if ($shot['year']): ?> · <?= (int)$shot['year'] ?><?php endif; ?></p>
  </a>
</article>
