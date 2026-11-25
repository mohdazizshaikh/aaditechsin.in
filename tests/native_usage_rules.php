<?php
declare(strict_types=1);
require dirname(__DIR__).'/secure-setup/maintenance/native-usage-read.php';
require dirname(__DIR__).'/public_html/api/intelligence-core.php';
function nuCheck(bool $ok,string $m):void{if(!$ok)throw new RuntimeException($m);}
function nuBlocked(callable $f):void{try{$f();}catch(RuntimeException $e){return;}throw new LogicException('Expected refusal');}
$now=new DateTimeImmutable('now',new DateTimeZone('UTC'));$day=nativeUsageDay($now);$ts=$now->getTimestamp();
$base=['enabled'=>true,'authorized'=>true,'revoked'=>false,'generation'=>str_repeat('a',32),'expires_utc'=>$now->modify('+2 days')->format('Y-m-d\TH:i:s\Z')];
$tw=$base+['account'=>'AC'.str_repeat('b',32),'key'=>'SK'.str_repeat('c',32),'secret'=>str_repeat('d',40)];
$cfg=['growth'=>['enabled'=>true],'communications'=>['enabled'=>true,'native_usage_enabled'=>true,'native_usage'=>['whatsapp'=>$base+['account'=>'123','api_version'=>'v27.0','token'=>str_repeat('e',40)],'twilio-sms'=>$tw,'twilio-calls'=>$tw]]];
nuCheck(nativeUsageConfig([],'whatsapp')===null&&nativeUsageConfig($cfg,'whatsapp',$ts+172800)===null,'OFF/expiry');
$p=nativeUsageConfig($cfg,'whatsapp',$ts);$start=strtotime($day.' UTC');$wa=['id'=>'123','analytics'=>['granularity'=>'DAY','data_points'=>[['start'=>$start,'end'=>$start+86400,'sent'=>10,'delivered'=>8]]]];
nuCheck(nativeUsageParse($p,$day,$wa)===['count'=>10,'delivered'=>8],'WABA metrics');
nuBlocked(static fn()=>nativeUsageParse($p,$day,array_replace($wa,['id'=>'456'])));
nuBlocked(static fn()=>nativeUsageParse($p,$day,['id'=>'123','analytics'=>['granularity'=>'DAY','data_points'=>[]]]));
foreach(['twilio-sms','twilio-calls'] as $source){
    $p=nativeUsageConfig($cfg,$source,$ts);$sms=$source==='twilio-sms';
    $r=['next_page_uri'=>null,'usage_records'=>[['account_sid'=>$p['account'],'category'=>$sms?'sms-inbound':'calls-inbound','start_date'=>$day,'end_date'=>$day,'count_unit'=>$sms?'messages':'calls','count'=>'7']]];
    nuCheck(nativeUsageParse($p,$day,$r)===['count'=>7],'Twilio metrics');
    nuBlocked(static fn()=>nativeUsageParse($p,$day,array_replace($r,['next_page_uri'=>'/next'])));
    $bad=$r;$bad['usage_records'][0]['account_sid']='wrong';nuBlocked(static fn()=>nativeUsageParse($p,$day,$bad));
}
if(($argv[1]??'')!=='--database'){echo "Native provider usage parsing and credential rules: PASS\n";exit;}
if(getenv('AADITECHS_DISPOSABLE_TEST')!=='yes'||getenv('CI_DB_NAME')!=='aaditechs_ci'||getenv('CI_DB_USER')!=='aaditechs_ci'||getenv('CI_DB_HOST')!=='127.0.0.1'||getenv('CI_DB_PASSWORD')!=='ci_only_not_real_password')exit(2);
$db=new PDO('mysql:host=127.0.0.1;port='.(int)getenv('CI_DB_PORT').';dbname=aaditechs_ci;charset=utf8mb4','aaditechs_ci',getenv('CI_DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
$db->beginTransaction();
try{
    growthCommand($db,'/resume',$cfg);nativeUsageCapture($db,$cfg,'whatsapp',$day,['count'=>10,'delivered'=>8],$now);nativeUsageCapture($db,$cfg,'twilio-sms',$day,['count'=>7],$now);
    $s=nativeUsageSummary($db,$cfg,$now);
    nuCheck(str_contains($s,'sent 10, delivered 8')&&str_contains($s,'inbound usage count 7')&&str_contains($s,'twilio-calls: no current data'),'Separate snapshot');
    nuCheck(str_contains(growthCommand($db,'/nativeusage',$cfg),'sent 10, delivered 8')&&str_contains(growthCommand($db,'/intelligence',$cfg),'inbound usage count 7'),'Owner reporting');
    nuCheck(!str_contains($s,$tw['account'])&&!str_contains($s,$tw['secret']),'No credentials');
    $rot=$cfg;$rot['communications']['native_usage']['whatsapp']['generation']=str_repeat('f',32);nuCheck(str_contains(nativeUsageSummary($db,$rot,$now),'whatsapp: no current data'),'Rotation hides old snapshot');
    growthCommand($db,'/pause',$cfg);nuBlocked(static fn()=>nativeUsageCapture($db,$cfg,'whatsapp',$day,['count'=>1,'delivered'=>1],$now));
    $db->rollBack();echo "Synthetic native provider snapshot: PASS\n";
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();fwrite(STDERR,'Native usage test failed: '.$e->getMessage()."\n");exit(1);}
