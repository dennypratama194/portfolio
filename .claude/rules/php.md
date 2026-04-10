# PHP Patterns

## Public page structure
Every public page must set these variables before including `head.php`:
```php
<?php
$title       = 'Page Title — Denny Pratama';   // unique, under 60 chars
$description = 'Page description...';           // 150–160 chars
$canonical   = 'https://dennypratama.com/slug'; // full URL, no trailing slash
$og_image    = 'https://dennypratama.com/assets/og-image.jpg'; // absolute URL
$jsonld      = json_encode([...]);              // optional structured data
?>
<!DOCTYPE html>
<html lang="en">
<head><?php include 'partials/head.php'; ?></head>
<body>
<?php include 'partials/nav.php'; ?>
<main><!-- content --></main>
<?php include 'partials/modal.php'; ?>
<?php include 'partials/footer.php'; ?>
<script src="/script.js?v=6" defer></script>
<script>var PAGE = 'pagetype', SLUG = null;</script>
<script src="/api/tracker.js" defer></script>
</body>
```

## Admin page boilerplate
Every admin page starts with this exact block:
```php
<?php
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
session_start();
if (!isset($_SESSION['authed'])) { header('Location: /admin/login'); exit; }
require __DIR__ . '/../api/db.php';
$_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
```

## Shared helpers — `api/helpers.php`
Never redefine `escHtml()`, `formatDate()`, or `isAllowedImage()` inline. Always require:
```php
require __DIR__ . '/api/helpers.php';       // from project root
require __DIR__ . '/../api/helpers.php';    // from admin/ or partials/
```

## Database access
Always prepared statements — never interpolate user input into SQL:
```php
// ✅ correct
$stmt = $pdo->prepare('SELECT * FROM posts WHERE slug = ?');
$stmt->execute([$slug]);
$post = $stmt->fetch();

// ❌ never
$pdo->query("SELECT * FROM posts WHERE slug = '$slug'");
```

## Output escaping
```php
// ✅ correct
echo escHtml($post['title']);

// ❌ never
echo $_GET['slug'];
echo $post['title']; // without escaping
```
Exception: post/chapter body HTML from Quill editor (admin-only input — acceptable).
