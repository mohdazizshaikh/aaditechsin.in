<?php
declare(strict_types=1);
/**
 * Aaditechs deployment preflight. CLI only; safe for public repositories.
 * Run on hosting from <domain-root>/secure/maintenance/preflight.php.
 * Never prints credential values. No writes, migrations, mail or customer records.
 *
 * Usage:
 *   php preflight.php                  # live/staging readiness (fails closed)
 *   php preflight.php --check-db       # additionally runs read-only DB SELECT checks
 *   php preflight.php --source-only    # local/CI source-layout test
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$allowed = ['--check-db', '--source-only'];
foreach (array_slice($argv, 1) as $argument) {
    if (!in_array($argument, $allowed, true)) {
        fwrite(STDERR, "Usage: php preflight.php [--check-db] [--source-only]\n");
        exit(2);
    }
}
$sourceOnly = in_array('--source-only', $argv, true);
$checkDb = in_array('--check-db', $argv, true);
if ($sourceOnly && $checkDb) {
    fwrite(STDERR, "Choose --source-only OR --check-db.\n");
    exit(2);
}
$root = dirname(__DIR__, 2);
$public = $root . '/public_html';
$private = $root . '/secure';
$configPath = $private . '/aaditechs-config.php';
$failures = 0;
$warnings = 0;
function outcome(string $name, bool $ok, string $onFail = 'BLOCKED'): void {
    global $failures;
    echo ($ok ? 'PASS' : $onFail) . ': ' . $name . "\n";
    if (!$ok) $failures++;
}
function warning(string $name): void {
    global $warnings;
    $warnings++;
    echo 'NOTE: ' . $name . "\n";
}
function setValue(array $config, array $keys): mixed {
    $value = $config;
    foreach ($keys as $key) {
        if (!is_array($value) || !array_key_exists($key, $value)) return null;
        $value = $value[$key];
    }
    return $value;
}
function hasPrivateValue(mixed $value): bool {
    return is_string($value) && trim($value) !== '' && !str_starts_with($value, 'REPLACE_');
}
$outstanding = [
    'public_html/index.html',
    'public_html/.htaccess',
    'public_html/api/bootstrap.php',
    'public_html/api/lead.php',
    'public_html/api/operations.php',
    'public_html/api/workflow.php',
    'public_html/portal/index.php',
    'public_html/track/index.php',
    'public_html/data-recovery/index.html',
    'public_html/it-amc/index.html',
    'public_html/video-conferencing-solutions/index.html',
    'public_html/auditorium-av-solutions/index.html',
    'public_html/professional-sound-systems/index.html',
    'public_html/av-project-planner/index.html',
    'public_html/assets/aaditechs-original-logo.png',
];
foreach ($outstanding as $file) outcome('File present: ' . $file, is_file($root . '/' . $file));
if ($sourceOnly) {
    outcome('Local schema template present', is_file($root . '/secure-setup/schema.sql'));
    outcome('Private example config present', is_file($root . '/secure-setup/aaditechs-config.php.example'));
    outcome('V8.4 queued-mail schema migration present', is_file($root . '/secure-setup/migrations/2026-11-23-v84.sql'));
    outcome('Private notification worker source present', is_file($root . '/secure-setup/maintenance/notification-worker.php'));
    warning('Source-only check: no Hostinger, SQL, HTTPS or SMTP readiness is asserted');
    exit($failures === 0 ? 0 : 1);
}
outcome('PHP >= 8.1', PHP_VERSION_ID >= 80100);
foreach (['pdo_mysql', 'curl', 'openssl', 'json'] as $extension) {
    outcome('PHP extension ' . $extension, extension_loaded($extension));
}
$publicReal = realpath($public);
$privateReal = realpath($private);
outcome('Private secure/ directory exists', $privateReal !== false && is_dir($privateReal));
if ($publicReal !== false && $privateReal !== false) {
    outcome('Private directory outside public_html', $privateReal !== $publicReal && !str_starts_with($privateReal, rtrim($publicReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR));
}
$configReal = realpath($configPath);
outcome('Private config exists (not in Git)', $configReal !== false && is_file($configReal));
if ($configReal === false || !is_file($configReal)) {
    warning('Use secure-setup/aaditechs-config.php.example privately; never upload secrets into webroot');
    exit(1);
}
if ($publicReal !== false) {
    outcome('Private config outside public_html', $configReal !== $publicReal && !str_starts_with($configReal, rtrim($publicReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR));
}
// Trusted owner-maintained PHP config, never user-submitted content.
$config = require $configReal;
if (!is_array($config)) {
    outcome('Private config returns an array', false);
    exit(1);
}
$origin = setValue($config, ['public_base_url']);
outcome('Configured HTTPS origin', is_string($origin) && (bool)preg_match('~^https://[a-z0-9.-]+(?::[0-9]+)?$~i', $origin));
if (is_string($origin) && $origin === 'https://aaditechs.in') {
    warning('Public origin is aaditechs.in; use staging HTTPS origin while testing staging');
}
$flags = [
    'database.enabled' => ['database', 'enabled'],
    'leads.enabled' => ['leads', 'enabled'],
    'portal.enabled' => ['portal', 'enabled'],
    'google.enabled' => ['google', 'enabled'],
    'mail.enabled' => ['mail', 'enabled'],
    'growth.enabled' => ['growth', 'enabled'],
    'growth.telegram.enabled' => ['growth', 'telegram', 'enabled'],
    'growth.telegram.reports_enabled' => ['growth','telegram','reports_enabled'],
    'analytics.enabled' => ['analytics', 'enabled'],
    'intelligence_dashboard.enabled' => ['intelligence_dashboard','enabled'],
    'intelligence_dashboard.csv_enabled' => ['intelligence_dashboard','csv_enabled'],
    'communications.batch_import_enabled' => ['communications','batch_import_enabled'],
    'communications.bridge_enabled' => ['communications','bridge_enabled'],
    'communications.retention_enabled' => ['communications','retention_enabled'],
    'communications.gmail.renewal.enabled' => ['communications','gmail','renewal','enabled'],
    'discovery.enabled' => ['discovery','enabled'],
    'creative.enabled' => ['creative','enabled'],
    'social.enabled' => ['social','enabled'],
    'social.telegram_channel.enabled' => ['social','telegram_channel','enabled'],
    'discovery.feeds_enabled' => ['discovery','feeds_enabled'],
];
foreach ($flags as $name => $keys) {
    $enabled = setValue($config, $keys) === true;
    echo 'STATE: ' . $name . ' = ' . ($enabled ? 'ON' : 'OFF') . "\n";
}
if (setValue($config, ['leads', 'enabled']) === true) {
    outcome('Leads require enabled database', setValue($config, ['database', 'enabled']) === true);
    $salt = setValue($config, ['rate_limit_salt']);
    outcome('Lead rate limiter has 32+ character private salt', hasPrivateValue($salt) && strlen($salt) >= 32);
    outcome('Private rate directory exists and is writable', is_dir($private . '/rate') && is_writable($private . '/rate'));
}
if (setValue($config, ['database', 'enabled']) === true) {
    foreach (['host', 'name', 'username', 'password'] as $field) {
        outcome('Database credential configured: ' . $field, hasPrivateValue(setValue($config, ['database', $field])));
    }
}
if (setValue($config, ['portal', 'enabled']) === true) {
    $hash = setValue($config, ['portal', 'admin_password_hash']);
    outcome('Owner portal password hash configured', hasPrivateValue($hash) && password_get_info($hash)['algo'] !== null);
    outcome('Owner portal email configured', filter_var(setValue($config, ['portal', 'admin_email']), FILTER_VALIDATE_EMAIL) !== false);
    if (setValue($config, ['portal', 'admin_totp_enabled']) === true) {
        $secret = setValue($config, ['portal', 'admin_totp_secret']);
        outcome('Owner TOTP secret is private 32+ character base32', is_string($secret) && (bool)preg_match('/^[A-Z2-7]{32,}$/D', $secret));
        warning('Confirm correct server clock, secure TOTP enrollment and offline recovery before enabling owner 2FA');
    } else {
        warning('Owner TOTP 2FA is OFF; do not mark production admin authentication as fully hardened');
    }
}
if (setValue($config, ['mail', 'enabled']) === true) {
    foreach (['host', 'username', 'password', 'from_email', 'inquiry_recipient'] as $field) {
        outcome('SMTP configuration present: ' . $field, hasPrivateValue(setValue($config, ['mail', $field])));
    }
    outcome('PHPMailer private vendor/autoload.php present', is_file($private . '/vendor/autoload.php'));
    outcome('Private notification CLI worker present', is_file($private . '/maintenance/notification-worker.php'));
    warning('Schedule private notification-worker.php --run with Hostinger Cron Jobs after mail acceptance; dry-run first');
    warning('SMTP values present is not proof of accepted or delivered email');
}
if (setValue($config, ['google', 'enabled']) === true) {
    warning('Google Business Profile approval and OAuth authorization must be independently verified');
}
if (setValue($config, ['analytics','enabled']) === true) {
    $salt = setValue($config, ['analytics','salt']);
    outcome('Independent analytics salt configured', hasPrivateValue($salt) && strlen($salt)>=32);
    outcome('Analytics requires enabled database', setValue($config,['database','enabled'])===true);
    warning('Review analytics consent and retention before enabling browser collection');
}
if (setValue($config, ['growth','enabled']) === true) {
    outcome('Growth requires enabled database', setValue($config,['database','enabled'])===true);
}
if (setValue($config,['discovery','enabled']) === true) {
    outcome('Discovery requires enabled growth and database', setValue($config,['growth','enabled'])===true && setValue($config,['database','enabled'])===true);
    warning('Discovery only imports evidence; external discovery and sending are not connected');
}
if ($checkDb) {
    if (setValue($config, ['database', 'enabled']) !== true || !extension_loaded('pdo_mysql')) {
        outcome('Read-only DB smoke test prerequisites', false);
    } else {
        try {
            $db = $config['database'];
            $dsn = 'mysql:host=' . $db['host'] . ';port=' . (int)($db['port'] ?? 3306) . ';dbname=' . $db['name'] . ';charset=utf8mb4';
            $pdo = new PDO($dsn, (string)$db['username'], (string)$db['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            outcome('Read-only DB SELECT 1', $pdo->query('SELECT 1')->fetchColumn() !== false);
            foreach (['leads', 'partners', 'jobs', 'job_activity', 'notification_outbox', 'growth_tasks', 'growth_audit', 'growth_control', 'growth_telegram_updates', 'growth_analytics_events', 'growth_analytics_budget', 'growth_opportunities', 'growth_source_runs', 'growth_creative', 'growth_creative_results', 'growth_publications', 'growth_report_preferences', 'growth_report_runs'] as $table) {
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
                $stmt->execute([$table]);
                outcome('Database table present: ' . $table, (int)$stmt->fetchColumn() >= 1);
            }
        } catch (Throwable $e) {
            // Never print SQL errors with connection details or credentials.
            outcome('Read-only DB connectivity and schema', false);
        }
    }
} else {
    warning('No database connection attempted. Use --check-db on staging with restricted DB access');
}
warning('For the current 40-table column contract and paused growth check, run release-check.php --check-db separately. This does not validate backups or provider acceptance.');
warning('Check HTTPS, SMTP delivery, real roles/CSRF and lead lifecycle in an approved staging acceptance session');
echo ($failures === 0 ? 'LOCAL PREFLIGHT: CHECKS PASSED' : 'LOCAL PREFLIGHT: BLOCKERS FOUND') . "\n";
exit($failures === 0 ? 0 : 1);
