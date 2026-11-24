<?php
declare(strict_types=1);
require dirname(__DIR__).'/public_html/api/telegram-media-core.php';
require dirname(__DIR__).'/secure-setup/maintenance/telegram-media-send.php';
function tgCheck(bool $ok,string $m):void{if(!$ok)throw new RuntimeException($m);}
$cfg=['growth'=>['enabled'=>true],'creative'=>['enabled'=>true],'media'=>['enabled'=>true],'social'=>['enabled'=>true,'telegram_channel'=>['enabled'=>true,'chat_id'=>'-1001234567890','bot_token'=>'123456:'.str_repeat('a',40)]],'media_upload'=>['enabled'=>true,'telegram_media_enabled'=>true]];
$p=tgMediaTarget($cfg);tgCheck(tgMediaTarget([])===null&&$p['id']==='-1001234567890','OFF and target');
$bytes=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+X2ZkAAAAASUVORK5CYII=');$hash=hash('sha256',$bytes);
foreach(['photo','video'] as $kind){
    $calls=[];$row=['target_id'=>$p['id'],'asset_kind'=>$kind,'asset_hash'=>$hash,'byte_count'=>strlen($bytes),'caption'=>'Plan IT requirements.'];
    $mock=static function($p,$method,$data)use(&$calls,$bytes,$kind){
        $calls[]=$method;
        $result=match($method){
            'getMe'=>['id'=>123,'is_bot'=>true],
            'getChat'=>['id'=>-1001234567890,'type'=>'channel'],
            'getChatMember'=>['user'=>['id'=>123],'status'=>'administrator','can_post_messages'=>true],
            default=>['message_id'=>789,'chat'=>['id'=>-1001234567890]]
        };
        if(str_starts_with($method,'send'))tgCheck($data[$kind] instanceof CURLStringFile&&$data[$kind]->data===$bytes&&$data['caption']==='Plan IT requirements.','Exact media');
        return ['http'=>200,'body'=>json_encode(['ok'=>true,'result'=>$result])];
    };
    tgCheck(tgMediaSend($p,$row,$bytes,$mock)['status']==='accepted'&&$calls===['getMe','getChat','getChatMember',$kind==='photo'?'sendPhoto':'sendVideo'],'Bound upload');
    tgCheck(tgMediaSend($p,array_replace($row,['target_id'=>'-1009999999999']),$bytes,$mock)['status']==='failed','Wrong target');
}
if(($argv[1]??'')!=='--database'){echo "Telegram media mock and PNG profile: PASS\n";exit;}
if(getenv('AADITECHS_DISPOSABLE_TEST')!=='yes'||getenv('CI_DB_NAME')!=='aaditechs_ci'||getenv('CI_DB_USER')!=='aaditechs_ci'||getenv('CI_DB_HOST')!=='127.0.0.1')exit(2);
$db=new PDO('mysql:host=127.0.0.1;port='.(int)getenv('CI_DB_PORT').';dbname=aaditechs_ci;charset=utf8mb4','aaditechs_ci',getenv('CI_DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
$db->beginTransaction();
try{
    growthCommand($db,'/resume',$cfg);$id=creativeCreate($db,'infrastructure','telegram','education');
    creativeImport($db,['version'=>1,'task_id'=>$id,'revision'=>1,'model'=>'local-test:1b','caption'=>'Plan IT requirements.','visual_brief'=>'Illustration','video_brief'=>'Planning','review_notes'=>'Review']);
    growthCommand($db,"/approve $id 1",$cfg);
    $raw=json_encode(['version'=>1,'task_id'=>$id,'revision'=>1,'channel'=>'telegram','renderer'=>'template','width'=>1080,'height'=>1080,'image_sha256'=>$hash,'video_sha256'=>str_repeat('b',64)]);
    $manifest=mediaRegister($db,$raw)['manifest_sha256'];
    try{tgMediaRegister($db,$cfg,$id,1,$manifest,'photo',$hash,strlen($bytes));throw new LogicException('Unapproved accepted');}catch(InvalidArgumentException $e){}
    growthCommand($db,"/approvemedia $id 1 $manifest",$cfg);
    $up=tgMediaRegister($db,$cfg,$id,1,$manifest,'photo',$hash,strlen($bytes));
    $when=(new DateTimeImmutable('now',new DateTimeZone('UTC')))->modify('+1 day')->format('Y-m-d\TH:i\Z');
    try{socialSchedule($db,$cfg,$id,1,'-1001234567890',$when);throw new LogicException('Text duplicate allowed');}catch(InvalidArgumentException $e){}
    tgCheck(str_contains(growthCommand($db,"/approvetgmedia $up $manifest video -1001234567890",$cfg),'mismatch'),'Kind mismatch');
    tgCheck(str_contains(growthCommand($db,"/approvetgmedia $up $manifest photo -1001234567890",$cfg),'approved'),'Exact approval');
    $r=tgMediaClaim($db,$cfg,$up);tgMediaFinish($db,$r,['status'=>'unknown','receipt'=>null,'error'=>'ambiguous_response']);
    try{tgMediaClaim($db,$cfg,$up);throw new LogicException('Retry accepted');}catch(RuntimeException $e){}
    tgCheck(str_contains(growthCommand($db,'/publications',$cfg),'telegram unknown: 1'),'Unified reporting');
    $db->rollBack();echo "Synthetic Telegram media reservation and approval: PASS\n";
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();fwrite(STDERR,'Telegram media test failed: '.$e->getMessage()."\n");exit(1);}
