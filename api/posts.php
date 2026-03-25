<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require __DIR__ . '/db.php';

$stmt = $pdo->query(
    'SELECT title, slug, excerpt, featured_image, COALESCE(published_at, scheduled_at) AS published_at, category
     FROM posts
     WHERE is_published = 1
        OR (scheduled_at IS NOT NULL AND scheduled_at <= NOW())
     ORDER BY COALESCE(published_at, scheduled_at) DESC'
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
