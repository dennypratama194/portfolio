<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false]);
    exit;
}

/* ── Parse input ── */
$input = json_decode(file_get_contents('php://input'), true);
$page  = $input['page'] ?? '';
$slug  = isset($input['slug']) ? trim((string)$input['slug']) : null;

if (!in_array($page, ['home', 'blog', 'post'], true)) {
    echo json_encode(['ok' => false]);
    exit;
}
if ($page !== 'post') $slug = null;
if ($slug) $slug = substr($slug, 0, 255);

/* ── Bot filter ── */
$ua   = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
$bots = ['bot','crawl','spider','slurp','facebook','twitter','linkedinbot',
         'whatsapp','telegram','curl','wget','python','go-http','dotbot',
         'ahrefsbot','semrushbot','mj12bot','dataforseo','yandexbot',
         'bingpreview','googleweblight','lighthouse','headlesschrome'];
foreach ($bots as $b) {
    if (str_contains($ua, $b)) {
        echo json_encode(['ok' => true, 'skipped' => 'bot']);
        exit;
    }
}

/* ── IP hash (pepper keeps hashes irreversible even if DB is dumped) ── */
$ip = $_SERVER['HTTP_X_FORWARDED_FOR']
    ?? $_SERVER['HTTP_CF_CONNECTING_IP']
    ?? $_SERVER['REMOTE_ADDR']
    ?? '';
$ip      = trim(explode(',', $ip)[0]);
$ip_hash = hash('sha256', $ip . 'dp-portfolio-pepper-2026');

/* ── Rate-limit: skip if same view within 30 min ── */
require __DIR__ . '/db.php';

$cutoff = date('Y-m-d H:i:s', time() - 1800);

if ($slug) {
    $chk = $pdo->prepare(
        'SELECT COUNT(*) FROM page_views
         WHERE ip_hash = ? AND page_type = ? AND post_slug = ? AND viewed_at > ?'
    );
    $chk->execute([$ip_hash, $page, $slug, $cutoff]);
} else {
    $chk = $pdo->prepare(
        'SELECT COUNT(*) FROM page_views
         WHERE ip_hash = ? AND page_type = ? AND post_slug IS NULL AND viewed_at > ?'
    );
    $chk->execute([$ip_hash, $page, $cutoff]);
}

if ($chk->fetchColumn() > 0) {
    echo json_encode(['ok' => true, 'skipped' => 'rate_limit']);
    exit;
}

/* ── Insert ── */
$referrer = substr($_SERVER['HTTP_REFERER'] ?? '', 0, 500) ?: null;

try {
    $pdo->prepare(
        'INSERT INTO page_views (page_type, post_slug, ip_hash, referrer, viewed_at)
         VALUES (?, ?, ?, ?, NOW())'
    )->execute([$page, $slug, $ip_hash, $referrer]);

    echo json_encode(['ok' => true]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false]);
}
