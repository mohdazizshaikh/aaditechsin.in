<?php
declare(strict_types=1);
require dirname(__DIR__).'/public_html/api/instagram-core.php';
require dirname(__DIR__).'/secure-setup/maintenance/instagram-send.php';
function igCheck(bool $ok,string $m):void{if(!$ok)throw new RuntimeException($m);}
$cfg=['growth'=>['enabled'=>true],'creative'=>['enabled'=>true],'media'=>['enabled'=>true],'social'=>['enabled'=>true],'media_upload'=>['enabled'=>true,'instagram'=>['enabled'=>true,'page_id'=>'123','account_id'=>'456','access_token'=>str_repeat('a',40),'api_version'=>'v27.0']]];
$p=igTarget($cfg);igCheck(igTarget([])===null&&$p['id']==='456','Default OFF');
$bytes=pack('N',12).'ftypmp42';$hash=hash('sha256',$bytes);
$row=['page_id'=>'123','target_id'=>'456','caption'=>'Aaditechs illustration.','video_hash'=>$hash,'byte_count'=>strlen($bytes),'container_id'=>'789'];
$calls=[];$mock=static function($p,$kind,$path,$data=[],$binary='')use(&$calls,$bytes){
    $calls[]=$kind.':'.$path;
    if($path==='me')return ['http'=>200,'json'=>['id'=>'123','instagram_business_account'=>['id'=>'456']]];
    if($path==='456/media'){igCheck($data['upload_type']==='resumable'&&$data['share_to_feed']==='false'&&!isset($data['video_url']),'Direct upload');return ['http'=>200,'json'=>['id'=>'789','uri'=>'https://evil.example/']];}
    if($kind==='binary'){igCheck($binary===$bytes,'Exact bytes');return ['http'=>200,'json'=>['success'=>true]];}
    if($kind==='get')return ['http'=>200,'json'=>['id'=>'789','status_code'=>'FINISHED']];
    igCheck($data['creation_id']==='789','Bound container');return ['http'=>200,'json'=>['id'=>'999']];
};
$remembered=[];igCheck(igUpload($p,$row,$bytes,$mock,static function($id)use(&$remembered){$remembered[]=$id;})===igResult('processing','789')&&$calls===['get:me','post:456/media','binary:789'],'Separate upload no publish');
igCheck($remembered===['789'],'Durable receipt callback');
$calls=[];igCheck(igUpload($p,$row,$bytes,$mock,static function(){throw new RuntimeException('DB failure');})['status']==='unknown'&&$calls===['get:me','post:456/media'],'Receipt failure blocks binary');
$calls=[];igCheck(igPublish($p,$row,$mock)===igResult('accepted','999')&&$calls===['get:me','get:789','post:456/media_publish'],'Publish finished container');
$calls=[];$wait=static function($p,$kind,$path,$data=[],$binary='')use(&$calls){$calls[]=$kind;return $path==='me'?['http'=>200,'json'=>['id'=>'123','instagram_business_account'=>['id'=>'456']]]:['http'=>200,'json'=>['id'=>'789','status_code'=>'IN_PROGRESS']];};
igCheck(igPublish($p,$row,$wait)['status']==='unknown'&&$calls===['get','get'],'Not finished cannot publish');
$calls=[];igCheck(igUpload($p,$row,'tampered',$mock)['status']==='failed'&&$calls===[],'Hash gate');
if(($argv[1]??'')!=='--database'){echo "Instagram direct upload and processing gates (mock): PASS\n";exit;}
if(getenv('AADITECHS_DISPOSABLE_TEST')!=='yes'||getenv('CI_DB_NAME')!=='aaditechs_ci'||getenv('CI_DB_USER')!=='aaditechs_ci'||getenv('CI_DB_HOST')!=='127.0.0.1')exit(2);
$db=new PDO('mysql:host=127.0.0.1;port='.(int)getenv('CI_DB_PORT').';dbname=aaditechs_ci;charset=utf8mb4','aaditechs_ci',getenv('CI_DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
$db->beginTransaction();
try{
    growthCommand($db,'/resume',$cfg);$id=creativeCreate($db,'infrastructure','instagram','education');
    creativeImport($db,['version'=>1,'task_id'=>$id,'revision'=>1,'model'=>'local-test:1b','caption'=>'Plan IT requirements.','visual_brief'=>'Illustration','video_brief'=>'Planning','review_notes'=>'Review']);
    growthCommand($db,"/approve $id 1",$cfg);
    $raw=json_encode(['version'=>1,'task_id'=>$id,'revision'=>1,'channel'=>'instagram','renderer'=>'template','width'=>1080,'height'=>1920,'image_sha256'=>str_repeat('a',64),'video_sha256'=>$hash]);
    $manifest=mediaRegister($db,$raw)['manifest_sha256'];
    try{igRegister($db,$cfg,$id,1,$manifest,$hash,strlen($bytes));throw new LogicException('Unapproved manifest accepted');}catch(InvalidArgumentException $e){}
    growthCommand($db,"/approvemedia $id 1 $manifest",$cfg);
    $up=igRegister($db,$cfg,$id,1,$manifest,$hash,strlen($bytes));
    igCheck(str_contains(growthCommand($db,"/approveig $up $manifest 999",$cfg),'mismatch'),'Wrong destination');
    igCheck(str_contains(growthCommand($db,"/approveig $up $manifest 456",$cfg),'approved'),'Exact public Reel approval');
    $r=igClaim($db,$cfg,$up,'upload');igRemember($db,$r,'789');igFinish($db,$r,'upload',igResult('processing','789'));
    $r=igClaim($db,$cfg,$up,'publish');igFinish($db,$r,'publish',igResult('unknown',null,'publish_unverified'));
    try{igClaim($db,$cfg,$up,'publish');throw new LogicException('Retry accepted');}catch(RuntimeException $e){}
    igCheck(str_contains(growthCommand($db,'/publications',$cfg),'instagram unknown: 1'),'Unified ledger');
    $db->rollBack();echo "Synthetic Instagram staged approval and no retry: PASS\n";
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();fwrite(STDERR,'Instagram test failed: '.$e->getMessage()."\n");exit(1);}
