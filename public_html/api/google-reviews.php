<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'GET') jsonResponse(405, ['error' => 'Method not allowed']);
$config = appConfig();
$google = $config['google'] ?? [];
if (($google['enabled'] ?? false) !== true) jsonResponse(503, ['status' => 'awaiting_google_api_approval']);

foreach (['client_id','client_secret','refresh_token','account_id','location_id'] as $key) {
    if (empty($google[$key]) || str_starts_with((string) $google[$key], 'REPLACE_')) {
        jsonResponse(503, ['error' => 'Google API configuration incomplete']);
    }
}
if (!ctype_digit((string)$google['account_id']) || !ctype_digit((string)$google['location_id'])) {
    jsonResponse(503, ['error' => 'Google account/location ID invalid']);
}

// Limited secure temporary cache: improves performance while respecting Google
// Business Profile API temporary-content rules. Never publish this cache file.
$cachePath = privateDirectory() . '/cache/reviews.json';
if (is_file($cachePath) && time() - (int) filemtime($cachePath) < 900) {
    $saved = file_get_contents($cachePath);
    $decoded = is_string($saved) ? json_decode($saved, true) : null;
    if (is_array($decoded) && isset($decoded['reviews'])) jsonResponse(200, $decoded);
}

$token = httpJson('https://oauth2.googleapis.com/token', [
    'client_id' => (string)$google['client_id'],
    'client_secret' => (string)$google['client_secret'],
    'refresh_token' => (string)$google['refresh_token'],
    'grant_type' => 'refresh_token',
]);
if (empty($token['access_token'])) jsonResponse(503, ['error' => 'OAuth authorization unavailable']);
$path = 'https://mybusiness.googleapis.com/v4/accounts/' . $google['account_id']
    . '/locations/' . $google['location_id'] . '/reviews?pageSize=6&orderBy=updateTime%20desc';
$source = httpJson($path, null, (string)$token['access_token']);
$starMap = ['ONE'=>1,'TWO'=>2,'THREE'=>3,'FOUR'=>4,'FIVE'=>5];
// Hostinger plans may not include mbstring; preserve a safe UTF-8 fallback.
$truncate = static function(string $value, int $limit): string {
    if (function_exists('mb_substr')) return mb_substr($value, 0, $limit, 'UTF-8');
    return substr($value, 0, $limit * 4);
};
$reviews = [];
foreach (($source['reviews'] ?? []) as $item) {
    if (!is_array($item)) continue;
    $rating = $starMap[$item['starRating'] ?? ''] ?? 0;
    if ($rating < 1) continue;
    $reviews[] = [
        'author' => $truncate((string)($item['reviewer']['displayName'] ?? 'Google reviewer'), 100),
        'rating' => $rating,
        'comment' => $truncate((string)($item['comment'] ?? ''), 2000),
        'date' => substr((string)($item['updateTime'] ?? ''), 0, 10),
    ];
}
$result = [
    'source' => 'Google Business Profile',
    'averageRating' => (float)($source['averageRating'] ?? 0),
    'totalReviewCount' => (int)($source['totalReviewCount'] ?? 0),
    'reviews' => $reviews,
];
$cacheDir = dirname($cachePath);
if (!is_dir($cacheDir)) @mkdir($cacheDir, 0700, true);
if (is_dir($cacheDir)) {
    $bytes = json_encode($result, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_SLASHES);
    if ($bytes !== false) { @file_put_contents($cachePath, $bytes, LOCK_EX); @chmod($cachePath, 0600); }
}
jsonResponse(200, $result);
