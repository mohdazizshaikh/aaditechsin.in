<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__,2).'/public_html/api/operations.php';
require __DIR__.'/discovery-feed-lib.php';
try{
    $mode=$argv[1]??'--dry-run';
    if(count($argv)>2||!in_array($mode,['--dry-run','--run'],true))throw new InvalidArgumentException('Mode');
    $cfg=opsConfig();
    if(!discoveryEnabled($cfg)||($cfg['discovery']['feeds_enabled']??false)!==true)throw new RuntimeException('Disabled');
    $feeds=$cfg['discovery']['feeds']??null;
    if(!is_array($feeds)||count($feeds)<1||count($feeds)>5)throw new RuntimeException('Configure 1-5 authorized sources');
    $minutes=feedCadence($cfg);
    $sources=[];foreach($feeds as $id=>$src)$sources[]=feedDefinition($id,$src);
    if($mode==='--dry-run'){echo 'Authorized configured sources: '.count($sources)."\nREAD-ONLY: no network or database access.\n";exit;}
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
