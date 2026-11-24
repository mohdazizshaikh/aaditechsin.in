<?php
declare(strict_types=1);
require_once __DIR__.'/network-media-core.php';
function lvTarget(array $cfg):?array{
    if(!mediaEnabled($cfg)||($cfg['media_upload']['enabled']??false)!==true||($cfg['media_upload']['linkedin_video_enabled']??false)!==true)return null;
    return networkTarget($cfg,'linkedin');
}
function lvId(mixed $id):bool{return is_string($id)&&preg_match('/^urn:li:video:[A-Za-z0-9_-]{1,100}$/D',$id)===1;}
function lvRegister(PDO $db,array $cfg,int $task,int $rev,string $manifest,string $hash,int $bytes):int{
    $p=lvTarget($cfg);if(!$p)throw new RuntimeException('Disabled');
    $r=nmManifest($db,$task,$rev,$manifest,'linkedin');
    if(!hash_equals($r['video_hash'],$hash)||$bytes<24||$bytes>20*1024*1024||strlen($r['caption'])>900)throw new InvalidArgumentException('Asset mismatch');
    foreach(['growth_linkedin_video','growth_network_media','growth_network_publications'] as $table){
        $q=$db->prepare("SELECT id FROM $table WHERE task_id=? AND revision=?");$q->execute([$task,$rev]);if($q->fetchColumn()!==false)throw new InvalidArgumentException('Revision reserved');
    }
    $db->prepare('INSERT INTO growth_linkedin_video(task_id,revision,manifest_hash,video_hash,byte_count,target_id,caption) VALUES(?,?,?,?,?,?,?)')->execute([$task,$rev,$manifest,$hash,$bytes,$p['id'],$r['caption']]);
    $id=(int)$db->lastInsertId();growthAudit($db,$task,$rev,'lv_staged','private_cli');return $id;
}
function lvCommand(PDO $db,array $cfg,string $cmd):?string{
    if(!preg_match('/^\/(?:livideos|livideo|approvelivideo|cancellivideo)(?: |$)/D',$cmd))return null;
    $p=lvTarget($cfg);if(!$p)return 'LinkedIn video disabled.';
    if($cmd==='/livideos'){
        $lines=['LinkedIn video history (not visibility):'];
        foreach($db->query('SELECT id,status,video_id,receipt FROM growth_linkedin_video ORDER BY id DESC LIMIT 10')->fetchAll(PDO::FETCH_ASSOC) as $r)$lines[]='#'.$r['id'].' '.$r['status'].' '.($r['video_id']??'').' '.($r['receipt']??'');
        return count($lines)===1?'No videos staged.':implode("\n",$lines);
    }
    if(preg_match('/^\/livideo ([1-9][0-9]{0,14})$/D',$cmd,$m)){
        $q=$db->prepare('SELECT * FROM growth_linkedin_video WHERE id=?');$q->execute([(int)$m[1]]);$r=$q->fetch(PDO::FETCH_ASSOC);
        return !$r?'Video not found.':'#'.$r['id'].' '.$r['status']."\nPublic LinkedIn organization ".$r['target_id']."\n".$r['caption']."\nManifest ".$r['manifest_hash']."\nMP4 ".$r['video_hash']."\nReview actual private video.";
    }
    if(preg_match('/^\/approvelivideo ([1-9][0-9]{0,14}) ([a-f0-9]{64}) ([1-9][0-9]{0,30})$/D',$cmd,$m)){
        $paused=$db->query('SELECT paused FROM growth_control WHERE id=1 FOR UPDATE')->fetchColumn();if($paused===false||(int)$paused!==0)return 'Growth paused.';
        if($p['id']!==$m[3])return 'Target mismatch.';
        $q=$db->prepare("UPDATE growth_linkedin_video u JOIN growth_tasks t ON t.id=u.task_id AND t.revision=u.revision AND t.status='approved' JOIN growth_media_manifests a ON a.task_id=u.task_id AND a.revision=u.revision AND a.manifest_hash=u.manifest_hash AND a.video_hash=u.video_hash AND a.status='approved' SET u.status='approved' WHERE u.id=? AND u.manifest_hash=? AND u.target_id=? AND u.status='pending_review'");
        $q->execute([(int)$m[1],$m[2],$m[3]]);if($q->rowCount()!==1)return 'Video approval mismatch or stale revision.';
        $q=$db->prepare('SELECT task_id,revision FROM growth_linkedin_video WHERE id=?');$q->execute([(int)$m[1]]);$r=$q->fetch(PDO::FETCH_ASSOC);growthAudit($db,(int)$r['task_id'],(int)$r['revision'],'lv_approved','owner');return 'Public video approved. No network request sent.';
    }
    if(preg_match('/^\/cancellivideo ([1-9][0-9]{0,14})$/D',$cmd,$m)){
        $q=$db->prepare("SELECT task_id,revision FROM growth_linkedin_video WHERE id=? AND status IN ('pending_review','approved','processing') FOR UPDATE");$q->execute([(int)$m[1]]);$r=$q->fetch(PDO::FETCH_ASSOC);if(!$r)return 'Only non-in-flight unpublished videos can be cancelled.';
        $db->prepare("UPDATE growth_linkedin_video SET status='cancelled',finished_at=UTC_TIMESTAMP() WHERE id=?")->execute([(int)$m[1]]);growthAudit($db,(int)$r['task_id'],(int)$r['revision'],'lv_cancelled','owner');return 'Cancelled locally; provider asset not deleted.';
    }
    return 'Use /livideos /livideo ID /approvelivideo ID SHA256 ORG_ID /cancellivideo ID.';
}
function lvRow(PDO $db,array $cfg,int $id,string $state):array{
    creativeActive($db);$p=lvTarget($cfg);if(!$p)throw new RuntimeException('Disabled');
    $q=$db->prepare("SELECT u.* FROM growth_linkedin_video u JOIN growth_tasks t ON t.id=u.task_id AND t.revision=u.revision AND t.status='approved' JOIN growth_media_manifests m ON m.task_id=u.task_id AND m.revision=u.revision AND m.manifest_hash=u.manifest_hash AND m.video_hash=u.video_hash AND m.status='approved' WHERE u.id=? AND u.target_id=? AND u.status=? FOR UPDATE");$q->execute([$id,$p['id'],$state]);$r=$q->fetch(PDO::FETCH_ASSOC);if(!$r)throw new RuntimeException('No matching approved video');return $r;
}
function lvClaim(PDO $db,array $cfg,int $id,string $stage):array{
    if(!in_array($stage,['upload','publish'],true))throw new InvalidArgumentException('Invalid stage');
    $r=lvRow($db,$cfg,$id,$stage==='upload'?'approved':'processing');$s=$stage==='upload'?'uploading':'publishing';
    $db->prepare('UPDATE growth_linkedin_video SET status=? WHERE id=?')->execute([$s,$id]);growthAudit($db,(int)$r['task_id'],(int)$r['revision'],'lv_'.$s,'private_cli');return $r;
}
function lvRemember(PDO $db,array $row,string $id):void{
    if(!$db->inTransaction()||!lvId($id))throw new InvalidArgumentException('Invalid receipt');
    $q=$db->prepare("UPDATE growth_linkedin_video SET video_id=? WHERE id=? AND status='uploading' AND video_id IS NULL");$q->execute([$id,$row['id']]);if($q->rowCount()!==1)throw new RuntimeException('State changed');
    growthAudit($db,(int)$row['task_id'],(int)$row['revision'],'lv_resource','private_cli');
}
function lvFinish(PDO $db,array $row,string $stage,array $v):void{
    if(!$db->inTransaction()||!in_array($stage,['upload','publish'],true))throw new LogicException('Invalid stage');
    $s=$v['status']??null;$id=$v['receipt']??null;$e=$v['error']??null;
    if(!in_array($s,[$stage==='upload'?'processing':'accepted','failed','unknown'],true)||!is_string($e)||!preg_match('/^[a-z_]{0,32}$/D',$e))throw new InvalidArgumentException('Invalid result');
    if($stage==='upload'){
        if(($s==='processing'&&!lvId($id))||($id!==null&&!lvId($id)))throw new InvalidArgumentException('Invalid video ID');
        $q=$db->prepare("UPDATE growth_linkedin_video SET status=?,error_code=?,finished_at=CASE WHEN ?='processing' THEN NULL ELSE UTC_TIMESTAMP() END WHERE id=? AND status='uploading' AND (? IS NULL OR video_id=?)");$q->execute([$s,$e,$s,$row['id'],$id,$id]);
    }else{
        if(($s==='accepted'&&(!is_string($id)||!preg_match('/^urn:li:(?:share|ugcPost):[0-9]{1,30}$/D',$id)))||($s!=='accepted'&&$id!==null))throw new InvalidArgumentException('Invalid post receipt');
        $q=$db->prepare("UPDATE growth_linkedin_video SET status=?,receipt=?,error_code=?,finished_at=UTC_TIMESTAMP() WHERE id=? AND status='publishing'");$q->execute([$s,$id,$e,$row['id']]);
    }
    if($q->rowCount()!==1)throw new RuntimeException('State changed');growthAudit($db,(int)$row['task_id'],(int)$row['revision'],'lv_'.$s,'private_cli');
}
