<?php
declare(strict_types=1);
require dirname(__DIR__).'/secure-setup/maintenance/competitor-review-lib.php';
function cpCheck(bool $ok,string $label):void{if(!$ok)throw new LogicException($label);}
function cpBlocked(callable $fn):void{try{$fn();}catch(RuntimeException|InvalidArgumentException|JsonException $e){return;}throw new LogicException('Expected refusal');}
$now=new DateTimeImmutable('now',new DateTimeZone('UTC'));$cfg=['growth'=>['enabled'=>true],'seo'=>['enabled'=>true,'competitor_review_batch_enabled'=>true]];
$items=[];foreach(array_keys(growthCatalog()) as $service)foreach(array_keys(competitorReviewSignals()) as $signal)$items[]=['service_id'=>$service,'signal'=>$signal];
$doc=['version'=>1,'scope'=>'competitor-editorial-review','evidence_sha256'=>str_repeat('a',64),'review_day_utc'=>$now->format('Y-m-d'),'items'=>$items];
$encode=static fn($v)=>json_encode($v,JSON_THROW_ON_ERROR);
$raw=$encode($doc);cpCheck(count(competitorReviewScope($cfg,$raw,$now)['items'])===84,'Full scope');
$cases=0;
foreach($items as $item){
    $one=array_replace($doc,['items'=>[$item]]);
    cpCheck(competitorReviewScope($cfg,$encode($one),$now)['items']===[$item],'Cell accepted');++$cases;
    $one['items'][]=$item;cpBlocked(static fn()=>competitorReviewScope($cfg,$encode($one),$now));++$cases;
}
foreach([['version'=>true],['review_day_utc'=>$now->modify('-1 day')->format('Y-m-d')],['evidence_sha256'=>'bad'],['items'=>[]],['items'=>[$items[0]+['source_url'=>'https://example.invalid/']]],['items'=>[['service_id'=>'unknown','signal'=>'service_scope']]],['items'=>[['service_id'=>$items[0]['service_id'],'signal'=>'unknown']]]] as $patch)
    cpBlocked(static fn()=>competitorReviewScope($cfg,$encode(array_replace($doc,$patch)),$now));
cpBlocked(static fn()=>competitorReviewScope([],$raw,$now));
cpCheck($cases===168,'Scope cases');echo "Competitor review scope: 168/168 PASS\n";
if(($argv[1]??'')!=='--database')exit;
if(getenv('AADITECHS_DISPOSABLE_TEST')!=='yes'||getenv('CI_DB_NAME')!=='aaditechs_ci'||getenv('CI_DB_USER')!=='aaditechs_ci'||getenv('CI_DB_HOST')!=='127.0.0.1'||getenv('CI_DB_PASSWORD')!=='ci_only_not_real_password')exit(2);
$db=new PDO('mysql:host=127.0.0.1;port='.(int)getenv('CI_DB_PORT').';dbname=aaditechs_ci;charset=utf8mb4','aaditechs_ci',getenv('CI_DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
$db->beginTransaction();
try{
    growthCommand($db,'/pause',$cfg);cpBlocked(static fn()=>competitorReviewPlan($db,$cfg,$raw,false,$now));
    growthCommand($db,'/resume',$cfg);
    $p=competitorReviewPlan($db,$cfg,$raw,false,$now);cpCheck($p['create_count']===84,'All cells planned');
    cpBlocked(static fn()=>competitorReviewApply($db,$cfg,$raw,str_repeat('0',64),$now));
    $r=competitorReviewApply($db,$cfg,$raw,$p['digest'],$now);
    cpCheck($r['created']===84&&count($r['task_ids'])===84,'84 synthetic tasks');
    $q=$db->prepare("SELECT service_id,status,task_type,title,draft FROM growth_tasks WHERE id=?");
    $seen=[];foreach($r['task_ids'] as $id){
        $q->execute([$id]);$row=$q->fetch(PDO::FETCH_ASSOC);
        cpCheck($row['status']==='pending_review'&&$row['task_type']==='seo'&&str_contains($row['draft'],$doc['evidence_sha256'])&&!str_contains($row['draft'],'https://'),'Private review');
        $seen[$row['service_id'].':'.$row['title']]=true;
    }
    cpCheck(count($seen)===84,'Distinct synthetic tasks');
    cpBlocked(static fn()=>competitorReviewApply($db,$cfg,$raw,$p['digest'],$now));
    $p=competitorReviewPlan($db,$cfg,$raw,false,$now);
    cpCheck($p['create_count']===0,'Preserve existing');
    $r2=competitorReviewApply($db,$cfg,$raw,$p['digest'],$now);cpCheck($r2['created']===0&&$r2['preserved']===84,'No duplicate');
    growthCommand($db,'/approve '.$r['task_ids'][0].' 1',$cfg);
    cpBlocked(static fn()=>competitorReviewApply($db,$cfg,$raw,$p['digest'],$now));
    $db->rollBack();echo "Synthetic 84-cell editorial task batch, stale digest, preservation and rollback: PASS\n";
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();fwrite(STDERR,'Competitor review test failed: '.$e->getMessage()."\n");exit(1);}
