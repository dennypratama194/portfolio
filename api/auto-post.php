<?php
/* ════════════════════════════════════════════════════════════════
   Auto-post v2 — AI blog publishing (Claude text + gpt-image-2)

   Entry modes (token-authed):
     ?token=X                     cron  → phase all (text + image)
     ?token=X&phase=1             admin → generate + publish post
     ?token=X&phase=2&post_id&image_prompt   admin → featured image
     ?token=X&phase=regen&post_id admin → rebuild image for a post
     ?token=X&phase=reformat&post_id  admin → repair code blocks (no save)
     CLI: php auto-post.php TOKEN → phase all

   v2 design notes (why this file looks the way it does):
   - Claude is called with stream:true. Bytes arrive continuously, and
     heartbeat() relays a space to the browser every ~10s — Cloudflare,
     LiteSpeed and the browser all see a live connection for the whole
     60–180s generation. Non-streaming was silent until done and got
     killed by every layer in turn.
   - Claude returns ===SECTION=== markers, NOT JSON. A 1500-word HTML
     body inside a JSON string breaks on unescaped quotes / newlines /
     truncation; plain markers cannot break. Missing ===END=== or a
     max_tokens stop reason is reported explicitly.
   - Every response and log line carries AP_VERSION so a stale deploy
     is detectable at a glance.
════════════════════════════════════════════════════════════════ */

const AP_VERSION = '2.1';
set_time_limit(300);

/* If the browser or a proxy drops mid-run, FINISH ANYWAY — the tokens are
   already paid for. The post publishes server-side and appears on reload. */
ignore_user_abort(true);

$is_cli = php_sapi_name() === 'cli';

if (!$is_cli) {
    /* Commit status + headers now, before the long API calls start.
       Compression off — heartbeat bytes must reach the wire, not a gzip buffer. */
    ini_set('zlib.output_compression', '0');
    if (function_exists('apache_setenv')) @apache_setenv('no-gzip', '1');
    header('Content-Type: application/json');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('X-Accel-Buffering: no');
    http_response_code(200);
    echo "\n";  // JSON.parse tolerates leading whitespace
    if (ob_get_level()) ob_end_flush();
    flush();
    ob_start(); // capture stray PHP warnings so they can't corrupt the JSON body
}

/* ── Config + auth ─────────────────────────────────── */
$config_file = __DIR__ . '/.auto_post_config.json';
if (!file_exists($config_file)) {
    respond(503, ['ok' => false, 'error' => 'Auto-post not configured yet.']);
}
/* BOM-strip before decoding: a UTF-8 BOM (added by many Windows editors)
   makes json_decode fail, silently turning every request into "Forbidden". */
$config = json_decode(ltrim(file_get_contents($config_file), "\xEF\xBB\xBF"), true);
if (!is_array($config)) {
    respond(500, ['ok' => false, 'error' => 'Config file exists but is not valid JSON — open the admin panel and click Save Settings to rewrite it.']);
}

$token = $is_cli ? ($argv[1] ?? '') : ($_GET['token'] ?? '');
if (!$token || !isset($config['token']) || !hash_equals($config['token'], $token)) {
    respond(403, ['ok' => false, 'error' => 'Forbidden.']);
}

$anthropic_key = $config['anthropic_api_key'] ?? '';
$openai_key    = $config['openai_api_key']    ?? '';
$model         = $config['model']             ?? 'claude-haiku-4-5-20251001';
$phase         = $is_cli ? 'all' : ($_GET['phase'] ?? 'all');

/* regen/reformat/test/status are manual admin actions or read-only — they
   bypass the enable toggle (it gates publishing, not admin diagnostics). */
if (empty($config['enabled']) && !in_array($phase, ['regen', 'reformat', 'test', 'status'], true)) {
    respond(200, ['ok' => false, 'error' => 'Auto-post is disabled.']);
}

require __DIR__ . '/db.php';
require __DIR__ . '/helpers.php';

