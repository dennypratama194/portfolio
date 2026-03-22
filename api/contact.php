<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://dennypratama.com');

// ── KEYS ──────────────────────────────────────────────
define('RECAPTCHA_SECRET', 'YOUR_SECRET_KEY');
define('WEB3FORMS_KEY',    '89b01a8a-31ae-4672-a5de-53c2c8d834bd');
define('RECAPTCHA_THRESHOLD', 0.5);
// ──────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
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

// Forward to Web3Forms
$payload = json_encode([
    'access_key' => WEB3FORMS_KEY,
    'subject'    => 'New project enquiry from ' . $name,
    'name'       => $name,
    'email'      => $email,
    'enquiry'    => $enquiry,
]);

$ctx = stream_context_create([
    'http' => [
        'method'  => 'POST',
        'header'  => "Content-Type: application/json\r\nAccept: application/json\r\n",
        'content' => $payload,
        'timeout' => 10,
    ]
]);

$response = @file_get_contents('https://api.web3forms.com/submit', false, $ctx);
$result   = $response ? json_decode($response, true) : null;

if ($result && $result['success']) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Submission failed, please try again']);
}
