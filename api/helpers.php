<?php
/**
 * Shared PHP utilities for dennypratama.com
 * Include this file wherever these helpers are needed.
 *
 * Usage:
 *   require __DIR__ . '/api/helpers.php';          // from project root
 *   require __DIR__ . '/../api/helpers.php';        // from admin/ or partials/
 */

/**
 * Escape a string for safe HTML output.
 * Use on every user-supplied value before echoing into HTML.
 */
function escHtml(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Format an ISO/MySQL datetime string to a human-readable date.
 * e.g. "2025-04-10 08:00:00" → "April 10, 2025"
 */
function formatDate(string $date): string {
    return date('F j, Y', strtotime($date));
}

/**
 * Validate a MIME type for uploaded images using finfo (PHP 8.1+ safe).
 * Returns the detected MIME string or false on failure.
 */
function getUploadMime(string $tmp_path): string|false {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $tmp_path);
    finfo_close($finfo);
    return $mime;
}

/**
 * Check whether an uploaded file is an allowed image type.
 * Replaces the deprecated mime_content_type() call.
 */
function isAllowedImage(string $tmp_path): bool {
    $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    return in_array(getUploadMime($tmp_path), $allowed, true);
}

/**
 * Convert raw image bytes to a WebP file on disk.
 * Returns the written filename (e.g. "img_abc.webp") on success, or null if
 * conversion isn't possible (GD/WebP missing, or the bytes aren't a valid image).
 * Caller should fall back to saving the original on null.
 */
function convertToWebp(string $image_data, string $dir, string $basename, int $quality = 82): ?string {
    if (!function_exists('imagewebp') || !function_exists('imagecreatefromstring')) {
        return null; // GD or WebP support unavailable on this host
    }
    $img = @imagecreatefromstring($image_data);
    if ($img === false) return null;

    /* Preserve transparency if the source was a PNG */
    if (function_exists('imagepalettetotruecolor')) @imagepalettetotruecolor($img);
    imagealphablending($img, true);
    imagesavealpha($img, true);

    $filename = $basename . '.webp';
    $ok = @imagewebp($img, rtrim($dir, '/\\') . '/' . $filename, $quality);
    imagedestroy($img);

    return $ok ? $filename : null;
}

/**
 * Slugify a heading into a URL-safe anchor id (e.g. "Why Empty States" → "why-empty-states").
 */
function slugify(string $text): string {
    $text = preg_replace('/[^\p{L}\p{N}]+/u', '-', $text);
    $text = trim((string) $text, '-');
    $text = mb_strtolower($text, 'UTF-8');
    return $text !== '' ? $text : 'section';
}

/**
 * Parse post body HTML, assign stable id="" anchors to each <h2>, and return
 * both the rewritten HTML and a flat table-of-contents list.
 *
 * Returns ['html' => string, 'toc' => [['id' => string, 'text' => string], ...]].
 * On any failure (empty body, no DOM extension) the original HTML is returned
 * with an empty toc, so callers can render unchanged.
 */
function injectHeadingIds(string $html): array {
    if (trim($html) === '' || !class_exists('DOMDocument')) {
        return ['html' => $html, 'toc' => []];
    }

    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML(
        '<?xml encoding="UTF-8">' . '<div id="__pb">' . $html . '</div>',
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
    );
    libxml_clear_errors();

    $xpath = new DOMXPath($dom);
    $root  = $xpath->query('//*[@id="__pb"]')->item(0);
    if (!$root) {
        return ['html' => $html, 'toc' => []];
    }

    $toc  = [];
    $used = [];
    foreach (iterator_to_array($root->getElementsByTagName('h2')) as $h2) {
        $text = trim($h2->textContent);
        if ($text === '') continue;

        $id = $h2->getAttribute('id');
        if ($id === '') {
            $base = slugify($text);
            $id   = $base;
            $n    = 2;
            while (isset($used[$id])) { $id = $base . '-' . $n++; }
            $h2->setAttribute('id', $id);
        }
        $used[$id] = true;
        $toc[] = ['id' => $id, 'text' => $text];
    }

    $out = '';
    foreach ($root->childNodes as $child) {
        $out .= $dom->saveHTML($child);
    }
    return ['html' => $out, 'toc' => $toc];
}

/**
 * Resolve the real client IP. The site sits behind Cloudflare, so
 * REMOTE_ADDR is Cloudflare's edge IP (shared by all visitors) — using it
 * for rate limiting/lockouts would punish everyone at once. Prefer the
 * Cloudflare-set header, then X-Forwarded-For, then REMOTE_ADDR.
 */
function client_ip(): string {
    $candidates = [];
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        $candidates[] = $_SERVER['HTTP_CF_CONNECTING_IP'];
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $candidates[] = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
    }
    $candidates[] = $_SERVER['REMOTE_ADDR'] ?? '';
    foreach ($candidates as $ip) {
        if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
    }
    return '0.0.0.0';
}

/**
 * Sliding-window IP rate limit (filesystem-backed).
 * Returns true if the request is allowed, false if the bucket is exhausted.
 *
 * Usage:
 *   if (!rateLimit('posts_list', 200)) { http_response_code(429); exit; }
 */
function rateLimit(string $bucket, int $max, int $window_sec = 3600): bool {
    $ip       = client_ip();
    $rate_dir = __DIR__ . '/logs/ratelimit';
    if (!is_dir($rate_dir)) @mkdir($rate_dir, 0755, true);

    $file       = $rate_dir . '/' . preg_replace('/[^a-z0-9_]/i', '', $bucket) . '_' . hash('sha256', $ip) . '.json';
    $now        = time();
    $cutoff     = $now - $window_sec;
    $timestamps = [];

    if (file_exists($file)) {
        $stored = json_decode(@file_get_contents($file), true);
        if (is_array($stored)) {
            $timestamps = array_values(array_filter($stored, fn($t) => is_int($t) && $t > $cutoff));
        }
    }
    if (count($timestamps) >= $max) return false;

    $timestamps[] = $now;
    @file_put_contents($file, json_encode($timestamps), LOCK_EX);
    return true;
}