/* A "Run start" line with no follow-up = the host killed the process. */
register_shutdown_function(function () use ($phase) {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        autoPostLog('FATAL: ' . $e['message'] . ' @ ' . basename($e['file']) . ':' . $e['line']);
        if (in_array($phase, ['1', '2', 'all'], true)) {
            setRunStatus(['state' => 'error', 'error' => 'PHP fatal: ' . $e['message'], 'done' => true]);
        }
    }
});
if ($phase !== 'status') { // status is polled every few seconds — don't spam the log
    autoPostLog('Run start v' . AP_VERSION . ': phase=' . $phase . ' model=' . $model . ($is_cli ? ' (cli)' : ''));
}

/* ════════════════════════════════════════════════════
   PHASE = STATUS — read-only progress of the current /
   last background run. Polled by the admin UI.
════════════════════════════════════════════════════ */
if ($phase === 'status') {
    $run = null;
    $sf  = __DIR__ . '/logs/run-status.json';
    if (file_exists($sf)) {
        $run = json_decode(ltrim((string)@file_get_contents($sf), "\xEF\xBB\xBF"), true) ?: null;
        if ($run && isset($run['updated'])) $run['age'] = time() - (int)$run['updated'];
    }
    respond(200, ['ok' => true, 'phase' => 'status', 'run' => $run]);
}

/* ════════════════════════════════════════════════════
   PHASE = START — kick off the full generation as a
   BACKGROUND process and return immediately. The admin
   UI then polls phase=status. No browser connection is
   held open during the minutes-long Claude call, so
   host/proxy HTTP timeouts can no longer kill a paid run.
════════════════════════════════════════════════════ */
if ($phase === 'start') {
    if (!$anthropic_key) respond(503, ['ok' => false, 'error' => 'Anthropic API key not set.']);

    $lock_file = __DIR__ . '/logs/run.lock';
    $sf        = __DIR__ . '/logs/run-status.json';
    $cur       = file_exists($sf) ? (json_decode(ltrim((string)@file_get_contents($sf), "\xEF\xBB\xBF"), true) ?: []) : [];

    $lock_busy   = file_exists($lock_file) && time() - filemtime($lock_file) < 600;
    $status_busy = $cur && empty($cur['done']) && isset($cur['updated']) && time() - (int)$cur['updated'] < 300;
    if ($lock_busy || $status_busy) {
        respond(429, ['ok' => false, 'error' => 'A run is already in progress — wait for the status below to finish before starting another.']);
    }

    setRunStatus([
        'state'   => 'starting', 'started' => time(), 'done' => false,
        'error'   => null, 'post_id' => null, 'title' => null,
        'image'   => null, 'image_error' => null,
    ], true);

    $mode = spawnBackgroundRun($token);
    autoPostLog('Background run spawned (' . $mode . ')');
    respond(200, ['ok' => true, 'phase' => 'start', 'mode' => $mode]);
}

/* ════════════════════════════════════════════════════
   PHASE = TEST — near-free pipeline check (~30 tokens).
   Verifies deployed version, API key, and streaming
   connectivity WITHOUT paying for a full post.
════════════════════════════════════════════════════ */
if ($phase === 'test') {
    if (!$anthropic_key) respond(503, ['ok' => false, 'error' => 'Anthropic API key not set.']);
    $t0  = microtime(true);
    $out = claudeCall('Reply with exactly: OK', 16);
    respond(200, [
        'ok'      => true,
        'phase'   => 'test',
        'model'   => $model,
        'claude'  => trim($out),
        'seconds' => round(microtime(true) - $t0, 1),
    ]);
}

/* Single-flight lock: a second click while a run is still generating would
   pay for the same post twice. Stale locks (>10 min) are ignored. */
if ($phase === '1' || $phase === 'all') {
    $lock_file = __DIR__ . '/logs/run.lock';
    if (file_exists($lock_file) && time() - filemtime($lock_file) < 600) {
        respond(429, ['ok' => false, 'error' =>
            'A run is already in progress (started ' . (time() - filemtime($lock_file)) . 's ago). '
            . 'It publishes even if this page showed a connection error — check the posts list before retrying.']);
    }
    if (!is_dir(__DIR__ . '/logs')) @mkdir(__DIR__ . '/logs', 0755, true);
    @file_put_contents($lock_file, date('c'));
    register_shutdown_function(function () use ($lock_file) { @unlink($lock_file); });
}

