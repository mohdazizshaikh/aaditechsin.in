<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__,2).'/public_html/api/operations.php';
require dirname(__DIR__,2).'/public_html/api/reports-core.php';
require __DIR__.'/telegram-send.php';
try{
    $mode=$argv[1]??'--dry-run';
    if(count($argv)>2||!in_array($mode,['--dry-run','--run'],true))throw new InvalidArgumentException('Invalid mode');
    $cfg=opsConfig();$chat=ownerReportTarget($cfg);if($chat===null)throw new RuntimeException('Disabled');
    $db=opsDb($cfg);
    if($mode==='--dry-run'){
        $db->beginTransaction();
        echo reportCommand($db,$cfg,'/notifications')."\n".reportCommand($db,$cfg,'/errors')."\nREAD-ONLY: no message or ledger change.\n";
        $db->rollBack();exit;
    }
    $lock=fopen(__DIR__.'/owner-notify.lock','c');
    if(!$lock||!flock($lock,LOCK_EX|LOCK_NB))throw new RuntimeException('Already running');
    $db->exec("UPDATE growth_report_runs SET status='unknown',error_code='interrupted_send',finished_at=UTC_TIMESTAMP() WHERE status='sending' AND created_at<DATE_SUB(UTC_TIMESTAMP(),INTERVAL 15 MINUTE)");
    // Owner monitoring stays available when marketing/external growth actions are paused.
    $failed=false;
    foreach(['daily','errors'] as $kind){
        $db->beginTransaction();$row=reportClaim($db,$cfg,$kind,new DateTimeImmutable('now'));$db->commit();
        if(!$row)continue;
        $result=telegramSend($cfg['growth']['telegram']['bot_token'],$row['chat'],$row['text']);
        $db->beginTransaction();reportFinish($db,$row['id'],$result);$db->commit();
        echo 'Owner report #'.$row['id'].' '.$result['status']."\n";
        if($result['status']!=='accepted')$failed=true;
    }
    flock($lock,LOCK_UN);fclose($lock);exit($failed?1:0);
}catch(Throwable $e){
    if(isset($db)&&$db->inTransaction())$db->rollBack();
    fwrite(STDERR,"BLOCKED: owner notification configuration or runtime unavailable. Inspect ledger.\n");exit(1);
}
