<?php
/* Dynamic sitemap — served at /sitemap.xml via .htaccess rewrite.
   Lists static pages + every published blog post (with lastmod). */
require __DIR__ . '/api/db.php';
header('Content-Type: application/xml; charset=UTF-8');

$base = 'https://dennypratama.com';

/* Static, indexable pages (individual /ebook/{slug} URLs intentionally excluded until launch) */
$urls = [
    ['loc' => $base . '/',                     'changefreq' => 'weekly', 'priority' => '1.0'],
    ['loc' => $base . '/blog',                 'changefreq' => 'weekly', 'priority' => '0.8'],
    ['loc' => $base . '/case-studies',         'changefreq' => 'weekly', 'priority' => '0.8'],
    ['loc' => $base . '/ebooks',               'changefreq' => 'monthly', 'priority' => '0.6'],
    ['loc' => $base . '/privacy-policy',       'changefreq' => 'yearly', 'priority' => '0.3'],
    ['loc' => $base . '/terms-and-conditions', 'changefreq' => 'yearly', 'priority' => '0.3'],
];

/* Published (or due-scheduled) blog posts */
$posts = $pdo->query(
    "SELECT slug, COALESCE(published_at, scheduled_at) AS pub
     FROM posts
     WHERE is_published = 1 OR (scheduled_at IS NOT NULL AND scheduled_at <= NOW())
     ORDER BY pub DESC"
)->fetchAll();

foreach ($posts as $p) {
    if (empty($p['slug'])) continue;
    $urls[] = [
        'loc'        => $base . '/blog/' . rawurlencode($p['slug']),
        'lastmod'    => $p['pub'] ? date('Y-m-d', strtotime($p['pub'])) : null,
        'changefreq' => 'monthly',
        'priority'   => '0.6',
    ];
}

/* Published case studies */
$projects = $pdo->query(
    "SELECT slug FROM projects WHERE is_published = 1 ORDER BY sort_order ASC, created_at DESC"
)->fetchAll();

foreach ($projects as $p) {
    if (empty($p['slug'])) continue;
    $urls[] = [
        'loc'        => $base . '/case-studies/' . rawurlencode($p['slug']),
        'changefreq' => 'monthly',
        'priority'   => '0.6',
    ];
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $u) {
    echo "  <url>\n";
    echo "    <loc>" . htmlspecialchars($u['loc'], ENT_QUOTES) . "</loc>\n";
    if (!empty($u['lastmod'])) echo "    <lastmod>" . $u['lastmod'] . "</lastmod>\n";
    echo "    <changefreq>" . $u['changefreq'] . "</changefreq>\n";
    echo "    <priority>" . $u['priority'] . "</priority>\n";
    echo "  </url>\n";
}
echo '</urlset>' . "\n";
