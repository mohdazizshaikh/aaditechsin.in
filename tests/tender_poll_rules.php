<?php
declare(strict_types=1);
require dirname(__DIR__).'/secure-setup/maintenance/tender-poll-lib.php';
function tpCheck(bool $v,string $m):void{if(!$v)throw new LogicException($m);}
function tpBlocked(callable $f):void{try{$f();}catch(RuntimeException|InvalidArgumentException|JsonException $e){return;}throw new LogicException('Expected refusal');}
$now=new DateTimeImmutable('2026-11-26 02:00:00',new DateTimeZone('Asia/Kolkata'));
$clock=static fn()=>$now;
$provider=['authorized'=>true,'host'=>'example.com','url'=>'https://example.com/tenders.json','services'=>array_keys(growthCatalog()),'bearer_token'=>str_repeat('a',32)];
$cfg=['growth'=>['enabled'=>true],'discovery'=>['enabled'=>true,'tender_review_enabled'=>true,'tender_poll_enabled'=>true,'tender_poll_minutes'=>60,'tender_feeds'=>['nicsi'=>$provider]]];
$src=tenderPollConfig($cfg)['sources']['nicsi'];
$base=['source'=>'nicsi','reference'=>'POLL/CI/2026/A','title'=>'Network switches','location'=>'Mumbai','observed_on'=>'2026-11-26','deadline_ist'=>'2026-11-30T15:00:00','state'=>'listed','selected_service'=>null];
$encode=static fn($items)=>json_encode(['version'=>1,'items'=>$items],JSON_THROW_ON_ERROR);
$raw=$encode([$base]);
foreach(tenderAliases() as $id=>$terms){
    $one=$src;$one['services']=[$id];
    $item=array_replace($base,['title'=>'Supply '.$terms[0]]);
    $p=tenderPollPrepare($encode([$item]),$one,$now);
    tpCheck(json_decode($p['selected'],true)['items'][0]['selected_service']===$id,'All service selection');
}
$p=tenderPollPrepare($raw,$src,$now);tpCheck($p['counts']['review_candidate']===1,'Eligible');
$one=$src;$one['services']=['data-recovery'];
tpCheck(tenderPollPrepare($raw,$one,$now)['counts']['outside_service_scope']===1,'Scope exclusion');
tpCheck(tenderPollPrepare($encode([]),$src,$now)['notices']===0,'Empty feed');
tpCheck(tenderPollPrepare($raw,$src,$now->modify('+5 days'))['selected']===null,'Expired');
foreach([['source'=>'gem'],['selected_service'=>'infrastructure'],['reference'=>'bad?'],['deadline_ist'=>'bad']] as $patch)tpBlocked(static fn()=>tenderPollPrepare($encode([array_replace($base,$patch)]),$src,$now));
foreach([['authorized'=>false],['url'=>'https://example.com/feed?secret=x'],['services'=>['unknown']],['services'=>['cloud','cloud']],['bearer_token'=>"bad\r\nvalue"],['bearer_token'=>'REPLACE_WITH_SECRET_TOKEN']] as $patch){
    $bad=$cfg;$bad['discovery']['tender_feeds']['nicsi']=array_replace($provider,$patch);tpBlocked(static fn()=>tenderPollConfig($bad));
}
tpBlocked(static fn()=>tenderPollConfig([]));
echo "Tender poll: all 14 service selections, source, authorization, expiry, empty and credential checks PASS\n";
if(($argv[1]??'')!=='--database')exit;
if(getenv('AADITECHS_DISPOSABLE_TEST')!=='yes'||getenv('CI_DB_NAME')!=='aaditechs_ci'||getenv('CI_DB_USER')!=='aaditechs_ci'||getenv('CI_DB_HOST')!=='127.0.0.1'||getenv('CI_DB_PASSWORD')!=='ci_only_not_real_password')exit(2);
$db=new PDO('mysql:host=127.0.0.1;port='.(int)getenv('CI_DB_PORT').';dbname=aaditechs_ci;charset=utf8mb4','aaditechs_ci',getenv('CI_DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
$changePause=static function($cmd)use($db,$cfg){$db->beginTransaction();growthCommand($db,$cmd,$cfg);$db->commit();};
$changePause('/resume');$get=static fn()=>$cfg;
tpCheck(tenderPollStatus($db,$cfg)['sources'][0]['state']==='due','Fresh source');
$calls=0;$fetch=static function()use(&$calls,$raw){++$calls;return $raw;};
$r=tenderPollRun($db,$get,'nicsi',$fetch,$clock);tpCheck($r['created']===1&&$r['status']==='completed'&&$calls===1,'Real task insert');
$r=tenderPollRun($db,$get,'nicsi',$fetch,$clock);tpCheck($r['status']==='not_due'&&$calls===1,'Cadence');
$age=static fn()=>$db->exec("UPDATE growth_source_runs SET started_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 2 HOUR) WHERE source_id='tender-feed-nicsi'");
$age();$r=tenderPollRun($db,$get,'nicsi',$fetch,$clock);tpCheck($r['preserved']===1&&$r['created']===0,'Dedup');
$age();$r=tenderPollRun($db,$get,'nicsi',static fn()=>'{bad',$clock);tpCheck($r['status']==='failed'&&$r['created']===0,'Malformed response');
$age();$r=tenderPollRun($db,$get,'nicsi',static function()use($changePause,$raw){$changePause('/pause');return $raw;},$clock);tpCheck($r['error']==='growth_paused'&&$r['created']===0,'Pause during fetch');
$changePause('/resume');$age();$mutable=$cfg;
$r=tenderPollRun($db,static function()use(&$mutable){return $mutable;},'nicsi',static function()use(&$mutable,$raw){$mutable['discovery']['tender_poll_enabled']=false;return $raw;},$clock);
tpCheck($r['status']==='failed'&&$r['created']===0,'Config revoked during fetch');
$age();$r=tenderPollRun($db,$get,'nicsi',static fn()=>$encode([]),$clock);tpCheck($r['status']==='completed'&&$r['created']===0,'Empty successful run');
tpCheck((int)$db->query("SELECT COUNT(*) FROM growth_tasks WHERE title='Tender review: nicsi:POLL/CI/2026/A'")->fetchColumn()===1,'One stored research task');
$db->beginTransaction();
tpCheck(str_contains(growthCommand($db,'/discoveryruns',$cfg),'research tasks created'),'Tender ledger visible with generic feeds OFF');
tpCheck(str_contains(growthCommand($db,'/tenders',$cfg),'POLL/CI/2026/A'),'Owner tender queue');
$db->rollBack();
// Clean up only dedicated synthetic fixture rows before subsequent tests.
$db->exec("DELETE FROM growth_audit WHERE task_id IN (SELECT id FROM growth_tasks WHERE title='Tender review: nicsi:POLL/CI/2026/A')");
$db->exec("DELETE FROM growth_tasks WHERE title='Tender review: nicsi:POLL/CI/2026/A'");
$db->exec("DELETE FROM growth_source_runs WHERE source_id='tender-feed-nicsi'");
$changePause('/resume');echo "Disposable scheduled tender ingestion, cadence, dedup, failure, pause and config change PASS\n";
