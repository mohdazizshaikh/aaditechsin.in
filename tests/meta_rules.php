<?php
declare(strict_types=1);
require dirname(__DIR__).'/public_html/api/meta-core.php';
require dirname(__DIR__).'/secure-setup/maintenance/meta-send.php';
function metaCheck(bool $ok,string $msg):void{if(!$ok)throw new RuntimeException($msg);}
$cfg=['growth'=>['enabled'=>true],'creative'=>['enabled'=>true],'social'=>['enabled'=>true,'meta'=>['enabled'=>true,'facebook'=>['enabled'=>true,'account_id'=>'1234','access_token'=>str_repeat('a',40),'api_version'=>'v27.0'],'threads'=>['enabled'=>true,'account_id'=>'5678','access_token'=>str_repeat('b',40),'api_version'=>'v1.0']]]];
$p=metaTarget($cfg,'facebook');$t=metaTarget($cfg,'threads');
metaCheck(metaTarget([],'facebook')===null&&metaTarget($cfg,'instagram')===null&&$p['id']==='1234','Default OFF and exact providers');
$row=['provider'=>'facebook','target_id'=>'1234','caption'=>'Plan your IT requirements.'];
$calls=[];
$fake=static function($p,$method,$path,$data)use(&$calls){$calls[]=$path;return ['http'=>200,'json'=>['id'=>$method==='GET'?'1234':'1234_99']];};
metaCheck(metaSend($p,$row,$fake)['status']==='accepted'&&$calls===['me','1234/feed'],'Facebook receipt');
$calls=[];$wrong=static function($p,$method,$path,$data)use(&$calls){$calls[]=$path;return ['http'=>200,'json'=>['id'=>'9999']];};
metaCheck(metaSend($p,$row,$wrong)['error']==='identity_unverified'&&$calls===['me'],'Identity blocks POST');
$calls=[];$threads=static function($p,$method,$path,$data)use(&$calls){$calls[]=$path;if($method==='GET')return ['http'=>200,'json'=>['id'=>'5678']];if($path==='5678/threads'){metaCheck($data['auto_publish_text']==='false','No auto publish');return ['http'=>200,'json'=>['id'=>'90']];}metaCheck($data['creation_id']==='90','Exact container');return ['http'=>200,'json'=>['id'=>'91']];};
metaCheck(metaSend($t,['provider'=>'threads','target_id'=>'5678','caption'=>'Plan a review.'],$threads)['receipt']==='91'&&$calls===['me','5678/threads','5678/threads_publish'],'Threads two-stage receipt');
metaCheck(metaResponse(['http'=>500,'json'=>['error'=>[]]],'/^[0-9]+$/D')['status']==='unknown','Ambiguous not retryable');
metaCheck(metaResponse(['http'=>429,'json'=>['error'=>[]]],'/^[0-9]+$/D')['status']==='failed','Explicit rejection');
if(($argv[1]??'')!=='--database'){echo "Meta text adapter mock transport: PASS\n";exit;}
if(getenv('AADITECHS_DISPOSABLE_TEST')!=='yes'||getenv('CI_DB_NAME')!=='aaditechs_ci'||getenv('CI_DB_USER')!=='aaditechs_ci'||getenv('CI_DB_HOST')!=='127.0.0.1')exit(2);
$db=new PDO('mysql:host=127.0.0.1;port='.(int)getenv('CI_DB_PORT').';dbname=aaditechs_ci;charset=utf8mb4','aaditechs_ci',getenv('CI_DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
$db->beginTransaction();
try{
    growthCommand($db,'/resume',$cfg);
    $id=creativeCreate($db,'infrastructure','facebook','education');
    creativeImport($db,['version'=>1,'task_id'=>$id,'revision'=>1,'model'=>'local-test:1b','caption'=>'Plan your infrastructure.','visual_brief'=>'Illustration','video_brief'=>'Planning','review_notes'=>'Review']);
    $when=(new DateTimeImmutable('now',new DateTimeZone('UTC')))->modify('+1 day')->format('Y-m-d\TH:i\Z');
    try{metaSchedule($db,$cfg,'facebook',$id,1,'1234',$when);throw new RuntimeException('Unapproved accepted');}catch(InvalidArgumentException $e){}
    growthCommand($db,"/approve $id 1",$cfg);
    try{metaSchedule($db,$cfg,'threads',$id,1,'5678',$when);throw new RuntimeException('Wrong channel accepted');}catch(InvalidArgumentException $e){}
    $pub=metaSchedule($db,$cfg,'facebook',$id,1,'1234',$when);
    try{metaSchedule($db,$cfg,'facebook',$id,1,'1234',$when);throw new RuntimeException('Duplicate accepted');}catch(InvalidArgumentException $e){}
    metaCheck(metaClaim($db,$cfg,'facebook')===null,'Not due');
    $db->prepare('UPDATE growth_meta_publications SET due_at=UTC_TIMESTAMP() WHERE id=?')->execute([$pub]);
    growthCommand($db,'/pause',$cfg);
    try{metaClaim($db,$cfg,'facebook');throw new LogicException('Pause bypass');}catch(RuntimeException $e){metaCheck($e->getMessage()==='Growth paused','Pause gate');}
    growthCommand($db,'/resume',$cfg);
    $r=metaClaim($db,$cfg,'facebook');metaCheck((int)$r['id']===$pub,'Claim approved');
    metaFinish($db,$r,metaResult('unknown',null,'ambiguous_response'));
    metaCheck(metaClaim($db,$cfg,'facebook')===null,'Unknown never requeued');
    metaCheck(str_contains(growthCommand($db,'/meta',$cfg),'unknown'),'Owner ledger');
    $db->rollBack();echo "Meta synthetic scheduling and ambiguous state: PASS\n";
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();fwrite(STDERR,'Meta test failed: '.$e->getMessage()."\n");exit(1);}
