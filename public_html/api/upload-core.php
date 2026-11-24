<?php
declare(strict_types=1);
require_once __DIR__.'/media-core.php';
function uploadTarget(array $cfg):?array {
    if(!mediaEnabled($cfg)||($cfg['social']['enabled']??false)!==true||($cfg['media_upload']['enabled']??false)!==true||($cfg['media_upload']['youtube']['enabled']??false)!==true)return null;
    $p=$cfg['media_upload']['youtube'];$id=$p['channel_id']??null;$token=$p['access_token']??null;
    if(!is_string($id)||!preg_match('/^UC[A-Za-z0-9_-]{22}$/D',$id)||!is_string($token)||!preg_match('/^[A-Za-z0-9._~+\/=-]{20,4096}$/D',$token)||str_starts_with($token,'REPLACE_'))return null;
    return ['id'=>$id,'token'=>$token];
}
function uploadManifest(PDO $db,int $task,int $rev,string $manifest):array {
    creativeActive($db);
    if(!preg_match('/^[a-f0-9]{64}$/D',$manifest))throw new InvalidArgumentException('Invalid manifest hash');
    $q=$db->prepare("SELECT m.video_hash,m.width,m.height,r.caption FROM growth_media_manifests m JOIN growth_tasks t ON t.id=m.task_id AND t.revision=m.revision AND t.status='approved' JOIN growth_creative c ON c.task_id=t.id AND c.channel='youtube' JOIN growth_creative_results r ON r.task_id=t.id AND r.revision=t.revision WHERE m.task_id=? AND m.revision=? AND m.manifest_hash=? AND m.status='approved' FOR UPDATE");
    $q->execute([$task,$rev,$manifest]);$r=$q->fetch(PDO::FETCH_ASSOC);
    if(!$r)throw new InvalidArgumentException('Current approved YouTube manifest required');return $r;
}
function uploadMetadata(array $m):array {
    $keys=['title','description','category_id','visibility','made_for_kids','synthetic_content'];
    if(count($m)!==count($keys)||array_diff(array_keys($m),$keys))throw new InvalidArgumentException('Exact metadata required');
    foreach(['title'=>100,'description'=>4000] as $k=>$max){
        if(!is_string($m[$k])||trim($m[$k])===''||strlen($m[$k])>$max||!preg_match('//u',$m[$k])||preg_match('/[<>\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/',$m[$k]))throw new InvalidArgumentException('Invalid metadata');
    }
    if(!is_string($m['category_id'])||!preg_match('/^[1-9][0-9]{0,2}$/D',$m['category_id'])||!in_array($m['visibility'],['private','unlisted','public'],true)||!is_bool($m['made_for_kids'])||!is_bool($m['synthetic_content']))throw new InvalidArgumentException('Invalid upload options');
    return $m;
}
function uploadRegister(PDO $db,array $cfg,int $task,int $rev,string $manifest,string $videoHash,int $bytes,array $metadata):int {
    $p=uploadTarget($cfg);if(!$p)throw new RuntimeException('Upload disabled');
    $r=uploadManifest($db,$task,$rev,$manifest);$m=uploadMetadata($metadata);
    if(!hash_equals($r['video_hash'],$videoHash)||$bytes<12||$bytes>20*1024*1024)throw new InvalidArgumentException('Asset mismatch');
    $q=$db->prepare('SELECT id FROM growth_media_uploads WHERE task_id=? AND revision=?');$q->execute([$task,$rev]);if($q->fetchColumn()!==false)throw new InvalidArgumentException('Upload already recorded');
    $db->prepare('INSERT INTO growth_media_uploads(task_id,revision,manifest_hash,video_hash,byte_count,target_id,visibility,title,description,category_id,made_for_kids,synthetic_content) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)')->execute([$task,$rev,$manifest,$videoHash,$bytes,$p['id'],$m['visibility'],$m['title'],$m['description'],$m['category_id'],(int)$m['made_for_kids'],(int)$m['synthetic_content']]);
    $id=(int)$db->lastInsertId();growthAudit($db,$task,$rev,'upload_staged','private_cli');return $id;
}
function uploadCommand(PDO $db,array $cfg,string $cmd):?string {
    if(!preg_match('/^\/(?:uploads|upload|approveupload|cancelupload)(?: |$)/D',$cmd))return null;
    if(!uploadTarget($cfg))return 'YouTube upload disabled or unconfigured.';
    if($cmd==='/uploads'){
        $lines=['YouTube uploads (provider receipt is not visibility):'];
        foreach($db->query('SELECT id,task_id,revision,status,visibility,receipt FROM growth_media_uploads ORDER BY id DESC LIMIT 10')->fetchAll(PDO::FETCH_ASSOC) as $r)$lines[]='#'.$r['id'].' task '.$r['task_id'].' rev '.$r['revision'].' '.$r['visibility'].' '.$r['status'].($r['receipt']?' receipt '.$r['receipt']:'');
        return count($lines)===1?'No upload staged.':implode("\n",$lines);
    }
    if(preg_match('/^\/upload ([1-9][0-9]{0,14})$/D',$cmd,$m)){
        $q=$db->prepare('SELECT * FROM growth_media_uploads WHERE id=?');$q->execute([(int)$m[1]]);$r=$q->fetch(PDO::FETCH_ASSOC);if(!$r)return 'Upload not found.';
        return '#'.$r['id'].' '.$r['status'].' YouTube '.$r['target_id']."\nVisibility: ".$r['visibility']."\nTitle: ".$r['title']."\nDescription: ".$r['description']."\nCategory: ".$r['category_id']." | made for kids: ".$r['made_for_kids']." | synthetic: ".$r['synthetic_content']."\nManifest: ".$r['manifest_hash']."\nVideo: ".$r['video_hash']."\nReview actual private asset before approval.";
    }
    if(preg_match('/^\/approveupload ([1-9][0-9]{0,14}) ([a-f0-9]{64}) (private|unlisted|public) (UC[A-Za-z0-9_-]{22})$/D',$cmd,$m)){
        creativeActive($db);$p=uploadTarget($cfg);
        if($p['id']!==$m[4])return 'Destination mismatch.';
        $q=$db->prepare("UPDATE growth_media_uploads u JOIN growth_tasks t ON t.id=u.task_id AND t.revision=u.revision AND t.status='approved' JOIN growth_media_manifests a ON a.task_id=u.task_id AND a.revision=u.revision AND a.manifest_hash=u.manifest_hash AND a.status='approved' SET u.status='approved' WHERE u.id=? AND u.manifest_hash=? AND u.visibility=? AND u.target_id=? AND u.status='pending_review'");
        $q->execute([(int)$m[1],$m[2],$m[3],$m[4]]);if($q->rowCount()!==1)return 'Upload approval mismatch or stale revision.';
        $q=$db->prepare('SELECT task_id,revision FROM growth_media_uploads WHERE id=?');$q->execute([(int)$m[1]]);$r=$q->fetch(PDO::FETCH_ASSOC);growthAudit($db,(int)$r['task_id'],(int)$r['revision'],'upload_approved','owner');return 'Exact upload approved. No network request sent.';
    }
    if(preg_match('/^\/cancelupload ([1-9][0-9]{0,14})$/D',$cmd,$m)){
        $q=$db->prepare("SELECT task_id,revision FROM growth_media_uploads WHERE id=? AND status IN ('pending_review','approved') FOR UPDATE");$q->execute([(int)$m[1]]);$r=$q->fetch(PDO::FETCH_ASSOC);if(!$r)return 'Only unsent uploads can be cancelled.';
        $db->prepare("UPDATE growth_media_uploads SET status='cancelled',finished_at=UTC_TIMESTAMP() WHERE id=?")->execute([(int)$m[1]]);growthAudit($db,(int)$r['task_id'],(int)$r['revision'],'upload_cancelled','owner');return 'Upload cancelled.';
    }
    return 'Use /uploads /upload ID /approveupload ID SHA256 VISIBILITY CHANNEL_ID /cancelupload ID.';
}
function uploadClaim(PDO $db,array $cfg,int $id):array {
    creativeActive($db);$p=uploadTarget($cfg);if(!$p)throw new RuntimeException('Disabled');
    $q=$db->prepare("SELECT u.* FROM growth_media_uploads u JOIN growth_tasks t ON t.id=u.task_id AND t.revision=u.revision AND t.status='approved' JOIN growth_media_manifests m ON m.task_id=u.task_id AND m.revision=u.revision AND m.manifest_hash=u.manifest_hash AND m.status='approved' AND m.video_hash=u.video_hash WHERE u.id=? AND u.target_id=? AND u.status='approved' FOR UPDATE");$q->execute([$id,$p['id']]);$r=$q->fetch(PDO::FETCH_ASSOC);if(!$r)throw new RuntimeException('No approved upload');
    $db->prepare("UPDATE growth_media_uploads SET status='sending' WHERE id=?")->execute([$id]);growthAudit($db,(int)$r['task_id'],(int)$r['revision'],'upload_claimed','private_cli');return $r;
}
function uploadFinish(PDO $db,array $row,array $result):void {
    if(!$db->inTransaction())throw new LogicException('Transaction required');
    $s=$result['status']??null;$r=$result['receipt']??null;$e=$result['error']??null;
    if(!in_array($s,['accepted','failed','unknown'],true)||!is_string($e)||!preg_match('/^[a-z_]{0,32}$/D',$e)||($s==='accepted'&&(!is_string($r)||!preg_match('/^[A-Za-z0-9_-]{11}$/D',$r)))||($s!=='accepted'&&$r!==null))throw new InvalidArgumentException('Invalid receipt');
    $q=$db->prepare("UPDATE growth_media_uploads SET status=?,receipt=?,error_code=?,finished_at=UTC_TIMESTAMP() WHERE id=? AND status='sending'");$q->execute([$s,$r,$e,$row['id']]);if($q->rowCount()!==1)throw new RuntimeException('Upload state changed');growthAudit($db,(int)$row['task_id'],(int)$row['revision'],'upload_'.$s,'private_cli');
}
