<?php
declare(strict_types=1);
require dirname(__DIR__).'/public_html/api/linkedin-video-core.php';
require dirname(__DIR__).'/secure-setup/maintenance/linkedin-video-send.php';
function lvCheck(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);}
$cfg=['growth'=>['enabled'=>true],'creative'=>['enabled'=>true],'media'=>['enabled'=>true],'social'=>['enabled'=>true,'network'=>['enabled'=>true,'linkedin'=>['enabled'=>true,'account_id'=>'123','access_token'=>str_repeat('a',40),'api_version'=>'202610']]],'media_upload'=>['enabled'=>true,'linkedin_video_enabled'=>true,'linkedin_enabled'=>true]];
$p=lvTarget($cfg);$id='urn:li:video:abc';$url='https://www.linkedin.com/dms-uploads/abc/uploadedVideo?sau=mock';$bytes=str_repeat('a',4194304).str_repeat('b',32);$hash=hash('sha256',$bytes);
lvCheck(lvTarget([])===null&&lvUrl($url,$id)&&!lvUrl('https://evil.example/dms-uploads/abc/uploadedVideo?sau=mock',$id),'OFF and URL gate');
$ins=[['firstByte'=>0,'lastByte'=>4194303,'uploadUrl'=>$url],['firstByte'=>4194304,'lastByte'=>8388607,'uploadUrl'=>$url]];
lvCheck(lvParts($ins,strlen($bytes),$id)[1]['length']===32,'Final part');
try{lvParts([['firstByte'=>1,'lastByte'=>4194303,'uploadUrl'=>$url]],strlen($bytes),$id);throw new LogicException('Gap accepted');}catch(RuntimeException $e){}
$row=['target_id'=>'123','video_hash'=>$hash,'byte_count'=>strlen($bytes),'caption'=>'Plan IT requirements.','video_id'=>$id];$seen=[];$chunks=[];$calls=[];
$mock=static function($p,$method,$path,$data)use(&$calls,$id,$ins){
    $calls[]=$path;
    if($path==='rest/organizationAcls')return ['http'=>200,'json'=>['elements'=>[['role'=>'ADMINISTRATOR','state'=>'APPROVED','organizationTarget'=>'urn:li:organization:123']]]];
    if($path==='rest/videos?action=initializeUpload')return ['http'=>200,'json'=>['value'=>['video'=>$id,'uploadUrlsExpireAt'=>(time()+600)*1000,'uploadToken'=>'','uploadInstructions'=>$ins]]];
    if($path==='rest/videos?action=finalizeUpload'){lvCheck($data['finalizeUploadRequest']['uploadedPartIds']===['tag1','tag2'],'Ordered ETags');return ['http'=>200];}
    if($path==='rest/videos/'.rawurlencode($id))return ['http'=>200,'json'=>['id'=>$id,'owner'=>'urn:li:organization:123','status'=>'AVAILABLE']];
    lvCheck($data['content']['media']['id']===$id,'Exact attachment');return ['http'=>201,'receipt'=>'urn:li:share:99'];
};
$remember=static function($id)use(&$seen){$seen[]=$id;};
$binary=static function($u,$i,$b)use(&$chunks,&$seen,$id){lvCheck($seen===[$id],'Persist before bytes');$chunks[]=$b;return ['http'=>200,'etag'=>'tag'.count($chunks)];};
lvCheck(lvUpload($p,$row,$bytes,$mock,$binary,$remember)['status']==='processing'&&implode('',$chunks)===$bytes&&!in_array('rest/posts',$calls),'Separate upload');
lvCheck(lvPublish($p,$row,$mock)['status']==='accepted','Ready publish');
$calls=[];$sent=false;lvCheck(lvUpload($p,$row,$bytes,$mock,static function()use(&$sent){$sent=true;return ['http'=>200];},static function(){throw new RuntimeException('DB failure');})['status']==='unknown'&&!$sent&&!in_array('rest/posts',$calls),'Receipt failure blocks binary');
if(($argv[1]??'')!=='--database'){echo "LinkedIn video mock multipart: PASS\n";exit;}
if(getenv('AADITECHS_DISPOSABLE_TEST')!=='yes'||getenv('CI_DB_NAME')!=='aaditechs_ci'||getenv('CI_DB_USER')!=='aaditechs_ci'||getenv('CI_DB_HOST')!=='127.0.0.1')exit(2);
$db=new PDO('mysql:host=127.0.0.1;port='.(int)getenv('CI_DB_PORT').';dbname=aaditechs_ci;charset=utf8mb4','aaditechs_ci',getenv('CI_DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
$db->beginTransaction();
try{
    growthCommand($db,'/resume',$cfg);$task=creativeCreate($db,'infrastructure','linkedin','education');
    creativeImport($db,['version'=>1,'task_id'=>$task,'revision'=>1,'model'=>'local-test:1b','caption'=>'Plan IT requirements.','visual_brief'=>'Illustration','video_brief'=>'Planning','review_notes'=>'Review']);
    growthCommand($db,"/approve $task 1",$cfg);
    $raw=json_encode(['version'=>1,'task_id'=>$task,'revision'=>1,'channel'=>'linkedin','renderer'=>'template','width'=>1200,'height'=>628,'image_sha256'=>str_repeat('a',64),'video_sha256'=>$hash]);
    $manifest=mediaRegister($db,$raw)['manifest_sha256'];growthCommand($db,"/approvemedia $task 1 $manifest",$cfg);
    $up=lvRegister($db,$cfg,$task,1,$manifest,$hash,strlen($bytes));
    lvCheck(str_contains(growthCommand($db,"/approvelivideo $up $manifest 123",$cfg),'approved'),'Exact approval');
    try{nmRegister($db,$cfg,$task,1,$manifest,'linkedin',str_repeat('a',64),100);throw new LogicException('Image duplicate');}catch(InvalidArgumentException $e){}
    $r=lvClaim($db,$cfg,$up,'upload');lvRemember($db,$r,$id);lvFinish($db,$r,'upload',networkResult('processing',$id));
    $r=lvClaim($db,$cfg,$up,'publish');lvCheck($r['video_id']===$id,'Persisted ID');lvFinish($db,$r,'publish',networkResult('unknown',null,'publish_unverified'));
    try{lvClaim($db,$cfg,$up,'publish');throw new LogicException('Retry allowed');}catch(RuntimeException $e){}
    lvCheck(str_contains(growthCommand($db,'/publications',$cfg),'linkedin unknown: 1'),'Unified ledger');
    lvCheck(str_contains(growthCommand($db,'/mediarecovery',$cfg),$id),'Recovery report');
    $db->rollBack();echo "Synthetic LinkedIn receipt and reservation: PASS\n";
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();fwrite(STDERR,'LinkedIn video test failed: '.$e->getMessage()."\n");exit(1);}
