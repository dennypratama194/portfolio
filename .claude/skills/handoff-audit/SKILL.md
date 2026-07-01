# Skill: handoff-audit
# Trigger: /handoff-audit

Full pre-delivery audit using 18 parallel specialist agents with cross-checking, followed by a dedicated reconciler that consolidates all findings into a single report.

Sections: Code Quality, SEO, Performance, Accessibility, Security (3 pairs), Mobile & Responsive, Deployment Readiness.

**All agents are read-only. Zero changes made to the codebase.**

---

## How It Works

1. **Discovery** — You (the orchestrator) read the project root, `package.json`, and main entry files to identify the stack and key paths.
2. **Specialist Agents** — Spawn all 18 agents simultaneously in one parallel message. Each agent checks specific items from a focused angle using grep/read on actual files.
3. **Reconciliation** — Once all 18 agents return, spawn one final reconciler agent that reads every report and produces the consolidated output.

## Hybrid Coverage Model

Each section's agent pairs share a small set of **high-stakes checks** and split the rest:

- **`[SHARED]` checks** appear in *both* agents in a pair. Each verifies independently without knowing the other's result. The reconciler cross-validates: agreement confirms the finding, disagreement is surfaced as disputed. These are the checks where a false "pass" hurts most.
- **Solo checks** appear in only one agent's checklist, for breadth. Reported as-is.

Agents must never skip a `[SHARED]` check — independent double verification is the point.

---

## Agent Report Format

```
SECTION: [section name]
ROLE: [agent role]

CHECK [SHARED]: [item]
STATUS: PASS | FAIL | WARN | SKIP
DETAIL: [file:line if failing — omit if PASS]

CHECK: [solo item]
STATUS: ...
DETAIL: ...
```

- `PASS` — confirmed good from reading actual files
- `FAIL` — confirmed failing, include file:line where applicable
- `WARN` — present but incomplete, uncertain, or could not fully verify
- `SKIP` — not applicable for this stack

If a check cannot be determined from the available files, report `WARN` with "could not verify" — never guess PASS.

---

## Phase 1: Discovery

Before spawning agents, you must:

1. List the project root directory
2. Read `package.json` if present — note framework, key dependencies, scripts
3. Identify main entry files: `index.html`, `src/main.tsx`, `pages/index.tsx`, `app/layout.tsx`, etc.
4. Resolve the stack (e.g. "Next.js 14 + TypeScript + Tailwind CSS")

Use the discovered `[PROJECT_PATH]` and `[STACK]` in every agent prompt below.

---

## Phase 2: Specialist Agents

Spawn all 18 agents in a single parallel message using the Agent tool. Substitute `[PROJECT_PATH]` and `[STACK]` with actual values from Phase 1.

---

### 1A — Code Quality: Structure Inspector

> Read-only audit. Project: [PROJECT_PATH]. Stack: [STACK].
>
> You are the Structure Inspector for Code Quality. Use grep and file reads to check actual source files. Verify every check yourself, including [SHARED] ones.
>
> SECTION: Code Quality
> ROLE: Structure Inspector
>
> CHECK [SHARED]: No console.log, console.warn, or debugger statements in production source
> CHECK [SHARED]: No secrets, API keys, or tokens hardcoded in source — env vars used instead
> CHECK [SHARED]: `.env` is not committed; `.env.example` is committed with placeholder values only
> CHECK: No unused imports in source files
> CHECK: No dead code (unreachable functions, unused variables, orphaned exports)
> CHECK: No commented-out code blocks (3 or more consecutive commented lines)
> CHECK: Component and file names are semantic — no Section1, Card2, temp, test, copy, final, final2, new, untitled
>
> Report using the structured format above, keeping [SHARED] tags.

---

### 1B — Code Quality: Standards Inspector

