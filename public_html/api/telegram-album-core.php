<?php
declare(strict_types=1);
require_once __DIR__.'/telegram-media-core.php';
function taTarget(array $cfg):?array{return ($cfg['media_upload']['telegram_album_enabled']??false)===true?tgMediaTarget($cfg):null;}
function taDigest(string $photo,string $video,int $pn,int $vn):string{
    if(!preg_match('/^[a-f0-9]{64}$/D',$photo)||!preg_match('/^[a-f0-9]{64}$/D',$video)||$pn<12||$pn>10*1024*1024||$vn<24||$vn>20*1024*1024||$pn+$vn>30*1024*1024)throw new InvalidArgumentException('Invalid album');
    return hash('sha256',json_encode([['photo',$photo,$pn],['video',$video,$vn]],JSON_THROW_ON_ERROR));
}
function taRegister(PDO $db,array $cfg,int $task,int $rev,string $manifest,int $pn,int $vn):int{
    $p=taTarget($cfg);if(!$p)throw new RuntimeException('Disabled');
    $r=tgMediaManifest($db,$task,$rev,$manifest,'photo');$digest=taDigest($r['image_hash'],$r['video_hash'],$pn,$vn);
    if(trim($r['caption'])===''||strlen($r['caption'])>900)throw new InvalidArgumentException('Caption');
    foreach(['growth_telegram_media','growth_publications'] as $table){$q=$db->prepare("SELECT id FROM $table WHERE task_id=? AND revision=?");$q->execute([$task,$rev]);if($q->fetchColumn()!==false)throw new InvalidArgumentException('Revision reserved');}
    $db->prepare("INSERT INTO growth_telegram_media(task_id,revision,manifest_hash,asset_kind,asset_hash,byte_count,target_id,caption) VALUES(?,?,?,'album',?,?,?,?)")->execute([$task,$rev,$manifest,$digest,$pn+$vn,$p['id'],$r['caption']]);
    $id=(int)$db->lastInsertId();growthAudit($db,$task,$rev,'tgalbum_staged','private_cli');return $id;
}
function taCommand(PDO $db,array $cfg,string $cmd):?string{
    if(!preg_match('/^\/(?:tgalbum|approvetgalbum)(?: |$)/D',$cmd))return null;
    $p=taTarget($cfg);if(!$p)return 'Telegram albums disabled.';
    if(preg_match('/^\/tgalbum ([1-9][0-9]{0,14})$/D',$cmd,$m)){
        $q=$db->prepare("SELECT u.*,a.image_hash,a.video_hash FROM growth_telegram_media u JOIN growth_media_manifests a ON a.task_id=u.task_id AND a.revision=u.revision AND a.manifest_hash=u.manifest_hash WHERE u.id=? AND u.asset_kind='album'");$q->execute([(int)$m[1]]);$r=$q->fetch(PDO::FETCH_ASSOC);if(!$r)return 'Album not found.';
        return '#'.$r['id'].' '.$r['status']."\nChannel ".$r['target_id']."\n1 PNG ".$r['image_hash']."\n2 MP4 ".$r['video_hash']."\nBundle ".$r['asset_hash']."\n".$r['caption']."\nReview both assets.".($r['album_messages']?"\nMessage IDs ".$r['album_messages']:'');
    }
    if(preg_match('/^\/approvetgalbum ([1-9][0-9]{0,14}) ([a-f0-9]{64}) (-100[0-9]{6,15})$/D',$cmd,$m)){
        try{creativeActive($db);}catch(RuntimeException $e){return 'Growth paused.';}
        if($m[3]!==$p['id'])return 'Target mismatch.';
        $q=$db->prepare("UPDATE growth_telegram_media u JOIN growth_tasks t ON t.id=u.task_id AND t.revision=u.revision AND t.status='approved' JOIN growth_media_manifests a ON a.task_id=u.task_id AND a.revision=u.revision AND a.manifest_hash=u.manifest_hash AND a.status='approved' SET u.status='approved' WHERE u.id=? AND u.asset_kind='album' AND u.asset_hash=? AND u.target_id=? AND u.status='pending_review'");
        $q->execute([(int)$m[1],$m[2],$m[3]]);if($q->rowCount()!==1)return 'Album approval mismatch.';
        $q=$db->prepare('SELECT task_id,revision FROM growth_telegram_media WHERE id=?');$q->execute([(int)$m[1]]);$r=$q->fetch(PDO::FETCH_ASSOC);growthAudit($db,(int)$r['task_id'],(int)$r['revision'],'tgalbum_approved','owner');return 'Exact album approved. No network request sent.';
    }
    return 'Use /tgalbum ID /approvetgalbum ID BUNDLE_SHA256 CHANNEL_ID /canceltgmedia ID.';
}
function taClaim(PDO $db,array $cfg,int $id):array{
    creativeActive($db);$p=taTarget($cfg);if(!$p)throw new RuntimeException('Disabled');
    $q=$db->prepare("SELECT u.*,m.image_hash,m.video_hash,m.width,m.height FROM growth_telegram_media u JOIN growth_tasks t ON t.id=u.task_id AND t.revision=u.revision AND t.status='approved' JOIN growth_media_manifests m ON m.task_id=u.task_id AND m.revision=u.revision AND m.manifest_hash=u.manifest_hash AND m.status='approved' WHERE u.id=? AND u.target_id=? AND u.asset_kind='album' AND u.status='approved' FOR UPDATE");$q->execute([$id,$p['id']]);$r=$q->fetch(PDO::FETCH_ASSOC);if(!$r)throw new RuntimeException('No approved album');
    $db->prepare("UPDATE growth_telegram_media SET status='sending' WHERE id=?")->execute([$id]);growthAudit($db,(int)$r['task_id'],(int)$r['revision'],'tgalbum_claimed','private_cli');return $r;
}
function taVerify(array $r,string $png,string $mp4):bool{
    return hash_equals($r['image_hash'],hash('sha256',$png))&&hash_equals($r['video_hash'],hash('sha256',$mp4))&&strlen($png)+strlen($mp4)===(int)$r['byte_count']&&hash_equals($r['asset_hash'],taDigest($r['image_hash'],$r['video_hash'],strlen($png),strlen($mp4)));
}
function taFinish(PDO $db,array $r,array $result):void{
    if(!$db->inTransaction())throw new LogicException('Transaction required');
    $s=$result['status']??null;$g=$result['receipt']??null;$ids=$result['messages']??null;
    if(!in_array($s,['accepted','failed','unknown'],true)||($s==='accepted'?(!is_string($g)||!preg_match('/^[0-9]{1,32}$/D',$g)||!is_string($ids)||!preg_match('/^[1-9][0-9]{0,20},[1-9][0-9]{0,20}$/D',$ids)):($g!==null||$ids!==null)))throw new InvalidArgumentException('Invalid receipt');
    $q=$db->prepare("UPDATE growth_telegram_media SET status=?,receipt=?,album_messages=?,error_code=?,finished_at=UTC_TIMESTAMP() WHERE id=? AND asset_kind='album' AND status='sending'");$q->execute([$s,$g,$ids,$s==='accepted'?'':'album_unconfirmed',$r['id']]);if($q->rowCount()!==1)throw new RuntimeException('State changed');growthAudit($db,(int)$r['task_id'],(int)$r['revision'],'tgalbum_'.$s,'private_cli');
}
