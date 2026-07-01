# Skill: handoff-audit-lite
# Trigger: /handoff-audit-lite

Fast single-pass pre-delivery audit — no subagents, runs entirely in the main conversation. Use this for quick mid-project sanity checks. For the full 14-agent cross-checked audit before actual handoff, use `/handoff-audit`.

Checks code quality, SEO, performance, accessibility, security, and deployment readiness. Outputs a structured checklist with status per item.

## Status Icons
- ✅ Pass
- ⚠️ Needs attention (present but incomplete or suboptimal)
- ❌ Missing / failing
- — Not applicable for this stack

## Steps

Read the project root and relevant source files before starting. Base the audit on what actually exists in the codebase — do not assume.

Work through each section below in order. For each item, check the actual files, report the status icon, and add a one-line note if the status is ⚠️ or ❌.

---

## 1. Code Quality

- [ ] No unused imports or dead code left in source files
- [ ] No `console.log`, `console.warn`, or `debugger` statements in production code
- [ ] No hardcoded color values, magic numbers, or inline styles that should be tokens
- [ ] No `any` types in TypeScript files (or suppressed with justification)
- [ ] No commented-out code blocks
- [ ] Component and file names are semantic and descriptive (no `Section1`, `Card2`, `temp`, `test`)
- [ ] Environment variables used for secrets — nothing secret hardcoded in source
- [ ] `.env` is not committed; `.env.example` is committed with placeholder values

## 2. SEO

- [ ] Every page has a unique `<title>` tag
- [ ] Every page has a `<meta name="description">` (120–160 chars)
- [ ] Open Graph tags present on every page: `og:title`, `og:description`, `og:image`, `og:url`, `og:type`
- [ ] `og:image` exists, is 1200×630px, and is an absolute URL
- [ ] Canonical URL set on every page (`<link rel="canonical">`)
- [ ] `robots.txt` exists at root and is not blocking important pages
- [ ] `sitemap.xml` exists and is referenced in `robots.txt`
- [ ] Every image has a descriptive `alt` attribute (not empty, not the filename)
- [ ] Heading hierarchy is correct: one `<h1>` per page, logical `h2` → `h3` nesting
- [ ] No broken internal links
- [ ] Structured data / JSON-LD present where applicable (Organisation, Article, Product, BreadcrumbList)

## 3. Performance

- [ ] All images use a modern format (WebP or AVIF)
- [ ] Images have explicit `width` and `height` attributes or are sized via CSS to prevent CLS
- [ ] Images below the fold use lazy loading (`loading="lazy"` or next/image)
- [ ] No render-blocking scripts (all `<script>` tags use `defer` or `async`, or are in `<body>`)
- [ ] Web fonts preloaded (`<link rel="preload">`) and use `font-display: swap`
- [ ] No unused CSS or JS being shipped (check bundle output)
- [ ] No bloated or unnecessary dependencies (`npm ls --depth=0` to review)
- [ ] Core Web Vitals targets: LCP < 2.5s, CLS < 0.1, INP < 200ms (check Lighthouse or PageSpeed)

## 4. Accessibility

- [ ] All interactive elements reachable and operable by keyboard alone
- [ ] Visible focus states on all focusable elements (not `outline: none` without a replacement)
- [ ] Icon-only buttons and links have an `aria-label` or visually-hidden text
- [ ] All form inputs have an associated `<label>` (via `for`/`id` or wrapping)
- [ ] Color contrast meets WCAG AA: 4.5:1 for body text, 3:1 for large text and UI components
- [ ] `lang` attribute set on `<html>` element
- [ ] Skip-to-content link present as first focusable element
- [ ] No content relies solely on color to convey meaning

## 5. Security

- [ ] No secrets, API keys, or tokens in committed source files or git history
- [ ] `npm audit` shows zero high or critical vulnerabilities
- [ ] Any use of `dangerouslySetInnerHTML` (React) or `innerHTML` is sanitized
- [ ] User-supplied input is validated and sanitized before use
- [ ] External links use `rel="noopener noreferrer"`
- [ ] No mixed content (HTTP assets on an HTTPS page)

## 6. Deployment Readiness

- [ ] `favicon.ico` or `favicon.svg` present (tested in browser tab)
- [ ] Custom 404 page exists and is wired up
- [ ] OG image file exists at the path referenced in meta tags
- [ ] `README.md` present, accurate, and includes setup/run instructions
- [ ] `.gitignore` covers: `node_modules/`, `dist/`, `.env`, build artifacts
- [ ] No staging URLs, localhost references, or `TODO` comments in production code
- [ ] All placeholder content (`[Lorem ipsum]`, `[Client name]`, `[URL]`) replaced
- [ ] Site tested on: Chrome, Safari, Firefox (latest); iOS Safari; Android Chrome
- [ ] Site tested at 375px, 768px, 1024px, 1440px viewport widths

---

## Output Format

After completing the audit, output the full checklist in this format:

```
# Handoff Audit (Lite) — [Project Name]

## 1. Code Quality
✅ No unused imports
❌ console.log found in src/utils/api.ts:42
...

## Summary
✅ X items passing
⚠️ X items need attention
❌ X items failing

### Priority fixes before handoff:
1. [Most critical issue]
2. [Next issue]
...
```

Do not commit anything. Do not make any changes to the codebase — this is read-only. Report only.