> Read-only audit. Project: [PROJECT_PATH]. Stack: [STACK].
>
> You are the Standards Inspector for Code Quality. Use grep and file reads to check actual source files. Verify every check yourself, including [SHARED] ones.
>
> SECTION: Code Quality
> ROLE: Standards Inspector
>
> CHECK [SHARED]: No console.log, console.warn, or debugger statements in production source
> CHECK [SHARED]: No secrets, API keys, or tokens hardcoded in source — env vars used instead
> CHECK [SHARED]: `.env` is not committed; `.env.example` is committed with placeholder values only
> CHECK: No hardcoded color values (hex/rgb/hsl literals) outside of CSS custom property definitions
> CHECK: No magic numbers used directly in layout, timing, or spacing
> CHECK: No inline styles overriding design tokens
> CHECK: No TypeScript `any` types without a suppression comment and justification
>
> Report using the structured format above, keeping [SHARED] tags.

---

### 2A — SEO: Technical Inspector

> Read-only audit. Project: [PROJECT_PATH]. Stack: [STACK].
>
> You are the Technical SEO Inspector. Check HTML templates, config files, and the public directory. Verify every check yourself, including [SHARED] ones.
>
> SECTION: SEO
> ROLE: Technical Inspector
>
> CHECK [SHARED]: Every page has a unique `<title>` tag — not identical across pages
> CHECK [SHARED]: Every page has `<meta name="description">` between 120–160 characters
> CHECK [SHARED]: `og:image` file physically exists at the path referenced in meta tags
> CHECK: `robots.txt` exists at root and does not block important pages
> CHECK: `sitemap.xml` exists and is referenced in robots.txt
> CHECK: Canonical URL set on every page via `<link rel="canonical">`
> CHECK: `og:image` is referenced as an absolute URL (not a relative path)
> CHECK: Structured data / JSON-LD present where applicable (Organisation, Article, Product, BreadcrumbList)
>
> Report using the structured format above, keeping [SHARED] tags.

---

### 2B — SEO: Content Inspector

> Read-only audit. Project: [PROJECT_PATH]. Stack: [STACK].
>
> You are the Content SEO Inspector. Check page and template files. Verify every check yourself, including [SHARED] ones.
>
> SECTION: SEO
> ROLE: Content Inspector
>
> CHECK [SHARED]: Every page has a unique `<title>` tag — not identical across pages
> CHECK [SHARED]: Every page has `<meta name="description">` between 120–160 characters
> CHECK [SHARED]: `og:image` file physically exists at the path referenced in meta tags
> CHECK: OG tags present on every page: og:title, og:description, og:image, og:url, og:type
> CHECK: Every image has a descriptive `alt` attribute — not empty, not the filename, not "image" or "photo"
> CHECK: Heading hierarchy is correct: exactly one `<h1>` per page, logical h2 → h3 nesting
> CHECK: No obviously broken internal links (href="#", href="", empty href, placeholder links)
>
> Report using the structured format above, keeping [SHARED] tags.

---

### 3A — Performance: Asset Inspector

> Read-only audit. Project: [PROJECT_PATH]. Stack: [STACK].
>
> You are the Asset Inspector for Performance. Check source files, stylesheets, and the assets/public directory. Verify every check yourself, including [SHARED] ones.
>
> SECTION: Performance
> ROLE: Asset Inspector
>
> CHECK [SHARED]: No render-blocking scripts — all `<script>` tags use defer or async, or are placed before </body>
> CHECK [SHARED]: Images have explicit `width` and `height` attributes or are sized via CSS to prevent layout shift (CLS)
> CHECK [SHARED]: Images below the fold use lazy loading (`loading="lazy"` or framework equivalent)
> CHECK: All images use modern formats — WebP or AVIF, not JPEG/PNG for photos unless legacy-required
> CHECK: Web fonts use `font-display: swap` in CSS or font config
> CHECK: Critical above-fold fonts are preloaded with `<link rel="preload">`
> CHECK: No obviously unused CSS rules or dead style blocks in stylesheets
>
> Report using the structured format above, keeping [SHARED] tags.

---

### 3B — Performance: Load Inspector

