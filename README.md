# dennypratama.com

PHP + MySQL portfolio / ebook storefront. No build tools — edit CSS and JS directly.

## Stack

| Layer | Tech |
|---|---|
| Backend | PHP 7.4+, MySQL via PDO |
| Frontend | Vanilla CSS + JS, GSAP 3 (CDN), Quill (admin editor) |
| Payments | Xendit Invoices API (IDR) |
| Email | Resend API (transactional) |
| Hosting | cPanel shared hosting (RumahWeb.com) |
| Git | `main` = production · `staging` = new features |

## Local setup

1. **Requirements**: PHP 7.4+ with the `pdo_mysql` extension, and a MySQL/MariaDB server.
2. **Clone and serve**:
   ```bash
   php -S localhost:8000
   ```
3. **Database**: create a database and run the migrations in `migrations/` in order:
   ```bash
   mysql -u root -p your_db_name < migrations/001_ebook_tables.sql
   mysql -u root -p your_db_name < migrations/002_chapter_excerpt.sql
   mysql -u root -p your_db_name < migrations/003_page_views_country.sql
   mysql -u root -p your_db_name < migrations/004_projects.sql
   ```
4. **Secrets**: copy the example file and fill in real values.
   ```bash
   cp api/.secrets.php.example api/.secrets.php
   ```
   Required constants: `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, `ADMIN_USER`, `RECAPTCHA_SECRET`, `XENDIT_SECRET_KEY`, `XENDIT_WEBHOOK_TOKEN`, `RESEND_API_KEY`, `SITE_URL`.
5. **Admin password**: the admin login checks a bcrypt hash stored in `api/.admin_hash` (gitignored, not generated automatically). Create it once:
   ```bash
   php -r "echo password_hash('your-password-here', PASSWORD_BCRYPT), PHP_EOL;"
   ```
   Paste the output into a new `api/.admin_hash` file (no trailing newline needed). Once logged in, you can change the password from `/admin/change-password`.
6. Visit `http://localhost:8000` for the public site, `http://localhost:8000/admin/login` for the admin panel.

## Deploying

Push to `main` for production, `staging` for work-in-progress features, per the branch convention above. On the server, `api/.secrets.php` and `api/.admin_hash` must exist (they're gitignored — create them manually via cPanel/SSH, same as steps 4–5).

## Project structure

See `CLAUDE.md` for the full file map and coding standards, and `.claude/rules/` for area-specific conventions (CSS, PHP, security, SEO, admin pages, API endpoints).
