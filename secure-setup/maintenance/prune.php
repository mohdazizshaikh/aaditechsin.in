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
    // Do not delete leads tied to service jobs or V9 audit/consent/idempotency history.
    // A partial V9 installation is a safe-stop condition, never an excuse to bypass FKs.
    $v9Tables=['lead_events','lead_attribution','lead_consent','contact_suppression','lead_requests'];
    $placeholders=implode(',',array_fill(0,count($v9Tables),'?'));
    $schemaCheck=$pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('.$placeholders.')');
    $schemaCheck->execute($v9Tables);
    $present=(int)$schemaCheck->fetchColumn();
    if($present>0 && $present!==count($v9Tables))throw new RuntimeException('V9 schema is partial; prune blocked');
    $sql='DELETE l FROM leads l LEFT JOIN jobs j ON j.lead_id=l.id';
    if($present===count($v9Tables)) {
        $sql.=' LEFT JOIN lead_events ev ON ev.lead_id=l.id'
             .' LEFT JOIN lead_attribution att ON att.lead_id=l.id'
             .' LEFT JOIN lead_consent c ON c.lead_id=l.id'
             .' LEFT JOIN lead_requests r ON r.lead_id=l.id';
    }
    $sql.=' WHERE j.id IS NULL';
    if($present===count($v9Tables)) $sql.=' AND ev.id IS NULL AND att.lead_id IS NULL AND c.id IS NULL AND r.id IS NULL';
    $sql.=' AND l.created_at < DATE_SUB(UTC_TIMESTAMP(),INTERVAL ? DAY)';
    $stmt=$pdo->prepare($sql);
    $stmt->execute([$days]);
    echo 'Pruned '.$stmt->rowCount()." unassigned leads.\n";
} catch(Throwable $e){fwrite(STDERR,"Prune task unavailable: ".$e->getMessage()."\n");exit(1);}
