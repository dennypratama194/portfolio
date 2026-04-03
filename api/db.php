<?php
/* ════════════════════════════════════════
   DATABASE CONFIGURATION
   Fill in your rumahweb.com DB credentials
   (found in cPanel → MySQL Databases)
════════════════════════════════════════ */
define('DB_HOST', 'localhost');
define('DB_NAME', 'digh8452_portfolio');
define('DB_USER', 'digh8452_denny');
define('DB_PASS', 'Mycsapin87');

/* ── Admin credentials ── */
define('ADMIN_USER', 'denny');
$_hash_file = __DIR__ . '/.admin_hash';
define('ADMIN_PASS_HASH', file_exists($_hash_file)
    ? trim(file_get_contents($_hash_file))
    : '$2y$10$CFUblz69PYpZkGj7v.LnV.yOFuFa8B7Q3WVzXvkfFMatgnpdy7UHu'
);
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
