<?php
declare(strict_types=1);
require dirname(__DIR__).'/public_html/api/intelligence-core.php';
require dirname(__DIR__).'/secure-setup/maintenance/gmail-health-read.php';
require dirname(__DIR__).'/secure-setup/maintenance/discovery-batch-lib.php';
require dirname(__DIR__).'/secure-setup/maintenance/followup-batch-lib.php';
function inCheck(bool $ok,string $m):void{if(!$ok)throw new RuntimeException($m);}
$now=new DateTimeImmutable('now',new DateTimeZone('UTC'));$day=$now->format('Y-m-d');
$cfg=['growth'=>['enabled'=>true],'communications'=>['enabled'=>true,'gmail'=>['enabled'=>true,'mailbox'=>'business@example.invalid','access_token'=>str_repeat('a',40),'generation'=>str_repeat('b',32),'expires_utc'=>$now->modify('+2 days')->format('Y-m-d\TH:i:s\Z'),'revoked'=>false]],'discovery'=>['enabled'=>true,'followups_enabled'=>true,'local_batch_enabled'=>true,'feeds'=>['ci-intel'=>['authorized'=>true,'url'=>'https://example.com/feed','host'=>'example.com','source_type'=>'company_announcement']]]];
$p=gmailConfig($cfg,$now->getTimestamp());
inCheck(gmailConfig([])===null&&$p!==null&&followDate($day,$now)&&!followDate($now->modify('+91 days')->format('Y-m-d'),$now),'OFF/date');
$bad=$cfg;$bad['communications']['gmail']['expires_utc']=$now->modify('+5 minutes')->format('Y-m-d\TH:i:s\Z');inCheck(gmailConfig($bad,$now->getTimestamp())===null,'Expiry');
$calls=[];$mock=static function($p,$resource)use(&$calls){$calls[]=$resource;return $resource==='profile'?['emailAddress'=>$p['mailbox']]:['id'=>'INBOX','type'=>'system','messagesTotal'=>42,'messagesUnread'=>7];};
$counts=gmailCollect($p,$mock);inCheck($counts===['inbox'=>42,'unread'=>7]&&$calls===['profile','labels/INBOX'],'Only metadata counts');
try{gmailCollect($p,static fn()=>['emailAddress'=>'other@example.invalid']);throw new LogicException('Wrong account');}catch(RuntimeException $e){}
try{gmailCounts(['id'=>'INBOX','type'=>'system','messagesTotal'=>2,'messagesUnread'=>3]);throw new LogicException('Invalid count');}catch(RuntimeException $e){}
inCheck(discoveryBatchSource($cfg,'ci-intel')['host']==='example.com','Source allowlist');
$batchCfg=$cfg;$batchCfg['discovery']['followup_batch_enabled']=true;
function followReject(callable $fn):void{try{$fn();}catch(InvalidArgumentException|RuntimeException|JsonException $e){return;}throw new LogicException('Invalid follow-up batch accepted');}
$cases=0;
for($n=1;$n<=50;$n++){
    $rawScope=json_encode(['version'=>1,'action'=>'set','ids'=>array_reverse(range(1,$n)),'due_on'=>$day],JSON_THROW_ON_ERROR);
    inCheck(followBatchScope($batchCfg,$rawScope,$now)['ids']===range(1,$n),'Canonical size '.$n);++$cases;
}
foreach([[],[0],[-1],[1,1],['1'],[true],[1.5],range(1,51),[1000000000000000]] as $ids){
    followReject(static fn()=>followBatchScope($batchCfg,json_encode(['version'=>1,'action'=>'set','ids'=>$ids,'due_on'=>$day]),$now));++$cases;
}
foreach(['2000-01-01','2999-01-01','2026-02-30','not-a-date',null] as $date){
    followReject(static fn()=>followBatchScope($batchCfg,json_encode(['version'=>1,'action'=>'set','ids'=>[1],'due_on'=>$date]),$now));++$cases;
}
foreach(['[]','{}','{"version":true,"action":"complete","ids":[1]}','{"version":1,"action":"send","ids":[1]}','{"version":1,"action":"cancel","ids":[1],"due_on":"2026-01-01"}','{"version":1,"action":"complete","ids":{"0":1}}','{"version":1,"action":"complete","ids":[1],"contact":"synthetic"}',str_repeat(' ',8193)] as $invalid){
    followReject(static fn()=>followBatchScope($batchCfg,$invalid,$now));++$cases;
}
followReject(static fn()=>followBatchScope($cfg,'{"version":1,"action":"complete","ids":[1]}',$now));++$cases;
inCheck($cases===73,'73 scope cases');echo "Reviewed reminder scope: $cases/73 cases PASS\n";
if(($argv[1]??'')!=='--database'){echo "Gmail metadata, followup date and batch source rules: PASS\n";exit;}
if(getenv('AADITECHS_DISPOSABLE_TEST')!=='yes'||getenv('CI_DB_NAME')!=='aaditechs_ci'||getenv('CI_DB_USER')!=='aaditechs_ci'||getenv('CI_DB_HOST')!=='127.0.0.1')exit(2);
$db=new PDO('mysql:host=127.0.0.1;port='.(int)getenv('CI_DB_PORT').';dbname=aaditechs_ci;charset=utf8mb4','aaditechs_ci',getenv('CI_DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
$db->beginTransaction();
try{
    growthCommand($db,'/resume',$cfg);gmailCapture($db,$cfg,$counts,$now);
    inCheck(str_contains(growthCommand($db,'/gmailhealth',$cfg),'42 messages, 7 unread')&&!str_contains(gmailSummary($db,$cfg,$now),$p['mailbox']),'Sanitized snapshot');
    inCheck(str_contains(gmailSummary($db,$cfg,$now->modify('+25 hours')),'stale'),'Stale');
    $rotated=$cfg;$rotated['communications']['gmail']['generation']=str_repeat('c',32);inCheck(str_contains(gmailSummary($db,$rotated,$now),'no current evidence'),'Rotation');
    $item=['source_url'=>'https://example.com/ci-intelligence','organization'=>'Synthetic Organization','location_label'=>'Mumbai','evidence'=>'Planned conference room and video conferencing upgrade','observed_on'=>$day,'signal_type'=>'upgrade'];
    $raw=json_encode(['version'=>1,'items'=>[$item]],JSON_THROW_ON_ERROR);
    $r=discoveryBatch($db,$cfg,'ci-intel',$raw);inCheck($r['imported']>0&&discoveryBatch($db,$cfg,'ci-intel',$raw)['duplicates']===$r['imported'],'Batch and dedupe');
    $q=$db->prepare('SELECT id FROM growth_opportunities WHERE source_url=? AND service_id=?');$q->execute([$item['source_url'],'video-conferencing']);$id=(int)$q->fetchColumn();inCheck($id>0,'Opportunity');
    inCheck(str_contains(growthCommand($db,"/followup $id $day",$cfg),'Qualified opportunity required'),'Qualification gate');
    growthCommand($db,"/qualify $id",$cfg);
    inCheck(str_contains(growthCommand($db,"/followup $id $day",$cfg),'reminder set')&&str_contains(growthCommand($db,'/followups',$cfg),'1 due/overdue'),'Due reminder');
    inCheck(str_contains(growthCommand($db,'/discoveryruns',$cfg),'ci-intel completed'),'Local run visible');
    $cfg['growth']['telegram']=['enabled'=>true,'reports_enabled'=>true,'owner_user_id'=>'12345','owner_chat_id'=>'12345','bot_token'=>'123456:'.str_repeat('a',30),'daily_after_ist'=>'00:00'];
    growthCommand($db,'/notifydaily on',$cfg);$report=reportClaim($db,$cfg,'daily',$now);
    inCheck($report!==null&&str_contains($report['text'],'42 messages, 7 unread')&&str_contains($report['text'],'1 due/overdue'),'Daily intelligence digest');
    $s=growthCommand($db,'/intelligence',$cfg);inCheck(str_contains($s,'1 due/overdue')&&str_contains($s,'42 messages, 7 unread'),'Combined report');
    inCheck(str_contains(growthCommand($db,"/completefollowup $id",$cfg),'completed')&&str_contains(followSummary($db),'0 due/overdue'),'Complete');
    // Fourteen services: qualification, creation, no-op, stale digest, rescheduling and closure.
    $db->exec('SAVEPOINT followup_matrix');$ids=[];$matrix=0;
    foreach(discoveryTerms() as $service=>$phrases){
        $v=['source_type'=>'company_announcement','source_url'=>'https://example.com/follow-batch/'.$service,'organization'=>'Synthetic Reminder','location_label'=>'Mumbai','evidence'=>'Planned '.$phrases[0].' assessment','observed_on'=>$day,'signal_type'=>'upgrade','service_id'=>$service];
        $oid=discoveryImport($db,$v)['id'];$ids[]=$oid;
        $scope=json_encode(['version'=>1,'action'=>'set','ids'=>[$oid],'due_on'=>$day]);
        followReject(static fn()=>followBatchPlan($db,$batchCfg,$scope,false,$now));++$matrix;
        growthCommand($db,"/qualify $oid",$cfg);
        $plan=followBatchPlan($db,$batchCfg,$scope,false,$now);
        inCheck(followBatchApply($db,$batchCfg,$scope,$plan['digest'],$now)['applied']===1,'Create '.$service);++$matrix;
        followReject(static fn()=>followBatchPlan($db,$batchCfg,$scope,false,$now));++$matrix;
        $next=json_encode(['version'=>1,'action'=>'set','ids'=>[$oid],'due_on'=>$now->modify('+1 day')->format('Y-m-d')]);
        followReject(static fn()=>followBatchApply($db,$batchCfg,$next,$plan['digest'],$now));++$matrix;
        $nextPlan=followBatchPlan($db,$batchCfg,$next,false,$now);
        inCheck(followBatchApply($db,$batchCfg,$next,$nextPlan['digest'],$now)['applied']===1,'Reschedule '.$service);++$matrix;
    }
    $close=json_encode(['version'=>1,'action'=>'complete','ids'=>$ids]);
    $closePlan=followBatchPlan($db,$batchCfg,$close,false,$now);
    growthCommand($db,'/pause',$cfg);
    followReject(static fn()=>followBatchApply($db,$batchCfg,$close,$closePlan['digest'],$now));
    $closePlan=followBatchPlan($db,$batchCfg,$close,false,$now);
    inCheck(followBatchApply($db,$batchCfg,$close,$closePlan['digest'],$now)['applied']===14,'Close all while paused');
    foreach($ids as $oid){
        $one=json_encode(['version'=>1,'action'=>'cancel','ids'=>[$oid]]);
        followReject(static fn()=>followBatchPlan($db,$batchCfg,$one,false,$now));++$matrix;
    }
    inCheck($matrix===84,'84 per-service cases');
    $db->exec('ROLLBACK TO SAVEPOINT followup_matrix');$db->exec('RELEASE SAVEPOINT followup_matrix');
    echo "Reviewed reminder service matrix: $matrix/84 cases PASS\n";
    growthCommand($db,'/pause',$cfg);
    try{gmailCapture($db,$cfg,$counts,$now);throw new LogicException('Pause bypass');}catch(RuntimeException $e){}
    try{discoveryBatch($db,$cfg,'ci-intel',$raw);throw new LogicException('Pause bypass');}catch(RuntimeException $e){}
    $db->rollBack();echo "Synthetic intelligence, snapshot and followup: PASS\n";
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();fwrite(STDERR,'Intelligence test failed: '.$e->getMessage()."\n");exit(1);}
