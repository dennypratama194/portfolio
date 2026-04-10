# dennypratama.com — Project Standards

This site has grown from a portfolio into a product platform (blog + ebook e-commerce). These rules exist to keep the codebase consistent as it scales.

---

## Stack

| Layer | Tech |
|---|---|
| Backend | PHP 7.4+, MySQL via PDO |
| Frontend | Vanilla CSS + JS, GSAP 3 (CDN), Quill (admin editor) |
| Payments | Xendit Invoices API (IDR) |
| Email | Resend API (transactional) |
| Hosting | cPanel shared hosting (RumahWeb.com) |
| Git | `main` = production, `staging` = new features |

No build tools. Edit CSS and JS directly.

---

## CSS Rules

**Before adding any class — search `style.css` (public) or `admin/theme.css` (admin) first.** The class you need probably already exists.

### Color tokens — always use these, never hardcode hex
```css
--paper:    #ECEAE2   /* light background */
--ink:      #0D0C09   /* dark text */
--ink-2:    #3A3830   /* secondary text */
--ink-3:    #6B6960   /* tertiary text */
--red:      #E8320A   /* accent / CTA */
--border:   rgba(13,12,9,0.1)
```

### Spacing
- Use multiples of 4px only: 4 / 8 / 12 / 16 / 24 / 32 / 48 / 64 / 96 / 128px
- Use `--pad-x` and `--pad-x-sm` for horizontal page padding (responsive via `clamp()`)
- No arbitrary values like `margin: 13px` or `padding: 22px`

### Naming
- kebab-case, component-prefix: `.blog-card`, `.hero-title`, `.post-cta`, `.stat-cell`
- State modifiers: `.is-active`, `.is-open`, `.scrolled`
- No generic names: never `.box`, `.wrapper`, `.item` without a prefix

### Where CSS lives
- Public pages → `style.css`
- Admin pages → `admin/theme.css` (base styles + light mode at bottom)
- Page-specific overrides → inline `<style>` block, max ~20 lines
- New admin light-mode overrides → `[data-theme="light"]` section at bottom of `admin/theme.css`

### Never
- Hardcode a hex color in a component rule — use a CSS variable
- Add `style=""` attributes for anything not computed dynamically
- Create utility classes that already exist (`.sr-only`, `.empty`, `.hint`, `.back-link` are all defined)

---

## PHP Page Structure

### Public pages — required variables before `head.php`
```php
<?php
$title       = 'Page Title — Denny Pratama';   // unique, under 60 chars
$description = 'Page description...';           // 150-160 chars
$canonical   = 'https://dennypratama.com/slug'; // full URL, no trailing slash
$og_image    = 'https://dennypratama.com/assets/og-image.jpg'; // absolute URL
// optional:
$jsonld      = json_encode([...]);              // structured data object
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

### Admin pages — required pattern at top
```php
<?php
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
session_start();
if (!isset($_SESSION['authed'])) { header('Location: /admin/login'); exit; }
require __DIR__ . '/../api/db.php';
$_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
```

### Shared PHP helpers — `api/helpers.php`
Never redefine `escHtml()` or `formatDate()` inline. Require the helpers file:
```php
require __DIR__ . '/api/helpers.php'; // from project root
// or from admin/:
require __DIR__ . '/../api/helpers.php';
```

### Database access
- Always use prepared statements — no string interpolation in SQL
- ✅ `$stmt = $pdo->prepare('SELECT * FROM posts WHERE slug = ?'); $stmt->execute([$slug]);`
- ❌ `$pdo->query("SELECT * FROM posts WHERE slug = '$slug'")`

---

## Security Checklist

Every new page or API endpoint must follow these rules:

### Admin pages
- Session check + auth guard at the very top (before any output)
- CSRF token generated: `$_SESSION['csrf_token'] ??= bin2hex(random_bytes(32))`
- POST handlers verify: `if (($_POST['csrf'] ?? '') !== $_SESSION['csrf_token']) { http_response_code(403); exit; }`

### Output
- All user-supplied data through `escHtml()` before echo — never `echo $_GET['x']` raw
- Exception: post body HTML (from Quill, admin-only input) — acceptable risk

### File uploads
- Validate MIME with `finfo_file()` — **not** `mime_content_type()` (deprecated in PHP 8.1+)
- Whitelist allowed types: `['image/jpeg', 'image/png', 'image/webp', 'image/gif']`
- Cap file size (5MB default)
- Store in `admin/uploads/`, never in a web-accessible API directory

### Secrets
- All credentials in `api/.secrets.php` (gitignored) — never in source code
- Required constants: `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, `ADMIN_USER`, `RECAPTCHA_SECRET`, `WEB3FORMS_KEY`
- Ebook constants: `XENDIT_SECRET_KEY`, `XENDIT_WEBHOOK_TOKEN`, `RESEND_API_KEY`, `SITE_URL`

### API endpoints
```php
// Always set correct status codes
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
header('Content-Type: application/json');
// Return consistent shape:
echo json_encode(['ok' => true, 'data' => $result]);
echo json_encode(['ok' => false, 'error' => 'Message']);
```

---

## SEO Checklist

Every new public page needs all of these:

