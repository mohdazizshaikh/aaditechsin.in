<?php
declare(strict_types=1);
require dirname(__DIR__).'/public_html/api/communications-core.php';
require dirname(__DIR__).'/secure-setup/maintenance/communication-review-batch-lib.php';
function cmCheck(bool $ok,string $msg):void{if(!$ok)throw new RuntimeException($msg);}
$now=new DateTimeImmutable('now',new DateTimeZone('UTC'));$day=$now->modify('-1 day')->format('Y-m-d');$r=['channel'=>'calls','day_utc'=>$day,'received'=>12,'answered'=>9,'missed'=>3];$cfg=['growth'=>['enabled'=>true],'communications'=>['enabled'=>true]];
cmCheck(!communicationsEnabled([])&&communicationsValidate($r,$now)===$r,'OFF and valid');
foreach([array_replace($r,['received'=>2]),array_replace($r,['day_utc'=>$now->format('Y-m-d')]),$r+['phone'=>'synthetic'],array_replace($r,['received'=>'12'])] as $bad){try{communicationsValidate($bad,$now);throw new LogicException('Invalid accepted');}catch(InvalidArgumentException $e){}}
function cmBlocked(callable $fn):void{try{$fn();}catch(RuntimeException|InvalidArgumentException|JsonException $e){return;}throw new LogicException('Expected refusal');}
$reviewCfg=$cfg;$reviewCfg['communications']['reviews_enabled']=true;$reviewCfg['communications']['review_batch_enabled']=true;
$today=$now->format('Y-m-d');$scopeCases=0;
foreach(['email','whatsapp','sms','calls'] as $channel){
    $item=['channel'=>$channel,'day_utc'=>$day];
    $make=static fn($row,$action='set',$due=null)=>json_encode(['version'=>1,'action'=>$action,'items'=>[$row]]+($action==='set'?['due_on'=>$due??$today]:[]),JSON_THROW_ON_ERROR);
    foreach(['set','complete','cancel'] as $action){cmCheck(commReviewBatchScope($reviewCfg,$make($item,$action),$now)['action']===$action,'Valid action');++$scopeCases;}
    foreach([['channel'=>'unknown'],['day_utc'=>$today],['day_utc'=>'2026-02-30'],['day_utc'=>'2000-01-01'],['day_utc'=>123],['phone'=>'synthetic']] as $patch){cmBlocked(static fn()=>commReviewBatchScope($reviewCfg,$make(array_replace($item,$patch)),$now));++$scopeCases;}
    foreach(['2000-01-01','2999-01-01','invalid'] as $due){cmBlocked(static fn()=>commReviewBatchScope($reviewCfg,$make($item,'set',$due),$now));++$scopeCases;}
    cmBlocked(static fn()=>commReviewBatchScope($cfg,$make($item),$now));++$scopeCases;
}
for($n=1;$n<=50;$n++){
    $items=[];for($i=1;$i<=$n;$i++)$items[]=['channel'=>'email','day_utc'=>$now->modify("-$i days")->format('Y-m-d')];
    $raw=json_encode(['version'=>1,'action'=>'set','items'=>$items,'due_on'=>$today]);
    cmCheck(count(commReviewBatchScope($reviewCfg,$raw,$now)['items'])===$n,'Bounded size');++$scopeCases;
}
foreach(['[]','{}','{"version":true,"action":"complete","items":[]}','{"version":1,"action":"send","items":[]}',str_repeat(' ',16385)] as $bad){cmBlocked(static fn()=>commReviewBatchScope($reviewCfg,$bad,$now));++$scopeCases;}
cmBlocked(static fn()=>commReviewBatchScope($reviewCfg,json_encode(['version'=>1,'action'=>'set','items'=>[$item,$item],'due_on'=>$today]),$now));++$scopeCases;
cmCheck($scopeCases===108,'108 scope cases');echo "Communication review scope: $scopeCases/108 PASS\n";
if(($argv[1]??'')!=='--database'){echo "Communications aggregate privacy and validation: PASS\n";exit;}
if(getenv('AADITECHS_DISPOSABLE_TEST')!=='yes'||getenv('CI_DB_NAME')!=='aaditechs_ci'||getenv('CI_DB_USER')!=='aaditechs_ci'||getenv('CI_DB_HOST')!=='127.0.0.1')exit(2);
$db=new PDO('mysql:host=127.0.0.1;port='.(int)getenv('CI_DB_PORT').';dbname=aaditechs_ci;charset=utf8mb4','aaditechs_ci',getenv('CI_DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
$db->beginTransaction();
try{
    growthCommand($db,'/resume',$cfg);
    cmCheck(communicationsImport($db,$cfg,$r,$now)&&!communicationsImport($db,$cfg,$r,$now),'Deduplication');
    try{communicationsImport($db,$cfg,array_replace($r,['received'=>13]),$now);throw new LogicException('Overwrite accepted');}catch(RuntimeException $e){}
    cmCheck(str_contains(communicationsSummary($db,$now),'calls: received 12 / answered 9 / missed 3')&&str_contains(growthCommand($db,'/communications',$cfg),'email: no data'),'Owner summary missing vs zero');
    $cfg['communications']['batch_import_enabled']=true;
    $batch=['version'=>1,'items'=>[array_replace($r,['channel'=>'email']),array_replace($r,['received'=>13])]];
    try{communicationsBatch($db,$cfg,$batch,$now);throw new LogicException('Conflict accepted');}catch(RuntimeException $e){}
    cmCheck(str_contains(communicationsSummary($db,$now),'email: no data'),'Batch rollback');
    $batch=['version'=>1,'items'=>[array_replace($r,['channel'=>'email']),$r]];
    cmCheck(communicationsBatch($db,$cfg,$batch,$now)===['imported'=>1,'duplicates'=>1]&&communicationsBatch($db,$cfg,$batch,$now)===['imported'=>0,'duplicates'=>2],'Batch import and dedupe');
    try{communicationsBatch($db,$cfg,['version'=>1,'items'=>[$r,$r]],$now);throw new LogicException('Duplicate batch key accepted');}catch(InvalidArgumentException $e){}
    $db->exec('SAVEPOINT comm_review_matrix');
    $items=[];$cases=0;
    foreach(['email','whatsapp','sms','calls'] as $channel){
        $item=['channel'=>$channel,'day_utc'=>$now->modify('-2 days')->format('Y-m-d')];
        $scope=json_encode(['version'=>1,'action'=>'set','items'=>[$item],'due_on'=>$today]);
        cmBlocked(static fn()=>commReviewBatchPlan($db,$reviewCfg,$scope,false,$now));++$cases;
        communicationsImport($db,$cfg,$item+['received'=>3,'answered'=>1,'missed'=>2],$now);
        $plan=commReviewBatchPlan($db,$reviewCfg,$scope,false,$now);
        cmBlocked(static fn()=>commReviewBatchApply($db,$reviewCfg,$scope,str_repeat('0',64),$now));++$cases;
        cmCheck(commReviewBatchApply($db,$reviewCfg,$scope,$plan['digest'],$now)['applied']===1,'Set');++$cases;
        cmBlocked(static fn()=>commReviewBatchPlan($db,$reviewCfg,$scope,false,$now));++$cases;
        $next=json_encode(['version'=>1,'action'=>'set','items'=>[$item],'due_on'=>$now->modify('+1 day')->format('Y-m-d')]);
        $p=commReviewBatchPlan($db,$reviewCfg,$next,false,$now);
        $db->prepare('UPDATE growth_communication_daily SET received_count=4 WHERE channel=? AND day_utc=?')->execute([$channel,$item['day_utc']]);
        cmBlocked(static fn()=>commReviewBatchApply($db,$reviewCfg,$next,$p['digest'],$now));++$cases;
        $p=commReviewBatchPlan($db,$reviewCfg,$next,false,$now);
        cmCheck(commReviewBatchApply($db,$reviewCfg,$next,$p['digest'],$now)['applied']===1,'Reschedule');++$cases;
        $items[]=$item;
    }
    $cancel=json_encode(['version'=>1,'action'=>'cancel','items'=>$items]);
    $p=commReviewBatchPlan($db,$reviewCfg,$cancel,false,$now);growthCommand($db,'/pause',$cfg);
    cmBlocked(static fn()=>commReviewBatchApply($db,$reviewCfg,$cancel,$p['digest'],$now));
    $p=commReviewBatchPlan($db,$reviewCfg,$cancel,false,$now);
    cmCheck(commReviewBatchApply($db,$reviewCfg,$cancel,$p['digest'],$now)['applied']===4,'Cancel while paused');
    cmBlocked(static fn()=>commReviewBatchPlan($db,$reviewCfg,$cancel,false,$now));
    cmCheck($cases===24,'24 channel cases');$db->exec('ROLLBACK TO SAVEPOINT comm_review_matrix');$db->exec('RELEASE SAVEPOINT comm_review_matrix');
    echo "Communication review channel matrix: $cases/24 PASS\n";
    growthCommand($db,'/pause',$cfg);try{communicationsImport($db,$cfg,array_replace($r,['channel'=>'sms']),$now);throw new LogicException('Pause bypass');}catch(RuntimeException $e){}
    $db->rollBack();echo "Synthetic communication summary and pause: PASS\n";
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();fwrite(STDERR,'Communications test failed: '.$e->getMessage()."\n");exit(1);}
