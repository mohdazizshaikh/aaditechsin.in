<?php
declare(strict_types=1);
require dirname(__DIR__).'/secure-setup/maintenance/tender-poll-lib.php';
require dirname(__DIR__).'/public_html/api/tender-intelligence-core.php';
function tiCheck(bool $v,string $m):void{if(!$v)throw new LogicException($m);}
function tiBlocked(callable $f):void{try{$f();}catch(RuntimeException|InvalidArgumentException|JsonException $e){return;}throw new LogicException('Expected refusal');}
$now=new DateTimeImmutable('2026-11-26 02:00:00',new DateTimeZone('Asia/Kolkata'));
$cfg=['growth'=>['enabled'=>true],'discovery'=>['enabled'=>true,'tender_review_enabled'=>true,'tender_intelligence_enabled'=>true,'tender_poll_enabled'=>true,'tender_poll_minutes'=>60,'tender_feeds'=>['nicsi'=>['authorized'=>true,'host'=>'example.com','url'=>'https://example.com/tenders.json','services'=>array_keys(growthCatalog()),'bearer_token'=>null]]]];
$base=['source'=>'nicsi','reference'=>'INTEL/CI/2026/A','title'=>'Network switches','location'=>'Mumbai','observed_on'=>'2026-11-26','deadline_ist'=>'2026-11-30T15:00:00','state'=>'listed','selected_service'=>null];
$enc=static fn($items)=>json_encode(['version'=>1,'items'=>$items],JSON_THROW_ON_ERROR);
$cases=0;
foreach(tenderAliases() as $id=>$terms){
    foreach(['Mumbai'=>'local_label','Pune'=>'state_label','Not stated'=>'unverified_or_other'] as $loc=>$label){
        foreach(['2026-11-27T02:00:00'=>20,'2026-11-30T02:00:00'=>10,'2026-12-06T02:00:00'=>0] as $date=>$urgency){
            $row=tenderIntelRank($enc([array_replace($base,['title'=>'Supply '.$terms[0],'location'=>$loc,'deadline_ist'=>$date])]),$now)['rows'][0];
            tiCheck($row['location_review']===$label&&isset($row['relevance_scores'][$id]),'Service and region');
            tiCheck($row['review_priority']===min(100,max($row['relevance_scores'])+match($label){'local_label'=>20,'state_label'=>10,default=>0}+$urgency),'Priority');++$cases;
        }
    }
    foreach(['cancelled','archived','unconfirmed'] as $state){
        $r=tenderIntelRank($enc([array_replace($base,['title'=>$terms[0],'state'=>$state])]),$now)['rows'][0];
        tiCheck($r['review_priority']===null,'Not eligible');++$cases;
    }
}
tiCheck($cases===168,'Case count');
foreach(['2026-11-26T02:00:00'=>'recorded_deadline_elapsed','2026-11-29T02:00:00'=>'within_3_days','2026-12-03T02:00:00'=>'within_7_days','2026-12-03T02:00:01'=>'later','2026-02-30T02:00:00'=>null] as $date=>$state)tiCheck(tenderDeadlineBucket($date,$now)===$state,'Deadline boundary');
$old=array_intersect_key($base,array_flip(['observed_on','deadline_ist','title','location']));
foreach([['state'=>'cancelled'],['state'=>'archived'],['deadline_ist'=>'2026-12-01T15:00:00'],['deadline_ist'=>null],['title'=>'Supply network switches and laptops'],['location'=>'Thane']] as $patch){
    $row=tenderIntelRank($enc([array_replace($base,$patch)]),$now)['rows'][0];tiCheck(count(tenderIntelChanges($old,$row,$now))>0,'Material change');
}
foreach([[],['state'=>'unconfirmed'],['observed_on'=>'2026-11-24']] as $patch){
    $row=tenderIntelRank($enc([array_replace($base,$patch)]),$now)['rows'][0];tiCheck(!tenderIntelChanges($old,$row,$now),'No unsupported change');
}
echo "Tender intelligence: $cases ranking scenarios and deadline/amendment rules PASS\n";
if(($argv[1]??'')!=='--database')exit;
if(getenv('AADITECHS_DISPOSABLE_TEST')!=='yes'||getenv('CI_DB_NAME')!=='aaditechs_ci'||getenv('CI_DB_USER')!=='aaditechs_ci'||getenv('CI_DB_HOST')!=='127.0.0.1'||getenv('CI_DB_PASSWORD')!=='ci_only_not_real_password')exit(2);
$db=new PDO('mysql:host=127.0.0.1;port='.(int)getenv('CI_DB_PORT').';dbname=aaditechs_ci;charset=utf8mb4','aaditechs_ci',getenv('CI_DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
$original=(int)$db->query('SELECT paused FROM growth_control WHERE id=1')->fetchColumn();
$clock=static fn()=>$now;$get=static fn()=>$cfg;
$age=static fn()=>$db->exec("UPDATE growth_source_runs SET started_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 2 HOUR) WHERE source_id='tender-feed-nicsi'");
try{
    $db->exec('UPDATE growth_control SET paused=0 WHERE id=1');
    $first=tenderPollRun($db,$get,'nicsi',static fn()=>$enc([$base]),$clock);
    tiCheck($first['created']===1&&$first['amendment_reviews_created']===0,'Original only');
    $originalTask=$db->query("SELECT * FROM growth_tasks WHERE title='Tender review: nicsi:INTEL/CI/2026/A'")->fetch(PDO::FETCH_ASSOC);
    $amended=array_replace($base,['deadline_ist'=>'2026-12-02T15:00:00']);
    $age();$r=tenderPollRun($db,$get,'nicsi',static fn()=>$enc([$amended]),$clock);
    tiCheck($r['preserved']===1&&$r['amendment_reviews_created']===1,'New amendment');
    $age();$r=tenderPollRun($db,$get,'nicsi',static fn()=>$enc([$amended]),$clock);
    tiCheck($r['amendment_reviews_preserved']===1&&$r['amendment_reviews_created']===0,'Dedup');
    $age();$r=tenderPollRun($db,$get,'nicsi',static fn()=>$enc([array_replace($amended,['state'=>'cancelled'])]),$clock);
    tiCheck($r['amendment_reviews_created']===1&&$r['created']===0,'Cancelled notice review only');
    $age();$r=tenderPollRun($db,$get,'nicsi',static fn()=>$enc([]),$clock);tiCheck($r['amendment_reviews_created']===0,'Missing not cancellation');
    $db->beginTransaction();
    $p=tenderIntelPlan($db,$cfg,$enc([array_replace($base,['location'=>'Thane'])]),false,$now);
    tiBlocked(static fn()=>tenderIntelApply($db,$cfg,$enc([array_replace($base,['location'=>'Thane'])]),str_repeat('0',64),$now));
    tiCheck(count($p['items'])===1,'Manual plan');
    $r=tenderIntelApply($db,$cfg,$enc([array_replace($base,['location'=>'Thane'])]),$p['digest'],$now);tiCheck($r['created']===1,'Manual apply');$db->rollBack();
    $db->beginTransaction();$report=tenderDeadlineReport($db,$now);
    $stored=array_values(array_filter($report['rows'],static fn($r)=>$r['id']===(int)$originalTask['id']));
    tiCheck(count($stored)===1&&$stored[0]['bucket']==='within_7_days','Recorded deadline report');
    tiCheck(str_contains(growthCommand($db,'/tenderchanges',$cfg),'INTEL/CI/2026/A'),'Owner amendment queue');
    tiCheck(str_contains(growthCommand($db,'/tenderdeadlines',$cfg),'Recorded tender deadlines'),'Owner deadline queue');
    $db->rollBack();
    tiCheck($db->query("SELECT * FROM growth_tasks WHERE title='Tender review: nicsi:INTEL/CI/2026/A'")->fetch(PDO::FETCH_ASSOC)===$originalTask,'Original unchanged');
    $age();$mutable=$cfg;$r=tenderPollRun($db,static function()use(&$mutable){return $mutable;},'nicsi',static function()use(&$mutable,$enc,$amended){$mutable['discovery']['tender_intelligence_enabled']=false;return $enc([$amended]);},$clock);
    tiCheck($r['status']==='failed','Authorization drift');
}finally{
    if($db->inTransaction())$db->rollBack();
    $db->exec("DELETE FROM growth_audit WHERE task_id IN (SELECT id FROM growth_tasks WHERE title LIKE 'Tender review: nicsi:INTEL/CI/2026/A' OR title LIKE 'Tender change: nicsi:INTEL/CI/2026/A %')");
    $db->exec("DELETE FROM growth_tasks WHERE title LIKE 'Tender review: nicsi:INTEL/CI/2026/A' OR title LIKE 'Tender change: nicsi:INTEL/CI/2026/A %'");
    $db->exec("DELETE FROM growth_source_runs WHERE source_id='tender-feed-nicsi'");
    $db->exec('UPDATE growth_control SET paused='.$original.' WHERE id=1');
}
echo "Disposable tender intelligence: scheduled amendment/cancellation, dedup, original preservation, deadline queue and drift PASS\n";
