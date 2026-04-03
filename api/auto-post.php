<?php
set_time_limit(90);

$is_cli = php_sapi_name() === 'cli';

if (!$is_cli) {
    header('Content-Type: application/json');
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

if (empty($config['enabled'])) {
    respond(200, ['ok' => false, 'error' => 'Auto-post is disabled.']);
}

$anthropic_key = $config['anthropic_api_key'] ?? '';
$openai_key    = $config['openai_api_key']    ?? '';
$model         = $config['model']             ?? 'claude-haiku-4-5-20251001';

if (!$anthropic_key) {
    respond(503, ['ok' => false, 'error' => 'Anthropic API key not set.']);
}

$phase = $is_cli ? 'all' : ($_GET['phase'] ?? 'all');

require __DIR__ . '/db.php';

/* ════════════════════════════════════════════════════
   PHASE 1 — Claude generates the post content
════════════════════════════════════════════════════ */
if ($phase === '1' || $phase === 'all') {

    $prompt = <<<PROMPT
You are a blog writer for Denny Pratama, a UI/UX designer and developer based in Indonesia.
Write an original, insightful, and practical blog post on ONE of these topics: UI/UX design, web development, or AI tools for designers and developers.

Return ONLY a valid JSON object with these exact fields:
{
  "title": "The post title (engaging, specific, not generic)",
  "slug": "url-friendly-slug-from-title",
  "excerpt": "A compelling 2-sentence summary for the blog listing page.",
  "category": "uiux or development or ai",
  "image_prompt": "A concise DALL-E prompt for a clean, modern, minimal blog header image. No text, no people, abstract or conceptual.",
  "body": "Full blog post in HTML. Use <h2>, <h3>, <p>, <ul>, <li>, <strong>, <em>, <blockquote> tags only. Minimum 600 words. No inline styles."
}

Do not include any markdown, explanation, or text outside the JSON object.
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
        CURLOPT_TIMEOUT        => 60,
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
        respond(502, ['ok' => false, 'error' => 'Claude API failed: ' . $curl_err]);
    }

    $claude_resp = json_decode($claude_raw, true);
    $raw_content = $claude_resp['content'][0]['text'] ?? '';
    $raw_content = preg_replace('/^```(?:json)?\s*/i', '', trim($raw_content));
    $raw_content = preg_replace('/\s*```$/', '', $raw_content);
    $post_data   = json_decode(trim($raw_content), true);

    if (!$post_data || empty($post_data['title']) || empty($post_data['body'])) {
        respond(502, ['ok' => false, 'error' => 'Claude returned invalid content.', 'raw' => substr($raw_content, 0, 300)]);
    }

    /* Sanitize and build slug */
    $title    = trim(strip_tags($post_data['title']));
    $excerpt  = trim(strip_tags($post_data['excerpt']));
    $category = in_array($post_data['category'], ['uiux', 'development', 'ai']) ? $post_data['category'] : 'ai';
    $allowed  = '<h2><h3><p><ul><ol><li><strong><em><blockquote><a><br>';
    $body     = strip_tags($post_data['body'], $allowed) . "\n<!-- auto-generated -->";

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
   PHASE 2 — DALL-E generates the featured image
════════════════════════════════════════════════════ */
if ($phase === '2' || $phase === 'all') {

    if ($phase === '2') {
        /* Browser mode: read post_id and image_prompt from GET */
        $post_id      = (int)($_GET['post_id']      ?? 0);
        $image_prompt = trim($_GET['image_prompt']  ?? '');
        if (!$post_id) respond(400, ['ok' => false, 'error' => 'post_id required for phase 2.']);
    }
    /* CLI/all mode: $post_id and $image_prompt already set above */

    $featured_image = '';

    if ($openai_key && $image_prompt) {
        $ch = curl_init('https://api.openai.com/v1/images/generations');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode([
                'model'           => 'dall-e-3',
                'prompt'          => $image_prompt,
                'n'               => 1,
                'size'            => '1792x1024',
                'quality'         => 'standard',
                'response_format' => 'url',
            ]),
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $openai_key,
            ],
        ]);
        $dalle_raw = curl_exec($ch);
        curl_close($ch);

        $image_url = json_decode($dalle_raw, true)['data'][0]['url'] ?? '';

        if ($image_url) {
            $ch = curl_init($image_url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT        => 30,
            ]);
            $image_data = curl_exec($ch);
            curl_close($ch);

            if ($image_data) {
                $uploads_dir = __DIR__ . '/../admin/uploads/';
                if (!is_dir($uploads_dir)) mkdir($uploads_dir, 0755, true);
                $featured_image = 'img_' . uniqid('', true) . '.jpg';
                file_put_contents($uploads_dir . $featured_image, $image_data);

                $pdo->prepare('UPDATE posts SET featured_image = ? WHERE id = ?')
                    ->execute([$featured_image, $post_id]);
            }
        }
    }

    /* Update last_run */
    $config['last_run'] = date('Y-m-d H:i:s');
    file_put_contents($config_file, json_encode($config, JSON_PRETTY_PRINT));

    respond(200, [
        'ok'    => true,
        'phase' => 2,
        'image' => $featured_image ?: null,
    ]);
}

/* ── Helpers ── */
function respond(int $code, array $data): void {
    global $is_cli;
    if (!$is_cli) http_response_code($code);
    echo json_encode($data) . "\n";
    exit;
}
