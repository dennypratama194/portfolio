<?php
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
session_start();
if (!isset($_SESSION['authed'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['image'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No file']);
    exit;
}

$file    = $_FILES['image'];
$allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
$mime    = mime_content_type($file['tmp_name']);

if (!in_array($mime, $allowed)) {
    http_response_code(415);
    echo json_encode(['error' => 'Invalid file type']);
    exit;
}
if ($file['size'] > 5 * 1024 * 1024) {
    http_response_code(413);
    echo json_encode(['error' => 'File too large (max 5 MB)']);
    exit;
}

$ext      = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'jpg';
$filename = 'img_' . uniqid('', true) . '.' . strtolower($ext);
$dest     = __DIR__ . '/uploads/' . $filename;

if (!move_uploaded_file($file['tmp_name'], $dest)) {
    http_response_code(500);
    echo json_encode(['error' => 'Upload failed']);
    exit;
}

echo json_encode(['url' => '/admin/uploads/' . $filename]);
