<?php
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_secure', '1');
session_start();
if (!isset($_SESSION['authed'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

/* CSRF — the uploader (Quill) must send the session token */
if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['image'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No file']);
    exit;
}

require __DIR__ . '/../api/helpers.php';

$file = $_FILES['image'];

if (!isAllowedImage($file['tmp_name'])) {
    http_response_code(415);
    echo json_encode(['error' => 'Invalid file type']);
    exit;
}
if ($file['size'] > 5 * 1024 * 1024) {
    http_response_code(413);
    echo json_encode(['error' => 'File too large (max 5 MB)']);
    exit;
}

/* Derive extension from the validated image type, never the user filename */
$ext_map  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
$ext      = $ext_map[getUploadMime($file['tmp_name'])] ?? 'jpg';
$filename = 'img_' . uniqid('', true) . '.' . $ext;
$dest     = __DIR__ . '/uploads/' . $filename;

if (!move_uploaded_file($file['tmp_name'], $dest)) {
    http_response_code(500);
    echo json_encode(['error' => 'Upload failed']);
    exit;
}

echo json_encode(['url' => '/admin/uploads/' . $filename]);
