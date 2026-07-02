<?php
set_time_limit(300);

$is_cli = php_sapi_name() === 'cli';

if (!$is_cli) {
    // Send status + Content-Type now so nginx's fastcgi_read_timeout resets before the
    // long Claude / OpenAI calls start. JSON.parse() tolerates a leading newline.
    http_response_code(200);
    header('Content-Type: application/json');
    echo "\n";
    if (ob_get_level()) ob_end_flush();
    flush();
    ob_start(); // capture stray PHP warnings so they don't corrupt the JSON body
}

/* ── Load config ── */
$config_file = __DIR__ . '/.auto_post_config.json';
if (!file_exists($config_file)) {
    respond(503, ['ok' => false, 'error' => 'Auto-post not configured yet.']);
}
$config = json_decode(file_get_contents($config_file), true);

/* ── Auth: token from GET or CLI arg ── */
if ($is_cli) {
    $token = $argv[1] ?? '';
} else {
    $token = $_GET['token'] ?? '';
}

if (!$token || !isset($config['token']) || !hash_equals($config['token'], $token)) {
    respond(403, ['ok' => false, 'error' => 'Forbidden.']);
}

$anthropic_key = $config['anthropic_api_key'] ?? '';
$openai_key    = $config['openai_api_key']    ?? '';
$model         = $config['model']             ?? 'claude-haiku-4-5-20251001';

$phase = $is_cli ? 'all' : ($_GET['phase'] ?? 'all');

/* `regen` and `reformat` are manual admin actions on an existing post — they
   bypass the global enable toggle (it gates cron publishing, not manual repair). */
if (empty($config['enabled']) && !in_array($phase, ['regen', 'reformat'], true)) {
    respond(200, ['ok' => false, 'error' => 'Auto-post is disabled.']);
}

require __DIR__ . '/db.php';
require __DIR__ . '/helpers.php';

/* ════════════════════════════════════════════════════
   PHASE = REGEN — rebuild the featured image for an
   existing post using a prompt synthesised from its
   title + excerpt. Falls through to phase 2 below.
════════════════════════════════════════════════════ */
$is_regen = false;
if ($phase === 'regen') {
    $post_id = (int)($_GET['post_id'] ?? 0);
    if (!$post_id) respond(400, ['ok' => false, 'error' => 'post_id required for regen.']);

    $stmt = $pdo->prepare('SELECT title, excerpt FROM posts WHERE id = ?');
    $stmt->execute([$post_id]);
    $row = $stmt->fetch();
    if (!$row) respond(404, ['ok' => false, 'error' => 'Post not found.']);

    $image_prompt = 'Clean, modern, minimal blog header image, abstract and conceptual, no text, no people. Topic: ' . $row['title'];
    if (!empty($row['excerpt'])) $image_prompt .= '. Context: ' . $row['excerpt'];

    $is_regen = true;
    $phase    = '2';   // fall through to existing image-generation block
}

