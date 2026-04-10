# Security Rules

## Admin pages
- Session check + auth guard before any output (see php.md for boilerplate)
- CSRF token on every form: `<input type="hidden" name="csrf" value="<?= $_SESSION['csrf_token'] ?>"/>`
- Verify on POST: `if (($_POST['csrf'] ?? '') !== $_SESSION['csrf_token']) { http_response_code(403); exit; }`

## File uploads
- Validate MIME with `isAllowedImage()` from `api/helpers.php` — **not** `mime_content_type()` (removed in PHP 8.1+)
- Allowed types: `image/jpeg`, `image/png`, `image/webp`, `image/gif`
- Max size: 5MB
- Store in `admin/uploads/` — never in `api/` or any web-accessible directory without auth

## Secrets
All credentials live in `api/.secrets.php` (gitignored). Never hardcode in source.

| Constant | Purpose |
|---|---|
| `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` | Database |
| `ADMIN_USER` | Admin login username |
| `RECAPTCHA_SECRET` | reCAPTCHA v3 |
| `WEB3FORMS_KEY` | Contact form forwarding |
| `XENDIT_SECRET_KEY` | Payment API |
| `XENDIT_WEBHOOK_TOKEN` | Webhook verification |
| `RESEND_API_KEY` | Transactional email |
| `SITE_URL` | Absolute base URL (e.g. `https://dennypratama.com`) |

## Git hygiene
- Never `git add .` — stage files individually
- `.secrets.php` and `admin/uploads/` are gitignored — keep it that way
- Rotate any key that has been visible in plaintext, even briefly
