<?php
declare(strict_types=1);
require dirname(__DIR__).'/secure-setup/maintenance/tender-review-lib.php';
function trCheck(bool $ok,string $m):void{if(!$ok)throw new LogicException($m);}
function trBlocked(callable $fn):void{try{$fn();}catch(RuntimeException|InvalidArgumentException|JsonException $e){return;}throw new LogicException('Expected refusal');}
$tz=new DateTimeZone('Asia/Kolkata');$now=new DateTimeImmutable('2026-11-26 01:30:00',$tz);
$cfg=['growth'=>['enabled'=>true],'discovery'=>['enabled'=>true,'tender_review_enabled'=>true]];
$base=['source'=>'nicsi','reference'=>'TEST/2026/A','title'=>'Network switches','location'=>'Mumbai','observed_on'=>'2026-11-26','deadline_ist'=>'2026-11-30T15:00:00','state'=>'listed','selected_service'=>'infrastructure'];
$encode=static fn($rows)=>json_encode(['version'=>1,'items'=>$rows],JSON_THROW_ON_ERROR);
$cases=0;
foreach(tenderAliases() as $id=>$aliases){
    foreach($aliases as $term){
        $item=array_replace($base,['title'=>'Request for '.$term,'selected_service'=>$id]);
        $r=tenderShortlist($encode([$item]),$now)['rows'][0];
        trCheck(isset($r['service_matches'][$id])&&$r['state']==='review_candidate','Alias match');++$cases;
    }
}
foreach([['state'=>'archived','expected'=>'archived'],['state'=>'cancelled','expected'=>'cancelled'],['state'=>'unconfirmed','expected'=>'requires_official_confirmation'],['deadline_ist'=>'2026-11-26T01:30:00','expected'=>'closed_by_observed_deadline'],['observed_on'=>'2026-11-24','expected'=>'stale_observation'],['deadline_ist'=>null,'expected'=>'deadline_unknown'],['title'=>'Paper clips','selected_service'=>null,'expected'=>'no_service_match']] as $patch){
    $expected=$patch['expected'];unset($patch['expected']);
    trCheck(tenderShortlist($encode([array_replace($base,$patch)]),$now)['rows'][0]['state']===$expected,'Notice state');++$cases;
}
foreach([['source'=>'unknown'],['reference'=>'bad?ref'],['title'=>'Call person@example.com'],['selected_service'=>'data-recovery'],['deadline_ist'=>'2026-02-30T01:30:00'],['observed_on'=>'2026-11-27'],['extra'=>true]] as $patch){trBlocked(static fn()=>tenderShortlist($encode([array_replace($base,$patch)]),$now));++$cases;}
trBlocked(static fn()=>tenderShortlist($encode([$base,$base]),$now));
trBlocked(static fn()=>tenderShortlist(str_replace('"version":1','"version":0,"version":1',$encode([$base])),$now));
echo "Tender matching and deadline cases: ".$cases." PASS\n";
if(($argv[1]??'')!=='--database')exit;
if(getenv('AADITECHS_DISPOSABLE_TEST')!=='yes'||getenv('CI_DB_NAME')!=='aaditechs_ci'||getenv('CI_DB_USER')!=='aaditechs_ci'||getenv('CI_DB_HOST')!=='127.0.0.1'||getenv('CI_DB_PASSWORD')!=='ci_only_not_real_password')exit(2);
$db=new PDO('mysql:host=127.0.0.1;port='.(int)getenv('CI_DB_PORT').';dbname=aaditechs_ci;charset=utf8mb4','aaditechs_ci',getenv('CI_DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
$raw=$encode([$base]);$db->beginTransaction();
try{
    growthCommand($db,'/pause',$cfg);trBlocked(static fn()=>tenderReviewPlan($db,$cfg,$raw,false,$now));
    growthCommand($db,'/resume',$cfg);
    $p=tenderReviewPlan($db,$cfg,$raw,false,$now);trCheck($p['create_count']===1,'Plan');
    trBlocked(static fn()=>tenderReviewApply($db,$cfg,$raw,str_repeat('0',64),$now));
    $r=tenderReviewApply($db,$cfg,$raw,$p['digest'],$now);trCheck($r['created']===1&&!$r['bid_authorized'],'Create');
    $q=$db->prepare('SELECT status,task_type,draft FROM growth_tasks WHERE id=?');$q->execute([$r['task_ids'][0]]);$row=$q->fetch(PDO::FETCH_ASSOC);
    trCheck($row['status']==='pending_review'&&$row['task_type']==='research'&&str_contains($row['draft'],$base['deadline_ist']),'Private research task');
    trBlocked(static fn()=>tenderReviewApply($db,$cfg,$raw,$p['digest'],$now));
    $p=tenderReviewPlan($db,$cfg,$raw,false,$now);trCheck($p['create_count']===0,'Preserve');
    $r=tenderReviewApply($db,$cfg,$raw,$p['digest'],$now);trCheck($r['preserved']===1,'Idempotent');
    trBlocked(static fn()=>tenderReviewPlan($db,$cfg,$raw,false,$now->modify('+5 days')));
    $db->rollBack();echo "Disposable tender review plan/apply/expiry/rollback PASS\n";
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();fwrite(STDERR,'Tender review test failed: '.$e->getMessage()."\n");exit(1);}
