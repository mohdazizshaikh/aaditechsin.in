<?php
declare(strict_types=1);
require_once __DIR__.'/instagram-core.php';
require_once __DIR__.'/meta-core.php';
function miTarget(array $cfg,string $provider,string $kind='image'):?array {
    if(!in_array($kind,['image','video'],true)||($kind==='video'&&$provider!=='threads'))return null;
    if(!mediaEnabled($cfg)||($cfg['media_upload']['enabled']??false)!==true||($cfg['media_upload']['fetch_enabled']??false)!==true||($cfg['media_upload']['fetch_log_redaction_verified']??false)!==true||!in_array($provider,['threads','instagram'],true)||($cfg['media_upload'][$provider.($kind==='video'?'_video_enabled':'_images_enabled')]??false)!==true)return null;
    $p=$provider==='threads'?metaTarget($cfg,'threads'):igTarget($cfg);
    if(!$p)return null;
    $origin=$cfg['public_base_url']??null;
    if(!is_string($origin)||!preg_match('#^https://[a-zA-Z0-9][a-zA-Z0-9.-]*[a-zA-Z0-9]$#D',$origin))return null;
    return $p+['provider'=>$provider,'page'=>'','origin'=>$origin];
}
function miManifest(PDO $db,int $task,int $rev,string $manifest,string $provider):array {
    creativeActive($db);
    if(!preg_match('/^[a-f0-9]{64}$/D',$manifest)||!in_array($provider,['threads','instagram'],true))throw new InvalidArgumentException('Invalid manifest');
    $q=$db->prepare("SELECT m.image_hash,m.video_hash,m.width,m.height,r.caption FROM growth_media_manifests m JOIN growth_tasks t ON t.id=m.task_id AND t.revision=m.revision AND t.status='approved' JOIN growth_creative c ON c.task_id=t.id AND c.channel=? JOIN growth_creative_results r ON r.task_id=t.id AND r.revision=t.revision WHERE m.task_id=? AND m.revision=? AND m.manifest_hash=? AND m.status='approved' FOR UPDATE");
    $q->execute([$provider,$task,$rev,$manifest]);$r=$q->fetch(PDO::FETCH_ASSOC);if(!$r)throw new InvalidArgumentException('Approved matching manifest required');return $r;
}
function miImageDimensions(string $provider,int $w,int $h):array {
    if($provider==='instagram'&&[$w,$h]===[1080,1920])return [1080,1350];
    if($provider==='threads'&&[$w,$h]===[1080,1080])return [$w,$h];
    throw new RuntimeException('Unsupported image profile');
}
function miRoot(array $cfg):string {
    $path=$cfg['media_upload']['private_root']??null;
    $r=is_string($path)&&$path!==''?realpath($path):false;$w=realpath(dirname(__DIR__));
    if($r===false||$w===false||$r===$w||str_starts_with($r,$w.'/')||str_starts_with($w,$r.'/')||!is_dir($r)||is_link($path)||(fileperms($r)&0077)!==0)throw new RuntimeException('Private root required');
    return $r;
}
function miRead(string $path,string $hash,int $width,int $height,int $expected=0):string {
    if(!preg_match('/^[a-f0-9]{64}$/D',$hash)||is_link($path)||!is_file($path))throw new RuntimeException('Invalid file');
    $f=fopen($path,'rb');if(!$f)throw new RuntimeException('Cannot read');
    try{$bytes=stream_get_contents($f,8000001);}finally{fclose($f);}
    if(!is_string($bytes)||strlen($bytes)<24||strlen($bytes)>8000000||($expected!==0&&strlen($bytes)!==$expected)||!hash_equals($hash,hash('sha256',$bytes)))throw new RuntimeException('Hash mismatch');
    $info=@getimagesizefromstring($bytes);
    if(!$info||$info[2]!==IMAGETYPE_JPEG||$info[0]!==$width||$info[1]!==$height||!in_array($info['channels']??0,[3],true))throw new RuntimeException('JPEG profile mismatch');
    return $bytes;
}
function miStore(string $root,string $hash,string $bytes,int $w,int $h):void {
    if(!preg_match('/^[a-f0-9]{64}$/D',$hash)||!hash_equals($hash,hash('sha256',$bytes)))throw new RuntimeException('Hash mismatch');
    $path=$root.'/'.$hash.'.jpg';
    if(file_exists($path)||is_link($path)){miRead($path,$hash,$w,$h,strlen($bytes));return;}
    $f=fopen($path,'xb');if(!$f)throw new RuntimeException('Cannot create');
    try{if(!chmod($path,0600)||fwrite($f,$bytes)!==strlen($bytes))throw new RuntimeException('Write failed');}
    catch(Throwable $e){fclose($f);unlink($path);throw $e;}fclose($f);
}
function miRegister(PDO $db,array $cfg,string $provider,int $task,int $rev,string $manifest,string $hash,int $size,string $kind='image'):int {
    $p=miTarget($cfg,$provider,$kind);if(!$p)throw new RuntimeException('Disabled');$r=miManifest($db,$task,$rev,$manifest,$provider);
    if(!preg_match('/^[a-f0-9]{64}$/D',$hash)||$size<24||$size>($kind==='video'?20*1024*1024:8000000)||($kind==='video'&&!hash_equals($r['video_hash'],$hash))||strlen($r['caption'])>($provider==='threads'?500:900))throw new InvalidArgumentException('Invalid asset');
    foreach(['growth_carousels','growth_meta_images',$provider==='threads'?'growth_meta_publications':'growth_instagram_uploads'] as $table){
        $q=$db->prepare("SELECT id FROM $table WHERE task_id=? AND revision=?");$q->execute([$task,$rev]);if($q->fetchColumn()!==false)throw new InvalidArgumentException('Revision already reserved');
    }
    $db->prepare('INSERT INTO growth_meta_images(task_id,revision,manifest_hash,asset_hash,provider,target_id,page_id,byte_count,caption,asset_kind) VALUES(?,?,?,?,?,?,?,?,?,?)')->execute([$task,$rev,$manifest,$hash,$provider,$p['id'],$p['page'],$size,$r['caption'],$kind]);
    $id=(int)$db->lastInsertId();growthAudit($db,$task,$rev,'meta_image_staged','private_cli');return $id;
}
function miCommand(PDO $db,array $cfg,string $cmd):?string {
    $kind=str_starts_with($cmd,'/approvethreadsvideo ')?'video':'image';
    if($kind==='video')$cmd=str_replace('/approvethreadsvideo ','/approveimage ',$cmd);
    if(!preg_match('/^\/(?:metaimages|metaimage|approveimage|cancelimage)(?: |$)/D',$cmd))return null;
    if(!miTarget($cfg,'threads')&&!miTarget($cfg,'instagram')&&!miTarget($cfg,'threads','video'))return 'Meta media disabled.';
    if($cmd==='/metaimages'){
        $lines=['Meta media ledger (not visibility):'];
        foreach($db->query('SELECT id,provider,asset_kind,status,receipt FROM growth_meta_images ORDER BY id DESC LIMIT 10')->fetchAll(PDO::FETCH_ASSOC) as $r)$lines[]='#'.$r['id'].' '.$r['provider'].' '.$r['asset_kind'].' '.$r['status'].($r['receipt']?' receipt '.$r['receipt']:'');
        return count($lines)===1?'No images staged.':implode("\n",$lines);
    }
    if(preg_match('/^\/metaimage ([1-9][0-9]{0,14})$/D',$cmd,$m)){
        $q=$db->prepare('SELECT * FROM growth_meta_images WHERE id=?');$q->execute([(int)$m[1]]);$r=$q->fetch(PDO::FETCH_ASSOC);if(!$r)return 'Image not found.';
        return '#'.$r['id'].' '.$r['status']."\nPublic ".$r['provider'].' '.$r['target_id']."\n".$r['caption']."\nManifest ".$r['manifest_hash']."\nAsset ".$r['asset_kind'].' '.$r['asset_hash']."\nInspect actual asset; bearer URL accessible to anyone holding it during fetch window.";
    }
    if(preg_match('/^\/approveimage ([1-9][0-9]{0,14}) ([a-f0-9]{64}) (threads|instagram) ([1-9][0-9]{0,30})$/D',$cmd,$m)){
        $paused=$db->query('SELECT paused FROM growth_control WHERE id=1 FOR UPDATE')->fetchColumn();if($paused===false||(int)$paused!==0)return 'Growth paused.';
        $p=miTarget($cfg,$m[3],$kind);if(!$p||$p['id']!==$m[4])return 'Target mismatch.';
        $q=$db->prepare("UPDATE growth_meta_images u JOIN growth_tasks t ON t.id=u.task_id AND t.revision=u.revision AND t.status='approved' JOIN growth_media_manifests a ON a.task_id=u.task_id AND a.revision=u.revision AND a.manifest_hash=u.manifest_hash AND a.status='approved' AND (u.asset_kind='image' OR a.video_hash=u.asset_hash) SET u.status='approved' WHERE u.id=? AND u.asset_hash=? AND u.provider=? AND u.target_id=? AND u.page_id=? AND u.asset_kind=? AND u.status='pending_review'");
        $q->execute([(int)$m[1],$m[2],$m[3],$m[4],$p['page'],$kind]);if($q->rowCount()!==1)return 'Image approval mismatch or stale revision.';
        $q=$db->prepare('SELECT task_id,revision FROM growth_meta_images WHERE id=?');$q->execute([(int)$m[1]]);$r=$q->fetch(PDO::FETCH_ASSOC);growthAudit($db,(int)$r['task_id'],(int)$r['revision'],'meta_image_approved','owner');return 'Exact asset and public post approved; temporary bearer URL authorized. No request sent.';
    }
    if(preg_match('/^\/cancelimage ([1-9][0-9]{0,14})$/D',$cmd,$m)){
        $q=$db->prepare("SELECT task_id,revision FROM growth_meta_images WHERE id=? AND status IN ('pending_review','approved','processing') FOR UPDATE");$q->execute([(int)$m[1]]);$r=$q->fetch(PDO::FETCH_ASSOC);if(!$r)return 'Only non-in-flight unpublished images can be cancelled.';
        $db->prepare("UPDATE growth_meta_images SET status='cancelled',token_hash=NULL WHERE id=?")->execute([(int)$m[1]]);growthAudit($db,(int)$r['task_id'],(int)$r['revision'],'meta_image_cancelled','owner');return 'Cancelled locally; provider copies are not deleted.';
    }
    return 'Use /metaimages /metaimage ID /approveimage ID JPEG_SHA256 PROVIDER ACCOUNT_ID /approvethreadsvideo ID MP4_SHA256 threads ACCOUNT_ID /cancelimage ID.';
}
function miRow(PDO $db,array $cfg,string $provider,int $id,string $state):array {
    creativeActive($db);$p=miTarget($cfg,$provider)??miTarget($cfg,$provider,'video');if(!$p)throw new RuntimeException('Disabled');
    $q=$db->prepare("SELECT u.*,m.width,m.height FROM growth_meta_images u JOIN growth_tasks t ON t.id=u.task_id AND t.revision=u.revision AND t.status='approved' JOIN growth_media_manifests m ON m.task_id=u.task_id AND m.revision=u.revision AND m.manifest_hash=u.manifest_hash AND m.status='approved' AND (u.asset_kind='image' OR m.video_hash=u.asset_hash) WHERE u.id=? AND u.provider=? AND u.target_id=? AND u.page_id=? AND u.status=? FOR UPDATE");
    $q->execute([$id,$provider,$p['id'],$p['page'],$state]);$r=$q->fetch(PDO::FETCH_ASSOC);if(!$r||!miTarget($cfg,$provider,$r['asset_kind']))throw new RuntimeException('No matching approved asset');return $r;
}
function miClaimFetch(PDO $db,array $cfg,string $provider,int $id):array {
    $r=miRow($db,$cfg,$provider,$id,'approved');$token=bin2hex(random_bytes(32));
    $db->prepare("UPDATE growth_meta_images SET status='fetching',token_hash=?,expires_at=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 15 MINUTE),fetch_count=0 WHERE id=?")->execute([hash('sha256',$token),$id]);
    growthAudit($db,(int)$r['task_id'],(int)$r['revision'],'meta_image_fetching','private_cli');return $r+['fetch_token'=>$token];
}
function miFetch(PDO $db,array $cfg,string $token,bool $head=false):array {
    creativeActive($db);
    if(!preg_match('/^[a-f0-9]{64}$/D',$token))throw new RuntimeException('Unavailable');
    $q=$db->prepare("SELECT u.*,m.width,m.height FROM growth_meta_images u JOIN growth_tasks t ON t.id=u.task_id AND t.revision=u.revision AND t.status='approved' JOIN growth_media_manifests m ON m.task_id=u.task_id AND m.revision=u.revision AND m.manifest_hash=u.manifest_hash AND m.status='approved' AND (u.asset_kind='image' OR m.video_hash=u.asset_hash) WHERE u.token_hash=? AND u.status IN ('fetching','processing') AND u.expires_at>UTC_TIMESTAMP() AND u.fetch_count<20 FOR UPDATE");
    $q->execute([hash('sha256',$token)]);$r=$q->fetch(PDO::FETCH_ASSOC);if(!$r)throw new RuntimeException('Unavailable');
    $p=miTarget($cfg,$r['provider'],$r['asset_kind']);if(!$p||$p['id']!==$r['target_id']||$p['page']!==$r['page_id'])throw new RuntimeException('Unavailable');
    $bytes=miAssetBytes($cfg,$r);
    $db->prepare('UPDATE growth_meta_images SET fetch_count=fetch_count+1 WHERE id=?')->execute([$r['id']]);return ['bytes'=>$head?'':$bytes,'size'=>strlen($bytes),'kind'=>$r['asset_kind']];
}
function miFinishFetch(PDO $db,array $r,?string $container):void {
    if(!$db->inTransaction())throw new LogicException('Transaction required');
    $ok=is_string($container)&&preg_match('/^[1-9][0-9]{0,30}$/D',$container);
    $q=$db->prepare("UPDATE growth_meta_images SET status=?,container_id=?,token_hash=CASE WHEN ?='processing' THEN token_hash ELSE NULL END,error_code=? WHERE id=? AND status='fetching'");
    $s=$ok?'processing':'unknown';$q->execute([$s,$ok?$container:null,$s,$ok?'':'container_unverified',$r['id']]);if($q->rowCount()!==1)throw new RuntimeException('State changed');
    growthAudit($db,(int)$r['task_id'],(int)$r['revision'],'meta_image_'.$s,'private_cli');
}
function miClaimPublish(PDO $db,array $cfg,string $provider,int $id):array {
    $r=miRow($db,$cfg,$provider,$id,'processing');
    $db->prepare("UPDATE growth_meta_images SET status='publishing',token_hash=NULL WHERE id=?")->execute([$id]);growthAudit($db,(int)$r['task_id'],(int)$r['revision'],'meta_image_publishing','private_cli');return $r;
}
function miFinishPublish(PDO $db,array $r,?string $receipt):void {
    if(!$db->inTransaction())throw new LogicException('Transaction required');
    $ok=is_string($receipt)&&preg_match('/^[1-9][0-9]{0,30}$/D',$receipt);
    $q=$db->prepare("UPDATE growth_meta_images SET status=?,receipt=?,error_code=?,token_hash=NULL WHERE id=? AND status='publishing'");
    $s=$ok?'accepted':'unknown';$q->execute([$s,$ok?$receipt:null,$ok?'':'publish_unverified',$r['id']]);if($q->rowCount()!==1)throw new RuntimeException('State changed');growthAudit($db,(int)$r['task_id'],(int)$r['revision'],'meta_image_'.$s,'private_cli');
}

function miAssetBytes(array $cfg,array $r):string{
    $root=miRoot($cfg);$hash=$r['asset_hash'];
    if(!preg_match('/^[a-f0-9]{64}$/D',$hash))throw new RuntimeException('Invalid hash');
    if($r['asset_kind']==='video'){
        $path=$root.'/'.$hash.'.mp4';
        if(is_link($path)||!is_file($path))throw new RuntimeException('Unavailable');
        $f=fopen($path,'rb');if(!$f)throw new RuntimeException('Unavailable');
        try{$bytes=stream_get_contents($f,20*1024*1024+1);}finally{fclose($f);}
        if(!is_string($bytes)||strlen($bytes)<24||strlen($bytes)>20*1024*1024||strlen($bytes)!==(int)$r['byte_count']||substr($bytes,4,4)!=='ftyp'||!hash_equals($hash,hash('sha256',$bytes)))throw new RuntimeException('Invalid video');
        return $bytes;
    }
    [$w,$h]=miImageDimensions($r['provider'],(int)$r['width'],(int)$r['height']);
    return miRead($root.'/'.$hash.'.jpg',$hash,$w,$h,(int)$r['byte_count']);
}
