# CSS Rules

**Before adding any class — search `style.css` (public) or `admin/theme.css` (admin) first.** The class you need probably already exists.

## Color tokens — always use these, never hardcode hex
```css
--paper:    #ECEAE2   /* light background */
--ink:      #0D0C09   /* dark text */
--ink-2:    #3A3830   /* secondary text */
--ink-3:    #6B6960   /* tertiary text */
--red:      #E8320A   /* accent / CTA */
--border:   rgba(13,12,9,0.1)
```

## Spacing
- Multiples of 4px only: 4 / 8 / 12 / 16 / 24 / 32 / 48 / 64 / 96 / 128px
- Use `--pad-x` and `--pad-x-sm` for horizontal page padding
- No arbitrary values like `margin: 13px` or `padding: 22px`
- line-height as a spacing unit must also resolve to a 4px-grid value (e.g. at 18px font: `line-height: 0` = 0px, not 0.75 = 13.5px)

## Headings — always use the token, never inline clamp()
For every heading (hero, section title, card title, etc.), set:
```css
font-size: var(--text-display); /* homepage hero */
font-size: var(--text-h1);      /* section titles + page heroes (blog/post/ebook/form) */
font-size: var(--text-h2);      /* card titles, subsections, post-body h2 */
font-size: var(--text-h3);      /* small card titles, blockquotes, testimonial quote */
```
Tokens are defined once in `:root` (with `clamp()` for desktop) and overridden to fixed px values inside `@media (max-width: 768px)` so hierarchy is guaranteed on mobile. **Never** write `font-size: clamp(...)` on an individual heading selector — that's exactly the inconsistency the tokens exist to prevent. See `COMPONENTS.md` for the full table.

## Typography scale — strict (non-heading text)
Allowed `font-size` values only: **14 / 16 / 18 / 20 / 24 / 28 / 32 / 40 / 48 / 56 / 64 / 72 / 80px**

Rules:
- **No odd numbers.** Never `11`, `13`, `15`, `17`, `19`, `21`, `23`, `25`, `27`px etc.
- **No 9, 10px.** Too small — bump to 12 (eyebrow) or 14 (anything else).
- **Primary body / paragraph copy → 16px minimum.** 12px is way too small to read; never use it for body copy, descriptions, list text, table cells, post text, modal sub-text, or stat descriptions.
- **Captions / disclaimers / form labels / metadata → 14px minimum.** Use sparingly; default to 16px when in doubt.
- **22, 26, 30, 36, 44, 60px are not on the scale** — round to the nearest allowed size (22→24, 26→24, 30→32, 36→32 or 40, 44→48, 60→64).

### Exception: eyebrow / tag pattern (12px allowed)
12px is permitted **only** for short uppercase labels that meet ALL of these:
- `text-transform: uppercase`
- `letter-spacing: ≥ 0.06em` (intentional tracking — body copy never has this)
- ≤ ~30 chars (eyebrow, tag, badge, kicker, button text, table header label)

Anything that reads as a sentence, description, or paragraph is body text — use 16px minimum, regardless of color or weight.

### Migration map (when fixing legacy code)
| Found | Replace with |
|---|---|
| 9, 10, 11px | 12px (if eyebrow) else 14px |
| 12px (body context) | 16px |
| 12px (uppercase eyebrow ≥ 0.1em letter-spacing) | keep 12px |
| 13px | 14px |
| 15px | 16px |
| 17px | 18px |
| 19px | 20px |
| 22px | 24px |
| 26px | 24px or 28px (whichever fits hierarchy) |
| 30px | 32px |
| 44px | 48px |
| 60px | 64px |

## Mobile
- Every desktop CSS change must be checked for mobile impact
- Breakpoints: 375 / 768 / 1024 / 1440px
- If a desktop rule changes layout, spacing, or typography — add or verify a `@media (max-width: 768px)` override exists
- Never ship a desktop-only fix without confirming mobile renders correctly

## Naming
- kebab-case, component-prefix: `.blog-card`, `.hero-title`, `.post-cta`, `.stat-cell`
- State modifiers: `.is-active`, `.is-open`, `.scrolled`
- No generic names: never `.box`, `.wrapper`, `.item` without a prefix

## Where CSS lives
| Scope | File |
|---|---|
| Public pages | `style.css` |
| Admin pages | `admin/theme.css` |
| Small page-specific overrides (≤ ~20 lines) | Inline `<style>` block in the page |
| Large page-specific styles (> ~20 lines) | `css/<page>.css`, loaded via `$page_css` set before `include 'partials/head.php'` (e.g. `$page_css = '/css/ebook.css?v=1';`) |
| Admin light-mode overrides | `[data-theme="light"]` section at bottom of `admin/theme.css` |

Don't dump page-specific rules into the global `style.css` — it loads on every page. Keep them scoped via `$page_css`. Bump the `?v=` when editing a `css/<page>.css` file.

See `COMPONENTS.md` (project root) for the full catalog of buttons, cards, forms, and label patterns — search it before adding new CSS.

## Never
- Hardcode a hex color in a component rule — use a CSS variable
- Add `style=""` attributes for anything not computed dynamically
- Recreate classes that already exist: `.sr-only`, `.empty`, `.hint`, `.back-link`, `.btn-save`, `.btn-new`, `.badge`, `.action-link`, `.field`, `.section-heading` are all defined