/* ════════════════════════════════════════════════════
   PHASE = REGEN — rebuild image for an existing post.
   Sets the prompt, then falls through to phase 2.
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
    $phase    = '2';
}

/* ════════════════════════════════════════════════════
   PHASE = REFORMAT — re-wrap stripped code blocks in an
   existing post body. Returns body for review; no save.
════════════════════════════════════════════════════ */
if ($phase === 'reformat') {
    if (!$anthropic_key) respond(503, ['ok' => false, 'error' => 'Anthropic API key not set.']);

    $post_id = (int)($_GET['post_id'] ?? 0);
    if (!$post_id) respond(400, ['ok' => false, 'error' => 'post_id required for reformat.']);

    $stmt = $pdo->prepare('SELECT title, body FROM posts WHERE id = ?');
    $stmt->execute([$post_id]);
    $row = $stmt->fetch();
    if (!$row) respond(404, ['ok' => false, 'error' => 'Post not found.']);

    $prompt = <<<PROMPT
You are repairing a blog post whose code blocks were destroyed when raw HTML was stripped during publishing. Identify everything that should be code (PHP, HTML, CSS, JavaScript, shell, .htaccess, JSON, regex, file paths, variables, function signatures) and re-wrap it as code.

Post title (context only): {$row['title']}

Current broken body HTML:
---
{$row['body']}
---

CRITICAL RULES:
- DO NOT change the prose. Every sentence stays identical except for added code wrapping.
- DO NOT add new content, headings, or commentary.
- Keep existing <h2>, <h3>, <p>, <ul>, <ol>, <li>, <strong>, <em>, <blockquote>, <a>, <br>, <hr> structure where already correct.
- Multi-line code → <pre><code>...</code></pre>. Inline code → <code>...</code>.
- Inside any <pre>/<code>: HTML-escape special characters (&lt; &gt; &amp;).
- Reinsert real newlines inside <pre><code> where the original code clearly had them.
- Keep the trailing "<!-- auto-generated -->" marker if present.
- If a snippet doesn't clearly look like code, leave it as prose.

Return EXACTLY this structure — markers on their own lines, nothing outside them, no markdown fences:

===BODY===
the corrected complete HTML body
===END===
PROMPT;

    $text = claudeCall($prompt, 8192);
    $sec  = parseSections($text);

    if (empty($sec['BODY'])) {
        autoPostLog('Reformat: no BODY section. Preview: ' . substr($text, 0, 300));
        respond(502, ['ok' => false, 'error' => 'Reformat output missing body section.']);
    }

    $new_body = strip_tags(
        sanitizeAutoPostCode($sec['BODY']),
        '<h2><h3><p><ul><ol><li><strong><em><blockquote><a><br><pre><code><hr>'
    );
    if (strpos($row['body'], '<!-- auto-generated -->') !== false
        && strpos($new_body, '<!-- auto-generated -->') === false) {
        $new_body .= "\n<!-- auto-generated -->";
    }

    /* Not saved — the editor loads it into Quill for review. */
    respond(200, ['ok' => true, 'phase' => 'reformat', 'body' => $new_body]);
}