> Read-only audit. Project: [PROJECT_PATH]. Stack: [STACK].
>
> You are the Load Inspector for Performance. Check HTML files, component files, and package.json. Verify every check yourself, including [SHARED] ones.
>
> SECTION: Performance
> ROLE: Load Inspector
>
> CHECK [SHARED]: No render-blocking scripts — all `<script>` tags use defer or async, or are placed before </body>
> CHECK [SHARED]: Images have explicit `width` and `height` attributes or are sized via CSS to prevent layout shift (CLS)
> CHECK [SHARED]: Images below the fold use lazy loading (`loading="lazy"` or framework equivalent)
> CHECK: No bloated or redundant dependencies in package.json (multiple date libraries, duplicate utilities, etc.)
> CHECK: Third-party scripts (analytics, chat, ads) loaded asynchronously and not in the critical path
> CHECK: No unnecessary polyfills for browsers that are not being targeted
> CHECK: Build config targets production mode — no dev-only bundles, source maps, or verbose logging shipped
>
> Report using the structured format above, keeping [SHARED] tags.

---

### 4A — Accessibility: Semantics Inspector

> Read-only audit. Project: [PROJECT_PATH]. Stack: [STACK].
>
> You are the Semantics Inspector for Accessibility. Check HTML and component source files. Verify every check yourself, including [SHARED] ones.
>
> SECTION: Accessibility
> ROLE: Semantics Inspector
>
> CHECK [SHARED]: Visible focus states on all focusable elements — no `outline: none` without a custom replacement
> CHECK [SHARED]: Color contrast meets WCAG AA: 4.5:1 for body text, 3:1 for large text and UI components (check CSS variables and values)
> CHECK [SHARED]: All form inputs have an associated `<label>` via `for`/`id` or as a wrapping element
> CHECK: `lang` attribute set on `<html>` element with a correct BCP 47 language code
> CHECK: Skip-to-content link is the first focusable element on every page
> CHECK: Icon-only buttons and icon-only links have `aria-label` or visually-hidden text
> CHECK: Semantic HTML used where appropriate: `<nav>`, `<main>`, `<footer>`, `<header>`, `<article>`, `<section>`
> CHECK: No `role` attributes that unnecessarily override native HTML semantics
>
> Report using the structured format above, keeping [SHARED] tags.

---

### 4B — Accessibility: Visual & Interaction Inspector

> Read-only audit. Project: [PROJECT_PATH]. Stack: [STACK].
>
> You are the Visual & Interaction Inspector for Accessibility. Check CSS files and component source. Verify every check yourself, including [SHARED] ones.
>
> SECTION: Accessibility
> ROLE: Visual & Interaction Inspector
>
> CHECK [SHARED]: Visible focus states on all focusable elements — no `outline: none` without a custom replacement
> CHECK [SHARED]: Color contrast meets WCAG AA: 4.5:1 for body text, 3:1 for large text and UI components (check CSS variables and values)
> CHECK [SHARED]: All form inputs have an associated `<label>` via `for`/`id` or as a wrapping element
> CHECK: No content relies solely on color to convey meaning (error states, status indicators, required fields)
> CHECK: Interactive elements appear in a logical keyboard tab order (DOM order matches visual order)
> CHECK: No `tabindex` values greater than 0 (breaks natural tab order)
> CHECK: Motion/animation respects `prefers-reduced-motion` media query
>
> Report using the structured format above, keeping [SHARED] tags.

---

## Security — Three Agent Pairs

Security gets three dedicated agent pairs covering injection, data exposure, and auth/config. Each pair cross-verifies its own shared checks independently.

---

### 5A — Security: Injection Inspector

> Read-only audit. Project: [PROJECT_PATH]. Stack: [STACK].
>
> You are the Injection Inspector for Security. Think like a penetration tester doing static code analysis. Use grep extensively to find dangerous patterns. Verify every check yourself, including [SHARED] ones.
>
> SECTION: Security
> ROLE: Injection Inspector
>
> CHECK [SHARED]: No `dangerouslySetInnerHTML`, `innerHTML`, `outerHTML`, or `document.write` used with unsanitized user-controlled data
> CHECK [SHARED]: No `eval()`, `new Function()`, `setTimeout(string)`, or `setInterval(string)` with user-controlled input
> CHECK [SHARED]: User-supplied input (forms, URL params, query strings, route params) is validated and sanitized before use
> CHECK: No SQL injection risk — no string concatenation or template literals used to build database queries with user input (parameterized queries or ORM used instead)
> CHECK: No command injection risk — no `child_process.exec()` or `execSync()` with user-controlled arguments (use `execFile` or `spawn` with argument arrays)
> CHECK: No path traversal risk — user-controlled values not used directly in file path operations without normalization (`path.resolve`, `path.normalize`, allowlist validation)
> CHECK: No prototype pollution risk — `Object.assign()`, `_.merge()`, `JSON.parse()` results, or spread operators not applied to user-controlled keys without sanitization
>
> Report using the structured format above, keeping [SHARED] tags.

