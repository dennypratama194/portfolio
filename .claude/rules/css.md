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
| Page-specific overrides | Inline `<style>` block, max ~20 lines |
| Admin light-mode overrides | `[data-theme="light"]` section at bottom of `admin/theme.css` |

## Never
- Hardcode a hex color in a component rule — use a CSS variable
- Add `style=""` attributes for anything not computed dynamically
- Recreate classes that already exist: `.sr-only`, `.empty`, `.hint`, `.back-link`, `.btn-save`, `.btn-new`, `.badge`, `.action-link`, `.field`, `.section-heading` are all defined
