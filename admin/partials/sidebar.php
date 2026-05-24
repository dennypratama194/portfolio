<?php
/* Shared admin chrome: mobile topbar + sidebar nav.
   Active nav item is auto-detected from the current script — no per-page flag needed.
   Include with: <?php include __DIR__ . '/partials/sidebar.php'; ?> */
$__page = basename($_SERVER['SCRIPT_NAME'] ?? '');
if (in_array($__page, ['index.php', 'edit.php', 'auto-post.php'], true)) {
    $__nav = 'posts';
} elseif (in_array($__page, ['ebooks.php', 'ebook-edit.php', 'ebook-chapters.php', 'ebook-purchases.php'], true)) {
    $__nav = 'ebooks';
} elseif ($__page === 'change-password.php') {
    $__nav = 'password';
} else {
    $__nav = 'dashboard';
}
?>
<div class="mobile-topbar">
  <div class="mobile-topbar-logo"><a href="/" target="_blank" rel="noopener" title="View site"><img src="/assets/logo.png" alt="Denny Pratama"/></a></div>
  <button class="mobile-burger" id="mobile-burger" aria-label="Menu"><span></span><span></span><span></span></button>
</div>
<div class="sidebar-overlay" id="sidebar-overlay"></div>

<aside class="sidebar" id="sidebar">
  <a class="sidebar-logo" href="/" target="_blank" rel="noopener" title="View site">
    <img src="/assets/logo.png" alt="Denny Pratama" style="height:28px;width:auto;opacity:0.85;"/>
  </a>
  <nav class="sidebar-nav">
    <a class="sidebar-link<?= $__nav === 'dashboard' ? ' active' : '' ?>" href="analytics.php">Dashboard</a>
    <a class="sidebar-link<?= $__nav === 'posts' ? ' active' : '' ?>" href="index.php">Posts</a>
    <a class="sidebar-link<?= $__nav === 'ebooks' ? ' active' : '' ?>" href="ebooks.php">Ebooks</a>
    <a class="sidebar-link<?= $__nav === 'password' ? ' active' : '' ?>" href="change-password.php">Change Password</a>
  </nav>
  <div class="sidebar-bottom">
    <button class="theme-toggle" id="theme-toggle">◑ Light mode</button>
    <a class="sidebar-logout" href="logout.php">Sign out</a>
  </div>
</aside>