---

### 5B — Security: XSS & Input Inspector

> Read-only audit. Project: [PROJECT_PATH]. Stack: [STACK].
>
> You are the XSS & Input Inspector for Security. Think like a penetration tester doing static code analysis. Use grep extensively to find dangerous patterns. Verify every check yourself, including [SHARED] ones.
>
> SECTION: Security
> ROLE: XSS & Input Inspector
>
> CHECK [SHARED]: No `dangerouslySetInnerHTML`, `innerHTML`, `outerHTML`, or `document.write` used with unsanitized user-controlled data
> CHECK [SHARED]: No `eval()`, `new Function()`, `setTimeout(string)`, or `setInterval(string)` with user-controlled input
> CHECK [SHARED]: User-supplied input (forms, URL params, query strings, route params) is validated and sanitized before use
> CHECK: No open redirect risk — redirects using user-controlled destination URLs are validated against an allowlist
> CHECK: No ReDoS risk — complex nested or backtracking regex patterns (`(a+)+`, `(.+)*`, `(a|aa)+`) not applied to user-controlled input
> CHECK: All external links use `rel="noopener noreferrer"`
> CHECK: No mixed content — no HTTP asset URLs on what will be an HTTPS site
> CHECK: No server-side template injection — user input not directly interpolated into template strings that are evaluated server-side
>
> Report using the structured format above, keeping [SHARED] tags.

---

### 5C — Security: Secrets & Data Exposure Inspector

> Read-only audit. Project: [PROJECT_PATH]. Stack: [STACK].
>
> You are the Secrets & Data Exposure Inspector for Security. Think like a penetration tester reviewing source for sensitive data leaks. Use grep extensively. Verify every check yourself, including [SHARED] ones.
>
> SECTION: Security
> ROLE: Secrets & Data Exposure Inspector
>
> CHECK [SHARED]: No API keys, tokens, passwords, or credentials in committed source files — grep for: `sk-`, `pk-`, `key=`, `secret=`, `password=`, `token=`, `_KEY`, `_SECRET`, `_TOKEN`, `AUTH_`, `Bearer `, `api_key`, `apikey`, `client_secret`
> CHECK [SHARED]: No sensitive data stored in `localStorage` or `sessionStorage` — tokens, passwords, or PII must not be persisted in browser storage
> CHECK [SHARED]: No stack traces, internal file paths, DB schema details, or technology version strings exposed to the client in error handling code
> CHECK: `.env` not committed — confirm it is listed in `.gitignore`; `.env.example` exists with placeholder values
> CHECK: No PII logged via `console.log` or logging libraries — email addresses, phone numbers, SSNs, credit card numbers must not appear in log calls
> CHECK: No sensitive data in URL parameters — passwords, tokens, or session IDs must not be passed as GET parameters
> CHECK: No internal IP addresses, server hostnames, or database connection strings present in client-side code
>
> Report using the structured format above, keeping [SHARED] tags.

---

### 5D — Security: Information Disclosure Inspector