/* ════════════════════════════════════════════════════
   PHASE 1 — Claude writes and publishes the post
════════════════════════════════════════════════════ */
if ($phase === '1' || $phase === 'all') {
    if (!$anthropic_key) respond(503, ['ok' => false, 'error' => 'Anthropic API key not set.']);
    setRunStatus(['state' => 'claude']);

    /* Last 20 published posts: dedup source + internal-link list */
    $recent_posts = $pdo->query(
        "SELECT title, slug, category FROM posts
         WHERE is_published = 1
         ORDER BY COALESCE(published_at, scheduled_at) DESC
         LIMIT 20"
    )->fetchAll();

    /* Hard category rotation: least represented of the last 9 wins */
    $cat_counts = ['uiux' => 0, 'development' => 0, 'ai' => 0];
    foreach (array_slice($recent_posts, 0, 9) as $r) {
        if (isset($cat_counts[$r['category']])) $cat_counts[$r['category']]++;
    }
    asort($cat_counts);
    $required_category = array_key_first($cat_counts);

    /* Topic seeds tied to this site's actual stack */
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
    $seed_list = '- ' . implode("\n- ", $topic_seeds[$required_category]);

    /* Deterministic per-day format rotation */
    $angles = [
        'a practical tutorial with concrete step-by-step examples',
        'an opinion piece backed by real evidence and lived experience',
        'a "before / after" case-study style breakdown',
        'a checklist or pattern catalog (numbered, scannable)',
        'a debugging or post-mortem walkthrough of a real problem',
    ];
    $angle = $angles[(int)date('z') % count($angles)];

    $recent_list = $internal_links = '';
    foreach ($recent_posts as $r) {
        $recent_list    .= '- "' . $r['title'] . '" [' . ($r['category'] ?: '?') . "]\n";
        $internal_links .= '- "' . $r['title'] . '" → https://dennypratama.com/blog/' . rawurlencode($r['slug']) . "\n";
    }
    if ($recent_list === '') {
        $recent_list    = '(no posts yet — this is the first one)';
        $internal_links = '(none yet)';
    }

    /* Generate, retrying once if the title overlaps a recent post */
    $extra_constraint = '';
    $sections = null;

    for ($attempt = 1; $attempt <= 2; $attempt++) {
        $prompt = <<<PROMPT
You are writing as Denny Pratama — a UI/UX designer and developer based in Indonesia with 5+ years of hands-on experience building products in PHP, vanilla JS, modern CSS, GSAP, and MySQL on shared cPanel hosting. You write with genuine first-hand expertise: real bugs, real tradeoffs, opinions formed from actual project work.

REQUIRED CATEGORY for this post: $required_category
Format / angle for this post: $angle

Topic seed list — pick ONE (or invent something equally specific in the same category). Avoid the most obvious/generic choice:
$seed_list

Posts already published — do NOT repeat, rehash, or write a near-variant of any of these:
$recent_list
$extra_constraint
WRITING REQUIREMENTS:
- Minimum 1200 words. Aim for 1400–1600.
- First person where natural: "In my experience...", "I ran into this on a recent project..."
- Be specific: real tools, real error messages, real tradeoffs. No vague generalisations.
- Structure: opening hook → problem framing → practical breakdown (h2/h3 sections) → concrete takeaways → closing paragraph.
- Where genuinely relevant, link to 1–2 existing posts using <a href="URL">anchor text</a>. Only if the topic naturally connects.

Existing posts available for internal linking:
$internal_links

OUTPUT FORMAT — return EXACTLY this structure. Each ===MARKER=== on its own line. No markdown fences, no commentary outside the markers:

===TITLE===
The post title (specific and engaging — not generic)
===SLUG===
url-friendly-slug-from-title
===EXCERPT===
A compelling 2-sentence summary for the blog listing page, under 160 characters total.
===IMAGE_PROMPT===
A concise image-generation prompt for a clean, modern, minimal blog header. No text, no people, abstract or conceptual.
===FAQ===
Q: Question one readers commonly ask about this topic?
A: Direct, complete answer in 2–3 sentences.
Q: Question two?
A: Answer.
Q: Question three?
A: Answer.
===BODY===
Full blog post in HTML. Use ONLY: <h2>, <h3>, <p>, <ul>, <ol>, <li>, <strong>, <em>, <blockquote>, <a href="...">, <pre>, <code>, <hr>. End the body with an <h2>Frequently Asked Questions</h2> section repeating the same 3 Q&As, each as <h3>question</h3><p>answer</p>. Multi-line code → <pre><code>...</code></pre>. Inline code → <code>...</code>. Inside <pre>/<code>: HTML-escape special chars (&lt; &gt; &amp;). No inline styles.
===END===
PROMPT;

        $text = claudeCall($prompt, 8192);

        /* Paid output is never lost: keep the raw text so a failed parse can
           be recovered manually instead of paying for another generation. */
        @file_put_contents(__DIR__ . '/logs/last-run-raw.txt', $text);

        if (strpos($text, '===END===') === false) {
            autoPostLog('Phase 1: output incomplete (no END marker). Tail: ' . substr($text, -300));
            respond(502, ['ok' => false, 'error' => 'Claude output was cut off before finishing. Raw text saved in api/logs/last-run-raw.txt.']);
        }

        $sec = parseSections($text);
        if (empty($sec['TITLE']) || empty($sec['BODY'])) {
            autoPostLog('Phase 1: missing sections. Preview: ' . substr($text, 0, 300));
            respond(502, ['ok' => false, 'error' => 'Claude output missing required sections. Raw text saved in api/logs/last-run-raw.txt.']);
        }

        /* Jaccard word overlap vs recent titles — ≥0.5 means "same topic" */
        $clash = null;
        foreach ($recent_posts as $r) {
            if (titleSimilarity($sec['TITLE'], $r['title']) >= 0.5) { $clash = $r['title']; break; }
        }
        if (!$clash) { $sections = $sec; break; }

        autoPostLog('Attempt ' . $attempt . ' too similar to "' . $clash . '" (proposed: "' . $sec['TITLE'] . '") — retrying.');
        $extra_constraint = "\nThe previous attempt produced \"{$sec['TITLE']}\" which overlaps the existing post \"$clash\". Pick a COMPLETELY DIFFERENT topic this time.\n";
        $sections = $sec; // ship the last candidate if both attempts clash
    }

    /* The Claude call took minutes — the DB connection may have timed out */
    ensureDbAlive();

    /* Sanitize + build post fields */
    $title   = trim(strip_tags($sections['TITLE']));
    $excerpt = trim(strip_tags($sections['EXCERPT'] ?? ''));
    $allowed = '<h2><h3><p><ul><ol><li><strong><em><blockquote><a><br><pre><code><hr>';

    $faq      = parseFaq($sections['FAQ'] ?? '');
    $faq_json = $faq ? "\n<!-- faq:" . json_encode($faq, JSON_UNESCAPED_UNICODE) . ' -->' : '';

    /* sanitizeAutoPostCode must run BEFORE strip_tags so raw HTML examples
       inside <pre>/<code> get entity-encoded instead of stripped. */
    $body = strip_tags(sanitizeAutoPostCode($sections['BODY']), $allowed)
          . "\n<!-- auto-generated -->" . $faq_json;

    $slug_base = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($sections['SLUG'] ?? $title)), '-');
    $slug = $slug_base;
    for ($n = 2; ; $n++) {
        $chk = $pdo->prepare('SELECT id FROM posts WHERE slug = ?');
        $chk->execute([$slug]);
        if (!$chk->fetch()) break;
        $slug = $slug_base . '-' . $n;
    }

    $pdo->prepare(
        'INSERT INTO posts (title, slug, excerpt, body, featured_image, category, is_published, published_at)
         VALUES (?, ?, ?, ?, ?, ?, 1, NOW())'
    )->execute([$title, $slug, $excerpt, $body, '', $required_category]);

    $post_id      = (int)$pdo->lastInsertId();
    $image_prompt = !empty($sections['IMAGE_PROMPT'])
        ? $sections['IMAGE_PROMPT']
        : 'Clean, modern, minimal blog header image, abstract and conceptual, no text, no people. Topic: ' . $title;
    autoPostLog('Post created: id=' . $post_id . ' "' . $title . '"');
    setRunStatus(['state' => 'post_created', 'post_id' => $post_id, 'title' => $title]);

    if ($phase === '1') {
        respond(200, [
            'ok'           => true,
            'phase'        => 1,
            'post_id'      => $post_id,
            'title'        => $title,
            'slug'         => $slug,
            'image_prompt' => $image_prompt,
        ]);
    }
    /* phase=all falls through to the image */
}

