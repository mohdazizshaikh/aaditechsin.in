<?php
declare(strict_types=1);
require_once __DIR__.'/native-review-lib.php';
require_once __DIR__.'/upload-assets.php';
require_once dirname(__DIR__,2).'/public_html/api/media-storage-core.php';
function nativeImportEnabled(array $cfg):bool{
    return mediaEnabled($cfg)&&($cfg['media']['native_review_enabled']??false)===true&&($cfg['media_upload']['enabled']??false)===true&&($cfg['media_upload']['inbox_import_enabled']??false)===true&&($cfg['media_upload']['native_import_enabled']??false)===true;
}
function nativeImportDay(string $raw,?DateTimeImmutable $now=null):DateTimeImmutable{
    if(strlen($raw)>65536)throw new InvalidArgumentException('Packet cap');
    $p=strictJsonDecode($raw,true,16);$day=$p['review_day_utc']??null;
    if(!is_string($day)||!preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D',$day))throw new InvalidArgumentException('Packet date');
    $z=new DateTimeZone('UTC');$d=DateTimeImmutable::createFromFormat('!Y-m-d',$day,$z);
    $today=($now??new DateTimeImmutable('now',$z))->setTimezone($z)->setTime(0,0);
    if(!$d||$d->format('Y-m-d')!==$day||$d>$today)throw new InvalidArgumentException('Packet date');
    // An already approved historical packet remains usable; exact current job and review are rechecked.
    return $d;
}
function nativeImportRoots(string $inbox,string $root):void{
    $web=dirname(__DIR__,2).'/public_html';
    if(uploadRoot($inbox,$web)!==$inbox||uploadRoot($root,$web)!==$root||$inbox===$root||str_starts_with($inbox,$root.'/')||str_starts_with($root,$inbox.'/'))throw new RuntimeException('Separate canonical private roots required');
}
function nativeImportPair(string $dir,array $r):array{
    $png=storageBytes($dir,$r['poster_sha256'].'.png');
    $mp4=storageBytes($dir,$r['candidate_sha256'].'.mp4');
    $info=@getimagesizefromstring($png);
    if(!$info||$info[2]!==IMAGETYPE_PNG||[$info[0],$info[1]]!==$r['dimensions']||strlen($png)>10*1024*1024)throw new RuntimeException('Poster profile');
    return [$png,$mp4];
}
function nativeImportManifest(array $review,array $row,string $packet):array{
    return ['version'=>1,'scope'=>'native-video-import-v1','renderer'=>'native-video','task_id'=>$review['task_id'],'revision'=>$review['revision'],'channel'=>$review['channel'],'width'=>$review['dimensions'][0],'height'=>$review['dimensions'][1],'image_sha256'=>$review['poster_sha256'],'video_sha256'=>$review['candidate_sha256'],'review_sha256'=>$row['review_sha256'],'editorial_task_id'=>(int)$row['existing']['id'],'packet_sha256'=>$packet];
}
function nativeImportPlan(PDO $db,array $cfg,string $inbox,string $root,string $raw,string $digest,?DateTimeImmutable $now=null):array{
    if(!nativeImportEnabled($cfg)||!$db->inTransaction())throw new RuntimeException('Native import disabled or no transaction');
    nativeImportRoots($inbox,$root);
    $day=nativeImportDay($raw,$now);
    $packet=nativeReviewScope($cfg,$raw,$digest,$day);
    $review=nativeReviewPlan($db,$cfg,$raw,$digest,$day);
    $rows=[];
    $lookup=$db->prepare('SELECT renderer,status,image_hash,video_hash,width,height FROM growth_media_manifests WHERE task_id=? AND revision=? AND manifest_hash=? FOR UPDATE');
    $audit=$db->prepare("SELECT COUNT(*) FROM growth_audit WHERE task_id=? AND revision=1 AND action=? AND actor=?");
    foreach($review['rows'] as $i=>$row){
        $existing=$row['existing'];
        if(!$existing||$existing['status']!=='approved'||(int)$existing['revision']!==1||!hash_equals($existing['draft_sha256'],hash('sha256',nativeReviewDraft($row,$digest))))throw new RuntimeException('Exact approved editorial task required');
        foreach([['native_video_review_created','private_cli'],['approve','owner']] as [$action,$actor]){
            $audit->execute([(int)$existing['id'],$action,$actor]);if((int)$audit->fetchColumn()!==1)throw new RuntimeException('Editorial audit required');
        }
        $r=$packet['items'][$i]['review'];$m=nativeImportManifest($r,$row,$digest);$mh=hash('sha256',nativeReviewCanonical($m));
        [$png,$mp4]=nativeImportPair($inbox,$r);
        $dest=[];
        foreach([['poster_sha256','png',$png],['candidate_sha256','mp4',$mp4]] as [$key,$ext,$bytes]){
            $name=$r[$key].'.'.$ext;
            if(file_exists($root.'/.quarantine/'.$name)||is_link($root.'/.quarantine/'.$name))throw new RuntimeException('Quarantined asset requires separate reconciliation');
            $exists=file_exists($root.'/'.$name)||is_link($root.'/'.$name);
            if($exists&&storageBytes($root,$name)!==$bytes)throw new RuntimeException('Destination drift');
            $dest[$ext]=$exists;
        }
        $lookup->execute([$r['task_id'],$r['revision'],$mh]);$old=$lookup->fetch(PDO::FETCH_ASSOC);
        if($old&&($old['renderer']!=='native-video'||$old['image_hash']!==$r['poster_sha256']||$old['video_hash']!==$r['candidate_sha256']||[(int)$old['width'],(int)$old['height']]!==$r['dimensions']||!$dest['png']||!$dest['mp4']))throw new RuntimeException('Existing manifest requires reconciliation');
        $rows[]=['manifest'=>$m,'manifest_sha256'=>$mh,'image_bytes'=>strlen($png),'video_bytes'=>strlen($mp4),'destination'=>$dest,'existing_status'=>$old?$old['status']:null];
        unset($png,$mp4);
    }
    $plan=['packet_sha256'=>$digest,'rows'=>$rows,'publication_authorized'=>false];
    $plan['plan_sha256']=hash('sha256',nativeReviewCanonical(['native-import-plan-v1',$inbox,$root,$plan]));
    return $plan;
}
function nativeImportApply(PDO $db,array $cfg,string $inbox,string $root,string $raw,string $digest,string $planDigest,?DateTimeImmutable $now=null):array{
    if(!$db->inTransaction()||!nativeReviewHash($planDigest))throw new InvalidArgumentException('Reviewed plan required');
    $db->exec('SAVEPOINT native_import');
    try{
        $plan=nativeImportPlan($db,$cfg,$inbox,$root,$raw,$digest,$now);
        if(!hash_equals($plan['plan_sha256'],$planDigest))throw new RuntimeException('Plan changed');
        $created=0;$manifests=[];
        $insert=$db->prepare("INSERT INTO growth_media_manifests(task_id,revision,manifest_hash,image_hash,video_hash,renderer,width,height) VALUES(?,?,?,?,?,'native-video',?,?)");
        foreach($plan['rows'] as $row){
            $m=$row['manifest'];$manifests[]=$row['manifest_sha256'];
            if($row['existing_status']!==null)continue;
            [$png,$mp4]=nativeImportPair($inbox,['poster_sha256'=>$m['image_sha256'],'candidate_sha256'=>$m['video_sha256'],'dimensions'=>[$m['width'],$m['height']]]);
            if(strlen($png)!==$row['image_bytes']||strlen($mp4)!==$row['video_bytes'])throw new RuntimeException('Source changed');
            uploadPhotoStore($root,$m['image_sha256'],$png,$m['width'],$m['height']);uploadStore($root,$m['video_sha256'],$mp4);
            if(storageBytes($root,$m['image_sha256'].'.png')!==$png||storageBytes($root,$m['video_sha256'].'.mp4')!==$mp4)throw new RuntimeException('Destination verification');
            $insert->execute([$m['task_id'],$m['revision'],$row['manifest_sha256'],$m['image_sha256'],$m['video_sha256'],$m['width'],$m['height']]);
            growthAudit($db,$m['task_id'],$m['revision'],'native_video_import','private_cli');++$created;
        }
        $db->exec('RELEASE SAVEPOINT native_import');
        return ['created'=>$created,'preserved'=>count($manifests)-$created,'manifest_sha256'=>$manifests,'publication_authorized'=>false];
    }catch(Throwable $e){$db->exec('ROLLBACK TO SAVEPOINT native_import');$db->exec('RELEASE SAVEPOINT native_import');throw $e;}
}
