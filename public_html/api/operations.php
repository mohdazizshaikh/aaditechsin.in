<?php
declare(strict_types=1);
// Shared operations helpers. Server side only; never expose sensitive configuration.
function opsConfig(): array {
    $base = dirname(__DIR__, 2);
    $path = $base . '/secure/aaditechs-config.php';
    $real = realpath($path);
    if ($real === false || !is_file($real)) throw new RuntimeException('Configuration unavailable');
    $public = realpath(dirname(__DIR__));
    if ($public !== false && ($real === $public || str_starts_with($real, rtrim($public, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR))) {
        throw new RuntimeException('Unsafe configuration path');
    }
    $cfg = require $real;
    if (!is_array($cfg)) throw new RuntimeException('Configuration unavailable');
    return $cfg;
}
function opsDb(array $cfg): PDO {
    $db = $cfg['database'] ?? [];
    if (($db['enabled'] ?? false) !== true) throw new RuntimeException('Database unavailable');
    $host = (string)($db['host'] ?? '');
    $name = (string)($db['name'] ?? '');
    if ($host === '' || $name === '' || str_starts_with($host,'REPLACE_')) throw new RuntimeException('Database unavailable');
    $dsn='mysql:host=' . $host . ';port=' . (int)($db['port'] ?? 3306) . ';dbname=' . $name . ';charset=utf8mb4';
    return new PDO($dsn, (string)($db['username'] ?? ''), (string)($db['password'] ?? ''), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_TIMEOUT => 5,
    ]);
}
function opsLen(string $value): int { return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value); }
function opsRef(string $prefix): string {return $prefix . '-' . gmdate('ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));}
function opsH(string $text): string {return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');}
function opsSsl(): bool {
    return !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
}
function opsHeaders(): void {
    header('Cache-Control: no-store, max-age=0');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: no-referrer');
    header('X-Robots-Tag: noindex, nofollow, noarchive');
    header("Content-Security-Policy: default-src 'none'; style-src 'self'; img-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
}
function opsSession(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    if (!opsSsl()) throw new RuntimeException('HTTPS required');
    session_name('aaditechs_ops');
    session_set_cookie_params(['lifetime'=>0,'path'=>'/portal/','secure'=>true,'httponly'=>true,'samesite'=>'Strict']);
    session_start();
}
function opsCsrf(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(24));
    return (string)$_SESSION['csrf'];
}
function opsCheckCsrf(): void {
    if (!is_string($_POST['csrf'] ?? null) || !hash_equals(opsCsrf(), $_POST['csrf'])) {
        http_response_code(403); exit('Invalid request token');
    }
}
function opsEvent(PDO $pdo, int $job, string $role, string $actor, string $type, string $note=''): void {
    $q=$pdo->prepare('INSERT INTO job_activity(job_id,actor_role,actor_label,event_type,note) VALUES(?,?,?,?,?)');
    $q->execute([$job, $role, $actor, $type, substr($note,0,1500)]);
}
