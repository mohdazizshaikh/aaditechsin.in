<?php
declare(strict_types=1);
require dirname(__DIR__).'/secure-setup/maintenance/native-review-lib.php';
function nrCheck(bool $v,string $m):void{if(!$v)throw new LogicException($m);}
function nrBlocked(callable $f):void{try{$f();}catch(RuntimeException|InvalidArgumentException|JsonException $e){return;}throw new LogicException('Expected refusal');}
$cfg=['growth'=>['enabled'=>true],'creative'=>['enabled'=>true],'media'=>['enabled'=>true,'native_review_enabled'=>true]];
$now=new DateTimeImmutable('now',new DateTimeZone('UTC'));
function nrItem(array $job):array{
    $r=['version'=>1,'scope'=>'native-video-review-only','task_id'=>$job['task_id'],'revision'=>$job['revision'],'channel'=>$job['channel'],'dimensions'=>$job['dimensions'],'model_sha256'=>str_repeat('a',64),'job_sha256'=>hash('sha256',nativeReviewCanonical($job)),'seed'=>42,'poster_sha256'=>str_repeat('b',64),'candidate_sha256'=>str_repeat('c',64)];
    return ['review'=>$r,'review_sha256'=>hash('sha256',nativeReviewCanonical($r)."\n")];
}
function nrPacket(array $items,DateTimeImmutable $now):array{
    return ['version'=>1,'scope'=>'native-video-owner-review','review_day_utc'=>$now->format('Y-m-d'),'plan_sha256'=>str_repeat('d',64),'index_sha256'=>str_repeat('e',64),'decision_sha256'=>str_repeat('f',64),'publication_authorized'=>false,'items'=>$items];
}
function nrRaw(array $packet):string{return nativeReviewCanonical($packet)."\n";}
function nrScope(array $cfg,array $packet,DateTimeImmutable $now):array{$raw=nrRaw($packet);return nativeReviewScope($cfg,$raw,hash('sha256',$raw),$now);}
$cases=0;
foreach(array_keys(growthCatalog()) as $service)foreach(creativeChannels() as $key=>$val){
    // Channel registry is keyed by name or represented as a list.
    $channel=is_int($key)?$val:$key;
    $item=nrItem(['task_id'=>1,'revision'=>1,'channel'=>$channel,'dimensions'=>mediaDimensions($channel),'service'=>$service]);
    $p=nrPacket([$item],$now);
    nrCheck(count(nrScope($cfg,$p,$now)['items'])===1,'Profile');
    $bad=$p;$bad['items'][0]['review']['dimensions']=[1,1];
    nrBlocked(static fn()=>nrScope($cfg,$bad,$now));++$cases;
}
nrCheck($cases===126,'14 x 9 coverage');
$items=[];for($i=1;$i<=12;$i++){
    $items[]=nrItem(['task_id'=>$i,'revision'=>1,'channel'=>'linkedin','dimensions'=>[1200,628]]);
    nrCheck(count(nrScope($cfg,nrPacket($items,$now),$now)['items'])===$i,'Batch size');
}
$p=nrPacket($items,$now);
foreach(['plan_sha256','index_sha256','decision_sha256','review_day_utc','publication_authorized','scope','version'] as $field){
    $bad=$p;$bad[$field]=null;nrBlocked(static fn()=>nrScope($cfg,$bad,$now));
}
$disabled=$cfg;$disabled['media']['native_review_enabled']=false;
nrBlocked(static fn()=>nrScope($disabled,$p,$now));
$bad=$p;$bad['items'][]=$items[0];nrBlocked(static fn()=>nrScope($cfg,$bad,$now));
$bad=$p;$bad['items'][1]=$bad['items'][0];nrBlocked(static fn()=>nrScope($cfg,$bad,$now));
$bad=$p;$bad['items'][0]['review_sha256']=str_repeat('0',64);nrBlocked(static fn()=>nrScope($cfg,$bad,$now));
$raw=nrRaw($p);nrBlocked(static fn()=>nativeReviewScope($cfg,$raw,str_repeat('0',64),$now));
$duplicate=str_replace('"version":1}', '"version":1,"version":1}', $raw);
nrBlocked(static fn()=>nativeReviewScope($cfg,$duplicate,hash('sha256',$duplicate),$now));
echo "Native review packet: 126 service/channel cases, bounded scope and digest refusal PASS\n";
if(($argv[1]??'')!=='--database')exit;
if(getenv('AADITECHS_DISPOSABLE_TEST')!=='yes'||getenv('CI_DB_NAME')!=='aaditechs_ci'||getenv('CI_DB_USER')!=='aaditechs_ci'||getenv('CI_DB_HOST')!=='127.0.0.1'||getenv('CI_DB_PORT')!=='3306'||getenv('CI_DB_PASSWORD')!=='ci_only_not_real_password')exit(2);
$db=new PDO('mysql:host=127.0.0.1;port=3306;dbname=aaditechs_ci;charset=utf8mb4','aaditechs_ci','ci_only_not_real_password',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
$db->beginTransaction();
try{
    growthCommand($db,'/resume',$cfg);
    $ids=[];$items=[];
    for($i=0;$i<12;$i++){
        $id=creativeCreate($db,'infrastructure','linkedin','education');$ids[]=$id;
        nrCheck(creativeImport($db,['version'=>1,'task_id'=>$id,'revision'=>1,'model'=>'local-test:1b','caption'=>'Plan your network.','visual_brief'=>'Illustration','video_brief'=>'Planning','review_notes'=>'Review']),'Text imported');
        growthCommand($db,"/approve $id 1",$cfg);$items[]=nrItem(mediaExport($db,$id,1));
    }
    $p=nrPacket($items,$now);$raw=nrRaw($p);$digest=hash('sha256',$raw);
    $plan=nativeReviewPlan($db,$cfg,$raw,$digest,$now);
    nrCheck($plan['create_count']===12&&$plan['publication_authorized']===false,'Exact plan');
    nrBlocked(static fn()=>nativeReviewApply($db,$cfg,$raw,$digest,str_repeat('0',64),$now));
    growthCommand($db,'/pause',$cfg);nrBlocked(static fn()=>nativeReviewPlan($db,$cfg,$raw,$digest,$now));growthCommand($db,'/resume',$cfg);
    $before=(int)$db->query('SELECT COUNT(*) FROM growth_media_manifests')->fetchColumn();
    $r=nativeReviewApply($db,$cfg,$raw,$digest,$plan['plan_sha256'],$now);
    nrCheck($r['created']===12&&!$r['publication_authorized'],'Create pending tasks');
    $q=$db->prepare("SELECT status,task_type,draft FROM growth_tasks WHERE id=?");
    foreach($r['task_ids'] as $id){$q->execute([$id]);$row=$q->fetch(PDO::FETCH_ASSOC);nrCheck($row['status']==='pending_review'&&$row['task_type']==='system'&&str_contains($row['draft'],'no verified inference'),'Review only');}
    nrCheck((int)$db->query('SELECT COUNT(*) FROM growth_media_manifests')->fetchColumn()===$before,'No media manifest');
    nrBlocked(static fn()=>nativeReviewApply($db,$cfg,$raw,$digest,$plan['plan_sha256'],$now));
    $again=nativeReviewPlan($db,$cfg,$raw,$digest,$now);
    nrCheck($again['create_count']===0&&nativeReviewApply($db,$cfg,$raw,$digest,$again['plan_sha256'],$now)['preserved']===12,'No duplicate tasks');
    $db->prepare("UPDATE growth_creative_results SET caption='Changed draft' WHERE task_id=? AND revision=1")->execute([$ids[11]]);
    nrBlocked(static fn()=>nativeReviewPlan($db,$cfg,$raw,$digest,$now));
    $db->rollBack();echo "Disposable 12-item native review handoff, stale source, pause, duplicate and publication isolation PASS\n";
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();fwrite(STDERR,'Native review test: '.$e->getMessage()."\n");exit(1);}
