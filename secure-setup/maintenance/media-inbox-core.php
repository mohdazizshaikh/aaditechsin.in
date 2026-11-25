<?php
declare(strict_types=1);
require_once __DIR__.'/upload-assets.php';
function inboxReadPair(string $inbox,string $root,array $row):array{
    if($inbox===$root||str_starts_with($inbox,$root.'/')||str_starts_with($root,$inbox.'/'))throw new RuntimeException('Separate inbox required');
    foreach(['image_hash','video_hash'] as $k)if(!is_string($row[$k]??null)||!preg_match('/^[a-f0-9]{64}$/D',$row[$k]))throw new RuntimeException('Invalid manifest');
    $png=uploadPhotoRead($inbox.'/'.$row['image_hash'].'.png',$row['image_hash'],(int)$row['width'],(int)$row['height']);
    $mp4=uploadRead($inbox.'/'.$row['video_hash'].'.mp4',$row['video_hash']);
    // Existing content-addressed files must match before any new write.
    $photo=$root.'/'.$row['image_hash'].'.png';$video=$root.'/'.$row['video_hash'].'.mp4';
    if(file_exists($photo)||is_link($photo))uploadPhotoRead($photo,$row['image_hash'],(int)$row['width'],(int)$row['height'],strlen($png));
    if(file_exists($video)||is_link($video))uploadRead($video,$row['video_hash'],strlen($mp4));
    return [$png,$mp4];
}
function inboxImport(string $inbox,string $root,array $row):void{
    [$png,$mp4]=inboxReadPair($inbox,$root,$row);
    uploadPhotoStore($root,$row['image_hash'],$png,(int)$row['width'],(int)$row['height']);uploadStore($root,$row['video_hash'],$mp4);
}
function inboxBatchEnabled(array $cfg):bool{
    return mediaEnabled($cfg)&&($cfg['media_upload']['enabled']??false)===true&&($cfg['media_upload']['inbox_import_enabled']??false)===true&&($cfg['media_upload']['inbox_batch_enabled']??false)===true;
}
function inboxBatchRequest(string $raw):array{
    if(strlen($raw)>16384)throw new InvalidArgumentException('Batch too large');
    $doc=json_decode($raw,true,16,JSON_THROW_ON_ERROR);
    if(!is_array($doc)||count($doc)!==2||($doc['version']??null)!==1||!is_array($doc['items']??null)||!array_is_list($doc['items'])||count($doc['items'])<1||count($doc['items'])>12)throw new InvalidArgumentException('Invalid batch');
    $seen=[];$out=[];
    foreach($doc['items'] as $r){
        if(!is_array($r)||count($r)!==3||!is_int($r['task_id']??null)||$r['task_id']<1||$r['task_id']>999999999999999||!is_int($r['revision']??null)||$r['revision']<1||$r['revision']>999999999||!is_string($r['manifest_sha256']??null)||!preg_match('/^[a-f0-9]{64}$/D',$r['manifest_sha256'])||isset($seen[$r['task_id']]))throw new InvalidArgumentException('Invalid or duplicate scope');
        $seen[$r['task_id']]=true;$out[]=['task_id'=>$r['task_id'],'revision'=>$r['revision'],'manifest_sha256'=>$r['manifest_sha256']];
    }
    return $out;
}
function inboxApproved(PDO $db,int $task,int $rev,string $manifest):array{
    $q=$db->prepare("SELECT m.image_hash,m.video_hash,m.width,m.height,c.channel FROM growth_media_manifests m JOIN growth_tasks t ON t.id=m.task_id AND t.revision=m.revision AND t.status='approved' JOIN growth_creative c ON c.task_id=t.id WHERE m.task_id=? AND m.revision=? AND m.manifest_hash=? AND m.status='approved' FOR UPDATE");
    $q->execute([$task,$rev,$manifest]);$r=$q->fetch(PDO::FETCH_ASSOC);
    if(!$r||[(int)$r['width'],(int)$r['height']]!==mediaDimensions($r['channel']))throw new RuntimeException('Current approved manifest required');
    return ['image_hash'=>$r['image_hash'],'video_hash'=>$r['video_hash'],'width'=>(int)$r['width'],'height'=>(int)$r['height'],'channel'=>$r['channel']];
}
function inboxBatchPlan(PDO $db,array $cfg,string $inbox,string $root,string $raw):array{
    if(!inboxBatchEnabled($cfg))throw new RuntimeException('Batch disabled');
    $items=inboxBatchRequest($raw);creativeActive($db);$rows=[];
    foreach($items as $item){
        $r=inboxApproved($db,$item['task_id'],$item['revision'],$item['manifest_sha256']);
        [$png,$mp4]=inboxReadPair($inbox,$root,$r);
        $rows[]=$item+$r+['image_bytes'=>strlen($png),'video_bytes'=>strlen($mp4)];unset($png,$mp4);
    }
    $digest=hash('sha256',json_encode(['media-inbox-batch-v1',$inbox,$root,$rows],JSON_THROW_ON_ERROR));
    return ['count'=>count($rows),'sha256'=>$digest,'rows'=>$rows];
}
function inboxBatchApply(PDO $db,array $cfg,string $inbox,string $root,string $raw,string $digest):int{
    $p=inboxBatchPlan($db,$cfg,$inbox,$root,$raw);
    if(!preg_match('/^[a-f0-9]{64}$/D',$digest)||!hash_equals($p['sha256'],$digest))throw new RuntimeException('Reviewed plan changed');
    $db->exec('SAVEPOINT inbox_batch');
    try{
        foreach($p['rows'] as $r){
            inboxImport($inbox,$root,$r);
            growthAudit($db,$r['task_id'],$r['revision'],'media_inbox_import','private_cli');
        }
        $db->exec('RELEASE SAVEPOINT inbox_batch');
        return $p['count'];
    }catch(Throwable $e){
        // Filesystem writes cannot roll back with SQL; retain exact content-addressed files.
        $db->exec('ROLLBACK TO SAVEPOINT inbox_batch');$db->exec('RELEASE SAVEPOINT inbox_batch');throw $e;
    }
}
