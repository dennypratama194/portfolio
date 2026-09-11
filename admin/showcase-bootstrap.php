<?php
if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'] ?? '')) { http_response_code(403); exit; }
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_secure', '1');
session_start();
if (($_SESSION['authed'] ?? false) !== true) { header('Location: /admin/login'); exit; }
$_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf']))) {
    http_response_code(403); exit('Your session expired. Reload the page and try again.');
}
require __DIR__ . '/../api/db.php';
require_once __DIR__ . '/../api/showcase.php';
require_once __DIR__ . '/showcase-media.php';

function showcaseText(array $values, string $key, int $max = 255): string {
    $value = $values[$key] ?? '';
    if (!is_string($value) || mb_strlen($value) > $max) throw new RuntimeException('Check the length and format of ' . str_replace('_', ' ', $key) . '.');
    return trim($value);
}
function showcaseInt(array $values, string $key, int $min = 0, int $max = 2147483647): int {
    $value = filter_var($values[$key] ?? 0, FILTER_VALIDATE_INT);
    if ($value === false || $value < $min || $value > $max) throw new RuntimeException('Enter a valid ' . str_replace('_', ' ', $key) . '.');
    return $value;
}
function showcaseSlug(string $slug, int $max = 180): bool {
    return strlen($slug) <= $max && (bool)preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug);
}
function showcaseUniqueSlug(PDO $pdo, string $base): string {
    $slug = $base;
    for ($suffix = 2; showcaseQuery($pdo, 'SELECT id FROM showcase_projects WHERE slug=?', [$slug])->fetch(); $suffix++) {
        if ($suffix > 500) throw new RuntimeException('Could not generate a unique slug. Please try again.');
        $slug = $base . '-' . $suffix;
    }
    return $slug;
}