/* ════════════════════════════════════════════════════
   PHASE = REFORMAT — Claude rewrites an existing post's
   body so previously-stripped code sections get wrapped
   back in <pre><code> with HTML entities. Returns the
   new body for review; does NOT save to DB.
════════════════════════════════════════════════════ */
if ($phase === 'reformat') {
    if (!$anthropic_key) {
        respond(503, ['ok' => false, 'error' => 'Anthropic API key not set.']);
    }

    $post_id = (int)($_GET['post_id'] ?? 0);
    if (!$post_id) respond(400, ['ok' => false, 'error' => 'post_id required for reformat.']);

    $stmt = $pdo->prepare('SELECT title, body FROM posts WHERE id = ?');
    $stmt->execute([$post_id]);
    $row = $stmt->fetch();
    if (!$row) respond(404, ['ok' => false, 'error' => 'Post not found.']);

    $old_body  = $row['body'];
    $old_title = $row['title'];

    $reformat_prompt = <<<PROMPT
You are repairing a blog post whose code blocks were destroyed when raw HTML was stripped during publishing. Your job is to identify everything that should be code (PHP, HTML, CSS, JavaScript, shell/bash, .htaccess directives, JSON, regex, file paths, variables, function signatures, etc.) and re-wrap each one as code.

Post title (for context only): $old_title

Current broken body HTML:
---
$old_body
---

CRITICAL RULES — follow exactly:
- DO NOT change the prose. Keep every sentence and word identical except for adding code wrapping.
- DO NOT add new content, headings, summaries, or commentary.
- DO NOT alter existing <h2>, <h3>, <p>, <ul>, <ol>, <li>, <strong>, <em>, <blockquote>, <a>, <br>, <hr> structure where it's already correct.
- Multi-line code MUST be wrapped in <pre><code>...</code></pre>. Inline code (variables, file paths, short identifiers) in <code>...</code>.
- Inside ANY <pre> or <code>: HTML-escape special characters — &lt; for <, &gt; for >, &amp; for &.
- Reinsert real newlines inside <pre><code> blocks where the original code clearly had them (one statement per line, opening braces on their own line where appropriate, etc.).
- Keep the trailing "<!-- auto-generated -->" marker if it exists in the original body.
- If a snippet doesn't clearly look like code, leave it as prose. Don't over-wrap.

Return ONLY a JSON object with this exact shape:
{"body": "the corrected complete HTML body"}

Do not include markdown fences, commentary, or any text outside the JSON object.
PROMPT;

    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode([
            'model'      => $model,
            'max_tokens' => 8192,
            'messages'   => [['role' => 'user', 'content' => $reformat_prompt]],
        ]),
        CURLOPT_TIMEOUT        => 180,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'x-api-key: ' . $anthropic_key,
            'anthropic-version: 2023-06-01',
        ],
    ]);
    $reformat_raw = curl_exec($ch);
    $reformat_err = curl_error($ch);
    curl_close($ch);

    if ($reformat_err) {
        autoPostLog('Reformat curl error: ' . $reformat_err);
        respond(502, ['ok' => false, 'error' => 'Claude request failed.']);
    }

    $reformat_resp = json_decode($reformat_raw, true);
    $reformat_text = $reformat_resp['content'][0]['text'] ?? '';
    $reformat_text = preg_replace('/^```(?:json)?\s*/i', '', trim($reformat_text));
    $reformat_text = preg_replace('/\s*```$/', '', $reformat_text);
    $reformat_json = json_decode(trim($reformat_text), true);

    if (!$reformat_json || empty($reformat_json['body'])) {
        autoPostLog('Reformat returned invalid JSON. Raw (first 500 chars): ' . substr($reformat_text, 0, 500));
        respond(502, ['ok' => false, 'error' => 'Reformat output was not valid JSON.']);
    }

    $new_body = strip_tags(
        sanitizeAutoPostCode($reformat_json['body']),
        '<h2><h3><p><ul><ol><li><strong><em><blockquote><a><br><pre><code><hr>'
    );

    // Preserve the auto-generated marker if it was in the original
    if (strpos($old_body, '<!-- auto-generated -->') !== false
        && strpos($new_body, '<!-- auto-generated -->') === false) {
        $new_body .= "\n<!-- auto-generated -->";
    }

    /* Intentionally NOT saving to DB. The editor receives the new body, the user
       reviews it in Quill, and saves via the existing form submit. */
    respond(200, [
        'ok'    => true,
        'phase' => 'reformat',
        'body'  => $new_body,
    ]);
}

