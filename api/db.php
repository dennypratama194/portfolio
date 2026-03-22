<?php
/* ════════════════════════════════════════
   DATABASE CONFIGURATION
   Fill in your rumahweb.com DB credentials
   (found in cPanel → MySQL Databases)
════════════════════════════════════════ */
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_db_name');   // ← replace
define('DB_USER', 'your_db_user');   // ← replace
define('DB_PASS', 'your_db_pass');   // ← replace

/* ── Admin credentials ── */
define('ADMIN_USER', 'denny');
define('ADMIN_PASS_HASH', password_hash('change_this_password', PASSWORD_BCRYPT));
// After setup, generate a real hash by running this once in PHP:
// echo password_hash('your_chosen_password', PASSWORD_BCRYPT);
// Then paste the result above instead of calling password_hash() here.

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
