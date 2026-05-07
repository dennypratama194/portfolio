<?php
http_response_code(404);
require __DIR__ . '/api/helpers.php';

$title       = '404 — Page Not Found · Denny Pratama';
$description = 'The page you\'re looking for doesn\'t exist.';
$canonical   = 'https://dennypratama.com/404';
$og_image    = 'https://dennypratama.com/assets/og-image.png';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include 'partials/head.php'; ?>
<meta name="robots" content="noindex, nofollow"/>
</head>
<body>

<?php include 'partials/nav.php'; ?>

<main>
  <section class="error-page">
    <div class="error-page-inner">
      <div class="error-num">404</div>
      <h1 class="error-title">Page not found.</h1>
      <p class="error-desc">The page you're looking for has moved, been deleted, or never existed.</p>
      <div class="error-actions">
        <a class="btn-hero-primary" href="/">Go home</a>
        <a class="btn-hero-ghost" href="/blog">Read the blog</a>
      </div>
    </div>
  </section>
</main>

<?php include 'partials/footer.php'; ?>

<script src="/script.js?v=16" defer></script>
<script>var PAGE='404',SLUG=null;</script>
<script src="/api/tracker.js" defer></script>
</body>
</html>