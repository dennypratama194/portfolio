<?php
set_time_limit(120);
header('Content-Type: application/json');

/* ── Load config ── */
$config_file = __DIR__ . '/.auto_post_config.json';
if (!file_exists($config_file)) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'Auto-post not configured yet.']);
    exit;
}
$config = json_decode(file_get_contents($config_file), true);

/* ── Verify secret token ── */
$token = $_GET['token'] ?? '';
if (!$token || !isset($config['token']) || !hash_equals($config['token'], $token)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden.']);
    exit;
}

/* ── Check enabled ── */
if (empty($config['enabled'])) {
    echo json_encode(['ok' => false, 'error' => 'Auto-post is disabled.']);
    exit;
}

$anthropic_key = $config['anthropic_api_key'] ?? '';
$openai_key    = $config['openai_api_key']    ?? '';
$model         = $config['model']             ?? 'claude-haiku-4-5-20251001';

if (!$anthropic_key) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'Anthropic API key not set.']);
    exit;
}

/* ════════════════════════════════════
   STEP 1 — Generate post with Claude
════════════════════════════════════ */
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

$claude_payload = json_encode([
    'model'      => $model,
    'max_tokens' => 4000,
    'messages'   => [['role' => 'user', 'content' => $prompt]],
]);

$ch = curl_init('https://api.anthropic.com/v1/messages');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $claude_payload,
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
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => 'Claude API request failed: ' . $curl_err]);
    exit;
}

$claude_resp = json_decode($claude_raw, true);
$raw_content = $claude_resp['content'][0]['text'] ?? '';

/* Strip markdown code fences if Claude wrapped the JSON */
$raw_content = preg_replace('/^```(?:json)?\s*/i', '', trim($raw_content));
$raw_content = preg_replace('/\s*```$/', '', $raw_content);

$post_data = json_decode(trim($raw_content), true);

if (!$post_data || empty($post_data['title']) || empty($post_data['body'])) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => 'Claude returned invalid JSON.', 'raw' => substr($raw_content, 0, 300)]);
    exit;
}

/* ════════════════════════════════════
   STEP 2 — Generate image with DALL-E
════════════════════════════════════ */
$featured_image = '';

if ($openai_key && !empty($post_data['image_prompt'])) {
    $dalle_payload = json_encode([
        'model'           => 'dall-e-3',
        'prompt'          => $post_data['image_prompt'],
        'n'               => 1,
        'size'            => '1792x1024',
        'quality'         => 'standard',
        'response_format' => 'url',
    ]);

    $ch = curl_init('https://api.openai.com/v1/images/generations');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $dalle_payload,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $openai_key,
        ],
    ]);
    $dalle_raw = curl_exec($ch);
    curl_close($ch);

    $dalle_resp  = json_decode($dalle_raw, true);
    $image_url   = $dalle_resp['data'][0]['url'] ?? '';

    if ($image_url) {
        /* Download and save image */
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
            $filename       = 'img_' . uniqid('', true) . '.jpg';
            file_put_contents($uploads_dir . $filename, $image_data);
            $featured_image = $filename;
        }
    }
}

/* ════════════════════════════════════
   STEP 3 — Save to database
════════════════════════════════════ */
require __DIR__ . '/db.php';

/* Sanitize fields */
$title    = trim(strip_tags($post_data['title']));
$excerpt  = trim(strip_tags($post_data['excerpt']));
$category = in_array($post_data['category'], ['uiux', 'development', 'ai'])
            ? $post_data['category'] : 'ai';

/* Clean slug */
$slug_base = preg_replace('/[^a-z0-9]+/', '-', strtolower(trim($post_data['slug'])));
$slug_base = trim($slug_base, '-');
if (!$slug_base) $slug_base = preg_replace('/[^a-z0-9]+/', '-', strtolower($title));

/* Ensure slug uniqueness */
$slug    = $slug_base;
$attempt = 1;
while (true) {
    $chk = $pdo->prepare('SELECT id FROM posts WHERE slug = ?');
    $chk->execute([$slug]);
    if (!$chk->fetch()) break;
    $attempt++;
    $slug = $slug_base . '-' . $attempt;
}

/* Allowed HTML tags for body */
$allowed_tags = '<h2><h3><p><ul><ol><li><strong><em><blockquote><a><br>';
$body = strip_tags($post_data['body'], $allowed_tags);

/* Append auto-generated marker */
$body .= "\n<!-- auto-generated -->";

/* Insert post */
try {
    $pdo->prepare(
        'INSERT INTO posts (title, slug, excerpt, body, featured_image, category, is_published, published_at)
         VALUES (?, ?, ?, ?, ?, ?, 1, NOW())'
    )->execute([$title, $slug, $excerpt, $body, $featured_image, $category]);

    /* Update last_run in config */
    $config['last_run'] = date('Y-m-d H:i:s');
    file_put_contents($config_file, json_encode($config, JSON_PRETTY_PRINT));

    echo json_encode([
        'ok'    => true,
        'title' => $title,
        'slug'  => $slug,
        'image' => $featured_image ?: null,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'DB error: ' . $e->getMessage()]);
}
