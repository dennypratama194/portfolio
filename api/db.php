<?php
/* ════════════════════════════════════════
   DATABASE CONFIGURATION
   Credentials live in .secrets.php (gitignored).
   Copy .secrets.php.example → .secrets.php on a new server.
════════════════════════════════════════ */
require_once __DIR__ . '/.secrets.php';

/* ── Admin password hash ── */
$_hash_file = __DIR__ . '/.admin_hash';
if (!file_exists($_hash_file)) {
    http_response_code(503);
    die(json_encode(['error' => 'Admin not configured. Run setup first.']));
}
define('ADMIN_PASS_HASH', trim(file_get_contents($_hash_file)));
unset($_hash_file);

/* ── DB connection ── */
try {
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
} catch (PDOException $e) {
    http_response_code(500);
    die(json_encode(['error' => 'Database connection failed.']));
}