/* ════════════════════════════════════════════════════
   PHASE 2 — gpt-image-2 featured image
════════════════════════════════════════════════════ */
if ($phase === '2' || $phase === 'all') {

    if ($phase === '2' && !$is_regen) {
        $post_id      = (int)($_GET['post_id'] ?? 0);
        $image_prompt = trim($_GET['image_prompt'] ?? '');
        if (!$post_id) respond(400, ['ok' => false, 'error' => 'post_id required for phase 2.']);
    }

    if (!$is_regen) setRunStatus(['state' => 'image']);

    $featured_image = '';
    $image_error    = '';

    try { // the post already exists — nothing in the image step may fail the run
    if (!$openai_key) {
        $image_error = 'OpenAI API key not configured.';
        autoPostLog('Phase 2 skipped: ' . $image_error);
    } elseif (!$image_prompt) {
        $image_error = 'No image prompt available.';
        autoPostLog('Phase 2 skipped: ' . $image_error);
    } else {
        /* gpt-image-2 returns base64 in data[0].b64_json — no URL variant. */
        $ch = curl_init('https://api.openai.com/v1/images/generations');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER   => true,
            CURLOPT_POST             => true,
            CURLOPT_POSTFIELDS       => json_encode([
                'model'   => 'gpt-image-2',
                'prompt'  => $image_prompt,
                'n'       => 1,
                'size'    => '1536x1024',
                'quality' => 'medium',
            ]),
            CURLOPT_TIMEOUT          => 120,
            CURLOPT_CONNECTTIMEOUT   => 10,
            /* non-streaming call: heartbeat via progress callback */
            CURLOPT_NOPROGRESS       => false,
            CURLOPT_PROGRESSFUNCTION => function () { heartbeat(); return 0; },
            CURLOPT_HTTPHEADER       => [
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
                $image_error = 'Image API error (HTTP ' . $img_code . '): ' . ($img_json['error']['message'] ?? 'unknown error');
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

                    /* Prefer WebP; fall back to original PNG if GD/WebP is unavailable */
                    $featured_image = convertToWebp($image_data, $uploads_dir, $base);
                    if ($featured_image === null) {
                        autoPostLog('WebP conversion unavailable — saving original PNG.');
                        if (@file_put_contents($uploads_dir . $base . '.png', $image_data) !== false) {
                            $featured_image = $base . '.png';
                        }
                    }

                    if ($featured_image) {
                        /* Image generation waits up to 120s — reconnect if needed */
                        ensureDbAlive();
                        $pdo->prepare('UPDATE posts SET featured_image = ? WHERE id = ?')
                            ->execute([$featured_image, $post_id]);
                        autoPostLog('Image saved: ' . $featured_image . ' → post ' . $post_id);
                    } else {
                        $image_error = 'Could not write the image to admin/uploads/ (check permissions).';
                        autoPostLog('Failed to write image to ' . $uploads_dir);
                    }
                }
            }
        }
    }
    } catch (Throwable $e) {
        $featured_image = '';
        $image_error    = 'Image step crashed: ' . $e->getMessage();
        autoPostLog('Image step crashed: ' . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine());
    }

    /* Update last_run (manual regen is not a cron tick) */
    if (!$is_regen) {
        $config['last_run'] = date('Y-m-d H:i:s');
        file_put_contents($config_file, json_encode($config, JSON_PRETTY_PRINT));
        setRunStatus([
            'state' => 'done', 'done' => true,
            'image' => $featured_image ?: null,
            'image_error' => $image_error ?: null,
        ]);
    }

    respond(200, [
        'ok'          => true,
        'phase'       => 2,
        'image'       => $featured_image ?: null,
        'image_error' => $image_error ?: null,
    ]);
}

