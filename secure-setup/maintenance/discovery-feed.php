<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__,2).'/public_html/api/operations.php';
require __DIR__.'/discovery-feed-lib.php';
try{
    $mode=$argv[1]??'--dry-run';
    if(count($argv)>2||!in_array($mode,['--dry-run','--run','--schedule-check','--schedule-status','--scheduled-run'],true))throw new InvalidArgumentException('Mode');
    $cfg=opsConfig();
    $scheduled=in_array($mode,['--schedule-check','--schedule-status','--scheduled-run'],true);
    $plan=feedConfiguredSources($cfg,$scheduled);$minutes=$plan['minutes'];$sources=$plan['sources'];
    if(in_array($mode,['--dry-run','--schedule-check'],true)){echo 'Authorized configured sources: '.count($sources)."\nMinimum interval minutes: ".$minutes."\nREAD-ONLY: no network or database access.\n";exit;}
    if($mode==='--schedule-status'){echo json_encode(feedScheduleStatus(opsDb($cfg),$cfg),JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT)."\n";exit;}
    $lock=fopen(__DIR__.'/discovery-feed.lock','c');
    if(!$lock||!flock($lock,LOCK_EX|LOCK_NB))throw new RuntimeException('Already running');
    $db=opsDb($cfg);
    foreach($sources as $source){
        // Pause is checked immediately before each source. In-flight work is not cancelled.
        $paused=$db->query('SELECT paused FROM growth_control WHERE id=1')->fetchColumn();
        if($paused===false||(int)$paused!==0){echo "PAUSED: no further source fetches.\n";$failed=true;break;}
        $result=feedRun($db,$source,'feedFetch',$minutes);
        echo $source['id'].' '.json_encode($result,JSON_THROW_ON_ERROR)."\n";
        if(!in_array($result['status'],['completed','not_due'],true))$failed=true;
        if($result['error']==='growth_paused')break;
    }
    flock($lock,LOCK_UN);fclose($lock);
    exit(!empty($failed)?1:0);
}catch(Throwable $e){fwrite(STDERR,"BLOCKED: discovery feed configuration or runtime unavailable.\n");exit(1);}
