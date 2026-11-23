<?php
declare(strict_types=1);
// Deploy to <domain-root>/secure/maintenance/prune.php outside public_html.
// CLI only. Review the retention period and legal requirements before running.
if(PHP_SAPI!=='cli') {http_response_code(403);exit('CLI only');}
require dirname(__DIR__,2).'/public_html/api/operations.php';
try {
    $cfg=opsConfig();
    if (($cfg['leads']['enabled']??false)!==true)throw new RuntimeException('Lead service disabled');
    $pdo=opsDb($cfg);
    $days=(int)($cfg['retention_days']??0);
    if($days<30||$days>3650)throw new RuntimeException('Set a reviewed retention_days value (30-3650)');
    // Keep active cases and any lead whose owner alert is still pending,
    // being delivered or failed. Discarding dead alerts would hide incidents.
    $stmt=$pdo->prepare("DELETE l FROM leads l
        LEFT JOIN jobs j ON j.lead_id=l.id
        LEFT JOIN notification_outbox o ON o.lead_id=l.id AND o.status <> 'sent'
        WHERE j.id IS NULL AND o.id IS NULL AND l.created_at < DATE_SUB(UTC_TIMESTAMP(),INTERVAL ? DAY)");
    $stmt->execute([$days]);
    echo 'Pruned '.$stmt->rowCount()." unassigned leads.\n";
} catch(Throwable $e){fwrite(STDERR,"Prune task unavailable: ".$e->getMessage()."\n");exit(1);}
