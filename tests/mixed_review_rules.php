<?php
declare(strict_types=1);
require dirname(__DIR__).'/public_html/api/carousel-core.php';
require dirname(__DIR__).'/secure-setup/maintenance/carousel-send.php';
require dirname(__DIR__).'/public_html/api/communication-review-core.php';
require dirname(__DIR__).'/secure-setup/maintenance/communications-retention-lib.php';
function mrCheck(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);}
function mrBlocked(callable $f):void{try{$f();}catch(RuntimeException|InvalidArgumentException $e){return;}throw new LogicException('Expected refusal');}
$cfg=['public_base_url'=>'https://staging.example.com','growth'=>['enabled'=>true],'creative'=>['enabled'=>true],'media'=>['enabled'=>true],'social'=>['enabled'=>true,'meta'=>['enabled'=>true,'threads'=>['enabled'=>true,'account_id'=>'123','access_token'=>str_repeat('a',40),'api_version'=>'v1.0']]],'media_upload'=>['enabled'=>true,'fetch_enabled'=>true,'fetch_log_redaction_verified'=>true,'threads_images_enabled'=>true,'threads_carousel_enabled'=>true,'threads_mixed_carousel_enabled'=>true,'instagram_images_enabled'=>true,'instagram_carousel_enabled'=>true,'instagram_mixed_carousel_enabled'=>true,'instagram'=>['enabled'=>true,'page_id'=>'456','account_id'=>'789','access_token'=>str_repeat('b',40),'api_version'=>'v27.0']],'communications'=>['enabled'=>true,'reviews_enabled'=>true,'retention_enabled'=>true]];
$legacy=[['asset_hash'=>str_repeat('a',64),'byte_count'=>100],['asset_hash'=>str_repeat('b',64),'byte_count'=>200]];
$items=$legacy;$items[1]['asset_kind']='video';
mrCheck(caDigest($legacy)===hash('sha256',json_encode($legacy,JSON_THROW_ON_ERROR))&&caDigest($items)!==caDigest($legacy)&&caDigest($items)!==caDigest(array_reverse($items)),'Legacy and typed digest');
$off=$cfg;$off['media_upload']['threads_mixed_carousel_enabled']=false;
mrBlocked(static fn()=>caKinds($off,'threads',['video_hash'=>$items[1]['asset_hash']],$items));
mrBlocked(static fn()=>caKinds($cfg,'threads',['video_hash'=>str_repeat('c',64)],$items));
$bad=$items;$bad[0]['asset_kind']='video';mrBlocked(static fn()=>caDigest($bad));
foreach(['threads','instagram'] as $provider){
    $p=caTarget($cfg,$provider);$row=['provider'=>$provider,'target_id'=>$p['id'],'page_id'=>$p['page'],'items'=>[['position_no'=>1,'fetch_token'=>str_repeat('a',64),'asset_kind'=>'image'],['position_no'=>2,'fetch_token'=>str_repeat('b',64),'asset_kind'=>'video']]];$calls=[];$ids=[];
    $mock=static function($p,$method,$path,$data)use(&$calls){
        if($path==='me')return ['http'=>200,'json'=>$p['provider']==='threads'?['id'=>'123']:['id'=>'456','instagram_business_account'=>['id'=>'789']]];
        $calls[]=$data;return ['http'=>200,'json'=>['id'=>count($calls)===1?'111':'222']];
    };
    mrCheck(caChildren($p,$row,$mock,static function($pos,$id)use(&$ids){$ids[]=$id;})&&$ids===['111','222'],'Child order');
    mrCheck(isset($calls[0]['image_url'])&&!isset($calls[0]['video_url'])&&isset($calls[1]['video_url'])&&!isset($calls[1]['image_url'])&&$calls[1]['media_type']==='VIDEO'&&$calls[1]['is_carousel_item']==='true','Typed child request');
}
if(($argv[1]??'')!=='--database'){echo "Mixed carousel typed digest and child request: PASS\n";exit;}
if(getenv('AADITECHS_DISPOSABLE_TEST')!=='yes'||getenv('CI_DB_NAME')!=='aaditechs_ci'||getenv('CI_DB_USER')!=='aaditechs_ci'||getenv('CI_DB_HOST')!=='127.0.0.1')exit(2);
$db=new PDO('mysql:host=127.0.0.1;port='.(int)getenv('CI_DB_PORT').';dbname=aaditechs_ci;charset=utf8mb4','aaditechs_ci',getenv('CI_DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
$root=sys_get_temp_dir().'/aaditechs-mr-'.bin2hex(random_bytes(8));mkdir($root,0700);$cfg['media_upload']['private_root']=$root;
$im=imagecreatetruecolor(1080,1080);ob_start();imagejpeg($im,null,85);$jpeg=ob_get_clean();ob_start();imagepng($im);$png=ob_get_clean();imagedestroy($im);
$mp4=pack('N',32).'ftypmp42'.str_repeat('v',20);$jh=hash('sha256',$jpeg);$ih=hash('sha256',$png);$vh=hash('sha256',$mp4);miStore($root,$jh,$jpeg,1080,1080);file_put_contents($root.'/'.$vh.'.mp4',$mp4);chmod($root.'/'.$vh.'.mp4',0600);
$now=new DateTimeImmutable('now',new DateTimeZone('UTC'));$day=$now->modify('-1 day')->format('Y-m-d');$today=$now->format('Y-m-d');
$db->beginTransaction();
try{
    growthCommand($db,'/resume',$cfg);$task=creativeCreate($db,'infrastructure','threads','education');
    creativeImport($db,['version'=>1,'task_id'=>$task,'revision'=>1,'model'=>'local-test:1b','caption'=>'Plan IT requirements.','visual_brief'=>'Illustration','video_brief'=>'Planning','review_notes'=>'Review']);growthCommand($db,"/approve $task 1",$cfg);
    $manifest=mediaRegister($db,json_encode(['version'=>1,'task_id'=>$task,'revision'=>1,'channel'=>'threads','renderer'=>'template','width'=>1080,'height'=>1080,'image_sha256'=>$ih,'video_sha256'=>$vh]))['manifest_sha256'];growthCommand($db,"/approvemedia $task 1 $manifest",$cfg);
    $items=[['asset_hash'=>$jh,'byte_count'=>strlen($jpeg),'asset_kind'=>'image'],['asset_hash'=>$vh,'byte_count'=>strlen($mp4),'asset_kind'=>'video']];
    $id=caRegister($db,$cfg,'threads',$task,1,$manifest,$items);$digest=caDigest($items);
    mrCheck(str_contains(growthCommand($db,"/approvecarousel $id $digest threads 123",$cfg),'approved'),'Mixed approval');
    $r=caClaim($db,$cfg,'threads',$id,'children');$token=$r['items'][1]['fetch_token'];$got=caFetch($db,$cfg,$token,false);
    mrCheck($got['kind']==='video'&&$got['bytes']===$mp4,'Exact video fetch');
    $off=$cfg;$off['media_upload']['threads_mixed_carousel_enabled']=false;mrBlocked(static fn()=>caFetch($db,$off,$token,false));
    caRemember($db,$r,1,'111');caRemember($db,$r,2,'222');caFinish($db,$r,'children',true);
    $r=caClaim($db,$cfg,'threads',$id,'parent');mrBlocked(static fn()=>caFetch($db,$cfg,$token,false));caFinish($db,$r,'parent',true,'999');$r=caClaim($db,$cfg,'threads',$id,'publish');caFinish($db,$r,'publish',true,'888');
    mrCheck(str_contains(growthCommand($db,"/commreview calls $day $today",$cfg),'evidence required'),'No invented missed count');
    communicationsImport($db,$cfg,['channel'=>'calls','day_utc'=>$day,'received'=>7,'answered'=>4,'missed'=>3],$now);
    mrCheck(str_contains(growthCommand($db,"/commreview calls $day $today",$cfg),'review set')&&str_contains(growthCommand($db,'/commreviews',$cfg),'1 due/overdue'),'Owner reminder');
    mrCheck(str_contains(growthCommand($db,'/intelligence',$cfg),'Communication review reminders: 1 due/overdue'),'Report integration');
    growthCommand($db,'/pause',$cfg);
    mrCheck(str_contains(growthCommand($db,"/commreview calls $day $today",$cfg),'paused'),'Pause');
    $future=$now->modify('+91 days');mrBlocked(static fn()=>commRetentionPlan($db,$cfg,$future));
    mrCheck(str_contains(growthCommand($db,"/completecommreview calls $day",$cfg),'completed'),'Complete');
    $plan=commRetentionPlan($db,$cfg,$future);commRetentionApply($db,$cfg,$plan['digest'],$future);
    mrCheck((int)$db->query("SELECT COUNT(*) FROM growth_communication_reviews")->fetchColumn()===0,'Closed review follows aggregate retention');
    $db->rollBack();echo "Synthetic mixed carousel, communication reviews and retention: PASS\n";
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();fwrite(STDERR,'Mixed/review test failed: '.$e->getMessage()."\n");exit(1);}finally{foreach(glob($root.'/*') as $path)unlink($path);rmdir($root);}