- [ ] `$title` — unique per page, primary keyword near front, under 60 chars
- [ ] `$description` — unique, 150–160 chars, includes a call to action
- [ ] `$canonical` — `https://dennypratama.com/exact-url` (no trailing slash, no query string except for posts)
- [ ] `$og_image` — absolute URL; fall back to `/assets/og-default.jpg` if no page-specific image
- [ ] One `<h1>` tag — in HTML/PHP, **not injected by JavaScript**
- [ ] JSON-LD schema — Person (home), BlogPosting (posts), Product (ebook pages)
- [ ] Add URL to `sitemap.xml`

### Admin pages
Add this inside `<head>` of every admin page:
```html
<meta name="robots" content="noindex, nofollow"/>
```

### Schema patterns
```php
// Blog post
$jsonld = json_encode([
    '@context' => 'https://schema.org',
    '@type'    => 'BlogPosting',
    'headline' => $post['title'],
    'author'   => ['@type' => 'Person', 'name' => 'Denny Pratama'],
    'datePublished' => $post['published_at'],
    'url'      => $canonical,
]);

// Ebook product
$jsonld = json_encode([
    '@context' => 'https://schema.org',
    '@type'    => 'Product',
    'name'     => $product['title'],
    'offers'   => ['@type' => 'Offer', 'price' => $product['price'], 'priceCurrency' => 'IDR'],
]);
```

---

## Admin Pages — Template Pattern

New admin page checklist:

1. PHP auth + CSRF block at top (see above)
2. In `<head>`: link to `theme.css`, add `noindex` meta, no inline sidebar CSS
3. Mobile topbar HTML immediately after `<body>`:
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
4. End of `<body>`: `<script src="admin.js"></script>` — never add theme toggle or burger JS inline
5. Page-specific CSS in a `<style>` block, max ~20 lines

---

## JavaScript

### Frontend (script.js)
- All shared utilities (modal open/close, nav behavior, cursor) live in `script.js`
- Page-specific JS goes in an inline `<script>` at bottom of the page, after `script.js`
- Always wrap GSAP code in `DOMContentLoaded`
- Check elements exist before operating: `if (!el) return;`
- No `console.log()` in production

### Admin (admin.js)
- Shared: burger menu toggle + theme toggle
- Page-specific JS stays inline in the page file
- Do not add new shared behavior to admin.js without it being needed by 3+ pages

---

## What Never To Do

| Don't | Do instead |
|---|---|
| Create a CSS class without searching first | `Grep` for similar class in `style.css` / `admin/theme.css` |
| Hardcode `#E8320A` in a rule | Use `var(--red)` |
| Duplicate sidebar CSS in a new admin page | It's in `admin/theme.css` — just link the stylesheet |
| Add inline theme toggle JS | `<script src="admin.js"></script>` handles it |
| Use `mime_content_type()` | Use `finfo_file()` |
| Hardcode a secret or API key | Add to `api/.secrets.php` |
| Add `console.log()` to production JS | Remove before committing |
| Use `echo $_GET['x']` directly | `echo escHtml($_GET['x'])` |
| Put credentials in `.env` or config.php | Use `api/.secrets.php` (already gitignored) |
| `git add .` blindly | Stage specific files — avoid committing `.secrets.php` or uploads |

---

## File Map

```
portfolio/
├── index.php              # Homepage
├── blog.php               # Blog listing
├── post.php               # Single post (JS-rendered from API)
├── ebook.php              # Ebook sales page
├── ebooks.php             # Ebook catalog
├── my-library.php         # Purchased library (user-facing)
├── recover.php            # Resend magic link
├── read.php               # Magic-link ebook reader
├── style.css              # ALL public CSS — search here first
├── script.js              # ALL public JS
├── robots.txt             # Crawl rules
├── sitemap.xml            # Update manually when adding routes
├── .htaccess              # URL routing + security headers
├── partials/
│   ├── head.php           # Meta, OG, JSON-LD, CSS/font preloads
│   ├── nav.php            # Fixed nav + mobile menu
│   ├── footer.php         # Footer + scroll-fade overlay
│   └── modal.php          # Contact/project inquiry modal
├── admin/
│   ├── theme.css          # ALL admin CSS — search here first
│   ├── admin.js           # Burger + theme toggle (shared)
│   ├── analytics.php      # Dashboard
│   ├── index.php          # Posts list
│   ├── edit.php           # Post editor (Quill)
│   ├── auto-post.php      # Auto-publish settings
│   ├── ebooks.php         # Ebook products list
│   ├── ebook-edit.php     # Ebook product editor
│   ├── ebook-chapters.php # Chapter management
│   ├── ebook-purchases.php# Purchase records
│   ├── change-password.php# Admin password
│   └── login.php          # Auth (brute-force lockout after 5 attempts)
└── api/
    ├── helpers.php        # Shared PHP utilities (escHtml, formatDate)
    ├── db.php             # PDO connection (requires .secrets.php)
    ├── .secrets.php       # Credentials — NEVER commit, create manually on server
    ├── posts.php          # GET → JSON post list
    ├── post.php           # GET ?slug=X → JSON single post
    ├── track.php          # Pageview analytics
    ├── tracker.js         # Client-side analytics
    ├── contact.php        # Contact form → Web3Forms
    ├── ebook-checkout.php # Xendit invoice creation
    ├── ebook-webhook.php  # Xendit payment confirmation
    ├── ebook-library.php  # User purchase lookup
    ├── ebook-recover.php  # Resend magic link
    └── auto-post.php      # AI auto-publishing (Claude + DALL-E)
```
