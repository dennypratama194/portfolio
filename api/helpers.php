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
