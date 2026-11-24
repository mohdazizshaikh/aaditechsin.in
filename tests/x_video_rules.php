<?php
declare(strict_types=1);
require dirname(__DIR__).'/public_html/api/x-video-core.php';
require dirname(__DIR__).'/secure-setup/maintenance/x-video-send.php';
function xvCheck(bool $ok,string $m):void{if(!$ok)throw new RuntimeException($m);}
$cfg=['growth'=>['enabled'=>true],'creative'=>['enabled'=>true],'media'=>['enabled'=>true],'social'=>['enabled'=>true,'network'=>['enabled'=>true,'x'=>['enabled'=>true,'account_id'=>'456','access_token'=>str_repeat('a',40)]]],'media_upload'=>['enabled'=>true,'x_video_enabled'=>true,'x_enabled'=>true]];
$p=xvTarget($cfg);$id='789';$bytes=str_repeat('a',4194304).str_repeat('b',32);$hash=hash('sha256',$bytes);
xvCheck(xvTarget([])===null&&$p['id']==='456','OFF and target');
$row=['target_id'=>'456','video_hash'=>$hash,'byte_count'=>strlen($bytes),'caption'=>'Plan IT requirements.','video_id'=>$id];$seen=[];$chunks=[];$calls=[];
$mock=static function($p,$method,$path,$data)use(&$seen,&$chunks,&$calls,$id){
    $calls[]=$path;
    if($path==='2/users/me')return ['http'=>200,'json'=>['data'=>['id'=>'456']]];
    if($path==='2/media/upload/initialize'){xvCheck($data['media_category']==='tweet_video'&&$data['total_bytes']===4194336,'Init profile');return ['http'=>200,'json'=>['data'=>['id'=>$id,'expires_after_secs'=>600]]];}
    if(str_ends_with($path,'/append')){xvCheck($seen===[$id]&&$data['segment_index']===count($chunks),'Receipt and ordering');$chunks[]=base64_decode($data['media'],true);return ['http'=>204];}
    if(str_ends_with($path,'/finalize'))return ['http'=>200,'json'=>['data'=>['id'=>$id,'processing_info'=>['state'=>'pending']]]];
    if($path==='2/media/upload')return ['http'=>200,'json'=>['data'=>['id'=>$id,'expires_after_secs'=>600,'processing_info'=>['state'=>'succeeded']]]];
    xvCheck($data['media']['media_ids']===[$id],'Exact attachment');return ['http'=>201,'json'=>['data'=>['id'=>'999']]];
};
$remember=static function($id)use(&$seen){$seen[]=$id;};
xvCheck(xvUpload($p,$row,$bytes,$mock,$remember)['status']==='processing'&&implode('',$chunks)===$bytes&&!in_array('2/tweets',$calls),'Separate upload');
xvCheck(xvPublish($p,$row,$mock)['status']==='accepted','Ready publish');
$notReady=static function($p,$method,$path,$data){if($path==='2/users/me')return ['http'=>200,'json'=>['data'=>['id'=>'456']]];if($path==='2/media/upload')return ['http'=>200,'json'=>['data'=>['id'=>'789','expires_after_secs'=>600,'processing_info'=>['state'=>'pending']]]];throw new LogicException('Must not publish');};
xvCheck(!xvReady($p,$row,$notReady)&&xvPublish($p,$row,$notReady)['status']==='unknown','Processing cannot publish');
$calls=[];xvCheck(xvUpload($p,$row,$bytes,$mock,static function(){throw new RuntimeException('DB failure');})['status']==='unknown'&&$calls===['2/users/me','2/media/upload/initialize'],'Receipt failure blocks append');
if(($argv[1]??'')!=='--database'){echo "X video mock multipart: PASS\n";exit;}
if(getenv('AADITECHS_DISPOSABLE_TEST')!=='yes'||getenv('CI_DB_NAME')!=='aaditechs_ci'||getenv('CI_DB_USER')!=='aaditechs_ci'||getenv('CI_DB_HOST')!=='127.0.0.1')exit(2);
$db=new PDO('mysql:host=127.0.0.1;port='.(int)getenv('CI_DB_PORT').';dbname=aaditechs_ci;charset=utf8mb4','aaditechs_ci',getenv('CI_DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
$db->beginTransaction();
try{
    growthCommand($db,'/resume',$cfg);$task=creativeCreate($db,'infrastructure','x','education');
    creativeImport($db,['version'=>1,'task_id'=>$task,'revision'=>1,'model'=>'local-test:1b','caption'=>'Plan IT requirements.','visual_brief'=>'Illustration','video_brief'=>'Planning','review_notes'=>'Review']);
    growthCommand($db,"/approve $task 1",$cfg);
    $raw=json_encode(['version'=>1,'task_id'=>$task,'revision'=>1,'channel'=>'x','renderer'=>'template','width'=>1200,'height'=>628,'image_sha256'=>str_repeat('a',64),'video_sha256'=>$hash]);
    $manifest=mediaRegister($db,$raw)['manifest_sha256'];growthCommand($db,"/approvemedia $task 1 $manifest",$cfg);
    $up=xvRegister($db,$cfg,$task,1,$manifest,$hash,strlen($bytes));
    xvCheck(str_contains(growthCommand($db,"/approvexvideo $up $manifest 456",$cfg),'approved'),'Exact approval');
    try{nmRegister($db,$cfg,$task,1,$manifest,'x',str_repeat('a',64),100);throw new LogicException('Image duplicate');}catch(InvalidArgumentException $e){}
    $r=xvClaim($db,$cfg,$up,'upload');xvRemember($db,$r,$id);xvFinish($db,$r,'upload',networkResult('processing',$id));
    $r=xvClaim($db,$cfg,$up,'publish');xvCheck($r['video_id']===$id,'Persisted ID');xvFinish($db,$r,'publish',networkResult('unknown',null,'publish_unverified'));
    try{xvClaim($db,$cfg,$up,'publish');throw new LogicException('Retry allowed');}catch(RuntimeException $e){}
    xvCheck(str_contains(growthCommand($db,'/publications',$cfg),'x unknown: 1'),'Unified ledger');
    xvCheck(str_contains(growthCommand($db,'/mediarecovery',$cfg),$id),'Recovery report');
    $db->rollBack();echo "Synthetic X receipt and reservation: PASS\n";
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();fwrite(STDERR,'X video test failed: '.$e->getMessage()."\n");exit(1);}
