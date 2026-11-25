<?php
declare(strict_types=1);
require dirname(__DIR__).'/secure-setup/maintenance/communications-bridge-lib.php';
function cbCheck(bool $ok,string $m):void{if(!$ok)throw new RuntimeException($m);}
function cbBlocked(callable $fn):void{try{$fn();}catch(RuntimeException|InvalidArgumentException|JsonException $e){return;}throw new LogicException('Expected rejection');}
$now=new DateTimeImmutable('now',new DateTimeZone('UTC'));$day=$now->modify('-1 day')->format('Y-m-d');
$src=['enabled'=>true,'authorized'=>true,'url'=>'https://example.com/summary','host'=>'example.com','hmac_key_hex'=>str_repeat('a',64),'bearer_token'=>str_repeat('b',40),'generation'=>str_repeat('c',32)];
$cfg=['growth'=>['enabled'=>true],'communications'=>['enabled'=>true,'bridge_enabled'=>true,'bridges'=>['whatsapp'=>$src,'sms'=>$src,'calls'=>$src]]];
$s=commBridgeConfig($cfg,'whatsapp');
$row=['version'=>1,'generation'=>$src['generation'],'generated_utc'=>$now->format('Y-m-d\TH:i:s\Z'),'channel'=>'whatsapp','day_utc'=>$day,'received'=>8,'answered'=>5,'missed'=>2];
$sign=static function(array $r)use($s):string{$p=json_encode($r,JSON_THROW_ON_ERROR);return json_encode(['payload_b64'=>base64_encode($p),'signature'=>hash_hmac('sha256',"aaditechs-communications-v1\n".$p,$s['key'])],JSON_THROW_ON_ERROR);};
$raw=$sign($row);cbCheck(commBridgeDecode($s,$raw,$now)['received']===8,'Valid signed record');
cbBlocked(static fn()=>commBridgeConfig([],'whatsapp'));
cbBlocked(static fn()=>commBridgeConfig($cfg,'email'));
$bad=$cfg;$bad['communications']['bridges']['whatsapp']['url']='https://example.com/summary?secret=1';cbBlocked(static fn()=>commBridgeConfig($bad,'whatsapp'));
foreach([['channel'=>'sms'],['generation'=>str_repeat('d',32)],['generated_utc'=>$now->modify('-25 hours')->format('Y-m-d\TH:i:s\Z')],['generated_utc'=>$now->modify('+6 minutes')->format('Y-m-d\TH:i:s\Z')],['day_utc'=>$now->format('Y-m-d')],['received'=>1],['phone'=>'synthetic']] as $v)cbBlocked(static fn()=>commBridgeDecode($s,$sign(array_replace($row,$v)),$now));
$env=json_decode($raw,true);$env['signature']=str_repeat('0',64);cbBlocked(static fn()=>commBridgeDecode($s,json_encode($env),$now));
cbCheck(!feedPublicIp('127.0.0.1')&&!feedPublicIp('169.254.169.254'),'Private destinations blocked');
if(($argv[1]??'')!=='--database'){echo "Signed communication bridge contract: PASS\n";exit;}
if(getenv('AADITECHS_DISPOSABLE_TEST')!=='yes'||getenv('CI_DB_NAME')!=='aaditechs_ci'||getenv('CI_DB_USER')!=='aaditechs_ci'||getenv('CI_DB_HOST')!=='127.0.0.1'||getenv('CI_DB_PASSWORD')!=='ci_only_not_real_password')exit(2);
$db=new PDO('mysql:host=127.0.0.1;port='.(int)getenv('CI_DB_PORT').';dbname=aaditechs_ci;charset=utf8mb4','aaditechs_ci',getenv('CI_DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
$db->beginTransaction();
try{
    growthCommand($db,'/resume',$cfg);
    foreach(['whatsapp','sms','calls'] as $channel){
        $r=$sign(array_replace($row,['channel'=>$channel]));
        cbCheck(commBridgeImport($db,$cfg,$channel,$r,$now)&&!commBridgeImport($db,$cfg,$channel,$r,$now),'Idempotence');
    }
    cbBlocked(static fn()=>commBridgeImport($db,$cfg,'whatsapp',$sign(array_replace($row,['received'=>9])),$now));
    cbCheck(str_contains(communicationsSummary($db,$now),'whatsapp: received 8 / answered 5 / missed 2'),'Owner reporting');
    cbCheck((int)$db->query("SELECT COUNT(*) FROM growth_audit WHERE action IN ('comm_bridge_whatsapp','comm_bridge_sms','comm_bridge_calls')")->fetchColumn()>=3,'Sanitized audit');
    growthCommand($db,'/pause',$cfg);cbBlocked(static fn()=>commBridgeImport($db,$cfg,'whatsapp',$raw,$now));
    $db->rollBack();echo "Synthetic signed bridge and immutable daily counts: PASS\n";
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();fwrite(STDERR,'Bridge test failed: '.$e->getMessage()."\n");exit(1);}
