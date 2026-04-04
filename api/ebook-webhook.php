<?php
/* ════════════════════════════════════════════════════════════
   POST /api/ebook-webhook
   Receives Xendit payment status callbacks.
   Configure this URL in Xendit Dashboard → Webhooks.
════════════════════════════════════════════════════════════ */

/* ── Helpers ── */
function wlog(string $level, string $message): void {
    $dir = __DIR__ . '/logs';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    file_put_contents(
        $dir . '/webhook.log',
        '[' . date('Y-m-d H:i:s') . '] [' . $level . '] ' . $message . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
}

function wok(): never {
    echo json_encode(['status' => 'ok']);
    exit;
}

/* ── Only accept POST ── */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

/* ── Load secrets (before DB so token check is cheap) ── */
require_once __DIR__ . '/.secrets.php';

/* ── Verify Xendit callback token ── */
$callback_token = $_SERVER['HTTP_X_CALLBACK_TOKEN'] ?? '';
if (!hash_equals(XENDIT_WEBHOOK_TOKEN, $callback_token)) {
    wlog('WARN', 'rejected — invalid callback token');
    http_response_code(403);
    exit;
}

/* ── Parse body ── */
$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);

if (!is_array($body)) {
    wlog('ERR', 'rejected — unparseable body');
    http_response_code(400);
    exit;
}

$status      = $body['status']      ?? '';
$external_id = $body['external_id'] ?? '';
$invoice_id  = $body['id']          ?? '';
$payer_email = $body['payer_email'] ?? '';

wlog('RECV', 'status=' . $status . ' external_id=' . $external_id);

/* ── Ignore non-PAID events (return 200 so Xendit stops retrying) ── */
if ($status !== 'PAID') {
    wok();
}

/* ── Validate required fields ── */
if (!$external_id || !$invoice_id || !$payer_email) {
    wlog('ERR', 'missing required fields — external_id=' . $external_id);
    http_response_code(400);
    exit;
}

/* ── Parse product slug from external_id ──
   Format: "ebook-{slug}-{10-digit-timestamp}-{8-hex-chars}"
   Use a greedy capture so slugs with hyphens work correctly.  ── */
if (!preg_match('/^ebook-(.+)-\d{10}-[a-f0-9]{8}$/', $external_id, $m)) {
    wlog('ERR', 'unrecognised external_id format: ' . $external_id);
    http_response_code(400);
    exit;
}
$product_slug = $m[1];

/* ── Open DB ── */
require_once __DIR__ . '/db.php'; /* require_once; .secrets.php already loaded */

/* ── Idempotency: skip if already processed ──
   Xendit can fire duplicate webhooks for the same payment.  ── */
$dupe = $pdo->prepare('SELECT id FROM ebook_purchases WHERE xendit_invoice_id = ?');
$dupe->execute([$invoice_id]);
if ($dupe->fetch()) {
    wlog('SKIP', 'already processed — invoice_id=' . $invoice_id);
    wok();
}

/* ── Look up active product by slug ── */
$prod_stmt = $pdo->prepare('SELECT * FROM ebook_products WHERE slug = ? AND is_active = 1');
$prod_stmt->execute([$product_slug]);
$product = $prod_stmt->fetch();

if (!$product) {
    wlog('ERR', 'product not found or inactive — slug=' . $product_slug);
    /* Still return 200 so Xendit doesn't retry endlessly */
    wok();
}

/* ── Generate secure access token ── */
$token = bin2hex(random_bytes(32)); /* 64-char hex, fits VARCHAR(64) */

/* ── Insert purchase record ── */
$pdo->prepare(
    'INSERT INTO ebook_purchases (product_id, email, token, xendit_invoice_id, paid_at)
     VALUES (?, ?, ?, ?, NOW())'
)->execute([$product['id'], $payer_email, $token, $invoice_id]);

wlog('OK', 'purchase recorded — email=' . $payer_email . ' product=' . $product_slug
    . ' token=' . substr($token, 0, 8) . '…');

/* ── Build magic link ── */
$magic_link   = SITE_URL . '/read/' . rawurlencode($product_slug) . '?t=' . $token;
$recover_link = SITE_URL . '/ebook/recover';

