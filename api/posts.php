<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require __DIR__ . '/db.php';

$stmt = $pdo->query(
    'SELECT title, slug, excerpt, featured_image, published_at, category
     FROM posts
     WHERE is_published = 1
     ORDER BY published_at DESC'
);

$posts = $stmt->fetchAll();

/* Prepend full URL path to featured_image filename */
$base = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
foreach ($posts as &$post) {
    $post['featured_image'] = $post['featured_image']
        ? $base . '/admin/uploads/' . $post['featured_image']
        : null;
}
unset($post);

echo json_encode($posts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
