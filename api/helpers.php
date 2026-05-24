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
 * Sliding-window IP rate limit (filesystem-backed).
 * Returns true if the request is allowed, false if the bucket is exhausted.
 *
 * Usage:
 *   if (!rateLimit('posts_list', 200)) { http_response_code(429); exit; }
 */
function rateLimit(string $bucket, int $max, int $window_sec = 3600): bool {
    $ip       = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
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
