<?php
declare(strict_types=1);
require dirname(__DIR__).'/public_html/api/operations.php';
require dirname(__DIR__).'/secure-setup/maintenance/media-restore-lib.php';
function stCheck(bool $ok,string $msg):void{if(!$ok)throw new RuntimeException($msg);}
$cfg=['growth'=>['enabled'=>true],'creative'=>['enabled'=>true],'media'=>['enabled'=>true],'media_upload'=>['enabled'=>true,'inventory_enabled'=>true,'retention_enabled'=>true],'portal'=>['enabled'=>true,'admin_email'=>'owner@example.invalid','admin_password_hash'=>password_hash('synthetic-password',PASSWORD_DEFAULT)]];
$now=time();$session=['role'=>'admin','last_seen'=>$now,'admin_fingerprint'=>opsAdminSessionFingerprint($cfg)];
stCheck(!storageEnabled([])&&storageOwner($cfg,$session,$now)&&!storageOwner($cfg,array_replace($session,['role'=>'partner']),$now)&&!storageOwner($cfg,$session,$now+1801)&&!storageOwner($cfg,array_replace($session,['admin_fingerprint'=>'wrong']),$now),'Admin only');
$root=sys_get_temp_dir().'/aaditechs-storage-'.bin2hex(random_bytes(8));mkdir($root,0700);$cfg['media_upload']['private_root']=$root;
$bytes=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+X2ZkAAAAASUVORK5CYII=');
$hash=hash('sha256',$bytes);$name=$hash.'.png';file_put_contents($root.'/'.$name,$bytes);chmod($root.'/'.$name,0600);touch($root.'/'.$name,$now-31*86400);
try{
    stCheck(storageBytes($root,$name)===$bytes&&!storageName('../'.$name),'Hash/path gate');
    $link=str_repeat('a',64).'.png';symlink($root.'/'.$name,$root.'/'.$link);
    try{storageBytes($root,$link);throw new LogicException('Symlink accepted');}catch(RuntimeException $e){}finally{unlink($root.'/'.$link);}
    // 50 accepted plan sizes and 50 invalid position cases.
    $names=[];$cases=0;
    for($i=1;$i<=50;$i++){
        $names[]=hash('sha256',(string)$i).'.png';
        stCheck(count(storagePlanRequest(json_encode(['version'=>1,'names'=>$names],JSON_THROW_ON_ERROR)))===$i,'Plan size');++$cases;
    }
    foreach(array_keys($names) as $i){
        $bad=$names;$bad[$i]='../escape.png';
        try{storagePlanRequest(json_encode(['version'=>1,'names'=>$bad],JSON_THROW_ON_ERROR));throw new LogicException('Invalid plan accepted');}catch(InvalidArgumentException $e){}
        ++$cases;
    }
    stCheck($cases===100&&count(storageReferenceSources())===15,'Plan matrix and sources');
    foreach([[],array_merge($names,[$name]),[$name,$name]] as $bad){
        try{storagePlanRequest(json_encode(['version'=>1,'names'=>$bad],JSON_THROW_ON_ERROR));throw new LogicException('Invalid scope accepted');}catch(InvalidArgumentException $e){}
    }
    $remaining=0;stCheck(storageAuditState($root,$name,$remaining)==='not_checked_budget','Budget');
    $remaining=10000;stCheck(storageAuditState($root,$name,$remaining)==='verified'&&$remaining===10000-strlen($bytes),'Verified bytes');
    stCheck(storageAuditState($root,str_repeat('b',64).'.mp4',$remaining)==='missing','Missing');
    stCheck(storageAuditState($root,'../escape.png',$remaining)==='invalid_reference','Invalid reference');
    $badName=str_repeat('c',64).'.png';file_put_contents($root.'/'.$badName,$bytes);chmod($root.'/'.$badName,0600);
    stCheck(storageAuditState($root,$badName,$remaining)==='invalid_bytes','Invalid bytes');
    chmod($root.'/'.$badName,0644);stCheck(storageAuditState($root,$badName,$remaining)==='unsafe','Unsafe');unlink($root.'/'.$badName);
    echo "Storage plan scope matrix: $cases cases PASS\n";
    $key=restoreContentKey(str_repeat('a',64));$backup=str_repeat('b',64);
    $budget=10000;$active=mediaRestoreZone($root,mediaRestoreNames($root,true),$budget);
    stCheck($active===[$name=>strlen($bytes)]&&$budget===10000-strlen($bytes),'Full active inventory');
    $manifest=['version'=>1,'scope'=>'private-media-restore','backup_sha256'=>$backup,'zones'=>[],'references'=>[]];
    foreach(['active','quarantine'] as $zone)$manifest['zones'][$zone]=mediaRestoreEntry($key,$backup,$zone,[]);
    foreach(array_keys(storageReferenceSources()) as $source)$manifest['references'][$source]=mediaRestoreEntry($key,$backup,'reference:'.$source,[])+['missing'=>0,'quarantined'=>0];
    $raw=mediaRestoreBytes($manifest);
    stCheck(count(array_filter(mediaRestoreCompare($manifest,$raw,hash('sha256',$raw))))===17&&mediaRestoreReady($manifest),'Manifest round trip');
    $matrix=0;
    foreach(['zones','references'] as $group)foreach(array_keys($manifest[$group]) as $label){
        $changed=$manifest;$changed[$group][$label]['hmac_sha256']=str_repeat('f',64);$raw=mediaRestoreBytes($changed);
        $diff=mediaRestoreCompare($manifest,$raw,hash('sha256',$raw));
        stCheck(!$diff[$group.':'.$label]&&count(array_filter($diff))===16,'Isolated drift');++$matrix;
        $changed=$manifest;unset($changed[$group][$label]);$raw=mediaRestoreBytes($changed);
        try{mediaRestoreCompare($manifest,$raw,hash('sha256',$raw));throw new LogicException('Missing group accepted');}catch(RuntimeException $e){}++$matrix;
        $changed=$manifest;$changed[$group][$label]['count']=-1;$raw=mediaRestoreBytes($changed);
        try{mediaRestoreCompare($manifest,$raw,hash('sha256',$raw));throw new LogicException('Invalid count accepted');}catch(RuntimeException $e){}++$matrix;
    }
    stCheck($matrix===51,'51 manifest cases');
    $changed=$manifest;$changed['references']['manifest-video']['count']=1;$changed['references']['manifest-video']['missing']=1;
    stCheck(!mediaRestoreReady($changed),'Missing reference blocks readiness');
    $changed['references']['manifest-video']['missing']=0;$changed['references']['manifest-video']['quarantined']=1;
    stCheck(!mediaRestoreReady($changed),'Quarantined reference blocks readiness');
    $budget=1;try{mediaRestoreZone($root,[$name],$budget);throw new LogicException('Budget bypass');}catch(RuntimeException $e){}
    $extra=$root.'/unexpected';file_put_contents($extra,'x');
    try{mediaRestoreNames($root,true);throw new LogicException('Unexpected file accepted');}catch(RuntimeException $e){}finally{unlink($extra);}
    stCheck(mediaRestoreDigest($key,$backup,'active',$active)!==mediaRestoreDigest($key,$backup,'quarantine',$active),'Zone binding');
    echo "Media restore manifest: 51 isolated drift/inventory/shape cases PASS\n";
    if(($argv[1]??'')!=='--database'){echo "Storage owner, hash and path tests: PASS\n";return;}
    if(getenv('AADITECHS_DISPOSABLE_TEST')!=='yes'||getenv('CI_DB_NAME')!=='aaditechs_ci'||getenv('CI_DB_USER')!=='aaditechs_ci'||getenv('CI_DB_HOST')!=='127.0.0.1')throw new RuntimeException('Not disposable');
    $db=new PDO('mysql:host=127.0.0.1;port='.(int)getenv('CI_DB_PORT').';dbname=aaditechs_ci;charset=utf8mb4','aaditechs_ci',getenv('CI_DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
    $db->beginTransaction();
    growthCommand($db,'/resume',$cfg);
    stCheck(storageInventory($db,$root)['items'][0]['candidate'],'Old orphan candidate');
    try{storageMove($db,$cfg,$name);throw new LogicException('Pause bypass');}catch(RuntimeException $e){}
    $scope=json_encode(['version'=>1,'names'=>[$name]],JSON_THROW_ON_ERROR);
    try{storageQuarantinePlan($db,$cfg,$scope,$now);throw new LogicException('Pause bypass');}catch(RuntimeException $e){}
    growthCommand($db,'/pause',$cfg);
    $plan=storageQuarantinePlan($db,$cfg,$scope,$now);
    stCheck($plan['count']===1&&$plan['bytes']===strlen($bytes)&&is_file($root.'/'.$name)&&!is_dir($root.'/.quarantine'),'Read-only plan');
    stCheck(storageQuarantinePlan($db,$cfg,$scope,$now)===$plan,'Stable plan');
    storageMove($db,$cfg,$name);stCheck(!file_exists($root.'/'.$name)&&is_file($root.'/.quarantine/'.$name),'Reversible move');
    storageMove($db,$cfg,$name,true);stCheck(storageBytes($root,$name)===$bytes,'Restore exact bytes');
    growthCommand($db,'/resume',$cfg);$id=creativeCreate($db,'infrastructure','facebook','education');
    creativeImport($db,['version'=>1,'task_id'=>$id,'revision'=>1,'model'=>'local-test:1b','caption'=>'Plan IT requirements.','visual_brief'=>'Illustration','video_brief'=>'Planning','review_notes'=>'Review']);
    growthCommand($db,"/approve $id 1",$cfg);
    $raw=json_encode(['version'=>1,'task_id'=>$id,'revision'=>1,'channel'=>'facebook','renderer'=>'template','width'=>1200,'height'=>628,'image_sha256'=>$hash,'video_sha256'=>str_repeat('b',64)]);
    mediaRegister($db,$raw);stCheck(storageReferences($db,$name)>0&&storagePreview($db,$cfg,$name)===$bytes,'Referenced preview');
    $audit=storageReferenceAudit($db,$root,50);
    stCheck(count($audit['sources'])===15&&$audit['sources']['manifest-image']['counts']['verified']>=1&&$audit['sources']['manifest-video']['counts']['missing']>=1,'Reverse reference sample');
    growthCommand($db,'/pause',$cfg);
    try{storageQuarantinePlan($db,$cfg,$scope,$now);throw new LogicException('Referenced plan accepted');}catch(RuntimeException $e){}
    try{storageMove($db,$cfg,$name);throw new LogicException('Referenced asset moved');}catch(RuntimeException $e){}
    if(releaseWorkerClear(releaseWorkerState($db))){
        $m=mediaRestoreCapture($db,$cfg,$backup,$key);$raw=mediaRestoreBytes($m);
        stCheck($m['zones']['active']['count']===1&&$m['references']['manifest-image']['count']>=1&&$m['references']['manifest-video']['missing']>=1&&!mediaRestoreReady($m),'Actual reference completeness');
        stCheck(count(array_filter(mediaRestoreCompare($m,$raw,hash('sha256',$raw))))===17,'Actual manifest parity');
        stCheck(!str_contains($raw,$name)&&!str_contains($raw,$root),'No asset names or paths exported');
    }else{
        try{mediaRestoreCapture($db,$cfg,$backup,$key);throw new LogicException('Worker gate bypass');}catch(RuntimeException $e){}
    }
    $q=$db->prepare('SELECT COUNT(*) FROM growth_media_storage_events WHERE asset_name=?');$q->execute([$name]);stCheck((int)$q->fetchColumn()===2,'Audit');
    $db->rollBack();echo "Synthetic inventory, protected references, quarantine and restore: PASS\n";
}catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();fwrite(STDERR,'Storage test failed: '.$e->getMessage()."\n");exit(1);}
finally{if(is_file($root.'/'.$name))unlink($root.'/'.$name);if(is_file($root.'/.quarantine/'.$name))unlink($root.'/.quarantine/'.$name);if(is_dir($root.'/.quarantine'))rmdir($root.'/.quarantine');rmdir($root);}
