<?php
/* Shared admin chrome: mobile topbar + sidebar nav.
   Active nav item is auto-detected from the current script — no per-page flag needed.
   Include with: <?php include __DIR__ . '/partials/sidebar.php'; ?> */
$__page = basename($_SERVER['SCRIPT_NAME'] ?? '');
if (in_array($__page, ['index.php', 'edit.php', 'auto-post.php'], true)) {
    $__nav = 'posts';
} elseif (in_array($__page, ['ebooks.php', 'ebook-edit.php', 'ebook-chapters.php', 'ebook-purchases.php'], true)) {
    $__nav = 'ebooks';
} elseif (in_array($__page, ['projects.php', 'project-edit.php'], true)) {
    $__nav = 'projects';
} elseif ($__page === 'change-password.php') {
    $__nav = 'password';
} else {
    $__nav = 'dashboard';
}
?>
<div class="mobile-topbar">
  <div class="mobile-topbar-logo"><a href="/" target="_blank" rel="noopener noreferrer" title="View site"><img src="/assets/logo.png" alt="Denny Pratama"/></a></div>
  <button class="mobile-burger" id="mobile-burger" aria-label="Menu"><span></span><span></span><span></span></button>
</div>
<div class="sidebar-overlay" id="sidebar-overlay"></div>

<aside class="sidebar" id="sidebar">
  <a class="sidebar-logo" href="/" target="_blank" rel="noopener noreferrer" title="View site">
    <img src="/assets/logo.png" alt="Denny Pratama" style="height:28px;width:auto;opacity:0.85;"/>
  </a>
  <nav class="sidebar-nav">
    <a class="sidebar-link<?= $__nav === 'dashboard' ? ' active' : '' ?>" href="analytics.php">
      <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
      Dashboard</a>
    <a class="sidebar-link<?= $__nav === 'posts' ? ' active' : '' ?>" href="index.php">
      <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
      Posts</a>
    <a class="sidebar-link<?= $__nav === 'ebooks' ? ' active' : '' ?>" href="ebooks.php">
      <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
      Ebooks</a>
    <a class="sidebar-link<?= $__nav === 'projects' ? ' active' : '' ?>" href="projects.php">
      <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
      Case Studies</a>
    <a class="sidebar-link<?= $__nav === 'password' ? ' active' : '' ?>" href="change-password.php">
      <svg class="sidebar-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
      Change Password</a>
  </nav>
  <div class="sidebar-bottom">
    <button class="theme-toggle" id="theme-toggle">◑ Light mode</button>
    <a class="sidebar-logout" href="logout.php">Sign out</a>
  </div>
</aside>
