<?php
declare(strict_types=1);
/**
 * Private CLI-only lead retention cleanup. Not an automatic cron task.
 * --dry-run (default): count eligible unassigned leads, no writes.
 * --run: explicitly delete eligible leads after privacy/retention approval.
 * Never print raw database exceptions: they can disclose connection details.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$mode = $argv[1] ?? '--dry-run';
if (!in_array($mode, ['--dry-run', '--run'], true) || count($argv) > 2) {
    fwrite(STDERR, "Usage: php prune.php [--dry-run|--run]\n");
    exit(2);
}
require dirname(__DIR__, 2) . '/public_html/api/operations.php';
try {
    $cfg = opsConfig();
    if (($cfg['leads']['enabled'] ?? false) !== true) {
        throw new RuntimeException('Lead service unavailable');
    }
    $days = $cfg['retention_days'] ?? null;
    if (!is_int($days) || $days < 30 || $days > 3650) {
        throw new RuntimeException('A reviewed retention period is required');
    }
    $pdo = opsDb($cfg);
    // Unresolved alerts (pending, sending, dead) and ALL leads with a service
    // case must survive cleanup. The same predicate is used for both modes.
    $joins = " FROM leads l
        LEFT JOIN jobs j ON j.lead_id = l.id
        LEFT JOIN notification_outbox o ON o.lead_id = l.id AND o.status <> 'sent'
        WHERE j.id IS NULL AND o.id IS NULL
          AND l.created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL ? DAY)";
    $count = $pdo->prepare('SELECT COUNT(*)' . $joins);
    $count->execute([$days]);
    $eligible = (int) $count->fetchColumn();
    if ($mode === '--dry-run') {
        echo 'Eligible unassigned leads: ' . $eligible . "\n";
        echo "READ-ONLY: no lead or notification records changed.\n";
        exit(0);
    }
    // The DELETE re-evaluates the same restrictions at execution time. The
    // database's jobs foreign key also prevents accidental case deletion.
    $remove = $pdo->prepare('DELETE l' . $joins);
    $remove->execute([$days]);
    echo 'Deleted eligible unassigned leads: ' . $remove->rowCount() . "\n";
} catch (Throwable $e) {
    fwrite(STDERR, "BLOCKED: review private configuration, database and retention policy. No exception details disclosed.\n");
    exit(1);
}
