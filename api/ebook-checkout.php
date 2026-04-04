<?php
/* ════════════════════════════════════════════════════════════
   POST /api/ebook-checkout
   Validates buyer, creates a Xendit invoice, redirects to payment.
════════════════════════════════════════════════════════════ */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

/* ── Read inputs first so we can redirect on email error ── */
$product_slug = trim($_POST['product_slug'] ?? '');
$email        = trim($_POST['email']        ?? '');

/* Slug is required to know where to redirect on any error */
if (!$product_slug) {
    http_response_code(400);
    exit('Missing product.');
}

require_once __DIR__ . '/db.php'; /* also loads .secrets.php → defines SITE_URL */

/* ── All errors redirect back to the sales page ── */
function checkout_fail(string $slug, string $code): never {
    header('Location: ' . SITE_URL . '/ebook/' . rawurlencode($slug) . '?error=' . $code);
    exit;
}

/* ── Validate email ── */
if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    checkout_fail($product_slug, 'invalid_email');
}

/* ── Look up active product ── */
$stmt = $pdo->prepare('SELECT * FROM ebook_products WHERE slug = ? AND is_active = 1');
$stmt->execute([$product_slug]);
$product = $stmt->fetch();

if (!$product) {
    http_response_code(404);
    exit('Product not found or not currently available.');
}

/* ── Check for existing purchase ── */
$owned = $pdo->prepare(
    'SELECT id FROM ebook_purchases WHERE email = ? AND product_id = ?'
);
$owned->execute([$email, $product['id']]);

if ($owned->fetch()) {
    /* Buyer already owns this — redirect to "you already own this" state on sales page */
    header('Location: ' . SITE_URL . '/ebook/' . rawurlencode($product_slug) . '?owned=1');
    exit;
}

/* ── Build Xendit invoice payload ── */
/*
 * external_id encodes slug + timestamp + 8 random hex chars.
 * The webhook parser can extract the slug via:
 *   preg_match('/^ebook-(.+)-\d{10}-[a-f0-9]{8}$/', $external_id, $m)
 */
$external_id = 'ebook-' . $product_slug . '-' . time() . '-' . bin2hex(random_bytes(4));

$payload = json_encode([
    'external_id'          => $external_id,
    'amount'               => (int)$product['price'],
    'payer_email'          => $email,
    'description'          => 'Ebook: ' . $product['title'],
    'success_redirect_url' => SITE_URL . '/ebook/' . rawurlencode($product_slug) . '?purchased=1',
    'failure_redirect_url' => SITE_URL . '/ebook/' . rawurlencode($product_slug) . '?failed=1',
    'currency'             => 'IDR',
    'invoice_duration'     => 86400, /* 24 hours */
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

/* ── Call Xendit Invoices API ── */
/*
 * Auth: HTTP Basic with secret key as username, empty password.
 * Header: "Authorization: Basic base64(SECRET_KEY:)"
 */
$ch = curl_init('https://api.xendit.co/v2/invoices');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Basic ' . base64_encode(XENDIT_SECRET_KEY . ':'),
    ],
]);

$response  = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_err  = curl_errno($ch);
curl_close($ch);

/* Network-level failure */
if ($curl_err || $response === false) {
    checkout_fail($product_slug, 'payment');
}

/* Xendit returned a non-success status */
if ($http_code !== 200 && $http_code !== 201) {
    checkout_fail($product_slug, 'payment');
}

$data = json_decode($response, true);

/* Missing invoice URL in response */
if (empty($data['invoice_url'])) {
    checkout_fail($product_slug, 'payment');
}

/* ── Redirect buyer to Xendit-hosted payment page ── */
header('Location: ' . $data['invoice_url']);
exit;
