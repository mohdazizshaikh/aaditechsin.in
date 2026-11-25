<?php
declare(strict_types=1);
require dirname(__DIR__).'/public_html/api/upload-core.php';
require dirname(__DIR__).'/secure-setup/maintenance/upload-assets.php';
require dirname(__DIR__).'/secure-setup/maintenance/youtube-send.php';
function upCheck(bool $ok,string $m):void{if(!$ok)throw new RuntimeException($m);}
$channel='UC'.str_repeat('a',22);
$cfg=['growth'=>['enabled'=>true],'creative'=>['enabled'=>true],'media'=>['enabled'=>true],'social'=>['enabled'=>true],'media_upload'=>['enabled'=>true,'youtube'=>['enabled'=>true,'channel_id'=>$channel,'access_token'=>str_repeat('a',40)]]];
upCheck(uploadTarget([])===null&&uploadTarget($cfg)['id']===$channel,'OFF and target');
$bytes=pack('N',12).'ftypmp42';$hash=hash('sha256',$bytes);
$meta=['title'=>'Technical illustration','description'=>'Aaditechs service illustration','category_id'=>'28','visibility'=>'private','made_for_kids'=>false,'synthetic_content'=>true];
$row=$meta+['target_id'=>$channel,'video_hash'=>$hash,'byte_count'=>strlen($bytes)];
$tmp=sys_get_temp_dir().'/aaditechs-upload-'.bin2hex(random_bytes(8));mkdir($tmp,0700);
try{
    $root=uploadRoot($tmp,dirname(__DIR__).'/public_html');uploadStore($root,$hash,$bytes);
    upCheck(uploadRead($root.'/'.$hash.'.mp4',$hash)===$bytes,'Exact bytes');
    file_put_contents($root.'/'.$hash.'.mp4','tampered');
    try{uploadRead($root.'/'.$hash.'.mp4',$hash);throw new LogicException('Accepted tampering');}catch(RuntimeException $e){}
}finally{unlink($tmp.'/'.$hash.'.mp4');rmdir($tmp);}
$p=uploadTarget($cfg);$calls=[];
$fake=static function($p,$method,$url,$body='',$headers=[])use(&$calls,$channel,$bytes){
    $calls[]=$method;
    if($method==='GET')return ['http'=>200,'json'=>['items'=>[['id'=>$channel]]]];
    if($method==='POST'){upCheck(json_decode($body,true)['status']['privacyStatus']==='private','Visibility bound');return ['http'=>200,'location'=>'https://www.googleapis.com/upload/youtube/v3/videos?upload_id=mock'];}
    upCheck($body===$bytes,'Exact upload bytes');return ['http'=>200,'json'=>['id'=>'abcdefghijk','snippet'=>['channelId'=>$channel],'status'=>['privacyStatus'=>'private']]];
};
upCheck(youtubeSend($p,$row,$bytes,$fake)['status']==='accepted'&&$calls===['GET','POST','PUT'],'Mock upload');
$calls=[];$bad=static function($p,$method,$url,$body='',$headers=[])use(&$calls,$channel){$calls[]=$method;return $method==='GET'?['http'=>200,'json'=>['items'=>[['id'=>$channel]]]]:['http'=>200,'location'=>'https://evil.example/upload'];};
upCheck(youtubeSend($p,$row,$bytes,$bad)['status']==='unknown'&&$calls===['GET','POST'],'Foreign upload URL refused');
if(($argv[1]??'')!=='--database'){echo "Private asset integrity and YouTube mock upload: PASS\n";exit;}
if(getenv('AADITECHS_DISPOSABLE_TEST')!=='yes'||getenv('CI_DB_NAME')!=='aaditechs_ci'||getenv('CI_DB_USER')!=='aaditechs_ci'||getenv('CI_DB_HOST')!=='127.0.0.1')exit(2);
$db=new PDO('mysql:host=127.0.0.1;port='.(int)getenv('CI_DB_PORT').';dbname=aaditechs_ci;charset=utf8mb4','aaditechs_ci',getenv('CI_DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
$db->beginTransaction();
try{
    growthCommand($db,'/resume',$cfg);$id=creativeCreate($db,'infrastructure','youtube','education');
    creativeImport($db,['version'=>1,'task_id'=>$id,'revision'=>1,'model'=>'local-test:1b','caption'=>'Plan IT requirements.','visual_brief'=>'Illustration','video_brief'=>'Planning','review_notes'=>'Review']);
    growthCommand($db,"/approve $id 1",$cfg);
    $raw=json_encode(['version'=>1,'task_id'=>$id,'revision'=>1,'channel'=>'youtube','renderer'=>'template','width'=>1080,'height'=>1920,'image_sha256'=>str_repeat('a',64),'video_sha256'=>$hash]);
    $manifest=mediaRegister($db,$raw)['manifest_sha256'];
    try{uploadRegister($db,$cfg,$id,1,$manifest,$hash,strlen($bytes),$meta);throw new LogicException('Unapproved manifest');}catch(InvalidArgumentException $e){}
    growthCommand($db,"/approvemedia $id 1 $manifest",$cfg);
    $up=uploadRegister($db,$cfg,$id,1,$manifest,$hash,strlen($bytes),$meta);
    upCheck(str_contains(growthCommand($db,"/approveupload $up $manifest public $channel",$cfg),'mismatch'),'Visibility mismatch');
    upCheck(str_contains(growthCommand($db,"/approveupload $up $manifest private $channel",$cfg),'approved'),'Exact approval');
    $r=uploadClaim($db,$cfg,$up);uploadFinish($db,$r,youtubeResult('unknown',null,'ambiguous_response'));
    try{uploadClaim($db,$cfg,$up);throw new LogicException('Retry allowed');}catch(RuntimeException $e){}
    $db->rollBack();echo "Synthetic upload approval and no retry: PASS\n";
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();fwrite(STDERR,'Upload test failed: '.$e->getMessage()."\n");exit(1);}
