<?php
/* One-time metadata patch — delete this file immediately after running. */
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
session_start();
if (!isset($_SESSION['authed'])) { http_response_code(403); exit; }
require __DIR__ . '/../api/db.php';

$slug    = 'bulletproof-file-uploads-php-validation-safe-storage-cpanel';
$title   = 'Bulletproof PHP File Uploads: Validation & Safe Storage';
$excerpt = 'Secure PHP file uploads the right way: validate file types, sanitize filenames, and store uploads safely on cPanel. A practical, copy-paste guide.';

$stmt = $pdo->prepare('UPDATE posts SET title = ?, excerpt = ? WHERE slug = ?');
$stmt->execute([$title, $excerpt, $slug]);

header('Content-Type: text/plain; charset=UTF-8');
echo "Rows updated: " . $stmt->rowCount() . "\n";
echo "Slug:    {$slug}\n";
echo "Title:   {$title}\n";
echo "Excerpt: {$excerpt}\n";
echo "\nDone. Delete admin/update-post-meta.php from the server now.\n";