/* ════════════════════════════════════════════════════
   PHASE 1 — Claude generates the post content
════════════════════════════════════════════════════ */
if ($phase === '1' || $phase === 'all') {

    if (!$anthropic_key) {
        respond(503, ['ok' => false, 'error' => 'Anthropic API key not set.']);
    }

    /* ── Memory: last 20 published posts — used for two things:
       1. Avoid repeating topics (title overlap check)
       2. Build an internal-link list so Claude can reference existing posts ── */
    $recent_stmt = $pdo->query(
        "SELECT title, slug, category FROM posts
         WHERE is_published = 1
         ORDER BY COALESCE(published_at, scheduled_at) DESC
         LIMIT 20"
    );
    $recent_posts = $recent_stmt->fetchAll();

    /* ── Hard category rotation: pick whichever of uiux/development/ai is
       least represented in the last 9 posts. Guarantees development and
       AI posts cycle in instead of UI/UX dominating every run. ── */
    $cat_counts = ['uiux' => 0, 'development' => 0, 'ai' => 0];
    foreach (array_slice($recent_posts, 0, 9) as $r) {
        if (isset($cat_counts[$r['category']])) $cat_counts[$r['category']]++;
    }
    asort($cat_counts); // ascending — least-published first
    $required_category = array_key_first($cat_counts);

    /* ── Topic seeds per category. Tied to the actual stack on this site
       (PHP/MySQL/vanilla JS/GSAP/cPanel) so dev posts read native, not generic. ── */
    $topic_seeds = [
        'uiux' => [
            'empty states and zero-data screens',
            'error states and inline form validation',
            'onboarding sequences and progressive disclosure',
            'microcopy that improves conversion',
            'navigation patterns: sidebar, tabs, command palette',
            'accessibility, focus management, keyboard navigation',
            'dashboard information density and hierarchy',
            'mobile-first interaction patterns',
            'multi-step forms with saved state',
            'search and filtering UX',
            'motion and micro-interactions',
            'design systems and component governance',
            'color contrast and dark mode',
            'web typography hierarchy',
            'pricing pages and checkout flows',
        ],
        'development' => [
            'PHP performance and prepared statements with PDO',
            'MySQL indexing and query optimization',
            'vanilla JS patterns without frameworks',
            'CSS Grid and modern layout techniques',
            'GSAP animation patterns for the web',
            'Core Web Vitals and image optimization (WebP, AVIF)',
            'caching strategies on shared cPanel hosting',
            'security headers, CSRF protection, XSS prevention',
            'HTML and ARIA accessibility implementation',
            'REST API design and JSON conventions',
            'session and authentication patterns in PHP',
            'file upload validation and safe storage',
            'error logging and debugging on production',
            'Git workflows for solo developers',
            'deploying from local to cPanel hosting',
        ],
        'ai' => [
            'using Claude as a design collaborator',
            'prompt engineering for product copy',
            'AI for design QA and accessibility audits',
            'generating realistic placeholder content with AI',
            'AI image generation for blog and marketing assets',
            'building product features with the Claude API',
            'AI ethics and trust in product UX',
            'AI-assisted code review and refactoring',
            'building AI-assisted internal tools',
            'evaluating AI outputs: when to trust, when to verify',
        ],
    ];
    $seed_list = "- " . implode("\n- ", $topic_seeds[$required_category]);

    /* ── Format / angle cycle (day-of-year keeps it deterministic per run) ── */
    $angles = [
        'a practical tutorial with concrete step-by-step examples',
        'an opinion piece backed by real evidence and lived experience',
        'a "before / after" case-study style breakdown',
        'a checklist or pattern catalog (numbered, scannable)',
        'a debugging or post-mortem walkthrough of a real problem',
    ];
    $angle = $angles[(int)date('z') % count($angles)];

    /* ── Build the "avoid repeating" list and the internal-link reference list ── */
    $recent_list  = '';
    $internal_links = '';
    foreach ($recent_posts as $r) {
        $recent_list .= '- "' . $r['title'] . '" [' . ($r['category'] ?: '?') . "]\n";
        $internal_links .= '- "' . $r['title'] . '" → https://dennypratama.com/blog/' . rawurlencode($r['slug']) . "\n";
    }
    if ($recent_list === '') {
        $recent_list    = '(no posts yet — this is the first one)';
        $internal_links = '(none yet)';
    }

    /* ── Generate with one retry if the result word-overlaps a recent title ── */
    $extra_constraint = '';
    $post_data = null;
    $candidate = null;

    for ($attempt = 1; $attempt <= 2; $attempt++) {
        $prompt = <<<PROMPT
You are writing as Denny Pratama — a UI/UX designer and developer based in Indonesia with 5+ years of hands-on experience building products in PHP, vanilla JS, modern CSS, GSAP, and MySQL on shared cPanel hosting. You write with genuine first-hand expertise: you have hit real bugs, made real tradeoffs, and have opinions formed from actual project work.

REQUIRED CATEGORY for this post: $required_category
Format / angle for this post: $angle

Topic seed list — pick ONE (or invent something equally specific within the same category). Avoid the most obvious/generic choice:
$seed_list

Posts already published — do NOT repeat, rehash, or write a near-variant of any of these:
$recent_list
$extra_constraint

WRITING REQUIREMENTS:
- Minimum 1200 words. Aim for 1400–1600.
- Write in first person where natural: "In my experience...", "I ran into this on a recent project...", "The mistake I kept making was..."
- Be specific: name real tools, real error messages, real tradeoffs. Avoid vague generalisations.
- Structure: opening hook → problem framing → practical breakdown (h2/h3 sections) → concrete takeaways → closing paragraph.
- Where genuinely relevant, link to 1–2 existing posts from the list below using <a href="URL">anchor text</a>. Only link if the topic naturally connects — do not force it.

Existing posts available for internal linking:
$internal_links

Return ONLY a valid JSON object with these exact fields:
{
  "title": "The post title (specific and engaging — not generic)",
  "slug": "url-friendly-slug-from-title",
  "excerpt": "A compelling 2-sentence summary for the blog listing page (under 160 chars total).",
  "category": "$required_category",
  "image_prompt": "A concise image-generation prompt for a clean, modern, minimal blog header. No text, no people, abstract or conceptual.",
  "faq": [
    {"q": "Question one readers commonly ask about this topic?", "a": "Direct, complete answer in 2–3 sentences."},
    {"q": "Question two?", "a": "Answer."},
    {"q": "Question three?", "a": "Answer."}
  ],
  "body": "Full blog post in HTML. Use ONLY: <h2>, <h3>, <p>, <ul>, <ol>, <li>, <strong>, <em>, <blockquote>, <a href=\"...\">, <pre>, <code>, <hr>. End the body with an <h2>Frequently Asked Questions</h2> section that contains the same 3 Q&As from the faq field, each as <h3>question</h3><p>answer</p>. Multi-line code → <pre><code>...</code></pre>. Inline code → <code>...</code>. Inside <pre>/<code>: HTML-escape all special chars (&lt; &gt; &amp;). No raw angle brackets inside code. No inline styles."
}

Do not include markdown fences, commentary, or any text outside the JSON object.
PROMPT;

        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode([
                'model'      => $model,
                'max_tokens' => 4000,
                'messages'   => [['role' => 'user', 'content' => $prompt]],
            ]),
            /* Non-streaming: no bytes arrive until the full post is generated.
               1200–1600 words ≈ 40–70s on Haiku, 90s+ on Sonnet — 60s was too tight. */
            CURLOPT_TIMEOUT        => 180,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'x-api-key: ' . $anthropic_key,
                'anthropic-version: 2023-06-01',
            ],
        ]);
        $claude_raw = curl_exec($ch);
        $curl_err   = curl_error($ch);
        curl_close($ch);

        if ($curl_err) {
            autoPostLog('Claude API curl error: ' . $curl_err);
            respond(502, ['ok' => false, 'error' => 'Claude curl error: ' . $curl_err]);
        }

        $claude_resp = json_decode($claude_raw, true);

        /* Surface API-level errors (auth, rate limit, model not found, etc.) */
        if (isset($claude_resp['error'])) {
            $api_err = ($claude_resp['error']['type'] ?? 'error') . ': ' . ($claude_resp['error']['message'] ?? 'unknown');
            autoPostLog('Claude API error: ' . $api_err);
            respond(502, ['ok' => false, 'error' => 'Claude API — ' . $api_err]);
        }

        $raw_content = $claude_resp['content'][0]['text'] ?? '';
        $raw_content = preg_replace('/^```(?:json)?\s*/i', '', trim($raw_content));
        $raw_content = preg_replace('/\s*```$/', '', $raw_content);
        $candidate   = json_decode(trim($raw_content), true);

        if (!$candidate || empty($candidate['title']) || empty($candidate['body'])) {
            $preview = substr($raw_content ?: json_encode($claude_resp), 0, 300);
            autoPostLog('Claude returned invalid content. Raw (first 300 chars): ' . $preview);
            respond(502, ['ok' => false, 'error' => 'Claude returned invalid JSON. Preview: ' . $preview]);
        }

        /* Jaccard word overlap vs every recent title — ≥0.5 means "same topic". */
        $clash = null;
        foreach ($recent_posts as $r) {
            if (titleSimilarity($candidate['title'], $r['title']) >= 0.5) {
                $clash = $r['title'];
                break;
            }
        }
        if (!$clash) { $post_data = $candidate; break; }

        autoPostLog('Attempt ' . $attempt . ' too similar to "' . $clash . '" (proposed: "' . $candidate['title'] . '") — retrying.');
        $extra_constraint = "\nThe previous attempt produced \"" . $candidate['title'] . "\" which overlaps too much with the existing post \"" . $clash . "\". You MUST pick a COMPLETELY DIFFERENT topic this time.\n";
    }

    /* If both attempts clashed, ship the last candidate rather than failing the cron run */
    if (!$post_data) $post_data = $candidate;

    /* Sanitize and build slug — force the rotated category server-side */
    $title    = trim(strip_tags($post_data['title']));
    $excerpt  = trim(strip_tags($post_data['excerpt']));
    $category = $required_category;
    $allowed  = '<h2><h3><p><ul><ol><li><strong><em><blockquote><a><br><pre><code><hr>';
    /* Force code-block contents to be entity-encoded BEFORE strip_tags runs — otherwise
       raw HTML examples inside <pre>/<code> get treated as tags and stripped, leaving
       the code mashed together as plain inline text (the bug we just hit on the blog). */
    /* Embed FAQ as a JSON comment so post.php can extract it for FAQPage schema */
    $faq_json = '';
    if (!empty($post_data['faq']) && is_array($post_data['faq'])) {
        $faq_json = "\n<!-- faq:" . json_encode($post_data['faq'], JSON_UNESCAPED_UNICODE) . " -->";
    }
    $body = strip_tags(sanitizeAutoPostCode($post_data['body']), $allowed) . "\n<!-- auto-generated -->" . $faq_json;

    $slug_base = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($post_data['slug'] ?? $title)), '-');
    $slug = $slug_base; $attempt = 1;
    while (true) {
        $chk = $pdo->prepare('SELECT id FROM posts WHERE slug = ?');
        $chk->execute([$slug]);
        if (!$chk->fetch()) break;
        $slug = $slug_base . '-' . (++$attempt);
    }

    /* Save post (no image yet) */
    $pdo->prepare(
        'INSERT INTO posts (title, slug, excerpt, body, featured_image, category, is_published, published_at)
         VALUES (?, ?, ?, ?, ?, ?, 1, NOW())'
    )->execute([$title, $slug, $excerpt, $body, '', $category]);

    $post_id      = (int)$pdo->lastInsertId();
    $image_prompt = $post_data['image_prompt'] ?? '';

    if ($phase === '1') {
        /* Browser mode: return phase 1 result so JS can call phase 2 */
        respond(200, [
            'ok'           => true,
            'phase'        => 1,
            'post_id'      => $post_id,
            'title'        => $title,
            'slug'         => $slug,
            'image_prompt' => $image_prompt,
        ]);
    }
    /* CLI / 'all' mode: fall through to phase 2 */
}

