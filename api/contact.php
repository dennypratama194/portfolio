<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://dennypratama.com');

require_once __DIR__ . '/.secrets.php';
define('RECAPTCHA_THRESHOLD', 0.5);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

/* ── Rate limiting: max 5 submissions per IP per hour ── */
$rate_dir = __DIR__ . '/logs/ratelimit';
if (!is_dir($rate_dir)) {
    mkdir($rate_dir, 0755, true);
}
$ip_key    = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? 'unknown');
$rate_file = $rate_dir . '/contact_' . $ip_key . '.json';
$one_hour_ago = time() - 3600;
$timestamps   = [];

if (file_exists($rate_file)) {
    $stored = json_decode(file_get_contents($rate_file), true);
    if (is_array($stored)) {
        $timestamps = array_values(array_filter($stored, fn($t) => $t > $one_hour_ago));
    }
}
if (count($timestamps) >= 5) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Too many requests, please try again later']);
    exit;
}
$timestamps[] = time();
file_put_contents($rate_file, json_encode($timestamps), LOCK_EX);

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

// Forward to Web3Forms — prefer cURL (more reliable on shared hosting)
$payload = json_encode([
    'access_key' => WEB3FORMS_KEY,
    'subject'    => 'New project enquiry from ' . $name,
    'name'       => $name,
    'email'      => $email,
    'message'    => $enquiry,
    'from_name'  => 'dennypratama.com contact form',
]);

$response  = null;
$transport = 'none';
$curl_err  = '';

if (function_exists('curl_init')) {
    $transport = 'curl';
    $ch = curl_init('https://api.web3forms.com/submit');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CONNECTTIMEOUT => 8,
    ]);
    $response   = curl_exec($ch);
    $http_code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err   = curl_error($ch);
    curl_close($ch);
} elseif (ini_get('allow_url_fopen')) {
    $transport = 'fopen';
    $ctx = stream_context_create([
        'http' => [
            'method'        => 'POST',
            'header'        => "Content-Type: application/json\r\nAccept: application/json\r\n",
            'content'       => $payload,
            'timeout'       => 10,
            'ignore_errors' => true,
        ]
    ]);
    $response  = @file_get_contents('https://api.web3forms.com/submit', false, $ctx);
    $http_code = 0;
    if (isset($http_response_header[0]) && preg_match('#\s(\d{3})\s#', $http_response_header[0], $m)) {
        $http_code = (int)$m[1];
    }
}

$result = $response ? json_decode($response, true) : null;

if ($result && !empty($result['success'])) {
    echo json_encode(['success' => true]);
} else {
    // Log the failure so we can diagnose without exposing details to the client
    $log_dir = __DIR__ . '/logs';
    if (!is_dir($log_dir)) { @mkdir($log_dir, 0755, true); }
    @file_put_contents($log_dir . '/contact-errors.log',
        '[' . date('c') . '] transport=' . $transport
        . ' http=' . ($http_code ?? 'n/a')
        . ' curl_err=' . $curl_err
        . ' response=' . substr((string)$response, 0, 500)
        . "\n",
        FILE_APPEND | LOCK_EX
    );

    http_response_code(500);
    $client_msg = $result['message'] ?? 'Submission failed, please try again';
    echo json_encode(['success' => false, 'message' => $client_msg]);
}
