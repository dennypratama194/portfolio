<?php
/* Showcase-only queries and media presentation. Shared helpers remain unchanged. */
if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    http_response_code(403); exit;
}
require_once __DIR__ . '/helpers.php';

function showcaseQuery(PDO $pdo, string $sql, array $params = []): PDOStatement {
    $stmt = $pdo->prepare($sql);
    foreach (array_values($params) as $i => $value) {
        $stmt->bindValue($i + 1, $value, $value === null ? PDO::PARAM_NULL : (is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR));
    }
    $stmt->execute();
    return $stmt;
}

function showcaseCards(PDO $pdo, ?int $category = null, int $limit = 12, int $offset = 0, bool $featured = false, ?int $exclude = null): array {
    $where = 's.is_published = 1';
    $params = [];
    if ($category !== null) { $where .= ' AND s.category_id = ?'; $params[] = $category; }
    if ($featured) $where .= ' AND s.is_featured = 1';
    if ($exclude !== null) { $where .= ' AND s.id != ?'; $params[] = $exclude; }
    $params[] = $limit;
    $params[] = $offset;
    return showcaseQuery($pdo, "SELECT s.id, s.title, s.slug, s.thumbnail, s.thumbnail_alt, s.year, c.name AS category_name
        FROM showcase_projects s LEFT JOIN showcase_categories c ON c.id = s.category_id
        WHERE $where ORDER BY s.sort_order ASC, s.id ASC LIMIT ? OFFSET ?", $params)->fetchAll();
}

function showcaseMedia(?string $json): array {
    $media = json_decode($json ?? '', true);
    if (!is_array($media) || !is_string($media['key'] ?? null) || !preg_match('/^[a-f0-9]{32}$/D', $media['key'])) return [];
    if (empty($media['width']) || empty($media['height']) || !is_array($media['variants'] ?? null)) return [];
    if (isset($media['grid_variants']) && !is_array($media['grid_variants'])) return [];
    foreach (array_merge(array_values($media['variants']), array_values($media['grid_variants'] ?? [])) as $filename) {
        if (!is_string($filename) || !preg_match('/^' . $media['key'] . '-(?:grid-)?[0-9]+\.(webp|jpg)$/D', $filename)) return [];
    }
    foreach ($media['variants'] + ($media['grid_variants'] ?? []) as $width => $filename) {
        if (!ctype_digit((string)$width) || (int)$width < 1 || !is_string($filename)
            || !preg_match('/^' . $media['key'] . '-(?:grid-)?[0-9]+\.(webp|jpg)$/D', $filename)) return [];
    }
    return $media;
}

function showcaseImage(?string $json, string $alt, bool $grid = true, bool $eager = false): string {
    $media = showcaseMedia($json);
    if (!$media) return '<div class="sc-missing" role="img" aria-label="Image unavailable">Image coming soon</div>';
    $variants = array_filter($grid ? ($media['grid_variants'] ?? $media['variants']) : $media['variants'], function ($width) use ($grid) { return !$grid || $width <= 800; }, ARRAY_FILTER_USE_KEY);
    $variants = array_filter($variants, function ($filename) { return is_file(__DIR__ . '/../admin/uploads/showcase/' . $filename); });
    if (!$variants) return '<div class="sc-missing">Image unavailable</div>';
    ksort($variants, SORT_NUMERIC);
    $srcset = [];
    foreach ($variants as $width => $file) $srcset[] = '/admin/uploads/showcase/' . $file . ' ' . $width . 'w';
    $sizes = $grid ? '(max-width: 767px) calc(100vw - 48px), (max-width: 1024px) 44vw, 30vw' : '(max-width: 768px) calc(100vw - 48px), (max-width: 1280px) 90vw, 1200px';
    $width = $grid && isset($media['grid_variants']) ? (int)array_key_last($variants) : (int)$media['width'];
    $height = $grid && isset($media['grid_variants']) ? max(1,(int)round($width * 9 / 16)) : (int)$media['height'];
    return '<img src="/admin/uploads/showcase/' . escHtml(end($variants)) . '" srcset="' . escHtml(implode(', ', $srcset))
        . '" sizes="' . $sizes . '" width="' . $width . '" height="' . $height
        . '" alt="' . escHtml($alt) . '" decoding="async" ' . ($eager ? 'loading="eager" fetchpriority="high"' : 'loading="lazy"') . '>';
}

function showcaseUrl(string $category = '', int $page = 1): string {
    $query = [];
    if ($category !== '') $query['category'] = $category;
    if ($page > 1) $query['page'] = $page;
    return '/showcase' . ($query ? '?' . http_build_query($query) : '');
}
