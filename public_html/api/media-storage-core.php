<?php
declare(strict_types=1);
require_once __DIR__.'/meta-image-core.php';
function storageEnabled(array $cfg):bool {
    return mediaEnabled($cfg)&&($cfg['media_upload']['enabled']??false)===true&&($cfg['media_upload']['inventory_enabled']??false)===true;
}
function storageName(string $name):bool{return preg_match('/^[a-f0-9]{64}\.(?:png|jpg|mp4)$/D',$name)===1;}
function storageReferences(PDO $db,string $name):int {
    if(!storageName($name))throw new InvalidArgumentException('Invalid asset name');
    [$hash,$ext]=explode('.',$name);$queries=match($ext){
        'png'=>['SELECT COUNT(*) FROM growth_media_manifests WHERE image_hash=?','SELECT COUNT(*) FROM growth_facebook_media WHERE asset_hash=? AND asset_kind=\'photo\'','SELECT COUNT(*) FROM growth_network_media WHERE asset_hash=?'],
        'jpg'=>['SELECT COUNT(*) FROM growth_meta_images WHERE asset_hash=?'],
        'mp4'=>['SELECT COUNT(*) FROM growth_media_manifests WHERE video_hash=?','SELECT COUNT(*) FROM growth_media_uploads WHERE video_hash=?','SELECT COUNT(*) FROM growth_instagram_uploads WHERE video_hash=?','SELECT COUNT(*) FROM growth_facebook_media WHERE asset_hash=? AND asset_kind=\'video\'']
    };
    $n=0;foreach($queries as $sql){$q=$db->prepare($sql);$q->execute([$hash]);$n+=(int)$q->fetchColumn();}return $n;
}
function storageFile(string $root,string $name):array {
    if(!storageName($name))throw new InvalidArgumentException('Invalid asset name');
    $path=$root.'/'.$name;clearstatcache(true,$path);
    $s=@lstat($path);if(!$s||is_link($path)||!is_file($path)||($s['mode']&0077)!==0||$s['nlink']!==1||$s['size']<24||$s['size']>20*1024*1024)throw new RuntimeException('Unsafe asset');
    return ['path'=>$path,'size'=>(int)$s['size'],'mtime'=>(int)$s['mtime']];
}
function storageBytes(string $root,string $name):string {
    $s=storageFile($root,$name);$f=fopen($s['path'],'rb');if(!$f)throw new RuntimeException('Unavailable');
    try{$bytes=stream_get_contents($f,20*1024*1024+1);}finally{fclose($f);}
    if(!is_string($bytes)||strlen($bytes)!==$s['size']||!hash_equals(substr($name,0,64),hash('sha256',$bytes)))throw new RuntimeException('Hash mismatch');
    $ext=substr($name,65);
    if($ext==='mp4'){if(substr($bytes,4,4)!=='ftyp')throw new RuntimeException('Invalid MP4');}
    else{$info=@getimagesizefromstring($bytes);if(!$info||$info[2]!==($ext==='png'?IMAGETYPE_PNG:IMAGETYPE_JPEG))throw new RuntimeException('Invalid image');}
    return $bytes;
}
function storageInventory(PDO $db,string $root,int $limit=200):array {
    if($limit<1||$limit>500)throw new InvalidArgumentException('Invalid limit');
    $items=[];$skipped=0;$seen=0;$truncated=false;
    foreach(new DirectoryIterator($root) as $entry){
        if($entry->isDot())continue;
        if(++$seen>5000){$truncated=true;break;}
        $name=$entry->getFilename();if($name==='.quarantine')continue;
        if(!storageName($name)){++$skipped;continue;}
        try{$s=storageFile($root,$name);}catch(RuntimeException $e){++$skipped;continue;}
        if(count($items)>=$limit){$truncated=true;break;}
        $refs=storageReferences($db,$name);
        $items[]=['name'=>$name,'size'=>$s['size'],'modified_utc'=>gmdate('Y-m-d H:i:s',$s['mtime']),'references'=>$refs,'candidate'=>$refs===0&&$s['mtime']<=time()-30*86400];
    }
    return ['items'=>$items,'skipped'=>$skipped,'truncated'=>$truncated,'scope'=>'bounded inventory, not total storage'];
}
function storagePreview(PDO $db,array $cfg,string $name):string {
    if(!storageEnabled($cfg)||!storageName($name)||storageReferences($db,$name)<1)throw new RuntimeException('Unavailable');
    return storageBytes(miRoot($cfg),$name);
}
function storageOwner(array $cfg,array $session,int $now):bool {
    if(($cfg['portal']['enabled']??false)!==true||!storageEnabled($cfg)||($session['role']??null)!=='admin'||!is_int($session['last_seen']??null)||$session['last_seen']>$now||$now-$session['last_seen']>1800||!is_string($session['admin_fingerprint']??null))return false;
    return hash_equals(opsAdminSessionFingerprint($cfg),$session['admin_fingerprint']);
}
function storageQuarantineDir(string $root):string {
    $path=$root.'/.quarantine';
    if(!is_dir($path)&&!is_link($path)&&!mkdir($path,0700))throw new RuntimeException('Cannot create quarantine');
    if(is_link($path)||!is_dir($path)||realpath($path)!==$path||(fileperms($path)&0077)!==0)throw new RuntimeException('Unsafe quarantine');
    return $path;
}
function storageMove(PDO $db,array $cfg,string $name,bool $restore=false,?int $now=null):void {
    if(!$db->inTransaction()||!storageEnabled($cfg)||($cfg['media_upload']['retention_enabled']??false)!==true||!storageName($name))throw new RuntimeException('Maintenance disabled');
    // Same row lock as all staged media workers. Require global pause and stopped workers.
    $paused=$db->query('SELECT paused FROM growth_control WHERE id=1 FOR UPDATE')->fetchColumn();
    if($paused===false||(int)$paused!==1)throw new RuntimeException('Pause required');
    $root=miRoot($cfg);$q=storageQuarantineDir($root);
    $from=$restore?$q:$root;$to=$restore?$root:$q;$s=storageFile($from,$name);
    if(!$restore&&(storageReferences($db,$name)!==0||$s['mtime']>($now??time())-30*86400))throw new RuntimeException('Referenced or recent asset protected');
    storageBytes($from,$name);
    if(file_exists($to.'/'.$name)||is_link($to.'/'.$name))throw new RuntimeException('Destination exists');
    // Same-filesystem atomic move, no deletion. DB/filesystem not a single transaction.
    if(!rename($s['path'],$to.'/'.$name))throw new RuntimeException('Move failed');
    $db->prepare('INSERT INTO growth_media_storage_events(asset_name,action,byte_count) VALUES(?,?,?)')->execute([$name,$restore?'restored':'quarantined',$s['size']]);
}
