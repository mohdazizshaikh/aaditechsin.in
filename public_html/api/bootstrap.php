<?php
declare(strict_types=1);

// These PHP files run on Hostinger, NOT in the Sites static preview.
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store');

function jsonResponse(int $status, array $data): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function privateDirectory(): string
{
    // api is public_html/api; the secure directory is a SIBLING of public_html.
    $root = dirname(__DIR__, 2);
    return $root . '/secure';
}

function appConfig(): array
{
    $path = privateDirectory() . '/aaditechs-config.php';
    $real = realpath($path);
    $public = realpath($_SERVER['DOCUMENT_ROOT'] ?? __DIR__);
    if ($real === false || !is_file($real)) jsonResponse(503, ['error' => 'Service not configured']);
    if ($public !== false && (str_starts_with($real, rtrim($public, '/') . '/') || $real === $public)) {
        jsonResponse(503, ['error' => 'Unsafe server configuration']);
    }
    $cfg = require $real;
    if (!is_array($cfg)) jsonResponse(503, ['error' => 'Service not configured']);
    return $cfg;
}

function httpJson(string $url, ?array $post = null, ?string $accessToken = null): array
{
    if (!function_exists('curl_init')) jsonResponse(503, ['error' => 'Server cURL extension required']);
    $h = curl_init($url);
    $headers = ['Accept: application/json'];
    if ($accessToken !== null) $headers[] = 'Authorization: Bearer ' . $accessToken;
    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 7,
        CURLOPT_TIMEOUT => 14,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HTTPHEADER => $headers,
    ];
    if ($post !== null) {
        $options[CURLOPT_POST] = true;
        $options[CURLOPT_POSTFIELDS] = http_build_query($post);
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        $options[CURLOPT_HTTPHEADER] = $headers;
    }
    curl_setopt_array($h, $options);
    $body = curl_exec($h);
    $status = (int) curl_getinfo($h, CURLINFO_HTTP_CODE);
    curl_close($h);
    if (!is_string($body) || $status < 200 || $status >= 300) {
        // Do not leak provider responses (which may include account or token details).
        error_log('Aaditechs upstream HTTP error ' . $status);
        jsonResponse(503, ['error' => 'Integration temporarily unavailable']);
    }
    $decoded = json_decode($body, true);
    if (!is_array($decoded)) jsonResponse(503, ['error' => 'Unexpected upstream response']);
    return $decoded;
}