respond(400, ['ok' => false, 'error' => 'Unknown phase: ' . $phase]);

/* ════════════════════════════════════════════════════
   Helpers
════════════════════════════════════════════════════ */

function respond(int $code, array $data): void {
    global $is_cli, $phase;
    $data['v'] = AP_VERSION;
    /* A failed generation run must surface in the polled status. 429 is excluded:
       it means "another run is active" and must not overwrite that run's state. */
    if ($code !== 429 && ($data['ok'] ?? true) === false && in_array($phase ?? '', ['1', '2', 'all'], true)) {
        setRunStatus(['state' => 'error', 'error' => (string)($data['error'] ?? 'Unknown error'), 'done' => true]);
    }
    if (!$is_cli) {
        $stray = ob_get_clean();
        if ($stray) $data['_php_warnings'] = substr($stray, 0, 500);
        if (!headers_sent()) http_response_code($code);
    }
    echo json_encode($data) . "\n";
    exit;
}

/* Emits a space every ~10s so every proxy layer sees a live connection.
   Called from streaming/progress callbacks during long API calls. */
function heartbeat(): void {
    global $is_cli;
    static $last = 0;
    if ($is_cli || time() - $last < 10) return;
    $last = time();
    echo ' ';
    if (ob_get_level()) ob_flush();
    flush();
}

/* Streaming call to the Claude API. Returns the full completion text;
   responds with a clear error (and exits) on any failure, including
   truncation at max_tokens. */
