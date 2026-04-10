# Admin Page Rules

## New page checklist
1. PHP auth + CSRF block at top (see php.md)
2. `<head>`: link `theme.css`, add `noindex` meta — no inline sidebar CSS
3. Mobile topbar + sidebar HTML after `<body>` (copy exactly):

```html
<div class="mobile-topbar">
  <div class="mobile-topbar-logo"><img src="/assets/logo.png" alt="Denny Pratama"/></div>
  <button class="mobile-burger" id="mobile-burger" aria-label="Menu">
    <span></span><span></span><span></span>
  </button>
</div>
<div class="sidebar-overlay" id="sidebar-overlay"></div>

<aside class="sidebar" id="sidebar">
  <div class="sidebar-logo"><img src="/assets/logo.png" alt="Denny Pratama" style="height:28px;width:auto;opacity:0.85;"/></div>
  <nav class="sidebar-nav">
    <a class="sidebar-link" href="analytics.php">Dashboard</a>
    <a class="sidebar-link" href="index.php">Posts</a>
    <a class="sidebar-link" href="auto-post.php">Auto Post</a>
    <a class="sidebar-link" href="ebooks.php">Ebooks</a>
    <a class="sidebar-link" href="change-password.php">Change Password</a>
    <a class="sidebar-link" href="../index.html" target="_blank">View Site →</a>
  </nav>
  <div class="sidebar-bottom">
    <button class="theme-toggle" id="theme-toggle">◑ Light mode</button>
    <a class="sidebar-logout" href="logout.php">Sign out</a>
  </div>
</aside>
```

4. End of `<body>`: `<script src="admin.js"></script>` — never duplicate theme toggle or burger JS inline
5. Page-specific CSS in a `<style>` block, max ~20 lines

## CSS
- All shared admin styles are in `admin/theme.css` — search there before adding anything
- Light mode overrides go at the bottom under `[data-theme="light"]`
- Never redefine `.sidebar`, `.sidebar-link`, `.btn-save`, `.field`, `.table`, `.badge` — already defined

## JavaScript
- `admin.js` handles burger menu + theme toggle for all pages — don't touch it for page-specific needs
- Page-specific JS stays inline in the page file
- Don't add to `admin.js` unless the behavior is needed by 3+ pages