/* ════════════════════════════════════════════════════
   PHASE 2 — gpt-image-2 generates the featured image
════════════════════════════════════════════════════ */
if ($phase === '2' || $phase === 'all') {

    if ($phase === '2' && !$is_regen) {
        /* Browser mode: read post_id and image_prompt from GET */
        $post_id      = (int)($_GET['post_id']      ?? 0);
        $image_prompt = trim($_GET['image_prompt']  ?? '');
        if (!$post_id) respond(400, ['ok' => false, 'error' => 'post_id required for phase 2.']);
    }
    /* CLI/all and regen: $post_id and $image_prompt already set above */

    $featured_image = '';
    $image_error    = '';

    if (!$openai_key) {
        $image_error = 'OpenAI API key not configured.';
        autoPostLog('Phase 2 skipped: ' . $image_error);
    } elseif (!$image_prompt) {
        $image_error = 'No image prompt was produced in phase 1.';
        autoPostLog('Phase 2 skipped: ' . $image_error);
    } else {
        /* gpt-image-2 (DALL·E was retired 2026-05-12). The API returns the image as
           base64 in data[0].b64_json — there is no URL to download. Note: response_format
           and style are no longer accepted; quality is low|medium|high|auto. */
        $ch = curl_init('https://api.openai.com/v1/images/generations');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode([
                'model'   => 'gpt-image-2',
                'prompt'  => $image_prompt,
                'n'       => 1,
                'size'    => '1536x1024',
                'quality' => 'medium',
            ]),
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $openai_key,
            ],
        ]);
        $img_raw  = curl_exec($ch);
        $img_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $img_err  = curl_error($ch);
        curl_close($ch);

        if ($img_err) {
            $image_error = 'Could not reach the image API.';
            autoPostLog('Image API curl error: ' . $img_err);
        } else {
            $img_json = json_decode($img_raw, true);
            $b64      = $img_json['data'][0]['b64_json'] ?? '';

            if (!$b64) {
                $api_msg     = $img_json['error']['message'] ?? 'unknown error';
                $image_error = 'Image API error (HTTP ' . $img_code . '): ' . $api_msg;
                autoPostLog('Image API HTTP ' . $img_code . ' — ' . substr((string)$img_raw, 0, 800));
            } else {
                $image_data = base64_decode($b64);

                if (!$image_data) {
                    $image_error = 'Image API returned data that could not be decoded.';
                    autoPostLog('base64_decode failed on gpt-image-2 response.');
                } else {
                    $uploads_dir = __DIR__ . '/../admin/uploads/';
                    if (!is_dir($uploads_dir)) @mkdir($uploads_dir, 0755, true);
                    $base = 'img_' . uniqid('', true);

                    /* Prefer WebP (smaller); fall back to the original JPEG if GD/WebP is unavailable */
                    $featured_image = convertToWebp($image_data, $uploads_dir, $base);

                    if ($featured_image === null) {
                        autoPostLog('WebP conversion unavailable — saving original PNG instead.');
                        $candidate = $base . '.png'; // gpt-image-2 returns PNG bytes
                        if (@file_put_contents($uploads_dir . $candidate, $image_data) !== false) {
                            $featured_image = $candidate;
                        }
                    }

                    if ($featured_image) {
                        $pdo->prepare('UPDATE posts SET featured_image = ? WHERE id = ?')
                            ->execute([$featured_image, $post_id]);
                    } else {
                        $image_error = 'Could not write the image to admin/uploads/ (check permissions).';
                        autoPostLog('Failed to write image (webp + jpeg fallback both failed) to ' . $uploads_dir);
                    }
                }
            }
        }
    }

    /* Update last_run (skip for manual regen — that's not a cron tick) */
    if (!$is_regen) {
        $config['last_run'] = date('Y-m-d H:i:s');
        file_put_contents($config_file, json_encode($config, JSON_PRETTY_PRINT));
    }

    respond(200, [
        'ok'          => true,
        'phase'       => 2,
        'image'       => $featured_image ?: null,
        'image_error' => $image_error ?: null,
    ]);
}

