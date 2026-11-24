<?php
declare(strict_types=1);
require dirname(__DIR__).'/public_html/api/twilio-inbound-core.php';
require dirname(__DIR__).'/secure-setup/maintenance/whatsapp-retention-lib.php';
require dirname(__DIR__).'/public_html/api/intelligence-core.php';
function tiCheck(bool $ok,string $m):void{if(!$ok)throw new RuntimeException($m);}
function tiBlocked(callable $fn):void{try{$fn();}catch(RuntimeException|InvalidArgumentException $e){return;}throw new LogicException('Expected refusal');}
$now=new DateTimeImmutable('now',new DateTimeZone('UTC'));$day=$now->modify('-1 day')->format('Y-m-d');
$base=['enabled'=>true,'authorized'=>true,'log_redaction_verified'=>true,'account'=>'AC'.str_repeat('a',32),'to'=>'+12025550101','auth_token'=>str_repeat('b',32),'dedupe_key_hex'=>str_repeat('c',64)];
$cfg=['growth'=>['enabled'=>true],'communications'=>['enabled'=>true,'whatsapp_retention_enabled'=>true,'twilio_inbound'=>['sms'=>$base+['url'=>'https://example.invalid/api/twilio-sms-inbound.php'],'calls'=>$base+['url'=>'https://example.invalid/api/twilio-voice-inbound.php']]]];
$s=twInboundConfig($cfg,'sms');$v=twInboundConfig($cfg,'calls');
tiCheck($s!==null&&$v!==null&&twInboundConfig([],'sms')===null,'Default off');
$sign=static function(array $p,array $fields):array{ksort($fields,SORT_STRING);$data=$p['url'];foreach($fields as $k=>$x)$data.=$k.$x;return [http_build_query($fields,'','&',PHP_QUERY_RFC3986),base64_encode(hash_hmac('sha1',$data,$p['auth_token'],true))];};
$sms=['AccountSid'=>$base['account'],'To'=>$base['to'],'From'=>'+12025550102','MessageSid'=>'SM'.str_repeat('d',32),'SmsStatus'=>'received','NumMedia'=>'0','Body'=>'Synthetic private message + & ='];
$voice=['AccountSid'=>$base['account'],'To'=>$base['to'],'From'=>'+12025550102','CallSid'=>'CA'.str_repeat('e',32),'Direction'=>'inbound','CallStatus'=>'completed'];
[$raw,$sig]=$sign($s,$sms);[$vr,$vs]=$sign($v,$voice);
$h=twInboundDecode($s,$raw,$sig);tiCheck(strlen($h)===64&&strlen(twInboundDecode($v,$vr,$vs))===64,'Signed events');
tiBlocked(static fn()=>twInboundDecode($s,$raw.'x',$sig));
tiBlocked(static fn()=>twInboundDecode($v,$raw,$sig));
tiBlocked(static fn()=>twInboundForm('Body=a&Body=b'));
tiBlocked(static fn()=>twInboundForm('Body=%xx'));
foreach([['AccountSid'=>'wrong'],['To'=>'+12025550103'],['NumMedia'=>'1'],['SmsStatus'=>'sent']] as $bad){[$r,$g]=$sign($s,array_replace($sms,$bad));tiBlocked(static fn()=>twInboundDecode($s,$r,$g));}
foreach([['Direction'=>'outbound-api'],['CallStatus'=>'ringing']] as $bad){[$r,$g]=$sign($v,array_replace($voice,$bad));tiBlocked(static fn()=>twInboundDecode($v,$r,$g));}
if(($argv[1]??'')!=='--database'){echo "Twilio form signature and channel rules: PASS\n";exit;}
if(getenv('AADITECHS_DISPOSABLE_TEST')!=='yes'||getenv('CI_DB_NAME')!=='aaditechs_ci'||getenv('CI_DB_USER')!=='aaditechs_ci'||getenv('CI_DB_HOST')!=='127.0.0.1'||getenv('CI_DB_PASSWORD')!=='ci_only_not_real_password')exit(2);
$db=new PDO('mysql:host=127.0.0.1;port='.(int)getenv('CI_DB_PORT').';dbname=aaditechs_ci;charset=utf8mb4','aaditechs_ci',getenv('CI_DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
$db->beginTransaction();
try{
    growthCommand($db,'/resume',$cfg);
    tiCheck(twInboundRecord($db,$cfg,'sms',$raw,$sig,$now->modify('-1 day'))&&!twInboundRecord($db,$cfg,'sms',$raw,$sig,$now),'Dedup across receipt days');
    tiCheck(twInboundRecord($db,$cfg,'calls',$vr,$vs,$now->modify('-1 day')),'Voice callback');
    $summary=twInboundSummary($db,$cfg,$now);tiCheck(str_contains($summary,'sms: 1 distinct recorded callbacks')&&str_contains($summary,'calls: 1 distinct recorded callbacks')&&!str_contains($summary,$base['to']),'Counts only');
    tiCheck(str_contains(growthCommand($db,'/twinbound',$cfg),'distinct recorded callbacks'),'Owner command');
    tiBlocked(static fn()=>waRetentionPlan($db,$cfg,$now));
    growthCommand($db,'/pause',$cfg);tiBlocked(static fn()=>twInboundRecord($db,$cfg,'sms',$raw,$sig,$now));
    $q=$db->prepare('INSERT INTO growth_whatsapp_inbound(event_hash,account_hash,day_utc) VALUES(?,?,?)');
    $old=str_repeat('1',64);$keep=str_repeat('2',64);$extra=str_repeat('3',64);$acct=str_repeat('4',64);
    $q->execute([$old,$acct,$now->modify('-31 days')->format('Y-m-d')]);$q->execute([$keep,$acct,$now->modify('-30 days')->format('Y-m-d')]);
    $plan=waRetentionPlan($db,$cfg,$now);tiCheck(in_array($old,array_column($plan['rows'],'event_hash'),true)&&!in_array($keep,array_column($plan['rows'],'event_hash'),true),'Cutoff');
    $q->execute([$extra,$acct,$now->modify('-32 days')->format('Y-m-d')]);tiBlocked(static fn()=>waRetentionApply($db,$cfg,$plan['digest'],$now));
    $plan=waRetentionPlan($db,$cfg,$now);tiCheck(waRetentionApply($db,$cfg,$plan['digest'],$now)===count($plan['rows']),'Exact cleanup');
    $q=$db->prepare('SELECT COUNT(*) FROM growth_whatsapp_inbound WHERE event_hash=?');$q->execute([$keep]);tiCheck((int)$q->fetchColumn()===1,'Boundary preserved');
    $q=$db->prepare('SELECT COUNT(*) FROM growth_twilio_inbound WHERE event_hash=?');$q->execute([$h]);tiCheck((int)$q->fetchColumn()===1,'Twilio replay digest preserved');
    $db->rollBack();echo "Synthetic Twilio callbacks and WhatsApp retention: PASS\n";
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();fwrite(STDERR,'Inbound/retention test failed: '.$e->getMessage()."\n");exit(1);}
