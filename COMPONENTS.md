# Components & Patterns — dennypratama.com

A catalog of the reusable building blocks on the site. **Search this file before writing new CSS or markup** — the thing you need probably already exists. Public styles live in `style.css`; admin styles in `admin/theme.css`; large page-specific styles in `css/<page>.css`.

---

## Design tokens (CSS custom properties)

Defined in `:root` at the top of `style.css`. Always use these — never hardcode.

| Token | Value | Use |
|---|---|---|
| `--paper` | `#ECEAE2` | light background |
| `--ink` | `#0D0C09` | primary text / dark bg |
| `--ink-2` | `#3A3830` | secondary text |
| `--ink-3` | `#6B6960` | tertiary text / meta |
| `--red` | `#E8320A` | accent / CTA |
| `--border` | `rgba(13,12,9,0.1)` | hairlines |
| `--font-sans` | Geist | body + display |
| `--font-mono` | Geist Mono | eyebrows, labels, meta, buttons |
| `--pad-x` / `--pad-x-sm` | — | horizontal page padding |

**Spacing:** multiples of 4 only (4/8/12/16/24/32/48/64/96/128).
**Type scale:** 12/14/16/18/20/24/28/32/40/48/56/64/72/80. `12px` only for uppercase eyebrows with `letter-spacing ≥ 0.06em`; body copy ≥ 16px; captions/labels ≥ 14px.

**Heading tokens** (the single source of truth — use these, never inline `clamp()` per selector):

| Token | Desktop size | Mobile size (≤768px) | line-height (desktop / mobile) | Use for |
|---|---|---|---|---|
| `--text-display` / `--leading-display` | `clamp(44px, 8vw, 88px)` | `44px` | `0.92` / `1.05` | Homepage hero only |
| `--text-h1` / `--leading-h1` | `clamp(40px, 5vw, 72px)` | `32px` | `1.05` / `1.15` | Section titles, page heroes (blog/post/ebook/form) |
| `--text-h2` / `--leading-h2` | `clamp(28px, 3vw, 40px)` | `24px` | `1.2` / `1.25` | Card titles, subsections, post-body `<h2>` |
| `--text-h3` / `--leading-h3` | `clamp(20px, 2vw, 28px)` | `20px` | `1.35` / `1.4` | Small card titles, testimonial quotes, blockquotes |

Hierarchy is guaranteed at every viewport (display > h1 > h2 > h3). Apply with `font-size: var(--text-h1); line-height: var(--leading-h1);`. Tight ratios that read confident at 72px feel crowded at 32px, so line-heights loosen on mobile. To resize the whole site's mobile h2, edit one line in `:root` — every h2 follows.

---

## Layout components (PHP partials)

Included on every public page; edit once, applies everywhere.

| Component | File | Notes |
|---|---|---|
| `<head>` | `partials/head.php` | meta, OG/Twitter, JSON-LD, fonts, global CSS, page-transition, GSAP (gated by `$needs_gsap`), per-page CSS (gated by `$page_css`) |
| Header / nav | `partials/nav.php` | fixed logo + burger + fullscreen overlay menu |
| Footer | `partials/footer.php` | copyright + legal + socials (single inline row) |
| Contact modal | `partials/modal.php` | project-inquiry form, opened by any `.js-open-modal` |

**Page variables** (set *before* `include 'partials/head.php'`):
`$title`, `$description`, `$canonical`, `$og_image`, `$og_type`, `$jsonld`, `$needs_gsap` (bool — loads GSAP), `$page_css` (string — loads an extra stylesheet, e.g. `'/css/ebook.css?v=1'`).

Admin pages share a **sidebar + mobile topbar** block (currently copy-pasted per page — see `.claude/rules/admin.md`) and load `admin/theme.css` + `admin/admin.js`.

---

## Buttons

