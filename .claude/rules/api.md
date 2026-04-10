# API Rules

## Every endpoint must

```php
<?php
header('Content-Type: application/json');

// 1. Validate method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); exit;
}

// 2. Require secrets + DB
require __DIR__ . '/.secrets.php';
require __DIR__ . '/db.php';

// 3. Return consistent JSON shape
echo json_encode(['ok' => true,  'data'  => $result]);
echo json_encode(['ok' => false, 'error' => 'Human-readable message']);
```

## HTTP status codes
| Situation | Code |
|---|---|
| Wrong method | 405 |
| Bad input / missing field | 400 |
| reCAPTCHA / auth failed | 403 |
| Resource not found | 404 |
| Server / DB error | 500 |

## Rate limiting
Public endpoints (recover, library lookup) must rate-limit by IP. See `api/ebook-library.php` for the filesystem-based pattern currently in use.

## Public vs protected
- Public endpoints (contact, checkout, webhook): no session required, use reCAPTCHA or token verification
- Admin endpoints: check `$_SESSION['authed']` and CSRF token
- Webhook endpoints: verify `X-CALLBACK-TOKEN` header with `hash_equals()`
