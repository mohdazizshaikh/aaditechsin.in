<?php
declare(strict_types=1);
require_once __DIR__.'/oauth-policy.php';
require_once __DIR__.'/media-core.php';
function igTarget(array $cfg):?array {
    if(!oauthAllowed($cfg,'instagram'))return null;
    if(!mediaEnabled($cfg)||($cfg['social']['enabled']??false)!==true||($cfg['media_upload']['enabled']??false)!==true||($cfg['media_upload']['instagram']['enabled']??false)!==true)return null;
    $p=$cfg['media_upload']['instagram'];
    foreach(['page_id','account_id'] as $k)if(!is_string($p[$k]??null)||!preg_match('/^[1-9][0-9]{0,30}$/D',$p[$k]))return null;
    if(!is_string($p['access_token']??null)||!preg_match('/^[A-Za-z0-9._~-]{20,4096}$/D',$p['access_token'])||str_starts_with($p['access_token'],'REPLACE_')||!is_string($p['api_version']??null)||!preg_match('/^v[1-9][0-9]?\.[0-9]$/D',$p['api_version']))return null;
    return ['page'=>$p['page_id'],'id'=>$p['account_id'],'token'=>$p['access_token'],'version'=>$p['api_version']];
}
function igManifest(PDO $db,int $task,int $rev,string $manifest):array {
    creativeActive($db);
    if(!preg_match('/^[a-f0-9]{64}$/D',$manifest))throw new InvalidArgumentException('Invalid hash');
    $q=$db->prepare("SELECT m.video_hash,r.caption FROM growth_media_manifests m JOIN growth_tasks t ON t.id=m.task_id AND t.revision=m.revision AND t.status='approved' JOIN growth_creative c ON c.task_id=t.id AND c.channel='instagram' JOIN growth_creative_results r ON r.task_id=t.id AND r.revision=t.revision WHERE m.task_id=? AND m.revision=? AND m.manifest_hash=? AND m.status='approved' FOR UPDATE");
    $q->execute([$task,$rev,$manifest]);$r=$q->fetch(PDO::FETCH_ASSOC);if(!$r)throw new InvalidArgumentException('Approved Instagram manifest required');return $r;
}
function igRegister(PDO $db,array $cfg,int $task,int $rev,string $manifest,string $hash,int $bytes):int {
    $p=igTarget($cfg);if(!$p)throw new RuntimeException('Disabled');
    $r=igManifest($db,$task,$rev,$manifest);
    if(!hash_equals($r['video_hash'],$hash)||$bytes<12||$bytes>20*1024*1024||strlen($r['caption'])>900)throw new InvalidArgumentException('Asset mismatch');
    $q=$db->prepare('SELECT id FROM growth_meta_images WHERE task_id=? AND revision=?');$q->execute([$task,$rev]);if($q->fetchColumn()!==false)throw new InvalidArgumentException('Revision reserved for image');
    $q=$db->prepare('SELECT id FROM growth_instagram_uploads WHERE task_id=? AND revision=?');$q->execute([$task,$rev]);if($q->fetchColumn()!==false)throw new InvalidArgumentException('Already staged');
    $db->prepare('INSERT INTO growth_instagram_uploads(task_id,revision,manifest_hash,video_hash,byte_count,page_id,target_id,caption) VALUES(?,?,?,?,?,?,?,?)')->execute([$task,$rev,$manifest,$hash,$bytes,$p['page'],$p['id'],$r['caption']]);
    $id=(int)$db->lastInsertId();growthAudit($db,$task,$rev,'ig_staged','private_cli');return $id;
}
function igCommand(PDO $db,array $cfg,string $cmd):?string {
    if(!preg_match('/^\/(?:igreels|igreel|approveig|cancelig)(?: |$)/D',$cmd))return null;
    $p=igTarget($cfg);if(!$p)return 'Instagram Reels disabled.';
    if($cmd==='/igreels'){
        $lines=['Instagram Reel ledger (not reach metrics):'];
        foreach($db->query('SELECT id,task_id,revision,status,receipt FROM growth_instagram_uploads ORDER BY id DESC LIMIT 10')->fetchAll(PDO::FETCH_ASSOC) as $r)$lines[]='#'.$r['id'].' task '.$r['task_id'].' rev '.$r['revision'].' '.$r['status'].($r['receipt']?' receipt '.$r['receipt']:'');
        return count($lines)===1?'No staged Reel.':implode("\n",$lines);
    }
    if(preg_match('/^\/igreel ([1-9][0-9]{0,14})$/D',$cmd,$m)){
        $q=$db->prepare('SELECT * FROM growth_instagram_uploads WHERE id=?');$q->execute([(int)$m[1]]);$r=$q->fetch(PDO::FETCH_ASSOC);
        if(!$r)return 'Reel not found.';
        return '#'.$r['id'].' '.$r['status']."\nPublic Reel on IG ".$r['target_id'].' linked Page '.$r['page_id']."\n".$r['caption']."\nManifest ".$r['manifest_hash']."\nVideo ".$r['video_hash']."\nReview actual private MP4 before approval. Feed sharing OFF.";
    }
    if(preg_match('/^\/approveig ([1-9][0-9]{0,14}) ([a-f0-9]{64}) ([1-9][0-9]{0,30})$/D',$cmd,$m)){
        $paused=$db->query('SELECT paused FROM growth_control WHERE id=1 FOR UPDATE')->fetchColumn();if($paused===false||(int)$paused!==0)return 'Growth paused.';
        if($p['id']!==$m[3])return 'Target mismatch.';
        $q=$db->prepare("UPDATE growth_instagram_uploads u JOIN growth_tasks t ON t.id=u.task_id AND t.revision=u.revision AND t.status='approved' JOIN growth_media_manifests a ON a.task_id=u.task_id AND a.revision=u.revision AND a.manifest_hash=u.manifest_hash AND a.status='approved' SET u.status='approved' WHERE u.id=? AND u.manifest_hash=? AND u.target_id=? AND u.page_id=? AND u.status='pending_review'");
        $q->execute([(int)$m[1],$m[2],$m[3],$p['page']]);if($q->rowCount()!==1)return 'Reel approval mismatch or stale revision.';
        $q=$db->prepare('SELECT task_id,revision FROM growth_instagram_uploads WHERE id=?');$q->execute([(int)$m[1]]);$r=$q->fetch(PDO::FETCH_ASSOC);growthAudit($db,(int)$r['task_id'],(int)$r['revision'],'ig_approved','owner');return 'Public Reel approved for exact manifest and target. No network request sent.';
    }
    if(preg_match('/^\/cancelig ([1-9][0-9]{0,14})$/D',$cmd,$m)){
        $q=$db->prepare("SELECT task_id,revision FROM growth_instagram_uploads WHERE id=? AND status IN ('pending_review','approved','processing') FOR UPDATE");$q->execute([(int)$m[1]]);$r=$q->fetch(PDO::FETCH_ASSOC);if(!$r)return 'Only unpublished, non-in-flight Reels can be cancelled.';
        $db->prepare("UPDATE growth_instagram_uploads SET status='cancelled',finished_at=UTC_TIMESTAMP() WHERE id=?")->execute([(int)$m[1]]);growthAudit($db,(int)$r['task_id'],(int)$r['revision'],'ig_cancelled','owner');return 'Local publication cancelled. Existing provider container not deleted.';
    }
    return 'Use /igreels /igreel ID /approveig ID SHA256 ACCOUNT_ID /cancelig ID.';
}
function igRow(PDO $db,array $cfg,int $id,string $state):array {
    creativeActive($db);$p=igTarget($cfg);if(!$p)throw new RuntimeException('Disabled');
    $q=$db->prepare("SELECT u.* FROM growth_instagram_uploads u JOIN growth_tasks t ON t.id=u.task_id AND t.revision=u.revision AND t.status='approved' JOIN growth_media_manifests m ON m.task_id=u.task_id AND m.revision=u.revision AND m.manifest_hash=u.manifest_hash AND m.video_hash=u.video_hash AND m.status='approved' WHERE u.id=? AND u.page_id=? AND u.target_id=? AND u.status=? FOR UPDATE");
    $q->execute([$id,$p['page'],$p['id'],$state]);$r=$q->fetch(PDO::FETCH_ASSOC);if(!$r)throw new RuntimeException('No matching approved Reel');return $r;
}
function igClaim(PDO $db,array $cfg,int $id,string $stage):array {
    if(!in_array($stage,['upload','publish'],true))throw new InvalidArgumentException('Invalid stage');
    $r=igRow($db,$cfg,$id,$stage==='upload'?'approved':'processing');
    $state=$stage==='upload'?'uploading':'publishing';
    $db->prepare('UPDATE growth_instagram_uploads SET status=? WHERE id=?')->execute([$state,$id]);growthAudit($db,(int)$r['task_id'],(int)$r['revision'],'ig_'.$state,'private_cli');return $r;
}
function igFinish(PDO $db,array $row,string $stage,array $v):void {
    if(!$db->inTransaction())throw new LogicException('Transaction required');
    if(!in_array($stage,['upload','publish'],true))throw new InvalidArgumentException('Invalid stage');
    $status=$v['status']??null;$id=$v['id']??null;$error=$v['error']??null;
    $success=$stage==='upload'?'processing':'accepted';
    if(!in_array($status,[$success,'failed','unknown'],true)||!is_string($error)||!preg_match('/^[a-z_]{0,32}$/D',$error)||($id!==null&&(!is_string($id)||!preg_match('/^[1-9][0-9]{0,30}$/D',$id)))||($status===$success&&$id===null)||($stage==='publish'&&$status!=='accepted'&&$id!==null))throw new InvalidArgumentException('Invalid result');
    $field=$stage==='upload'?'container_id':'receipt';$from=$stage==='upload'?'uploading':'publishing';
    $q=$db->prepare("UPDATE growth_instagram_uploads SET status=?,$field=?,error_code=?,finished_at=CASE WHEN ?='processing' THEN NULL ELSE UTC_TIMESTAMP() END WHERE id=? AND status=?");
    $q->execute([$status,$id,$error,$status,$row['id'],$from]);if($q->rowCount()!==1)throw new RuntimeException('State changed');growthAudit($db,(int)$row['task_id'],(int)$row['revision'],'ig_'.$status,'private_cli');
}