> Read-only audit. Project: [PROJECT_PATH]. Stack: [STACK].
>
> You are the Information Disclosure Inspector for Security. Think like a penetration tester looking for what an attacker could learn from the codebase. Use grep extensively. Verify every check yourself, including [SHARED] ones.
>
> SECTION: Security
> ROLE: Information Disclosure Inspector
>
> CHECK [SHARED]: No API keys, tokens, passwords, or credentials in committed source files — grep for: `sk-`, `pk-`, `key=`, `secret=`, `password=`, `token=`, `_KEY`, `_SECRET`, `_TOKEN`, `AUTH_`, `Bearer `, `api_key`, `apikey`, `client_secret`
> CHECK [SHARED]: No sensitive data stored in `localStorage` or `sessionStorage` — tokens, passwords, or PII must not be persisted in browser storage
> CHECK [SHARED]: No stack traces, internal file paths, DB schema details, or technology version strings exposed to the client in error handling code
> CHECK: Error messages shown to users are generic — not revealing database structure, file system paths, or framework internals
> CHECK: No commented-out code containing credentials, internal URLs, debug tokens, or admin paths
> CHECK: Source maps (`.map` files) are not shipped to production — they expose original source code
> CHECK: No debug endpoints, test routes, or admin panels accessible without authentication (check route definitions)
> CHECK: `package.json` or `package-lock.json` not publicly accessible at a URL that exposes full dependency list and versions
>
> Report using the structured format above, keeping [SHARED] tags.

---

### 5E — Security: Authentication & Session Inspector

> Read-only audit. Project: [PROJECT_PATH]. Stack: [STACK].
>
> You are the Authentication & Session Inspector for Security. Think like a penetration tester reviewing auth flows for weaknesses. Use grep and file reads on route handlers, middleware, and auth utilities. Verify every check yourself, including [SHARED] ones.
>
> SECTION: Security
> ROLE: Authentication & Session Inspector
>
> CHECK [SHARED]: CORS not configured with wildcard `*` for credentialed requests — check API route config, `cors()` middleware, and framework CORS settings
> CHECK [SHARED]: JWT usage is secure — no `alg: "none"` accepted, expiry (`exp`) is set, secrets are not hardcoded and are sufficiently long
> CHECK [SHARED]: CSRF protection present on all state-changing forms and API endpoints — check for CSRF tokens, `SameSite` cookie attributes, or framework-level CSRF middleware
> CHECK: Authentication checks are enforced server-side, not client-side only — no auth logic that only hides UI without a server gate
> CHECK: Session tokens and auth cookies stored with `httpOnly` and `Secure` flags — not stored in `localStorage`
> CHECK: Password hashing uses a strong algorithm — bcrypt, argon2, or scrypt — not MD5, SHA1, plain SHA256, or unsalted hashes
> CHECK: No IDOR risk — sequential or predictable resource IDs (1, 2, 3...) are validated for ownership server-side before returning data
> CHECK: `Math.random()` not used for security-sensitive operations (tokens, OTPs, nonces) — use `crypto.randomBytes` or `crypto.randomUUID`
>
> Report using the structured format above, keeping [SHARED] tags.

---

### 5F — Security: Headers & Config Inspector

> Read-only audit. Project: [PROJECT_PATH]. Stack: [STACK].
>
> You are the Headers & Config Inspector for Security. Think like a penetration tester checking for misconfiguration. Check `next.config.js`, `vercel.json`, `netlify.toml`, `.htaccess`, server middleware, and HTML meta tags. Verify every check yourself, including [SHARED] ones.
>
> SECTION: Security
> ROLE: Headers & Config Inspector
>
> CHECK [SHARED]: CORS not configured with wildcard `*` for credentialed requests — check API route config, `cors()` middleware, and framework CORS settings
> CHECK [SHARED]: JWT usage is secure — no `alg: "none"` accepted, expiry (`exp`) is set, secrets are not hardcoded and are sufficiently long
> CHECK [SHARED]: CSRF protection present on all state-changing forms and API endpoints — check for CSRF tokens, `SameSite` cookie attributes, or framework-level CSRF middleware
> CHECK: Security headers configured: `Content-Security-Policy`, `X-Frame-Options` (or `frame-ancestors` in CSP), `X-Content-Type-Options: nosniff`, `Strict-Transport-Security`, `Referrer-Policy`
> CHECK: `Content-Security-Policy` does not use `unsafe-inline` or `unsafe-eval` unless absolutely necessary and documented
> CHECK: External scripts loaded with Subresource Integrity (`integrity` + `crossorigin` attributes) where possible
> CHECK: No `npm audit` high or critical vulnerabilities — check `package-lock.json` for known vulnerable versions
> CHECK: No `--legacy-peer-deps` or `--force` flags in npm scripts that suppress dependency conflict errors
>
> Report using the structured format above, keeping [SHARED] tags.

