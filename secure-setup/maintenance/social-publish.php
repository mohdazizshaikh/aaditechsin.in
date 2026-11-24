<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__,2).'/public_html/api/operations.php';
require dirname(__DIR__,2).'/public_html/api/social-core.php';
require __DIR__.'/telegram-send.php';
try{
    $mode=$argv[1]??'--dry-run';
    if(count($argv)>2||!in_array($mode,['--dry-run','--run'],true))throw new InvalidArgumentException('Invalid mode');
    $cfg=opsConfig();$target=socialTarget($cfg);if($target===null)throw new RuntimeException('Disabled');
    $db=opsDb($cfg);
    if($mode==='--dry-run'){
        $q=$db->prepare("SELECT COUNT(*) FROM growth_publications WHERE status='queued' AND target_chat_id=? AND due_at<=UTC_TIMESTAMP()");$q->execute([$target]);
        echo 'Due configured-channel rows: '.$q->fetchColumn()."\nREAD-ONLY: no publication or database change.\n";exit;
    }
    $lock=fopen(__DIR__.'/social-publish.lock','c');
    if(!$lock||!flock($lock,LOCK_EX|LOCK_NB))throw new RuntimeException('Already running');
    // Ambiguous interrupted sends are never retried automatically.
    $db->exec("UPDATE growth_publications SET status='unknown',error_code='interrupted_send',finished_at=UTC_TIMESTAMP() WHERE status='sending' AND claimed_at<DATE_SUB(UTC_TIMESTAMP(),INTERVAL 15 MINUTE)");
    $db->beginTransaction();
    $row=socialClaim($db,$cfg);$db->commit();
    if(!$row){echo "No eligible due publication.\n";exit;}
    // One external action per explicit run. Pause is checked at claim, not a cancellation of in-flight sends.
    $result=telegramSend($cfg['social']['telegram_channel']['bot_token'],$target,$row['caption']);
    $db->beginTransaction();socialFinish($db,$row,$result);$db->commit();
    echo 'Publication #'.$row['id'].' '.$result['status']."\n";
    exit($result['status']==='accepted'?0:1);
}catch(Throwable $e){
    if(isset($db)&&$db->inTransaction())$db->rollBack();
    fwrite(STDERR,"BLOCKED: publishing unavailable or paused. Inspect ledger before retrying.\n");exit(1);
}
