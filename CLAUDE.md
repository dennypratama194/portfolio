# dennypratama.com — Project Standards

PHP + MySQL portfolio/e-commerce site. No build tools. Rules are split into focused files — Claude Code loads them automatically.

- **CSS** → `.claude/rules/css.md`
- **PHP patterns** → `.claude/rules/php.md`
- **Security** → `.claude/rules/security.md`
- **SEO** → `.claude/rules/seo.md`
- **Admin pages** → `.claude/rules/admin.md`
- **API endpoints** → `.claude/rules/api.md`
- **Stack & file map** → `.claude/rules/stack.md`

---

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
