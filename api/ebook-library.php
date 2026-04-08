<?php
/* ════════════════════════════════════════════════════════════
   POST /api/ebook-library
   Returns all purchases + read tokens for a given email.
   Called by: my-library.php (public, on-page — no email sent)
════════════════════════════════════════════════════════════ */
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error']);
    exit;
}

$raw   = file_get_contents('php://input');
$json  = json_decode($raw, true);
$email = strtolower(trim($json['email'] ?? $_POST['email'] ?? ''));

if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Valid email required']);
    exit;
}

/* ── Rate limiting: 10 requests per email per hour ── */
$rate_dir  = __DIR__ . '/logs/ratelimit';
if (!is_dir($rate_dir)) mkdir($rate_dir, 0755, true);
$rate_file    = $rate_dir . '/lib_' . hash('sha256', $email) . '.json';
$timestamps   = [];
$one_hour_ago = time() - 3600;

if (file_exists($rate_file)) {
    $stored = json_decode(file_get_contents($rate_file), true);
    if (is_array($stored)) {
        $timestamps = array_values(array_filter($stored, fn($t) => $t > $one_hour_ago));
    }
}

if (count($timestamps) >= 10) {
    http_response_code(429);
    echo json_encode(['status' => 'rate_limited']);
    exit;
}

require_once __DIR__ . '/db.php';

$stmt = $pdo->prepare(
    'SELECT pu.token, pu.paid_at,
            ep.title, ep.slug, ep.cover_image
     FROM ebook_purchases pu
     JOIN ebook_products ep ON ep.id = pu.product_id
     WHERE LOWER(pu.email) = ? AND ep.is_active = 1
     ORDER BY pu.paid_at DESC'
);
$stmt->execute([$email]);
$purchases = $stmt->fetchAll();

if (empty($purchases)) {
    echo json_encode(['status' => 'not_found']);
    exit;
}

$timestamps[] = time();
file_put_contents($rate_file, json_encode($timestamps), LOCK_EX);

$items = array_map(fn($pu) => [
    'title'       => $pu['title'],
    'slug'        => $pu['slug'],
    'cover_image' => $pu['cover_image'] ? '/admin/uploads/' . $pu['cover_image'] : null,
    'paid_at'     => $pu['paid_at'],
    'read_url'    => '/read/' . rawurlencode($pu['slug']) . '?t=' . $pu['token'],
], $purchases);

echo json_encode(['status' => 'found', 'purchases' => $items]);
