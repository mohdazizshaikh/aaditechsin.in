<?php
declare(strict_types=1);
require_once __DIR__.'/creative-core.php';
function mediaEnabled(array $cfg):bool {
    return creativeEnabled($cfg)&&($cfg['media']['enabled']??false)===true;
}
function mediaDimensions(string $channel):array {
    return match($channel){
        'instagram','youtube'=>[1080,1920],
        'threads','telegram'=>[1080,1080],
        'facebook','linkedin','google-business','x','blog'=>[1200,628],
        default=>throw new InvalidArgumentException('Unknown channel')
    };
}
function mediaExport(PDO $db,int $id,int $rev):array {
    creativeActive($db);
    $q=$db->prepare("SELECT t.service_id,c.channel,r.caption,r.visual_brief,r.video_brief FROM growth_tasks t JOIN growth_creative c ON c.task_id=t.id JOIN growth_creative_results r ON r.task_id=t.id AND r.revision=t.revision WHERE t.id=? AND t.revision=? AND t.status='approved' FOR UPDATE");
    $q->execute([$id,$rev]);$r=$q->fetch(PDO::FETCH_ASSOC);
    if(!$r)throw new RuntimeException('Current approved text revision required');
    $svc=growthCatalog()[$r['service_id']]??null;if(!$svc)throw new RuntimeException('Unknown service');
    return ['version'=>1,'task_id'=>$id,'revision'=>$rev,'channel'=>$r['channel'],'service_title'=>$svc['title'],'caption'=>$r['caption'],'visual_brief'=>$r['visual_brief'],'video_brief'=>$r['video_brief'],'dimensions'=>mediaDimensions($r['channel'])];
}
function mediaRegister(PDO $db,string $raw):array {
    if(strlen($raw)>4096)throw new InvalidArgumentException('Manifest too large');
    $m=json_decode($raw,true,16,JSON_THROW_ON_ERROR);
    $keys=['version','task_id','revision','channel','renderer','width','height','image_sha256','video_sha256'];
    if(!is_array($m)||count($m)!==count($keys)||array_diff(array_keys($m),$keys)||($m['version']??null)!==1)throw new InvalidArgumentException('Invalid manifest');
    foreach(['task_id','revision','width','height'] as $k)if(!is_int($m[$k])||$m[$k]<1)throw new InvalidArgumentException('Invalid number');
    foreach(['image_sha256','video_sha256'] as $k)if(!is_string($m[$k])||!preg_match('/^[a-f0-9]{64}$/D',$m[$k]))throw new InvalidArgumentException('Invalid hash');
    if(!is_string($m['channel'])||!in_array($m['renderer'],['local-diffusion','template'],true))throw new InvalidArgumentException('Invalid renderer');
    $job=mediaExport($db,$m['task_id'],$m['revision']);
    if($m['channel']!==$job['channel']||[$m['width'],$m['height']]!==$job['dimensions'])throw new InvalidArgumentException('Profile mismatch');
    $hash=hash('sha256',$raw);
    $db->prepare("INSERT INTO growth_media_manifests(task_id,revision,manifest_hash,image_hash,video_hash,renderer,width,height) VALUES(?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE manifest_hash=VALUES(manifest_hash)")->execute([$m['task_id'],$m['revision'],$hash,$m['image_sha256'],$m['video_sha256'],$m['renderer'],$m['width'],$m['height']]);
    return ['manifest_sha256'=>$hash,'status'=>'registered','note'=>'Metadata only; local binary files not verified by server'];
}
function mediaCommand(PDO $db,array $cfg,string $command):?string {
    if(!preg_match('/^\\/(?:media|approvemedia|rejectmedia)(?: |$)/D',$command))return null;
    if(!mediaEnabled($cfg))return 'Media studio disabled or unconfigured.';
    if(preg_match('/^\\/media ([1-9][0-9]{0,14})$/D',$command,$m)){
        $q=$db->prepare("SELECT m.revision,m.manifest_hash,m.renderer,m.status,m.width,m.height,t.status AS text_status,t.revision AS current_revision FROM growth_media_manifests m JOIN growth_tasks t ON t.id=m.task_id WHERE m.task_id=? ORDER BY m.created_at DESC,m.manifest_hash LIMIT 5");$q->execute([(int)$m[1]]);
        $lines=['Media metadata (review actual files privately; not server-verified):'];
        foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r)$lines[]='rev '.$r['revision'].' '.$r['renderer'].' '.$r['width'].'x'.$r['height'].' '.$r['status'].((int)$r['revision']!==(int)$r['current_revision']||$r['text_status']!=='approved'?' [stale]':'')."\n".$r['manifest_hash'];
        return count($lines)===1?'No media manifests registered.':implode("\n",$lines);
    }
    if(preg_match('/^\\/(approve|reject)media ([1-9][0-9]{0,14}) ([1-9][0-9]{0,8}) ([a-f0-9]{64})$/D',$command,$m)){
        $p=$db->query('SELECT paused FROM growth_control WHERE id=1 FOR UPDATE')->fetchColumn();
        if($p===false||(int)$p!==0)return 'Growth paused.';
        $q=$db->prepare("UPDATE growth_media_manifests m JOIN growth_tasks t ON t.id=m.task_id SET m.status=?,m.reviewed_at=UTC_TIMESTAMP() WHERE m.task_id=? AND m.revision=? AND m.manifest_hash=? AND m.status='pending_review' AND t.revision=m.revision AND t.status='approved'");
        $q->execute([$m[1]==='approve'?'approved':'rejected',(int)$m[2],(int)$m[3],$m[4]]);
        if($q->rowCount()!==1)return 'Manifest not pending for the current approved text revision.';
        growthAudit($db,(int)$m[2],(int)$m[3],$m[1].'_media','owner');
        return 'Media review recorded for exact manifest. No upload or publication authorized.';
    }
    return 'Use /media ID or /approvemedia ID REV SHA256 or /rejectmedia ID REV SHA256.';
}
