<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://dennypratama.com');

require_once __DIR__ . '/.secrets.php';
require_once __DIR__ . '/helpers.php';
define('RECAPTCHA_THRESHOLD', 0.5);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

/* ── Rate limiting: max 5 submissions per IP per hour ──
   Uses the shared rateLimit() helper so the bucket is keyed on client_ip()
   (Cloudflare-aware). REMOTE_ADDR is Cloudflare's edge IP here, so the old
   local implementation shared one bucket across every visitor behind the same
   edge node. Same bucket name, same directory, same 1-hour window. */
if (!rateLimit('contact', 5)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Too many requests, please try again later']);
    exit;
}

$data  = json_decode(file_get_contents('php://input'), true);
$token = trim($data['recaptcha_token'] ?? '');
$name  = trim($data['name']    ?? '');
$email = trim($data['email']   ?? '');
$enquiry = trim($data['enquiry'] ?? '');

// Basic validation
if (!$name || !$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

if (!$enquiry) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Message is required']);
    exit;
}

// Verify reCAPTCHA token
$verify = file_get_contents(
    'https://www.google.com/recaptcha/api/siteverify?secret='
    . urlencode(RECAPTCHA_SECRET) . '&response=' . urlencode($token)
);
$rc = json_decode($verify, true);

if (!$rc['success'] || ($rc['score'] ?? 0) < RECAPTCHA_THRESHOLD) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'reCAPTCHA check failed']);
    exit;
}

/* ── Send via Resend ── */
$safe_name    = htmlspecialchars($name,    ENT_QUOTES, 'UTF-8');
$safe_email   = htmlspecialchars($email,   ENT_QUOTES, 'UTF-8');
$safe_enquiry = nl2br(htmlspecialchars($enquiry, ENT_QUOTES, 'UTF-8'));

$html = <<<HTML
<!doctype html>
<html><body style="font-family: -apple-system, Segoe UI, Roboto, sans-serif; background: #ECEAE2; color: #0D0C09; padding: 32px;">
  <table style="max-width: 560px; margin: 0 auto; background: #fff; border: 1px solid rgba(13,12,9,0.1); border-collapse: collapse;">
    <tr><td style="padding: 32px;">
      <p style="font-size: 12px; letter-spacing: 0.14em; text-transform: uppercase; color: #6B6960; margin: 0 0 8px;">New project enquiry</p>
      <h1 style="font-size: 24px; font-weight: 600; margin: 0 0 24px;">From {$safe_name}</h1>
      <table style="width: 100%; font-size: 14px; line-height: 1.6;">
        <tr><td style="padding: 8px 0; color: #6B6960; width: 80px;">Name</td><td style="padding: 8px 0;">{$safe_name}</td></tr>
        <tr><td style="padding: 8px 0; color: #6B6960;">Email</td><td style="padding: 8px 0;"><a href="mailto:{$safe_email}" style="color: #CC2A08;">{$safe_email}</a></td></tr>
      </table>
      <div style="margin-top: 24px; padding-top: 24px; border-top: 1px solid rgba(13,12,9,0.1);">
        <p style="font-size: 12px; letter-spacing: 0.14em; text-transform: uppercase; color: #6B6960; margin: 0 0 12px;">Message</p>
        <div style="font-size: 16px; line-height: 1.7; color: #3A3830;">{$safe_enquiry}</div>
      </div>
    </td></tr>
  </table>
</body></html>
HTML;

$payload = json_encode([
    'from'     => 'Denny Pratama <noreply@dennypratama.com>',
    'to'       => ['dennypratama194@gmail.com'],
    'reply_to' => $email,
    'subject'  => 'New project enquiry from ' . $name,
    'html'     => $html,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$ch = curl_init('https://api.resend.com/emails');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_CONNECTTIMEOUT => 8,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . RESEND_API_KEY,
    ],
]);
$response  = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_err  = curl_error($ch);
curl_close($ch);

if ($http_code >= 200 && $http_code < 300) {
    echo json_encode(['success' => true]);
} else {
    $log_dir = __DIR__ . '/logs';
    if (!is_dir($log_dir)) { @mkdir($log_dir, 0755, true); }
    @file_put_contents($log_dir . '/contact-errors.log',
        '[' . date('c') . '] http=' . $http_code
        . ' curl_err=' . $curl_err
        . "\n",
        FILE_APPEND | LOCK_EX
    );

    $resend_msg = '';
    if ($response) {
        $decoded = json_decode($response, true);
        if (is_array($decoded)) {
            $resend_msg = $decoded['message'] ?? $decoded['name'] ?? '';
        }
    }

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $resend_msg ?: ('Submission failed (HTTP ' . $http_code . ')'),
    ]);
}
