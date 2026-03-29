<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

/* ── "end" call: save duration for an existing view ── */
if (isset($input['action']) && $input['action'] === 'end') {
    $view_id  = isset($input['view_id'])  ? (int)$input['view_id']  : 0;
    $duration = isset($input['duration']) ? (int)$input['duration'] : 0;

    if ($view_id > 0 && $duration > 0 && $duration < 86400) {
        require __DIR__ . '/db.php';
        $pdo->prepare('UPDATE page_views SET time_on_page = ? WHERE id = ? AND time_on_page IS NULL')
            ->execute([$duration, $view_id]);
    }
    echo json_encode(['ok' => true]);
    exit;
}

/* ── "start" call: record a page view ── */
$page = $input['page'] ?? '';
$slug = isset($input['slug']) ? trim((string)$input['slug']) : null;

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

/* ── IP hash ── */
$ip = $_SERVER['HTTP_X_FORWARDED_FOR']
    ?? $_SERVER['HTTP_CF_CONNECTING_IP']
    ?? $_SERVER['REMOTE_ADDR']
    ?? '';
$ip      = trim(explode(',', $ip)[0]);
$ip_hash = hash('sha256', $ip . 'dp-portfolio-pepper-2026');

require __DIR__ . '/db.php';

/* ── Rate-limit: skip if same view within 30 min ── */
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

/* ── Insert and return view_id ── */
$referrer = substr($_SERVER['HTTP_REFERER'] ?? '', 0, 500) ?: null;

try {
    $pdo->prepare(
        'INSERT INTO page_views (page_type, post_slug, ip_hash, referrer, viewed_at)
         VALUES (?, ?, ?, ?, NOW())'
    )->execute([$page, $slug, $ip_hash, $referrer]);

    echo json_encode(['ok' => true, 'view_id' => (int)$pdo->lastInsertId()]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false]);
}
