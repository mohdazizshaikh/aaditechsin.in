<?php
declare(strict_types=1);
require dirname(__DIR__).'/public_html/api/carousel-core.php';
require dirname(__DIR__).'/public_html/api/media-range.php';
require dirname(__DIR__).'/secure-setup/maintenance/carousel-send.php';
require dirname(__DIR__).'/secure-setup/maintenance/media-inbox-core.php';
function caCheck(bool $ok,string $m):void{if(!$ok)throw new RuntimeException($m);}
$cfg=['public_base_url'=>'https://staging.example.com','growth'=>['enabled'=>true],'creative'=>['enabled'=>true],'media'=>['enabled'=>true],'social'=>['enabled'=>true,'meta'=>['enabled'=>true,'threads'=>['enabled'=>true,'account_id'=>'123','access_token'=>str_repeat('a',40),'api_version'=>'v1.0']]],'media_upload'=>['enabled'=>true,'fetch_enabled'=>true,'fetch_log_redaction_verified'=>true,'threads_images_enabled'=>true,'threads_carousel_enabled'=>true,'instagram_images_enabled'=>true,'instagram_carousel_enabled'=>true,'instagram'=>['enabled'=>true,'page_id'=>'456','account_id'=>'789','access_token'=>str_repeat('b',40),'api_version'=>'v27.0']]];
$items=[['asset_hash'=>str_repeat('a',64),'byte_count'=>100],['asset_hash'=>str_repeat('b',64),'byte_count'=>200]];
caCheck(caDigest($items)!==caDigest(array_reverse($items))&&caTarget([],'threads')===null,'Order and OFF');
try{caDigest([$items[0],$items[0]]);throw new LogicException('Duplicate accepted');}catch(InvalidArgumentException $e){}
caCheck(mediaRange('video',100,'bytes=10-19','GET')===[10,19]&&mediaRange('video',100,'bytes=90-','GET')===[90,99]&&mediaRange('video',100,'bytes=100-','GET')===false&&mediaRange('video',100,'bytes=0-1,3-4','GET')===false&&mediaRange('image',100,'bytes=0-1','GET')===false,'Range policy');
foreach(['threads','instagram'] as $provider){
    $p=caTarget($cfg,$provider);$row=['provider'=>$provider,'target_id'=>$p['id'],'page_id'=>$p['page'],'caption'=>'Plan IT requirements.','container_id'=>'999','items'=>[['position_no'=>1,'fetch_token'=>str_repeat('a',64),'container_id'=>'111'],['position_no'=>2,'fetch_token'=>str_repeat('b',64),'container_id'=>'222']]];$calls=[];$seen=[];
    $mock=static function($p,$method,$path,$data)use(&$calls,&$seen){
        if($path==='me')return ['http'=>200,'json'=>$p['provider']==='threads'?['id'=>'123']:['id'=>'456','instagram_business_account'=>['id'=>'789']]];
        if($method==='GET')return ['http'=>200,'json'=>['id'=>$path,'status'=>'FINISHED','status_code'=>'FINISHED']];
        if(($data['media_type']??null)==='CAROUSEL'){caCheck($data['children']==='111,222','Ordered children');return ['http'=>200,'json'=>['id'=>'999']];}
        if(str_ends_with($path,'_publish'))return ['http'=>200,'json'=>['id'=>'888']];
        caCheck(($data['is_carousel_item']??null)==='true'&&str_contains($data['image_url'],'?c='),'Child only');$calls[]=$path;return ['http'=>200,'json'=>['id'=>count($calls)===1?'111':'222']];
    };
    caCheck(caChildren($p,$row,$mock,static function($pos,$id)use(&$seen){$seen[]=$id;})&&$seen===['111','222'],'Child persistence');
    caCheck(caParent($p,$row,$mock)==='999'&&miPublish($p,$row,$mock)==='888','Separate parent and publish');
}
if(($argv[1]??'')!=='--database'){echo "Carousel mock and range rules: PASS\n";exit;}
if(getenv('AADITECHS_DISPOSABLE_TEST')!=='yes'||getenv('CI_DB_NAME')!=='aaditechs_ci'||getenv('CI_DB_USER')!=='aaditechs_ci'||getenv('CI_DB_HOST')!=='127.0.0.1')exit(2);
$db=new PDO('mysql:host=127.0.0.1;port='.(int)getenv('CI_DB_PORT').';dbname=aaditechs_ci;charset=utf8mb4','aaditechs_ci',getenv('CI_DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
$root=sys_get_temp_dir().'/aaditechs-ca-'.bin2hex(random_bytes(8));mkdir($root,0700);$inbox=$root.'-inbox';mkdir($inbox,0700);$cfg['media_upload']['private_root']=$root;
$items=[];$files=[];
foreach([0,255] as $red){$im=imagecreatetruecolor(1080,1080);imagefill($im,0,0,imagecolorallocate($im,$red,0,0));ob_start();imagejpeg($im,null,85);$jpeg=ob_get_clean();imagedestroy($im);$hash=hash('sha256',$jpeg);miStore($root,$hash,$jpeg,1080,1080);$items[]=['asset_hash'=>$hash,'byte_count'=>strlen($jpeg)];$files[]=$root.'/'.$hash.'.jpg';}
$im=imagecreatetruecolor(1080,1080);ob_start();imagepng($im);$png=ob_get_clean();imagedestroy($im);$mp4=pack('N',32).'ftypmp42'.str_repeat('v',20);$ih=hash('sha256',$png);$vh=hash('sha256',$mp4);file_put_contents($inbox.'/'.$ih.'.png',$png);file_put_contents($inbox.'/'.$vh.'.mp4',$mp4);
$db->beginTransaction();
try{
    growthCommand($db,'/resume',$cfg);$task=creativeCreate($db,'infrastructure','threads','education');
    creativeImport($db,['version'=>1,'task_id'=>$task,'revision'=>1,'model'=>'local-test:1b','caption'=>'Plan IT requirements.','visual_brief'=>'Illustration','video_brief'=>'Planning','review_notes'=>'Review']);growthCommand($db,"/approve $task 1",$cfg);
    $raw=json_encode(['version'=>1,'task_id'=>$task,'revision'=>1,'channel'=>'threads','renderer'=>'template','width'=>1080,'height'=>1080,'image_sha256'=>$ih,'video_sha256'=>$vh]);$manifest=mediaRegister($db,$raw)['manifest_sha256'];growthCommand($db,"/approvemedia $task 1 $manifest",$cfg);
    inboxImport($inbox,$root,['image_hash'=>$ih,'video_hash'=>$vh,'width'=>1080,'height'=>1080]);caCheck(file_get_contents($root.'/'.$ih.'.png')===$png&&file_get_contents($root.'/'.$vh.'.mp4')===$mp4,'Exact inbox import');
    $id=caRegister($db,$cfg,'threads',$task,1,$manifest,$items);$digest=caDigest($items);
    caCheck(str_contains(growthCommand($db,"/approvecarousel $id $digest threads 123",$cfg),'approved'),'Exact bundle approval');
    try{miRegister($db,$cfg,'threads',$task,1,$manifest,$items[0]['asset_hash'],100);throw new LogicException('Duplicate');}catch(InvalidArgumentException $e){}
    $r=caClaim($db,$cfg,'threads',$id,'children');$got=caFetch($db,$cfg,$r['items'][0]['fetch_token'],false);caCheck(hash('sha256',$got['bytes'])===$items[0]['asset_hash'],'Exact fetch');
    caRemember($db,$r,1,'111');caRemember($db,$r,2,'222');caFinish($db,$r,'children',true);
    $r=caClaim($db,$cfg,'threads',$id,'parent');caCheck(array_column($r['items'],'container_id')===['111','222'],'Durable order');
    try{caFetch($db,$cfg,str_repeat('a',64),false);throw new LogicException('Invalid token');}catch(RuntimeException $e){}
    caFinish($db,$r,'parent',true,'999');$r=caClaim($db,$cfg,'threads',$id,'publish');caFinish($db,$r,'publish',false);
    caCheck(str_contains(growthCommand($db,'/publications',$cfg),'threads unknown: 1'),'Unified report');
    try{caClaim($db,$cfg,'threads',$id,'publish');throw new LogicException('Retry accepted');}catch(RuntimeException $e){}
    $db->rollBack();echo "Synthetic carousel, exact inbox and receipt state: PASS\n";
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();fwrite(STDERR,'Carousel test failed: '.$e->getMessage()."\n");exit(1);}finally{foreach(glob($root.'/*') as $path)unlink($path);foreach(glob($inbox.'/*') as $path)unlink($path);rmdir($root);rmdir($inbox);}
