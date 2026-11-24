<?php
declare(strict_types=1);
require dirname(__DIR__).'/public_html/api/network-media-core.php';
require dirname(__DIR__).'/secure-setup/maintenance/network-media-send.php';
function nmCheck(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);}
$cfg=['growth'=>['enabled'=>true],'creative'=>['enabled'=>true],'media'=>['enabled'=>true],'social'=>['enabled'=>true,'network'=>['enabled'=>true,'linkedin'=>['enabled'=>true,'account_id'=>'123','access_token'=>str_repeat('a',40),'api_version'=>'202610'],'x'=>['enabled'=>true,'account_id'=>'456','access_token'=>str_repeat('b',40)]]],'media_upload'=>['enabled'=>true,'linkedin_enabled'=>true,'x_enabled'=>true]];
$bytes=str_repeat('x',100);$hash=hash('sha256',$bytes);$li='urn:li:image:abc';$url='https://www.linkedin.com/dms-uploads/abc/uploaded-image/0?ut=mock';
nmCheck(nmTarget([],'x')===null&&nmLinkedInUrl($url,$li)&&!nmLinkedInUrl('https://evil.example/dms-uploads/abc/uploaded-image/0?ut=mock',$li),'OFF and URL gate');
foreach(['linkedin','x'] as $provider){
    $p=nmTarget($cfg,$provider);$row=['provider'=>$provider,'target_id'=>$p['id'],'asset_hash'=>$hash,'byte_count'=>strlen($bytes),'caption'=>'Plan IT requirements.'];
    $mock=static function($p,$method,$path,$data)use($url,$li,$bytes){
        if($path==='rest/organizationAcls')return ['http'=>200,'json'=>['elements'=>[['role'=>'ADMINISTRATOR','state'=>'APPROVED','organizationTarget'=>'urn:li:organization:123']]]];
        if($path==='2/users/me')return ['http'=>200,'json'=>['data'=>['id'=>'456']]];
        if($path==='rest/images?action=initializeUpload')return ['http'=>200,'json'=>['value'=>['image'=>$li,'uploadUrl'=>$url]]];
        if($path==='2/media/upload'){nmCheck(base64_decode($data['media'],true)===$bytes,'Exact X bytes');return ['http'=>200,'json'=>['data'=>['id'=>'789','expires_after_secs'=>600]]];}
        if($path==='rest/images/'.rawurlencode($li))return ['http'=>200,'json'=>['id'=>$li,'owner'=>'urn:li:organization:123','status'=>'AVAILABLE']];
        if($path==='rest/posts'){nmCheck($data['content']['media']['id']===$li,'LinkedIn attachment');return ['http'=>201,'receipt'=>'urn:li:share:99'];}
        nmCheck($data['media']['media_ids']===['789'],'X attachment');return ['http'=>201,'json'=>['data'=>['id'=>'99']]];
    };
    $binary=static function($p,$u,$id,$b)use($url,$li,$bytes){nmCheck($u===$url&&$id===$li&&$b===$bytes,'Exact LinkedIn bytes');return ['http'=>201];};
    $events=[];$remember=static function($id,$phase)use(&$events){$events[]=$phase.':'.$id;};
    nmCheck(nmSend($p,$row,$bytes,$mock,$binary,static fn()=>null,$remember)['status']==='accepted','Mock '.$provider);
    nmCheck($events===($provider==='linkedin'?['initialized:urn:li:image:abc','uploaded:urn:li:image:abc']:['uploaded:789']),'Intermediate receipts');
    nmCheck(nmSend($p,$row,$bytes,$mock,$binary,static fn()=>null,static function(){throw new RuntimeException('audit failed');})['status']==='unknown','Receipt failure stops send');
    nmCheck(nmSend($p,$row,'tampered',$mock,$binary,static fn()=>null)['status']==='failed','Tamper gate');
}
if(($argv[1]??'')!=='--database'){echo "Network image mock: PASS\n";exit;}
if(getenv('AADITECHS_DISPOSABLE_TEST')!=='yes'||getenv('CI_DB_NAME')!=='aaditechs_ci'||getenv('CI_DB_USER')!=='aaditechs_ci'||getenv('CI_DB_HOST')!=='127.0.0.1')exit(2);
$db=new PDO('mysql:host=127.0.0.1;port='.(int)getenv('CI_DB_PORT').';dbname=aaditechs_ci;charset=utf8mb4','aaditechs_ci',getenv('CI_DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
$db->beginTransaction();
try{
    growthCommand($db,'/resume',$cfg);$id=creativeCreate($db,'infrastructure','x','education');
    creativeImport($db,['version'=>1,'task_id'=>$id,'revision'=>1,'model'=>'local-test:1b','caption'=>'Plan IT requirements.','visual_brief'=>'Illustration','video_brief'=>'Planning','review_notes'=>'Review']);
    growthCommand($db,"/approve $id 1",$cfg);
    $raw=json_encode(['version'=>1,'task_id'=>$id,'revision'=>1,'channel'=>'x','renderer'=>'template','width'=>1200,'height'=>628,'image_sha256'=>$hash,'video_sha256'=>str_repeat('b',64)]);
    $manifest=mediaRegister($db,$raw)['manifest_sha256'];growthCommand($db,"/approvemedia $id 1 $manifest",$cfg);
    $up=nmRegister($db,$cfg,$id,1,$manifest,'x',$hash,strlen($bytes));
    $when=(new DateTimeImmutable('now',new DateTimeZone('UTC')))->modify('+1 day')->format('Y-m-d\TH:i\Z');
    try{networkSchedule($db,$cfg,'x',$id,1,'456',$when);throw new LogicException('Text duplicate');}catch(InvalidArgumentException $e){}
    nmCheck(str_contains(growthCommand($db,"/approvenetmedia $up $manifest x 456",$cfg),'approved'),'Exact approval');
    $r=nmClaim($db,$cfg,'x',$up);nmRememberResource($db,$r,'789','uploaded');
    nmCheck(str_contains(nmRecoveryReport($db,$cfg,'/mediarecovery'),'uploaded 789'),'Resource recovery report');
    nmFinish($db,$r,networkResult('unknown',null,'publish_unverified'));
    try{nmClaim($db,$cfg,'x',$up);throw new LogicException('Retry accepted');}catch(RuntimeException $e){}
    nmCheck(str_contains(growthCommand($db,'/publications',$cfg),'x unknown: 1'),'Unified report');
    $db->rollBack();echo "Synthetic network image reservation: PASS\n";
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();fwrite(STDERR,'Network media test failed: '.$e->getMessage()."\n");exit(1);}
