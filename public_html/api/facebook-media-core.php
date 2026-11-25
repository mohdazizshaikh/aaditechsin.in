<?php
declare(strict_types=1);
require_once __DIR__.'/media-core.php';
require_once __DIR__.'/meta-core.php';
function fbMediaTarget(array $cfg):?array {
    if(!mediaEnabled($cfg)||($cfg['media_upload']['enabled']??false)!==true||($cfg['media_upload']['facebook_enabled']??false)!==true)return null;
    return metaTarget($cfg,'facebook');
}
function fbMediaManifest(PDO $db,int $task,int $rev,string $manifest,string $kind):array {
    creativeActive($db);
    if(!preg_match('/^[a-f0-9]{64}$/D',$manifest)||!in_array($kind,['photo','video'],true))throw new InvalidArgumentException('Invalid asset');
    $q=$db->prepare("SELECT m.image_hash,m.video_hash,m.width,m.height,r.caption FROM growth_media_manifests m JOIN growth_tasks t ON t.id=m.task_id AND t.revision=m.revision AND t.status='approved' JOIN growth_creative c ON c.task_id=t.id AND c.channel='facebook' JOIN growth_creative_results r ON r.task_id=t.id AND r.revision=t.revision WHERE m.task_id=? AND m.revision=? AND m.manifest_hash=? AND m.status='approved' FOR UPDATE");
    $q->execute([$task,$rev,$manifest]);$r=$q->fetch(PDO::FETCH_ASSOC);if(!$r)throw new InvalidArgumentException('Current approved Facebook manifest required');
    $r['asset_hash']=$r[$kind==='photo'?'image_hash':'video_hash'];return $r;
}
function fbMediaRegister(PDO $db,array $cfg,int $task,int $rev,string $manifest,string $kind,string $hash,int $bytes):int {
    $p=fbMediaTarget($cfg);if(!$p)throw new RuntimeException('Disabled');
    $r=fbMediaManifest($db,$task,$rev,$manifest,$kind);
    if(!hash_equals($r['asset_hash'],$hash)||$bytes<12||$bytes>($kind==='photo'?10:20)*1024*1024||strlen($r['caption'])>900)throw new InvalidArgumentException('Asset mismatch');
    foreach(['growth_facebook_media','growth_meta_publications'] as $table){
        $q=$db->prepare("SELECT id FROM $table WHERE task_id=? AND revision=?");$q->execute([$task,$rev]);if($q->fetchColumn()!==false)throw new InvalidArgumentException('Revision already reserved for media or text');
    }
    $db->prepare('INSERT INTO growth_facebook_media(task_id,revision,manifest_hash,asset_kind,asset_hash,byte_count,target_id,caption) VALUES(?,?,?,?,?,?,?,?)')->execute([$task,$rev,$manifest,$kind,$hash,$bytes,$p['id'],$r['caption']]);
    $id=(int)$db->lastInsertId();growthAudit($db,$task,$rev,'fbmedia_staged','private_cli');return $id;
}
function fbMediaCommand(PDO $db,array $cfg,string $cmd):?string {
    if(!preg_match('/^\/(?:fbmedia|fbasset|approvefbmedia|cancelfbmedia)(?: |$)/D',$cmd))return null;
    $p=fbMediaTarget($cfg);if(!$p)return 'Facebook media disabled.';
    if($cmd==='/fbmedia'){
        $lines=['Facebook media ledger (not verified visibility):'];
        foreach($db->query('SELECT id,task_id,revision,asset_kind,status,receipt FROM growth_facebook_media ORDER BY id DESC LIMIT 10')->fetchAll(PDO::FETCH_ASSOC) as $r)$lines[]='#'.$r['id'].' task '.$r['task_id'].' rev '.$r['revision'].' '.$r['asset_kind'].' '.$r['status'].($r['receipt']?' receipt '.$r['receipt']:'');
        return count($lines)===1?'No media staged.':implode("\n",$lines);
    }
    if(preg_match('/^\/fbasset ([1-9][0-9]{0,14})$/D',$cmd,$m)){
        $q=$db->prepare('SELECT * FROM growth_facebook_media WHERE id=?');$q->execute([(int)$m[1]]);$r=$q->fetch(PDO::FETCH_ASSOC);if(!$r)return 'Asset not found.';
        return '#'.$r['id'].' '.$r['status']."\nPublic Facebook Page ".$r['target_id'].' '.$r['asset_kind']."\n".$r['caption']."\nManifest ".$r['manifest_hash']."\nAsset ".$r['asset_hash']."\nReview actual private asset before approval.";
    }
    if(preg_match('/^\/approvefbmedia ([1-9][0-9]{0,14}) ([a-f0-9]{64}) (photo|video) ([1-9][0-9]{0,30})$/D',$cmd,$m)){
        $paused=$db->query('SELECT paused FROM growth_control WHERE id=1 FOR UPDATE')->fetchColumn();if($paused===false||(int)$paused!==0)return 'Growth paused.';
        if($p['id']!==$m[4])return 'Target mismatch.';
        $q=$db->prepare("UPDATE growth_facebook_media u JOIN growth_tasks t ON t.id=u.task_id AND t.revision=u.revision AND t.status='approved' JOIN growth_media_manifests a ON a.task_id=u.task_id AND a.revision=u.revision AND a.manifest_hash=u.manifest_hash AND a.status='approved' SET u.status='approved' WHERE u.id=? AND u.manifest_hash=? AND u.asset_kind=? AND u.target_id=? AND u.status='pending_review'");
        $q->execute([(int)$m[1],$m[2],$m[3],$m[4]]);if($q->rowCount()!==1)return 'Media approval mismatch or stale revision.';
        $q=$db->prepare('SELECT task_id,revision FROM growth_facebook_media WHERE id=?');$q->execute([(int)$m[1]]);$r=$q->fetch(PDO::FETCH_ASSOC);growthAudit($db,(int)$r['task_id'],(int)$r['revision'],'fbmedia_approved','owner');return 'Public media approved for exact manifest, type and Page. No network request sent.';
    }
    if(preg_match('/^\/cancelfbmedia ([1-9][0-9]{0,14})$/D',$cmd,$m)){
        $q=$db->prepare("SELECT task_id,revision FROM growth_facebook_media WHERE id=? AND status IN ('pending_review','approved') FOR UPDATE");$q->execute([(int)$m[1]]);$r=$q->fetch(PDO::FETCH_ASSOC);if(!$r)return 'Only unsent media can be cancelled.';
        $db->prepare("UPDATE growth_facebook_media SET status='cancelled',finished_at=UTC_TIMESTAMP() WHERE id=?")->execute([(int)$m[1]]);growthAudit($db,(int)$r['task_id'],(int)$r['revision'],'fbmedia_cancelled','owner');return 'Media cancelled.';
    }
    return 'Use /fbmedia /fbasset ID /approvefbmedia ID SHA256 photo|video PAGE_ID /cancelfbmedia ID.';
}
function fbMediaClaim(PDO $db,array $cfg,int $id):array {
    creativeActive($db);$p=fbMediaTarget($cfg);if(!$p)throw new RuntimeException('Disabled');
    $q=$db->prepare("SELECT u.*,m.width,m.height FROM growth_facebook_media u JOIN growth_tasks t ON t.id=u.task_id AND t.revision=u.revision AND t.status='approved' JOIN growth_media_manifests m ON m.task_id=u.task_id AND m.revision=u.revision AND m.manifest_hash=u.manifest_hash AND m.status='approved' AND u.asset_hash=CASE WHEN u.asset_kind='photo' THEN m.image_hash ELSE m.video_hash END WHERE u.id=? AND u.target_id=? AND u.status='approved' FOR UPDATE");$q->execute([$id,$p['id']]);$r=$q->fetch(PDO::FETCH_ASSOC);if(!$r)throw new RuntimeException('No approved media');
    $db->prepare("UPDATE growth_facebook_media SET status='sending' WHERE id=?")->execute([$id]);growthAudit($db,(int)$r['task_id'],(int)$r['revision'],'fbmedia_claimed','private_cli');return $r;
}
function fbMediaFinish(PDO $db,array $row,array $result):void {
    if(!$db->inTransaction())throw new LogicException('Transaction required');
    $s=$result['status']??null;$r=$result['receipt']??null;$e=$result['error']??null;
    if(!in_array($s,['accepted','failed','unknown'],true)||!is_string($e)||!preg_match('/^[a-z_]{0,32}$/D',$e)||($s==='accepted'&&(!is_string($r)||!preg_match('/^[1-9][0-9]{0,30}$/D',$r)))||($s!=='accepted'&&$r!==null))throw new InvalidArgumentException('Invalid receipt');
    $q=$db->prepare("UPDATE growth_facebook_media SET status=?,receipt=?,error_code=?,finished_at=UTC_TIMESTAMP() WHERE id=? AND status='sending'");$q->execute([$s,$r,$e,$row['id']]);if($q->rowCount()!==1)throw new RuntimeException('State changed');growthAudit($db,(int)$row['task_id'],(int)$row['revision'],'fbmedia_'.$s,'private_cli');
}
