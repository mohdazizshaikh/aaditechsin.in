<?php
declare(strict_types=1);
require dirname(__DIR__).'/public_html/api/facebook-media-core.php';
require dirname(__DIR__).'/secure-setup/maintenance/upload-assets.php';
require dirname(__DIR__).'/secure-setup/maintenance/facebook-media-send.php';
function fbCheck(bool $ok,string $m):void{if(!$ok)throw new RuntimeException($m);}
$cfg=['growth'=>['enabled'=>true],'creative'=>['enabled'=>true],'media'=>['enabled'=>true],'social'=>['enabled'=>true,'meta'=>['enabled'=>true,'facebook'=>['enabled'=>true,'account_id'=>'123','access_token'=>str_repeat('a',40),'api_version'=>'v27.0']]],'media_upload'=>['enabled'=>true,'facebook_enabled'=>true]];
$p=fbMediaTarget($cfg);fbCheck(fbMediaTarget([])===null&&$p['id']==='123','Default OFF');
$bytes=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+X2ZkAAAAASUVORK5CYII=');
$hash=hash('sha256',$bytes);
$tmp=sys_get_temp_dir().'/aaditechs-fb-'.bin2hex(random_bytes(8));mkdir($tmp,0700);
try{
    $root=uploadRoot($tmp,dirname(__DIR__).'/public_html');uploadPhotoStore($root,$hash,$bytes,1,1);
    fbCheck(uploadPhotoRead($root.'/'.$hash.'.png',$hash,1,1)===$bytes,'Photo bytes');
    try{uploadPhotoRead($root.'/'.$hash.'.png',$hash,2,1);throw new LogicException('Wrong profile accepted');}catch(RuntimeException $e){}
}finally{unlink($tmp.'/'.$hash.'.png');rmdir($tmp);}
foreach(['photo','video'] as $kind){
    $calls=[];$row=['target_id'=>'123','asset_kind'=>$kind,'asset_hash'=>$hash,'byte_count'=>strlen($bytes),'caption'=>'Plan IT requirements.'];
    $identity=static fn($p,$method,$path,$data)=>['http'=>200,'json'=>['id'=>'123']];
    $fake=static function($p,$k,$data,$b)use(&$calls,$bytes,$kind){$calls[]=$k;fbCheck($b===$bytes&&$data['published']==='true'&&isset($data[$kind==='photo'?'message':'description']),'Exact bytes and caption');return ['http'=>200,'json'=>['id'=>'789']];};
    fbCheck(fbMediaSend($p,$row,$bytes,$identity,$fake)['status']==='accepted'&&$calls===[$kind],'Mock '.$kind);
    fbCheck(fbMediaSend($p,$row,$bytes,$identity,static fn()=>['http'=>500,'json'=>[]])['status']==='unknown','Uncertain');
}
if(($argv[1]??'')!=='--database'){echo "Facebook media mock and PNG profile: PASS\n";exit;}
if(getenv('AADITECHS_DISPOSABLE_TEST')!=='yes'||getenv('CI_DB_NAME')!=='aaditechs_ci'||getenv('CI_DB_USER')!=='aaditechs_ci'||getenv('CI_DB_HOST')!=='127.0.0.1')exit(2);
$db=new PDO('mysql:host=127.0.0.1;port='.(int)getenv('CI_DB_PORT').';dbname=aaditechs_ci;charset=utf8mb4','aaditechs_ci',getenv('CI_DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
$db->beginTransaction();
try{
    growthCommand($db,'/resume',$cfg);$id=creativeCreate($db,'infrastructure','facebook','education');
    creativeImport($db,['version'=>1,'task_id'=>$id,'revision'=>1,'model'=>'local-test:1b','caption'=>'Plan IT requirements.','visual_brief'=>'Illustration','video_brief'=>'Planning','review_notes'=>'Review']);
    growthCommand($db,"/approve $id 1",$cfg);
    $raw=json_encode(['version'=>1,'task_id'=>$id,'revision'=>1,'channel'=>'facebook','renderer'=>'template','width'=>1200,'height'=>628,'image_sha256'=>$hash,'video_sha256'=>str_repeat('b',64)]);
    $manifest=mediaRegister($db,$raw)['manifest_sha256'];
    try{fbMediaRegister($db,$cfg,$id,1,$manifest,'photo',$hash,strlen($bytes));throw new LogicException('Unapproved accepted');}catch(InvalidArgumentException $e){}
    growthCommand($db,"/approvemedia $id 1 $manifest",$cfg);
    $up=fbMediaRegister($db,$cfg,$id,1,$manifest,'photo',$hash,strlen($bytes));
    $when=(new DateTimeImmutable('now',new DateTimeZone('UTC')))->modify('+1 day')->format('Y-m-d\TH:i\Z');
    try{metaSchedule($db,$cfg,'facebook',$id,1,'123',$when);throw new LogicException('Text duplicate allowed');}catch(InvalidArgumentException $e){}
    fbCheck(str_contains(growthCommand($db,"/approvefbmedia $up $manifest video 123",$cfg),'mismatch'),'Kind mismatch');
    fbCheck(str_contains(growthCommand($db,"/approvefbmedia $up $manifest photo 123",$cfg),'approved'),'Exact approval');
    $r=fbMediaClaim($db,$cfg,$up);fbMediaFinish($db,$r,metaResult('unknown',null,'ambiguous_response'));
    try{fbMediaClaim($db,$cfg,$up);throw new LogicException('Retry accepted');}catch(RuntimeException $e){}
    fbCheck(str_contains(growthCommand($db,'/publications',$cfg),'facebook unknown: 1'),'Unified reporting');
    $db->rollBack();echo "Synthetic Facebook media reservation and approval: PASS\n";
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();fwrite(STDERR,'Facebook media test failed: '.$e->getMessage()."\n");exit(1);}
