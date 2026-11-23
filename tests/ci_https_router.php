<?php
declare(strict_types=1);
// TEST-ONLY router. Simulates TLS termination for local ephemeral HTTP smoke tests.
// Never deploy this file; the real Hostinger site must provide actual HTTPS.
if (PHP_SAPI !== 'cli-server' || !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit;
}
$_SERVER['HTTPS'] = 'on';
return false;
