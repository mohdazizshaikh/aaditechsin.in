<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__,2).'/public_html/api/operations.php';
require __DIR__.'/tender-poll-lib.php';
try{
    $mode=$argv[1]??'';
    if(count($argv)!==2||!in_array($mode,['--check','--status','--scheduled-run'],true))throw new InvalidArgumentException('Explicit mode');
    $cfg=opsConfig();$p=tenderPollConfig($cfg);
    if($mode==='--check'){echo json_encode(['sources'=>array_keys($p['sources']),'interval_minutes'=>$p['minutes'],'network'=>false,'database'=>false],JSON_THROW_ON_ERROR)."\n";exit;}
    $db=opsDb($cfg);
    if($mode==='--status'){echo json_encode(tenderPollStatus($db,$cfg),JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";exit;}
    $failed=false;
    foreach(array_keys($p['sources']) as $id){
        $result=tenderPollRun($db,'opsConfig',$id,static fn($src)=>feedFetch($src,$src['bearer_token']));
        echo $id.' '.json_encode($result,JSON_THROW_ON_ERROR)."\n";
        if(!in_array($result['status'],['completed','not_due'],true)){$failed=true;break;}
    }
    exit($failed?1:0);
}catch(Throwable $e){fwrite(STDERR,"BLOCKED: tender poll configuration, source authorization or runtime. Review source run status.\n");exit(1);}
