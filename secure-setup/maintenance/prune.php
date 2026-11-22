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
    // Do not delete leads associated with service jobs. Owner must review jobs and legal obligations first.
    $stmt=$pdo->prepare('DELETE l FROM leads l LEFT JOIN jobs j ON j.lead_id=l.id WHERE j.id IS NULL AND l.created_at < DATE_SUB(UTC_TIMESTAMP(),INTERVAL ? DAY)');
    $stmt->execute([$days]);
    echo 'Pruned '.$stmt->rowCount()." unassigned leads.\n";
} catch(Throwable $e){fwrite(STDERR,"Prune task unavailable: ".$e->getMessage()."\n");exit(1);}
