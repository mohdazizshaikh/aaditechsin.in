<?php
declare(strict_types=1);
require dirname(__DIR__).'/public_html/api/intelligence-core.php';
require dirname(__DIR__).'/secure-setup/maintenance/gmail-health-read.php';
require dirname(__DIR__).'/secure-setup/maintenance/discovery-batch-lib.php';
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
    $s=growthCommand($db,'/intelligence',$cfg);inCheck(str_contains($s,'1 due/overdue')&&str_contains($s,'42 messages, 7 unread'),'Combined report');
    inCheck(str_contains(growthCommand($db,"/completefollowup $id",$cfg),'completed')&&str_contains(followSummary($db),'0 due/overdue'),'Complete');
    growthCommand($db,'/pause',$cfg);
    try{gmailCapture($db,$cfg,$counts,$now);throw new LogicException('Pause bypass');}catch(RuntimeException $e){}
    try{discoveryBatch($db,$cfg,'ci-intel',$raw);throw new LogicException('Pause bypass');}catch(RuntimeException $e){}
    $db->rollBack();echo "Synthetic intelligence, snapshot and followup: PASS\n";
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();fwrite(STDERR,'Intelligence test failed: '.$e->getMessage()."\n");exit(1);}
