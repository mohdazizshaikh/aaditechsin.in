<?php
declare(strict_types=1);
require dirname(__DIR__).'/secure-setup/maintenance/native-import-lib.php';
function niCheck(bool $ok,string $msg):void{if(!$ok)throw new LogicException($msg);}
function niBlocked(callable $fn):void{try{$fn();}catch(RuntimeException|InvalidArgumentException|JsonException $e){return;}throw new LogicException('Expected refusal');}
$cfg=['growth'=>['enabled'=>true],'creative'=>['enabled'=>true],'media'=>['enabled'=>true,'native_review_enabled'=>true],'media_upload'=>['enabled'=>true,'inbox_import_enabled'=>true,'native_import_enabled'=>true]];
$day=new DateTimeImmutable('2026-11-26',new DateTimeZone('UTC'));
niCheck(nativeImportEnabled($cfg)&&!nativeImportEnabled([]),'Default OFF');
foreach([['growth','enabled'],['creative','enabled'],['media','enabled'],['media','native_review_enabled'],['media_upload','enabled'],['media_upload','inbox_import_enabled'],['media_upload','native_import_enabled']] as [$group,$key]){
    $bad=$cfg;$bad[$group][$key]=false;niBlocked(static function()use($bad){if(!nativeImportEnabled($bad))throw new RuntimeException('Disabled');});
}
foreach(['2026-11-25','2026-11-26'] as $d)niCheck(nativeImportDay(json_encode(['review_day_utc'=>$d]),$day)->format('Y-m-d')===$d,'Historical packet date');
foreach(['2026-11-27','2026-02-30','2026-11-26T00:00:00Z','',null] as $d)niBlocked(static fn()=>nativeImportDay(json_encode(['review_day_utc'=>$d]),$day));
function niChunk(string $kind,string $bytes):string{return pack('N',strlen($bytes)).$kind.$bytes.pack('N',crc32($kind.$bytes));}
function niPng(int $w,int $h):string{
    return "\x89PNG\r\n\x1a\n".niChunk('IHDR',pack('NNCCCCC',$w,$h,8,2,0,0,0)).niChunk('IDAT',gzcompress(str_repeat("\0".str_repeat("\x13\x35\x57",$w),$h))).niChunk('IEND','');
}
function niFile(string $p,string $bytes):void{if(file_put_contents($p,$bytes)!==strlen($bytes)||!chmod($p,0600))throw new RuntimeException('Fixture write');}
function niPacket(array $job,string $poster,string $video,DateTimeImmutable $day):array{
    $r=['version'=>1,'scope'=>'native-video-review-only','task_id'=>$job['task_id'],'revision'=>$job['revision'],'channel'=>$job['channel'],'dimensions'=>$job['dimensions'],'model_sha256'=>str_repeat('a',64),'job_sha256'=>hash('sha256',nativeReviewCanonical($job)),'seed'=>42,'poster_sha256'=>$poster,'candidate_sha256'=>$video];
    return ['version'=>1,'scope'=>'native-video-owner-review','review_day_utc'=>$day->format('Y-m-d'),'plan_sha256'=>str_repeat('d',64),'index_sha256'=>str_repeat('e',64),'decision_sha256'=>str_repeat('f',64),'publication_authorized'=>false,'items'=>[['review'=>$r,'review_sha256'=>hash('sha256',nativeReviewCanonical($r)."\n")]]];
}
$tmp=sys_get_temp_dir().'/aaditechs-native-import-'.bin2hex(random_bytes(8));mkdir($tmp,0700);$in=$tmp.'/in';$out=$tmp.'/out';mkdir($in,0700);mkdir($out,0700);
try{
    nativeImportRoots($in,$out);niBlocked(static fn()=>nativeImportRoots($in,$in));
    $png=niPng(1200,628);
    // Offline header-only fixture, not an assertion of codec validity.
    $mp4=pack('N',24).'ftypmp42'.str_repeat("\0",12);
    $ih=hash('sha256',$png);$vh=hash('sha256',$mp4);
    niFile($in.'/'.$ih.'.png',$png);niFile($in.'/'.$vh.'.mp4',$mp4);
    $r=['poster_sha256'=>$ih,'candidate_sha256'=>$vh,'dimensions'=>[1200,628]];
    niCheck(nativeImportPair($in,$r)===[$png,$mp4],'Exact asset pair');
    niBlocked(static fn()=>nativeImportPair($in,array_replace($r,['dimensions'=>[1,1]])));
    chmod($in.'/'.$vh.'.mp4',0644);niBlocked(static fn()=>nativeImportPair($in,$r));chmod($in.'/'.$vh.'.mp4',0600);
    $alias=$in.'/alias';link($in.'/'.$vh.'.mp4',$alias);niBlocked(static fn()=>nativeImportPair($in,$r));unlink($alias);
    niFile($in.'/'.$vh.'.mp4','tampered');niBlocked(static fn()=>nativeImportPair($in,$r));niFile($in.'/'.$vh.'.mp4',$mp4);
    echo "Native import flags, historical date, private file, profile and digest rules PASS\n";
    if(($argv[1]??'')!=='--database')return;
    if(getenv('AADITECHS_DISPOSABLE_TEST')!=='yes'||getenv('CI_DB_HOST')!=='127.0.0.1'||getenv('CI_DB_PORT')!=='3306'||getenv('CI_DB_NAME')!=='aaditechs_ci'||getenv('CI_DB_USER')!=='aaditechs_ci'||getenv('CI_DB_PASSWORD')!=='ci_only_not_real_password')exit(2);
    $db=new PDO('mysql:host=127.0.0.1;port=3306;dbname=aaditechs_ci;charset=utf8mb4','aaditechs_ci','ci_only_not_real_password',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
    $db->beginTransaction();
    growthCommand($db,'/resume',$cfg);
    $id=creativeCreate($db,'infrastructure','linkedin','education');
    niCheck(creativeImport($db,['version'=>1,'task_id'=>$id,'revision'=>1,'model'=>'local-test:1b','caption'=>'Plan your network.','visual_brief'=>'Illustration','video_brief'=>'Planning','review_notes'=>'Review']),'Text import');
    growthCommand($db,"/approve $id 1",$cfg);
    $job=mediaExport($db,$id,1);
    // Test generic metadata registration cannot bypass the dedicated native path.
    $generic=json_encode(['version'=>1,'task_id'=>$id,'revision'=>1,'channel'=>'linkedin','renderer'=>'native-video','width'=>1200,'height'=>628,'image_sha256'=>$ih,'video_sha256'=>$vh]);
    niBlocked(static fn()=>mediaRegister($db,$generic));
    $p=niPacket($job,$ih,$vh,$day);$raw=nativeReviewCanonical($p)."\n";$digest=hash('sha256',$raw);
    niBlocked(static fn()=>nativeImportPlan($db,$cfg,$in,$out,$raw,$digest,$day));
    $review=nativeReviewPlan($db,$cfg,$raw,$digest,$day);
    $applied=nativeReviewApply($db,$cfg,$raw,$digest,$review['plan_sha256'],$day);$rid=$applied['task_ids'][0];
    niBlocked(static fn()=>nativeImportPlan($db,$cfg,$in,$out,$raw,$digest,$day));
    growthCommand($db,"/approve $rid 1",$cfg);
    $plan=nativeImportPlan($db,$cfg,$in,$out,$raw,$digest,$day);
    niCheck(count(scandir($out))===2&&$plan['rows'][0]['existing_status']===null,'Plan does not write');
    niBlocked(static fn()=>nativeImportApply($db,$cfg,$in,$out,$raw,$digest,str_repeat('0',64),$day));
    growthCommand($db,'/pause',$cfg);niBlocked(static fn()=>nativeImportPlan($db,$cfg,$in,$out,$raw,$digest,$day));growthCommand($db,'/resume',$cfg);
    $result=nativeImportApply($db,$cfg,$in,$out,$raw,$digest,$plan['plan_sha256'],$day);
    $mh=$result['manifest_sha256'][0];
    niCheck($result['created']===1&&!$result['publication_authorized']&&nativeImportPair($out,$r)===[$png,$mp4],'Exact binary import');
    $q=$db->prepare('SELECT renderer,status FROM growth_media_manifests WHERE task_id=? AND manifest_hash=?');$q->execute([$id,$mh]);
    niCheck($q->fetch(PDO::FETCH_ASSOC)===['renderer'=>'native-video','status'=>'pending_review'],'Distinct pending manifest');
    niBlocked(static fn()=>nativeImportApply($db,$cfg,$in,$out,$raw,$digest,$plan['plan_sha256'],$day));
    growthCommand($db,"/approvemedia $id 1 $mh",$cfg);
    $repeat=nativeImportPlan($db,$cfg,$in,$out,$raw,$digest,$day);
    niCheck($repeat['rows'][0]['existing_status']==='approved'&&nativeImportApply($db,$cfg,$in,$out,$raw,$digest,$repeat['plan_sha256'],$day)['preserved']===1,'Preserve approval on repeat');
    unlink($out.'/'.$vh.'.mp4');niBlocked(static fn()=>nativeImportPlan($db,$cfg,$in,$out,$raw,$digest,$day));niFile($out.'/'.$vh.'.mp4',$mp4);
    $db->prepare("UPDATE growth_creative_results SET caption='Changed draft' WHERE task_id=? AND revision=1")->execute([$id]);
    niBlocked(static fn()=>nativeImportPlan($db,$cfg,$in,$out,$raw,$digest,$day));
    $db->rollBack();echo "Disposable native binary import, approval separation, stale source and replay checks PASS\n";
}catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();fwrite(STDERR,'Native import test: '.$e->getMessage()."\n");exit(1);}
finally{foreach([$in,$out] as $dir){foreach(glob($dir.'/*')?:[] as $f)unlink($f);rmdir($dir);}rmdir($tmp);}
