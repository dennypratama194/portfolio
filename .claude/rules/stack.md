# Stack & File Map

## Stack
| Layer | Tech |
|---|---|
| Backend | PHP 7.4+, MySQL via PDO |
| Frontend | Vanilla CSS + JS, GSAP 3 (CDN), Quill (admin editor) |
| Payments | Xendit Invoices API (IDR) |
| Email | Resend API (transactional) |
| Hosting | cPanel shared hosting (RumahWeb.com) |
| Git | `main` = production · `staging` = new features |

No build tools. Edit CSS and JS directly.

## File map
```
portfolio/
├── index.php               # Homepage
├── blog.php                # Blog listing (cards link to /blog/SLUG)
├── post.php                # Single post (server-rendered; routed /blog/SLUG → post.php?slug=)
├── ebook.php               # Ebook sales page (styles in css/ebook.css)
├── ebooks.php              # Ebook catalog (styles in css/ebooks.css)
├── my-library.php          # Purchased library (styles in css/my-library.css)
├── recover.php             # Resend magic link
├── read.php                # Magic-link ebook reader (self-contained <head> + styles)
├── style.css               # Shared public CSS — search here first
├── script.js               # ALL public JS
├── COMPONENTS.md           # Catalog of buttons/cards/forms/patterns — read before adding CSS
├── robots.txt              # Crawl rules
├── llms.txt                # AI/LLM crawler map
├── sitemap.php             # Dynamic sitemap (served at /sitemap.xml via .htaccess); auto-lists posts
├── .htaccess               # URL routing + security headers
├── css/                    # Large page-specific stylesheets (loaded via $page_css)
│   ├── ebook.css
│   ├── ebooks.css
│   └── my-library.css
├── partials/
│   ├── head.php            # Meta, OG, JSON-LD, CSS/font preloads
│   ├── nav.php             # Fixed nav + mobile menu
│   ├── footer.php          # Footer + scroll-fade overlay
│   └── modal.php           # Contact/project inquiry modal
├── admin/
│   ├── theme.css           # ALL admin CSS — search here first
│   ├── admin.js            # Burger + theme toggle (shared)
│   ├── analytics.php       # Dashboard
│   ├── index.php           # Posts list
│   ├── edit.php            # Post editor (Quill)
│   ├── auto-post.php       # Auto-publish settings
│   ├── ebooks.php          # Ebook products list
│   ├── ebook-edit.php      # Ebook product editor
│   ├── ebook-chapters.php  # Chapter management
│   ├── ebook-purchases.php # Purchase records
│   ├── change-password.php # Admin password
│   └── login.php           # Auth (brute-force lockout after 5 attempts)
└── api/
    ├── helpers.php         # Shared PHP utilities (escHtml, formatDate, isAllowedImage)
    ├── db.php              # PDO connection (requires .secrets.php)
    ├── .secrets.php        # Credentials — NEVER commit, create manually on server
    ├── posts.php           # GET → JSON post list
    ├── post.php            # GET ?slug=X → JSON single post
    ├── track.php           # Pageview analytics
    ├── tracker.js          # Client-side analytics
    ├── contact.php         # Contact form → Web3Forms
    ├── ebook-checkout.php  # Xendit invoice creation
    ├── ebook-webhook.php   # Xendit payment confirmation
    ├── ebook-recover.php   # Resend magic link (also serves /my-library lookups)
    └── auto-post.php       # AI auto-publishing (Claude + gpt-image-2)
```

## Never do
| Don't | Do instead |
|---|---|
| Create a CSS class without searching first | Grep `style.css` / `admin/theme.css` |
| Hardcode `#E8320A` in a rule | `var(--red)` |
| Duplicate sidebar CSS in a new admin page | It's in `admin/theme.css` |
| Add inline theme toggle JS | `<script src="admin.js"></script>` |
| Use `mime_content_type()` | `isAllowedImage()` from helpers.php |
| Hardcode a secret or API key | `api/.secrets.php` |
| `echo $_GET['x']` directly | `echo escHtml($_GET['x'])` |
| `git add .` blindly | Stage specific files |
| `console.log()` in production JS | Remove before committing |
| Add `Co-Authored-By: Claude ...` in commits | Plain commit message only — no attribution footer |