| Class | Where | Look |
|---|---|---|
| `.btn-hero-primary` | hero, homepage | solid ink pill, magnetic hover |
| `.btn-hero-ghost` | hero, modal triggers | outlined pill |
| `.btn-cta-main` / `.btn-cta-outline` | CTA section | solid / outlined on dark |
| `.form-btn` | public forms (recover, library) | full-width red, has `.form-btn-spinner` loading state |
| `.pm-btn-send` | contact modal | red pill submit; shares spinner/label pattern via `.loading` class |
| `.eb-btn-buy` | ebook pages (hero + CTA) | red pill checkout; same visual as `.pm-btn-send`, shares spinner/label pattern |
| `.btn-save` / `.btn-new` / `.btn-cancel` / `.btn-secondary` | admin | standard admin actions |

All button text is mono + uppercase via the typography system. Magnetic hover (`.btn-hero-primary`, `.btn-cta-main`) is wired in `script.js`.

---

## Cards

Each card type lives on one page — reuse the class, don't reinvent.

| Class | Page | Structure |
|---|---|---|
| `.blog-card` | blog listing | img · `.blog-card-head` (cat · date) · title · excerpt · readmore |
| `.bp-card` | homepage preview | img · meta · title · excerpt · read |
| `.eb-list-card` | ebooks catalog (`css/ebooks.css`) | cover · meta · title · desc · footer (price + CTA) |
| `.lib-card` | my-library (`css/my-library.css`) | cover · body (title + date) · CTA |
| `.stat-card` | admin dashboard | label · value · sub |

---

## Forms

| Class | Use |
|---|---|
| `.form-page-section` / `.form-page-wrap` | centered single-form page layout (recover, library) |
| `.form-page-eyebrow` / `.form-page-title` / `.form-page-sub` | form page header |
| `.form-stack` | vertical form |
| `.form-input` | text/email input |
| `.form-btn` + `.form-btn-label` + `.form-btn-spinner` | submit with loading state |
| `.form-msg` + `.form-msg--success` / `.form-msg--error` | inline status messages (toggle `hidden`) |
| `.pm-field` / `.pm-input` / `.pm-textarea` / `.pm-label` | contact modal fields |
| `.field` / `.form-input` (admin) | admin form rows (`admin/theme.css`) |

Always pair an input with a `<label>` (use `.sr-only` if visually hidden).

---

## Labels, badges, eyebrows

- **Eyebrow pattern:** 12px, uppercase, `letter-spacing ≥ 0.1em`, mono, `--ink-3`. Used as `.section-title-sm`, `.eb-eyebrow`, `.blog-hero-eyebrow`, etc.
- `.section-num` — `01`-style section index.
- `.badge` (admin) — status pill; variants `.badge-pub`, `.badge-draft`, `.badge-scheduled`, `.badge-active`.
- `.cat-badge` / `.blog-card-cat` — post category tag (red).

---

## Utilities

`.sr-only` (visually hidden) · `.hint` · `.empty` · `.back-link` · `.section-heading` · `.action-link` (admin) · `.is-active` / `.is-open` / `.scrolled` state modifiers.

---

## Typography system

The block at the **end of `style.css`** (`/* TYPOGRAPHY SYSTEM */`) assigns fonts by role:
- **Display** (`--font-sans`, weight 500, tight tracking): titles, headings, hero, stat numbers.
- **Mono** (`--font-mono`, weight 500, `0.08em`): every eyebrow, label, meta, button, footer item.

If a new element should read as a label/eyebrow, **add its class to the mono list** rather than restyling it inline (this is how the footer/blog-card meta stay consistent).

---

## JavaScript

| File | Responsibility |
|---|---|
| `script.js` | custom cursor, nav burger/overlay, scroll effects, page transitions, magnetic buttons, GSAP animations, contact modal, reCAPTCHA |
| `api/tracker.js` | page-view + session-duration analytics |
| `admin/admin.js` | admin burger menu + light/dark theme toggle |

Page-specific JS stays inline in the page file. Bump the `?v=` query on `script.js`/`style.css` (in `partials/head.php` and the page footers) when changing them.

---

## Where CSS lives

| Scope | File |
|---|---|
| Public, shared | `style.css` |
| Admin, shared | `admin/theme.css` |
| Large page-specific (ebook, ebooks, my-library) | `css/<page>.css` via `$page_css` |
| Small page-specific (≤ ~20 lines) | inline `<style>` in the page |
| Admin light-mode overrides | `[data-theme="light"]` block at bottom of `admin/theme.css` |