---

### 6A — Mobile & Responsive: Layout Inspector

> Read-only audit. Project: [PROJECT_PATH]. Stack: [STACK].
>
> You are the Layout Inspector for Mobile & Responsive. Check HTML templates and CSS/Tailwind files. Verify every check yourself, including [SHARED] ones.
>
> SECTION: Mobile & Responsive
> ROLE: Layout Inspector
>
> CHECK [SHARED]: Viewport meta tag is present and correct: `<meta name="viewport" content="width=device-width, initial-scale=1">` — and does NOT include `user-scalable=no` or `maximum-scale=1`
> CHECK [SHARED]: No horizontal overflow on narrow screens — no fixed pixel widths on layout containers, no content wider than viewport, no `overflow-x: hidden` used as a band-aid
> CHECK [SHARED]: All interactive elements (buttons, links, inputs) have a minimum 44×44px touch target size
> CHECK: Media queries or responsive utilities cover all target breakpoints: 375px, 768px, 1024px, 1440px
> CHECK: Images are responsive — `max-width: 100%`, `width: 100%`, or equivalent — no fixed-width images that break mobile
> CHECK: Flexbox/Grid layouts have appropriate `flex-wrap`, `grid-template-columns`, or collapse rules for narrow viewports
>
> Report using the structured format above, keeping [SHARED] tags.

---

### 6B — Mobile & Responsive: Interaction Inspector

> Read-only audit. Project: [PROJECT_PATH]. Stack: [STACK].
>
> You are the Interaction Inspector for Mobile & Responsive. Check CSS and component files for mobile interaction patterns. Verify every check yourself, including [SHARED] ones.
>
> SECTION: Mobile & Responsive
> ROLE: Interaction Inspector
>
> CHECK [SHARED]: Viewport meta tag is present and correct: `<meta name="viewport" content="width=device-width, initial-scale=1">` — and does NOT include `user-scalable=no` or `maximum-scale=1`
> CHECK [SHARED]: No horizontal overflow on narrow screens — no fixed pixel widths on layout containers, no content wider than viewport, no `overflow-x: hidden` used as a band-aid
> CHECK [SHARED]: All interactive elements (buttons, links, inputs) have a minimum 44×44px touch target size
> CHECK: Sufficient spacing between adjacent tap targets — minimum 8px gap between clickable elements
> CHECK: Input font size is at least 16px on mobile — prevents iOS Safari auto-zoom on focus
> CHECK: Navigation is functional on mobile (hamburger menu, bottom nav, drawer, or equivalent — not a desktop-only nav bar)
> CHECK: No hover-only interactive states with no touch or focus equivalent
>
> Report using the structured format above, keeping [SHARED] tags.

---

### 7A — Deployment Readiness: File Completeness Inspector

> Read-only audit. Project: [PROJECT_PATH]. Stack: [STACK].
>
> You are the File Completeness Inspector for Deployment Readiness. Check the project root, public directory, and output config. Verify every check yourself, including [SHARED] ones.
>
> SECTION: Deployment Readiness
> ROLE: File Completeness Inspector
>
> CHECK [SHARED]: No placeholder content anywhere: Lorem ipsum, [Client name], [URL], [Email], example.com (outside of .example files)
> CHECK [SHARED]: No staging URLs or localhost references (localhost, 127.0.0.1, .local, staging.) in production code
> CHECK [SHARED]: Custom 404 page exists and is wired up (404.html, pages/404.tsx, app/not-found.tsx, etc.)
> CHECK: `favicon.ico` or `favicon.svg` present at the correct location for this stack
> CHECK: `README.md` exists and contains actual setup and run instructions (not just a framework default)
> CHECK: `.gitignore` covers: node_modules/, dist/, .env, build artifacts, .DS_Store, *.log
> CHECK: `robots.txt` and `sitemap.xml` are present in the public or output directory
>
> Report using the structured format above, keeping [SHARED] tags.

---

