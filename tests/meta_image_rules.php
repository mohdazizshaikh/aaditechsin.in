<?php
declare(strict_types=1);
require dirname(__DIR__).'/public_html/api/meta-image-core.php';
require dirname(__DIR__).'/secure-setup/maintenance/meta-image-send.php';
function miCheck(bool $ok,string $m):void{if(!$ok)throw new RuntimeException($m);}
$cfg=['public_base_url'=>'https://staging.example.com','growth'=>['enabled'=>true],'creative'=>['enabled'=>true],'media'=>['enabled'=>true],'social'=>['enabled'=>true,'meta'=>['enabled'=>true,'threads'=>['enabled'=>true,'account_id'=>'123','access_token'=>str_repeat('a',40),'api_version'=>'v1.0']]],'media_upload'=>['enabled'=>true,'fetch_enabled'=>true,'fetch_log_redaction_verified'=>true,'threads_images_enabled'=>true,'instagram_images_enabled'=>true,'instagram'=>['enabled'=>true,'page_id'=>'456','account_id'=>'789','access_token'=>str_repeat('b',40),'api_version'=>'v27.0']]];
miCheck(miTarget([],'threads')===null&&miTarget($cfg,'instagram')['page']==='456','OFF and identity');
miCheck(miImageDimensions('instagram',1080,1920)===[1080,1350],'Instagram photo profile');
$disabled=$cfg;$disabled['media_upload']['fetch_log_redaction_verified']=false;miCheck(miTarget($disabled,'threads')===null,'Explicit log-redaction gate');
foreach(['threads','instagram'] as $provider){
    $p=miTarget($cfg,$provider);$row=['provider'=>$provider,'target_id'=>$p['id'],'page_id'=>$p['page'],'caption'=>'Plan IT requirements.','fetch_token'=>str_repeat('a',64),'container_id'=>'999'];$calls=[];
    $fake=static function($p,$method,$path,$data)use(&$calls,$row){
        $calls[]=$path;
        if($path==='me')return ['http'=>200,'json'=>$p['provider']==='threads'?['id'=>'123']:['id'=>'456','instagram_business_account'=>['id'=>'789']]];
        if($method==='GET')return ['http'=>200,'json'=>['id'=>'999','status'=>'FINISHED','status_code'=>'FINISHED']];
        if(str_ends_with($path,'_publish')){miCheck($data['creation_id']==='999','Bound container');return ['http'=>200,'json'=>['id'=>'111']];}
        miCheck($data['image_url']==='https://staging.example.com/api/media-fetch.php?t='.$row['fetch_token'],'Bound URL');return ['http'=>200,'json'=>['id'=>'999']];
    };
    miCheck(miCreate($p,$row,$fake)==='999'&&count($calls)===2,'Create without publish');
    miCheck(miPublish($p,$row,$fake)==='111','Ready publish');
}
if(($argv[1]??'')!=='--database'){echo "Meta image mock adapters: PASS\n";exit;}
if(getenv('AADITECHS_DISPOSABLE_TEST')!=='yes'||getenv('CI_DB_NAME')!=='aaditechs_ci'||getenv('CI_DB_USER')!=='aaditechs_ci'||getenv('CI_DB_HOST')!=='127.0.0.1')exit(2);
$db=new PDO('mysql:host=127.0.0.1;port='.(int)getenv('CI_DB_PORT').';dbname=aaditechs_ci;charset=utf8mb4','aaditechs_ci',getenv('CI_DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
$root=sys_get_temp_dir().'/aaditechs-mi-'.bin2hex(random_bytes(8));mkdir($root,0700);$cfg['media_upload']['private_root']=$root;
$im=imagecreatetruecolor(1080,1080);ob_start();imagejpeg($im,null,85);$jpeg=ob_get_clean();imagedestroy($im);$hash=hash('sha256',$jpeg);miStore($root,$hash,$jpeg,1080,1080);
$db->beginTransaction();
try{
    growthCommand($db,'/resume',$cfg);$id=creativeCreate($db,'infrastructure','threads','education');
    creativeImport($db,['version'=>1,'task_id'=>$id,'revision'=>1,'model'=>'local-test:1b','caption'=>'Plan IT requirements.','visual_brief'=>'Illustration','video_brief'=>'Planning','review_notes'=>'Review']);
    growthCommand($db,"/approve $id 1",$cfg);
    $raw=json_encode(['version'=>1,'task_id'=>$id,'revision'=>1,'channel'=>'threads','renderer'=>'template','width'=>1080,'height'=>1080,'image_sha256'=>str_repeat('a',64),'video_sha256'=>str_repeat('b',64)]);
    $manifest=mediaRegister($db,$raw)['manifest_sha256'];growthCommand($db,"/approvemedia $id 1 $manifest",$cfg);
    $up=miRegister($db,$cfg,'threads',$id,1,$manifest,$hash,strlen($jpeg));
    miCheck(str_contains(growthCommand($db,"/approveimage $up $manifest threads 123",$cfg),'mismatch'),'Manifest is not JPEG approval');
    miCheck(str_contains(growthCommand($db,"/approveimage $up $hash threads 123",$cfg),'approved'),'Exact JPEG approval');
    $r=miClaimFetch($db,$cfg,'threads',$up);$q=$db->prepare('SELECT token_hash FROM growth_meta_images WHERE id=?');$q->execute([$up]);miCheck($q->fetchColumn()===hash('sha256',$r['fetch_token']),'Digest only');
    try{miFetch($db,$cfg,str_repeat('f',64));throw new LogicException('Invalid token accepted');}catch(RuntimeException $e){}
    $got=miFetch($db,$cfg,$r['fetch_token']);miCheck($got['bytes']===$jpeg&&$got['size']===strlen($jpeg),'Exact positive binary fetch');
    $head=miFetch($db,$cfg,$r['fetch_token'],true);miCheck($head['bytes']===''&&$head['size']===strlen($jpeg),'HEAD size only');
    growthCommand($db,'/pause',$cfg);try{miFetch($db,$cfg,$r['fetch_token']);throw new LogicException('Paused fetch accepted');}catch(RuntimeException $e){}growthCommand($db,'/resume',$cfg);
    $db->prepare('UPDATE growth_meta_images SET fetch_count=20 WHERE id=?')->execute([$up]);try{miFetch($db,$cfg,$r['fetch_token']);throw new LogicException('Fetch cap bypass');}catch(RuntimeException $e){}
    $db->prepare('UPDATE growth_meta_images SET fetch_count=2 WHERE id=?')->execute([$up]);
    $db->prepare('UPDATE growth_meta_images SET expires_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE id=?')->execute([$up]);
    try{miFetch($db,$cfg,$r['fetch_token']);throw new LogicException('Expired token accepted');}catch(RuntimeException $e){}
    miFinishFetch($db,$r,'999');$fresh=miClaimPublish($db,$cfg,'threads',$up);miFinishPublish($db,$fresh,null);
    try{miClaimPublish($db,$cfg,'threads',$up);throw new LogicException('Retry accepted');}catch(RuntimeException $e){}
    miCheck(str_contains(growthCommand($db,'/publications',$cfg),'threads unknown: 1'),'Unified report');
    $db->rollBack();echo "Synthetic Meta image approval, expiry and no retry: PASS\n";
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();fwrite(STDERR,'Meta image test failed: '.$e->getMessage()."\n");exit(1);}finally{unlink($root.'/'.$hash.'.jpg');rmdir($root);}