/* ── Build email HTML ── */
$title_safe = htmlspecialchars($product['title'], ENT_QUOTES, 'UTF-8');
$html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Your access link</title>
</head>
<body style="margin:0;padding:0;background:#F5F4F0;font-family:'Inter',Helvetica,Arial,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" role="presentation"
         style="background:#F5F4F0;padding:48px 16px;">
    <tr>
      <td align="center">
        <table width="100%" cellpadding="0" cellspacing="0" role="presentation"
               style="max-width:520px;background:#FFFFFF;border:1px solid #E8E6DE;">
          <!-- Header -->
          <tr>
            <td style="padding:36px 40px 28px;border-bottom:1px solid #F0EEE6;">
              <p style="margin:0;font-size:12px;letter-spacing:0.12em;text-transform:uppercase;
                        color:#9E9B93;">Denny Pratama</p>
            </td>
          </tr>
          <!-- Body -->
          <tr>
            <td style="padding:36px 40px 0;">
              <h1 style="margin:0 0 12px;font-size:24px;font-weight:600;
                         letter-spacing:-0.02em;color:#0D0C09;line-height:1.25;">
                Your access link is ready.
              </h1>
              <p style="margin:0 0 28px;font-size:15px;color:#5A5855;line-height:1.6;">
                Thanks for purchasing <strong style="color:#0D0C09;">{$title_safe}</strong>.
                Click the button below to start reading — it opens immediately.
              </p>
              <!-- CTA button -->
              <table cellpadding="0" cellspacing="0" role="presentation" style="margin-bottom:28px;">
                <tr>
                  <td style="background:#E8320A;">
                    <a href="{$magic_link}"
                       style="display:inline-block;padding:14px 32px;
                              font-size:14px;font-weight:600;letter-spacing:0.04em;
                              text-transform:uppercase;color:#FFFFFF;text-decoration:none;">
                      Read Now &rarr;
                    </a>
                  </td>
                </tr>
              </table>
              <!-- Notice -->
              <table cellpadding="0" cellspacing="0" role="presentation"
                     style="width:100%;background:#F8F7F3;border-left:3px solid #E8320A;
                            margin-bottom:28px;">
                <tr>
                  <td style="padding:14px 18px;">
                    <p style="margin:0;font-size:13px;color:#5A5855;line-height:1.55;">
                      <strong style="color:#0D0C09;">Bookmark that link.</strong>
                      It's your permanent access to the ebook — no account needed.
                      Keep this email or save the link somewhere safe.
                    </p>
                  </td>
                </tr>
              </table>
              <p style="margin:0 0 28px;font-size:13px;color:#9E9B93;line-height:1.6;">
                Lost this email later? Recover all your links at<br/>
                <a href="{$recover_link}" style="color:#E8320A;text-decoration:none;">{$recover_link}</a>
              </p>
            </td>
          </tr>
          <!-- Footer -->
          <tr>
            <td style="padding:20px 40px 32px;border-top:1px solid #F0EEE6;margin-top:28px;">
              <p style="margin:0;font-size:11px;color:#C4C2BA;letter-spacing:0.06em;
                        text-transform:uppercase;">&copy; Denny Pratama</p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;

/* ── Send email via Resend ── */
$email_payload = json_encode([
    'from'    => 'Denny Pratama <noreply@dennypratama.com>',
    'to'      => [$payer_email],
    'subject' => 'Your access link — ' . $product['title'],
    'html'    => $html,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$ch = curl_init('https://api.resend.com/emails');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $email_payload,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . RESEND_API_KEY,
    ],
]);
$email_resp = curl_exec($ch);
$email_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$email_err  = curl_errno($ch);
curl_close($ch);

if ($email_err || $email_code < 200 || $email_code >= 300) {
    /* Purchase is recorded — email failure is non-fatal. Admin can resend via dashboard. */
    wlog('EMAIL_FAIL', 'to=' . $payer_email . ' http=' . $email_code . ' curl_err=' . $email_err);
} else {
    wlog('EMAIL_OK', 'sent to ' . $payer_email);
}

/* ── Respond 200 so Xendit does not retry ── */
http_response_code(200);
header('Content-Type: application/json');
wok();
