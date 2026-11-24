<?php
declare(strict_types=1);
require_once __DIR__.'/media-core.php';
require_once __DIR__.'/network-core.php';
function nmTarget(array $cfg,string $provider):?array {
    if(!mediaEnabled($cfg)||($cfg['media_upload']['enabled']??false)!==true||!in_array($provider,['linkedin','x'],true)||($cfg['media_upload'][$provider.'_enabled']??false)!==true)return null;
    return networkTarget($cfg,$provider);
}
function nmManifest(PDO $db,int $task,int $rev,string $manifest,string $provider):array {
    creativeActive($db);
    if(!preg_match('/^[a-f0-9]{64}$/D',$manifest)||!in_array($provider,['linkedin','x'],true))throw new InvalidArgumentException('Invalid asset');
    $q=$db->prepare("SELECT m.image_hash,m.video_hash,m.width,m.height,r.caption FROM growth_media_manifests m JOIN growth_tasks t ON t.id=m.task_id AND t.revision=m.revision AND t.status='approved' JOIN growth_creative c ON c.task_id=t.id AND c.channel=? JOIN growth_creative_results r ON r.task_id=t.id AND r.revision=t.revision WHERE m.task_id=? AND m.revision=? AND m.manifest_hash=? AND m.status='approved' FOR UPDATE");
    $q->execute([$provider,$task,$rev,$manifest]);$r=$q->fetch(PDO::FETCH_ASSOC);if(!$r)throw new InvalidArgumentException('Current approved image manifest required');
    $r['asset_hash']=$r['image_hash'];return $r;
}
function nmRegister(PDO $db,array $cfg,int $task,int $rev,string $manifest,string $provider,string $hash,int $bytes):int {
    $p=nmTarget($cfg,$provider);if(!$p)throw new RuntimeException('Disabled');
    $r=nmManifest($db,$task,$rev,$manifest,$provider);
    if(!hash_equals($r['asset_hash'],$hash)||$bytes<12||$bytes>($provider==='x'?5:10)*1000000||strlen($r['caption'])>($provider==='x'?280:900))throw new InvalidArgumentException('Asset mismatch');
    foreach(['growth_network_media','growth_network_publications'] as $table){
        $q=$db->prepare("SELECT id FROM $table WHERE task_id=? AND revision=?");$q->execute([$task,$rev]);if($q->fetchColumn()!==false)throw new InvalidArgumentException('Revision already reserved for media or text');
    }
    $db->prepare('INSERT INTO growth_network_media(task_id,revision,manifest_hash,asset_kind,asset_hash,byte_count,target_id,caption,provider) VALUES(?,?,?,?,?,?,?,?,?)')->execute([$task,$rev,$manifest,'photo',$hash,$bytes,$p['id'],$r['caption'],$provider]);
    $id=(int)$db->lastInsertId();growthAudit($db,$task,$rev,'netmedia_staged','private_cli');return $id;
}
function nmCommand(PDO $db,array $cfg,string $cmd):?string {
    if(!preg_match('/^\/(?:netmedia|netasset|approvenetmedia|cancelnetmedia)(?: |$)/D',$cmd))return null;
    if(!nmTarget($cfg,'linkedin')&&!nmTarget($cfg,'x'))return 'Network media disabled.';
    if($cmd==='/netmedia'){
        $lines=['Network image ledger (not verified visibility):'];
        foreach($db->query('SELECT id,task_id,revision,provider,status,receipt FROM growth_network_media ORDER BY id DESC LIMIT 10')->fetchAll(PDO::FETCH_ASSOC) as $r)$lines[]='#'.$r['id'].' task '.$r['task_id'].' rev '.$r['revision'].' '.$r['provider'].' '.$r['status'].($r['receipt']?' receipt '.$r['receipt']:'');
        return count($lines)===1?'No media staged.':implode("\n",$lines);
    }
    if(preg_match('/^\/netasset ([1-9][0-9]{0,14})$/D',$cmd,$m)){
        $q=$db->prepare('SELECT * FROM growth_network_media WHERE id=?');$q->execute([(int)$m[1]]);$r=$q->fetch(PDO::FETCH_ASSOC);if(!$r)return 'Asset not found.';
        return '#'.$r['id'].' '.$r['status']."\nPublic ".$r['provider'].' account '.$r['target_id']."\n".$r['caption']."\nManifest ".$r['manifest_hash']."\nAsset ".$r['asset_hash']."\nReview actual private asset before approval.";
    }
    if(preg_match('/^\/approvenetmedia ([1-9][0-9]{0,14}) ([a-f0-9]{64}) (linkedin|x) ([1-9][0-9]{0,30})$/D',$cmd,$m)){
        $paused=$db->query('SELECT paused FROM growth_control WHERE id=1 FOR UPDATE')->fetchColumn();if($paused===false||(int)$paused!==0)return 'Growth paused.';
        $p=nmTarget($cfg,$m[3]);if(!$p||$p['id']!==$m[4])return 'Target mismatch.';
        $q=$db->prepare("UPDATE growth_network_media u JOIN growth_tasks t ON t.id=u.task_id AND t.revision=u.revision AND t.status='approved' JOIN growth_media_manifests a ON a.task_id=u.task_id AND a.revision=u.revision AND a.manifest_hash=u.manifest_hash AND a.status='approved' SET u.status='approved' WHERE u.id=? AND u.manifest_hash=? AND u.provider=? AND u.target_id=? AND u.status='pending_review'");
        $q->execute([(int)$m[1],$m[2],$m[3],$m[4]]);if($q->rowCount()!==1)return 'Media approval mismatch or stale revision.';
        $q=$db->prepare('SELECT task_id,revision FROM growth_network_media WHERE id=?');$q->execute([(int)$m[1]]);$r=$q->fetch(PDO::FETCH_ASSOC);growthAudit($db,(int)$r['task_id'],(int)$r['revision'],'netmedia_approved','owner');return 'Public media approved for exact manifest, provider and account. No network request sent.';
    }
    if(preg_match('/^\/cancelnetmedia ([1-9][0-9]{0,14})$/D',$cmd,$m)){
        $q=$db->prepare("SELECT task_id,revision FROM growth_network_media WHERE id=? AND status IN ('pending_review','approved') FOR UPDATE");$q->execute([(int)$m[1]]);$r=$q->fetch(PDO::FETCH_ASSOC);if(!$r)return 'Only unsent media can be cancelled.';
        $db->prepare("UPDATE growth_network_media SET status='cancelled',finished_at=UTC_TIMESTAMP() WHERE id=?")->execute([(int)$m[1]]);growthAudit($db,(int)$r['task_id'],(int)$r['revision'],'netmedia_cancelled','owner');return 'Media cancelled.';
    }
    return 'Use /netmedia /netasset ID /approvenetmedia ID SHA256 PROVIDER ACCOUNT_ID /cancelnetmedia ID.';
}
function nmClaim(PDO $db,array $cfg,string $provider,int $id):array {
    creativeActive($db);$p=nmTarget($cfg);if(!$p)throw new RuntimeException('Disabled');
    $q=$db->prepare("SELECT u.*,m.width,m.height FROM growth_network_media u JOIN growth_tasks t ON t.id=u.task_id AND t.revision=u.revision AND t.status='approved' JOIN growth_media_manifests m ON m.task_id=u.task_id AND m.revision=u.revision AND m.manifest_hash=u.manifest_hash AND m.status='approved' AND u.asset_hash=m.image_hash WHERE u.id=? AND u.provider=? AND u.target_id=? AND u.status='approved' FOR UPDATE");$q->execute([$id,$provider,$p['id']]);$r=$q->fetch(PDO::FETCH_ASSOC);if(!$r)throw new RuntimeException('No approved media');
    $db->prepare("UPDATE growth_network_media SET status='sending' WHERE id=?")->execute([$id]);growthAudit($db,(int)$r['task_id'],(int)$r['revision'],'netmedia_claimed','private_cli');return $r;
}
function nmFinish(PDO $db,array $row,array $result):void {
    if(!$db->inTransaction())throw new LogicException('Transaction required');
    $s=$result['status']??null;$r=$result['receipt']??null;$e=$result['error']??null;
    if(!in_array($s,['accepted','failed','unknown'],true)||!is_string($e)||!preg_match('/^[a-z_]{0,32}$/D',$e)||($s==='accepted'&&(!is_string($r)||!preg_match($row['provider']==='linkedin'?'/^urn:li:(?:share|ugcPost):[0-9]{1,30}$/D':'/^[1-9][0-9]{0,30}$/D',$r)))||($s!=='accepted'&&$r!==null))throw new InvalidArgumentException('Invalid receipt');
    $q=$db->prepare("UPDATE growth_network_media SET status=?,receipt=?,error_code=?,finished_at=UTC_TIMESTAMP() WHERE id=? AND status='sending'");$q->execute([$s,$r,$e,$row['id']]);if($q->rowCount()!==1)throw new RuntimeException('State changed');growthAudit($db,(int)$row['task_id'],(int)$row['revision'],'netmedia_'.$s,'private_cli');
}
