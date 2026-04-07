<?php
/* ════════════════════════════════════════════════════════════
   POST /api/ebook-recover
   Re-sends magic links for all purchases tied to an email.
   Called by: recover.php (public) and admin/ebook-purchases.php
════════════════════════════════════════════════════════════ */
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

/* ── Parse input — accept JSON body or form POST ── */
$raw   = file_get_contents('php://input');
$json  = json_decode($raw, true);
$email = trim($json['email'] ?? $_POST['email'] ?? '');

if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Valid email required']);
    exit;
}

$email = strtolower($email);

/* ── Rate limiting: max 3 requests per email per hour (file-based) ── */
$rate_dir  = __DIR__ . '/logs/ratelimit';
if (!is_dir($rate_dir)) {
    mkdir($rate_dir, 0755, true);
}
$rate_file = $rate_dir . '/' . hash('sha256', $email) . '.json';

$timestamps   = [];
$one_hour_ago = time() - 3600;

if (file_exists($rate_file)) {
    $stored = json_decode(file_get_contents($rate_file), true);
    if (is_array($stored)) {
        $timestamps = array_values(array_filter($stored, function ($t) use ($one_hour_ago) {
            return $t > $one_hour_ago;
        }));
    }
}

if (count($timestamps) >= 3) {
    http_response_code(429);
    echo json_encode(['status' => 'rate_limited']);
    exit;
}

/* ── Load secrets + DB ── */
require_once __DIR__ . '/db.php';

/* ── Look up all purchases for this email with active products ── */
$stmt = $pdo->prepare(
    'SELECT pu.token, pu.paid_at,
            ep.title AS product_title, ep.slug AS product_slug
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

/* ── Record this request in rate-limit file BEFORE sending ── */
$timestamps[] = time();
file_put_contents($rate_file, json_encode($timestamps), LOCK_EX);

/* ── Build the recovery email HTML ── */
$recover_link = SITE_URL . '/ebook/recover';
$count        = count($purchases);

/* ── Build one block per purchase ── */
$purchase_blocks = '';
foreach ($purchases as $pu) {
    $title_safe  = htmlspecialchars($pu['product_title'], ENT_QUOTES, 'UTF-8');
    $magic_link  = SITE_URL . '/read/' . rawurlencode($pu['product_slug']) . '?t=' . $pu['token'];
    $date_safe   = date('d M Y', strtotime($pu['paid_at']));

    $purchase_blocks .= <<<BLOCK
          <tr>
            <td style="padding:20px 40px 24px;border-bottom:1px solid #F0EEE6;">
              <p style="margin:0 0 4px;font-size:13px;font-weight:600;letter-spacing:-0.01em;color:#0D0C09;">
                {$title_safe}
              </p>
              <p style="margin:0 0 14px;font-size:12px;color:#9E9B93;">Purchased {$date_safe}</p>
              <table cellpadding="0" cellspacing="0" role="presentation">
                <tr>
                  <td style="background:#E8320A;">
                    <a href="{$magic_link}"
                       style="display:inline-block;padding:11px 24px;
                              font-size:13px;font-weight:600;letter-spacing:0.04em;
                              text-transform:uppercase;color:#FFFFFF;text-decoration:none;">
                      Read Now &rarr;
                    </a>
                  </td>
                </tr>
              </table>
            </td>
          </tr>
    BLOCK;
}

$plural  = $count === 1 ? 'link' : 'links';
$heading = $count === 1
    ? 'Here\'s your access link.'
    : 'Here are your ' . $count . ' access links.';

$html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Your ebook access links</title>
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
          <!-- Intro -->
          <tr>
            <td style="padding:32px 40px 24px;border-bottom:1px solid #F0EEE6;">
              <h1 style="margin:0 0 12px;font-size:22px;font-weight:600;
                         letter-spacing:-0.02em;color:#0D0C09;line-height:1.25;">
                {$heading}
              </h1>
              <p style="margin:0;font-size:15px;color:#5A5855;line-height:1.6;">
                We found {$count} {$plural} for <strong style="color:#0D0C09;">{$email}</strong>.
                Each button below opens your personal copy — it's ready to read right now.
              </p>
            </td>
          </tr>
          <!-- Purchase blocks -->
          {$purchase_blocks}
          <!-- Notice -->
          <tr>
            <td style="padding:24px 40px;">
              <table cellpadding="0" cellspacing="0" role="presentation"
                     style="width:100%;background:#F8F7F3;border-left:3px solid #E8320A;">
                <tr>
                  <td style="padding:14px 18px;">
                    <p style="margin:0;font-size:13px;color:#5A5855;line-height:1.55;">
                      <strong style="color:#0D0C09;">Bookmark your links.</strong>
                      Each link is personal — keep this email somewhere safe,
                      or recover again anytime at
                      <a href="{$recover_link}" style="color:#E8320A;text-decoration:none;">{$recover_link}</a>.
                    </p>
                  </td>
                </tr>
              </table>
            </td>
          </tr>
          <!-- Footer -->
          <tr>
            <td style="padding:20px 40px 32px;border-top:1px solid #F0EEE6;">
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

/* ── Send via Resend ── */
$email_payload = json_encode([
    'from'    => 'Denny Pratama <noreply@dennypratama.com>',
    'to'      => [$email],
    'subject' => 'Your ebook access ' . $plural . ' — Denny Pratama',
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
$resend_body = curl_exec($ch);
$email_code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_err    = curl_errno($ch);
curl_close($ch);

if ($curl_err || $email_code < 200 || $email_code >= 300) {
    /* Log the failure for server-side debugging */
    $log_dir = __DIR__ . '/logs';
    if (!is_dir($log_dir)) { mkdir($log_dir, 0755, true); }
    $log_entry = date('c') . ' | http=' . $email_code . ' | curl_err=' . $curl_err
               . ' | resend=' . $resend_body . PHP_EOL;
    file_put_contents($log_dir . '/resend-errors.log', $log_entry, FILE_APPEND | LOCK_EX);

    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to send email. Please try again.']);
    exit;
}

echo json_encode(['status' => 'sent', 'count' => $count]);