function claudeCall(string $prompt, int $max_tokens): string {
    global $anthropic_key, $model;

    $text = ''; $buf = ''; $stop = ''; $raw = ''; $api_err = '';
    $t0 = microtime(true);

    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode([
            'model'      => $model,
            'max_tokens' => $max_tokens,
            'stream'     => true,
            'messages'   => [['role' => 'user', 'content' => $prompt]],
        ]),
        CURLOPT_TIMEOUT        => 240,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'x-api-key: ' . $anthropic_key,
            'anthropic-version: 2023-06-01',
        ],
        CURLOPT_WRITEFUNCTION  => function ($ch, $chunk) use (&$text, &$buf, &$stop, &$raw, &$api_err) {
            if (strlen($raw) < 2000) $raw .= $chunk; // keep head for error reporting
            $buf .= $chunk;
            while (($nl = strpos($buf, "\n")) !== false) {
                $line = trim(substr($buf, 0, $nl));
                $buf  = substr($buf, $nl + 1);
                if (strpos($line, 'data:') !== 0) continue;
                $ev = json_decode(trim(substr($line, 5)), true);
                if (!is_array($ev)) continue;
                switch ($ev['type'] ?? '') {
                    case 'content_block_delta':
                        $text .= $ev['delta']['text'] ?? '';
                        break;
                    case 'message_delta':
                        $stop = $ev['delta']['stop_reason'] ?? $stop;
                        break;
                    case 'error':
                        $api_err = ($ev['error']['type'] ?? 'error') . ': ' . ($ev['error']['message'] ?? '');
                        break;
                }
            }
            heartbeat();
            return strlen($chunk);
        },
    ]);
    curl_exec($ch);
    $err  = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    autoPostLog(sprintf('Claude call: HTTP %d, %.1fs, %d chars, stop=%s%s',
        $code, microtime(true) - $t0, strlen($text), $stop ?: '-', $err ? ' — curl: ' . $err : ''));

    if ($err) {
        respond(502, ['ok' => false, 'error' => 'Claude connection failed: ' . $err]);
    }
    if ($code !== 200 || $api_err) {
        if (!$api_err) {
            $j = json_decode($raw, true);
            $api_err = isset($j['error'])
                ? (($j['error']['type'] ?? 'error') . ': ' . ($j['error']['message'] ?? 'unknown'))
                : ('HTTP ' . $code . ' — ' . substr($raw, 0, 200));
        }
        respond(502, ['ok' => false, 'error' => 'Claude API — ' . $api_err]);
    }
    if ($stop === 'max_tokens') {
        respond(502, ['ok' => false, 'error' => 'Claude output was truncated (max_tokens) — try again.']);
    }
    return $text;
}

/* Splits "===NAME===\ncontent" marker output into [NAME => content]. */
function parseSections(string $text): array {
    $out = [];
    if (preg_match_all('/^===([A-Z_]+)===\s*$(.*?)(?=^===[A-Z_]+===\s*$|\z)/ms', $text, $m, PREG_SET_ORDER)) {
        foreach ($m as $s) {
            if ($s[1] !== 'END') $out[$s[1]] = trim($s[2]);
        }
    }
    return $out;
}

/* "Q: ...\nA: ..." lines → [['q' => ..., 'a' => ...], ...] (multi-line answers ok) */
function parseFaq(string $block): array {
    $faq = []; $q = null; $a = null;
    foreach (preg_split('/\r?\n/', $block) as $line) {
        $line = trim($line);
        if (stripos($line, 'Q:') === 0) {
            if ($q !== null && $a !== null) $faq[] = ['q' => $q, 'a' => trim($a)];
            $q = trim(substr($line, 2)); $a = null;
        } elseif (stripos($line, 'A:') === 0) {
            $a = trim(substr($line, 2));
        } elseif ($a !== null && $line !== '') {
            $a .= ' ' . $line;
        }
    }
    if ($q !== null && $a !== null) $faq[] = ['q' => $q, 'a' => trim($a)];
    return $faq;
}

/* Normalize code blocks: every <pre> gets a single <code> with entity-encoded
   contents; standalone <code> same. Idempotent. Must run BEFORE strip_tags. */
