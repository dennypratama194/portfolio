<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require __DIR__ . '/db.php';

$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';

if (!$slug) {
    http_response_code(400);
    echo json_encode(['error' => 'slug is required']);
    exit;
}

$stmt = $pdo->prepare(
    'SELECT title, slug, excerpt, featured_image, body, published_at, category
     FROM posts
     WHERE slug = ? AND is_published = 1
     LIMIT 1'
);
$stmt->execute([$slug]);
$post = $stmt->fetch();

if (!$post) {
    http_response_code(404);
    echo json_encode(['error' => 'Post not found']);
    exit;
}

$base = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
$post['featured_image'] = $post['featured_image']
    ? $base . '/admin/uploads/' . $post['featured_image']
    : null;

echo json_encode($post, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