### 7B — Deployment Readiness: Content Hygiene Inspector

> Read-only audit. Project: [PROJECT_PATH]. Stack: [STACK].
>
> You are the Content Hygiene Inspector for Deployment Readiness. Grep across all source files. Verify every check yourself, including [SHARED] ones.
>
> SECTION: Deployment Readiness
> ROLE: Content Hygiene Inspector
>
> CHECK [SHARED]: No placeholder content anywhere: Lorem ipsum, [Client name], [URL], [Email], example.com (outside of .example files)
> CHECK [SHARED]: No staging URLs or localhost references (localhost, 127.0.0.1, .local, staging.) in production code
> CHECK [SHARED]: Custom 404 page exists and is wired up (404.html, pages/404.tsx, app/not-found.tsx, etc.)
> CHECK: No TODO, FIXME, or HACK comments remaining in production source files
> CHECK: No hardcoded "test", "demo", or "staging" copy visible in UI components
> CHECK: No placeholder images from picsum.photos, placehold.co, via.placeholder.com, lorempixel.com
> CHECK: All `<title>`, `<meta description>`, and OG content is real — not template placeholder text
>
> Report using the structured format above, keeping [SHARED] tags.

---

## Phase 3: Reconciler Agent

After all 18 agents have returned, spawn one final reconciler agent. Inject all 18 reports into its prompt verbatim.

**Reconciler prompt:**

> You are the Reconciler for a full handoff audit. You have received 18 specialist agent reports below.
> Your job is to reconcile findings and produce the final audit report.
>
> [INSERT ALL 18 AGENT REPORTS HERE VERBATIM]
>
> ## Reconciliation Rules
>
> **Shared checks** are tagged `[SHARED]` and appear in both agents of a pair. Match them by tag and description within each pair, then apply:
>
> | Agent A | Agent B | Final Status |
> |---------|---------|--------------|
> | PASS | PASS | ✅ Pass (cross-verified) |
> | FAIL | FAIL | ❌ Fail — merge both details |
> | WARN | WARN | ⚠️ Needs attention — merge details |
> | FAIL | PASS | ⚠️ Disputed — show both perspectives |
> | PASS | FAIL | ⚠️ Disputed — show both perspectives |
> | FAIL | WARN | ❌ Fail — use more severe detail |
> | WARN | PASS | ⚠️ Needs attention — use warning detail |
> | SKIP (either) | any | — Not applicable |
>
> If a `[SHARED]` check appears in only one agent's report, include it but flag: "⚠️ single-agent only — counterpart did not report".
>
> **Solo checks** (untagged): include as-is with the agent's reported status.
>
> ## Output Format
>
> ---
> # Handoff Audit — [Project Name]
> **Stack:** [STACK]
> **Audited:** [today's date]
> **Agents:** 18 specialist agents | 9 sections
>
> ## 1. Code Quality
> [reconciled items — mark cross-verified passes with "(×2)"]
>
> ## 2. SEO
>
> ## 3. Performance
>
> ## 4. Accessibility
>
> ## 5. Security
> ### Injection & XSS
> ### Secrets & Data Exposure
> ### Auth & Config
>
> ## 6. Mobile & Responsive
>
> ## 7. Deployment Readiness
>
> ---
>
> ## Summary
> ✅ [X] passing ([X] cross-verified)
> ⚠️ [X] need attention
> ❌ [X] failing
> — [X] not applicable
>
> ## Priority Fixes Before Handoff
> [Ordered: ❌ by severity first, then ⚠️. Each item: section, what's wrong, file:line if known.]
>
> ## Disputed Findings (Manual Review Required)
> [Any [SHARED] items where the two agents disagreed — what each found, recommended next step.]
> ---

---

## Orchestrator Notes

- If a section is entirely not applicable (e.g. pure static site — no auth/JWT), instruct those agents to SKIP and note it.
- If the project has no `package.json`, skip all dependency/npm checks and mark SKIP.
- 18 specialist agents + 1 reconciler = 19 total Agent tool calls. All 18 specialists run in parallel. Reconciler runs after all 18 complete.
- Do not commit anything. Do not suggest refactors. Report only.
