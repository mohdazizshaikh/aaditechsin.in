<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__,2).'/public_html/api/operations.php';
require dirname(__DIR__,2).'/public_html/api/analytics-core.php';
try{
    $mode=$argv[1]??'--dry-run';
    if(count($argv)>2||!in_array($mode,['--dry-run','--run'],true))throw new InvalidArgumentException('Invalid mode');
    $cfg=opsConfig();$db=opsDb($cfg);
    // Fixed 30-day proposed retention. Explicit --run only after owner review.
    $cutoff=(new DateTimeImmutable('today',new DateTimeZone('Asia/Kolkata')))->modify('-29 days')->format('Y-m-d');
    $db->beginTransaction();
    $q=$db->prepare('SELECT COUNT(*) FROM growth_analytics_events WHERE event_day<?');$q->execute([$cutoff]);
    echo 'Eligible analytics events: '.(int)$q->fetchColumn()."\n";
    if($mode==='--run'){
        $q=$db->prepare('DELETE FROM growth_analytics_events WHERE event_day<?');$q->execute([$cutoff]);echo 'Deleted analytics events: '.$q->rowCount()."\n";
        $q=$db->prepare('DELETE FROM growth_analytics_budget WHERE event_day<?');$q->execute([$cutoff]);
        $db->commit();
    }else{$db->rollBack();echo "READ-ONLY: no records changed.\n";}
}catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();fwrite(STDERR,"BLOCKED: analytics retention unavailable.\n");exit(1);}