function sanitizeAutoPostCode(string $html): string {
    $html = preg_replace_callback('#<pre(?:\s[^>]*)?>(.*?)</pre>#is', function ($m) {
        $inner = preg_replace('#</?code(?:\s[^>]*)?>#i', '', $m[1]);
        $inner = htmlspecialchars(
            html_entity_decode($inner, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            ENT_QUOTES | ENT_HTML5, 'UTF-8'
        );
        return '<pre><code>' . $inner . '</code></pre>';
    }, $html);
    $html = preg_replace_callback('#<code(?:\s[^>]*)?>(.*?)</code>#is', function ($m) {
        $inner = htmlspecialchars(
            html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            ENT_QUOTES | ENT_HTML5, 'UTF-8'
        );
        return '<code>' . $inner . '</code>';
    }, $html);
    return $html;
}

/* Jaccard word overlap between titles, ignoring filler words. */
function titleSimilarity(string $a, string $b): float {
    static $stop = ['the','a','an','of','for','to','in','on','and','or','your','you','i','is',
                    'are','that','this','with','how','why','what','from','at','by','as','it',
                    'its','be','will','can','if','should','do','does','about'];
    $wa = array_unique(array_diff(preg_split('/[^a-z0-9]+/', strtolower($a), -1, PREG_SPLIT_NO_EMPTY) ?: [], $stop));
    $wb = array_unique(array_diff(preg_split('/[^a-z0-9]+/', strtolower($b), -1, PREG_SPLIT_NO_EMPTY) ?: [], $stop));
    if (!$wa || !$wb) return 0;
    return count(array_intersect($wa, $wb)) / count(array_unique(array_merge($wa, $wb)));
}

function autoPostLog(string $msg): void {
    $dir = __DIR__ . '/logs';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    @file_put_contents($dir . '/auto-post.log', '[' . date('c') . '] ' . $msg . "\n", FILE_APPEND | LOCK_EX);
}

/* Shared-hosting MySQL drops connections that sit idle during the 60–150s
   AI calls ("server has gone away"). Ping and reconnect before using $pdo
   again after any long wait. Mirrors the connection built in db.php. */
function ensureDbAlive(): void {
    global $pdo;
    try { $pdo->query('SELECT 1'); return; } catch (Throwable $e) {}
    autoPostLog('DB connection lost during long API call — reconnecting.');
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
}

/* Merge (or reset) the background-run status file polled by the admin UI. */
function setRunStatus(array $patch, bool $reset = false): void {
    $dir = __DIR__ . '/logs';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $f   = $dir . '/run-status.json';
    $cur = [];
    if (!$reset && file_exists($f)) {
        $cur = json_decode(ltrim((string)@file_get_contents($f), "\xEF\xBB\xBF"), true) ?: [];
    }
    $patch['updated'] = time();
    @file_put_contents($f, json_encode(array_merge($cur, $patch)), LOCK_EX);
}

/* Launch the full generation (phase all) detached from this request.
   Preferred: PHP CLI — immune to every HTTP/gateway/proxy timeout (same
   mechanism as the recommended cron command). Fallback when exec() is
   unavailable: fire-and-forget HTTP self-call; the worker survives the
   1.5s client timeout thanks to ignore_user_abort. */
function spawnBackgroundRun(string $token): string {
    $disabled = array_map('trim', explode(',', (string)ini_get('disable_functions')));
    if (function_exists('exec') && !in_array('exec', $disabled, true) && stripos(PHP_OS, 'WIN') === false) {
        $php = PHP_BINDIR . '/php';
        if (!is_executable($php)) $php = 'php';
        exec(escapeshellarg($php) . ' ' . escapeshellarg(__FILE__) . ' ' . escapeshellarg($token)
            . ' > /dev/null 2>&1 &');
        return 'cli';
    }
    $host = $_SERVER['HTTP_HOST'] ?? 'dennypratama.com';
    $ch = curl_init('https://' . $host . '/api/auto-post.php?token=' . rawurlencode($token) . '&phase=all');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT_MS     => 1500,
        CURLOPT_NOSIGNAL       => true,
        CURLOPT_SSL_VERIFYPEER => false, // self-call via hostname can resolve to the local vhost
    ]);
    @curl_exec($ch);
    curl_close($ch);
    return 'http';
}