/* ── Helpers ── */
function respond(int $code, array $data): void {
    global $is_cli;
    if (!$is_cli) {
        $stray = ob_get_clean(); // discard any PHP warnings so JSON isn't corrupted
        if ($stray) {
            $data['_php_warnings'] = substr($stray, 0, 500); // surface for debugging
        }
        if (!headers_sent()) http_response_code($code);
    }
    echo json_encode($data) . "\n";
    exit;
}

/* Normalize Claude's code blocks: ensure every <pre> contains a single <code> with
   HTML-entity-escaped contents, and any standalone <code> gets the same treatment.
   Idempotent — runs whether Claude already used entities or wrote raw HTML. Must be
   called BEFORE strip_tags so raw `<...>` examples inside code don't get stripped. */
function sanitizeAutoPostCode(string $html): string {
    // <pre>...</pre>: normalize inner to a single <code> with safely-encoded content
    $html = preg_replace_callback(
        '#<pre(?:\s[^>]*)?>(.*?)</pre>#is',
        function ($m) {
            $inner = preg_replace('#</?code(?:\s[^>]*)?>#i', '', $m[1]);
            $inner = htmlspecialchars(
                html_entity_decode($inner, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                ENT_QUOTES | ENT_HTML5, 'UTF-8'
            );
            return '<pre><code>' . $inner . '</code></pre>';
        },
        $html
    );
    // Remaining <code>...</code> (inline) — same encode pass; idempotent on already-safe text
    $html = preg_replace_callback(
        '#<code(?:\s[^>]*)?>(.*?)</code>#is',
        function ($m) {
            $inner = htmlspecialchars(
                html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                ENT_QUOTES | ENT_HTML5, 'UTF-8'
            );
            return '<code>' . $inner . '</code>';
        },
        $html
    );
    return $html;
}

/* Jaccard word overlap on two titles, ignoring filler words. Used to detect
   "same topic, slightly different wording" duplicates before publishing. */
function titleSimilarity(string $a, string $b): float {
    static $stop = ['the','a','an','of','for','to','in','on','and','or','your','you','i','is',
                    'are','that','this','with','how','why','what','from','at','by','as','it',
                    'its','be','will','can','if','should','do','does','about'];
    $wa = array_values(array_unique(array_diff(
        preg_split('/[^a-z0-9]+/', strtolower($a), -1, PREG_SPLIT_NO_EMPTY) ?: [], $stop
    )));
    $wb = array_values(array_unique(array_diff(
        preg_split('/[^a-z0-9]+/', strtolower($b), -1, PREG_SPLIT_NO_EMPTY) ?: [], $stop
    )));
    if (!$wa || !$wb) return 0;
    $intersect = array_intersect($wa, $wb);
    $union     = array_unique(array_merge($wa, $wb));
    return count($intersect) / count($union);
}

function autoPostLog(string $msg): void {
    $dir = __DIR__ . '/logs';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    @file_put_contents($dir . '/auto-post.log', '[' . date('c') . '] ' . $msg . "\n", FILE_APPEND | LOCK_EX);
}
