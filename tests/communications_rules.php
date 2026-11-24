<?php
declare(strict_types=1);
require dirname(__DIR__).'/public_html/api/communications-core.php';
function cmCheck(bool $ok,string $msg):void{if(!$ok)throw new RuntimeException($msg);}
$now=new DateTimeImmutable('now',new DateTimeZone('UTC'));$day=$now->modify('-1 day')->format('Y-m-d');$r=['channel'=>'calls','day_utc'=>$day,'received'=>12,'answered'=>9,'missed'=>3];$cfg=['growth'=>['enabled'=>true],'communications'=>['enabled'=>true]];
cmCheck(!communicationsEnabled([])&&communicationsValidate($r,$now)===$r,'OFF and valid');
foreach([array_replace($r,['received'=>2]),array_replace($r,['day_utc'=>$now->format('Y-m-d')]),$r+['phone'=>'synthetic'],array_replace($r,['received'=>'12'])] as $bad){try{communicationsValidate($bad,$now);throw new LogicException('Invalid accepted');}catch(InvalidArgumentException $e){}}
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
    growthCommand($db,'/pause',$cfg);try{communicationsImport($db,$cfg,array_replace($r,['channel'=>'sms']),$now);throw new LogicException('Pause bypass');}catch(RuntimeException $e){}
    $db->rollBack();echo "Synthetic communication summary and pause: PASS\n";
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();fwrite(STDERR,'Communications test failed: '.$e->getMessage()."\n");exit(1);}
